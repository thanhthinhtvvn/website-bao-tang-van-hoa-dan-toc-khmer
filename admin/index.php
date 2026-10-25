<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once 'admin_helpers.php';
check_admin_login();

$event_count = $conn->query("SELECT COUNT(*) AS total FROM events")->fetch_assoc()['total'];
$artifact_count = $conn->query("SELECT COUNT(*) AS total FROM artifacts")->fetch_assoc()['total'];
$user_count = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$review_count = $conn->query("SELECT COUNT(*) AS total FROM reviews")->fetch_assoc()['total'];
$events = $conn->query('SELECT id, title, description, image FROM events ORDER BY created_at DESC, id DESC LIMIT 4');
$artifacts = $conn->query('SELECT id, name, image FROM artifacts ORDER BY created_at DESC LIMIT 8');
$reviews = $conn->query('SELECT r.id, r.content, r.rating, r.created_at, u.full_name FROM reviews r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC LIMIT 5');
$slides = $conn->query("SELECT content, url FROM media WHERE `table` = 'hero_slides' ORDER BY id ASC");

function dashboard_image_src(?string $image, string $folder): ?string
{
    if ($image === null || trim($image) === '') {
        return null;
    }

    $image = trim($image);
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $image : null;
    }

    $path = ltrim(str_replace('\\', '/', $image), '/');
    if (in_array('..', explode('/', $path), true)) {
        return null;
    }
    if (strpos($path, '/') === false) {
        $root = dirname(__DIR__);
        foreach (['uploads/' . $folder . '/' . $path, 'images/' . $path] as $candidate) {
            if (is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate))) {
                $path = $candidate;
                break;
            }
        }
    }

    return '../' . implode('/', array_map('rawurlencode', explode('/', $path)));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản trị - Bảo tàng Khmer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <style>
        :root { --gold: #c8961e; --gold-dark: #a07010; --page: #fff; --card: #121212; }
        body { min-height: 100vh; background: var(--page); color: var(--gold-dark); }
        .admin-nav { background: var(--gold); }
        .admin-nav .nav-link, .admin-nav .navbar-brand { color: #fff; }
        .admin-hero .carousel-bg { height: 400px; }
        .admin-hero .carousel-bg img { width: 100%; height: 100%; object-fit: cover; }
        .admin-hero .carousel-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.05) 40%, rgba(0,0,0,0.65) 100%);
        }
        .admin-hero .carousel-caption-custom {
            position: absolute;
            z-index: 2;
            top: 50%;
            transform: translateY(-50%);
            left: 0;
            right: 0;
            text-align: center;
            color: white;
            padding: 0 30px;
        }
        .admin-hero .carousel-caption-custom h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-shadow: 1px 2px 8px rgba(0,0,0,0.95);
            margin-bottom: 6px;
            color: #f5dfa0;
        }
        .admin-hero .carousel-caption-custom p {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 0.92rem;
            letter-spacing: 0.06em;
            text-shadow: 1px 1px 4px rgba(0,0,0,0.9);
            margin-bottom: 0;
            color: #e8d08a;
        }
        .admin-hero { position: relative; }
        .hero-manage-actions {
            position: absolute;
            right: 1rem;
            bottom: 1rem;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .5rem;
        }
        .hero-manage-actions .btn { box-shadow: 0 3px 12px rgba(0,0,0,.35); }
        .admin-hero-empty { min-height: 240px; background: linear-gradient(135deg, #6b5215, #c8961e); }
        .section-heading { color: var(--gold); border-left: 4px solid var(--gold); padding-left: 10px; }
        .content-section {
            position: relative;
            padding: clamp(1rem, 2.5vw, 2rem);
            border: 1px solid #a07010;
            border-radius: 18px;
            background: #c8961e;
            box-shadow: 0 14px 36px rgba(0,0,0,.24);
        }
        .content-section .section-heading { color: #fff; border-color: rgba(255,255,255,.8); }
        .content-section .btn-outline-warning {
            color: #fff;
            border-color: rgba(255,255,255,.75);
        }
        .content-section .btn-outline-warning:hover {
            color: #8a620d;
            background: #fff;
            border-color: #fff;
        }
        .empty-reviews {
            color: #fff !important;
            font-weight: 600;
            margin: 0;
        }
        .admin-site-footer {
            margin-top: 3rem;
            padding: 2.5rem 0 1rem;
            background: var(--gold);
            color: #fff;
            border-top: 3px solid var(--gold);
        }
        .admin-site-footer h5,
        .admin-site-footer h6 { color: #fff; }
        .admin-site-footer .footer-muted { color: #fff; }
        .admin-site-footer hr { border-color: rgba(255,255,255,.5); }
        .admin-site-footer a { color: #fff; }
        .admin-site-footer a:hover { color: #fff; }
        .admin-card { background: #fff; color: #212529; border: 0; border-radius: 8px; overflow: hidden; }
        .content-section .admin-card {
            border: 1px solid rgba(200,150,30,.2);
            border-radius: 12px;
            box-shadow: 0 8px 22px rgba(0,0,0,.24) !important;
            transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
        }
        .content-section .admin-card:hover {
            transform: translateY(-4px);
            border-color: rgba(240,192,64,.65);
            box-shadow: 0 14px 28px rgba(0,0,0,.34) !important;
        }
        .admin-card .card-body { background: #fff; }
        .admin-card .card-title { color: #212529; }
        .admin-card .card-text { color: #212529 !important; }
        .artifact-card-image { height: 280px !important; object-fit: contain !important; background: #fdf6e3; }
        .artifact-card-title { font-size: 1rem; }
        .content-section .admin-card .btn-outline-warning {
            color: var(--gold-dark);
            border-color: var(--gold-dark);
        }
        .content-section .admin-card .btn-outline-warning:hover {
            color: #fff;
            background: var(--gold-dark);
            border-color: var(--gold-dark);
        }
        .admin-card .card-img-top { height: 180px; object-fit: cover; }
        .stat-card { background: var(--gold); color: #fff; border: 0; border-left: 4px solid var(--gold-dark); }
        .stat-card .text-secondary { color: #fff !important; }
        .stat-card .btn-outline-warning { color: #fff; border-color: #fff; }
        .stat-card .btn-outline-warning:hover { color: var(--gold-dark); background: #fff; border-color: #fff; }
        .review-card { background: var(--card); color: #f8f5ed; border: 1px solid rgba(200,150,30,.25); }
        .btn-gold { background: var(--gold); border-color: var(--gold); color: #fff; }
        .btn-gold:hover { background: var(--gold-dark); border-color: var(--gold-dark); color: #fff; }
        @media (max-width: 576px) {
            .admin-hero .carousel-bg { height: 250px; }
            .admin-hero .carousel-caption-custom h1 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg admin-nav">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php" style="font-family:'Playfair Display',serif">Bảo tàng Văn hóa Dân tộc Khmer</a>
            <button class="navbar-toggler border-0 text-white" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-controls="adminNav" aria-expanded="false" aria-label="Mở menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-shield me-1"></i>Admin
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2 text-warning"></i>Đổi mật khẩu</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="logout.php" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="dropdown-item" type="submit"><i class="fas fa-sign-out-alt me-2 text-warning"></i>Đăng xuất</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($slides && $slides->num_rows > 0): ?>
    <div id="adminHero" class="carousel slide carousel-fade admin-hero" data-bs-ride="carousel" data-bs-interval="4000">
        <div class="carousel-inner">
            <?php $slideIndex = 0; while ($slide = $slides->fetch_assoc()): $slideImage = dashboard_image_src($slide['url'] ?? null, 'slides'); ?>
            <div class="carousel-item <?= $slideIndex === 0 ? 'active' : '' ?>">
                <div class="carousel-bg position-relative">
                    <?php if ($slideImage): ?><img class="position-absolute top-0 start-0" src="<?= htmlspecialchars($slideImage, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><div class="position-absolute top-0 start-0 w-100 h-100" style="background:linear-gradient(135deg,#6b5215,#c8961e)"></div><?php endif; ?>
                    <div class="carousel-overlay"></div>
                    <div class="carousel-caption-custom">
                        <h1 class="display-4 fw-bold"><?= htmlspecialchars($slide['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
                        <?php if ($slideIndex === 0): ?>
                        <p class="lead">Khám phá những di sản văn hóa độc đáo của đồng bào Khmer Nam bộ</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php $slideIndex++; endwhile; ?>
        </div>
        <div class="hero-manage-actions">
            <a href="slides.php" class="btn btn-outline-light btn-sm">
                <i class="fas fa-images me-1"></i>Quản lý banner
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="admin-hero-empty d-flex flex-column justify-content-center align-items-center text-center text-white p-4">
        <h1 class="h3 fw-bold mb-3">Chưa có banner trang chủ</h1>
        <a href="slides.php" class="btn btn-light">
            <i class="fas fa-plus me-1"></i>Thêm banner
        </a>
    </div>
    <?php endif; ?>

    <main class="container py-4">
        <div class="row g-3 mb-5">
            <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="small text-secondary">Sự kiện</div><div class="fs-3 fw-bold"><?= (int) $event_count ?></div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="small text-secondary">Hiện vật</div><div class="fs-3 fw-bold"><?= (int) $artifact_count ?></div></div></div></div>
            <div class="col-6 col-xl-3">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <div class="small text-secondary">Tài khoản người dùng</div>
                        <div class="fs-3 fw-bold"><?= (int) $user_count ?></div>
                        <div class="d-flex justify-content-end">
                            <a class="btn btn-sm btn-outline-warning mt-2 small" href="users.php">
                                <i class="fas fa-users me-1"></i>Quản lý người dùng
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="small text-secondary">Đánh giá</div><div class="fs-3 fw-bold"><?= (int) $review_count ?></div></div></div></div>
        </div>

        <section class="content-section mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <h2 class="h4 section-heading fw-bold mb-0">Sự kiện nổi bật</h2>
                <div class="d-flex gap-2">
                    <a href="manage_content.php?type=events#event-banners" class="btn btn-outline-light btn-sm"><i class="fas fa-images me-1"></i>Quản lý banner</a>
                    <a href="manage_content.php?type=events" class="btn btn-outline-warning btn-sm">Quản lý tất cả</a>
                    <a href="manage_content.php?type=events" class="btn btn-gold btn-sm"><i class="fas fa-plus me-1"></i>Thêm sự kiện</a>
                </div>
            </div>
            <div class="row g-4">
                <?php while ($event = $events->fetch_assoc()): $eventImage = dashboard_image_src($event['image'] ?? null, 'events'); ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card admin-card h-100 shadow-sm">
                        <?php if ($eventImage): ?><img class="card-img-top" src="<?= htmlspecialchars($eventImage, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php else: ?><div class="d-flex align-items-center justify-content-center" style="height:180px;background:linear-gradient(135deg,#6b5215,#c8961e)"><i class="fas fa-calendar-star fa-3x text-white opacity-50"></i></div><?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h3 class="h5 card-title"><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="card-text small flex-grow-1"><?= htmlspecialchars(mb_substr($event['description'] ?? '', 0, 110), ENT_QUOTES, 'UTF-8') ?>...</p>
                            <div class="d-flex gap-2">
                                <a href="manage_content.php?type=events&edit=<?= (int) $event['id'] ?>" class="btn btn-outline-warning btn-sm flex-grow-1"><i class="fas fa-edit me-1"></i>Sửa</a>
                                <form method="post" action="manage_content.php?type=events" onsubmit="return confirm('Bạn có chắc muốn xóa sự kiện này?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Xóa sự kiện"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="content-section mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <h2 class="h4 section-heading fw-bold mb-0">Hiện vật nổi bật</h2>
                <div class="d-flex gap-2">
                    <a href="manage_content.php?type=artifacts" class="btn btn-outline-warning btn-sm">Quản lý tất cả</a>
                    <a href="manage_content.php?type=artifacts" class="btn btn-gold btn-sm"><i class="fas fa-plus me-1"></i>Thêm hiện vật</a>
                </div>
            </div>
            <div class="row g-4">
                <?php while ($artifact = $artifacts->fetch_assoc()): $artifactImage = dashboard_image_src($artifact['image'] ?? null, 'artifacts'); ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card admin-card h-100 shadow-sm">
                        <?php if ($artifactImage): ?>
                            <img class="card-img-top artifact-card-image" src="<?= htmlspecialchars($artifactImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($artifact['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center" style="height:280px;background:linear-gradient(135deg,#6b5215,#c8961e)">
                                <i class="fas fa-image fa-3x text-white opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column justify-content-between">
                            <h3 class="h6 card-title artifact-card-title text-center"><?= htmlspecialchars($artifact['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="d-flex gap-2">
                                <a href="manage_content.php?type=artifacts&edit=<?= (int) $artifact['id'] ?>" class="btn btn-outline-warning btn-sm flex-grow-1"><i class="fas fa-edit me-1"></i>Sửa</a>
                                <form method="post" action="manage_content.php?type=artifacts" onsubmit="return confirm('Bạn có chắc muốn xóa hiện vật này?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $artifact['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Xóa hiện vật"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </section>

        <section class="content-section mb-5">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <h2 class="h4 section-heading fw-bold mb-0">Đánh giá gần đây</h2>
                <div class="d-flex flex-wrap gap-2">
                    <a href="manage_content.php?type=reviews" class="btn btn-outline-warning btn-sm">Quản lý tất cả (<?= (int) $review_count ?>)</a>
                </div>
            </div>
            <?php if ($review_count > 0): ?>
            <div class="row g-3">
                <?php while ($review = $reviews->fetch_assoc()): ?>
                <div class="col-lg-6">
                    <article class="card review-card h-100">
                        <div class="card-body d-flex justify-content-between gap-3">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($review['full_name'] ?? 'Ẩn danh', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-warning mb-2"><?= str_repeat('★', (int) $review['rating']) ?></div>
                                <p class="mb-1"><?= nl2br(htmlspecialchars($review['content'], ENT_QUOTES, 'UTF-8')) ?></p>
                                <small class="text-secondary"><?= htmlspecialchars($review['created_at'], ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <form method="post" action="manage_content.php?type=reviews" onsubmit="return confirm('Bạn có chắc muốn xóa đánh giá này?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm" type="submit" aria-label="Xóa đánh giá"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </article>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <p class="empty-reviews">Chưa có đánh giá nào.</p>
            <?php endif; ?>
        </section>

    </main>
<?php
$isAdminDashboard = true;
include '../includes/footer.php';
?>
