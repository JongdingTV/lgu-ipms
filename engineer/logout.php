<?php
require dirname(__DIR__) . '/session-auth.php';

destroy_session();
header('Location: /engineer/index.php');
exit;

