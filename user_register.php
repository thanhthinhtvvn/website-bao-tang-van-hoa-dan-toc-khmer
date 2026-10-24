<?php
session_start();
require_once 'config/db.php';
require_once 'config/mail.php';
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';
require_once 'phpmailer/Exception.php';

// Đã đăng nhập thì về trang chủ
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$errors  = [];
$success = '';
$step = empty($_SESSION['pending_user_registration_email']) ? 1 : 2;
$message = '';

if (empty($_SESSION['user_register_csrf'])) {
    $_SESSION['user_register_csrf'] = bin2hex(random_bytes(32));
}

$storageReady = $conn->query(
    "CREATE TABLE IF NOT EXISTS pending_user_registrations (
        email VARCHAR(100) NOT NULL PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        verification_code_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        request_ip VARCHAR(45) NOT NULL,
        last_sent_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pending_user_registrations_ip_sent (request_ip, last_sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

if (!$storageReady) {
    error_log('Could not initialize pending user registration storage: ' . $conn->error);
    $errors[] = 'Đăng ký hiện chưa khả dụng. Vui lòng thử lại sau.';
}

$sendVerificationCode = static function (string $email, string $fullName, string $code): void {
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
    $mail->addAddress($email, $fullName);
    $mail->Subject = 'Mã xác nhận đăng ký tài khoản - Bảo tàng Khmer';
    $mail->Body = "Xin chào {$fullName},\n\n"
        . "Mã xác nhận đăng ký tài khoản của bạn là: {$code}\n"
        . "Mã có hiệu lực trong 10 phút và chỉ sử dụng một lần.\n\n"
        . "Nếu bạn không thực hiện đăng ký này, hãy bỏ qua email.";
    $mail->send();
};

if ($storageReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!is_string($csrfToken) || !hash_equals($_SESSION['user_register_csrf'], $csrfToken)) {
        $errors[] = 'Phiên đăng ký không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } elseif (($_POST['action'] ?? '') === 'send_code') {
        $full_name = is_string($_POST['full_name'] ?? null) ? trim($_POST['full_name']) : '';
        $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
        $phone = is_string($_POST['phone'] ?? null) ? trim($_POST['phone']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

        if ($full_name === '') $errors[] = 'Vui lòng nhập họ và tên.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Vui lòng nhập email hợp lệ.';
        }
        if (!preg_match('/^[0-9]{10}$/', $phone)) {
            $errors[] = 'Vui lòng nhập số điện thoại gồm đúng 10 chữ số.';
        }
        if (mb_strlen($password, 'UTF-8') < 6) $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';
        if ($password !== $confirm) $errors[] = 'Mật khẩu xác nhận không khớp.';

        if (empty($errors)) {
            try {
                $existingUser = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                if (!$existingUser) throw new mysqli_sql_exception($conn->error);
                $existingUser->bind_param('s', $email);
                $existingUser->execute();
                $emailExists = $existingUser->get_result()->num_rows > 0;
                $existingUser->close();

                if ($emailExists) {
                    $errors[] = 'Email này đã được đăng ký. Vui lòng dùng email khác hoặc đăng nhập.';
                    $step = 1;
                } else {
                    $requestIp = $_SERVER['REMOTE_ADDR'] ?? '';
                    if (filter_var($requestIp, FILTER_VALIDATE_IP) === false) {
                        $requestIp = 'unknown';
                    }

                    $rateLimit = $conn->prepare(
                        'SELECT COUNT(*) AS request_count
                         FROM pending_user_registrations
                         WHERE request_ip = ? AND last_sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
                    );
                    if (!$rateLimit) throw new mysqli_sql_exception($conn->error);
                    $rateLimit->bind_param('s', $requestIp);
                    $rateLimit->execute();
                    $requestCount = (int) $rateLimit->get_result()->fetch_assoc()['request_count'];
                    $rateLimit->close();

                    $pendingLookup = $conn->prepare(
                        'SELECT last_sent_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) AS on_cooldown
                         FROM pending_user_registrations WHERE email = ? LIMIT 1'
                    );
                    if (!$pendingLookup) throw new mysqli_sql_exception($conn->error);
                    $pendingLookup->bind_param('s', $email);
                    $pendingLookup->execute();
                    $pending = $pendingLookup->get_result()->fetch_assoc();
                    $pendingLookup->close();

                    if ($pending && (int) $pending['on_cooldown'] === 1) {
                        $_SESSION['pending_user_registration_email'] = $email;
                        $step = 2;
                        $message = 'Mã xác nhận vừa được gửi. Vui lòng kiểm tra hộp thư đến và thư mục thư rác.';
                    } elseif ($requestCount >= 5) {
                        $errors[] = 'Bạn đã yêu cầu quá nhiều mã xác nhận. Vui lòng thử lại sau một giờ.';
                    } else {
                        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                        $codeHash = password_hash($code, PASSWORD_DEFAULT);
                        $ipForStorage = substr($requestIp, 0, 45);
                        $savePending = $conn->prepare(
                            'INSERT INTO pending_user_registrations
                                (email, full_name, phone, password_hash, verification_code_hash, expires_at, attempts, request_ip, last_sent_at)
                             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, ?, NOW())
                             ON DUPLICATE KEY UPDATE
                                full_name = VALUES(full_name),
                                phone = VALUES(phone),
                                password_hash = VALUES(password_hash),
                                verification_code_hash = VALUES(verification_code_hash),
                                expires_at = VALUES(expires_at),
                                attempts = 0,
                                request_ip = VALUES(request_ip),
                                last_sent_at = VALUES(last_sent_at)'
                        );
                        if (!$savePending) throw new mysqli_sql_exception($conn->error);
                        $savePending->bind_param('ssssss', $email, $full_name, $phone, $passwordHash, $codeHash, $ipForStorage);
                        $savePending->execute();
                        $savePending->close();

                        try {
                            $sendVerificationCode($email, $full_name, $code);
                            $_SESSION['pending_user_registration_email'] = $email;
                            $step = 2;
                            $message = 'Mã xác nhận đã được gửi đến email của bạn. Vui lòng nhập mã trong vòng 10 phút.';
                        } catch (\PHPMailer\PHPMailer\Exception $mailError) {
                            $deletePending = $conn->prepare('DELETE FROM pending_user_registrations WHERE email = ?');
                            if ($deletePending) {
                                $deletePending->bind_param('s', $email);
                                $deletePending->execute();
                                $deletePending->close();
                            } else {
                                error_log('Could not remove pending registration after email failure: ' . $conn->error);
                            }
                            error_log('User registration verification email failed: ' . $mailError->getMessage());
                            $errors[] = 'Không gửi được email xác nhận. Vui lòng kiểm tra email hoặc thử lại sau.';
                        }
                    }
                }
            } catch (mysqli_sql_exception $databaseError) {
                error_log('Could not start user registration: ' . $databaseError->getMessage());
                $errors[] = 'Không thể bắt đầu đăng ký lúc này. Vui lòng thử lại sau.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'resend_code') {
        $email = $_SESSION['pending_user_registration_email'] ?? '';
        if (!is_string($email) || $email === '') {
            $errors[] = 'Phiên đăng ký đã hết hạn. Vui lòng đăng ký lại.';
        } else {
            try {
                $pendingQuery = $conn->prepare(
                    'SELECT full_name, request_ip,
                            last_sent_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) AS on_cooldown
                     FROM pending_user_registrations WHERE email = ? LIMIT 1'
                );
                if (!$pendingQuery) throw new mysqli_sql_exception($conn->error);
                $pendingQuery->bind_param('s', $email);
                $pendingQuery->execute();
                $pending = $pendingQuery->get_result()->fetch_assoc();
                $pendingQuery->close();

                if (!$pending) {
                    unset($_SESSION['pending_user_registration_email']);
                    $step = 1;
                    $errors[] = 'Phiên đăng ký đã hết hạn. Vui lòng đăng ký lại.';
                } elseif ((int) $pending['on_cooldown'] === 1) {
                    $step = 2;
                    $message = 'Vui lòng chờ một phút trước khi yêu cầu gửi lại mã.';
                } else {
                    $requestIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    if (filter_var($requestIp, FILTER_VALIDATE_IP) === false) {
                        $requestIp = 'unknown';
                    }
                    $rateLimit = $conn->prepare(
                        'SELECT COUNT(*) AS request_count
                         FROM pending_user_registrations
                         WHERE request_ip = ? AND last_sent_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
                    );
                    if (!$rateLimit) throw new mysqli_sql_exception($conn->error);
                    $rateLimit->bind_param('s', $requestIp);
                    $rateLimit->execute();
                    $requestCount = (int) $rateLimit->get_result()->fetch_assoc()['request_count'];
                    $rateLimit->close();

                    if ($requestCount >= 5) {
                        $errors[] = 'Bạn đã yêu cầu quá nhiều mã xác nhận. Vui lòng thử lại sau một giờ.';
                        $step = 2;
                    } else {
                        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $codeHash = password_hash($code, PASSWORD_DEFAULT);
                        $ipForStorage = substr($requestIp, 0, 45);
                        $updatePending = $conn->prepare(
                            'UPDATE pending_user_registrations
                             SET verification_code_hash = ?, expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                                 attempts = 0, request_ip = ?, last_sent_at = NOW()
                             WHERE email = ?'
                        );
                        if (!$updatePending) throw new mysqli_sql_exception($conn->error);
                        $updatePending->bind_param('sss', $codeHash, $ipForStorage, $email);
                        $updatePending->execute();
                        $updatePending->close();

                        try {
                            $sendVerificationCode($email, $pending['full_name'], $code);
                            $step = 2;
                            $message = 'Mã xác nhận mới đã được gửi. Mã cũ không còn hiệu lực.';
                        } catch (\PHPMailer\PHPMailer\Exception $mailError) {
                            $deletePending = $conn->prepare('DELETE FROM pending_user_registrations WHERE email = ?');
                            if ($deletePending) {
                                $deletePending->bind_param('s', $email);
                                $deletePending->execute();
                                $deletePending->close();
                                unset($_SESSION['pending_user_registration_email']);
                            } else {
                                error_log('Could not remove pending registration after resend failure: ' . $conn->error);
                            }
                            error_log('Could not resend user registration verification code: ' . $mailError->getMessage());
                            $step = 1;
                            $errors[] = 'Không gửi được email xác nhận. Vui lòng thử lại sau.';
                        }
                    }
                }
            } catch (mysqli_sql_exception $databaseError) {
                error_log('Could not resend user registration verification code: ' . $databaseError->getMessage());
                $errors[] = 'Không thể gửi lại mã lúc này. Vui lòng thử lại sau.';
                $step = 2;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'verify_code') {
        $email = $_SESSION['pending_user_registration_email'] ?? '';
        $code = is_string($_POST['verification_code'] ?? null) ? trim($_POST['verification_code']) : '';
        if (!is_string($email) || $email === '' || !preg_match('/^[0-9]{6}$/', $code)) {
            $errors[] = 'Vui lòng nhập mã xác nhận gồm 6 chữ số.';
        } else {
            try {
                $conn->begin_transaction();
                $pendingQuery = $conn->prepare(
                    'SELECT full_name, phone, password_hash, verification_code_hash,
                            expires_at > NOW() AS is_valid, attempts
                     FROM pending_user_registrations WHERE email = ? LIMIT 1 FOR UPDATE'
                );
                if (!$pendingQuery) throw new mysqli_sql_exception($conn->error);
                $pendingQuery->bind_param('s', $email);
                $pendingQuery->execute();
                $pending = $pendingQuery->get_result()->fetch_assoc();
                $pendingQuery->close();

                if (!$pending || (int) $pending['is_valid'] !== 1) {
                    if ($pending) {
                        $deleteExpired = $conn->prepare('DELETE FROM pending_user_registrations WHERE email = ?');
                        if (!$deleteExpired) throw new mysqli_sql_exception($conn->error);
                        $deleteExpired->bind_param('s', $email);
                        $deleteExpired->execute();
                        $deleteExpired->close();
                    }
                    $conn->commit();
                    unset($_SESSION['pending_user_registration_email']);
                    $step = 1;
                    $errors[] = 'Mã xác nhận đã hết hạn. Vui lòng đăng ký lại để nhận mã mới.';
                } elseif ((int) $pending['attempts'] >= 5) {
                    $deleteLocked = $conn->prepare('DELETE FROM pending_user_registrations WHERE email = ?');
                    if (!$deleteLocked) throw new mysqli_sql_exception($conn->error);
                    $deleteLocked->bind_param('s', $email);
                    $deleteLocked->execute();
                    $deleteLocked->close();
                    $conn->commit();
                    unset($_SESSION['pending_user_registration_email']);
                    $step = 1;
                    $errors[] = 'Bạn đã nhập sai mã quá nhiều lần. Vui lòng đăng ký lại.';
                } elseif (!password_verify($code, $pending['verification_code_hash'])) {
                    $incrementAttempts = $conn->prepare(
                        'UPDATE pending_user_registrations SET attempts = attempts + 1 WHERE email = ?'
                    );
                    if (!$incrementAttempts) throw new mysqli_sql_exception($conn->error);
                    $incrementAttempts->bind_param('s', $email);
                    $incrementAttempts->execute();
                    $incrementAttempts->close();
                    $conn->commit();
                    $step = 2;
                    $errors[] = 'Mã xác nhận không đúng. Vui lòng kiểm tra lại.';
                } else {
                    $insertUser = $conn->prepare(
                        'INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)'
                    );
                    if (!$insertUser) throw new mysqli_sql_exception($conn->error);
                    $insertUser->bind_param('ssss', $pending['full_name'], $email, $pending['phone'], $pending['password_hash']);
                    $insertUser->execute();
                    $insertUser->close();

                    $deletePending = $conn->prepare('DELETE FROM pending_user_registrations WHERE email = ?');
                    if (!$deletePending) throw new mysqli_sql_exception($conn->error);
                    $deletePending->bind_param('s', $email);
                    $deletePending->execute();
                    $deletePending->close();
                    $conn->commit();

                    unset($_SESSION['pending_user_registration_email'], $_SESSION['user_register_csrf']);
                    $success = true;
                }
            } catch (mysqli_sql_exception $databaseError) {
                $conn->rollback();
                if ($databaseError->getCode() === 1062) {
                    unset($_SESSION['pending_user_registration_email']);
                    $step = 1;
                    $errors[] = 'Email này đã được đăng ký. Vui lòng đăng nhập.';
                } else {
                    error_log('Could not verify user registration code: ' . $databaseError->getMessage());
                    $errors[] = 'Không thể xác minh mã lúc này. Vui lòng thử lại sau.';
                    $step = 2;
                }
            }
        }
    } else {
        $errors[] = 'Yêu cầu không hợp lệ. Vui lòng tải lại trang và thử lại.';
    }
}

$page_title = 'Đăng ký tài khoản - Bảo Tàng Khmer Vĩnh Long';
include 'includes/header.php';
?>




<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <?php if ($success): ?>
                <div class="card border-0 shadow-sm text-center p-5">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h4 class="fw-bold">Đăng ký thành công</h4>
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
                                    <div><i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($message !== '' && $step !== 2): ?>
                            <div class="alert alert-info py-2">
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($step === 2 && !empty($_SESSION['pending_user_registration_email'])): ?>
                        <div class="alert alert-info text-center py-3 mb-4">
                            <i class="fas fa-envelope me-1"></i>
                            <span>Mã xác nhận đã được gửi đến</span><br>
                            <strong class="text-break"><?php echo htmlspecialchars($_SESSION['pending_user_registration_email'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="small mt-1">Mã có hiệu lực trong 10 phút.</div>
                            <?php if ($message !== 'Mã xác nhận đã được gửi đến email của bạn. Vui lòng nhập mã trong vòng 10 phút.'
                                && $message !== 'Mã xác nhận vừa được gửi. Vui lòng kiểm tra hộp thư đến và thư mục thư rác.'
                                && $message !== ''): ?>
                                <div class="small mt-2"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['user_register_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="verify_code">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mã xác nhận gồm 6 chữ số <span class="text-danger">*</span></label>
                                <input type="text" name="verification_code" class="form-control text-center"
                                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                       autocomplete="one-time-code" placeholder="000000" required autofocus>
                            </div>
                            <button type="submit" class="btn btn-primary-custom w-100 py-2"> Xác nhận và tạo tài khoản </button>
                        </form>
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['user_register_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="resend_code">
                            <button type="submit" class="btn btn-link w-100">Gửi lại mã xác nhận</button>
                        </form>
                        <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['user_register_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="send_code">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control"
                                       placeholder="Nguyễn Thanh Thịnh"
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control"
                                       placeholder="example@email.com"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" minlength="10" maxlength="10"
                                       pattern="[0-9]{10}" inputmode="numeric" required
                                       oninvalid="if (this.validity.valueMissing || this.validity.patternMismatch) this.setCustomValidity('Vui lòng nhập đủ 10 số')"
                                       oninput="this.setCustomValidity('')"
                                       placeholder="0123456789"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Mật khẩu <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password"
                                           class="form-control" placeholder="Tối thiểu 6 ký tự" minlength="6" required
                                           oninvalid="if (this.validity.valueMissing) this.setCustomValidity('Vui lòng điền mật khẩu')"
                                           oninput="this.setCustomValidity('')">
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
                            <button type="submit" class="btn btn-primary-custom w-100 py-2"> Gửi mã xác nhận qua email </button>
                        </form>
                        <?php endif; ?>

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
