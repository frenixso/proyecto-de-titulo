
<?php

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';

/* =========================================================
   RESPUESTA JSON
========================================================= */

header('Content-Type: application/json; charset=utf-8');

function responderEditarHorario(
    int $codigo,
    bool $ok,
    string $mensaje,
    array $datos = []
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
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/* =========================================================
   CONVERTIR BOOLEANOS DE FORMA SEGURA
========================================================= */

function editarHorarioBooleano($valor): ?bool
{
    if (
        $valor === '1' ||
        $valor === 1 ||
        $valor === true ||
        $valor === 'true' ||
        $valor === 't'
    ) {
        return true;
    }

    if (
        $valor === '0' ||
        $valor === 0 ||
        $valor === false ||
        $valor === 'false' ||
        $valor === 'f'
    ) {
        return false;
    }

    return null;
}

/* =========================================================
   CONVERTIR HORA A MINUTOS
========================================================= */

function editarHorarioAMinutos(?string $hora): ?int
{
    if (
        $hora === null ||
        !preg_match('/^\d{2}:\d{2}$/', $hora)
    ) {
        return null;
    }

    $partes = explode(':', $hora);

    $horas = (int) $partes[0];
    $minutos = (int) $partes[1];

    if (
        $horas > 23 ||
        $minutos > 59
    ) {
        return null;
    }

    return ($horas * 60) + $minutos;
}

/* =========================================================
   FORMATEAR HORA DE POSTGRESQL
========================================================= */

function editarHorarioFormatearHora($valor): ?string
{
    if ($valor === null || $valor === '') {
        return null;
    }

    return substr((string) $valor, 0, 5);
}

/* =========================================================
   VALIDAR FECHA DE POSTGRESQL
========================================================= */

function editarHorarioFechaValida($fecha): bool
{
    if (
        !is_string($fecha) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)
    ) {
        return false;
    }

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
   VALIDAR MÉTODO HTTP
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responderEditarHorario(
        405,
        false,
        'Método de solicitud no permitido.'
    );
}

/* =========================================================
   VALIDAR AUTENTICACIÓN Y ROL
========================================================= */

$rolFuncionario =
    $_SESSION['rol_funcionario'] ?? '';

$usuarioFuncionario =
    $_SESSION['usuario_funcionario'] ?? '';

if (
    !in_array(
        $rolFuncionario,
        ['superadmin', 'administrador', 'funcionario'],
        true
    )
) {

    responderEditarHorario(
        403,
        false,
        'No tienes permisos para editar horarios.'
    );
}

if ($rolFuncionario !== 'superadmin' && $usuarioFuncionario === '') {

    responderEditarHorario(
        403,
        false,
        'No fue posible identificar al funcionario autenticado.'
    );
}

/* =========================================================
   RECIBIR DATOS DEL FORMULARIO
========================================================= */

$idHorario = filter_var(
    $_POST['id_horario'] ?? null,
    FILTER_VALIDATE_INT
);

$horaInicioNueva = trim(
    (string) ($_POST['hora'] ?? '')
);

$horaFinRecibida = trim(
    (string) ($_POST['hora_fin'] ?? '')
);

$estadoNuevo = trim(
    (string) ($_POST['estado'] ?? '')
);

$observacionNueva = trim(
    (string) ($_POST['observacion'] ?? '')
);

$habilitadoRecibido = editarHorarioBooleano(
    $_POST['habilitado'] ?? null
);

$disponibleWebRecibido = editarHorarioBooleano(
    $_POST['disponible_web'] ?? null
);

/* =========================================================
   VALIDAR IDENTIFICADOR
========================================================= */

if (
    $idHorario === false ||
    $idHorario <= 0
) {

    responderEditarHorario(
        400,
        false,
        'El identificador del cupo no es válido.'
    );
}

/* =========================================================
   VALIDAR HORA DE INICIO
========================================================= */

$minutosInicioNueva =
    editarHorarioAMinutos($horaInicioNueva);

if ($minutosInicioNueva === null) {

    responderEditarHorario(
        400,
        false,
        'La hora de inicio no es válida.'
    );
}

/* =========================================================
   VALIDAR HORA DE FINALIZACIÓN
========================================================= */

$minutosFinNueva = null;

