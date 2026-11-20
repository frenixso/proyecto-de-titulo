<?php

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';

/* =========================================================
   ZONA HORARIA
========================================================= */

$zonaChile = new DateTimeZone('America/Santiago');

/* =========================================================
   FUNCIONARIO AUTENTICADO
========================================================= */

$nombreFuncionario = $_SESSION['nombre_funcionario'] ?? 'Funcionario';
$apellidoFuncionario = $_SESSION['apellido_funcionario'] ?? '';
$usuarioFuncionario = $_SESSION['usuario_funcionario'] ?? '';
$rolFuncionario = $_SESSION['rol_funcionario'] ?? 'funcionario';

$nombreCompleto = trim($nombreFuncionario . ' ' . $apellidoFuncionario);

if ($nombreCompleto === '') {
    $nombreCompleto = 'Funcionario';
}

$rolMostrar = match ($rolFuncionario) {
    'superadmin' => 'Superadministrador',
    'administrador' => 'Administrador',
    default => 'Funcionario'
};

/* =========================================================
   VARIABLES PARA COMPONENTES
========================================================= */

$base_url = getenv('APP_BASE_URL') !== false ? rtrim(getenv('APP_BASE_URL'), '/') : '/sistema toma de horas cesfam';
$nombre_funcionario = $nombreCompleto;
$rol_funcionario = $rolFuncionario;
$pagina_actual = 'agenda';

/* =========================================================
   VARIABLES GENERALES
========================================================= */

$departamentosAgenda = [];
$serviciosAgenda = [];
$serviciosParaAgenda = [];
$profesionalesAgenda = [];

$serviciosPermitidosAgenda = [];
$idsServiciosPermitidos = [];

$horariosSemana = [];
$horariosAgenda = [];

$errorServicios = '';
$errorAgenda = '';
$errorProfesionales = '';

$departamentoSeleccionado = '';
$servicioSeleccionado = '';
$profesionalSeleccionado = '';

$nombreProfesionalSeleccionado = '';

$idDepartamentoActual = '';
$nombreDepartamentoSeleccionado = '';
$departamentoActualNombre = '';
$servicioActualNombre = '';

$totalDisponibles = 0;
$totalReservadas = 0;
$totalBloqueadas = 0;
$totalRecordatorios = 0;

/* =========================================================
   FECHA ACTUAL Y VALIDACIÓN
========================================================= */

$hoy = new DateTime('now', $zonaChile);
$fechaHoy = $hoy->format('Y-m-d');

$fechaAgenda = $fechaHoy;

if (isset($_GET['fecha'])) {

    $fechaRecibida = (string) $_GET['fecha'];

    $fechaTemporal = DateTime::createFromFormat(
        '!Y-m-d',
        $fechaRecibida,
        $zonaChile
    );

    if (
        $fechaTemporal &&
        $fechaTemporal->format('Y-m-d') === $fechaRecibida
    ) {
        $fechaAgenda = $fechaRecibida;
    }
}

$fechaObjetoAgenda = DateTime::createFromFormat(
    '!Y-m-d',
    $fechaAgenda,
    $zonaChile
);

if (!$fechaObjetoAgenda) {
    $fechaObjetoAgenda = clone $hoy;
    $fechaAgenda = $fechaObjetoAgenda->format('Y-m-d');
}

/* =========================================================
   CALCULAR SEMANA DE LUNES A DOMINGO
========================================================= */

$fechaInicioSemana = clone $fechaObjetoAgenda;

$numeroDiaSemana = (int) $fechaInicioSemana->format('N');

$fechaInicioSemana->modify(
    '-' . ($numeroDiaSemana - 1) . ' days'
);

$fechaInicioSemana->setTime(0, 0, 0);

$fechaFinSemana = clone $fechaInicioSemana;
$fechaFinSemana->modify('+6 days');

/* =========================================================
   NOMBRES DE LOS DÍAS Y MESES
========================================================= */

$diasSemana = [
    1 => 'lunes',
    2 => 'martes',
    3 => 'miércoles',
    4 => 'jueves',
    5 => 'viernes',
    6 => 'sábado',
    7 => 'domingo'
];

$diasSemanaCortos = [
    1 => 'LUN',
    2 => 'MAR',
    3 => 'MIÉ',
    4 => 'JUE',
    5 => 'VIE',
    6 => 'SÁB',
    7 => 'DOM'
];

$nombreMeses = [
    1 => 'enero',
    2 => 'febrero',
    3 => 'marzo',
    4 => 'abril',
    5 => 'mayo',
    6 => 'junio',
    7 => 'julio',
    8 => 'agosto',
    9 => 'septiembre',
    10 => 'octubre',
    11 => 'noviembre',
    12 => 'diciembre'
];

/* =========================================================
   GENERAR LOS SIETE DÍAS
========================================================= */

$diasSemanaAgenda = [];

$cursorDiaSemana = clone $fechaInicioSemana;

for ($i = 0; $i < 7; $i++) {

    $numeroDia = (int) $cursorDiaSemana->format('N');
    $fechaDia = $cursorDiaSemana->format('Y-m-d');

    $diasSemanaAgenda[] = [
        'fecha' => $fechaDia,
        'dia_semana' => $numeroDia,
        'nombre' => $diasSemana[$numeroDia],
        'nombre_corto' => $diasSemanaCortos[$numeroDia],
        'numero' => $cursorDiaSemana->format('d'),
        'mes' => $nombreMeses[
            (int) $cursorDiaSemana->format('n')
        ],
        'es_hoy' => $fechaDia === $fechaHoy,
        'seleccionado' => $fechaDia === $fechaAgenda,
        'es_fin_semana' => $numeroDia >= 6
    ];

    $cursorDiaSemana->modify('+1 day');
}

/* =========================================================
   TÍTULO DE LA SEMANA
========================================================= */

$mesInicioSemana = $nombreMeses[
    (int) $fechaInicioSemana->format('n')
];

$mesFinSemana = $nombreMeses[
    (int) $fechaFinSemana->format('n')
];

$anioInicioSemana = $fechaInicioSemana->format('Y');
$anioFinSemana = $fechaFinSemana->format('Y');

if (
    $mesInicioSemana === $mesFinSemana &&
    $anioInicioSemana === $anioFinSemana
) {

    $tituloSemana =
        'Semana del ' .
        $fechaInicioSemana->format('d') .
        ' al ' .
        $fechaFinSemana->format('d') .
        ' de ' .
        $mesInicioSemana .
        ' de ' .
        $anioInicioSemana;

} else {

    $tituloSemana =
        $fechaInicioSemana->format('d') .
        ' de ' .
        $mesInicioSemana .
        ' al ' .
        $fechaFinSemana->format('d') .
        ' de ' .
        $mesFinSemana .
        ' de ' .
        $anioFinSemana;
}

/* =========================================================
   HORARIO VISIBLE

   07:00 hasta 21:00.
   Cada recuadro representa 30 minutos.
========================================================= */

$horasCalendario = [];

for ($minutos = 7 * 60; $minutos < 21 * 60; $minutos += 30) {

    $horasCalendario[] = sprintf(
        '%02d:%02d',
        (int) floor($minutos / 60),
        $minutos % 60
    );
}

/* =========================================================
   FILTROS RECIBIDOS
========================================================= */

if (
    $rolFuncionario === 'superadmin' &&
    isset($_GET['departamento']) &&
    ctype_digit((string) $_GET['departamento'])
) {
    $departamentoSeleccionado = (int) $_GET['departamento'];
}

