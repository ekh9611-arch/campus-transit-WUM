<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Invalid request.']);
}

// Spam trap: asli visitor ye field nahi bharta
if (trim((string)($_POST['hp_field'] ?? '')) !== '') {
    respond(200, ['ok' => true]);
}

// Har 30 second mein ek message
$now = time();
if (isset($_SESSION['last_contact']) && ($now - (int)$_SESSION['last_contact']) < 30) {
    respond(429, ['error' => 'Please wait a few seconds before sending another message.']);
}

$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$allowed_subjects = ['Bus pass', 'Routes and timings', 'Complaint or feedback', 'Other'];

if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    respond(422, ['error' => 'Please enter your name.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    respond(422, ['error' => 'Please enter a valid email address.']);
}
if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
    respond(422, ['error' => 'Please enter a valid phone number or leave it empty.']);
}
if (!in_array($subject, $allowed_subjects, true)) {
    respond(422, ['error' => 'Please choose a topic.']);
}
if (mb_strlen($message) < 5 || mb_strlen($message) > 1000) {
    respond(422, ['error' => 'Your message should be between 5 and 1000 characters.']);
}

try {
    $db = new mysqli('localhost', 'root', '', 'university_transport');
    $db->set_charset('utf8mb4');

    $stmt = $db->prepare(
        "INSERT INTO contact_messages (name, email, phone, subject, message)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssss', $name, $email, $phone, $subject, $message);
    $stmt->execute();
    $stmt->close();
    $db->close();

    $_SESSION['last_contact'] = $now;
    respond(200, ['ok' => true]);
} catch (mysqli_sql_exception $e) {
    error_log('contact_submit error: ' . $e->getMessage());
    respond(500, ['error' => 'Your message could not be saved right now. Please try again later.']);
}