<?php
session_start();
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
            <h1>Project Prioritization</h1>
            <p>Review and prioritize citizen inputs for infrastructure project planning</p>
        </div>

        <div class="inputs-section">
            <!-- Search & Filter Controls -->
            <div class="feedback-controls">
                <div class="search-group">
                    <label for="fbSearch">Search by Control Number or Name</label>
                    <input id="fbSearch" type="search" placeholder="e.g., CTL-001 or John Doe">
                </div>
                <div class="feedback-actions">
                    <button id="clearSearch" class="secondary">Clear</button>
                    <button id="exportData">Export CSV</button>
                </div>
            </div>

            <!-- Feedback Table -->
            <div class="card">
                <h2>User Feedback & Concerns</h2>
                <div class="table-wrap">
                    <table id="inputsTable" class="feedback-table">
                        <thead>
                            <tr>
                                <th>Control #</th>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($feedbacks)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="no-results">
                                        <div class="no-results-icon">📋</div>
                                        <div class="no-results-title">No Feedback Found</div>
                                        <div class="no-results-text">No feedback submitted yet. Please check back later.</div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $count = 1; foreach ($feedbacks as $fb): ?>
                                <?php $fb_lc = array_change_key_case($fb, CASE_LOWER); ?>
                                <tr class="<?= (isset($fb_lc['status']) && $fb_lc['status']==='Pending') ? 'pending-row' : '' ?>">
                                    <td><strong>CTL-<?= str_pad($count, 3, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td><?= isset($fb_lc['date_submitted']) ? htmlspecialchars($fb_lc['date_submitted']) : '-' ?></td>
                                    <td><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></td>
                                    <td><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></td>
                                    <td><?= isset($fb_lc['category']) ? htmlspecialchars($fb_lc['category']) : '-' ?></td>
                                    <td><?= isset($fb_lc['location']) ? htmlspecialchars($fb_lc['location']) : '-' ?></td>
                                    <td>
                                        <span class="badge <?= (isset($fb_lc['status']) ? strtolower($fb_lc['status']) : '') ?>">
                                            <?= isset($fb_lc['status']) ? htmlspecialchars($fb_lc['status']) : '-' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="edit-btn" onclick="openEditModal('edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">Edit</button>
                                        <button class="view-btn" onclick="openModal('modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">View Details</button>
                                    </td>
                                </tr>

                                <!-- Edit Modal for Status -->
                                <div id="edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h2>Update Feedback Status</h2>
                                            <button class="modal-close" onclick="closeEditModal('edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">&times;</button>
                                        </div>
                                        <form method="post" class="modal-form">
                                            <div class="modal-body">
                                                <div class="modal-field">
                                                    <span class="modal-label">Control Number:</span>
                                                    <div class="modal-value"><strong>CTL-<?= str_pad($count, 3, '0', STR_PAD_LEFT) ?></strong></div>
                                                </div>
                                                <div class="modal-field">
                                                    <span class="modal-label">From:</span>
                                                    <div class="modal-value"><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></div>
                                                </div>
                                                <div class="modal-field">
                                                    <span class="modal-label">Subject:</span>
                                                    <div class="modal-value"><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></div>
                                                </div>
                                                <div class="modal-field">
                                                    <label for="status-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal-label">Change Status:</label>
                                                    <select id="status-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" name="new_status" class="status-dropdown">
                                                        <option value="Pending" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Pending') ?'selected':'' ?>>Pending</option>
                                                        <option value="Reviewed" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Reviewed') ?'selected':'' ?>>Reviewed</option>
                                                        <option value="Addressed" <?= (isset($fb_lc['status']) && $fb_lc['status']==='Addressed') ?'selected':'' ?>>Addressed</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <input type="hidden" name="feedback_id" value="<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>">
                                                <button type="button" class="modal-btn modal-btn-close" onclick="closeEditModal('edit-modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">Cancel</button>
                                                <button type="submit" name="update_status" class="modal-btn modal-btn-action">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Modal for this feedback -->
                                <div id="modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>" class="modal">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h2>Feedback Details</h2>
                                            <button class="modal-close" onclick="closeModal('modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="modal-field">
                                                <span class="modal-label">Control Number:</span>
                                                <div class="modal-value"><strong>CTL-<?= str_pad($count, 3, '0', STR_PAD_LEFT) ?></strong></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Submitted By:</span>
                                                <div class="modal-value"><?= isset($fb_lc['user_name']) ? htmlspecialchars($fb_lc['user_name']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Date Submitted:</span>
                                                <div class="modal-value"><?= isset($fb_lc['date_submitted']) ? htmlspecialchars($fb_lc['date_submitted']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Subject:</span>
                                                <div class="modal-value"><?= isset($fb_lc['subject']) ? htmlspecialchars($fb_lc['subject']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Category:</span>
                                                <div class="modal-value"><?= isset($fb_lc['category']) ? htmlspecialchars($fb_lc['category']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Location:</span>
                                                <div class="modal-value"><?= isset($fb_lc['location']) ? htmlspecialchars($fb_lc['location']) : '-' ?></div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Status:</span>
                                                <div class="modal-value">
                                                    <span class="badge <?= (isset($fb_lc['status']) ? strtolower($fb_lc['status']) : '') ?>">
                                                        <?= isset($fb_lc['status']) ? htmlspecialchars($fb_lc['status']) : '-' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="modal-field">
                                                <span class="modal-label">Message / Description:</span>
                                                <div class="modal-value"><?= isset($fb_lc['description']) ? htmlspecialchars($fb_lc['description']) : '-' ?></div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button class="modal-btn modal-btn-close" onclick="closeModal('modal-<?= isset($fb_lc['id']) ? $fb_lc['id'] : '' ?>')">Close</button>
                                        </div>
                                    </div>
                                </div>
                            <?php $count++; endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="summary-section">
            <div class="card">
                <h2>Feedback Summary</h2>
                <div class="summary">
                    <div class="stat">
                        <div id="totalInputs"><?= $totalInputs ?></div>
                        <small>Total Feedback</small>
                    </div>
                    <div class="stat">
                        <div id="criticalInputs" style="color: #dc2626;"><?= $criticalInputs ?></div>
                        <small>Critical Priority</small>
                    </div>
                    <div class="stat">
                        <div id="highInputs" style="color: #f59e0b;"><?= $highInputs ?></div>
                        <small>High Priority</small>
                    </div>
                    <div class="stat">
                        <div id="pendingInputs" style="color: #3762c8;"><?= $pendingInputs ?></div>
                        <small>Pending Status</small>
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
    <script>
        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Edit Modal Functions
        function openEditModal(modalId) {
            document.getElementById(modalId).classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
                document.body.style.overflow = 'auto';
            }
        });

        // Search functionality
        const searchInput = document.getElementById('fbSearch');
        const clearBtn = document.getElementById('clearSearch');
        const table = document.getElementById('inputsTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            Array.from(rows).forEach(row => {
                if (row.querySelector('.no-results')) return;

                const controlNum = row.cells[0]?.textContent.toLowerCase() || '';
                const name = row.cells[2]?.textContent.toLowerCase() || '';

                const matches = searchTerm === '' || 
                               controlNum.includes(searchTerm) || 
                               name.includes(searchTerm);

                row.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            // Show no results message if needed
            const noResultsRow = Array.from(rows).find(r => r.querySelector('.no-results'));
            if (noResultsRow) {
                noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        searchInput.addEventListener('input', filterTable);

        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterTable();
            searchInput.focus();
        });

        // Export CSV function
        document.getElementById('exportData').addEventListener('click', function() {
            let csv = 'Control Number,Date,Name,Subject,Category,Location,Status\n';
            
            Array.from(rows).forEach((row, index) => {
                if (row.querySelector('.no-results')) return;
                
                const cells = row.getElementsByTagName('td');
                if (cells.length > 0 && row.style.display !== 'none') {
                    const rowData = [
                        cells[0]?.textContent || '',
                        cells[1]?.textContent || '',
                        cells[2]?.textContent || '',
                        cells[3]?.textContent || '',
                        cells[4]?.textContent || '',
                        cells[5]?.textContent || '',
                        cells[6]?.textContent || ''
                    ];
                    csv += rowData.map(cell => `"${cell.trim()}"`).join(',') + '\n';
                }
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'feedback_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        });
    </script>
</body>
</html>
