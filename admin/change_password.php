<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once 'admin_helpers.php';
check_admin_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!is_string($currentPassword) || !is_string($newPassword) || !is_string($confirmPassword)) {
        $error = 'Thông tin mật khẩu không hợp lệ.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } else {
        $stmt = $conn->prepare('SELECT password FROM admins WHERE id = ?');
        $adminId = (int) $_SESSION['admin_id'];
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin || !password_verify($currentPassword, $admin['password'])) {
            $error = 'Mật khẩu hiện tại không đúng.';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $stmt->bind_param('si', $passwordHash, $adminId);
            if ($stmt->execute()) {
                $success = 'Đổi mật khẩu thành công.';
            } else {
                $error = 'Không thể cập nhật mật khẩu. Vui lòng thử lại.';
            }
            $stmt->close();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi mật khẩu Admin - Bảo tàng Khmer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: #c8961e;
            color: #f8f5ed;
        }
        .password-card {
            width: 100%;
            max-width: 480px;
            padding: 2rem;
            border-radius: 16px;
            background: #121212;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .3);
        }
        .password-heading { margin-bottom: 1.5rem; text-align: center; }
        .password-heading h1 {
            color: #f5dfa0;
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.45;
        }
        .password-heading p { margin: 0; color: #c8c8c8; }
        .password-card .form-label { font-weight: 600; }
        .password-card .input-group-text,
        .password-card .form-control {
            min-height: 44px;
            border-color: #414141;
            background-color: #121212;
            color: #f8f5ed;
        }
        .password-card .input-group-text { color: #c8c8c8; }
        .password-card .form-control::placeholder { color: #aeb4bc; }
        .password-card .form-control:focus {
            border-color: #8B1A1A;
            box-shadow: 0 0 0 .2rem rgba(139, 26, 26, .25);
        }
        .btn-password {
            width: 100%;
            padding: .75rem;
            border: 0;
            border-radius: 8px;
            background: #b42323;
            color: #fff;
            font-weight: 600;
        }
        .btn-password:hover { background: #8f1717; color: #fff; }
        .password-back { display: block; margin-top: 1rem; color: #f5dfa0; text-align: center; text-decoration: none; }
        .password-back:hover { color: #fff0bd; }
        @media (max-width: 480px) {
            .password-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <main class="password-card">
        <header class="password-heading">
            <h1>BẢO TÀNG VĂN HÓA DÂN TỘC KHMER<br>TỈNH VĨNH LONG</h1>
            <p>Đổi mật khẩu quản trị</p>
        </header>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="mb-3">
                <label class="form-label" for="current_password">Mật khẩu hiện tại</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input class="form-control" id="current_password" name="current_password" type="password" required autocomplete="current-password">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="new_password">Mật khẩu mới</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                    <input class="form-control" id="new_password" name="new_password" type="password" minlength="6" required autocomplete="new-password">
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label" for="confirm_password">Xác nhận mật khẩu mới</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                    <input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="6" required autocomplete="new-password">
                </div>
            </div>
            <button class="btn btn-password" type="submit"><i class="fas fa-key me-2"></i>Cập nhật mật khẩu</button>
            <a class="password-back" href="index.php"><i class="fas fa-arrow-left me-1"></i>Quay lại trang quản trị</a>
        </form>
    </main>
</body>
</html>
