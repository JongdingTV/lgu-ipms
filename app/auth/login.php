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
require_once CONFIG_PATH . '/database.php';

// Set no-cache headers to prevent cached login page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$error = '';
$loginType = $_GET['type'] ?? 'employee'; // 'employee' or 'citizen'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim($_POST['email'] ?? '');
	$password = trim($_POST['password'] ?? '');
	
	// Validation
	if (empty($email) || empty($password)) {
		$error = 'Email and password are required.';
	} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$error = 'Please enter a valid email address.';
	} else {
		// Attempt login
		try {
			if ($loginType === 'employee') {
				$result = authenticate_employee($email, $password);
				if ($result['success']) {
					// Set session
					$_SESSION['user_id'] = $result['user_id'];
					$_SESSION['user_name'] = $result['user_name'];
					$_SESSION['user_type'] = 'employee';
					$_SESSION['user_email'] = $email;
					
					// Regenerate session ID for security
					session_regenerate_id(true);
					
					// Redirect to admin dashboard
					header('Location: ' . asset('../../app/admin/dashboard.php'));
					exit;
				} else {
					$error = 'Invalid email or password.';
				}
			} else {
				// Citizen login
				$result = authenticate_citizen($email, $password);
				if ($result['success']) {
					$_SESSION['user_id'] = $result['user_id'];
					$_SESSION['user_name'] = $result['user_name'];
					$_SESSION['user_type'] = 'citizen';
					$_SESSION['user_email'] = $email;
					
					// Regenerate session ID for security
					session_regenerate_id(true);
					
					// Redirect to citizen dashboard
					header('Location: ' . asset('../../app/user/dashboard.php'));
					exit;
				} else {
					$error = 'Invalid email or password.';
				}
			}
		} catch (Exception $e) {
			$error = 'An error occurred during login. Please try again.';
			error_log('Login error: ' . $e->getMessage());
		}
	}
}

