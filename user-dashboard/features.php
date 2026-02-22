                <div style="font-size:0.97em;color:#64748b;line-height:1.1;"> <?php echo htmlspecialchars($user_email); ?> </div>
<?php
/**
 * New Features for Citizens
 * Features added to improve citizen engagement and transparency
 */

// Only citizens can access this
require_once dirname(__DIR__) . '/session-auth.php';
require_once dirname(__DIR__) . '/includes/security.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /user-dashboard/user-login.php');
    exit;
}

$asset_url = '/assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Citizen Features - LGU IPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <script src="/assets/js/shared/security-no-back.js?v=<?php echo time(); ?>"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1e3a5f;
            --secondary: #f39c12;
            --success: #27ae60;
            --danger: #e74c3c;
            --info: #3498db;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --white: #ffffff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, #2c5282 100%);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .navbar-brand {
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand img {
            height: 40px;
            width: auto;
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 5px solid var(--secondary);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .feature-icon {
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            color: var(--primary);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .section-title {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 2rem;
            text-align: center;
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary) 0%, #2c5282 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .feature-category {
            margin-bottom: 3rem;
        }

        .category-title {
            color: var(--secondary);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-feature {
            background: linear-gradient(135deg, var(--secondary), #e67e22);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-feature:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(243, 156, 18, 0.3);
            color: white;
        }

        footer {
            background: var(--primary);
            color: white;
            padding: 2rem 0;
            text-align: center;
            margin-top: 3rem;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand text-white" href="/user-dashboard/user-dashboard.php">
                <img src="/assets/images/icons/ipms-icon.png" alt="Logo">
                <span>LGU IPMS - Citizen Portal</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="/user-dashboard/user-dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="header-section">
        <div class="container">
            <h1>Citizen Features</h1>
            <p>Discover all the ways you can stay informed and engaged with your community</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Real-time Tracking -->
        <div class="feature-category">
            <h2 class="category-title">
                <i class="fas fa-map"></i> Real-Time Project Tracking
            </h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-location-dot"></i>
                        </div>
                        <h3>Interactive Project Map</h3>
                        <p>View all infrastructure projects on an interactive map. See project locations, status, and proximity to your area. Get instant notifications when projects start or complete near you.</p>
                        <a href="#" class="btn-feature">View Map</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Live Progress Updates</h3>
                        <p>See real-time progress bars for every project. Track completion percentage, budget utilization, and expected completion dates. Subscribe to get updates directly to your email or app.</p>
                        <a href="#" class="btn-feature">Subscribe to Updates</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Feedback & Engagement -->
        <div class="feature-category">
            <h2 class="category-title">
                <i class="fas fa-comments"></i> Feedback & Community Engagement
            </h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <h3>Project Feedback Portal</h3>
                        <p>Share your feedback on any project. Rate project quality, report issues, suggest improvements, and see how your feedback influences decisions. Your voice matters!</p>
                        <a href="/user-dashboard/user-feedback.php" class="btn-feature">Leave Feedback</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <h3>Smart Notifications</h3>
                        <p>Receive notifications only for projects that matter to you. Customize by project type, location, and frequency. Never miss important updates about your community.</p>
                        <a href="/user-dashboard/user-settings.php" class="btn-feature">Manage Notifications</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transparency & Reports -->
        <div class="feature-category">
            <h2 class="category-title">
                <i class="fas fa-file-chart-line"></i> Transparency & Reports
            </h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h3>Budget Transparency</h3>
                        <p>See exactly where your tax money is being spent. View budget allocations, spending reports, and cost breakdowns for every project in your community.</p>
                        <a href="#" class="btn-feature">View Budget Reports</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <h3>Export & Share Reports</h3>
                        <p>Download comprehensive reports in PDF or Excel format. Share data with community organizations, media, or friends. Full transparency at your fingertips.</p>
                        <a href="#" class="btn-feature">Download Reports</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Community Features -->
        <div class="feature-category">
            <h2 class="category-title">
                <i class="fas fa-users"></i> Community Features
            </h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <h3>Community Discussions</h3>
                        <p>Join community forums to discuss projects with neighbors. Share concerns, ideas, and solutions. Build a more informed and engaged community together.</p>
                        <a href="#" class="btn-feature">Join Discussions</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-poll"></i>
                        </div>
                        <h3>Community Polls</h3>
                        <p>Vote on which projects are most important to you. Participate in surveys about community needs. Your opinion shapes the future of your city.</p>
                        <a href="#" class="btn-feature">Vote Now</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Problem Reporting -->
        <div class="feature-category">
            <h2 class="category-title">
                <i class="fas fa-exclamation-triangle"></i> Problem Reporting
            </h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bug"></i>
                        </div>
                        <h3>Report Project Issues</h3>
                        <p>Found a problem with ongoing work? Report issues directly to project managers. Include photos and location details. Get updates on issue resolution.</p>
                        <a href="#" class="btn-feature">Report an Issue</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3>Issue Tracking</h3>
                        <p>Track all reported issues and their status. See how quickly problems are addressed. This keeps government accountable to citizens like you.</p>
                        <a href="#" class="btn-feature">View Issue Status</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; 2026 LGU Infrastructure Project Management System</p>
            <p style="font-size: 0.9rem; opacity: 0.9; margin-top: 0.5rem;">
                <a href="#" style="color: var(--secondary); text-decoration: none;">Privacy Policy</a> | 
                <a href="#" style="color: var(--secondary); text-decoration: none;">Terms of Service</a> | 
                <a href="#" style="color: var(--secondary); text-decoration: none;">Help & Support</a>
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>




