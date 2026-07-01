<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(current_user()['role'] === 'admin' ? 'admin-dashboard.php' : 'dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post('email');
    $pass = $_POST['password'] ?? '';
    $st = db()->prepare('SELECT * FROM users WHERE email=? AND status="active" AND role IN ("teacher","student")');
    $st->execute([$email]);
    $u = $st->fetch();
    $valid = $u && (password_verify($pass, $u['password']) || hash_equals((string)$u['password'], $pass));
    if ($valid) {
        $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']];
        redirect('dashboard.php');
    }
    flash('Tài khoản giáo viên/học sinh hoặc mật khẩu không chính xác', 'error');
}

$page_title = 'Đăng nhập';
include __DIR__ . '/includes/header.php';
?>
<div class="login login-pro user-login">
  <div class="login-shell compact-login">
    <section class="login-showcase user-showcase">
      <div class="login-brand">
        <img class="brand-logo login-logo" src="img/logoeduquest.png" alt="EduQuest">
        <div>
          <strong>EduQuest</strong>
          <small>Teacher & Student Portal</small>
        </div>
      </div>

      <div class="login-copy">
        <span class="eyebrow">Cổng người dùng</span>
        <h1>Đăng nhập dành cho giáo viên và học sinh.</h1>
        <p>EduQuest cổng hệ thống dành cho Giáo viên và Học sinh</p>
      </div>

      <div class="login-metrics">
        <div><span class="material-symbols-outlined">quiz</span><strong>Thi</strong><small>Online</small></div>
        <div><span class="material-symbols-outlined">edit_note</span><strong>Đề</strong><small>Kiểm tra</small></div>
        <div><span class="material-symbols-outlined">analytics</span><strong>Điểm</strong><small>Kết quả</small></div>
      </div>
    </section>

    <section class="login-panel">
      <div class="login-panel-head">
        <div class="login-icon"><span class="material-symbols-outlined">person</span></div>
        <div>
          <h2>Đăng nhập</h2>
          <p class="muted">Chỉ dành cho giáo viên và học sinh.</p>
        </div>
      </div>

      <form method="post" class="login-form">
        <label>Email</label>
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
          Đăng nhập hệ thống
        </button>
      </form>
    </section>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
