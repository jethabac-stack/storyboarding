<?php
// filepath: api/admin/change_password.php
require_once 'db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = $input['admin_id'] ?? null;
    $current_password = $input['current_password'] ?? '';
    $new_password = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    if (!$admin_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Admin ID is required']);
        exit;
    }

    // Validation
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = 'Current password is required';
    }
    if (empty($new_password)) {
        $errors[] = 'New password is required';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters';
    }
    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    // Get current admin
    $stmt = $pdo->prepare("SELECT password FROM admin WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($current_password, $admin['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Current password is incorrect']);
        exit;
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $stmt = $pdo->prepare("UPDATE admin SET password = ? WHERE id = ?");
    
    try {
        $stmt->execute([$hashed_password, $admin_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Password change failed']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}