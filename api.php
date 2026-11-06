<?php
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');


function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}


set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    json_out(['error' => 'Server error'], 500);
});


$uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
if ($uid === null) {
    json_out(['error' => 'Not authenticated'], 401);
}

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    if (isset($_GET['user_id']) && (int) $_GET['user_id'] !== $uid) {
        json_out(['error' => 'Forbidden'], 403);
    }
    $stmt = $db->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$uid]);
    json_out($stmt->fetchAll());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    if ($origin !== null && $origin !== 'http://' . ($_SERVER['HTTP_HOST'] ?? '')) {
        json_out(['error' => 'Forbidden origin'], 403);
    }

    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        json_out(['error' => 'Content-Type must be application/json'], 415);
    }
   
    if (!check_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        json_out(['error' => 'Invalid CSRF token'], 403);
    }

  
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_out(['error' => 'Malformed JSON'], 400);
    }
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $title = $data['title'] ?? null;
    $body = $data['body'] ?? null;
    if ($id === false || !is_string($title) || !is_string($body)
        || trim($title) === '' || trim($body) === '' || strlen($title) > 120) {
        json_out(['error' => 'Invalid fields'], 422);
    }

  
    $stmt = $db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([trim($title), trim($body), $id, $uid]);
    if ($stmt->rowCount() === 0) {
        json_out(['error' => 'Note not found'], 404);
    }
    json_out(['updated' => 1]);
}

json_out(['error' => 'Method not supported'], 405);