if ($horaFinRecibida !== '') {

    $minutosFinNueva =
        editarHorarioAMinutos($horaFinRecibida);

    if ($minutosFinNueva === null) {

        responderEditarHorario(
            400,
            false,
            'La hora de finalización no es válida.'
        );
    }

    if ($minutosFinNueva <= $minutosInicioNueva) {

        responderEditarHorario(
            400,
            false,
            'La hora de finalización debe ser posterior a la hora de inicio.'
        );
    }
}

/* =========================================================
   VALIDAR ESTADO
========================================================= */

if (
    !in_array(
        $estadoNuevo,
        ['disponible', 'bloqueado'],
        true
    )
) {

    responderEditarHorario(
        400,
        false,
        'El estado seleccionado no es válido.'
    );
}

/* =========================================================
   VALIDAR BOOLEANOS
========================================================= */

if (
    $habilitadoRecibido === null ||
    $disponibleWebRecibido === null
) {

    responderEditarHorario(
        400,
        false,
        'Los valores de habilitación del cupo no son válidos.'
    );
}

/* =========================================================
   VALIDAR OBSERVACIÓN
========================================================= */

if (mb_strlen($observacionNueva, 'UTF-8') > 500) {

    responderEditarHorario(
        400,
        false,
        'La observación no puede superar los 500 caracteres.'
    );
}

/* =========================================================
   NORMALIZAR ESTADO Y BOOLEANOS
========================================================= */

/*
 * Un cupo bloqueado no puede quedar habilitado
 * ni disponible para reservas web.
 */

if ($estadoNuevo === 'bloqueado') {

    $habilitadoRecibido = false;

    $disponibleWebRecibido = false;
}

/*
 * Un cupo disponible debe estar habilitado.
 * La disponibilidad web puede desactivarse
 * para conservarlo solo para gestión interna.
 */

if (
    $estadoNuevo === 'disponible' &&
    !$habilitadoRecibido
) {

    responderEditarHorario(
        400,
        false,
        'Para dejar el cupo disponible debes marcar la opción Cupo habilitado. Si deseas desactivarlo, selecciona el estado Bloqueado.'
    );
}

if (!$habilitadoRecibido) {
    $disponibleWebRecibido = false;
}

/*
 * Se utilizan cadenas PostgreSQL explícitas.
 * Evita enviar booleanos PHP false como texto vacío.
 */

$habilitadoSQL =
    $habilitadoRecibido ? 'true' : 'false';

$disponibleWebSQL =
    $disponibleWebRecibido ? 'true' : 'false';

/* =========================================================
   VALIDAR Y NORMALIZAR HORA DE FINALIZACIÓN
========================================================= */

$horaFinNueva = $horaFinRecibida !== ''
    ? $horaFinRecibida
    : null;

/* =========================================================
   INICIAR TRANSACCIÓN
========================================================= */

