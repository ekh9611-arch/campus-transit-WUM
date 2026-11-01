<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $db = new mysqli('localhost', 'root', '', 'university_transport');
    $db->set_charset('utf8mb4');

    $stmt = $db->prepare(
        "SELECT b.bus_number, b.status,
                bl.latitude, bl.longitude,
                r.route_no,
                d.driver_name
         FROM Bus_Locations bl
         INNER JOIN Buses b       ON b.bus_id   = bl.bus_id
         LEFT JOIN  Routes r      ON r.route_id = b.route_id
         LEFT JOIN  Drivers d     ON d.bus_id   = b.bus_id
         ORDER BY b.bus_number ASC"
    );
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    $buses = [];
    foreach ($rows as $row) {
        $buses[] = [
            'bus_number'  => $row['bus_number'],
            'status'      => $row['status'],
            'latitude'    => (float)$row['latitude'],
            'longitude'   => (float)$row['longitude'],
            'route_no'    => $row['route_no'] !== null ? (int)$row['route_no'] : null,
            'driver_name' => $row['driver_name'],
        ];
    }

    echo json_encode(['buses' => $buses], JSON_UNESCAPED_UNICODE);
} catch (mysqli_sql_exception $e) {
    error_log('get_fleet_locations error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load locations']);
}
