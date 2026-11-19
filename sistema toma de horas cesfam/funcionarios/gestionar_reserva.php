
<?php

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';
require_once '../componentes/correos_reservas.php';

/* =========================================================
   CONFIGURACIÓN GENERAL
========================================================= */

date_default_timezone_set('America/Santiago');

$base_url = '/sistema toma de horas cesfam';
$pagina_actual = 'reservas';


/* =========================================================
   DATOS DEL FUNCIONARIO
========================================================= */

$nombreFuncionario = $_SESSION['nombre_funcionario'] ?? '';
$apellidoFuncionario = $_SESSION['apellido_funcionario'] ?? '';
$usuarioFuncionario = $_SESSION['usuario_funcionario'] ?? '';

$rolFuncionario = strtolower(
    trim((string) ($_SESSION['rol_funcionario'] ?? ''))
);

$nombreCompleto = trim(
    $nombreFuncionario . ' ' . $apellidoFuncionario
);

if ($nombreCompleto === '') {
    $nombreCompleto = 'Funcionario';
}

if ($rolFuncionario === 'superadmin') {
    $rolMostrar = 'Superadministrador';
} elseif (
    $rolFuncionario === 'administrador' ||
    $rolFuncionario === 'admin'
) {
    $rolMostrar = 'Administrador';
} else {
    $rolMostrar = 'Funcionario';
}

$nombreMostrar = htmlspecialchars(
    $nombreCompleto,
    ENT_QUOTES,
    'UTF-8'
);

$usuarioMostrar = htmlspecialchars(
    $usuarioFuncionario,
    ENT_QUOTES,
    'UTF-8'
);

$rolMostrar = htmlspecialchars(
    $rolMostrar,
    ENT_QUOTES,
    'UTF-8'
);


/* =========================================================
   VARIABLES GENERALES
========================================================= */

$nombre_funcionario = $nombreCompleto;

$mensaje = '';
$tipoMensaje = '';

$reservas = [];
$solicitudes = [];
$servicios = [];

$reservaSeleccionada = null;


/* =========================================================
   FILTROS DE RESERVAS
========================================================= */

$filtroServicio = trim(
    (string) ($_GET['servicio'] ?? '')
);

$filtroFecha = trim(
    (string) ($_GET['fecha'] ?? '')
);

$filtroEstado = trim(
    (string) ($_GET['estado'] ?? '')
);

$filtroBusqueda = trim(
    (string) ($_GET['busqueda'] ?? '')
);


/* =========================================================
   FILTROS DE SOLICITUDES
========================================================= */

$filtroSolicitudServicio = trim(
    (string) ($_GET['solicitud_servicio'] ?? '')
);

$filtroSolicitudFecha = trim(
    (string) ($_GET['solicitud_fecha'] ?? '')
);

$filtroSolicitudEstado = trim(
    (string) ($_GET['solicitud_estado'] ?? '')
);

$filtroSolicitudBusqueda = trim(
    (string) ($_GET['solicitud_busqueda'] ?? '')
);


/* =========================================================
   CONTADORES
========================================================= */

$totalReservas = 0;
$totalPendientesConfirmacion = 0;
$totalConfirmadas = 0;
$totalReagendadas = 0;
$totalCanceladas = 0;

$totalSolicitudes = 0;
$totalSolicitudesPendientes = 0;
$totalSolicitudesAtendidas = 0;
$totalSolicitudesRechazadas = 0;
$totalSolicitudesVencidas = 0;


/* =========================================================
   FUNCIONES AUXILIARES
========================================================= */

