<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$d='/var/lib/notizblock';
function hidden(string $prompt): string {fwrite(STDOUT,$prompt);system('stty -echo');try{$s=rtrim(fgets(STDIN)?:'',"\r\n");}finally{system('stty echo');fwrite(STDOUT,"\n");}return $s;}
$p=hidden('Neues gemeinsames Passwort (mindestens 12 Zeichen): ');$q=hidden('Passwort wiederholen: ');
if(strlen($p)<12||strlen($p)>1024||!hash_equals($p,$q)){fwrite(STDERR,"Nicht gespeichert: mindestens 12 Zeichen und identische Eingaben erforderlich.\n");exit(1);}
$hash=password_hash($p,PASSWORD_ARGON2ID);$tmp=$d.'/password.new';file_put_contents($tmp,$hash."\n",LOCK_EX);chmod($tmp,0600);rename($tmp,$d.'/password.hash');
$db=new PDO('sqlite:'.$d.'/notes.sqlite');$db->exec('DELETE FROM sessions');echo "Passwort gespeichert. Bestehende Anmeldungen wurden beendet.\n";
