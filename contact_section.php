<?php
declare(strict_types=1);

require_once __DIR__ . '/../admin_common.php';

const STANDING_ALLOWANCE = 20;   // 50 seats + 20 khare = 70 maximum

$allowed = ['active', 'maintenance', 'breakdown'];
$message = '';
$db = db();

// 1. Status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $message = flash('error', 'Session expired. Please try again.');
    } else {
        $bus_id = (int)($_POST['bus_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');

        if ($bus_id < 1 || !in_array($status, $allowed, true)) {
            $message = flash('error', 'Choose a bus and a valid status.');
        } else {
            $stmt = $db->prepare("UPDATE Buses SET status = ? WHERE bus_id = ?");
            $stmt->bind_param('si', $status, $bus_id);
            $stmt->execute();
            $stmt->close();
            $message = flash('success', 'Status updated. The map will show it within 5 seconds.');
        }
    }
}

// 2. Load buses with their route load
$buses = $db->query(
    "SELECT b.bus_id, b.bus_number, b.status, b.seats,
            COALESCE(r.students_estimate, 0) AS students,
            r.route_no
     FROM Buses b
     LEFT JOIN Routes r ON r.route_id = b.route_id
     ORDER BY b.bus_number ASC"
)->fetch_all(MYSQLI_ASSOC);

// 3. Replacement suggestion for each broken bus
//    Students of the broken bus go to active buses with free space.
//    Free space = (seats + standing allowance) - students on that bus's route.
$free = [];
$candidates = [];
foreach ($buses as $b) {
    $id = (int)$b['bus_id'];
    $free[$id] = (int)$b['seats'] + STANDING_ALLOWANCE - (int)$b['students'];
    if ($b['status'] === 'active') {
        $candidates[] = $b;
    }
}
usort($candidates, fn($x, $y) => $free[(int)$y['bus_id']] <=> $free[(int)$x['bus_id']]);

$plans = [];
foreach ($buses as $b) {
    if ($b['status'] !== 'breakdown') {
        continue;
    }
    $remaining = (int)$b['students'];
    $split = [];

    foreach ($candidates as $c) {
        if ($remaining <= 0) {
            break;
        }
        $cid = (int)$c['bus_id'];
        $take = min($remaining, max(0, $free[$cid]));
        if ($take > 0) {
            $split[] = [$c['bus_number'], $take];
            $free[$cid] -= $take;   // reduce space for the next broken bus too
            $remaining -= $take;
        }
    }

    $plans[] = [
        'bus'       => $b['bus_number'],
        'route'     => $b['route_no'],
        'students'  => (int)$b['students'],
        'split'     => $split,
        'remaining' => $remaining,
    ];
}

admin_header('Bus Status', 'admin_status.php');
?>
        <?= $message ?>

        <div class="box">
            <h2>1. Change bus status</h2>
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="grid">
                    <div class="form-group">
                        <label for="bus_id">Bus</label>
                        <select id="bus_id" name="bus_id" required>
                            <option value="">-- Select bus --</option>
                            <?php foreach ($buses as $b): ?>
                                <option value="<?= (int)$b['bus_id'] ?>">
                                    <?= h($b['bus_number']) ?> (<?= h($b['status']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">New status</label>
                        <select id="status" name="status" required>
                            <option value="active">Active (On Route)</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="breakdown">Breakdown</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn">Update Status</button>
            </form>
        </div>

        <?php if (!empty($plans)): ?>
        <div class="box">
            <h2>2. Replacement suggestion</h2>
            <p class="hint">Suggestion only. Confirm with the driver and the transport office before moving students.</p>
            <?php foreach ($plans as $p): ?>
                <div class="form-group">
                    <strong><?= h($p['bus']) ?></strong>
                    (Route <?= h($p['route']) ?>, <?= (int)$p['students'] ?> students)
                    <?php if (!empty($p['split'])): ?>
                        <ul>
                            <?php foreach ($p['split'] as [$num, $n]): ?>
                                <li><?= h($num) ?>: <?= (int)$n ?> students</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No active bus has free space.</p>
                    <?php endif; ?>
                    <?php if ($p['remaining'] > 0): ?>
                        <p><strong>Warning:</strong> <?= (int)$p['remaining'] ?> students cannot be placed. Arrange another bus.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
<?php admin_footer(); ?>