
<?php

/*
 * =========================================================
 * API GESTIÓN DE FUNCIONARIOS CON ACCESO ADMINISTRATIVO
 * CESFAM EDUARDO RAGGIO CATEMU
 * =========================================================
 *
 * PERFILES:
 *
 * 1. SUPERADMIN:
 *    rol = superadmin
 *
 * 2. ADMINISTRADOR DE RESERVAS:
 *    rol = administrador
 *
 * 3. ADMINISTRADOR WEB:
 *    rol = funcionario
 *    puede_administrar_web = TRUE
 *
 * REGLAS:
 *
 * - Exclusivamente superadmin puede gestionar cuentas.
 * - Ninguna cuenta administrativa pertenece a un departamento.
 * - id_departamento siempre será NULL.
 * - No se asignan servicios a cuentas administrativas.
 * - El personal de atención se gestiona mediante
 *   gestionar_profesionales.php.
 *
 * CORRECCIÓN:
 *
 * - Los booleanos se envían a PostgreSQL como TRUE/FALSE.
 * - Se evita enviar cadenas vacías a columnas BOOLEAN.
 *
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');


/* =========================================================
   1. AUTENTICACIÓN
========================================================= */

require_once __DIR__ . '/../componentes/auth_funcionario.php';


/* =========================================================
   2. FUNCIONES DE RESPUESTA
========================================================= */

function gestionarResponder(
    array $datos,
    int $codigo = 200
): void {

    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


function gestionarResponderError(
    string $mensaje,
    int $codigo = 400
): void {

    gestionarResponder(
        [
            'ok' => false,
            'error' => $mensaje
        ],
        $codigo
    );
}


/* =========================================================
   3. CONVERTIR VALORES BOOLEANOS
========================================================= */

function gestionarBooleano($valor): bool
{
    if (is_bool($valor)) {
        return $valor;
    }

    if (is_numeric($valor)) {
        return (int) $valor === 1;
    }

    return in_array(
        strtolower(trim((string) $valor)),
        ['1', 'true', 't', 'on', 'yes', 'si', 'sí'],
        true
    );
}


/*
 * IMPORTANTE:
 *
 * PDO puede convertir el booleano PHP false en una cadena
 * vacía cuando se utiliza execute() con PDO_PGSQL.
 *
 * PostgreSQL necesita recibir TRUE o FALSE, no ''.
 *
 * Esta función devuelve cadenas explícitas que PostgreSQL
 * reconoce como valores booleanos.
 */

function gestionarBooleanoPostgres($valor): string
{
    return gestionarBooleano($valor)
        ? 'TRUE'
        : 'FALSE';
}


/* =========================================================
   4. IDENTIFICAR SUPERADMIN ACTUAL
========================================================= */

$idAdministradorActual =
    (int) ($_SESSION['id_funcionario'] ?? 0);

$rolAdministradorActual =
    strtolower(
        trim(
            (string) ($_SESSION['rol_funcionario'] ?? '')
        )
    );


if ($idAdministradorActual <= 0) {

    gestionarResponderError(
        'La sesión no es válida.',
        401
    );
}


/* =========================================================
   5. AUTORIZACIÓN EXCLUSIVA PARA SUPERADMIN
========================================================= */

if ($rolAdministradorActual !== 'superadmin') {

    gestionarResponderError(
        'Solamente el superadministrador puede administrar cuentas de acceso.',
        403
    );
}


/*
 * Verificación adicional directamente en PostgreSQL.
 */

try {

    $stmtAdministrador = $conexion->prepare("
        SELECT
            id_funcionario,
            rol,
            activo
        FROM funcionarios
        WHERE id_funcionario = :id_funcionario
        LIMIT 1
    ");

    $stmtAdministrador->execute([
        ':id_funcionario' => $idAdministradorActual
    ]);

    $administradorActual =
        $stmtAdministrador->fetch(PDO::FETCH_ASSOC);


    if (
        !$administradorActual ||
        $administradorActual['rol'] !== 'superadmin' ||
        !gestionarBooleano($administradorActual['activo'])
    ) {

        gestionarResponderError(
            'No tienes autorización para administrar cuentas.',
            403
        );
    }

} catch (PDOException $e) {

    error_log(
        'Error verificar superadmin en gestionar_funcionarios.php: ' .
        $e->getMessage()
    );

    gestionarResponderError(
        'No fue posible verificar tus permisos.',
        500
    );
}


/* =========================================================
   6. CONTAR SUPERADMINISTRADORES ACTIVOS
========================================================= */

function gestionarContarSuperadminActivos(
    PDO $conexion
): int {

    $stmt = $conexion->query("
        SELECT COUNT(*)
        FROM funcionarios
        WHERE rol = 'superadmin'
          AND activo = TRUE
    ");

    return (int) $stmt->fetchColumn();
}


/* =========================================================
   7. IDENTIFICAR MÉTODO Y ACCIÓN
========================================================= */

$metodo = strtoupper(
    $_SERVER['REQUEST_METHOD'] ?? ''
);

$accion = trim(
    $_POST['accion'] ??
    $_GET['accion'] ??
    ''
);


$accionesPermitidas = [
    'obtener',
    'crear',
    'editar',
    'activar',
    'desactivar',
    'eliminar'
];


if (!in_array($accion, $accionesPermitidas, true)) {

    gestionarResponderError(
        'La acción solicitada no es válida.',
        400
    );
}


/* =========================================================
   8. OBTENER FUNCIONARIO
========================================================= */

if ($accion === 'obtener') {

    if ($metodo !== 'GET') {

        gestionarResponderError(
            'Esta consulta requiere el método GET.',
            405
        );
    }


    $idFuncionario = trim(
        (string) ($_GET['id'] ?? '')
    );


    if (
        !ctype_digit($idFuncionario) ||
        (int) $idFuncionario <= 0
    ) {

        gestionarResponderError(
            'El ID del funcionario no es válido.'
        );
    }


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

                puede_administrar_web,

                CASE

                    WHEN rol = 'superadmin'
                    THEN 'superadmin'

                    WHEN rol = 'administrador'
                    THEN 'administrador'

                    WHEN rol = 'funcionario'
                         AND COALESCE(puede_administrar_web, FALSE) = TRUE
                    THEN 'administrador_web'

                    ELSE 'funcionario'

                END AS tipo_acceso

            FROM funcionarios

            WHERE id_funcionario = :id_funcionario

            LIMIT 1
        ";


        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':id_funcionario' => (int) $idFuncionario
        ]);

        $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$funcionario) {

            gestionarResponderError(
                'No se encontró el funcionario.',
                404
            );
        }


        /*
         * Las cuentas administrativas no están
         * vinculadas a departamentos.
         */

        $funcionario['id_departamento'] = null;

        $funcionario['departamento'] = null;


        gestionarResponder([
            'ok' => true,
            'funcionario' => $funcionario
        ]);

    } catch (PDOException $e) {

        error_log(
            'Error obtener funcionario: ' .
            $e->getMessage()
        );

        gestionarResponderError(
            'No fue posible obtener el funcionario.',
            500
        );
    }
}


