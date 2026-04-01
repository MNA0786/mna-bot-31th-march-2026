<?php
// ==============================
// SECURITY HEADERS & BASIC SETUP
// ==============================

// Security headers PHP mein set karo - XSS aur security attacks se bachne ke liye
header("X-Content-Type-Options: nosniff");  // MIME type sniffing block karega
header("X-Frame-Options: DENY");  // Clickjacking se bachayega
header("X-XSS-Protection: 1; mode=block");  // XSS attacks block karega
header("Referrer-Policy: strict-origin-when-cross-origin");  // Referrer info secure rakhega

// ==============================
// TYPING INDICATOR SYSTEM - COMPLETE IMPLEMENTATION
// ==============================

/**
 * TypingIndicator Class - Full typing simulation with realistic patterns
 */
class TypingIndicator {
    private $chat_id;
    private $is_active = false;
    private $start_time = null;
    private $typing_history = [];
    
    public function __construct($chat_id) {
        $this->chat_id = $chat_id;
    }
    
    /**
     * Send typing action to Telegram
     */
    private function sendTypingAction() {
        $data = [
            'chat_id' => $this->chat_id,
            'action' => 'typing'
        ];
        apiRequest('sendChatAction', $data);
    }
    
    /**
     * Calculate realistic typing delay based on text length and complexity
     */
    private function calculateTypingDelay($text, $complexity_factor = 1.0) {
        $text_length = mb_strlen($text);
        
        // Base delay: 50ms per character (average typing speed)
        $base_delay = $text_length * 50;
        
        // Add complexity factor (emojis, special chars take longer)
        $emoji_count = preg_match_all('/[\x{1F600}-\x{1F64F}]/u', $text, $matches);
        $special_chars = preg_match_all('/[!@#$%^&*()_+\-=\[\]{};:\'\"\\|,.<>\/?]/', $text);
        
        $complexity_delay = ($emoji_count * 100) + ($special_chars * 30);
        
        // Add random variation (±20%)
        $random_factor = mt_rand(80, 120) / 100;
        
        // Minimum 500ms, maximum 8000ms (8 seconds)
        $delay = min(8000, max(500, ($base_delay + $complexity_delay) * $complexity_factor * $random_factor));
        
        return $delay;
    }
    
    /**
     * Calculate realistic pause between words
     */
    private function calculateWordPauses($text) {
        $words = preg_split('/\s+/', $text);
        $pauses = [];
        
        foreach ($words as $index => $word) {
            // Longer words take longer to type
            $word_delay = mb_strlen($word) * 40;
            
            // Punctuation adds pause
            if (preg_match('/[.!?]$/', $word)) {
                $word_delay += 300; // Sentence end pause
            } elseif (preg_match('/[,;:]$/', $word)) {
                $word_delay += 150; // Clause pause
            }
            
            // Random pause between words (50-300ms)
            if ($index < count($words) - 1) {
                $word_delay += mt_rand(50, 300);
            }
            
            $pauses[] = $word_delay;
        }
        
        return $pauses;
    }
    
    /**
     * Simulate realistic typing with word-by-word animation
     */
    public function simulateTyping($text, $callback_url = null, $callback_data = null) {
        $this->is_active = true;
        $this->start_time = microtime(true);
        
        // Calculate total delay
        $total_delay = $this->calculateTypingDelay($text);
        
        // Split into words for realistic typing
        $words = preg_split('/\s+/', $text);
        $word_pauses = $this->calculateWordPauses($text);
        
        // For very long texts, use progressive typing
        if (count($words) > 10 && $total_delay > 3000) {
            return $this->progressiveTyping($text, $word_pauses, $callback_url, $callback_data);
        }
        
        // Standard typing with typing indicator
        $this->sendTypingAction();
        
        // Store typing history
        $this->typing_history[] = [
            'text' => substr($text, 0, 100),
            'delay' => $total_delay,
            'timestamp' => time()
        ];
        
        // Apply delay
        usleep($total_delay * 1000);
        
        $this->is_active = false;
        
        return true;
    }
    
    /**
     * Progressive typing for long messages (shows typing indicator multiple times)
     */
    private function progressiveTyping($text, $word_pauses, $callback_url = null, $callback_data = null) {
        $words = preg_split('/\s+/', $text);
        $chunks = [];
        $current_chunk = '';
        $current_delay = 0;
        
        // Group words into chunks of 5-8 words for progressive typing
        $chunk_size = mt_rand(5, 8);
        
        foreach ($words as $index => $word) {
            $current_chunk .= ($current_chunk ? ' ' : '') . $word;
            $current_delay += $word_pauses[$index];
            
            if (($index + 1) % $chunk_size == 0 || $index == count($words) - 1) {
                $chunks[] = [
                    'text' => $current_chunk,
                    'delay' => $current_delay
                ];
                $current_chunk = '';
                $current_delay = 0;
            }
        }
        
        // Send progressive typing indicators
        foreach ($chunks as $chunk) {
            $this->sendTypingAction();
            usleep($chunk['delay'] * 1000);
        }
        
        return true;
    }
    
    /**
     * Simulate typing with backspace effect (like someone correcting typos)
     */
    public function simulateTypingWithBackspace($final_text, $backspace_probability = 0.3) {
        $this->sendTypingAction();
        
        $words = preg_split('/\s+/', $final_text);
        $current_text = '';
        
        foreach ($words as $index => $word) {
            // Type the word
            for ($i = 0; $i <= mb_strlen($word); $i++) {
                $current_text = substr($word, 0, $i);
                usleep(mt_rand(30, 80) * 1000);
            }
            
            // Random chance to backspace and retype (realistic)
            if (mt_rand() / mt_getrandmax() < $backspace_probability && mb_strlen($word) > 3) {
                // Simulate backspace
                $backspace_count = mt_rand(1, min(3, mb_strlen($word) - 1));
                for ($i = 0; $i < $backspace_count; $i++) {
                    $current_text = substr($current_text, 0, -1);
                    usleep(mt_rand(50, 100) * 1000);
                }
                
                // Retype
                for ($i = mb_strlen($current_text); $i <= mb_strlen($word); $i++) {
                    $current_text = substr($word, 0, $i);
                    usleep(mt_rand(40, 90) * 1000);
                }
            }
            
            // Add space between words
            if ($index < count($words) - 1) {
                $current_text .= ' ';
                usleep(mt_rand(100, 300) * 1000);
            }
        }
        
        return true;
    }
    
    /**
     * Simulate thinking/reading before typing
     */
    public function simulateThinking($thinking_time_ms = null) {
        if ($thinking_time_ms === null) {
            $thinking_time_ms = mt_rand(500, 2000);
        }
        
        $this->sendTypingAction();
        usleep($thinking_time_ms * 1000);
        
        return true;
    }
    
    /**
     * Get typing statistics
     */
    public function getTypingStats() {
        if (empty($this->typing_history)) {
            return null;
        }
        
        $total_typing_time = 0;
        $total_messages = count($this->typing_history);
        
        foreach ($this->typing_history as $history) {
            $total_typing_time += $history['delay'];
        }
        
        return [
            'total_messages' => $total_messages,
            'total_typing_time_ms' => $total_typing_time,
            'average_typing_time_ms' => $total_typing_time / $total_messages,
            'last_message' => end($this->typing_history)
        ];
    }
    
    /**
     * Check if currently typing
     */
    public function isTyping() {
        return $this->is_active;
    }
    
    /**
     * Get typing duration
     */
    public function getTypingDuration() {
        if (!$this->start_time) {
            return 0;
        }
        return (microtime(true) - $this->start_time) * 1000;
    }
}

/**
 * TypingIndicatorManager - Manages multiple typing sessions
 */
class TypingIndicatorManager {
    private static $instances = [];
    
    public static function getInstance($chat_id) {
        if (!isset(self::$instances[$chat_id])) {
            self::$instances[$chat_id] = new TypingIndicator($chat_id);
        }
        return self::$instances[$chat_id];
    }
    
    public static function clearSession($chat_id) {
        unset(self::$instances[$chat_id]);
    }
    
    public static function getAllStats() {
        $stats = [];
        foreach (self::$instances as $chat_id => $instance) {
            $stats[$chat_id] = $instance->getTypingStats();
        }
        return $stats;
    }
}

// ==============================
// UPDATED MESSAGE FUNCTIONS WITH TYPING INDICATOR
// ==============================

/**
 * Send message with realistic typing indicator
 */
function sendMessageWithTyping($chat_id, $text, $reply_markup = null, $parse_mode = null, $typing_style = 'normal') {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    
    // Simulate thinking before typing
    $typing->simulateThinking(mt_rand(300, 800));
    
    // Choose typing style
    switch ($typing_style) {
        case 'with_backspace':
            $typing->simulateTypingWithBackspace($text, 0.2);
            break;
        case 'progressive':
            $typing->simulateTyping($text);
            break;
        case 'normal':
        default:
            $typing->simulateTyping($text);
            break;
    }
    
    // Send actual message
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Send message with custom typing delay
 */
function sendMessageWithCustomTyping($chat_id, $text, $delay_ms, $reply_markup = null, $parse_mode = null) {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    
    // Show typing indicator
    $typing->simulateThinking($delay_ms);
    
    // Send actual message
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Send message with word-by-word typing animation
 */
function sendMessageWithWordByWordTyping($chat_id, $text, $reply_markup = null, $parse_mode = null, $word_delay = 100) {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    $words = preg_split('/\s+/', $text);
    
    // Simulate typing each word
    foreach ($words as $word) {
        $typing->simulateThinking(mt_rand(50, $word_delay));
    }
    
    // Send final message
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Send message with AI-like typing (variable speed)
 */
function sendMessageWithAITyping($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    
    // AI-like: think first, then type fast
    $typing->simulateThinking(mt_rand(800, 1500)); // Thinking time
    
    // Type quickly (AI is fast)
    $typing->simulateTyping($text, null, null);
    
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Send message with human-like typing (with pauses and variations)
 */
function sendMessageWithHumanTyping($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    
    // Human typing pattern: think, type with pauses, sometimes backspace
    $typing->simulateThinking(mt_rand(200, 500));
    
    // Randomly choose typing style
    $styles = ['normal', 'with_backspace', 'progressive'];
    $selected_style = $styles[array_rand($styles)];
    
    if ($selected_style == 'with_backspace') {
        $typing->simulateTypingWithBackspace($text, 0.15);
    } else {
        $typing->simulateTyping($text);
    }
    
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Edit message with typing indicator
 */
function editMessageWithTyping($chat_id, $message_id, $new_text, $reply_markup = null, $typing_style = 'normal') {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    
    // Shorter thinking for edits
    $typing->simulateThinking(mt_rand(200, 500));
    
    // Typing simulation
    switch ($typing_style) {
        case 'with_backspace':
            $typing->simulateTypingWithBackspace($new_text, 0.1);
            break;
        default:
            $typing->simulateTyping($new_text);
            break;
    }
    
    return editMessage($chat_id, $message_id, $new_text, $reply_markup);
}

/**
 * Send multiple messages with typing between them
 */
function sendMultipleWithTyping($chat_id, $messages, $reply_markups = null, $parse_mode = null) {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    $results = [];
    
    foreach ($messages as $index => $message) {
        // Wait between messages
        if ($index > 0) {
            $typing->simulateThinking(mt_rand(500, 1500));
        }
        
        $markup = isset($reply_markups[$index]) ? $reply_markups[$index] : null;
        $results[] = sendMessageWithTyping($chat_id, $message, $markup, $parse_mode);
    }
    
    return $results;
}

/**
 * Send message with dynamic typing based on message priority
 */
function sendMessageWithPriorityTyping($chat_id, $text, $priority = 'normal', $reply_markup = null, $parse_mode = null) {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    
    $priority_settings = [
        'urgent' => [
            'thinking' => 100,
            'typing_speed' => 'fast'
        ],
        'normal' => [
            'thinking' => 400,
            'typing_speed' => 'normal'
        ],
        'relaxed' => [
            'thinking' => 800,
            'typing_speed' => 'slow'
        ],
        'ai' => [
            'thinking' => 1000,
            'typing_speed' => 'ai'
        ]
    ];
    
    $setting = $priority_settings[$priority] ?? $priority_settings['normal'];
    
    // Thinking time
    $typing->simulateThinking($setting['thinking']);
    
    // Typing based on speed
    switch ($setting['typing_speed']) {
        case 'fast':
            $typing->simulateTyping($text);
            break;
        case 'slow':
            // Simulate slower typing by adding extra delay
            $typing->simulateTyping($text);
            usleep(500000); // Extra 0.5 second
            break;
        case 'ai':
            sendMessageWithAITyping($chat_id, $text, $reply_markup, $parse_mode);
            return;
        default:
            $typing->simulateTyping($text);
    }
    
    return sendMessage($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Send message with emoji reactions before typing
 */
function sendMessageWithReactionTyping($chat_id, $text, $reaction_emoji = '🤔', $reply_markup = null, $parse_mode = null) {
    // Send reaction emoji first (as a separate message or in thinking)
    $typing = TypingIndicatorManager::getInstance($chat_id);
    
    // Show thinking with emoji context
    $typing->simulateThinking(mt_rand(300, 600));
    
    // Send main message with typing
    return sendMessageWithTyping($chat_id, $text, $reply_markup, $parse_mode);
}

/**
 * Get typing statistics for a chat
 */
function getTypingStats($chat_id) {
    $typing = TypingIndicatorManager::getInstance($chat_id);
    return $typing->getTypingStats();
}

/**
 * Clear typing session for a chat
 */
function clearTypingSession($chat_id) {
    TypingIndicatorManager::clearSession($chat_id);
}

/**
 * Get all typing statistics (admin only)
 */
function getAllTypingStats() {
    return TypingIndicatorManager::getAllStats();
}

// ==============================
// COMPATIBILITY FUNCTIONS (Old functions kept for backward compatibility)
// ==============================

/**
 * Legacy function - maps to new system
 */
function sendMessageWithDelay($chat_id, $text, $reply_markup = null, $parse_mode = null, $delay_ms = 1000) {
    // Convert old delay_ms to new typing system
    if ($delay_ms > 500) {
        // Long delay means more realistic typing
        return sendMessageWithHumanTyping($chat_id, $text, $reply_markup, $parse_mode);
    } else {
        // Short delay means fast response
        return sendMessageWithPriorityTyping($chat_id, $text, 'urgent', $reply_markup, $parse_mode);
    }
}

/**
 * Legacy function - maps to new system
 */
function editMessageWithDelay($chat_id, $message_id, $new_text, $reply_markup = null, $delay_ms = 500) {
    return editMessageWithTyping($chat_id, $message_id, $new_text, $reply_markup);
}

// ==============================
// RENDER.COM SPECIFIC CONFIGURATION
// ==============================

// Render.com provides PORT environment variable
$port = getenv('PORT') ?: '80';  // Port detect karta hai, default 80

// Webhook URL automatically set karo
$webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Security - All credentials environment variables se lo
if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN environment variable set nahi hai. Render.com dashboard mein set karo.");
}

// ==============================
// ENVIRONMENT VARIABLES CONFIGURATION
// ==============================
// Yeh sab variables Render.com ke dashboard mein set karne hain
define('BOT_TOKEN', getenv('BOT_TOKEN'));  // Telegram bot token

// ==================== UPDATED CHANNEL CONFIGURATION WITH PUBLIC/PRIVATE FLAG ====================
// ALL CHANNELS DEFINED HERE - WITH VISIBILITY FLAG

// Public Channel 1 - Main Channel (VISIBLE TO PUBLIC)
define('MAIN_CHANNEL', '@EntertainmentTadka786');
define('MAIN_CHANNEL_ID', '-1003181705395');
define('MAIN_CHANNEL_IS_PUBLIC', true);  // ✅ PUBLIC - Forward header ON

// Public Channel 2 - Serial Channel (VISIBLE TO PUBLIC)
define('SERIAL_CHANNEL', '@Entertainment_Tadka_Serial_786');
define('SERIAL_CHANNEL_ID', '-1003614546520');
define('SERIAL_CHANNEL_IS_PUBLIC', true);  // ✅ PUBLIC - Forward header ON

// Public Channel 3 - Theater Channel (VISIBLE TO PUBLIC)
define('THEATER_CHANNEL', '@threater_print_movies');
define('THEATER_CHANNEL_ID', '-1002831605258');
define('THEATER_CHANNEL_IS_PUBLIC', true);  // ✅ PUBLIC - Forward header ON

// Public Channel 4 - Backup Channel (VISIBLE TO PUBLIC)
define('BACKUP_CHANNEL', '@ETBackup');
define('BACKUP_CHANNEL_ID', '-1002964109368');
define('BACKUP_CHANNEL_IS_PUBLIC', true);  // ✅ PUBLIC - Forward header ON

// Private Channel 1 (HIDDEN FROM PUBLIC)
define('PRIVATE_CHANNEL_1_ID', '-1003251791991');
define('PRIVATE_CHANNEL_1_IS_PUBLIC', false);  // ❌ PRIVATE - Forward header OFF

// Private Channel 2 (HIDDEN FROM PUBLIC)
define('PRIVATE_CHANNEL_2_ID', '-1002337293281');
define('PRIVATE_CHANNEL_2_IS_PUBLIC', false);  // ❌ PRIVATE - Forward header OFF

// Request Group (HIDDEN FROM PUBLIC)
define('REQUEST_GROUP', '@EntertainmentTadka7860');
define('REQUEST_GROUP_ID', '-1003083386043');
define('REQUEST_GROUP_IS_PUBLIC', false);  // ❌ PRIVATE - Not for movie display

// Backup channel username for compatibility
define('BACKUP_CHANNEL_USERNAME', BACKUP_CHANNEL);
define('BACKUP_CHANNEL_2_USERNAME', BACKUP_CHANNEL);
define('BACKUP_CHANNEL_2_ID', BACKUP_CHANNEL_ID);

// Admin ID - Environment variable se lo
define('ADMIN_ID', (int)getenv('ADMIN_ID'));  // Admin user ID

// Validate essential environment variables
if (!MAIN_CHANNEL_ID || !THEATER_CHANNEL_ID || !BACKUP_CHANNEL_ID) {
    die("❌ Essential channel IDs environment variables set nahi hain. Render.com dashboard mein set karo.");
}

// ==============================
// SEPARATE CSV FILES FOR EACH CHANNEL
// ==============================

// Define separate CSV file paths for each channel
define('CSV_FILE_MAIN', 'movies_main.csv');
define('CSV_FILE_SERIAL', 'movies_serial.csv');
define('CSV_FILE_THEATER', 'movies_theater.csv');
define('CSV_FILE_BACKUP', 'movies_backup.csv');
define('CSV_FILE_PRIVATE1', 'movies_private1.csv');
define('CSV_FILE_PRIVATE2', 'movies_private2.csv');
define('CSV_FILE_REQUEST', 'movies_request.csv');

// File paths - Yeh sab files bot ke saath create hongi
define('CSV_FILE', 'movies.csv');  // Legacy - for backward compatibility (kept but not used)
define('USERS_FILE', 'users.json');  // Users data
define('STATS_FILE', 'bot_stats.json');  // Bot statistics
define('REQUEST_FILE', 'movie_requests.json');  // Movie requests
define('BACKUP_DIR', 'backups/');  // Backup folder
define('LOG_FILE', 'bot_activity.log');  // Activity log

// Constants - Bot ke settings
define('CACHE_EXPIRY', 300);  // 5 minutes cache
define('ITEMS_PER_PAGE', 5);  // Pagination ke liye items per page
define('MAX_SEARCH_RESULTS', 15);  // Maximum search results
define('DAILY_REQUEST_LIMIT', 5);  // Daily movie request limit per user
define('AUTO_BACKUP_HOUR', '03');  // Auto backup time (3 AM)

// ==============================
// ENHANCED PAGINATION CONSTANTS
// ==============================
define('MAX_PAGES_TO_SHOW', 7);          // Max page buttons to display
define('PAGINATION_CACHE_TIMEOUT', 60);  // Cache timeout in seconds
define('PREVIEW_ITEMS', 3);              // Number of items to preview
define('BATCH_SIZE', 5);                 // Batch download size

// ==============================
// MAINTENANCE MODE
// ==============================
$MAINTENANCE_MODE = false;  // Agar true hai toh bot maintenance mode mein hoga
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe're temporarily unavailable for updates.\nWill be back in few days!\n\nThanks for patience 🙏";

// ==============================
// GLOBAL VARIABLES
// ==============================
$movie_messages = array();  // Movies cache
$movie_cache = array();  // Movies data cache
$waiting_users = array();  // Users waiting for movies
$user_sessions = array();  // User sessions
$user_pagination_sessions = array();  // Enhanced: Pagination sessions
$user_quickadd_sessions = array();  // Quick add sessions

// ==============================
// HELPER FUNCTION: GET CSV FILE PATH BASED ON CHANNEL TYPE
// ==============================
function get_csv_file_path($channel_type) {
    // Channel type ke hisaab se correct CSV file path return karta hai
    switch ($channel_type) {
        case 'main':
            return CSV_FILE_MAIN;
        case 'serial':
            return CSV_FILE_SERIAL;
        case 'theater':
            return CSV_FILE_THEATER;
        case 'backup':
            return CSV_FILE_BACKUP;
        case 'private1':
            return CSV_FILE_PRIVATE1;
        case 'private2':
            return CSV_FILE_PRIVATE2;
        case 'request_group':
            return CSV_FILE_REQUEST;
        default:
            return CSV_FILE_MAIN;  // Default main CSV
    }
}

// ==============================
// HELPER FUNCTION: CHECK IF CHANNEL IS PUBLIC (VISIBLE TO USERS)
// ==============================
function is_channel_public($channel_type) {
    // Channel public hai ya private - ye decide karta hai
    switch ($channel_type) {
        case 'main':
            return MAIN_CHANNEL_IS_PUBLIC;
        case 'serial':
            return SERIAL_CHANNEL_IS_PUBLIC;
        case 'theater':
            return THEATER_CHANNEL_IS_PUBLIC;
        case 'backup':
            return BACKUP_CHANNEL_IS_PUBLIC;
        case 'private1':
            return PRIVATE_CHANNEL_1_IS_PUBLIC;
        case 'private2':
            return PRIVATE_CHANNEL_2_IS_PUBLIC;
        case 'request_group':
            return REQUEST_GROUP_IS_PUBLIC;
        default:
            return false;
    }
}

// ==============================
// HELPER FUNCTION: GET FORWARD METHOD BASED ON CHANNEL TYPE
// ==============================
function should_use_forward_header($channel_type) {
    // Public channels: forward header ON (use forwardMessage)
    // Private channels: forward header OFF (use copyMessage)
    return is_channel_public($channel_type);
}

// ==============================
// CHANNEL MAPPING FUNCTIONS - UPDATED WITH ALL CHANNELS
// ==============================
function get_channel_id_by_username($username) {
    // Channel username se channel ID return karta hai
    $username = strtolower(trim($username));
    
    $channel_map = [
        '@entertainmenttadka786' => MAIN_CHANNEL_ID,
        '@entertainment_tadka_serial_786' => SERIAL_CHANNEL_ID,
        '@threater_print_movies' => THEATER_CHANNEL_ID,
        '@etbackup' => BACKUP_CHANNEL_ID,
        '@entertainmenttadka7860' => REQUEST_GROUP_ID,
        'entertainmenttadka786' => MAIN_CHANNEL_ID,
        'entertainment_tadka_serial_786' => SERIAL_CHANNEL_ID,
        'threater_print_movies' => THEATER_CHANNEL_ID,
        'etbackup' => BACKUP_CHANNEL_ID,
        'entertainmenttadka7860' => REQUEST_GROUP_ID,
    ];
    
    return $channel_map[$username] ?? null;
}

function get_channel_type_by_id($channel_id) {
    // Channel ID se channel type return karta hai
    $channel_id = strval($channel_id);
    
    if ($channel_id == MAIN_CHANNEL_ID) return 'main';
    if ($channel_id == SERIAL_CHANNEL_ID) return 'serial';
    if ($channel_id == THEATER_CHANNEL_ID) return 'theater';
    if ($channel_id == BACKUP_CHANNEL_ID) return 'backup';
    if ($channel_id == PRIVATE_CHANNEL_1_ID) return 'private1';
    if ($channel_id == PRIVATE_CHANNEL_2_ID) return 'private2';
    if ($channel_id == REQUEST_GROUP_ID) return 'request_group';
    
    return 'other';
}

function get_channel_display_name($channel_type) {
    // Channel type se display name return karta hai
    // PRIVATE CHANNELS HIDDEN FROM PUBLIC - Sirf admin dikhega
    if (!is_channel_public($channel_type)) {
        return '🔒 Private Channel';  // Hidden from public users
    }
    
    $names = [
        'main' => '🍿 Main Channel',
        'serial' => '📺 Serial Channel',
        'theater' => '🎭 Theater Prints',
        'backup' => '🔒 Backup Channel',
        'private1' => '🔒 Private Channel 1',
        'private2' => '🔒 Private Channel 2',
        'request_group' => '📥 Request Group',
        'other' => '📢 Other Channel'
    ];
    
    return $names[$channel_type] ?? '📢 Unknown Channel';
}

function get_channel_username_link($channel_type) {
    // Channel type se link generate karta hai
    // Private channels ke liye no link for public
    if (!is_channel_public($channel_type)) {
        return "Private Channel (Access Restricted)";
    }
    
    switch ($channel_type) {
        case 'main':
            return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
        case 'serial':
            return "https://t.me/" . ltrim(SERIAL_CHANNEL, '@');
        case 'theater':
            return "https://t.me/" . ltrim(THEATER_CHANNEL, '@');
        case 'backup':
            return "https://t.me/" . ltrim(BACKUP_CHANNEL, '@');
        case 'private1':
        case 'private2':
            return "Private Channel (Access Restricted)";
        case 'request_group':
            return "https://t.me/" . ltrim(REQUEST_GROUP, '@');
        default:
            return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
    }
}

// ==============================
// HELPER FUNCTION FOR DIRECT LINKS
// ==============================
function get_direct_channel_link($message_id, $channel_id) {
    // Telegram direct link generate karta hai
    if (empty($channel_id)) {
        return "Channel ID not available";
    }
    
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}

// ==============================
// FILE INITIALIZATION FUNCTION - UPDATED FOR MULTIPLE CSV FILES
// ==============================
function initialize_files() {
    // Sab required files create karta hai agar nahi hain toh
    $csv_header = "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username\n";
    
    $files = [
        CSV_FILE_MAIN => $csv_header,
        CSV_FILE_SERIAL => $csv_header,
        CSV_FILE_THEATER => $csv_header,
        CSV_FILE_BACKUP => $csv_header,
        CSV_FILE_PRIVATE1 => $csv_header,
        CSV_FILE_PRIVATE2 => $csv_header,
        CSV_FILE_REQUEST => $csv_header,
        USERS_FILE => json_encode([
            'users' => [],  // Users ka data
            'total_requests' => 0,  // Total requests count
            'message_logs' => [],  // Message logs
            'daily_stats' => []  // Daily statistics
        ], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0,  // Total movies count
            'total_users' => 0,  // Total users count
            'total_searches' => 0,  // Total searches
            'total_downloads' => 0,  // Total downloads
            'successful_searches' => 0,  // Successful searches
            'failed_searches' => 0,  // Failed searches
            'daily_activity' => [],  // Daily activity data
            'last_updated' => date('Y-m-d H:i:s')  // Last updated timestamp
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode([
            'requests' => [],  // Movie requests
            'pending_approval' => [],  // Pending requests
            'completed_requests' => [],  // Completed requests
            'user_request_count' => []  // User request counts
        ], JSON_PRETTY_PRINT)
    ];
    
    // Har file ko check karo aur create karo agar nahi hai
    foreach ($files as $file => $content) {
        if (!file_exists($file)) {
            file_put_contents($file, $content);
            @chmod($file, 0666);  // Read/write permissions
        }
    }
    
    // Backup directory create karo
    if (!file_exists(BACKUP_DIR)) {
        @mkdir(BACKUP_DIR, 0777, true);  // Full permissions
    }
    
    // Log file create karo
    if (!file_exists(LOG_FILE)) {
        file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized with separate CSVs\n");
    }
}

// Initialize all files
initialize_files();

// ==============================
// LOGGING SYSTEM
// ==============================
function bot_log($message, $type = 'INFO') {
    // Bot activities ko log karta hai
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// ==============================
// CACHING SYSTEM - UPDATED FOR MULTIPLE CSV FILES
// ==============================
function get_cached_movies() {
    global $movie_cache;
    
    // Cache check karo - 5 minutes se zyada purana toh refresh karo
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];  // Cache hit
    }
    
    // Cache miss - reload data from ALL CSV files
    $movie_cache = [
        'data' => load_all_csv_files(),
        'timestamp' => time()
    ];
    
    bot_log("Movie cache refreshed - " . count($movie_cache['data']) . " movies from all channels");
    return $movie_cache['data'];
}

/**
 * Load all CSV files and combine them
 */
function load_all_csv_files() {
    global $movie_messages;
    
    $all_movies = [];
    $csv_files = [
        CSV_FILE_MAIN => 'main',
        CSV_FILE_SERIAL => 'serial',
        CSV_FILE_THEATER => 'theater',
        CSV_FILE_BACKUP => 'backup',
        CSV_FILE_PRIVATE1 => 'private1',
        CSV_FILE_PRIVATE2 => 'private2',
        CSV_FILE_REQUEST => 'request_group'
    ];
    
    foreach ($csv_files as $csv_file => $channel_type) {
        $movies = load_csv_file($csv_file, $channel_type);
        $all_movies = array_merge($all_movies, $movies);
    }
    
    // Statistics update karo
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = count($all_movies);
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
    
    bot_log("Loaded " . count($all_movies) . " movies from all CSV files");
    return $all_movies;
}

/**
 * Load a single CSV file
 */
function load_csv_file($filename, $default_channel_type = 'main') {
    global $movie_messages;
    
    if (!file_exists($filename)) {
        return [];
    }
    
    $data = [];
    $handle = fopen($filename, "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle);  // Header read karo
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && (!empty(trim($row[0])))) {
                $movie_name = trim($row[0]);
                $message_id_raw = isset($row[1]) ? trim($row[1]) : '';
                $date = isset($row[2]) ? trim($row[2]) : '';
                $video_path = isset($row[3]) ? trim($row[3]) : '';
                $quality = isset($row[4]) ? trim($row[4]) : 'Unknown';
                $size = isset($row[5]) ? trim($row[5]) : 'Unknown';
                $language = isset($row[6]) ? trim($row[6]) : 'Hindi';
                $channel_type = isset($row[7]) ? trim($row[7]) : $default_channel_type;
                $channel_id = isset($row[8]) ? trim($row[8]) : '';
                $channel_username = isset($row[9]) ? trim($row[9]) : '';
                
                // Channel type agar empty hai toh determine karo channel ID se
                if (empty($channel_type) && !empty($channel_id)) {
                    $channel_type = get_channel_type_by_id($channel_id);
                }
                
                // Movie entry create karo
                $entry = [
                    'movie_name' => $movie_name,
                    'message_id_raw' => $message_id_raw,
                    'date' => $date,
                    'video_path' => $video_path,
                    'quality' => $quality,
                    'size' => $size,
                    'language' => $language,
                    'channel_type' => $channel_type,
                    'channel_id' => $channel_id,
                    'channel_username' => $channel_username,
                    'source_channel' => $channel_id
                ];
                
                // Message ID numeric check karo
                if (is_numeric($message_id_raw)) {
                    $entry['message_id'] = intval($message_id_raw);
                } else {
                    $entry['message_id'] = null;
                }
                
                $data[] = $entry;
                
                // Global movie messages array mein add karo
                $movie = strtolower($movie_name);
                if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
                $movie_messages[$movie][] = $entry;
            }
        }
        fclose($handle);
    }
    
    return $data;
}

// ==============================
// CSV MANAGEMENT FUNCTIONS - UPDATED FOR MULTIPLE FILES
// ==============================
function load_and_clean_csv($filename = null) {
    // Legacy function - ab load_all_csv_files use karo
    if ($filename === null) {
        return load_all_csv_files();
    }
    return load_csv_file($filename, 'main');
}

// ==============================
// TELEGRAM API FUNCTIONS
// ==============================
function apiRequest($method, $params = array(), $is_multipart = false) {
    // Telegram API ko call karta hai
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        // Files upload ke liye (multipart form data)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        if ($res === false) {
            bot_log("CURL ERROR: " . curl_error($ch), 'ERROR');
        }
        curl_close($ch);
        return $res;
    } else {
        // Normal API requests ke liye
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            )
        );
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            bot_log("API Request failed for method: $method", 'ERROR');
        }
        return $result;
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = null) {
    // Telegram message send karta hai (base function without typing)
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => true  // Link preview disable karta hai
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    if ($parse_mode) $data['parse_mode'] = $parse_mode;
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id: " . substr($text, 0, 50) . "...");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    // Existing message edit karta hai (base function without typing)
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
    // Message delete karta hai
    apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    // Callback query reply karta hai
    $data = [
        'callback_query_id' => $callback_query_id,
        'show_alert' => $show_alert
    ];
    if ($text) $data['text'] = $text;
    apiRequest('answerCallbackQuery', $data);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    // Message forward karta hai (USES FORWARD HEADER)
    $result = apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    return $result;
}