// Define page title
$pageTitle = $loginType === 'employee' ? 'Employee Login' : 'Citizen Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>LGU IPMS | <?php echo htmlspecialchars($pageTitle); ?></title>
	<link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/images/logo.png">
	
	<!-- Bootstrap CSS -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
	<!-- Google Fonts -->
	<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<!-- Main CSS -->
	<link href="<?php echo ASSETS_URL; ?>/css/main.css" rel="stylesheet" />
	
	<style>
		body {
			min-height: 100vh;
			display: flex;
			flex-direction: column;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			font-family: 'Poppins', sans-serif;
			padding-top: 0;
		}
		
		.login-container {
			flex: 1;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 2rem;
		}
		
		.login-box {
			background: #fff;
			border-radius: 15px;
			box-shadow: 0 10px 40px rgba(0,0,0,0.2);
			width: 100%;
			max-width: 450px;
			padding: 2.5rem;
			animation: slideUp 0.5s ease-out;
		}
		
		@keyframes slideUp {
			from {
				opacity: 0;
				transform: translateY(30px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		
		.login-header {
			text-align: center;
			margin-bottom: 2rem;
		}
		
		.login-header h1 {
			font-size: 1.8rem;
			font-weight: 700;
			color: #2c3e50;
			margin-bottom: 0.5rem;
		}
		
		.login-header p {
			color: #7f8c8d;
			font-size: 0.95rem;
		}
		
		.form-group {
			margin-bottom: 1.5rem;
		}
		
		.form-group label {
			font-weight: 600;
			color: #2c3e50;
			margin-bottom: 0.5rem;
			display: block;
		}
		
		.form-control {
			border: 1px solid #ddd;
			border-radius: 8px;
			padding: 0.75rem 1rem;
			font-size: 1rem;
			transition: all 0.3s ease;
		}
		
		.form-control:focus {
			border-color: #667eea;
			box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
		}
		
		.form-control::placeholder {
			color: #bdc3c7;
		}
		
		.btn-login {
			width: 100%;
			padding: 0.75rem;
			border: none;
			border-radius: 8px;
			font-size: 1rem;
			font-weight: 600;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: #fff;
			cursor: pointer;
			transition: all 0.3s ease;
			margin-top: 1.5rem;
		}
		
		.btn-login:hover {
			transform: translateY(-2px);
			box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
		}
		
		.btn-login:active {
			transform: translateY(0);
		}
		
		.alert {
			border-radius: 8px;
			margin-bottom: 1.5rem;
			animation: slideDown 0.3s ease-out;
		}
		
		@keyframes slideDown {
			from {
				opacity: 0;
				transform: translateY(-10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		
		.login-footer {
			text-align: center;
			margin-top: 1.5rem;
			padding-top: 1.5rem;
			border-top: 1px solid #eee;
			font-size: 0.9rem;
		}
		
		.login-footer a {
			color: #667eea;
			text-decoration: none;
			font-weight: 600;
		}
		
		.login-footer a:hover {
			text-decoration: underline;
		}
		
		.login-tabs {
			display: flex;
			gap: 0;
			margin-bottom: 2rem;
		}
		
		.login-tab {
			flex: 1;
			padding: 0.75rem;
			border: 2px solid #ddd;
			background: #f8f9fa;
			cursor: pointer;
			text-align: center;
			font-weight: 600;
			color: #7f8c8d;
			transition: all 0.3s ease;
		}
		
		.login-tab.active {
			background: #667eea;
			color: #fff;
			border-color: #667eea;
		}
		
		.login-tab:first-child {
			border-radius: 8px 0 0 8px;
		}
		
		.login-tab:last-child {
			border-radius: 0 8px 8px 0;
		}
		
		.checkbox-group {
			display: flex;
			align-items: center;
			margin: 1rem 0;
		}
		
		.checkbox-group input[type="checkbox"] {
			width: auto;
			margin-right: 0.5rem;
		}
		
		.checkbox-group label {
			margin: 0;
			cursor: pointer;
			color: #7f8c8d;
			font-weight: normal;
			font-size: 0.9rem;
		}
		
		.back-link {
			display: inline-block;
			margin-bottom: 1.5rem;
		}
		
		.back-link a {
			color: #667eea;
			text-decoration: none;
			font-weight: 600;
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}
		
		.back-link a:hover {
			text-decoration: underline;
		}
	</style>
</head>
<body>
	<div class="login-container">
		<div class="login-box">
			<!-- Back Link -->
			<div class="back-link">
				<a href="<?php echo asset('../../../public/index.php'); ?>">
					<span>←</span> Back to Home
				</a>
			</div>
			
			<!-- Login Header -->
			<div class="login-header">
				<h1><?php echo htmlspecialchars($pageTitle); ?></h1>
				<p>Secure Access to LGU IPMS</p>
			</div>
			
			<!-- Login Type Tabs -->
			<div class="login-tabs">
				<div class="login-tab <?php echo $loginType === 'employee' ? 'active' : ''; ?>" 
				     onclick="window.location='<?php echo asset_url('login.php?type=employee'); ?>'">
					<i class="fas fa-id-badge"></i> Employee
				</div>
				<div class="login-tab <?php echo $loginType === 'citizen' ? 'active' : ''; ?>" 
				     onclick="window.location='<?php echo asset_url('login.php?type=citizen'); ?>'">
					<i class="fas fa-user"></i> Citizen
				</div>
			</div>
			
			<!-- Error Message -->
			<?php if (!empty($error)): ?>
				<div class="alert alert-danger alert-dismissible fade show" role="alert">
					<i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				</div>
			<?php endif; ?>
			
			<!-- Login Form -->
			<form method="POST" id="loginForm">
				<div class="form-group">
					<label for="email">
						<i class="fas fa-envelope"></i> Email Address
					</label>
					<input 
						type="email" 
						class="form-control" 
						id="email" 
						name="email" 
						placeholder="your.email@example.com"
						required
						autofocus
						value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
					>
				</div>
				
				<div class="form-group">
					<label for="password">
						<i class="fas fa-lock"></i> Password
					</label>
					<input 
						type="password" 
						class="form-control" 
						id="password" 
						name="password" 
						placeholder="Enter your password"
						required
					>
				</div>
				
				<div class="checkbox-group">
					<input type="checkbox" id="remember" name="remember" value="1">
					<label for="remember">Remember me on this device</label>
				</div>
				
				<button type="submit" class="btn-login">
					<i class="fas fa-sign-in-alt"></i> Sign In
				</button>
			</form>
			
			<!-- Footer Links -->
			<div class="login-footer">
				<p>
					<a href="#forgot">Forgot Password?</a> | 
					<a href="<?php echo asset_url('register.php?type=' . htmlspecialchars($loginType)); ?>">Create Account</a>
				</p>
				<p style="margin-top: 1rem; color: #95a5a6;">
					<?php echo $loginType === 'employee' ? 
						'Need a citizen account? <a href="' . asset_url('login.php?type=citizen') . '">Switch to Citizen Login</a>' :
						'Employee access? <a href="' . asset_url('login.php?type=employee') . '">Switch to Employee Login</a>'
					; ?>
				</p>
			</div>
		</div>
	</div>
	
	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
	<!-- Font Awesome -->
	<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
	<!-- Main JS -->
	<script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>
