<?php
declare(strict_types=1);

require __DIR__ . '/admin_common.php';

$message = '';

if (isset($_POST['add_route'])) {
    if (!csrf_ok()) {
        $message = flash('error', 'Session expired. Please try again.');
    } else {
        $route_no    = (int)($_POST['route_no'] ?? 0);
        $route_desc  = trim((string)($_POST['route_description'] ?? ''));
        $distance    = filter_var($_POST['distance_km'] ?? '', FILTER_VALIDATE_FLOAT);
        $students    = filter_var($_POST['students_estimate'] ?? '', FILTER_VALIDATE_INT);
        $bus_number  = trim((string)($_POST['bus_number'] ?? ''));
        $seats       = filter_var($_POST['seats'] ?? '', FILTER_VALIDATE_INT);
        $mileage     = filter_var($_POST['mileage_km_per_l'] ?? '', FILTER_VALIDATE_FLOAT);
        $driver_name = trim((string)($_POST['driver_name'] ?? ''));
        $phone       = trim((string)($_POST['phone_number'] ?? ''));

        if ($route_no < 1 || $route_desc === '' || $bus_number === '' || $driver_name === ''
            || $distance === false || $distance < 0 || $distance > 1000
            || $students === false || $students < 0 || $students > 2000
            || $seats === false || $seats < 1 || $seats > 100
            || $mileage === false || $mileage < 0.5 || $mileage > 50
            || !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            $message = flash('error', 'Please fill every field correctly.');
        } else {
            $db = db();
            try {
                $db->begin_transaction();

                $stmt = $db->prepare("SELECT route_id FROM Routes WHERE route_no = ? LIMIT 1");
                $stmt->bind_param('i', $route_no);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($exists) {
                    $db->rollback();
                    $message = flash('error', 'Route ' . $route_no . ' already exists.');
                } else {
                    $stmt = $db->prepare("SELECT bus_id FROM Buses WHERE bus_number = ? LIMIT 1");
                    $stmt->bind_param('s', $bus_number);
                    $stmt->execute();
                    $bus = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($bus) {
                        $bus_id = (int)$bus['bus_id'];
                        $stmt = $db->prepare("UPDATE Buses SET seats = ?, mileage_km_per_l = ? WHERE bus_id = ?");
                        $stmt->bind_param('idi', $seats, $mileage, $bus_id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $stmt = $db->prepare("INSERT INTO Buses (bus_number, seats, mileage_km_per_l) VALUES (?, ?, ?)");
                        $stmt->bind_param('sid', $bus_number, $seats, $mileage);
                        $stmt->execute();
                        $bus_id = (int)$db->insert_id;
                        $stmt->close();
                    }

                    $distance = (float)$distance;
                    $stmt = $db->prepare(
                        "INSERT INTO Routes (route_no, route_description, distance_km, students_estimate)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param('isdi', $route_no, $route_desc, $distance, $students);
                    $stmt->execute();
                    $route_id = (int)$db->insert_id;
                    $stmt->close();

                    $stmt = $db->prepare("UPDATE Buses SET route_id = ? WHERE bus_id = ?");
                    $stmt->bind_param('ii', $route_id, $bus_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $db->prepare("INSERT INTO Drivers (driver_name, phone_number, bus_id) VALUES (?, ?, ?)");
                    $stmt->bind_param('ssi', $driver_name, $phone, $bus_id);
                    $stmt->execute();
                    $stmt->close();

                    $db->commit();
                    $message = flash('success', 'Route ' . $route_no . ' was added successfully.');
                }
            } catch (mysqli_sql_exception $e) {
                $db->rollback();
                error_log('admin.php error: ' . $e->getMessage());
                $message = flash('error', 'Could not save the route. Did you run setup_step1.sql?');
            }
        }
    }
}

admin_header('Add Route', 'admin.php');
?>
        <div class="box">
            <h2>Add New Route</h2>
            <?= $message ?>

            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="grid">
                    <div class="form-group">
                        <label for="route_no">Route Number</label>
                        <input type="number" id="route_no" name="route_no" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="route_description">Route Description / Areas</label>
                        <input type="text" id="route_description" name="route_description" required>
                    </div>
                    <div class="form-group">
                        <label for="distance_km">Distance, one way (km)</label>
                        <input type="number" id="distance_km" name="distance_km" min="0" max="1000" step="0.1" required>
                    </div>
                    <div class="form-group">
                        <label for="students_estimate">Estimated students on this route</label>
                        <input type="number" id="students_estimate" name="students_estimate" min="0" max="2000" value="0" required>
                    </div>
                    <div class="form-group">
                        <label for="bus_number">Bus Number</label>
                        <input type="text" id="bus_number" name="bus_number" placeholder="e.g. MNG-120" required>
                    </div>
                    <div class="form-group">
                        <label for="seats">Seats in the bus</label>
                        <input type="number" id="seats" name="seats" min="1" max="100" value="50" required>
                    </div>
                    <div class="form-group">
                        <label for="mileage_km_per_l">Mileage (km per litre)</label>
                        <input type="number" id="mileage_km_per_l" name="mileage_km_per_l" min="0.5" max="50" step="0.1" value="4" required>
                    </div>
                    <div class="form-group">
                        <label for="driver_name">Driver Name</label>
                        <input type="text" id="driver_name" name="driver_name" required>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Driver Contact Number</label>
                        <input type="text" id="phone_number" name="phone_number" required>
                    </div>
                </div>

                <button type="submit" name="add_route" class="btn">Save New Route</button>
            </form>
        </div>
<?php admin_footer(); ?>
