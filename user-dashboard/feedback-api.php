<?php
session_start();
header('Content-Type: application/json');

// Database connection
$conn = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'submit_feedback':
        submitFeedback($conn);
        break;
    
    case 'get_user_feedback':
        getUserFeedback($conn);
        break;
    
    case 'update_status':
        updateFeedbackStatus($conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();

// Function to submit new feedback
function submitFeedback($conn) {
    // Get user ID from session (if logged in)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Get POST data
    $street = $_POST['street'] ?? '';
    $barangay = $_POST['barangay'] ?? '';
    $category = $_POST['category'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    
    // Validate required fields
    if (empty($street) || empty($barangay) || empty($category) || empty($feedback)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Handle photo upload (optional)
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/feedback/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('feedback_') . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
            $photo_path = 'uploads/feedback/' . $file_name;
        }
    }
    
    // Insert feedback into database
    $sql = "INSERT INTO user_feedback (user_id, street, barangay, category, feedback, photo_path, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isssss', $user_id, $street, $barangay, $category, $feedback, $photo_path);
    
    if ($stmt->execute()) {
        $feedback_id = $stmt->insert_id;
        
        // Also save to prioritization module for backward compatibility
        $prioritization_data = [
            'id' => 'feedback_' . $feedback_id,
            'name' => 'User Feedback',
            'email' => '',
            'type' => 'Suggestion',
            'subject' => getCategorySubject($category),
            'description' => $feedback,
            'category' => mapCategoryToPrioritization($category),
            'location' => "$street, $barangay",
            'urgency' => 'Medium',
            'status' => 'Pending',
            'date' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode([
            'success' => true, 
            'message' => 'Thank you for your feedback! Your submission has been received.',
            'feedback_id' => $feedback_id,
            'prioritization_data' => $prioritization_data
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit feedback']);
    }
    
    $stmt->close();
}

// Function to get user's feedback
function getUserFeedback($conn) {
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // If no user_id in session, try to get all feedback (for guest users using session ID)
    $session_id = session_id();
    
    $sql = "SELECT id, street, barangay, category, feedback, photo_path, status, admin_response, created_at, updated_at 
            FROM user_feedback ";
    
    if ($user_id) {
        $sql .= "WHERE user_id = ? ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $user_id);
    } else {
        // For guest users, get all feedback (they can see community feedback)
        $sql .= "ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $feedbacks = [];
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
    
    echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);
    $stmt->close();
}

// Function to update feedback status (admin only)
function updateFeedbackStatus($conn) {
    // Check if user is admin (you may need to adjust this check)
    if (!isset($_SESSION['employee_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $feedback_id = intval($_POST['feedback_id'] ?? 0);
    $status = $conn->real_escape_string($_POST['status'] ?? '');
    $admin_response = $conn->real_escape_string($_POST['admin_response'] ?? '');
    
    if ($feedback_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid feedback ID']);
        return;
    }
    
    $sql = "UPDATE user_feedback SET status = ?, admin_response = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssi', $status, $admin_response, $feedback_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Feedback updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update feedback']);
    }
    
    $stmt->close();
}

// Helper function to get category subject
function getCategorySubject($category) {
    $subjects = [
        'transportation' => 'Transportation Infrastructure Feedback',
        'energy' => 'Energy Infrastructure Feedback',
        'water-waste' => 'Water & Waste Management Feedback',
        'social-infrastructure' => 'Social Infrastructure Feedback',
        'public-buildings' => 'Public Buildings Feedback'
    ];
    return $subjects[$category] ?? 'Infrastructure Feedback';
}

// Helper function to map category
function mapCategoryToPrioritization($category) {
    $mapping = [
        'transportation' => 'Roads',
        'energy' => 'Electricity',
        'water-waste' => 'Water',
        'social-infrastructure' => 'Buildings',
        'public-buildings' => 'Buildings'
    ];
    return $mapping[$category] ?? 'Other';
}
?>