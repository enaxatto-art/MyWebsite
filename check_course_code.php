<?php
// check_course_code.php — AJAX endpoint to check if course code exists
require_once 'database.php';

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exists' => false]);
    exit;
}

$course_code = trim($_POST['course_code'] ?? '');
$exclude_id = (int)($_POST['exclude_id'] ?? 0);

if(empty($course_code)) {
    echo json_encode(['exists' => false]);
    exit;
}

// Check if course code exists
$sql = "SELECT id FROM courses WHERE course_code = ?";
$params = [$course_code];
$param_types = 's';

if($exclude_id > 0) {
    $sql .= " AND id != ?";
    $params[] = $exclude_id;
    $param_types .= 'i';
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode(['exists' => $result->num_rows > 0]);
?>

