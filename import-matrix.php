<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
ensure_exam_points_columns();
$page_title = 'Nhập ma trận đặc tả';

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Nhập ma trận đặc tả</h1>
    <p>Chức năng này đang tạm ở chế độ bảo trì. Vui lòng tạo đề thủ công hoặc quay lại sau.</p>
  </div>
  <div class="heading-actions">
    <a class="btn ghost" href="create-exam.php"><span class="material-symbols-outlined">edit_note</span> Tạo đề thủ công</a>
    <a class="btn ghost" href="questions.php"><span class="material-symbols-outlined">database</span> Ngân hàng câu hỏi</a>
  </div>
</div>

<section class="card">
  <div class="section-head">
    <div>
      <h2>Đang bảo trì</h2>
      <p class="muted">Module nhập ma trận đặc tả đang được tạm khóa để bảo trì. Dữ liệu hiện có không bị ảnh hưởng.</p>
    </div>
    <span class="badge medium">Bảo trì</span>
  </div>
  <div class="actions" style="margin-top:14px">
    <a class="btn primary" href="create-exam.php"><span class="material-symbols-outlined">edit_note</span> Tạo đề thủ công</a>
    <a class="btn ghost" href="exams.php"><span class="material-symbols-outlined">assignment</span> Quản lý đề thi</a>
  </div>
</section>
<?php
include __DIR__ . '/includes/footer.php';
exit;

$subjects = fetch_subjects();
$subjectId = (int)post('subject_id', (string)($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0)));
$title = post('title', 'Đề tạo từ ma trận');
$school = post('school_name', 'Trường THPT Test');
$duration = max(1, (int)post('duration', 45));
$totalPoints = max(0.25, (float)post('total_points', 10));
$baseCode = max(1, (int)post('base_code', 101));
$matrixItems = [];
$checkedItems = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_exam'])) {
        $matrixItems = json_decode((string)($_POST['matrix_json'] ?? '[]'), true) ?: [];
        $checkedItems = matrix_availability($matrixItems, $subjectId);
        $hasError = !$checkedItems || count(array_filter($checkedItems, fn($item) => empty($item['ok']))) > 0;
        if ($hasError) {
            flash('Ma trận còn dòng thiếu bài học hoặc thiếu câu hỏi trong ngân hàng.', 'error');
        } else {
            $matrixTotalPoints = array_sum(array_map(fn($item) => (float)($item['points'] ?? 0), $checkedItems));
            $examId = create_exam_from_matrix($subjectId, $title, $school, $duration, $matrixTotalPoints > 0 ? $matrixTotalPoints : $totalPoints, $baseCode, $checkedItems);
            flash('Đã tạo đề thi từ ma trận đặc tả');
            redirect('exam-view.php?id=' . $examId);
        }
    } else {
        $rawRows = matrix_read_upload_rows($_FILES['matrix_file'] ?? []);
        $matrixItems = parse_exam_matrix_rows($rawRows);
        if (!$matrixItems) {
            flash('Không đọc được ma trận. Hãy dùng bảng hoặc dòng có đủ Bài học, Dạng câu, Số câu; có thể thêm Mức độ và Điểm.', 'error');
        } else {
            $checkedItems = matrix_availability($matrixItems, $subjectId);
        }
    }
}

$totalRequested = array_sum(array_map(fn($item) => (int)($item['count'] ?? 0), $checkedItems));
$totalMatrixPoints = array_sum(array_map(fn($item) => (float)($item['points'] ?? 0), $checkedItems));
$canCreate = $checkedItems && count(array_filter($checkedItems, fn($item) => empty($item['ok']))) === 0;

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Nhập ma trận đặc tả</h1>
    <p>Upload file TXT/DOCX/PDF để hệ thống tự chọn câu hỏi từ ngân hàng và tạo đề theo đúng cấu trúc ma trận.</p>
  </div>
  <div class="heading-actions">
    <a class="btn ghost" href="create-exam.php"><span class="material-symbols-outlined">edit_note</span> Tạo đề thủ công</a>
    <a class="btn ghost" href="questions.php"><span class="material-symbols-outlined">database</span> Ngân hàng câu hỏi</a>
  </div>
</div>

<div class="core-workflow">
  <a class="workflow-step" href="questions.php"><span class="material-symbols-outlined">database</span><strong>Ngân hàng câu hỏi</strong><small>Chuẩn bị dữ liệu</small></a>
  <a class="workflow-step active" href="import-matrix.php"><span class="material-symbols-outlined">upload_file</span><strong>Nhập ma trận</strong><small>Tạo đề tự động</small></a>
  <a class="workflow-step" href="exams.php"><span class="material-symbols-outlined">assignment</span><strong>Quản lý đề thi</strong><small>Xem và giao đề</small></a>
</div>

