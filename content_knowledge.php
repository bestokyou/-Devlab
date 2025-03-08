<?php
require_once 'config.php';
checkLogin();

// รับค่าภาษาที่ต้องการแสดง (HTML, CSS, PHP)
$language = isset($_GET['language']) ? strtolower($_GET['language']) : 'html';
$allowed_languages = ['html', 'css', 'php'];
if (!in_array($language, $allowed_languages)) {
    $language = 'html';
}

// กำหนดชื่อแสดงของภาษา
$language_title = strtoupper($language);

// กำหนด URL สำหรับกลับไปหน้ารายละเอียดหลักสูตร
$back_url = "dashboard_".$language."_detail.php";

// ดึงข้อมูลหมวดหมู่ทั้งหมดของภาษาที่เลือก
$stmt = $pdo->prepare("
    SELECT * FROM content_sections 
    WHERE language = ? 
    ORDER BY order_num ASC
");
$stmt->execute([$language]);
$sections = $stmt->fetchAll();

// ตรวจสอบไฟล์เดิม
$legacy_file_exists = false;
$legacy_file = $language . "_knowledge.php";
$legacy_file_capital_k = $language . "_Knowledge.php";

// ตรวจสอบทั้ง 2 รูปแบบ (ตัวพิมพ์เล็กและตัวพิมพ์ใหญ่)
if (file_exists($legacy_file)) {
    $legacy_file_exists = true;
} elseif (file_exists($legacy_file_capital_k)) {
    $legacy_file_exists = true;
    $legacy_file = $legacy_file_capital_k; // ใช้ชื่อไฟล์ที่มีตัว K ใหญ่แทน
}
?>
<!DOCTYPE html>
<html>

<head>
    <title><?php echo $language_title; ?> Learning Materials - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <!-- เพิ่ม CSS สำหรับแสดงเนื้อหา -->
    <style>
    .content-container img {
        max-width: 100%;
        height: auto;
    }

    .content-container h1,
    .content-container h2,
    .content-container h3 {
        margin-top: 1rem;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }

    .content-container pre {
        background-color: #f3f4f6;
        padding: 1rem;
        border-radius: 0.375rem;
        overflow-x: auto;
    }

    .content-container ul,
    .content-container ol {
        padding-left: 1.5rem;
        margin-bottom: 1rem;
    }

    .content-container ul {
        list-style-type: disc;
    }

    .content-container ol {
        list-style-type: decimal;
    }

    .content-container a {
        color: #2563eb;
        text-decoration: none;
    }

    .content-container a:hover {
        text-decoration: underline;
    }

    .content-container table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1rem;
    }

    .content-container table th,
    .content-container table td {
        border: 1px solid #e5e7eb;
        padding: 0.5rem;
    }

    .content-container table th {
        background-color: #f3f4f6;
    }

    /* ธีมตามภาษา */
    .html-theme {
        border-color: #e34c26 !important;
    }

    .css-theme {
        border-color: #264de4 !important;
    }

    .php-theme {
        border-color: #777bb3 !important;
    }

    .html-bg {
        background-color: #e34c26;
    }

    .css-bg {
        background-color: #264de4;
    }

    .php-bg {
        background-color: #777bb3;
    }
    </style>
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
                    <h1 class="text-2xl font-bold"><?php echo $language_title; ?> Learning Materials</h1>
                </div>

                <div class="flex items-center gap-4">
                    <a href="<?php echo $back_url; ?>" class="text-blue-500 hover:underline"> Back to
                        <?php echo $language_title; ?> Course</a>
                    <?php
                    // ตรวจสอบสิทธิ์ผู้ใช้ (แสดงลิงก์จัดการเนื้อหาเฉพาะแอดมินหรืออาจารย์)
                    $adminCheckStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
                    $adminCheckStmt->execute([$_SESSION['user_id']]);
                    $isAdmin = $adminCheckStmt->fetchColumn();
                    
                    $teacherCheckStmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
                    $teacherCheckStmt->execute([$_SESSION['user_id']]);
                    $isTeacher = $teacherCheckStmt->rowCount() > 0;
                    
                    if ($isAdmin || $isTeacher):
                    ?>
                    <a href="manage_content.php?language=<?php echo $language; ?>"
                        class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">จัดการเนื้อหา</a>
                    <?php endif; ?>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
                </div>
            </div>
        </div>

        <!-- Content Sections -->
        <div class="grid grid-cols-1 gap-6 mt-24">


            <?php
            // กำหนดสีปุ่มตามภาษา
            $btnColor = "";
            if ($language == "html") {
                $btnColor = "bg-red-600";
            } elseif ($language == "css") {
                $btnColor = "bg-blue-600";
            } elseif ($language == "php") {
                $btnColor = "bg-purple-600";
            } else {
                $btnColor = "bg-gray-600";
            }
            
            // แสดงปุ่มไปยังเนื้อหาเดิมถ้ามีไฟล์
            if ($legacy_file_exists) {
                echo '<div class="bg-white rounded-lg shadow-md p-4 mb-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-lg font-semibold">เนื้อหาเรียนรู้ '.$language_title.' แบบเดิม</h3>
                                <p class="text-gray-600 text-sm">ดูเนื้อหาในรูปแบบเดิมสำหรับการเรียนรู้ '.$language_title.'</p>
                                <p class="text-xs text-gray-500">ไฟล์ที่ตรวจพบ: '.$legacy_file.'</p>
                            </div>
                            <a href="./'.$legacy_file.'" class="'.$btnColor.' text-white px-6 py-2 rounded-lg hover:opacity-90 transition">
                                ไปยังเนื้อหาเบื้องต้น
                            </a>
                        </div>
                      </div>';
            }
            
            if (empty($sections)) {
                echo '<div class="bg-white rounded-lg shadow-md p-6">
                        <div class="text-center py-10">
                            <h2 class="text-2xl font-bold mb-3">ยังไม่มีเนื้อหาสำหรับภาษา '.$language_title.'</h2>
                            <p class="text-gray-600 mb-4">เนื้อหากำลังอยู่ในระหว่างการจัดทำ กรุณากลับมาใหม่ในภายหลัง</p>
                        </div>
                      </div>';
            } else {
                foreach ($sections as $section) {
                    // ดึงเนื้อหาทั้งหมดในหมวดหมู่นี้
                    $contentStmt = $pdo->prepare("
                        SELECT * FROM content_materials 
                        WHERE section_id = ? 
                        ORDER BY order_num ASC
                    ");
                    $contentStmt->execute([$section['id']]);
                    $contents = $contentStmt->fetchAll();
                    
                    ?>
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4 <?php echo $language; ?>-theme">
                <h2 class="text-2xl font-bold mb-4"><?php echo htmlspecialchars($section['title']); ?></h2>
                <?php if (!empty($section['description'])): ?>
                <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($section['description']); ?></p>
                <?php endif; ?>

                <?php if (empty($contents)): ?>
                <p class="text-gray-500 italic">ยังไม่มีเนื้อหาในหมวดหมู่นี้</p>
                <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($contents as $content): ?>
                    <div class="border-t pt-4">
                        <h3 class="text-xl font-semibold mb-3"><?php echo htmlspecialchars($content['title']); ?></h3>
                        <div class="content-container prose max-w-none">
                            <?php echo $content['content']; ?>
                        </div>

                        <?php
                                        // ตรวจสอบว่ามีไฟล์แนบหรือไม่
                                        $fileStmt = $pdo->prepare("SELECT * FROM content_files WHERE material_id = ?");
                                        $fileStmt->execute([$content['id']]);
                                        $files = $fileStmt->fetchAll();
                                        
                                        if (!empty($files)):
                                        ?>
                        <div class="mt-4 pt-3 border-t">
                            <h4 class="font-medium mb-2">ไฟล์แนบ:</h4>
                            <div class="space-y-4">
                                <?php foreach ($files as $file): 
                                    // ตรวจสอบว่าเป็นไฟล์รูปภาพหรือไม่
                                    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
                                    $file_extension = pathinfo($file['filename'], PATHINFO_EXTENSION);
                                    $is_image = in_array(strtolower($file_extension), $image_extensions);
                                    
                                    if ($is_image):
                                    // ถ้าเป็นรูปภาพ แสดงรูปภาพทันที
                                ?>
                                <div class="border p-2 rounded-lg shadow-sm">
                                    <a href="<?php echo htmlspecialchars($file['filepath']); ?>" target="_blank" 
                                       class="text-blue-500 hover:underline mb-2 block">
                                        <?php echo htmlspecialchars($file['filename']); ?>
                                    </a>
                                    <img src="<?php echo htmlspecialchars($file['filepath']); ?>" alt="<?php echo htmlspecialchars($file['filename']); ?>" 
                                         class="max-w-full h-auto mt-2 border rounded" style="max-height: 300px;">
                                </div>
                                <?php else: 
                                    // ถ้าไม่ใช่รูปภาพ แสดงแค่ลิงก์
                                ?>
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <a href="<?php echo htmlspecialchars($file['filepath']); ?>" target="_blank"
                                        class="text-blue-500 hover:underline">
                                        <?php echo htmlspecialchars($file['filename']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php
                }
            }
            ?>
        </div>
    </div>
</body>

</html>