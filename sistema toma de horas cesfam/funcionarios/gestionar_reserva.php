<?php

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';
require_once '../componentes/correos_reservas.php';

/* =========================================================
   GESTIONAR RESERVAS PHP
   ========================================================= */

/* =========================================================
   DATOS DEL FUNCIONARIO
   ========================================================= */

$nombreFuncionario = $_SESSION['nombre_funcionario'] ?? '';
$apellidoFuncionario = $_SESSION['apellido_funcionario'] ?? '';
$usuarioFuncionario = $_SESSION['usuario_funcionario'] ?? '';
$rolFuncionario = $_SESSION['rol_funcionario'] ?? '';


/* =========================================================
   NOMBRE COMPLETO
   ========================================================= */

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
} elseif ($rolFuncionario === 'admin') {
    $rolMostrar = 'Administrador';
} else {
    $rolMostrar = 'Funcionario';
}


/* =========================================================
   PROTECCIÓN CONTRA XSS
   ========================================================= */

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
   VARIABLES DE COMPONENTES
   ========================================================= */

$base_url = '/municipalidad';

$nombre_funcionario = $nombreCompleto;

$pagina_actual = 'reservas';


/* =========================================================
   VARIABLES
   ========================================================= */

$mensaje = '';
$tipoMensaje = '';

$filtroServicio = isset($_GET['servicio'])
    ? trim($_GET['servicio'])
    : '';

$filtroFecha = isset($_GET['fecha'])
    ? trim($_GET['fecha'])
    : '';

$filtroEstado = isset($_GET['estado'])
    ? trim($_GET['estado'])
    : '';

$filtroBusqueda = isset($_GET['busqueda'])
    ? trim($_GET['busqueda'])
    : '';

$reservaSeleccionada = null;


/* =========================================================
   FUNCIONES AUXILIARES
   ========================================================= */

function limpiarTexto($valor)
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatoFecha($fecha)
{
    if (!$fecha) {
        return '';
    }

    $fechaObjeto = DateTime::createFromFormat(
        'Y-m-d',
        (string) $fecha
    );

    if (!$fechaObjeto) {
        return $fecha;
    }

    return $fechaObjeto->format('d/m/Y');
}


