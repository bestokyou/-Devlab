<?php
// view_worksheet_submission.php - Page for students to view their worksheet submissions
require_once 'config.php';
checkLogin();

// Get submission ID from URL
$submissionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get submission details
$submissionStmt = $pdo->prepare("
    SELECT ws.*,
           w.title as worksheet_title,
           w.language,
           w.description,
           u.username as teacher_name
    FROM worksheet_submissions ws
    JOIN worksheets w ON ws.worksheet_id = w.id
    JOIN teachers t ON w.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE ws.id = ? AND ws.user_id = ?
");
$submissionStmt->execute([$submissionId, $_SESSION['user_id']]);
$submission = $submissionStmt->fetch();

if (!$submission) {
    $_SESSION['error_message'] = "ไม่พบข้อมูลการส่งงานหรือคุณไม่มีสิทธิ์เข้าถึงข้อมูลนี้";
    header('Location: worksheets.php');
    exit();
}

// Format submission times
$submittedAt = new DateTime($submission['submitted_at']);
$formattedSubmittedAt = $submittedAt->format('d/m/Y H:i');

$formattedGradedAt = '';
if ($submission['graded_at']) {
    $gradedAt = new DateTime($submission['graded_at']);
    $formattedGradedAt = $gradedAt->format('d/m/Y H:i');
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>งานที่ส่ง - <?php echo htmlspecialchars($submission['worksheet_title']); ?> - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
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

    /* Code highlighting */
    .code-display {
        background-color: #1a202c;
        border-radius: 0.5rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        color: #f7fafc;
        overflow-x: auto;
        font-family: monospace;
        line-height: 1.6;
    }

    /* Tab styles */
    .tab-container {
        display: flex;
        border-bottom: 1px solid #e2e8f0;
    }

    .tab {
        padding: 0.5rem 1rem;
        cursor: pointer;
    }

    .tab.active {
        border-bottom: 2px solid #4299e1;
        color: #4299e1;
        font-weight: bold;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .output-container {
        background-color: #f7fafc;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-top: 1rem;
        font-family: monospace;
        min-height: 100px;
        max-height: 400px;
        overflow-y: auto;
    }

    .output-header {
        background-color: #2d3748;
        color: white;
        padding: 0.5rem 1rem;
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
        font-weight: bold;
    }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="bg-white shadow rounded-lg mb-8">
            <div class="container mx-auto px-6 py-4">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="flex items-center">
                            <a href="dashboard.php">
                                <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                            </a>
                            <h1 class="text-2xl font-bold">
                                <?php echo htmlspecialchars($submission['worksheet_title']); ?></h1>
                        </div>
                        <p class="text-sm text-gray-600">
                            ภาษา: <span class="font-medium"><?php echo strtoupper($submission['language']); ?></span> |
                            อาจารย์: <span
                                class="font-medium"><?php echo htmlspecialchars($submission['teacher_name']); ?></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <a href="worksheets.php" class="text-blue-500 hover:underline"> กลับไปยังรายการโจทย์</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Worksheet Info -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold mb-4">รายละเอียดการส่งงาน</h2>

                    <div class="space-y-4">
                        <div>
                            <p class="text-gray-600 text-sm">สถานะ:</p>
                            <div class="mt-1">
                                <?php if ($submission['status'] === 'graded'): ?>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    <svg class="mr-1.5 h-2 w-2 text-green-500" fill="currentColor" viewBox="0 0 8 8">
                                        <circle cx="4" cy="4" r="3" />
                                    </svg>
                                    ตรวจแล้ว
                                </span>
                                <?php else: ?>
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    <svg class="mr-1.5 h-2 w-2 text-yellow-500" fill="currentColor" viewBox="0 0 8 8">
                                        <circle cx="4" cy="4" r="3" />
                                    </svg>
                                    รอการตรวจ
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <p class="text-gray-600 text-sm">เวลาที่ส่ง:</p>
                            <p class="font-medium"><?php echo $formattedSubmittedAt; ?></p>
                        </div>

                        <?php if ($submission['status'] === 'graded'): ?>
                        <div>
                            <p class="text-gray-600 text-sm">คะแนน:</p>
                            <p class="font-bold text-xl"><?php echo $submission['score']; ?>/10</p>
                        </div>

                        <div>
                            <p class="text-gray-600 text-sm">ตรวจเมื่อ:</p>
                            <p class="font-medium"><?php echo $formattedGradedAt; ?></p>
                        </div>

                        <?php if (!empty($submission['feedback'])): ?>
                        <div>
                            <p class="text-gray-600 text-sm">ข้อเสนอแนะจากอาจารย์:</p>
                            <div class="mt-2 p-3 bg-yellow-50 rounded-md text-gray-800">
                                <?php echo nl2br(htmlspecialchars($submission['feedback'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">โจทย์</h2>
                    <div class="worksheet-description" id="description">
                        <!-- Markdown content will be rendered here -->
                    </div>
                </div>
            </div>

            <!-- Right Column - Code Display -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">โค้ดที่คุณส่ง</h2>

                    <div class="mb-4 flex justify-between items-center">
                        <div
                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                            <?php echo strtoupper($submission['language']); ?>
                        </div>

                        <button onclick="copyCode()" class="text-blue-500 hover:text-blue-700 text-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            คัดลอกโค้ด
                        </button>
                    </div>

                    <div class="code-display" id="codeDisplay">
                        <?php echo htmlspecialchars($submission['code']); ?>
                    </div>

                    <!-- Tabs for output/preview -->
                    <div class="tab-container mt-4">
                        <div class="tab active" data-tab="output">ผลลัพธ์การทำงาน</div>
                        <?php if (in_array($submission['language'], ['html', 'css'])): ?>
<div class="tab" data-tab="preview">แสดงผลตัวอย่าง</div>
<?php endif; ?>
                    </div>

                    <!-- Output Tab -->
                    <div id="output-tab" class="tab-content active">
                        <div class="output-header">Output</div>
                        <div id="codeOutput" class="output-container">
                            <div class="flex justify-center items-center h-full text-gray-500">
                                <p>กำลังโหลดผลลัพธ์...</p>
                            </div>
                        </div>
                    </div>

                    <!-- HTML Preview Tab (if applicable) -->
                    <?php if (in_array($submission['language'], ['html', 'css'])): ?>
<div id="preview-tab" class="tab-content">
    <div class="output-header">ตัวอย่างผลลัพธ์</div>
    <iframe id="htmlPreview" class="w-full border-0 min-h-[400px]"></iframe>
</div>
<?php endif; ?>

                    <a href="do_worksheet.php?id=<?php echo $submission['worksheet_id']; ?>"
                        class="<?php echo $submission['status'] === 'graded' ? 'block' : 'hidden'; ?> mt-4 bg-blue-500 text-white py-2 rounded text-center hover:bg-blue-600 transition-colors">
                        ทำโจทย์อีกครั้ง
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Render markdown description
    document.addEventListener('DOMContentLoaded', function() {
    const descriptionElement = document.getElementById('description');
    const markdownContent =
        `<?php echo str_replace('"', '\\"', str_replace("\n", "\\n", addslashes($submission['description']))); ?>`;
    descriptionElement.innerHTML = marked.parse(markdownContent);

    // Initialize tabs
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + '-tab').classList.add('active');
        });
    });

    // Load code output
    loadCodeOutput();

    // Set up HTML preview if applicable
    // Within the DOMContentLoaded event listener
<?php if (in_array($submission['language'], ['html', 'css'])): ?>
    loadHtmlPreview();
<?php endif; ?>
});

    // Function to copy code to clipboard
    function copyCode() {
        const codeText =
            `<?php echo str_replace('"', '\\"', str_replace("\n", "\\n", addslashes($submission['code']))); ?>`;
        navigator.clipboard.writeText(codeText).then(() => {
            alert("คัดลอกโค้ดไปยังคลิปบอร์ดแล้ว");
        }).catch(err => {
            console.error('Error copying text: ', err);
        });
    }

    // Function to load code execution output
    function loadCodeOutput() {
        const code =
        `<?php echo str_replace('"', '\\"', str_replace("\n", "\\n", addslashes($submission['code']))); ?>`;
        const outputElement = document.getElementById('codeOutput');

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
                if (data.error) {
                    outputElement.innerHTML = `<div class="text-red-500 p-2">Error: ${data.error}</div>`;
                } else {
                    outputElement.innerHTML = `<pre class="p-2">${data.output || 'ไม่มีผลลัพธ์'}</pre>`;
                }
            })
            .catch(error => {
                outputElement.innerHTML =
                    `<div class="text-red-500 p-2">Error connecting to server: ${error.message}</div>`;
            });
    }

    <?php if (in_array($submission['language'], ['html', 'css'])): ?>
// Function to load HTML or CSS preview
function loadHtmlPreview() {
    // Get the escaped HTML from code-display
    const escapedCode = document.getElementById('codeDisplay').innerHTML;
    // Create a temporary element to decode the HTML entities
    const tempElement = document.createElement('textarea');
    tempElement.innerHTML = escapedCode;
    const unescapedCode = tempElement.value;
    
    const iframe = document.getElementById('htmlPreview');
    const preview = iframe.contentDocument || iframe.contentWindow.document;
    preview.open();
    preview.write(unescapedCode);
    preview.close();
}
<?php endif; ?>
    </script>
</body>

</html>
