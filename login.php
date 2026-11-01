<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

if (isset($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit();
}

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = '';

if (isset($_POST['login'])) {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $ok   = false;

    try {
        $db = new mysqli('localhost', 'root', '', 'university_transport');
        $db->set_charset('utf8mb4');

        $stmt = $db->prepare("SELECT password FROM Admin_Users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $user);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            $stored  = (string)$row['password'];
            $is_hash = (bool)preg_match('/^\$(2y|argon2i|argon2id)\$/', $stored);

            $ok = $is_hash ? password_verify($pass, $stored) : hash_equals($stored, $pass);

            // Old plain-text password: replace it with a hash, but only if the column is wide enough
            if ($ok && !$is_hash) {
                $col = $db->prepare(
                    "SELECT CHARACTER_MAXIMUM_LENGTH AS len
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND LOWER(TABLE_NAME) = 'admin_users'
                       AND COLUMN_NAME = 'password'"
                );
                $col->execute();
                $info = $col->get_result()->fetch_assoc();
                $col->close();

                if ($info && (int)$info['len'] >= 60) {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $upd = $db->prepare("UPDATE Admin_Users SET password = ? WHERE username = ?");
                    $upd->bind_param('ss', $hash, $user);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
        $db->close();
    } catch (mysqli_sql_exception $e) {
        error_log('Login error: ' . $e->getMessage());
        $error = 'Login is not available right now. Please try again later.';
    }

    if ($error === '') {
        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = $user;
            header('Location: admin.php');
            exit();
        }
        usleep(400000);
        $error = 'Wrong username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTMS - Admin Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px; }
        .login-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { color: #7b2cbf; text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; outline: none; }
        .form-group input:focus { border-color: #7b2cbf; }
        .btn { width: 100%; padding: 12px; background: #7b2cbf; color: white; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #621b9e; }
        .error { color: #b00020; text-align: center; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Admin Login</h2>
        <?php if ($error !== ''): ?>
            <div class="error"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn">Login</button>
        </form>
    </div>
</body>
</html>
