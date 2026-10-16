<?php
require_once '../componentes/auth_funcionario.php';


/* =========================================================
   DATOS DEL FUNCIONARIO AUTENTICADO
   ========================================================= */

$nombreFuncionario =
    $_SESSION['nombre_funcionario'] ?? 'Funcionario';

$apellidoFuncionario =
    $_SESSION['apellido_funcionario'] ?? '';

$usuarioFuncionario =
    $_SESSION['usuario_funcionario'] ?? '';

$rolFuncionario =
    $_SESSION['rol_funcionario'] ?? 'funcionario';


$nombreCompleto = trim(
    $nombreFuncionario . ' ' . $apellidoFuncionario
);

if ($nombreCompleto === '') {
    $nombreCompleto = 'Funcionario';
}


/* =========================================================
   TEXTO DEL ROL
   ========================================================= */

if ($rolFuncionario === 'superadmin') {
    $rolMostrar = 'Superadministrador';
} elseif ($rolFuncionario === 'administrador') {
    $rolMostrar = 'Administrador';
} else {
    $rolMostrar = 'Funcionario';
}


/* =========================================================
   VARIABLES PARA COMPONENTES
   ========================================================= */

$base_url = '/municipalidad';
$nombre_funcionario = $nombreCompleto;
$rol_funcionario = $rolFuncionario;
$pagina_actual = 'agenda';


/* =========================================================
   VARIABLES GENERALES
   ========================================================= */

$serviciosAgenda = [];
$departamentosAgenda = [];
$errorServicios = '';
$servicioSeleccionado = '';
$departamentoSeleccionado = '';
$nombreDepartamentoSeleccionado = '';
$errorAgenda = '';
$horariosAgenda = [];
$servicioActualNombre = '';
$departamentoActualNombre = '';
$idDepartamentoActual = '';

$totalDisponibles = 0;
$totalReservadas = 0;
$totalBloqueadas = 0;
$totalRecordatorios = 0;

$reservasCalendario = [];

/*
 * Lista de servicios permitidos para la vista actual.
 */
$serviciosPermitidosAgenda = [];
$idsServiciosPermitidos = [];


/* =========================================================
   FECHA ACTUAL
   ========================================================= */

$hoy = new DateTime();
$fechaHoy = $hoy->format('Y-m-d');


/* =========================================================
   FECHA SELECCIONADA
   ========================================================= */

$fechaAgenda = $fechaHoy;

if (
    isset($_GET['fecha']) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha'])
) {
    $fechaTemporal = DateTime::createFromFormat('Y-m-d', $_GET['fecha']);

    if ($fechaTemporal && $fechaTemporal->format('Y-m-d') === $_GET['fecha']) {
        $fechaAgenda = $_GET['fecha'];
    }
}


/* =========================================================
   MES DEL CALENDARIO
   ========================================================= */

$mesCalendario = substr($fechaAgenda, 0, 7);

$fechaMes = DateTime::createFromFormat('Y-m-d', $mesCalendario . '-01');

if (!$fechaMes) {
    $fechaMes = new DateTime(date('Y-m-01'));
    $mesCalendario = $fechaMes->format('Y-m');
}


/* =========================================================
   DATOS DEL MES
   ========================================================= */

$anioCalendario = (int) $fechaMes->format('Y');
$numeroMesCalendario = (int) $fechaMes->format('m');

$nombreMeses = [
    1  => 'enero',
    2  => 'febrero',
    3  => 'marzo',
    4  => 'abril',
    5  => 'mayo',
    6  => 'junio',
    7  => 'julio',
    8  => 'agosto',
    9  => 'septiembre',
    10 => 'octubre',
    11 => 'noviembre',
    12 => 'diciembre'
];

$nombreMesCalendario = $nombreMeses[$numeroMesCalendario];


/* =========================================================
   MES ANTERIOR Y SIGUIENTE
   ========================================================= */

$mesAnterior = clone $fechaMes;
$mesAnterior->modify('-1 month');
$mesAnteriorParametro = $mesAnterior->format('Y-m');

$mesSiguiente = clone $fechaMes;
$mesSiguiente->modify('+1 month');
$mesSiguienteParametro = $mesSiguiente->format('Y-m');


/* =========================================================
   DEPARTAMENTO SELECCIONADO
   ========================================================= */

if (
    $rolFuncionario === 'superadmin' &&
    isset($_GET['departamento']) &&
    ctype_digit((string) $_GET['departamento'])
) {
    $departamentoSeleccionado = (int) $_GET['departamento'];
}


/* =========================================================
   SERVICIO SELECCIONADO
   ========================================================= */

if (
    isset($_GET['servicio']) &&
    ctype_digit((string) $_GET['servicio'])
) {
    $servicioSeleccionado = (int) $_GET['servicio'];
}


/* =========================================================
   CONEXIÓN Y CONSULTAS
   ========================================================= */

