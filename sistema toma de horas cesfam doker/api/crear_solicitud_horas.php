<?php

/*
 * =========================================================
 * API - CREAR SOLICITUD DE HORAS
 * CESFAM EDUARDO RAGGIO CATEMU
 * =========================================================
 *
 * REGLAS:
 *
 * - Solo admite solicitudes para fechas futuras o el día actual.
 * - Solo utiliza agendas publicadas y habilitadas.
 * - Solo considera cupos habilitados para la web.
 * - No genera horarios desde configuracion_horarios.
 * - No admite solicitudes cuando todavía hay cupos disponibles.
 * - Respeta la disponibilidad compartida entre servicios.
 * - Conserva cupos simultáneos de servicios independientes.
 * - Evita solicitudes pendientes duplicadas.
 * - Reconsulta la disponibilidad después de bloquear los cupos.
 *
 * Tabla utilizada:
 * solicitudes_horas
 *
 * Estado inicial:
 * pendiente
 *
 * Zona horaria:
 * America/Santiago
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexion.php';

date_default_timezone_set('America/Santiago');


/* =========================================================
   1. RESPUESTAS JSON
========================================================= */

function responderSolicitudHoras(
    int $codigo,
    array $datos
): void {

    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


function responderErrorSolicitudHoras(
    string $mensaje,
    int $codigo = 400,
    array $datosExtra = []
): void {

    responderSolicitudHoras(
        $codigo,
        array_merge(
            [
                'ok' => false,
                'mensaje' => $mensaje
            ],
            $datosExtra
        )
    );
}


/* =========================================================
   2. UTILIDADES
========================================================= */

function solicitudEsVerdadero($valor): bool
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


function solicitudLongitud(string $texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}


function solicitudNormalizarTexto($valor): string
{
    $valor = trim((string) $valor);

    return function_exists('mb_strtolower')
        ? mb_strtolower($valor, 'UTF-8')
        : strtolower($valor);
}


function solicitudCrearPlaceholders(
    array $ids,
    string $prefijo,
    array &$parametros
): string {

    $placeholders = [];

    $ids = array_values(
        array_unique(
            array_map('intval', $ids)
        )
    );

    foreach ($ids as $indice => $id) {

        $nombre = ':' . $prefijo . $indice;

        $placeholders[] = $nombre;

        $parametros[$nombre] = $id;
    }

    return !empty($placeholders)
        ? implode(', ', $placeholders)
        : 'NULL';
}


function solicitudValidarHora(string $hora): bool
{
    return preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $hora
    ) === 1;
}


/* =========================================================
   3. VALIDAR RUT CHILENO
========================================================= */

function validarRutSolicitudHoras(string $rut): array
{
    $rutLimpio = strtoupper(
        preg_replace('/[^0-9Kk]/', '', $rut)
    );

    if (
        strlen($rutLimpio) < 2 ||
        strlen($rutLimpio) > 9
    ) {

        return [
            'valido' => false,
            'rut' => ''
        ];
    }

    $numero = substr(
        $rutLimpio,
        0,
        -1
    );

    $digito = substr(
        $rutLimpio,
        -1
    );

    if (!ctype_digit($numero)) {

        return [
            'valido' => false,
            'rut' => ''
        ];
    }

    $suma = 0;

    $factor = 2;

    for (
        $i = strlen($numero) - 1;
        $i >= 0;
        $i--
    ) {

        $suma +=
            (int) $numero[$i] *
            $factor;

        $factor++;

        if ($factor > 7) {
            $factor = 2;
        }
    }

    $resultado = 11 - ($suma % 11);

    if ($resultado === 11) {

        $digitoCalculado = '0';

    } elseif ($resultado === 10) {

        $digitoCalculado = 'K';

    } else {

        $digitoCalculado = (string) $resultado;
    }

    if ($digito !== $digitoCalculado) {

        return [
            'valido' => false,
            'rut' => ''
        ];
    }

    return [
        'valido' => true,
        'rut' => $numero . '-' . $digito
    ];
}


/* =========================================================
   4. VALIDAR FECHA
========================================================= */

function validarFechaSolicitudHoras(
    string $fecha,
    DateTimeZone $zona
): bool {

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $fecha
        )
    ) {
        return false;
    }

    $objeto = DateTime::createFromFormat(
        '!Y-m-d',
        $fecha,
        $zona
    );

    $errores = DateTime::getLastErrors();

    return (
        $objeto !== false &&
        $objeto->format('Y-m-d') === $fecha &&
        (
            $errores === false ||
            (
                $errores['warning_count'] === 0 &&
                $errores['error_count'] === 0
            )
        )
    );
}


