
<?php
/*
 * ============================================
 * SIDEBAR FUNCIONARIO - CESFAM
 *
 * Archivo: componentes/sidebar_funcionario.php
 *
 * Actualización:
 * - Acceso a la administración del sitio web.
 * - Acceso directo a la administración de noticias.
 * - Detección de la página de biblioteca multimedia.
 * - Identificación automática de la página activa.
 * - Se conservan los permisos según el rol.
 * ============================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * =========================================================
 * IDENTIFICAR PÁGINA ACTUAL
 * =========================================================
 */

$pagina_actual = $pagina_actual ?? '';

if ($pagina_actual === '') {
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
        'administrar_noticias.php'   => 'administrar_noticias'
    ];

    $pagina_actual = $paginasConocidas[$nombreArchivoActual] ?? '';
}

/*
 * =========================================================
 * IDENTIFICAR ROL DEL FUNCIONARIO
 * =========================================================
 */

$rol_raw = $rol_funcionario
    ?? ($_SESSION['rol_funcionario'] ?? ($_SESSION['rol'] ?? ''));

$rol_funcionario = strtolower(trim((string) $rol_raw));

$es_admin = in_array(
    $rol_funcionario,
    ['administrador', 'superadmin'],
    true
);

$es_superadmin = ($rol_funcionario === 'superadmin');

/*
 * =========================================================
 * DETECTAR PÁGINA DE BIBLIOTECA MULTIMEDIA
 * =========================================================
 *
 * Se comprueban nombres habituales para evitar mostrar
 * enlaces que apunten a archivos inexistentes.
 *
 * Si tu página de biblioteca tiene otro nombre, se deberá
 * incorporar ese nombre a la lista.
 */

$paginaMultimedia = null;

$posiblesPaginasMultimedia = [
    'administrar_multimedia.php',
    'biblioteca_multimedia.php',
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
 * Si la página existe, identificarla para resaltar
 * correctamente su opción en el menú.
 */

$paginasActivasMultimedia = [
    'administrar_multimedia.php'          => 'administrar_multimedia',
    'biblioteca_multimedia.php'           => 'biblioteca_multimedia',
    'administrar_biblioteca_multimedia.php' => 'administrar_multimedia',
    'administrar_biblioteca.php'          => 'biblioteca',
    'biblioteca.php'                      => 'biblioteca',
    'multimedia.php'                      => 'multimedia'
];

if (
    $paginaMultimedia !== null
    && basename($_SERVER['SCRIPT_NAME'] ?? '') === $paginaMultimedia
) {
    $pagina_actual =
        $paginasActivasMultimedia[$paginaMultimedia] ?? 'multimedia';
}
?>

<aside class="sidebar" id="sidebarNav" aria-label="Menú principal de opciones">

  <nav class="nav-group" role="navigation" aria-label="Navegación principal">


    <!-- =========================================
         PANEL PRINCIPAL
         ========================================= -->

    <a
      href="panel.php"
      class="nav-link <?php echo $pagina_actual === 'panel' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'panel' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
      </span>

      <span>Panel principal</span>
    </a>


    <!-- =========================================
         AGENDA
         ========================================= -->

    <a
      href="agenda.php"
      class="nav-link <?php echo $pagina_actual === 'agenda' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'agenda' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
          <line x1="16" y1="2" x2="16" y2="6"></line>
          <line x1="8" y1="2" x2="8" y2="6"></line>
          <line x1="3" y1="10" x2="21" y2="10"></line>
        </svg>
      </span>

      <span>Agenda</span>
    </a>


    <!-- =========================================
         HORARIOS
         Administrador / Superadmin
         ========================================= -->

    <?php if ($es_admin): ?>

    <a
      href="horarios.php"
      class="nav-link <?php echo $pagina_actual === 'horarios' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'horarios' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
      </span>

      <span>Horarios</span>
    </a>

    <?php endif; ?>


    <!-- =========================================
         VER HORARIOS
         ========================================= -->

    <a
      href="ver_horarios.php"
      class="nav-link <?php echo $pagina_actual === 'ver_horarios' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'ver_horarios' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </svg>
      </span>

      <span>Ver horarios</span>
    </a>


    <!-- =========================================
         RESERVAS
         ========================================= -->

    <a
      href="gestionar_reserva.php"
      class="nav-link <?php echo $pagina_actual === 'reservas' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'reservas' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
        </svg>
      </span>

      <span>Reservas</span>
    </a>


    <!-- =========================================
         ADMINISTRACIÓN
         Administrador / Superadmin
         ========================================= -->

    <?php if ($es_admin): ?>


    <!-- FUNCIONARIOS -->

    <a
      href="gestionar_funcionarios.php"
      class="nav-link <?php echo $pagina_actual === 'funcionarios' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'funcionarios' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      </span>

      <span>Funcionarios</span>
    </a>


    <!-- =========================================
         DEPARTAMENTOS Y SERVICIOS
         Solo Superadmin
         ========================================= -->

    <?php if ($es_superadmin): ?>

    <a
      href="gestionar_estructura.php"
      class="nav-link <?php echo $pagina_actual === 'estructura' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'estructura' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
         ADMINISTRACIÓN DEL SITIO WEB
         Administrador / Superadmin
         ========================================= -->

    <a
      href="administrar_web.php"
      class="nav-link <?php echo $pagina_actual === 'administrar_web' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'administrar_web' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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


    <!-- =========================================
         ADMINISTRAR NOTICIAS
         ========================================= -->

    <a
      href="administrar_noticias.php"
      class="nav-link <?php echo $pagina_actual === 'administrar_noticias' ? 'active' : ''; ?>"
      <?php echo $pagina_actual === 'administrar_noticias' ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 4h16v16H4z"></path>
          <path d="M8 8h8"></path>
          <path d="M8 12h8"></path>
          <path d="M8 16h5"></path>
        </svg>
      </span>

      <span>Administrar noticias</span>
    </a>


    <!-- =========================================
         BIBLIOTECA MULTIMEDIA
         Solo aparece si la página existe.
         ========================================= -->

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
      href="<?php echo htmlspecialchars(
          $paginaMultimedia,
          ENT_QUOTES | ENT_SUBSTITUTE,
          'UTF-8'
      ); ?>"
      class="nav-link <?php echo $paginaActivaMultimedia ? 'active' : ''; ?>"
      <?php echo $paginaActivaMultimedia ? 'aria-current="page"' : ''; ?>
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
         ========================================= -->

    <a
      href="../publicos/index.php"
      class="nav-link"
      target="_blank"
      rel="noopener noreferrer"
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
       ========================================= -->

  <div class="sidebar-footer">

    <a
      href="../api/logout_funcionario.php"
      class="nav-link-logout"
      id="btnSidebarLogout"
      title="Cerrar sesión"
      aria-label="Cerrar sesión"
    >
      <span class="nav-icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
      </span>

      <span>Cerrar sesión</span>
    </a>

  </div>

</aside>
