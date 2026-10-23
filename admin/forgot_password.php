<?php
session_start();
require_once '../config/db.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

// Bước 1: Xác minh username + câu trả lời bảo mật
// Bước 2: Đặt mật khẩu mới
$step    = $_SESSION['reset_step'] ?? 1;
$error   = '';
$success = '';

// --- BƯỚC 1: Xác minh ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $username = escape(trim($_POST['username'] ?? ''));
    $answer   = strtolower(trim($_POST['security_answer'] ?? ''));

    $result = query("SELECT * FROM admins WHERE username = '$username' LIMIT 1");
    $admin  = $result->fetch_assoc();

    if ($admin) {
        $stored = strtolower($admin['security_answer'] ?? '');
        // Nếu chưa cài câu trả lời, dùng giá trị mặc định
        if (empty($stored)) $stored = 'khmer vinh long';

        if ($answer === $stored) {
            $_SESSION['reset_step']     = 2;
            $_SESSION['reset_admin_id'] = $admin['id'];
            $step = 2;
        } else {
            $error = 'Câu trả lời bảo mật không đúng.';
        }
    } else {
        $error = 'Tên đăng nhập không tồn tại.';
    }
}

// --- BƯỚC 2: Đặt mật khẩu mới ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $admin_id     = $_SESSION['reset_admin_id'] ?? 0;

    if (strlen($new_pass) < 6) {
        $error = 'Mật khẩu tối thiểu 6 ký tự.';
        $step  = 2;
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Mật khẩu xác nhận không khớp.';
        $step  = 2;
    } elseif (!$admin_id) {
        $error = 'Phiên làm việc hết hạn. Vui lòng thử lại.';
        $step  = 1;
    } else {
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        query("UPDATE admins SET password = '$hashed' WHERE id = $admin_id");
        unset($_SESSION['reset_step'], $_SESSION['reset_admin_id']);
        $success = true;
        $step    = 3;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - Bảo Tàng Khmer Vĩnh Long</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #8B1A1A 0%, #5a0f0f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-box {
            background: white;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo { text-align: center; margin-bottom: 28px; }
        .logo i { font-size: 44px; color: #8B1A1A; }
        .logo h5 { color: #8B1A1A; font-weight: 700; margin-top: 10px; font-size: 15px; }
        .btn-main {
            background: linear-gradient(135deg, #8B1A1A, #C8961E);
            border: none; color: white;
            width: 100%; padding: 11px;
            border-radius: 8px; font-weight: 600;
        }
        .btn-main:hover { opacity: 0.9; color: white; }
        .form-control:focus {
            border-color: #8B1A1A;
            box-shadow: 0 0 0 0.2rem rgba(139,26,26,0.2);
        }
        .step-indicator { display: flex; justify-content: center; gap: 8px; margin-bottom: 24px; }
        .step-dot {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
            background: #dee2e6; color: #6c757d;
        }
        .step-dot.active { background: #8B1A1A; color: white; }
        .step-dot.done   { background: #28a745; color: white; }
        .back-link { text-align: center; margin-top: 18px; }
        .back-link a { color: #8B1A1A; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
<div class="card-box">
    <div class="logo">
        <i class="fas fa-key"></i>
        <h5>QUÊN MẬT KHẨU<br><small class="fw-normal text-muted">Bảo Tàng Khmer Vĩnh Long</small></h5>
    </div>

    <!-- Step indicator -->
    <div class="step-indicator">
        <div class="step-dot <?php echo $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>">
            <?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?>
        </div>
        <div class="step-dot <?php echo $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>">
            <?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?>
        </div>
        <div class="step-dot <?php echo $step >= 3 ? 'done' : ''; ?>">
            <?php echo $step >= 3 ? '<i class="fas fa-check"></i>' : '3'; ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 mb-3">
            <i class="fas fa-exclamation-circle me-1"></i><?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <!-- Bước 1: Xác minh danh tính -->
        <p class="text-muted small text-center mb-3">Nhập tên đăng nhập và trả lời câu hỏi bảo mật</p>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Tên đăng nhập</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control"
                           placeholder="Nhập tên đăng nhập"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Câu hỏi bảo mật</label>
                <div class="alert alert-light py-2 mb-2 small">
                    <i class="fas fa-question-circle text-warning me-1"></i>
                    <strong>Tên hệ thống bảo tàng?</strong>
                </div>
                <input type="text" name="security_answer" class="form-control"
                       placeholder="Nhập câu trả lời..." required>
            </div>
            <button type="submit" name="verify" class="btn btn-main">
                <i class="fas fa-shield-alt me-2"></i>Xác minh
            </button>
        </form>

    <?php elseif ($step === 2): ?>
        <!-- Bước 2: Đặt mật khẩu mới -->
        <p class="text-muted small text-center mb-3">Xác minh thành công. Nhập mật khẩu mới</p>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Mật khẩu mới</label>
                <div class="input-group">
                    <input type="password" name="new_password" id="new_password"
                           class="form-control" placeholder="Tối thiểu 6 ký tự" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePass('new_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Xác nhận mật khẩu</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password"
                           class="form-control" placeholder="Nhập lại mật khẩu" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePass('confirm_password')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" name="reset" class="btn btn-main">
                <i class="fas fa-save me-2"></i>Đặt mật khẩu mới
            </button>
        </form>

    <?php elseif ($step === 3): ?>
        <!-- Bước 3: Thành công -->
        <div class="text-center py-2">
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h5 class="fw-bold">Đặt lại thành công!</h5>
            <p class="text-muted mb-4">Mật khẩu của bạn đã được cập nhật. Hãy đăng nhập lại.</p>
            <a href="login.php" class="btn btn-main">
                <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập ngay
            </a>
        </div>
    <?php endif; ?>

    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left me-1"></i>Quay lại đăng nhập</a>
    </div>
</div>

<script>
function togglePass(id) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
