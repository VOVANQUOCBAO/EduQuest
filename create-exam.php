<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
ensure_exam_points_columns();
$page_title = 'Tạo đề thi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = (int)post('subject_id');
    $title = post('title');
    $school = post('school_name', 'Trường THPT');
    $duration = max(1, (int)post('duration', 45));
    $totalPoints = max(0.25, (float)post('total_points', 10));
    $versions = max(1, (int)post('versions', 1));
    $baseCode = max(1, (int)post('base_code', 101));
    $examMode = post('exam_mode', 'mixed');
    $questionTypes = ['mc', 'tf', 'sa', 'essay'];
    $activeTypes = in_array($examMode, $questionTypes, true) ? [$examMode] : $questionTypes;
    $counts = $_POST['count'] ?? [];
    $pdo = db();

    if ($subject <= 0 || $title === '') {
        flash('Vui lòng chọn môn học và nhập tên đề thi.', 'error');
        redirect('create-exam.php');
    }

    $requested = 0;
    foreach ($counts as $parts) {
        foreach ($parts as $type => $count) {
            if (!in_array((string)$type, $activeTypes, true)) {
                continue;
            }
            $requested += max(0, (int)$count);
        }
    }
    if ($requested <= 0) {
        flash('Vui lòng chọn ít nhất một câu hỏi từ ngân hàng để tạo đề.', 'error');
        redirect('create-exam.php?subject_id=' . $subject);
    }

    $created = 0;
    for ($v = 0; $v < $versions; $v++) {
        $code = (string)($baseCode + $v);
        $versionTitle = $versions > 1 ? $title . ' - Mã ' . $code : $title;
        $pdo->prepare('INSERT INTO exams(code,title,school_name,subject_id,duration,total_points,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$code, $versionTitle, $school, $subject, $duration, $totalPoints, 'draft', current_user()['id']]);
        $examId = (int)$pdo->lastInsertId();
        $selectedQuestions = [];

        foreach ($counts as $lessonId => $parts) {
            foreach ($parts as $type => $count) {
                $count = (int)$count;
                if ($count <= 0 || !in_array($type, $activeTypes, true)) {
                    continue;
                }
                $st = $pdo->prepare('SELECT id FROM questions WHERE subject_id=? AND lesson_id=? AND type=? ORDER BY RAND() LIMIT ' . $count);
                $st->execute([$subject, (int)$lessonId, $type]);
                foreach ($st->fetchAll() as $q) {
                    $selectedQuestions[] = (int)$q['id'];
                }
            }
        }

        if (!$selectedQuestions) {
            $pdo->prepare('DELETE FROM exams WHERE id=?')->execute([$examId]);
            continue;
        }
        $points = distribute_exam_points($totalPoints, count($selectedQuestions));
        $ins = $pdo->prepare('INSERT INTO exam_questions(exam_id,question_id,position,points) VALUES(?,?,?,?)');
        foreach ($selectedQuestions as $index => $questionId) {
            $ins->execute([$examId, $questionId, $index + 1, $points[$index] ?? 0]);
        }
        log_activity('create', 'exam', $examId, 'Đã tạo đề thi: ' . $versionTitle);
        $created++;
    }

    if ($created === 0) {
        flash('Ngân hàng câu hỏi chưa đủ dữ liệu theo lựa chọn của bạn.', 'error');
        redirect('create-exam.php?subject_id=' . $subject);
    }

    flash('Đã tạo ' . $created . ' đề thi và lưu vào database.');
    redirect('exams.php');
}

$subjects = fetch_subjects();
$selectedSubject = (int)($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0));
$lessons = $selectedSubject > 0 ? fetch_lessons($selectedSubject) : [];

$availability = [];
if ($selectedSubject > 0) {
    $st = db()->prepare('SELECT lesson_id,type,COUNT(*) total FROM questions WHERE subject_id=? GROUP BY lesson_id,type');
    $st->execute([$selectedSubject]);
    foreach ($st->fetchAll() as $row) {
        $availability[(int)$row['lesson_id']][$row['type']] = (int)$row['total'];
    }
}

