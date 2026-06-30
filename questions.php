<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
ensure_question_image_column();
$page_title = 'Ngân hàng câu hỏi';

if (isset($_GET['delete_lesson'])) {
    $lessonId = (int)$_GET['delete_lesson'];
    $st = db()->prepare('SELECT name FROM lessons WHERE id=?');
    $st->execute([$lessonId]);
    $lessonName = (string)$st->fetchColumn();
    db()->prepare('DELETE FROM lessons WHERE id=?')->execute([$lessonId]);
    log_activity('delete', 'lesson', $lessonId, 'Đã xóa bài học: ' . ($lessonName ?: ('#' . $lessonId)));
    flash('Đã xóa bài học và các câu hỏi thuộc bài đó');
    redirect('questions.php');
}

if (isset($_GET['delete'])) {
    $questionId = (int)$_GET['delete'];
    $st = db()->prepare('SELECT content FROM questions WHERE id=?');
    $st->execute([$questionId]);
    $content = (string)$st->fetchColumn();
    db()->prepare('DELETE FROM questions WHERE id=?')->execute([$questionId]);
    log_activity('delete', 'question', $questionId, 'Đã xóa câu hỏi: ' . mb_strimwidth($content, 0, 80, '...'));
    flash('Đã xóa câu hỏi');
    redirect('questions.php');
}

