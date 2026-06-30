<?php
require_once __DIR__ . '/../config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_logged_in(): bool { return isset($_SESSION['user']); }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function require_login(): void { if (!is_logged_in()) redirect('login.php'); }
function require_role(array $roles): void { require_login(); if (!in_array(current_user()['role'], $roles, true)) { http_response_code(403); exit('Bạn không có quyền truy cập chức năng này.'); } }
function flash(?string $message = null, string $type = 'success'): ?array {
    if ($message !== null) { $_SESSION['flash'] = ['message' => $message, 'type' => $type]; return null; }
    $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash;
}
function post($key, $default = '') { return trim($_POST[$key] ?? $default); }
function difficulty_label($v): string { return ['easy'=>'Nhận biết','medium'=>'Thông hiểu','hard'=>'Vận dụng','unknown'=>'Không rõ'][$v] ?? 'Không rõ'; }
function type_label($v): string { return ['mc'=>'Trắc nghiệm','tf'=>'Đúng/Sai','sa'=>'Trả lời ngắn','essay'=>'Tự luận','mixed'=>'Tổng hợp'][$v] ?? $v; }
function status_label($v): string { return ['draft'=>'Bản nháp','published'=>'Đã công bố','closed'=>'Đã đóng','open'=>'Đang mở','scheduled'=>'Lên lịch','submitted'=>'Đã nộp','active'=>'Đang hoạt động','locked'=>'Đã khóa'][$v] ?? $v; }
function question_types(): array { return ['mc', 'tf', 'sa', 'essay']; }
function generation_question_types(): array { return ['mixed', 'mc', 'tf', 'sa', 'essay']; }
function normalize_generation_type(string $type): string { return in_array($type, generation_question_types(), true) ? $type : 'mc'; }

function fetch_subjects(): array { return db()->query('SELECT * FROM subjects ORDER BY name')->fetchAll(); }
function fetch_lessons(?int $subjectId = null): array {
    if ($subjectId) { $st = db()->prepare('SELECT l.*, s.name subject_name FROM lessons l JOIN subjects s ON s.id=l.subject_id WHERE l.subject_id=? ORDER BY l.created_at DESC'); $st->execute([$subjectId]); return $st->fetchAll(); }
    return db()->query('SELECT l.*, s.name subject_name FROM lessons l JOIN subjects s ON s.id=l.subject_id ORDER BY l.created_at DESC')->fetchAll();
}
function fetch_lessons_with_questions(?int $subjectId = null): array {
    $sql = 'SELECT l.*, s.name subject_name, COUNT(q.id) total_questions
            FROM lessons l
            JOIN subjects s ON s.id=l.subject_id
            JOIN questions q ON q.lesson_id=l.id';
    $params = [];
    if ($subjectId) {
        $sql .= ' WHERE l.subject_id=?';
        $params[] = $subjectId;
    }
    $sql .= ' GROUP BY l.id ORDER BY l.created_at DESC';
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
function ensure_lesson_id(int $subjectId, int $lessonId = 0): int {
    if ($lessonId > 0) {
        $st = db()->prepare('SELECT id FROM lessons WHERE id=? AND subject_id=?');
        $st->execute([$lessonId, $subjectId]);
        if ($st->fetchColumn()) return $lessonId;
    }
    $st = db()->prepare('SELECT id FROM lessons WHERE subject_id=? ORDER BY id LIMIT 1');
    $st->execute([$subjectId]);
    $existing = (int)$st->fetchColumn();
    if ($existing > 0) return $existing;
    $st = db()->prepare('INSERT INTO lessons(subject_id,name,created_by) VALUES(?,?,?)');
    $st->execute([$subjectId, 'Bài tổng hợp', current_user()['id'] ?? null]);
    return (int)db()->lastInsertId();
}
function ensure_lesson_for_input(int $subjectId, int $lessonId = 0, string $lessonName = ''): int {
    $lessonName = trim($lessonName);
    if ($lessonName !== '') {
        $st = db()->prepare('SELECT id FROM lessons WHERE subject_id=? AND name=? LIMIT 1');
        $st->execute([$subjectId, $lessonName]);
        $existing = (int)$st->fetchColumn();
        if ($existing > 0) return $existing;
        $st = db()->prepare('INSERT INTO lessons(subject_id,name,created_by) VALUES(?,?,?)');
        $st->execute([$subjectId, $lessonName, current_user()['id'] ?? null]);
        $lessonId = (int)db()->lastInsertId();
        log_activity('create', 'lesson', $lessonId, 'Đã tạo bài học mới: ' . $lessonName);
        return $lessonId;
    }
    return ensure_lesson_id($subjectId, $lessonId);
}
function ensure_question_image_column(): void {
    $columns = db()->query('SHOW COLUMNS FROM questions')->fetchAll(PDO::FETCH_ASSOC);
    $imageColumn = null;
    $hasNeedsReview = false;
    foreach ($columns as $column) {
        if (($column['Field'] ?? '') === 'image_path') {
            $imageColumn = $column;
        }
        if (($column['Field'] ?? '') === 'needs_review') $hasNeedsReview = true;
    }
    if (!$imageColumn) {
        db()->exec('ALTER TABLE questions ADD image_path TEXT NULL AFTER content');
    } elseif (!str_contains(strtolower((string)($imageColumn['Type'] ?? '')), 'text')) {
        db()->exec('ALTER TABLE questions MODIFY image_path TEXT NULL');
    }
    if (!$hasNeedsReview) {
        db()->exec('ALTER TABLE questions ADD needs_review TINYINT(1) NOT NULL DEFAULT 0 AFTER explanation');
    }
}
function ensure_exam_points_columns(): void {
    $examColumns = db()->query('SHOW COLUMNS FROM exams')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('total_points', $examColumns, true)) {
        db()->exec('ALTER TABLE exams ADD total_points DECIMAL(6,2) NOT NULL DEFAULT 10.00 AFTER duration');
    }
    $examQuestionColumns = db()->query('SHOW COLUMNS FROM exam_questions')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('points', $examQuestionColumns, true)) {
        db()->exec('ALTER TABLE exam_questions ADD points DECIMAL(6,2) NOT NULL DEFAULT 1.00 AFTER position');
    }
}
function ensure_school_structure_tables(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS schools (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(180) NOT NULL,
        address VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS school_grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_id INT NOT NULL,
        name VARCHAR(80) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS school_classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade_id INT NOT NULL,
        name VARCHAR(80) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (grade_id) REFERENCES school_grades(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $userColumns = db()->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('class_id', $userColumns, true)) {
        db()->exec('ALTER TABLE users ADD class_id INT NULL AFTER status');
        db()->exec('ALTER TABLE users ADD INDEX idx_users_class_id (class_id)');
    }
}
function school_tree(): array {
    ensure_school_structure_tables();
    $schools = [];
    foreach (db()->query('SELECT * FROM schools ORDER BY name')->fetchAll() as $school) {
        $school['grades'] = [];
        $schools[(int)$school['id']] = $school;
    }
    $grades = [];
    foreach (db()->query('SELECT * FROM school_grades ORDER BY name')->fetchAll() as $grade) {
        $grade['classes'] = [];
        $grades[(int)$grade['id']] = $grade;
    }
    foreach (db()->query('SELECT * FROM school_classes ORDER BY name')->fetchAll() as $class) {
        $gradeId = (int)$class['grade_id'];
        if (isset($grades[$gradeId])) $grades[$gradeId]['classes'][(int)$class['id']] = $class + ['students' => []];
    }
    foreach (db()->query('SELECT id,name,email,status,class_id FROM users WHERE role="student" ORDER BY name')->fetchAll() as $student) {
        $classId = (int)($student['class_id'] ?? 0);
        foreach ($grades as &$grade) {
            if (isset($grade['classes'][$classId])) {
                $grade['classes'][$classId]['students'][] = $student;
                break;
            }
        }
        unset($grade);
    }
    foreach ($grades as $grade) {
        $schoolId = (int)$grade['school_id'];
        if (isset($schools[$schoolId])) $schools[$schoolId]['grades'][(int)$grade['id']] = $grade;
    }
    return $schools;
}
function distribute_exam_points(float $totalPoints, int $questionCount): array {
    if ($questionCount <= 0) return [];
    $totalCents = max(0, (int)round($totalPoints * 100));
    $base = intdiv($totalCents, $questionCount);
    $remainder = $totalCents % $questionCount;
    $points = [];
    for ($i = 0; $i < $questionCount; $i++) {
        $points[] = ($base + ($i < $remainder ? 1 : 0)) / 100;
    }
    return $points;
}
function save_question_image_upload(array $file, ?string $oldPath = null): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $oldPath;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return $oldPath;
    $path = save_question_image_from_path($file['tmp_name'], $file['name'] ?? '');
    return $path ?: $oldPath;
}
function save_question_image_from_path(string $sourcePath, string $originalName = ''): ?string {
    if (!is_file($sourcePath)) return null;
    return save_question_image_bytes((string)file_get_contents($sourcePath), $originalName);
}
function question_image_tag(?string $path): string {
    $paths = normalize_question_image_paths((string)$path);
    if (!$paths) return '';
    $blockPaths = array_values(array_filter($paths, fn($imagePath) => preg_match('/(^|\/)question-block-/i', $imagePath)));
    if ($blockPaths) $paths = $blockPaths;
    $html = '';
    foreach ($paths as $imagePath) {
        $html .= '<figure class="question-image"><img src="' . e($imagePath) . '" alt="Hinh minh hoa cau hoi" loading="lazy"></figure>';
    }
    return $html;
}
function is_image_question_placeholder(?string $content): bool {
    return (bool)preg_match('/^\s*Cau hoi bang hinh anh(?:\s+\d+)?\s*$/iu', (string)$content);
}
function display_question_content(?string $content, ?string $imagePath = ''): string {
    if (preg_match('/(^|\|)uploads\/questions\/question-block-/i', (string)$imagePath)) return '';
    return is_image_question_placeholder($content) ? '' : trim((string)$content);
}
function question_content_has_inline_images(?string $content): bool {
    return (bool)preg_match('/\[image:\s*.+?\s*\]/iu', (string)$content);
}
function question_content_html(?string $content, ?string $imagePath = ''): string {
    $content = display_question_content($content, $imagePath);
    if ($content === '') return '';
    $parts = preg_split('/(\[image:\s*.+?\s*\])/iu', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts as $part) {
        if ($part === '') continue;
        if (preg_match('/^\[image:\s*(.+?)\s*\]$/iu', $part, $m)) {
            $path = normalize_question_image_path(trim($m[1]));
            if ($path !== '') {
                $class = 'question-inline-image';
                $full = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/');
                $size = is_file($full) ? @getimagesize($full) : false;
                if ($size && ($size[0] >= 140 && $size[1] >= 100)) $class .= ' question-inline-diagram';
                $html .= '<img class="' . e($class) . '" src="' . e($path) . '" alt="Cong thuc" loading="lazy">';
            }
            continue;
        }
        $html .= nl2br(e($part));
    }
    return $html;
}
function normalize_question_image_paths(string $paths, string $fallbackText = ''): array {
    $out = [];
    foreach (preg_split('/\s*\|\s*/', trim($paths), -1, PREG_SPLIT_NO_EMPTY) as $path) {
        $normalized = normalize_question_image_path($path, $fallbackText);
        if ($normalized !== '' && !in_array($normalized, $out, true)) $out[] = $normalized;
    }
    return $out;
}
function normalize_question_image_path(string $path, string $fallbackText = ''): string {
    $path = trim($path);
    if ($path === '') return '';
    $full = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/');
    if (!is_file($full)) return '';
    $bytes = (string)file_get_contents($full);
    $ext = detect_image_extension($bytes, strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'png');
    if (in_array($ext, ['png', 'jpg', 'webp', 'gif'], true) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg') return $path;
    $replacement = save_question_image_bytes($bytes, $path, $fallbackText);
    return $replacement ?: '';
}
function append_question_image_path(string $paths, string $path, int $maxLength = 4000): string {
    $items = normalize_question_image_paths($paths);
    $path = normalize_question_image_path($path);
    if ($path === '' || in_array($path, $items, true)) return implode('|', $items);
    $candidate = implode('|', array_merge($items, [$path]));
    return strlen($candidate) <= $maxLength ? $candidate : implode('|', $items);
}
function mc_option_labels(array $options = [], string $answer = ''): array {
    $labels = ['A', 'B', 'C', 'D'];
    foreach ($options as $label => $content) {
        $label = strtoupper(trim((string)$label));
        if (preg_match('/^[A-Z]$/', $label) && trim((string)$content) !== '' && !in_array($label, $labels, true)) {
            $labels[] = $label;
        }
    }
    $answer = strtoupper(trim($answer));
    if (preg_match('/^[A-Z]$/', $answer) && !in_array($answer, $labels, true)) $labels[] = $answer;
    sort($labels);
    return $labels;
}
function detect_image_extension(string $bytes, string $fallback = 'png'): ?string {
    if (strncmp($bytes, "\x89PNG\r\n\x1A\n", 8) === 0) return 'png';
    if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) return 'jpg';
    if (strncmp($bytes, 'GIF87a', 6) === 0 || strncmp($bytes, 'GIF89a', 6) === 0) return 'gif';
    if (strncmp($bytes, 'RIFF', 4) === 0 && substr($bytes, 8, 4) === 'WEBP') return 'webp';
    if (strncmp($bytes, "\xD7\xCD\xC6\x9A", 4) === 0) return 'wmf';
    if (strncmp($bytes, "\x01\x00\x00\x00", 4) === 0 && substr($bytes, 40, 4) === ' EMF') return 'emf';
    $fallback = strtolower($fallback);
    return in_array($fallback, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true) ? ($fallback === 'jpeg' ? 'jpg' : $fallback) : null;
}
function save_question_image_bytes(string $bytes, string $originalName = '', string $fallbackText = ''): ?string {
    if ($bytes === '') return null;
    $ext = detect_image_extension($bytes, strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'png');
    $dir = __DIR__ . '/../uploads/questions';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $base = 'question-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

    if (in_array($ext, ['png', 'jpg', 'webp', 'gif'], true)) {
        file_put_contents($dir . '/' . $base . '.' . $ext, $bytes);
        return 'uploads/questions/' . $base . '.' . $ext;
    }

    if (in_array($ext, ['wmf', 'emf'], true)) {
        $metaPath = $dir . '/' . $base . '.' . $ext;
        $pngPath = $dir . '/' . $base . '.png';
        file_put_contents($metaPath, $bytes);
        if (convert_windows_metafile_to_png($metaPath, $pngPath)) {
            @unlink($metaPath);
            return 'uploads/questions/' . $base . '.png';
        }
        @unlink($metaPath);

        $text = trim($fallbackText) !== '' ? trim($fallbackText) : 'Hinh cong thuc tu file Word';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="720" height="150" viewBox="0 0 720 150">'
            . '<rect width="100%" height="100%" rx="10" fill="#ffffff" stroke="#d7d9e5"/>'
            . '<text x="28" y="58" font-family="Arial, sans-serif" font-size="22" fill="#111827">Cong thuc/hinh trong file Word can thay bang anh web.</text>'
            . '<text x="28" y="98" font-family="Arial, sans-serif" font-size="18" fill="#475569">' . e(mb_strimwidth($text, 0, 90, '...')) . '</text>'
            . '</svg>';
        file_put_contents($dir . '/' . $base . '.svg', $svg);
        return 'uploads/questions/' . $base . '.svg';
    }

    return null;
}

function save_question_formula_image(string $formulaText, string $contextText = ''): ?string {
    $formulaText = trim(preg_replace('/\s+/u', ' ', $formulaText));
    if ($formulaText === '') return null;
    $dir = __DIR__ . '/../uploads/questions';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $base = 'question-formula-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $formula = e(mb_strimwidth($formulaText, 0, 120, '...'));
    $context = e(mb_strimwidth(trim($contextText), 0, 95, '...'));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="860" height="170" viewBox="0 0 860 170">'
        . '<rect width="100%" height="100%" rx="10" fill="#ffffff" stroke="#d7d9e5"/>'
        . '<text x="28" y="46" font-family="Arial, sans-serif" font-size="18" fill="#475569">Cong thuc tu file Word</text>'
        . '<text x="28" y="94" font-family="Cambria Math, Times New Roman, serif" font-size="32" fill="#111827">' . $formula . '</text>'
        . ($context !== '' ? '<text x="28" y="136" font-family="Arial, sans-serif" font-size="16" fill="#64748b">' . $context . '</text>' : '')
        . '</svg>';
    file_put_contents($dir . '/' . $base . '.svg', $svg);
    return 'uploads/questions/' . $base . '.svg';
}

