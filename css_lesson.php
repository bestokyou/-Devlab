<?php
require_once 'config.php';
require_once 'lesson_data.php';
checkLogin();
$language = 'css';
$lesson_id = (int)($_GET['lesson'] ?? 21); // CSS lessons start from ID 21

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
        header("Location: css_lesson.php?lesson=$prevLessonId");
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

<head>
    <title>Lesson - <?php echo htmlspecialchars($lesson['title']); ?></title>
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

    /* Rest of your existing styles... */
    </style>
</head>

<body>
    <div class="main-container">
        <!-- Header -->
        <header class="header">
            <div class="flex justify-between items-center p-5">
                <div class="flex items-center">
                    <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                    <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($lesson['title']); ?></h1>
                </div>
                <div class="flex items-center gap-4">
                    <a href="dashboard_css_detail.php" class="text-blue-500 hover:text-blue-700">กลับไปยังแดชบอร์ด</a>
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
                        class="bg-blue-500 text-white py-1.5 px-3 rounded-md hover:bg-blue-600 ">
                        Check Solution
                    </button>
                    <button id="resetCodeBtn" class="bg-red-500 text-white py-1.5 px-3 rounded-md hover:bg-red-600">
                        Reset Code
                    </button>
                    <button id="nextLesson" class="bg-gray-300 text-gray-600 py-1.5 px-3 rounded-md cursor-not-allowed "
                        disabled>
                        Next Lesson →
                    </button>
                    <button id="showHintBtn"
                        class="bg-yellow-500 text-white py-1.5 px-3 rounded-md hover:bg-yellow-600 ">
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
            <!-- Preview -->
            <div class="preview-section">
                <iframe id="preview" class="w-full h-full"></iframe>
            </div>
        </div>
    </div>
    <script>
    const editor = ace.edit("editor");
    editor.setTheme("ace/theme/monokai");
    editor.session.setMode("ace/mode/html");
    editor.setOptions({
        enableBasicAutocompletion: true,
        enableSnippets: true,
        enableLiveAutocompletion: true
    });
    const lessonId = <?php echo $lesson_id; ?>;
    const language = '<?php echo $language; ?>';
    const storageKey = `lesson_${language}_${lessonId}`;
    const hint = <?php echo $lesson['hint'] ? json_encode($lesson['hint']) : 'null'; ?>;
    // Default HTML code templates based on lesson
    let defaultCode = '';
    if (lessonId === 21) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <title>My Styled Page</title>
</head>
<body style="background-color:  ;">
<h1>this is my first page</h1> 
</body>
</html>`;
    } else if (lessonId === 22) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <title>My Styled Page</title>
</head>
<body>
    <header>
        <h1>Welcome to My Website</h1>
        <nav>
            <a href="#">Home</a>
            <a href="#">About</a>
            <a href="#">Contact</a>
        </nav>
    </header>
    <main>
        <section>
            <h2>About Us</h2>
            <p>This is a paragraph about our company. We are dedicated to providing the best service to our customers.</p>
            <button>Learn More</button>
        </section>
        <section>
            <h3>Our Services</h3>
            <div class="service-box">
                <h4>Service 1</h4>
                <p>Description of service 1</p>
            </div>
            <div class="service-box">
                <h4>Service 2</h4>
                <p>Description of service 2</p>
            </div>
        </section>
        <section>
            <h3>Contact Form</h3>
            <form>
                <label>Name:</label>
                <input type="text">
                <label>Email:</label>
                <input type="email">
                <button type="submit">Send</button>
            </form>
        </section>
    </main>
    <footer>
        <p>Copyright © 2025 My Website</p>
    </footer>
</body>
</html>`;
    } else if (lessonId === 23) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <title>My Styled Page</title>
</head>
<body>
    <header>
        <h1>Welcome to My Website</h1>
        <nav>
            <a href="#">Home</a>
            <a href="#">About</a>
            <a href="#">Contact</a>
        </nav>
    </header> 
</body>
</html>`;
    } else if (lessonId === 24) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <title>My Styled Page</title>
</head>
<body>
    <header>
        <h1>Welcome to My Website</h1>
        <nav>
            <a href="#">Home</a>
            <a href="#">About</a>
            <a href="#">Contact</a>
        </nav>
    </header>
    <main>
        <section>
            <h2>About Us</h2>
            <p>This is a paragraph about our company. We are dedicated to providing the best service to our customers.</p>
            <button>Learn More</button>
        </section>
        <section>
            <h3>Our Services</h3>
            <div class="service-box">
                <h4>Service 1</h4>
                <p>Description of service 1</p>
            </div>
            <div class="service-box">
                <h4>Service 2</h4>
                <p>Description of service 2</p>
            </div>
        </section>
        <section>
            <h3>Contact Form</h3>
            <form>
                <label>Name:</label>
                <input type="text">
                <label>Email:</label>
                <input type="email">
                <button type="submit">Send</button>
            </form>
        </section>
    </main>
    <footer>
        <p>Copyright © 2025 My Website</p>
    </footer>
</body>
</html>`;
    } else if (lessonId === 25) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <title>My Styled Page</title>
