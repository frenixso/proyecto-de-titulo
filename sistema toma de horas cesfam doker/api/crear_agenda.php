<?php

/* =========================================================
   API: CREAR AGENDA Y GENERAR CUPOS

   Tablas utilizadas:
   - agendas
   - horarios
   - servicios
   - departamentos
   - profesionales
   - funcionarios

   IMPORTANTE:
   - La agenda es la única fuente de creación de cupos.
   - No se consulta configuracion_horarios.
   - No se generan horarios automáticos.
   - Es obligatorio activar Inicio diferenciado.
   - El servidor calcula nuevamente todos los cupos.

   Zona horaria: America/Santiago
========================================================= */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../componentes/auth_funcionario.php';
require_once __DIR__ . '/../config/conexion.php';

date_default_timezone_set('America/Santiago');


/* =========================================================
   RESPUESTA JSON
========================================================= */

function responderCrearAgenda(
    bool $ok,
    string $mensaje,
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
   CONVERTIR BOOLEANOS
========================================================= */

function crearAgendaBooleano($valor): bool
{
    return in_array(
        $valor,
        [true, 1, '1', 'true', 't', 'on'],
        true
    );
}


/* =========================================================
   CONVERTIR BOOLEANOS A TEXTO SQL

   Evita enviar cadenas vacías a PostgreSQL.
========================================================= */

function crearAgendaBooleanoSQL(bool $valor): string
{
    return $valor ? 'true' : 'false';
}


/* =========================================================
   CONVERTIR HORA A MINUTOS
========================================================= */

function crearAgendaHoraAMinutos(string $hora): ?int
{
    if (
        !preg_match(
            '/^([01]\d|2[0-3]):([0-5]\d)$/',
            $hora,
            $partes
        )
    ) {
        return null;
    }

    return (
        (int) $partes[1] * 60
    ) + (int) $partes[2];
}


/* =========================================================
   CONVERTIR MINUTOS A HORA
========================================================= */

function crearAgendaMinutosAHora(int $minutos): string
{
    $horas = intdiv($minutos, 60);

    $resto = $minutos % 60;

    return sprintf(
        '%02d:%02d',
        $horas,
        $resto
    );
}


/* =========================================================
   VALIDAR FORMATO DE FECHA
========================================================= */

function crearAgendaFechaValida(string $fecha): bool
{
    $objeto = DateTime::createFromFormat(
        '!Y-m-d',
        $fecha,
        new DateTimeZone('America/Santiago')
    );

    return (
        $objeto !== false &&
        $objeto->format('Y-m-d') === $fecha
    );
}


/* =========================================================
   GENERAR CUPOS EN EL SERVIDOR

   El servidor vuelve a calcular todos los intervalos.

   Permite:
   - Duración normal.
   - Último cupo reducido.
   - Máximo de 500 cupos.

   No utiliza configuraciones externas.
========================================================= */

function crearAgendaGenerarCupos(
    string $horaInicio,
    string $horaFin,
    int $duracion
): array {

    $inicio =
        crearAgendaHoraAMinutos($horaInicio);

    $fin =
        crearAgendaHoraAMinutos($horaFin);

    if (
        $inicio === null ||
        $fin === null ||
        $fin <= $inicio
    ) {

        throw new InvalidArgumentException(
            'El rango horario de la agenda no es válido.'
        );

    }

    if ($duracion <= 0) {

        throw new InvalidArgumentException(
            'La duración de los cupos debe ser mayor a cero.'
        );

    }

    $cupos = [];

    $actual = $inicio;

    while ($actual < $fin) {

        $siguiente = min(
            $actual + $duracion,
            $fin
        );

        if ($siguiente <= $actual) {
            break;
        }

        $cupos[] = [

            'numero' => count($cupos) + 1,

            'hora_inicio' =>
                crearAgendaMinutosAHora($actual),

            'hora_fin' =>
                crearAgendaMinutosAHora($siguiente),

            'duracion_minutos' =>
                $siguiente - $actual

        ];

        $actual = $siguiente;

        if (count($cupos) > 500) {

            throw new InvalidArgumentException(
                'La cantidad de cupos supera el límite permitido.'
            );

        }
    }

    return $cupos;
}


/* =========================================================
   VALIDAR MÉTODO HTTP
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responderCrearAgenda(
        false,
        'Método de solicitud no permitido.',
        [],
        405
    );

}


/* =========================================================
   DATOS DE SESIÓN
========================================================= */

$rolFuncionario =
    $_SESSION['rol_funcionario'] ?? 'funcionario';

$usuarioFuncionario =
    $_SESSION['usuario_funcionario'] ?? '';


/* =========================================================
   VALIDAR ROL
========================================================= */

if (
    !in_array(
        $rolFuncionario,
        [
            'superadmin',
            'administrador',
            'funcionario'
        ],
        true
    )
) {

    responderCrearAgenda(
        false,
        'No tienes permisos para generar agendas.',
        [],
        403
    );

}


/* =========================================================
   RECIBIR DATOS
========================================================= */

$idProfesional =
    trim((string) ($_POST['id_profesional'] ?? ''));

$idDepartamento =
    trim((string) ($_POST['id_departamento'] ?? ''));

$idServicio =
    trim((string) ($_POST['id_servicio'] ?? ''));

$fecha =
    trim((string) ($_POST['fecha'] ?? ''));

$sector =
    trim((string) ($_POST['sector'] ?? 'No informado'));

$horaInicioRecibida =
    trim((string) ($_POST['hora_inicio'] ?? ''));

$horaFinRecibida =
    trim((string) ($_POST['hora_fin'] ?? ''));

$duracionRecibida =
    trim((string) ($_POST['duracion_minutos'] ?? ''));

$inicioDiferenciado =
    crearAgendaBooleano(
        $_POST['inicio_diferenciado'] ?? false
    );

$segmentoHabilitado =
    crearAgendaBooleano(
        $_POST['segmento_habilitado'] ?? false
    );

$origenMultiprofesional =
    crearAgendaBooleano(
        $_POST['origen_multiprofesional'] ?? false
    );

$cuposRecibidosJSON =
    $_POST['cupos'] ?? '';


/* =========================================================
   REGLA PRINCIPAL: INICIO DIFERENCIADO OBLIGATORIO

   Si no está marcado, no se permite crear la agenda.

   Tampoco se utilizan horarios de configuraciones
   anteriores para completar automáticamente los datos.
========================================================= */

if (!$inicioDiferenciado) {

    responderCrearAgenda(
        false,
        'No se puede crear la agenda. Debes activar Inicio diferenciado y establecer una hora de inicio y finalización.',
        [],
        400
    );

}


/* =========================================================
   VALIDAR HORAS RECIBIDAS
========================================================= */

$horaInicio =
    $horaInicioRecibida;

$horaFin =
    $horaFinRecibida;

if (
    $horaInicio === '' ||
    $horaFin === ''
) {

    responderCrearAgenda(
        false,
        'Debes indicar la hora de inicio y la hora de finalización.',
        [],
        400
    );

}

$minutosInicio =
    crearAgendaHoraAMinutos($horaInicio);

$minutosFin =
    crearAgendaHoraAMinutos($horaFin);

if (
    $minutosInicio === null ||
    $minutosFin === null
) {

    responderCrearAgenda(
        false,
        'Las horas de inicio y finalización tienen un formato incorrecto.',
        [],
        400
    );

}

if ($minutosFin <= $minutosInicio) {

    responderCrearAgenda(
        false,
        'La hora de finalización debe ser posterior a la hora de inicio.',
        [],
        400
    );

}


/* =========================================================
   VALIDAR LÍMITES DEL CALENDARIO

   Horario permitido:
   07:00 hasta 21:00.
========================================================= */

if (
    $minutosInicio < 7 * 60 ||
    $minutosFin > 21 * 60
) {

    responderCrearAgenda(
        false,
        'El horario debe estar comprendido entre las 07:00 y las 21:00.',
        [],
        400
    );

}


/* =========================================================
   PREPARAR BOOLEANOS PARA POSTGRESQL
========================================================= */

$segmentoHabilitadoSQL =
    crearAgendaBooleanoSQL($segmentoHabilitado);

$origenMultiprofesionalSQL =
    crearAgendaBooleanoSQL($origenMultiprofesional);


/* =========================================================
   VALIDAR IDENTIFICADORES
========================================================= */

foreach (
    [
        'profesional' => $idProfesional,
        'departamento' => $idDepartamento,
        'servicio' => $idServicio
    ] as $nombre => $valor
) {

    if (
        $valor === '' ||
        !ctype_digit($valor) ||
        (int) $valor <= 0
    ) {

        responderCrearAgenda(
            false,
            'El identificador de ' . $nombre . ' no es válido.',
            [],
            400
        );

    }

}

$idProfesional =
    (int) $idProfesional;

$idDepartamento =
    (int) $idDepartamento;

$idServicio =
    (int) $idServicio;


/* =========================================================
   VALIDAR FECHA
========================================================= */

if (
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ||
    !crearAgendaFechaValida($fecha)
) {

    responderCrearAgenda(
        false,
        'La fecha seleccionada no es válida.',
        [],
        400
    );

}

$zonaChile =
    new DateTimeZone('America/Santiago');

$fechaObjeto = new DateTime(
    $fecha,
    $zonaChile
);

$hoy = new DateTime(
    'today',
    $zonaChile
);

if ($fechaObjeto < $hoy) {

    responderCrearAgenda(
        false,
        'No se pueden generar agendas en fechas anteriores a hoy.',
        [],
        400
    );

}


/* =========================================================
   VALIDAR DURACIÓN
========================================================= */

$duracionesPermitidas = [
    10, 15, 20, 25, 30,
    35, 40, 45, 50, 55,
    60, 75, 90, 120, 180
];

if (
    !ctype_digit($duracionRecibida) ||
    !in_array(
        (int) $duracionRecibida,
        $duracionesPermitidas,
        true
    )
) {

    responderCrearAgenda(
        false,
        'La duración seleccionada no es válida.',
        [],
        400
    );

}

$duracion =
    (int) $duracionRecibida;


/* =========================================================
   VALIDAR SECTOR
========================================================= */

$sector =
    trim($sector);

if ($sector === '') {
    $sector = 'No informado';
}

if (mb_strlen($sector, 'UTF-8') > 100) {

    responderCrearAgenda(
        false,
        'El sector no puede superar los 100 caracteres.',
        [],
        400
    );

}


/* =========================================================
   VALIDAR CUPOS RECIBIDOS
========================================================= */

$cuposRecibidos = json_decode(
    $cuposRecibidosJSON,
    true
);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($cuposRecibidos) ||
    count($cuposRecibidos) === 0
) {

    responderCrearAgenda(
        false,
        'No se recibió una vista previa válida de los cupos.',
        [],
        400
    );

}


