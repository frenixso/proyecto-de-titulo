<?php

/* =========================================================
   API: OBTENER CONFIGURACIÓN DE HORARIOS PARA AGENDA

   Recibe:
   - servicio
   - fecha

   Consulta:
   - configuracion_horarios

   Devuelve:
   - hora_inicio
   - hora_fin
   - intervalo_minutos
   - tiene_almuerzo
   - almuerzo_inicio
   - almuerzo_fin

   Zona horaria: America/Santiago
========================================================= */

header('Content-Type: application/json; charset=utf-8');

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';

date_default_timezone_set('America/Santiago');


/* =========================================================
   RESPUESTA JSON
========================================================= */

function responderConfiguracionAgenda(
    bool $ok,
    string $mensaje = '',
    array $datos = [],
    int $codigo = 200
): void {

    http_response_code($codigo);

    echo json_encode(
        array_merge(
            [
                'ok' => $ok,
                'mensaje' => $mensaje
            ],
            $datos
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/* =========================================================
   VALIDAR MÉTODO
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    responderConfiguracionAgenda(
        false,
        'Método de solicitud no permitido.',
        [],
        405
    );

}


/* =========================================================
   OBTENER DATOS DE SESIÓN
========================================================= */

$rolFuncionario =
    $_SESSION['rol_funcionario'] ?? 'funcionario';

$usuarioFuncionario =
    $_SESSION['usuario_funcionario'] ?? '';


/* =========================================================
   OBTENER PARÁMETROS
========================================================= */

$idServicio =
    $_GET['servicio'] ?? '';

$fechaRecibida =
    $_GET['fecha'] ?? '';


/* =========================================================
   VALIDAR SERVICIO
========================================================= */

if (
    !is_string($idServicio) ||
    !ctype_digit($idServicio) ||
    (int) $idServicio <= 0
) {

    responderConfiguracionAgenda(
        false,
        'El servicio seleccionado no es válido.',
        [],
        400
    );

}

$idServicio = (int) $idServicio;


/* =========================================================
   VALIDAR FECHA
========================================================= */

if (
    !is_string($fechaRecibida) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaRecibida)
) {

    responderConfiguracionAgenda(
        false,
        'La fecha seleccionada no es válida.',
        [],
        400
    );

}

$zonaChile = new DateTimeZone('America/Santiago');

$fechaObjeto = DateTime::createFromFormat(
    '!Y-m-d',
    $fechaRecibida,
    $zonaChile
);

if (
    !$fechaObjeto ||
    $fechaObjeto->format('Y-m-d') !== $fechaRecibida
) {

    responderConfiguracionAgenda(
        false,
        'La fecha seleccionada no existe.',
        [],
        400
    );

}


/* =========================================================
   VALIDAR QUE LA FECHA NO SEA ANTERIOR A HOY
========================================================= */

$fechaHoy = new DateTime('today', $zonaChile);

if ($fechaObjeto < $fechaHoy) {

    responderConfiguracionAgenda(
        false,
        'No se pueden generar agendas para fechas anteriores a hoy.',
        [],
        400
    );

}


/* =========================================================
   OBTENER DÍA DE LA SEMANA

   PostgreSQL:
   1 = lunes
   2 = martes
   3 = miércoles
   4 = jueves
   5 = viernes
   6 = sábado
   7 = domingo
========================================================= */

$diaSemana = (int) $fechaObjeto->format('N');


/* =========================================================
   CONSULTAR SERVICIO Y DEPARTAMENTO
========================================================= */

try {

    $sqlServicio = "
        SELECT
            s.id_servicio,
            s.nombre AS nombre_servicio,
            s.id_departamento,
            d.nombre AS nombre_departamento

        FROM servicios s

        INNER JOIN departamentos d
            ON d.id_departamento = s.id_departamento

        WHERE s.id_servicio = :id_servicio
          AND s.activo = TRUE
          AND d.activo = TRUE

        LIMIT 1
    ";

    $stmtServicio = $conexion->prepare($sqlServicio);

    $stmtServicio->execute([
        ':id_servicio' => $idServicio
    ]);

    $servicio = $stmtServicio->fetch(PDO::FETCH_ASSOC);

    if (!$servicio) {

        responderConfiguracionAgenda(
            false,
            'El servicio no existe o se encuentra deshabilitado.',
            [],
            404
        );

    }


    /* =====================================================
       VALIDAR DEPARTAMENTO DEL FUNCIONARIO

       El superadmin puede consultar cualquier departamento.
       Administradores y funcionarios solamente el suyo.
    ===================================================== */

    if (
        $rolFuncionario === 'administrador' ||
        $rolFuncionario === 'funcionario'
    ) {

        if ($usuarioFuncionario === '') {

            responderConfiguracionAgenda(
                false,
                'No fue posible identificar al funcionario autenticado.',
                [],
                403
            );

        }

        $sqlDepartamento = "
            SELECT
                id_departamento

            FROM funcionarios

            WHERE usuario = :usuario

            LIMIT 1
        ";

        $stmtDepartamento =
            $conexion->prepare($sqlDepartamento);

        $stmtDepartamento->execute([
            ':usuario' => $usuarioFuncionario
        ]);

        $idDepartamentoFuncionario =
            $stmtDepartamento->fetchColumn();

        if (
            !$idDepartamentoFuncionario ||
            (int) $idDepartamentoFuncionario !==
            (int) $servicio['id_departamento']
        ) {

            responderConfiguracionAgenda(
                false,
                'No tienes permiso para consultar la configuración de este servicio.',
                [],
                403
            );

        }

    } elseif ($rolFuncionario !== 'superadmin') {

        responderConfiguracionAgenda(
            false,
            'No tienes permisos para consultar esta configuración.',
            [],
            403
        );

    }


    /* =====================================================
       CONSULTAR CONFIGURACIONES DEL SERVICIO

       Se permiten varias configuraciones registradas
       para el mismo día. El frontend actual requiere
       una sola configuración habitual por servicio y día.
    ===================================================== */

    $sqlConfiguraciones = "

        SELECT

            id_configuracion,

            TO_CHAR(
                hora_inicio,
                'HH24:MI:SS'
            ) AS hora_inicio,

            TO_CHAR(
                hora_fin,
                'HH24:MI:SS'
            ) AS hora_fin,

            intervalo_minutos,

            COALESCE(
                tiene_almuerzo,
                FALSE
            ) AS tiene_almuerzo,

            CASE

                WHEN almuerzo_inicio IS NOT NULL THEN

                    TO_CHAR(
                        almuerzo_inicio,
                        'HH24:MI:SS'
                    )

                ELSE NULL

            END AS almuerzo_inicio,

            CASE

                WHEN almuerzo_fin IS NOT NULL THEN

                    TO_CHAR(
                        almuerzo_fin,
                        'HH24:MI:SS'
                    )

                ELSE NULL

            END AS almuerzo_fin

        FROM configuracion_horarios

        WHERE id_servicio = :id_servicio
          AND dia_semana = :dia_semana
          AND activo = TRUE

        ORDER BY hora_inicio ASC

    ";

    $stmtConfiguraciones =
        $conexion->prepare($sqlConfiguraciones);

    $stmtConfiguraciones->execute([
        ':id_servicio' => $idServicio,
        ':dia_semana' => $diaSemana
    ]);

    $configuraciones =
        $stmtConfiguraciones->fetchAll(PDO::FETCH_ASSOC);


    /* =====================================================
       PREPARAR CONFIGURACIONES
    ===================================================== */

    foreach ($configuraciones as &$configuracion) {

        $configuracion['id_configuracion'] =
            (int) $configuracion['id_configuracion'];

        $configuracion['intervalo_minutos'] =
            (int) $configuracion['intervalo_minutos'];

        $configuracion['tiene_almuerzo'] =
            filter_var(
                $configuracion['tiene_almuerzo'],
                FILTER_VALIDATE_BOOLEAN
            );

        if (
            $configuracion['intervalo_minutos'] <= 0
        ) {

            throw new RuntimeException(
                'La configuración contiene un intervalo inválido.'
            );

        }

        if (
            $configuracion['tiene_almuerzo'] &&
            (
                empty($configuracion['almuerzo_inicio']) ||
                empty($configuracion['almuerzo_fin'])
            )
        ) {

            throw new RuntimeException(
                'La configuración de almuerzo está incompleta.'
            );

        }

    }

    unset($configuracion);


    /* =====================================================
       RESPUESTA
    ===================================================== */

    responderConfiguracionAgenda(
        true,
        count($configuraciones) > 0
            ? 'Configuración de horarios consultada correctamente.'
            : 'No existe una configuración habitual para este servicio y día.',
        [
            'servicio' => [
                'id_servicio' =>
                    (int) $servicio['id_servicio'],

                'nombre' =>
                    $servicio['nombre_servicio'],

                'id_departamento' =>
                    (int) $servicio['id_departamento'],

                'departamento' =>
                    $servicio['nombre_departamento']
            ],

            'fecha' =>
                $fechaRecibida,

            'dia_semana' =>
                $diaSemana,

            'configuraciones' =>
                $configuraciones
        ]
    );


} catch (Throwable $e) {

    error_log(
        'Error obtener_configuracion_horarios_agenda.php: ' .
        $e->getMessage()
    );

    responderConfiguracionAgenda(
        false,
        'Ocurrió un error al consultar la configuración de horarios.',
        [],
        500
    );

}
