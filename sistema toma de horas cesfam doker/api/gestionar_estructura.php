<?php

header(
    'Content-Type: application/json; charset=utf-8'
);

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';


/* =========================================================
   FUNCIONES DE RESPUESTA
   ========================================================= */

function responderJSON(
    $datos,
    $codigo = 200
) {

    http_response_code($codigo);

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

    responderJSON(
        [
            'ok' => false,
            'mensaje' => $mensaje
        ],
        $codigo
    );
}


/* =========================================================
   VALIDACIÓN DE SESIÓN
   ========================================================= */

if (
    !isset($_SESSION['id_funcionario']) &&
    !isset($_SESSION['usuario_funcionario'])
) {

    responderError(
        'La sesión del funcionario no es válida.',
        401
    );
}


/* =========================================================
   VALIDACIÓN DE ROL
   SOLO SUPERADMIN
   ========================================================= */

$rolFuncionario = strtolower(
    trim(
        $_SESSION['rol_funcionario'] ?? ''
    )
);


if (
    $rolFuncionario !== 'superadmin'
) {

    responderError(
        'No tiene permisos para realizar esta acción.',
        403
    );
}


/* =========================================================
   VALIDACIÓN DEL MÉTODO
   ========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    responderError(
        'Método de solicitud no permitido.',
        405
    );
}


/* =========================================================
   OBTENER DATOS JSON
   ========================================================= */

$contenido =
    file_get_contents('php://input');


if (
    $contenido === false ||
    trim($contenido) === ''
) {

    responderError(
        'No se recibieron datos.'
    );
}


$datos = json_decode(
    $contenido,
    true
);


if (
    !is_array($datos)
) {

    responderError(
        'Los datos recibidos no tienen un formato válido.'
    );
}


/* =========================================================
   DATOS GENERALES
   ========================================================= */

$accion = strtolower(
    trim(
        $datos['accion'] ?? ''
    )
);


$tipo = strtolower(
    trim(
        $datos['tipo'] ?? ''
    )
);


$accionesPermitidas = [
    'crear',
    'editar',
    'activar',
    'desactivar',
    'eliminar'
];


$tiposPermitidos = [
    'departamento',
    'servicio'
];


if (
    !in_array(
        $accion,
        $accionesPermitidas,
        true
    )
) {

    responderError(
        'La acción solicitada no es válida.'
    );
}


if (
    !in_array(
        $tipo,
        $tiposPermitidos,
        true
    )
) {

    responderError(
        'El tipo de elemento solicitado no es válido.'
    );
}


/* =========================================================
   FUNCIONES DE VALIDACIÓN
   ========================================================= */

function obtenerTexto(
    $valor
) {

    if (
        $valor === null
    ) {

        return '';
    }

    return trim(
        (string) $valor
    );
}


function validarNombre(
    $nombre,
    $elemento
) {

    if (
        $nombre === ''
    ) {

        responderError(
            'Debe ingresar el nombre del ' .
            $elemento .
            '.'
        );
    }


    if (
        mb_strlen($nombre) < 2
    ) {

        responderError(
            'El nombre del ' .
            $elemento .
            ' es demasiado corto.'
        );
    }


    if (
        mb_strlen($nombre) > 150
    ) {

        responderError(
            'El nombre del ' .
            $elemento .
            ' no puede superar los 150 caracteres.'
        );
    }
}


/* =========================================================
   PROCESAMIENTO
   ========================================================= */

