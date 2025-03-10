<?php
// teacher_manage_lessons.php - Teacher page to manage lessons for their assigned languages
require_once 'config.php';
checkLogin();

// Check if the current user is a teacher and get their assigned languages
$teacherCheckStmt = $pdo->prepare("
    SELECT t.id, t.languages 
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE u.id = ?
");
$teacherCheckStmt->execute([$_SESSION['user_id']]);
$teacher = $teacherCheckStmt->fetch();

if (!$teacher) {
   // $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้ เฉพาะอาจารย์เท่านั้น";
    header('Location: dashboard.php');
    exit();
}

// Convert the teacher's assigned languages into an array
$assignedLanguages = explode(',', $teacher['languages']); // Assuming languages are stored as a comma-separated string (e.g., "html,css,php")

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new question
    if (isset($_POST['add_question'])) {
        $language = $_POST['language'];
        $type = $_POST['type'];
        $question = $_POST['question'];
        $option_a = $_POST['option_a'];
        $option_b = $_POST['option_b'];
        $option_c = $_POST['option_c'];
        $option_d = $_POST['option_d'];
        $correct_answer = $_POST['correct_answer'];

        // Check if the selected language is in the teacher's assigned languages
        if (!in_array($language, $assignedLanguages)) {
            $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เพิ่มคำถามสำหรับภาษา $language";
        } else {
            $stmt = $pdo->prepare("INSERT INTO pre_post_tests (language, type, question, option_a, option_b, option_c, option_d, correct_answer) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$language, $type, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer])) {
                $_SESSION['success_message'] = "เพิ่มคำถามใหม่เรียบร้อยแล้ว";
            } else {
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการเพิ่มคำถาม";
            }
        }
    }

    // Update question
    if (isset($_POST['update_question'])) {
        $question_id = $_POST['question_id'];
        $question = $_POST['question'];
        $option_a = $_POST['option_a'];
        $option_b = $_POST['option_b'];
        $option_c = $_POST['option_c'];
        $option_d = $_POST['option_d'];
        $correct_answer = $_POST['correct_answer'];

        // Check if the question belongs to an assigned language
        $checkStmt = $pdo->prepare("SELECT language FROM pre_post_tests WHERE id = ?");
        $checkStmt->execute([$question_id]);
        $existingLanguage = $checkStmt->fetchColumn();

        if (!$existingLanguage || !in_array($existingLanguage, $assignedLanguages)) {
            $_SESSION['error_message'] = "คุณไม่มีสิทธิ์แก้ไขคำถามนี้";
        } else {
            $stmt = $pdo->prepare("UPDATE pre_post_tests SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ? WHERE id = ?");
            if ($stmt->execute([$question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $question_id])) {
                $_SESSION['success_message'] = "อัปเดตคำถามเรียบร้อยแล้ว";
            } else {
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตคำถาม";
            }
        }
    }

    // Delete question
    if (isset($_POST['delete_question'])) {
        $question_id = $_POST['question_id'];

        // Check if the question belongs to an assigned language
        $checkStmt = $pdo->prepare("SELECT language FROM pre_post_tests WHERE id = ?");
        $checkStmt->execute([$question_id]);
        $existingLanguage = $checkStmt->fetchColumn();

        if (!$existingLanguage || !in_array($existingLanguage, $assignedLanguages)) {
            $_SESSION['error_message'] = "คุณไม่มีสิทธิ์ลบคำถามนี้";
        } else {
            $checkAnswersStmt = $pdo->prepare("SELECT COUNT(*) FROM test_answers WHERE question_id = ?");
            $checkAnswersStmt->execute([$question_id]);
            $answersCount = $checkAnswersStmt->fetchColumn();

            if ($answersCount > 0) {
                $_SESSION['error_message'] = "ไม่สามารถลบคำถามนี้ได้เนื่องจากมีคำตอบเก็บอยู่ในระบบ";
            } else {
                $deleteStmt = $pdo->prepare("DELETE FROM pre_post_tests WHERE id = ?");
                if ($deleteStmt->execute([$question_id])) {
                    $_SESSION['success_message'] = "ลบคำถามเรียบร้อยแล้ว";
                } else {
                    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบคำถาม";
                }
            }
        }
    }

    header('Location: manage_questions.php');
    exit();
}

