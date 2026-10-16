<?php

header(
    'Content-Type: application/json; charset=utf-8'
);


require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';


/* =========================================================
   FUNCIONES DE RESPUESTA
   ========================================================= */

function responder(
    $datos,
    $codigo = 200
) {

    http_response_code(
        $codigo
    );

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


function responderError(
    $mensaje,
    $codigo = 400
) {

    responder(
        [
            'ok' => false,
            'error' => $mensaje
        ],
        $codigo
    );
}


/* =========================================================
   CONVERTIR BOOLEANO
   ========================================================= */

function convertirBooleano(
    $valor
) {

    return (
        $valor === true ||
        $valor === 1 ||
        $valor === '1' ||
        $valor === 'true' ||
        $valor === 't'
    );
}


/* =========================================================
   INICIAR SESIÓN
   ========================================================= */

if (
    session_status() ===
    PHP_SESSION_NONE
) {

    session_start();
}


/* =========================================================
   DATOS DE SESIÓN
   ========================================================= */

$usuarioFuncionario =
    trim(
        $_SESSION[
            'usuario_funcionario'
        ]
        ?? ''
    );


$rolFuncionario =
    trim(
        $_SESSION[
            'rol_funcionario'
        ]
        ?? ''
    );


/* =========================================================
   VALIDAR SESIÓN
   ========================================================= */

if (
    $usuarioFuncionario === ''
) {

    responderError(
        'La sesión no es válida.',
        401
    );
}


/* =========================================================
   VALIDAR ROL DE ACCESO
   ========================================================= */

if (
    $rolFuncionario !== 'superadmin' &&
    $rolFuncionario !== 'administrador'
) {

    responderError(
        'No tienes permisos para administrar funcionarios.',
        403
    );
}


/* =========================================================
   VALIDAR FUNCIONARIO ACTUAL
   ========================================================= */

try {

    $sqlAdministrador = "
        SELECT
            id_funcionario,
            nombre,
            apellido,
            correo,
            usuario,
            rol,
            activo,
            id_departamento
        FROM funcionarios
        WHERE usuario = :usuario
        LIMIT 1
    ";


    $stmtAdministrador =
        $conexion->prepare(
            $sqlAdministrador
        );


    $stmtAdministrador->execute(
        [
            ':usuario' =>
                $usuarioFuncionario
        ]
    );


    $administrador =
        $stmtAdministrador->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$administrador
    ) {

        responderError(
            'No fue posible validar el funcionario administrador.',
            403
        );
    }


    if (
        !convertirBooleano(
            $administrador['activo']
        )
    ) {

        responderError(
            'El funcionario administrador está inactivo.',
            403
        );
    }


    /*
     * El rol almacenado en la base de datos
     * es la referencia definitiva para los permisos.
     */
    $rolActual =
        trim(
            $administrador['rol']
            ?? ''
        );


    $idDepartamentoActual =
        $administrador['id_departamento'] !== null
            ? (int)
                $administrador['id_departamento']
            : null;


    /*
     * La sesión también debe coincidir con
     * el rol actual de la base de datos.
     */
    if (
        $rolActual !== $rolFuncionario
    ) {

        responderError(
            'Los permisos de la sesión no coinciden con los permisos actuales del funcionario.',
            403
        );
    }


    /*
     * Un administrador de departamento debe
     * tener obligatoriamente un departamento.
     */
    if (
        $rolActual === 'administrador' &&
        $idDepartamentoActual === null
    ) {

        responderError(
            'El administrador no tiene un departamento asignado.',
            403
        );
    }


} catch (
    PDOException $e
) {

    error_log(
        'Error validar administrador: ' .
        $e->getMessage()
    );


    responderError(
        'No fue posible validar los permisos.',
        500
    );
}


/* =========================================================
   MÉTODO Y ACCIÓN
   ========================================================= */

$metodo =
    $_SERVER['REQUEST_METHOD']
    ?? 'GET';


$accion =
    $_POST['accion']
    ?? $_GET['accion']
    ?? '';


$accionesPermitidas = [
    'obtener',
    'crear',
    'editar',
    'activar',
    'desactivar',
    'eliminar'
];


