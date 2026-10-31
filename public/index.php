<?php
declare(strict_types=1);
define('DATA', getenv('NOTIZBLOCK_DATA') ?: '/var/lib/notizblock');
const COOKIE = '__Secure-notizblock';
const LIFE = 31536000;
require dirname(__DIR__).'/storage.php';
require dirname(__DIR__).'/merge.php';
ini_set('display_errors','0');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' blob:; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
function out(array $data,int $code=200): never { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
function sql(string $q,array $p=[]): PDOStatement { global $db; $s=$db->prepare($q);$s->execute($p);return $s; }
function cookie(string $token,int $expiry): void {setcookie(COOKIE,$token,['expires'=>$expiry,'path'=>'/notizblock/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);}
function sessionRow(): array|false { $t=$_COOKIE[COOKIE]??''; if(!preg_match('/^[a-f0-9]{64}$/D',$t))return false; return sql('SELECT * FROM sessions WHERE token=? AND expires>?',[hash('sha256',$t),time()])->fetch(); }
function csrf(array $s): void {if(!hash_equals($s['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))out(['error'=>'Bitte neu laden und erneut versuchen.'],403);}
function note(int $id): array { $n=sql('SELECT * FROM notes WHERE id=? AND deleted<2',[$id])->fetch();if(!$n)out(['error'=>'Eintrag nicht gefunden.'],404);$n['items']=json_decode($n['items'],true);$n['images']=sql('SELECT id FROM images WHERE note_id=? ORDER BY id',[$id])->fetchAll();return $n; }
function changed(int $id,int $version): void { $n=note($id);if((int)$n['version']!==$version)out(['error'=>'Dieser Eintrag wurde auf einem anderen Gerät geändert. Dein Text bleibt hier erhalten. Bitte kopiere ihn vor dem Neuladen.','conflict'=>true],409); }
function textval(mixed $v,int $max): string {if(!is_string($v)||strlen($v)>$max||!preg_match('//u',$v))out(['error'=>'Text ist ungültig oder zu lang.'],422);return $v;}
try {
 if(!is_dir(DATA)||!is_file(DATA.'/password.hash')) { http_response_code(503);header('Content-Type: text/plain; charset=utf-8');echo 'Noch nicht eingerichtet.';exit; }
 $db=new PDO('sqlite:'.DATA.'/notes.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON;');
 $action=$_GET['action']??'';
 $s=sessionRow();
 if($s)nbArchive($db);
 if($_SERVER['REQUEST_METHOD']==='POST') {
  if(($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')out(['error'=>'Zugriff verweigert.'],403);
  if($action==='login') {
   if(stripos($_SERVER['CONTENT_TYPE']??'','application/json')!==0)out(['error'=>'Zugriff verweigert.'],403);
   $input=json_decode(file_get_contents('php://input'),true)??[];
   $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'');$now=time();
   $db->exec('BEGIN IMMEDIATE');
   sql('DELETE FROM attempts WHERE at<?',[$now-900]);
   $count=(int)sql('SELECT count(*) FROM attempts WHERE ip=?',[$ip])->fetchColumn();
   $global=(int)sql('SELECT count(*) FROM attempts')->fetchColumn();
   if($count>=10||$global>=100){$db->exec('COMMIT');out(['error'=>'Zu viele Versuche. Bitte in 15 Minuten erneut versuchen.'],429);}
   sql('INSERT INTO attempts(ip,at) VALUES(?,?)',[$ip,$now]);$db->exec('COMMIT');
   $password=$input['password']??'';
   if(!is_string($password)||strlen($password)>1024||!password_verify($password,trim(file_get_contents(DATA.'/password.hash'))))out(['error'=>'Anmeldung nicht möglich.'],401);
   sql('DELETE FROM attempts WHERE ip=?',[$ip]);sql('DELETE FROM sessions WHERE expires<?',[$now]);
   $token=bin2hex(random_bytes(32));$csrf=bin2hex(random_bytes(32));
   sql('INSERT INTO sessions(token,csrf,expires) VALUES(?,?,?)',[hash('sha256',$token),$csrf,$now+LIFE]);cookie($token,$now+LIFE);out(['ok'=>true]);
  }
  if(!$s)out(['error'=>'Bitte anmelden.'],401);csrf($s);
  if($action==='logout'){sql('DELETE FROM sessions WHERE token=?',[$s['token']]);cookie('',time()-3600);out(['ok'=>true]);}
  if($action==='upload') {
   $id=(int)($_POST['id']??0);if($id){$target=note($id);if((int)$target['deleted']!==0)out(['error'=>'Bitte den Eintrag zuerst wiederherstellen.'],409);}
   $f=$_FILES['image']??null;
   if(!$f||$f['error']!==UPLOAD_ERR_OK||$f['size']>10*1024*1024)out(['error'=>'Bitte ein Bild bis 10 MB auswählen.'],422);
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
   if(!in_array($mime,['image/jpeg','image/png','image/webp','image/gif'],true)||!@getimagesize($f['tmp_name']))out(['error'=>'Bitte JPEG, PNG, WebP oder GIF verwenden.'],422);
   if((int)sql('SELECT COALESCE(SUM(size),0) FROM images')->fetchColumn()+$f['size']>2*1024*1024*1024)out(['error'=>'Der Bildspeicher ist voll (2 GB).'],422);
   $name=bin2hex(random_bytes(24));
   if(!move_uploaded_file($f['tmp_name'],DATA.'/uploads/'.$name))throw new RuntimeException('Upload failed');
   chmod(DATA.'/uploads/'.$name,0600);
   try{
    $db->beginTransaction();
    if(!$id){sql('INSERT INTO notes(title,body,items,updated,version,deleted,kind) VALUES(?,?,?, ?,1,0,?)',['','','[]',time(),'image']);$id=(int)$db->lastInsertId();}
    // Recheck state inside the transaction to avoid uploading to an entry deleted concurrently.
    $target=sql('SELECT deleted FROM notes WHERE id=?',[$id])->fetch();
    if(!$target||(int)$target['deleted']!==0)throw new RuntimeException('Entry is no longer active');
    sql('INSERT INTO images(note_id,name,mime,size) VALUES(?,?,?,?)',[$id,$name,$mime,$f['size']]);
    sql('UPDATE notes SET updated=? WHERE id=?',[time(),$id]);
    $db->commit();
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();unlink(DATA.'/uploads/'.$name);throw $e;}
   out(['note'=>note($id)]);
  }
  $input=json_decode(file_get_contents('php://input'),true)??[];
  if($action==='create'){sql('INSERT INTO notes(title,body,items,updated,version,deleted) VALUES(?,?,?, ?,1,0)',['','','[]',time()]);out(['note'=>note((int)$db->lastInsertId())]);}
  $id=(int)($input['id']??0);$ver=(int)($input['version']??0);
  if($action==='save') {
   $title=textval($input['title']??'',1200);$body=textval($input['body']??'',100000);$items=$input['items']??null;
   $mutation=$input['mutation']??'';if(!is_string($mutation)||!preg_match('/^[a-f0-9]{32}$/D',$mutation))out(['error'=>'Bitte die Seite neu laden, damit die aktuelle Version verwendet wird.'],422);
   if(!is_array($items)||!array_is_list($items)||count($items)>500)out(['error'=>'Maximal 500 Listenpunkte pro Notiz.'],422);
   $clean=[];$ids=[];foreach($items as $i){if(!is_array($i)||!is_string($i['id']??null)||!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$i['id'])||isset($ids[$i['id']]))out(['error'=>'Bitte die Seite für die neue Listenfunktion neu laden.'],422);$ids[$i['id']]=true;$clean[]=['id'=>$i['id'],'text'=>textval($i['text']??'',10000),'done'=>($i['done']??false)===true];}
   $db->exec('BEGIN IMMEDIATE');
   $n=sql('SELECT * FROM notes WHERE id=? AND deleted=0',[$id])->fetch();
   if(!$n){$db->exec('ROLLBACK');out(['error'=>'Die Notiz wurde gelöscht oder archiviert. Dein Text bleibt hier erhalten.'],409);}
   if(sql('SELECT 1 FROM mutations WHERE id=? AND note_id=?',[$mutation,$id])->fetchColumn()){$db->exec('COMMIT');out(['note'=>note($id),'merged'=>true]);}
   nbRecord($db,$n);
   $merged=$ver!==(int)$n['version'];
   if($merged){
    $base=sql('SELECT title,body,items FROM revisions WHERE note_id=? AND version=?',[$id,$ver])->fetch();
    if(!$base){$db->exec('ROLLBACK');out(['error'=>'Die Ausgangsversion ist nicht verfügbar. Dein Text bleibt erhalten. Bitte kopiere ihn vor dem Neuladen.'],409);}
    $title=str_replace("\n",' / ',nbText($base['title'],$title,$n['title']));$body=nbText($base['body'],$body,$n['body']);$clean=nbItems(json_decode($base['items'],true),$clean,json_decode($n['items'],true));
   }
   if(strlen($title)>1200||strlen($body)>100000||count($clean)>500||array_filter($clean,fn($i)=>strlen($i['text'])>10000)){$db->exec('ROLLBACK');out(['error'=>'Die zusammengeführte Notiz ist zu lang. Deine Eingaben bleiben hier erhalten.'],422);}
   $encoded=json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
   sql('UPDATE notes SET title=?,body=?,items=?,updated=?,version=version+1 WHERE id=?',[$title,$body,$encoded,time(),$id]);
   $saved=sql('SELECT * FROM notes WHERE id=?',[$id])->fetch();nbRecord($db,$saved);
   sql('INSERT INTO mutations(id,note_id,version) VALUES(?,?,?)',[$mutation,$id,$saved['version']]);
   $db->exec('COMMIT');out(['note'=>note($id),'merged'=>$merged]);
  }
  if($action==='trash'||$action==='restore') { $r=sql('UPDATE notes SET deleted=?,deleted_at=?,updated=?,version=version+1 WHERE id=? AND version=? AND deleted=?',[$action==='trash'?1:0,$action==='trash'?time():null,time(),$id,$ver,$action==='trash'?0:1]);if(!$r->rowCount()){changed($id,$ver);out(['error'=>'Änderung fehlgeschlagen.'],409);}out(['ok'=>true]);}
  out(['error'=>'Unbekannte Aktion.'],400);
 }
 if($action!=='') {
  if(!$s)out(['error'=>'Bitte anmelden.'],401);
  if($action==='list'){
   $rows=sql('SELECT n.id,n.title,n.body,n.items,n.updated,n.version,n.deleted,n.kind,n.deleted_at,(SELECT count(*) FROM images i WHERE i.note_id=n.id) AS image_count,(SELECT min(id) FROM images i WHERE i.note_id=n.id) AS thumbnail FROM notes n WHERE n.deleted<2 ORDER BY n.updated DESC,n.id DESC')->fetchAll();
   $used=(int)sql('SELECT COALESCE(SUM(size),0) FROM images')->fetchColumn();
   out(['notes'=>$rows,'storage'=>['used'=>$used,'limit'=>2*1024*1024*1024,'warning'=>$used>=1600*1024*1024]]);
  }
  if($action==='note')out(['note'=>note((int)($_GET['id']??0))]);
  if($action==='image') {
   $im=sql('SELECT i.* FROM images i JOIN notes n ON n.id=i.note_id WHERE i.id=? AND n.deleted<2',[(int)($_GET['id']??0)])->fetch();if(!$im){http_response_code(404);exit;}
   header('Content-Type: '.$im['mime']);header('Content-Disposition: inline; filename="bild"');header('Content-Length: '.filesize(DATA.'/uploads/'.$im['name']));readfile(DATA.'/uploads/'.$im['name']);exit;
  }
  out(['error'=>'Unbekannte Aktion.'],400);
 }
 if($s){sql('UPDATE sessions SET expires=? WHERE token=?',[time()+LIFE,$s['token']]);cookie($_COOKIE[COOKIE],time()+LIFE);}
} catch(Throwable $e){error_log('Notizblock: '.$e->getMessage());out(['error'=>'Der Server konnte die Anfrage nicht verarbeiten. Bitte erneut versuchen.'],500);}
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#f6f4ef"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Notizen"><title>Notizen</title><link rel="stylesheet" href="style.css?v=3"><script src="app.js?v=3" defer></script></head>
<body data-auth="<?= $s?'1':'0' ?>" data-csrf="<?= $s?htmlspecialchars($s['csrf'],ENT_QUOTES):'' ?>">
<?php if(!$s): ?>
<main class="login"><form id="login"><div class="lock" aria-hidden="true">○</div><label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus><button class="primary">Öffnen <span aria-hidden="true">→</span></button><p id="login-error" role="alert"></p></form></main>
<?php else: ?>
<div class="layout"><aside id="sidebar"><header><div><p class="eyebrow">FÜR UNS BEIDE</p><h1>Notizen<span>.</span></h1></div><button id="new" class="primary" aria-label="Neu erstellen" aria-expanded="false">＋</button></header><div id="new-menu" hidden><button id="new-text">✎ Notiz schreiben</button><button id="new-picture">▧ Bild hochladen</button></div><input id="standalone-upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden><label class="search"><span aria-hidden="true">⌕</span><input id="search" type="search" placeholder="Notizen durchsuchen" aria-label="Notizen durchsuchen"></label><nav><button id="active-tab" class="selected">Alle Notizen <span id="count">0</span></button><button id="pictures-tab">Bilder</button><button id="trash-tab">Papierkorb</button></nav><button id="picture-upload" class="upload" hidden>＋ Bild hochladen</button><p id="trash-hint" class="hint" hidden>Einträge bleiben 30 Tage im Papierkorb.</p><p id="storage-warning" class="storage-warning" role="status" hidden>Der Bildspeicher ist fast voll. Bitte melde dich, damit wir Platz schaffen können.</p><div id="notes"></div><footer><span>Ein Platz für unsere Gedanken.</span><button id="logout">Abmelden</button></footer></aside>
<main id="main"><div id="welcome"><div class="paper" aria-hidden="true">☰</div><p class="eyebrow">WENIGER IM KOPF. MEHR ZUSAMMEN.</p><h2>Was steht an?</h2><p>Eine Einkaufsliste, eine Idee, ein Foto.<br>Hier ist Platz dafür.</p><div class="welcome-actions"><button id="first" class="primary">＋ Neue Notiz</button><button id="first-picture" class="upload">▧ Bild hochladen</button></div></div><section id="editor" hidden><div class="toolbar"><button id="back">‹ Übersicht</button><span id="status" role="status">Gespeichert</span><button id="save" class="primary">Speichern</button><button id="trash" class="danger">Löschen</button><button id="restore" hidden>Wiederherstellen</button></div><div id="remote" hidden role="status">Auf einem anderen Gerät geändert. Die Änderungen werden übernommen.</div><div class="sheet"><label class="sr" for="title">Titel</label><input id="title" placeholder="Titel" maxlength="1200"><p id="date"></p><label class="sr" for="body">Notiztext</label><textarea id="body" placeholder="Was möchtest du festhalten?"></textarea><div id="items"></div><div id="list-tools"><button id="add-item-button" type="button">＋ Listenpunkt</button><form id="add-item" hidden><input id="item-text" placeholder="Listenpunkt schreiben …" aria-label="Neuer Listenpunkt" maxlength="10000"><button id="commit-item" type="submit" aria-label="Listenpunkt speichern" title="Speichern (Enter)">💾</button><button id="cancel-item" type="button" aria-label="Listenpunkt abbrechen">×</button></form></div><div id="images"></div><button id="add-text" hidden>＋ Text hinzufügen</button><label id="upload-label" class="upload">＋ Bild hinzufügen<input id="upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden></label><p class="hint">Bilder bis 10 MB · JPEG, PNG, WebP, GIF</p></div></section></main></div>
<?php endif; ?><div id="message" role="alert" hidden></div><div id="feedback" role="status" aria-live="polite" hidden></div></body></html>
