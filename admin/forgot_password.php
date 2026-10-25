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
    "CREATE TABLE IF NOT EXISTS admin_password_reset_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_admin_password_reset_codes_admin (admin_id),
        CONSTRAINT fk_admin_password_reset_codes_admin
            FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$step = 1;
$error = '';
$message = '';
$completed = false;
$username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
$email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$verificationCode = is_string($_POST['verification_code'] ?? null)
    ? trim($_POST['verification_code'])
    : '';
$transactionOpen = false;

if (!$storageReady) {
    error_log('Could not initialize admin password reset code storage: ' . $conn->error);
    $error = 'Chức năng khôi phục mật khẩu hiện chưa khả dụng. Vui lòng liên hệ quản trị hệ thống.';
}

$lookupAdminRecovery = static function (mysqli $connection, string $username, string $email): ?array {
    $query = $connection->prepare('SELECT id, full_name, email FROM admins WHERE username = ? LIMIT 1');
    if (!$query) {
        throw new mysqli_sql_exception($connection->error);
    }
    $query->bind_param('s', $username);
    $query->execute();
    $admin = $query->get_result()->fetch_assoc();
    $query->close();

    if (!$admin) {
        return null;
    }
    if (strcasecmp($admin['email'], $email) === 0) {
        return ['admin' => $admin, 'email' => $admin['email']];
    }
    if (filter_var(MAIL_ADMIN, FILTER_VALIDATE_EMAIL) && strcasecmp(MAIL_ADMIN, $email) === 0) {
        return ['admin' => $admin, 'email' => MAIL_ADMIN];
    }
    return null;
};

$sendResetCode = static function (array $recovery) use ($conn): bool {
    $admin = $recovery['admin'];
    $adminId = (int) $admin['id'];
    $cooldownQuery = $conn->prepare(
        'SELECT id FROM admin_password_reset_codes
         WHERE admin_id = ? AND last_sent_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
         LIMIT 1'
    );
    if (!$cooldownQuery) {
        throw new mysqli_sql_exception($conn->error);
    }
    $cooldownQuery->bind_param('i', $adminId);
    $cooldownQuery->execute();
    $onCooldown = $cooldownQuery->get_result()->num_rows > 0;
    $cooldownQuery->close();
    if ($onCooldown) {
        return false;
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codeHash = password_hash($code, PASSWORD_DEFAULT);
    $saveQuery = $conn->prepare(
        'INSERT INTO admin_password_reset_codes (admin_id, code_hash, expires_at, attempts, created_at, last_sent_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            code_hash = VALUES(code_hash),
            expires_at = VALUES(expires_at),
            attempts = 0,
            created_at = NOW(),
            last_sent_at = NOW()'
    );
    if (!$saveQuery) {
        throw new mysqli_sql_exception($conn->error);
    }
    $saveQuery->bind_param('is', $adminId, $codeHash);
    $saveQuery->execute();
    $saveQuery->close();

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
        $mail->addAddress($recovery['email'], $admin['full_name']);
        $mail->Subject = 'Mã xác nhận đặt lại mật khẩu - Bảo tàng Khmer';
        $mail->Body = "Xin chào {$admin['full_name']},\n\n"
            . "Mã xác nhận đặt lại mật khẩu của bạn là: {$code}\n"
            . "Mã có hiệu lực trong 10 phút, tối đa 5 lần nhập và chỉ sử dụng một lần.\n\n"
            . "Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.";
        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $mailError) {
        $deleteQuery = $conn->prepare('DELETE FROM admin_password_reset_codes WHERE admin_id = ?');
        if ($deleteQuery) {
            $deleteQuery->bind_param('i', $adminId);
            $deleteQuery->execute();
            $deleteQuery->close();
        } else {
            error_log('Could not remove unsent admin password reset code: ' . $conn->error);
        }
        error_log('Admin password reset code email failed: ' . $mailError->getMessage());
        throw $mailError;
    }

    return true;
};

