
<?php
/*
|---------------------------------------------------------------------------
| PROTECCIÓN DEL PANEL & LOGICA PHP
|---------------------------------------------------------------------------
*/
require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';

$nombreFuncionario   = $_SESSION['nombre_funcionario']   ?? 'Funcionario';
$apellidoFuncionario = $_SESSION['apellido_funcionario'] ?? '';
$usuarioFuncionario  = $_SESSION['usuario_funcionario']  ?? '';
$rolFuncionario      = $_SESSION['rol_funcionario']      ?? 'funcionario';

$nombreCompleto = trim($nombreFuncionario . ' ' . $apellidoFuncionario);

if ($rolFuncionario === 'superadmin') {
    $rolMostrar = 'Superadministrador';
} elseif ($rolFuncionario === 'administrador') {
    $rolMostrar = 'Administrador';
} else {
    $rolMostrar = 'Funcionario';
}

$nombreMostrar  = htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8');
$usuarioMostrar = htmlspecialchars($usuarioFuncionario, ENT_QUOTES, 'UTF-8');
$rolMostrar     = htmlspecialchars($rolMostrar, ENT_QUOTES, 'UTF-8');

// Ruta base corregida a relativa para evitar fallos de enlace
$base_url           = '..';
$nombre_funcionario = $nombreCompleto;
$rol_funcionario    = $rolFuncionario;
$pagina_actual      = 'panel';


/* =========================================================
   PERMISO PARA ADMINISTRAR LA PÁGINA PÚBLICA
   ========================================================= */

/*
 * El superadmin siempre tiene acceso a la administración web,
 * incluso cuando la columna puede_administrar_web sea FALSE.
 *
 * Para el resto del personal, el permiso se comprueba
 * directamente en PostgreSQL.
 */

$puedeAdministrarWeb =
    $rolFuncionario === 'superadmin';

if (!$puedeAdministrarWeb) {

    try {

        $sqlPermisoWeb = "
            SELECT puede_administrar_web
            FROM funcionarios
            WHERE id_funcionario = :id_funcionario
            LIMIT 1
        ";

        $stmtPermisoWeb = $conexion->prepare(
            $sqlPermisoWeb
        );

        $stmtPermisoWeb->execute([
            ':id_funcionario' =>
                (int) ($_SESSION['id_funcionario'] ?? 0)
        ]);

        $permisoWeb = $stmtPermisoWeb->fetchColumn();

        $puedeAdministrarWeb = in_array(
            $permisoWeb,
            [true, 1, '1', 't', 'true'],
            true
        );

    } catch (PDOException $e) {

        error_log(
            'Error consultar permiso de administración web: ' .
            $e->getMessage()
        );

        // Si falla la comprobación, no se muestra el acceso.
        $puedeAdministrarWeb = false;
    }
}


$totalReservas     = 0;
$totalHorarios     = 0;
$totalServicios    = 0;
$totalFuncionarios = 0;
$idDepartamentoFuncionario = null;

if ($rolFuncionario === 'administrador' || $rolFuncionario === 'funcionario') {
    $sqlDepartamento = "SELECT id_departamento FROM funcionarios WHERE usuario = :usuario LIMIT 1";
    $stmtDepartamento = $conexion->prepare($sqlDepartamento);
    $stmtDepartamento->execute([':usuario' => $usuarioFuncionario]);
    $filaDepartamento = $stmtDepartamento->fetch();

    if ($filaDepartamento) {
        $idDepartamentoFuncionario = $filaDepartamento['id_departamento'];
    }
}


// RESERVAS
if ($rolFuncionario === 'superadmin') {

    $sqlReservas = "SELECT COUNT(*) AS total FROM reservas";

    $stmtReservas = $conexion->prepare($sqlReservas);
    $stmtReservas->execute();

} else {

    $sqlReservas = "
        SELECT COUNT(*) AS total
        FROM reservas r
        INNER JOIN servicios s
            ON s.id_servicio = r.id_servicio
        WHERE s.id_departamento = :id_departamento
    ";

    $stmtReservas = $conexion->prepare($sqlReservas);

    $stmtReservas->execute([
        ':id_departamento' => $idDepartamentoFuncionario
    ]);
}

