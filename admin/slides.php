<?php
require_once '../config/db.php';
require_once 'auth.php';
check_admin_login();

$msg = '';

// XÓA
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM media WHERE id = $id AND `table` = 'hero_slides'");
    header("Location: slides.php?msg=deleted");
    exit();
}

// LẤY BẢN GHI ĐỂ SỬA
$edit = null;
if (isset($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $edit = $conn->query("SELECT * FROM media WHERE id = $id AND `table` = 'hero_slides'")->fetch_assoc();
}

// THÊM / CẬP NHẬT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $content = $conn->real_escape_string(trim($_POST['content']));
    $url     = $conn->real_escape_string(trim($_POST['url']));

    if (empty($url)) {
        $msg = '<div class="alert alert-danger">Vui lòng nhập URL ảnh.</div>';
    } else {
        if ($id > 0) {
            $conn->query("UPDATE media SET content = '$content', url = '$url' WHERE id = $id AND `table` = 'hero_slides'");
            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>Cập nhật thành công!</div>';
        } else {
            $conn->query("INSERT INTO media (`table`, content, url) VALUES ('hero_slides', '$content', '$url')");
            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>Thêm slide thành công!</div>';
        }
        $edit = null;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $msg = '<div class="alert alert-warning"><i class="fas fa-trash me-1"></i>Đã xóa slide.</div>';
}

// Danh sách slides
$rows = $conn->query("SELECT * FROM media WHERE `table` = 'hero_slides' ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Slides - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --gold: #C8961E; --gold-dark: #a07010; }
        body { background: #f8f5ee; }
        .slide-thumb { width: 110px; height: 65px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; }
        .preview-box { max-height: 160px; width: 100%; object-fit: cover; border-radius: 8px; display: none; margin-top: 8px; }
    </style>
</head>
<body class="p-4">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0" style="color:var(--gold-dark);">
        <i class="fas fa-images me-2"></i>Quản lý Slides Trang Chủ
    </h4>
    <a href="../index.php" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-external-link-alt me-1"></i>Xem trang chủ
    </a>
</div>

<?= $msg ?>

<div class="row g-4">
    <!-- FORM -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-bold text-white"
                 style="background:var(--gold);">
                <i class="fas fa-<?= $edit ? 'edit' : 'plus-circle' ?> me-1"></i>
                <?= $edit ? 'Sửa Slide' : 'Thêm Slide Mới' ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?= $edit['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mô tả (content)</label>
                        <input type="text" name="content" class="form-control"
                               placeholder="vd: Slide 1 - Trang chủ"
                               value="<?= htmlspecialchars($edit['content'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL ảnh <span class="text-danger">*</span></label>
                        <input type="text" name="url" id="urlInput" class="form-control"
                               placeholder="https://..."
                               value="<?= htmlspecialchars($edit['url'] ?? '') ?>"
                               oninput="previewUrl(this.value)" required>
                        <img id="urlPreview" class="preview-box"
                             src="<?= htmlspecialchars($edit['url'] ?? '') ?>"
                             <?= !empty($edit['url']) ? 'style="display:block"' : '' ?>>
                        <div class="form-text">Dán link ảnh từ internet hoặc đường dẫn file.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn text-white fw-semibold"
                                style="background:var(--gold);">
                            <i class="fas fa-save me-1"></i><?= $edit ? 'Cập nhật' : 'Thêm' ?>
                        </button>
                        <?php if ($edit): ?>
                        <a href="slides.php" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>Hủy
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3 border-0 bg-light">
            <div class="card-body py-3 small text-muted">
                <strong><i class="fas fa-info-circle me-1 text-info"></i>Lưu ý:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <li>Cột <code>table</code> = <code>hero_slides</code> (tự động).</li>
                    <li>Cột <code>content</code> là tên/mô tả slide.</li>
                    <li>Cột <code>url</code> là link ảnh hiển thị trên carousel.</li>
                    <li>Ảnh nên rộng ít nhất <strong>1280px</strong>.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- DANH SÁCH -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header fw-bold text-white" style="background:var(--gold-dark);">
                <i class="fas fa-list me-1"></i>Danh sách (table = hero_slides)
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>content</th>
                            <th>url</th>
                            <th class="text-center">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($rows && $rows->num_rows > 0): ?>
                        <?php while ($r = $rows->fetch_assoc()): ?>
                        <tr>
                            <td class="text-muted"><?= $r['id'] ?></td>
                            <td>
                                <img src="<?= htmlspecialchars($r['url']) ?>"
                                     class="slide-thumb"
                                     onerror="this.src='https://placehold.co/110x65?text=No+Image'"
                                     alt="">
                            </td>
                            <td><?= htmlspecialchars($r['content'] ?? '—') ?></td>
                            <td class="small text-break" style="max-width:200px;">
                                <a href="<?= htmlspecialchars($r['url']) ?>" target="_blank" title="<?= htmlspecialchars($r['url']) ?>">
                                    <?= htmlspecialchars(mb_substr($r['url'], 0, 50)) ?>...
                                </a>
                            </td>
                            <td class="text-center" style="white-space:nowrap;">
                                <a href="slides.php?edit=<?= $r['id'] ?>"
                                   class="btn btn-sm btn-outline-warning me-1">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="slides.php?delete=<?= $r['id'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   onclick="return confirm('Xóa slide #<?= $r['id'] ?>?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Chưa có slide nào.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function previewUrl(url) {
    const img = document.getElementById('urlPreview');
    if (url.startsWith('http')) {
        img.src = url;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }
}
</script>
</body>
</html>
