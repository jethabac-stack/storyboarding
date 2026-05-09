<?php
// filepath: api/students/login.php
require_once '../admin/db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    // Check if student exists
    $stmt = $pdo->prepare("SELECT s.*, sec.name as section_name FROM students s LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.email = ?");
    $stmt->execute([$email]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student || !password_verify($password, $student['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        exit;
    }

    // Return student data (without password)
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $student['id'],
            'name' => $student['name'],
            'email' => $student['email'],
            'section_id' => $student['section_id'],
            'section_name' => $student['section_name'] ?? '',
            'grade' => $student['grade']
        ]
    ]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}