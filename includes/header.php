<?php
/**
 * Header Component
 * 
 * Meta tags and common head elements
 * This should be included in the <head> section
 * 
 * @package LGU-IPMS
 * @subpackage Components
 * @version 1.0.0
 */
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Local Government Unit Infrastructure Project Management System">
<meta name="keywords" content="LGU, Infrastructure, Projects, Management">
<meta name="author" content="LGU">
<meta name="robots" content="noindex, nofollow">

<!-- Favicon -->
<link rel="icon" type="image/png" href="<?php echo image('logo.png'); ?>">

<!-- Apple Touch Icon -->
<link rel="apple-touch-icon" href="<?php echo image('logo.png'); ?>">

<!-- Stylesheets -->
<link rel="stylesheet" href="<?php echo asset('css/main.css'); ?>">
<link rel="stylesheet" href="<?php echo asset('css/responsive.css'); ?>">

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Security Headers -->
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;">
