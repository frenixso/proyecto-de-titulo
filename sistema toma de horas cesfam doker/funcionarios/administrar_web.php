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
|   ../css/administracion_web/administrar_web.css
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


                </div>
                <!-- FIN quick-access-grid -->

            </section>
            <!-- FIN quick-access-section -->


            <!-- =============================================
                 4. FOOTER
                 ============================================= -->

            <?php
            require_once '../componentes/footer_funcionario.php';
            ?>


        </main>
        <!-- FIN main-content -->


    </div>
    <!-- FIN app-container -->


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