</head>
<body>
<div class="service-box">
    <h4>Service 1</h4>
    <p>Description of service 1</p>
</div>
</body>
</html>`;
    } else if (lessonId === 26) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <title>My Styled Page</title>
</head>
<body>
    <div style="border:1px solid black;width:200px; ">Centered Content</div>
</body>
</html>`;
    } else if (lessonId === 27) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <style>
        h1 {
        }
        p {
        }
    </style>
</head>
<body>
    <h1>This is a heading</h1>
    <p>This is a paragraph</p>
</body>
</html>`;
    } else if (lessonId === 28) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <style>
        .info-box {
        }
    </style>
</head>
<body>
    <div class="info-box">
        This is an information box
    </div>
</body>
</html>`;
    } else if (lessonId === 29) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <style>
    div{
    }
    </style>
</head>
<body>
<div>
    Centered Content
</div>
</body>
</html>`;
    } else if (lessonId === 30) {
        defaultCode = `
<!DOCTYPE html>
<html>
<head>
    <style>
        .box-grid {
             display: grid; 
        }
    </style>
</head>
<body>
    <div class=box-grid>
    <div style="background-color: lightblue; padding: 20px;">Grid Item 1</div>
    <div style="background-color: lightgreen; padding: 20px;">Grid Item 2</div>
    </div>
</body>
</html>`;
    } else if (lessonId === 31) {
        defaultCode = `
<!DOCTYPE html>
<html>
<head>
    <style>
        .product-grid {
            display: grid;
}
        .product {
            background: lightblue;
            padding: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="product-grid">
    <div class="product">สินค้า 1</div>
    <div class="product">สินค้า 2</div>
    <div class="product">สินค้า 3</div>
    <div class="product">สินค้า 4</div>
</div>
</body>
</html>`;
    } else if (lessonId === 32) {
        defaultCode = `
<!DOCTYPE html>
<html>
<head>
    <style>
        .product-grid {
            display: grid;
            grid-auto-columns: 200px;
            /*เพิ่ม gap ตรงนี้*/
}
        .product {
            background: lightblue;
            padding: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="product-grid">
    <div class="product">สินค้า 1</div>
    <div class="product">สินค้า 2</div>
    <div class="product">สินค้า 3</div>
    <div class="product">สินค้า 4</div>
</div>
</body>
</html>`;
    } else if (lessonId === 33) {
        defaultCode = `
<html>
<head>
    <style>
        h1 {
        }
        p {
        }
    </style>
</head>
<body>
    <h1>หัวข้อใหญ่</h1>
    <p>เนื้อหาข้อความ</p>
</body>`;
    } else if (lessonId === 34) {
        defaultCode = `
<!DOCTYPE html>
<html>
<head>
    <title>Border Example</title>
    <style>
    .box1{
        /*เพิ่มborder*/
        /*เพิ่มborder-radius*/
         padding: 20px;       
    }
    </style>
</head>
<body>
    <div class="box1">
        This is a box with border
    </div>
</body>
</html>`;
    } else if (lessonId === 35) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <style>
        h1 {
            border-block: 5px solid red;
        }
        h2 {
            border-block: ;
        }
        div {
            border-block: ;
        }
    </style>
</head>
<body>
    <h1>Heading 1</h1>
    <h2>Heading 2</h2>
    <div>This is a div</div>
</body>
</html>`;
    } else if (lessonId === 36) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <style>
        .shadow-box {
            width: 200px;
            height: 100px;
            background-color: white;
            padding: 20px;
            /*เพิ่ม box-shadow ตรงนี้*/
        }
    </style>
</head>
<body>
    <div class="shadow-box">
        Box with shadow
    </div>
</body>
</html>`;
    } else if (lessonId === 37) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <title>Font Style Example</title>
</head>
<body>
    <p>
        <span style="">This is a normal text.</span>
        <br>
        <span style="">This is an italic text.</span>
    </p>
</body>
</html>`;
    } else if (lessonId === 38) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <title>Styled Card</title>
    <style>
        div{
            /*border*/
            /*border-radius*/
            /*box-shadow*/
            /*padding*/
            /*font-style*/
        }
    </style>
