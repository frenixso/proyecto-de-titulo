
<?php

/*
 * =========================================================
 * NAVBAR FUNCIONARIO — CESFAM EDUARDO RAGGIO CATEMU
 * =========================================================
 *
 * Incluye:
 * - Identificación del funcionario.
 * - Menú de usuario.
 * - Campanita de notificaciones.
 * - Panel desplegable de notificaciones.
 *
 * =========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($base_url)) {
    $base_url = '..';
}


/*
 * =========================================================
 * DATOS DEL USUARIO
 * =========================================================
 */

$nombreUsuario = $_SESSION['nombre_funcionario'] ?? 'Funcionario';
$rolUsuario    = $_SESSION['rol_funcionario'] ?? 'funcionario';

$idFuncionario = (int) ($_SESSION['id_funcionario'] ?? 0);


if ($rolUsuario === 'superadmin') {

    $rolTexto = 'Superadministrador';

} elseif ($rolUsuario === 'administrador') {

    $rolTexto = 'Administrador';

} else {

    $rolTexto = 'Funcionario';
}


$inicialAvatar = !empty($nombreUsuario)
    ? mb_strtoupper(
        mb_substr($nombreUsuario, 0, 1, 'UTF-8')
    )
    : 'F';


/*
 * =========================================================
 * CONTADOR DE NOTIFICACIONES NO LEÍDAS
 * =========================================================
 *
 * Cada funcionario consulta exclusivamente
 * sus propias notificaciones.
 *
 * =========================================================
 */

$cantidadNotificaciones = 0;

if (
    isset($conexion) &&
    $idFuncionario > 0
) {

    try {

        $sqlNotif = "
            SELECT COUNT(*)
            FROM notificaciones
            WHERE id_funcionario = :id_funcionario
              AND leida = FALSE
        ";

        $stmtNotif = $conexion->prepare($sqlNotif);

        $stmtNotif->execute([
            ':id_funcionario' => $idFuncionario
        ]);

        $cantidadNotificaciones = (int) $stmtNotif->fetchColumn();

    } catch (Throwable $e) {

        error_log(
            'Error al obtener contador de notificaciones: ' .
            $e->getMessage()
        );

        $cantidadNotificaciones = 0;
    }
}

?>

<header class="navbar-funcionario">

    <div class="navbar-funcionario-container">

        <!-- =================================================
             LOGO / NOMBRE DEL PORTAL
        ================================================== -->

        <a
            href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/funcionarios/panel.php"
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


        <!-- =================================================
             ACCIONES DERECHA
        ================================================== -->

        <div class="navbar-funcionario-actions">


            <!-- =============================================
                 NOTIFICACIONES
            ============================================== -->

            <div class="navbar-funcionario-notificaciones">

                <!-- BOTÓN CAMPANITA -->

                <button
                    type="button"
                    class="btn-notification-nav"
                    id="btnNotificaciones"
                    aria-label="Notificaciones"
                    aria-expanded="false"
                    aria-controls="notificacionesPanel"
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


                    <!-- CONTADOR -->

                    <span
                        class="notification-badge"
                        id="notificationBadge"
                        <?php if ($cantidadNotificaciones <= 0): ?>
                            style="display: none;"
                        <?php endif; ?>
                    >
                        <?php
                        echo $cantidadNotificaciones > 99
                            ? '99+'
                            : $cantidadNotificaciones;
                        ?>
                    </span>

                </button>


                <!-- =========================================
                     PANEL DESPLEGABLE
                ========================================== -->

                <div
                    class="notificaciones-panel"
                    id="notificacionesPanel"
                    aria-hidden="true"
                >

                    <!-- ENCABEZADO -->

                    <div class="notificaciones-panel-header">

                        <div class="notificaciones-panel-titulo">

                            <strong>
                                Notificaciones
                            </strong>

                            <span id="notificacionesCantidadTexto">
                                <?php
                                if ($cantidadNotificaciones === 0) {

                                    echo 'Sin notificaciones nuevas';

                                } elseif ($cantidadNotificaciones === 1) {

                                    echo '1 notificación nueva';

                                } else {

                                    echo $cantidadNotificaciones .
                                        ' notificaciones nuevas';
                                }
                                ?>
                            </span>

                        </div>


                        <!-- MARCAR TODAS COMO LEÍDAS -->

                        <button
                            type="button"
                            id="btnMarcarTodasLeidas"
                            class="btn-marcar-todas-notificaciones"
                        >
                            Marcar todas como leídas
                        </button>

                    </div>


                    <!-- LISTA DE NOTIFICACIONES -->

                    <div
                        class="notificaciones-lista"
                        id="notificacionesLista"
                        aria-live="polite"
                    >

                        <div class="notificaciones-cargando">
                            Cargando notificaciones...
                        </div>

                    </div>

                </div>

            </div>


            <!-- =============================================
                 MENÚ USUARIO
            ============================================== -->

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


                <!-- MENÚ DESPLEGABLE -->

                <div
                    class="user-dropdown-menu"
                    id="userDropdown"
                >

                    <a
                        href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/funcionarios/perfil.php"
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
                        href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/publicos/index.php"
                        class="dropdown-item-link"
                    >

                        <svg
                            width="18"
                            height="18"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <circle cx="12" cy="12" r="10"></circle>

                            <line x1="2" y1="12" x2="22" y2="12"></line>

                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                        </svg>

                        <span>Sitio Público</span>

                    </a>


                    <div class="dropdown-divider-line"></div>


                    <a
                        href="<?php echo htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8'); ?>/api/logout_funcionario.php"
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
