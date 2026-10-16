<?php

/*
 * =========================================================
 * API CREAR RESERVA
 * =========================================================
 *
 * Permite crear reservas:
 *
 * - Desde el sitio público
 * - Desde el sistema de funcionarios
 *
 * Roles permitidos para modo funcionario:
 * - superadmin
 * - administrador
 * - funcionario
 *
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../config/conexion.php';


/*
 * =========================================================
 * FUNCIONES DE RESPUESTA
 * =========================================================
 */

function responder($datos, $codigo = 200)
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


function responderError($mensaje, $codigo = 400, $datosExtra = [])
{
    responder(
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


/*
 * =========================================================
 * FUNCIONES DE VALIDACIÓN
 * =========================================================
 */

function longitudTexto($texto)
{
    return function_exists('mb_strlen')
        ? mb_strlen($texto, 'UTF-8')
        : strlen($texto);
}


/*
 * ---------------------------------------------------------
 * VALIDAR RUT CHILENO
 * ---------------------------------------------------------
 */

function rutEsValido($rut)
{
    $rut = strtoupper(
        preg_replace(
            '/[^0-9Kk]/',
            '',
            $rut
        )
    );

    if (strlen($rut) < 2) {
        return false;
    }

    $cuerpo = substr(
        $rut,
        0,
        -1
    );

    $dv = substr(
        $rut,
        -1
    );

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
            intval($cuerpo[$i])
            * $multiplicador;

        $multiplicador++;

        if ($multiplicador > 7) {
            $multiplicador = 2;
        }
    }

    $resto = $suma % 11;

    $resultado = 11 - $resto;

    if ($resultado === 11) {
        $dvCalculado = '0';

    } elseif ($resultado === 10) {
        $dvCalculado = 'K';

    } else {
        $dvCalculado = (string) $resultado;
    }

    return $dv === $dvCalculado;
}


/*
 * ---------------------------------------------------------
 * LIMPIAR IDENTIFICACIÓN
 * ---------------------------------------------------------
 */

function limpiarIdentificacion($identificacion)
{
    return trim(
        preg_replace(
            '/\s+/',
            ' ',
            $identificacion
        )
    );
}


/*
 * ---------------------------------------------------------
 * NORMALIZAR HORA DE BASE DE DATOS
 * ---------------------------------------------------------
 */

function normalizarHoraConfiguracion($hora)
{
    if ($hora === null) {
        return null;
    }

    $hora = trim(
        (string) $hora
    );

    if ($hora === '') {
        return null;
    }

    if (
        preg_match(
            '/^([01]\d|2[0-3]):([0-5]\d)$/',
            $hora,
            $coincidencia
        )
    ) {
        return $coincidencia[1] . ':' . $coincidencia[2];
    }

    if (
        preg_match(
            '/^([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d+)?$/',
            $hora,
            $coincidencia
        )
    ) {
        return $coincidencia[1] . ':' . $coincidencia[2];
    }

    return null;
}


/*
 * =========================================================
 * VALIDAR HORARIO SEGÚN CONFIGURACIÓN
 * =========================================================
 */

function validarHorarioConfigurado(
    $conexion,
    $idServicio,
    $fechaObjeto,
    $hora
) {

    $zonaHoraria = new DateTimeZone('America/Santiago');

    $ahora = new DateTime(
        'now',
        $zonaHoraria
    );

    $fechaHoy = $ahora->format('Y-m-d');
    $fechaSolicitada = $fechaObjeto->format('Y-m-d');

    if ($fechaSolicitada < $fechaHoy) {
        return [
            'ok' => false,
            'mensaje' => 'No se pueden crear reservas para fechas anteriores a hoy.'
        ];
    }

    $diaSemana = (int) $fechaObjeto->format('N');

    $sqlDepartamento = "
        SELECT
            d.id_departamento,
            d.horarios_unificados
        FROM servicios s
        INNER JOIN departamentos d
            ON d.id_departamento = s.id_departamento
        WHERE s.id_servicio = :id_servicio
        LIMIT 1
    ";

    $stmtDepartamento = $conexion->prepare(
        $sqlDepartamento
    );

    $stmtDepartamento->execute(
        [
            ':id_servicio' => $idServicio
        ]
    );

    $departamento =
        $stmtDepartamento->fetch(PDO::FETCH_ASSOC);

    if (!$departamento) {
        return [
            'ok' => false,
            'mensaje' => 'No se pudo determinar el departamento del servicio.'
        ];
    }

    $idDepartamento =
        (int) $departamento['id_departamento'];

    $horariosUnificados =
        !empty($departamento['horarios_unificados']);


    /*
     * =====================================================
     * EXCEPCIONES
     * =====================================================
     */

    if ($horariosUnificados) {

        $sqlExcepcion = "
            SELECT
                e.id_excepcion,
                e.fecha,
                e.es_bloqueo_total,
                e.hora_inicio,
                e.hora_fin,
                e.motivo
            FROM excepciones_horarios e
            INNER JOIN servicios s
                ON s.id_servicio = e.id_servicio
            WHERE s.id_departamento = :id_departamento
              AND s.activo = TRUE
              AND e.fecha = :fecha
            ORDER BY e.id_excepcion ASC
            LIMIT 1
        ";

        $stmtExcepcion =
            $conexion->prepare($sqlExcepcion);

        $stmtExcepcion->execute(
            [
                ':id_departamento' => $idDepartamento,
                ':fecha' => $fechaSolicitada
            ]
        );

    } else {

        $sqlExcepcion = "
            SELECT
                id_excepcion,
                fecha,
                es_bloqueo_total,
                hora_inicio,
                hora_fin,
                motivo
            FROM excepciones_horarios
            WHERE id_servicio = :id_servicio
              AND fecha = :fecha
            LIMIT 1
        ";

        $stmtExcepcion =
            $conexion->prepare($sqlExcepcion);

        $stmtExcepcion->execute(
            [
                ':id_servicio' => $idServicio,
                ':fecha' => $fechaSolicitada
            ]
        );
    }

    $excepcion =
        $stmtExcepcion->fetch(PDO::FETCH_ASSOC);

    if (
        $excepcion &&
        !empty($excepcion['es_bloqueo_total'])
    ) {
        return [
            'ok' => false,
            'mensaje' => 'No hay horarios disponibles para la fecha seleccionada.'
        ];
    }


    /*
     * =====================================================
     * CONFIGURACIÓN DE HORARIOS
     * =====================================================
     */

    if ($horariosUnificados) {

        $sqlConfiguracion = "
            SELECT
                ch.id_configuracion,
                ch.id_servicio,
                ch.dia_semana,
                ch.hora_inicio,
                ch.hora_fin,
                ch.intervalo_minutos,
                ch.activo,
                ch.tiene_almuerzo,
                ch.almuerzo_inicio,
                ch.almuerzo_fin
            FROM configuracion_horarios ch
            INNER JOIN servicios s
                ON s.id_servicio = ch.id_servicio
            WHERE s.id_departamento = :id_departamento
              AND s.activo = TRUE
              AND ch.dia_semana = :dia_semana
            ORDER BY ch.id_configuracion ASC
            LIMIT 1
        ";

        $stmtConfiguracion =
            $conexion->prepare($sqlConfiguracion);

        $stmtConfiguracion->execute(
            [
                ':id_departamento' => $idDepartamento,
                ':dia_semana' => $diaSemana
            ]
        );

    } else {

        $sqlConfiguracion = "
            SELECT
                id_configuracion,
                id_servicio,
                dia_semana,
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
        ";

        $stmtConfiguracion =
            $conexion->prepare($sqlConfiguracion);

        $stmtConfiguracion->execute(
            [
                ':id_servicio' => $idServicio,
                ':dia_semana' => $diaSemana
            ]
        );
    }

    $configuracion =
        $stmtConfiguracion->fetch(PDO::FETCH_ASSOC);

    if (
        !$configuracion ||
        empty($configuracion['activo'])
    ) {
        return [
            'ok' => false,
            'mensaje' => 'No existen horarios disponibles para el día seleccionado.'
        ];
    }


    /*
     * =====================================================
     * HORARIOS
     * =====================================================
     */

    $horaInicio =
        trim($configuracion['hora_inicio'] ?? '');

    $horaFin =
        trim($configuracion['hora_fin'] ?? '');

    $intervalo =
        (int) ($configuracion['intervalo_minutos'] ?? 0);

    if ($intervalo <= 0) {
        $intervalo = 30;
    }


    if (
        $excepcion &&
        !empty($excepcion['hora_inicio']) &&
        !empty($excepcion['hora_fin'])
    ) {
        $horaInicio =
            trim($excepcion['hora_inicio']);

        $horaFin =
            trim($excepcion['hora_fin']);
    }


    $horaInicio =
        normalizarHoraConfiguracion($horaInicio);

    $horaFin =
        normalizarHoraConfiguracion($horaFin);

    $hora =
        normalizarHoraConfiguracion($hora);


    if (
        $horaInicio === null ||
        $horaFin === null ||
        $hora === null
    ) {
        return [
            'ok' => false,
            'mensaje' => 'La configuración del horario no es válida.'
        ];
    }


    $horaInicioObjeto =
        DateTime::createFromFormat(
            'H:i',
            $horaInicio,
            $zonaHoraria
        );

    $horaFinObjeto =
        DateTime::createFromFormat(
            'H:i',
            $horaFin,
            $zonaHoraria
        );

    $horaSolicitadaObjeto =
        DateTime::createFromFormat(
            'H:i',
            $hora,
            $zonaHoraria
        );


    if (
        !$horaInicioObjeto ||
        !$horaFinObjeto ||
        !$horaSolicitadaObjeto
    ) {
        return [
            'ok' => false,
            'mensaje' => 'La configuración del horario no es válida.'
        ];
    }


    if (
        $horaSolicitadaObjeto < $horaInicioObjeto ||
        $horaSolicitadaObjeto >= $horaFinObjeto
    ) {
        return [
            'ok' => false,
            'mensaje' => 'La hora seleccionada está fuera del horario permitido.'
        ];
    }


    $minutosDesdeInicio =
        (
            (
                (int) $horaSolicitadaObjeto->format('H') * 60
            )
            +
            (int) $horaSolicitadaObjeto->format('i')
        )
        -
        (
            (
                (int) $horaInicioObjeto->format('H') * 60
            )
            +
            (int) $horaInicioObjeto->format('i')
        );


    if (
        $minutosDesdeInicio < 0 ||
        $minutosDesdeInicio % $intervalo !== 0
    ) {
        return [
            'ok' => false,
            'mensaje' => 'La hora seleccionada no corresponde a un horario disponible.'
        ];
    }


    /*
     * =====================================================
     * HORARIO DE ALMUERZO
     * =====================================================
     */

    if (
        !empty($configuracion['tiene_almuerzo']) &&
        !empty($configuracion['almuerzo_inicio']) &&
        !empty($configuracion['almuerzo_fin'])
    ) {

        $almuerzoInicio =
            normalizarHoraConfiguracion(
                $configuracion['almuerzo_inicio']
            );

        $almuerzoFin =
            normalizarHoraConfiguracion(
                $configuracion['almuerzo_fin']
            );


        if (
            $almuerzoInicio !== null &&
            $almuerzoFin !== null
        ) {

            $almuerzoInicioObjeto =
                DateTime::createFromFormat(
                    'H:i',
                    $almuerzoInicio,
                    $zonaHoraria
                );

            $almuerzoFinObjeto =
                DateTime::createFromFormat(
                    'H:i',
                    $almuerzoFin,
                    $zonaHoraria
                );


            if (
                $almuerzoInicioObjeto &&
                $almuerzoFinObjeto
            ) {

                if (
                    $horaSolicitadaObjeto >= $almuerzoInicioObjeto &&
                    $horaSolicitadaObjeto < $almuerzoFinObjeto
                ) {
                    return [
                        'ok' => false,
                        'mensaje' => 'La hora seleccionada corresponde al horario de almuerzo.'
                    ];
                }
            }
        }
    }


    /*
     * =====================================================
     * FECHA ACTUAL
     * =====================================================
     *
     * Se mantiene la regla actual:
     *
     * Si son las 10:30:
     * - 11:00 no está disponible
     * - 11:30 es la siguiente hora posible
     *
     * =====================================================
     */

    if ($fechaSolicitada === $fechaHoy) {

        $horaMinima =
            clone $ahora;

        $horaMinima->modify('+1 hour');

        $minutosActuales =
            (int) $horaMinima->format('i');


        if (
            $minutosActuales > 0 &&
            $minutosActuales <= 30
        ) {

            $horaMinima->setTime(
                (int) $horaMinima->format('H'),
                30,
                0
            );

        } elseif (
            $minutosActuales > 30
        ) {

            $horaMinima->modify('+1 hour');

            $horaMinima->setTime(
                (int) $horaMinima->format('H'),
                0,
                0
            );
        }


        if (
            $horaSolicitadaObjeto < $horaMinima
        ) {
            return [
                'ok' => false,
                'mensaje' => 'La hora seleccionada ya no está disponible. Seleccione una hora posterior.'
            ];
        }
    }


    return [
        'ok' => true
    ];
}


/*
 * =========================================================
 * OBTENER FUNCIONARIO DE LA SESIÓN
 * =========================================================
 */

function obtenerFuncionarioSesion($conexion)
{
    if (
        session_status() === PHP_SESSION_NONE
    ) {
        session_start();
    }


    $usuarioFuncionario =
        trim(
            $_SESSION['usuario_funcionario'] ?? ''
        );

    $rolFuncionario =
        trim(
            $_SESSION['rol_funcionario'] ?? ''
        );


    if ($usuarioFuncionario === '') {
        return null;
    }


    $rolNormalizado =
        function_exists('mb_strtolower')
            ? mb_strtolower(
                $rolFuncionario,
                'UTF-8'
            )
            : strtolower($rolFuncionario);

    $rolNormalizado =
        trim($rolNormalizado);


    if (
        $rolNormalizado !== 'superadmin' &&
        $rolNormalizado !== 'admin' &&
        $rolNormalizado !== 'administrador' &&
        $rolNormalizado !== 'funcionario'
    ) {
        return null;
    }


    $sql = "
        SELECT
            id_funcionario,
            nombre,
            apellido,
            usuario,
            rol,
            activo
        FROM funcionarios
        WHERE usuario = :usuario
        LIMIT 1
    ";


    $stmt =
        $conexion->prepare($sql);

    $stmt->execute(
        [
            ':usuario' => $usuarioFuncionario
        ]
    );


    $funcionario =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (
        !$funcionario ||
        empty($funcionario['activo'])
    ) {
        return null;
    }


    $rolBD =
        function_exists('mb_strtolower')
            ? mb_strtolower(
                $funcionario['rol'] ?? '',
                'UTF-8'
            )
            : strtolower(
                $funcionario['rol'] ?? ''
            );

    $rolBD =
        trim($rolBD);


    if (
        $rolBD !== 'superadmin' &&
        $rolBD !== 'admin' &&
        $rolBD !== 'administrador' &&
        $rolBD !== 'funcionario'
    ) {
        return null;
    }


    return $funcionario;
}


/*
 * =========================================================
 * SOLO POST
 * =========================================================
 */

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    responderError(
        'Método no permitido.',
        405
    );
}


