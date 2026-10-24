<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once 'admin_helpers.php';
check_admin_login();

if (!$conn->query("CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) {
    throw new RuntimeException('Không thể khởi tạo bảng cài đặt footer: ' . $conn->error);
}

$defaults = [
    'museum_name' => 'Bảo Tàng Văn Hóa Dân Tộc Khmer',
    'description' => 'Đưa bảo tàng lên không gian mạng chính là mở rộng cánh cửa trái tim văn hóa đến với mọi miền Tổ quốc và bạn bè quốc tế.',
    'facebook_url' => '',
    'youtube_url' => '',
    'opening_days' => 'Thứ hai - Chủ nhật',
    'opening_hours' => "07:00 - 11:00\n13:00 - 17:00",
    'address' => 'Nguyễn Du, khóm 3, phường Nguyệt Hóa, tỉnh Vĩnh Long',
    'phone' => '0294.3842.188',
    'email' => 'baotangvhdtkhmer@gmail.com',
    'website' => 'www.baotang-khmer.vn',
    'copyright' => '© 2026 Bảo tàng Văn hóa Dân tộc Khmer tỉnh Vĩnh Long.',
];
$settings = $defaults;
$result = $conn->query('SELECT setting_key, setting_value FROM site_settings');
while ($row = $result->fetch_assoc()) {
    if (array_key_exists($row['setting_key'], $settings)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $submitted = [];
    foreach ($defaults as $key => $default) {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) {
            $value = '';
        }
        $value = trim($value);
        if (mb_strlen($value) > 2000) {
            $message = 'Nội dung từng trường không được vượt quá 2.000 ký tự.';
            $messageType = 'danger';
            break;
        }
        $submitted[$key] = $value;
    }

    if ($message === '') {
        foreach (['facebook_url', 'youtube_url'] as $key) {
            if ($submitted[$key] !== '') {
                $scheme = strtolower((string) parse_url($submitted[$key], PHP_URL_SCHEME));
                if (!filter_var($submitted[$key], FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
                    $message = 'Liên kết mạng xã hội phải là URL http hoặc https hợp lệ.';
                    $messageType = 'danger';
                    break;
                }
            }
        }
    }

    if ($message === '') {
        $stmt = $conn->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
        foreach ($submitted as $key => $value) {
            $stmt->bind_param('ss', $key, $value);
            if (!$stmt->execute()) {
                $message = 'Không thể lưu cài đặt footer: ' . $stmt->error;
                $messageType = 'danger';
                break;
            }
        }
        $stmt->close();
        if ($message === '') {
            header('Location: footer_settings.php?msg=saved');
            exit();
        }
        $settings = array_merge($settings, $submitted);
    }
}

if (($_GET['msg'] ?? '') === 'saved') {
    $message = 'Đã cập nhật footer thành công.';
}

admin_page_start('Chỉnh sửa footer');
?>
<main class="container-fluid admin-main py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Nội dung footer</h1>
            <p class="text-muted mb-0">Chỉnh sửa thông tin hiển thị ở chân trang website.</p>
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold">Thông tin bảo tàng</div>
        <div class="card-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="museum_name">Tên bảo tàng</label>
                    <input class="form-control" id="museum_name" name="museum_name" maxlength="200" required value="<?= htmlspecialchars($settings['museum_name'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Giới thiệu ngắn</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($settings['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="facebook_url">Liên kết Facebook</label>
                    <input class="form-control" id="facebook_url" name="facebook_url" type="url" placeholder="https://facebook.com/..." value="<?= htmlspecialchars($settings['facebook_url'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="youtube_url">Liên kết YouTube</label>
                    <input class="form-control" id="youtube_url" name="youtube_url" type="url" placeholder="https://youtube.com/..." value="<?= htmlspecialchars($settings['youtube_url'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="opening_days">Ngày mở cửa</label>
                    <input class="form-control" id="opening_days" name="opening_days" value="<?= htmlspecialchars($settings['opening_days'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="opening_hours">Giờ mở cửa</label>
                    <textarea class="form-control" id="opening_hours" name="opening_hours" rows="2"><?= htmlspecialchars($settings['opening_hours'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="form-text">Mỗi dòng sẽ hiển thị thành một khoảng giờ.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Địa chỉ</label>
                    <input class="form-control" id="address" name="address" value="<?= htmlspecialchars($settings['address'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="phone">Số điện thoại</label>
                    <input class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($settings['phone'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" name="email" type="email" value="<?= htmlspecialchars($settings['email'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="website">Website</label>
                    <input class="form-control" id="website" name="website" value="<?= htmlspecialchars($settings['website'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="copyright">Dòng bản quyền</label>
                    <input class="form-control" id="copyright" name="copyright" value="<?= htmlspecialchars($settings['copyright'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap gap-2">
            <button class="btn btn-warning text-white" type="submit"><i class="fas fa-save me-1"></i>Lưu thay đổi</button>
            <a href="index.php" class="btn btn-outline-secondary">Quay lại quản trị</a>
        </div>
    </form>
</main>
<?php admin_page_end(); ?>
