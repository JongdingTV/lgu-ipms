<?php
// Database connection
$conn = new mysqli('localhost:3307', 'root', '', 'lgu_ipms');
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $conn->query("SELECT * FROM projects ORDER BY priority DESC, created_at DESC");
    $projects = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
        $result->free();
    }
    
    echo json_encode($projects);
    exit;
}

// Handle feedback status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $feedback_id = intval($_POST['feedback_id']);
    $new_status = $conn->real_escape_string($_POST['new_status']);
    $conn->query("UPDATE feedback SET status='$new_status' WHERE id=$feedback_id");
    header('Location: project-prioritization.php');
    exit;
}

// Fetch feedback for display
$feedback_result = $conn->query("SELECT * FROM feedback ORDER BY date_submitted DESC");
$feedbacks = [];
if ($feedback_result) {
    while ($row = $feedback_result->fetch_assoc()) {
        $feedbacks[] = $row;
    }
    $feedback_result->free();
}

// Feedback summary stats
$totalInputs = count($feedbacks);
$criticalInputs = 0;
$highInputs = 0;
$pendingInputs = 0;
foreach ($feedbacks as $fb) {
    if (isset($fb['category']) && strtolower($fb['category']) === 'critical') $criticalInputs++;
    if (isset($fb['category']) && strtolower($fb['category']) === 'high') $highInputs++;
    if (isset($fb['status']) && strtolower($fb['status']) === 'pending') $pendingInputs++;
}

$conn->close();
?>
<!doctype html>
<html>
<head>
        <link rel="stylesheet" href="../assets/style.css" />
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Project Prioritization - LGU IPMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <a href="../project-registration/project_registration.php"><img src="../project-registration/list.png" class="nav-icon">Project Registration</a>
            <a href="../progress-monitoring/progress_monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            <a href="../contractors/contractors.php"><img src="../contractors/contractors.png" class="nav-icon">Contractors</a>
            <a href="project-prioritization.php" class="active"><img src="prioritization.png" alt="Priority Icon" class="nav-icon">Project Prioritization</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome <?php echo isset($_SESSION['employee_name']) ? $_SESSION['employee_name'] : 'Admin'; ?></span>
            <a href="../employee-login.php" class="nav-logout">Logout</a>
        </div>
        <div class="lgu-arrow-back">
            <a href="#" id="toggleSidebar">
                <img src="../dashboard/lgu-arrow-back.png" alt="Toggle sidebar">
            </a>
        </div>
    </header>

    <div class="toggle-btn" id="showSidebarBtn">
        <a href="#" id="toggleSidebarShow">
            <img src="../dashboard/lgu-arrow-right.png" alt="Show sidebar">
        </a>
    </div>

    <section class="main-content">
        <div class="dash-header">
            <h1>Project Prioritization</h1>
            <p>Review and prioritize citizen inputs for infrastructure project planning</p>
        </div>

        <div class="inputs-section" style="max-width:1100px;margin:40px auto 0;">
            <div class="card" style="background:#fff;border-radius:18px;box-shadow:0 4px 24px rgba(37,99,235,0.07);padding:40px 32px;">
                <h2 style="font-size:1.5rem;font-weight:700;color:#2563eb;margin-bottom:24px;letter-spacing:-1px;">User Feedback & Concerns</h2>
                <div class="table-wrap" style="overflow-x:auto;">
                    <table id="inputsTable" class="table" style="width:100%;border-collapse:separate;border-spacing:0;">
                        <thead style="background:#f1f5f9;">
                            <tr>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Date</th>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Name</th>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Type</th>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Subject</th>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Category</th>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Location</th>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Priority</th>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Status</th>
                                <th style="padding:14px 10px;font-weight:600;color:#1e3a8a;border-bottom:2px solid #2563eb;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($feedbacks as $fb): ?>
                            <tr>
                                <td style="padding:10px;"><?= isset($fb['date_submitted']) ? htmlspecialchars($fb['date_submitted']) : '-' ?></td>
                                <td style="padding:10px;"><?= isset($fb['user_name']) ? htmlspecialchars($fb['user_name']) : '-' ?></td>
                                <td style="padding:10px;">Feedback</td>
                                <td style="padding:10px;"><?= isset($fb['subject']) ? htmlspecialchars($fb['subject']) : '-' ?></td>
                                <td style="padding:10px;"><?= isset($fb['category']) ? htmlspecialchars($fb['category']) : '-' ?></td>
                                <td style="padding:10px;"><?= isset($fb['location']) ? htmlspecialchars($fb['location']) : '-' ?></td>
                                <td style="padding:10px;"><?= isset($fb['priority']) ? htmlspecialchars($fb['priority']) : '-' ?></td>
                                <td style="padding:10px;">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="feedback_id" value="<?= isset($fb['id']) ? $fb['id'] : '' ?>">
                                        <select name="new_status" style="padding:4px 8px;">
                                            <option value="Pending" <?= (isset($fb['status']) && $fb['status']==='Pending')?'selected':'' ?>>Pending</option>
                                            <option value="Reviewed" <?= (isset($fb['status']) && $fb['status']==='Reviewed')?'selected':'' ?>>Reviewed</option>
                                            <option value="Addressed" <?= (isset($fb['status']) && $fb['status']==='Addressed')?'selected':'' ?>>Addressed</option>
                                        </select>
                                        <button type="submit" name="update_status" style="padding:4px 10px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;">Update</button>
                                    </form>
                                </td>
                                <td style="padding:10px;">
                                    <details>
                                        <summary style="cursor:pointer;color:#2563eb;">View</summary>
                                        <div style="padding:8px 0;max-width:300px;white-space:pre-wrap;"><?= isset($fb['description']) ? htmlspecialchars($fb['description']) : '-' ?></div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="summary-section" style="max-width:1100px;margin:32px auto 0;">
            <div class="card" style="background:#fff;border-radius:18px;box-shadow:0 4px 24px rgba(37,99,235,0.07);padding:32px 32px;">
                <h2 style="font-size:1.2rem;font-weight:700;color:#2563eb;margin-bottom:18px;">Feedback Summary</h2>
                <div class="summary" style="display:flex;gap:40px;justify-content:center;flex-wrap:wrap;">
                    <div class="stat" style="text-align:center;min-width:120px;">
                        <div id="totalInputs" style="font-size:1.5em;font-weight:700;color:#1e3a8a;"><?= $totalInputs ?></div>
                        <small style="color:#64748b;">Total Feedback</small>
                    </div>
                    <div class="stat" style="text-align:center;min-width:120px;">
                        <div id="criticalInputs" style="font-size:1.5em;font-weight:700;color:#e11d48;"><?= $criticalInputs ?></div>
                        <small style="color:#64748b;">Critical Priority</small>
                    </div>
                    <div class="stat" style="text-align:center;min-width:120px;">
                        <div id="highInputs" style="font-size:1.5em;font-weight:700;color:#f59e42;"><?= $highInputs ?></div>
                        <small style="color:#64748b;">High Priority</small>
                    </div>
                    <div class="stat" style="text-align:center;min-width:120px;">
                        <div id="pendingInputs" style="font-size:1.5em;font-weight:700;color:#2563eb;"><?= $pendingInputs ?></div>
                        <small style="color:#64748b;">Pending Status</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script src="../shared-data.js?v=1"></script>
    <script src="project-prioritization.js?v=99"></script>
</body>
</html>
