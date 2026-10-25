<?php
require_once 'config/db.php';
require_once 'includes/event_image.php';
require_once 'includes/event_description.php';
$page_title = 'Trang chủ - Bảo tàng Văn hóa Dân tộc Khmer';
include 'includes/header.php';

// Thống kê
$total_artifacts = $conn->query("SELECT COUNT(*) as total FROM artifacts")->fetch_assoc()['total'];
$total_visits    = $conn->query("SELECT COUNT(*) as total FROM visits")->fetch_assoc()['total'];
$total_reviews   = $conn->query("SELECT COUNT(*) as total FROM reviews")->fetch_assoc()['total'];
$total_events    = $conn->query("SELECT COUNT(*) as total FROM events")->fetch_assoc()['total'];

// Sự kiện mới nhất
$events = $conn->query("SELECT * FROM events ORDER BY created_at DESC, id DESC LIMIT 4");

// Hiện vật nổi bật
$artifacts = $conn->query("SELECT a.* FROM artifacts a ORDER BY a.created_at DESC LIMIT 6");

// Đánh giá mới nhất
$reviews = $conn->query("SELECT r.*, u.full_name FROM reviews r 
                         LEFT JOIN users u ON r.user_id = u.id 
                         ORDER BY r.created_at DESC LIMIT 3");

// Slides trang chủ
$slides = $conn->query("SELECT * FROM media WHERE `table` = 'hero_slides' ORDER BY id ASC");
?>

<!-- Hero Carousel - dữ liệu từ bảng media (table = 'hero_slides') -->
<?php
$slides_arr = [];
if ($slides && $slides->num_rows > 0) {
    while ($s = $slides->fetch_assoc()) $slides_arr[] = $s;
}
?>
<?php if (!empty($slides_arr)): ?>
<div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="4000">
    <div class="carousel-indicators">
        <?php foreach ($slides_arr as $i => $s): ?>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $i ?>"
            <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>></button>
        <?php endforeach; ?>
        </div>
    <div class="carousel-inner">
        <?php foreach ($slides_arr as $i => $slide): ?>
        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
            <div class="carousel-bg" style="background-image: url('<?= htmlspecialchars($slide['url']) ?>');">
                <div class="carousel-overlay"></div>
                <div class="carousel-caption-custom carousel-caption-center">
                    <h1 class="display-4 fw-bold <?= $i === 0 ? 'animate__animated animate__fadeInDown' : '' ?>">
                        <?= htmlspecialchars($slide['content']) ?>
                    </h1>
                    <?php if ($i === 0): ?>
                    <p class="lead animate__animated animate__fadeInUp">Khám phá những di sản văn hóa độc đáo của đồng bào Khmer Nam bộ</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>
<?php endif; ?>


<!-- Thống kê đã chuyển lên navbar -->

<!-- Sự kiện -->
<section class="bg-white py-5">
    <div class="container">
        <div class="d-flex justify-content-center align-items-center mb-4">
            <h3 class="text-primary-custom fw-bold text-center mb-0" style="font-family:'Playfair Display',serif;font-size:clamp(1.7rem,3vw,2.3rem);letter-spacing:.03em;border-left:0;padding-left:0;">
                Sự kiện nổi bật
            </h3>
        </div>
        <div class="row">
            <?php if ($events->num_rows > 0): ?>
                <?php while ($event = $events->fetch_assoc()): ?>
                <?php $event_image = event_image_src($event['image'] ?? ''); ?>
                <?php $event_description = event_description_without_time($event['description'] ?? null); ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <a href="#homeEventModal<?= (int) $event['id'] ?>" class="text-decoration-none text-dark"
                       data-bs-toggle="modal" role="button"
                       aria-label="Xem chi tiết <?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="card h-100 shadow-sm card-hover" style="border:1px solid #C8961E;background-color:#C8961E;color:#fff;">
                        <?php if ($event_image !== null): ?>
                            <img src="<?= htmlspecialchars($event_image, ENT_QUOTES, 'UTF-8') ?>" class="card-img-top" style="height:240px;object-fit:cover" alt="">
                        <?php else: ?>
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="height:240px">
                                <i class="fas fa-calendar-star fa-3x text-white opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title text-white"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h5>
                            <p class="card-text text-white small"><?= htmlspecialchars(mb_substr($event_description, 0, 100)) ?>...</p>
                        </div>
                    </div>
                    </a>
                </div>
                <div class="modal fade" id="homeEventModal<?= (int) $event['id'] ?>" tabindex="-1"
                     aria-labelledby="homeEventModalTitle<?= (int) $event['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content" style="background:#171613;color:#f8f5ed;border:1px solid rgba(200,150,30,.45);border-radius:14px;overflow:hidden;">
                            <div class="modal-header text-white" style="background:#C8961E;">
                                <h5 class="modal-title fw-bold" id="homeEventModalTitle<?= (int) $event['id'] ?>">
                                    <i class="fas fa-calendar-alt me-2"></i><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                            </div>
                            <div class="modal-body p-4">
                                <?php if ($event_image !== null): ?>
                                    <div class="text-center mb-4">
                                        <img src="<?= htmlspecialchars($event_image, ENT_QUOTES, 'UTF-8') ?>"
                                             alt="<?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?>"
                                             style="max-width:100%;max-height:380px;object-fit:contain;border-radius:12px;">
                                    </div>
                                <?php endif; ?>
                                <div style="padding:1.25rem 1.5rem;border:1px solid rgba(200,150,30,.2);border-left:3px solid #C8961E;border-radius:8px;background:rgba(255,255,255,.035);color:#e5e1d8;line-height:1.9;">
                                    <?= nl2br(htmlspecialchars(trim($event['description'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-sm text-white" style="background:#C8961E;" data-bs-dismiss="modal">Đóng</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center text-muted py-4">
                    <i class="fas fa-calendar-times fa-2x mb-2"></i>
                    <p>Chưa có sự kiện nào.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Hiện vật nổi bật -->
<div class="bg-light py-5">
    <div class="container">
        <div class="d-flex justify-content-center align-items-center mb-4 position-relative">
            <h3 class="text-primary-custom fw-bold text-center mb-0" style="font-family:'Playfair Display',serif;font-size:clamp(1.7rem,3vw,2.3rem);letter-spacing:.03em;border-left:0;padding-left:0;">
                Hiện vật nổi bật
            </h3>
            <a href="artifacts.php" class="btn btn-warning btn-sm position-absolute end-0">Xem tất cả</a>
        </div>
        <div class="row">
            <?php if ($artifacts->num_rows > 0): ?>
                <?php while ($artifact = $artifacts->fetch_assoc()): ?>
                <div class="col-md-4 col-6 mb-4">
                    <div class="card h-100 shadow-sm card-hover border-0" style="background-color:#C8961E;color:#fff;">
                        <?php if (!empty($artifact['image'])): ?>
                            <img src="uploads/artifacts/<?= htmlspecialchars($artifact['image']) ?>" class="card-img-top" style="height:480px;object-fit:cover" alt="">
                        <?php else: ?>
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="height:480px">
                                <i class="fas fa-image fa-2x text-white opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body text-center py-2">
                            <h6 class="card-title mb-0 text-white small"><?= htmlspecialchars($artifact['name']) ?></h6>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center text-muted py-4">
                    <i class="fas fa-box-open fa-2x mb-2"></i>
                    <p>Chưa có hiện vật nào.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Đánh giá của khách tham quan -->
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-4 w-100">
        <h3 class="text-primary-custom fw-bold mb-0" style="border-left:0;padding-left:0;">Đánh giá của khách tham quan</h3>
        <a href="review.php" class="btn btn-warning btn-sm ms-auto flex-shrink-0">Viết đánh giá</a>
    </div>
    <div class="row">
        <?php if ($reviews->num_rows > 0): ?>
            <?php while ($review = $reviews->fetch_assoc()): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <div class="star-rating mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i > $review['rating'] ? ' text-muted' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="card-text fst-italic">"<?= htmlspecialchars(mb_substr($review['content'], 0, 120)) ?>..."</p>
                        <div class="d-flex align-items-center mt-3">
                            <i class="fas fa-user-circle fa-2x text-secondary me-2"></i>
                            <div>
                                <strong class="small"><?= htmlspecialchars($review['full_name'] ?? 'Ẩn danh') ?></strong><br>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($review['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-4">
                <i class="fas fa-comment-slash fa-2x mb-2"></i>
                <p>Chưa có đánh giá nào.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