if (
    !in_array(
        $accion,
        $accionesPermitidas,
        true
    )
) {

    responderError(
        'Acción no válida.',
        400
    );
}


/* =========================================================
   OBTENER FUNCIONARIO
   ========================================================= */

if (
    $accion ===
    'obtener'
) {

    $idFuncionario =
        $_GET['id']
        ?? $_POST['id_funcionario']
        ?? '';


    if (
        !ctype_digit(
            (string)
            $idFuncionario
        )
    ) {

        responderError(
            'El ID del funcionario no es válido.'
        );
    }


    $idFuncionario =
        (int)
        $idFuncionario;


    try {

        /*
         * Superadmin puede consultar cualquier funcionario.
         *
         * Administrador solamente puede consultar
         * funcionarios de su propio departamento.
         */
        $sql = "
            SELECT
                f.id_funcionario,
                f.nombre,
                f.apellido,
                f.correo,
                f.usuario,
                f.rol,
                f.activo,
                f.id_departamento,
                d.nombre AS departamento
            FROM funcionarios f
            LEFT JOIN departamentos d
                ON d.id_departamento =
                   f.id_departamento
            WHERE f.id_funcionario =
                  :id_funcionario
        ";


        if (
            $rolActual ===
            'administrador'
        ) {

            $sql .= "
                AND f.id_departamento =
                    :id_departamento_actual
            ";
        }


        $sql .= "
            LIMIT 1
        ";


        $stmt =
            $conexion->prepare(
                $sql
            );


        $parametros = [
            ':id_funcionario' =>
                $idFuncionario
        ];


        if (
            $rolActual ===
            'administrador'
        ) {

            $parametros[
                ':id_departamento_actual'
            ] =
                $idDepartamentoActual;
        }


        $stmt->execute(
            $parametros
        );


        $funcionario =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$funcionario
        ) {

            responderError(
                'No se encontró el funcionario o no tienes permisos para consultarlo.',
                404
            );
        }


        responder(
            [
                'ok' => true,
                'funcionario' =>
                    $funcionario
            ]
        );

    } catch (
        PDOException $e
    ) {

        error_log(
            'Error obtener funcionario: ' .
            $e->getMessage()
        );


        responderError(
            'No fue posible obtener el funcionario.',
            500
        );
    }
}


/* =========================================================
   VALIDAR MÉTODO PARA MODIFICACIONES
   ========================================================= */

if (
    $metodo !==
    'POST'
) {

    responderError(
        'Esta acción requiere una solicitud POST.',
        405
    );
}


/* =========================================================
   OBTENER ID DE FUNCIONARIO
   ========================================================= */

$idFuncionario =
    trim(
        $_POST['id_funcionario']
        ?? ''
    );


/* =========================================================
   ACTIVAR FUNCIONARIO
   ========================================================= */

if (
    $accion ===
    'activar'
) {

    if (
        !ctype_digit(
            $idFuncionario
        )
    ) {

        responderError(
            'El ID del funcionario no es válido.'
        );
    }


    $idFuncionario =
        (int)
        $idFuncionario;


    try {

        /*
         * El funcionario debe pertenecer al
         * departamento administrado actualmente.
         */
        $sqlExiste = "
            SELECT
                id_funcionario,
                rol,
                id_departamento
            FROM funcionarios
            WHERE id_funcionario =
                  :id_funcionario
        ";


        if (
            $rolActual ===
            'administrador'
        ) {

            $sqlExiste .= "
                AND id_departamento =
                    :id_departamento_actual
            ";
        }


        $sqlExiste .= "
            LIMIT 1
        ";


        $stmtExiste =
            $conexion->prepare(
                $sqlExiste
            );


        $parametrosExiste = [
            ':id_funcionario' =>
                $idFuncionario
        ];


        if (
            $rolActual ===
            'administrador'
        ) {

            $parametrosExiste[
                ':id_departamento_actual'
            ] =
                $idDepartamentoActual;
        }


        $stmtExiste->execute(
            $parametrosExiste
        );


        $funcionarioObjetivo =
            $stmtExiste->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$funcionarioObjetivo
        ) {

            responderError(
                'No se encontró el funcionario o no tienes permisos para modificarlo.',
                404
            );
        }


        /*
         * Un administrador de departamento
         * nunca puede activar un superadmin.
         */
        if (
            $rolActual ===
            'administrador' &&
            $funcionarioObjetivo['rol'] ===
            'superadmin'
        ) {

            responderError(
                'No tienes permisos para modificar un superadmin.',
                403
            );
        }


        $sql = "
            UPDATE funcionarios
            SET activo = TRUE
            WHERE id_funcionario =
                  :id_funcionario
        ";


        $stmt =
            $conexion->prepare(
                $sql
            );


        $stmt->execute(
            [
                ':id_funcionario' =>
                    $idFuncionario
            ]
        );


        responder(
            [
                'ok' => true,
                'mensaje' =>
                    'Funcionario activado correctamente.'
            ]
        );

    } catch (
        PDOException $e
    ) {

        error_log(
            'Error activar funcionario: ' .
            $e->getMessage()
        );


        responderError(
            'No fue posible activar el funcionario.',
            500
        );
    }
}


