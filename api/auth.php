<?php
require_once 'db.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// User registration
if ($method === 'POST' && $action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }
    
    $username = trim($data['username']);
    $email = trim($data['email']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $role = $data['role'] ?? 'student';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $role]);
        
        echo json_encode(['message' => 'User registered successfully', 'user_id' => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Username or email already exists']);
    }
    exit;
}

// User login
if ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing email or password']);
        exit;
    }
    
    $email = trim($data['email']);
    $password = $data['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Generate simple token (in production, use JWT)
        $token = bin2hex(random_bytes(32));
        
        echo json_encode([
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'token' => $token
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;
}

// Get user profile
if ($method === 'GET' && $action === 'profile') {
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo json_encode($user);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
    }
    exit;
}

// Default response
http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);