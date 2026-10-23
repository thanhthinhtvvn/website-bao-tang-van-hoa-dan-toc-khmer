<?php
session_start();
require_once 'config/db.php';

// Đã đăng nhập thì về trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    // Validate
    if (!$full_name) $errors[] = 'Vui lòng nhập họ và tên.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email không hợp lệ.';
    if (strlen($password) < 6) $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';
    if ($password !== $confirm) $errors[] = 'Mật khẩu xác nhận không khớp.';

    if (empty($errors)) {
        // Kiểm tra email đã tồn tại
        $escaped_email = escape($email);
        $check = query("SELECT id FROM users WHERE email = '$escaped_email' LIMIT 1");
        if ($check->num_rows > 0) {
            $errors[] = 'Email này đã được đăng ký. Vui lòng dùng email khác hoặc đăng nhập.';
        } else {
            $hashed   = password_hash($password, PASSWORD_BCRYPT);
            $name_esc = escape($full_name);
            $phone_esc = escape($phone);
            query("INSERT INTO users (full_name, email, phone, password)
                   VALUES ('$name_esc', '$escaped_email', '$phone_esc', '$hashed')");
            $success = true;
        }
    }
}

$page_title = 'Đăng ký tài khoản - Bảo Tàng Khmer Vĩnh Long';
include 'includes/header.php';
?>

<div class="bg-primary-custom text-white py-4">
    <div class="container">
        <h2 class="mb-0"><i class="fas fa-user-plus me-2"></i>Đăng ký tài khoản</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 mt-1">
                <li class="breadcrumb-item"><a href="index.php" class="text-warning">Trang chủ</a></li>
                <li class="breadcrumb-item active text-white">Đăng ký tài khoản</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <?php if ($success): ?>
                <div class="card border-0 shadow-sm text-center p-5">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h4 class="fw-bold">Đăng ký thành công!</h4>
                    <p class="text-muted mb-4">Tài khoản của bạn đã được tạo. Hãy đăng nhập để tiếp tục.</p>
                    <a href="user_login.php" class="btn btn-primary-custom">
                        <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập ngay
                    </a>
                </div>
            <?php else: ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-4 text-primary-custom text-center">Tạo tài khoản mới</h5>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger py-2">
                                <?php foreach ($errors as $e): ?>
                                    <div><i class="fas fa-exclamation-circle me-1"></i><?php echo $e; ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control"
                                       placeholder="Nguyễn Văn A"
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control"
                                       placeholder="example@email.com"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Số điện thoại</label>
                                <input type="tel" name="phone" class="form-control"
                                       placeholder="0xxx xxx xxx"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mật khẩu <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password"
                                           class="form-control" placeholder="Tối thiểu 6 ký tự" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePass('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Xác nhận mật khẩu <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirm_password"
                                           class="form-control" placeholder="Nhập lại mật khẩu" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="togglePass('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary-custom w-100 py-2">
                                <i class="fas fa-user-plus me-2"></i>Đăng ký
                            </button>
                        </form>

                        <hr class="my-3">
                        <p class="text-center text-muted small mb-0">
                            Đã có tài khoản?
                            <a href="user_login.php" class="text-primary-custom fw-semibold">Đăng nhập</a>
                        </p>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
function togglePass(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

<?php include 'includes/footer.php'; ?>
