<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once 'admin_helpers.php';
check_admin_login();

$message = '';
$messageType = 'success';
$edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    $id = $id && $id > 0 ? $id : 0;

    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare("DELETE FROM media WHERE id = ? AND `table` = 'hero_slides'");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        header('Location: slides.php?msg=deleted');
        exit();
    }

    if ($action === 'save') {
        $content = trim(is_string($_POST['content'] ?? null) ? $_POST['content'] : '');
        $url = trim(is_string($_POST['url'] ?? null) ? $_POST['url'] : '');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $isWebUrl = filter_var($url, FILTER_VALIDATE_URL)
            && in_array($scheme, ['http', 'https'], true);
        $isLocalPath = $url !== ''
            && !str_starts_with($url, '//')
            && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $url)
            && !in_array('..', explode('/', str_replace('\\', '/', $url)), true);

        if ($url === '' || (!$isWebUrl && !$isLocalPath)) {
            $message = 'Vui lòng nhập URL ảnh http/https hoặc đường dẫn ảnh hợp lệ.';
            $messageType = 'danger';
            $edit = ['id' => $id, 'content' => $content, 'url' => $url];
        } elseif ($id > 0) {
            $stmt = $conn->prepare("UPDATE media SET content = ?, url = ? WHERE id = ? AND `table` = 'hero_slides'");
            $stmt->bind_param('ssi', $content, $url, $id);
            $stmt->execute();
            $updated = $stmt->affected_rows;
            $stmt->close();
            if ($updated === 0) {
                $check = $conn->prepare("SELECT id FROM media WHERE id = ? AND `table` = 'hero_slides'");
                $check->bind_param('i', $id);
                $check->execute();
                $exists = $check->get_result()->num_rows > 0;
                $check->close();
                if (!$exists) {
                    $message = 'Không tìm thấy slide cần sửa.';
                    $messageType = 'danger';
                }
            }
            if ($message === '') {
                header('Location: slides.php?msg=saved');
                exit();
            }
            $edit = ['id' => $id, 'content' => $content, 'url' => $url];
        } else {
            $stmt = $conn->prepare("INSERT INTO media (`table`, content, url) VALUES ('hero_slides', ?, ?)");
            $stmt->bind_param('ss', $content, $url);
            $stmt->execute();
            $stmt->close();
            header('Location: slides.php?msg=added');
            exit();
        }
    } else {
        if ($action !== 'delete') {
            $message = 'Thao tác không hợp lệ.';
            $messageType = 'danger';
        }
    }
}

if ($message === '' && isset($_GET['msg'])) {
    $message = match ($_GET['msg']) {
        'added' => 'Đã thêm banner thành công.',
        'saved' => 'Đã cập nhật banner thành công.',
        'deleted' => 'Đã xóa banner.',
        default => '',
    };
    $messageType = $_GET['msg'] === 'deleted' ? 'warning' : 'success';
}

if ($edit === null && isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId && $editId > 0) {
        $stmt = $conn->prepare("SELECT id, content, url FROM media WHERE id = ? AND `table` = 'hero_slides'");
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $edit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$edit) {
            $message = 'Không tìm thấy banner cần sửa.';
            $messageType = 'danger';
        }
    }
}

$slides = $conn->query("SELECT id, content, url FROM media WHERE `table` = 'hero_slides' ORDER BY id ASC");

function slide_preview_src(string $url): string
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

