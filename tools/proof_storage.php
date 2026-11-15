<?php
// Run only from a trusted terminal; no application/database account is created.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/scheme/ProofStorage.php';
$mode = $argv[1] ?? 'dry-run';
try {
    if ($mode === 'check') {
        $storage = new ProofStorage();
        if (!$storage->remote()) throw new RuntimeException('Configure permanent storage before checking it.');
        $path = 'resources/pod/pod_999999999_' . bin2hex(random_bytes(12)) . '.png';
        $source = tempnam(sys_get_temp_dir(), 'dropit-storage-check-');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC');
        file_put_contents($source, $png); $saved = false;
        try {
            $storage->saveFile($source, $path); $saved = true;
            unlink($source);
            if (is_file(APP_ROOT.'/'.$path)) throw new RuntimeException('Storage unexpectedly depends on a local copy.');
            // A fresh client reads the external original after the source is gone.
            [$mime, $bytes] = (new ProofStorage())->read($path);
            if ($mime !== 'image/png' || !hash_equals(hash('sha256',$png),hash('sha256',$bytes))) throw new RuntimeException('Stored image verification failed.');
            echo "PASS: remote upload and fresh-client retrieval match the original with no local copy.\n";
        } finally {
            if (is_file($source)) unlink($source);
            if ($saved) $storage->discard($path);
        }
        echo "PASS: only the synthetic storage-check image was cleaned up.\n";
        exit;
    }
    if (!in_array($mode, ['dry-run','migrate'], true)) throw new InvalidArgumentException('Use dry-run, check, or migrate.');
    $root = realpath($argv[2] ?? APP_ROOT);
    if (!$root) throw new InvalidArgumentException('Backup project directory does not exist.');
    $storage = $mode === 'migrate' ? new ProofStorage() : null;
    if ($storage && !$storage->remote()) throw new RuntimeException('Configure permanent storage before migrating.');
    foreach (['pod','transit'] as $kind) {
        foreach (glob($root.'/resources/'.$kind.'/*') ?: [] as $source) {
            $path = 'resources/'.$kind.'/'.basename($source);
            if (!is_file($source) || !ProofStorage::validPath($path)) continue;
            if (!$storage) { echo 'AVAILABLE '.$path.' ('.filesize($source)." bytes)\n"; continue; }
            // Never request a missing CDN URL before upload (negative caching).
            // overwrite=false preserves existing assets; byte verification below
            // refuses a conflicting backup without replacing the stored original.
            $storage->saveFile($source,$path,false,true);
            [, $bytes] = (new ProofStorage())->read($path);
            if (!hash_equals(hash_file('sha256',$source),hash('sha256',$bytes))) throw new RuntimeException('Stored cloud photo differs; refusing to overwrite it.');
            echo 'SAVED AND VERIFIED '.$path."\n";
        }
    }
} catch (Throwable $e) {
    // No provider body, credentials, URLs containing signatures, or image bytes.
    fwrite(STDERR, 'Photo storage operation failed: '.get_class($e).'. '.$e->getMessage()."\n");
    exit(1);
}
