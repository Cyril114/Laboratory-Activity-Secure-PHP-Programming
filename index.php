<?php
require __DIR__.'/bootstrap.php';
$db = db();

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !check_csrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('Invalid request.');
}

if ($action === 'register') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $message = 'Enter a valid email and a password of at least 8 characters.';
    } else {
        
        $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
        try {
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'student']);
            $message = 'Account created. You can sign in now.';
        } catch (PDOException $ex) {
            $message = 'Could not create account.';
        }
    }
}

if ($action === 'login') {
   
    $stmt = $db->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$_POST['email'] ?? '']);
    $user = $stmt->fetch();
    
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true); 
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        header('Location: /');
        exit;
    }
    $message = 'Invalid email or password.'; 
}

$uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

if (in_array($action, ['save', 'update', 'delete'], true)) {
    if ($uid === null) {
        http_response_code(401);
        exit('Please sign in.');
    }
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (($action === 'save' || $action === 'update')
        && ($title === '' || $body === '' || strlen($title) > 120)) {
        http_response_code(400);
        exit('Invalid note.');
    }

    if ($action === 'save') {
        
        $stmt = $db->prepare('INSERT INTO notes (user_id, title, body) VALUES (?, ?, ?)');
        $stmt->execute([$uid, $title, $body]);
    }

    if ($action === 'update') {
        
        $stmt = $db->prepare('UPDATE notes SET title = ?, body = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$title, $body, (int) ($_POST['id'] ?? 0), $uid]);
    }

    if ($action === 'delete') {
        
        $stmt = $db->prepare('DELETE FROM notes WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) ($_POST['id'] ?? 0), $uid]);
    }

    header('Location: /');
    exit;
}

if ($action === 'logout') {
    
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
    header('Location: /');
    exit;
}

$message = $message ?? ''; 
$notes = [];
$editing = false;
if ($uid !== null) {
    
    $stmt = $db->prepare('SELECT id, user_id, title, body FROM notes WHERE user_id = ? ORDER BY id DESC');
    $stmt->execute([$uid]);
    $notes = $stmt->fetchAll();

    if (isset($_GET['edit'])) {
        
        $stmt = $db->prepare('SELECT id, title, body FROM notes WHERE id = ? AND user_id = ?');
        $stmt->execute([(int) $_GET['edit'], $uid]);
        $editing = $stmt->fetch();
    }
}
?>
<?php $token = csrf_token(); ?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Campus Notes</title>
<style>body{font:16px sans-serif;max-width:750px;margin:2rem auto}label{display:block;margin:.5rem 0}article{border:1px solid #aaa;padding:1rem;margin:1rem 0}form.inline{display:inline}</style>
</head>
<body>
<h1>Campus Notes</h1>
<p><?= e($message) ?></p>
<?php if ($uid === null): ?>
<h2>Register or sign in</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e($token) ?>">
  <label>Email <input name="email" type="email" required></label>
  <label>Password <input name="password" type="password" required></label>
  <button name="action" value="register">Register</button>
  <button name="action" value="login">Sign in</button>
</form>
<?php else: ?>
<p>User ID: <?= e($uid) ?> | Role: <?= e($_SESSION['role'] ?? '') ?> |
  <form method="post" class="inline">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">
    <button name="action" value="logout">Log out</button>
  </form>
</p>
<?php if ($editing): ?>
<h2>Edit note</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e($token) ?>">
  <input type="hidden" name="id" value="<?= e($editing['id']) ?>">
  <label>Title <input name="title" value="<?= e($editing['title']) ?>" maxlength="120" required></label>
  <label>Body <textarea name="body" required><?= e($editing['body']) ?></textarea></label>
  <button name="action" value="update">Update</button>
  <a href="/">Cancel</a>
</form>
<?php endif; ?>
<h2>Create a note</h2>
<form method="post">
  <input type="hidden" name="csrf" value="<?= e($token) ?>">
  <label>Title <input name="title" maxlength="120" required></label>
  <label>Body <textarea name="body" required></textarea></label>
  <button name="action" value="save">Save</button>
</form>
<h2>Your notes</h2>
<?php foreach ($notes as $note): ?>
<article>
  <h3><?= e($note['title']) ?></h3>
  <p><?= nl2br(e($note['body'])) ?></p>
  <a href="?edit=<?= e($note['id']) ?>">Edit</a>
  <form method="post" class="inline">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">
    <input type="hidden" name="id" value="<?= e($note['id']) ?>">
    <button name="action" value="delete">Delete</button>
  </form>
</article>
<?php endforeach; ?>
<?php endif; ?>
</body></html>
