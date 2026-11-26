# Everland Group — Hệ thống Đăng ký Nghỉ phép & Overtime (OT)

Ứng dụng nội bộ cho nhân sự Everland Group: đăng ký nghỉ phép, đăng ký làm thêm giờ, duyệt theo mô hình thác nước nhiều cấp, quản lý quỹ phép, lịch công tác, thông báo, báo cáo và các module quản trị HR.

- **Stack:** PHP 8.2 (Laravel 12) + MySQL/MariaDB (XAMPP) + Blade + CSS thuần (không cần npm/Node).
- **Ngôn ngữ giao diện:** Tiếng Việt.
- **Múi giờ:** Asia/Ho_Chi_Minh.

---

## 1. Yêu cầu môi trường

| Thành phần | Phiên bản |
|---|---|
| XAMPP | PHP 8.2+ (bật `pdo_mysql`, `mbstring`, `fileinfo`) |
| MySQL/MariaDB | 10.4+ (chạy kèm XAMPP) |
| Composer | 2.x |
| Trình duyệt | Chrome/Edge bản mới |

Không cần cài Node.js — toàn bộ CSS/JS nằm trong `public/css` và `public/js`.

---

## 2. Cài đặt nhanh

```bash
# 1. Cài thư viện PHP
composer install

# 2. Tạo file cấu hình (nếu chưa có)
copy .env.example .env

# 3. Tạo khoá ứng dụng
php artisan key:generate

# 4. Bật MySQL trong XAMPP, tạo database trống
#    Tên: leave_ot_system  (utf8mb4_unicode_ci)

# 5. Tạo bảng + nạp dữ liệu mẫu (22 nhân sự, đơn mẫu, quỹ phép, ngày lễ...)
php artisan migrate --seed

# 6. Chạy server
php artisan serve --host=127.0.0.1 --port=8000
```

Mở **http://127.0.0.1:8000** → màn hình đăng nhập dạng tách đôi có nút đăng nhập nhanh cho tài khoản demo.

> Nếu chạy qua XAMPP Apache: trỏ document root vào thư mục `public/` hoặc dùng `php artisan serve`.

Cấu hình DB mặc định trong `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=leave_ot_system
DB_USERNAME=root
DB_PASSWORD=
```

Ảnh đại diện tải lên được lưu tại `public/uploads/avatars` (không cần `storage:link`).

### Chạy bằng Apache của XAMPP (thư mục htdocs)

1. Copy dự án vào `C:\xampp\htdocs\LeaveOTSystem` (đã có sẵn ở máy này).
2. Trong `.env` của bản trong htdocs đặt:
   ```env
   APP_URL=http://localhost/LeaveOTSystem/public
   ```
