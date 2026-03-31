<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Start session for admin panel login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================

$port = getenv('PORT') ?: '80';

if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');

// Public Channels
define('MAIN_CHANNEL', '@EntertainmentTadka786');
define('MAIN_CHANNEL_ID', '-1003181705395');

define('SERIAL_CHANNEL', '@Entertainment_Tadka_Serial_786');
define('SERIAL_CHANNEL_ID', '-1003614546520');

define('THEATER_CHANNEL', '@threater_print_movies');
define('THEATER_CHANNEL_ID', '-1002831605258');

define('BACKUP_CHANNEL_USERNAME', '@ETBackup');
define('BACKUP_CHANNEL_ID', '-1002964109368');

define('REQUEST_CHANNEL', '@EntertainmentTadka7860');
define('REQUEST_CHANNEL_ID', '-1003083386043');

// Private Channels
define('PRIVATE_CHANNEL_1_ID', '-1003251791991');
define('PRIVATE_CHANNEL_2_ID', '-1002337293281');

// Admin
define('ADMIN_ID', (int)getenv('ADMIN_ID'));

// File paths
define('CSV_DIR', 'csv_data/');
define('CSV_MAIN', 'movies_main.csv');
define('CSV_SERIAL', 'movies_serial.csv');
define('CSV_THEATER', 'movies_theater.csv');
define('CSV_BACKUP', 'movies_backup.csv');
define('CSV_PRIVATE1', 'movies_private1.csv');
define('CSV_PRIVATE2', 'movies_private2.csv');

define('REQUEST_FILE', 'movie_requests.json');
define('LOG_FILE', 'bot_activity.log');

// Constants
define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('MAX_PAGES_TO_SHOW', 7);
define('PAGINATION_CACHE_TIMEOUT', 60);
define('PREVIEW_ITEMS', 3);
define('BATCH_SIZE', 5);

// ==============================
// GLOBAL VARIABLES
// ==============================
$movie_messages = array();
$movie_cache = array();
$user_pagination_sessions = array();

// ==============================
// HELPER FUNCTIONS
// ==============================
function get_csv_filename($channel_type, $channel_id = '') {
    if (!file_exists(CSV_DIR)) mkdir(CSV_DIR, 0777, true);
    switch ($channel_type) {
        case 'main': return CSV_DIR . CSV_MAIN;
        case 'serial': return CSV_DIR . CSV_SERIAL;
        case 'theater': return CSV_DIR . CSV_THEATER;
        case 'backup': return CSV_DIR . CSV_BACKUP;
        case 'private':
            if ($channel_id == PRIVATE_CHANNEL_1_ID) return CSV_DIR . CSV_PRIVATE1;
            if ($channel_id == PRIVATE_CHANNEL_2_ID) return CSV_DIR . CSV_PRIVATE2;
            return CSV_DIR . 'movies_private_other.csv';
        default: return CSV_DIR . 'movies_other.csv';
    }
}

function get_channel_type_by_id($channel_id) {
    $channel_id = strval($channel_id);
    if ($channel_id == MAIN_CHANNEL_ID) return 'main';
    if ($channel_id == SERIAL_CHANNEL_ID) return 'serial';
    if ($channel_id == THEATER_CHANNEL_ID) return 'theater';
    if ($channel_id == BACKUP_CHANNEL_ID) return 'backup';
    if ($channel_id == PRIVATE_CHANNEL_1_ID || $channel_id == PRIVATE_CHANNEL_2_ID) return 'private';
    if ($channel_id == REQUEST_CHANNEL_ID) return 'request';
    return 'other';
}

function get_channel_display_name($channel_type) {
    $names = [
        'main' => '🍿 Main Channel',
        'serial' => '📺 Serial Channel',
        'theater' => '🎭 Theater Prints',
        'backup' => '🔒 Backup Channel',
        'private' => '🔐 Private Channel',
        'request' => '📥 Request Group',
        'other' => '📢 Other Channel'
    ];
    return $names[$channel_type] ?? '📢 Unknown Channel';
}

