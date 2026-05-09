<?php
// filepath: api/admin/register.php
require_once 'db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $bio = trim($input['bio'] ?? '');
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    // Validation
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = 'First name is required';
    }
    if (empty($last_name)) {
        $errors[] = 'Last name is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        exit;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert admin
    $stmt = $pdo->prepare("INSERT INTO admin (first_name, last_name, email, phone, bio, password) VALUES (?, ?, ?, ?, ?, ?)");
    
    try {
        $stmt->execute([$first_name, $last_name, $email, $phone, $bio, $hashed_password]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin registered successfully',
            'admin_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}