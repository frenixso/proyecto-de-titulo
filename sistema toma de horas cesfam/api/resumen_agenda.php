<?php

// =========================================================
// API - RESUMEN DE AGENDA
// Obtiene las reservas confirmadas de un día,
// servicio y/o departamento.
// =========================================================

header(
    'Content-Type: application/json; charset=utf-8'
);

require_once __DIR__ . '/../config/conexion.php';


if (
    session_status() !==
    PHP_SESSION_ACTIVE
) {

    session_start();

}


// =========================================================
// FUNCIÓN RESPUESTA
// =========================================================

function responder(
    array $datos,
    int $codigo = 200
) {

    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;

}


try {

    // =====================================================
    // 1. RECIBIR DATOS
    // =====================================================

    /*
     * El servicio es opcional.
     *
     * Si viene vacío:
     *
     *      → se muestran todas las reservas del día.
     *
     * Si viene informado:
     *
     *      → se muestran solamente las reservas
     *        de ese servicio.
     */

    $servicio =
        trim(
            $_GET['servicio'] ?? ''
        );


    /*
     * Departamento también es opcional.
     *
     * Se utilizará principalmente para el
     * filtro administrativo del superadmin.
     */

    $departamento =
        trim(
            $_GET['departamento'] ?? ''
        );


    $fecha =
        trim(
            $_GET['fecha'] ?? ''
        );


    // =====================================================
    // 2. VALIDAR SERVICIO SI FUE ENVIADO
    // =====================================================

    $idServicio = null;


    if ($servicio !== '') {

        if (
            !ctype_digit(
                $servicio
            )
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El servicio seleccionado no es válido.'
                ],
                400
            );

        }


        $idServicio =
            (int) $servicio;


        if (
            $idServicio <= 0
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El servicio seleccionado no es válido.'
                ],
                400
            );

        }

    }


    // =====================================================
    // 3. VALIDAR DEPARTAMENTO SI FUE ENVIADO
    // =====================================================

    $idDepartamento = null;


    if ($departamento !== '') {

        if (
            !ctype_digit(
                $departamento
            )
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El departamento seleccionado no es válido.'
                ],
                400
            );

        }


        $idDepartamento =
            (int) $departamento;


        if (
            $idDepartamento <= 0
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El departamento seleccionado no es válido.'
                ],
                400
            );

        }

    }


    // =====================================================
    // 4. VALIDAR FECHA
    // =====================================================

    if ($fecha === '') {

        responder(
            [
                'ok' => false,
                'error' =>
                    'La fecha es obligatoria.'
            ],
            400
        );

    }


    $fechaValida =
        DateTime::createFromFormat(
            'Y-m-d',
            $fecha
        );


    if (
        !$fechaValida ||
        $fechaValida->format('Y-m-d') !==
        $fecha
    ) {

        responder(
            [
                'ok' => false,
                'error' =>
                    'La fecha seleccionada no es válida.'
            ],
            400
        );

    }


    // =====================================================
    // 5. VALIDAR DEPARTAMENTO DEL FUNCIONARIO
    // =====================================================

    /*
     * Los funcionarios y administradores solamente
     * pueden consultar reservas de su departamento.
     *
     * El superadmin mantiene acceso global.
     */

    $rolFuncionario =
        strtolower(
            trim(
                $_SESSION['rol_funcionario'] ?? ''
            )
        );


    $usuarioFuncionario =
        trim(
            $_SESSION['usuario_funcionario'] ?? ''
        );


    if (
        $rolFuncionario !== 'superadmin'
    ) {

        if (
            $usuarioFuncionario === ''
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'No se pudo identificar al funcionario.'
                ],
                401
            );

        }


        $sqlFuncionario = "

            SELECT
                f.id_departamento

            FROM funcionarios f

            WHERE f.usuario =
                :usuario

            LIMIT 1

        ";


        $stmtFuncionario =
            $conexion->prepare(
                $sqlFuncionario
            );


        $stmtFuncionario->execute(
            [
                ':usuario' =>
                    $usuarioFuncionario
            ]
        );


        $funcionarioEncontrado =
            $stmtFuncionario->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$funcionarioEncontrado
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El funcionario no existe o no tiene un departamento asignado.'
                ],
                403
            );

        }


        if (
            $funcionarioEncontrado[
                'id_departamento'
            ] === null
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El funcionario no tiene un departamento asignado.'
                ],
                403
            );

        }


        /*
         * El departamento enviado por JavaScript
         * se ignora para funcionarios y administradores.
         *
         * Se utiliza siempre el departamento registrado
         * en la tabla funcionarios.
         */

        $idDepartamento =
            (int)
            $funcionarioEncontrado[
                'id_departamento'
            ];

    }


    // =====================================================
    // 6. VALIDAR SERVICIO SI FUE SELECCIONADO
    // =====================================================

    $servicioEncontrado =
        null;


    if (
        $idServicio !== null
    ) {

        $sqlServicio = "

            SELECT
                s.id_servicio,
                s.nombre,
                s.id_departamento,
                d.nombre AS departamento

            FROM servicios s

            LEFT JOIN departamentos d
                ON d.id_departamento =
                    s.id_departamento

            WHERE s.id_servicio =
                :id_servicio

              AND s.activo = TRUE

            LIMIT 1

        ";


        $stmtServicio =
            $conexion->prepare(
                $sqlServicio
            );


        $stmtServicio->execute(
            [
                ':id_servicio' =>
                    $idServicio
            ]
        );


        $servicioEncontrado =
            $stmtServicio->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$servicioEncontrado
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El servicio seleccionado no existe o está inactivo.'
                ],
                404
            );

        }


        /*
         * Si además se recibió un departamento,
         * comprobar que el servicio pertenece
         * a ese departamento.
         */

        if (
            $idDepartamento !== null &&
            (int)
            $servicioEncontrado[
                'id_departamento'
            ] !==
            $idDepartamento
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El servicio seleccionado no pertenece al departamento indicado.'
                ],
                400
            );

        }

    }


    // =====================================================
    // 7. VALIDAR DEPARTAMENTO SI FUE SELECCIONADO
    // =====================================================

    $departamentoEncontrado =
        null;


    if (
        $idDepartamento !== null
    ) {

        $sqlDepartamento = "

            SELECT
                id_departamento,
                nombre

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


        $departamentoEncontrado =
            $stmtDepartamento->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$departamentoEncontrado
        ) {

            responder(
                [
                    'ok' => false,
                    'error' =>
                        'El departamento seleccionado no existe.'
                ],
                404
            );

        }

    }


    // =====================================================
    // 8. BUSCAR RESERVAS DEL DÍA
    //
    // La consulta permite tres escenarios:
    //
    // 1. Sin servicio y sin departamento:
    //    → todas las reservas del día.
    //
    // 2. Con departamento y sin servicio:
    //    → todas las reservas del departamento.
    //
    // 3. Con servicio:
    //    → reservas solamente de ese servicio.
    // =====================================================

    $sqlReservas = "

        SELECT

            r.id_reserva,

            r.nombre,

            r.rut,

            r.telefono,

            r.correo,

            r.tipo_identificacion,

            r.estado,

            h.id_horario,

            h.fecha,

            h.hora,

            s.id_servicio,

            s.nombre AS servicio,

            s.id_departamento,

            d.nombre AS departamento

        FROM reservas r

        INNER JOIN horarios h
            ON h.id_horario =
                r.id_horario

        INNER JOIN servicios s
            ON s.id_servicio =
                h.id_servicio

        LEFT JOIN departamentos d
            ON d.id_departamento =
                s.id_departamento

        WHERE h.fecha =
            :fecha

          AND r.estado =
            'confirmada'

          AND s.activo = TRUE

    ";


    $parametros =
        [
            ':fecha' =>
                $fecha
        ];


    // =====================================================
    // FILTRO POR SERVICIO
    // =====================================================

    if (
        $idServicio !== null
    ) {

        $sqlReservas .= "

          AND h.id_servicio =
            :id_servicio

        ";


        $parametros[
            ':id_servicio'
        ] =
            $idServicio;

    }


    // =====================================================
    // FILTRO POR DEPARTAMENTO
    // =====================================================

    if (
        $idDepartamento !== null
    ) {

        $sqlReservas .= "

          AND s.id_departamento =
            :id_departamento

        ";


        $parametros[
            ':id_departamento'
        ] =
            $idDepartamento;

    }


    $sqlReservas .= "

        ORDER BY
            h.hora ASC,
            s.nombre ASC

    ";


    $stmtReservas =
        $conexion->prepare(
            $sqlReservas
        );


    $stmtReservas->execute(
        $parametros
    );


    $reservasDB =
        $stmtReservas->fetchAll(
            PDO::FETCH_ASSOC
        );


    // =====================================================
    // 9. PREPARAR RESERVAS PARA JAVASCRIPT
    // =====================================================

    $reservas = [];


    foreach (
        $reservasDB
        as $reserva
    ) {

        /*
         * PostgreSQL puede devolver TIME como:
         *
         * 09:00:00
         *
         * El modal solamente necesita:
         *
         * 09:00
         */

        $hora =
            isset(
                $reserva['hora']
            )
                ? substr(
                    (string)
                    $reserva['hora'],
                    0,
                    5
                )
                : '';


        $reservas[] = [

            'id_reserva' =>
                (int)
                $reserva['id_reserva'],

            'id_horario' =>
                (int)
                $reserva['id_horario'],

            'nombre' =>
                $reserva['nombre'],

            'rut' =>
                $reserva['rut'],

            'telefono' =>
                $reserva['telefono'],

            'correo' =>
                $reserva['correo'],

            'tipo_identificacion' =>
                $reserva[
                    'tipo_identificacion'
                ],

            'estado' =>
                $reserva['estado'],

            'fecha' =>
                $reserva['fecha'],

            'hora' =>
                $hora,

            /*
             * IMPORTANTE:
             *
             * Cada reserva devuelve su propio
             * servicio.
             *
             * Esto permite que el resumen general
             * muestre reservas de distintos servicios.
             */

            'servicio' =>
                $reserva['servicio'],

            'id_servicio' =>
                (int)
                $reserva['id_servicio'],

            'departamento' =>
                $reserva['departamento'],

            'id_departamento' =>
                $reserva['id_departamento'] !== null
                    ? (int)
                        $reserva['id_departamento']
                    : null,

            /*
             * Actualmente observación no está
             * almacenada en reservas.
             *
             * Se mantiene para que agenda.js
             * pueda seguir funcionando sin errores.
             */

            'observacion' =>
                null

        ];

    }


    // =====================================================
    // 10. DETERMINAR TEXTO DEL SERVICIO
    // =====================================================

    if (
        $servicioEncontrado
    ) {

        $nombreServicio =
            $servicioEncontrado['nombre'];

    } elseif (
        $departamentoEncontrado
    ) {

        $nombreServicio =
            'Todos los servicios de ' .
            $departamentoEncontrado['nombre'];

    } else {

        $nombreServicio =
            'Todos los servicios';

    }


    // =====================================================
    // 11. RESPUESTA
    // =====================================================

    responder(
        [

            'ok' =>
                true,

            'fecha' =>
                $fecha,

            'servicio' =>
                $nombreServicio,

            'id_servicio' =>
                $idServicio,

            'departamento' =>
                $departamentoEncontrado
                    ? $departamentoEncontrado['nombre']
                    : null,

            'id_departamento' =>
                $idDepartamento,

            'total_reservas' =>
                count(
                    $reservas
                ),

            'reservas' =>
                $reservas,

            'mensaje' =>
                empty($reservas)
                    ? 'No hay reservas confirmadas para este día.'
                    : 'Reservas encontradas.'

        ]
    );


} catch (PDOException $e) {

    error_log(
        'Error en resumen_agenda.php: ' .
        $e->getMessage()
    );


    responder(
        [
            'ok' => false,
            'error' =>
                'Error al consultar las reservas del día.'
        ],
        500
    );


} catch (Throwable $e) {

    error_log(
        'Error inesperado en resumen_agenda.php: ' .
        $e->getMessage()
    );


    responder(
        [
            'ok' => false,
            'error' =>
                'Ocurrió un error inesperado.'
        ],
        500
    );

}

?>
