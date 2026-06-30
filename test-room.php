<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_question_image_column();
ensure_exam_points_columns();
ensure_school_structure_tables();

$id = (int)($_GET['id'] ?? 0);
$pdo = db();
$st = $pdo->prepare('SELECT e.*, s.name subject_name FROM exams e JOIN subjects s ON s.id=e.subject_id WHERE e.id=?');
$st->execute([$id]);
$exam = $st->fetch();
if (!$exam) {
    exit('Không tìm thấy đề');
}
if ($exam['status'] !== 'published' && current_user()['role'] === 'student') {
    exit('Đề chưa được công bố.');
}
if (in_array(current_user()['role'], ['teacher', 'student'], true)) {
    ensure_assignments_table();
    $classId = user_class_id((int)current_user()['id']);
    $st = $pdo->prepare('SELECT id FROM exam_assignments WHERE exam_id=? AND (target_user_id=? OR target_role="group" OR (target_role="class" AND target_class_id=?)) LIMIT 1');
    $st->execute([$id, current_user()['id'], $classId]);
    if (!$st->fetchColumn()) {
        exit('Bạn chưa được giao đề thi này.');
    }
}

$st = $pdo->prepare('SELECT * FROM attempts WHERE exam_id=? AND user_id=? AND status="doing" ORDER BY id DESC LIMIT 1');
$st->execute([$id, current_user()['id']]);
$attempt = $st->fetch();
if (!$attempt) {
    $pdo->prepare('INSERT INTO attempts(exam_id,user_id,max_score) VALUES(?,?,0)')->execute([$id, current_user()['id']]);
    $attempt = ['id' => $pdo->lastInsertId()];
    log_activity('start', 'attempt', (int)$attempt['id'], 'Đã bắt đầu làm bài: ' . ($exam['code'] ?? '') . ' - ' . ($exam['title'] ?? ''));
}

$st = $pdo->prepare('SELECT q.*, eq.position, eq.points FROM exam_questions eq JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=? ORDER BY eq.position');
$st->execute([$id]);
$qs = $st->fetchAll();
$page_title = 'Phòng thi ' . $exam['code'];
include __DIR__ . '/includes/header.php';
?>
<div class="test-layout">
  <aside class="test-panel card">
    <h2><?= e($exam['code']) ?></h2>
    <p class="muted"><?= e($exam['subject_name']) ?> · <?= e($exam['duration']) ?> phút</p>
    <div class="timer" data-minutes="<?= e($exam['duration']) ?>"><?= e($exam['duration']) ?>:00</div>
    <div class="question-nav">
      <?php foreach ($qs as $i => $q): ?><a href="#q<?= $i + 1 ?>" data-qnav="<?= $q['id'] ?>"><?= $i + 1 ?></a><?php endforeach; ?>
    </div>
    <button class="btn ghost" type="button" data-save-draft><span class="material-symbols-outlined">save</span> Lưu tạm</button>
  </aside>
  <form class="card test-paper" method="post" action="submit-test.php">
    <input type="hidden" name="attempt_id" value="<?= e($attempt['id']) ?>">
    <input type="hidden" name="exam_id" value="<?= e($id) ?>">
    <div class="section-head">
      <div>
        <h2><?= e($exam['title']) ?></h2>
        <p class="muted">Mã đề <?= e($exam['code']) ?> · Hãy kiểm tra kỹ trước khi nộp.</p>
      </div>
    </div>
    <?php foreach ($qs as $i => $q): ?>
      <div class="question" id="q<?= $i + 1 ?>">
        <strong>Câu <?= $i + 1 ?>:</strong> <span class="muted">(<?= e($q['points']) ?> điểm)</span> <?= question_content_html($q['content'], $q['image_path'] ?? '') ?>
        <?= question_image_tag($q['image_path'] ?? '') ?>
        <?php if ($q['type'] === 'mc'): foreach (question_options($q['id']) as $op): ?>
          <label class="choice"><input data-answer-input="<?= $q['id'] ?>" type="radio" name="answer[<?= $q['id'] ?>]" value="<?= e($op['label']) ?>"> <?= e($op['label'] . '. ') ?><?= question_content_html($op['content']) ?></label>
        <?php endforeach; elseif ($q['type'] === 'tf'): ?>
          <div class="choice">
            <select data-answer-input="<?= $q['id'] ?>" name="answer[<?= $q['id'] ?>]">
              <option value="">Chọn</option>
              <option value="true">Đúng</option>
              <option value="false">Sai</option>
            </select>
          </div>
        <?php elseif ($q['type'] === 'sa'): ?>
          <input data-answer-input="<?= $q['id'] ?>" name="answer[<?= $q['id'] ?>]" placeholder="Nhập câu trả lời ngắn">
        <?php else: ?>
          <textarea data-answer-input="<?= $q['id'] ?>" name="answer[<?= $q['id'] ?>]" placeholder="Nhập bài làm tự luận"></textarea>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="actions"><button class="btn primary" data-confirm="Nộp bài? Sau khi nộp bạn không thể sửa bài."><span class="material-symbols-outlined">send</span> Nộp bài</button></div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
