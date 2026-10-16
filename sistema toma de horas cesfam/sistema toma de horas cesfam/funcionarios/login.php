
<?php
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Acceso Funcionarios - CESFAM</title>

    <link
        rel="stylesheet"
        href="../css/general.css"
    >

    <link
        rel="stylesheet"
        href="../css/navbar_funcionario.css"
    >

    <link
        rel="stylesheet"
        href="../css/login.css"
    >

</head>

<body>

    <!-- =====================================================
         BARRA SUPERIOR
         ===================================================== -->

    <header class="navbar-funcionario">

        <div class="navbar-funcionario-container">

            <!-- Logo / Nombre del Portal -->
            <a
                href="../publicos/index.php"
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
                    CESFAM
                </span>

            </a>

        </div>

    </header>


    <!-- =====================================================
         CONTENIDO PRINCIPAL
         ===================================================== -->

    <main class="login-page">

        <div class="login-container">

            <section class="login-card">

                <div class="login-header">

                    <div class="login-icon">
                        🔐
                    </div>

                    <h1>
                        Acceso de funcionarios
                    </h1>

                    <p>
                        Ingresa tus credenciales para acceder
                        al panel administrativo.
                    </p>

                </div>


                <!-- =================================================
                     FORMULARIO
                     ================================================= -->

                <form
                    class="login-form"
                    id="login-form"
                    method="POST"
                    autocomplete="off"
                    novalidate
                >

                    <!-- USUARIO -->

                    <div class="form-group">

                        <label for="usuario">
                            Usuario
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                👤
                            </span>

                            <input
                                type="text"
                                id="usuario"
                                name="usuario"
                                placeholder="Ingresa tu usuario"
                                autocomplete="off"
                                maxlength="50"
                                required
                            >

                        </div>

                    </div>


                    <!-- CONTRASEÑA -->

                    <div class="form-group">

                        <label for="password">
                            Contraseña
                        </label>

                        <div class="input-wrapper">

                            <span class="input-icon">
                                🔒
                            </span>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Ingresa tu contraseña"
                                autocomplete="off"
                                required
                            >

                            <button
                                type="button"
                                class="password-toggle"
                                id="password-toggle"
                                aria-label="Mostrar contraseña"
                            >
                                👁
                            </button>

                        </div>

                    </div>


                    <!-- MENSAJE -->

                    <div
                        class="login-message error"
                        id="login-message"
                        style="display: none;"
                    >
                    </div>


                    <!-- BOTÓN -->

                    <button
                        type="submit"
                        class="login-button"
                        id="login-button"
                    >
                        Iniciar sesión
                    </button>

                </form>


                <!-- =================================================
                     VOLVER AL SITIO
                     ================================================= -->

                <div class="login-footer">

                    <a href="../publicos/index.php">
                        ← Volver al sitio público
                    </a>

                </div>

            </section>

        </div>

    </main>


    <!-- =====================================================
         JAVASCRIPT
         ===================================================== -->

    <script
        src="../js/login.js"
    ></script>

</body>

</html>

