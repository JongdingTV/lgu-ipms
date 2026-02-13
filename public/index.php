<?php
require_once dirname(__DIR__) . '/config/app.php';
require_once INCLUDES_PATH . '/helpers.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function landing_generate_csrf_token(): string
{
    if (empty($_SESSION['landing_csrf_token'])) {
        $_SESSION['landing_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['landing_csrf_token'];
}

function landing_verify_csrf_token($token): bool
{
    $given = is_string($token) ? $token : '';
    $stored = isset($_SESSION['landing_csrf_token']) ? (string) $_SESSION['landing_csrf_token'] : '';
    return $stored !== '' && $given !== '' && hash_equals($stored, $given);
}

function landing_send_recommendation_email(string $senderName, string $senderEmail, string $subject, string $message): bool
{
    try {
        require_once dirname(__DIR__) . '/config/email.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/PHPMailer.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/SMTP.php';
        require_once dirname(__DIR__) . '/vendor/PHPMailer/Exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->Timeout = 20;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress('ipms.systemlgu@gmail.com', 'LGU IPMS');
        $mail->addReplyTo($senderEmail, $senderName);
        $mail->isHTML(true);
        $mail->Subject = 'Landing Recommendation: ' . $subject;

        $safeName = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($senderEmail, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;background:#f7fbff;padding:16px;'>
                <div style='max-width:680px;margin:0 auto;background:#fff;border:1px solid #d7e6f8;border-radius:10px;padding:18px;'>
                    <h2 style='margin:0 0 10px 0;color:#153a69;'>Citizen System Recommendation</h2>
                    <p style='margin:0 0 6px 0;color:#314b6f;'><strong>From:</strong> {$safeName}</p>
                    <p style='margin:0 0 6px 0;color:#314b6f;'><strong>Email:</strong> {$safeEmail}</p>
                    <p style='margin:0 0 12px 0;color:#314b6f;'><strong>Subject:</strong> {$safeSubject}</p>
                    <div style='margin-top:8px;padding:12px;border:1px solid #e2ecfa;border-radius:8px;background:#f9fcff;color:#1f3f67;line-height:1.6;'>
                        {$safeMessage}
                    </div>
                </div>
            </div>
        ";

        return $mail->send();
    } catch (\Throwable $e) {
        error_log('Landing recommendation email error: ' . $e->getMessage());
        return false;
    }
}

$feedbackNotice = ['type' => '', 'text' => ''];
$feedbackForm = [
    'full_name' => '',
    'email' => '',
    'subject' => '',
    'message' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['landing_feedback_submit'])) {
    $feedbackForm['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $feedbackForm['email'] = trim((string) ($_POST['email'] ?? ''));
    $feedbackForm['subject'] = trim((string) ($_POST['subject'] ?? ''));
    $feedbackForm['message'] = trim((string) ($_POST['message'] ?? ''));

    if (!landing_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $feedbackNotice = ['type' => 'error', 'text' => 'Invalid request token. Please refresh and try again.'];
    } elseif (
        $feedbackForm['full_name'] === '' ||
        $feedbackForm['email'] === '' ||
        $feedbackForm['subject'] === '' ||
        $feedbackForm['message'] === ''
    ) {
        $feedbackNotice = ['type' => 'error', 'text' => 'Please complete all fields before sending your recommendation.'];
    } elseif (!is_valid_email($feedbackForm['email'])) {
        $feedbackNotice = ['type' => 'error', 'text' => 'Please provide a valid email address.'];
    } else {
        require dirname(__DIR__) . '/database.php';

        if (!isset($db) || $db->connect_error) {
            $feedbackNotice = ['type' => 'error', 'text' => 'Unable to connect right now. Please try again later.'];
        } else {
            $status = 'Pending';
            $userName = $feedbackForm['full_name'] . ' (Guest)';
            $category = 'System Recommendation';
            $location = 'Landing Page';
            $description = "Sender Email: " . $feedbackForm['email'] . "\n\n" . $feedbackForm['message'];
            $stmt = $db->prepare('INSERT INTO feedback (user_name, subject, category, location, description, status) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param(
                    'ssssss',
                    $userName,
                    $feedbackForm['subject'],
                    $category,
                    $location,
                    $description,
                    $status
                );
                $ok = $stmt->execute();
                $stmt->close();

                if ($ok) {
                    $mailSent = landing_send_recommendation_email(
                        $feedbackForm['full_name'],
                        $feedbackForm['email'],
                        $feedbackForm['subject'],
                        $feedbackForm['message']
                    );
                    if ($mailSent) {
                        $feedbackNotice = ['type' => 'success', 'text' => 'Thank you. Your recommendation has been submitted and emailed to LGU IPMS.'];
                    } else {
                        $feedbackNotice = ['type' => 'success', 'text' => 'Recommendation saved successfully. Email notification was not sent, but your message is in the review queue.'];
                    }
                    $feedbackForm = ['full_name' => '', 'email' => '', 'subject' => '', 'message' => ''];
                } else {
                    $feedbackNotice = ['type' => 'error', 'text' => 'Submission failed. Please try again after a few minutes.'];
                }
            } else {
                $feedbackNotice = ['type' => 'error', 'text' => 'Submission service is unavailable right now.'];
            }

            $db->close();
        }
    }
}

$csrfToken = landing_generate_csrf_token();
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

        .welcome-strip {
            background: linear-gradient(135deg, #173e70, #2b66ac);
            color: #f5f9ff;
            border-radius: 18px;
            padding: 1.1rem;
            margin-top: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.7rem;
        }

        .welcome-strip i {
            margin-top: 0.15rem;
            color: #ffd58f;
        }

        .welcome-strip strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .contact-wrap {
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            gap: 1rem;
            align-items: start;
        }

        .contact-cards {
            display: grid;
            gap: 0.85rem;
        }

        .contact-card {
            background: #fff;
            border: 1px solid #d8e5f5;
            border-radius: 14px;
            padding: 1rem;
            box-shadow: 0 8px 18px rgba(17, 41, 71, 0.06);
        }

        .contact-card h4 {
            font-size: 1rem;
            color: #143252;
            margin-bottom: 0.35rem;
        }

        .contact-card p,
        .contact-card a {
            color: #4d6482;
            font-size: 0.9rem;
            line-height: 1.6;
            text-decoration: none;
        }

        .contact-card a:hover {
            color: #1f4c87;
        }

        .feedback-form {
            background: #fff;
            border: 1px solid #d8e5f5;
            border-radius: 16px;
            padding: 1rem;
            box-shadow: 0 10px 22px rgba(10, 33, 64, 0.08);
        }

        .feedback-form h3 {
            margin-bottom: 0.35rem;
            font-size: 1.08rem;
            color: #123154;
        }

        .feedback-form .desc {
            color: #4e6787;
            font-size: 0.9rem;
            margin-bottom: 0.9rem;
        }

        .feedback-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .form-group {
            display: grid;
            gap: 0.35rem;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 0.84rem;
            font-weight: 700;
            color: #26476c;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            border: 1px solid #cfe0f5;
            border-radius: 10px;
            padding: 0.62rem 0.7rem;
            font: inherit;
            font-size: 0.9rem;
            color: #1e3d61;
            background: #fbfdff;
        }

        .form-group textarea {
            min-height: 115px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4e84c7;
            box-shadow: 0 0 0 3px rgba(78, 132, 199, 0.15);
        }

        .feedback-msg {
            border-radius: 10px;
            padding: 0.6rem 0.7rem;
            font-size: 0.86rem;
            margin-bottom: 0.75rem;
        }

        .feedback-msg.success {
            background: #e8f8ef;
            color: #17613f;
            border: 1px solid #b8e7ca;
        }

        .feedback-msg.error {
            background: #fff0f0;
            color: #922c2c;
            border: 1px solid #f6c6c6;
        }

        .btn-submit-feedback {
            width: 100%;
            justify-content: center;
            background: linear-gradient(135deg, #1f4c87, #2f6bb8);
            color: #fff;
            padding: 0.78rem;
            border-radius: 10px;
            margin-top: 0.8rem;
        }

        .footer {
            background: #0f2f57;
            color: rgba(245, 250, 255, 0.92);
            padding: 1.2rem 1rem 1.4rem;
            text-align: center;
            font-size: 0.9rem;
        }

        .employee-login-widget {
            position: fixed;
            right: 1.1rem;
            bottom: 1.1rem;
            z-index: 1200;
        }

        .employee-login-toggle {
            width: 54px;
            height: 54px;
            border: 0;
            border-radius: 14px;
            cursor: pointer;
            background: linear-gradient(135deg, #1f4c87, #2f6bb8);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            box-shadow: 0 14px 28px rgba(11, 40, 77, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .employee-login-toggle:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 30px rgba(11, 40, 77, 0.4);
        }

        .floating-employee-login {
            position: absolute;
            right: 0;
            bottom: 66px;
            width: min(92vw, 330px);
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid #d2e2f7;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(10, 33, 64, 0.28);
            overflow: hidden;
            transform: translateY(8px) scale(0.98);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .employee-login-widget.is-open .floating-employee-login {
            transform: translateY(0) scale(1);
            opacity: 1;
            pointer-events: auto;
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

            .contact-wrap {
                grid-template-columns: 1fr;
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

            .feedback-grid {
                grid-template-columns: 1fr;
            }

            .employee-login-widget {
                right: 0.75rem;
                bottom: 0.75rem;
            }

            .floating-employee-login {
                width: min(92vw, 330px);
                bottom: 62px;
                box-shadow: 0 12px 24px rgba(10, 33, 64, 0.24);
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
                <a href="#contact"><i class="fa-solid fa-envelope"></i> Contact</a>
                <a href="/user-dashboard/user-login.php"><i class="fa-solid fa-users"></i> Citizen Login</a>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-wrap">
            <div class="hero-copy">
                <span class="hero-pill"><i class="fa-solid fa-circle-check"></i> Digital LGU Service Platform</span>
                <h1>Welcome, citizens. Track projects that matter to your community.</h1>
                <p>
                    This portal keeps you informed on local infrastructure plans, progress, and budget use.
                    You can follow updates and share recommendations so projects reflect real community needs.
                </p>
                <div class="hero-actions">
                    <a class="btn btn-citizen" href="/user-dashboard/user-login.php">
                        <i class="fa-solid fa-arrow-right"></i> Access Citizen Portal
                    </a>
                    <a class="btn btn-ghost" href="#features">
                        <i class="fa-solid fa-circle-info"></i> Explore Features
                    </a>
                </div>
                <div class="welcome-strip">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                    <div>
                        <strong>Citizen-first experience</strong>
                        We designed this system so residents can clearly see project status and submit suggestions for better prioritization.
                    </div>
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

    <section id="contact" class="section">
        <h2 class="section-title">Contact Us & Send Recommendations</h2>
        <p class="section-sub">Use this form only for system suggestions or recommendations (UI, features, usability, or improvements). Concerns and project ideas should be submitted inside the citizen portal.</p>
        <div class="contact-wrap">
            <div class="contact-cards">
                <article class="contact-card">
                    <h4><i class="fa-solid fa-circle-info"></i> How We Use Your Input</h4>
                    <p>Your submission goes to the LGU feedback queue and can be reviewed in project prioritization discussions.</p>
                </article>
                <article class="contact-card">
                    <h4><i class="fa-solid fa-envelope-open-text"></i> Alternative Contact</h4>
                    <p>
                        <a href="mailto:ipms.systemlgu@gmail.com">ipms.systemlgu@gmail.com</a><br>
                        For urgent follow-ups, include your full name and recommendation subject.
                    </p>
                </article>
            </div>

            <form class="feedback-form" method="post" action="/public/index.php#contact">
                <h3>Citizen Recommendation Form</h3>
                <p class="desc">All fields are required. Please provide complete and clear details.</p>
                <?php if ($feedbackNotice['text'] !== ''): ?>
                    <div class="feedback-msg <?php echo $feedbackNotice['type'] === 'success' ? 'success' : 'error'; ?>" role="status" aria-live="polite">
                        <?php echo htmlspecialchars($feedbackNotice['text'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="feedback-grid">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" maxlength="120" required value="<?php echo htmlspecialchars($feedbackForm['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" maxlength="190" required value="<?php echo htmlspecialchars($feedbackForm['email'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group full">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" maxlength="150" required value="<?php echo htmlspecialchars($feedbackForm['subject'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="form-group full">
                        <label for="message">Recommendation</label>
                        <textarea id="message" name="message" required><?php echo htmlspecialchars($feedbackForm['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" name="landing_feedback_submit" value="1" class="btn btn-submit-feedback">
                    <i class="fa-solid fa-paper-plane"></i> Submit Recommendation
                </button>
            </form>
        </div>
    </section>

    <div class="employee-login-widget" id="employeeLoginWidget">
        <button type="button" class="employee-login-toggle" id="employeeLoginToggle" aria-label="Toggle employee login panel" aria-expanded="false" aria-controls="employeeLoginPanel">
            <i class="fa-solid fa-user-shield"></i>
        </button>
        <div class="floating-employee-login" id="employeeLoginPanel" aria-label="Employee login shortcut">
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
    </div>

    <footer class="footer">
        <p>&copy; 2026 Local Government Unit Infrastructure Project Management System</p>
    </footer>
    <script>
        (function () {
            var widget = document.getElementById('employeeLoginWidget');
            var toggle = document.getElementById('employeeLoginToggle');
            var panel = document.getElementById('employeeLoginPanel');
            if (!widget || !toggle || !panel) return;

            function setOpen(isOpen) {
                widget.classList.toggle('is-open', isOpen);
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            toggle.addEventListener('click', function () {
                setOpen(!widget.classList.contains('is-open'));
            });

            document.addEventListener('click', function (event) {
                if (!widget.classList.contains('is-open')) return;
                if (widget.contains(event.target)) return;
                setOpen(false);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') setOpen(false);
            });
        })();

        document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
            anchor.addEventListener('click', function (e) {
                var targetId = this.getAttribute('href');
                if (!targetId || targetId.length < 2) return;
                var target = document.querySelector(targetId);
                if (!target) return;
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });
    </script>
</body>
</html>
