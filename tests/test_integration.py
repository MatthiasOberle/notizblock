import os, pathlib, subprocess, tempfile, secrets, urllib.request, urllib.error, json, re, time, base64
root=pathlib.Path(__file__).resolve().parents[1];data=pathlib.Path(tempfile.mkdtemp(prefix='notizblock-test-'));(data/'uploads').mkdir();(data/'tmp').mkdir();env={**os.environ,'NOTIZBLOCK_DATA':str(data)}
subprocess.run(['php',str(root/'init.php')],env=env,check=True,stdout=subprocess.DEVNULL)
pw=secrets.token_urlsafe(32)
hashvalue=subprocess.run(['php','-r','$p=stream_get_contents(STDIN);echo password_hash($p,PASSWORD_ARGON2ID);'],input=pw,text=True,capture_output=True,check=True).stdout
(data/'password.hash').write_text(hashvalue)
import socket
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
log=tempfile.TemporaryFile(mode='w+');server=subprocess.Popen(['php','-S','127.0.0.1:'+str(port),'-t',str(root/'public')],env=env,stdout=log,stderr=log)
base='http://127.0.0.1:'+str(port)+'/index.php';cookie='';csrf='';passed=[]
def req(action='',payload=None,extra=None,auth=True,raw=None):
 headers={}
 if auth and cookie:headers['Cookie']=cookie
 if payload is not None:headers.update({'Content-Type':'application/json','X-CSRF-Token':csrf});raw=json.dumps(payload).encode()
 headers.update(extra or {})
 r=urllib.request.Request(base+('?action='+action if action else ''),data=raw,headers=headers)
 try:
  with urllib.request.urlopen(r) as x:return x.status,x.read(),x.headers
 except urllib.error.HTTPError as x:return x.code,x.read(),x.headers
