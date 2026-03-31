<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Web pe error mat dikhao
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Create log file with write permission
if (!file_exists('php_error.log')) {
    file_put_contents('php_error.log', "[" . date('Y-m-d H:i:s') . "] Log started\n");
    chmod('php_error.log', 0666);
}

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

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================

$port = getenv('PORT') ?: '80';
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') ?: 'admin123');

// Public Channels (only these will be displayed to users)
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

// Private Channels (not displayed, only internal)
define('PRIVATE_CHANNEL_1_ID', '-1003251791991');
define('PRIVATE_CHANNEL_2_ID', '-1002337293281');

// Admin
define('ADMIN_ID', (int)getenv('ADMIN_ID'));

// File paths - Separate CSV per channel
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
// HELPER: GET CSV FILENAME BASED ON CHANNEL
// ==============================
function get_csv_filename($channel_type, $channel_id = '') {
    if (!file_exists(CSV_DIR)) {
        mkdir(CSV_DIR, 0777, true);
    }
    switch ($channel_type) {
        case 'main':
            return CSV_DIR . CSV_MAIN;
        case 'serial':
            return CSV_DIR . CSV_SERIAL;
        case 'theater':
            return CSV_DIR . CSV_THEATER;
        case 'backup':
            return CSV_DIR . CSV_BACKUP;
        case 'private':
            if ($channel_id == PRIVATE_CHANNEL_1_ID) return CSV_DIR . CSV_PRIVATE1;
            if ($channel_id == PRIVATE_CHANNEL_2_ID) return CSV_DIR . CSV_PRIVATE2;
            return CSV_DIR . 'movies_private_other.csv';
        default:
            return CSV_DIR . 'movies_other.csv';
    }
}

// ==============================
// CHANNEL MAPPING FUNCTIONS
// ==============================
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

// ==============================
// FILE INITIALIZATION
// ==============================
function initialize_files() {
    if (!file_exists(CSV_DIR)) {
        mkdir(CSV_DIR, 0777, true);
    }
    
    $csv_files = [
        CSV_DIR . CSV_MAIN,
        CSV_DIR . CSV_SERIAL,
        CSV_DIR . CSV_THEATER,
        CSV_DIR . CSV_BACKUP,
        CSV_DIR . CSV_PRIVATE1,
        CSV_DIR . CSV_PRIVATE2,
        CSV_DIR . 'movies_other.csv'
    ];
    
    $header = "movie_name,message_id\n";
    foreach ($csv_files as $file) {
        if (!file_exists($file)) {
            file_put_contents($file, $header);
            @chmod($file, 0666);
        }
    }
    
    if (!file_exists(REQUEST_FILE)) {
        file_put_contents(REQUEST_FILE, json_encode([
            'requests' => [],
            'pending_approval' => [],
            'completed_requests' => [],
            'user_request_count' => []
        ], JSON_PRETTY_PRINT));
    }
    
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
    }
}
initialize_files();

// ==============================
// LOGGING
// ==============================
function bot_log($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $type: $message\n", FILE_APPEND);
}

// ==============================
// LOAD A SINGLE CSV
// ==============================
function load_one_csv($filename, $channel_type, $channel_id) {
    if (!file_exists($filename)) return [];
    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle); // skip header
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 2 && !empty(trim($row[0]))) {
                $movie_name = trim($row[0]);
                $message_id_raw = trim($row[1]);
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'channel_type' => $channel_type,
                    'channel_id' => $channel_id,
                    'source_channel' => $channel_id
                ];
                if (is_numeric($message_id_raw)) {
                    $entry['message_id'] = intval($message_id_raw);
                } else {
                    $entry['message_id'] = null;
                }
                $data[] = $entry;
            }
        }
        fclose($handle);
    }
    return $data;
}

// ==============================
// LOAD ALL MOVIES
// ==============================
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

// ==============================
// CACHING
// ==============================
function get_cached_movies() {
    global $movie_cache;
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    $movie_cache = [
        'data' => load_all_movies(),
        'timestamp' => time()
    ];
    return $movie_cache['data'];
}

// ==============================
// ADD MOVIE TO CSV (helper for admin panel)
// ==============================
function add_movie_to_csv($movie_name, $message_id, $channel_type, $channel_id = '') {
    $csv_file = get_csv_filename($channel_type, $channel_id);
    $entry = [trim($movie_name), trim($message_id)];
    $handle = fopen($csv_file, "a");
    if ($handle) {
        fputcsv($handle, $entry);
        fclose($handle);
        global $movie_cache;
        $movie_cache = [];
        bot_log("Movie added via admin panel: $movie_name to $channel_type (ID: $message_id)");
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
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($res === false) {
            $error = curl_error($ch);
            bot_log("CURL ERROR: $error", 'ERROR');
            file_put_contents(LOG_FILE, "[ERROR] CURL: $error\n", FILE_APPEND);
        } else {
            bot_log("API Response: $res", 'DEBUG');
        }
        curl_close($ch);
        return $res;
    } else {
        $postData = http_build_query($params);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => $postData,
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            $error = error_get_last();
            bot_log("API Request failed for method: $method - " . ($error['message'] ?? 'Unknown'), 'ERROR');
            file_put_contents(LOG_FILE, "[ERROR] API: $method failed\n", FILE_APPEND);
        } else {
            bot_log("API Response: $result", 'DEBUG');
        }
        return $result;
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    apiRequest('editMessageText', $data);
}

