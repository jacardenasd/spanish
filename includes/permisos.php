<?php
// includes/permisos.php
require_once __DIR__ . '/conexion.php';

function cargar_permisos_sesion($usuario_id) {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Override simple: si es admin de la empresa seleccionada, permite todo
    if (!empty($_SESSION['es_admin_empresa'])) {
        $_SESSION['permisos'] = ['*' => true];
        return;
    }

    $sql = "
        SELECT p.clave
        FROM usuario_roles ur
        INNER JOIN rol_permisos rp ON rp.rol_id = ur.rol_id
        INNER JOIN permisos p ON p.permiso_id = rp.permiso_id
        INNER JOIN roles r ON r.rol_id = ur.rol_id
        WHERE ur.usuario_id = :uid
          AND r.estatus = 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => (int)$usuario_id]);

    $perms = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $perms[$row['clave']] = true;
    }

    $_SESSION['permisos'] = $perms;
}

function can($permiso_clave) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $p = $_SESSION['permisos'] ?? [];

    if (!empty($p['*'])) {
        return true;
    }

    return !empty($p[$permiso_clave]);
}

function can_any(array $permiso_claves) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $p = $_SESSION['permisos'] ?? [];

    if (!empty($p['*'])) {
        return true;
    }

    foreach ($permiso_claves as $clave) {
        if (!empty($p[$clave])) {
            return true;
        }
    }
    return false;
}

function require_perm($permiso_clave) {
    if (!can($permiso_clave)) {
        header('Location: sin_permiso.php');
        exit;
    }
}

function require_perm_any(array $permiso_claves) {
    if (!can_any($permiso_claves)) {
        header('Location: sin_permiso.php');
        exit;
    }
}