/*
 * =========================================================
 * RECIBIR DATOS
 * =========================================================
 */

$nombre =
    trim($_POST['nombre'] ?? '');

$rut =
    trim($_POST['rut'] ?? '');

$tipoIdentificacion =
    trim(
        $_POST['tipo_identificacion'] ?? 'rut'
    );

$telefono =
    trim($_POST['telefono'] ?? '');

$correo =
    trim($_POST['correo'] ?? '');

$servicio =
    trim($_POST['servicio'] ?? '');

$fecha =
    trim($_POST['fecha'] ?? '');

$hora =
    trim($_POST['hora'] ?? '');

$observacion =
    trim($_POST['observacion'] ?? '');

$modo =
    trim(
        $_POST['modo'] ?? 'publico'
    );


$modo =
    function_exists('mb_strtolower')
        ? mb_strtolower(
            $modo,
            'UTF-8'
        )
        : strtolower($modo);


if (
    $modo !== 'publico' &&
    $modo !== 'funcionario'
) {
    responderError(
        'Modo de creación no válido.',
        400
    );
}


/*
 * =========================================================
 * VALIDACIONES
 * =========================================================
 */

if (
    $nombre === '' ||
    longitudTexto($nombre) < 2 ||
    longitudTexto($nombre) > 150
) {
    responderError(
        'El nombre debe tener entre 2 y 150 caracteres.',
        400
    );
}