function get_channel_username_link($channel_type) {
    switch ($channel_type) {
        case 'main': return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
        case 'serial': return "https://t.me/" . ltrim(SERIAL_CHANNEL, '@');
        case 'theater': return "https://t.me/" . ltrim(THEATER_CHANNEL, '@');
        case 'backup': return "https://t.me/" . ltrim(BACKUP_CHANNEL_USERNAME, '@');
        case 'request': return "https://t.me/" . ltrim(REQUEST_CHANNEL, '@');
        default: return "https://t.me/EntertainmentTadka786";
    }
}

function initialize_files() {
    if (!file_exists(CSV_DIR)) mkdir(CSV_DIR, 0777, true);
    $csv_files = [
        CSV_DIR . CSV_MAIN, CSV_DIR . CSV_SERIAL, CSV_DIR . CSV_THEATER,
        CSV_DIR . CSV_BACKUP, CSV_DIR . CSV_PRIVATE1, CSV_DIR . CSV_PRIVATE2,
        CSV_DIR . 'movies_other.csv'
    ];
    $header = "movie_name,message_id\n";
    foreach ($csv_files as $file) {
        if (!file_exists($file)) file_put_contents($file, $header);
    }
    if (!file_exists(REQUEST_FILE)) {
        file_put_contents(REQUEST_FILE, json_encode(['requests' => [], 'pending_approval' => [], 'completed_requests' => [], 'user_request_count' => []], JSON_PRETTY_PRINT));
    }
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
    }
}
initialize_files();

function bot_log($message, $type = 'INFO') {
    file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] $type: $message\n", FILE_APPEND);
}

function load_one_csv($filename, $channel_type, $channel_id) {
    if (!file_exists($filename)) return [];
    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 2 && !empty(trim($row[0]))) {
                $data[] = [
                    'movie_name' => trim($row[0]),
                    'message_id_raw' => trim($row[1]),
                    'channel_type' => $channel_type,
                    'channel_id' => $channel_id,
                    'source_channel' => $channel_id,
                    'message_id' => is_numeric(trim($row[1])) ? intval(trim($row[1])) : null
                ];
            }
        }
        fclose($handle);
    }
    return $data;
}

function load_all_movies() {
    global $movie_messages;
    $all_movies = [];
    $movie_messages = [];
    $channels = [
        ['type' => 'main', 'id' => MAIN_CHANNEL_ID, 'file' => CSV_DIR . CSV_MAIN],
        ['type' => 'serial', 'id' => SERIAL_CHANNEL_ID, 'file' => CSV_DIR . CSV_SERIAL],
        ['type' => 'theater', 'id' => THEATER_CHANNEL_ID, 'file' => CSV_DIR . CSV_THEATER],
        ['type' => 'backup', 'id' => BACKUP_CHANNEL_ID, 'file' => CSV_DIR . CSV_BACKUP],
        ['type' => 'private', 'id' => PRIVATE_CHANNEL_1_ID, 'file' => CSV_DIR . CSV_PRIVATE1],
        ['type' => 'private', 'id' => PRIVATE_CHANNEL_2_ID, 'file' => CSV_DIR . CSV_PRIVATE2],
        ['type' => 'other', 'id' => '', 'file' => CSV_DIR . 'movies_other.csv']
    ];
    foreach ($channels as $ch) {
        $movies = load_one_csv($ch['file'], $ch['type'], $ch['id']);
        foreach ($movies as $movie) {
            $all_movies[] = $movie;
            $key = strtolower($movie['movie_name']);
            if (!isset($movie_messages[$key])) $movie_messages[$key] = [];
            $movie_messages[$key][] = $movie;
        }
    }
    return $all_movies;
}

function get_cached_movies() {
    global $movie_cache;
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    $movie_cache = ['data' => load_all_movies(), 'timestamp' => time()];
    return $movie_cache['data'];
}

