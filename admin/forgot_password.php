<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';
require_once '../phpmailer/PHPMailer.php';
require_once '../phpmailer/SMTP.php';
require_once '../phpmailer/Exception.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit();
}

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$storageReady = $conn->query(
    "CREATE TABLE IF NOT EXISTS admin_password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_password_resets_admin_created (admin_id, created_at),
        CONSTRAINT fk_admin_password_resets_admin
            FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$step = 1;
$error = '';
$message = '';

if (!$storageReady) {
    error_log('Could not initialize admin password reset storage: ' . $conn->error);
    $error = 'Chức năng khôi phục mật khẩu hiện chưa khả dụng. Vui lòng liên hệ quản trị hệ thống.';
}

if ($storageReady && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $message = 'Nếu tên đăng nhập và email khớp tài khoản quản trị, liên kết đặt lại sẽ được gửi đến email đó. Hãy kiểm tra cả thư mục thư rác; nếu không nhận được, liên hệ quản trị hệ thống.';

    if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Vui lòng nhập tên đăng nhập và địa chỉ email hợp lệ.';
        $message = '';
    } else {
        try {
            $adminQuery = $conn->prepare('SELECT id, full_name, email FROM admins WHERE username = ? LIMIT 1');
            if (!$adminQuery) {
                throw new mysqli_sql_exception($conn->error);
            }
            $adminQuery->bind_param('s', $username);
            $adminQuery->execute();
            $admin = $adminQuery->get_result()->fetch_assoc();
            $adminQuery->close();

            $recoveryEmail = null;
            if ($admin && strcasecmp($admin['email'], $email) === 0) {
                $recoveryEmail = $admin['email'];
            } elseif ($admin && filter_var(MAIL_ADMIN, FILTER_VALIDATE_EMAIL)
                && strcasecmp(MAIL_ADMIN, $email) === 0) {
                $recoveryEmail = MAIL_ADMIN;
            }

            if ($admin && $recoveryEmail !== null) {
                $rateLimitQuery = $conn->prepare(
                    'SELECT id FROM admin_password_resets
                     WHERE admin_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                     LIMIT 1'
                );
                if (!$rateLimitQuery) {
                    throw new mysqli_sql_exception($conn->error);
                }
                $rateLimitQuery->bind_param('i', $admin['id']);
                $rateLimitQuery->execute();
                $recentRequest = $rateLimitQuery->get_result()->num_rows > 0;
                $rateLimitQuery->close();

                if (!$recentRequest) {
                    $host = $_SERVER['SERVER_NAME'] ?? '';
                    if ($host === '' || !preg_match('/\A[a-zA-Z0-9.-]+\z/', $host)) {
                        throw new RuntimeException('Invalid server name for password reset URL.');
                    }
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);

                    $deleteQuery = $conn->prepare('DELETE FROM admin_password_resets WHERE admin_id = ?');
                    if (!$deleteQuery) {
                        throw new mysqli_sql_exception($conn->error);
                    }
                    $deleteQuery->bind_param('i', $admin['id']);
                    $deleteQuery->execute();
                    $deleteQuery->close();

                    $insertQuery = $conn->prepare(
                        'INSERT INTO admin_password_resets (admin_id, token_hash, expires_at)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
                    );
                    if (!$insertQuery) {
                        throw new mysqli_sql_exception($conn->error);
                    }
                    $insertQuery->bind_param('is', $admin['id'], $tokenHash);
                    $insertQuery->execute();
                    $insertQuery->close();

                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
                    $portSuffix = ($port > 0 && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)))
                        ? ':' . $port
                        : '';
                    $resetUrl = $scheme . '://' . $host . $portSuffix
                        . dirname($_SERVER['SCRIPT_NAME']) . '/forgot_password.php?token=' . rawurlencode($token);

                    try {
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = MAIL_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = MAIL_USERNAME;
                        $mail->Password = MAIL_PASSWORD;
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = MAIL_PORT;
                        $mail->CharSet = 'UTF-8';
                        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
                        $mail->addAddress($recoveryEmail, $admin['full_name']);
                        $mail->Subject = 'Đặt lại mật khẩu quản trị - Bảo tàng Khmer';
                        $mail->Body = "Xin chào {$admin['full_name']},\n\n"
                            . "Hãy mở liên kết sau để đặt lại mật khẩu quản trị. Liên kết chỉ dùng một lần và hết hạn sau 30 phút:\n"
                            . $resetUrl . "\n\n"
                            . "Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.";
                        $mail->send();
                    } catch (\PHPMailer\PHPMailer\Exception $mailError) {
                        $deleteTokenQuery = $conn->prepare('DELETE FROM admin_password_resets WHERE token_hash = ?');
                        if ($deleteTokenQuery) {
                            $deleteTokenQuery->bind_param('s', $tokenHash);
                            $deleteTokenQuery->execute();
                            $deleteTokenQuery->close();
                        } else {
                            error_log('Could not remove unsent admin password reset token: ' . $conn->error);
                        }
                        error_log('Admin password reset email failed: ' . $mailError->getMessage());
                    }
                }
            }
        } catch (mysqli_sql_exception $databaseError) {
            error_log('Admin password reset request failed: ' . $databaseError->getMessage());
            $error = 'Không thể xử lý yêu cầu lúc này. Vui lòng thử lại sau.';
            $message = '';
        } catch (RuntimeException $runtimeError) {
            error_log('Admin password reset URL could not be created: ' . $runtimeError->getMessage());
            $error = 'Không thể tạo liên kết khôi phục lúc này. Vui lòng liên hệ quản trị hệ thống.';
            $message = '';
        }
    }
}

