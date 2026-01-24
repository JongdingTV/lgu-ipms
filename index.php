<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>LGU-IPMS | Landing Page</title>
	<link rel="stylesheet" href="assets/style.css">
	<style>
		body {
			margin: 0;
			padding: 0;
			font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
			background: linear-gradient(120deg, #2980b9, #6dd5fa, #ffffff);
			min-height: 100vh;
		}
		.landing-container {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
			text-align: center;
		}
		.logo {
			width: 120px;
			margin-bottom: 24px;
		}
		h1 {
			font-size: 2.8rem;
			color: #1a3c5d;
			margin-bottom: 12px;
		}
		p {
			font-size: 1.2rem;
			color: #333;
			margin-bottom: 32px;
		}
		.landing-btns {
			display: flex;
			gap: 20px;
			flex-wrap: wrap;
			justify-content: center;
		}
		.landing-btn {
			background: #2980b9;
			color: #fff;
			border: none;
			padding: 14px 32px;
			border-radius: 6px;
			font-size: 1.1rem;
			cursor: pointer;
			transition: background 0.2s;
			text-decoration: none;
		}
		.landing-btn:hover {
			background: #1a3c5d;
		}
		@media (max-width: 600px) {
			h1 { font-size: 2rem; }
			.landing-btn { padding: 12px 20px; font-size: 1rem; }
		}
	</style>
</head>
<body>
	<div class="landing-container">
		<img src="assets/logo.png" alt="LGU-IPMS Logo" class="logo" onerror="this.style.display='none'">
		<h1>Welcome to LGU-IPMS</h1>
		<p>Local Government Unit Infrastructure Project Monitoring System</p>
		<div class="landing-btns">
			<a href="dashboard/dashboard.php" class="landing-btn">Admin Login</a>
			<a href="user-dashboard/index.php" class="landing-btn">User Portal</a>
		</div>
	</div>
</body>
</html>