function run_command_with_timeout($cmd, int $timeoutSeconds = 15): string {
    if (!function_exists('proc_open')) return '';
    $outPath = tempnam(sys_get_temp_dir(), 'eq-cmd-out-');
    $errPath = tempnam(sys_get_temp_dir(), 'eq-cmd-err-');
    if ($outPath === false || $errPath === false) return '';
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['file', $outPath, 'w'],
        2 => ['file', $errPath, 'w'],
    ];
    $options = is_array($cmd) ? ['bypass_shell' => true] : [];
    $process = @proc_open($cmd, $descriptorSpec, $pipes, null, null, $options);
    if (!is_resource($process)) {
        @unlink($outPath);
        @unlink($errPath);
        return '';
    }

    fclose($pipes[0]);

    $started = time();
    do {
        $status = proc_get_status($process);
        if (!$status['running']) break;
        if (time() - $started >= $timeoutSeconds) {
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false && !empty($status['pid'])) {
                @exec('taskkill /F /T /PID ' . (int)$status['pid'] . ' 2>NUL');
            } else {
                proc_terminate($process);
            }
            break;
        }
        usleep(100000);
    } while (true);

    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) fclose($pipe);
    }
    proc_close($process);
    $output = is_file($outPath) ? (string)file_get_contents($outPath) : '';
    @unlink($outPath);
    @unlink($errPath);
    return $output;
}

function render_pdf_pages_to_question_images(string $path, int $maxPages = 30): array {
    if (!is_file($path)) return [];
    $dir = __DIR__ . '/../uploads/questions';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $prefix = 'pdf-page-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $script = <<<'PY'
import fitz, json, os, sys
pdf_path, out_dir, prefix, max_pages = sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4])
doc = fitz.open(pdf_path)
paths = []
for i in range(min(max_pages, doc.page_count)):
    page = doc.load_page(i)
    pix = page.get_pixmap(matrix=fitz.Matrix(2, 2), alpha=False)
    name = f"{prefix}-{i + 1}.png"
    full = os.path.join(out_dir, name)
    pix.save(full)
    paths.append("uploads/questions/" + name)
print(json.dumps(paths))
PY;
    $cmd = ['python', '-c', $script, $path, $dir, $prefix, (string)$maxPages];
    $raw = run_command_with_timeout($cmd, 20);
    $paths = json_decode(trim((string)$raw), true);
    return is_array($paths) ? array_values(array_filter($paths, fn($p) => is_string($p) && $p !== '')) : [];
}

function render_pdf_question_clips_to_images(string $path, int $maxQuestions = 120): array {
    if (!is_file($path)) return [];
    $dir = __DIR__ . '/../uploads/questions';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $prefix = 'pdf-question-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $script = <<<'PY'
import fitz, json, os, re, sys
sys.stdout.reconfigure(encoding="utf-8")
pdf_path, out_dir, prefix, max_questions = sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4])
doc = fitz.open(pdf_path)
marker = re.compile(r'^\s*(?:(?:Câu|Cau|Question|Q)\s*\d{1,4}\s*[\.\:\)\-]|\d{1,4}\s*[\.\)]\s+\S)', re.I)

def overlap(a0, a1, b0, b1):
    return max(0.0, min(a1, b1) - max(a0, b0))

# Mỗi câu hỏi -> một clip bao trọn cả phần chữ lẫn ảnh công thức (kể cả công
# thức cao vượt lên trên dòng chữ). Gán mỗi ảnh/nét vẽ cho câu mà nó chồng lấn
# dọc nhiều nhất nên không bị cắt cụt và không lẫn sang câu kế.
segments = []  # mỗi phần tử: dict(page, lines=[bbox...], y0, y1)
for page_index in range(doc.page_count):
    page = doc.load_page(page_index)
    pw, ph = page.rect.width, page.rect.height
    data = page.get_text("dict")
    lines = []
    graphics = []
    for block in data.get("blocks", []):
        if block.get("type") == 0:
            for line in block.get("lines", []):
                text = "".join(span.get("text", "") for span in line.get("spans", [])).strip()
                lines.append((tuple(line["bbox"]), text))
        else:
            graphics.append(tuple(block["bbox"]))
    for dr in page.get_drawings():
        r = dr["rect"]
        graphics.append((r.x0, r.y0, r.x1, r.y1))
    # Bỏ nét vẽ/ảnh phủ gần kín trang (đường viền, nền) để không làm phình clip
    graphics = [g for g in graphics
                if (g[2] - g[0]) < 0.92 * pw and (g[3] - g[1]) < 0.6 * ph]
    lines.sort(key=lambda L: (round(L[0][1], 1), L[0][0]))

    page_segs = []
    for bbox, text in lines:
        if marker.match(text):
            seg = {"page": page_index, "x0": bbox[0], "y0": bbox[1], "x1": bbox[2], "y1": bbox[3]}
            page_segs.append(seg)
        elif page_segs:
            seg = page_segs[-1]
            seg["x0"] = min(seg["x0"], bbox[0]); seg["y0"] = min(seg["y0"], bbox[1])
            seg["x1"] = max(seg["x1"], bbox[2]); seg["y1"] = max(seg["y1"], bbox[3])
    # Gán ảnh/công thức cho câu chồng lấn dọc nhiều nhất trong cùng trang
    for g in graphics:
        best, best_ov = None, 0.0
        for seg in page_segs:
            ov = overlap(g[1], g[3], seg["y0"], seg["y1"])
            if ov > best_ov:
                best, best_ov = seg, ov
        if best is None and page_segs:
            gc = (g[1] + g[3]) / 2.0
            best = min(page_segs, key=lambda s: abs((s["y0"] + s["y1"]) / 2.0 - gc))
        if best is not None:
            best["x0"] = min(best["x0"], g[0]); best["y0"] = min(best["y0"], g[1])
            best["x1"] = max(best["x1"], g[2]); best["y1"] = max(best["y1"], g[3])
    segments.extend(page_segs)
    if len(segments) >= max_questions:
        segments = segments[:max_questions]
        break

paths = []
zoom = fitz.Matrix(2, 2)
for idx, seg in enumerate(segments):
    page = doc.load_page(seg["page"])
    rect = page.rect
    pad = 5.0
    clip = fitz.Rect(
        max(rect.x0, seg["x0"] - pad), max(rect.y0, seg["y0"] - pad),
        min(rect.x1, seg["x1"] + pad), min(rect.y1, seg["y1"] + pad))
    if clip.height < 12 or clip.width < 12:
        continue
    pix = page.get_pixmap(matrix=zoom, clip=clip, alpha=False)
    name = f"{prefix}-{idx + 1}.png"
    pix.save(os.path.join(out_dir, name))
    paths.append("uploads/questions/" + name)
print(json.dumps(paths, ensure_ascii=False))
PY;
    $cmd = ['python', '-c', $script, $path, $dir, $prefix, (string)$maxQuestions];
    $raw = run_command_with_timeout($cmd, 40);
    $paths = json_decode(trim((string)$raw), true);
    return is_array($paths) ? array_values(array_filter($paths, fn($p) => is_string($p) && $p !== '')) : [];
}

function question_image_markers(array $paths): string {
    $lines = [];
    foreach ($paths as $path) {
        $path = normalize_question_image_path((string)$path);
        if ($path !== '') $lines[] = '[image:' . $path . ']';
    }
    return implode("\n", $lines);
}

function split_import_question_blocks(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') return [];
    $pattern = '/(?=^\s*(?:(?:Cau|Câu|Question|Q)\s*\d{1,4}|\d{1,4})\s*[\.\:\)\-])/miu';
    $blocks = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_filter(array_map('trim', $blocks), fn($block) => $block !== ''));
}

function merge_question_blocks_with_images(string $text, array $imagePaths): string {
    $blocks = split_import_question_blocks($text);
    if (!$imagePaths) return $text;
    $out = [];
    $count = max(count($blocks), count($imagePaths));
    for ($i = 0; $i < $count; $i++) {
        $block = $blocks[$i] ?? ('Cau ' . ($i + 1) . ':');
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn($line) => $line !== ''));
        $first = $lines ? array_shift($lines) : ('Cau ' . ($i + 1) . ':');
        $path = normalize_question_image_path((string)($imagePaths[$i] ?? ''));
        $out[] = trim($first . ($path !== '' ? "\n[image:" . $path . ']' : '') . ($lines ? "\n" . implode("\n", $lines) : ''));
    }
    return implode("\n", $out);
}

function image_question_text_from_paths(array $imagePaths): string {
    $out = [];
    foreach ($imagePaths as $i => $path) {
        $path = normalize_question_image_path((string)$path);
        if ($path !== '') $out[] = 'Cau ' . ($i + 1) . ":\n[image:" . $path . ']';
    }
    return implode("\n", $out);
}

function question_content_unreliable(array $question): bool {
    $content = trim((string)($question['content'] ?? ''));
    if ($content === '') return true;
    $plain = preg_replace('/\s+/u', ' ', $content);
    $letters = preg_match_all('/[\p{L}\p{N}]/u', $plain);
    $badChars = preg_match_all('/[�\?]{2,}|Ã|Â/u', $plain);
    if (mb_strlen($plain, 'UTF-8') < 18 && empty($question['options']) && empty($question['tf_items'])) return true;
    if ($letters > 0 && ($badChars / max(1, $letters)) > 0.12) return true;
    return false;
}

function image_question_from_path(string $path, string $type = 'essay', int $index = 1): array {
    return [
        'type' => normalize_generation_type($type) === 'mixed' ? 'essay' : normalize_generation_type($type),
        'content' => 'Cau hoi bang hinh anh ' . $index,
        'image_path' => normalize_question_image_path($path),
        'difficulty' => 'unknown',
        'answer' => '',
        'options' => [],
        'tf_items' => [],
        'explanation' => '',
    ];
}

function svg_text_lines(string $text, int $maxChars = 92): array {
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') return [];
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (mb_strlen($candidate, 'UTF-8') > $maxChars && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') $lines[] = $line;
    return $lines;
}

function save_question_text_snapshot_image(string $text, int $index = 1): ?string {
    $lines = svg_text_lines($text);
    if (!$lines) return null;
    $dir = __DIR__ . '/../uploads/questions';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $base = 'question-snapshot-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $height = max(96, 46 + count($lines) * 30);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1120" height="' . $height . '" viewBox="0 0 1120 ' . $height . '">'
        . '<rect width="100%" height="100%" fill="#ffffff"/>';
    foreach ($lines as $i => $line) {
        $svg .= '<text x="14" y="' . (38 + $i * 30) . '" font-family="Times New Roman, Cambria, serif" font-size="24" fill="#111111">' . e($line) . '</text>';
    }
    $svg .= '</svg>';
    file_put_contents($dir . '/' . $base . '.svg', $svg);
    return 'uploads/questions/' . $base . '.svg';
}

function save_question_inline_tokens_image(array $tokens, int $index = 1): ?string {
    $tokens = array_values(array_filter($tokens, fn($token) => !empty($token['text']) || !empty($token['path'])));
    if (!$tokens) return null;
    $dir = __DIR__ . '/../uploads/questions';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $base = 'question-inline-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.png';
    $target = $dir . '/' . $base;
    $payload = [];
    foreach ($tokens as $token) {
        if (($token['type'] ?? '') === 'image') {
            $path = normalize_question_image_path((string)($token['path'] ?? ''));
            $full = $path !== '' ? __DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/') : '';
            if ($full !== '' && is_file($full)) $payload[] = ['type' => 'image', 'path' => $full];
        } else {
            $text = trim((string)($token['text'] ?? ''));
            if ($text !== '') $payload[] = ['type' => 'text', 'text' => $text];
        }
    }
    if (!$payload) return null;
    $jsonPath = tempnam(sys_get_temp_dir(), 'eq-inline-');
    file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE));
    $script = <<<'PY'
from PIL import Image, ImageDraw, ImageFont
import json, os, sys
payload_path, out_path = sys.argv[1], sys.argv[2]
tokens = json.load(open(payload_path, "r", encoding="utf-8"))
font_paths = [
    r"C:\Windows\Fonts\times.ttf",
    r"C:\Windows\Fonts\arial.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
]
font = None
for fp in font_paths:
    if os.path.exists(fp):
        font = ImageFont.truetype(fp, 27)
        break
if font is None:
    font = ImageFont.load_default()
max_w, pad, gap = 1120, 14, 7
line_h = 40
rows = []
row = []
x = pad
row_h = line_h
measure = ImageDraw.Draw(Image.new("RGB", (1, 1)))

def text_size(text):
    box = measure.textbbox((0, 0), text, font=font)
    return max(1, box[2] - box[0]), max(line_h, box[3] - box[1] + 12)

for token in tokens:
    if token.get("type") == "image":
        try:
            im = Image.open(token["path"]).convert("RGBA")
        except Exception:
            continue
        max_img_h = 54
        if im.height > max_img_h:
            scale = max_img_h / float(im.height)
            im = im.resize((max(1, int(im.width * scale)), max(1, int(im.height * scale))))
        if x + im.width > max_w - pad:
            if row:
                rows.append((row, row_h))
            row, x, row_h = [], pad, line_h
        row.append(("image", im, x, 0))
        x += im.width + gap
        row_h = max(row_h, im.height + 12)
        continue

    text = token.get("text", "")
    parts = text.split(" ")
    for part in parts:
        if part == "":
            continue
        piece = part + " "
        tw, th = text_size(piece)
        if x + tw > max_w - pad and x > pad:
            if row:
                rows.append((row, row_h))
            row, x, row_h = [], pad, line_h
        row.append(("text", piece, x, 0))
        x += tw
        row_h = max(row_h, th)
if row:
    rows.append((row, row_h))
