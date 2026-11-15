<?php
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/scheme/ProofStorage.php';
function check(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
$config = ['driver'=>'cloudinary', 'cloud'=>'test-cloud', 'key'=>'123456', 'secret'=>'fixture-secret'];
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC');
$source = tempnam(sys_get_temp_dir(), 'proof-fixture-');
file_put_contents($source, $png);
$path = 'resources/pod/pod_1_' . bin2hex(random_bytes(12)) . '.png';
$calls = [];
$transport = static function($method,$url,$headers,$body) use (&$calls,$png) {
    parse_str($body,$data); $calls[] = compact('method','url','headers','data');
    if ($method === 'GET') return [200,$png];
    if (str_ends_with($url,'/destroy')) return [200,'{"result":"ok"}'];
    return [200,json_encode(['public_id'=>$data['public_id'], 'type'=>$data['type'], 'resource_type'=>'image', 'asset_id'=>'fixture-asset'])];
};
try {
    $storage = new ProofStorage($config,$transport);
    check($storage->saveFile($source,$path)===$path,'permanent upload retains the existing database photo path');
    $upload = $calls[0]; $data = $upload['data'];
    check($upload['method']==='POST' && $upload['url']==='https://api.cloudinary.com/v1_1/test-cloud/image/upload','uploads go only to the configured Cloudinary HTTPS API');
    check($data['type']==='authenticated' && $data['overwrite']==='false','proof images are private and cannot overwrite an existing asset');
    check(base64_decode(explode(',', $data['file'],2)[1])===$png,'exact original photo bytes are uploaded');
    $signed = array_diff_key($data,array_flip(['file','api_key','signature'])); ksort($signed);
    $joined = implode('&',array_map(static fn($k,$v)=>$k.'='.$v,array_keys($signed),$signed));
    check($data['signature']===sha1($joined.$config['secret']) && !str_contains($data['file'],$config['secret']),'backend signs parameters without putting the secret in the image');
    check(!is_file(APP_ROOT.'/'.$path),'remote mode does not rely on an ephemeral local copy');
    [$mime,$bytes] = $storage->read($path);
    check($mime==='image/png' && $bytes===$png,'authenticated original can be fetched byte-for-byte');
    $download = end($calls);
    check(str_contains($download['url'],'/image/authenticated/s--') && !str_contains($download['url'],'fixture-secret'),'read URL is signed without exposing the API secret');
    $storage->discard($path); $count=count($calls); $storage->discard($path);
    check(count($calls)===$count && str_ends_with($calls[$count-1]['url'],'/destroy'),'rollback deletes only the photo created by this save, once');
    $storage->discard('resources/pod/pod_99_abcdef.png');
    check(count($calls)===$count,'rollback cannot delete an existing unrelated proof');
    foreach ([401,429,500,0] as $status) {
        $failed = new ProofStorage($config,static fn()=>[$status,'{"error":"sensitive provider details"}']);
        try { $failed->saveFile($source,$path); throw new LogicException('accepted'); }
        catch (RuntimeException $e) { check(!str_contains($e->getMessage(),'sensitive') && str_contains($e->getMessage(),'HTTP '.$status),'provider failure '.$status.' blocks the save with safe feedback'); }
    }
    foreach (['{}','not-json',json_encode(['public_id'=>'unrelated','type'=>'authenticated','resource_type'=>'image','asset_id'=>'x']),json_encode(['public_id'=>$data['public_id'],'type'=>'authenticated','resource_type'=>'image','asset_id'=>'x','existing'=>true])] as $response) {
        $failed=new ProofStorage($config,static fn()=>[200,$response]);
        try { $failed->saveFile($source,$path); throw new LogicException('accepted'); }
        catch (RuntimeException $e) { check(true,'unconfirmed or colliding upload cannot complete delivery'); }
    }
    $existingCalls=0;
    $existing=new ProofStorage($config,static function() use (&$existingCalls,$data) {
        $existingCalls++;
        return [200,json_encode(['public_id'=>$data['public_id'],'type'=>'authenticated','resource_type'=>'image','asset_id'=>'existing','existing'=>true])];
    });
    check($existing->saveFile($source,$path,false,true)===$path,'explicit backup migration may verify an existing immutable asset');
    $existing->discard($path);
    check($existingCalls===1,'migration never claims ownership or deletes a pre-existing asset');
    foreach (['resources/pod/../../config.php','https://attacker.invalid/photo.png','resources/other/pod_1_abc.png','resources/pod/pod_1_a.php'] as $invalid) {
        check(!ProofStorage::validPath($invalid),'invalid photo path is rejected');
    }
    try { $failed=new ProofStorage($config,static fn()=>throw new RuntimeException('connection failed')); $failed->saveFile($source,$path); throw new LogicException('accepted'); }
    catch (RuntimeException $e) { check(true,'network exception propagates for delivery transaction rollback'); }
    try { new ProofStorage(array_replace($config,['secret'=>''])); throw new LogicException('accepted'); }
    catch (RuntimeException $e) { check(true,'missing cloud credentials never silently fall back to local storage'); }
    $invalid=tempnam(sys_get_temp_dir(),'invalid-proof-'); file_put_contents($invalid,'<script>alert(1)</script>');
    try { $storage->saveFile($invalid,$path); throw new LogicException('accepted'); }
    catch (InvalidArgumentException $e) { check(true,'spoofed image content is rejected before upload'); }
    finally { unlink($invalid); }
    $local=new ProofStorage(['driver'=>'local']);
    check($local->saveFile($source,$path)===$path && file_get_contents(APP_ROOT.'/'.$path)===$png,'local development file storage remains compatible');
    $local->discard($path); check(!is_file(APP_ROOT.'/'.$path),'local rollback removes its file');
} finally { unlink($source); }
