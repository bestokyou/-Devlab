<?php
// change_password.php
require_once 'config.php';
checkLogin();
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($update_stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                    $message = "Password successfully updated!";
                } else {
                    $error = "Failed to update password. Please try again.";
                }
            } else {
                $error = "New password must be at least 6 characters long.";
            }
        } else {
            $error = "New passwords do not match.";
        }
    } else {
        $error = "Current password is incorrect.";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8 pb-6 border-b bg-white shadow p-5 rounded">
            <div class="flex items-center">
                <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                <h1 class="text-2xl font-bold">Change Password</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="profile.php" class="text-blue-500 hover:text-blue-700">Back to Profile</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>
        <!-- Password Change Form -->
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md p-6">
            <?php if ($message): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700 mb-2">รหัสผ่านปัจจุบัน</label>
                    <input type="password" name="current_password" required
                        class="w-full px-4 py-2 border rounded focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" required
                        class="w-full px-4 py-2 border rounded focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" required
                        class="w-full px-4 py-2 border rounded focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                    Change Password
                </button>
            </form>
        </div>
    </div>
</body>

</html>