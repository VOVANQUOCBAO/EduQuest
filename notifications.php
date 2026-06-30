<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_notifications_tables();
ensure_school_structure_tables();

$user = current_user();
$canSend = in_array($user['role'], ['admin', 'teacher'], true);
$page_title = 'Thong bao';
if (!$canSend) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canSend) {
    if (isset($_POST['delete_notification'])) {
        $notificationId = (int)$_POST['delete_notification'];
        $deleteSql = $user['role'] === 'admin'
            ? 'DELETE FROM notifications WHERE id=?'
            : 'DELETE FROM notifications WHERE id=? AND sender_id=?';
        $deleteParams = $user['role'] === 'admin' ? [$notificationId] : [$notificationId, $user['id']];
        db()->prepare($deleteSql)->execute($deleteParams);
        log_activity('delete', 'notification', $notificationId, 'Đã xóa thông báo #' . $notificationId);
        flash('Đã xóa thông báo');
        redirect('notifications.php');
    }

    $title = post('title');
    $content = trim($_POST['content'] ?? '');
    $classIds = array_map('intval', $_POST['class_recipients'] ?? []);
    $teacherIds = $user['role'] === 'admin' ? array_map('intval', $_POST['teacher_recipients'] ?? []) : [];
    if ($title === '' || $content === '' || (!$classIds && !$teacherIds)) {
        flash('Vui long nhap tieu de, noi dung va chon nguoi nhan.', 'error');
        redirect('notifications.php');
    }

    $st = db()->prepare('INSERT INTO notifications(title,content,sender_id) VALUES(?,?,?)');
    $st->execute([$title, $content, $user['id']]);
    $notificationId = (int)db()->lastInsertId();

    $studentStmt = db()->prepare('SELECT id FROM users WHERE role="student" AND status="active" AND class_id=?');
    $insertRecipient = db()->prepare('INSERT IGNORE INTO notification_recipients(notification_id,user_id) VALUES(?,?)');
    $sent = 0;
    foreach ($classIds as $classId) {
        if ($classId <= 0) continue;
        $studentStmt->execute([$classId]);
        foreach ($studentStmt->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
            $insertRecipient->execute([$notificationId, (int)$studentId]);
            $sent += $insertRecipient->rowCount();
        }
    }
    if ($teacherIds) {
        $teacherStmt = db()->prepare('SELECT id FROM users WHERE role="teacher" AND status="active" AND id=?');
        foreach ($teacherIds as $teacherId) {
            if ($teacherId <= 0) continue;
            $teacherStmt->execute([$teacherId]);
            $validTeacherId = (int)$teacherStmt->fetchColumn();
            if ($validTeacherId <= 0) continue;
            $insertRecipient->execute([$notificationId, $validTeacherId]);
            $sent += $insertRecipient->rowCount();
        }
    }
    if ($sent === 0) {
        db()->prepare('DELETE FROM notifications WHERE id=?')->execute([$notificationId]);
        flash('Lop da chon chua co hoc sinh active nao.', 'error');
    } else {
        log_activity('create', 'notification', $notificationId, 'Đã gửi thông báo: ' . $title . ' (' . $sent . ' người nhận)');
        flash('Da gui thong bao cho ' . $sent . ' hoc sinh.');
    }
    redirect('notifications.php');
}

$viewId = (int)($_GET['view'] ?? 0);
$detail = null;
if ($viewId > 0) {
    $st = db()->prepare('SELECT n.*,u.name sender_name FROM notifications n LEFT JOIN users u ON u.id=n.sender_id JOIN notification_recipients nr ON nr.notification_id=n.id WHERE n.id=? AND nr.user_id=?');
    $st->execute([$viewId, $user['id']]);
    $detail = $st->fetch();
    if ($detail) {
        db()->prepare('UPDATE notification_recipients SET read_at=COALESCE(read_at,NOW()) WHERE notification_id=? AND user_id=?')->execute([$viewId, $user['id']]);
    } elseif ($canSend) {
        $st = db()->prepare('SELECT n.*,u.name sender_name FROM notifications n LEFT JOIN users u ON u.id=n.sender_id WHERE n.id=? AND n.sender_id=?');
        $st->execute([$viewId, $user['id']]);
        $detail = $st->fetch();
    }
}

