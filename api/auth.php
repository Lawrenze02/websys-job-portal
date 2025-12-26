<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'register':
        register();
        break;
    case 'login':
        login();
        break;
    case 'logout':
        logout();
        break;
    case 'check':
        checkAuth();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function register() {
    global $conn;
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'job_seeker';
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        sendResponse(false, 'All fields are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse(false, 'Invalid email format');
    }
    
    if (strlen($password) < 6) {
        sendResponse(false, 'Password must be at least 6 characters');
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, 'Email already registered');
    }
    
    // Hash password and insert
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
    
    if ($stmt->execute()) {
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = $role;
        
        sendResponse(true, 'Registration successful', [
            'id' => $_SESSION['user_id'],
            'name' => $name,
            'role' => $role
        ]);
    } else {
        sendResponse(false, 'Registration failed');
    }
}

function login() {
    global $conn;
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendResponse(false, 'Email and password are required');
    }
    
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Invalid email or password');
    }
    
    $user = $result->fetch_assoc();
    
    if (!password_verify($password, $user['password'])) {
        sendResponse(false, 'Invalid email or password');
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_role'] = $user['role'];
    
    sendResponse(true, 'Login successful', [
        'id' => $user['id'],
        'name' => $user['name'],
        'role' => $user['role']
    ]);
}

function logout() {
    session_destroy();
    sendResponse(true, 'Logged out successfully');
}

function checkAuth() {
    if (isset($_SESSION['user_id'])) {
        sendResponse(true, 'Authenticated', [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'role' => $_SESSION['user_role']
        ]);
    } else {
        sendResponse(false, 'Not authenticated');
    }
}
?>
