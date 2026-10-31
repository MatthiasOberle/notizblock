<?php
declare(strict_types=1);
function nbMigrate(PDO $db): void {
 $cols=array_column($db->query('PRAGMA table_info(notes)')->fetchAll(PDO::FETCH_ASSOC),'name');
 $db->beginTransaction();
 try {
  foreach(['kind'=>"TEXT NOT NULL DEFAULT 'note'",'deleted_at'=>'INTEGER','archived_at'=>'INTEGER'] as $name=>$definition){if(!in_array($name,$cols,true))$db->exec("ALTER TABLE notes ADD COLUMN $name $definition");}
  $db->exec('UPDATE notes SET deleted_at=updated WHERE deleted=1 AND deleted_at IS NULL');
  $db->exec('CREATE INDEX IF NOT EXISTS notes_trash_age ON notes(deleted,deleted_at)');
  $db->exec('CREATE TABLE IF NOT EXISTS revisions(note_id INTEGER NOT NULL,version INTEGER NOT NULL,title TEXT NOT NULL,body TEXT NOT NULL,items TEXT NOT NULL,PRIMARY KEY(note_id,version))');
  $db->exec('CREATE TABLE IF NOT EXISTS mutations(id TEXT PRIMARY KEY,note_id INTEGER NOT NULL,version INTEGER NOT NULL)');
  foreach($db->query('SELECT id,version,title,body,items FROM notes')->fetchAll(PDO::FETCH_ASSOC) as $n){
   $items=json_decode($n['items'],true);$changed=false;
   foreach($items as $i=>&$item){if(!isset($item['id'])){$item=['id'=>'legacy-'.$n['id'].'-'.$i,'text'=>$item['text'],'done'=>(bool)$item['done']];$changed=true;}}unset($item);
   if($changed){$n['items']=json_encode($items,JSON_UNESCAPED_UNICODE);$u=$db->prepare('UPDATE notes SET items=? WHERE id=?');$u->execute([$n['items'],$n['id']]);}
   $v=$db->prepare('INSERT OR IGNORE INTO revisions(note_id,version,title,body,items) VALUES(?,?,?,?,?)');$v->execute([$n['id'],$n['version'],$n['title'],$n['body'],$n['items']]);
  }
  $db->commit();
 }catch(Throwable $e){$db->rollBack();throw $e;}
}
function nbArchive(PDO $db): int {
 $s=$db->prepare('UPDATE notes SET deleted=2,archived_at=?,version=version+1 WHERE deleted=1 AND deleted_at IS NOT NULL AND deleted_at<=?');$s->execute([time(),time()-30*86400]);return $s->rowCount();
}