if (
    isset($_GET['servicio']) &&
    ctype_digit((string) $_GET['servicio'])
) {
    $servicioSeleccionado = (int) $_GET['servicio'];
}

/* =========================================================
   CONVERTIR BOOLEANOS DE POSTGRESQL
========================================================= */

function agendaEsVerdadero($valor): bool
{
    return (
        $valor === true ||
        $valor === 1 ||
        $valor === '1' ||
        $valor === 't' ||
        $valor === 'true'
    );
}

/* =========================================================
   OBTENER ESTADO VISUAL
========================================================= */

function agendaEstadoVisual(array $horario): array
{
    if (
        !empty($horario['id_reserva']) &&
        in_array(
            $horario['estado_reserva'] ?? '',
            ['confirmada', 'reagendada'],
            true
        )
    ) {
        return [
            'estado_visual' => 'reservada',
            'estado_texto' => 'Reservada'
        ];
    }

    if (($horario['estado_horario'] ?? '') === 'bloqueado') {
        return [
            'estado_visual' => 'bloqueada',
            'estado_texto' => 'Bloqueada'
        ];
    }

    return [
        'estado_visual' => 'disponible',
        'estado_texto' => 'Disponible'
    ];
}

/* =========================================================
   CONEXIÓN Y CARGA DE DATOS
========================================================= */

