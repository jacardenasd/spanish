<?php
// includes/guard.php
require_once __DIR__ . '/auth.php';

function require_login() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

function require_empresa() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['empresa_id'])) {
        header('Location: seleccionar_empresa.php');
        exit;
    }
}

function require_password_change_redirect() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['debe_cambiar_pass']) && basename($_SERVER['PHP_SELF']) !== 'cambiar_password.php') {
        header('Location: cambiar_password.php');
        exit;
    }
}