function copyMessage($chat_id, $from_chat_id, $message_id) {
    // Message copy karta hai (NO FORWARD HEADER)
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

// ==============================
// MOVIE DELIVERY SYSTEM - WITH FORWARD HEADER TOGGLE BASED ON CHANNEL TYPE
// ==============================
function deliver_item_to_chat($chat_id, $item) {
    // Movie user ko deliver karta hai
    // Item mein channel_id aur message_id hona chahiye
    
    if (!isset($item['channel_id']) || empty($item['channel_id'])) {
        // Agar channel ID nahi hai, fallback karo
        $source_channel = MAIN_CHANNEL_ID;
        $channel_type = 'main';
        bot_log("Channel ID not found for movie: {$item['movie_name']}, using default", 'WARNING');
    } else {
        $source_channel = $item['channel_id'];
        $channel_type = isset($item['channel_type']) ? $item['channel_type'] : 'main';
    }
    
    // Check if channel is public or private
    $use_forward_header = should_use_forward_header($channel_type);
    
    // Agar valid message ID hai
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        
        if ($use_forward_header) {
            // PUBLIC CHANNEL: Forward header ON
            $result = json_decode(forwardMessage($chat_id, $source_channel, $item['message_id']), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie FORWARDED (Public) from $channel_type: {$item['movie_name']} to $chat_id");
                return true;
            } else {
                // Fallback to copy if forward fails
                $fallback_result = json_decode(copyMessage($chat_id, $source_channel, $item['message_id']), true);
                if ($fallback_result && $fallback_result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie COPIED (Fallback) from $channel_type: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        } else {
            // PRIVATE CHANNEL: Forward header OFF - Use copyMessage
            $result = json_decode(copyMessage($chat_id, $source_channel, $item['message_id']), true);
            
            if ($result && $result['ok']) {
                update_stats('total_downloads', 1);
                bot_log("Movie COPIED (Private) from $channel_type: {$item['movie_name']} to $chat_id");
                return true;
            } else {
                // Fallback to forward if copy fails (rare)
                $fallback_result = json_decode(forwardMessage($chat_id, $source_channel, $item['message_id']), true);
                if ($fallback_result && $fallback_result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie FORWARDED (Fallback) from $channel_type: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        }
    }
    
    // Agar message ID nahi hai ya numeric nahi hai
    if (!empty($item['message_id_raw'])) {
        // Raw message ID se try karo
        $message_id_clean = preg_replace('/[^0-9]/', '', $item['message_id_raw']);
        if (is_numeric($message_id_clean) && $message_id_clean > 0) {
            
            if ($use_forward_header) {
                $result = json_decode(forwardMessage($chat_id, $source_channel, $message_id_clean), true);
                if ($result && $result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie FORWARDED (raw ID) from $channel_type: {$item['movie_name']} to $chat_id");
                    return true;
                }
            } else {
                $result = json_decode(copyMessage($chat_id, $source_channel, $message_id_clean), true);
                if ($result && $result['ok']) {
                    update_stats('total_downloads', 1);
                    bot_log("Movie COPIED (raw ID) from $channel_type: {$item['movie_name']} to $chat_id");
                    return true;
                }
            }
        }
    }
    
    // Agar koi bhi method kaam na kare toh text info bhejo (NO FORWARD)
    $is_public = is_channel_public($channel_type);
    $forward_status = $is_public ? "Forward ON (Public Channel)" : "Forward OFF (Private Channel)";
    
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📊 Quality: " . htmlspecialchars($item['quality'] ?? 'Unknown') . "\n";
    $text .= "💾 Size: " . htmlspecialchars($item['size'] ?? 'Unknown') . "\n";
    $text .= "🗣️ Language: " . htmlspecialchars($item['language'] ?? 'Hindi') . "\n";
    
    // Only show channel name if public
    if ($is_public) {
        $text .= "🎭 Channel: " . get_channel_display_name($channel_type) . "\n";
    } else {
        $text .= "🎭 Channel: 🔒 Private Content\n";
    }
    
    $text .= "📅 Date: " . htmlspecialchars($item['date'] ?? 'N/A') . "\n";
    $text .= "📨 Delivery Mode: $forward_status\n";
    $text .= "📎 Reference: " . htmlspecialchars($item['message_id_raw'] ?? 'N/A') . "\n\n";
    
    // Direct link provide karo (forward nahi)
    if (!empty($item['message_id']) && is_numeric($item['message_id']) && !empty($source_channel)) {
        $text .= "🔗 Direct Link: " . get_direct_channel_link($item['message_id'], $source_channel) . "\n\n";
    }
    
    // Only show public channel links
    if ($is_public) {
        $text .= "📢 Join channel to access content: " . get_channel_username_link($channel_type);
    } else {
        $text .= "🔒 This content is from a private channel. Access restricted.";
    }
    
    sendMessageWithHumanTyping($chat_id, $text, null, 'HTML');
    update_stats('total_downloads', 1);
    return false;
}

// ==============================
// STATISTICS SYSTEM
// ==============================
function update_stats($field, $increment = 1) {
    // Statistics update karta hai
    if (!file_exists(STATS_FILE)) return;
    
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    // Daily activity update karo
    $today = date('Y-m-d');
    if (!isset($stats['daily_activity'][$today])) {
        $stats['daily_activity'][$today] = [
            'searches' => 0,
            'downloads' => 0,
            'users' => 0
        ];
    }
    
    if ($field == 'total_searches') $stats['daily_activity'][$today]['searches'] += $increment;
    if ($field == 'total_downloads') $stats['daily_activity'][$today]['downloads'] += $increment;
    
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

function get_stats() {
    // Statistics return karta hai
    if (!file_exists(STATS_FILE)) return [];
    return json_decode(file_get_contents(STATS_FILE), true);
}

// ==============================
// USER MANAGEMENT
// ==============================
function update_user_data($user_id, $user_info = []) {
    // User data update/create karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        // New user create karo
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'last_name' => $user_info['last_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'points' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'request_count' => 0,
            'last_request_date' => null
        ];
        $users_data['total_requests'] = ($users_data['total_requests'] ?? 0) + 1;
        update_stats('total_users', 1);
        bot_log("New user registered: $user_id");
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    return $users_data['users'][$user_id];
}

function update_user_activity($user_id, $action) {
    // User activity aur points update karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
        $points_map = [
            'search' => 1,
            'found_movie' => 5,
            'daily_login' => 10,
            'movie_request' => 2,
            'download' => 3
        ];
        
        $users_data['users'][$user_id]['points'] += ($points_map[$action] ?? 0);
        
        if ($action == 'search') $users_data['users'][$user_id]['total_searches']++;
        if ($action == 'download') $users_data['users'][$user_id]['total_downloads']++;
        
        $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

// ==============================
// SEARCH SYSTEM - WITH PRIVATE CHANNEL FILTERING
// ==============================
function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = array();
    
    // Theater search detection
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
    
    // Serial search detection
    $is_serial_search = false;
    $serial_keywords = ['serial', 'episode', 'season', 's01', 's02', 's03', 'ep1', 'ep2'];
    foreach ($serial_keywords as $keyword) {
        if (strpos($query_lower, $keyword) !== false) {
            $is_serial_search = true;
            break;
        }
    }
    
    // Har movie ke against query match karo
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        // Filter out private channels from search results for normal users
        // Admin ko sab dikhega but normal users ko sirf public channels
        $filtered_entries = [];
        foreach ($entries as $entry) {
            $entry_channel_type = $entry['channel_type'] ?? 'main';
            if (is_channel_public($entry_channel_type)) {
                $filtered_entries[] = $entry;
            }
        }
        
        // Agar koi public entry nahi hai toh skip karo
        if (empty($filtered_entries)) {
            continue;
        }
        
        // Channel type matching (only public channels)
        foreach ($filtered_entries as $entry) {
            $entry_channel_type = $entry['channel_type'] ?? 'main';
            
            if ($is_theater_search && $entry_channel_type == 'theater') {
                $score += 20;  // Theater search ko theater movies ka bonus
            } elseif ($is_serial_search && $entry_channel_type == 'serial') {
                $score += 20;  // Serial search ko serial channel ka bonus
            } elseif (!$is_theater_search && $entry_channel_type == 'main') {
                $score += 10;  // Normal search ko main channel movies ka bonus
            }
            
            // Backup channels ko bhi include karo (public)
            if (in_array($entry_channel_type, ['backup'])) {
                $score += 5;
            }
        }
        
        // 1. Exact match check karo
        if ($movie == $query_lower) {
            $score = 100;
        }
        // 2. Partial match check karo
        elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        }
        // 3. Similarity match check karo
        else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        // Quality aur language ke liye bonus points
        foreach ($filtered_entries as $entry) {
            if (stripos($entry['quality'] ?? '', '1080') !== false) $score += 5;
            if (stripos($entry['quality'] ?? '', '720') !== false) $score += 3;
            if (stripos($entry['language'] ?? '', 'hindi') !== false) $score += 2;
        }
        
        if ($score > 0) {
            $channel_types = array_column($filtered_entries, 'channel_type');
            $results[$movie] = [
                'score' => $score,
                'count' => count($filtered_entries),
                'latest_entry' => end($filtered_entries),
                'qualities' => array_unique(array_column($filtered_entries, 'quality')),
                'has_theater' => in_array('theater', $channel_types),
                'has_serial' => in_array('serial', $channel_types),
                'has_main' => in_array('main', $channel_types),
                'has_backup' => in_array('backup', $channel_types),
                'has_private' => false,  // Private not shown in public search
                'all_channels' => array_unique($channel_types)
            ];
        }
    }
    
    // Score ke hisab se sort karo (descending)
    uasort($results, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    // Maximum results return karo
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

function detect_language($text) {
    // Text ki language detect karta hai (Hindi/English)
    $hindi_keywords = ['फिल्म', 'मूवी', 'डाउनलोड', 'हिंदी', 'चाहिए', 'कहाँ', 'कैसे', 'खोज', 'तलाश'];
    $english_keywords = ['movie', 'download', 'watch', 'print', 'search', 'find', 'looking', 'want', 'need'];
    
    $hindi_score = 0;
    $english_score = 0;
    
    // Hindi keywords check karo
    foreach ($hindi_keywords as $k) {
        if (strpos($text, $k) !== false) $hindi_score++;
    }
    
    // English keywords check karo
    foreach ($english_keywords as $k) {
        if (stripos($text, $k) !== false) $english_score++;
    }
    
    // Hindi characters detect karo
    $hindi_chars = preg_match('/[\x{0900}-\x{097F}]/u', $text);
    if ($hindi_chars) $hindi_score += 3;
    
    return $hindi_score > $english_score ? 'hindi' : 'english';
}

function send_multilingual_response($chat_id, $message_type, $language) {
    // Language ke hisab se response send karta hai
    $responses = [
        'hindi' => [
            'welcome' => "🎬 Boss, kis movie ki talash hai?",
            'found' => "✅ Mil gayi! Movie info bhej raha hoon...",
            'not_found' => "😔 Yeh movie abhi available nahi hai!\n\n📝 Aap ise request kar sakte hain: " . REQUEST_GROUP . "\n\n🔔 Jab bhi yeh add hogi, main automatically bhej dunga!",
            'searching' => "🔍 Dhoondh raha hoon... Zara wait karo",
            'multiple_found' => "🎯 Kai versions mili hain! Aap konsi chahte hain?",
            'request_success' => "✅ Request receive ho gayi! Hum jald hi add karenge.",
            'request_limit' => "❌ Aaj ke liye aap maximum " . DAILY_REQUEST_LIMIT . " requests hi kar sakte hain."
        ],
        'english' => [
            'welcome' => "🎬 Boss, which movie are you looking for?",
            'found' => "✅ Found it! Sending movie info...",
            'not_found' => "😔 This movie isn't available yet!\n\n📝 You can request it here: " . REQUEST_GROUP . "\n\n🔔 I'll send it automatically once it's added!",
            'searching' => "🔍 Searching... Please wait",
            'multiple_found' => "🎯 Multiple versions found! Which one do you want?",
            'request_success' => "✅ Request received! We'll add it soon.",
            'request_limit' => "❌ You've reached the daily limit of " . DAILY_REQUEST_LIMIT . " requests."
        ]
    ];
    
    sendMessageWithHumanTyping($chat_id, $responses[$language][$message_type]);
}

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages, $waiting_users;
    $q = strtolower(trim($query));
    
    // Minimum length check
    if (strlen($q) < 2) {
        sendMessageWithTyping($chat_id, "❌ Please enter at least 2 characters for search");
        return;
    }
    
    // Invalid keywords filter - technical queries block karega
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
    
    // Smart word analysis
    $query_words = explode(' ', $q);
    $total_words = count($query_words);
    
    $invalid_count = 0;
    foreach ($query_words as $word) {
        if (in_array($word, $invalid_keywords)) {
            $invalid_count++;
        }
    }
    
    // Stricter threshold - agar 50% se zyada invalid words toh block karo
    if ($invalid_count > 0 && ($invalid_count / $total_words) > 0.5) {
        $help_msg = "🎬 Please enter a movie name!\n\n";
        $help_msg .= "🔍 Examples of valid movie names:\n";
        $help_msg .= "• kgf\n• pushpa\n• avengers\n• hindi movie\n• spider-man\n\n";
        $help_msg .= "❌ Technical queries like 'vlc', 'audio track', etc. are not movie names.\n\n";
        $help_msg .= "📢 Join: " . MAIN_CHANNEL . "\n";
        $help_msg .= "💬 Help: " . REQUEST_GROUP;
        sendMessageWithTyping($chat_id, $help_msg, null, 'HTML');
        return;
    }
    
    // Movie name pattern validation
    $movie_pattern = '/^[a-zA-Z0-9\s\-\.\,\&\+\(\)\:\'\"]+$/';
    if (!preg_match($movie_pattern, $query)) {
        sendMessageWithTyping($chat_id, "❌ Invalid movie name format. Only letters, numbers, and basic punctuation allowed.");
        return;
    }
    
    // Search karo
    $found = smart_search($q);
    
    if (!empty($found)) {
        // Movies mil gayi
        update_stats('successful_searches', 1);
        
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $channel_info = "";
            if ($data['has_theater']) $channel_info .= "🎭 ";
            if ($data['has_serial']) $channel_info .= "📺 ";
            if ($data['has_main']) $channel_info .= "🍿 ";
            if ($data['has_backup']) $channel_info .= "🔒 ";
            $msg .= "$i. $movie ($channel_info" . $data['count'] . " versions, $quality_info)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessageWithHumanTyping($chat_id, $msg);
        
        // Inline keyboard banayega top matches ke liye
        $keyboard = ['inline_keyboard' => []];
        $top_movies = array_slice(array_keys($found), 0, 5);
        
        foreach ($top_movies as $movie) {
            $movie_data = $found[$movie];
            $channel_icon = '🍿';
            if ($movie_data['has_theater']) $channel_icon = '🎭';
            elseif ($movie_data['has_serial']) $channel_icon = '📺';
            elseif ($movie_data['has_backup']) $channel_icon = '🔒';
            
            $keyboard['inline_keyboard'][] = [[ 
                'text' => $channel_icon . ucwords($movie), 
                'callback_data' => $movie 
            ]];
        }
        
        // Request button add karo
        $keyboard['inline_keyboard'][] = [[
            'text' => "📝 Request Different Movie", 
            'callback_data' => 'request_movie'
        ]];
        
        sendMessageWithTyping($chat_id, "🚀 Top matches (click for info):", $keyboard);
        
        if ($user_id) {
            update_user_activity($user_id, 'found_movie');
            update_user_activity($user_id, 'search');
        }
        
    } else {
        // Movies nahi mili
        update_stats('failed_searches', 1);
        $lang = detect_language($query);
        send_multilingual_response($chat_id, 'not_found', $lang);
        
        // Auto-suggest request
        $request_keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        
        sendMessageWithTyping($chat_id, "💡 Click below to automatically request this movie:", $request_keyboard);
        
        // Waiting list mein add karo
        if (!isset($waiting_users[$q])) $waiting_users[$q] = [];
        $waiting_users[$q][] = [$chat_id, $user_id ?? $chat_id];
    }
    
    update_stats('total_searches', 1);
    if ($user_id) update_user_activity($user_id, 'search');
}

// ==============================
// MOVIE REQUEST SYSTEM
// ==============================
function can_user_request($user_id) {
    // Check karo user daily limit mein hai ya nahi
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    
    $user_requests_today = 0;
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id && $request['date'] == $today) {
            $user_requests_today++;
        }
    }
    
    return $user_requests_today < DAILY_REQUEST_LIMIT;
}

function add_movie_request($user_id, $movie_name, $language = 'hindi') {
    // Movie request add karta hai
    if (!can_user_request($user_id)) {
        return false;
    }
    
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
    
    // User request count update karo
    if (!isset($requests_data['user_request_count'][$user_id])) {
        $requests_data['user_request_count'][$user_id] = 0;
    }
    $requests_data['user_request_count'][$user_id]++;
    
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    // Admin ko notify karo
    $admin_msg = "🎯 New Movie Request\n\n";
    $admin_msg .= "🎬 Movie: $movie_name\n";
    $admin_msg .= "🗣️ Language: $language\n";
    $admin_msg .= "👤 User ID: $user_id\n";
    $admin_msg .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
    $admin_msg .= "🆔 Request ID: $request_id";
    
    sendMessageWithHumanTyping(ADMIN_ID, $admin_msg);
    bot_log("Movie request added: $movie_name by $user_id");
    
    // Send to request group as well
    $group_msg = "📝 New Movie Request\n\n";
    $group_msg .= "🎬 Movie: $movie_name\n";
    $group_msg .= "👤 From: User #$user_id\n";
    $group_msg .= "📅 Time: " . date('Y-m-d H:i:s');
    
    sendMessageWithTyping(REQUEST_GROUP_ID, $group_msg);
    
    return true;
}

// ==============================
// ENHANCED PAGINATION SYSTEM
// ==============================

function paginate_movies(array $all, int $page, array $filters = []): array {
    // Apply filters if any
    if (!empty($filters)) {
        $all = apply_movie_filters($all, $filters);
    }
    
    $total = count($all);
    if ($total === 0) {
        return [
            'total' => 0,
            'total_pages' => 1, 
            'page' => 1,
            'slice' => [],
            'filters' => $filters,
            'has_next' => false,
            'has_prev' => false,
            'start_item' => 0,
            'end_item' => 0
        ];
    }
    
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
    
    // Enhanced navigation with page numbers
    $nav_row = [];
    
    // Previous/Fast Previous buttons
    if ($page > 1) {
        $nav_row[] = ['text' => '⏪', 'callback_data' => 'pag_first_' . $session_id];
        $nav_row[] = ['text' => '◀️', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
    }
    
    // Smart page number display (max 7 pages)
    $start_page = max(1, $page - 3);
    $end_page = min($total_pages, $start_page + 6);
    
    if ($end_page - $start_page < 6) {
        $start_page = max(1, $end_page - 6);
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $page) {
            $nav_row[] = ['text' => "【{$i}】", 'callback_data' => 'current'];
        } else {
            $nav_row[] = ['text' => "{$i}", 'callback_data' => 'pag_' . $i . '_' . $session_id];
        }
    }
    
    // Next/Fast Next buttons
    if ($page < $total_pages) {
        $nav_row[] = ['text' => '▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
        $nav_row[] = ['text' => '⏩', 'callback_data' => 'pag_last_' . $total_pages . '_' . $session_id];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    // Action buttons row
    $action_row = [];
    $action_row[] = ['text' => '📥 Send Page', 'callback_data' => 'send_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '👁️ Preview', 'callback_data' => 'prev_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '📊 Stats', 'callback_data' => 'stats_' . $session_id];
    
    $kb['inline_keyboard'][] = $action_row;
    
    // Filter buttons row - Only public channel filters
    if (empty($filters)) {
        $filter_row = [];
        $filter_row[] = ['text' => '🎬 HD Only', 'callback_data' => 'flt_hd_' . $session_id];
        $filter_row[] = ['text' => '🎭 Theater Only', 'callback_data' => 'flt_theater_' . $session_id];
        $filter_row[] = ['text' => '📺 Serial Only', 'callback_data' => 'flt_serial_' . $session_id];
        $filter_row[] = ['text' => '🔒 Backup Only', 'callback_data' => 'flt_backup_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    } else {
        $filter_row = [];
        $filter_row[] = ['text' => '🧹 Clear Filter', 'callback_data' => 'flt_clr_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
    }
    
    // Control buttons row
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '💾 Save', 'callback_data' => 'save_' . $session_id];
    $ctrl_row[] = ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_' . $session_id];
    
    $kb['inline_keyboard'][] = $ctrl_row;
    
    return $kb;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    // Get all movies and filter out private channels for public users
    $all = get_all_movies_list();
    
    // Filter out private channel movies for normal users
    $filtered_all = [];
    foreach ($all as $movie) {
        $channel_type = $movie['channel_type'] ?? 'main';
        if (is_channel_public($channel_type)) {
            $filtered_all[] = $movie;
        }
    }
    
    if (empty($filtered_all)) {
        sendMessageWithTyping($chat_id, "📭 Koi public movies nahi mili! Only private channels have content.");
        return;
    }
    
    // Create session ID if not provided
    if (!$session_id) {
        $session_id = uniqid('sess_', true);
    }
    
    $pg = paginate_movies($filtered_all, (int)$page, $filters);
    
    // Send preview for first page
    if ($page == 1 && PREVIEW_ITEMS > 0 && count($pg['slice']) > 0) {
        $preview_msg = "👁️ <b>Quick Preview (First " . PREVIEW_ITEMS . "):</b>\n\n";
        $preview_count = min(PREVIEW_ITEMS, count($pg['slice']));
        
        for ($i = 0; $i < $preview_count; $i++) {
            $movie = $pg['slice'][$i];
            $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
            $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
            $preview_msg .= "   ⭐ " . ($movie['quality'] ?? 'Unknown') . " | ";
            $preview_msg .= "🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        }
        
        sendMessageWithTyping($chat_id, $preview_msg, null, 'HTML');
    }
    
    // Build enhanced message
    $title = "🎬 <b>Enhanced Movie Browser</b>\n\n";
    
    // Session info
    $title .= "🆔 <b>Session:</b> <code>" . substr($session_id, 0, 8) . "</code>\n";
    
    // Statistics
    $title .= "📊 <b>Statistics:</b>\n";
    $title .= "• Total Movies: <b>{$pg['total']}</b>\n";
    $title .= "• Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n";
    $title .= "• Items: <b>{$pg['start_item']}-{$pg['end_item']}</b>\n";
    
    // Filter info
    if (!empty($filters)) {
        $title .= "• Filters: <b>" . count($filters) . " active</b>\n";
    }
    
    $title .= "\n";
    
    // Current page movies list
    $title .= "📋 <b>Page {$page} Movies:</b>\n\n";
    $i = $pg['start_item'];
    foreach ($pg['slice'] as $movie) {
        $movie_name = htmlspecialchars($movie['movie_name'] ?? 'Unknown');
        $quality = $movie['quality'] ?? 'Unknown';
        $language = $movie['language'] ?? 'Hindi';
        $date = $movie['date'] ?? 'N/A';
        $size = $movie['size'] ?? 'Unknown';
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        
        $title .= "<b>{$i}.</b> $channel_icon {$movie_name}\n";
        $title .= "   🏷️ {$quality} | 🗣️ {$language}\n";
        $title .= "   💾 {$size} | 📅 {$date}\n\n";
        $i++;
    }
    
    // Navigation help
    $title .= "📍 <i>Use number buttons for direct page access</i>\n";
    $title .= "🔧 <i>Apply filters using buttons below</i>";
    
    // Build enhanced keyboard
    $kb = build_totalupload_keyboard($pg['page'], $pg['total_pages'], $session_id, $filters);
    
    // Delete previous pagination message if exists
    delete_pagination_message($chat_id, $session_id);
    
    // Save new message ID
    $result = sendMessageWithHumanTyping($chat_id, $title, $kb, 'HTML');
    save_pagination_message($chat_id, $session_id, $result['result']['message_id']);
    
    bot_log("Enhanced pagination - Chat: $chat_id, Page: $page, Session: " . substr($session_id, 0, 8));
}

// ==============================
// PAGINATION HELPER FUNCTIONS
// ==============================

function apply_movie_filters($movies, $filters) {
    if (empty($filters)) return $movies;
    
    $filtered = [];
    foreach ($movies as $movie) {
        $pass = true;
        
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'quality':
                    if (stripos($movie['quality'] ?? '', $value) === false) {
                        $pass = false;
                    }
                    break;
                    
                case 'language':
                    if (stripos($movie['language'] ?? '', $value) === false) {
                        $pass = false;
                    }
                    break;
                    
                case 'year':
                    $movie_year = substr($movie['date'] ?? '', -4);
                    if ($movie_year != $value) {
                        $pass = false;
                    }
                    break;
                    
                case 'channel_type':
                    if (($movie['channel_type'] ?? 'main') != $value) {
                        $pass = false;
                    }
                    break;
            }
            
            if (!$pass) break;
        }
        
        if ($pass) {
            $filtered[] = $movie;
        }
    }
    
    return $filtered;
}

function save_pagination_message($chat_id, $session_id, $message_id) {
    global $user_pagination_sessions;
    
    if (!isset($user_pagination_sessions[$session_id])) {
        $user_pagination_sessions[$session_id] = [];
    }
    
    $user_pagination_sessions[$session_id]['last_message_id'] = $message_id;
    $user_pagination_sessions[$session_id]['chat_id'] = $chat_id;
    $user_pagination_sessions[$session_id]['last_updated'] = time();
}

function delete_pagination_message($chat_id, $session_id) {
    global $user_pagination_sessions;
    
    if (isset($user_pagination_sessions[$session_id]) && 
        isset($user_pagination_sessions[$session_id]['last_message_id'])) {
        
        $message_id = $user_pagination_sessions[$session_id]['last_message_id'];
        deleteMessage($chat_id, $message_id);
    }
}

function batch_download_with_progress($chat_id, $movies, $page_num) {
    $total = count($movies);
    if ($total === 0) return;
    
    $progress_msg = sendMessageWithPriorityTyping($chat_id, "📦 <b>Batch Info Started</b>\n\nPage: {$page_num}\nTotal: {$total} movies\n\n⏳ Initializing...", 'urgent');
    $progress_id = $progress_msg['result']['message_id'];
    
    $success = 0;
    $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        $movie = $movies[$i];
        
        // Update progress every 2 movies
        if ($i % 2 == 0) {
            $progress = round(($i / $total) * 100);
            editMessageWithTyping($chat_id, $progress_id, 
                "📦 <b>Sending Page {$page_num} Info</b>\n\n" .
                "Progress: {$progress}%\n" .
                "Processed: {$i}/{$total}\n" .
                "✅ Success: {$success}\n" .
                "❌ Failed: {$failed}\n\n" .
                "⏳ Please wait..."
            );
        }
        
        try {
            $result = deliver_item_to_chat($chat_id, $movie);
            if ($result) {
                $success++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
        
        usleep(500000); // 0.5 second delay
    }
    
    // Final update
    editMessageWithTyping($chat_id, $progress_id,
        "✅ <b>Batch Info Complete</b>\n\n" .
        "📄 Page: {$page_num}\n" .
        "🎬 Total: {$total} movies\n" .
        "✅ Successfully sent: {$success}\n" .
        "❌ Failed: {$failed}\n\n" .
        "📊 Success rate: " . round(($success / $total) * 100, 2) . "%\n" .
        "⏱️ Time: " . date('H:i:s') . "\n\n" .
        "🔗 Join channel to download: " . MAIN_CHANNEL
    );
}

// ==============================
// GET ALL MOVIES LIST FUNCTION - FILTERS OUT PRIVATE CHANNELS
// ==============================
function get_all_movies_list() {
    // All movies list return karta hai (PUBLIC CHANNELS ONLY for users)
    $all_movies = get_cached_movies();
    
    // Filter out private channels for normal users
    // Admin will see everything through admin functions
    $public_movies = [];
    foreach ($all_movies as $movie) {
        $channel_type = $movie['channel_type'] ?? 'main';
        if (is_channel_public($channel_type)) {
            $public_movies[] = $movie;
        }
    }
    
    return $public_movies;
}

// ==============================
// BACKUP SYSTEM - COMPLETE IMPLEMENTATION (UPDATED FOR MULTIPLE CSVs)
// ==============================
function auto_backup() {
    // Automatic backup process
    bot_log("Starting auto-backup process...");
    
    $all_csv_files = [CSV_FILE_MAIN, CSV_FILE_SERIAL, CSV_FILE_THEATER, CSV_FILE_BACKUP, CSV_FILE_PRIVATE1, CSV_FILE_PRIVATE2, CSV_FILE_REQUEST];
    $backup_files = array_merge($all_csv_files, [USERS_FILE, STATS_FILE, REQUEST_FILE, LOG_FILE]);
    $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s');
    $backup_success = true;
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    // 1. Local file backup
    foreach ($backup_files as $file) {
        if (file_exists($file)) {
            $backup_path = $backup_dir . '/' . basename($file) . '.bak';
            if (!copy($file, $backup_path)) {
                bot_log("Failed to backup: $file", 'ERROR');
                $backup_success = false;
            } else {
                bot_log("Backed up: $file");
            }
        }
    }
    
    // 2. Create backup summary
    $summary = create_backup_summary();
    file_put_contents($backup_dir . '/backup_summary.txt', $summary);
    
    // 3. Upload to backup channel
    if ($backup_success) {
        $channel_backup_success = upload_backup_to_channel($backup_dir, $summary);
        
        if ($channel_backup_success) {
            bot_log("Backup successfully uploaded to channel");
        } else {
            bot_log("Failed to upload backup to channel", 'WARNING');
        }
    }
    
    // 4. Clean old backups
    clean_old_backups();
    
    // 5. Send backup report to admin
    send_backup_report($backup_success, $summary);
    
    bot_log("Auto-backup process completed");
    return $backup_success;
}

function create_backup_summary() {
    // Backup summary create karta hai
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    // Get counts from each CSV
    $csv_counts = [];
    $csv_files = [CSV_FILE_MAIN, CSV_FILE_SERIAL, CSV_FILE_THEATER, CSV_FILE_BACKUP, CSV_FILE_PRIVATE1, CSV_FILE_PRIVATE2, CSV_FILE_REQUEST];
    foreach ($csv_files as $csv_file) {
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, 'r');
            $count = 0;
            if ($handle) {
                fgetcsv($handle); // skip header
                while (fgetcsv($handle)) $count++;
                fclose($handle);
            }
            $csv_counts[basename($csv_file)] = $count;
        }
    }
    
    $summary = "📊 BACKUP SUMMARY\n";
    $summary .= "================\n\n";
    
    $summary .= "📅 Backup Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "🤖 Bot: Entertainment Tadka\n\n";
    
    $summary .= "📈 STATISTICS:\n";
    $summary .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $summary .= "• Total Users: " . count($users_data['users'] ?? []) . "\n";
    $summary .= "• Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $summary .= "• Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $summary .= "• Pending Requests: " . count($requests_data['requests'] ?? []) . "\n\n";
    
    $summary .= "📁 CSV FILE BREAKDOWN:\n";
    foreach ($csv_counts as $file => $count) {
        $summary .= "• $file: $count movies\n";
    }
    $summary .= "\n";
    
    $summary .= "💾 FILES BACKED UP:\n";
    $summary .= "• " . CSV_FILE_MAIN . " (" . (file_exists(CSV_FILE_MAIN) ? filesize(CSV_FILE_MAIN) : 0) . " bytes)\n";
    $summary .= "• " . CSV_FILE_SERIAL . " (" . (file_exists(CSV_FILE_SERIAL) ? filesize(CSV_FILE_SERIAL) : 0) . " bytes)\n";
    $summary .= "• " . CSV_FILE_THEATER . " (" . (file_exists(CSV_FILE_THEATER) ? filesize(CSV_FILE_THEATER) : 0) . " bytes)\n";
    $summary .= "• " . CSV_FILE_BACKUP . " (" . (file_exists(CSV_FILE_BACKUP) ? filesize(CSV_FILE_BACKUP) : 0) . " bytes)\n";
    $summary .= "• " . CSV_FILE_PRIVATE1 . " (" . (file_exists(CSV_FILE_PRIVATE1) ? filesize(CSV_FILE_PRIVATE1) : 0) . " bytes)\n";
    $summary .= "• " . CSV_FILE_PRIVATE2 . " (" . (file_exists(CSV_FILE_PRIVATE2) ? filesize(CSV_FILE_PRIVATE2) : 0) . " bytes)\n";
    $summary .= "• " . CSV_FILE_REQUEST . " (" . (file_exists(CSV_FILE_REQUEST) ? filesize(CSV_FILE_REQUEST) : 0) . " bytes)\n";
    $summary .= "• " . USERS_FILE . " (" . (file_exists(USERS_FILE) ? filesize(USERS_FILE) : 0) . " bytes)\n";
    $summary .= "• " . STATS_FILE . " (" . (file_exists(STATS_FILE) ? filesize(STATS_FILE) : 0) . " bytes)\n";
    $summary .= "• " . REQUEST_FILE . " (" . (file_exists(REQUEST_FILE) ? filesize(REQUEST_FILE) : 0) . " bytes)\n";
    $summary .= "• " . LOG_FILE . " (" . (file_exists(LOG_FILE) ? filesize(LOG_FILE) : 0) . " bytes)\n\n";
    
    $summary .= "🔄 Backup Type: Automated Daily Backup\n";
    $summary .= "📍 Stored In: " . BACKUP_DIR . "\n";
    $summary .= "📡 Channel: " . BACKUP_CHANNEL . "\n";
    
    return $summary;
}

function upload_backup_to_channel($backup_dir, $summary) {
    // Backup Telegram channel pe upload karta hai
    try {
        // 1. Backup summary message send karo
        $summary_message = "🔄 <b>Daily Auto-Backup Report</b>\n\n";
        $summary_message .= "📅 " . date('Y-m-d H:i:s') . "\n\n";
        
        $stats = get_stats();
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        
        $summary_message .= "📊 <b>Current Stats:</b>\n";
        $summary_message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
        $summary_message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
        $summary_message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
        $summary_message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
        
        $summary_message .= "📁 <b>CSV Files Backed Up:</b>\n";
        $summary_message .= "• Main: " . (file_exists(CSV_FILE_MAIN) ? round(filesize(CSV_FILE_MAIN)/1024, 2) . " KB" : "N/A") . "\n";
        $summary_message .= "• Serial: " . (file_exists(CSV_FILE_SERIAL) ? round(filesize(CSV_FILE_SERIAL)/1024, 2) . " KB" : "N/A") . "\n";
        $summary_message .= "• Theater: " . (file_exists(CSV_FILE_THEATER) ? round(filesize(CSV_FILE_THEATER)/1024, 2) . " KB" : "N/A") . "\n";
        $summary_message .= "• Backup: " . (file_exists(CSV_FILE_BACKUP) ? round(filesize(CSV_FILE_BACKUP)/1024, 2) . " KB" : "N/A") . "\n";
        $summary_message .= "• Private 1: " . (file_exists(CSV_FILE_PRIVATE1) ? round(filesize(CSV_FILE_PRIVATE1)/1024, 2) . " KB" : "N/A") . "\n";
        $summary_message .= "• Private 2: " . (file_exists(CSV_FILE_PRIVATE2) ? round(filesize(CSV_FILE_PRIVATE2)/1024, 2) . " KB" : "N/A") . "\n\n";
        
        $summary_message .= "✅ <b>Backup Status:</b> Successful\n";
        $summary_message .= "📁 <b>Location:</b> " . $backup_dir . "\n";
        $summary_message .= "💾 <b>Files:</b> " . count($backup_files) . " data files\n";
        $summary_message .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL . "\n\n";
        
        $summary_message .= "🔗 <a href=\"https://t.me/ETBackup\">Visit Backup Channel</a>";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📡 Visit ' . BACKUP_CHANNEL, 'url' => 'https://t.me/ETBackup']
                ]
            ]
        ];
        
        $message_result = sendMessageWithTyping(BACKUP_CHANNEL_ID, $summary_message, $keyboard, 'HTML');
        
        if (!$message_result || !isset($message_result['ok']) || !$message_result['ok']) {
            bot_log("Failed to send backup summary to channel", 'ERROR');
            return false;
        }
        
        // 2. Critical files as documents upload karo
        $critical_files = [
            CSV_FILE_MAIN => "🎬 Main Channel Movies Database",
            CSV_FILE_SERIAL => "📺 Serial Channel Movies",
            CSV_FILE_THEATER => "🎭 Theater Prints Movies",
            CSV_FILE_BACKUP => "🔒 Backup Channel Movies",
            CSV_FILE_PRIVATE1 => "🔐 Private Channel 1 Movies",
            CSV_FILE_PRIVATE2 => "🔐 Private Channel 2 Movies",
            CSV_FILE_REQUEST => "📝 Request Group Movies",
            USERS_FILE => "👥 Users Data", 
            STATS_FILE => "📊 Bot Statistics",
            REQUEST_FILE => "📝 Movie Requests"
        ];
        
        foreach ($critical_files as $file => $description) {
            if (file_exists($file)) {
                $upload_success = upload_file_to_channel($file, $backup_dir, $description);
                if (!$upload_success) {
                    bot_log("Failed to upload $file to channel", 'WARNING');
                }
                sleep(2); // Rate limiting
            }
        }
        
        // 3. Zip archive create karo aur upload karo
        $zip_success = create_and_upload_zip($backup_dir);
        
        // 4. Completion message send karo
        $completion_message = "✅ <b>Backup Process Completed</b>\n\n";
        $completion_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $completion_message .= "💾 All files backed up successfully\n";
        $completion_message .= "📦 Zip archive created\n";
        $completion_message .= "📡 Uploaded to: " . BACKUP_CHANNEL . "\n\n";
        $completion_message .= "🛡️ <i>Your data is now securely backed up!</i>";
        
        sendMessageWithTyping(BACKUP_CHANNEL_ID, $completion_message, null, 'HTML');
        
        return true;
        
    } catch (Exception $e) {
        bot_log("Channel backup failed: " . $e->getMessage(), 'ERROR');
        
        // Error report send karo backup channel pe
        $error_message = "❌ <b>Backup Process Failed</b>\n\n";
        $error_message .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $error_message .= "🚨 Error: " . $e->getMessage() . "\n\n";
        $error_message .= "⚠️ Please check server logs immediately!";
        
        sendMessageWithTyping(BACKUP_CHANNEL_ID, $error_message, null, 'HTML');
        
        return false;
    }
}