if ($rut === '') {
    responderError(
        'Debe ingresar la identificación.',
        400
    );
}


$rut =
    limpiarIdentificacion($rut);


if (
    $tipoIdentificacion === 'rut' &&
    !rutEsValido($rut)
) {
    responderError(
        'El RUT ingresado no es válido.',
        400
    );
}


if (
    (
        $tipoIdentificacion === 'rut_extranjero' ||
        $tipoIdentificacion === 'pasaporte'
    ) &&
    (
        longitudTexto($rut) < 3 ||
        longitudTexto($rut) > 50
    )
) {
    responderError(
        'La identificación ingresada no es válida.',
        400
    );
}


$tiposIdentificacionPermitidos = [
    'rut',
    'rut_extranjero',
    'pasaporte'
];


if (
    !in_array(
        $tipoIdentificacion,
        $tiposIdentificacionPermitidos,
        true
    )
) {
    responderError(
        'Tipo de identificación no válido.',
        400
    );
}


if (
    $telefono === '' ||
    longitudTexto($telefono) < 7 ||
    longitudTexto($telefono) > 30
) {
    responderError(
        'El teléfono ingresado no es válido.',
        400
    );
}


if ($correo !== '') {

    if (
        !filter_var(
            $correo,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        responderError(
            'El correo electrónico ingresado no es válido.',
            400
        );
    }

} else {

    $correo = null;
}


if (
    $servicio === '' ||
    !ctype_digit($servicio)
) {
    responderError(
        'Debe seleccionar un servicio válido.',
        400
    );
}


$idServicio =
    (int) $servicio;


if ($idServicio <= 0) {
    responderError(
        'El servicio seleccionado no es válido.',
        400
    );
}


if ($fecha === '') {
    responderError(
        'Debe seleccionar una fecha.',
        400
    );
}


$fechaObjeto =
    DateTime::createFromFormat(
        'Y-m-d',
        $fecha
    );

$erroresFecha =
    DateTime::getLastErrors();


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
    responderError(
        'La fecha seleccionada no es válida.',
        400
    );
}


