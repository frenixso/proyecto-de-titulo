<?php

/*
|--------------------------------------------------------------------------
| ADMINISTRACIÓN DE LA PÁGINA PÚBLICA DEL CESFAM
|--------------------------------------------------------------------------
|
| Panel inicial del módulo de administración web.
|
| - Utiliza la sesión existente de funcionarios.
| - Solo permite entrar a superadmin o funcionarios
|   que tengan el permiso puede_administrar_web.
| - No modifica ni reemplaza la toma de horas.
| - Los estilos particulares se encuentran en:
|   ../css/administrar_web.css
|
|--------------------------------------------------------------------------
*/

require_once '../componentes/auth_administracion_web.php';


/* =========================================================
   DATOS DEL FUNCIONARIO
   ========================================================= */

$nombreFuncionario =
    $_SESSION['nombre_funcionario'] ?? 'Funcionario';

$apellidoFuncionario =
    $_SESSION['apellido_funcionario'] ?? '';

$usuarioFuncionario =
    $_SESSION['usuario_funcionario'] ?? '';

$rolFuncionario =
    $_SESSION['rol_funcionario'] ?? 'funcionario';


$nombreCompleto =
    trim(
        $nombreFuncionario . ' ' . $apellidoFuncionario
    );


/* =========================================================
   CONFIGURACIÓN DE COMPONENTES
   ========================================================= */

$base_url = '..';

$nombre_funcionario = $nombreCompleto;

$rol_funcionario = $rolFuncionario;

$pagina_actual = 'administrar_web';


/* =========================================================
   DATOS PARA PRESENTACIÓN
   ========================================================= */

$nombreMostrar =
    htmlspecialchars(
        $nombreCompleto,
        ENT_QUOTES,
        'UTF-8'
    );

$esSuperadmin =
    $rolFuncionario === 'superadmin';

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
        Administración de la página pública | CESFAM Catemu
    </title>


    <!-- =====================================================
         ESTILOS DE COMPONENTES COMPARTIDOS
         ===================================================== -->

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


    <!-- =====================================================
         ESTILOS EXCLUSIVOS DE ESTE MÓDULO
         No depende de css/panel.css
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/administracion_web/administrar_web.css"
    >

</head>


