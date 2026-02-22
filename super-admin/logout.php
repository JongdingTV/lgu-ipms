<?php
require_once dirname(__DIR__) . '/session-auth.php';

destroy_session();
header('Location: /super-admin/index.php?logout=1');
exit;

