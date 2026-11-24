<?php
// index.php - Main Application Router
require_once __DIR__ . '/config/auth.php';

$user = getCurrentUser();

if ($user) {
    header("Location: dashboard.php");
    exit;
} else {
    header("Location: login.php");
    exit;
}
