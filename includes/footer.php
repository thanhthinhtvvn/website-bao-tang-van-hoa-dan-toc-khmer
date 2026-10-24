<?php
$footerSettings = [
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

if (isset($conn) && $conn instanceof mysqli) {
    $settingsTable = $conn->query("SHOW TABLES LIKE 'site_settings'");
    if ($settingsTable && $settingsTable->num_rows > 0) {
        $settingsResult = $conn->query('SELECT setting_key, setting_value FROM site_settings');
        while ($setting = $settingsResult->fetch_assoc()) {
            if (array_key_exists($setting['setting_key'], $footerSettings)) {
                $footerSettings[$setting['setting_key']] = $setting['setting_value'];
            }
        }
    }
}

$footerEscape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$facebookUrl = filter_var($footerSettings['facebook_url'], FILTER_VALIDATE_URL)
    && in_array(strtolower((string) parse_url($footerSettings['facebook_url'], PHP_URL_SCHEME)), ['http', 'https'], true)
    ? $footerSettings['facebook_url']
    : null;
$youtubeUrl = filter_var($footerSettings['youtube_url'], FILTER_VALIDATE_URL)
    && in_array(strtolower((string) parse_url($footerSettings['youtube_url'], PHP_URL_SCHEME)), ['http', 'https'], true)
    ? $footerSettings['youtube_url']
    : null;
?>
    <footer class="<?= !empty($isAdminDashboard) ? 'admin-site-footer' : '' ?>">
        <div class="container">
            <div class="row">
                <div class="col-md-6 col-lg-5 mb-4">
                    <h5 class="text-warning mb-3">
                       <?= $footerEscape($footerSettings['museum_name']) ?>
                    </h5>
                    <p class="small"><?= nl2br($footerEscape($footerSettings['description'])) ?></p>
                    <div class="d-flex gap-2 mt-3">
                        <?php if ($facebookUrl !== null): ?>
                        <a href="<?= $footerEscape($facebookUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm rounded-circle" style="width:36px;height:36px;padding:0;line-height:34px;text-align:center" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <?php endif; ?>
                        <?php if ($youtubeUrl !== null): ?>
                        <a href="<?= $footerEscape($youtubeUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm rounded-circle" style="width:36px;height:36px;padding:0;line-height:34px;text-align:center" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3 mb-4">
                    <h6 class="text-warning mb-3">Giờ mở cửa</h6>
                    <ul class="list-unstyled small <?= !empty($isAdminDashboard) ? 'footer-muted' : 'text-secondary' ?> mb-0">
                        <li class="d-flex justify-content-start gap-2 mb-2">
                            <span><i class="far fa-clock me-2 text-warning"></i><?= $footerEscape($footerSettings['opening_days']) ?></span>
                            <span class="text-nowrap"><?= nl2br($footerEscape($footerSettings['opening_hours'])) ?></span>
                        </li>
                    </ul>
                </div>

                <div class="col-md-6 col-lg-4 mb-4">
                    <h6 class="text-warning mb-3">Liên hệ</h6>
                    <ul class="list-unstyled small <?= !empty($isAdminDashboard) ? 'footer-muted' : 'text-secondary' ?>">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2 text-warning"></i><?= $footerEscape($footerSettings['address']) ?></li>
                        <li class="mb-2"><i class="fas fa-phone me-2 text-warning"></i><?= $footerEscape($footerSettings['phone']) ?></li>
                        <li class="mb-2"><i class="fas fa-envelope me-2 text-warning"></i><?= $footerEscape($footerSettings['email']) ?></li>
                        <li class="mb-2"><i class="fas fa-globe me-2 text-warning"></i><?= $footerEscape($footerSettings['website']) ?></li>
                    </ul>
                </div>
            </div>

            <hr>

            <div class="row align-items-center">
                <div class="col-md-8 text-center text-md-start">
                    <p class="mb-0 small <?= !empty($isAdminDashboard) ? 'footer-muted' : 'text-secondary' ?>">
                        <?= $footerEscape($footerSettings['copyright']) ?>
                    </p>
                </div>
                <?php if (empty($isAdminDashboard)): ?>
                <div class="col-md-4 text-center text-md-end mt-2 mt-md-0">
                    <a href="admin/login.php" class="text-secondary text-decoration-none small">
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