$filaReservas = $stmtReservas->fetch();

if ($filaReservas) {
    $totalReservas = (int) ($filaReservas['total'] ?? 0);
}


// HORARIOS
if ($rolFuncionario === 'superadmin') {

    $sqlHorarios = "
        SELECT COUNT(*) AS total
        FROM horarios
        WHERE estado = 'disponible'
    ";

    $stmtHorarios = $conexion->prepare($sqlHorarios);
    $stmtHorarios->execute();

} else {

    $sqlHorarios = "
        SELECT COUNT(*) AS total
        FROM horarios h
        INNER JOIN servicios s
            ON s.id_servicio = h.id_servicio
        WHERE h.estado = 'disponible'
        AND s.id_departamento = :id_departamento
    ";

    $stmtHorarios = $conexion->prepare($sqlHorarios);

    $stmtHorarios->execute([
        ':id_departamento' => $idDepartamentoFuncionario
    ]);
}

$filaHorarios = $stmtHorarios->fetch();

if ($filaHorarios) {
    $totalHorarios = (int) ($filaHorarios['total'] ?? 0);
}


// SERVICIOS
if ($rolFuncionario === 'superadmin') {

    $sqlServicios = "
        SELECT COUNT(DISTINCT id_servicio) AS total
        FROM servicios
        WHERE activo = TRUE
    ";

    $stmtServicios = $conexion->prepare($sqlServicios);
    $stmtServicios->execute();

} else {

    $sqlServicios = "
        SELECT COUNT(DISTINCT id_servicio) AS total
        FROM servicios
        WHERE activo = TRUE
        AND id_departamento = :id_departamento
    ";

    $stmtServicios = $conexion->prepare($sqlServicios);

    $stmtServicios->execute([
        ':id_departamento' => $idDepartamentoFuncionario
    ]);
}

$filaServicios = $stmtServicios->fetch();

if ($filaServicios) {
    $totalServicios = (int) ($filaServicios['total'] ?? 0);
}


// FUNCIONARIOS
if ($rolFuncionario === 'superadmin') {

    $sqlFuncionarios = "
        SELECT COUNT(*) AS total
        FROM funcionarios
        WHERE activo = TRUE
    ";

    $stmtFuncionarios = $conexion->prepare($sqlFuncionarios);
    $stmtFuncionarios->execute();

} elseif ($rolFuncionario === 'administrador') {

    $sqlFuncionarios = "
        SELECT COUNT(*) AS total
        FROM funcionarios
        WHERE activo = TRUE
        AND id_departamento = :id_departamento
    ";

    $stmtFuncionarios = $conexion->prepare($sqlFuncionarios);

    $stmtFuncionarios->execute([
        ':id_departamento' => $idDepartamentoFuncionario
    ]);

} else {

    $stmtFuncionarios = null;
}

