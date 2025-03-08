<?php
// view_submissions.php - Page for teachers to view and grade submissions for a worksheet
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

// Get worksheet ID from URL
$worksheetId = isset($_GET['worksheet_id']) ? (int)$_GET['worksheet_id'] : 0;

// Verify that the worksheet exists and belongs to this teacher
$worksheetStmt = $pdo->prepare("
    SELECT * FROM worksheets
    WHERE id = ? AND teacher_id = ?
");
$worksheetStmt->execute([$worksheetId, $teacher['id']]);
$worksheet = $worksheetStmt->fetch();

if (!$worksheet) {
    $_SESSION['error_message'] = "ไม่พบโจทย์งานหรือคุณไม่มีสิทธิ์เข้าถึงโจทย์นี้";
    header('Location: teacher_dashboard.php');
    exit();
}

// Handle grading form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['grade_submission'])) {
    $submissionId = (int)$_POST['submission_id'];
    $score = (int)$_POST['score'];
    $feedback = trim($_POST['feedback']);
    
    // Validate score (0-10)
    if ($score < 0 || $score > 10) {
        $error = "คะแนนต้องอยู่ระหว่าง 0-10";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE worksheet_submissions
                SET status = 'graded', score = ?, feedback = ?, graded_at = NOW()
                WHERE id = ? AND worksheet_id = ?
            ");
            $stmt->execute([$score, $feedback, $submissionId, $worksheetId]);
            
            $_SESSION['success_message'] = "ให้คะแนนงานเรียบร้อยแล้ว";
            header('Location: view_submissions.php?worksheet_id=' . $worksheetId);
            exit();
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        }
    }
}

// Get all submissions for this worksheet
$submissionsStmt = $pdo->prepare("
    SELECT ws.*, 
           u.username as student_name,
           u.email as student_email
    FROM worksheet_submissions ws
    JOIN users u ON ws.user_id = u.id
    WHERE ws.worksheet_id = ?
    ORDER BY ws.status ASC, ws.submitted_at DESC
");
$submissionsStmt->execute([$worksheetId]);
$submissions = $submissionsStmt->fetchAll();

// Get submission counts
$countStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) as graded
    FROM worksheet_submissions
    WHERE worksheet_id = ?
");
$countStmt->execute([$worksheetId]);
$counts = $countStmt->fetch();
?>
<!DOCTYPE html>
<html>

