<?php
// edit_teacher.php - For admin to edit teacher information
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

// Check if teacher ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ไม่พบข้อมูลอาจารย์";
    header('Location: manage_teachers.php');
    exit();
}

$teacherId = (int)$_GET['id'];

// Get teacher information
$teacherStmt = $pdo->prepare("
    SELECT t.id, t.user_id, t.languages, u.username, u.email 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
");
$teacherStmt->execute([$teacherId]);
$teacher = $teacherStmt->fetch();

if (!$teacher) {
    $_SESSION['error_message'] = "ไม่พบข้อมูลอาจารย์";
    header('Location: manage_teachers.php');
    exit();
}

// Update teacher information
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_teacher'])) {
    $languages = isset($_POST['languages']) ? implode(',', $_POST['languages']) : '';
    
    $updateStmt = $pdo->prepare("UPDATE teachers SET languages = ? WHERE id = ?");
    $updateStmt->execute([$languages, $teacherId]);
    
    $_SESSION['success_message'] = "อัปเดตข้อมูลอาจารย์เรียบร้อยแล้ว";
    header('Location: manage_teachers.php');
    exit();
}

// Get the languages the teacher can teach
$teacherLanguages = explode(',', $teacher['languages']);
?>
<!DOCTYPE html>
<html>

<head>
    <title>แก้ไขข้อมูลอาจารย์ - DevLab</title>
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
                <h1 class="text-2xl font-bold">แก้ไขข้อมูลอาจารย์</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="manage_teachers.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าจัดการอาจารย์</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Edit Teacher Form -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-bold mb-4">แก้ไขข้อมูลอาจารย์</h2>
            <div class="mb-6">
                <p class="text-gray-700"><strong>ชื่อผู้ใช้:</strong>
                    <?php echo htmlspecialchars($teacher['username']); ?></p>
                <p class="text-gray-700"><strong>อีเมล:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>
            </div>
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block mb-2">ภาษาที่สอน</label>
                    <div class="space-y-2">
                        <label class="inline-flex items-center mr-4">
                            <input type="checkbox" name="languages[]" value="html" class="mr-2"
                                <?php echo in_array('html', $teacherLanguages) ? 'checked' : ''; ?>>
                            HTML
                        </label>
                        <label class="inline-flex items-center mr-4">
                            <input type="checkbox" name="languages[]" value="css" class="mr-2"
                                <?php echo in_array('css', $teacherLanguages) ? 'checked' : ''; ?>>
                            CSS
                        </label>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="languages[]" value="php" class="mr-2"
                                <?php echo in_array('php', $teacherLanguages) ? 'checked' : ''; ?>>
                            PHP
                        </label>
                    </div>
                </div>
                <div class="flex gap-4">
                    <button type="submit" name="update_teacher"
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        บันทึกการเปลี่ยนแปลง
                    </button>
                    <a href="manage_teachers.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        ยกเลิก
                    </a>
                </div>
            </form>
        </div>

        <!-- Teacher's Courses Section (Optional) -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">บทเรียนที่สอนโดยอาจารย์</h2>

            <?php
            // Get lessons taught by this teacher (based on languages they teach)
            $languageList = "'" . implode("','", $teacherLanguages) . "'";
            $lessonsStmt = $pdo->prepare("
                SELECT * FROM lessons 
                WHERE language IN ($languageList)
                ORDER BY language, order_num
            ");
            $lessonsStmt->execute();
            $lessons = $lessonsStmt->fetchAll();
            
            if (count($lessons) > 0):
            ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-3 px-4 text-left">ภาษา</th>
                            <th class="py-3 px-4 text-left">หัวข้อ</th>
                            <th class="py-3 px-4 text-left">ลำดับ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($lessons as $lesson): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <span class="<?php 
                                    switch ($lesson['language']) {
                                        case 'html': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'css': echo 'bg-green-100 text-green-800'; break;
                                        case 'php': echo 'bg-purple-100 text-purple-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                ?> px-2 py-1 rounded text-xs font-semibold">
                                    <?php echo strtoupper($lesson['language']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($lesson['title']); ?></td>
                            <td class="py-3 px-4"><?php echo $lesson['order_num']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-600">ยังไม่มีบทเรียนที่สอนโดยอาจารย์ท่านนี้</p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>