admin_page_start('Quản lý ảnh trang chủ');
?>
<style>
    .slides-main { max-width: 1440px; }
    .slides-card { border: 0; border-radius: 14px; overflow: hidden; }
    .slides-card .card-header { background: linear-gradient(135deg, #c8961e, #a07010); }
    .slide-preview { width: 100%; height: 190px; object-fit: cover; background: #211d16; }
    .slide-empty-preview { height: 190px; background: linear-gradient(135deg, #6b5215, #c8961e); }
    .slide-url { overflow-wrap: anywhere; }
</style>
<main class="container-fluid admin-main slides-main py-4">
    <?php if ($message !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <section class="card slides-card shadow-sm">
                <div class="card-header text-white fw-bold py-3">
                    <i class="fas fa-<?= $edit ? 'edit' : 'plus-circle' ?> me-2"></i><?= $edit ? 'Chỉnh sửa banner' : 'Thêm banner mới' ?>
                </div>
                <div class="card-body p-4">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="slide-content">Tiêu đề / nội dung</label>
                            <input class="form-control" id="slide-content" name="content" maxlength="255"
                                placeholder="Ví dụ: Khám phá văn hóa Khmer"
                                value="<?= htmlspecialchars($edit['content'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="slide-url">Đường dẫn ảnh <span class="text-danger">*</span></label>
                            <input class="form-control" id="slide-url" name="url" required
                                placeholder="https://... hoặc images/banner.jpg"
                                value="<?= htmlspecialchars($edit['url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-text">Dùng liên kết ảnh http/https hoặc đường dẫn tương đối trong website.</div>
                            <?php $editPreview = slide_preview_src($edit['url'] ?? ''); ?>
                            <img id="slide-preview" class="img-fluid rounded mt-3 <?= $editPreview === '' ? 'd-none' : '' ?>"
                                src="<?= htmlspecialchars($editPreview, ENT_QUOTES, 'UTF-8') ?>" alt="Xem trước banner">
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-warning text-white" type="submit">
                                <i class="fas fa-save me-1"></i><?= $edit ? 'Lưu thay đổi' : 'Thêm banner' ?>
                            </button>
                            <?php if ($edit): ?><a href="slides.php" class="btn btn-outline-secondary">Hủy sửa</a><?php endif; ?>
                        </div>
                    </form>
                </div>
            </section>
            <div class="alert alert-light border mt-3 small">
                <i class="fas fa-lightbulb text-warning me-1"></i>
                Banner hiển thị trên trang chủ. Dùng ảnh ngang, khuyến nghị chiều rộng từ 1280 px trở lên.
            </div>
        </div>

        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 fw-bold mb-0">Danh sách banner</h2>
                <span class="badge text-bg-warning"><?= (int) $slides->num_rows ?> banner</span>
            </div>
            <?php if ($slides->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($slide = $slides->fetch_assoc()): $preview = slide_preview_src($slide['url']); ?>
                <div class="col-xl-6">
                    <article class="card slides-card shadow-sm h-100">
                        <?php if ($preview !== ''): ?>
                            <img class="slide-preview" src="<?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                            <div class="slide-empty-preview d-flex align-items-center justify-content-center text-white"><i class="fas fa-image fa-3x opacity-50"></i></div>
                        <?php endif; ?>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <h3 class="h6 fw-bold mb-0"><?= htmlspecialchars($slide['content'] ?: 'Banner chưa có tiêu đề', ENT_QUOTES, 'UTF-8') ?></h3>
                                <span class="badge text-bg-light">#<?= (int) $slide['id'] ?></span>
                            </div>
                            <div class="small text-muted slide-url mb-3"><?= htmlspecialchars($slide['url'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="d-flex gap-2">
                                <a href="slides.php?edit=<?= (int) $slide['id'] ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-edit me-1"></i>Sửa
                                </a>
                                <form method="post" onsubmit="return confirm('Bạn có chắc muốn xóa banner này?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $slide['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="fas fa-trash me-1"></i>Xóa</button>
                                </form>
                            </div>
                        </div>
                    </article>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
                <div class="card slides-card shadow-sm"><div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-images fa-3x mb-3 text-warning"></i>
                    <p class="mb-0">Chưa có banner nào. Hãy dùng biểu mẫu để thêm banner đầu tiên.</p>
                </div></div>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>
const slideUrlInput = document.getElementById('slide-url');
const slidePreview = document.getElementById('slide-preview');
slideUrlInput.addEventListener('input', () => {
    const value = slideUrlInput.value.trim();
    if (/^(https?:\/\/|\/?[a-z0-9_-]+\/)/i.test(value)) {
        slidePreview.src = value.startsWith('/') ? value : '../' + value.replace(/^(\.\.\/)+/, '');
        slidePreview.classList.remove('d-none');
    } else {
        slidePreview.classList.add('d-none');
    }
});
slidePreview.addEventListener('error', () => slidePreview.classList.add('d-none'));
</script>
<?php admin_page_end(); ?>
