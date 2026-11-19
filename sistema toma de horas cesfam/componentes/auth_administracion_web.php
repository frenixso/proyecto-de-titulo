
<?php

/*
 * ============================================================
 * AUTORIZACIÓN PARA ADMINISTRAR LA PÁGINA PÚBLICA
 * ============================================================
 *
 * Este archivo protege el módulo de administración web.
 *
 * Reglas:
 * - El superadmin siempre tiene acceso.
 * - Otros funcionarios necesitan el permiso
 *   puede_administrar_web = TRUE.
 * - El funcionario debe existir y estar activo.
 * - Los permisos se verifican directamente en PostgreSQL.
 *
 * Uso desde una página ubicada en /funcionarios:
 *
 * require_once '../componentes/auth_administracion_web.php';
 *
 * Este archivo no modifica los permisos de la toma de horas.
 * ============================================================
 */


/* ============================================================
   1. COMPROBAR LA SESIÓN DEL FUNCIONARIO
   ============================================================ */

require_once __DIR__ . '/auth_funcionario.php';


/* ============================================================
   2. IDENTIFICAR AL FUNCIONARIO ACTUAL
   ============================================================ */

$idFuncionarioActual =
    (int) (
        $_SESSION['id_funcionario'] ?? 0
    );


if ($idFuncionarioActual <= 0) {

    header('Location: login.php');

    exit;
}


/* ============================================================
   3. VERIFICAR LOS PERMISOS EN POSTGRESQL
   ============================================================ */

try {

    $sql = "
        SELECT
            id_funcionario,
            rol,
            activo,
            puede_administrar_web
        FROM funcionarios
        WHERE id_funcionario = :id_funcionario
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute(
        [
            ':id_funcionario' => $idFuncionarioActual
        ]
    );

    $funcionarioWeb = $stmt->fetch(
        PDO::FETCH_ASSOC
    );


    /* ========================================================
       4. COMPROBAR QUE EL FUNCIONARIO EXISTA Y ESTÉ ACTIVO
       ======================================================== */

    if (
        !$funcionarioWeb ||
        !in_array(
            $funcionarioWeb['activo'] ?? false,
            [true, 1, '1', 't', 'true'],
            true
        )
    ) {

        header('Location: login.php');

        exit;
    }


    /* ========================================================
       5. AUTORIZAR AL SUPERADMIN
       ======================================================== */

    /*
     * El superadmin siempre puede entrar al módulo web,
     * aunque su columna puede_administrar_web sea FALSE.
     */

    if (
        $funcionarioWeb['rol'] === 'superadmin'
    ) {

        /*
         * Acceso autorizado.
         * La ejecución de la página continúa.
         */

    } else {

        /* ====================================================
           6. COMPROBAR EL PERMISO EDITORIAL
           ==================================================== */

        $permisoWeb =
            $funcionarioWeb['puede_administrar_web'] ?? false;


        $tienePermisoWeb = in_array(
            $permisoWeb,
            [true, 1, '1', 't', 'true'],
            true
        );


        if (!$tienePermisoWeb) {

            /*
             * Sin permiso editorial:
             * regresar al panel general.
             */

            header('Location: panel.php');

            exit;
        }
    }

} catch (PDOException $e) {

    error_log(
        'Error al comprobar autorización web: ' .
        $e->getMessage()
    );

    http_response_code(500);

    exit(
        'No fue posible verificar los permisos de acceso.'
    );
}


/*
 * Si la ejecución llega hasta aquí, el funcionario
 * está autenticado y autorizado para administrar
 * la página pública del CESFAM.
 */

?>
