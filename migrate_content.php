<?php
// migrate_content.php - สคริปต์สำหรับโหลดเนื้อหาจากไฟล์เดิมเข้าฐานข้อมูลใหม่
// ต้องรันโดยแอดมินเท่านั้น

require_once 'config.php';
checkLogin();

// ตรวจสอบสิทธิ์แอดมิน
$adminCheckStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$adminCheckStmt->execute([$_SESSION['user_id']]);
$isAdmin = $adminCheckStmt->fetchColumn();

if (!$isAdmin) {
    die("คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

// ฟังก์ชั่นสำหรับดึงเนื้อหาจากไฟล์ HTML/PHP
function extractSections($file_path, $language) {
    if (!file_exists($file_path)) {
        return ['status' => 'error', 'message' => 'ไม่พบไฟล์ ' . $file_path];
    }
    
    $content = file_get_contents($file_path);
    
    // ดึงส่วน <div class="grid grid-cols-1 gap-6 mt-20"> ถึง </div> (ปิดสุดท้าย)
    if (preg_match('/<div class="grid grid-cols-1 gap-6 mt-\d+">(.*)<\/div>\s*<\/div>\s*<\/body>/s', $content, $matches)) {
        $content_html = $matches[1];
        
        // แยกหมวดหมู่ต่างๆ
        preg_match_all('/<div class="bg-white rounded-lg shadow-md p-6">(.*?)<\/div>\s*<\/div>/s', $content_html, $sections);
        
        $results = [];
        
        foreach ($sections[0] as $index => $section_html) {
            // ดึงชื่อหมวดหมู่
            if (preg_match('/<h2 class="text-2xl font-bold mb-4">(.*?)<\/h2>/s', $section_html, $section_title)) {
                $title = trim(strip_tags($section_title[1]));
                
                // สำหรับคำอธิบาย (description) - อาจจะมีหรือไม่มีก็ได้
                $description = '';
                if (preg_match('/<p class="mb-4">(.*?)<\/p>/s', $section_html, $section_desc)) {
                    $description = trim(strip_tags($section_desc[1]));
                }
                
                // ดึงเนื้อหาย่อย (subsections)
                $subsections = [];
                
                // แยกเนื้อหาย่อยตามหัวข้อ <h3>
                if (preg_match_all('/<h3 class="text-xl font-semibold mb-3">(.*?)<\/h3>(.*?)(?=<h3|$)/s', $section_html, $matches_sub, PREG_SET_ORDER)) {
                    foreach ($matches_sub as $sub_index => $subsection) {
                        $sub_title = trim(strip_tags($subsection[1]));
                        $sub_content = trim($subsection[2]);
                        
                        // แทนที่ tags หรือปรับแต่งอื่นๆ ตามต้องการ
                        // ในที่นี้เราเก็บ HTML ไว้เพื่อให้รูปแบบยังคงอยู่
                        
                        $subsections[] = [
                            'title' => $sub_title,
                            'content' => $sub_content,
                            'order' => $sub_index
                        ];
                    }
                }
                
                $results[] = [
                    'title' => $title,
                    'description' => $description,
                    'order' => $index,
                    'subsections' => $subsections
                ];
            }
        }
        
        return ['status' => 'success', 'sections' => $results];
    } else {
        return ['status' => 'error', 'message' => 'ไม่สามารถดึงเนื้อหาจากไฟล์ได้'];
    }
}

// ฟังก์ชั่นสำหรับบันทึกข้อมูลลงฐานข้อมูล
function saveSectionsToDatabase($sections, $language, $pdo, $user_id) {
    $results = [
        'sections_added' => 0,
        'contents_added' => 0,
        'errors' => []
    ];
    
    foreach ($sections as $section) {
        try {
            // เพิ่มหมวดหมู่
            $stmt = $pdo->prepare("
                INSERT INTO content_sections (language, title, description, order_num) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$language, $section['title'], $section['description'], $section['order']]);
            $section_id = $pdo->lastInsertId();
            $results['sections_added']++;
            
            // เพิ่มเนื้อหาย่อย
            foreach ($section['subsections'] as $subsection) {
                $stmt = $pdo->prepare("
                    INSERT INTO content_materials (language, section_id, title, content, order_num, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $language, 
                    $section_id, 
                    $subsection['title'], 
                    $subsection['content'], 
                    $subsection['order'],
                    $user_id
                ]);
                $results['contents_added']++;
            }
        } catch (Exception $e) {
            $results['errors'][] = "Error adding section {$section['title']}: " . $e->getMessage();
        }
    }
    
    return $results;
}

// ประมวลผลการโหลดข้อมูล
$message = '';
$messageType = '';

if (isset($_POST['migrate'])) {
    $language = strtolower($_POST['language']);
    $allowed_languages = ['html', 'css', 'php'];
    
    if (in_array($language, $allowed_languages)) {
        $file_path = $language . '_knowledge.php';
        $extraction_result = extractSections($file_path, $language);
        
        if ($extraction_result['status'] === 'success') {
            // ตรวจสอบว่ามีหมวดหมู่ของภาษานี้อยู่แล้วหรือไม่
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM content_sections WHERE language = ?");
            $stmt->execute([$language]);
            $existing_sections = $stmt->fetchColumn();
            
            if ($existing_sections > 0 && !isset($_POST['overwrite'])) {
                $message = "มีข้อมูลของภาษา " . strtoupper($language) . " อยู่แล้ว กรุณาเลือก 'เขียนทับข้อมูลเดิม' หากต้องการโหลดใหม่";
                $messageType = "warning";
            } else {
                // ถ้าเลือกเขียนทับ ให้ลบข้อมูลเดิมก่อน
                if (isset($_POST['overwrite'])) {
                    // ลบเนื้อหาย่อยทั้งหมดของภาษานี้
                    $stmt = $pdo->prepare("
                        DELETE FROM content_materials 
                        WHERE language = ?
                    ");
                    $stmt->execute([$language]);
                    
                    // ลบหมวดหมู่ทั้งหมดของภาษานี้
                    $stmt = $pdo->prepare("
                        DELETE FROM content_sections 
                        WHERE language = ?
                    ");
                    $stmt->execute([$language]);
                }
                
                // บันทึกข้อมูลลงฐานข้อมูล
                $save_result = saveSectionsToDatabase(
                    $extraction_result['sections'], 
                    $language, 
                    $pdo, 
                    $_SESSION['user_id']
                );
                
                $message = "โหลดข้อมูลสำเร็จ: เพิ่ม {$save_result['sections_added']} หมวดหมู่ และ {$save_result['contents_added']} เนื้อหา";
                if (!empty($save_result['errors'])) {
                    $message .= "<br>พบข้อผิดพลาด: " . implode("<br>", $save_result['errors']);
                    $messageType = "warning";
                } else {
                    $messageType = "success";
                }
            }
        } else {
            $message = "เกิดข้อผิดพลาด: " . $extraction_result['message'];
            $messageType = "danger";
        }
    } else {
        $message = "ภาษาที่เลือกไม่ถูกต้อง";
        $messageType = "danger";
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>โหลดเนื้อหาเดิมเข้าสู่ระบบใหม่ - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
</head>

<body class="bg-gray-900">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto mt-10">
            <h1 class="text-2xl font-bold mb-6">โหลดเนื้อหาเดิมเข้าสู่ระบบใหม่</h1>

            <?php if (!empty($message)): ?>
            <div class="bg-<?php echo $messageType === 'success' ? 'green' : ($messageType === 'warning' ? 'yellow' : 'red'); ?>-100 border-l-4 border-<?php echo $messageType === 'success' ? 'green' : ($messageType === 'warning' ? 'yellow' : 'red'); ?>-500 text-<?php echo $messageType === 'success' ? 'green' : ($messageType === 'warning' ? 'yellow' : 'red'); ?>-700 p-4 mb-6"
                role="alert">
                <p><?php echo $message; ?></p>
            </div>
            <?php endif; ?>

            <form method="post" class="space-y-4">
                <div>
                    <label for="language" class="block text-sm font-medium text-gray-700">เลือกภาษา</label>
                    <select id="language" name="language"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                        <option value="html">HTML</option>
                        <option value="css">CSS</option>
                        <option value="php">PHP</option>
                    </select>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="overwrite" name="overwrite" class="h-4 w-4 text-blue-600">
                    <label for="overwrite" class="ml-2 block text-sm text-gray-700">
                        เขียนทับข้อมูลเดิม (หากมี)
                    </label>
                </div>

                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                ข้อความเตือน: การดำเนินการนี้จะโหลดเนื้อหาจากไฟล์เดิมเข้าสู่ฐานข้อมูลใหม่
                                ตรวจสอบให้แน่ใจว่าไฟล์เดิมยังคงอยู่ในเซิร์ฟเวอร์
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between">
                    <a href="dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        กลับสู่หน้าหลัก
                    </a>
                    <button type="submit" name="migrate"
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        เริ่มโหลดข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>