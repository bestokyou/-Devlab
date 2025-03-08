<?php
session_start(); // ต้องเริ่ม session ก่อนใช้งาน
// login.php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        // เพิ่มการสร้าง remember me cookie
        if (isset($_POST['remember_me'])) {
            $token = bin2hex(random_bytes(32)); // สร้าง token แบบสุ่ม
            // บันทึก token ลงในฐานข้อมูล
            $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $user['id']]);
            // สร้าง cookie ที่หมดอายุใน 30 วัน
            setcookie('remember_token', $token, time() + (86400 * 30), '/');
        }
        
        // ตรวจสอบว่าผู้ใช้เป็น admin หรือไม่
        if ($user['is_admin'] == 1) {
            header('Location: admin_dashboard.php');
        } else {
            // ตรวจสอบว่าผู้ใช้เป็นอาจารย์หรือไม่
            $teacherCheckStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
            $teacherCheckStmt->execute([$user['id']]);
            $isTeacher = $teacherCheckStmt->rowCount() > 0;
            
            if ($isTeacher) {
                header('Location: dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
        }
        exit();
    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Login</title>
    <link rel="icon" type="image/png" href="icon1.png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
</head>

<body class="bg-gray-900">
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-96">
            <h2 class="text-2xl mb-6 text-center">Login</h2>
            <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <input type="email" name="email" placeholder="Email" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="relative">
                    <input type="password" name="password" id="password" placeholder="Password" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                    <button type="button" id="togglePassword"
                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-600 hover:text-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"
                            id="eyeIcon">
                            <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                            <path fill-rule="evenodd"
                                d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                clip-rule="evenodd" />
                        </svg>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" viewBox="0 0 20 20"
                            fill="currentColor" id="eyeSlashIcon">
                            <path fill-rule="evenodd"
                                d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z"
                                clip-rule="evenodd" />
                            <path
                                d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
                        </svg>
                    </button>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="remember_me" id="remember_me"
                        class="h-4 w-4 text-blue-500 border-gray-300 rounded">
                    <label for="remember_me" class="ml-2 text-gray-600">
                        Remember me
                    </label>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">
                    Login
                </button>
            </form>
            <p class="mt-4 text-center">
                ยังไม่มีบัญชีใช่ไหม? <a href="register.php" class="text-blue-500">Register</a>
            </p>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButton = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeSlashIcon = document.getElementById('eyeSlashIcon');

        toggleButton.addEventListener('click', function() {
            // Toggle password visibility
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeSlashIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeSlashIcon.classList.add('hidden');
            }
        });
    });
    </script>
</body>

</html>