function limpiarTexto($valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatoFecha($fecha): string
{
    if (!$fecha) {
        return '';
    }

    $fechaObjeto = DateTime::createFromFormat(
        'Y-m-d',
        substr((string) $fecha, 0, 10)
    );

    if (!$fechaObjeto) {
        return (string) $fecha;
    }

    return $fechaObjeto->format('d/m/Y');
}


function horaCorta($hora): string
{
    if (!$hora) {
        return '';
    }

    return substr((string) $hora, 0, 5);
}


function fechaHoraChile($fecha, $hora): ?DateTime
{
    $fechaHora = substr((string) $fecha, 0, 10)
        . ' '
        . substr((string) $hora, 0, 5);

    return DateTime::createFromFormat(
        'Y-m-d H:i',
        $fechaHora,
        new DateTimeZone('America/Santiago')
    ) ?: null;
}


function estadoSolicitudNormalizado($estado): string
{
    return strtolower(
        trim((string) $estado)
    );
}


function etiquetaEstadoReserva($estado): string
{
    $estado = strtolower(
        trim((string) $estado)
    );

    $etiquetas = [
        'pendiente_confirmacion' => 'Pendiente de confirmación',
        'confirmada' => 'Confirmada',
        'reagendada' => 'Reagendada',
        'cancelada' => 'Cancelada'
    ];

    return $etiquetas[$estado] ?? ucfirst($estado);
}


/* =========================================================
   1. OBTENER SERVICIOS ACTIVOS
========================================================= */

try {

    $sqlServicios = "
        SELECT
            id_servicio,
            id_departamento,
            nombre,
            descripcion

        FROM servicios

        WHERE activo = TRUE

        ORDER BY nombre ASC
    ";

    $stmtServicios = $conexion->query(
        $sqlServicios
    );

    $servicios = $stmtServicios->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    error_log(
        'Error al obtener servicios: ' .
        $e->getMessage()
    );

    $servicios = [];

    $mensaje = 'No fue posible cargar los servicios.';
    $tipoMensaje = 'error';
}


/* =========================================================
   2. VENCER SOLICITUDES CON FECHA EXPIRADA
========================================================= */

/*
 * Solamente se actualizan las solicitudes que:
 *
 * - Tienen estado pendiente.
 * - Tienen una fecha solicitada anterior al día actual.
 *
 * No se modifican solicitudes:
 *
 * - Atendidas.
 * - Rechazadas.
 * - Contactadas.
 * - Cerradas.
 * - Que ya están vencidas.
 *
 * Se utiliza la fecha de Chile para evitar diferencias
 * entre la zona horaria de PHP y la de PostgreSQL.
 *
 * La solicitud no se elimina. Solamente cambia su estado.
 */

try {

    $fechaHoyChile = (new DateTimeImmutable(
        'today',
        new DateTimeZone('America/Santiago')
    ))->format('Y-m-d');


    $sqlVencerSolicitudes = "
        UPDATE solicitudes_horas

        SET estado = 'vencida'

        WHERE LOWER(BTRIM(estado)) = 'pendiente'

          AND fecha_solicitada < CAST(:fecha_hoy AS DATE)
    ";

    $stmtVencerSolicitudes = $conexion->prepare(
        $sqlVencerSolicitudes
    );

    $stmtVencerSolicitudes->execute([
        ':fecha_hoy' => $fechaHoyChile
    ]);

} catch (PDOException $e) {

    error_log(
        'Error al actualizar solicitudes vencidas: ' .
        $e->getMessage()
    );

    if ($mensaje === '') {

        $mensaje = 'No fue posible actualizar el estado de algunas solicitudes vencidas.';

        $tipoMensaje = 'error';
    }
}


/* =========================================================
   3. OBTENER RESERVAS
========================================================= */

try {

    $sqlReservas = "
        SELECT
            r.id_reserva,
            r.id_horario,
            r.id_servicio,
            r.nombre,
            r.rut,
            r.correo,
            r.telefono,
            r.estado,
            r.fecha_reserva,
            r.fecha_actualizacion,
            r.tipo_identificacion,

            s.nombre AS servicio_nombre,

            h.fecha AS fecha_horario,
            h.hora AS hora_horario,
            h.estado AS estado_horario,
            h.id_agenda

        FROM reservas r

        INNER JOIN servicios s
            ON s.id_servicio = r.id_servicio

        INNER JOIN horarios h
            ON h.id_horario = r.id_horario

        INNER JOIN agendas a
            ON a.id_agenda = h.id_agenda
           AND a.id_servicio = h.id_servicio
           AND a.fecha = h.fecha

        WHERE h.id_agenda IS NOT NULL
    ";

    $parametros = [];


    /* FILTRO POR SERVICIO */

    if ($filtroServicio !== '') {

        $sqlReservas .= "
            AND r.id_servicio = :filtro_servicio
        ";

        $parametros[':filtro_servicio'] =
            (int) $filtroServicio;
    }


    /* FILTRO POR FECHA */

    if ($filtroFecha !== '') {

        $sqlReservas .= "
            AND h.fecha::DATE = :filtro_fecha
        ";

        $parametros[':filtro_fecha'] =
            $filtroFecha;
    }


    /* FILTRO POR ESTADO */

    if ($filtroEstado !== '') {

        $sqlReservas .= "
            AND LOWER(r.estado) = LOWER(:filtro_estado)
        ";

        $parametros[':filtro_estado'] =
            $filtroEstado;
    }


    /* BUSCADOR */

    if ($filtroBusqueda !== '') {

        $sqlReservas .= "
            AND (
                LOWER(r.nombre)
                    LIKE LOWER(:busqueda_nombre)

                OR LOWER(r.rut)
                    LIKE LOWER(:busqueda_rut)

                OR LOWER(COALESCE(r.correo, ''))
                    LIKE LOWER(:busqueda_correo)

                OR COALESCE(r.telefono, '')
                    LIKE :busqueda_telefono
            )
        ";

        $busqueda = '%' . $filtroBusqueda . '%';

        $parametros[':busqueda_nombre'] = $busqueda;
        $parametros[':busqueda_rut'] = $busqueda;
        $parametros[':busqueda_correo'] = $busqueda;
        $parametros[':busqueda_telefono'] = $busqueda;
    }


    /* ORDENAMIENTO */

    $sqlReservas .= "
        ORDER BY
            CASE
                WHEN LOWER(r.estado) = 'pendiente_confirmacion'
                THEN 0

                ELSE 1
            END ASC,

            h.fecha::DATE ASC,
            h.hora::TIME ASC,
            r.id_reserva ASC
    ";

    $stmtReservas = $conexion->prepare(
        $sqlReservas
    );

    $stmtReservas->execute(
        $parametros
    );

    $reservas = $stmtReservas->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    error_log(
        'Error al obtener reservas: ' .
        $e->getMessage()
    );

    $reservas = [];

    if ($mensaje === '') {
        $mensaje = 'No fue posible cargar las reservas.';
        $tipoMensaje = 'error';
    }
}


/* =========================================================
   4. OBTENER SOLICITUDES DE HORAS
========================================================= */

try {

    $sqlSolicitudes = "
        SELECT
            sh.id_solicitud,
            sh.id_departamento,
            sh.id_servicio,
            sh.fecha_solicitada,
            sh.rut,
            sh.nombre,
            sh.apellido,
            sh.telefono,
            sh.correo,
            sh.estado,

            s.nombre AS servicio_nombre,

            d.nombre AS departamento_nombre

        FROM solicitudes_horas sh

        LEFT JOIN servicios s
            ON s.id_servicio = sh.id_servicio

        LEFT JOIN departamentos d
            ON d.id_departamento = sh.id_departamento

        WHERE 1 = 1
    ";

    $parametrosSolicitudes = [];


    /* FILTRO POR SERVICIO */

    if ($filtroSolicitudServicio !== '') {

        $sqlSolicitudes .= "
            AND sh.id_servicio = :solicitud_servicio
        ";

        $parametrosSolicitudes[':solicitud_servicio'] =
            (int) $filtroSolicitudServicio;
    }


    /* FILTRO POR FECHA */

    if ($filtroSolicitudFecha !== '') {

        $sqlSolicitudes .= "
            AND sh.fecha_solicitada::DATE = :solicitud_fecha
        ";

        $parametrosSolicitudes[':solicitud_fecha'] =
            $filtroSolicitudFecha;
    }


    /* FILTRO POR ESTADO */

    if ($filtroSolicitudEstado !== '') {

        $sqlSolicitudes .= "
            AND LOWER(sh.estado) = LOWER(:solicitud_estado)
        ";

        $parametrosSolicitudes[':solicitud_estado'] =
            $filtroSolicitudEstado;
    }


    /* BUSCADOR */

    if ($filtroSolicitudBusqueda !== '') {

        $sqlSolicitudes .= "
            AND (
                LOWER(
                    CONCAT_WS(
                        ' ',
                        sh.nombre,
                        sh.apellido
                    )
                ) LIKE LOWER(:solicitud_nombre)

                OR LOWER(sh.rut)
                    LIKE LOWER(:solicitud_rut)

                OR LOWER(COALESCE(sh.correo, ''))
                    LIKE LOWER(:solicitud_correo)

                OR COALESCE(sh.telefono, '')
                    LIKE :solicitud_telefono
            )
        ";

        $busquedaSolicitud =
            '%' . $filtroSolicitudBusqueda . '%';

        $parametrosSolicitudes[':solicitud_nombre'] =
            $busquedaSolicitud;

        $parametrosSolicitudes[':solicitud_rut'] =
            $busquedaSolicitud;

        $parametrosSolicitudes[':solicitud_correo'] =
            $busquedaSolicitud;

        $parametrosSolicitudes[':solicitud_telefono'] =
            $busquedaSolicitud;
    }


    /* ORDENAMIENTO */

    $sqlSolicitudes .= "
        ORDER BY
            CASE
                WHEN LOWER(BTRIM(sh.estado)) = 'pendiente'
                THEN 0

                WHEN LOWER(BTRIM(sh.estado)) = 'contactada'
                THEN 1

                WHEN LOWER(BTRIM(sh.estado)) = 'atendida'
                THEN 2

                WHEN LOWER(BTRIM(sh.estado)) = 'cerrada'
                THEN 3

                WHEN LOWER(BTRIM(sh.estado)) = 'rechazada'
                THEN 4

                WHEN LOWER(BTRIM(sh.estado)) = 'vencida'
                THEN 5

                ELSE 6
            END ASC,

            sh.fecha_solicitada ASC,
            sh.id_solicitud DESC
    ";

    $stmtSolicitudes = $conexion->prepare(
        $sqlSolicitudes
    );

    $stmtSolicitudes->execute(
        $parametrosSolicitudes
    );

    $solicitudes = $stmtSolicitudes->fetchAll(
        PDO::FETCH_ASSOC
    );


    /* CONTADORES DE SOLICITUDES */

    $totalSolicitudes = count($solicitudes);

    foreach ($solicitudes as $solicitud) {

        $estadoSolicitud = estadoSolicitudNormalizado(
            $solicitud['estado']
        );

        if ($estadoSolicitud === 'pendiente') {

            $totalSolicitudesPendientes++;

        } elseif ($estadoSolicitud === 'atendida') {

            $totalSolicitudesAtendidas++;

        } elseif ($estadoSolicitud === 'rechazada') {

            $totalSolicitudesRechazadas++;

        } elseif ($estadoSolicitud === 'vencida') {

            $totalSolicitudesVencidas++;
        }
    }

} catch (PDOException $e) {

    error_log(
        'Error al obtener solicitudes de horas: ' .
        $e->getMessage()
    );

    $solicitudes = [];

    if ($mensaje === '') {
        $mensaje = 'No fue posible cargar las solicitudes de horas.';
        $tipoMensaje = 'error';
    }
}


/* =========================================================
   5. CONTADORES DE RESERVAS
========================================================= */

$totalReservas = count($reservas);

foreach ($reservas as $reserva) {

    switch (
        strtolower(
            trim((string) $reserva['estado'])
        )
    ) {

        case 'pendiente_confirmacion':
            $totalPendientesConfirmacion++;
            break;

        case 'confirmada':
            $totalConfirmadas++;
            break;

        case 'reagendada':
            $totalReagendadas++;
            break;

        case 'cancelada':
            $totalCanceladas++;
            break;
    }
}


/* =========================================================
   6. RESERVA SELECCIONADA
========================================================= */

if (isset($_GET['id_reserva'])) {

    $idReservaSeleccionada = (int) $_GET['id_reserva'];

    if ($idReservaSeleccionada > 0) {

        foreach ($reservas as $reserva) {

            if (
                (int) $reserva['id_reserva'] ===
                $idReservaSeleccionada
            ) {

                $reservaSeleccionada = $reserva;
                break;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Gestionar reservas | CESFAM Eduardo Raggio Catemu
    </title>


    <!-- ESTILOS GENERALES -->

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
        href="../css/funcionarios/reservas.css"
    >

    <link
        rel="stylesheet"
        href="../css/funcionarios/formulario_crear_reserva.css"
    >


    <!-- JAVASCRIPT EXTERNO -->

    <script
        src="../js/gestionar_reserva.js"
        defer
    ></script>

</head>


<body>


<?php
require_once '../componentes/navbar_funcionario.php';
?>


<div class="admin-layout">


    <?php
    require_once '../componentes/sidebar_funcionario.php';
    ?>


    <main class="admin-content">


        <!-- =================================================
             ENCABEZADO
        ================================================= -->

        <section class="reservation-header">

            <div>

                <div class="page-kicker">
                    ADMINISTRACIÓN CESFAM
                </div>

                <h1>
                    Gestionar reservas
                </h1>

                <p>
                    Administra las reservas y solicitudes de horas
                    registradas en el sistema.
                </p>

            </div>

        </section>


        <!-- =================================================
             MENSAJES
        ================================================= -->

        <?php if ($mensaje !== ''): ?>

            <div
                class="<?= $tipoMensaje === 'success'
                    ? 'alert alert-success'
                    : 'alert alert-error'
                ?>"
            >

                <?= limpiarTexto($mensaje) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FILTROS DE RESERVAS
        ================================================= -->

        <form
            method="GET"
            class="reservation-filters"
        >

            <div class="filter-group">

                <label for="busqueda">
                    Buscar reserva
                </label>

                <input
                    type="text"
                    id="busqueda"
                    name="busqueda"
                    value="<?= limpiarTexto($filtroBusqueda) ?>"
                    placeholder="Nombre, RUT, correo..."
                >

            </div>


            <div class="filter-group">

                <label for="servicio">
                    Servicio
                </label>

                <select
                    id="servicio"
                    name="servicio"
                >

                    <option value="">
                        Todos los servicios
                    </option>

                    <?php foreach ($servicios as $servicio): ?>

                        <option
                            value="<?= (int) $servicio['id_servicio'] ?>"
                            <?= (string) $filtroServicio ===
                                (string) $servicio['id_servicio']
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= limpiarTexto($servicio['nombre']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-group">

                <label for="fecha">
                    Fecha
                </label>

                <input
                    type="date"
                    id="fecha"
                    name="fecha"
                    value="<?= limpiarTexto($filtroFecha) ?>"
                >

            </div>


            <div class="filter-group">

                <label for="estado">
                    Estado
                </label>

                <select
                    id="estado"
                    name="estado"
                >

                    <option value="">
                        Todos
                    </option>

                    <option
                        value="pendiente_confirmacion"
                        <?= $filtroEstado === 'pendiente_confirmacion'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Pendientes de confirmación
                    </option>

                    <option
                        value="confirmada"
                        <?= $filtroEstado === 'confirmada'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Confirmadas
                    </option>

                    <option
                        value="reagendada"
                        <?= $filtroEstado === 'reagendada'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Reagendadas
                    </option>

                    <option
                        value="cancelada"
                        <?= $filtroEstado === 'cancelada'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Canceladas
                    </option>

                </select>

            </div>


            <div class="filter-actions">

                <button
                    type="submit"
                    class="filter-button"
                >
                    Filtrar
                </button>

                <a
                    href="gestionar_reserva.php"
                    class="clear-filter-button"
                >
                    Limpiar
                </a>

            </div>

        </form>


        <!-- =================================================
             RESUMEN DE RESERVAS
        ================================================= -->

        <section class="reservation-summary">

            <div class="summary-card">

                <div class="summary-card-icon total">
                    📋
                </div>

                <div>

                    <span>Total</span>

                    <strong>
                        <?= $totalReservas ?>
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-card-icon total">
                    ◷
                </div>

                <div>

                    <span>Pendientes de confirmación</span>

                    <strong>
                        <?= $totalPendientesConfirmacion ?>
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-card-icon confirmed">
                    ✓
                </div>

                <div>

                    <span>Confirmadas</span>

                    <strong>
                        <?= $totalConfirmadas ?>
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-card-icon rescheduled">
                    ↻
                </div>

                <div>

                    <span>Reagendadas</span>

                    <strong>
                        <?= $totalReagendadas ?>
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-card-icon cancelled">
                    ✕
                </div>

                <div>

                    <span>Canceladas</span>

                    <strong>
                        <?= $totalCanceladas ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- =================================================
             TABLA DE RESERVAS
        ================================================= -->

        <section class="reservation-container">

            <div class="reservation-container-header">

                <div>

                    <h2>
                        Reservas
                    </h2>

                    <p>
                        Reservas vinculadas a horarios de agendas
                        registradas en el sistema.
                    </p>

                </div>

                <span class="reservation-date">
                    <?= date('d/m/Y') ?>
                </span>

            </div>


            <?php if (count($reservas) > 0): ?>

                <div class="reservation-table-wrapper">

                    <table class="reservation-table">

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Servicio</th>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($reservas as $reserva): ?>

                                <?php

                                $fechaHoraReserva = fechaHoraChile(
                                    $reserva['fecha_horario'],
                                    $reserva['hora_horario']
                                );

                                $ahoraChile = new DateTime(
                                    'now',
                                    new DateTimeZone('America/Santiago')
                                );

                                $esReservaPasada = (
                                    $fechaHoraReserva !== null &&
                                    $fechaHoraReserva < $ahoraChile
                                );

                                $estadoReserva = strtolower(
                                    trim((string) $reserva['estado'])
                                );

                                $claseEstado = 'unknown';

                                if ($estadoReserva === 'pendiente_confirmacion') {
                                    $claseEstado = 'pending';
                                } elseif ($estadoReserva === 'confirmada') {
                                    $claseEstado = 'confirmed';
                                } elseif ($estadoReserva === 'cancelada') {
                                    $claseEstado = 'cancelled';
                                } elseif ($estadoReserva === 'reagendada') {
                                    $claseEstado = 'rescheduled';
                                }

                                ?>

                                <tr>

                                    <td>

                                        <span class="reservation-id">
                                            #<?= (int) $reserva['id_reserva'] ?>
                                        </span>

                                    </td>


                                    <td>

                                        <div class="user-cell">

                                            <div class="user-avatar">

                                                <?= limpiarTexto(
                                                    strtoupper(
                                                        substr(
                                                            (string) $reserva['nombre'],
                                                            0,
                                                            1
                                                        )
                                                    )
                                                ) ?>

                                            </div>

                                            <div>

                                                <strong>
                                                    <?= limpiarTexto($reserva['nombre']) ?>
                                                </strong>

                                                <span>
                                                    <?= limpiarTexto($reserva['rut']) ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    <td>
                                        <?= limpiarTexto(
                                            $reserva['servicio_nombre']
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= formatoFecha(
                                            $reserva['fecha_horario']
                                        ) ?>
                                    </td>


                                    <td>

                                        <span class="reservation-time">
                                            <?= limpiarTexto(
                                                horaCorta(
                                                    $reserva['hora_horario']
                                                )
                                            ) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <span class="status <?= limpiarTexto($claseEstado) ?>">

                                            <?= limpiarTexto(
                                                etiquetaEstadoReserva($estadoReserva)
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <div class="reservation-actions">


                                            <!-- VER RESERVA -->

                                            <button
                                                type="button"
                                                class="action-button view"
                                                title="Ver reserva"
                                                data-reserva-view="1"
                                                data-id-reserva="<?= (int) $reserva['id_reserva'] ?>"
                                                data-nombre="<?= limpiarTexto($reserva['nombre']) ?>"
                                                data-rut="<?= limpiarTexto($reserva['rut']) ?>"
                                                data-tipo-identificacion="<?= limpiarTexto($reserva['tipo_identificacion']) ?>"
                                                data-correo="<?= limpiarTexto($reserva['correo'] ?: 'No registrado') ?>"
                                                data-telefono="<?= limpiarTexto($reserva['telefono'] ?: 'No registrado') ?>"
                                                data-servicio="<?= limpiarTexto($reserva['servicio_nombre']) ?>"
                                                data-fecha="<?= limpiarTexto($reserva['fecha_horario']) ?>"
                                                data-hora="<?= limpiarTexto(horaCorta($reserva['hora_horario'])) ?>"
                                                data-estado="<?= limpiarTexto($estadoReserva) ?>"
                                            >
                                                Ver
                                            </button>


                                            <?php if ($estadoReserva !== 'cancelada'): ?>


                                                <!-- CONFIRMAR -->

                                                <?php if ($estadoReserva === 'pendiente_confirmacion'): ?>

                                                    <button
                                                        type="button"
                                                        class="action-button"
                                                        data-reserva-accion="confirmar"
                                                        data-id-reserva="<?= (int) $reserva['id_reserva'] ?>"
                                                    >
                                                        Confirmar hora
                                                    </button>

                                                <?php endif; ?>


                                                <!-- MODIFICAR -->

                                                <button
                                                    type="button"
                                                    class="action-button reschedule"
                                                    title="Modificar reserva"
                                                    data-action="modificar"
                                                    data-id-reserva="<?= (int) $reserva['id_reserva'] ?>"
                                                    data-id-servicio="<?= (int) $reserva['id_servicio'] ?>"
                                                    data-nombre="<?= limpiarTexto($reserva['nombre']) ?>"
                                                    data-rut="<?= limpiarTexto($reserva['rut']) ?>"
                                                    data-tipo-identificacion="<?= limpiarTexto($reserva['tipo_identificacion']) ?>"
                                                    data-telefono="<?= limpiarTexto($reserva['telefono'] ?: 'No registrado') ?>"
                                                    data-correo="<?= limpiarTexto($reserva['correo'] ?: 'No registrado') ?>"
                                                    data-servicio-nombre="<?= limpiarTexto($reserva['servicio_nombre']) ?>"
                                                    data-fecha="<?= limpiarTexto($reserva['fecha_horario']) ?>"
                                                    data-hora="<?= limpiarTexto(horaCorta($reserva['hora_horario'])) ?>"
                                                    data-estado="<?= limpiarTexto($estadoReserva) ?>"
                                                    data-es-pasada="<?= $esReservaPasada ? '1' : '0' ?>"
                                                >
                                                    Modificar
                                                </button>


                                                <!-- CANCELAR -->

                                                <button
                                                    type="button"
                                                    class="action-button cancel"
                                                    data-reserva-accion="cancelar"
                                                    data-id-reserva="<?= (int) $reserva['id_reserva'] ?>"
                                                >
                                                    Cancelar
                                                </button>


                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


            <?php else: ?>

                <div class="empty-reservations">

                    <div class="empty-icon">
                        📋
                    </div>

                    <strong>
                        No hay reservas
                    </strong>

                    <span>
                        No se encontraron reservas con los filtros seleccionados.
                    </span>

                </div>

            <?php endif; ?>

        </section>


        <!-- =================================================
             SOLICITUDES DE HORAS
        ================================================= -->

        <section class="reservation-container request-section">

            <div class="reservation-container-header">

                <div>

                    <h2>
                        Solicitudes de horas
                    </h2>

                    <p>
                        Pacientes que solicitaron una hora al no
                        encontrar disponibilidad en el servicio.
                    </p>

                </div>

                <div class="request-header-counters">

                    <span class="reservation-date">
                        Total: <?= $totalSolicitudes ?>
                    </span>

                    <span class="reservation-date">
                        Pendientes: <?= $totalSolicitudesPendientes ?>
                    </span>

                    <span class="reservation-date">
                        Vencidas: <?= $totalSolicitudesVencidas ?>
                    </span>

                </div>

            </div>


            <!-- RESUMEN DE SOLICITUDES -->

            <div class="request-summary">

                <div class="request-summary-card">

                    <span>
                        Total de solicitudes
                    </span>

                    <strong>
                        <?= $totalSolicitudes ?>
                    </strong>

                </div>


                <div class="request-summary-card">

                    <span>
                        Pendientes
                    </span>

                    <strong>
                        <?= $totalSolicitudesPendientes ?>
                    </strong>

                </div>


                <div class="request-summary-card">

                    <span>
                        Atendidas
                    </span>

                    <strong>
                        <?= $totalSolicitudesAtendidas ?>
                    </strong>

                </div>


                <div class="request-summary-card">

                    <span>
                        Rechazadas
                    </span>

                    <strong>
                        <?= $totalSolicitudesRechazadas ?>
                    </strong>

                </div>


                <div class="request-summary-card">

                    <span>
                        Vencidas
                    </span>

                    <strong>
                        <?= $totalSolicitudesVencidas ?>
                    </strong>

                </div>

            </div>


            <!-- FILTROS DE SOLICITUDES -->

            <form
                method="GET"
                class="reservation-filters"
            >

                <div class="filter-group">

                    <label for="solicitud_busqueda">
                        Buscar paciente
                    </label>

                    <input
                        type="text"
                        id="solicitud_busqueda"
                        name="solicitud_busqueda"
                        value="<?= limpiarTexto($filtroSolicitudBusqueda) ?>"
                        placeholder="Nombre, RUT, correo..."
                    >

                </div>


                <div class="filter-group">

                    <label for="solicitud_servicio">
                        Servicio
                    </label>

                    <select
                        id="solicitud_servicio"
                        name="solicitud_servicio"
                    >

                        <option value="">
                            Todos los servicios
                        </option>

                        <?php foreach ($servicios as $servicio): ?>

                            <option
                                value="<?= (int) $servicio['id_servicio'] ?>"
                                <?= (string) $filtroSolicitudServicio ===
                                    (string) $servicio['id_servicio']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= limpiarTexto($servicio['nombre']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label for="solicitud_fecha">
                        Fecha solicitada
                    </label>

                    <input
                        type="date"
                        id="solicitud_fecha"
                        name="solicitud_fecha"
                        value="<?= limpiarTexto($filtroSolicitudFecha) ?>"
                    >

                </div>


                <div class="filter-group">

                    <label for="solicitud_estado">
                        Estado
                    </label>

                    <select
                        id="solicitud_estado"
                        name="solicitud_estado"
                    >

                        <option value="">
                            Todos
                        </option>

                        <option
                            value="pendiente"
                            <?= $filtroSolicitudEstado === 'pendiente'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Pendientes
                        </option>

                        <option
                            value="contactada"
                            <?= $filtroSolicitudEstado === 'contactada'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Contactadas
                        </option>

                        <option
                            value="atendida"
                            <?= $filtroSolicitudEstado === 'atendida'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Atendidas
                        </option>

                        <option
                            value="rechazada"
                            <?= $filtroSolicitudEstado === 'rechazada'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Rechazadas
                        </option>

                        <option
                            value="cerrada"
                            <?= $filtroSolicitudEstado === 'cerrada'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Cerradas
                        </option>

                        <option
                            value="vencida"
                            <?= $filtroSolicitudEstado === 'vencida'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Vencidas
                        </option>

                    </select>

                </div>


                <div class="filter-actions">

                    <button
                        type="submit"
                        class="filter-button"
                    >
                        Filtrar
                    </button>

                    <a
                        href="gestionar_reserva.php"
                        class="clear-filter-button"
                    >
                        Limpiar
                    </a>

                </div>

            </form>


            <!-- TABLA DE SOLICITUDES -->

            <?php if (count($solicitudes) > 0): ?>

                <div class="reservation-table-wrapper">

                    <table class="reservation-table">

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Paciente</th>
                                <th>Identificación</th>
                                <th>Servicio</th>
                                <th>Fecha solicitada</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($solicitudes as $solicitud): ?>

                                <?php

                                $estadoSolicitud = estadoSolicitudNormalizado(
                                    $solicitud['estado']
                                );

                                $esPendiente = (
                                    $estadoSolicitud === 'pendiente'
                                );

                                $esRechazada = (
                                    $estadoSolicitud === 'rechazada'
                                );

                                $esAtendida = (
                                    $estadoSolicitud === 'atendida'
                                );

                                $esVencida = (
                                    $estadoSolicitud === 'vencida'
                                );

                                $esContactada = (
                                    $estadoSolicitud === 'contactada'
                                );

                                $esCerrada = (
                                    $estadoSolicitud === 'cerrada'
                                );

                                $nombrePacienteSolicitud = trim(
                                    (string) $solicitud['nombre']
                                    . ' '
                                    . (string) $solicitud['apellido']
                                );

                                ?>

                                <tr>

                                    <td>

                                        <span class="reservation-id">
                                            #<?= (int) $solicitud['id_solicitud'] ?>
                                        </span>

                                    </td>


                                    <td>

                                        <div class="user-cell">

                                            <div class="user-avatar">

                                                <?= limpiarTexto(
                                                    strtoupper(
                                                        substr(
                                                            $nombrePacienteSolicitud,
                                                            0,
                                                            1
                                                        )
                                                    )
                                                ) ?>

                                            </div>

                                            <div>

                                                <strong>
                                                    <?= limpiarTexto(
                                                        $nombrePacienteSolicitud
                                                    ) ?>
                                                </strong>

                                            </div>

                                        </div>

                                    </td>


                                    <td>
                                        <?= limpiarTexto($solicitud['rut']) ?>
                                    </td>


                                    <td>
                                        <?= limpiarTexto(
                                            $solicitud['servicio_nombre']
                                            ?? 'Servicio no disponible'
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= formatoFecha(
                                            $solicitud['fecha_solicitada']
                                        ) ?>
                                    </td>


                                    <td>

                                        <div class="request-contact">

                                            <span>
                                                <?= limpiarTexto(
                                                    $solicitud['telefono']
                                                    ?: 'Sin teléfono'
                                                ) ?>
                                            </span>

                                            <small>
                                                <?= limpiarTexto(
                                                    $solicitud['correo']
                                                    ?: 'Sin correo'
                                                ) ?>
                                            </small>

                                        </div>

                                    </td>


                                    <td>

                                        <?php if ($esPendiente): ?>

                                            <span class="request-status request-status-pending">
                                                Pendiente
                                            </span>

                                        <?php elseif ($esAtendida): ?>

                                            <span class="request-status request-status-attended">
                                                Atendida
                                            </span>

                                        <?php elseif ($esRechazada): ?>

                                            <span class="request-status request-status-rejected">
                                                Rechazada
                                            </span>

                                        <?php elseif ($esVencida): ?>

                                            <span class="request-status request-status-expired">
                                                Vencida
                                            </span>

                                        <?php elseif ($esContactada): ?>

                                            <span class="request-status request-status-other">
                                                Contactada
                                            </span>

                                        <?php elseif ($esCerrada): ?>

                                            <span class="request-status request-status-other">
                                                Cerrada
                                            </span>

                                        <?php else: ?>

                                            <span class="request-status request-status-other">
                                                <?= limpiarTexto(
                                                    ucfirst($estadoSolicitud)
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div class="request-actions">

                                            <?php if ($esPendiente): ?>

                                                <button
                                                    type="button"
                                                    class="action-button request-assign-button"
                                                    data-solicitud-asignar="1"
                                                    data-id-solicitud="<?= (int) $solicitud['id_solicitud'] ?>"
                                                    data-id-servicio="<?= (int) $solicitud['id_servicio'] ?>"
                                                    data-id-departamento="<?= (int) $solicitud['id_departamento'] ?>"
                                                    data-nombre="<?= limpiarTexto($solicitud['nombre']) ?>"
                                                    data-apellido="<?= limpiarTexto($solicitud['apellido']) ?>"
                                                    data-rut="<?= limpiarTexto($solicitud['rut']) ?>"
                                                    data-telefono="<?= limpiarTexto($solicitud['telefono']) ?>"
                                                    data-correo="<?= limpiarTexto($solicitud['correo']) ?>"
                                                    data-fecha="<?= limpiarTexto(substr((string) $solicitud['fecha_solicitada'], 0, 10)) ?>"
                                                    data-servicio-nombre="<?= limpiarTexto($solicitud['servicio_nombre']) ?>"
                                                >
                                                    Asignar hora
                                                </button>


                                                <button
                                                    type="button"
                                                    class="action-button request-reject-button"
                                                    data-solicitud-accion="rechazar"
                                                    data-id-solicitud="<?= (int) $solicitud['id_solicitud'] ?>"
                                                >
                                                    Rechazar
                                                </button>

                                            <?php elseif ($esRechazada): ?>

                                                <button
                                                    type="button"
                                                    class="action-button request-reopen-button"
                                                    data-solicitud-accion="reabrir"
                                                    data-id-solicitud="<?= (int) $solicitud['id_solicitud'] ?>"
                                                >
                                                    Reabrir
                                                </button>

                                            <?php else: ?>

                                                <span class="request-no-actions">
                                                    Sin acciones
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-reservations">

                    <div class="empty-icon">
                        📄
                    </div>

                    <strong>
                        No hay solicitudes de horas
                    </strong>

                    <span>
                        No se encontraron solicitudes con los filtros seleccionados.
                    </span>

                </div>

            <?php endif; ?>

        </section>


        <!-- =================================================
             DETALLE DE RESERVA SELECCIONADA
        ================================================= -->

        <?php if ($reservaSeleccionada): ?>

            <section class="reservation-detail">

                <div class="panel-title">

                    <div>

                        <h2>
                            Detalle de la reserva
                        </h2>

                        <p>
                            Información de la reserva seleccionada.
                        </p>

                    </div>

                </div>


                <div class="detail-grid">

                    <div class="detail-item">

                        <span>ID reserva</span>

                        <strong>
                            #<?= (int) $reservaSeleccionada['id_reserva'] ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>Servicio</span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['servicio_nombre']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>Nombre</span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['nombre']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>Identificación</span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['rut']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>Teléfono</span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['telefono']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>Correo</span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['correo']
                                ?: 'No registrado'
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>Fecha</span>

                        <strong>
                            <?= formatoFecha(
                                $reservaSeleccionada['fecha_horario']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>Hora</span>

                        <strong>
                            <?= limpiarTexto(
                                horaCorta(
                                    $reservaSeleccionada['hora_horario']
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>Estado</span>

                        <strong>
                            <?= limpiarTexto(
                                etiquetaEstadoReserva(
                                    $reservaSeleccionada['estado']
                                )
                            ) ?>
                        </strong>

                    </div>

                </div>

            </section>

        <?php endif; ?>


    </main>

</div>


<!-- =========================================================
     POPUP VER RESERVA
========================================================= -->

<div
    id="reservationViewModal"
    class="reservation-view-modal"
    hidden
>

    <div
        class="reservation-view-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="reservationViewTitle"
    >

        <div class="reservation-view-header">

            <div>

                <span class="reservation-view-kicker">
                    DETALLE DE RESERVA
                </span>

                <h2 id="reservationViewTitle">
                    Información de la reserva
                </h2>

            </div>


            <button
                type="button"
                id="btnCloseReservationView"
                class="reservation-view-close"
                aria-label="Cerrar"
            >
                ×
            </button>

        </div>


        <div class="reservation-view-body">

            <div class="reservation-view-grid">

                <div class="reservation-view-item">
                    <span>ID reserva</span>
                    <strong id="viewReservaId">—</strong>
                </div>

                <div class="reservation-view-item">
                    <span>Estado</span>
                    <strong id="viewReservaEstado">—</strong>
                </div>

                <div class="reservation-view-item">
                    <span>Nombre</span>
                    <strong id="viewReservaNombre">—</strong>
                </div>

                <div class="reservation-view-item">
                    <span>Identificación</span>
                    <strong id="viewReservaRut">—</strong>
                </div>

                <div class="reservation-view-item">
                    <span>Teléfono</span>
                    <strong id="viewReservaTelefono">—</strong>
                </div>

                <div class="reservation-view-item">
                    <span>Correo</span>
                    <strong id="viewReservaCorreo">—</strong>
                </div>

                <div class="reservation-view-item">
                    <span>Servicio</span>
                    <strong id="viewReservaServicio">—</strong>
                </div>

                <div class="reservation-view-item">
                    <span>Fecha</span>
                    <strong id="viewReservaFecha">—</strong>
                </div>

                <div class="reservation-view-item">
                    <span>Hora</span>
                    <strong id="viewReservaHora">—</strong>
                </div>

            </div>

        </div>


        <div class="reservation-view-footer">

            <button
                type="button"
                id="btnCloseReservationViewFooter"
                class="secondary-button"
            >
                Cerrar
            </button>

        </div>

    </div>

</div>


<!-- =========================================================
     POPUP MODIFICAR RESERVA
========================================================= -->

<div
    id="reservationModifyModal"
    class="reservation-modal"
    hidden
>

    <div class="reservation-modal-overlay"></div>

    <div
        class="reservation-modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modifyReservationTitle"
    >

        <div class="reservation-modal-header">

            <div>

                <span class="reservation-view-kicker">
                    MODIFICAR RESERVA
                </span>

                <h2 id="modifyReservationTitle">
                    Cambiar fecha y hora
                </h2>

            </div>


            <button
                type="button"
                class="reservation-modal-close"
                data-close-modify-reservation
                aria-label="Cerrar"
            >
                &times;
            </button>

        </div>


        <div class="reservation-modal-body">

            <input
                type="hidden"
                id="modifyReservaId"
            >


            <div class="reservation-modal-section">

                <h3>
                    Información del usuario
                </h3>

                <div class="reservation-detail">
                    <strong>Nombre</strong>
                    <span id="modifyReservaNombre">-</span>
                </div>

                <div class="reservation-detail">
                    <strong>Identificación</strong>
                    <span id="modifyReservaRut">-</span>
                </div>

                <div class="reservation-detail">
                    <strong>Teléfono</strong>
                    <span id="modifyReservaTelefono">-</span>
                </div>

                <div class="reservation-detail">
                    <strong>Correo</strong>
                    <span id="modifyReservaCorreo">-</span>
                </div>

            </div>


            <div class="reservation-modal-section">

                <h3>
                    Información de la reserva
                </h3>

                <div class="reservation-detail">
                    <strong>ID de reserva</strong>
                    <span id="modifyReservaIdMostrar">-</span>
                </div>

                <div class="reservation-detail">
                    <strong>Servicio</strong>
                    <span id="modifyReservaServicio">-</span>
                </div>

                <div class="reservation-detail">
                    <strong>Fecha actual</strong>
                    <span id="modifyReservaFechaActual">-</span>
                </div>

                <div class="reservation-detail">
                    <strong>Hora actual</strong>
                    <span id="modifyReservaHoraActual">-</span>
                </div>

            </div>


            <div class="form-group">

                <label for="modifyReservaFecha">
                    Nueva fecha
                </label>

                <input
                    type="date"
                    id="modifyReservaFecha"
                >

            </div>


            <div class="form-group">

                <label for="modifyReservaHora">
                    Nueva hora
                </label>

                <select
                    id="modifyReservaHora"
                    disabled
                >

                    <option value="">
                        Seleccione una fecha
                    </option>

                </select>

                <small id="modifyReservaEstadoHorario"></small>

            </div>


            <div class="reservation-modal-actions">

                <button
                    type="button"
                    id="btnCancelReservationModify"
                    class="btn-secondary"
                >
                    Cancelar
                </button>

                <button
                    type="button"
                    id="btnSaveReservationModify"
                    class="btn-primary"
                >
                    Guardar cambios
                </button>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     POPUP ASIGNAR HORA A SOLICITUD
========================================================= -->

<div
    id="requestAssignModal"
    class="request-modal"
    hidden
>

    <div
        class="request-modal-overlay"
        data-close-request-modal
    ></div>

    <div
        class="request-modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="requestAssignTitle"
    >

        <div class="request-modal-header">

            <div>

                <span class="reservation-view-kicker">
                    GESTIÓN DE SOLICITUD
                </span>

                <h2 id="requestAssignTitle">
                    Asignar hora al paciente
                </h2>

            </div>

            <button
                type="button"
                class="request-modal-close"
                data-close-request-modal
                aria-label="Cerrar"
            >
                ×
            </button>

        </div>


        <div class="request-modal-body">

            <input
                type="hidden"
                id="requestAssignId"
            >

            <input
                type="hidden"
                id="requestAssignServiceId"
            >

            <input
                type="hidden"
                id="requestAssignDepartmentId"
            >

            <input
                type="hidden"
                id="requestAssignName"
            >

            <input
                type="hidden"
                id="requestAssignSurname"
            >

            <input
                type="hidden"
                id="requestAssignRut"
            >

            <input
                type="hidden"
                id="requestAssignPhone"
            >

            <input
                type="hidden"
                id="requestAssignEmail"
            >


            <div class="request-patient-information">

                <h3>
                    Datos del paciente
                </h3>

                <div class="request-patient-grid">

                    <div>
                        <span>Nombre</span>
                        <strong id="requestAssignPatientName">—</strong>
                    </div>

                    <div>
                        <span>RUT</span>
                        <strong id="requestAssignPatientRut">—</strong>
                    </div>

                    <div>
                        <span>Teléfono</span>
                        <strong id="requestAssignPatientPhone">—</strong>
                    </div>

                    <div>
                        <span>Correo</span>
                        <strong id="requestAssignPatientEmail">—</strong>
                    </div>

                    <div>
                        <span>Servicio</span>
                        <strong id="requestAssignServiceName">—</strong>
                    </div>

                </div>

            </div>


            <div class="request-assign-fields">

                <div class="form-group">

                    <label for="requestAssignDate">
                        Fecha solicitada
                    </label>

                    <input
                        type="date"
                        id="requestAssignDate"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="requestAssignHour">
                        Cupo disponible
                    </label>

                    <select
                        id="requestAssignHour"
                        disabled
                        required
                    >

                        <option value="">
                            Seleccione una fecha
                        </option>

                    </select>

                    <small id="requestAssignHourMessage"></small>

                </div>

            </div>


            <div class="request-modal-actions">

                <button
                    type="button"
                    class="btn-secondary"
                    data-close-request-modal
                >
                    Cancelar
                </button>

                <button
                    type="button"
                    id="btnConfirmRequestAssignment"
                    class="btn-primary"
                    disabled
                >
                    Asignar hora
                </button>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     FORMULARIO CREAR RESERVA
========================================================= -->

<?php

$serviciosFormulario = $servicios;

include '../componentes/formulario_crear_reserva.php';

?>


<!-- =========================================================
     NOTIFICACIÓN
========================================================= -->

<div
    id="notification"
    class="notification"
    hidden
>

    <span
        id="notificationIcon"
        class="notification-icon"
    >
        ✓
    </span>

    <span
        id="notificationText"
        class="notification-text"
    ></span>

</div>


<!-- =========================================================
     FOOTER
========================================================= -->

<?php
require_once '../componentes/footer_funcionario.php';
?>


</body>
</html>