function deleteMessage($chat_id, $message_id) {
    apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $data = ['callback_query_id' => $callback_query_id, 'show_alert' => $show_alert];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

// ==============================
// MOVIE DELIVERY SYSTEM
// ==============================
function deliver_item_to_chat($chat_id, $item) {
    if (!isset($item['channel_id']) || empty($item['channel_id'])) {
        $source_channel = MAIN_CHANNEL_ID;
        bot_log("Channel ID not found for movie: {$item['movie_name']}, using default", 'WARNING');
    } else {
        $source_channel = $item['channel_id'];
    }
    $channel_type = isset($item['channel_type']) ? $item['channel_type'] : 'main';

    $msg_id = null;
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        $msg_id = $item['message_id'];
    } elseif (!empty($item['message_id_raw'])) {
        $msg_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($msg_id_clean) && $msg_id_clean > 0) {
            $msg_id = $msg_id_clean;
        }
    }

    if (!$msg_id) {
        $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
        $text .= "🎭 Channel: " . get_channel_display_name($channel_type) . "\n";
        $text .= "⚠️ Join channel to access: " . get_channel_username_link($channel_type);
        sendMessage($chat_id, $text, null, 'HTML');
        return false;
    }

    $is_private = ($channel_type == 'private');
    
    if ($is_private) {
        $result = json_decode(copyMessage($chat_id, $source_channel, $msg_id), true);
        if ($result && $result['ok']) {
            bot_log("Movie COPIED (no header) from private: {$item['movie_name']} to $chat_id");
            return true;
        } else {
            $fallback = json_decode(forwardMessage($chat_id, $source_channel, $msg_id), true);
            if ($fallback && $fallback['ok']) {
                bot_log("Movie FORWARDED (fallback) from private: {$item['movie_name']} to $chat_id");
                return true;
            }
        }
    } else {
        $result = json_decode(forwardMessage($chat_id, $source_channel, $msg_id), true);
        if ($result && $result['ok']) {
            bot_log("Movie FORWARDED (header on) from $channel_type: {$item['movie_name']} to $chat_id");
            return true;
        } else {
            $fallback = json_decode(copyMessage($chat_id, $source_channel, $msg_id), true);
            if ($fallback && $fallback['ok']) {
                bot_log("Movie COPIED (fallback) from $channel_type: {$item['movie_name']} to $chat_id");
                return true;
            }
        }
    }

    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "🎭 Channel: " . get_channel_display_name($channel_type) . "\n";
    $text .= "⚠️ Join channel: " . get_channel_username_link($channel_type);
    sendMessage($chat_id, $text, null, 'HTML');
    return false;
}

// ==============================
// SEARCH SYSTEM
// ==============================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
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
            if (in_array($entry_channel_type, ['backup', 'private', 'serial', 'request'])) $score += 5;
        }
        if ($movie == $query_lower) $score = 100;
        elseif (strpos($movie, $query_lower) !== false) $score = 80 - (strlen($movie) - strlen($query_lower));
        else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        if ($score > 0) {
            $channel_types = array_column($entries, 'channel_type');
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'qualities' => ['Unknown'],
                'has_theater' => in_array('theater', $channel_types),
                'has_main' => in_array('main', $channel_types),
                'has_serial' => in_array('serial', $channel_types),
                'has_backup' => in_array('backup', $channel_types),
                'has_private' => in_array('private', $channel_types),
                'all_channels' => array_unique($channel_types)
            ];
        }
    }
    uasort($results, function($a, $b) { return $b['score'] - $a['score']; });
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए', 'कहाँ', 'कैसे', 'खोज', 'तलाश'];
    $english_keywords = ['movie', 'download', 'watch', 'print', 'search', 'find', 'looking', 'want', 'need'];
    $hindi_score = 0;
    $english_score = 0;
    foreach ($hindi_keywords as $k) if (strpos($text, $k) !== false) $hindi_score++;
    foreach ($english_keywords as $k) if (stripos($text, $k) !== false) $english_score++;
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) $hindi_score += 3;
    return $hindi_score > $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    $responses = [
        'hindi' => [
            'welcome' => "🎬 Boss, kis movie ki talash hai?",
            'found' => "✅ Mil gayi! Movie info bhej raha hoon...",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain: " . REQUEST_CHANNEL,
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'multiple_found' => "🎯 Kai versions mili hain! Aap konsi chahte hain?",
            'request_success' => "✅ Request receive ho gayi! Hum jald hi add karenge.",
            'request_limit' => "❌ Aaj ke liye aap maximum " . DAILY_REQUEST_LIMIT . " requests hi kar sakte hain."
        ],
        'english' => [
            'welcome' => "🎬 Boss, which movie are you looking for?",
            'found' => "✅ Found it! Sending movie info...",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it here: " . REQUEST_CHANNEL,
            'searching' => "🔍 Searching... Please wait",
            'multiple_found' => "🎯 Multiple versions found! Which one do you want?",
            'request_success' => "✅ Request received! We'll add it soon.",
            'request_limit' => "❌ You've reached the daily limit of " . DAILY_REQUEST_LIMIT . " requests."
        ]
    ];
    sendMessage($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages;
    $q = strtolower(trim($query));
    if (strlen($q) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters");
        return;
    }
    $invalid_keywords = [
        'vlc', 'audio', 'track', 'change', 'open', 'kar', 'me', 'hai',
        'how', 'what', 'problem', 'issue', 'help', 'solution', 'fix',
        'error', 'not working', 'download', 'play', 'video', 'sound',
        'subtitle', 'quality', 'hd', 'full', 'part', 'scene',
        'hi', 'hello', 'hey', 'good', 'morning', 'night', 'bye',
        'thanks', 'thank', 'ok', 'okay', 'yes', 'no', 'maybe',
        'who', 'when', 'where', 'why', 'how', 'can', 'should',
        'kaise', 'kya', 'kahan', 'kab', 'kyun', 'kon', 'kisne',
        'hai', 'hain', 'ho', 'raha', 'raha', 'rah', 'tha', 'thi',
        'mere', 'apne', 'tumhare', 'hamare', 'sab', 'log', 'group'
    ];
    $query_words = explode(' ', $q);
    $total_words = count($query_words);
    $invalid_count = 0;
    foreach ($query_words as $word) {
        if (in_array($word, $invalid_keywords)) $invalid_count++;
    }
    if ($invalid_count > 0 && ($invalid_count / $total_words) > 0.5) {
        $help_msg = "🎬 Please enter a movie name!\n\n🔍 Examples: kgf, pushpa, avengers, hindi movie\n\n📢 Join: " . MAIN_CHANNEL;
        sendMessage($chat_id, $help_msg, null, 'HTML');
        return;
    }
    $movie_pattern = '/^[a-zA-Z0-9\s\-\.\,\&\+\(\)\:\'\"]+$/';
    if (!preg_match($movie_pattern, $query)) {
        sendMessage($chat_id, "❌ Invalid movie name format.");
        return;
    }
    $found = smart_search($q);
    if (!empty($found)) {
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $channel_info = "";
            if ($data['has_theater']) $channel_info .= "🎭 ";
            if ($data['has_main']) $channel_info .= "🍿 ";
            if ($data['has_serial']) $channel_info .= "📺 ";
            if ($data['has_backup']) $channel_info .= "🔒 ";
            if ($data['has_private']) $channel_info .= "🔐 ";
            $msg .= "$i. $movie ($channel_info" . $data['count'] . " versions)\n";
            $i++;
            if ($i > 10) break;
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
        sendMessage($chat_id, "🚀 Top matches (click for info):", $keyboard);
    } else {
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        sendMessage($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard);
    }
}