/* =========================================================
   DESACTIVAR FUNCIONARIO
   ========================================================= */

if (
    $accion ===
    'desactivar'
) {

    if (
        !ctype_digit(
            $idFuncionario
        )
    ) {

        responderError(
            'El ID del funcionario no es válido.'
        );
    }


    $idFuncionario =
        (int)
        $idFuncionario;


    if (
        $idFuncionario ===
        (int)
        $administrador['id_funcionario']
    ) {

        responderError(
            'No puedes desactivar tu propio usuario.'
        );
    }


    try {

        $sqlExiste = "
            SELECT
                id_funcionario,
                rol,
                id_departamento
            FROM funcionarios
            WHERE id_funcionario =
                  :id_funcionario
        ";


        if (
            $rolActual ===
            'administrador'
        ) {

            $sqlExiste .= "
                AND id_departamento =
                    :id_departamento_actual
            ";
        }


        $sqlExiste .= "
            LIMIT 1
        ";


        $stmtExiste =
            $conexion->prepare(
                $sqlExiste
            );


        $parametrosExiste = [
            ':id_funcionario' =>
                $idFuncionario
        ];


        if (
            $rolActual ===
            'administrador'
        ) {

            $parametrosExiste[
                ':id_departamento_actual'
            ] =
                $idDepartamentoActual;
        }


        $stmtExiste->execute(
            $parametrosExiste
        );


        $funcionarioObjetivo =
            $stmtExiste->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$funcionarioObjetivo
        ) {

            responderError(
                'No se encontró el funcionario o no tienes permisos para modificarlo.',
                404
            );
        }


        if (
            $rolActual ===
            'administrador' &&
            $funcionarioObjetivo['rol'] ===
            'superadmin'
        ) {

            responderError(
                'No tienes permisos para modificar un superadmin.',
                403
            );
        }


        $sql = "
            UPDATE funcionarios
            SET activo = FALSE
            WHERE id_funcionario =
                  :id_funcionario
        ";


        $stmt =
            $conexion->prepare(
                $sql
            );


        $stmt->execute(
            [
                ':id_funcionario' =>
                    $idFuncionario
            ]
        );


        responder(
            [
                'ok' => true,
                'mensaje' =>
                    'Funcionario desactivado correctamente.'
            ]
        );

    } catch (
        PDOException $e
    ) {

        error_log(
            'Error desactivar funcionario: ' .
            $e->getMessage()
        );


        responderError(
            'No fue posible desactivar el funcionario.',
            500
        );
    }
}


/* =========================================================
   ELIMINAR FUNCIONARIO
   ========================================================= */

