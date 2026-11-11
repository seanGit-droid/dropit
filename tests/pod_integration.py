#!/usr/bin/env python3
"""Exercise real multipart HTTP requests against a disposable MySQL schema.
Uses the configured database's schema only; never copies or edits its data.
Run from the project root: python3 tests/pod_integration.py
"""
import base64, sys, http.cookiejar, json, os, re, socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request, uuid
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
ENV = os.environ.copy()
BRIDGE = r'''
require 'config.php';
$i=json_decode(stream_get_contents(STDIN),true);
$p=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4',DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
if(empty($i['no_schema'])) $p->exec('USE `'.DB_NAME.'`');
$q=$p->prepare($i['sql']); $q->execute($i['args']??[]);
echo json_encode($q->columnCount()?$q->fetchAll():[]);
'''
def sql(statement, args=None, no_schema=False):
    result = subprocess.run(['php','-r',BRIDGE],input=json.dumps({'sql':statement,'args':args or [],'no_schema':no_schema}),text=True,capture_output=True,cwd=ROOT,env=ENV,check=True)
    return json.loads(result.stdout)
def check(condition, message):
    if not condition: raise AssertionError(message)
    print('PASS:', message, flush=True)
class Client:
    def __init__(self, base):
        self.base=base; self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self,path,data=None,headers=None):
        req=urllib.request.Request(self.base+path,data=data,headers=headers or {})
        try:
            with self.opener.open(req,timeout=15) as r: return r.status,r.read().decode(),r.url
        except urllib.error.HTTPError as e: return e.code,e.read().decode(),e.url
    def login(self,email,admin=False):
        data=urllib.parse.urlencode({'email':email,'password':'POD-test-only-123!', 'admin_mode':'1' if admin else ''}).encode()
        code,body,_=self.request('/login',data,{'Content-Type':'application/x-www-form-urlencoded'})
        check(code==200 and 'Server error' not in body,'fixture account logs in')
    def token(self,pid=1):
        _,html,_=self.request('/driver/pod/3')
        return re.search(r'name="csrf_token" value="([^"]+)"',html).group(1)
    def pod(self,pid=1,override=None,image=None,omit=()):
        now=int(time.time()*1000)
        data={'csrf_token':self.token(pid),'receiver_name':'Test Receiver','signature':SIGNATURE,'latitude':'14.5995123','longitude':'120.9842345','accuracy':'6.25','captured_at':str(now),'location_at':str(now),'cod_collected':'1'}
        data.update(override or {})
        boundary='POD'+uuid.uuid4().hex; parts=[]
        for k,v in data.items():
            if k not in omit: parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
        if 'photo' not in omit:
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="photo"; filename="capture.jpg"\r\nContent-Type: image/jpeg\r\n\r\n'.encode()+(PNG if image is None else image)+b'\r\n')
        parts.append(f'--{boundary}--\r\n'.encode())
        return self.request('/driver/pod/'+str(pid),b''.join(parts),{'Content-Type':'multipart/form-data; boundary='+boundary,'Accept':'application/json'})
