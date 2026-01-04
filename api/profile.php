<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getProfile();
        break;
    case 'update':
        updateProfile();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function getProfile() {
    global $conn;
    
    $user_id = intval($_GET['user_id'] ?? $_SESSION['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        sendResponse(false, 'User ID required');
    }
    
    // Get user basic info
    $stmt = $conn->prepare("SELECT id, name, email, role, phone, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'User not found');
    }
    
    $user = $result->fetch_assoc();
    
    // Get profile details
    $stmt = $conn->prepare("SELECT * FROM profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $profile = [];
    if ($result->num_rows > 0) {
        $profile = $result->fetch_assoc();
        // Remove duplicate id/user_id if you want, but likely fine
    }
    
    $data = array_merge($user, $profile);
    
    sendResponse(true, 'Profile retrieved', $data);
}

function updateProfile() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Please login first');
    }
    
    $user_id = $_SESSION['user_id'];
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $github = trim($_POST['github'] ?? '');
    $linkedin = trim($_POST['linkedin'] ?? '');
    
    if (empty($name)) {
        sendResponse(false, 'Name is required');
    }
    
    // Update users table
    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $phone, $user_id);
    
    if (!$stmt->execute()) {
        sendResponse(false, 'Failed to update user info');
    }
    
    // Check if profile exists
    $stmt = $conn->prepare("SELECT id FROM profiles WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Update
        $stmt = $conn->prepare("UPDATE profiles SET bio=?, address=?, website=?, github=?, linkedin=? WHERE user_id=?");
        $stmt->bind_param("sssssi", $bio, $address, $website, $github, $linkedin, $user_id);
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO profiles (user_id, bio, address, website, github, linkedin) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $bio, $address, $website, $github, $linkedin);
    }
    
    if ($stmt->execute()) {
        $_SESSION['user_name'] = $name; // Update session name
        sendResponse(true, 'Profile updated successfully');
    } else {
        sendResponse(false, 'Failed to update profile details');
    }
}
?>
