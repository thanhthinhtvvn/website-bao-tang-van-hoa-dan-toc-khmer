<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once 'admin_helpers.php';
check_admin_login();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$userId || $userId < 1) {
        http_response_code(400);
        exit('Tài khoản người dùng không hợp lệ.');
    }

    $deleteUser = $conn->prepare('DELETE FROM users WHERE id = ?');
    if (!$deleteUser) {
        error_log('Could not prepare user deletion: ' . $conn->error);
        http_response_code(500);
        exit('Không thể xóa tài khoản lúc này. Vui lòng thử lại sau.');
    }
    $deleteUser->bind_param('i', $userId);
    if (!$deleteUser->execute()) {
        error_log('Could not delete user: ' . $deleteUser->error);
        $deleteUser->close();
        http_response_code(500);
        exit('Không thể xóa tài khoản lúc này. Vui lòng thử lại sau.');
    }
    $deleted = $deleteUser->affected_rows > 0;
    $deleteUser->close();

    $redirect = $deleted ? 'deleted' : 'not_found';
    header('Location: users.php?msg=' . $redirect);
    exit();
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $message = 'Đã xóa tài khoản người dùng cùng các đánh giá và lượt tham quan liên quan.';
    } elseif ($_GET['msg'] === 'not_found') {
        $message = 'Không tìm thấy tài khoản người dùng cần xóa.';
        $messageType = 'warning';
    }
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$perPage = 50;
$totalUsers = (int) $conn->query('SELECT COUNT(*) AS total FROM users')->fetch_assoc()['total'];
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$usersQuery = $conn->prepare(
    'SELECT id, full_name, email, phone, created_at
     FROM users ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?'
);
if (!$usersQuery) {
    error_log('Could not prepare user list query: ' . $conn->error);
    http_response_code(500);
    exit('Không thể tải danh sách người dùng lúc này.');
}
$usersQuery->bind_param('ii', $perPage, $offset);
if (!$usersQuery->execute()) {
    error_log('Could not load user list: ' . $usersQuery->error);
    $usersQuery->close();
    http_response_code(500);
    exit('Không thể tải danh sách người dùng lúc này.');
}
$users = $usersQuery->get_result();

admin_page_start('Quản lý người dùng');
?>
<main class="container-fluid admin-main py-4">
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-warning">
        <i class="fas fa-triangle-exclamation me-1"></i>
        Xóa tài khoản cũng sẽ xóa các đánh giá và lượt tham quan liên quan. Thao tác này không thể hoàn tác.
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Họ và tên</th>
                        <th>Email</th>
                        <th>Số điện thoại</th>
                        <th>Ngày đăng ký</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows === 0): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Chưa có tài khoản người dùng nào.</td></tr>
                    <?php else: ?>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= (int) $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <form method="post" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này? Các đánh giá và lượt tham quan liên quan cũng sẽ bị xóa.')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">
                                            <i class="fas fa-trash me-1"></i>Xóa
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4" aria-label="Phân trang danh sách người dùng">
            <ul class="pagination justify-content-center">
                <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                    <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>">
                        <a class="page-link" href="users.php?page=<?= $pageNumber ?>"><?= $pageNumber ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</main>
<?php
$usersQuery->close();
admin_page_end();
?>
