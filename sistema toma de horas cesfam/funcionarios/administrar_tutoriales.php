<?php
/*
 * =========================================================
 * ADMINISTRACIÓN DE TUTORIALES
 * CESFAM EDUARDO RAGGIO CATEMU
 *
 * Archivo: funcionarios/administrar_tutoriales.php
 *
 * Funciones:
 *   - Crear y editar tutoriales.
 *   - Subir videos desde el computador.
 *   - Incorporar videos de YouTube mediante código iframe.
 *   - Asignar varias categorías a un tutorial.
 *   - Buscar, filtrar y paginar resultados.
 *   - Publicar, desactivar y guardar borradores.
 *   - Administrar categorías.
 *   - Recuperar tutoriales desde la papelera.
 *   - Eliminar definitivamente desde la papelera.
 *
 * Seguridad:
 *   - La API valida los datos enviados.
 *   - El código iframe no se inserta directamente en la página.
 *   - La API extrae y almacena únicamente el ID de YouTube.
 * =========================================================
 */

require_once __DIR__ . '/../componentes/auth_administracion_web.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrar tutoriales | CESFAM</title>

    <link rel="stylesheet" href="../css/compartidos/general.css">
    <link rel="stylesheet" href="../css/compartidos/navbar_funcionario.css">
    <link rel="stylesheet" href="../css/compartidos/sidebar_funcionario.css">
    <link rel="stylesheet" href="../css/compartidos/footer_funcionario.css">

    <link rel="stylesheet" href="../css/administracion_web/administrar_tutoriales.css">
</head>

