<?php
require_once 'config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$page_title = 'Đánh giá - Bảo Tàng Văn Hóa Dân Tộc Khmer';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        $error = 'Bạn cần <a href="user_login.php" class="alert-link">đăng nhập</a> để gửi đánh giá.';
    } else {
        $user_id = (int)$_SESSION['user_id'];
        $rating  = (int)($_POST['rating'] ?? 0);
        $content = $conn->real_escape_string(trim($_POST['content'] ?? ''));
        if ($rating >= 1 && $rating <= 5 && $content !== '') {
            $conn->query("INSERT INTO reviews (user_id, rating, content) VALUES ($user_id, $rating, '$content')");
            $success = 'Cảm ơn bạn đã gửi đánh giá!';
        } else {
            $error = 'Vui lòng chọn số sao và nhập nội dung đánh giá.';
        }
    }
}

// Thống kê
$stats = $conn->query("SELECT COUNT(*) as total, ROUND(AVG(rating),1) as avg_rating,
    SUM(rating=5) as s5, SUM(rating=4) as s4, SUM(rating=3) as s3,
    SUM(rating=2) as s2, SUM(rating=1) as s1 FROM reviews")->fetch_assoc();

// Danh sách đánh giá
$reviews = $conn->query("SELECT r.*, u.full_name FROM reviews r
                         LEFT JOIN users u ON r.user_id = u.id
                         ORDER BY r.created_at DESC");

include 'includes/header.php';
?>

<!-- Banner -->
<div style="background:linear-gradient(135deg,#C8961E,#a07010);color:white;padding:50px 0;text-align:center;">
    <div class="container">
        <h2 class="fw-bold"><i class="fas fa-star me-2"></i>Đánh Giá Của Khách Tham Quan</h2>
        <p class="mb-0">Chia sẻ trải nghiệm của bạn về Bảo tàng Văn hóa Dân tộc Khmer</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">

        <!-- Cột trái: Thống kê + Form -->
        <div class="col-lg-4">

            <!-- Thống kê tổng quan -->
            <?php if ($stats['total'] > 0): ?>
            <div class="card border-0 shadow-sm mb-4 text-center">
                <div class="card-body p-4">
                    <h1 class="display-3 fw-bold" style="color:#C8961E"><?= $stats['avg_rating'] ?></h1>
                    <div class="mb-1" style="color:#C8961E;font-size:1.4rem;">
                        <?php for ($i=1;$i<=5;$i++): ?>
                            <i class="fas fa-star<?= $i <= round($stats['avg_rating']) ? '' : ' opacity-25' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted mb-3"><?= $stats['total'] ?> đánh giá</p>
                    <?php foreach ([5=>'s5',4=>'s4',3=>'s3',2=>'s2',1=>'s1'] as $star => $key):
                        $pct = $stats['total'] > 0 ? round(($stats[$key]/$stats['total'])*100) : 0; ?>
                    <div class="d-flex align-items-center mb-1">
                        <span class="small me-2"><?= $star ?><i class="fas fa-star ms-1" style="color:#C8961E"></i></span>
                        <div class="progress flex-grow-1" style="height:7px">
                            <div class="progress-bar" style="width:<?= $pct ?>%;background:#C8961E"></div>
                        </div>
                        <span class="small ms-2 text-muted"><?= $stats[$key] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form đánh giá -->
            <div class="card border-0 shadow-sm">
                <div class="card-header text-white py-3" style="background:#C8961E">
                    <h6 class="mb-0"><i class="fas fa-pen me-2"></i>Gửi đánh giá của bạn</h6>
                </div>
                <div class="card-body p-3">
                    <?php if ($success): ?>
                        <div class="alert alert-success py-2 small"><i class="fas fa-check-circle me-1"></i><?= $success ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><i class="fas fa-exclamation-circle me-1"></i><?= $error ?></div>
                    <?php endif; ?>

                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-lock fa-2x text-muted mb-2"></i>
                            <p class="small text-muted">Đăng nhập để gửi đánh giá</p>
                            <a href="user_login.php" class="btn btn-warning text-white btn-sm w-100">
                                <i class="fas fa-sign-in-alt me-1"></i>Đăng nhập
                            </a>
                        </div>
                    <?php else: ?>
                    <form method="POST">
                        <!-- Chọn sao -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Đánh giá <span class="text-danger">*</span></label>
                            <div class="d-flex gap-1" id="starRow" style="font-size:1.8rem;cursor:pointer;">
                                <?php for ($i=1;$i<=5;$i++): ?>
                                <i class="fas fa-star star-btn text-muted" data-val="<?= $i ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nhận xét <span class="text-danger">*</span></label>
                            <textarea name="content" class="form-control form-control-sm" rows="4"
                                      placeholder="Chia sẻ trải nghiệm của bạn..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning text-white btn-sm w-100">
                            <i class="fas fa-paper-plane me-1"></i>Gửi đánh giá
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Cột phải: Danh sách đánh giá -->
        <div class="col-lg-8">
            <h5 class="fw-bold mb-4" style="color:#C8961E;border-left:4px solid #C8961E;padding-left:10px;">
                <i class="fas fa-comments me-2"></i>Nhận xét từ khách tham quan
            </h5>

            <?php if ($reviews->num_rows === 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-comment-slash fa-3x mb-3"></i>
                    <p>Chưa có đánh giá nào. Hãy là người đầu tiên!</p>
                </div>
            <?php else: ?>
                <?php while ($r = $reviews->fetch_assoc()): ?>
                <div class="card border-0 shadow-sm mb-3" style="border-left:3px solid #C8961E !important;">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-3 fw-bold"
                                     style="width:44px;height:44px;background:#C8961E;flex-shrink:0;font-size:18px;">
                                    <?= mb_strtoupper(mb_substr($r['full_name'] ?? 'K', 0, 1)) ?>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($r['full_name'] ?? 'Khách') ?></h6>
                                    <small class="text-muted"><i class="far fa-clock me-1"></i><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></small>
                                </div>
                            </div>
                            <div style="color:#C8961E;">
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <i class="fas fa-star<?= $i > $r['rating'] ? ' opacity-25' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="mb-0 text-muted ps-1"><?= nl2br(htmlspecialchars($r['content'])) ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const stars = document.querySelectorAll('.star-btn');
const input = document.getElementById('ratingInput');
if (stars.length) {
    stars.forEach((s, i) => {
        s.addEventListener('mouseover', () => stars.forEach((x,j) => x.style.color = j<=i ? '#C8961E' : '#aaa'));
        s.addEventListener('mouseout',  () => {
            const v = parseInt(input.value||0);
            stars.forEach((x,j) => x.style.color = j < v ? '#C8961E' : '#aaa');
        });
        s.addEventListener('click', () => {
            input.value = i+1;
            stars.forEach((x,j) => x.style.color = j<=i ? '#C8961E' : '#aaa');
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>