// ==============================
// REQUEST SYSTEM
// ==============================
function can_user_request($user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    $user_requests_today = 0;
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id && $request['date'] == $today) $user_requests_today++;
    }
    return $user_requests_today < DAILY_REQUEST_LIMIT;
}

function add_movie_request($user_id, $movie_name, $language = 'hindi') {
    if (!can_user_request($user_id)) return false;
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $request_id = uniqid();
    $requests_data['requests'][] = [
        'id' => $request_id,
        'user_id' => $user_id,
        'movie_name' => $movie_name,
        'language' => $language,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'status' => 'pending'
    ];
    if (!isset($requests_data['user_request_count'][$user_id])) $requests_data['user_request_count'][$user_id] = 0;
    $requests_data['user_request_count'][$user_id]++;
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    $admin_msg = "🎯 New Movie Request\n\n🎬 Movie: $movie_name\n🗣️ Language: $language\n👤 User ID: $user_id\n📅 Date: " . date('Y-m-d H:i:s');
    sendMessage(ADMIN_ID, $admin_msg);
    bot_log("Movie request added: $movie_name by $user_id");
    return true;
}

function show_user_requests($chat_id, $user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id) $user_requests[] = $request;
    }
    if (empty($user_requests)) {
        sendMessage($chat_id, "📭 Aapne abhi tak koi movie request nahi ki hai!");
        return;
    }
    $message = "📝 <b>Your Movie Requests</b>\n\n";
    $i = 1;
    foreach (array_slice($user_requests, 0, 10) as $request) {
        $status_emoji = $request['status'] == 'completed' ? '✅' : '⏳';
        $message .= "$i. $status_emoji <b>" . htmlspecialchars($request['movie_name']) . "</b>\n";
        $message .= "   📅 " . $request['date'] . " | 🗣️ " . ucfirst($request['language']) . "\n";
        $message .= "   🆔 " . $request['id'] . "\n\n";
        $i++;
    }
    $pending_count = count(array_filter($user_requests, function($req) { return $req['status'] == 'pending'; }));
    $message .= "📊 <b>Summary:</b>\n• Total Requests: " . count($user_requests) . "\n• Pending: $pending_count\n• Completed: " . (count($user_requests) - $pending_count);
    sendMessage($chat_id, $message, null, 'HTML');
}

function show_pending_requests($chat_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $pending = array_filter($requests_data['requests'] ?? [], function($req) { return $req['status'] == 'pending'; });
    if (empty($pending)) {
        sendMessage($chat_id, "📭 No pending requests.");
        return;
    }
    $msg = "⏳ <b>Pending Requests (" . count($pending) . ")</b>\n\n";
    $i = 1;
    foreach ($pending as $req) {
        $msg .= "$i. <b>" . htmlspecialchars($req['movie_name']) . "</b> - User: {$req['user_id']} ({$req['date']})\n";
        $i++;
        if ($i > 20) { $msg .= "\n... and more"; break; }
    }
    sendMessage($chat_id, $msg, null, 'HTML');
}

function bulk_approve_requests($chat_id, $count) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $pending_keys = [];
    foreach ($requests_data['requests'] as $idx => $req) {
        if ($req['status'] == 'pending') $pending_keys[] = $idx;
        if (count($pending_keys) >= $count) break;
    }
    $approved = 0;
    foreach ($pending_keys as $idx) {
        $requests_data['requests'][$idx]['status'] = 'completed';
        $approved++;
    }
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    sendMessage($chat_id, "✅ Approved $approved request(s).");
    bot_log("Admin bulk approved $approved requests");
    return $approved;
}

