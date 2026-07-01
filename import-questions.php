<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
ensure_question_image_column();
$page_title = 'Nhập câu hỏi từ file';

$preview = [];
$subject = (int)post('subject_id', 0);
$lesson = (int)post('lesson_id', 0);
$lessonName = post('lesson_name', '');
// Luôn để chế độ tự nhận diện: hệ thống tự suy ra mỗi câu là trắc nghiệm,
// đúng/sai, trả lời ngắn hay tự luận dựa theo nội dung file. Không bắt người
// dùng chọn dạng câu hỏi trước khi upload.
$type = 'mixed';
$requestedCount = max(0, (int)post('count', 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = (int)post('subject_id');
    $lesson = (int)post('lesson_id');
    $lessonName = post('lesson_name', '');
    $type = 'mixed';
    $requestedCount = max(0, (int)post('count', 0));

    if (isset($_POST['save_preview'])) {
        $saved = collect_preview_questions($_POST['q'] ?? [], $subject, $lesson, $lessonName);
        flash('Đã lưu ' . $saved . ' câu hỏi vào ngân hàng');
        redirect('questions.php?subject_id=' . $subject);
    }

    $text = extract_upload_text($_FILES['file'] ?? []);
    if (!$text) {
        flash('Không đọc được file. Hỗ trợ tốt nhất TXT, DOCX và PDF có lớp văn bản.', 'error');
        redirect('import-questions.php');
    }
    $blockImages = save_import_question_block_images($text);
    $imagePaths = $blockImages ? array_values($blockImages) : import_image_paths_from_text($text);
    $isPdfUpload = strtolower(pathinfo($_FILES['file']['name'] ?? '', PATHINFO_EXTENSION)) === 'pdf';
    $needsAi = $imagePaths || $isPdfUpload;
    if ($needsAi && !gemini_api_key()) {
        flash('File có ảnh/PDF scan/công thức nhưng chưa cấu hình Gemini API key, nên hệ thống chỉ giữ ảnh và chữ thô. Hãy tạo config/gemini.local.php trên hosting để bật OCR. Vào import-diagnostics.php để kiểm tra.', 'error');
    }
    $textPreview = parse_questions_text($text, $type);
    $hasReadableText = false;
    foreach ($textPreview as $q) {
        $content = trim((string)($q['content'] ?? ''));
        if ($content !== '' && !is_image_question_placeholder($content)) {
            $hasReadableText = true;
            break;
        }
        foreach (($q['options'] ?? []) as $optionText) {
            $optionText = trim((string)$optionText);
            if ($optionText !== '' && !is_image_question_placeholder($optionText)) {
                $hasReadableText = true;
                break 2;
            }
        }
        foreach (($q['tf_items'] ?? []) as $item) {
            $itemText = trim((string)($item['content'] ?? ''));
            if ($itemText !== '' && !is_image_question_placeholder($itemText)) {
                $hasReadableText = true;
                break 2;
            }
        }
    }
    $aiCount = $requestedCount > 0 ? $requestedCount : max(1, max(count($textPreview), count($imagePaths)));
    $pdfPreview = ($isPdfUpload && gemini_api_key())
        ? gemini_generate_questions_from_pdf_upload($_FILES['file'] ?? [], $type, $aiCount, post('difficulty', 'medium'), $text)
        : [];
    $visionPreview = $imagePaths
        ? gemini_generate_questions_from_images($imagePaths, $type, $aiCount, post('difficulty', 'medium'), $text)
        : [];
    if ($pdfPreview) $visionPreview = $pdfPreview;
    $useVisionPreview = !$hasReadableText && $visionPreview;
    if (!$useVisionPreview && $visionPreview) {
        $visionReadable = false;
        foreach ($visionPreview as $q) {
            $content = trim((string)($q['content'] ?? ''));
            if ($content !== '' && !is_image_question_placeholder($content)) {
                $visionReadable = true;
                break;
            }
        }
        $useVisionPreview = $visionReadable;
    }
    $parsed = attach_image_groups_to_questions($useVisionPreview ? $visionPreview : $textPreview, $text);
    $parsed = attach_block_images_to_questions($parsed, $blockImages);
    $parsed = attach_pdf_page_images_to_questions($parsed, $_FILES['file'] ?? [], $type);
    $inferredCount = count($parsed);
    if ($inferredCount === 0) {
        $inferredCount = preg_match_all('/^\s*(?:Cau|Câu)\s+\d+\s*[\.:]/miu', $text) ?: 10;
    }
    $fallbackCount = $requestedCount > 0 ? $requestedCount : max(1, $inferredCount);
    $preview = $parsed ?: gemini_generate_questions($text, $type, $fallbackCount, post('difficulty', 'medium'));
    $preview = preserve_import_source_images(finalize_import_preview_questions($preview, $text), $text, $blockImages);

    // Neu file can AI (anh/PDF scan), da co key nhung goi Gemini that bai (thuong do
    // host chan outbound), bao ro ly do thay vi de nguoi dung nhan ra ket qua rong.
    if ($needsAi && gemini_api_key() && !$useVisionPreview && !$pdfPreview && gemini_last_error() !== '') {
        flash('Đã có Gemini key nhưng gọi API thất bại: ' . gemini_last_error() . ' — Mở import-diagnostics.php và bấm "Test gọi Gemini" để xác nhận host có chặn outbound không.', 'error');
    }
}

$subjects = fetch_subjects();
$lessons = fetch_lessons_with_questions();
include __DIR__ . '/includes/header.php';
?>
<style>
  form[enctype="multipart/form-data"] div:has(> input[name="count"]) { display: none; }
  .inline-check{display:inline-flex;align-items:center;gap:6px;margin:0;font-weight:700;color:#991b1b}
  .inline-check input{width:auto}
</style>
<div class="card">
  <h2>Nhập câu hỏi từ file</h2>
  <p class="muted">Upload TXT/DOCX/PDF, chọn bài có sẵn hoặc nhập tên bài mới để lưu thành một bộ câu hỏi riêng.</p>
  <form method="post" enctype="multipart/form-data">
    <div class="form-row">
      <div>
        <label>Môn</label>
        <select name="subject_id" data-subject-filter="import-lessons" required>
          <?php foreach ($subjects as $s): ?><option value="<?= e($s['id']) ?>" <?= $subject === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Bài học có sẵn</label>
        <select name="lesson_id" data-lesson-filter="import-lessons" data-empty-label="Môn này chưa có bài học từ đề đã upload">
          <?php foreach ($lessons as $l): ?><option value="<?= e($l['id']) ?>" data-subject-id="<?= e($l['subject_id']) ?>" <?= $lesson === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['subject_name'] . ' - ' . $l['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <label>Tên bài mới nếu muốn tạo riêng cho file này</label>
    <input name="lesson_name" value="<?= e($lessonName) ?>" placeholder="Ví dụ: Chương 1 - Hàm số">
    <p class="muted" style="margin:4px 0 0">Không cần chọn dạng câu hỏi: hệ thống tự nhận diện trắc nghiệm, đúng/sai, trả lời ngắn hoặc tự luận cho từng câu theo nội dung file.</p>
    <input type="hidden" name="difficulty" value="medium">
    <div class="form-row">
      <div><label>Số câu khi dùng AI</label><input type="number" name="count" min="0" value="0"></div>
      <div><label>File TXT/DOCX/PDF/ảnh</label><input type="file" name="file" accept=".txt,.docx,.pdf,application/pdf,image/png,image/jpeg,image/webp,image/gif" required></div>
    </div>
    <button class="btn primary"><span class="material-symbols-outlined">auto_fix_high</span> Đọc file và tạo preview</button>
  </form>
</div>

<?php if ($preview): ?>
<form method="post" class="card preview-editor" style="margin-top:24px">
  <input type="hidden" name="subject_id" value="<?= e($subject) ?>">
  <input type="hidden" name="lesson_id" value="<?= e($lesson) ?>">
  <input type="hidden" name="lesson_name" value="<?= e($lessonName) ?>">
  <div class="actions">
    <button class="btn" type="button" data-add-preview-question><span class="material-symbols-outlined">add_circle</span> Thêm câu hỏi</button>
  </div>
  <h2>Chỉnh sửa trước khi lưu <?= count($preview) ?> câu</h2>
  <?php foreach ($preview as $i => $q): ?>
    <?php $q['image_path'] = implode('|', normalize_question_image_paths($q['image_path'] ?? '', $q['content'] ?? '')); ?>
    <div class="question-edit-card">
      <div class="question-edit-head">
        <strong>Câu <?= $i + 1 ?></strong>
        <select name="q[<?= $i ?>][type]" data-question-type><?php foreach (['mixed' => 'Tổng hợp', 'mc' => 'Trắc nghiệm', 'tf' => 'Đúng/Sai', 'sa' => 'Trả lời ngắn', 'essay' => 'Tự luận'] as $k => $v): ?><option value="<?= $k ?>" <?= $q['type'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        <select name="q[<?= $i ?>][difficulty]"><?php foreach (['easy' => 'Nhận biết', 'medium' => 'Thông hiểu', 'hard' => 'Vận dụng', 'unknown' => 'Không rõ'] as $k => $v): ?><option value="<?= $k ?>" <?= $q['difficulty'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
        <button class="btn danger" type="button" data-remove-preview-question><span class="material-symbols-outlined">delete</span> Xóa câu</button>
      </div>
      <input type="hidden" name="q[<?= $i ?>][image_path]" value="<?= e($q['image_path'] ?? '') ?>">
      <div class="question-content-field">
        <?php if (is_image_question_placeholder($q['content'] ?? '')): ?>
          <input type="hidden" name="q[<?= $i ?>][content]" value="<?= e($q['content']) ?>">
        <?php elseif (question_content_has_inline_images($q['content'] ?? '')): ?>
          <label>Nội dung</label>
          <div class="question-content-render"><?= question_content_html($q['content'] ?? '') ?></div>
          <input type="hidden" name="q[<?= $i ?>][content]" value="<?= e($q['content']) ?>">
        <?php else: ?>
          <label>Nội dung</label><textarea name="q[<?= $i ?>][content]"><?= e($q['content']) ?></textarea>
        <?php endif; ?>
        <?= question_image_tag($q['image_path'] ?? '') ?>
      </div>
      <div class="grid grid-2 question-type-panel" data-panel="mc">
        <div data-option-list data-option-name-template="q[<?= $i ?>][options][__LABEL__]" data-answer-select="select[name='q[<?= $i ?>][answer]']">
          <?php foreach (mc_option_labels($q['options'] ?? [], $q['answer'] ?? 'A') as $o): ?>
            <?php $optionValue = (string)($q['options'][$o] ?? ''); ?>
            <div class="option-row" data-option-label="<?= e($o) ?>">
              <label>Đáp án <?= $o ?></label>
              <?php if (question_content_has_inline_images($optionValue)): ?>
                <div class="question-content-render option-render"><?= question_content_html($optionValue) ?></div>
                <input type="hidden" name="q[<?= $i ?>][options][<?= $o ?>]" value="<?= e($optionValue) ?>">
              <?php else: ?>
                <input name="q[<?= $i ?>][options][<?= $o ?>]" value="<?= e($optionValue) ?>">
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn ghost" type="button" data-add-option><span class="material-symbols-outlined">add_circle</span> Thêm đáp án</button>
      </div>
      <div class="question-type-panel" data-panel="tf">
        <?php $tfAnswer = (string)($q['tf_answer'] ?? $q['answer'] ?? ($q['tf_items']['a']['answer'] ?? 'true')); ?>
        <label>Đáp án Đúng/Sai</label>
        <?php $tfItems = normalize_tf_items_array((array)($q['tf_items'] ?? []), $q['difficulty'] ?? 'unknown'); ?>
        <?php if (!$tfItems) $tfItems = [['label'=>'a','content'=>$q['content'] ?? '','answer'=>$tfAnswer,'difficulty'=>$q['difficulty'] ?? 'unknown']]; ?>
        <?php foreach (['a','b','c','d'] as $label): ?>
          <?php $item = []; foreach ($tfItems as $candidate) if (($candidate['label'] ?? '') === $label) $item = $candidate; $itemAnswer = (string)($item['answer'] ?? ($label === 'a' ? $tfAnswer : 'true')); ?>
          <?php $itemContent = (string)($item['content'] ?? ''); ?>
          <div class="option-row">
            <label><?= e($label) ?></label>
            <?php if (question_content_has_inline_images($itemContent)): ?>
              <div class="question-content-render option-render"><?= question_content_html($itemContent) ?></div>
              <input type="hidden" name="q[<?= $i ?>][tf_items][<?= e($label) ?>][content]" value="<?= e($itemContent) ?>">
            <?php else: ?>
              <input name="q[<?= $i ?>][tf_items][<?= e($label) ?>][content]" value="<?= e($itemContent) ?>">
            <?php endif; ?>
            <select name="q[<?= $i ?>][tf_items][<?= e($label) ?>][answer]">
              <option value="true" <?= $itemAnswer !== 'false' ? 'selected' : '' ?>>Đúng</option>
              <option value="false" <?= $itemAnswer === 'false' ? 'selected' : '' ?>>Sai</option>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="question-type-panel" data-panel="mc-answer">
        <label>Đáp án đúng</label>
        <select name="q[<?= $i ?>][answer]">
          <?php foreach (mc_option_labels($q['options'] ?? [], $q['answer'] ?? 'A') as $o): ?><option value="<?= $o ?>" <?= strtoupper((string)($q['answer'] ?? '')) === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="question-type-panel" data-panel="text">
        <label>Đáp án gợi ý / đáp án ngắn</label><input name="q[<?= $i ?>][answer_text]" value="<?= e($q['answer'] ?? '') ?>" placeholder="Đáp án tự luận hoặc trả lời ngắn">
      </div>
      <label>Giải thích</label><textarea name="q[<?= $i ?>][explanation]"><?= e($q['explanation'] ?? '') ?></textarea>
    </div>
  <?php endforeach; ?>
  <button class="btn primary" name="save_preview" value="1"><span class="material-symbols-outlined">save</span> Lưu vào ngân hàng câu hỏi</button>
</form>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