if ($hora === '') {
    responderError(
        'Debe seleccionar una hora.',
        400
    );
}


$horaObjeto =
    DateTime::createFromFormat(
        'H:i',
        $hora
    );

$erroresHora =
    DateTime::getLastErrors();


if (
    $horaObjeto === false ||
    (
        is_array($erroresHora) &&
        (
            $erroresHora['warning_count'] > 0 ||
            $erroresHora['error_count'] > 0
        )
    ) ||
    $horaObjeto->format('H:i') !== $hora
) {
    responderError(
        'La hora seleccionada no es válida.',
        400
    );
}


if (
    longitudTexto($observacion) > 1000
) {
    responderError(
        'La observación no puede superar los 1000 caracteres.',
        400
    );
}


/*
 * =========================================================
 * FUNCIONARIO
 * =========================================================
 */

$funcionario = null;


if (
    $modo === 'funcionario'
) {

    $funcionario =
        obtenerFuncionarioSesion(
            $conexion
        );


    if (!$funcionario) {
        responderError(
            'No autorizado. La sesión del funcionario no es válida o el usuario no tiene permisos para crear reservas.',
            401
        );
    }
}


/*
 * =========================================================
 * BUSCAR SERVICIO
 * =========================================================
 */

$sqlServicio = "
    SELECT
        s.id_servicio,
        s.nombre AS nombre_servicio,
        s.descripcion AS descripcion_servicio,
        s.activo AS servicio_activo,
        d.id_departamento,
        d.nombre AS nombre_departamento,
        d.activo AS departamento_activo,
        d.horarios_unificados
    FROM servicios s
    INNER JOIN departamentos d
        ON d.id_departamento = s.id_departamento
    WHERE s.id_servicio = :id_servicio
    LIMIT 1
