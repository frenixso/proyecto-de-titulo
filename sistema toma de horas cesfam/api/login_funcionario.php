<?php

header(
    'Content-Type: application/json; charset=utf-8'
);


/* =========================================================
   RESPUESTA JSON
   ========================================================= */

function responder(
    $ok,
    $mensaje,
    $codigo = 200,
    $datos = []
) {

    http_response_code(
        $codigo
    );

    echo json_encode(
        array_merge(
            [
                'ok' =>
                    $ok,

                'mensaje' =>
                    $mensaje
            ],
            $datos
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;

}


/* =========================================================
   MÉTODO HTTP
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] !==
    'POST'
) {

    responder(
        false,
        'Método no permitido.',
        405
    );

}


/* =========================================================
   LEER JSON
   ========================================================= */

$contenido =
    file_get_contents(
        'php://input'
    );


if (
    $contenido === false ||
    trim($contenido) === ''
) {

    responder(
        false,
        'No se recibieron datos.',
        400
    );

}


$datos =
    json_decode(
        $contenido,
        true
    );


/* =========================================================
   VALIDAR JSON
   ========================================================= */

if (
    !is_array($datos)
) {

    responder(
        false,
        'Los datos enviados no tienen un formato válido.',
        400
    );

}


/* =========================================================
   OBTENER CREDENCIALES
   ========================================================= */

$usuarioLogin =
    isset($datos['usuario'])
        ? trim(
            (string)
            $datos['usuario']
        )
        : '';


$passwordLogin =
    isset($datos['password'])
        ? (string)
            $datos['password']
        : '';


/* =========================================================
   VALIDAR CREDENCIALES
   ========================================================= */

if (
    $usuarioLogin === ''
) {

    responder(
        false,
        'Debe ingresar su usuario.',
        400
    );

}


if (
    $passwordLogin === ''
) {

    responder(
        false,
        'Debe ingresar su contraseña.',
        400
    );

}


/* =========================================================
   CONEXIÓN
   ========================================================= */

require_once __DIR__ .
    '/../config/conexion.php';


try {

    /* =====================================================
       BUSCAR FUNCIONARIO
       ===================================================== */

    $sql = "
        SELECT
            id_funcionario,
            nombre,
            apellido,
            correo,
            usuario,
            password_hash,
            rol,
            activo
        FROM funcionarios
        WHERE usuario = :usuario
        LIMIT 1
    ";


    $stmt =
        $conexion->prepare(
            $sql
        );


    $stmt->execute(
        [
            ':usuario' =>
                $usuarioLogin
        ]
    );


    $funcionario =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    /* =====================================================
       USUARIO NO EXISTE
       ===================================================== */

    if (
        !$funcionario
    ) {

        responder(
            false,
            'Usuario o contraseña incorrectos.',
            401
        );

    }


    /* =====================================================
       VERIFICAR CONTRASEÑA
       ===================================================== */

    if (
        !password_verify(
            $passwordLogin,
            $funcionario['password_hash']
        )
    ) {

        responder(
            false,
            'Usuario o contraseña incorrectos.',
            401
        );

    }


    /* =====================================================
       VERIFICAR ESTADO
       ===================================================== */

    if (
        !$funcionario['activo']
    ) {

        responder(
            false,
            'El usuario se encuentra inactivo.',
            403
        );

    }


    /* =====================================================
       VERIFICAR ROL
       ===================================================== */

    $rol =
        trim(
            (string)
            $funcionario['rol']
        );


    $rolesPermitidos = [
        'superadmin',
        'administrador',
        'funcionario'
    ];


    if (
        !in_array(
            $rol,
            $rolesPermitidos,
            true
        )
    ) {

        error_log(
            'Funcionario con rol inválido. ID: ' .
            $funcionario['id_funcionario']
        );


        responder(
            false,
            'El usuario no tiene un rol válido configurado.',
            403
        );

    }


    /* =====================================================
       CONFIGURAR SESIÓN
       ===================================================== */

    $httpsActivo =
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off';


    session_set_cookie_params(
        [
            'lifetime' =>
                0,

            'path' =>
                '/',

            'secure' =>
                $httpsActivo,

            'httponly' =>
                true,

            'samesite' =>
                'Lax'
        ]
    );


    ini_set(
        'session.use_strict_mode',
        '1'
    );


    ini_set(
        'session.use_only_cookies',
        '1'
    );


    ini_set(
        'session.use_trans_sid',
        '0'
    );


    if (
        session_status() !==
        PHP_SESSION_ACTIVE
    ) {

        session_start();

    }


    /* =====================================================
       REGENERAR ID DE SESIÓN
       ===================================================== */

    session_regenerate_id(
        true
    );


    /* =====================================================
       GUARDAR DATOS EN SESIÓN
       ===================================================== */

    $_SESSION[
        'funcionario_autenticado'
    ] = true;


    $_SESSION[
        'id_funcionario'
    ] =
        (int)
        $funcionario[
            'id_funcionario'
        ];


    $_SESSION[
        'nombre_funcionario'
    ] =
        $funcionario[
            'nombre'
        ];


    $_SESSION[
        'apellido_funcionario'
    ] =
        $funcionario[
            'apellido'
        ];


    $_SESSION[
        'usuario_funcionario'
    ] =
        $funcionario[
            'usuario'
        ];


    $_SESSION[
        'correo_funcionario'
    ] =
        $funcionario[
            'correo'
        ];


    $_SESSION[
        'rol_funcionario'
    ] =
        $rol;


    /* =====================================================
       ACTUALIZAR ÚLTIMO ACCESO
       ===================================================== */

    $sqlAcceso = "
        UPDATE funcionarios
        SET ultimo_acceso =
            CURRENT_TIMESTAMP
        WHERE id_funcionario =
            :id_funcionario
    ";


    $stmtAcceso =
        $conexion->prepare(
            $sqlAcceso
        );


    $stmtAcceso->execute(
        [
            ':id_funcionario' =>
                $funcionario[
                    'id_funcionario'
                ]
        ]
    );


    /* =====================================================
       RESPUESTA EXITOSA
       ===================================================== */

    responder(
        true,
        'Inicio de sesión correcto.',
        200,
        [

            'redirect' =>
                '../funcionarios/panel.php',

            'funcionario' => [

                'id' =>
                    (int)
                    $funcionario[
                        'id_funcionario'
                    ],

                'nombre' =>
                    $funcionario[
                        'nombre'
                    ],

                'apellido' =>
                    $funcionario[
                        'apellido'
                    ],

                'usuario' =>
                    $funcionario[
                        'usuario'
                    ],

                'rol' =>
                    $rol

            ]

        ]
    );


} catch (
    PDOException $e
) {

    error_log(
        'Error PDO en login_funcionario.php: ' .
        $e->getMessage()
    );


    responder(
        false,
        'No fue posible iniciar sesión. Intente nuevamente.',
        500
    );


} catch (
    Throwable $e
) {

    error_log(
        'Error general en login_funcionario.php: ' .
        $e->getMessage()
    );


    responder(
        false,
        'No fue posible iniciar sesión. Intente nuevamente.',
        500
    );

}

?>