PNG=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jA1sAAAAASUVORK5CYII=')
SIGNATURE='data:image/png;base64,'+base64.b64encode(PNG).decode()
schema='dropit_pod_test_'+uuid.uuid4().hex[:10]
source=subprocess.check_output(['php','-r','require "config.php"; echo DB_NAME;'],cwd=ROOT,env=ENV,text=True)
check(bool(re.fullmatch(r'\w+',source)),'source schema identifier is valid')
process=None; photos=[]
try:
    tables=[next(iter(row.values())) for row in sql('SHOW TABLES')]
    sql(f'CREATE DATABASE `{schema}` CHARACTER SET utf8mb4',no_schema=True)
    for table in tables:
        check(bool(re.fullmatch(r'\w+',table)),'table identifier is valid') if not re.fullmatch(r'\w+',table) else None
        sql(f'CREATE TABLE `{schema}`.`{table}` LIKE `{source}`.`{table}`',no_schema=True)
    ENV['DB_NAME']=schema
    password=subprocess.check_output(['php','-r','echo password_hash("POD-test-only-123!", PASSWORD_DEFAULT);'],text=True)
    for uid,role in [(1,'admin'),(2,'driver'),(3,'customer'),(4,'driver'),(5,'customer')]:
        sql('INSERT INTO users(id,username,email,password,role,full_name) VALUES(?,?,?,?,?,?)',[uid,f'test{uid}',f'test{uid}@example.invalid',password,role,f'Test {role}'])
    # Disable outbound notifications: no provider settings are copied from the real database.
    for pid,method,status,driver in [(1,'wallet','Arrived',2),(2,'cod','Arrived',2),(3,'wallet','Arrived',2),(4,'wallet','In Transit',2),(5,'wallet','Arrived',4),(6,'cod','Accepted',2),(7,'cod','Accepted',2),(8,'cod','Accepted',2)]:
        sql('INSERT INTO parcels(id,tracking_number,driver_id,customer_id,recipient_name,status,payment_method,payment_status,amount,admin_fee,driver_earnings) VALUES(?,?,?,?,?,?,?,?,100,10,90)',[pid,f'PODTEST{pid}',driver,3,'Test Receiver',status,method,'held' if method=='wallet' else 'unpaid'])
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    with tempfile.TemporaryDirectory(prefix='dropit-pod-test-') as temp:
        log=open(Path(temp)/'server.log','w+')
        process=subprocess.Popen(['php','-d',f'session.save_path={temp}','-S',f'127.0.0.1:{port}','index.php'],cwd=ROOT,env=ENV,stdout=log,stderr=log)
        base=f'http://127.0.0.1:{port}'
        driver=Client(base)
        for attempt in range(50):
            try: driver.request('/login'); break
            except urllib.error.URLError: time.sleep(.1)
        driver.login('test2@example.invalid')
        for name,changes,omit,image in [
            ('missing GPS',{},('latitude',),None),
            ('invalid coordinates',{'latitude':'91'},(),None),
            ('blank GPS',{'longitude':''},(),None),
            ('nonfinite GPS',{'latitude':'NaN'},(),None),
            ('stale GPS',{'location_at':str(int(time.time()*1000)-60000)},(),None),
            ('future capture',{'captured_at':str(int(time.time()*1000)+120000)},(),None),
            ('missing photo',{},('photo',),None),
            ('spoofed image MIME',{},(),b'<?php echo "invalid";'),
            ('missing signature',{},('signature',),None),
            ('missing CSRF',{},('csrf_token',),None),
        ]:
            code,body,_=driver.pod(override=changes,omit=omit,image=image)
            check(code==422 and not json.loads(body)['ok'],name+' rejected')
            check(sql('SELECT status,pod_photo FROM parcels WHERE id=1')[0]=={'status':'Arrived','pod_photo':None},name+' leaves delivery untouched')
        code,_,_=driver.pod(5)
        check(code==422,'another driver cannot submit proof')
        sql("UPDATE parcels SET status='Cancelled' WHERE id=4")
        code,_,_=driver.pod(4)
        check(code==422,'cancelled parcel cannot complete delivery')
        # Force a database failure after the file is saved and credits are attempted.
        before=set((ROOT/'resources/pod').glob('*'))
        sql("CREATE TRIGGER pod_test_failure BEFORE INSERT ON parcel_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test rollback'")
        code,_,_=driver.pod(3)
        check(code==422,'database failure reported')
        row=sql('SELECT status,pod_photo,pod_latitude,payment_status FROM parcels WHERE id=3')[0]
        check(row=={'status':'Arrived','pod_photo':None,'pod_latitude':None,'payment_status':'held'},'database failure rolls back proof and delivery status')
        check(float(sql('SELECT wallet_balance FROM users WHERE id=2')[0]['wallet_balance'])==0,'database failure rolls back driver earnings')
        check(set((ROOT/'resources/pod').glob('*'))==before,'database failure removes the new photo')
        sql('DROP TRIGGER pod_test_failure')
        # Failure after balances have been credited must undo every financial change.
        before_money=set((ROOT/'resources/pod').glob('*'))
        sql("CREATE TRIGGER pod_payment_failure BEFORE INSERT ON wallet_transactions FOR EACH ROW BEGIN IF NEW.type='admin_commission' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='settlement rollback test'; END IF; END")
        code,_,_=driver.pod(3)
        check(code==422,'settlement failure reported after driver credit')
        check(all(float(r['wallet_balance'])==0 for r in sql('SELECT wallet_balance FROM users WHERE id IN (1,2)')),'settlement failure rolls back both balances')
        check(sql('SELECT status,pod_photo FROM parcels WHERE id=3')[0]=={'status':'Arrived','pod_photo':None},'settlement failure rolls back delivery and proof')
        check(set((ROOT/'resources/pod').glob('*'))==before_money,'settlement failure removes its photo')
        sql('DROP TRIGGER pod_payment_failure')
        code,body,_=driver.pod()
        check(code==200 and json.loads(body)['ok'],'valid multipart photo and GPS completes delivery')
        proof=sql('SELECT * FROM parcels WHERE id=1')[0]; photos.append(ROOT/proof['pod_photo'])
        check(proof['status']=='Delivered' and proof['payment_status']=='settled','delivery and wallet settle together')
        check(proof['pod_latitude']=='14.5995123' and proof['pod_longitude']=='120.9842345' and proof['pod_captured_at'] and proof['pod_location_at'] and proof['pod_saved_at'],'exact coordinates and all timestamps persisted')
        check(photos[-1].is_file(),'saved photo exists')
        check(float(sql('SELECT wallet_balance FROM users WHERE id=2')[0]['wallet_balance'])==90,'driver credited once')
        check(float(sql('SELECT wallet_balance FROM users WHERE id=1')[0]['wallet_balance'])==10,'admin credited once')
        # Completed parcel form redirects, so obtain a token from a still-arrived fixture.
        token=driver.token(3)
        original_token=driver.token; driver.token=lambda pid: token
        code,_,_=driver.pod()
        driver.token=original_token
        check(code==422 and float(sql('SELECT wallet_balance FROM users WHERE id=2')[0]['wallet_balance'])==90,'duplicate delivery rejected without double payment')
        code,body,_=driver.pod(2)
        check(code==200 and json.loads(body)['ok'],'COD proof saves successfully')
        photos.append(ROOT/sql('SELECT pod_photo FROM parcels WHERE id=2')[0]['pod_photo'])
        check(float(sql('SELECT cash_on_hand FROM users WHERE id=2')[0]['cash_on_hand'])==100,'COD credits cash on hand, not wallet')
        code,html,_=driver.request('/driver/pod/1')
        check(code==200 and '14.5995123' in html and '120.9842345' in html and 'Location:' in html,'saved driver proof displays location and exact coordinates')
        # The capture addition retains the existing customer receipt and tracking flow.
        customer=Client(base); customer.login('test3@example.invalid')
        code,html,_=customer.request('/customer/track?q=PODTEST1')
        check(code==200 and 'Delivered' in html,'existing customer tracking still shows delivery')
        code,html,_=customer.request('/customer/receipt/PODTEST1')
        check(code==200 and proof['pod_photo'] in html,'existing receipt still shows delivery photo')
        anonymous=Client(base); _,html,_=anonymous.request('/track/PODTEST1')
        check('14.5995123' not in html,'public tracker remains unchanged and does not expose exact GPS')
        code,html,_=driver.request('/driver/pod/1')
        check(code==200 and '14.5995123' in html and '120.9842345' in html and 'Saved delivery proof' in html,'driver can review saved photo and coordinate label')
        admin=Client(base); admin.login('test1@example.invalid',True)
        code,html,_=admin.request('/admin/dashboard')
        check(code==200 and 'Hi Admin!' in html,'existing admin dashboard still works')
        print('ALL POD INTEGRATION CHECKS PASSED',flush=True)
        if '--preview' in sys.argv:
            print('ISOLATED PREVIEW: '+base+' | admin: test1@example.invalid | driver: test2@example.invalid | customer: test3@example.invalid | password: POD-test-only-123!',flush=True)
            input('Press Enter to remove disposable preview data: ')
finally:
    if process:
        process.terminate(); process.wait(timeout=10)
    try:
        photos += [ROOT/r['pod_photo'] for r in sql('SELECT pod_photo FROM parcels WHERE pod_photo IS NOT NULL')]

    except Exception: pass
    for photo in set(photos):
        if photo.parent in [ROOT/'resources/pod',ROOT/'resources/transit'] and photo.is_file(): photo.unlink()
    # Only the uniquely named disposable schema created above is removed.
    if re.fullmatch(r'dropit_pod_test_[0-9a-f]{10}',schema): sql(f'DROP DATABASE IF EXISTS `{schema}`',no_schema=True)