/* =========================================================
   INICIAR PROCESAMIENTO DE BASE DE DATOS
========================================================= */

$transaccionIniciada = false;

try {

    /* =====================================================
       OBTENER FUNCIONARIO CREADOR
    ===================================================== */

    $idFuncionarioCreador = null;

    $idDepartamentoFuncionario = null;

    if ($usuarioFuncionario !== '') {

        $sqlFuncionario = "

            SELECT
                id_funcionario,
                id_departamento

            FROM funcionarios

            WHERE usuario = :usuario

            LIMIT 1

        ";

        $stmtFuncionario =
            $conexion->prepare($sqlFuncionario);

        $stmtFuncionario->execute([
            ':usuario' => $usuarioFuncionario
        ]);

        $funcionario =
            $stmtFuncionario->fetch(PDO::FETCH_ASSOC);

        if ($funcionario) {

            $idFuncionarioCreador =
                (int) $funcionario['id_funcionario'];

            $idDepartamentoFuncionario =
                (int) $funcionario['id_departamento'];

        }

    }


    /* =====================================================
       VALIDAR FUNCIONARIO CREADOR
    ===================================================== */

    if (
        $rolFuncionario !== 'superadmin' &&
        $idFuncionarioCreador === null
    ) {

        responderCrearAgenda(
            false,
            'No se pudo identificar al funcionario creador.',
            [],
            403
        );

    }


    /* =====================================================
       VALIDAR SERVICIO Y DEPARTAMENTO
    ===================================================== */

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

          AND s.id_departamento = :id_departamento

          AND s.activo = TRUE

          AND d.activo = TRUE

        LIMIT 1

    ";

    $stmtServicio =
        $conexion->prepare($sqlServicio);

    $stmtServicio->execute([

        ':id_servicio' =>
            $idServicio,

        ':id_departamento' =>
            $idDepartamento

    ]);

    $servicio =
        $stmtServicio->fetch(PDO::FETCH_ASSOC);

    if (!$servicio) {

        responderCrearAgenda(
            false,
            'El servicio no pertenece al departamento seleccionado o está deshabilitado.',
            [],
            400
        );

    }


    /* =====================================================
       VALIDAR PERMISOS DEL DEPARTAMENTO
    ===================================================== */

    if (
        $rolFuncionario !== 'superadmin' &&
        $idDepartamentoFuncionario !== $idDepartamento
    ) {

        responderCrearAgenda(
            false,
            'No tienes permisos para generar agendas en este departamento.',
            [],
            403
        );

    }


    /* =====================================================
       VALIDAR PROFESIONAL
    ===================================================== */

    $sqlProfesional = "

        SELECT
            id_profesional,
            nombre,
            apellido,
            estamento,
            nodo

        FROM profesionales

        WHERE id_profesional = :id_profesional

          AND activo = TRUE

        LIMIT 1

    ";

    $stmtProfesional =
        $conexion->prepare($sqlProfesional);

    $stmtProfesional->execute([
        ':id_profesional' => $idProfesional
    ]);

    $profesional =
        $stmtProfesional->fetch(PDO::FETCH_ASSOC);

    if (!$profesional) {

        responderCrearAgenda(
            false,
            'El profesional seleccionado no existe o está deshabilitado.',
            [],
            404
        );

    }


    /* =====================================================
       GENERAR CUPOS EN EL SERVIDOR

       Se utilizan exclusivamente:
       - Hora de inicio recibida.
       - Hora de finalización recibida.
       - Duración recibida.

       No se consulta ninguna configuración antigua.
    ===================================================== */

    $cuposServidor =
        crearAgendaGenerarCupos(
            $horaInicio,
            $horaFin,
            $duracion
        );

    if (count($cuposServidor) === 0) {

        responderCrearAgenda(
            false,
            'No se pudieron generar cupos para el horario seleccionado.',
            [],
            400
        );

    }


    /* =====================================================
       COMPARAR VISTA PREVIA CON LOS CUPOS DEL SERVIDOR

       El servidor no confía en los cupos enviados
       por JavaScript.
    ===================================================== */

    if (
        count($cuposServidor) !==
        count($cuposRecibidos)
    ) {

        responderCrearAgenda(
            false,
            'La vista previa no coincide con el horario calculado por el servidor. Genera nuevamente la vista previa.',
            [],
            409
        );

    }

    foreach ($cuposServidor as $indice => $cupo) {

        $cupoRecibido =
            $cuposRecibidos[$indice] ?? null;

        if (!is_array($cupoRecibido)) {

            responderCrearAgenda(
                false,
                'Los datos de la vista previa no son válidos.',
                [],
                400
            );

        }

        $horaRecibidaInicio =
            substr(
                (string) ($cupoRecibido['hora_inicio'] ?? ''),
                0,
                5
            );

        $horaRecibidaFin =
            substr(
                (string) ($cupoRecibido['hora_fin'] ?? ''),
                0,
                5
            );

        $duracionRecibidaCupo =
            (int) ($cupoRecibido['duracion_minutos'] ?? 0);

        if (
            $horaRecibidaInicio !== $cupo['hora_inicio'] ||
            $horaRecibidaFin !== $cupo['hora_fin'] ||
            $duracionRecibidaCupo !== $cupo['duracion_minutos']
        ) {

            responderCrearAgenda(
                false,
                'La información de los cupos fue modificada o no coincide con el cálculo del servidor.',
                [],
                409
            );

        }

    }


    /* =====================================================
       INICIAR TRANSACCIÓN
    ===================================================== */

    $conexion->beginTransaction();

    $transaccionIniciada = true;


    /* =====================================================
       INSERTAR AGENDA
    ===================================================== */

    $sqlCrearAgenda = "

        INSERT INTO agendas (

            id_servicio,

            fecha,

            hora_inicio,

            hora_fin,

            duracion_inicial_minutos,

            estado,

            id_funcionario_creador,

            id_profesional,

            sector,

            segmento_habilitado,

            origen_multiprofesional,

            fecha_publicacion

        )

        VALUES (

            :id_servicio,

            :fecha,

            :hora_inicio,

            :hora_fin,

            :duracion,

            'publicada',

            :id_funcionario_creador,

            :id_profesional,

            :sector,

            :segmento_habilitado,

            :origen_multiprofesional,

            CURRENT_TIMESTAMP

        )

        RETURNING id_agenda

    ";

    $stmtCrearAgenda =
        $conexion->prepare($sqlCrearAgenda);

    $stmtCrearAgenda->execute([

        ':id_servicio' =>
            $idServicio,

        ':fecha' =>
            $fecha,

        ':hora_inicio' =>
            $horaInicio . ':00',

        ':hora_fin' =>
            $horaFin . ':00',

        ':duracion' =>
            $duracion,

        ':id_funcionario_creador' =>
            $idFuncionarioCreador,

        ':id_profesional' =>
            $idProfesional,

        ':sector' =>
            $sector,

        ':segmento_habilitado' =>
            $segmentoHabilitadoSQL,

        ':origen_multiprofesional' =>
            $origenMultiprofesionalSQL

    ]);

    $idAgenda =
        (int) $stmtCrearAgenda->fetchColumn();


    /* =====================================================
       DETERMINAR ESTADO INICIAL DE LOS CUPOS
    ===================================================== */

    $estadoHorario =
        $segmentoHabilitado
            ? 'disponible'
            : 'bloqueado';


    /* =====================================================
       PREPARAR INSERCIÓN DE CUPOS
    ===================================================== */

    $sqlCrearHorario = "

        INSERT INTO horarios (

            id_servicio,

            fecha,

            hora,

            hora_fin,

            estado,

            observacion,

            id_funcionario,

            id_agenda,

            habilitado,

            disponible_web

        )

        VALUES (

            :id_servicio,

            :fecha,

            :hora,

            :hora_fin,

            :estado,

            NULL,

            :id_funcionario,

            :id_agenda,

            :habilitado,

            :disponible_web

        )

    ";

    $stmtCrearHorario =
        $conexion->prepare($sqlCrearHorario);


    /* =====================================================
       INSERTAR TODOS LOS CUPOS DE LA AGENDA
    ===================================================== */

    foreach ($cuposServidor as $cupo) {

        $stmtCrearHorario->execute([

            ':id_servicio' =>
                $idServicio,

            ':fecha' =>
                $fecha,

            ':hora' =>
                $cupo['hora_inicio'] . ':00',

            ':hora_fin' =>
                $cupo['hora_fin'] . ':00',

            ':estado' =>
                $estadoHorario,

            ':id_funcionario' =>
                $idFuncionarioCreador,

            ':id_agenda' =>
                $idAgenda,

            /*
             * Si el segmento está deshabilitado,
             * sus cupos también quedan deshabilitados
             * y no disponibles para la web.
             */

            ':habilitado' =>
                $segmentoHabilitadoSQL,

            ':disponible_web' =>
                $segmentoHabilitadoSQL

        ]);

    }


    /* =====================================================
       CONFIRMAR TRANSACCIÓN
    ===================================================== */

    $conexion->commit();

    $transaccionIniciada = false;


    /* =====================================================
       RESPUESTA EXITOSA
    ===================================================== */

    responderCrearAgenda(
        true,
        'La agenda y sus cupos fueron guardados correctamente.',
        [

            'id_agenda' =>
                $idAgenda,

            'id_profesional' =>
                $idProfesional,

            'id_servicio' =>
                $idServicio,

            'fecha' =>
                $fecha,

            'hora_inicio' =>
                $horaInicio,

            'hora_fin' =>
                $horaFin,

            'duracion_minutos' =>
                $duracion,

            'total_cupos' =>
                count($cuposServidor),

            'estado' =>
                'publicada',

            'segmento_habilitado' =>
                $segmentoHabilitado,

            'cupos' =>
                $cuposServidor

        ]
    );


} catch (PDOException $e) {

    /* =====================================================
       REVERTIR TRANSACCIÓN
    ===================================================== */

    if (
        $transaccionIniciada &&
        $conexion->inTransaction()
    ) {

        $conexion->rollBack();

    }

    error_log(
        'Error crear_agenda.php: ' .
        $e->getMessage()
    );

    $sqlState =
        $e->errorInfo[0] ?? $e->getCode();


    /* =====================================================
       CONFLICTO DE AGENDA DEL PROFESIONAL
    ===================================================== */

    if ($sqlState === '23P01') {

        responderCrearAgenda(
            false,
            'El profesional ya tiene otra agenda que se superpone con este horario.',
            [],
            409
        );

    }


    /* =====================================================
       REGISTROS DUPLICADOS
    ===================================================== */

    if ($sqlState === '23505') {

        responderCrearAgenda(
            false,
            'Ya existen cupos registrados en ese segmento horario.',
            [],
            409
        );

    }


    /* =====================================================
       CLAVE FORÁNEA
    ===================================================== */

    if ($sqlState === '23503') {

        responderCrearAgenda(
            false,
            'No se pudo relacionar la agenda con uno de sus registros.',
            [],
            409
        );

    }


    /* =====================================================
       FORMATO INCORRECTO
    ===================================================== */

    if ($sqlState === '22P02') {

        responderCrearAgenda(
            false,
            'Se recibió un dato con un formato incorrecto. Revisa los campos y los valores enviados.',
            [],
            400
        );

    }


    /* =====================================================
       ERROR GENERAL DE BASE DE DATOS
    ===================================================== */

    responderCrearAgenda(
        false,
        'Ocurrió un error al guardar la agenda. Revisa el registro de errores del servidor.',
        [],
        500
    );


} catch (Throwable $e) {

    /* =====================================================
       REVERTIR TRANSACCIÓN
    ===================================================== */

    if (
        $transaccionIniciada &&
        $conexion->inTransaction()
    ) {

        $conexion->rollBack();

    }

    error_log(
        'Error general crear_agenda.php: ' .
        $e->getMessage()
    );

    responderCrearAgenda(
        false,
        'Ocurrió un error al procesar la generación de la agenda.',
        [],
        500
    );

}