";


$stmtServicio =
    $conexion->prepare(
        $sqlServicio
    );


$stmtServicio->execute(
    [
        ':id_servicio' => $idServicio
    ]
);


$servicioBD =
    $stmtServicio->fetch(
        PDO::FETCH_ASSOC
    );


if (!$servicioBD) {
    responderError(
        'El servicio seleccionado no existe.',
        404
    );
}


if (
    !$servicioBD['servicio_activo']
) {
    responderError(
        'El servicio seleccionado no está disponible.',
        400
    );
}


if (
    !$servicioBD['departamento_activo']
) {
    responderError(
        'El departamento asociado al servicio no está disponible.',
        400
    );
}


$horariosUnificados =
    !empty(
        $servicioBD['horarios_unificados']
    );


/*
 * =========================================================
 * VALIDAR HORARIO CONFIGURADO
 * =========================================================
 */

$validacionHorario =
    validarHorarioConfigurado(
        $conexion,
        $idServicio,
        $fechaObjeto,
        $hora
    );


if (!$validacionHorario['ok']) {
    responderError(
        $validacionHorario['mensaje'],
        400
    );
}


/*
 * =========================================================
 * OBTENER SERVICIOS COMPARTIDOS
 * =========================================================
 */

$idsServiciosCompartidos = [
    $idServicio
];

$idGrupoUnificado = null;