try {

    $conexion->beginTransaction();

    /* =====================================================
       OBTENER Y BLOQUEAR EL CUPO
    ===================================================== */

    $sqlHorario = "

        SELECT

            h.id_horario,
            h.id_servicio,
            h.id_agenda,
            h.fecha,
            h.hora,
            h.hora_fin,
            h.estado,
            h.habilitado,
            h.disponible_web,
            h.observacion,

            s.id_departamento

        FROM horarios h

        INNER JOIN servicios s
            ON s.id_servicio = h.id_servicio

        WHERE h.id_horario = :id_horario

        FOR UPDATE OF h

    ";

    $stmtHorario = $conexion->prepare($sqlHorario);

    $stmtHorario->execute([
        ':id_horario' => $idHorario
    ]);

    $horarioActual = $stmtHorario->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$horarioActual) {

        $conexion->rollBack();

        responderEditarHorario(
            404,
            false,
            'El cupo que intentas editar no existe.'
        );
    }

    /* =====================================================
       OBTENER DATOS DEL HORARIO ACTUAL
    ===================================================== */

    $idServicio =
        (int) $horarioActual['id_servicio'];

    $idAgenda =
        $horarioActual['id_agenda'] !== null
            ? (int) $horarioActual['id_agenda']
            : null;

    $fechaHorario =
        substr(
            (string) $horarioActual['fecha'],
            0,
            10
        );

    $horaActual =
        editarHorarioFormatearHora(
            $horarioActual['hora']
        );

    $horaFinActual =
        editarHorarioFormatearHora(
            $horarioActual['hora_fin']
        );

    if (
        !$horaActual ||
        !editarHorarioFechaValida($fechaHorario)
    ) {

        throw new RuntimeException(
            'Los datos originales del cupo no son válidos.'
        );
    }

    /*
     * Cuando el campo de finalización viene vacío,
     * se conserva el valor que ya tenía el horario.
     */

    if ($horaFinNueva === null) {
        $horaFinNueva = $horaFinActual;
    }

    $minutosFinEfectivos = null;

    if ($horaFinNueva !== null) {

        $minutosFinEfectivos =
            editarHorarioAMinutos($horaFinNueva);

        if (
            $minutosFinEfectivos === null ||
            $minutosFinEfectivos <= $minutosInicioNueva
        ) {

            throw new RuntimeException(
                'El intervalo horario no es válido.'
            );
        }

    } else {

        /*
         * Para horarios históricos sin hora_fin,
         * se utiliza un intervalo provisional de 30 minutos
         * solo para verificar cruces.
         */

        $minutosFinEfectivos =
            $minutosInicioNueva + 30;
    }

    if ($minutosFinEfectivos > 1440) {

        throw new RuntimeException(
            'La hora de finalización supera el límite del día.'
        );
    }

    /* =====================================================
       VALIDAR PERMISOS SOBRE EL SERVICIO
    ===================================================== */

    if ($rolFuncionario !== 'superadmin') {

        $sqlDepartamentoFuncionario = "

            SELECT id_departamento

            FROM funcionarios

            WHERE usuario = :usuario

            LIMIT 1

        ";

        $stmtDepartamentoFuncionario =
            $conexion->prepare(
                $sqlDepartamentoFuncionario
            );

        $stmtDepartamentoFuncionario->execute([
            ':usuario' => $usuarioFuncionario
        ]);

        $departamentoFuncionario =
            $stmtDepartamentoFuncionario->fetchColumn();

        if (
            $departamentoFuncionario === false ||
            $departamentoFuncionario === null
        ) {

            $conexion->rollBack();

            responderEditarHorario(
                403,
                false,
                'No se pudo verificar el departamento del funcionario.'
            );
        }

        if (
            (int) $departamentoFuncionario !==
            (int) $horarioActual['id_departamento']
        ) {

            $conexion->rollBack();

            responderEditarHorario(
                403,
                false,
                'No tienes permisos para modificar horarios de este departamento.'
            );
        }
    }

    /* =====================================================
       BLOQUEAR AGENDA ASOCIADA
    ===================================================== */

    $idProfesional = null;

    $horaInicioAgenda = null;
    $horaFinAgenda = null;

    if ($idAgenda !== null) {

        $sqlAgenda = "

            SELECT

                id_agenda,
                id_servicio,
                id_profesional,
                fecha,
                hora_inicio,
                hora_fin,
                estado

            FROM agendas

            WHERE id_agenda = :id_agenda

            FOR UPDATE

        ";

        $stmtAgenda = $conexion->prepare($sqlAgenda);

        $stmtAgenda->execute([
            ':id_agenda' => $idAgenda
        ]);

        $agendaActual =
            $stmtAgenda->fetch(PDO::FETCH_ASSOC);

        if (!$agendaActual) {

            throw new RuntimeException(
                'La agenda asociada al cupo ya no existe.'
            );
        }

        if (
            (int) $agendaActual['id_servicio'] !== $idServicio ||
            substr(
                (string) $agendaActual['fecha'],
                0,
                10
            ) !== $fechaHorario
        ) {

            throw new RuntimeException(
                'El cupo no coincide con los datos de su agenda.'
            );
        }

        $idProfesional =
            (int) $agendaActual['id_profesional'];

        $horaInicioAgenda =
            editarHorarioFormatearHora(
                $agendaActual['hora_inicio']
            );

        $horaFinAgenda =
            editarHorarioFormatearHora(
                $agendaActual['hora_fin']
            );

        /*
         * No permitimos que un cupo se mueva fuera
         * del segmento al que pertenece.
         */

        $minutosLimiteInicio =
            editarHorarioAMinutos($horaInicioAgenda);

        $minutosLimiteFin =
            editarHorarioAMinutos($horaFinAgenda);

        if (
            $minutosLimiteInicio === null ||
            $minutosLimiteFin === null
        ) {

            throw new RuntimeException(
                'No se pudieron validar los límites de la agenda asociada.'
            );
        }

        if (
            $minutosInicioNueva < $minutosLimiteInicio ||
            $minutosFinEfectivos > $minutosLimiteFin
        ) {

            throw new RuntimeException(
                'El horario del cupo debe permanecer dentro del segmento de agenda original.'
            );
        }
    }

    /* =====================================================
       COMPROBAR RESERVAS ACTIVAS
    ===================================================== */

    $sqlReservas = "

        SELECT
            id_reserva,
            estado

        FROM reservas

        WHERE id_horario = :id_horario

          AND estado IN (
              'confirmada',
              'reagendada'
          )

        LIMIT 1

        FOR UPDATE

    ";

    $stmtReservas = $conexion->prepare($sqlReservas);

    $stmtReservas->execute([
        ':id_horario' => $idHorario
    ]);

    $reservaActiva =
        $stmtReservas->fetch(PDO::FETCH_ASSOC);

    if ($reservaActiva) {

        $conexion->rollBack();

        responderEditarHorario(
            409,
            false,
            'Este cupo tiene una reserva activa. No se puede modificar mientras esté reservado.'
        );
    }

    /* =====================================================
       BLOQUEAR HORARIOS RELACIONADOS
    ===================================================== */

    /*
     * Si pertenece a una agenda, el bloqueo de la fila
     * de agenda permite serializar las modificaciones
     * de sus cupos.
     *
     * Para horarios históricos sin agenda, se bloquean
     * los horarios del mismo servicio y fecha.
     */

    if ($idAgenda === null) {

        $sqlBloquearLegados = "

            SELECT id_horario

            FROM horarios

            WHERE fecha = :fecha
              AND id_servicio = :id_servicio

            FOR UPDATE

        ";

        $stmtBloquearLegados =
            $conexion->prepare($sqlBloquearLegados);

        $stmtBloquearLegados->execute([
            ':fecha' => $fechaHorario,
            ':id_servicio' => $idServicio
        ]);

        $stmtBloquearLegados->fetchAll(
            PDO::FETCH_COLUMN
        );
    }

    /* =====================================================
       COMPROBAR CRUCES DE HORARIO
    ===================================================== */

    if ($idProfesional !== null) {

        /*
         * Cupos del mismo profesional, incluso si
         * pertenecen a otras agendas.
         *
         * También se incluyen horarios históricos
         * del mismo servicio sin agenda asociada.
         */

        $sqlCruces = "

            SELECT

                h.id_horario,

                h.hora,

                COALESCE(
                    h.hora_fin,
                    h.hora + INTERVAL '30 minutes'
                )::time AS hora_fin_efectiva

            FROM horarios h

            LEFT JOIN agendas a
                ON a.id_agenda = h.id_agenda

            WHERE h.fecha = :fecha

              AND h.id_horario <> :id_horario

              AND (
                    a.id_profesional = :id_profesional

                    OR (
                        h.id_agenda IS NULL
                        AND h.id_servicio = :id_servicio
                    )
              )

        ";

        $stmtCruces = $conexion->prepare($sqlCruces);

        $stmtCruces->execute([
            ':fecha' => $fechaHorario,
            ':id_horario' => $idHorario,
            ':id_profesional' => $idProfesional,
            ':id_servicio' => $idServicio
        ]);

    } else {

        /*
         * Horarios sin agenda vinculada.
         * No existe información fiable del profesional,
         * por lo que se comprueba el servicio correspondiente.
         */

        $sqlCruces = "

            SELECT

                id_horario,

                hora,

                COALESCE(
                    hora_fin,
                    hora + INTERVAL '30 minutes'
                )::time AS hora_fin_efectiva

            FROM horarios

            WHERE fecha = :fecha

              AND id_servicio = :id_servicio

              AND id_horario <> :id_horario

        ";

        $stmtCruces = $conexion->prepare($sqlCruces);

        $stmtCruces->execute([
            ':fecha' => $fechaHorario,
            ':id_servicio' => $idServicio,
            ':id_horario' => $idHorario
        ]);
    }

    $otrosHorarios =
        $stmtCruces->fetchAll(PDO::FETCH_ASSOC);

    /* =====================================================
       COMPARAR INTERVALOS
    ===================================================== */

    foreach ($otrosHorarios as $otroHorario) {

        $horaOtroInicio =
            editarHorarioFormatearHora(
                $otroHorario['hora']
            );

        $horaOtroFin =
            editarHorarioFormatearHora(
                $otroHorario['hora_fin_efectiva']
            );

        $minutosOtroInicio =
            editarHorarioAMinutos($horaOtroInicio);

        $minutosOtroFin =
            editarHorarioAMinutos($horaOtroFin);

        if (
            $minutosOtroInicio === null ||
            $minutosOtroFin === null ||
            $minutosOtroFin <= $minutosOtroInicio
        ) {
            continue;
        }

        /*
         * Intervalos [inicio, fin).
         * Dos cupos consecutivos pueden tocarse
         * sin considerarse un cruce.
         */

        $existeCruce = (
            $minutosInicioNueva < $minutosOtroFin &&
            $minutosFinEfectivos > $minutosOtroInicio
        );

        if ($existeCruce) {

            throw new RuntimeException(
                'El horario seleccionado se cruza con otro cupo del mismo profesional o servicio.'
            );
        }
    }

    /* =====================================================
       ACTUALIZAR CUPO
    ===================================================== */

    $observacionSQL =
        $observacionNueva !== ''
            ? $observacionNueva
            : null;

    $sqlActualizar = "

        UPDATE horarios

        SET

            hora = :hora,

            hora_fin = :hora_fin,

            estado = :estado,

            observacion = :observacion,

            habilitado = CAST(:habilitado AS BOOLEAN),

            disponible_web = CAST(:disponible_web AS BOOLEAN)

        WHERE id_horario = :id_horario

    ";

    $stmtActualizar =
        $conexion->prepare($sqlActualizar);

    $stmtActualizar->bindValue(
        ':hora',
        $horaInicioNueva,
        PDO::PARAM_STR
    );

    if ($horaFinNueva === null) {

        $stmtActualizar->bindValue(
            ':hora_fin',
            null,
            PDO::PARAM_NULL
        );

    } else {

        $stmtActualizar->bindValue(
            ':hora_fin',
            $horaFinNueva,
            PDO::PARAM_STR
        );
    }

    $stmtActualizar->bindValue(
        ':estado',
        $estadoNuevo,
        PDO::PARAM_STR
    );

    if ($observacionSQL === null) {

        $stmtActualizar->bindValue(
            ':observacion',
            null,
            PDO::PARAM_NULL
        );

    } else {

        $stmtActualizar->bindValue(
            ':observacion',
            $observacionSQL,
            PDO::PARAM_STR
        );
    }

    /*
     * Los booleanos llegan a PostgreSQL como
     * las cadenas explícitas true y false.
     */

    $stmtActualizar->bindValue(
        ':habilitado',
        $habilitadoSQL,
        PDO::PARAM_STR
    );

    $stmtActualizar->bindValue(
        ':disponible_web',
        $disponibleWebSQL,
        PDO::PARAM_STR
    );

    $stmtActualizar->bindValue(
        ':id_horario',
        $idHorario,
        PDO::PARAM_INT
    );

    $stmtActualizar->execute();

    /* =====================================================
       CONFIRMAR TRANSACCIÓN
    ===================================================== */

    $conexion->commit();

    responderEditarHorario(
        200,
        true,
        'El cupo fue actualizado correctamente.',
        [
            'id_horario' => $idHorario,
            'fecha' => $fechaHorario,
            'hora' => $horaInicioNueva,
            'hora_fin' => $horaFinNueva,
            'estado' => $estadoNuevo,
            'habilitado' => $habilitadoRecibido,
            'disponible_web' => $disponibleWebRecibido
        ]
    );

/* =========================================================
   ERRORES DE EJECUCIÓN
========================================================= */

} catch (RuntimeException $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    responderEditarHorario(
        409,
        false,
        $e->getMessage()
    );

} catch (PDOException $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        'Error editar_horario.php: ' .
        $e->getMessage()
    );

    $codigoSQL = $e->getCode();

    if ($codigoSQL === '23P01') {

        responderEditarHorario(
            409,
            false,
            'El cambio genera un conflicto con otro horario del profesional.'
        );
    }

    if (
        $codigoSQL === '23505' ||
        $codigoSQL === '23503'
    ) {

        responderEditarHorario(
            409,
            false,
            'El cambio entra en conflicto con otros registros del sistema.'
        );
    }

    responderEditarHorario(
        500,
        false,
        'Ocurrió un error al actualizar el cupo. Revisa el registro de errores de PHP.'
    );

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        'Error inesperado editar_horario.php: ' .
        $e->getMessage()
    );

    responderEditarHorario(
        500,
        false,
        'Ocurrió un error inesperado al actualizar el cupo.'
    );
}
