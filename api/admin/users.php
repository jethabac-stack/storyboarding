<?php
// filepath: api/admin/users.php
require_once 'db.php';

$method = $_SERVER['REQUEST_METHOD'];

// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get all users
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'list') {
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, email, bio, phone, created_at 
            FROM admin 
            ORDER BY created_at DESC
        ");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users
        ]);
        exit;
    }
    
    // Get single user by ID
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, bio, phone, created_at FROM admin WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
        exit;
    }
    
    // Default: get all users
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, bio, phone, created_at FROM admin ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    exit;
}

// Create new user
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO admin (first_name, last_name, email, bio, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['bio'] ?? '',
            $hashedPassword
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error creating user: ' . $e->getMessage()]);
    }
    exit;
}

// Update user
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    try {
        if (!empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin SET first_name = ?, last_name = ?, email = ?, bio = ?, password = ? WHERE id = ?");
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['bio'] ?? '',
                $hashedPassword,
                $data['id']
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE admin SET first_name = ?, last_name = ?, email = ?, bio = ? WHERE id = ?");
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['bio'] ?? '',
                $data['id']
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
    }
    exit;
}

// Delete user
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);