<?php
// filepath: api/students/register.php
require_once '../admin/db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $section_id = intval($input['section_id'] ?? 0);
    $grade = trim($input['grade'] ?? '');
    $password = $input['password'] ?? '';

    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($section_id)) {
        $errors[] = 'Section is required';
    }
    if (empty($grade)) {
        $errors[] = 'Grade is required';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['error' => implode(', ', $errors)]);
        exit;
    }

    // Check if email already exists in students table
    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        exit;
    }

    // Check if section exists
    $stmt = $pdo->prepare("SELECT id FROM sections WHERE id = ?");
    $stmt->execute([$section_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid section']);
        exit;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert student
    $stmt = $pdo->prepare("INSERT INTO students (name, email, section_id, grade, password) VALUES (?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute([$name, $email, $section_id, $grade, $hashed_password]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Student registered successfully',
            'student_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Registration error: " . $e->getMessage());
        echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}