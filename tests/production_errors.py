#!/usr/bin/env python3
"""Exercise production HTML/JSON errors with deliberately unavailable MySQL. No data changes."""
import json,os,socket,subprocess,tempfile,time,urllib.request,urllib.error,urllib.parse
from pathlib import Path
root=Path(__file__).resolve().parents[1]
def check(ok,label):
 assert ok,label
 print('PASS:',label)
with tempfile.TemporaryDirectory() as tmp:
 with socket.socket() as sock:
  sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 env={**os.environ,'APP_ENV':'production','DB_HOST':'127.0.0.1','DB_PORT':'1','DB_PASSWORD':'test-secret-never-display'}
 with open(Path(tmp)/'server.log','w+') as log:
  p=subprocess.Popen(['php','-d',f'session.save_path={tmp}','-S',f'127.0.0.1:{port}','index.php'],cwd=root,env=env,stdout=log,stderr=log)
  try:
   def req(path,data=None,headers=None):
    try:
     with urllib.request.urlopen(urllib.request.Request(f'http://127.0.0.1:{port}'+path,data=urllib.parse.urlencode(data).encode() if data is not None else None,headers=headers or {}),timeout=10) as r:return r.status,r.headers,r.read().decode()
    except urllib.error.HTTPError as r:return r.code,r.headers,r.read().decode()
   for _ in range(40):
    try:status,headers,body=req('/login');break
    except urllib.error.URLError:time.sleep(.1)
   check(status==200 and 'Welcome back' in body,'login form renders without database')
   status,headers,body=req('/login',{'email':'fixture@example.invalid','password':'fixture'})
   check(status==503 and 'Service temporarily unavailable' in body,'unavailable database produces visible login error with HTTP 503')
   check('test-secret' not in body and 'SQLSTATE' not in body,'production error hides connection details')
   status,headers,body=req('/signup/otp/send',{'purpose':'email','identifier':'fixture@example.invalid'})
   check(status==503 and headers['Content-Type'].startswith('application/json') and json.loads(body)['ok'] is False,'OTP failure returns valid JSON instead of a network parse error')
   check(headers['Retry-After']=='30','temporary failures include retry guidance')
   log.flush();log.seek(0);text=log.read()
   check('DropIT request failed: PDOException' in text and 'test-secret' not in text,'server records sanitized failure classification')
  finally:p.terminate();p.wait(timeout=10)
