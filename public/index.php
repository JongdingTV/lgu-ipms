<?php
// Define root path for all includes
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('ASSETS_URL', '/assets');

// Load configuration
require_once CONFIG_PATH . '/app.php';
require_once INCLUDES_PATH . '/helpers.php';

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>LGU Infrastructure & Project Management System</title>
	<meta name="description" content="Access community infrastructure maintenance requests and track project progress securely.">
	<meta name="keywords" content="LGU, Infrastructure, Projects, Citizen Portal">
	<meta name="theme-color" content="#2980b9">
	<link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/images/logo.png" />
	
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
	<!-- Google Fonts -->
	<link href="https://fonts.googleapis.com/css?family=Merriweather+Sans:400,700" rel="stylesheet" />
	<!-- Main CSS -->
	<link href="<?php echo ASSETS_URL; ?>/css/main.css" rel="stylesheet" />
	<link href="<?php echo ASSETS_URL; ?>/css/responsive.css" rel="stylesheet" />
	
	<style>
		body {
			min-height: 100vh;
			background: linear-gradient(120deg, #2980b9, #6dd5fa, #ffffff);
			font-family: 'Merriweather Sans', Arial, sans-serif;
		}
		
		.masthead {
			background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), 
			            url('<?php echo ASSETS_URL; ?>/images/gallery/road.jpg') center/cover no-repeat;
			min-height: 60vh;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #fff;
			text-shadow: 0 2px 8px rgba(0,0,0,0.3);
			margin-top: 70px;
		}
		
		.masthead h1 {
			font-size: 2.5rem;
			font-weight: bold;
			animation: slideInDown 0.8s ease-out;
		}
		
		.masthead p {
			font-size: 1.2rem;
			animation: slideInUp 0.8s ease-out 0.2s both;
		}
		
		@keyframes slideInDown {
			from {
				opacity: 0;
				transform: translateY(-30px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		
		@keyframes slideInUp {
			from {
				opacity: 0;
				transform: translateY(30px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		
		.facility-card {
			transition: transform 0.3s ease, box-shadow 0.3s ease;
			border: none;
			border-radius: 12px;
			box-shadow: 0 2px 12px rgba(0,0,0,0.08);
		}
		
		.facility-card:hover {
			transform: translateY(-8px);
			box-shadow: 0 8px 24px rgba(0,0,0,0.15);
		}
		
		.facility-img {
			width: 100%;
			height: 200px;
			object-fit: cover;
			border-radius: 12px 12px 0 0;
		}
		
		.btn-login {
			background: linear-gradient(135deg, #2980b9, #3498db);
			color: #fff;
			border: none;
			padding: 14px 32px;
			border-radius: 6px;
			font-size: 1.1rem;
			cursor: pointer;
			transition: all 0.3s ease;
			text-decoration: none;
			display: inline-block;
			margin-top: 1.5rem;
			font-weight: 600;
		}
		
		.btn-login:hover {
			background: linear-gradient(135deg, #1a3c5d, #2980b9);
			transform: translateY(-2px);
			box-shadow: 0 8px 16px rgba(41, 128, 185, 0.3);
			color: #fff;
			text-decoration: none;
		}
		
		.btn-login.secondary {
			background: linear-gradient(135deg, #27ae60, #2ecc71);
			margin-left: 1rem;
		}
		
		.btn-login.secondary:hover {
			background: linear-gradient(135deg, #1c5a3f, #27ae60);
		}
		
		.section-title {
			color: #1a3c5d;
			margin-top: 3rem;
			margin-bottom: 2rem;
			font-weight: 700;
			font-size: 2rem;
		}
		
		.navbar-custom {
			background: linear-gradient(to right, #2980b9, #3498db) !important;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
			padding: 1rem 0;
		}
		
		.navbar-custom .navbar-brand {
			font-weight: 700;
			font-size: 1.5rem;
			color: #fff !important;
		}
		
		.navbar-custom .nav-link {
			color: rgba(255,255,255,0.9) !important;
			margin: 0 0.5rem;
			transition: color 0.3s ease;
		}
		
		.navbar-custom .nav-link:hover {
			color: #fff !important;
		}
		
		.features-section {
			background: #f8f9fa;
			padding: 3rem 0;
		}
		
		.footer {
			background: linear-gradient(to right, #1a3c5d, #2c3e50);
			color: #ecf0f1;
			text-align: center;
			padding: 2rem 0 1rem;
			margin-top: 3rem;
		}
		
		.footer a {
			color: #3498db;
			text-decoration: none;
		}
		
		.footer a:hover {
			text-decoration: underline;
		}
		
		.stats-section {
			background: #fff;
			padding: 3rem 0;
		}
		
		.stat-card {
			text-align: center;
			padding: 2rem;
		}
		
		.stat-number {
			font-size: 2.5rem;
			font-weight: 700;
			color: #2980b9;
		}
		
		.stat-label {
			color: #7f8c8d;
			font-size: 1rem;
			margin-top: 0.5rem;
		}
	</style>
</head>
<body>
	<!-- Navigation Bar -->
	<nav class="navbar navbar-expand-lg navbar-custom fixed-top">
		<div class="container">
			<a class="navbar-brand" href="/">
				<i class="fas fa-building"></i> LGU IPMS
			</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNav">
				<ul class="navbar-nav ms-auto">
					<li class="nav-item">
						<a class="nav-link" href="#features">Features</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="#stats">About</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="/admin/admin.php">Admin Login</a>
					</li>
				</ul>
			</div>
		</div>
	</nav>

	<!-- Hero Section -->
	<header class="masthead">
		<div class="container text-center">
			<h1>Welcome to LGU Infrastructure & Project Management</h1>
			<p>Access community infrastructure maintenance requests and track project progress securely.</p>
			<div>
				<a href="user-dashboard/user-login.php" class="btn-login">
					<i class="fas fa-user-circle"></i> Citizen Login
				</a>
				<a href="/admin/admin.php" class="btn-login secondary">
					<i class="fas fa-briefcase"></i> Employee Login
				</a>
			</div>
		</div>
	</header>

	<!-- Features Section -->
	<section class="features-section" id="features">
		<div class="container">
			<h2 class="section-title text-center">Key Features</h2>
			<div class="row justify-content-center">
				<div class="col-md-6 col-lg-3 mb-4">
					<div class="card facility-card h-100">
						<img src="<?php echo asset('../images/gallery/road.jpg'); ?>" alt="Infrastructure" class="facility-img">
						<div class="card-body text-center">
							<h5 class="card-title">
								<i class="fas fa-road" style="color: #2980b9;"></i><br>
								Infrastructure Tracking
							</h5>
							<p class="card-text">Monitor community infrastructure projects in real-time</p>
						</div>
					</div>
				</div>
				
				<div class="col-md-6 col-lg-3 mb-4">
					<div class="card facility-card h-100">
						<img src="<?php echo asset('../images/gallery/drainage.jpg'); ?>" alt="Sustainability" class="facility-img">
						<div class="card-body text-center">
							<h5 class="card-title">
								<i class="fas fa-leaf" style="color: #27ae60;"></i><br>
								Sustainability
							</h5>
							<p class="card-text">Ensure sustainable community development</p>
						</div>
					</div>
				</div>
				
				<div class="col-md-6 col-lg-3 mb-4">
					<div class="card facility-card h-100">
						<img src="<?php echo asset('../images/gallery/construction.jpg'); ?>" alt="Management" class="facility-img">
						<div class="card-body text-center">
							<h5 class="card-title">
								<i class="fas fa-hard-hat" style="color: #e74c3c;"></i><br>
								Strong Management
							</h5>
							<p class="card-text">Professional project and budget management</p>
						</div>
					</div>
				</div>
				
				<div class="col-md-6 col-lg-3 mb-4">
					<div class="card facility-card h-100">
						<img src="<?php echo asset('../images/gallery/bridge.jpg'); ?>" alt="Innovation" class="facility-img">
						<div class="card-body text-center">
							<h5 class="card-title">
								<i class="fas fa-laptop" style="color: #9b59b6;"></i><br>
								Digital Innovation
							</h5>
							<p class="card-text">Modern technology-driven solutions</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Statistics Section -->
	<section class="stats-section" id="stats">
		<div class="container">
			<h2 class="section-title text-center">About Our System</h2>
			<div class="row">
				<div class="col-md-3">
					<div class="stat-card">
						<div class="stat-number">150+</div>
						<div class="stat-label">Active Projects</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="stat-card">
						<div class="stat-number">95%</div>
						<div class="stat-label">Completion Rate</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="stat-card">
						<div class="stat-number">500+</div>
						<div class="stat-label">Registered Users</div>
					</div>
				</div>
				<div class="col-md-3">
					<div class="stat-card">
						<div class="stat-number">24/7</div>
						<div class="stat-label">System Support</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Footer -->
	<footer class="footer">
		<div class="container">
			<p><strong>LGU Infrastructure & Project Management System</strong></p>
			<p style="margin-bottom: 0;">© <?php echo date('Y'); ?> Local Government Unit · All Rights Reserved</p>
			<p style="font-size: 0.9rem; margin-top: 1rem;">
				<a href="#privacy">Privacy Policy</a> · 
				<a href="#terms">Terms of Service</a> · 
				<a href="#contact">Contact Us</a>
			</p>
		</div>
	</footer>

	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
	<!-- Font Awesome -->
	<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
	<!-- Main JS -->
	<script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>