/* =========================================================
   5. CALCULAR HORA MÍNIMA PARA EL DÍA ACTUAL
========================================================= */

/*
 * Se conserva la regla de una hora de anticipación.
 *
 * El resultado se redondea hacia adelante al siguiente
 * intervalo de 30 minutos.
 *
 * Ejemplo:
 *
 * Hora actual: 10:05
 * Hora mínima: 11:30
 *
 * Hora actual: 10:40
 * Hora mínima: 12:00
 */

function calcularHoraMinimaSolicitud(
    DateTime $ahora
): DateTime {

    $horaMinima = clone $ahora;

    $horaMinima->modify('+1 hour');

    /*
     * Si quedaron segundos, se avanza un minuto
     * para no reducir el tiempo mínimo.
     */

    if (
        (int) $horaMinima->format('s') > 0
    ) {
        $horaMinima->modify('+1 minute');
    }

    $minutos = (int) $horaMinima->format('i');

    if ($minutos === 0) {

        $horaMinima->setTime(
            (int) $horaMinima->format('H'),
            0,
            0
        );

    } elseif ($minutos <= 30) {

        $horaMinima->setTime(
            (int) $horaMinima->format('H'),
            30,
            0
        );

    } else {

        $horaMinima->modify('+1 hour');

        $horaMinima->setTime(
            (int) $horaMinima->format('H'),
            0,
            0
        );
    }

    return $horaMinima;
}


/* =========================================================
   6. OBTENER SERVICIOS CON DISPONIBILIDAD COMPARTIDA
========================================================= */

function obtenerServiciosDisponibilidadSolicitud(
    PDO $conexion,
    int $idServicio,
    int $idDepartamento,
    bool $horariosUnificados
): array {

    /*
     * CASO 1:
     *
     * Todos los servicios activos del departamento
     * comparten disponibilidad.
     */

    if ($horariosUnificados) {

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

        $ids = array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );

        if (!in_array($idServicio, $ids, true)) {
            $ids[] = $idServicio;
        }

        return array_values(
            array_unique($ids)
        );
    }


    /*
     * CASO 2:
     *
     * Servicios asociados mediante
     * unificacion_horarios_servicios.
     *
     * La estructura actual no tiene id_grupo.
     * Por lo tanto, los servicios asociados al departamento
     * se interpretan como un único conjunto compartido.
     */

    $stmt = $conexion->prepare("
        SELECT DISTINCT
            u.id_servicio

        FROM unificacion_horarios_servicios u

        INNER JOIN servicios s
            ON s.id_servicio = u.id_servicio

        WHERE u.id_departamento = :id_departamento

          AND s.id_departamento = u.id_departamento

          AND s.activo = TRUE

          AND EXISTS (
              SELECT 1

              FROM unificacion_horarios_servicios u2

              WHERE u2.id_departamento = :id_departamento_seleccionado

                AND u2.id_servicio = :id_servicio_seleccionado
          )

        ORDER BY u.id_servicio
    ");

    $stmt->execute([
        ':id_departamento' =>
            $idDepartamento,

        ':id_departamento_seleccionado' =>
            $idDepartamento,

        ':id_servicio_seleccionado' =>
            $idServicio
    ]);

    $idsGrupo = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    if (
        count($idsGrupo) < 2 ||
        !in_array($idServicio, $idsGrupo, true)
    ) {

        return [$idServicio];
    }

    return array_values(
        array_unique($idsGrupo)
    );
}


/* =========================================================
   7. IDENTIFICAR SERVICIOS REALMENTE COMPARTIDOS
========================================================= */

function serviciosCompartenDisponibilidadSolicitud(
    array $idsServicios
): bool {

    return count(
        array_unique(
            array_map('intval', $idsServicios)
        )
    ) > 1;
}


/* =========================================================
   8. GENERAR CLAVE DE DISPONIBILIDAD
========================================================= */

/*
 * Las claves coinciden con las de:
 *
 * api/crear_reserva.php
 * api/gestionar_reserva.php
 *
 * Esto permite coordinar las solicitudes con las
 * reservas públicas e internas.
 */

function obtenerClaveDisponibilidadSolicitud(
    int $idServicio,
    int $idDepartamento,
    bool $horariosUnificados,
    bool $esCompartido,
    string $fecha,
    string $hora
): string {

    if ($horariosUnificados) {

        return
            'departamento-unificado|' .
            $idDepartamento . '|' .
            $fecha . '|' .
            $hora;
    }

    if ($esCompartido) {

        return
            'grupo-unificado|' .
            $idDepartamento . '|' .
            $fecha . '|' .
            $hora;
    }

    return
        'servicio-individual|' .
        $idServicio . '|' .
        $fecha . '|' .
        $hora;
}


