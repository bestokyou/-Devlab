<?php
// php_lesson.php
require_once 'config.php';
require_once 'lesson_data.php';
checkLogin();
$language = 'php';
$lesson_id = (int)($_GET['lesson'] ?? 41); // PHP lessons start from ID 41

// Check if requested lesson exists
if (!checkLessonExists($pdo, $language, $lesson_id)) {
    header('Location: dashboard.php');
    exit();
}

// Get current lesson's order_num
$currentLessonOrderQuery = $pdo->prepare("
    SELECT order_num 
    FROM lessons 
    WHERE language = ? AND id = ?
");
$currentLessonOrderQuery->execute([$language, $lesson_id]);
$currentLessonOrder = $currentLessonOrderQuery->fetchColumn();

// If not the first lesson by order_num, check if previous lesson was completed
if ($currentLessonOrder > 1) {
    // Find the previous lesson by order_num
    $prevLessonQuery = $pdo->prepare("
        SELECT id 
        FROM lessons 
        WHERE language = ? AND order_num < ? 
        ORDER BY order_num DESC 
        LIMIT 1
    ");
    $prevLessonQuery->execute([$language, $currentLessonOrder]);
    $prevLessonId = $prevLessonQuery->fetchColumn();
    
    // Check if the previous lesson is completed
    $checkPrevLesson = $pdo->prepare("
        SELECT completed 
        FROM progress 
        WHERE user_id = ? AND language = ? AND lesson_id = ? AND completed = 1
    ");
    $checkPrevLesson->execute([$_SESSION['user_id'], $language, $prevLessonId]);
    
    if (!$checkPrevLesson->fetch()) {
        $_SESSION['error_message'] = "You need to complete the previous lesson first!";
        header("Location: php_lesson.php?lesson=$prevLessonId");
        exit();
    }
}

// Fetch the current lesson
$lesson = getLesson($pdo, $language, $lesson_id);
if (!$lesson) {
    header('Location: dashboard.php');
    exit();
}

// Find the next lesson by order_num for the "Next Lesson" button
$nextLessonQuery = $pdo->prepare("
    SELECT id 
    FROM lessons 
    WHERE language = ? AND order_num > ? 
    ORDER BY order_num ASC 
    LIMIT 1
");
$nextLessonQuery->execute([$language, $currentLessonOrder]);
$nextLessonId = $nextLessonQuery->fetchColumn() ?: null;
?>
<!DOCTYPE html>
<html>
<!-- Rest of the HTML code remains the same -->
<!DOCTYPE html>
<html>

<head>
    <title>PHP Lesson - <?php echo htmlspecialchars($lesson['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ext-language_tools.js"></script>
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

    .lesson-content h1 {
        font-size: 2em;
        font-weight: bold;
        margin-bottom: 1rem;
        color: #1a202c;
    }

    .lesson-content h2 {
        font-size: 1.5em;
        font-weight: bold;
        margin-top: 1.5rem;
        margin-bottom: 1rem;
        color: #2d3748;
    }

    .lesson-content p {
        margin-bottom: 1rem;
        line-height: 1.7;
        color: #4a5568;
    }

    .lesson-content ul,
    .lesson-content ol {
        margin-left: 1.5rem;
        margin-bottom: 1rem;
    }

    .lesson-content pre {
        background-color: #2d3748;
        color: #fff;
        padding: 1rem;
        border-radius: 0.5rem;
        overflow-x: auto;
        margin-bottom: 1rem;
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

<body>
    <div class="main-container">
        <!-- Header -->
        <header class="header">
            <div class="flex justify-between items-center p-5">
                <div class="flex items-center">
                    <a href="dashboard.php">
                        <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                    </a>
                    <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($lesson['title']); ?></h1>
                </div>
                <div class="flex items-center gap-4">
                    <a href="dashboard_php_detail.php" class="text-blue-500 hover:text-blue-700">กลับไปยังแดชบอร์ด</a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
                </div>
            </div>
        </header>
        <!-- Error Message -->
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Warning!</strong>
            <span class="block sm:inline"><?php echo $_SESSION['error_message']; ?></span>
            <?php unset($_SESSION['error_message']); ?>
        </div>
        <?php endif; ?>
        <!-- Main Content -->
        <div class="content-wrapper">
            <!-- Left Sidebar -->
            <div class="sidebar">
                <h2 class="text-xl font-bold text-gray-800 border-b pb-2">
                    <?php echo htmlspecialchars($lesson['title']); ?></h2>
                <div id="successMessage" class="mt-4 mb-4 p-4 bg-green-100 text-green-700 rounded-lg hidden">
                    <p class="font-medium">🎉 ถูกต้อง!</p>
                    <p class="mt-1 mb-1">เยี่ยมมาก! Code ของคุณสมบูรณ์แบบแล้ว</p>
                </div>
                <div class="lesson-content">
                    <?php echo $lesson['description']; ?>
                </div>
                <div class="mt-4 flex justify-center gap-4">
                    <button id="checkSolutionBtn"
                        class="bg-blue-500 text-white py-1.5 px-3 rounded-md hover:bg-blue-600">
                        Check Solution
                    </button>
                    <button id="resetCodeBtn" class="bg-red-500 text-white py-1.5 px-3 rounded-md hover:bg-red-600">
                        Reset Code
                    </button>
                    <button id="nextLesson" class="bg-gray-300 text-gray-600 py-1.5 px-3 rounded-md cursor-not-allowed"
                        disabled>
                        Next Lesson →
                    </button>
                    <button id="showHintBtn"
                        class="bg-yellow-500 text-white py-1.5 px-3 rounded-md hover:bg-yellow-600">
                        Show Hint
                    </button>
                </div>
                <div id="hintMessage" class="mt-4 p-4 bg-yellow-50 text-yellow-700 rounded-lg hidden text-sm">
                    <p class="font-medium mb-2">💡 Hint:</p>
                    <div id="hintContent" class="text-sm space-y-1"></div>
                </div>
            </div>
            <!-- Code Editor -->
            <div class="editor-section">
                <div id="statusIndicator" class="absolute top-2 right-2 hidden">
                    <span id="correctIcon" class="text-green-500 text-xl">✓</span>
                </div>
                <div id="editor" class="h-full"></div>
            </div>
            <!-- Output Preview -->
            <div class="preview-section">
                <div class="bg-gray-800 text-white p-2">Output Preview</div>
                <div id="output" class="w-full h-full p-4 font-mono text-sm overflow-auto"></div>
            </div>
        </div>
    </div>
    <script>
    const editor = ace.edit("editor");
    editor.setTheme("ace/theme/monokai");
    editor.session.setMode("ace/mode/php");
    editor.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true
    });
    const lessonId = <?php echo $lesson_id; ?>;
    const language = '<?php echo $language; ?>';
    const storageKey = `lesson_${language}_${lessonId}`;
    const hint = <?php echo $lesson['hint'] ? json_encode($lesson['hint']) : 'null'; ?>;
    // Default PHP code templates based on lesson
    let defaultCode = '';
    if (lessonId === 41) {
        defaultCode = `<?php
echo "";
?>`;
    } else if (lessonId === 42) {
        defaultCode = `<?php
echo "<?php
    // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 43) {
        defaultCode = `<?php
echo "<?php
    // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 44) {
        defaultCode = `<?php
echo "<?php
    // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 45) {
        defaultCode = `<?php
echo "<?php 
  // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 46) {
        defaultCode = `<?php
echo "<?php 
  // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 47) {
        defaultCode = `<?php
echo "<?php 
  // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 48) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 49) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 50) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 51) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 52) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 53) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 54) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 55) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 56) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 57) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 58) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 59) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    } else if (lessonId === 60) {
        defaultCode = `<?php
echo "<?php 
 // เขียนโค้ด
?>";
?>`;
    }
    // Add more lesson templates as needed
    // Load saved code from localStorage if it exists
    const savedCode = localStorage.getItem(storageKey);
    try {
        if (savedCode && savedCode.trim()) {
            editor.setValue(savedCode);
        } else {
            editor.setValue(defaultCode);
        }
    } catch (error) {
        console.error('Error loading saved code:', error);
        editor.setValue(defaultCode);
    }
    // Save draft functionality
    function saveDraft() {
        const code = editor.getValue();
        const draftData = {
            lesson_id: lessonId,
            language: language,
            code: code
        };
        fetch('save_draft.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(draftData)
            })
            .catch(error => console.error('Error saving draft:', error));
    }
    // Auto-save draft
    let saveTimeout;
    editor.on('change', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            saveDraft();
            updateOutput();
        }, 1000);
        checkSolution(false);
    });

    function updateOutput() {
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
                // Create a container with proper styling
                if (!document.getElementById('output-container')) {
                    const styleElement = document.createElement('style');
                    styleElement.textContent = `
                .output-content h1 { 
                    font-size: 2em !important; 
                    font-weight: bold !important; 
                    margin-bottom: 0.67em !important;
                }
                .output-content h2 { 
                    font-size: 1.5em !important; 
                    font-weight: bold !important; 
                    margin-bottom: 0.5em !important;
                }
                .output-content h3 { 
                    font-size: 1.17em !important; 
                    font-weight: bold !important; 
                    margin-bottom: 0.33em !important;
                }
                .output-content p { 
                    margin-bottom: 1em !important; 
                }
            `;
                    document.head.appendChild(styleElement);
                    const container = document.createElement('div');
                    container.id = 'output-container';
                    container.className = 'output-content';
                    outputDiv.appendChild(container);
                }
                const container = document.getElementById('output-container');
                if (data.error) {
                    container.innerHTML = `<span class="text-red-500">Error: ${data.error}</span>`;
                } else {
                    // Safely render HTML content
                    container.innerHTML = data.output;
                }
            })
            .catch(error => {
                const outputDiv = document.getElementById('output');
                outputDiv.innerHTML = `<span class="text-red-500">Error: ${error.message}</span>`;
            });
    }
    // Load draft when page loads
    window.addEventListener('load', function() {
        fetch(`get_draft.php?language=${language}&lesson_id=${lessonId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.draft_code) {
                    editor.setValue(data.draft_code);
                } else {
                    editor.setValue(defaultCode);
                }
            })
            .catch(error => {
                console.error('Error loading draft:', error);
                editor.setValue(defaultCode);
            });
    });
    // Reset code button functionality
    document.getElementById('resetCodeBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to reset your code?')) {
            editor.setValue(defaultCode);
            editor.clearSelection();
            checkSolution(false);
            successMessage.classList.add('hidden');
            statusIndicator.classList.add('hidden');
            nextLessonBtn.classList.remove('bg-green-500', 'text-white', 'hover:bg-green-600');
            nextLessonBtn.classList.add('bg-gray-300', 'text-gray-600', 'cursor-not-allowed');
            nextLessonBtn.disabled = true;
        }
    });
    const nextLessonBtn = document.getElementById('nextLesson');
    const checkSolutionBtn = document.getElementById('checkSolutionBtn');
    const successMessage = document.getElementById('successMessage');
    const statusIndicator = document.getElementById('statusIndicator');
    const nextLessonId = <?php echo $nextLessonId ? $nextLessonId : 'null'; ?>;
    nextLessonBtn.addEventListener('click', function() {
        if (nextLessonId) {
            window.location.href = `php_lesson.php?lesson=${nextLessonId}`;
        } else {
            // If there's no next lesson, redirect to dashboard
            window.location.href = `dashboard_${language}_detail.php`;
        }
    });
    checkSolutionBtn.addEventListener('click', function() {
        checkSolution(true);
    });
    document.getElementById('showHintBtn').addEventListener('click', function() {
        const hintMessage = document.getElementById('hintMessage');
        const hintContent = document.getElementById('hintContent');
        if (hint) {
            if (hintMessage.classList.contains('hidden')) {
                // Show hint
                hintContent.textContent = hint;
                hintMessage.classList.remove('hidden');
                this.textContent = 'Hide Hint';
            } else {
                // Hide hint
                hintMessage.classList.add('hidden');
                this.textContent = 'Show Hint';
            }
        } else {
            alert('No hint available for this lesson');
        }
    });

    function checkSolution(showFeedback = false) {
        const code = editor.getValue();
        const expectedOutput = <?php echo json_encode($lesson['expected_output']); ?>;
        // Import helper functions
        function levenshtein(a, b) {
            if (a.length === 0) return b.length;
            if (b.length === 0) return a.length;
            if (a.length > b.length) {
                [a, b] = [b, a];
            }
            let row = Array(a.length + 1).fill(0).map((_, i) => i);
            for (let i = 1; i <= b.length; i++) {
                let prev = i;
                for (let j = 1; j <= a.length; j++) {
                    let val = b[i - 1] === a[j - 1] ? row[j - 1] : Math.min(row[j - 1] + 1, prev + 1, row[j] + 1);
                    row[j - 1] = prev;
                    prev = val;
                }
                row[a.length] = prev;
            }
            return row[a.length];
        }

        function calculateSimilarity(s1, s2) {
            let longer = s1.length > s2.length ? s1 : s2;
            let shorter = s1.length > s2.length ? s2 : s1;
            let longerLength = longer.length;
            if (longerLength === 0) return 1.0;
            let editDistance = levenshtein(s1, s2);
            return (longerLength - editDistance) / longerLength;
        }
        // Normalize both code and expected output
        const normalizedCode = code.replace(/\s+/g, ' ').trim().toLowerCase();
        const normalizedExpected = expectedOutput.replace(/\s+/g, ' ').trim().toLowerCase();
        // Set threshold for similarity (95% similar)
        const SIMILARITY_THRESHOLD = 0.99;
        // Check if code matches or is very similar to expected output
        if (normalizedCode === normalizedExpected ||
            calculateSimilarity(normalizedCode, normalizedExpected) >= SIMILARITY_THRESHOLD) {
            const progressData = {
                lesson_id: lessonId,
                language: language,
                score: 10,
                completed: true
            };
            fetch('save_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(progressData)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(errData => {
                            throw new Error(errData.error || 'Failed to save progress');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        nextLessonBtn.classList.remove('bg-gray-300', 'text-gray-600', 'cursor-not-allowed');
                        nextLessonBtn.classList.add('bg-green-500', 'text-white', 'hover:bg-green-600');
                        nextLessonBtn.disabled = false;
                        successMessage.classList.remove('hidden');
                        statusIndicator.classList.remove('hidden');
                        if (showFeedback) {
                            successMessage.classList.add('animate-bounce');
                            setTimeout(() => {
                                successMessage.classList.remove('animate-bounce');
                            }, 1000);
                        }
                    }
                })
                .catch(error => {
                    console.error('Progress save error:', error.message);
                });
        } else {
            nextLessonBtn.classList.remove('bg-green-500', 'text-white', 'hover:bg-green-600');
            nextLessonBtn.classList.add('bg-gray-300', 'text-gray-600', 'cursor-not-allowed');
            nextLessonBtn.disabled = true;
            successMessage.classList.add('hidden');
            statusIndicator.classList.add('hidden');
            if (showFeedback) {
                alert('Not quite right yet. Keep trying!');
            }
        }
    }
    updatePreview();
    </script>
</body>

</html>