if ($stmtFuncionarios !== null) {

    $filaFuncionarios = $stmtFuncionarios->fetch();

    if ($filaFuncionarios) {
        $totalFuncionarios = (int) ($filaFuncionarios['total'] ?? 0);
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

    <title>Panel Administrativo | CESFAM Catemu</title>

    <!-- =====================================================
         ESTILOS
         ===================================================== -->

    <!-- Navbar -->
    <link
        rel="stylesheet"
        href="../css/compartidos/navbar_funcionario.css"
    >

    <!-- Panel -->
    <link
        rel="stylesheet"
        href="../css/funcionarios/panel.css"
    >

    <!-- Sidebar
         Se carga después de panel.css para mantener
         el diseño propio de la navegación lateral -->
    <link
        rel="stylesheet"
        href="../css/compartidos/sidebar_funcionario.css"
    >

    <!-- Footer compartido de todos los funcionarios -->
    <link
        rel="stylesheet"
        href="../css/compartidos/footer_funcionario.css"
    >

</head>


<body>

    <!-- =====================================================
         1. NAVBAR SUPERIOR
         ===================================================== -->

    <?php require_once '../componentes/navbar_funcionario.php'; ?>


    <!-- =====================================================
         CONTENEDOR PRINCIPAL DEL SISTEMA
         ===================================================== -->

    <div class="app-container">


        <!-- =================================================
             2. MENÚ LATERAL
             ================================================= -->

        <?php require_once '../componentes/sidebar_funcionario.php'; ?>


        <!-- =================================================
             3. CONTENIDO PRINCIPAL
             ================================================= -->

        <main
            class="main-content"
            id="mainContent"
            role="main"
        >


            <!-- =================================================
                 ENCABEZADO DE BIENVENIDA
                 ================================================= -->

            <section
                class="welcome-header"
                aria-labelledby="welcomeTitle"
            >

                <h1
                    class="welcome-title"
                    id="welcomeTitle"
                >
                    Hola, <?php echo $nombreMostrar; ?> 👋
                </h1>

                <p class="welcome-subtitle">
                    Desde este panel puedes acceder a los módulos
                    del CESFAM y consultar las funciones disponibles
                    para tu cuenta.
                </p>

            </section>


            <!-- =================================================
                 ACCESO A LOS DOS MÓDULOS DEL SISTEMA
                 ================================================= -->

            <section
                class="main-actions-section"
                aria-labelledby="modulosSistemaTitle"
            >

                <div class="section-header">

                    <h2
                        class="section-title"
                        id="modulosSistemaTitle"
                    >
                        Módulos del sistema
                    </h2>

                </div>


                <div class="main-actions-grid">


                    <!-- =====================================
                         MÓDULO 1: TOMA DE HORAS
                         ===================================== -->

                    <a
                        href="agenda.php"
                        class="action-card action-card-primary"
                    >

                        <div
                            class="action-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="28"
                                height="28"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <rect
                                    x="3"
                                    y="4"
                                    width="18"
                                    height="18"
                                    rx="2"
                                    ry="2"
                                ></rect>

                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>

                            </svg>

                        </div>

                        <span class="action-card-title">
                            Toma de horas
                        </span>

                        <span class="action-card-desc">
                            Accede a la agenda, las reservas,
                            los horarios y la gestión de atención.
                        </span>

                    </a>


                    <!-- =====================================
                         MÓDULO 2: ADMINISTRACIÓN WEB
                         Visible solo para usuarios autorizados
                         ===================================== -->

                    <?php if ($puedeAdministrarWeb): ?>

                        <a
                            href="administrar_web.php"
                            class="action-card"
                        >

                            <div
                                class="action-card-icon"
                                aria-hidden="true"
                            >

                                <svg
                                    width="28"
                                    height="28"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >

                                    <rect
                                        x="3"
                                        y="3"
                                        width="18"
                                        height="18"
                                        rx="2"
                                        ry="2"
                                    ></rect>

                                    <line x1="3" y1="9" x2="21" y2="9"></line>

                                    <circle cx="7" cy="6" r="0.5"></circle>
                                    <circle cx="10" cy="6" r="0.5"></circle>

                                    <path d="M8 14h8"></path>
                                    <path d="M8 17h5"></path>

                                </svg>

                            </div>

                            <span class="action-card-title">
                                Administración de la página pública
                            </span>

                            <span class="action-card-desc">
                                Gestiona noticias, slider, banners,
                                tutoriales e información institucional.
                            </span>

                        </a>

                    <?php endif; ?>


                </div>

            </section>


            <hr
                class="section-divider"
                aria-hidden="true"
            >


            <!-- =================================================
                 TARJETAS DE RESUMEN Y ESTADÍSTICAS
                 ================================================= -->

            <section
                class="main-actions-section"
                aria-label="Resumen del sistema"
            >

                <div class="main-actions-grid">


                    <!-- Card Reservas -->

                    <div class="action-card action-card-primary">

                        <div
                            class="action-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="28"
                                height="28"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>

                            </svg>

                        </div>

                        <span class="action-card-title">
                            <?php echo $totalReservas; ?>
                        </span>

                        <span class="action-card-desc">
                            Reservas registradas en el sistema
                        </span>

                    </div>


                    <!-- Card Horarios -->

                    <div class="action-card">

                        <div
                            class="action-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="28"
                                height="28"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>

                            </svg>

                        </div>

                        <span class="action-card-title">
                            <?php echo $totalHorarios; ?>
                        </span>

                        <span class="action-card-desc">
                            Horarios disponibles de atención
                        </span>

                    </div>


                    <!-- Card Servicios -->

                    <div class="action-card">

                        <div
                            class="action-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="28"
                                height="28"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                                <line x1="9" y1="6" x2="15" y2="6"></line>
                                <line x1="9" y1="10" x2="15" y2="10"></line>

                            </svg>

                        </div>

                        <span class="action-card-title">
                            <?php echo $totalServicios; ?>
                        </span>

                        <span class="action-card-desc">
                            Servicios de salud activos
                        </span>

                    </div>


                    <?php if ($rolFuncionario === 'superadmin' || $rolFuncionario === 'administrador'): ?>

                    <!-- Card Funcionarios -->

                    <div class="action-card">

                        <div
                            class="action-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="28"
                                height="28"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>

                            </svg>

                        </div>

                        <span class="action-card-title">
                            <?php echo $totalFuncionarios; ?>
                        </span>

                        <span class="action-card-desc">
                            Funcionarios activos
                        </span>

                    </div>

                    <?php endif; ?>

                </div>

            </section>


            <hr
                class="section-divider"
                aria-hidden="true"
            >


            <!-- =================================================
                 ACCIONES RÁPIDAS
                 ================================================= -->

            <section
                class="quick-access-section"
                aria-labelledby="quickAccessTitle"
            >

                <div class="section-header">

                    <h2
                        class="section-title"
                        id="quickAccessTitle"
                    >

                        <svg
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >

                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>

                        </svg>

                        Acciones rápidas

                    </h2>

                </div>


                <div class="quick-access-grid">


                    <!-- Agenda -->

                    <a
                        href="agenda.php"
                        class="quick-card"
                    >

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="26"
                                height="26"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>

                            </svg>

                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Agenda
                            </span>

                            <span class="quick-card-desc">
                                Consulta la agenda y las horas programadas.
                            </span>

                        </div>

                        <svg
                            class="quick-card-arrow"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >

                            <polyline points="9 18 15 12 9 6"></polyline>

                        </svg>

                    </a>


                    <!-- Horarios -->

                    <a
                        href="horarios.php"
                        class="quick-card"
                    >

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="26"
                                height="26"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>

                            </svg>

                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Horarios
                            </span>

                            <span class="quick-card-desc">
                                Consulta y administra los horarios de atención.
                            </span>

                        </div>

                        <svg
                            class="quick-card-arrow"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >

                            <polyline points="9 18 15 12 9 6"></polyline>

                        </svg>

                    </a>


                    <!-- Gestionar Reservas -->

                    <a
                        href="gestionar_reserva.php"
                        class="quick-card"
                    >

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="26"
                                height="26"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>

                            </svg>

                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Gestionar reservas
                            </span>

                            <span class="quick-card-desc">
                                Consulta y gestiona las solicitudes de horas.
                            </span>

                        </div>

                        <svg
                            class="quick-card-arrow"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >

                            <polyline points="9 18 15 12 9 6"></polyline>

                        </svg>

                    </a>


                    <?php if ($rolFuncionario === 'superadmin' || $rolFuncionario === 'administrador'): ?>

                    <!-- Funcionarios -->

                    <a
                        href="gestionar_funcionarios.php"
                        class="quick-card"
                    >

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >

                            <svg
                                width="26"
                                height="26"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>

                            </svg>

                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Funcionarios
                            </span>

                            <span class="quick-card-desc">
                                Administra las cuentas de los funcionarios.
                            </span>

                        </div>

                        <svg
                            class="quick-card-arrow"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >

                            <polyline points="9 18 15 12 9 6"></polyline>

                        </svg>

                    </a>

                    <?php endif; ?>

                </div>

            </section>


            <hr
                class="section-divider"
                aria-hidden="true"
            >


            <!-- =================================================
                 ACTIVIDAD RECIENTE
                 ================================================= -->

            <section
                class="appointment-section"
                aria-labelledby="activitySectionTitle"
            >

                <div class="section-header">

                    <h2
                        class="section-title"
                        id="activitySectionTitle"
                    >

                        <svg
                            width="24"
                            height="24"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >

                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>

                        </svg>

                        Actividad reciente

                    </h2>

                </div>


                <article class="appointment-card">

                    <div
                        class="appointment-card-body"
                        style="text-align: center; padding: 35px 20px;"
                    >

                        <svg
                            width="48"
                            height="48"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="#9ca3af"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            style="margin-bottom: 12px;"
                        >

                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>

                        </svg>

                        <h3
                            style="
                                margin: 0;
                                font-size: 16px;
                                color: #374151;
                            "
                        >
                            Sin actividad reciente
                        </h3>

                        <p
                            style="
                                margin: 6px 0 0;
                                font-size: 13px;
                                color: #9ca3af;
                            "
                        >
                            Los últimos movimientos registrados
                            aparecerán aquí automáticamente.
                        </p>

                    </div>

                </article>

            </section>


            <!-- =================================================
                 FOOTER FUNCIONARIO COMPARTIDO
                 ================================================= -->

            <?php require_once '../componentes/footer_funcionario.php'; ?>


        </main>

    </div>


    <!-- =====================================================
         JS INTERACTIVO PARA DROPDOWN Y MÓVIL
         ===================================================== -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // Toggle Sidebar móvil
            const btnToggleSidebar =
                document.getElementById('btnToggleSidebar');

            const sidebarNav =
                document.getElementById('sidebarNav');

            const sidebarOverlay =
                document.getElementById('sidebarOverlay');


            if (
                btnToggleSidebar &&
                sidebarNav &&
                sidebarOverlay
            ) {

                btnToggleSidebar.addEventListener(
                    'click',
                    function() {

                        const isExpanded =
                            btnToggleSidebar.getAttribute(
                                'aria-expanded'
                            ) === 'true';

                        btnToggleSidebar.setAttribute(
                            'aria-expanded',
                            !isExpanded
                        );

                        sidebarNav.classList.toggle('active');

                        sidebarOverlay.classList.toggle('active');

                    }
                );


                sidebarOverlay.addEventListener(
                    'click',
                    function() {

                        btnToggleSidebar.setAttribute(
                            'aria-expanded',
                            'false'
                        );

                        sidebarNav.classList.remove('active');

                        sidebarOverlay.classList.remove('active');

                    }
                );

            }


            // Toggle Perfil Dropdown Navbar
            const btnUserDropdown =
                document.getElementById('userMenuBtn');

            const userDropdownMenu =
                document.getElementById('userDropdown');


            if (
                btnUserDropdown &&
                userDropdownMenu
            ) {

                btnUserDropdown.addEventListener(
                    'click',
                    function(e) {

                        e.stopPropagation();

                        const isVisible =
                            userDropdownMenu.style.display === 'block';

                        userDropdownMenu.style.display =
                            isVisible ? 'none' : 'block';

                    }
                );


                document.addEventListener(
                    'click',
                    function(e) {

                        if (
                            !btnUserDropdown.contains(e.target) &&
                            !userDropdownMenu.contains(e.target)
                        ) {

                            userDropdownMenu.style.display = 'none';

                        }

                    }
                );

            }

        });
    </script>

</body>

</html>
