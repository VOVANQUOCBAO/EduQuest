<?php
require_once __DIR__ . '/includes/functions.php';
redirect('exam-view.php?id=' . (int)($_GET['id'] ?? 0));