$edit = null;
$editOptions = [];
$editTf = [];
if (isset($_GET['edit'])) {
    $edit = get_question((int)$_GET['edit']);
    if ($edit) {
        foreach (question_options((int)$edit['id']) as $op) {
            $editOptions[$op['label']] = $op['content'];
        }
        foreach (tf_items((int)$edit['id']) as $it) {
            $editTf[$it['label']] = $it;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_lesson'])) {
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        $subjectId = (int)post('lesson_subject_id');
        $name = post('lesson_name');
        if ($subjectId <= 0 || $name === '') {
            flash('Vui lòng chọn môn và nhập tên bài học.', 'error');
            redirect('questions.php');
        }
        if ($lessonId > 0) {
            db()->prepare('UPDATE lessons SET subject_id=?,name=? WHERE id=?')->execute([$subjectId, $name, $lessonId]);
            log_activity('update', 'lesson', $lessonId, 'Đã cập nhật bài học: ' . $name);
            flash('Đã cập nhật tên bài học');
        } else {
            db()->prepare('INSERT INTO lessons(subject_id,name,created_by) VALUES(?,?,?)')->execute([$subjectId, $name, current_user()['id']]);
            log_activity('create', 'lesson', (int)db()->lastInsertId(), 'Đã tạo bài học mới: ' . $name);
            flash('Đã tạo bài học mới');
        }
        redirect('questions.php?subject_id=' . $subjectId);
    }

    if (in_array($_POST['type'] ?? '', ['sa', 'essay'], true)) {
        $_POST['answer'] = $_POST['answer_text'] ?? '';
    }

    if (($_POST['lesson_id'] ?? '') === '__new__' && trim($_POST['lesson_name'] ?? '') === '') {
        flash('Vui lòng nhập tên đề mới.', 'error');
        redirect('questions.php');
    }

    [$data, $options, $tfItems] = question_form_payload($_POST);
    $data['image_path'] = save_question_image_upload($_FILES['image_file'] ?? [], $data['image_path'] ?? null);
    if (!empty($_POST['id'])) {
        update_question((int)$_POST['id'], $data, $options, $tfItems);
        flash('Đã cập nhật câu hỏi');
    } else {
        create_question($data, $options, $tfItems);
        flash('Đã thêm câu hỏi');
    }
    redirect('questions.php');
}

$subjects = fetch_subjects();
$lessons = fetch_lessons();
$lessonEdit = null;
if (isset($_GET['lesson_edit'])) {
    $st = db()->prepare('SELECT * FROM lessons WHERE id=?');
    $st->execute([(int)$_GET['lesson_edit']]);
    $lessonEdit = $st->fetch() ?: null;
}

$where = [];
$params = [];
if (isset($_GET['subject_id']) && $_GET['subject_id'] !== '') {
    $where[] = 'q.subject_id=?';
    $params[] = (int)$_GET['subject_id'];
}
if (isset($_GET['type']) && $_GET['type'] !== '' && $_GET['type'] !== 'mixed') {
    $where[] = 'q.type=?';
    $params[] = $_GET['type'];
}

$sql = 'SELECT q.*, s.name subject_name, l.name lesson_name
        FROM questions q
        JOIN subjects s ON s.id = q.subject_id
        JOIN lessons l ON l.id = q.lesson_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY s.name ASC, l.name ASC, q.created_at DESC LIMIT 300';
$st = db()->prepare($sql);
$st->execute($params);
$questions = $st->fetchAll();

$groupedQuestions = [];
foreach ($questions as $question) {
    $subjectId = (int)$question['subject_id'];
    $lessonId = (int)$question['lesson_id'];

    if (!isset($groupedQuestions[$subjectId])) {
        $groupedQuestions[$subjectId] = [
            'name' => $question['subject_name'],
            'count' => 0,
            'lessons' => [],
        ];
    }
    if (!isset($groupedQuestions[$subjectId]['lessons'][$lessonId])) {
        $groupedQuestions[$subjectId]['lessons'][$lessonId] = [
            'id' => $lessonId,
            'subject_id' => $subjectId,
            'name' => $question['lesson_name'],
            'count' => 0,
            'questions' => [],
        ];
    }

    $groupedQuestions[$subjectId]['count']++;
    $groupedQuestions[$subjectId]['lessons'][$lessonId]['count']++;
    $groupedQuestions[$subjectId]['lessons'][$lessonId]['questions'][] = $question;
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Ngân hàng câu hỏi</h1>
    <p>Lưu bộ câu hỏi theo môn và bài học để dùng lại khi tạo đề thi, giao đề cho giáo viên và học sinh.</p>
  </div>
  <div class="heading-actions">
    <a class="btn ghost" href="import-questions.php"><span class="material-symbols-outlined">cloud_upload</span> Nhập từ file</a>
    <a class="btn ghost" href="generate-questions.php"><span class="material-symbols-outlined">psychology</span> Tạo bằng AI</a>
    <a class="btn ghost" href="create-exam.php"><span class="material-symbols-outlined">edit_note</span> Tạo đề</a>
  </div>
</div>

<div class="core-workflow">
  <a class="workflow-step active" href="questions.php"><span class="material-symbols-outlined">database</span><strong>Ngân hàng câu hỏi</strong><small>Lưu câu hỏi vào database</small></a>
  <a class="workflow-step" href="create-exam.php"><span class="material-symbols-outlined">edit_note</span><strong>Tạo đề thi</strong><small>Chọn câu từ ngân hàng</small></a>
  <a class="workflow-step" href="exams.php"><span class="material-symbols-outlined">assignment</span><strong>Quản lý đề thi</strong><small>Sửa, xóa, giao đề</small></a>
</div>

<div class="grid grid-2">
  <div class="card" id="lesson-form">
    <h2><?= $lessonEdit ? 'Sửa tên bài học' : 'Tạo bài học mới' ?></h2>
    <form method="post">
      <input type="hidden" name="lesson_id" value="<?= e($lessonEdit['id'] ?? 0) ?>">
      <label>Môn học</label>
      <select name="lesson_subject_id" required>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= e($s['id']) ?>" <?= (int)($lessonEdit['subject_id'] ?? ($_GET['subject_id'] ?? 0)) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Tên bài / chủ đề</label>
      <input name="lesson_name" required value="<?= e($lessonEdit['name'] ?? '') ?>" placeholder="Ví dụ: Hàm số bậc nhất">
      <div class="actions" style="margin-top:12px">
        <button class="btn primary" name="save_lesson" value="1"><span class="material-symbols-outlined">save</span> <?= $lessonEdit ? 'Lưu tên bài' : 'Tạo bài học' ?></button>
        <?php if ($lessonEdit): ?><a class="btn ghost" href="questions.php">Hủy</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h2><?= $edit ? 'Sửa câu hỏi' : 'Thêm câu hỏi' ?></h2>
    <form method="post" class="question-editor" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">
      <input type="hidden" name="image_path" value="<?= e($edit['image_path'] ?? '') ?>">
      <div class="form-row">
        <div>
          <label>Môn</label>
          <select name="subject_id" data-subject-filter="question-editor-lessons" required>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= e($s['id']) ?>" <?= ($edit['subject_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Bài học</label>
          <select name="lesson_id" data-lesson-filter="question-editor-lessons" data-empty-label="Mon nay chua co bai hoc" required>
            <?php foreach ($lessons as $l): ?>
              <option value="<?= e($l['id']) ?>" data-subject-id="<?= e($l['subject_id']) ?>" <?= ($edit['lesson_id'] ?? '') == $l['id'] ? 'selected' : '' ?>><?= e($l['subject_name'] . ' - ' . $l['name']) ?></option>
            <?php endforeach; ?>
            <option value="__new__" data-new-lesson-option>+ Tạo thêm đề</option>
          </select>
          <div class="new-lesson-field" data-new-lesson-field>
            <label class="inline-create-label">Nhập tên đề</label>
            <input name="lesson_name" placeholder="Ví dụ: Đề 2 - Toán tổng hợp">
          </div>
        </div>
      </div>
      <div class="form-row">
        <div>
          <label>Dạng câu</label>
          <select name="type" data-question-type>
            <option value="mixed" <?= ($edit['type'] ?? '') === 'mixed' ? 'selected' : '' ?>>Tổng hợp</option>
            <?php foreach (['mc' => 'Trắc nghiệm', 'tf' => 'Đúng/Sai', 'sa' => 'Trả lời ngắn', 'essay' => 'Tự luận'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($edit['type'] ?? 'mc') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Độ khó</label>
          <select name="difficulty">
            <?php foreach (['easy' => 'Nhận biết', 'medium' => 'Thông hiểu', 'hard' => 'Vận dụng', 'unknown' => 'Không rõ'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= ($edit['difficulty'] ?? 'medium') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <label>Nội dung câu hỏi</label>
      <div class="question-content-field">
        <?php if (question_content_has_inline_images($edit['content'] ?? '')): ?>
          <div class="question-content-render"><?= question_content_html($edit['content'] ?? '') ?></div>
          <input type="hidden" name="content" value="<?= e($edit['content'] ?? '') ?>">
        <?php else: ?>
          <textarea name="content" required><?= e($edit['content'] ?? '') ?></textarea>
        <?php endif; ?>
        <?= question_image_tag($edit['image_path'] ?? '') ?>
      </div>
      <label>Hinh anh kem cau hoi</label>
      <input type="file" name="image_file" accept="image/png,image/jpeg,image/webp,image/gif">
      <div class="grid grid-2 question-type-panel" data-panel="mc">
        <div>
          <h3>Đáp án trắc nghiệm</h3>
          <div data-option-list data-option-name-template="options[__LABEL__]" data-answer-select="select[name='answer']">
            <?php foreach (mc_option_labels($editOptions, $edit['answer'] ?? 'A') as $o): ?>
              <div class="option-row" data-option-label="<?= e($o) ?>">
                <label><?= $o ?></label>
                <input name="options[<?= $o ?>]" value="<?= e($editOptions[$o] ?? '') ?>">
              </div>
            <?php endforeach; ?>
          </div>
          <button class="btn ghost" type="button" data-add-option><span class="material-symbols-outlined">add_circle</span> Thêm đáp án</button>
        </div>
        <div>
          <h3>Đáp án đúng</h3>
          <select name="answer">
            <?php foreach (mc_option_labels($editOptions, $edit['answer'] ?? 'A') as $o): ?>
              <option value="<?= $o ?>" <?= strtoupper($edit['answer'] ?? 'A') === $o ? 'selected' : '' ?>><?= $o ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="question-type-panel" data-panel="tf">
        <?php $tfAnswer = (string)($edit['answer'] ?? ($editTf['a']['answer'] ?? 'true')); ?>
        <h3>Đáp án Đúng/Sai</h3>
        <select name="tf_answer">
          <option value="true" <?= $tfAnswer !== 'false' ? 'selected' : '' ?>>Đúng</option>
          <option value="false" <?= $tfAnswer === 'false' ? 'selected' : '' ?>>Sai</option>
        </select>
      </div>
      <div class="question-type-panel" data-panel="text">
        <label>Đáp án gợi ý / đáp án ngắn</label>
        <textarea name="answer_text" data-answer-text><?= e(in_array($edit['type'] ?? '', ['sa', 'essay'], true) ? ($edit['answer'] ?? '') : '') ?></textarea>
      </div>
      <label>Giải thích</label>
      <textarea name="explanation"><?= e($edit['explanation'] ?? '') ?></textarea>
      <div class="actions">
        <button class="btn primary"><?= $edit ? 'Cập nhật câu hỏi' : 'Lưu câu hỏi' ?></button>
        <?php if ($edit): ?><a class="btn ghost" href="questions.php">Hủy sửa</a><?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card question-browser">
  <div class="question-browser-head">
    <div>
      <h2>Danh sách câu hỏi</h2>
      <p class="muted">Câu hỏi được nhóm theo từng môn. Mở môn để xem các bài, sửa tên bài hoặc xóa bài nếu nhập nhầm.</p>
    </div>
    <form class="question-filter">
      <select name="subject_id">
        <option value="">Tất cả môn</option>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= e($s['id']) ?>" <?= ($_GET['subject_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="type">
        <option value="" <?= ($_GET['type'] ?? '') === '' ? 'selected' : '' ?>>Tất cả dạng</option>
        <option value="mixed" <?= ($_GET['type'] ?? '') === 'mixed' ? 'selected' : '' ?>>Tổng hợp</option>
        <option value="">Tất cả dạng</option>
        <?php foreach (['mc' => 'Trắc nghiệm', 'tf' => 'Đúng/Sai', 'sa' => 'Trả lời ngắn', 'essay' => 'Tự luận'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= ($_GET['type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn">Lọc</button>
    </form>
  </div>

  <?php if (!$groupedQuestions): ?>
    <div class="empty-state">
      <span class="material-symbols-outlined">quiz</span>
      <strong>Chưa có câu hỏi phù hợp</strong>
      <p class="muted">Thử đổi bộ lọc hoặc thêm câu hỏi mới vào ngân hàng.</p>
    </div>
  <?php else: ?>
    <div class="subject-accordion">
      <?php foreach ($groupedQuestions as $subject): ?>
        <section class="subject-group">
          <button class="subject-toggle" type="button" data-accordion-toggle>
            <span class="material-symbols-outlined">menu_book</span>
            <span class="subject-title"><?= e($subject['name']) ?></span>
            <span class="group-count"><?= e($subject['count']) ?> câu hỏi</span>
            <span class="material-symbols-outlined chevron">expand_more</span>
          </button>
          <div class="subject-body">
            <?php foreach ($subject['lessons'] as $lesson): ?>
              <section class="lesson-group">
                <button class="lesson-toggle" type="button" data-accordion-toggle>
                  <span class="material-symbols-outlined">article</span>
                  <span><?= e($lesson['name']) ?></span>
                  <span class="group-count"><?= e($lesson['count']) ?> câu</span>
                  <span class="material-symbols-outlined chevron">expand_more</span>
                </button>
                <div class="lesson-body">
                  <div class="lesson-actions">
                    <strong><?= e($lesson['name']) ?></strong>
                    <div class="actions">
                      <a class="btn ghost" href="?lesson_edit=<?= e($lesson['id']) ?>#lesson-form">Sửa tên bài</a>
                      <a class="btn danger" data-confirm="Xóa bài này sẽ xóa toàn bộ câu hỏi thuộc bài. Bạn chắc chắn?" href="?delete_lesson=<?= e($lesson['id']) ?>">Xóa bài</a>
                    </div>
                  </div>
                  <div class="table-scroll">
                    <table class="table question-table">
                      <tr>
                        <th>Nội dung</th>
                        <th>Dạng</th>
                        <th>Độ khó</th>
                        <th>Thao tác</th>
                      </tr>
                      <?php foreach ($lesson['questions'] as $q): ?>
                        <tr>
                          <td><?= e(is_image_question_placeholder($q['content'] ?? '') ? 'Câu hỏi dạng hình ảnh' : mb_strimwidth($q['content'], 0, 150, '...')) ?><?= !empty($q['image_path']) ? ' [hinh]' : '' ?><?= !empty($q['needs_review']) ? ' [can kiem tra]' : '' ?></td>
                          <td><?= type_label($q['type']) ?></td>
                          <td><span class="badge <?= e($q['difficulty']) ?>"><?= difficulty_label($q['difficulty']) ?></span></td>
                          <td class="actions">
                            <a class="btn ghost" href="?edit=<?= e($q['id']) ?>">Sửa</a>
                            <a class="btn danger" data-confirm="Xóa câu hỏi?" href="?delete=<?= e($q['id']) ?>">Xóa</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </table>
                  </div>
                </div>
              </section>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