<body class="administrar-tutoriales-page">

    <?php require_once __DIR__ . '/../componentes/navbar_funcionario.php'; ?>

    <div class="tutoriales-layout">

        <?php require_once __DIR__ . '/../componentes/sidebar_funcionario.php'; ?>

        <main class="tutoriales-contenido">

            <!-- =================================================
                 ENCABEZADO
                 ================================================= -->

            <header class="tutoriales-encabezado">

                <div class="tutoriales-encabezado-texto">

                    <h1>Administración de tutoriales</h1>

                    <p>
                        Crea y administra los videos educativos que se mostrarán
                        en la página pública del CESFAM.
                    </p>

                </div>

                <div class="tutoriales-encabezado-acciones">

                    <button
                        type="button"
                        id="btnAdministrarCategorias"
                        class="btn-tutoriales-secundario"
                    >
                        <span aria-hidden="true">▤</span>
                        Administrar categorías
                    </button>

                    <button
                        type="button"
                        id="btnNuevoTutorial"
                        class="btn-tutoriales-principal"
                    >
                        <span aria-hidden="true">＋</span>
                        Nuevo tutorial
                    </button>

                </div>

            </header>

            <!-- =================================================
                 MENSAJES
                 ================================================= -->

            <div
                id="mensajeTutoriales"
                class="tutoriales-mensaje"
                role="status"
                aria-live="polite"
                hidden
            ></div>

            <!-- =================================================
                 INFORMACIÓN SOBRE LOS MÉTODOS DE VIDEO
                 ================================================= -->

            <section
                class="tutoriales-introduccion"
                aria-label="Información sobre los videos"
            >

                <div class="tutoriales-introduccion-item">

                    <span
                        class="tutoriales-introduccion-icono"
                        aria-hidden="true"
                    >
                        ▶
                    </span>

                    <div>
                        <h2>Videos del servidor</h2>

                        <p>
                            Sube archivos MP4, WebM u OGG compatibles.
                            El archivo se almacenará en el servidor.
                        </p>
                    </div>

                </div>

                <div
                    class="tutoriales-introduccion-separador"
                    aria-hidden="true"
                ></div>

                <div class="tutoriales-introduccion-item">

                    <span
                        class="tutoriales-introduccion-icono youtube"
                        aria-hidden="true"
                    >
                        ▷
                    </span>

                    <div>
                        <h2>Videos de YouTube</h2>

                        <p>
                            Copia el código iframe desde YouTube, en
                            Compartir y después Incorporar. El sistema
                            extraerá el identificador y utilizará el
                            reproductor integrado.
                        </p>
                    </div>

                </div>

            </section>

            <!-- =================================================
                 FILTROS
                 ================================================= -->

            <section
                class="tutoriales-panel"
                aria-labelledby="tituloFiltrosTutoriales"
            >

                <div class="tutoriales-panel-encabezado">

                    <div>
                        <h2 id="tituloFiltrosTutoriales">
                            Buscar tutoriales
                        </h2>

                        <p>
                            Filtra por título, tipo de video, categoría o estado.
                        </p>
                    </div>

                    <button
                        type="button"
                        id="btnActualizarTutoriales"
                        class="btn-tutoriales-secundario"
                    >
                        Actualizar
                    </button>

                </div>

                <div class="tutoriales-filtros">

                    <div class="tutoriales-campo tutoriales-busqueda">

                        <label for="filtroBusquedaTutoriales">
                            Buscar por texto
                        </label>

                        <input
                            type="search"
                            id="filtroBusquedaTutoriales"
                            placeholder="Escribe el título o la descripción..."
                            maxlength="180"
                            autocomplete="off"
                        >

                    </div>

                    <div class="tutoriales-campo">

                        <label for="filtroEstadoTutoriales">
                            Estado
                        </label>

                        <select id="filtroEstadoTutoriales">
                            <option value="todas">Todos los estados</option>
                            <option value="borrador">Borradores</option>
                            <option value="publicada">Publicados</option>
                            <option value="inactiva">Inactivos</option>
                            <option value="papelera">Papelera</option>
                        </select>

                    </div>

                    <div class="tutoriales-campo">

                        <label for="filtroTipoTutoriales">
                            Tipo de video
                        </label>

                        <select id="filtroTipoTutoriales">
                            <option value="todos">Todos los tipos</option>
                            <option value="archivo">Video del servidor</option>
                            <option value="youtube">YouTube</option>
                        </select>

                    </div>

                    <div class="tutoriales-campo">

                        <label for="filtroCategoriaTutoriales">
                            Categoría
                        </label>

                        <select id="filtroCategoriaTutoriales">
                            <option value="todas">Todas las categorías</option>
                        </select>

                    </div>

                    <div class="tutoriales-campo">

                        <label for="filtroCantidadTutoriales">
                            Resultados por página
                        </label>

                        <select id="filtroCantidadTutoriales">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>

                    </div>

                </div>

            </section>

            <!-- =================================================
                 LISTADO DE TUTORIALES
                 ================================================= -->

            <section
                class="tutoriales-panel"
                aria-labelledby="tituloListadoTutoriales"
            >

                <div class="tutoriales-panel-encabezado">

                    <div>
                        <h2 id="tituloListadoTutoriales">
                            Tutoriales registrados
                        </h2>

                        <p id="resumenListadoTutoriales">
                            Cargando tutoriales...
                        </p>
                    </div>

                </div>

                <div
                    id="cargandoTutoriales"
                    class="tutoriales-cargando"
                    hidden
                >
                    Cargando tutoriales...
                </div>

                <div class="tutoriales-tabla-contenedor">

                    <table class="tutoriales-tabla">

                        <thead>
                            <tr>
                                <th scope="col">Video</th>
                                <th scope="col">Tutorial</th>
                                <th scope="col">Tipo</th>
                                <th scope="col">Categorías</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="tablaTutorialesBody">

                            <tr>
                                <td
                                    colspan="7"
                                    class="tutoriales-tabla-vacia"
                                >
                                    Cargando tutoriales...
                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

                <!-- Paginación -->

                <div
                    class="tutoriales-paginacion"
                    id="paginacionTutoriales"
                >

                    <button
                        type="button"
                        id="btnPaginaAnteriorTutoriales"
                        class="btn-tutoriales-secundario"
                        disabled
                    >
                        ← Anterior
                    </button>

                    <span id="textoPaginacionTutoriales">
                        Página 1
                    </span>

                    <button
                        type="button"
                        id="btnPaginaSiguienteTutoriales"
                        class="btn-tutoriales-secundario"
                        disabled
                    >
                        Siguiente →
                    </button>

                </div>

            </section>

        </main>

    </div>

    <!-- =====================================================
         MODAL: CREAR O EDITAR TUTORIAL
         ===================================================== -->

    <div
        id="modalTutorial"
        class="tutoriales-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalTutorial"
        hidden
    >

        <div
            class="tutoriales-modal-fondo"
            data-cerrar-modal-tutorial="true"
        ></div>

        <div class="tutoriales-modal-contenido">

            <header class="tutoriales-modal-encabezado">

                <div>
                    <h2 id="tituloModalTutorial">
                        Nuevo tutorial
                    </h2>

                    <p id="descripcionModalTutorial">
                        Completa los datos y selecciona cómo incorporar el video.
                    </p>
                </div>

                <button
                    type="button"
                    id="btnCerrarModalTutorial"
                    class="tutoriales-modal-cerrar"
                    aria-label="Cerrar formulario"
                >
                    ×
                </button>

            </header>

            <form
                id="formTutorial"
                autocomplete="off"
                enctype="multipart/form-data"
            >

                <input
                    type="hidden"
                    id="idTutorial"
                    name="id_tutorial"
                    value=""
                >

                <!-- =================================================
                     TÍTULO
                     ================================================= -->

                <div class="tutoriales-campo">

                    <label for="inputTituloTutorial">
                        Título <span aria-hidden="true">*</span>
                    </label>

                    <input
                        type="text"
                        id="inputTituloTutorial"
                        name="titulo"
                        maxlength="180"
                        placeholder="Título del tutorial"
                        required
                    >

                    <small>
                        Máximo 180 caracteres.
                    </small>

                </div>

                <!-- =================================================
                     DESCRIPCIÓN
                     ================================================= -->

                <div class="tutoriales-campo">

                    <label for="inputDescripcionTutorial">
                        Descripción
                    </label>

                    <textarea
                        id="inputDescripcionTutorial"
                        name="descripcion"
                        rows="4"
                        placeholder="Explica brevemente qué aprenderá el ciudadano..."
                    ></textarea>

                    <small>
                        Describe el contenido del video de manera clara.
                    </small>

                </div>

                <!-- =================================================
                     TIPO DE VIDEO
                     ================================================= -->

                <section class="tutoriales-seccion-video">

                    <div class="tutoriales-seccion-encabezado">

                        <h3>Origen del video</h3>

                        <p>
                            Selecciona una opción. Solo se utilizará el método elegido.
                        </p>

                    </div>

                    <div class="tutoriales-campo">

                        <label for="selectTipoVideoTutorial">
                            ¿Cómo quieres incorporar el video?
                        </label>

                        <select
                            id="selectTipoVideoTutorial"
                            name="tipo_video"
                            required
                        >
                            <option value="archivo">
                                Subir un video desde mi computador
                            </option>

                            <option value="youtube">
                                Incorporar un video de YouTube
                            </option>
                        </select>

                    </div>

                    <!-- =============================================
                         MÉTODO 1: ARCHIVO LOCAL
                         ============================================= -->

                    <div
                        id="bloqueArchivoVideo"
                        class="tutoriales-metodo-video"
                    >

                        <div class="tutoriales-metodo-video-encabezado">

                            <span
                                class="tutoriales-metodo-video-icono"
                                aria-hidden="true"
                            >
                                ⇧
                            </span>

                            <div>
                                <h4>Subir desde el computador</h4>

                                <p>
                                    El video se guardará en el servidor del CESFAM.
                                </p>
                            </div>

                        </div>

                        <div class="tutoriales-campo">

                            <label for="inputArchivoVideoTutorial">
                                Archivo de video
                            </label>

                            <input
                                type="file"
                                id="inputArchivoVideoTutorial"
                                name="archivo_video"
                                accept="video/mp4,video/webm,video/ogg,.mp4,.webm,.ogv,.ogg"
                                aria-describedby="ayudaArchivoVideoTutorial estadoCargaVideoTutorial"
                            >

                            <small id="ayudaArchivoVideoTutorial">
                                Formatos permitidos: MP4, WebM y OGG compatibles.
                                Tamaño máximo previsto: 100 MB.
                                Si estás editando un tutorial, puedes dejar el campo
                                vacío para conservar el archivo anterior.
                            </small>

                            <small
                                id="estadoCargaVideoTutorial"
                                class="tutoriales-estado-carga-video"
                                role="status"
                                aria-live="polite"
                            ></small>

                        </div>

                    </div>

                    <!-- =============================================
                         MÉTODO 2: YOUTUBE MEDIANTE IFRAME
                         ============================================= -->

                    <div
                        id="bloqueYoutubeVideo"
                        class="tutoriales-metodo-video"
                        hidden
                    >

                        <div class="tutoriales-metodo-video-encabezado">

                            <span
                                class="tutoriales-metodo-video-icono tutoriales-icono-youtube"
                                aria-hidden="true"
                            >
                                ▶
                            </span>

                            <div>
                                <h4>Incorporar desde YouTube</h4>

                                <p>
                                    Pega el código iframe completo que proporciona
                                    YouTube para incorporar el reproductor.
                                </p>
                            </div>

                        </div>

                        <div class="tutoriales-campo">

                            <label for="inputUrlYoutubeTutorial">
                                Código iframe de YouTube
                                <span aria-hidden="true">*</span>
                            </label>

                            <textarea
                                id="inputUrlYoutubeTutorial"
                                name="youtube_url"
                                rows="5"
                                maxlength="5000"
                                placeholder="Pega aquí el código iframe obtenido en YouTube → Compartir → Incorporar"
                                aria-describedby="ayudaUrlYoutubeTutorial"
                                spellcheck="false"
                            ></textarea>

                            <small id="ayudaUrlYoutubeTutorial">
                                En YouTube, abre el video, selecciona
                                Compartir y después Incorporar. Copia el
                                código iframe completo y pégalo aquí.
                                El sistema validará el código y extraerá
                                el identificador del video.
                            </small>

                        </div>

                    </div>

                    <!-- =============================================
                         VISTA PREVIA
                         ============================================= -->

                    <div
                        id="contenedorVistaPreviaVideoTutorial"
                        class="tutoriales-vista-previa-video"
                        hidden
                    >

                        <div class="tutoriales-vista-previa-video-encabezado">

                            <h4>Vista previa</h4>

                            <span id="tipoVistaPreviaVideoTutorial">
                                Video seleccionado
                            </span>

                        </div>

                        <div class="tutoriales-reproductor-contenedor">

                            <video
                                id="videoVistaPreviaTutorial"
                                controls
                                preload="metadata"
                                playsinline
                                hidden
                            >
                                Tu navegador no permite reproducir este video.
                            </video>

                            <iframe
                                id="iframeVistaPreviaTutorial"
                                title="Vista previa del tutorial de YouTube"
                                loading="lazy"
                                referrerpolicy="strict-origin-when-cross-origin"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                                hidden
                            ></iframe>

                            <div
                                id="mensajeVistaPreviaVideoTutorial"
                                class="tutoriales-vista-previa-vacia"
                                hidden
                            >
                                Selecciona un archivo o pega el código iframe
                                de YouTube para mostrar la vista previa.
                            </div>

                        </div>

                        <p
                            id="nombreVistaPreviaVideoTutorial"
                            class="tutoriales-nombre-video"
                        ></p>

                    </div>

                </section>

                <!-- =================================================
                     CATEGORÍAS
                     ================================================= -->

                <section class="tutoriales-seccion-categorias">

                    <div class="tutoriales-seccion-encabezado">

                        <h3>Categorías</h3>

                        <p>
                            Puedes seleccionar una o varias categorías para
                            facilitar la búsqueda en la página pública.
                        </p>

                    </div>

                    <div
                        id="listaCategoriasTutorial"
                        class="tutoriales-categorias-checkboxes"
                    >

                        <p class="tutoriales-categorias-cargando">
                            Cargando categorías...
                        </p>

                    </div>

                    <p
                        id="mensajeCategoriasTutorial"
                        class="tutoriales-ayuda-categorias"
                        role="status"
                        aria-live="polite"
                    ></p>

                </section>

                <!-- =================================================
                     ACCIONES DEL FORMULARIO
                     ================================================= -->

                <div class="tutoriales-formulario-acciones">

                    <button
                        type="button"
                        id="btnCancelarModalTutorial"
                        class="btn-tutoriales-secundario"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        id="btnGuardarTutorial"
                        class="btn-tutoriales-principal"
                    >
                        Guardar borrador
                    </button>

                </div>

            </form>

        </div>

    </div>

    <!-- =====================================================
         MODAL: ADMINISTRAR CATEGORÍAS
         ===================================================== -->

    <div
        id="modalCategoriasTutoriales"
        class="tutoriales-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalCategoriasTutoriales"
        hidden
    >

        <div
            class="tutoriales-modal-fondo"
            data-cerrar-modal-categorias="true"
        ></div>

        <div class="tutoriales-modal-contenido tutoriales-modal-categorias-contenido">

            <header class="tutoriales-modal-encabezado">

                <div>
                    <h2 id="tituloModalCategoriasTutoriales">
                        Administrar categorías
                    </h2>

                    <p>
                        Crea, edita, activa o desactiva las categorías de tutoriales.
                    </p>
                </div>

                <button
                    type="button"
                    id="btnCerrarModalCategoriasTutoriales"
                    class="tutoriales-modal-cerrar"
                    aria-label="Cerrar administración de categorías"
                >
                    ×
                </button>

            </header>

            <div class="tutoriales-categorias-administracion">

                <div
                    id="mensajeAdministrarCategoriasTutoriales"
                    class="tutoriales-mensaje"
                    role="status"
                    aria-live="polite"
                    hidden
                ></div>

                <form
                    id="formCategoriaTutorial"
                    autocomplete="off"
                >

                    <input
                        type="hidden"
                        id="idCategoriaTutorial"
                        name="id_categoria"
                        value=""
                    >

                    <div class="tutoriales-campo">

                        <label for="inputNombreCategoriaTutorial">
                            Nombre de la categoría
                            <span aria-hidden="true">*</span>
                        </label>

                        <input
                            type="text"
                            id="inputNombreCategoriaTutorial"
                            name="nombre"
                            maxlength="100"
                            placeholder="Ej.: Salud bucal"
                            required
                        >

                    </div>

                    <div class="tutoriales-campo">

                        <label for="inputDescripcionCategoriaTutorial">
                            Descripción
                        </label>

                        <textarea
                            id="inputDescripcionCategoriaTutorial"
                            name="descripcion"
                            rows="3"
                            placeholder="Descripción opcional de la categoría..."
                        ></textarea>

                    </div>

                    <div class="tutoriales-formulario-acciones">

                        <button
                            type="button"
                            id="btnCancelarEdicionCategoriaTutorial"
                            class="btn-tutoriales-secundario"
                            hidden
                        >
                            Cancelar edición
                        </button>

                        <button
                            type="submit"
                            id="btnGuardarCategoriaTutorial"
                            class="btn-tutoriales-principal"
                        >
                            Crear categoría
                        </button>

                    </div>

                </form>

                <div class="tutoriales-categorias-listado-encabezado">

                    <h3>Categorías registradas</h3>

                    <button
                        type="button"
                        id="btnActualizarCategoriasTutoriales"
                        class="btn-tutoriales-secundario"
                    >
                        Actualizar
                    </button>

                </div>

                <div
                    id="cargandoCategoriasTutoriales"
                    class="tutoriales-cargando"
                    hidden
                >
                    Cargando categorías...
                </div>

                <div
                    id="listaAdministrarCategoriasTutoriales"
                    class="tutoriales-lista-administrar-categorias"
                >
                    <p class="tutoriales-categorias-cargando">
                        Cargando categorías...
                    </p>
                </div>

            </div>

        </div>

    </div>

    <?php require_once __DIR__ . '/../componentes/footer_funcionario.php'; ?>

    <script src="../js/administrar_tutoriales.js" defer></script>

</body>
</html>