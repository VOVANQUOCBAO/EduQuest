<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
ensure_exam_points_columns();

$attemptId = (int)post('attempt_id');
$examId = (int)post('exam_id');
$answers = $_POST['answer'] ?? [];
$pdo = db();

$st = $pdo->prepare('SELECT q.*,eq.points FROM exam_questions eq JOIN questions q ON q.id=eq.question_id WHERE eq.exam_id=? ORDER BY eq.position');
$st->execute([$examId]);
$qs = $st->fetchAll();

$score = 0.0;
$max = 0.0;
$pdo->prepare('DELETE FROM attempt_answers WHERE attempt_id=?')->execute([$attemptId]);

foreach ($qs as $q) {
    $questionPoints = (float)($q['points'] ?? 1);
    $max += $questionPoints;
    $ans = $answers[$q['id']] ?? '';
    $correct = null;
    $points = 0.0;

    if ($q['type'] === 'mc') {
        $correct = strtoupper((string)$ans) === strtoupper((string)$q['answer']);
        $points = $correct ? $questionPoints : 0;
    } elseif ($q['type'] === 'tf') {
        $items = tf_items($q['id']);
        $ok = 0;
        foreach ($items as $it) {
            if (($ans[$it['label']] ?? '') === $it['answer']) $ok++;
        }
        $ratio = count($items) ? $ok / count($items) : 0;
        $points = round($questionPoints * $ratio, 2);
        $correct = $ratio >= 1;
        $ans = json_encode($ans, JSON_UNESCAPED_UNICODE);
    } elseif ($q['type'] === 'sa') {
        $correct = mb_strtolower(trim((string)$ans)) === mb_strtolower(trim((string)$q['answer']));
        $points = $correct ? $questionPoints : 0;
    } else {
        $correct = null;
        $points = 0;
    }

    $score += $points;
    $pdo->prepare('INSERT INTO attempt_answers(attempt_id,question_id,answer,is_correct,score) VALUES(?,?,?,?,?)')
        ->execute([$attemptId, $q['id'], is_array($ans) ? json_encode($ans, JSON_UNESCAPED_UNICODE) : $ans, $correct === null ? null : ($correct ? 1 : 0), $points]);
}

$pdo->prepare('UPDATE attempts SET submitted_at=NOW(),score=?,max_score=?,status="submitted" WHERE id=? AND user_id=?')
    ->execute([round($score, 2), round($max, 2), $attemptId, current_user()['id']]);
log_activity('submit', 'attempt', $attemptId, 'Đã nộp bài thi #' . $examId . ' với điểm tự động ' . round($score, 2) . '/' . round($max, 2));
flash('Da nop bai. Diem tu dong: ' . round($score, 2) . '/' . round($max, 2));
redirect('results.php');
