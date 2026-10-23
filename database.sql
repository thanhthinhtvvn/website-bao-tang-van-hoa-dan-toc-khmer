-- ============================================
-- BẢO TÀNG VĂN HÓA DÂN TỘC KHMER
-- Database: museum_khmer
-- ============================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS visits;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS media;
DROP TABLE IF EXISTS artifacts;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. ADMINS
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CATEGORIES
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ARTIFACTS
CREATE TABLE artifacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    origin VARCHAR(200),
    period VARCHAR(100),
    image VARCHAR(255),
    category_id INT,
    admin_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_artifact_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_artifact_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ARTIFACT_IMAGES (nhiều ảnh cho mỗi hiện vật)
CREATE TABLE artifact_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    artifact_id INT NOT NULL,
    image VARCHAR(255) NOT NULL,
    sort_order TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_artimg_artifact FOREIGN KEY (artifact_id) REFERENCES artifacts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. EVENTS
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    image VARCHAR(255),
    event_date DATE,
    admin_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. USERS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. VISITS
CREATE TABLE visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    visit_date DATE NOT NULL,
    num_people INT NOT NULL DEFAULT 1,
    status ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_visit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. MEDIA (lưu link ảnh dùng chung cho các bảng)
CREATE TABLE media (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    `table` VARCHAR(100) NOT NULL COMMENT 'Tên bảng sử dụng ảnh, vd: hero_slides, events',
    content VARCHAR(255)          COMMENT 'Mô tả / tên ảnh',
    url     TEXT         NOT NULL COMMENT 'Đường dẫn ảnh (URL hoặc đường dẫn file)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. REVIEWS
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
--9. Media

CREATE TABLE IF NOT EXISTS media (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    `table` VARCHAR(100) NOT NULL COMMENT 'Tên bảng sử dụng ảnh, vd: hero_slides, events',
    content VARCHAR(255)          COMMENT 'Mô tả / tên ảnh',
    url     TEXT         NOT NULL COMMENT 'Đường dẫn ảnh (URL hoặc đường dẫn file)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu mẫu 3 slide trang chủ
INSERT INTO media (`table`, content, url) VALUES
('hero_slides', 'Bảo tàng Văn hóa Dân tộc Khmer tỉnh Vĩnh Long', 'images/slide1.jpg'),
('hero_slides', 'Văn hóa là nền tảng tinh thần của xã hội, là mục tiêu, động lực và nguồn lực nội sinh quan trọng cho phát triển bền vững đất nước','images/slide2.jpg'),
('hero_slides','Bảo tàng không phải là nơi giấu đi quá khứ, mà là nơi thắp sáng ngọn lửa truyền thống cho mai sau','images/slide3.jpg');

-- ============================================
-- DỮ LIỆU MẪU
-- ============================================

-- Admin mặc định: username=admin | password=admin123
INSERT INTO admins (username, password, full_name, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Quản trị viên', 'admin@museum.com');

INSERT INTO categories (name) VALUES
('Trang phục truyền thống'),
('Nhạc cụ dân tộc'),
('Đồ gốm sứ'),
('Vũ khí cổ'),
('Đồ dùng sinh hoạt');

INSERT INTO artifacts (name, description, origin, period, image, category_id, admin_id) VALUES
('Áo truyền thống Khmer', 'Áo lụa thêu hoa văn đặc trưng của người Khmer, được dệt thủ công từ tơ lụa tự nhiên với các họa tiết hoa văn tinh tế mang đậm bản sắc văn hóa Khmer Nam Bộ.', 'Sóc Trăng', 'Thế kỷ 19', 'ao_khmer.jpg', 1, 1),
('Váy Sampot truyền thống', 'Sampot là trang phục truyền thống của phụ nữ Khmer, được dệt từ lụa với hoa văn kẻ sọc đặc trưng, thường mặc trong các dịp lễ hội và nghi lễ tôn giáo.', 'Trà Vinh', 'Thế kỷ 19', 'sampot.jpg', 1, 1),
('Khăn Krama', 'Khăn Krama là biểu tượng văn hóa của người Khmer, được dệt theo kiểu ô vuông đặc trưng, dùng đa năng trong đời sống hàng ngày từ che nắng đến địu trẻ em.', 'Kiên Giang', 'Thế kỷ 18', 'krama.jpg', 1, 1),
('Mũ lễ hội Khmer', 'Mũ truyền thống được đội trong các buổi biểu diễn nghệ thuật Robam, được trang trí bằng vàng và đá quý giả, phản ánh ảnh hưởng của nghệ thuật Angkor.', 'An Giang', 'Thế kỷ 20', 'mu_lehoi.jpg', 1, 1),
('Đàn Chapei Dang Veng', 'Nhạc cụ dây truyền thống dài cổ của người Khmer, có 2 dây, thường được các nghệ nhân vừa đàn vừa hát kể chuyện dân gian và triết lý nhân sinh.', 'Trà Vinh', 'Thế kỷ 18', 'chapei.jpg', 2, 1),
('Trống Skor Thom', 'Trống lớn dùng trong các nghi lễ Phật giáo và lễ hội, làm từ gỗ quý và da trâu thuộc, âm thanh vang vọng có thể nghe từ rất xa.', 'Sóc Trăng', 'Thế kỷ 17', 'skor_thom.jpg', 2, 1),
('Kèn Sralai', 'Kèn truyền thống của người Khmer, làm từ tre và gỗ, âm sắc trong trẻo đặc trưng, thường dùng trong dàn nhạc Pinpeat biểu diễn trong các lễ hội và nghi lễ.', 'Vĩnh Long', 'Thế kỷ 18', 'sralai.jpg', 2, 1),
('Đàn Tro Khmer', 'Đàn nhị truyền thống của người Khmer với hộp cộng hưởng bọc da, tạo ra âm thanh trầm ấm đặc trưng, thường xuất hiện trong các buổi biểu diễn nghệ thuật dân gian.', 'Bạc Liêu', 'Thế kỷ 19', 'tro_khmer.jpg', 2, 1),
('Bình gốm hoa văn rồng', 'Bình gốm cao cấp trang trí hình rồng nước Naga - biểu tượng linh thiêng trong văn hóa Khmer, được chế tác bằng kỹ thuật thủ công truyền thống với men màu đặc trưng.', 'An Giang', 'Thế kỷ 17', 'gom_rong.jpg', 3, 1),
('Chum đựng nước nghi lễ', 'Chum gốm dùng trong các nghi lễ Phật giáo và lễ hội Ok Om Bok, được trang trí hoa văn hình học truyền thống, thể hiện tín ngưỡng tâm linh của người Khmer.', 'Kiên Giang', 'Thế kỷ 16', 'chum_nghi_le.jpg', 3, 1),
('Tượng gốm Phật Thích Ca', 'Tượng Phật nhỏ bằng gốm nung được đặt trong các gia đình Khmer để thờ phụng, thể hiện ảnh hưởng sâu sắc của Phật giáo Theravada trong đời sống tinh thần.', 'Trà Vinh', 'Thế kỷ 18', 'tuong_phat.jpg', 3, 1),
('Kiếm lễ hội Preah Khan', 'Kiếm nghi lễ được sử dụng trong các buổi tế thần và lễ hội truyền thống, chuôi kiếm chạm khắc hình rắn thần Naga, lưỡi kiếm bằng đồng được đánh bóng.', 'Sóc Trăng', 'Thế kỷ 17', 'preah_khan.jpg', 4, 1),
('Giáo lễ hội', 'Giáo bằng tre và đồng dùng trong các điệu múa chiến trận truyền thống, được trang trí với tua rua màu sắc rực rỡ, là đạo cụ quan trọng trong nghệ thuật biểu diễn Khmer.', 'An Giang', 'Thế kỷ 18', 'giao_lehoi.jpg', 4, 1),
('Nồi đồng nấu lễ', 'Nồi đồng lớn dùng để nấu thức ăn trong các lễ hội cộng đồng và nghi lễ Phật giáo, được đúc thủ công với hoa văn trang trí xung quanh thân nồi.', 'Vĩnh Long', 'Thế kỷ 19', 'noi_dong.jpg', 5, 1),
('Thúng đan tre truyền thống', 'Thúng đan từ tre nứa theo kỹ thuật truyền thống của người Khmer, dùng để đựng gạo và hoa quả dâng lên Phật trong các lễ hội, thể hiện sự khéo léo của người thợ thủ công.', 'Bạc Liêu', 'Thế kỷ 19', 'thung_tre.jpg', 5, 1),
('Đèn dầu nghi lễ', 'Đèn dầu bằng đồng được thắp trong các buổi cầu nguyện tại chùa Khmer, thiết kế hình hoa sen kết hợp hình rắn Naga, biểu tượng của sự giác ngộ trong Phật giáo.', 'Trà Vinh', 'Thế kỷ 18', 'den_dau.jpg', 5, 1);


INSERT INTO media (`table`, content, url) VALUES
('hero_slides', 'Slide 1 - Bảo tàng Văn hóa Dân tộc Khmer', 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3e/Angkor_Wat_temple.jpg/1280px-Angkor_Wat_temple.jpg'),
('hero_slides', 'Slide 2 - Hiện Vật Quý Giá',                'https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/Bayon_Angkor_Thom.jpg/1280px-Bayon_Angkor_Thom.jpg'),
('hero_slides', 'Slide 3 - Sự Kiện & Lễ Hội',               'https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/Camponotus_flavomarginatus_ant.jpg/1280px-Camponotus_flavomarginatus_ant.jpg');

INSERT INTO events (title, description, image, event_date, admin_id) VALUES
('Lễ hội Ok Om Bok 2024', 'Lễ hội truyền thống cúng trăng của người Khmer Nam Bộ', 'okombok.jpg', '2024-11-15', 1),
('Triển lãm hiện vật Khmer cổ', 'Trưng bày hơn 100 hiện vật quý hiếm của dân tộc Khmer', 'trienlam.jpg', '2024-12-01', 1),
('Ngày hội văn hóa Khmer', 'Giao lưu văn hóa, âm nhạc và ẩm thực Khmer', 'ngayhoi.jpg', '2025-01-20', 1);

COMMIT;
