<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Bảo Tàng Văn Hóa Dân Tộc Khmer'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold: #C8961E;
            --gold-dark: #a07010;
            --gold-light: #f0c040;
            --dark: #1a1a1a;
        }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #fdf6e3;
        }
        body > footer {
            margin-top: auto;
        }
        /* Navbar */
        .navbar-custom {
            background: var(--gold);
        }
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: #fff !important;
        }
        .navbar-custom .nav-link:hover {
            color: var(--dark) !important;
        }
        .navbar-custom .dropdown-menu {
            background: #2d2010;
            border: 1px solid var(--gold-dark);
            margin-top: 4px;
            border-radius: 8px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }
        .navbar-custom .dropdown-item {
            color: #f0d88a;
            font-size: 0.9rem;
            padding: 8px 16px;
        }
        .navbar-custom .dropdown-item:hover {
            background: rgba(200,150,30,0.25);
            color: #fff;
        }
        /* Mobile menu */
        @media (max-width: 991px) {
            .navbar-custom .navbar-collapse {
                background: #1e1408;
                border-radius: 0 0 12px 12px;
                padding: 8px 0 12px;
                margin-top: 6px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            }
            .navbar-custom .nav-link {
                padding: 11px 20px !important;
                border-bottom: 1px solid rgba(200,150,30,0.15);
                font-size: 0.97rem;
            }
            .navbar-custom .nav-link:last-child {
                border-bottom: none;
            }
            /* Dropdown trên mobile mở thẳng, không cần hover */
            .navbar-custom .dropdown-menu {
                background: rgba(255,255,255,0.05);
                border: none;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
            .navbar-custom .dropdown-item {
                padding: 10px 36px;
                border-bottom: 1px solid rgba(200,150,30,0.1);
                font-size: 0.9rem;
            }
            .navbar-custom .dropdown-item:last-child {
                border-bottom: none;
            }
            .navbar-custom .dropdown-toggle::after {
                float: right;
                margin-top: 6px;
            }
        }
        /* Headings & text */
        .text-primary-custom { color: var(--gold) !important; }
        .text-gold { color: var(--gold) !important; }
        /* Backgrounds */
        .bg-primary-custom { background-color: var(--gold) !important; }
        .bg-gold-light { background-color: #fdf0d0 !important; }
        /* Buttons */
        .btn-primary-custom, .btn-warning {
            background-color: var(--gold);
            border-color: var(--gold-dark);
            color: #fff !important;
        }
        .btn-primary-custom:hover, .btn-warning:hover {
            background-color: var(--gold-dark);
            border-color: var(--gold-dark);
        }
        .btn-outline-danger { border-color: var(--gold); color: var(--gold) !important; }
        .btn-outline-danger:hover { background-color: var(--gold); color: #fff !important; }
        /* Badges */
        .badge.bg-warning { background-color: var(--gold) !important; color: #fff !important; }
        .badge.bg-danger  { background-color: var(--gold-dark) !important; color: #fff !important; }
        /* Cards */
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(200,150,30,0.3);
            transition: all 0.3s;
        }
        /* Stats bar */
        .bg-primary-custom h2 { color: #fff !important; }
        /* Section titles */
        h3.text-primary-custom { border-left: 4px solid var(--gold); padding-left: 10px; }
        /* Star rating */
        .star-rating { color: var(--gold); }
        /* Carousel */
        .carousel-bg {
            height: 400px;
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .carousel-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.05) 40%, rgba(0,0,0,0.65) 100%);
        }
        .carousel-caption-custom {
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

        .carousel-caption-custom h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-shadow: 1px 2px 8px rgba(0,0,0,0.95);
            margin-bottom: 6px;
            color: #f5dfa0;
        }
        .carousel-caption-custom p {
            font-family: 'Cormorant Garamond', serif;
            font-style: italic;
            font-size: 0.92rem;
            letter-spacing: 0.06em;
            text-shadow: 1px 1px 4px rgba(0,0,0,0.9);
            margin-bottom: 0;
            color: #e8d08a;
        }
        /* Footer */
        footer {
            background-color: var(--gold);
            color: #fff;
            padding: 40px 0 20px;
            margin-top: 60px;
        }
        footer .text-secondary,
        footer .footer-muted,
        footer .text-warning { color: #fff !important; }
        footer a { color: #fff !important; }
        footer a:hover { color: #fff !important; }
        footer h5, footer h6 { color: #fff !important; }
        footer hr { border-color: rgba(255,255,255,.5) !important; }
        @media (max-width: 576px) {
            .carousel-bg { height: 250px; }
            .carousel-caption-custom h1 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"
               style="font-family:'Playfair Display',serif; font-size:1.15rem; letter-spacing:0.03em;">
                Bảo tàng Văn hóa Dân tộc Khmer
            </a>
            <button class="navbar-toggler border-0" type="button"
                    data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">



                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-calendar-check me-1"></i>Đăng ký tham quan
                        </a>
                    </li>

                    <!-- Tài khoản -->                    <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="user_logout.php">
                                    <i class="fas fa-sign-out-alt me-2" style="color:var(--gold)"></i>Đăng xuất
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#"
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i>Tài khoản
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="user_login.php">
                                    <i class="fas fa-sign-in-alt me-2" style="color:var(--gold)"></i>Đăng nhập
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="user_register.php">
                                    <i class="fas fa-user-plus me-2" style="color:var(--gold)"></i>Đăng ký tài khoản
                                </a>
                            </li>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php">
                            <i class="fas fa-user-shield me-1"></i>Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
