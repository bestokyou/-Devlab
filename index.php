<?php
require_once 'config.php';
// ตรวจสอบว่ามี session หรือ remember token หรือไม่
if (isset($_SESSION['user_id']) || (isset($_COOKIE['remember_token']) && checkRememberToken())) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevLab - Learn HTML, CSS & PHP</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="icon" type="image/png" href="icon1.png">
    <link href="css/styles.css" rel="stylesheet">
</head>

<body class="bg-gray-900">
    <!-- Header -->
    <header class="bg-white shadow">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <img src="img/devlab.png" alt="DevLab Logo" class="h-10">
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-blue-600 hover:text-blue-800">Login</a>
                    <a href="register.php"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        Let Start!
                    </a>
                </div>
            </div>
        </nav>
    </header>
    <!-- Hero Section -->
    <main>
        <div class="container mx-auto px-6 py-16">
            <div class="flex flex-col lg:flex-row items-center">
                <div class="lg:w-1/2">
                    <h1 class="text-4xl lg:text-8xl font-bold text-white leading-tight mb-6">
                        Devlab<br>
                        <span class="text-blue-500"></span>
                    </h1>
                    <p class="text-gray-300 text-xl mb-8">
                        เรียนรู้ HTML, CSS และ PHP ผ่านบทเรียน และแบบฝึกหัดการเขียนโค้ดแบบปฏิบัติจริง
                    </p>
                    <div class="space-x-4">
                        <a href="register.php"
                            class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors inline-block">
                            Start Learning for Free
                        </a>
                    </div>
                </div>
                <div class="lg:w-1/2 mt-10 lg:mt-0">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white p-6 rounded-lg shadow-lg">
                            <img src="img/html.png" alt="HTML" class="h-12 mb-4">
                            <h3 class="text-xl font-semibold mb-2">HTML</h3>
                            <p class="text-gray-600">เรียนรู้องค์ประกอบพื้นฐานของการพัฒนาเว็บ</p>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow-lg">
                            <img src="img/css.png" alt="CSS" class="h-12 mb-4">
                            <h3 class="text-xl font-semibold mb-2">CSS</h3>
                            <p class="text-gray-600">เรียนรูปการจัดรูปแบบเว็บไซต์
                            </p>
                        </div>
                        <div class="bg-white p-6 rounded-lg shadow-lg">
                            <img src="img/php.png" alt="PHP" class="h-12 mb-4">
                            <h3 class="text-xl font-semibold mb-2">PHP</h3>
                            <p class="text-gray-600">เรียนรู้การใช้งานPHPสำหรับแอปพลิเคชันเว็บ</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Features Section -->
        <div class="bg-white py-16">
            <div class="container mx-auto px-6">
                <h2 class="text-3xl font-bold text-center mb-12">...Devlab...</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="text-center">
                        <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">เส้นทางการเรียนรู้ที่มีโครงสร้าง</h3>
                        <p class="text-gray-600">
                            ปฏิบัติตามหลักสูตรที่ได้รับการออกแบบอย่างรอบคอบซึ่งจะช่วยสร้างทักษะของคุณทีละขั้นตอน</p>
                    </div>
                    <div class="text-center">
                        <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">แบบฝึกหัดแบบโต้ตอบ</h3>
                        <p class="text-gray-600">ฝึกฝนทักษะของคุณด้วยแบบฝึกหัดเขียนโค้ด</p>
                    </div>
                    <div class="text-center">
                        <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-2">ติดตามความคืบหน้าของคุณ</h3>
                        <p class="text-gray-600">ติดตามเส้นทางการเรียนรู้ของคุณด้วยการติดตามความคืบหน้าโดยละเอียด</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <div class="mt-8 text-center text-sm">
        <div class="mt-4 max-w-2xl mx-auto text-gray-400">
            <p>เว็บไซต์นี้ได้รับการพัฒนาขึ้นเป็นโครงการเพื่อการศึกษาเพื่อการเรียนรู้เทคโนโลยีการพัฒนาเว็บ เช่น HTML,
                CSS, PHP และ MySQL มีวัตถุประสงค์เพื่อการศึกษาเท่านั้น และไม่ใช้เพื่อการค้า</p>
            <p class="mt-2"></p>
        </div>
</body>

</html>