<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../componentes/auth_funcionario.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../componentes/correos_reservas.php';

date_default_timezone_set('America/Santiago');


/* =========================================================
   1. RESPUESTAS JSON
========================================================= */

function responder(
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
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


/* =========================================================
   2. UTILIDADES GENERALES
========================================================= */

function validarFecha(string $fecha): bool
{
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


function validarHora(string $hora): bool
{
    return preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $hora
    ) === 1;
}


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


function normalizarTexto($valor): string
{
    $valor = trim((string) $valor);

    return function_exists('mb_strtolower')
        ? mb_strtolower($valor, 'UTF-8')
        : strtolower($valor);
}


function fechaHoraEsFutura(
    string $fecha,
    string $hora
): bool {

    if (
        !validarFecha($fecha) ||
        !validarHora($hora)
    ) {
        return false;
    }

    $zona = new DateTimeZone('America/Santiago');

    $seleccion = DateTime::createFromFormat(
        '!Y-m-d H:i',
        $fecha . ' ' . $hora,
        $zona
    );

    if (!$seleccion) {
        return false;
    }

    return $seleccion > new DateTime('now', $zona);
}


function crearPlaceholders(
    array $valores,
    string $prefijo,
    array &$parametros
): string {

    $placeholders = [];

    $valores = array_values(
        array_unique(
            array_map('intval', $valores)
        )
    );

    foreach ($valores as $indice => $valor) {

        $nombre = ':' . $prefijo . $indice;

        $placeholders[] = $nombre;

        $parametros[$nombre] = $valor;
    }

    return !empty($placeholders)
        ? implode(', ', $placeholders)
        : 'NULL';
}


/* =========================================================
   3. IDENTIFICACIÓN
========================================================= */

function validarRutGestion(string $rut): bool
{
    $rut = strtoupper(
        preg_replace('/[^0-9Kk]/', '', $rut)
    );

    if (
        strlen($rut) < 2 ||
        strlen($rut) > 9
    ) {
        return false;
    }

    $cuerpo = substr($rut, 0, -1);

    $dv = substr($rut, -1);

    if (!ctype_digit($cuerpo)) {
        return false;
    }

    $suma = 0;

    $multiplicador = 2;

    for (
        $i = strlen($cuerpo) - 1;
        $i >= 0;
        $i--
    ) {

        $suma +=
            (int) $cuerpo[$i] *
            $multiplicador;

        $multiplicador++;

        if ($multiplicador > 7) {
            $multiplicador = 2;
        }
    }

    $resultado = 11 - ($suma % 11);

    if ($resultado === 11) {

        $dvCalculado = '0';

    } elseif ($resultado === 10) {

        $dvCalculado = 'K';

    } else {

        $dvCalculado = (string) $resultado;
    }

    return $dv === $dvCalculado;
}


function validarIdentificacionGestion(
    string $identificacion,
    string $tipo
): bool {

    $identificacion = trim($identificacion);

    $tipo = normalizarTexto($tipo);

    if ($identificacion === '') {
        return false;
    }

    if ($tipo === 'rut') {
        return validarRutGestion($identificacion);
    }

    if (
        $tipo === 'rut_extranjero' ||
        $tipo === 'pasaporte'
    ) {
        return preg_match(
            '/^[A-Za-z0-9.\-]{4,20}$/',
            $identificacion
        ) === 1;
    }

    return false;
}


/* =========================================================
   4. SESIÓN Y PERMISOS
========================================================= */

function obtenerFuncionarioActualGestion(
    PDO $conexion
): ?array {

    static $funcionarioCache = null;

    static $consultado = false;

    if ($consultado) {
        return $funcionarioCache;
    }

    $consultado = true;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $usuario = trim(
        (string) ($_SESSION['usuario_funcionario'] ?? '')
    );

    $rolSesion = normalizarTexto(
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

    $funcionario = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if (
        !$funcionario ||
        !esVerdadero($funcionario['activo'] ?? false)
    ) {
        return null;
    }

    $rolBD = normalizarTexto(
        $funcionario['rol'] ?? ''
    );

    if (!in_array($rolBD, $rolesPermitidos, true)) {
        return null;
    }

    $funcionario['rol_normalizado'] = $rolBD;

    $funcionarioCache = $funcionario;

    return $funcionarioCache;
}


function validarAccesoDepartamentoGestion(
    PDO $conexion,
    int $idDepartamento
): bool {

    $funcionario = obtenerFuncionarioActualGestion(
        $conexion
    );

    if (!$funcionario) {
        return false;
    }

    if (
        ($funcionario['rol_normalizado'] ?? '') ===
        'superadmin'
    ) {
        return true;
    }

    return (
        (int) ($funcionario['id_departamento'] ?? 0) ===
        $idDepartamento
    );
}


/* =========================================================
   5. SERVICIOS Y DEPARTAMENTOS
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

    $fila = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return $fila ?: null;
}


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
   6. SERVICIOS QUE COMPARTEN DISPONIBILIDAD
========================================================= */

function obtenerServiciosDisponibilidad(
    PDO $conexion,
    array $servicio
): array {

    $idServicio = (int) $servicio['id_servicio'];

    $idDepartamento = (int) $servicio['id_departamento'];

    /*
     * CASO 1:
     * Todos los servicios activos del departamento
     * comparten disponibilidad.
     */

    if (
        esVerdadero(
            $servicio['horarios_unificados'] ?? false
        )
    ) {

        $ids = obtenerServiciosDepartamento(
            $conexion,
            $idDepartamento
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
     * Servicios asociados mediante
     * unificacion_horarios_servicios.
     *
     * La estructura actual permite un conjunto compartido
     * por departamento, pero no varios grupos independientes
     * dentro del mismo departamento.
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

        ORDER BY u.id_servicio
    ");

    $stmt->execute([
        ':id_departamento' => $idDepartamento
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
   7. IDENTIFICAR SI LOS SERVICIOS COMPARTEN CUPOS
========================================================= */

function serviciosCompartenDisponibilidad(
    array $idsServicios
): bool {

    return count(
        array_unique(
            array_map('intval', $idsServicios)
        )
    ) > 1;
}


/* =========================================================
   8. BLOQUEOS TRANSACCIONALES
========================================================= */

/*
 * Las claves deben coincidir con las utilizadas
 * por api/crear_reserva.php.
 */

function obtenerClaveDisponibilidad(
    PDO $conexion,
    array $servicio,
    string $fecha,
    string $hora
): string {

    $idServicio = (int) $servicio['id_servicio'];

    $idDepartamento = (int) $servicio['id_departamento'];

    $idsCompartidos = obtenerServiciosDisponibilidad(
        $conexion,
        $servicio
    );

    if (
        esVerdadero(
            $servicio['horarios_unificados'] ?? false
        )
    ) {

        return
            'departamento-unificado|' .
            $idDepartamento . '|' .
            $fecha . '|' .
            $hora;
    }

    if (count($idsCompartidos) > 1) {

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


function bloquearClavesDisponibilidad(
    PDO $conexion,
    array $claves
): void {

    $claves = array_values(
        array_unique($claves)
    );

    /*
     * El orden estable reduce el riesgo de interbloqueos
     * cuando dos operaciones afectan a más de un bloque.
     */

    sort($claves, SORT_STRING);

    $stmt = $conexion->prepare("
        SELECT pg_advisory_xact_lock(hashtext(:clave))
    ");

    foreach ($claves as $clave) {

        $stmt->execute([
            ':clave' => $clave
        ]);
    }
}


function bloquearDisponibilidad(
    PDO $conexion,
    array $servicio,
    string $fecha,
    string $hora
): void {

    $clave = obtenerClaveDisponibilidad(
        $conexion,
        $servicio,
        $fecha,
        $hora
    );

    bloquearClavesDisponibilidad(
        $conexion,
        [$clave]
    );
}


/* =========================================================
   9. OBTENER HORARIO REAL DE UNA AGENDA
========================================================= */

/*
 * Recupera un horario existente.
 *
 * No genera cupos.
 *
 * El horario debe:
 *
 * - Tener id_agenda.
 * - Pertenecer al servicio y fecha de su agenda.
 * - Pertenecer a una agenda publicada.
 * - Tener segmento habilitado.
 * - Estar habilitado.
 * - Pertenecer a un servicio y departamento activos.
 *
 * No exige disponible_web porque esta función se utiliza
 * desde el panel interno de funcionarios.
 */

function obtenerHorarioDeAgenda(
    PDO $conexion,
    int $idHorario,
    array $idsServicios,
    bool $bloquearFila = false
): ?array {

    $parametros = [
        ':id_horario' => $idHorario
    ];

    $listaIds = crearPlaceholders(
        $idsServicios,
        'servicio_agenda',
        $parametros
    );

    $sql = "
        SELECT
            h.id_horario,
            h.id_servicio,
            h.id_agenda,
            h.fecha,

            LEFT(h.hora::text, 5) AS hora,

            LEFT(h.hora_fin::text, 5) AS hora_fin,

            h.estado AS estado_horario,
            h.habilitado,
            h.disponible_web,
            h.observacion,

            a.id_profesional,
            a.sector,
            a.estado AS estado_agenda,
            a.segmento_habilitado,

            s.nombre AS servicio_horario,
            s.activo AS servicio_activo,

            d.id_departamento,
            d.activo AS departamento_activo

        FROM horarios h

        INNER JOIN agendas a
            ON a.id_agenda = h.id_agenda
           AND a.id_servicio = h.id_servicio
           AND a.fecha = h.fecha

        INNER JOIN servicios s
            ON s.id_servicio = h.id_servicio

        INNER JOIN departamentos d
            ON d.id_departamento = s.id_departamento

        WHERE h.id_horario = :id_horario

          AND h.id_servicio IN ($listaIds)

          AND h.id_agenda IS NOT NULL

          AND LOWER(COALESCE(a.estado, '')) = 'publicada'

          AND a.segmento_habilitado = TRUE

          AND h.habilitado = TRUE

          AND s.activo = TRUE

          AND d.activo = TRUE
    ";

    if ($bloquearFila) {
        $sql .= ' FOR UPDATE OF h';
    }

    $stmt = $conexion->prepare($sql);

    $stmt->execute($parametros);

    $fila = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    return $fila ?: null;
}


/* =========================================================
   10. BUSCAR HORARIO POR FECHA Y HORA
========================================================= */

/*
 * Compatibilidad temporal.
 *
 * Solo busca horarios reales.
 *
 * Si hay más de una coincidencia, se exige enviar
 * id_nuevo_horario para evitar seleccionar un cupo ambiguo.
 */

function buscarHorarioAgendaPorFechaHora(
    PDO $conexion,
    array $idsServicios,
    string $fecha,
    string $hora
): array {

    $parametros = [
        ':fecha' => $fecha,
        ':hora' => $hora
    ];

    $listaIds = crearPlaceholders(
        $idsServicios,
        'servicio_busqueda',
        $parametros
    );

    $stmt = $conexion->prepare("
        SELECT h.id_horario

        FROM horarios h

        INNER JOIN agendas a
            ON a.id_agenda = h.id_agenda
           AND a.id_servicio = h.id_servicio
           AND a.fecha = h.fecha

        INNER JOIN servicios s
            ON s.id_servicio = h.id_servicio

        INNER JOIN departamentos d
            ON d.id_departamento = s.id_departamento

        WHERE h.id_servicio IN ($listaIds)

          AND h.fecha = :fecha

          AND LEFT(h.hora::text, 5) = :hora

          AND h.id_agenda IS NOT NULL

          AND LOWER(COALESCE(a.estado, '')) = 'publicada'

          AND a.segmento_habilitado = TRUE

          AND h.habilitado = TRUE

          AND LOWER(COALESCE(h.estado, '')) = 'disponible'

          AND s.activo = TRUE

          AND d.activo = TRUE

        ORDER BY h.id_horario

        LIMIT 2
    ");

    $stmt->execute($parametros);

    return array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}


/* =========================================================
   11. COMPROBAR RESERVA ACTIVA EN HORARIO EXACTO
========================================================= */

function existeReservaActivaEnHorario(
    PDO $conexion,
    int $idHorario,
    int $idReservaExcluir = 0
): bool {

    $stmt = $conexion->prepare("
        SELECT 1

        FROM reservas r

        WHERE r.id_horario = :id_horario

          AND r.id_reserva <> :id_reserva_excluir

          AND LOWER(COALESCE(r.estado, ''))
              IN (
                  'pendiente_confirmacion',
                  'confirmada',
                  'reagendada'
              )

        LIMIT 1
    ");

    $stmt->execute([
        ':id_horario' => $idHorario,
        ':id_reserva_excluir' => $idReservaExcluir
    ]);

    return (bool) $stmt->fetchColumn();
}


/* =========================================================
   12. COMPROBAR RESERVAS ACTIVAS DEL GRUPO COMPARTIDO
========================================================= */

function existeReservaActivaEnGrupo(
    PDO $conexion,
    array $idsServicios,
    string $fecha,
    string $hora,
    int $idReservaExcluir = 0,
    int $idHorarioExcluir = 0
): bool {

    $parametros = [
        ':fecha' => $fecha,
        ':hora' => $hora,
        ':id_reserva_excluir' => $idReservaExcluir,
        ':id_horario_excluir' => $idHorarioExcluir
    ];

    $listaIds = crearPlaceholders(
        $idsServicios,
        'servicio_reserva',
        $parametros
    );

    $stmt = $conexion->prepare("
        SELECT 1

        FROM reservas r

        INNER JOIN horarios h
            ON h.id_horario = r.id_horario

        WHERE h.id_servicio IN ($listaIds)

          AND h.id_agenda IS NOT NULL

          AND h.fecha = :fecha

          AND LEFT(h.hora::text, 5) = :hora

          AND h.id_horario <> :id_horario_excluir

          AND r.id_reserva <> :id_reserva_excluir

          AND LOWER(COALESCE(r.estado, ''))
              IN (
                  'pendiente_confirmacion',
                  'confirmada',
                  'reagendada'
              )

        LIMIT 1
    ");

    $stmt->execute($parametros);

    return (bool) $stmt->fetchColumn();
}


/* =========================================================
   13. COMPROBAR BLOQUEOS DE OTROS SERVICIOS COMPARTIDOS
========================================================= */

function existeOtroHorarioBloqueadoEnGrupo(
    PDO $conexion,
    array $idsServicios,
    string $fecha,
    string $hora,
    int $idHorarioExcluir
): bool {

    $parametros = [
        ':fecha' => $fecha,
        ':hora' => $hora,
        ':id_horario_excluir' => $idHorarioExcluir
    ];

    $listaIds = crearPlaceholders(
        $idsServicios,
        'servicio_bloqueo',
        $parametros
    );

    $stmt = $conexion->prepare("
        SELECT 1

        FROM horarios h

        INNER JOIN agendas a
            ON a.id_agenda = h.id_agenda
           AND a.id_servicio = h.id_servicio
           AND a.fecha = h.fecha

        WHERE h.id_servicio IN ($listaIds)

          AND h.fecha = :fecha

          AND LEFT(h.hora::text, 5) = :hora

          AND h.id_horario <> :id_horario_excluir

          AND h.id_agenda IS NOT NULL

          AND h.habilitado = TRUE

          AND LOWER(COALESCE(a.estado, '')) = 'publicada'

          AND a.segmento_habilitado = TRUE

          AND LOWER(COALESCE(h.estado, '')) = 'bloqueado'

        LIMIT 1
    ");

    $stmt->execute($parametros);

    return (bool) $stmt->fetchColumn();
}


/* =========================================================
   14. COMPROBAR CONFLICTOS DE DISPONIBILIDAD
========================================================= */

function validarDisponibilidadHorarioGestion(
    PDO $conexion,
    int $idHorario,
    array $idsServicios,
    string $fecha,
    string $hora,
    int $idReservaExcluir = 0
): void {

    /*
     * Primero se comprueba el horario exacto.
     */

    if (
        existeReservaActivaEnHorario(
            $conexion,
            $idHorario,
            $idReservaExcluir
        )
    ) {
        throw new RuntimeException(
            'El horario seleccionado ya tiene una reserva activa.'
        );
    }


    /*
     * Los conflictos entre diferentes horarios de la misma
     * fecha y hora solamente se consideran cuando existe
     * disponibilidad compartida.
     *
     * En servicios independientes se conservan los cupos
     * simultáneos de distintos profesionales.
     */

    if (
        serviciosCompartenDisponibilidad(
            $idsServicios
        )
    ) {

        if (
            existeReservaActivaEnGrupo(
                $conexion,
                $idsServicios,
                $fecha,
                $hora,
                $idReservaExcluir,
                $idHorario
            )
        ) {
            throw new RuntimeException(
                'Ese bloque horario ya está ocupado por otra reserva de un servicio que comparte disponibilidad.'
            );
        }

        if (
            existeOtroHorarioBloqueadoEnGrupo(
                $conexion,
                $idsServicios,
                $fecha,
                $hora,
                $idHorario
            )
        ) {
            throw new RuntimeException(
                'Ese bloque horario está bloqueado en otro servicio que comparte disponibilidad.'
            );
        }
    }
}


/* =========================================================
   15. LIBERAR HORARIO CUANDO CORRESPONDA
========================================================= */

function liberarHorarioSiCorresponde(
    PDO $conexion,
    int $idHorario
): void {

    /*
     * Se libera únicamente si:
     *
     * - El horario está bloqueado.
     * - No tiene observación de bloqueo manual.
     * - No tiene otra reserva pendiente o activa.
     * - El horario continúa habilitado.
     */

    $stmt = $conexion->prepare("
        UPDATE horarios h

        SET estado = 'disponible'

        WHERE h.id_horario = :id_horario

          AND LOWER(COALESCE(h.estado, '')) = 'bloqueado'

          AND h.habilitado = TRUE

          AND COALESCE(
              BTRIM(h.observacion),
              ''
          ) = ''

          AND NOT EXISTS (
              SELECT 1

              FROM reservas r

              WHERE r.id_horario = h.id_horario

                AND LOWER(COALESCE(r.estado, ''))
                    IN (
                        'pendiente_confirmacion',
                        'confirmada',
                        'reagendada'
                    )
          )
    ");

    $stmt->execute([
        ':id_horario' => $idHorario
    ]);
}


/* =========================================================
   16. FORMATO DE FECHAS Y HORAS PARA CORREOS
========================================================= */

function fechaCorreoGestion(string $fecha): string
{
    if (function_exists('fechaMostrarCorreoCESFAM')) {
        return fechaMostrarCorreoCESFAM($fecha);
    }

    $objeto = DateTime::createFromFormat(
        'Y-m-d',
        substr($fecha, 0, 10)
    );

    return $objeto
        ? $objeto->format('d/m/Y')
        : $fecha;
}


function horaCorreoGestion(string $hora): string
{
    if (function_exists('horaMostrarCorreoCESFAM')) {
        return horaMostrarCorreoCESFAM($hora);
    }

    return substr($hora, 0, 5);
}


/* =========================================================
   17. NOTIFICACIONES POR CORREO
========================================================= */

function notificarModificacionReserva(
    PDO $conexion,
    array $reserva,
    array $servicio,
    string $fechaAnterior,
    string $horaAnterior,
    string $fechaNueva,
    string $horaNueva
): void {

    /*
     * Correo al paciente.
     */

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
            ($reserva['id_reserva'] ?? 'N/A') .
            ': ' .
            $e->getMessage()
        );
    }


    /*
     * Correo a funcionarios.
     */

    try {

        enviarCorreoFuncionariosEventoReservaCESFAM(
            $conexion,
            $reserva,
            $servicio,
            'modificacion',
            [
                [
                    'Fecha anterior',
                    fechaCorreoGestion($fechaAnterior)
                ],
                [
                    'Hora anterior',
                    horaCorreoGestion($horaAnterior)
                ],
                [
                    'Nueva fecha',
                    fechaCorreoGestion($fechaNueva)
                ],
                [
                    'Nueva hora',
                    horaCorreoGestion($horaNueva)
                ]
            ]
        );

    } catch (Throwable $e) {

        error_log(
            'Error correo funcionarios por modificación de reserva #' .
            ($reserva['id_reserva'] ?? 'N/A') .
            ': ' .
            $e->getMessage()
        );
    }
}


function notificarCancelacionReserva(
    PDO $conexion,
    array $reserva,
    array $servicio,
    string $fecha,
    string $hora
): void {

    /*
     * Correo al paciente.
     */

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
            ($reserva['id_reserva'] ?? 'N/A') .
            ': ' .
            $e->getMessage()
        );
    }


    /*
     * Correo a funcionarios.
     *
     * La hora se formatea como hora, no como fecha.
     */

    try {

        enviarCorreoFuncionariosEventoReservaCESFAM(
            $conexion,
            $reserva,
            $servicio,
            'cancelacion',
            [
                [
                    'Fecha cancelada',
                    fechaCorreoGestion($fecha)
                ],
                [
                    'Hora cancelada',
                    horaCorreoGestion($hora)
                ]
            ]
        );

    } catch (Throwable $e) {

        error_log(
            'Error correo funcionarios por cancelación de reserva #' .
            ($reserva['id_reserva'] ?? 'N/A') .
            ': ' .
            $e->getMessage()
        );
    }
}


/* =========================================================
   18. RECIBIR Y VALIDAR ACCIÓN
========================================================= */

$accion = trim(
    (string) ($_POST['accion'] ?? '')
);

$accionesPermitidas = [
    'horarios',
    'obtener_reserva',
    'crear',
    'confirmar',
    'reagendar',
    'cancelar'
];

if (!in_array($accion, $accionesPermitidas, true)) {

    responder(
        false,
        'Acción no válida.',
        [],
        400
    );
}


/* =========================================================
   19. OBTENER RESERVA
========================================================= */

if ($accion === 'obtener_reserva') {

    $idReserva = (int) (
        $_POST['id_reserva'] ?? 0
    );

    if ($idReserva <= 0) {

        responder(
            false,
            'Reserva no válida.',
            [],
            400
        );
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
                s.id_departamento,

                h.fecha AS fecha_horario,

                LEFT(h.hora::text, 5) AS hora_horario,

                h.estado AS estado_horario,
                h.id_agenda

            FROM reservas r

            INNER JOIN horarios h
                ON h.id_horario = r.id_horario

            INNER JOIN agendas a
                ON a.id_agenda = h.id_agenda
               AND a.id_servicio = h.id_servicio
               AND a.fecha = h.fecha

            INNER JOIN servicios s
                ON s.id_servicio = r.id_servicio

            WHERE r.id_reserva = :id_reserva

              AND h.id_agenda IS NOT NULL

            LIMIT 1
        ");

        $stmt->execute([
            ':id_reserva' => $idReserva
        ]);

        $reserva = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$reserva) {

            responder(
                false,
                'La reserva no existe o no está asociada a una agenda.',
                [],
                404
            );
        }

        if (
            !validarAccesoDepartamentoGestion(
                $conexion,
                (int) $reserva['id_departamento']
            )
        ) {

            responder(
                false,
                'No tienes permisos para consultar esta reserva.',
                [],
                403
            );
        }

        foreach (
            ['id_reserva', 'id_horario', 'id_servicio'] as $campo
        ) {

            $reserva[$campo] =
                (int) $reserva[$campo];
        }

        $reserva['fecha_horario'] =
            (string) $reserva['fecha_horario'];

        $reserva['hora_horario'] =
            substr(
                (string) $reserva['hora_horario'],
                0,
                5
            );

        $reserva['correo'] =
            (string) ($reserva['correo'] ?? '');

        unset(
            $reserva['id_departamento'],
            $reserva['id_agenda']
        );

        responder(
            true,
            'Reserva obtenida correctamente.',
            [
                'reserva' => $reserva
            ]
        );

    } catch (Throwable $e) {

        error_log(
            'Error obtener reserva: ' .
            $e->getMessage()
        );

        responder(
            false,
            'No fue posible obtener los datos de la reserva.',
            [],
            500
        );
    }
}


/* =========================================================
   20. CONSULTAR HORARIOS DE AGENDAS PARA FUNCIONARIOS
========================================================= */

if ($accion === 'horarios') {

    $idServicio = (int) (
        $_POST['id_servicio'] ?? 0
    );

    $fecha = trim(
        (string) ($_POST['fecha'] ?? '')
    );

    if ($idServicio <= 0) {

        responder(
            false,
            'Debe seleccionar un servicio.',
            [],
            400
        );
    }

    if (!validarFecha($fecha)) {

        responder(
            false,
            'La fecha no es válida.',
            [],
            400
        );
    }

    try {

        $servicio = obtenerServicio(
            $conexion,
            $idServicio
        );

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

        if (
            !esVerdadero(
                $servicio['departamento_activo'] ?? false
            )
        ) {

            responder(
                false,
                'El departamento está desactivado.',
                [],
                400
            );
        }

        $idDepartamento = (int) (
            $servicio['id_departamento']
        );

        if (
            !validarAccesoDepartamentoGestion(
                $conexion,
                $idDepartamento
            )
        ) {

            responder(
                false,
                'No tienes permisos para consultar los horarios de este departamento.',
                [],
                403
            );
        }

        $idsServicios = obtenerServiciosDisponibilidad(
            $conexion,
            $servicio
        );

        $esCompartido = serviciosCompartenDisponibilidad(
            $idsServicios
        );

        $esUnificadoDepartamento = esVerdadero(
            $servicio['horarios_unificados'] ?? false
        );

        $esUnificado = (
            $esUnificadoDepartamento ||
            $esCompartido
        );


        /*
         * Placeholders separados para cada consulta.
         */

        $parametros = [];

        $listaIdsPrincipal = crearPlaceholders(
            $idsServicios,
            'servicio_principal',
            $parametros
        );


        /*
         * Los servicios independientes no deben bloquear
         * los cupos simultáneos de otros profesionales.
         *
         * Por eso las comprobaciones cruzadas solamente
         * se generan cuando existe disponibilidad compartida.
         */

        if ($esCompartido) {

            $parametrosReserva = [];

            $listaIdsReservas = crearPlaceholders(
                $idsServicios,
                'servicio_reserva',
                $parametrosReserva
            );

            $parametros = array_merge(
                $parametros,
                $parametrosReserva
            );

            $parametrosBloqueo = [];

            $listaIdsBloqueos = crearPlaceholders(
                $idsServicios,
                'servicio_bloqueo',
                $parametrosBloqueo
            );

            $parametros = array_merge(
                $parametros,
                $parametrosBloqueo
            );

            $condicionReservaCompartida = "
                EXISTS (
                    SELECT 1

                    FROM reservas r2

                    INNER JOIN horarios h2
                        ON h2.id_horario = r2.id_horario

                    WHERE h2.id_servicio IN ($listaIdsReservas)

                      AND h2.id_agenda IS NOT NULL

                      AND h2.fecha = h.fecha

                      AND LEFT(h2.hora::text, 5) =
                          LEFT(h.hora::text, 5)

                      AND h2.id_horario <> h.id_horario

                      AND LOWER(COALESCE(r2.estado, ''))
                          IN (
                              'pendiente_confirmacion',
                              'confirmada',
                              'reagendada'
                          )
                )
            ";

            $condicionBloqueoCompartido = "
                EXISTS (
                    SELECT 1

                    FROM horarios hb

                    INNER JOIN agendas ab
                        ON ab.id_agenda = hb.id_agenda
                       AND ab.id_servicio = hb.id_servicio
                       AND ab.fecha = hb.fecha

                    WHERE hb.id_servicio IN ($listaIdsBloqueos)

                      AND hb.fecha = h.fecha

                      AND LEFT(hb.hora::text, 5) =
                          LEFT(h.hora::text, 5)

                      AND hb.id_horario <> h.id_horario

                      AND hb.id_agenda IS NOT NULL

                      AND hb.habilitado = TRUE

                      AND LOWER(COALESCE(ab.estado, '')) =
                          'publicada'

                      AND ab.segmento_habilitado = TRUE

                      AND LOWER(COALESCE(hb.estado, '')) =
                          'bloqueado'
                )
            ";

        } else {

            $condicionReservaCompartida = 'FALSE';

            $condicionBloqueoCompartido = 'FALSE';
        }


        $parametros[':fecha'] = $fecha;

        $parametros[':servicio_preferido'] = $idServicio;

        $parametros[':id_departamento'] = $idDepartamento;


        /*
         * Los funcionarios pueden consultar horarios que
         * no estén publicados en la web.
         *
         * No se utiliza disponible_web como filtro.
         *
         * Sí se exige agenda publicada, segmento habilitado
         * y horario habilitado.
         */

        $sql = "
            SELECT
                h.id_horario,
                h.id_servicio AS id_servicio_horario,
                h.id_agenda,
                h.fecha,

                LEFT(h.hora::text, 5) AS hora,

                LEFT(h.hora_fin::text, 5) AS hora_fin,

                h.estado,
                h.habilitado,
                h.disponible_web,
                h.observacion,

                a.id_profesional,
                a.sector,

                s.nombre AS servicio_horario,

                CASE

                    /*
                     * El horario exacto debe estar disponible.
                     */

                    WHEN LOWER(COALESCE(h.estado, '')) <>
                        'disponible'
                    THEN FALSE

                    /*
                     * No puede tener una reserva pendiente o activa
                     * asociada al mismo id_horario.
                     */

                    WHEN EXISTS (
                        SELECT 1

                        FROM reservas r

                        WHERE r.id_horario = h.id_horario

                          AND LOWER(COALESCE(r.estado, ''))
                              IN (
                                  'pendiente_confirmacion',
                                  'confirmada',
                                  'reagendada'
                              )
                    )
                    THEN FALSE

                    /*
                     * Conflicto en otros servicios compartidos.
                     */

                    WHEN ($condicionReservaCompartida)
                    THEN FALSE

                    /*
                     * Bloqueo en otros servicios compartidos.
                     */

                    WHEN ($condicionBloqueoCompartido)
                    THEN FALSE

                    ELSE TRUE

                END AS disponible

            FROM horarios h

            INNER JOIN agendas a
                ON a.id_agenda = h.id_agenda
               AND a.id_servicio = h.id_servicio
               AND a.fecha = h.fecha

            INNER JOIN servicios s
                ON s.id_servicio = h.id_servicio

            INNER JOIN departamentos d
                ON d.id_departamento = s.id_departamento

            WHERE h.id_servicio IN ($listaIdsPrincipal)

              AND h.fecha = :fecha

              AND h.id_agenda IS NOT NULL

              AND LOWER(COALESCE(a.estado, '')) =
                  'publicada'

              AND a.segmento_habilitado = TRUE

              AND h.habilitado = TRUE

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

            $tipo = (
                $parametro === ':fecha'
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

        $filas = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $horarios = [];

        $horariosPorHora = [];


        foreach ($filas as $fila) {

            $hora = substr(
                (string) $fila['hora'],
                0,
                5
            );

            if (!validarHora($hora)) {
                continue;
            }

            $disponible = (
                esVerdadero($fila['disponible']) &&
                fechaHoraEsFutura($fecha, $hora)
            );

            $registro = [

                'id_horario' =>
                    (int) $fila['id_horario'],

                'id_agenda' =>
                    (int) $fila['id_agenda'],

                'id_servicio_horario' =>
                    (int) $fila['id_servicio_horario'],

                'hora' =>
                    $hora,

                'hora_fin' =>
                    $fila['hora_fin'] !== null
                        ? substr(
                            (string) $fila['hora_fin'],
                            0,
                            5
                        )
                        : null,

                'estado' =>
                    (string) $fila['estado'],

                'disponible' =>
                    $disponible,

                'disponible_web' =>
                    esVerdadero($fila['disponible_web']),

                'id_profesional' =>
                    $fila['id_profesional'] !== null
                        ? (int) $fila['id_profesional']
                        : null,

                'sector' =>
                    $fila['sector'],

                'servicio_horario' =>
                    $fila['servicio_horario']
            ];


            /*
             * Servicios compartidos:
             * se muestra un solo cupo por hora.
             *
             * Servicios independientes:
             * se mantienen todos los cupos, incluso si
             * distintos profesionales tienen la misma hora.
             */

            if ($esUnificado) {

                if (!isset($horariosPorHora[$hora])) {

                    $horariosPorHora[$hora] =
                        $registro;
                }

            } else {

                $horarios[] = $registro;
            }
        }


        if ($esUnificado) {

            $horarios = array_values(
                $horariosPorHora
            );
        }


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


        $totalDisponibles = 0;

        foreach ($horarios as $horario) {

            if ($horario['disponible']) {
                $totalDisponibles++;
            }
        }


        responder(
            true,
            'Horarios consultados desde agendas publicadas.',
            [
                'horarios' => $horarios,

                'total_horarios' =>
                    count($horarios),

                'total_disponibles' =>
                    $totalDisponibles,

                'horarios_unificados' =>
                    $esUnificado,

                'ids_servicios_disponibilidad' =>
                    $idsServicios
            ]
        );

    } catch (Throwable $e) {

        error_log(
            'Error consultar horarios de agenda: ' .
            $e->getMessage()
        );

        responder(
            false,
            'No fue posible consultar los horarios de las agendas.',
            [],
            500
        );
    }
}


/* =========================================================
   21. CREAR RESERVA DESDE ESTA API
========================================================= */

/*
 * Esta acción se conserva por compatibilidad.
 *
 * El formulario interno actualizado utiliza crear_reserva.php.
 *
 * Si se utiliza esta acción, debe recibir un id_horario real.
 *
 * Las reservas creadas por esta ruta también quedan
 * pendientes de confirmación.
 */

if ($accion === 'crear') {

    $nombre = trim(
        (string) ($_POST['nombre'] ?? '')
    );

    $rut = trim(
        (string) ($_POST['rut'] ?? '')
    );

    $tipoIdentificacion = normalizarTexto(
        $_POST['tipo_identificacion'] ?? 'rut'
    );

    $telefono = trim(
        (string) ($_POST['telefono'] ?? '')
    );

    $correo = trim(
        (string) ($_POST['correo'] ?? '')
    );

    $idServicio = (int) (
        $_POST['id_servicio'] ?? 0
    );

    $idHorario = (int) (
        $_POST['id_horario'] ?? 0
    );


    if (
        $nombre === '' ||
        $rut === '' ||
        $telefono === '' ||
        $idServicio <= 0 ||
        $idHorario <= 0
    ) {

        responder(
            false,
            'Complete los campos obligatorios y seleccione un horario disponible.',
            [],
            400
        );
    }

    if (
        mb_strlen($nombre, 'UTF-8') > 100 ||
        mb_strlen($telefono, 'UTF-8') > 20
    ) {

        responder(
            false,
            'El nombre o teléfono excede la longitud permitida.',
            [],
            400
        );
    }

    if (
        !validarIdentificacionGestion(
            $rut,
            $tipoIdentificacion
        )
    ) {

        responder(
            false,
            'La identificación ingresada no es válida.',
            [],
            400
        );
    }

    if (
        $correo !== '' &&
        !filter_var($correo, FILTER_VALIDATE_EMAIL)
    ) {

        responder(
            false,
            'El correo electrónico no es válido.',
            [],
            400
        );
    }

    try {

        $conexion->beginTransaction();

        $servicio = obtenerServicio(
            $conexion,
            $idServicio
        );

        if (
            !$servicio ||
            !esVerdadero(
                $servicio['servicio_activo'] ?? false
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El servicio seleccionado no existe o está desactivado.',
                [],
                404
            );
        }

        if (
            !esVerdadero(
                $servicio['departamento_activo'] ?? false
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El departamento está desactivado.',
                [],
                400
            );
        }

        if (
            !validarAccesoDepartamentoGestion(
                $conexion,
                (int) $servicio['id_departamento']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No tienes permisos para crear reservas en este departamento.',
                [],
                403
            );
        }


        $idsServicios = obtenerServiciosDisponibilidad(
            $conexion,
            $servicio
        );


        /*
         * Recuperar primero el horario para conocer
         * su fecha y hora.
         */

        $horarioPrevio = obtenerHorarioDeAgenda(
            $conexion,
            $idHorario,
            $idsServicios,
            false
        );

        if (!$horarioPrevio) {

            $conexion->rollBack();

            responder(
                false,
                'El horario seleccionado no pertenece a una agenda publicada y habilitada.',
                [],
                409
            );
        }

        $fecha = (string) $horarioPrevio['fecha'];

        $hora = substr(
            (string) $horarioPrevio['hora'],
            0,
            5
        );


        /*
         * Bloquear la disponibilidad.
         */

        bloquearDisponibilidad(
            $conexion,
            $servicio,
            $fecha,
            $hora
        );


        /*
         * Volver a obtener el horario con bloqueo de fila.
         */

        $horario = obtenerHorarioDeAgenda(
            $conexion,
            $idHorario,
            $idsServicios,
            true
        );

        if (
            !$horario ||
            normalizarTexto(
                $horario['estado_horario']
            ) !== 'disponible'
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El horario seleccionado ya no está disponible.',
                [],
                409
            );
        }


        if (!fechaHoraEsFutura($fecha, $hora)) {

            $conexion->rollBack();

            responder(
                false,
                'La hora seleccionada ya pasó.',
                [],
                400
            );
        }


        validarDisponibilidadHorarioGestion(
            $conexion,
            $idHorario,
            $idsServicios,
            $fecha,
            $hora
        );


        /*
         * Crear la reserva pendiente.
         */

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
                'pendiente_confirmacion',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP,
                :tipo_identificacion
            )
            RETURNING id_reserva
        ");

        $stmtInsert->execute([
            ':id_horario' => $idHorario,
            ':id_servicio' => $idServicio,
            ':nombre' => $nombre,
            ':rut' => $rut,
            ':correo' => $correo !== ''
                ? $correo
                : null,
            ':telefono' => $telefono,
            ':tipo_identificacion' => $tipoIdentificacion
        ]);

        $idReserva = (int) $stmtInsert->fetchColumn();


        /*
         * Bloquear el horario.
         */

        $stmtBloquear = $conexion->prepare("
            UPDATE horarios

            SET estado = 'bloqueado'

            WHERE id_horario = :id_horario

              AND LOWER(COALESCE(estado, '')) =
                  'disponible'

              AND habilitado = TRUE
        ");

        $stmtBloquear->execute([
            ':id_horario' => $idHorario
        ]);

        if ($stmtBloquear->rowCount() !== 1) {

            $conexion->rollBack();

            responder(
                false,
                'No fue posible bloquear el horario seleccionado.',
                [],
                409
            );
        }

        $conexion->commit();


        /*
         * Correo de recepción.
         *
         * No se envía una confirmación definitiva.
         */

        try {

            if (function_exists('enviarCorreoReservaCESFAM')) {

                $reservaCorreo = [
                    'id_reserva' => $idReserva,
                    'nombre' => $nombre,
                    'correo' => $correo !== ''
                        ? $correo
                        : null
                ];

                $servicioCorreo = [
                    'nombre' => $servicio['nombre'],
                    'nombre_departamento' =>
                        $servicio['nombre_departamento']
                ];

                enviarCorreoReservaCESFAM(
                    $reservaCorreo,
                    $servicioCorreo,
                    'Solicitud de hora recibida - CESFAM Eduardo Raggio Catemu',
                    'Recibimos su solicitud de hora',
                    'Su solicitud fue ingresada correctamente. El CESFAM deberá revisar y confirmar su atención. La hora todavía no se encuentra confirmada.',
                    [
                        [
                            'Número de registro',
                            (string) $idReserva
                        ],
                        [
                            'Departamento',
                            $servicio['nombre_departamento']
                        ],
                        [
                            'Servicio',
                            $servicio['nombre']
                        ],
                        [
                            'Fecha solicitada',
                            fechaCorreoGestion($fecha)
                        ],
                        [
                            'Hora solicitada',
                            horaCorreoGestion($hora)
                        ],
                        [
                            'Estado',
                            'Pendiente de confirmación'
                        ]
                    ],
                    'Espere el correo de confirmación definitiva del CESFAM antes de considerar su hora confirmada.'
                );
            }

        } catch (Throwable $e) {

            error_log(
                'Error enviando correo de recepción de reserva interna: ' .
                $e->getMessage()
            );
        }

        responder(
            true,
            'Solicitud de hora recibida correctamente. Está pendiente de confirmación.',
            [
                'id_reserva' => $idReserva,
                'id_horario' => $idHorario,
                'estado' => 'pendiente_confirmacion'
            ]
        );

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error crear reserva desde gestionar_reserva: ' .
            $e->getMessage()
        );

        if ($e instanceof RuntimeException) {

            responder(
                false,
                $e->getMessage(),
                [],
                409
            );
        }

        responder(
            false,
            'No fue posible crear la reserva.',
            [],
            500
        );
    }
}


/* =========================================================
   22. CONFIRMAR RESERVA
========================================================= */

if ($accion === 'confirmar') {

    $idReserva = (int) (
        $_POST['id_reserva'] ?? 0
    );

    if ($idReserva <= 0) {

        responder(
            false,
            'Reserva no válida.',
            [],
            400
        );
    }

    try {

        /*
         * 1. Obtener información preliminar.
         *
         * Primero necesitamos conocer el servicio,
         * fecha y hora para bloquear la disponibilidad.
         */

        $stmtPrevia = $conexion->prepare("
            SELECT
                r.id_reserva,
                r.id_horario,
                r.id_servicio,
                r.estado,

                h.fecha,
                LEFT(h.hora::text, 5) AS hora

            FROM reservas r

            INNER JOIN horarios h
                ON h.id_horario = r.id_horario

            INNER JOIN agendas a
                ON a.id_agenda = h.id_agenda
               AND a.id_servicio = h.id_servicio
               AND a.fecha = h.fecha

            WHERE r.id_reserva = :id_reserva

              AND h.id_agenda IS NOT NULL

            LIMIT 1
        ");

        $stmtPrevia->execute([
            ':id_reserva' => $idReserva
        ]);

        $reservaPrevia = $stmtPrevia->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$reservaPrevia) {

            responder(
                false,
                'La reserva no existe o no está asociada a una agenda.',
                [],
                404
            );
        }


        /*
         * 2. Obtener servicio y validar permisos.
         */

        $servicio = obtenerServicio(
            $conexion,
            (int) $reservaPrevia['id_servicio']
        );

        if (!$servicio) {

            responder(
                false,
                'No se encontró el servicio de la reserva.',
                [],
                404
            );
        }

        if (
            !validarAccesoDepartamentoGestion(
                $conexion,
                (int) $servicio['id_departamento']
            )
        ) {

            responder(
                false,
                'No tienes permisos para confirmar esta reserva.',
                [],
                403
            );
        }


        /*
         * 3. Obtener fecha y hora.
         */

        $fecha = substr(
            (string) $reservaPrevia['fecha'],
            0,
            10
        );

        $hora = substr(
            (string) $reservaPrevia['hora'],
            0,
            5
        );


        /*
         * 4. No permitir confirmar una hora que ya pasó.
         */

        if (!fechaHoraEsFutura($fecha, $hora)) {

            responder(
                false,
                'No se puede confirmar una reserva cuya fecha u hora ya pasó.',
                [],
                409
            );
        }


        /*
         * 5. Obtener y bloquear la disponibilidad.
         */

        $clave = obtenerClaveDisponibilidad(
            $conexion,
            $servicio,
            $fecha,
            $hora
        );

        $conexion->beginTransaction();

        bloquearClavesDisponibilidad(
            $conexion,
            [$clave]
        );


        /*
         * 6. Volver a consultar y bloquear las filas.
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

                h.fecha,
                LEFT(h.hora::text, 5) AS hora,
                h.estado AS estado_horario,
                h.habilitado,

                a.estado AS estado_agenda,
                a.segmento_habilitado,

                s.id_departamento,
                s.nombre AS nombre_servicio,
                s.activo AS servicio_activo,

                d.nombre AS nombre_departamento,
                d.activo AS departamento_activo

            FROM reservas r

            INNER JOIN horarios h
                ON h.id_horario = r.id_horario

            INNER JOIN agendas a
                ON a.id_agenda = h.id_agenda
               AND a.id_servicio = h.id_servicio
               AND a.fecha = h.fecha

            INNER JOIN servicios s
                ON s.id_servicio = r.id_servicio

            INNER JOIN departamentos d
                ON d.id_departamento = s.id_departamento

            WHERE r.id_reserva = :id_reserva

              AND h.id_agenda IS NOT NULL

            FOR UPDATE OF r, h, a
        ");

        $stmtReserva->execute([
            ':id_reserva' => $idReserva
        ]);

        $reserva = $stmtReserva->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$reserva) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva ya no existe o no está asociada a una agenda.',
                [],
                404
            );
        }


        /*
         * 7. Verificar que el horario no haya cambiado
         * mientras se obtenía el bloqueo.
         */

        if (
            (int) $reserva['id_horario'] !==
            (int) $reservaPrevia['id_horario']
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva cambió durante la operación. Actualice la pantalla e inténtelo nuevamente.',
                [],
                409
            );
        }


        /*
         * 8. Comprobar nuevamente los permisos.
         */

        if (
            !validarAccesoDepartamentoGestion(
                $conexion,
                (int) $reserva['id_departamento']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No tienes permisos para confirmar esta reserva.',
                [],
                403
            );
        }


        /*
         * 9. Comprobar el estado actual.
         */

        $estadoActual = normalizarTexto(
            $reserva['estado']
        );

        if ($estadoActual === 'confirmada') {

            $conexion->commit();

            responder(
                true,
                'La reserva ya se encuentra confirmada.'
            );
        }

        if ($estadoActual === 'cancelada') {

            $conexion->rollBack();

            responder(
                false,
                'Una reserva cancelada no puede confirmarse.',
                [],
                409
            );
        }

        if ($estadoActual !== 'pendiente_confirmacion') {

            $conexion->rollBack();

            responder(
                false,
                'Solamente se pueden confirmar reservas pendientes de confirmación.',
                [],
                409
            );
        }


        /*
         * 10. Validar nuevamente agenda y horario.
         */

        if (
            normalizarTexto(
                $reserva['estado_horario']
            ) !== 'bloqueado'
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El horario de esta reserva ya no se encuentra bloqueado correctamente.',
                [],
                409
            );
        }

        if (
            !esVerdadero($reserva['habilitado']) ||
            normalizarTexto($reserva['estado_agenda']) !== 'publicada' ||
            !esVerdadero($reserva['segmento_habilitado']) ||
            !esVerdadero($reserva['servicio_activo']) ||
            !esVerdadero($reserva['departamento_activo'])
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No se puede confirmar la reserva porque su agenda, segmento, horario o servicio ya no está habilitado.',
                [],
                409
            );
        }


        /*
         * 11. Actualizar únicamente reservas pendientes.
         */

        $stmtActualizar = $conexion->prepare("
            UPDATE reservas

            SET
                estado = 'confirmada',
                fecha_actualizacion = CURRENT_TIMESTAMP

            WHERE id_reserva = :id_reserva

              AND LOWER(
                  COALESCE(estado, '')
              ) = 'pendiente_confirmacion'

            RETURNING id_reserva
        ");

        $stmtActualizar->execute([
            ':id_reserva' => $idReserva
        ]);

        if (!$stmtActualizar->fetchColumn()) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva dejó de estar pendiente y no pudo confirmarse.',
                [],
                409
            );
        }


        /*
         * 12. Guardar la confirmación.
         */

        $conexion->commit();


        /*
         * 13. Enviar el correo definitivo al paciente.
         *
         * Esta es la única etapa que envía el correo
         * de confirmación definitiva.
         */

        try {

            $reservaCorreo = [
                'id_reserva' =>
                    (int) $reserva['id_reserva'],

                'nombre' =>
                    $reserva['nombre'],

                'correo' =>
                    $reserva['correo'],

                'rut' =>
                    $reserva['rut'],

                'telefono' =>
                    $reserva['telefono']
            ];

            $servicioCorreo = [
                'id_departamento' =>
                    (int) $reserva['id_departamento'],

                'nombre' =>
                    $reserva['nombre_servicio'],

                'nombre_servicio' =>
                    $reserva['nombre_servicio'],

                'nombre_departamento' =>
                    $reserva['nombre_departamento']
            ];

            enviarCorreoConfirmacionReserva(
                $reservaCorreo,
                $servicioCorreo,
                substr(
                    (string) $reserva['fecha'],
                    0,
                    10
                ),
                substr(
                    (string) $reserva['hora'],
                    0,
                    5
                )
            );

        } catch (Throwable $errorCorreo) {

            error_log(
                'Error enviando correo de confirmación de reserva #' .
                $idReserva .
                ': ' .
                $errorCorreo->getMessage()
            );
        }


        /*
         * 14. Respuesta final.
         */

        responder(
            true,
            'Reserva confirmada correctamente. Se procesó el correo de confirmación.',
            [
                'id_reserva' => $idReserva,
                'estado' => 'confirmada'
            ]
        );

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error confirmar reserva #' .
            $idReserva .
            ': ' .
            $e->getMessage()
        );

        responder(
            false,
            'No fue posible confirmar la reserva.',
            [],
            500
        );
    }
}


/* =========================================================
   23. REAGENDAR RESERVA
========================================================= */

if ($accion === 'reagendar') {

    $idReserva = (int) (
        $_POST['id_reserva'] ?? 0
    );

    $idNuevoHorario = (int) (
        $_POST['id_nuevo_horario'] ??
        $_POST['id_horario'] ??
        0
    );

    $fechaLegada = trim(
        (string) (
            $_POST['fecha_nueva'] ??
            $_POST['fecha'] ??
            ''
        )
    );

    $horaLegada = trim(
        (string) (
            $_POST['hora_nueva'] ??
            $_POST['hora'] ??
            ''
        )
    );

    if ($idReserva <= 0) {

        responder(
            false,
            'Reserva no válida.',
            [],
            400
        );
    }

    if (
        $idNuevoHorario <= 0 &&
        (
            !validarFecha($fechaLegada) ||
            !validarHora($horaLegada)
        )
    ) {

        responder(
            false,
            'Debe seleccionar un horario válido de una agenda publicada.',
            [],
            400
        );
    }

    try {

        $conexion->beginTransaction();


        /*
         * 1. Consultar la reserva actual sin bloquearla todavía.
         *
         * Primero necesitamos conocer la fecha y hora anteriores
         * para obtener las claves de bloqueo.
         */

        $stmtReservaPrevia = $conexion->prepare("
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

                h.fecha AS fecha_actual,

                LEFT(h.hora::text, 5) AS hora_actual

            FROM reservas r

            INNER JOIN horarios h
                ON h.id_horario = r.id_horario

            WHERE r.id_reserva = :id_reserva

            LIMIT 1
        ");

        $stmtReservaPrevia->execute([
            ':id_reserva' => $idReserva
        ]);

        $reservaPrevia = $stmtReservaPrevia->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$reservaPrevia) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva no existe.',
                [],
                404
            );
        }

        if (
            normalizarTexto($reservaPrevia['estado']) ===
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
         * 2. Consultar servicio y permisos.
         */

        $servicio = obtenerServicio(
            $conexion,
            (int) $reservaPrevia['id_servicio']
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

        if (
            !validarAccesoDepartamentoGestion(
                $conexion,
                (int) $servicio['id_departamento']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No tienes permisos para modificar esta reserva.',
                [],
                403
            );
        }

        $idsServicios = obtenerServiciosDisponibilidad(
            $conexion,
            $servicio
        );


        /*
         * 3. Compatibilidad con versiones antiguas del JS.
         */

        if ($idNuevoHorario <= 0) {

            $coincidencias = buscarHorarioAgendaPorFechaHora(
                $conexion,
                $idsServicios,
                $fechaLegada,
                $horaLegada
            );

            if (count($coincidencias) !== 1) {

                $conexion->rollBack();

                responder(
                    false,
                    count($coincidencias) > 1
                        ? 'Hay más de un horario para esa hora. Actualice la pantalla y seleccione el horario nuevamente.'
                        : 'No existe un horario en una agenda publicada para esa fecha y hora.',
                    [],
                    409
                );
            }

            $idNuevoHorario = $coincidencias[0];
        }


        /*
         * 4. Obtener el nuevo horario preliminarmente.
         */

        $horarioPrevio = obtenerHorarioDeAgenda(
            $conexion,
            $idNuevoHorario,
            $idsServicios,
            false
        );

        if (!$horarioPrevio) {

            $conexion->rollBack();

            responder(
                false,
                'El nuevo horario no pertenece a una agenda publicada y habilitada para el grupo de servicios.',
                [],
                409
            );
        }

        $fechaNueva = (string) $horarioPrevio['fecha'];

        $horaNueva = substr(
            (string) $horarioPrevio['hora'],
            0,
            5
        );

        $fechaAnterior = substr(
            (string) $reservaPrevia['fecha_actual'],
            0,
            10
        );

        $horaAnterior = substr(
            (string) $reservaPrevia['hora_actual'],
            0,
            5
        );


        /*
         * 5. No permitir conservar el mismo bloque.
         */

        if (
            $fechaAnterior === $fechaNueva &&
            $horaAnterior === $horaNueva
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
         * 6. Validar que la nueva fecha y hora sean futuras.
         */

        if (!fechaHoraEsFutura($fechaNueva, $horaNueva)) {

            $conexion->rollBack();

            responder(
                false,
                'La nueva fecha y hora deben ser futuras.',
                [],
                400
            );
        }


        /*
         * 7. Bloquear el bloque anterior y el nuevo.
         *
         * Las claves se bloquean en orden estable para reducir
         * conflictos entre operaciones simultáneas.
         */

        $claveAnterior = obtenerClaveDisponibilidad(
            $conexion,
            $servicio,
            $fechaAnterior,
            $horaAnterior
        );

        $claveNueva = obtenerClaveDisponibilidad(
            $conexion,
            $servicio,
            $fechaNueva,
            $horaNueva
        );

        bloquearClavesDisponibilidad(
            $conexion,
            [
                $claveAnterior,
                $claveNueva
            ]
        );


        /*
         * 8. Volver a consultar la reserva y bloquear sus filas.
         */

        $stmtReservaBloqueada = $conexion->prepare("
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

                h.fecha AS fecha_actual,

                LEFT(h.hora::text, 5) AS hora_actual

            FROM reservas r

            INNER JOIN horarios h
                ON h.id_horario = r.id_horario

            WHERE r.id_reserva = :id_reserva

            FOR UPDATE OF r, h
        ");

        $stmtReservaBloqueada->execute([
            ':id_reserva' => $idReserva
        ]);

        $reservaActual = $stmtReservaBloqueada->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$reservaActual) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva ya no existe.',
                [],
                404
            );
        }

        /*
         * Comprobar que nadie haya cambiado la reserva
         * entre la primera lectura y el bloqueo.
         */

        if (
            (int) $reservaActual['id_horario'] !==
            (int) $reservaPrevia['id_horario']
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva cambió mientras se procesaba la operación. Actualice la pantalla e inténtelo nuevamente.',
                [],
                409
            );
        }

        if (
            normalizarTexto($reservaActual['estado']) ===
            'cancelada'
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva fue cancelada y ya no puede reagendarse.',
                [],
                409
            );
        }


        /*
         * 9. Volver a consultar y bloquear el nuevo horario.
         */

        $nuevoHorario = obtenerHorarioDeAgenda(
            $conexion,
            $idNuevoHorario,
            $idsServicios,
            true
        );

        if (
            !$nuevoHorario ||
            normalizarTexto(
                $nuevoHorario['estado_horario']
            ) !== 'disponible'
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El nuevo horario ya no está disponible.',
                [],
                409
            );
        }


        /*
         * 10. Comprobar conflictos exactos y compartidos.
         */

        validarDisponibilidadHorarioGestion(
            $conexion,
            $idNuevoHorario,
            $idsServicios,
            $fechaNueva,
            $horaNueva,
            $idReserva
        );


        /*
         * 11. Ocupar el nuevo horario.
         */

        $stmtBloquearNuevo = $conexion->prepare("
            UPDATE horarios

            SET estado = 'bloqueado'

            WHERE id_horario = :id_horario

              AND LOWER(COALESCE(estado, '')) =
                  'disponible'

              AND habilitado = TRUE
        ");

        $stmtBloquearNuevo->execute([
            ':id_horario' => $idNuevoHorario
        ]);

        if ($stmtBloquearNuevo->rowCount() !== 1) {

            $conexion->rollBack();

            responder(
                false,
                'El nuevo horario dejó de estar disponible.',
                [],
                409
            );
        }


        /*
         * 12. Actualizar la reserva.
         *
         * Si la reserva estaba pendiente, continúa pendiente.
         * Si ya estaba confirmada, pasa a reagendada.
         */

        $estadoReservaAnterior = normalizarTexto(
            $reservaActual['estado']
        );

        $estadoNuevo = (
            $estadoReservaAnterior === 'pendiente_confirmacion'
        )
            ? 'pendiente_confirmacion'
            : 'reagendada';

        $stmtActualizar = $conexion->prepare("
            UPDATE reservas

            SET
                id_horario = :id_horario,
                estado = :estado,
                fecha_actualizacion = CURRENT_TIMESTAMP

            WHERE id_reserva = :id_reserva
        ");

        $stmtActualizar->execute([
            ':id_horario' => $idNuevoHorario,
            ':estado' => $estadoNuevo,
            ':id_reserva' => $idReserva
        ]);

        if ($stmtActualizar->rowCount() !== 1) {

            $conexion->rollBack();

            responder(
                false,
                'No fue posible actualizar la reserva.',
                [],
                500
            );
        }


        /*
         * 13. Liberar el horario anterior.
         */

        liberarHorarioSiCorresponde(
            $conexion,
            (int) $reservaActual['id_horario']
        );


        /*
         * 14. Guardar la transacción.
         */

        $conexion->commit();


        /*
         * 15. Notificaciones posteriores al COMMIT.
         *
         * Una reserva pendiente sigue pendiente.
         * Una reserva confirmada recibe correo de modificación.
         */

        $reservaActual['id_reserva'] = $idReserva;

        $reservaActual['correo'] =
            $reservaActual['correo'] ?? null;

        if ($estadoNuevo === 'pendiente_confirmacion') {

            try {

                enviarCorreoReservaCESFAM(
                    $reservaActual,
                    $servicio,
                    'Actualización de solicitud de hora - CESFAM Eduardo Raggio Catemu',
                    'Se actualizaron los datos de su solicitud',
                    'Los datos de su solicitud de hora fueron modificados. La atención todavía se encuentra pendiente de confirmación por parte del CESFAM.',
                    [
                        [
                            'Número de registro',
                            (string) $idReserva
                        ],
                        [
                            'Servicio',
                            $servicio['nombre']
                        ],
                        [
                            'Fecha anterior',
                            fechaCorreoGestion($fechaAnterior)
                        ],
                        [
                            'Hora anterior',
                            horaCorreoGestion($horaAnterior)
                        ],
                        [
                            'Nueva fecha solicitada',
                            fechaCorreoGestion($fechaNueva)
                        ],
                        [
                            'Nueva hora solicitada',
                            horaCorreoGestion($horaNueva)
                        ],
                        [
                            'Estado',
                            'Pendiente de confirmación'
                        ]
                    ],
                    'Espere la confirmación definitiva del CESFAM antes de considerar su hora confirmada.'
                );

            } catch (Throwable $e) {

                error_log(
                    'Error enviando correo de actualización de solicitud #' .
                    $idReserva . ': ' .
                    $e->getMessage()
                );
            }

        } else {

            notificarModificacionReserva(
                $conexion,
                $reservaActual,
                $servicio,
                $fechaAnterior,
                $horaAnterior,
                $fechaNueva,
                $horaNueva
            );
        }

        responder(
            true,
            'Reserva modificada correctamente.',
            [
                'id_reserva' => $idReserva,
                'id_horario' => $idNuevoHorario,
                'fecha' => $fechaNueva,
                'hora' => $horaNueva,
                'estado' => $estadoNuevo
            ]
        );

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error modificar reserva #' .
            $idReserva .
            ': ' .
            $e->getMessage()
        );

        if ($e instanceof RuntimeException) {

            responder(
                false,
                $e->getMessage(),
                [],
                409
            );
        }

        responder(
            false,
            'No fue posible modificar la reserva.',
            [],
            500
        );
    }
}


/* =========================================================
   24. CANCELAR RESERVA
========================================================= */

if ($accion === 'cancelar') {

    $idReserva = (int) (
        $_POST['id_reserva'] ?? 0
    );

    if ($idReserva <= 0) {

        responder(
            false,
            'Reserva no válida.',
            [],
            400
        );
    }

    try {

        $conexion->beginTransaction();


        /*
         * 1. Obtener información preliminar.
         *
         * Todavía no se bloquean las filas. Primero se obtiene
         * la fecha y hora para adquirir el bloqueo de disponibilidad.
         */

        $stmtPrevia = $conexion->prepare("
            SELECT
                r.id_reserva,
                r.id_horario,
                r.id_servicio,
                r.estado

            FROM reservas r

            WHERE r.id_reserva = :id_reserva

            LIMIT 1
        ");

        $stmtPrevia->execute([
            ':id_reserva' => $idReserva
        ]);

        $reservaPrevia = $stmtPrevia->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$reservaPrevia) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva no existe.',
                [],
                404
            );
        }


        /*
         * 2. Obtener servicio y permisos.
         */

        $servicio = obtenerServicio(
            $conexion,
            (int) $reservaPrevia['id_servicio']
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

        if (
            !validarAccesoDepartamentoGestion(
                $conexion,
                (int) $servicio['id_departamento']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No tienes permisos para cancelar esta reserva.',
                [],
                403
            );
        }


        /*
         * 3. Obtener fecha y hora del horario anterior.
         */

        $stmtHorarioPrevio = $conexion->prepare("
            SELECT
                fecha,
                LEFT(hora::text, 5) AS hora

            FROM horarios

            WHERE id_horario = :id_horario

            LIMIT 1
        ");

        $stmtHorarioPrevio->execute([
            ':id_horario' =>
                (int) $reservaPrevia['id_horario']
        ]);

        $horarioPrevio = $stmtHorarioPrevio->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$horarioPrevio) {

            $conexion->rollBack();

            responder(
                false,
                'No se encontró el horario asociado a la reserva.',
                [],
                404
            );
        }

        $fechaCancelada = substr(
            (string) $horarioPrevio['fecha'],
            0,
            10
        );

        $horaCancelada = substr(
            (string) $horarioPrevio['hora'],
            0,
            5
        );


        /*
         * 4. Bloquear la disponibilidad antes de modificar
         * la reserva o liberar el horario.
         */

        $clave = obtenerClaveDisponibilidad(
            $conexion,
            $servicio,
            $fechaCancelada,
            $horaCancelada
        );

        bloquearClavesDisponibilidad(
            $conexion,
            [$clave]
        );


        /*
         * 5. Volver a consultar y bloquear reserva y horario.
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

                LEFT(h.hora::text, 5) AS hora_cancelada,

                s.id_departamento,
                s.nombre AS nombre_servicio,

                d.nombre AS nombre_departamento

            FROM reservas r

            INNER JOIN horarios h
                ON h.id_horario = r.id_horario

            INNER JOIN servicios s
                ON s.id_servicio = r.id_servicio

            INNER JOIN departamentos d
                ON d.id_departamento = s.id_departamento

            WHERE r.id_reserva = :id_reserva

            FOR UPDATE OF r, h
        ");

        $stmtReserva->execute([
            ':id_reserva' => $idReserva
        ]);

        $reserva = $stmtReserva->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$reserva) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva ya no existe.',
                [],
                404
            );
        }


        /*
         * Evitar cancelar si otra operación cambió
         * la reserva antes de obtener el bloqueo.
         */

        if (
            (int) $reserva['id_horario'] !==
            (int) $reservaPrevia['id_horario']
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva cambió durante la operación. Actualice la pantalla e inténtelo nuevamente.',
                [],
                409
            );
        }


        if (
            normalizarTexto($reserva['estado']) ===
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


        /*
         * 6. Cancelar la reserva.
         */

        $stmtCancelar = $conexion->prepare("
            UPDATE reservas

            SET
                estado = 'cancelada',
                fecha_actualizacion = CURRENT_TIMESTAMP

            WHERE id_reserva = :id_reserva

              AND LOWER(COALESCE(estado, '')) <> 'cancelada'

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
         * 7. Liberar el horario si corresponde.
         *
         * La función revisa que no exista otra reserva
         * pendiente, confirmada o reagendada en ese cupo.
         */

        liberarHorarioSiCorresponde(
            $conexion,
            (int) $reserva['id_horario']
        );


        /*
         * 8. Guardar cambios.
         */

        $conexion->commit();


        /*
         * 9. Notificar por correo después del COMMIT.
         */

        $servicioCorreo = [
            'id_departamento' =>
                (int) $reserva['id_departamento'],

            'nombre' =>
                (string) $reserva['nombre_servicio'],

            'nombre_departamento' =>
                (string) $reserva['nombre_departamento']
        ];

        notificarCancelacionReserva(
            $conexion,
            $reserva,
            $servicioCorreo,
            substr(
                (string) $reserva['fecha_cancelada'],
                0,
                10
            ),
            substr(
                (string) $reserva['hora_cancelada'],
                0,
                5
            )
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
            $idReserva .
            ': ' .
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


/* =========================================================
   25. ACCIÓN NO PROCESADA
========================================================= */

responder(
    false,
    'No se pudo procesar la acción solicitada.',
    [],
    400
);