if (
    $accion ===
    'eliminar'
) {

    if (
        !ctype_digit(
            $idFuncionario
        )
    ) {

        responderError(
            'El ID del funcionario no es válido.'
        );
    }


    $idFuncionario =
        (int)
        $idFuncionario;


    if (
        $idFuncionario ===
        (int)
        $administrador['id_funcionario']
    ) {

        responderError(
            'No puedes eliminar tu propio usuario.'
        );
    }


    try {

        $sqlExiste = "
            SELECT
                id_funcionario,
                rol,
                id_departamento
            FROM funcionarios
            WHERE id_funcionario =
                  :id_funcionario
        ";


        if (
            $rolActual ===
            'administrador'
        ) {

            $sqlExiste .= "
                AND id_departamento =
                    :id_departamento_actual
            ";
        }


        $sqlExiste .= "
            LIMIT 1
        ";


        $stmtExiste =
            $conexion->prepare(
                $sqlExiste
            );


        $parametrosExiste = [
            ':id_funcionario' =>
                $idFuncionario
        ];


        if (
            $rolActual ===
            'administrador'
        ) {

            $parametrosExiste[
                ':id_departamento_actual'
            ] =
                $idDepartamentoActual;
        }


        $stmtExiste->execute(
            $parametrosExiste
        );


        $funcionarioObjetivo =
            $stmtExiste->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$funcionarioObjetivo
        ) {

            responderError(
                'No se encontró el funcionario o no tienes permisos para eliminarlo.',
                404
            );
        }


        if (
            $rolActual ===
            'administrador' &&
            $funcionarioObjetivo['rol'] ===
            'superadmin'
        ) {

            responderError(
                'No tienes permisos para eliminar un superadmin.',
                403
            );
        }


        $sql = "
            DELETE FROM funcionarios
            WHERE id_funcionario =
                  :id_funcionario
        ";


        $stmt =
            $conexion->prepare(
                $sql
            );


        $stmt->execute(
            [
                ':id_funcionario' =>
                    $idFuncionario
            ]
        );


        responder(
            [
                'ok' => true,
                'mensaje' =>
                    'Funcionario eliminado correctamente.'
            ]
        );

    } catch (
        PDOException $e
    ) {

        error_log(
            'Error eliminar funcionario: ' .
            $e->getMessage()
        );


        responderError(
            'No fue posible eliminar el funcionario.',
            500
        );
    }
}


/* =========================================================
   DATOS CREAR / EDITAR
   ========================================================= */

$nombre =
    trim(
        $_POST['nombre']
        ?? ''
    );


$apellido =
    trim(
        $_POST['apellido']
        ?? ''
    );


$correo =
    trim(
        $_POST['correo']
        ?? ''
    );


$usuario =
    trim(
        $_POST['usuario']
        ?? ''
    );


$rol =
    trim(
        $_POST['rol']
        ?? ''
    );


$password =
    $_POST['password']
    ?? '';


$activo =
    $_POST['activo']
    ?? '1';


$idDepartamento =
    trim(
        $_POST['id_departamento']
        ?? ''
    );


/* =========================================================
   NORMALIZAR ESTADO
   ========================================================= */

$activo =
    convertirBooleano(
        $activo
    );


/* =========================================================
   VALIDAR NOMBRE
   ========================================================= */

if (
    mb_strlen(
        $nombre,
        'UTF-8'
    ) < 2 ||
    mb_strlen(
        $nombre,
        'UTF-8'
    ) > 100
) {

    responderError(
        'El nombre debe tener entre 2 y 100 caracteres.'
    );
}


/* =========================================================
   VALIDAR APELLIDO
   ========================================================= */

if (
    mb_strlen(
        $apellido,
        'UTF-8'
    ) < 2 ||
    mb_strlen(
        $apellido,
        'UTF-8'
    ) > 100
) {

    responderError(
        'El apellido debe tener entre 2 y 100 caracteres.'
    );
}


/* =========================================================
   VALIDAR CORREO
   ========================================================= */

if (
    $correo === ''
) {

    responderError(
        'El correo electrónico es obligatorio.'
    );
}


if (
    mb_strlen(
        $correo,
        'UTF-8'
    ) > 254
) {

    responderError(
        'El correo electrónico es demasiado largo.'
    );
}


if (
    filter_var(
        $correo,
        FILTER_VALIDATE_EMAIL
    ) === false
) {

    responderError(
        'El correo electrónico no tiene un formato válido.'
    );
}


/* =========================================================
   VALIDAR USUARIO
   ========================================================= */

if (
    mb_strlen(
        $usuario,
        'UTF-8'
    ) < 3 ||
    mb_strlen(
        $usuario,
        'UTF-8'
    ) > 50
) {

    responderError(
        'El usuario debe tener entre 3 y 50 caracteres.'
    );
}


