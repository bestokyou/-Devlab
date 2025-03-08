<?php
// manage_roles.php - Page for admins to manage user roles
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

$success = '';
$error = '';

// Update user role
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role'])) {
    $userId = (int)$_POST['user_id'];
    $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
    
    // Update user role
    $updateStmt = $pdo->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    if ($updateStmt->execute([$isAdmin, $userId])) {
        $success = "อัปเดตสิทธิ์ผู้ใช้เรียบร้อยแล้ว";
    } else {
        $error = "เกิดข้อผิดพลาดในการอัปเดตสิทธิ์ผู้ใช้";
    }
}

// Get all users with their roles
$usersStmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.is_admin,
           CASE WHEN t.id IS NOT NULL THEN 1 ELSE 0 END as is_teacher,
           t.languages
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    ORDER BY u.username
");
$usersStmt->execute();
$users = $usersStmt->fetchAll();
?>
<!DOCTYPE html>
<html>

<head>
    <title>จัดการสิทธิ์ผู้ใช้ - DevLab</title>
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
                <h1 class="text-2xl font-bold">จัดการสิทธิ์ผู้ใช้</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าผู้ดูแลระบบ</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold mb-2">รายชื่อผู้ใช้ทั้งหมด</h2>
                <p class="text-gray-600">จัดการสิทธิ์ผู้ใช้ในระบบ</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ชื่อผู้ใช้</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                อีเมล</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                สิทธิ์</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ภาษาที่สอน</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-4 px-4">
                                <?php echo htmlspecialchars($user['username']); ?>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                <span class="ml-2 px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded-full">คุณ</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-gray-500">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </td>
                            <td class="py-4 px-4">
                                <div class="space-y-1">
                                    <?php if ($user['is_admin']): ?>
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                                        ผู้ดูแลระบบ
                                    </span>
                                    <?php endif; ?>

                                    <?php if ($user['is_teacher']): ?>
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                        อาจารย์
                                    </span>
                                    <?php endif; ?>

                                    <?php if (!$user['is_admin'] && !$user['is_teacher']): ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                        นักเรียน
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($user['is_teacher'] && $user['languages']): ?>
                                <?php 
                                            $languages = explode(',', $user['languages']);
                                            foreach ($languages as $language): 
                                                $bgColor = '';
                                                $textColor = '';
                                                switch ($language) {
                                                    case 'html':
                                                        $bgColor = 'bg-blue-100';
                                                        $textColor = 'text-blue-800';
                                                        break;
                                                    case 'css':
                                                        $bgColor = 'bg-green-100';
                                                        $textColor = 'text-green-800';
                                                        break;
                                                    case 'php':
                                                        $bgColor = 'bg-purple-100';
                                                        $textColor = 'text-purple-800';
                                                        break;
                                                    default:
                                                        $bgColor = 'bg-gray-100';
                                                        $textColor = 'text-gray-800';
                                                }
                                        ?>
                                <span
                                    class="inline-block px-2 py-1 text-xs font-medium rounded-full <?php echo $bgColor . ' ' . $textColor; ?> mr-1">
                                    <?php echo strtoupper($language); ?>
                                </span>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <span class="text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <div class="flex items-center">
                                        <label class="inline-flex items-center mr-4">
                                            <input type="checkbox" name="is_admin"
                                                class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded"
                                                <?php echo $user['is_admin'] ? 'checked' : ''; ?>>
                                            <span class="ml-2 text-sm text-gray-700">ผู้ดูแลระบบ</span>
                                        </label>
                                        <button type="submit" name="update_role"
                                            class="text-blue-500 hover:text-blue-700 text-sm">
                                            อัปเดต
                                        </button>
                                    </div>
                                </form>
                                <?php else: ?>
                                <span class="text-gray-500 text-sm">ไม่สามารถแก้ไขสิทธิ์ของตัวเองได้</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Explanation Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h2 class="text-xl font-bold mb-4">คำอธิบายเกี่ยวกับสิทธิ์</h2>
            <div class="space-y-3">
                <div class="flex items-start">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 mt-0.5 mr-2">
                        ผู้ดูแลระบบ
                    </span>
                    <p class="text-gray-600">
                        สามารถจัดการผู้ใช้ทั้งหมด, เพิ่ม/ลบอาจารย์, และดูข้อมูลทั้งหมดในระบบ
                    </p>
                </div>
                <div class="flex items-start">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 mt-0.5 mr-2">
                        อาจารย์
                    </span>
                    <p class="text-gray-600">
                        สามารถสร้างและจัดการโจทย์งานเฉพาะในภาษาที่ได้รับมอบหมาย และตรวจงานนักเรียน
                    </p>
                </div>
                <div class="flex items-start">
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 mt-0.5 mr-2">
                        นักเรียน
                    </span>
                    <p class="text-gray-600">
                        สามารถเรียนบทเรียน, ทำโจทย์งาน, และส่งงานให้อาจารย์ตรวจ
                    </p>
                </div>
            </div>
            <div class="mt-4 p-4 bg-yellow-50 rounded-md">
                <p class="text-yellow-700">
                    <span class="font-bold">หมายเหตุ:</span> การแก้ไขสิทธิ์ผู้ดูแลระบบจะมีผลทันที
                    โปรดใช้งานด้วยความระมัดระวัง
                </p>
            </div>
        </div>
    </div>
</body>

</html>