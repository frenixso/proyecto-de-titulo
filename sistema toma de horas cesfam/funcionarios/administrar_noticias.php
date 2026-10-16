
<?php
/*
 * =========================================================
 * ADMINISTRACIÓN DE NOTICIAS
 * CESFAM EDUARDO RAGGIO CATEMU
 *
 * Archivo: funcionarios/administrar_noticias.php
 *
 * Actualización:
 *   - Se incorpora la selección de imágenes desde el equipo.
 *   - Se mantiene la selección desde la biblioteca multimedia.
 *   - La subida se procesará mediante la API multimedia existente.
 * =========================================================
 */

require_once __DIR__ . '/../componentes/auth_administracion_web.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrar noticias | CESFAM</title>

    <link rel="stylesheet" href="../css/compartidos/general.css">
    <link rel="stylesheet" href="../css/compartidos/navbar_funcionario.css">
    <link rel="stylesheet" href="../css/compartidos/sidebar_funcionario.css">
    <link rel="stylesheet" href="../css/compartidos/footer_funcionario.css">

    <link rel="stylesheet" href="../css/administracion_web/administrar_noticias.css">
</head>

<body class="administrar-noticias-page">

    <?php require_once __DIR__ . '/../componentes/navbar_funcionario.php'; ?>

    <div class="noticias-layout">

        <?php require_once __DIR__ . '/../componentes/sidebar_funcionario.php'; ?>

        <main class="noticias-contenido">

            <!-- =================================================
                 ENCABEZADO
                 ================================================= -->

            <header class="noticias-encabezado">

                <div class="noticias-encabezado-texto">
                    <a href="administrar_web.php" class="noticias-volver">
                        ← Volver a administración web
                    </a>

                    <h1>Administración de noticias</h1>

                    <p>
                        Crea y administra las noticias que se mostrarán
                        en la página pública del CESFAM.
                    </p>
                </div>

                <button
                    type="button"
                    id="btnNuevaNoticia"
                    class="btn-noticias-principal"
                >
                    <span aria-hidden="true">＋</span>
                    Nueva noticia
                </button>

            </header>

            <!-- =================================================
                 MENSAJES DEL SISTEMA
                 ================================================= -->

            <div
                id="mensajeNoticias"
                class="noticias-mensaje"
                role="status"
                aria-live="polite"
                hidden
            ></div>

            <!-- =================================================
                 FILTROS
                 ================================================= -->

            <section class="noticias-panel" aria-labelledby="tituloFiltrosNoticias">

                <div class="noticias-panel-encabezado">
                    <div>
                        <h2 id="tituloFiltrosNoticias">Buscar noticias</h2>
                        <p>Filtra las noticias por título, estado o destacadas.</p>
                    </div>

                    <button
                        type="button"
                        id="btnActualizarNoticias"
                        class="btn-noticias-secundario"
                    >
                        Actualizar
                    </button>
                </div>

                <div class="noticias-filtros">

                    <div class="noticias-campo noticias-busqueda">
                        <label for="filtroBusquedaNoticias">
                            Buscar por texto
                        </label>

                        <input
                            type="search"
                            id="filtroBusquedaNoticias"
                            placeholder="Escribe el título o contenido..."
                            maxlength="180"
                            autocomplete="off"
                        >
                    </div>

                    <div class="noticias-campo">
                        <label for="filtroEstadoNoticias">
                            Estado
                        </label>

                        <select id="filtroEstadoNoticias">
                            <option value="todas">Todos los estados</option>
                            <option value="borrador">Borradores</option>
                            <option value="publicada">Publicadas</option>
                            <option value="inactiva">Inactivas</option>
                            <option value="papelera">Papelera</option>
                        </select>
                    </div>

                    <div class="noticias-campo">
                        <label for="filtroDestacadaNoticias">
                            Destacadas
                        </label>

                        <select id="filtroDestacadaNoticias">
                            <option value="todas">Todas</option>
                            <option value="1">Solo destacadas</option>
                            <option value="0">No destacadas</option>
                        </select>
                    </div>

                    <div class="noticias-campo">
                        <label for="filtroCantidadNoticias">
                            Resultados por página
                        </label>

                        <select id="filtroCantidadNoticias">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>

                </div>

            </section>

            <!-- =================================================
                 LISTADO DE NOTICIAS
                 ================================================= -->

            <section class="noticias-panel" aria-labelledby="tituloListadoNoticias">

                <div class="noticias-panel-encabezado">
                    <div>
                        <h2 id="tituloListadoNoticias">Noticias registradas</h2>
                        <p id="resumenListadoNoticias">
                            Cargando noticias...
                        </p>
                    </div>
                </div>

                <div
                    id="cargandoNoticias"
                    class="noticias-cargando"
                    hidden
                >
                    Cargando noticias...
                </div>

                <div class="noticias-tabla-contenedor">

                    <table class="noticias-tabla">

                        <thead>
                            <tr>
                                <th scope="col">Imagen</th>
                                <th scope="col">Noticia</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Publicación</th>
                                <th scope="col">Destacada</th>
                                <th scope="col">Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="tablaNoticiasBody">
                            <tr>
                                <td colspan="6" class="noticias-tabla-vacia">
                                    Cargando noticias...
                                </td>
                            </tr>
                        </tbody>

                    </table>

                </div>

                <!-- Paginación -->

                <div class="noticias-paginacion" id="paginacionNoticias">

                    <button
                        type="button"
                        id="btnPaginaAnteriorNoticias"
                        class="btn-noticias-secundario"
                        disabled
                    >
                        ← Anterior
                    </button>

                    <span id="textoPaginacionNoticias">
                        Página 1
                    </span>

                    <button
                        type="button"
                        id="btnPaginaSiguienteNoticias"
                        class="btn-noticias-secundario"
                        disabled
                    >
                        Siguiente →
                    </button>

                </div>

            </section>

        </main>

    </div>

    <!-- =====================================================
         MODAL: CREAR O EDITAR NOTICIA
         ===================================================== -->

    <div
        id="modalNoticia"
        class="noticias-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalNoticia"
        hidden
    >

        <div class="noticias-modal-fondo" data-cerrar-modal="true"></div>

        <div class="noticias-modal-contenido">

            <header class="noticias-modal-encabezado">

                <div>
                    <h2 id="tituloModalNoticia">Nueva noticia</h2>

                    <p>
                        Completa los datos de la noticia.
                        Podrás publicarla después de guardarla.
                    </p>
                </div>

                <button
                    type="button"
                    id="btnCerrarModalNoticia"
                    class="noticias-modal-cerrar"
                    aria-label="Cerrar formulario"
                >
                    ×
                </button>

            </header>

            <form id="formNoticia" autocomplete="off">

                <input
                    type="hidden"
                    id="idNoticia"
                    name="id_noticia"
                    value=""
                >

                <!-- Título -->

                <div class="noticias-campo">
                    <label for="inputTituloNoticia">
                        Título <span aria-hidden="true">*</span>
                    </label>

                    <input
                        type="text"
                        id="inputTituloNoticia"
                        name="titulo"
                        maxlength="180"
                        placeholder="Título de la noticia"
                        required
                    >

                    <small>Máximo 180 caracteres.</small>
                </div>

                <!-- Resumen -->

                <div class="noticias-campo">
                    <label for="inputResumenNoticia">
                        Resumen
                    </label>

                    <textarea
                        id="inputResumenNoticia"
                        name="resumen"
                        rows="3"
                        maxlength="500"
                        placeholder="Descripción breve para presentar la noticia..."
                    ></textarea>

                    <small>Máximo 500 caracteres.</small>
                </div>

                <!-- Contenido -->

                <div class="noticias-campo">
                    <label for="inputContenidoNoticia">
                        Contenido <span aria-hidden="true">*</span>
                    </label>

                    <textarea
                        id="inputContenidoNoticia"
                        name="contenido"
                        rows="9"
                        placeholder="Escribe el contenido completo de la noticia..."
                        required
                    ></textarea>

                    <small>
                        Por ahora, el contenido se ingresa como texto plano.
                    </small>
                </div>

                <!-- =================================================
                     IMAGEN DE LA NOTICIA
                     ================================================= -->

                <div class="noticias-seccion-imagen">

                    <!-- Seleccionar imagen existente -->

                    <div class="noticias-campo">
                        <label for="selectImagenNoticia">
                            Seleccionar imagen de la biblioteca
                        </label>

                        <select
                            id="selectImagenNoticia"
                            name="id_multimedia"
                        >
                            <option value="">
                                Sin imagen
                            </option>
                        </select>

                        <small>
                            Puedes utilizar una imagen que ya esté cargada
                            en la biblioteca multimedia.
                        </small>
                    </div>

                    <!-- Separador visual entre las dos alternativas -->

                    <div class="noticias-imagen-separador" aria-hidden="true">
                        <span>O bien</span>
                    </div>

                    <!-- Seleccionar imagen desde el computador -->

                    <div class="noticias-campo noticias-subir-imagen">

                        <label for="inputImagenArchivoNoticia">
                            Subir una imagen desde tu computador
                        </label>

                        <input
                            type="file"
                            id="inputImagenArchivoNoticia"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            aria-describedby="ayudaImagenArchivoNoticia estadoCargaImagenNoticia"
                        >

                        <small id="ayudaImagenArchivoNoticia">
                            Formatos permitidos: JPG, PNG, WEBP y GIF.
                            Tamaño máximo: 5 MB.
                            Si seleccionas un archivo, se utilizará en lugar
                            de la imagen elegida en la biblioteca.
                        </small>

                        <small
                            id="estadoCargaImagenNoticia"
                            role="status"
                            aria-live="polite"
                        ></small>

                    </div>

                    <!-- Vista previa -->

                    <div
                        id="contenedorVistaPreviaNoticia"
                        class="noticias-vista-previa"
                        hidden
                    >
                        <img
                            id="imagenVistaPreviaNoticia"
                            src=""
                            alt=""
                        >

                        <p id="nombreVistaPreviaNoticia"></p>
                    </div>

                    <!-- Texto alternativo -->

                    <div class="noticias-campo">
                        <label for="inputTextoAlternativoNoticia">
                            Texto alternativo de la imagen
                        </label>

                        <input
                            type="text"
                            id="inputTextoAlternativoNoticia"
                            name="texto_alternativo_imagen"
                            maxlength="255"
                            placeholder="Describe brevemente la imagen"
                        >

                        <small>
                            Describe la imagen para facilitar su comprensión
                            a las personas que utilizan lectores de pantalla.
                        </small>
                    </div>

                </div>

                <!-- Destacada -->

                <div class="noticias-destacada-opcion">
                    <label for="inputDestacadaNoticia">

                        <input
                            type="checkbox"
                            id="inputDestacadaNoticia"
                            name="destacada"
                            value="1"
                        >

                        <span>
                            <strong>Marcar como noticia destacada</strong>

                            <small>
                                Permite priorizar esta noticia en la portada,
                                según las reglas de publicación que implementaremos.
                            </small>
                        </span>

                    </label>
                </div>

                <!-- Acciones -->

                <div class="noticias-formulario-acciones">

                    <button
                        type="button"
                        id="btnCancelarModalNoticia"
                        class="btn-noticias-secundario"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        id="btnGuardarNoticia"
                        class="btn-noticias-principal"
                    >
                        Guardar borrador
                    </button>

                </div>

            </form>

        </div>

    </div>

    <?php require_once __DIR__ . '/../componentes/footer_funcionario.php'; ?>

    <script src="../js/administrar_noticias.js" defer></script>

</body>
</html>