if (
    !preg_match(
        '/^[a-zA-Z0-9._-]+$/',
        $usuario
    )
) {

    responderError(
        'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos.'
    );
}


/* =========================================================
   VALIDAR ROL
   ========================================================= */

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

    responderError(
        'El rol seleccionado no es válido.'
    );
}


/* =========================================================
   VALIDAR PERMISOS SOBRE EL ROL
   ========================================================= */

/*
 * El superadmin puede administrar los tres roles.
 *
 * El administrador de departamento solamente puede
 * crear y editar funcionarios.
 *
 * Esta validación se realiza en el backend para que
 * no pueda saltarse la restricción modificando la
 * petición desde el navegador.
 */
if (
    $rolActual ===
    'administrador' &&
    $rol !==
    'funcionario'
) {

    responderError(
        'Un administrador de departamento solamente puede crear o editar funcionarios.',
        403
    );
}


/* =========================================================
   VALIDAR DEPARTAMENTO
   ========================================================= */

if (
    $rolActual ===
    'administrador'
) {

    /*
     * Para un administrador de departamento,
     * el departamento enviado por el navegador
     * NO es una fuente confiable.
     *
     * Siempre se utiliza el departamento
     * almacenado en la cuenta del administrador.
     */
    if (
        $idDepartamentoActual === null
    ) {

        responderError(
            'El administrador no tiene un departamento asignado.',
            403
        );
    }


    $idDepartamento =
        $idDepartamentoActual;

} elseif (
    $rol ===
    'superadmin'
) {

    /*
     * El superadmin no pertenece obligatoriamente
     * a un departamento.
     */
    $idDepartamento = '';

} else {

    /*
     * Cuando el superadmin crea un funcionario
     * o administrador, el departamento enviado
     * desde el formulario sí puede utilizarse.
     */
    if (
        $idDepartamento === ''
    ) {

        responderError(
            'Debes seleccionar un departamento.'
        );
    }


    if (
        !ctype_digit(
            $idDepartamento
        )
    ) {

        responderError(
            'El departamento seleccionado no es válido.'
        );
    }


    $idDepartamento =
        (int)
        $idDepartamento;
}


/* =========================================================
   VALIDAR DEPARTAMENTO EN BASE DE DATOS
   ========================================================= */

if (
    $rol !==
    'superadmin'
) {

    try {

        $sqlDepartamento = "
            SELECT
                id_departamento,
                nombre,
                activo
            FROM departamentos
            WHERE id_departamento =
                  :id_departamento
            LIMIT 1
        ";


        $stmtDepartamento =
            $conexion->prepare(
                $sqlDepartamento
            );


        $stmtDepartamento->execute(
            [
                ':id_departamento' =>
                    $idDepartamento
            ]
        );


        $departamento =
            $stmtDepartamento->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$departamento
        ) {

            responderError(
                'El departamento seleccionado no existe.'
            );
        }


        if (
            !convertirBooleano(
                $departamento['activo']
            )
        ) {

            responderError(
                'El departamento seleccionado está inactivo.'
            );
        }

    } catch (
        PDOException $e
    ) {

        error_log(
            'Error validar departamento: ' .
            $e->getMessage()
        );


        responderError(
            'No fue posible validar el departamento.',
            500
        );
    }
}


/* =========================================================
   VALIDAR CONTRASEÑA
   ========================================================= */

if (
    $accion ===
    'crear'
) {

    if (
        $password === ''
    ) {

        responderError(
            'La contraseña es obligatoria para crear un funcionario.'
        );
    }


    if (
        strlen(
            $password
        ) < 6
    ) {

        responderError(
            'La contraseña debe tener al menos 6 caracteres.'
        );
    }

} elseif (
    $accion ===
    'editar'
) {

    if (
        $password !== '' &&
        strlen(
            $password
        ) < 6
    ) {

        responderError(
            'La contraseña debe tener al menos 6 caracteres.'
        );
    }
}


/* =========================================================
   CREAR FUNCIONARIO
   ========================================================= */

