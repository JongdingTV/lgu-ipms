<?php
require dirname(__DIR__) . '/session-auth.php';

destroy_session();
header('Location: /contractor/index.php');
exit;

