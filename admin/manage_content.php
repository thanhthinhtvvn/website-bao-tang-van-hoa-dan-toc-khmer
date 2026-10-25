<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once 'admin_helpers.php';
check_admin_login();

$type = is_string($_GET['type'] ?? null) ? $_GET['type'] : 'events';
$sections = [
    'events' => ['title' => 'Quản lý sự kiện', 'table' => 'events', 'folder' => 'events'],
    'artifacts' => ['title' => 'Quản lý hiện vật', 'table' => 'artifacts', 'folder' => 'artifacts'],
    'reviews' => ['title' => 'Quản lý đánh giá', 'table' => 'reviews', 'folder' => null],
];
if (!isset($sections[$type])) {
    http_response_code(404);
    exit('Không tìm thấy mục quản lý.');
}
$section = $sections[$type];
$message = '';
$messageType = 'success';
$edit = null;
$bannerEdit = null;

function event_banner_preview_src(string $url): string
{
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    $path = str_replace('\\', '/', $url);
    if ($path === '' || str_starts_with($path, '//')
        || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)
        || in_array('..', explode('/', $path), true)) {
        return '';
    }

    return '../' . implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id || $id < 0) {
        $id = 0;
    }

    try {
        if ($type === 'events' && $action === 'banner_delete' && $id > 0) {
            $stmt = $conn->prepare("DELETE FROM media WHERE id = ? AND `table` = 'event_slides'");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            header('Location: manage_content.php?type=events&msg=banner_deleted');
            exit();
        }

        if ($type === 'events' && $action === 'banner_save') {
            $content = trim(is_string($_POST['content'] ?? null) ? $_POST['content'] : '');
            $url = trim(is_string($_POST['url'] ?? null) ? $_POST['url'] : '');
            $bannerEdit = ['id' => $id, 'content' => $content, 'url' => $url];
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            $isWebUrl = filter_var($url, FILTER_VALIDATE_URL)
                && in_array($scheme, ['http', 'https'], true);
            $isLocalPath = $url !== ''
                && !str_starts_with($url, '//')
                && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)
                && !in_array('..', explode('/', str_replace('\\', '/', $url)), true);

            if ($url === '' || (!$isWebUrl && !$isLocalPath)) {
                throw new RuntimeException('Vui lòng nhập URL ảnh http/https hoặc đường dẫn ảnh hợp lệ.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE media SET content = ?, url = ? WHERE id = ? AND `table` = 'event_slides'");
                $stmt->bind_param('ssi', $content, $url, $id);
                $stmt->execute();
                $updated = $stmt->affected_rows;
                $stmt->close();
                if ($updated === 0) {
                    $check = $conn->prepare("SELECT id FROM media WHERE id = ? AND `table` = 'event_slides'");
                    $check->bind_param('i', $id);
                    $check->execute();
                    $exists = $check->get_result()->num_rows > 0;
                    $check->close();
                    if (!$exists) {
                        throw new RuntimeException('Không tìm thấy banner sự kiện cần sửa.');
                    }
                }
                header('Location: manage_content.php?type=events&msg=banner_saved');
                exit();
            }

            $stmt = $conn->prepare("INSERT INTO media (`table`, content, url) VALUES ('event_slides', ?, ?)");
            $stmt->bind_param('ss', $content, $url);
            $stmt->execute();
            $stmt->close();
            header('Location: manage_content.php?type=events&msg=banner_added');
            exit();
        }

        if ($type === 'reviews' && $action === 'delete' && $id > 0) {
            $stmt = $conn->prepare('DELETE FROM reviews WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            header('Location: manage_content.php?type=reviews&msg=deleted');
            exit();
        }

        if ($type !== 'reviews' && in_array($action, ['save', 'delete'], true)) {
            if ($action === 'delete' && $id > 0) {
                $stmt = $conn->prepare("DELETE FROM {$section['table']} WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                header('Location: manage_content.php?type=' . $type . '&msg=deleted');
                exit();
            }

            if ($action === 'save') {
                $image = '';
                if ($id > 0) {
                    $imageStmt = $conn->prepare("SELECT image FROM {$section['table']} WHERE id = ?");
                    $imageStmt->bind_param('i', $id);
                    $imageStmt->execute();
                    $existingImage = $imageStmt->get_result()->fetch_assoc();
                    $imageStmt->close();
                    if (!$existingImage) {
                        throw new RuntimeException('Không tìm thấy nội dung cần chỉnh sửa.');
                    }
                    $image = $existingImage['image'] ?? '';
                }

                if ($type === 'events') {
                    $title = trim($_POST['title'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    if ($title === '') {
                        throw new RuntimeException('Vui lòng nhập tên sự kiện.');
                    }

                    if ($id > 0) {
                        $stmt = $conn->prepare('UPDATE events SET title = ?, description = ?, image = ? WHERE id = ?');
                        $stmt->bind_param('sssi', $title, $description, $image, $id);
                    } else {
                        $adminId = (int) $_SESSION['admin_id'];
                        $stmt = $conn->prepare('INSERT INTO events (title, description, image, admin_id) VALUES (?, ?, ?, ?)');
                        $stmt->bind_param('sssi', $title, $description, $image, $adminId);
                    }
                } else {
                    $name = trim($_POST['name'] ?? '');
                    $description = is_string($_POST['description'] ?? null)
                        ? trim($_POST['description'])
                        : '';
                    if ($name === '') {
                        throw new RuntimeException('Vui lòng nhập tên hiện vật.');
                    }
                }

                $uploadedImage = admin_upload_image('image_file', $section['folder']);
                if ($uploadedImage !== null) {
                    $image = $uploadedImage;
                }

                if ($type === 'artifacts') {
                    if ($id > 0) {
                        $stmt = $conn->prepare('UPDATE artifacts SET name = ?, description = ?, image = ? WHERE id = ?');
                        $stmt->bind_param('sssi', $name, $description, $image, $id);
                    } else {
                        $adminId = (int) $_SESSION['admin_id'];
                        $stmt = $conn->prepare('INSERT INTO artifacts (name, description, image, admin_id) VALUES (?, ?, ?, ?)');
                        $stmt->bind_param('sssi', $name, $description, $image, $adminId);
                    }
                }

                if (!$stmt->execute()) {
                    throw new RuntimeException('Không thể lưu dữ liệu: ' . $stmt->error);
                }
                $stmt->close();
                header('Location: manage_content.php?type=' . $type . '&msg=saved');
                exit();
            }
        }
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
        $messageType = 'danger';
    }
}

if ($type !== 'reviews' && isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $conn->prepare("SELECT * FROM {$section['table']} WHERE id = ?");
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $edit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($type === 'artifacts') {
    $artifactDescription = $_SERVER['REQUEST_METHOD'] === 'POST'
        && is_string($_POST['description'] ?? null)
        ? $_POST['description']
        : ($edit['description'] ?? '');
}

if ($type === 'events' && isset($_GET['banner_edit'])) {
    $bannerEditId = filter_var($_GET['banner_edit'], FILTER_VALIDATE_INT);
    if ($bannerEditId && $bannerEditId > 0) {
        $stmt = $conn->prepare("SELECT id, content, url FROM media WHERE id = ? AND `table` = 'event_slides'");
        $stmt->bind_param('i', $bannerEditId);
        $stmt->execute();
        $bannerEdit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$bannerEdit) {
            $message = 'Không tìm thấy banner sự kiện cần sửa.';
            $messageType = 'danger';
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'saved') {
        $message = 'Đã lưu nội dung thành công.';
    } elseif ($_GET['msg'] === 'deleted') {
        $message = 'Đã xóa nội dung thành công.';
    } elseif ($_GET['msg'] === 'banner_added') {
        $message = 'Đã thêm banner sự kiện thành công.';
    } elseif ($_GET['msg'] === 'banner_saved') {
        $message = 'Đã cập nhật banner sự kiện thành công.';
    } elseif ($_GET['msg'] === 'banner_deleted') {
        $message = 'Đã xóa banner sự kiện.';
    }
}

if ($type === 'events') {
    $rows = $conn->query('SELECT id, title, description, image FROM events ORDER BY created_at DESC, id DESC');
    $eventBanners = $conn->query("SELECT id, content, url FROM media WHERE `table` = 'event_slides' ORDER BY id ASC");
} elseif ($type === 'artifacts') {
    $rows = $conn->query('SELECT id, name, description, image FROM artifacts ORDER BY created_at DESC');
} else {
    $rows = $conn->query('SELECT r.id, r.content, r.rating, r.created_at, u.full_name, u.email FROM reviews r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC');
}

admin_page_start($section['title']);
?>
<style>
    .event-banner-manager { width: 100%; max-width: 560px; margin: 0 auto 1.5rem; }
    .event-banner-manager .card-header { padding: .65rem 1rem; }
    .event-banner-manager .card-body { padding: 1rem; }
    .event-banner-form { padding: 0; }
    .event-banner-item { display: flex; align-items: center; gap: .85rem; padding: .65rem; }
    .event-banner-item img,
    .event-banner-item .banner-placeholder { flex: 0 0 112px; width: 112px; height: 70px; object-fit: cover; border-radius: .375rem; }
    .event-banner-item .banner-placeholder { display: grid; place-items: center; background: #f8f9fa; color: #6c757d; }
    .event-banner-item .banner-details { min-width: 0; flex: 1; }
    @media (max-width: 576px) {
        .event-banner-item { align-items: flex-start; gap: .6rem; }
        .event-banner-item img,
        .event-banner-item .banner-placeholder { flex-basis: 82px; width: 82px; height: 60px; }
    }
</style>
<main class="container-fluid admin-main py-4">
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($type === 'events'): ?>
    <section id="event-banners" class="card border-0 shadow-sm mb-4 event-banner-manager">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-bold"><i class="fas fa-images text-warning me-2"></i>Quản lý banner sự kiện</span>
            <span class="badge text-bg-warning"><?= (int) $eventBanners->num_rows ?> banner</span>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <form method="post" class="event-banner-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="banner_save">
                        <input type="hidden" name="id" value="<?= (int) ($bannerEdit['id'] ?? 0) ?>">
                        <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="event-banner-content">Tiêu đề / nội dung</label>
                            <input class="form-control" id="event-banner-content" name="content" maxlength="255"
                                value="<?= htmlspecialchars($bannerEdit['content'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="event-banner-url">Đường dẫn ảnh <span class="text-danger">*</span></label>
                            <input class="form-control" id="event-banner-url" name="url" required
                                placeholder="https://... hoặc images/banner.jpg"
                                value="<?= htmlspecialchars($bannerEdit['url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-3 d-flex flex-wrap gap-2">
                            <button class="btn btn-warning text-white" type="submit"><i class="fas fa-save me-1"></i><?= $bannerEdit ? 'Lưu' : 'Thêm banner' ?></button>
                            <?php if ($bannerEdit): ?><a class="btn btn-outline-secondary" href="manage_content.php?type=events">Hủy</a><?php endif; ?>
                        </div>
                        </div>
                        <div class="form-text mt-2">Dùng URL ảnh http/https hoặc đường dẫn tương đối trong website.</div>
                    </form>
                </div>
                <div class="col-12">
                    <?php if ($eventBanners->num_rows > 0): ?>
                    <div class="row g-2">
                        <?php while ($banner = $eventBanners->fetch_assoc()): $preview = event_banner_preview_src($banner['url']); ?>
                        <div class="col-12">
                            <article class="border rounded event-banner-item">
                                <?php if ($preview !== ''): ?>
                                    <img src="<?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?>" alt=""
                                        >
                                <?php else: ?>
                                    <div class="banner-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="banner-details">
                                    <h3 class="small fw-bold mb-1 text-truncate"><?= htmlspecialchars($banner['content'] ?: 'Banner chưa có tiêu đề', ENT_QUOTES, 'UTF-8') ?></h3>
                                    <div class="small text-muted text-truncate"><?= htmlspecialchars($banner['url'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a class="btn btn-sm btn-outline-primary" href="manage_content.php?type=events&banner_edit=<?= (int) $banner['id'] ?>">Sửa</a>
                                    <form method="post" onsubmit="return confirm('Bạn có chắc muốn xóa banner này?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="banner_delete">
                                        <input type="hidden" name="id" value="<?= (int) $banner['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                    </form>
                                </div>
                            </article>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                        <div class="h-100 d-flex flex-column justify-content-center align-items-center text-center text-muted p-4 border rounded">
                            <i class="fas fa-images fa-2x text-warning mb-3"></i>
                            <p class="mb-0">Chưa có banner sự kiện. Thêm banner bằng biểu mẫu bên cạnh.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($type !== 'reviews'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">
            <?= $edit ? 'Chỉnh sửa' : 'Thêm mới' ?>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                <?php if ($type === 'events'): ?>
                <div class="col-md-6">
                    <label class="form-label">Tên sự kiện</label>
                    <input class="form-control" name="title" maxlength="200" required value="<?= htmlspecialchars($edit['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <?php else: ?>
                <div class="col-md-6">
                    <label class="form-label">Tên hiện vật</label>
                    <input class="form-control" name="name" maxlength="200" required value="<?= htmlspecialchars($edit['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <?php endif; ?>

                <?php if ($type === 'artifacts'): ?>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($artifactDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <?php else: ?>
                <div class="col-12">
                    <label class="form-label">Mô tả</label>
                    <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($edit['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
                <?php endif; ?>
                <div class="col-md-8">
                    <label class="form-label">Ảnh <?= $edit ? '(để trống nếu giữ ảnh hiện tại)' : '(không bắt buộc)' ?></label>
                    <input class="form-control" type="file" name="image_file" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="form-text">JPG, PNG, GIF hoặc WEBP, tối đa 5 MB.</div>
                    <?php if (!empty($edit['image'])): ?>
                        <div class="small text-muted mt-1">Ảnh hiện tại: <?= htmlspecialchars($edit['image'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <button class="btn btn-warning text-white" type="submit">
                        <i class="fas fa-save me-1"></i><?= $edit ? 'Lưu thay đổi' : 'Thêm mới' ?>
                    </button>
                    <?php if ($edit): ?><a class="btn btn-outline-secondary" href="manage_content.php?type=<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">Hủy</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <p class="text-muted">Chọn xóa để gỡ một đánh giá khỏi website. Thao tác này không thể hoàn tác.</p>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <?php if ($type === 'reviews'): ?>
                            <th>Người đánh giá</th><th>Nội dung</th><th>Số sao</th><th>Ngày gửi</th><th></th>
                        <?php else: ?>
                            <th>Ảnh</th><th>Tên</th><th>Mô tả</th>
                            <th></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <?php if ($type === 'reviews'): ?>
                            <td><?= htmlspecialchars($row['full_name'] ?? 'Ẩn danh', ENT_QUOTES, 'UTF-8') ?><div class="small text-muted"><?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div></td>
                            <td style="min-width:240px;white-space:pre-wrap"><?= htmlspecialchars($row['content'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= str_repeat('★', (int) $row['rating']) ?></td>
                            <td><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Bạn có chắc muốn xóa đánh giá này?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                </form>
                            </td>
                        <?php else: ?>
                            <td><?php if (!empty($row['image'])): ?><img src="../uploads/<?= htmlspecialchars($section['folder'], ENT_QUOTES, 'UTF-8') ?>/<?= rawurlencode(basename($row['image'])) ?>" alt=""><?php else: ?><span class="text-muted">Chưa có ảnh</span><?php endif; ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($row[$type === 'events' ? 'title' : 'name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td style="min-width:220px">
                                <?= htmlspecialchars(mb_substr($row['description'] ?? '', 0, 140), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td class="text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="manage_content.php?type=<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>&edit=<?= (int) $row['id'] ?>">Sửa</a>
                                <form class="d-inline" method="post" onsubmit="return confirm('Bạn có chắc muốn xóa mục này?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Xóa</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php admin_page_end(); ?>
