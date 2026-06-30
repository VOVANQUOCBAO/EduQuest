<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
$page_title = 'Tạo câu hỏi từ tài liệu';

$generated = [];
$subject = (int)post('subject_id', 0);
$lesson = (int)post('lesson_id', 0);
$lessonName = post('lesson_name', '');
$type = normalize_generation_type(post('type', 'mixed'));
$difficulty = post('difficulty', 'medium');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = (int)post('subject_id');
    $lesson = (int)post('lesson_id');
    $lessonName = post('lesson_name', '');
    $type = normalize_generation_type(post('type', 'mixed'));
    $difficulty = post('difficulty', 'medium');

    if (isset($_POST['save_preview'])) {
        $saved = collect_preview_questions($_POST['q'] ?? [], $subject, $lesson, $lessonName);
        flash('Đã tạo và lưu ' . $saved . ' câu hỏi');
        redirect('questions.php?subject_id=' . $subject);
    }

    $text = extract_upload_text($_FILES['file'] ?? []);
    $rawText = post('raw_text');
    if (!$text) $text = $rawText;
    if (!$text) {
        flash('Vui lòng upload file hoặc dán nội dung tài liệu.', 'error');
        redirect('generate-questions.php');
    }
    $blockImages = save_import_question_block_images($text);
    $imagePaths = $blockImages ? array_values($blockImages) : import_image_paths_from_text($text);
    $generated = $imagePaths
        ? gemini_generate_questions_from_images($imagePaths, $type, max(1, (int)post('count', 5)), $difficulty, trim($rawText . "\n" . $text))
        : gemini_generate_questions($text, $type, max(1, (int)post('count', 5)), $difficulty);
    $generated = attach_image_groups_to_questions($generated, $text);
    $generated = attach_block_images_to_questions($generated, $blockImages);
}

