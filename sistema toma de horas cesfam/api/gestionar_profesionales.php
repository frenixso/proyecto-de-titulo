<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';

/* =========================================================
   FUNCIONES DE RESPUESTA
   ========================================================= */

function responderProfesional($datos, $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderErrorProfesional($mensaje, $codigo = 400): void
{
    responderProfesional([
        'ok' => false,
        'error' => $mensaje
    ], $codigo);
}

function convertirBooleanoProfesionalAPI($valor): bool
{
    return $valor === true
        || $valor === 1
        || $valor === '1'
        || $valor === 'true'
        || $valor === 't'
        || $valor === 'on';
}

function rutTieneFormatoValido($rut): bool
{
    return preg_match('/^\d{1,2}\.?\d{3}\.?\d{3}-[\dkK]$/', $rut) === 1;
}

/* =========================================================
   SESIÓN Y DATOS DEL USUARIO CONECTADO
   ========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuarioFuncionario = trim($_SESSION['usuario_funcionario'] ?? '');
$rolFuncionario = trim($_SESSION['rol_funcionario'] ?? '');

if ($usuarioFuncionario === '') {
    responderErrorProfesional('La sesión no es válida.', 401);
}

if ($rolFuncionario !== 'superadmin' && $rolFuncionario !== 'administrador') {
    responderErrorProfesional('No tienes permisos para administrar profesionales.', 403);
}

/* =========================================================
   VALIDAR AL FUNCIONARIO ACTUAL CONTRA POSTGRESQL
   ========================================================= */

try {
    $sqlAdministrador = "
        SELECT
            id_funcionario,
            nombre,
            apellido,
            usuario,
            rol,
            activo
        FROM funcionarios
        WHERE usuario = :usuario
        LIMIT 1
    ";

    $stmtAdministrador = $conexion->prepare($sqlAdministrador);
    $stmtAdministrador->execute([':usuario' => $usuarioFuncionario]);
    $administrador = $stmtAdministrador->fetch(PDO::FETCH_ASSOC);

    if (!$administrador) {
        responderErrorProfesional('No fue posible validar el funcionario conectado.', 403);
    }

    if (!convertirBooleanoProfesionalAPI($administrador['activo'])) {
        responderErrorProfesional('El funcionario conectado está inactivo.', 403);
    }

    $rolActual = trim($administrador['rol'] ?? '');

    if ($rolActual !== $rolFuncionario) {
        responderErrorProfesional('Los permisos de la sesión no coinciden con los permisos actuales.', 403);
    }
