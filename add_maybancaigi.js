const fs = require('fs');
const qaJsonFile = 'E:\\Backup_Ghi_Nho_Antigravity\\01_Du_An_Code\\sgcc-chatbot\\qa_pairs.json';

let qaPairs = JSON.parse(fs.readFileSync(qaJsonFile, 'utf-8'));

const newQA = {
  question: "mày bán cái gì",
  answer: "Dạ tiệm Sài Gòn Cá Cảnh (246 Hồ Văn Huê, Phú Nhuận) chuyên cung cấp trọn gói vật tư cá cảnh bao gồm:\n- Phụ kiện & Thiết bị: Máy bơm chìm (Atman, Periha), Sưởi Gecai chống nổ, Lọc váng, Đèn Tanning.\n- Thuốc & Vi sinh: Seachem Prime, Extrabio, Compozyme, Bio-Knock 1,2,3,4.\n- Vật liệu lọc & Thức ăn: Bùi nhùi J-Mat, Sứ lọc củ sen, Purigen, Cám Arowana, Cám Guppy, Betta.\n- Cá cảnh sống & Tép màu: Guppy, Betta, Cá Vàng, La Hán, Cá Rồng, Koi...\nAnh/chị mời xem danh mục đầy đủ trên [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com) ạ!"
};

const idx = qaPairs.findIndex(q => q.question.toLowerCase() === newQA.question.toLowerCase());
if (idx !== -1) qaPairs.splice(idx, 1);
qaPairs.unshift(newQA);

fs.writeFileSync(qaJsonFile, JSON.stringify(qaPairs, null, 2), 'utf-8');
console.log('Added may ban cai gi QA successfully!');