<div class="grid grid-2">
  <section class="card">
    <h2>Thông tin đề thi</h2>
    <form method="post" enctype="multipart/form-data">
      <div class="form-row">
        <div>
          <label>Môn</label>
          <select name="subject_id" required>
            <?php foreach ($subjects as $subject): ?>
              <option value="<?= e($subject['id']) ?>" <?= $subjectId === (int)$subject['id'] ? 'selected' : '' ?>><?= e($subject['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Tên đề thi</label>
          <input name="title" required value="<?= e($title) ?>" placeholder="Ví dụ: Kiểm tra giữa kỳ - Toán 12">
        </div>
      </div>
      <div class="form-row">
        <div>
          <label>Tên trường</label>
          <input name="school_name" value="<?= e($school) ?>">
        </div>
        <div>
          <label>Thời gian làm bài</label>
          <input type="number" min="1" name="duration" value="<?= e($duration) ?>">
        </div>
      </div>
      <div class="form-row">
        <div>
          <label>Tổng điểm</label>
          <input type="number" min="0.25" step="0.25" name="total_points" value="<?= e($totalPoints) ?>">
        </div>
        <div>
          <label>Mã đề</label>
          <input type="number" min="1" name="base_code" value="<?= e($baseCode) ?>">
        </div>
      </div>
      <label>File ma trận TXT/DOCX/PDF</label>
      <input type="file" name="matrix_file" accept=".txt,.docx,.pdf,application/pdf" required>
      <div class="actions" style="margin-top:14px">
        <button class="btn primary"><span class="material-symbols-outlined">visibility</span> Đọc ma trận</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2>Định dạng file</h2>
    <p class="muted">Dòng đầu nên là tiêu đề cột. Với TXT, mỗi cột có thể cách nhau bằng tab, dấu |, dấu ; hoặc dấu phẩy. Với DOCX/PDF, nên dùng bảng hoặc dòng văn bản rõ cột.</p>
    <table class="table">
      <tr><th>Cột</th><th>Ví dụ</th></tr>
      <tr><td>Bài học</td><td>Đề 1 - Toán tổng hợp</td></tr>
      <tr><td>Dạng câu</td><td>Trắc nghiệm / Đúng sai / Trả lời ngắn / Tự luận</td></tr>
      <tr><td>Mức độ</td><td>Nhận biết / Thông hiểu / Vận dụng</td></tr>
      <tr><td>Số câu</td><td>5</td></tr>
      <tr><td>Điểm</td><td>2.5</td></tr>
    </table>
  </section>
</div>

<?php if ($checkedItems): ?>
  <form method="post" class="card" style="margin-top:22px">
    <input type="hidden" name="subject_id" value="<?= e($subjectId) ?>">
    <input type="hidden" name="title" value="<?= e($title) ?>">
    <input type="hidden" name="school_name" value="<?= e($school) ?>">
    <input type="hidden" name="duration" value="<?= e($duration) ?>">
    <input type="hidden" name="total_points" value="<?= e($totalPoints) ?>">
    <input type="hidden" name="base_code" value="<?= e($baseCode) ?>">
    <input type="hidden" name="matrix_json" value="<?= e(json_encode($matrixItems, JSON_UNESCAPED_UNICODE)) ?>">
    <div class="section-head">
      <div>
        <h2>Preview ma trận</h2>
        <p class="muted"><?= e($totalRequested) ?> câu cần lấy<?= $totalMatrixPoints > 0 ? ' · ' . e($totalMatrixPoints) . ' điểm theo ma trận' : '' ?>.</p>
      </div>
      <button class="btn primary" name="create_exam" value="1" <?= $canCreate ? '' : 'disabled' ?>><span class="material-symbols-outlined">edit_note</span> Tạo đề từ ma trận</button>
    </div>
    <div class="table-scroll">
      <table class="table">
        <tr>
          <th>Bài học</th>
          <th>Dạng câu</th>
          <th>Mức độ</th>
          <th>Số câu</th>
          <th>Có sẵn</th>
          <th>Điểm</th>
          <th>Trạng thái</th>
        </tr>
        <?php foreach ($checkedItems as $item): ?>
          <tr>
            <td><?= e($item['lesson_name']) ?></td>
            <td><?= e(type_label($item['type'])) ?></td>
            <td><?= e(difficulty_label($item['difficulty'])) ?></td>
            <td><?= e($item['count']) ?></td>
            <td><?= e($item['available']) ?></td>
            <td><?= e($item['points'] > 0 ? $item['points'] : 'Tự chia') ?></td>
            <td>
              <?php if (!$item['lesson_id']): ?>
                <span class="badge hard">Không tìm thấy bài học</span>
              <?php elseif (!$item['ok']): ?>
                <span class="badge hard">Thiếu câu hỏi</span>
              <?php else: ?>
                <span class="badge easy">Đủ dữ liệu</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php if (!$canCreate): ?><p class="muted">Hãy bổ sung câu hỏi hoặc chỉnh tên bài học trong file ma trận cho khớp với ngân hàng câu hỏi trước khi tạo đề.</p><?php endif; ?>
  </form>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