// ==============================
// PAGINATION SYSTEM
// ==============================
function paginate_movies(array $all, int $page, array $filters = []): array {
    if (!empty($filters)) {
        $all = array_filter($all, function($movie) use ($filters) {
            foreach ($filters as $key => $value) {
                if ($key == 'channel_type' && ($movie['channel_type'] ?? 'main') != $value) return false;
            }
            return true;
        });
        $all = array_values($all);
    }
    $total = count($all);
    if ($total === 0) return ['total' => 0, 'total_pages' => 1, 'page' => 1, 'slice' => [], 'filters' => $filters, 'has_next' => false, 'has_prev' => false, 'start_item' => 0, 'end_item' => 0];
    $total_pages = (int)ceil($total / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    return [
        'total' => $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'slice' => array_slice($all, $start, ITEMS_PER_PAGE),
        'filters' => $filters,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1,
        'start_item' => $start + 1,
        'end_item' => min($start + ITEMS_PER_PAGE, $total)
    ];
}

function build_totalupload_keyboard(int $page, int $total_pages, string $session_id = '', array $filters = []): array {
    $kb = ['inline_keyboard' => []];
    $nav_row = [];
    if ($page > 1) {
        $nav_row[] = ['text' => '⏪', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
    }
    $start_page = max(1, $page - 3);
    $end_page = min($total_pages, $start_page + 6);
    if ($end_page - $start_page < 6) $start_page = max(1, $end_page - 6);
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $page) $nav_row[] = ['text' => "【{$i}】", 'callback_data' => 'current'];
        else $nav_row[] = ['text' => "{$i}", 'callback_data' => 'pag_' . $i . '_' . $session_id];
    }
    if ($page < $total_pages) {
        $nav_row[] = ['text' => '▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
        $nav_row[] = ['text' => '⏩', 'callback_data' => 'pag_last_' . $total_pages . '_' . $session_id];
    }
    if (!empty($nav_row)) $kb['inline_keyboard'][] = $nav_row;
    $action_row = [];
    $action_row[] = ['text' => '📥 Send Page', 'callback_data' => 'send_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '👁️ Preview', 'callback_data' => 'prev_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'stats_' . $session_id];
    $kb['inline_keyboard'][] = $action_row;
    if (empty($filters)) {
        $filter_row = [];
        $filter_row[] = ['text' => '🎬 HD Only', 'callback_data' => 'flt_hd_' . $session_id];
        $filter_row[] = ['text' => '🎭 Theater Only', 'callback_data' => 'flt_theater_' . $session_id];
        $filter_row[] = ['text' => '🔒 Backup Only', 'callback_data' => 'flt_backup_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    } else {
        $filter_row = [];
        $filter_row[] = ['text' => '🧹 Clear Filter', 'callback_data' => 'flt_clr_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    }
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '💾 Save', 'callback_data' => 'save_' . $session_id];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    $kb['inline_keyboard'][] = $ctrl_row;
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    $all = get_cached_movies();
    if (empty($all)) {
        sendMessage($chat_id, "📭 Koi movies nahi mili! Pehle kuch movies add karo.");
        return;
    }
    if (!$session_id) $session_id = uniqid('sess_', true);
    $pg = paginate_movies($all, (int)$page, $filters);
    if ($page == 1 && PREVIEW_ITEMS > 0 && count($pg['slice']) > 0) {
        $preview_msg = "👁️ <b>Quick Preview (First " . PREVIEW_ITEMS . "):</b>\n\n";
        $preview_count = min(PREVIEW_ITEMS, count($pg['slice']));
        for ($i = 0; $i < $preview_count; $i++) {
            $movie = $pg['slice'][$i];
            $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
            $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n\n";
        }
        sendMessage($chat_id, $preview_msg, null, 'HTML');
    }
    $title = "🎬 <b>Movie Browser</b>\n\n";
    $title .= "🆔 <b>Session:</b> <code>" . substr($session_id, 0, 8) . "</code>\n";
    $title .= "📊 <b>Total Movies:</b> <b>{$pg['total']}</b>\n";
    $title .= "📄 <b>Page:</b> {$pg['page']}/{$pg['total_pages']} (Items {$pg['start_item']}-{$pg['end_item']})\n";
    if (!empty($filters)) $title .= "• Filters: <b>" . count($filters) . " active</b>\n";
    $title .= "\n📋 <b>Page {$page} Movies:</b>\n\n";
    $i = $pg['start_item'];
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $title .= "<b>{$i}.</b> $channel_icon {$movie_name}\n\n";
        $i++;
    }
    $title .= "📍 Use number buttons for direct page access\n🔧 Apply filters using buttons below";
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    delete_pagination_message($chat_id, $session_id);
    $result = sendMessage($chat_id, $title, $kb, 'HTML');
    save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
    bot_log("Pagination - Chat: $chat_id, Page: $page");
}

function save_pagination_message($chat_id, $session_id, $message_id) {
    global $user_pagination_sessions;
    if (!isset($user_pagination_sessions[$session_id])) $user_pagination_sessions[$session_id] = [];
    $user_pagination_sessions[$session_id]['last_message_id'] = $message_id;
    $user_pagination_sessions[$session_id]['chat_id'] = $chat_id;
    $user_pagination_sessions[$session_id]['last_updated'] = time();
}

function delete_pagination_message($chat_id, $session_id) {
    global $user_pagination_sessions;
    if (isset($user_pagination_sessions[$session_id]) && isset($user_pagination_sessions[$session_id]['last_message_id'])) {
        deleteMessage($chat_id, $user_pagination_sessions[$session_id]['last_message_id']);
    }
}

function batch_download_with_progress($chat_id, $movies, $page_num) {
    $total = count($movies);
    if ($total === 0) return;
    $progress_msg = sendMessage($chat_id, "📦 <b>Batch Info Started</b>\n\nPage: {$page_num}\nTotal: {$total} movies\n\n⏳ Initializing...");
    $progress_id = $progress_msg['result']['message_id'];
    $success = 0;
    $failed = 0;
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        if ($i % 2 == 0) {
            $progress = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, "📦 <b>Sending Page {$page_num} Info</b>\n\nProgress: {$progress}%\nProcessed: {$i}/{$total}\n✅ Success: {$success}\n❌ Failed: {$failed}\n\n⏳ Please wait...");
        }
        try {
            if (deliver_item_to_chat($chat_id, $movie)) $success++;
            else $failed++;
        } catch (Exception $e) { $failed++; }
        usleep(500000);
    }
    editMessage($chat_id, $progress_id, "✅ <b>Batch Info Complete</b>\n\n📄 Page: {$page_num}\n🎬 Total: {$total} movies\n✅ Successfully sent: {$success}\n❌ Failed: {$failed}\n\n📊 Success rate: " . round(($success / $total) * 100, 2) . "%");
}

