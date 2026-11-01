<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$status_map = [
    'active'      => ['status-active',      '🟢 On Route'],
    'maintenance' => ['status-maintenance', '🟡 Maintenance'],
    'breakdown'   => ['status-breakdown',   '🔴 Breakdown'],
];

$fleet    = [];
$db_error = '';

try {
    $db = new mysqli('localhost', 'root', '', 'university_transport');
    $db->set_charset('utf8mb4');

    $stmt = $db->prepare(
        "SELECT b.bus_id, b.bus_number, b.status,
                r.route_no, r.route_description,
                d.driver_name, d.phone_number
         FROM buses b
         LEFT JOIN routes  r ON r.route_id = b.route_id
         LEFT JOIN drivers d ON d.bus_id   = b.bus_id
         ORDER BY b.bus_number ASC"
    );
    $stmt->execute();
    $fleet = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $db->close();
} catch (mysqli_sql_exception $e) {
    error_log('Fleet page DB error: ' . $e->getMessage());
    $db_error = 'Fleet data could not be loaded right now. Please try again later.';
}

$total_buses  = count($fleet);
$active_buses = 0;
foreach ($fleet as $row) {
    if (($row['status'] ?? '') === 'active') {
        $active_buses++;
    }
}
$issue_buses = $total_buses - $active_buses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="CampusTransit WUM fleet schedule and live campus tracking map.">
  <title>Bus Tracking | CampusTransit WUM</title>

  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="../css/style.css">

  <style>
    .page-hero {
      background: radial-gradient(circle at 90% 20%, rgba(30, 123, 224, 0.25), transparent 35%),
                  linear-gradient(120deg, #083c87, #0b4fa8);
      color: #fff;
      padding: 62px 0;
    }
    .page-hero h1 { color: #fff; font-size: clamp(2.4rem, 5.2vw, 3.5rem); font-weight: 800; line-height: 1.15; margin: 0 0 14px; }
    .page-hero p { max-width: 700px; color: #dbeafe; font-size: 1.05rem; }
    .hero-label { color: #bfdbfe; font-size: 0.82rem; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 12px; }
    .breadcrumb { margin-top: 22px; font-size: 0.85rem; color: #dbeafe; }
    .breadcrumb a { color: #fff; }

    .alert-banner { background: #dc2626; color: #fff; padding: 14px 20px; font-weight: 700; font-size: 0.95rem; line-height: 1.5; text-align: center; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
    .alert-banner[hidden] { display: none; }
    .route-pick { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px 16px; background: #eaf4fd; font-size: 0.9rem; }
    .route-pick select { padding: 6px 10px; border: 1px solid #dbeafe; border-radius: 6px; font: inherit; }
    .notify-btn { padding: 6px 12px; border: none; border-radius: 6px; background: #1e7be0; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
    .notify-btn:disabled { opacity: 0.6; cursor: default; }

    .section { padding: 45px 0; }
    .section-heading { margin: 0 0 12px; color: var(--primary); font-size: clamp(25px, 3vw, 34px); line-height: 1.3; }
    .section-description { color: var(--muted); max-width: 800px; margin-bottom: 24px; }
    .demo-tag { display: inline-block; margin-left: 6px; color: var(--accent); font-size: 12px; font-weight: 700; vertical-align: middle; }

    .map-card { background: #fff; border: 1px solid var(--border); padding: 16px; border-radius: 12px; box-shadow: var(--shadow); }
    #map { height: 430px; width: 100%; border-radius: 12px; background: var(--tint); border: 1px solid var(--border); z-index: 1; }
    .map-note { display: flex; align-items: flex-start; gap: 10px; margin-top: 14px; padding: 13px 15px; background: var(--tint); border-radius: 10px; color: var(--primary); font-size: 13px; }

    .stats-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; margin: 25px 0; }
    .stat-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 22px; box-shadow: var(--shadow); }
    .stat-card p { color: var(--muted); font-size: 14px; margin-bottom: 0; }
    .stat-card h3 { margin-top: 8px; color: var(--primary); font-size: 30px; }

    .control-panel { margin-top: 30px; padding: 25px; background: #fff; border: 1px solid var(--border); border-left: 5px solid var(--accent); border-radius: var(--radius); box-shadow: var(--shadow); }
    .control-panel h3 { color: var(--primary); font-size: 23px; margin-bottom: 8px; }
    .control-panel p { color: var(--muted); margin-bottom: 12px; }
    .diagnostic-log { margin-top: 18px; padding: 16px; border-radius: 10px; background: #102f35; color: #9df4c4; overflow-wrap: anywhere; font-family: "Courier New", monospace; font-size: 13px; }
    .diagnostic-log strong { display: block; margin-bottom: 5px; }

    .table-wrapper { margin-top: 22px; overflow-x: auto; background: #fff; border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); }
    .fleet-table { width: 100%; min-width: 720px; border-collapse: collapse; text-align: left; }
    .fleet-table th, .fleet-table td { padding: 15px 17px; border-bottom: 1px solid #e7eeee; }
    .fleet-table th { background: var(--primary); color: #fff; font-size: 13px; white-space: nowrap; }
    .fleet-table tbody tr:nth-child(even) { background: #f5faff; }
    .fleet-table tbody tr:hover { background: var(--tint); }
    .fleet-table tbody tr:last-child td { border-bottom: none; }

    .status-badge { display: inline-block; padding: 5px 11px; border-radius: 4px; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .status-active { background: #dcfce7; color: #166534; }
    .status-maintenance { background: #fef3c7; color: #92400e; }
    .status-breakdown { background: #ffe0df; color: #a52320; }

    @media (max-width: 900px) {
      .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 480px) {
      .stats-grid { grid-template-columns: 1fr; }
      #map { height: 300px; }
    }
  </style>
</head>

<body>

  <!-- Header -->
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php">
        <img class="logo-img" src="../images/wum-logo.jpg" alt="Women University Multan logo">
        <span>CampusTransit WUM</span>
      </a>

      <button class="nav-toggle" id="navToggle" type="button" aria-expanded="false" aria-controls="siteNav">Menu</button>

      <nav class="main-nav" id="siteNav" aria-label="Main navigation">
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a class="active" href="schedule.php" aria-current="page">Bus Tracking</a></li>
          <li><a href="analytics.php">Reports</a></li>
          <li><a href="contact.php">Contact Us</a></li>
          <li><a href="../login.php">Admin Login</a></li>
          <li><a href="../driver_login.php">Driver Login</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Alert banner and route picker -->
  <div id="alertBanner" class="alert-banner" role="alert" hidden></div>
  <div class="route-pick">
    <label for="routePick">Your route:</label>
    <select id="routePick">
      <option value="0">All routes</option>
    </select>
    <button id="notifyBtn" class="notify-btn" type="button">🔔 Enable alerts</button>
    <button id="checkAlertsBtn" class="notify-btn" type="button">🚨 Check alerts</button>
  </div>

  <main>

    <!-- Hero -->
    <section class="page-hero">
      <div class="container">
        <div class="hero-label">Women University Multan</div>
        <h1>Live Bus Tracking</h1>
        <p>Track the university buses live on the map and check the status, driver and route of every vehicle in the fleet.</p>
        <div class="breadcrumb">
          <a href="index.php">Home</a> &nbsp; › &nbsp; Bus Tracking
        </div>
      </div>
    </section>

    <!-- Map -->
    <section class="section" id="tracking">
      <div class="container">
        <h2 class="section-heading">
          Campus Tracking Map
          <span class="demo-tag">LIVE · refreshes every 5 seconds</span>
        </h2>

        <p class="section-description">
          Coloured circles are buses, positioned from the database and refreshed automatically. Grey circles are fixed campus checkpoints.
        </p>

        <div class="map-card">
          <div id="map" role="region" aria-label="Campus checkpoint map"></div>
          <div class="map-note">
            <span aria-hidden="true">ℹ️</span>
            <p>
              <strong>Live status:</strong>
              <span id="liveStatus">Waiting for bus locations...</span>
              Map tiles require an internet connection.
            </p>
          </div>
        </div>

        <!-- Demo simulation -->
        <div class="control-panel">
          <h3>🚨 Emergency Control Center</h3>
          <p>
            Simulate a mechanical breakdown for bus MNG-116 to see how its status changes in the fleet table. This is a demonstration only and does not change the database.
          </p>
          <button id="simulateBreakdownBtn" class="btn btn-danger" type="button">Simulate Fleet Breakdown</button>
          <div class="diagnostic-log" aria-live="polite">
            <strong>System Diagnostic Log</strong>
            <p id="diagnosticLog">System operational. Fleet data loaded from the database.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Fleet table -->
    <section class="section" id="fleet">
      <div class="container">
        <h2 class="section-heading">All Buses &amp; Drivers</h2>
        <p class="section-description">Vehicle assignments, drivers and statuses are loaded from the transport database.</p>

        <div class="stats-grid">
          <div class="stat-card">
            <p>Total Vehicles</p>
            <h3 id="totalBuses"><?= (int)$total_buses ?></h3>
          </div>
          <div class="stat-card">
            <p>Active Vehicles</p>
            <h3 id="activeBuses"><?= (int)$active_buses ?></h3>
          </div>
          <div class="stat-card">
            <p>Maintenance / Breakdown</p>
            <h3 id="issueBuses"><?= (int)$issue_buses ?></h3>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="fleet-table">
            <thead>
              <tr>
                <th>Bus ID</th>
                <th>Route Allocation</th>
                <th>Assigned Driver</th>
                <th>Contact Number</th>
                <th>Tracking Status</th>
              </tr>
            </thead>
            <tbody id="fleetTableBody">
              <?php if ($db_error !== ''): ?>
                <tr><td colspan="5"><?= h($db_error) ?></td></tr>
              <?php elseif (empty($fleet)): ?>
                <tr><td colspan="5">No buses have been added to the database yet.</td></tr>
              <?php else: ?>
                <?php foreach ($fleet as $bus): ?>
                  <?php
                    $st = isset($status_map[$bus['status'] ?? '']) ? $bus['status'] : 'maintenance';
                    $route_label = $bus['route_no'] !== null
                        ? 'Route ' . $bus['route_no'] . ' — ' . $bus['route_description']
                        : 'Not assigned';
                  ?>
                  <tr data-bus="<?= h($bus['bus_number']) ?>" data-status="<?= h($st) ?>">
                    <td><?= h($bus['bus_number']) ?></td>
                    <td><?= h($route_label) ?></td>
                    <td><?= h($bus['driver_name'] ?? 'Not assigned') ?></td>
                    <td><?= h($bus['phone_number'] ?? 'N/A') ?></td>
                    <td>
                      <span class="status-badge <?= h($status_map[$st][0]) ?>"><?= h($status_map[$st][1]) ?></span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <div>
        <strong>CampusTransit WUM</strong>
        <p class="footer-small">Educational transport planning prototype.</p>
      </div>
      <p class="footer-small">© 2026 CampusTransit WUM. For educational use only.</p>
    </div>
  </footer>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <!-- Map and fleet script -->
  <script>
  (function () {
    "use strict";

    var navToggle = document.getElementById("navToggle");
    var siteNav = document.getElementById("siteNav");
    navToggle.addEventListener("click", function () {
      var isOpen = siteNav.classList.toggle("open");
      navToggle.setAttribute("aria-expanded", String(isOpen));
      navToggle.textContent = isOpen ? "Close" : "Menu";
    });

    var POLL_MS = 5000;
    var STATUS_COLORS = { active: "#16a34a", maintenance: "#d97706", breakdown: "#dc2626" };
    var map = null;
    var firstFit = true;
    var busMarkers = {};
    var simulatedStatus = {};

    function setLiveStatus(text) {
      var el = document.getElementById("liveStatus");
      if (el) el.textContent = text;
    }

    function makePopup(bus, status) {
      var box = document.createElement("div");
      var title = document.createElement("strong");
      var line1 = document.createElement("p");
      var line2 = document.createElement("p");
      title.textContent = bus.bus_number;
      line1.textContent = bus.route_no ? "Route " + bus.route_no : "Route not assigned";
      line2.textContent = "Driver: " + (bus.driver_name || "Not assigned") + " | Status: " + status;
      box.append(title, line1, line2);
      return box;
    }

    function initTrackingMap() {
      var mapSlot = document.getElementById("map");
      if (typeof L === "undefined") {
        mapSlot.textContent = "The map library could not load. Check your internet connection and refresh the page.";
        return;
      }
      try {
        map = L.map("map").setView([30.1978, 71.4694], 12);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          attribution: "&copy; OpenStreetMap contributors",
          maxZoom: 19
        }).addTo(map);

        var checkpoints = [
          { name: "WUM Mattital Campus Terminal", lat: 30.2712, lon: 71.4921 },
          { name: "Shah Rukn-e-Alam Checkpoint", lat: 30.2015, lon: 71.5032 },
          { name: "Kutchery Checkpoint", lat: 30.1978, lon: 71.4694 },
          { name: "Chowk Kumharanwala Checkpoint", lat: 30.1892, lon: 71.5215 }
        ];

        checkpoints.forEach(function (point) {
          var popup = document.createElement("div");
          var title = document.createElement("strong");
          var detail = document.createElement("p");
          title.textContent = point.name;
          detail.textContent = "Fixed campus checkpoint.";
          popup.append(title, detail);
          L.circleMarker([point.lat, point.lon], {
            radius: 7, color: "#475569", fillColor: "#94a3b8", fillOpacity: 0.9, weight: 2
          }).addTo(map).bindPopup(popup);
        });
      } catch (error) {
        console.error("Map initialization failed:", error);
        map = null;
        mapSlot.textContent = "The map could not be initialized. The fleet table remains available.";
      }
    }

    function updateBusLocations() {
      if (!map) return;

      fetch("../get_fleet_locations.php", { cache: "no-store" })
        .then(function (response) {
          if (!response.ok) throw new Error("HTTP " + response.status);
          return response.json();
        })
        .then(function (data) {
          if (!data || !Array.isArray(data.buses)) throw new Error("Unexpected response");

          var positions = [];

          data.buses.forEach(function (bus) {
            var lat = parseFloat(bus.latitude);
            var lng = parseFloat(bus.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            var status = simulatedStatus[bus.bus_number] || bus.status || "active";
            var color = STATUS_COLORS[status] || STATUS_COLORS.maintenance;
            var pos = [lat, lng];
            positions.push(pos);

            var marker = busMarkers[bus.bus_number];
            if (!marker) {
              marker = L.circleMarker(pos, {
                radius: 11, color: "#ffffff", weight: 3, fillColor: color, fillOpacity: 1
              }).addTo(map);
              marker.bindTooltip(bus.bus_number, { direction: "top" });
              busMarkers[bus.bus_number] = marker;
            } else {
              marker.setLatLng(pos);
              marker.setStyle({ fillColor: color });
            }
            marker.bindPopup(makePopup(bus, status));
          });

          if (firstFit && positions.length > 0) {
            map.fitBounds(positions, { padding: [40, 40], maxZoom: 14 });
            firstFit = false;
          }

          setLiveStatus(
            positions.length > 0
              ? positions.length + " bus(es) reporting. Last updated " + new Date().toLocaleTimeString() + "."
              : "No bus has reported a location yet."
          );
        })
        .catch(function () {
          setLiveStatus("Unable to fetch live locations. Retrying...");
        });
    }

    function updateFleetStats() {
      var rows = document.querySelectorAll("#fleetTableBody tr[data-status]");
      var active = 0;
      rows.forEach(function (row) {
        if (row.dataset.status === "active") active++;
      });
      document.getElementById("totalBuses").textContent = rows.length;
      document.getElementById("activeBuses").textContent = active;
      document.getElementById("issueBuses").textContent = rows.length - active;
    }

    function setupBreakdownSimulation() {
      var triggerBtn = document.getElementById("simulateBreakdownBtn");
      var log = document.getElementById("diagnosticLog");

      triggerBtn.addEventListener("click", function () {
        var row = document.querySelector('#fleetTableBody tr[data-bus="MNG-116"]');
        if (!row) {
          log.textContent = "Bus MNG-116 was not found in the fleet table.";
          return;
        }
        row.dataset.status = "breakdown";
        var badge = row.querySelector(".status-badge");
        badge.className = "status-badge status-breakdown";
        badge.textContent = "🔴 Breakdown";
        simulatedStatus["MNG-116"] = "breakdown";
        if (busMarkers["MNG-116"]) {
          busMarkers["MNG-116"].setStyle({ fillColor: STATUS_COLORS.breakdown });
        }
        updateFleetStats();
        triggerBtn.disabled = true;
        triggerBtn.textContent = "Simulation Applied";
        log.textContent = "DEMO ALERT: Bus MNG-116 is marked as broken down on this page only. The database was not changed and no real bus was contacted.";
      });
    }

    document.addEventListener("DOMContentLoaded", function () {
      initTrackingMap();
      updateBusLocations();
      setInterval(updateBusLocations, POLL_MS);
      setupBreakdownSimulation();
    });
  })();
  </script>

  <!-- Breakdown alert and route picker -->
  <script>
  (function () {
    "use strict";

    var box = document.getElementById("alertBanner");
    var pick = document.getElementById("routePick");
    var checkBtn = document.getElementById("checkAlertsBtn");
    var notifyBtn = document.getElementById("notifyBtn");
    if (!box || !pick) return;

    var KEY = "utms_route";
    var routesLoaded = false;

    function fillRoutes(data) {
      if (routesLoaded || !Array.isArray(data.routes)) return;
      data.routes.forEach(function (no) {
        var opt = document.createElement("option");
        opt.value = String(no);
        opt.textContent = "Route " + no;
        pick.appendChild(opt);
      });
      var saved = localStorage.getItem(KEY);
      if (saved !== null && pick.querySelector('option[value="' + saved + '"]')) {
        pick.value = saved;
      }
      routesLoaded = true;
    }

    function render(list, showEmpty) {
      box.textContent = "";
      box.style.background = "";

      if (list.length === 0) {
        if (showEmpty) {
          box.style.background = "#16a34a";
          box.textContent = "Is route par abhi koi breakdown alert nahi hai.";
          box.hidden = false;
        } else {
          box.hidden = true;
        }
        return;
      }

      box.textContent = list.map(function (a) {
        return "Bus " + a.bus_number +
          (a.route_no ? " (Route " + a.route_no + ")" : "") +
          " is broken down. Please wait for another bus.";
      }).join(" ");
      box.hidden = false;
    }

    function check(showEmpty, notify) {
      fetch("../get_alerts.php?route=" + encodeURIComponent(pick.value), { cache: "no-store" })
        .then(function (res) {
          if (!res.ok) throw new Error("HTTP " + res.status);
          return res.json();
        })
        .then(function (data) {
          fillRoutes(data);
          var list = data.alerts || [];
          render(list, showEmpty);

          if (notify && list.length > 0 && "Notification" in window && Notification.permission === "granted") {
            new Notification("CampusTransit WUM", {
              body: list.map(function (a) { return "Bus " + a.bus_number + " is broken down."; }).join(" ")
            });
          }
        })
        .catch(function () {
          box.hidden = true;
          box.textContent = "";
        });
    }

    pick.addEventListener("change", function () {
      localStorage.setItem(KEY, pick.value);
      check(false, false);
    });

    if (checkBtn) {
      checkBtn.addEventListener("click", function () {
        check(true, true);
      });
    }

    if (notifyBtn) {
      if (!("Notification" in window)) {
        notifyBtn.hidden = true;
      } else if (Notification.permission === "granted") {
        notifyBtn.textContent = "🔔 Alerts on";
        notifyBtn.disabled = true;
      }
      notifyBtn.addEventListener("click", function () {
        Notification.requestPermission().then(function (permission) {
          notifyBtn.textContent = permission === "granted" ? "🔔 Alerts on" : "Alerts blocked";
          notifyBtn.disabled = true;
        });
      });
    }

    // Page khulte hi sirf route list bharein, banner nahi
    fetch("../get_alerts.php?route=0", { cache: "no-store" })
      .then(function (res) { return res.json(); })
      .then(function (data) { fillRoutes(data); })
      .catch(function () {});
  })();
  </script>

</body>
</html>


