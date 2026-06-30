# EduSystem Pro PHP/MySQL

Đây là bản chuyển từ `taode_hoanchinh.html` sang website PHP có database, dựa trên giao diện trong folder Stitch.

## Chức năng đã có

- Đăng nhập, đăng xuất, phân quyền `admin`, `teacher`, `student`.
- Quản lý tài khoản người dùng.
- Quản lý môn học.
- Quản lý bài học.
- Ngân hàng câu hỏi lưu MySQL.
- Thêm câu hỏi trắc nghiệm, đúng/sai, trả lời ngắn, tự luận.
- Nhập câu hỏi từ file `.txt` hoặc `.docx` dạng văn bản.
- Tự tạo câu hỏi từ tài liệu theo rule-based generator.
- Tạo đề thi từ database, hỗ trợ nhiều mã đề.
- Quản lý đề thi, công bố/đóng/xóa đề.
- Xem đề, xuất đề `.doc`, xuất đáp án `.doc`.
- Học sinh làm bài trực tuyến.
- Lưu bài làm, chấm tự động phần trắc nghiệm/đúng-sai/trả lời ngắn.
- Xem kết quả.
- Sao lưu dữ liệu JSON.

## Cách chạy bằng XAMPP/Laragon

1. Copy folder `stitch_school_exam_management_system` vào thư mục web server, ví dụ:
   - XAMPP: `C:\xampp\htdocs\stitch_school_exam_management_system`
   - Laragon: `C:\laragon\www\stitch_school_exam_management_system`

2. Mở phpMyAdmin và import file `setup.sql`.

3. Sửa cấu hình database nếu cần tại `config/database.php`.

Mặc định:
- Host: `127.0.0.1`
- Database: `edusystem_php`
- User: `root`
- Password: rỗng

4. Truy cập `http://localhost/stitch_school_exam_management_system/`.

5. Tài khoản mặc định:
- Email: `admin@test.com`
- Mật khẩu: `admin123`

## Ghi chú kỹ thuật

- File `.docx` được đọc bằng `ZipArchive`, vì vậy PHP cần bật extension `zip`.
- Xuất Word hiện dùng định dạng `.doc` HTML-compatible để chạy không cần Composer.
- Chức năng tạo câu hỏi từ tài liệu hiện là bản rule-based. Muốn tạo câu hỏi thông minh hơn có thể nối thêm API AI sau.
- Nếu muốn dùng Composer, có thể nâng cấp xuất Word sang `phpoffice/phpword`.
