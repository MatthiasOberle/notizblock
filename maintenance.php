<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require __DIR__.'/storage.php';
$d=getenv('NOTIZBLOCK_DATA') ?: '/var/lib/notizblock';
$db=new PDO('sqlite:'.$d.'/notes.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('PRAGMA busy_timeout=5000');
if(in_array('--migrate',$argv,true))nbMigrate($db);
echo nbArchive($db)." Einträge ins verborgene Archiv verschoben.\n";
