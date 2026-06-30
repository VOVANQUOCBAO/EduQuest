<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_question_image_column();
ensure_exam_points_columns();

$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare('SELECT e.*,s.name subject_name FROM exams e JOIN subjects s ON s.id=e.subject_id WHERE e.id=?');
$st->execute([$id]);
$exam = $st->fetch();
if (!$exam) exit('Khong tim thay de');
if ($exam['status'] !== 'published' && current_user()['role'] === 'student') exit('De chua duoc cong bo.');

$st = $pdo->prepare('SELECT * FROM attempts WHERE exam_id=? AND user_id=? AND status="doing" ORDER BY id DESC LIMIT 1');
$st->execute([$id, current_user()['id']]);
$attempt = $st->fetch();
if (!$attempt) {
    $pdo->prepare('INSERT INTO attempts(exam_id,user_id,max_score) VALUES(?,?,0)')->execute([$id, current_user()['id']]);
    $attempt = ['id' => $pdo->lastInsertId()];
    log_activity('start', 'attempt', (int)$attempt['id'], 'Đã bắt đầu làm bài: ' . ($exam['code'] ?? '') . ' - ' . ($exam['title'] ?? ''));
}

$st = $pdo->prepare('SELECT q.*,eq.position,eq.points FROM exam_questions eq JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=? ORDER BY eq.position');
$st->execute([$id]);
$qs = $st->fetchAll();

$page_title = 'Lam bai ' . $exam['code'];
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <h2><?= e($exam['title']) ?></h2>
  <p>Mon <?= e($exam['subject_name']) ?> - Ma de <?= e($exam['code']) ?> - Thoi gian <?= e($exam['duration']) ?> phut</p>
  <form method="post" action="submit-test.php">
    <input type="hidden" name="attempt_id" value="<?= e($attempt['id']) ?>">
    <input type="hidden" name="exam_id" value="<?= e($id) ?>">

    <?php foreach ($qs as $i => $q): ?>
      <div class="question">
        <strong>Cau <?= $i + 1 ?>:</strong> <span class="muted">(<?= e($q['points']) ?> diem)</span> <?= e(display_question_content($q['content'], $q['image_path'] ?? '')) ?>
        <?= question_image_tag($q['image_path'] ?? '') ?>

        <?php if ($q['type'] === 'mc'): ?>
          <?php foreach (question_options($q['id']) as $op): ?>
            <label class="choice"><input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= e($op['label']) ?>"> <?= e($op['label'] . '. ' . $op['content']) ?></label>
          <?php endforeach; ?>
        <?php elseif ($q['type'] === 'tf'): ?>
          <?php foreach (tf_items($q['id']) as $it): ?>
            <div class="choice"><?= e($it['label'] . ') ' . $it['content']) ?> <select name="answer[<?= $q['id'] ?>][<?= e($it['label']) ?>]"><option value="true">Dung</option><option value="false">Sai</option></select></div>
          <?php endforeach; ?>
        <?php else: ?>
          <textarea name="answer[<?= $q['id'] ?>]" placeholder="Nhap cau tra loi"></textarea>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <button class="btn primary" data-confirm="Nop bai?">Nop bai</button>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
