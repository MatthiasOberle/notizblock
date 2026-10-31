<?php
declare(strict_types=1);
/** Conservative three-way line merge: overlapping edits retain both variants. */
function nbSpan(array $base,array $value): array {
 $start=0;$end=count($base);$vend=count($value);
 while($start<$end&&$start<$vend&&$base[$start]===$value[$start])$start++;
 while($end>$start&&$vend>$start&&$base[$end-1]===$value[$vend-1]){$end--;$vend--;}
 return [$start,$end,array_slice($value,$start,$vend-$start)];
}
function nbText(string $base,string $local,string $remote): string {
 if($local===$remote||$local===$base)return $remote;
 if($remote===$base)return $local;
 $b=$base===''?[]:explode("\n",$base);$l=nbSpan($b,$local===''?[]:explode("\n",$local));$r=nbSpan($b,$remote===''?[]:explode("\n",$remote));
 if($l[1]<=$r[0]&&$l[0]!==$r[0]){$out=array_merge(array_slice($b,0,$l[0]),$l[2],array_slice($b,$l[1],$r[0]-$l[1]),$r[2],array_slice($b,$r[1]));}
 elseif($r[1]<=$l[0]&&$l[0]!==$r[0]){$out=array_merge(array_slice($b,0,$r[0]),$r[2],array_slice($b,$r[1],$l[0]-$r[1]),$l[2],array_slice($b,$l[1]));}
 else {
  $start=min($l[0],$r[0]);$end=max($l[1],$r[1]);
  $lv=array_merge(array_slice($b,$start,$l[0]-$start),$l[2],array_slice($b,$l[1],$end-$l[1]));
  $rv=array_merge(array_slice($b,$start,$r[0]-$start),$r[2],array_slice($b,$r[1],$end-$r[1]));
  $common=0;$re=count($rv);$le=count($lv);
  while($common<$re&&$common<$le&&$rv[$common]===$lv[$common])$common++;
  $suffix=0;while($re-$suffix>$common&&$le-$suffix>$common&&$rv[$re-$suffix-1]===$lv[$le-$suffix-1])$suffix++;
  $both=array_merge(array_slice($rv,0,$common),array_slice($rv,$common,$re-$common-$suffix),array_slice($lv,$common,$le-$common-$suffix),$suffix?array_slice($rv,-$suffix):[]);
  $out=array_merge(array_slice($b,0,$start),$both,array_slice($b,$end));
 }
 return implode("\n",$out);
}
function nbItems(array $base,array $local,array $remote): array {
 $b=array_column($base,null,'id');$l=array_column($local,null,'id');$r=array_column($remote,null,'id');$out=[];
 $ids=array_values(array_unique(array_merge(array_column($remote,'id'),array_column($local,'id'))));
 foreach($ids as $id){$bv=$b[$id]??null;$lv=$l[$id]??null;$rv=$r[$id]??null;
  if(!$lv){if(!$bv||$rv!==$bv)$out[]=$rv;continue;}
  if(!$rv){if(!$bv||$lv!==$bv)$out[]=$lv;continue;}
  $out[]=['id'=>$id,'text'=>nbText($bv['text']??'',$lv['text'],$rv['text']),'done'=>$lv['done']===($bv['done']??false)?$rv['done']:$lv['done']];
 }
 return $out;
}
function nbRecord(PDO $db,array $note): void {
 $s=$db->prepare('INSERT OR IGNORE INTO revisions(note_id,version,title,body,items) VALUES(?,?,?,?,?)');
 $items=is_string($note['items'])?$note['items']:json_encode($note['items'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 $s->execute([$note['id'],$note['version'],$note['title'],$note['body'],$items]);
}
