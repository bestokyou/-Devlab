<?php
require_once 'config.php';
checkLogin();
?>
<!DOCTYPE html>
<html>

<head>
    <title>HTML Learning Materials - DevLab</title>
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
                    <h1 class="text-2xl font-bold">HTML Learning Materials</h1>
                </div>
                <div class="flex items-center gap-4">
                    <a href="dashboard_html_detail.php" class="text-blue-500 hover:underline"> Back to HTML Course</a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
                </div>
            </div>
        </div>
        <!-- Content Sections -->
        <div class="grid grid-cols-1 gap-6 mt-20">
            <!-- Introduction to HTML -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">บทนำสู่ HTML</h2>
                <div class="prose max-w-none">
                    <p class="mb-4">HTML (HyperText Markup Language) คือภาษาที่ใช้กำหนดโครงสร้างของเนื้อหาบนเว็บไซต์
                        ทำให้หน้าเว็บดูมีชีวิตชีวาและน่าสนใจขึ้น</p>
                    <h3 class="text-xl font-semibold mb-3">โครงสร้างพื้นฐาน HTML</h3>
                    <div class="bg-gray-100 p-4 rounded-lg mb-4">
                        <pre class="text-sm">
&lt;!DOCTYPE html&gt;
&lt;html&gt;
&lt;head&gt;
    &lt;title&gt;ชื่อหน้าเว็บ&lt;/title&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;h1&gt;หัวข้อหลัก&lt;/h1&gt;
    &lt;p&gt;เนื้อหาย่อหน้า&lt;/p&gt;
&lt;/body&gt;
&lt;/html&gt;
                        </pre>
                    </div>
                </div>
            </div>
            <!-- Text Formatting -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">การจัดรูปแบบข้อความ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">แท็กพื้นฐาน</h3>
                        <ul class="list-disc pl-5 space-y-2">
                            <li><code>&lt;h1&gt;</code> ถึง <code>&lt;h6&gt;</code> - หัวข้อ</li>
                            <li><code>&lt;p&gt;</code> - ย่อหน้า</li>
                            <li><code>&lt;strong&gt;</code> - ตัวหนา</li>
                            <li><code>&lt;em&gt;</code> - ตัวเอียง</li>
                            <li><code>&lt;br&gt;</code> - ขึ้นบรรทัดใหม่</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold mb-3">ตัวอย่างการใช้งาน</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
&lt;h1&gt;หัวข้อหลัก&lt;/h1&gt;
&lt;p&gt;นี่คือ&lt;strong&gt;ข้อความตัวหนา&lt;/strong&gt;
และนี่คือ&lt;em&gt;ข้อความตัวเอียง&lt;/em&gt;&lt;/p&gt;
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Links and Images -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">การสร้างลิงก์และใส่รูปภาพ</h2>
                <div class="space-y-4">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">การสร้างลิงก์</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
&lt;!-- ลิงก์พื้นฐาน --&gt;
&lt;a href="https://www.example.com"&gt;คลิกที่นี่&lt;/a&gt;
&lt;!-- ลิงก์เปิดในแท็บใหม่ --&gt;
&lt;a href="https://www.example.com" target="_blank"&gt;เปิดในแท็บใหม่&lt;/a&gt;
                            </pre>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold mb-3">การใส่รูปภาพ</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
&lt;!-- รูปภาพพื้นฐาน --&gt;
&lt;img src="image.jpg" alt="คำอธิบายรูปภาพ"&gt;
&lt;!-- รูปภาพพร้อมกำหนดขนาด --&gt;
&lt;img src="image.jpg" alt="คำอธิบายรูปภาพ" width="300" height="200"&gt;
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Lists -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">การสร้างรายการ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-xl font-semibold mb-3">รายการแบบไม่เรียงลำดับ</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
&lt;ul&gt;
    &lt;li&gt;รายการที่ 1&lt;/li&gt;
    &lt;li&gt;รายการที่ 2&lt;/li&gt;
    &lt;li&gt;รายการที่ 3&lt;/li&gt;
&lt;/ul&gt;
                            </pre>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold mb-3">รายการแบบเรียงลำดับ</h3>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <pre class="text-sm">
&lt;ol&gt;
    &lt;li&gt;ขั้นตอนที่ 1&lt;/li&gt;
    &lt;li&gt;ขั้นตอนที่ 2&lt;/li&gt;
    &lt;li&gt;ขั้นตอนที่ 3&lt;/li&gt;
&lt;/ol&gt;
                            </pre>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Tables -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">การสร้างตาราง</h2>
                <div class="space-y-4">
                    <h3 class="text-xl font-semibold mb-3">โครงสร้างตารางพื้นฐาน</h3>
                    <div class="bg-gray-100 p-4 rounded-lg">
                        <pre class="text-sm">
&lt;table border="1"&gt;
    &lt;tr&gt;
        &lt;th&gt;หัวข้อที่ 1&lt;/th&gt;
        &lt;th&gt;หัวข้อที่ 2&lt;/th&gt;
    &lt;/tr&gt;
    &lt;tr&gt;
        &lt;td&gt;ข้อมูล 1&lt;/td&gt;
        &lt;td&gt;ข้อมูล 2&lt;/td&gt;
    &lt;/tr&gt;
&lt;/table&gt;
                        </pre>
                    </div>
                </div>
            </div>
            <!-- Forms -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-2xl font-bold mb-4">การสร้างฟอร์ม</h2>
                <div class="space-y-4">
                    <h3 class="text-xl font-semibold mb-3">ตัวอย่างฟอร์มพื้นฐาน</h3>
                    <div class="bg-gray-100 p-4 rounded-lg">
                        <pre class="text-sm">
&lt;form action="/submit" method="post"&gt;
    &lt;label for="name"&gt;ชื่อ:&lt;/label&gt;
    &lt;input type="text" id="name" name="name"&gt;
    &lt;label for="email"&gt;อีเมล:&lt;/label&gt;
    &lt;input type="email" id="email" name="email"&gt;
    &lt;button type="submit"&gt;ส่งข้อมูล&lt;/button&gt;
&lt;/form&gt;
                        </pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>