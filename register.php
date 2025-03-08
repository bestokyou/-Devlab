<?php
// register.php
require_once 'config.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    // Validate username
    if (strlen($username) < 3) {
        $errors['username'] = "Username must be at least 3 characters long";
    }
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address";
    }
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors['email'] = "This email is already registered";
    }
    // Validate password
    if (strlen($password) < 8) {
        $errors['password'] = "รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร";
    } elseif (!preg_match("/[0-9]/", $password)) {
        $errors['password'] = "รหัสผ่านจะต้องมีตัวเลขอย่างน้อยหนึ่งตัว";
    }
    // Check password confirmation
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    // If no errors, proceed with registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        try {
            $stmt->execute([$username, $email, $hashed_password]);
            $_SESSION['success_message'] = "Registration successful! Please login.";
            header('Location: login.php');
            exit();
        } catch(PDOException $e) {
            $errors['general'] = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Register</title>
    <link rel="icon" type="image/png" href="icon1.png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            <h2 class="text-2xl mb-6 text-center">Register</h2>
            <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <input type="text" name="username" placeholder="Username"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 
                           <?php echo isset($errors['username']) ? 'border-red-500' : ''; ?>">
                    <?php if (isset($errors['username'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo htmlspecialchars($errors['username']); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <input type="email" name="email" placeholder="Email"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500
                           <?php echo isset($errors['email']) ? 'border-red-500' : ''; ?>">
                    <?php if (isset($errors['email'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo htmlspecialchars($errors['email']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="relative">
                    <input type="password" name="password" id="password" placeholder="Password" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500
                           <?php echo isset($errors['password']) ? 'border-red-500' : ''; ?>">
                    <button type="button" onclick="togglePassword('password')"
                        class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if (isset($errors['password'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo htmlspecialchars($errors['password']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="relative">
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500
                           <?php echo isset($errors['confirm_password']) ? 'border-red-500' : ''; ?>">
                    <button type="button" onclick="togglePassword('confirm_password')"
                        class="absolute right-3 top-2.5 text-gray-500 hover:text-gray-700">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if (isset($errors['confirm_password'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo htmlspecialchars($errors['confirm_password']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="text-sm text-gray-600 mt-2">
                    รหัสผ่านจะต้องประกอบด้วย:
                    <ul class="list-disc ml-5">
                        <li>อย่างน้อย 8 ตัวอักษร</li>
                        <li>ตัวเลขอย่างน้อย 1 ตัว</li>
                    </ul>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600">
                    Register
                </button>
            </form>
            <p class="mt-4 text-center">
                มีบัญชีอยู่แล้วใช่ไหม?<a href="login.php" class="text-blue-500">Login</a>
            </p>
        </div>
    </div>
    <script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    </script>
</body>

</html>