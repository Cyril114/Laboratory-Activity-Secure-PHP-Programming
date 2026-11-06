<?php



ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);


session_set_cookie_params([
    'httponly' => true,
    'secure'   => false, 
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
session_start();


set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'Something went wrong.';
});


function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $user = getenv('LAB_DB_USER');
    $pass = getenv('LAB_DB_PASSWORD');
    if ($user === false || $pass === false) {
        error_log('Database environment variables are not set');
        http_response_code(500);
        exit('Server configuration error.');
    }
    $pdo = new PDO('mysql:host=localhost;dbname=notes_lab;charset=utf8mb4', $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}


function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf($token): bool
{
    return is_string($token) && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}