<?php
require_once 'config/db.php';
require_once 'includes/event_image.php';
require_once 'includes/event_description.php';
$page_title = 'Sự Kiện - Bảo Tàng Văn Hóa Dân Tộc Khmer';
include 'includes/header.php';

$events_result = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
$event_slides_result = $conn->query(
    "SELECT content, url FROM media WHERE `table` = 'event_slides' ORDER BY id ASC"
);
$events = [];
$event_slides = [];
while ($event = $events_result->fetch_assoc()) {
    $events[] = $event;
}

while ($slide = $event_slides_result->fetch_assoc()) {
    $slide_image = event_image_src($slide['url'] ?? '');
    if ($slide_image !== null) {
        $event_slides[] = [
            'content' => $slide['content'] ?? '',
            'image_src' => $slide_image,
        ];
    }
}
?>

<style>
    .events-hero .carousel-bg {
        height: 220px;
        background: #171613;
        overflow: hidden;
    }

    .events-hero .event-slide-backdrop {
        position: absolute;
        inset: -12px;
        width: calc(100% + 24px);
        height: calc(100% + 24px);
        object-fit: cover;
        filter: blur(12px) brightness(0.45);
    }

    .events-hero .event-slide-image {
        position: absolute;
        inset: 0;
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center;
        z-index: 1;
    }

    .events-hero .carousel-overlay {
        z-index: 2;
    }

    .events-hero .carousel-caption-custom {
        z-index: 3;
    }

    .events-hero .carousel-caption-custom h1 {
        color: #fff;
    }

    @media (max-width: 576px) {
        .events-hero .carousel-bg {
            height: 150px;
        }
    }

    .event-detail-modal .modal-content {
        overflow: hidden;
        border: 1px solid rgba(200, 150, 30, 0.45) !important;
        border-radius: 14px;
        background: #171613;
        color: #f8f5ed;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.35);
    }

    .event-detail-modal .modal-header {
        min-height: 68px;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        background: linear-gradient(135deg, #c8961e, #a07010) !important;
    }

    .event-detail-modal .modal-title {
        flex: 1 1 auto;
    }

    .event-detail-modal .modal-body {
        padding: clamp(1rem, 4vw, 2rem);
    }

    .event-detail-modal .event-detail-image {
        max-width: 100%;
        max-height: 380px;
        border: 1px solid rgba(200, 150, 30, 0.3);
        border-radius: 12px;
        object-fit: contain;
        box-shadow: 0 0.75rem 1.75rem rgba(0, 0, 0, 0.3);
    }

    .event-detail-modal .event-description {
        padding: 1.25rem 1.5rem;
        border: 1px solid rgba(200, 150, 30, 0.2);
        border-left: 3px solid #c8961e;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.035);
        color: #e5e1d8;
        font-size: 1rem;
        line-height: 1.9;
        text-align: left;
        white-space: normal;
    }

    .event-detail-modal .modal-footer {
        padding: 0.75rem 1.5rem 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .event-detail-modal .event-close-button {
        border: 0;
        background: #c8961e;
        color: #fff;
    }

    .event-detail-modal .event-close-button:hover {
        background: #a07010;
        color: #fff;
    }

    @media (max-width: 576px) {
        .event-detail-modal .modal-header {
            padding: 0.85rem 1rem;
        }

        .event-detail-modal .event-description {
            padding: 1rem;
            font-size: 0.95rem;
        }
    }
</style>

<!-- Banner sự kiện: ảnh lấy từ bảng media, độc lập với ảnh từng sự kiện -->
<?php if (!empty($event_slides)): ?>
<div id="eventsCarousel" class="carousel slide carousel-fade events-hero"
     data-bs-ride="carousel" data-bs-interval="4000">
    <?php if (count($event_slides) > 1): ?>
    <div class="carousel-indicators">
        <?php foreach ($event_slides as $i => $slide): ?>
        <button type="button" data-bs-target="#eventsCarousel" data-bs-slide-to="<?= $i ?>"
            <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
            aria-label="Chuyển đến ảnh <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="carousel-inner">
        <?php foreach ($event_slides as $i => $slide): ?>
        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
            <div class="carousel-bg">
                <img class="event-slide-backdrop"
                     src="<?= htmlspecialchars($slide['image_src'], ENT_QUOTES, 'UTF-8') ?>"
                     alt="" aria-hidden="true">
                <img class="event-slide-image"
                     src="<?= htmlspecialchars($slide['image_src'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                <div class="carousel-overlay"></div>
                <div class="carousel-caption-custom carousel-caption-center">
                    <h1 class="fw-bold">Sự Kiện &amp; Lễ Hội</h1>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (count($event_slides) > 1): ?>
    <button class="carousel-control-prev" type="button" data-bs-target="#eventsCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Ảnh trước</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#eventsCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Ảnh tiếp theo</span>
    </button>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="events-hero-fallback text-center text-white p-4 rounded"
     style="background:linear-gradient(135deg,#C8961E,#a07010);">
    <i class="fas fa-calendar-alt fa-2x mb-3"></i>
    <h2 class="h4 fw-bold">Sự Kiện &amp; Lễ Hội</h2>
    <p class="mb-0 small">Các sự kiện văn hóa đặc sắc tại Bảo tàng Văn hóa Dân tộc Khmer</p>
</div>
<?php endif; ?>

<div class="container py-5">
    <?php if (count($events) === 0): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-calendar-times fa-3x mb-3"></i>
            <p>Chưa có sự kiện nào.</p>
        </div>
    <?php else: ?>

    <div class="row g-4">
        <?php foreach ($events as $e):
            $event_image = event_image_src($e['image'] ?? '');
            $event_description = event_description_without_time($e['description'] ?? null);
            $event_description_detail = trim($e['description'] ?? '');
        ?>
        <div class="col-lg-3 col-md-6">
            <div class="card h-100 border-0 shadow-sm card-hover" style="border-top:3px solid #C8961E !important;">

                <!-- Ảnh sự kiện -->
                <?php if ($event_image !== null): ?>
                    <img src="<?= htmlspecialchars($event_image, ENT_QUOTES, 'UTF-8') ?>"
                         class="card-img-top" style="height:220px;object-fit:cover;" alt="">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center"
                         style="height:220px;background:linear-gradient(135deg,#f0e0a0,#e8c96a);">
                        <i class="fas fa-calendar-star fa-4x" style="color:#C8961E;opacity:0.5"></i>
                    </div>
                <?php endif; ?>

                <div class="card-body d-flex flex-column text-center">
                    <div class="d-flex justify-content-center align-items-center flex-wrap gap-2 mb-2">
                        <h5 class="card-title fw-bold mb-0"><?= htmlspecialchars($e['title']) ?></h5>
                    </div>
                    <p class="card-text text-muted small flex-grow-1">
                        <?= htmlspecialchars(mb_substr($event_description, 0, 150)) ?>...
                    </p>

                    <button class="btn btn-warning text-white btn-sm mt-2"
                            data-bs-toggle="modal" data-bs-target="#modal<?= $e['id'] ?>">
                        <i class="fas fa-info-circle me-1"></i>Xem chi tiết
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal chi tiết sự kiện -->
        <div class="modal fade" id="modal<?= $e['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable event-detail-modal">
                <div class="modal-content border-0">
                    <div class="modal-header text-white flex-wrap gap-2" style="background:#C8961E;">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-calendar-alt me-2"></i><?= htmlspecialchars($e['title']) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <?php if ($event_image !== null): ?>
                            <div class="text-center mb-4">
                                <img src="<?= htmlspecialchars($event_image, ENT_QUOTES, 'UTF-8') ?>"
                                     class="event-detail-image" alt="">
                            </div>
                        <?php endif; ?>
                        <div class="event-description">
                            <?= nl2br(htmlspecialchars($event_description_detail, ENT_QUOTES, 'UTF-8')) ?>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-sm event-close-button" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>

        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
