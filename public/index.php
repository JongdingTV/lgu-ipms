<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once INCLUDES_PATH . '/helpers.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>LGU Infrastructure & Project Management System</title>
    <meta name="description" content="A modern and user-friendly portal for LGU project registration, monitoring, and transparency.">
    <meta name="theme-color" content="#0f2f57">
    <link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/images/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --bg: #f5f8fc;
            --surface: #ffffff;
            --ink: #0f2138;
            --ink-soft: #4c607b;
            --brand: #1f4c87;
            --brand-2: #2f6bb8;
            --accent: #f2a63a;
            --line: #d9e4f2;
            --success: #1fa468;
            --shadow: 0 14px 32px rgba(10, 33, 64, 0.16);
            --radius: 18px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: "Plus Jakarta Sans", sans-serif;
            background: var(--bg);
            color: var(--ink);
        }

        .top-nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(15, 47, 87, 0.94);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .nav-wrap {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0.85rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            text-decoration: none;
            color: #fff;
            font-weight: 800;
            letter-spacing: 0.2px;
        }

        .brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .nav-links {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.92);
            text-decoration: none;
            padding: 0.55rem 0.9rem;
            border-radius: 10px;
            font-size: 0.93rem;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.14);
        }

        .hero {
            position: relative;
            min-height: calc(100vh - 66px);
            display: flex;
            align-items: center;
            padding: 3rem 1rem 4rem;
            background:
                linear-gradient(120deg, rgba(15, 47, 87, 0.84) 0%, rgba(26, 73, 128, 0.78) 50%, rgba(18, 59, 107, 0.82) 100%),
                url('/cityhall.jpeg') center/cover no-repeat;
            overflow: hidden;
        }

        .hero::before,
        .hero::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            filter: blur(2px);
        }

        .hero::before {
            width: 460px;
            height: 460px;
            right: -120px;
            top: -120px;
            background: rgba(242, 166, 58, 0.22);
        }

        .hero::after {
            width: 360px;
            height: 360px;
            left: -100px;
            bottom: -130px;
            background: rgba(142, 197, 255, 0.18);
        }

        .hero-wrap {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            display: grid;
            gap: 1.5rem;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: center;
        }

        .hero-copy {
            color: #f8fbff;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.4);
            padding: 0.35rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .hero h1 {
            font-size: clamp(1.9rem, 4.5vw, 3.6rem);
            line-height: 1.15;
            margin-bottom: 1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            max-width: 13ch;
        }

        .hero p {
            max-width: 56ch;
            color: rgba(244, 248, 255, 0.92);
            font-size: clamp(0.98rem, 2vw, 1.12rem);
            line-height: 1.72;
            margin-bottom: 1.6rem;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .btn {
            border: 0;
            text-decoration: none;
            border-radius: 12px;
            padding: 0.78rem 1rem;
            font-weight: 700;
            font-size: 0.93rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-citizen {
            background: linear-gradient(135deg, #ffbe5a, #f39b1f);
            color: #102944;
            box-shadow: 0 8px 18px rgba(242, 166, 58, 0.35);
        }

        .btn-ghost {
            background: rgba(255, 255, 255, 0.14);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.45);
        }

        .hero-panel {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.2rem;
        }

        .hero-panel h3 {
            color: var(--ink);
            font-size: 1.05rem;
            margin-bottom: 0.7rem;
            font-weight: 800;
        }

        .hero-panel ul {
            list-style: none;
            display: grid;
            gap: 0.7rem;
        }

        .hero-panel li {
            color: #274262;
            font-weight: 600;
            font-size: 0.9rem;
            background: #eef5ff;
            border: 1px solid #d6e6fa;
            border-radius: 12px;
            padding: 0.65rem 0.75rem;
            display: flex;
            gap: 0.55rem;
            align-items: flex-start;
        }

        .hero-panel i {
            color: var(--brand-2);
            margin-top: 0.1rem;
        }

        .section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 1rem;
        }

        .section-title {
            font-size: clamp(1.45rem, 2.2vw, 2.1rem);
            color: var(--ink);
            margin-bottom: 0.35rem;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .section-sub {
            color: var(--ink-soft);
            max-width: 60ch;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .card {
            border-radius: 14px;
            background: var(--surface);
            border: 1px solid var(--line);
            padding: 1rem;
            box-shadow: 0 8px 18px rgba(17, 41, 71, 0.06);
        }

        .card .ico {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.65rem;
            background: #e8f1ff;
            color: #1e5ba0;
        }

        .card h4 {
            font-size: 1rem;
            margin-bottom: 0.35rem;
            color: var(--ink);
        }

        .card p {
            color: var(--ink-soft);
            font-size: 0.9rem;
            line-height: 1.65;
        }

        .process {
            background: linear-gradient(180deg, #eef4fc 0%, #f8fbff 100%);
        }

        .process-row {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .step {
            background: #fff;
            border: 1px solid #dce8f8;
            border-radius: 14px;
            padding: 1rem;
        }

        .step .tag {
            display: inline-block;
            border-radius: 999px;
            padding: 0.25rem 0.6rem;
            background: #eef5ff;
            color: #1f4c87;
            font-size: 0.72rem;
            font-weight: 700;
            margin-bottom: 0.55rem;
        }

        .step h5 {
            font-size: 1rem;
            margin-bottom: 0.35rem;
            color: #142d49;
        }

        .step p {
            color: #4f6583;
            font-size: 0.9rem;
            line-height: 1.65;
        }

        .footer {
            background: #0f2f57;
            color: rgba(245, 250, 255, 0.92);
            padding: 1.2rem 1rem 1.4rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .floating-employee-login {
            position: fixed;
            right: 1.1rem;
            bottom: 1.1rem;
            z-index: 1200;
            width: min(92vw, 330px);
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid #d2e2f7;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(10, 33, 64, 0.28);
            overflow: hidden;
        }

        .floating-head {
            background: linear-gradient(135deg, #1f4c87, #2f6bb8);
            color: #fff;
            padding: 0.85rem 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .floating-head i {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .floating-head strong {
            font-size: 0.95rem;
            font-weight: 800;
        }

        .floating-body {
            padding: 0.9rem 0.95rem;
        }

        .floating-body p {
            color: #3b5578;
            font-size: 0.88rem;
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }

        .floating-login-btn {
            width: 100%;
            justify-content: center;
            background: linear-gradient(135deg, #1f4c87, #2f6bb8);
            color: #fff;
            border-radius: 10px;
            padding: 0.72rem 0.8rem;
            font-size: 0.9rem;
            box-shadow: 0 9px 18px rgba(31, 76, 135, 0.3);
        }

        .floating-login-btn:hover {
            color: #fff;
        }

        @media (max-width: 1024px) {
            .hero-wrap {
                grid-template-columns: 1fr;
            }

            .hero-panel {
                max-width: 540px;
            }

            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .nav-wrap {
                flex-wrap: wrap;
            }

            .nav-links {
                width: 100%;
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 0.25rem;
            }

            .hero {
                min-height: auto;
                padding-top: 2rem;
            }

            .hero h1 {
                max-width: none;
            }

            .hero-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .grid,
            .process-row {
                grid-template-columns: 1fr;
            }

            .floating-employee-login {
                position: static;
                width: calc(100% - 2rem);
                margin: 0 auto 1.4rem;
                box-shadow: 0 12px 24px rgba(10, 33, 64, 0.18);
            }
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="nav-wrap">
            <a class="brand" href="/">
                <img src="/logocityhall.png" alt="LGU Logo">
                <span>LGU IPMS</span>
            </a>
            <div class="nav-links">
                <a href="#features"><i class="fa-solid fa-star"></i> Features</a>
                <a href="#process"><i class="fa-solid fa-route"></i> Workflow</a>
                <a href="/user-dashboard/user-login.php"><i class="fa-solid fa-users"></i> Citizen Login</a>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-wrap">
            <div class="hero-copy">
                <span class="hero-pill"><i class="fa-solid fa-circle-check"></i> Digital LGU Service Platform</span>
                <h1>Build trust with transparent and trackable public projects.</h1>
                <p>
                    The Infrastructure & Project Management System helps your LGU register projects, monitor progress,
                    control budgets, and keep citizens informed using one clean and reliable dashboard.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-citizen" href="/user-dashboard/user-login.php">
                        <i class="fa-solid fa-arrow-right"></i> Access Citizen Portal
                    </a>
                    <a class="btn btn-ghost" href="#features">
                        <i class="fa-solid fa-circle-info"></i> Explore Features
                    </a>
                </div>
            </div>

            <aside class="hero-panel" aria-label="Highlights">
                <h3>What You Can Do Here</h3>
                <ul>
                    <li><i class="fa-solid fa-diagram-project"></i><span>Register infrastructure projects with full details and location.</span></li>
                    <li><i class="fa-solid fa-chart-line"></i><span>Track milestones and implementation updates in real-time.</span></li>
                    <li><i class="fa-solid fa-wallet"></i><span>Link project budgets and expense tracking to avoid overspending.</span></li>
                    <li><i class="fa-solid fa-shield-halved"></i><span>Use role-based access and secure login for protected operations.</span></li>
                </ul>
            </aside>
        </div>
    </section>

    <section id="features" class="section">
        <h2 class="section-title">Core Modules</h2>
        <p class="section-sub">A user-friendly structure for both administrators and citizens, designed for daily government operations on desktop and mobile.</p>
        <div class="grid">
            <article class="card">
                <span class="ico"><i class="fa-solid fa-folder-plus"></i></span>
                <h4>Project Registration</h4>
                <p>Create and organize projects with clear metadata, sector classification, and target locations.</p>
            </article>
            <article class="card">
                <span class="ico"><i class="fa-solid fa-sliders"></i></span>
                <h4>Progress Monitoring</h4>
                <p>Review implementation status, updates, and timelines from a centralized monitoring area.</p>
            </article>
            <article class="card">
                <span class="ico"><i class="fa-solid fa-coins"></i></span>
                <h4>Budget & Resources</h4>
                <p>Reflect estimated project budget, monitor expenses, and keep fund data consistent per project.</p>
            </article>
            <article class="card">
                <span class="ico"><i class="fa-solid fa-comments"></i></span>
                <h4>Citizen Visibility</h4>
                <p>Provide transparent updates so community members can follow public infrastructure progress.</p>
            </article>
        </div>
    </section>

    <section id="process" class="section process">
        <h2 class="section-title">Simple Workflow</h2>
        <p class="section-sub">Designed to fit your actual LGU flow from registration to reporting.</p>
        <div class="process-row">
            <article class="step">
                <span class="tag">Step 1</span>
                <h5>Register Project</h5>
                <p>Encode project name, sector, location, timeline, and estimated budget in one form.</p>
            </article>
            <article class="step">
                <span class="tag">Step 2</span>
                <h5>Track Implementation</h5>
                <p>Update status and milestones while keeping all records visible for internal monitoring.</p>
            </article>
            <article class="step">
                <span class="tag">Step 3</span>
                <h5>Manage Budget Use</h5>
                <p>Record expenses per registered project and keep usage aligned with allocated funds.</p>
            </article>
        </div>
    </section>

    <div class="floating-employee-login" aria-label="Employee login shortcut">
        <div class="floating-head">
            <i class="fa-solid fa-user-shield"></i>
            <strong>Employee Login</strong>
        </div>
        <div class="floating-body">
            <p>For LGU staff and authorized users. Access project operations, monitoring, and administration tools.</p>
            <a class="btn floating-login-btn" href="/admin/index.php">
                <i class="fa-solid fa-right-to-bracket"></i> Open Employee Portal
            </a>
        </div>
    </div>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit Infrastructure Project Management System</p>
    </footer>
</body>
</html>
