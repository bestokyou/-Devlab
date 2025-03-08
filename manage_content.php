<?php
require_once 'config.php';
checkLogin();

// ตรวจสอบสิทธิ์ผู้ใช้ (ต้องเป็นแอดมินหรืออาจารย์)
$adminCheckStmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
$adminCheckStmt->execute([$_SESSION['user_id']]);
$isAdmin = $adminCheckStmt->fetchColumn();

$teacherCheckStmt = $pdo->prepare("SELECT id, languages FROM teachers WHERE user_id = ?");
$teacherCheckStmt->execute([$_SESSION['user_id']]);
$teacherData = $teacherCheckStmt->fetch();
$isTeacher = $teacherData !== false;

// ถ้าไม่ใช่ทั้งแอดมินและอาจารย์ ให้กลับไปหน้า dashboard
if (!$isAdmin && !$isTeacher) {
    header("Location: dashboard.php");
    exit;
}

// รับค่าภาษาที่ต้องการจัดการ (HTML, CSS, PHP)
$language = isset($_GET['language']) ? strtolower($_GET['language']) : 'html';
$allowed_languages = ['html', 'css', 'php'];

// ตรวจสอบว่าอาจารย์มีสิทธิ์จัดการภาษาที่เลือกหรือไม่
$teacherAllowedLanguages = [];
if ($isTeacher) {
    $teacherAllowedLanguages = explode(',', strtolower($teacherData['languages']));
    
    // ถ้าภาษาที่เลือกไม่อยู่ในรายการที่อาจารย์สามารถสอนได้ และไม่ใช่แอดมิน
    if (!in_array($language, $teacherAllowedLanguages) && !$isAdmin) {
        // ถ้ามีภาษาที่อาจารย์สอนได้อย่างน้อย 1 ภาษา ให้เปลี่ยนไปใช้ภาษาแรกในรายการแทน
        if (!empty($teacherAllowedLanguages)) {
            $language = $teacherAllowedLanguages[0];
        } else {
            // ถ้าไม่มีภาษาที่สอนได้เลย ให้แสดงข้อความแจ้งเตือน
            $_SESSION['error_message'] = "คุณไม่มีสิทธิ์จัดการเนื้อหาภาษาใดๆ กรุณาติดต่อผู้ดูแลระบบ";
            header("Location: dashboard.php");
            exit;
        }
    }
}

// สำหรับแอดมิน ตรวจสอบว่าภาษาที่เลือกมีอยู่ในระบบหรือไม่
if (!in_array($language, $allowed_languages)) {
    $language = 'html';
}

// ดำเนินการจัดการกับฟอร์ม
$message = '';
$messageType = '';

// จัดการกับการเพิ่ม/แก้ไขหมวดหมู่
if (isset($_POST['add_section'])) {
    $title = $_POST['section_title'];
    $description = $_POST['section_description'];
    $order = isset($_POST['section_order']) ? intval($_POST['section_order']) : 0;
    
    $stmt = $pdo->prepare("INSERT INTO content_sections (language, title, description, order_num) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$language, $title, $description, $order])) {
        $message = "เพิ่มหมวดหมู่ใหม่สำเร็จ";
        $messageType = "success";
    } else {
        $message = "เกิดข้อผิดพลาดในการเพิ่มหมวดหมู่";
        $messageType = "danger";
    }
}

