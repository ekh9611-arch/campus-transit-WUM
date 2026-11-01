<?php
declare(strict_types=1);

require __DIR__ . '/admin_common.php';

const STANDING_ALLOWANCE = 20;   // 50 seats + 20 khare = 70 maximum

$allowed = ['active', 'maintenance', 'breakdown'];
$message = '';
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $message = flash('error', 'Session expired. Please try again.');

    // 1. Driver ki alert: confirm ya dismiss
    } elseif (isset($_POST['alert_action'])) {
        $alert_id = (int)($_POST['alert_id'] ?? 0);
        $action   = (string)$_POST['alert_action'];

        if ($alert_id < 1 || !in_array($action, ['confirm', 'dismiss'], true)) {
            $message = flash('error', 'Invalid alert action.');

        } elseif ($action === 'dismiss') {
            $stmt = $db->prepare("UPDATE bus_alerts SET status = 'dismissed' WHERE alert_id = ? AND status = 'pending'");
            $stmt->bind_param('i', $alert_id);
            $stmt->execute();
            $stmt->close();
            $message = flash('success', 'Alert dismissed.');

        } else {
            $stmt = $db->prepare("SELECT bus_id FROM bus_alerts WHERE alert_id = ? AND status = 'pending' LIMIT 1");
            $stmt->bind_param('i', $alert_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $message = flash('error', 'This alert is no longer pending.');
            } else {
                $bus_id = (int)$row['bus_id'];

                $stmt = $db->prepare("UPDATE buses SET status = 'breakdown' WHERE bus_id = ?");
                $stmt->bind_param('i', $bus_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $db->prepare("UPDATE bus_alerts SET status = 'confirmed' WHERE alert_id = ?");
                $stmt->bind_param('i', $alert_id);
                $stmt->execute();
                $stmt->close();

                $message = flash('success', 'Breakdown confirmed. The bus is now marked as breakdown on the map.');
            }
        }

    // 2. Bus ka status change
    } else {
        $bus_id = (int)($_POST['bus_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');

        if ($bus_id < 1 || !in_array($status, $allowed, true)) {
            $message = flash('error', 'Choose a bus and a valid status.');
        } else {
            $stmt = $db->prepare("UPDATE buses SET status = ? WHERE bus_id = ?");
            $stmt->bind_param('si', $status, $bus_id);
            $stmt->execute();
            $stmt->close();

            // Bus active ho gayi to us ke purane alerts band ho jayen
            if ($status === 'active') {
                $stmt = $db->prepare(
                    "UPDATE bus_alerts SET status = 'resolved'
                     WHERE bus_id = ? AND status IN ('pending','confirmed')"
                );
                $stmt->bind_param('i', $bus_id);
                $stmt->execute();
                $stmt->close();
            }

            $message = flash('success', 'Status updated. The map will show it within 5 seconds.');
        }
    }
}

// Pending driver reports
$alerts = $db->query(
    "SELECT a.alert_id, a.message, a.created_at, b.bus_number
     FROM bus_alerts a
     JOIN buses b ON b.bus_id = a.bus_id
     WHERE a.status = 'pending'
     ORDER BY a.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

// Saari buses, route ke students ke saath
$buses = $db->query(
    "SELECT b.bus_id, b.bus_number, b.status, b.seats,
            COALESCE(r.students_estimate, 0) AS students,
            r.route_no
     FROM buses b
     LEFT JOIN routes r ON r.route_id = b.route_id
     ORDER BY b.bus_number ASC"
)->fetch_all(MYSQLI_ASSOC);

// Replacement suggestion
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
            $free[$cid] -= $take;
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
            <h2>1. Driver breakdown reports</h2>
            <?php if (empty($alerts)): ?>
                <p class="hint">No pending reports.</p>
            <?php else: ?>
                <?php foreach ($alerts as $a): ?>
                    <div class="form-group" style="border-bottom:1px solid #eee; padding-bottom:12px;">
                        <strong>Bus <?= h($a['bus_number']) ?></strong>
                        <span class="hint">(<?= h($a['created_at']) ?>)</span>
                        <p><?= h($a['message']) ?></p>
                        <form method="POST" action="" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="alert_id" value="<?= (int)$a['alert_id'] ?>">
                            <button type="submit" name="alert_action" value="confirm" class="btn">Confirm breakdown</button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="alert_id" value="<?= (int)$a['alert_id'] ?>">
                            <button type="submit" name="alert_action" value="dismiss" class="btn" style="background:#6b7280;">Dismiss</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="box">
            <h2>2. Change bus status</h2>
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
            <h2>3. Replacement suggestion</h2>
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