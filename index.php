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
$events = $conn->query("SELECT * FROM events ORDER BY event_date DESC LIMIT 4");

// Hiện vật nổi bật
$artifacts = $conn->query("SELECT a.*, c.name as category_name FROM artifacts a 
                           LEFT JOIN categories c ON a.category_id = c.id 
                           ORDER BY a.created_at DESC LIMIT 6");

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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-primary-custom fw-bold">Sự kiện nổi bật</h3>
            <a href="events.php" class="btn btn-warning btn-sm">Xem tất cả</a>
        </div>
        <div class="row">
            <?php if ($events->num_rows > 0): ?>
                <?php while ($event = $events->fetch_assoc()): ?>
                <?php $event_image = event_image_src($event['image'] ?? ''); ?>
                <?php $event_description = event_description_without_time($event['description'] ?? null); ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <a href="events.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm card-hover" style="border:1px solid #C8961E;background-color:#C8961E;color:#fff;">
                        <?php if ($event_image !== null): ?>
                            <img src="<?= htmlspecialchars($event_image, ENT_QUOTES, 'UTF-8') ?>" class="card-img-top" style="height:200px;object-fit:cover" alt="">
                        <?php else: ?>
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="height:200px">
                                <i class="fas fa-calendar-star fa-3x text-white opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title text-white"><?= htmlspecialchars($event['title']) ?></h5>
                            <p class="card-text text-white small"><?= htmlspecialchars(mb_substr($event_description, 0, 100)) ?>...</p>
                        </div>
                    </div>
                    </a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-primary-custom fw-bold">Hiện vật nổi bật</h3>
            <a href="artifacts.php" class="btn btn-warning btn-sm">Xem tất cả</a>
        </div>
        <div class="row">
            <?php if ($artifacts->num_rows > 0): ?>
                <?php while ($artifact = $artifacts->fetch_assoc()): ?>
                <div class="col-md-4 col-6 mb-4">
                    <a href="artifacts.php" class="text-decoration-none text-dark">
                    <div class="card h-100 shadow-sm card-hover border-0" style="background-color:#C8961E;color:#fff;">
                        <?php if (!empty($artifact['image'])): ?>
                            <img src="uploads/artifacts/<?= htmlspecialchars($artifact['image']) ?>" class="card-img-top" style="height:180px;object-fit:cover" alt="">
                        <?php else: ?>
                            <div class="bg-secondary d-flex align-items-center justify-content-center" style="height:180px">
                                <i class="fas fa-image fa-2x text-white opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <?php if (!empty($artifact['category_name'])): ?>
                                <span class="badge bg-danger mb-1"><?= htmlspecialchars($artifact['category_name']) ?></span>
                            <?php endif; ?>
                            <h6 class="card-title mt-1 text-white"><?= htmlspecialchars($artifact['name']) ?></h6>
                            <p class="card-text text-white small"><?= htmlspecialchars(mb_substr($artifact['description'], 0, 80)) ?>...</p>
                            <?php if (!empty($artifact['origin'])): ?>
                                <small class="text-white"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($artifact['origin']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    </a>
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="text-primary-custom fw-bold">Đánh giá của khách tham quan</h3>
        <a href="review.php" class="btn btn-warning btn-sm">Viết đánh giá</a>
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
