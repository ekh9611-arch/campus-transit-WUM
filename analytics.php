<?php
declare(strict_types=1);

session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: ../login.php');
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$active_buses = 0;
$total_fuel   = 0.0;
$mean_expense = null;
$route_fuel   = [];
$bus_fuel     = [];
$max_litres   = 0.0;
$db_error     = '';

try {
    $db = new mysqli('localhost', 'root', '', 'university_transport');
    $db->set_charset('utf8mb4');

    $active_buses = (int)$db->query(
        "SELECT COUNT(*) AS c FROM buses WHERE status = 'active'"
    )->fetch_assoc()['c'];

    $total_fuel = (float)$db->query(
        "SELECT COALESCE(SUM(litres), 0) AS t FROM fuel_logs"
    )->fetch_assoc()['t'];

    $route_fuel = $db->query(
        "SELECT r.route_no, SUM(f.litres) AS litres
         FROM fuel_logs f
         INNER JOIN buses b  ON b.bus_id   = f.bus_id
         INNER JOIN routes r ON r.route_id = b.route_id
         GROUP BY r.route_no
         ORDER BY r.route_no ASC"
    )->fetch_all(MYSQLI_ASSOC);

    foreach ($route_fuel as $rf) {
        $max_litres = max($max_litres, (float)$rf['litres']);
    }

    $bus_fuel = $db->query(
        "SELECT b.bus_number, b.mileage_km_per_l, r.distance_km,
                COALESCE(SUM(f.litres), 0) AS litres_bought,
                COALESCE(SUM(f.cost), 0)   AS total_cost
         FROM buses b
         LEFT JOIN routes r    ON r.route_id = b.route_id
         LEFT JOIN fuel_logs f ON f.bus_id   = b.bus_id
         GROUP BY b.bus_id, b.bus_number, b.mileage_km_per_l, r.distance_km
         ORDER BY b.bus_number ASC"
    )->fetch_all(MYSQLI_ASSOC);

    $avg = $db->query("SELECT AVG(amount) AS m FROM maintenance_expenses")->fetch_assoc()['m'];
    $mean_expense = $avg !== null ? (float)$avg : null;

    $db->close();
} catch (mysqli_sql_exception $e) {
    error_log('Reports error: ' . $e->getMessage());
    $db_error = 'Some figures could not be loaded. Please check the database.';
}