if (
    $accion ===
    'crear'
) {

    try {

        /* =================================================
           VERIFICAR USUARIO DUPLICADO
           ================================================= */

        $sqlExiste = "
            SELECT
                id_funcionario
            FROM funcionarios
            WHERE LOWER(usuario) =
                  LOWER(:usuario)
            LIMIT 1
        ";


        $stmtExiste =
            $conexion->prepare(
                $sqlExiste
            );


        $stmtExiste->execute(
            [
                ':usuario' =>
                    $usuario
            ]
        );


        if (
            $stmtExiste->fetch(
                PDO::FETCH_ASSOC
            )
        ) {

            responderError(
                'El nombre de usuario ya está registrado.'
            );
        }


        /* =================================================
           GENERAR CONTRASEÑA
           ================================================= */

        $passwordHash =
            password_hash(
                $password,
                PASSWORD_DEFAULT
            );


        if (
            $passwordHash === false
        ) {

            responderError(
                'No fue posible generar la contraseña.',
                500
            );
        }


        /* =================================================
           INSERTAR FUNCIONARIO
           ================================================= */

        $sql = "
            INSERT INTO funcionarios (
                nombre,
                apellido,
                correo,
                usuario,
                password_hash,
                rol,
                activo,
                id_departamento
            )
            VALUES (
                :nombre,
                :apellido,
                :correo,
                :usuario,
                :password_hash,
                :rol,
                :activo,
                :id_departamento
            )
            RETURNING id_funcionario
        ";


        $stmt =
            $conexion->prepare(
                $sql
            );


        $parametros = [

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
                $rol,

            ':activo' =>
                $activo,

            ':id_departamento' =>
                $rol === 'superadmin'
                    ? null
                    : $idDepartamento
        ];


        $stmt->execute(
            $parametros
        );


        $idCreado =
            $stmt->fetchColumn();


        responder(
            [
                'ok' => true,
                'mensaje' =>
                    'Funcionario creado correctamente.',
                'id_funcionario' =>
                    (int)
                    $idCreado
            ]
        );

    } catch (
        PDOException $e
    ) {

        error_log(
            'Error crear funcionario: ' .
            $e->getMessage()
        );


        responderError(
            'No fue posible crear el funcionario.',
            500
        );
    }
}


/* =========================================================
   EDITAR FUNCIONARIO
   ========================================================= */

