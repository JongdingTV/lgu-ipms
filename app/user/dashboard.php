<?php
require_once dirname(dirname(__DIR__)) . '/config/app.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/database.php';

check_auth();
if (!has_role('citizen')) {
	header('HTTP/1.0 403 Forbidden');
	die('Access denied. Required role: citizen');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>Citizen Dashboard - LGU IPMS</title>
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
			background: linear-gradient(to right, #27ae60, #2ecc71);
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		
		.navbar-brand {
			font-weight: 700;
			font-size: 1.3rem;
			color: #fff !important;
		}
		
		.main-content {
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
			margin-bottom: 1.5rem;
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
			color: #27ae60;
			margin: 0.5rem 0;
		}
		
		.card-label {
			color: #7f8c8d;
			font-size: 0.95rem;
		}
		
		.btn-action {
			background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
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
			box-shadow: 0 8px 16px rgba(39, 174, 96, 0.4);
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
		
		.status-badge {
			display: inline-block;
			padding: 0.25rem 0.75rem;
			border-radius: 20px;
			font-size: 0.85rem;
			font-weight: 600;
		}
		
		.status-pending {
			background: #fff3cd;
			color: #856404;
		}
		
		.status-approved {
			background: #d4edda;
			color: #155724;
		}
		
		.status-completed {
			background: #d1ecf1;
			color: #0c5460;
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
			
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
				<span class="navbar-toggler-icon"></span>
			</button>
			
			<div class="collapse navbar-collapse" id="navbarNav">
				<ul class="navbar-nav ms-auto">
					<li class="nav-item">
						<a class="nav-link active" href="#"><i class="fas fa-home"></i> Dashboard</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="#requests"><i class="fas fa-file-alt"></i> My Requests</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="#tracking"><i class="fas fa-map"></i> Track Progress</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="#feedback"><i class="fas fa-comments"></i> Feedback</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="/app/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
					</li>
				</ul>
			</div>
		</div>
	</nav>
	
	<div class="main-content container-fluid">
		<div class="page-header">
			<div>
				<h1>Welcome, <?php echo htmlspecialchars(get_user_name()); ?>!</h1>
				<p style="color: #7f8c8d; margin: 0.5rem 0;">Track your infrastructure requests and community feedback</p>
			</div>
			<a href="#request" class="btn-action">
				<i class="fas fa-plus"></i> Submit Request
			</a>
		</div>
		
		<!-- Dashboard Stats -->
		<div class="row mb-4">
			<div class="col-md-6 col-lg-3 mb-3">
				<div class="dashboard-card">
					<div class="card-icon" style="color: #27ae60;">
						<i class="fas fa-check-circle"></i>
					</div>
					<div class="card-stat">5</div>
					<div class="card-label">Completed Requests</div>
				</div>
			</div>
			<div class="col-md-6 col-lg-3 mb-3">
				<div class="dashboard-card">
					<div class="card-icon" style="color: #f39c12;">
						<i class="fas fa-spinner"></i>
					</div>
					<div class="card-stat">2</div>
					<div class="card-label">In Progress</div>
				</div>
			</div>
			<div class="col-md-6 col-lg-3 mb-3">
				<div class="dashboard-card">
					<div class="card-icon" style="color: #e74c3c;">
						<i class="fas fa-clock"></i>
					</div>
					<div class="card-stat">1</div>
					<div class="card-label">Pending Review</div>
				</div>
			</div>
			<div class="col-md-6 col-lg-3 mb-3">
				<div class="dashboard-card">
					<div class="card-icon" style="color: #2980b9;">
						<i class="fas fa-bell"></i>
					</div>
					<div class="card-stat">3</div>
					<div class="card-label">New Updates</div>
				</div>
			</div>
		</div>
		
		<!-- My Recent Requests -->
		<div class="dashboard-card">
			<h5 style="margin-bottom: 1.5rem; color: #2c3e50;">
				<i class="fas fa-list"></i> My Recent Requests
			</h5>
			<div class="table-responsive">
				<table class="table table-hover" style="margin-bottom: 0;">
					<thead style="background: #f8f9fa;">
						<tr>
							<th>Request ID</th>
							<th>Description</th>
							<th>Date Submitted</th>
							<th>Status</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>#REQ-2024-001</td>
							<td>Road repair request - Purok 1</td>
							<td>Jan 15, 2024</td>
							<td><span class="status-badge status-completed"><i class="fas fa-check"></i> Completed</span></td>
							<td><a href="#" style="color: #27ae60; text-decoration: none;"><i class="fas fa-eye"></i> View</a></td>
						</tr>
						<tr>
							<td>#REQ-2024-002</td>
							<td>Drainage system improvement</td>
							<td>Feb 10, 2024</td>
							<td><span class="status-badge status-approved"><i class="fas fa-check"></i> Approved</span></td>
							<td><a href="#" style="color: #27ae60; text-decoration: none;"><i class="fas fa-eye"></i> View</a></td>
						</tr>
						<tr>
							<td>#REQ-2024-003</td>
							<td>Street lighting installation</td>
							<td>Feb 20, 2024</td>
							<td><span class="status-badge status-pending"><i class="fas fa-hourglass-half"></i> Pending</span></td>
							<td><a href="#" style="color: #27ae60; text-decoration: none;"><i class="fas fa-eye"></i> View</a></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	
	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
	<!-- Main JS -->
	<script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>
