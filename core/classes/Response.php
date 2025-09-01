<?php
declare(strict_types=1);

class Response
{
    public static function redirect(string $to, int $status = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . $to, true, $status);
        } else {
            echo "<script>location.href='".addslashes($to)."'</script>";
        }
        exit;
    }

    public static function json($data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message = 'OK', array $data = [], int $status = 200): void
    {
        self::json(['status' => 'success', 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $data = []): void
    {
        // JSON isteyen istemcilere JSON, aksi halde düz metin döner
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if (stripos($accept, 'application/json') !== false) {
            self::json(['status' => 'error', 'message' => $message, 'data' => $data], $status);
        } else {
            if (!headers_sent()) {
                http_response_code($status);
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo $message;
            exit;
        }
    }
}