<?php

/*
 * =========================================================
 * API - RESUMEN DE AGENDA
 * CESFAM EDUARDO RAGGIO CATEMU
 * =========================================================
 *
 * Permite consultar reservas confirmadas por:
 * - Fecha.
 * - Servicio.
 * - Departamento.
 * - Profesional.
 *
 * PERMISOS:
 * - Superadmin: acceso completo.
 * - Administrador: acceso completo a reservas.
 * - Administrador web: sin acceso.
 *
 * IMPORTANTE:
 * Las cuentas administrativas no necesitan
 * tener un departamento asignado.
 *
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');


/* =========================================================
   1. AUTENTICACIÓN
========================================================= */

require_once __DIR__ . '/../componentes/auth_funcionario.php';


/* =========================================================
   2. RESPUESTA JSON
========================================================= */

function responderResumenAgenda(
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


/* =========================================================
   3. VALIDAR PERMISOS
========================================================= */

/*
 * Los únicos perfiles autorizados para consultar
 * las reservas desde esta API son:
 *
 * - superadmin
 * - administrador
 *
 * El administrador web utiliza el rol funcionario
 * y no puede consultar las agendas.
 */

$rolFuncionario = strtolower(
    trim(
        (string) ($_SESSION['rol_funcionario'] ?? '')
    )
);


if (
    !in_array(
        $rolFuncionario,
        ['superadmin', 'administrador'],
        true
    )
) {

    responderResumenAgenda(
        [
            'ok' => false,
            'error' => 'No tienes permisos para consultar las reservas.'
        ],
        403
    );
}


/* =========================================================
   4. PROCESAMIENTO
========================================================= */

try {


    /* =====================================================
       4.1 VALIDAR MÉTODO HTTP
    ===================================================== */

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
    ) {

        responderResumenAgenda(
            [
                'ok' => false,
                'error' => 'Método no permitido.'
            ],
            405
        );
    }


    /* =====================================================
       4.2 RECIBIR PARÁMETROS
    ===================================================== */

    $servicio = trim(
        (string) ($_GET['servicio'] ?? '')
    );

    $departamento = trim(
        (string) ($_GET['departamento'] ?? '')
    );

    $profesional = trim(
        (string) ($_GET['profesional'] ?? '')
    );

    $fecha = trim(
        (string) ($_GET['fecha'] ?? '')
    );


    /* =====================================================
       4.3 VALIDAR SERVICIO
    ===================================================== */

    $idServicio = null;

    if ($servicio !== '') {

        if (
            !ctype_digit($servicio) ||
            (int) $servicio <= 0
        ) {

            responderResumenAgenda(
                [
                    'ok' => false,
                    'error' => 'El servicio seleccionado no es válido.'
                ],
                400
            );
        }

        $idServicio = (int) $servicio;
    }


    /* =====================================================
       4.4 VALIDAR DEPARTAMENTO
    ===================================================== */

    $idDepartamento = null;

    if ($departamento !== '') {

        if (
            !ctype_digit($departamento) ||
            (int) $departamento <= 0
        ) {

            responderResumenAgenda(
                [
                    'ok' => false,
                    'error' => 'El departamento seleccionado no es válido.'
                ],
                400
            );
        }

        $idDepartamento = (int) $departamento;
    }


    /* =====================================================
       4.5 VALIDAR PROFESIONAL
    ===================================================== */

    $idProfesional = null;

    if ($profesional !== '') {

        if (
            !ctype_digit($profesional) ||
            (int) $profesional <= 0
        ) {

            responderResumenAgenda(
                [
                    'ok' => false,
                    'error' => 'El profesional seleccionado no es válido.'
                ],
                400
            );
        }

        $idProfesional = (int) $profesional;
    }


    /* =====================================================
       4.6 VALIDAR FECHA
    ===================================================== */

    if ($fecha === '') {

        responderResumenAgenda(
            [
                'ok' => false,
                'error' => 'La fecha es obligatoria.'
            ],
            400
        );
    }


    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $fecha
        )
    ) {

        responderResumenAgenda(
            [
                'ok' => false,
                'error' => 'La fecha seleccionada no es válida.'
            ],
            400
        );
    }


    $fechaValida = DateTime::createFromFormat(
        '!Y-m-d',
        $fecha,
        new DateTimeZone('America/Santiago')
    );

    $erroresFecha = DateTime::getLastErrors();


    if (
        !$fechaValida ||
        $fechaValida->format('Y-m-d') !== $fecha ||
        (
            $erroresFecha !== false &&
            (
                $erroresFecha['warning_count'] > 0 ||
                $erroresFecha['error_count'] > 0
            )
        )
    ) {

        responderResumenAgenda(
            [
                'ok' => false,
                'error' => 'La fecha seleccionada no es válida.'
            ],
            400
        );
    }


    /* =====================================================
       5. VALIDAR SERVICIO
    ===================================================== */

    $servicioEncontrado = null;

    if ($idServicio !== null) {

        $sqlServicio = "

            SELECT
                s.id_servicio,
                s.nombre,
                s.id_departamento,
                d.nombre AS departamento,
                s.activo AS servicio_activo

            FROM servicios s

            LEFT JOIN departamentos d
                ON d.id_departamento =
                    s.id_departamento

            WHERE s.id_servicio = :id_servicio

            LIMIT 1

        ";


        $stmtServicio = $conexion->prepare(
            $sqlServicio
        );

        $stmtServicio->execute([
            ':id_servicio' => $idServicio
        ]);

        $servicioEncontrado = $stmtServicio->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$servicioEncontrado) {

            responderResumenAgenda(
                [
                    'ok' => false,
                    'error' => 'El servicio seleccionado no existe.'
                ],
                404
            );
        }


        if (
            !in_array(
                $servicioEncontrado['servicio_activo'],
                [true, 1, '1', 't', 'true'],
                true
            )
        ) {

            responderResumenAgenda(
                [
                    'ok' => false,
                    'error' => 'El servicio seleccionado está inactivo.'
                ],
                400
            );
        }


        /*
         * Si se seleccionó un departamento,
         * comprobar que el servicio pertenece a él.
         */

        if (
            $idDepartamento !== null &&
            (int) $servicioEncontrado['id_departamento']
                !== $idDepartamento
        ) {

            responderResumenAgenda(
                [
                    'ok' => false,
                    'error' => 'El servicio seleccionado no pertenece al departamento indicado.'
                ],
                400
            );
        }
    }


    /* =====================================================
       6. VALIDAR DEPARTAMENTO
    ===================================================== */

    $departamentoEncontrado = null;

    if ($idDepartamento !== null) {

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


        $stmtDepartamento = $conexion->prepare(
            $sqlDepartamento
        );

        $stmtDepartamento->execute([
            ':id_departamento' => $idDepartamento
        ]);

        $departamentoEncontrado =
            $stmtDepartamento->fetch(PDO::FETCH_ASSOC);


        if (!$departamentoEncontrado) {

            responderResumenAgenda(
                [
                    'ok' => false,
                    'error' => 'El departamento seleccionado no existe.'
                ],
                404
            );
        }
    }


    /* =====================================================
       7. CONSULTAR RESERVAS
    ===================================================== */

    /*
     * IMPORTANTE:
     *
     * Ya no se obtiene id_departamento desde funcionarios.
     *
     * Las cuentas administrativas pueden consultar
     * todos los departamentos.
     *
     * Cuando se selecciona un filtro, este se aplica
     * exclusivamente a la consulta correspondiente.
     */

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

            h.id_agenda,

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


        WHERE h.fecha = :fecha

          AND r.estado = 'confirmada'

          AND s.activo = TRUE

    ";


    $parametros = [
        ':fecha' => $fecha
    ];


    /* =====================================================
       7.1 FILTRAR POR SERVICIO
    ===================================================== */

    if ($idServicio !== null) {

        $sqlReservas .= "

            AND h.id_servicio = :id_servicio

        ";

        $parametros[':id_servicio'] =
            $idServicio;
    }


    /* =====================================================
       7.2 FILTRAR POR DEPARTAMENTO
    ===================================================== */

    if ($idDepartamento !== null) {

        $sqlReservas .= "

            AND s.id_departamento = :id_departamento

        ";

        $parametros[':id_departamento'] =
            $idDepartamento;
    }


    /* =====================================================
       7.3 FILTRAR POR PROFESIONAL
    ===================================================== */

    if ($idProfesional !== null) {

        $sqlReservas .= "

            AND EXISTS (

                SELECT 1

                FROM agendas a

                WHERE a.id_agenda = h.id_agenda

                  AND a.id_profesional = :id_profesional

            )

        ";

        $parametros[':id_profesional'] =
            $idProfesional;
    }


    /* =====================================================
       7.4 ORDENAR
    ===================================================== */

    $sqlReservas .= "

        ORDER BY
            h.hora ASC,
            s.nombre ASC

    ";


    $stmtReservas = $conexion->prepare(
        $sqlReservas
    );

    $stmtReservas->execute(
        $parametros
    );

    $reservasDB = $stmtReservas->fetchAll(
        PDO::FETCH_ASSOC
    );


    /* =====================================================
       8. PREPARAR RESERVAS PARA JAVASCRIPT
    ===================================================== */

    $reservas = [];

    foreach ($reservasDB as $reserva) {

        /*
         * PostgreSQL puede devolver TIME como 09:00:00.
         * El modal necesita 09:00.
         */

        $hora = isset($reserva['hora'])
            ? substr(
                (string) $reserva['hora'],
                0,
                5
            )
            : '';


        $reservas[] = [

            'id_reserva' =>
                (int) $reserva['id_reserva'],

            'id_horario' =>
                (int) $reserva['id_horario'],

            'nombre' =>
                $reserva['nombre'],

            'rut' =>
                $reserva['rut'],

            'telefono' =>
                $reserva['telefono'],

            'correo' =>
                $reserva['correo'],

            'tipo_identificacion' =>
                $reserva['tipo_identificacion'],

            'estado' =>
                $reserva['estado'],

            'fecha' =>
                $reserva['fecha'],

            'hora' =>
                $hora,

            'servicio' =>
                $reserva['servicio'],

            'id_servicio' =>
                (int) $reserva['id_servicio'],

            'departamento' =>
                $reserva['departamento'],

            'id_departamento' =>
                $reserva['id_departamento'] !== null
                    ? (int) $reserva['id_departamento']
                    : null,

            /*
             * Actualmente observación no se almacena
             * en la tabla reservas.
             */

            'observacion' =>
                null

        ];
    }


    /* =====================================================
       9. DETERMINAR TEXTO DEL FILTRO
    ===================================================== */

    if ($servicioEncontrado !== null) {

        $nombreServicio =
            $servicioEncontrado['nombre'];

    } elseif ($departamentoEncontrado !== null) {

        $nombreServicio =
            'Todos los servicios de ' .
            $departamentoEncontrado['nombre'];

    } else {

        $nombreServicio =
            'Todos los servicios';
    }


    /*
     * Determinar departamento de respuesta.
     *
     * Si hay un servicio seleccionado, se devuelve
     * su departamento incluso cuando no se haya
     * aplicado un filtro departamental.
     */

    $nombreDepartamentoRespuesta = null;

    $idDepartamentoRespuesta = null;


    if ($departamentoEncontrado !== null) {

        $nombreDepartamentoRespuesta =
            $departamentoEncontrado['nombre'];

        $idDepartamentoRespuesta =
            (int) $departamentoEncontrado['id_departamento'];

    } elseif ($servicioEncontrado !== null) {

        $nombreDepartamentoRespuesta =
            $servicioEncontrado['departamento'];

        $idDepartamentoRespuesta =
            $servicioEncontrado['id_departamento'] !== null
                ? (int) $servicioEncontrado['id_departamento']
                : null;
    }


    /* =====================================================
       10. RESPUESTA FINAL
    ===================================================== */

    responderResumenAgenda(

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
                $nombreDepartamentoRespuesta,

            'id_departamento' =>
                $idDepartamentoRespuesta,

            'id_profesional' =>
                $idProfesional,

            'total_reservas' =>
                count($reservas),

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

    responderResumenAgenda(
        [
            'ok' => false,
            'error' => 'Error al consultar las reservas del día.'
        ],
        500
    );


} catch (Throwable $e) {

    error_log(
        'Error inesperado en resumen_agenda.php: ' .
        $e->getMessage()
    );

    responderResumenAgenda(
        [
            'ok' => false,
            'error' => 'Ocurrió un error inesperado.'
        ],
        500
    );

}

?>
