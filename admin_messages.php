<?php
declare(strict_types=1);

require __DIR__ . '/admin_common.php';

$db = db();

$messages = $db->query(
    "SELECT message_id, name, email, phone, subject, message, created_at
     FROM contact_messages
     ORDER BY created_at DESC
     LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);

admin_header('Contact Messages', 'admin_messages.php');
?>
<div class="box">
    <h2>Contact messages</h2>

    <?php if (empty($messages)): ?>
        <p class="hint">No messages yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Topic</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr>
                        <td><?= h($m['created_at']) ?></td>
                        <td><?= h($m['name']) ?></td>
                        <td>
                            <?= h($m['email']) ?>
                            <?php if (!empty($m['phone'])): ?>
                                <br><?= h($m['phone']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= h($m['subject']) ?></td>
                        <td><?= nl2br(h($m['message'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php admin_footer(); ?>