$subjects = fetch_subjects();
$lessons = fetch_lessons_with_questions();
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <h2>Tạo câu hỏi từ tài liệu</h2>
  <p class="muted">Dán nội dung hoặc upload TXT/DOCX/PDF. Có thể đặt tên bài mới để lưu thành một bộ câu hỏi riêng trong ngân hàng.</p>
  <form method="post" enctype="multipart/form-data">
    <div class="form-row">
      <div>
        <label>Môn</label>
        <select name="subject_id" data-subject-filter="generate-lessons" required>
          <?php foreach ($subjects as $s): ?><option value="<?= e($s['id']) ?>" <?= $subject === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Bài học có sẵn</label>
        <select name="lesson_id" data-lesson-filter="generate-lessons" data-empty-label="Mon nay chua co bai hoc tu de da upload">
          <?php foreach ($lessons as $l): ?><option value="<?= e($l['id']) ?>" data-subject-id="<?= e($l['subject_id']) ?>" <?= $lesson === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['subject_name'] . ' - ' . $l['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <label>Tên bài mới nếu muốn tạo riêng cho tài liệu này</label>
    <input name="lesson_name" value="<?= e($lessonName) ?>" placeholder="Ví dụ: Bài 3 - Đạo hàm">
    <div class="form-row">
      <div>
        <label>Dạng câu</label>
        <select name="type"><?php foreach (['mixed' => 'Tổng hợp', 'mc' => 'Trắc nghiệm', 'tf' => 'Đúng/Sai', 'sa' => 'Trả lời ngắn', 'essay' => 'Tự luận'] as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
      </div>
      <div>
        <label>Độ khó</label>
        <select name="difficulty"><?php foreach (['easy' => 'Nhận biết', 'medium' => 'Thông hiểu', 'hard' => 'Vận dụng'] as $k => $v): ?><option value="<?= $k ?>" <?= $difficulty === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div class="form-row">
      <div><label>Số lượng</label><input type="number" name="count" value="5" min="1" max="50"></div>
      <div><label>File TXT/DOCX/PDF</label><input type="file" name="file" accept=".txt,.docx,.pdf,application/pdf"></div>
    </div>
    <label>Nội dung tài liệu</label>
    <textarea name="raw_text" placeholder="Dán nội dung bài học tại đây nếu không upload file"></textarea>
    <button class="btn primary"><span class="material-symbols-outlined">psychology</span> Tạo câu hỏi để chỉnh sửa</button>
  </form>
</div>

<?php if ($generated): ?>
<form method="post" class="card preview-editor" style="margin-top:24px">
  <input type="hidden" name="subject_id" value="<?= e($subject) ?>">
  <input type="hidden" name="lesson_id" value="<?= e($lesson) ?>">
  <input type="hidden" name="lesson_name" value="<?= e($lessonName) ?>">
  <div class="actions">
    <button class="btn" type="button" data-add-preview-question><span class="material-symbols-outlined">add_circle</span> Thêm câu hỏi</button>
  </div>
  <h2>Chỉnh sửa câu hỏi được tạo</h2>
  <?php foreach ($generated as $i => $q): ?>
    <div class="question-edit-card">
      <div class="question-edit-head">
        <strong>Câu <?= $i + 1 ?></strong>
        <select name="q[<?= $i ?>][type]" data-question-type><?php foreach (['mixed' => 'Tổng hợp', 'mc' => 'Trắc nghiệm', 'tf' => 'Đúng/Sai', 'sa' => 'Trả lời ngắn', 'essay' => 'Tự luận'] as $k => $v): ?><option value="<?= $k ?>" <?= $q['type'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        <select name="q[<?= $i ?>][difficulty]"><?php foreach (['easy' => 'Nhận biết', 'medium' => 'Thông hiểu', 'hard' => 'Vận dụng', 'unknown' => 'Không rõ'] as $k => $v): ?><option value="<?= $k ?>" <?= $q['difficulty'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        <label class="inline-check"><input type="checkbox" name="q[<?= $i ?>][needs_review]" value="1" <?= !empty($q['needs_review']) ? 'checked' : '' ?>> Cần kiểm tra</label>
      </div>
      <input type="hidden" name="q[<?= $i ?>][image_path]" value="<?= e($q['image_path'] ?? '') ?>">
      <label>Nội dung</label><textarea name="q[<?= $i ?>][content]"><?= e($q['content']) ?></textarea>
      <?= question_image_tag($q['image_path'] ?? '') ?>
      <div class="grid grid-2 question-type-panel" data-panel="mc">
        <?php foreach (['A', 'B', 'C', 'D'] as $o): ?><div><label>Đáp án <?= $o ?></label><input name="q[<?= $i ?>][options][<?= $o ?>]" value="<?= e($q['options'][$o] ?? '') ?>"></div><?php endforeach; ?>
      </div>
      <div class="question-type-panel" data-panel="tf">
        <div class="grid grid-2"><?php foreach (['a', 'b', 'c', 'd'] as $o): ?><?php $tfAnswer = (string)($q['tf_items'][$o]['answer'] ?? 'true'); ?><div><label>Ý <?= $o ?></label><input name="q[<?= $i ?>][tf_items][<?= $o ?>][content]" value="<?= e($q['tf_items'][$o]['content'] ?? '') ?>"><select name="q[<?= $i ?>][tf_items][<?= $o ?>][answer]"><option value="true" <?= $tfAnswer === 'true' ? 'selected' : '' ?>>Đúng</option><option value="false" <?= $tfAnswer === 'false' ? 'selected' : '' ?>>Sai</option></select></div><?php endforeach; ?></div>
      </div>
      <label>Đáp án đúng / đáp án gợi ý</label><input name="q[<?= $i ?>][answer]" value="<?= e($q['answer'] ?? '') ?>" placeholder="A/B/C/D hoặc đáp án tự luận">
      <label>Giải thích</label><textarea name="q[<?= $i ?>][explanation]"><?= e($q['explanation'] ?? '') ?></textarea>
    </div>
  <?php endforeach; ?>
  <button class="btn primary" name="save_preview" value="1"><span class="material-symbols-outlined">save</span> Lưu vào ngân hàng câu hỏi</button>
</form>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
