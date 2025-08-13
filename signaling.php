<?php
// 处理WebRTC信令
$signalingFile = 'signaling.txt';

// 确保文件存在
if (!file_exists($signalingFile)) {
    file_put_contents($signalingFile, json_encode([]));
}

// 处理GET请求 - 获取指定用户的消息
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user'])) {
    $user = $_GET['user'];
    $data = json_decode(file_get_contents($signalingFile), true) ?: [];
    
    // 获取该用户的消息
    $userMessages = [];
    $remainingMessages = [];
    
    foreach ($data as $message) {
        if ($message['to'] === $user) {
            $userMessages[] = $message;
        } else {
            $remainingMessages[] = $message;
        }
    }
    
    // 保存剩余消息
    file_put_contents($signalingFile, json_encode($remainingMessages));
    
    // 返回用户消息
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'messages' => $userMessages
    ]);
    exit;
}

// 处理POST请求 - 发送消息
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = json_decode(file_get_contents('php://input'), true);
    
    if ($message && isset($message['to'], $message['from'], $message['type'])) {
        $data = json_decode(file_get_contents($signalingFile), true) ?: [];
        $data[] = $message;
        
        // 保存消息
        file_put_contents($signalingFile, json_encode($data));
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true
        ]);
        exit;
    }
}

// 无效请求
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => '无效请求'
]);
?>