function add_movie_to_csv($movie_name, $message_id, $channel_type, $channel_id = '') {
    $csv_file = get_csv_filename($channel_type, $channel_id);
    $entry = [trim($movie_name), trim($message_id)];
    $handle = fopen($csv_file, "a");
    if ($handle) {
        fputcsv($handle, $entry);
        fclose($handle);
        global $movie_cache;
        $movie_cache = [];
        bot_log("Movie added: $movie_name to $channel_type (ID: $message_id)");
        return true;
    }
    return false;
}

// ==============================
// TELEGRAM API FUNCTIONS
// ==============================
function apiRequest($method, $params = array(), $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    } else {
        $options = ['http' => ['method' => 'POST', 'content' => http_build_query($params), 'header' => "Content-Type: application/x-www-form-urlencoded\r\n"]];
        $context = stream_context_create($options);
        return @file_get_contents($url, false, $context);
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = ['chat_id' => $chat_id, 'text' => $text, 'disable_web_page_preview' => true];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    $data = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $new_text, 'disable_web_page_preview' => true];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    apiRequest('editMessageText', $data);
}

function deleteMessage($chat_id, $message_id) {
    apiRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $data = ['callback_query_id' => $callback_query_id, 'show_alert' => $show_alert];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('forwardMessage', ['chat_id' => $chat_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id]);
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('copyMessage', ['chat_id' => $chat_id, 'from_chat_id' => $from_chat_id, 'message_id' => $message_id]);
}

// ==============================
// MOVIE DELIVERY SYSTEM
// ==============================
function deliver_item_to_chat($chat_id, $item) {
    $source_channel = !empty($item['channel_id']) ? $item['channel_id'] : MAIN_CHANNEL_ID;
    $channel_type = $item['channel_type'] ?? 'main';
    $msg_id = null;
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $msg_id = $item['message_id'];
    } elseif (!empty($item['message_id_raw'])) {
        $msg_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($msg_id_clean) && $msg_id_clean > 0) $msg_id = $msg_id_clean;
    }
    if (!$msg_id) {
        $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n🎭 Channel: " . get_channel_display_name($channel_type) . "\n⚠️ Join: " . get_channel_username_link($channel_type);
        sendMessage($chat_id, $text, null, 'HTML');
        return false;
    }
    $is_private = ($channel_type == 'private');
    if ($is_private) {
        $result = json_decode(copyMessage($chat_id, $source_channel, $msg_id), true);
        if ($result && $result['ok']) return true;
        $fallback = json_decode(forwardMessage($chat_id, $source_channel, $msg_id), true);
        if ($fallback && $fallback['ok']) return true;
    } else {
        $result = json_decode(forwardMessage($chat_id, $source_channel, $msg_id), true);
        if ($result && $result['ok']) return true;
        $fallback = json_decode(copyMessage($chat_id, $source_channel, $msg_id), true);
        if ($fallback && $fallback['ok']) return true;
    }
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n🎭 Channel: " . get_channel_display_name($channel_type) . "\n⚠️ Join: " . get_channel_username_link($channel_type);
    sendMessage($chat_id, $text, null, 'HTML');
    return false;
}

