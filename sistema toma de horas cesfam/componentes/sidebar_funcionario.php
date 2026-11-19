
<?php

/*
 * =========================================================
 * SIDEBAR FUNCIONARIO — CESFAM EDUARDO RAGGIO CATEMU
 * =========================================================
 *
 * PERFILES:
 *
 * 1. SUPERADMIN:
 *    - Acceso completo.
 *
 * 2. ADMINISTRADOR DE RESERVAS:
 *    - Agenda.
 *    - Reservas.
 *    - Personal de atención.
 *
 * 3. ADMINISTRADOR WEB:
 *    - Administración web.
 *    - Noticias.
 *    - Tutoriales.
 *    - Biblioteca multimedia.
 *
 * REGLA:
 * - Solo el superadmin administra cuentas de acceso.
 * - El personal de atención no tiene acceso al panel.
 * - Las cuentas administrativas no dependen de departamentos.
 *
 * =========================================================
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/* =========================================================
   1. IDENTIFICAR PÁGINA ACTUAL
   ========================================================= */

$nombreArchivoActual = basename(
    $_SERVER['SCRIPT_NAME'] ?? ''
);

$paginasConocidas = [

    'panel.php'                  => 'panel',
    'agenda.php'                 => 'agenda',
    'horarios.php'               => 'horarios',
    'ver_horarios.php'           => 'ver_horarios',
    'gestionar_reserva.php'      => 'reservas',
    'gestionar_funcionarios.php' => 'funcionarios',
    'gestionar_estructura.php'   => 'estructura',
    'administrar_web.php'        => 'administrar_web',
    'administrar_noticias.php'   => 'administrar_noticias',
    'administrar_tutoriales.php' => 'administrar_tutoriales'

];

$pagina_actual = $pagina_actual
    ?? ($paginasConocidas[$nombreArchivoActual] ?? '');


/*
 * La página de gestión de personal tiene dos vistas:
 *
 * vista=funcionarios
 * vista=profesionales
 */

if ($nombreArchivoActual === 'gestionar_funcionarios.php') {

    $vistaPersonal = $_GET['vista'] ?? 'funcionarios';

    if ($vistaPersonal === 'profesionales') {

        $pagina_actual = 'personal_atencion';

    } else {

        $pagina_actual = 'funcionarios';
    }
}


/* =========================================================
   2. IDENTIFICAR ROL Y PERMISOS
   ========================================================= */

$rol_raw = $_SESSION['rol_funcionario']
    ?? $_SESSION['rol']
    ?? '';

$rol_funcionario = strtolower(
    trim((string) $rol_raw)
);


/*
 * Superadministrador.
 */

$es_superadmin = (
    $rol_funcionario === 'superadmin'
);


/*
 * Administrador de reservas.
 */

$es_admin_reservas = in_array(
    $rol_funcionario,
    ['administrador', 'superadmin'],
    true
);


/*
 * Administrador web.
 *
 * El superadmin siempre tiene acceso.
 * Los demás necesitan puede_administrar_web.
 */

$permisoWeb = $_SESSION['puede_administrar_web'] ?? false;

$permisoWebActivo = in_array(
    $permisoWeb,
    [true, 1, '1', 't', 'true'],
    true
);

$es_admin_web = (
    $es_superadmin ||
    $permisoWebActivo
);


/* =========================================================
   3. DETECTAR BIBLIOTECA MULTIMEDIA
   ========================================================= */

$paginaMultimedia = null;

$posiblesPaginasMultimedia = [

    'biblioteca_multimedia.php',
    'administrar_multimedia.php',
    'administrar_biblioteca_multimedia.php',
    'administrar_biblioteca.php',
    'biblioteca.php',
    'multimedia.php'

];


foreach ($posiblesPaginasMultimedia as $archivoMultimedia) {

    $rutaArchivoMultimedia = __DIR__
        . '/../funcionarios/'
        . $archivoMultimedia;

    if (is_file($rutaArchivoMultimedia)) {

        $paginaMultimedia = $archivoMultimedia;

        break;
    }
}