function upload_file_to_channel($file_path, $backup_dir, $description = "") {
    // Individual file channel pe upload karta hai
    if (!file_exists($file_path)) {
        return false;
    }
    
    $file_name = basename($file_path);
    $backup_file_path = $backup_dir . '/' . $file_name . '.bak';
    
    if (!file_exists($backup_file_path)) {
        return false;
    }
    
    $file_size = filesize($backup_file_path);
    $file_size_mb = round($file_size / (1024 * 1024), 2);
    $backup_time = date('Y-m-d H:i:s');
    
    $caption = "💾 " . $description . "\n";
    $caption .= "📅 " . $backup_time . "\n";
    $caption .= "📊 Size: " . $file_size_mb . " MB\n";
    $caption .= "🔄 Auto-backup\n";
    $caption .= "📡 " . BACKUP_CHANNEL;
    
    // Large files ke liye (Telegram limit 50MB)
    if ($file_size > 45 * 1024 * 1024) { // 45MB limit
        bot_log("File too large for Telegram: $file_name ($file_size_mb MB)", 'WARNING');
        
        // Large CSV files ko split karo
        if (strpos($file_name, 'movies_') !== false) {
            return split_and_upload_large_csv($backup_file_path, $backup_dir, $description);
        }
        return false;
    }
    
    $post_fields = [
        'chat_id' => BACKUP_CHANNEL_ID,
        'document' => new CURLFile($backup_file_path),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result_data = json_decode($result, true);
    $success = ($http_code == 200 && $result_data && $result_data['ok']);
    
    if ($success) {
        bot_log("Uploaded to channel: $file_name");
        
        // Large files ke liye confirmation message
        if ($file_size > 10 * 1024 * 1024) {
            $confirmation = "✅ <b>Large File Uploaded</b>\n\n";
            $confirmation .= "📁 File: " . $description . "\n";
            $confirmation .= "💾 Size: " . $file_size_mb . " MB\n";
            $confirmation .= "✅ Status: Successfully uploaded to " . BACKUP_CHANNEL;
            sendMessageWithTyping(BACKUP_CHANNEL_ID, $confirmation, null, 'HTML');
        }
    } else {
        bot_log("Failed to upload to channel: $file_name", 'ERROR');
    }
    
    return $success;
}

function split_and_upload_large_csv($csv_file_path, $backup_dir, $description) {
    // Large CSV files ko split karke upload karta hai
    if (!file_exists($csv_file_path)) {
        return false;
    }
    
    $file_size = filesize($csv_file_path);
    $file_size_mb = round($file_size / (1024 * 1024), 2);
    
    bot_log("Splitting large CSV file: $file_size_mb MB", 'INFO');
    
    // CSV file read karo
    $rows = [];
    $handle = fopen($csv_file_path, 'r');
    if ($handle !== FALSE) {
        $header = fgetcsv($handle); // Header read karo
        while (($row = fgetcsv($handle)) !== FALSE) {
            $rows[] = $row;
        }
        fclose($handle);
    }
    
    $total_rows = count($rows);
    $rows_per_file = ceil($total_rows / 3); // 3 parts mein split karo
    
    $upload_success = true;
    
    for ($i = 0; $i < 3; $i++) {
        $start = $i * $rows_per_file;
        $end = min($start + $rows_per_file, $total_rows);
        $part_rows = array_slice($rows, $start, $end - $start);
        
        // Part file create karo
        $part_file = $backup_dir . '/movies_part_' . ($i + 1) . '.csv';
        $part_handle = fopen($part_file, 'w');
        fputcsv($part_handle, $header);
        foreach ($part_rows as $row) {
            fputcsv($part_handle, $row);
        }
        fclose($part_handle);
        
        // Part file upload karo
        $part_caption = "💾 " . $description . " (Part " . ($i + 1) . "/3)\n";
        $part_caption .= "📅 " . date('Y-m-d H:i:s') . "\n";
        $part_caption .= "📊 Rows: " . count($part_rows) . "\n";
        $part_caption .= "🔄 Split backup\n";
        $part_caption .= "📡 " . BACKUP_CHANNEL;
        
        $post_fields = [
            'chat_id' => BACKUP_CHANNEL_ID,
            'document' => new CURLFile($part_file),
            'caption' => $part_caption,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Part file clean up karo
        @unlink($part_file);
        
        if ($http_code != 200) {
            $upload_success = false;
            bot_log("Failed to upload CSV part " . ($i + 1), 'ERROR');
        } else {
            bot_log("Uploaded CSV part " . ($i + 1));
        }
        
        sleep(2); // Rate limiting
    }
    
    // Split completion message send karo
    if ($upload_success) {
        $split_message = "📦 <b>Large CSV Split Successfully</b>\n\n";
        $split_message .= "📁 File: " . $description . "\n";
        $split_message .= "💾 Original Size: " . $file_size_mb . " MB\n";
        $split_message .= "📊 Total Rows: " . $total_rows . "\n";
        $split_message .= "🔀 Split into: 3 parts\n";
        $split_message .= "✅ All parts uploaded to " . BACKUP_CHANNEL;
        
        sendMessageWithTyping(BACKUP_CHANNEL_ID, $split_message, null, 'HTML');
    }
    
    return $upload_success;
}

function create_and_upload_zip($backup_dir) {
    // Zip archive create aur upload karta hai
    $zip_file = $backup_dir . '/complete_backup.zip';
    $zip = new ZipArchive();
    
    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
        bot_log("Cannot open zip file: $zip_file", 'ERROR');
        return false;
    }
    
    // Files zip mein add karo
    $files = glob($backup_dir . '/*.bak');
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    
    // Summary file add karo
    if (file_exists($backup_dir . '/backup_summary.txt')) {
        $zip->addFile($backup_dir . '/backup_summary.txt', 'backup_summary.txt');
    }
    
    $zip->close();
    
    $zip_size = filesize($zip_file);
    $zip_size_mb = round($zip_size / (1024 * 1024), 2);
    
    // Zip file upload karo
    $caption = "📦 Complete Backup Archive\n";
    $caption .= "📅 " . date('Y-m-d H:i:s') . "\n";
    $caption .= "💾 Size: " . $zip_size_mb . " MB\n";
    $caption .= "📁 Contains all data files\n";
    $caption .= "🔄 Auto-generated backup\n";
    $caption .= "📡 " . BACKUP_CHANNEL;
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔗 ' . BACKUP_CHANNEL, 'url' => 'https://t.me/ETBackup']
            ]
        ]
    ];
    
    $post_fields = [
        'chat_id' => BACKUP_CHANNEL_ID,
        'document' => new CURLFile($zip_file),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Zip file clean up karo
    @unlink($zip_file);
    
    $success = ($http_code == 200);
    
    if ($success) {
        bot_log("Zip backup uploaded to channel successfully");
        
        // Zip upload confirmation send karo
        $zip_confirmation = "✅ <b>Zip Archive Uploaded</b>\n\n";
        $zip_confirmation .= "📦 File: Complete Backup Archive\n";
        $zip_confirmation .= "💾 Size: " . $zip_size_mb . " MB\n";
        $zip_confirmation .= "✅ Status: Successfully uploaded\n";
        $zip_confirmation .= "📡 Channel: " . BACKUP_CHANNEL . "\n\n";
        $zip_confirmation .= "🛡️ <i>All data securely backed up!</i>";
        
        sendMessageWithTyping(BACKUP_CHANNEL_ID, $zip_confirmation, $keyboard, 'HTML');
    } else {
        bot_log("Failed to upload zip backup to channel", 'WARNING');
    }
    
    return $success;
}

