<?php
require_once 'includes/auth.php';
epl_logout();
header('Location: login.php');
exit;