// จัดการกับการเพิ่ม/แก้ไขเนื้อหา
if (isset($_POST['add_content'])) {
    $section_id = $_POST['section_id'];
    $title = $_POST['content_title'];
    $content = $_POST['content_html'];
    $order = isset($_POST['content_order']) ? intval($_POST['content_order']) : 0;
    
    $stmt = $pdo->prepare("INSERT INTO content_materials (language, section_id, title, content, order_num, user_id) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$language, $section_id, $title, $content, $order, $_SESSION['user_id']])) {
        $message = "เพิ่มเนื้อหาใหม่สำเร็จ";
        $messageType = "success";
        
        // จัดการกับไฟล์แนบ (ถ้ามี)
        if (!empty($_FILES['content_files']['name'][0])) {
            $material_id = $pdo->lastInsertId();
            $upload_dir = 'uploads/materials/';
            
            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            foreach ($_FILES['content_files']['name'] as $key => $filename) {
                if ($_FILES['content_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['content_files']['tmp_name'][$key];
                    $filetype = $_FILES['content_files']['type'][$key];
                    $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $filename);
                    $filepath = $upload_dir . $safe_filename;
                    
                    if (move_uploaded_file($tmp_name, $filepath)) {
                        $stmt = $pdo->prepare("INSERT INTO content_files (material_id, filename, filepath, filetype, user_id) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$material_id, $filename, $filepath, $filetype, $_SESSION['user_id']]);
                    }
                }
            }
        }
    } else {
        $message = "เกิดข้อผิดพลาดในการเพิ่มเนื้อหา";
        $messageType = "danger";
    }
}

// จัดการกับการแก้ไขเนื้อหา
// Replace the existing edit_content handler (approximately lines 120-193) with this updated code
if (isset($_POST['edit_content'])) {
    $content_id = $_POST['content_id'];
    $title = $_POST['content_title'];
    $content = $_POST['content_html'];
    $section_id = $_POST['section_id'];
    $order = isset($_POST['content_order']) ? intval($_POST['content_order']) : 0;
    
    try {
        // First update the main content data without starting a transaction
        // This separates the content update from file handling
        $stmt = $pdo->prepare("UPDATE content_materials SET title = ?, content = ?, section_id = ?, order_num = ? WHERE id = ?");
        $stmt->execute([$title, $content, $section_id, $order, $content_id]);
        
        // Only process files if there are actually new files uploaded
        // Check if files array contains actual uploads (not empty entries)
        $has_file_uploads = false;
        
        if (isset($_FILES['content_files']) && isset($_FILES['content_files']['name'])) {
            foreach ($_FILES['content_files']['name'] as $filename) {
                if (!empty($filename)) {
                    $has_file_uploads = true;
                    break;
                }
            }
        }
        
        // Process file uploads only if there are actual files
        if ($has_file_uploads) {
            $upload_dir = 'uploads/materials/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Process each file individually
            foreach ($_FILES['content_files']['name'] as $key => $filename) {
                // Only process if a file was actually uploaded
                if (!empty($filename) && $_FILES['content_files']['error'][$key] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['content_files']['tmp_name'][$key];
                    $filetype = $_FILES['content_files']['type'][$key];
                    
                    // Create a safe filename to prevent security issues
                    $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $filename);
                    $filepath = $upload_dir . $safe_filename;
                    
                    // Upload the file
                    if (move_uploaded_file($tmp_name, $filepath)) {
                        // Only insert into database if file upload was successful
                        $stmt = $pdo->prepare("INSERT INTO content_files (material_id, filename, filepath, filetype, user_id) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$content_id, $filename, $filepath, $filetype, $_SESSION['user_id']]);
                    }
                }
            }
        }
        
        $message = "อัปเดตเนื้อหาสำเร็จ";
        $messageType = "success";
    } 
    catch (Exception $e) {
        $message = "เกิดข้อผิดพลาดในการอัปเดตเนื้อหา: " . $e->getMessage();
        $messageType = "danger";
    }
}

// จัดการกับการลบไฟล์
if (isset($_POST['delete_file'])) {
    $file_id = $_POST['file_id'];
    
    // ดึงข้อมูลไฟล์
    $stmt = $pdo->prepare("SELECT filepath FROM content_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $filepath = $stmt->fetchColumn();
    
    // ลบไฟล์จากระบบไฟล์
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // ลบข้อมูลจากฐานข้อมูล
    $stmt = $pdo->prepare("DELETE FROM content_files WHERE id = ?");
    if ($stmt->execute([$file_id])) {
        $message = "ลบไฟล์สำเร็จ";
        $messageType = "success";
    } else {
        $message = "เกิดข้อผิดพลาดในการลบไฟล์";
        $messageType = "danger";
    }
}

// จัดการกับการลบเนื้อหา
if (isset($_POST['delete_content'])) {
    $content_id = $_POST['content_id'];
    
    // ดึงข้อมูลไฟล์ทั้งหมดที่เกี่ยวข้อง
    $stmt = $pdo->prepare("SELECT filepath FROM content_files WHERE material_id = ?");
    $stmt->execute([$content_id]);
    $filepaths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // ลบไฟล์จากระบบไฟล์
    foreach ($filepaths as $filepath) {
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    
    // ลบข้อมูลจากฐานข้อมูล (การลบ content_materials จะลบ content_files ด้วยเนื่องจาก CASCADE)
    $stmt = $pdo->prepare("DELETE FROM content_materials WHERE id = ?");
    if ($stmt->execute([$content_id])) {
        $message = "ลบเนื้อหาสำเร็จ";
        $messageType = "success";
    } else {
        $message = "เกิดข้อผิดพลาดในการลบเนื้อหา";
        $messageType = "danger";
    }
}

// จัดการกับการลบหมวดหมู่
if (isset($_POST['delete_section'])) {
    $section_id = $_POST['section_id'];
    
    // ดึงข้อมูล material_id ทั้งหมด
    $stmt = $pdo->prepare("SELECT id FROM content_materials WHERE section_id = ?");
    $stmt->execute([$section_id]);
    $material_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // ลบไฟล์แนบทั้งหมด
    foreach ($material_ids as $material_id) {
        $stmt = $pdo->prepare("SELECT filepath FROM content_files WHERE material_id = ?");
        $stmt->execute([$material_id]);
        $filepaths = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($filepaths as $filepath) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
    
    // ลบข้อมูลจากฐานข้อมูล (การลบ content_sections จะลบ content_materials และ content_files ด้วยเนื่องจาก CASCADE)
    $stmt = $pdo->prepare("DELETE FROM content_sections WHERE id = ?");
    if ($stmt->execute([$section_id])) {
        $message = "ลบหมวดหมู่สำเร็จ";
        $messageType = "success";
    } else {
        $message = "เกิดข้อผิดพลาดในการลบหมวดหมู่";
        $messageType = "danger";
    }
}

// ดึงข้อมูลหมวดหมู่ทั้งหมดของภาษาที่เลือก
$stmt = $pdo->prepare("SELECT * FROM content_sections WHERE language = ? ORDER BY order_num ASC");
$stmt->execute([$language]);
$sections = $stmt->fetchAll();

// ตรวจสอบการแก้ไข
$editing_content = false;
$content_data = null;
$content_files = [];

if (isset($_GET['edit_content']) && is_numeric($_GET['edit_content'])) {
    $content_id = $_GET['edit_content'];
    $stmt = $pdo->prepare("SELECT * FROM content_materials WHERE id = ?");
    $stmt->execute([$content_id]);
    $content_data = $stmt->fetch();
    
    if ($content_data) {
        $editing_content = true;
        
        // ดึงข้อมูลไฟล์แนบ
        $stmt = $pdo->prepare("SELECT * FROM content_files WHERE material_id = ?");
        $stmt->execute([$content_id]);
        $content_files = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>จัดการเนื้อหาสื่อการสอน - DevLab</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&family=Noto+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <!-- เพิ่ม CSS สำหรับ TinyMCE -->
    <style>
    .tox-tinymce {
        border-radius: 0.375rem;
    }

    .section-card {
        transition: all 0.3s ease;
    }

    .section-card:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    </style>
    <!-- เพิ่ม TinyMCE -->
    <script src="js/tinymce/tinymce.min.js"></script>
    <script>
    tinymce.init({
        selector: '#content_html',
        license_key: 'gpl',
        height: 500,
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount',
        toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat'
    });
    </script>
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
                    <h1 class="text-2xl font-bold">จัดการเนื้อหาสื่อการสอน <?php echo strtoupper($language); ?></h1>
                </div>
                <div class="flex items-center gap-4">
                    <?php 
                    // กรองภาษาที่แสดงตามสิทธิ์ของผู้ใช้
                    $display_languages = $isAdmin ? $allowed_languages : $teacherAllowedLanguages;
                    
                    foreach ($display_languages as $lang): 
                    ?>
                    <a href="manage_content.php?language=<?php echo $lang; ?>"
                        class="<?php echo $language === $lang ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700'; ?> px-4 py-2 rounded hover:opacity-90">
                        <?php echo strtoupper($lang); ?>
                    </a>
                    <?php endforeach; ?>
                    <a href="dashboard.php" class="text-blue-500 hover:underline"> กลับสู่หน้าหลัก</a>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">ออกจากระบบ</a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="mt-24 mb-10">
            <?php if (!empty($message)): ?>
            <div class="bg-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-100 border-l-4 border-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-500 text-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-700 p-4 mb-6"
                role="alert">
                <p><?php echo $message; ?></p>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Section 1: หมวดหมู่ทั้งหมด -->
                <div class="bg-white rounded-lg shadow-md p-6 col-span-1">
                    <h2 class="text-xl font-bold mb-4">หมวดหมู่ทั้งหมด</h2>

                    <!-- ฟอร์มเพิ่มหมวดหมู่ -->
                    <form method="post" class="mb-6">
                        <div class="mb-4">
                            <label for="section_title"
                                class="block text-sm font-medium text-gray-700">ชื่อหมวดหมู่</label>
                            <input type="text" id="section_title" name="section_title" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-300">
                        </div>
                        <div class="mb-4">
                            <label for="section_description"
                                class="block text-sm font-medium text-gray-700">คำอธิบาย</label>
                            <textarea id="section_description" name="section_description" rows="3"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-300"></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="section_order" class="block text-sm font-medium text-gray-700">ลำดับ</label>
                            <input type="number" id="section_order" name="section_order" value="0" min="0"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-300">
                        </div>
                        <button type="submit" name="add_section"
                            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 w-full">
                            เพิ่มหมวดหมู่ใหม่
                        </button>
                    </form>

                    <!-- รายการหมวดหมู่ -->
                    <div class="space-y-4">
                        <?php if (empty($sections)): ?>
                        <p class="text-gray-500 text-center py-3">ยังไม่มีหมวดหมู่</p>
                        <?php else: ?>
                        <?php foreach ($sections as $section): ?>
                        <div class="section-card border rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold"><?php echo htmlspecialchars($section['title']); ?></h3>
                                    <p class="text-sm text-gray-600">
                                        <?php echo htmlspecialchars($section['description']); ?></p>
                                    <p class="text-xs text-gray-500 mt-1">ลำดับ: <?php echo $section['order_num']; ?>
                                    </p>
                                </div>
                                <div class="flex flex-col space-y-2">
                                    <a href="#"
                                        onclick="showAddContentForm(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars(addslashes($section['title'])); ?>')"
                                        class="text-blue-500 text-sm hover:underline">เพิ่มเนื้อหา</a>
                                    <form method="post"
                                        onsubmit="return confirm('คุณแน่ใจหรือว่าต้องการลบหมวดหมู่นี้? การดำเนินการนี้จะลบเนื้อหาทั้งหมดในหมวดหมู่ด้วย');">
                                        <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
                                        <button type="submit" name="delete_section"
                                            class="text-red-500 text-sm hover:underline">ลบหมวดหมู่</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 2: เพิ่ม/แก้ไขเนื้อหา -->
                <div class="bg-white rounded-lg shadow-md p-6 col-span-2">
                    <h2 class="text-xl font-bold mb-4">
                        <?php echo $editing_content ? 'แก้ไขเนื้อหา' : 'เพิ่มเนื้อหาใหม่'; ?>
                    </h2>

                    <form method="post" enctype="multipart/form-data" id="contentForm"
                        style="<?php echo (!$editing_content && empty($_GET['section_id'])) ? 'display:none;' : ''; ?>">
                        <?php if ($editing_content): ?>
                        <input type="hidden" name="content_id" value="<?php echo $content_data['id']; ?>">
                        <input type="hidden" name="edit_content" value="1">
                        <?php else: ?>
                        <input type="hidden" name="add_content" value="1">
                        <?php endif; ?>

                        <div class="mb-4">
                            <label for="section_id" class="block text-sm font-medium text-gray-700">หมวดหมู่</label>
                            <select id="section_id" name="section_id" required
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-200">
                                <option value="">-- เลือกหมวดหมู่ --</option>
                                <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['id']; ?>"
                                    <?php echo ($editing_content && $content_data['section_id'] == $section['id']) || (!$editing_content && isset($_GET['section_id']) && $_GET['section_id'] == $section['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="content_title"
                                class="block text-sm font-medium text-gray-700">หัวข้อเนื้อหา</label>
                            <input type="text" id="content_title" name="content_title" required
                                value="<?php echo $editing_content ? htmlspecialchars($content_data['title']) : ''; ?>"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-200">
                        </div>

                        <div class="mb-4">
                            <label for="content_order" class="block text-sm font-medium text-gray-700">ลำดับ</label>
                            <input type="number" id="content_order" name="content_order"
                                value="<?php echo $editing_content ? $content_data['order_num'] : '0'; ?>" min="0"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 bg-gray-200">
                        </div>

                        <div class="mb-4">
                            <label for="content_html" class="block text-sm font-medium text-gray-700">เนื้อหา</label>
                            <textarea id="content_html"
                                name="content_html"><?php echo $editing_content ? $content_data['content'] : ''; ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">ไฟล์แนบ</label>
                            <input type="file" name="content_files[]" multiple
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3">
                            <p class="text-xs text-gray-500 mt-1">สามารถเลือกได้หลายไฟล์</p>
                        </div>

                        <?php if ($editing_content && !empty($content_files)): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700">ไฟล์แนบที่มีอยู่</label>
                            <div class="space-y-2 mt-2">
                                <?php foreach ($content_files as $file): ?>
                                <div class="flex justify-between items-center bg-gray-100 p-2 rounded">
                                    <div>
                                        <span class="text-sm"><?php echo htmlspecialchars($file['filename']); ?></span>
                                        <a href="<?php echo htmlspecialchars($file['filepath']); ?>" target="_blank"
                                            class="text-blue-500 text-xs ml-2 hover:underline">ดูไฟล์</a>
                                    </div>
                                    <button type="button" class="text-red-500 text-xs hover:underline"
                                        onclick="deleteFile(<?php echo $file['id']; ?>)">
                                        ลบไฟล์
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <script>
                        function deleteFile(fileId) {
                            if (confirm('คุณแน่ใจหรือว่าต้องการลบไฟล์นี้?')) {
                                // สร้างฟอร์มใหม่ที่แยกออกมา
                                var form = document.createElement('form');
                                form.method = 'post';
                                form.style.display = 'none';

                                var fileIdInput = document.createElement('input');
                                fileIdInput.type = 'hidden';
                                fileIdInput.name = 'file_id';
                                fileIdInput.value = fileId;

                                var deleteFileInput = document.createElement('input');
                                deleteFileInput.type = 'hidden';
                                deleteFileInput.name = 'delete_file';
                                deleteFileInput.value = '1';

                                form.appendChild(fileIdInput);
                                form.appendChild(deleteFileInput);

                                document.body.appendChild(form);
                                form.submit();
                            }
                        }
                        </script>



                        <div class=" flex justify-between mt-6">
                            <button type="button" onclick="cancelForm()"
                                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                                ยกเลิก
                            </button>
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                                <?php echo $editing_content ? 'อัปเดตเนื้อหา' : 'บันทึกเนื้อหา'; ?>
                            </button>
                        </div>
                    </form>

                    <!-- แสดงเมื่อไม่มีการแก้ไขและไม่ได้เลือกหมวดหมู่ -->
                    <div id="contentList"
                        class="<?php echo (!$editing_content && empty($_GET['section_id'])) ? '' : 'hidden'; ?>">
                        <h3 class="font-bold mb-4">เนื้อหาทั้งหมด</h3>

                        <?php
                        // ดึงข้อมูลเนื้อหาทั้งหมดของภาษาที่เลือก
                        $stmt = $pdo->prepare("
                            SELECT m.*, s.title as section_title 
                            FROM content_materials m
                            JOIN content_sections s ON m.section_id = s.id
                            WHERE m.language = ?
                            ORDER BY s.order_num, m.order_num
                        ");
                        $stmt->execute([$language]);
                        $materials = $stmt->fetchAll();
                        ?>

                        <?php if (empty($materials)): ?>
                        <p class="text-gray-500 text-center py-6">ยังไม่มีเนื้อหา กรุณาเลือกหมวดหมู่แล้วคลิก
                            "เพิ่มเนื้อหา"</p>
                        <?php else: ?>
                        <div class="space-y-4">
                            <?php 
                                $current_section = null;
                                foreach ($materials as $material): 
                                    if ($current_section !== $material['section_title']):
                                        $current_section = $material['section_title'];
                                ?>
                            <h4 class="font-semibold mt-4 bg-gray-100 p-2 rounded ">
                                <?php echo htmlspecialchars($material['section_title']); ?>
                            </h4>
                            <?php endif; ?>

                            <div class="border rounded-lg p-4 hover:bg-gray-50 transition-colors ">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h5 class="font-medium"><?php echo htmlspecialchars($material['title']); ?></h5>
                                        <p class="text-xs text-gray-500 mt-1">
                                            อัปเดตล่าสุด:
                                            <?php echo date('d/m/Y H:i', strtotime($material['updated_at'])); ?>
                                            | ลำดับ: <?php echo $material['order_num']; ?>
                                        </p>
                                    </div>
                                    <div class="flex space-x-3">
                                        <a href="manage_content.php?language=<?php echo $language; ?>&edit_content=<?php echo $material['id']; ?>"
                                            class="text-blue-500 text-sm hover:underline">แก้ไข</a>
                                        <form method="post"
                                            onsubmit="return confirm('คุณแน่ใจหรือว่าต้องการลบเนื้อหานี้?');">
                                            <input type="hidden" name="content_id"
                                                value="<?php echo $material['id']; ?>">
                                            <button type="submit" name="delete_content"
                                                class="text-red-500 text-sm hover:underline">ลบ</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showAddContentForm(sectionId, sectionTitle) {
        document.getElementById('contentForm').style.display = 'block';
        document.getElementById('contentList').classList.add('hidden');

        // ตั้งค่า select หมวดหมู่
        const selectElement = document.getElementById('section_id');
        for (let i = 0; i < selectElement.options.length; i++) {
            if (selectElement.options[i].value == sectionId) {
                selectElement.selectedIndex = i;
                break;
            }
        }

        // เลื่อนไปที่ฟอร์ม
        document.getElementById('contentForm').scrollIntoView({
            behavior: 'smooth'
        });
    }

    function cancelForm() {
        // ล้างฟอร์ม
        document.getElementById('contentForm').reset();

        // ซ่อนฟอร์ม แสดงรายการ
        document.getElementById('contentForm').style.display = 'none';
        document.getElementById('contentList').classList.remove('hidden');

        // ถ้ากำลังแก้ไข ให้กลับไปหน้าหลัก
        <?php if ($editing_content): ?>
        window.location.href = 'manage_content.php?language=<?php echo $language; ?>';
        <?php endif; ?>
    }
    </script>
</body>

</html>