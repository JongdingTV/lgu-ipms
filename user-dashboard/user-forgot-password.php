<?php
require dirname(__DIR__) . '/session-auth.php';
require dirname(__DIR__) . '/config-path.php';

set_no_cache_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LGU | Forgot Password</title>
<link rel="icon" type="image/png" href="/logocityhall.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/shared/admin-auth.css">
<?php echo get_app_config_script(); ?>
<style>
body { min-height: 100vh; margin: 0; display: flex; flex-direction: column; padding-top: 88px; color: #0f172a; background: radial-gradient(circle at 15% 15%, rgba(63, 131, 201, 0.28), transparent 40%), radial-gradient(circle at 85% 85%, rgba(29, 78, 137, 0.26), transparent 45%), linear-gradient(125deg, rgba(7, 20, 36, 0.72), rgba(15, 42, 74, 0.68)), url("/cityhall.jpeg") center/cover fixed no-repeat; }
.nav { position: fixed; inset: 0 0 auto 0; width: 100%; height: 78px; padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; background: linear-gradient(90deg, rgba(255,255,255,0.94), rgba(247,251,255,0.98)); border-bottom: 1px solid rgba(15,23,42,.12); box-shadow: 0 12px 30px rgba(2,6,23,.12); z-index: 30; }
.nav-logo { display: inline-flex; align-items: center; gap: 10px; font-size: .98rem; font-weight: 700; color: #0f2a4a; }
.nav-logo img { width: 44px; height: 44px; object-fit: contain; }
.home-btn { display: inline-flex; align-items: center; justify-content: center; padding: 9px 16px; border-radius: 10px; border: 1px solid rgba(29,78,137,.22); text-decoration: none; font-weight: 600; color: #1d4e89; background: #fff; }
.wrapper { width: 100%; flex: 1; display: flex; justify-content: center; align-items: center; padding: 30px 16px 36px; }
.card { width: 100%; max-width: 480px; background: rgba(255,255,255,.95); border: 1px solid rgba(255,255,255,.75); border-radius: 20px; padding: 30px 26px; text-align: center; box-shadow: 0 24px 56px rgba(2,6,23,.3); }
.btn-primary { display: inline-block; margin-top: 18px; border: 0; border-radius: 11px; background: linear-gradient(135deg, #1d4e89, #3f83c9); color: #fff; text-decoration: none; font-size: .95rem; font-weight: 600; padding: 12px 20px; }
.notice { margin-top: 16px; padding: 12px; border-radius: 10px; text-align: left; background: #dbeafe; color: #1e3a8a; border: 1px solid #bfdbfe; }
</style>
</head>
<body>
<header class="nav"><div class="nav-logo"><img src="/logocityhall.png" alt="LGU Logo"> Local Government Unit Portal</div><a href="/public/index.php" class="home-btn" aria-label="Go to Home">Home</a></header>
<div class="wrapper"><div class="card"><img src="/logocityhall.png" class="icon-top" alt="LGU Logo" style="width:72px;height:72px;object-fit:contain;"><h2 style="margin:8px 0 10px;color:#0f2a4a;">Forgot Password</h2><p style="margin:0;color:#475569;">Password reset for citizen accounts is not yet available.</p><div class="notice">A full reset flow will be added soon. For now, contact the LGU support desk to recover your account.</div><a href="/user-dashboard/user-login.php" class="btn-primary">Back to Login</a></div></div>
</body>
</html>
