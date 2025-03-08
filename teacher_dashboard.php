<?php
// teacher_dashboard.php - Dashboard for teachers to manage worksheets
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

// Get filter parameters
$languageFilter = isset($_GET['language']) && in_array($_GET['language'], $teacherLanguages) 
    ? $_GET['language'] 
    : '';

// Delete worksheet if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $worksheetId = (int)$_GET['delete'];
    
    try {
        // Check if this worksheet belongs to this teacher
        $checkStmt = $pdo->prepare("
            SELECT id FROM worksheets 
            WHERE id = ? AND teacher_id = ?
        ");
        $checkStmt->execute([$worksheetId, $teacher['id']]);
        
        if ($checkStmt->rowCount() > 0) {
            // Delete the worksheet
            $deleteStmt = $pdo->prepare("DELETE FROM worksheets WHERE id = ?");
            $deleteStmt->execute([$worksheetId]);
            
            $_SESSION['success_message'] = "ลบโจทย์เรียบร้อยแล้ว";
        } else {
            $_SESSION['error_message'] = "คุณไม่มีสิทธิ์ลบโจทย์นี้";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "เกิดข้อผิดพลาดในการลบโจทย์: " . $e->getMessage();
    }
    
    // Redirect to remove the action from URL
    header('Location: teacher_dashboard.php' . ($languageFilter ? "?language=$languageFilter" : ''));
    exit();
}

// Get all worksheets for this teacher
$query = "
    SELECT w.*, 
           (SELECT COUNT(*) FROM worksheet_submissions ws WHERE ws.worksheet_id = w.id) as submission_count,
           (SELECT COUNT(*) FROM worksheet_submissions ws WHERE ws.worksheet_id = w.id AND ws.status = 'submitted') as pending_count
    FROM worksheets w
    WHERE w.teacher_id = ?
";
$params = [$teacher['id']];

// Apply language filter if set
if (!empty($languageFilter)) {
    $query .= " AND w.language = ?";
    $params[] = $languageFilter;
}

// Add sorting
$query .= " ORDER BY w.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$worksheets = $stmt->fetchAll();

// Get submission statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_worksheets,
        SUM((SELECT COUNT(*) FROM worksheet_submissions ws WHERE ws.worksheet_id = w.id)) as total_submissions,
        SUM((SELECT COUNT(*) FROM worksheet_submissions ws WHERE ws.worksheet_id = w.id AND ws.status = 'submitted')) as pending_submissions
    FROM worksheets w
    WHERE w.teacher_id = ?
");
$statsStmt->execute([$teacher['id']]);
$stats = $statsStmt->fetch();

// Format teacher languages for display
$languageNames = [];
foreach ($teacherLanguages as $lang) {
    switch ($lang) {
        case 'html': 
            $languageNames[] = 'HTML'; 
            break;
        case 'css': 
            $languageNames[] = 'CSS'; 
            break;
        case 'php': 
            $languageNames[] = 'PHP'; 
            break;
        default: 
            $languageNames[] = strtoupper($lang);
    }
}
$displayLanguages = implode(', ', $languageNames);
?>
<!DOCTYPE html>
<html>
<head>
    <title>หน้าอาจารย์ - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    /* Font settings */
    :root {
        --font-thai: 'IBM Plex Sans Thai', 'Noto Sans Thai', sans-serif;
        --font-english: 'IBM Plex Sans', 'Noto Sans', sans-serif;
    }
    body {
        font-family: var(--font-thai);
    }
    /* Use English font for specific elements */
    input, 
    textarea,
    .font-english {
        font-family: var(--font-english);
    }
    /* Combined font stack for mixed content */
    .mixed-text {
        font-family: var(--font-english), var(--font-thai);
    }
    .worksheet-row {
        transition: all 0.2s ease;
    }
    .worksheet-row:hover {
        background-color: #f9fafb;
    }
    /* Badge animation */
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    .badge-pulse {
        animation: pulse 1.5s infinite;
    }
    </style>
</head>
<body class="bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8 pb-6 border-b bg-white shadow p-5 rounded">
            <div class="flex items-center">
                <a href="dashboard.php">
                    <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                </a>
                <div>
                    <h1 class="text-2xl font-bold">หน้าอาจารย์</h1>
                    <p class="text-sm text-gray-600">ภาษาที่สอน: <?php echo $displayLanguages; ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-blue-500 hover:underline">กลับไปหน้าหลัก</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-blue-500">
                <h3 class="text-gray-500 text-sm">จำนวนโจทย์ทั้งหมด</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_worksheets']); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-green-500">
                <h3 class="text-gray-500 text-sm">จำนวนการส่งงานทั้งหมด</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_submissions']); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-yellow-500">
                <h3 class="text-gray-500 text-sm">งานที่รอตรวจ</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['pending_submissions']); ?></p>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
            <div class="flex items-center gap-4">
                <a href="create_worksheet.php" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
                    </svg>
                    สร้างโจทย์ใหม่
                </a>
                
                <?php if ($stats['pending_submissions'] > 0): ?>
                <a href="#pending-submissions" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                    </svg>
                    งานที่รอตรวจ (<?php echo $stats['pending_submissions']; ?>)
                </a>
                <?php endif; ?>
            </div>
            
            <div>
                <form method="GET" action="" class="flex items-center gap-2">
                    <label for="language" class="text-white">กรองตามภาษา:</label>
                    <select id="language" name="language" class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($teacherLanguages as $lang): ?>
                            <option value="<?php echo $lang; ?>" <?php echo $languageFilter === $lang ? 'selected' : ''; ?>>
                                <?php echo strtoupper($lang); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Worksheets Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold">โจทย์ทั้งหมดของคุณ</h2>
                <?php if (!empty($languageFilter)): ?>
                    <p class="text-sm text-gray-600">กำลังแสดงเฉพาะภาษา: <?php echo strtoupper($languageFilter); ?></p>
                <?php endif; ?>
            </div>
            <?php if (count($worksheets) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    โจทย์
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ภาษา
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ความยาก
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    กำหนดส่ง
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    งานที่ส่ง
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    การจัดการ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($worksheets as $worksheet): ?>
                                <tr class="worksheet-row">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($worksheet['title']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 font-english">
                                            <?php echo strtoupper($worksheet['language']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                                switch ($worksheet['difficulty']) {
                                                    case 'easy': echo 'bg-green-100 text-green-800'; break;
                                                    case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'hard': echo 'bg-red-100 text-red-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                            ?>">
                                            <?php 
                                                switch ($worksheet['difficulty']) {
                                                    case 'easy': echo 'ง่าย'; break;
                                                    case 'medium': echo 'ปานกลาง'; break;
                                                    case 'hard': echo 'ยาก'; break;
                                                    default: echo $worksheet['difficulty'];
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                                if ($worksheet['due_date']) {
                                                    $due = new DateTime($worksheet['due_date']);
                                                    $now = new DateTime();
                                                    $isPastDue = $due < $now;
                                                    
                                                    echo '<span class="' . ($isPastDue ? 'text-red-600' : 'text-gray-900') . '">';
                                                    echo date('d/m/Y H:i', strtotime($worksheet['due_date']));
                                                    echo '</span>';
                                                    
                                                    if ($isPastDue) {
                                                        echo ' <span class="text-red-600 text-xs">(หมดเวลา)</span>';
                                                    }
                                                } else {
                                                    echo '<span class="text-gray-500">ไม่มีกำหนด</span>';
                                                }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($worksheet['submission_count'] > 0): ?>
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium"><?php echo $worksheet['submission_count']; ?></span>
                                                <?php if ($worksheet['pending_count'] > 0): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 <?php echo $worksheet['pending_count'] > 0 ? 'badge-pulse' : ''; ?>">
                                                        รอตรวจ <?php echo $worksheet['pending_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-500">ยังไม่มีการส่งงาน</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <?php if ($worksheet['submission_count'] > 0): ?>
                                                <a href="view_submissions.php?worksheet_id=<?php echo $worksheet['id']; ?>" class="text-indigo-600 hover:text-indigo-900">ดูงานที่ส่ง</a>
                                            <?php endif; ?>
                                            <a href="edit_worksheet.php?id=<?php echo $worksheet['id']; ?>" class="text-blue-600 hover:text-blue-900">แก้ไข</a>
                                            <a href="#" onclick="confirmDelete(<?php echo $worksheet['id']; ?>, '<?php echo addslashes($worksheet['title']); ?>')" class="text-red-600 hover:text-red-900">ลบ</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-8 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-xl font-semibold mb-2">ยังไม่มีโจทย์</h3>
                    <p class="text-gray-600 mb-4">คุณยังไม่ได้สร้างโจทย์ใด ๆ</p>
                    <a href="create_worksheet.php" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 inline-block">
                        สร้างโจทย์ใหม่
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Submissions Section -->
        <?php if ($stats['pending_submissions'] > 0): ?>
            <div id="pending-submissions" class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="p-6 border-b bg-yellow-50">
                    <h2 class="text-xl font-bold">งานที่รอตรวจ (<?php echo $stats['pending_submissions']; ?>)</h2>
                    <p class="text-sm text-gray-600">รายการงานที่นักเรียนส่งและยังไม่ได้ตรวจ</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    โจทย์
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ภาษา
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    จำนวนที่รอตรวจ
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    การจัดการ
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php 
                            // Get worksheets with pending submissions
                            $pendingStmt = $pdo->prepare("
                                SELECT w.id, w.title, w.language,
                                    (SELECT COUNT(*) FROM worksheet_submissions ws WHERE ws.worksheet_id = w.id AND ws.status = 'submitted') as pending_count
                                FROM worksheets w
                                WHERE w.teacher_id = ?
                                AND (SELECT COUNT(*) FROM worksheet_submissions ws WHERE ws.worksheet_id = w.id AND ws.status = 'submitted') > 0
                                ORDER BY pending_count DESC
                            ");
                            $pendingStmt->execute([$teacher['id']]);
                            $pendingWorksheets = $pendingStmt->fetchAll();
                            
                            foreach ($pendingWorksheets as $worksheet):
                            ?>
                                <tr class="worksheet-row">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($worksheet['title']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 font-english">
                                            <?php echo strtoupper($worksheet['language']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <?php echo $worksheet['pending_count']; ?> งาน
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <a href="view_submissions.php?worksheet_id=<?php echo $worksheet['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                            ตรวจงาน
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-xl">
            <h3 class="text-xl font-bold mb-4">ยืนยันการลบโจทย์</h3>
            <p class="mb-6">คุณแน่ใจหรือไม่ว่าต้องการลบโจทย์ "<span id="deleteWorksheetTitle" class="font-medium"></span>"?</p>
            <div class="flex justify-end space-x-4">
                <button onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">
                    ยกเลิก
                </button>
                <a id="confirmDeleteBtn" href="#" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">
                    ลบโจทย์
                </a>
            </div>
        </div>
    </div>

    <script>
        // Functions for delete confirmation modal
        function confirmDelete(worksheetId, title) {
            document.getElementById('deleteWorksheetTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = 'teacher_dashboard.php?delete=' + worksheetId + '<?php echo $languageFilter ? "&language=$languageFilter" : ''; ?>';
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>