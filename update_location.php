<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'POST only']);
}

// Sirf wahi driver location bhej sakta hai jo login hai
if (empty($_SESSION['driver_id'])) {
    respond(401, ['error' => 'Not logged in']);
}

$lat = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false
    || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    respond(400, ['error' => 'Invalid coordinates']);
}

try {
    $db = new mysqli('localhost', 'root', '', 'university_transport');
    $db->set_charset('utf8mb4');

    // Driver ki bus session wale driver se dhoondein
    $driver_id = (int)$_SESSION['driver_id'];
    $stmt = $db->prepare("SELECT bus_id FROM drivers WHERE driver_id = ? LIMIT 1");
    $stmt->bind_param('i', $driver_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['bus_id'])) {
        $db->close();
        respond(404, ['error' => 'No bus assigned to this driver']);
    }

    $bus_id = (int)$row['bus_id'];
    $lat = (float)$lat;
    $lng = (float)$lng;

    $stmt = $db->prepare(
        "INSERT INTO bus_locations (bus_id, latitude, longitude)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude)"
    );
    $stmt->bind_param('idd', $bus_id, $lat, $lng);
    $stmt->execute();
    $stmt->close();
    $db->close();

    respond(200, ['ok' => true]);
} catch (mysqli_sql_exception $e) {
    error_log('update_location error: ' . $e->getMessage());
    respond(500, ['error' => 'Server error']);
}