3. File `.htaccess` ở thư mục gốc dự án chuyển hướng mọi URL vào `public/`:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteBase /LeaveOTSystem/
       RewriteRule ^(?!public/)(.*)$ public/$1 [R=302,L]
   </IfModule>
   ```
4. Mở **XAMPP Control Panel** → Start **Apache** và **MySQL**.
5. Truy cập **http://localhost/LeaveOTSystem/** (tự chuyển tới `/public/`).

Lưu ý: đây là bản sao độc lập. Khi sửa mã nguồn, cần copy lại thay đổi sang `htdocs` hoặc làm việc trực tiếp trong thư mục htdocs đó.

---

## 3. Tài khoản demo

Mật khẩu chung cho **tất cả** tài khoản demo: `Everland@123`

| Vai trò | Email | Nhân sự | Phạm vi |
|---|---|---|---|
| Admin | `admin@everland.vn` | Nguyễn Hoàng Anh | Toàn quyền hệ thống, mọi module quản trị |
| Tổng Giám đốc | `director@everland.vn` | Trần Minh Quân | Duyệt cấp cao nhất, xem báo cáo toàn công ty |
| HR | `hr@everland.vn` | Phạm Thu Hà | Quản lý nhân sự, quỹ phép, bước duyệt HR cuối của đơn nghỉ |
| Giám đốc Khối KD | `manager@everland.vn` | Lê Văn Hùng | Duyệt chuỗi Khối Kinh doanh, xem team & báo cáo |
| Giám đốc Khối DA | `manager2@everland.vn` | Đỗ Thị Mai | Duyệt chuỗi Khối Dự án / Thiết kế |
| Trưởng phòng KT | `ketoan@everland.vn` | Võ Quốc Bảo | Duyệt chuỗi Kế toán |
| Trưởng nhóm KD1 | `leader@everland.vn` | Nguyễn Thị Lan | Duyệt cấp 1 cho nhóm Kinh doanh 1 |
| Trưởng nhóm KD2 | `leader2@everland.vn` | Bùi Anh Tuấn | Duyệt cấp 1 cho nhóm Kinh doanh 2 |
| Trưởng nhóm TK | `leader3@everland.vn` | Hoàng Ngọc Diệp | Duyệt cấp 1 cho nhóm Thiết kế |
| Nhân viên | `employee@everland.vn` | Trần Văn Nam | Tạo đơn nghỉ/OT, xem quỹ phép, lịch, thông báo |

Các tài khoản nhân viên khác: `huong.ly@`, `thang.phan@`, `khoi.dang@`, `trang.nguyen@`, `cuong.le@`, `linh.trinh@`, `phuc.mai@`, `yen.nguyen@`, `long.vu@`, `tam.bui@`, `hong.ngo@`, `tuan.doan@` + `everland.vn`.

---

## 4. Luồng duyệt thác nước (Waterfall Approval)

Chuỗi duyệt được dựng tự động từ **Quản lý trực tiếp** (`manager_id`) của người gửi đơn:

```
Nhân viên → Trưởng nhóm → Trưởng phòng/Trưởng khối → Giám đốc → (HR chỉ với đơn nghỉ phép)
```

- **Đơn nghỉ phép:** duyệt qua chuỗi quản lý + thêm bước cuối **HR**; HR luôn là bước chốt cuối kể cả khi HR nằm trong chuỗi quản lý trực tiếp.
- **Đơn OT:** chỉ duyệt qua chuỗi quản lý.
- **Lịch làm việc:** Thứ 2 – Thứ 6 (8:00–17:30) tính 1 ngày công; Thứ 7 làm buổi sáng (8:00–12:00) tính 0,5 ngày; Chủ nhật và ngày lễ nghỉ.
- Từ chối ở bất kỳ bước nào → dừng toàn bộ chuỗi, người gửi nhận thông báo kèm lý do.
- Duyệt đến bước cuối → tự động **trừ quỹ phép** (với loại nghỉ có cấu hình trừ quỹ).
- Mọi thao tác tạo/duyệt/từ chối/huỷ đều ghi **Audit log** và gửi **thông báo trong ứng dụng**.
- **Email theo từng bước (nghỉ phép và OT):** khi gửi đơn, quản lý trực tiếp và người gửi nhận mail; mỗi lần duyệt, người duyệt kế tiếp và người gửi nhận mail ghi rõ ai đã duyệt và ai đang chờ duyệt. Khi hoàn tất hoặc bị từ chối, người gửi nhận mail kết quả.
- **Quản lý cấp cao nhất** (bước quản lý cuối trước HR) có nút **Duyệt toàn bộ các cấp**: duyệt hết các bước quản lý còn lại nhưng bước **HR vẫn bắt buộc chờ duyệt** và HR nhận mail khi đến lượt.
- **Quản trị viên (admin)**: thấy toàn bộ đơn đang chờ duyệt trong công ty, có thể phê duyệt/từ chối thay ở bước hiện tại của bất kỳ đơn nào, hoặc bấm **Duyệt toàn bộ chuỗi** để duyệt hết các bước còn lại kể cả HR. Người thực hiện duyệt thay được lưu ở cột `acted_by` và hiển thị trên timeline.

Bước hiện tại được xác định bằng `current_step` của đơn so với `step` của bản ghi `approvals`; chỉ người duyệt đúng bước mới thấy nút Phê duyệt/Từ chối.

---

## 5. Vai trò & phân quyền

Ma trận quyền được định nghĩa tại `config/permissions.php` và Gate trong `AppServiceProvider`, quản lý trực tiếp trên giao diện **Admin → Vai trò & ma trận quyền**.

| Vai trò | Cấp | Quyền chính |
|---|---|---|
| Admin | 100 | Toàn bộ (bypass mọi kiểm tra quyền); duyệt thay mọi bước, duyệt toàn bộ chuỗi |
| Giám đốc (director) | 80 | Duyệt, xem team, báo cáo, xuất báo cáo |
| HR | 60 | Quản lý nhân sự, phòng ban, loại phép, ngày lễ, quỹ phép, báo cáo, audit |
| Trưởng phòng/Khối (manager) | 50 | Duyệt đơn, xem team, báo cáo |
| Trưởng nhóm (leader) | 40 | Duyệt đơn, xem team |
| Nhân viên | 10 | Tạo/huỷ đơn của mình, xem lịch, quỹ phép, thông báo, hồ sơ cá nhân |

Quyền được kiểm tra 3 lớp: middleware `can:` trên route, `@can` trong Blade (ẩn nút), và check trong controller.

---

## 6. Chức năng chính

**Nhân viên**
- Dashboard tổng quan: quỹ phép, đơn gần đây, đơn chờ duyệt, thông báo.
- Đăng ký nghỉ phép: chọn loại nghỉ, khoảng ngày, nửa ngày, lý do, đính kèm; tính số ngày công tự động theo lịch làm việc + ngày lễ.
- Đăng ký OT: chọn ngày, khung giờ, loại OT (ngày thường/cuối tuần/ngày lễ), tính số giờ và hệ số.
- Theo dõi trạng thái đơn, timeline duyệt, huỷ đơn khi còn chờ.
- Lịch tháng: nghỉ phép đã duyệt + OT trên lưới lịch; quỹ phép theo năm; thông báo; hồ sơ cá nhân (đổi ảnh, mật khẩu).

**Quản lý / Trưởng nhóm**
- Hộp duyệt: danh sách đơn chờ, xem chi tiết chuỗi duyệt, phê duyệt hoặc từ chối kèm lý do.
- Trang Team: nhân sự trực thuộc, đơn chờ, giờ OT trong tháng.

**HR / Admin**
- Nhân sự (tạo/sửa/khoá tài khoản, reset mật khẩu, gán vai trò–phòng ban–quản lý).
- Phòng ban dạng cây (4 cấp: Tập đoàn → Khối/Ban → Phòng → Nhóm).
- Danh mục loại nghỉ phép (màu, ngày mặc định, trừ quỹ, bắt buộc tệp, có lương).
- Ngày lễ theo năm.
- Quỹ phép: tổng hợp toàn công ty, khởi tạo theo năm, chuyển năm, **cộng/trừ nhanh ngày phép cho bất kỳ nhân sự** (có lý do, ghi audit log và gửi thông báo), điều chỉnh thủ công từng chỉ số.
- Ma trận phân quyền theo vai trò.
- Cấu hình hệ thống (thông tin công ty, chính sách nghỉ, hệ số OT).
- Audit log: lọc theo từ khoá, phân hệ, khoảng ngày.

**Báo cáo**
- Tab Nghỉ phép / OT, lọc theo năm–tháng–phòng ban–trạng thái–loại nghỉ.
- Biểu đồ theo tháng, phân bổ theo phòng ban, theo loại nghỉ; xuất CSV (quyền `report.export`).

---

## 7. Cấu trúc chính

```
app/
├─ Http/Controllers/            # Auth, Dashboard, Leave, Overtime, Approval, Calendar,
│                               # Team, Balance, Notification, Profile, Report, Admin/*
├─ Models/                      # User, Role, Permission, Department, LeaveType, LeaveRequest,
│                               # LeaveBalance, OvertimeRequest, Approval, Holiday, Setting,
│                               # AppNotification, AuditLog
├─ Services/                    # ApprovalService (buildChain, approve/reject), AuditLogger
config/permissions.php          # Định nghĩa quyền + ma trận mặc định theo vai trò
database/migrations|seeders/    # Schema + dữ liệu demo (22 nhân sự, 13 đơn vị, đơn mẫu)
resources/views/                # layouts, partials (icons), auth, dashboard, leave, overtime,
│                               # approvals, calendar, team, balances, notifications,
│                               # profile, reports, admin/*
public/css/app.css              # Design system (Everland navy/blue)
public/js/app.js                # Sidebar, modal, dropdown, confirm, tính ngày/giờ, sort, filter
routes/web.php                  # Toàn bộ route có middleware auth + can:
```

---

## 8. Lệnh hữu ích

```bash
php artisan migrate:fresh --seed   # Reset sạch DB và nạp lại dữ liệu demo
php artisan test                   # Chạy test (nếu có)
php artisan route:list             # Xem toàn bộ route + middleware quyền
php artisan optimize:clear         # Xoá cache config/route/view
```

---

## 9. Lưu ý khi triển khai

- Đổi `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false` trong `.env`.
- Đặt mật khẩu MySQL thay vì `root` trống; đổi mật khẩu tài khoản admin demo.
- Ứng dụng ghi file vào `storage/logs` và `public/uploads/avatars` → cần quyền ghi.
- Không commit file `.env` lên kho mã nguồn.
