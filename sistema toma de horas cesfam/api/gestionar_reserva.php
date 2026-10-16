<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../componentes/auth_funcionario.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../componentes/correos_reservas.php';

date_default_timezone_set('America/Santiago');


/* =========================================================
   RESPUESTAS JSON
   ========================================================= */

function responder(
    bool $ok,
    string $mensaje,
    array $datos = [],
    int $codigo = 200
) {
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
   VALIDAR FECHA
   ========================================================= */

function validarFecha(string $fecha): bool
{
    $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);
    $errores = DateTime::getLastErrors();

    return
        $objeto !== false &&
        $objeto->format('Y-m-d') === $fecha &&
        (
            $errores === false ||
            (
                $errores['warning_count'] === 0 &&
                $errores['error_count'] === 0
            )
        );
}


/* =========================================================
   VALIDAR HORA
   ========================================================= */

function validarHora(string $hora): bool
{
    return preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $hora
    ) === 1;
}


/* =========================================================
   BOOLEANOS POSTGRESQL
   ========================================================= */

function esVerdadero($valor): bool
{
    if (is_bool($valor)) {
        return $valor;
    }

    if (is_int($valor) || is_float($valor)) {
        return (int) $valor === 1;
    }

    return in_array(
        strtolower(trim((string) $valor)),
        ['1', 't', 'true', 'y', 'yes', 'on'],
        true
    );
}


/* =========================================================
   OBTENER INFORMACIÓN DEL SERVICIO
   ========================================================= */

