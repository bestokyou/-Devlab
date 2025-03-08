<?php
// manage_teachers.php - For admin to manage teachers
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

// Add new teacher
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_teacher'])) {
    $email = trim($_POST['email']);
    $languages = isset($_POST['languages']) ? implode(',', $_POST['languages']) : '';
    
    // Check if user exists
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $userStmt->execute([$email]);
    $userData = $userStmt->fetch();
    
    if (!$userData) {
        $error = "ไม่พบผู้ใช้ด้วยอีเมลนี้";
    } else {
        $userId = $userData['id'];
        
        // Check if user is already a teacher
        $checkStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
        $checkStmt->execute([$userId]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing teacher
            $updateStmt = $pdo->prepare("UPDATE teachers SET languages = ? WHERE user_id = ?");
            $updateStmt->execute([$languages, $userId]);
            $success = "อัปเดตข้อมูลอาจารย์เรียบร้อยแล้ว";
        } else {
            // Add new teacher
            $insertStmt = $pdo->prepare("INSERT INTO teachers (user_id, languages) VALUES (?, ?)");
            $insertStmt->execute([$userId, $languages]);
            $success = "เพิ่มอาจารย์ใหม่เรียบร้อยแล้ว";
        }
    }
}

// Delete teacher
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_teacher'])) {
    $teacherId = (int)$_POST['teacher_id'];
    
    $deleteStmt = $pdo->prepare("DELETE FROM teachers WHERE id = ?");
    $deleteStmt->execute([$teacherId]);
    
    $success = "ลบอาจารย์เรียบร้อยแล้ว";
}

// Get all teachers
$teachersStmt = $pdo->prepare("
    SELECT t.id, t.languages, u.username, u.email 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.username
");
$teachersStmt->execute();
$teachers = $teachersStmt->fetchAll();

// Get all users who are not teachers for dropdown
$usersStmt = $pdo->prepare("
    SELECT u.id, u.username, u.email 
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE t.id IS NULL
    ORDER BY u.username
");
$usersStmt->execute();
$users = $usersStmt->fetchAll();
?>
<!DOCTYPE html>
<html>

<head>
    <title>จัดการอาจารย์ - DevLab</title>
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
                <h1 class="text-2xl font-bold">จัดการอาจารย์</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าผู้ดูแลระบบ</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>

        <!-- Add Teacher Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold mb-4">เพิ่มอาจารย์ใหม่</h2>
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="email" class="block mb-2">อีเมลผู้ใช้</label>
                        <input type="email" name="email" id="email" required
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block mb-2">ภาษาที่สอน</label>
                        <div class="space-y-2">
                            <label class="inline-flex items-center mr-4">
                                <input type="checkbox" name="languages[]" value="html" class="mr-2">
                                HTML
                            </label>
                            <label class="inline-flex items-center mr-4">
                                <input type="checkbox" name="languages[]" value="css" class="mr-2">
                                CSS
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="languages[]" value="php" class="mr-2">
                                PHP
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" name="add_teacher"
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    เพิ่มอาจารย์
                </button>
            </form>
        </div>

        <!-- Teachers List -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">รายชื่ออาจารย์</h2>
            <?php if (count($teachers) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 text-left">ชื่อผู้ใช้</th>
                            <th class="py-3 px-4 text-left">อีเมล</th>
                            <th class="py-3 px-4 text-left">ภาษาที่สอน</th>
                            <th class="py-3 px-4 text-left">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($teachers as $teacher): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4"><?php echo htmlspecialchars($teacher['username']); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($teacher['email']); ?></td>
                            <td class="py-3 px-4">
                                <?php 
                                            $languageArray = explode(',', $teacher['languages']);
                                            foreach ($languageArray as $index => $lang) {
                                                echo '<span class="';
                                                switch ($lang) {
                                                    case 'html': echo 'bg-blue-100 text-blue-800'; break;
                                                    case 'css': echo 'bg-green-100 text-green-800'; break;
                                                    case 'php': echo 'bg-purple-100 text-purple-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                echo ' px-2 py-1 rounded text-xs font-semibold">';
                                                echo strtoupper($lang);
                                                echo '</span>';
                                                echo ($index < count($languageArray) - 1) ? ' ' : '';
                                            }
                                        ?>
                            </td>
                            <td class="py-3 px-4">
                                <form method="POST" action="" class="inline"
                                    onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบอาจารย์คนนี้?');">
                                    <input type="hidden" name="teacher_id" value="<?php echo $teacher['id']; ?>">
                                    <button type="submit" name="delete_teacher" class="text-red-500 hover:text-red-700">
                                        ลบ
                                    </button>
                                </form>
                                <a href="edit_teacher.php?id=<?php echo $teacher['id']; ?>"
                                    class="text-blue-500 hover:text-blue-700 ml-3">
                                    แก้ไข
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-600">ยังไม่มีอาจารย์ในระบบ</p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>