// Get filter parameters
$language_filter = $_GET['language'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Prepare SQL for filtering, restricting to teacher's languages
$sql = "SELECT * FROM pre_post_tests WHERE language IN (" . implode(',', array_fill(0, count($assignedLanguages), '?')) . ")";
$params = $assignedLanguages;

if ($language_filter !== 'all') {
    $sql .= " AND language = ?";
    $params[] = $language_filter;
}

if ($type_filter !== 'all') {
    $sql .= " AND type = ?";
    $params[] = $type_filter;
}

if ($search_query) {
    $sql .= " AND (question LIKE ? OR option_a LIKE ? OR option_b LIKE ? OR option_c LIKE ? OR option_d LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY language, type, id";

// Fetch questions
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html>
<head>
    <title>จัดการคำถามแบบทดสอบ - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                <h1 class="text-2xl font-bold">จัดการคำถามแบบทดสอบ</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าผู้ดูแลระบบ</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>

        <!-- Filter and Search -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="get" class="flex flex-wrap md:flex-nowrap gap-4">
                <div class="w-full md:w-1/4">
                    <label class="block text-gray-700 mb-2">ภาษา</label>
                    <select name="language" class="w-full px-4 py-2 border rounded bg-gray-300">
                        <option value="all" <?php echo $language_filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <?php foreach ($assignedLanguages as $lang): ?>
                        <option value="<?php echo $lang; ?>" <?php echo $language_filter === $lang ? 'selected' : ''; ?>>
                            <?php echo strtoupper($lang); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="w-full md:w-1/4">
                    <label class="block text-gray-700 mb-2">ประเภท</label>
                    <select name="type" class="w-full px-4 py-2 border rounded bg-gray-300">
                        <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <option value="pre" <?php echo $type_filter === 'pre' ? 'selected' : ''; ?>>แบบทดสอบก่อนเรียน</option>
                        <option value="post" <?php echo $type_filter === 'post' ? 'selected' : ''; ?>>แบบทดสอบหลังเรียน</option>
                    </select>
                </div>

                <div class="w-full md:w-2/4">
                    <label class="block text-gray-700 mb-2">ค้นหา</label>
                    <div class="flex">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="ค้นหาตามเนื้อหาคำถามหรือตัวเลือก"
                            class="flex-grow px-4 py-2 border rounded-l bg-gray-300">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-r hover:bg-blue-600">
                            ค้นหา
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Add New Question Button -->
        <div class="mb-8">
            <button id="showAddForm" class="bg-green-500 text-white px-6 py-3 rounded shadow hover:bg-green-600 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                </svg>
                เพิ่มคำถามใหม่
            </button>
        </div>

        <!-- Add Question Form (Hidden by default) -->
        <div id="addQuestionForm" class="bg-white rounded-lg shadow-md p-6 mb-8 hidden">
            <h2 class="text-xl font-semibold mb-4">เพิ่มคำถามใหม่</h2>
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">ภาษา</label>
                        <select name="language" required class="w-full bg-gray-300 px-4 py-2 border rounded">
                            <?php foreach ($assignedLanguages as $lang): ?>
                            <option value="<?php echo $lang; ?>"><?php echo strtoupper($lang); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">ประเภท</label>
                        <select name="type" required class="w-full px-4 py-2 border rounded bg-gray-300">
                            <option value="pre">แบบทดสอบก่อนเรียน</option>
                            <option value="post">แบบทดสอบหลังเรียน</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">คำถาม</label>
                    <textarea name="question" required class="w-full px-4 py-2 border rounded h-24 bg-gray-300"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">ตัวเลือก A</label>
                        <textarea name="option_a" required class="w-full px-4 py-2 border rounded h-16 bg-gray-300"></textarea>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">ตัวเลือก B</label>
                        <textarea name="option_b" required class="w-full px-4 py-2 border rounded h-16 bg-gray-300"></textarea>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">ตัวเลือก C</label>
                        <textarea name="option_c" required class="w-full px-4 py-2 border rounded h-16 bg-gray-300"></textarea>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">ตัวเลือก D</label>
                        <textarea name="option_d" required class="w-full px-4 py-2 border rounded h-16 bg-gray-300"></textarea>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">คำตอบที่ถูกต้อง</label>
                    <select name="correct_answer" required class="w-full px-4 py-2 border rounded bg-green-100">
                        <option value="a">A</option>
                        <option value="b">B</option>
                        <option value="c">C</option>
                        <option value="d">D</option>
                    </select>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" id="cancelAdd" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        ยกเลิก
                    </button>
                    <button type="submit" name="add_question" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>

        <!-- Questions Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ภาษา</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ประเภท</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">คำถาม</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">คำตอบ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($questions) > 0): ?>
                        <?php foreach ($questions as $question): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $question['id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap uppercase"><?php echo $question['language']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo $question['type'] === 'pre' ? 'ก่อนเรียน' : 'หลังเรียน'; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php echo htmlspecialchars(substr($question['question'], 0, 50)) . (strlen($question['question']) > 50 ? '...' : ''); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo strtoupper($question['correct_answer']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex gap-2">
                                    <button class="view-question bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600 text-sm"
                                            data-id="<?php echo $question['id']; ?>"
                                            data-question="<?php echo htmlspecialchars($question['question']); ?>"
                                            data-option-a="<?php echo htmlspecialchars($question['option_a']); ?>"
                                            data-option-b="<?php echo htmlspecialchars($question['option_b']); ?>"
                                            data-option-c="<?php echo htmlspecialchars($question['option_c']); ?>"
                                            data-option-d="<?php echo htmlspecialchars($question['option_d']); ?>"
                                            data-correct="<?php echo $question['correct_answer']; ?>">
                                        ดู
                                    </button>
                                    <button class="edit-question bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-sm"
                                            data-id="<?php echo $question['id']; ?>"
                                            data-question="<?php echo htmlspecialchars($question['question']); ?>"
                                            data-option-a="<?php echo htmlspecialchars($question['option_a']); ?>"
                                            data-option-b="<?php echo htmlspecialchars($question['option_b']); ?>"
                                            data-option-c="<?php echo htmlspecialchars($question['option_c']); ?>"
                                            data-option-d="<?php echo htmlspecialchars($question['option_d']); ?>"
                                            data-correct="<?php echo $question['correct_answer']; ?>">
                                        แก้ไข
                                    </button>
                                    <button class="delete-question bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-sm"
                                            data-id="<?php echo $question['id']; ?>">
                                        ลบ
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">ไม่พบคำถาม</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Question Modal -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
            <h2 class="text-xl font-semibold mb-4">รายละเอียดคำถาม</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 mb-1 font-semibold">คำถาม:</label>
                    <div id="view_question" class="p-3 bg-gray-100 rounded"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-1 font-semibold">ตัวเลือก A:</label>
                        <div id="view_option_a" class="p-3 bg-gray-100 rounded"></div>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1 font-semibold">ตัวเลือก B:</label>
                        <div id="view_option_b" class="p-3 bg-gray-100 rounded"></div>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1 font-semibold">ตัวเลือก C:</label>
                        <div id="view_option_c" class="p-3 bg-gray-100 rounded"></div>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-1 font-semibold">ตัวเลือก D:</label>
                        <div id="view_option_d" class="p-3 bg-gray-100 rounded"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 mb-1 font-semibold">คำตอบที่ถูกต้อง:</label>
                    <div id="view_correct_answer" class="p-3 bg-green-100 rounded font-bold"></div>
                </div>
                <div class="flex justify-end">
                    <button type="button" id="closeView" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        ปิด
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Question Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-screen overflow-y-auto">
            <h2 class="text-xl font-semibold mb-4">แก้ไขคำถาม</h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="question_id" id="edit_question_id">
                <div>
                    <label class="block text-gray-700 mb-2">คำถาม</label>
                    <textarea name="question" id="edit_question" required class="w-full px-4 py-2 border rounded h-24"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">ตัวเลือก A</label>
                        <textarea name="option_a" id="edit_option_a" required class="w-full px-4 py-2 border rounded h-16"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">ตัวเลือก B</label>
                        <textarea name="option_b" id="edit_option_b" required class="w-full px-4 py-2 border rounded h-16"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">ตัวเลือก C</label>
                        <textarea name="option_c" id="edit_option_c" required class="w-full px-4 py-2 border rounded h-16"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">ตัวเลือก D</label>
                        <textarea name="option_d" id="edit_option_d" required class="w-full px-4 py-2 border rounded h-16"></textarea>
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 mb-2">คำตอบที่ถูกต้อง</label>
                    <select name="correct_answer" id="edit_correct_answer" required class="w-full px-4 py-2 border rounded">
                        <option value="a">A</option>
                        <option value="b">B</option>
                        <option value="c">C</option>
                        <option value="d">D</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="cancelEdit" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        ยกเลิก
                    </button>
                    <button type="submit" name="update_question" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        บันทึกการเปลี่ยนแปลง
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-semibold mb-4">ยืนยันการลบ</h2>
            <p class="mb-4">คุณแน่ใจหรือไม่ว่าต้องการลบคำถามนี้?</p>
            <form method="post" class="flex justify-end gap-2">
                <input type="hidden" name="question_id" id="delete_question_id">
                <button type="button" id="cancelDelete" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                    ยกเลิก
                </button>
                <button type="submit" name="delete_question" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    ลบ
                </button>
            </form>
        </div>
    </div>

    <script>
    // Toggle Add Question Form
    document.getElementById('showAddForm').addEventListener('click', function() {
        document.getElementById('addQuestionForm').classList.toggle('hidden');
    });

    document.getElementById('cancelAdd').addEventListener('click', function() {
        document.getElementById('addQuestionForm').classList.add('hidden');
    });

    // View Question
    document.querySelectorAll('.view-question').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const question = this.getAttribute('data-question');
            const optionA = this.getAttribute('data-option-a');
            const optionB = this.getAttribute('data-option-b');
            const optionC = this.getAttribute('data-option-c');
            const optionD = this.getAttribute('data-option-d');
            const correct = this.getAttribute('data-correct');

            document.getElementById('view_question').textContent = question;
            document.getElementById('view_option_a').textContent = optionA;
            document.getElementById('view_option_b').textContent = optionB;
            document.getElementById('view_option_c').textContent = optionC;
            document.getElementById('view_option_d').textContent = optionD;
            document.getElementById('view_correct_answer').textContent = correct.toUpperCase();

            document.getElementById('viewModal').classList.remove('hidden');
        });
    });

    document.getElementById('closeView').addEventListener('click', function() {
        document.getElementById('viewModal').classList.add('hidden');
    });

    // Edit Question
    document.querySelectorAll('.edit-question').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const question = this.getAttribute('data-question');
            const optionA = this.getAttribute('data-option-a');
            const optionB = this.getAttribute('data-option-b');
            const optionC = this.getAttribute('data-option-c');
            const optionD = this.getAttribute('data-option-d');
            const correct = this.getAttribute('data-correct');

            document.getElementById('edit_question_id').value = id;
            document.getElementById('edit_question').value = question;
            document.getElementById('edit_option_a').value = optionA;
            document.getElementById('edit_option_b').value = optionB;
            document.getElementById('edit_option_c').value = optionC;
            document.getElementById('edit_option_d').value = optionD;
            document.getElementById('edit_correct_answer').value = correct;

            document.getElementById('editModal').classList.remove('hidden');
        });
    });

    document.getElementById('cancelEdit').addEventListener('click', function() {
        document.getElementById('editModal').classList.add('hidden');
    });

    // Delete Question
    document.querySelectorAll('.delete-question').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            document.getElementById('delete_question_id').value = id;
            document.getElementById('deleteModal').classList.remove('hidden');
        });
    });

    document.getElementById('cancelDelete').addEventListener('click', function() {
        document.getElementById('deleteModal').classList.add('hidden');
    });
    </script>
</body>
</html>