// ==============================
// CHANNEL INFO COMMANDS (Public only)
// ==============================
function show_channel_info($chat_id) {
    $message = "📢 <b>Join Our Channels</b>\n\n";
    $message .= "🍿 Main: @EntertainmentTadka786\n";
    $message .= "📺 Serial: @Entertainment_Tadka_Serial_786\n";
    $message .= "📥 Requests: @EntertainmentTadka7860\n";
    $message .= "🎭 Theater Prints: @threater_print_movies\n";
    $message .= "🔒 Backup: @ETBackup\n\n";
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786'], ['text' => '📺 Serial', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']],
            [['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860'], ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies']],
            [['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']]
        ]
    ];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_main_channel_info($chat_id) {
    $message = "🍿 <b>Main Channel - @EntertainmentTadka786</b>\n\nLatest Bollywood & Hollywood movies.";
    $keyboard = ['inline_keyboard' => [[['text' => '🍿 Join Main Channel', 'url' => 'https://t.me/EntertainmentTadka786']]]];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_request_channel_info($chat_id) {
    $message = "📥 <b>Requests Channel - @EntertainmentTadka7860</b>\n\nUse /request movie_name or post directly.";
    $keyboard = ['inline_keyboard' => [[['text' => '📥 Join Requests Channel', 'url' => 'https://t.me/EntertainmentTadka7860']]]];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_theater_channel_info($chat_id) {
    $message = "🎭 <b>Theater Prints - @threater_print_movies</b>\n\nLatest theater quality prints.";
    $keyboard = ['inline_keyboard' => [[['text' => '🎭 Join Theater Channel', 'url' => 'https://t.me/threater_print_movies']]]];
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_backup_channel_info($chat_id) {
    $message = "🔒 <b>Backup Channel - @ETBackup</b>\n\nAdmin only.";
    if ($chat_id == ADMIN_ID) {
        $keyboard = ['inline_keyboard' => [[['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup']]]];
        sendMessage($chat_id, $message, $keyboard, 'HTML');
    } else {
        sendMessage($chat_id, "🔒 Backup channel is private admin-only.", null, 'HTML');
    }
}

// ==============================
// ADMIN COMMANDS (Telegram)
// ==============================
function show_csv_data($chat_id, $show_all = false) {
    $all_movies = get_cached_movies();
    if (empty($all_movies)) {
        sendMessage($chat_id, "📊 No movies in database.");
        return;
    }
    $limit = $show_all ? count($all_movies) : 10;
    $movies = array_slice(array_reverse($all_movies), 0, $limit);
    $message = "📊 <b>Movie Database</b>\n\nTotal Movies: " . count($all_movies) . "\n" . (!$show_all ? "Showing latest 10 entries\n\n" : "Full listing\n\n");
    $i = 1;
    foreach ($movies as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'N/A');
        $msg_id = $movie['message_id_raw'] ?? 'N/A';
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon $movie_name (ID: $msg_id)\n";
        $i++;
        if (strlen($message) > 3000) {
            sendMessage($chat_id, $message, null, 'HTML');
            $message = "";
        }
    }
    sendMessage($chat_id, $message, null, 'HTML');
}

// ==============================
// GROUP MESSAGE FILTER
// ==============================
function is_valid_movie_query($text) {
    $text = strtolower(trim($text));
    if (strpos($text, '/') === 0) return true;
    if (strlen($text) < 3) return false;
    $invalid_phrases = ['good morning', 'good night', 'hello', 'hi ', 'hey ', 'thank you', 'thanks', 'welcome', 'bye', 'see you', 'ok ', 'okay', 'yes', 'no', 'maybe', 'how are you', 'whats up', 'anyone', 'someone', 'everyone', 'problem', 'issue', 'help', 'question', 'doubt', 'query'];
    foreach ($invalid_phrases as $phrase) if (strpos($text, $phrase) !== false) return false;
    $movie_patterns = ['movie', 'film', 'video', 'download', 'watch', 'hd', 'full', 'part', 'series', 'episode', 'season', 'bollywood', 'hollywood', 'theater', 'theatre', 'print', 'hdcam', 'camrip'];
    foreach ($movie_patterns as $pattern) if (strpos($text, $pattern) !== false) return true;
    if (preg_match('/^[a-zA-Z0-9\s\-\.\,]{3,}$/', $text)) return true;
    return false;
}

// ==============================
// COMMAND HANDLER
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    switch ($command) {
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka!\n\n📢 <b>How to use:</b>\n• Type any movie name\n• Use English or Hindi\n• Add 'theater' for theater prints\n\n🔍 <b>Examples:</b>\n• Mandala Murders 2025\n• Zebra 2024\n• Now You See Me All Parts\n• Squid Game All Seasons\n• Show Time (2024)\n• Taskaree S01 (2025)\n\n📢 <b>Join Our Channels:</b>\n🍿 Main: @EntertainmentTadka786\n📺 Serial: @Entertainment_Tadka_Serial_786\n📥 Requests: @EntertainmentTadka7860\n🎭 Theater Prints: @threater_print_movies\n🔒 Backup: @ETBackup\n\n💬 /help for commands";
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''], ['text' => '🍿 Main', 'url' => 'https://t.me/EntertainmentTadka786']],
                    [['text' => '📥 Requests', 'url' => 'https://t.me/EntertainmentTadka7860'], ['text' => '🎭 Theater', 'url' => 'https://t.me/threater_print_movies']],
                    [['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup'], ['text' => '❓ Help', 'callback_data' => 'help_command']]
                ]
            ];
            sendMessage($chat_id, $welcome, $keyboard, 'HTML');
            break;
        case '/help':
            $help = "🤖 <b>Commands:</b>\n/search <movie> - Search\n/request <movie> - Request\n/myrequests - Your requests\n/pending_request - Admin pending\n/bulk_approve <count> - Admin approve\n/totaluploads - Browse all\n/channel - Channels\n/mainchannel - Main info\n/requestchannel - Requests info\n/theaterchannel - Theater info\n/checkcsv - CSV data";
            sendMessage($chat_id, $help, null, 'HTML');
            break;
        case '/search':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) { sendMessage($chat_id, "❌ Usage: /search movie_name"); return; }
            send_multilingual_response($chat_id, 'searching', detect_language($movie_name));
            advanced_search($chat_id, $movie_name, $user_id);
            break;
        case '/request':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) { sendMessage($chat_id, "❌ Usage: /request movie_name"); return; }
            $lang = detect_language($movie_name);
            if (add_movie_request($user_id, $movie_name, $lang)) send_multilingual_response($chat_id, 'request_success', $lang);
            else send_multilingual_response($chat_id, 'request_limit', $lang);
            break;
        case '/myrequests':
            show_user_requests($chat_id, $user_id);
            break;
        case '/pending_request':
            if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Admin only."); return; }
            show_pending_requests($chat_id);
            break;
        case '/bulk_approve':
            if ($chat_id != ADMIN_ID) { sendMessage($chat_id, "❌ Admin only."); return; }
            $count = isset($params[0]) ? intval($params[0]) : 1;
            bulk_approve_requests($chat_id, max(1, $count));
            break;
        case '/totaluploads':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            totalupload_controller($chat_id, $page);
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
            $show_all = (isset($params[0]) && strtolower($params[0]) == 'all');
            show_csv_data($chat_id, $show_all);
            break;
        default:
            sendMessage($chat_id, "❌ Unknown command. Use /help", null, 'HTML');
    }
}