<body class="adminweb-page">

    <!-- =====================================================
         1. NAVBAR SUPERIOR
         ===================================================== -->

    <?php
    require_once '../componentes/navbar_funcionario.php';
    ?>


    <!-- =====================================================
         CONTENEDOR PRINCIPAL
         ===================================================== -->

    <div class="app-container">


        <!-- =================================================
             2. MENÚ LATERAL
             ================================================= -->

        <?php
        require_once '../componentes/sidebar_funcionario.php';
        ?>


        <!-- =================================================
             3. CONTENIDO PRINCIPAL
             ================================================= -->

        <main
            class="main-content"
            id="mainContent"
            role="main"
        >


            <!-- =============================================
                 ENCABEZADO
                 ============================================= -->

            <section
                class="welcome-header"
                aria-labelledby="welcomeTitle"
            >

                <h1
                    class="welcome-title"
                    id="welcomeTitle"
                >
                    Administración de la página pública
                </h1>

                <p class="welcome-subtitle">
                    Hola, <?= $nombreMostrar ?>.
                    Desde aquí podrás administrar los contenidos
                    informativos del sitio público del CESFAM.
                </p>

            </section>


            <!-- =============================================
                 MÓDULOS DE GESTIÓN DE CONTENIDOS
                 ============================================= -->

            <section
                class="quick-access-section"
                aria-labelledby="moduleTitle"
            >

                <div class="section-header">

                    <h2
                        class="section-title"
                        id="moduleTitle"
                    >
                        Gestión de contenidos
                    </h2>

                </div>


                <p>
                    Este módulo utiliza la sesión actual
                    de funcionarios y tiene permisos
                    independientes de la toma de horas.
                    Las herramientas se incorporarán
                    progresivamente.
                </p>


                <div class="quick-access-grid">


                    <!-- =====================================
                         NOTICIAS
                         ===================================== -->

                    <article class="quick-card">

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >
                            📰
                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Noticias
                            </span>

                            <span class="quick-card-desc">
                                Crear, editar, destacar, publicar
                                y ocultar noticias institucionales.
                            </span>

                        </div>

                    </article>


                    <!-- =====================================
                         SLIDER PRINCIPAL
                         ===================================== -->

                    <article class="quick-card">

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >
                            🖼️
                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Slider principal
                            </span>

                            <span class="quick-card-desc">
                                Administrar diapositivas, enlaces,
                                orden y fechas de vigencia.
                            </span>

                        </div>

                    </article>


                    <!-- =====================================
                         BANNERS
                         ===================================== -->

                    <article class="quick-card">

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >
                            📢
                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Banners institucionales
                            </span>

                            <span class="quick-card-desc">
                                Gestionar banners, enlaces y
                                períodos de publicación.
                            </span>

                        </div>

                    </article>


                    <!-- =====================================
                         TUTORIALES
                         ===================================== -->

                    <article class="quick-card">

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >
                            🎓
                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Tutoriales y categorías
                            </span>

                            <span class="quick-card-desc">
                                Organizar tutoriales, categorías
                                y enlaces de vídeos.
                            </span>

                        </div>

                    </article>


                    <!-- =====================================
                         INFORMACIÓN INSTITUCIONAL
                         ===================================== -->

                    <article class="quick-card">

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >
                            🏥
                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Información institucional
                            </span>

                            <span class="quick-card-desc">
                                Administrar contacto, horarios,
                                servicios, equipo, historia y ubicación.
                            </span>

                        </div>

                    </article>


                    <!-- =====================================
                         BIBLIOTECA MULTIMEDIA
                         ===================================== -->

                    <article class="quick-card">

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >
                            🗂️
                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Biblioteca multimedia
                            </span>

                            <span class="quick-card-desc">
                                Centralizar y reutilizar imágenes
                                en los contenidos públicos.
                            </span>

                        </div>

                    </article>


                    <!-- =====================================
                         PAPELERA
                         ===================================== -->

                    <article class="quick-card">

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >
                            🗑️
                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Papelera
                            </span>

                            <span class="quick-card-desc">
                                Recuperar contenido eliminado
                                o confirmar su eliminación definitiva.
                            </span>

                        </div>

                    </article>


                    <!-- =====================================
                         REGISTRO DE ACTIVIDAD
                         ===================================== -->

                    <article class="quick-card">

                        <div
                            class="quick-card-icon"
                            aria-hidden="true"
                        >
                            📋
                        </div>

                        <div class="quick-card-info">

                            <span class="quick-card-title">
                                Registro de actividad
                            </span>

                            <span class="quick-card-desc">
                                Consultar las acciones realizadas
                                por los administradores.
                            </span>

                        </div>

                    </article>


                </div>

            </section>


            <!-- =============================================
                 INFORMACIÓN DE PERMISOS
                 ============================================= -->

            <section
                class="appointment-section"
                aria-labelledby="accessTitle"
            >

                <div class="section-header">

                    <h2
                        class="section-title"
                        id="accessTitle"
                    >
                        Acceso y permisos
                    </h2>

                </div>


                <article class="appointment-card">

                    <div class="appointment-card-body">

                        <p>
                            <strong>Usuario:</strong>
                            <?= $nombreMostrar ?>
                        </p>

                        <p>
                            <strong>Rol:</strong>
                            <?= $esSuperadmin
                                ? 'Superadministrador'
                                : 'Funcionario autorizado para administrar la página pública' ?>
                        </p>

                        <?php if ($esSuperadmin): ?>

                            <p>
                                Tienes acceso completo a este módulo
                                y eres el único rol autorizado para
                                conceder o retirar permisos de
                                administración web.
                            </p>

                        <?php else: ?>

                            <p>
                                Tienes permiso para administrar
                                los contenidos públicos del CESFAM.
                                No se modifican tus permisos de
                                toma de horas.
                            </p>

                        <?php endif; ?>

                    </div>

                </article>

            </section>


            <!-- =============================================
                 ESTADO ACTUAL DEL MÓDULO
                 ============================================= -->

            <section
                class="appointment-section"
                aria-label="Estado del módulo"
            >

                <article class="appointment-card">

                    <div class="appointment-card-body">

                        <h3>
                            Panel inicial creado
                        </h3>

                        <p>
                            Esta es la vista inicial del módulo.
                            Las tarjetas son informativas por ahora;
                            sus herramientas de administración
                            se implementarán progresivamente.
                        </p>

                    </div>

                </article>

            </section>


            <!-- =============================================
                 4. FOOTER
                 ============================================= -->

            <?php
            require_once '../componentes/footer_funcionario.php';
            ?>


        </main>

    </div>


    <!-- =====================================================
         NAVEGACIÓN DEL MENÚ MÓVIL Y PERFIL
         ===================================================== -->

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                /* Toggle Sidebar móvil */

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
                        function () {

                            const isExpanded =
                                btnToggleSidebar.getAttribute(
                                    'aria-expanded'
                                ) === 'true';

                            btnToggleSidebar.setAttribute(
                                'aria-expanded',
                                String(!isExpanded)
                            );

                            sidebarNav.classList.toggle('active');

                            sidebarOverlay.classList.toggle('active');

                        }
                    );


                    sidebarOverlay.addEventListener(
                        'click',
                        function () {

                            btnToggleSidebar.setAttribute(
                                'aria-expanded',
                                'false'
                            );

                            sidebarNav.classList.remove('active');

                            sidebarOverlay.classList.remove('active');

                        }
                    );

                }


                /* Toggle menú de perfil */

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
                        function (evento) {

                            evento.stopPropagation();

                            const isVisible =
                                userDropdownMenu.style.display === 'block';

                            userDropdownMenu.style.display =
                                isVisible ? 'none' : 'block';

                        }
                    );


                    document.addEventListener(
                        'click',
                        function (evento) {

                            if (
                                !btnUserDropdown.contains(evento.target) &&
                                !userDropdownMenu.contains(evento.target)
                            ) {

                                userDropdownMenu.style.display = 'none';

                            }

                        }
                    );

                }

            }
        );

    </script>

</body>

</html>