/*
 * Identificar correctamente la página activa.
 */

$paginasActivasMultimedia = [

    'biblioteca_multimedia.php'             => 'biblioteca_multimedia',
    'administrar_multimedia.php'            => 'administrar_multimedia',
    'administrar_biblioteca_multimedia.php' => 'administrar_multimedia',
    'administrar_biblioteca.php'            => 'biblioteca',
    'biblioteca.php'                        => 'biblioteca',
    'multimedia.php'                        => 'multimedia'

];


if (
    $paginaMultimedia !== null &&
    $nombreArchivoActual === $paginaMultimedia
) {

    $pagina_actual =
        $paginasActivasMultimedia[$paginaMultimedia]
        ?? 'multimedia';
}

?>

<aside
    class="sidebar"
    id="sidebarNav"
    aria-label="Menú principal de opciones"
>

    <nav
        class="nav-group"
        role="navigation"
        aria-label="Navegación principal"
    >


        <!-- =========================================
             PANEL PRINCIPAL
             Todos los perfiles
        ========================================== -->

        <a
            href="panel.php"
            class="nav-link <?= $pagina_actual === 'panel' ? 'active' : '' ?>"
            <?= $pagina_actual === 'panel' ? 'aria-current="page"' : '' ?>
        >

            <span class="nav-icon" aria-hidden="true">

                <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>

            </span>

            <span>Panel principal</span>

        </a>


        <!-- =========================================
             ADMINISTRACIÓN DE RESERVAS
             Superadmin / Administrador de reservas
        ========================================== -->

        <?php if ($es_admin_reservas): ?>


            <!-- AGENDA -->

            <a
                href="agenda.php"
                class="nav-link <?= $pagina_actual === 'agenda' ? 'active' : '' ?>"
                <?= $pagina_actual === 'agenda' ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="3" y="4" width="18" height="18" rx="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>

                </span>

                <span>Agenda</span>

            </a>


            <!-- RESERVAS -->

            <a
                href="gestionar_reserva.php"
                class="nav-link <?= $pagina_actual === 'reservas' ? 'active' : '' ?>"
                <?= $pagina_actual === 'reservas' ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        width="22"
                        height="22"
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

                </span>

                <span>Reservas</span>

            </a>


            <!-- =====================================
                 PERSONAL DE ATENCIÓN
                 Superadmin / Administrador de reservas
            ====================================== -->

            <a
                href="gestionar_funcionarios.php?vista=profesionales"
                class="nav-link <?= $pagina_actual === 'personal_atencion' ? 'active' : '' ?>"
                <?= $pagina_actual === 'personal_atencion' ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        width="22"
                        height="22"
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

                </span>

                <span>Personal de atención</span>

            </a>


        <?php endif; ?>


        <!-- =========================================
             GESTIÓN DE CUENTAS ADMINISTRATIVAS
             EXCLUSIVO SUPERADMIN
        ========================================== -->

        <?php if ($es_superadmin): ?>


            <a
                href="gestionar_funcionarios.php?vista=funcionarios"
                class="nav-link <?= $pagina_actual === 'funcionarios' ? 'active' : '' ?>"
                <?= $pagina_actual === 'funcionarios' ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        width="22"
                        height="22"
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

                </span>

                <span>Funcionarios del sistema</span>

            </a>


            <!-- DEPARTAMENTOS Y SERVICIOS -->

            <a
                href="gestionar_estructura.php"
                class="nav-link <?= $pagina_actual === 'estructura' ? 'active' : '' ?>"
                <?= $pagina_actual === 'estructura' ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M3 21h18"></path>
                        <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                        <path d="M9 7h2"></path>
                        <path d="M13 7h2"></path>
                        <path d="M9 11h2"></path>
                        <path d="M13 11h2"></path>
                        <path d="M9 15h2"></path>
                        <path d="M13 15h2"></path>
                    </svg>

                </span>

                <span>Departamentos y servicios</span>

            </a>


        <?php endif; ?>


        <!-- =========================================
             ADMINISTRACIÓN WEB
             Superadmin / Administrador web
        ========================================== -->

        <?php if ($es_admin_web): ?>


            <!-- ADMINISTRACIÓN DEL SITIO WEB -->

            <a
                href="administrar_web.php"
                class="nav-link <?= $pagina_actual === 'administrar_web' ? 'active' : '' ?>"
                <?= $pagina_actual === 'administrar_web' ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                        <path d="M3 9h18"></path>
                        <path d="M7 6.5h.01"></path>
                        <path d="M10 6.5h.01"></path>
                        <path d="M8 13h8"></path>
                        <path d="M8 16h5"></path>
                    </svg>

                </span>

                <span>Administración web</span>

            </a>


            <!-- NOTICIAS -->

            <a
                href="administrar_noticias.php"
                class="nav-link <?= $pagina_actual === 'administrar_noticias' ? 'active' : '' ?>"
                <?= $pagina_actual === 'administrar_noticias' ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M4 4h16v16H4z"></path>
                        <path d="M8 8h8"></path>
                        <path d="M8 12h8"></path>
                        <path d="M8 16h5"></path>
                    </svg>

                </span>

                <span>Administrar noticias</span>

            </a>


            <!-- TUTORIALES -->

            <a
                href="administrar_tutoriales.php"
                class="nav-link <?= $pagina_actual === 'administrar_tutoriales' ? 'active' : '' ?>"
                <?= $pagina_actual === 'administrar_tutoriales' ? 'aria-current="page"' : '' ?>
            >

                <span class="nav-icon" aria-hidden="true">

                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                        <path d="m10 9 5 3-5 3z"></path>
                    </svg>

                </span>

                <span>Administrar tutoriales</span>

            </a>


            <!-- BIBLIOTECA MULTIMEDIA -->

            <?php if ($paginaMultimedia !== null): ?>

                <?php

                $paginaActivaMultimedia = in_array(
                    $pagina_actual,
                    [
                        'administrar_multimedia',
                        'biblioteca_multimedia',
                        'biblioteca',
                        'multimedia'
                    ],
                    true
                );

                ?>

                <a
                    href="<?= htmlspecialchars(
                        $paginaMultimedia,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        'UTF-8'
                    ) ?>"
                    class="nav-link <?= $paginaActivaMultimedia ? 'active' : '' ?>"
                    <?= $paginaActivaMultimedia ? 'aria-current="page"' : '' ?>
                >

                    <span class="nav-icon" aria-hidden="true">

                        <svg
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <path d="m21 15-5-5L5 21"></path>
                            <path d="M14 14l2-2 5 5"></path>
                        </svg>

                    </span>

                    <span>Biblioteca multimedia</span>

                </a>

            <?php endif; ?>


        <?php endif; ?>


        <!-- =========================================
             SITIO PÚBLICO
             Todos los perfiles
        ========================================== -->

        <a
            href="../publicos/index.php"
            class="nav-link"
            target="_blank"
            rel="noopener noreferrer"
        >

            <span class="nav-icon" aria-hidden="true">

                <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>

            </span>

            <span>Ver sitio público</span>

        </a>


    </nav>


    <!-- =========================================
         CERRAR SESIÓN
         Todos los perfiles autenticados
    ========================================== -->

    <div class="sidebar-footer">

        <a
            href="../api/logout_funcionario.php"
            class="nav-link-logout"
            id="btnSidebarLogout"
            title="Cerrar sesión"
            aria-label="Cerrar sesión"
        >

            <span class="nav-icon" aria-hidden="true">

                <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>

            </span>

            <span>Cerrar sesión</span>

        </a>

    </div>

</aside>