try:
 for _ in range(50):
  try:req();break
  except OSError:time.sleep(.1)
 assert req('list',auth=False)[0]==401;passed.append('Notizen ohne Anmeldung gesperrt')
 assert req('image&id=1',auth=False)[0]==401;passed.append('Bilder ohne Anmeldung gesperrt')
 assert req('login',{'password':'wrong'})[0]==401;passed.append('Falsches Passwort abgewiesen')
 status,body,headers=req('login',{'password':pw});assert status==200
 sc=headers['Set-Cookie'];assert all(t in sc for t in ['HttpOnly','Secure','SameSite=Strict']);cookie=sc.split(';')[0]
 status,body,_=req();csrf=re.search(rb'data-csrf="([a-f0-9]+)"',body).group(1).decode();passed.append('Anmeldung und sichere Cookie-Attribute')
 realcsrf=csrf;csrf='bad';assert req('create',{})[0]==403;csrf=realcsrf;passed.append('CSRF-Schutz')
 assert req('create',{},extra={'Sec-Fetch-Site':'cross-site'})[0]==403;passed.append('Fremde Website abgewiesen')
 status,b,_=req('create',{});assert status==200;n=json.loads(b)['note'];nid=n['id']
 payload={'id':nid,'version':n['version'],'title':'Einkauf <script>alert(1)</script>','body':'Test äöü','items':[{'id':'milk','text':'Milch','done':False}], 'mutation':secrets.token_hex(16)}
 status,b,_=req('save',payload);assert status==200;n=json.loads(b)['note'];assert n['items'][0]['text']=='Milch';passed.append('Notiz und Checkliste gespeichert')
 assert req('save',payload)[0]==200;passed.append('Wiederholter Speicherversuch ist idempotent')
 png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP9sAAAAASUVORK5CYII=')
 def upload(content,mime,name):
  boundary='testboundary';raw=(f'--{boundary}\r\nContent-Disposition: form-data; name="id"\r\n\r\n{nid}\r\n--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+f'\r\n--{boundary}--\r\n'.encode())
  return req('upload',extra={'Content-Type':'multipart/form-data; boundary='+boundary,'X-CSRF-Token':csrf},raw=raw)
 assert upload(b'<?php echo 1; ?>','image/png','bad.png')[0]==422;passed.append('Getarnter PHP-Upload abgewiesen')
 status,b,_=upload(png,'image/png','bild.png');assert status==200;imageid=json.loads(b)['note']['images'][0]['id'];assert req('image&id='+str(imageid))[1]==png;passed.append('Bild hochgeladen und geschützt ausgeliefert')
 assert req('trash',{'id':nid,'version':n['version']})[0]==200
 status,b,_=req('note&id='+str(nid));n=json.loads(b)['note'];assert n['deleted']==1
 assert req('restore',{'id':nid,'version':n['version']})[0]==200;passed.append('Papierkorb und Wiederherstellung')
 # Two devices save from the same server-held base revision.
 cid=json.loads(req('create',{})[1])['note']['id']
 def put(version,body,items,title='Gemeinsam',mutation=None):
  return req('save',{'id':cid,'version':version,'title':title,'body':body,'items':items,'mutation':mutation or secrets.token_hex(16)})
 v=json.loads(put(1,'Anfang',[])[1])['note']['version']
 assert put(v,'Anfang\nLinks',[{'id':'a','text':'Milch','done':False}])[0]==200
 retryid=secrets.token_hex(16)
 status,b,_=put(v,'Anfang\nRechts',[{'id':'b','text':'Brot','done':False}],mutation=retryid)
 merged=json.loads(b);assert status==200 and merged['merged']
 assert merged['note']['body']=='Anfang\nLinks\nRechts'
 assert [x['id'] for x in merged['note']['items']]==['a','b']
 assert put(v,'Anfang\nDritte',[])[0]==200
 latest=json.loads(req('note&id='+str(cid))[1])['note']
 assert latest['body']=='Anfang\nLinks\nRechts\nDritte'
 # The third device deleted nothing from its empty base; both other additions survive.
 assert len(latest['items'])==2
 assert put(v,'Anfang\nRechts',[{'id':'b','text':'Brot','done':False}],mutation=retryid)[0]==200
 assert json.loads(req('note&id='+str(cid))[1])['note']['body']==latest['body']
 passed.append('Zwei und drei Geräte: Text und neue Listenpunkte zusammengeführt, Retry ohne Duplikate')
 # Check one device can edit a label while the other ticks it off.
 v=latest['version'];its=latest['items']
 left=[dict(x) for x in its];left[0]['text']='Hafermilch'
 assert put(v,latest['body'],left)[0]==200
 right=[dict(x) for x in its];right[0]['done']=True
 status,b,_=put(v,latest['body'],right)
 latest=json.loads(b)['note'];assert latest['items'][0]['text']=='Hafermilch' and latest['items'][0]['done']
 passed.append('Listenpunkt-Text und Häkchen unabhängig zusammengeführt')
 # Deleting on one device cannot discard a concurrent edit on the other.
 v=latest['version'];its=latest['items'];edited=[dict(x) for x in its];edited[0]['text']='Frische Hafermilch'
 assert put(v,latest['body'],edited)[0]==200
 status,b,_=put(v,latest['body'],its[1:]);latest=json.loads(b)['note'];assert any(x['text']=='Frische Hafermilch' for x in latest['items'])
 passed.append('Entfernen gegen gleichzeitiges Bearbeiten erhält den geänderten Listenpunkt')
 # Separate text ranges merge without repeating their common context.
 status,b,_=put(latest['version'],'eins\nmitte\ndrei',latest['items']);latest=json.loads(b)['note'];v=latest['version']
 assert put(v,'EINS\nmitte\ndrei',latest['items'])[0]==200
 status,b,_=put(v,'eins\nmitte\nDREI',latest['items']);assert json.loads(b)['note']['body']=='EINS\nmitte\nDREI'
 passed.append('Unterschiedliche Textstellen ohne doppelte Ausgangsfassung zusammengeführt')
 # Direct image upload creates a photo entry only after validating the upload.
 oldcount=len(json.loads(req('list')[1])['notes'])
 original_nid=nid;nid=0
 assert upload(b'bad','image/png','bad.png')[0]==422
 assert len(json.loads(req('list')[1])['notes'])==oldcount
 status,b,_=upload(png,'image/png','direkt.png');assert status==200
 photo=json.loads(b)['note'];assert photo['kind']=='image' and photo['title']=='' and photo['body']==''
 photoid=photo['id'];photoimage=photo['images'][0]['id']
 listing=json.loads(req('list')[1]);listed=next(x for x in listing['notes'] if x['id']==photoid)
 assert listed['image_count']==1 and listed['thumbnail']==photoimage
 passed.append('Direkter Bild-Upload ohne leere Notiz; Bildfilter-Metadaten')
 assert req('trash',{'id':photoid,'version':photo['version']})[0]==200
 # A freshly deleted entry must stay in the trash.
 assert json.loads(req('note&id='+str(photoid))[1])['note']['deleted']==1
 import sqlite3
 con=sqlite3.connect(data/'notes.sqlite')
 con.execute('UPDATE notes SET deleted_at=? WHERE id=?',(int(time.time())-29*86400,photoid));con.commit()
 subprocess.run(['php',str(root/'maintenance.php')],env=env,check=True,stdout=subprocess.DEVNULL)
 assert con.execute('SELECT deleted FROM notes WHERE id=?',(photoid,)).fetchone()[0]==1
 con.execute('UPDATE notes SET deleted_at=? WHERE id=?',(int(time.time())-30*86400,photoid));con.commit()
 subprocess.run(['php',str(root/'maintenance.php')],env=env,check=True,stdout=subprocess.DEVNULL)
 assert con.execute('SELECT deleted,archived_at FROM notes WHERE id=?',(photoid,)).fetchone()[0]==2
 assert all(x['id']!=photoid for x in json.loads(req('list')[1])['notes'])
 assert req('note&id='+str(photoid))[0]==404
 assert req('image&id='+str(photoimage))[0]==404
 assert req('restore',{'id':photoid,'version':photo['version']+2})[0]==404
 assert con.execute('SELECT count(*) FROM images WHERE note_id=?',(photoid,)).fetchone()[0]==1
 imagefile=con.execute('SELECT name FROM images WHERE note_id=?',(photoid,)).fetchone()[0]
 assert (data/'uploads'/imagefile).read_bytes()==png
 con.close();nid=original_nid
 passed.append('30-Tage-Grenze: Archiv verbirgt Notiz und Bilder, Originale bleiben erhalten')
 assert req('logout',{})[0]==200;assert req('list')[0]==401;passed.append('Abmeldung widerruft Sitzung serverseitig')
 for _ in range(10):req('login',{'password':'wrong'})
 assert req('login',{'password':'wrong'})[0]==429;passed.append('Rate-Limit gegen Passwortversuche')
 print('\n'.join('OK: '+x for x in passed))
finally:
 server.terminate();server.wait();log.close()
 import shutil;shutil.rmtree(data)
