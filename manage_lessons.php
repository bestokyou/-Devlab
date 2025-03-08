<?php
// manage_lessons.php - Admin page to manage lessons
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

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new lesson
    if (isset($_POST['add_lesson'])) {
        $language = $_POST['language'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $expected_output = $_POST['expected_output'];
        $hint = $_POST['hint'];
        
        // Get the max order_num for the selected language
        $orderStmt = $pdo->prepare("SELECT MAX(order_num) FROM lessons WHERE language = ?");
        $orderStmt->execute([$language]);
        $maxOrder = $orderStmt->fetchColumn();
        $nextOrder = $maxOrder ? $maxOrder + 1 : 1;
        
        $stmt = $pdo->prepare("INSERT INTO lessons (language, title, description, expected_output, hint, order_num) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$language, $title, $description, $expected_output, $hint, $nextOrder])) {
            $_SESSION['success_message'] = "เพิ่มบทเรียนใหม่เรียบร้อยแล้ว";
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการเพิ่มบทเรียน";
        }
    }
    
    // Update lesson (Add this missing functionality)
    if (isset($_POST['update_lesson'])) {
        $lesson_id = $_POST['lesson_id'];
        $title = $_POST['title'];
        $description = $_POST['description'];
        $expected_output = $_POST['expected_output'];
        $hint = $_POST['hint'];
        
        $stmt = $pdo->prepare("UPDATE lessons 
                             SET title = ?, description = ?, expected_output = ?, hint = ? 
                             WHERE id = ?");
        if ($stmt->execute([$title, $description, $expected_output, $hint, $lesson_id])) {
            $_SESSION['success_message'] = "อัปเดตบทเรียนเรียบร้อยแล้ว";
        } else {
            $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตบทเรียน";
        }
        
        // Redirect to refresh
        header('Location: manage_lessons.php');
        exit();
    }
    
 // Delete lesson
if (isset($_POST['delete_lesson'])) {
    $lesson_id = $_POST['lesson_id'];
    
    // Begin transaction to ensure all related deletions complete
    $pdo->beginTransaction();
    
    try {
        // Delete any progress records for this lesson first
        $deleteProgressStmt = $pdo->prepare("DELETE FROM progress WHERE lesson_id = ?");
        $deleteProgressStmt->execute([$lesson_id]);
        
        // Delete any lesson_drafts for this lesson
        $deleteDraftsStmt = $pdo->prepare("DELETE FROM lesson_drafts WHERE lesson_id = ?");
        $deleteDraftsStmt->execute([$lesson_id]);
        
        // Finally delete the lesson itself
        $deleteStmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
        $deleteStmt->execute([$lesson_id]);
        
        // Commit the transaction if everything succeeded
        $pdo->commit();
        $_SESSION['success_message'] = "ลบบทเรียนเรียบร้อยแล้ว";
    } catch (Exception $e) {
        // Roll back the transaction if there was an error
        $pdo->rollBack();
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบบทเรียน: " . $e->getMessage();
    }
    
    // Redirect to refresh
    header('Location: manage_lessons.php');
    exit();
}
    // Update lesson order
    if (isset($_POST['update_order'])) {
        $lesson_id = $_POST['lesson_id'];
        $new_order = (int)$_POST['order_num'];
        
        // Get current lesson info
        $currentLessonStmt = $pdo->prepare("SELECT language, order_num FROM lessons WHERE id = ?");
        $currentLessonStmt->execute([$lesson_id]);
        $currentLesson = $currentLessonStmt->fetch();
        
        if ($currentLesson) {
            $language = $currentLesson['language'];
            $old_order = $currentLesson['order_num'];
            
            // Begin transaction for safety
            $pdo->beginTransaction();
            
            try {
                if ($new_order > $old_order) {
                    // Moving down - shift lessons in between up by 1
                    $shiftStmt = $pdo->prepare("
                        UPDATE lessons 
                        SET order_num = order_num - 1 
                        WHERE language = ? AND order_num > ? AND order_num <= ?
                    ");
                    $shiftStmt->execute([$language, $old_order, $new_order]);
                } elseif ($new_order < $old_order) {
                    // Moving up - shift lessons in between down by 1
                    $shiftStmt = $pdo->prepare("
                        UPDATE lessons 
                        SET order_num = order_num + 1 
                        WHERE language = ? AND order_num >= ? AND order_num < ?
                    ");
                    $shiftStmt->execute([$language, $new_order, $old_order]);
                }
                
                // Update the current lesson's order
                $updateStmt = $pdo->prepare("UPDATE lessons SET order_num = ? WHERE id = ?");
                $updateStmt->execute([$new_order, $lesson_id]);
                
                $pdo->commit();
                $_SESSION['success_message'] = "อัปเดตลำดับบทเรียนเรียบร้อยแล้ว";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการอัปเดตลำดับบทเรียน: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = "ไม่พบบทเรียนที่ต้องการเปลี่ยนลำดับ";
        }
        
        // Redirect to refresh
        header('Location: manage_lessons.php');
        exit();
    }
}

// Get filter parameters
$language_filter = $_GET['language'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Prepare SQL for filtering
$sql = "SELECT * FROM lessons WHERE 1=1";
$params = [];

if ($language_filter !== 'all') {
    $sql .= " AND language = ?";
    $params[] = $language_filter;
}

if ($search_query) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$sql .= " ORDER BY id ASC";

// Fetch lessons
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

// Fetch unique languages for dropdown
$langStmt = $pdo->query("SELECT DISTINCT language FROM lessons ORDER BY language");
$languages = $langStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html>

<head>
    <title>จัดการบทเรียน - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <!-- Include TinyMCE -->
    <script src="js/tinymce/tinymce.min.js"></script>
    <script>
    tinymce.init({
        selector: 'textarea.tinymce',
        license_key: 'gpl',
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
        height: 300
    });
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
                <h1 class="text-2xl font-bold">จัดการบทเรียน</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin_dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าผู้ดูแลระบบ</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $_SESSION['success_message']; ?>
            <?php unset($_SESSION['success_message']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $_SESSION['error_message']; ?>
            <?php unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>

        <!-- Filter and Search -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="get" class="flex flex-wrap md:flex-nowrap gap-4">
                <div class="w-full md:w-1/3">
                    <label class="block text-gray-700 mb-2">ภาษา</label>
                    <select name="language" class="w-full px-4 py-2 border rounded bg-gray-300">
                        <option value="all" <?php echo $language_filter === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <?php foreach ($languages as $lang): ?>
                        <option value="<?php echo $lang; ?>"
                            <?php echo $language_filter === $lang ? 'selected' : ''; ?>>
                            <?php echo strtoupper($lang); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="w-full md:w-2/3">
                    <label class="block text-gray-700 mb-2">ค้นหา</label>
                    <div class="flex">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>"
                            placeholder="ค้นหาตามชื่อหรือคำอธิบาย"
                            class="flex-grow px-4 py-2 border rounded-l bg-gray-300">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-r hover:bg-blue-600">
                            ค้นหา
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Add New Lesson Button -->
        <div class="mb-8">
            <button id="showAddForm"
                class="bg-green-500 text-white px-6 py-3 rounded shadow hover:bg-green-600 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                        clip-rule="evenodd" />
                </svg>
                เพิ่มบทเรียนใหม่
            </button>
        </div>

        <!-- Add Lesson Form (Hidden by default) -->
        <div id="addLessonForm" class="bg-white rounded-lg shadow-md p-6 mb-8 hidden">
            <h2 class="text-xl font-semibold mb-4">เพิ่มบทเรียนใหม่</h2>

            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">ภาษา</label>
                        <select name="language" required class="w-full px-4 py-2 border rounded bg-gray-300">
                            <option value="html">HTML</option>
                            <option value="css">CSS</option>
                            <option value="php">PHP</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">ชื่อบทเรียน</label>
                        <input type="text" name="title" required class="w-full px-4 py-2 border rounded bg-gray-300">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">คำอธิบายบทเรียน</label>
                    <textarea name="description" class="tinymce w-full px-4 py-2 border rounded bg-gray-300"></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">ผลลัพธ์ที่คาดหวัง (โค้ดที่ถูกต้อง)</label>
                    <textarea name="expected_output"
                        class="w-full px-4 py-2 border rounded h-32 font-mono bg-gray-300"></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">คำใบ้</label>
                    <textarea name="hint" class="w-full px-4 py-2 border rounded h-20 bg-gray-300"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" id="cancelAdd"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400 ">
                        ยกเลิก
                    </button>
                    <button type="submit" name="add_lesson"
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>

        <!-- Lessons Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ภาษา</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ชื่อบทเรียน</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ลำดับ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                การกระทำ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($lessons) > 0): ?>
                        <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo $lesson['id']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap uppercase"><?php echo $lesson['language']; ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($lesson['title']); ?></td>
                            <td class="px-6 py-4"><?php echo $lesson['order_num']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex gap-2">
                                    <button
                                        class="edit-lesson bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-sm"
                                        data-id="<?php echo $lesson['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($lesson['title']); ?>"
                                        data-description="<?php echo htmlspecialchars($lesson['description']); ?>"
                                        data-expected="<?php echo htmlspecialchars($lesson['expected_output']); ?>"
                                        data-hint="<?php echo htmlspecialchars($lesson['hint']); ?>">
                                        แก้ไข
                                    </button>
                                    <button
                                        class="delete-lesson bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-sm"
                                        data-id="<?php echo $lesson['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($lesson['title']); ?>">
                                        ลบ
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">ไม่พบบทเรียน</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div id="orderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h2 class="text-xl font-semibold mb-4">จัดลำดับบทเรียน</h2>

            <form method="post" class="space-y-4">
                <input type="hidden" name="lesson_id" id="order_lesson_id">

                <div>
                    <label class="block text-gray-700 mb-2">ลำดับบทเรียน</label>
                    <input type="number" name="order_num" id="order_num_input" min="1"
                        class="w-full px-4 py-2 border rounded">
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" id="cancelOrder"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        ยกเลิก
                    </button>
                    <button type="submit" name="update_order"
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Edit Lesson Modal -->
    <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-4xl max-h-screen overflow-y-auto">
            <h2 class="text-xl font-semibold mb-4">แก้ไขบทเรียน</h2>

            <form method="post" class="space-y-4">
                <input type="hidden" name="lesson_id" id="edit_lesson_id">

                <div>
                    <label class="block text-gray-700 mb-2">ชื่อบทเรียน</label>
                    <input type="text" name="title" id="edit_title" required class="w-full px-4 py-2 border rounded">
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">คำอธิบายบทเรียน</label>
                    <textarea name="description" id="edit_description"
                        class="tinymce-edit w-full px-4 py-2 border rounded"></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">ผลลัพธ์ที่คาดหวัง (โค้ดที่ถูกต้อง)</label>
                    <textarea name="expected_output" id="edit_expected_output"
                        class="w-full px-4 py-2 border rounded h-32 font-mono"></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 mb-2">คำใบ้</label>
                    <textarea name="hint" id="edit_hint" class="w-full px-4 py-2 border rounded h-20"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" id="cancelEdit"
                        class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        ยกเลิก
                    </button>
                    <button type="submit" name="update_lesson"
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
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

            <p class="mb-4">คุณแน่ใจหรือไม่ว่าต้องการลบบทเรียน <span id="delete_lesson_title"
                    class="font-semibold"></span>?</p>

            <form method="post" class="flex justify-end gap-2">
                <input type="hidden" name="lesson_id" id="delete_lesson_id">

                <button type="button" id="cancelDelete"
                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                    ยกเลิก
                </button>
                <button type="submit" name="delete_lesson"
                    class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    ลบ
                </button>
            </form>
        </div>
    </div>

    <script>
    // Toggle Add Lesson Form
    document.getElementById('showAddForm').addEventListener('click', function() {
        document.getElementById('addLessonForm').classList.toggle('hidden');
    });

    document.getElementById('cancelAdd').addEventListener('click', function() {
        document.getElementById('addLessonForm').classList.add('hidden');
    });

    // Edit Lesson
    document.querySelectorAll('.edit-lesson').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');
            const description = this.getAttribute('data-description');
            const expected = this.getAttribute('data-expected');
            const hint = this.getAttribute('data-hint');

            document.getElementById('edit_lesson_id').value = id;
            document.getElementById('edit_title').value = title;

            // For TinyMCE editor
            if (tinymce.get('edit_description')) {
                tinymce.get('edit_description').setContent(description);
            } else {
                document.getElementById('edit_description').value = description;
            }

            document.getElementById('edit_expected_output').value = expected;
            document.getElementById('edit_hint').value = hint;

            document.getElementById('editModal').classList.remove('hidden');
        });
    });

    document.getElementById('cancelEdit').addEventListener('click', function() {
        document.getElementById('editModal').classList.add('hidden');
    });

    // Delete Lesson
    document.querySelectorAll('.delete-lesson').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const title = this.getAttribute('data-title');

            document.getElementById('delete_lesson_id').value = id;
            document.getElementById('delete_lesson_title').textContent = title;

            document.getElementById('deleteModal').classList.remove('hidden');
        });
    });

    document.getElementById('cancelDelete').addEventListener('click', function() {
        document.getElementById('deleteModal').classList.add('hidden');
    });

    // Initialize TinyMCE for edit form

    document.querySelectorAll('tr').forEach(row => {
        const actionCell = row.querySelector('td:last-child');
        if (actionCell && actionCell.querySelector('.edit-lesson')) {
            const orderBtn = document.createElement('button');
            orderBtn.className =
                'change-order bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 text-sm ml-2';
            orderBtn.textContent = 'จัดลำดับ';
            orderBtn.dataset.id = actionCell.querySelector('.edit-lesson').dataset.id;
            orderBtn.dataset.order = row.querySelector('td:nth-child(4)').textContent;

            actionCell.querySelector('.flex').appendChild(orderBtn);
        }
    });

    // Handle Order change click
    document.querySelectorAll('.change-order').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const order = this.getAttribute('data-order');

            document.getElementById('order_lesson_id').value = id;
            document.getElementById('order_num_input').value = order;

            document.getElementById('orderModal').classList.remove('hidden');
        });
    });

    document.getElementById('cancelOrder').addEventListener('click', function() {
        document.getElementById('orderModal').classList.add('hidden');
    });
    </script>
</body>

</html>