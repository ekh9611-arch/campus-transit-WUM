<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

// Agar driver pehle se login hai, to dashboard par bhejein
if (!empty($_SESSION['driver_id'])) {
    header('Location: pages/driver_dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if ($user === '' || $pass === '') {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $db = new mysqli('localhost', 'root', '', 'university_transport');
            $db->set_charset('utf8mb4');

            $stmt = $db->prepare("SELECT driver_id, password_hash FROM drivers WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $user);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $db->close();

            $hash = $row['password_hash'] ?? '';
            $id   = $row['driver_id'] ?? null;

            if ($id !== null && $hash !== '' && password_verify($pass, $hash)) {
                session_regenerate_id(true);
                $_SESSION['driver_id'] = (int)$id;
                header('Location: pages/driver_dashboard.php');
                exit();
            }

            usleep(400000);
            $error = 'Wrong username or password.';
        } catch (mysqli_sql_exception $e) {
            error_log('Driver login error: ' . $e->getMessage());
            $error = 'Login is not available right now.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Login | CampusTransit WUM</title>
  <style>
    * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
    body { background: #eaf4fd; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 16px; }
    .box { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 20px rgba(11,79,168,0.12); width: 100%; max-width: 380px; }
    h2 { color: #0b4fa8; text-align: center; margin-top: 0; }
    label { display: block; font-weight: 600; color: #4a5f78; margin: 12px 0 5px; }
    input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; }
    input:focus { outline: none; border-color: #1e7be0; }
    .btn { width: 100%; margin-top: 18px; padding: 12px; background: #1e7be0; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
    .btn:hover { background: #0a63c4; }
    .error { color: #b00020; text-align: center; font-weight: bold; margin-bottom: 10px; }
  </style>
</head>
<body>
  <div class="box">
    <h2>Driver Login</h2>
    <?php if ($error !== ''): ?>
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="POST" action="" autocomplete="off">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required autocomplete="off">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="new-password">
      <button type="submit" class="btn">Login</button>
    </form>
  </div>
</body>
</html>