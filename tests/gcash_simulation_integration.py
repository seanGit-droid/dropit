#!/usr/bin/env python3
"""Real checkout HTTP/database tests. Disposable schema; no gateway calls or real user changes."""
import http.cookiejar,json,os,re,socket,subprocess,sys,tempfile,time,urllib.error,urllib.parse,urllib.request,uuid
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]; ENV=os.environ.copy()
BRIDGE=r'''require 'config.php';$i=json_decode(stream_get_contents(STDIN),true);$p=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4',DB_USERNAME,DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);if(empty($i['no_schema']))$p->exec('USE `'.DB_NAME.'`');$q=$p->prepare($i['sql']);$q->execute($i['args']);echo json_encode($q->columnCount()?$q->fetchAll():[]);'''
def sql(query,args=None,no_schema=False):
 r=subprocess.run(['php','-r',BRIDGE],cwd=ROOT,env=ENV,input=json.dumps({'sql':query,'args':args or [],'no_schema':no_schema}),capture_output=True,text=True,check=True);return json.loads(r.stdout)
def check(ok,message):
 if not ok:raise AssertionError(message)
 print('PASS:',message,flush=True)
schema='dropit_gcash_test_'+uuid.uuid4().hex[:10];process=None
try:
 source=subprocess.check_output(['php','-r','require "config.php";echo DB_NAME;'],cwd=ROOT,env=ENV,text=True)
 assert re.fullmatch(r'\w+',source)
 tables=[next(iter(r.values())) for r in sql('SHOW TABLES')]
 sql(f'CREATE DATABASE `{schema}` CHARACTER SET utf8mb4',no_schema=True)
 for table in tables:
  assert re.fullmatch(r'\w+',table)
  sql(f'CREATE TABLE `{schema}`.`{table}` LIKE `{source}`.`{table}`',no_schema=True)
 ENV['DB_NAME']=schema
 password=subprocess.check_output(['php','-r','echo password_hash("GCash-test-only-123!",PASSWORD_DEFAULT);'],text=True)
 for uid in (1,2):sql('INSERT INTO users(id,username,email,password,role,full_name,wallet_balance) VALUES(?,?,?,?,?,?,500)',[uid,f'gcash{uid}',f'gcash{uid}@example.invalid',password,'customer',f'GCash Test {uid}'])
 for key,value in {'fare_base':'166.23','fare_per_km':'0'}.items():sql('INSERT INTO settings(setting_key,setting_value) VALUES(?,?)',[key,value])
 with tempfile.TemporaryDirectory(prefix='dropit-gcash-') as temp:
  with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
  log=open(Path(temp)/'php.log','w+')
  process=subprocess.Popen(['php','-d',f'session.save_path={temp}','-S',f'127.0.0.1:{port}','index.php'],cwd=ROOT,env=ENV,stdout=log,stderr=log)
  base=f'http://127.0.0.1:{port}';opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
  def request(path,data=None):
   req=urllib.request.Request(base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None)
   try:
    with opener.open(req,timeout=15) as r:return r.status,r.read().decode(),r.url
   except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.url
  for i in range(50):
   try:request('/login');break
   except urllib.error.URLError:time.sleep(.1)
  request('/login',{'email':'gcash1@example.invalid','password':'GCash-test-only-123!'})
  def token():return re.search(r'name="csrf_token" value="([^"]+)"',request('/customer/order')[1]).group(1)
  def balance(value):sql('UPDATE users SET wallet_balance=? WHERE id=1',[value])
  def wallet():return float(sql('SELECT wallet_balance FROM users WHERE id=1')[0]['wallet_balance'])
  def count(table):return sql(f'SELECT COUNT(*) n FROM {table}')[0]['n']
  def order(method='gcash',**extra):
   data={'csrf_token':token(),'payment_method':method,'sender_name':'Test Sender','pickup_address':'Test pickup','pickup_lat':'14.5995','pickup_lng':'120.9842','recipient_name':'Test Receiver','recipient_phone':'09123456789','recipient_address':'Test dropoff','dropoff_lat':'14.6','dropoff_lng':'120.99','product_name':'Test parcel','weight':'1'}
   data.update(extra);return request('/customer/order',data)
  code,html,url=order()
  check(code==200 and '/customer/track?q=' in url and 'PAID (Via GCash Simulation)' in html,'GCash confirms pickup directly with a paid simulation message')
  row=sql('SELECT * FROM parcels ORDER BY id DESC LIMIT 1')[0];pid=row['id']
  check(wallet()==333.77 and float(row['amount'])==166.23,'exact calculated ₱166.23 debited from ₱500 to ₱333.77')
  check(row['status']=='Pending' and row['payment_status']=='held','existing booking and delivery escrow workflow preserved')
  payment=sql('SELECT * FROM payments WHERE parcel_id=?',[pid])[0]
  check(payment['status']=='paid' and payment['method']=='gcash' and payment['provider_ref'].startswith('gcash_sim_') and not payment['checkout_url'],'paid database record has local reference and no external checkout URL')
  check(float(sql('SELECT amount FROM wallet_transactions WHERE parcel_id=?',[pid])[0]['amount'])==-166.23,'wallet ledger records exact debit')
  code,html,url=request(f'/pay/gcash/create/{pid}')
  check(code==200 and '/customer/track?q=' in url and wallet()==333.77,'legacy GET payment URL redirects to tracking without charging')
  request(f'/pay/gcash/create/{pid}',{'csrf_token':token()})
  check(wallet()==333.77 and count('wallet_transactions')==1,'repeat payment for a paid booking never deducts twice')
  balance(166.23);order();check(wallet()==0,'balance exactly equal to fare succeeds')
  balance(166.22);before=[count(t) for t in ('parcels','payments','wallet_transactions')]
  code,html,url=order()
  check(code==200 and '/customer/order' in url and 'Insufficient GCash simulated balance.' in html,'insufficient balance appears inline on original checkout screen')
  check(wallet()==166.22 and before==[count(t) for t in ('parcels','payments','wallet_transactions')],'insufficient checkout changes no balance, booking, or payment records')
  balance(500)
  sql("CREATE TRIGGER gcash_payment_failure BEFORE INSERT ON payments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Forced integration failure'")
  before=[count(t) for t in ('parcels','payments','wallet_transactions')];code,html,_=order()
  check(code==200 and 'could not be saved' in html,'database write failure gives controlled checkout feedback')
  check(wallet()==500 and before==[count(t) for t in ('parcels','payments','wallet_transactions')],'failed payment write rolls back balance, booking, and ledger together')
  sql('DROP TRIGGER gcash_payment_failure')
  code,html,_=order(csrf_token='');check(wallet()==500 and (code==403 or 'expired' in html),'missing checkout CSRF cannot deduct balance')
  order('cod');check(wallet()==500 and sql('SELECT payment_status FROM parcels ORDER BY id DESC LIMIT 1')[0]['payment_status']=='unpaid','COD booking behavior preserved')
  order('wallet');check(wallet()==333.77 and sql('SELECT payment_status FROM parcels ORDER BY id DESC LIMIT 1')[0]['payment_status']=='held','wallet booking behavior preserved')
  sql("INSERT INTO parcels(tracking_number,customer_id,status,payment_method,payment_status,amount) VALUES('GCASHOLD',1,'Pending','gcash','unpaid',166.23)")
  old=sql("SELECT id FROM parcels WHERE tracking_number='GCASHOLD'")[0]['id']
  request(f'/pay/gcash/return/{old}?sim=1')
  check(sql('SELECT payment_status FROM parcels WHERE id=?',[old])[0]['payment_status']=='unpaid' and wallet()==333.77,'legacy sim return URL cannot mark unpaid bookings paid')
  before_ledger=count('wallet_transactions')
  code,html,url=request(f'/pay/gcash/create/{old}')
  check(code==200 and '/customer/track?q=GCASHOLD' in url and 'via GCash' in html,'old unpaid checkout link opens a safe payment review')
  check(wallet()==333.77 and count('wallet_transactions')==before_ledger and sql('SELECT payment_status FROM parcels WHERE id=?',[old])[0]['payment_status']=='unpaid','viewing old checkout cannot debit or mark a booking paid')
  code,html,_=request('/customer/track?q=GCASHOLD')
  csrf=re.search(r'name="csrf_token" value="([^"]+)"',html).group(1)
  request(f'/pay/gcash/create/{old}',{'csrf_token':csrf})
  check(wallet()==167.54 and sql('SELECT status FROM payments WHERE parcel_id=?',[old])[0]['status']=='paid','existing unpaid booking uses the same local deduction')
  sql("INSERT INTO parcels(tracking_number,customer_id,status,payment_method,payment_status,amount) VALUES('GCASHOTHER',2,'Pending','gcash','unpaid',10)")
  other=sql("SELECT id FROM parcels WHERE tracking_number='GCASHOTHER'")[0]['id']
  request(f'/pay/gcash/create/{other}',{'csrf_token':token()})
  check(wallet()==167.54 and sql('SELECT payment_status FROM parcels WHERE id=?',[other])[0]['payment_status']=='unpaid','another customer booking cannot be paid from this session')
  print('ALL GCASH SIMULATION INTEGRATION CHECKS PASSED',flush=True)
  if '--preview' in sys.argv:
   print('Disposable preview: '+base+' / gcash1@example.invalid / GCash-test-only-123!',flush=True)
   input('Press Enter to close preview and remove test schema: ')
finally:
 if process:process.terminate();process.wait(timeout=10)
 if re.fullmatch(r'dropit_gcash_test_[0-9a-f]{10}',schema):sql(f'DROP DATABASE IF EXISTS `{schema}`',no_schema=True)
