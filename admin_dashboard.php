<?php
// admin_dashboard.php - Admin dashboard page
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

// Get statistics for admin dashboard
// Count total users
$userCountStmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $userCountStmt->fetchColumn();

// Count total teachers - only if teachers table exists
$totalTeachers = 0;
try {
    $teacherCountStmt = $pdo->query("SELECT COUNT(*) FROM teachers");
    $totalTeachers = $teacherCountStmt->fetchColumn();
} catch (PDOException $e) {
    // Table might not exist yet
    $totalTeachers = 0;
}

// Count worksheets - only if worksheets table exists
$totalWorksheets = 0;
try {
    $worksheetCountStmt = $pdo->query("SELECT COUNT(*) FROM worksheets");
    $totalWorksheets = $worksheetCountStmt->fetchColumn();
} catch (PDOException $e) {
    // Table might not exist yet
    $totalWorksheets = 0;
}

// Count submissions - only if worksheet_submissions table exists
$totalSubmissions = 0;
try {
    $submissionCountStmt = $pdo->query("SELECT COUNT(*) FROM worksheet_submissions");
    $totalSubmissions = $submissionCountStmt->fetchColumn();
} catch (PDOException $e) {
    // Table might not exist yet
    $totalSubmissions = 0;
}

// Count total lessons
$lessonCountStmt = $pdo->query("SELECT COUNT(*) FROM lessons");
$totalLessons = $lessonCountStmt->fetchColumn();

// Count questions in pre_post_tests table
$questionCountStmt = $pdo->query("SELECT COUNT(*) FROM pre_post_tests");
$totalQuestions = $questionCountStmt->fetchColumn();
?>
<!DOCTYPE html>
<html>

<head>
    <title>หน้าผู้ดูแลระบบ - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=display"
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
                <h1 class="text-2xl font-bold">หน้าผู้ดูแลระบบ</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าหลัก</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-blue-500">
                <h3 class="text-gray-500 text-sm">ผู้ใช้ทั้งหมด</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalUsers); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-green-500">
                <h3 class="text-gray-500 text-sm">อาจารย์ทั้งหมด</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalTeachers); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-purple-500">
                <h3 class="text-gray-500 text-sm">โจทย์งานทั้งหมด</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalWorksheets); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-yellow-500">
                <h3 class="text-gray-500 text-sm">การส่งงานทั้งหมด</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalSubmissions); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-indigo-500">
                <h3 class="text-gray-500 text-sm">บทเรียนทั้งหมด</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalLessons); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-amber-500">
                <h3 class="text-gray-500 text-sm">คำถามแบบทดสอบ</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($totalQuestions); ?></p>
            </div>
        </div>

        <!-- Admin Functions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Manage Users -->
            <a href="manage_users.php"
                class="admin-card bg-white rounded-lg shadow-md p-6 flex flex-col items-center text-center hover:shadow-lg">
                <div class="bg-red-100 p-3 rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">จัดการผู้ใช้งาน</h3>
                <p class="text-gray-600">ดูรายชื่อและลบผู้ใช้งานออกจากระบบ</p>
            </a>

            <!-- Manage Teachers -->
            <a href="manage_teachers.php"
                class="admin-card bg-white rounded-lg shadow-md p-6 flex flex-col items-center text-center hover:shadow-lg">
                <div class="bg-blue-100 p-3 rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">จัดการอาจารย์</h3>
                <p class="text-gray-600">เพิ่ม แก้ไข หรือลบอาจารย์ในระบบ</p>
            </a>

            <!-- Create Teacher Account -->
            <a href="create_teacher.php"
                class="admin-card bg-white rounded-lg shadow-md p-6 flex flex-col items-center text-center hover:shadow-lg">
                <div class="bg-purple-100 p-3 rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-500" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">สร้างบัญชีอาจารย์</h3>
                <p class="text-gray-600">สร้างบัญชีผู้ใช้ใหม่ที่มีสิทธิ์เป็นอาจารย์</p>
            </a>

            <!-- Manage User Roles -->
            <a href="manage_roles.php"
                class="admin-card bg-white rounded-lg shadow-md p-6 flex flex-col items-center text-center hover:shadow-lg">
                <div class="bg-green-100 p-3 rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">จัดการสิทธิ์ผู้ใช้</h3>
                <p class="text-gray-600">กำหนดสิทธิ์ผู้ใช้และผู้ดูแลระบบ</p>
            </a>

            <!-- จัดการบทเรียน -->
            <a href="manage_lessons.php"
                class="admin-card bg-white rounded-lg shadow-md p-6 flex flex-col items-center text-center hover:shadow-lg">
                <div class="bg-indigo-100 p-3 rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-500" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">จัดการบทเรียน</h3>
                <p class="text-gray-600">เพิ่ม แก้ไข หรือลบบทเรียนในระบบ</p>
            </a>

            <!-- จัดการแบบทดสอบ -->
            <a href="manage_questions.php"
                class="admin-card bg-white rounded-lg shadow-md p-6 flex flex-col items-center text-center hover:shadow-lg">
                <div class="bg-amber-100 p-3 rounded-full mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-500" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">จัดการแบบทดสอบ</h3>
                <p class="text-gray-600">จัดการคำถามแบบทดสอบก่อนเรียนและหลังเรียน</p>
            </a>


        </div>
    </div>
</body>

</html>