$policy = [
    'Verify parts prices against approved supplier rates before each purchase.',
    'Schedule an engine wear inspection every 45 days for each active vehicle.',
    'Review rate agreements with transport contractors at the start of each academic session.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports | CampusTransit WUM</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .rep-hero { background: linear-gradient(120deg, #083c87, #0b4fa8); color: #fff; padding: 40px 0; }
    .rep-hero h1 { color: #fff; margin: 0; text-transform: uppercase; font-size: 2rem; }
    .rep-section { padding: 30px 0; }
    .rep-section.tint { background: var(--tint); }
    .kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.2rem; }
    .kpi { background: #fff; border-radius: 12px; box-shadow: var(--shadow); padding: 1.4rem; text-align: center; }
    .kpi-value { font-size: 2rem; font-weight: 800; color: var(--primary); margin: 0.4rem 0 0; }
    .panel { background: #fff; border-radius: 12px; box-shadow: var(--shadow); padding: 1.4rem; margin-bottom: 1.2rem; }
    .bar-row { display: grid; grid-template-columns: 90px 1fr 90px; align-items: center; gap: 0.8rem; margin: 0.6rem 0; }
    .bar-track { background: var(--tint); border-radius: 8px; height: 22px; overflow: hidden; }
    .bar-fill { height: 100%; background: linear-gradient(90deg, var(--accent), var(--primary)); border-radius: 8px; }
    table.rep { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
    table.rep th { background: var(--primary); color: #fff; text-align: left; padding: 10px; }
    table.rep td { padding: 10px; border-bottom: 1px solid #e7eeee; }
    .note { color: var(--muted); font-size: 0.85rem; margin-top: 0.6rem; }
    .alert-box { background: #fef3c7; color: #92400e; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
    @media (max-width: 768px) { .kpis { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<header class="site-header">
  <div class="container header-inner">
    <a class="logo" href="index.php">
      <img class="logo-img" src="../images/wum-logo.jpg" alt="Women University Multan logo">
      <span>CampusTransit WUM</span>
    </a>
    <nav class="main-nav">
      <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="schedule.php">Bus Tracking</a></li>
        <li><a class="active" href="analytics.php">Reports</a></li>
        <li><a href="contact.php">Contact Us</a></li>
        <li><a href="../admin_status.php">Bus Status</a></li>
        <li><a href="../logout.php">Logout</a></li>
      </ul>
    </nav>
  </div>
</header>

<div class="rep-hero">
  <div class="container">
    <h1>Transport Reports</h1>
    <p>Fuel and maintenance overview from the database.</p>
  </div>
</div>

<section class="rep-section tint">
  <div class="container">
    <?php if ($db_error !== ''): ?>
      <div class="alert-box"><?= h($db_error) ?></div>
    <?php endif; ?>

    <div class="kpis">
      <div class="kpi">
        <div>Total Active Buses</div>
        <p class="kpi-value"><?= (int)$active_buses ?></p>
      </div>
      <div class="kpi">
        <div>Cumulative Fuel Used</div>
        <p class="kpi-value"><?= h(number_format($total_fuel, 2)) ?> L</p>
      </div>
      <div class="kpi">
        <div>Average Maintenance Expense</div>
        <p class="kpi-value">
          <?= $mean_expense !== null ? 'Rs ' . h(number_format($mean_expense, 2)) : 'No records' ?>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="rep-section">
  <div class="container">

    <div class="panel">
      <h2>Fuel Used by Route</h2>
      <?php if (empty($route_fuel)): ?>
        <p class="note">No fuel records are linked to a route yet.</p>
      <?php else: ?>
        <?php foreach ($route_fuel as $rf):
          $pct = $max_litres > 0 ? round(((float)$rf['litres'] / $max_litres) * 100, 1) : 0; ?>
          <div class="bar-row">
            <div>Route <?= h($rf['route_no']) ?></div>
            <div class="bar-track"><div class="bar-fill" style="width: <?= h($pct) ?>%"></div></div>
            <div><?= h(number_format((float)$rf['litres'], 2)) ?> L</div>
          </div>
        <?php endforeach; ?>
        <p class="note">Bar widths are proportional. The highest route is 100%.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Fuel Report per Bus</h2>
      <?php if (empty($bus_fuel)): ?>
        <p class="note">No buses found.</p>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="rep">
            <thead>
              <tr>
                <th>Bus</th>
                <th>Distance (km, one way)</th>
                <th>Mileage (km/L)</th>
                <th>Fuel needed per day (L)</th>
                <th>Fuel bought (L)</th>
                <th>Total cost (Rs)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bus_fuel as $bf):
                $dist = (float)($bf['distance_km'] ?? 0);
                $mil  = (float)$bf['mileage_km_per_l'];
                $per_day = ($dist > 0 && $mil > 0) ? ($dist * 2) / $mil : 0; ?>
                <tr>
                  <td><?= h($bf['bus_number']) ?></td>
                  <td><?= h($bf['distance_km'] ?? '—') ?></td>
                  <td><?= h($bf['mileage_km_per_l']) ?></td>
                  <td><?= h(number_format($per_day, 2)) ?></td>
                  <td><?= h(number_format((float)$bf['litres_bought'], 2)) ?></td>
                  <td>Rs <?= h(number_format((float)$bf['total_cost'], 2)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="note">Fuel needed per day = (distance x 2) / mileage. Fuel bought comes from the Expenses entries.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h2>Audit Checklist</h2>
      <ul class="checks">
        <?php foreach ($policy as $item): ?>
          <li><?= h($item) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="note">These are recommended administrative checks, not automatically verified database records.</p>
    </div>

  </div>
</section>

<footer class="site-footer">
  <div class="container footer-inner">
    <div>
      <strong>CampusTransit WUM</strong>
      <p class="footer-small">Educational transport planning prototype.</p>
    </div>
    <p class="footer-small">© 2026 CampusTransit WUM. For educational use only.</p>
  </div>
</footer>

</body>
</html>