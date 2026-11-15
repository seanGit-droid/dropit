<?php
defined('APP_ROOT') OR exit('No direct script access allowed');

/** Keeps existing database photo paths stable; Cloudinary holds the original bytes. */
final class ProofStorage
{
    private array $config;
    private $transport;
    private array $created = [];

    public function __construct(?array $config = null, ?callable $transport = null)
    {
        $this->config = $config ?? [
            'driver' => getenv('PROOF_STORAGE') ?: (getenv('APP_ENV') === 'production' ? 'cloudinary' : 'local'),
            'cloud' => getenv('CLOUDINARY_CLOUD_NAME') ?: '',
            'key' => getenv('CLOUDINARY_API_KEY') ?: '',
            'secret' => getenv('CLOUDINARY_API_SECRET') ?: '',
        ];
        $this->transport = $transport;
        if (!in_array($this->config['driver'], ['local', 'cloudinary'], true)) {
            throw new RuntimeException('Photo storage is not configured.');
        }
        if ($this->remote() && (!preg_match('/^[a-z0-9_-]+$/i', $this->config['cloud']) ||
            !preg_match('/^\d+$/', $this->config['key']) || $this->config['secret'] === '')) {
            throw new RuntimeException('Permanent photo storage is not configured.');
        }
    }

    public function remote(): bool { return $this->config['driver'] === 'cloudinary'; }

    public static function validPath(string $path): bool
    {
        return (bool)preg_match('#^resources/(pod|transit)/(pod|pickup|transit)_[1-9][0-9]*_[a-z0-9_]+\.(jpg|jpeg|png|webp|heic)$#D', $path);
    }

    private function publicId(string $path): string
    {
        if (!self::validPath($path)) throw new InvalidArgumentException('Invalid photo path.');
        return 'dropit/proofs/' . substr($path, strlen('resources/'), strrpos($path, '.') - strlen('resources/'));
    }

    public function saveUpload(array $upload, string $path): string
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
            empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('Choose a photo and try again.');
        }
        return $this->saveFile($upload['tmp_name'], $path, true);
    }

    /** Also supports explicitly migrating an existing server-side proof file. */
    public function saveFile(string $source, string $path, bool $uploaded = false, bool $allowExisting = false): string
    {
        $id = $this->publicId($path);
        $size = is_file($source) ? filesize($source) : 0;
        if (!$size || $size > 10 * 1024 * 1024) throw new InvalidArgumentException('Photo must be no larger than 10 MB.');
        $info = @getimagesize($source);
        $mime = $info['mime'] ?? (new finfo(FILEINFO_MIME_TYPE))->file($source);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'], true)) {
            throw new InvalidArgumentException('Choose a valid JPEG, PNG, WebP or HEIC photo.');
        }
        if (!$this->remote()) {
            $target = APP_ROOT . '/' . $path;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
                throw new RuntimeException('Photo storage is unavailable.');
            }
            $saved = $uploaded ? move_uploaded_file($source, $target) : copy($source, $target);
            if (!$saved) throw new RuntimeException('Photo could not be saved.');
            $this->created[$path] = true;
            return $path;
        }
        $response = $this->api('upload', [
            'public_id' => $id, 'type' => 'authenticated', 'overwrite' => 'false',
            'timestamp' => (string)time(),
        ], ['file' => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($source))]);
        if ((!$allowExisting && !empty($response['existing'])) || ($response['public_id'] ?? '') !== $id || ($response['type'] ?? '') !== 'authenticated' ||
            ($response['resource_type'] ?? '') !== 'image' || empty($response['asset_id'])) {
            throw new RuntimeException('Permanent photo storage did not confirm the saved photo.');
        }
        if (empty($response['existing'])) $this->created[$path] = true;
        return $path;
    }

    /** Only a new file created by this instance may be removed during rollback. */
    public function discard(string $path): void
    {
        if (!isset($this->created[$path])) return;
        if ($this->remote()) {
            $result = $this->api('destroy', ['public_id' => $this->publicId($path), 'type' => 'authenticated', 'timestamp' => (string)time()]);
            if (!in_array($result['result'] ?? '', ['ok', 'not found'], true)) throw new RuntimeException('Photo rollback cleanup failed.');
        } else {
            $target = APP_ROOT . '/' . $path;
            if (is_file($target) && !unlink($target)) throw new RuntimeException('Photo rollback cleanup failed.');
        }
        unset($this->created[$path]);
    }

    /** The caller must authorize access to the database photo record first. */
    public function read(string $path): array
    {
        if (!$this->remote()) throw new RuntimeException('Photo not available.');
        // Omit a format transformation: deliver the stored original (including HEIC).
        $id = $this->publicId($path);
        $delivery = 'v1/' . $id; // Standard SDK placeholder for folder-based public IDs.
        $signature = substr(strtr(base64_encode(sha1($delivery . $this->config['secret'], true)), '+/', '-_'), 0, 8);
        $url = 'https://res.cloudinary.com/' . $this->config['cloud'] . '/image/authenticated/s--' . $signature . '--/' . $delivery;
        [$status, $body] = $this->request('GET', $url, [], '');
        if ($status === 404) throw new OutOfBoundsException('Photo not available.');
        if ($status !== 200 || $body === '' || strlen($body) > 10 * 1024 * 1024) throw new RuntimeException('Photo storage is temporarily unavailable.');
        $info = @getimagesizefromstring($body);
        $mime = $info['mime'] ?? (new finfo(FILEINFO_MIME_TYPE))->buffer($body);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'], true)) throw new RuntimeException('Photo storage returned an invalid image.');
        return [$mime, $body];
    }

    private function api(string $action, array $parameters, array $extra = []): array
    {
        ksort($parameters);
        $toSign = implode('&', array_map(static fn($k, $v) => $k . '=' . $v, array_keys($parameters), $parameters));
        $body = http_build_query($parameters + $extra + [
            'api_key' => $this->config['key'],
            'signature' => sha1($toSign . $this->config['secret']),
        ]);
        [$status, $json] = $this->request('POST', 'https://api.cloudinary.com/v1_1/' . $this->config['cloud'] . '/image/' . $action,
            ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], $body);
        if ($status < 200 || $status >= 300) throw new RuntimeException('Permanent photo storage request failed (HTTP ' . (int)$status . ').');
        $response = json_decode($json, true);
        if (!is_array($response) || isset($response['error'])) throw new RuntimeException('Permanent photo storage returned an invalid response.');
        return $response;
    }

    private function request(string $method, string $url, array $headers, string $body): array
    {
        if ($this->transport) return ($this->transport)($method, $url, $headers, $body);
        $context = stream_context_create([
            'http' => ['method' => $method, 'header' => $headers, 'content' => $body, 'timeout' => 20, 'ignore_errors' => true, 'follow_location' => 0],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $result = @file_get_contents($url, false, $context, 0, 10 * 1024 * 1024 + 1);
        preg_match('/^HTTP\/\S+\s+(\d{3})/', ($http_response_header ?? [])[0] ?? '', $match);
        return [(int)($match[1] ?? 0), $result === false ? '' : $result];
    }
}
