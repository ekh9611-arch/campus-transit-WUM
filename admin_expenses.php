<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_common.php';

$fuel_message  = '';
$maint_message = '';
$db = db();

$buses = $db->query("SELECT bus_id, bus_number FROM buses ORDER BY bus_number ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $fuel_message  = flash('error', 'Session expired. Please try again.');
        $maint_message = $fuel_message;

    } elseif (isset($_POST['submit_fuel'])) {
        $bus_id   = (int)($_POST['bus_id'] ?? 0);
        $liters   = filter_var($_POST['liters'] ?? '', FILTER_VALIDATE_FLOAT);
        $cost     = filter_var($_POST['cost'] ?? '', FILTER_VALIDATE_FLOAT);
        $log_date = (string)($_POST['log_date'] ?? '');

        try {
            // Bus ka route pehle nikalna
            $stmt = $db->prepare("SELECT route_id FROM buses WHERE bus_id = ?");
            $stmt->bind_param('i', $bus_id);
            $stmt->execute();
            $bus = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($bus_id < 1 || $liters === false || $liters <= 0 || $cost === false || $cost <= 0 || $log_date === '') {
                $fuel_message = flash('error', 'Please fill all fuel details correctly.');
            } elseif (!$bus || empty($bus['route_id'])) {
                $fuel_message = flash('error', 'This bus has no route. Assign a route in Fleet Settings first.');
            } else {
                $route_id = (int)$bus['route_id'];
                $stmt = $db->prepare(
                    "INSERT INTO fuel_logs (bus_id, route_id, litres, cost, log_date) VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('iidds', $bus_id, $route_id, $liters, $cost, $log_date);
                $stmt->execute();
                $stmt->close();
                $fuel_message = flash('success', 'Fuel log saved.');
            }
        } catch (mysqli_sql_exception $e) {
            error_log('Fuel log error: ' . $e->getMessage());
            $fuel_message = flash('error', 'Could not save fuel log. Did you run the SQL in step 1?');
        }

    } elseif (isset($_POST['submit_maint'])) {
        $bus_id      = (int)($_POST['bus_id'] ?? 0);
        $description = trim((string)($_POST['description'] ?? ''));
        $cost        = filter_var($_POST['cost'] ?? '', FILTER_VALIDATE_FLOAT);
        $date        = (string)($_POST['expense_date'] ?? '');

        if ($bus_id < 1 || $description === '' || $cost === false || $cost <= 0 || $date === '') {
            $maint_message = flash('error', 'Please fill all maintenance details correctly.');
        } else {
            try {
                $stmt = $db->prepare(
                    "INSERT INTO maintenance_expenses (bus_id, amount, expense_date, description) VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param('idss', $bus_id, $cost, $date, $description);
                $stmt->execute();
                $stmt->close();
                $maint_message = flash('success', 'Maintenance record saved.');
            } catch (mysqli_sql_exception $e) {
                error_log('Maintenance error: ' . $e->getMessage());
                $maint_message = flash('error', 'Could not save maintenance record.');
            }
        }
    }
}

admin_header('Manage Expenses', 'admin_expenses.php');
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">

    <div class="box">
        <h2>⛽ Fuel Consumption Log</h2>
        <?= $fuel_message ?>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="fuel_bus">Select Bus</label>
                <select id="fuel_bus" name="bus_id" required>
                    <option value="">-- Choose Vehicle --</option>
                    <?php foreach ($buses as $b): ?>
                        <option value="<?= (int)$b['bus_id'] ?>">Bus <?= h($b['bus_number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="liters">Quantity (Litres)</label>
                <input type="number" step="0.01" id="liters" name="liters" required>
            </div>
            <div class="form-group">
                <label for="fuel_cost">Total Cost (PKR)</label>
                <input type="number" step="0.01" id="fuel_cost" name="cost" required>
            </div>
            <div class="form-group">
                <label for="log_date">Refuel Date</label>
                <input type="date" id="log_date" name="log_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <button type="submit" name="submit_fuel" class="btn">Save Fuel Log</button>
        </form>
    </div>

    <div class="box">
        <h2>🛠️ Maintenance and Repairs</h2>
        <?= $maint_message ?>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="maint_bus">Select Bus</label>
                <select id="maint_bus" name="bus_id" required>
                    <option value="">-- Choose Vehicle --</option>
                    <?php foreach ($buses as $b): ?>
                        <option value="<?= (int)$b['bus_id'] ?>">Bus <?= h($b['bus_number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="description">Work Description</label>
                <input type="text" id="description" name="description" placeholder="e.g. Engine oil change" required>
            </div>
            <div class="form-group">
                <label for="maint_cost">Repair Cost (PKR)</label>
                <input type="number" step="0.01" id="maint_cost" name="cost" required>
            </div>
            <div class="form-group">
                <label for="expense_date">Service Date</label>
                <input type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <button type="submit" name="submit_maint" class="btn">Save Maintenance Log</button>
        </form>
    </div>

</div>

<?php admin_footer(); ?>