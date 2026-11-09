<?php defined('APP_ROOT') OR exit('No direct script access allowed');
/** Return a visible failure instead of silently exiting in production. */
final class ErrorResponse
{
    public static function send(Throwable $exception): never
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $databaseFailure = $exception instanceof PDOException;
        $status = $databaseFailure ? 503 : 500;
        // No SQL, credentials, request values, or stack traces in production responses/logs.
        error_log('DropIT request failed: '.get_class($exception).' code='.(string)$exception->getCode());
        http_response_code($status);
        header('Cache-Control: no-store');
        if ($status === 503) header('Retry-After: 30');
        $message = $databaseFailure
            ? 'Drop IT cannot connect to its database right now. Please try again shortly.'
            : 'Drop IT could not complete your request. Please try again shortly.';
        $isJson = str_starts_with($path, '/api/')
            || str_starts_with($path, '/signup/otp/')
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        if ($isJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => false, 'error' => $message]);
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            $error = IS_DEV ? htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') : $message;
            include APP_ROOT . '/app/views/errors/500.php';
        }
        exit;
    }
}
