<?php
// all_students.php - List all students for teachers and admins
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
$languageFilter = isset($_GET['language']) ? $_GET['language'] : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';

// Only allow teachers to see languages they teach
if (!$userInfo['is_admin'] && $languageFilter) {
    $teacherLanguages = explode(',', $userInfo['languages']);
    if (!in_array($languageFilter, $teacherLanguages)) {
        $languageFilter = '';
    }
}

// Get available languages
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

// Base query for students
$studentsQuery = "
    SELECT 
        u.id, 
        u.username, 
        u.email, 
        u.created_at,
        COUNT(DISTINCT p.lesson_id) as completed_lessons,
        COALESCE(SUM(p.score), 0) as total_score,
        COUNT(DISTINCT ws.id) as submitted_worksheets
    FROM users u
    LEFT JOIN progress p ON u.id = p.user_id AND p.completed = 1
    LEFT JOIN worksheet_submissions ws ON u.id = ws.user_id
";

// Add language filter if needed
if ($languageFilter) {
    $studentsQuery .= "
        LEFT JOIN lessons l ON p.lesson_id = l.id
        LEFT JOIN worksheets w ON ws.worksheet_id = w.id
        WHERE u.is_admin = 0 
        AND (l.language = :language OR w.language = :language)
    ";
} else {
    $studentsQuery .= "
        WHERE u.is_admin = 0
    ";
}

// Add search filter if provided
if ($searchFilter) {
    if ($languageFilter) {
        $studentsQuery .= " AND (u.username LIKE :search OR u.email LIKE :search)";
    } else {
        $studentsQuery .= " AND (u.username LIKE :search OR u.email LIKE :search)";
    }
}

// Finalize the query
$studentsQuery .= "
    GROUP BY u.id
    ORDER BY u.username
";

// Prepare and execute the query
$studentsStmt = $pdo->prepare($studentsQuery);

// Bind parameters
if ($languageFilter) {
    $studentsStmt->bindValue(':language', $languageFilter);
}
if ($searchFilter) {
    $studentsStmt->bindValue(':search', "%$searchFilter%");
}

$studentsStmt->execute();
$students = $studentsStmt->fetchAll();

// Get total lesson count by language for progress calculation
$lessonCountsQuery = $pdo->query("
    SELECT language, COUNT(*) as count
    FROM lessons
    GROUP BY language
");
$lessonCounts = $lessonCountsQuery->fetchAll(PDO::FETCH_KEY_PAIR);

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
?>
<!DOCTYPE html>
<html>

<head>
    <title>รายชื่อนักเรียนทั้งหมด - DevLab</title>
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

    /* Table styles */
    .student-table th {
        position: sticky;
        top: 0;
        background-color: #f9fafb;
        z-index: 10;
    }

    .student-row {
        transition: all 0.2s ease-in-out;
    }

    .student-row:hover {
        background-color: #f3f4f6;
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
                    <h1 class="text-2xl font-bold">รายชื่อนักเรียนทั้งหมด</h1>
                    <p class="text-sm text-gray-600">สวัสดี, <?php echo htmlspecialchars($username); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <?php if ($userInfo['is_admin']): ?>
                <a href="admin_dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าผู้ดูแลระบบ</a>
                <?php else: ?>
                <a href="dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังแดชบอร์ด</a>
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
            <form method="GET" action="all_students.php" class="flex flex-wrap gap-4 items-end">
                <div class="w-full md:w-auto flex-grow">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="search">ค้นหา</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>"
                        class="w-full rounded-md border border-gray-300 py-2 px-3"
                        placeholder="ค้นหาด้วยชื่อผู้ใช้ หรืออีเมล">
                </div>

                <div class="w-full md:w-auto">
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="language">ภาษา</label>
                    <select id="language" name="language" class="rounded-md border border-gray-300 py-2 px-3">
                        <option value="">-- ทุกภาษา --</option>
                        <?php foreach ($languages as $lang): ?>
                        <option value="<?php echo $lang; ?>" <?php echo $languageFilter == $lang ? 'selected' : ''; ?>>
                            <?php echo getLanguageDisplay($lang); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="w-full md:w-auto flex gap-2">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        ค้นหา
                    </button>
                    <?php if ($searchFilter || $languageFilter): ?>
                    <a href="all_students.php" class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                        ล้างตัวกรอง
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Students Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold">นักเรียนทั้งหมด (<?php echo count($students); ?> คน)</h2>
            </div>

            <?php if (count($students) > 0): ?>
            <div class="overflow-x-auto max-h-[70vh]">
                <table class="min-w-full divide-y divide-gray-200 student-table">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ชื่อผู้ใช้
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                อีเมล
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                วันที่สมัคร
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                บทเรียนที่เสร็จสิ้น
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                คะแนนรวม
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                โจทย์งานที่ส่ง
                            </th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                การจัดการ
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($students as $student): ?>
                        <tr class="student-row">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($student['username']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-gray-500"><?php echo htmlspecialchars($student['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-gray-500"><?php echo formatDate($student['created_at']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-gray-500"><?php echo $student['completed_lessons']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-gray-500"><?php echo $student['total_score']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-gray-500"><?php echo $student['submitted_worksheets']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <a href="student_progress.php?student=<?php echo $student['id']; ?><?php echo $languageFilter ? '&language='.$languageFilter : ''; ?>"
                                    class="inline-flex items-center px-3 py-1.5 bg-blue-500 text-white rounded hover:bg-blue-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd"
                                            d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    ดูความคืบหน้า
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-8 text-center text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400 mb-4" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="text-lg font-medium mb-2">ไม่พบข้อมูลนักเรียน</p>
                <p>ไม่พบข้อมูลนักเรียนที่ตรงกับเงื่อนไขการค้นหา</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pagination (if needed in the future) -->
        <div class="mt-6 flex justify-center">
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                <a href="#"
                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Previous</span>
                    <!-- Heroicon name: solid/chevron-left -->
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                        aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                            clip-rule="evenodd" />
                    </svg>
                </a>
                <a href="#" aria-current="page"
                    class="z-10 bg-blue-50 border-blue-500 text-blue-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                    1
                </a>
                <a href="#"
                    class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                    2
                </a>
                <a href="#"
                    class="bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                    3
                </a>
                <a href="#"
                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Next</span>
                    <!-- Heroicon name: solid/chevron-right -->
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"
                        aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                            clip-rule="evenodd" />
                    </svg>
                </a>
            </nav>
        </div>
    </div>

    <script>
    // Submit form when language filter changes
    document.getElementById('language').addEventListener('change', function() {
        this.form.submit();
    });
    </script>
</body>

</html>