try {

    /* =====================================================
       DEPARTAMENTO DEL FUNCIONARIO
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
            INNER JOIN departamentos d
                ON d.id_departamento = f.id_departamento
            WHERE f.usuario = :usuario
            LIMIT 1
        ";

        $stmtDepartamento = $conexion->prepare($sqlDepartamento);

        $stmtDepartamento->execute([
            ':usuario' => $usuarioFuncionario
        ]);

        $filaDepartamento = $stmtDepartamento->fetch(PDO::FETCH_ASSOC);

        if ($filaDepartamento) {

            $idDepartamentoActual =
                $filaDepartamento['id_departamento'];

            $departamentoActualNombre =
                $filaDepartamento['nombre_departamento'];

            $departamentoSeleccionado =
                (int) $idDepartamentoActual;

            $departamentosAgenda[] = [
                'id_departamento' => $idDepartamentoActual,
                'nombre' => $departamentoActualNombre
            ];

        } else {

            $errorServicios =
                'No se pudo determinar el departamento del funcionario.';
        }
    }

    /* =====================================================
       DEPARTAMENTOS PARA SUPERADMIN
    ===================================================== */

    if ($rolFuncionario === 'superadmin') {

        $sqlDepartamentos = "
            SELECT
                id_departamento,
                nombre
            FROM departamentos
            WHERE activo = TRUE
            ORDER BY nombre ASC
        ";

        $stmtDepartamentos = $conexion->prepare($sqlDepartamentos);
        $stmtDepartamentos->execute();

        $departamentosAgenda =
            $stmtDepartamentos->fetchAll(PDO::FETCH_ASSOC);

        if ($departamentoSeleccionado !== '') {

            $departamentoEncontrado = false;

            foreach ($departamentosAgenda as $departamento) {

                if (
                    (int) $departamento['id_departamento'] ===
                    (int) $departamentoSeleccionado
                ) {

                    $departamentoEncontrado = true;

                    $nombreDepartamentoSeleccionado =
                        $departamento['nombre'];

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
       CARGAR PROFESIONALES ACTIVOS
    ===================================================== */

    try {

        $sqlProfesionales = "
            SELECT
                id_profesional,
                nombre,
                apellido,
                rut,
                estamento,
                nodo
            FROM profesionales
            WHERE activo = TRUE
            ORDER BY apellido ASC, nombre ASC
        ";

        $stmtProfesionales = $conexion->prepare($sqlProfesionales);
        $stmtProfesionales->execute();

        $profesionalesAgenda =
            $stmtProfesionales->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log(
            'Error al cargar profesionales de agenda: ' .
            $e->getMessage()
        );

        $errorProfesionales =
            'No fue posible cargar los profesionales.';
    }

    /* =====================================================
       VALIDAR PROFESIONAL SELECCIONADO
    ===================================================== */

    if (isset($_GET['profesional'])) {

        $profesionalRecibido =
            (string) $_GET['profesional'];

        if (
            $profesionalRecibido !== '' &&
            ctype_digit($profesionalRecibido)
        ) {

            foreach ($profesionalesAgenda as $profesional) {

                if (
                    (int) $profesional['id_profesional'] ===
                    (int) $profesionalRecibido
                ) {

                    $profesionalSeleccionado =
                        (int) $profesional['id_profesional'];

                    $nombreProfesionalSeleccionado =
                        trim(
                            $profesional['apellido'] .
                            ', ' .
                            $profesional['nombre']
                        );

                    break;
                }
            }
        }

        if ($profesionalSeleccionado === '') {

            $errorAgenda =
                'El profesional seleccionado no existe o no está activo.';
        }
    }

    /* =====================================================
       SERVICIOS DEL SISTEMA
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

            $stmtServicios->execute([
                ':id_departamento' => $departamentoSeleccionado
            ]);

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
        (
            $rolFuncionario === 'administrador' ||
            $rolFuncionario === 'funcionario'
        ) &&
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

        $stmtServicios->execute([
            ':id_departamento' => $idDepartamentoActual
        ]);

        $serviciosAgenda = $stmtServicios->fetchAll(PDO::FETCH_ASSOC);
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
       VALIDAR SERVICIO SELECCIONADO
    ===================================================== */

    if ($servicioSeleccionado !== '') {

        if (
            !isset(
                $serviciosPermitidosAgenda[$servicioSeleccionado]
            )
        ) {

            $servicioSeleccionado = '';

            $errorAgenda =
                'El servicio seleccionado no existe o no está disponible para tu departamento.';

        } else {

            $servicioActual =
                $serviciosPermitidosAgenda[$servicioSeleccionado];

            $servicioActualNombre =
                $servicioActual['nombre'];

            $departamentoActualNombre =
                $servicioActual['nombre_departamento'];
        }
    }

    /* =====================================================
       SERVICIOS QUE SE MOSTRARÁN EN LA AGENDA
    ===================================================== */

    if ($errorAgenda === '') {

        if ($servicioSeleccionado !== '') {

            $serviciosParaAgenda[] =
                $serviciosPermitidosAgenda[$servicioSeleccionado];

        } else {

            $serviciosParaAgenda = $serviciosAgenda;
        }
    }

    /* =====================================================
       CARGAR HORARIOS REALES DE LA SEMANA
    ===================================================== */

    if (!empty($serviciosParaAgenda)) {

        $idsServiciosConsulta = array_map(
            'intval',
            array_column($serviciosParaAgenda, 'id_servicio')
        );

        $placeholders = [];

        $parametros = [
            ':fecha_inicio' => $fechaInicioSemana->format('Y-m-d'),
            ':fecha_fin' => $fechaFinSemana->format('Y-m-d')
        ];

        foreach ($idsServiciosConsulta as $indice => $idServicio) {

            $nombreParametro = ':servicio_' . $indice;

            $placeholders[] = $nombreParametro;

            $parametros[$nombreParametro] = $idServicio;
        }

        $listaServiciosSQL = implode(', ', $placeholders);

        /* =============================================
           FILTRO OPCIONAL POR PROFESIONAL
        ============================================= */

        $filtroProfesionalSQL = '';

        if ($profesionalSeleccionado !== '') {

            $filtroProfesionalSQL = "
                AND a.id_profesional = :id_profesional
            ";

            $parametros[':id_profesional'] =
                $profesionalSeleccionado;
        }

        /* =============================================
           CONSULTA DE HORARIOS

           Se incluyen los datos necesarios para editar
           cada cupo individualmente.
        ============================================= */

        $sqlHorariosSemana = "

            SELECT

                h.id_horario,
                h.id_servicio,
                h.fecha,
                h.hora,
                h.hora_fin,
                h.estado AS estado_horario,
                h.observacion AS observacion_horario,
                h.id_agenda,
                h.habilitado,
                h.disponible_web,

                a.id_profesional,
                a.sector,
                a.origen_multiprofesional,
                a.segmento_habilitado,

                r.id_reserva,
                r.nombre AS nombre_reserva,
                r.rut AS rut_reserva,
                r.telefono AS telefono_reserva,
                r.correo AS correo_reserva,
                r.tipo_identificacion,
                r.estado AS estado_reserva

            FROM horarios h

            LEFT JOIN agendas a
                ON a.id_agenda = h.id_agenda

            LEFT JOIN reservas r
                ON r.id_horario = h.id_horario
                AND r.estado IN ('confirmada', 'reagendada')

            WHERE h.fecha BETWEEN :fecha_inicio AND :fecha_fin

              AND h.id_servicio IN ($listaServiciosSQL)

              $filtroProfesionalSQL

            ORDER BY
                h.fecha ASC,
                h.hora ASC

        ";

        $stmtHorariosSemana =
            $conexion->prepare($sqlHorariosSemana);

        $stmtHorariosSemana->execute($parametros);

        $horariosDBSemana =
            $stmtHorariosSemana->fetchAll(PDO::FETCH_ASSOC);

        /* =============================================
           PREPARAR MAPA DE SERVICIOS
        ============================================= */

        $mapaServicios = [];

        foreach ($serviciosParaAgenda as $servicio) {

            $mapaServicios[
                (int) $servicio['id_servicio']
            ] = $servicio;
        }

        /* =============================================
           PROCESAR HORARIOS
        ============================================= */

        foreach ($horariosDBSemana as $horarioDB) {

            $fechaHorario = substr(
                (string) $horarioDB['fecha'],
                0,
                10
            );

            $horaHorario = substr(
                (string) $horarioDB['hora'],
                0,
                5
            );

            if (
                $fechaHorario < $fechaInicioSemana->format('Y-m-d') ||
                $fechaHorario > $fechaFinSemana->format('Y-m-d')
            ) {
                continue;
            }

            if (
                $horaHorario < '07:00' ||
                $horaHorario >= '21:00'
            ) {
                continue;
            }

            $idServicioHorario =
                (int) $horarioDB['id_servicio'];

            if (!isset($mapaServicios[$idServicioHorario])) {
                continue;
            }

            $servicioHorario =
                $mapaServicios[$idServicioHorario];

            $slotSemana = $horarioDB;

            $slotSemana['fecha'] = $fechaHorario;
            $slotSemana['hora_formateada'] = $horaHorario;

            $slotSemana['nombre_servicio'] =
                $servicioHorario['nombre'];

            $slotSemana['nombre_departamento'] =
                $servicioHorario['nombre_departamento'];

            $slotSemana['id_servicio'] =
                $idServicioHorario;

            $estadoVisual = agendaEstadoVisual($slotSemana);

            $slotSemana['estado_visual'] =
                $estadoVisual['estado_visual'];

            $slotSemana['estado_texto'] =
                $estadoVisual['estado_texto'];

            /* =========================================
               UBICAR EN EL BLOQUE VISUAL DE 30 MINUTOS
            ========================================= */

            $partesHora = explode(':', $horaHorario);

            $minutosHora =
                ((int) $partesHora[0] * 60) +
                (int) $partesHora[1];

            $minutosBloque =
                (int) (floor($minutosHora / 30) * 30);

            $bloqueVisual = sprintf(
                '%02d:%02d',
                (int) floor($minutosBloque / 60),
                $minutosBloque % 60
            );

            if (!isset($horariosSemana[$fechaHorario])) {
                $horariosSemana[$fechaHorario] = [];
            }

            if (!isset($horariosSemana[$fechaHorario][$bloqueVisual])) {
                $horariosSemana[$fechaHorario][$bloqueVisual] = [];
            }

            $horariosSemana[$fechaHorario][$bloqueVisual][] =
                $slotSemana;

            /* =========================================
               GUARDAR TAMBIÉN EL DÍA SELECCIONADO
            ========================================= */

            if ($fechaHorario === $fechaAgenda) {
                $horariosAgenda[] = $slotSemana;
            }
        }

        /* =============================================
           ORDENAR LOS EVENTOS DE CADA RECUADRO
        ============================================= */

        foreach ($horariosSemana as &$bloquesFecha) {

            foreach ($bloquesFecha as &$eventosBloque) {

                usort(
                    $eventosBloque,
                    function ($a, $b) {

                        return strcmp(
                            $a['hora_formateada'],
                            $b['hora_formateada']
                        );
                    }
                );
            }

            unset($eventosBloque);
        }

        unset($bloquesFecha);
    }

    /* =====================================================
       ORDENAR AGENDA DEL DÍA
    ===================================================== */

    if (count($horariosAgenda) > 1) {

        usort(
            $horariosAgenda,
            function ($a, $b) {

                $comparacionHora = strcmp(
                    $a['hora_formateada'],
                    $b['hora_formateada']
                );

                if ($comparacionHora === 0) {

                    return strcmp(
                        $a['nombre_servicio'],
                        $b['nombre_servicio']
                    );
                }

                return $comparacionHora;
            }
        );
    }

} catch (PDOException $e) {

    error_log(
        'Error al cargar agenda.php: ' .
        $e->getMessage()
    );

    $errorAgenda =
        'No fue posible cargar la agenda.';

    if ($errorServicios === '') {
        $errorServicios =
            'No fue posible cargar los servicios.';
    }
}

/* =========================================================
   RESUMEN DIARIO Y ESTADOS VISUALES
========================================================= */

foreach ($horariosAgenda as &$horario) {

    $estadoVisual = agendaEstadoVisual($horario);

    $horario['estado_visual'] =
        $estadoVisual['estado_visual'];

    $horario['estado_texto'] =
        $estadoVisual['estado_texto'];

    if ($horario['estado_visual'] === 'reservada') {

        $totalReservadas++;

    } elseif ($horario['estado_visual'] === 'bloqueada') {

        $totalBloqueadas++;

    } else {

        $totalDisponibles++;
    }

    $horario['hora_formateada'] =
        !empty($horario['hora_formateada'])
            ? $horario['hora_formateada']
            : substr((string) ($horario['hora'] ?? ''), 0, 5);
}

unset($horario);

/* =========================================================
   FECHA MOSTRADA EN AGENDA DIARIA
========================================================= */

$fechaObjetoMostrar = DateTime::createFromFormat(
    '!Y-m-d',
    $fechaAgenda,
    $zonaChile
);

$fechaMostrar =
    ucfirst(
        $diasSemana[(int) $fechaObjetoMostrar->format('N')]
    ) .
    ' ' .
    (int) $fechaObjetoMostrar->format('d') .
    ' de ' .
    $nombreMeses[
        (int) $fechaObjetoMostrar->format('n')
    ] .
    ' de ' .
    $fechaObjetoMostrar->format('Y');

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Agenda | CESFAM Eduardo Raggio</title>

    <link
        rel="stylesheet"
        href="../css/compartidos/general.css"
    >

    <link
        rel="stylesheet"
        href="../css/compartidos/navbar_funcionario.css"
    >

    <link
        rel="stylesheet"
        href="../css/compartidos/sidebar_funcionario.css"
    >

    <link
        rel="stylesheet"
        href="../css/compartidos/footer_funcionario.css"
    >

    <link
        rel="stylesheet"
        href="../css/funcionarios/agenda.css"
    >

    <link
        rel="stylesheet"
        href="../css/funcionarios/formulario_crear_reserva.css"
    >

    <link
        rel="stylesheet"
        href="../css/funcionarios/generar_agenda.css"
    >

    <!-- ÚNICO JAVASCRIPT DE LA AGENDA -->

    <script src="../js/agenda.js" defer></script>

</head>

<body>

<?php require_once '../componentes/navbar_funcionario.php'; ?>


<div class="admin-layout">

    <?php require_once '../componentes/sidebar_funcionario.php'; ?>


    <main class="admin-content">

        <!-- =============================================
             ENCABEZADO
        ============================================== -->

        <section class="agenda-header">

            <div>

                <span class="page-kicker">
                    Gestión administrativa
                </span>

                <h1>Agenda</h1>

                <p>
                    Consulta la disponibilidad, reservas y bloques
                    de atención durante la semana.
                </p>

            </div>

        </section>


        <!-- =============================================
             SELECTOR DE PROFESIONAL
        ============================================== -->

        <section class="selector-profesional-agenda">

            <div class="selector-profesional-titulo">

                <span class="agenda-service-kicker">
                    Generación de agenda
                </span>

                <h2>Seleccionar profesional</h2>

                <p>
                    Selecciona al profesional y aplica el filtro
                    para consultar sus cupos.
                </p>

            </div>

            <form
                method="GET"
                action="agenda.php"
                class="selector-profesional-contenido"
                id="formFiltroProfesionalAgenda"
            >

                <input
                    type="search"
                    id="buscarProfesionalAgenda"
                    placeholder="Buscar por nombre o apellido..."
                    autocomplete="off"
                    aria-label="Buscar profesional"
                >

                <select
                    id="profesionalAgenda"
                    name="profesional"
                    aria-label="Seleccionar profesional"
                >

                    <option value="">
                        Todos los profesionales
                    </option>

                    <?php foreach ($profesionalesAgenda as $profesional): ?>

                        <option
                            value="<?= (int) $profesional['id_profesional'] ?>"
                            data-estamento="<?= htmlspecialchars($profesional['estamento'], ENT_QUOTES, 'UTF-8') ?>"
                            data-rut="<?= htmlspecialchars($profesional['rut'], ENT_QUOTES, 'UTF-8') ?>"
                            data-nodo="<?= htmlspecialchars($profesional['nodo'], ENT_QUOTES, 'UTF-8') ?>"
                            <?= (
                                $profesionalSeleccionado !== '' &&
                                (int) $profesional['id_profesional'] ===
                                $profesionalSeleccionado
                            ) ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $profesional['apellido'] . ', ' . $profesional['nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            — <?= htmlspecialchars(
                                $profesional['nodo'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <!-- CONSERVAR FILTROS AL APLICAR PROFESIONAL -->

                <input
                    type="hidden"
                    name="fecha"
                    value="<?= htmlspecialchars($fechaAgenda, ENT_QUOTES, 'UTF-8') ?>"
                >

                <?php if ($rolFuncionario === 'superadmin' && $departamentoSeleccionado !== ''): ?>

                    <input
                        type="hidden"
                        name="departamento"
                        value="<?= (int) $departamentoSeleccionado ?>"
                    >

                <?php endif; ?>

                <?php if ($servicioSeleccionado !== ''): ?>

                    <input
                        type="hidden"
                        name="servicio"
                        value="<?= (int) $servicioSeleccionado ?>"
                    >

                <?php endif; ?>


                <button
                    type="submit"
                    class="calendar-today-button"
                >
                    Ver agenda
                </button>


                <div
                    id="informacionProfesionalAgenda"
                    aria-live="polite"
                >

                    <?php if ($profesionalSeleccionado !== ''): ?>

                        Profesional seleccionado:
                        <?= htmlspecialchars(
                            $nombreProfesionalSeleccionado,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    <?php else: ?>

                        No hay ningún profesional seleccionado.

                    <?php endif; ?>

                </div>


                <?php if ($errorProfesionales !== ''): ?>

                    <span class="agenda-service-error">

                        <?= htmlspecialchars(
                            $errorProfesionales,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                <?php endif; ?>

            </form>

        </section>


        <!-- =============================================
             ERROR DE AGENDA
        ============================================== -->

        <?php if ($errorAgenda !== ''): ?>

            <div
                class="agenda-service-error"
                style="margin-bottom:20px;padding:14px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;"
            >

                <?= htmlspecialchars(
                    $errorAgenda,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =============================================
             CALENDARIO SEMANAL
        ============================================== -->

        <section class="calendar-container">

            <div class="calendar-header">

                <div class="calendar-title">

                    <h2>
                        <?= htmlspecialchars(
                            $tituloSemana,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h2>

                    <span>
                        Vista semanal · 07:00 a 21:00
                    </span>

                </div>

                <div class="calendar-navigation">

                    <button
                        type="button"
                        class="calendar-nav-button"
                        id="mesAnterior"
                        aria-label="Semana anterior"
                        title="Semana anterior"
                    >
                        ‹
                    </button>

                    <button
                        type="button"
                        class="calendar-today-button"
                        id="btnHoy"
                    >
                        Hoy
                    </button>

                    <button
                        type="button"
                        class="calendar-nav-button"
                        id="mesSiguiente"
                        aria-label="Semana siguiente"
                        title="Semana siguiente"
                    >
                        ›
                    </button>

                </div>

            </div>


            <!-- LEYENDA -->

            <div class="calendar-legend">

                <span>
                    <i class="legend-dot available"></i>
                    Disponible
                </span>

                <span>
                    <i class="legend-dot reserved"></i>
                    Reservada
                </span>

                <span>
                    <i class="legend-dot blocked"></i>
                    Bloqueada
                </span>

                <span>
                    <i class="legend-dot"></i>
                    Recuadro vacío: generar agenda
                </span>

            </div>


            <!-- CUADRÍCULA -->

            <div class="week-calendar-scroll">

                <div class="week-calendar-grid">

                    <div class="week-calendar-corner">
                        Hora
                    </div>


                    <!-- ENCABEZADOS DE LOS DÍAS -->

                    <?php foreach ($diasSemanaAgenda as $dia): ?>

                        <button
                            type="button"
                            class="week-day-header
                                <?= $dia['es_hoy'] ? 'today' : '' ?>
                                <?= $dia['seleccionado'] ? 'selected' : '' ?>
                                <?= $dia['es_fin_semana'] ? 'weekend' : '' ?>"
                            data-fecha="<?= htmlspecialchars(
                                $dia['fecha'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            title="Consultar <?= htmlspecialchars(
                                $dia['nombre'] . ' ' . $dia['numero'] . ' de ' . $dia['mes'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                            <span class="week-day-name">
                                <?= htmlspecialchars(
                                    $dia['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                            <strong class="week-day-number">
                                <?= (int) $dia['numero'] ?>
                            </strong>

                            <span class="week-day-month">
                                <?= htmlspecialchars(
                                    $dia['mes'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                            <?php if ($dia['es_hoy']): ?>

                                <span class="week-day-today-label">
                                    Hoy
                                </span>

                            <?php endif; ?>

                        </button>

                    <?php endforeach; ?>


                    <!-- EJE DE HORAS -->

                    <div class="week-time-axis">

                        <?php foreach ($horasCalendario as $hora): ?>

                            <div class="week-time-label">

                                <?php if (substr($hora, 3, 2) === '00'): ?>

                                    <?= htmlspecialchars(
                                        $hora,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- COLUMNAS DE LOS DÍAS -->

                    <?php foreach ($diasSemanaAgenda as $dia): ?>

                        <div
                            class="week-day-column
                                <?= $dia['es_hoy'] ? 'today' : '' ?>
                                <?= $dia['es_fin_semana'] ? 'weekend' : '' ?>"
                            data-fecha="<?= htmlspecialchars(
                                $dia['fecha'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                            <?php foreach ($horasCalendario as $horaBloque): ?>

                                <?php

                                $eventosBloque =
                                    $horariosSemana[$dia['fecha']][$horaBloque] ?? [];

                                ?>

                                <div class="week-time-cell">

                                    <?php if (!empty($eventosBloque)): ?>

                                        <div class="week-cell-events">

                                            <?php foreach ($eventosBloque as $evento): ?>

                                                <?php

                                                $horaEvento =
                                                    $evento['hora_formateada'];

                                                $nombreServicioEvento =
                                                    $evento['nombre_servicio'] ?? 'Servicio';

                                                $claseServicio =
                                                    $evento['estado_visual'] ?? 'available';

                                                ?>


                                                <?php if ($claseServicio === 'reservada'): ?>

                                                    <button
                                                        type="button"
                                                        class="week-slot reserved time-action view"
                                                        data-id-reserva="<?= (int) ($evento['id_reserva'] ?? 0) ?>"
                                                        data-id-horario="<?= (int) $evento['id_horario'] ?>"
                                                        data-fecha="<?= htmlspecialchars(
                                                            $dia['fecha'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-hora="<?= htmlspecialchars(
                                                            $horaEvento,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        title="<?= htmlspecialchars(
                                                            ($evento['nombre_reserva'] ?? 'Reserva') . ' - ' . $nombreServicioEvento,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                    >

                                                        <strong>
                                                            <?= htmlspecialchars(
                                                                $horaEvento,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </strong>

                                                        <span>
                                                            <?= htmlspecialchars(
                                                                $evento['nombre_reserva'] ?? 'Reservada',
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </span>

                                                    </button>


                                                <?php elseif ($claseServicio === 'bloqueada'): ?>

                                                    <button
                                                        type="button"
                                                        class="week-slot blocked time-action edit-horario"
                                                        data-id-horario="<?= (int) $evento['id_horario'] ?>"
                                                        data-id-servicio="<?= (int) $evento['id_servicio'] ?>"
                                                        data-id-agenda="<?= (int) ($evento['id_agenda'] ?? 0) ?>"
                                                        data-servicio-nombre="<?= htmlspecialchars(
                                                            $nombreServicioEvento,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-fecha="<?= htmlspecialchars(
                                                            $dia['fecha'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-hora="<?= htmlspecialchars(
                                                            $horaEvento,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-hora-fin="<?= htmlspecialchars(
                                                            substr((string) ($evento['hora_fin'] ?? ''), 0, 5),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-estado="bloqueado"
                                                        data-observacion="<?= htmlspecialchars(
                                                            $evento['observacion_horario'] ?? '',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-habilitado="<?= agendaEsVerdadero($evento['habilitado'] ?? false) ? '1' : '0' ?>"
                                                        data-disponible-web="<?= agendaEsVerdadero($evento['disponible_web'] ?? false) ? '1' : '0' ?>"
                                                        title="Editar cupo bloqueado"
                                                    >

                                                        <strong>
                                                            <?= htmlspecialchars(
                                                                $horaEvento,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </strong>

                                                        <span>
                                                            Bloqueada
                                                        </span>

                                                    </button>


                                                <?php else: ?>

                                                    <button
                                                        type="button"
                                                        class="week-slot available time-action edit-horario"
                                                        data-id-horario="<?= (int) $evento['id_horario'] ?>"
                                                        data-id-servicio="<?= (int) $evento['id_servicio'] ?>"
                                                        data-id-agenda="<?= (int) ($evento['id_agenda'] ?? 0) ?>"
                                                        data-servicio-nombre="<?= htmlspecialchars(
                                                            $nombreServicioEvento,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-fecha="<?= htmlspecialchars(
                                                            $dia['fecha'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-hora="<?= htmlspecialchars(
                                                            $horaEvento,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-hora-fin="<?= htmlspecialchars(
                                                            substr((string) ($evento['hora_fin'] ?? ''), 0, 5),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-estado="disponible"
                                                        data-observacion="<?= htmlspecialchars(
                                                            $evento['observacion_horario'] ?? '',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                        data-habilitado="<?= agendaEsVerdadero($evento['habilitado'] ?? false) ? '1' : '0' ?>"
                                                        data-disponible-web="<?= agendaEsVerdadero($evento['disponible_web'] ?? false) ? '1' : '0' ?>"
                                                        title="<?= htmlspecialchars(
                                                            'Editar cupo ' . $horaEvento . ' - ' . $nombreServicioEvento,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                    >

                                                        <strong>
                                                            <?= htmlspecialchars(
                                                                $horaEvento,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </strong>

                                                        <span>
                                                            <?= htmlspecialchars(
                                                                $nombreServicioEvento,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>
                                                        </span>

                                                    </button>

                                                <?php endif; ?>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php else: ?>

                                        <button
                                            type="button"
                                            class="week-cell-generar"
                                            data-fecha="<?= htmlspecialchars(
                                                $dia['fecha'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            data-hora="<?= htmlspecialchars(
                                                $horaBloque,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            title="Generar agenda para este día y horario"
                                            aria-label="Generar agenda el <?= htmlspecialchars(
                                                $dia['fecha'] . ' a las ' . $horaBloque,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        ></button>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endforeach; ?>

                </div>


                <div class="week-calendar-bottom">

                    <span>21:00</span>

                </div>

            </div>

        </section>


        <!-- =============================================
             AGENDA DEL DÍA SELECCIONADO
        ============================================== -->

        <section class="day-agenda-container">

            <div class="day-agenda-header">

                <div>

                    <span class="day-agenda-kicker">
                        Día seleccionado
                    </span>

                    <h2>
                        <?= htmlspecialchars(
                            $fechaMostrar,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </h2>

                    <div class="day-agenda-date-selector">

                        <label for="fechaSeleccionadaAgenda">
                            Cambiar día
                        </label>

                        <input
                            type="date"
                            id="fechaSeleccionadaAgenda"
                            value="<?= htmlspecialchars(
                                $fechaAgenda,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>

                    <p>

                        <?php if ($rolFuncionario === 'superadmin'): ?>

                            <?php if ($nombreDepartamentoSeleccionado !== ''): ?>

                                Departamento:
                                <strong>
                                    <?= htmlspecialchars(
                                        $nombreDepartamentoSeleccionado,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </strong>

                                <br>

                            <?php else: ?>

                                Todos los departamentos.

                                <br>

                            <?php endif; ?>

                        <?php elseif ($departamentoActualNombre !== ''): ?>

                            Departamento:
                            <strong>
                                <?= htmlspecialchars(
                                    $departamentoActualNombre,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                            <br>

                        <?php endif; ?>


                        <?php if ($servicioActualNombre !== ''): ?>

                            Servicio:
                            <strong>
                                <?= htmlspecialchars(
                                    $servicioActualNombre,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>

                        <?php else: ?>

                            Todos los servicios disponibles.

                        <?php endif; ?>

                    </p>

                    <?php if ($profesionalSeleccionado !== ''): ?>

                        <p>
                            Profesional:
                            <strong>
                                <?= htmlspecialchars(
                                    $nombreProfesionalSeleccionado,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <!-- RESUMEN DEL DÍA -->

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


            <!-- LISTA DE HORARIOS DEL DÍA -->

            <div class="time-list">

                <?php if (empty($serviciosParaAgenda)): ?>

                    <div class="time-row blocked">

                        <div class="time-main">

                            <span class="time-status blocked">
                                Sin servicios
                            </span>

                            <span class="time-service">
                                No existen servicios disponibles para mostrar.
                            </span>

                        </div>

                    </div>

                <?php elseif (empty($horariosAgenda)): ?>

                    <div class="time-row blocked">

                        <div class="time-main">

                            <span class="time-status blocked">
                                Sin cupos generados
                            </span>

                            <span class="time-service">

                                <?php if ($profesionalSeleccionado !== ''): ?>

                                    El profesional seleccionado no tiene
                                    cupos registrados para esta fecha.

                                <?php else: ?>

                                    No existen cupos registrados para esta fecha.
                                    Puedes seleccionar un profesional y hacer clic
                                    en un recuadro vacío del calendario para generar
                                    una agenda.

                                <?php endif; ?>

                            </span>

                        </div>

                    </div>

                <?php else: ?>

                    <?php foreach ($horariosAgenda as $horario): ?>


                        <?php if ($horario['estado_visual'] === 'reservada'): ?>

                            <div class="time-row reserved">

                                <div class="time-column">

                                    <?= htmlspecialchars(
                                        $horario['hora_formateada'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                                <div class="time-main">

                                    <div class="person-info">

                                        <div class="person-avatar">

                                            <?php

                                            $nombreReserva =
                                                trim($horario['nombre_reserva'] ?? '');

                                            $iniciales = '';

                                            if ($nombreReserva !== '') {

                                                $partesNombre =
                                                    preg_split('/\s+/', $nombreReserva);

                                                if (!empty($partesNombre[0])) {

                                                    $iniciales .= mb_strtoupper(
                                                        mb_substr(
                                                            $partesNombre[0],
                                                            0,
                                                            1,
                                                            'UTF-8'
                                                        )
                                                    );
                                                }

                                                if (!empty($partesNombre[1])) {

                                                    $iniciales .= mb_strtoupper(
                                                        mb_substr(
                                                            $partesNombre[1],
                                                            0,
                                                            1,
                                                            'UTF-8'
                                                        )
                                                    );
                                                }
                                            }

                                            echo htmlspecialchars(
                                                $iniciales !== '' ? $iniciales : 'RS',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );

                                            ?>

                                        </div>

                                        <div>

                                            <strong>
                                                <?= htmlspecialchars(
                                                    $horario['nombre_reserva'] ?? 'Reserva',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= htmlspecialchars(
                                                    $horario['nombre_servicio'] ?? '',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>

                                        </div>

                                    </div>

                                    <span class="time-status reserved">
                                        Reservada
                                    </span>

                                </div>

                                <div class="time-actions">

                                    <button
                                        type="button"
                                        class="time-action view"
                                        data-id-reserva="<?= (int) ($horario['id_reserva'] ?? 0) ?>"
                                        data-id-horario="<?= (int) $horario['id_horario'] ?>"
                                        data-fecha="<?= htmlspecialchars(
                                            $fechaAgenda,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-hora="<?= htmlspecialchars(
                                            $horario['hora_formateada'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                        👁 Ver
                                    </button>

                                    <button
                                        type="button"
                                        class="time-action reminder"
                                    >
                                        🔔
                                    </button>

                                    <button
                                        type="button"
                                        class="time-action reschedule"
                                    >
                                        ↻
                                    </button>

                                </div>

                            </div>


                        <?php elseif ($horario['estado_visual'] === 'bloqueada'): ?>

                            <div class="time-row blocked">

                                <div class="time-column">

                                    <?= htmlspecialchars(
                                        $horario['hora_formateada'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                                <div class="time-main">

                                    <span class="time-status blocked">
                                        Bloqueada
                                    </span>

                                    <span class="time-service">

                                        <?= htmlspecialchars(
                                            $horario['nombre_servicio'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                        <?php if (!empty($horario['observacion_horario'])): ?>

                                            —
                                            <?= htmlspecialchars(
                                                $horario['observacion_horario'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        <?php endif; ?>

                                    </span>

                                </div>

                                <div class="time-actions">

                                    <button
                                        type="button"
                                        class="time-action edit edit-horario"
                                        data-id-horario="<?= (int) $horario['id_horario'] ?>"
                                        data-id-servicio="<?= (int) $horario['id_servicio'] ?>"
                                        data-id-agenda="<?= (int) ($horario['id_agenda'] ?? 0) ?>"
                                        data-servicio-nombre="<?= htmlspecialchars(
                                            $horario['nombre_servicio'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-fecha="<?= htmlspecialchars(
                                            $fechaAgenda,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-hora="<?= htmlspecialchars(
                                            $horario['hora_formateada'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-hora-fin="<?= htmlspecialchars(
                                            substr((string) ($horario['hora_fin'] ?? ''), 0, 5),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-estado="bloqueado"
                                        data-observacion="<?= htmlspecialchars(
                                            $horario['observacion_horario'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-habilitado="<?= agendaEsVerdadero($horario['habilitado'] ?? false) ? '1' : '0' ?>"
                                        data-disponible-web="<?= agendaEsVerdadero($horario['disponible_web'] ?? false) ? '1' : '0' ?>"
                                    >
                                        ✏️ Editar
                                    </button>

                                </div>

                            </div>


                        <?php else: ?>

                            <div class="time-row available">

                                <div class="time-column">

                                    <?= htmlspecialchars(
                                        $horario['hora_formateada'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </div>

                                <div class="time-main">

                                    <span class="time-status available">
                                        Disponible
                                    </span>

                                    <span class="time-service">

                                        <?= htmlspecialchars(
                                            $horario['nombre_servicio'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>

                                </div>

                                <div class="time-actions">

                                    <button
                                        type="button"
                                        class="time-action edit edit-horario"
                                        data-id-horario="<?= (int) $horario['id_horario'] ?>"
                                        data-id-servicio="<?= (int) $horario['id_servicio'] ?>"
                                        data-id-agenda="<?= (int) ($horario['id_agenda'] ?? 0) ?>"
                                        data-servicio-nombre="<?= htmlspecialchars(
                                            $horario['nombre_servicio'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-fecha="<?= htmlspecialchars(
                                            $fechaAgenda,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-hora="<?= htmlspecialchars(
                                            $horario['hora_formateada'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-hora-fin="<?= htmlspecialchars(
                                            substr((string) ($horario['hora_fin'] ?? ''), 0, 5),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-estado="disponible"
                                        data-observacion="<?= htmlspecialchars(
                                            $horario['observacion_horario'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        data-habilitado="<?= agendaEsVerdadero($horario['habilitado'] ?? false) ? '1' : '0' ?>"
                                        data-disponible-web="<?= agendaEsVerdadero($horario['disponible_web'] ?? false) ? '1' : '0' ?>"
                                    >
                                        ✏️ Editar cupo
                                    </button>

                                </div>

                            </div>

                        <?php endif; ?>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- ACCIONES ESPECIALES -->

            <div class="agenda-special-actions">

                <button
                    type="button"
                    class="special-action"
                    id="btnReservaAsistida"
                    data-servicio="<?= $servicioSeleccionado !== '' ? (int) $servicioSeleccionado : '' ?>"
                    data-servicio-nombre="<?= htmlspecialchars(
                        $servicioActualNombre,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    data-fecha="<?= htmlspecialchars(
                        $fechaAgenda,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                    <span class="special-icon">
                        👤
                    </span>

                    <span>
                        <strong>Reserva asistida</strong>

                        <small>
                            Crear una hora para una persona que necesita ayuda.
                        </small>
                    </span>

                    <b>→</b>

                </button>


                <button
                    type="button"
                    class="special-action"
                >

                    <span class="special-icon reminder-icon">
                        🔔
                    </span>

                    <span>
                        <strong>Gestionar recordatorios</strong>

                        <small>
                            Revisar y administrar recordatorios del día.
                        </small>
                    </span>

                    <b>→</b>

                </button>

            </div>

        </section>

    </main>

</div>


<!-- =========================================================
     MODAL GENERAR AGENDA
========================================================= -->

<div
    id="modalGenerarAgenda"
    class="modal-generar-agenda"
    aria-hidden="true"
>

    <div
        class="contenido-modal-generar-agenda"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloGenerarAgenda"
    >

        <div class="encabezado-generar-agenda">

            <div>

                <h2 id="tituloGenerarAgenda">
                    Generar agenda
                </h2>

                <p>
                    Configura los segmentos y cupos del profesional seleccionado.
                </p>

            </div>

            <button
                type="button"
                id="cerrarModalGenerarAgenda"
                class="cerrar-modal-generar-agenda"
                aria-label="Cerrar"
            >
                &times;
            </button>

        </div>


        <form id="formGenerarAgenda" autocomplete="off">

            <!-- DATOS INTERNOS -->

            <input
                type="hidden"
                id="idProfesionalAgenda"
                name="id_profesional"
            >

            <input
                type="hidden"
                id="horaReferenciaAgenda"
                name="hora_referencia"
            >


            <!-- PROFESIONAL -->

            <div class="campo-generar-agenda">

                <label for="nombreProfesionalAgenda">
                    Profesional seleccionado
                </label>

                <input
                    type="text"
                    id="nombreProfesionalAgenda"
                    readonly
                    placeholder="Selecciona un profesional en la agenda"
                >

            </div>


            <!-- TIPO DE ATENCIÓN -->

            <div class="campo-generar-agenda">

                <label for="tipoAtencionAgenda">
                    Tipo de atención
                </label>

                <select
                    id="tipoAtencionAgenda"
                    name="id_departamento"
                    required
                >

                    <option value="">
                        Selecciona el tipo de atención
                    </option>

                    <?php foreach ($departamentosAgenda as $departamento): ?>

                        <option
                            value="<?= (int) $departamento['id_departamento'] ?>"
                            <?= (
                                $departamentoSeleccionado !== '' &&
                                (int) $departamento['id_departamento'] ===
                                (int) $departamentoSeleccionado
                            ) ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $departamento['nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- SERVICIO -->

            <div class="campo-generar-agenda">

                <label for="servicioAgenda">
                    Servicio
                </label>

                <select
                    id="servicioAgenda"
                    name="id_servicio"
                    required
                    disabled
                >

                    <option value="">
                        Selecciona primero un tipo de atención
                    </option>

                    <?php foreach ($serviciosAgenda as $servicio): ?>

                        <option
                            value="<?= (int) $servicio['id_servicio'] ?>"
                            data-departamento="<?= (int) $servicio['id_departamento'] ?>"
                            hidden
                        >

                            <?= htmlspecialchars(
                                $servicio['nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <small>
                    Los servicios disponibles dependen del tipo de atención seleccionado.
                </small>

            </div>


            <!-- INSTRUMENTO -->

            <div class="campo-generar-agenda">

                <label for="instrumentoAgenda">
                    Instrumento del profesional
                </label>

                <input
                    type="text"
                    id="instrumentoAgenda"
                    readonly
                    placeholder="Se completa automáticamente"
                >

                <small>
                    Corresponde al estamento registrado para el profesional.
                    Este dato no se puede modificar desde la agenda.
                </small>

            </div>


            <!-- SECTOR -->

            <div class="campo-generar-agenda">

                <label for="sectorAgenda">
                    Sector
                </label>

                <input
                    type="text"
                    id="sectorAgenda"
                    name="sector"
                    value="No informado"
                    maxlength="100"
                    placeholder="Ingresa el sector"
                >

            </div>


            <!-- DÍA -->

            <div class="campo-generar-agenda">

                <label for="diaAgenda">
                    Día de la agenda
                </label>

                <input
                    type="date"
                    id="diaAgenda"
                    name="fecha"
                    min="<?= htmlspecialchars($fechaHoy, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >

            </div>


            <!-- DURACIÓN -->

            <div class="campo-generar-agenda">

                <label for="duracionAgenda">
                    Duración de cada cupo
                </label>

                <select
                    id="duracionAgenda"
                    name="duracion_minutos"
                    required
                >

                    <option value="10">10 minutos</option>
                    <option value="15">15 minutos</option>
                    <option value="20" selected>20 minutos</option>
                    <option value="25">25 minutos</option>
                    <option value="30">30 minutos</option>
                    <option value="35">35 minutos</option>
                    <option value="40">40 minutos</option>
                    <option value="45">45 minutos</option>
                    <option value="50">50 minutos</option>
                    <option value="55">55 minutos</option>
                    <option value="60">60 minutos</option>
                    <option value="75">75 minutos</option>
                    <option value="90">90 minutos</option>
                    <option value="120">120 minutos</option>
                    <option value="180">180 minutos</option>

                </select>

            </div>


            <!-- INICIO DIFERENCIADO -->

            <div class="opcion-generar-agenda">

                <label>

                    <input
                        type="checkbox"
                        id="inicioDiferenciado"
                        name="inicio_diferenciado"
                        value="1"
                    >

                    <span>
                        Inicio diferenciado
                    </span>

                </label>

                <p>
                    Permite establecer manualmente la hora de inicio
                    y finalización del segmento.
                </p>

            </div>


            <!-- HORARIO DIFERENCIADO -->

            <div
                id="horarioDiferenciadoCampos"
                class="horario-diferenciado-campos"
                hidden
            >

                <div class="campo-generar-agenda">

                    <label for="horaInicioAgenda">
                        Hora de inicio
                    </label>

                    <input
                        type="time"
                        id="horaInicioAgenda"
                        name="hora_inicio"
                    >

                </div>

                <div class="campo-generar-agenda">

                    <label for="horaFinAgenda">
                        Hora de finalización
                    </label>

                    <input
                        type="time"
                        id="horaFinAgenda"
                        name="hora_fin"
                    >

                </div>

                <div class="aviso-horario-diferenciado">

                    La duración seleccionada se aplicará a cada cupo.
                    El último podrá tener una duración menor para
                    ajustarse a la hora de finalización.

                </div>

            </div>


            <!-- SEGMENTO HABILITADO -->

            <div class="opcion-generar-agenda">

                <label>

                    <input
                        type="checkbox"
                        id="segmentoHabilitado"
                        name="segmento_habilitado"
                        value="1"
                        checked
                    >

                    <span>
                        Segmento habilitado
                    </span>

                </label>

                <p>
                    Los cupos de este segmento podrán quedar habilitados
                    o deshabilitados para las reservas.
                </p>

            </div>


            <!-- ORIGEN MULTIPROFESIONAL -->

            <div class="opcion-generar-agenda">

                <label>

                    <input
                        type="checkbox"
                        id="origenMultiprofesional"
                        name="origen_multiprofesional"
                        value="1"
                    >

                    <span>
                        Origen multiprofesional
                    </span>

                </label>

                <p>
                    Indica si la atención requiere la participación
                    simultánea de más de un profesional.
                </p>

            </div>


            <!-- VISTA PREVIA -->

            <div
                id="resultadoVistaPreviaAgenda"
                class="resultado-vista-previa-agenda"
                aria-live="polite"
            ></div>


            <!-- ACCIONES -->

            <div class="acciones-generar-agenda">

                <button
                    type="button"
                    id="btnPrevisualizarAgenda"
                >
                    Previsualizar cupos
                </button>

                <button
                    type="button"
                    id="btnGuardarAgenda"
                    disabled
                >
                    Guardar agenda
                </button>

                <button
                    type="button"
                    id="btnCancelarGenerarAgenda"
                >
                    Cancelar
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     MODAL EDITAR CUPO
========================================================= -->

<div
    id="modalEditarHorario"
    class="modal-generar-agenda"
    aria-hidden="true"
>

    <div
        class="contenido-modal-generar-agenda"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloEditarHorario"
    >

        <div class="encabezado-generar-agenda">

            <div>

                <h2 id="tituloEditarHorario">
                    Editar cupo
                </h2>

                <p>
                    Modifica los datos del cupo seleccionado.
                </p>

            </div>

            <button
                type="button"
                id="cerrarModalEditarHorario"
                class="cerrar-modal-generar-agenda"
                aria-label="Cerrar"
            >
                &times;
            </button>

        </div>


        <form id="formEditarHorario" autocomplete="off">

            <input
                type="hidden"
                id="editarIdHorario"
                name="id_horario"
            >


            <div class="campo-generar-agenda">

                <label for="editarServicioHorario">
                    Servicio
                </label>

                <input
                    type="text"
                    id="editarServicioHorario"
                    readonly
                >

            </div>


            <div class="campo-generar-agenda">

                <label for="editarFechaHorario">
                    Fecha
                </label>

                <input
                    type="date"
                    id="editarFechaHorario"
                    readonly
                >

            </div>


            <div class="campo-generar-agenda">

                <label for="editarHoraHorario">
                    Hora de inicio
                </label>

                <input
                    type="time"
                    id="editarHoraHorario"
                    name="hora"
                    required
                >

            </div>


            <div class="campo-generar-agenda">

                <label for="editarHoraFinHorario">
                    Hora de finalización
                </label>

                <input
                    type="time"
                    id="editarHoraFinHorario"
                    name="hora_fin"
                >

            </div>


            <div class="campo-generar-agenda">

                <label for="editarEstadoHorario">
                    Estado del cupo
                </label>

                <select
                    id="editarEstadoHorario"
                    name="estado"
                    required
                >

                    <option value="disponible">
                        Disponible
                    </option>

                    <option value="bloqueado">
                        Bloqueado
                    </option>

                </select>

            </div>


            <div class="campo-generar-agenda">

                <label for="editarObservacionHorario">
                    Observación
                </label>

                <textarea
                    id="editarObservacionHorario"
                    name="observacion"
                    rows="3"
                    maxlength="500"
                    placeholder="Motivo del bloqueo u observación..."
                ></textarea>

            </div>


            <div class="opcion-generar-agenda">

                <label>

                    <input
                        type="checkbox"
                        id="editarHabilitadoHorario"
                        name="habilitado"
                        value="1"
                    >

                    <span>
                        Cupo habilitado
                    </span>

                </label>

            </div>


            <div class="opcion-generar-agenda">

                <label>

                    <input
                        type="checkbox"
                        id="editarDisponibleWebHorario"
                        name="disponible_web"
                        value="1"
                    >

                    <span>
                        Disponible para reservas web
                    </span>

                </label>

            </div>


            <div class="acciones-generar-agenda">

                <button
                    type="submit"
                    id="btnGuardarEdicionHorario"
                >
                    Guardar cambios
                </button>

                <button
                    type="button"
                    id="btnCancelarEdicionHorario"
                >
                    Cancelar
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     MODAL RESUMEN DEL DÍA
========================================================= -->

<div
    class="agenda-modal-overlay"
    id="agendaModalOverlay"
    aria-hidden="true"
>

    <div
        class="agenda-modal"
        id="agendaModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="agendaModalTitle"
    >

        <div class="agenda-modal-header">

            <div>

                <span class="agenda-modal-kicker">
                    Resumen de agenda
                </span>

                <h2 id="agendaModalTitle">
                    Resumen del día
                </h2>

                <p id="agendaModalFecha">
                    Cargando fecha...
                </p>

            </div>

            <button
                type="button"
                class="agenda-modal-close"
                id="agendaModalClose"
                aria-label="Cerrar"
            >
                ×
            </button>

        </div>


        <div
            class="agenda-modal-loading"
            id="agendaModalLoading"
        >

            <span class="agenda-modal-spinner"></span>

            <span>
                Consultando reservas...
            </span>

        </div>


        <div
            class="agenda-modal-content"
            id="agendaModalContent"
        >

            <div class="agenda-modal-summary">

                <div class="agenda-modal-summary-card">

                    <span class="agenda-modal-summary-icon">
                        📅
                    </span>

                    <div>

                        <strong id="agendaModalTotal">
                            0
                        </strong>

                        <span>
                            Reservas agendadas
                        </span>

                    </div>

                </div>

            </div>


            <div class="agenda-modal-reservas-header">

                <h3>
                    Reservas del día
                </h3>

                <span id="agendaModalServicio">
                    Todos los servicios
                </span>

            </div>


            <div
                class="agenda-modal-reservas"
                id="agendaModalReservas"
            ></div>

        </div>


        <div
            class="agenda-modal-error"
            id="agendaModalError"
        ></div>


        <div class="agenda-modal-footer">

            <button
                type="button"
                class="agenda-modal-button secondary"
                id="agendaModalCancel"
            >
                Cerrar
            </button>

            <button
                type="button"
                class="agenda-modal-button primary"
                id="agendaModalVerAgenda"
            >
                Ver agenda del día
            </button>

        </div>

    </div>

</div>


<!-- =========================================================
     FORMULARIO EXISTENTE PARA CREAR RESERVAS
========================================================= -->

<?php

$servicios = $serviciosAgenda;

include '../componentes/formulario_crear_reserva.php';

?>


<!-- =========================================================
     FOOTER
========================================================= -->

<?php require_once '../componentes/footer_funcionario.php'; ?>


</body>
</html>
