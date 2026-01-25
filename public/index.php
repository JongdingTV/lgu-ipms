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
	<meta name="description" content="Manage infrastructure projects, track progress, and connect with your community.">
	<meta name="keywords" content="LGU, Infrastructure, Projects, Government Services">
	<meta name="theme-color" content="#1e3a5f">
	<link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/images/logo.png" />
	
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
	<!-- Font Awesome -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<!-- Google Fonts -->
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
	
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		:root {
			--primary: #1e3a5f;
			--primary-light: #2c5282;
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
			line-height: 1.6;
			color: #333;
			background: linear-gradient(rgba(30, 58, 95, 0.5), rgba(30, 58, 95, 0.5)), 
			            url('/cityhall.jpeg') center/cover no-repeat fixed;
			min-height: 100vh;
			position: relative;
		}

		/* Navbar */
		.navbar {
			background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%) !important;
			box-shadow: 0 4px 15px rgba(0,0,0,0.2);
			backdrop-filter: blur(10px);
			padding: 1rem 0;
		}

		.navbar-brand {
			font-weight: 800;
			font-size: 1.6rem;
			color: var(--white) !important;
			display: flex;
			align-items: center;
			gap: 0.5rem;
			letter-spacing: -0.5px;
		}

		.navbar-brand i {
			font-size: 1.8rem;
		}

		.nav-link {
			color: rgba(255,255,255,0.85) !important;
			font-weight: 500;
			margin: 0 0.8rem;
			position: relative;
			transition: all 0.3s ease;
		}

		.nav-link:hover {
			color: var(--secondary) !important;
		}

		.nav-link::after {
			content: '';
			position: absolute;
			width: 0;
			height: 2px;
			bottom: -5px;
			left: 0;
			background: var(--secondary);
			transition: width 0.3s ease;
		}

		.nav-link:hover::after {
			width: 100%;
		}

		/* Hero Section */
		.hero {
			min-height: 90vh;
			display: flex;
			align-items: center;
			justify-content: center;
			text-align: center;
			color: var(--white);
			position: relative;
			overflow: hidden;
			padding: 2rem;
			margin-top: 70px;
		}

		.hero::before {
			content: '';
			position: absolute;
			top: -50%;
			right: -10%;
			width: 600px;
			height: 600px;
			background: rgba(243, 156, 18, 0.1);
			border-radius: 50%;
			animation: float 6s ease-in-out infinite;
		}

		.hero::after {
			content: '';
			position: absolute;
			bottom: -20%;
			left: -5%;
			width: 400px;
			height: 400px;
			background: rgba(52, 152, 219, 0.1);
			border-radius: 50%;
			animation: float 8s ease-in-out infinite reverse;
		}

		.hero-content {
			position: relative;
			z-index: 1;
			max-width: 900px;
			animation: slideInUp 1s ease-out;
		}

		.hero h1 {
			font-size: 3.5rem;
			font-weight: 800;
			margin-bottom: 1.5rem;
			text-shadow: 2px 2px 10px rgba(0,0,0,0.4);
			line-height: 1.2;
		}

		.hero .subtitle {
			font-size: 1.3rem;
			margin-bottom: 2.5rem;
			font-weight: 300;
			text-shadow: 1px 1px 5px rgba(0,0,0,0.3);
			opacity: 0.95;
		}

		.hero-buttons {
			display: flex;
			gap: 1.5rem;
			justify-content: center;
			flex-wrap: wrap;
		}

		.btn-primary-custom {
			padding: 1rem 2.5rem;
			font-size: 1.1rem;
			font-weight: 600;
			border: none;
			border-radius: 50px;
			cursor: pointer;
			transition: all 0.3s ease;
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			gap: 0.7rem;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			box-shadow: 0 8px 25px rgba(0,0,0,0.15);
		}

		.btn-primary-custom:hover {
			transform: translateY(-3px);
			box-shadow: 0 12px 35px rgba(0,0,0,0.25);
		}

		.btn-citizen {
			background: linear-gradient(135deg, var(--secondary) 0%, #e67e22 100%);
			color: var(--white);
		}

		.btn-employee {
			background: var(--white);
			color: var(--primary);
		}

		.btn-employee:hover {
			color: var(--primary);
		}

		@keyframes float {
			0%, 100% { transform: translateY(0px); }
			50% { transform: translateY(30px); }
		}

		@keyframes slideInUp {
			from {
				opacity: 0;
				transform: translateY(50px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		/* Features Section */
		.features {
			padding: 5rem 0;
			background: var(--white);
		}

		.section-title {
			font-size: 2.8rem;
			font-weight: 800;
			text-align: center;
			margin-bottom: 1rem;
			color: var(--primary);
			position: relative;
			padding-bottom: 1.5rem;
		}

		.section-title::after {
			content: '';
			position: absolute;
			bottom: 0;
			left: 50%;
			transform: translateX(-50%);
			width: 80px;
			height: 4px;
			background: linear-gradient(90deg, var(--secondary), var(--info));
			border-radius: 2px;
		}

		.section-subtitle {
			text-align: center;
			color: #666;
			font-size: 1.1rem;
			margin-bottom: 3.5rem;
			font-weight: 300;
		}

		.feature-card {
			background: var(--white);
			border-radius: 15px;
			padding: 2.5rem;
			text-align: center;
			transition: all 0.3s ease;
			border: 1px solid #e0e0e0;
			height: 100%;
			box-shadow: 0 4px 15px rgba(0,0,0,0.08);
		}

		.feature-card:hover {
			transform: translateY(-10px);
			box-shadow: 0 12px 35px rgba(0,0,0,0.15);
			border-color: var(--secondary);
		}

		.feature-icon {
			width: 80px;
			height: 80px;
			margin: 0 auto 1.5rem;
			background: linear-gradient(135deg, var(--secondary), #e67e22);
			border-radius: 15px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 2.5rem;
			color: var(--white);
			box-shadow: 0 8px 20px rgba(243, 156, 18, 0.3);
		}

		.feature-card h5 {
			font-size: 1.4rem;
			font-weight: 700;
			margin-bottom: 1rem;
			color: var(--dark);
		}

		.feature-card p {
			color: #666;
			line-height: 1.8;
			font-size: 0.95rem;
		}

		/* Stats Section */
		.stats {
			padding: 5rem 0;
			background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
			color: var(--white);
		}

		.stat-box {
			text-align: center;
			padding: 2rem;
		}

		.stat-number {
			font-size: 2.8rem;
			font-weight: 800;
			margin-bottom: 0.5rem;
			color: var(--secondary);
		}

		.stat-label {
			font-size: 1.1rem;
			font-weight: 500;
			opacity: 0.9;
		}

		/* Benefits Section */
		.benefits {
			padding: 5rem 0;
			background: var(--light);
		}

		.benefit-item {
			display: flex;
			gap: 2rem;
			margin-bottom: 2.5rem;
			padding: 2rem;
			background: var(--white);
			border-radius: 12px;
			align-items: flex-start;
			box-shadow: 0 3px 10px rgba(0,0,0,0.05);
			transition: all 0.3s ease;
		}

		.benefit-item:hover {
			box-shadow: 0 8px 20px rgba(0,0,0,0.1);
			transform: translateX(5px);
		}

		.benefit-icon {
			width: 60px;
			height: 60px;
			background: linear-gradient(135deg, var(--info), #3498db);
			border-radius: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1.8rem;
			color: var(--white);
			flex-shrink: 0;
		}

		.benefit-content h4 {
			font-size: 1.3rem;
			font-weight: 700;
			margin-bottom: 0.5rem;
			color: var(--dark);
		}

		.benefit-content p {
			color: #666;
			line-height: 1.7;
		}

		/* CTA Section */
		.cta {
			padding: 5rem 0;
			background: linear-gradient(135deg, var(--secondary) 0%, #e67e22 100%);
			color: var(--white);
			text-align: center;
		}

		.cta h2 {
			font-size: 2.5rem;
			font-weight: 800;
			margin-bottom: 1rem;
		}

		.cta p {
			font-size: 1.2rem;
			margin-bottom: 2rem;
			opacity: 0.95;
		}

		.btn-cta {
			padding: 1rem 2.5rem;
			font-size: 1.1rem;
			font-weight: 600;
			background: var(--white);
			color: var(--secondary);
			border: none;
			border-radius: 50px;
			cursor: pointer;
			transition: all 0.3s ease;
			text-decoration: none;
			display: inline-block;
			box-shadow: 0 8px 25px rgba(0,0,0,0.15);
		}

		.btn-cta:hover {
			transform: translateY(-3px);
			box-shadow: 0 12px 35px rgba(0,0,0,0.25);
			color: var(--secondary);
		}

		/* Footer */
		footer {
			background: var(--primary);
			color: var(--white);
			padding: 3rem 0 1rem;
		}

		.footer-section h5 {
			font-weight: 700;
			margin-bottom: 1.5rem;
			font-size: 1.1rem;
		}

		.footer-section ul {
			list-style: none;
		}

		.footer-section ul li {
			margin-bottom: 0.8rem;
		}

		.footer-section a {
			color: rgba(255,255,255,0.8);
			text-decoration: none;
			transition: all 0.3s ease;
		}

		.footer-section a:hover {
			color: var(--secondary);
			padding-left: 5px;
		}

		.footer-bottom {
			border-top: 1px solid rgba(255,255,255,0.1);
			padding-top: 2rem;
			margin-top: 2rem;
			text-align: center;
			opacity: 0.85;
		}

		/* Responsive */
		@media (max-width: 768px) {
			.hero h1 {
				font-size: 2.2rem;
			}

			.hero .subtitle {
				font-size: 1rem;
			}

			.hero-buttons {
				flex-direction: column;
			}

			.btn-primary-custom {
				width: 100%;
				justify-content: center;
			}

			.section-title {
				font-size: 2rem;
			}

			.hero-content {
				padding: 0 1rem;
			}
		}
	</style>
</head>
<body>
	<!-- Navbar -->
	<nav class="navbar navbar-expand-lg sticky-top">
		<div class="container-fluid">
			<a class="navbar-brand" href="/">
				<i class="fas fa-building"></i>
				LGU IPMS
			</a>
			<button class="navbar-toggler bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNav">
				<ul class="navbar-nav ms-auto">
					<li class="nav-item">
						<a class="nav-link" href="#features"><i class="fas fa-star"></i> Features</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="#about"><i class="fas fa-info-circle"></i> About</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="/admin/admin.php"><i class="fas fa-sign-in-alt"></i> Login</a>
					</li>
				</ul>
			</div>
		</div>
	</nav>

	<!-- Hero Section -->
	<section class="hero">
		<div class="hero-content">
			<h1><i class="fas fa-rocket"></i> Infrastructure Made Simple</h1>
			<p class="subtitle">Manage projects, track progress, and serve your community with modern technology</p>
			<div class="hero-buttons">
				<a href="/user-dashboard/user-login.php" class="btn-primary-custom btn-citizen">
					<i class="fas fa-user-circle"></i> Citizen Access
				</a>
				<a href="/admin/admin.php" class="btn-primary-custom btn-employee">
					<i class="fas fa-briefcase"></i> Employee Access
				</a>
			</div>
		</div>
	</section>

	<!-- Features Section -->
	<section class="features" id="features">
		<div class="container">
			<h2 class="section-title">Core Features</h2>
			<p class="section-subtitle">Everything you need to manage infrastructure projects efficiently</p>
			
			<div class="row g-4">
				<div class="col-md-6 col-lg-4">
					<div class="feature-card">
						<div class="feature-icon">
							<i class="fas fa-tasks"></i>
						</div>
						<h5>Project Management</h5>
						<p>Create, track, and manage infrastructure projects with detailed timelines and milestones</p>
					</div>
				</div>

				<div class="col-md-6 col-lg-4">
					<div class="feature-card">
						<div class="feature-icon">
							<i class="fas fa-chart-line"></i>
						</div>
						<h5>Progress Tracking</h5>
						<p>Real-time progress updates and monitoring of project completion status</p>
					</div>
				</div>

				<div class="col-md-6 col-lg-4">
					<div class="feature-card">
						<div class="feature-icon">
							<i class="fas fa-money-bill-wave"></i>
						</div>
						<h5>Budget Management</h5>
						<p>Comprehensive budget tracking and expense management for all projects</p>
					</div>
				</div>

				<div class="col-md-6 col-lg-4">
					<div class="feature-card">
						<div class="feature-icon">
							<i class="fas fa-users"></i>
						</div>
						<h5>Team Collaboration</h5>
						<p>Work together seamlessly with contractors and team members</p>
					</div>
				</div>

				<div class="col-md-6 col-lg-4">
					<div class="feature-card">
						<div class="feature-icon">
							<i class="fas fa-comments"></i>
						</div>
						<h5>Community Feedback</h5>
						<p>Collect and manage feedback directly from citizens and stakeholders</p>
					</div>
				</div>

				<div class="col-md-6 col-lg-4">
					<div class="feature-card">
						<div class="feature-icon">
							<i class="fas fa-file-alt"></i>
						</div>
						<h5>Reporting & Analytics</h5>
						<p>Generate comprehensive reports and gain insights into project performance</p>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Stats Section -->
	<section class="stats">
		<div class="container">
			<div class="row">
				<div class="col-md-3 col-6">
					<div class="stat-box">
						<div class="stat-number">250+</div>
						<div class="stat-label">Active Projects</div>
					</div>
				</div>
				<div class="col-md-3 col-6">
					<div class="stat-box">
						<div class="stat-number">95%</div>
						<div class="stat-label">Success Rate</div>
					</div>
				</div>
				<div class="col-md-3 col-6">
					<div class="stat-box">
						<div class="stat-number">1000+</div>
						<div class="stat-label">Registered Users</div>
					</div>
				</div>
				<div class="col-md-3 col-6">
					<div class="stat-box">
						<div class="stat-number">24/7</div>
						<div class="stat-label">System Support</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Benefits Section -->
	<section class="benefits" id="about">
		<div class="container">
			<h2 class="section-title">Why Choose Our System?</h2>
			<p class="section-subtitle">Transform how you manage community infrastructure</p>

			<div class="row">
				<div class="col-lg-6">
					<div class="benefit-item">
						<div class="benefit-icon">
							<i class="fas fa-shield-alt"></i>
						</div>
						<div class="benefit-content">
							<h4>Secure & Reliable</h4>
							<p>Enterprise-grade security ensures your data and citizen information is always protected</p>
						</div>
					</div>

					<div class="benefit-item">
						<div class="benefit-icon">
							<i class="fas fa-mobile-alt"></i>
						</div>
						<div class="benefit-content">
							<h4>Mobile Friendly</h4>
							<p>Access the system anytime, anywhere from any device with responsive design</p>
						</div>
					</div>

					<div class="benefit-item">
						<div class="benefit-icon">
							<i class="fas fa-bolt"></i>
						</div>
						<div class="benefit-content">
							<h4>Fast & Efficient</h4>
							<p>Optimized performance ensures quick loading and smooth operations</p>
						</div>
					</div>
				</div>

				<div class="col-lg-6">
					<div class="benefit-item">
						<div class="benefit-icon">
							<i class="fas fa-graduation-cap"></i>
						</div>
						<div class="benefit-content">
							<h4>Easy to Use</h4>
							<p>Intuitive interface requires minimal training for staff and citizens</p>
						</div>
					</div>

					<div class="benefit-item">
						<div class="benefit-icon">
							<i class="fas fa-chart-pie"></i>
						</div>
						<div class="benefit-content">
							<h4>Data Insights</h4>
							<p>Actionable analytics help you make better decisions for your community</p>
						</div>
					</div>

					<div class="benefit-item">
						<div class="benefit-icon">
							<i class="fas fa-headset"></i>
						</div>
						<div class="benefit-content">
							<h4>Expert Support</h4>
							<p>Dedicated support team ready to help with any questions or issues</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- CTA Section -->
	<section class="cta">
		<div class="container">
			<h2>Ready to Get Started?</h2>
			<p>Join thousands of communities managing infrastructure efficiently</p>
			<a href="/admin/admin.php" class="btn-cta">
				<i class="fas fa-arrow-right"></i> Access Portal Now
			</a>
		</div>
	</section>

	<!-- Footer -->
	<footer>
		<div class="container">
			<div class="row">
				<div class="col-md-3">
					<div class="footer-section">
						<h5><i class="fas fa-building"></i> LGU IPMS</h5>
						<p style="font-size: 0.95rem; opacity: 0.85;">Modernizing local government infrastructure management</p>
					</div>
				</div>
				<div class="col-md-3">
					<div class="footer-section">
						<h5>Quick Links</h5>
						<ul>
							<li><a href="/admin/admin.php">Employee Login</a></li>
							<li><a href="/user-dashboard/user-login.php">Citizen Login</a></li>
							<li><a href="#features">Features</a></li>
							<li><a href="#about">About</a></li>
						</ul>
					</div>
				</div>
				<div class="col-md-3">
					<div class="footer-section">
						<h5>Support</h5>
						<ul>
							<li><a href="#">Contact Us</a></li>
							<li><a href="#">Help Center</a></li>
							<li><a href="#">FAQ</a></li>
							<li><a href="#">Report Issues</a></li>
						</ul>
					</div>
				</div>
				<div class="col-md-3">
					<div class="footer-section">
						<h5>Follow Us</h5>
						<ul style="display: flex; gap: 1rem;">
							<li><a href="#" style="padding: 0;"><i class="fab fa-facebook"></i></a></li>
							<li><a href="#" style="padding: 0;"><i class="fab fa-twitter"></i></a></li>
							<li><a href="#" style="padding: 0;"><i class="fab fa-linkedin"></i></a></li>
							<li><a href="#" style="padding: 0;"><i class="fab fa-instagram"></i></a></li>
						</ul>
					</div>
				</div>
			</div>

			<div class="footer-bottom">
				<p>&copy; 2026 Local Government Unit Infrastructure Project Management System. All Rights Reserved.</p>
				<p style="font-size: 0.85rem; margin-top: 0.5rem;">
					<a href="#" style="color: var(--secondary); text-decoration: none; padding: 0;">Privacy Policy</a> | 
					<a href="#" style="color: var(--secondary); text-decoration: none; padding: 0;">Terms of Service</a> | 
					<a href="#" style="color: var(--secondary); text-decoration: none; padding: 0;">Accessibility</a>
				</p>
			</div>
		</div>
	</footer>

	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
	<!-- Main JS -->
	<script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
	<script>
		// Smooth scroll for anchor links
		document.querySelectorAll('a[href^="#"]').forEach(anchor => {
			anchor.addEventListener('click', function (e) {
				e.preventDefault();
				const target = document.querySelector(this.getAttribute('href'));
				if (target) {
					target.scrollIntoView({
						behavior: 'smooth',
						block: 'start'
					});
				}
			});
		});

		// Navbar background on scroll
		window.addEventListener('scroll', function() {
			const navbar = document.querySelector('.navbar');
			if (window.scrollY > 50) {
				navbar.style.boxShadow = '0 8px 25px rgba(0,0,0,0.3)';
			} else {
				navbar.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';
			}
		});
	</script>
</body>
</html>
