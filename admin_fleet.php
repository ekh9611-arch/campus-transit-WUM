<?php
declare(strict_types=1);

require __DIR__ . '/admin_common.php';

$message = '';
$db = db();

// Load current data
$routes = $db->query(
    "SELECT route_id, route_no, route_description, distance_km, students_estimate
     FROM Routes ORDER BY route_no ASC"
)->fetch_all(MYSQLI_ASSOC);

$buses = $db->query(
    "SELECT bus_id, bus_number, route_id, seats, mileage_km_per_l
     FROM Buses ORDER BY bus_number ASC"
)->fetch_all(MYSQLI_ASSOC);

$valid_route_ids = array_map('intval', array_column($routes, 'route_id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_ok()) {
        $message = flash('error', 'Session expired. Please try again.');
    } elseif ($action === 'routes') {
        $distances = (array)($_POST['distance'] ?? []);
        $students  = (array)($_POST['students'] ?? []);
        $bad = false;
        $rows = [];

        foreach ($routes as $r) {
            $id = (int)$r['route_id'];
            $d  = filter_var($distances[$id] ?? '', FILTER_VALIDATE_FLOAT);
            $s  = filter_var($students[$id] ?? '', FILTER_VALIDATE_INT);
            if ($d === false || $d < 0 || $d > 1000 || $s === false || $s < 0 || $s > 2000) {
                $bad = true;
                break;
            }
            $rows[] = [$id, (float)$d, (int)$s];
        }

        if ($bad) {
            $message = flash('error', 'Distance must be 0 to 1000 km and students 0 to 2000.');
        } else {
            $stmt = $db->prepare("UPDATE Routes SET distance_km = ?, students_estimate = ? WHERE route_id = ?");
            foreach ($rows as [$id, $d, $s]) {
                $stmt->bind_param('dii', $d, $s, $id);
                $stmt->execute();
            }
            $stmt->close();
            $message = flash('success', 'Routes saved.');
        }
    } elseif ($action === 'buses') {
        $route_ids = (array)($_POST['bus_route'] ?? []);
        $seats_in  = (array)($_POST['bus_seats'] ?? []);
        $mile_in   = (array)($_POST['bus_mileage'] ?? []);
        $bad = false;
        $rows = [];

        foreach ($buses as $b) {
            $id = (int)$b['bus_id'];
            $rid_raw = (string)($route_ids[$id] ?? '');
            $rid = $rid_raw === '' ? null : filter_var($rid_raw, FILTER_VALIDATE_INT);
            $st  = filter_var($seats_in[$id] ?? '', FILTER_VALIDATE_INT);
            $mi  = filter_var($mile_in[$id] ?? '', FILTER_VALIDATE_FLOAT);

            if (($rid_raw !== '' && ($rid === false || !in_array($rid, $valid_route_ids, true)))
                || $st === false || $st < 1 || $st > 100
                || $mi === false || $mi < 0.5 || $mi > 50) {
                $bad = true;
                break;
            }
            $rows[] = [$id, $rid, (int)$st, (float)$mi];
        }

        if ($bad) {
            $message = flash('error', 'Seats must be 1 to 100 and mileage 0.5 to 50 km per litre.');
        } else {
            $stmt = $db->prepare("UPDATE Buses SET route_id = ?, seats = ?, mileage_km_per_l = ? WHERE bus_id = ?");
            foreach ($rows as [$id, $rid, $st, $mi]) {
                $stmt->bind_param('iidi', $rid, $st, $mi, $id);
                $stmt->execute();
            }
            $stmt->close();
            $message = flash('success', 'Buses saved.');
        }
    } elseif ($action === 'rate') {
        $date  = (string)($_POST['rate_date'] ?? '');
        $price = filter_var($_POST['price_per_litre'] ?? '', FILTER_VALIDATE_FLOAT);
        $dt    = DateTime::createFromFormat('Y-m-d', $date);

        if (!$dt || $dt->format('Y-m-d') !== $date || $price === false || $price < 1 || $price > 5000) {
            $message = flash('error', 'Enter a valid date and a price between 1 and 5000.');
        } else {
            $price = (float)$price;
            $stmt = $db->prepare(
                "INSERT INTO Fuel_Rates (rate_date, price_per_litre) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE price_per_litre = VALUES(price_per_litre)"
            );
            $stmt->bind_param('sd', $date, $price);
            $stmt->execute();
            $stmt->close();
            $message = flash('success', 'Fuel rate saved.');
        }
    }

    // Reload so the tables show the saved values
    $routes = $db->query(
        "SELECT route_id, route_no, route_description, distance_km, students_estimate
         FROM Routes ORDER BY route_no ASC"
    )->fetch_all(MYSQLI_ASSOC);
    $buses = $db->query(
        "SELECT bus_id, bus_number, route_id, seats, mileage_km_per_l
         FROM Buses ORDER BY bus_number ASC"
    )->fetch_all(MYSQLI_ASSOC);
}