height = max(80, pad * 2 + sum(h for _, h in rows))
canvas = Image.new("RGB", (max_w, height), "white")
draw = ImageDraw.Draw(canvas)
y = pad
for row, rh in rows:
    for kind, value, item_x, _ in row:
        if kind == "text":
            draw.text((item_x, y + max(0, (rh - line_h) // 2)), value, fill=(17, 17, 17), font=font)
        else:
            canvas.paste(value, (item_x, y + max(0, (rh - value.height) // 2)), value)
    y += rh
canvas.save(out_path)
PY;
    $scriptPath = tempnam(sys_get_temp_dir(), 'eq-inline-script-') . '.py';
    file_put_contents($scriptPath, $script);
    $cmd = ['python', $scriptPath, $jsonPath, $target];
    run_command_with_timeout($cmd, 12);
    @unlink($scriptPath);
    @unlink($jsonPath);
    return is_file($target) ? 'uploads/questions/' . $base : null;
}

function save_question_block_image_from_text(string $block, int $index = 1): ?string {
    $lines = preg_split('/\R/u', trim($block), -1, PREG_SPLIT_NO_EMPTY);
    if (!$lines) return null;
    $payload = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (preg_match('/^\[image:\s*(.+?)\s*\]$/iu', $line, $m)) {
            $path = normalize_question_image_path(trim($m[1]));
            $full = $path !== '' ? __DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/') : '';
            if ($full !== '' && is_file($full)) $payload[] = ['type' => 'image', 'path' => $full];
        } else {
            $payload[] = ['type' => 'text', 'text' => $line];
        }
        $payload[] = ['type' => 'break'];
    }
    $payload = array_values(array_filter($payload, fn($item) => ($item['type'] ?? '') !== 'break' || count($payload) > 1));
    if (!$payload) return null;

    $dir = __DIR__ . '/../uploads/questions';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $base = 'question-block-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.png';
    $target = $dir . '/' . $base;
    $jsonPath = tempnam(sys_get_temp_dir(), 'eq-block-');
    file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE));
    $script = <<<'PY'
from PIL import Image, ImageDraw, ImageFont
import json, os, sys, textwrap
payload_path, out_path = sys.argv[1], sys.argv[2]
items = json.load(open(payload_path, "r", encoding="utf-8"))
font_paths = [
    r"C:\Windows\Fonts\times.ttf",
    r"C:\Windows\Fonts\arial.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf",
    "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
]
font = None
bold = None
for fp in font_paths:
    if os.path.exists(fp):
        font = ImageFont.truetype(fp, 28)
        bold = ImageFont.truetype(fp, 29)
        break
if font is None:
    font = ImageFont.load_default()
    bold = font
max_w, pad, gap = 1280, 26, 14
draw_probe = ImageDraw.Draw(Image.new("RGB", (1, 1)))

def text_w(text, fnt=font):
    box = draw_probe.textbbox((0, 0), text, font=fnt)
    return box[2] - box[0]

rows = []
for item in items:
    kind = item.get("type")
    if kind == "break":
        rows.append(("space", 10))
        continue
    if kind == "image":
        try:
            im = Image.open(item["path"]).convert("RGBA")
        except Exception:
            continue
        if im.width > max_w - pad * 2:
            scale = (max_w - pad * 2) / float(im.width)
            im = im.resize((max(1, int(im.width * scale)), max(1, int(im.height * scale))))
        rows.append(("image", im))
        continue
    text = str(item.get("text", "")).strip()
    if not text:
        continue
    words, current = text.split(), ""
    lines = []
    for word in words:
        candidate = (current + " " + word).strip()
        if current and text_w(candidate) > max_w - pad * 2:
            lines.append(current)
            current = word
        else:
            current = candidate
    if current:
        lines.append(current)
    for line in lines:
        rows.append(("text", line))

if rows and rows[-1][0] == "space":
    rows.pop()
height = pad * 2
for kind, value in rows:
    if kind == "image":
        height += value.height + gap
    elif kind == "space":
        height += value
    else:
        height += 40
height = max(90, height)
canvas = Image.new("RGB", (max_w, height), "white")
draw = ImageDraw.Draw(canvas)
y = pad
for kind, value in rows:
    if kind == "image":
        canvas.paste(value, (pad, y), value)
        y += value.height + gap
    elif kind == "space":
        y += value
    else:
        fnt = bold if value.lower().startswith(("câu", "cau", "question", "q ")) else font
        draw.text((pad, y), value, fill=(15, 23, 42), font=fnt)
        y += 40
canvas = canvas.crop((0, 0, max_w, min(height, y + pad)))
canvas.save(out_path)
PY;
    $scriptPath = tempnam(sys_get_temp_dir(), 'eq-block-script-') . '.py';
    file_put_contents($scriptPath, $script);
    run_command_with_timeout(['python', $scriptPath, $jsonPath, $target], 20);
    @unlink($scriptPath);
    @unlink($jsonPath);
    return is_file($target) ? 'uploads/questions/' . $base : null;
}

function save_import_question_block_images(string $text): array {
    $blocks = split_import_question_blocks($text);
    if (!$blocks) return [];
    $paths = [];
    foreach ($blocks as $i => $block) {
        if (!preg_match('/\[image:/i', $block)) continue;
        continue;
        // Ảnh clip nguyên-câu từ PDF (LibreOffice) đã là ảnh đầy đủ của câu hỏi;
        // không bake lại thành ảnh text-snapshot PIL kẻo bị trùng/lặp text.
        if (preg_match('~\[image:[^\]]*(?:pdf-question-|pdf-page-)~i', $block)) continue;
        $path = save_question_block_image_from_text($block, $i + 1);
        if ($path) $paths[$i] = $path;
    }
    return $paths;
}

function convert_docx_to_pdf(string $docxPath): ?string {
    if (!is_file($docxPath)) return null;
    $outDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eduquest-docx-' . bin2hex(random_bytes(4));
    if (!is_dir($outDir)) mkdir($outDir, 0777, true);
    $inputPath = $outDir . DIRECTORY_SEPARATOR . 'source.docx';
    copy($docxPath, $inputPath);
    $pdfPath = $outDir . DIRECTORY_SEPARATOR . 'source.pdf';
    // Profile riêng (KHÔNG dùng profile mặc định để tránh đụng quickstarter làm
    // soffice crash 0xC0000409). Dùng lại một profile cố định cho lần sau chỉ mất
    // ~3s thay vì ~17s khởi tạo mới mỗi lần.
    $profileDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'eduquest-lo-profile';
    if (!is_dir($profileDir)) @mkdir($profileDir, 0777, true);
    $profile = 'file:///' . str_replace('\\', '/', $profileDir);

    // Trên Windows phải dùng soffice.com (console, chạy đồng bộ); soffice.exe là
    // launcher tách tiến trình nên trả về ngay trước khi tạo xong PDF.
    $isWindows = stripos(PHP_OS_FAMILY, 'Windows') !== false;
    $officeCandidates = $isWindows ? [
        'C:\\Program Files\\LibreOffice\\program\\soffice.com',
        'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.com',
        'soffice.com',
        'soffice.exe',
    ] : [
        'soffice',
        'libreoffice',
    ];
    foreach ($officeCandidates as $office) {
        $cmd = [$office, '-env:UserInstallation=' . $profile, '--headless', '--norestore', '--convert-to', 'pdf', '--outdir', $outDir, $inputPath];
        run_command_with_timeout($cmd, 90);
        if (is_file($pdfPath)) return $pdfPath;
    }

    return null;
}

function docx_media_zip_path(string $target): string {
    $target = str_replace('\\', '/', $target);
    return str_starts_with($target, '/') ? ltrim($target, '/') : 'word/' . ltrim($target, '/');
}

function docx_inline_text_from_tokens(array $tokens): string {
    $out = '';
    foreach ($tokens as $token) {
        if (($token['type'] ?? '') === 'image') {
            $path = trim((string)($token['path'] ?? ''));
            if ($path !== '') {
                $out = rtrim($out);
                $out .= ' [image:' . $path . '] ';
            }
            continue;
        }
        $out .= (string)($token['text'] ?? '');
    }
    return trim(preg_replace('/[ \t]+/u', ' ', $out));
}

function extract_docx_text_with_inline_media(string $path): string {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return '';
    $xml = $zip->getFromName('word/document.xml');
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    if (!$xml) {
        $zip->close();
        return '';
    }

    $rels = [];
    if ($relsXml) {
        $relsDoc = new DOMDocument();
        if (@$relsDoc->loadXML($relsXml)) {
            foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
                if (str_contains((string)$rel->getAttribute('Type'), '/image')) {
                    $rels[(string)$rel->getAttribute('Id')] = (string)$rel->getAttribute('Target');
                }
            }
        }
    }

    $doc = new DOMDocument();
    if (!@$doc->loadXML($xml)) {
        $zip->close();
        return trim(html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    $xp = new DOMXPath($doc);
    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
    $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');
    $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

    $out = [];
    foreach ($xp->query('//w:body//w:tbl//w:tr') as $tr) {
        $cells = [];
        foreach ($xp->query('./w:tc', $tr) as $tc) {
            $parts = [];
            foreach ($xp->query('.//w:t|.//m:t', $tc) as $t) $parts[] = $t->textContent;
            $cellText = trim(html_entity_decode(implode('', $parts), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($cellText !== '') $cells[] = $cellText;
        }
        if (count($cells) >= 2) $out[] = implode('|', $cells);
    }

    foreach ($xp->query('//w:body//w:p') as $p) {
        $tokens = [];
        foreach ($xp->query('./w:r|./m:oMathPara|./m:oMath', $p) as $node) {
            if ($node->localName === 'oMath' || $node->localName === 'oMathPara') {
                $formulaParts = [];
                foreach ($xp->query('.//m:t', $node) as $t) $formulaParts[] = $t->textContent;
                $formulaText = trim(html_entity_decode(implode(' ', $formulaParts), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if ($formulaText !== '') $tokens[] = ['type' => 'text', 'text' => $formulaText];
                continue;
            }

            $runTextParts = [];
            foreach ($xp->query('.//w:t', $node) as $t) $runTextParts[] = $t->textContent;
            $runText = html_entity_decode(implode('', $runTextParts), ENT_QUOTES | ENT_XML1, 'UTF-8');
            if ($runText !== '') $tokens[] = ['type' => 'text', 'text' => $runText];

            foreach ($xp->query('.//a:blip|.//v:imagedata', $node) as $blip) {
                $rid = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed')
                    ?: $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                if ($rid === '' || empty($rels[$rid])) continue;
                $image = $zip->getFromName(docx_media_zip_path($rels[$rid]));
                if ($image === false) continue;
                $imagePath = save_question_image_bytes($image, basename($rels[$rid]), docx_inline_text_from_tokens($tokens));
                if ($imagePath) $tokens[] = ['type' => 'image', 'path' => $imagePath];
            }
        }
        $line = docx_inline_text_from_tokens($tokens);
        if ($line !== '') $out[] = $line;
    }

    $zip->close();
    return trim(implode("\n", $out));
}

function force_import_questions_as_images(array $questions): array {
    foreach ($questions as $i => &$question) {
        $content = trim((string)($question['content'] ?? ''));
        $paths = normalize_question_image_paths((string)($question['image_path'] ?? ''), $content);

        if ($content !== '' && !$paths) {
            $snapshot = save_question_text_snapshot_image($content, $i + 1);
            if ($snapshot) $paths[] = $snapshot;
        } elseif ($content !== '' && $paths && !preg_match('/^Cau hoi bang hinh anh/i', $content)) {
            $hasOriginalQuestionImage = (bool)preg_match('/pdf-question-|pdf-page-|question-inline-|question-block-/i', implode('|', $paths));
            if (!$hasOriginalQuestionImage) {
                $snapshot = save_question_text_snapshot_image($content, $i + 1);
                if ($snapshot) array_unshift($paths, $snapshot);
            }
        }

        $question['image_path'] = implode('|', array_values(array_unique($paths)));
        if ($content === '' || is_image_question_placeholder($content)) {
            $question['content'] = 'Cau hoi bang hinh anh ' . ($i + 1);
        }
        if (!empty($question['image_path'])) {
            $question['needs_review'] = (int)($question['needs_review'] ?? 1);
        }
    }
    unset($question);
    return $questions;
}

function attach_pdf_page_images_to_questions(array $questions, array $file, string $type = 'mixed'): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return $questions;
    if (strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)) !== 'pdf') return $questions;
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) return $questions;

    $alreadyHasSourceImages = $questions && count(array_filter($questions, fn($q) => !empty($q['image_path']))) === count($questions);
    if ($alreadyHasSourceImages) return $questions;

    $alreadyImageQuestions = $questions && count(array_filter($questions, fn($q) => !empty($q['image_path']) && preg_match('/^Cau hoi bang hinh anh/i', (string)($q['content'] ?? '')))) === count($questions);
    if ($alreadyImageQuestions) return $questions;

    $questionImages = render_pdf_question_clips_to_images($tmp, max(120, count($questions) + 5));
    if ($questionImages) {
        if (!$questions) {
            return array_map(fn($path, $i) => image_question_from_path($path, $type, $i + 1), $questionImages, array_keys($questionImages));
        }
        foreach ($questions as $i => &$question) {
            if (!isset($questionImages[$i])) break;
            $question['image_path'] = append_question_image_path((string)($question['image_path'] ?? ''), $questionImages[$i]);
            if (question_content_unreliable($question)) $question['content'] = 'Cau hoi bang hinh anh ' . ($i + 1);
        }
        unset($question);
        return $questions;
    }

    $maxPages = max(8, min(30, max(1, count($questions) + 2)));
    $pageImages = render_pdf_pages_to_question_images($tmp, $maxPages);
    if (!$pageImages) return $questions;

    if (!$questions) {
        return array_map(fn($path, $i) => image_question_from_path($path, $type, $i + 1), $pageImages, array_keys($pageImages));
    }

    if (count($questions) === count($pageImages)) {
        foreach ($questions as $i => &$question) {
            $question['image_path'] = append_question_image_path((string)($question['image_path'] ?? ''), $pageImages[$i]);
            if (question_content_unreliable($question)) $question['content'] = 'Cau hoi bang hinh anh ' . ($i + 1);
        }
        unset($question);
        return $questions;
    }

    if (count($questions) === 1) {
        foreach ($pageImages as $path) {
            $questions[0]['image_path'] = append_question_image_path((string)($questions[0]['image_path'] ?? ''), $path);
        }
        if (question_content_unreliable($questions[0])) $questions[0]['content'] = 'Cau hoi bang hinh anh';
        return $questions;
    }

    $pageIndex = 0;
    foreach ($questions as $i => &$question) {
        if (!question_content_unreliable($question) && !empty($question['image_path'])) continue;
        if (!isset($pageImages[$pageIndex])) break;
        $question['image_path'] = append_question_image_path((string)($question['image_path'] ?? ''), $pageImages[$pageIndex]);
        if (question_content_unreliable($question)) $question['content'] = 'Cau hoi bang hinh anh ' . ($i + 1);
        $pageIndex++;
    }
    unset($question);
    return $questions;
}
function convert_windows_metafile_to_png(string $sourcePath, string $targetPath): bool {
    if (stripos(PHP_OS_FAMILY, 'Windows') === false) return false;
    $source = str_replace("'", "''", $sourcePath);
    $target = str_replace("'", "''", $targetPath);
    $script = "Add-Type -AssemblyName System.Drawing;"
        . "\$img=[System.Drawing.Image]::FromFile('{$source}');"
        . "\$img.Save('{$target}',[System.Drawing.Imaging.ImageFormat]::Png);"
        . "\$img.Dispose();";
    run_command_with_timeout(['powershell', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-Command', $script], 8);
    if (!is_file($targetPath)) return false;
    $bytes = (string)file_get_contents($targetPath);
    return detect_image_extension($bytes, 'png') === 'png';
}
function get_question(int $id): ?array { $st=db()->prepare('SELECT q.*,s.name subject_name,l.name lesson_name FROM questions q JOIN subjects s ON s.id=q.subject_id JOIN lessons l ON l.id=q.lesson_id WHERE q.id=?'); $st->execute([$id]); return $st->fetch() ?: null; }
function question_options(int $qid): array { $st=db()->prepare('SELECT * FROM question_options WHERE question_id=? ORDER BY label'); $st->execute([$qid]); return $st->fetchAll(); }
function tf_items(int $qid): array { $st=db()->prepare('SELECT * FROM true_false_items WHERE question_id=? ORDER BY label'); $st->execute([$qid]); return $st->fetchAll(); }

function ensure_assignments_table(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS exam_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id INT NOT NULL,
        target VARCHAR(180) NOT NULL,
        target_role ENUM('teacher','student','group','class') NOT NULL DEFAULT 'group',
        target_user_id INT NULL,
        target_class_id INT NULL,
        start_at DATETIME NULL,
        due_at DATETIME NULL,
        show_score TINYINT(1) NOT NULL DEFAULT 1,
        show_answers TINYINT(1) NOT NULL DEFAULT 0,
        status ENUM('scheduled','open','closed') NOT NULL DEFAULT 'open',
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
        FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns = db()->query('SHOW COLUMNS FROM exam_assignments')->fetchAll(PDO::FETCH_COLUMN);
    db()->exec("ALTER TABLE exam_assignments MODIFY target_role ENUM('teacher','student','group','class') NOT NULL DEFAULT 'group'");
    if (!in_array('target_role', $columns, true)) {
        db()->exec("ALTER TABLE exam_assignments ADD target_role ENUM('teacher','student','group','class') NOT NULL DEFAULT 'group' AFTER target");
    }
    if (!in_array('target_user_id', $columns, true)) {
        db()->exec("ALTER TABLE exam_assignments ADD target_user_id INT NULL AFTER target_role");
        db()->exec("ALTER TABLE exam_assignments ADD INDEX idx_exam_assignments_target_user (target_user_id)");
    }
    if (!in_array('target_class_id', $columns, true)) {
        db()->exec("ALTER TABLE exam_assignments ADD target_class_id INT NULL AFTER target_user_id");
        db()->exec("ALTER TABLE exam_assignments ADD INDEX idx_exam_assignments_target_class (target_class_id)");
    }
}

function user_class_id(int $userId): int {
    ensure_school_structure_tables();
    $st = db()->prepare('SELECT class_id FROM users WHERE id=?');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

function fetch_school_classes(): array {
    ensure_school_structure_tables();
    return db()->query('SELECT c.*,g.name grade_name,s.name school_name,
        (SELECT COUNT(*) FROM users u WHERE u.class_id=c.id AND u.role="student" AND u.status="active") student_count
        FROM school_classes c
        JOIN school_grades g ON g.id=c.grade_id
        JOIN schools s ON s.id=g.school_id
        ORDER BY s.name,g.name,c.name')->fetchAll();
}

function ensure_notifications_tables(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(220) NOT NULL,
        content TEXT NOT NULL,
        sender_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS notification_recipients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_notification_user (notification_id,user_id),
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_activity_logs_table(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        role VARCHAR(30) NULL,
        action VARCHAR(60) NOT NULL,
        entity_type VARCHAR(60) NOT NULL,
        entity_id INT NULL,
        description VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_created_at (created_at),
        INDEX idx_activity_user_id (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function log_activity(string $action, string $entityType, ?int $entityId, string $description): void {
    ensure_activity_logs_table();
    $user = current_user();
    $userId = $user ? (int)$user['id'] : null;
    $role = $user['role'] ?? null;
    db()->prepare('INSERT INTO activity_logs(user_id,role,action,entity_type,entity_id,description) VALUES(?,?,?,?,?,?)')
        ->execute([$userId, $role, $action, $entityType, $entityId, mb_strimwidth($description, 0, 250, '...')]);
}

function recent_activity_logs(int $limit = 8, ?int $userId = null): array {
    ensure_activity_logs_table();
    $limit = max(1, min(30, $limit));
    $where = $userId ? 'WHERE al.user_id=' . (int)$userId : '';
    return db()->query("SELECT al.*,u.name user_name
        FROM activity_logs al
        LEFT JOIN users u ON u.id=al.user_id
        {$where}
        ORDER BY al.created_at DESC
        LIMIT {$limit}")->fetchAll();
}

function activity_chart_data(string $period = 'week', ?int $userId = null): array {
    ensure_activity_logs_table();
    $period = $period === 'month' ? 'month' : 'week';
    $labels = [];
    $keys = [];
    if ($period === 'week') {
        $start = new DateTimeImmutable('monday this week');
        for ($i = 0; $i < 7; $i++) {
            $day = $start->modify("+{$i} days");
            $keys[] = $day->format('Y-m-d');
            $labels[] = ['T2','T3','T4','T5','T6','T7','CN'][$i];
        }
    } else {
        $start = new DateTimeImmutable('first day of this month');
        $days = (int)$start->format('t');
        for ($i = 0; $i < $days; $i++) {
            $day = $start->modify("+{$i} days");
            $keys[] = $day->format('Y-m-d');
            $labels[] = $day->format('d/m');
        }
    }

    $counts = array_fill_keys($keys, 0);
    $from = $keys[0] . ' 00:00:00';
    $toDate = (new DateTimeImmutable(end($keys)))->modify('+1 day')->format('Y-m-d');
    $sql = 'SELECT DATE(created_at) day,COUNT(*) total FROM activity_logs WHERE created_at>=? AND created_at<?';
    $params = [$from, $toDate . ' 00:00:00'];
    if ($userId) {
        $sql .= ' AND user_id=?';
        $params[] = $userId;
    }
    $sql .= ' GROUP BY DATE(created_at)';
    $st = db()->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $row) {
        if (isset($counts[$row['day']])) $counts[$row['day']] = (int)$row['total'];
    }
    return ['labels' => $labels, 'values' => array_values($counts)];
}

function unread_notification_count(?int $userId = null): int {
    if (!current_user() && !$userId) return 0;
    ensure_notifications_tables();
    $userId = $userId ?: (int)current_user()['id'];
    $st = db()->prepare('SELECT COUNT(*) FROM notification_recipients WHERE user_id=? AND read_at IS NULL');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

function user_notifications(int $userId, int $limit = 8): array {
    ensure_notifications_tables();
    $limit = max(1, min(20, $limit));
    $st = db()->prepare("SELECT n.*,u.name sender_name,nr.read_at
        FROM notification_recipients nr
        JOIN notifications n ON n.id=nr.notification_id
        LEFT JOIN users u ON u.id=n.sender_id
        WHERE nr.user_id=?
        ORDER BY n.created_at DESC
        LIMIT {$limit}");
    $st->execute([$userId]);
    return $st->fetchAll();
}

function assignment_status(array $a): string {
    $now = time();
    if (!empty($a['start_at']) && strtotime($a['start_at']) > $now) return 'scheduled';
    if (!empty($a['due_at']) && strtotime($a['due_at']) < $now) return 'closed';
    return $a['status'] ?? 'open';
}

function create_question(array $data, array $options = [], array $tfItems = []): int {
    ensure_question_image_column();
    $data['lesson_id'] = ensure_lesson_for_input((int)$data['subject_id'], (int)$data['lesson_id'], (string)($data['lesson_name'] ?? ''));
    $st = db()->prepare('INSERT INTO questions(subject_id,lesson_id,type,content,image_path,answer,difficulty,explanation,needs_review,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
    $st->execute([$data['subject_id'],$data['lesson_id'],$data['type'],$data['content'],$data['image_path'] ?? null,$data['answer'] ?? null,$data['difficulty'] ?? 'unknown',$data['explanation'] ?? null,!empty($data['needs_review']) ? 1 : 0,current_user()['id'] ?? null]);
    $qid = (int)db()->lastInsertId();
    if ($data['type'] === 'mc') {
        $ins = db()->prepare('INSERT INTO question_options(question_id,label,content,is_correct) VALUES(?,?,?,?)');
        foreach ($options as $label => $content) if (trim($content) !== '') $ins->execute([$qid, strtoupper($label), trim($content), strtoupper($label) === strtoupper($data['answer'] ?? '') ? 1 : 0]);
    }
    if ($data['type'] === 'tf') {
        $ins = db()->prepare('INSERT INTO true_false_items(question_id,label,content,answer,difficulty) VALUES(?,?,?,?,?)');
        foreach ($tfItems as $item) if (trim($item['content'] ?? '') !== '') $ins->execute([$qid, strtolower($item['label']), trim($item['content']), $item['answer'] ?? 'true', $item['difficulty'] ?? 'unknown']);
    }
    log_activity('create', 'question', $qid, 'Đã thêm câu hỏi ' . type_label($data['type']) . ': ' . mb_strimwidth((string)$data['content'], 0, 80, '...'));
    return $qid;
}

function update_question(int $qid, array $data, array $options = [], array $tfItems = []): void {
    ensure_question_image_column();
    $data['lesson_id'] = ensure_lesson_for_input((int)$data['subject_id'], (int)$data['lesson_id'], (string)($data['lesson_name'] ?? ''));
    $st = db()->prepare('UPDATE questions SET subject_id=?,lesson_id=?,type=?,content=?,image_path=?,answer=?,difficulty=?,explanation=?,needs_review=? WHERE id=?');
    $st->execute([$data['subject_id'],$data['lesson_id'],$data['type'],$data['content'],$data['image_path'] ?? null,$data['answer'] ?? null,$data['difficulty'] ?? 'unknown',$data['explanation'] ?? null,!empty($data['needs_review']) ? 1 : 0,$qid]);
    db()->prepare('DELETE FROM question_options WHERE question_id=?')->execute([$qid]);
    db()->prepare('DELETE FROM true_false_items WHERE question_id=?')->execute([$qid]);
    if ($data['type'] === 'mc') {
        $ins = db()->prepare('INSERT INTO question_options(question_id,label,content,is_correct) VALUES(?,?,?,?)');
        foreach ($options as $label => $content) if (trim((string)$content) !== '') $ins->execute([$qid, strtoupper($label), trim($content), strtoupper($label) === strtoupper($data['answer'] ?? '') ? 1 : 0]);
    }
    if ($data['type'] === 'tf') {
        $ins = db()->prepare('INSERT INTO true_false_items(question_id,label,content,answer,difficulty) VALUES(?,?,?,?,?)');
        foreach ($tfItems as $item) if (trim($item['content'] ?? '') !== '') $ins->execute([$qid, strtolower($item['label']), trim($item['content']), $item['answer'] ?? 'true', $item['difficulty'] ?? 'unknown']);
    }
    log_activity('update', 'question', $qid, 'Đã cập nhật câu hỏi: ' . mb_strimwidth((string)$data['content'], 0, 80, '...'));
}

function question_form_payload(array $src): array {
    $type = trim($src['type'] ?? 'mc');
    if ($type === 'mixed') {
        $hasOptions = false;
        foreach (($src['options'] ?? []) as $value) {
            if (trim((string)$value) !== '') $hasOptions = true;
        }
        foreach (range('A', 'Z') as $l) {
            if (trim($src['opt_'.$l] ?? '') !== '') $hasOptions = true;
        }
        $hasTfItems = false;
        foreach (['a','b','c','d'] as $l) {
            if (trim($src['tf_items'][$l]['content'] ?? $src['tf_'.$l] ?? '') !== '') $hasTfItems = true;
        }
        $type = $hasOptions ? 'mc' : ($hasTfItems ? 'tf' : 'essay');
    }
    $finalType = in_array($type, ['mc','tf','sa','essay'], true) ? $type : 'mc';
    $rawTfAnswer = strtolower(trim((string)($src['tf_answer'] ?? $src['answer'] ?? $src['answer_text'] ?? 'true')));
    $answerValue = $finalType === 'tf'
        ? (in_array($rawTfAnswer, ['false', 'sai', 's', '0', 'no'], true) ? 'false' : 'true')
        : (in_array($finalType, ['sa', 'essay'], true)
        ? trim($src['answer_text'] ?? $src['answer'] ?? '')
        : trim($src['answer'] ?? $src['answer_text'] ?? ''));
    $data = [
        'subject_id' => (int)($src['subject_id'] ?? 0),
        'lesson_id' => ($src['lesson_id'] ?? '') === '__new__' ? 0 : (int)($src['lesson_id'] ?? 0),
        'lesson_name' => trim($src['lesson_name'] ?? ''),
        'type' => $finalType,
        'content' => trim($src['content'] ?? ''),
        'image_path' => implode('|', normalize_question_image_paths(trim($src['image_path'] ?? ''), trim($src['content'] ?? ''))),
        'answer' => $answerValue,
        'difficulty' => $src['difficulty'] ?? 'unknown',
        'explanation' => trim($src['explanation'] ?? ''),
        'needs_review' => !empty($src['needs_review']) ? 1 : 0,
    ];
    $options = [];
    foreach (($src['options'] ?? []) as $label => $value) {
        $label = strtoupper(trim((string)$label));
        if (preg_match('/^[A-Z]$/', $label)) $options[$label] = trim((string)$value);
    }
    foreach (range('A', 'Z') as $l) {
        if (isset($src['opt_'.$l]) && !isset($options[$l])) $options[$l] = trim((string)$src['opt_'.$l]);
    }
    foreach (['A','B','C','D'] as $l) {
        if (!isset($options[$l])) $options[$l] = '';
    }
    $tfItems = [];
    if ($finalType === 'tf') {
        $tfItems[] = ['label'=>'a','content'=>$data['content'],'answer'=>$answerValue === 'false' ? 'false' : 'true','difficulty'=>$data['difficulty']];
    } else {
        foreach (['a','b','c','d'] as $l) {
            $content = trim($src['tf_items'][$l]['content'] ?? $src['tf_'.$l] ?? '');
            if ($content !== '') $tfItems[] = ['label'=>$l,'content'=>$content,'answer'=>$src['tf_items'][$l]['answer'] ?? $src['tf_ans_'.$l] ?? 'true','difficulty'=>$src['tf_items'][$l]['difficulty'] ?? $data['difficulty']];
        }
    }
    return [$data, $options, $tfItems];
}

function matrix_normalize_text(string $text): string {
    $text = normalize_vietnamese_search_text($text);
    $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);
    return trim(preg_replace('/\s+/', ' ', $text));
}

function matrix_header_key(string $header): ?string {
    $h = matrix_normalize_text($header);
    if ($h === '') return null;
    if (str_contains($h, 'bai hoc') || str_contains($h, 'chu de') || str_contains($h, 'noi dung') || str_contains($h, 'mach kien thuc') || $h === 'de') return 'lesson';
    if (str_contains($h, 'dang cau') || str_contains($h, 'loai cau') || str_contains($h, 'hinh thuc') || str_contains($h, 'kieu cau') || $h === 'type') return 'type';
    if (str_contains($h, 'muc do') || str_contains($h, 'do kho') || str_contains($h, 'cap do') || str_contains($h, 'difficulty')) return 'difficulty';
    if (str_contains($h, 'so cau') || str_contains($h, 'so luong') || $h === 'sl' || $h === 'count') return 'count';
    if (str_contains($h, 'diem') || str_contains($h, 'point')) return 'points';
    return null;
}

function matrix_type_value(string $value): string {
    $v = matrix_normalize_text($value);
    if ($v === 'mc' || str_contains($v, 'trac nghiem')) return 'mc';
    if ($v === 'tf' || str_contains($v, 'dung sai') || str_contains($v, 'dung/sai')) return 'tf';
    if ($v === 'sa' || str_contains($v, 'tra loi ngan') || str_contains($v, 'tu luan ngan')) return 'sa';
    if ($v === 'essay' || str_contains($v, 'tu luan')) return 'essay';
    return '';
}

function matrix_difficulty_value(string $value): string {
    $v = matrix_normalize_text($value);
    if ($v === '') return 'unknown';
    if ($v === 'easy' || str_contains($v, 'nhan biet')) return 'easy';
    if ($v === 'medium' || str_contains($v, 'thong hieu')) return 'medium';
    if ($v === 'hard' || str_contains($v, 'van dung')) return 'hard';
    if (str_contains($v, 'khong ro') || $v === 'unknown') return 'unknown';
    return 'unknown';
}

function matrix_number_value(string $value): float {
    $value = trim(str_replace(',', '.', $value));
    if ($value === '') return 0;
    if (preg_match('/-?\d+(?:\.\d+)?/', $value, $m)) return (float)$m[0];
    return 0;
}

function matrix_read_upload_rows(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return [];
    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (in_array($ext, ['txt', 'docx', 'pdf'], true)) {
        $text = extract_upload_text($file);
        return matrix_text_to_rows($text);
    }
    return [];
}

function matrix_text_to_rows(string $text): array {
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') return [];
    $rows = [];
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $delimiter = null;
        foreach (["\t", '|', ';', ','] as $candidate) {
            if (substr_count($line, $candidate) >= 2) {
                $delimiter = $candidate;
                break;
            }
        }
        if ($delimiter !== null) {
            $rows[] = array_map('trim', str_getcsv($line, $delimiter));
            continue;
        }
        $rows[] = preg_split('/\s{2,}/u', $line) ?: [$line];
    }
    return $rows;
}

function parse_exam_matrix_rows(array $rawRows): array {
    $rawRows = array_values(array_filter($rawRows, fn($row) => count(array_filter($row, fn($v) => trim((string)$v) !== '')) > 0));
    if (!$rawRows) return [];
    if (count(array_filter($rawRows, fn($row) => count(array_filter($row, fn($v) => trim((string)$v) !== '')) > 1)) === 0) {
        $tableRows = matrix_cell_stream_to_rows($rawRows);
        if ($tableRows) $rawRows = $tableRows;
    }
    $headerIndex = 0;
    $map = [];
    foreach ($rawRows as $idx => $row) {
        $candidate = [];
        foreach ($row as $i => $header) {
            $key = matrix_header_key((string)$header);
            if ($key) $candidate[$key] = $i;
        }
        if (isset($candidate['lesson'], $candidate['type'], $candidate['count']) && count($candidate) > count($map)) {
            $map = $candidate;
            $headerIndex = $idx;
            break;
        }
    }
    if (!isset($map['lesson'], $map['type'], $map['count'])) return parse_exam_matrix_loose_rows($rawRows);

    $items = [];
    for ($i = $headerIndex + 1; $i < count($rawRows); $i++) {
        $row = $rawRows[$i];
        $lesson = trim((string)($row[$map['lesson']] ?? ''));
        $type = matrix_type_value((string)($row[$map['type']] ?? ''));
        $count = (int)matrix_number_value((string)($row[$map['count']] ?? ''));
        if ($lesson === '' || $type === '' || $count <= 0) continue;
        $items[] = [
            'lesson_name' => $lesson,
            'type' => $type,
            'difficulty' => isset($map['difficulty']) ? matrix_difficulty_value((string)($row[$map['difficulty']] ?? '')) : 'unknown',
            'count' => $count,
            'points' => isset($map['points']) ? matrix_number_value((string)($row[$map['points']] ?? '')) : 0,
        ];
    }
    return $items ?: parse_exam_matrix_loose_rows($rawRows);
}

function matrix_cell_stream_to_rows(array $rawRows): array {
    $cells = [];
    foreach ($rawRows as $row) {
        foreach ($row as $cell) {
            $cell = trim((string)$cell);
            if ($cell !== '') $cells[] = $cell;
        }
    }
    if (count($cells) < 4) return [];

    $start = null;
    for ($i = 0; $i < count($cells); $i++) {
        if (matrix_header_key($cells[$i]) === 'lesson') {
            $start = $i;
            break;
        }
    }
    if ($start === null) return [];

    $headers = [];
    $keys = [];
    for ($i = $start; $i < count($cells); $i++) {
        $key = matrix_header_key($cells[$i]);
        if (!$key) break;
        $headers[] = $cells[$i];
        $keys[] = $key;
        if (count($headers) >= 8) break;
    }
    if (!in_array('lesson', $keys, true) || !in_array('type', $keys, true) || !in_array('count', $keys, true)) return [];
    $width = count($headers);
    $values = array_slice($cells, $start + $width);
    $rows = [$headers];
    for ($i = 0; $i + $width <= count($values); $i += $width) {
        $rows[] = array_slice($values, $i, $width);
    }
    return count($rows) > 1 ? $rows : [];
}

function parse_exam_matrix_loose_rows(array $rawRows): array {
    $items = [];
    foreach ($rawRows as $row) {
        $line = trim(implode(' ', array_filter(array_map('trim', array_map('strval', $row)))));
        if ($line === '') continue;
        if (matrix_header_key($line) && !matrix_type_value($line)) continue;
        $item = parse_exam_matrix_line($line);
        if ($item) $items[] = $item;
    }
    return $items;
}

function parse_exam_matrix_line(string $line): ?array {
    $line = trim(preg_replace('/\s+/', ' ', $line));
    $type = matrix_type_value($line);
    if ($type === '') return null;

    $count = 0;
    if (preg_match('/(?:số\s*câu|so\s*cau|số\s*lượng|so\s*luong|sl)\s*[:：]?\s*(\d+)/iu', $line, $m)) {
        $count = (int)$m[1];
    } elseif (preg_match('/(\d+)\s*(?:câu|cau)\b/iu', $line, $m)) {
        $count = (int)$m[1];
    }

    $points = 0.0;
    if (preg_match('/(?:điểm|diem)\s*[:：]?\s*(\d+(?:[\.,]\d+)?)/iu', $line, $m) || preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:điểm|diem)\b/iu', $line, $m)) {
        $points = matrix_number_value($m[1]);
    }

    preg_match_all('/\d+(?:[\.,]\d+)?/', $line, $nums);
    $numbers = array_map('matrix_number_value', $nums[0] ?? []);
    if ($count <= 0 && $numbers) {
        $count = count($numbers) >= 2 ? (int)$numbers[count($numbers) - 2] : (int)end($numbers);
    }
    if ($points <= 0 && count($numbers) >= 2) {
        $points = (float)end($numbers);
    }
    if ($count <= 0) return null;

    $difficulty = matrix_difficulty_value($line);
    $lesson = matrix_lesson_from_line($line);
    if ($lesson === '') return null;

    return [
        'lesson_name' => $lesson,
        'type' => $type,
        'difficulty' => $difficulty,
        'count' => $count,
        'points' => $points,
    ];
}

function matrix_lesson_from_line(string $line): string {
    if (preg_match('/(?:bài\s*học|bai\s*hoc|chủ\s*đề|chu\s*de|nội\s*dung|noi\s*dung)\s*[:：]\s*(.*?)(?=\s*(?:dạng\s*câu|dang\s*cau|loại\s*câu|loai\s*cau|mức\s*độ|muc\s*do|độ\s*khó|do\s*kho|số\s*câu|so\s*cau|điểm|diem)\s*[:：]|$)/iu', $line, $m)) {
        return trim($m[1], " \t\n\r\0\x0B-|;,.");
    }
    if (preg_match('/\b(?:trắc\s*nghiệm|trac\s*nghiem|đúng\s*\/?\s*sai|dung\s*\/?\s*sai|trả\s*lời\s*ngắn|tra\s*loi\s*ngan|tự\s*luận|tu\s*luan|mc|tf|sa|essay)\b/iu', $line, $m, PREG_OFFSET_CAPTURE)) {
        $lesson = trim(substr($line, 0, $m[0][1]));
        $lesson = preg_replace('/^(?:stt|tt|\d+)[\.\)\-\s]+/iu', '', $lesson);
        return trim($lesson, " \t\n\r\0\x0B-|;,.");
    }
    $lesson = preg_replace('/(?:dạng\s*câu|dang\s*cau|loại\s*câu|loai\s*cau|mức\s*độ|muc\s*do|độ\s*khó|do\s*kho|số\s*câu|so\s*cau|điểm|diem)\s*[:：]?/iu', ' ', $line);
    $lesson = preg_replace('/\b(?:trắc\s*nghiệm|trac\s*nghiem|đúng\s*\/?\s*sai|dung\s*\/?\s*sai|trả\s*lời\s*ngắn|tra\s*loi\s*ngan|tự\s*luận|tu\s*luan|mc|tf|sa|essay|nhận\s*biết|nhan\s*biet|thông\s*hiểu|thong\s*hieu|vận\s*dụng|van\s*dung)\b/iu', ' ', $lesson);
    $lesson = preg_replace('/\s+\d+(?:[\.,]\d+)?\s*(?:câu|cau|điểm|diem)?\b/iu', ' ', $lesson);
    return trim(preg_replace('/\s+/', ' ', $lesson), " \t\n\r\0\x0B-|;,.");
}

function find_lesson_id_by_name(int $subjectId, string $lessonName): int {
    $target = matrix_normalize_text($lessonName);
    foreach (fetch_lessons($subjectId) as $lesson) {
        $lessonOnly = matrix_normalize_text((string)$lesson['name']);
        $lessonWithSubject = matrix_normalize_text((string)$lesson['subject_name'] . ' - ' . (string)$lesson['name']);
        if ($lessonOnly === $target || $lessonWithSubject === $target) return (int)$lesson['id'];
    }
    return 0;
}

function matrix_availability(array $items, int $subjectId): array {
    $out = [];
    foreach ($items as $item) {
        $lessonId = find_lesson_id_by_name($subjectId, $item['lesson_name']);
        $params = [$subjectId, $lessonId, $item['type']];
        $sql = 'SELECT COUNT(*) FROM questions WHERE subject_id=? AND lesson_id=? AND type=?';
        if ($item['difficulty'] !== 'unknown') {
            $sql .= ' AND difficulty=?';
            $params[] = $item['difficulty'];
        }
        $available = 0;
        if ($lessonId > 0) {
            $st = db()->prepare($sql);
            $st->execute($params);
            $available = (int)$st->fetchColumn();
        }
        $item['lesson_id'] = $lessonId;
        $item['available'] = $available;
        $item['ok'] = $lessonId > 0 && $available >= $item['count'];
        $out[] = $item;
    }
    return $out;
}

function create_exam_from_matrix(int $subjectId, string $title, string $school, int $duration, float $totalPoints, int $baseCode, array $items): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO exams(code,title,school_name,subject_id,duration,total_points,status,created_by) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([(string)$baseCode, $title, $school, $subjectId, $duration, $totalPoints, 'draft', current_user()['id'] ?? null]);
        $examId = (int)$pdo->lastInsertId();
        $selected = [];
        foreach ($items as $item) {
            $params = [$subjectId, (int)$item['lesson_id'], $item['type']];
            $sql = 'SELECT id FROM questions WHERE subject_id=? AND lesson_id=? AND type=?';
            if ($item['difficulty'] !== 'unknown') {
                $sql .= ' AND difficulty=?';
                $params[] = $item['difficulty'];
            }
            $sql .= ' ORDER BY RAND() LIMIT ' . (int)$item['count'];
            $st = $pdo->prepare($sql);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $qid) {
                $selected[] = ['id' => (int)$qid, 'row_points' => (float)$item['points'], 'row_count' => (int)$item['count']];
            }
        }
        if (!$selected) throw new RuntimeException('No questions selected');

        $defaultPoints = distribute_exam_points($totalPoints, count($selected));
        $position = 1;
        $ins = $pdo->prepare('INSERT INTO exam_questions(exam_id,question_id,position,points) VALUES(?,?,?,?)');
        foreach ($selected as $index => $question) {
            $points = $question['row_points'] > 0 && $question['row_count'] > 0 ? $question['row_points'] / $question['row_count'] : ($defaultPoints[$index] ?? 0);
            $ins->execute([$examId, $question['id'], $position++, $points]);
        }
        log_activity('create', 'exam', $examId, 'Đã tạo đề từ ma trận: ' . $title);
        $pdo->commit();
        return $examId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function gemini_api_key(): ?string {
    $key = getenv('GEMINI_API_KEY') ?: '';
    $local = __DIR__ . '/../config/gemini.local.php';
    if ($key === '' && is_file($local)) {
        $cfg = require $local;
        if (is_array($cfg)) $key = trim((string)($cfg['api_key'] ?? ''));
    }
    return $key !== '' ? $key : null;
}

function gemini_model(): string {
    $model = trim((string)(getenv('GEMINI_MODEL') ?: ''));
    $local = __DIR__ . '/../config/gemini.local.php';
    if ($model === '' && is_file($local)) {
        $cfg = require $local;
        if (is_array($cfg)) $model = trim((string)($cfg['model'] ?? ''));
    }
    return $model !== '' ? $model : 'gemini-2.5-flash';
}

function gemini_question_prompt(string $text, string $type, int $count, string $difficulty): string {
    $typeInstruction = $type === 'mixed' ? 'a reasonable mix of mc, tf, sa, and essay' : $type;
    return "You extract and generate questions for Vietnamese teachers. If the document/images are already a test, extract the existing questions and answers in the original order. If they are lesson content, generate up to {$count} questions of type {$typeInstruction}, difficulty {$difficulty}.\n"
        . "Critical OCR/math rules:\n"
        . "- Preserve every math, chemistry, and geometry symbol exactly as seen, including dots, primes, subscripts, powers, angle/triangle signs, vectors, fractions, and expressions like S.ABC, S.ABCD, Delta ABC, A'B'C', Oxyz.\n"
        . "- Do not silently omit uncertain symbols. If a character or phrase is unclear, write [KHONG_RO] at that exact position.\n"
        . "- Do not rewrite math notation based on your guess.\n"
        . "- If a question contains a diagram/image or unreadable symbols from an image, set has_image=true and needs_review=true.\n"
        . "- When images are attached, set source_image_index to the main 1-based Image number that contains the question. If a question uses multiple attached images/formula fragments, also set source_image_indices to all relevant image numbers.\n"
        . "- For true/false questions, put the statement in content and put exactly one answer value in answer: true or false. Do not create a,b,c,d true/false sub-items.\n"
        . "- Return valid JSON only, no markdown fences and no explanation outside JSON.\n"
        . "JSON schema: {\"questions\":[{\"type\":\"mc|tf|sa|essay\",\"question_text\":\"\",\"content\":\"\",\"has_image\":false,\"source_image_index\":1,\"source_image_indices\":[1],\"image_note\":\"\",\"options\":[{\"label\":\"A\",\"text\":\"\"},{\"label\":\"B\",\"text\":\"\"},{\"label\":\"C\",\"text\":\"\"},{\"label\":\"D\",\"text\":\"\"}],\"correct_answer\":\"\",\"answer\":\"A|true|false|short answer\",\"difficulty\":\"easy|medium|hard|unknown\",\"explanation\":\"\",\"needs_review\":false}]}.\n"
        . "Context text, if any:\n" . mb_substr($text, 0, 12000);
}

function gemini_api_generate(array $parts, string $key): ?array {
    $model = rawurlencode(gemini_model());
    $payload = json_encode(['contents' => [['parts' => $parts]]], JSON_UNESCAPED_UNICODE);
    $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 75,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$raw || $status < 200 || $status >= 300) return null;
    return json_decode($raw, true) ?: null;
}

function gemini_decode_questions_response(?array $json, string $type): array {
    if (!$json) return [];
    $answer = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $answer = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($answer)));
    if (preg_match('/\{.*\}/s', $answer, $m)) $answer = $m[0];
    $decoded = json_decode($answer, true);
    return normalize_ai_questions($decoded['questions'] ?? [], $type);
}

function import_image_paths_from_text(string $text): array {
    if (!preg_match_all('/^\s*\[image:\s*(.+?)\s*\]\s*$/mi', $text, $matches)) return [];
    $paths = [];
    foreach ($matches[1] as $path) {
        $path = normalize_question_image_path(trim($path));
        if ($path !== '' && !in_array($path, $paths, true)) $paths[] = $path;
    }
    return $paths;
}

function import_question_image_groups(string $text): array {
    $blocks = split_import_question_blocks($text);
    if (!$blocks) {
        $paths = import_image_paths_from_text($text);
        return $paths ? [$paths] : [];
    }
    $groups = [];
    foreach ($blocks as $block) {
        $groups[] = import_image_paths_from_text($block);
    }
    return $groups;
}

function attach_image_groups_to_questions(array $questions, string $sourceText): array {
    $groups = import_question_image_groups($sourceText);
    if (!$questions || !$groups) return $questions;
    foreach ($questions as $i => &$question) {
        $paths = normalize_question_image_paths((string)($question['image_path'] ?? ''), (string)($question['content'] ?? ''));
        $group = $groups[$i] ?? [];
        if (!$group && count($groups) === 1) $group = $groups[0];
        foreach ($group as $path) {
            if ($path !== '' && !in_array($path, $paths, true)) $paths[] = $path;
        }
        $question['image_path'] = implode('|', array_values(array_unique($paths)));
        if ($question['image_path'] !== '') $question['needs_review'] = (int)($question['needs_review'] ?? 1);
    }
    unset($question);
    return $questions;
}

function attach_block_images_to_questions(array $questions, array $blockImages): array {
    if (!$questions || !$blockImages) return $questions;
    foreach ($questions as $i => &$question) {
        $blockImage = $blockImages[$i] ?? null;
        if (!$blockImage) continue;
        $paths = normalize_question_image_paths((string)($question['image_path'] ?? ''), (string)($question['content'] ?? ''));
        if (!in_array($blockImage, $paths, true)) array_unshift($paths, $blockImage);
        $question['image_path'] = implode('|', array_values(array_unique($paths)));
        $question['needs_review'] = (int)($question['needs_review'] ?? 1);
    }
    unset($question);
    return $questions;
}

function gemini_image_mime(string $path): string {
    $full = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/');
    $bytes = is_file($full) ? (string)file_get_contents($full) : '';
    $ext = detect_image_extension($bytes, strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'png');
    return match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/png',
    };
}

function gemini_generate_questions_from_images(array $imagePaths, string $type, int $count, string $difficulty = 'medium', string $contextText = ''): array {
    $type = normalize_generation_type($type);
    $key = gemini_api_key();
    if (!$key) return [];
    $imagePaths = array_values(array_filter(array_unique(array_map(fn($p) => normalize_question_image_path((string)$p), $imagePaths))));
    if (!$imagePaths) return [];

    $out = [];
    foreach (array_chunk($imagePaths, 8) as $chunkIndex => $chunk) {
        $remaining = max(1, $count - count($out));
        $parts = [[
            'text' => gemini_question_prompt($contextText, $type, $remaining, $difficulty)
                . "\nThe attached images are original question images/pages. OCR them carefully, especially math and geometry notation. Extract all questions in image order and include source_image_index for every question."
        ]];
        foreach ($chunk as $idx => $path) {
            $full = __DIR__ . '/../' . ltrim(str_replace('\\', '/', $path), '/');
            if (!is_file($full)) continue;
            $parts[] = ['text' => "\nImage " . (($chunkIndex * 8) + $idx + 1) . ": {$path}"];
            $parts[] = [
                'inline_data' => [
                    'mime_type' => gemini_image_mime($path),
                    'data' => base64_encode((string)file_get_contents($full)),
                ],
            ];
        }
        $questions = gemini_decode_questions_response(gemini_api_generate($parts, $key), $type);
        foreach ($questions as $i => $question) {
            $indices = array_map('intval', (array)($question['source_image_indices'] ?? []));
            if (!$indices && !empty($question['source_image_index'])) $indices = [(int)$question['source_image_index']];
            $paths = normalize_question_image_paths((string)($question['image_path'] ?? ''), (string)($question['content'] ?? ''));
            foreach ($indices as $sourceIndex) {
                $localIndex = $sourceIndex > 0 ? $sourceIndex - 1 - ($chunkIndex * 8) : -1;
                if (isset($chunk[$localIndex]) && !in_array($chunk[$localIndex], $paths, true)) $paths[] = $chunk[$localIndex];
            }
            $sourcePath = $chunk[$i] ?? ($chunk[0] ?? '');
            if (!$paths && $sourcePath !== '') $paths[] = $sourcePath;
            $question['image_path'] = implode('|', $paths);
            $question['needs_review'] = 1;
            $out[] = $question;
        }
    }
    return array_slice($out, 0, max(1, $count));
}

function normalize_ai_questions(array $items, string $type): array {
    $out = [];
    $type = normalize_generation_type($type);
    $fallbackTypes = question_types();
    foreach ($items as $item) {
        $rawType = strtolower((string)($item['type'] ?? $type));
        $typeAliases = [
            'multiple_choice' => 'mc',
            'choice' => 'mc',
            'true_false' => 'tf',
            'truefalse' => 'tf',
            'short_answer' => 'sa',
            'short' => 'sa',
            'essay_question' => 'essay',
        ];
        $qType = $typeAliases[$rawType] ?? $rawType;
        if (!in_array($qType, question_types(), true)) {
            $qType = $type === 'mixed' ? $fallbackTypes[count($out) % count($fallbackTypes)] : $type;
        }
        $content = trim((string)($item['content'] ?? $item['question'] ?? $item['question_text'] ?? $item['text'] ?? ''));
        $imagePath = trim((string)($item['image_path'] ?? $item['question_image'] ?? ''));
        $needsReview = !empty($item['needs_review']) || !empty($item['has_image']) || $imagePath !== '' || str_contains($content, '[KHONG_RO]');
        $q = [
            'type' => $qType,
            'content' => $content,
            'image_path' => $imagePath,
            'source_image_index' => (int)($item['source_image_index'] ?? $item['image_index'] ?? 0),
            'source_image_indices' => array_values(array_filter(array_map('intval', (array)($item['source_image_indices'] ?? [])))),
            'answer' => trim((string)($item['answer'] ?? $item['correct_answer'] ?? '')),
            'difficulty' => in_array(($item['difficulty'] ?? 'medium'), ['easy','medium','hard','unknown'], true) ? $item['difficulty'] : 'medium',
            'explanation' => trim((string)($item['explanation'] ?? '')),
            'needs_review' => $needsReview ? 1 : 0,
            'options' => [],
            'tf_items' => [],
        ];
        if ($needsReview && !str_contains($q['explanation'], '[Can kiem tra]')) {
            $note = trim((string)($item['image_note'] ?? 'Can kiem tra lai ky hieu/hinh anh tu file goc.'));
            $q['explanation'] = trim($q['explanation'] . "\n[Can kiem tra] " . $note);
        }
        if ($q['type'] === 'mc') {
            $opts = $item['options'] ?? [];
            $normalizedOptions = [];
            if (is_array($opts)) {
                foreach ($opts as $key => $value) {
                    if (is_array($value)) {
                        $label = strtoupper((string)($value['label'] ?? $key));
                        $normalizedOptions[$label] = trim((string)($value['text'] ?? $value['content'] ?? ''));
                    } else {
                        $normalizedOptions[strtoupper((string)$key)] = trim((string)$value);
                    }
                }
            }
            foreach (mc_option_labels($normalizedOptions, $q['answer']) as $label) {
                $q['options'][$label] = trim((string)($normalizedOptions[$label] ?? $normalizedOptions[strtolower($label)] ?? ''));
            }
            if (!preg_match('/^[A-Z]$/', strtoupper($q['answer']))) $q['answer'] = 'A';
        }
        if ($q['type'] === 'tf') {
            foreach (($item['tf_items'] ?? []) as $idx => $it) {
                $label = strtolower($it['label'] ?? chr(97 + (int)$idx));
                $q['tf_items'][$label] = ['label'=>$label,'content'=>trim((string)($it['content'] ?? '')),'answer'=>($it['answer'] ?? 'true') === 'false' ? 'false' : 'true','difficulty'=>$q['difficulty']];
            }
        }
        if ($q['content'] !== '' || $q['image_path'] !== '') $out[] = $q;
    }
    return $out;
}

function gemini_generate_questions(string $text, string $type, int $count, string $difficulty = 'medium', bool $fallback = true): array {
    $type = normalize_generation_type($type);
    $key = gemini_api_key();
    if (!$key || !function_exists('curl_init')) return $fallback ? auto_generate_questions($text, $type, $count) : [];
    $questions = gemini_decode_questions_response(gemini_api_generate([['text' => gemini_question_prompt($text, $type, $count, $difficulty)]], $key), $type);
    return $questions ?: ($fallback ? auto_generate_questions($text, $type, $count) : []);
    $typeInstruction = $type === 'mixed'
        ? "nhiều dạng khác nhau, phối hợp hợp lý giữa mc, tf, sa và essay"
        : "dạng {$type}";
    $prompt = "Bạn là công cụ tạo câu hỏi cho giáo viên Việt Nam. Từ tài liệu bên dưới, hãy tạo đúng {$count} câu hỏi {$typeInstruction}, độ khó {$difficulty}. Với trắc nghiệm, bắt buộc có options A,B,C,D và answer là một chữ A/B/C/D. Với đúng/sai, dùng tf_items có label a,b,c,d và answer true/false. Với tự luận, answer là đáp án gợi ý/rubric. Chỉ trả về JSON thuần dạng {\"questions\":[{\"type\":\"mc|tf|sa|essay\",\"content\":\"...\",\"options\":{\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\"},\"tf_items\":[{\"label\":\"a\",\"content\":\"...\",\"answer\":\"true\"}],\"answer\":\"A\",\"difficulty\":\"easy|medium|hard\",\"explanation\":\"...\"}]}. Tài liệu:\n" . mb_substr($text, 0, 18000);
    $prompt = "Ban la cong cu trich xuat va tao cau hoi cho giao vien Viet Nam. Neu tai lieu ben duoi da la mot de thi, hay TRICH XUAT cac cau hoi co san, tu nhan dien dung type cua tung cau theo tieu de phan nhu TRAC NGHIEM, DUNG/SAI, TRA LOI NGAN, TU LUAN va theo dinh dang dap an. Khong gop nhieu cau vao mot content. Neu tai lieu chi la bai hoc, hay tao toi da {$count} cau hoi {$typeInstruction}, do kho {$difficulty}. Voi trac nghiem: type mc, options A/B/C/D, answer la A/B/C/D. Voi dung/sai: type tf, dung tf_items label a,b,c,d va answer true/false. Voi tra loi ngan: type sa. Voi tu luan: type essay. Chi tra ve JSON thuan dang {\"questions\":[{\"type\":\"mc|tf|sa|essay\",\"content\":\"...\",\"options\":{\"A\":\"...\",\"B\":\"...\",\"C\":\"...\",\"D\":\"...\"},\"tf_items\":[{\"label\":\"a\",\"content\":\"...\",\"answer\":\"true\"}],\"answer\":\"A\",\"difficulty\":\"easy|medium|hard\",\"explanation\":\"...\"}]}. Tai lieu:\n" . mb_substr($text, 0, 18000);
    $typeInstruction = $type === 'mixed' ? 'a reasonable mix of mc, tf, sa, and essay' : $type;
    $prompt = "You extract and generate questions for Vietnamese teachers. If the document below is already a test, extract the existing questions and answers. If it is lesson content, generate up to {$count} questions of type {$typeInstruction}, difficulty {$difficulty}.\n"
        . "Critical OCR/math rules:\n"
        . "- Preserve every math, chemistry, and geometry symbol exactly as seen, including dots, primes, subscripts, powers, angle/triangle signs, and expressions like S.ABC, S.ABCD, Delta ABC, A'B'C', Oxyz.\n"
        . "- Do not silently omit uncertain symbols. If a character or phrase is unclear, write [KHONG_RO] at that exact position.\n"
        . "- Do not rewrite math notation based on your guess.\n"
        . "- If a question appears to contain a diagram/image or unreadable symbols from an image, set has_image=true and needs_review=true.\n"
        . "- Return valid JSON only, no markdown fences and no explanation outside JSON.\n"
        . "JSON schema: {\"questions\":[{\"type\":\"mc|tf|sa|essay\",\"question_text\":\"\",\"content\":\"\",\"has_image\":false,\"image_note\":\"\",\"options\":[{\"label\":\"A\",\"text\":\"\"},{\"label\":\"B\",\"text\":\"\"},{\"label\":\"C\",\"text\":\"\"},{\"label\":\"D\",\"text\":\"\"}],\"tf_items\":[{\"label\":\"a\",\"content\":\"\",\"answer\":\"true\"}],\"correct_answer\":\"\",\"answer\":\"\",\"difficulty\":\"easy|medium|hard|unknown\",\"explanation\":\"\",\"needs_review\":false}]}.\n"
        . "Document:\n" . mb_substr($text, 0, 18000);
    $payload = json_encode(['contents' => [['parts' => [['text' => $prompt]]]]], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 45,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$raw || $status < 200 || $status >= 300) return $fallback ? auto_generate_questions($text, $type, $count) : [];
    $json = json_decode($raw, true);
    $answer = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $answer = trim(preg_replace('/^```json|```$/m', '', trim($answer)));
    if (preg_match('/\{.*\}/s', $answer, $m)) $answer = $m[0];
    $decoded = json_decode($answer, true);
    $questions = normalize_ai_questions($decoded['questions'] ?? [], $type);
    return $questions ?: ($fallback ? auto_generate_questions($text, $type, $count) : []);
}

function collect_preview_questions(array $rows, int $subjectId, int $lessonId, string $lessonName = ''): int {
    $saved = 0;
    $lessonId = ensure_lesson_for_input($subjectId, $lessonId, $lessonName);
    foreach ($rows as $row) {
        if (!empty($row['delete'])) continue;
        $row['subject_id'] = $subjectId;
        $row['lesson_id'] = $lessonId;
        [$data, $options, $tfItems] = question_form_payload($row);
        if ($data['content'] === '' && ($data['image_path'] ?? '') !== '') $data['content'] = 'Cau hoi bang hinh anh';
        if ($data['content'] === '' && ($data['image_path'] ?? '') === '') continue;
        create_question($data, $options, $tfItems);
        $saved++;
    }
    return $saved;
}

function detect_difficulty(string $line): string { $n = preg_match_all('/\*/', $line); return $n === 1 ? 'easy' : ($n === 2 ? 'medium' : ($n >= 3 ? 'hard' : 'unknown')); }
function clean_stars(string $line): string { return trim(preg_replace('/\s*\*{1,3}\s*$/', '', $line)); }

function import_match_text(string $text): string {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = mb_strtolower($text, 'UTF-8');
    $from = ['đ','Đ','à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ','è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ','ì','í','ị','ỉ','ĩ','ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ','ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ','ỳ','ý','ỵ','ỷ','ỹ'];
    $to =   ['d','d','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y'];
    $text = str_replace($from, $to, $text);
    $text = normalize_vietnamese_search_text($text);
    return preg_replace('/\s+/', ' ', trim($text));
}

function is_import_question_line(string $line): bool {
    $line = trim($line);
    $line = preg_replace('/^(?:\[image:\s*.+?\s*\]\s*)+/iu', '', $line);
    if (preg_match('/^(?:cau|câu|question|q)\s*\d{1,4}\s*[\.\:\)\-]/iu', $line)) return true;
    if (preg_match('/^\d{1,4}\s*[\.\)]\s+\S/u', $line)) return true;
    return false;
}

function strip_import_question_prefix(string $line): string {
    $line = trim($line);
    $leadingImages = '';
    if (preg_match('/^((?:\[image:\s*.+?\s*\]\s*)+)/iu', $line, $m)) {
        $leadingImages = trim($m[1]);
        $line = trim(substr($line, strlen($m[0])));
    }
    $line = preg_replace('/^(?:cau|câu|question|q)\s*\d{1,4}\s*[\.\:\)\-]\s*/iu', '', $line);
    $line = preg_replace('/^\d{1,4}\s*[\.\)]\s*/u', '', (string)$line);
    if ($leadingImages !== '') $line = $leadingImages . ' ' . trim((string)$line);
    return trim((string)$line);
}

function parse_import_answer_line(string $line): ?string {
    if (preg_match('/^(?:dap\s*an|đáp\s*án|dáp\s*án|answer|key)\s*[\:\.\-]\s*(.*)$/iu', trim($line), $m)) return trim($m[1]);
    $search = import_match_text($line);
    if (preg_match('/^(?:dap an|answer|key)\s*[\:\.\-]\s*(.*)$/u', $search, $m)) {
        if (preg_match('/[\:\.\-]/u', $line, $sep, PREG_OFFSET_CAPTURE)) {
            return trim(substr($line, $sep[0][1] + 1));
        }
        return trim($m[1]);
    }
    return null;
}

function parse_import_option_line(string $line): ?array {
    if (!preg_match('/^([A-F])\s*[\.\)\:\-]\s*(.+)$/u', trim($line), $m)) return null;
    return [strtoupper($m[1]), clean_stars($m[2])];
}

function parse_import_tf_line(string $line): ?array {
    if (!preg_match('/^([a-d])\s*[\.\)\:\-]\s*(.+)$/u', trim($line), $m)) return null;
    return [strtolower($m[1]), clean_stars($m[2])];
}

function tf_answer_value(string $text): string {
    $text = import_match_text($text);
    return preg_match('/^(d|dung|true|1|yes)\b/u', $text) ? 'true' : 'false';
}

function apply_tf_answer_map(array &$question, string $answerText): void {
    if ($answerText === '') return;
    foreach (preg_split('/[,;]+/', $answerText, -1, PREG_SPLIT_NO_EMPTY) as $part) {
        if (!preg_match('/^\s*([a-d])\s*[\.\-\:\)]\s*(.+?)\s*$/iu', trim($part), $m)) continue;
        $label = strtolower($m[1]);
        if (isset($question['tf_items'][$label])) $question['tf_items'][$label]['answer'] = tf_answer_value($m[2]);
    }
}

function parse_import_answer_key_map(string $text): array {
    $map = [];
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = array_map('trim', explode("\n", $text));
    $inAnswerKey = false;

    foreach ($lines as $line) {
        if ($line === '') continue;
        $search = import_match_text($line);
        if (preg_match('/^(?:bang\s*)?(?:dap an|answer key|answers|key)\b/u', $search)) {
            $inAnswerKey = true;
            $line = preg_replace('/^.*?[\:\-]\s*/u', '', $line);
            $search = import_match_text($line);
        } elseif (!$inAnswerKey && !preg_match('/(?:^|\s)(?:cau\s*)?\d{1,4}\s*[\.\:\)\-]?\s*[A-F](?=\s|$|[,;])/iu', $line)) {
            continue;
        }

        if (preg_match_all('/(?:^|[\s,;|])(?:cau\s*)?(\d{1,4})\s*[\.\:\)\-]?\s*([A-F])(?=$|[\s,;|])/iu', $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $map[(int)$match[1]] = strtoupper($match[2]);
            }
            continue;
        }

        if ($inAnswerKey && preg_match_all('/(?:^|[\s,;|])(\d{1,4})([A-F])(?=$|[\s,;|])/iu', $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $map[(int)$match[1]] = strtoupper($match[2]);
            }
        }
    }

    return $map;
}

function is_import_answer_key_line(string $line): bool {
    $map = parse_import_answer_key_map($line);
    if (count($map) >= 2) return true;
    $search = import_match_text($line);
    return (bool)preg_match('/^(?:bang\s*)?(?:dap an|answer key|answers|key)\b/u', $search)
        && preg_match('/\d{1,4}\s*[\.\:\)\-]?\s*[A-F]\b/iu', $line);
}

function apply_import_answer_key_map(array $questions, array $answerMap): array {
    if (!$questions || !$answerMap) return $questions;
    foreach ($questions as $index => &$question) {
        if (($question['type'] ?? '') !== 'mc') continue;
        $answer = strtoupper(trim((string)($question['answer'] ?? '')));
        if (preg_match('/^[A-Z]$/', $answer)) continue;
        $number = $index + 1;
        if (!empty($answerMap[$number])) $question['answer'] = $answerMap[$number];
    }
    unset($question);
    return $questions;
}

function apply_inline_question_parts(array &$question): void {
    $content = trim((string)($question['content'] ?? ''));
    if ($content === '') return;

    if (preg_match('/\s(?:dap\s*an|đáp\s*án|answer|key)\s*[\:\.\-]\s*(.+)$/iu', $content, $answerMatch, PREG_OFFSET_CAPTURE)) {
        $question['answer'] = trim(($question['answer'] ?? '') . ' ' . $answerMatch[1][0]);
        $content = trim(substr($content, 0, $answerMatch[0][1]));
    }

    if (empty($question['options']) && preg_match_all('/(?:^|\s)([A-F])\s*[\.\)\:\-]\s*(.*?)(?=\s+[A-F]\s*[\.\)\:\-]\s+|$)/u', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        $firstOffset = $matches[0][0][1] ?? null;
        if ($firstOffset !== null && $firstOffset > 0 && count($matches) >= 2) {
            foreach ($matches as $match) {
                $label = strtoupper($match[1][0]);
                $question['options'][$label] = clean_stars($match[2][0]);
            }
            $question['content'] = clean_stars(substr($content, 0, $firstOffset));
            $question['type'] = 'mc';
            return;
        }
    }

    if (($question['type'] ?? '') === 'tf' && empty($question['tf_items']) && preg_match_all('/(?:^|\s)([a-d])\s*[\.\)\:\-]\s*(.*?)(?=\s+[a-d]\s*[\.\)\:\-]\s+|$)/u', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        $firstOffset = $matches[0][0][1] ?? null;
        if ($firstOffset !== null && $firstOffset > 0 && count($matches) >= 2) {
            foreach ($matches as $match) {
                $label = strtolower($match[1][0]);
                $question['tf_items'][$label] = [
                    'label' => $label,
                    'content' => clean_stars($match[2][0]),
                    'answer' => 'true',
                    'difficulty' => 'unknown',
                ];
            }
            $question['content'] = clean_stars(substr($content, 0, $firstOffset));
        }
    }
}

function finish_wayground_question(?array $question, array &$out, string $pendingAnswer): void {
    if (!$question) return;
    if ($pendingAnswer !== '') {
        $question['answer'] = trim(($question['answer'] ?? '') . ' ' . $pendingAnswer);
    }
    apply_inline_question_parts($question);
    if (($question['type'] ?? '') === 'essay' && !empty($question['options'])) {
        $question['type'] = 'mc';
    }
    if (($question['type'] ?? '') === 'essay' && !empty($question['tf_items'])) {
        $question['type'] = 'tf';
    }
    if (($question['type'] ?? '') === 'tf') {
        apply_tf_answer_map($question, (string)($question['answer'] ?? ''));
    }
    if (($question['type'] ?? '') === 'mc' && ($question['answer'] ?? '') !== '') {
        if (preg_match('/[A-F]/i', (string)$question['answer'], $m)) $question['answer'] = strtoupper($m[0]);
    }
    if (($question['content'] ?? '') !== '' || ($question['image_path'] ?? '') !== '') $out[] = $question;
}

function parse_wayground_style_questions(string $text, string $preferredType = 'mixed'): array {
    $preferredType = normalize_generation_type($preferredType);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), fn($line) => $line !== ''));
    if (!$lines) return [];

    $currentType = $preferredType === 'mixed' ? null : $preferredType;
    $current = null;
    $pendingAnswer = '';
    $lastField = '';
    $out = [];
    $sawSection = false;

    foreach ($lines as $line) {
        $headingType = infer_question_type_from_heading($line);
        if ($headingType && !is_import_question_line($line)) {
            finish_wayground_question($current, $out, $pendingAnswer);
            $current = null;
            $pendingAnswer = '';
            $lastField = '';
            $currentType = $headingType;
            $sawSection = true;
            continue;
        }

        if (is_import_question_line($line)) {
            finish_wayground_question($current, $out, $pendingAnswer);
            $questionType = $currentType ?: ($preferredType === 'mixed' ? 'essay' : $preferredType);
            $content = strip_import_question_prefix($line);
            $current = [
                'type' => $questionType,
                'content' => clean_stars($content),
                'image_path' => '',
                'difficulty' => detect_difficulty($content),
                'answer' => '',
                'options' => [],
                'tf_items' => [],
                'explanation' => '',
            ];
            $pendingAnswer = '';
            $lastField = 'content';
            continue;
        }

        if (!$current) continue;

        if (is_import_answer_key_line($line)) {
            continue;
        }

        $answer = parse_import_answer_line($line);
        if ($answer !== null) {
            $current['answer'] = $answer;
            $lastField = 'answer';
            continue;
        }

        $option = parse_import_option_line($line);
        if (($current['type'] === 'mc' || $preferredType === 'mixed') && $option) {
            $current['type'] = 'mc';
            $current['options'][$option[0]] = $option[1];
            $lastField = 'option:' . $option[0];
            continue;
        }

        $tfItem = parse_import_tf_line($line);
        if (($current['type'] === 'tf' || $preferredType === 'mixed') && $tfItem) {
            $current['type'] = 'tf';
            $label = $tfItem[0];
            $current['tf_items'][$label] = [
                'label' => $label,
                'content' => $tfItem[1],
                'difficulty' => detect_difficulty($line),
                'answer' => 'true',
            ];
            $lastField = 'tf:' . $label;
            continue;
        }

        if (preg_match('/^\[image:\s*(.+?)\s*\]$/iu', $line, $m) || preg_match('/^!\[[^\]]*\]\((.+?)\)$/u', $line, $m)) {
            $current['image_path'] = append_question_image_path((string)($current['image_path'] ?? ''), trim($m[1]));
            continue;
        }

        if ($lastField === 'answer') {
            $current['answer'] = trim(($current['answer'] ?? '') . ' ' . $line);
        } elseif (str_starts_with($lastField, 'option:')) {
            $label = substr($lastField, -1);
            $current['options'][$label] = trim(($current['options'][$label] ?? '') . ' ' . clean_stars($line));
        } elseif (str_starts_with($lastField, 'tf:')) {
            $label = substr($lastField, -1);
            $current['tf_items'][$label]['content'] = trim(($current['tf_items'][$label]['content'] ?? '') . ' ' . clean_stars($line));
        } else {
            $current['content'] = trim(($current['content'] ?? '') . ' ' . clean_stars($line));
        }
    }

    finish_wayground_question($current, $out, $pendingAnswer);

    if (!$sawSection && $preferredType === 'mixed') {
        $typed = array_filter($out, fn($q) => in_array($q['type'] ?? '', ['mc','tf','sa','essay'], true) && (($q['type'] !== 'essay') || ($q['answer'] ?? '') !== ''));
        if (!$typed) return [];
    }
    return $out;
}

function normalize_vietnamese_search_text(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $map = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
    ];
    return strtr($text, $map);
}

function infer_question_type_from_heading(string $line): ?string {
    $line = normalize_vietnamese_search_text($line);
    if (str_contains($line, 'dung/sai') || str_contains($line, 'dung sai')) return 'tf';
    if (str_contains($line, 'tra loi ngan')) return 'sa';
    if (str_contains($line, 'tu luan')) return 'essay';
    if (str_contains($line, 'trac nghiem')) return 'mc';
    return null;
}

function tag_mixed_question_sections(string $text): string {
    $currentType = null;
    $out = [];
    foreach (explode("\n", $text) as $line) {
        $headingType = infer_question_type_from_heading($line);
        $isQuestionLine = preg_match('/^\s*(?:Cau|CÃ¢u|CÃƒÂ¢u|Câu)\s+\d+\s*[\.:]/iu', $line);
        if ($headingType && !$isQuestionLine) {
            $currentType = $headingType;
            continue;
        }
        if ($headingType) $currentType = $headingType;
        if ($currentType && $isQuestionLine && !preg_match('/\[type:(mc|tf|sa|essay)\]/i', $line)) {
            $line .= ' [type:' . $currentType . ']';
        }
        $out[] = $line;
    }
    return implode("\n", $out);
}

function parse_mixed_numbered_questions(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = preg_replace('/\b((?:PHAN|PHẦN|PART|I+\.|[A-D]\.)?[^0-9]{0,80}?(?:TRẮC NGHIỆM|TRAC NGHIEM|ĐÚNG\/SAI|DUNG\/SAI|ĐÚNG SAI|DUNG SAI|TRẢ LỜI NGẮN|TRA LOI NGAN|TỰ LUẬN|TU LUAN)[^0-9]{0,80})/iu', "\n$1\n", $text);
    $text = preg_replace('/\s+(\d{1,3}\s*[\.)]\s+)/u', "\n$1", $text);
    $parts = preg_split('/\n+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    $currentType = 'essay';
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        $headingType = infer_question_type_from_heading($part);
        if ($headingType) {
            $currentType = $headingType;
            continue;
        }
        if (!preg_match('/^(\d{1,3})\s*[\.)]\s*(.+)$/u', $part, $m)) {
            continue;
        }
        $content = trim($m[2]);
        if ($content === '') continue;
        $q = ['type'=>$currentType,'content'=>$content,'image_path'=>'','difficulty'=>'unknown','answer'=>'','options'=>[],'tf_items'=>[],'explanation'=>''];
        if ($currentType === 'mc') {
            $q['content'] = trim(preg_replace('/\s+[A-F]\s*[\.)]\s+.+$/u', '', $content));
            if (preg_match_all('/\b([A-F])\s*[\.)]\s*(.*?)(?=\s+[A-F]\s*[\.)]\s+|\s*(?:Đáp án|Dap an)\s*:|$)/iu', $content, $opts, PREG_SET_ORDER)) {
                foreach ($opts as $op) $q['options'][strtoupper($op[1])] = trim($op[2]);
            }
            if (preg_match('/(?:Đáp án|Dap an)\s*:\s*([A-F])/iu', $content, $ans)) $q['answer'] = strtoupper($ans[1]);
        }
        if ($currentType === 'tf') {
            if (preg_match_all('/\b([a-d])\s*[\.)]\s*(.*?)(?=\s+[a-d]\s*[\.)]\s+|$)/iu', $content, $items, PREG_SET_ORDER)) {
                foreach ($items as $it) {
                    $label = strtolower($it[1]);
                    $q['tf_items'][$label] = ['label'=>$label,'content'=>trim($it[2]),'answer'=>'true','difficulty'=>'unknown'];
                }
            }
        }
        $out[] = $q;
    }
    return $out;
}

function parse_questions_text(string $text, string $type): array {
    $type = normalize_generation_type($type);
    $isMixed = $type === 'mixed';
    $originalText = $text;
    $answerMap = parse_import_answer_key_map($originalText);
    $wayground = parse_wayground_style_questions($originalText, $type);
    if (count($wayground) >= 1) return apply_import_answer_key_map($wayground, $answerMap);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if ($isMixed) $text = tag_mixed_question_sections($text);
    if (!preg_match('/^\s*(?:(?:Cau|Câu|Question|Q)\s*\d{1,4}|\d{1,4})\s*[\.\:\)\-]/miu', $text) && preg_match_all('/^\[image:\s*(.+?)\s*\]$/mi', $text, $matches)) {
        return array_map(fn($path) => [
            'type' => $isMixed ? 'essay' : $type,
            'content' => 'Cau hoi bang hinh anh',
            'image_path' => trim($path),
            'difficulty' => 'unknown',
            'answer' => '',
            'options' => [],
            'tf_items' => [],
        ], $matches[1]);
    }
    $questionStartPattern = '/(?=^\s*(?:(?:Cau|Câu|Question|Q)\s*\d{1,4}|\d{1,4})\s*[\.\:\)\-])/miu';
    $blocks = preg_split($questionStartPattern, $text, -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    foreach ($blocks as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn($x)=>$x!==''));
        if (!$lines) continue;
        $first = array_shift($lines);
        if (preg_match('/\[type:(mc|tf|sa|essay)\]/i', $first, $m)) {
            $type = $isMixed ? strtolower($m[1]) : $type;
            $first = trim(preg_replace('/\s*\[type:(mc|tf|sa|essay)\]\s*/i', ' ', $first));
        }
        $difficulty = detect_difficulty($first);
        $content = clean_stars(strip_import_question_prefix($first));
        $q = ['type'=>$isMixed ? 'essay' : $type,'content'=>$content,'image_path'=>'','difficulty'=>$difficulty,'answer'=>null,'options'=>[],'tf_items'=>[]];
        if (preg_match('/^\[image:\s*(.+?)\s*\]$/iu', $first, $m) || preg_match('/^!\[[^\]]*\]\((.+?)\)$/u', $first, $m)) {
            $q['content'] = '';
            $q['image_path'] = append_question_image_path($q['image_path'], trim($m[1]));
        }
        foreach ($lines as $line) {
            if (preg_match('/^\[image:\s*(.+?)\s*\]$/iu', $line, $m) || preg_match('/^!\[[^\]]*\]\((.+?)\)$/u', $line, $m)) {
                $q['image_path'] = append_question_image_path($q['image_path'], trim($m[1]));
                continue;
            }
            if (is_import_answer_key_line($line)) continue;
            $answer = parse_import_answer_line($line);
            if ($answer !== null) { $q['answer'] = $answer; continue; }
            $option = parse_import_option_line($line);
            if (($type === 'mc' || $isMixed) && $option) { $q['type'] = 'mc'; $q['options'][$option[0]] = $option[1]; continue; }
            $tfItem = parse_import_tf_line($line);
            if (($type === 'tf' || $isMixed) && $tfItem) { $q['type'] = 'tf'; $label = $tfItem[0]; $q['tf_items'][$label] = ['label'=>$label, 'content'=>$tfItem[1], 'difficulty'=>detect_difficulty($line), 'answer'=>'true']; continue; }
            if ($q['answer']) $q['answer'] .= ' ' . $line; else $q['content'] .= ' ' . clean_stars($line);
        }
        if ($q['type'] === 'tf' && $q['answer']) {
            foreach ($q['tf_items'] as &$it) if (preg_match('/'.$it['label'].'\s*[-:\)]\s*(đúng|d|true|sai|s|false)/iu', $q['answer'], $m)) $it['answer'] = preg_match('/^(đúng|d|true)$/iu', $m[1]) ? 'true' : 'false';
        }
        if ($q['content'] === '' && $q['image_path'] !== '') $q['content'] = 'Cau hoi bang hinh anh';
        if ($q['content'] || $q['image_path']) $out[] = $q;
    }
    if ($isMixed && count($out) <= 1) {
        $numbered = parse_mixed_numbered_questions($originalText);
        if (count($numbered) > count($out)) return apply_import_answer_key_map($numbered, $answerMap);
    }
    return apply_import_answer_key_map($out, $answerMap);
}

function finalize_import_preview_questions(array $questions, string $sourceText = ''): array {
    $questions = apply_import_answer_key_map($questions, parse_import_answer_key_map($sourceText));
    foreach ($questions as $i => &$question) {
        $question['type'] = in_array(($question['type'] ?? ''), question_types(), true) ? $question['type'] : 'essay';
        $question['content'] = trim((string)($question['content'] ?? ''));
        $question['image_path'] = implode('|', normalize_question_image_paths((string)($question['image_path'] ?? ''), $question['content']));
        $rawDifficulty = $question['difficulty'] ?? 'unknown';
        $question['difficulty'] = in_array($rawDifficulty, ['easy','medium','hard','unknown'], true) ? $rawDifficulty : 'unknown';
        $question['answer'] = trim((string)($question['answer'] ?? ''));
        $question['explanation'] = trim((string)($question['explanation'] ?? ''));
        $question['options'] = is_array($question['options'] ?? null) ? $question['options'] : [];
        $question['tf_items'] = is_array($question['tf_items'] ?? null) ? $question['tf_items'] : [];

        apply_inline_question_parts($question);
        if (($question['type'] ?? '') === 'essay' && !empty(array_filter($question['options'], fn($v) => trim((string)$v) !== ''))) $question['type'] = 'mc';
        if (($question['type'] ?? '') === 'essay' && !empty($question['tf_items'])) $question['type'] = 'tf';
        // Câu tự luận nhưng kèm đáp án ngắn gọn (1 từ/cụm ngắn, không có ảnh inline)
        // -> coi là "trả lời ngắn" (sa) để chấm tự động theo đáp án.
        if (($question['type'] ?? '') === 'essay') {
            $ans = trim((string)($question['answer'] ?? ''));
            $wordCount = $ans === '' ? 0 : count(preg_split('/\s+/u', $ans));
            if ($ans !== '' && mb_strlen($ans) <= 40 && $wordCount <= 6
                && strpos($ans, "\n") === false && !question_content_has_inline_images($ans)) {
                $question['type'] = 'sa';
            }
        }

        if ($question['type'] === 'mc') {
            foreach (mc_option_labels($question['options'], $question['answer']) as $label) {
                $val = trim((string)($question['options'][$label] ?? $question['options'][strtolower($label)] ?? ''));
                // Đáp án là ảnh công thức (WMF) thì chỉ còn lại dấu câu vụn ("." hoặc
                // lẫn nhãn phương án khác như ". C. . D. .") -> để trống cho gọn,
                // giáo viên nhìn ảnh câu hỏi để biết phương án.
                $probe = preg_replace('/[A-Z]\s*[.\):]/u', '', $val);
                if (preg_match('/^[\s.,;:·•\-–—]*$/u', (string)$probe)) $val = '';
                $question['options'][$label] = $val;
            }
            if (preg_match('/[A-Z]/i', $question['answer'], $m)) $question['answer'] = strtoupper($m[0]);
            if (!preg_match('/^[A-Z]$/', $question['answer'])) $question['answer'] = 'A';
        }

        if ($question['type'] === 'tf') {
            apply_tf_answer_map($question, $question['answer']);
            $tfAnswer = strtolower(trim($question['answer']));
            if (in_array($tfAnswer, ['false', 'sai', 's', '0', 'no'], true)) {
                $question['answer'] = 'false';
            } elseif (in_array($tfAnswer, ['true', 'dung', 'đúng', 'd', '1', 'yes'], true)) {
                $question['answer'] = 'true';
            } else {
                $firstTf = $question['tf_items'] ? reset($question['tf_items']) : [];
                $question['answer'] = (($firstTf['answer'] ?? 'true') === 'false') ? 'false' : 'true';
            }
            // Mô hình Đúng/Sai dùng một đáp án duy nhất; bỏ tf_items để preview/editor
            // không còn dữ liệu cũ (4 ý a/b/c/d kèm marker ảnh thô).
            $question['tf_items'] = [];
        }

        if ($question['content'] === '' && $question['image_path'] !== '') $question['content'] = 'Cau hoi bang hinh anh ' . ($i + 1);
        if ($question['image_path'] !== '' && question_content_unreliable($question)) $question['needs_review'] = 1;
    }
    unset($question);
    return array_values(array_filter($questions, fn($q) => trim((string)($q['content'] ?? '')) !== '' || trim((string)($q['image_path'] ?? '')) !== ''));
}

function preserve_import_source_images(array $questions, string $sourceText = '', array $blockImages = []): array {
    if (!$questions) return $questions;

    if ($sourceText !== '') {
        $questions = attach_image_groups_to_questions($questions, $sourceText);
    }
    if ($blockImages) {
        $questions = attach_block_images_to_questions($questions, $blockImages);
    }

    $allImages = import_image_paths_from_text($sourceText);
    if ($allImages) {
        $assignedImages = [];
        foreach ($questions as $question) {
            foreach (normalize_question_image_paths((string)($question['image_path'] ?? ''), (string)($question['content'] ?? '')) as $path) {
                $assignedImages[$path] = true;
            }
        }
        foreach ($questions as $i => &$question) {
            $paths = normalize_question_image_paths((string)($question['image_path'] ?? ''), (string)($question['content'] ?? ''));
            if (!$paths && isset($allImages[$i]) && empty($assignedImages[$allImages[$i]])) {
                $paths[] = $allImages[$i];
            } elseif (!$paths && count($questions) === 1) {
                $paths = array_values(array_filter($allImages, fn($path) => empty($assignedImages[$path])));
            }
            $question['image_path'] = implode('|', array_values(array_unique(array_filter($paths))));
            if ($question['image_path'] !== '') $question['needs_review'] = (int)($question['needs_review'] ?? 1);
        }
        unset($question);
    }

    return finalize_import_preview_questions($questions, $sourceText);
}

function extract_upload_text(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
        $path = save_question_image_from_path($file['tmp_name'], $file['name']);
        return $path ? "Cau 1:\n[image:$path]" : '';
    }
    if ($ext === 'txt') return file_get_contents($file['tmp_name']);
    if ($ext === 'pdf') {
        $text = extract_pdf_text($file['tmp_name']);
        $questionImages = render_pdf_question_clips_to_images($file['tmp_name']);
        if ($questionImages) {
            return merge_question_blocks_with_images($text, $questionImages);
        }
        if (!preg_match('/^\s*(?:(?:Cau|Câu|Question|Q)\s*\d{1,4}|\d{1,4})\s*[\.\:\)\-]/miu', $text)) {
            $pageImages = render_pdf_pages_to_question_images($file['tmp_name']);
            if ($pageImages) return image_question_text_from_paths($pageImages);
        }
        return $text;
    }
    if ($ext === 'docx') {
        $docxText = extract_docx_text_with_inline_media($file['tmp_name']);
        if ($docxText !== '') return $docxText;

        $pdfPath = convert_docx_to_pdf($file['tmp_name']);
        if ($pdfPath) {
            $text = extract_pdf_text($pdfPath);
            $questionImages = render_pdf_question_clips_to_images($pdfPath);
            if ($questionImages) return merge_question_blocks_with_images($text, $questionImages);
            $pageImages = render_pdf_pages_to_question_images($pdfPath);
            if ($pageImages) return image_question_text_from_paths($pageImages);
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
            if (!$xml) {
                $zip->close();
                return '';
            }

            $rels = [];
            if ($relsXml) {
                $relsDoc = new DOMDocument();
                if (@$relsDoc->loadXML($relsXml)) {
                    foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
                        if (str_contains((string)$rel->getAttribute('Type'), '/image')) {
                            $rels[(string)$rel->getAttribute('Id')] = (string)$rel->getAttribute('Target');
                        }
                    }
                }
            }

            $doc = new DOMDocument();
            if (!@$doc->loadXML($xml)) {
                $zip->close();
                return trim(html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }

            $xp = new DOMXPath($doc);
            $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $xp->registerNamespace('m', 'http://schemas.openxmlformats.org/officeDocument/2006/math');
            $xp->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $xp->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

            $out = [];
            foreach ($xp->query('//w:body//w:tbl//w:tr') as $tr) {
                $cells = [];
                foreach ($xp->query('./w:tc', $tr) as $tc) {
                    $parts = [];
                    foreach ($xp->query('.//w:t|.//m:t', $tc) as $t) $parts[] = $t->textContent;
                    $cellText = trim(html_entity_decode(implode('', $parts), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                    if ($cellText !== '') $cells[] = $cellText;
                }
                if (count($cells) >= 2) $out[] = implode('|', $cells);
            }
            foreach ($xp->query('//w:body//w:p') as $pIndex => $p) {
                $parts = [];
                foreach ($xp->query('.//w:t|.//m:t', $p) as $t) $parts[] = $t->textContent;
                $line = trim(html_entity_decode(implode('', $parts), ENT_QUOTES | ENT_XML1, 'UTF-8'));

                $tokens = [];
                $hasInlineMedia = false;
                foreach ($xp->query('./w:r|./m:oMathPara|./m:oMath', $p) as $node) {
                    if ($node->localName === 'oMath' || $node->localName === 'oMathPara') {
                        $formulaParts = [];
                        foreach ($xp->query('.//m:t', $node) as $t) $formulaParts[] = $t->textContent;
                        $formulaText = trim(html_entity_decode(implode(' ', $formulaParts), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                        if ($formulaText !== '') {
                            $tokens[] = ['type' => 'text', 'text' => $formulaText];
                            $hasInlineMedia = true;
                        }
                        continue;
                    }

                    $runTextParts = [];
                    foreach ($xp->query('.//w:t', $node) as $t) $runTextParts[] = $t->textContent;
                    $runText = html_entity_decode(implode('', $runTextParts), ENT_QUOTES | ENT_XML1, 'UTF-8');
                    if (trim($runText) !== '') $tokens[] = ['type' => 'text', 'text' => $runText];

                    foreach ($xp->query('.//a:blip|.//v:imagedata', $node) as $blip) {
                        $rid = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed')
                            ?: $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                        if ($rid === '' || empty($rels[$rid])) continue;
                        $target = str_replace('\\', '/', $rels[$rid]);
                        $zipPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'word/' . ltrim($target, '/');
                        $image = $zip->getFromName($zipPath);
                        if ($image === false) continue;
                        $imagePath = save_question_image_bytes($image, basename($zipPath), $line);
                        if ($imagePath) {
                            $tokens[] = ['type' => 'image', 'path' => $imagePath];
                            $hasInlineMedia = true;
                        }
                    }
                }

                if ($hasInlineMedia && $tokens) {
                    $inlinePath = save_question_inline_tokens_image($tokens, (int)$pIndex + 1);
                    if ($inlinePath) {
                        // Giữ nguyên text của đoạn (gồm cả text công thức đã trích từ m:t)
                        // để câu hỏi/đáp án vẫn nhận đúng vị trí; ảnh công thức được đính
                        // ngay sau đó nên gắn vào đúng câu này.
                        if ($line !== '') $out[] = $line;
                        $out[] = '[image:' . $inlinePath . ']';
                        continue;
                    }
                }

                if ($line !== '') $out[] = $line;
            }
            $zip->close();
            return trim(implode("\n", $out));
        }
    }
    return '';
}

function extract_pdf_text(string $path): string {
    if (!is_file($path)) return '';
    $text = extract_pdf_text_with_pdftotext($path);
    if ($text !== '') return $text;
    $text = extract_pdf_text_with_python($path);
    if ($text !== '') return $text;
    return extract_pdf_text_native($path);
}

function extract_pdf_text_with_pdftotext(string $path): string {
    $cmd = ['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-'];
    $text = run_command_with_timeout($cmd, 12);
    return trim_pdf_text((string)$text);
}

function extract_pdf_text_with_python(string $path): string {
    $script = 'import sys, importlib.util; sys.stdout.reconfigure(encoding="utf-8"); p=sys.argv[1]; text="";'
        . "\nif importlib.util.find_spec('fitz'):\n import fitz\n d=fitz.open(p)\n text='\\n'.join(page.get_text() for page in d)"
        . "\nelif importlib.util.find_spec('pypdf'):\n from pypdf import PdfReader\n r=PdfReader(p)\n text='\\n'.join((page.extract_text() or '') for page in r.pages)"
        . "\nprint(text)";
    $cmd = ['python', '-c', $script, $path];
    $text = run_command_with_timeout($cmd, 12);
    return trim_pdf_text((string)$text);
}

function extract_pdf_text_native(string $path): string {
    $bytes = (string)file_get_contents($path);
    if ($bytes === '') return '';
    preg_match_all('/(<<.*?>>\s*)?stream\s*\r?\n(.*?)\r?\nendstream/s', $bytes, $matches, PREG_SET_ORDER);
    $chunks = [];
    foreach ($matches as $match) {
        $dict = $match[1] ?? '';
        $stream = $match[2] ?? '';
        if (stripos($dict, 'FlateDecode') !== false) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) $decoded = @gzdecode($stream);
            if ($decoded === false) $decoded = @gzinflate(substr($stream, 2));
            if ($decoded === false) continue;
            $stream = $decoded;
        } elseif (stripos($dict, 'ASCIIHexDecode') !== false) {
            $stream = @hex2bin(preg_replace('/[^0-9A-Fa-f]/', '', $stream)) ?: '';
        }
        $text = extract_pdf_stream_text($stream);
        if ($text !== '') $chunks[] = $text;
    }
    return trim_pdf_text(implode("\n", $chunks));
}

function extract_pdf_stream_text(string $stream): string {
    $parts = [];
    if (preg_match_all('/\[((?:\\\\.|[^\]])*)\]\s*TJ/s', $stream, $arrays)) {
        foreach ($arrays[1] as $array) {
            if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)|<([0-9A-Fa-f\s]+)>/s', $array, $items)) {
                foreach ($items[0] as $item) $parts[] = decode_pdf_text_item($item);
                $parts[] = "\n";
            }
        }
    }
    if (preg_match_all('/(\((?:\\\\.|[^\\\\)])*\)|<([0-9A-Fa-f\s]+)>)\s*Tj/s', $stream, $strings)) {
        foreach ($strings[1] as $item) $parts[] = decode_pdf_text_item($item) . "\n";
    }
    if (preg_match_all('/BT(.*?)ET/s', $stream, $blocks)) {
        foreach ($blocks[1] as $block) {
            if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)|<([0-9A-Fa-f\s]+)>/s', $block, $items)) {
                foreach ($items[0] as $item) $parts[] = decode_pdf_text_item($item);
                $parts[] = "\n";
            }
        }
    }
    return trim(implode('', $parts));
}

