<?php
// 获取在线用户列表
$usersFile = 'users.txt';
$response = [
    'success' => false,
    'users' => []
];

if (file_exists($usersFile)) {
    $content = file_get_contents($usersFile);
    $users = json_decode($content, true) ?: [];
    
    // 格式化用户数据
    $userList = [];
    foreach ($users as $name => $data) {
        $userList[] = [
            'name' => $name,
            'avatar' => $data['avatar'],
            'last_active' => $data['last_active']
        ];
    }
    
    // 按名称排序
    usort($userList, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
    
    $response['success'] = true;
    $response['users'] = $userList;
}

header('Content-Type: application/json');
echo json_encode($response);
?>
