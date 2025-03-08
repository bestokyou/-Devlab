<?php
// student_progress.php - Track student progress for teachers and admins
require_once 'config.php';
checkLogin();

// Check if the current user is a teacher or admin
$userCheckStmt = $pdo->prepare("
    SELECT u.is_admin, t.id as teacher_id, t.languages 
    FROM users u
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE u.id = ?
");
$userCheckStmt->execute([$_SESSION['user_id']]);
$userInfo = $userCheckStmt->fetch();

if (!$userInfo || (!$userInfo['is_admin'] && !$userInfo['teacher_id'])) {
    $_SESSION['error_message'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
    header('Location: dashboard.php');
    exit();
}

// Get current username for display
$usernameStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$usernameStmt->execute([$_SESSION['user_id']]);
$username = $usernameStmt->fetchColumn();

// Process filter parameters
$studentFilter = isset($_GET['student']) ? (int)$_GET['student'] : 0;
$languageFilter = isset($_GET['language']) ? $_GET['language'] : '';

// Only allow teachers to see languages they teach
if (!$userInfo['is_admin'] && $languageFilter) {
    $teacherLanguages = explode(',', $userInfo['languages']);
    if (!in_array($languageFilter, $teacherLanguages)) {
        $languageFilter = '';
    }
}
$searchTerm = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '';
// Get all students
if ($userInfo['is_admin']) {
    // Admins can see all students
    if (!empty($searchTerm)) {
        $studentsStmt = $pdo->prepare("
            SELECT id, username, email 
            FROM users 
            WHERE is_admin = 0 AND (username LIKE ? OR email LIKE ?)
            ORDER BY username
        ");
        $studentsStmt->execute([$searchTerm, $searchTerm]);
    } else {
        $studentsStmt = $pdo->prepare("
            SELECT id, username, email 
            FROM users 
            WHERE is_admin = 0
            ORDER BY username
        ");
        $studentsStmt->execute();
    }
} else {
    // Teachers can see all students (แก้ไขให้อาจารย์เห็นนักเรียนทั้งหมด)
    $studentsStmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.username, u.email 
        FROM users u
        LEFT JOIN progress p ON u.id = p.user_id
        WHERE u.is_admin = 0 
        ORDER BY u.username
    ");
    $studentsStmt->execute();
}
$students = $studentsStmt->fetchAll();

// Get languages (for filtering)
if ($userInfo['is_admin']) {
    // Admin gets all languages
    $languagesQuery = $pdo->query("
        SELECT DISTINCT language 
        FROM lessons 
        ORDER BY language
    ");
    $languages = $languagesQuery->fetchAll(PDO::FETCH_COLUMN);
} else {
    // Teachers get only languages they teach
    $languages = explode(',', $userInfo['languages']);
}

// If a student is selected, get their progress data
$studentData = null;
if ($studentFilter) {
    // Verify that the teacher has access to view this student (for teachers only)
    if (!$userInfo['is_admin']) {
        $teacherAccessCheck = $pdo->prepare("
            SELECT COUNT(*) 
            FROM worksheet_submissions ws
            JOIN worksheets w ON ws.worksheet_id = w.id
            WHERE ws.user_id = ? AND w.teacher_id = ?
        ");
     
    }
    
    // Get student basic info
    $studentInfoStmt = $pdo->prepare("
        SELECT id, username, email, created_at
        FROM users
        WHERE id = ?
    ");
    $studentInfoStmt->execute([$studentFilter]);
    $studentData = $studentInfoStmt->fetch();
    
    if ($studentData) {
        // Calculate overall progress stats
        $overallStatsQuery = $pdo->prepare("
            SELECT
                COUNT(DISTINCT l.id) as total_lessons,
                COUNT(DISTINCT CASE WHEN p.completed = 1 THEN l.id ELSE NULL END) as completed_lessons,
                COALESCE(SUM(p.score), 0) as total_score
            FROM lessons l
            LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
            " . ($languageFilter ? "WHERE l.language = ?" : "")
        );
        
        if ($languageFilter) {
            $overallStatsQuery->execute([$studentFilter, $languageFilter]);
        } else {
            $overallStatsQuery->execute([$studentFilter]);
        }
        $studentData['overall_stats'] = $overallStatsQuery->fetch();
        
        // Calculate completion percentage
        if ($studentData['overall_stats']['total_lessons'] > 0) {
            $studentData['completion_percentage'] = round(
                ($studentData['overall_stats']['completed_lessons'] / $studentData['overall_stats']['total_lessons']) * 100
            );
        } else {
            $studentData['completion_percentage'] = 0;
        }
        
        // Get lesson progress by language
        $lessonProgressQuery = $pdo->prepare("
            SELECT
                l.language,
                COUNT(DISTINCT l.id) as total_lessons,
                COUNT(DISTINCT CASE WHEN p.completed = 1 THEN l.id ELSE NULL END) as completed_lessons,
                COALESCE(SUM(p.score), 0) as total_score
            FROM lessons l
            LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
            " . ($languageFilter ? "WHERE l.language = ?" : "") . "
            GROUP BY l.language
            ORDER BY l.language
        ");
        
        if ($languageFilter) {
            $lessonProgressQuery->execute([$studentFilter, $languageFilter]);
        } else {
            $lessonProgressQuery->execute([$studentFilter]);
        }
        $studentData['lesson_progress'] = $lessonProgressQuery->fetchAll();
        
        // Get test results
        $testResultsQuery = $pdo->prepare("
            SELECT
                tr.language,
                tr.test_type,
                tr.score,
                tr.total_questions,
                tr.completed_at
            FROM test_results tr
            WHERE tr.user_id = ?
            " . ($languageFilter ? "AND tr.language = ?" : "") . "
            ORDER BY tr.language, tr.test_type
        ");
        
        if ($languageFilter) {
            $testResultsQuery->execute([$studentFilter, $languageFilter]);
        } else {
            $testResultsQuery->execute([$studentFilter]);
        }
        $studentData['test_results'] = $testResultsQuery->fetchAll();
        
        // Get worksheet submissions
        $worksheetQuery = $pdo->prepare("
            SELECT
                w.id,
                w.title,
                w.language,
                w.difficulty,
                ws.submitted_at,
                ws.status,
                ws.score,
                ws.feedback,
                ws.graded_at,
                u.username as teacher_name
            FROM worksheet_submissions ws
            JOIN worksheets w ON ws.worksheet_id = w.id
            JOIN teachers t ON w.teacher_id = t.id
            JOIN users u ON t.user_id = u.id
            WHERE ws.user_id = ?
            " . ($languageFilter ? "AND w.language = ?" : "") . "
            ORDER BY ws.submitted_at DESC
        ");
        
        if ($languageFilter) {
            $worksheetQuery->execute([$studentFilter, $languageFilter]);
        } else {
            $worksheetQuery->execute([$studentFilter]);
        }
        $studentData['worksheet_submissions'] = $worksheetQuery->fetchAll();
        
        // Get detailed lesson progress
        $detailedLessonsQuery = $pdo->prepare("
            SELECT
                l.id,
                l.title,
                l.language,
                l.order_num,
                CASE WHEN p.completed = 1 THEN 'completed' 
                     WHEN p.id IS NOT NULL THEN 'in-progress'
                     ELSE 'not-started' END as status,
                p.score,
                p.completed_at
            FROM lessons l
            LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ?
            " . ($languageFilter ? "WHERE l.language = ?" : "") . "
            ORDER BY l.language, l.order_num
        ");
        
        if ($languageFilter) {
            $detailedLessonsQuery->execute([$studentFilter, $languageFilter]);
        } else {
            $detailedLessonsQuery->execute([$studentFilter]);
        }
        $studentData['detailed_lessons'] = $detailedLessonsQuery->fetchAll();
    }
}

// Function to get language display name
function getLanguageDisplay($languageCode) {
    switch ($languageCode) {
        case 'html': return 'HTML';
        case 'css': return 'CSS';
        case 'php': return 'PHP';
        default: return strtoupper($languageCode);
    }
}

// Function to format dates
function formatDate($dateString) {
    if (!$dateString) return '-';
    $date = new DateTime($dateString);
    return $date->format('d/m/Y H:i');
}

// Function to get status badge HTML
function getStatusBadge($status) {
    switch ($status) {
        case 'completed':
            return '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">เสร็จสิ้น</span>';
        case 'in-progress':
            return '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">กำลังเรียน</span>';
        case 'not-started':
            return '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">ยังไม่เริ่ม</span>';
        case 'submitted':
            return '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">รอตรวจ</span>';
        case 'graded':
            return '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">ตรวจแล้ว</span>';
        default:
            return '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">' . $status . '</span>';
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>ติดตามความคืบหน้าของนักเรียน - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=display"
        rel="stylesheet">
    <style>
    /* Font settings */
    :root {
        --font-thai: 'IBM Plex Sans Thai', 'Noto Sans Thai', sans-serif;
        --font-english: 'IBM Plex Sans', 'Noto Sans', sans-serif;
    }

    body {
        font-family: var(--font-thai);
    }

    .font-english {
        font-family: var(--font-english);
    }

    /* Progress bar animation */
    @keyframes progressAnimation {
        0% {
            background-position: 0% 50%;
        }

        50% {
            background-position: 100% 50%;
        }

        100% {
            background-position: 0% 50%;
        }
    }

    .progress-bar-animated {
        background: linear-gradient(270deg, #60a5fa, #3b82f6, #2563eb);
        background-size: 200% 200%;
        animation: progressAnimation 2s ease infinite;
    }

    /* Tab styles */
    .tab-button {
        transition: all 0.2s ease;
    }

    .tab-button.active {
        border-bottom: 2px solid #3b82f6;
        color: #3b82f6;
        font-weight: 500;
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
                    <h1 class="text-2xl font-bold">ติดตามความคืบหน้าของนักเรียน</h1>
                    <p class="text-sm text-gray-600">สวัสดี, <?php echo htmlspecialchars($username); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <?php if ($userInfo['is_admin']): ?>
                <a href="admin_dashboard.php" class="text-blue-500 hover:underline">← กลับไปยังหน้าผู้ดูแลระบบ</a>
                <?php else: ?>
                <a href="all_students.php" class="text-blue-500 hover:underline">←
                    กลับไปยังหน้ารายชื่อนักเรียนทั้งหมด</a>
                <?php endif; ?>
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

        <!-- Filter Panel -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">ค้นหาและกรอง</h2>
            <form method="GET" action="student_progress.php"
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                <div class="mb-0">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="student">นักเรียน</label>
                    <select id="student" name="student" class="w-full rounded-md border border-gray-300 py-2 px-3">
                        <option value="">-- เลือกนักเรียน --</option>
                        <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>"
                            <?php echo $studentFilter == $student['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['username']) . ' (' . htmlspecialchars($student['email']) . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="language">ภาษา</label>
                    <select id="language" name="language" class="w-full rounded-md border border-gray-300 py-2 px-3">
                        <option value="">-- ทุกภาษา --</option>
                        <?php foreach ($languages as $lang): ?>
                        <option value="<?php echo $lang; ?>" <?php echo $languageFilter == $lang ? 'selected' : ''; ?>>
                            <?php echo getLanguageDisplay($lang); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        ค้นหา
                    </button>
                    <?php if ($studentFilter || $languageFilter): ?>
                    <a href="student_progress.php" class="ml-2 text-gray-500 hover:text-gray-700">ล้างตัวกรอง</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!$studentFilter): ?>
        <!-- No student selected message -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            <h3 class="text-xl font-semibold mb-2">กรุณาเลือกนักเรียน</h3>
            <p class="text-gray-600 mb-2">เลือกนักเรียนจากรายการด้านบนเพื่อดูความคืบหน้า</p>
        </div>
        <?php elseif (!$studentData): ?>
        <!-- Student not found message -->
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-red-400 mx-auto mb-4" fill="none"
                viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <h3 class="text-xl font-semibold mb-2">ไม่พบข้อมูลนักเรียน</h3>
            <p class="text-gray-600 mb-2">ไม่พบข้อมูลสำหรับนักเรียนที่เลือก</p>
        </div>
        <?php else: ?>
        <!-- Student Progress Display -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <!-- Student Info Header -->
            <div class="bg-gray-50 p-6 border-b">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                    <div>
                        <h2 class="text-xl font-bold"><?php echo htmlspecialchars($studentData['username']); ?></h2>
                        <p class="text-gray-600"><?php echo htmlspecialchars($studentData['email']); ?></p>
                        <p class="text-gray-500 text-sm">สมัครเมื่อ:
                            <?php echo formatDate($studentData['created_at']); ?></p>
                    </div>
                    <div class="mt-4 md:mt-0 text-right">
                        <div class="text-lg font-semibold">ความคืบหน้าโดยรวม</div>
                        <div class="flex items-center">
                            <div class="text-2xl font-bold text-blue-600 mr-2">
                                <?php echo $studentData['completion_percentage']; ?>%</div>
                            <div class="text-sm text-gray-500">
                                (<?php echo $studentData['overall_stats']['completed_lessons']; ?>/<?php echo $studentData['overall_stats']['total_lessons']; ?>
                                บทเรียน)
                            </div>
                        </div>
                        <div class="text-gray-600">คะแนนรวม: <?php echo $studentData['overall_stats']['total_score']; ?>
                        </div>
                    </div>
                </div>
                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 rounded-full h-2.5 mt-4">
                    <div class="progress-bar-animated h-2.5 rounded-full"
                        style="width: <?php echo $studentData['completion_percentage']; ?>%"></div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <nav class="flex overflow-x-auto" id="tabs">
                    <button data-tab="overview" class="tab-button active px-6 py-3 text-gray-600 hover:text-gray-900">
                        ภาพรวม
                    </button>
                    <button data-tab="lessons" class="tab-button px-6 py-3 text-gray-600 hover:text-gray-900">
                        บทเรียน
                    </button>
                    <button data-tab="tests" class="tab-button px-6 py-3 text-gray-600 hover:text-gray-900">
                        แบบทดสอบ
                    </button>
                    <button data-tab="worksheets" class="tab-button px-6 py-3 text-gray-600 hover:text-gray-900">
                        โจทย์งาน
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Overview Tab -->
                <div id="overview-tab" class="tab-content">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($studentData['lesson_progress'] as $progress): ?>
                        <div class="bg-gray-50 rounded-lg p-4 border">
                            <div class="flex justify-between items-start">
                                <h3 class="text-lg font-semibold">
                                    <?php echo getLanguageDisplay($progress['language']); ?></h3>
                                <span class="px-2 py-1 text-xs rounded-full <?php 
                                    $percent = $progress['total_lessons'] > 0 
                                        ? round(($progress['completed_lessons'] / $progress['total_lessons']) * 100) 
                                        : 0;
                                    if ($percent >= 75) echo 'bg-green-100 text-green-800';
                                    else if ($percent >= 40) echo 'bg-yellow-100 text-yellow-800';
                                    else echo 'bg-red-100 text-red-800';
                                ?>">
                                    <?php echo $percent; ?>%
                                </span>
                            </div>
                            <div class="mt-2">
                                <div class="text-sm text-gray-600">
                                    บทเรียนที่เสร็จสิ้น:
                                    <?php echo $progress['completed_lessons']; ?>/<?php echo $progress['total_lessons']; ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    คะแนนรวม: <?php echo $progress['total_score']; ?>
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                                <div class="<?php 
                                    if ($percent >= 75) echo 'bg-green-500';
                                    else if ($percent >= 40) echo 'bg-yellow-500';
                                    else echo 'bg-red-500';
                                ?> h-1.5 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Test Scores Summary -->
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold mb-4">ผลการทดสอบ</h3>
                        <?php if (count($studentData['test_results']) > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($studentData['test_results'] as $test): ?>
                            <div class="bg-gray-50 rounded-lg p-4 border">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-medium">
                                        <?php echo getLanguageDisplay($test['language']); ?> -
                                        <?php echo $test['test_type'] == 'pre' ? 'แบบทดสอบก่อนเรียน' : 'แบบทดสอบหลังเรียน'; ?>
                                    </h4>
                                    <span class="px-2 py-1 text-xs rounded-full <?php 
                                        $scorePercent = $test['total_questions'] > 0 
                                            ? round(($test['score'] / $test['total_questions']) * 100) 
                                            : 0;
                                        if ($scorePercent >= 80) echo 'bg-green-100 text-green-800';
                                        else if ($scorePercent >= 50) echo 'bg-yellow-100 text-yellow-800';
                                        else echo 'bg-red-100 text-red-800';
                                    ?>">
                                        <?php echo $test['score']; ?>/<?php echo $test['total_questions']; ?>
                                    </span>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">
                                    ทำเมื่อ: <?php echo formatDate($test['completed_at']); ?>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                                    <div class="<?php 
                                        if ($scorePercent >= 80) echo 'bg-green-500';
                                        else if ($scorePercent >= 50) echo 'bg-yellow-500';
                                        else echo 'bg-red-500';
                                    ?> h-1.5 rounded-full" style="width: <?php echo $scorePercent; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-gray-500 text-center py-6 bg-gray-50 rounded">
                            ยังไม่มีข้อมูลการทดสอบ
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Worksheets -->
                    <div class="mt-8">
                        <h3 class="text-lg font-semibold mb-4">โจทย์งานล่าสุด</h3>
                        <?php if (count($studentData['worksheet_submissions']) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            หัวข้อ
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ภาษา
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            ส่งเมื่อ
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            สถานะ
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            คะแนน
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach (array_slice($studentData['worksheet_submissions'], 0, 5) as $submission): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo htmlspecialchars($submission['title']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo getLanguageDisplay($submission['language']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo formatDate($submission['submitted_at']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo getStatusBadge($submission['status']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php echo $submission['status'] == 'graded' ? $submission['score'] : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (count($studentData['worksheet_submissions']) > 5): ?>
                        <div class="mt-2 text-center">
                            <button data-tab="worksheets" class="tab-link text-blue-500 hover:underline">
                                ดูโจทย์งานทั้งหมด (<?php echo count($studentData['worksheet_submissions']); ?>)
                            </button>
                        </div>
                        <?php endif; ?>
                        <?php else: ?>
                        <div class="text-gray-500 text-center py-6 bg-gray-50 rounded">
                            ยังไม่มีข้อมูลการส่งโจทย์งาน
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lessons Tab -->
                <div id="lessons-tab" class="tab-content hidden">
                    <?php if (count($studentData['detailed_lessons']) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ลำดับ
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ภาษา
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        บทเรียน
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        สถานะ
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        คะแนน
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        เสร็จสิ้นเมื่อ
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($studentData['detailed_lessons'] as $lesson): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $lesson['order_num']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo getLanguageDisplay($lesson['language']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php echo htmlspecialchars($lesson['title']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo getStatusBadge($lesson['status']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $lesson['status'] == 'completed' ? $lesson['score'] : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $lesson['status'] == 'completed' ? formatDate($lesson['completed_at']) : '-'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-gray-500 text-center py-6 bg-gray-50 rounded">
                        ไม่พบข้อมูลบทเรียน หรือยังไม่มีการเรียน
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tests Tab -->
                <div id="tests-tab" class="tab-content hidden">
                    <?php if (count($studentData['test_results']) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ภาษา
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ประเภทแบบทดสอบ
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        คะแนน
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        เปอร์เซ็นต์
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ทำเมื่อ
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        การปรับปรุง
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $improvementByLanguage = [];
                                
                                // First pass to gather data by language
                                foreach ($studentData['test_results'] as $test) {
                                    if (!isset($improvementByLanguage[$test['language']])) {
                                        $improvementByLanguage[$test['language']] = [
                                            'pre' => null,
                                            'post' => null
                                        ];
                                    }
                                    
                                    $improvementByLanguage[$test['language']][$test['test_type']] = [
                                        'score' => $test['score'],
                                        'total' => $test['total_questions']
                                    ];
                                }
                                
                                // Now display with improvement calculation
                                foreach ($studentData['test_results'] as $test): 
                                    $preTest = $improvementByLanguage[$test['language']]['pre'];
                                    $postTest = $improvementByLanguage[$test['language']]['post'];
                                    
                                    $improvement = null;
                                    if ($test['test_type'] == 'post' && $preTest && $postTest) {
                                        $prePercent = ($preTest['score'] / $preTest['total']) * 100;
                                        $postPercent = ($postTest['score'] / $postTest['total']) * 100;
                                        $improvement = $postPercent - $prePercent;
                                    }
                                ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo getLanguageDisplay($test['language']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $test['test_type'] == 'pre' ? 'แบบทดสอบก่อนเรียน' : 'แบบทดสอบหลังเรียน'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $test['score']; ?>/<?php echo $test['total_questions']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                            $percent = $test['total_questions'] > 0 
                                                ? round(($test['score'] / $test['total_questions']) * 100, 1) 
                                                : 0;
                                            echo $percent . '%';
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo formatDate($test['completed_at']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($improvement !== null): ?>
                                        <span
                                            class="<?php echo $improvement >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $improvement > 0 ? '+' : ''; ?><?php echo round($improvement, 1); ?>%
                                        </span>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-gray-500 text-center py-6 bg-gray-50 rounded">
                        ยังไม่มีข้อมูลการทดสอบ
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Worksheets Tab -->
                <div id="worksheets-tab" class="tab-content hidden">
                    <?php if (count($studentData['worksheet_submissions']) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        หัวข้อ
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ภาษา
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ระดับความยาก
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ส่งเมื่อ
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        สถานะ
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        คะแนน
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        อาจารย์
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ตรวจเมื่อ
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($studentData['worksheet_submissions'] as $submission): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <a href="view_submission.php?worksheet_id=<?php echo $submission['id']; ?>&user_id=<?php echo $studentFilter; ?>"
                                            class="text-blue-500 hover:underline">
                                            <?php echo htmlspecialchars($submission['title']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo getLanguageDisplay($submission['language']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full <?php 
                                            switch ($submission['difficulty']) {
                                                case 'easy': echo 'bg-green-100 text-green-800'; break;
                                                case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'hard': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                        ?>">
                                            <?php 
                                                switch ($submission['difficulty']) {
                                                    case 'easy': echo 'ง่าย'; break;
                                                    case 'medium': echo 'ปานกลาง'; break;
                                                    case 'hard': echo 'ยาก'; break;
                                                    default: echo $submission['difficulty'];
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo formatDate($submission['submitted_at']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo getStatusBadge($submission['status']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $submission['status'] == 'graded' ? $submission['score'] : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($submission['teacher_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $submission['status'] == 'graded' ? formatDate($submission['graded_at']) : '-'; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-gray-500 text-center py-6 bg-gray-50 rounded">
                        ยังไม่มีข้อมูลการส่งโจทย์งาน
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    // Tab switching functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        const tabLinks = document.querySelectorAll('.tab-link');

        function showTab(tabId) {
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });

            // Remove active class from all tabs
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabId + '-tab').classList.remove('hidden');

            // Set active class on selected tab
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
        }

        // Add click event listeners to tabs
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                showTab(tab.getAttribute('data-tab'));
            });
        });

        // Add click event listeners to in-page tab links
        tabLinks.forEach(link => {
            link.addEventListener('click', () => {
                showTab(link.getAttribute('data-tab'));
            });
        });

        // Auto-submit form when student or language selection changes
        const filterForm = document.querySelector('form');
        const studentSelect = document.getElementById('student');
        const languageSelect = document.getElementById('language');

        studentSelect.addEventListener('change', () => {
            filterForm.submit();
        });

        languageSelect.addEventListener('change', () => {
            filterForm.submit();
        });
    });
    </script>
</body>

</html>