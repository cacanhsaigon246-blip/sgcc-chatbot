<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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
if (empty($api_key)) {
    // Nếu chưa có key hợp lệ, kiểm tra file local key
    $key_file = __DIR__ . '/gemini_key.txt';
    if (file_exists($key_file)) {
        $api_key = trim(file_get_contents($key_file));
    }
}

$model = $data['model'] ?? 'gemini-2.0-flash-lite';

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
    $location_instruction = "- Vị trí khách hàng: Chưa rõ. Mời khách ghé tiệm tại 246 Hồ Văn Huê, Phường Đức Nhuận, Phú Nhuận hoặc tham khảo gian hàng online: [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com).";
} else if (stripos($city, 'Minh') !== false || stripos($city, 'HCM') !== false) {
    $location_instruction = "- Vị trí khách hàng: TP.HCM. Mời khách ghé thăm trực tiếp tiệm tại 246 Hồ Văn Huê, Phường Đức Nhuận, Phú Nhuận hoặc mua online tại: [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com).";
} else {
    $location_instruction = "- Vị trí khách hàng: Tỉnh ngoài TP.HCM (" . $city . "). Hướng dẫn khách xem và đặt hàng trực tiếp tại: [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com).";
}

// Trích xuất câu hỏi mới nhất từ phần tử cuối cùng của contents
$question = '';
if (!empty($data['contents']) && is_array($data['contents'])) {
    $last_item = end($data['contents']);
    if (isset($last_item['parts'][0]['text'])) {
        $question = $last_item['parts'][0]['text'];
    }
}

// Nhận diện dữ liệu chuẩn hóa từ Edge SLM Client (nếu có)
$client_clean = $data['cleanQuestion'] ?? '';
$intent_tag = $data['intentTag'] ?? 'CHUNG';

if (!function_exists('stripAccents')) {
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
}