/* =========================================================
   PROCESAR ACCIONES
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = isset($_POST['accion'])
        ? trim($_POST['accion'])
        : '';

    try {

        /* =================================================
           CONFIRMAR RESERVA
           ================================================= */

        if ($accion === 'confirmar') {

            $idReserva = isset($_POST['id_reserva'])
                ? (int) $_POST['id_reserva']
                : 0;

            if ($idReserva <= 0) {
                throw new Exception(
                    'La reserva seleccionada no es válida.'
                );
            }

            $sql = "
                UPDATE reservas
                SET
                    estado = 'confirmada',
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id_reserva = :id_reserva
            ";

            $stmt = $conexion->prepare($sql);

            $stmt->execute([
                ':id_reserva' => $idReserva
            ]);

            if ($stmt->rowCount() === 0) {
                throw new Exception(
                    'No se encontró la reserva seleccionada.'
                );
            }

            $mensaje = 'La reserva fue confirmada correctamente.';
            $tipoMensaje = 'success';
        }


        /* =================================================
           CANCELAR RESERVA
           ================================================= */

        elseif ($accion === 'cancelar') {

            $idReserva = isset($_POST['id_reserva'])
                ? (int) $_POST['id_reserva']
                : 0;

            if ($idReserva <= 0) {
                throw new Exception(
                    'La reserva seleccionada no es válida.'
                );
            }

            /*
             * Iniciar transacción para asegurar que la
             * cancelación y la liberación del horario
             * se guarden como una sola operación.
             */
            $conexion->beginTransaction();

            /*
             * Obtener toda la información necesaria para
             * cancelar la reserva y preparar los correos.
             *
             * Es importante recuperar el correo del paciente,
             * el servicio y el departamento antes de cancelar.
             */
            $sqlHorario = "
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
                    h.hora,

                    s.nombre AS nombre_servicio,
                    s.id_departamento,

                    d.nombre AS nombre_departamento

                FROM reservas r

                INNER JOIN horarios h
                    ON h.id_horario = r.id_horario

                INNER JOIN servicios s
                    ON s.id_servicio = r.id_servicio

                INNER JOIN departamentos d
                    ON d.id_departamento = s.id_departamento

                WHERE r.id_reserva = :id_reserva

                LIMIT 1
                FOR UPDATE OF r
            ";

            $stmtHorario = $conexion->prepare(
                $sqlHorario
            );

            $stmtHorario->execute([
                ':id_reserva' => $idReserva
            ]);

            $horarioReserva = $stmtHorario->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$horarioReserva) {
                throw new Exception(
                    'No se encontró la reserva seleccionada.'
                );
            }

            /*
             * Evitar cancelar dos veces la misma reserva.
             */
            if (
                strtolower(
                    trim((string) $horarioReserva['estado'])
                ) === 'cancelada'
            ) {
                throw new Exception(
                    'La reserva ya está cancelada.'
                );
            }

            /*
             * Guardar la reserva como cancelada.
             */
            $sqlReserva = "
                UPDATE reservas
                SET
                    estado = 'cancelada',
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id_reserva = :id_reserva
                  AND estado <> 'cancelada'
            ";

            $stmtReserva = $conexion->prepare(
                $sqlReserva
            );

            $stmtReserva->execute([
                ':id_reserva' => $idReserva
            ]);

            if ($stmtReserva->rowCount() === 0) {
                throw new Exception(
                    'No fue posible cancelar la reserva.'
                );
            }

            /*
             * Liberar el horario.
             *
             * Se mantiene la condición para no cambiar
             * horarios que estén bloqueados manualmente.
             */
            $sqlLiberar = "
                UPDATE horarios
                SET
                    estado = 'disponible'
                WHERE id_horario = :id_horario
                  AND estado <> 'bloqueado'
            ";

            $stmtLiberar = $conexion->prepare(
                $sqlLiberar
            );

            $stmtLiberar->execute([
                ':id_horario' =>
                    (int) $horarioReserva['id_horario']
            ]);

            /*
             * Confirmar primero los cambios de PostgreSQL.
             *
             * El correo se envía después para que un problema
             * de correo no revierta la cancelación.
             */
            $conexion->commit();

            /*
             * Preparar los datos del servicio y departamento
             * que utilizarán las funciones de correo.
             */
            $servicioCorreoCancelacion = [
                'id_departamento' =>
                    (int) $horarioReserva['id_departamento'],

                'nombre' =>
                    (string) $horarioReserva['nombre_servicio'],

                'nombre_departamento' =>
                    (string) $horarioReserva['nombre_departamento']
            ];

            /*
             * Conservar la fecha y hora originales de la reserva.
             */
            $fechaCancelada = substr(
                (string) $horarioReserva['fecha'],
                0,
                10
            );

            $horaCancelada = substr(
                (string) $horarioReserva['hora'],
                0,
                5
            );

            /*
             * ENVIAR CORREO AL PACIENTE Y A LOS FUNCIONARIOS
             */
            try {

                /*
                 * Correo al paciente.
                 *
                 * La función utiliza el correo guardado
                 * en la reserva. Si no existe o no es válido,
                 * revisa el registro de errores de PHP.
                 */
                enviarCorreoCancelacionReserva(
                    $horarioReserva,
                    $servicioCorreoCancelacion,
                    $fechaCancelada,
                    $horaCancelada
                );

                /*
                 * Notificar a los funcionarios activos
                 * del departamento de la reserva.
                 */
                enviarCorreoFuncionariosEventoReservaCESFAM(
                    $conexion,
                    $horarioReserva,
                    $servicioCorreoCancelacion,
                    'cancelacion',
                    [
                        [
                            'Fecha cancelada',
                            $fechaCancelada
                        ],
                        [
                            'Hora cancelada',
                            $horaCancelada
                        ]
                    ]
                );

            } catch (Throwable $e) {

                /*
                 * El error del correo no debe deshacer
                 * la cancelación ya guardada.
                 */
                error_log(
                    '[CESFAM CORREO] Error al enviar las notificaciones de cancelación de la reserva #' .
                    $idReserva . ': ' .
                    $e->getMessage()
                );
            }

            $mensaje = 'La reserva fue cancelada correctamente.';
            $tipoMensaje = 'success';
        }


        /* =================================================
           REAGENDAR RESERVA
           ================================================= */

        elseif ($accion === 'reagendar') {

            $idReserva = isset($_POST['id_reserva'])
                ? (int) $_POST['id_reserva']
                : 0;

            $idNuevoHorario = isset($_POST['id_nuevo_horario'])
                ? (int) $_POST['id_nuevo_horario']
                : 0;

            if ($idReserva <= 0) {
                throw new Exception(
                    'La reserva seleccionada no es válida.'
                );
            }

            if ($idNuevoHorario <= 0) {
                throw new Exception(
                    'Debe seleccionar un nuevo horario.'
                );
            }

            $conexion->beginTransaction();

            $sqlReservaActual = "
                SELECT
                    r.id_reserva,
                    r.id_horario,
                    r.id_servicio,
                    r.estado,
                    h.fecha,
                    h.hora
                FROM reservas r
                INNER JOIN horarios h
                    ON h.id_horario = r.id_horario
                WHERE r.id_reserva = :id_reserva
                FOR UPDATE
            ";

            $stmtReservaActual = $conexion->prepare(
                $sqlReservaActual
            );

            $stmtReservaActual->execute([
                ':id_reserva' => $idReserva
            ]);

            $reservaActual = $stmtReservaActual->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$reservaActual) {
                throw new Exception(
                    'No se encontró la reserva.'
                );
            }

            /*
             * Validar si una reserva confirmada ya transcurrió.
             */
            $fechaHoraString =
                $reservaActual['fecha'] .
                ' ' .
                substr(
                    (string) $reservaActual['hora'],
                    0,
                    5
                );

            $fechaHoraObjeto = DateTime::createFromFormat(
                'Y-m-d H:i',
                $fechaHoraString
            );

            $ahoraChile = new DateTime(
                'now',
                new DateTimeZone('America/Santiago')
            );

            if (
                $reservaActual['estado'] === 'confirmada' &&
                $fechaHoraObjeto &&
                $fechaHoraObjeto < $ahoraChile
            ) {
                throw new Exception(
                    'No es posible reagendar una reserva confirmada cuya fecha y hora ya transcurrieron.'
                );
            }

            $sqlNuevoHorario = "
                SELECT
                    id_horario,
                    id_servicio,
                    estado
                FROM horarios
                WHERE id_horario = :id_horario
                FOR UPDATE
            ";

            $stmtNuevoHorario = $conexion->prepare(
                $sqlNuevoHorario
            );

            $stmtNuevoHorario->execute([
                ':id_horario' => $idNuevoHorario
            ]);

            $nuevoHorario = $stmtNuevoHorario->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$nuevoHorario) {
                throw new Exception(
                    'El nuevo horario no existe.'
                );
            }

            if (
                $nuevoHorario['estado'] !== 'disponible'
            ) {
                throw new Exception(
                    'El horario seleccionado ya no está disponible.'
                );
            }

            if (
                (int) $nuevoHorario['id_servicio'] !==
                (int) $reservaActual['id_servicio']
            ) {
                throw new Exception(
                    'El nuevo horario no corresponde al mismo servicio.'
                );
            }

            $sqlComprobarReserva = "
                SELECT
                    id_reserva
                FROM reservas
                WHERE id_horario = :id_horario
                  AND estado <> 'cancelada'
                  AND id_reserva <> :id_reserva
                LIMIT 1
            ";

            $stmtComprobarReserva = $conexion->prepare(
                $sqlComprobarReserva
            );

            $stmtComprobarReserva->execute([
                ':id_horario' => $idNuevoHorario,
                ':id_reserva' => $idReserva
            ]);

            if ($stmtComprobarReserva->fetch()) {
                throw new Exception(
                    'El horario seleccionado ya tiene una reserva.'
                );
            }

            $sqlLiberarAnterior = "
                UPDATE horarios
                SET
                    estado = 'disponible'
                WHERE id_horario = :id_horario
                  AND estado <> 'bloqueado'
            ";

            $stmtLiberarAnterior = $conexion->prepare(
                $sqlLiberarAnterior
            );

            $stmtLiberarAnterior->execute([
                ':id_horario' =>
                    (int) $reservaActual['id_horario']
            ]);

            $sqlOcuparNuevo = "
                UPDATE horarios
                SET
                    estado = 'bloqueado'
                WHERE id_horario = :id_horario
                  AND estado = 'disponible'
            ";

            $stmtOcuparNuevo = $conexion->prepare(
                $sqlOcuparNuevo
            );

            $stmtOcuparNuevo->execute([
                ':id_horario' => $idNuevoHorario
            ]);

            if ($stmtOcuparNuevo->rowCount() === 0) {
                throw new Exception(
                    'No fue posible reservar el nuevo horario.'
                );
            }

            $sqlActualizarReserva = "
                UPDATE reservas
                SET
                    id_horario = :id_nuevo_horario,
                    estado = 'reagendada',
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id_reserva = :id_reserva
            ";

            $stmtActualizarReserva = $conexion->prepare(
                $sqlActualizarReserva
            );

            $stmtActualizarReserva->execute([
                ':id_nuevo_horario' => $idNuevoHorario,
                ':id_reserva' => $idReserva
            ]);

            $conexion->commit();

            $mensaje = 'La reserva fue reagendada correctamente.';
            $tipoMensaje = 'success';
        }


        /* =================================================
           NOTIFICAR
           ================================================= */

        elseif ($accion === 'notificar') {

            $idReserva = isset($_POST['id_reserva'])
                ? (int) $_POST['id_reserva']
                : 0;

            if ($idReserva <= 0) {
                throw new Exception(
                    'La reserva seleccionada no es válida.'
                );
            }

            $sql = "
                SELECT
                    id_reserva
                FROM reservas
                WHERE id_reserva = :id_reserva
                LIMIT 1
            ";

            $stmt = $conexion->prepare($sql);

            $stmt->execute([
                ':id_reserva' => $idReserva
            ]);

            if (!$stmt->fetch()) {
                throw new Exception(
                    'No se encontró la reserva seleccionada.'
                );
            }

            $mensaje = 'La reserva está lista para ser notificada.';
            $tipoMensaje = 'success';
        }

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error en gestionar_reserva.php: ' .
            $e->getMessage()
        );

        $mensaje = $e->getMessage();
        $tipoMensaje = 'error';
    }
}


