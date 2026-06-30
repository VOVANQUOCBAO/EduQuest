<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin']);
ensure_activity_logs_table();
$page_title = 'Admin Dashboard';

$counts = [];
foreach (['users','subjects','lessons','questions','exams','attempts'] as $t) {
    $counts[$t] = (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn();
}
$recentUsers = db()->query('SELECT id,name,email,role,status,created_at FROM users ORDER BY created_at DESC LIMIT 5')->fetchAll();
$recentExams = db()->query('SELECT e.*,s.name subject_name,COUNT(eq.id) total_questions FROM exams e JOIN subjects s ON s.id=e.subject_id LEFT JOIN exam_questions eq ON eq.exam_id=e.id GROUP BY e.id ORDER BY e.created_at DESC LIMIT 5')->fetchAll();
$weekActivity = activity_chart_data('week');
$monthActivity = activity_chart_data('month');
$recentActivities = recent_activity_logs(8);
$activityRoleNames = ['admin' => 'Admin', 'teacher' => 'Giáo viên', 'student' => 'Học sinh'];
$stats = [
    ['users','Người dùng','manage_accounts','blue','+12%'],
    ['questions','Câu hỏi','quiz','purple','+24%'],
    ['exams','Đề thi','assignment','rose','+8%'],
    ['attempts','Lượt thi','history_edu','indigo','+42%'],
    ['subjects','Môn học','auto_stories','teal','0%'],
    ['lessons','Bài học','menu_book','amber','+5%'],
];
include __DIR__ . '/includes/header.php';
?>
<div class="page-heading admin-heading">
  <div>
    <span class="eyebrow">Admin Control Center</span>
    <h1>Quản trị hệ thống EduQuest</h1>
    <p>Trang riêng cho admin để theo dõi dữ liệu, quản lý tài khoản, sao lưu và điều phối toàn bộ hệ thống thi trực tuyến.</p>
  </div>
  <div class="heading-actions">
    <a class="btn ghost" href="backups.php"><span class="material-symbols-outlined">backup</span> Sao lưu</a>
    <a class="btn primary" href="users.php"><span class="material-symbols-outlined">manage_accounts</span> Tài khoản</a>
    <a class="btn secondary" href="notifications.php"><span class="material-symbols-outlined">notifications</span> Thông báo</a>
  </div>
</div>

<div class="stats-grid">
  <?php foreach ($stats as $s): ?>
    <a class="dash-card stat-card" href="<?= $s[0] === 'users' ? 'users.php' : ($s[0] === 'exams' ? 'exams.php' : '#') ?>">
      <div class="stat-top">
        <span class="stat-icon <?= e($s[3]) ?>"><span class="material-symbols-outlined"><?= e($s[2]) ?></span></span>
        <span class="trend"><?= e($s[4]) ?> <span class="material-symbols-outlined">trending_up</span></span>
      </div>
      <p><?= e($s[1]) ?></p>
      <h3><?= e(number_format($counts[$s[0]])) ?></h3>
    </a>
  <?php endforeach; ?>
</div>

<div class="admin-shortcuts">
  <a class="dash-card shortcut-card" href="users.php"><span class="material-symbols-outlined">manage_accounts</span><strong>Quản lý tài khoản</strong><small>Thêm, sửa, khóa và phân quyền người dùng</small></a>
  <a class="dash-card shortcut-card" href="exams.php"><span class="material-symbols-outlined">assignment</span><strong>Quản lý đề thi</strong><small>Công bố, đóng, xuất đề và đáp án</small></a>
  <a class="dash-card shortcut-card" href="backups.php"><span class="material-symbols-outlined">backup</span><strong>Sao lưu dữ liệu</strong><small>Xuất dữ liệu chính ra file JSON</small></a>
  <a class="dash-card shortcut-card" href="notifications.php"><span class="material-symbols-outlined">notifications</span><strong>Thông báo</strong><small>Soạn và theo dõi toàn bộ thông báo đã gửi</small></a>
</div>

<div class="dashboard-grid">
  <section class="dash-card chart-card">
    <div class="section-head">
      <h2>Hoạt động hệ thống</h2>
      <div class="segmented" data-chart-tabs><button type="button" class="active" data-chart-tab="week">Tuần này</button><button type="button" data-chart-tab="month">Tháng này</button></div>
    </div>
    <?php $weekMax = max(1, max($weekActivity['values'])); ?>
    <div class="chart-wrap" data-chart-panel="week">
      <?php foreach ($weekActivity['values'] as $i => $value): $height = $value > 0 ? max(8, round($value / $weekMax * 100)) : 0; ?>
        <div class="chart-col">
          <div class="chart-bar" style="height:<?= e($height) ?>%" title="<?= e($value) ?> hoạt động"></div>
          <span><?= e($weekActivity['labels'][$i]) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php $monthMax = max(1, max($monthActivity['values'])); ?>
    <div class="chart-wrap month-chart" data-chart-panel="month" style="display:none">
      <?php foreach ($monthActivity['values'] as $i => $value): $height = $value > 0 ? max(8, round($value / $monthMax * 100)) : 0; ?>
        <div class="chart-col">
          <div class="chart-bar" style="height:<?= e($height) ?>%" title="<?= e($value) ?> hoạt động"></div>
          <span><?= e($monthActivity['labels'][$i]) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="dash-card activity-card">
    <div class="section-head"><h2>Lịch sử hoạt động</h2></div>
    <div class="activity-list">
      <?php foreach ($recentActivities as $i => $activity): ?>
        <div class="activity-item <?= e(['primary','teal','amber','danger'][$i % 4]) ?>">
          <strong><?= e($activity['description']) ?></strong>
          <span><?= e($activity['user_name'] ?? 'Hệ thống') ?> · <?= e($activityRoleNames[$activity['role']] ?? ($activity['role'] ?? '')) ?> · <?= e($activity['created_at']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$recentActivities): ?>
        <div class="activity-item primary"><strong>Chưa có lịch sử hoạt động</strong><span>Các thao tác mới của Admin, Giáo viên và Học sinh sẽ xuất hiện tại đây.</span></div>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="grid grid-2 dashboard-tables">
  <section class="dash-card table-card">
    <div class="section-head">
      <h2>Tài khoản mới</h2>
      <a href="users.php">Quản lý <span class="material-symbols-outlined">arrow_forward</span></a>
    </div>
    <div class="table-scroll">
      <table class="table modern-table">
        <thead><tr><th>Tên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th></tr></thead>
        <tbody>
        <?php foreach ($recentUsers as $u): ?>
          <tr><td><strong><?= e($u['name']) ?></strong></td><td><?= e($u['email']) ?></td><td><?= e($u['role']) ?></td><td><span class="status-pill <?= e($u['status']) ?>"><?= e(status_label($u['status'])) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="dash-card table-card">
    <div class="section-head">
      <h2>Đề thi gần đây</h2>
      <a href="exams.php">Xem chi tiết <span class="material-symbols-outlined">arrow_forward</span></a>
    </div>
    <div class="table-scroll">
      <table class="table modern-table">
        <thead><tr><th>Mã</th><th>Tiêu đề</th><th>Môn</th><th>Trạng thái</th></tr></thead>
        <tbody>
        <?php foreach ($recentExams as $e): ?>
          <tr><td><strong><?= e($e['code']) ?></strong></td><td><?= e($e['title']) ?></td><td><?= e($e['subject_name']) ?></td><td><span class="status-pill <?= e($e['status']) ?>"><?= e(status_label($e['status'])) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$recentExams): ?><tr><td colspan="4" class="muted">Chưa có đề thi nào.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
