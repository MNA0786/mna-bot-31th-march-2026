<?php
// Simple test script to verify bot is working
$input = file_get_contents('php://input');
$update = json_decode($input, true);

// Log everything
file_put_contents('test_log.txt', date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents('test_log.txt', "Input: " . $input . "\n", FILE_APPEND);

if ($update && isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
    
    // Send a test reply
    $token = getenv('BOT_TOKEN');
    $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$chat_id&text=✅ Bot is working! You said: $text";
    
    $result = file_get_contents($url);
    file_put_contents('test_log.txt', "Reply sent: " . $result . "\n\n", FILE_APPEND);
}

echo "OK";