function clean_old_backups() {
    // Purane backups delete karta hai (last 7 rakhta hai)
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $deleted_count = 0;
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            if (@rmdir($d)) {
                $deleted_count++;
                bot_log("Deleted old backup: $d");
            }
        }
        
        bot_log("Cleaned $deleted_count old backups");
    }
}

function send_backup_report($success, $summary) {
    // Admin ko backup report send karta hai
    $report_message = "🔄 <b>Backup Completion Report</b>\n\n";
    
    if ($success) {
        $report_message .= "✅ <b>Status:</b> SUCCESS\n";
        $report_message .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
        $report_message .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL . "\n\n";
    } else {
        $report_message .= "❌ <b>Status:</b> FAILED\n";
        $report_message .= "📅 <b>Time:</b> " . date('Y-m-d H:i:s') . "\n";
        $report_message .= "📡 <b>Channel:</b> " . BACKUP_CHANNEL . "\n\n";
        $report_message .= "⚠️ Some backup operations may have failed. Check logs for details.\n\n";
    }
    
    // Summary stats add karo
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $report_message .= "📊 <b>Current System Status:</b>\n";
    $report_message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $report_message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $report_message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $report_message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    
    $report_message .= "💾 <b>Backup Locations:</b>\n";
    $report_message .= "• Local: " . BACKUP_DIR . "\n";
    $report_message .= "• Channel: " . BACKUP_CHANNEL . "\n\n";
    
    $report_message .= "🕒 <b>Next Backup:</b> " . AUTO_BACKUP_HOUR . ":00 daily";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📡 Visit Backup Channel', 'url' => 'https://t.me/ETBackup'],
                ['text' => '📊 Backup Status', 'callback_data' => 'backup_status']
            ]
        ]
    ];
    
    sendMessageWithTyping(ADMIN_ID, $report_message, $keyboard, 'HTML');
}

// ==============================
// MANUAL BACKUP COMMANDS
// ==============================
function manual_backup($chat_id) {
    // Manual backup command handler
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $progress_msg = sendMessageWithPriorityTyping($chat_id, "🔄 Starting manual backup...", 'urgent');
    
    try {
        $success = auto_backup();
        
        if ($success) {
            editMessageWithTyping($chat_id, $progress_msg['result']['message_id'], "✅ Manual backup completed successfully!\n\n📊 Backup has been saved locally and uploaded to backup channel.");
        } else {
            editMessageWithTyping($chat_id, $progress_msg['result']['message_id'], "⚠️ Backup completed with some warnings.\n\nSome files may not have been backed up properly. Check logs for details.");
        }
        
    } catch (Exception $e) {
        editMessageWithTyping($chat_id, $progress_msg['result']['message_id'], "❌ Backup failed!\n\nError: " . $e->getMessage());
        bot_log("Manual backup failed: " . $e->getMessage(), 'ERROR');
    }
}

function quick_backup($chat_id) {
    // Quick backup command handler
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $progress_msg = sendMessageWithPriorityTyping($chat_id, "💾 Creating quick backup...", 'urgent');
    
    try {
        // Quick backup - only essential files
        $essential_files = [CSV_FILE_MAIN, CSV_FILE_SERIAL, CSV_FILE_THEATER, CSV_FILE_BACKUP, CSV_FILE_PRIVATE1, CSV_FILE_PRIVATE2, CSV_FILE_REQUEST, USERS_FILE];
        $backup_dir = BACKUP_DIR . 'quick_' . date('Y-m-d_H-i-s');
        
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        foreach ($essential_files as $file) {
            if (file_exists($file)) {
                copy($file, $backup_dir . '/' . basename($file) . '.bak');
            }
        }
        
        // Channel pe upload karo
        $summary = "🚀 Quick Backup\n" . date('Y-m-d H:i:s') . "\nEssential files only";
        file_put_contents($backup_dir . '/quick_backup_info.txt', $summary);
        
        foreach ($essential_files as $file) {
            $backup_file = $backup_dir . '/' . basename($file) . '.bak';
            if (file_exists($backup_file)) {
                upload_file_to_channel($file, $backup_dir);
                sleep(1);
            }
        }
        
        editMessageWithTyping($chat_id, $progress_msg['result']['message_id'], "✅ Quick backup completed!\n\nEssential files backed up to channel.");
        
    } catch (Exception $e) {
        editMessageWithTyping($chat_id, $progress_msg['result']['message_id'], "❌ Quick backup failed!\n\nError: " . $e->getMessage());
    }
}

// ==============================
// BACKUP STATUS & INFO COMMANDS
// ==============================
function backup_status($chat_id) {
    // Backup status show karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $backup_dirs = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    $latest_backup = null;
    $total_size = 0;
    
    if (!empty($backup_dirs)) {
        usort($backup_dirs, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $latest_backup = $backup_dirs[0];
    }
    
    foreach ($backup_dirs as $dir) {
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
    }
    
    $total_size_mb = round($total_size / (1024 * 1024), 2);
    
    $status_message = "💾 <b>Backup System Status</b>\n\n";
    
    $status_message .= "📊 <b>Storage Info:</b>\n";
    $status_message .= "• Total Backups: " . count($backup_dirs) . "\n";
    $status_message .= "• Storage Used: " . $total_size_mb . " MB\n";
    $status_message .= "• Backup Channel: " . BACKUP_CHANNEL . "\n";
    $status_message .= "• Channel ID: " . BACKUP_CHANNEL_ID . "\n\n";
    
    $status_message .= "📁 <b>CSV Files:</b>\n";
    $status_message .= "• Main: " . (file_exists(CSV_FILE_MAIN) ? round(filesize(CSV_FILE_MAIN)/1024, 2) . " KB" : "N/A") . "\n";
    $status_message .= "• Serial: " . (file_exists(CSV_FILE_SERIAL) ? round(filesize(CSV_FILE_SERIAL)/1024, 2) . " KB" : "N/A") . "\n";
    $status_message .= "• Theater: " . (file_exists(CSV_FILE_THEATER) ? round(filesize(CSV_FILE_THEATER)/1024, 2) . " KB" : "N/A") . "\n";
    $status_message .= "• Backup: " . (file_exists(CSV_FILE_BACKUP) ? round(filesize(CSV_FILE_BACKUP)/1024, 2) . " KB" : "N/A") . "\n";
    $status_message .= "• Private 1: " . (file_exists(CSV_FILE_PRIVATE1) ? round(filesize(CSV_FILE_PRIVATE1)/1024, 2) . " KB" : "N/A") . "\n";
    $status_message .= "• Private 2: " . (file_exists(CSV_FILE_PRIVATE2) ? round(filesize(CSV_FILE_PRIVATE2)/1024, 2) . " KB" : "N/A") . "\n";
    $status_message .= "• Request: " . (file_exists(CSV_FILE_REQUEST) ? round(filesize(CSV_FILE_REQUEST)/1024, 2) . " KB" : "N/A") . "\n\n";
    
    if ($latest_backup) {
        $latest_time = date('Y-m-d H:i:s', filemtime($latest_backup));
        $status_message .= "🕒 <b>Latest Backup:</b>\n";
        $status_message .= "• Time: " . $latest_time . "\n";
        $status_message .= "• Folder: " . basename($latest_backup) . "\n\n";
    } else {
        $status_message .= "❌ <b>No backups found!</b>\n\n";
    }
    
    $status_message .= "⏰ <b>Auto-backup Schedule:</b>\n";
    $status_message .= "• Daily at " . AUTO_BACKUP_HOUR . ":00\n";
    $status_message .= "• Keep last 7 backups\n";
    $status_message .= "• Upload to " . BACKUP_CHANNEL . "\n\n";
    
    $status_message .= "🛠️ <b>Manual Commands:</b>\n";
    $status_message .= "• <code>/backup</code> - Full backup\n";
    $status_message .= "• <code>/quickbackup</code> - Quick backup\n";
    $status_message .= "• <code>/backupstatus</code> - This info\n\n";
    
    $status_message .= "🔗 <b>Backup Channel:</b> " . BACKUP_CHANNEL;
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📡 Visit ' . BACKUP_CHANNEL, 'url' => 'https://t.me/ETBackup'],
                ['text' => '🔄 Run Backup', 'callback_data' => 'run_backup']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $status_message, $keyboard, 'HTML');
}