if ($horariosUnificados) {

    $sqlServiciosDepartamento = "
        SELECT
            id_servicio
        FROM servicios
        WHERE id_departamento = :id_departamento
          AND activo = TRUE
        ORDER BY id_servicio ASC
    ";


    $stmtServiciosDepartamento =
        $conexion->prepare(
            $sqlServiciosDepartamento
        );


    $stmtServiciosDepartamento->execute(
        [
            ':id_departamento' =>
                $servicioBD['id_departamento']
        ]
    );


    while (
        $servicioDepartamento =
            $stmtServiciosDepartamento->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $idServicioDepartamento =
            (int) $servicioDepartamento['id_servicio'];


        if (
            !in_array(
                $idServicioDepartamento,
                $idsServiciosCompartidos,
                true
            )
        ) {
            $idsServiciosCompartidos[] =
                $idServicioDepartamento;
        }
    }

} else {

    $sqlGrupo = "
        SELECT
            id_grupo
        FROM unificacion_horarios_servicios
        WHERE id_servicio = :id_servicio
        LIMIT 1
    ";


    $stmtGrupo =
        $conexion->prepare(
            $sqlGrupo
        );


    $stmtGrupo->execute(
        [
            ':id_servicio' => $idServicio
        ]
    );


    $grupoRow =
        $stmtGrupo->fetch(
            PDO::FETCH_ASSOC
        );


    if ($grupoRow) {

        $idGrupoUnificado =
            (int) $grupoRow['id_grupo'];


        $sqlServiciosGrupo = "
            SELECT
                u.id_servicio
            FROM unificacion_horarios_servicios u
            INNER JOIN servicios s
                ON s.id_servicio = u.id_servicio
            WHERE u.id_grupo = :id_grupo
              AND s.activo = TRUE
            ORDER BY u.id_servicio ASC
        ";


        $stmtServiciosGrupo =
            $conexion->prepare(
                $sqlServiciosGrupo
            );


        $stmtServiciosGrupo->execute(
            [
                ':id_grupo' =>
                    $idGrupoUnificado
            ]
        );


        while (
            $servicioGrupo =
                $stmtServiciosGrupo->fetch(
                    PDO::FETCH_ASSOC
                )
        ) {

            $idServicioGrupo =
                (int) $servicioGrupo['id_servicio'];


            if (
                !in_array(
                    $idServicioGrupo,
                    $idsServiciosCompartidos,
                    true
                )
            ) {
                $idsServiciosCompartidos[] =
                    $idServicioGrupo;
            }
        }
    }
}


/*
 * =========================================================
 * TRANSACCIÓN
 * =========================================================
 */

