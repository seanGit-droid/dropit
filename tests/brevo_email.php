<?php
define('APP_ROOT',dirname(__DIR__)); require APP_ROOT.'/scheme/BrevoEmail.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
$args=['test-key','sender@example.invalid','current@example.invalid','Your verification code','Code: 123456','<p>Code: 123456</p>'];
[$ok,$note]=BrevoEmail::send(...[...$args,function($key,$body){$p=json_decode($body,true);check($key==='test-key' && $p['to'][0]['email']==='current@example.invalid','exact recipient and API authentication passed to transport');check($p['textContent']==='Code: 123456' && $p['sender']['email']==='sender@example.invalid','OTP and verified sender preserved');return [201,'{"messageId":"test-message"}'];}]);
check($ok,'provider acceptance reported as sent');
foreach([[401,'{"message":"test-key secret"}'],[429,'{}'],[500,'<html>error</html>'],[0,'']] as $failure){[$ok,$note]=BrevoEmail::send(...[...$args,fn()=> $failure]);check(!$ok && !str_contains($note,'test-key'),'provider/network failure returns safe feedback');}
[$ok]=BrevoEmail::send(...[...$args,function(){throw new RuntimeException('network');}]);check(!$ok,'connection exception does not crash signup');
