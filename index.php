<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
	<title>LGU Citizen Portal</title>
	<link rel="icon" type="image/png" href="assets/road.jpeg" />
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css?family=Merriweather+Sans:400,700" rel="stylesheet" />
	<style>
		body {
			min-height: 100vh;
			background: linear-gradient(120deg, #2980b9, #6dd5fa, #ffffff);
			font-family: 'Merriweather Sans', Arial, sans-serif;
		}
		.masthead {
			background: url('assets/road.jpeg') center/cover no-repeat;
			min-height: 60vh;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #fff;
			text-shadow: 0 2px 8px rgba(0,0,0,0.3);
		}
		.masthead h1 {
			font-size: 2.5rem;
			font-weight: bold;
		}
		.masthead p {
			font-size: 1.2rem;
		}
		.feature-img {
			width: 100%;
			max-width: 320px;
			border-radius: 12px;
			margin: 1rem auto;
			box-shadow: 0 2px 12px rgba(0,0,0,0.08);
		}
		.btn-login {
			background: #2980b9;
			color: #fff;
			border: none;
			padding: 14px 32px;
			border-radius: 6px;
			font-size: 1.1rem;
			cursor: pointer;
			transition: background 0.2s;
			text-decoration: none;
			margin-top: 1.5rem;
		}
		.btn-login:hover {
			background: #1a3c5d;
		}
		.section-title {
			color: #1a3c5d;
			margin-top: 2rem;
			margin-bottom: 1rem;
		}
		.facility-img {
			width: 100%;
			max-width: 200px;
			border-radius: 8px;
			margin-bottom: 1rem;
		}
		.footer {
			background: #f8f9fa;
			color: #888;
			text-align: center;
			padding: 1.5rem 0 0.5rem 0;
			margin-top: 2rem;
		}
	</style>
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
		<div class="container">
			<a class="navbar-brand fw-bold" href="#">LGU Citizen Portal</a>
		</div>
	</nav>
	<header class="masthead">
		<div class="container text-center">
			<h1>Welcome to LGU Infrastructure & Utilities Services</h1>
			<p>Access community infrastructure maintenance requests and track progress securely.</p>
			<a href="user-dashboard/user-login.php" class="btn-login">User Login</a>
		</div>
	</header>
	<section class="container text-center">
		<h2 class="section-title">Community Facilities & Services</h2>
		<div class="row justify-content-center">
			<div class="col-md-3 col-6 mb-4">
				<img src="assets/road.jpeg" alt="Road" class="facility-img">
				<div>Efficient Service</div>
			</div>
			<div class="col-md-3 col-6 mb-4">
				<img src="assets/drainage.jpg" alt="Drainage" class="facility-img">
				<div>Sustainability</div>
			</div>
			<div class="col-md-3 col-6 mb-4">
				<img src="assets/construction.jpg" alt="Construction" class="facility-img">
				<div>Strong Management</div>
			</div>
			<div class="col-md-3 col-6 mb-4">
				<img src="assets/bridge.jpg" alt="Bridge" class="facility-img">
				<div>Digital Innovation</div>
			</div>
		</div>
	</section>
	<footer class="footer">
		<div>© 2026 LGU Citizen Portal · All Rights Reserved</div>
	</footer>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
