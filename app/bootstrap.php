<?php

// ===============================
// 🔧 CORE SYSTEM BOOTSTRAP
// ===============================

// session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// timezone (สำคัญมาก)
date_default_timezone_set('Asia/Bangkok');

// error reporting (สำหรับ dev)
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === '1' ? '1' : '0');

// config
require_once __DIR__ . '/config/config.php';

// security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');