<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin']);
$page_title = 'Sao lưu dữ liệu';

if (isset($_POST['backup'])) {
    $data = [];
    foreach (['users','subjects','lessons','questions','question_options','true_false_items','exams','exam_questions','attempts','attempt_answers','activity_logs'] as $table) {
        $data[$table] = db()->query("SELECT * FROM $table")->fetchAll();
    }
    log_activity('export', 'backup', null, 'Đã tải backup JSON dữ liệu hệ thống');
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup-edusystem-' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading">
  <div>
    <h1>Sao lưu dữ liệu</h1>
    <p>Tải dữ liệu chính của hệ thống ra file JSON để lưu trữ hoặc kiểm tra khi cần.</p>
  </div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2>Sao lưu</h2>
    <p class="muted">Xuất toàn bộ dữ liệu chính hiện tại ra file JSON.</p>
    <form method="post">
      <button class="btn primary" name="backup" value="1"><span class="material-symbols-outlined">download</span> Tải backup JSON</button>
    </form>
  </div>
  <div class="card">
    <h2>Database SQL</h2>
    <p>File cấu trúc database nằm tại <code>setup.sql</code>. Import file này trong phpMyAdmin trước khi chạy website trên môi trường mới.</p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
