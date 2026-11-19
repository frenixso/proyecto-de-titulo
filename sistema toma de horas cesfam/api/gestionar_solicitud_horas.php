<?php

/*
 * =========================================================
 * API - GESTIONAR SOLICITUDES DE HORAS
 * CESFAM EDUARDO RAGGIO CATEMU
 * =========================================================
 *
 * ACCIONES:
 *
 * - marcar_atendida:
 *   Comprueba que exista una reserva válida asociada a la
 *   solicitud y cambia su estado a atendida.
 *   Acepta reservas pendientes de confirmación, confirmadas
 *   o reagendadas, porque asignar un cupo no confirma por sí
 *   mismo la atención.
 *
 * - rechazar:
 *   Cambia una solicitud pendiente a rechazada.
 *
 * - reabrir:
 *   Cambia una solicitud rechazada a pendiente.
 *
 * La reserva se crea mediante api/crear_reserva.php.
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Santiago');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/conexion.php';


/* =========================================================
   1. RESPUESTAS JSON
========================================================= */

function responderSolicitud(
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


function errorSolicitud(
    string $mensaje,
    int $codigo = 400
): void {

    global $conexion;

    if (
        isset($conexion) &&
        $conexion instanceof PDO &&
        $conexion->inTransaction()
    ) {
        $conexion->rollBack();
    }

    responderSolicitud([
        'ok' => false,
        'error' => $mensaje,
        'mensaje' => $mensaje
    ], $codigo);
}


/* =========================================================
   2. UTILIDADES
========================================================= */

function normalizarEstadoSolicitud($estado): string
{
    return strtolower(trim((string) $estado));
}


function normalizarTextoSolicitud($valor): string
{
    $valor = trim((string) $valor);

    return function_exists('mb_strtolower')
        ? mb_strtolower($valor, 'UTF-8')
        : strtolower($valor);
}


function normalizarRutSolicitud($rut): string
{
    return strtoupper(
        preg_replace('/[^0-9K]/i', '', (string) $rut)
    );
}


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


/* =========================================================
   3. VALIDAR SESIÓN Y OBTENER FUNCIONARIO
========================================================= */

/**
 * Utiliza las mismas variables de sesión y la misma
 * verificación de funcionario que api/gestionar_reserva.php
 * y api/crear_reserva.php.
 */
function obtenerFuncionarioActualSolicitud(
    PDO $conexion
): ?array {

    static $funcionarioCache = null;
    static $consultado = false;

    if ($consultado) {
        return $funcionarioCache;
    }

    $consultado = true;

    $usuario = trim(
        (string) ($_SESSION['usuario_funcionario'] ?? '')
    );

    $rolSesion = normalizarTextoSolicitud(
        $_SESSION['rol_funcionario'] ?? ''
    );

    if ($usuario === '') {
        return null;
    }

    $rolesPermitidos = [
        'superadmin',
        'admin',
        'administrador',
        'funcionario'
    ];

    if (!in_array($rolSesion, $rolesPermitidos, true)) {
        return null;
    }

    $stmt = $conexion->prepare("
        SELECT
            id_funcionario,
            nombre,
            apellido,
            usuario,
            rol,
            activo,
            id_departamento
        FROM funcionarios
        WHERE usuario = :usuario
        LIMIT 1
    ");

    $stmt->execute([
        ':usuario' => $usuario
    ]);

    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$funcionario ||
        !solicitudEsVerdadero($funcionario['activo'] ?? false)
    ) {
        return null;
    }

    $rolBD = normalizarTextoSolicitud(
        $funcionario['rol'] ?? ''
    );

    if (!in_array($rolBD, $rolesPermitidos, true)) {
        return null;
    }

    $funcionario['rol_normalizado'] = $rolBD;
    $funcionarioCache = $funcionario;

    return $funcionarioCache;
}


function validarPermisosSolicitud(PDO $conexion): array
{
    $funcionario = obtenerFuncionarioActualSolicitud($conexion);

    if (!$funcionario) {
        errorSolicitud(
            'No tienes permisos para gestionar solicitudes de horas o tu sesión ha caducado.',
            403
        );
    }

    return $funcionario;
}


function validarAccesoDepartamentoSolicitud(
    array $funcionario,
    int $idDepartamento
): bool {

    if (
        ($funcionario['rol_normalizado'] ?? '') === 'superadmin'
    ) {
        return true;
    }

    return (
        (int) ($funcionario['id_departamento'] ?? 0) ===
        $idDepartamento
    );
}


/* =========================================================
   4. COMPROBAR SERVICIOS COMPARTIDOS
========================================================= */

/**
 * Comprueba si dos servicios pertenecen al mismo conjunto
 * de disponibilidad, respetando la estructura actual de
 * horarios_unificados y unificacion_horarios_servicios.
 */
function serviciosCompatiblesSolicitud(
    PDO $conexion,
    int $idDepartamento,
    int $idServicioSolicitud,
    int $idServicioComparar
): bool {

    if ($idServicioSolicitud === $idServicioComparar) {
        return true;
    }

    $stmtDepartamento = $conexion->prepare("
        SELECT horarios_unificados
        FROM departamentos
        WHERE id_departamento = :id_departamento
        LIMIT 1
    ");

    $stmtDepartamento->execute([
        ':id_departamento' => $idDepartamento
    ]);

    $filaDepartamento = $stmtDepartamento->fetch(PDO::FETCH_ASSOC);

    if (!$filaDepartamento) {
        return false;
    }

    $horariosUnificados = $filaDepartamento['horarios_unificados'] ?? false;

    /* Todo el departamento comparte disponibilidad. */
    if (solicitudEsVerdadero($horariosUnificados)) {

        $stmtServicios = $conexion->prepare("
            SELECT COUNT(*)
            FROM servicios
            WHERE id_departamento = :id_departamento
              AND id_servicio IN (
                  :id_servicio_solicitud,
                  :id_servicio_comparar
              )
              AND activo = TRUE
        ");

        $stmtServicios->execute([
            ':id_departamento' => $idDepartamento,
            ':id_servicio_solicitud' => $idServicioSolicitud,
            ':id_servicio_comparar' => $idServicioComparar
        ]);

        return (int) $stmtServicios->fetchColumn() === 2;
    }

    /* Servicios asociados mediante unificación. */
    $stmtGrupo = $conexion->prepare("
        SELECT COUNT(DISTINCT id_servicio)
        FROM unificacion_horarios_servicios
        WHERE id_departamento = :id_departamento
          AND id_servicio IN (
              :id_servicio_solicitud,
              :id_servicio_comparar
          )
    ");

    $stmtGrupo->execute([
        ':id_departamento' => $idDepartamento,
        ':id_servicio_solicitud' => $idServicioSolicitud,
        ':id_servicio_comparar' => $idServicioComparar
    ]);

    return (int) $stmtGrupo->fetchColumn() === 2;
}


/* =========================================================
   5. OBTENER SOLICITUD CON BLOQUEO
========================================================= */

function obtenerSolicitudBloqueada(
    PDO $conexion,
    int $idSolicitud
): array {

    $stmt = $conexion->prepare("
        SELECT
            id_solicitud,
            id_departamento,
            id_servicio,
            fecha_solicitada,
            rut,
            nombre,
            apellido,
            estado
        FROM solicitudes_horas
        WHERE id_solicitud = :id_solicitud
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':id_solicitud' => $idSolicitud
    ]);

    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        errorSolicitud(
            'La solicitud seleccionada no existe.',
            404
        );
    }

    return $solicitud;
}


/* =========================================================
   6. VALIDAR RESERVA ASOCIADA A LA SOLICITUD
========================================================= */

/**
 * La solicitud puede marcarse como atendida cuando existe
 * una reserva válida que:
 *
 * - Utiliza el id_horario recibido.
 * - Pertenece al mismo RUT.
 * - Tiene la misma fecha solicitada.
 * - Pertenece al mismo departamento.
 * - Su servicio es compatible con el solicitado.
 * - Está asociada a una agenda publicada y habilitada.
 * - Está pendiente de confirmación, confirmada o reagendada.
 *
 * Se incluye pendiente_confirmacion porque crear_reserva.php
 * deja las reservas nuevas en ese estado. Asignar una hora
 * no significa que la reserva ya esté confirmada.
 */
function validarReservaSolicitud(
    PDO $conexion,
    array $solicitud,
    int $idHorario
): bool {

    $stmt = $conexion->prepare("
        SELECT
            r.rut,
            r.id_servicio AS id_servicio_reserva,
            r.estado AS estado_reserva,

            h.id_horario,
            h.id_servicio AS id_servicio_horario,
            h.fecha,
            h.habilitado,

            a.estado AS estado_agenda,
            a.segmento_habilitado,

            sh.id_departamento AS id_departamento_horario,
            sh.activo AS servicio_horario_activo,
            sr.id_departamento AS id_departamento_reserva,
            sr.activo AS servicio_reserva_activo,
            d.activo AS departamento_activo

        FROM reservas r

        INNER JOIN horarios h
            ON h.id_horario = r.id_horario

        INNER JOIN agendas a
            ON a.id_agenda = h.id_agenda
           AND a.id_servicio = h.id_servicio
           AND a.fecha = h.fecha

        INNER JOIN servicios sh
            ON sh.id_servicio = h.id_servicio

        INNER JOIN servicios sr
            ON sr.id_servicio = r.id_servicio

        INNER JOIN departamentos d
            ON d.id_departamento = sh.id_departamento

        WHERE r.id_horario = :id_horario

          AND LOWER(COALESCE(r.estado, '')) IN (
              'pendiente_confirmacion',
              'confirmada',
              'reagendada'
          )

          AND h.id_agenda IS NOT NULL
          AND h.habilitado = TRUE
          AND LOWER(COALESCE(a.estado, '')) = 'publicada'
          AND a.segmento_habilitado = TRUE

        LIMIT 1
        FOR UPDATE OF r, h
    ");

    $stmt->execute([
        ':id_horario' => $idHorario
    ]);

    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        return false;
    }

    /* Validar RUT. */
    $rutSolicitud = normalizarRutSolicitud($solicitud['rut'] ?? '');
    $rutReserva = normalizarRutSolicitud($reserva['rut'] ?? '');

    if (
        $rutSolicitud === '' ||
        $rutSolicitud !== $rutReserva
    ) {
        return false;
    }

    /* Validar fecha de la reserva. */
    $fechaReserva = substr(
        (string) ($reserva['fecha'] ?? ''),
        0,
        10
    );

    if ($fechaReserva === '') {
        return false;
    }

    /* Validar departamento. */
    $idDepartamentoSolicitud = (int) (
        $solicitud['id_departamento'] ?? 0
    );

    if (
        $idDepartamentoSolicitud <= 0 ||
        $idDepartamentoSolicitud !== (int) $reserva['id_departamento_horario'] ||
        $idDepartamentoSolicitud !== (int) $reserva['id_departamento_reserva']
    ) {
        return false;
    }

    /* Validar que los servicios estén activos. */
    if (
        !solicitudEsVerdadero($reserva['servicio_horario_activo']) ||
        !solicitudEsVerdadero($reserva['servicio_reserva_activo']) ||
        !solicitudEsVerdadero($reserva['departamento_activo'])
    ) {
        return false;
    }

    /*
     * Validar tanto el servicio registrado en la reserva como
     * el servicio al que pertenece el horario. En agendas con
     * disponibilidad compartida ambos pueden ser diferentes.
     */
    $servicioReservaCompatible = serviciosCompatiblesSolicitud(
        $conexion,
        $idDepartamentoSolicitud,
        (int) $solicitud['id_servicio'],
        (int) $reserva['id_servicio_reserva']
    );

    if (!$servicioReservaCompatible) {
        return false;
    }

    return serviciosCompatiblesSolicitud(
        $conexion,
        $idDepartamentoSolicitud,
        (int) $solicitud['id_servicio'],
        (int) $reserva['id_servicio_horario']
    );
}


/* =========================================================
   7. PROCESAMIENTO
========================================================= */

try {

    /* =====================================================
       7.1. MÉTODO HTTP
    ===================================================== */

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        errorSolicitud('Método no permitido.', 405);
    }


    /* =====================================================
       7.2. VALIDAR FUNCIONARIO Y PERMISOS
    ===================================================== */

    $funcionarioActual = validarPermisosSolicitud($conexion);


    /* =====================================================
       7.3. RECIBIR DATOS
    ===================================================== */

    $datos = $_POST;

    /* Permite recibir JSON además de formularios POST. */
    if (empty($datos)) {

        $contenido = file_get_contents('php://input');

        if ($contenido !== false && trim($contenido) !== '') {
            $json = json_decode($contenido, true);

            if (is_array($json)) {
                $datos = $json;
            }
        }
    }

    $accion = strtolower(
        trim((string) ($datos['accion'] ?? ''))
    );

    $idSolicitudParam = trim(
        (string) ($datos['id_solicitud'] ?? '')
    );

    $idHorarioParam = trim(
        (string) ($datos['id_horario'] ?? '')
    );


    /* =====================================================
       7.4. VALIDAR ACCIÓN
    ===================================================== */

    $accionesPermitidas = [
        'marcar_atendida',
        'rechazar',
        'reabrir'
    ];

    if (!in_array($accion, $accionesPermitidas, true)) {
        errorSolicitud('La acción solicitada no es válida.', 400);
    }


    /* =====================================================
       7.5. VALIDAR ID DE SOLICITUD
    ===================================================== */

    if (
        !ctype_digit($idSolicitudParam) ||
        (int) $idSolicitudParam <= 0
    ) {
        errorSolicitud(
            'El identificador de la solicitud no es válido.',
            400
        );
    }

    $idSolicitud = (int) $idSolicitudParam;


    /* =====================================================
       7.6. VALIDAR ID DE HORARIO CUANDO CORRESPONDA
    ===================================================== */

    if ($accion === 'marcar_atendida') {

        if (
            !ctype_digit($idHorarioParam) ||
            (int) $idHorarioParam <= 0
        ) {
            errorSolicitud(
                'Debes indicar el horario utilizado para la reserva.',
                400
            );
        }

        $idHorario = (int) $idHorarioParam;
    }


    /* =====================================================
       7.7. INICIAR TRANSACCIÓN Y BLOQUEAR SOLICITUD
    ===================================================== */

    $conexion->beginTransaction();

    $solicitud = obtenerSolicitudBloqueada(
        $conexion,
        $idSolicitud
    );

    $estadoActual = normalizarEstadoSolicitud(
        $solicitud['estado']
    );

    $idDepartamentoSolicitud = (int) (
        $solicitud['id_departamento'] ?? 0
    );

    if (
        !validarAccesoDepartamentoSolicitud(
            $funcionarioActual,
            $idDepartamentoSolicitud
        )
    ) {
        errorSolicitud(
            'No tienes permisos para gestionar solicitudes de este departamento.',
            403
        );
    }


    /* =====================================================
       8. MARCAR COMO ATENDIDA
    ===================================================== */

    if ($accion === 'marcar_atendida') {

        if ($estadoActual === 'atendida') {
            $conexion->commit();

            responderSolicitud([
                'ok' => true,
                'mensaje' => 'La solicitud ya se encontraba marcada como atendida.',
                'id_solicitud' => $idSolicitud,
                'id_horario' => $idHorario,
                'estado' => 'atendida'
            ]);
        }

        if (!in_array($estadoActual, ['pendiente', 'contactada'], true)) {
            errorSolicitud(
                'La solicitud no se encuentra en un estado que permita asignarle una hora. Si está rechazada, primero debes reabrirla.',
                409
            );
        }

        /* Comprobar que exista una reserva válida para la solicitud. */
        $reservaValida = validarReservaSolicitud(
            $conexion,
            $solicitud,
            $idHorario
        );

        if (!$reservaValida) {
            errorSolicitud(
                'No se encontró una reserva válida para esta solicitud. Comprueba que el RUT, la fecha, el departamento y el servicio coincidan con la solicitud.',
                409
            );
        }

        $stmtActualizar = $conexion->prepare("
            UPDATE solicitudes_horas
            SET estado = 'atendida',
                fecha_solicitada = (
                    SELECT fecha
                    FROM horarios
                    WHERE id_horario = :id_horario
                )
            WHERE id_solicitud = :id_solicitud
              AND estado IN ('pendiente', 'contactada')
            RETURNING id_solicitud
        ");

        $stmtActualizar->execute([
            ':id_solicitud' => $idSolicitud,
            ':id_horario' => $idHorario
        ]);

        if (!$stmtActualizar->fetchColumn()) {
            errorSolicitud(
                'La solicitud cambió de estado y no pudo marcarse como atendida. Actualiza la pantalla e inténtalo nuevamente.',
                409
            );
        }

        $conexion->commit();

        responderSolicitud([
            'ok' => true,
            'mensaje' => 'La hora fue asignada y la solicitud quedó marcada como atendida. La reserva continúa pendiente de confirmación por parte del CESFAM.',
            'id_solicitud' => $idSolicitud,
            'id_horario' => $idHorario,
            'estado' => 'atendida',
            'estado_reserva' => 'pendiente_confirmacion'
        ]);
    }


    /* =====================================================
       9. RECHAZAR SOLICITUD
    ===================================================== */

    if ($accion === 'rechazar') {

        if ($estadoActual !== 'pendiente') {
            errorSolicitud(
                'Solo se pueden rechazar solicitudes pendientes.',
                409
            );
        }

        $stmtActualizar = $conexion->prepare("
            UPDATE solicitudes_horas
            SET estado = 'rechazada'
            WHERE id_solicitud = :id_solicitud
              AND estado = 'pendiente'
            RETURNING id_solicitud
        ");

        $stmtActualizar->execute([
            ':id_solicitud' => $idSolicitud
        ]);

        if (!$stmtActualizar->fetchColumn()) {
            errorSolicitud(
                'La solicitud cambió de estado y no pudo rechazarse. Actualiza la pantalla e inténtalo nuevamente.',
                409
            );
        }

        $conexion->commit();

        responderSolicitud([
            'ok' => true,
            'mensaje' => 'La solicitud fue rechazada correctamente.',
            'id_solicitud' => $idSolicitud,
            'estado' => 'rechazada'
        ]);
    }


    /* =====================================================
       10. REABRIR SOLICITUD
    ===================================================== */

    if ($accion === 'reabrir') {

        if ($estadoActual !== 'rechazada') {
            errorSolicitud(
                'Solo se pueden reabrir solicitudes rechazadas.',
                409
            );
        }

        $stmtActualizar = $conexion->prepare("
            UPDATE solicitudes_horas
            SET estado = 'pendiente'
            WHERE id_solicitud = :id_solicitud
              AND estado = 'rechazada'
            RETURNING id_solicitud
        ");

        $stmtActualizar->execute([
            ':id_solicitud' => $idSolicitud
        ]);

        if (!$stmtActualizar->fetchColumn()) {
            errorSolicitud(
                'La solicitud cambió de estado y no pudo reabrirse. Actualiza la pantalla e inténtalo nuevamente.',
                409
            );
        }

        $conexion->commit();

        responderSolicitud([
            'ok' => true,
            'mensaje' => 'La solicitud volvió a quedar pendiente.',
            'id_solicitud' => $idSolicitud,
            'estado' => 'pendiente'
        ]);
    }

    errorSolicitud(
        'No se pudo completar la acción solicitada.',
        400
    );

} catch (PDOException $e) {

    if (
        isset($conexion) &&
        $conexion instanceof PDO &&
        $conexion->inTransaction()
    ) {
        $conexion->rollBack();
    }

    error_log(
        'Error en api/gestionar_solicitud_horas.php: ' .
        $e->getMessage()
    );

    responderSolicitud([
        'ok' => false,
        'error' => 'No fue posible gestionar la solicitud en este momento.',
        'mensaje' => 'No fue posible gestionar la solicitud en este momento.'
    ], 500);

} catch (Throwable $e) {

    if (
        isset($conexion) &&
        $conexion instanceof PDO &&
        $conexion->inTransaction()
    ) {
        $conexion->rollBack();
    }

    error_log(
        'Error general en api/gestionar_solicitud_horas.php: ' .
        $e->getMessage()
    );

    responderSolicitud([
        'ok' => false,
        'error' => 'Ocurrió un error al procesar la solicitud.',
        'mensaje' => 'Ocurrió un error al procesar la solicitud.'
    ], 500);
}
