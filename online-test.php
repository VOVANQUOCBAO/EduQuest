<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_assignments_table();
ensure_school_structure_tables();
$page_title = 'Thi trực tuyến';
$role = current_user()['role'];
if (in_array($role, ['teacher', 'student'], true)) {
    $classId = user_class_id((int)current_user()['id']);
    $st = db()->prepare('SELECT DISTINCT e.*,s.name subject_name,a.target,a.due_at,a.show_score
        FROM exams e
        JOIN subjects s ON s.id=e.subject_id
        JOIN exam_assignments a ON a.exam_id=e.id
        WHERE e.status="published"
          AND (a.target_user_id=? OR a.target_role="group" OR (a.target_role="class" AND a.target_class_id=?))
        ORDER BY COALESCE(a.created_at,e.created_at) DESC');
    $st->execute([current_user()['id'], $classId]);
    $rows = $st->fetchAll();
} else {
    $rows = db()->query('SELECT e.*,s.name subject_name,NULL target,NULL due_at,1 show_score FROM exams e JOIN subjects s ON s.id=e.subject_id ORDER BY e.created_at DESC')->fetchAll();
}
include __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><h1>Thi trực tuyến</h1><p>Danh sách bài kiểm tra được giao hoặc đã công bố.</p></div></div>
<div class="card">
  <table class="table"><tr><th>Mã đề</th><th>Tiêu đề</th><th>Môn</th><th>Thời gian</th><th>Hạn nộp</th><th>Trạng thái</th><th></th></tr>
  <?php foreach($rows as $r):
    $st=db()->prepare('SELECT * FROM attempts WHERE exam_id=? AND user_id=? ORDER BY id DESC LIMIT 1'); $st->execute([$r['id'],current_user()['id']]); $attempt=$st->fetch();
    $status = $attempt['status'] ?? ((!empty($r['due_at']) && strtotime($r['due_at']) < time()) ? 'Quá hạn' : 'Chưa làm');
  ?>
    <tr><td><strong><?= e($r['code']) ?></strong></td><td><?= e($r['title']) ?></td><td><?= e($r['subject_name']) ?></td><td><?= e($r['duration']) ?> phút</td><td><?= e($r['due_at'] ?? '') ?></td><td><span class="status-pill <?= e($attempt['status'] ?? $r['status']) ?>"><?= e(status_label($status)) ?></span></td><td class="actions"><a class="btn primary" href="test-room.php?id=<?= e($r['id']) ?>">Bắt đầu</a><?php if($attempt && $attempt['status']==='submitted'): ?><a class="btn ghost" href="results.php">Kết quả</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="7" class="muted">Chưa có bài kiểm tra nào.</td></tr><?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