/* =========================================================
   9. BLOQUEAR CLAVES DE DISPONIBILIDAD
========================================================= */

function bloquearClavesSolicitud(
    PDO $conexion,
    array $claves
): void {

    $claves = array_values(
        array_unique($claves)
    );

    /*
     * Se utiliza el mismo orden para reducir
     * el riesgo de interbloqueos.
     */

    sort(
        $claves,
        SORT_STRING
    );

    $stmt = $conexion->prepare("
        SELECT pg_advisory_xact_lock(
            hashtext(:clave)
        )
    ");

    foreach ($claves as $clave) {

        $stmt->execute([
            ':clave' => $clave
        ]);
    }
}


/* =========================================================
   10. CONSULTAR CUPOS REALES DE AGENDAS
========================================================= */

/*
 * Solo considera:
 *
 * - Horarios con id_agenda.
 * - Agenda publicada.
 * - Agenda y horario con mismo servicio y fecha.
 * - Segmento habilitado.
 * - Horario habilitado.
 * - Servicio y departamento activos.
 * - disponible_web = TRUE.
 *
 * Los horarios antiguos sin agenda no se consideran.
 *
 * No utiliza configuracion_horarios.
 */

function consultarCuposSolicitud(
    PDO $conexion,
    array $idsServicios,
    int $idDepartamento,
    int $idServicioPreferido,
    bool $esCompartido,
    string $fecha
): array {

    $parametros = [];

    /*
     * Horarios principales.
     */

    $listaServiciosCupos =
        solicitudCrearPlaceholders(
            $idsServicios,
            'cupo',
            $parametros
        );


    /*
     * Comprobaciones cruzadas solamente cuando
     * existen servicios que comparten disponibilidad.
     */

    if ($esCompartido) {

        $parametrosReservas = [];

        $listaServiciosReservas =
            solicitudCrearPlaceholders(
                $idsServicios,
                'reserva',
                $parametrosReservas
            );

        $parametrosBloqueos = [];

        $listaServiciosBloqueos =
            solicitudCrearPlaceholders(
                $idsServicios,
                'bloqueo',
                $parametrosBloqueos
            );

        $parametros = array_merge(
            $parametros,
            $parametrosReservas,
            $parametrosBloqueos
        );


        /*
         * Reserva activa en otro horario compartido.
         */

        $condicionReservaCompartida = "
            EXISTS (
                SELECT 1

                FROM reservas r2

                INNER JOIN horarios h2
                    ON h2.id_horario = r2.id_horario

                INNER JOIN agendas a2
                    ON a2.id_agenda = h2.id_agenda
                   AND a2.id_servicio = h2.id_servicio
                   AND a2.fecha = h2.fecha

                WHERE h2.id_servicio IN (
                    $listaServiciosReservas
                )

                  AND h2.id_agenda IS NOT NULL

                  AND h2.fecha = h.fecha

                  AND LEFT(h2.hora::text, 5) =
                      LEFT(h.hora::text, 5)

                  AND h2.id_horario <> h.id_horario

                  AND h2.habilitado = TRUE

                  AND LOWER(
                      COALESCE(a2.estado, '')
                  ) = 'publicada'

                  AND a2.segmento_habilitado = TRUE

                  AND LOWER(
                      COALESCE(r2.estado, '')
                  ) IN (
                      'confirmada',
                      'reagendada'
                  )
            )
        ";


        /*
         * Bloqueo en otro horario compartido.
         */

        $condicionBloqueoCompartido = "
            EXISTS (
                SELECT 1

                FROM horarios hb

                INNER JOIN agendas ab
                    ON ab.id_agenda = hb.id_agenda
                   AND ab.id_servicio = hb.id_servicio
                   AND ab.fecha = hb.fecha

                WHERE hb.id_servicio IN (
                    $listaServiciosBloqueos
                )

                  AND hb.fecha = h.fecha

                  AND LEFT(hb.hora::text, 5) =
                      LEFT(h.hora::text, 5)

                  AND hb.id_horario <> h.id_horario

                  AND hb.id_agenda IS NOT NULL

                  AND hb.habilitado = TRUE

                  AND LOWER(
                      COALESCE(ab.estado, '')
                  ) = 'publicada'

                  AND ab.segmento_habilitado = TRUE

                  AND LOWER(
                      COALESCE(hb.estado, '')
                  ) = 'bloqueado'
            )
        ";

    } else {

        /*
         * Servicios independientes:
         * No se revisan reservas ni bloqueos de otros cupos
         * que tengan la misma hora.
         *
         * Cada id_horario representa su propia capacidad.
         */

        $condicionReservaCompartida = 'FALSE';

        $condicionBloqueoCompartido = 'FALSE';
    }


    $parametros[':fecha_cupos'] = $fecha;

    $parametros[':id_departamento'] =
        $idDepartamento;

    $parametros[':servicio_preferido'] =
        $idServicioPreferido;


    /* =====================================================
       CONSULTA SQL
    ===================================================== */

    $sql = "
        SELECT
            h.id_horario,
            h.id_servicio,
            h.id_agenda,
            h.fecha,

            LEFT(
                h.hora::text,
                5
            ) AS hora,

            LEFT(
                h.hora_fin::text,
                5
            ) AS hora_fin,

            h.estado AS estado_horario,
            h.habilitado,
            h.disponible_web,

            EXISTS (
                SELECT 1

                FROM reservas r

                WHERE r.id_horario = h.id_horario

                  AND LOWER(
                      COALESCE(r.estado, '')
                  ) IN (
                      'confirmada',
                      'reagendada'
                  )
            ) AS tiene_reserva,

            $condicionReservaCompartida
                AS reserva_compartida,

            $condicionBloqueoCompartido
                AS bloqueo_compartido

        FROM horarios h

        INNER JOIN agendas a
            ON a.id_agenda = h.id_agenda
           AND a.id_servicio = h.id_servicio
           AND a.fecha = h.fecha

        INNER JOIN servicios s
            ON s.id_servicio = h.id_servicio

        INNER JOIN departamentos d
            ON d.id_departamento = s.id_departamento

        WHERE h.id_servicio IN (
            $listaServiciosCupos
        )

          AND h.fecha = :fecha_cupos

          AND h.id_agenda IS NOT NULL

          AND LOWER(
              COALESCE(a.estado, '')
          ) = 'publicada'

          AND a.segmento_habilitado = TRUE

          AND h.habilitado = TRUE

          AND h.disponible_web = TRUE

          AND s.activo = TRUE

          AND d.activo = TRUE

          AND s.id_departamento = :id_departamento

        ORDER BY
            h.hora ASC,

            CASE
                WHEN h.id_servicio = :servicio_preferido
                THEN 0
                ELSE 1
            END,

            h.id_horario ASC
    ";

    $stmt = $conexion->prepare($sql);

    foreach ($parametros as $parametro => $valor) {

        $tipo = in_array(
            $parametro,
            [
                ':fecha_cupos'
            ],
            true
        )
            ? PDO::PARAM_STR
            : PDO::PARAM_INT;

        $stmt->bindValue(
            $parametro,
            $valor,
            $tipo
        );
    }

    $stmt->execute();

    return $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
}


/* =========================================================
   11. EVALUAR CUPOS DISPONIBLES
========================================================= */

function evaluarCuposSolicitud(
    array $cupos,
    bool $esCompartido,
    ?string $horaMinima
): array {

    $totalHorarios = 0;

    $totalDisponibles = 0;

    $horariosPorHora = [];


    foreach ($cupos as $cupo) {

        $hora = substr(
            (string) $cupo['hora'],
            0,
            5
        );

        if (!solicitudValidarHora($hora)) {
            continue;
        }

        /*
         * Aplicar anticipación mínima en el día actual.
         */

        if (
            $horaMinima !== null &&
            $hora < $horaMinima
        ) {
            continue;
        }


        $estadoHorario = solicitudNormalizarTexto(
            $cupo['estado_horario']
        );

        $tieneReserva = solicitudEsVerdadero(
            $cupo['tiene_reserva']
        );

        $reservaCompartida = solicitudEsVerdadero(
            $cupo['reserva_compartida']
        );

        $bloqueoCompartido = solicitudEsVerdadero(
            $cupo['bloqueo_compartido']
        );


        /*
         * Un cupo individual está disponible si:
         *
         * - Su estado es disponible.
         * - No tiene reserva activa propia.
         * - No tiene reserva compartida.
         * - No tiene un bloqueo compartido.
         */

        $estadoDisponible = (
            $estadoHorario === 'disponible'
        );

        $hayConflicto = (
            $tieneReserva ||
            $reservaCompartida ||
            $bloqueoCompartido
        );

        $cupoDisponible = (
            $estadoDisponible &&
            !$hayConflicto
        );


        /*
         * SERVICIOS COMPARTIDOS
         *
         * Se agrupan por hora.
         *
         * Una reserva o bloqueo en otro servicio
         * ocupa el bloque horario compartido.
         *
         * Solo se cuenta una vez cada hora.
         */

        if ($esCompartido) {

            if (!isset($horariosPorHora[$hora])) {

                $horariosPorHora[$hora] = [
                    'hay_cupo_disponible' => false,
                    'hay_conflicto' => false
                ];
            }

            if ($estadoDisponible) {

                $horariosPorHora[$hora][
                    'hay_cupo_disponible'
                ] = true;
            }

            /*
             * Si un horario del grupo está ocupado o bloqueado,
             * el bloque completo se considera no disponible.
             */

            if (
                !$cupoDisponible
            ) {

                $horariosPorHora[$hora][
                    'hay_conflicto'
                ] = true;
            }

        } else {

            /*
             * SERVICIOS INDEPENDIENTES
             *
             * Cada profesional puede tener su propio cupo
             * a la misma hora.
             */

            $totalHorarios++;

            if ($cupoDisponible) {
                $totalDisponibles++;
            }
        }
    }


    /* =====================================================
       RESOLVER GRUPOS COMPARTIDOS
    ===================================================== */

    if ($esCompartido) {

        foreach (
            $horariosPorHora as $grupoHorario
        ) {

            $totalHorarios++;

            $disponible = (
                $grupoHorario['hay_cupo_disponible'] &&
                !$grupoHorario['hay_conflicto']
            );

            if ($disponible) {
                $totalDisponibles++;
            }
        }
    }

    return [
        'total_horarios' => $totalHorarios,
        'total_disponibles' => $totalDisponibles
    ];
}


/* =========================================================
   12. COMPROBAR BLOQUEO TOTAL DEL DÍA
========================================================= */

function comprobarExcepcionTotalSolicitud(
    PDO $conexion,
    array $idsServicios,
    string $fecha
): ?string {

    $parametros = [];

    $listaServicios = solicitudCrearPlaceholders(
        $idsServicios,
        'excepcion',
        $parametros
    );

    $sql = "
        SELECT motivo

        FROM excepciones_horarios

        WHERE id_servicio IN (
            $listaServicios
        )

          AND fecha = :fecha_excepcion

          AND es_bloqueo_total = TRUE

        ORDER BY id_servicio

        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);

    foreach ($parametros as $parametro => $valor) {

        $stmt->bindValue(
            $parametro,
            $valor,
            PDO::PARAM_INT
        );
    }

    $stmt->bindValue(
        ':fecha_excepcion',
        $fecha,
        PDO::PARAM_STR
    );

    $stmt->execute();

    $excepcion = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$excepcion) {
        return null;
    }

    $motivo = trim(
        (string) ($excepcion['motivo'] ?? '')
    );

    return $motivo !== ''
        ? $motivo
        : 'La atención está bloqueada para esta fecha.';
}


