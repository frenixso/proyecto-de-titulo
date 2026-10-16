
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    /*
     * =========================================================
     * ADMINISTRACIÓN DE NOTICIAS
     * CESFAM EDUARDO RAGGIO CATEMU
     *
     * Archivo: js/administrar_noticias.js
     *
     * Funciones:
     * - Listar, buscar y filtrar noticias.
     * - Navegar mediante paginación.
     * - Crear y editar noticias.
     * - Seleccionar imágenes de la biblioteca multimedia.
     * - Subir imágenes directamente desde el computador.
     * - Mostrar una vista previa de la imagen seleccionada.
     * - Destacar y quitar el destacado.
     * - Publicar, desactivar y devolver a borrador.
     * - Enviar noticias a la papelera y restaurarlas.
     * - Eliminar definitivamente noticias desde la papelera.
     * =========================================================
     */

    const API_NOTICIAS_URL = "../api/web_noticias.php";
    const API_MULTIMEDIA_URL = "../api/web_multimedia.php";

    const LIMITE_IMAGEN_BYTES = 5 * 1024 * 1024;

    const TIPOS_IMAGEN_PERMITIDOS = [
        "image/jpeg",
        "image/png",
        "image/webp",
        "image/gif"
    ];

    let tokenNoticias = "";
    let tokenMultimedia = "";

    let noticiasActuales = [];
    let paginaActual = 1;
    let totalPaginas = 1;
    let cantidadPorPagina = 10;

    let cargandoNoticias = false;
    let guardandoNoticia = false;
    let temporizadorBusqueda = null;
    let urlVistaPrevia = null;

    /*
     * =========================================================
     * ELEMENTOS DEL DOM
     * =========================================================
     */

    const elementos = {
        btnNueva: document.getElementById("btnNuevaNoticia"),
        btnActualizar: document.getElementById("btnActualizarNoticias"),

        busqueda: document.getElementById("filtroBusquedaNoticias"),
        estado: document.getElementById("filtroEstadoNoticias"),
        destacada: document.getElementById("filtroDestacadaNoticias"),
        cantidad: document.getElementById("filtroCantidadNoticias"),

        mensaje: document.getElementById("mensajeNoticias"),
        resumen: document.getElementById("resumenListadoNoticias"),
        cargando: document.getElementById("cargandoNoticias"),
        tabla: document.getElementById("tablaNoticiasBody"),

        btnAnterior: document.getElementById("btnPaginaAnteriorNoticias"),
        btnSiguiente: document.getElementById("btnPaginaSiguienteNoticias"),
        textoPaginacion: document.getElementById("textoPaginacionNoticias"),

        modal: document.getElementById("modalNoticia"),
        tituloModal: document.getElementById("tituloModalNoticia"),
        btnCerrarModal: document.getElementById("btnCerrarModalNoticia"),
        btnCancelarModal: document.getElementById("btnCancelarModalNoticia"),

        formulario: document.getElementById("formNoticia"),
        idNoticia: document.getElementById("idNoticia"),
        titulo: document.getElementById("inputTituloNoticia"),
        resumenNoticia: document.getElementById("inputResumenNoticia"),
        contenido: document.getElementById("inputContenidoNoticia"),

        imagen: document.getElementById("selectImagenNoticia"),
        archivoImagen: document.getElementById("inputImagenArchivoNoticia"),
        estadoCargaImagen: document.getElementById("estadoCargaImagenNoticia"),

        textoAlternativo: document.getElementById("inputTextoAlternativoNoticia"),
        destacadaNoticia: document.getElementById("inputDestacadaNoticia"),

        contenedorVistaPrevia: document.getElementById("contenedorVistaPreviaNoticia"),
        imagenVistaPrevia: document.getElementById("imagenVistaPreviaNoticia"),
        nombreVistaPrevia: document.getElementById("nombreVistaPreviaNoticia"),

        btnGuardar: document.getElementById("btnGuardarNoticia")
    };

    /*
     * =========================================================
     * UTILIDADES
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

    function mostrarMensaje(mensaje, tipo = "info") {
        if (!elementos.mensaje) {
            console.info("[Administración de noticias]", mensaje);
            return;
        }

        elementos.mensaje.textContent = mensaje;
        elementos.mensaje.className = "noticias-mensaje";
        elementos.mensaje.classList.add("mensaje-" + tipo);
        elementos.mensaje.hidden = false;
    }

    function limpiarMensaje() {
        if (!elementos.mensaje) {
            return;
        }

        elementos.mensaje.textContent = "";
        elementos.mensaje.hidden = true;
        elementos.mensaje.className = "noticias-mensaje";
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
        datos.set("csrf_token", tokenNoticias);

        Object.entries(datosAdicionales).forEach(function ([clave, valor]) {
            if (valor !== null && valor !== undefined) {
                datos.set(clave, String(valor));
            }
        });

        return datos;
    }

    function obtenerRutaImagen(rutaRelativa) {
        const ruta = String(rutaRelativa || "");

        if (
            !ruta.startsWith("uploads/multimedia/") ||
            ruta.includes("..") ||
            ruta.includes("\\") ||
            ruta.startsWith("/") ||
            /^[a-z][a-z0-9+.-]*:/i.test(ruta)
        ) {
            return null;
        }

        return "../" + ruta;
    }

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

    function escaparTextoNoNecesario(valor) {
        /*
         * El contenido se inserta con textContent, no con innerHTML.
         * Esta función se conserva como conversión a texto.
         */
        return String(valor ?? "");
    }

    /*
     * =========================================================
     * GESTIÓN DE LA VISTA PREVIA LOCAL
     * =========================================================
     */

    function liberarVistaPreviaLocal() {
        if (urlVistaPrevia) {
            URL.revokeObjectURL(urlVistaPrevia);
            urlVistaPrevia = null;
        }
    }

    function limpiarEstadoCargaImagen() {
        if (elementos.estadoCargaImagen) {
            elementos.estadoCargaImagen.textContent = "";
        }
    }

    function mostrarEstadoCargaImagen(mensaje) {
        if (elementos.estadoCargaImagen) {
            elementos.estadoCargaImagen.textContent = mensaje;
        }
    }

    function limpiarArchivoSeleccionado() {
        liberarVistaPreviaLocal();

        if (elementos.archivoImagen) {
            elementos.archivoImagen.value = "";
        }

        limpiarEstadoCargaImagen();
    }

    function validarArchivoImagen(archivo) {
        if (!archivo) {
            return "Selecciona una imagen antes de continuar.";
        }

        if (archivo.size <= 0) {
            return "El archivo está vacío o no tiene un tamaño válido.";
        }

        if (archivo.size > LIMITE_IMAGEN_BYTES) {
            return "La imagen supera el límite permitido de 5 MB.";
        }

        /*
         * La comprobación definitiva del formato se realiza también
         * en PHP mediante el MIME real del archivo.
         *
         * Si el navegador no informa el MIME, permitimos que el
         * servidor realice la comprobación definitiva.
         */

        const tipoMime = String(archivo.type || "").toLowerCase();

        if (
            tipoMime !== "" &&
            !TIPOS_IMAGEN_PERMITIDOS.includes(tipoMime)
        ) {
            return "Formato no permitido. Utiliza JPG, PNG, WEBP o GIF.";
        }

        return "";
    }

    function mostrarVistaPreviaArchivo(archivo) {
        if (
            !archivo ||
            !elementos.contenedorVistaPrevia ||
            !elementos.imagenVistaPrevia
        ) {
            return;
        }

        liberarVistaPreviaLocal();

        urlVistaPrevia = URL.createObjectURL(archivo);

        elementos.contenedorVistaPrevia.hidden = false;
        elementos.imagenVistaPrevia.src = urlVistaPrevia;

        elementos.imagenVistaPrevia.alt =
            elementos.textoAlternativo?.value.trim() ||
            archivo.name ||
            "Vista previa de la imagen seleccionada";

        if (elementos.nombreVistaPrevia) {
            elementos.nombreVistaPrevia.textContent =
                archivo.name + " — Se subirá al guardar la noticia";
        }

        elementos.imagenVistaPrevia.onerror = function () {
            liberarVistaPreviaLocal();

            elementos.imagenVistaPrevia.removeAttribute("src");
            elementos.contenedorVistaPrevia.hidden = true;

            mostrarEstadoCargaImagen(
                "No se pudo mostrar la vista previa del archivo seleccionado."
            );
        };
    }

    /*
     * =========================================================
     * CARGAR NOTICIAS
     * =========================================================
     */

    async function cargarNoticias(opciones = {}) {
        if (cargandoNoticias) {
            return;
        }

        if (opciones.pagina !== undefined) {
            paginaActual = opciones.pagina;
        }

        cargandoNoticias = true;

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
        parametros.set("busqueda", elementos.busqueda?.value.trim() || "");
        parametros.set("estado", elementos.estado?.value || "todas");
        parametros.set("destacada", elementos.destacada?.value || "todas");

        try {
            const resultado = await solicitarGET(
                API_NOTICIAS_URL + "?" + parametros.toString()
            );

            const datos = obtenerDatos(resultado);

            if (resultado.csrf_token) {
                tokenNoticias = String(resultado.csrf_token);
            } else if (datos.csrf_token) {
                tokenNoticias = String(datos.csrf_token);
            }

            noticiasActuales = Array.isArray(resultado.noticias)
                ? resultado.noticias
                : Array.isArray(datos.noticias)
                    ? datos.noticias
                    : [];

            const paginacion = resultado.paginacion || datos.paginacion || {};

            paginaActual = Number(paginacion.pagina || 1);
            totalPaginas = Number(paginacion.total_paginas || 1);
            cantidadPorPagina = Number(
                paginacion.por_pagina || cantidadPorPagina
            );

            renderizarNoticias(noticiasActuales);
            actualizarPaginacion(paginacion);

            if (elementos.resumen) {
                const total = Number(paginacion.total || 0);

                elementos.resumen.textContent =
                    total === 1
                        ? "Se encontró 1 noticia."
                        : "Se encontraron " + total + " noticias.";
            }

            if (opciones.mensaje) {
                mostrarMensaje(opciones.mensaje, "exito");
            }
        } catch (error) {
            if (elementos.tabla) {
                elementos.tabla.replaceChildren();

                const fila = document.createElement("tr");
                const celda = crearElemento(
                    "td",
                    "noticias-tabla-vacia",
                    error.message || "No fue posible cargar las noticias."
                );

                celda.colSpan = 6;
                fila.appendChild(celda);
                elementos.tabla.appendChild(fila);
            }

            if (elementos.resumen) {
                elementos.resumen.textContent =
                    "No fue posible cargar el listado.";
            }

            mostrarMensaje(
                error.message || "No fue posible cargar las noticias.",
                "error"
            );
        } finally {
            cargandoNoticias = false;

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
     * ETIQUETAS
     * =========================================================
     */

    function textoEstado(estado) {
        const etiquetas = {
            borrador: "Borrador",
            publicada: "Publicada",
            inactiva: "Inactiva",
            papelera: "En papelera"
        };

        return etiquetas[estado] || estado || "Desconocido";
    }

    function crearEtiquetaEstado(estado) {
        const clases = {
            borrador: "noticias-estado-borrador",
            publicada: "noticias-estado-publicada",
            inactiva: "noticias-estado-inactiva",
            papelera: "noticias-estado-papelera"
        };

        const etiqueta = crearElemento(
            "span",
            "noticias-estado " + (clases[estado] || ""),
            textoEstado(estado)
        );

        return etiqueta;
    }

    /*
     * =========================================================
     * ACCIONES DE LAS FILAS
     * =========================================================
     */

    function crearBotonAccion(texto, clase, callback) {
        const boton = crearElemento("button", clase, texto);

        boton.type = "button";

        boton.addEventListener("click", callback);

        return boton;
    }

    function crearAccionesNoticia(noticia) {
        const contenedor = crearElemento("div", "noticias-acciones");

        const id = Number(noticia.id_noticia);
        const estado = String(noticia.estado || "");
        const estaEnPapelera = estado === "papelera";

        if (!estaEnPapelera) {
            contenedor.appendChild(
                crearBotonAccion("Editar", "accion-editar", function () {
                    abrirEdicionNoticia(id);
                })
            );

            const textoDestacada = esVerdadero(noticia.destacada)
                ? "Quitar destacado"
                : "Destacar";

            contenedor.appendChild(
                crearBotonAccion(
                    textoDestacada,
                    "accion-destacar",
                    function () {
                        cambiarDestacada(id, !esVerdadero(noticia.destacada));
                    }
                )
            );

            if (estado === "publicada") {
                contenedor.appendChild(
                    crearBotonAccion(
                        "Desactivar",
                        "accion-desactivar",
                        function () {
                            cambiarEstadoNoticia(id, "inactiva");
                        }
                    )
                );
            } else {
                contenedor.appendChild(
                    crearBotonAccion(
                        "Publicar",
                        "accion-publicar",
                        function () {
                            cambiarEstadoNoticia(id, "publicada");
                        }
                    )
                );
            }

            contenedor.appendChild(
                crearBotonAccion(
                    "Papelera",
                    "accion-papelera",
                    function () {
                        enviarPapelera(id, noticia.titulo);
                    }
                )
            );
        } else {
            /*
             * Las noticias en papelera ofrecen dos opciones:
             * restaurarlas o eliminarlas definitivamente.
             */

            contenedor.appendChild(
                crearBotonAccion(
                    "Restaurar",
                    "accion-publicar",
                    function () {
                        restaurarNoticia(id);
                    }
                )
            );

            contenedor.appendChild(
                crearBotonAccion(
                    "Eliminar definitivamente",
                    "accion-eliminar-definitivamente",
                    function () {
                        eliminarDefinitivamente(
                            id,
                            noticia.titulo
                        );
                    }
                )
            );
        }

        return contenedor;
    }

    function esVerdadero(valor) {
        return valor === true ||
            valor === 1 ||
            valor === "1" ||
            valor === "t" ||
            valor === "true";
    }

    /*
     * =========================================================
     * RENDERIZAR TABLA
     * =========================================================
     */

    function renderizarNoticias(noticias) {
        if (!elementos.tabla) {
            return;
        }

        elementos.tabla.replaceChildren();

        if (!Array.isArray(noticias) || noticias.length === 0) {
            const fila = document.createElement("tr");
            const celda = crearElemento(
                "td",
                "noticias-tabla-vacia",
                "No hay noticias que coincidan con los filtros seleccionados."
            );

            celda.colSpan = 6;
            fila.appendChild(celda);
            elementos.tabla.appendChild(fila);

            return;
        }

        const fragmento = document.createDocumentFragment();

        noticias.forEach(function (noticia) {
            const fila = document.createElement("tr");

            /*
             * Columna: imagen
             */

            const celdaImagen = document.createElement("td");
            const rutaImagen = obtenerRutaImagen(noticia.imagen_ruta);

            if (rutaImagen) {
                const imagen = crearElemento(
                    "img",
                    "noticias-tabla-imagen"
                );

                imagen.src = rutaImagen;
                imagen.alt = String(
                    noticia.texto_alternativo_imagen ||
                    noticia.imagen_nombre ||
                    "Imagen de noticia"
                );
                imagen.loading = "lazy";

                imagen.addEventListener("error", function () {
                    imagen.removeAttribute("src");
                    imagen.alt = "Imagen no disponible";
                    imagen.classList.add("imagen-no-disponible");
                });

                celdaImagen.appendChild(imagen);
            } else {
                celdaImagen.appendChild(
                    crearElemento(
                        "div",
                        "noticias-tabla-sin-imagen",
                        "Sin imagen"
                    )
                );
            }

            fila.appendChild(celdaImagen);

            /*
             * Columna: contenido resumido
             */

            const celdaNoticia = document.createElement("td");

            celdaNoticia.appendChild(
                crearElemento(
                    "span",
                    "noticias-tabla-titulo",
                    noticia.titulo || "Sin título"
                )
            );

            if (noticia.resumen) {
                celdaNoticia.appendChild(
                    crearElemento(
                        "span",
                        "noticias-tabla-resumen",
                        noticia.resumen
                    )
                );
            }

            fila.appendChild(celdaNoticia);

            /*
             * Columna: estado
             */

            const celdaEstado = document.createElement("td");
            celdaEstado.appendChild(
                crearEtiquetaEstado(noticia.estado)
            );
            fila.appendChild(celdaEstado);

            /*
             * Columna: fecha
             */

            const celdaFecha = document.createElement("td");

            celdaFecha.textContent = noticia.fecha_publicacion
                ? formatearFecha(noticia.fecha_publicacion)
                : "Sin publicar";

            fila.appendChild(celdaFecha);

            /*
             * Columna: destacada
             */

            const celdaDestacada = document.createElement("td");

            if (esVerdadero(noticia.destacada)) {
                celdaDestacada.appendChild(
                    crearElemento(
                        "span",
                        "noticias-destacada-etiqueta",
                        "★ Sí"
                    )
                );
            } else {
                celdaDestacada.appendChild(
                    crearElemento(
                        "span",
                        "noticias-no-destacada",
                        "No"
                    )
                );
            }

            fila.appendChild(celdaDestacada);

            /*
             * Columna: acciones
             */

            const celdaAcciones = document.createElement("td");

            celdaAcciones.appendChild(
                crearAccionesNoticia(noticia)
            );

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
     * CARGAR IMÁGENES DE LA BIBLIOTECA
     * =========================================================
     */

    async function cargarImagenesBiblioteca(idSeleccionado = "") {
        if (!elementos.imagen) {
            return;
        }

        const valorAnterior = String(idSeleccionado || "");
        const opcionInicial = document.createElement("option");

        opcionInicial.value = "";
        opcionInicial.textContent = "Sin imagen";

        elementos.imagen.replaceChildren(opcionInicial);
        elementos.imagen.disabled = true;

        try {
            const resultado = await solicitarGET(
                API_MULTIMEDIA_URL + "?accion=listar"
            );

            const datos = obtenerDatos(resultado);

            /*
             * La API multimedia usa su propio token CSRF.
             * Se guarda por separado del token de noticias.
             */

            if (resultado.csrf_token) {
                tokenMultimedia = String(resultado.csrf_token);
            } else if (datos.csrf_token) {
                tokenMultimedia = String(datos.csrf_token);
            }

            const imagenes = Array.isArray(resultado.imagenes)
                ? resultado.imagenes
                : Array.isArray(datos.imagenes)
                    ? datos.imagenes
                    : [];

            imagenes.forEach(function (imagen) {
                const opcion = document.createElement("option");

                opcion.value = String(imagen.id_multimedia);

                opcion.textContent =
                    String(imagen.nombre_original || imagen.nombre_archivo) +
                    " (" +
                    String(imagen.tipo_mime || "imagen") +
                    ")";

                opcion.dataset.ruta = String(imagen.ruta_relativa || "");
                opcion.dataset.nombre = String(
                    imagen.nombre_original || imagen.nombre_archivo || ""
                );

                elementos.imagen.appendChild(opcion);
            });

            elementos.imagen.value = valorAnterior;

            if (elementos.imagen.value !== valorAnterior) {
                elementos.imagen.value = "";
            }

            actualizarVistaPreviaImagen();
        } catch (error) {
            mostrarMensaje(
                "No fue posible cargar la biblioteca multimedia: " +
                error.message,
                "error"
            );
        } finally {
            elementos.imagen.disabled = false;
        }
    }

    /*
     * =========================================================
     * VISTA PREVIA DE UNA IMAGEN DE LA BIBLIOTECA
     * =========================================================
     */

    function actualizarVistaPreviaImagen() {
        if (!elementos.contenedorVistaPrevia) {
            return;
        }

        const opcion = elementos.imagen?.selectedOptions?.[0];

        if (!opcion || !opcion.value) {
            elementos.contenedorVistaPrevia.hidden = true;

            if (elementos.imagenVistaPrevia) {
                elementos.imagenVistaPrevia.removeAttribute("src");
                elementos.imagenVistaPrevia.alt = "";
                elementos.imagenVistaPrevia.onerror = null;
            }

            if (elementos.nombreVistaPrevia) {
                elementos.nombreVistaPrevia.textContent = "";
            }

            return;
        }

        const ruta = obtenerRutaImagen(opcion.dataset.ruta);

        if (!ruta) {
            elementos.contenedorVistaPrevia.hidden = true;
            return;
        }

        elementos.contenedorVistaPrevia.hidden = false;

        if (elementos.imagenVistaPrevia) {
            elementos.imagenVistaPrevia.onerror = null;
            elementos.imagenVistaPrevia.src = ruta;
            elementos.imagenVistaPrevia.alt =
                elementos.textoAlternativo?.value ||
                opcion.dataset.nombre ||
                "Vista previa de la imagen";
        }

        if (elementos.nombreVistaPrevia) {
            elementos.nombreVistaPrevia.textContent =
                opcion.dataset.nombre || "Imagen seleccionada";
        }

        if (
            elementos.textoAlternativo &&
            !elementos.textoAlternativo.value
        ) {
            elementos.textoAlternativo.value =
                opcion.dataset.nombre || "";
        }
    }

    /*
     * =========================================================
     * SUBIR IMAGEN DESDE EL COMPUTADOR
     * =========================================================
     */

    async function subirImagenDesdeComputador(archivo) {
        if (!tokenMultimedia) {
            throw new Error(
                "No se encontró el token de seguridad de la biblioteca multimedia. " +
                "Cierra el formulario, actualiza la página e inténtalo nuevamente."
            );
        }

        const errorArchivo = validarArchivoImagen(archivo);

        if (errorArchivo) {
            throw new Error(errorArchivo);
        }

        const datos = new FormData();

        datos.set("accion", "subir");
        datos.set("csrf_token", tokenMultimedia);
        datos.set("archivo", archivo, archivo.name);
        datos.set(
            "texto_alternativo",
            elementos.textoAlternativo?.value.trim() || ""
        );

        mostrarEstadoCargaImagen("Subiendo imagen a la biblioteca multimedia...");

        const resultado = await solicitarPOST(
            API_MULTIMEDIA_URL,
            datos
        );

        const datosRespuesta = obtenerDatos(resultado);

        const imagenSubida =
            resultado.imagen ||
            datosRespuesta.imagen;

        if (
            !imagenSubida ||
            !imagenSubida.id_multimedia ||
            !imagenSubida.ruta_relativa
        ) {
            throw new Error(
                "La imagen se recibió, pero el servidor no devolvió los datos necesarios para asociarla a la noticia."
            );
        }

        return imagenSubida;
    }

    /*
     * =========================================================
     * ASOCIAR IMAGEN SUBIDA AL SELECTOR
     *
     * Al guardar correctamente una imagen en la biblioteca,
     * se agrega al selector y queda seleccionada.
     *
     * Esto permite reutilizar la imagen si falla el guardado
     * de la noticia y el usuario necesita volver a intentarlo.
     * =========================================================
     */

    function seleccionarImagenSubida(imagenSubida) {
        const idImagen = String(imagenSubida.id_multimedia);

        let opcion = Array.from(elementos.imagen.options).find(
            function (elemento) {
                return elemento.value === idImagen;
            }
        );

        if (!opcion) {
            opcion = document.createElement("option");
            opcion.value = idImagen;

            elementos.imagen.appendChild(opcion);
        }

        opcion.textContent = String(
            imagenSubida.nombre_original ||
            imagenSubida.nombre_archivo ||
            "Imagen subida"
        ) + " (" + String(imagenSubida.tipo_mime || "imagen") + ")";

        opcion.dataset.ruta = String(imagenSubida.ruta_relativa || "");
        opcion.dataset.nombre = String(
            imagenSubida.nombre_original ||
            imagenSubida.nombre_archivo ||
            "Imagen subida"
        );

        elementos.imagen.value = idImagen;

        /*
         * Una vez subida, la imagen ya está en la biblioteca.
         * Se limpia el archivo local para evitar que al reintentar
         * el guardado se vuelva a subir el mismo archivo.
         */

        if (elementos.archivoImagen) {
            elementos.archivoImagen.value = "";
        }

        liberarVistaPreviaLocal();

        actualizarVistaPreviaImagen();

        mostrarEstadoCargaImagen(
            "Imagen cargada correctamente. Se asociará a la noticia al guardar."
        );
    }

    /*
     * =========================================================
     * MANEJAR SELECCIÓN DE ARCHIVO LOCAL
     * =========================================================
     */

    function manejarSeleccionArchivoImagen() {
        if (!elementos.archivoImagen) {
            return;
        }

        const archivo = elementos.archivoImagen.files?.[0];

        if (!archivo) {
            liberarVistaPreviaLocal();
            limpiarEstadoCargaImagen();
            actualizarVistaPreviaImagen();
            return;
        }

        const errorArchivo = validarArchivoImagen(archivo);

        if (errorArchivo) {
            elementos.archivoImagen.value = "";

            liberarVistaPreviaLocal();

            mostrarEstadoCargaImagen(errorArchivo);

            actualizarVistaPreviaImagen();

            return;
        }

        /*
         * La imagen local tiene prioridad.
         * Se elimina la selección previa de la biblioteca para
         * que el usuario no confunda cuál imagen se guardará.
         */

        if (elementos.imagen) {
            elementos.imagen.value = "";
        }

        mostrarVistaPreviaArchivo(archivo);

        mostrarEstadoCargaImagen(
            "Imagen seleccionada. Se subirá al guardar la noticia."
        );
    }

    /*
     * =========================================================
     * CAMBIAR A UNA IMAGEN DE LA BIBLIOTECA
     * =========================================================
     */

    function manejarSeleccionBiblioteca() {
        /*
         * Al elegir una imagen existente, se descarta el archivo
         * local pendiente, para mantener una sola selección.
         */

        if (elementos.archivoImagen?.files?.length) {
            elementos.archivoImagen.value = "";
            liberarVistaPreviaLocal();
        }

        limpiarEstadoCargaImagen();

        actualizarVistaPreviaImagen();
    }

    /*
     * =========================================================
     * ABRIR Y CERRAR MODAL
     * =========================================================
     */

    function abrirModal() {
        if (!elementos.modal) {
            return;
        }

        elementos.modal.hidden = false;
        document.body.style.overflow = "hidden";
    }

    function cerrarModal() {
        if (!elementos.modal) {
            return;
        }

        elementos.modal.hidden = true;
        document.body.style.overflow = "";

        limpiarArchivoSeleccionado();
    }

    function prepararFormularioNuevo() {
        if (!elementos.formulario) {
            return;
        }

        limpiarArchivoSeleccionado();

        elementos.formulario.reset();

        elementos.idNoticia.value = "";
        elementos.destacadaNoticia.checked = false;
        elementos.tituloModal.textContent = "Nueva noticia";
        elementos.btnGuardar.textContent = "Guardar borrador";

        if (elementos.textoAlternativo) {
            elementos.textoAlternativo.value = "";
        }

        if (elementos.imagen) {
            elementos.imagen.value = "";
        }

        actualizarVistaPreviaImagen();
        limpiarMensaje();

        cargarImagenesBiblioteca("").then(function () {
            abrirModal();

            if (elementos.titulo) {
                elementos.titulo.focus();
            }
        });
    }

    /*
     * =========================================================
     * EDITAR NOTICIA
     * =========================================================
     */

    async function abrirEdicionNoticia(idNoticia) {
        if (!tokenNoticias) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        limpiarMensaje();

        try {
            const resultado = await solicitarGET(
                API_NOTICIAS_URL +
                "?accion=obtener&id=" +
                encodeURIComponent(String(idNoticia))
            );

            const datos = obtenerDatos(resultado);
            const noticia = resultado.noticia || datos.noticia;

            if (!noticia) {
                throw new Error("No se pudo obtener la noticia seleccionada.");
            }

            limpiarArchivoSeleccionado();

            elementos.formulario.reset();

            elementos.idNoticia.value = noticia.id_noticia;
            elementos.titulo.value = noticia.titulo || "";
            elementos.resumenNoticia.value = noticia.resumen || "";
            elementos.contenido.value = noticia.contenido || "";
            elementos.destacadaNoticia.checked =
                esVerdadero(noticia.destacada);

            elementos.textoAlternativo.value =
                noticia.texto_alternativo_imagen || "";

            elementos.tituloModal.textContent = "Editar noticia";
            elementos.btnGuardar.textContent = "Guardar cambios";

            await cargarImagenesBiblioteca(
                noticia.id_multimedia || ""
            );

            abrirModal();

            if (elementos.titulo) {
                elementos.titulo.focus();
            }
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible abrir la noticia.",
                "error"
            );
        }
    }

    /*
     * =========================================================
     * GUARDAR NOTICIA
     * =========================================================
     */

    async function guardarNoticia(evento) {
        evento.preventDefault();

        if (guardandoNoticia) {
            return;
        }

        if (!tokenNoticias) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        const titulo = elementos.titulo.value.trim();
        const contenido = elementos.contenido.value.trim();

        if (!titulo) {
            elementos.titulo.focus();
            mostrarMensaje(
                "Debes ingresar el título de la noticia.",
                "error"
            );
            return;
        }

        if (!contenido) {
            elementos.contenido.focus();
            mostrarMensaje(
                "Debes ingresar el contenido de la noticia.",
                "error"
            );
            return;
        }

        const archivoSeleccionado =
            elementos.archivoImagen?.files?.[0] || null;

        if (archivoSeleccionado) {
            const errorArchivo = validarArchivoImagen(archivoSeleccionado);

            if (errorArchivo) {
                mostrarEstadoCargaImagen(errorArchivo);
                return;
            }
        }

        guardandoNoticia = true;

        if (elementos.btnGuardar) {
            elementos.btnGuardar.disabled = true;
            elementos.btnGuardar.textContent = archivoSeleccionado
                ? "Subiendo imagen..."
                : "Guardando...";
        }

        try {
            /*
             * PASO 1:
             * Si el usuario eligió una imagen de su computador,
             * subirla mediante la API multimedia existente.
             *
             * La API responde con el identificador generado en
             * PostgreSQL. Luego seleccionamos ese registro en
             * el formulario antes de guardar la noticia.
             */

            if (archivoSeleccionado) {
                const imagenSubida = await subirImagenDesdeComputador(
                    archivoSeleccionado
                );

                seleccionarImagenSubida(imagenSubida);

                if (elementos.btnGuardar) {
                    elementos.btnGuardar.textContent = "Guardando noticia...";
                }
            }

            /*
             * PASO 2:
             * Guardar la noticia con el id_multimedia seleccionado.
             *
             * Si se subió una imagen local, seleccionarImagenSubida()
             * ya dejó su identificador en el selector de biblioteca.
             */

            const datos = new FormData(elementos.formulario);

            datos.set("accion", "guardar");
            datos.set("csrf_token", tokenNoticias);
            datos.set(
                "destacada",
                elementos.destacadaNoticia.checked ? "1" : "0"
            );

            const resultado = await solicitarPOST(
                API_NOTICIAS_URL,
                datos
            );

            cerrarModal();

            await cargarNoticias({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "La noticia se guardó correctamente."
            });
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible guardar la noticia.",
                "error"
            );
        } finally {
            guardandoNoticia = false;

            if (elementos.btnGuardar) {
                elementos.btnGuardar.disabled = false;

                elementos.btnGuardar.textContent =
                    elementos.idNoticia.value
                        ? "Guardar cambios"
                        : "Guardar borrador";
            }
        }
    }

    /*
     * =========================================================
     * CAMBIAR ESTADO DE LA NOTICIA
     * =========================================================
     */

    async function cambiarEstadoNoticia(idNoticia, estado) {
        if (!tokenNoticias) {
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
                "¿Deseas " + accionTexto + " esta noticia?"
            )
        ) {
            return;
        }

        limpiarMensaje();

        try {
            const datos = crearDatosPOST("cambiar_estado", {
                id_noticia: idNoticia,
                estado: estado
            });

            const resultado = await solicitarPOST(
                API_NOTICIAS_URL,
                datos
            );

            await cargarNoticias({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "El estado de la noticia se actualizó."
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
     * DESTACAR / QUITAR DESTACADO
     *
     * La API reutiliza la acción guardar. Primero se obtiene
     * la noticia completa para conservar sus otros campos.
     * =========================================================
     */

    async function cambiarDestacada(idNoticia, nuevoValor) {
        if (!tokenNoticias) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        try {
            const resultadoConsulta = await solicitarGET(
                API_NOTICIAS_URL +
                "?accion=obtener&id=" +
                encodeURIComponent(String(idNoticia))
            );

            const datosConsulta = obtenerDatos(resultadoConsulta);
            const noticia =
                resultadoConsulta.noticia || datosConsulta.noticia;

            if (!noticia) {
                throw new Error("No se encontró la noticia.");
            }

            const datos = new FormData();

            datos.set("accion", "guardar");
            datos.set("csrf_token", tokenNoticias);
            datos.set("id_noticia", String(noticia.id_noticia));
            datos.set("titulo", noticia.titulo || "");
            datos.set("resumen", noticia.resumen || "");
            datos.set("contenido", noticia.contenido || "");
            datos.set(
                "id_multimedia",
                noticia.id_multimedia === null ||
                noticia.id_multimedia === undefined
                    ? ""
                    : String(noticia.id_multimedia)
            );
            datos.set(
                "texto_alternativo_imagen",
                noticia.texto_alternativo_imagen || ""
            );
            datos.set("destacada", nuevoValor ? "1" : "0");

            await solicitarPOST(
                API_NOTICIAS_URL,
                datos
            );

            await cargarNoticias({
                pagina: paginaActual,
                mensaje: nuevoValor
                    ? "La noticia quedó destacada."
                    : "Se quitó el destacado de la noticia."
            });
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible cambiar el destacado.",
                "error"
            );
        }
    }

    /*
     * =========================================================
     * ENVIAR NOTICIA A PAPELERA
     * =========================================================
     */

    async function enviarPapelera(idNoticia, titulo) {
        if (!tokenNoticias) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        const confirmado = window.confirm(
            '¿Enviar la noticia "' +
            escaparTextoNoNecesario(titulo || "Sin título") +
            '" a la papelera?\n\n' +
            "La noticia no se eliminará definitivamente y podrás recuperarla."
        );

        if (!confirmado) {
            return;
        }

        try {
            const datos = crearDatosPOST("enviar_papelera", {
                id_noticia: idNoticia
            });

            const resultado = await solicitarPOST(
                API_NOTICIAS_URL,
                datos
            );

            await cargarNoticias({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "La noticia se envió a la papelera."
            });
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible enviar la noticia a la papelera.",
                "error"
            );
        }
    }

    /*
     * =========================================================
     * RESTAURAR NOTICIA
     * =========================================================
     */

    async function restaurarNoticia(idNoticia) {
        if (!tokenNoticias) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        if (
            !window.confirm(
                "¿Deseas restaurar esta noticia desde la papelera?"
            )
        ) {
            return;
        }

        try {
            const datos = crearDatosPOST("restaurar", {
                id_noticia: idNoticia
            });

            const resultado = await solicitarPOST(
                API_NOTICIAS_URL,
                datos
            );

            await cargarNoticias({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "La noticia se restauró correctamente."
            });
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible restaurar la noticia.",
                "error"
            );
        }
    }

    /*
     * =========================================================
     * ELIMINAR NOTICIA DEFINITIVAMENTE
     *
     * Solo se muestra para noticias que ya están en papelera.
     * La API debe validar nuevamente el estado antes de eliminar.
     * =========================================================
     */

    async function eliminarDefinitivamente(idNoticia, titulo) {
        if (!tokenNoticias) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página.",
                "error"
            );
            return;
        }

        const confirmado = window.confirm(
            '¿Eliminar definitivamente la noticia "' +
            escaparTextoNoNecesario(titulo || "Sin título") +
            '"?\n\n' +
            "Esta acción no se puede deshacer. La noticia se borrará de la base de datos y no podrás restaurarla.\n\n" +
            "La imagen original permanecerá en la biblioteca multimedia."
        );

        if (!confirmado) {
            return;
        }

        limpiarMensaje();

        try {
            const datos = crearDatosPOST(
                "eliminar_definitivamente",
                {
                    id_noticia: idNoticia
                }
            );

            const resultado = await solicitarPOST(
                API_NOTICIAS_URL,
                datos
            );

            await cargarNoticias({
                pagina: paginaActual,
                mensaje: resultado.mensaje ||
                    "La noticia fue eliminada definitivamente."
            });
        } catch (error) {
            mostrarMensaje(
                error.message ||
                "No fue posible eliminar definitivamente la noticia.",
                "error"
            );
        }
    }

    /*
     * =========================================================
     * EVENTOS
     * =========================================================
     */

    if (elementos.btnNueva) {
        elementos.btnNueva.addEventListener(
            "click",
            prepararFormularioNuevo
        );
    }

    if (elementos.btnActualizar) {
        elementos.btnActualizar.addEventListener("click", function () {
            cargarNoticias({ pagina: paginaActual });
        });
    }

    if (elementos.busqueda) {
        elementos.busqueda.addEventListener("input", function () {
            window.clearTimeout(temporizadorBusqueda);

            temporizadorBusqueda = window.setTimeout(function () {
                cargarNoticias({ pagina: 1 });
            }, 350);
        });
    }

    if (elementos.estado) {
        elementos.estado.addEventListener("change", function () {
            cargarNoticias({ pagina: 1 });
        });
    }

    if (elementos.destacada) {
        elementos.destacada.addEventListener("change", function () {
            cargarNoticias({ pagina: 1 });
        });
    }

    if (elementos.cantidad) {
        elementos.cantidad.addEventListener("change", function () {
            cantidadPorPagina = Number(elementos.cantidad.value) || 10;
            cargarNoticias({ pagina: 1 });
        });
    }

    if (elementos.btnAnterior) {
        elementos.btnAnterior.addEventListener("click", function () {
            if (paginaActual > 1) {
                cargarNoticias({ pagina: paginaActual - 1 });
            }
        });
    }

    if (elementos.btnSiguiente) {
        elementos.btnSiguiente.addEventListener("click", function () {
            if (paginaActual < totalPaginas) {
                cargarNoticias({ pagina: paginaActual + 1 });
            }
        });
    }

    if (elementos.btnCerrarModal) {
        elementos.btnCerrarModal.addEventListener(
            "click",
            cerrarModal
        );
    }

    if (elementos.btnCancelarModal) {
        elementos.btnCancelarModal.addEventListener(
            "click",
            cerrarModal
        );
    }

    if (elementos.modal) {
        elementos.modal.querySelectorAll("[data-cerrar-modal]").forEach(
            function (elemento) {
                elemento.addEventListener("click", cerrarModal);
            }
        );
    }

    if (elementos.formulario) {
        elementos.formulario.addEventListener(
            "submit",
            guardarNoticia
        );
    }

    if (elementos.imagen) {
        elementos.imagen.addEventListener(
            "change",
            manejarSeleccionBiblioteca
        );
    }

    if (elementos.archivoImagen) {
        elementos.archivoImagen.addEventListener(
            "change",
            manejarSeleccionArchivoImagen
        );
    }

    if (elementos.textoAlternativo) {
        elementos.textoAlternativo.addEventListener("input", function () {
            if (elementos.imagenVistaPrevia) {
                elementos.imagenVistaPrevia.alt =
                    elementos.textoAlternativo.value;
            }
        });
    }

    document.addEventListener("keydown", function (evento) {
        if (
            evento.key === "Escape" &&
            elementos.modal &&
            !elementos.modal.hidden &&
            !guardandoNoticia
        ) {
            cerrarModal();
        }
    });

    /*
     * =========================================================
     * INICIO
     * =========================================================
     */

    cargarNoticias({ pagina: 1 });
});
