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

    $self = basename($_SERVER['PHP_SELF']);
    $permitidos = ['cambiar_password.php', 'logout.php', 'recuperar_contrasena.php', 'terminos.php', 'login.php'];

    if (!empty($_SESSION['usuario_id']) && !empty($_SESSION['debe_cambiar_pass']) && !in_array($self, $permitidos, true)) {
        header('Location: cambiar_password.php');
        exit;
    }
}