/* =========================================================
   13. VALIDAR MÉTODO HTTP
========================================================= */

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
) {

    responderErrorSolicitudHoras(
        'Método de solicitud no permitido.',
        405
    );
}


/* =========================================================
   14. RECIBIR DATOS
========================================================= */

$idDepartamento = filter_var(
    $_POST['id_departamento'] ?? null,
    FILTER_VALIDATE_INT
);

$idServicio = filter_var(
    $_POST['id_servicio'] ?? null,
    FILTER_VALIDATE_INT
);

$fechaSolicitada = trim(
    (string) ($_POST['fecha_solicitada'] ?? '')
);

$rutRecibido = trim(
    (string) ($_POST['rut'] ?? '')
);

$nombre = trim(
    (string) ($_POST['nombre'] ?? '')
);

$apellido = trim(
    (string) ($_POST['apellido'] ?? '')
);

$telefono = trim(
    (string) ($_POST['telefono'] ?? '')
);

$correo = trim(
    (string) ($_POST['correo'] ?? '')
);


/* =========================================================
   15. VALIDAR CAMPOS OBLIGATORIOS
========================================================= */

if (
    $idDepartamento === false ||
    $idDepartamento === null ||
    $idDepartamento <= 0 ||

    $idServicio === false ||
    $idServicio === null ||
    $idServicio <= 0 ||

    $fechaSolicitada === '' ||

    $rutRecibido === '' ||

    $nombre === '' ||

    $apellido === '' ||

    $telefono === '' ||

    $correo === ''
) {

    responderErrorSolicitudHoras(
        'Todos los campos son obligatorios.',
        400
    );
}


