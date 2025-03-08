<?php
require_once 'config.php';
checkLogin();

// ตรวจสอบว่าผู้ใช้มีสิทธิ์ทำแบบทดสอบหลังเรียนหรือไม่ (ต้องทำบทเรียนให้ครบอย่างน้อย 90%)
$lessonsStmt = $pdo->prepare("
    SELECT COUNT(*) as total_lessons,
           SUM(CASE WHEN p.completed = 1 THEN 1 ELSE 0 END) as completed_lessons
    FROM lessons l
    LEFT JOIN progress p ON l.id = p.lesson_id AND p.user_id = ? AND p.language = l.language
    WHERE l.language = 'css'
");
$lessonsStmt->execute([$_SESSION['user_id']]);
$lessons = $lessonsStmt->fetch();

$completionPercentage = ($lessons['completed_lessons'] / $lessons['total_lessons']) * 100;
if ($completionPercentage < 90) {
    $_SESSION['test_message'] = "คุณต้องเรียน CSS ให้ครบอย่างน้อย 90% ก่อนทำแบบทดสอบหลังเรียน";
    header('Location: dashboard_css_detail.php');
    exit();
}

// ตรวจสอบว่าผู้ใช้เคยทำแบบทดสอบหลังเรียนแล้วหรือไม่
$checkTestStmt = $pdo->prepare("
    SELECT id, score, total_questions 
    FROM test_results 
    WHERE user_id = ? AND language = 'css' AND test_type = 'post'
");
$checkTestStmt->execute([$_SESSION['user_id']]);
$existingTest = $checkTestStmt->fetch();

// ถ้าเคยทำแล้ว ให้ redirect ไปดูผลการทดสอบ
if ($existingTest) {
    $_SESSION['test_message'] = "คุณได้ทำแบบทดสอบหลังเรียน CSS ไปแล้ว คะแนนของคุณคือ {$existingTest['score']}/{$existingTest['total_questions']}";
    header('Location: dashboard_css_detail.php');
    exit();
}

// ดึงข้อสอบ
$questionsStmt = $pdo->prepare("
    SELECT * FROM pre_post_tests 
    WHERE language = 'css' AND type = 'post'
    ORDER BY id
");
$questionsStmt->execute();
$questions = $questionsStmt->fetchAll();

// สลับลำดับข้อสอบ (สามารถเปิด-ปิดฟีเจอร์นี้ได้)
// shuffle($questions); 

// ตรวจสอบการส่งคำตอบ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];
    
    // แปลงคำตอบทั้งหมดเป็นตัวพิมพ์ใหญ่
    foreach ($answers as $questionId => $userAnswer) {
        $answers[$questionId] = strtoupper($userAnswer);
    }
    
    $score = 0;
    $totalQuestions = count($questions);
    
    // ตรวจคำตอบและคำนวณคะแนน
    foreach ($questions as $question) {
        $questionId = $question['id'];
        if (isset($answers[$questionId]) && $answers[$questionId] === $question['correct_answer']) {
            $score++;
        }
    }
    
    // บันทึกผลการทดสอบ
    $pdo->beginTransaction();
    try {
        // บันทึกผลรวม
        $insertStmt = $pdo->prepare("
            INSERT INTO test_results (user_id, language, test_type, score, total_questions)
            VALUES (?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([$_SESSION['user_id'], 'css', 'post', $score, $totalQuestions]);
        $resultId = $pdo->lastInsertId();
        
        // บันทึกคำตอบแต่ละข้อ
        $answerStmt = $pdo->prepare("
            INSERT INTO test_answers (result_id, question_id, user_answer)
            VALUES (?, ?, ?)
        ");
        
        foreach ($answers as $questionId => $userAnswer) {
            $answerStmt->execute([$resultId, $questionId, $userAnswer]);
        }
        
        $pdo->commit();
        
        // แสดงผลคะแนน
        $_SESSION['test_message'] = "คุณได้ทำแบบทดสอบหลังเรียน CSS เสร็จสิ้น คะแนนของคุณคือ {$score}/{$totalQuestions}";
        
        // ไปที่หน้าแสดงผลละเอียด
        header("Location: view_test_results.php?language=css&type=post");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['test_message'] = "เกิดข้อผิดพลาดในการบันทึกผลการทดสอบ: " . $e->getMessage();
        header('Location: dashboard_css_detail.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>แบบทดสอบหลังเรียน CSS - DevLab</title>
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
                <h1 class="text-2xl font-bold">แบบทดสอบหลังเรียน CSS</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="dashboard_css_detail.php" class="text-blue-500 hover:underline"> กลับไปยังหลักสูตร CSS</a>
            </div>
        </div>

        <!-- แบบทดสอบ -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="mb-6">
                <h2 class="text-xl font-semibold mb-2">คำชี้แจง</h2>
                <p class="text-gray-600">
                    แบบทดสอบนี้ประกอบด้วยข้อสอบ <?php echo count($questions); ?> ข้อ เพื่อประเมินความรู้ด้าน CSS
                    ที่คุณได้เรียนรู้จากหลักสูตรนี้ คุณมีเวลาไม่จำกัดในการทำแบบทดสอบ
                </p>
                <p class="text-gray-600 mt-2">
                    <strong>หมายเหตุ:</strong> คุณสามารถทำแบบทดสอบนี้ได้เพียงครั้งเดียวเท่านั้น
                </p>
            </div>

            <form method="POST" id="testForm">
                <?php $questionNumber = 1; ?>
                <?php foreach ($questions as $question): ?>
                <div class="question-card bg-gray-50 p-4 rounded-lg mb-6">
                    <h3 class="font-semibold mb-3">
                        ข้อ <?php echo $questionNumber; ?>: <?php echo htmlspecialchars($question['question']); ?>
                    </h3>
                    <div class="ml-6 space-y-2">
                        <div class="flex items-center">
                            <input type="radio" id="q<?php echo $question['id']; ?>_a"
                                name="answers[<?php echo $question['id']; ?>]" value="a" required class="mr-2">
                            <label for="q<?php echo $question['id']; ?>_a">ก.
                                <?php echo htmlspecialchars($question['option_a']); ?></label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="q<?php echo $question['id']; ?>_b"
                                name="answers[<?php echo $question['id']; ?>]" value="b" required class="mr-2">
                            <label for="q<?php echo $question['id']; ?>_b">ข.
                                <?php echo htmlspecialchars($question['option_b']); ?></label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="q<?php echo $question['id']; ?>_c"
                                name="answers[<?php echo $question['id']; ?>]" value="c" required class="mr-2">
                            <label for="q<?php echo $question['id']; ?>_c">ค.
                                <?php echo htmlspecialchars($question['option_c']); ?></label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="q<?php echo $question['id']; ?>_d"
                                name="answers[<?php echo $question['id']; ?>]" value="d" required class="mr-2">
                            <label for="q<?php echo $question['id']; ?>_d">ง.
                                <?php echo htmlspecialchars($question['option_d']); ?></label>
                        </div>
                    </div>
                </div>
                <?php $questionNumber++; ?>
                <?php endforeach; ?>

                <div class="flex items-center justify-between mt-8">
                    <a href="dashboard_css_detail.php"
                        class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        ยกเลิก
                    </a>
                    <button type="submit"
                        class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                        ส่งคำตอบ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // เพิ่ม JavaScript เพื่อตรวจสอบว่าผู้ใช้ตอบครบทุกข้อก่อนส่ง
    document.getElementById('testForm').addEventListener('submit', function(e) {
        const questions = <?php echo count($questions); ?>;
        const answered = document.querySelectorAll('input[type="radio"]:checked').length;

        if (answered < questions) {
            e.preventDefault();
            alert('กรุณาตอบคำถามให้ครบทุกข้อ');
        } else {
            if (!confirm('คุณแน่ใจหรือไม่ว่าต้องการส่งคำตอบ? หลังจากส่งแล้วคุณจะไม่สามารถแก้ไขคำตอบได้')) {
                e.preventDefault();
            }
        }
    });
    </script>
</body>

</html>