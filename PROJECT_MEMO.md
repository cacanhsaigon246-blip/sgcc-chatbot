# 📘 SỔ TAY DỰ ÁN & QUY TẮC BẢO VỆ DỰ ÁN SÀI GÒN CÁ CẢNH (SGCC)

*Cập nhật gần nhất: 31/07/2026*  
*Chủ sở hữu: Sài Gòn Cá Cảnh (Anh Phát)*

---

## 🛡️ 1. QUY TẮC AN TOÀN BẢO VỆ WEBSITE CHÍNH (`saigoncacanh.com`)

1. **Nguyên tắc Độc lập (Isolation)**:
   - Chatbot (`sgcc-chatbot`) chạy hoàn toàn độc lập với hệ thống cốt lõi của WordPress WooCommerce.
   - Mọi kết nối từ Website chính đến Chatbot chỉ qua 1 file duy nhất: `widget.js`.
2. **Quy trình Đẩy Code An toàn (Safe-Deploy Protocol)**:
   - Tất cả mã nguồn can thiệp đều được sao lưu & tạo Commit lịch sử trên **GitHub** trước khi đẩy lên server thật (Hostinger).
   - Nếu xảy ra sự cố ngoài ý muốn trên website, thực hiện khôi phục (Rollback) 1-Click trên GitHub ngay lập tức.
3. **Cơ chế Dự phòng Tránh Gián đoạn (Fallback Guarantee)**:
   - Mọi dịch vụ bên thứ 3 (Google Auth, API Gemini, Zalo) phải được bọc trong khối `try...catch`. Dù dịch vụ ngoài bị lỗi hay hết quota, trải nghiệm mua sắm trên website chính vẫn hoạt động mượt mà 100%.

---

## 🗂️ 2. DANH SÁCH KHO LƯU TRỮ GITHUB (REPOSITORIES)

| Tên Repo GitHub | Chức năng & Mục đích | Trạng thái Bảo vệ |
| :--- | :--- | :--- |
| **`cacanhsaigon246-blip/sgcc-chatbot`** | Mã nguồn toàn bộ hệ thống SGCC Chatbot, Proxy PHP, Admin Dashboard, Widget UI | ✅ Đã đồng bộ 100% |
| **`cacanhsaigon246-blip/saigoncacanh-woocommerce`** | Kho sao lưu Mã nguồn WooCommerce, Theme tùy biến & Plugin can thiệp của `saigoncacanh.com` | 🚀 Đang khởi tạo |
| **`cacanhsaigon246-blip/POS_Hostinger_Chinh_Thuc`** | Mã nguồn phần mềm POS quản lý kho & bán hàng Hostinger | ✅ Đã lưu an toàn |

---

## ⚙️ 3. TẬP TỆP VÀ BẢNG MÃ NGUỒN QUAN TRỌNG CHATBOT

- **Frontend Widget & UI**:
  - `widget.js`: Nút bong bóng chat nhúng vào WordPress.
  - `chatbot.js`: Xử lý logic hội thoại, đếm tin nhắn khách, gọi backend API, Google Sign-in.
  - `style.css`: Bộ giao diện đại dương biển xanh.
- **Admin Dashboard**:
  - `admin.html`: Quản lý API Key Gemini, Google Client ID, xem nhật ký chat, xem thống kê & đồng bộ POS.
- **Backend PHP**:
  - `proxy.php`: Trạm trung chuyển gọi AI Gemini bảo mật.
  - `save_key.php` / `check_key.php`: Quản lý Gemini API Key.
  - `save_google_config.php` / `get_google_config.php`: Quản lý Google Client ID.
  - `sync_pos_db.php` / `sync_woocommerce.php`: Lấy dữ liệu sản phẩm mới nhất từ POS & WooCommerce.

---

## 📝 4. NHẬT KÝ TIẾN ĐỘ & CÔNG VIỆC CẦN LÀM (TODOLIST)

### 🟢 Đã hoàn thành:
- [x] Sửa lỗi câu phản hồi AI không đẩy vào khung chat UI (`commit be4d8bf`).
- [x] Thêm tự động bới đệm (cache-busting) `Date.now()` cho `widget.js` trong mã nhúng Admin.
- [x] Tích hợp tính năng nhận diện thành viên WordPress và tự động xóa giới hạn tin nhắn khách vãng lai.
- [x] Xử lý lọc bỏ dấu tiếng Việt (Vietnamese Accent Stripping) khi tìm sản phẩm & gợi ý link chính xác.
- [x] 1-Click đồng bộ sản phẩm từ MySQL Hostinger POS Database.

### 🟡 Đang thực hiện:
- [/] Khởi tạo kho sao lưu GitHub `saigoncacanh-woocommerce` bảo vệ website chính.
- [/] Nâng cấp trang Admin để cấu hình linh hoạt `Google Client ID` tự động nạp sang `chatbot.js`.
- [/] Thiết lập Sổ tay dự án ghi nhớ (`PROJECT_MEMO.md`).
