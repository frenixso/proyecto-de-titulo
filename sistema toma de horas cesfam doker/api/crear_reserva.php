<?php

/*
 * =========================================================
 * API CREAR RESERVA — CESFAM EDUARDO RAGGIO CATEMU
 * =========================================================
 *
 * Permite crear reservas desde:
 * - Sitio público.
 * - Formulario interno de funcionarios.
 *
 * REGLAS:
 * - Utiliza exclusivamente id_horario existentes.
 * - El horario debe pertenecer a una agenda publicada.
 * - Agenda, servicio y fecha deben coincidir.
 * - El horario y el segmento deben estar habilitados.
 * - Las reservas públicas requieren disponible_web = TRUE.
 * - Las reservas internas no requieren disponible_web = TRUE.
 * - No genera horarios automáticamente.
 * - No utiliza configuracion_horarios para generar cupos.
 * - Respeta la disponibilidad compartida entre servicios.
 * - Las reservas nuevas quedan pendientes de confirmación.
 *
 * Zona horaria: America/Santiago
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../componentes/correos_reservas.php';

date_default_timezone_set('America/Santiago');


/* =========================================================
   1. RESPUESTAS JSON
========================================================= */

function responderReserva(
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


function responderErrorReserva(
    string $mensaje,
    int $codigo = 400,
    array $datosExtra = []
): void {

    responderReserva(
        array_merge(
            [
                'ok' => false,
                'mensaje' => $mensaje
            ],
            $datosExtra
        ),
        $codigo
    );
}


/* =========================================================
   2. UTILIDADES GENERALES
========================================================= */

function esVerdaderoReserva($valor): bool
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


function longitudTextoReserva($texto): int
{
    return function_exists('mb_strlen')
        ? mb_strlen((string) $texto, 'UTF-8')
        : strlen((string) $texto);
}


function escaparHTMLCorreoReserva($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


function normalizarTextoReserva($valor): string
{
    $valor = trim((string) $valor);

    return function_exists('mb_strtolower')
        ? mb_strtolower($valor, 'UTF-8')
        : strtolower($valor);
}


function crearPlaceholdersReserva(
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
   3. VALIDAR RUT CHILENO
========================================================= */

function rutEsValidoReserva(string $rut): bool
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


/* =========================================================
   4. VALIDAR FECHA Y HORA
========================================================= */

function validarFechaHoraReserva(
    string $fecha,
    string $hora
): array {

    $zonaChile = new DateTimeZone(
        'America/Santiago'
    );

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $fecha
        )
    ) {

        return [
            'ok' => false,
            'mensaje' => 'La fecha seleccionada no es válida.'
        ];
    }

    $fechaObjeto = DateTime::createFromFormat(
        '!Y-m-d',
        $fecha,
        $zonaChile
    );

    $erroresFecha = DateTime::getLastErrors();

    if (
        $fechaObjeto === false ||
        (
            is_array($erroresFecha) &&
            (
                $erroresFecha['warning_count'] > 0 ||
                $erroresFecha['error_count'] > 0
            )
        ) ||
        $fechaObjeto->format('Y-m-d') !== $fecha
    ) {

        return [
            'ok' => false,
            'mensaje' => 'La fecha seleccionada no es válida.'
        ];
    }

    if (
        !preg_match(
            '/^([01]\d|2[0-3]):([0-5]\d)$/',
            $hora
        )
    ) {

        return [
            'ok' => false,
            'mensaje' => 'La hora seleccionada no es válida.'
        ];
    }

    $ahora = new DateTime(
        'now',
        $zonaChile
    );

    $fechaHoy = $ahora->format('Y-m-d');

    if ($fecha < $fechaHoy) {

        return [
            'ok' => false,
            'mensaje' => 'No se pueden crear reservas para fechas anteriores a hoy.'
        ];
    }

    /*
     * Regla de anticipación mínima:
     *
     * - Se exige al menos una hora.
     * - El resultado se redondea hacia adelante
     *   al siguiente intervalo de 30 minutos.
     *
     * Ejemplo:
     * Si la hora mínima calculada es 10:12,
     * se permite desde las 10:30.
     */

    if ($fecha === $fechaHoy) {

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

        if ($hora < $horaMinima->format('H:i')) {

            return [
                'ok' => false,
                'mensaje' => 'La hora seleccionada no cumple con el tiempo mínimo de anticipación. Seleccione una hora posterior.'
            ];
        }
    }

    return [
        'ok' => true,
        'fecha_objeto' => $fechaObjeto,
        'hora' => $hora
    ];
}


/* =========================================================
   5. OBTENER FUNCIONARIO DE LA SESIÓN
========================================================= */

function obtenerFuncionarioSesionReserva(
    PDO $conexion
): ?array {

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $usuario = trim(
        (string) (
            $_SESSION['usuario_funcionario'] ?? ''
        )
    );

    $rolSesion = normalizarTextoReserva(
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

    if (
        !in_array(
            $rolSesion,
            $rolesPermitidos,
            true
        )
    ) {
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
        !esVerdaderoReserva(
            $funcionario['activo'] ?? false
        )
    ) {
        return null;
    }

    $rolBD = normalizarTextoReserva(
        $funcionario['rol'] ?? ''
    );

    if (
        !in_array(
            $rolBD,
            $rolesPermitidos,
            true
        )
    ) {
        return null;
    }

    $funcionario['rol_normalizado'] = $rolBD;

    return $funcionario;
}


/* =========================================================
   6. OBTENER SERVICIOS CON DISPONIBILIDAD COMPARTIDA
========================================================= */

function obtenerServiciosDisponibilidadReserva(
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
     * Unificación mediante unificacion_horarios_servicios.
     *
     * La estructura actual no tiene id_grupo.
     * Por eso los servicios asociados al departamento
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
   7. BLOQUEO TRANSACCIONAL DE DISPONIBILIDAD
========================================================= */

/*
 * IMPORTANTE:
 *
 * Las claves son idénticas a las utilizadas por
 * api/gestionar_reserva.php.
 *
 * Esto permite coordinar las reservas públicas
 * con las reservas realizadas por funcionarios.
 */

function bloquearDisponibilidadReserva(
    PDO $conexion,
    int $idServicio,
    int $idDepartamento,
    bool $horariosUnificados,
    bool $esCompartido,
    string $fecha,
    string $hora
): void {

    if ($horariosUnificados) {

        $clave =
            'departamento-unificado|' .
            $idDepartamento . '|' .
            $fecha . '|' .
            $hora;

    } elseif ($esCompartido) {

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

    $stmt = $conexion->prepare("
        SELECT pg_advisory_xact_lock(
            hashtext(:clave)
        )
    ");

    $stmt->execute([
        ':clave' => $clave
    ]);
}


/* =========================================================
   8. OBTENER COLUMNA DE CORREO DE FUNCIONARIOS
========================================================= */

function obtenerColumnaCorreoFuncionariosReserva(
    PDO $conexion
): ?string {

    $stmt = $conexion->query("
        SELECT column_name

        FROM information_schema.columns

        WHERE table_schema = current_schema()

          AND table_name = 'funcionarios'

          AND column_name IN (
              'correo_institucional',
              'email_institucional',
              'correo',
              'correo_electronico',
              'email',
              'email_address'
          )

        ORDER BY
            CASE column_name
                WHEN 'correo_institucional' THEN 1
                WHEN 'email_institucional' THEN 2
                WHEN 'correo' THEN 3
                WHEN 'correo_electronico' THEN 4
                WHEN 'email' THEN 5
                WHEN 'email_address' THEN 6
                ELSE 99
            END

        LIMIT 1
    ");

    $columna = $stmt->fetchColumn();

    $permitidas = [
        'correo_institucional',
        'email_institucional',
        'correo',
        'correo_electronico',
        'email',
        'email_address'
    ];

    return (
        is_string($columna) &&
        in_array($columna, $permitidas, true)
    )
        ? $columna
        : null;
}


/* =========================================================
   9. ENVIAR CORREO A FUNCIONARIO
========================================================= */

function enviarCorreoNuevaReservaFuncionario(
    array $funcionario,
    array $reserva,
    array $servicio,
    string $fecha,
    string $hora
): bool {

    $correoDestino = trim(
        (string) ($funcionario['correo'] ?? '')
    );

    if (
        $correoDestino === '' ||
        !filter_var(
            $correoDestino,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    try {

        require_once __DIR__ . '/../config/correo.php';

        if (!function_exists('crearCorreo')) {

            error_log(
                'No existe crearCorreo() para las notificaciones CESFAM.'
            );

            return false;
        }

        $mail = crearCorreo();

        $nombreFuncionario = trim(
            ($funcionario['nombre'] ?? '') .
            ' ' .
            ($funcionario['apellido'] ?? '')
        );

        if ($nombreFuncionario === '') {
            $nombreFuncionario = 'Funcionario CESFAM';
        }

        $nombreServicio =
            $servicio['nombre_servicio'] ??
            'Servicio CESFAM';

        $nombreDepartamento =
            $servicio['nombre_departamento'] ??
            'CESFAM';

        $tipoIdentificacion = (string) (
            $reserva['tipo_identificacion'] ?? 'rut'
        );

        $etiquetas = [
            'rut' => 'RUT',
            'rut_extranjero' => 'RUT extranjero',
            'pasaporte' => 'Pasaporte'
        ];

        $etiqueta = $etiquetas[$tipoIdentificacion]
            ?? 'Identificación';

        $mail->addAddress(
            $correoDestino,
            $nombreFuncionario
        );

        $mail->Subject =
            'Nueva reserva pendiente de confirmación - CESFAM';

        $mail->Body = '
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width">
                <title>Nueva reserva pendiente CESFAM</title>
            </head>

            <body style="
                margin:0;
                padding:20px;
                background:#f4f6f8;
                font-family:Arial,Helvetica,sans-serif;
                color:#263238;
            ">

                <div style="
                    max-width:640px;
                    margin:0 auto;
                    background:#ffffff;
                    border-radius:10px;
                    overflow:hidden;
                    border:1px solid #e0e5e9;
                ">

                    <div style="
                        padding:22px;
                        background:#0879bd;
                        color:#ffffff;
                    ">

                        <h2 style="margin:0;">
                            Nueva reserva pendiente de confirmación
                        </h2>

                        <p style="margin:8px 0 0;">
                            Notificación para funcionarios del CESFAM
                        </p>

                    </div>

                    <div style="padding:24px;">

                        <p>
                            Hola ' .
                            escaparHTMLCorreoReserva($nombreFuncionario) .
                        ',
                        </p>

                        <p>
                            Se ha registrado una nueva solicitud de reserva
                            para
                            <strong>' .
                            escaparHTMLCorreoReserva($nombreDepartamento) .
                            '</strong>.
                            Esta solicitud requiere revisión y confirmación
                            por parte de un funcionario.
                        </p>

                        <table style="
                            width:100%;
                            border-collapse:collapse;
                            margin-top:20px;
                        ">

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>Número de reserva</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    #' .
                                    escaparHTMLCorreoReserva(
                                        $reserva['id_reserva'] ?? ''
                                    ) .
                                '
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>Estado</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    Pendiente de confirmación
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>Servicio</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    ' .
                                    escaparHTMLCorreoReserva($nombreServicio) .
                                '
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>Fecha</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    ' .
                                    escaparHTMLCorreoReserva($fecha) .
                                '
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>Hora</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    ' .
                                    escaparHTMLCorreoReserva($hora) .
                                '
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>Paciente</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    ' .
                                    escaparHTMLCorreoReserva(
                                        $reserva['nombre'] ?? ''
                                    ) .
                                '
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>' .
                                    escaparHTMLCorreoReserva($etiqueta) .
                                    '</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    ' .
                                    escaparHTMLCorreoReserva(
                                        $reserva['rut'] ?? ''
                                    ) .
                                '
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>Teléfono</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    ' .
                                    escaparHTMLCorreoReserva(
                                        $reserva['telefono'] ?? ''
                                    ) .
                                '
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    <strong>Correo del paciente</strong>
                                </td>

                                <td style="padding:10px;border-bottom:1px solid #e8ecef;">
                                    ' .
                                    escaparHTMLCorreoReserva(
                                        $reserva['correo'] ?: 'No informado'
                                    ) .
                                '
                                </td>
                            </tr>

                        </table>

                        <p style="
                            margin-top:24px;
                            font-size:13px;
                            color:#65727c;
                        ">
                            Este mensaje fue generado automáticamente
                            por el sistema de toma de horas del CESFAM.
                        </p>

                    </div>

                </div>

            </body>
            </html>
        ';

        $mail->AltBody =
            "Nueva reserva pendiente de confirmación - CESFAM\n\n" .
            "La reserva requiere revisión y confirmación.\n\n" .
            "Reserva: #" . ($reserva['id_reserva'] ?? '') . "\n" .
            "Estado: Pendiente de confirmación\n" .
            "Departamento: " . $nombreDepartamento . "\n" .
            "Servicio: " . $nombreServicio . "\n" .
            "Fecha: " . $fecha . "\n" .
            "Hora: " . $hora . "\n" .
            "Paciente: " . ($reserva['nombre'] ?? '') . "\n" .
            $etiqueta . ": " . ($reserva['rut'] ?? '') . "\n" .
            "Teléfono: " . ($reserva['telefono'] ?? '') . "\n" .
            "Correo: " . ($reserva['correo'] ?? 'No informado');

        $mail->send();

        return true;

    } catch (Throwable $e) {

        error_log(
            'Error enviando correo a funcionario ID ' .
            ($funcionario['id_funcionario'] ?? 'desconocido') .
            ': ' .
            $e->getMessage()
        );

        return false;
    }
}


/* =========================================================
   10. VALIDAR MÉTODO HTTP
========================================================= */

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
) {

    responderErrorReserva(
        'Método no permitido.',
        405
    );
}


/* =========================================================
   11. RECIBIR DATOS
========================================================= */

$nombre = trim(
    (string) ($_POST['nombre'] ?? '')
);

$rut = trim(
    (string) ($_POST['rut'] ?? '')
);

$tipoIdentificacion = normalizarTextoReserva(
    $_POST['tipo_identificacion'] ?? 'rut'
);

$telefono = trim(
    (string) ($_POST['telefono'] ?? '')
);

$correo = trim(
    (string) ($_POST['correo'] ?? '')
);

$servicio = trim(
    (string) (
        $_POST['id_servicio'] ??
        $_POST['servicio'] ??
        ''
    )
);

$fecha = trim(
    (string) ($_POST['fecha'] ?? '')
);

$hora = trim(
    (string) ($_POST['hora'] ?? '')
);

$idHorarioRecibido = trim(
    (string) ($_POST['id_horario'] ?? '')
);

$observacion = trim(
    (string) ($_POST['observacion'] ?? '')
);

$modo = normalizarTextoReserva(
    $_POST['modo'] ?? 'publico'
);


/* =========================================================
   12. VALIDAR MODO
========================================================= */

if (
    !in_array(
        $modo,
        ['publico', 'funcionario'],
        true
    )
) {

    responderErrorReserva(
        'Modo de creación no válido.',
        400
    );
}


/* =========================================================
   13. VALIDAR ID DEL HORARIO
========================================================= */

if (
    $idHorarioRecibido === '' ||
    !ctype_digit($idHorarioRecibido) ||
    (int) $idHorarioRecibido <= 0
) {

    responderErrorReserva(
        'No se recibió un cupo válido. Seleccione un horario disponible de una agenda.',
        400
    );
}

$idHorario = (int) $idHorarioRecibido;


/* =========================================================
   14. VALIDAR NOMBRE
========================================================= */

if (
    $nombre === '' ||
    longitudTextoReserva($nombre) < 2 ||
    longitudTextoReserva($nombre) > 150
) {

    responderErrorReserva(
        'El nombre debe tener entre 2 y 150 caracteres.',
        400
    );
}


/* =========================================================
   15. VALIDAR IDENTIFICACIÓN
========================================================= */

$rut = trim(
    preg_replace('/\s+/', ' ', $rut)
);

$tiposPermitidos = [
    'rut',
    'rut_extranjero',
    'pasaporte'
];

if (
    $rut === '' ||
    !in_array(
        $tipoIdentificacion,
        $tiposPermitidos,
        true
    )
) {

    responderErrorReserva(
        'La identificación o su tipo no son válidos.',
        400
    );
}

if (
    $tipoIdentificacion === 'rut' &&
    !rutEsValidoReserva($rut)
) {

    responderErrorReserva(
        'El RUT ingresado no es válido.',
        400
    );
}

if (
    $tipoIdentificacion === 'rut_extranjero' ||
    $tipoIdentificacion === 'pasaporte'
) {

    if (
        !preg_match(
            '/^[A-Za-z0-9.\-]{4,20}$/',
            $rut
        )
    ) {

        responderErrorReserva(
            'La identificación ingresada no es válida.',
            400
        );
    }
}


/* =========================================================
   16. VALIDAR TELÉFONO
========================================================= */

if (
    $telefono === '' ||
    longitudTextoReserva($telefono) < 7 ||
    longitudTextoReserva($telefono) > 30 ||
    !preg_match(
        '/^[0-9+\s().\-]+$/',
        $telefono
    )
) {

    responderErrorReserva(
        'El teléfono ingresado no es válido.',
        400
    );
}


/* =========================================================
   17. VALIDAR CORREO
========================================================= */

if ($correo !== '') {

    if (
        !filter_var(
            $correo,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        responderErrorReserva(
            'El correo electrónico ingresado no es válido.',
            400
        );
    }

} else {

    $correo = null;
}


/* =========================================================
   18. VALIDAR SERVICIO
========================================================= */

if (
    $servicio === '' ||
    !ctype_digit($servicio) ||
    (int) $servicio <= 0
) {

    responderErrorReserva(
        'Debe seleccionar un servicio válido.',
        400
    );
}

$idServicio = (int) $servicio;


/* =========================================================
   19. VALIDAR FECHA Y HORA
========================================================= */

$validacionFechaHora = validarFechaHoraReserva(
    $fecha,
    $hora
);

if (!$validacionFechaHora['ok']) {

    responderErrorReserva(
        $validacionFechaHora['mensaje'],
        400
    );
}

$fechaObjeto = $validacionFechaHora['fecha_objeto'];

$horaFormateada = $validacionFechaHora['hora'];


/* =========================================================
   20. VALIDAR OBSERVACIÓN
========================================================= */

if (
    longitudTextoReserva($observacion) > 1000
) {

    responderErrorReserva(
        'La observación no puede superar los 1000 caracteres.',
        400
    );
}


/* =========================================================
   21. VALIDAR SESIÓN DEL FUNCIONARIO
========================================================= */

$funcionario = null;

if ($modo === 'funcionario') {

    $funcionario = obtenerFuncionarioSesionReserva(
        $conexion
    );

    if (!$funcionario) {

        responderErrorReserva(
            'No autorizado. La sesión del funcionario no es válida o no tiene permisos para crear reservas.',
            401
        );
    }
}


/* =========================================================
   22. OBTENER SERVICIO Y DEPARTAMENTO
========================================================= */

$stmtServicio = $conexion->prepare("
    SELECT
        s.id_servicio,
        s.nombre AS nombre_servicio,
        s.descripcion AS descripcion_servicio,
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

$servicioBD = $stmtServicio->fetch(
    PDO::FETCH_ASSOC
);

if (!$servicioBD) {

    responderErrorReserva(
        'El servicio seleccionado no existe.',
        404
    );
}

if (
    !esVerdaderoReserva(
        $servicioBD['servicio_activo'] ?? false
    )
) {

    responderErrorReserva(
        'El servicio seleccionado no está disponible.',
        400
    );
}

if (
    !esVerdaderoReserva(
        $servicioBD['departamento_activo'] ?? false
    )
) {

    responderErrorReserva(
        'El departamento asociado al servicio no está disponible.',
        400
    );
}

$idDepartamento = (int) (
    $servicioBD['id_departamento']
);

$horariosUnificados = esVerdaderoReserva(
    $servicioBD['horarios_unificados'] ?? false
);


/* =========================================================
   23. VALIDAR PERMISOS POR DEPARTAMENTO
========================================================= */

if (
    $modo === 'funcionario' &&
    $funcionario &&
    ($funcionario['rol_normalizado'] ?? '') !== 'superadmin' &&
    (int) ($funcionario['id_departamento'] ?? 0) !== $idDepartamento
) {

    responderErrorReserva(
        'No tienes permisos para crear reservas en este departamento.',
        403
    );
}


/* =========================================================
   24. OBTENER SERVICIOS CON DISPONIBILIDAD COMPARTIDA
========================================================= */

$idsServiciosCompartidos =
    obtenerServiciosDisponibilidadReserva(
        $conexion,
        $idServicio,
        $idDepartamento,
        $horariosUnificados
    );

$esCompartido = (
    count($idsServiciosCompartidos) > 1
);


/* =========================================================
   25. PREPARAR PARÁMETROS SQL
========================================================= */

$parametrosServicios = [];

$listaServiciosSQL = crearPlaceholdersReserva(
    $idsServiciosCompartidos,
    'servicio',
    $parametrosServicios
);


/*
 * El paciente solamente puede reservar horarios
 * habilitados para la web.
 *
 * El funcionario puede utilizar horarios habilitados
 * aunque no estén publicados para el público.
 */

$filtroDisponibleWebSQL = (
    $modo === 'publico'
)
    ? 'AND h.disponible_web = TRUE'
    : '';


/* =========================================================
   26. INICIAR TRANSACCIÓN
========================================================= */

$correosFuncionariosEnviados = 0;

try {

    $conexion->beginTransaction();


    /* =====================================================
       26.1 BLOQUEO TRANSACCIONAL
    ===================================================== */

    bloquearDisponibilidadReserva(
        $conexion,
        $idServicio,
        $idDepartamento,
        $horariosUnificados,
        $esCompartido,
        $fecha,
        $horaFormateada
    );


    /* =====================================================
       26.2 VALIDAR CUPO EXACTO Y AGENDA
    ===================================================== */

    $sqlHorario = "
        SELECT
            h.id_horario,
            h.id_servicio AS id_servicio_horario,
            h.id_agenda,
            h.fecha,

            TO_CHAR(
                h.hora,
                'HH24:MI'
            ) AS hora,

            TO_CHAR(
                h.hora_fin,
                'HH24:MI'
            ) AS hora_fin,

            h.estado AS estado_horario,
            h.observacion AS observacion_horario,
            h.habilitado,
            h.disponible_web,

            a.id_profesional,
            a.estado AS estado_agenda,
            a.segmento_habilitado,
            a.sector,
            a.origen_multiprofesional

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

          AND h.id_servicio IN ($listaServiciosSQL)

          AND h.id_agenda IS NOT NULL

          AND h.fecha = :fecha

          AND TO_CHAR(
              h.hora,
              'HH24:MI'
          ) = :hora

          AND LOWER(
              COALESCE(h.estado, '')
          ) = 'disponible'

          AND h.habilitado = TRUE

          AND LOWER(
              COALESCE(a.estado, '')
          ) = 'publicada'

          AND a.segmento_habilitado = TRUE

          AND s.activo = TRUE

          AND d.activo = TRUE

          $filtroDisponibleWebSQL

        LIMIT 1

        FOR UPDATE OF h, a
    ";

    $stmtHorario = $conexion->prepare(
        $sqlHorario
    );

    $stmtHorario->bindValue(
        ':id_horario',
        $idHorario,
        PDO::PARAM_INT
    );

    foreach (
        $parametrosServicios as $parametro => $valor
    ) {

        $stmtHorario->bindValue(
            $parametro,
            $valor,
            PDO::PARAM_INT
        );
    }

    $stmtHorario->bindValue(
        ':fecha',
        $fecha,
        PDO::PARAM_STR
    );

    $stmtHorario->bindValue(
        ':hora',
        $horaFormateada,
        PDO::PARAM_STR
    );

    $stmtHorario->execute();

    $horario = $stmtHorario->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$horario) {

        $conexion->rollBack();

        responderErrorReserva(
            'El cupo seleccionado ya no está disponible o no pertenece a una agenda publicada y habilitada. Actualice los horarios y seleccione otro cupo.',
            409
        );
    }


    /* =====================================================
       26.3 COMPROBAR RESERVA ACTIVA DEL CUPO EXACTO
    ===================================================== */

    /*
     * Una reserva pendiente también ocupa el cupo.
     *
     * No debe permitirse una segunda reserva aunque
     * la primera todavía no haya sido confirmada.
     */

    $stmtReservaExistente = $conexion->prepare("
        SELECT id_reserva

        FROM reservas

        WHERE id_horario = :id_horario

          AND LOWER(
              COALESCE(estado, '')
          ) IN (
              'pendiente_confirmacion',
              'confirmada',
              'reagendada'
          )

        LIMIT 1

        FOR UPDATE
    ");

    $stmtReservaExistente->execute([
        ':id_horario' => $idHorario
    ]);

    $reservaExistente = $stmtReservaExistente->fetch(
        PDO::FETCH_ASSOC
    );

    if ($reservaExistente) {

        $conexion->rollBack();

        responderErrorReserva(
            'El horario seleccionado ya tiene una reserva activa o pendiente de confirmación.',
            409,
            [
                'id_reserva' =>
                    (int) $reservaExistente['id_reserva']
            ]
        );
    }


    /* =====================================================
       26.4 COMPROBAR DISPONIBILIDAD COMPARTIDA
    ===================================================== */

    /*
     * Esta comprobación solamente se realiza cuando
     * hay más de un servicio compartiendo cupos.
     *
     * Si el servicio es independiente, dos profesionales
     * pueden tener horarios simultáneos.
     */

    if ($esCompartido) {

        $parametrosConflicto = [];

        $listaServiciosConflicto =
            crearPlaceholdersReserva(
                $idsServiciosCompartidos,
                'servicio_conflicto',
                $parametrosConflicto
            );

        $sqlConflicto = "
            SELECT h.id_horario

            FROM horarios h

            INNER JOIN agendas a
                ON a.id_agenda = h.id_agenda
               AND a.id_servicio = h.id_servicio
               AND a.fecha = h.fecha

            WHERE h.id_servicio IN (
                $listaServiciosConflicto
            )

              AND h.fecha = :fecha_compartida

              AND TO_CHAR(
                  h.hora,
                  'HH24:MI'
              ) = :hora_compartida

              AND h.id_horario <> :id_horario_excluir

              AND h.id_agenda IS NOT NULL

              AND h.habilitado = TRUE

              AND LOWER(
                  COALESCE(a.estado, '')
              ) = 'publicada'

              AND a.segmento_habilitado = TRUE

              AND (
                    LOWER(
                        COALESCE(h.estado, '')
                    ) = 'bloqueado'

                    OR EXISTS (
                        SELECT 1

                        FROM reservas r

                        WHERE r.id_horario = h.id_horario

                          AND LOWER(
                              COALESCE(r.estado, '')
                          ) IN (
                              'pendiente_confirmacion',
                              'confirmada',
                              'reagendada'
                          )
                    )
              )

            LIMIT 1

            FOR UPDATE OF h
        ";

        $stmtConflicto = $conexion->prepare(
            $sqlConflicto
        );

        foreach (
            $parametrosConflicto as $parametro => $valor
        ) {

            $stmtConflicto->bindValue(
                $parametro,
                $valor,
                PDO::PARAM_INT
            );
        }

        $stmtConflicto->bindValue(
            ':fecha_compartida',
            $fecha,
            PDO::PARAM_STR
        );

        $stmtConflicto->bindValue(
            ':hora_compartida',
            $horaFormateada,
            PDO::PARAM_STR
        );

        $stmtConflicto->bindValue(
            ':id_horario_excluir',
            $idHorario,
            PDO::PARAM_INT
        );

        $stmtConflicto->execute();

        if ($stmtConflicto->fetch(PDO::FETCH_ASSOC)) {

            $conexion->rollBack();

            responderErrorReserva(
                'El horario seleccionado ya no está disponible para el grupo de servicios. Seleccione otro horario.',
                409
            );
        }
    }


    /* =====================================================
       26.5 INSERTAR RESERVA PENDIENTE
    ===================================================== */

    /*
     * La reserva no queda confirmada en este momento.
     *
     * Primero se registra como pendiente_confirmacion.
     * Posteriormente, un funcionario autorizado deberá
     * confirmar la atención desde gestionar_reserva.php.
     */

    $stmtCrearReserva = $conexion->prepare("
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

        RETURNING
            id_reserva,
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
    ");

    $stmtCrearReserva->execute([
        ':id_horario' => $idHorario,
        ':id_servicio' => $idServicio,
        ':nombre' => $nombre,
        ':rut' => $rut,
        ':correo' => $correo,
        ':telefono' => $telefono,
        ':tipo_identificacion' => $tipoIdentificacion
    ]);

    $reserva = $stmtCrearReserva->fetch(
        PDO::FETCH_ASSOC
    );

    if (!$reserva) {

        throw new RuntimeException(
            'No fue posible obtener la reserva creada.'
        );
    }


    /* =====================================================
       26.6 BLOQUEAR EL CUPO RESERVADO
    ===================================================== */

    /*
     * Aunque la reserva todavía no esté confirmada,
     * el cupo queda bloqueado inmediatamente.
     *
     * Esto evita que otra persona pueda seleccionarlo
     * mientras el CESFAM revisa la solicitud.
     */

    $stmtActualizarHorario = $conexion->prepare("
        UPDATE horarios

        SET estado = 'bloqueado'

        WHERE id_horario = :id_horario

          AND LOWER(
              COALESCE(estado, '')
          ) = 'disponible'

          AND habilitado = TRUE
    ");

    $stmtActualizarHorario->execute([
        ':id_horario' => $idHorario
    ]);

    if ($stmtActualizarHorario->rowCount() !== 1) {

        throw new RuntimeException(
            'No fue posible actualizar el estado del cupo reservado.'
        );
    }


    /* =====================================================
       26.7 NOTIFICACIONES INTERNAS
    ===================================================== */

    $stmtFuncionariosNotificacion = $conexion->prepare("
        SELECT id_funcionario

        FROM funcionarios

        WHERE id_departamento = :id_departamento

          AND activo = TRUE

        ORDER BY id_funcionario
    ");

    $stmtFuncionariosNotificacion->execute([
        ':id_departamento' => $idDepartamento
    ]);

    $tituloNotificacion =
        'Reserva pendiente de confirmación';

    $fechaNotificacion = $fechaObjeto->format('d/m/Y');

    $mensajeNotificacion =
        'Se registró una solicitud de reserva para "' .
        $servicioBD['nombre_servicio'] .
        '" el ' .
        $fechaNotificacion .
        ' a las ' .
        $horaFormateada .
        '. Requiere revisión y confirmación del CESFAM.';

    $stmtCrearNotificacion = $conexion->prepare("
        INSERT INTO notificaciones (
            id_funcionario,
            id_reserva,
            titulo,
            mensaje,
            leida,
            fecha_creacion
        )

        VALUES (
            :id_funcionario,
            :id_reserva,
            :titulo,
            :mensaje,
            FALSE,
            CURRENT_TIMESTAMP
        )
    ");

    while (
        $filaFuncionario =
            $stmtFuncionariosNotificacion->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $stmtCrearNotificacion->execute([
            ':id_funcionario' =>
                $filaFuncionario['id_funcionario'],

            ':id_reserva' =>
                $reserva['id_reserva'],

            ':titulo' =>
                $tituloNotificacion,

            ':mensaje' =>
                $mensajeNotificacion
        ]);
    }


    /* =====================================================
       26.8 CONFIRMAR TRANSACCIÓN
    ===================================================== */

    $conexion->commit();


    /* =====================================================
       27. CORREO DE RECEPCIÓN AL PACIENTE
    ===================================================== */

    /*
     * Este correo NO confirma la hora.
     *
     * Solamente informa que la solicitud fue recibida
     * y que queda pendiente de revisión por el CESFAM.
     *
     * La confirmación definitiva se enviará cuando
     * un funcionario confirme la reserva.
     */

    try {

        if (function_exists('enviarCorreoReservaCESFAM')) {

            enviarCorreoReservaCESFAM(
                $reserva,
                $servicioBD,
                'Solicitud de hora recibida - CESFAM Eduardo Raggio Catemu',
                'Recibimos su solicitud de hora',
                'Su solicitud de hora fue ingresada correctamente al sistema. El CESFAM deberá revisar y confirmar su atención. La hora todavía no se encuentra confirmada.',
                [
                    [
                        'Número de registro',
                        (string) $reserva['id_reserva']
                    ],
                    [
                        'Departamento',
                        $servicioBD['nombre_departamento']
                    ],
                    [
                        'Servicio',
                        $servicioBD['nombre_servicio']
                    ],
                    [
                        'Fecha solicitada',
                        fechaMostrarCorreoCESFAM(
                            $fechaObjeto->format('Y-m-d')
                        )
                    ],
                    [
                        'Hora solicitada',
                        $horaFormateada
                    ],
                    [
                        'Estado',
                        'Pendiente de confirmación'
                    ]
                ],
                'Por favor, espere el correo de confirmación definitiva del CESFAM antes de considerar su hora confirmada.'
            );

        } else {

            error_log(
                'No se encontró la función enviarCorreoReservaCESFAM() para enviar el correo de recepción.'
            );
        }

    } catch (Throwable $errorCorreoPaciente) {

        error_log(
            'Error enviando correo de recepción de reserva al paciente: ' .
            $errorCorreoPaciente->getMessage()
        );
    }


    /* =====================================================
       28. CORREOS A FUNCIONARIOS
    ===================================================== */

    try {

        $columnaCorreo =
            obtenerColumnaCorreoFuncionariosReserva(
                $conexion
            );

        if ($columnaCorreo === null) {

            error_log(
                'No se enviaron correos a funcionarios: ' .
                'no se encontró una columna de correo reconocida.'
            );

        } else {

            /*
             * El nombre de columna procede de una lista
             * blanca obtenida desde information_schema.
             */

            $sqlFuncionariosCorreo = "
                SELECT
                    id_funcionario,
                    nombre,
                    apellido,
                    \"$columnaCorreo\" AS correo

                FROM funcionarios

                WHERE id_departamento = :id_departamento

                  AND activo = TRUE

                  AND NULLIF(
                      TRIM(
                          CAST(
                              \"$columnaCorreo\" AS TEXT
                          )
                      ),
                      ''
                  ) IS NOT NULL

                ORDER BY id_funcionario
            ";

            $stmtFuncionariosCorreo = $conexion->prepare(
                $sqlFuncionariosCorreo
            );

            $stmtFuncionariosCorreo->execute([
                ':id_departamento' => $idDepartamento
            ]);

            $funcionariosCorreo =
                $stmtFuncionariosCorreo->fetchAll(
                    PDO::FETCH_ASSOC
                );

            foreach (
                $funcionariosCorreo as $funcionarioCorreo
            ) {

                $enviado = enviarCorreoNuevaReservaFuncionario(
                    $funcionarioCorreo,
                    $reserva,
                    $servicioBD,
                    $fechaObjeto->format('d/m/Y'),
                    $horaFormateada
                );

                if ($enviado) {
                    $correosFuncionariosEnviados++;
                }
            }
        }

    } catch (Throwable $errorCorreosFuncionarios) {

        error_log(
            'Error general enviando correos a funcionarios: ' .
            $errorCorreosFuncionarios->getMessage()
        );
    }


    /* =====================================================
       29. RESPUESTA FINAL
    ===================================================== */

    responderReserva(
        [
            'ok' => true,

            'mensaje' =>
                'Solicitud de hora recibida correctamente. Está pendiente de confirmación por parte del CESFAM.',

            'reserva' => $reserva,

            'horario' => [

                'id_horario' =>
                    (int) $horario['id_horario'],

                'id_agenda' =>
                    (int) $horario['id_agenda'],

                'id_servicio' =>
                    (int) $horario['id_servicio_horario'],

                'fecha' =>
                    $horario['fecha'],

                'hora' =>
                    $horario['hora'],

                'hora_fin' =>
                    $horario['hora_fin'],

                'estado' =>
                    'bloqueado'
            ],

            'departamento' => [

                'id_departamento' =>
                    $idDepartamento,

                'nombre' =>
                    $servicioBD['nombre_departamento'],

                'horarios_unificados' =>
                    $horariosUnificados
            ],

            'servicio' => [

                'id_servicio' =>
                    $servicioBD['id_servicio'],

                'nombre' =>
                    $servicioBD['nombre_servicio']
            ],

            'modo' =>
                $modo,

            'funcionario' => $funcionario
                ? [

                    'id_funcionario' =>
                        (int) $funcionario['id_funcionario'],

                    'nombre' =>
                        $funcionario['nombre'],

                    'apellido' =>
                        $funcionario['apellido'],

                    'usuario' =>
                        $funcionario['usuario'],

                    'rol' =>
                        $funcionario['rol']

                ]
                : null,

            /*
             * Indica los correos que PHPMailer aceptó enviar.
             * No garantiza la entrega final en la bandeja.
             */

            'correos_funcionarios_enviados' =>
                $correosFuncionariosEnviados
        ]
    );


} catch (PDOException $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    $sqlState = $e->errorInfo[0] ?? $e->getCode();

    error_log(
        'Error en api/crear_reserva.php: ' .
        $e->getMessage()
    );

    if ($sqlState === '23505') {

        responderErrorReserva(
            'El horario seleccionado ya no está disponible.',
            409
        );
    }

    if ($sqlState === '23503') {

        responderErrorReserva(
            'No se pudo relacionar la reserva con el cupo seleccionado.',
            409
        );
    }

    responderErrorReserva(
        'Ocurrió un error al crear la reserva.',
        500
    );


} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log(
        'Error inesperado en api/crear_reserva.php: ' .
        $e->getMessage()
    );

    responderErrorReserva(
        'Ocurrió un error inesperado al crear la reserva.',
        500
    );
}