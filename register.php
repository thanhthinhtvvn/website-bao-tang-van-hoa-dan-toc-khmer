<?php
require_once 'config/db.php';
require_once 'config/mail.php';

require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';
require_once 'phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$page_title = 'Đăng ký tham quan - Bảo Tàng Khmer Vĩnh Long';
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name    = trim($_POST['full_name']    ?? '');
    $email        = trim($_POST['email']        ?? '');
    $phone        = trim($_POST['phone']        ?? '');
    $visit_date   = trim($_POST['visit_date']   ?? '');
    $visit_time   = trim($_POST['visit_time']   ?? '');
    $group_size   = (int)($_POST['group_size']  ?? 1);
    $organization = trim($_POST['organization'] ?? '');
    $notes        = trim($_POST['notes']        ?? '');

    if ($full_name && $email && $phone && $visit_date && $visit_time) {
        if ($visit_date < date('Y-m-d')) {
            $error = 'Ngày tham quan không được là ngày trong quá khứ.';
        } else {
            // ── Hàm gửi email ────────────────────────────────
            function sendMail($to, $toName, $subject, $body) {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = MAIL_USERNAME;
                $mail->Password   = MAIL_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
                $mail->CharSet    = 'UTF-8';
                $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
                $mail->addAddress($to, $toName);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $body;
                $mail->send();
            }

            // ── Nội dung bảng thông tin ───────────────────────
            $date_fmt = date('d/m/Y', strtotime($visit_date));
            $org_row  = $organization ? "
                <tr style='background:#fdf6e3'>
                    <td style='padding:8px 12px;border:1px solid #e8d08a;font-weight:bold;color:#a07010;font-size:12px'>Tỉnh</td>
                    <td style='padding:8px 12px;border:1px solid #e8d08a;color:#333;font-size:12px'>$organization</td>
                </tr>" : '';
            $notes_row = $notes ? "
                <tr>
                    <td style='padding:8px 12px;border:1px solid #e8d08a;font-weight:bold;color:#a07010;font-size:12px'>Ghi chú</td>
                    <td style='padding:8px 12px;border:1px solid #e8d08a;color:#333;font-size:12px'>$notes</td>
                </tr>" : '';

            $info_table = "
            <table style='width:100%;border-collapse:collapse;margin:10px 0;font-size:11px'>
                <tr style='background:#fdf6e3'>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;font-weight:bold;color:#a07010;white-space:nowrap'>Họ và tên</td>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;color:#333'>$full_name</td>
                </tr>
                <tr>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;font-weight:bold;color:#a07010;white-space:nowrap'>Email</td>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;color:#333'>$email</td>
                </tr>
                <tr style='background:#fdf6e3'>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;font-weight:bold;color:#a07010;white-space:nowrap'>Điện thoại</td>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;color:#333'>$phone</td>
                </tr>
                <tr>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;font-weight:bold;color:#a07010;white-space:nowrap'>Ngày tham quan</td>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;color:#333'>$date_fmt</td>
                </tr>
                <tr style='background:#fdf6e3'>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;font-weight:bold;color:#a07010;white-space:nowrap'>Giờ tham quan</td>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;color:#333'>$visit_time</td>
                </tr>
                <tr>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;font-weight:bold;color:#a07010;white-space:nowrap'>Số lượng</td>
                    <td style='padding:6px 10px;border:1px solid #e8d08a;color:#333'>$group_size người</td>
                </tr>
                $org_row $notes_row
            </table>";

            // ── Header & Footer email ─────────────────────────
            $header = "<div style='background:linear-gradient(135deg,#C8961E,#a07010);padding:14px 20px;text-align:center'>
                <h1 style='color:#fff;margin:0;font-size:0.95rem;font-weight:bold'>Bảo tàng Văn hóa Dân tộc Khmer</h1>
            </div>";
            $footer = "<div style='background:#1a1208;padding:10px;text-align:center'>
                <p style='color:#c9b06a;margin:0;font-size:0.72rem'>© 2026 Bảo tàng Văn hóa Dân tộc Khmer tỉnh Vĩnh Long</p>
            </div>";
            $wrap_open  = "<div style='max-width:480px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;font-family:Arial,sans-serif;font-size:12px;box-shadow:0 2px 10px rgba(0,0,0,0.1)'>$header<div style='padding:18px 20px'>";
            $wrap_close = "</div>$footer</div>";

            // ── Email 1: gửi cho NGƯỜI ĐĂNG KÝ ──────────────
            $body_user = $wrap_open . "
                <h2 style='color:#a07010;margin-top:0;font-size:1rem'>Đăng ký tham quan thành công</h2>
                <p style='color:#333;font-size:12px'>Xin chào <strong>$full_name</strong>,</p>
                <p style='color:#555;font-size:12px'>Chúng tôi đã nhận được đăng ký của bạn. Dưới đây là thông tin chi tiết:</p>
                $info_table
                <div style='background:#fdf6e3;border-left:3px solid #C8961E;padding:10px 12px;border-radius:4px;margin:14px 0'>
                    <p style='margin:0;color:#a07010;font-size:11px'>
                        Chúng tôi sẽ xác nhận lịch tham quan trong vòng <strong>24 giờ</strong>.<br>
                        Địa chỉ: Bảo tàng Văn hóa Dân tộc Khmer tỉnh Vĩnh Long<br>
                        Hotline: 0294.3842.188
                    </p>
                </div>
                <p style='color:#888;font-size:11px'>Trân trọng,<br><strong>Ban quản lý Bảo tàng</strong></p>
            " . $wrap_close;

            // ── Email 2: gửi cho Admin ───────────
            $body_admin = $wrap_open . "
                <h2 style='color:#a07010;margin-top:0;font-size:1rem'>Có đăng ký tham quan mới!</h2>
                <p style='color:#555;font-size:12px'>Một khách vừa đăng ký tham quan. Thông tin chi tiết:</p>
                $info_table
                <p style='color:#888;font-size:11px'>Vui lòng xác nhận và liên hệ lại với khách hàng.</p>
            " . $wrap_close;

            try {
                // Gửi cho người đăng ký
                sendMail($email, $full_name,
                    'Xác nhận đăng ký tham quan - Bảo tàng Văn hóa Dân tộc Khmer tỉnh Vĩnh Long',
                    $body_user);

                // Gửi cho bảo tàng
                sendMail(MAIL_ADMIN, 'Quản trị viên',
                    "Đăng ký mới từ $full_name - " . date('d/m/Y H:i'),
                    $body_admin);

                $success = "Đăng ký thành công! Email xác nhận đã được gửi đến <strong>$email</strong>.";
            } catch (Exception $e) {
                $error = 'Đăng ký thất bại, vui lòng thử lại. Lỗi: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Vui lòng điền đầy đủ các trường bắt buộc (*).';
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary-custom text-white py-3">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Thông tin đăng ký</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Họ và tên <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control"
                                       placeholder="Nguyễn Văn A"
                                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control"
                                       placeholder="example@email.com"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Số điện thoại <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control"
                                       placeholder="0xxx xxx xxx"
                                       maxlength="10"
                                       pattern="[0-9]{10}"
                                       title="Số điện thoại gồm đúng 10 chữ số"
                                       oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Số lượng người <span class="text-danger">*</span></label>
                                <input type="number" name="group_size" class="form-control"
                                       min="1" max="50"
                                       title="Tối đa 50 người"
                                       value="<?= (int)($_POST['group_size'] ?? 1) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Ngày tham quan <span class="text-danger">*</span></label>
                                <input type="date" name="visit_date" class="form-control"
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= htmlspecialchars($_POST['visit_date'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Giờ tham quan <span class="text-danger">*</span></label>
                                <select name="visit_time" class="form-select" required>
                                    <option value="">-- Chọn giờ --</option>
                                    <option value="8:00 - 10:00"  <?= (($_POST['visit_time'] ?? '') === '8:00 - 10:00')  ? 'selected' : '' ?>>8:00 - 10:00</option>
                                    <option value="10:00 - 12:00" <?= (($_POST['visit_time'] ?? '') === '10:00 - 12:00') ? 'selected' : '' ?>>10:00 - 12:00</option>
                                    <option value="13:00 - 15:00" <?= (($_POST['visit_time'] ?? '') === '13:00 - 15:00') ? 'selected' : '' ?>>13:00 - 15:00</option>
                                    <option value="15:00 - 17:00" <?= (($_POST['visit_time'] ?? '') === '15:00 - 17:00') ? 'selected' : '' ?>>15:00 - 17:00</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label fw-semibold">Tỉnh/Thành phố</label>
                                <select name="organization" class="form-select">
                                    <option value="">-- Chọn tỉnh --</option>
                                    <?php
                                    $tinh_list = [
                                        'An Giang',
                                        'Cà Mau',
                                        'Cần Thơ',
                                        'Cao Bằng',
                                        'Đà Nẵng',
                                        'Đắk Lắk',
                                        'Điện Biên',
                                        'Đồng Nai',
                                        'Đồng Tháp',
                                        'Gia Lai',
                                        'Hà Nội',
                                        'Hải Phòng',
                                        'Khánh Hòa',
                                        'Lai Châu',
                                        'Lâm Đồng',
                                        'Lạng Sơn',
                                        'Lào Cai',
                                        'Nghệ An',
                                        'Ninh Bình',
                                        'Phú Thọ',
                                        'Quảng Ngãi',
                                        'Quảng Ninh',
                                        'Quảng Trị',
                                        'Sơn La',
                                        'Tây Ninh',
                                        'Thái Nguyên',
                                        'Thanh Hóa',
                                        'Thừa Thiên Huế',
                                        'TP. Hồ Chí Minh',
                                        'Tuyên Quang',
                                        'Vĩnh Long',
                                        'Bắc Ninh',
                                        'Hưng Yên',
                                    ];
                                    sort($tinh_list);
                                    foreach ($tinh_list as $tinh):
                                        $sel = (($_POST['organization'] ?? '') === $tinh) ? 'selected' : '';
                                        echo "<option value='$tinh' $sel>$tinh</option>";
                                    endforeach;
                                    ?>
                                </select>
                            </div>
                            <div class="col-12 mb-4">
                                <label class="form-label fw-semibold">Ghi chú</label>
                                <textarea name="notes" class="form-control" rows="3"
                                          placeholder="Yêu cầu đặc biệt..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary-custom px-5 py-2">
                            <i class="fas fa-paper-plane me-2"></i>Gửi đăng ký
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
