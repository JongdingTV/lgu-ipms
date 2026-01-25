<?php
// Define root path for all includes
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_URL', '/assets');

// Load configuration and auth
require_once CONFIG_PATH . '/app.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/database.php';

// Require authentication - employee/admin only
require_auth('employee', '/app/auth/login.php?type=employee');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>Admin Dashboard - LGU IPMS</title>
	<link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/images/logo.png">
	
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
	<!-- Font Awesome -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<!-- Main CSS -->
	<link href="<?php echo ASSETS_URL; ?>/css/main.css" rel="stylesheet" />
	
	<style>
		body {
			background: #f5f7fa;
			font-family: 'Poppins', sans-serif;
		}
		
		.navbar {
			background: linear-gradient(to right, #2980b9, #3498db);
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		
		.navbar-brand {
			font-weight: 700;
			font-size: 1.3rem;
			color: #fff !important;
		}
		
		.sidebar {
			background: #fff;
			min-height: 100vh;
			box-shadow: 2px 0 8px rgba(0,0,0,0.05);
		}
		
		.sidebar-menu {
			list-style: none;
			padding: 2rem 0;
		}
		
		.sidebar-menu li {
			margin: 0;
		}
		
		.sidebar-menu a {
			display: flex;
			align-items: center;
			padding: 1rem 1.5rem;
			color: #7f8c8d;
			text-decoration: none;
			transition: all 0.3s ease;
			border-left: 4px solid transparent;
		}
		
		.sidebar-menu a:hover {
			background: #f0f4f8;
			color: #2980b9;
			border-left-color: #2980b9;
		}
		
		.sidebar-menu a.active {
			background: #e3f2fd;
			color: #2980b9;
			border-left-color: #2980b9;
			font-weight: 600;
		}
		
		.sidebar-menu i {
			width: 25px;
			margin-right: 1rem;
			text-align: center;
		}
		
		.main-content {
			background: #f5f7fa;
			min-height: 100vh;
			padding: 2rem;
		}
		
		.page-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 2rem;
		}
		
		.page-header h1 {
			font-size: 2rem;
			font-weight: 700;
			color: #2c3e50;
			margin: 0;
		}
		
		.dashboard-card {
			background: #fff;
			border-radius: 12px;
			padding: 1.5rem;
			box-shadow: 0 2px 12px rgba(0,0,0,0.05);
			transition: all 0.3s ease;
			border: 1px solid #ecf0f1;
		}
		
		.dashboard-card:hover {
			box-shadow: 0 8px 20px rgba(0,0,0,0.1);
			transform: translateY(-4px);
		}
		
		.card-icon {
			font-size: 2.5rem;
			margin-bottom: 1rem;
		}
		
		.card-stat {
			font-size: 2rem;
			font-weight: 700;
			color: #2980b9;
			margin: 0.5rem 0;
		}
		
		.card-label {
			color: #7f8c8d;
			font-size: 0.95rem;
		}
		
		.btn-action {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
			border: none;
			padding: 0.75rem 1.5rem;
			border-radius: 8px;
			text-decoration: none;
			transition: all 0.3s ease;
			display: inline-flex;
			align-items: center;
			gap: 0.5rem;
		}
		
		.btn-action:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
			color: #fff;
			text-decoration: none;
		}
		
		.user-menu {
			display: flex;
			align-items: center;
			gap: 1rem;
		}
		
		.user-info {
			color: #fff;
		}
		
		.user-info .name {
			font-weight: 600;
			margin: 0;
		}
		
		.user-info .role {
			font-size: 0.85rem;
			opacity: 0.9;
			margin: 0;
		}
	</style>
</head>
<body>
	<!-- Navigation Bar -->
	<nav class="navbar navbar-expand-lg navbar-dark">
		<div class="container-fluid">
			<a class="navbar-brand" href="/public/index.php">
				<i class="fas fa-building"></i> LGU IPMS
			</a>
			
			<div class="ms-auto">
				<div class="user-menu">
					<div class="user-info">
						<p class="name"><?php echo htmlspecialchars(get_current_user_name()); ?></p>
						<p class="role">Administrator</p>
					</div>
					<a href="/app/auth/logout.php" class="btn btn-sm btn-outline-light">
						<i class="fas fa-sign-out-alt"></i> Logout
					</a>
				</div>
			</div>
		</div>
	</nav>
	
	<div class="container-fluid">
		<div class="row">
			<!-- Sidebar -->
			<div class="col-md-2 sidebar">
				<ul class="sidebar-menu">
					<li><a href="#dashboard" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
					<li><a href="#projects"><i class="fas fa-tasks"></i> Projects</a></li>
					<li><a href="#budget"><i class="fas fa-money-bill-wave"></i> Budget</a></li>
					<li><a href="#contractors"><i class="fas fa-briefcase"></i> Contractors</a></li>
					<li><a href="#progress"><i class="fas fa-hourglass-half"></i> Progress</a></li>
					<li><a href="#reports"><i class="fas fa-file-alt"></i> Reports</a></li>
					<li><a href="#settings"><i class="fas fa-cog"></i> Settings</a></li>
				</ul>
			</div>
			
			<!-- Main Content -->
			<div class="col-md-10 main-content">
				<div class="page-header">
					<h1>Admin Dashboard</h1>
					<a href="#" class="btn-action">
						<i class="fas fa-plus"></i> New Project
					</a>
				</div>
				
				<!-- Dashboard Stats -->
				<div class="row mb-4">
					<div class="col-md-6 col-lg-3 mb-3">
						<div class="dashboard-card">
							<div class="card-icon" style="color: #2980b9;">
								<i class="fas fa-tasks"></i>
							</div>
							<div class="card-stat">150</div>
							<div class="card-label">Total Projects</div>
						</div>
					</div>
					<div class="col-md-6 col-lg-3 mb-3">
						<div class="dashboard-card">
							<div class="card-icon" style="color: #27ae60;">
								<i class="fas fa-check-circle"></i>
							</div>
							<div class="card-stat">95%</div>
							<div class="card-label">Completion Rate</div>
						</div>
					</div>
					<div class="col-md-6 col-lg-3 mb-3">
						<div class="dashboard-card">
							<div class="card-icon" style="color: #f39c12;">
								<i class="fas fa-spinner"></i>
							</div>
							<div class="card-stat">12</div>
							<div class="card-label">In Progress</div>
						</div>
					</div>
					<div class="col-md-6 col-lg-3 mb-3">
						<div class="dashboard-card">
							<div class="card-icon" style="color: #e74c3c;">
								<i class="fas fa-money-bill-wave"></i>
							</div>
							<div class="card-stat">₱2.5M</div>
							<div class="card-label">Total Budget</div>
						</div>
					</div>
				</div>
				
				<!-- Recent Projects -->
				<div class="dashboard-card">
					<h5 style="margin-bottom: 1.5rem; color: #2c3e50;">
						<i class="fas fa-list"></i> Recent Projects
					</h5>
					<div style="min-height: 300px; display: flex; align-items: center; justify-content: center; color: #bdc3c7;">
						<p>No projects yet. <a href="#projects" style="color: #2980b9;">Create a new project</a></p>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
	<!-- Main JS -->
	<script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>