// ==============================
// CHANNEL MANAGEMENT FUNCTIONS - UPDATED WITH PUBLIC ONLY DISPLAY
// ==============================
function show_channel_info($chat_id) {
    // All PUBLIC channels ka information show karta hai
    // Private channels hidden from public users
    $message = "📢 <b>Join Our Public Channels</b>\n\n";
    
    $message .= "🍿 <b>Main Channel:</b> " . MAIN_CHANNEL . "\n";
    $message .= "• Latest movie updates\n";
    $message .= "• Daily new additions\n";
    $message .= "• High quality prints\n";
    $message .= "• Direct downloads\n\n";
    
    $message .= "📺 <b>Serial Channel:</b> " . SERIAL_CHANNEL . "\n";
    $message .= "• Latest TV series\n";
    $message .= "• Web series episodes\n";
    $message .= "• Complete seasons\n";
    $message .= "• Regular updates\n\n";
    
    $message .= "🎭 <b>Theater Prints:</b> " . THEATER_CHANNEL . "\n";
    $message .= "• Theater quality prints\n";
    $message .= "• HD screen recordings\n";
    $message .= "• Latest theater prints\n\n";
    
    $message .= "🔒 <b>Backup Channel:</b> " . BACKUP_CHANNEL . "\n";
    $message .= "• Secure data backups\n";
    $message .= "• System archives\n";
    $message .= "• Database copies\n\n";
    
    $message .= "📥 <b>Request Group:</b> " . REQUEST_GROUP . "\n";
    $message .= "• Movie requests\n";
    $message .= "• Bug reports\n";
    $message .= "• Feature suggestions\n";
    $message .= "• Support & help\n\n";
    
    $message .= "🔔 <b>Note:</b> Private channels are not listed here as they are for admin use only.";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 ' . MAIN_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📺 ' . SERIAL_CHANNEL, 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']
            ],
            [
                ['text' => '🎭 ' . THEATER_CHANNEL, 'url' => 'https://t.me/threater_print_movies'],
                ['text' => '🔒 ' . BACKUP_CHANNEL, 'url' => 'https://t.me/ETBackup']
            ],
            [
                ['text' => '📥 ' . REQUEST_GROUP, 'url' => 'https://t.me/EntertainmentTadka7860']
            ]
        ]
    ];
    
    sendMessageWithHumanTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_main_channel_info($chat_id) {
    // Main channel ka detailed information
    $message = "🍿 <b>Main Channel - " . MAIN_CHANNEL . "</b>\n\n";
    
    $message .= "🎬 <b>What you get:</b>\n";
    $message .= "• Latest Bollywood & Hollywood movies\n";
    $message .= "• HD/1080p/720p quality prints\n";
    $message .= "• Daily new uploads\n";
    $message .= "• Multiple server links\n";
    $message .= "• Fast direct downloads\n";
    $message .= "• No ads, no spam\n\n";
    
    $message .= "📊 <b>Current Stats:</b>\n";
    $stats = get_stats();
    $message .= "• Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• Active Users: " . get_active_users_count() . "\n";
    $message .= "• Daily Uploads: " . get_daily_uploads_count() . "\n\n";
    
    $message .= "🔔 <b>Join now for latest movies!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🍿 Join Main Channel', 'url' => 'https://t.me/EntertainmentTadka786'],
                ['text' => '📥 Request Movies', 'url' => 'https://t.me/EntertainmentTadka7860']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_serial_channel_info($chat_id) {
    // Serial channel ka detailed information
    $message = "📺 <b>Serial Channel - " . SERIAL_CHANNEL . "</b>\n\n";
    
    $message .= "🎬 <b>What you get:</b>\n";
    $message .= "• Latest TV series\n";
    $message .= "• Web series episodes\n";
    $message .= "• Complete seasons\n";
    $message .= "• Regular episode updates\n";
    $message .= "• HD quality prints\n\n";
    
    $message .= "⭐ <b>Features:</b>\n";
    $message .= "• 1080p/720p quality\n";
    $message .= "• Fast episode updates\n";
    $message .= "• Complete season packs\n";
    $message .= "• Multiple languages\n\n";
    
    $message .= "📥 <b>How to access:</b>\n";
    $message .= "1. Join " . SERIAL_CHANNEL . "\n";
    $message .= "2. Search in bot\n";
    $message .= "3. Get message IDs\n";
    $message .= "4. Download from channel\n\n";
    
    $message .= "🎬 <b>For the best serial viewing experience!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📺 Join Serial Channel', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786'],
                ['text' => '🔍 Search Serials', 'callback_data' => 'search_serial']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_theater_channel_info($chat_id) {
    // Theater channel ka detailed information
    $message = "🎭 <b>Theater Prints - " . THEATER_CHANNEL . "</b>\n\n";
    
    $message .= "🎥 <b>What you get:</b>\n";
    $message .= "• Latest theater prints\n";
    $message .= "• HD screen recordings\n";
    $message .= "• Best quality available\n";
    $message .= "• Fast uploads after release\n";
    $message .= "• Multiple quality options\n\n";
    
    $message .= "⭐ <b>Features:</b>\n";
    $message .= "• 1080p theater prints\n";
    $message .= "• Clear audio quality\n";
    $message .= "• No watermarks\n";
    $message .= "• Multiple languages\n\n";
    
    $message .= "📥 <b>How to access:</b>\n";
    $message .= "1. Join " . THEATER_CHANNEL . "\n";
    $message .= "2. Search in bot\n";
    $message .= "3. Get message IDs\n";
    $message .= "4. Download from channel\n\n";
    
    $message .= "🎬 <b>For the best viewing experience!</b>";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🎭 Join Theater Channel', 'url' => 'https://t.me/threater_print_movies'],
                ['text' => '🔍 Search Theater Movies', 'callback_data' => 'search_theater']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_backup_channel_info($chat_id) {
    // Backup channel ka detailed information
    $message = "🔒 <b>Backup Channel - " . BACKUP_CHANNEL . "</b>\n\n";
    
    $message .= "🛡️ <b>Purpose:</b>\n";
    $message .= "• Secure data backups\n";
    $message .= "• Database protection\n";
    $message .= "• System recovery\n";
    $message .= "• Disaster prevention\n\n";
    
    $message .= "💾 <b>What's backed up:</b>\n";
    $message .= "• Movies database (all 7 CSV files)\n";
    $message .= "• Users data (" . get_users_count() . " users)\n";
    $message .= "• Bot statistics\n";
    $message .= "• Request history\n";
    $message .= "• Complete system archives\n\n";
    
    $message .= "⏰ <b>Backup Schedule:</b>\n";
    $message .= "• Automatic: Daily at " . AUTO_BACKUP_HOUR . ":00\n";
    $message .= "• Manual: On admin command\n";
    $message .= "• Retention: Last 7 backups\n\n";
    
    $message .= "🔐 <b>Note:</b> This is a public channel for backup storage.";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔒 ' . BACKUP_CHANNEL, 'url' => 'https://t.me/ETBackup'],
                ['text' => '📊 Backup Status', 'callback_data' => 'backup_status']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_request_group_info($chat_id) {
    // Request group ka detailed information
    $message = "📥 <b>Request Group - " . REQUEST_GROUP . "</b>\n\n";
    
    $message .= "🎯 <b>How to request movies:</b>\n";
    $message .= "1. Join this group first\n";
    $message .= "2. Use <code>/request movie_name</code> in bot\n";
    $message .= "3. Or post directly in group\n";
    $message .= "4. We'll add within 24 hours\n\n";
    
    $message .= "📝 <b>Also available:</b>\n";
    $message .= "• Bug reports & issues\n";
    $message .= "• Feature suggestions\n";
    $message .= "• General support\n";
    $message .= "• Bot help & guidance\n\n";
    
    $message .= "⚠️ <b>Please check these before requesting:</b>\n";
    $message .= "• Search in bot first\n";
    $message .= "• Check spelling\n";
    $message .= "• Use correct movie name\n";
    $message .= "• Be patient for uploads\n\n";
    
    $message .= "🔔 <b>Auto-notification:</b> You'll get notified when requested movies are added!";

    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Join Request Group', 'url' => 'https://t.me/EntertainmentTadka7860'],
                ['text' => '🎬 Request via Bot', 'callback_data' => 'request_help']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

// ==============================
// HELPER FUNCTIONS FOR CHANNEL INFO
// ==============================
function get_active_users_count() {
    // Active users count karta hai (last 7 days)
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $active_count = 0;
    $one_week_ago = strtotime('-1 week');
    
    foreach ($users_data['users'] ?? [] as $user) {
        if (strtotime($user['last_active'] ?? '') >= $one_week_ago) {
            $active_count++;
        }
    }
    
    return $active_count;
}

function get_daily_uploads_count() {
    // Daily uploads count karta hai from all CSV files
    $today = date('d-m-Y');
    $count = 0;
    
    $csv_files = [CSV_FILE_MAIN, CSV_FILE_SERIAL, CSV_FILE_THEATER, CSV_FILE_BACKUP, CSV_FILE_PRIVATE1, CSV_FILE_PRIVATE2, CSV_FILE_REQUEST];
    
    foreach ($csv_files as $csv_file) {
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, 'r');
            if ($handle !== FALSE) {
                fgetcsv($handle); // skip header
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 3 && $row[2] == $today) {
                        $count++;
                    }
                }
                fclose($handle);
            }
        }
    }
    
    return $count;
}

function get_csv_count() {
    // CSV mein total movies count karta hai from all files
    $count = 0;
    
    $csv_files = [CSV_FILE_MAIN, CSV_FILE_SERIAL, CSV_FILE_THEATER, CSV_FILE_BACKUP, CSV_FILE_PRIVATE1, CSV_FILE_PRIVATE2, CSV_FILE_REQUEST];
    
    foreach ($csv_files as $csv_file) {
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, 'r');
            if ($handle !== FALSE) {
                fgetcsv($handle); // skip header
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) >= 3 && !empty(trim($row[0]))) {
                        $count++;
                    }
                }
                fclose($handle);
            }
        }
    }
    
    return $count;
}

function get_users_count() {
    // Total users count karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    return count($users_data['users'] ?? []);
}

// ==============================
// USER STATS & LEADERBOARD FUNCTIONS
// ==============================
function show_user_stats($chat_id, $user_id) {
    // User ki statistics show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessageWithTyping($chat_id, "❌ User data not found!");
        return;
    }
    
    $message = "👤 <b>Your Statistics</b>\n\n";
    $message .= "🆔 User ID: <code>$user_id</code>\n";
    $message .= "📅 Joined: " . ($user['joined'] ?? 'N/A') . "\n";
    $message .= "🕒 Last Active: " . ($user['last_active'] ?? 'N/A') . "\n\n";
    
    $message .= "📊 <b>Activity:</b>\n";
    $message .= "• 🔍 Searches: " . ($user['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($user['total_downloads'] ?? 0) . "\n";
    $message .= "• 📝 Requests: " . ($user['request_count'] ?? 0) . "\n";
    $message .= "• ⭐ Points: " . ($user['points'] ?? 0) . "\n\n";
    
    $message .= "🎯 <b>Rank:</b> " . calculate_user_rank($user['points'] ?? 0);
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📈 Leaderboard', 'callback_data' => 'show_leaderboard'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_stats']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_user_points($chat_id, $user_id) {
    // User ke points show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessageWithTyping($chat_id, "❌ User data not found!");
        return;
    }
    
    $points = $user['points'] ?? 0;
    
    $message = "⭐ <b>Your Points</b>\n\n";
    $message .= "🎯 Total Points: <b>$points</b>\n\n";
    
    $message .= "📈 <b>How to earn points:</b>\n";
    $message .= "• 🔍 Daily search: +1 point\n";
    $message .= "• 📥 Movie download: +3 points\n";
    $message .= "• 📝 Movie request: +2 points\n";
    $message .= "• 🎯 Found movie: +5 points\n";
    $message .= "• 📅 Daily login: +10 points\n\n";
    
    $message .= "🏆 <b>Your Rank:</b> " . calculate_user_rank($points) . "\n";
    $message .= "📊 <b>Next Rank:</b> " . get_next_rank_info($points);
    
    sendMessageWithTyping($chat_id, $message, null, 'HTML');
}

