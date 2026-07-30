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

$model = $data['model'] ?? 'gemini-flash-lite-latest';

// Bỏ apiKey khỏi payload gửi cho Gemini
if (isset($data['apiKey'])) unset($data['apiKey']);

if (empty($api_key)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Chưa cấu hình Gemini API Key chính thức! Anh Phát hãy nhập Key tại https://chatbot.saigoncacanh.com/admin.html mục API Key nhé ạ!'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Tự động chèn Quy tắc Q&A huấn luyện từ Admin Panel vào nội dung câu hỏi
$qa_file = __DIR__ . '/qa_pairs.json';
if (file_exists($qa_file)) {
    $qa_data = json_decode(file_get_contents($qa_file), true);
    if (is_array($qa_data) && !empty($qa_data)) {
        $qa_rules = "\n\n[QUY TẮC TRẢ LỜI QUAN TRỌNG ĐƯỢC CHỈ ĐỊNH BỞI CỬA HÀNG - HÃY TUÂN THỦ TUYỆT ĐỐI]:\n";
        foreach ($qa_data as $qa) {
            if (!empty($qa['question']) && !empty($qa['answer'])) {
                $qa_rules .= "- Khách hỏi: \"" . $qa['question'] . "\"\n  Hãy trả lời đúng như sau: \"" . $qa['answer'] . "\"\n";
            }
        }
        $qa_rules .= "\nNếu câu hỏi của khách hàng trùng khớp hoặc gần giống với các câu hỏi trên, bạn PHẢI sử dụng câu trả lời tương ứng được chỉ định ở trên thay vì tự ý bịa đặt.";
        
        if (isset($data['contents'][0]['parts'][0]['text'])) {
            $data['contents'][0]['parts'][0]['text'] .= $qa_rules;
        }
    }
}

// Tự động định vị Tỉnh/Thành của khách hàng qua IP để chỉ thị AI
$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']);
$city = 'Không rõ';
if ($client_ip && $client_ip !== '127.0.0.1' && $client_ip !== '::1') {
    // Gọi API định vị nhẹ qua ip-api
    $geo_res = @file_get_contents("http://ip-api.com/json/" . $client_ip . "?fields=status,city");
    if ($geo_res) {
        $geo_data = json_decode($geo_res, true);
        if (isset($geo_data['status']) && $geo_data['status'] === 'success') {
            $city = $geo_data['city'] ?? 'Không rõ';
        }
    }
}

$location_instruction = "";
if ($city === 'Không rõ') {
    $location_instruction = "\n\n[CHỈ THỊ THU THẬP THÔNG TIN]: Vị trí địa lý của khách hàng hiện tại chưa rõ ràng (Không rõ). Hãy tìm thời cơ khéo léo hỏi xem khách ở quận nào của TP.HCM hoặc ở tỉnh nào để tiện báo giá ship chính xác và tư vấn.";
} else if (stripos($city, 'Minh') !== false || stripos($city, 'HCM') !== false) {
    $location_instruction = "\n\n[CHỈ THỊ ĐỊA LÝ QUAN TRỌNG - KHÁCH Ở TP.HCM]: Khách hàng này đang ở khu vực TP.HCM. Hãy nhiệt tình mời họ ghé thăm trực tiếp cửa hàng tại địa chỉ: 246 Hồ Văn Huê, Phường Đức Nhuận để lựa chọn cá trực tiếp và nhận tư vấn chuyên sâu từ chủ cửa hàng (anh Phát).";
} else {
    $location_instruction = "\n\n[CHỈ THỊ ĐỊA LÝ QUAN TRỌNG - KHÁCH TỈNH]: Khách hàng này ở ngoài TP.HCM (" . $city . "). Hãy hướng dẫn họ mua sắm Online và tư vấn mua phụ kiện/thuốc chữa bệnh qua Siêu thị Shopee bên em giao hàng nhanh toàn quốc: https://shop.saigoncacanh.com hoặc nhắn Zalo Shop.";
}

// Kịch bản dẫn dụ xin thông tin (SĐT, Địa chỉ) khéo léo, tự nhiên
$location_instruction .= "\n\n[HƯỚNG DẪN DẪN DỤ XIN THÔNG TIN TỰ NHIÊN - TUYỆT ĐỐI KHÔNG ÉP BUỘC]:
Khi khách hỏi mua, tư vấn giá, chốt đơn hoặc hỏi về phí ship, hãy dùng các cái cớ sau để khách tự nguyện để lại thông tin thật mà không nghi ngờ:
1. Tạo mã ưu đãi: 'Dạ hiện hệ thống POS của tiệm đang có mã giảm giá 5% cho khách lẻ chốt đơn hôm nay. Anh/chị cho em xin số điện thoại để em kích hoạt ưu đãi này trên hệ thống cho mình nhé ạ!'
2. Kiểm tra Freeship & phí vận chuyển: 'Dạ để em kiểm tra phí giao hàng hỏa tốc tốt nhất và xem đơn mình có được hỗ trợ Freeship không, anh/chị cho em xin số điện thoại và địa chỉ nhận hàng cụ thể nha!'
3. Gửi hình ảnh/video cá thực tế qua Zalo: 'Dạ các dòng cá tại tiệm đang rất đẹp và khỏe. Anh cho em xin số điện thoại kết nối Zalo để kỹ thuật viên bên em quay video thực tế cá tại tiệm gửi anh xem và lựa chọn cho trực quan nhé!'";

// Chỉ thị phân biệt website thông tin và kho hàng POS thực tế
$location_instruction .= "\n\n[LƯU Ý PHÂN BIỆT KÊNH THÔNG TIN VÀ KHO HÀNG THỰC TẾ]:
- Website saigoncacanh.com chỉ dùng để chứa thông tin giới thiệu, hướng dẫn kỹ thuật nuôi cá và bài viết chia sẻ kinh nghiệm (Hãy hướng dẫn khách đọc bài viết ở đây để tham khảo).
- Hệ thống POS (pos.saigoncacanh.com) được đưa vào dưới dạng bảng [DANH SÁCH SẢN PHẨM THỰC TẾ ĐANG CÓ SẴN TẠI CỬA HÀNG] mới là nơi chứa sản phẩm, giá bán và số lượng tồn kho THỰC TẾ đang có tại tiệm. Bạn PHẢI ưu tiên giới thiệu các sản phẩm thực tế có sẵn này và báo đúng giá bán tại tiệm cho khách hàng.";

if (isset($data['contents'][0]['parts'][0]['text'])) {
    $data['contents'][0]['parts'][0]['text'] .= $location_instruction;
}

// Tải và khớp từ khóa với sản phẩm POS thực tế, sản phẩm WooCommerce và bài viết WordPress
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

$matching_context = "";

if (!empty($question)) {
    $q_words = preg_split('/\s+/', mb_strtolower($question, 'UTF-8'));
    $q_words = array_filter($q_words, function($w) { return mb_strlen($w, 'UTF-8') > 2; });

    if (!empty($q_words)) {
        // 1. Tìm trong sản phẩm POS thực tế (pos_products.json) - Ưu tiên 1
        $pos_file = __DIR__ . '/pos_products.json';
        if (file_exists($pos_file)) {
            $pos_products = json_decode(file_get_contents($pos_file), true) ?: [];
            $matched_pos = [];
            foreach ($pos_products as $p) {
                $p_name = mb_strtolower($p['name'] ?? '', 'UTF-8');
                foreach ($q_words as $w) {
                    if (strpos($p_name, $w) !== false) {
                        $matched_pos[] = $p;
                        break;
                    }
                }
            }
            $matched_pos = array_slice($matched_pos, 0, 8);
            if (!empty($matched_pos)) {
                $matching_context .= "\n\n[DANH SÁCH SẢN PHẨM THỰC TẾ ĐANG CÓ TRỰC TIẾP TẠI KHO - ƯU TIÊN GIỚI THIỆU HÀNG ĐẦU (pos.saigoncacanh.com)]:\n";
                foreach ($matched_pos as $p) {
                    $matching_context .= "- Tên: " . $p['name'] . " | Giá bán tại tiệm: " . number_format(intval($p['sellPrice'] ?? 0), 0, ',', '.') . "đ | Tồn kho hiện tại: " . ($p['qty'] ?? 0) . " | Size: " . ($p['size'] ?? '—') . "\n";
                }
                $matching_context .= "-> Bạn hãy dùng các sản phẩm thực tế ở trên để báo giá chính xác và khẳng định ĐANG CÓ SẴN TẠI TIỆM cho khách.";
            }
        }

        // 2. Tìm trong sản phẩm WooCommerce trên website (woocommerce_products.json) - Ưu tiên 2
        $woo_file = __DIR__ . '/woocommerce_products.json';
        if (file_exists($woo_file)) {
            $woo_products = json_decode(file_get_contents($woo_file), true) ?: [];
            $matched_woo = [];
            foreach ($woo_products as $p) {
                $p_name = mb_strtolower($p['name'] ?? '', 'UTF-8');
                foreach ($q_words as $w) {
                    if (strpos($p_name, $w) !== false) {
                        $matched_woo[] = $p;
                        break;
                    }
                }
            }
            $matched_woo = array_slice($matched_woo, 0, 5);
            if (!empty($matched_woo)) {
                $matching_context .= "\n\n[SẢN PHẨM KHÁC TRÊN WEBSITE SAIGONCACANH.COM (THÔNG TIN THAM KHẢO - ƯU TIÊN 2)]:\n";
                foreach ($matched_woo as $p) {
                    $matching_context .= "- " . $p['name'] . " | Xem chi tiết: " . ($p['link'] ?? 'https://saigoncacanh.com') . "\n";
                }
                $matching_context .= "-> Hãy nói rõ đây là sản phẩm giới thiệu trên web chính, anh nhắn số điện thoại để em kiểm tra xem kho hàng thực tế (POS) còn hàng sẵn không nhé.";
            }
        }

        // 3. Tìm trong bài viết WordPress (wordpress_posts.json) - Ưu tiên 3
        $posts_file = __DIR__ . '/wordpress_posts.json';
        if (file_exists($posts_file)) {
            $posts_data = json_decode(file_get_contents($posts_file), true) ?: [];
            $matched_posts = [];
            foreach ($posts_data as $post) {
                $post_name = mb_strtolower($post['name'] ?? '', 'UTF-8');
                foreach ($q_words as $w) {
                    if (strpos($post_name, $w) !== false) {
                        $matched_posts[] = $post;
                        break;
                    }
                }
            }
            $matched_posts = array_slice($matched_posts, 0, 3);
            if (!empty($matched_posts)) {
                $matching_context .= "\n\n[BÀI VIẾT HƯỚNG DẪN CHI TIẾT TỪ WEBSITE SAIGONCACANH.COM - HÃY GỢI Ý LINK CHO KHÁCH ĐỌC THÊM]:\n";
                foreach ($matched_posts as $post) {
                    $matching_context .= "- Hướng dẫn: \"" . $post['name'] . "\" -> Đường dẫn bài viết: " . ($post['link'] ?? '') . "\n";
                }
            }
        }
    }
}

// Chèn toàn bộ ngữ cảnh tìm kiếm vào trước câu hỏi
if (!empty($matching_context) && isset($data['contents'][0]['parts'][0]['text'])) {
    $data['contents'][0]['parts'][0]['text'] .= $matching_context;
}

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