if ($storageReady && isset($_GET['token'])) {
    $token = $_GET['token'];
    if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        $tokenHash = hash('sha256', $token);
        $tokenQuery = $conn->prepare(
            'SELECT id FROM admin_password_resets
             WHERE token_hash = ? AND expires_at > NOW() LIMIT 1'
        );
        if ($tokenQuery) {
            $tokenQuery->bind_param('s', $tokenHash);
            $tokenQuery->execute();
            $validToken = $tokenQuery->get_result()->num_rows > 0;
            $tokenQuery->close();
            if ($validToken) {
                $_SESSION['admin_reset_token_hash'] = $tokenHash;
                $_SESSION['admin_reset_csrf'] = bin2hex(random_bytes(32));
                $step = 2;
            } else {
                $error = 'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu liên kết mới.';
            }
        } else {
            error_log('Could not validate admin password reset token: ' . $conn->error);
            $error = 'Không thể xác minh liên kết lúc này. Vui lòng thử lại sau.';
        }
    } else {
        $error = 'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu liên kết mới.';
    }
}

if ($storageReady && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $new_pass = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm_pass = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    $tokenHash = $_SESSION['admin_reset_token_hash'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['admin_reset_csrf'] ?? '', $csrfToken)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng mở lại liên kết trong email.';
        unset($_SESSION['admin_reset_token_hash'], $_SESSION['admin_reset_csrf']);
    } elseif (strlen($new_pass) < 6) {
        $error = 'Mật khẩu tối thiểu 6 ký tự.';
        $step = 2;
    } elseif ($new_pass !== $confirm_pass) {
        $error = 'Mật khẩu xác nhận không khớp.';
        $step = 2;
    } elseif (!is_string($tokenHash) || !preg_match('/\A[a-f0-9]{64}\z/', $tokenHash)) {
        $error = 'Phiên làm việc hết hạn. Vui lòng yêu cầu liên kết mới.';
    } else {
        try {
            $conn->begin_transaction();
            $tokenQuery = $conn->prepare(
                'SELECT admin_id FROM admin_password_resets
                 WHERE token_hash = ? AND expires_at > NOW() LIMIT 1 FOR UPDATE'
            );
            if (!$tokenQuery) {
                throw new mysqli_sql_exception($conn->error);
            }
            $tokenQuery->bind_param('s', $tokenHash);
            $tokenQuery->execute();
            $reset = $tokenQuery->get_result()->fetch_assoc();
            $tokenQuery->close();

            if (!$reset) {
                $conn->rollback();
                unset($_SESSION['admin_reset_token_hash']);
                $error = 'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu liên kết mới.';
            } else {
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $passwordQuery = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
                if (!$passwordQuery) {
                    throw new mysqli_sql_exception($conn->error);
                }
                $passwordQuery->bind_param('si', $hashed, $reset['admin_id']);
                $passwordQuery->execute();
                $passwordQuery->close();

                $deleteQuery = $conn->prepare('DELETE FROM admin_password_resets WHERE admin_id = ?');
                if (!$deleteQuery) {
                    throw new mysqli_sql_exception($conn->error);
                }
                $deleteQuery->bind_param('i', $reset['admin_id']);
                $deleteQuery->execute();
                $deleteQuery->close();
                $conn->commit();

                unset($_SESSION['admin_reset_token_hash'], $_SESSION['admin_reset_csrf']);
                session_regenerate_id(true);
                $step = 3;
            }
        } catch (mysqli_sql_exception $databaseError) {
            $conn->rollback();
            error_log('Admin password reset failed: ' . $databaseError->getMessage());
            $error = 'Không thể đặt lại mật khẩu lúc này. Vui lòng thử lại sau.';
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu - Bảo tàng Văn hóa Dân tộc Khmer tỉnh Vĩnh Long</title>
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
        <h5>QUÊN MẬT KHẨU<br><small class="fw-normal text-muted">Bảo tàng Văn hóa Dân tộc Khmer tỉnh Vĩnh long</small></h5>
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
            <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <?php if ($message !== ''): ?>
        <div class="alert alert-info py-2 mb-3">
            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <!-- Bước 1: Gửi liên kết đến email đã đăng ký -->
        <p class="text-muted small text-center mb-3">Nhập tên đăng nhập cùng email tài khoản hoặc email khôi phục đã cấu hình</p>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Tên đăng nhập</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control"
                           placeholder="Nhập tên đăng nhập"
                           value="<?php echo htmlspecialchars(is_string($_POST['username'] ?? null) ? $_POST['username'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                           required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Email đã đăng ký</label>
                <input type="email" name="email" class="form-control"
                       placeholder="Nhập email quản trị" required>
            </div>
            <button type="submit" name="request_reset" class="btn btn-main">
                <i class="fas fa-envelope me-2"></i>Gửi liên kết đặt lại
            </button>
        </form>

    <?php elseif ($step === 2): ?>
        <!-- Bước 2: Đặt mật khẩu mới -->
        <p class="text-muted small text-center mb-3">Tạo mật khẩu mới cho tài khoản quản trị</p>
        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?php echo htmlspecialchars($_SESSION['admin_reset_csrf'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Mật khẩu mới</label>
                <div class="input-group">
                    <input type="password" name="new_password" id="new_password"
                           class="form-control" placeholder="Tối thiểu 6 ký tự" minlength="6" required>
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