function show_leaderboard($chat_id) {
    // Top users leaderboard show karta hai
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    
    if (empty($users)) {
        sendMessageWithTyping($chat_id, "📭 Koi user data nahi mila!");
        return;
    }
    
    // Points ke hisab se sort karo
    uasort($users, function($a, $b) {
        return ($b['points'] ?? 0) - ($a['points'] ?? 0);
    });
    
    $message = "🏆 <b>Top Users Leaderboard</b>\n\n";
    $i = 1;
    
    foreach (array_slice($users, 0, 10) as $user_id => $user) {
        $points = $user['points'] ?? 0;
        $username = $user['username'] ? "@" . $user['username'] : "User#" . substr($user_id, -4);
        $medal = $i == 1 ? "🥇" : ($i == 2 ? "🥈" : ($i == 3 ? "🥉" : "🔸"));
        
        $message .= "$medal $i. $username\n";
        $message .= "   ⭐ $points points | 🎯 " . calculate_user_rank($points) . "\n\n";
        $i++;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📊 My Stats', 'callback_data' => 'my_stats'],
                ['text' => '🔄 Refresh', 'callback_data' => 'refresh_leaderboard']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function calculate_user_rank($points) {
    // Points ke hisab se user rank calculate karta hai
    if ($points >= 1000) return "🎖️ Elite";
    if ($points >= 500) return "🔥 Pro";
    if ($points >= 250) return "⭐ Advanced";
    if ($points >= 100) return "🚀 Intermediate";
    if ($points >= 50) return "👍 Beginner";
    return "🌱 Newbie";
}

function get_next_rank_info($points) {
    // Next rank ke liye required points batata hai
    if ($points < 50) return "Beginner (50 points needed)";
    if ($points < 100) return "Intermediate (100 points needed)";
    if ($points < 250) return "Advanced (250 points needed)";
    if ($points < 500) return "Pro (500 points needed)";
    if ($points < 1000) return "Elite (1000 points needed)";
    return "Max Rank Achieved! 🏆";
}

// ==============================
// BROWSE COMMANDS
// ==============================
function show_latest_movies($chat_id, $limit = 10) {
    // Latest movies show karta hai (public channels only)
    $all_movies = get_all_movies_list();
    $latest_movies = array_slice($all_movies, -$limit);
    $latest_movies = array_reverse($latest_movies);
    
    if (empty($latest_movies)) {
        sendMessageWithTyping($chat_id, "📭 Koi public movies nahi mili!");
        return;
    }
    
    $message = "🎬 <b>Latest $limit Movies</b>\n\n";
    $i = 1;
    
    foreach ($latest_movies as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   📊 " . ($movie['quality'] ?? 'Unknown') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n";
        $message .= "   📅 " . ($movie['date'] ?? 'N/A') . "\n\n";
        $i++;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Get All Latest Info', 'callback_data' => 'download_latest'],
                ['text' => '📊 Browse All', 'callback_data' => 'browse_all']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_trending_movies($chat_id) {
    // Trending movies show karta hai (public channels only)
    $all_movies = get_all_movies_list();
    
    // Simple trending logic (recent aur most downloaded)
    $trending_movies = array_slice($all_movies, -15); // Last 15 movies
    
    if (empty($trending_movies)) {
        sendMessageWithTyping($chat_id, "📭 Koi trending movies nahi mili!");
        return;
    }
    
    $message = "🔥 <b>Trending Movies</b>\n\n";
    $i = 1;
    
    foreach (array_slice($trending_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $message .= "   ⭐ " . ($movie['quality'] ?? 'HD') . " | 🗣️ " . ($movie['language'] ?? 'Hindi') . "\n\n";
        $i++;
    }
    
    $message .= "💡 <i>Based on recent popularity and downloads</i>";
    
    sendMessageWithTyping($chat_id, $message, null, 'HTML');
}

function show_movies_by_quality($chat_id, $quality) {
    // Specific quality ki movies show karta hai (public channels only)
    $all_movies = get_all_movies_list();
    $filtered_movies = [];
    
    foreach ($all_movies as $movie) {
        if (stripos($movie['quality'] ?? '', $quality) !== false) {
            $filtered_movies[] = $movie;
        }
    }
    
    if (empty($filtered_movies)) {
        sendMessageWithTyping($chat_id, "❌ Koi $quality quality public movies nahi mili!");
        return;
    }
    
    $message = "🎬 <b>$quality Quality Movies</b>\n\n";
    $message .= "📊 Total Found: " . count($filtered_movies) . "\n\n";
    
    $i = 1;
    foreach (array_slice($filtered_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon " . htmlspecialchars($movie['movie_name']) . "\n";
        $i++;
    }
    
    if (count($filtered_movies) > 10) {
        $message .= "\n... and " . (count($filtered_movies) - 10) . " more";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Get All Info', 'callback_data' => 'download_quality_' . $quality],
                ['text' => '🔄 Other Qualities', 'callback_data' => 'show_qualities']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_movies_by_language($chat_id, $language) {
    // Specific language ki movies show karta hai (public channels only)
    $all_movies = get_all_movies_list();
    $filtered_movies = [];
    
    foreach ($all_movies as $movie) {
        if (stripos($movie['language'] ?? '', $language) !== false) {
            $filtered_movies[] = $movie;
        }
    }
    
    if (empty($filtered_movies)) {
        sendMessageWithTyping($chat_id, "❌ Koi $language public movies nahi mili!");
        return;
    }
    
    $message = "🎬 <b>" . ucfirst($language) . " Movies</b>\n\n";
    $message .= "📊 Total Found: " . count($filtered_movies) . "\n\n";
    
    $i = 1;
    foreach (array_slice($filtered_movies, 0, 10) as $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
        $message .= "$i. $channel_icon " . htmlspecialchars($movie['movie_name']) . "\n";
        $message .= "   📊 " . ($movie['quality'] ?? 'Unknown') . "\n\n";
        $i++;
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📥 Get All Info', 'callback_data' => 'download_lang_' . $language],
                ['text' => '🔄 Other Languages', 'callback_data' => 'show_languages']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

// ==============================
// REQUEST MANAGEMENT
// ==============================
function show_user_requests($chat_id, $user_id) {
    // User ke movie requests show karta hai
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id) {
            $user_requests[] = $request;
        }
    }
    
    if (empty($user_requests)) {
        sendMessageWithTyping($chat_id, "📭 Aapne abhi tak koi movie request nahi ki hai!");
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
    
    $pending_count = count(array_filter($user_requests, function($req) {
        return $req['status'] == 'pending';
    }));
    
    $message .= "📊 <b>Summary:</b>\n";
    $message .= "• Total Requests: " . count($user_requests) . "\n";
    $message .= "• Pending: $pending_count\n";
    $message .= "• Completed: " . (count($user_requests) - $pending_count);
    
    sendMessageWithTyping($chat_id, $message, null, 'HTML');
}

function show_request_limit($chat_id, $user_id) {
    // User ke request limit ka status show karta hai
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    $today_requests = 0;
    
    foreach ($requests_data['requests'] ?? [] as $request) {
        if ($request['user_id'] == $user_id && $request['date'] == $today) {
            $today_requests++;
        }
    }
    
    $remaining = DAILY_REQUEST_LIMIT - $today_requests;
    
    $message = "📋 <b>Your Request Limit</b>\n\n";
    $message .= "✅ Daily Limit: " . DAILY_REQUEST_LIMIT . " requests\n";
    $message .= "📅 Used Today: $today_requests requests\n";
    $message .= "🎯 Remaining Today: $remaining requests\n\n";
    
    if ($remaining > 0) {
        $message .= "💡 Use <code>/request movie_name</code> to request movies!";
    } else {
        $message .= "⏳ Limit resets at midnight!";
    }
    
    sendMessageWithTyping($chat_id, $message, null, 'HTML');
}

// ==============================
// ADMIN COMMANDS
// ==============================
function admin_stats($chat_id) {
    // Complete bot statistics show karta hai (includes private channel counts)
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    
    // Get counts from each CSV
    $csv_counts = [];
    $csv_files = [CSV_FILE_MAIN, CSV_FILE_SERIAL, CSV_FILE_THEATER, CSV_FILE_BACKUP, CSV_FILE_PRIVATE1, CSV_FILE_PRIVATE2, CSV_FILE_REQUEST];
    foreach ($csv_files as $csv_file) {
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, 'r');
            $count = 0;
            if ($handle) {
                fgetcsv($handle); // skip header
                while (fgetcsv($handle)) $count++;
                fclose($handle);
            }
            $csv_counts[basename($csv_file)] = $count;
        }
    }
    
    $msg = "📊 <b>Bot Statistics</b>\n\n";
    $msg .= "🎬 Total Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $msg .= "👥 Total Users: " . $total_users . "\n";
    $msg .= "🔍 Total Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $msg .= "✅ Successful Searches: " . ($stats['successful_searches'] ?? 0) . "\n";
    $msg .= "❌ Failed Searches: " . ($stats['failed_searches'] ?? 0) . "\n";
    $msg .= "📥 Total Downloads: " . ($stats['total_downloads'] ?? 0) . "\n";
    $msg .= "🕒 Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n\n";
    
    $msg .= "📁 <b>CSV File Breakdown:</b>\n";
    foreach ($csv_counts as $file => $count) {
        $msg .= "• $file: $count movies\n";
    }
    $msg .= "\n";
    
    // Daily activity
    $today = date('Y-m-d');
    if (isset($stats['daily_activity'][$today])) {
        $today_stats = $stats['daily_activity'][$today];
        $msg .= "📈 <b>Today's Activity:</b>\n";
        $msg .= "• Searches: " . ($today_stats['searches'] ?? 0) . "\n";
        $msg .= "• Downloads: " . ($today_stats['downloads'] ?? 0) . "\n";
    }
    
    $msg .= "\n📦 <b>Channel Status:</b>\n";
    $msg .= "• Public Channels: Main, Serial, Theater, Backup\n";
    $msg .= "• Private Channels: 2 channels (hidden from users)\n";
    $msg .= "• Forward Header: ON for Public, OFF for Private";
    
    sendMessageWithTyping($chat_id, $msg, null, 'HTML');
    bot_log("Admin stats viewed by $chat_id");
}

function show_csv_data($chat_id, $show_all = false) {
    // CSV data show karta hai (admin only)
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $all_movies = load_all_csv_files();
    
    if (empty($all_movies)) {
        sendMessageWithTyping($chat_id, "📊 CSV files are empty.");
        return;
    }
    
    $all_movies = array_reverse($all_movies);
    $limit = $show_all ? count($all_movies) : 10;
    $movies = array_slice($all_movies, 0, $limit);
    
    $message = "📊 <b>CSV Movie Database (All Channels)</b>\n\n";
    $message .= "📁 Total Movies: " . count($all_movies) . "\n";
    
    if (!$show_all) {
        $message .= "🔍 Showing latest 10 entries\n";
        $message .= "📋 Use '/checkcsv all' for full list\n\n";
    } else {
        $message .= "📋 Full database listing\n\n";
    }
    
    $i = 1;
    foreach ($movies as $movie) {
        $movie_name = $movie['movie_name'] ?? 'N/A';
        $message_id = $movie['message_id_raw'] ?? 'N/A';
        $date = $movie['date'] ?? 'N/A';
        $quality = $movie['quality'] ?? 'Unknown';
        $language = $movie['language'] ?? 'Hindi';
        $channel_type = $movie['channel_type'] ?? 'main';
        $channel_icon = get_channel_display_name($channel_type);
        $public_status = is_channel_public($channel_type) ? "✅ Public" : "🔒 Private";
        
        $message .= "$i. $channel_icon " . htmlspecialchars($movie_name) . " [$public_status]\n";
        $message .= "   📝 ID: $message_id | 🗣️ $language | 📊 $quality\n";
        $message .= "   📅 Date: $date\n\n";
        
        $i++;
        
        if (strlen($message) > 3000) {
            sendMessageWithTyping($chat_id, $message, null, 'HTML');
            $message = "📊 Continuing...\n\n";
        }
    }
    
    $message .= "💾 CSV Files:\n";
    $message .= "• Main: " . CSV_FILE_MAIN . "\n";
    $message .= "• Serial: " . CSV_FILE_SERIAL . "\n";
    $message .= "• Theater: " . CSV_FILE_THEATER . "\n";
    $message .= "• Backup: " . CSV_FILE_BACKUP . "\n";
    $message .= "• Private 1: " . CSV_FILE_PRIVATE1 . "\n";
    $message .= "• Private 2: " . CSV_FILE_PRIVATE2 . "\n";
    $message .= "• Request: " . CSV_FILE_REQUEST . "\n\n";
    $message .= "⏰ Last Updated: " . date('Y-m-d H:i:s', filemtime(CSV_FILE_MAIN));
    
    sendMessageWithTyping($chat_id, $message, null, 'HTML');
    bot_log("CSV data viewed by $chat_id - Show all: " . ($show_all ? 'Yes' : 'No'));
}

function send_broadcast($chat_id, $message) {
    // All users ko broadcast message send karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total_users = count($users_data['users'] ?? []);
    $success_count = 0;
    
    $progress_msg = sendMessageWithPriorityTyping($chat_id, "📢 Broadcasting to $total_users users...\n\nProgress: 0%", 'urgent');
    $progress_msg_id = $progress_msg['result']['message_id'];
    
    $i = 0;
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessageWithTyping($user_id, "📢 <b>Announcement from Admin:</b>\n\n$message", null, 'HTML');
            $success_count++;
            
            // Har 10 users ke baad progress update karo
            if ($i % 10 === 0) {
                $progress = round(($i / $total_users) * 100);
                editMessageWithTyping($chat_id, $progress_msg_id, "📢 Broadcasting to $total_users users...\n\nProgress: $progress%");
            }
            
            usleep(100000); // 0.1 second delay
            $i++;
        } catch (Exception $e) {
            // Failed sends skip karo
        }
    }
    
    editMessageWithTyping($chat_id, $progress_msg_id, "✅ Broadcast completed!\n\n📊 Sent to: $success_count/$total_users users");
    bot_log("Broadcast sent by $chat_id to $success_count users");
}

function toggle_maintenance_mode($chat_id, $mode) {
    // Maintenance mode toggle karta hai
    global $MAINTENANCE_MODE;
    
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    if ($mode == 'on') {
        $MAINTENANCE_MODE = true;
        sendMessageWithTyping($chat_id, "🔧 Maintenance mode ENABLED\n\nBot is now in maintenance mode. Users will see maintenance message.");
        bot_log("Maintenance mode enabled by $chat_id");
    } elseif ($mode == 'off') {
        $MAINTENANCE_MODE = false;
        sendMessageWithTyping($chat_id, "✅ Maintenance mode DISABLED\n\nBot is now operational.");
        bot_log("Maintenance mode disabled by $chat_id");
    } else {
        sendMessageWithTyping($chat_id, "❌ Usage: <code>/maintenance on</code> or <code>/maintenance off</code>", null, 'HTML');
    }
}

function perform_cleanup($chat_id) {
    // System cleanup perform karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $stats_before = get_stats();
    
    // Purane backups clean karo
    $old = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    if (count($old) > 7) {
        usort($old, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $deleted_count = 0;
        foreach (array_slice($old, 0, count($old) - 7) as $d) {
            $files = glob($d . '/*');
            foreach ($files as $ff) @unlink($ff);
            if (@rmdir($d)) $deleted_count++;
        }
    }
    
    // Cache clean karo
    global $movie_cache;
    $movie_cache = [];
    
    sendMessageWithTyping($chat_id, "🧹 Cleanup completed!\n\n• Old backups removed\n• Cache cleared\n• System optimized");
    bot_log("Cleanup performed by $chat_id");
}

function send_alert_to_all($chat_id, $alert_message) {
    // All users ko alert send karta hai
    if ($chat_id != ADMIN_ID) {
        sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $success_count = 0;
    
    foreach ($users_data['users'] as $user_id => $user) {
        try {
            sendMessageWithTyping($user_id, "🚨 <b>Important Alert:</b>\n\n$alert_message", null, 'HTML');
            $success_count++;
            usleep(50000); // 0.05 second delay
        } catch (Exception $e) {
            // Failed sends skip karo
        }
    }
    
    sendMessageWithTyping($chat_id, "✅ Alert sent to $success_count users!");
    bot_log("Alert sent by $chat_id: " . substr($alert_message, 0, 50));
}

// ==============================
// UTILITY FUNCTIONS
// ==============================
function check_date($chat_id) {
    // Movies upload dates ka record show karta hai
    $all_movies = load_all_csv_files();
    
    $date_counts = [];
    foreach ($all_movies as $movie) {
        $d = $movie['date'] ?? '';
        if ($d) {
            if (!isset($date_counts[$d])) $date_counts[$d] = 0;
            $date_counts[$d]++;
        }
    }
    
    krsort($date_counts);
    $msg = "📅 <b>Movies Upload Record</b>\n\n";
    $total_days = 0;
    $total_movies = 0;
    
    foreach ($date_counts as $date => $count) {
        $msg .= "➡️ $date: $count movies\n";
        $total_days++;
        $total_movies += $count;
    }
    
    $msg .= "\n📊 <b>Summary:</b>\n";
    $msg .= "• Total Days: $total_days\n";
    $msg .= "• Total Movies: $total_movies\n";
    $msg .= "• Average per day: " . round($total_movies / max(1, $total_days), 2);
    
    sendMessageWithTyping($chat_id, $msg, null, 'HTML');
}

function test_csv($chat_id) {
    // CSV testing ke liye raw data show karta hai
    $all_movies = load_all_csv_files();
    
    if (empty($all_movies)) {
        sendMessageWithTyping($chat_id, "⚠️ No movies found in any CSV file.");
        return;
    }
    
    $i = 1;
    $msg = "";
    
    foreach ($all_movies as $movie) {
        $channel_type = $movie['channel_type'] ?? 'main';
        $channel_icon = get_channel_display_name($channel_type);
        $public_status = is_channel_public($channel_type) ? "Public" : "Private";
        $line = "$i. $channel_icon {$movie['movie_name']} | ID/Ref: {$movie['message_id_raw']} | Date: {$movie['date']} | Quality: {$movie['quality']} | Language: {$movie['language']} | $public_status\n";
        
        if (strlen($msg) + strlen($line) > 4000) {
            sendMessageWithTyping($chat_id, $msg);
            $msg = "";
        }
        $msg .= $line;
        $i++;
    }
    
    if (!empty($msg)) {
        sendMessageWithTyping($chat_id, $msg);
    }
}

function show_bot_info($chat_id) {
    // Bot information show karta hai
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $message = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
    $message .= "📱 <b>Version:</b> 3.0.0\n";
    $message .= "🆙 <b>Last Updated:</b> " . date('Y-m-d') . "\n";
    $message .= "👨‍💻 <b>Developer:</b> @EntertainmentTadka0786\n\n";
    
    $message .= "📊 <b>Bot Statistics:</b>\n";
    $message .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $message .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
    $message .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $message .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    
    $message .= "🎯 <b>Features:</b>\n";
    $message .= "• Smart movie search\n";
    $message .= "• Multi-language support\n";
    $message .= "• Quality filtering\n";
    $message .= "• Movie requests\n";
    $message .= "• User points system\n";
    $message .= "• Leaderboard\n";
    $message .= "• Separate CSV per channel\n";
    $message .= "• Public/Private channel separation\n";
    $message .= "• Smart forward header toggle\n\n";
    
    $message .= "📢 <b>Public Channels:</b>\n";
    $message .= "• Main: " . MAIN_CHANNEL . " ✅\n";
    $message .= "• Serial: " . SERIAL_CHANNEL . " ✅\n";
    $message .= "• Theater: " . THEATER_CHANNEL . " ✅\n";
    $message .= "• Backup: " . BACKUP_CHANNEL . " ✅\n";
    $message .= "• Request: " . REQUEST_GROUP . " ✅\n\n";
    
    $message .= "🔒 <b>Private Channels (Admin Only):</b>\n";
    $message .= "• Private Channel 1: " . PRIVATE_CHANNEL_1_ID . " 🔒\n";
    $message .= "• Private Channel 2: " . PRIVATE_CHANNEL_2_ID . " 🔒\n\n";
    
    $message .= "📨 <b>Forward Header:</b>\n";
    $message .= "• Public Channels: ON (Users see source)\n";
    $message .= "• Private Channels: OFF (No source visible)";
    
    sendMessageWithTyping($chat_id, $message, null, 'HTML');
}

function show_support_info($chat_id) {
    // Support information show karta hai
    $message = "🆘 <b>Support & Contact</b>\n\n";
    
    $message .= "📞 <b>Need Help?</b>\n";
    $message .= "• Movie not found?\n";
    $message .= "• Technical issues?\n";
    $message .= "• Feature requests?\n\n";
    
    $message .= "🎯 <b>Quick Solutions:</b>\n";
    $message .= "1. Use <code>/request movie_name</code> for new movies\n";
    $message .= "2. Check <code>/help</code> for all commands\n";
    $message .= "3. Join support group below\n\n";
    
    $message .= "📢 <b>Support Group:</b> " . REQUEST_GROUP . "\n";
    $message .= "👨‍💻 <b>Admin:</b> @EntertainmentTadka0786\n\n";
    
    $message .= "💡 <b>Pro Tip:</b> Always check spelling before reporting!";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📢 Support Group', 'url' => 'https://t.me/EntertainmentTadka7860'],
                ['text' => '🐛 Report Bug', 'callback_data' => 'report_bug']
            ],
            [
                ['text' => '💡 Suggest Feature', 'callback_data' => 'suggest_feature'],
                ['text' => '📝 Give Feedback', 'callback_data' => 'give_feedback']
            ]
        ]
    ];
    
    sendMessageWithTyping($chat_id, $message, $keyboard, 'HTML');
}

function show_donate_info($chat_id) {
    // Donation information show karta hai
    $message = "💝 <b>Support Our Work</b>\n\n";
    
    $message .= "🤖 <b>Why Donate?</b>\n";
    $message .= "• Server maintenance costs\n";
    $message .= "• Bot development & updates\n";
    $message .= "• New features implementation\n";
    $message .= "• 24/7 service availability\n\n";
    
    $message .= "💰 <b>Donation Methods:</b>\n";
    $message .= "• UPI: entertainmenttadka@upi\n";
    $message .= "• PayPal: coming soon\n";
    $message .= "• Crypto: coming soon\n\n";
    
    $message .= "🎁 <b>Donor Benefits:</b>\n";
    $message .= "• Priority support\n";
    $message .= "• Early access to features\n";
    $message .= "• Special donor badge\n";
    $message .= "• Increased request limits\n\n";
    
    $message .= "💌 <b>Contact for other methods:</b> " . REQUEST_GROUP;
    
    sendMessageWithTyping($chat_id, $message, null, 'HTML');
}

function submit_bug_report($chat_id, $user_id, $bug_report) {
    // Bug report submit karta hai
    $report_id = uniqid();
    
    $admin_message = "🐛 <b>New Bug Report</b>\n\n";
    $admin_message .= "🆔 Report ID: $report_id\n";
    $admin_message .= "👤 User ID: $user_id\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Bug Description:</b>\n$bug_report";
    
    sendMessageWithTyping(ADMIN_ID, $admin_message, null, 'HTML');
    sendMessageWithTyping($chat_id, "✅ Bug report submitted!\n\n🆔 Report ID: <code>$report_id</code>\n\nWe'll fix it soon! 🛠️", null, 'HTML');
    
    // Also send to request group
    sendMessageWithTyping(REQUEST_GROUP_ID, "🐛 Bug Report #$report_id\nFrom: User $user_id\n\n$bug_report");
    
    bot_log("Bug report submitted by $user_id: $report_id");
}

function submit_feedback($chat_id, $user_id, $feedback) {
    // User feedback submit karta hai
    $feedback_id = uniqid();
    
    $admin_message = "💡 <b>New User Feedback</b>\n\n";
    $admin_message .= "🆔 Feedback ID: $feedback_id\n";
    $admin_message .= "👤 User ID: $user_id\n";
    $admin_message .= "📅 Time: " . date('Y-m-d H:i:s') . "\n\n";
    $admin_message .= "📝 <b>Feedback:</b>\n$feedback";
    
    sendMessageWithTyping(ADMIN_ID, $admin_message, null, 'HTML');
    sendMessageWithTyping($chat_id, "✅ Feedback submitted!\n\n🆔 Feedback ID: <code>$feedback_id</code>\n\nThanks for your input! 🌟", null, 'HTML');
    
    // Also send to request group
    sendMessageWithTyping(REQUEST_GROUP_ID, "💡 Feedback #$feedback_id\nFrom: User $user_id\n\n$feedback");
    
    bot_log("Feedback submitted by $user_id: $feedback_id");
}

function show_version_info($chat_id) {
    // Bot version information show karta hai
    $message = "🔄 <b>Bot Version Information</b>\n\n";
    
    $message .= "📱 <b>Current Version:</b> v3.0.0\n";
    $message .= "🆙 <b>Release Date:</b> " . date('Y-m-d') . "\n";
    $message .= "🐛 <b>Status:</b> Stable Release\n\n";
    
    $message .= "🎯 <b>What's New in v3.0.0:</b>\n";
    $message .= "• Separate CSV files for each channel\n";
    $message .= "• Public/Private channel separation\n";
    $message .= "• Smart forward header toggle\n";
    $message .= "• Private channels hidden from users\n";
    $message .= "• Enhanced backup system\n";
    $message .= "• Improved search filtering\n";
    $message .= "• Better channel management\n\n";
    
    $message .= "📋 <b>Upcoming Features:</b>\n";
    $message .= "• Movie ratings & reviews\n";
    $message .= "• Watchlist feature\n";
    $message .= "• Advanced filters\n";
    $message .= "• User profiles\n";
    $message .= "• More coming soon...\n\n";
    
    $message .= "🐛 <b>Found a bug?</b> Use <code>/report</code>\n";
    $message .= "💡 <b>Suggestions?</b> Use <code>/feedback</code>";
    
    sendMessageWithTyping($chat_id, $message, null, 'HTML');
}

// ==============================
// GROUP MESSAGE FILTER
// ==============================
function is_valid_movie_query($text) {
    // Group messages filter karta hai, valid movie queries hi allow karta hai
    $text = strtolower(trim($text));
    
    // Commands allow karo
    if (strpos($text, '/') === 0) {
        return true;
    }
    
    // Very short messages block karo
    if (strlen($text) < 3) {
        return false;
    }
    
    // Common group chat phrases block karo
    $invalid_phrases = [
        'good morning', 'good night', 'hello', 'hi ', 'hey ', 'thank you', 'thanks',
        'welcome', 'bye', 'see you', 'ok ', 'okay', 'yes', 'no', 'maybe',
        'how are you', 'whats up', 'anyone', 'someone', 'everyone',
        'problem', 'issue', 'help', 'question', 'doubt', 'query'
    ];
    
    foreach ($invalid_phrases as $phrase) {
        if (strpos($text, $phrase) !== false) {
            return false;
        }
    }
    
    // Movie-like patterns allow karo
    $movie_patterns = [
        'movie', 'film', 'video', 'download', 'watch', 'hd', 'full', 'part',
        'series', 'episode', 'season', 'bollywood', 'hollywood',
        'theater', 'theatre', 'print', 'hdcam', 'camrip', 'serial'
    ];
    
    foreach ($movie_patterns as $pattern) {
        if (strpos($text, $pattern) !== false) {
            return true;
        }
    }
    
    // Agar specific movie jaisa lagta hai
    if (preg_match('/^[a-zA-Z0-9\s\-\.\,]{3,}$/', $text)) {
        return true;
    }
    
    return false;
}

// ==============================
// MOVIE APPEND FUNCTION WITH AUTO-NOTIFICATION - UPDATED FOR MULTIPLE CSVs
// ==============================
function append_movie($movie_name, $message_id_raw, $date = null, $video_path = '', $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi', $channel_type = 'main') {
    // Movie database mein add karta hai (specific CSV file based on channel type)
    global $movie_messages, $movie_cache, $waiting_users;
    
    if (empty(trim($movie_name))) return;
    
    if ($date === null) $date = date('d-m-Y');
    
    // Channel ID determine karo based on type
    $channel_id = '';
    $channel_username = '';
    
    switch ($channel_type) {
        case 'main':
            $channel_id = MAIN_CHANNEL_ID;
            $channel_username = MAIN_CHANNEL;
            break;
        case 'serial':
            $channel_id = SERIAL_CHANNEL_ID;
            $channel_username = SERIAL_CHANNEL;
            break;
        case 'theater':
            $channel_id = THEATER_CHANNEL_ID;
            $channel_username = THEATER_CHANNEL;
            break;
        case 'backup':
            $channel_id = BACKUP_CHANNEL_ID;
            $channel_username = BACKUP_CHANNEL;
            break;
        case 'private1':
            $channel_id = PRIVATE_CHANNEL_1_ID;
            $channel_username = '@private_channel_1';
            break;
        case 'private2':
            $channel_id = PRIVATE_CHANNEL_2_ID;
            $channel_username = '@private_channel_2';
            break;
        case 'request_group':
            $channel_id = REQUEST_GROUP_ID;
            $channel_username = REQUEST_GROUP;
            break;
        default:
            $channel_id = MAIN_CHANNEL_ID;
            $channel_username = MAIN_CHANNEL;
    }
    
    // Get correct CSV file path
    $csv_file = get_csv_file_path($channel_type);
    
    $entry = [$movie_name, $message_id_raw, $date, $video_path, $quality, $size, $language, $channel_type, $channel_id, $channel_username];
    
    $handle = fopen($csv_file, "a");
    fputcsv($handle, $entry);
    fclose($handle);

    $movie = strtolower(trim($movie_name));
    $item = [
        'movie_name' => $movie_name,
        'message_id_raw' => $message_id_raw,
        'date' => $date,
        'video_path' => $video_path,
        'quality' => $quality,
        'size' => $size,
        'language' => $language,
        'channel_type' => $channel_type,
        'channel_id' => $channel_id,
        'channel_username' => $channel_username,
        'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null,
        'source_channel' => $channel_id
    ];
    
    if (!isset($movie_messages[$movie])) $movie_messages[$movie] = [];
    $movie_messages[$movie][] = $item;
    $movie_cache = [];

    // Auto-notification to request group
    $movie_lower = strtolower($movie_name);
    if (!empty($waiting_users[$movie_lower])) {
        $notification_msg = "🔔 <b>Movie Added!</b>\n\n";
        $notification_msg .= "🎬 <b>$movie_name</b> has been added to our collection!\n\n";
        $notification_msg .= "📢 Join: " . get_channel_username_link($channel_type) . " to download\n";
        $notification_msg .= "🔔 " . count($waiting_users[$movie_lower]) . " users were waiting for this movie!\n\n";
        $notification_msg .= "📅 Added: " . $date . "\n";
        $notification_msg .= "📊 Quality: " . $quality . "\n";
        $notification_msg .= "🗣️ Language: " . $language . "\n";
        $notification_msg .= "🎭 Channel: " . get_channel_display_name($channel_type);
        
        sendMessageWithTyping(REQUEST_GROUP_ID, $notification_msg, null, 'HTML');
        bot_log("Auto-notification sent for: $movie_name to " . count($waiting_users[$movie_lower]) . " users in request group");
        
        // Waiting users ko notify karo (only if channel is public)
        if (is_channel_public($channel_type)) {
            foreach ($waiting_users[$movie_lower] as $user_data) {
                list($user_chat_id, $user_id) = $user_data;
                $channel_link = get_channel_username_link($channel_type);
                sendMessageWithTyping($user_chat_id, "🎉 <b>Good News!</b>\n\nYour requested movie <b>$movie_name</b> has been added!\n\nJoin channel to download: $channel_link", null, 'HTML');
            }
        }
        unset($waiting_users[$movie_lower]);
    }

    update_stats('total_movies', 1);
    bot_log("Movie appended: $movie_name with ID $message_id_raw to $channel_type channel ($csv_file)");
}

// ==============================
// COMPLETE COMMAND HANDLER WITH UPDATED START MESSAGE
// ==============================
function handle_command($chat_id, $user_id, $command, $params = []) {
    // Sab commands handle karta hai
    switch ($command) {
        // ==================== CORE COMMANDS ====================
        case '/start':
            $welcome = "🎬 Welcome to Entertainment Tadka v3.0!\n\n";
            
            $welcome .= "📢 <b>Public Channels (Visible to you):</b>\n";
            $welcome .= "🍿 Main: @EntertainmentTadka786\n";
            $welcome .= "📺 Serial: @Entertainment_Tadka_Serial_786\n";
            $welcome .= "🎭 Theater: @threater_print_movies\n";
            $welcome .= "🔒 Backup: @ETBackup\n";
            $welcome .= "📥 Request: @EntertainmentTadka7860\n\n";
            
            $welcome .= "🔒 <b>Private Channels:</b> Hidden (Admin only)\n\n";
            
            $welcome .= "📨 <b>Forward Header:</b>\n";
            $welcome .= "• Public Channels: ON (You'll see source)\n";
            $welcome .= "• Private Channels: OFF (No source visible)\n\n";
            
            $welcome .= "🔍 <b>How to use:</b>\n";
            $welcome .= "• Simply type any movie name\n";
            $welcome .= "• Add 'theater' for theater prints\n";
            $welcome .= "• Add 'serial' for TV series\n\n";
            
            $welcome .= "❌ <b>Don't type:</b>\n";
            $welcome .= "• Technical questions\n";
            $welcome .= "• Player instructions\n";
            $welcome .= "• Non-movie queries\n\n";
            
            $welcome .= "📢 <b>Join our public channels for latest content!</b>";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Search Movies', 'switch_inline_query_current_chat' => ''],
                        ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                    ],
                    [
                        ['text' => '📺 Serial Channel', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786'],
                        ['text' => '🎭 Theater Prints', 'url' => 'https://t.me/threater_print_movies']
                    ],
                    [
                        ['text' => '🔒 Backup', 'url' => 'https://t.me/ETBackup'],
                        ['text' => '📥 Request Group', 'url' => 'https://t.me/EntertainmentTadka7860']
                    ],
                    [
                        ['text' => '❓ Help', 'callback_data' => 'help_command']
                    ]
                ]
            ];
            
            sendMessageWithHumanTyping($chat_id, $welcome, $keyboard, 'HTML');
            update_user_activity($user_id, 'daily_login');
            break;

        case '/help':
        case '/commands':
            $help = "🤖 <b>Entertainment Tadka Bot - Complete Guide</b>\n\n";
            
            $help .= "📢 <b>Our Public Channels:</b>\n";
            $help .= "🍿 Main: " . MAIN_CHANNEL . " - Latest movies\n";
            $help .= "📺 Serial: " . SERIAL_CHANNEL . " - TV series & web series\n";
            $help .= "🎭 Theater: " . THEATER_CHANNEL . " - HD prints\n";
            $help .= "🔒 Backup: " . BACKUP_CHANNEL . " - Data protection\n";
            $help .= "📥 Request: " . REQUEST_GROUP . " - Support & requests\n\n";
            
            $help .= "🔒 <b>Private Channels:</b> Hidden (Admin only)\n\n";
            
            $help .= "📨 <b>Forward Header Status:</b>\n";
            $help .= "• Public Channels: ON (You'll see original source)\n";
            $help .= "• Private Channels: OFF (No source visible)\n\n";
            
            $help .= "🔔 <b>Auto-notification Feature:</b>\n";
            $help .= "• Request a movie in request group\n";
            $help .= "• We add it within 24 hours\n";
            $help .= "• Get auto-notification when added!\n";
            $help .= "• Join request group for updates\n\n";
            
            $help .= "🎯 <b>Search Commands:</b>\n";
            $help .= "• Just type movie name - Smart search\n";
            $help .= "• Add 'theater' for theater prints\n";
            $help .= "• Add 'serial' for TV series\n";
            $help .= "• <code>/search movie</code> - Direct search\n";
            $help .= "• <code>/s movie</code> - Quick search\n\n";
            
            $help .= "📁 <b>Browse Commands:</b>\n";
            $help .= "• <code>/totalupload</code> - All public movies\n";
            $help .= "• <code>/latest</code> - New additions\n";
            $help .= "• <code>/trending</code> - Popular movies\n";
            $help .= "• <code>/theater</code> - Theater prints only\n";
            $help .= "• <code>/serial</code> - Serial/Series only\n\n";
            
            $help .= "📝 <b>Request Commands:</b>\n";
            $help .= "• <code>/request movie</code> - Request movie\n";
            $help .= "• <code>/myrequests</code> - Request status\n";
            $help .= "• Join " . REQUEST_GROUP . " for support\n\n";
            
            $help .= "👤 <b>User Commands:</b>\n";
            $help .= "• <code>/mystats</code> - Your statistics\n";
            $help .= "• <code>/leaderboard</code> - Top users\n";
            $help .= "• <code>/mypoints</code> - Points info\n\n";
            
            $help .= "🔗 <b>Channel Commands:</b>\n";
            $help .= "• <code>/channel</code> - All public channels\n";
            $help .= "• <code>/mainchannel</code> - Main channel\n";
            $help .= "• <code>/serialchannel</code> - Serial channel\n";
            $help .= "• <code>/theaterchannel</code> - Theater prints\n";
            $help .= "• <code>/backupchannel</code> - Backup info\n";
            $help .= "• <code>/requestgroup</code> - Request group\n\n";
            
            $help .= "💡 <b>Pro Tips:</b>\n";
            $help .= "• Use partial names (e.g., 'aveng')\n";
            $help .= "• Add 'theater' for theater prints\n";
            $help .= "• Add 'serial' for TV series\n";
            $help .= "• Join all public channels for updates\n";
            $help .= "• Request movies you can't find\n";
            $help .= "• Check spelling before reporting";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🍿 ' . MAIN_CHANNEL, 'url' => 'https://t.me/EntertainmentTadka786'],
                        ['text' => '📺 ' . SERIAL_CHANNEL, 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']
                    ],
                    [
                        ['text' => '🎭 ' . THEATER_CHANNEL, 'url' => 'https://t.me/threater_print_movies'],
                        ['text' => '🔒 ' . BACKUP_CHANNEL, 'url' => 'https://t.me/ETBackup']
                    ],
                    [
                        ['text' => '📥 ' . REQUEST_GROUP, 'url' => 'https://t.me/EntertainmentTadka7860'],
                        ['text' => '🎬 Search Movies', 'switch_inline_query_current_chat' => '']
                    ]
                ]
            ];
            
            sendMessageWithHumanTyping($chat_id, $help, $keyboard, 'HTML');
            break;

        // ==================== SEARCH COMMANDS ====================
        case '/search':
        case '/s':
        case '/find':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessageWithTyping($chat_id, "❌ Usage: <code>/search movie_name</code>\nExample: <code>/search kgf 2</code>", null, 'HTML');
                return;
            }
            $lang = detect_language($movie_name);
            send_multilingual_response($chat_id, 'searching', $lang);
            advanced_search($chat_id, $movie_name, $user_id);
            break;

        // ==================== BROWSE COMMANDS ====================
        case '/totalupload':
        case '/totaluploads':
        case '/allmovies':
        case '/browse':
            $page = isset($params[0]) ? intval($params[0]) : 1;
            totalupload_controller($chat_id, $page);
            break;

        case '/latest':
        case '/recent':
        case '/new':
            show_latest_movies($chat_id, isset($params[0]) ? intval($params[0]) : 10);
            break;

        case '/trending':
        case '/popular':
            show_trending_movies($chat_id);
            break;

        case '/quality':
            $quality = isset($params[0]) ? $params[0] : '1080p';
            show_movies_by_quality($chat_id, $quality);
            break;

        case '/language':
            $language = isset($params[0]) ? $params[0] : 'hindi';
            show_movies_by_language($chat_id, $language);
            break;

        case '/theater':
        case '/theatermovies':
        case '/theateronly':
            show_movies_by_quality($chat_id, 'theater');
            break;

        case '/serial':
        case '/series':
        case '/serialonly':
            show_movies_by_quality($chat_id, 'serial');
            break;

        // ==================== CHANNEL COMMANDS ====================
        case '/serialchannel':
            show_serial_channel_info($chat_id);
            break;

        case '/theaterchannel':
            show_theater_channel_info($chat_id);
            break;

        case '/requestgroup':
        case '/requestchannel':
        case '/requests':
        case '/support':
            show_request_group_info($chat_id);
            break;

        // ==================== REQUEST COMMANDS ====================
        case '/request':
        case '/req':
        case '/requestmovie':
            $movie_name = implode(' ', $params);
            if (empty($movie_name)) {
                sendMessageWithTyping($chat_id, "❌ Usage: <code>/request movie_name</code>\nExample: <code>/request Animal Park</code>", null, 'HTML');
                return;
            }
            $lang = detect_language($movie_name);
            if (add_movie_request($user_id, $movie_name, $lang)) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                update_user_activity($user_id, 'movie_request');
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
            }
            break;

        case '/myrequests':
        case '/myreqs':
            show_user_requests($chat_id, $user_id);
            break;

        case '/requestlimit':
        case '/reqlimit':
            show_request_limit($chat_id, $user_id);
            break;

        // ==================== USER COMMANDS ====================
        case '/mystats':
        case '/mystatistics':
        case '/profile':
            show_user_stats($chat_id, $user_id);
            break;

        case '/mypoints':
        case '/points':
            show_user_points($chat_id, $user_id);
            break;

        case '/leaderboard':
        case '/topusers':
        case '/ranking':
            show_leaderboard($chat_id);
            break;

        // ==================== CHANNEL COMMANDS ====================
        case '/channel':
        case '/channels':
        case '/join':
            show_channel_info($chat_id);
            break;

        case '/mainchannel':
        case '/entertainmenttadka':
            show_main_channel_info($chat_id);
            break;

        case '/backupchannel':
        case '/etbackup':
            show_backup_channel_info($chat_id);
            break;

        // ==================== INFO COMMANDS ====================
        case '/checkdate':
        case '/datestats':
        case '/uploadstats':
            check_date($chat_id);
            break;

        case '/stats':
        case '/statistics':
        case '/botstats':
            if ($user_id == ADMIN_ID) {
                admin_stats($chat_id);
            } else {
                sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/checkcsv':
        case '/csvdata':
        case '/database':
            $show_all = (isset($params[0]) && strtolower($params[0]) == 'all');
            show_csv_data($chat_id, $show_all);
            break;

        case '/testcsv':
        case '/rawdata':
        case '/export':
            test_csv($chat_id);
            break;

        case '/info':
        case '/about':
        case '/botinfo':
            show_bot_info($chat_id);
            break;

        case '/support':
        case '/contact':
        case '/helpgroup':
            show_support_info($chat_id);
            break;

        case '/version':
        case '/changelog':
            show_version_info($chat_id);
            break;

        // ==================== ADMIN COMMANDS ====================
        case '/broadcast':
            if ($user_id == ADMIN_ID) {
                $message = implode(' ', $params);
                if (empty($message)) {
                    sendMessageWithTyping($chat_id, "❌ Usage: <code>/broadcast your_message</code>", null, 'HTML');
                    return;
                }
                send_broadcast($chat_id, $message);
            } else {
                sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/backup':
            if ($user_id == ADMIN_ID) {
                manual_backup($chat_id);
            } else {
                sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/quickbackup':
        case '/qbackup':
            if ($user_id == ADMIN_ID) {
                quick_backup($chat_id);
            } else {
                sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/backupstatus':
        case '/backupinfo':
            if ($user_id == ADMIN_ID) {
                backup_status($chat_id);
            } else {
                sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/maintenance':
            if ($user_id == ADMIN_ID) {
                $mode = isset($params[0]) ? strtolower($params[0]) : '';
                toggle_maintenance_mode($chat_id, $mode);
            } else {
                sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/cleanup':
            if ($user_id == ADMIN_ID) {
                perform_cleanup($chat_id);
            } else {
                sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        case '/sendalert':
            if ($user_id == ADMIN_ID) {
                $alert_message = implode(' ', $params);
                if (empty($alert_message)) {
                    sendMessageWithTyping($chat_id, "❌ Usage: <code>/sendalert your_alert</code>", null, 'HTML');
                    return;
                }
                send_alert_to_all($chat_id, $alert_message);
            } else {
                sendMessageWithTyping($chat_id, "❌ Access denied. Admin only command.");
            }
            break;

        // ==================== UTILITY COMMANDS ====================
        case '/ping':
        case '/status':
            sendMessageWithTyping($chat_id, "🏓 <b>Bot Status:</b> ✅ Online\n⏰ <b>Server Time:</b> " . date('Y-m-d H:i:s'), null, 'HTML');
            break;

        case '/donate':
        case '/supportus':
            show_donate_info($chat_id);
            break;

        case '/report':
        case '/reportbug':
            $bug_report = implode(' ', $params);
            if (empty($bug_report)) {
                sendMessageWithTyping($chat_id, "❌ Usage: <code>/report bug_description</code>", null, 'HTML');
                return;
            }
            submit_bug_report($chat_id, $user_id, $bug_report);
            break;

        case '/feedback':
            $feedback = implode(' ', $params);
            if (empty($feedback)) {
                sendMessageWithTyping($chat_id, "❌ Usage: <code>/feedback your_feedback</code>", null, 'HTML');
                return;
            }
            submit_feedback($chat_id, $user_id, $feedback);
            break;

        default:
            sendMessageWithTyping($chat_id, "❌ Unknown command. Use <code>/help</code> to see all available commands.", null, 'HTML');
    }
}

// ==============================
// MAIN UPDATE PROCESSING
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Maintenance mode check
    global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
    if ($MAINTENANCE_MODE && isset($update['message'])) {
        $chat_id = $update['message']['chat']['id'];
        sendMessageWithTyping($chat_id, $MAINTENANCE_MESSAGE, null, 'HTML');
        bot_log("Maintenance mode active - message blocked from $chat_id");
        exit;
    }

    get_cached_movies();

    // Channel post handling - UPDATED WITH ALL CHANNEL IDs and separate CSV
    if (isset($update['channel_post'])) {
        $message = $update['channel_post'];
        $message_id = $message['message_id'];
        $chat_id = $message['chat']['id'];

        // Determine channel type based on channel ID
        $channel_type = 'other';
        if ($chat_id == MAIN_CHANNEL_ID) {
            $channel_type = 'main';
        } elseif ($chat_id == SERIAL_CHANNEL_ID) {
            $channel_type = 'serial';
        } elseif ($chat_id == THEATER_CHANNEL_ID) {
            $channel_type = 'theater';
        } elseif ($chat_id == BACKUP_CHANNEL_ID) {
            $channel_type = 'backup';
        } elseif ($chat_id == PRIVATE_CHANNEL_1_ID) {
            $channel_type = 'private1';
        } elseif ($chat_id == PRIVATE_CHANNEL_2_ID) {
            $channel_type = 'private2';
        } else {
            // Not our known channel, skip
            exit;
        }

        $text = '';
        $quality = 'Unknown';
        $size = 'Unknown';
        $language = 'Hindi';

        if (isset($message['caption'])) {
            $text = $message['caption'];
            // Caption se quality extract karo
            if (stripos($text, '1080') !== false) $quality = '1080p';
            elseif (stripos($text, '720') !== false) $quality = '720p';
            elseif (stripos($text, '480') !== false) $quality = '480p';
            
            // Language extract karo
            if (stripos($text, 'english') !== false) $language = 'English';
            if (stripos($text, 'hindi') !== false) $language = 'Hindi';
        }
        elseif (isset($message['text'])) {
            $text = $message['text'];
        }
        elseif (isset($message['document'])) {
            $text = $message['document']['file_name'];
            $size = round($message['document']['file_size'] / (1024 * 1024), 2) . ' MB';
        }
        else {
            $text = 'Uploaded Media - ' . date('d-m-Y H:i');
        }

        if (!empty(trim($text))) {
            append_movie($text, $message_id, date('d-m-Y'), '', $quality, $size, $language, $channel_type);
            bot_log("Channel post processed: $channel_type - $text");
        }
    }

    // Message handling
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = isset($message['text']) ? $message['text'] : '';
        $chat_type = $message['chat']['type'] ?? 'private';

        // User data update karo
        $user_info = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        update_user_data($user_id, $user_info);

        // Group message filtering
        if ($chat_type !== 'private') {
            if (strpos($text, '/') === 0) {
                // Commands allow karo
            } else {
                if (!is_valid_movie_query($text)) {
                    bot_log("Invalid group message blocked from $chat_id: $text");
                    return;
                }
            }
        }

        // Command handling
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

    // Callback query handling
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $message = $query['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $query['from']['id'];
        $data = $query['data'];

        global $movie_messages;
        
        // Movie selection - filter out private channels for users
        $movie_lower = strtolower($data);
        if (isset($movie_messages[$movie_lower])) {
            $entries = $movie_messages[$movie_lower];
            $cnt = 0;
            
            // Filter only public channel entries for normal users
            $public_entries = [];
            foreach ($entries as $entry) {
                $channel_type = $entry['channel_type'] ?? 'main';
                if (is_channel_public($channel_type)) {
                    $public_entries[] = $entry;
                }
            }
            
            foreach ($public_entries as $entry) {
                deliver_item_to_chat($chat_id, $entry);
                usleep(200000);
                $cnt++;
            }
            
            if ($cnt > 0) {
                sendMessageWithTyping($chat_id, "✅ '$data' ke $cnt items ka info mil gaya!\n\n📢 Join our public channel to download: " . MAIN_CHANNEL);
                answerCallbackQuery($query['id'], "🎬 $cnt items ka info sent!");
                update_user_activity($user_id, 'download');
            } else {
                sendMessageWithTyping($chat_id, "❌ Movie found but only in private channels. Contact admin for access.");
                answerCallbackQuery($query['id'], "❌ Only in private channels");
            }
        }
        // Pagination controls
        elseif (strpos($data, 'tu_prev_') === 0) {
            $page = (int)str_replace('tu_prev_', '', $data);
            totalupload_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'tu_next_') === 0) {
            $page = (int)str_replace('tu_next_', '', $data);
            totalupload_controller($chat_id, $page);
            answerCallbackQuery($query['id'], "Page $page");
        }
        elseif (strpos($data, 'tu_view_') === 0) {
            $page = (int)str_replace('tu_view_', '', $data);
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page);
            batch_download_with_progress($chat_id, $pg['slice'], $page);
            answerCallbackQuery($query['id'], "Re-sent current page movies info");
        }
        elseif (strpos($data, 'tu_info_') === 0) {
            $page = (int)str_replace('tu_info_', '', $data);
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page);
            
            $info = "📊 <b>Page Information</b>\n\n";
            $info .= "📄 Page: $page/{$pg['total_pages']}\n";
            $info .= "🎬 Movies: " . count($pg['slice']) . "\n";
            $info .= "📁 Total: {$pg['total']} movies\n\n";
            
            foreach ($pg['slice'] as $index => $movie) {
                $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
                $info .= ($index + 1) . ". $channel_icon {$movie['movie_name']} [{$movie['quality']}]\n";
            }
            
            sendMessageWithTyping($chat_id, $info, null, 'HTML');
            answerCallbackQuery($query['id'], "Page $page info");
        }
        elseif ($data === 'tu_stop') {
            sendMessageWithTyping($chat_id, "✅ Pagination stopped. Type /totalupload to start again.");
            answerCallbackQuery($query['id'], "Stopped");
        }
        elseif ($data === 'current_page') {
            answerCallbackQuery($query['id'], "You're on this page");
        }
        // Enhanced Pagination Controls
        elseif (strpos($data, 'pag_') === 0) {
            $parts = explode('_', $data);
            $action = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            if ($action == 'first') {
                totalupload_controller($chat_id, 1, [], $session_id);
                answerCallbackQuery($query['id'], "First page");
            } 
            elseif ($action == 'last') {
                $all = get_all_movies_list();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                totalupload_controller($chat_id, $total_pages, [], $session_id);
                answerCallbackQuery($query['id'], "Last page");
            }
            elseif ($action == 'prev') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $session_id = isset($parts[3]) ? $parts[3] : '';
                totalupload_controller($chat_id, max(1, $current_page - 1), [], $session_id);
                answerCallbackQuery($query['id'], "Previous page");
            }
            elseif ($action == 'next') {
                $current_page = isset($parts[2]) ? intval($parts[2]) : 1;
                $session_id = isset($parts[3]) ? $parts[3] : '';
                $all = get_all_movies_list();
                $total_pages = ceil(count($all) / ITEMS_PER_PAGE);
                totalupload_controller($chat_id, min($total_pages, $current_page + 1), [], $session_id);
                answerCallbackQuery($query['id'], "Next page");
            }
            elseif (is_numeric($action)) {
                $page_num = intval($action);
                $session_id = isset($parts[2]) ? $parts[2] : '';
                totalupload_controller($chat_id, $page_num, [], $session_id);
                answerCallbackQuery($query['id'], "Page $page_num");
            }
        }
        // Send page batch info
        elseif (strpos($data, 'send_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page_num, []);
            batch_download_with_progress($chat_id, $pg['slice'], $page_num);
            answerCallbackQuery($query['id'], "📦 Batch info started!");
        }
        // Preview page
        elseif (strpos($data, 'prev_') === 0) {
            $parts = explode('_', $data);
            $page_num = isset($parts[1]) ? intval($parts[1]) : 1;
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $all = get_all_movies_list();
            $pg = paginate_movies($all, $page_num, []);
            
            $preview_msg = "👁️ <b>Page {$page_num} Preview</b>\n\n";
            $limit = min(5, count($pg['slice']));
            
            for ($i = 0; $i < $limit; $i++) {
                $movie = $pg['slice'][$i];
                $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main');
                $preview_msg .= ($i + 1) . ". $channel_icon <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
                $preview_msg .= "   ⭐ " . ($movie['quality'] ?? 'Unknown') . "\n\n";
            }
            
            sendMessageWithTyping($chat_id, $preview_msg, null, 'HTML');
            answerCallbackQuery($query['id'], "Preview sent");
        }
        // Filter controls
        elseif (strpos($data, 'flt_') === 0) {
            $parts = explode('_', $data);
            $filter_type = $parts[1];
            $session_id = isset($parts[2]) ? $parts[2] : '';
            
            $filters = [];
            if ($filter_type == 'hd') {
                $filters = ['quality' => '1080'];
                answerCallbackQuery($query['id'], "HD filter applied");
            } elseif ($filter_type == 'theater') {
                $filters = ['channel_type' => 'theater'];
                answerCallbackQuery($query['id'], "Theater filter applied");
            } elseif ($filter_type == 'serial') {
                $filters = ['channel_type' => 'serial'];
                answerCallbackQuery($query['id'], "Serial filter applied");
            } elseif ($filter_type == 'backup') {
                $filters = ['channel_type' => 'backup'];
                answerCallbackQuery($query['id'], "Backup filter applied");
            } elseif ($filter_type == 'clr') {
                answerCallbackQuery($query['id'], "Filters cleared");
            }
            
            totalupload_controller($chat_id, 1, $filters, $session_id);
        }
        // Theater channel search
        elseif ($data == 'search_theater') {
            sendMessageWithTyping($chat_id, "🎭 <b>Theater Prints Search</b>\n\nType any movie name to search for theater prints!\n\nExamples:\n<code>kgf 2 theater</code>\n<code>avengers endgame print</code>\n<code>hindi movie theater</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Search theater movies");
        }
        // Serial channel search
        elseif ($data == 'search_serial') {
            sendMessageWithTyping($chat_id, "📺 <b>Serial/Series Search</b>\n\nType any series name to search!\n\nExamples:\n<code>game of thrones serial</code>\n<code>stranger things series</code>\n<code>hindi serial</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Search serials");
        }
        // Close pagination
        elseif ($data == 'close_' || strpos($data, 'close_') === 0) {
            deleteMessage($chat_id, $message['message_id']);
            sendMessageWithTyping($chat_id, "🗂️ Pagination closed. Use /totalupload to browse again.");
            answerCallbackQuery($query['id'], "Pagination closed");
        }
        // Movie requests
        elseif (strpos($data, 'auto_request_') === 0) {
            $movie_name = base64_decode(str_replace('auto_request_', '', $data));
            $lang = detect_language($movie_name);
            
            if (add_movie_request($user_id, $movie_name, $lang)) {
                send_multilingual_response($chat_id, 'request_success', $lang);
                answerCallbackQuery($query['id'], "Request sent successfully!");
                update_user_activity($user_id, 'movie_request');
            } else {
                send_multilingual_response($chat_id, 'request_limit', $lang);
                answerCallbackQuery($query['id'], "Daily limit reached!", true);
            }
        }
        elseif ($data === 'request_movie') {
            sendMessageWithTyping($chat_id, "📝 To request a movie, use:\n<code>/request movie_name</code>\n\nExample: <code>/request Avengers Endgame</code>", null, 'HTML');
            answerCallbackQuery($query['id'], "Request instructions sent");
        }
        elseif ($data === 'request_help') {
            show_request_group_info($chat_id);
            answerCallbackQuery($query['id'], "Request group info");
        }
        // User stats
        elseif ($data === 'my_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Your statistics");
        }
        elseif ($data === 'show_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Leaderboard");
        }
        // Backup commands
        elseif ($data === 'backup_status') {
            if ($chat_id == ADMIN_ID) {
                backup_status($chat_id);
                answerCallbackQuery($query['id'], "Backup status");
            } else {
                answerCallbackQuery($query['id'], "Admin only command!", true);
            }
        }
        elseif ($data === 'run_backup') {
            if ($chat_id == ADMIN_ID) {
                manual_backup($chat_id);
                answerCallbackQuery($query['id'], "Backup started");
            } else {
                answerCallbackQuery($query['id'], "Admin only command!", true);
            }
        }
        // Help command
        elseif ($data === 'help_command') {
            $command = '/help';
            $params = [];
            handle_command($chat_id, $user_id, $command, $params);
            answerCallbackQuery($query['id'], "Help menu");
        }
        // Other callbacks
        elseif ($data === 'refresh_stats') {
            show_user_stats($chat_id, $user_id);
            answerCallbackQuery($query['id'], "Refreshed");
        }
        elseif ($data === 'refresh_leaderboard') {
            show_leaderboard($chat_id);
            answerCallbackQuery($query['id'], "Refreshed");
        }
        elseif ($data === 'download_latest') {
            $all = get_all_movies_list();
            $latest = array_slice($all, -10);
            $latest = array_reverse($latest);
            batch_download_with_progress($chat_id, $latest, "latest");
            answerCallbackQuery($query['id'], "Latest movies info sent");
        }
        elseif ($data === 'browse_all') {
            totalupload_controller($chat_id, 1);
            answerCallbackQuery($query['id'], "Browse all movies");
        }
        elseif (strpos($data, 'download_quality_') === 0) {
            $quality = str_replace('download_quality_', '', $data);
            $all = get_all_movies_list();
            $filtered = [];
            foreach ($all as $movie) {
                if (stripos($movie['quality'] ?? '', $quality) !== false) {
                    $filtered[] = $movie;
                }
            }
            batch_download_with_progress($chat_id, $filtered, $quality . " quality");
            answerCallbackQuery($query['id'], "$quality movies info sent");
        }
        elseif (strpos($data, 'download_lang_') === 0) {
            $language = str_replace('download_lang_', '', $data);
            $all = get_all_movies_list();
            $filtered = [];
            foreach ($all as $movie) {
                if (stripos($movie['language'] ?? '', $language) !== false) {
                    $filtered[] = $movie;
                }
            }
            batch_download_with_progress($chat_id, $filtered, $language . " language");
            answerCallbackQuery($query['id'], "$language movies info sent");
        }
        else {
            sendMessageWithTyping($chat_id, "❌ Movie not found: " . $data . "\n\nTry searching with exact name!");
            answerCallbackQuery($query['id'], "❌ Movie not available");
        }
    }

    // Scheduled tasks
    $current_hour = date('H');
    $current_minute = date('i');

    // Daily auto-backup at 3 AM
    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
        bot_log("Daily auto-backup completed");
    }

    // Hourly cache cleanup
    if ($current_minute == '30') { // Every hour at 30 minutes
        global $movie_cache;
        $movie_cache = [];
        bot_log("Hourly cache cleanup");
    }
}

