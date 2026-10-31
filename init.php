<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$d=getenv('NOTIZBLOCK_DATA') ?: '/var/lib/notizblock';
$db=new PDO('sqlite:'.$d.'/notes.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("PRAGMA journal_mode=WAL; CREATE TABLE IF NOT EXISTS notes(id INTEGER PRIMARY KEY,title TEXT NOT NULL,body TEXT NOT NULL,items TEXT NOT NULL,updated INTEGER NOT NULL,version INTEGER NOT NULL,deleted INTEGER NOT NULL DEFAULT 0); CREATE TABLE IF NOT EXISTS images(id INTEGER PRIMARY KEY,note_id INTEGER NOT NULL REFERENCES notes(id),name TEXT NOT NULL,mime TEXT NOT NULL,size INTEGER NOT NULL); CREATE TABLE IF NOT EXISTS sessions(token TEXT PRIMARY KEY,csrf TEXT NOT NULL,expires INTEGER NOT NULL); CREATE TABLE IF NOT EXISTS attempts(ip TEXT NOT NULL,at INTEGER NOT NULL); CREATE INDEX IF NOT EXISTS attempts_time ON attempts(at);");
echo "Datenbank bereit.\n";

require __DIR__.'/storage.php';nbMigrate($db);
