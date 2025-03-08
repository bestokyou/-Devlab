<?php
// lesson_data.php
function getLesson($pdo, $language, $lesson_id) {
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE language = ? AND id = ?");
    $stmt->execute([$language, $lesson_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function checkLessonExists($pdo, $language, $lesson_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE language = ? AND id = ?");
    $stmt->execute([$language, $lesson_id]);
    return $stmt->fetchColumn() > 0;
}

function getTotalLessons($pdo, $language) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE language = ?");
    $stmt->execute([$language]);
    return $stmt->fetchColumn();
}

function getNextLesson($pdo, $language, $current_order) {
    $stmt = $pdo->prepare("
        SELECT id 
        FROM lessons 
        WHERE language = ? AND order_num > ? 
        ORDER BY order_num ASC 
        LIMIT 1
    ");
    $stmt->execute([$language, $current_order]);
    return $stmt->fetchColumn();
}

function getPrevLesson($pdo, $language, $current_order) {
    $stmt = $pdo->prepare("
        SELECT id 
        FROM lessons 
        WHERE language = ? AND order_num < ? 
        ORDER BY order_num DESC 
        LIMIT 1
    ");
    $stmt->execute([$language, $current_order]);
    return $stmt->fetchColumn();
}
?>