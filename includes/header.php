<?php
require_once __DIR__ . '/functions.php';
$user = current_user();
$page_title = $page_title ?? 'EduQuest';
$roleNames = ['admin' => 'Quản trị viên', 'teacher' => 'Giáo viên', 'student' => 'Học sinh'];
$unreadNotifications = $user ? unread_notification_count((int)$user['id']) : 0;
$headerNotifications = $user ? user_notifications((int)$user['id'], 8) : [];
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?></title>
  <link rel="icon" href="favicon.ico?v=20260630">
  <link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32.png?v=20260630">
  <link rel="apple-touch-icon" sizes="180x180" href="img/apple-touch-icon.png?v=20260630">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Material+Symbols+Outlined" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=20260701-inline-formula-zoom">
</head>
<body>
<?php if ($user): ?>
<aside class="sidebar">
  <div class="brand">
    <img class="brand-logo" src="img/logoeduquest.png" alt="EduQuest">
    <div>
      <strong>EduQuest</strong>
      <small><?= e($roleNames[$user['role']] ?? 'School Portal') ?></small>
    </div>
  </div>
  <?php include __DIR__ . '/sidebar.php'; ?>
</aside>
<main class="main">
<header class="topbar">
  <button class="icon-btn sidebar-toggle" data-sidebar-toggle type="button" title="Thu/mở menu"><span class="material-symbols-outlined">menu</span></button>
  <div class="top-actions">
    <div class="notification-shell" data-notification-shell>
      <button class="icon-btn" type="button" title="Th&#244;ng b&#225;o" data-notification-toggle>
        <span class="material-symbols-outlined">notifications</span>
        <?php if ($unreadNotifications > 0): ?><i data-notification-badge><?= e($unreadNotifications > 99 ? '99+' : $unreadNotifications) ?></i><?php endif; ?>
      </button>
      <div class="notification-popover" data-notification-popover>
        <div class="notification-popover-head"><strong>Th&#244;ng b&#225;o</strong><span><?= e(count($headerNotifications)) ?></span></div>
        <div class="notification-popover-list">
          <?php foreach ($headerNotifications as $n): ?>
            <button type="button" class="notification-popover-item <?= $n['read_at'] ? '' : 'unread' ?>" data-notification-item data-id="<?= e($n['id']) ?>" data-title="<?= e($n['title']) ?>" data-content="<?= e($n['content']) ?>" data-sender="<?= e($n['sender_name'] ?? 'He thong') ?>" data-created="<?= e($n['created_at']) ?>">
              <span class="notification-popover-icon"><span class="material-symbols-outlined">notifications</span></span>
              <span><strong><?= e($n['title']) ?></strong><small><?= e($n['sender_name'] ?? 'He thong') ?> &middot; <?= e($n['created_at']) ?></small></span>
            </button>
          <?php endforeach; ?>
          <?php if (!$headerNotifications): ?><p class="muted notification-empty">Ch&#432;a c&#243; th&#244;ng b&#225;o.</p><?php endif; ?>
        </div>
      </div>
    </div>
    <button class="icon-btn" type="button" title="Dark mode" data-dark-toggle><span class="material-symbols-outlined">dark_mode</span></button>
    <button class="icon-btn" type="button" title="Trợ giúp"><span class="material-symbols-outlined">help</span></button>
    <div class="userbox">
      <div class="user-meta">
        <strong><?= e($user['name']) ?></strong>
        <small><?= e($roleNames[$user['role']] ?? $user['role']) ?></small>
      </div>
      <div class="avatar"><?= e(mb_substr($user['name'], 0, 1)) ?></div>
      <a href="logout.php">Đăng xuất</a>
    </div>
  </div>
</header>
<div class="notification-modal" data-notification-modal aria-hidden="true">
  <div class="notification-modal-backdrop" data-notification-close></div>
  <article class="notification-modal-card">
    <button class="icon-btn notification-modal-close" type="button" data-notification-close title="&#272;&#243;ng"><span class="material-symbols-outlined">close</span></button>
    <h2 data-notification-modal-title></h2>
    <p class="muted" data-notification-modal-meta></p>
    <div class="notification-modal-content" data-notification-modal-content></div>
  </article>
</div>
<section class="content">
<?php endif; ?>
<?php if ($f = flash()): ?><div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endif; ?>
