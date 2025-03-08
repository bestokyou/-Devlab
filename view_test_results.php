<?php
require_once 'config.php';
checkLogin();

// ตรวจสอบพารามิเตอร์
$language = isset($_GET['language']) ? $_GET['language'] : '';
$testType = isset($_GET['type']) ? $_GET['type'] : '';

// ตรวจสอบว่าพารามิเตอร์ถูกต้อง
if (!in_array($language, ['html', 'css', 'php']) || !in_array($testType, ['pre', 'post'])) {
    $_SESSION['test_message'] = "ข้อมูลไม่ถูกต้อง";
    header('Location: dashboard.php');
    exit();
}

// ตรวจสอบว่าผู้ใช้เคยทำแบบทดสอบนี้หรือไม่
$testResultStmt = $pdo->prepare("
    SELECT id, score, total_questions, completed_at
    FROM test_results 
    WHERE user_id = ? AND language = ? AND test_type = ?
");
$testResultStmt->execute([$_SESSION['user_id'], $language, $testType]);
$testResult = $testResultStmt->fetch();

if (!$testResult) {
    $_SESSION['test_message'] = "ยังไม่มีผลการทดสอบ {$testType} สำหรับภาษา {$language}";
    header("Location: dashboard_{$language}_detail.php");
    exit();
}

// ดึงข้อมูลคำถามและคำตอบ
$questionsStmt = $pdo->prepare("
    SELECT * FROM pre_post_tests 
    WHERE language = ? AND type = ?
    ORDER BY id
");
$questionsStmt->execute([$language, $testType]);
$questions = $questionsStmt->fetchAll();

// ดึงคำตอบของผู้ใช้
$answersStmt = $pdo->prepare("
    SELECT question_id, user_answer
    FROM test_answers
    WHERE result_id = ?
");
$answersStmt->execute([$testResult['id']]);
$userAnswers = [];

// สร้าง array คำตอบของผู้ใช้
while ($row = $answersStmt->fetch()) {
    $userAnswers[$row['question_id']] = $row['user_answer'];
}

// คำนวณจำนวนข้อที่ถูกและผิด
$correctCount = 0;
$wrongCount = 0;

foreach ($questions as $question) {
    if (isset($userAnswers[$question['id']]) && $userAnswers[$question['id']] === $question['correct_answer']) {
        $correctCount++;
    } else {
        $wrongCount++;
    }
}

// แปลงประเภทแบบทดสอบเป็นภาษาไทย
$testTypeText = $testType === 'pre' ? 'ก่อนเรียน' : 'หลังเรียน';

// แปลงภาษาเป็นภาษาไทย
$languageText = '';
switch ($language) {
    case 'html': $languageText = 'HTML'; break;
    case 'css': $languageText = 'CSS'; break;
    case 'php': $languageText = 'PHP'; break;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ผลการทดสอบ <?php echo $testTypeText; ?> <?php echo $languageText; ?> - DevLab</title>
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
        .font-english {
            font-family: var(--font-english);
        }
        /* Combined font stack for mixed content */
        .mixed-text {
            font-family: var(--font-english), var(--font-thai);
        }
        .question-card {
            transition: all 0.3s ease;
        }
        .question-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        /* สไตล์สำหรับวงกลมตัวเลือก */
        .option-circle {
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        .option-correct {
            background-color: #10b981;
            color: white;
        }
        .option-wrong {
            background-color: #ef4444;
            color: white;
        }
        .option-user-selected {
            background-color: #6366f1;
            color: white;
        }
        /* สไตล์สำหรับการ์ดคำถาม */
        .correct-answer {
            border-left: 4px solid #10b981;
        }
        .wrong-answer {
            border-left: 4px solid #ef4444;
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
                <h1 class="text-2xl font-bold">ผลการทดสอบ<?php echo $testTypeText; ?> <?php echo $languageText; ?></h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="dashboard_<?php echo $language; ?>_detail.php" class="text-blue-500 hover:underline"> กลับไปยังหลักสูตร <?php echo $languageText; ?></a>
            </div>
        </div>
        
        <!-- สรุปผลคะแนน -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg text-center">
                    <h3 class="text-lg font-semibold text-blue-700 mb-2">คะแนนรวม</h3>
                    <p class="text-3xl font-bold text-blue-800"><?php echo $testResult['score']; ?> / <?php echo $testResult['total_questions']; ?></p>
                    <p class="text-sm text-blue-600 mt-2">
                        คิดเป็น <?php echo round(($testResult['score'] / $testResult['total_questions']) * 100, 1); ?>%
                    </p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg text-center">
                    <h3 class="text-lg font-semibold text-green-700 mb-2">ตอบถูก</h3>
                    <p class="text-3xl font-bold text-green-800"><?php echo $correctCount; ?> ข้อ</p>
                    <p class="text-sm text-green-600 mt-2">
                        คิดเป็น <?php echo round(($correctCount / $testResult['total_questions']) * 100, 1); ?>%
                    </p>
                </div>
                <div class="bg-red-50 p-4 rounded-lg text-center">
                    <h3 class="text-lg font-semibold text-red-700 mb-2">ตอบผิด</h3>
                    <p class="text-3xl font-bold text-red-800"><?php echo $wrongCount; ?> ข้อ</p>
                    <p class="text-sm text-red-600 mt-2">
                        คิดเป็น <?php echo round(($wrongCount / $testResult['total_questions']) * 100, 1); ?>%
                    </p>
                </div>
            </div>
            
            <div class="text-center text-gray-600 text-sm mb-6">
                <p>ทำแบบทดสอบเมื่อ <?php echo date('d/m/Y H:i', strtotime($testResult['completed_at'])); ?></p>
            </div>
            
            <div class="flex justify-center">
                <a href="#question-details" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors inline-flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                    ดูรายละเอียดคำตอบ
                </a>
            </div>
        </div>
        
        <!-- รายละเอียดแต่ละข้อ -->
        <div id="question-details" class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-2xl font-bold mb-6 text-center">รายละเอียดคำตอบรายข้อ</h2>
            
            <?php foreach ($questions as $index => $question): ?>
                <?php 
                    $userAnswer = $userAnswers[$question['id']] ?? null;
                    $isCorrect = $userAnswer === $question['correct_answer'];
                    $cardClass = $isCorrect ? 'correct-answer' : 'wrong-answer';
                ?>
                <div class="question-card <?php echo $cardClass; ?> bg-gray-50 p-4 rounded-lg mb-6">
                    <div class="flex items-start gap-3">
                        <div class="<?php echo $isCorrect ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?> rounded-full w-8 h-8 flex items-center justify-center flex-shrink-0">
                            <?php echo $isCorrect ? '✓' : '✗'; ?>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold mb-3">
                                ข้อ <?php echo $index + 1; ?>: <?php echo htmlspecialchars($question['question']); ?>
                            </h3>
                            <div class="ml-6 space-y-3">
                                <?php 
                                    $options = [
                                        'a' => $question['option_a'],
                                        'b' => $question['option_b'],
                                        'c' => $question['option_c'],
                                        'd' => $question['option_d']
                                    ];
                                    $optionLabels = [
                                        'a' => 'ก',
                                        'b' => 'ข',
                                        'c' => 'ค',
                                        'd' => 'ง'
                                    ];
                                    
                                    foreach ($options as $key => $option):
                                        $isUserSelected = $userAnswer === $key;
                                        $isCorrectOption = $question['correct_answer'] === $key;
                                        
                                        $circleClass = 'bg-gray-200 text-gray-700';
                                        if ($isUserSelected && $isCorrectOption) {
                                            $circleClass = 'option-correct';
                                        } else if ($isUserSelected && !$isCorrectOption) {
                                            $circleClass = 'option-wrong';
                                        } else if (!$isUserSelected && $isCorrectOption) {
                                            $circleClass = 'option-correct';
                                        }
                                        
                                        $optionClass = '';
                                        if ($isUserSelected && !$isCorrectOption) {
                                            $optionClass = 'line-through text-red-500';
                                        } else if ($isCorrectOption) {
                                            $optionClass = 'font-semibold text-green-700';
                                        }
                                ?>
                                    <div class="flex items-center">
                                        <span class="option-circle <?php echo $circleClass; ?>">
                                            <?php echo $optionLabels[$key]; ?>
                                        </span>
                                        <span class="<?php echo $optionClass; ?>">
                                            <?php echo htmlspecialchars($option); ?>
                                            <?php if ($isUserSelected && !$isCorrectOption): ?>
                                                <span class="text-red-500 ml-2"> คำตอบของคุณ</span>
                                            <?php elseif ($isUserSelected && $isCorrectOption): ?>
                                                <span class="text-green-500 ml-2"> คำตอบของคุณ (ถูกต้อง)</span>
                                            <?php elseif (!$isUserSelected && $isCorrectOption): ?>
                                                <span class="text-green-500 ml-2"> คำตอบที่ถูกต้อง</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="mt-8 text-center">
                <a href="dashboard_<?php echo $language; ?>_detail.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    กลับไปยังหลักสูตร <?php echo $languageText; ?>
                </a>
            </div>
        </div>
    </div>
</body>
</html>