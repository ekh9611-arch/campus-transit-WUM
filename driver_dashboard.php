<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
if (empty($_SESSION['driver_id'])) {
    header('Location: ../driver_login.php');
    exit();
}
$driver_id = (int)$_SESSION['driver_id'];

$bus_info = null;
$message = '';

try {
    $db = new mysqli('localhost', 'root', '', 'university_transport');
    $db->set_charset('utf8mb4');

    // ڈرائیور کی بس اور روٹ کی تفصیلات نکالنا
    $stmt = $db->prepare(
        "SELECT d.driver_name, b.bus_id, b.bus_number, r.route_no, r.route_description 
         FROM drivers d
         INNER JOIN buses b ON d.bus_id = b.bus_id
         LEFT JOIN routes r ON b.route_id = r.route_id
         WHERE d.driver_id = ?"
    );
    $stmt->bind_param('i', $driver_id);
    $stmt->execute();
    $bus_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

   if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emergency_alert'])) {
    if ($bus_info) {
        $bus_id = (int)$bus_info['bus_id'];

        // Agar pehle se pending report hai to dobara na banayein
        $chk = $db->prepare("SELECT alert_id FROM bus_alerts WHERE bus_id = ? AND status = 'pending' LIMIT 1");
        $chk->bind_param('i', $bus_id);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($exists) {
            $message = "<div style='background:#fef3c7; color:#92400e; padding:10px; border-radius:6px; font-weight:bold; margin-bottom:15px;'>Report already sent. Waiting for admin confirmation.</div>";
        } else {
            $msg = 'Breakdown reported by driver';
            $ins = $db->prepare("INSERT INTO bus_alerts (bus_id, driver_id, message) VALUES (?, ?, ?)");
            $ins->bind_param('iis', $bus_id, $driver_id, $msg);
            $ins->execute();
            $ins->close();

            $message = "<div style='background:#dcfce7; color:#166534; padding:10px; border-radius:6px; font-weight:bold; margin-bottom:15px;'>Breakdown report sent to admin. Bus status will change after confirmation.</div>";
        }
    }
}
    $db->close();
} catch (Exception $e) {
    error_log('Driver Dashboard Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Portal | CampusTransit WUM</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .driver-box { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 600px; margin: 40px auto; }
    .driver-meta { border-bottom: 1px solid #e2e8f0; padding-bottom: 15px; margin-bottom: 15px; }
    .btn-gps {
  display: block;
  width: 100%;
  padding: 12px 20px;
  margin-bottom: 15px;
  border: none;
  border-radius: 6px;
  background: #16a34a;
  color: #ffffff;
  font-size: 1rem;
  font-weight: bold;
  cursor: pointer;
}

.btn-gps.tracking {
  background: #dc2626;
}
    .btn-danger { background: #fee2e2; color: #991b1b; border: 1px solid #f87171; padding: 10px; border-radius: 6px; width: 100%; font-weight: bold; cursor: pointer; }
    .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: bold; background: #dcfce7; color: #166534; }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
  </style>
</head>
<body>

  <div class="driver-box">
    <p style="text-align:right; margin:0 0 10px;"><a href="../driver_logout.php">Logout</a></p>
    <h2>🪪 Driver Control Panel</h2>
    <p>Welcome, <strong><?= htmlspecialchars($bus_info['driver_name'] ?? 'Driver') ?></strong></p>
    
    <?= $message ?>

    <?php if ($bus_info): ?>
      <div class="driver-meta">
        <p><strong>Assigned Bus:</strong> <?= htmlspecialchars($bus_info['bus_number']) ?></p>
         <p><strong>Current Route:</strong> Route <?= htmlspecialchars((string)($bus_info['route_no'] ?? 'N/A')) ?> - <?= htmlspecialchars((string)($bus_info['route_description'] ?? 'No Route Assigned')) ?></p>
      </div>

      <!-- لائیو لوکیشن کا بٹن (اب بس نمبر پاس کرے گا) -->
      <button id="trackBtn" class="btn-gps" onclick="toggleTracking()">📡 Start Journey (Share Live GPS)</button>
      <p id="gpsStatus" style="text-align:center; font-size:0.9rem; color:#64748b; font-weight:bold;"></p>

      <!-- ایمرجنسی بریک ڈاؤن کا فارم -->
      <form method="POST" action="" onsubmit="return confirm('Are you sure you want to send a breakdown alert?');">
        <button type="submit" name="emergency_alert" class="btn-danger">🚨 Report Bus Breakdown / Emergency</button>
      </form>
    <?php else: ?>
      <p style="color: red;">No bus or route assigned to your profile yet.</p>
    <?php endif; ?>
  </div>

  <script>
    let watchId = null;

function toggleTracking() {
    const btn = document.getElementById('trackBtn');
    const statusText = document.getElementById('gpsStatus');

    if (watchId === null) {
        if (!navigator.geolocation) {
            alert("Your browser/device does not support GPS tracking.");
            return;
        }

        watchId = navigator.geolocation.watchPosition(sendLocationToServer, handleError, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 5000
        });

        btn.textContent = "🛑 Stop Journey (Stop Sharing)";
        btn.classList.add('tracking');
        statusText.textContent = "📡 GPS connected. Sending live coordinates to map...";
    } else {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
        btn.textContent = "📡 Start Journey (Share Live GPS)";
        btn.classList.remove('tracking');
        statusText.textContent = "Tracking stopped.";
    }
}

function sendLocationToServer(position) {
  var payload = new FormData();
  payload.append('latitude', position.coords.latitude);
  payload.append('longitude', position.coords.longitude);

  fetch('../update_location.php', {
    method: 'POST',
    body: payload,
    credentials: 'same-origin',
    headers: { 'ngrok-skip-browser-warning': 'true' }
  })
  .then(function (res) {
    return res.text().then(function (text) {
      var data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        throw new Error('Server ne HTML bheja (HTTP ' + res.status + ')');
      }
      if (!res.ok || !data.ok) {
        throw new Error(data.error || ('HTTP ' + res.status));
      }
      document.getElementById('gpsStatus').textContent = 'Location sent at ' + new Date().toLocaleTimeString();
    });
  })
  .catch(function (err) {
    document.getElementById('gpsStatus').textContent = 'Location not sent: ' + err.message;
  });
}

function handleError(err) {
    document.getElementById('gpsStatus').textContent = "GPS error: " + err.message;
}
  </script>
</body>
</html>

