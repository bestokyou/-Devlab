<?php
// dashboard.php - Fixed version with proper progress calculation
require_once 'config.php';
checkLogin();
// ดึงคะแนนรวมของแต่ละภาษา
$stmt = $pdo->prepare("SELECT language, SUM(score) as total_score FROM progress WHERE user_id = ? GROUP BY language");
$stmt->execute([$_SESSION['user_id']]);
$scores = $stmt->fetchAll();
// ดึงบทเรียนที่ผ่านแล้ว
$completedLessons = $pdo->prepare("
    SELECT language, COUNT(*) as completed 
    FROM progress 
    WHERE user_id = ? AND completed = 1 
    GROUP BY language
");
$completedLessons->execute([$_SESSION['user_id']]);
$completed = $completedLessons->fetchAll(PDO::FETCH_KEY_PAIR);

// ดึงจำนวนบทเรียนทั้งหมดของแต่ละภาษา
$totalLessonsQuery = $pdo->prepare("
    SELECT language, COUNT(*) as total
    FROM lessons
    GROUP BY language
");
$totalLessonsQuery->execute();
$totalLessonsByLanguage = $totalLessonsQuery->fetchAll(PDO::FETCH_KEY_PAIR);

// ฟังก์ชั่นสำหรับดึงคะแนนของแต่ละภาษา
function getLanguageScore($scores, $language) {
    $filtered = array_filter($scores, function($s) use ($language) { 
        return $s['language'] == $language; 
    });
    return !empty($filtered) ? current($filtered)['total_score'] : 0;
}
// ฟังก์ชั่นสำหรับดึงจำนวนบทเรียนที่ผ่านแล้ว
function getCompletedLessons($completed, $language) {
    return isset($completed[$language]) ? $completed[$language] : 0;
}

// ฟังก์ชั่นสำหรับคำนวณเปอร์เซ็นต์ความคืบหน้า
function calculateProgress($completed, $total) {
    if ($total == 0) return 0;
    
    // คำนวณเปอร์เซ็นต์ความคืบหน้า
    $progress = ($completed / $total) * 100;
    
    // จำกัดค่าไม่ให้เกิน 100%
    return min($progress, 100);
}

// ดึงจำนวนบทเรียนทั้งหมดของแต่ละภาษา (ใช้ค่าจริงจากฐานข้อมูล)
$htmlTotalLessons = isset($totalLessonsByLanguage['html']) ? $totalLessonsByLanguage['html'] : 1; 
$cssTotalLessons = isset($totalLessonsByLanguage['css']) ? $totalLessonsByLanguage['css'] : 1;
$phpTotalLessons = isset($totalLessonsByLanguage['php']) ? $totalLessonsByLanguage['php'] : 1;

// ให้แน่ใจว่าจำนวนบทเรียนไม่เป็น 0 เพื่อป้องกันการหารด้วย 0
$htmlTotalLessons = max($htmlTotalLessons, 1);
$cssTotalLessons = max($cssTotalLessons, 1);
$phpTotalLessons = max($phpTotalLessons, 1);

// คำนวณความคืบหน้าสำหรับแต่ละภาษา
$htmlProgress = calculateProgress(getCompletedLessons($completed, 'html'), $htmlTotalLessons);
$cssProgress = calculateProgress(getCompletedLessons($completed, 'css'), $cssTotalLessons);
$phpProgress = calculateProgress(getCompletedLessons($completed, 'php'), $phpTotalLessons);

$totalScore = !empty($scores) ? array_sum(array_column($scores, 'total_score')) : 0;
$totalLessons = !empty($completed) ? array_sum($completed) : 0;
$maxScore = !empty($scores) ? max(array_column($scores, 'total_score')) : 0;

// คำนวณจำนวนบทเรียนทั้งหมดในระบบ (ใช้ค่าจริงจากฐานข้อมูล)
$totalSystemLessons = array_sum(array_values($totalLessonsByLanguage));
$totalSystemLessons = max($totalSystemLessons, 1); // ป้องกันการหารด้วย 0

$completionRate = $totalLessons > 0 ? ($totalLessons / $totalSystemLessons) * 100 : 0;

// Check if the user is an admin
$adminCheckStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$adminCheckStmt->execute([$_SESSION['user_id']]);
$isAdmin = $adminCheckStmt->fetchColumn();

// Check if the user is a teacher
$teacherCheckStmt = $pdo->prepare("
    SELECT t.id, t.languages 
    FROM teachers t
    WHERE t.user_id = ?
");
$teacherCheckStmt->execute([$_SESSION['user_id']]);
$isTeacher = $teacherCheckStmt->rowCount() > 0;
$teacherData = $teacherCheckStmt->fetch();
?>
<!DOCTYPE html>
<html>

<head>
    <title>Dashboard</title>
    <link rel="icon" type="image/png" href="icon1.png">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
</head>

<body class=" bg-gray-900">
    <div class="container mx-auto px-4  ">
        <div class="flex justify-between items-center mb-8 pb-6 border-b bg-white shadow p-5 mb-5 bg-body rounded">
            <div class="flex items-center">
                <a href="dashboard.php">
                    <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                </a>
            </div>
            <div class="flex items-center gap-9">
                ลงชื่อเข้าใช้โดย : <a href="profile.php" class="hover:underline bg-gray-100 px-6 py-1 "><span
                        class="text-lg font-bold text-blue-500"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- HTML Card -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <img src="img/html.png" alt="DevLab Logo" class="h-10 mr-4 float-right">
                <h3 class="text-xl mb-4">HTML</h3>
                <div class="mb-4">
                    <p class="text-gray-600">ความคืบหน้า:
                        <?php echo getCompletedLessons($completed, 'html'); ?> บทเรียนเสร็จสิ้น
                    </p>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                        <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $htmlProgress; ?>%">
                        </div>
                    </div>
                </div>
                <p class="mb-4">คะแนน: <?php echo getLanguageScore($scores, 'html'); ?></p>
                <a href="dashboard_html_detail.php"
                    class="bg-blue-500 text-white px-4 py-2 rounded block text-center hover:bg-blue-600">
                    เริ่มต้นการเรียนรู้ HTML
                </a>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <img src="img/css.png" alt="DevLab Logo" class="h-10 mr-4 float-right">
                <h3 class="text-xl mb-4">CSS</h3>
                <div class="mb-4">
                    <p class="text-gray-600">ความคืบหน้า:
                        <?php echo getCompletedLessons($completed, 'css'); ?> บทเรียนเสร็จสิ้น
                    </p>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                        <div class="bg-green-600 h-2.5 rounded-full" style="width: <?php echo $cssProgress; ?>%">
                        </div>
                    </div>
                </div>
                <p class="mb-4">คะแนน: <?php echo getLanguageScore($scores, 'css'); ?></p>
                <a href="dashboard_css_detail.php"
                    class="bg-green-500 text-white px-4 py-2 rounded block text-center hover:bg-green-600">
                    เริ่มต้นการเรียนรู้ CSS
                </a>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <img src="img/php.png" alt="DevLab Logo" class="h-10 mr-4 float-right">
                <h3 class="text-xl mb-4">PHP</h3>
                <div class="mb-4">
                    <p class="text-gray-600">ความคืบหน้า:
                        <?php echo getCompletedLessons($completed, 'php'); ?> บทเรียนเสร็จสิ้น
                    </p>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                        <div class="bg-yellow-600 h-2.5 rounded-full" style="width: <?php echo $phpProgress; ?>%">
                        </div>
                    </div>
                </div>
                <p class="mb-4">คะแนน: <?php echo getLanguageScore($scores, 'php'); ?></p>
                <a href="dashboard_php_detail.php"
                    class="bg-yellow-500 text-white px-4 py-2 rounded block text-center hover:bg-yellow-600">
                    เริ่มต้นการเรียนรู้ PHP
                </a>
            </div>
        </div>

        <!-- Teacher & Admin Section -->
        <div
            class="grid grid-cols-1 md:grid-cols-<?php echo (!$isAdmin && !$isTeacher) ? '1' : ($isAdmin && $isTeacher ? '2' : '1'); ?> gap-6 mt-8 mb-8">
            <?php if ($isAdmin): ?>
            <!-- Admin Panel -->
            <div
                class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-300">
                <h3 class="text-xl font-bold mb-3">จัดการระบบ</h3>
                <p class="mb-4">ตั้งค่าระบบและจัดการผู้ใช้ในฐานะผู้ดูแลระบบ</p>
                <a href="admin_dashboard.php"
                    class="inline-block bg-white text-indigo-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                    ไปที่หน้าผู้ดูแลระบบ
                </a>
            </div>
            <?php endif; ?>

            <?php if ($isTeacher): ?>
            <!-- Teacher Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div
                    class="bg-gradient-to-br from-blue-500 to-cyan-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-300">
                    <h3 class="text-xl font-bold mb-3">จัดการโจทย์งาน</h3>
                    <p class="mb-4">สร้างและจัดการโจทย์งานสำหรับนักเรียนในฐานะอาจารย์</p>
                    <a href="teacher_dashboard.php"
                        class="inline-block bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                        ไปที่หน้าอาจารย์
                    </a>
                </div>

                <div
                    class="bg-gradient-to-br from-green-500 to-teal-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-300">
                    <h3 class="text-xl font-bold mb-3">จัดการบทเรียน</h3>
                    <p class="mb-4">เพิ่ม ลบ หรือแก้ไขบทเรียนในภาษาที่คุณสอน</p>
                    <a href="teacher_manage_lessons.php"
                        class="inline-block bg-white text-green-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                        จัดการบทเรียน
                    </a>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div
                    class="bg-gradient-to-br from-red-500 to-cyan-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-300">
                    <h3 class="text-xl font-bold mb-3">จัดการเนื้อหา</h3>
                    <p class="mb-4">สร้างและจัดการเนื้อหาสำหรับนักเรียนในฐานะอาจารย์</p>
                    <a href="manage_content.php"
                        class="inline-block bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                        ไปที่หน้าจัดการเนื้อหา
                    </a>
                </div>
                <div
                    class="bg-gradient-to-br from-purple-500 to-cyan-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-300">
                    <h3 class="text-xl font-bold mb-3">จัดการแบบทดสอบ</h3>
                    <p class="mb-4">จัดการคำถามแบบทดสอบก่อนเรียนและหลังเรียน</p>
                    <a href="teacher_manage_questions.php"
                        class="inline-block bg-white text-blue-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                        ไปที่หน้าจัดการแบบทดสอบ
                    </a>
                </div>
                
                
                <a href="all_students.php"
                    class="admin-card bg-white rounded-lg shadow-md p-6 flex flex-col items-center text-center hover:shadow-lg">
                    <div class="bg-blue-100 p-3 rounded-full mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">ติดตามความคืบหน้านักเรียน</h3>
                    <p class="text-gray-600">ดูความคืบหน้าและผลการเรียนของนักเรียน</p>
                </a>
                
                <?php endif; ?>

                <!-- Worksheet Access for regular users only (not admin or teacher) -->
                <?php if (!$isAdmin && !$isTeacher): ?>
                <div
                    class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-lg shadow-lg p-6 text-white transform hover:scale-105 transition-all duration-300">
                    <h3 class="text-xl font-bold mb-3">โจทย์งานทั้งหมด</h3>
                    <p class="mb-4">เข้าถึงโจทย์งานที่อาจารย์มอบหมายทั้งหมด</p>
                    <a href="worksheets.php"
                        class="inline-block bg-white text-green-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                        ดูโจทย์งานทั้งหมด
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Statistics Section -->
            <div class="mt-20 mb-8">
                <h2 class="text-2xl font-bold text-white mb-6 text-center">สถิติการเรียนรู้ของคุณ</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Total Score Card -->
                    <div
                        class="bg-gradient-to-br from-purple-500 to-indigo-600 p-6 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300">
                        <div class="text-white">
                            <h3 class="text-lg font-semibold mb-2">คะแนนรวม</h3>
                            <p class="text-3xl font-bold"><?php echo $totalScore; ?></p>
                        </div>
                    </div>
                    <!-- Completed Lessons Card -->
                    <div
                        class="bg-gradient-to-br from-blue-500 to-teal-400 p-6 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300">
                        <div class="text-white">
                            <h3 class="text-lg font-semibold mb-2">จำนวนบทเรียนที่สำเร็จทั้งหมด</h3>
                            <p class="text-3xl font-bold">
                                <?php echo $totalLessons; ?>/<?php echo $totalSystemLessons; ?>
                            </p>
                        </div>
                    </div>
                    <!-- Learning Streak Card -->
                    <div
                        class="bg-gradient-to-br from-green-500 to-emerald-400 p-6 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300">
                        <div class="text-white">
                            <h3 class="text-lg font-semibold mb-2">คะแนนสูงสุด</h3>
                            <p class="text-3xl font-bold"><?php echo $maxScore; ?></p>
                        </div>
                    </div>
                    <!-- Achievement Card -->
                    <div
                        class="bg-gradient-to-br from-pink-500 to-rose-400 p-6 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300">
                        <div class="text-white">
                            <h3 class="text-lg font-semibold mb-2">อัตราความสำเร็จ</h3>
                            <p class="text-3xl font-bold"><?php echo round($completionRate, 1); ?>%</p>
                        </div>
                    </div>
                    <!-- Leaderboard Card -->
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                <a href="leaderboard.php"
                    class="bg-gradient-to-br from-yellow-400 to-orange-500 p-6 rounded-lg shadow-lg transform hover:scale-105 transition-all duration-300">
                    <div class="text-white">
                        <h3 class="text-lg font-semibold mb-2">Leaderboard</h3>
                        <p class="text-3xl font-bold">🏆</p>
                        <p class="text-sm mt-2">ดูอันดับผู้เรียน</p>
                    </div>
                </a>
            </div>

            <!-- Rest of the page content remains the same -->

        </div>
</body>

</html>