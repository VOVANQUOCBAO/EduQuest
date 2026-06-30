<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_notifications_tables();

header('Content-Type: application/json; charset=utf-8');
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    db()->prepare('UPDATE notification_recipients SET read_at=COALESCE(read_at,NOW()) WHERE notification_id=? AND user_id=?')
        ->execute([$id, current_user()['id']]);
}
echo json_encode(['ok' => true, 'unread' => unread_notification_count((int)current_user()['id'])]);
