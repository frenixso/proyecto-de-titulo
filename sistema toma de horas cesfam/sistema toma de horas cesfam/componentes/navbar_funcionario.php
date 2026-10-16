
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($base_url)) {
    $base_url = '..';
}

// Datos del usuario desde la sesión
$nombreUsuario = $_SESSION['nombre_funcionario'] ?? 'Funcionario';
$rolUsuario    = $_SESSION['rol_funcionario']    ?? 'funcionario';

if ($rolUsuario === 'superadmin') {
    $rolTexto = 'Superadministrador';
} elseif ($rolUsuario === 'administrador') {
    $rolTexto = 'Administrador';
} else {
    $rolTexto = 'Funcionario';
}

$inicialAvatar = !empty($nombreUsuario)
    ? mb_strtoupper(mb_substr($nombreUsuario, 0, 1, 'UTF-8'))
    : 'F';

// Consulta de notificaciones no leídas
$tieneNotificaciones = false;

if (isset($conexion) && isset($_SESSION['usuario_funcionario'])) {
    try {
        $stmtNotif = $conexion->prepare(
            "SELECT COUNT(*)
             FROM notificaciones
             WHERE usuario = :u
             AND leida = 0"
        );

        $stmtNotif->execute([
            ':u' => $_SESSION['usuario_funcionario']
        ]);

        $tieneNotificaciones = ($stmtNotif->fetchColumn() > 0);

    } catch (Exception $e) {
        $tieneNotificaciones = false;
    }
}
?>

<header class="navbar-funcionario">

  <div class="navbar-funcionario-container">

    <!-- Logo / Nombre del Portal -->
    <a
      href="<?php echo $base_url; ?>/funcionarios/panel.php"
      class="navbar-funcionario-brand"
    >

      <div class="navbar-funcionario-logo">

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
          <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
        </svg>

      </div>

      <span class="navbar-funcionario-title">
        CESFAM Funcionario
      </span>

    </a>


    <!-- Acciones Derecha -->
    <div class="navbar-funcionario-actions">

      <!-- Botón Notificaciones -->
      <button
        type="button"
        class="btn-notification-nav"
        id="btnNotificacionesNav"
        aria-label="Notificaciones"
      >

        <svg
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>

        <?php if ($tieneNotificaciones): ?>

          <span class="notification-badge-dot"></span>

        <?php endif; ?>

      </button>


      <!-- Menú Usuario -->
      <div class="navbar-funcionario-user">

        <button
          type="button"
          class="user-button-nav"
          id="userMenuBtn"
        >

          <div class="user-avatar-circle">

            <span>
              <?php
              echo htmlspecialchars(
                  $inicialAvatar,
                  ENT_QUOTES,
                  'UTF-8'
              );
              ?>
            </span>

          </div>

          <span class="user-role-label">
            <?php
            echo htmlspecialchars(
                $rolTexto,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
          </span>

          <svg
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <path d="M6 9l6 6 6-6"/>
          </svg>

        </button>


        <!-- Dropdown Desplegable -->
        <div
          class="user-dropdown-menu"
          id="userDropdown"
        >

          <a
            href="<?php echo $base_url; ?>/funcionarios/perfil.php"
            class="dropdown-item-link"
          >

            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>

            <span>Mi Perfil</span>

          </a>


          <a
            href="<?php echo $base_url; ?>/publicos/index.php"
            class="dropdown-item-link"
          >

            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="2" y1="12" x2="22" y2="12"></line>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>

            <span>Sitio Público</span>

          </a>


          <div class="dropdown-divider-line"></div>


          <a
            href="<?php echo $base_url; ?>/api/logout_funcionario.php"
            class="dropdown-item-link text-danger"
          >

            <svg
              width="18"
              height="18"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>

            <span>Cerrar Sesión</span>

          </a>

        </div>

      </div>

    </div>

  </div>

</header>

