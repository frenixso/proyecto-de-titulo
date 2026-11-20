<?php

/*
 * ============================================================
 * AUTENTICACIÓN Y SESIÓN DE FUNCIONARIOS
 * ============================================================
 *
 * Funciones:
 * - Configurar la sesión de forma segura.
 * - Comprobar que existe una sesión autenticada.
 * - Verificar que el funcionario existe en PostgreSQL.
 * - Comprobar que el funcionario está activo.
 * - Obtener y guardar su rol.
 * - Obtener el permiso de administración web.
 * - Mantener actualizados sus datos en la sesión.
 *
 * ============================================================
 */


/* ============================================================
   1. CONFIGURACIÓN DE LA COOKIE DE SESIÓN
   ============================================================ */

$httpsActivo =
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off';


if (session_status() !== PHP_SESSION_ACTIVE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $httpsActivo,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

}


/* ============================================================
   2. CONFIGURACIÓN DE SEGURIDAD DE SESIÓN
   ============================================================ */

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');


/* ============================================================
   3. INICIAR SESIÓN
   ============================================================ */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/* ============================================================
   4. COMPROBAR AUTENTICACIÓN
   ============================================================ */

if (
    !isset($_SESSION['funcionario_autenticado']) ||
    $_SESSION['funcionario_autenticado'] !== true ||
    !isset($_SESSION['id_funcionario']) ||
    !is_numeric($_SESSION['id_funcionario'])
) {

    header('Location: login.php');
    exit;

}


$idFuncionario = (int) $_SESSION['id_funcionario'];


/* ============================================================
   5. CONEXIÓN A POSTGRESQL
   ============================================================ */

require_once __DIR__ . '/../config/conexion.php';


/* ============================================================
   6. VERIFICAR FUNCIONARIO EN LA BASE DE DATOS
   ============================================================ */

try {

    $sql = "
        SELECT
            id_funcionario,
            nombre,
            apellido,
            correo,
            usuario,
            rol,
            activo,
            puede_administrar_web
        FROM funcionarios
        WHERE id_funcionario = :id_funcionario
        LIMIT 1
    ";


    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':id_funcionario' => $idFuncionario
    ]);


    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);


    /* ========================================================
       FUNCIONARIO NO EXISTE
       ======================================================== */

    if (!$funcionario) {

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $parametrosCookie = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametrosCookie['path'],
                $parametrosCookie['domain'] ?? '',
                $parametrosCookie['secure'],
                $parametrosCookie['httponly']
            );

        }

        session_destroy();

        header('Location: login.php');
        exit;

    }


    /* ========================================================
       FUNCIONARIO INACTIVO
       ======================================================== */

    $funcionarioActivo = in_array(
        $funcionario['activo'],
        [true, 1, '1', 't', 'true'],
        true
    );


    if (!$funcionarioActivo) {

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $parametrosCookie = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametrosCookie['path'],
                $parametrosCookie['domain'] ?? '',
                $parametrosCookie['secure'],
                $parametrosCookie['httponly']
            );

        }

        session_destroy();

        header('Location: login.php');
        exit;

    }


    /* ========================================================
       7. VALIDAR ROL
       ======================================================== */

    $rol = trim(
        (string) $funcionario['rol']
    );


    $rolesPermitidos = [
        'superadmin',
        'administrador',
        'funcionario'
    ];


    if (!in_array($rol, $rolesPermitidos, true)) {

        error_log(
            'Funcionario con rol inválido. ID: ' .
            $idFuncionario
        );

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $parametrosCookie = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametrosCookie['path'],
                $parametrosCookie['domain'] ?? '',
                $parametrosCookie['secure'],
                $parametrosCookie['httponly']
            );

        }

        session_destroy();

        header('Location: login.php');
        exit;

    }


    /* ========================================================
       8. OBTENER PERMISO DE ADMINISTRACIÓN WEB
       ======================================================== */

    $valorPermisoWeb =
        $funcionario['puede_administrar_web'] ?? false;


    $puedeAdministrarWeb = in_array(
        $valorPermisoWeb,
        [true, 1, '1', 't', 'true'],
        true
    );


    /* ========================================================
       9. ACTUALIZAR DATOS DE SESIÓN
       ======================================================== */

    $_SESSION['funcionario_autenticado'] = true;

    $_SESSION['id_funcionario'] =
        (int) $funcionario['id_funcionario'];

    $_SESSION['nombre_funcionario'] =
        $funcionario['nombre'];

    $_SESSION['apellido_funcionario'] =
        $funcionario['apellido'];

    $_SESSION['usuario_funcionario'] =
        $funcionario['usuario'];

    $_SESSION['correo_funcionario'] =
        $funcionario['correo'];

    $_SESSION['rol_funcionario'] =
        $rol;


    /*
     * Permiso independiente del rol.
     * Permite identificar a los administradores web.
     */

    $_SESSION['puede_administrar_web'] =
        $puedeAdministrarWeb;


} catch (PDOException $e) {

    error_log(
        'Error en auth_funcionario.php: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Error del sistema</title>
        </head>
        <body>
            <h1>Error del sistema</h1>
            <p>
                No fue posible verificar la sesión
                del funcionario.
            </p>
        </body>
        </html>
    ';

    exit;

}

?>
