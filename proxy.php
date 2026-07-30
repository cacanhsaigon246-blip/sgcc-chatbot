<?php
/**
 * proxy.php — Gemini API Proxy & Fallback Engine
 * Sài Gòn Cá Cảnh Chatbot
 *
 * File này ẩn API Key, nhận Key từ Header/Payload nếu có,
 * hoặc dùng Key mặc định trên Server.
 */

// ── CORS HEADERS ──────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Gemini-Key');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight & health check
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' || isset($_GET['health'])) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'PHP Proxy Server Online', 'time' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── RATE LIMITING ─────────────────────────────────────────────
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$ip = explode(',', $ip)[0];
$rate_file = sys_get_temp_dir() . '/sgcc_rate_' . md5($ip) . '.json';

$rate_data = ['count' => 0, 'reset_at' => time() + 3600];
if (file_exists($rate_file)) {
    $stored = json_decode(file_get_contents($rate_file), true);
    if ($stored && $stored['reset_at'] > time()) {
        $rate_data = $stored;
    }
}

if ($rate_data['count'] >= 300) { // 300 req/hour/IP
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

$rate_data['count']++;
file_put_contents($rate_file, json_encode($rate_data));

// ── PARSE REQUEST ─────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

// Nhận Key từ Header hoặc Body
$api_key = $_SERVER['HTTP_X_GEMINI_KEY'] ?? ($data['apiKey'] ?? '');
if (empty($api_key) || strpos($api_key, 'AQ.') === 0) {
    // Nếu chưa có key hợp lệ, kiểm tra file local key
    $key_file = __DIR__ . '/gemini_key.txt';
    if (file_exists($key_file)) {
        $api_key = trim(file_get_contents($key_file));
    }
}

$model = $data['model'] ?? 'gemini-2.0-flash';

// Bỏ apiKey khỏi payload gửi cho Gemini
if (isset($data['apiKey'])) unset($data['apiKey']);

if (empty($api_key)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Chưa cấu hình Gemini API Key chính thức! Anh Phát hãy nhập Key tại https://chatbot.saigoncacanh.com/admin.html mục API Key nhé ạ!'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── CHUẨN BỊ CHỈ THỊ HỆ THỐNG (SYSTEM INSTRUCTION) ──────────────────
$qa_rules = "";
$qa_file = __DIR__ . '/qa_pairs';
// Đọc quy tắc Q&A từ admin
$qa_file = __DIR__ . '/qa_pairs.json';
if (file_exists($qa_file)) {
    $qa_data = json_decode(file_get_contents($qa_file), true);
    if (is_array($qa_data) && !empty($qa_data)) {
        $qa_rules = "\n[QUY TẮC PHẢN HỒI BẮT BUỘC TỪ CHỦ TIỆM (ANH PHÁT)]:\n";
        foreach ($qa_data as $qa) {
            if (!empty($qa['question']) && !empty($qa['answer'])) {
                $qa_rules .= "- Khách hỏi: \"" . $qa['question'] . "\" -> Bạn trả lời đúng như sau: \"" . $qa['answer'] . "\"\n";
            }
        }
    }
}

// Định vị địa lý của khách hàng (giới hạn thời gian kết nối 1.5 giây để tránh bị treo script)
$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
$client_ip = explode(',', $client_ip)[0];
$city = 'Không rõ';
if ($client_ip && $client_ip !== '127.0.0.1' && $client_ip !== '::1') {
    $ctx = stream_context_create(['http' => ['timeout' => 1.5]]);
    $geo_res = @file_get_contents("http://ip-api.com/json/" . trim($client_ip) . "?fields=status,city", false, $ctx);
    if ($geo_res) {
        $geo_data = json_decode($geo_res, true);
        if (isset($geo_data['status']) && $geo_data['status'] === 'success') {
            $city = $geo_data['city'] ?? 'Không rõ';
        }
    }
}

$location_instruction = "";
if ($city === 'Không rõ') {
    $location_instruction = "- Vị trí khách hàng: Chưa rõ. Hãy tìm thời cơ khéo léo hỏi xem khách ở quận nào của TP.HCM hoặc ở tỉnh nào để tiện báo giá ship chính xác.";
} else if (stripos($city, 'Minh') !== false || stripos($city, 'HCM') !== false) {
    $location_instruction = "- Vị trí khách hàng: TP.HCM. Hãy nhiệt tình mời họ ghé thăm trực tiếp tiệm tại địa chỉ: 246 Hồ Văn Huê, Phường 9, Phú Nhuận để lựa chọn cá trực tiếp.";
} else {
    $location_instruction = "- Vị trí khách hàng: Tỉnh ngoài TP.HCM (" . $city . "). Hướng dẫn khách mua Online qua Shopee: https://shop.saigoncacanh.com hoặc Zalo.";
}

// Trích xuất câu hỏi thô để làm đối khớp từ khóa
$question = '';
if (isset($data['contents'][0]['parts'][0]['text'])) {
    $prompt_text = $data['contents'][0]['parts'][0]['text'];
    $parts_lines = explode("\n", $prompt_text);
    foreach (array_reverse($parts_lines) as $line) {
        if (stripos($line, 'Câu hỏi của khách:') !== false) {
            $question = trim(str_ireplace('Câu hỏi của khách:', '', $line));
            break;
        }
    }
    if (empty($question)) {
        $question = $prompt_text;
    }
}

// Hàm loại bỏ dấu tiếng Việt để đối khớp không dấu
function stripAccents($str) {
    $unicode = array(
        'a'=>'á|à|ả|ã|ạ|ă|ắ|ằ|ẳ|ẵ|ặ|â|ấ|ầ|ẩ|ẫ|ậ|A|Á|À|Ả|Ã|Ạ|Ă|Ắ|Ằ|Ẳ|Ẵ|Ặ|Â|Ấ|Ầ|Ẩ|Ẫ|Ậ',
        'd'=>'đ|D|Đ',
        'e'=>'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ|E|É|È|Ẻ|Ẽ|Ẹ|Ê|Ế|Ề|Ể|Ễ|Ệ',
        'i'=>'í|ì|ỉ|ĩ|ị|I|Í|Ì|Ỉ|Ĩ|Ị',
        'o'=>'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ẫ|ộ|ơ|ớ|ờ|ở|ỡ|ợ|O|Ó|Ò|Ỏ|Õ|Ọ|Ô|Ố|Ồ|Ổ|Ẫ|Ộ|Ơ|Ớ|Ờ|Ở|Ỡ|Ợ',
        'u'=>'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự|U|Ú|Ù|Ủ|Ũ|Ụ|Ư|Ứ|Ừ|Ử|Ữ|Ự',
        'y'=>'ý|ỳ|ỷ|ỹ|ỵ|Y|Ý|Ì|Ỷ|Ỹ|Ỵ',
    );
    foreach($unicode as $nonUnicode=>$uni) {
        $str = preg_replace("/($uni)/i", $nonUnicode, $str);
    }
    return $str;
}

$matching_context = "";
if (!empty($question)) {
    $q_stripped = stripAccents(mb_strtolower($question, 'UTF-8'));
    $q_words = preg_split('/\s+/', $q_stripped);
    $q_words = array_filter($q_words, function($w) { return mb_strlen($w, 'UTF-8') > 1; });

    if (!empty($q_words)) {
        // 1. Khớp kho POS thực tế
        $pos_file = __DIR__ . '/pos_products.json';
        if (file_exists($pos_file)) {
            $pos_products = json_decode(file_get_contents($pos_file), true) ?: [];
            $matched_pos = [];
            foreach ($pos_products as $p) {
                $p_name_stripped = stripAccents(mb_strtolower($p['name'] ?? '', 'UTF-8'));
                foreach ($q_words as $w) {
                    if (strpos($p_name_stripped, $w) !== false) {
                        $matched_pos[] = $p;
                        break;
                    }
                }
            }
            $matched_pos = array_slice($matched_pos, 0, 8);
            if (!empty($matched_pos)) {
                $matching_context .= "\n[DANH SÁCH SẢN PHẨM THỰC TẾ ĐANG CÓ TẠI TIỆM (pos.saigoncacanh.com)]:\n";
                foreach ($matched_pos as $p) {
                    $matching_context .= "- " . $p['name'] . " | Giá tại tiệm: " . number_format(intval($p['sellPrice'] ?? 0), 0, ',', '.') . "đ | Tồn: " . ($p['qty'] ?? 0) . " | Size: " . ($p['size'] ?? '—') . "\n";
                }
            }
        }

        // 2. Khớp sản phẩm giới thiệu web
        $woo_file = __DIR__ . '/woocommerce_products.json';
        if (file_exists($woo_file)) {
            $woo_products = json_decode(file_get_contents($woo_file), true) ?: [];
            $matched_woo = [];
            foreach ($woo_products as $p) {
                $p_name_stripped = stripAccents(mb_strtolower($p['name'] ?? '', 'UTF-8'));
                foreach ($q_words as $w) {
                    if (strpos($p_name_stripped, $w) !== false) {
                        $matched_woo[] = $p;
                        break;
                    }
                }
            }
            $matched_woo = array_slice($matched_woo, 0, 4);
            if (!empty($matched_woo)) {
                $matching_context .= "\n[SẢN PHẨM KHÁC TRÊN WEBSITE SAIGONCACANH.COM]:\n";
                foreach ($matched_woo as $p) {
                    $matching_context .= "- " . $p['name'] . " | Link xem: " . ($p['link'] ?? 'https://saigoncacanh.com') . "\n";
                }
            }
        }

        // 3. Khớp bài viết
        $posts_file = __DIR__ . '/wordpress_posts.json';
        if (file_exists($posts_file)) {
            $posts_data = json_decode(file_get_contents($posts_file), true) ?: [];
            $matched_posts = [];
            foreach ($posts_data as $post) {
                $post_name_stripped = stripAccents(mb_strtolower($post['name'] ?? '', 'UTF-8'));
                foreach ($q_words as $w) {
                    if (strpos($post_name_stripped, $w) !== false) {
                        $matched_posts[] = $post;
                        break;
                    }
                }
            }
            $matched_posts = array_slice($matched_posts, 0, 3);
            if (!empty($matched_posts)) {
                $matching_context .= "\n[BÀI VIẾT HƯỚNG DẪN TỪ WEBSITE SAIGONCACANH.COM]:\n";
                foreach ($matched_posts as $post) {
                    $matching_context .= "- Hướng dẫn: \"" . $post['name'] . "\" | Link đọc: " . ($post['link'] ?? '') . "\n";
                }
            }
        }
    }
}

// Xây dựng System Prompt cứng mang phong cách thợ cả miền Nam, thực chiến bán hàng
$system_prompt = "Bạn là trợ lý AI chính thức của tiệm cá cảnh 'Sài Gòn Cá Cảnh' (địa chỉ 246 Hồ Văn Huê, Phường 9, Quận Phú Nhuận, TP.HCM).

[VAI TRÒ & PHONG CÁCH PHẢN HỒI]:
- Đóng vai một 'thợ cả' lão làng, thợ kỹ thuật nuôi cá rồng và cá cảnh dày dặn kinh nghiệm, chuyên nghiệp nhưng vô cùng bình dân, mộc mạc và gần gũi.
- Sử dụng văn phong miền Nam tự nhiên, thân thiết, đúng chất dân chơi cá Sài Gòn (xưng hô: dạ - em - anh/chị, dùng các từ ngữ gần gũi như 'tiệm em', 'bên em', 'hồ mình', 'dòng này', 'nhận hàng', 'giao hỏa tốc', 'kết nối Zalo').
- Tuyệt đối LỘT BỎ hoàn toàn những câu trả lời dài dòng mang tính văn mẫu, sáo rỗng hoặc lý thuyết sách vở. Nói năng sắc bén, tập trung đi thẳng vào bóc tách nguyên nhân cốt lõi của vấn đề và chốt đơn ngay sản phẩm tại shop.

[MỤC TIÊU BÁN HÀNG & GẮN DỮ LIỆU KHO POS]:
- Mọi câu hỏi của khách hàng về bệnh cá, cách setup hồ, chăm nước, vật liệu lọc đều phải được định hướng trực tiếp đến các sản phẩm cụ thể đang có sẵn tại cửa hàng 246 Hồ Văn Huê.
- Bạn phải chỉ mặt gọi tên ngay sản phẩm thuốc/vật liệu lọc/sưởi phù hợp trong danh sách bên dưới, báo đúng giá bán tại tiệm để khách hàng chốt đơn nhanh chóng. Khẳng định hàng luôn có sẵn giao hỏa tốc.
- Khi khách hỏi về một loại cá hoặc phụ kiện bất kỳ, bạn PHẢI giới thiệu sản phẩm liên quan trong danh sách bên dưới (ví dụ khách hỏi cá bảy màu, hãy giới thiệu thêm thức ăn cá bảy màu, lọc, sưởi liên quan).
- Khi khách hỏi hướng dẫn kỹ thuật hoặc bệnh cá, bạn PHẢI đính kèm đường link bài viết hướng dẫn tương ứng được cung cấp dưới đây để khách bấm vào xem chi tiết.

[ĐỊA LÝ & XIN THÔNG TIN KHÉO LÉO]:
- Khi khách hỏi mua hoặc chốt đơn, khéo léo dùng các cái cớ tự nhiên (tặng mã giảm giá 5% lẻ trên hệ thống POS cho đơn đầu tiên, kiểm tra phí ship hỏa tốc tốt nhất, gửi video thực tế qua Zalo) để xin SĐT và địa chỉ của khách một cách tự nhiên nhất.
- Địa phương khách ở hiện tại: " . $city . " (sử dụng thông tin này để tư vấn ship hoặc mời ghé tiệm).

[NGỮ CẢNH HỆ THỐNG]:
" . $location_instruction . "
" . $qa_rules . "
" . $matching_context;

// Chèn systemInstruction cho Gemini API
$data['systemInstruction'] = [
    'parts' => [
        ['text' => $system_prompt]
    ]
];

// ── CALL GEMINI API ───────────────────────────────────────────
$gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);

$ch = curl_init($gemini_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi kết nối mạng: ' . $curl_error], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code($http_code);
echo $response;
