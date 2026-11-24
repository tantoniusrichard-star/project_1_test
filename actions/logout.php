<?php
// actions/logout.php
require_once __DIR__ . '/../config/auth.php';
logoutUser();
header("Location: ../login.php?msg=logged_out");
exit;
