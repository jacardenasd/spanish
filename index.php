<?php
// /sgrh/index.php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/guard.php';

// 1) No autenticado → login
if (empty($_SESSION['usuario_id'])) {
    header('Location: public/login.php');
    exit;
}

// 2) Debe cambiar contraseña
if (!empty($_SESSION['debe_cambiar_pass'])) {
    header('Location: public/cambiar_password.php');
    exit;
}

// 3) No ha seleccionado empresa
if (empty($_SESSION['empresa_id'])) {
    header('Location: public/seleccionar_empresa.php');
    exit;
}

// 4) Todo OK → dashboard
header('Location: public/dashboard.php');
exit;
