// ============================================================
// PRODUCTS.JS — Dữ liệu sản phẩm từ POS (CSV)
// File này được tự động cập nhật khi anh upload CSV từ Admin Panel
// ============================================================

// Dữ liệu mẫu — sẽ được ghi đè khi anh upload CSV từ POS
const SAMPLE_PRODUCTS = [
  { name: 'Cám cá Rồng Arowana Gold', category: 'Thức ăn', size: 'Hộp 100g', qty: 15, sellPrice: 250000 },
  { name: 'Sưởi Gecai chống nổ 100W', category: 'Phụ kiện', size: '', qty: 8, sellPrice: 120000 },
  { name: 'Sưởi Gecai chống nổ 200W', category: 'Phụ kiện', size: '', qty: 5, sellPrice: 160000 },
  { name: 'Seachem Prime', category: 'Thuốc', size: 'Chai 100ml', qty: 20, sellPrice: 85000 },
  { name: 'Seachem Prime', category: 'Thuốc', size: 'Chai 250ml', qty: 12, sellPrice: 180000 },
  { name: 'Compozyme Long Sinh', category: 'Thuốc', size: 'Gói 10g', qty: 30, sellPrice: 25000 },
  { name: 'Extrabio vi sinh nước', category: 'Thuốc', size: 'Chai 100ml', qty: 10, sellPrice: 95000 },
  { name: 'API Stress Coat', category: 'Thuốc', size: 'Chai 120ml', qty: 8, sellPrice: 145000 },
  { name: 'Seachem Purigen', category: 'Vật liệu lọc', size: 'Túi 100ml', qty: 6, sellPrice: 220000 },
  { name: 'Sứ củ sen Mountain Tree', category: 'Vật liệu lọc', size: 'Túi 1L', qty: 18, sellPrice: 85000 },
  { name: 'Bùi nhùi J-Mat', category: 'Vật liệu lọc', size: 'Cuộn 1m', qty: 25, sellPrice: 45000 },
  { name: 'Máy bơm Atman AT-302', category: 'Phụ kiện', size: '', qty: 4, sellPrice: 180000 },
  { name: 'Máy bơm Periha 2500L/h', category: 'Phụ kiện', size: '', qty: 3, sellPrice: 320000 },
  { name: 'Máy lọc váng Sunsun JY-02', category: 'Phụ kiện', size: '', qty: 2, sellPrice: 185000 },
  { name: 'Bộ test pH nước', category: 'Phụ kiện', size: 'Bộ 50 lần test', qty: 9, sellPrice: 75000 },
  { name: 'Bút TDS đo nước', category: 'Phụ kiện', size: '', qty: 7, sellPrice: 95000 },
];

// Chỉ dùng sample nếu chưa có dữ liệu từ CSV
(function() {
  const stored = localStorage.getItem('sgcc_products');
  if (!stored) {
    localStorage.setItem('sgcc_products', JSON.stringify(SAMPLE_PRODUCTS));
  }
})();
