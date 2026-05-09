<?php
// filepath: api/admin/login.php
require_once 'db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password, bio FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password'])) {
        // Remove password from response
        unset($admin['password']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'admin' => $admin
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}