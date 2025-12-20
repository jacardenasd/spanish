<?php
// includes/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));              // /ruta/proyecto
define('PUBLIC_PATH', BASE_PATH . '/public');
date_default_timezone_set('America/Mexico_City');
define('ASSET_BASE', '/sgrh/'); 


