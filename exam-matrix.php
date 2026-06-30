<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','teacher']);
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT e.*,s.name subject_name FROM exams e JOIN subjects s ON s.id=e.subject_id WHERE e.id=?');
$st->execute([$id]);
$exam = $st->fetch();
if (!$exam) exit('Không tìm thấy đề');
$st = db()->prepare('SELECT l.name lesson_name,q.type,q.difficulty,COUNT(*) total FROM exam_questions eq JOIN questions q ON q.id=eq.question_id JOIN lessons l ON l.id=q.lesson_id WHERE eq.exam_id=? GROUP BY l.name,q.type,q.difficulty ORDER BY l.name,q.type');
$st->execute([$id]);
$rows = $st->fetchAll();
$page_title = 'Ma trận đề '.$exam['code'];
include __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><h1>Ma trận đề <?= e($exam['code']) ?></h1><p><?= e($exam['title']) ?> · <?= e($exam['subject_name']) ?></p></div><a class="btn ghost" href="exam-view.php?id=<?= e($id) ?>">Xem đề</a></div>
<div class="card">
  <table class="table matrix"><tr><th>Bài học</th><th>Dạng câu</th><th>Độ khó</th><th>Số câu</th></tr>
  <?php foreach($rows as $r): ?><tr><td><?= e($r['lesson_name']) ?></td><td><?= e(type_label($r['type'])) ?></td><td><?= e(difficulty_label($r['difficulty'])) ?></td><td><?= e($r['total']) ?></td></tr><?php endforeach; ?>
  <?php if(!$rows): ?><tr><td colspan="4" class="muted">Đề chưa có câu hỏi.</td></tr><?php endif; ?>
  </table>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
