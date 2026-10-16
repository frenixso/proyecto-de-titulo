
<?php

/*
|--------------------------------------------------------------------------
| BIBLIOTECA MULTIMEDIA DEL CESFAM
|--------------------------------------------------------------------------
|
| Archivo: funcionarios/biblioteca_multimedia.php
|
| Funciones de esta página:
| - Mostrar la interfaz de la biblioteca compartida.
| - Permitir seleccionar imágenes para subirlas.
| - Mostrar el resultado de las operaciones.
| - Presentar las imágenes registradas en PostgreSQL.
|
| La carga y el listado se conectarán con:
| ../api/web_multimedia.php
|
| Hoja de estilos propia:
| ../css/biblioteca_multimedia.css
|
| JavaScript propio:
| ../js/biblioteca_multimedia.js
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

$pagina_actual = 'biblioteca_multimedia';


/* =========================================================
   DATOS PARA PRESENTACIÓN
   ========================================================= */

$nombreMostrar =
    htmlspecialchars(
        $nombreCompleto,
        ENT_QUOTES,
        'UTF-8'
    );

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
        Biblioteca multimedia | CESFAM Catemu
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
         ESTILOS EXCLUSIVOS DE LA BIBLIOTECA MULTIMEDIA
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/administracion_web/biblioteca_multimedia.css"
    >

</head>


