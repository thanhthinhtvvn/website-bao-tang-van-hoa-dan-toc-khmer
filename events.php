<?php
require_once 'config/db.php';
$page_title = 'Sự Kiện - Bảo Tàng Văn Hóa Dân Tộc Khmer';
include 'includes/header.php';

$events = $conn->query("SELECT * FROM events ORDER BY event_date DESC");
?>

<!-- Banner -->
<div style="background:linear-gradient(135deg,#C8961E,#a07010);color:white;padding:50px 0;text-align:center;">
    <div class="container">
        <h2 class="fw-bold"><i class="fas fa-calendar-alt me-2"></i>Sự Kiện & Lễ Hội</h2>
        <p class="mb-0">Các sự kiện văn hóa đặc sắc tại Bảo tàng Văn hóa Dân tộc Khmer</p>
    </div>
</div>

<div class="container py-5">

    <?php if ($events->num_rows === 0): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-calendar-times fa-3x mb-3"></i>
            <p>Chưa có sự kiện nào.</p>
        </div>
    <?php else: ?>

    <div class="row g-4">
        <?php while ($e = $events->fetch_assoc()):
            $is_past = strtotime($e['event_date']) < time();
        ?>
        <div class="col-lg-4 col-md-6">
            <div class="card h-100 border-0 shadow-sm card-hover" style="border-top:3px solid #C8961E !important;">

                <!-- Ảnh sự kiện -->
                <?php if (!empty($e['image']) && file_exists("uploads/events/" . $e['image'])): ?>
                    <img src="uploads/events/<?= htmlspecialchars($e['image']) ?>"
                         class="card-img-top" style="height:220px;object-fit:cover;" alt="">
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center"
                         style="height:220px;background:linear-gradient(135deg,#f0e0a0,#e8c96a);">
                        <i class="fas fa-calendar-star fa-4x" style="color:#C8961E;opacity:0.5"></i>
                    </div>
                <?php endif; ?>

                <div class="card-body d-flex flex-column">
                    <!-- Ngày + trạng thái -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge px-3 py-2" style="background:#C8961E;">
                            <i class="far fa-calendar me-1"></i>
                            <?= date('d/m/Y', strtotime($e['event_date'])) ?>
                        </span>
                        <span class="badge <?= $is_past ? 'bg-secondary' : 'bg-success' ?>">
                            <?= $is_past ? 'Đã diễn ra' : 'Sắp diễn ra' ?>
                        </span>
                    </div>

                    <h5 class="card-title fw-bold"><?= htmlspecialchars($e['title']) ?></h5>
                    <p class="card-text text-muted small flex-grow-1">
                        <?= htmlspecialchars(mb_substr($e['description'] ?? '', 0, 150)) ?>...
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
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0">
                    <div class="modal-header text-white" style="background:#C8961E;">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-calendar-alt me-2"></i><?= htmlspecialchars($e['title']) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <?php if (!empty($e['image']) && file_exists("uploads/events/" . $e['image'])): ?>
                            <div class="col-md-5">
                                <img src="uploads/events/<?= htmlspecialchars($e['image']) ?>"
                                     class="img-fluid rounded" alt="">
                            </div>
                            <div class="col-md-7">
                            <?php else: ?>
                            <div class="col-12">
                            <?php endif; ?>
                                <div class="d-flex align-items-center mb-3 gap-2">
                                    <span class="badge px-3 py-2" style="background:#C8961E;">
                                        <i class="far fa-calendar me-1"></i><?= date('d/m/Y', strtotime($e['event_date'])) ?>
                                    </span>
                                    <span class="badge <?= $is_past ? 'bg-secondary' : 'bg-success' ?>">
                                        <?= $is_past ? 'Đã diễn ra' : 'Sắp diễn ra' ?>
                                    </span>
                                </div>
                                <p class="text-muted lh-lg" style="text-align:justify;">
                                    <?= nl2br(htmlspecialchars($e['description'] ?? '')) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                    </div>
                </div>
            </div>
        </div>

        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
