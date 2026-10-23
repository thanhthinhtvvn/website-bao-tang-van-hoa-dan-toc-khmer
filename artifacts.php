<?php
require_once 'config/db.php';
$page_title = 'Hiện Vật - Bảo Tàng Văn Hóa Dân Tộc Khmer';
include 'includes/header.php';

// Lấy danh sách danh mục
$categories = $conn->query("SELECT * FROM categories ORDER BY name");

// Lọc theo danh mục
$filter_cat = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : '';

$where = "WHERE 1=1";
if ($filter_cat > 0) $where .= " AND a.category_id = $filter_cat";
if ($search !== '') $where .= " AND (a.name LIKE '%$search%' OR a.origin LIKE '%$search%')";

$artifacts = $conn->query("SELECT a.*, c.name as category_name 
                           FROM artifacts a 
                           LEFT JOIN categories c ON a.category_id = c.id 
                           $where ORDER BY a.created_at DESC");
?>



<div class="container my-5">

    <!-- Tìm kiếm & lọc -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-warning text-white border-0"><i class="fas fa-search"></i></span>
                <input type="text" name="search" class="form-control border-warning" placeholder="Tìm theo tên, nguồn gốc..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select name="category" class="form-select border-warning">
                <option value="0">-- Tất cả danh mục --</option>
                <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                <option value="<?= $cat['id'] ?>" <?= $filter_cat == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-warning text-white w-100">
                <i class="fas fa-filter me-1"></i>Lọc
            </button>
        </div>
    </form>

    <!-- Số kết quả -->
    <p class="text-muted mb-3">
        <i class="fas fa-cubes me-1 text-warning"></i>
        <strong><?= $artifacts->num_rows ?></strong> hiện vật
    </p>

    <!-- Danh sách hiện vật -->
    <div class="row">
        <?php if ($artifacts->num_rows > 0): ?>
            <?php while ($a = $artifacts->fetch_assoc()): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card h-100 border-0 shadow-sm card-hover" style="border-top: 3px solid #C8961E !important;">
                    <!-- Ảnh -->
                    <?php if (!empty($a['image']) && file_exists("uploads/artifacts/" . $a['image'])): ?>
                        <img src="uploads/artifacts/<?= htmlspecialchars($a['image']) ?>"
                             class="card-img-top" style="height:220px;object-fit:cover;" alt="">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center"
                             style="height:220px;background:linear-gradient(135deg,#f0e0a0,#e8c96a);">
                            <i class="fas fa-landmark fa-4x" style="color:#C8961E;opacity:0.5"></i>
                        </div>
                    <?php endif; ?>

                    <div class="card-body">
                        <!-- Badge danh mục -->
                        <?php if (!empty($a['category_name'])): ?>
                        <span class="badge mb-2" style="background:#C8961E">
                            <?= htmlspecialchars($a['category_name']) ?>
                        </span>
                        <?php endif; ?>

                        <h5 class="card-title fw-bold"><?= htmlspecialchars($a['name']) ?></h5>
                        <p class="card-text text-muted small">
                            <?= htmlspecialchars(mb_substr($a['description'] ?? '', 0, 100)) ?>...
                        </p>

                        <!-- Thông tin nhanh -->
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php if (!empty($a['origin'])): ?>
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt text-warning me-1"></i><?= htmlspecialchars($a['origin']) ?>
                            </small>
                            <?php endif; ?>
                            <?php if (!empty($a['period'])): ?>
                            <small class="text-muted">
                                <i class="fas fa-clock text-warning me-1"></i><?= htmlspecialchars($a['period']) ?>
                            </small>
                            <?php endif; ?>
                        </div>

                        <a href="artifact_detail.php?id=<?= $a['id'] ?>"
                           class="btn btn-sm btn-warning text-white w-100">
                            <i class="fas fa-eye me-1"></i>Xem chi tiết
                        </a>
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