$recent_rates = $db->query(
    "SELECT rate_date, price_per_litre FROM Fuel_Rates ORDER BY rate_date DESC LIMIT 7"
)->fetch_all(MYSQLI_ASSOC);

admin_header('Fleet Settings', 'admin_fleet.php');
?>
        <?= $message ?>

        <div class="box">
            <h2>1. Routes: distance and students</h2>
            <p class="hint">Distance is one way. Fuel is worked out for going and coming back (distance x 2).</p>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="routes">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Route</th><th>Areas</th><th>Distance one way (km)</th><th>Estimated students</th></tr></thead>
                        <tbody>
                        <?php foreach ($routes as $r): ?>
                            <tr>
                                <td>Route <?= h($r['route_no']) ?></td>
                                <td><?= h($r['route_description']) ?></td>
                                <td><input type="number" name="distance[<?= (int)$r['route_id'] ?>]" min="0" max="1000" step="0.1" value="<?= h($r['distance_km']) ?>"></td>
                                <td><input type="number" name="students[<?= (int)$r['route_id'] ?>]" min="0" max="2000" value="<?= h($r['students_estimate']) ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn">Save Routes</button>
            </form>
        </div>

        <div class="box">
            <h2>2. Buses: route, seats and mileage</h2>
            <p class="hint">Seats are 50 by default. Mileage means how many km the bus runs on one litre.</p>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="buses">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Bus</th><th>Route</th><th>Seats</th><th>Mileage (km/l)</th></tr></thead>
                        <tbody>
                        <?php foreach ($buses as $b): ?>
                            <tr>
                                <td><?= h($b['bus_number']) ?></td>
                                <td>
                                    <select name="bus_route[<?= (int)$b['bus_id'] ?>]">
                                        <option value="">Not assigned</option>
                                        <?php foreach ($routes as $r): ?>
                                            <option value="<?= (int)$r['route_id'] ?>"<?= (int)$b['route_id'] === (int)$r['route_id'] ? ' selected' : '' ?>>Route <?= h($r['route_no']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="bus_seats[<?= (int)$b['bus_id'] ?>]" min="1" max="100" value="<?= h($b['seats']) ?>"></td>
                                <td><input type="number" name="bus_mileage[<?= (int)$b['bus_id'] ?>]" min="0.5" max="50" step="0.1" value="<?= h($b['mileage_km_per_l']) ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn">Save Buses</button>
            </form>
        </div>

        <div class="box">
            <h2>3. Fuel rate (Rs per litre)</h2>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rate">
                <div class="grid">
                    <div class="form-group">
                        <label for="rate_date">Date</label>
                        <input type="date" id="rate_date" name="rate_date" value="<?= h(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="price_per_litre">Price per litre (Rs)</label>
                        <input type="number" id="price_per_litre" name="price_per_litre" min="1" max="5000" step="0.01" required>
                    </div>
                </div>
                <button type="submit" class="btn">Save Fuel Rate</button>
            </form>

            <?php if (!empty($recent_rates)): ?>
                <div class="table-wrap" style="margin-top:16px;">
                    <table>
                        <thead><tr><th>Date</th><th>Rs per litre</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent_rates as $fr): ?>
                            <tr><td><?= h($fr['rate_date']) ?></td><td><?= h(number_format((float)$fr['price_per_litre'], 2)) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
<?php admin_footer(); ?>
