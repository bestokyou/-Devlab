<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';
if (empty($code)) {
    echo json_encode(['error' => 'No code provided']);
    exit;
}
// Capture output
ob_start();
try {
    eval('?>' . $code);
$output = ob_get_clean();
echo json_encode(['output' => $output]);
} catch (Throwable $e) {
ob_end_clean();
echo json_encode(['error' => $e->getMessage()]);
}
?>