$matching_context = "";
if (!empty($question)) {
    $target_text = !empty($client_clean) ? $client_clean : $question;
    $q_stripped = stripAccents(mb_strtolower($target_text, 'UTF-8'));
    $q_words = preg_split('/\s+/', $q_stripped);
    $q_words = array_filter($q_words, function($w) { return mb_strlen($w, 'UTF-8') > 1; });

    // ── 0Đ INSTANT MATCHING ENGINE (TẦNG 1 - 0.01s - KHÔNG TỐN CỬA API GEMINI) ──
    $q_trim = mb_strtolower(trim($target_text), 'UTF-8');
    $q_clean = stripAccents($q_trim);

    // 1. Khớp câu chào hỏi phổ thông
    $greetings = ['xin chao', 'chao shop', 'chao em', 'hi', 'hello', 'alo', 'halo', 'chao', 'cho hoi', 'tu van', 'shop oi'];
    if (in_array($q_clean, $greetings) || in_array($q_clean, ['xin chao shop', 'chao shop oi', 'hi shop', 'halo shop'])) {
        $reply = "Dạ em chào anh/chị ạ! Em là trợ lý tự động của tiệm Sài Gòn Cá Cảnh (246 Hồ Văn Huê, Phường Đức Nhuận, Phú Nhuận) 🐟\n\nAnh/chị đang cần em hỗ trợ tư vấn về phụ kiện, thuốc cá hay kinh nghiệm chăm nước gì cho hồ cá của mình hôm nay ạ?";
        echo json_encode([
            'candidates' => [
                ['content' => ['parts' => [['text' => $reply]]]]
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // 2. Khớp trực tiếp Q&A đã huấn luyện (qa_pairs.json) với thuật toán 3 Pass Smart Concept Matcher
    $qa_file = __DIR__ . '/qa_pairs.json';
    if (file_exists($qa_file)) {
        $qa_list = json_decode(file_get_contents($qa_file), true);
        if (is_array($qa_list)) {
            // PASS 1: Khớp chính xác 100% hoặc khớp chuỗi con hoàn hảo
            foreach ($qa_list as $qa) {
                if (!empty($qa['question']) && !empty($qa['answer'])) {
                    $q_target = stripAccents(mb_strtolower($qa['question'], 'UTF-8'));
                    if ($q_clean === $q_target || (mb_strlen($q_clean, 'UTF-8') >= 4 && strpos($q_target, $q_clean) !== false) || (mb_strlen($q_target, 'UTF-8') >= 4 && strpos($q_clean, $q_target) !== false)) {
                        $reply = $qa['answer'];
                        echo json_encode([
                            'candidates' => [
                                ['content' => ['parts' => [['text' => $reply]]]]
                            ]
                        ], JSON_UNESCAPED_UNICODE);
                        exit();
                    }
                }
            }

            // PASS 2: Khớp Chủ Đề Khái Niệm Cốt Lõi (Topic Concept Matcher - Siêu Thông Minh 100%)
            $concept_matched_reply = null;
            
            // 1. Thương hiệu Thiết bị & Thuốc
            if (strpos($q_clean, 'atman') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'atman') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'periha') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'periha') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'gecai') !== false || strpos($q_clean, 'suoi') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'suoi') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'seachem') !== false || strpos($q_clean, 'prime') !== false || strpos($q_clean, 'clo') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'prime') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'extrabio') !== false || strpos($q_clean, 'vi sinh nuoc') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'extrabio') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'compozyme') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'compozyme') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'bio knock') !== false || strpos($q_clean, 'knock') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'bio knock') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            // 2. Vấn đề bệnh cá & Nước
            else if (strpos($q_clean, 'duc') !== false || strpos($q_clean, 'nuoc do') !== false || strpos($q_clean, 'ho do') !== false || strpos($q_clean, 'hoi') !== false || strpos($q_clean, 'trong nuoc') !== false || preg_match('/\bdo\b/u', $q_clean)) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'duc') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'nam') !== false || strpos($q_clean, 'lo') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'nam') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'tum') !== false || strpos($q_clean, 'lac lo') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'tum') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            // 3. Phụ kiện & Thức ăn & Bơm
            else if (strpos($q_clean, 'j-mat') !== false || strpos($q_clean, 'jmat') !== false || strpos($q_clean, 'vat lieu loc') !== false || strpos($q_clean, 'su loc') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'vat lieu loc') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'cam') !== false || strpos($q_clean, 'thuc an') !== false || strpos($q_clean, 'arowana') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'thuc an') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'dong may bom') !== false || strpos($q_clean, 'may bom') !== false || strpos($q_clean, 'bom') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'may bom ho ca') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            // 4. Mua bán & Dòng cá & Giá cả & Tiệm bán gì
            else if (strpos($q_clean, 'ban cai gi') !== false || strpos($q_clean, 'co cai gi') !== false || strpos($q_clean, 'ban gi') !== false || strpos($q_clean, 'co gi') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'may ban cai gi') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'gia ca') !== false || strpos($q_clean, 'gia bao nhieu') !== false || strpos($q_clean, 'nhieu tien') !== false || strpos($q_clean, 'bao gia') !== false || strpos($q_clean, 'bao nhieu tien') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'gia ca') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'dong ca') !== false || strpos($q_clean, 'ca canh') !== false || strpos($q_clean, 'loai ca') !== false || strpos($q_clean, 'mua ca') !== false || strpos($q_clean, 'co ca gi') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'can mua ca canh') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if ((strpos($q_clean, 'ho ca') !== false || strpos($q_clean, 'be ca') !== false) && (strpos($q_clean, 'ca') === false)) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'mua ho ca') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'tep') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'tep') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }
            else if (strpos($q_clean, 'dia chi') !== false || strpos($q_clean, 'o dau') !== false || strpos($q_clean, 'cua hang') !== false || strpos($q_clean, 'tiem') !== false) {
                foreach ($qa_list as $qa) {
                    if (strpos(stripAccents(mb_strtolower($qa['question'], 'UTF-8')), 'dia chi') !== false) {
                        $concept_matched_reply = $qa['answer']; break;
                    }
                }
            }

            if ($concept_matched_reply !== null) {
                echo json_encode([
                    'candidates' => [
                        ['content' => ['parts' => [['text' => $concept_matched_reply]]]]
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }

            // PASS 3: Khớp điểm từ khóa cao nhất (Highest Match Score)
            $best_reply = null;
            $best_score = 0;
            $stopwords = ['cho', 'em', 'hoi', 'la', 'nhu', 'the', 'nao', 'lam', 'sao', 'co', 'bi', 'khong', 'gi', 'voi', 'thi', 'vay', 'cac', 'cua', 'trong', 'hoac', 'be', 'ho', 'hom', 'nay', 'toi', 'muon', 'can', 'tu', 'van', 'giup', 've', 'voi', 'shop', 'a', 'gia', 'ca'];
            $q_words_input = array_values(array_filter(explode(' ', $q_clean), function($w) use ($stopwords) {
                return mb_strlen($w, 'UTF-8') >= 2 && !in_array($w, $stopwords);
            }));

            if (count($q_words_input) >= 1) {
                foreach ($qa_list as $qa) {
                    if (!empty($qa['question']) && !empty($qa['answer'])) {
                        $q_target = stripAccents(mb_strtolower($qa['question'], 'UTF-8'));
                        $score = 0;
                        foreach ($q_words_input as $w_in) {
                            if (strpos($q_target, $w_in) !== false) {
                                $score++;
                            }
                        }
                        if ($score > $best_score && $score >= 2) {
                            $best_score = $score;
                            $best_reply = $qa['answer'];
                        }
                    }
                }
            }

            if ($best_reply !== null) {
                echo json_encode([
                    'candidates' => [
                        ['content' => ['parts' => [['text' => $best_reply]]]]
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }
    }

    $shop_data = null;
    $ctx_shop = stream_context_create(['http' => ['timeout' => 3.0]]);

    if (!empty($q_words)) {
        // 1. LẤY SẢN PHẨM THẬT & LINK THẬT TỪ GIAN HÀNG SHOP.SAIGONCACANH.COM
        $shop_api_url = "https://shop.saigoncacanh.com/chatbot-api.php?q=" . urlencode($question);
        $shop_res = @file_get_contents($shop_api_url, false, $ctx_shop);
        $shop_data = $shop_res ? json_decode($shop_res, true) : null;
        
        // Fallback: If no products found, try searching with just the first significant word (e.g., 'bơm', 'lọc')
        if (empty($shop_data['products']) && count($q_words) > 1) {
            $fallback_query = urlencode($q_words[0]);
            if (in_array(strtolower($q_words[0]), ['máy', 'thuốc', 'cám', 'đèn', 'phụ', 'kiện', 'thức', 'ăn'])) {
                $fallback_query = urlencode($q_words[0] . ' ' . ($q_words[1] ?? ''));
            }
            $shop_api_url = "https://shop.saigoncacanh.com/chatbot-api.php?q=" . $fallback_query;
            $shop_res = @file_get_contents($shop_api_url, false, $ctx_shop);
            $shop_data = $shop_res ? json_decode($shop_res, true) : null;
        }
    }

    // Fallback 2: If STILL no products found, just get a general list of products to suggest
    if (empty($shop_data['products'])) {
        $shop_api_url = "https://shop.saigoncacanh.com/chatbot-api.php?limit=15";
        $shop_res = @file_get_contents($shop_api_url, false, $ctx_shop);
        $shop_data = $shop_res ? json_decode($shop_res, true) : null;
        if (!empty($shop_data['products'])) {
            shuffle($shop_data['products']);
        }
    }

    if ($shop_data) {
            if (!empty($shop_data['products'])) {
                $matching_context .= "\n[DANH SÁCH SẢN PHẨM TỪ GIAN HÀNG SHOPEE]:\n";
                $matching_context .= "LƯU Ý ĐẶC BIỆT: Mọi sản phẩm bạn muốn giới thiệu từ danh sách này, bạn BẮT BUỘC phải copy nguyên văn dòng [CARD:...] tương ứng bên dưới để chatbot tự động hiển thị Hình Ảnh và Link chuẩn. TUYỆT ĐỐI KHÔNG dùng định dạng [Tên](link) và KHÔNG ĐƯỢC rút gọn link bên trong thẻ [CARD:...]. Nếu bạn sai định dạng, khách hàng sẽ không thể click để mua!\n\n";
                foreach (array_slice($shop_data['products'], 0, 6) as $sp) {
                    $sp_title = str_replace('|', '-', $sp['title']); // Remove | to avoid breaking regex
                    $sp_price = $sp['price'] ?? 'Deal Ngon';
                    $sp_link = $sp['affiliate_link'] ?? 'https://shop.saigoncacanh.com';
                    $sp_image = $sp['image'] ?? 'https://shop.saigoncacanh.com/images/default.jpg';
                    $matching_context .= "[CARD:" . $sp_title . "|" . $sp_link . "|" . $sp_image . "|" . $sp_price . "]\n";
                }
                
                // Thu thập thông tin sản phẩm khách hàng quan tâm
                $interested_file = __DIR__ . '/interested_products.json';
                $interested = [];
                if (file_exists($interested_file)) {
                    $interested = json_decode(file_get_contents($interested_file), true) ?: [];
                }
                $now = date('Y-m-d H:i:s');
                foreach (array_slice($shop_data['products'], 0, 3) as $sp) {
                    $found = false;
                    foreach ($interested as &$item) {
                        if (isset($item['id']) && isset($sp['id']) && $item['id'] === $sp['id']) {
                            $item['count'] = ($item['count'] ?? 0) + 1;
                            $item['last_searched'] = $now;
                            $found = true;
                            break;
                        }
                    }
                    if (!$found && isset($sp['id'])) {
                        $interested[] = [
                            'id' => $sp['id'],
                            'title' => $sp['title'],
                            'price' => $sp['price'] ?? '',
                            'link' => $sp['affiliate_link'] ?? '',
                            'image' => $sp['image'] ?? '',
                            'count' => 1,
                            'last_searched' => $now
                        ];
                    }
                }
                file_put_contents($interested_file, json_encode($interested, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        // 2. LẤY GIÁ BÁN & TỒN KHO THỰC TẾ TRỰC TIẾP TỪ DATABASE POS (pos.saigoncacanh.com)
        $matched_pos = [];
        try {
            $pos_pdo = new PDO("mysql:host=127.0.0.1;dbname=u972437838_pos;charset=utf8mb4", "u972437838_pos_user", "Cannabis041188", [
                PDO::ATTR_TIMEOUT => 1,
                PDO::ERRMODE => PDO::ERRMODE_SILENT
            ]);
            if (!empty($q_words)) {
                $pos_stmt = $pos_pdo->query("SELECT `name`, `quantity`, `sell_price` FROM `inventory` ORDER BY `quantity` DESC LIMIT 150");
                if ($pos_stmt) {
                    $pos_rows = $pos_stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($pos_rows as $p) {
                        $p_name_stripped = stripAccents(mb_strtolower($p['name'] ?? '', 'UTF-8'));
                        foreach ($q_words as $w) {
                            if (mb_strlen($w, 'UTF-8') >= 2 && strpos($p_name_stripped, $w) !== false) {
                                $matched_pos[] = [
                                    'name' => $p['name'],
                                    'qty' => intval($p['quantity'] ?? 0),
                                    'sellPrice' => floatval($p['sell_price'] ?? 0)
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Fallback đọc file pos_products.json nếu MySQL gián đoạn
            $pos_file = __DIR__ . '/pos_products.json';
            if (file_exists($pos_file)) {
                $pos_products = json_decode(file_get_contents($pos_file), true) ?: [];
                foreach ($pos_products as $p) {
                    $p_name_stripped = stripAccents(mb_strtolower($p['name'] ?? '', 'UTF-8'));
                    foreach ($q_words as $w) {
                        if (mb_strlen($w, 'UTF-8') >= 2 && strpos($p_name_stripped, $w) !== false) {
                            $matched_pos[] = $p;
                            break;
                        }
                    }
                }
            }
        }

        if (!empty($matched_pos)) {
            $matching_context .= "\n[GIÁ BÁN & TỒN KHO THỰC TẾ TỪ CSDL POS.SAIGONCACANH.COM (TIỆM 246 HỒ VĂN HUÊ)]:\n";
            $matching_context .= "LƯU Ý: Đây là con số thực tế 100% đang có tại tiệm. Bạn BẮT BUỘC phải ưu tiên báo giá và số lượng tồn kho này cho khách hàng!\n";
            foreach (array_slice($matched_pos, 0, 6) as $p) {
                $stock_status = intval($p['qty'] ?? 0) > 0 ? ("Còn " . intval($p['qty']) . " món tại tiệm") : "Tạm hết hàng tại tiệm";
                $matching_context .= "- " . $p['name'] . " | Giá POS tiệm: " . number_format(intval($p['sellPrice'] ?? 0), 0, ',', '.') . "đ (" . $stock_status . ")\n";
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

// Xây dựng System Prompt cứng mang phong cách thợ cá Sài Gòn lâu năm, tư vấn tận tâm và am hiểu sâu sắc
$system_prompt = "Bạn là người thợ cá lâu năm giàu kinh nghiệm của tiệm 'Sài Gòn Cá Cảnh' tại 246 Hồ Văn Huê, Phường Đức Nhuận (P.9 cũ), Quận Phú Nhuận, TP.HCM.

[PHONG CÁCH TƯ DUY & VĂN PHONG THỢ CÁ SÀI GÒN]:
- ĐÓNG VAI NGUỜI THỢ CÁ AM HIỂU VÀ TẬN TÂM: Bạn có hơn 15 năm kinh nghiệm nuôi cá rồng, cá koi, cá betta, cá vàng, tép màu và xử lý mọi loại bệnh cá & thiết bị hồ lọc TPHCM.
- XƯNG HÔ THÂN THIỆN: Gọi khách là 'anh' hoặc 'chị', tự xưng là 'em'. Văn phong tự nhiên, ấm áp, chân thành đúng chất anh em chơi cá Sài Gòn.
- TƯ DUY PHÂN TÍCH SÂU SẮC: Khi khách hỏi bất kỳ câu hỏi nào (bệnh cá, máy bơm, sưởi, nước đục...), hãy phân tích RÕ NGUYÊN NHÂN CỐT LÕI trước, sau đó đưa ra GIẢI PHÁP THỰC TẾ THEO BƯỚC (1, 2, 3) để khách áp dụng thành công ngay.
- TRÌNH BÀY ĐẸP MẮT & SẮC NÉT: Phải xuống dòng (newline) rõ ràng giữa các phần để dễ đọc trên điện thoại.
- TUYỆT ĐỐI KHÔNG DÙNG VĂN MẪU RẬP KHUÔN kiểu robot: Tránh các câu khô khan như 'Dạ về câu hỏi của anh... em xin tư vấn...'. Trả lời thẳng thắn, hào hứng như hai thợ cá đang ngồi uống trà trao đổi kinh nghiệm tại tiệm 246 Hồ Văn Huê.
- NẾU CÓ THẺ SẢN PHẨM [CARD:...]: Hãy lồng ghép tự nhiên các thẻ sản phẩm [CARD:...] vào cuối bài tư vấn để khách bấm xem và mua mượt mà.

[BẢO VỆ PHÁP LÝ & AN TOÀN]:
- Dùng từ ngữ khiêm tốn, an toàn: 'bên em thấy dùng rất ổn', 'được nhiều anh em chơi cá tin dùng', 'đã lọc kỹ chất lượng'.
- Hàng hóa giao nhận/bảo hành online: Giới thiệu link gian hàng shop.saigoncacanh.com để khách xem giá và đặt mua dễ dàng.

[QUY TẮC HIỂN THỊ SẢN PHẨM & CẤM BỊA ĐẶT LINK]:
- BẮT BUỘC DÙNG [CARD]: Để hiển thị một sản phẩm, TUYỆT ĐỐI KHÔNG dùng định dạng Markdown [Tên](link). Bạn CHỈ ĐƯỢC PHÉP copy nguyên văn dòng [CARD:...] từ phần [DANH SÁCH SẢN PHẨM TỪ GIAN HÀNG SHOPEE] bên dưới.
- KHÔNG BỊA ĐẶT LINK SẢN PHẨM: Nếu khách hỏi một sản phẩm KHÔNG CÓ trong [DANH SÁCH SẢN PHẨM TỪ GIAN HÀNG SHOPEE], BẠN TUYỆT ĐỐI KHÔNG ĐƯỢC TỰ BỊA RA LINK HAY TỰ BỊA RA THẺ [CARD]. Hãy thành thật nói rằng trên gian hàng online tạm hết hoặc chưa cập nhật, và mời khách ghé trực tiếp tiệm hoặc xem các sản phẩm khác.
- LINK GIAN HÀNG CHÍNH THỨC: Khi mời khách xem thêm toàn bộ gian hàng (không phải một sản phẩm cụ thể), hãy dùng định dạng Markdown: [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com).

[NGỮ CẢNH HỆ THỐNG & KHO TRI THỨC CHUẨN CỦA TIỆM]:
" . $location_instruction . "
" . $qa_rules . "
" . $matching_context . "

[BẢN CHẤT TƯ DUY & TẠO ĐÁP ÁN THÔNG MINH BIẾN HÓA]:
1. Bạn không phải là bot cứng nhắc! Hãy dùng trí tuệ tự nhiên của người thợ cá lâu năm tại Sài Gòn Cá Cảnh để phân tích nhu cầu thực sự của khách.
2. Trả lời thẳng vào giải pháp, chia các bước rõ ràng (1., 2., 3.), kèm mẹo chăm cá mộc mạc và chân thành nhất.
3. Nếu có sản phẩm phù hợp từ danh sách bên dưới, hãy đính kèm thẻ [CARD:...] để khách tiện xem và đặt mua!";

// ── CALL GEMINI API (XỬ LÝ ĐÚNG ĐỊNH DẠNG API KEY GOOGLE AI STUDIO) ───────────────────────────────────────────
$is_bearer = (strpos($api_key, 'ya29.') === 0);

if ($is_bearer) {
    $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
} else {
    $gemini_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($api_key);
    $headers = [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $api_key
    ];
}

// ── TẦNG 2: CLOUD MICRO SLM (CLOUDFLARE WORKERS AI FREE TIER 300MS) ──
$cf_worker_url = 'https://sgcc-slm.cacanhsaigon246.workers.dev';
if (!empty($cf_worker_url) && !empty($question)) {
    $cf_ch = curl_init($cf_worker_url);
    curl_setopt_array($cf_ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['question' => $question]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 3
    ]);
    $cf_res = curl_exec($cf_ch);
    $cf_code = curl_getinfo($cf_ch, CURLINFO_HTTP_CODE);
    curl_close($cf_ch);
    if ($cf_code === 200 && $cf_res) {
        $cf_data = json_decode($cf_res, true);
        if (!empty($cf_data['reply'])) {
            $reply = $cf_data['reply'];
            if (!empty($matching_context)) {
                $reply .= "\n\n" . $matching_context;
            }
            echo json_encode([
                'candidates' => [
                    ['content' => ['parts' => [['text' => $reply]]]]
                ]
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    }
}

$ch = curl_init($gemini_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 15
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => "cURL error: " . $curl_err], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($http_code === 429 || stripos($response, 'RESOURCE_EXHAUSTED') !== false || stripos($response, 'quota') !== false) {
    http_response_code(200);
    $reply = "Dạ câu hỏi này chưa nằm trong kho 100 Chủ đề Q&A đã huấn luyện và API Gemini đang tạm hết hạn mức miễn phí trong phút này ạ.\n\nAnh/chị xem thêm thông tin sản phẩm và bài viết tại [Siêu Thị Sài Gòn Cá Cảnh](https://shop.saigoncacanh.com) hoặc liên hệ trực tiếp Zalo tiệm em 0938.604.144 để được tư vấn ngay nhé ạ!";
    echo json_encode([
        'candidates' => [
            ['content' => ['parts' => [['text' => $reply]]]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($http_code === 401 || stripos($response, 'UNAUTHENTICATED') !== false || stripos($response, 'API_KEY_INVALID') !== false) {
    http_response_code(200);
    $reply = "Dạ câu hỏi này chưa có sẵn trong kho 100 Chủ đề Q&A đã huấn luyện. Hiện tại Gemini API Key trên hệ thống đang hết hạn hoặc chưa đúng ạ.\n\nAnh Phát hãy truy cập https://aistudio.google.com lấy 1 Gemini API Key miễn phí mới ➔ Dán vào mục API Key tại https://chatbot.saigoncacanh.com/admin.html để kích hoạt lại AI suy luận mở rộng nhé ạ! Với các câu hỏi về chăm cá, bệnh cá, thiết bị đã huấn luyện thì Chatbot vẫn trả lời bình thường 100% 0đ ạ!";
    echo json_encode([
        'candidates' => [
            ['content' => ['parts' => [['text' => $reply]]]]
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

http_response_code($http_code);
echo $response;