/* =========================================================
   16. VALIDAR RUT
========================================================= */

$resultadoRut = validarRutSolicitudHoras(
    $rutRecibido
);

if (!$resultadoRut['valido']) {

    responderErrorSolicitudHoras(
        'El RUT ingresado no es válido.',
        400
    );
}

$rut = $resultadoRut['rut'];


/* =========================================================
   17. VALIDAR NOMBRE Y APELLIDO
========================================================= */

if (
    solicitudLongitud($nombre) < 2 ||
    solicitudLongitud($apellido) < 2 ||

    solicitudLongitud($nombre) > 100 ||
    solicitudLongitud($apellido) > 100
) {

    responderErrorSolicitudHoras(
        'El nombre o apellido no es válido.',
        400
    );
}


/* =========================================================
   18. VALIDAR TELÉFONO
========================================================= */

$telefonoNormalizado = preg_replace(
    '/[\s().-]/',
    '',
    $telefono
);

if (
    !preg_match(
        '/^\+?[0-9]{8,15}$/',
        $telefonoNormalizado
    )
) {

    responderErrorSolicitudHoras(
        'Ingresa un número de teléfono válido.',
        400
    );
}


/* =========================================================
   19. VALIDAR CORREO
========================================================= */

if (
    strlen($correo) > 150 ||
    !filter_var(
        $correo,
        FILTER_VALIDATE_EMAIL
    )
) {

    responderErrorSolicitudHoras(
        'Ingresa un correo electrónico válido.',
        400
    );
}


