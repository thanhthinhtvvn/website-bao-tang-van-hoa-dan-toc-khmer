# Bảo tàng Văn hóa Dân tộc Khmer

## Cài đặt

1. Đặt thư mục dự án trong document root của máy chủ PHP.
2. Tạo cơ sở dữ liệu `museum_khmer` và import `database.sql`.
3. Cấu hình kết nối MySQL trong `config/db.php`.
4. Đặt các biến môi trường sau trên máy chủ để gửi email đăng ký:
   - `MUSEUM_MAIL_USERNAME`: địa chỉ Gmail dùng để gửi email.
   - `MUSEUM_MAIL_PASSWORD`: mật khẩu ứng dụng Gmail.
   - `MUSEUM_MAIL_ADMIN`: địa chỉ nhận thông báo đăng ký.

Không lưu mật khẩu email trong mã nguồn. Nếu thiếu cấu hình SMTP, chức năng gửi email sẽ báo lỗi khi có đăng ký mới.
