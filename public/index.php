<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/guard.php';

if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Forzar cambio de contraseña
if (!empty($_SESSION['debe_cambiar_pass'])) {
    header('Location: cambiar_password.php');
    exit;
}

// Selección de empresa (multi-razón social)
if (empty($_SESSION['empresa_id'])) {
    header('Location: seleccionar_empresa.php');
    exit;
}

// Todo OK
header('Location: dashboard.php');
exit;
