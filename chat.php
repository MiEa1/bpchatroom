<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

// 处理用户状态
$username = $_SESSION['username'];
$avatar = $_SESSION['avatar'];
$_SESSION['last_active'] = time();

// 初始化数据文件
$usersFile = 'users.txt';
$messagesFile = 'messages.txt';

// 确保上传目录存在
if (!file_exists('uploads/files')) {
    mkdir('uploads/files', 0755, true);
}

// 保存用户信息
$users = [];
if (file_exists($usersFile)) {
    $usersData = file_get_contents($usersFile);
    $users = json_decode($usersData, true) ?: [];
}

// 更新当前用户信息
$users[$username] = [
    'avatar' => $avatar,
    'last_active' => time()
];

// 清理 inactive 用户（5分钟无活动）
$expiryTime = time() - 300;
foreach ($users as $user => $data) {
    if ($data['last_active'] < $expiryTime) {
        unset($users[$user]);
        
        // 记录用户离开消息
        if (file_exists($messagesFile)) {
            $messages = file_get_contents($messagesFile);
        } else {
            $messages = '';
        }
        
        $leaveMessage = json_encode([
            'type' => 'system',
            'content' => $user . ' 离开了聊天室',
            'time' => time()
        ]) . "\n";
        
        file_put_contents($messagesFile, $messages . $leaveMessage, LOCK_EX);
    }
}

// 保存更新后的用户列表
file_put_contents($usersFile, json_encode($users), LOCK_EX);

// 处理新消息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && !empty($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        // 获取现有消息
        if (file_exists($messagesFile)) {
            $messages = file_get_contents($messagesFile);
        } else {
            $messages = '';
        }
        
        // 创建新消息
        $newMessage = json_encode([
            'type' => 'message',
            'username' => $username,
            'avatar' => $avatar,
            'content' => $message,
            'time' => time()
        ]) . "\n";
        
        // 保存消息
        file_put_contents($messagesFile, $messages . $newMessage, LOCK_EX);
    }
    
    // 防止表单重提交
    header("Location: chat.php");
    exit;
}

// 处理文件上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $maxFileSize = 100 * 1024 * 1024; // 100MB
    
    if ($file['size'] > $maxFileSize) {
        $error = "文件大小不能超过100MB";
    } else {
        // 生成唯一文件名
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . ($extension ? '.' . $extension : '');
        $filePath = 'uploads/files/' . $filename;
        
        // 移动上传的文件
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // 记录文件消息
            if (file_exists($messagesFile)) {
                $messages = file_get_contents($messagesFile);
            } else {
                $messages = '';
            }
            
            $fileMessage = json_encode([
                'type' => 'file',
                'username' => $username,
                'avatar' => $avatar,
                'original_name' => $file['name'],
                'file_path' => $filePath,
                'file_size' => $file['size'],
                'time' => time()
            ]) . "\n";
            
            file_put_contents($messagesFile, $messages . $fileMessage, LOCK_EX);
        } else {
            $error = "文件上传失败，请重试";
        }
    }
    
    // 防止表单重提交
    header("Location: chat.php");
    exit;
}

