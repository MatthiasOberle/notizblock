import os, pathlib
os.chdir(pathlib.Path(__file__).resolve().parents[1])
import subprocess,json,pathlib
vectors=[['','A','B'],['A','A\nlinks','A\nrechts'],['A\nB\nC','X\nB\nC','A\nB\nY'],['alt','neu','anders'],['alt','','bearbeitet'],['A\nB','A\nC','X\nB'],['A\n','A\nB\n','A\nC\n'],['Äpfel','Öl','Brot']]
js=pathlib.Path('scripts/merge.js').read_text()+"\nconst fs=require('fs');const v=JSON.parse(fs.readFileSync(0,'utf8'));process.stdout.write(JSON.stringify(v.map(a=>mergeText(...a))));"
payload=json.dumps(vectors)
a=json.loads(subprocess.run(['node','-e',js],input=payload,text=True,capture_output=True,check=True).stdout)
b=json.loads(subprocess.run(['php','-r',"require 'merge.php';$v=json_decode(stream_get_contents(STDIN),true);echo json_encode(array_map(fn($a)=>nbText(...$a),$v));"],input=payload,text=True,capture_output=True,check=True).stdout)
assert a==b
assert a[0]=='B\nA' and a[1]=='A\nrechts\nlinks' and a[2]=='X\nB\nY' and a[4]=='bearbeitet'
# Rebase edits typed while an earlier network request is in flight.
js=pathlib.Path('scripts/merge.js').read_text()+"\nconst base={title:'Titel',body:'alt',items:[]};const sent={...base,body:'erste Eingabe'};const typed={...sent,body:'erste Eingabe und weitergetippt',items:[{id:'x',text:'Brot',done:false}]};const ack={...sent,version:2};const result=mergeDraft(sent,typed,ack);if(result.body!==typed.body||result.items.length!==1)throw Error('In-flight edits lost');console.log('OK');"
subprocess.run(['node','-e',js],check=True)
print('OK: Browser und Server liefern gleiche Merge-Ergebnisse; laufende Eingaben bleiben erhalten')