if ($storageReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    try {
        if ($action === 'request_code' || $action === 'resend_code') {
            $step = 2;
            if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $step = 1;
                $error = 'Vui lòng nhập tên đăng nhập và địa chỉ email hợp lệ.';
            } else {
                $message = 'Nếu thông tin khớp, mã xác nhận đã được gửi. Hãy kiểm tra cả thư mục thư rác.';
                $recovery = $lookupAdminRecovery($conn, $username, $email);
                if ($recovery) {
                    $sent = $sendResetCode($recovery);
                    if (!$sent && $action === 'resend_code') {
                        $message = 'Vui lòng chờ một phút trước khi yêu cầu gửi lại mã.';
                    }
                }
            }
        } elseif ($action === 'verify_code') {
            $step = 2;
            if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || !preg_match('/\A[0-9]{6}\z/', $verificationCode)) {
                $error = 'Vui lòng nhập mã xác nhận gồm 6 chữ số.';
            } else {
                $recovery = $lookupAdminRecovery($conn, $username, $email);
                if (!$recovery) {
                    $error = 'Mã xác nhận không hợp lệ hoặc đã hết hạn.';
                } else {
                    $adminId = (int) $recovery['admin']['id'];
                    $conn->begin_transaction();
                    $transactionOpen = true;
                    $codeQuery = $conn->prepare(
                        'SELECT code_hash, attempts FROM admin_password_reset_codes
                         WHERE admin_id = ? AND expires_at > NOW() FOR UPDATE'
                    );
                    if (!$codeQuery) {
                        throw new mysqli_sql_exception($conn->error);
                    }
                    $codeQuery->bind_param('i', $adminId);
                    $codeQuery->execute();
                    $resetCode = $codeQuery->get_result()->fetch_assoc();
                    $codeQuery->close();

                    if (!$resetCode || (int) $resetCode['attempts'] >= 5) {
                        $conn->rollback();
                        $transactionOpen = false;
                        $error = 'Mã xác nhận không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu mã mới.';
                    } elseif (password_verify($verificationCode, $resetCode['code_hash'])) {
                        $conn->commit();
                        $transactionOpen = false;
                        $step = 3;
                    } else {
                        $attemptQuery = $conn->prepare(
                            'UPDATE admin_password_reset_codes SET attempts = attempts + 1 WHERE admin_id = ?'
                        );
                        if (!$attemptQuery) {
                            throw new mysqli_sql_exception($conn->error);
                        }
                        $attemptQuery->bind_param('i', $adminId);
                        $attemptQuery->execute();
                        $attemptQuery->close();
                        $conn->commit();
                        $transactionOpen = false;
                        $error = 'Mã xác nhận không đúng. Vui lòng kiểm tra lại.';
                    }
                }
            }
        } elseif ($action === 'reset_password') {
            $step = 3;
            $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
            $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

            if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || !preg_match('/\A[0-9]{6}\z/', $verificationCode)) {
                $step = 2;
                $error = 'Phiên xác nhận không hợp lệ. Vui lòng xác minh mã lại.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'Mật khẩu tối thiểu 6 ký tự.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Mật khẩu xác nhận không khớp.';
            } else {
                $recovery = $lookupAdminRecovery($conn, $username, $email);
                if (!$recovery) {
                    $step = 2;
                    $error = 'Phiên xác nhận không hợp lệ. Vui lòng yêu cầu mã mới.';
                } else {
                    $adminId = (int) $recovery['admin']['id'];
                    $conn->begin_transaction();
                    $transactionOpen = true;
                    $codeQuery = $conn->prepare(
                        'SELECT code_hash, attempts FROM admin_password_reset_codes
                         WHERE admin_id = ? AND expires_at > NOW() FOR UPDATE'
                    );
                    if (!$codeQuery) {
                        throw new mysqli_sql_exception($conn->error);
                    }
                    $codeQuery->bind_param('i', $adminId);
                    $codeQuery->execute();
                    $resetCode = $codeQuery->get_result()->fetch_assoc();
                    $codeQuery->close();

                    if (!$resetCode || (int) $resetCode['attempts'] >= 5) {
                        $conn->rollback();
                        $transactionOpen = false;
                        $step = 2;
                        $error = 'Mã xác nhận không hợp lệ hoặc đã hết hạn. Vui lòng xác minh lại.';
                    } elseif (!password_verify($verificationCode, $resetCode['code_hash'])) {
                        $attemptQuery = $conn->prepare(
                            'UPDATE admin_password_reset_codes SET attempts = attempts + 1 WHERE admin_id = ?'
                        );
                        if (!$attemptQuery) {
                            throw new mysqli_sql_exception($conn->error);
                        }
                        $attemptQuery->bind_param('i', $adminId);
                        $attemptQuery->execute();
                        $attemptQuery->close();
                        $conn->commit();
                        $transactionOpen = false;
                        $step = 2;
                        $error = 'Mã xác nhận không đúng. Vui lòng xác minh lại.';
                    } else {
                        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                        $passwordQuery = $conn->prepare('UPDATE admins SET password = ? WHERE id = ?');
                        if (!$passwordQuery) {
                            throw new mysqli_sql_exception($conn->error);
                        }
                        $passwordQuery->bind_param('si', $passwordHash, $adminId);
                        $passwordQuery->execute();
                        $passwordQuery->close();

                        $deleteQuery = $conn->prepare('DELETE FROM admin_password_reset_codes WHERE admin_id = ?');
                        if (!$deleteQuery) {
                            throw new mysqli_sql_exception($conn->error);
                        }
                        $deleteQuery->bind_param('i', $adminId);
                        $deleteQuery->execute();
                        $deleteQuery->close();
                        $conn->commit();
                        $transactionOpen = false;
                        $step = 3;
                        $completed = true;
                    }
                }
            }
        }
    } catch (\PHPMailer\PHPMailer\Exception $mailError) {
        $error = 'Không gửi được mã xác nhận. Vui lòng kiểm tra cấu hình email hoặc thử lại sau.';
        $message = '';
        $step = 1;
    } catch (mysqli_sql_exception $databaseError) {
        if ($transactionOpen) {
            $conn->rollback();
            $transactionOpen = false;
        }
        error_log('Admin password reset request failed: ' . $databaseError->getMessage());
        $error = 'Không thể xử lý yêu cầu lúc này. Vui lòng thử lại sau.';
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
            background: #C8961E;
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
        .logo i { font-size: 44px; color: #8A6500; }
        .logo h5 { color: #8A6500; font-weight: 700; margin-top: 10px; font-size: 15px; }
        .btn-main {
            background: #8B1A1A;
            border: none; color: white;
            width: 100%; padding: 11px;
            border-radius: 8px; font-weight: 600;
        }
        .btn-main:hover,
        .btn-main:focus,
        .btn-main:active {
            background: #8B1A1A;
            color: white;
        }
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
        .step-dot.done   { background: #8B1A1A; color: white; }
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
        <div class="step-dot <?php echo $step > 2 ? 'done' : ($step === 2 ? 'active' : ''); ?>">
            <?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?>
        </div>
        <div class="step-dot <?php echo $completed ? 'done' : ($step === 3 ? 'active' : ''); ?>">
            <?php echo $completed ? '<i class="fas fa-check"></i>' : '3'; ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 mb-3">
            <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>
    <?php if ($step === 1): ?>
        <div class="alert alert-info py-2 mb-3">
            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars(
                $message !== '' ? $message : 'Nhập tên đăng nhập và email tài khoản hoặc email khôi phục để nhận mã xác nhận.',
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>
    <?php elseif ($step === 2): ?>
        <div class="alert alert-info py-2 mb-3">
            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars(
                ($message !== '' ? $message . ' ' : '')
                    . 'Mã gồm 6 chữ số, có hiệu lực 10 phút.',
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>
    <?php elseif ($message !== ''): ?>
        <div class="alert alert-info py-2 mb-3">
            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <!-- Bước 1: Gửi mã xác nhận -->
        <form method="POST">
            <input type="hidden" name="action" value="request_code">
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
                       placeholder="Nhập email quản trị"
                       value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <button type="submit" class="btn btn-main">
                <i class="fas fa-envelope me-2"></i>Gửi mã xác nhận
            </button>
        </form>

    <?php elseif ($step === 2): ?>
        <!-- Bước 2: Xác nhận mã email -->
        <form method="POST">
            <input type="hidden" name="action" value="verify_code">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-4">
                <label class="form-label fw-semibold">Mã xác nhận</label>
                <input type="text" name="verification_code" class="form-control text-center"
                       inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}"
                       maxlength="6" placeholder="Nhập mã 6 chữ số" required autofocus>
            </div>
            <button type="submit" class="btn btn-main">Xác nhận mã</button>
        </form>
        <form method="POST" class="mt-2">
            <input type="hidden" name="action" value="resend_code">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-link w-100 text-danger">Gửi lại mã</button>
        </form>

    <?php elseif ($step === 3 && !$completed): ?>
        <!-- Bước 3: Đặt mật khẩu mới sau khi xác nhận mã -->
        <p class="text-muted small text-center mb-3">Mã đã xác nhận. Hãy tạo mật khẩu mới.</p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="verification_code" value="<?php echo htmlspecialchars($verificationCode, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Mật khẩu mới</label>
                <div class="input-group">
                    <input type="password" name="new_password" id="new_password"
                           class="form-control" placeholder="Tối thiểu 6 ký tự" minlength="6" required autofocus>
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
            <button type="submit" class="btn btn-main">Đặt mật khẩu mới</button>
        </form>

    <?php elseif ($step === 3 && $completed): ?>
        <!-- Hoàn tất -->
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
