<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// route=0 ya khali ho to sab alerts, warna sirf us route ke
$route = (isset($_GET['route']) && ctype_digit((string)$_GET['route'])) ? (int)$_GET['route'] : 0;

try {
    $db = new mysqli('localhost', 'root', '', 'university_transport');
    $db->set_charset('utf8mb4');

    $routes = $db->query("SELECT route_no FROM routes ORDER BY route_no ASC")
        ->fetch_all(MYSQLI_ASSOC);

    $stmt = $db->prepare(
        "SELECT MAX(a.alert_id) AS alert_id, b.bus_number, r.route_no
         FROM bus_alerts a
         JOIN buses b ON b.bus_id = a.bus_id
         LEFT JOIN routes r ON r.route_id = b.route_id
         WHERE a.status = 'confirmed'
           AND (? = 0 OR r.route_no = ?)
         GROUP BY b.bus_id, b.bus_number, r.route_no
         ORDER BY b.bus_number ASC"
    );
    $stmt->bind_param('ii', $route, $route);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();

    $alerts = array_map(function ($r) {
        return [
            'id'         => (int)$r['alert_id'],
            'bus_number' => $r['bus_number'],
            'route_no'   => $r['route_no'] !== null ? (int)$r['route_no'] : null,
        ];
    }, $rows);

    $route_list = array_map(fn($r) => (int)$r['route_no'], $routes);

    echo json_encode(
        ['alerts' => $alerts, 'routes' => $route_list],
        JSON_UNESCAPED_UNICODE
    );
} catch (mysqli_sql_exception $e) {
    error_log('get_alerts error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load alerts']);
}