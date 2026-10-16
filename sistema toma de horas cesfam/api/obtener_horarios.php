<?php

// =========================================================
// API - OBTENER HORARIOS DISPONIBLES
// =========================================================
// Compatible con:
// - Horarios individuales por servicio
// - Horarios unificados por departamento
// - Horarios unificados por grupo (tabla unificacion_horarios_servicios)
// - Excepciones por servicio / departamento unificado
// - Ocultamiento de horas pasadas
// =========================================================

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexion.php';

try {

    date_default_timezone_set('America/Santiago');

    // =========================================================
    // 1. RECIBIR Y LIMPIAR PARÁMETROS
    // =========================================================

    $servicioParam = trim($_GET['servicio'] ?? '');
    $fecha = trim($_GET['fecha'] ?? '');

    if ($servicioParam === '' || $fecha === '') {
        echo json_encode([
            'ok' => false,
            'error' => 'Faltan datos requeridos (servicio o fecha).'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================
    // 2. VALIDAR FECHA
    // =========================================================

    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);

    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        echo json_encode([
            'ok' => false,
            'error' => 'La fecha seleccionada no es válida.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $zonaChile = new DateTimeZone('America/Santiago');
    $ahora = new DateTime('now', $zonaChile);
    $fechaHoy = $ahora->format('Y-m-d');

    if ($fecha < $fechaHoy) {
        echo json_encode([
            'ok' => true,
            'fecha' => $fecha,
            'servicio' => $servicioParam,
            'horarios' => [],
            'mensaje' => 'No se pueden seleccionar fechas pasadas.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================
    // 3. VALIDAR ID DEL SERVICIO
    // =========================================================

    if (!ctype_digit($servicioParam)) {
        echo json_encode([
            'ok' => false,
            'error' => 'El servicio seleccionado no es válido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idServicio = (int) $servicioParam;

    if ($idServicio <= 0) {
        echo json_encode([
            'ok' => false,
            'error' => 'El servicio seleccionado no es válido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================
    // 4. BUSCAR SERVICIO Y DEPARTAMENTO
    // =========================================================

    $sqlServicio = "
        SELECT
            s.id_servicio,
            s.nombre,
            s.descripcion,
            s.activo,
            s.id_departamento,
            d.nombre AS nombre_departamento,
            d.activo AS departamento_activo,
            d.horarios_unificados
        FROM servicios s
        INNER JOIN departamentos d ON d.id_departamento = s.id_departamento
        WHERE s.id_servicio = :id_servicio
        LIMIT 1
    ";

    $stmtServicio = $conexion->prepare($sqlServicio);
    $stmtServicio->execute([':id_servicio' => $idServicio]);
    $servicioEncontrado = $stmtServicio->fetch(PDO::FETCH_ASSOC);

    if (!$servicioEncontrado) {
        echo json_encode([
            'ok' => false,
            'error' => 'El servicio seleccionado no existe en la base de datos.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nombreServicio = $servicioEncontrado['nombre'];
    $nombreDepartamento = $servicioEncontrado['nombre_departamento'];
    $idDepartamento = (int) $servicioEncontrado['id_departamento'];

    $servicioActivo = in_array($servicioEncontrado['activo'], [true, 't', '1', 1], true);
    $departamentoActivo = in_array($servicioEncontrado['departamento_activo'], [true, 't', '1', 1], true);
    $horariosUnificadosDept = in_array($servicioEncontrado['horarios_unificados'], [true, 't', '1', 1], true);

    if (!$servicioActivo) {
        echo json_encode([
            'ok' => false,
            'error' => 'El servicio seleccionado no está disponible actualmente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$departamentoActivo) {
        echo json_encode([
            'ok' => false,
            'error' => 'El departamento seleccionado no está disponible actualmente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================
    // 5. SERVICIOS QUE COMPARTEN DISPONIBILIDAD (UNIFICACIÓN)
    // =========================================================

    $idsServiciosDisponibilidad = [$idServicio];

    /*
     * A) Unificación por grupo específico (unificacion_horarios_servicios)
     */
    try {
        $sqlGrupoUnificado = "
            SELECT DISTINCT id_servicio
            FROM unificacion_horarios_servicios
            WHERE id_unificacion IN (
                SELECT id_unificacion
                FROM unificacion_horarios_servicios
                WHERE id_servicio = :id_servicio
            )
        ";

        $stmtGrupo = $conexion->prepare($sqlGrupoUnificado);
        $stmtGrupo->execute([':id_servicio' => $idServicio]);
        $serviciosGrupo = $stmtGrupo->fetchAll(PDO::FETCH_COLUMN);

        foreach ($serviciosGrupo as $idSrv) {
            $idInt = (int)$idSrv;
            if (!in_array($idInt, $idsServiciosDisponibilidad, true)) {
                $idsServiciosDisponibilidad[] = $idInt;
            }
        }
    } catch (PDOException $e) {
        // En caso de fallo en tabla, se continúa con el array base
    }

    /*
     * B) Unificación total por Departamento (si está activo en tabla departamentos)
     */
    if ($horariosUnificadosDept) {
        try {
            $sqlServiciosDept = "
                SELECT id_servicio
                FROM servicios
                WHERE id_departamento = :id_departamento
                  AND activo = TRUE
            ";

            $stmtServiciosDept = $conexion->prepare($sqlServiciosDept);
            $stmtServiciosDept->execute([':id_departamento' => $idDepartamento]);

            while ($rowDept = $stmtServiciosDept->fetch(PDO::FETCH_ASSOC)) {
                $idSrvDept = (int)$rowDept['id_servicio'];
                if (!in_array($idSrvDept, $idsServiciosDisponibilidad, true)) {
                    $idsServiciosDisponibilidad[] = $idSrvDept;
                }
            }
        } catch (PDOException $e) {
            // Se mantiene lo acumulado
        }
    }

    $esUnificado = (count($idsServiciosDisponibilidad) > 1);

    // =========================================================
    // 6. DETERMINAR SERVICIO DE REFERENCIA PARA CONFIGURACIÓN
    // =========================================================

    $idServicioConfiguracion = $idServicio;

    if ($esUnificado) {
        try {
            $sqlServicioReferencia = "
                SELECT s.id_servicio
                FROM servicios s
                INNER JOIN configuracion_horarios ch ON ch.id_servicio = s.id_servicio
                WHERE s.id_servicio IN (" . implode(',', array_fill(0, count($idsServiciosDisponibilidad), '?')) . ")
                  AND s.activo = TRUE
                ORDER BY s.id_servicio ASC
                LIMIT 1
            ";

            $stmtRef = $conexion->prepare($sqlServicioReferencia);
            $stmtRef->execute($idsServiciosDisponibilidad);
            $referencia = $stmtRef->fetchColumn();

            if ($referencia !== false) {
                $idServicioConfiguracion = (int) $referencia;
            }
        } catch (PDOException $e) {
            $idServicioConfiguracion = $idServicio;
        }
    }

    // =========================================================
    // 7. VERIFICAR EXCEPCIÓN DEL DÍA
    // =========================================================

    $sqlEx = "
        SELECT es_bloqueo_total, hora_inicio, hora_fin, motivo
        FROM excepciones_horarios
        WHERE id_servicio = :id_servicio
          AND fecha = :fecha
        LIMIT 1
    ";

    $stmtEx = $conexion->prepare($sqlEx);
    $stmtEx->execute([
        ':id_servicio' => $idServicioConfiguracion,
        ':fecha' => $fecha
    ]);

    $excepcion = $stmtEx->fetch(PDO::FETCH_ASSOC);

    if ($excepcion) {
        $esBloqueo = in_array($excepcion['es_bloqueo_total'], [true, 't', '1', 1], true);

        if ($esBloqueo) {
            $motivo = !empty($excepcion['motivo'])
                ? $excepcion['motivo']
                : 'Día no disponible por excepción u horario especial.';

            echo json_encode([
                'ok' => true,
                'fecha' => $fecha,
                'id_servicio' => $idServicio,
                'servicio' => $nombreServicio,
                'id_departamento' => $idDepartamento,
                'departamento' => $nombreDepartamento,
                'horarios_unificados' => $esUnificado,
                'horarios' => [],
                'mensaje' => $motivo
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // =========================================================
    // 8. OBTENER CONFIGURACIÓN SEMANAL
    // =========================================================

    $diaSemana = (int) $fechaValida->format('N');

    $sqlConf = "
        SELECT hora_inicio, hora_fin, intervalo_minutos, activo, tiene_almuerzo, almuerzo_inicio, almuerzo_fin
        FROM configuracion_horarios
        WHERE id_servicio = :id_servicio
          AND dia_semana = :dia_semana
        LIMIT 1
    ";

    $stmtConf = $conexion->prepare($sqlConf);
    $stmtConf->execute([
        ':id_servicio' => $idServicioConfiguracion,
        ':dia_semana' => $diaSemana
    ]);

    $config = $stmtConf->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        echo json_encode([
            'ok' => true,
            'fecha' => $fecha,
            'id_servicio' => $idServicio,
            'servicio' => $nombreServicio,
            'id_departamento' => $idDepartamento,
            'departamento' => $nombreDepartamento,
            'horarios_unificados' => $esUnificado,
            'horarios' => [],
            'mensaje' => 'No hay horarios configurados para este día de la semana.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $esActivo = in_array($config['activo'], [true, 't', '1', 1], true);

    if (!$esActivo) {
        echo json_encode([
            'ok' => true,
            'fecha' => $fecha,
            'id_servicio' => $idServicio,
            'servicio' => $nombreServicio,
            'id_departamento' => $idDepartamento,
            'departamento' => $nombreDepartamento,
            'horarios_unificados' => $esUnificado,
            'horarios' => [],
            'mensaje' => 'No hay atención disponible durante este día.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================
    // 9. DETERMINAR RANGO DE ATENCIÓN
    // =========================================================

    $strHoraInicio = ($excepcion && !empty($excepcion['hora_inicio'])) ? $excepcion['hora_inicio'] : $config['hora_inicio'];
    $strHoraFin = ($excepcion && !empty($excepcion['hora_fin'])) ? $excepcion['hora_fin'] : $config['hora_fin'];
    $intervaloMinutos = (int) ($config['intervalo_minutos'] ?? 30);
    if ($intervaloMinutos <= 0) $intervaloMinutos = 30;

    $tieneAlmuerzo = in_array($config['tiene_almuerzo'], [true, 't', '1', 1], true);
    $almuerzoInicio = $config['almuerzo_inicio'];
    $almuerzoFin = $config['almuerzo_fin'];

    // =========================================================
    // 10. CALCULAR HORA MÍNIMA SI ES HOY
    // =========================================================

    $horaMinima = null;

    if ($fecha === $fechaHoy) {
        $horaMinima = clone $ahora;
        $horaMinima->modify('+1 hour');

        $minutos = (int) $horaMinima->format('i');

        if ($minutos > 0 && $minutos <= 30) {
            $horaMinima->setTime((int)$horaMinima->format('H'), 30, 0);
        } elseif ($minutos > 30) {
            $horaMinima->modify('+1 hour');
            $horaMinima->setTime((int)$horaMinima->format('H'), 0, 0);
        }
    }

    // =========================================================
    // 11. CONSULTAR HORARIOS EXISTENTES DEL SERVICIO
    // =========================================================

    $horariosRegistrados = [];

    try {
        $sqlHorariosRegistrados = "
            SELECT id_horario, LEFT(hora::text, 5) AS hora, estado
            FROM horarios
            WHERE id_servicio = :id_servicio
              AND fecha = :fecha
            ORDER BY hora ASC
        ";

        $stmtHorariosRegistrados = $conexion->prepare($sqlHorariosRegistrados);
        $stmtHorariosRegistrados->execute([
            ':id_servicio' => $idServicio,
            ':fecha' => $fecha
        ]);

        while ($registro = $stmtHorariosRegistrados->fetch(PDO::FETCH_ASSOC)) {
            $horaRegistrada = substr($registro['hora'], 0, 5);
            $horariosRegistrados[$horaRegistrada] = [
                'id_horario' => (int) $registro['id_horario'],
                'estado' => $registro['estado']
            ];
        }
    } catch (PDOException $e) {
        $horariosRegistrados = [];
    }

    // =========================================================
    // 12. CONSULTAR HORARIOS OCUPADOS
    // =========================================================

    $horasOcupadas = [];

    try {
        $parametrosServicios = [];
        $placeholdersServicios = [];

        foreach ($idsServiciosDisponibilidad as $indice => $idServDisponibilidad) {
            $parametro = ':id_servicio_disponibilidad_' . $indice;
            $placeholdersServicios[] = $parametro;
            $parametrosServicios[$parametro] = $idServDisponibilidad;
        }

        if (!empty($placeholdersServicios)) {
            $sqlOcupados = "
                SELECT DISTINCT LEFT(h.hora::text, 5) AS hora
                FROM horarios h
                LEFT JOIN reservas r ON r.id_horario = h.id_horario
                WHERE h.id_servicio IN (" . implode(', ', $placeholdersServicios) . ")
                  AND h.fecha = :fecha
                  AND (
                        h.estado = 'bloqueado'
                        OR LOWER(COALESCE(r.estado, '')) IN ('confirmada', 'reagendada')
                  )
            ";

            $stmtOcupados = $conexion->prepare($sqlOcupados);

            foreach ($parametrosServicios as $parametro => $valor) {
                $stmtOcupados->bindValue($parametro, $valor, PDO::PARAM_INT);
            }

            $stmtOcupados->bindValue(':fecha', $fecha, PDO::PARAM_STR);
            $stmtOcupados->execute();

            while ($r = $stmtOcupados->fetch(PDO::FETCH_ASSOC)) {
                $horasOcupadas[] = substr($r['hora'], 0, 5);
            }
        }
    } catch (PDOException $e) {
        $horasOcupadas = [];
    }

    // =========================================================
    // 13. GENERAR BLOQUES DE HORARIO
    // =========================================================

    $horarios = [];
    $horaCursor = new DateTime($fecha . ' ' . $strHoraInicio, $zonaChile);
    $horaFinAtencion = new DateTime($fecha . ' ' . $strHoraFin, $zonaChile);

    while ($horaCursor < $horaFinAtencion) {

        if ($fecha === $fechaHoy && $horaMinima !== null && $horaCursor < $horaMinima) {
            $horaCursor->modify("+{$intervaloMinutos} minutes");
            continue;
        }

        $horaActualStr = $horaCursor->format('H:i');

        // Evaluar almuerzo
        $esHoraAlmuerzo = false;
        if ($tieneAlmuerzo && !empty($almuerzoInicio) && !empty($almuerzoFin)) {
            $objAlmInicio = new DateTime($fecha . ' ' . $almuerzoInicio, $zonaChile);
            $objAlmFin = new DateTime($fecha . ' ' . $almuerzoFin, $zonaChile);

            if ($horaCursor >= $objAlmInicio && $horaCursor < $objAlmFin) {
                $esHoraAlmuerzo = true;
            }
        }

        if (!$esHoraAlmuerzo) {
            $registroHorario = $horariosRegistrados[$horaActualStr] ?? null;
            $idHorario = $registroHorario !== null ? (int) $registroHorario['id_horario'] : null;

            $estaOcupado = in_array($horaActualStr, $horasOcupadas, true);

            if ($registroHorario !== null && strtolower(trim((string)($registroHorario['estado'] ?? ''))) === 'bloqueado') {
                $estaOcupado = true;
            }

            $horarios[] = [
                'id_horario' => $idHorario,
                'hora' => $horaActualStr,
                'estado' => $estaOcupado ? 'ocupado' : 'disponible',
                'disponible' => !$estaOcupado
            ];
        }

        $horaCursor->modify("+{$intervaloMinutos} minutes");
    }

    // =========================================================
    // 14. RESPUESTA FINAL
    // =========================================================

    if (empty($horarios)) {
        $msg = ($fecha === $fechaHoy)
            ? 'Ya no quedan horarios disponibles para hoy.'
            : 'No hay horarios disponibles para la fecha seleccionada.';

        echo json_encode([
            'ok' => true,
            'fecha' => $fecha,
            'id_servicio' => $idServicio,
            'servicio' => $nombreServicio,
            'id_departamento' => $idDepartamento,
            'departamento' => $nombreDepartamento,
            'horarios_unificados' => $esUnificado,
            'horarios' => [],
            'mensaje' => $msg
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'fecha' => $fecha,
        'id_servicio' => $idServicio,
        'servicio' => $nombreServicio,
        'id_departamento' => $idDepartamento,
        'departamento' => $nombreDepartamento,
        'horarios_unificados' => $esUnificado,
        'horarios' => $horarios,
        'mensaje' => 'Horarios cargados correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error BD: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Error en servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}