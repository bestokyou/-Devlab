<?php
// manage_users.php - Manage users page
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

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    
    // Don't allow admin to delete themselves
    if ($userId == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "ไม่สามารถลบบัญชีของตัวเองได้";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete related records first (due to foreign key constraints)
            // Delete from progress
            $stmt = $pdo->prepare("DELETE FROM progress WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete from lesson_drafts
            $stmt = $pdo->prepare("DELETE FROM lesson_drafts WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete from test_results and related test_answers
            $stmt = $pdo->prepare("DELETE FROM test_results WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Finally delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "ลบผู้ใช้เรียบร้อยแล้ว";
        } catch (PDOException $e) {
            // Rollback in case of error
            $pdo->rollBack();
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบผู้ใช้: " . $e->getMessage();
        }
    }
    
    // Redirect to refresh the page
    header('Location: manage_users.php');
    exit();
}

// Get all users excluding current admin
$currentUserId = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT id, username, email, created_at, 
    CASE WHEN is_admin = 1 THEN 'ผู้ดูแลระบบ' ELSE 'ผู้ใช้งานทั่วไป' END as role
    FROM users 
    ORDER BY id
");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>

<head>
    <title>จัดการผู้ใช้งาน - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <script>
    function confirmDelete(userId, username) {
        return confirm(
            `คุณแน่ใจหรือไม่ที่จะลบผู้ใช้ "${username}"? การดำเนินการนี้ไม่สามารถเรียกคืนได้ และข้อมูลทั้งหมดของผู้ใช้นี้จะถูกลบออกจากระบบ`
        );
    }
    </script>
</head>

<body class="bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8 pb-6 border-b bg-white shadow p-5 rounded">
            <div class="flex items-center">
                <a href="dashboard.php">
                    <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                </a>
                <h1 class="text-2xl font-bold">จัดการผู้ใช้งาน</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าผู้ดูแลระบบ</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded shadow" role="alert">
            <p><?php echo $_SESSION['success_message']; ?></p>
        </div>
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded shadow" role="alert">
            <p><?php echo $_SESSION['error_message']; ?></p>
        </div>
        <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Users List -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold">รายชื่อผู้ใช้งานทั้งหมด</h2>
                <!-- Search form can be added here -->
            </div>

            <!-- Users Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 bg-gray-100 font-semibold text-sm text-gray-700 border-b text-left">ID
                            </th>
                            <th class="py-3 px-4 bg-gray-100 font-semibold text-sm text-gray-700 border-b text-left">
                                ชื่อผู้ใช้</th>
                            <th class="py-3 px-4 bg-gray-100 font-semibold text-sm text-gray-700 border-b text-left">
                                อีเมล</th>
                            <th class="py-3 px-4 bg-gray-100 font-semibold text-sm text-gray-700 border-b text-left">
                                บทบาท</th>
                            <th class="py-3 px-4 bg-gray-100 font-semibold text-sm text-gray-700 border-b text-left">
                                วันที่สร้าง</th>
                            <th class="py-3 px-4 bg-gray-100 font-semibold text-sm text-gray-700 border-b text-center">
                                การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 border-b text-sm"><?php echo $user['id']; ?></td>
                            <td class="py-3 px-4 border-b text-sm"><?php echo htmlspecialchars($user['username']); ?>
                            </td>
                            <td class="py-3 px-4 border-b text-sm"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="py-3 px-4 border-b text-sm"><?php echo $user['role']; ?></td>
                            <td class="py-3 px-4 border-b text-sm">
                                <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                            <td class="py-3 px-4 border-b text-sm text-center">
                                <?php if ($user['id'] != $currentUserId): ?>
                                <form method="post"
                                    onsubmit="return confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="text-red-500 hover:text-red-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        ลบ
                                    </button>
                                </form>
                                <?php else: ?>
                                <span class="text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    บัญชีปัจจุบัน
                                </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" class="py-4 px-4 text-center text-gray-500">ไม่พบข้อมูลผู้ใช้งาน</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>