try {
    require_once '../config/conexion.php';

    /* =====================================================
       OBTENER DEPARTAMENTO DEL FUNCIONARIO
       ===================================================== */

    if (
        $rolFuncionario === 'administrador' ||
        $rolFuncionario === 'funcionario'
    ) {
        $sqlDepartamento = "
            SELECT
                f.id_departamento,
                d.nombre AS nombre_departamento
            FROM funcionarios f
            LEFT JOIN departamentos d
                ON d.id_departamento = f.id_departamento
            WHERE f.usuario = :usuario
            LIMIT 1
        ";

        $stmtDepartamento = $conexion->prepare($sqlDepartamento);
        $stmtDepartamento->execute([':usuario' => $usuarioFuncionario]);
        $filaDepartamento = $stmtDepartamento->fetch(PDO::FETCH_ASSOC);

        if ($filaDepartamento) {
            $idDepartamentoActual = $filaDepartamento['id_departamento'];
            $departamentoActualNombre = $filaDepartamento['nombre_departamento'] ?? '';
        }
    }


    /* =====================================================
       DEPARTAMENTOS PARA SUPERADMIN
       ===================================================== */

    if ($rolFuncionario === 'superadmin') {
        $sqlDepartamentos = "
            SELECT id_departamento, nombre
            FROM departamentos
            WHERE activo = TRUE
            ORDER BY nombre ASC
        ";

        $stmtDepartamentos = $conexion->prepare($sqlDepartamentos);
        $stmtDepartamentos->execute();
        $departamentosAgenda = $stmtDepartamentos->fetchAll(PDO::FETCH_ASSOC);

        if ($departamentoSeleccionado !== '') {
            $departamentoEncontrado = false;

            foreach ($departamentosAgenda as $departamento) {
                if ((int) $departamento['id_departamento'] === $departamentoSeleccionado) {
                    $departamentoEncontrado = true;
                    $nombreDepartamentoSeleccionado = $departamento['nombre'];
                    break;
                }
            }

            if (!$departamentoEncontrado) {
                $departamentoSeleccionado = '';
                $nombreDepartamentoSeleccionado = '';
            }
        }
    }


    /* =====================================================
       SERVICIOS MUNICIPALES
       ===================================================== */

    if ($rolFuncionario === 'superadmin') {
        if ($departamentoSeleccionado !== '') {
            $sqlServicios = "
                SELECT
                    s.id_servicio,
                    s.nombre,
                    s.descripcion,
                    s.id_departamento,
                    d.nombre AS nombre_departamento
                FROM servicios s
                INNER JOIN departamentos d
                    ON d.id_departamento = s.id_departamento
                WHERE s.activo = TRUE
                  AND d.activo = TRUE
                  AND s.id_departamento = :id_departamento
                ORDER BY s.nombre ASC
            ";

            $stmtServicios = $conexion->prepare($sqlServicios);
            $stmtServicios->execute([':id_departamento' => $departamentoSeleccionado]);
        } else {
            $sqlServicios = "
                SELECT
                    s.id_servicio,
                    s.nombre,
                    s.descripcion,
                    s.id_departamento,
                    d.nombre AS nombre_departamento
                FROM servicios s
                INNER JOIN departamentos d
                    ON d.id_departamento = s.id_departamento
                WHERE s.activo = TRUE
                  AND d.activo = TRUE
                ORDER BY d.nombre ASC, s.nombre ASC
            ";

            $stmtServicios = $conexion->prepare($sqlServicios);
            $stmtServicios->execute();
        }

        $serviciosAgenda = $stmtServicios->fetchAll(PDO::FETCH_ASSOC);

    } elseif (
        ($rolFuncionario === 'administrador' || $rolFuncionario === 'funcionario') &&
        $idDepartamentoActual !== ''
    ) {
        $sqlServicios = "
            SELECT
                s.id_servicio,
                s.nombre,
                s.descripcion,
                s.id_departamento,
                d.nombre AS nombre_departamento
            FROM servicios s
            INNER JOIN departamentos d
                ON d.id_departamento = s.id_departamento
            WHERE s.activo = TRUE
              AND d.activo = TRUE
              AND s.id_departamento = :id_departamento
            ORDER BY s.nombre ASC
        ";

        $stmtServicios = $conexion->prepare($sqlServicios);
        $stmtServicios->execute([':id_departamento' => $idDepartamentoActual]);
        $serviciosAgenda = $stmtServicios->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $errorServicios = 'No fue posible determinar el departamento del funcionario.';
    }


    /* =====================================================
       INDEXAR SERVICIOS PERMITIDOS
       ===================================================== */

    foreach ($serviciosAgenda as $servicio) {
        $idServicioLista = (int) $servicio['id_servicio'];
        $serviciosPermitidosAgenda[$idServicioLista] = $servicio;
        $idsServiciosPermitidos[] = $idServicioLista;
    }


    /* =====================================================
       VALIDAR SERVICIO
       ===================================================== */

    if ($servicioSeleccionado !== '') {
        if (!isset($serviciosPermitidosAgenda[$servicioSeleccionado])) {
            $servicioSeleccionado = '';
            $errorAgenda = 'El servicio seleccionado no existe o no está disponible para tu departamento.';
        } else {
            $servicioActual = $serviciosPermitidosAgenda[$servicioSeleccionado];
            $servicioActualNombre = $servicioActual['nombre'];
            $idDepartamentoServicio = $servicioActual['id_departamento'];
            $departamentoActualNombre = $servicioActual['nombre_departamento'];
        }
    }


    /* =====================================================
       SI NO HAY SERVICIO, PREPARAR VISTA GENERAL
       ===================================================== */

    $serviciosParaAgenda = [];

    if ($errorAgenda === '') {
        if ($servicioSeleccionado !== '') {
            $serviciosParaAgenda[] = $serviciosPermitidosAgenda[$servicioSeleccionado];
        } else {
            $serviciosParaAgenda = $serviciosAgenda;
        }
    }


    /* =====================================================
       RESERVAS DEL MES PARA EL CALENDARIO
       ===================================================== */

    if (!empty($idsServiciosPermitidos) && $errorAgenda === '') {
        $idsServiciosCalendario = $idsServiciosPermitidos;

        if ($servicioSeleccionado !== '') {
            $idsServiciosCalendario = [$servicioSeleccionado];
        }

        $placeholdersServicios = [];
        $parametrosServicios = [];

        foreach ($idsServiciosCalendario as $indiceServicio => $idServicioPermitido) {
            $placeholder = ':servicio_cal_' . $indiceServicio;
            $placeholdersServicios[] = $placeholder;
            $parametrosServicios[$placeholder] = $idServicioPermitido;
        }

        $sqlReservasCalendario = "
            SELECT
                h.fecha,
                h.hora,
                h.id_servicio,
                s.nombre AS nombre_servicio
            FROM horarios h
            INNER JOIN servicios s
                ON s.id_servicio = h.id_servicio
            INNER JOIN reservas r
                ON r.id_horario = h.id_horario
            WHERE h.id_servicio IN (" . implode(',', $placeholdersServicios) . ")
              AND h.fecha >= :fecha_inicio_calendario
              AND h.fecha <= :fecha_fin_calendario
              AND r.estado = 'confirmada'
            ORDER BY h.fecha ASC, h.hora ASC
        ";

        $fechaInicioMes = $fechaMes->format('Y-m-d');
        $fechaFinMesObjeto = clone $fechaMes;
        $fechaFinMesObjeto->modify('last day of this month');
        $fechaFinMes = $fechaFinMesObjeto->format('Y-m-d');

        $parametrosServicios[':fecha_inicio_calendario'] = $fechaInicioMes;
        $parametrosServicios[':fecha_fin_calendario'] = $fechaFinMes;

        $stmtReservasCalendario = $conexion->prepare($sqlReservasCalendario);
        $stmtReservasCalendario->execute($parametrosServicios);
        $reservasCalendarioDB = $stmtReservasCalendario->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservasCalendarioDB as $reservaCalendario) {
            $fechaReserva = $reservaCalendario['fecha'];
            $horaReserva = substr($reservaCalendario['hora'], 0, 5);
            $nombreServicioReserva = $reservaCalendario['nombre_servicio'] ?? 'Servicio';

            if (!isset($reservasCalendario[$fechaReserva])) {
                $reservasCalendario[$fechaReserva] = [];
            }

            $reservasCalendario[$fechaReserva][] = [
                'hora' => $horaReserva,
                'servicio' => $nombreServicioReserva
            ];
        }
    }


    /* =====================================================
       CARGAR AGENDA
       ===================================================== */

    if (!empty($serviciosParaAgenda) && $errorAgenda === '') {
        $fechaObjeto = DateTime::createFromFormat('Y-m-d', $fechaAgenda);
        $diaSemana = (int) $fechaObjeto->format('N');

        foreach ($serviciosParaAgenda as $servicioAgendaActual) {
            $idServicioAgendaActual = (int) $servicioAgendaActual['id_servicio'];
            $nombreServicioAgendaActual = $servicioAgendaActual['nombre'];
            $nombreDepartamentoAgendaActual = $servicioAgendaActual['nombre_departamento'];

            /* CONFIGURACIÓN DEL DÍA */
            $sqlConfiguracionDia = "
                SELECT
                    hora_inicio,
                    hora_fin,
                    intervalo_minutos,
                    activo,
                    tiene_almuerzo,
                    almuerzo_inicio,
                    almuerzo_fin
                FROM configuracion_horarios
                WHERE id_servicio = :id_servicio_config
                  AND dia_semana = :dia_semana_config
                LIMIT 1
            ";

            $stmtConfiguracionDia = $conexion->prepare($sqlConfiguracionDia);
            $stmtConfiguracionDia->execute([
                ':id_servicio_config' => $idServicioAgendaActual,
                ':dia_semana_config'  => $diaSemana
            ]);
            $configuracionDia = $stmtConfiguracionDia->fetch(PDO::FETCH_ASSOC);

            /* EXCEPCIÓN DE LA FECHA */
            $sqlExcepcionDia = "
                SELECT
                    es_bloqueo_total,
                    hora_inicio,
                    hora_fin,
                    motivo
                FROM excepciones_horarios
                WHERE id_servicio = :id_servicio_excepcion
                  AND fecha = :fecha_excepcion
                LIMIT 1
            ";

            $stmtExcepcionDia = $conexion->prepare($sqlExcepcionDia);
            $stmtExcepcionDia->execute([
                ':id_servicio_excepcion' => $idServicioAgendaActual,
                ':fecha_excepcion'       => $fechaAgenda
            ]);
            $excepcionDia = $stmtExcepcionDia->fetch(PDO::FETCH_ASSOC);

            /* VALIDAR BLOQUEO TOTAL */
            $diaBloqueadoTotal = false;

            if ($excepcionDia) {
                $diaBloqueadoTotal = (
                    $excepcionDia['es_bloqueo_total'] === true ||
                    $excepcionDia['es_bloqueo_total'] === 't' ||
                    $excepcionDia['es_bloqueo_total'] === '1' ||
                    $excepcionDia['es_bloqueo_total'] === 1
                );
            }

            /* VALIDAR CONFIGURACIÓN */
            $configuracionActiva = false;

            if ($configuracionDia) {
                $configuracionActiva = (
                    $configuracionDia['activo'] === true ||
                    $configuracionDia['activo'] === 't' ||
                    $configuracionDia['activo'] === '1' ||
                    $configuracionDia['activo'] === 1
                );
            }

            if ($diaBloqueadoTotal || !$configuracionDia || !$configuracionActiva) {
                continue;
            }

            /* DEFINIR HORARIO EFECTIVO */
            $horaInicio = $configuracionDia['hora_inicio'];
            $horaFin = $configuracionDia['hora_fin'];

            if ($excepcionDia && !empty($excepcionDia['hora_inicio']) && !empty($excepcionDia['hora_fin'])) {
                $horaInicio = $excepcionDia['hora_inicio'];
                $horaFin = $excepcionDia['hora_fin'];
            }

            /* INTERVALO */
            $intervaloMinutos = (int) ($configuracionDia['intervalo_minutos'] ?? 30);
            if ($intervaloMinutos <= 0) {
                $intervaloMinutos = 30;
            }

            /* ALMUERZO */
            $tieneAlmuerzo = (
                $configuracionDia['tiene_almuerzo'] === true ||
                $configuracionDia['tiene_almuerzo'] === 't' ||
                $configuracionDia['tiene_almuerzo'] === '1' ||
                $configuracionDia['tiene_almuerzo'] === 1
            );

            $almuerzoInicio = $configuracionDia['almuerzo_inicio'] ?? null;
            $almuerzoFin = $configuracionDia['almuerzo_fin'] ?? null;

            /* OBTENER HORARIOS REALES */
            $sqlHorariosDia = "
                SELECT
                    h.id_horario,
                    h.hora,
                    h.estado AS estado_horario,
                    h.observacion AS observacion_horario,
                    r.id_reserva,
                    r.nombre AS nombre_reserva,
                    r.rut AS rut_reserva,
                    r.telefono AS telefono_reserva,
                    r.correo AS correo_reserva,
                    r.tipo_identificacion,
                    r.estado AS estado_reserva
                FROM horarios h
                LEFT JOIN reservas r
                    ON r.id_horario = h.id_horario
                    AND r.estado = 'confirmada'
                WHERE h.id_servicio = :id_servicio_horarios_dia
                  AND h.fecha = :fecha_horarios_dia
                ORDER BY h.hora ASC
            ";

            $stmtHorariosDia = $conexion->prepare($sqlHorariosDia);
            $stmtHorariosDia->execute([
                ':id_servicio_horarios_dia' => $idServicioAgendaActual,
                ':fecha_horarios_dia'       => $fechaAgenda
            ]);
            $horariosDB = $stmtHorariosDia->fetchAll(PDO::FETCH_ASSOC);

            /* INDEXAR HORARIOS REALES POR HORA */
            $horariosPorHora = [];
            foreach ($horariosDB as $horarioDB) {
                $horaDB = substr($horarioDB['hora'], 0, 5);
                $horarioDB['id_servicio'] = $idServicioAgendaActual;
                $horarioDB['nombre_servicio'] = $nombreServicioAgendaActual;
                $horarioDB['nombre_departamento'] = $nombreDepartamentoAgendaActual;

                $horariosPorHora[$horaDB] = $horarioDB;
            }

            /* GENERAR HORAS SEGÚN CONFIGURACIÓN */
            $zonaChile = new DateTimeZone('America/Santiago');
            $cursor = new DateTime($fechaAgenda . ' ' . $horaInicio, $zonaChile);
            $finAtencion = new DateTime($fechaAgenda . ' ' . $horaFin, $zonaChile);

            while ($cursor < $finAtencion) {
                $horaActual = $cursor->format('H:i');

                /* EVALUAR ALMUERZO */
                $esHoraAlmuerzo = false;
                if ($tieneAlmuerzo && !empty($almuerzoInicio) && !empty($almuerzoFin)) {
                    $inicioAlmuerzo = new DateTime($fechaAgenda . ' ' . $almuerzoInicio, $zonaChile);
                    $finAlmuerzo = new DateTime($fechaAgenda . ' ' . $almuerzoFin, $zonaChile);

                    if ($cursor >= $inicioAlmuerzo && $cursor < $finAlmuerzo) {
                        $esHoraAlmuerzo = true;
                    }
                }

                if (!$esHoraAlmuerzo) {
                    $horarioReal = $horariosPorHora[$horaActual] ?? null;

                    if ($horarioReal) {
                        $horariosAgenda[] = $horarioReal;
                    } else {
                        $horariosAgenda[] = [
                            'hora'                => $horaActual,
                            'id_horario'          => null,
                            'estado_horario'      => 'disponible',
                            'observacion_horario' => null,
                            'id_reserva'          => null,
                            'nombre_reserva'      => null,
                            'rut_reserva'         => null,
                            'telefono_reserva'    => null,
                            'correo_reserva'      => null,
                            'tipo_identificacion' => null,
                            'estado_reserva'      => null,
                            'id_servicio'         => $idServicioAgendaActual,
                            'nombre_servicio'     => $nombreServicioAgendaActual,
                            'nombre_departamento' => $nombreDepartamentoAgendaActual
                        ];
                    }
                }

                $cursor->modify('+' . $intervaloMinutos . ' minutes');
            }
        }

        if ($servicioSeleccionado === '' && count($horariosAgenda) > 1) {
            usort($horariosAgenda, function ($a, $b) {
                $horaA = $a['hora'] ?? '';
                $horaB = $b['hora'] ?? '';

                if ($horaA === $horaB) {
                    return strcmp($a['nombre_servicio'] ?? '', $b['nombre_servicio'] ?? '');
                }

                return strcmp($horaA, $horaB);
            });
        }
    }

} catch (PDOException $e) {
    error_log('Error al cargar agenda.php: ' . $e->getMessage());
    $errorServicios = 'No fue posible cargar los servicios municipales.';
    $errorAgenda = 'No fue posible cargar la agenda.';
}