// ==============================
// ADMIN PANEL HTML FUNCTIONS
// ==============================
function admin_panel_login() {
    echo '<!DOCTYPE html>
    <html>
    <head><title>Admin Login - Entertainment Tadka Bot</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>
        body { font-family: Arial; background: #1e1e2f; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #2a2a3a; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.3); width: 300px; }
        h2 { color: #fff; text-align: center; margin-bottom: 20px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: none; border-radius: 5px; }
        input[type="password"] { background: #3a3a4a; color: #fff; }
        button { width: 100%; padding: 10px; background: #5865f2; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #4752c4; }
        .error { color: #ff6b6b; text-align: center; margin-top: 10px; }
    </style></head>
    <body>
    <div class="login-box">
        <h2>Admin Login</h2>
        <form method="post">
            <input type="password" name="admin_pass" placeholder="Enter Password" required>
            <button type="submit">Login</button>
        </form>';
    if (isset($_GET['error'])) echo '<div class="error">Invalid password!</div>';
    echo '</div></body></html>';
}

function admin_panel_dashboard() {
    $all_movies = get_cached_movies();
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $pending_count = count(array_filter($requests_data['requests'] ?? [], function($r) { return $r['status'] == 'pending'; }));
    echo '<!DOCTYPE html>
    <html>
    <head><title>Admin Panel - Entertainment Tadka Bot</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>
        body { font-family: Arial; background: #1e1e2f; margin: 0; padding: 20px; color: #ddd; }
        .container { max-width: 1200px; margin: auto; }
        .header { background: #2a2a3a; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        h1 { margin: 0; font-size: 24px; }
        .logout-btn { background: #ff6b6b; padding: 8px 15px; border-radius: 5px; text-decoration: none; color: white; }
        .stats { display: flex; gap: 20px; margin-bottom: 20px; }
        .stat-card { background: #2a2a3a; padding: 20px; border-radius: 10px; flex: 1; text-align: center; }
        .stat-card h3 { margin: 0 0 10px; font-size: 32px; color: #5865f2; }
        .card { background: #2a2a3a; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .card h2 { margin-top: 0; border-bottom: 1px solid #3a3a4a; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3a3a4a; }
        th { background: #3a3a4a; }
        .approve-btn, .add-btn { background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .approve-btn:hover, .add-btn:hover { background: #218838; }
        .bulk-form { display: inline-block; margin-left: 10px; }
        .bulk-form input { width: 60px; padding: 5px; margin-right: 5px; }
        .bulk-form button { background: #ffc107; color: #000; padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; }
        .nav-links { margin-bottom: 20px; }
        .nav-links a { background: #3a3a4a; padding: 10px 15px; text-decoration: none; color: white; border-radius: 5px; margin-right: 10px; display: inline-block; }
        .nav-links a:hover { background: #5865f2; }
        form.add-movie { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
        form.add-movie input, form.add-movie select { padding: 8px; border-radius: 5px; border: none; background: #3a3a4a; color: #fff; }
        form.add-movie button { background: #28a745; border: none; padding: 8px 15px; border-radius: 5px; color: white; cursor: pointer; }
        .success { background: #28a745; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #ff6b6b; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style></head>
    <body>
    <div class="container">
        <div class="header">
            <h1>🎬 Admin Panel - Entertainment Tadka Bot</h1>
            <a href="?logout=1" class="logout-btn">Logout</a>
        </div>
        <div class="stats">
            <div class="stat-card"><h3>'.count($all_movies).'</h3><p>Total Movies</p></div>
            <div class="stat-card"><h3>'.$pending_count.'</h3><p>Pending Requests</p></div>
        </div>
        <div class="nav-links">
            <a href="#pending">📝 Pending Requests</a>
            <a href="#add">➕ Add Movie</a>
            <a href="#csv">📊 CSV Data</a>
        </div>';
    
    // Pending Requests Section
    echo '<div class="card" id="pending"><h2>📝 Pending Requests</h2>';
    if ($pending_count == 0) {
        echo '<p>No pending requests.</p>';
    } else {
        echo ' <table> <tr><th>#</th><th>Movie Name</th><th>User ID</th><th>Date</th><th>Action</th></tr>';
        $i = 1;
        foreach ($requests_data['requests'] as $req) {
            if ($req['status'] == 'pending') {
                echo '<tr>
                        <td>'.$i.'</td>
                        <td><b>'.htmlspecialchars($req['movie_name']).'</b></td>
                        <td>'.$req['user_id'].'</td>
                        <td>'.$req['date'].'</td>
                        <td><a href="?approve='.$req['id'].'" class="approve-btn" onclick="return confirm(\'Approve this request?\')">✅ Approve</a></td>
                    </tr>';
                $i++;
            }
        }
        echo '</table>';
        echo '<form method="post" class="bulk-form" style="margin-top:15px;">
                <input type="number" name="bulk_count" placeholder="Count" min="1" required>
                <button type="submit" name="bulk_approve">📦 Bulk Approve</button>
              </form>';
    }
    echo '</div>';
    
    // Add Movie Section
    echo '<div class="card" id="add"><h2>➕ Add Movie Manually</h2>';
    echo '<form method="post" class="add-movie">
            <input type="text" name="movie_name" placeholder="Movie Name" required>
            <input type="text" name="message_id" placeholder="Message ID" required>
            <select name="channel_type">
                <option value="main">🍿 Main Channel</option>
                <option value="serial">📺 Serial Channel</option>
                <option value="theater">🎭 Theater Prints</option>
                <option value="backup">🔒 Backup Channel</option>
                <option value="private">🔐 Private Channel 1</option>
                <option value="private2">🔐 Private Channel 2</option>
            </select>
            <button type="submit" name="add_movie">➕ Add Movie</button>
          </form>';
    echo '</div>';
    
    // CSV Data Section
    echo '<div class="card" id="csv"><h2>📊 CSV Data (Latest 20)</h2>';
    $movies = array_slice(array_reverse($all_movies), 0, 20);
    if (empty($movies)) {
        echo '<p>No movies found.</p>';
    } else {
        echo ' <table> <tr><th>#</th><th>Movie Name</th><th>Message ID</th><th>Channel</th></tr>';
        $i = 1;
        foreach ($movies as $m) {
            echo '<tr>
                    <td>'.$i.'</td>
                    <td>'.htmlspecialchars($m['movie_name']).'</td>
                    <td>'.$m['message_id_raw'].'</td>
                    <td>'.get_channel_display_name($m['channel_type']).'</td>
                </tr>';
            $i++;
        }
        echo '</table>';
        echo '<p><a href="?view_all=1" style="color:#5865f2;">View All Movies</a></p>';
    }
    echo '</div></div></body></html>';
}

function admin_panel_all_movies() {
    $all_movies = get_cached_movies();
    echo '<!DOCTYPE html>
    <html>
    <head><title>All Movies - Admin Panel</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>
        body { font-family: Arial; background: #1e1e2f; margin: 0; padding: 20px; color: #ddd; }
        .container { max-width: 1200px; margin: auto; }
        .header { background: #2a2a3a; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3a3a4a; }
        th { background: #3a3a4a; }
        a { color: #5865f2; text-decoration: none; }
    </style></head>
    <body>
    <div class="container">
        <div class="header"><h1>📋 All Movies ('.count($all_movies).')</h1><a href="?">⬅ Back to Dashboard</a></div>
         <table> <tr><th>#</th><th>Movie Name</th><th>Message ID</th><th>Channel</th></tr>';
    $i = 1;
    foreach ($all_movies as $m) {
        echo '<tr><td>'.$i.'</td><td>'.htmlspecialchars($m['movie_name']).'</td><td>'.$m['message_id_raw'].'</td><td>'.get_channel_display_name($m['channel_type']).'</td></tr>';
        $i++;
    }
    echo '</table></div></body></html>';
}

// ==============================
// MAIN UPDATE PROCESSING & ADMIN PANEL ROUTING
// ==============================
// IMPORTANT: Webhook must be processed BEFORE admin login
// ==============================
// WEBHOOK HANDLER - FIXED VERSION
// ==============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_CONTENT_TYPE']) && strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false) {
    // Get raw input
    $input = file_get_contents('php://input');
    $update = json_decode($input, true);
    
    if ($update) {
        // Log incoming webhook
        bot_log("Webhook received: " . substr($input, 0, 200), "DEBUG");
        
        // Handle channel posts
        if (isset($update['channel_post'])) {
            $message = $update['channel_post'];
            $message_id = $message['message_id'];
            $chat_id = $message['chat']['id'];
            $channel_type = get_channel_type_by_id($chat_id);
            
            if ($channel_type != 'other' && $channel_type != 'request') {
                $movie_name = '';
                if (isset($message['caption'])) $movie_name = trim($message['caption']);
                elseif (isset($message['text'])) $movie_name = trim($message['text']);
                elseif (isset($message['document'])) $movie_name = trim($message['document']['file_name']);
                else $movie_name = 'Unknown Movie';
                
                if (!empty($movie_name)) {
                    $csv_file = get_csv_filename($channel_type, $chat_id);
                    $entry = [$movie_name, $message_id];
                    $handle = fopen($csv_file, "a");
                    if ($handle) {
                        fputcsv($handle, $entry);
                        fclose($handle);
                        global $movie_cache;
                        $movie_cache = [];
                        bot_log("Auto-indexed: $movie_name (ID: $message_id) to $channel_type");
                    }
                }
            }
            exit;
        }
        
        // Handle private/user messages
        if (isset($update['message'])) {
            $message = $update['message'];
            $chat_id = $message['chat']['id'];
            $user_id = $message['from']['id'];
            $text = isset($message['text']) ? $message['text'] : '';
            $chat_type = $message['chat']['type'] ?? 'private';
            
            if ($chat_type !== 'private') {
                if (strpos($text, '/') !== 0 && !is_valid_movie_query($text)) exit;
            }
            
            if (strpos($text, '/') === 0) {
                $parts = explode(' ', $text);
                $command = strtolower($parts[0]);
                $params = array_slice($parts, 1);
                handle_command($chat_id, $user_id, $command, $params);
            } else if (!empty(trim($text))) {
                $lang = detect_language($text);
                send_multilingual_response($chat_id, 'searching', $lang);
                advanced_search($chat_id, $text, $user_id);
            }
        }
        
        // Handle callback queries
        if (isset($update['callback_query'])) {
            $query = $update['callback_query'];
            $message = $query['message'];
            $chat_id = $message['chat']['id'];
            $user_id = $query['from']['id'];
            $data = $query['data'];
            global $movie_messages;
            $movie_lower = strtolower($data);
            
            if (isset($movie_messages[$movie_lower])) {
                foreach ($movie_messages[$movie_lower] as $entry) {
                    deliver_item_to_chat($chat_id, $entry);
                    usleep(200000);
                }
                sendMessage($chat_id, "✅ '$data' ka info mil gaya!\n\n📢 Join channel to download: " . MAIN_CHANNEL);
                answerCallbackQuery($query['id'], "🎬 Items sent!");
            } elseif (strpos($data, 'pag_') === 0) {
                $parts = explode('_', $data);
                $action = $parts[1];
                $session_id = isset($parts[2]) ? $parts[2] : '';
                if ($action == 'first') totalupload_controller($chat_id, 1, [], $session_id);
                elseif ($action == 'last') { $all = get_cached_movies(); $total_pages = ceil(count($all) / ITEMS_PER_PAGE); totalupload_controller($chat_id, $total_pages, [], $session_id); }
                elseif ($action == 'prev') { $current = isset($parts[2]) ? intval($parts[2]) : 1; $sid = isset($parts[3]) ? $parts[3] : ''; totalupload_controller($chat_id, max(1, $current-1), [], $sid); }
                elseif ($action == 'next') { $current = isset($parts[2]) ? intval($parts[2]) : 1; $sid = isset($parts[3]) ? $parts[3] : ''; $all = get_cached_movies(); $total_pages = ceil(count($all) / ITEMS_PER_PAGE); totalupload_controller($chat_id, min($total_pages, $current+1), [], $sid); }
                elseif (is_numeric($action)) { $page_num = intval($action); $sid = isset($parts[2]) ? $parts[2] : ''; totalupload_controller($chat_id, $page_num, [], $sid); }
                answerCallbackQuery($query['id'], "Page changed");
            } elseif (strpos($data, 'send_') === 0) {
                $parts = explode('_', $data);
                $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
                $sid = isset($parts[2]) ? $parts[2] : '';
                $all = get_cached_movies();
                $pg = paginate_movies($all, $page_num, []);
                batch_download_with_progress($chat_id, $pg['slice'], $page_num);
                answerCallbackQuery($query['id'], "Batch started");
            } elseif (strpos($data, 'prev_') === 0) {
                $parts = explode('_', $data);
                $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
                $sid = isset($parts[2]) ? $parts[2] : '';
                $all = get_cached_movies();
                $pg = paginate_movies($all, $page_num, []);
                $preview = "👁️ Page $page_num Preview:\n";
                for ($i=0; $i<min(5, count($pg['slice'])); $i++) {
                    $preview .= ($i+1).". ".$pg['slice'][$i]['movie_name']."\n";
                }
                sendMessage($chat_id, $preview, null, 'HTML');
                answerCallbackQuery($query['id'], "Preview sent");
            } elseif (strpos($data, 'flt_') === 0) {
                $parts = explode('_', $data);
                $filter = $parts[1];
                $sid = isset($parts[2]) ? $parts[2] : '';
                $filters = [];
                if ($filter == 'theater') $filters = ['channel_type'=>'theater'];
                elseif ($filter == 'backup') $filters = ['channel_type'=>'backup'];
                totalupload_controller($chat_id, 1, $filters, $sid);
                answerCallbackQuery($query['id'], "Filter applied");
            } elseif ($data == 'request_movie') {
                sendMessage($chat_id, "📝 Use /request movie_name to request.", null, 'HTML');
                answerCallbackQuery($query['id'], "Request help");
            } elseif (strpos($data, 'auto_request_') === 0) {
                $movie_name = base64_decode(str_replace('auto_request_', '', $data));
                $lang = detect_language($movie_name);
                if (add_movie_request($user_id, $movie_name, $lang)) send_multilingual_response($chat_id, 'request_success', $lang);
                else send_multilingual_response($chat_id, 'request_limit', $lang);
                answerCallbackQuery($query['id'], "Request sent");
            } elseif ($data == 'help_command') {
                handle_command($chat_id, $user_id, '/help', []);
                answerCallbackQuery($query['id'], "Help");
            } else {
                sendMessage($chat_id, "❌ Movie not found: $data");
                answerCallbackQuery($query['id'], "Not found");
            }
        }
    }
    exit;
}
        
        // Handle private/user messages
        if (isset($update['message'])) {
            $message = $update['message'];
            $chat_id = $message['chat']['id'];
            $user_id = $message['from']['id'];
            $text = isset($message['text']) ? $message['text'] : '';
            $chat_type = $message['chat']['type'] ?? 'private';
            if ($chat_type !== 'private') {
                if (strpos($text, '/') !== 0 && !is_valid_movie_query($text)) exit;
            }
            if (strpos($text, '/') === 0) {
                $parts = explode(' ', $text);
                $command = strtolower($parts[0]);
                $params = array_slice($parts, 1);
                handle_command($chat_id, $user_id, $command, $params);
            } else if (!empty(trim($text))) {
                $lang = detect_language($text);
                send_multilingual_response($chat_id, 'searching', $lang);
                advanced_search($chat_id, $text, $user_id);
            }
        }
        
        // Handle callback queries
        if (isset($update['callback_query'])) {
            $query = $update['callback_query'];
            $message = $query['message'];
            $chat_id = $message['chat']['id'];
            $user_id = $query['from']['id'];
            $data = $query['data'];
            global $movie_messages;
            $movie_lower = strtolower($data);
            if (isset($movie_messages[$movie_lower])) {
                foreach ($movie_messages[$movie_lower] as $entry) {
                    deliver_item_to_chat($chat_id, $entry);
                    usleep(200000);
                }
                sendMessage($chat_id, "✅ '$data' ka info mil gaya!\n\n📢 Join channel to download: " . MAIN_CHANNEL);
                answerCallbackQuery($query['id'], "🎬 Items sent!");
            } elseif (strpos($data, 'pag_') === 0) {
                $parts = explode('_', $data);
                $action = $parts[1];
                $session_id = isset($parts[2]) ? $parts[2] : '';
                if ($action == 'first') totalupload_controller($chat_id, 1, [], $session_id);
                elseif ($action == 'last') { $all = get_cached_movies(); $total_pages = ceil(count($all) / ITEMS_PER_PAGE); totalupload_controller($chat_id, $total_pages, [], $session_id); }
                elseif ($action == 'prev') { $current = isset($parts[2]) ? intval($parts[2]) : 1; $sid = isset($parts[3]) ? $parts[3] : ''; totalupload_controller($chat_id, max(1, $current-1), [], $sid); }
                elseif ($action == 'next') { $current = isset($parts[2]) ? intval($parts[2]) : 1; $sid = isset($parts[3]) ? $parts[3] : ''; $all = get_cached_movies(); $total_pages = ceil(count($all) / ITEMS_PER_PAGE); totalupload_controller($chat_id, min($total_pages, $current+1), [], $sid); }
                elseif (is_numeric($action)) { $page_num = intval($action); $sid = isset($parts[2]) ? $parts[2] : ''; totalupload_controller($chat_id, $page_num, [], $sid); }
                answerCallbackQuery($query['id'], "Page changed");
            } elseif (strpos($data, 'send_') === 0) {
                $parts = explode('_', $data);
                $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
                $sid = isset($parts[2]) ? $parts[2] : '';
                $all = get_cached_movies();
                $pg = paginate_movies($all, $page_num, []);
                batch_download_with_progress($chat_id, $pg['slice'], $page_num);
                answerCallbackQuery($query['id'], "Batch started");
            } elseif (strpos($data, 'prev_') === 0) {
                $parts = explode('_', $data);
                $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
                $sid = isset($parts[2]) ? $parts[2] : '';
                $all = get_cached_movies();
                $pg = paginate_movies($all, $page_num, []);
                $preview = "👁️ Page $page_num Preview:\n";
                for ($i=0; $i<min(5, count($pg['slice'])); $i++) {
                    $preview .= ($i+1).". ".$pg['slice'][$i]['movie_name']."\n";
                }
                sendMessage($chat_id, $preview, null, 'HTML');
                answerCallbackQuery($query['id'], "Preview sent");
            } elseif (strpos($data, 'flt_') === 0) {
                $parts = explode('_', $data);
                $filter = $parts[1];
                $sid = isset($parts[2]) ? $parts[2] : '';
                $filters = [];
                if ($filter == 'theater') $filters = ['channel_type'=>'theater'];
                elseif ($filter == 'backup') $filters = ['channel_type'=>'backup'];
                totalupload_controller($chat_id, 1, $filters, $sid);
                answerCallbackQuery($query['id'], "Filter applied");
            } elseif ($data == 'request_movie') {
                sendMessage($chat_id, "📝 Use /request movie_name to request.", null, 'HTML');
                answerCallbackQuery($query['id'], "Request help");
            } elseif (strpos($data, 'auto_request_') === 0) {
                $movie_name = base64_decode(str_replace('auto_request_', '', $data));
                $lang = detect_language($movie_name);
                if (add_movie_request($user_id, $movie_name, $lang)) send_multilingual_response($chat_id, 'request_success', $lang);
                else send_multilingual_response($chat_id, 'request_limit', $lang);
                answerCallbackQuery($query['id'], "Request sent");
            } elseif ($data == 'help_command') {
                handle_command($chat_id, $user_id, '/help', []);
                answerCallbackQuery($query['id'], "Help");
            } else {
                sendMessage($chat_id, "❌ Movie not found: $data");
                answerCallbackQuery($query['id'], "Not found");
            }
        }
        exit;
    }
}

// Admin panel login handling (only if not webhook)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
    if ($_POST['admin_pass'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ?');
        exit;
    } else {
        header('Location: ?error=1');
        exit;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?');
    exit;
}

// Admin panel actions
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (isset($_GET['approve'])) {
        $req_id = $_GET['approve'];
        $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
        foreach ($requests_data['requests'] as &$req) {
            if ($req['id'] == $req_id && $req['status'] == 'pending') {
                $req['status'] = 'completed';
                break;
            }
        }
        file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
        bot_log("Admin approved request $req_id via panel");
        header('Location: ?');
        exit;
    }
    
    if (isset($_POST['bulk_approve'])) {
        $count = intval($_POST['bulk_count']);
        if ($count > 0) {
            bulk_approve_requests(0, $count);
        }
        header('Location: ?');
        exit;
    }
    
    if (isset($_POST['add_movie'])) {
        $movie_name = trim($_POST['movie_name']);
        $message_id = trim($_POST['message_id']);
        $channel_type = $_POST['channel_type'];
        if ($channel_type == 'private2') $channel_type = 'private';
        $channel_id = '';
        if ($channel_type == 'private') {
            $channel_id = PRIVATE_CHANNEL_1_ID;
        }
        if (!empty($movie_name) && !empty($message_id)) {
            add_movie_to_csv($movie_name, $message_id, $channel_type, $channel_id);
        }
        header('Location: ?');
        exit;
    }
    
    if (isset($_GET['view_all'])) {
        admin_panel_all_movies();
        exit;
    }
    
    admin_panel_dashboard();
    exit;
}

// Not logged in, show login page
admin_panel_login();
?>
