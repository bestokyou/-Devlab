<?php
// edit_worksheet.php - Page for teachers to edit worksheets
require_once 'config.php';
checkLogin();

// Check if the current user is a teacher
$teacherCheckStmt = $pdo->prepare("
    SELECT t.id, t.languages 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE u.id = ?
");
$teacherCheckStmt->execute([$_SESSION['user_id']]);
$teacher = $teacherCheckStmt->fetch();

if (!$teacher) {
    $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้ เฉพาะอาจารย์เท่านั้น";
    header('Location: dashboard.php');
    exit();
}

// Get teacher's languages
$teacherLanguages = explode(',', $teacher['languages']);

// Get worksheet ID from URL parameter
$worksheetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify that the worksheet exists and belongs to this teacher
$worksheetStmt = $pdo->prepare("
    SELECT * FROM worksheets
    WHERE id = ? AND teacher_id = ?
");
$worksheetStmt->execute([$worksheetId, $teacher['id']]);
$worksheet = $worksheetStmt->fetch();

if (!$worksheet) {
    $_SESSION['error_message'] = "ไม่พบโจทย์งานที่ต้องการแก้ไขหรือคุณไม่มีสิทธิ์แก้ไขโจทย์นี้";
    header('Location: teacher_dashboard.php');
    exit();
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate form data
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $language = $_POST['language'];
    $starter_code = isset($_POST['starter_code']) ? trim($_POST['starter_code']) : '';
    $expected_output = isset($_POST['expected_output']) ? trim($_POST['expected_output']) : '';
    $difficulty = $_POST['difficulty'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    
    // Validate required fields
    if (empty($title)) {
        $errors['title'] = "กรุณาระบุชื่อโจทย์";
    }
    
    if (empty($description)) {
        $errors['description'] = "กรุณาระบุคำอธิบายโจทย์";
    }
    
    if (empty($language)) {
        $errors['language'] = "กรุณาเลือกภาษา";
    } elseif (!in_array($language, $teacherLanguages)) {
        $errors['language'] = "คุณไม่มีสิทธิ์สร้างโจทย์ในภาษานี้";
    }
    
    // If no errors, update the worksheet
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE worksheets 
                SET title = ?, description = ?, language = ?, starter_code = ?, 
                    expected_output = ?, difficulty = ?, due_date = ?
                WHERE id = ? AND teacher_id = ?
            ");
            $stmt->execute([
                $title,
                $description,
                $language,
                $starter_code,
                $expected_output,
                $difficulty,
                $due_date,
                $worksheetId,
                $teacher['id']
            ]);
            
            $_SESSION['success_message'] = "อัปเดตโจทย์งานเรียบร้อยแล้ว";
            header('Location: teacher_dashboard.php');
            exit();
        } catch (PDOException $e) {
            $errors['general'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        }
    }
} else {
    // Pre-fill the form with existing worksheet data
    $_POST['title'] = $worksheet['title'];
    $_POST['description'] = $worksheet['description'];
    $_POST['language'] = $worksheet['language'];
    $_POST['starter_code'] = $worksheet['starter_code'];
    $_POST['expected_output'] = $worksheet['expected_output'];
    $_POST['difficulty'] = $worksheet['difficulty'];
    $_POST['due_date'] = $worksheet['due_date'];
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>แก้ไขโจทย์งาน - DevLab</title>
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
                <h1 class="text-2xl font-bold">แก้ไขโจทย์งาน</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="teacher_dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าอาจารย์</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Main Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($errors['general']); ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-gray-700 font-medium mb-2">ชื่อโจทย์ <span
                                class="text-red-500">*</span></label>
                        <input type="text" id="title" name="title"
                            value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['title']) ? 'border-red-500' : ''; ?>">
                        <?php if (isset($errors['title'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo htmlspecialchars($errors['title']); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Language Selection -->
                    <div>
                        <label for="language" class="block text-gray-700 font-medium mb-2">ภาษา <span
                                class="text-red-500">*</span></label>
                        <select id="language" name="language"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['language']) ? 'border-red-500' : ''; ?>">
                            <option value="">เลือกภาษา</option>
                            <?php foreach ($teacherLanguages as $lang): ?>
                            <option value="<?php echo $lang; ?>"
                                <?php echo (isset($_POST['language']) && $_POST['language'] === $lang) ? 'selected' : ''; ?>>
                                <?php 
                                        switch ($lang) {
                                            case 'html': echo 'HTML'; break;
                                            case 'css': echo 'CSS'; break;
                                            case 'php': echo 'PHP'; break;
                                            default: echo strtoupper($lang);
                                        }
                                    ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['language'])): ?>
                        <p class="text-red-500 text-sm mt-1"><?php echo htmlspecialchars($errors['language']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-gray-700 font-medium mb-2">คำอธิบายโจทย์ <span
                            class="text-red-500">*</span></label>
                    <textarea id="description" name="description" rows="6"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo isset($errors['description']) ? 'border-red-500' : ''; ?>"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <p class="text-gray-500 text-sm mt-1">คุณสามารถใช้ Markdown ในการจัดรูปแบบข้อความได้</p>
                    <?php if (isset($errors['description'])): ?>
                    <p class="text-red-500 text-sm mt-1"><?php echo htmlspecialchars($errors['description']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Difficulty -->
                    <div>
                        <label for="difficulty" class="block text-gray-700 font-medium mb-2">ระดับความยาก</label>
                        <select id="difficulty" name="difficulty"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="easy"
                                <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'easy') ? 'selected' : ''; ?>>
                                ง่าย
                            </option>
                            <option value="medium"
                                <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'medium') ? 'selected' : ''; ?>>
                                ปานกลาง
                            </option>
                            <option value="hard"
                                <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'hard') ? 'selected' : ''; ?>>
                                ยาก
                            </option>
                        </select>
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label for="due_date" class="block text-gray-700 font-medium mb-2">กำหนดส่ง</label>
                        <input type="datetime-local" id="due_date" name="due_date"
                            value="<?php echo isset($_POST['due_date']) ? htmlspecialchars($_POST['due_date']) : ''; ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-gray-500 text-sm mt-1">ปล่อยว่างไว้หากไม่มีกำหนดส่ง</p>
                    </div>
                </div>

                <!-- Starter Code -->
                <div class="mb-6">
                    <label for="starter_code" class="block text-gray-700 font-medium mb-2">โค้ดเริ่มต้น</label>
                    <textarea id="starter_code" name="starter_code" rows="8"
                        class="w-full px-4 py-2 border rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo isset($_POST['starter_code']) ? htmlspecialchars($_POST['starter_code']) : ''; ?></textarea>
                    <p class="text-gray-500 text-sm mt-1">โค้ดเริ่มต้นที่จะแสดงให้นักเรียนเห็นเมื่อเริ่มทำโจทย์</p>
                </div>

                <!-- Expected Output -->
                <div class="mb-6">
                    <label for="expected_output" class="block text-gray-700 font-medium mb-2">ผลลัพธ์ที่คาดหวัง</label>
                    <textarea id="expected_output" name="expected_output" rows="8"
                        class="w-full px-4 py-2 border rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo isset($_POST['expected_output']) ? htmlspecialchars($_POST['expected_output']) : ''; ?></textarea>
                    <p class="text-gray-500 text-sm mt-1">
                        โค้ดที่ถูกต้องหรือผลลัพธ์ที่คาดหวังสำหรับใช้ในการตรวจสอบคำตอบของนักเรียน</p>
                </div>

                <!-- Submit Buttons -->
                <div class="flex justify-end space-x-4">
                    <a href="teacher_dashboard.php"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        ยกเลิก
                    </a>
                    <button type="submit"
                        class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Add script to preview markdown if needed
    document.addEventListener('DOMContentLoaded', function() {
        // Automatically adjust textarea height based on content
        const textareas = document.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight + 2) + 'px';
            });
            // Initial adjustment
            textarea.dispatchEvent(new Event('input'));
        });
    });
    </script>
</body>

</html>