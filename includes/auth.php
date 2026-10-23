<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Kiểm tra đăng nhập admin
function check_admin_login() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Đăng xuất
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Kiểm tra nếu đã đăng nhập
function is_logged_in() {
    return isset($_SESSION['admin_id']);
}
?>
