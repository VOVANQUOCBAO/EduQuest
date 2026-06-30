<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin','teacher']);
ensure_exam_points_columns();
$page_title = 'Chấm tự luận';
$attemptId = (int)($_GET['attempt_id'] ?? $_POST['attempt_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $attemptId) {
    $st = db()->prepare('SELECT aa.id,eq.points FROM attempt_answers aa JOIN attempts a ON a.id=aa.attempt_id JOIN exam_questions eq ON eq.exam_id=a.exam_id AND eq.question_id=aa.question_id WHERE aa.attempt_id=?');
    $st->execute([$attemptId]);
    $maxByAnswer = [];
    foreach ($st->fetchAll() as $row) $maxByAnswer[(int)$row['id']] = (float)$row['points'];

    foreach (($_POST['score'] ?? []) as $answerId => $score) {
        $answerId = (int)$answerId;
        $maxScore = $maxByAnswer[$answerId] ?? 1;
        $score = max(0, min((float)$score, $maxScore));
        db()->prepare('UPDATE attempt_answers SET score=?,is_correct=NULL WHERE id=?')->execute([$score, $answerId]);
    }

    $st = db()->prepare('SELECT SUM(aa.score) score,SUM(eq.points) max_score FROM attempt_answers aa JOIN attempts a ON a.id=aa.attempt_id JOIN exam_questions eq ON eq.exam_id=a.exam_id AND eq.question_id=aa.question_id WHERE aa.attempt_id=?');
    $st->execute([$attemptId]);
    $sum = $st->fetch();
    db()->prepare('UPDATE attempts SET score=?,max_score=? WHERE id=?')->execute([(float)$sum['score'], (float)$sum['max_score'], $attemptId]);
    log_activity('grade', 'attempt', $attemptId, 'Đã chấm tự luận cho bài làm #' . $attemptId);
    flash('Đã lưu điểm tự luận');
    redirect('grade-essay.php?attempt_id=' . $attemptId);
}

$attempts = db()->query('SELECT a.*,e.code,e.title,s.name subject_name,u.name student FROM attempts a JOIN exams e ON e.id=a.exam_id JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=a.user_id WHERE a.status="submitted" ORDER BY a.submitted_at DESC')->fetchAll();
$attempt = null;
$answers = [];
if ($attemptId) {
    $st = db()->prepare('SELECT a.*,e.code,e.title,s.name subject_name,u.name student FROM attempts a JOIN exams e ON e.id=a.exam_id JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=a.user_id WHERE a.id=?');
    $st->execute([$attemptId]);
    $attempt = $st->fetch();
    $st = db()->prepare('SELECT aa.*,q.content,q.answer suggested_answer,q.type,eq.points FROM attempt_answers aa JOIN attempts a ON a.id=aa.attempt_id JOIN questions q ON q.id=aa.question_id JOIN exam_questions eq ON eq.exam_id=a.exam_id AND eq.question_id=aa.question_id WHERE aa.attempt_id=? AND q.type="essay"');
    $st->execute([$attemptId]);
    $answers = $st->fetchAll();
}
include __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><h1>Chấm tự luận</h1><p>Xem câu trả lời tự luận, nhập điểm và lưu lại kết quả.</p></div></div>
<div class="grid grid-2">
  <div class="card">
    <h2>Bài làm đã nộp</h2>
    <table class="table"><tr><th>Học sinh</th><th>Đề</th><th>Điểm</th><th></th></tr><?php foreach($attempts as $a): ?><tr><td><?= e($a['student']) ?></td><td><?= e($a['code'].' - '.$a['title']) ?></td><td><?= e($a['score']) ?>/<?= e($a['max_score']) ?></td><td><a class="btn ghost" href="?attempt_id=<?= e($a['id']) ?>">Chấm</a></td></tr><?php endforeach; ?></table>
  </div>
  <div class="card">
    <h2>Thông tin bài chấm</h2>
    <?php if($attempt): ?><p><strong><?= e($attempt['student']) ?></strong></p><p class="muted"><?= e($attempt['code'].' - '.$attempt['title'].' · '.$attempt['subject_name']) ?></p><?php else: ?><p class="muted">Chọn một bài làm để chấm tự luận.</p><?php endif; ?>
  </div>
</div>
<?php if($attempt): ?>
<form method="post" class="card" style="margin-top:24px">
  <input type="hidden" name="attempt_id" value="<?= e($attemptId) ?>">
  <h2>Câu tự luận</h2>
  <?php foreach($answers as $i=>$a): ?>
    <div class="question-edit-card">
      <strong>Câu <?= $i+1 ?>:</strong> <span class="muted">(tối đa <?= e($a['points']) ?> điểm)</span>
      <p><?= e($a['content']) ?></p>
      <label>Bài làm học sinh</label><textarea readonly><?= e($a['answer']) ?></textarea>
      <label>Đáp án gợi ý</label><textarea readonly><?= e($a['suggested_answer']) ?></textarea>
      <label>Điểm</label><input type="number" step="0.25" min="0" max="<?= e($a['points']) ?>" name="score[<?= e($a['id']) ?>]" value="<?= e($a['score']) ?>">
      <label>Nhận xét</label><textarea placeholder="Nhập nhận xét cho bài làm"></textarea>
    </div>
  <?php endforeach; ?>
  <?php if(!$answers): ?><p class="muted">Bài làm này không có câu tự luận.</p><?php endif; ?>
  <button class="btn primary"><span class="material-symbols-outlined">save</span> Lưu điểm</button>
</form>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
