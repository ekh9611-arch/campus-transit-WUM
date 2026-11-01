<?php
declare(strict_types=1);

require __DIR__ . '/admin_common.php';

$message = '';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $message = flash('error', 'Session expired. Please try again.');
    } else {
        $route_id = (int)($_POST['route_id'] ?? 0);
        $stop_name = trim((string)($_POST['stop_name'] ?? ''));
        $stop_order = filter_var($_POST['stop_order'] ?? '', FILTER_VALIDATE_INT);
        $morning = (string)($_POST['morning_pickup_time'] ?? '');
        $afternoon = (string)($_POST['afternoon_drop_time'] ?? '');

        $valid_time = fn(string $t): bool => (bool)preg_match('/^\d{2}:\d{2}$/', $t);

        if ($route_id < 1
            || mb_strlen($stop_name) < 2 || mb_strlen($stop_name) > 100
            || $stop_order === false || $stop_order < 1 || $stop_order > 99
            || !$valid_time($morning) || !$valid_time($afternoon)) {
            $message = flash('error', 'Please fill every field correctly.');
        } else {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO Route_Stops (route_id, stop_name, morning_pickup_time, afternoon_drop_time, stop_order)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('isssi', $route_id, $stop_name, $morning, $afternoon, $stop_order);
                $stmt->execute();
                $stmt->close();
                $message = flash('success', 'Stop "' . $stop_name . '" was added to the route.');
            } catch (mysqli_sql_exception $e) {
                error_log('admin_stops error: ' . $e->getMessage());
                $message = flash('error', 'Could not save the stop. Please try again.');
            }
        }
    }
}

$routes = $db->query(
    "SELECT route_id, route_no, route_description FROM Routes ORDER BY route_no ASC"
)->fetch_all(MYSQLI_ASSOC);

admin_header('Add Stops', 'admin_stops.php');
?>
        <div class="box">
            <h2>Add Stops and Shift Timings</h2>
            <?= $message ?>

            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="grid">
                    <div class="form-group">
                        <label for="route_id">Route</label>
                        <select id="route_id" name="route_id" required>
                            <option value="">-- Select route --</option>
                            <?php foreach ($routes as $r): ?>
                                <option value="<?= (int)$r['route_id'] ?>">
                                    Route <?= h($r['route_no']) ?> - <?= h($r['route_description']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="stop_name">Stop name</label>
                        <input type="text" id="stop_name" name="stop_name" required maxlength="100" placeholder="e.g. Fazal Mill">
                    </div>
                    <div class="form-group">
                        <label for="stop_order">Stop order (1 = first stop)</label>
                        <input type="number" id="stop_order" name="stop_order" min="1" max="99" required>
                    </div>
                    <div class="form-group">
                        <label for="morning_pickup_time">Morning pickup time</label>
                        <input type="time" id="morning_pickup_time" name="morning_pickup_time" required>
                    </div>
                    <div class="form-group">
                        <label for="afternoon_drop_time">Afternoon drop time</label>
                        <input type="time" id="afternoon_drop_time" name="afternoon_drop_time" required>
                    </div>
                </div>

                <button type="submit" class="btn">Add Stop To Route</button>
            </form>
        </div>
<?php admin_footer(); ?>