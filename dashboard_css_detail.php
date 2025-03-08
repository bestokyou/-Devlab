<?php
require_once 'config.php';
checkLogin();
// Fetch all CSS lessons
$stmt = $pdo->prepare("
    SELECT l.*, 
           CASE WHEN p.completed = 1 THEN true ELSE false END as is_completed,
           CASE WHEN prev_lesson.id IS NULL OR prev_progress.completed = 1 THEN true ELSE false END as is_available
    FROM lessons l
    LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ? AND p.language = l.language
    LEFT JOIN (
        SELECT l2.id, l2.order_num 
        FROM lessons l2 
        WHERE l2.language = 'css'
    ) prev_lesson ON prev_lesson.order_num = l.order_num - 1 AND prev_lesson.order_num > 0
    LEFT JOIN progress prev_progress ON prev_progress.lesson_id = prev_lesson.id AND prev_progress.user_id = ? AND prev_progress.language = 'css'
    WHERE l.language = 'css'
    ORDER BY l.order_num
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Get total completed lessons
$completedLessons = array_filter($lessons, function($lesson) {
    return $lesson['is_completed'];
});
$progress = count($completedLessons) / count($lessons) * 100;

// ตรวจสอบว่าผู้ใช้ทำแบบทดสอบก่อนหรือหลังเรียนไปแล้วหรือไม่
$testStmt = $pdo->prepare("
    SELECT test_type, score, total_questions 
    FROM test_results 
    WHERE user_id = ? AND language = 'css'
");
$testStmt->execute([$_SESSION['user_id']]);
$testResults = $testStmt->fetchAll(PDO::FETCH_ASSOC);

// แปลงผลลัพธ์ให้ใช้งานง่ายขึ้น
$preTestResult = null;
$postTestResult = null;

foreach ($testResults as $result) {
    if ($result['test_type'] === 'pre') {
        $preTestResult = $result;
    } elseif ($result['test_type'] === 'post') {
        $postTestResult = $result;
    }
}

$preTestCompleted = $preTestResult !== null;
$postTestCompleted = $postTestResult !== null;

// ตรวจสอบว่าผู้ใช้มีสิทธิ์ทำแบบทดสอบหลังเรียนหรือไม่ (ต้องทำบทเรียนให้ครบอย่างน้อย 90%)
$canTakePostTest = (count($completedLessons) / count($lessons)) >= 0.9;
?>
<!DOCTYPE html>
<html>

<head>
    <title>CSS Lessons - DevLab</title>
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
    <div class="container mx-auto px-4 ">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8 pb-6 border-b bg-white shadow p-5 rounded">
            <div class="flex items-center">
                <a href="dashboard.php">
                    <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                </a>
                <h1 class="text-2xl font-bold">ภาพรวมหลักสูตร CSS</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังแดชบอร์ด</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- แบบทดสอบก่อนเรียน -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">แบบทดสอบก่อนเรียน</h2>
                    <?php if ($preTestCompleted): ?>
                    <div class="flex items-center gap-2">
                        <div class="bg-green-100 text-green-800 px-4 py-2 rounded-lg">
                            <p>คะแนน:
                                <?php echo $preTestResult['score']; ?>/<?php echo $preTestResult['total_questions']; ?>
                            </p>
                        </div>
                        <a href="view_test_results.php?language=css&type=pre"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd"
                                    d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>
                    <?php else: ?>
                    <a href="css_pretest.php"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-6 py-2 rounded-lg transition-colors flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                clip-rule="evenodd" />
                        </svg>
                        เริ่มทำแบบทดสอบ
                    </a>
                    <?php endif; ?>
                </div>
                <p class="text-gray-600 text-sm">
                    แบบทดสอบนี้จะช่วยวัดความรู้พื้นฐานของคุณก่อนเริ่มเรียน CSS
                </p>
            </div>
            <!-- CSS Learning Resources Card -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">แหล่งการเรียนรู้ CSS</h2>
                    <a href="content_knowledge.php?language=css"
                        class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                        เรียนรู้พื้นฐาน CSS
                    </a>
                </div>
            </div>



            <!-- แบบทดสอบหลังเรียน -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold">แบบทดสอบหลังเรียน</h2>
                    <?php if ($postTestCompleted): ?>
                    <div class="flex items-center gap-2">
                        <div class="bg-green-100 text-green-800 px-4 py-2 rounded-lg">
                            <p>คะแนน:
                                <?php echo $postTestResult['score']; ?>/<?php echo $postTestResult['total_questions']; ?>
                            </p>
                        </div>
                        <a href="view_test_results.php?language=css&type=post"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                <path fill-rule="evenodd"
                                    d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>
                    <?php elseif ($canTakePostTest): ?>
                    <a href="css_posttest.php"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg transition-colors flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                            <path fill-rule="evenodd"
                                d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"
                                clip-rule="evenodd" />
                        </svg>
                        เริ่มทำแบบทดสอบ
                    </a>
                    <?php else: ?>
                    <div class="bg-gray-100 text-gray-500 px-4 py-2 rounded-lg">
                        <p>ต้องเรียนให้ครบ 90% ก่อน</p>
                    </div>
                    <?php endif; ?>
                </div>
                <p class="text-gray-600 text-sm">
                    แบบทดสอบนี้จะวัดความรู้ของคุณหลังจากเรียน CSS ครบทุกบทเรียน
                </p>
            </div>
        </div>


        <!-- Progress Overview -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold">ความคืบหน้าการเรียนรู้ CSS ของคุณ</h2>
                    <p class="text-gray-600">
                        <?php echo count($completedLessons); ?> of <?php echo count($lessons); ?> lessons completed
                    </p>
                </div>
                <img src="img/css.png" alt="CSS Logo" class="h-12">
            </div>
            <div class="w-full bg-gray-200 rounded-full h-4">
                <div class="css-gradient h-4 rounded-full transition-all duration-500"
                    style="width: <?php echo $progress; ?>%">
                </div>
            </div>
        </div>

        <!-- Learning Path Guide -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h3 class="text-lg font-semibold mb-4">สถานะ</h3>
            <div class="flex gap-4">
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                    <span>เสร็จสิ้น</span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                    <span>ยังไม่เสร็จ</span>
                </div>
                <div class="flex items-center">
                    <span class="w-3 h-3 bg-gray-300 rounded-full mr-2"></span>
                    <span>ล็อค</span>
                </div>
            </div>
        </div>
        <!-- Lessons Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($lessons as $lesson): ?>
            <div
                class="lesson-card bg-white rounded-lg shadow-md overflow-hidden relative <?php echo (!$lesson['is_completed'] && !$lesson['is_available']) ? 'opacity-50' : ''; ?>">
                <div class="p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">
                            <?php echo htmlspecialchars($lesson['title']); ?>
                        </h3>
                    </div>
                    <div class="text-gray-600 mb-4 prose">
                        <?php 
                $description = strip_tags($lesson['description']);
                echo substr($description, 0, 100) . (strlen($description) > 100 ? '...' : ''); 
                ?>
                    </div>
                    <div class="mt-4 space-y-2">
                        <?php if ($lesson['is_completed']): ?>
                        <span class="block text-green-600 text-sm mb-2">
                            ✓ เสร็จสมบูรณ์
                        </span>
                        <?php endif; ?>
                        <?php if ($lesson['is_available']): ?>
                        <?php if ($lesson['is_completed']): ?>
                        <div class="flex gap-2">
                            <a href="css_lesson.php?language=css&lesson=<?php echo $lesson['id']; ?>"
                                class="flex-1 text-center bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 transition">
                                ทบทวนบทเรียน
                            </a>
                        </div>
                        <?php else: ?>
                        <a href="css_lesson.php?css&lesson=<?php echo $lesson['id']; ?>"
                            class="block text-center bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">
                            เริ่มบทเรียน
                        </a>
                        <?php endif; ?>
                        <?php else: ?>
                        <button disabled class="w-full bg-gray-300 text-gray-500 px-4 py-2 rounded cursor-not-allowed">
                            บทเรียนก่อนหน้าให้เสร็จสิ้นก่อน
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Help Button -->
    <div class="fixed bottom-4 right-4">
        <button onclick="alert('Need help with PHP? Contact our support team for assistance!')"
            class="bg-blue-500 text-white p-4 rounded-full shadow-lg hover:bg-blue-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>
    </div>
</body>

</html>