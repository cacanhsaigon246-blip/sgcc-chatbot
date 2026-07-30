<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$pass = isset($input['pass']) ? $input['pass'] : '';

if ($pass !== 'sgcacanh2024') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Mật khẩu quản trị không đúng']);
    exit;
}

$db_host = '127.0.0.1';
$db_name = 'u972437838_pos'; 
$db_user = 'u972437838_pos_user';
$db_pass = 'Cannabis041188';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Lấy toàn bộ sản phẩm từ bảng inventory của POS
    $stmt = $pdo->query("SELECT * FROM `inventory` ORDER BY `name` ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $products = [];
    foreach ($rows as $row) {
        $products[] = [
            'name'        => trim($row['name'] ?? ''),
            'category'    => trim($row['category'] ?? ''),
            'size'        => trim($row['size'] ?? ''),
            'qty'         => intval($row['quantity'] ?? 0),
            'sellPrice'   => floatval($row['sell_price'] ?? 0),
            'importPrice' => floatval($row['import_price'] ?? 0),
            'barcode'     => trim($row['barcode'] ?? ($row['id'] ?? ''))
        ];
    }
    
    // Lưu thẳng vào pos_products.json trên server
    $file = __DIR__ . '/pos_products.json';
    file_put_contents($file, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'status' => 'success',
        'count'  => count($products),
        'message' => 'Đã tự động đồng bộ trực tiếp ' . count($products) . ' sản phẩm từ Database POS sang Chatbot!'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Lỗi kết nối CSDL POS: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
