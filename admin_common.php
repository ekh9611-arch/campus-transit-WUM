<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

function db(): mysqli
{
    static $db = null;
    if ($db === null) {
        $db = new mysqli('localhost', 'root', '', 'university_transport');
        $db->set_charset('utf8mb4');
    }
    return $db;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function flash(string $type, string $text): string
{
    return '<div class="alert ' . h($type) . '">' . h($text) . '</div>';
}

function admin_header(string $title, string $active): void
{
    $links = [
    'admin.php'           => 'Add Route',
    'admin_stops.php'     => 'Stops',
    'admin_fleet.php'     => 'Fleet Settings',
    'admin_expenses.php'  => 'Expenses',
    'admin_status.php'    => 'Bus Status',
    'admin_messages.php'  => 'Messages',
    'pages/analytics.php' => 'Reports',
    'pages/index.php'     => 'View Site',
    'logout.php'          => 'Logout',
];
?>
<!DOCTYPE html>
<html lang="en">
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
    <title><?= h($title) ?> | UTMS Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: #f4f6f9; color: #333; }
        .topnav { background: #0b4fa8; display: flex; flex-wrap: wrap; gap: 4px; padding: 10px 20px; }
        .topnav a { color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 8px 14px; border-radius: 6px; }
        .topnav a:hover, .topnav a.active { background: rgba(255, 255, 255, 0.22); }
        .wrap { max-width: 900px; margin: 24px auto; padding: 0 16px; }
        .box { background: #fff; padding: 26px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05); margin-bottom: 22px; }
        h2 { color: #0b4fa8; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #0b4fa8; }
        .hint { color: #777; font-size: 13px; margin: -6px 0 14px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 14px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; outline: none; background: #fff; }
        input:focus, select:focus { border-color: #0b4fa8; }
        .btn { display: inline-block; padding: 11px 22px; background: #0b4fa8; color: #fff; border: none; border-radius: 5px; font-size: 15px; font-weight: bold; cursor: pointer; }
        .btn:hover { background: #083c87; }
        .alert { padding: 11px; border-radius: 5px; margin-bottom: 16px; font-weight: 600; text-align: center; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .table-wrap { overflow-x: auto; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; min-width: 560px; }
        th { background: #0b4fa8; color: #fff; text-align: left; padding: 10px; font-size: 13px; }
        td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 14px; }
        td input, td select { padding: 7px; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } .box { padding: 18px; } }
    </style>
</head>
<body>
    <nav class="topnav">
        <?php foreach ($links as $href => $label): ?>
            <a href="<?= h($href) ?>"<?= $href === $active ? ' class="active"' : '' ?>><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="wrap">
<?php
}

function admin_footer(): void
{
?>
    </div>
</body>
</html>
<?php
}
