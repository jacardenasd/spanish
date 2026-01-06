<?php
// includes/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_ENV', 'dev'); // <-- debe ser dev para generar archivos

ini_set('display_errors', APP_ENV === 'dev' ? '1' : '0');
ini_set('display_startup_errors', APP_ENV === 'dev' ? '1' : '0');
error_reporting(E_ALL);


define('APP_URL', 'http://localhost/'); // ajusta si tu ruta es distinta

define('BASE_PATH', dirname(__DIR__));              // /ruta/proyecto
define('PUBLIC_PATH', BASE_PATH . '/public');
date_default_timezone_set('America/Mexico_City');
define('ASSET_BASE', '/sgrh/'); 

require_once __DIR__ . '/../vendor/autoload.php';

