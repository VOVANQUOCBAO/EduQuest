<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
ensure_question_image_column();
$page_title = 'Tạo câu hỏi bằng AI';

$generated = [];
$subject = (int)post('subject_id', (int)($_GET['subject_id'] ?? 0));
$lesson = (int)post('lesson_id', 0);
$lessonName = post('lesson_name', '');
$type = normalize_generation_type(post('type', 'mixed'));
$difficulty = post('difficulty', 'medium');
$count = max(1, min(200, (int)post('count', 5)));
$sourceText = post('source_text', '');
$sourceMode = post('source_mode', 'subject');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = (int)post('subject_id');
    $lesson = (int)post('lesson_id');
    $lessonName = post('lesson_name', '');
    $type = normalize_generation_type(post('type', 'mixed'));
    $difficulty = post('difficulty', 'medium');
    $count = max(1, min(200, (int)post('count', 5)));
    $sourceText = post('source_text', '');
    $sourceMode = in_array(post('source_mode', 'subject'), ['subject', 'manual'], true) ? post('source_mode', 'subject') : 'subject';

    if (isset($_POST['save_preview'])) {
        $saved = collect_preview_questions($_POST['q'] ?? [], $subject, $lesson, $lessonName);
        flash('Đã lưu ' . $saved . ' câu hỏi vào ngân hàng');
        redirect('questions.php?subject_id=' . $subject);
    }

    $sourceForAi = $sourceMode === 'subject' ? subject_question_corpus($subject) : trim($sourceText);
    if ($sourceMode === 'subject' && trim($sourceText) !== '') {
        $sourceForAi .= "\n\nYeu cau rieng cua giao vien:\n" . trim($sourceText);
    }
    if ($sourceForAi !== '') {
        $items = $sourceMode === 'subject'
            ? gemini_generate_similar_questions($sourceForAi, $type, $count, $difficulty)
            : gemini_generate_questions($sourceForAi, $type, $count, $difficulty);
        $generated = finalize_import_preview_questions($items, $sourceForAi);
    } else {
        flash('Môn này chưa có dữ liệu đề/câu hỏi để AI học theo. Hãy nhập đề vào ngân hàng trước hoặc chọn chế độ nhập nội dung thủ công.', 'error');
    }
}

$subjects = fetch_subjects();
$lessons = fetch_lessons_with_questions();
include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <h2>Tạo câu hỏi bằng AI</h2>
  <form method="post">
    <div class="form-row">
      <div>
        <label>Môn</label>
        <select name="subject_id" data-subject-filter="generate-lessons" required>
          <?php foreach ($subjects as $s): ?><option value="<?= e($s['id']) ?>" <?= $subject === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Bài học có sẵn</label>
        <select name="lesson_id" data-lesson-filter="generate-lessons">
          <?php foreach ($lessons as $l): ?><option value="<?= e($l['id']) ?>" data-subject-id="<?= e($l['subject_id']) ?>" <?= $lesson === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['subject_name'] . ' - ' . $l['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <label>Tên bài mới nếu muốn tạo riêng</label>
    <input name="lesson_name" value="<?= e($lessonName) ?>">
    <div class="form-row">
      <div>
        <label>Dạng câu hỏi</label>
        <select name="type">
          <?php foreach (['mixed' => 'Tổng hợp', 'mc' => 'Trắc nghiệm', 'tf' => 'Đúng/Sai', 'sa' => 'Trả lời ngắn', 'essay' => 'Tự luận'] as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Độ khó</label>
        <select name="difficulty"><?php foreach (['easy' => 'Nhận biết', 'medium' => 'Thông hiểu', 'hard' => 'Vận dụng'] as $k => $v): ?><option value="<?= $k ?>" <?= $difficulty === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <label>Nguồn dữ liệu tạo câu</label>
    <select name="source_mode">
      <option value="subject" <?= $sourceMode === 'subject' ? 'selected' : '' ?>>Tự lấy dữ liệu đề/câu hỏi đang có của môn đã chọn</option>
      <option value="manual" <?= $sourceMode === 'manual' ? 'selected' : '' ?>>Nhập nội dung thủ công</option>
    </select>
    <label>Nội dung bổ sung / yêu cầu riêng</label>
    <textarea name="source_text" placeholder="Có thể để trống nếu dùng dữ liệu môn đã chọn"><?= e($sourceText) ?></textarea>
    <div class="form-row">
      <div><label>Số câu</label><input type="number" name="count" min="1" max="200" value="<?= e($count) ?>"></div>
    </div>
    <button class="btn primary"><span class="material-symbols-outlined">auto_awesome</span> Tạo câu hỏi</button>
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
        <button class="btn danger" type="button" data-remove-preview-question><span class="material-symbols-outlined">delete</span> Xóa câu</button>
      </div>
      <input type="hidden" name="q[<?= $i ?>][image_path]" value="<?= e($q['image_path'] ?? '') ?>">
      <div class="question-content-field">
        <?php if (question_content_has_inline_images($q['content'] ?? '')): ?>
          <label>Nội dung</label>
          <div class="question-content-render"><?= question_content_html($q['content'] ?? '') ?></div>
          <input type="hidden" name="q[<?= $i ?>][content]" value="<?= e($q['content'] ?? '') ?>">
        <?php else: ?>
          <label>Nội dung</label><textarea name="q[<?= $i ?>][content]"><?= e($q['content'] ?? '') ?></textarea>
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