<body class="bibliotecaweb-page">


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
                class="biblioteca-header"
                aria-labelledby="bibliotecaTitulo"
            >

                <div class="biblioteca-header-info">

                    <span class="biblioteca-kicker">
                        Administración de contenidos
                    </span>

                    <h1
                        class="biblioteca-title"
                        id="bibliotecaTitulo"
                    >
                        Biblioteca multimedia
                    </h1>

                    <p class="biblioteca-subtitle">
                        Hola, <?= $nombreMostrar ?>.
                        Administra y reutiliza las imágenes
                        del sitio público del CESFAM desde
                        un único lugar.
                    </p>

                </div>


                <a
                    href="administrar_web.php"
                    class="biblioteca-back-link"
                >

                    <span aria-hidden="true">
                        ←
                    </span>

                    Volver a administración web

                </a>

            </section>


            <!-- =============================================
                 INFORMACIÓN DE LA BIBLIOTECA
                 ============================================= -->

            <section
                class="biblioteca-resumen"
                aria-label="Información de la biblioteca"
            >

                <article class="biblioteca-resumen-card">

                    <div
                        class="biblioteca-resumen-icon"
                        aria-hidden="true"
                    >
                        🗂️
                    </div>

                    <div class="biblioteca-resumen-info">

                        <span class="biblioteca-resumen-titulo">
                            Biblioteca compartida
                        </span>

                        <p>
                            Una misma imagen podrá reutilizarse
                            en noticias, slider, banners e
                            información institucional.
                        </p>

                    </div>

                </article>


                <article class="biblioteca-resumen-card">

                    <div
                        class="biblioteca-resumen-icon biblioteca-resumen-icon-secundario"
                        aria-hidden="true"
                    >
                        🛡️
                    </div>

                    <div class="biblioteca-resumen-info">

                        <span class="biblioteca-resumen-titulo">
                            Subida controlada
                        </span>

                        <p>
                            Se aceptarán imágenes JPEG, PNG,
                            WebP y GIF de hasta 5 MB por archivo.
                        </p>

                    </div>

                </article>

            </section>


            <!-- =============================================
                 PANEL DE SUBIDA
                 ============================================= -->

            <section
                class="biblioteca-panel"
                aria-labelledby="subirImagenTitulo"
            >

                <div class="biblioteca-panel-header">

                    <div>

                        <span class="biblioteca-panel-kicker">
                            Incorporar contenido
                        </span>

                        <h2
                            class="biblioteca-panel-title"
                            id="subirImagenTitulo"
                        >
                            Subir una imagen
                        </h2>

                    </div>

                </div>


                <form
                    id="formSubirImagen"
                    enctype="multipart/form-data"
                    novalidate
                >

                    <div class="biblioteca-subida-area">

                        <div
                            class="biblioteca-subida-icon"
                            aria-hidden="true"
                        >
                            🖼️
                        </div>


                        <div class="biblioteca-subida-texto">

                            <label
                                for="archivoMultimedia"
                                class="biblioteca-subida-label"
                            >
                                Selecciona una imagen de tu equipo
                            </label>

                            <p>
                                Formatos permitidos: JPEG, PNG,
                                WebP y GIF. Tamaño máximo: 5 MB.
                            </p>

                        </div>


                        <input
                            type="file"
                            id="archivoMultimedia"
                            name="archivo"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            required
                        >

                    </div>


                    <!-- Vista previa del archivo seleccionado -->

                    <div
                        class="biblioteca-vista-previa"
                        id="vistaPreviaMultimedia"
                        hidden
                    >

                        <img
                            id="imagenPreviaMultimedia"
                            src=""
                            alt="Vista previa de la imagen seleccionada"
                        >

                        <div class="biblioteca-vista-previa-info">

                            <strong id="nombrePreviaMultimedia">
                                Imagen seleccionada
                            </strong>

                            <span id="tamanoPreviaMultimedia"></span>

                        </div>

                        <button
                            type="button"
                            class="biblioteca-quitar-seleccion"
                            id="btnQuitarImagen"
                        >
                            Quitar selección
                        </button>

                    </div>


                    <!-- Acciones del formulario -->

                    <div class="biblioteca-subida-acciones">

                        <button
                            type="submit"
                            class="biblioteca-button biblioteca-button-primary"
                            id="btnSubirImagen"
                        >

                            Subir imagen

                        </button>

                    </div>


                    <!-- Mensajes de operación -->

                    <div
                        class="biblioteca-mensaje"
                        id="mensajeMultimedia"
                        role="status"
                        aria-live="polite"
                        hidden
                    ></div>

                </form>

            </section>


            <!-- =============================================
                 LISTADO DE IMÁGENES
                 ============================================= -->

            <section
                class="biblioteca-panel biblioteca-listado-panel"
                aria-labelledby="imagenesTitulo"
            >

                <div class="biblioteca-panel-header biblioteca-listado-header">

                    <div>

                        <span class="biblioteca-panel-kicker">
                            Archivos almacenados
                        </span>

                        <h2
                            class="biblioteca-panel-title"
                            id="imagenesTitulo"
                        >
                            Imágenes de la biblioteca
                        </h2>

                        <p class="biblioteca-listado-descripcion">
                            Aquí aparecerán las imágenes
                            registradas en PostgreSQL.
                        </p>

                    </div>


                    <button
                        type="button"
                        class="biblioteca-button biblioteca-button-secondary"
                        id="btnActualizarBiblioteca"
                    >

                        Actualizar listado

                    </button>

                </div>


                <!-- Estado de carga -->

                <div
                    class="biblioteca-estado"
                    id="estadoCargaMultimedia"
                    role="status"
                    aria-live="polite"
                >

                    Cargando imágenes...

                </div>


                <!-- Listado generado por JavaScript -->

                <div
                    class="biblioteca-grid"
                    id="listaMultimedia"
                    aria-live="polite"
                ></div>


                <!-- Mensaje de error independiente -->

                <div
                    class="biblioteca-mensaje"
                    id="mensajeListadoMultimedia"
                    role="status"
                    aria-live="polite"
                    hidden
                ></div>

            </section>


            <!-- =============================================
                 NOTA DE SEGURIDAD
                 ============================================= -->

            <section
                class="biblioteca-nota"
                aria-label="Información importante"
            >

                <span
                    class="biblioteca-nota-icon"
                    aria-hidden="true"
                >
                    ℹ️
                </span>

                <p>
                    Las imágenes no se eliminarán automáticamente.
                    Antes de permitir eliminaciones, se comprobará
                    si algún contenido de la página pública
                    utiliza el archivo.
                </p>

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
         JAVASCRIPT EXCLUSIVO DE LA BIBLIOTECA MULTIMEDIA
         ===================================================== -->

    <script
        src="../js/biblioteca_multimedia.js"
        defer
    ></script>


</body>

</html>
