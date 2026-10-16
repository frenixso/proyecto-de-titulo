document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    /*
     * =========================================================
     * BIBLIOTECA MULTIMEDIA
     * Archivo: js/biblioteca_multimedia.js
     *
     * Funciones:
     * - Listar imágenes.
     * - Subir imágenes.
     * - Mostrar vista previa.
     * - Abrir imágenes.
     * - Copiar rutas.
     * - Eliminar imágenes no utilizadas.
     * =========================================================
     */

    const API_MULTIMEDIA_URL = "../api/web_multimedia.php";
    const TAMANO_MAXIMO = 5 * 1024 * 1024;

    const TIPOS_PERMITIDOS = [
        "image/jpeg",
        "image/png",
        "image/webp",
        "image/gif"
    ];

    /*
     * =========================================================
     * ELEMENTOS DEL DOCUMENTO
     * =========================================================
     */

    const formularioSubir = document.getElementById("formSubirImagen");
    const inputArchivo = document.getElementById("archivoMultimedia");
    const listaMultimedia = document.getElementById("listaMultimedia");
    const botonActualizar = document.getElementById("btnActualizarBiblioteca");
    const botonSubir = document.getElementById("btnSubirImagen");

    let tokenCSRF = "";
    let cargandoBiblioteca = false;
    let subiendoImagen = false;

    /*
     * Buscar elementos opcionales por diferentes identificadores.
     * Así no se interrumpe el funcionamiento si una vista previa
     * o un mensaje no está presente en el HTML.
     */

    function buscarElemento(ids) {
        for (const id of ids) {
            const elemento = document.getElementById(id);

            if (elemento) {
                return elemento;
            }
        }

        return null;
    }

    const elementoVistaPrevia = buscarElemento([
        "vistaPreviaMultimedia",
        "previewImagenMultimedia",
        "vistaPreviaImagen",
        "contenedorVistaPrevia"
    ]);

    const imagenVistaPrevia = buscarElemento([
        "imagenVistaPrevia",
        "previewImagen",
        "imagenPreviaMultimedia"
    ]);

    const textoNombreArchivo = buscarElemento([
        "nombreArchivoSeleccionado",
        "nombreImagenSeleccionada",
        "textoArchivoMultimedia"
    ]);

    const mensajeEstado = buscarElemento([
        "mensajeBiblioteca",
        "mensajeMultimedia",
        "estadoBiblioteca",
        "estadoMultimedia"
    ]);

    /*
     * =========================================================
     * MENSAJES
     * =========================================================
     */

    function mostrarMensaje(mensaje, tipo) {
        if (mensajeEstado) {
            mensajeEstado.textContent = mensaje;
            mensajeEstado.className = "mensaje-multimedia";

            if (tipo) {
                mensajeEstado.classList.add("mensaje-" + tipo);
            }

            mensajeEstado.hidden = false;
        } else {
            /*
             * Si el HTML no dispone de un elemento de mensajes,
             * informar mediante la consola sin bloquear el flujo.
             */
            console.info("[Biblioteca multimedia]", mensaje);
        }
    }

    function limpiarMensaje() {
        if (!mensajeEstado) {
            return;
        }

        mensajeEstado.textContent = "";
        mensajeEstado.hidden = true;
        mensajeEstado.className = "mensaje-multimedia";
    }

    /*
     * =========================================================
     * RESPUESTAS DE LA API
     * =========================================================
     */

    async function leerRespuestaHTTP(respuesta) {
        let resultado;

        try {
            resultado = await respuesta.json();
        } catch (error) {
            throw new Error(
                "El servidor devolvió una respuesta que no se pudo interpretar."
            );
        }

        if (
            !respuesta.ok
            || resultado.ok === false
            || resultado.success === false
            || resultado.exito === false
        ) {
            throw new Error(
                resultado.mensaje
                || resultado.message
                || "La operación no pudo completarse."
            );
        }

        return resultado;
    }

    function obtenerDatos(resultado) {
        if (
            resultado.data
            && typeof resultado.data === "object"
        ) {
            return resultado.data;
        }

        if (
            resultado.datos
            && typeof resultado.datos === "object"
        ) {
            return resultado.datos;
        }

        return resultado;
    }

    /*
     * =========================================================
     * VALIDACIÓN DE RUTAS
     * =========================================================
     */

    function obtenerRutaImagen(imagen) {
        const ruta = String(imagen.ruta_relativa || "");

        if (
            !ruta.startsWith("uploads/multimedia/")
            || ruta.includes("..")
            || ruta.includes("\\")
            || ruta.startsWith("/")
            || /^[a-z][a-z0-9+.-]*:/i.test(ruta)
        ) {
            return null;
        }

        return "../" + ruta;
    }

    /*
     * =========================================================
     * FORMATO DE DATOS
     * =========================================================
     */

    function formatearTamano(bytes) {
        const tamano = Number(bytes);

        if (!Number.isFinite(tamano) || tamano < 0) {
            return "Tamaño desconocido";
        }

        if (tamano < 1024) {
            return tamano + " B";
        }

        if (tamano < 1024 * 1024) {
            return (tamano / 1024).toFixed(1) + " KB";
        }

        return (tamano / (1024 * 1024)).toFixed(2) + " MB";
    }

    function formatearFecha(fecha) {
        if (!fecha) {
            return "Fecha no disponible";
        }

        const fechaConvertida = new Date(fecha);

        if (Number.isNaN(fechaConvertida.getTime())) {
            return String(fecha);
        }

        return fechaConvertida.toLocaleString("es-CL", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit"
        });
    }

    /*
     * =========================================================
     * CREACIÓN SEGURA DE ELEMENTOS
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

    function crearBoton(texto, clase, accion) {
        const boton = crearElemento("button", clase, texto);

        boton.type = "button";
        boton.addEventListener("click", accion);

        return boton;
    }

    /*
     * =========================================================
     * COPIAR RUTA
     * =========================================================
     */

    async function copiarTexto(texto) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(texto);
            return;
        }

        const campoTemporal = document.createElement("textarea");

        campoTemporal.value = texto;
        campoTemporal.setAttribute("readonly", "");
        campoTemporal.style.position = "fixed";
        campoTemporal.style.left = "-9999px";
        campoTemporal.style.top = "0";

        document.body.appendChild(campoTemporal);
        campoTemporal.select();

        let copiado = false;

        try {
            copiado = document.execCommand("copy");
        } finally {
            campoTemporal.remove();
        }

        if (!copiado) {
            throw new Error(
                "El navegador no permitió copiar la ruta automáticamente."
            );
        }
    }

    /*
     * =========================================================
     * ELIMINAR IMAGEN
     * =========================================================
     */

    async function eliminarImagen(imagen, boton) {
        const id = Number(imagen.id_multimedia);

        if (!Number.isInteger(id) || id <= 0) {
            mostrarMensaje(
                "No se pudo identificar la imagen seleccionada.",
                "error"
            );
            return;
        }

        const nombre = String(
            imagen.nombre_original || imagen.nombre_archivo || "esta imagen"
        );

        const confirmado = window.confirm(
            '¿Estás seguro de que deseas eliminar la imagen "' + nombre + '"?\n\n'
            + "Esta acción no se puede deshacer. Si la imagen está vinculada "
            + "a noticias, banners, slider o tutoriales, el servidor impedirá "
            + "eliminarla."
        );

        if (!confirmado) {
            return;
        }

        if (!tokenCSRF) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página e inténtalo nuevamente.",
                "error"
            );
            return;
        }

        const textoOriginal = boton.textContent;

        boton.disabled = true;
        boton.textContent = "Eliminando...";
        limpiarMensaje();

        const datos = new URLSearchParams();

        datos.set("accion", "eliminar");
        datos.set("id_multimedia", String(id));
        datos.set("csrf_token", tokenCSRF);

        try {
            const respuesta = await fetch(API_MULTIMEDIA_URL, {
                method: "POST",
                body: datos,
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            });

            const resultado = await leerRespuestaHTTP(respuesta);

            mostrarMensaje(
                resultado.mensaje || "La imagen se eliminó correctamente.",
                "exito"
            );

            await cargarBiblioteca(true);
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible eliminar la imagen.",
                "error"
            );

            boton.disabled = false;
            boton.textContent = textoOriginal;
        }
    }

    /*
     * =========================================================
     * CONSTRUIR TARJETA DE IMAGEN
     * =========================================================
     */

    function crearTarjetaImagen(imagen) {
        const tarjeta = crearElemento("article", "biblioteca-imagen-card");

        const ruta = obtenerRutaImagen(imagen);

        /*
         * Vista previa
         */

        const contenedorImagen = crearElemento(
            "div",
            "biblioteca-imagen-preview"
        );

        if (ruta) {
            const miniatura = document.createElement("img");

            miniatura.src = ruta;
            miniatura.alt = String(
                imagen.texto_alternativo || imagen.nombre_original || "Imagen multimedia"
            );
            miniatura.loading = "lazy";

            miniatura.addEventListener("error", function () {
                miniatura.alt = "No se pudo cargar la vista previa";
                miniatura.classList.add("imagen-no-disponible");
            });

            contenedorImagen.appendChild(miniatura);
        } else {
            contenedorImagen.appendChild(
                crearElemento(
                    "p",
                    "biblioteca-imagen-error",
                    "Ruta de imagen no válida"
                )
            );
        }

        tarjeta.appendChild(contenedorImagen);

        /*
         * Información
         */

        const informacion = crearElemento(
            "div",
            "biblioteca-imagen-info"
        );

        informacion.appendChild(
            crearElemento(
                "h3",
                "biblioteca-imagen-nombre",
                imagen.nombre_original || imagen.nombre_archivo || "Imagen sin nombre"
            )
        );

        informacion.appendChild(
            crearElemento(
                "p",
                "biblioteca-imagen-tipo",
                "Formato: " + String(imagen.tipo_mime || "Desconocido")
            )
        );

        informacion.appendChild(
            crearElemento(
                "p",
                "biblioteca-imagen-tamano",
                "Tamaño: " + formatearTamano(imagen.tamano_bytes)
            )
        );

        informacion.appendChild(
            crearElemento(
                "p",
                "biblioteca-imagen-fecha",
                "Subida: " + formatearFecha(imagen.fecha_subida)
            )
        );

        informacion.appendChild(
            crearElemento(
                "p",
                "biblioteca-imagen-ruta",
                "Ruta: " + String(imagen.ruta_relativa || "No disponible")
            )
        );

        tarjeta.appendChild(informacion);

        /*
         * Botones de acción
         */

        const acciones = crearElemento(
            "div",
            "biblioteca-imagen-acciones"
        );

        if (ruta) {
            const enlaceAbrir = crearElemento(
                "a",
                "btn-abrir-imagen",
                "Abrir imagen"
            );

            enlaceAbrir.href = ruta;
            enlaceAbrir.target = "_blank";
            enlaceAbrir.rel = "noopener noreferrer";

            acciones.appendChild(enlaceAbrir);

            const botonCopiar = crearBoton(
                "Copiar ruta",
                "btn-copiar-ruta",
                async function () {
                    const textoOriginal = botonCopiar.textContent;

                    botonCopiar.disabled = true;

                    try {
                        await copiarTexto(imagen.ruta_relativa);

                        botonCopiar.textContent = "Ruta copiada";
                        mostrarMensaje(
                            "Ruta de la imagen copiada correctamente.",
                            "exito"
                        );
                    } catch (error) {
                        mostrarMensaje(
                            error.message || "No se pudo copiar la ruta.",
                            "error"
                        );
                    } finally {
                        window.setTimeout(function () {
                            botonCopiar.textContent = textoOriginal;
                            botonCopiar.disabled = false;
                        }, 1800);
                    }
                }
            );

            acciones.appendChild(botonCopiar);
        }

        /*
         * NUEVO BOTÓN: ELIMINAR
         */

        const botonEliminar = crearBoton(
            "Eliminar",
            "btn-eliminar-imagen",
            function () {
                eliminarImagen(imagen, botonEliminar);
            }
        );

        botonEliminar.setAttribute(
            "aria-label",
            "Eliminar imagen " + String(imagen.nombre_original || "")
        );

        acciones.appendChild(botonEliminar);
        tarjeta.appendChild(acciones);

        return tarjeta;
    }

    /*
     * =========================================================
     * MOSTRAR IMÁGENES
     * =========================================================
     */

    function renderizarImagenes(imagenes) {
        if (!listaMultimedia) {
            mostrarMensaje(
                "No se encontró el contenedor de imágenes en la página.",
                "error"
            );
            return;
        }

        listaMultimedia.replaceChildren();

        if (!Array.isArray(imagenes) || imagenes.length === 0) {
            listaMultimedia.appendChild(
                crearElemento(
                    "p",
                    "biblioteca-multimedia-vacia",
                    "Todavía no hay imágenes en la biblioteca multimedia."
                )
            );

            return;
        }

        const fragmento = document.createDocumentFragment();

        imagenes.forEach(function (imagen) {
            fragmento.appendChild(crearTarjetaImagen(imagen));
        });

        listaMultimedia.appendChild(fragmento);
    }

    /*
     * =========================================================
     * CARGAR BIBLIOTECA DESDE EL SERVIDOR
     * =========================================================
     */

    async function cargarBiblioteca(silencioso) {
        if (cargandoBiblioteca) {
            return;
        }

        cargandoBiblioteca = true;

        if (botonActualizar) {
            botonActualizar.disabled = true;
        }

        if (!silencioso) {
            mostrarMensaje("Cargando biblioteca multimedia...", "info");
        }

        try {
            const respuesta = await fetch(
                API_MULTIMEDIA_URL + "?accion=listar",
                {
                    method: "GET",
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    }
                }
            );

            const resultado = await leerRespuestaHTTP(respuesta);
            const datos = obtenerDatos(resultado);

            const imagenes = Array.isArray(resultado.imagenes)
                ? resultado.imagenes
                : Array.isArray(datos.imagenes)
                    ? datos.imagenes
                    : [];

            const nuevoToken = resultado.csrf_token || datos.csrf_token;

            if (nuevoToken) {
                tokenCSRF = String(nuevoToken);
            }

            renderizarImagenes(imagenes);

            if (!silencioso) {
                mostrarMensaje(
                    "Biblioteca cargada. Imágenes encontradas: " + imagenes.length + ".",
                    "exito"
                );
            }
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible cargar las imágenes.",
                "error"
            );
        } finally {
            cargandoBiblioteca = false;

            if (botonActualizar) {
                botonActualizar.disabled = false;
            }
        }
    }

    /*
     * =========================================================
     * VISTA PREVIA DE LA IMAGEN SELECCIONADA
     * =========================================================
     */

    function limpiarVistaPrevia() {
        if (elementoVistaPrevia) {
            elementoVistaPrevia.hidden = true;
        }

        if (imagenVistaPrevia) {
            imagenVistaPrevia.removeAttribute("src");
            imagenVistaPrevia.alt = "";
        }

        if (textoNombreArchivo) {
            textoNombreArchivo.textContent = "";
        }
    }

    function mostrarVistaPrevia(archivo) {
        if (!archivo) {
            limpiarVistaPrevia();
            return;
        }

        if (!TIPOS_PERMITIDOS.includes(archivo.type)) {
            limpiarVistaPrevia();

            mostrarMensaje(
                "Formato no permitido. Selecciona una imagen JPG, PNG, WEBP o GIF.",
                "error"
            );

            if (inputArchivo) {
                inputArchivo.value = "";
            }

            return;
        }

        if (archivo.size > TAMANO_MAXIMO) {
            limpiarVistaPrevia();

            mostrarMensaje(
                "La imagen supera el límite permitido de 5 MB.",
                "error"
            );

            if (inputArchivo) {
                inputArchivo.value = "";
            }

            return;
        }

        if (textoNombreArchivo) {
            textoNombreArchivo.textContent =
                archivo.name + " (" + formatearTamano(archivo.size) + ")";
        }

        if (
            imagenVistaPrevia
            && archivo.type.startsWith("image/")
        ) {
            if (imagenVistaPrevia.dataset.objectUrl) {
                URL.revokeObjectURL(imagenVistaPrevia.dataset.objectUrl);
            }

            const objectUrl = URL.createObjectURL(archivo);

            imagenVistaPrevia.src = objectUrl;
            imagenVistaPrevia.alt = archivo.name;
            imagenVistaPrevia.dataset.objectUrl = objectUrl;
        }

        if (elementoVistaPrevia) {
            elementoVistaPrevia.hidden = false;
        }

        limpiarMensaje();
    }

    /*
     * =========================================================
     * SUBIR IMAGEN
     * =========================================================
     */

    async function subirImagen(evento) {
        evento.preventDefault();

        if (subiendoImagen) {
            return;
        }

        if (!formularioSubir || !inputArchivo) {
            mostrarMensaje(
                "No se encontró el formulario para subir imágenes.",
                "error"
            );
            return;
        }

        const archivo = inputArchivo.files
            ? inputArchivo.files[0]
            : null;

        if (!archivo) {
            mostrarMensaje(
                "Selecciona una imagen antes de subirla.",
                "error"
            );
            return;
        }

        if (!TIPOS_PERMITIDOS.includes(archivo.type)) {
            mostrarMensaje(
                "Formato no permitido. Utiliza JPG, PNG, WEBP o GIF.",
                "error"
            );
            return;
        }

        if (archivo.size <= 0 || archivo.size > TAMANO_MAXIMO) {
            mostrarMensaje(
                "La imagen debe tener un tamaño mayor que 0 y no superar los 5 MB.",
                "error"
            );
            return;
        }

        if (!tokenCSRF) {
            mostrarMensaje(
                "No se encontró el token de seguridad. Actualiza la página e inténtalo nuevamente.",
                "error"
            );
            return;
        }

        subiendoImagen = true;

        if (botonSubir) {
            botonSubir.disabled = true;
            botonSubir.textContent = "Subiendo imagen...";
        }

        limpiarMensaje();

        try {
            const datos = new FormData(formularioSubir);

            datos.set("accion", "subir");
            datos.set("csrf_token", tokenCSRF);
            datos.set("archivo", archivo);

            const respuesta = await fetch(API_MULTIMEDIA_URL, {
                method: "POST",
                body: datos,
                credentials: "same-origin",
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            });

            const resultado = await leerRespuestaHTTP(respuesta);

            mostrarMensaje(
                resultado.mensaje || "La imagen se subió correctamente.",
                "exito"
            );

            formularioSubir.reset();
            limpiarVistaPrevia();

            await cargarBiblioteca(true);
        } catch (error) {
            mostrarMensaje(
                error.message || "No fue posible subir la imagen.",
                "error"
            );
        } finally {
            subiendoImagen = false;

            if (botonSubir) {
                botonSubir.disabled = false;
                botonSubir.textContent = "Subir imagen";
            }
        }
    }

    /*
     * =========================================================
     * EVENTOS
     * =========================================================
     */

    if (formularioSubir) {
        formularioSubir.addEventListener("submit", subirImagen);
    }

    if (inputArchivo) {
        inputArchivo.addEventListener("change", function () {
            const archivo = inputArchivo.files
                ? inputArchivo.files[0]
                : null;

            mostrarVistaPrevia(archivo);
        });
    }

    if (botonActualizar) {
        botonActualizar.addEventListener("click", function () {
            cargarBiblioteca(false);
        });
    }

    /*
     * Limpiar la URL temporal de vista previa al abandonar la página.
     */

    window.addEventListener("beforeunload", function () {
        if (
            imagenVistaPrevia
            && imagenVistaPrevia.dataset.objectUrl
        ) {
            URL.revokeObjectURL(
                imagenVistaPrevia.dataset.objectUrl
            );
        }
    });

    /*
     * =========================================================
     * INICIO
     * =========================================================
     */

    limpiarVistaPrevia();
    cargarBiblioteca(false);
});