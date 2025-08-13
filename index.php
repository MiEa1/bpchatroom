<?php
session_start();
if (isset($_SESSION['username'])) {
    header("Location: chat.php");
    exit;
}

// 处理头像上传
$avatarError = '';
$avatarPath = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['username'])) {
    $username = trim($_POST['username']);
    
    if (empty($username)) {
        $error = "请输入昵称";
    } else {
        // 处理头像上传
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['avatar']['type'];
            $fileSize = $_FILES['avatar']['size'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $avatarError = "只允许上传JPG、PNG或GIF格式的图片";
            } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB限制
                $avatarError = "头像大小不能超过5MB";
            } else {
                // 创建uploads目录（如果不存在）
                if (!file_exists('uploads/avatars')) {
                    mkdir('uploads/avatars', 0755, true);
                }
                
                // 生成唯一文件名
                $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $avatarFilename = uniqid() . '.' . $extension;
                $avatarPath = 'uploads/avatars/' . $avatarFilename;
                
                // 移动上传的文件
                if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $avatarPath)) {
                    $avatarError = "头像上传失败，请重试";
                    $avatarPath = '';
                }
            }
        }
        
        // 如果没有上传头像或上传失败，使用默认头像
        if (empty($avatarPath)) {
            $avatarPath = 'assets/default-avatar.png';
        }
        
        if (empty($avatarError)) {
            // 保存用户信息到会话
            $_SESSION['username'] = $username;
            $_SESSION['avatar'] = $avatarPath;
            $_SESSION['last_active'] = time();
            
            // 跳转到聊天室
            header("Location: chat.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>聊天室 - 登录</title>
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
                        light: '#F8FAFC'
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
            .gradient-bg {
                background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            }
            .card-shadow {
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            }
            .input-focus {
                @apply focus:ring-2 focus:ring-primary/50 focus:border-primary focus:outline-none;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl p-8 card-shadow transform transition-all duration-300 hover:scale-[1.02]">
            <div class="text-center mb-6">
                <h1 class="text-[clamp(1.8rem,5vw,2.5rem)] font-bold text-dark mb-2">聊天室</h1>
                <p class="text-gray-500">加入我们，开始聊天吧！</p>
            </div>
            
            <form method="post" enctype="multipart/form-data" class="space-y-6">
                <!-- 头像上传 -->
                <div class="text-center">
                    <label for="avatar" class="cursor-pointer">
                        <div class="w-24 h-24 mx-auto rounded-full bg-gray-200 overflow-hidden border-4 border-primary/20 transition-all duration-300 hover:border-primary">
                            <img id="avatar-preview" src="assets/default-avatar.png" alt="预览头像" class="w-full h-full object-cover">
                        </div>
                        <p class="mt-2 text-sm text-primary font-medium">点击上传头像</p>
                    </label>
                    <input type="file" id="avatar" name="avatar" accept="image/*" class="hidden" onchange="previewAvatar(event)">
                    <?php if (!empty($avatarError)): ?>
                        <p class="mt-1 text-red-500 text-sm"><?php echo htmlspecialchars($avatarError); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- 昵称输入 -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">昵称</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">
                            <i class="fa fa-user"></i>
                        </span>
                        <input type="text" id="username" name="username" required
                            class="w-full pl-10 pr-4 py-3 rounded-lg border border-gray-300 input-focus transition-all"
                            placeholder="请输入你的昵称">
                    </div>
                </div>
                
                <!-- 登录按钮 -->
                <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-3 px-4 rounded-lg transition-all duration-300 transform hover:translate-y-[-2px] flex items-center justify-center">
                    <span>进入聊天室</span>
                    <i class="fa fa-arrow-right ml-2"></i>
                </button>
            </form>
            
            <div class="mt-6 text-center text-sm text-gray-500">
                <p>进入即表示你同意我们的 <a href="#" class="text-primary hover:underline">使用条款</a></p>
            </div>
        </div>
        
        <div class="mt-6 text-center text-gray-500 text-sm">
            <p>支持头像设置、文件发送和语音通话</p>
        </div>
    </div>

    <script>
        // 预览头像
        function previewAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatar-preview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
        
        // 添加页面载入动画
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('opacity-100');
            document.body.classList.remove('opacity-0');
        });
    </script>
</body>
</html>
