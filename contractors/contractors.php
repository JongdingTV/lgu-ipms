<?php
// Database connection
$conn = new mysqli('localhost', 'ipms_root', 'G3P+JANpr2GK6fax', 'ipms_lgu');
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Handle GET request for loading projects
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'load_projects') {
    header('Content-Type: application/json');
    
    $result = $conn->query("SELECT id, code, name FROM projects ORDER BY created_at DESC");
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

$conn->close();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Contractors - LGU IPMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/style.css" />
</head>
<body>
    <header class="nav" id="navbar">
        <div class="nav-logo">
            <img src="../logocityhall.png" alt="City Hall Logo" class="logo-img">
            <span class="logo-text">IPMS</span>
        </div>
        <div class="nav-links">
            <a href="../dashboard/dashboard.php"><img src="../dashboard/dashboard.png" alt="Dashboard Icon" class="nav-icon">Dashboard Overview</a>
            <a href="../project-registration/project_registration.php"><img src="../project-registration/list.png" class="nav-icon">Project Registration   ▼</a>
            <a href="../progress-monitoring/progress_monitoring.php"><img src="../progress-monitoring/monitoring.png" class="nav-icon">Progress Monitoring</a>
            <a href="../budget-resources/budget_resources.php"><img src="../budget-resources/budget.png" class="nav-icon">Budget & Resources</a>
            <a href="../task-milestone/tasks_milestones.php"><img src="../task-milestone/production.png" class="nav-icon">Task & Milestone</a>
            
            <!-- Contractors with Submenu -->
            <div class="nav-item-group">
                <a href="contractors.php" class="active nav-main-item" id="contractorsToggle">
                    <img src="contractors.png" class="nav-icon">Contractors
                    <span class="dropdown-arrow">▼</span>
                </a>
                <div class="nav-submenu" id="contractorsSubmenu">
                    <a href="contractors.php" class="nav-submenu-item active">
                        <span class="submenu-icon">➕</span>
                        <span>Add Contractor</span>
                    </a>
                    <a href="registered_contractors.php" class="nav-submenu-item">
                        <span class="submenu-icon">👷</span>
                        <span>Registered Contractors</span>
                    </a>
                </div>
            </div>
            
            <a href="../project-prioritization/project-prioritization.php"><img src="../project-prioritization/prioritization.png" class="nav-icon">Project Prioritization</a>
        </div>
        <div class="nav-user">
            <img src="../dashboard/person.png" alt="User Icon" class="user-icon">
            <span class="nav-username">Welcome <?php echo isset($_SESSION['employee_name']) ? $_SESSION['employee_name'] : 'Admin'; ?></span>
            <a href="../index.php" class="nav-logout">Logout</a>
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
            <h1>Contractors</h1>
            <p>Manage contractor information</p>
        </div>

        <div class="recent-projects">
            <h3>Add/Edit Contractor</h3>

            <form id="contractorForm" enctype="multipart/form-data">
                <!-- Basic contractor details -->
                <fieldset>
                    <legend>Basic Information</legend>
                    <div>
                        <div>
                            <label for="ctrCompany">Company Name</label>
                            <input type="text" id="ctrCompany" required>
                        </div>
                        <div>
                            <label for="ctrOwner">Owner Name</label>
                            <input type="text" id="ctrOwner">
                        </div>
                        <div>
                            <label for="ctrLicense">License Number</label>
                            <input type="text" id="ctrLicense" required>
                        </div>
                    </div>
                    <div>
                        <div>
                            <label for="ctrEmail">Email</label>
                            <input type="email" id="ctrEmail">
                        </div>
                        <div>
                            <label for="ctrPhone">Phone Number</label>
                            <input type="tel" id="ctrPhone">
                        </div>
                    </div>
                </fieldset>

                <!-- Additional details -->
                <fieldset>
                    <legend>Additional Details</legend>
                    <div>
                        <div>
                            <label for="ctrAddress">Address</label>
                            <input type="text" id="ctrAddress" required>
                        </div>
                        <div>
                            <label for="ctrSpecialization">Specialization</label>
                            <select id="ctrSpecialization">
                                <option value="">-- Select --</option>
                                <option>Construction</option>
                                <option>Plumbing</option>
                                <option>Electrical</option>
                                <option>Civil Engineering</option>
                                <option>Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="ctrExperience">Years of Experience</label>
                            <input type="number" id="ctrExperience" min="0">
                        </div>
                    </div>
                    <div>
                        <div>
                            <label for="ctrRating">Rating (1-5)</label>
                            <input type="number" id="ctrRating" min="1" max="5" step="0.1">
                        </div>
                        <div>
                            <label for="ctrStatus">Status</label>
                            <select id="ctrStatus">
                                <option value="Active">Active</option>
                                <option value="Suspended">Suspended</option>
                                <option value="Blacklisted">Blacklisted</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div style="flex: 1;">
                            <label for="ctrNotes">Notes</label>
                            <textarea id="ctrNotes" rows="2"></textarea>
                        </div>
                    </div>
                </fieldset>

                <div style="margin-top:12px;">
                    <button type="submit" id="submitBtn">
                        Create Contractor
                    </button>
                    <button type="button" id="resetBtn">
                        Reset
                    </button>
                </div>
            </form>

            <div id="formMessage" style="margin-top:12px;color:#0b5;display:none;"></div>
        </div>
    </section>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit. All rights reserved.</p>
    </footer>

    <script src="../shared-data.js?v=1"></script>
    <script src="contractors.js?v=3"></script>
</body>
</html>
