<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'apply':
        applyJob();
        break;
    case 'my-applications':
        getMyApplications();
        break;
    case 'job-applications':
        getJobApplications();
        break;
    case 'update-status':
        updateStatus();
        break;
    case 'check':
        checkApplication();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function applyJob() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Please login to apply');
    }
    
    if ($_SESSION['user_role'] !== 'job_seeker') {
        sendResponse(false, 'Only job seekers can apply');
    }
    
    $job_id = intval($_POST['job_id'] ?? 0);
    $cover_letter = trim($_POST['cover_letter'] ?? '');
    
    if ($job_id <= 0) {
        sendResponse(false, 'Invalid job ID');
    }
    
    // Check if job exists
    $stmt = $conn->prepare("SELECT id FROM jobs WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        sendResponse(false, 'Job not found or no longer active');
    }
    
    // Check for duplicate application
    $stmt = $conn->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        sendResponse(false, 'You have already applied for this job');
    }
    
    // Handle file upload
    $resume_path = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/resumes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx'];
        
        if (!in_array($file_ext, $allowed)) {
            sendResponse(false, 'Only PDF, DOC, DOCX files are allowed');
        }
        
        $resume_path = $upload_dir . uniqid('resume_') . '.' . $file_ext;
        move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path);
    }
    
    $stmt = $conn->prepare("INSERT INTO applications (job_id, user_id, cover_letter, resume_path) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $job_id, $_SESSION['user_id'], $cover_letter, $resume_path);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Application submitted successfully');
    } else {
        sendResponse(false, 'Failed to submit application');
    }
}

function getMyApplications() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Please login first');
    }
    
    $stmt = $conn->prepare("SELECT a.*, j.title, j.company, j.location 
                            FROM applications a 
                            JOIN jobs j ON a.job_id = j.id 
                            WHERE a.user_id = ? 
                            ORDER BY a.created_at DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
    
    sendResponse(true, 'Your applications', $applications);
}

function getJobApplications() {
    global $conn;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employer') {
        sendResponse(false, 'Only employers can view applications');
    }
    
    $job_id = intval($_GET['job_id'] ?? 0);
    
    // Verify job ownership
    $stmt = $conn->prepare("SELECT employer_id FROM jobs WHERE id = ?");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Job not found');
    }
    
    $job = $result->fetch_assoc();
    if ($job['employer_id'] != $_SESSION['user_id']) {
        sendResponse(false, 'You can only view applications for your jobs');
    }
    
    $stmt = $conn->prepare("SELECT a.*, u.name, u.email, u.phone 
                            FROM applications a 
                            JOIN users u ON a.user_id = u.id 
                            WHERE a.job_id = ? 
                            ORDER BY a.created_at DESC");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
    
    sendResponse(true, 'Job applications', $applications);
}

function updateStatus() {
    global $conn;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employer') {
        sendResponse(false, 'Only employers can update status');
    }
    
    $application_id = intval($_POST['application_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    $valid_statuses = ['pending', 'reviewed', 'shortlisted', 'rejected'];
    if (!in_array($status, $valid_statuses)) {
        sendResponse(false, 'Invalid status');
    }
    
    // Verify the application belongs to employer's job
    $stmt = $conn->prepare("SELECT j.employer_id 
                            FROM applications a 
                            JOIN jobs j ON a.job_id = j.id 
                            WHERE a.id = ?");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Application not found');
    }
    
    $data = $result->fetch_assoc();
    if ($data['employer_id'] != $_SESSION['user_id']) {
        sendResponse(false, 'Unauthorized');
    }
    
    $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $application_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Status updated successfully');
    } else {
        sendResponse(false, 'Failed to update status');
    }
}

function checkApplication() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Not logged in');
    }
    
    $job_id = intval($_GET['job_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT id, status FROM applications WHERE job_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $job_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        sendResponse(true, 'Already applied', $result->fetch_assoc());
    } else {
        sendResponse(false, 'Not applied');
    }
}
?>