</head>
<body>
    <div>
    <p>นี่คือการ์ดที่มีสไตล์พร้อมคุณสมบัติ CSS </p>
    </div>
</body>
</html>`;
    } else if (lessonId === 39) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <style>
        .product-grid {
            /*display*/
            /*grid-template-columns*/
        }
        .card {
            /*border*/
            /*box-shadow*/
            /*padding*/
        }
    </style>
</head>
<body>
    <div class="product-grid">
        <div class="card">สินค้า 1</div>
        <div class="card">สินค้า 2</div>
    </div>
</body>
</html>`;
    } else if (lessonId === 40) {
        defaultCode = `<!DOCTYPE html>
<html>
<head>
    <style>
        header {
            /*background-color สีฟ้าอ่อน skyblue */
            /*color สีขาว white          */
            /*padding 20px           */
            /*text-align กึ่งกลาง  */ 
        }
        nav {
            /* padding 20px*/
            /*background-color สีขาว */
        }
        main {
          /*  padding 20px */
        }
        footer {
            color: white;
            background-color: black;
            text-align: center;
            padding: 20px;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <header>
        <h1>My Website</h1>
    </header>
    <nav>
        <a href="#">Home</a>
        <a href="#">About</a>
        <a href="#">Contact</a>
    </nav>
    <main>
        <h2>Welcome to my website</h2>
        <p>This is the main content area of the website.</p>
    </main>
    <footer>
        <p>© 2025 My Website. All rights reserved.</p>
    </footer>
</body>
</html>`;
    }
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
    editor.on('change', function() {
        updatePreview();
        checkSolution(false);
    });
    // Reset code button functionality
    document.getElementById('resetCodeBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to reset your code?')) {
            editor.setValue(defaultCode);
            editor.clearSelection();
            updatePreview();
            checkSolution(false);
            // Clear any success messages and disable next lesson button
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
            window.location.href = `css_lesson.php?lesson=${nextLessonId}`;
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

    function updatePreview() {
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
    }

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
            .then(response => response.json())
            .catch(error => console.error('Error saving draft:', error));
    }
    // Auto-save draft
    let saveTimeout;
    editor.on('change', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveDraft, 1000);
        updatePreview();
        checkSolution(false);
    });
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
    // check 2
    function checkSolution(showFeedback = false) {
        // ทำความสะอาดโค้ดที่ผู้ใช้ป้อน
        let code = editor.getValue()
            .replace(/>\s+</g, '><') // ลบช่องว่างระหว่างแท็ก
            .replace(/\s+/g, ' ') // แทนที่ช่องว่างหลายๆ ช่องด้วยช่องว่างเดียว
            .replace(/>\s/g, '>') // ลบช่องว่างหลังเครื่องหมาย >
            .replace(/\s</g, '<') // ลบช่องว่างก่อนเครื่องหมาย <
            .trim()
            .toLowerCase();
        let expectedOutput = <?php echo json_encode($lesson['expected_output']); ?>
            .replace(/>\s+</g, '><')
            .replace(/\s+/g, ' ')
            .replace(/>\s/g, '>')
            .replace(/\s</g, '<')
            .trim()
            .toLowerCase();

        function similarity(s1, s2) {
            let longer = s1.length > s2.length ? s1 : s2;
            let shorter = s1.length > s2.length ? s2 : s1;
            let longerLength = longer.length;
            if (longerLength === 0) return 1.0;
            let editDistance = levenshtein(s1, s2);
            return (longerLength - editDistance) / longerLength;
        }

        function levenshtein(a, b) {
            let tmp;
            if (a.length === 0) return b.length;
            if (b.length === 0) return a.length;
            if (a.length > b.length) {
                tmp = a;
                a = b;
                b = tmp;
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
        // กำหนดค่าความคล้ายคลึงขั้นต่ำ (เช่น 85%)
        const SIMILARITY_THRESHOLD = 0.99;
        if (similarity(code, expectedOutput) >= SIMILARITY_THRESHOLD) {
            const progressData = {
                lesson_id: lessonId,
                language: language,
                score: 10,
                completed: true
            };
            fetch('save_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(progressData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        nextLessonBtn.classList.remove('bg-gray-300', 'text-gray-600', 'cursor-not-allowed');
                        nextLessonBtn.classList.add('bg-green-500', 'text-white', 'hover:bg-green-600');
                        nextLessonBtn.disabled = false;
                        successMessage.classList.remove('hidden');
                        statusIndicator.classList.remove('hidden');
                        if (showFeedback) {
                            successMessage.classList.add('animate-bounce');
                            setTimeout(() => successMessage.classList.remove('animate-bounce'), 1000);
                        }
                    }
                })
                .catch(error => console.error('Progress save error:', error.message));
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