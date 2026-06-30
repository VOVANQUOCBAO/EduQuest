<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['teacher','student']);
ensure_activity_logs_table();
$page_title = 'Dashboard';
$current = current_user();
$currentUserId = (int)$current['id'];
$isTeacher = $current['role'] === 'teacher';

$counts = [];
ensure_assignments_table();
$counts['questions'] = (int)db()->query("SELECT COUNT(*) FROM questions WHERE created_by={$currentUserId}")->fetchColumn();
$counts['exams'] = (int)db()->query("SELECT COUNT(*) FROM exams WHERE created_by={$currentUserId}")->fetchColumn();
$counts['attempts'] = (int)db()->query("SELECT COUNT(*) FROM attempts WHERE user_id={$currentUserId}")->fetchColumn();
$counts['assignments'] = $isTeacher
    ? (int)db()->query("SELECT COUNT(*) FROM exam_assignments WHERE created_by={$currentUserId} OR target_user_id={$currentUserId}")->fetchColumn()
    : (int)db()->query("SELECT COUNT(*) FROM exam_assignments WHERE target_user_id={$currentUserId}")->fetchColumn();
$counts['ungraded'] = $isTeacher
    ? (int)db()->query("SELECT COUNT(*) FROM attempt_answers aa JOIN attempts a ON a.id=aa.attempt_id JOIN exams e ON e.id=a.exam_id JOIN questions q ON q.id=aa.question_id WHERE q.type='essay' AND aa.score=0 AND e.created_by={$currentUserId}")->fetchColumn()
    : 0;
$recentSql = $isTeacher
    ? 'SELECT e.*,s.name subject_name,u.name creator,COUNT(eq.id) total_questions FROM exams e JOIN subjects s ON s.id=e.subject_id LEFT JOIN users u ON u.id=e.created_by LEFT JOIN exam_questions eq ON eq.exam_id=e.id WHERE e.created_by=? GROUP BY e.id ORDER BY e.created_at DESC LIMIT 6'
    : 'SELECT DISTINCT e.*,s.name subject_name,u.name creator,COUNT(eq.id) total_questions FROM attempts a JOIN exams e ON e.id=a.exam_id JOIN subjects s ON s.id=e.subject_id LEFT JOIN users u ON u.id=e.created_by LEFT JOIN exam_questions eq ON eq.exam_id=e.id WHERE a.user_id=? GROUP BY e.id ORDER BY MAX(a.started_at) DESC LIMIT 6';
$st = db()->prepare($recentSql);
$st->execute([$currentUserId]);
$recent = $st->fetchAll();
$weekActivity = activity_chart_data('week', $currentUserId);
$monthActivity = activity_chart_data('month', $currentUserId);
$recentActivities = recent_activity_logs(8, $currentUserId);
$activityRoleNames = ['admin' => 'Admin', 'teacher' => 'Giáo viên', 'student' => 'Học sinh'];
$stats = $isTeacher
    ? [
        ['questions','Câu hỏi của tôi','quiz','purple'], ['exams','Đề tôi tạo','assignment','rose'], ['assignments','Đề liên quan','send','blue'], ['attempts','Lượt làm của tôi','history_edu','indigo'], ['ungraded','Chưa chấm','rate_review','rose'],
    ]
    : [
        ['assignments','Đề được giao','send','blue'], ['attempts','Lượt làm bài','history_edu','indigo'],
    ];
include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Tổng quan hệ thống</h1>
    <p>Chào mừng trở lại! Dưới đây là tóm tắt hoạt động mới nhất của EduQuest.</p>
  </div>
  <div class="heading-actions">
    <?php if ($isTeacher): ?>
      <a class="btn secondary" href="notifications.php"><span class="material-symbols-outlined">notifications</span> Thông báo</a>
    <?php endif; ?>
    <?php if ($isTeacher): ?><a class="btn primary" href="create-exam.php"><span class="material-symbols-outlined">add_circle</span> Tạo đề mới</a><?php endif; ?>
  </div>
</div>

<div class="stats-grid">
  <?php foreach ($stats as $s): ?>
    <div class="dash-card stat-card">
      <div class="stat-top">
        <span class="stat-icon <?= e($s[3]) ?>"><span class="material-symbols-outlined"><?= e($s[2]) ?></span></span>
        <span class="trend"><span class="material-symbols-outlined">schedule</span></span>
      </div>
      <p><?= e($s[1]) ?></p>
      <h3><?= e(number_format($counts[$s[0]])) ?></h3>
    </div>
  <?php endforeach; ?>
</div>

<div class="dashboard-grid">
  <section class="dash-card chart-card">
    <div class="section-head">
      <h2>Hoạt động của tôi</h2>
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
    <div class="section-head"><h2>Hoạt động gần đây</h2></div>
    <div class="activity-list">
      <?php foreach ($recentActivities as $i => $activity): ?>
        <div class="activity-item <?= e(['primary','teal','amber','danger'][$i % 4]) ?>">
          <strong><?= e($activity['description']) ?></strong>
          <span><?= e($activityRoleNames[$activity['role']] ?? ($activity['role'] ?? '')) ?> · <?= e($activity['created_at']) ?></span>
        </div>
      <?php endforeach; ?>
      <?php if (!$recentActivities): ?>
        <div class="activity-item primary"><strong>Chưa có hoạt động riêng</strong><span>Các thao tác của tài khoản này sẽ xuất hiện tại đây.</span></div>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="dash-card table-card">
  <div class="section-head">
    <h2>Đề thi gần đây</h2>
    <a href="exams.php">Xem chi tiết <span class="material-symbols-outlined">arrow_forward</span></a>
  </div>
  <div class="table-scroll">
    <table class="table modern-table">
      <thead><tr><th>Mã đề</th><th>Tiêu đề</th><th>Môn</th><th>Số câu</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $e): ?>
        <tr>
          <td><strong><?= e($e['code']) ?></strong></td>
          <td><?= e($e['title']) ?></td>
          <td><?= e($e['subject_name']) ?></td>
          <td><?= e($e['total_questions']) ?></td>
          <td><span class="status-pill <?= e($e['status']) ?>"><?= e(status_label($e['status'])) ?></span></td>
          <td><?= e($e['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recent): ?><tr><td colspan="6" class="muted">Chưa có đề thi nào.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