if (
    $accion ===
    'editar'
) {

    if (
        !ctype_digit(
            $idFuncionario
        )
    ) {

        responderError(
            'El ID del funcionario no es válido.'
        );
    }


    $idFuncionario =
        (int)
        $idFuncionario;


    try {

        /* =================================================
           VERIFICAR FUNCIONARIO
           ================================================= */

        $sqlExiste = "
            SELECT
                id_funcionario,
                rol,
                activo,
                id_departamento
            FROM funcionarios
            WHERE id_funcionario =
                  :id_funcionario
        ";


        if (
            $rolActual ===
            'administrador'
        ) {

            $sqlExiste .= "
                AND id_departamento =
                    :id_departamento_actual
            ";
        }


        $sqlExiste .= "
            LIMIT 1
        ";


        $stmtExiste =
            $conexion->prepare(
                $sqlExiste
            );


        $parametrosExiste = [
            ':id_funcionario' =>
                $idFuncionario
        ];


        if (
            $rolActual ===
            'administrador'
        ) {

            $parametrosExiste[
                ':id_departamento_actual'
            ] =
                $idDepartamentoActual;
        }


        $stmtExiste->execute(
            $parametrosExiste
        );


        $funcionarioExistente =
            $stmtExiste->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$funcionarioExistente
        ) {

            responderError(
                'No se encontró el funcionario o no tienes permisos para modificarlo.',
                404
            );
        }


        /* =================================================
           PROTEGER SUPERADMIN
           ================================================= */

        if (
            $rolActual ===
            'administrador' &&
            $funcionarioExistente['rol'] ===
            'superadmin'
        ) {

            responderError(
                'No tienes permisos para modificar un superadmin.',
                403
            );
        }


        /*
         * Un administrador de departamento
         * solamente puede editar funcionarios.
         *
         * Esta validación también impide que un
         * administrador intente convertir un funcionario
         * en administrador mediante una petición manipulada.
         */
        if (
            $rolActual ===
            'administrador' &&
            $rol !==
            'funcionario'
        ) {

            responderError(
                'Un administrador de departamento solamente puede editar funcionarios.',
                403
            );
        }


        /*
         * Si el administrador está editando
         * un funcionario de su departamento,
         * el departamento se fuerza nuevamente
         * al departamento del administrador.
         *
         * Esto evita que una petición manipulada
         * intente mover al funcionario a otro
         * departamento.
         */
        if (
            $rolActual ===
            'administrador'
        ) {

            $idDepartamento =
                $idDepartamentoActual;
        }


        /* =================================================
           VERIFICAR USUARIO DUPLICADO
           ================================================= */

        $sqlUsuario = "
            SELECT
                id_funcionario
            FROM funcionarios
            WHERE LOWER(usuario) =
                  LOWER(:usuario)
              AND id_funcionario <>
                  :id_funcionario
            LIMIT 1
        ";


        $stmtUsuario =
            $conexion->prepare(
                $sqlUsuario
            );


        $stmtUsuario->execute(
            [
                ':usuario' =>
                    $usuario,

                ':id_funcionario' =>
                    $idFuncionario
            ]
        );


        if (
            $stmtUsuario->fetch(
                PDO::FETCH_ASSOC
            )
        ) {

            responderError(
                'El nombre de usuario ya está registrado por otro funcionario.'
            );
        }


        /* =================================================
           EVITAR DESACTIVAR AL USUARIO ACTUAL
           ================================================= */

        if (
            $idFuncionario ===
            (int)
            $administrador['id_funcionario'] &&
            !$activo
        ) {

            responderError(
                'No puedes desactivar tu propio usuario.'
            );
        }


        /*
         * Un superadmin no puede quitarse a sí mismo
         * el rol superadmin desde esta pantalla.
         */
        if (
            $idFuncionario ===
            (int)
            $administrador['id_funcionario'] &&
            $rolActual ===
            'superadmin' &&
            $rol !==
            'superadmin'
        ) {

            responderError(
                'No puedes quitarte tu propio rol de superadmin.'
            );
        }


        /* =================================================
           ACTUALIZAR CON CONTRASEÑA
           ================================================= */

        if (
            $password !== ''
        ) {

            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            if (
                $passwordHash === false
            ) {

                responderError(
                    'No fue posible generar la contraseña.',
                    500
                );
            }


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
                    id_departamento = :id_departamento
                WHERE id_funcionario =
                      :id_funcionario
            ";


            $parametros = [

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
                    $rol,

                ':activo' =>
                    $activo,

                ':id_departamento' =>
                    $rol === 'superadmin'
                        ? null
                        : $idDepartamento,

                ':id_funcionario' =>
                    $idFuncionario
            ];

        } else {

            /* =============================================
               ACTUALIZAR SIN CAMBIAR CONTRASEÑA
               ============================================= */

            $sql = "
                UPDATE funcionarios
                SET
                    nombre = :nombre,
                    apellido = :apellido,
                    correo = :correo,
                    usuario = :usuario,
                    rol = :rol,
                    activo = :activo,
                    id_departamento = :id_departamento
                WHERE id_funcionario =
                      :id_funcionario
            ";


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
                    $rol,

                ':activo' =>
                    $activo,

                ':id_departamento' =>
                    $rol === 'superadmin'
                        ? null
                        : $idDepartamento,

                ':id_funcionario' =>
                    $idFuncionario
            ];
        }


        /* =================================================
           EJECUTAR ACTUALIZACIÓN
           ================================================= */

        $stmt =
            $conexion->prepare(
                $sql
            );


        $stmt->execute(
            $parametros
        );


        responder(
            [
                'ok' => true,
                'mensaje' =>
                    'Funcionario actualizado correctamente.'
            ]
        );

    } catch (
        PDOException $e
    ) {

        error_log(
            'Error editar funcionario: ' .
            $e->getMessage()
        );


        responderError(
            'No fue posible actualizar el funcionario.',
            500
        );
    }
}


/* =========================================================
   ACCIÓN NO PROCESADA
   ========================================================= */

responderError(
    'No fue posible procesar la acción solicitada.',
    400
);