$subjectTotals = [];
if ($subjects) {
    $rows = db()->query('SELECT s.id,s.name,COUNT(q.id) total
        FROM subjects s
        LEFT JOIN questions q ON q.subject_id=s.id
        GROUP BY s.id
        ORDER BY s.name')->fetchAll();
    foreach ($rows as $row) {
        $subjectTotals[(int)$row['id']] = (int)$row['total'];
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Tạo đề thi</h1>
    <p>Chọn môn, xem dữ liệu trong ngân hàng câu hỏi rồi tạo đề và lưu vào database.</p>
  </div>
  <div class="heading-actions">
    <a class="btn ghost" href="questions.php"><span class="material-symbols-outlined">database</span> Ngân hàng câu hỏi</a>
    <a class="btn ghost" href="exams.php"><span class="material-symbols-outlined">assignment</span> Quản lý đề</a>
  </div>
</div>

<div class="core-workflow">
  <a class="workflow-step" href="questions.php"><span class="material-symbols-outlined">database</span><strong>Ngân hàng câu hỏi</strong><small>Lưu bộ câu hỏi theo môn/bài</small></a>
  <a class="workflow-step active" href="create-exam.php"><span class="material-symbols-outlined">edit_note</span><strong>Tạo đề thi</strong><small>Lấy câu hỏi từ ngân hàng</small></a>
  <a class="workflow-step" href="exams.php"><span class="material-symbols-outlined">assignment</span><strong>Quản lý đề thi</strong><small>Sửa, xuất file và giao đề</small></a>
</div>

<div class="card">
  <div class="section-head">
    <div>
      <h2>Lọc ngân hàng theo môn</h2>
      <p class="muted">Chọn môn trước để chỉ hiện các bài và số câu hiện có của môn đó.</p>
    </div>
  </div>
  <form class="assignment-filter" method="get">
    <select name="subject_id">
      <?php foreach ($subjects as $subject): ?>
        <option value="<?= e($subject['id']) ?>" <?= $selectedSubject === (int)$subject['id'] ? 'selected' : '' ?>>
          <?= e($subject['name']) ?> (<?= e($subjectTotals[(int)$subject['id']] ?? 0) ?> câu)
        </option>
      <?php endforeach; ?>
    </select>
    <button class="btn"><span class="material-symbols-outlined">filter_alt</span> Lọc môn</button>
    <a class="btn ghost" href="import-matrix.php?subject_id=<?= e($selectedSubject) ?>"><span class="material-symbols-outlined">construction</span> Nhập ma trận (Bảo trì)</a>
    <a class="btn ghost" href="import-questions.php"><span class="material-symbols-outlined">cloud_upload</span> Nhập câu hỏi</a>
    <a class="btn ghost" href="generate-questions.php"><span class="material-symbols-outlined">psychology</span> Tạo bằng AI</a>
  </form>
</div>

<div class="card">
  <form method="post">
    <input type="hidden" name="subject_id" value="<?= e($selectedSubject) ?>">
    <div class="form-row">
      <div>
        <label>Tên đề thi</label>
        <input name="title" required placeholder="Ví dụ: Kiểm tra giữa kỳ II - Toán 12">
      </div>
      <div>
        <label>Tên trường</label>
        <input name="school_name" value="Trường THPT Test">
      </div>
    </div>
    <div class="form-row">
      <div>
        <label>Môn đang chọn</label>
        <select disabled>
          <?php foreach ($subjects as $subject): ?>
            <option <?= $selectedSubject === (int)$subject['id'] ? 'selected' : '' ?>><?= e($subject['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Thời gian làm bài</label>
        <input type="number" name="duration" min="1" value="45">
      </div>
    </div>
    <div class="form-row">
      <div>
        <label>T&#7893;ng &#273;i&#7875;m</label>
        <input type="number" name="total_points" min="0.25" step="0.25" value="10">
      </div>
      <div>
        <label>C&#225;ch chia &#273;i&#7875;m</label>
        <input value="T&#7921; chia &#273;&#7873;u theo s&#7889; c&#226;u &#273;&#227; ch&#7885;n" disabled>
      </div>
    </div>
    <div class="form-row">
      <div>
        <label>Phương thức đề</label>
        <select name="exam_mode" data-exam-mode>
          <option value="mixed" selected>Tổng hợp nhiều dạng câu</option>
          <option value="mc">Chỉ trắc nghiệm</option>
          <option value="tf">Chỉ đúng/sai</option>
          <option value="sa">Chỉ trả lời ngắn</option>
          <option value="essay">Chỉ tự luận</option>
        </select>
      </div>
      <div>
        <label>Cách chọn câu</label>
        <input value="Nhập số câu theo từng bài học bên dưới" disabled>
      </div>
    </div>
    <div class="form-row">
      <div>
        <label>Số lượng mã đề</label>
        <input type="number" name="versions" min="1" value="1">
      </div>
      <div>
        <label>Mã đề bắt đầu</label>
        <input type="number" name="base_code" min="1" value="101">
      </div>
    </div>

    <h3>Chọn số câu theo bài học</h3>
    <p class="muted">Số nhỏ trong mỗi ô là số câu hiện có trong database. Chọn “Tổng hợp” nếu muốn một đề có nhiều dạng như trắc nghiệm, đúng/sai và tự luận.</p>
    <div class="actions" style="margin-bottom:12px">
      <button class="btn ghost" type="button" data-fill-all-questions><span class="material-symbols-outlined">select_all</span> Chọn tất cả câu hiện có</button>
      <button class="btn ghost" type="button" data-clear-all-questions><span class="material-symbols-outlined">backspace</span> Bỏ chọn</button>
    </div>
    <div class="table-scroll">
      <table class="table exam-builder-table" data-exam-builder-table>
        <tr>
          <th>Bài học</th>
          <th data-exam-type="mc">Trắc nghiệm</th>
          <th data-exam-type="tf">Đúng/Sai</th>
          <th data-exam-type="sa">Trả lời ngắn</th>
          <th data-exam-type="essay">Tự luận</th>
        </tr>
        <?php foreach ($lessons as $lesson): ?>
          <tr>
            <td><strong><?= e($lesson['name']) ?></strong><br><span class="muted"><?= e($lesson['subject_name']) ?></span></td>
            <?php foreach (['mc' => 'MC', 'tf' => 'TF', 'sa' => 'SA', 'essay' => 'TL'] as $type => $short): $available = $availability[(int)$lesson['id']][$type] ?? 0; ?>
              <td data-exam-type="<?= e($type) ?>">
                <div class="exam-count-cell">
                  <input type="number" min="0" max="<?= e($available) ?>" value="0" name="count[<?= e($lesson['id']) ?>][<?= e($type) ?>]">
                  <span><?= e($available) ?> có sẵn</span>
                </div>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$lessons): ?>
          <tr><td colspan="5" class="muted">Môn này chưa có bài học/câu hỏi. Hãy thêm dữ liệu ở Ngân hàng câu hỏi trước.</td></tr>
        <?php endif; ?>
      </table>
    </div>

    <div class="actions" style="margin-top:16px">
      <button class="btn primary"><span class="material-symbols-outlined">edit_note</span> Tạo và lưu đề</button>
      <a class="btn ghost" href="exams.php">Xem đề đã tạo</a>
    </div>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
