<?php
$active = basename($_SERVER['PHP_SELF']);
$role = current_user()['role'];

$sections = [
    'main' => [
        'label' => 'Chức năng chính',
        'items' => [
            ['questions.php', 'database', 'Ngân hàng câu hỏi', ['admin', 'teacher']],
            ['create-exam.php', 'edit_note', 'Tạo đề thi', ['admin', 'teacher']],
            ['exams.php', 'assignment', 'Quản lý đề thi', ['admin', 'teacher']],
        ],
    ],
    'delivery' => [
        'label' => 'Giao và làm bài',
        'items' => [
            ['assignments.php', 'send', 'Giao đề thi', ['admin', 'teacher']],
            ['online-test.php', 'quiz', 'Thi trực tuyến', ['admin', 'teacher', 'student']],
            ['results.php', 'analytics', 'Kết quả', ['admin', 'teacher', 'student']],
            ['grade-essay.php', 'rate_review', 'Chấm tự luận', ['admin', 'teacher']],
        ],
    ],
    'tools' => [
        'label' => 'Công cụ',
        'items' => [
            ['import-questions.php', 'cloud_upload', 'Nhập câu hỏi từ file', ['admin', 'teacher']],
            ['import-matrix.php', 'construction', 'Nhập ma trận đặc tả (Bảo trì)', ['admin', 'teacher']],
            ['subjects.php', 'auto_stories', 'Môn học', ['admin', 'teacher']],
            ['lessons.php', 'menu_book', 'Bài học', ['admin', 'teacher']],
        ],
    ],
    'system' => [
        'label' => 'Hệ thống',
        'items' => [
            ['admin-dashboard.php', 'admin_panel_settings', 'Admin Dashboard', ['admin']],
            ['dashboard.php', 'dashboard', 'Dashboard', ['teacher', 'student']],
            ['users.php', 'manage_accounts', 'Quản lý tài khoản', ['admin', 'teacher']],
            ['notifications.php', 'notifications', 'Thông báo', ['admin', 'teacher']],
            ['backups.php', 'backup', 'Sao lưu dữ liệu', ['admin']],
        ],
    ],
];

foreach ($sections as $section) {
    $visibleItems = array_values(array_filter($section['items'], fn($item) => in_array($role, $item[3], true)));
    if (!$visibleItems) {
        continue;
    }
    echo '<div class="nav-section"><span class="nav-section-label">' . e($section['label']) . '</span>';
    foreach ($visibleItems as $item) {
        $cls = $active === $item[0] ? 'active' : '';
        echo '<a class="nav ' . $cls . '" href="' . $item[0] . '" data-label="' . e($item[2]) . '"><span class="material-symbols-outlined">' . $item[1] . '</span><span class="nav-label">' . e($item[2]) . '</span></a>';
    }
    echo '</div>';
}
?>