$classes = $canSend ? fetch_school_classes() : [];
$teachers = [];
if ($user['role'] === 'admin') {
    $teachers = db()->query('SELECT id,name,email FROM users WHERE role="teacher" AND status="active" ORDER BY name')->fetchAll();
}
$st = db()->prepare('SELECT n.*,u.name sender_name,nr.read_at
    FROM notification_recipients nr
    JOIN notifications n ON n.id=nr.notification_id
    LEFT JOIN users u ON u.id=n.sender_id
    WHERE nr.user_id=?
    ORDER BY n.created_at DESC');
$st->execute([$user['id']]);
$received = $st->fetchAll();

$sentRows = [];
if ($canSend) {
    if ($user['role'] === 'admin') {
        $st = db()->prepare('SELECT n.*,u.name sender_name,COUNT(nr.id) recipient_count FROM notifications n LEFT JOIN users u ON u.id=n.sender_id LEFT JOIN notification_recipients nr ON nr.notification_id=n.id GROUP BY n.id ORDER BY n.created_at DESC');
        $st->execute();
    } else {
        $st = db()->prepare('SELECT n.*,u.name sender_name,COUNT(nr.id) recipient_count FROM notifications n LEFT JOIN users u ON u.id=n.sender_id LEFT JOIN notification_recipients nr ON nr.notification_id=n.id WHERE n.sender_id=? GROUP BY n.id ORDER BY n.created_at DESC');
        $st->execute([$user['id']]);
    }
    $sentRows = $st->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Thông báo</h1>
    <p>Soạn, gửi và xem chi tiết thông báo trong hệ thống.</p>
  </div>
</div>

<?php if ($canSend): ?>
<div class="card">
  <h2>So&#7841;n th&#244;ng b&#225;o</h2>
  <form method="post" class="notification-compose split-compose">
    <div class="notification-compose-main">
      <div><label>Ti&#234;u &#273;&#7873;</label><input name="title" required placeholder="V&#237; d&#7909;: L&#7883;ch ki&#7875;m tra tu&#7847;n n&#224;y"></div>
      <div><label>N&#7897;i dung th&#244;ng b&#225;o</label><textarea name="content" required placeholder="Nh&#7853;p n&#7897;i dung th&#244;ng b&#225;o"></textarea></div>
    </div>
    <div class="notification-compose-classes">
      <?php if ($user['role'] === 'admin'): ?>
      <label>G&#7917;i cho gi&#225;o vi&#234;n</label>
      <div class="recipient-list notification-teacher-list">
        <?php foreach ($teachers as $teacher): ?>
          <label class="recipient-row">
            <input type="checkbox" name="teacher_recipients[]" value="<?= e($teacher['id']) ?>">
            <span><strong><?= e($teacher['name']) ?></strong><small><?= e($teacher['email']) ?></small></span>
          </label>
        <?php endforeach; ?>
        <?php if (!$teachers): ?><p class="muted">Ch&#432;a c&#243; gi&#225;o vi&#234;n active.</p><?php endif; ?>
      </div>
      <?php endif; ?>
      <label>G&#7917;i cho c&#225;c l&#7899;p</label>
      <div class="recipient-list">
        <?php foreach ($classes as $class): ?>
          <label class="recipient-row">
            <input type="checkbox" name="class_recipients[]" value="<?= e($class['id']) ?>">
            <span><strong><?= e($class['school_name'] . ' - ' . $class['grade_name'] . ' - ' . $class['name']) ?></strong><small><?= e($class['student_count']) ?> h&#7885;c sinh</small></span>
          </label>
        <?php endforeach; ?>
        <?php if (!$classes): ?><p class="muted">Ch&#432;a c&#243; l&#7899;p n&#224;o &#273;&#7875; g&#7917;i.</p><?php endif; ?>
      </div>
      <button class="btn primary notification-submit"><span class="material-symbols-outlined">notifications</span> G&#7917;i th&#244;ng b&#225;o</button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($detail): ?>
<div class="card" style="margin-top:24px">
  <h2><?= e($detail['title']) ?></h2>
  <p class="muted">Người gửi: <?= e($detail['sender_name'] ?? 'Hệ thống') ?> · <?= e($detail['created_at']) ?></p>
  <p><?= nl2br(e($detail['content'])) ?></p>
</div>
<?php endif; ?>

<div class="grid grid-2" style="margin-top:24px">
  <div class="card">
    <h2>Thông báo nhận được</h2>
    <table class="table">
      <tr><th>Tiêu đề</th><th>Người gửi</th><th>Ngày gửi</th><th></th></tr>
      <?php foreach ($received as $n): ?>
        <tr>
          <td><strong><?= e($n['title']) ?></strong><?= $n['read_at'] ? '' : ' <span class="badge hard">Mới</span>' ?></td>
          <td><?= e($n['sender_name'] ?? '') ?></td>
          <td><?= e($n['created_at']) ?></td>
          <td><a class="btn ghost" href="?view=<?= e($n['id']) ?>">Xem</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$received): ?><tr><td colspan="4" class="muted">Chưa có thông báo.</td></tr><?php endif; ?>
    </table>
  </div>
  <?php if ($canSend): ?>
  <div class="card">
    <h2>Thông báo đã gửi</h2>
    <table class="table">
      <tr><th>Tiêu đề</th><th>Người nhận</th><th>Ngày gửi</th><th></th></tr>
      <?php foreach ($sentRows as $n): ?>
        <tr>
          <td><?= e($n['title']) ?><br><span class="muted"><?= e($n['sender_name'] ?? '') ?></span></td>
          <td><?= e($n['recipient_count']) ?> người nhận</td>
          <td><?= e($n['created_at']) ?></td>
          <td class="actions">
            <a class="btn ghost" href="?view=<?= e($n['id']) ?>">Xem</a>
            <form method="post">
              <button class="btn danger" name="delete_notification" value="<?= e($n['id']) ?>" data-confirm="Xóa thông báo này?">Xóa</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$sentRows): ?><tr><td colspan="4" class="muted">Chưa gửi thông báo nào.</td></tr><?php endif; ?>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