function obtenerServicio(
    PDO $conexion,
    int $idServicio
): ?array {
    $stmt = $conexion->prepare("
        SELECT
            s.id_servicio,
            s.nombre,
            s.id_departamento,
            s.activo AS servicio_activo,
            d.nombre AS nombre_departamento,
            d.activo AS departamento_activo,
            d.horarios_unificados
        FROM servicios s
        INNER JOIN departamentos d
            ON d.id_departamento = s.id_departamento
        WHERE s.id_servicio = :id_servicio
        LIMIT 1
    ");

    $stmt->execute([
        ':id_servicio' => $idServicio
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}


/* =========================================================
   OBTENER SERVICIOS ACTIVOS DEL DEPARTAMENTO
   ========================================================= */

function obtenerServiciosDepartamento(
    PDO $conexion,
    int $idDepartamento
): array {
    $stmt = $conexion->prepare("
        SELECT id_servicio
        FROM servicios
        WHERE id_departamento = :id_departamento
          AND activo = TRUE
        ORDER BY id_servicio
    ");

    $stmt->execute([
        ':id_departamento' => $idDepartamento
    ]);

    return array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}


/* =========================================================
   OBTENER SERVICIOS QUE COMPARTEN DISPONIBILIDAD
   =========================================================
   La tabla unificacion_horarios_servicios utiliza:
   - id_departamento
   - id_servicio

   No utiliza id_grupo.
   ========================================================= */

function obtenerServiciosDisponibilidad(
    PDO $conexion,
    array $servicio
): array {
    $idServicio = (int) $servicio['id_servicio'];
    $idDepartamento = (int) $servicio['id_departamento'];

    if (esVerdadero($servicio['horarios_unificados'] ?? false)) {
        $ids = obtenerServiciosDepartamento(
            $conexion,
            $idDepartamento
        );

        if (!in_array($idServicio, $ids, true)) {
            $ids[] = $idServicio;
        }

        return array_values(array_unique($ids));
    }

    $stmt = $conexion->prepare("
        SELECT u.id_servicio
        FROM unificacion_horarios_servicios u
        INNER JOIN servicios s
            ON s.id_servicio = u.id_servicio
        WHERE u.id_departamento = :id_departamento
          AND s.id_departamento = u.id_departamento
          AND s.activo = TRUE
        ORDER BY u.id_servicio
    ");

    $stmt->execute([
        ':id_departamento' => $idDepartamento
    ]);

    $idsGrupo = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    /*
     * Si no hay al menos dos servicios asociados,
     * o el servicio actual no pertenece a la asociación,
     * se trata como un servicio independiente.
     */

    if (
        count($idsGrupo) < 2 ||
        !in_array($idServicio, $idsGrupo, true)
    ) {
        return [$idServicio];
    }

    return array_values(array_unique($idsGrupo));
}


/* =========================================================
   OBTENER SERVICIO DE REFERENCIA PARA HORARIOS
   ========================================================= */

function obtenerServicioReferencia(
    PDO $conexion,
    array $servicio
): int {
    $idServicio = (int) $servicio['id_servicio'];

    if (!esVerdadero($servicio['horarios_unificados'] ?? false)) {
        return $idServicio;
    }

    $idDepartamento = (int) $servicio['id_departamento'];

    $stmt = $conexion->prepare("
        SELECT s.id_servicio
        FROM servicios s
        INNER JOIN configuracion_horarios ch
            ON ch.id_servicio = s.id_servicio
        WHERE s.id_departamento = :id_departamento
          AND s.activo = TRUE
          AND ch.activo = TRUE
        ORDER BY s.id_servicio
        LIMIT 1
    ");

    $stmt->execute([
        ':id_departamento' => $idDepartamento
    ]);

    $idReferencia = $stmt->fetchColumn();

    return $idReferencia !== false
        ? (int) $idReferencia
        : $idServicio;
}


/* =========================================================
   OBTENER CONFIGURACIÓN DE HORARIO
   ========================================================= */

function obtenerConfiguracionHorario(
    PDO $conexion,
    int $idServicio,
    int $diaSemana
): ?array {
    $stmt = $conexion->prepare("
        SELECT
            hora_inicio,
            hora_fin,
            intervalo_minutos,
            activo,
            tiene_almuerzo,
            almuerzo_inicio,
            almuerzo_fin
        FROM configuracion_horarios
        WHERE id_servicio = :id_servicio
          AND dia_semana = :dia_semana
        LIMIT 1
    ");

    $stmt->execute([
        ':id_servicio' => $idServicio,
        ':dia_semana' => $diaSemana
    ]);

    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    return $config ?: null;
}


/* =========================================================
   OBTENER EXCEPCIÓN DE HORARIO
   ========================================================= */

function obtenerExcepcion(
    PDO $conexion,
    int $idServicio,
    string $fecha
): ?array {
    $stmt = $conexion->prepare("
        SELECT
            es_bloqueo_total,
            hora_inicio,
            hora_fin,
            motivo
        FROM excepciones_horarios
        WHERE id_servicio = :id_servicio
          AND fecha = :fecha
        LIMIT 1
    ");

    $stmt->execute([
        ':id_servicio' => $idServicio,
        ':fecha' => $fecha
    ]);

    $excepcion = $stmt->fetch(PDO::FETCH_ASSOC);

    return $excepcion ?: null;
}


/* =========================================================
   BLOQUEO TRANSACCIONAL DE DISPONIBILIDAD
   ========================================================= */

function bloquearDisponibilidad(
    PDO $conexion,
    array $servicio,
    string $fecha,
    string $hora
): void {
    $idServicio = (int) $servicio['id_servicio'];
    $idDepartamento = (int) $servicio['id_departamento'];

    if (esVerdadero($servicio['horarios_unificados'] ?? false)) {
        $clave =
            'departamento-unificado|' .
            $idDepartamento . '|' .
            $fecha . '|' .
            $hora;
    } else {
        $idsCompartidos = obtenerServiciosDisponibilidad(
            $conexion,
            $servicio
        );

        if (count($idsCompartidos) > 1) {
            $clave =
                'grupo-unificado|' .
                $idDepartamento . '|' .
                $fecha . '|' .
                $hora;
        } else {
            $clave =
                'servicio-individual|' .
                $idServicio . '|' .
                $fecha . '|' .
                $hora;
        }
    }

    $stmt = $conexion->prepare("
        SELECT pg_advisory_xact_lock(hashtext(:clave))
    ");

    $stmt->execute([
        ':clave' => $clave
    ]);
}


/* =========================================================
   OBTENER HORAS OCUPADAS
   ========================================================= */

function obtenerHorasOcupadas(
    PDO $conexion,
    array $idsServicios,
    string $fecha
): array {
    if (empty($idsServicios)) {
        return [];
    }

    $placeholders = [];
    $parametros = [];

    foreach ($idsServicios as $indice => $idServicio) {
        $param = ':servicio_' . $indice;
        $placeholders[] = $param;
        $parametros[$param] = (int) $idServicio;
    }

    $sql = "
        SELECT DISTINCT LEFT(h.hora::text, 5) AS hora
        FROM horarios h
        LEFT JOIN reservas r
            ON r.id_horario = h.id_horario
        WHERE h.id_servicio IN (
            " . implode(', ', $placeholders) . "
        )
          AND h.fecha = :fecha
          AND (
                LOWER(COALESCE(h.estado, '')) = 'bloqueado'
                OR LOWER(COALESCE(r.estado, ''))
                    IN ('confirmada', 'reagendada')
          )
    ";

    $stmt = $conexion->prepare($sql);

    foreach ($parametros as $parametro => $valor) {
        $stmt->bindValue(
            $parametro,
            $valor,
            PDO::PARAM_INT
        );
    }

    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();

    $ocupadas = [];

    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ocupadas[] = substr((string) $fila['hora'], 0, 5);
    }

    return array_values(array_unique($ocupadas));
}


/* =========================================================
   VALIDAR INTERVALO Y ALMUERZO
   ========================================================= */

function validarHoraConfigurada(
    array $config,
    ?array $excepcion,
    string $fecha,
    string $hora
): ?string {
    if (!$config || !esVerdadero($config['activo'] ?? false)) {
        return 'No existe una configuración de atención activa para ese día.';
    }

    if (
        $excepcion &&
        esVerdadero($excepcion['es_bloqueo_total'] ?? false)
    ) {
        return $excepcion['motivo']
            ?: 'La fecha seleccionada está bloqueada.';
    }

    $horaInicio = (
        $excepcion &&
        !empty($excepcion['hora_inicio'])
    )
        ? substr((string) $excepcion['hora_inicio'], 0, 5)
        : substr((string) $config['hora_inicio'], 0, 5);

    $horaFin = (
        $excepcion &&
        !empty($excepcion['hora_fin'])
    )
        ? substr((string) $excepcion['hora_fin'], 0, 5)
        : substr((string) $config['hora_fin'], 0, 5);

    if (!validarHora($horaInicio) || !validarHora($horaFin)) {
        return 'La configuración del horario no es válida.';
    }

    $intervalo = (int) ($config['intervalo_minutos'] ?? 0);

    if ($intervalo <= 0) {
        return 'El intervalo de atención no es válido.';
    }

    $inicioMinutos = (
        ((int) substr($horaInicio, 0, 2)) * 60
    ) + (int) substr($horaInicio, 3, 2);

    $finMinutos = (
        ((int) substr($horaFin, 0, 2)) * 60
    ) + (int) substr($horaFin, 3, 2);

    $horaMinutos = (
        ((int) substr($hora, 0, 2)) * 60
    ) + (int) substr($hora, 3, 2);

    if (
        $horaMinutos < $inicioMinutos ||
        $horaMinutos >= $finMinutos
    ) {
        return 'La hora está fuera del horario de atención configurado.';
    }

    if (($horaMinutos - $inicioMinutos) % $intervalo !== 0) {
        return 'La hora no corresponde al intervalo configurado.';
    }

    if (
        esVerdadero($config['tiene_almuerzo'] ?? false) &&
        !empty($config['almuerzo_inicio']) &&
        !empty($config['almuerzo_fin'])
    ) {
        $almuerzoInicio = substr(
            (string) $config['almuerzo_inicio'],
            0,
            5
        );

        $almuerzoFin = substr(
            (string) $config['almuerzo_fin'],
            0,
            5
        );

        if (validarHora($almuerzoInicio) && validarHora($almuerzoFin)) {
            $inicioAlmuerzo = (
                ((int) substr($almuerzoInicio, 0, 2)) * 60
            ) + (int) substr($almuerzoInicio, 3, 2);

            $finAlmuerzo = (
                ((int) substr($almuerzoFin, 0, 2)) * 60
            ) + (int) substr($almuerzoFin, 3, 2);

            if (
                $horaMinutos >= $inicioAlmuerzo &&
                $horaMinutos < $finAlmuerzo
            ) {
                return 'La hora seleccionada corresponde al horario de almuerzo.';
            }
        }
    }

    return null;
}


/* =========================================================
   DATOS Y ENVÍO DE CORREOS
   ========================================================= */

/**
 * Envía el correo del paciente y las notificaciones a los funcionarios.
 * Se llama después del COMMIT.
 */
function notificarModificacionReserva(
    PDO $conexion,
    array $reserva,
    array $servicio,
    string $fechaAnterior,
    string $horaAnterior,
    string $fechaNueva,
    string $horaNueva
): void {
    try {
        enviarCorreoModificacionReserva(
            $reserva,
            $servicio,
            $fechaAnterior,
            $horaAnterior,
            $fechaNueva,
            $horaNueva
        );
    } catch (Throwable $e) {
        error_log(
            'Error correo paciente por modificación de reserva #' .
            ($reserva['id_reserva'] ?? 'N/A') . ': ' .
            $e->getMessage()
        );
    }

    try {
        enviarCorreoFuncionariosEventoReservaCESFAM(
            $conexion,
            $reserva,
            $servicio,
            'modificacion',
            [
                ['Fecha anterior', fechaMostrarCorreoCESFAM($fechaAnterior)],
                ['Hora anterior', horaMostrarCorreoCESFAM($horaAnterior)],
                ['Nueva fecha', fechaMostrarCorreoCESFAM($fechaNueva)],
                ['Nueva hora', horaMostrarCorreoCESFAM($horaNueva)]
            ]
        );
    } catch (Throwable $e) {
        error_log(
            'Error correo funcionarios por modificación de reserva #' .
            ($reserva['id_reserva'] ?? 'N/A') . ': ' .
            $e->getMessage()
        );
    }
}


/**
 * Envía el correo de cancelación al paciente y a los funcionarios.
 * Se llama después del COMMIT.
 */
function notificarCancelacionReserva(
    PDO $conexion,
    array $reserva,
    array $servicio,
    string $fecha,
    string $hora
): void {
    try {
        enviarCorreoCancelacionReserva(
            $reserva,
            $servicio,
            $fecha,
            $hora
        );
    } catch (Throwable $e) {
        error_log(
            'Error correo paciente por cancelación de reserva #' .
            ($reserva['id_reserva'] ?? 'N/A') . ': ' .
            $e->getMessage()
        );
    }

    try {
        enviarCorreoFuncionariosEventoReservaCESFAM(
            $conexion,
            $reserva,
            $servicio,
            'cancelacion',
            [
                ['Fecha cancelada', fechaMostrarCorreoCESFAM($fecha)],
                ['Hora cancelada', horaMostrarCorreoCESFAM($hora)]
            ]
        );
    } catch (Throwable $e) {
        error_log(
            'Error correo funcionarios por cancelación de reserva #' .
            ($reserva['id_reserva'] ?? 'N/A') . ': ' .
            $e->getMessage()
        );
    }
}


/* =========================================================
   IDENTIFICACIÓN Y ACCIÓN
   ========================================================= */

$accion = trim((string) ($_POST['accion'] ?? ''));

$accionesPermitidas = [
    'horarios',
    'obtener_reserva',
    'crear',
    'confirmar',
    'reagendar',
    'cancelar'
];

if (!in_array($accion, $accionesPermitidas, true)) {
    responder(false, 'Acción no válida.', [], 400);
}


/* =========================================================
   OBTENER RESERVA
   ========================================================= */

if ($accion === 'obtener_reserva') {
    $idReserva = (int) ($_POST['id_reserva'] ?? 0);

    if ($idReserva <= 0) {
        responder(false, 'Reserva no válida.', [], 400);
    }

    try {
        $stmt = $conexion->prepare("
            SELECT
                r.id_reserva,
                r.id_horario,
                r.id_servicio,
                r.nombre,
                r.rut,
                r.tipo_identificacion,
                r.telefono,
                r.correo,
                r.estado,
                r.fecha_reserva,
                r.fecha_actualizacion,
                s.nombre AS servicio_nombre,
                h.fecha AS fecha_horario,
                h.hora AS hora_horario,
                h.estado AS estado_horario
            FROM reservas r
            INNER JOIN horarios h
                ON h.id_horario = r.id_horario
            INNER JOIN servicios s
                ON s.id_servicio = r.id_servicio
            WHERE r.id_reserva = :id_reserva
            LIMIT 1
        ");

        $stmt->execute([
            ':id_reserva' => $idReserva
        ]);

        $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reserva) {
            responder(false, 'La reserva no existe.', [], 404);
        }

        $reserva['id_reserva'] = (int) $reserva['id_reserva'];
        $reserva['id_horario'] = (int) $reserva['id_horario'];
        $reserva['id_servicio'] = (int) $reserva['id_servicio'];
        $reserva['fecha_horario'] = (string) $reserva['fecha_horario'];
        $reserva['hora_horario'] = substr(
            (string) $reserva['hora_horario'],
            0,
            5
        );
        $reserva['correo'] = (string) ($reserva['correo'] ?? '');

        responder(
            true,
            'Reserva obtenida correctamente.',
            ['reserva' => $reserva]
        );

    } catch (Throwable $e) {
        error_log('Error obtener reserva: ' . $e->getMessage());

        responder(
            false,
            'No fue posible obtener los datos de la reserva.',
            [],
            500
        );
    }
}


/* =========================================================
   CONSULTAR HORARIOS
   ========================================================= */

if ($accion === 'horarios') {
    $idServicio = (int) ($_POST['id_servicio'] ?? 0);
    $fecha = trim((string) ($_POST['fecha'] ?? ''));

    if ($idServicio <= 0) {
        responder(false, 'Debe seleccionar un servicio.', [], 400);
    }

    if (!validarFecha($fecha)) {
        responder(false, 'La fecha no es válida.', [], 400);
    }

    try {
        $servicio = obtenerServicio($conexion, $idServicio);

        if (
            !$servicio ||
            !esVerdadero($servicio['servicio_activo'] ?? false)
        ) {
            responder(
                false,
                'El servicio no existe o está desactivado.',
                [],
                404
            );
        }

        if (!esVerdadero($servicio['departamento_activo'] ?? false)) {
            responder(false, 'El departamento está desactivado.', [], 400);
        }

        $fechaObjeto = new DateTime($fecha);
        $diaSemana = (int) $fechaObjeto->format('N');

        if ($diaSemana >= 6) {
            responder(
                true,
                'No hay atención durante el fin de semana.',
                ['horarios' => []]
            );
        }

        $idServicioReferencia = obtenerServicioReferencia(
            $conexion,
            $servicio
        );

        $config = obtenerConfiguracionHorario(
            $conexion,
            $idServicioReferencia,
            $diaSemana
        );

        if (!$config || !esVerdadero($config['activo'] ?? false)) {
            responder(
                true,
                'No hay atención configurada para este día.',
                ['horarios' => []]
            );
        }

        $excepcion = obtenerExcepcion(
            $conexion,
            $idServicioReferencia,
            $fecha
        );

        if (
            $excepcion &&
            esVerdadero($excepcion['es_bloqueo_total'] ?? false)
        ) {
            responder(
                true,
                $excepcion['motivo'] ?: 'El día está bloqueado.',
                ['horarios' => []]
            );
        }

        $horaInicio = (
            $excepcion &&
            !empty($excepcion['hora_inicio'])
        )
            ? substr((string) $excepcion['hora_inicio'], 0, 5)
            : substr((string) $config['hora_inicio'], 0, 5);

        $horaFin = (
            $excepcion &&
            !empty($excepcion['hora_fin'])
        )
            ? substr((string) $excepcion['hora_fin'], 0, 5)
            : substr((string) $config['hora_fin'], 0, 5);

        $intervalo = (int) $config['intervalo_minutos'];

        if ($intervalo <= 0) {
            $intervalo = 30;
        }

        if (!validarHora($horaInicio) || !validarHora($horaFin)) {
            responder(
                false,
                'La configuración de horarios no es válida.',
                [],
                500
            );
        }

        $idsDisponibilidad = obtenerServiciosDisponibilidad(
            $conexion,
            $servicio
        );

        $horasOcupadas = obtenerHorasOcupadas(
            $conexion,
            $idsDisponibilidad,
            $fecha
        );

        $stmtExistentes = $conexion->prepare("
            SELECT
                id_horario,
                LEFT(hora::text, 5) AS hora,
                estado
            FROM horarios
            WHERE id_servicio = :id_servicio
              AND fecha = :fecha
        ");

        $stmtExistentes->execute([
            ':id_servicio' => $idServicio,
            ':fecha' => $fecha
        ]);

        $existentes = [];

        while ($fila = $stmtExistentes->fetch(PDO::FETCH_ASSOC)) {
            $existentes[$fila['hora']] = $fila;
        }

        $ahora = new DateTime(
            'now',
            new DateTimeZone('America/Santiago')
        );

        $horaMinima = null;

        if ($fecha === $ahora->format('Y-m-d')) {
            $horaMinima = clone $ahora;
            $horaMinima->modify('+1 hour');

            $minutos = (int) $horaMinima->format('i');

            if ($minutos > 0 && $minutos <= 30) {
                $horaMinima->setTime(
                    (int) $horaMinima->format('H'),
                    30,
                    0
                );
            } elseif ($minutos > 30) {
                $horaMinima->modify('+1 hour');
                $horaMinima->setTime(
                    (int) $horaMinima->format('H'),
                    0,
                    0
                );
            }
        }

        $cursor = new DateTime($fecha . ' ' . $horaInicio);
        $fin = new DateTime($fecha . ' ' . $horaFin);
        $horarios = [];

        while ($cursor < $fin) {
            if ($horaMinima !== null && $cursor < $horaMinima) {
                $cursor->modify("+{$intervalo} minutes");
                continue;
            }

            $hora = $cursor->format('H:i');
            $esAlmuerzo = false;

            if (
                esVerdadero($config['tiene_almuerzo'] ?? false) &&
                !empty($config['almuerzo_inicio']) &&
                !empty($config['almuerzo_fin'])
            ) {
                $inicioAlmuerzo = new DateTime(
                    $fecha . ' ' . substr(
                        (string) $config['almuerzo_inicio'],
                        0,
                        5
                    )
                );

                $finAlmuerzo = new DateTime(
                    $fecha . ' ' . substr(
                        (string) $config['almuerzo_fin'],
                        0,
                        5
                    )
                );

                if ($cursor >= $inicioAlmuerzo && $cursor < $finAlmuerzo) {
                    $esAlmuerzo = true;
                }
            }

            if (!$esAlmuerzo) {
                $registro = $existentes[$hora] ?? null;

                $ocupado = in_array(
                    $hora,
                    $horasOcupadas,
                    true
                );

                if (
                    $registro &&
                    strtolower(trim((string) $registro['estado'])) === 'bloqueado'
                ) {
                    $ocupado = true;
                }

                $horarios[] = [
                    'id_horario' => $registro
                        ? (int) $registro['id_horario']
                        : null,
                    'hora' => $hora,
                    'disponible' => !$ocupado
                ];
            }

            $cursor->modify("+{$intervalo} minutes");
        }

        responder(
            true,
            'Horarios consultados.',
            [
                'horarios' => $horarios,
                'horarios_unificados' => esVerdadero(
                    $servicio['horarios_unificados'] ?? false
                )
            ]
        );

    } catch (Throwable $e) {
        error_log('Error horarios: ' . $e->getMessage());

        responder(
            false,
            'No fue posible consultar los horarios.',
            [],
            500
        );
    }
}


/* =========================================================
   CREAR RESERVA DESDE FUNCIONARIOS
   ========================================================= */

if ($accion === 'crear') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $rut = trim((string) ($_POST['rut'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $idServicio = (int) ($_POST['id_servicio'] ?? 0);
    $fecha = trim((string) ($_POST['fecha'] ?? ''));
    $hora = trim((string) ($_POST['hora'] ?? ''));

    if (
        $nombre === '' ||
        $rut === '' ||
        $telefono === '' ||
        $idServicio <= 0 ||
        $fecha === '' ||
        $hora === ''
    ) {
        responder(false, 'Complete todos los campos obligatorios.', [], 400);
    }

    if (!rutEsValido($rut)) {
        responder(false, 'El RUT ingresado no es válido.', [], 400);
    }

    if (!validarFecha($fecha) || !validarHora($hora)) {
        responder(false, 'La fecha o el horario no son válidos.', [], 400);
    }

    if (
        $correo !== '' &&
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {
        responder(false, 'El correo electrónico no es válido.', [], 400);
    }

    try {
        $conexion->beginTransaction();

        $servicio = obtenerServicio($conexion, $idServicio);

        if (
            !$servicio ||
            !esVerdadero($servicio['servicio_activo'] ?? false)
        ) {
            $conexion->rollBack();

            responder(
                false,
                'El servicio seleccionado no existe o está desactivado.',
                [],
                404
            );
        }

        if (!esVerdadero($servicio['departamento_activo'] ?? false)) {
            $conexion->rollBack();

            responder(false, 'El departamento está desactivado.', [], 400);
        }

        $fechaObjeto = new DateTime($fecha);
        $diaSemana = (int) $fechaObjeto->format('N');

        if ($diaSemana >= 6) {
            $conexion->rollBack();

            responder(
                false,
                'No se pueden crear reservas durante el fin de semana.',
                [],
                400
            );
        }

        bloquearDisponibilidad(
            $conexion,
            $servicio,
            $fecha,
            $hora
        );

        $idReferencia = obtenerServicioReferencia(
            $conexion,
            $servicio
        );

        $config = obtenerConfiguracionHorario(
            $conexion,
            $idReferencia,
            $diaSemana
        );

        if (!$config || !esVerdadero($config['activo'] ?? false)) {
            $conexion->rollBack();

            responder(
                false,
                'No existe atención configurada para ese día.',
                [],
                409
            );
        }

        $excepcion = obtenerExcepcion(
            $conexion,
            $idReferencia,
            $fecha
        );

        $errorHora = validarHoraConfigurada(
            $config,
            $excepcion,
            $fecha,
            $hora
        );

        if ($errorHora !== null) {
            $conexion->rollBack();

            responder(false, $errorHora, [], 409);
        }

        $ahora = new DateTime(
            'now',
            new DateTimeZone('America/Santiago')
        );

        if (
            $fecha < $ahora->format('Y-m-d') ||
            (
                $fecha === $ahora->format('Y-m-d') &&
                $hora <= $ahora->format('H:i')
            )
        ) {
            $conexion->rollBack();

            responder(false, 'La hora seleccionada ya pasó.', [], 400);
        }

        $idsDisponibilidad = obtenerServiciosDisponibilidad(
            $conexion,
            $servicio
        );

        $horasOcupadas = obtenerHorasOcupadas(
            $conexion,
            $idsDisponibilidad,
            $fecha
        );

        if (in_array($hora, $horasOcupadas, true)) {
            $conexion->rollBack();

            responder(
                false,
                'La hora seleccionada ya no está disponible.',
                [],
                409
            );
        }

        $stmtHorario = $conexion->prepare("
            SELECT id_horario, estado
            FROM horarios
            WHERE id_servicio = :id_servicio
              AND fecha = :fecha
              AND hora = :hora
            FOR UPDATE
        ");

        $stmtHorario->execute([
            ':id_servicio' => $idServicio,
            ':fecha' => $fecha,
            ':hora' => $hora
        ]);

        $horario = $stmtHorario->fetch(PDO::FETCH_ASSOC);

        if ($horario) {
            if (
                strtolower(trim((string) $horario['estado'])) === 'bloqueado'
            ) {
                $conexion->rollBack();

                responder(
                    false,
                    'El horario seleccionado está bloqueado.',
                    [],
                    409
                );
            }

            $idHorario = (int) $horario['id_horario'];
        } else {
            $stmtCrearHorario = $conexion->prepare("
                INSERT INTO horarios (
                    id_servicio,
                    fecha,
                    hora,
                    estado,
                    observacion
                )
                VALUES (
                    :id_servicio,
                    :fecha,
                    :hora,
                    'disponible',
                    NULL
                )
                RETURNING id_horario
            ");

            $stmtCrearHorario->execute([
                ':id_servicio' => $idServicio,
                ':fecha' => $fecha,
                ':hora' => $hora
            ]);

            $idHorario = (int) $stmtCrearHorario->fetchColumn();
        }

        $stmtReserva = $conexion->prepare("
            SELECT id_reserva
            FROM reservas
            WHERE id_horario = :id_horario
              AND LOWER(estado) IN ('confirmada', 'reagendada')
            FOR UPDATE
        ");

        $stmtReserva->execute([
            ':id_horario' => $idHorario
        ]);

        if ($stmtReserva->fetch()) {
            $conexion->rollBack();

            responder(
                false,
                'La hora seleccionada ya no está disponible.',
                [],
                409
            );
        }

        $stmtInsert = $conexion->prepare("
            INSERT INTO reservas (
                id_horario,
                id_servicio,
                nombre,
                rut,
                correo,
                telefono,
                estado,
                fecha_reserva,
                fecha_actualizacion,
                tipo_identificacion
            )
            VALUES (
                :id_horario,
                :id_servicio,
                :nombre,
                :rut,
                :correo,
                :telefono,
                'confirmada',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP,
                'RUT'
            )
            RETURNING id_reserva
        ");

        $stmtInsert->execute([
            ':id_horario' => $idHorario,
            ':id_servicio' => $idServicio,
            ':nombre' => $nombre,
            ':rut' => $rut,
            ':correo' => $correo !== '' ? $correo : null,
            ':telefono' => $telefono
        ]);

        $nuevoId = (int) $stmtInsert->fetchColumn();

        $stmtBloquear = $conexion->prepare("
            UPDATE horarios
            SET estado = 'bloqueado'
            WHERE id_horario = :id_horario
        ");

        $stmtBloquear->execute([
            ':id_horario' => $idHorario
        ]);

        $conexion->commit();

        responder(
            true,
            'Reserva creada correctamente.',
            ['id_reserva' => $nuevoId]
        );

    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log('Error crear reserva: ' . $e->getMessage());

        responder(false, 'No fue posible crear la reserva.', [], 500);
    }
}


/* =========================================================
   CONFIRMAR RESERVA
   ========================================================= */

if ($accion === 'confirmar') {
    $idReserva = (int) ($_POST['id_reserva'] ?? 0);

    if ($idReserva <= 0) {
        responder(false, 'Reserva no válida.', [], 400);
    }

    try {
        $stmt = $conexion->prepare("
            UPDATE reservas
            SET
                estado = 'confirmada',
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id_reserva = :id_reserva
              AND estado <> 'cancelada'
            RETURNING id_reserva
        ");

        $stmt->execute([
            ':id_reserva' => $idReserva
        ]);

        if (!$stmt->fetchColumn()) {
            responder(
                false,
                'La reserva no existe o no puede ser confirmada.',
                [],
                404
            );
        }

        responder(true, 'Reserva confirmada correctamente.');

    } catch (Throwable $e) {
        error_log('Error confirmar reserva: ' . $e->getMessage());

        responder(false, 'No fue posible confirmar la reserva.', [], 500);
    }
}


/* =========================================================
   REAGENDAR / MODIFICAR RESERVA
   ========================================================= */

if ($accion === 'reagendar') {
    $idReserva = (int) ($_POST['id_reserva'] ?? 0);

    $fecha = trim(
        (string) (
            $_POST['fecha_nueva'] ??
            $_POST['fecha'] ??
            ''
        )
    );

    $hora = trim(
        (string) (
            $_POST['hora_nueva'] ??
            $_POST['hora'] ??
            ''
        )
    );

    if ($idReserva <= 0) {
        responder(false, 'Reserva no válida.', [], 400);
    }

    if (!validarFecha($fecha)) {
        responder(false, 'La nueva fecha no es válida.', [], 400);
    }

    if (!validarHora($hora)) {
        responder(false, 'La nueva hora no es válida.', [], 400);
    }

    try {
        $conexion->beginTransaction();

        /*
         * Obtener y bloquear la reserva actual.
         * Se incluyen los datos personales necesarios para el correo.
         */

        $stmtReserva = $conexion->prepare("
            SELECT
                r.id_reserva,
                r.id_servicio,
                r.id_horario,
                r.nombre,
                r.rut,
                r.correo,
                r.telefono,
                r.tipo_identificacion,
                r.estado,
                h.fecha AS fecha_actual,
                h.hora AS hora_actual
            FROM reservas r
            INNER JOIN horarios h
                ON h.id_horario = r.id_horario
            WHERE r.id_reserva = :id_reserva
            FOR UPDATE OF r
        ");

        $stmtReserva->execute([
            ':id_reserva' => $idReserva
        ]);

        $reservaActual = $stmtReserva->fetch(PDO::FETCH_ASSOC);

        if (!$reservaActual) {
            $conexion->rollBack();

            responder(false, 'La reserva no existe.', [], 404);
        }

        if (
            strtolower(trim((string) $reservaActual['estado'])) ===
            'cancelada'
        ) {
            $conexion->rollBack();

            responder(
                false,
                'Una reserva cancelada no puede modificarse.',
                [],
                400
            );
        }

        /*
         * Conservar fecha y hora originales para el correo.
         */

        $fechaAnterior = substr(
            (string) $reservaActual['fecha_actual'],
            0,
            10
        );

        $horaAnterior = substr(
            (string) $reservaActual['hora_actual'],
            0,
            5
        );

        $servicio = obtenerServicio(
            $conexion,
            (int) $reservaActual['id_servicio']
        );

        if (!$servicio) {
            $conexion->rollBack();

            responder(
                false,
                'El servicio de la reserva no existe.',
                [],
                404
            );
        }

        bloquearDisponibilidad(
            $conexion,
            $servicio,
            $fecha,
            $hora
        );

        $fechaObjeto = new DateTime($fecha);
        $diaSemana = (int) $fechaObjeto->format('N');

        if ($diaSemana >= 6) {
            $conexion->rollBack();

            responder(
                false,
                'No se puede modificar una reserva para un fin de semana.',
                [],
                400
            );
        }

        $zonaHoraria = new DateTimeZone('America/Santiago');

        $hoy = new DateTime('today', $zonaHoraria);
        $fechaComparar = new DateTime($fecha, $zonaHoraria);

        if ($fechaComparar < $hoy) {
            $conexion->rollBack();

            responder(
                false,
                'No se puede modificar una reserva hacia una fecha que ya pasó.',
                [],
                400
            );
        }

        $ahora = new DateTime('now', $zonaHoraria);

        if (
            $fecha === $ahora->format('Y-m-d') &&
            $hora <= $ahora->format('H:i')
        ) {
            $conexion->rollBack();

            responder(
                false,
                'La nueva hora ya pasó. Seleccione una hora posterior.',
                [],
                400
            );
        }

        $idReferencia = obtenerServicioReferencia(
            $conexion,
            $servicio
        );

        $config = obtenerConfiguracionHorario(
            $conexion,
            $idReferencia,
            $diaSemana
        );

        $excepcion = obtenerExcepcion(
            $conexion,
            $idReferencia,
            $fecha
        );

        $errorHora = validarHoraConfigurada(
            $config,
            $excepcion,
            $fecha,
            $hora
        );

        if ($errorHora !== null) {
            $conexion->rollBack();

            responder(false, $errorHora, [], 409);
        }

        /*
         * Evitar modificar a la misma fecha y hora.
         */

        if (
            $fechaAnterior === $fecha &&
            $horaAnterior === $hora
        ) {
            $conexion->rollBack();

            responder(
                false,
                'Debe seleccionar una fecha u hora diferente.',
                [],
                400
            );
        }

        /*
         * Comprobar la disponibilidad compartida.
         */

        $idsDisponibilidad = obtenerServiciosDisponibilidad(
            $conexion,
            $servicio
        );

        $placeholders = [];
        $parametros = [];

        foreach ($idsDisponibilidad as $indice => $idServicioCompartido) {
            $param = ':servicio_' . $indice;

            $placeholders[] = $param;
            $parametros[$param] = (int) $idServicioCompartido;
        }

        $sqlConflicto = "
            SELECT r.id_reserva
            FROM reservas r
            INNER JOIN horarios h
                ON h.id_horario = r.id_horario
            WHERE h.id_servicio IN (
                " . implode(', ', $placeholders) . "
            )
              AND h.fecha = :fecha
              AND LEFT(h.hora::text, 5) = :hora
              AND r.id_reserva <> :id_reserva
              AND LOWER(r.estado) IN ('confirmada', 'reagendada')
            LIMIT 1
        ";

        $stmtConflicto = $conexion->prepare($sqlConflicto);

        foreach ($parametros as $parametro => $valor) {
            $stmtConflicto->bindValue(
                $parametro,
                $valor,
                PDO::PARAM_INT
            );
        }

        $stmtConflicto->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmtConflicto->bindValue(':hora', $hora, PDO::PARAM_STR);
        $stmtConflicto->bindValue(
            ':id_reserva',
            $idReserva,
            PDO::PARAM_INT
        );

        $stmtConflicto->execute();

        if ($stmtConflicto->fetch(PDO::FETCH_ASSOC)) {
            $conexion->rollBack();

            responder(
                false,
                'El nuevo horario ya está ocupado.',
                [],
                409
            );
        }

        /*
         * Buscar el horario nuevo para el mismo servicio.
         */

        $stmtHorario = $conexion->prepare("
            SELECT id_horario, estado
            FROM horarios
            WHERE id_servicio = :id_servicio
              AND fecha = :fecha
              AND LEFT(hora::text, 5) = :hora
            FOR UPDATE
        ");

        $stmtHorario->execute([
            ':id_servicio' => (int) $reservaActual['id_servicio'],
            ':fecha' => $fecha,
            ':hora' => $hora
        ]);

        $nuevoHorario = $stmtHorario->fetch(PDO::FETCH_ASSOC);

        if ($nuevoHorario) {
            if (
                strtolower(trim((string) $nuevoHorario['estado'])) !==
                'disponible'
            ) {
                $conexion->rollBack();

                responder(
                    false,
                    'El nuevo horario no está disponible.',
                    [],
                    409
                );
            }

            $idNuevoHorario = (int) $nuevoHorario['id_horario'];

        } else {
            $stmtCrear = $conexion->prepare("
                INSERT INTO horarios (
                    id_servicio,
                    fecha,
                    hora,
                    estado,
                    observacion
                )
                VALUES (
                    :id_servicio,
                    :fecha,
                    :hora,
                    'disponible',
                    NULL
                )
                RETURNING id_horario
            ");

            $stmtCrear->execute([
                ':id_servicio' => (int) $reservaActual['id_servicio'],
                ':fecha' => $fecha,
                ':hora' => $hora
            ]);

            $idNuevoHorario = (int) $stmtCrear->fetchColumn();
        }

        /*
         * Liberar el horario anterior.
         */

        $stmtLiberar = $conexion->prepare("
            UPDATE horarios
            SET estado = 'disponible'
            WHERE id_horario = :id_horario
        ");

        $stmtLiberar->execute([
            ':id_horario' => (int) $reservaActual['id_horario']
        ]);

        /*
         * Bloquear el horario nuevo.
         */

        $stmtBloquear = $conexion->prepare("
            UPDATE horarios
            SET estado = 'bloqueado'
            WHERE id_horario = :id_horario
              AND estado = 'disponible'
        ");

        $stmtBloquear->execute([
            ':id_horario' => $idNuevoHorario
        ]);

        if ($stmtBloquear->rowCount() === 0) {
            $conexion->rollBack();

            responder(
                false,
                'El nuevo horario dejó de estar disponible.',
                [],
                409
            );
        }

        /*
         * Guardar la modificación.
         */

        $stmtActualizar = $conexion->prepare("
            UPDATE reservas
            SET
                id_horario = :id_horario,
                estado = 'reagendada',
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id_reserva = :id_reserva
        ");

        $stmtActualizar->execute([
            ':id_horario' => $idNuevoHorario,
            ':id_reserva' => $idReserva
        ]);

        if ($stmtActualizar->rowCount() === 0) {
            $conexion->rollBack();

            responder(
                false,
                'No fue posible actualizar la reserva.',
                [],
                500
            );
        }

        /*
         * Confirmar primero PostgreSQL.
         */

        $conexion->commit();

        /*
         * Correo al paciente y funcionarios.
         * Si falla SMTP, la reserva permanece modificada.
         */

        notificarModificacionReserva(
            $conexion,
            $reservaActual,
            $servicio,
            $fechaAnterior,
            $horaAnterior,
            $fecha,
            $hora
        );

        responder(
            true,
            'Reserva modificada correctamente.',
            [
                'id_reserva' => $idReserva,
                'fecha' => $fecha,
                'hora' => $hora
            ]
        );

    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error modificar reserva #' .
            $idReserva . ': ' .
            $e->getMessage()
        );

        responder(
            false,
            'No fue posible modificar la reserva.',
            [],
            500
        );
    }
}


/* =========================================================
   CANCELAR RESERVA
   ========================================================= */

if ($accion === 'cancelar') {
    $idReserva = (int) ($_POST['id_reserva'] ?? 0);

    if ($idReserva <= 0) {
        responder(false, 'Reserva no válida.', [], 400);
    }

    try {
        $conexion->beginTransaction();

        /*
         * Obtener los datos completos antes de cancelar.
         */

        $stmtReserva = $conexion->prepare("
            SELECT
                r.id_reserva,
                r.id_horario,
                r.id_servicio,
                r.nombre,
                r.rut,
                r.correo,
                r.telefono,
                r.tipo_identificacion,
                r.estado,
                h.fecha AS fecha_cancelada,
                h.hora AS hora_cancelada
            FROM reservas r
            INNER JOIN horarios h
                ON h.id_horario = r.id_horario
            WHERE r.id_reserva = :id_reserva
            FOR UPDATE OF r
        ");

        $stmtReserva->execute([
            ':id_reserva' => $idReserva
        ]);

        $reservaCancelada = $stmtReserva->fetch(PDO::FETCH_ASSOC);

        if (!$reservaCancelada) {
            $conexion->rollBack();

            responder(false, 'La reserva no existe.', [], 404);
        }

        if (
            strtolower(trim((string) $reservaCancelada['estado'])) ===
            'cancelada'
        ) {
            $conexion->rollBack();

            responder(
                false,
                'La reserva ya está cancelada.',
                [],
                409
            );
        }

        $servicio = obtenerServicio(
            $conexion,
            (int) $reservaCancelada['id_servicio']
        );

        if (!$servicio) {
            $conexion->rollBack();

            responder(
                false,
                'No se encontró el servicio de la reserva.',
                [],
                404
            );
        }

        $fechaCancelada = substr(
            (string) $reservaCancelada['fecha_cancelada'],
            0,
            10
        );

        $horaCancelada = substr(
            (string) $reservaCancelada['hora_cancelada'],
            0,
            5
        );

        /*
         * Cambiar el estado de la reserva.
         */

        $stmtCancelar = $conexion->prepare("
            UPDATE reservas
            SET
                estado = 'cancelada',
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id_reserva = :id_reserva
              AND estado <> 'cancelada'
            RETURNING id_reserva
        ");

        $stmtCancelar->execute([
            ':id_reserva' => $idReserva
        ]);

        if (!$stmtCancelar->fetchColumn()) {
            $conexion->rollBack();

            responder(
                false,
                'La reserva ya no se puede cancelar.',
                [],
                409
            );
        }

        /*
         * Liberar el horario.
         */

        $stmtLiberar = $conexion->prepare("
            UPDATE horarios
            SET estado = 'disponible'
            WHERE id_horario = :id_horario
        ");

        $stmtLiberar->execute([
            ':id_horario' => (int) $reservaCancelada['id_horario']
        ]);

        /*
         * Confirmar primero PostgreSQL.
         */

        $conexion->commit();

        /*
         * Correo al paciente y funcionarios.
         * Si falla SMTP, la reserva permanece cancelada.
         */

        notificarCancelacionReserva(
            $conexion,
            $reservaCancelada,
            $servicio,
            $fechaCancelada,
            $horaCancelada
        );

        responder(
            true,
            'Reserva cancelada correctamente.',
            [
                'id_reserva' => $idReserva
            ]
        );

    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error cancelar reserva #' .
            $idReserva . ': ' .
            $e->getMessage()
        );

        responder(
            false,
            'No fue posible cancelar la reserva.',
            [],
            500
        );
    }
}