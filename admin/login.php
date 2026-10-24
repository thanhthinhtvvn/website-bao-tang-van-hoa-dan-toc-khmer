<?php
session_start();
require_once '../config/db.php';

// Nếu đã đăng nhập thì chuyển thẳng vào dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = escape(trim($_POST['username'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    if ($username && $password) {
        $result = query("SELECT * FROM admins WHERE username = '$username' LIMIT 1");
        $admin = $result->fetch_assoc();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            header('Location: index.php');
            exit();
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
    } else {
        $error = 'Vui lòng nhập đầy đủ thông tin.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập quản trị viên - Bảo tàng Văn hóa Dân tộc Khmer tỉnh Vĩnh long</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #c8961e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #121212;
            color: #f8f5ed;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo i {
            font-size: 48px;
            color: #8B1A1A;
        }
        .login-logo h4 {
            color: #f5dfa0;
            font-weight: 700;
            margin-top: 10px;
            font-size: 16px;
        }
        .login-logo p { color: #c8c8c8 !important; }
        .login-card .input-group-text,
        .login-card .form-control {
            border-color: #414141;
            background-color: #121212;
            color: #f8f5ed;
        }
        .login-card .input-group-text { color: #c8c8c8; }
        .login-card .form-control::placeholder { color: #aeb4bc; }
        .btn-login {
            background: #b42323;
            border: none;
            color: white;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #8f1717;
            color: white;
        }
        .form-control:focus {
            border-color: #8B1A1A;
            box-shadow: 0 0 0 0.2rem rgba(139,26,26,0.25);
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #f5dfa0;
            text-decoration: none;
            font-size: 14px;
        }
        .login-card a[href="forgot_password.php"] { color: #f5dfa0 !important; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <h4>BẢO TÀNG VĂN HÓA DÂN TỘC KHMER<br>TỈNH VĨNH LONG</h4>
            <p class="text-muted small mb-0">Đăng nhập quản trị hệ thống</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>

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
                <label class="form-label fw-semibold">Mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control"
                           placeholder="Nhập mật khẩu" required>
                </div>
            </div>
            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
            </button>
            <div class="text-center mt-3">
                <a href="forgot_password.php" style="color:#8B1A1A;font-size:14px;text-decoration:none;">
                    <i class="fas fa-key me-1"></i>Quên mật khẩu?
                </a>
            </div>
        </form>

        <div class="back-link">
            <a href="../index.php"><i class="fas fa-arrow-left me-1"></i>Quay về trang chủ</a>
        </div>
    </div>
</body>
</html>
