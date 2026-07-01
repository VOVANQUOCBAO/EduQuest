<?php
// ============================================================
//  Front controller cho Vercel (vercel-php).
//  Vercel Hobby gioi han 12 serverless function nen khong the
//  moi file .php thanh 1 function. Thay vao do dinh tuyen moi
//  request toi dung file .php o thu muc goc qua 1 function duy nhat.
// ============================================================
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$rel  = ltrim(rawurldecode((string)$uri), '/');
if ($rel === '') $rel = 'index.php';

// Chong path traversal
$rel = str_replace('\\', '/', $rel);
if (strpos($rel, '..') !== false) { http_response_code(400); exit('Bad request'); }

$root = dirname(__DIR__);

// Neu khong tro toi file .php cu the thi mac dinh ve index.php
if (substr($rel, -4) !== '.php') $rel = 'index.php';

$target = $root . '/' . $rel;
if (!is_file($target)) { http_response_code(404); exit('Not found: ' . htmlspecialchars($rel)); }

chdir(dirname($target));
require $target;
