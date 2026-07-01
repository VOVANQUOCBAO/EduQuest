<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['admin', 'teacher']);
$page_title = 'Chẩn đoán upload & AI';

/** In 1 dòng trạng thái. */
function diag_row(string $label, bool $ok, string $detail = ''): string {
    $icon = $ok ? '✅' : '❌';
    $color = $ok ? '#166534' : '#991b1b';
    return '<tr><td style="padding:8px;border-bottom:1px solid #eee">' . e($label) . '</td>'
        . '<td style="padding:8px;border-bottom:1px solid #eee;color:' . $color . ';font-weight:700">' . $icon . '</td>'
        . '<td style="padding:8px;border-bottom:1px solid #eee;color:#555">' . e($detail) . '</td></tr>';
}

$rows = '';

// 1. Môi trường
$shared = is_shared_hosting_runtime();
$rows .= diag_row('Nhận diện shared hosting (InfinityFree...)', true, $shared ? 'CÓ → công cụ chạy lệnh ngoài bị tắt' : 'Không → có thể dùng pdftotext/python');

// 2. Công cụ chạy lệnh ngoài (OCR/PDF→ảnh)
$rows .= diag_row('proc_open khả dụng', function_exists('proc_open'), function_exists('proc_open') ? '' : 'Bị vô hiệu hóa (bình thường trên hosting free)');
$rows .= diag_row('can_run_external_import_tools()', can_run_external_import_tools(), can_run_external_import_tools() ? '' : 'PDF/DOCX scan không cắt được ảnh tại chỗ → phải dùng Gemini');

// 3. Extension cần thiết
$rows .= diag_row('cURL extension', function_exists('curl_init'));
$rows .= diag_row('allow_url_fopen (fallback)', filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN), ini_get('allow_url_fopen') ? 'Bật' : 'Tắt');
$rows .= diag_row('ZipArchive (đọc DOCX)', class_exists('ZipArchive'));
$rows .= diag_row('DOMDocument (đọc DOCX XML)', class_exists('DOMDocument'));
$rows .= diag_row('mbstring', function_exists('mb_substr'));
$rows .= diag_row('GD (xử lý ảnh PNG/JPG)', function_exists('imagecreatefromstring'));
$imagickOk = class_exists('Imagick');
$imagickFormats = [];
if ($imagickOk) { try { $imagickFormats = array_merge(Imagick::queryFormats('WMF'), Imagick::queryFormats('EMF')); } catch (\Throwable $e) {} }
$rows .= diag_row('Imagick (chuyển công thức WMF/EMF)', $imagickOk, $imagickOk ? ('Có. Định dạng metafile: ' . (implode(',', array_values($imagickFormats)) ?: 'không khai báo WMF/EMF')) : 'Không có → công thức WMF/EMF sẽ thành ô placeholder');

// 4. Giới hạn upload / thời gian
$rows .= diag_row('upload_max_filesize', true, (string)ini_get('upload_max_filesize'));
$rows .= diag_row('post_max_size', true, (string)ini_get('post_max_size'));
$rows .= diag_row('max_execution_time', true, ini_get('max_execution_time') . 's (Gemini cần ~30-70s)');
$rows .= diag_row('memory_limit', true, (string)ini_get('memory_limit'));

// 5. Gemini key
$key = gemini_api_key();
$rows .= diag_row('Gemini API key đã cấu hình', (bool)$key, $key ? 'Model: ' . gemini_model() : 'Thiếu config/gemini.local.php → KHÔNG đọc được ảnh/scan');

// 6. Thư mục uploads ghi được
$uploadDir = __DIR__ . '/uploads/questions';
$writable = is_dir($uploadDir) ? is_writable($uploadDir) : @mkdir($uploadDir, 0777, true);
$rows .= diag_row('uploads/questions ghi được', (bool)$writable, $uploadDir);

// 7. Test gọi Gemini thật (chỉ khi bấm nút)
$liveResult = null;
if (($_POST['live_test'] ?? '') === '1') {
    if (!$key) {
        $liveResult = ['ok' => false, 'msg' => 'Chưa có API key nên không thể test.'];
    } else {
        $parts = [['text' => 'Reply with a single JSON object: {"questions":[{"type":"mc","content":"1+1=?","options":[{"label":"A","text":"2"},{"label":"B","text":"3"},{"label":"C","text":"4"},{"label":"D","text":"5"}],"answer":"A","difficulty":"easy"}]}']];
        $t0 = microtime(true);
        $resp = gemini_api_generate($parts, $key);
        $ms = round((microtime(true) - $t0) * 1000);
        if ($resp && isset($resp['candidates'])) {
            $liveResult = ['ok' => true, 'msg' => "Gemini phản hồi thành công sau {$ms}ms. Outbound tới Google HOẠT ĐỘNG."];
        } else {
            $liveResult = ['ok' => false, 'msg' => "Thất bại sau {$ms}ms. " . (gemini_last_error() ?: 'Không rõ lỗi.')];
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="card">
  <h2>Chẩn đoán hệ thống upload &amp; AI</h2>
  <p class="muted">Trang này kiểm tra host hiện tại có đủ điều kiện để đọc file (kể cả ảnh/PDF scan) và gọi Gemini hay không. Xoá file này sau khi kiểm tra xong.</p>
  <table style="width:100%;border-collapse:collapse">
    <thead><tr style="text-align:left;background:#f8fafc">
      <th style="padding:8px">Hạng mục</th><th style="padding:8px">Kết quả</th><th style="padding:8px">Chi tiết</th>
    </tr></thead>
    <tbody><?= $rows ?></tbody>
  </table>

  <form method="post" style="margin-top:20px">
    <input type="hidden" name="live_test" value="1">
    <button class="btn primary" type="submit"><span class="material-symbols-outlined">wifi_tethering</span> Test gọi Gemini thật (kiểm tra outbound)</button>
  </form>
  <?php if ($liveResult): ?>
    <div style="margin-top:16px;padding:14px;border-radius:8px;background:<?= $liveResult['ok'] ? '#dcfce7' : '#fee2e2' ?>;color:<?= $liveResult['ok'] ? '#166534' : '#991b1b' ?>">
      <strong><?= $liveResult['ok'] ? 'THÀNH CÔNG' : 'THẤT BẠI' ?>:</strong> <?= e($liveResult['msg']) ?>
    </div>
  <?php endif; ?>

  <div style="margin-top:20px;padding:14px;border-radius:8px;background:#eff6ff;color:#1e3a8a;font-size:14px;line-height:1.6">
    <strong>Cách đọc kết quả:</strong><br>
    • Muốn <b>ảnh / PDF scan → câu hỏi</b> thì bắt buộc: có <b>Gemini key</b> (✅) VÀ nút test outbound ở trên phải <b>THÀNH CÔNG</b>.<br>
    • Nếu test outbound THẤT BẠI với thông báo "host chặn outbound" → InfinityFree gói free đang chặn kết nối ra ngoài, cần đổi host cho phần AI (xem hướng dẫn kèm theo).<br>
    • File TXT/DOCX chữ/PDF có text layer vẫn hoạt động dù không có AI.
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
