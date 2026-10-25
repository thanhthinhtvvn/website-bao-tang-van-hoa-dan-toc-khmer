<?php
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: artifacts.php"); exit; }

$result = $conn->query("SELECT a.* FROM artifacts a WHERE a.id = $id");

if ($result->num_rows === 0) { header("Location: artifacts.php"); exit; }
$a = $result->fetch_assoc();

$page_title = htmlspecialchars($a['name']) . ' - Bảo Tàng Khmer';
include 'includes/header.php';
?>

<!-- Breadcrumb -->
<div style="background:#fdf6e3; border-bottom: 1px solid #e8d5a0; padding: 12px 0;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="artifacts.php" class="text-warning text-decoration-none">Hiện vật</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($a['name']) ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- KHUNG 1: HÌNH ẢNH Ở PHÍA TRÊN -->
            <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                <div class="card-body p-0 text-center" style="background:#fdf6e3;">
                    <?php if (!empty($a['image']) && file_exists("uploads/artifacts/" . $a['image'])): ?>
                        <img src="uploads/artifacts/<?= htmlspecialchars($a['image']) ?>"
                             class="img-fluid w-100"
                             style="max-height:550px; object-fit:cover;"
                             alt="<?= htmlspecialchars($a['name']) ?>">
                    <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center w-100"
                             style="height:450px; background:linear-gradient(135deg,#f0e0a0,#e8c96a);">
                            <i class="fas fa-landmark fa-5x" style="color:#C8961E;opacity:0.5"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KHUNG 2: THÔNG TIN CHI TIẾT Ở PHÍA DƯỚI -->
            <div class="card border-0 shadow-sm p-4 text-center" style="background:#fff;">
                
                <!-- Tên hiện vật -->
                <h2 class="fw-bold mb-4"><?= htmlspecialchars($a['name']) ?></h2>

                <!-- Thông tin tóm tắt -->
                <div class="row g-3 mb-4 justify-content-center">
                    <div class="col-md-6 col-sm-12">
                        <div class="p-3 rounded h-100 text-center" style="background:#fdf6e3;border-top:4px solid #C8961E;">
                            <small class="text-muted d-block"><i class="fas fa-calendar text-warning me-1"></i>Ngày cập nhật</small>
                            <strong><?= date('d/m/Y', strtotime($a['created_at'])) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Mô tả -->
                <div class="mb-4 text-start px-md-3">
                    <h5 class="fw-bold text-center mb-3" style="color:#C8961E; border-bottom: 2px solid #C8961E; padding-bottom:8px; display:inline-block; width:100%;">Mô tả chi tiết</h5>
                    <p class="text-muted lh-lg" style="text-align:justify;">
                        <?= nl2br(htmlspecialchars($a['description'] ?? 'Chưa có mô tả.')) ?>
                    </p>
                </div>

                <!-- Nút Quay lại (Bên trái) & Chia sẻ Facebook (Bên phải) -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 pt-3 border-top">
                    <div>
                        <a href="artifacts.php" class="btn btn-outline-secondary px-4">
                            <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                        </a>
                    </div>

                   <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">Chia sẻ:</span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
                        target="_blank" class="btn btn-sm btn-primary text-white d-inline-flex align-items-center justify-content-center"
                        style="width: 32px; height: 32px; padding: 0; border-radius: 4px;" title="Chia sẻ lên Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>