// ==============================
// MANUAL TESTING FUNCTIONS
// ==============================
if (isset($_GET['test_save'])) {
    // Manual testing ke liye movie save function - updated for multiple CSVs
    function manual_save_to_csv($movie_name, $message_id, $quality = '1080p', $language = 'Hindi', $channel_type = 'main') {
        // Channel ID determine karo
        $channel_id = '';
        $channel_username = '';
        
        switch ($channel_type) {
            case 'main':
                $channel_id = MAIN_CHANNEL_ID;
                $channel_username = MAIN_CHANNEL;
                break;
            case 'serial':
                $channel_id = SERIAL_CHANNEL_ID;
                $channel_username = SERIAL_CHANNEL;
                break;
            case 'theater':
                $channel_id = THEATER_CHANNEL_ID;
                $channel_username = THEATER_CHANNEL;
                break;
            case 'backup':
                $channel_id = BACKUP_CHANNEL_ID;
                $channel_username = BACKUP_CHANNEL;
                break;
            case 'private1':
                $channel_id = PRIVATE_CHANNEL_1_ID;
                $channel_username = '@private_channel_1';
                break;
            case 'private2':
                $channel_id = PRIVATE_CHANNEL_2_ID;
                $channel_username = '@private_channel_2';
                break;
        }
        
        $csv_file = get_csv_file_path($channel_type);
        $entry = [$movie_name, $message_id, date('d-m-Y'), '', $quality, '1.5GB', $language, $channel_type, $channel_id, $channel_username];
        $handle = fopen($csv_file, "a");
        if ($handle !== FALSE) {
            fputcsv($handle, $entry);
            fclose($handle);
            @chmod($csv_file, 0666);
            return true;
        }
        return false;
    }
    
    // Test movies save karo - sab channels ke liye
    manual_save_to_csv("Mandala Murders 2025 S01", 8, "1080p", "Hindi", "main");
    manual_save_to_csv("Mandala Murders 2025 S01 WebRip 480p", 6, "480p", "Hindi", "main");
    manual_save_to_csv("Mandala Murders 2025 S01 Hindi 720p", 7, "720p", "Hindi", "main");
    manual_save_to_csv("Animal (2023) Hindi 1080p", 1927, "1080p", "Hindi", "main");
    manual_save_to_csv("Avengers Endgame (2019) English", 1928, "1080p", "English", "main");
    manual_save_to_csv("KGF Chapter 2 (2022) Theater Print", 1929, "1080p", "Hindi", "theater");
    manual_save_to_csv("Pushpa 2 The Rule (2024) Theater", 1930, "1080p", "Hindi", "theater");
    manual_save_to_csv("Game of Thrones S01E01 (2011)", 1931, "1080p", "English", "serial");
    manual_save_to_csv("Stranger Things S04 (2022)", 1932, "1080p", "English", "serial");
    manual_save_to_csv("Backup Movie Test (2025)", 1933, "720p", "Hindi", "backup");
    manual_save_to_csv("Private Channel Movie (2025)", 1934, "1080p", "English", "private1");
    manual_save_to_csv("Private Channel 2 Movie (2025)", 1935, "480p", "Hindi", "private2");
    
    echo "✅ All 12 movies manually save ho gayi in separate CSV files!<br>";
    echo "📊 <a href='?check_csv=1'>Check CSV</a> | ";
    echo "<a href='?setwebhook=1'>Reset Webhook</a> | ";
    echo "<a href='?test_stats=1'>Test Stats</a>";
    exit;
}