// ==============================
// SEARCH SYSTEM
// ==============================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = [];
    $is_theater_search = false;
    $theater_keywords = ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hq', 'hdrip'];
    foreach ($theater_keywords as $keyword) {
        if (strpos($query_lower, $keyword) !== false) {
            $is_theater_search = true;
            $query_lower = str_replace($keyword, '', $query_lower);
            break;
        }
    }
    $query_lower = trim($query_lower);
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        foreach ($entries as $entry) {
            $entry_channel_type = $entry['channel_type'] ?? 'main';
            if ($is_theater_search && $entry_channel_type == 'theater') $score += 20;
            elseif (!$is_theater_search && $entry_channel_type == 'main') $score += 10;
            if (in_array($entry_channel_type, ['backup', 'private', 'serial'])) $score += 5;
        }
        if ($movie == $query_lower) $score = 100;
        elseif (strpos($movie, $query_lower) !== false) $score = 80 - (strlen($movie) - strlen($query_lower));
        else { similar_text($movie, $query_lower, $similarity); if ($similarity > 60) $score = $similarity; }
        if ($score > 0) {
            $channel_types = array_column($entries, 'channel_type');
            $results[$movie] = [
                'score' => $score, 'count' => count($entries), 'latest_entry' => end($entries),
                'qualities' => ['Unknown'], 'has_theater' => in_array('theater', $channel_types),
                'has_main' => in_array('main', $channel_types), 'has_serial' => in_array('serial', $channel_types),
                'has_backup' => in_array('backup', $channel_types), 'has_private' => in_array('private', $channel_types),
                'all_channels' => array_unique($channel_types)
            ];
        }
    }
    uasort($results, fn($a, $b) => $b['score'] - $a['score']);
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    $hindi_score = 0; $english_score = 0;
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए', 'कहाँ', 'कैसे', 'खोज', 'तलाश'];
    $english_keywords = ['movie', 'download', 'watch', 'print', 'search', 'find', 'looking', 'want', 'need'];
    foreach ($hindi_keywords as $k) if (strpos($text, $k) !== false) $hindi_score++;
    foreach ($english_keywords as $k) if (stripos($text, $k) !== false) $english_score++;
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) $hindi_score += 3;
    return $hindi_score > $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    $responses = [
        'hindi' => ['welcome' => "🎬 Boss, kis movie ki talash hai?", 'found' => "✅ Mil gayi!", 'not_found' => "😔 Movie not found!\n\n📝 Request: " . REQUEST_CHANNEL, 'searching' => "🔍 Searching...", 'request_success' => "✅ Request received!", 'request_limit' => "❌ Daily limit " . DAILY_REQUEST_LIMIT],
        'english' => ['welcome' => "🎬 Which movie?", 'found' => "✅ Found!", 'not_found' => "😔 Not found!\n\n📝 Request: " . REQUEST_CHANNEL, 'searching' => "🔍 Searching...", 'request_success' => "✅ Request received!", 'request_limit' => "❌ Daily limit " . DAILY_REQUEST_LIMIT]
    ];
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    $q = strtolower(trim($query));
    if (strlen($q) < 2) { sendMessage($chat_id, "❌ At least 2 characters"); return; }
    $found = smart_search($q);
    if (!empty($found)) {
        $msg = "🔍 Found " . count($found) . " movies:\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $channel_info = "";
            if ($data['has_theater']) $channel_info .= "🎭 ";
            if ($data['has_main']) $channel_info .= "🍿 ";
            if ($data['has_serial']) $channel_info .= "📺 ";
            if ($data['has_backup']) $channel_info .= "🔒 ";
            if ($data['has_private']) $channel_info .= "🔐 ";
            $msg .= "$i. $movie ($channel_info" . $data['count'] . " versions)\n";
            if (++$i > 10) break;
        }
        sendMessage($chat_id, $msg);
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        foreach ($top_movies as $movie) {
            $movie_data = $found[$movie];
            $channel_icon = '🍿';
            if ($movie_data['has_theater']) $channel_icon = '🎭';
            elseif ($movie_data['has_serial']) $channel_icon = '📺';
            elseif ($movie_data['has_backup']) $channel_icon = '🔒';
            elseif ($movie_data['has_private']) $channel_icon = '🔐';
            $keyboard['inline_keyboard'][] = [[ 'text' => $channel_icon . ucwords($movie), 'callback_data' => $movie ]];
        }
        $keyboard['inline_keyboard'][] = [[ 'text' => "📝 Request Different Movie", 'callback_data' => 'request_movie' ]];
        sendMessage($chat_id, "🚀 Top matches:", $keyboard);
    } else {
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        $request_keyboard = ['inline_keyboard' => [[['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]]]];
        sendMessage($chat_id, "💡 Click to request:", $request_keyboard);
    }
}

// ==============================
// REQUEST SYSTEM
// ==============================
function can_user_request($user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    $count = 0;
    foreach ($requests_data['requests'] ?? [] as $req) if ($req['user_id'] == $user_id && $req['date'] == $today) $count++;
    return $count < DAILY_REQUEST_LIMIT;
}

function add_movie_request($user_id, $movie_name, $language = 'hindi') {
    if (!can_user_request($user_id)) return false;
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $request_id = uniqid();
    $requests_data['requests'][] = ['id' => $request_id, 'user_id' => $user_id, 'movie_name' => $movie_name, 'language' => $language, 'date' => date('Y-m-d'), 'time' => date('H:i:s'), 'status' => 'pending'];
    $requests_data['user_request_count'][$user_id] = ($requests_data['user_request_count'][$user_id] ?? 0) + 1;
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    sendMessage(ADMIN_ID, "🎯 New Request: $movie_name by $user_id");
    bot_log("Movie request: $movie_name by $user_id");
    return true;
}

function show_user_requests($chat_id, $user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = array_filter($requests_data['requests'] ?? [], fn($r) => $r['user_id'] == $user_id);
    if (empty($user_requests)) { sendMessage($chat_id, "📭 No requests."); return; }
    $message = "📝 Your Requests:\n\n";
    $i = 1;
    foreach (array_slice($user_requests, 0, 10) as $r) {
        $status = $r['status'] == 'completed' ? '✅' : '⏳';
        $message .= "$i. $status {$r['movie_name']} ({$r['date']})\n";
        $i++;
    }
    sendMessage($chat_id, $message);
}

function show_pending_requests($chat_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $pending = array_filter($requests_data['requests'] ?? [], fn($r) => $r['status'] == 'pending');
    if (empty($pending)) { sendMessage($chat_id, "📭 No pending."); return; }
    $msg = "⏳ Pending (" . count($pending) . "):\n\n";
    $i = 1;
    foreach ($pending as $r) {
        $msg .= "$i. {$r['movie_name']} - {$r['user_id']} ({$r['date']})\n";
        if (++$i > 20) break;
    }
    sendMessage($chat_id, $msg);
}

function bulk_approve_requests($chat_id, $count) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $approved = 0;
    foreach ($requests_data['requests'] as &$r) {
        if ($r['status'] == 'pending' && $approved < $count) {
            $r['status'] = 'completed';
            $approved++;
        }
    }
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    sendMessage($chat_id, "✅ Approved $approved requests.");
    return $approved;
}

// ==============================
// PAGINATION (simplified)
// ==============================
function paginate_movies(array $all, int $page, array $filters = []): array {
    if (!empty($filters)) $all = array_filter($all, fn($m) => ($m['channel_type'] ?? 'main') == ($filters['channel_type'] ?? ''));
    $all = array_values($all);
    $total = count($all);
    if ($total === 0) return ['total' => 0, 'total_pages' => 1, 'page' => 1, 'slice' => [], 'has_next' => false, 'has_prev' => false];
    $total_pages = ceil($total / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    return ['total' => $total, 'total_pages' => $total_pages, 'page' => $page, 'slice' => array_slice($all, $start, ITEMS_PER_PAGE), 'has_next' => $page < $total_pages, 'has_prev' => $page > 1];
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_cached_movies();
    if (empty($all)) { sendMessage($chat_id, "📭 No movies."); return; }
    $pg = paginate_movies($all, $page, $filters);
    $msg = "🎬 Movie Browser\nTotal: {$pg['total']} | Page {$pg['page']}/{$pg['total_pages']}\n\n";
    $i = ($pg['page'] - 1) * ITEMS_PER_PAGE + 1;
    foreach ($pg['slice'] as $m) {
        $msg .= "$i. " . get_channel_display_name($m['channel_type'] ?? 'main') . " {$m['movie_name']}\n";
        $i++;
    }
    $kb = ['inline_keyboard' => []];
    if ($pg['has_prev']) $kb['inline_keyboard'][] = [['text' => '◀️ Prev', 'callback_data' => "page_" . ($page - 1)]];
    if ($pg['has_next']) $kb['inline_keyboard'][] = [['text' => 'Next ▶️', 'callback_data' => "page_" . ($page + 1)]];
    if (!empty($kb['inline_keyboard'])) sendMessage($chat_id, $msg, $kb);
    else sendMessage($chat_id, $msg);
}

// ==============================
// CHANNEL INFO COMMANDS
// ==============================
function show_channel_info($chat_id) {
    $msg = "📢 Join:\n🍿 Main: @EntertainmentTadka786\n📺 Serial: @Entertainment_Tadka_Serial_786\n📥 Requests: @EntertainmentTadka7860\n🎭 Theater: @threater_print_movies\n🔒 Backup: @ETBackup";
    $kb = ['inline_keyboard' => [
        [['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'], ['text' => '📺 Serial', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']],
        [['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860'], ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies']],
        [['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']]
    ]];
    sendMessage($chat_id, $msg, $kb);
}

function show_main_channel_info($chat_id) { sendMessage($chat_id, "🍿 Main: @EntertainmentTadka786\nhttps://t.me/EntertainmentTadka786"); }
function show_request_channel_info($chat_id) { sendMessage($chat_id, "📥 Requests: @EntertainmentTadka7860\nhttps://t.me/EntertainmentTadka7860"); }
function show_theater_channel_info($chat_id) { sendMessage($chat_id, "🎭 Theater: @threater_print_movies\nhttps://t.me/threater_print_movies"); }
function show_backup_channel_info($chat_id) { sendMessage($chat_id, "🔒 Backup: @ETBackup\nhttps://t.me/ETBackup"); }

function show_csv_data($chat_id, $show_all = false) {
    $movies = get_cached_movies();
    if (empty($movies)) { sendMessage($chat_id, "📊 No movies."); return; }
    $limit = $show_all ? count($movies) : 10;
    $movies = array_slice(array_reverse($movies), 0, $limit);
    $msg = "📊 Movies (" . count($movies) . "):\n\n";
    $i = 1;
    foreach ($movies as $m) {
        $msg .= "$i. " . get_channel_display_name($m['channel_type'] ?? 'main') . " {$m['movie_name']} (ID: {$m['message_id_raw']})\n";
        if (++$i > 20) break;
    }
    sendMessage($chat_id, $msg);
}

function is_valid_movie_query($text) {
    $text = strtolower(trim($text));
    if (strpos($text, '/') === 0) return true;
    if (strlen($text) < 3) return false;
    $invalid = ['good morning', 'hello', 'hi', 'thanks', 'ok', 'help', 'problem'];
    foreach ($invalid as $v) if (strpos($text, $v) !== false) return false;
    return true;
}

// ==============================
// COMMAND HANDLER
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    switch ($command) {
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n📢 How to use:\n• Type movie name\n• Use English or Hindi\n\n🔍 Examples:\n• Zebra 2024\n• Mandala Murders\n• Show Time 2025\n\n📢 Join:\n🍿 Main: @EntertainmentTadka786\n📺 Serial: @Entertainment_Tadka_Serial_786\n📥 Requests: @EntertainmentTadka7860\n🎭 Theater: @threater_print_movies\n🔒 Backup: @ETBackup";
            $kb = ['inline_keyboard' => [
                [['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''], ['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786']],
                [['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860'], ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies']]
            ]];
            sendMessage($chat_id, $welcome, $kb);
            break;
        case '/help':
            sendMessage($chat_id, "Commands:\n/search <movie>\n/request <movie>\n/myrequests\n/pending_request (admin)\n/bulk_approve <count> (admin)\n/totaluploads\n/channel\n/checkcsv");
            break;
        case '/search':
            $movie = implode(' ', $params);
            if (empty($movie)) { sendMessage($chat_id, "Usage: /search movie"); return; }
            send_multilingual_response($chat_id, 'searching', detect_language($movie));
            advanced_search($chat_id, $movie, $user_id);
            break;
        case '/request':
            $movie = implode(' ', $params);
            if (empty($movie)) { sendMessage($chat_id, "Usage: /request movie"); return; }
            if (add_movie_request($user_id, $movie, detect_language($movie))) send_multilingual_response($chat_id, 'request_success', 'english');
            else send_multilingual_response($chat_id, 'request_limit', 'english');
            break;
        case '/myrequests':
            show_user_requests($chat_id, $user_id);
            break;
        case '/pending_request':
            if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "Admin only."); return; }
            show_pending_requests($chat_id);
            break;
        case '/bulk_approve':
            if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "Admin only."); return; }
            $count = isset($params[0]) ? intval($params[0]) : 1;
            bulk_approve_requests($chat_id, max(1, $count));
            break;
        case '/totaluploads':
            totalupload_controller($chat_id, isset($params[0]) ? intval($params[0]) : 1);
            break;
        case '/channel':
            show_channel_info($chat_id);
            break;
        case '/mainchannel':
            show_main_channel_info($chat_id);
            break;
        case '/requestchannel':
            show_request_channel_info($chat_id);
            break;
        case '/theaterchannel':
            show_theater_channel_info($chat_id);
            break;
        case '/checkcsv':
            show_csv_data($chat_id, isset($params[0]) && strtolower($params[0]) == 'all');
            break;
        default:
            sendMessage($chat_id, "Unknown command. Use /help");
    }
}

// ==============================
// WEBHOOK HANDLER
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_CONTENT_TYPE']) && strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);
    
    if ($update) {
        // Channel posts
        if (isset($update['channel_post'])) {
            $msg = $update['channel_post'];
            $chat_id = $msg['chat']['id'];
            $channel_type = get_channel_type_by_id($chat_id);
            if ($channel_type != 'other' && $channel_type != 'request') {
                $movie_name = $msg['caption'] ?? $msg['text'] ?? $msg['document']['file_name'] ?? 'Unknown';
                if (!empty(trim($movie_name))) {
                    $csv_file = get_csv_filename($channel_type, $chat_id);
                    $entry = [trim($movie_name), $msg['message_id']];
                    $handle = fopen($csv_file, "a");
                    if ($handle) { fputcsv($handle, $entry); fclose($handle); global $movie_cache; $movie_cache = []; bot_log("Auto-indexed: $movie_name"); }
                }
            }
            exit;
        }
        
        // Messages
        if (isset($update['message'])) {
            $msg = $update['message'];
            $chat_id = $msg['chat']['id'];
            $user_id = $msg['from']['id'];
            $text = $msg['text'] ?? '';
            $chat_type = $msg['chat']['type'] ?? 'private';
            
            if ($chat_type != 'private' && strpos($text, '/') !== 0 && !is_valid_movie_query($text)) exit;
            
            if (strpos($text, '/') === 0) {
                $parts = explode(' ', $text);
                $command = strtolower($parts[0]);
                $params = array_slice($parts, 1);
                handle_command($chat_id, $user_id, $command, $params);
            } elseif (!empty(trim($text))) {
                send_multilingual_response($chat_id, 'searching', detect_language($text));
                advanced_search($chat_id, $text, $user_id);
            }
        }
        
        // Callback queries
        if (isset($update['callback_query'])) {
            $q = $update['callback_query'];
            $chat_id = $q['message']['chat']['id'];
            $data = $q['data'];
            global $movie_messages;
            
            if (strpos($data, 'page_') === 0) {
                $page = intval(str_replace('page_', '', $data));
                totalupload_controller($chat_id, $page);
            } elseif (isset($movie_messages[strtolower($data)])) {
                foreach ($movie_messages[strtolower($data)] as $entry) {
                    deliver_item_to_chat($chat_id, $entry);
                    usleep(200000);
                }
                sendMessage($chat_id, "✅ '$data' info sent!");
            } elseif ($data == 'request_movie') {
                sendMessage($chat_id, "Use /request movie_name");
            } elseif (strpos($data, 'auto_request_') === 0) {
                $movie = base64_decode(str_replace('auto_request_', '', $data));
                if (add_movie_request($q['from']['id'], $movie, detect_language($movie))) sendMessage($chat_id, "✅ Request sent!");
                else sendMessage($chat_id, "❌ Daily limit reached!");
            }
            answerCallbackQuery($q['id']);
        }
    }
    exit;
}

// ==============================
// ADMIN PANEL
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    if ($_POST['admin_pass'] === ADMIN_PASSWORD) { $_SESSION['admin_logged_in'] = true; header('Location: ?'); exit; }
    else { header('Location: ?error=1'); exit; }
}

if (isset($_GET['logout'])) { session_destroy(); header('Location: ?'); exit; }

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (isset($_GET['approve'])) {
        $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
        foreach ($requests_data['requests'] as &$r) if ($r['id'] == $_GET['approve'] && $r['status'] == 'pending') $r['status'] = 'completed';
        file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
        header('Location: ?'); exit;
    }
    if (isset($_POST['bulk_approve'])) {
        $count = intval($_POST['bulk_count']);
        if ($count > 0) bulk_approve_requests(0, $count);
        header('Location: ?'); exit;
    }
    if (isset($_POST['add_movie'])) {
        $channel = $_POST['channel_type'];
        if ($channel == 'private2') $channel = 'private';
        add_movie_to_csv($_POST['movie_name'], $_POST['message_id'], $channel, $channel == 'private' ? PRIVATE_CHANNEL_1_ID : '');
        header('Location: ?'); exit;
    }
    if (isset($_GET['view_all'])) {
        $movies = get_cached_movies();
        echo "<h1>All Movies (" . count($movies) . ")</h1><a href='?'>Back</a><ul>";
        foreach ($movies as $m) echo "<li>" . get_channel_display_name($m['channel_type']) . " {$m['movie_name']} (ID: {$m['message_id_raw']})</li>";
        echo "</ul>"; exit;
    }
    
    // Dashboard
    $movies = get_cached_movies();
    $requests = json_decode(file_get_contents(REQUEST_FILE), true);
    $pending = count(array_filter($requests['requests'] ?? [], fn($r) => $r['status'] == 'pending'));
    echo "<h1>Admin Panel</h1>
    <p>Total Movies: " . count($movies) . "</p>
    <p>Pending Requests: $pending</p>
    <hr>
    <h2>Add Movie</h2>
    <form method='post'>
        <input name='movie_name' placeholder='Movie Name' required>
        <input name='message_id' placeholder='Message ID' required>
        <select name='channel_type'>
            <option value='main'>Main</option>
            <option value='serial'>Serial</option>
            <option value='theater'>Theater</option>
            <option value='backup'>Backup</option>
            <option value='private'>Private 1</option>
            <option value='private2'>Private 2</option>
        </select>
        <button type='submit' name='add_movie'>Add</button>
    </form>
    <hr>
    <h2>Pending Requests</h2>";
    if ($pending > 0) {
        foreach ($requests['requests'] as $r) if ($r['status'] == 'pending') echo "<p><b>{$r['movie_name']}</b> by {$r['user_id']} <a href='?approve={$r['id']}'>Approve</a></p>";
        echo "<form method='post'><input name='bulk_count' placeholder='Count'><button name='bulk_approve'>Bulk Approve</button></form>";
    } else echo "<p>No pending requests.</p>";
    echo "<hr><a href='?view_all=1'>View All Movies</a> | <a href='?logout=1'>Logout</a>";
    exit;
}

// Default page
echo "<h1>Entertainment Tadka Bot</h1><p>Bot is running.</p><p>Total Movies: " . count(get_cached_movies()) . "</p><p><a href='?setwebhook=1'>Set Webhook</a></p>";