try {

    $conexion->beginTransaction();


    $esCompartido =
        (
            $horariosUnificados ||
            count($idsServiciosCompartidos) > 1
        );


    /*
     * =====================================================
     * BLOQUEO TRANSACCIONAL
     * =====================================================
     */

    if ($esCompartido) {

        if ($horariosUnificados) {

            $claveBloqueo =
                'departamento-unificado|' .
                $servicioBD['id_departamento'] .
                '|' .
                $fecha .
                '|' .
                $hora;

        } else {

            $claveBloqueo =
                'grupo-unificado|' .
                $idGrupoUnificado .
                '|' .
                $fecha .
                '|' .
                $hora;
        }


        $sqlBloqueo = "
            SELECT pg_advisory_xact_lock(
                hashtext(:clave)
            )
        ";


        $stmtBloqueo =
            $conexion->prepare(
                $sqlBloqueo
            );


        $stmtBloqueo->execute(
            [
                ':clave' => $claveBloqueo
            ]
        );


        /*
         * =================================================
         * VALIDAR DISPONIBILIDAD COMPARTIDA
         * =================================================
         */

        $placeholdersServicios = [];
        $parametrosServicios = [];


        foreach (
            $idsServiciosCompartidos
            as $indice => $idServicioCompartido
        ) {

            $parametro =
                ':servicio_compartido_' .
                $indice;


            $placeholdersServicios[] =
                $parametro;


            $parametrosServicios[$parametro] =
                $idServicioCompartido;
        }


        $sqlConflictoCompartido = "
            SELECT
                h.id_horario,
                h.id_servicio,
                h.estado,
                r.id_reserva,
                r.estado AS estado_reserva
            FROM horarios h
            LEFT JOIN reservas r
                ON r.id_horario = h.id_horario
               AND LOWER(r.estado)
                   IN ('confirmada', 'reagendada')
            WHERE h.id_servicio IN (
                " .
                implode(
                    ', ',
                    $placeholdersServicios
                ) .
                "
            )
              AND h.fecha = :fecha_compartida
              AND h.hora = :hora_compartida
              AND (
                    LOWER(h.estado) = 'bloqueado'
                    OR r.id_reserva IS NOT NULL
              )
            FOR UPDATE OF h
        ";


        $stmtConflictoCompartido =
            $conexion->prepare(
                $sqlConflictoCompartido
            );


        foreach (
            $parametrosServicios
            as $parametro => $valor
        ) {

            $stmtConflictoCompartido->bindValue(
                $parametro,
                $valor,
                PDO::PARAM_INT
            );
        }


        $stmtConflictoCompartido->bindValue(
            ':fecha_compartida',
            $fecha,
            PDO::PARAM_STR
        );


        $stmtConflictoCompartido->bindValue(
            ':hora_compartida',
            $hora,
            PDO::PARAM_STR
        );


        $stmtConflictoCompartido->execute();


        $conflictoCompartido =
            $stmtConflictoCompartido->fetch(
                PDO::FETCH_ASSOC
            );


        if ($conflictoCompartido) {

            $conexion->rollBack();


            if ($horariosUnificados) {

                responderError(
                    'El horario seleccionado ya no está disponible para el departamento. Seleccione otro horario.',
                    409
                );

            } else {

                responderError(
                    'El horario seleccionado ya no está disponible para este servicio. Seleccione otro horario.',
                    409
                );
            }
        }
    }


    /*
     * =====================================================
     * BUSCAR HORARIO
     * =====================================================
     */

    $sqlHorario = "
        SELECT
            id_horario,
            id_servicio,
            fecha,
            hora,
            estado,
            observacion
        FROM horarios
        WHERE id_servicio = :id_servicio
          AND fecha = :fecha
          AND hora = :hora
        FOR UPDATE
    ";


    $stmtHorario =
        $conexion->prepare(
            $sqlHorario
        );


    $stmtHorario->execute(
        [
            ':id_servicio' => $idServicio,
            ':fecha' => $fecha,
            ':hora' => $hora
        ]
    );


    $horario =
        $stmtHorario->fetch(
            PDO::FETCH_ASSOC
        );


    /*
     * =====================================================
     * CREAR HORARIO SI NO EXISTE
     * =====================================================
     */

    $horarioCreadoAhora = false;


    if (!$horario) {

        $sqlCrearHorario = "
            INSERT INTO horarios (
                id_servicio,
                fecha,
                hora,
                estado,
                observacion,
                fecha_creacion,
                id_funcionario
            )
            VALUES (
                :id_servicio,
                :fecha,
                :hora,
                'bloqueado',
                :observacion,
                CURRENT_TIMESTAMP,
                :id_funcionario
            )
            RETURNING
                id_horario,
                id_servicio,
                fecha,
                hora,
                estado,
                observacion
        ";


        $stmtCrearHorario =
            $conexion->prepare(
                $sqlCrearHorario
            );


        $stmtCrearHorario->execute(
            [
                ':id_servicio' =>
                    $idServicio,

                ':fecha' =>
                    $fecha,

                ':hora' =>
                    $hora,

                ':observacion' =>
                    $observacion !== ''
                        ? $observacion
                        : null,

                ':id_funcionario' =>
                    $funcionario
                        ? $funcionario['id_funcionario']
                        : null
            ]
        );


        $horario =
            $stmtCrearHorario->fetch(
                PDO::FETCH_ASSOC
            );


        $horarioCreadoAhora = true;
    }


    /*
     * =====================================================
     * VALIDAR ESTADO DEL HORARIO
     * =====================================================
     */

    if (
        !$horarioCreadoAhora &&
        isset($horario['estado']) &&
        strtolower(
            trim($horario['estado'])
        ) === 'bloqueado'
    ) {

        $conexion->rollBack();

        responderError(
            'El horario seleccionado está bloqueado.',
            409
        );
    }


    /*
     * =====================================================
     * BUSCAR RESERVA ACTIVA
     * =====================================================
     */

    $sqlReservaExistente = "
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


    $stmtReservaExistente =
        $conexion->prepare(
            $sqlReservaExistente
        );


    $stmtReservaExistente->execute(
        [
            ':id_horario' =>
                $horario['id_horario']
        ]
    );


    $reservaExistente =
        $stmtReservaExistente->fetch(
            PDO::FETCH_ASSOC
        );


    if ($reservaExistente) {

        $conexion->rollBack();


        responderError(
            'El horario seleccionado ya no está disponible.',
            409,
            [
                'id_reserva' =>
                    $reservaExistente['id_reserva']
            ]
        );
    }


    /*
     * =====================================================
     * CREAR RESERVA
     * =====================================================
     */

    $estadoReserva =
        'confirmada';


    $sqlCrearReserva = "
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
            :estado,
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
    ";


    $stmtCrearReserva =
        $conexion->prepare(
            $sqlCrearReserva
        );


    $stmtCrearReserva->execute(
        [
            ':id_horario' =>
                $horario['id_horario'],

            ':id_servicio' =>
                $idServicio,

            ':nombre' =>
                $nombre,

            ':rut' =>
                $rut,

            ':correo' =>
                $correo,

            ':telefono' =>
                $telefono,

            ':estado' =>
                $estadoReserva,

            ':tipo_identificacion' =>
                $tipoIdentificacion
        ]
    );


    $reserva =
        $stmtCrearReserva->fetch(
            PDO::FETCH_ASSOC
        );


    /*
     * =====================================================
     * ACTUALIZAR ESTADO DEL HORARIO
     * =====================================================
     */

    $sqlActualizarHorario = "
        UPDATE horarios
        SET estado = 'bloqueado'
        WHERE id_horario = :id_horario
    ";


    $stmtActualizarHorario =
        $conexion->prepare(
            $sqlActualizarHorario
        );


    $stmtActualizarHorario->execute(
        [
            ':id_horario' =>
                $horario['id_horario']
        ]
    );


    /*
     * =========================================================
     * CREAR NOTIFICACIONES INTERNAS
     * =========================================================
     *
     * La reserva ya fue creada correctamente.
     *
     * Se busca a todos los funcionarios activos
     * pertenecientes al departamento del servicio.
     *
     * Cada funcionario recibe su propia notificación.
     *
     * Esto ocurre antes del COMMIT para que la reserva
     * y sus notificaciones formen parte de la misma
     * transacción.
     *
     * =========================================================
     */

    $sqlFuncionariosNotificacion = "
        SELECT
            id_funcionario
        FROM funcionarios
        WHERE id_departamento = :id_departamento
          AND activo = TRUE
        ORDER BY id_funcionario ASC
    ";


    $stmtFuncionariosNotificacion =
        $conexion->prepare(
            $sqlFuncionariosNotificacion
        );


    $stmtFuncionariosNotificacion->execute(
        [
            ':id_departamento' =>
                $servicioBD['id_departamento']
        ]
    );


    $tituloNotificacion =
        'Nueva reserva';


    $fechaNotificacion =
        $fechaObjeto->format('d/m/Y');


    $horaNotificacion =
        $horaObjeto->format('H:i');


    $mensajeNotificacion =
        'Se registró una nueva reserva para "' .
        $servicioBD['nombre_servicio'] .
        '" el ' .
        $fechaNotificacion .
        ' a las ' .
        $horaNotificacion .
        '.';


    $sqlCrearNotificacion = "
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
    ";


    $stmtCrearNotificacion =
        $conexion->prepare(
            $sqlCrearNotificacion
        );


    while (
        $funcionarioNotificacion =
            $stmtFuncionariosNotificacion->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $stmtCrearNotificacion->execute(
            [
                ':id_funcionario' =>
                    $funcionarioNotificacion['id_funcionario'],

                ':id_reserva' =>
                    $reserva['id_reserva'],

                ':titulo' =>
                    $tituloNotificacion,

                ':mensaje' =>
                    $mensajeNotificacion
            ]
        );
    }


    /*
     * =====================================================
     * CONFIRMAR TRANSACCIÓN
     * =====================================================
     */

    $conexion->commit();


    /*
     * =====================================================
     * RESPUESTA
     * =====================================================
     */

    responder(
        [
            'ok' => true,

            'mensaje' =>
                'Reserva creada correctamente.',

            'reserva' =>
                $reserva,

            'horario' => [
                'id_horario' =>
                    $horario['id_horario'],

                'fecha' =>
                    $horario['fecha'],

                'hora' =>
                    $horario['hora'],

                'estado' =>
                    'bloqueado'
            ],

            'departamento' => [
                'id_departamento' =>
                    $servicioBD['id_departamento'],

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

            'funcionario' =>
                $funcionario
                    ? [
                        'id_funcionario' =>
                            $funcionario['id_funcionario'],

                        'nombre' =>
                            $funcionario['nombre'],

                        'apellido' =>
                            $funcionario['apellido'],

                        'usuario' =>
                            $funcionario['usuario'],

                        'rol' =>
                            $funcionario['rol']
                    ]
                    : null
        ]
    );


} catch (PDOException $e) {

    if (
        $conexion->inTransaction()
    ) {
        $conexion->rollBack();
    }


    if (
        $e->getCode() === '23505'
    ) {
        responderError(
            'El horario seleccionado ya no está disponible.',
            409
        );
    }


    error_log(
        'Error en crear_reserva.php: ' .
        $e->getMessage()
    );


    responderError(
        'Ocurrió un error al crear la reserva.',
        500
    );


} catch (Throwable $e) {

    if (
        $conexion->inTransaction()
    ) {
        $conexion->rollBack();
    }


    error_log(
        'Error inesperado en crear_reserva.php: ' .
        $e->getMessage()
    );


    responderError(
        'Ocurrió un error inesperado al crear la reserva.',
        500
    );
}