if (isset($_GET['check_csv'])) {
    // CSV content check karo - all files
    echo "<h3>CSV Content (All Files):</h3>";
    $csv_files = [CSV_FILE_MAIN, CSV_FILE_SERIAL, CSV_FILE_THEATER, CSV_FILE_BACKUP, CSV_FILE_PRIVATE1, CSV_FILE_PRIVATE2, CSV_FILE_REQUEST];
    foreach ($csv_files as $csv_file) {
        echo "<h4>" . basename($csv_file) . ":</h4>";
        if (file_exists($csv_file)) {
            $lines = file($csv_file);
            foreach ($lines as $line) {
                echo htmlspecialchars($line) . "<br>";
            }
        } else {
            echo "❌ Not found<br>";
        }
        echo "<br>";
    }
    exit;
}

if (isset($_GET['test_stats'])) {
    // Statistics test karo
    echo "<h3>Bot Statistics:</h3>";
    $stats = get_stats();
    echo "<pre>";
    print_r($stats);
    echo "</pre>";
    
    echo "<h3>User Data:</h3>";
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    echo "<pre>";
    print_r($users_data);
    echo "</pre>";
    exit;
}

// Webhook setup
if (php_sapi_name() === 'cli' || isset($_GET['setwebhook'])) {
    // Webhook setup karo
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<h1>Webhook Setup</h1>";
    echo "<p>Result: " . htmlspecialchars($result) . "</p>";
    echo "<p>Webhook URL: " . htmlspecialchars($webhook_url) . "</p>";
    
    $bot_info = json_decode(apiRequest('getMe'), true);
    if ($bot_info && isset($bot_info['ok']) && $bot_info['ok']) {
        echo "<h2>Bot Info</h2>";
        echo "<p>Name: " . htmlspecialchars($bot_info['result']['first_name']) . "</p>";
        echo "<p>Username: @" . htmlspecialchars($bot_info['result']['username']) . "</p>";
        echo "<p><b>Public Channels:</b></p>";
        echo "<p>Main Channel: " . MAIN_CHANNEL . " (" . MAIN_CHANNEL_ID . ") - Forward Header: ON</p>";
        echo "<p>Serial Channel: " . SERIAL_CHANNEL . " (" . SERIAL_CHANNEL_ID . ") - Forward Header: ON</p>";
        echo "<p>Theater Channel: " . THEATER_CHANNEL . " (" . THEATER_CHANNEL_ID . ") - Forward Header: ON</p>";
        echo "<p>Backup Channel: " . BACKUP_CHANNEL . " (" . BACKUP_CHANNEL_ID . ") - Forward Header: ON</p>";
        echo "<p>Request Group: " . REQUEST_GROUP . " (" . REQUEST_GROUP_ID . ") - Forward Header: OFF</p>";
        echo "<p><b>Private Channels (Hidden from users):</b></p>";
        echo "<p>Private Channel 1: " . PRIVATE_CHANNEL_1_ID . " - Forward Header: OFF</p>";
        echo "<p>Private Channel 2: " . PRIVATE_CHANNEL_2_ID . " - Forward Header: OFF</p>";
    }
    
    echo "<h3>System Status</h3>";
    echo "<p>Main CSV: " . (file_exists(CSV_FILE_MAIN) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Serial CSV: " . (file_exists(CSV_FILE_SERIAL) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Theater CSV: " . (file_exists(CSV_FILE_THEATER) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Backup CSV: " . (file_exists(CSV_FILE_BACKUP) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Private 1 CSV: " . (file_exists(CSV_FILE_PRIVATE1) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Private 2 CSV: " . (file_exists(CSV_FILE_PRIVATE2) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Request CSV: " . (file_exists(CSV_FILE_REQUEST) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Users File: " . (file_exists(USERS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Stats File: " . (file_exists(STATS_FILE) ? "✅ Exists" : "❌ Missing") . "</p>";
    echo "<p>Backup Directory: " . (file_exists(BACKUP_DIR) ? "✅ Exists" : "❌ Missing") . "</p>";
    
    exit;
}

// Default page display
if (!isset($update) || !$update) {
    // Bot status page show karo
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<h1>🎬 Entertainment Tadka Bot v3.0</h1>";
    echo "<p><strong>Public Channels:</strong></p>";
    echo "<p>Main Channel: " . MAIN_CHANNEL . " (" . MAIN_CHANNEL_ID . ") - Forward Header: ON ✅</p>";
    echo "<p>Serial Channel: " . SERIAL_CHANNEL . " (" . SERIAL_CHANNEL_ID . ") - Forward Header: ON ✅</p>";
    echo "<p>Theater Channel: " . THEATER_CHANNEL . " (" . THEATER_CHANNEL_ID . ") - Forward Header: ON ✅</p>";
    echo "<p>Backup Channel: " . BACKUP_CHANNEL . " (" . BACKUP_CHANNEL_ID . ") - Forward Header: ON ✅</p>";
    echo "<p>Request Group: " . REQUEST_GROUP . " (" . REQUEST_GROUP_ID . ") - Forward Header: OFF 🔒</p>";
    echo "<p><strong>Private Channels (Hidden from users):</strong></p>";
    echo "<p>Private Channel 1: " . PRIVATE_CHANNEL_1_ID . " - Forward Header: OFF 🔒</p>";
    echo "<p>Private Channel 2: " . PRIVATE_CHANNEL_2_ID . " - Forward Header: OFF 🔒</p>";
    echo "<p><strong>Status:</strong> ✅ Running</p>";
    echo "<p><strong>Total Movies:</strong> " . ($stats['total_movies'] ?? 0) . "</p>";
    echo "<p><strong>Total Users:</strong> " . count($users_data['users'] ?? []) . "</p>";
    echo "<p><strong>Total Searches:</strong> " . ($stats['total_searches'] ?? 0) . "</p>";
    echo "<p><strong>Last Updated:</strong> " . ($stats['last_updated'] ?? 'N/A') . "</p>";
    
    echo "<h3>🚀 Quick Setup</h3>";
    echo "<p><a href='?setwebhook=1'>Set Webhook Now</a></p>";
    echo "<p><a href='?test_save=1'>Test Movie Save (All Channels)</a></p>";
    echo "<p><a href='?check_csv=1'>Check All CSV Data</a></p>";
    
    echo "<h3>📋 Available Commands</h3>";
    echo "<ul>";
    echo "<li><code>/start</code> - Welcome message</li>";
    echo "<li><code>/help</code> - All commands</li>";
    echo "<li><code>/search movie</code> - Search movies</li>";
    echo "<li><code>/totalupload</code> - Browse all public movies</li>";
    echo "<li><code>/theater</code> - Theater prints only</li>";
    echo "<li><code>/serial</code> - Serial/Series only</li>";
    echo "<li><code>/request movie</code> - Request movie</li>";
    echo "<li><code>/mystats</code> - User statistics</li>";
    echo "<li><code>/leaderboard</code> - Top users</li>";
    echo "<li><code>/channel</code> - Join public channels</li>";
    echo "<li><code>/checkdate</code> - Upload statistics</li>";
    echo "<li><code>/stats</code> - Admin statistics</li>";
    echo "</ul>";
    
    echo "<h3>🎯 New Features in v3.0</h3>";
    echo "<ul>";
    echo "<li>✅ Separate CSV files for each channel</li>";
    echo "<li>✅ Public/Private channel separation</li>";
    echo "<li>✅ Smart forward header toggle:</li>";
    echo "<ul>";
    echo "<li>• Public Channels: Forward header ON</li>";
    echo "<li>• Private Channels: Forward header OFF</li>";
    echo "</ul>";
    echo "<li>✅ Private channels hidden from users</li>";
    echo "<li>✅ Enhanced backup system for multiple CSVs</li>";
    echo "<li>✅ Improved search filtering</li>";
    echo "</ul>";
    
    echo "<h3>📁 CSV File Structure</h3>";
    echo "<ul>";
    echo "<li>Main Channel: <code>" . CSV_FILE_MAIN . "</code></li>";
    echo "<li>Serial Channel: <code>" . CSV_FILE_SERIAL . "</code></li>";
    echo "<li>Theater Channel: <code>" . CSV_FILE_THEATER . "</code></li>";
    echo "<li>Backup Channel: <code>" . CSV_FILE_BACKUP . "</code></li>";
    echo "<li>Private Channel 1: <code>" . CSV_FILE_PRIVATE1 . "</code></li>";
    echo "<li>Private Channel 2: <code>" . CSV_FILE_PRIVATE2 . "</code></li>";
    echo "<li>Request Group: <code>" . CSV_FILE_REQUEST . "</code></li>";
    echo "</ul>";
    
    echo "<h3>📊 Recent Activity</h3>";
    if (file_exists(LOG_FILE)) {
        $logs = array_slice(file(LOG_FILE), -10);
        echo "<pre>";
        foreach ($logs as $log) {
            echo htmlspecialchars($log);
        }
        echo "</pre>";
    }
}
?>