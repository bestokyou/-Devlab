<?php
// worksheets.php - Page for students to view all available worksheets
require_once 'config.php';
checkLogin();

// Get filter parameters
$language = isset($_GET['language']) ? $_GET['language'] : '';
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Check if the user is a teacher
$teacherCheckStmt = $pdo->prepare("
    SELECT t.id FROM teachers t
    JOIN users u ON t.user_id = u.id
    WHERE u.id = ?
");
$teacherCheckStmt->execute([$_SESSION['user_id']]);
$isTeacher = $teacherCheckStmt->fetch() ? true : false;

// Build the query
$query = "
    SELECT w.*, 
           t.id as teacher_id,
           u.username as teacher_name,
           (SELECT COUNT(*) FROM worksheet_submissions ws WHERE ws.worksheet_id = w.id) as submission_count
    FROM worksheets w
    JOIN teachers t ON w.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE 1=1
";
$params = [];

// Apply filters
if (!empty($language)) {
    $query .= " AND w.language = ?";
    $params[] = $language;
}

if (!empty($difficulty)) {
    $query .= " AND w.difficulty = ?";
    $params[] = $difficulty;
}

if (!empty($search)) {
    $query .= " AND (w.title LIKE ? OR w.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add sorting
$query .= " ORDER BY w.created_at DESC";

// Execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$worksheets = $stmt->fetchAll();

// Check for completed worksheets
$completedWorksheetsStmt = $pdo->prepare("
    SELECT worksheet_id FROM worksheet_submissions
    WHERE user_id = ?
");
$completedWorksheetsStmt->execute([$_SESSION['user_id']]);
$completedWorksheets = [];
while ($row = $completedWorksheetsStmt->fetch()) {
    $completedWorksheets[$row['worksheet_id']] = true;
}

// Get available languages
$languagesStmt = $pdo->query("SELECT DISTINCT language FROM worksheets ORDER BY language");
$availableLanguages = $languagesStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html>
<head>
    <title>รายการโจทย์ - DevLab</title>
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
        .worksheet-card {
            transition: all 0.3s ease;
        }
        .worksheet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .badge {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            z-index: 10;
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
                <h1 class="text-2xl font-bold">รายการโจทย์</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-blue-500 hover:underline">กลับไปหน้าหลัก</a>
                <?php if ($isTeacher): ?>
                <a href="teacher_dashboard.php" class="text-green-500 hover:underline">หน้าอาจารย์</a>
                <?php endif; ?>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <form method="GET" action="">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <input type="text" name="search" placeholder="ค้นหาโจทย์..." value="<?php echo htmlspecialchars($search); ?>" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="w-auto">
                        <select name="language" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">ทุกภาษา</option>
                            <?php foreach ($availableLanguages as $lang): ?>
                                <option value="<?php echo $lang; ?>" <?php echo $language === $lang ? 'selected' : ''; ?>>
                                    <?php echo strtoupper($lang); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-auto">
                        <select name="difficulty" class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">ทุกระดับความยาก</option>
                            <option value="easy" <?php echo $difficulty === 'easy' ? 'selected' : ''; ?>>ง่าย</option>
                            <option value="medium" <?php echo $difficulty === 'medium' ? 'selected' : ''; ?>>ปานกลาง</option>
                            <option value="hard" <?php echo $difficulty === 'hard' ? 'selected' : ''; ?>>ยาก</option>
                        </select>
                    </div>
                    <div class="w-auto flex gap-2">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                            กรอง
                        </button>
                        <?php if (!empty($language) || !empty($difficulty) || !empty($search)): ?>
                            <a href="worksheets.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                                รีเซ็ต
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Worksheets Grid -->
        <?php if (count($worksheets) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <?php foreach ($worksheets as $worksheet): ?>
                    <?php 
                    $isDue = !empty($worksheet['due_date']) && strtotime($worksheet['due_date']) < time();
                    $isCompleted = isset($completedWorksheets[$worksheet['id']]);
                    
                    // Set card classes based on status
                    $cardClasses = "worksheet-card bg-white rounded-lg shadow-md overflow-hidden relative";
                    if ($isCompleted) {
                        $cardClasses .= " border-l-4 border-green-500";
                    } elseif ($isDue) {
                        $cardClasses .= " border-l-4 border-red-500";
                    }
                    ?>
                    <div class="<?php echo $cardClasses; ?>">
                        <?php if ($isCompleted): ?>
                            <div class="badge bg-green-500 text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        <?php endif; ?>
                        
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($worksheet['title']); ?></h3>
                                <span class="inline-block px-2 py-1 rounded text-xs font-semibold <?php 
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
                            </div>
                            
                            <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                <span class="font-medium font-english"><?php echo strtoupper($worksheet['language']); ?></span>
                                <span class="text-gray-400">|</span>
                                <span>โดย <?php echo htmlspecialchars($worksheet['teacher_name']); ?></span>
                            </div>
                            
                            <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                <?php 
                                // Show first 100 characters of description
                                $description = strip_tags($worksheet['description']);
                                echo strlen($description) > 100 
                                    ? htmlspecialchars(substr($description, 0, 100)) . '...' 
                                    : htmlspecialchars($description);
                                ?>
                            </p>
                            
                            <?php if (!empty($worksheet['due_date'])): ?>
                                <div class="text-sm mb-4 <?php echo $isDue ? 'text-red-600 font-medium' : 'text-gray-600'; ?>">
                                    <span>กำหนดส่ง: <?php echo date('d/m/Y H:i', strtotime($worksheet['due_date'])); ?></span>
                                    <?php if ($isDue): ?>
                                        <span class="ml-2 text-red-600">(เลยกำหนดแล้ว)</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-sm mb-4 text-gray-600">ไม่มีกำหนดส่ง</div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-center">
                                <div class="text-sm text-gray-600">
                                    สร้างเมื่อ <?php echo date('d/m/Y', strtotime($worksheet['created_at'])); ?>
                                </div>
                                
                                <div>
                                    <?php if ($isCompleted): ?>
                                        <?php 
                                        // Find submission ID for completed worksheet
                                        $submissionStmt = $pdo->prepare("
                                            SELECT id FROM worksheet_submissions
                                            WHERE worksheet_id = ? AND user_id = ?
                                        ");
                                        $submissionStmt->execute([$worksheet['id'], $_SESSION['user_id']]);
                                        $submission = $submissionStmt->fetch();
                                        ?>
                                        <a href="view_worksheet_submission.php?id=<?php echo $submission['id']; ?>" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                            ดูงานที่ส่ง
                                        </a>
                                    <?php else: ?>
                                        <a href="do_worksheet.php?id=<?php echo $worksheet['id']; ?>" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                            ทำโจทย์
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-xl font-semibold mb-2">ไม่พบโจทย์</h3>
                <p class="text-gray-600 mb-4">ยังไม่มีโจทย์ที่ตรงกับเงื่อนไขการค้นหาของคุณ</p>
                <a href="worksheets.php" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 inline-block">
                    ดูโจทย์ทั้งหมด
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>