/* =========================================================
   OBTENER SERVICIOS
   ========================================================= */

$servicios = [];

try {

    $sqlServicios = "
        SELECT
            id_servicio,
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
}


/* =========================================================
   OBTENER RESERVAS
   ========================================================= */

$reservas = [];

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
            h.estado AS estado_horario

        FROM reservas r

        INNER JOIN servicios s
            ON s.id_servicio = r.id_servicio

        INNER JOIN horarios h
            ON h.id_horario = r.id_horario

        WHERE 1 = 1
    ";

    $parametros = [];


    /* =====================================================
       FILTRO SERVICIO
       ===================================================== */

    if ($filtroServicio !== '') {

        $sqlReservas .= "
            AND r.id_servicio = :filtro_servicio
        ";

        $parametros[':filtro_servicio'] =
            (int) $filtroServicio;
    }


    /* =====================================================
       FILTRO FECHA
       ===================================================== */

    if ($filtroFecha !== '') {

        $sqlReservas .= "
            AND h.fecha::DATE = :filtro_fecha
        ";

        $parametros[':filtro_fecha'] =
            $filtroFecha;
    }


    /* =====================================================
       FILTRO ESTADO
       ===================================================== */

    if ($filtroEstado !== '') {

        $sqlReservas .= "
            AND r.estado = :filtro_estado
        ";

        $parametros[':filtro_estado'] =
            $filtroEstado;
    }


    /* =====================================================
       BUSCADOR
       ===================================================== */

    if ($filtroBusqueda !== '') {

        $sqlReservas .= "
            AND (
                LOWER(r.nombre) LIKE LOWER(:busqueda_nombre)
                OR LOWER(r.rut) LIKE LOWER(:busqueda_rut)
                OR LOWER(COALESCE(r.correo, '')) LIKE LOWER(:busqueda_correo)
                OR r.telefono LIKE :busqueda_telefono
            )
        ";

        $busqueda = '%' . $filtroBusqueda . '%';

        $parametros[':busqueda_nombre'] = $busqueda;
        $parametros[':busqueda_rut'] = $busqueda;
        $parametros[':busqueda_correo'] = $busqueda;
        $parametros[':busqueda_telefono'] = $busqueda;
    }


    /* =====================================================
       ORDEN
       ===================================================== */

    $sqlReservas .= "
        ORDER BY
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


    /* =====================================================
       ORDENAMIENTO DEFINITIVO EN PHP
       ===================================================== */

    usort(
        $reservas,
        function ($a, $b) {

            $fechaA = trim(
                (string) ($a['fecha_horario'] ?? '')
            );

            $fechaB = trim(
                (string) ($b['fecha_horario'] ?? '')
            );

            if ($fechaA !== $fechaB) {
                return strcmp(
                    $fechaA,
                    $fechaB
                );
            }

            $horaA = trim(
                (string) ($a['hora_horario'] ?? '')
            );

            $horaB = trim(
                (string) ($b['hora_horario'] ?? '')
            );

            $horaAComparar = substr(
                $horaA,
                0,
                8
            );

            $horaBComparar = substr(
                $horaB,
                0,
                8
            );

            if ($horaAComparar !== $horaBComparar) {
                return strcmp(
                    $horaAComparar,
                    $horaBComparar
                );
            }

            return
                ((int) ($a['id_reserva'] ?? 0))
                <=>
                ((int) ($b['id_reserva'] ?? 0));
        }
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
   CONTADORES
   ========================================================= */

$totalReservas = count($reservas);

$totalConfirmadas = 0;
$totalReagendadas = 0;
$totalCanceladas = 0;

foreach ($reservas as $reserva) {

    switch ($reserva['estado']) {

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
   RESERVA SELECCIONADA
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

    <title>Gestionar reservas | Municipalidad</title>


    <!-- =====================================================
         ESTILOS GENERALES
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/compartidos/general.css"
    >


    <!-- =====================================================
         ESTILOS NAVBAR FUNCIONARIO
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/compartidos/navbar_funcionario.css"
    >


    <!-- =====================================================
         ESTILOS SIDEBAR FUNCIONARIO
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/compartidos/sidebar_funcionario.css"
    >


    <!-- =====================================================
         ESTILOS FOOTER FUNCIONARIO
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/compartidos/footer_funcionario.css"
    >


    <!-- =====================================================
         ESTILOS DE RESERVAS
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/funcionarios/reservas.css"
    >


    <!-- =====================================================
         ESTILOS DEL FORMULARIO CREAR RESERVA
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/funcionarios/formulario_crear_reserva.css"
    >


    <style>

        /* =====================================================
           SIDEBAR ICONOS
           ===================================================== */

        .sidebar-icon {
            width: 22px;
            min-width: 22px;
            height: 22px;

            display: inline-flex;

            align-items: center;
            justify-content: center;
        }


        /* =====================================================
           NOTIFICACIÓN
           ===================================================== */

        #notification {
            position: fixed;

            right: 25px;
            bottom: 25px;

            z-index: 10001;

            display: flex;

            align-items: center;

            gap: 10px;

            max-width: 420px;

            padding: 14px 18px;

            border-radius: 10px;

            background: #ffffff;

            box-shadow:
                0 10px 35px rgba(0, 0, 0, 0.18);

            border-left: 4px solid #0074c8;
        }


        #notification[hidden] {
            display: none;
        }


        .notification-icon {
            font-weight: bold;
        }


        .notification-text {
            line-height: 1.4;
        }


        @media (max-width: 700px) {

            #notification {
                left: 15px;
                right: 15px;
                bottom: 15px;

                max-width: none;
            }

        }

    </style>


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

        <section class="reservation-header">

            <div>

                <div class="page-kicker">
                    ADMINISTRACIÓN
                </div>

                <h1>
                    Gestionar reservas
                </h1>

                <p>
                    Crea, confirma, reagenda, cancela y
                    gestiona las reservas de los usuarios.
                </p>

            </div>


            <div class="reservation-header-actions">

                <button
                    type="button"
                    id="btnCreateReservation"
                    class="create-reservation-button"
                >
                    + Crear reserva
                </button>

            </div>

        </section>


        <?php if ($mensaje !== ''): ?>

            <div
                class="alert
                <?= $tipoMensaje === 'success'
                    ? 'alert-success'
                    : 'alert-error'
                ?>"
            >

                <?= limpiarTexto($mensaje) ?>

            </div>

        <?php endif; ?>


        <form
            method="GET"
            class="reservation-filters"
        >

            <div class="filter-group">

                <label for="busqueda">
                    Buscar
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


        <section class="reservation-summary">

            <div class="summary-card">

                <div class="summary-card-icon total">
                    📋
                </div>

                <div>

                    <span>
                        Total
                    </span>

                    <strong>
                        <?= $totalReservas ?>
                    </strong>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-card-icon confirmed">
                    ✓
                </div>

                <div>

                    <span>
                        Confirmadas
                    </span>

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

                    <span>
                        Reagendadas
                    </span>

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

                    <span>
                        Canceladas
                    </span>

                    <strong>
                        <?= $totalCanceladas ?>
                    </strong>

                </div>

            </div>

        </section>


        <section class="reservation-container">

            <div class="reservation-container-header">

                <div>

                    <h2>
                        Reservas
                    </h2>

                    <p>
                        Reservas registradas en el sistema,
                        ordenadas por fecha y hora de atención.
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

                                $esReservaPasada = false;

                                if (
                                    !empty($reserva['fecha_horario']) &&
                                    !empty($reserva['hora_horario'])
                                ) {

                                    $fechaHoraStr =
                                        $reserva['fecha_horario'] .
                                        ' ' .
                                        substr(
                                            (string) $reserva['hora_horario'],
                                            0,
                                            5
                                        );

                                    $fechaHoraObj = DateTime::createFromFormat(
                                        'Y-m-d H:i',
                                        $fechaHoraStr
                                    );

                                    $ahoraChile = new DateTime(
                                        'now',
                                        new DateTimeZone('America/Santiago')
                                    );

                                    if (
                                        $fechaHoraObj &&
                                        $fechaHoraObj < $ahoraChile
                                    ) {
                                        $esReservaPasada = true;
                                    }
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
                                                            $reserva['nombre'],
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

                                        <?= limpiarTexto($reserva['servicio_nombre']) ?>

                                    </td>


                                    <td>

                                        <?= formatoFecha($reserva['fecha_horario']) ?>

                                    </td>


                                    <td>

                                        <span class="reservation-time">
                                            <?= limpiarTexto(
                                                substr(
                                                    $reserva['hora_horario'],
                                                    0,
                                                    5
                                                )
                                            ) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <?php

                                        $claseEstado = 'unknown';

                                        if ($reserva['estado'] === 'confirmada') {
                                            $claseEstado = 'confirmed';
                                        } elseif ($reserva['estado'] === 'cancelada') {
                                            $claseEstado = 'cancelled';
                                        } elseif ($reserva['estado'] === 'reagendada') {
                                            $claseEstado = 'rescheduled';
                                        }

                                        ?>

                                        <span class="status <?= $claseEstado ?>">

                                            <?= limpiarTexto(
                                                ucfirst($reserva['estado'])
                                            ) ?>

                                        </span>

                                    </td>


                                    <td>

                                        <div class="reservation-actions">

                                            <!-- =================================================
                                                 BOTÓN VER
                                                 ================================================= -->

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
                                                data-hora="<?= limpiarTexto(substr($reserva['hora_horario'], 0, 5)) ?>"
                                                data-estado="<?= limpiarTexto($reserva['estado']) ?>"
                                            >
                                                Ver
                                            </button>


                                            <?php if ($reserva['estado'] !== 'cancelada'): ?>


                                                <?php if ($reserva['estado'] !== 'confirmada'): ?>

                                                    <form
                                                        method="POST"
                                                        style="display:inline;"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="confirmar"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="id_reserva"
                                                            value="<?= (int) $reserva['id_reserva'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="action-button"
                                                        >
                                                            Confirmar
                                                        </button>

                                                    </form>

                                                <?php endif; ?>


                                                <!-- =================================================
                                                     BOTÓN MODIFICAR
                                                     ================================================= -->

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
                                                    data-hora="<?= limpiarTexto(substr($reserva['hora_horario'], 0, 5)) ?>"
                                                    data-estado="<?= limpiarTexto($reserva['estado']) ?>"
                                                    data-es-pasada="<?= $esReservaPasada ? '1' : '0' ?>"
                                                >
                                                    Modificar
                                                </button>


                                                <!-- =================================================
                                                     BOTÓN CANCELAR
                                                     ================================================= -->

                                                <form
                                                    method="POST"
                                                    style="display:inline;"
                                                    onsubmit="return confirm('¿Está seguro de que desea cancelar esta reserva?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="accion"
                                                        value="cancelar"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="id_reserva"
                                                        value="<?= (int) $reserva['id_reserva'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="action-button"
                                                    >
                                                        Cancelar
                                                    </button>

                                                </form>


                                                <?php if (
                                                    !empty($reserva['correo']) ||
                                                    !empty($reserva['telefono'])
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        style="display:inline;"
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="accion"
                                                            value="notificar"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="id_reserva"
                                                            value="<?= (int) $reserva['id_reserva'] ?>"
                                                        >

                                                        <button
                                                            type="submit"
                                                            class="action-button"
                                                        >
                                                            Notificar
                                                        </button>

                                                    </form>

                                                <?php endif; ?>


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

                        <span>
                            ID reserva
                        </span>

                        <strong>
                            #<?= (int) $reservaSeleccionada['id_reserva'] ?>
                        </strong>

                    </div>


                    <div
                        class="detail-item"
                        data-id-servicio="<?= (int) $reservaSeleccionada['id_servicio'] ?>"
                    >

                        <span>
                            Servicio
                        </span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['servicio_nombre']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Nombre
                        </span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['nombre']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Identificación
                        </span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['rut']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Teléfono
                        </span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['telefono']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Correo
                        </span>

                        <strong>
                            <?= limpiarTexto(
                                $reservaSeleccionada['correo'] ?: 'No registrado'
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Fecha
                        </span>

                        <strong>
                            <?= formatoFecha(
                                $reservaSeleccionada['fecha_horario']
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Hora
                        </span>

                        <strong>
                            <?= limpiarTexto(
                                substr(
                                    $reservaSeleccionada['hora_horario'],
                                    0,
                                    5
                                )
                            ) ?>
                        </strong>

                    </div>


                    <div class="detail-item">

                        <span>
                            Estado
                        </span>

                        <strong>
                            <?= limpiarTexto(
                                ucfirst($reservaSeleccionada['estado'])
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

                    <span>
                        ID reserva
                    </span>

                    <strong id="viewReservaId">
                        —
                    </strong>

                </div>


                <div class="reservation-view-item">

                    <span>
                        Estado
                    </span>

                    <strong id="viewReservaEstado">
                        —
                    </strong>

                </div>


                <div class="reservation-view-item">

                    <span>
                        Nombre
                    </span>

                    <strong id="viewReservaNombre">
                        —
                    </strong>

                </div>


                <div class="reservation-view-item">

                    <span>
                        Identificación
                    </span>

                    <strong id="viewReservaRut">
                        —
                    </strong>

                </div>


                <div class="reservation-view-item">

                    <span>
                        Teléfono
                    </span>

                    <strong id="viewReservaTelefono">
                        —
                    </strong>

                </div>


                <div class="reservation-view-item">

                    <span>
                        Correo
                    </span>

                    <strong id="viewReservaCorreo">
                        —
                    </strong>

                </div>


                <div class="reservation-view-item">

                    <span>
                        Servicio
                    </span>

                    <strong id="viewReservaServicio">
                        —
                    </strong>

                </div>


                <div class="reservation-view-item">

                    <span>
                        Fecha
                    </span>

                    <strong id="viewReservaFecha">
                        —
                    </strong>

                </div>


                <div class="reservation-view-item">

                    <span>
                        Hora
                    </span>

                    <strong id="viewReservaHora">
                        —
                    </strong>

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


            <!-- =================================================
                 INFORMACIÓN DEL USUARIO
                 ================================================= -->

            <div class="reservation-modal-section">

                <h3>
                    Información del usuario
                </h3>


                <div class="reservation-detail">

                    <strong>
                        Nombre
                    </strong>

                    <span id="modifyReservaNombre">
                        -
                    </span>

                </div>


                <div class="reservation-detail">

                    <strong>
                        Identificación
                    </strong>

                    <span id="modifyReservaRut">
                        -
                    </span>

                </div>


                <div class="reservation-detail">

                    <strong>
                        Teléfono
                    </strong>

                    <span id="modifyReservaTelefono">
                        -
                    </span>

                </div>


                <div class="reservation-detail">

                    <strong>
                        Correo
                    </strong>

                    <span id="modifyReservaCorreo">
                        -
                    </span>

                </div>

            </div>


            <!-- =================================================
                 INFORMACIÓN DE LA RESERVA
                 ================================================= -->

            <div class="reservation-modal-section">

                <h3>
                    Información de la reserva
                </h3>


                <div class="reservation-detail">

                    <strong>
                        ID de reserva
                    </strong>

                    <span id="modifyReservaIdMostrar">
                        -
                    </span>

                </div>


                <div class="reservation-detail">

                    <strong>
                        Servicio
                    </strong>

                    <span id="modifyReservaServicio">
                        -
                    </span>

                </div>


                <div class="reservation-detail">

                    <strong>
                        Fecha actual
                    </strong>

                    <span id="modifyReservaFechaActual">
                        -
                    </span>

                </div>


                <div class="reservation-detail">

                    <strong>
                        Hora actual
                    </strong>

                    <span id="modifyReservaHoraActual">
                        -
                    </span>

                </div>

            </div>


            <!-- =================================================
                 NUEVA FECHA
                 ================================================= -->

            <div class="form-group">

                <label for="modifyReservaFecha">
                    Nueva fecha
                </label>

                <input
                    type="date"
                    id="modifyReservaFecha"
                >

            </div>


            <!-- =================================================
                 NUEVA HORA
                 ================================================= -->

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


                <small
                    id="modifyReservaEstadoHorario"
                ></small>

            </div>


            <!-- =================================================
                 ACCIONES
                 ================================================= -->

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
     POPUP CREAR RESERVA
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
     MODAL CANCELAR RESERVA
     ========================================================= -->

<div
    id="cancelModal"
    class="modal"
    hidden
>

    <div class="modal-content">

        <button
            type="button"
            id="btnCloseCancelModal"
            class="modal-close"
            aria-label="Cerrar"
        >
            ×
        </button>


        <div class="modal-icon">
            ⚠
        </div>


        <h2>
            Cancelar reserva
        </h2>


        <p>
            ¿Está seguro de que desea cancelar esta reserva?
        </p>


        <div class="modal-actions">

            <button
                type="button"
                id="btnCancelModalBack"
                class="secondary-button"
            >
                Volver
            </button>


            <button
                type="button"
                id="btnConfirmCancel"
                class="danger-button"
            >
                Cancelar reserva
            </button>

        </div>

    </div>

</div>


<!-- =========================================================
     FOOTER FUNCIONARIO
     ========================================================= -->

<?php
require_once '../componentes/footer_funcionario.php';
?>


</body>

</html>