// 处理用户离开
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // 记录用户离开消息
    if (file_exists($messagesFile)) {
        $messages = file_get_contents($messagesFile);
    } else {
        $messages = '';
    }
    
    $leaveMessage = json_encode([
        'type' => 'system',
        'content' => $username . ' 离开了聊天室',
        'time' => time()
    ]) . "\n";
    
    file_put_contents($messagesFile, $messages . $leaveMessage, LOCK_EX);
    
    // 从用户列表中移除
    unset($users[$username]);
    file_put_contents($usersFile, json_encode($users), LOCK_EX);
    
    // 销毁会话
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>聊天室 - 正在聊天中</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#10B981',
                        dark: '#1E293B',
                        light: '#F8FAFC',
                        'message-self': '#E0E7FF',
                        'message-other': '#F3F4F6'
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .content-auto {
                content-visibility: auto;
            }
            .scrollbar-thin {
                scrollbar-width: thin;
            }
            .scrollbar-thin::-webkit-scrollbar {
                width: 4px;
                height: 4px;
            }
            .scrollbar-thin::-webkit-scrollbar-thumb {
                background-color: rgba(156, 163, 175, 0.5);
                border-radius: 2px;
            }
            .pulse-animation {
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.5;
                }
            }
            .slide-up {
                animation: slideUp 0.3s ease-out forwards;
            }
            @keyframes slideUp {
                from {
                    transform: translateY(10px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col text-dark">
    <!-- 顶部导航 -->
    <header class="bg-white border-b border-gray-200 shadow-sm">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center">
                <h1 class="text-xl font-bold text-primary flex items-center">
                    <i class="fa fa-comments-o mr-2"></i>
                    <span>聊天室</span>
                </h1>
            </div>
            
            <div class="flex items-center space-x-4">
                <!-- 语音通话按钮 -->
                <button id="start-call-btn" class="bg-green-500 hover:bg-green-600 text-white p-2 rounded-full transition-all duration-300 transform hover:scale-110">
                    <i class="fa fa-phone"></i>
                </button>
                
                <!-- 用户信息 -->
                <div class="flex items-center">
                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="<?php echo htmlspecialchars($username); ?>" class="w-8 h-8 rounded-full object-cover border-2 border-primary">
                    <span class="ml-2 font-medium hidden md:inline"><?php echo htmlspecialchars($username); ?></span>
                </div>
                
                <!-- 退出按钮 -->
                <a href="?action=logout" class="text-gray-500 hover:text-red-500 transition-colors">
                    <i class="fa fa-sign-out"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- 主内容区 -->
    <main class="flex-1 flex flex-col md:flex-row overflow-hidden">
        <!-- 在线用户列表 -->
        <aside class="w-full md:w-64 bg-white border-r border-gray-200 shadow-sm md:h-[calc(100vh-61px)] overflow-hidden flex flex-col">
            <div class="p-4 border-b border-gray-200">
                <h2 class="font-bold text-gray-700 flex items-center">
                    <i class="fa fa-users mr-2 text-primary"></i>
                    在线用户
                    <span id="user-count" class="ml-2 bg-primary/10 text-primary text-xs px-2 py-1 rounded-full">
                        <?php echo count($users); ?>
                    </span>
                </h2>
            </div>
            
            <div id="user-list" class="flex-1 overflow-y-auto scrollbar-thin p-2">
                <?php foreach ($users as $user => $data): ?>
                    <div class="flex items-center p-2 rounded-lg hover:bg-gray-100 transition-colors mb-1">
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars($data['avatar']); ?>" alt="<?php echo htmlspecialchars($user); ?>" class="w-10 h-10 rounded-full object-cover">
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white pulse-animation"></span>
                        </div>
                        <div class="ml-3">
                            <p class="font-medium text-sm <?php echo $user === $username ? 'text-primary' : ''; ?>">
                                <?php echo htmlspecialchars($user); ?>
                                <?php echo $user === $username ? '<span class="text-xs bg-primary/10 px-1.5 py-0.5 rounded ml-1">(你)</span>' : ''; ?>
                            </p>
                            <p class="text-xs text-gray-500">在线</p>
                        </div>
                        
                        <!-- 语音通话按钮 -->
                        <?php if ($user !== $username): ?>
                            <button class="ml-auto text-gray-400 hover:text-green-500 transition-colors start-call" data-user="<?php echo htmlspecialchars($user); ?>">
                                <i class="fa fa-phone"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>
        
        <!-- 聊天区域 -->
        <section class="flex-1 flex flex-col bg-gray-50 h-[calc(100vh-61px)] overflow-hidden">
            <!-- 通话界面 (默认隐藏) -->
            <div id="call-interface" class="hidden absolute inset-0 bg-dark z-50 flex flex-col items-center justify-center text-white">
                <div class="absolute top-4 right-4">
                    <button id="end-call-btn" class="bg-red-500 hover:bg-red-600 text-white p-3 rounded-full transition-all">
                        <i class="fa fa-phone rotate-135"></i>
                    </button>
                </div>
                
                <div id="call-status" class="text-xl mb-8">正在呼叫...</div>
                
                <div class="flex flex-col md:flex-row items-center gap-8">
                    <div class="w-64 h-64 bg-gray-700 rounded-lg overflow-hidden relative">
                        <div id="local-video" class="w-full h-full object-cover">
                            <!-- 本地视频将在这里显示 -->
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fa fa-user text-6xl text-gray-400"></i>
                            </div>
                        </div>
                        <div class="absolute bottom-2 left-2 bg-black/50 px-2 py-1 rounded text-sm">你</div>
                    </div>
                    
                    <div class="w-64 h-64 bg-gray-700 rounded-lg overflow-hidden relative">
                        <div id="remote-video" class="w-full h-full object-cover">
                            <!-- 远程视频将在这里显示 -->
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fa fa-user-o text-6xl text-gray-400"></i>
                            </div>
                        </div>
                        <div id="remote-user" class="absolute bottom-2 left-2 bg-black/50 px-2 py-1 rounded text-sm">对方</div>
                    </div>
                </div>
                
                <div class="mt-8 flex gap-4">
                    <button id="mute-audio-btn" class="bg-gray-700 hover:bg-gray-600 p-3 rounded-full transition-all">
                        <i class="fa fa-microphone"></i>
                    </button>
                    <button id="disable-video-btn" class="bg-gray-700 hover:bg-gray-600 p-3 rounded-full transition-all">
                        <i class="fa fa-video-camera"></i>
                    </button>
                </div>
            </div>
            
            <!-- 消息区域 -->
            <div id="messages-container" class="flex-1 overflow-y-auto scrollbar-thin p-4 md:p-6">
                <div id="messages" class="max-w-3xl mx-auto space-y-6">
                    <!-- 系统消息：用户加入 -->
                    <div class="slide-up">
                        <div class="bg-gray-200/50 text-gray-600 text-sm rounded-full px-4 py-1.5 inline-block">
                            你已加入聊天室
                        </div>
                    </div>
                    
                    <!-- 历史消息将通过JS加载 -->
                </div>
            </div>
            
            <!-- 输入区域 -->
            <div class="border-t border-gray-200 bg-white p-4">
                <form id="message-form" class="space-y-3">
                    <!-- 文件上传 -->
                    <div class="flex items-center space-x-3">
                        <label for="file-upload" class="text-primary hover:text-primary/80 cursor-pointer transition-colors">
                            <i class="fa fa-paperclip text-lg"></i>
                        </label>
                        <input type="file" id="file-upload" class="hidden" onchange="handleFileSelected(event)">
                        <div id="file-preview" class="hidden flex-1 bg-gray-100 rounded-lg p-2 text-sm">
                            <div class="flex items-center">
                                <i class="fa fa-file-o text-gray-500 mr-2"></i>
                                <span id="file-name" class="truncate"></span>
                                <button type="button" id="cancel-file" class="ml-auto text-gray-400 hover:text-red-500">
                                    <i class="fa fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 消息输入和发送 -->
                    <div class="flex items-end space-x-3">
                        <textarea id="message-input" name="message" rows="1" 
                            class="flex-1 border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-primary/50 focus:border-primary focus:outline-none transition-all resize-none"
                            placeholder="输入消息..."></textarea>
                        <button type="submit" class="bg-primary hover:bg-primary/90 text-white p-3 rounded-lg transition-all transform hover:scale-105">
                            <i class="fa fa-paper-plane"></i>
                        </button>
                    </div>
                    
                    <!-- 文件上传表单 (隐藏) -->
                    <form id="file-form" method="post" enctype="multipart/form-data" class="hidden">
                        <input type="file" id="file-input" name="file">
                    </form>
                </form>
            </div>
        </section>
    </main>

    <script>
        // 当前用户名
        const currentUser = "<?php echo htmlspecialchars($username); ?>";
        let lastMessageId = 0;
        let checkInterval;
        let peerConnection;
        let localStream;
        let remoteStream;
        let isAudioMuted = false;
        let isVideoDisabled = false;
        let targetUser = null;
        
        // 格式化文件大小
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // 格式化时间
        function formatTime(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        
        // 加载消息
        function loadMessages() {
            fetch('get_messages.php?lastId=' + lastMessageId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        const messagesContainer = document.getElementById('messages');
                        let shouldScroll = false;
                        
                        // 检查是否应该自动滚动到底部
                        const container = document.getElementById('messages-container');
                        if (container.scrollTop + container.clientHeight >= container.scrollHeight - 100) {
                            shouldScroll = true;
                        }
                        
                        // 添加新消息
                        data.messages.forEach(message => {
                            lastMessageId = message.id;
                            const messageElement = createMessageElement(message);
                            messagesContainer.appendChild(messageElement);
                            
                            // 添加动画
                            setTimeout(() => {
                                messageElement.classList.add('slide-up');
                            }, 10);
                        });
                        
                        // 如果需要，滚动到底部
                        if (shouldScroll) {
                            container.scrollTop = container.scrollHeight;
                        }
                    }
                })
                .catch(error => console.error('Error loading messages:', error));
        }
        
        // 更新用户列表
        function updateUserList() {
            fetch('get_users.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const userList = document.getElementById('user-list');
                        const userCount = document.getElementById('user-count');
                        
                        // 更新用户数量
                        userCount.textContent = data.users.length;
                        
                        // 清空现有列表
                        userList.innerHTML = '';
                        
                        // 添加用户
                        data.users.forEach(user => {
                            const userElement = document.createElement('div');
                            userElement.className = 'flex items-center p-2 rounded-lg hover:bg-gray-100 transition-colors mb-1';
                            
                            userElement.innerHTML = `
                                <div class="relative">
                                    <img src="${user.avatar}" alt="${user.name}" class="w-10 h-10 rounded-full object-cover">
                                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-white pulse-animation"></span>
                                </div>
                                <div class="ml-3">
                                    <p class="font-medium text-sm ${user.name === currentUser ? 'text-primary' : ''}">
                                        ${user.name}
                                        ${user.name === currentUser ? '<span class="text-xs bg-primary/10 px-1.5 py-0.5 rounded ml-1">(你)</span>' : ''}
                                    </p>
                                    <p class="text-xs text-gray-500">在线</p>
                                </div>
                                ${user.name !== currentUser ? `
                                    <button class="ml-auto text-gray-400 hover:text-green-500 transition-colors start-call" data-user="${user.name}">
                                        <i class="fa fa-phone"></i>
                                    </button>
                                ` : ''}
                            `;
                            
                            userList.appendChild(userElement);
                            
                            // 添加点击事件
                            if (user.name !== currentUser) {
                                const callButton = userElement.querySelector('.start-call');
                                callButton.addEventListener('click', () => {
                                    startCall(user.name);
                                });
                            }
                        });
                    }
                })
                .catch(error => console.error('Error updating user list:', error));
        }
        
        // 创建消息元素
        function createMessageElement(message) {
            const div = document.createElement('div');
            div.className = 'opacity-0 transition-opacity duration-300';
            
            if (message.type === 'system') {
                // 系统消息
                div.innerHTML = `
                    <div class="bg-gray-200/50 text-gray-600 text-sm rounded-full px-4 py-1.5 inline-block">
                        ${message.content}
                    </div>
                `;
            } else if (message.type === 'file') {
                // 文件消息
                const isSelf = message.username === currentUser;
                const alignClass = isSelf ? 'flex-row-reverse' : 'flex-row';
                const bgClass = isSelf ? 'bg-message-self' : 'bg-message-other';
                const marginClass = isSelf ? 'mr-0 ml-2' : 'ml-0 mr-2';
                
                div.innerHTML = `
                    <div class="flex ${alignClass} items-start">
                        <img src="${message.avatar}" alt="${message.username}" class="w-8 h-8 rounded-full object-cover ${marginClass}">
                        <div class="max-w-[80%]">
                            <div class="flex items-center mb-1">
                                <span class="font-medium text-sm ${isSelf ? 'text-primary' : 'text-gray-700'}">${message.username}</span>
                                <span class="text-xs text-gray-400 ml-2">${formatTime(message.time)}</span>
                            </div>
                            <div class="${bgClass} rounded-lg p-3 shadow-sm">
                                <a href="${message.file_path}" target="_blank" class="flex items-center">
                                    <i class="fa fa-file-o text-gray-500 mr-3 text-xl"></i>
                                    <div>
                                        <div class="text-sm font-medium">${message.original_name}</div>
                                        <div class="text-xs text-gray-500">${formatFileSize(message.file_size)}</div>
                                    </div>
                                    <i class="fa fa-download ml-auto text-gray-400"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // 文本消息
                const isSelf = message.username === currentUser;
                const alignClass = isSelf ? 'flex-row-reverse' : 'flex-row';
                const bgClass = isSelf ? 'bg-message-self' : 'bg-message-other';
                const marginClass = isSelf ? 'mr-0 ml-2' : 'ml-0 mr-2';
                
                div.innerHTML = `
                    <div class="flex ${alignClass} items-start">
                        <img src="${message.avatar}" alt="${message.username}" class="w-8 h-8 rounded-full object-cover ${marginClass}">
                        <div class="max-w-[80%]">
                            <div class="flex items-center mb-1">
                                <span class="font-medium text-sm ${isSelf ? 'text-primary' : 'text-gray-700'}">${message.username}</span>
                                <span class="text-xs text-gray-400 ml-2">${formatTime(message.time)}</span>
                            </div>
                            <div class="${bgClass} rounded-lg p-3 shadow-sm">
                                <p class="text-sm">${message.content.replace(/\n/g, '<br>')}</p>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            return div;
        }
        
        // 处理文件选择
        function handleFileSelected(event) {
            const file = event.target.files[0];
            if (file) {
                const filePreview = document.getElementById('file-preview');
                const fileName = document.getElementById('file-name');
                
                fileName.textContent = file.name;
                filePreview.classList.remove('hidden');
                
                // 取消文件选择
                document.getElementById('cancel-file').addEventListener('click', () => {
                    filePreview.classList.add('hidden');
                    document.getElementById('file-upload').value = '';
                });
                
                // 自动提交文件
                const fileForm = document.getElementById('file-form');
                const fileInput = document.getElementById('file-input');
                
                // 克隆文件到表单输入
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                fileInput.files = dataTransfer.files;
                
                // 提交表单
                fileForm.submit();
            }
        }
        
        // 初始化消息输入框自动调整高度
        function initMessageInput() {
            const textarea = document.getElementById('message-input');
            
            textarea.addEventListener('input', () => {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            });
            
            // 按Enter发送消息，Shift+Enter换行
            textarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    document.getElementById('message-form').dispatchEvent(new Event('submit'));
                }
            });
        }
        
        // 初始化消息表单提交
        function initMessageForm() {
            const form = document.getElementById('message-form');
            
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                const input = document.getElementById('message-input');
                const message = input.value.trim();
                
                if (message) {
                    // 创建表单数据
                    const formData = new FormData();
                    formData.append('message', message);
                    
                    // 发送消息
                    fetch('chat.php', {
                        method: 'POST',
                        body: formData
                    }).then(() => {
                        // 清空输入框
                        input.value = '';
                        input.style.height = 'auto';
                        
                        // 加载新消息
                        loadMessages();
                    }).catch(error => console.error('Error sending message:', error));
                }
            });
        }
        
        // WebRTC相关函数
        async function startCall(user) {
            targetUser = user;
            document.getElementById('remote-user').textContent = user;
            document.getElementById('call-status').textContent = `正在呼叫 ${user}...`;
            document.getElementById('call-interface').classList.remove('hidden');
            
            try {
                // 获取本地媒体流
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
                document.getElementById('local-video').innerHTML = '';
                
                const videoElement = document.createElement('video');
                videoElement.srcObject = localStream;
                videoElement.autoplay = true;
                videoElement.muted = true; //  mute local video to prevent echo
                document.getElementById('local-video').appendChild(videoElement);
                
                // 初始化PeerConnection
                initPeerConnection();
                
                // 发送offer
                createAndSendOffer();
                
            } catch (error) {
                console.error('Error starting call:', error);
                alert('无法访问摄像头/麦克风，请确保已授予权限。');
                endCall();
            }
        }
        
        function initPeerConnection() {
            const configuration = {
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ]
            };
            
            peerConnection = new RTCPeerConnection(configuration);
            
            // 添加本地流到连接
            if (localStream) {
                localStream.getTracks().forEach(track => {
                    peerConnection.addTrack(track, localStream);
                });
            }
            
            // 处理远程流
            peerConnection.ontrack = (event) => {
                remoteStream = event.streams[0];
                document.getElementById('remote-video').innerHTML = '';
                
                const videoElement = document.createElement('video');
                videoElement.srcObject = remoteStream;
                videoElement.autoplay = true;
                document.getElementById('remote-video').appendChild(videoElement);
                
                document.getElementById('call-status').textContent = '正在通话中...';
            };
            
            // 处理ICE候选
            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignalingData({
                        type: 'candidate',
                        to: targetUser,
                        candidate: event.candidate
                    });
                }
            };
            
            // 处理连接状态变化
            peerConnection.onconnectionstatechange = () => {
                if (peerConnection.connectionState === 'disconnected' || 
                    peerConnection.connectionState === 'failed') {
                    endCall();
                }
            };
        }
        
        async function createAndSendOffer() {
            try {
                const offer = await peerConnection.createOffer();
                await peerConnection.setLocalDescription(offer);
                
                sendSignalingData({
                    type: 'offer',
                    to: targetUser,
                    sdp: offer.sdp
                });
            } catch (error) {
                console.error('Error creating offer:', error);
            }
        }
        
        async function handleOffer(offer, fromUser) {
            targetUser = fromUser;
            document.getElementById('remote-user').textContent = fromUser;
            document.getElementById('call-status').textContent = `${fromUser} 正在呼叫你...`;
            document.getElementById('call-interface').classList.remove('hidden');
            
            try {
                // 获取本地媒体流
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
                document.getElementById('local-video').innerHTML = '';
                
                const videoElement = document.createElement('video');
                videoElement.srcObject = localStream;
                videoElement.autoplay = true;
                videoElement.muted = true;
                document.getElementById('local-video').appendChild(videoElement);
                
                // 初始化PeerConnection
                initPeerConnection();
                
                // 设置远程描述
                await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
                
                // 创建并发送应答
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                
                sendSignalingData({
                    type: 'answer',
                    to: targetUser,
                    sdp: answer.sdp
                });
                
            } catch (error) {
                console.error('Error handling offer:', error);
                alert('无法访问摄像头/麦克风，请确保已授予权限。');
                endCall();
            }
        }
        
        async function handleAnswer(answer) {
            if (peerConnection) {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
                document.getElementById('call-status').textContent = '正在通话中...';
            }
        }
        
        async function handleCandidate(candidate) {
            if (peerConnection && candidate) {
                try {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                } catch (error) {
                    console.error('Error adding ICE candidate:', error);
                }
            }
        }
        
        function sendSignalingData(data) {
            // 添加发送者信息
            data.from = currentUser;
            
            // 发送信令数据到服务器
            fetch('signaling.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            }).catch(error => console.error('Error sending signaling data:', error));
        }
        
        function checkSignalingMessages() {
            fetch('signaling.php?user=' + encodeURIComponent(currentUser))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            switch (message.type) {
                                case 'offer':
                                    handleOffer({ sdp: message.sdp }, message.from);
                                    break;
                                case 'answer':
                                    handleAnswer({ sdp: message.sdp });
                                    break;
                                case 'candidate':
                                    handleCandidate(message.candidate);
                                    break;
                                case 'endCall':
                                    endCall();
                                    break;
                            }
                        });
                    }
                })
                .catch(error => console.error('Error checking signaling messages:', error));
        }
        
        function endCall() {
            // 发送结束通话信号
            if (targetUser) {
                sendSignalingData({
                    type: 'endCall',
                    to: targetUser
                });
            }
            
            // 关闭媒体流
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            
            // 关闭连接
            if (peerConnection) {
                peerConnection.close();
                peerConnection = null;
            }
            
            // 重置UI
            document.getElementById('call-interface').classList.add('hidden');
            document.getElementById('local-video').innerHTML = `
                <div class="w-full h-full flex items-center justify-center">
                    <i class="fa fa-user text-6xl text-gray-400"></i>
                </div>
            `;
            document.getElementById('remote-video').innerHTML = `
                <div class="w-full h-full flex items-center justify-center">
                    <i class="fa fa-user-o text-6xl text-gray-400"></i>
                </div>
            `;
            
            targetUser = null;
            isAudioMuted = false;
            isVideoDisabled = false;
            
            // 重置按钮状态
            document.getElementById('mute-audio-btn').innerHTML = '<i class="fa fa-microphone"></i>';
            document.getElementById('disable-video-btn').innerHTML = '<i class="fa fa-video-camera"></i>';
        }
        
        function toggleAudio() {
            if (localStream) {
                isAudioMuted = !isAudioMuted;
                localStream.getAudioTracks().forEach(track => {
                    track.enabled = !isAudioMuted;
                });
                
                const icon = document.getElementById('mute-audio-btn').querySelector('i');
                if (isAudioMuted) {
                    icon.className = 'fa fa-microphone-slash';
                } else {
                    icon.className = 'fa fa-microphone';
                }
            }
        }
        
        function toggleVideo() {
            if (localStream) {
                isVideoDisabled = !isVideoDisabled;
                localStream.getVideoTracks().forEach(track => {
                    track.enabled = !isVideoDisabled;
                });
                
                const icon = document.getElementById('disable-video-btn').querySelector('i');
                if (isVideoDisabled) {
                    icon.className = 'fa fa-video-camera fa-ban';
                } else {
                    icon.className = 'fa fa-video-camera';
                }
            }
        }
        
        // 初始化通话按钮事件
        function initCallButtons() {
            // 开始通话按钮
            document.getElementById('start-call-btn').addEventListener('click', () => {
                alert('请从在线用户列表中选择要通话的用户');
            });
            
            // 结束通话按钮
            document.getElementById('end-call-btn').addEventListener('click', endCall);
            
            // 静音按钮
            document.getElementById('mute-audio-btn').addEventListener('click', toggleAudio);
            
            // 关闭视频按钮
            document.getElementById('disable-video-btn').addEventListener('click', toggleVideo);
        }
        
        // 初始化应用
        function initApp() {
            // 加载历史消息
            loadMessages();
            
            // 初始化用户列表
            updateUserList();
            
            // 初始化消息输入
            initMessageInput();
            
            // 初始化消息表单
            initMessageForm();
            
            // 初始化通话按钮
            initCallButtons();
            
            // 设置定期检查新消息和用户列表
            checkInterval = setInterval(() => {
                loadMessages();
                updateUserList();
                checkSignalingMessages();
            }, 2000);
            
            // 页面关闭时清理
            window.addEventListener('beforeunload', () => {
                clearInterval(checkInterval);
                if (peerConnection) {
                    endCall();
                }
            });
        }
        
        // 启动应用
        document.addEventListener('DOMContentLoaded', initApp);
    </script>
</body>
</html>
