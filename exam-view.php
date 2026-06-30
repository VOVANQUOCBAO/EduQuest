<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_question_image_column();
ensure_exam_points_columns();

function exam_data($id)
{
    $st = db()->prepare('SELECT e.*,s.name subject_name FROM exams e JOIN subjects s ON s.id=e.subject_id WHERE e.id=?');
    $st->execute([$id]);
    $exam = $st->fetch();

    $st = db()->prepare('SELECT q.*,eq.position,eq.points,l.name lesson_name FROM exam_questions eq JOIN questions q ON q.id=eq.question_id JOIN lessons l ON l.id=q.lesson_id WHERE eq.exam_id=? ORDER BY eq.position');
    $st->execute([$id]);
    return [$exam, $st->fetchAll()];
}

[$exam, $questions] = exam_data((int)($_GET['id'] ?? 0));
if (!$exam) exit('Khong tim thay de');

$page_title = 'Xem de ' . $exam['code'];
include __DIR__ . '/includes/header.php';
?>
<div class="exam-paper">
  <div style="text-align:center">
    <strong><?= e($exam['school_name']) ?></strong>
    <h2><?= e($exam['title']) ?></h2>
    <p>Mon: <?= e($exam['subject_name']) ?> - Ma de: <?= e($exam['code']) ?> - Thoi gian: <?= e($exam['duration']) ?> phut - Tong diem: <?= e($exam['total_points'] ?? '') ?></p>
  </div>
  <?php foreach ($questions as $i => $q): ?>
    <div class="question">
      <strong>Cau <?= $i + 1 ?>:</strong> <span class="muted">(<?= e($q['points']) ?> diem)</span> <?= e(display_question_content($q['content'], $q['image_path'] ?? '')) ?>
      <span class="badge <?= e($q['difficulty']) ?>"><?= difficulty_label($q['difficulty']) ?></span>
      <?= question_image_tag($q['image_path'] ?? '') ?>

      <?php if ($q['type'] === 'mc'): ?>
        <?php foreach (question_options($q['id']) as $op): ?>
          <div class="choice"><?= e($op['label'] . '. ' . $op['content']) ?></div>
        <?php endforeach; ?>
      <?php elseif ($q['type'] === 'tf'): ?>
        <?php foreach (tf_items($q['id']) as $it): ?>
          <div class="choice"><?= e($it['label'] . ') ' . $it['content']) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <p class="muted">Bai: <?= e($q['lesson_name']) ?> - Dap an: <?= e($q['answer']) ?></p>
    </div>
  <?php endforeach; ?>
  <h3 style="text-align:center">----------- HET -----------</h3>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