/* =========================================================
   9. VALIDAR MÉTODO PARA MODIFICACIONES
========================================================= */

if ($metodo !== 'POST') {

    gestionarResponderError(
        'Esta acción requiere una solicitud POST.',
        405
    );
}


/* =========================================================
   10. IDENTIFICAR FUNCIONARIO OBJETIVO
========================================================= */

$idFuncionario = trim(
    (string) ($_POST['id_funcionario'] ?? '')
);


/* =========================================================
   11. ACTIVAR FUNCIONARIO
========================================================= */

if ($accion === 'activar') {

    if (
        !ctype_digit($idFuncionario) ||
        (int) $idFuncionario <= 0
    ) {

        gestionarResponderError(
            'El ID del funcionario no es válido.'
        );
    }


    $idFuncionario = (int) $idFuncionario;


    try {

        $conexion->beginTransaction();


        $stmtExiste = $conexion->prepare("
            SELECT id_funcionario
            FROM funcionarios
            WHERE id_funcionario = :id_funcionario
            LIMIT 1
            FOR UPDATE
        ");

        $stmtExiste->execute([
            ':id_funcionario' => $idFuncionario
        ]);

        $funcionario = $stmtExiste->fetch(PDO::FETCH_ASSOC);


        if (!$funcionario) {

            $conexion->rollBack();

            gestionarResponderError(
                'No se encontró el funcionario.',
                404
            );
        }


        $stmt = $conexion->prepare("
            UPDATE funcionarios
            SET activo = TRUE
            WHERE id_funcionario = :id_funcionario
        ");

        $stmt->execute([
            ':id_funcionario' => $idFuncionario
        ]);


        $conexion->commit();


        gestionarResponder([
            'ok' => true,
            'mensaje' => 'Cuenta activada correctamente.'
        ]);

    } catch (PDOException $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error activar funcionario: ' .
            $e->getMessage()
        );

        gestionarResponderError(
            'No fue posible activar la cuenta.',
            500
        );
    }
}


/* =========================================================
   12. DESACTIVAR FUNCIONARIO
========================================================= */

if ($accion === 'desactivar') {

    if (
        !ctype_digit($idFuncionario) ||
        (int) $idFuncionario <= 0
    ) {

        gestionarResponderError(
            'El ID del funcionario no es válido.'
        );
    }


    $idFuncionario = (int) $idFuncionario;


    if ($idFuncionario === $idAdministradorActual) {

        gestionarResponderError(
            'No puedes desactivar tu propia cuenta.'
        );
    }


    try {

        $conexion->beginTransaction();


        $stmtExiste = $conexion->prepare("
            SELECT
                id_funcionario,
                rol,
                activo
            FROM funcionarios
            WHERE id_funcionario = :id_funcionario
            LIMIT 1
            FOR UPDATE
        ");

        $stmtExiste->execute([
            ':id_funcionario' => $idFuncionario
        ]);

        $funcionario = $stmtExiste->fetch(PDO::FETCH_ASSOC);


        if (!$funcionario) {

            $conexion->rollBack();

            gestionarResponderError(
                'No se encontró el funcionario.',
                404
            );
        }


        /*
         * Evitar dejar el sistema sin superadministradores.
         */

        if (
            $funcionario['rol'] === 'superadmin' &&
            gestionarBooleano($funcionario['activo']) &&
            gestionarContarSuperadminActivos($conexion) <= 1
        ) {

            $conexion->rollBack();

            gestionarResponderError(
                'No puedes desactivar al último superadministrador activo.',
                403
            );
        }


        $stmt = $conexion->prepare("
            UPDATE funcionarios
            SET activo = FALSE
            WHERE id_funcionario = :id_funcionario
        ");

        $stmt->execute([
            ':id_funcionario' => $idFuncionario
        ]);


        $conexion->commit();


        gestionarResponder([
            'ok' => true,
            'mensaje' => 'Cuenta desactivada correctamente.'
        ]);

    } catch (PDOException $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error desactivar funcionario: ' .
            $e->getMessage()
        );

        gestionarResponderError(
            'No fue posible desactivar la cuenta.',
            500
        );
    }
}


/* =========================================================
   13. ELIMINAR FUNCIONARIO
========================================================= */

if ($accion === 'eliminar') {

    if (
        !ctype_digit($idFuncionario) ||
        (int) $idFuncionario <= 0
    ) {

        gestionarResponderError(
            'El ID del funcionario no es válido.'
        );
    }


    $idFuncionario = (int) $idFuncionario;


    if ($idFuncionario === $idAdministradorActual) {

        gestionarResponderError(
            'No puedes eliminar tu propia cuenta.'
        );
    }


    try {

        $conexion->beginTransaction();


        $stmtExiste = $conexion->prepare("
            SELECT
                id_funcionario,
                rol,
                activo
            FROM funcionarios
            WHERE id_funcionario = :id_funcionario
            LIMIT 1
            FOR UPDATE
        ");

        $stmtExiste->execute([
            ':id_funcionario' => $idFuncionario
        ]);

        $funcionario = $stmtExiste->fetch(PDO::FETCH_ASSOC);


        if (!$funcionario) {

            $conexion->rollBack();

            gestionarResponderError(
                'No se encontró el funcionario.',
                404
            );
        }


        /*
         * Proteger la última cuenta superadmin activa.
         */

        if (
            $funcionario['rol'] === 'superadmin' &&
            gestionarBooleano($funcionario['activo']) &&
            gestionarContarSuperadminActivos($conexion) <= 1
        ) {

            $conexion->rollBack();

            gestionarResponderError(
                'No puedes eliminar al último superadministrador activo.',
                403
            );
        }


        $stmt = $conexion->prepare("
            DELETE FROM funcionarios
            WHERE id_funcionario = :id_funcionario
        ");

        $stmt->execute([
            ':id_funcionario' => $idFuncionario
        ]);


        $conexion->commit();


        gestionarResponder([
            'ok' => true,
            'mensaje' => 'Cuenta eliminada correctamente.'
        ]);

    } catch (PDOException $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error eliminar funcionario: ' .
            $e->getMessage()
        );

        gestionarResponderError(
            'No fue posible eliminar la cuenta. Puede tener registros relacionados.',
            500
        );
    }
}


/* =========================================================
   14. VALIDAR ACCIÓN DE CREACIÓN O EDICIÓN
========================================================= */

if (
    $accion !== 'crear' &&
    $accion !== 'editar'
) {

    gestionarResponderError(
        'No fue posible procesar la acción solicitada.'
    );
}


/* =========================================================
   15. DATOS PARA CREAR Y EDITAR
========================================================= */

$nombre = trim(
    (string) ($_POST['nombre'] ?? '')
);

$apellido = trim(
    (string) ($_POST['apellido'] ?? '')
);

$correo = trim(
    (string) ($_POST['correo'] ?? '')
);

$usuario = trim(
    (string) ($_POST['usuario'] ?? '')
);

$rolSolicitado = strtolower(
    trim(
        (string) ($_POST['rol'] ?? '')
    )
);

$password = (string) ($_POST['password'] ?? '');

$activo = gestionarBooleano(
    $_POST['activo'] ?? '1'
);


/* =========================================================
   16. VALIDAR DATOS PERSONALES
========================================================= */

if (
    mb_strlen($nombre, 'UTF-8') < 2 ||
    mb_strlen($nombre, 'UTF-8') > 100
) {

    gestionarResponderError(
        'El nombre debe tener entre 2 y 100 caracteres.'
    );
}


if (
    mb_strlen($apellido, 'UTF-8') < 2 ||
    mb_strlen($apellido, 'UTF-8') > 100
) {

    gestionarResponderError(
        'El apellido debe tener entre 2 y 100 caracteres.'
    );
}


if (
    $correo === '' ||
    mb_strlen($correo, 'UTF-8') > 254 ||
    filter_var($correo, FILTER_VALIDATE_EMAIL) === false
) {

    gestionarResponderError(
        'Debes ingresar un correo electrónico válido.'
    );
}


if (
    mb_strlen($usuario, 'UTF-8') < 3 ||
    mb_strlen($usuario, 'UTF-8') > 50
) {

    gestionarResponderError(
        'El usuario debe tener entre 3 y 50 caracteres.'
    );
}


if (
    !preg_match(
        '/^[a-zA-Z0-9._-]+$/',
        $usuario
    )
) {

    gestionarResponderError(
        'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos.'
    );
}


/* =========================================================
   17. VALIDAR CONTRASEÑA
========================================================= */

if ($accion === 'crear') {

    if ($password === '') {

        gestionarResponderError(
            'La contraseña es obligatoria.'
        );
    }

    if (strlen($password) < 6) {

        gestionarResponderError(
            'La contraseña debe tener al menos 6 caracteres.'
        );
    }

} elseif ($accion === 'editar') {

    if (
        $password !== '' &&
        strlen($password) < 6
    ) {

        gestionarResponderError(
            'La contraseña debe tener al menos 6 caracteres.'
        );
    }
}


/* =========================================================
   18. OBTENER PERMISO WEB
========================================================= */

$permisoWebEnviado = array_key_exists(
    'puede_administrar_web',
    $_POST
);

$permisoWebSolicitado = (
    $permisoWebEnviado &&
    gestionarBooleano(
        $_POST['puede_administrar_web']
    )
);


/* =========================================================
   19. INICIAR TRANSACCIÓN PARA CREAR / EDITAR
========================================================= */

try {

    $conexion->beginTransaction();


    /* =====================================================
       19.1 OBTENER CUENTA EXISTENTE SI ES EDICIÓN
    ===================================================== */

    $funcionarioExistente = null;

    $idFuncionarioEditar = null;


    if ($accion === 'editar') {

        $idFuncionarioEditar = trim(
            (string) ($_POST['id_funcionario'] ?? '')
        );


        if (
            !ctype_digit($idFuncionarioEditar) ||
            (int) $idFuncionarioEditar <= 0
        ) {

            $conexion->rollBack();

            gestionarResponderError(
                'El ID del funcionario no es válido.'
            );
        }


        $idFuncionarioEditar =
            (int) $idFuncionarioEditar;


        $stmtExistente = $conexion->prepare("
            SELECT
                id_funcionario,
                rol,
                activo,
                puede_administrar_web
            FROM funcionarios
            WHERE id_funcionario = :id_funcionario
            LIMIT 1
            FOR UPDATE
        ");

        $stmtExistente->execute([
            ':id_funcionario' => $idFuncionarioEditar
        ]);

        $funcionarioExistente =
            $stmtExistente->fetch(PDO::FETCH_ASSOC);


        if (!$funcionarioExistente) {

            $conexion->rollBack();

            gestionarResponderError(
                'No se encontró el funcionario que deseas editar.',
                404
            );
        }


        /*
         * No permitir quitarse el rol de superadmin.
         */

        if (
            $idFuncionarioEditar === $idAdministradorActual &&
            $rolSolicitado !== 'superadmin'
        ) {

            $conexion->rollBack();

            gestionarResponderError(
                'No puedes quitarte tu propio rol de superadministrador.',
                403
            );
        }


        /*
         * No permitir desactivar la propia cuenta.
         */

        if (
            $idFuncionarioEditar === $idAdministradorActual &&
            !$activo
        ) {

            $conexion->rollBack();

            gestionarResponderError(
                'No puedes desactivar tu propia cuenta.',
                403
            );
        }
    }


    /* =====================================================
       20. DETERMINAR TIPO DE ACCESO
    ===================================================== */

    /*
     * Tipos admitidos:
     *
     * superadmin:
     *   rol = superadmin
     *   puede_administrar_web = FALSE
     *
     * administrador:
     *   rol = administrador
     *   puede_administrar_web = FALSE
     *
     * administrador_web:
     *   rol = funcionario
     *   puede_administrar_web = TRUE
     */

    $rolBD = '';

    $puedeAdministrarWeb = false;


    if ($rolSolicitado === 'superadmin') {

        $rolBD = 'superadmin';

        $puedeAdministrarWeb = false;


    } elseif ($rolSolicitado === 'administrador') {

        $rolBD = 'administrador';

        $puedeAdministrarWeb = false;


    } elseif ($rolSolicitado === 'administrador_web') {

        $rolBD = 'funcionario';

        $puedeAdministrarWeb = true;


    } elseif ($rolSolicitado === 'funcionario') {

        /*
         * Compatibilidad con el formulario anterior.
         */

        if (
            $accion === 'editar' &&
            !$permisoWebEnviado
        ) {

            $permisoWebSolicitado =
                gestionarBooleano(
                    $funcionarioExistente['puede_administrar_web'] ?? false
                );
        }


        if (!$permisoWebSolicitado) {

            $conexion->rollBack();

            gestionarResponderError(
                'El perfil Funcionario sin permisos administrativos no puede crearse como cuenta del sistema. Selecciona Administrador web, Administrador de reservas o Superadministrador.'
            );
        }


        $rolBD = 'funcionario';

        $puedeAdministrarWeb = true;


    } else {

        $conexion->rollBack();

        gestionarResponderError(
            'Selecciona un tipo de acceso válido.'
        );
    }


    /*
     * Convertir los booleanos a texto PostgreSQL.
     *
     * Evita que PDO transforme false en ''.
     */

    $activoPostgres =
        gestionarBooleanoPostgres($activo);

    $permisoWebPostgres =
        gestionarBooleanoPostgres($puedeAdministrarWeb);


    /* =====================================================
       21. EVITAR DEJAR EL SISTEMA SIN SUPERADMINISTRADOR
    ===================================================== */

    if ($accion === 'editar') {

        $antesEraSuperadmin = (
            $funcionarioExistente['rol'] === 'superadmin' &&
            gestionarBooleano($funcionarioExistente['activo'])
        );

        $dejaráDeSerSuperadmin = (
            $rolBD !== 'superadmin' ||
            !$activo
        );


        if (
            $antesEraSuperadmin &&
            $dejaráDeSerSuperadmin &&
            gestionarContarSuperadminActivos($conexion) <= 1
        ) {

            $conexion->rollBack();

            gestionarResponderError(
                'No puedes quitar los privilegios o desactivar al último superadministrador activo.',
                403
            );
        }
    }


    /* =====================================================
       22. VERIFICAR USUARIO DUPLICADO
    ===================================================== */

    $sqlUsuario = "

        SELECT id_funcionario

        FROM funcionarios

        WHERE LOWER(usuario) = LOWER(:usuario)

    ";


    $parametrosUsuario = [
        ':usuario' => $usuario
    ];


    if ($accion === 'editar') {

        $sqlUsuario .= "
            AND id_funcionario <> :id_funcionario
        ";

        $parametrosUsuario[':id_funcionario'] =
            $idFuncionarioEditar;
    }


    $sqlUsuario .= " LIMIT 1";


    $stmtUsuario = $conexion->prepare(
        $sqlUsuario
    );

    $stmtUsuario->execute(
        $parametrosUsuario
    );


    if ($stmtUsuario->fetch(PDO::FETCH_ASSOC)) {

        $conexion->rollBack();

        gestionarResponderError(
            'El nombre de usuario ya está registrado.'
        );
    }


    /* =====================================================
       23. CREAR CUENTA
    ===================================================== */

    if ($accion === 'crear') {

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        if ($passwordHash === false) {

            $conexion->rollBack();

            gestionarResponderError(
                'No fue posible generar la contraseña.',
                500
            );
        }


        $sql = "

            INSERT INTO funcionarios (

                nombre,

                apellido,

                correo,

                usuario,

                password_hash,

                rol,

                activo,

                id_departamento,

                puede_administrar_web

            )

            VALUES (

                :nombre,

                :apellido,

                :correo,

                :usuario,

                :password_hash,

                :rol,

                :activo,

                NULL,

                :puede_administrar_web

            )

            RETURNING id_funcionario

        ";


        $stmt = $conexion->prepare($sql);


        $stmt->execute([

            ':nombre' =>
                $nombre,

            ':apellido' =>
                $apellido,

            ':correo' =>
                $correo,

            ':usuario' =>
                $usuario,

            ':password_hash' =>
                $passwordHash,

            ':rol' =>
                $rolBD,

            /*
             * IMPORTANTE:
             * Se envían cadenas TRUE/FALSE.
             */

            ':activo' =>
                $activoPostgres,

            ':puede_administrar_web' =>
                $permisoWebPostgres

        ]);


        $idCreado = (int) $stmt->fetchColumn();


        $conexion->commit();


        gestionarResponder([

            'ok' => true,

            'mensaje' =>
                'Cuenta de acceso creada correctamente.',

            'id_funcionario' =>
                $idCreado,

            'tipo_acceso' =>
                $rolBD === 'funcionario'
                    ? 'administrador_web'
                    : $rolBD

        ]);
    }


    /* =====================================================
       24. EDITAR CUENTA
    ===================================================== */

    if ($accion === 'editar') {

        $parametros = [

            ':nombre' =>
                $nombre,

            ':apellido' =>
                $apellido,

            ':correo' =>
                $correo,

            ':usuario' =>
                $usuario,

            ':rol' =>
                $rolBD,

            /*
             * IMPORTANTE:
             * Se envían cadenas TRUE/FALSE.
             */

            ':activo' =>
                $activoPostgres,

            ':puede_administrar_web' =>
                $permisoWebPostgres,

            ':id_funcionario' =>
                $idFuncionarioEditar

        ];


        if ($password !== '') {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            if ($passwordHash === false) {

                $conexion->rollBack();

                gestionarResponderError(
                    'No fue posible generar la contraseña.',
                    500
                );
            }


            $parametros[':password_hash'] =
                $passwordHash;


            $sql = "

                UPDATE funcionarios

                SET

                    nombre = :nombre,

                    apellido = :apellido,

                    correo = :correo,

                    usuario = :usuario,

                    password_hash = :password_hash,

                    rol = :rol,

                    activo = :activo,

                    id_departamento = NULL,

                    puede_administrar_web =
                        :puede_administrar_web

                WHERE id_funcionario =
                    :id_funcionario

            ";

        } else {

            $sql = "

                UPDATE funcionarios

                SET

                    nombre = :nombre,

                    apellido = :apellido,

                    correo = :correo,

                    usuario = :usuario,

                    rol = :rol,

                    activo = :activo,

                    id_departamento = NULL,

                    puede_administrar_web =
                        :puede_administrar_web

                WHERE id_funcionario =
                    :id_funcionario

            ";
        }


        $stmt = $conexion->prepare($sql);

        $stmt->execute($parametros);


        $conexion->commit();


        gestionarResponder([

            'ok' => true,

            'mensaje' =>
                'Cuenta de acceso actualizada correctamente.',

            'tipo_acceso' =>
                $rolBD === 'funcionario'
                    ? 'administrador_web'
                    : $rolBD

        ]);
    }


} catch (PDOException $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        'Error API gestionar funcionarios: ' .
        $e->getMessage()
    );

    gestionarResponderError(
        'No fue posible completar la operación. Comprueba los datos y las relaciones existentes.',
        500
    );


} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        'Error general API gestionar funcionarios: ' .
        $e->getMessage()
    );

    gestionarResponderError(
        'Ocurrió un error inesperado al gestionar la cuenta.',
        500
    );
}


/* =========================================================
   25. RESPUESTA FINAL
========================================================= */

gestionarResponderError(
    'No fue posible procesar la solicitud.',
    400
);

?>
