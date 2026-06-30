<?php
require_once __DIR__ . '/includes/functions.php';

if (!is_logged_in()) redirect('login.php');

redirect(current_user()['role'] === 'admin' ? 'admin-dashboard.php' : 'dashboard.php');
