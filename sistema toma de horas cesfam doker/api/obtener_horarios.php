<?php

/*
 * =========================================================
 * API - OBTENER CUPOS DE AGENDA PARA TOMA DE HORAS
 * CESFAM EDUARDO RAGGIO CATEMU
 * =========================================================
 *
 * FUENTES DE DISPONIBILIDAD:
 * - agendas
 * - horarios
 * - reservas
 *
 * REGLAS:
 * - No genera horarios desde configuracion_horarios.
 * - No crea registros en horarios.
 * - Solo consulta cupos asociados a agendas.
 * - La agenda debe estar publicada.
 * - El segmento debe estar habilitado.
 * - El horario debe estar habilitado.
 * - El horario debe estar disponible para la web.
 * - Agenda y horario deben coincidir en servicio y fecha.
 * - Respeta la disponibilidad compartida.
 * - Conserva cupos simultáneos de servicios independientes.
 * - Utiliza siempre el id_horario real.
 * - Permite solicitar horas solamente cuando todos los
 *   cupos web existentes están ocupados o bloqueados.
 *
 * Zona horaria: America/Santiago
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexion.php';

date_default_timezone_set('America/Santiago');


/* =========================================================
   1. RESPUESTAS JSON
========================================================= */

function responderHorarios(
    array $datos,
    int $codigo = 200
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


function responderErrorHorarios(
    string $mensaje,
    int $codigo = 400,
    array $datosExtra = []
): void {

    responderHorarios(
        array_merge(
            [
                'ok' => false,
                'error' => $mensaje
            ],
            $datosExtra
        ),
        $codigo
    );
}


/* =========================================================
   2. UTILIDADES
========================================================= */

function horarioEsVerdadero($valor): bool
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


function horarioNormalizarTexto($valor): string
{
    $valor = trim((string) $valor);

    return function_exists('mb_strtolower')
        ? mb_strtolower($valor, 'UTF-8')
        : strtolower($valor);
}


function horarioValidarFecha(
    string $fecha
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
        new DateTimeZone('America/Santiago')
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


function horarioValidarHora(
    string $hora
): bool {

    return preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $hora
    ) === 1;
}


/**
 * Construye placeholders únicos para consultas preparadas.
 */

function horarioCrearPlaceholders(
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


/* =========================================================
   3. RESPUESTA SIN HORARIOS
========================================================= */

function respuestaSinHorarios(
    string $fecha,
    int $idServicio,
    string $nombreServicio,
    int $idDepartamento,
    string $nombreDepartamento,
    bool $unificado,
    string $mensaje
): void {

    responderHorarios([
        'ok' => true,

        'fecha' => $fecha,

        'id_servicio' => $idServicio,

        'servicio' => $nombreServicio,

        'id_departamento' => $idDepartamento,

        'departamento' => $nombreDepartamento,

        'horarios_unificados' => $unificado,

        'horarios' => [],

        'total_horarios' => 0,

        'total_disponibles' => 0,

        'permite_solicitud' => false,

        'mensaje' => $mensaje
    ]);
}


/* =========================================================
   4. OBTENER SERVICIOS CON DISPONIBILIDAD COMPARTIDA
========================================================= */

function obtenerServiciosDisponibilidadWeb(
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
     * Disponibilidad compartida registrada en
     * unificacion_horarios_servicios.
     *
     * La estructura actual no dispone de id_grupo.
     * Por ello, los servicios asociados al departamento
     * se consideran un único conjunto compartido.
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

              WHERE u2.id_departamento = :id_departamento_actual

                AND u2.id_servicio = :id_servicio_actual
          )

        ORDER BY u.id_servicio
    ");

    $stmt->execute([
        ':id_departamento' => $idDepartamento,

        ':id_departamento_actual' => $idDepartamento,

        ':id_servicio_actual' => $idServicio
    ]);

    $idsGrupo = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );

    /*
     * Si el servicio actual no pertenece a una asociación
     * de al menos dos servicios, queda independiente.
     */

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
   5. IDENTIFICAR SERVICIOS REALMENTE COMPARTIDOS
========================================================= */

function serviciosCompartenDisponibilidadWeb(
    array $idsServicios
): bool {

    return count(
        array_unique(
            array_map('intval', $idsServicios)
        )
    ) > 1;
}


/* =========================================================
   6. CALCULAR HORA MÍNIMA PARA EL DÍA ACTUAL
========================================================= */

/*
 * Se conserva la regla del sistema:
 *
 * - Una hora de anticipación como mínimo.
 * - Redondeo hacia adelante al siguiente intervalo
 *   de 30 minutos.
 *
 * Esta regla debe coincidir con las API que crean reservas.
 */

function calcularHoraMinimaWeb(
    DateTime $ahora
): DateTime {

    $horaMinima = clone $ahora;

    $horaMinima->modify('+1 hour');

    $minutos = (int) $horaMinima->format('i');

    if (
        $minutos > 0 &&
        $minutos <= 30
    ) {

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

    return $horaMinima;
}


/* =========================================================
   7. COMPROBAR EXCEPCIONES DE BLOQUEO TOTAL
========================================================= */

function obtenerExcepcionTotalWeb(
    PDO $conexion,
    array $idsServicios,
    string $fecha
): ?string {

    $parametros = [];

    $listaServicios = horarioCrearPlaceholders(
        $idsServicios,
        'excepcion',
        $parametros
    );

    $stmt = $conexion->prepare("
        SELECT motivo

        FROM excepciones_horarios

        WHERE id_servicio IN ($listaServicios)

          AND fecha = :fecha_excepcion

          AND es_bloqueo_total = TRUE

        ORDER BY id_servicio

        LIMIT 1
    ");

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
        : 'La atención no está disponible para esta fecha.';
}


/* =========================================================
   8. CONSULTAR CUPOS REALES DE AGENDA
========================================================= */

function consultarCuposDisponibilidadWeb(
    PDO $conexion,
    array $idsServicios,
    int $idDepartamento,
    int $idServicioPreferido,
    bool $esCompartido,
    string $fecha
): array {

    $parametros = [];


    /*
     * Servicios de los horarios principales.
     */

    $listaServiciosPrincipal =
        horarioCrearPlaceholders(
            $idsServicios,
            'servicio_principal',
            $parametros
        );


    /*
     * Comprobaciones entre servicios únicamente
     * cuando existe disponibilidad compartida.
     */

    if ($esCompartido) {

        $parametrosReservas = [];

        $listaServiciosReservas =
            horarioCrearPlaceholders(
                $idsServicios,
                'servicio_reserva',
                $parametrosReservas
            );

        $parametrosBloqueos = [];

        $listaServiciosBloqueos =
            horarioCrearPlaceholders(
                $idsServicios,
                'servicio_bloqueo',
                $parametrosBloqueos
            );

        $parametros = array_merge(
            $parametros,
            $parametrosReservas,
            $parametrosBloqueos
        );


        /*
         * Reservas activas en otros servicios compartidos.
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
         * Bloqueos en otros servicios compartidos.
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
         *
         * Cada id_horario representa su propio cupo.
         * Una reserva o bloqueo en otro profesional
         * no debe ocultar este horario.
         */

        $condicionReservaCompartida = 'FALSE';

        $condicionBloqueoCompartido = 'FALSE';
    }


    $parametros[':fecha_consulta'] = $fecha;

    $parametros[':id_departamento'] = $idDepartamento;

    $parametros[':servicio_preferido'] =
        $idServicioPreferido;


    /* =====================================================
       CONSULTA PRINCIPAL
    ===================================================== */

    $sql = "

        SELECT

            h.id_horario,

            h.id_servicio AS id_servicio_horario,

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

            h.observacion AS observacion_horario,

            h.habilitado,

            h.disponible_web,

            a.id_profesional,

            a.sector,

            s.nombre AS nombre_servicio_horario,


            /*
             * Reserva activa en el horario exacto.
             */

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


            /*
             * Reserva en otro servicio compartido.
             */

            $condicionReservaCompartida
                AS reserva_compartida,


            /*
             * Bloqueo en otro servicio compartido.
             */

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
            $listaServiciosPrincipal
        )

          AND h.fecha = :fecha_consulta

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


    /*
     * Enlazar todos los parámetros.
     */

    foreach ($parametros as $parametro => $valor) {

        $tipo = (
            $parametro === ':fecha_consulta'
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
   9. PROCESAMIENTO
========================================================= */

try {

    $zonaChile = new DateTimeZone(
        'America/Santiago'
    );


    /* =====================================================
       9.1 VALIDAR MÉTODO HTTP
    ===================================================== */

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
    ) {

        responderErrorHorarios(
            'Método no permitido.',
            405
        );
    }


    /* =====================================================
       9.2 RECIBIR PARÁMETROS
    ===================================================== */

    $servicioParam = trim(
        (string) ($_GET['servicio'] ?? '')
    );

    $fecha = trim(
        (string) ($_GET['fecha'] ?? '')
    );

    if (
        $servicioParam === '' ||
        $fecha === ''
    ) {

        responderErrorHorarios(
            'Faltan datos requeridos: servicio o fecha.',
            400
        );
    }


    /* =====================================================
       9.3 VALIDAR SERVICIO
    ===================================================== */

    if (
        !ctype_digit($servicioParam) ||
        (int) $servicioParam <= 0
    ) {

        responderErrorHorarios(
            'El servicio seleccionado no es válido.',
            400
        );
    }

    $idServicio = (int) $servicioParam;


    /* =====================================================
       9.4 VALIDAR FECHA
    ===================================================== */

    if (!horarioValidarFecha($fecha)) {

        responderErrorHorarios(
            'La fecha seleccionada no es válida.',
            400
        );
    }

    $ahora = new DateTime(
        'now',
        $zonaChile
    );

    $fechaHoy = $ahora->format('Y-m-d');

    if ($fecha < $fechaHoy) {

        responderHorarios([
            'ok' => true,

            'fecha' => $fecha,

            'id_servicio' => $idServicio,

            'horarios' => [],

            'total_horarios' => 0,

            'total_disponibles' => 0,

            'permite_solicitud' => false,

            'mensaje' =>
                'No se pueden seleccionar fechas pasadas.'
        ]);
    }


    /* =====================================================
       10. OBTENER SERVICIO Y DEPARTAMENTO
    ===================================================== */

    $stmtServicio = $conexion->prepare("
        SELECT
            s.id_servicio,
            s.nombre,
            s.descripcion,
            s.activo AS servicio_activo,
            s.id_departamento,

            d.nombre AS nombre_departamento,
            d.activo AS departamento_activo,
            d.horarios_unificados

        FROM servicios s

        INNER JOIN departamentos d
            ON d.id_departamento = s.id_departamento

        WHERE s.id_servicio = :id_servicio

        LIMIT 1
    ");

    $stmtServicio->execute([
        ':id_servicio' => $idServicio
    ]);

    $servicioEncontrado = $stmtServicio->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$servicioEncontrado) {

        responderErrorHorarios(
            'El servicio seleccionado no existe en la base de datos.',
            404
        );
    }


    $nombreServicio = (string) (
        $servicioEncontrado['nombre']
    );

    $nombreDepartamento = (string) (
        $servicioEncontrado['nombre_departamento']
    );

    $idDepartamento = (int) (
        $servicioEncontrado['id_departamento']
    );

    $servicioActivo = horarioEsVerdadero(
        $servicioEncontrado['servicio_activo']
    );

    $departamentoActivo = horarioEsVerdadero(
        $servicioEncontrado['departamento_activo']
    );

    $horariosUnificadosDepartamento =
        horarioEsVerdadero(
            $servicioEncontrado['horarios_unificados']
        );


    if (!$servicioActivo) {

        responderErrorHorarios(
            'El servicio seleccionado no está disponible actualmente.',
            400
        );
    }

    if (!$departamentoActivo) {

        responderErrorHorarios(
            'El departamento seleccionado no está disponible actualmente.',
            400
        );
    }


    /* =====================================================
       11. DETERMINAR SERVICIOS COMPARTIDOS
    ===================================================== */

    $idsServiciosDisponibilidad =
        obtenerServiciosDisponibilidadWeb(
            $conexion,
            $idServicio,
            $idDepartamento,
            $horariosUnificadosDepartamento
        );

    $esCompartido =
        serviciosCompartenDisponibilidadWeb(
            $idsServiciosDisponibilidad
        );

    /*
     * Se conserva el estado de unificación del departamento
     * aunque actualmente solo haya un servicio activo.
     *
     * La agrupación por hora se realiza solamente cuando
     * hay más de un servicio compartido.
     */

    $esUnificado = (
        $horariosUnificadosDepartamento ||
        $esCompartido
    );


    /* =====================================================
       12. COMPROBAR EXCEPCIÓN DE BLOQUEO TOTAL
    ===================================================== */

    $motivoExcepcion = obtenerExcepcionTotalWeb(
        $conexion,
        $idsServiciosDisponibilidad,
        $fecha
    );

    if ($motivoExcepcion !== null) {

        respuestaSinHorarios(
            $fecha,
            $idServicio,
            $nombreServicio,
            $idDepartamento,
            $nombreDepartamento,
            $esUnificado,
            $motivoExcepcion
        );
    }


    /* =====================================================
       13. CALCULAR HORA MÍNIMA DEL DÍA ACTUAL
    ===================================================== */

    $horaMinima = null;

    if ($fecha === $fechaHoy) {

        $horaMinima = calcularHoraMinimaWeb(
            $ahora
        );
    }


    /* =====================================================
       14. CONSULTAR CUPOS REALES
    ===================================================== */

    $cuposDB = consultarCuposDisponibilidadWeb(
        $conexion,
        $idsServiciosDisponibilidad,
        $idDepartamento,
        $idServicio,
        $esCompartido,
        $fecha
    );


    /* =====================================================
       15. PROCESAR CUPOS
    ===================================================== */

    $horarios = [];

    $horariosPorHora = [];

    foreach ($cuposDB as $cupo) {

        $hora = substr(
            (string) $cupo['hora'],
            0,
            5
        );

        if (!horarioValidarHora($hora)) {
            continue;
        }


        /*
         * Aplicar anticipación mínima en el día actual.
         */

        if (
            $fecha === $fechaHoy &&
            $horaMinima !== null &&
            $hora < $horaMinima->format('H:i')
        ) {
            continue;
        }


        /*
         * Obtener los conflictos.
         */

        $estadoHorario = horarioNormalizarTexto(
            $cupo['estado_horario']
        );

        $tieneReserva = horarioEsVerdadero(
            $cupo['tiene_reserva']
        );

        $reservaCompartida = horarioEsVerdadero(
            $cupo['reserva_compartida']
        );

        $bloqueoCompartido = horarioEsVerdadero(
            $cupo['bloqueo_compartido']
        );


        /*
         * El cupo está disponible cuando:
         *
         * - El estado es disponible.
         * - No tiene una reserva activa.
         * - No existe una reserva conflictiva compartida.
         * - No existe un bloqueo conflictivo compartido.
         *
         * La agenda, habilitación y disponibilidad web
         * ya fueron comprobadas en SQL.
         */

        $estaDisponible = (
            $estadoHorario === 'disponible' &&
            !$tieneReserva &&
            !$reservaCompartida &&
            !$bloqueoCompartido
        );


        /* =================================================
           CONSTRUIR REGISTRO DE RESPUESTA
        ================================================= */

        $registro = [

            'id_horario' =>
                (int) $cupo['id_horario'],

            'id_agenda' =>
                (int) $cupo['id_agenda'],

            /*
             * Servicio que solicitó el paciente.
             */

            'id_servicio' =>
                $idServicio,

            /*
             * Servicio al que pertenece el horario real.
             *
             * CORRECCIÓN:
             * La consulta SQL utiliza el alias
             * id_servicio_horario.
             *
             * Por eso se debe leer exactamente esa clave.
             */

            'id_servicio_horario' =>
                (int) $cupo['id_servicio_horario'],

            'id_profesional' =>
                $cupo['id_profesional'] !== null
                    ? (int) $cupo['id_profesional']
                    : null,

            'servicio_horario' =>
                (string) $cupo['nombre_servicio_horario'],

            'sector' =>
                $cupo['sector'],

            'hora' =>
                $hora,

            'hora_fin' =>
                $cupo['hora_fin'] !== null
                    ? substr(
                        (string) $cupo['hora_fin'],
                        0,
                        5
                    )
                    : null,

            'estado' =>
                $estaDisponible
                    ? 'disponible'
                    : 'ocupado',

            'disponible' =>
                $estaDisponible
        ];


        /* =================================================
           15.1 SERVICIOS COMPARTIDOS
        ================================================= */

        /*
         * Cuando varios servicios comparten cupos:
         *
         * - Se muestra un solo horario por hora.
         * - Se prioriza el servicio seleccionado.
         * - El estado de disponibilidad considera los
         *   conflictos de todo el conjunto compartido.
         */

        if ($esCompartido) {

            if (!isset($horariosPorHora[$hora])) {

                $horariosPorHora[$hora] =
                    $registro;
            }

        } else {

            /*
             * Servicios independientes:
             *
             * Se conservan todos los horarios, aunque
             * diferentes profesionales tengan la misma hora.
             */

            $horarios[] = $registro;
        }
    }


    /* =====================================================
       16. RESOLVER HORARIOS COMPARTIDOS
    ===================================================== */

    if ($esCompartido) {

        $horarios = array_values(
            $horariosPorHora
        );
    }


    /* =====================================================
       17. ORDENAR HORARIOS
    ===================================================== */

    usort(
        $horarios,
        function ($a, $b) {

            $comparacion = strcmp(
                $a['hora'],
                $b['hora']
            );

            if ($comparacion !== 0) {
                return $comparacion;
            }

            return (
                $a['id_horario'] <=>
                $b['id_horario']
            );
        }
    );


    /* =====================================================
       18. CALCULAR DISPONIBILIDAD
    ===================================================== */

    $totalHorarios = count($horarios);

    $totalDisponibles = 0;

    foreach ($horarios as $horario) {

        if ($horario['disponible'] === true) {
            $totalDisponibles++;
        }
    }


    /* =====================================================
       19. DETERMINAR SI PERMITE SOLICITUD
    ===================================================== */

    /*
     * Se permite registrar una solicitud únicamente cuando:
     *
     * - Existen cupos reales de agendas publicadas.
     * - Están habilitados para la web.
     * - No queda ningún cupo disponible.
     *
     * Si no hay agenda publicada, no se permite solicitud.
     */

    $permiteSolicitud = (
        $totalHorarios > 0 &&
        $totalDisponibles === 0
    );


    /* =====================================================
       20. CONSTRUIR MENSAJE
    ===================================================== */

    if ($totalHorarios === 0) {

        $mensajeFinal = (
            $fecha === $fechaHoy
        )
            ? 'No quedan cupos de agenda publicados y habilitados para reservas web durante hoy.'
            : 'No hay agendas publicadas con cupos habilitados para reservas web en la fecha seleccionada.';

    } elseif ($permiteSolicitud) {

        $mensajeFinal =
            'No quedan cupos disponibles para esta fecha. Puedes registrar una solicitud de horas.';

    } else {

        $mensajeFinal =
            'Cupos de agenda cargados correctamente.';
    }


    /* =====================================================
       21. RESPUESTA FINAL
    ===================================================== */

    responderHorarios([

        'ok' => true,

        'fecha' => $fecha,

        'id_servicio' => $idServicio,

        'servicio' => $nombreServicio,

        'id_departamento' => $idDepartamento,

        'departamento' => $nombreDepartamento,

        'horarios_unificados' => $esUnificado,

        'horarios' => $horarios,

        'total_horarios' => $totalHorarios,

        'total_disponibles' => $totalDisponibles,

        'permite_solicitud' => $permiteSolicitud,

        'mensaje' => $mensajeFinal

    ]);


} catch (PDOException $e) {

    error_log(
        'Error en api/obtener_horarios.php: ' .
        $e->getMessage()
    );

    responderErrorHorarios(
        'No fue posible consultar los cupos de agenda en este momento.',
        500
    );


} catch (Throwable $e) {

    error_log(
        'Error general en api/obtener_horarios.php: ' .
        $e->getMessage()
    );

    responderErrorHorarios(
        'Ocurrió un error al consultar los cupos de agenda.',
        500
    );
}
