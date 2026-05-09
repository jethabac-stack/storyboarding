<?php
// filepath: api/admin/profile.php
require_once 'db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $admin_id = $input['admin_id'] ?? null;
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $bio = trim($input['bio'] ?? '');

    if (!$admin_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Admin ID is required']);
        exit;
    }

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

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    // Check if email is already taken by another admin
    $stmt = $pdo->prepare("SELECT id FROM admin WHERE email = ? AND id != ?");
    $stmt->execute([$email, $admin_id]);
    
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already in use by another admin']);
        exit;
    }

    // Update admin profile
    $stmt = $pdo->prepare("UPDATE admin SET first_name = ?, last_name = ?, email = ?, phone = ?, bio = ? WHERE id = ?");
    
    try {
        $stmt->execute([$first_name, $last_name, $email, $phone, $bio, $admin_id]);
        
        // Fetch updated admin
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, bio, created_at FROM admin WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'admin' => $admin
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get admin by ID
    $admin_id = $_GET['id'] ?? null;
    
    if (!$admin_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Admin ID is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, phone, bio, created_at FROM admin WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo json_encode(['success' => true, 'admin' => $admin]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Admin not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}