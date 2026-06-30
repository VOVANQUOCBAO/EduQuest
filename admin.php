<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(current_user()['role'] === 'admin' ? 'admin-dashboard.php' : 'dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post('email');
    $pass = $_POST['password'] ?? '';
    $st = db()->prepare('SELECT * FROM users WHERE email=? AND status="active" AND role="admin"');
    $st->execute([$email]);
    $u = $st->fetch();
    $valid = $u && (password_verify($pass, $u['password']) || hash_equals((string)$u['password'], $pass));
    if ($valid) {
        $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
        redirect('admin-dashboard.php');
    }
    flash('Tài khoản admin hoặc mật khẩu không chính xác', 'error');
}

$page_title = 'Đăng nhập Admin';
include __DIR__ . '/includes/header.php';
?>
<div class="login login-pro admin-login">
  <div class="login-shell compact-login">
    <section class="login-showcase admin-showcase">
      <div class="login-brand">
        <img class="brand-logo login-logo" src="img/logoeduquest.png" alt="EduQuest Admin">
        <div>
          <strong>EduQuest Admin</strong>
          <small>System Control Center</small>
        </div>
      </div>

      <div class="login-copy">
        <span class="eyebrow">Admin only</span>
        <h1>Khu vực quản trị hệ thống EduQuest.</h1>
        <p>Quản lý tài khoản, phân quyền, sao lưu dữ liệu và giám sát toàn bộ hoạt động thi trực tuyến.</p>
      </div>

      <div class="login-metrics">
        <div><span class="material-symbols-outlined">manage_accounts</span><strong>Users</strong><small>Phân quyền</small></div>
        <div><span class="material-symbols-outlined">backup</span><strong>Backup</strong><small>Dữ liệu</small></div>
        <div><span class="material-symbols-outlined">analytics</span><strong>Report</strong><small>Thống kê</small></div>
      </div>
    </section>

    <section class="login-panel">
      <a class="back-link" href="login.php"><span class="material-symbols-outlined">arrow_back</span> Chọn cổng khác</a>
      <div class="login-panel-head">
        <div class="login-icon"><span class="material-symbols-outlined">lock</span></div>
        <div>
          <h2>Đăng nhập Admin</h2>
          <p class="muted">Chỉ tài khoản có vai trò admin được truy cập.</p>
        </div>
      </div>

      <form method="post" class="login-form">
        <label>Email admin</label>
        <div class="input-icon">
          <span class="material-symbols-outlined">mail</span>
          <input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>

        <label>Mật khẩu</label>
        <div class="input-icon">
          <span class="material-symbols-outlined">key</span>
          <input name="password" type="password" required>
          <button class="password-toggle" type="button" data-password-toggle title="Ẩn/hiện mật khẩu">
            <span class="material-symbols-outlined">visibility</span>
          </button>
        </div>

        <button class="btn primary login-submit">
          <span class="material-symbols-outlined">login</span>
          Vào trang Admin
        </button>
      </form>
    </section>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