/* =========================================================
   20. VALIDAR FECHA
========================================================= */

$zonaChile = new DateTimeZone(
    'America/Santiago'
);

if (
    !validarFechaSolicitudHoras(
        $fechaSolicitada,
        $zonaChile
    )
) {

    responderErrorSolicitudHoras(
        'La fecha seleccionada no es válida.',
        400
    );
}

$ahora = new DateTime(
    'now',
    $zonaChile
);

$fechaHoy = $ahora->format('Y-m-d');

if ($fechaSolicitada < $fechaHoy) {

    responderErrorSolicitudHoras(
        'No puedes registrar solicitudes para fechas pasadas.',
        400
    );
}


/* =========================================================
   21. CALCULAR HORA MÍNIMA DEL DÍA ACTUAL
========================================================= */

$horaMinima = null;

if ($fechaSolicitada === $fechaHoy) {

    $horaMinima = calcularHoraMinimaSolicitud(
        $ahora
    );
}

$horaMinimaTexto = $horaMinima !== null
    ? $horaMinima->format('H:i')
    : null;


/* =========================================================
   22. INICIAR PROCESAMIENTO
========================================================= */

try {

    $conexion->beginTransaction();


    /* =====================================================
       22.1 VALIDAR SERVICIO Y DEPARTAMENTO
    ===================================================== */

    $stmtServicio = $conexion->prepare("
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

          AND s.id_departamento = :id_departamento

        LIMIT 1
    ");

    $stmtServicio->execute([
        ':id_servicio' => (int) $idServicio,
        ':id_departamento' => (int) $idDepartamento
    ]);

    $servicio = $stmtServicio->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$servicio) {

        $conexion->rollBack();

        responderErrorSolicitudHoras(
            'El servicio seleccionado no es válido.',
            400
        );
    }

    if (
        !solicitudEsVerdadero(
            $servicio['servicio_activo']
        ) ||

        !solicitudEsVerdadero(
            $servicio['departamento_activo']
        )
    ) {

        $conexion->rollBack();

        responderErrorSolicitudHoras(
            'El servicio o departamento no está disponible.',
            400
        );
    }


    /* =====================================================
       22.2 OBTENER SERVICIOS DEL MISMO CONJUNTO
    ===================================================== */

    $horariosUnificadosDepartamento =
        solicitudEsVerdadero(
            $servicio['horarios_unificados']
        );

    $idsServiciosDisponibilidad =
        obtenerServiciosDisponibilidadSolicitud(
            $conexion,
            (int) $idServicio,
            (int) $idDepartamento,
            $horariosUnificadosDepartamento
        );

    /*
     * Importante:
     *
     * Una bandera de unificación puede estar activa
     * aunque solo exista un servicio activo.
     *
     * En ese caso se utiliza la clave de departamento
     * para los bloqueos, pero no se tratan las agendas
     * de distintos profesionales como servicios cruzados.
     */

    $esCompartido =
        serviciosCompartenDisponibilidadSolicitud(
            $idsServiciosDisponibilidad
        );


    /* =====================================================
       22.3 CONSULTAR CUPOS ANTES DE BLOQUEAR
    ===================================================== */

    /*
     * Esta consulta inicial permite conocer todas las horas
     * afectadas por la solicitud.
     *
     * Después se bloquearán las claves de todas esas horas
     * y se repetirá la consulta antes de insertar la solicitud.
     */

    $cuposIniciales = consultarCuposSolicitud(
        $conexion,
        $idsServiciosDisponibilidad,
        (int) $idDepartamento,
        (int) $idServicio,
        $esCompartido,
        $fechaSolicitada
    );

    if (empty($cuposIniciales)) {

        $conexion->rollBack();

        responderErrorSolicitudHoras(
            'No existen cupos de agendas publicadas y habilitadas para reservas web en la fecha seleccionada. No se puede registrar una solicitud de lista de espera.',
            409
        );
    }


    /* =====================================================
       22.4 GENERAR BLOQUEOS DE DISPONIBILIDAD
    ===================================================== */

    $clavesBloqueo = [];

    /*
     * Bloqueo de duplicados:
     *
     * El mismo RUT no puede registrar simultáneamente
     * varias solicitudes pendientes para el mismo servicio
     * y la misma fecha.
     */

    $claveSolicitud =
        'solicitud-horas|' .
        $rut . '|' .
        (int) $idServicio . '|' .
        $fechaSolicitada;

    $clavesBloqueo[] = $claveSolicitud;


    /*
     * Bloqueos de cada hora que participa en la disponibilidad.
     *
     * Deben ser las mismas claves que utilizan:
     *
     * - api/crear_reserva.php
     * - api/gestionar_reserva.php
     */

    $horasBloqueo = [];

    foreach ($cuposIniciales as $cupo) {

        $hora = substr(
            (string) $cupo['hora'],
            0,
            5
        );

        if (!solicitudValidarHora($hora)) {
            continue;
        }

        if (
            $horaMinimaTexto !== null &&
            $hora < $horaMinimaTexto
        ) {
            continue;
        }

        $horasBloqueo[$hora] = true;
    }

    foreach (
        array_keys($horasBloqueo) as $hora
    ) {

        $clave = obtenerClaveDisponibilidadSolicitud(
            (int) $idServicio,
            (int) $idDepartamento,
            $horariosUnificadosDepartamento,
            $esCompartido,
            $fechaSolicitada,
            $hora
        );

        $clavesBloqueo[] = $clave;
    }


    if (count($horasBloqueo) === 0) {

        $conexion->rollBack();

        responderErrorSolicitudHoras(
            'No existen horarios que cumplan con el tiempo mínimo de anticipación para la fecha seleccionada.',
            409
        );
    }


    /* =====================================================
       22.5 OBTENER BLOQUEOS TRANSACCIONALES
    ===================================================== */

    /*
     * Los bloqueos se adquieren en orden estable.
     *
     * Una reserva pública o interna que utilice cualquiera
     * de estos bloques deberá esperar a que finalice
     * la comprobación de la solicitud.
     */

    bloquearClavesSolicitud(
        $conexion,
        $clavesBloqueo
    );


    /* =====================================================
       22.6 COMPROBAR EXCEPCIÓN DE BLOQUEO TOTAL
    ===================================================== */

    $motivoExcepcion =
        comprobarExcepcionTotalSolicitud(
            $conexion,
            $idsServiciosDisponibilidad,
            $fechaSolicitada
        );

    if ($motivoExcepcion !== null) {

        $conexion->rollBack();

        responderErrorSolicitudHoras(
            $motivoExcepcion .
            ' No se puede registrar una solicitud de lista de espera.',
            409
        );
    }


    /* =====================================================
       22.7 VOLVER A CONSULTAR LOS CUPOS
    ===================================================== */

    /*
     * Esta segunda consulta es la importante.
     *
     * Se ejecuta después de adquirir los mismos bloqueos
     * que utilizan las API de reservas.
     *
     * Así se evita aceptar solicitudes usando una
     * disponibilidad que cambió durante el procesamiento.
     */

    $cuposActualizados = consultarCuposSolicitud(
        $conexion,
        $idsServiciosDisponibilidad,
        (int) $idDepartamento,
        (int) $idServicio,
        $esCompartido,
        $fechaSolicitada
    );


    /* =====================================================
       22.8 EVALUAR DISPONIBILIDAD ACTUALIZADA
    ===================================================== */

    $resultadoDisponibilidad = evaluarCuposSolicitud(
        $cuposActualizados,
        $esCompartido,
        $horaMinimaTexto
    );

    $totalHorarios = (int) (
        $resultadoDisponibilidad['total_horarios']
    );

    $totalDisponibles = (int) (
        $resultadoDisponibilidad['total_disponibles']
    );


    /* =====================================================
       22.9 VALIDAR QUE EXISTAN CUPOS REALES
    ===================================================== */

    if ($totalHorarios === 0) {

        $conexion->rollBack();

        responderErrorSolicitudHoras(
            'No existen cupos de agendas publicadas y habilitadas para reservas web en la fecha seleccionada. No se puede registrar una solicitud de lista de espera.',
            409
        );
    }


    /* =====================================================
       22.10 RECHAZAR SI TODAVÍA HAY CUPOS DISPONIBLES
    ===================================================== */

    if ($totalDisponibles > 0) {

        $conexion->rollBack();

        responderErrorSolicitudHoras(
            'Todavía existen cupos disponibles para la fecha seleccionada. Actualiza los horarios e intenta reservar una hora.',
            409,
            [
                'total_disponibles' => $totalDisponibles
            ]
        );
    }


    /*
     * En este punto:
     *
     * - Existen horarios reales de agendas publicadas.
     * - Todos están ocupados o bloqueados.
     * - No hay cupos web disponibles.
     *
     * Se puede registrar la solicitud.
     */


    /* =====================================================
       23. COMPROBAR SOLICITUD DUPLICADA
    ===================================================== */

    /*
     * El bloqueo de clave solicitud-horas evita que dos
     * peticiones simultáneas del mismo RUT, servicio y fecha
     * inserten solicitudes pendientes duplicadas.
     */

    $stmtDuplicada = $conexion->prepare("
        SELECT id_solicitud

        FROM solicitudes_horas

        WHERE rut = :rut

          AND id_servicio = :id_servicio

          AND fecha_solicitada = :fecha

          AND LOWER(
              COALESCE(estado, '')
          ) = 'pendiente'

        LIMIT 1
    ");

    $stmtDuplicada->execute([
        ':rut' => $rut,
        ':id_servicio' => (int) $idServicio,
        ':fecha' => $fechaSolicitada
    ]);

    $solicitudExistente = $stmtDuplicada->fetch(
        PDO::FETCH_ASSOC
    );

    if ($solicitudExistente) {

        $conexion->rollBack();

        responderErrorSolicitudHoras(
            'Ya existe una solicitud pendiente para este servicio y fecha con el RUT ingresado.',
            409,
            [
                'id_solicitud' =>
                    (int) $solicitudExistente['id_solicitud']
            ]
        );
    }


    /* =====================================================
       24. INSERTAR SOLICITUD
    ===================================================== */

    $stmtInsertar = $conexion->prepare("
        INSERT INTO solicitudes_horas (
            id_departamento,
            id_servicio,
            fecha_solicitada,
            rut,
            nombre,
            apellido,
            telefono,
            correo,
            estado
        )

        VALUES (
            :id_departamento,
            :id_servicio,
            :fecha_solicitada,
            :rut,
            :nombre,
            :apellido,
            :telefono,
            :correo,
            'pendiente'
        )

        RETURNING id_solicitud
    ");

    $stmtInsertar->execute([
        ':id_departamento' =>
            (int) $idDepartamento,

        ':id_servicio' =>
            (int) $idServicio,

        ':fecha_solicitada' =>
            $fechaSolicitada,

        ':rut' =>
            $rut,

        ':nombre' =>
            $nombre,

        ':apellido' =>
            $apellido,

        ':telefono' =>
            $telefonoNormalizado,

        ':correo' =>
            $correo
    ]);

    $idSolicitud = (int) $stmtInsertar->fetchColumn();

    if ($idSolicitud <= 0) {

        throw new RuntimeException(
            'No fue posible obtener el identificador de la solicitud.'
        );
    }


    /* =====================================================
       25. CONFIRMAR TRANSACCIÓN
    ===================================================== */

    $conexion->commit();


    /* =====================================================
       26. RESPUESTA CORRECTA
    ===================================================== */

    responderSolicitudHoras(
        201,
        [
            'ok' => true,

            'id_solicitud' => $idSolicitud,

            'estado' => 'pendiente',

            'mensaje' =>
                'Tu solicitud de horas fue registrada correctamente. Si se libera un cupo, el CESFAM podrá contactarte utilizando los datos que proporcionaste.'
        ]
    );


} catch (PDOException $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $sqlState = $e->errorInfo[0] ?? $e->getCode();

    error_log(
        'Error al registrar solicitud de horas: ' .
        $e->getMessage()
    );

    if ($sqlState === '23505') {

        responderErrorSolicitudHoras(
            'Ya existe una solicitud pendiente para este servicio y fecha.',
            409
        );
    }

    responderErrorSolicitudHoras(
        'Ocurrió un error al registrar tu solicitud. Intenta nuevamente.',
        500
    );


} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        'Error general en solicitud de horas: ' .
        $e->getMessage()
    );

    responderErrorSolicitudHoras(
        'Ocurrió un error al procesar la solicitud.',
        500
    );
}
