<?php
require_once 'config/db.php';
$page_title = 'Hiện Vật - Bảo Tàng Văn Hóa Dân Tộc Khmer';
include 'includes/header.php';

$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';
$sort = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC';

$where = "WHERE 1=1";
if ($search !== '') $where .= " AND a.name LIKE '%$search%'";

$artifacts = $conn->query("SELECT a.* FROM artifacts a $where ORDER BY a.name $sort, a.id $sort");
?>

<style>
    .artifact-detail-modal .modal-content {
        overflow: hidden;
        border: 1px solid rgba(200, 150, 30, 0.45);
        border-radius: 14px;
        background: #171613;
        color: #f8f5ed;
        box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.35);
    }

    .artifact-detail-modal .modal-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.12);
        background: linear-gradient(135deg, #c8961e, #a07010);
    }

    .artifact-detail-modal .modal-body {
        padding: clamp(1rem, 4vw, 2rem);
    }

    .artifact-detail-modal .artifact-detail-image {
        max-width: 100%;
        max-height: 380px;
        border: 1px solid rgba(200, 150, 30, 0.3);
        border-radius: 12px;
        object-fit: contain;
        box-shadow: 0 0.75rem 1.75rem rgba(0, 0, 0, 0.3);
    }

    .artifact-detail-modal .artifact-description {
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

    .artifact-detail-modal .modal-footer {
        padding: 0.75rem 1.5rem 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .artifact-detail-modal .artifact-close-button {
        border: 0;
        background: #c8961e;
        color: #fff;
    }

    .artifact-detail-modal .artifact-close-button:hover {
        background: #a07010;
        color: #fff;
    }
</style>

<div class="container my-5">

    <!-- Tìm kiếm & lọc -->
    <form method="GET" class="row g-2 mb-4">
        <input type="hidden" name="sort" value="<?= strtolower($sort) ?>">
        <div class="col-md-10">
            <div class="input-group">
                <span class="input-group-text bg-warning text-white border-0"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control border-warning" placeholder="Tìm theo tên hiện vật..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-warning text-white w-100">
                <i class="fas fa-search me-1"></i>Tìm kiếm
            </button>
        </div>
    </form>

    <!-- Số kết quả -->
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
        <p class="text-muted mb-0">
            <i class="fas fa-cubes me-1 text-warning"></i>
            <strong><?= $artifacts->num_rows ?></strong> hiện vật
        </p>
        <form method="GET" class="mb-0">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            <select name="sort" class="form-select form-select-sm border-warning" aria-label="Sắp xếp hiện vật theo tên" onchange="this.form.submit()">
                <option value="asc" <?= $sort === 'ASC' ? 'selected' : '' ?>>Tên: A đến Z</option>
                <option value="desc" <?= $sort === 'DESC' ? 'selected' : '' ?>>Tên: Z đến A</option>
            </select>
        </form>
    </div>

    <!-- Danh sách hiện vật -->
    <div class="row">
        <?php if ($artifacts->num_rows > 0): ?>
            <?php while ($a = $artifacts->fetch_assoc()): ?>
            <div class="col-lg-3 col-md-4 col-6 mb-4">
                <div class="card h-100 border-0 shadow-sm card-hover">
                    <div class="card-body text-center py-2">
                        <h6 class="card-title fw-bold text-center mb-0"><?= htmlspecialchars($a['name']) ?></h6>
                    </div>
                    <!-- Ảnh -->
                    <?php if (!empty($a['image']) && file_exists("uploads/artifacts/" . $a['image'])): ?>
                        <img src="uploads/artifacts/<?= htmlspecialchars($a['image']) ?>"
                             class="card-img-top" style="height:300px;object-fit:contain;background:#fdf6e3;" alt="">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center"
                             style="height:300px;background:linear-gradient(135deg,#f0e0a0,#e8c96a);">
                            <i class="fas fa-landmark fa-4x" style="color:#C8961E;opacity:0.5"></i>
                        </div>
                    <?php endif; ?>

                    <div class="card-body pt-3">
                        <button class="btn btn-sm btn-warning text-white w-100" type="button"
                                data-bs-toggle="modal" data-bs-target="#artifactModal<?= (int) $a['id'] ?>">
                            <i class="fas fa-eye me-1"></i>Xem chi tiết
                        </button>
                    </div>
                </div>
                <div class="modal fade" id="artifactModal<?= (int) $a['id'] ?>" tabindex="-1"
                     aria-labelledby="artifactModalTitle<?= (int) $a['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable artifact-detail-modal">
                        <div class="modal-content border-0">
                            <div class="modal-header text-white">
                                <h5 class="modal-title fw-bold" id="artifactModalTitle<?= (int) $a['id'] ?>">
                                    <i class="fas fa-landmark me-2"></i><?= htmlspecialchars($a['name']) ?>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (!empty($a['image']) && file_exists("uploads/artifacts/" . $a['image'])): ?>
                                    <div class="text-center mb-4">
                                        <img src="uploads/artifacts/<?= htmlspecialchars($a['image'], ENT_QUOTES, 'UTF-8') ?>"
                                             class="artifact-detail-image" alt="<?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="artifact-description">
                                    <?= nl2br(htmlspecialchars($a['description'] ?? 'Chưa có mô tả.', ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-sm artifact-close-button" data-bs-dismiss="modal">Đóng</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
        <div class="col-12 text-center py-5">
            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
            <p class="text-muted">Không tìm thấy hiện vật nào.</p>
            <a href="artifacts.php" class="btn btn-warning text-white">Xem tất cả</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
