<?php
// do_worksheet.php - Page for students to work on worksheets
require_once 'config.php';
checkLogin();

// Get worksheet ID from URL
$worksheetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get worksheet details
$worksheetStmt = $pdo->prepare("
    SELECT w.*, t.id as teacher_id, u.username as teacher_name 
    FROM worksheets w
    JOIN teachers t ON w.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE w.id = ?
");
$worksheetStmt->execute([$worksheetId]);
$worksheet = $worksheetStmt->fetch();

if (!$worksheet) {
    $_SESSION['error_message'] = "ไม่พบโจทย์งานที่ระบุ";
    header('Location: worksheets.php');
    exit();
}

// Check if the student has already submitted this worksheet
$submissionCheckStmt = $pdo->prepare("
    SELECT id, code, status, score, feedback 
    FROM worksheet_submissions 
    WHERE worksheet_id = ? AND user_id = ?
");
$submissionCheckStmt->execute([$worksheetId, $_SESSION['user_id']]);
$existingSubmission = $submissionCheckStmt->fetch();

// Handle form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_code'])) {
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        $errorMessage = "กรุณาเขียนโค้ดก่อนส่งงาน";
    } else {
        try {
            if ($existingSubmission) {
                // Update existing submission
                $stmt = $pdo->prepare("
                    UPDATE worksheet_submissions 
                    SET code = ?, status = 'submitted', submitted_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$code, $existingSubmission['id']]);
                $successMessage = "อัปเดตงานเรียบร้อยแล้ว";
            } else {
                // Create new submission
                $stmt = $pdo->prepare("
                    INSERT INTO worksheet_submissions (worksheet_id, user_id, code, status, submitted_at)
                    VALUES (?, ?, ?, 'submitted', NOW())
                ");
                $stmt->execute([$worksheetId, $_SESSION['user_id'], $code]);
                $successMessage = "ส่งงานเรียบร้อยแล้ว";
            }
            
            // Redirect to view the submission
            if (!empty($successMessage)) {
                // Get the submission ID
                $getSubmissionStmt = $pdo->prepare("
                    SELECT id FROM worksheet_submissions 
                    WHERE worksheet_id = ? AND user_id = ?
                ");
                $getSubmissionStmt->execute([$worksheetId, $_SESSION['user_id']]);
                $submissionId = $getSubmissionStmt->fetchColumn();
                
                $_SESSION['success_message'] = $successMessage;
                header("Location: view_worksheet_submission.php?id=$submissionId");
                exit();
            }
        } catch (PDOException $e) {
            $errorMessage = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
        }
    }
}

// Load initial code (either from existing submission or starter code)
$initialCode = $existingSubmission ? $existingSubmission['code'] : $worksheet['starter_code'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>ทำโจทย์ - <?php echo htmlspecialchars($worksheet['title']); ?> - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ext-language_tools.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        /* Font settings */
        :root {
            --font-thai: 'IBM Plex Sans Thai', 'Noto Sans Thai', sans-serif;
            --font-english: 'IBM Plex Sans', 'Noto Sans', sans-serif;
        }
        body {
            font-family: var(--font-thai);
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }
        .main-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .header {
            position: sticky;
            top: 0;
            z-index: 50;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .content-wrapper {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        .sidebar {
            width: 35%;
            overflow-y: auto;
            background-color: white;
            padding: 1.5rem;
        }
        .editor-section {
            width: 35%;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
        }
        .preview-section {
            width: 30%;
        }
        /* Markdown content styles */
        .worksheet-description h1 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
        }
        .worksheet-description h2 {
            font-size: 1.25rem;
            font-weight: bold;
            margin-top: 1.25rem;
            margin-bottom: 0.5rem;
        }
        .worksheet-description h3 {
            font-size: 1.125rem;
            font-weight: bold;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
        }
        .worksheet-description p {
            margin-bottom: 1rem;
        }
        .worksheet-description pre {
            background-color: #f7fafc;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            overflow-x: auto;
        }
        .worksheet-description code {
            font-family: monospace;
            background-color: #f7fafc;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
        }
        .worksheet-description ul,
        .worksheet-description ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .worksheet-description li {
            margin-bottom: 0.25rem;
        }
        h3 {
            font-size: 18px;
            font-weight: 700;
        }
        .code-example {
            background-color: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            margin: 10px 0;
            line-height: 1.5;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="main-container">
        <!-- Header -->
        <header class="header">
            <div class="flex justify-between items-center p-5">
                <div class="flex items-center">
                    <a href="dashboard.php">
                        <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($worksheet['title']); ?></h1>
                        <p class="text-sm text-gray-600">
                            ภาษา: <span class="font-medium"><?php echo strtoupper($worksheet['language']); ?></span> | 
                            อาจารย์: <span class="font-medium"><?php echo htmlspecialchars($worksheet['teacher_name']); ?></span>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <a href="worksheets.php" class="text-blue-500 hover:text-blue-700">กลับไปยังรายการโจทย์</a>
                </div>
            </div>
        </header>

        <!-- Error Message -->
        <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Warning!</strong>
            <span class="block sm:inline"><?php echo $errorMessage; ?></span>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="content-wrapper">
            <!-- Left Sidebar -->
            <div class="sidebar">
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2 mb-4">รายละเอียดโจทย์</h2>
                
                <?php if ($worksheet['due_date']): ?>
                <div class="mb-4 p-4 bg-yellow-50 rounded-lg">
                    <p class="font-medium">⏰ กำหนดส่ง:</p>
                    <p><?php echo date('d/m/Y H:i', strtotime($worksheet['due_date'])); ?></p>
                    <?php 
                    $now = new DateTime();
                    $dueDate = new DateTime($worksheet['due_date']);
                    $diff = $now->diff($dueDate);
                    
                    if ($dueDate < $now) {
                        echo '<p class="text-red-600 font-medium">เลยกำหนดส่งแล้ว</p>';
                    } else {
                        if ($diff->days > 0) {
                            echo "<p>เหลือเวลาอีก {$diff->days} วัน</p>";
                        } else {
                            $hours = $diff->h;
                            $minutes = $diff->i;
                            echo "<p>เหลือเวลาอีก {$hours} ชั่วโมง {$minutes} นาที</p>";
                        }
                    }
                    ?>
                </div>
                <?php endif; ?>
                
                <div class="worksheet-description" id="description">
                    <!-- Markdown content will be rendered here -->
                </div>
                
                <?php if ($existingSubmission && $existingSubmission['status'] === 'graded'): ?>
                <div class="mt-6 p-4 bg-green-50 rounded-lg">
                    <h3 class="font-bold text-lg mb-2">ผลการตรวจ</h3>
                    <p class="text-2xl font-bold mb-2"><?php echo $existingSubmission['score']; ?>/10</p>
                    <?php if (!empty($existingSubmission['feedback'])): ?>
                    <div class="mt-2">
                        <p class="font-medium">ข้อเสนอแนะจากอาจารย์:</p>
                        <p class="italic"><?php echo nl2br(htmlspecialchars($existingSubmission['feedback'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="mt-6">
                    <form id="codeForm" method="POST" action="">
                        <input type="hidden" name="code" id="hiddenCode">
                        <div class="flex justify-center space-x-4">
                            <button type="button" id="resetCodeBtn" class="bg-red-500 text-white py-1.5 px-3 rounded-md hover:bg-red-600">
                                รีเซ็ตโค้ด
                            </button>
                            <button type="submit" name="submit_code" id="submitBtn" class="bg-blue-500 text-white py-1.5 px-3 rounded-md hover:bg-blue-600">
                                ส่งงาน
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Code Editor -->
            <div class="editor-section">
                <div id="editor" class="h-full"></div>
            </div>

            <!-- Output or Preview Section -->
            <?php if ($worksheet['language'] === 'php'): ?>
            <!-- PHP Output Preview -->
            <div class="preview-section">
                <div class="bg-gray-800 text-white p-2">Output Preview</div>
                <div id="output" class="w-full h-full p-4 font-mono text-sm overflow-auto"></div>
            </div>
            <?php else: ?>
            <!-- HTML/CSS Preview -->
            <div class="preview-section">
                <iframe id="preview" class="w-full h-full"></iframe>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Editor setup
        const editor = ace.edit("editor");
        editor.setTheme("ace/theme/monokai");
        
        // Set editor mode based on language
        <?php if ($worksheet['language'] === 'html'): ?>
        editor.session.setMode("ace/mode/html");
        <?php elseif ($worksheet['language'] === 'css'): ?>
        editor.session.setMode("ace/mode/html");
        <?php elseif ($worksheet['language'] === 'php'): ?>
        editor.session.setMode("ace/mode/php");
        <?php endif; ?>
        
        editor.setOptions({
            enableBasicAutocompletion: true,
            enableSnippets: true,
            enableLiveAutocompletion: true
        });
        
        // Set initial code
        const initialCode = `<?php echo str_replace('`', '\`', str_replace('\\', '\\\\', str_replace("\n", "\\n", str_replace("'", "\\'", str_replace('"', '\\"', $initialCode))))) ?>`;
        editor.setValue(initialCode);
        editor.clearSelection();
        
        // Render markdown description
        document.addEventListener('DOMContentLoaded', function() {
            const descriptionElement = document.getElementById('description');
            const markdownContent = `<?php echo str_replace('`', '\`', str_replace('\\', '\\\\', str_replace("\n", "\\n", str_replace("'", "\\'", str_replace('"', '\\"', $worksheet['description']))))) ?>`;
            descriptionElement.innerHTML = marked.parse(markdownContent);
            
            // Initial preview update
            updatePreview();
        });
        
        // Handle code changes and preview
        editor.on('change', function() {
            updatePreview();
            
            // Update hidden form field with current code
            document.getElementById('hiddenCode').value = editor.getValue();
        });
        
        // Reset code button functionality
        document.getElementById('resetCodeBtn').addEventListener('click', function() {
            if (confirm('คุณแน่ใจหรือไม่ว่าต้องการรีเซ็ตโค้ด? การกระทำนี้ไม่สามารถเรียกคืนได้')) {
                editor.setValue(initialCode);
                editor.clearSelection();
                updatePreview();
            }
        });
        
        // Form submission
        document.getElementById('codeForm').addEventListener('submit', function(e) {
            // Update hidden code field one last time before submission
            document.getElementById('hiddenCode').value = editor.getValue();
            
            // Confirmation dialog
            if (!confirm('คุณแน่ใจหรือไม่ว่าต้องการส่งงานนี้?')) {
                e.preventDefault();
            }
        });
        
        // Function to update preview based on language
        function updatePreview() {
            <?php if ($worksheet['language'] === 'php'): ?>
            // PHP - Update output preview
            const code = editor.getValue();
            fetch('run_php.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    code: code
                })
            })
            .then(response => response.json())
            .then(data => {
                const outputDiv = document.getElementById('output');
                if (data.error) {
                    outputDiv.innerHTML = `<span class="text-red-500">Error: ${data.error}</span>`;
                } else {
                    // Create styled container for output
                    outputDiv.innerHTML = `<div class="p-2">${data.output || 'ไม่มีผลลัพธ์'}</div>`;
                }
            })
            .catch(error => {
                const outputDiv = document.getElementById('output');
                outputDiv.innerHTML = `<span class="text-red-500">Error connecting to server: ${error.message}</span>`;
            });
            <?php else: ?>
            // HTML/CSS - Update iframe preview
            const code = editor.getValue();
            const preview = document.getElementById('preview').contentWindow.document;
            preview.open();
            preview.write(code);
            preview.close();
            
            // Add base styles to the preview iframe
            const style = preview.createElement('style');
            style.textContent = `
                body { 
                    margin: 1rem;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                h1 { font-size: 2em; margin: 0.67em 0; font-weight: bold; }
                h2 { font-size: 1.5em; margin: 0.75em 0; font-weight: bold; }
                h3 { font-size: 1.17em; margin: 0.83em 0; font-weight: bold; }
                h4 { margin: 1.12em 0; font-weight: bold; }
                h5 { font-size: 0.83em; margin: 1.5em 0; font-weight: bold; }
                h6 { font-size: 0.75em; margin: 1.67em 0; font-weight: bold; }
                p { margin: 1em 0; }
            `;
            preview.head.appendChild(style);
            <?php endif; ?>
        }
    </script>
</body>
</html>