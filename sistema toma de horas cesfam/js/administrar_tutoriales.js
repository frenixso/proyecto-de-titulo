document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    /*
     * =========================================================
     * ADMINISTRACIÓN DE TUTORIALES
     * CESFAM EDUARDO RAGGIO CATEMU
     *
     * Archivo: js/administrar_tutoriales.js
     *
     * Funciones:
     * - Listar, buscar y filtrar tutoriales.
     * - Navegar mediante paginación.
     * - Crear y editar tutoriales.
     * - Subir videos al servidor.
     * - Aceptar el código iframe completo de YouTube.
     * - Generar vistas previas seguras de YouTube.
     * - Asignar múltiples categorías.
     * - Crear, editar, activar y desactivar categorías.
     * - Publicar, desactivar y devolver a borrador.
     * - Enviar tutoriales a la papelera y restaurarlos.
     * - Eliminar definitivamente desde la papelera.
     * =========================================================
     */

    const API_TUTORIALES_URL = "../api/web_tutoriales.php";

    const LIMITE_VIDEO_BYTES = 100 * 1024 * 1024;
    const LIMITE_ENTRADA_YOUTUBE = 5000;

    const TIPOS_VIDEO_PERMITIDOS = [
        "video/mp4",
        "video/webm",
        "video/ogg"
    ];

    const EXTENSIONES_VIDEO_PERMITIDAS = [
        "mp4",
        "webm",
        "ogv",
        "ogg"
    ];

    let tokenTutoriales = "";

    let tutorialesActuales = [];
    let categoriasActuales = [];

    let paginaActual = 1;
    let totalPaginas = 1;
    let cantidadPorPagina = 10;

    let cargandoTutoriales = false;
    let guardandoTutorial = false;
    let guardandoCategoria = false;

    let temporizadorBusqueda = null;
    let urlVistaPreviaLocal = null;

    let tipoOriginalTutorial = "";
    let rutaOriginalTutorial = "";

    /*
     * Se guardan los identificadores seleccionados al editar
     * para conservarlos cuando se cargan las categorías.
     */
    let categoriasSeleccionadasEdicion = [];

    /*
     * =========================================================
     * ELEMENTOS DEL DOM
     * =========================================================
     */

    const elementos = {
        btnNuevo: document.getElementById("btnNuevoTutorial"),
        btnActualizar: document.getElementById("btnActualizarTutoriales"),
        btnAdministrarCategorias: document.getElementById(
            "btnAdministrarCategorias"
        ),

        busqueda: document.getElementById("filtroBusquedaTutoriales"),
        estado: document.getElementById("filtroEstadoTutoriales"),
        tipo: document.getElementById("filtroTipoTutoriales"),
        categoria: document.getElementById("filtroCategoriaTutoriales"),
        cantidad: document.getElementById("filtroCantidadTutoriales"),

        mensaje: document.getElementById("mensajeTutoriales"),
        resumen: document.getElementById("resumenListadoTutoriales"),
        cargando: document.getElementById("cargandoTutoriales"),
        tabla: document.getElementById("tablaTutorialesBody"),

        btnAnterior: document.getElementById(
            "btnPaginaAnteriorTutoriales"
        ),
        btnSiguiente: document.getElementById(
            "btnPaginaSiguienteTutoriales"
        ),
        textoPaginacion: document.getElementById(
            "textoPaginacionTutoriales"
        ),

        modal: document.getElementById("modalTutorial"),
        tituloModal: document.getElementById("tituloModalTutorial"),
        descripcionModal: document.getElementById(
            "descripcionModalTutorial"
        ),
        btnCerrarModal: document.getElementById(
            "btnCerrarModalTutorial"
        ),
        btnCancelarModal: document.getElementById(
            "btnCancelarModalTutorial"
        ),

        formulario: document.getElementById("formTutorial"),
        idTutorial: document.getElementById("idTutorial"),
        titulo: document.getElementById("inputTituloTutorial"),
        descripcion: document.getElementById(
            "inputDescripcionTutorial"
        ),

        tipoVideo: document.getElementById("selectTipoVideoTutorial"),
        bloqueArchivo: document.getElementById("bloqueArchivoVideo"),
        bloqueYoutube: document.getElementById("bloqueYoutubeVideo"),

        archivoVideo: document.getElementById(
            "inputArchivoVideoTutorial"
        ),

        /*
         * Este campo debe ser un textarea o un input type="text".
         * No debe ser input type="url", porque recibirá HTML iframe.
         */
        urlYoutube: document.getElementById(
            "inputUrlYoutubeTutorial"
        ),

        estadoCargaVideo: document.getElementById(
            "estadoCargaVideoTutorial"
        ),

        contenedorVistaPrevia: document.getElementById(
            "contenedorVistaPreviaVideoTutorial"
        ),
        videoVistaPrevia: document.getElementById(
            "videoVistaPreviaTutorial"
        ),
        iframeVistaPrevia: document.getElementById(
            "iframeVistaPreviaTutorial"
        ),
        mensajeVistaPrevia: document.getElementById(
            "mensajeVistaPreviaVideoTutorial"
        ),
        tipoVistaPrevia: document.getElementById(
            "tipoVistaPreviaVideoTutorial"
        ),
        nombreVistaPrevia: document.getElementById(
            "nombreVistaPreviaVideoTutorial"
        ),

        listaCategorias: document.getElementById(
            "listaCategoriasTutorial"
        ),
        mensajeCategorias: document.getElementById(
            "mensajeCategoriasTutorial"
        ),

        btnGuardar: document.getElementById("btnGuardarTutorial"),

        /*
         * Modal de categorías.
         */
        modalCategorias: document.getElementById(
            "modalCategoriasTutoriales"
        ),
        btnCerrarModalCategorias: document.getElementById(
            "btnCerrarModalCategoriasTutoriales"
        ),
        mensajeAdministrarCategorias: document.getElementById(
            "mensajeAdministrarCategoriasTutoriales"
        ),
        formularioCategoria: document.getElementById(
            "formCategoriaTutorial"
        ),
        idCategoria: document.getElementById("idCategoriaTutorial"),
        nombreCategoria: document.getElementById(
            "inputNombreCategoriaTutorial"
        ),
        descripcionCategoria: document.getElementById(
            "inputDescripcionCategoriaTutorial"
        ),
        btnGuardarCategoria: document.getElementById(
            "btnGuardarCategoriaTutorial"
        ),
        btnCancelarEdicionCategoria: document.getElementById(
            "btnCancelarEdicionCategoriaTutorial"
        ),
        btnActualizarCategorias: document.getElementById(
            "btnActualizarCategoriasTutoriales"
        ),
        cargandoCategorias: document.getElementById(
            "cargandoCategoriasTutoriales"
        ),
        listaAdministrarCategorias: document.getElementById(
            "listaAdministrarCategoriasTutoriales"
        )
    };

    /*
     * =========================================================
     * UTILIDADES GENERALES
     * =========================================================
     */

    function crearElemento(etiqueta, clase, texto) {
        const elemento = document.createElement(etiqueta);

        if (clase) {
            elemento.className = clase;
        }

        if (texto !== undefined && texto !== null) {
            elemento.textContent = String(texto);
        }

        return elemento;
    }

    function mostrarMensaje(mensaje, tipo = "info", destino = null) {
        const elementoMensaje = destino || elementos.mensaje;

        if (!elementoMensaje) {
            console.info("[Administración de tutoriales]", mensaje);
            return;
        }

        elementoMensaje.textContent = String(mensaje || "");
        elementoMensaje.className = "tutoriales-mensaje";
        elementoMensaje.classList.add("mensaje-" + tipo);
        elementoMensaje.hidden = false;
    }

    function limpiarMensaje(destino = null) {
        const elementoMensaje = destino || elementos.mensaje;

        if (!elementoMensaje) {
            return;
        }

        elementoMensaje.textContent = "";
        elementoMensaje.hidden = true;
        elementoMensaje.className = "tutoriales-mensaje";
    }

    function esVerdadero(valor) {
        return valor === true ||
            valor === 1 ||
            valor === "1" ||
            valor === "t" ||
            valor === "true";
    }

    function obtenerDatos(resultado) {
        if (resultado.data && typeof resultado.data === "object") {
            return resultado.data;
        }

        if (resultado.datos && typeof resultado.datos === "object") {
            return resultado.datos;
        }

        return resultado;
    }

    async function leerRespuesta(respuesta) {
        let resultado;

        try {
            resultado = await respuesta.json();
        } catch (error) {
            throw new Error(
                "El servidor devolvió una respuesta que no se pudo interpretar."
            );
        }

        if (
            !respuesta.ok ||
            resultado.ok === false ||
            resultado.success === false ||
            resultado.exito === false
        ) {
            throw new Error(
                resultado.mensaje ||
                resultado.message ||
                "No fue posible completar la operación."
            );
        }

        return resultado;
    }

    async function solicitarGET(url) {
        const respuesta = await fetch(url, {
            method: "GET",
            credentials: "same-origin",
            cache: "no-store",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        return leerRespuesta(respuesta);
    }

    async function solicitarPOST(url, datos) {
        const respuesta = await fetch(url, {
            method: "POST",
            body: datos,
            credentials: "same-origin",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        return leerRespuesta(respuesta);
    }

    function crearDatosPOST(accion, datosAdicionales = {}) {
        const datos = new URLSearchParams();

        datos.set("accion", accion);
        datos.set("csrf_token", tokenTutoriales);

        Object.entries(datosAdicionales).forEach(function ([clave, valor]) {
            if (valor !== null && valor !== undefined) {
                datos.set(clave, String(valor));
            }
        });

        return datos;
    }

    function escaparTexto(valor) {
        return String(valor ?? "");
    }

    function actualizarToken(resultado) {
        const datos = obtenerDatos(resultado);

        if (resultado.csrf_token) {
            tokenTutoriales = String(resultado.csrf_token);
        } else if (datos.csrf_token) {
            tokenTutoriales = String(datos.csrf_token);
        }
    }

    /*
     * =========================================================
     * VALIDACIÓN DE RUTAS DE VIDEOS LOCALES
     * =========================================================
     */

    function obtenerRutaVideo(rutaRelativa) {
        const ruta = String(rutaRelativa || "");

        if (
            !ruta.startsWith("uploads/tutoriales/") ||
            ruta.includes("..") ||
            ruta.includes("\\") ||
            ruta.includes("\0") ||
            ruta.startsWith("/") ||
            /^[a-z][a-z0-9+.-]*:/i.test(ruta)
        ) {
            return null;
        }

        return "../" + ruta;
    }

    /*
     * =========================================================
     * OBTENER ID DE YOUTUBE
     *
     * Acepta:
     *
     * 1. El iframe completo que entrega YouTube en:
     *    Compartir > Incorporar.
     *
     * 2. Un enlace normal de YouTube.
     *
     * 3. Un enlace corto de youtu.be.
     *
     * 4. Un enlace /embed/, /shorts/ o /live/.
     *
     * Importante:
     * El iframe pegado por el usuario nunca se inserta como HTML.
     * Solo se extrae y valida su atributo src.
     * =========================================================
     */

    function obtenerIdYouTube(entrada) {
        const texto = String(entrada || "").trim();

        if (!texto || texto.length > LIMITE_ENTRADA_YOUTUBE) {
            return null;
        }

        const esIframe = /<iframe\b/i.test(texto);

        let textoURL = texto;

        /*
         * Procesar el código iframe completo.
         */
        if (esIframe) {
            const coincidenciasIframe = Array.from(
                texto.matchAll(/<iframe\b([^>]*)>/gi)
            );

            /*
             * Rechazar entradas que incluyan más de un iframe.
             */
            if (coincidenciasIframe.length !== 1) {
                return null;
            }

            const atributos = coincidenciasIframe[0][1] || "";

            /*
             * Extraer src permitiendo comillas dobles,
             * comillas simples o un atributo sin comillas.
             */
            const coincidenciaSrc = atributos.match(
                /(?:^|\s)src\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/i
            );

            if (!coincidenciaSrc) {
                return null;
            }

            let src = "";

            if (
                coincidenciaSrc[1] !== undefined &&
                coincidenciaSrc[1] !== ""
            ) {
                src = coincidenciaSrc[1];
            } else if (
                coincidenciaSrc[2] !== undefined &&
                coincidenciaSrc[2] !== ""
            ) {
                src = coincidenciaSrc[2];
            } else if (
                coincidenciaSrc[3] !== undefined &&
                coincidenciaSrc[3] !== ""
            ) {
                src = coincidenciaSrc[3];
            }

            /*
             * Normalizar las entidades HTML más habituales.
             * No se ejecuta ni se incorpora el HTML recibido.
             */
            src = src
                .replace(/&amp;/gi, "&")
                .replace(/&quot;/gi, '"')
                .replace(/&#39;|&apos;/gi, "'");

            textoURL = src.trim();

            if (!textoURL) {
                return null;
            }
        }

        /*
         * Analizar la URL extraída o el enlace normal.
         */
        let url;

        try {
            url = new URL(textoURL);
        } catch (error) {
            return null;
        }

        /*
         * Solo HTTPS y sin credenciales ni puertos personalizados.
         */
        if (
            url.protocol !== "https:" ||
            url.username ||
            url.password ||
            url.port
        ) {
            return null;
        }

        const host = url.hostname
            .toLowerCase()
            .replace(/\.$/, "");

        const hostsPermitidos = [
            "youtube.com",
            "www.youtube.com",
            "m.youtube.com",
            "music.youtube.com",
            "youtu.be",
            "youtube-nocookie.com",
            "www.youtube-nocookie.com"
        ];

        if (!hostsPermitidos.includes(host)) {
            return null;
        }

        /*
         * Si se pegó un iframe, su src debe apuntar al reproductor.
         *
         * Ejemplo:
         * https://www.youtube.com/embed/AbCdEf12345
         *
         * Los ID de YouTube tienen 11 caracteres.
         */
        if (esIframe) {
            const coincidenciaEmbed = url.pathname.match(
                /^\/embed\/([A-Za-z0-9_-]{11})\/?$/
            );

            if (!coincidenciaEmbed) {
                return null;
            }

            return coincidenciaEmbed[1];
        }

        /*
         * Admitir enlaces normales por compatibilidad.
         */
        let id = null;

        if (host === "youtu.be") {
            id = url.pathname.replace(/^\/+|\/+$/g, "");

            if (id.includes("/")) {
                return null;
            }
        } else if (url.pathname === "/watch") {
            id = url.searchParams.get("v");
        } else {
            const coincidencia = url.pathname.match(
                /^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})\/?$/
            );

            if (coincidencia) {
                id = coincidencia[1];
            }
        }

        if (
            typeof id !== "string" ||
            !/^[A-Za-z0-9_-]{11}$/.test(id)
        ) {
            return null;
        }

        return id;
    }

    /*
     * =========================================================
     * FORMATEO DE FECHAS Y TAMAÑOS
     * =========================================================
     */

    function formatearFecha(fecha) {
        if (!fecha) {
            return "Sin publicar";
        }

        const fechaNormalizada = String(fecha).replace(" ", "T");
        const fechaConvertida = new Date(fechaNormalizada);

        if (Number.isNaN(fechaConvertida.getTime())) {
            return String(fecha);
        }

        return fechaConvertida.toLocaleString("es-CL", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit"
        });
    }

    function formatearTamano(valor) {
        const bytes = Number(valor);

        if (!Number.isFinite(bytes) || bytes <= 0) {
            return "";
        }

        if (bytes < 1024 * 1024) {
            return Math.max(1, Math.round(bytes / 1024)) + " KB";
        }

        return (bytes / (1024 * 1024)).toFixed(1) + " MB";
    }

    /*
     * =========================================================
     * VISTA PREVIA DE VIDEOS
     * =========================================================
     */

    function liberarVistaPreviaLocal() {
        if (urlVistaPreviaLocal) {
            URL.revokeObjectURL(urlVistaPreviaLocal);
            urlVistaPreviaLocal = null;
        }
    }

    function detenerReproductorLocal() {
        if (!elementos.videoVistaPrevia) {
            return;
        }

        elementos.videoVistaPrevia.pause();
        elementos.videoVistaPrevia.removeAttribute("src");
        elementos.videoVistaPrevia.load();
        elementos.videoVistaPrevia.hidden = true;
    }

    function detenerReproductorYouTube() {
        if (!elementos.iframeVistaPrevia) {
            return;
        }

        elementos.iframeVistaPrevia.removeAttribute("src");
        elementos.iframeVistaPrevia.hidden = true;
    }

    function ocultarVistaPreviaVideo() {
        liberarVistaPreviaLocal();
        detenerReproductorLocal();
        detenerReproductorYouTube();

        if (elementos.contenedorVistaPrevia) {
            elementos.contenedorVistaPrevia.hidden = true;
        }

        if (elementos.mensajeVistaPrevia) {
            elementos.mensajeVistaPrevia.hidden = true;
        }

        if (elementos.tipoVistaPrevia) {
            elementos.tipoVistaPrevia.textContent = "";
        }

        if (elementos.nombreVistaPrevia) {
            elementos.nombreVistaPrevia.textContent = "";
        }
    }

    function mostrarMensajeVistaPrevia(mensaje) {
        liberarVistaPreviaLocal();
        detenerReproductorLocal();
        detenerReproductorYouTube();

        if (elementos.contenedorVistaPrevia) {
            elementos.contenedorVistaPrevia.hidden = false;
        }

        if (elementos.mensajeVistaPrevia) {
            elementos.mensajeVistaPrevia.textContent = mensaje;
            elementos.mensajeVistaPrevia.hidden = false;
        }

        if (elementos.tipoVistaPrevia) {
            elementos.tipoVistaPrevia.textContent = "Vista previa";
        }

        if (elementos.nombreVistaPrevia) {
            elementos.nombreVistaPrevia.textContent = "";
        }
    }

    function mostrarVistaPreviaArchivoLocal(archivo) {
        if (!archivo) {
            ocultarVistaPreviaVideo();
            return;
        }

        liberarVistaPreviaLocal();
        detenerReproductorYouTube();
        detenerReproductorLocal();

        urlVistaPreviaLocal = URL.createObjectURL(archivo);

        elementos.contenedorVistaPrevia.hidden = false;
        elementos.mensajeVistaPrevia.hidden = true;
        elementos.videoVistaPrevia.hidden = false;
        elementos.videoVistaPrevia.src = urlVistaPreviaLocal;

        elementos.tipoVistaPrevia.textContent = "Archivo local";

        elementos.nombreVistaPrevia.textContent =
            archivo.name +
            " — " +
            formatearTamano(archivo.size);

        elementos.videoVistaPrevia.onerror = function () {
            mostrarMensajeVistaPrevia(
                "No se pudo reproducir este archivo en el navegador. Comprueba que sea un video compatible."
            );
        };
    }

    function mostrarVistaPreviaArchivoExistente(rutaRelativa, nombre) {
        const ruta = obtenerRutaVideo(rutaRelativa);

        if (!ruta) {
            mostrarMensajeVistaPrevia(
                "La ruta del video guardado no es válida."
            );
            return;
        }

        liberarVistaPreviaLocal();
        detenerReproductorYouTube();
        detenerReproductorLocal();

        elementos.contenedorVistaPrevia.hidden = false;
        elementos.mensajeVistaPrevia.hidden = true;
        elementos.videoVistaPrevia.hidden = false;
        elementos.videoVistaPrevia.src = ruta;

        elementos.tipoVistaPrevia.textContent = "Video guardado";

        elementos.nombreVistaPrevia.textContent =
            String(nombre || "Video existente");

        elementos.videoVistaPrevia.onerror = function () {
            mostrarMensajeVistaPrevia(
                "No se pudo cargar el video existente. Puede que el archivo ya no esté disponible."
            );
        };
    }

    function mostrarVistaPreviaYouTube(idVideo) {
        const id = String(idVideo || "");

        if (!/^[A-Za-z0-9_-]{11}$/.test(id)) {
            mostrarMensajeVistaPrevia(
                "Pega el código iframe completo de YouTube para mostrar la vista previa."
            );
            return;
        }

        liberarVistaPreviaLocal();
        detenerReproductorLocal();
        detenerReproductorYouTube();

        elementos.contenedorVistaPrevia.hidden = false;
        elementos.mensajeVistaPrevia.hidden = true;
        elementos.iframeVistaPrevia.hidden = false;

        /*
         * Generamos un iframe válido usando únicamente el ID validado.
         * No insertamos en la página el HTML pegado por el usuario.
         */
        const iframe = elementos.iframeVistaPrevia;

        iframe.title = "Reproductor de video de YouTube";
        iframe.setAttribute(
            "allow",
            "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        );
        iframe.setAttribute(
            "referrerpolicy",
            "strict-origin-when-cross-origin"
        );
        iframe.setAttribute("allowfullscreen", "");

        iframe.src =
            "https://www.youtube.com/embed/" +
            encodeURIComponent(id);

        elementos.tipoVistaPrevia.textContent = "YouTube";

        elementos.nombreVistaPrevia.textContent =
            "Vista previa del video incorporado de YouTube";
    }

    function actualizarVistaPreviaYouTube() {
        if (!elementos.tipoVideo || elementos.tipoVideo.value !== "youtube") {
            return;
        }

        const texto = elementos.urlYoutube.value.trim();

        if (!texto) {
            mostrarMensajeVistaPrevia(
                "Pega aquí el código iframe de YouTube obtenido en Compartir > Incorporar."
            );
            return;
        }

        const id = obtenerIdYouTube(texto);

        if (!id) {
            mostrarMensajeVistaPrevia(
                "No se pudo reconocer el iframe. Copia el código completo desde YouTube > Compartir > Incorporar y pégalo aquí."
            );
            return;
        }

        mostrarVistaPreviaYouTube(id);
    }

    function actualizarVistaPreviaActual() {
        if (!elementos.tipoVideo) {
            return;
        }

        const tipo = elementos.tipoVideo.value;

        if (tipo === "youtube") {
            actualizarVistaPreviaYouTube();
            return;
        }

        const archivo = elementos.archivoVideo.files?.[0];

        if (archivo) {
            mostrarVistaPreviaArchivoLocal(archivo);
            return;
        }

        if (
            tipoOriginalTutorial === "archivo" &&
            rutaOriginalTutorial
        ) {
            mostrarVistaPreviaArchivoExistente(
                rutaOriginalTutorial,
                "Video guardado"
            );
            return;
        }

        mostrarMensajeVistaPrevia(
            "Selecciona un archivo de video para mostrar la vista previa."
        );
    }

    function mostrarEstadoCargaVideo(mensaje) {
        if (elementos.estadoCargaVideo) {
            elementos.estadoCargaVideo.textContent = String(mensaje || "");
        }
    }

    /*
     * =========================================================
     * VALIDACIÓN PRELIMINAR DEL ARCHIVO
     * La API vuelve a validar el MIME real.
     * =========================================================
     */

    function validarArchivoVideo(archivo) {
        if (!archivo) {
            return "Selecciona un archivo de video.";
        }

        if (archivo.size <= 0) {
            return "El archivo está vacío o no tiene un tamaño válido.";
        }

        if (archivo.size > LIMITE_VIDEO_BYTES) {
            return "El video supera el límite permitido de 100 MB.";
        }

        const nombre = String(archivo.name || "");
        const partes = nombre.split(".");
        const extension = partes.length > 1
            ? partes.pop().toLowerCase()
            : "";

        const tipoMime = String(archivo.type || "").toLowerCase();

        if (!EXTENSIONES_VIDEO_PERMITIDAS.includes(extension)) {
            return "La extensión no está permitida. Utiliza MP4, WebM, OGG u OGV compatible.";
        }

        if (
            tipoMime !== "" &&
            !TIPOS_VIDEO_PERMITIDOS.includes(tipoMime)
        ) {
            return "El navegador no reconoce un formato de video permitido.";
        }

        return "";
    }

    function limpiarArchivoSeleccionado() {
        liberarVistaPreviaLocal();

        if (elementos.archivoVideo) {
            elementos.archivoVideo.value = "";
        }

        mostrarEstadoCargaVideo("");
    }

    function manejarSeleccionArchivo() {
        const archivo = elementos.archivoVideo.files?.[0];

        mostrarEstadoCargaVideo("");

        if (!archivo) {
            actualizarVistaPreviaActual();
            return;
        }

        const errorArchivo = validarArchivoVideo(archivo);

        if (errorArchivo) {
            elementos.archivoVideo.value = "";
            mostrarEstadoCargaVideo(errorArchivo);
            actualizarVistaPreviaActual();
            return;
        }

        mostrarVistaPreviaArchivoLocal(archivo);

        mostrarEstadoCargaVideo(
            "Video seleccionado. Se enviará al servidor al guardar el tutorial."
        );
    }

    /*
     * =========================================================
     * CATEGORÍAS DEL FORMULARIO
     * =========================================================
     */

    async function cargarCategorias(
        idsSeleccionados = categoriasSeleccionadasEdicion
    ) {
        const seleccionadas = Array.isArray(idsSeleccionados)
            ? idsSeleccionados.map(String)
            : [];

        if (elementos.listaCategorias) {
            elementos.listaCategorias.replaceChildren();

            elementos.listaCategorias.appendChild(
                crearElemento(
                    "p",
                    "tutoriales-categorias-cargando",
                    "Cargando categorías..."
                )
            );
        }

        try {
            const resultado = await solicitarGET(
                API_TUTORIALES_URL +
                "?accion=categorias&incluir_inactivas=1"
            );

            actualizarToken(resultado);

            const datos = obtenerDatos(resultado);

            categoriasActuales = Array.isArray(datos.categorias)
                ? datos.categorias
                : Array.isArray(resultado.categorias)
                    ? resultado.categorias
                    : [];

            renderizarCategoriasFormulario(seleccionadas);
            renderizarFiltroCategorias();
            renderizarAdministracionCategorias();

            return categoriasActuales;
        } catch (error) {
            if (elementos.listaCategorias) {
                elementos.listaCategorias.replaceChildren();

                elementos.listaCategorias.appendChild(
                    crearElemento(
                        "p",
                        "tutoriales-categorias-cargando",
                        "No fue posible cargar las categorías. " + error.message
                    )
                );
            }

            mostrarMensaje(
                "No fue posible cargar las categorías: " + error.message,
                "error"
            );

            throw error;
        }
    }

    function renderizarCategoriasFormulario(idsSeleccionados = []) {
        if (!elementos.listaCategorias) {
            return;
        }

        const seleccionadas = idsSeleccionados.map(String);

        elementos.listaCategorias.replaceChildren();

        if (!categoriasActuales.length) {
            elementos.listaCategorias.appendChild(
                crearElemento(
                    "p",
                    "tutoriales-categorias-cargando",
                    "Todavía no hay categorías. Puedes crearlas con «Administrar categorías»."
                )
            );

            return;
        }

        categoriasActuales.forEach(function (categoria) {
            const id = String(categoria.id_categoria);
            const estaActiva = esVerdadero(categoria.activo);
            const estaSeleccionada = seleccionadas.includes(id);

            const etiqueta = crearElemento(
                "label",
                "tutoriales-categoria-opcion"
            );

            const checkbox = document.createElement("input");

            checkbox.type = "checkbox";
            checkbox.name = "categorias[]";
            checkbox.value = id;
            checkbox.checked = estaSeleccionada;
            checkbox.disabled = !estaActiva && !estaSeleccionada;

            const texto = document.createElement("span");

            texto.appendChild(
                crearElemento(
                    "strong",
                    "",
                    categoria.nombre || "Categoría sin nombre"
                )
            );

            const detalles = [];

            if (!estaActiva) {
                detalles.push("Inactiva");
            }

            if (categoria.descripcion) {
                detalles.push(String(categoria.descripcion));
            }

            if (detalles.length) {
                texto.appendChild(
                    crearElemento("small", "", detalles.join(" · "))
                );
            }

            etiqueta.appendChild(checkbox);
            etiqueta.appendChild(texto);

            elementos.listaCategorias.appendChild(etiqueta);
        });
    }

    function renderizarFiltroCategorias() {
        if (!elementos.categoria) {
            return;
        }

        const valorAnterior = elementos.categoria.value;

        elementos.categoria.replaceChildren();

        const opcionTodas = document.createElement("option");

        opcionTodas.value = "todas";
        opcionTodas.textContent = "Todas las categorías";

        elementos.categoria.appendChild(opcionTodas);

        categoriasActuales.forEach(function (categoria) {
            const opcion = document.createElement("option");

            opcion.value = String(categoria.id_categoria);
            opcion.textContent = String(categoria.nombre || "");

            if (!esVerdadero(categoria.activo)) {
                opcion.textContent += " (inactiva)";
            }

            elementos.categoria.appendChild(opcion);
        });

        if (
            Array.from(elementos.categoria.options).some(
                function (opcion) {
                    return opcion.value === valorAnterior;
                }
            )
        ) {
            elementos.categoria.value = valorAnterior;
        } else {
            elementos.categoria.value = "todas";
        }
    }

    /*
     * =========================================================
     * ADMINISTRAR CATEGORÍAS
     * =========================================================
     */

    function renderizarAdministracionCategorias() {
        if (!elementos.listaAdministrarCategorias) {
            return;
        }

        elementos.listaAdministrarCategorias.replaceChildren();

        if (!categoriasActuales.length) {
            elementos.listaAdministrarCategorias.appendChild(
                crearElemento(
                    "p",
                    "tutoriales-categorias-cargando",
                    "Todavía no hay categorías registradas."
                )
            );

            return;
        }

        const fragmento = document.createDocumentFragment();

        categoriasActuales.forEach(function (categoria) {
            const id = Number(categoria.id_categoria);
            const activa = esVerdadero(categoria.activo);

            const item = crearElemento(
                "article",
                "tutoriales-categoria-item" +
                    (activa ? "" : " tutoriales-categoria-item-inactiva")
            );

            const informacion = crearElemento(
                "div",
                "tutoriales-categoria-info"
            );

            informacion.appendChild(
                crearElemento(
                    "strong",
                    "",
                    categoria.nombre || "Categoría sin nombre"
                )
            );

            if (categoria.descripcion) {
                informacion.appendChild(
                    crearElemento("p", "", categoria.descripcion)
                );
            }

            informacion.appendChild(
                crearElemento(
                    "small",
                    "",
                    "Tutoriales relacionados: " +
                        String(categoria.cantidad_tutoriales || 0)
                )
            );

            informacion.appendChild(
                crearElemento(
                    "span",
                    "tutoriales-categoria-estado " +
                        (activa ? "activa" : "inactiva"),
                    activa ? "Activa" : "Inactiva"
                )
            );

            const acciones = crearElemento(
                "div",
                "tutoriales-categoria-acciones"
            );

            const botonEditar = crearElemento("button", "", "Editar");

            botonEditar.type = "button";

            botonEditar.addEventListener("click", function () {
                prepararEdicionCategoria(categoria);
            });

            acciones.appendChild(botonEditar);

            const botonEstado = crearElemento(
                "button",
                activa ? "accion-desactivar" : "accion-activar",
                activa ? "Desactivar" : "Activar"
            );

            botonEstado.type = "button";

            botonEstado.addEventListener("click", function () {
                cambiarEstadoCategoria(id, !activa);
            });

            acciones.appendChild(botonEstado);

            item.appendChild(informacion);
            item.appendChild(acciones);

            fragmento.appendChild(item);
        });

        elementos.listaAdministrarCategorias.appendChild(fragmento);
    }

    function prepararNuevaCategoria() {
        if (!elementos.formularioCategoria) {
            return;
        }

        elementos.formularioCategoria.reset();

        elementos.idCategoria.value = "";
        elementos.btnGuardarCategoria.textContent = "Crear categoría";
        elementos.btnCancelarEdicionCategoria.hidden = true;

        limpiarMensaje(elementos.mensajeAdministrarCategorias);
    }

    function prepararEdicionCategoria(categoria) {
        elementos.idCategoria.value = String(categoria.id_categoria);
        elementos.nombreCategoria.value = categoria.nombre || "";
        elementos.descripcionCategoria.value =
            categoria.descripcion || "";

        elementos.btnGuardarCategoria.textContent = "Guardar cambios";
        elementos.btnCancelarEdicionCategoria.hidden = false;

        limpiarMensaje(elementos.mensajeAdministrarCategorias);

        elementos.nombreCategoria.focus();
        elementos.formularioCategoria.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    }

    async function guardarCategoria(evento) {
        evento.preventDefault();

        if (guardandoCategoria) {
            return;
        }

        const nombre = elementos.nombreCategoria.value.trim();

        if (!nombre) {
            mostrarMensaje(
                "Debes ingresar el nombre de la categoría.",
                "error",
                elementos.mensajeAdministrarCategorias
            );

            elementos.nombreCategoria.focus();
            return;
        }

        if (!tokenTutoriales) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error",
                elementos.mensajeAdministrarCategorias
            );
            return;
        }

        guardandoCategoria = true;

        elementos.btnGuardarCategoria.disabled = true;
        elementos.btnGuardarCategoria.textContent = "Guardando...";

        try {
            const datos = new FormData(elementos.formularioCategoria);

            datos.set("accion", "guardar_categoria");
            datos.set("csrf_token", tokenTutoriales);

            const resultado = await solicitarPOST(
                API_TUTORIALES_URL,
                datos
            );

            actualizarToken(resultado);

            prepararNuevaCategoria();

            await cargarCategorias([]);

            mostrarMensaje(
                resultado.mensaje || "Categoría guardada correctamente.",
                "exito",
                elementos.mensajeAdministrarCategorias
            );
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible guardar la categoría.",
                "error",
                elementos.mensajeAdministrarCategorias
            );
        } finally {
            guardandoCategoria = false;
            elementos.btnGuardarCategoria.disabled = false;

            elementos.btnGuardarCategoria.textContent =
                elementos.idCategoria.value
                    ? "Guardar cambios"
                    : "Crear categoría";
        }
    }

    async function cambiarEstadoCategoria(idCategoria, activar) {
        const textoAccion = activar ? "activar" : "desactivar";

        if (!window.confirm("¿Deseas " + textoAccion + " esta categoría?")) {
            return;
        }

        try {
            const datos = crearDatosPOST(
                "cambiar_estado_categoria",
                {
                    id_categoria: idCategoria,
                    activo: activar ? "1" : "0"
                }
            );

            const resultado = await solicitarPOST(
                API_TUTORIALES_URL,
                datos
            );

            actualizarToken(resultado);

            await cargarCategorias([]);

            mostrarMensaje(
                resultado.mensaje ||
                    "El estado de la categoría se actualizó.",
                "exito",
                elementos.mensajeAdministrarCategorias
            );
        } catch (error) {
            mostrarMensaje(
                error.message ||
                    "No fue posible cambiar el estado de la categoría.",
                "error",
                elementos.mensajeAdministrarCategorias
            );
        }
    }

    function abrirModalCategorias() {
        limpiarMensaje(elementos.mensajeAdministrarCategorias);
        prepararNuevaCategoria();

        elementos.modalCategorias.hidden = false;
        document.body.style.overflow = "hidden";

        cargarCategorias(categoriasSeleccionadasEdicion).catch(function () {
            /* El error ya se muestra en la interfaz. */
        });
    }

    function cerrarModalCategorias() {
        elementos.modalCategorias.hidden = true;

        if (!elementos.modal || elementos.modal.hidden) {
            document.body.style.overflow = "";
        }

        prepararNuevaCategoria();
    }

    /*
     * =========================================================
     * ETIQUETAS DE ESTADO
     * =========================================================
     */

    function textoEstado(estado) {
        const etiquetas = {
            borrador: "Borrador",
            publicada: "Publicado",
            inactiva: "Inactivo",
            papelera: "En papelera"
        };

        return etiquetas[estado] || estado || "Desconocido";
    }

    function crearEtiquetaEstado(estado) {
        const clases = {
            borrador: "tutoriales-estado-borrador",
            publicada: "tutoriales-estado-publicada",
            inactiva: "tutoriales-estado-inactiva",
            papelera: "tutoriales-estado-papelera"
        };

        return crearElemento(
            "span",
            "tutoriales-estado " + (clases[estado] || ""),
            textoEstado(estado)
        );
    }

    /*
     * =========================================================
     * ACCIONES DE LOS TUTORIALES
     * =========================================================
     */

    function crearBotonAccion(texto, clase, callback) {
        const boton = crearElemento("button", clase, texto);

        boton.type = "button";
        boton.addEventListener("click", callback);

        return boton;
    }

    function crearAccionesTutorial(tutorial) {
        const contenedor = crearElemento("div", "tutoriales-acciones");

        const id = Number(tutorial.id_tutorial);
        const estado = String(tutorial.estado || "");
        const estaEnPapelera = estado === "papelera";

        if (!estaEnPapelera) {
            contenedor.appendChild(
                crearBotonAccion("Editar", "accion-editar", function () {
                    abrirEdicionTutorial(id);
                })
            );

            if (estado === "publicada") {
                contenedor.appendChild(
                    crearBotonAccion(
                        "Desactivar",
                        "accion-inactivar",
                        function () {
                            cambiarEstadoTutorial(id, "inactiva");
                        }
                    )
                );
            } else {
                contenedor.appendChild(
                    crearBotonAccion(
                        "Publicar",
                        "accion-publicar",
                        function () {
                            cambiarEstadoTutorial(id, "publicada");
                        }
                    )
                );
            }

            if (estado !== "borrador") {
                contenedor.appendChild(
                    crearBotonAccion("Borrador", "", function () {
                        cambiarEstadoTutorial(id, "borrador");
                    })
                );
            }

            contenedor.appendChild(
                crearBotonAccion(
                    "Papelera",
                    "accion-papelera",
                    function () {
                        enviarPapeleraTutorial(id, tutorial.titulo);
                    }
                )
            );
        } else {
            contenedor.appendChild(
                crearBotonAccion(
                    "Restaurar",
                    "accion-publicar",
                    function () {
                        restaurarTutorial(id);
                    }
                )
            );

            contenedor.appendChild(
                crearBotonAccion(
                    "Eliminar definitivamente",
                    "accion-eliminar",
                    function () {
                        eliminarDefinitivamenteTutorial(id, tutorial.titulo);
                    }
                )
            );
        }

        return contenedor;
    }

    /*
     * =========================================================
     * TABLA DE TUTORIALES
     * =========================================================
     */

    function renderizarTutoriales(tutoriales) {
        if (!elementos.tabla) {
            return;
        }

        elementos.tabla.replaceChildren();

        if (!Array.isArray(tutoriales) || tutoriales.length === 0) {
            const fila = document.createElement("tr");

            const celda = crearElemento(
                "td",
                "tutoriales-tabla-vacia",
                "No hay tutoriales que coincidan con los filtros seleccionados."
            );

            celda.colSpan = 7;
            fila.appendChild(celda);
            elementos.tabla.appendChild(fila);

            return;
        }

        const fragmento = document.createDocumentFragment();

        tutoriales.forEach(function (tutorial) {
            const fila = document.createElement("tr");

            /*
             * Columna: video.
             */

            const celdaVideo = document.createElement("td");

            if (tutorial.tipo_video === "youtube") {
                const icono = crearElemento(
                    "div",
                    "tutoriales-tabla-video",
                    "▶"
                );

                icono.setAttribute("aria-label", "Video de YouTube");
                celdaVideo.appendChild(icono);
            } else if (tutorial.tipo_video === "archivo") {
                const icono = crearElemento(
                    "div",
                    "tutoriales-tabla-video",
                    "▣"
                );

                icono.setAttribute(
                    "aria-label",
                    "Video guardado en el servidor"
                );

                celdaVideo.appendChild(icono);
            } else {
                celdaVideo.appendChild(
                    crearElemento(
                        "div",
                        "tutoriales-tabla-sin-video",
                        "Sin video"
                    )
                );
            }

            fila.appendChild(celdaVideo);

            /*
             * Columna: título y descripción.
             */

            const celdaTitulo = document.createElement("td");

            celdaTitulo.appendChild(
                crearElemento(
                    "span",
                    "tutoriales-tabla-titulo",
                    tutorial.titulo || "Sin título"
                )
            );

            if (tutorial.descripcion) {
                celdaTitulo.appendChild(
                    crearElemento(
                        "span",
                        "tutoriales-tabla-descripcion",
                        tutorial.descripcion
                    )
                );
            }

            if (
                tutorial.tipo_video === "archivo" &&
                tutorial.nombre_archivo_original
            ) {
                celdaTitulo.appendChild(
                    crearElemento(
                        "span",
                        "tutoriales-tabla-metadato",
                        String(tutorial.nombre_archivo_original) +
                            (
                                tutorial.tamano_bytes
                                    ? " · " + formatearTamano(
                                        tutorial.tamano_bytes
                                    )
                                    : ""
                            )
                    )
                );
            }

            fila.appendChild(celdaTitulo);

            /*
             * Columna: tipo de video.
             */

            const celdaTipo = document.createElement("td");
            const esYouTube = tutorial.tipo_video === "youtube";

            celdaTipo.appendChild(
                crearElemento(
                    "span",
                    "tutoriales-tipo " +
                        (
                            esYouTube
                                ? "tutoriales-tipo-youtube"
                                : "tutoriales-tipo-archivo"
                        ),
                    esYouTube ? "YouTube" : "Archivo local"
                )
            );

            fila.appendChild(celdaTipo);

            /*
             * Columna: categorías.
             */

            const celdaCategorias = document.createElement("td");

            const etiquetasCategorias = crearElemento(
                "div",
                "tutoriales-categorias-etiquetas"
            );

            const nombresCategorias = String(
                tutorial.categorias_texto || ""
            )
                .split(",")
                .map(function (nombre) {
                    return nombre.trim();
                })
                .filter(Boolean);

            if (!nombresCategorias.length) {
                etiquetasCategorias.appendChild(
                    crearElemento(
                        "span",
                        "tutoriales-sin-categorias",
                        "Sin categorías"
                    )
                );
            } else {
                nombresCategorias.forEach(function (nombre) {
                    etiquetasCategorias.appendChild(
                        crearElemento(
                            "span",
                            "tutoriales-categoria-etiqueta",
                            nombre
                        )
                    );
                });
            }

            celdaCategorias.appendChild(etiquetasCategorias);
            fila.appendChild(celdaCategorias);

            /*
             * Columna: estado.
             */

            const celdaEstado = document.createElement("td");

            celdaEstado.appendChild(crearEtiquetaEstado(tutorial.estado));

            fila.appendChild(celdaEstado);

            /*
             * Columna: fecha.
             */

            const celdaFecha = document.createElement("td");

            celdaFecha.textContent = tutorial.fecha_publicacion
                ? formatearFecha(tutorial.fecha_publicacion)
                : "Sin publicar";

            fila.appendChild(celdaFecha);

            /*
             * Columna: acciones.
             */

            const celdaAcciones = document.createElement("td");

            celdaAcciones.appendChild(crearAccionesTutorial(tutorial));

            fila.appendChild(celdaAcciones);
            fragmento.appendChild(fila);
        });

        elementos.tabla.appendChild(fragmento);
    }

    /*
     * =========================================================
     * PAGINACIÓN
     * =========================================================
     */

    function actualizarPaginacion(paginacion) {
        if (elementos.btnAnterior) {
            elementos.btnAnterior.disabled = paginaActual <= 1;
        }

        if (elementos.btnSiguiente) {
            elementos.btnSiguiente.disabled =
                paginaActual >= totalPaginas;
        }

        if (elementos.textoPaginacion) {
            elementos.textoPaginacion.textContent =
                "Página " + paginaActual + " de " + totalPaginas;
        }
    }

    /*
     * =========================================================
     * CARGAR TUTORIALES
     * =========================================================
     */

    async function cargarTutoriales(opciones = {}) {
        if (cargandoTutoriales) {
            return;
        }

        if (opciones.pagina !== undefined) {
            paginaActual = opciones.pagina;
        }

        cargandoTutoriales = true;

        if (elementos.cargando) {
            elementos.cargando.hidden = false;
        }

        if (elementos.btnActualizar) {
            elementos.btnActualizar.disabled = true;
        }

        if (elementos.tabla) {
            elementos.tabla.setAttribute("aria-busy", "true");
        }

        const parametros = new URLSearchParams();

        parametros.set("accion", "listar");
        parametros.set("pagina", String(paginaActual));
        parametros.set("por_pagina", String(cantidadPorPagina));

        parametros.set(
            "busqueda",
            elementos.busqueda?.value.trim() || ""
        );

        parametros.set("estado", elementos.estado?.value || "todas");
        parametros.set("tipo", elementos.tipo?.value || "todos");
        parametros.set("categoria", elementos.categoria?.value || "todas");

        try {
            const resultado = await solicitarGET(
                API_TUTORIALES_URL + "?" + parametros.toString()
            );

            actualizarToken(resultado);

            const datos = obtenerDatos(resultado);

            tutorialesActuales = Array.isArray(datos.tutoriales)
                ? datos.tutoriales
                : Array.isArray(resultado.tutoriales)
                    ? resultado.tutoriales
                    : [];

            const paginacion =
                datos.paginacion || resultado.paginacion || {};

            paginaActual = Number(paginacion.pagina || 1);
            totalPaginas = Number(paginacion.total_paginas || 1);

            cantidadPorPagina = Number(
                paginacion.por_pagina || cantidadPorPagina
            );

            renderizarTutoriales(tutorialesActuales);
            actualizarPaginacion(paginacion);

            if (elementos.resumen) {
                const total = Number(paginacion.total || 0);

                elementos.resumen.textContent =
                    total === 1
                        ? "Se encontró 1 tutorial."
                        : "Se encontraron " + total + " tutoriales.";
            }

            if (opciones.mensaje) {
                mostrarMensaje(opciones.mensaje, "exito");
            }
        } catch (error) {
            elementos.tabla?.replaceChildren();

            if (elementos.tabla) {
                const fila = document.createElement("tr");

                const celda = crearElemento(
                    "td",
                    "tutoriales-tabla-vacia",
                    error.message || "No fue posible cargar los tutoriales."
                );

                celda.colSpan = 7;

                fila.appendChild(celda);
                elementos.tabla.appendChild(fila);
            }

            if (elementos.resumen) {
                elementos.resumen.textContent =
                    "No fue posible cargar el listado.";
            }

            mostrarMensaje(
                error.message || "No fue posible cargar los tutoriales.",
                "error"
            );
        } finally {
            cargandoTutoriales = false;

            if (elementos.cargando) {
                elementos.cargando.hidden = true;
            }

            if (elementos.btnActualizar) {
                elementos.btnActualizar.disabled = false;
            }

            if (elementos.tabla) {
                elementos.tabla.removeAttribute("aria-busy");
            }
        }
    }

    /*
     * =========================================================
     * ABRIR Y CERRAR MODAL DE TUTORIAL
     * =========================================================
     */

    function abrirModalTutorial() {
        elementos.modal.hidden = false;
        document.body.style.overflow = "hidden";
    }

    function cerrarModalTutorial() {
        if (!elementos.modal) {
            return;
        }

        elementos.modal.hidden = true;

        if (!elementos.modalCategorias || elementos.modalCategorias.hidden) {
            document.body.style.overflow = "";
        }

        limpiarArchivoSeleccionado();
        ocultarVistaPreviaVideo();
    }

    async function prepararFormularioNuevo() {
        if (guardandoTutorial) {
            return;
        }

        limpiarArchivoSeleccionado();
        ocultarVistaPreviaVideo();

        elementos.formulario.reset();

        elementos.idTutorial.value = "";
        elementos.tipoVideo.value = "archivo";

        tipoOriginalTutorial = "";
        rutaOriginalTutorial = "";
        categoriasSeleccionadasEdicion = [];

        elementos.tituloModal.textContent = "Nuevo tutorial";

        elementos.descripcionModal.textContent =
            "Completa los datos y selecciona cómo incorporar el video.";

        elementos.btnGuardar.textContent = "Guardar borrador";

        limpiarMensaje();
        mostrarEstadoCargaVideo("");

        actualizarBloquesTipoVideo();

        mostrarMensajeVistaPrevia(
            "Selecciona un archivo de video o pega el código iframe de YouTube."
        );

        if (elementos.mensajeCategorias) {
            elementos.mensajeCategorias.textContent = "";
        }

        try {
            await cargarCategorias([]);

            renderizarCategoriasFormulario([]);

            abrirModalTutorial();

            elementos.titulo.focus();
        } catch (error) {
            abrirModalTutorial();
            elementos.titulo.focus();
        }
    }

    /*
     * =========================================================
     * CAMBIAR ENTRE ARCHIVO Y YOUTUBE
     * =========================================================
     */

    function actualizarBloquesTipoVideo() {
        const esYouTube = elementos.tipoVideo.value === "youtube";

        elementos.bloqueArchivo.hidden = esYouTube;
        elementos.bloqueYoutube.hidden = !esYouTube;

        if (elementos.urlYoutube) {
            elementos.urlYoutube.required = esYouTube;
        }

        /*
         * El archivo local puede conservarse al editar un tutorial.
         * Si se cambia a YouTube, se quita un archivo recién seleccionado.
         */
        if (esYouTube) {
            if (elementos.archivoVideo.files?.length) {
                elementos.archivoVideo.value = "";
            }

            liberarVistaPreviaLocal();
        }

        actualizarVistaPreviaActual();
    }

    /*
     * =========================================================
     * CREAR CÓDIGO IFRAME PARA UN TUTORIAL EXISTENTE
     *
     * Se genera una cadena segura a partir del ID guardado.
     * Se muestra como texto en el textarea.
     * =========================================================
     */

    function generarCodigoIframeYouTube(idVideo) {
        const id = String(idVideo || "");

        if (!/^[A-Za-z0-9_-]{11}$/.test(id)) {
            return "";
        }

        return (
            '<iframe width="560" height="315" ' +
            'src="https://www.youtube.com/embed/' + id + '" ' +
            'title="Reproductor de video de YouTube" ' +
            'frameborder="0" ' +
            'allow="accelerometer; autoplay; clipboard-write; ' +
            'encrypted-media; gyroscope; picture-in-picture; web-share" ' +
            'referrerpolicy="strict-origin-when-cross-origin" ' +
            'allowfullscreen></iframe>'
        );
    }

    /*
     * =========================================================
     * EDITAR TUTORIAL
     * =========================================================
     */

    async function abrirEdicionTutorial(idTutorial) {
        if (!tokenTutoriales) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        limpiarMensaje();

        try {
            const resultado = await solicitarGET(
                API_TUTORIALES_URL +
                "?accion=obtener&id=" +
                encodeURIComponent(String(idTutorial))
            );

            actualizarToken(resultado);

            const datos = obtenerDatos(resultado);

            const tutorial = resultado.tutorial || datos.tutorial;

            if (!tutorial) {
                throw new Error(
                    "No se pudo obtener el tutorial seleccionado."
                );
            }

            limpiarArchivoSeleccionado();
            ocultarVistaPreviaVideo();

            elementos.formulario.reset();

            elementos.idTutorial.value = tutorial.id_tutorial;
            elementos.titulo.value = tutorial.titulo || "";
            elementos.descripcion.value = tutorial.descripcion || "";

            elementos.tipoVideo.value = tutorial.tipo_video || "archivo";

            tipoOriginalTutorial = tutorial.tipo_video || "";
            rutaOriginalTutorial = tutorial.ruta_video || "";

            categoriasSeleccionadasEdicion = Array.isArray(
                tutorial.categorias
            )
                ? tutorial.categorias.map(function (categoria) {
                    return String(categoria.id_categoria);
                })
                : [];

            elementos.tituloModal.textContent = "Editar tutorial";

            elementos.descripcionModal.textContent =
                "Modifica los datos del tutorial y guarda los cambios.";

            elementos.btnGuardar.textContent = "Guardar cambios";

            /*
             * Para un video de YouTube existente, se muestra un iframe
             * generado a partir de su ID, para editar con el mismo formato.
             */
            elementos.urlYoutube.value =
                tutorial.youtube_id
                    ? generarCodigoIframeYouTube(tutorial.youtube_id)
                    : "";

            mostrarEstadoCargaVideo("");

            actualizarBloquesTipoVideo();

            await cargarCategorias(categoriasSeleccionadasEdicion);

            renderizarCategoriasFormulario(
                categoriasSeleccionadasEdicion
            );

            if (
                tutorial.tipo_video === "youtube" &&
                /^[A-Za-z0-9_-]{11}$/.test(
                    String(tutorial.youtube_id || "")
                )
            ) {
                mostrarVistaPreviaYouTube(
                    String(tutorial.youtube_id)
                );
            } else if (
                tutorial.tipo_video === "archivo" &&
                tutorial.ruta_video
            ) {
                mostrarVistaPreviaArchivoExistente(
                    tutorial.ruta_video,
                    tutorial.nombre_archivo_original
                );
            } else {
                mostrarMensajeVistaPrevia(
                    "Selecciona un archivo o pega el código iframe de YouTube."
                );
            }

            abrirModalTutorial();
            elementos.titulo.focus();
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible abrir el tutorial.",
                "error"
            );
        }
    }

    /*
     * =========================================================
     * GUARDAR TUTORIAL
     * =========================================================
     */

    async function guardarTutorial(evento) {
        evento.preventDefault();

        if (guardandoTutorial) {
            return;
        }

        if (!tokenTutoriales) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        const titulo = elementos.titulo.value.trim();
        const tipoVideo = elementos.tipoVideo.value;

        if (!titulo) {
            elementos.titulo.focus();

            mostrarMensaje(
                "Debes ingresar el título del tutorial.",
                "error"
            );

            return;
        }

        if (titulo.length > 180) {
            mostrarMensaje(
                "El título no puede superar los 180 caracteres.",
                "error"
            );

            elementos.titulo.focus();
            return;
        }

        const archivo = elementos.archivoVideo.files?.[0] || null;

        if (tipoVideo === "archivo") {
            if (archivo) {
                const errorArchivo = validarArchivoVideo(archivo);

                if (errorArchivo) {
                    mostrarEstadoCargaVideo(errorArchivo);
                    return;
                }
            } else {
                const puedeConservarArchivo =
                    Boolean(elementos.idTutorial.value) &&
                    tipoOriginalTutorial === "archivo" &&
                    Boolean(rutaOriginalTutorial);

                if (!puedeConservarArchivo) {
                    mostrarEstadoCargaVideo(
                        "Selecciona un archivo de video antes de continuar."
                    );

                    elementos.archivoVideo.focus();
                    return;
                }
            }
        }

        if (tipoVideo === "youtube") {
            const codigoIframe = elementos.urlYoutube.value.trim();

            if (!obtenerIdYouTube(codigoIframe)) {
                mostrarMensaje(
                    "Pega el código iframe válido de YouTube. Lo encontrarás en Compartir > Incorporar.",
                    "error"
                );

                elementos.urlYoutube.focus();
                return;
            }
        }

        guardandoTutorial = true;

        elementos.btnGuardar.disabled = true;

        elementos.btnGuardar.textContent = archivo
            ? "Subiendo video..."
            : "Guardando...";

        mostrarEstadoCargaVideo(
            archivo
                ? "Preparando la subida del video..."
                : ""
        );

        try {
            const datos = new FormData(elementos.formulario);

            datos.set("accion", "guardar");
            datos.set("csrf_token", tokenTutoriales);

            /*
             * El campo youtube_url contiene el código iframe.
             * La API extrae el ID y guarda únicamente ese ID.
             */
            if (tipoVideo === "youtube") {
                datos.delete("archivo_video");
            } else {
                datos.delete("youtube_url");
            }

            const resultado = await solicitarPOST(
                API_TUTORIALES_URL,
                datos
            );

            actualizarToken(resultado);

            cerrarModalTutorial();

            categoriasSeleccionadasEdicion = [];

            await cargarTutoriales({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "El tutorial se guardó correctamente."
            });

            await cargarCategorias([]);
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible guardar el tutorial.",
                "error"
            );

            mostrarEstadoCargaVideo(
                error.message || "No fue posible guardar el video."
            );
        } finally {
            guardandoTutorial = false;

            elementos.btnGuardar.disabled = false;

            elementos.btnGuardar.textContent =
                elementos.idTutorial.value
                    ? "Guardar cambios"
                    : "Guardar borrador";
        }
    }

    /*
     * =========================================================
     * CAMBIAR ESTADO
     * =========================================================
     */

    async function cambiarEstadoTutorial(idTutorial, estado) {
        if (!tokenTutoriales) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        const acciones = {
            publicada: "publicar",
            inactiva: "desactivar",
            borrador: "devolver a borrador"
        };

        const accionTexto = acciones[estado] || "cambiar el estado de";

        if (
            !window.confirm(
                "¿Deseas " + accionTexto + " este tutorial?"
            )
        ) {
            return;
        }

        limpiarMensaje();

        try {
            const datos = crearDatosPOST("cambiar_estado", {
                id_tutorial: idTutorial,
                estado: estado
            });

            const resultado = await solicitarPOST(
                API_TUTORIALES_URL,
                datos
            );

            actualizarToken(resultado);

            await cargarTutoriales({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "El estado del tutorial se actualizó."
            });
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible cambiar el estado.",
                "error"
            );
        }
    }

    /*
     * =========================================================
     * PAPELERA
     * =========================================================
     */

    async function enviarPapeleraTutorial(idTutorial, titulo) {
        const confirmado = window.confirm(
            '¿Enviar el tutorial "' +
                escaparTexto(titulo || "Sin título") +
                '" a la papelera?\n\n' +
                "Podrás recuperarlo después. El archivo no se eliminará."
        );

        if (!confirmado) {
            return;
        }

        try {
            const datos = crearDatosPOST("enviar_papelera", {
                id_tutorial: idTutorial
            });

            const resultado = await solicitarPOST(
                API_TUTORIALES_URL,
                datos
            );

            actualizarToken(resultado);

            await cargarTutoriales({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "El tutorial se envió a la papelera."
            });
        } catch (error) {
            mostrarMensaje(
                error.message ||
                    "No fue posible enviar el tutorial a la papelera.",
                "error"
            );
        }
    }

    async function restaurarTutorial(idTutorial) {
        if (
            !window.confirm(
                "¿Deseas restaurar este tutorial desde la papelera?"
            )
        ) {
            return;
        }

        try {
            const datos = crearDatosPOST("restaurar", {
                id_tutorial: idTutorial
            });

            const resultado = await solicitarPOST(
                API_TUTORIALES_URL,
                datos
            );

            actualizarToken(resultado);

            await cargarTutoriales({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "El tutorial se restauró correctamente."
            });
        } catch (error) {
            mostrarMensaje(
                error.message ||
                    "No fue posible restaurar el tutorial.",
                "error"
            );
        }
    }

    async function eliminarDefinitivamenteTutorial(
        idTutorial,
        titulo
    ) {
        const confirmado = window.confirm(
            '¿Eliminar definitivamente el tutorial "' +
                escaparTexto(titulo || "Sin título") +
                '"?\n\n' +
                "Esta acción no se puede deshacer. También se intentará eliminar el archivo de video guardado en el servidor."
        );

        if (!confirmado) {
            return;
        }

        try {
            const datos = crearDatosPOST(
                "eliminar_definitivamente",
                {
                    id_tutorial: idTutorial
                }
            );

            const resultado = await solicitarPOST(
                API_TUTORIALES_URL,
                datos
            );

            actualizarToken(resultado);

            await cargarTutoriales({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "El tutorial fue eliminado definitivamente."
            });
        } catch (error) {
            mostrarMensaje(
                error.message ||
                    "No fue posible eliminar definitivamente el tutorial.",
                "error"
            );
        }
    }

    /*
     * =========================================================
     * EVENTOS DE LA INTERFAZ
     * =========================================================
     */

    if (elementos.btnNuevo) {
        elementos.btnNuevo.addEventListener(
            "click",
            prepararFormularioNuevo
        );
    }

    if (elementos.btnActualizar) {
        elementos.btnActualizar.addEventListener("click", function () {
            cargarTutoriales({ pagina: paginaActual });

            cargarCategorias(categoriasSeleccionadasEdicion).catch(
                function () {}
            );
        });
    }

    if (elementos.busqueda) {
        elementos.busqueda.addEventListener("input", function () {
            window.clearTimeout(temporizadorBusqueda);

            temporizadorBusqueda = window.setTimeout(function () {
                cargarTutoriales({ pagina: 1 });
            }, 350);
        });
    }

    if (elementos.estado) {
        elementos.estado.addEventListener("change", function () {
            cargarTutoriales({ pagina: 1 });
        });
    }

    if (elementos.tipo) {
        elementos.tipo.addEventListener("change", function () {
            cargarTutoriales({ pagina: 1 });
        });
    }

    if (elementos.categoria) {
        elementos.categoria.addEventListener("change", function () {
            cargarTutoriales({ pagina: 1 });
        });
    }

    if (elementos.cantidad) {
        elementos.cantidad.addEventListener("change", function () {
            cantidadPorPagina =
                Number(elementos.cantidad.value) || 10;

            cargarTutoriales({ pagina: 1 });
        });
    }

    if (elementos.btnAnterior) {
        elementos.btnAnterior.addEventListener("click", function () {
            if (paginaActual > 1) {
                cargarTutoriales({ pagina: paginaActual - 1 });
            }
        });
    }

    if (elementos.btnSiguiente) {
        elementos.btnSiguiente.addEventListener("click", function () {
            if (paginaActual < totalPaginas) {
                cargarTutoriales({ pagina: paginaActual + 1 });
            }
        });
    }

    if (elementos.btnCerrarModal) {
        elementos.btnCerrarModal.addEventListener(
            "click",
            cerrarModalTutorial
        );
    }

    if (elementos.btnCancelarModal) {
        elementos.btnCancelarModal.addEventListener(
            "click",
            cerrarModalTutorial
        );
    }

    if (elementos.modal) {
        elementos.modal
            .querySelectorAll("[data-cerrar-modal-tutorial]")
            .forEach(function (elemento) {
                elemento.addEventListener("click", cerrarModalTutorial);
            });
    }

    if (elementos.formulario) {
        elementos.formulario.addEventListener(
            "submit",
            guardarTutorial
        );
    }

    if (elementos.tipoVideo) {
        elementos.tipoVideo.addEventListener(
            "change",
            actualizarBloquesTipoVideo
        );
    }

    if (elementos.archivoVideo) {
        elementos.archivoVideo.addEventListener(
            "change",
            manejarSeleccionArchivo
        );
    }

    /*
     * Actualizar la vista previa al pegar o modificar el iframe.
     */
    if (elementos.urlYoutube) {
        elementos.urlYoutube.addEventListener("input", function () {
            if (elementos.tipoVideo.value === "youtube") {
                actualizarVistaPreviaYouTube();
            }
        });

        elementos.urlYoutube.addEventListener("change", function () {
            if (elementos.tipoVideo.value === "youtube") {
                actualizarVistaPreviaYouTube();
            }
        });
    }

    /*
     * Eventos del modal de categorías.
     */

    if (elementos.btnAdministrarCategorias) {
        elementos.btnAdministrarCategorias.addEventListener(
            "click",
            abrirModalCategorias
        );
    }

    if (elementos.btnCerrarModalCategorias) {
        elementos.btnCerrarModalCategorias.addEventListener(
            "click",
            cerrarModalCategorias
        );
    }

    if (elementos.modalCategorias) {
        elementos.modalCategorias
            .querySelectorAll("[data-cerrar-modal-categorias]")
            .forEach(function (elemento) {
                elemento.addEventListener(
                    "click",
                    cerrarModalCategorias
                );
            });
    }

    if (elementos.formularioCategoria) {
        elementos.formularioCategoria.addEventListener(
            "submit",
            guardarCategoria
        );
    }

    if (elementos.btnCancelarEdicionCategoria) {
        elementos.btnCancelarEdicionCategoria.addEventListener(
            "click",
            prepararNuevaCategoria
        );
    }

    if (elementos.btnActualizarCategorias) {
        elementos.btnActualizarCategorias.addEventListener(
            "click",
            function () {
                cargarCategorias(categoriasSeleccionadasEdicion).catch(
                    function () {}
                );
            }
        );
    }

    /*
     * Cerrar modales al pulsar Escape.
     */

    document.addEventListener("keydown", function (evento) {
        if (
            evento.key !== "Escape" ||
            guardandoTutorial ||
            guardandoCategoria
        ) {
            return;
        }

        if (
            elementos.modalCategorias &&
            !elementos.modalCategorias.hidden
        ) {
            cerrarModalCategorias();
            return;
        }

        if (elementos.modal && !elementos.modal.hidden) {
            cerrarModalTutorial();
        }
    });

    /*
     * Liberar URLs temporales al abandonar la página.
     */

    window.addEventListener("beforeunload", function () {
        liberarVistaPreviaLocal();
    });

    /*
     * =========================================================
     * INICIO
     * =========================================================
     */

    cargarCategorias([]).catch(function () {
        /* El error ya se muestra en la interfaz. */
    });

    cargarTutoriales({ pagina: 1 });
});