try {


    /* =====================================================
       DEPARTAMENTOS
       ===================================================== */

    if (
        $tipo === 'departamento'
    ) {


        /* =================================================
           CREAR DEPARTAMENTO
           ================================================= */

        if (
            $accion === 'crear'
        ) {

            $nombre = obtenerTexto(
                $datos['nombre'] ?? ''
            );


            validarNombre(
                $nombre,
                'departamento'
            );


            $sqlExiste = "
                SELECT id_departamento
                FROM departamentos
                WHERE
                    LOWER(TRIM(nombre))
                    =
                    LOWER(TRIM(:nombre))
                LIMIT 1
            ";


            $stmtExiste =
                $conexion->prepare(
                    $sqlExiste
                );


            $stmtExiste->execute([
                ':nombre' => $nombre
            ]);


            if (
                $stmtExiste->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                responderError(
                    'Ya existe un departamento con ese nombre.'
                );
            }


            $sqlInsertar = "
                INSERT INTO departamentos (
                    nombre,
                    activo
                )
                VALUES (
                    :nombre,
                    TRUE
                )
                RETURNING
                    id_departamento,
                    nombre,
                    activo
            ";


            $stmtInsertar =
                $conexion->prepare(
                    $sqlInsertar
                );


            $stmtInsertar->execute([
                ':nombre' => $nombre
            ]);


            $departamento =
                $stmtInsertar->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$departamento
            ) {

                responderError(
                    'No fue posible crear el departamento.',
                    500
                );
            }


            responderJSON([
                'ok' => true,
                'mensaje' =>
                    'Departamento creado correctamente.',
                'departamento' =>
                    $departamento
            ]);
        }


        /* =================================================
           EDITAR DEPARTAMENTO
           ================================================= */

        if (
            $accion === 'editar'
        ) {

            $idDepartamento =
                filter_var(
                    $datos['id_departamento'] ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$idDepartamento ||
                $idDepartamento <= 0
            ) {

                responderError(
                    'El identificador del departamento no es válido.'
                );
            }


            $nombre = obtenerTexto(
                $datos['nombre'] ?? ''
            );


            validarNombre(
                $nombre,
                'departamento'
            );


            $sqlExiste = "
                SELECT
                    id_departamento,
                    nombre
                FROM departamentos
                WHERE
                    id_departamento = :id
                LIMIT 1
            ";


            $stmtExiste =
                $conexion->prepare(
                    $sqlExiste
                );


            $stmtExiste->execute([
                ':id' =>
                    $idDepartamento
            ]);


            $departamentoExistente =
                $stmtExiste->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$departamentoExistente
            ) {

                responderError(
                    'El departamento solicitado no existe.'
                );
            }


            $sqlDuplicado = "
                SELECT id_departamento
                FROM departamentos
                WHERE
                    LOWER(TRIM(nombre))
                    =
                    LOWER(TRIM(:nombre))
                    AND id_departamento <> :id
                LIMIT 1
            ";


            $stmtDuplicado =
                $conexion->prepare(
                    $sqlDuplicado
                );


            $stmtDuplicado->execute([
                ':nombre' =>
                    $nombre,
                ':id' =>
                    $idDepartamento
            ]);


            if (
                $stmtDuplicado->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                responderError(
                    'Ya existe otro departamento con ese nombre.'
                );
            }


            $sqlActualizar = "
                UPDATE departamentos
                SET
                    nombre = :nombre
                WHERE
                    id_departamento = :id
            ";


            $stmtActualizar =
                $conexion->prepare(
                    $sqlActualizar
                );


            $stmtActualizar->execute([
                ':nombre' =>
                    $nombre,
                ':id' =>
                    $idDepartamento
            ]);


            responderJSON([
                'ok' => true,
                'mensaje' =>
                    'Departamento actualizado correctamente.'
            ]);
        }


        /* =================================================
           ACTIVAR / DESACTIVAR DEPARTAMENTO
           ================================================= */

        if (
            $accion === 'activar' ||
            $accion === 'desactivar'
        ) {

            $idDepartamento =
                filter_var(
                    $datos['id_departamento'] ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$idDepartamento ||
                $idDepartamento <= 0
            ) {

                responderError(
                    'El identificador del departamento no es válido.'
                );
            }


            $sqlExiste = "
                SELECT
                    id_departamento,
                    activo
                FROM departamentos
                WHERE
                    id_departamento = :id
                LIMIT 1
            ";


            $stmtExiste =
                $conexion->prepare(
                    $sqlExiste
                );


            $stmtExiste->execute([
                ':id' =>
                    $idDepartamento
            ]);


            $departamento =
                $stmtExiste->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$departamento
            ) {

                responderError(
                    'El departamento solicitado no existe.'
                );
            }


            $nuevoEstado =
                $accion === 'activar';


            $sqlActualizar = "
                UPDATE departamentos
                SET activo = " .
                (
                    $nuevoEstado
                        ? 'TRUE'
                        : 'FALSE'
                ) . "
                WHERE id_departamento = :id
            ";


            $stmtActualizar =
                $conexion->prepare(
                    $sqlActualizar
                );


            $stmtActualizar->execute([
                ':id' =>
                    $idDepartamento
            ]);


            responderJSON([
                'ok' => true,
                'mensaje' =>
                    $nuevoEstado
                        ? 'Departamento activado correctamente.'
                        : 'Departamento desactivado correctamente.'
            ]);
        }


        /* =================================================
           ELIMINAR DEPARTAMENTO

           ORDEN:

           RESERVAS
           UNIFICACIONES
           CONFIGURACIÓN DE HORARIOS
           HORARIOS
           FUNCIONARIOS
           SERVICIOS
           DEPARTAMENTO
           ================================================= */

        if (
            $accion === 'eliminar'
        ) {

            $idDepartamento =
                filter_var(
                    $datos['id_departamento'] ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$idDepartamento ||
                $idDepartamento <= 0
            ) {

                responderError(
                    'El identificador del departamento no es válido.'
                );
            }


            /* ---------------------------------------------
               COMPROBAR EXISTENCIA
               --------------------------------------------- */

            $sqlExiste = "
                SELECT
                    id_departamento,
                    nombre
                FROM departamentos
                WHERE
                    id_departamento = :id
                LIMIT 1
            ";


            $stmtExiste =
                $conexion->prepare(
                    $sqlExiste
                );


            $stmtExiste->execute([
                ':id' =>
                    $idDepartamento
            ]);


            $departamento =
                $stmtExiste->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$departamento
            ) {

                responderError(
                    'El departamento solicitado no existe.'
                );
            }


            /* ---------------------------------------------
               INICIAR TRANSACCIÓN
               --------------------------------------------- */

            $conexion->beginTransaction();


            try {

                /*
                 * 1. RESERVAS
                 *
                 * Se eliminan antes de horarios y servicios.
                 */

                $sqlReservas = "
                    DELETE FROM reservas
                    WHERE
                        id_servicio IN (
                            SELECT id_servicio
                            FROM servicios
                            WHERE id_departamento = :id
                        )
                        OR id_horario IN (
                            SELECT h.id_horario
                            FROM horarios h
                            INNER JOIN servicios s
                                ON s.id_servicio = h.id_servicio
                            WHERE s.id_departamento = :id2
                        )
                ";


                $stmtReservas =
                    $conexion->prepare(
                        $sqlReservas
                    );


                $stmtReservas->execute([
                    ':id' =>
                        $idDepartamento,
                    ':id2' =>
                        $idDepartamento
                ]);


                /*
                 * 2. UNIFICACIONES
                 */

                $sqlUnificaciones = "
                    DELETE FROM unificacion_horarios_servicios
                    WHERE id_servicio IN (
                        SELECT id_servicio
                        FROM servicios
                        WHERE id_departamento = :id
                    )
                ";


                $stmtUnificaciones =
                    $conexion->prepare(
                        $sqlUnificaciones
                    );


                $stmtUnificaciones->execute([
                    ':id' =>
                        $idDepartamento
                ]);


                /*
                 * 3. CONFIGURACIÓN DE HORARIOS
                 *
                 * ESTA ERA LA RELACIÓN QUE ESTABA
                 * PROVOCANDO EL ERROR 409.
                 *
                 * configuracion_horarios.id_servicio
                 * apunta a servicios.id_servicio.
                 */

                $sqlConfiguracionHorarios = "
                    DELETE FROM configuracion_horarios
                    WHERE id_servicio IN (
                        SELECT id_servicio
                        FROM servicios
                        WHERE id_departamento = :id
                    )
                ";


                $stmtConfiguracionHorarios =
                    $conexion->prepare(
                        $sqlConfiguracionHorarios
                    );


                $stmtConfiguracionHorarios->execute([
                    ':id' =>
                        $idDepartamento
                ]);


                /*
                 * 4. HORARIOS
                 */

                $sqlHorarios = "
                    DELETE FROM horarios
                    WHERE id_servicio IN (
                        SELECT id_servicio
                        FROM servicios
                        WHERE id_departamento = :id
                    )
                ";


                $stmtHorarios =
                    $conexion->prepare(
                        $sqlHorarios
                    );


                $stmtHorarios->execute([
                    ':id' =>
                        $idDepartamento
                ]);


                /*
                 * 5. FUNCIONARIOS
                 *
                 * Se eliminan después de horarios.
                 */

                $sqlFuncionarios = "
                    DELETE FROM funcionarios
                    WHERE id_departamento = :id
                ";


                $stmtFuncionarios =
                    $conexion->prepare(
                        $sqlFuncionarios
                    );


                $stmtFuncionarios->execute([
                    ':id' =>
                        $idDepartamento
                ]);


                /*
                 * 6. SERVICIOS
                 */

                $sqlServicios = "
                    DELETE FROM servicios
                    WHERE id_departamento = :id
                ";


                $stmtServicios =
                    $conexion->prepare(
                        $sqlServicios
                    );


                $stmtServicios->execute([
                    ':id' =>
                        $idDepartamento
                ]);


                /*
                 * 7. DEPARTAMENTO
                 */

                $sqlDepartamento = "
                    DELETE FROM departamentos
                    WHERE id_departamento = :id
                ";


                $stmtDepartamento =
                    $conexion->prepare(
                        $sqlDepartamento
                    );


                $stmtDepartamento->execute([
                    ':id' =>
                        $idDepartamento
                ]);


                if (
                    $stmtDepartamento->rowCount() !== 1
                ) {

                    throw new RuntimeException(
                        'No fue posible eliminar el departamento.'
                    );
                }


                /*
                 * 8. CONFIRMAR TODO
                 */

                $conexion->commit();


            } catch (
                Throwable $e
            ) {

                if (
                    $conexion->inTransaction()
                ) {

                    $conexion->rollBack();
                }


                throw $e;
            }


            responderJSON([
                'ok' => true,
                'mensaje' =>
                    'El departamento "' .
                    $departamento['nombre'] .
                    '" y todos sus datos asociados fueron eliminados correctamente.'
            ]);
        }
    }


    /* =====================================================
       SERVICIOS
       ===================================================== */

    if (
        $tipo === 'servicio'
    ) {


        /* =================================================
           CREAR SERVICIO
           ================================================= */

        if (
            $accion === 'crear'
        ) {

            $idDepartamento =
                filter_var(
                    $datos['id_departamento'] ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$idDepartamento ||
                $idDepartamento <= 0
            ) {

                responderError(
                    'Debe seleccionar un departamento válido.'
                );
            }


            $nombre = obtenerTexto(
                $datos['nombre'] ?? ''
            );


            $descripcion = obtenerTexto(
                $datos['descripcion'] ?? ''
            );


            validarNombre(
                $nombre,
                'servicio'
            );


            if (
                mb_strlen($descripcion) > 500
            ) {

                responderError(
                    'La descripción no puede superar los 500 caracteres.'
                );
            }


            $sqlDepartamento = "
                SELECT
                    id_departamento,
                    nombre,
                    activo
                FROM departamentos
                WHERE
                    id_departamento = :id
                LIMIT 1
            ";


            $stmtDepartamento =
                $conexion->prepare(
                    $sqlDepartamento
                );


            $stmtDepartamento->execute([
                ':id' =>
                    $idDepartamento
            ]);


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
                !$departamento['activo']
            ) {

                responderError(
                    'No es posible agregar un servicio a un departamento inactivo.'
                );
            }


            $sqlDuplicado = "
                SELECT id_servicio
                FROM servicios
                WHERE
                    id_departamento = :id_departamento
                    AND LOWER(TRIM(nombre))
                        =
                        LOWER(TRIM(:nombre))
                LIMIT 1
            ";


            $stmtDuplicado =
                $conexion->prepare(
                    $sqlDuplicado
                );


            $stmtDuplicado->execute([
                ':id_departamento' =>
                    $idDepartamento,
                ':nombre' =>
                    $nombre
            ]);


            if (
                $stmtDuplicado->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                responderError(
                    'Ya existe un servicio con ese nombre en este departamento.'
                );
            }


            $sqlInsertar = "
                INSERT INTO servicios (
                    id_departamento,
                    nombre,
                    descripcion,
                    activo
                )
                VALUES (
                    :id_departamento,
                    :nombre,
                    :descripcion,
                    TRUE
                )
                RETURNING
                    id_servicio,
                    id_departamento,
                    nombre,
                    descripcion,
                    activo
            ";


            $stmtInsertar =
                $conexion->prepare(
                    $sqlInsertar
                );


            $stmtInsertar->execute([
                ':id_departamento' =>
                    $idDepartamento,
                ':nombre' =>
                    $nombre,
                ':descripcion' =>
                    $descripcion !== ''
                        ? $descripcion
                        : null
            ]);


            $servicio =
                $stmtInsertar->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$servicio
            ) {

                responderError(
                    'No fue posible crear el servicio.',
                    500
                );
            }


            responderJSON([
                'ok' => true,
                'mensaje' =>
                    'Servicio creado correctamente.',
                'servicio' =>
                    $servicio
            ]);
        }


        /* =================================================
           EDITAR SERVICIO
           ================================================= */

        if (
            $accion === 'editar'
        ) {

            $idServicio =
                filter_var(
                    $datos['id_servicio'] ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$idServicio ||
                $idServicio <= 0
            ) {

                responderError(
                    'El identificador del servicio no es válido.'
                );
            }


            $idDepartamento =
                filter_var(
                    $datos['id_departamento'] ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$idDepartamento ||
                $idDepartamento <= 0
            ) {

                responderError(
                    'Debe seleccionar un departamento válido.'
                );
            }


            $nombre = obtenerTexto(
                $datos['nombre'] ?? ''
            );


            $descripcion = obtenerTexto(
                $datos['descripcion'] ?? ''
            );


            validarNombre(
                $nombre,
                'servicio'
            );


            if (
                mb_strlen($descripcion) > 500
            ) {

                responderError(
                    'La descripción no puede superar los 500 caracteres.'
                );
            }


            $sqlServicio = "
                SELECT
                    id_servicio,
                    id_departamento,
                    nombre,
                    activo
                FROM servicios
                WHERE
                    id_servicio = :id
                LIMIT 1
            ";


            $stmtServicio =
                $conexion->prepare(
                    $sqlServicio
                );


            $stmtServicio->execute([
                ':id' =>
                    $idServicio
            ]);


            $servicioExistente =
                $stmtServicio->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$servicioExistente
            ) {

                responderError(
                    'El servicio solicitado no existe.'
                );
            }


            $sqlDepartamento = "
                SELECT
                    id_departamento,
                    activo
                FROM departamentos
                WHERE
                    id_departamento = :id
                LIMIT 1
            ";


            $stmtDepartamento =
                $conexion->prepare(
                    $sqlDepartamento
                );


            $stmtDepartamento->execute([
                ':id' =>
                    $idDepartamento
            ]);


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
                !$departamento['activo']
            ) {

                responderError(
                    'No es posible asignar el servicio a un departamento inactivo.'
                );
            }


            $sqlDuplicado = "
                SELECT id_servicio
                FROM servicios
                WHERE
                    id_departamento = :id_departamento
                    AND LOWER(TRIM(nombre))
                        =
                        LOWER(TRIM(:nombre))
                    AND id_servicio <> :id_servicio
                LIMIT 1
            ";


            $stmtDuplicado =
                $conexion->prepare(
                    $sqlDuplicado
                );


            $stmtDuplicado->execute([
                ':id_departamento' =>
                    $idDepartamento,
                ':nombre' =>
                    $nombre,
                ':id_servicio' =>
                    $idServicio
            ]);


            if (
                $stmtDuplicado->fetch(
                    PDO::FETCH_ASSOC
                )
            ) {

                responderError(
                    'Ya existe otro servicio con ese nombre en este departamento.'
                );
            }


            $sqlActualizar = "
                UPDATE servicios
                SET
                    id_departamento = :id_departamento,
                    nombre = :nombre,
                    descripcion = :descripcion
                WHERE
                    id_servicio = :id_servicio
            ";


            $stmtActualizar =
                $conexion->prepare(
                    $sqlActualizar
                );


            $stmtActualizar->execute([
                ':id_departamento' =>
                    $idDepartamento,
                ':nombre' =>
                    $nombre,
                ':descripcion' =>
                    $descripcion !== ''
                        ? $descripcion
                        : null,
                ':id_servicio' =>
                    $idServicio
            ]);


            responderJSON([
                'ok' => true,
                'mensaje' =>
                    'Servicio actualizado correctamente.'
            ]);
        }


        /* =================================================
           ACTIVAR / DESACTIVAR SERVICIO
           ================================================= */

        if (
            $accion === 'activar' ||
            $accion === 'desactivar'
        ) {

            $idServicio =
                filter_var(
                    $datos['id_servicio'] ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$idServicio ||
                $idServicio <= 0
            ) {

                responderError(
                    'El identificador del servicio no es válido.'
                );
            }


            $sqlServicio = "
                SELECT
                    s.id_servicio,
                    s.activo,
                    s.id_departamento,
                    d.activo AS departamento_activo
                FROM servicios s
                INNER JOIN departamentos d
                    ON d.id_departamento =
                       s.id_departamento
                WHERE
                    s.id_servicio = :id
                LIMIT 1
            ";


            $stmtServicio =
                $conexion->prepare(
                    $sqlServicio
                );


            $stmtServicio->execute([
                ':id' =>
                    $idServicio
            ]);


            $servicio =
                $stmtServicio->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$servicio
            ) {

                responderError(
                    'El servicio solicitado no existe.'
                );
            }


            $nuevoEstado =
                $accion === 'activar';


            if (
                $nuevoEstado &&
                !$servicio['departamento_activo']
            ) {

                responderError(
                    'No es posible activar un servicio cuyo departamento está inactivo.'
                );
            }


            $sqlActualizar = "
                UPDATE servicios
                SET activo = " .
                (
                    $nuevoEstado
                        ? 'TRUE'
                        : 'FALSE'
                ) . "
                WHERE id_servicio = :id
            ";


            $stmtActualizar =
                $conexion->prepare(
                    $sqlActualizar
                );


            $stmtActualizar->execute([
                ':id' =>
                    $idServicio
            ]);


            responderJSON([
                'ok' => true,
                'mensaje' =>
                    $nuevoEstado
                        ? 'Servicio activado correctamente.'
                        : 'Servicio desactivado correctamente.'
            ]);
        }


        /* =================================================
           ELIMINAR SERVICIO

           RESERVAS
           UNIFICACIONES
           CONFIGURACIÓN DE HORARIOS
           HORARIOS
           SERVICIO
           ================================================= */

        if (
            $accion === 'eliminar'
        ) {

            $idServicio =
                filter_var(
                    $datos['id_servicio'] ?? null,
                    FILTER_VALIDATE_INT
                );


            if (
                !$idServicio ||
                $idServicio <= 0
            ) {

                responderError(
                    'El identificador del servicio no es válido.'
                );
            }


            /* ---------------------------------------------
               COMPROBAR EXISTENCIA
               --------------------------------------------- */

            $sqlServicio = "
                SELECT
                    id_servicio,
                    nombre
                FROM servicios
                WHERE
                    id_servicio = :id
                LIMIT 1
            ";


            $stmtServicio =
                $conexion->prepare(
                    $sqlServicio
                );


            $stmtServicio->execute([
                ':id' =>
                    $idServicio
            ]);


            $servicio =
                $stmtServicio->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$servicio
            ) {

                responderError(
                    'El servicio solicitado no existe.'
                );
            }


            /* ---------------------------------------------
               INICIAR TRANSACCIÓN
               --------------------------------------------- */

            $conexion->beginTransaction();


            try {

                /*
                 * 1. RESERVAS
                 */

                $sqlReservas = "
                    DELETE FROM reservas
                    WHERE
                        id_servicio = :id
                        OR id_horario IN (
                            SELECT id_horario
                            FROM horarios
                            WHERE id_servicio = :id2
                        )
                ";


                $stmtReservas =
                    $conexion->prepare(
                        $sqlReservas
                    );


                $stmtReservas->execute([
                    ':id' =>
                        $idServicio,
                    ':id2' =>
                        $idServicio
                ]);


                /*
                 * 2. UNIFICACIONES
                 */

                $sqlUnificaciones = "
                    DELETE FROM unificacion_horarios_servicios
                    WHERE id_servicio = :id
                ";


                $stmtUnificaciones =
                    $conexion->prepare(
                        $sqlUnificaciones
                    );


                $stmtUnificaciones->execute([
                    ':id' =>
                        $idServicio
                ]);


                /*
                 * 3. CONFIGURACIÓN DE HORARIOS
                 *
                 * Esta eliminación soluciona la FK:
                 *
                 * configuracion_horarios_servicio_fk
                 */

                $sqlConfiguracionHorarios = "
                    DELETE FROM configuracion_horarios
                    WHERE id_servicio = :id
                ";


                $stmtConfiguracionHorarios =
                    $conexion->prepare(
                        $sqlConfiguracionHorarios
                    );


                $stmtConfiguracionHorarios->execute([
                    ':id' =>
                        $idServicio
                ]);


                /*
                 * 4. HORARIOS
                 */

                $sqlHorarios = "
                    DELETE FROM horarios
                    WHERE id_servicio = :id
                ";


                $stmtHorarios =
                    $conexion->prepare(
                        $sqlHorarios
                    );


                $stmtHorarios->execute([
                    ':id' =>
                        $idServicio
                ]);


                /*
                 * 5. SERVICIO
                 */

                $sqlEliminar = "
                    DELETE FROM servicios
                    WHERE id_servicio = :id
                ";


                $stmtEliminar =
                    $conexion->prepare(
                        $sqlEliminar
                    );


                $stmtEliminar->execute([
                    ':id' =>
                        $idServicio
                ]);


                if (
                    $stmtEliminar->rowCount() !== 1
                ) {

                    throw new RuntimeException(
                        'No fue posible eliminar el servicio.'
                    );
                }


                /*
                 * 6. CONFIRMAR TODO
                 */

                $conexion->commit();


            } catch (
                Throwable $e
            ) {

                if (
                    $conexion->inTransaction()
                ) {

                    $conexion->rollBack();
                }


                throw $e;
            }


            responderJSON([
                'ok' => true,
                'mensaje' =>
                    'El servicio "' .
                    $servicio['nombre'] .
                    '" y todos sus datos asociados fueron eliminados correctamente.'
            ]);
        }
    }


    /* =====================================================
       RESPUESTA POR DEFECTO
       ===================================================== */

    responderError(
        'No se pudo determinar la operación solicitada.'
    );


} catch (
    PDOException $e
) {

    if (
        $conexion->inTransaction()
    ) {

        $conexion->rollBack();
    }


    error_log(
        'Error PDO en api/gestionar_estructura.php: ' .
        $e->getMessage()
    );


    responderError(
        'PostgreSQL no permite realizar la operación: ' .
        $e->getMessage(),
        409
    );


} catch (
    Throwable $e
) {

    if (
        $conexion->inTransaction()
    ) {

        $conexion->rollBack();
    }


    error_log(
        'Error general en api/gestionar_estructura.php: ' .
        $e->getMessage()
    );


    responderError(
        'Ocurrió un error inesperado al procesar la solicitud: ' .
        $e->getMessage(),
        500
    );
}