<head>
    <title>การส่งงานทั้งหมด - <?php echo htmlspecialchars($worksheet['title']); ?> - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
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

    .submission-row {
        transition: all 0.2s ease;
    }

    .submission-row:hover {
        background-color: #f9fafb;
    }

    /* Styling for modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        overflow: auto;
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 1.5rem;
        border-radius: 0.5rem;
        width: 90%;
        max-width: 800px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        max-height: 85vh;
        overflow-y: auto;
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
                    <h1 class="text-2xl font-bold">การส่งงานทั้งหมด</h1>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($worksheet['title']); ?> -
                        <?php echo strtoupper($worksheet['language']); ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="teacher_dashboard.php" class="text-blue-500 hover:underline"> กลับไปยังหน้าอาจารย์</a>
                <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
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
                <h3 class="text-gray-500 text-sm">จำนวนการส่งงานทั้งหมด</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($counts['total']); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-yellow-500">
                <h3 class="text-gray-500 text-sm">รอการตรวจ</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($counts['pending']); ?></p>
            </div>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-green-500">
                <h3 class="text-gray-500 text-sm">ตรวจแล้ว</h3>
                <p class="text-3xl font-bold text-gray-800"><?php echo number_format($counts['graded']); ?></p>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if (count($submissions) > 0): ?>
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            นักเรียน</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ
                        </th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            เวลาส่ง</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">คะแนน
                        </th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($submissions as $submission): ?>
                    <tr class="submission-row">
                        <td class="py-4 px-4">
                            <div class="flex flex-col">
                                <span
                                    class="font-medium"><?php echo htmlspecialchars($submission['student_name']); ?></span>
                                <span
                                    class="text-sm text-gray-500"><?php echo htmlspecialchars($submission['student_email']); ?></span>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <?php if ($submission['status'] === 'graded'): ?>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                ตรวจแล้ว
                            </span>
                            <?php else: ?>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                รอการตรวจ
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-4 text-sm text-gray-500">
                            <?php echo date('d/m/Y H:i', strtotime($submission['submitted_at'])); ?>
                        </td>
                        <td class="py-4 px-4">
                            <?php if ($submission['status'] === 'graded'): ?>
                            <span class="font-medium">
                                <?php echo $submission['score']; ?>/10
                            </span>
                            <?php else: ?>
                            <span class="text-gray-500">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-4">
                            <button onclick="viewSubmission(<?php echo $submission['id']; ?>)"
                                class="text-blue-500 hover:text-blue-700 mr-3">
                                ดูงาน
                            </button>

                            <?php if ($submission['status'] !== 'graded'): ?>
                            <button
                                onclick="gradeSubmission(<?php echo $submission['id']; ?>, '<?php echo addslashes($submission['student_name']); ?>')"
                                class="text-green-500 hover:text-green-700">
                                ให้คะแนน
                            </button>
                            <?php else: ?>
                            <button
                                onclick="editGrade(<?php echo $submission['id']; ?>, <?php echo $submission['score']; ?>, '<?php echo addslashes($submission['feedback'] ?? ''); ?>', '<?php echo addslashes($submission['student_name']); ?>')"
                                class="text-yellow-500 hover:text-yellow-700">
                                แก้ไขคะแนน
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-8 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3 class="text-xl font-semibold mb-2">ยังไม่มีการส่งงาน</h3>
                <p class="text-gray-600 mb-4">ยังไม่มีนักเรียนส่งงานสำหรับโจทย์นี้</p>
                <a href="teacher_dashboard.php"
                    class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 inline-block">
                    กลับไปยังหน้าอาจารย์
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal for viewing submission -->
    <div id="viewSubmissionModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">ดูงานที่ส่ง</h2>
                <button onclick="closeModal('viewSubmissionModal')" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="submissionContent">
                <!-- Submission content will be loaded here via AJAX -->
                <div class="p-8 text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto"></div>
                    <p class="mt-4">กำลังโหลดข้อมูล...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for grading submission -->
    <div id="gradeSubmissionModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">ให้คะแนนงาน</h2>
                <button onclick="closeModal('gradeSubmissionModal')" class="text-gray-500 hover:text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="gradeForm" method="POST" action="">
                <input type="hidden" id="submissionId" name="submission_id" value="">

                <div class="mb-4">
                    <p>กำลังให้คะแนนงานของ: <span id="studentName" class="font-medium"></span></p>
                </div>

                <div class="mb-4">
                    <label for="score" class="block text-gray-700 font-medium mb-2">คะแนน (0-10)</label>
                    <input type="number" id="score" name="score" min="0" max="10" value="10"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-6">
                    <label for="feedback" class="block text-gray-700 font-medium mb-2">ข้อเสนอแนะ</label>
                    <textarea id="feedback" name="feedback" rows="4"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex justify-end">
                    <button type="button" onclick="closeModal('gradeSubmissionModal')"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 mr-2 hover:bg-gray-100">
                        ยกเลิก
                    </button>
                    <button type="submit" name="grade_submission"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        บันทึกคะแนน
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Function to view submission
    function viewSubmission(submissionId) {
        const modal = document.getElementById('viewSubmissionModal');
        const contentContainer = document.getElementById('submissionContent');

        // Show modal
        modal.style.display = 'block';

        // Load submission content via AJAX
        fetch('get_submission.php?id=' + submissionId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let content = '<div class="bg-gray-100 p-4 rounded mb-4">';
                    content += '<p><strong>นักเรียน:</strong> ' + data.student_name + '</p>';
                    content += '<p><strong>วันที่ส่ง:</strong> ' + data.submitted_at + '</p>';

                    if (data.status === 'graded') {
                        content += '<p><strong>คะแนน:</strong> ' + data.score + '/10</p>';
                        content += '<p><strong>ตรวจเมื่อ:</strong> ' + data.graded_at + '</p>';

                        if (data.feedback) {
                            content += '<p><strong>ข้อเสนอแนะ:</strong> ' + data.feedback + '</p>';
                        }
                    } else {
                        content += '<p><strong>สถานะ:</strong> รอการตรวจ</p>';
                    }

                    content += '</div>';

                    // Add code display
                    content += '<div class="mb-4">';
                    content += '<h3 class="font-medium mb-2">โค้ดที่ส่ง:</h3>';
                    content += '<pre class="bg-gray-100 p-4 rounded font-mono text-sm overflow-x-auto">' + data
                        .code + '</pre>';
                    content += '</div>';

                    contentContainer.innerHTML = content;
                } else {
                    contentContainer.innerHTML = '<p class="text-red-500">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>';
                }
            })
            .catch(error => {
                contentContainer.innerHTML = '<p class="text-red-500">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>';
                console.error('Error:', error);
            });
    }

    // Function to prepare grading form
    function gradeSubmission(submissionId, studentName) {
        const modal = document.getElementById('gradeSubmissionModal');
        document.getElementById('submissionId').value = submissionId;
        document.getElementById('studentName').textContent = studentName;
        document.getElementById('score').value = "10"; // Default value
        document.getElementById('feedback').value = "";

        // Show modal
        modal.style.display = 'block';
    }

    // Function to edit existing grade
    function editGrade(submissionId, score, feedback, studentName) {
        const modal = document.getElementById('gradeSubmissionModal');
        document.getElementById('submissionId').value = submissionId;
        document.getElementById('studentName').textContent = studentName;
        document.getElementById('score').value = score;
        document.getElementById('feedback').value = feedback;

        // Show modal
        modal.style.display = 'block';
    }

    // Function to close modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
    </script>
</body>

</html>