/* =========================================================
   ESTADOS VISUALES Y FORMATO
   ========================================================= */

foreach ($horariosAgenda as &$horario) {
    if (
        !empty($horario['id_reserva']) &&
        ($horario['estado_reserva'] === 'confirmada' || $horario['estado_reserva'] === 'reagendada')
    ) {
        $horario['estado_visual'] = 'reservada';
        $horario['estado_texto'] = 'Reservada';
        $totalReservadas++;
    } elseif (isset($horario['estado_horario']) && $horario['estado_horario'] === 'bloqueado') {
        $horario['estado_visual'] = 'bloqueada';
        $horario['estado_texto'] = 'Bloqueada';
        $totalBloqueadas++;
    } else {
        $horario['estado_visual'] = 'disponible';
        $horario['estado_texto'] = 'Disponible';
        $totalDisponibles++;
    }

    if (!empty($horario['hora'])) {
        $horario['hora_formateada'] = substr($horario['hora'], 0, 5);
    } else {
        $horario['hora_formateada'] = '';
    }
}
unset($horario);


/* =========================================================
   FECHA MOSTRADA
   ========================================================= */

$fechaObjetoMostrar = DateTime::createFromFormat('Y-m-d', $fechaAgenda);

$diasSemana = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo'
];

$fechaMostrar = $diasSemana[(int) $fechaObjetoMostrar->format('N')]
    . ' ' . (int) $fechaObjetoMostrar->format('d')
    . ' de ' . $nombreMeses[(int) $fechaObjetoMostrar->format('m')]
    . ' de ' . $fechaObjetoMostrar->format('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - Panel Administrativo</title>

    <link rel="stylesheet" href="../css/compartidos/general.css">
    <link rel="stylesheet" href="../css/compartidos/navbar_funcionario.css">
    <link rel="stylesheet" href="../css/compartidos/sidebar_funcionario.css">
    <link rel="stylesheet" href="../css/compartidos/footer_funcionario.css">
    <link rel="stylesheet" href="../css/funcionarios/agenda.css">
    <link rel="stylesheet" href="../css/funcionarios/formulario_crear_reserva.css">

    <script src="../js/agenda.js" defer></script>
</head>
<body>

<?php require_once '../componentes/navbar_funcionario.php'; ?>

<div class="admin-layout">

    <?php require_once '../componentes/sidebar_funcionario.php'; ?>

    <main class="admin-content">

        <section class="agenda-header">
            <div>
                <span class="page-kicker">Gestión administrativa</span>
                <h1>Agenda</h1>
                <p>Consulta la disponibilidad, reservas y bloqueos de cada día.</p>
            </div>

            <div class="agenda-header-actions">
                <button type="button" class="agenda-action-button secondary">
                    🔔 <span>Recordatorio</span>
                </button>

                <button type="button" class="agenda-action-button primary" id="btnAgregarHoraAgenda"
                    data-servicio="<?= (int) $servicioSeleccionado ?>"
                    data-servicio-nombre="<?= htmlspecialchars($servicioActualNombre, ENT_QUOTES, 'UTF-8') ?>"
                    data-fecha="<?= htmlspecialchars($fechaAgenda, ENT_QUOTES, 'UTF-8') ?>">
                    + <span>Agregar hora</span>
                </button>
            </div>
        </section>

        <?php if ($rolFuncionario === 'superadmin' || $rolFuncionario === 'administrador' || $rolFuncionario === 'funcionario'): ?>
            <section class="agenda-service-selector">
                <div class="agenda-service-selector-info">
                    <span class="agenda-service-kicker">Vista administrativa</span>
                    <h2>Agenda municipal</h2>
                    <p>Consulta todos los servicios permitidos o filtra uno específico.</p>
                </div>

                <div class="agenda-service-selector-controls">
                    <?php if ($rolFuncionario === 'superadmin'): ?>
                        <div class="agenda-service-selector-control">
                            <label for="departamentoAgenda">Departamento</label>
                            <select id="departamentoAgenda" name="departamento" class="agenda-service-select">
                                <option value="">Todos los departamentos</option>
                                <?php foreach ($departamentosAgenda as $departamento): ?>
                                    <option value="<?= (int) $departamento['id_departamento'] ?>"
                                        <?= ($departamentoSeleccionado === (int) $departamento['id_departamento']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($departamento['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="agenda-service-selector-control">
                        <label for="servicioAgenda">Servicio</label>
                        <select id="servicioAgenda" name="servicio" class="agenda-service-select">
                            <option value="">Todos los servicios</option>
                            <?php foreach ($serviciosAgenda as $servicio): ?>
                                <option value="<?= (int) $servicio['id_servicio'] ?>"
                                    <?= ($servicioSeleccionado === (int) $servicio['id_servicio']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($servicio['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($rolFuncionario === 'superadmin' && $departamentoSeleccionado === ''): ?>
                                        — <?= htmlspecialchars($servicio['nombre_departamento'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($errorServicios !== ''): ?>
                            <span class="agenda-service-error">
                                <?= htmlspecialchars($errorServicios, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($errorAgenda !== ''): ?>
            <div class="agenda-service-error" style="margin-bottom: 20px; padding: 14px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;">
                <?= htmlspecialchars($errorAgenda, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <section class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-title">
                    <h2>
                        <?= htmlspecialchars(ucfirst($nombreMesCalendario), ENT_QUOTES, 'UTF-8') ?>
                        <?= $anioCalendario ?>
                    </h2>
                    <span>Calendario de agenda</span>
                </div>

                <div class="calendar-navigation">
                    <button type="button" class="calendar-nav-button" id="mesAnterior" aria-label="Mes anterior">‹</button>
                    <button type="button" class="calendar-today-button" id="btnHoy">Hoy</button>
                    <button type="button" class="calendar-nav-button" id="mesSiguiente" aria-label="Mes siguiente">›</button>
                </div>
            </div>

            <div class="calendar-legend">
                <span><i class="legend-dot available"></i> Disponible</span>
                <span><i class="legend-dot reserved"></i> Reservas</span>
                <span><i class="legend-dot blocked"></i> Bloqueado</span>
                <span><i class="legend-dot reminder"></i> Recordatorio</span>
            </div>

            <div class="calendar-wrapper">
                <div class="calendar-grid" role="grid" aria-label="Calendario mensual">
                    <div class="calendar-weekday">LUN</div>
                    <div class="calendar-weekday">MAR</div>
                    <div class="calendar-weekday">MIÉ</div>
                    <div class="calendar-weekday">JUE</div>
                    <div class="calendar-weekday">VIE</div>
                    <div class="calendar-weekday weekend">SÁB</div>
                    <div class="calendar-weekday weekend">DOM</div>

                    <?php
                    $primerDiaMes = clone $fechaMes;
                    $diaSemanaPrimerDia = (int) $primerDiaMes->format('N');

                    for ($i = $diaSemanaPrimerDia - 1; $i > 0; $i--) {
                        $diaAnterior = clone $primerDiaMes;
                        $diaAnterior->modify('-' . $i . ' days');
                        ?>
                        <button type="button" class="calendar-day other-month" disabled>
                            <span class="day-number"><?= $diaAnterior->format('j') ?></span>
                        </button>
                    <?php
                    }

                    $ultimoDiaMes = clone $fechaMes;
                    $ultimoDiaMes->modify('last day of this month');
                    $diaActual = clone $primerDiaMes;

                    while ($diaActual <= $ultimoDiaMes) {
                        $fechaDia = $diaActual->format('Y-m-d');
                        $numeroDia = (int) $diaActual->format('j');
                        $diaSemana = (int) $diaActual->format('N');

                        $clasesDia = 'calendar-day';
                        if ($fechaDia === $fechaAgenda) {
                            $clasesDia .= ' selected';
                        }
                        if ($diaSemana >= 6) {
                            $clasesDia .= ' weekend';
                        }
                        if ($fechaDia < $fechaHoy) {
                            $clasesDia .= ' dia-pasado';
                        }
                        ?>
                        <button type="button" class="<?= htmlspecialchars($clasesDia, ENT_QUOTES, 'UTF-8') ?>"
                            data-fecha="<?= htmlspecialchars($fechaDia, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="day-number"><?= $numeroDia ?></span>

                            <?php $reservasDelDia = $reservasCalendario[$fechaDia] ?? []; ?>
                            <?php if (!empty($reservasDelDia)): ?>
                                <div class="calendar-reservas-preview">
                                    <?php foreach (array_slice($reservasDelDia, 0, 3) as $reservaCalendario): ?>
                                        <span class="calendar-reserva-pill">
                                            Reserva <?= htmlspecialchars($reservaCalendario['hora'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php endforeach; ?>

                                    <?php $reservasRestantes = count($reservasDelDia) - 3; ?>
                                    <?php if ($reservasRestantes > 0): ?>
                                        <span class="calendar-reserva-more">+<?= $reservasRestantes ?> reservas</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </button>
                        <?php
                        $diaActual->modify('+1 day');
                    }

                    $ultimoDiaSemana = (int) $ultimoDiaMes->format('N');
                    $diasSobrantes = 7 - $ultimoDiaSemana;

                    if ($diasSobrantes < 7) {
                        for ($i = 1; $i <= $diasSobrantes; $i++) {
                            ?>
                            <button type="button" class="calendar-day other-month" disabled>
                                <span class="day-number"><?= $i ?></span>
                            </button>
                            <?php
                        }
                    }
                    ?>
                </div>
            </div>
        </section>

        <section class="day-agenda-container">
            <div class="day-agenda-header">
                <div>
                    <span class="day-agenda-kicker">Día seleccionado</span>
                    <h2><?= htmlspecialchars($fechaMostrar, ENT_QUOTES, 'UTF-8') ?></h2>

                    <div class="day-agenda-date-selector">
                        <label for="fechaSeleccionadaAgenda">Cambiar día</label>
                        <input type="date" id="fechaSeleccionadaAgenda"
                            value="<?= htmlspecialchars($fechaAgenda, ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <p>
                        <?php if ($rolFuncionario === 'superadmin' && $nombreDepartamentoSeleccionado !== ''): ?>
                            Departamento: <strong><?= htmlspecialchars($nombreDepartamentoSeleccionado, ENT_QUOTES, 'UTF-8') ?></strong><br>
                        <?php elseif ($rolFuncionario !== 'superadmin' && $departamentoActualNombre !== ''): ?>
                            Departamento: <strong><?= htmlspecialchars($departamentoActualNombre, ENT_QUOTES, 'UTF-8') ?></strong><br>
                        <?php endif; ?>

                        <?php if ($servicioActualNombre !== ''): ?>
                            Servicio: <strong><?= htmlspecialchars($servicioActualNombre, ENT_QUOTES, 'UTF-8') ?></strong>
                        <?php else: ?>
                            <?php if ($rolFuncionario === 'superadmin' && $nombreDepartamentoSeleccionado === ''): ?>
                                Todos los departamentos y servicios.
                            <?php elseif ($rolFuncionario === 'superadmin'): ?>
                                Todos los servicios del departamento seleccionado.
                            <?php else: ?>
                                Todos los servicios de tu departamento.
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="day-agenda-actions">
                    <button type="button" class="day-button secondary">🔔 Recordatorio</button>
                    <button type="button" class="day-button primary" id="btnAgregarHoraDia"
                        data-servicio="<?= (int) $servicioSeleccionado ?>"
                        data-servicio-nombre="<?= htmlspecialchars($servicioActualNombre, ENT_QUOTES, 'UTF-8') ?>"
                        data-fecha="<?= htmlspecialchars($fechaAgenda, ENT_QUOTES, 'UTF-8') ?>">
                        + Agregar hora
                    </button>
                </div>
            </div>

            <div class="day-summary">
                <div class="day-summary-item available">
                    <span class="summary-dot"></span>
                    <div>
                        <strong><?= $totalDisponibles ?></strong>
                        <span>Disponibles</span>
                    </div>
                </div>

                <div class="day-summary-item reserved">
                    <span class="summary-dot"></span>
                    <div>
                        <strong><?= $totalReservadas ?></strong>
                        <span>Reservadas</span>
                    </div>
                </div>

                <div class="day-summary-item blocked">
                    <span class="summary-dot"></span>
                    <div>
                        <strong><?= $totalBloqueadas ?></strong>
                        <span>Bloqueadas</span>
                    </div>
                </div>

                <div class="day-summary-item reminder">
                    <span class="summary-dot"></span>
                    <div>
                        <strong><?= $totalRecordatorios ?></strong>
                        <span>Recordatorios</span>
                    </div>
                </div>
            </div>

            <div class="time-list">
                <?php if (empty($serviciosParaAgenda)): ?>
                    <div class="time-row blocked">
                        <div class="time-main">
                            <span class="time-status blocked">Sin servicios</span>
                            <span class="time-service">No existen servicios disponibles para mostrar en la agenda.</span>
                        </div>
                    </div>
                <?php elseif (empty($horariosAgenda)): ?>
                    <div class="time-row blocked">
                        <div class="time-main">
                            <span class="time-status blocked">Sin atención</span>
                            <span class="time-service">No existen horarios de atención para los servicios seleccionados en la fecha.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($horariosAgenda as $horario): ?>
                        <?php if ($horario['estado_visual'] === 'reservada'): ?>
                            <div class="time-row reserved">
                                <div class="time-column">
                                    <?= htmlspecialchars($horario['hora_formateada'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="time-main">
                                    <div class="person-info">
                                        <div class="person-avatar">
                                            <?php
                                            $nombreReserva = trim($horario['nombre_reserva'] ?? '');
                                            $iniciales = '';

                                            if ($nombreReserva !== '') {
                                                $partesNombre = preg_split('/\s+/', $nombreReserva);
                                                if (isset($partesNombre[0])) {
                                                    $iniciales .= mb_strtoupper(mb_substr($partesNombre[0], 0, 1));
                                                }
                                                if (isset($partesNombre[1])) {
                                                    $iniciales .= mb_strtoupper(mb_substr($partesNombre[1], 0, 1));
                                                }
                                            }
                                            if ($iniciales === '') {
                                                $iniciales = 'RS';
                                            }
                                            echo htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </div>

                                        <div>
                                            <strong><?= htmlspecialchars($horario['nombre_reserva'] ?? 'Reserva', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars($horario['nombre_servicio'] ?? $servicioActualNombre, ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>

                                    <span class="time-status reserved">Reservada</span>
                                </div>

                                <div class="time-actions">
                                    <button type="button" class="time-action view"
                                        data-id-reserva="<?= (int) ($horario['id_reserva'] ?? 0) ?>"
                                        data-fecha="<?= htmlspecialchars($fechaAgenda, ENT_QUOTES, 'UTF-8') ?>"
                                        data-hora="<?= htmlspecialchars($horario['hora_formateada'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        👁 Ver
                                    </button>
                                    <button type="button" class="time-action reminder">🔔</button>
                                    <button type="button" class="time-action reschedule">↻</button>
                                </div>
                            </div>

                        <?php elseif ($horario['estado_visual'] === 'bloqueada'): ?>
                            <div class="time-row blocked">
                                <div class="time-column">
                                    <?= htmlspecialchars($horario['hora_formateada'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="time-main">
                                    <span class="time-status blocked">Bloqueada</span>
                                    <span class="time-service">
                                        <?= htmlspecialchars($horario['nombre_servicio'] ?? $servicioActualNombre, ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($horario['observacion_horario'])): ?>
                                            — <?= htmlspecialchars($horario['observacion_horario'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </span>
                                </div>

                                <div class="time-actions">
                                    <button type="button" class="time-action edit">✏️ Editar</button>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="time-row available">
                                <div class="time-column">
                                    <?= htmlspecialchars($horario['hora_formateada'], ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="time-main">
                                    <span class="time-status available">Disponible</span>
                                    <span class="time-service">
                                        <?= htmlspecialchars($horario['nombre_servicio'] ?? $servicioActualNombre, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </div>

                                <div class="time-actions">
                                    <button type="button" class="time-action add reserve"
                                        data-servicio="<?= (int) ($horario['id_servicio'] ?? $servicioSeleccionado) ?>"
                                        data-servicio-nombre="<?= htmlspecialchars($horario['nombre_servicio'] ?? $servicioActualNombre, ENT_QUOTES, 'UTF-8') ?>"
                                        data-fecha="<?= htmlspecialchars($fechaAgenda, ENT_QUOTES, 'UTF-8') ?>"
                                        data-hora="<?= htmlspecialchars($horario['hora_formateada'], ENT_QUOTES, 'UTF-8') ?>">
                                        + Reservar
                                    </button>
                                    <button type="button" class="time-action edit">✏️</button>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="agenda-special-actions">
                <button type="button" class="special-action" id="btnReservaAsistida"
                    data-servicio="<?= (int) $servicioSeleccionado ?>"
                    data-servicio-nombre="<?= htmlspecialchars($servicioActualNombre, ENT_QUOTES, 'UTF-8') ?>"
                    data-fecha="<?= htmlspecialchars($fechaAgenda, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="special-icon">👤</span>
                    <span>
                        <strong>Reserva asistida</strong>
                        <small>Crear una hora para una persona que necesita ayuda.</small>
                    </span>
                    <b>→</b>
                </button>

                <button type="button" class="special-action">
                    <span class="special-icon reminder-icon">🔔</span>
                    <span>
                        <strong>Gestionar recordatorios</strong>
                        <small>Revisar y administrar recordatorios del día.</small>
                    </span>
                    <b>→</b>
                </button>
            </div>
        </section>

    </main>

</div>

<!-- =========================================================
     POPUP RESUMEN DEL DÍA (MODAL)
     ========================================================= -->

<div class="agenda-modal-overlay" id="agendaModalOverlay" aria-hidden="true">
    <div class="agenda-modal" id="agendaModal" role="dialog" aria-modal="true" aria-labelledby="agendaModalTitle">
        <div class="agenda-modal-header">
            <div>
                <span class="agenda-modal-kicker">Resumen de agenda</span>
                <h2 id="agendaModalTitle">Resumen del día</h2>
                <p id="agendaModalFecha">Cargando fecha...</p>
            </div>
            <button type="button" class="agenda-modal-close" id="agendaModalClose" aria-label="Cerrar">×</button>
        </div>

        <div class="agenda-modal-loading" id="agendaModalLoading">
            <span class="agenda-modal-spinner"></span>
            <span>Consultando reservas...</span>
        </div>

        <div class="agenda-modal-content" id="agendaModalContent">
            <div class="agenda-modal-summary">
                <div class="agenda-modal-summary-card">
                    <span class="agenda-modal-summary-icon">📅</span>
                    <div>
                        <strong id="agendaModalTotal">0</strong>
                        <span>Reservas agendadas</span>
                    </div>
                </div>
            </div>

            <div class="agenda-modal-reservas-header">
                <h3>Reservas del día</h3>
                <span id="agendaModalServicio">Todos los servicios</span>
            </div>

            <div class="agenda-modal-reservas" id="agendaModalReservas"></div>
        </div>

        <div class="agenda-modal-error" id="agendaModalError"></div>

        <div class="agenda-modal-footer">
            <button type="button" class="agenda-modal-button secondary" id="agendaModalCancel">Cerrar</button>
            <button type="button" class="agenda-modal-button primary" id="agendaModalVerAgenda">Ver agenda del día</button>
        </div>
    </div>
</div>

<!-- =========================================================
     POPUP CREAR RESERVA
     ========================================================= -->

<?php
$servicios = $serviciosAgenda;
include '../componentes/formulario_crear_reserva.php';
?>

<!-- =========================================================
     FOOTER FUNCIONARIO
     ========================================================= -->

 <?php require_once '../componentes/footer_funcionario.php'; ?>

</body>
</html>