<?php
// create_teacher.php - Page for admins to create teacher accounts
require_once 'config.php';
checkLogin();

// Check if the current user is an admin
$adminCheckStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$adminCheckStmt->execute([$_SESSION['user_id']]);
$user = $adminCheckStmt->fetch();

if (!$user || $user['is_admin'] != 1) {
    $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    header('Location: dashboard.php');
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $languages = isset($_POST['languages']) ? $_POST['languages'] : [];
    
    // Validate form data
    if (empty($username)) {
        $errors['username'] = "กรุณากรอกชื่อผู้ใช้";
    } elseif (strlen($username) < 3) {
        $errors['username'] = "ชื่อผู้ใช้ต้องมีความยาวอย่างน้อย 3 ตัวอักษร";
    }
    
    if (empty($email)) {
        $errors['email'] = "กรุณากรอกอีเมล";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        // Check if email already exists
        $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $checkEmailStmt->execute([$email]);
        if ($checkEmailStmt->rowCount() > 0) {
            $errors['email'] = "อีเมลนี้มีอยู่ในระบบแล้ว";
        }
    }
    
    if (empty($password)) {
        $errors['password'] = "กรุณากรอกรหัสผ่าน";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
    }
    
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "รหัสผ่านยืนยันไม่ตรงกัน";
    }
    
    if (empty($languages)) {
        $errors['languages'] = "กรุณาเลือกอย่างน้อยหนึ่งภาษาที่สอน";
    }
    
    // If no errors, create the account
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Create user account
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $createUserStmt = $pdo->prepare("
                INSERT INTO users (username, email, password, is_admin) 
                VALUES (?, ?, ?, 0)
            ");
            $createUserStmt->execute([$username, $email, $hashedPassword]);
            $userId = $pdo->lastInsertId();
            
            // Set user as teacher
            $languagesList = implode(',', $languages);
            $createTeacherStmt = $pdo->prepare("
                INSERT INTO teachers (user_id, languages) 
                VALUES (?, ?)
            ");
            $createTeacherStmt->execute([$userId, $languagesList]);
            
            $pdo->commit();
            
            $success = "สร้างบัญชีอาจารย์เรียบร้อยแล้ว";
            
            // Clear form
            unset($username, $email, $password, $confirm_password, $languages);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = "เกิดข้อผิดพลาดในการสร้างบัญชี: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>สร้างบัญชีอาจารย์ - DevLab</title>
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
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8 pb-6 border-b bg-white shadow p-5 rounded">
            <div class="flex items-center">
                <a href="dashboard.php">
                    <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                </a>
                <h1 class="text-2xl font-bold">สร้างบัญชีอาจารย์</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าผู้ดูแลระบบ</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if (!empty($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"
                role="alert">
                <span class="block sm:inline"><?php echo $success; ?></span>
            </div>
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $errors['general']; ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Username -->
                    <div>
                        <label for="username" class="block text-gray-700 font-medium mb-2">ชื่อผู้ใช้ <span
                                class="text-red-500">*</span></label>
                        <input type="text" id="username" name="username"
                            value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['username']) ? 'border-red-500' : ''; ?>">
                        <?php if (isset($errors['username'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['username']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-gray-700 font-medium mb-2">อีเมล <span
                                class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email"
                            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['email']) ? 'border-red-500' : ''; ?>">
                        <?php if (isset($errors['email'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['email']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-gray-700 font-medium mb-2">รหัสผ่าน <span
                                class="text-red-500">*</span></label>
                        <input type="password" id="password" name="password"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['password']) ? 'border-red-500' : ''; ?>">
                        <?php if (isset($errors['password'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['password']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="block text-gray-700 font-medium mb-2">ยืนยันรหัสผ่าน <span
                                class="text-red-500">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['confirm_password']) ? 'border-red-500' : ''; ?>">
                        <?php if (isset($errors['confirm_password'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo $errors['confirm_password']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Teaching Languages -->
                <div class="mb-6">
                    <label class="block text-gray-700 font-medium mb-2">ภาษาที่สอน <span
                            class="text-red-500">*</span></label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="languages[]" value="html" class="h-5 w-5 text-blue-600"
                                <?php echo (isset($languages) && in_array('html', $languages)) ? 'checked' : ''; ?>>
                            <span>HTML</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="languages[]" value="css" class="h-5 w-5 text-green-600"
                                <?php echo (isset($languages) && in_array('css', $languages)) ? 'checked' : ''; ?>>
                            <span>CSS</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="languages[]" value="php" class="h-5 w-5 text-purple-600"
                                <?php echo (isset($languages) && in_array('php', $languages)) ? 'checked' : ''; ?>>
                            <span>PHP</span>
                        </label>
                    </div>
                    <?php if (isset($errors['languages'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo $errors['languages']; ?></p>
                    <?php endif; ?>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end">
                    <a href="admin_dashboard.php"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 mr-2 hover:bg-gray-100">
                        ยกเลิก
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        สร้างบัญชี
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>