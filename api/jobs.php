<?php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listJobs();
        break;
    case 'get':
        getJob();
        break;
    case 'create':
        createJob();
        break;
    case 'update':
        updateJob();
        break;
    case 'delete':
        deleteJob();
        break;
    case 'search':
        searchJobs();
        break;
    case 'my-jobs':
        getMyJobs();
        break;
    default:
        sendResponse(false, 'Invalid action');
}

function listJobs() {
    global $conn;
    
    $sql = "SELECT j.*, u.name as employer_name 
            FROM jobs j 
            JOIN users u ON j.employer_id = u.id 
            WHERE j.is_active = 1 
            ORDER BY j.created_at DESC";
    
    $result = $conn->query($sql);
    $jobs = [];
    
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    
    sendResponse(true, 'Jobs retrieved', $jobs);
}

function getJob() {
    global $conn;
    
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        sendResponse(false, 'Invalid job ID');
    }
    
    $stmt = $conn->prepare("SELECT j.*, u.name as employer_name, u.email as employer_email 
                            FROM jobs j 
                            JOIN users u ON j.employer_id = u.id 
                            WHERE j.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Job not found');
    }
    
    sendResponse(true, 'Job retrieved', $result->fetch_assoc());
}

function createJob() {
    global $conn;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employer') {
        sendResponse(false, 'Only employers can post jobs');
    }
    
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $job_type = $_POST['job_type'] ?? 'full-time';
    $salary_min = floatval($_POST['salary_min'] ?? 0);
    $salary_max = floatval($_POST['salary_max'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    
    if (empty($title) || empty($company) || empty($location) || empty($description)) {
        sendResponse(false, 'Title, company, location, and description are required');
    }
    
    $stmt = $conn->prepare("INSERT INTO jobs (employer_id, title, company, location, job_type, salary_min, salary_max, description, requirements) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssddss", $_SESSION['user_id'], $title, $company, $location, $job_type, $salary_min, $salary_max, $description, $requirements);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Job posted successfully', ['id' => $conn->insert_id]);
    } else {
        sendResponse(false, 'Failed to post job');
    }
}

function updateJob() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Please login first');
    }
    
    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $job_type = $_POST['job_type'] ?? 'full-time';
    $salary_min = floatval($_POST['salary_min'] ?? 0);
    $salary_max = floatval($_POST['salary_max'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    
    // Verify ownership
    $stmt = $conn->prepare("SELECT employer_id FROM jobs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Job not found');
    }
    
    $job = $result->fetch_assoc();
    if ($job['employer_id'] != $_SESSION['user_id']) {
        sendResponse(false, 'You can only edit your own jobs');
    }
    
    $stmt = $conn->prepare("UPDATE jobs SET title=?, company=?, location=?, job_type=?, salary_min=?, salary_max=?, description=?, requirements=?, is_active=? WHERE id=?");
    $stmt->bind_param("ssssddssii", $title, $company, $location, $job_type, $salary_min, $salary_max, $description, $requirements, $is_active, $id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Job updated successfully');
    } else {
        sendResponse(false, 'Failed to update job');
    }
}

function deleteJob() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Please login first');
    }
    
    $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
    
    // Verify ownership
    $stmt = $conn->prepare("SELECT employer_id FROM jobs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendResponse(false, 'Job not found');
    }
    
    $job = $result->fetch_assoc();
    if ($job['employer_id'] != $_SESSION['user_id']) {
        sendResponse(false, 'You can only delete your own jobs');
    }
    
    $stmt = $conn->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Job deleted successfully');
    } else {
        sendResponse(false, 'Failed to delete job');
    }
}

function searchJobs() {
    global $conn;
    
    $keyword = trim($_GET['keyword'] ?? '');
    $location = trim($_GET['location'] ?? '');
    $job_type = $_GET['job_type'] ?? '';
    
    $sql = "SELECT j.*, u.name as employer_name 
            FROM jobs j 
            JOIN users u ON j.employer_id = u.id 
            WHERE j.is_active = 1";
    $params = [];
    $types = "";
    
    if (!empty($keyword)) {
        $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.company LIKE ?)";
        $keyword = "%$keyword%";
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= "sss";
    }
    
    if (!empty($location)) {
        $sql .= " AND j.location LIKE ?";
        $params[] = "%$location%";
        $types .= "s";
    }
    
    if (!empty($job_type)) {
        $sql .= " AND j.job_type = ?";
        $params[] = $job_type;
        $types .= "s";
    }
    
    $sql .= " ORDER BY j.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    
    sendResponse(true, 'Search results', $jobs);
}

function getMyJobs() {
    global $conn;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employer') {
        sendResponse(false, 'Only employers can view their jobs');
    }
    
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE employer_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    
    sendResponse(true, 'Your jobs', $jobs);
}
?>