function decode_pdf_text_item(string $item): string {
    $item = trim($item);
    if ($item === '') return '';
    if ($item[0] === '<') {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', trim($item, '<>'));
        if ($hex === '') return '';
        if (strlen($hex) % 2 === 1) $hex .= '0';
        $bytes = @hex2bin($hex);
        if ($bytes === false) return '';
        if (strncmp($bytes, "\xFE\xFF", 2) === 0 && function_exists('mb_convert_encoding')) return mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');
        if (strpos($bytes, "\x00") !== false && function_exists('mb_convert_encoding')) return mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
        return $bytes;
    }
    $text = substr($item, 1, -1);
    $text = preg_replace_callback('/\\\\([nrtbf\\\\()])|\\\\([0-7]{1,3})/s', function ($m) {
        if (($m[2] ?? '') !== '') return chr(octdec($m[2]));
        return ['n'=>"\n",'r'=>"\r",'t'=>"\t",'b'=>"\b",'f'=>"\f",'\\'=>'\\','('=>'(',')'=>')'][$m[1]] ?? $m[1];
    }, $text);
    return (string)$text;
}

function trim_pdf_text(string $text): string {
    $text = str_replace("\0", '', $text);
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

function auto_generate_questions(string $text, string $type, int $count): array {
    $type = normalize_generation_type($type);
    $types = question_types();
    $plain = preg_replace('/\s+/', ' ', strip_tags($text));
    $sentences = preg_split('/(?<=[\.\?\!])\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY);
    $sentences = array_values(array_filter($sentences, fn($s)=>mb_strlen($s) > 35));
    $result = [];
    for ($i=0; $i<$count && $i<count($sentences); $i++) {
        $s = trim($sentences[$i]);
        $qType = $type === 'mixed' ? $types[$i % count($types)] : $type;
        if ($qType === 'mc') $result[] = ['type'=>'mc','content'=>'Theo tài liệu, nội dung nào sau đây là đúng: ' . $s, 'difficulty'=>'medium','answer'=>'A','options'=>['A'=>$s,'B'=>'Một nhận định không phù hợp với tài liệu','C'=>'Một nội dung không được đề cập','D'=>'Tất cả các đáp án trên đều sai']];
        elseif ($qType === 'tf') $result[] = ['type'=>'tf','content'=>$s, 'difficulty'=>'medium','answer'=>'true','tf_items'=>[]];
        elseif ($qType === 'sa') $result[] = ['type'=>'sa','content'=>'Trình bày ngắn gọn ý chính của đoạn: ' . $s, 'difficulty'=>'medium','answer'=>$s];
        else $result[] = ['type'=>'essay','content'=>'Phân tích và mở rộng ý sau dựa trên tài liệu: ' . $s, 'difficulty'=>'hard','answer'=>'Học sinh trình bày đúng trọng tâm, có dẫn chứng và lập luận rõ ràng.'];
    }
    return $result;
}
