<?php
require_once 'config.php';
checkLogin();
?>
<!DOCTYPE html>
<html>

<head>
    <title>CSS Learning Materials - DevLab</title>
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
        <div class="fixed top-0 left-0 right-0 z-50">
            <div class="flex justify-between items-center mb-8 pb-6 border-b bg-white shadow p-5 rounded">
                <div class="flex items-center">
                    <a href="dashboard.php">
                        <img src="img/devlab.png" alt="DevLab Logo" class="h-10 mr-4">
                    </a>
                    <h1 class="text-2xl font-bold">CSS Learning Materials</h1>
                </div>
                <div class="flex items-center gap-4">
                    <a href="dashboard_css_detail.php" class="text-blue-500 hover:underline"> Back to CSS Course</a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
                </div>
            </div>
        </div>
        <!-- Content Sections -->
        <div class="grid grid-cols-1 gap-6 mt-20">
            <!-- Basic Concepts -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">แนวคิด CSS ขั้นพื้นฐาน</h2>
                <div class="prose max-w-none">
                    <h3 class="text-xl font-semibold mb-3">CSS คืออะไร?</h3>
                    <p class="mb-4">CSS (Cascading Style Sheets) คือภาษาที่ใช้สำหรับจัดรูปแบบเพื่ออธิบายการนำเสนอเอกสาร
                        HTML โดยจะกำหนดว่าองค์ประกอบต่างๆ ควรจะแสดงบนหน้าจอ กระดาษ หรือสื่ออื่นๆ อย่างไร
                    </p>
                    <h3 class="text-xl font-semibold mb-3">CSS Syntax (ไวยากรณ์ )</h3>
                    <div class="bg-gray-100 p-4 rounded-lg mb-4">
                        <pre class="text-sm">
                                selector {
                                    property: value;
                                }
                                /* ตัวอย่าง*/
                                p {
                                    color: blue;
                                    font-size: 16px;
                                }
                        </pre>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">วิธีการรวม CSS</h3>
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-semibold">1. Inline CSS </h4>
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <code>&lt;p style="color: blue;"&gt;Blue text&lt;/p&gt;</code>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold">2. Internal CSS</h4>
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <pre class="text-sm">
                                    &lt;style&gt;
                                        p { color: blue; }
                                    &lt;/style&gt;
                                </pre>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold">3. External CSS</h4>
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <code>&lt;link rel="stylesheet" href="styles.css"&gt;</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- CSS Selectors -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">ตัวเลือก CSS</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">ตัวเลือกทั่วไป</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
                                /* ตัวเลือกองค์ประกอบ */
                                p { color: blue; }
                                /* ตัวเลือกคลาส */
                                .highlight { background-color: yellow; }
                                /* ตัวเลือก ID */
                                #header { font-size: 24px; }
                                /* ตัวเลือกผู้สืบทอด (div) */
                                div p { margin: 10px; }
                                /* ตัวเลือกลูก */
                                div > p { padding: 5px; }
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Colors and Typography -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Colors and Typography</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Colors</h3>
                        <div class="space-y-4">
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <pre class="text-sm">
                                        /* Color Methods */
                                        color: red;                /* Named color */
                                        color: #FF0000;           /* Hexadecimal */
                                        color: rgb(255, 0, 0);    /* RGB */
                                        color: rgba(255, 0, 0, 0.5); /* RGBA with opacity */
                                </pre>
                            </div>
                            <div class="demo-box">
                                <p class="demo-text-color">This text is colored using CSS</p>
                                <p class="demo-background">This has a background color</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold mb-3">Typography</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
                                    font-family: Arial, sans-serif;
                                    font-size: 16px;
                                    font-weight: bold;
                                    text-align: center;
                                    line-height: 1.5;
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Box Model -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Box Model</h2>
                <div class="space-y-4">
                    <p>The CSS box model consists of margins, borders, padding, and content.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-xl font-semibold mb-3">Box Model Properties</h3>
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <pre class="text-sm">
.box {
    margin: 10px;     /* Space outside */
    border: 1px solid black;
    padding: 15px;    /* Space inside */
    width: 200px;
    height: 100px;
}
                                </pre>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold mb-3">Examples</h3>
                            <div class="demo-box">
                                <div class="demo-margin">Element with margin</div>
                                <div class="demo-border">Element with border</div>
                                <div class="demo-padding">Element with padding</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Layout -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">Layout with Flexbox</h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-xl font-semibold mb-3">Flexbox Properties</h3>
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <pre class="text-sm">
.container {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.item {
    flex: 1;
    margin: 5px;
}
                                </pre>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold mb-3">Example</h3>
                            <div class="demo-flexbox">
                                <div class="demo-flex-item">Item 1</div>
                                <div class="demo-flex-item">Item 2</div>
                                <div class="demo-flex-item">Item 3</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>