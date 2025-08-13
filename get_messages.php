<?php
// 读取最新消息
$messagesFile = 'messages.txt';
$lastId = isset($_GET['lastId']) ? (int)$_GET['lastId'] : 0;

$messages = [];
$response = [
    'success' => false,
    'messages' => []
];

if (file_exists($messagesFile)) {
    $content = file_get_contents($messagesFile);
    $lines = explode("\n", trim($content));
    
    // 收集ID大于lastId的消息
    foreach ($lines as $index => $line) {
        if (!empty($line)) {
            $message = json_decode($line, true);
            if ($message) {
                $messageId = $index + 1; // 简单的ID生成
                if ($messageId > $lastId) {
                    $message['id'] = $messageId;
                    $messages[] = $message;
                }
            }
        }
    }
    
    $response['success'] = true;
    $response['messages'] = $messages;
}

header('Content-Type: application/json');
echo json_encode($response);
?>
