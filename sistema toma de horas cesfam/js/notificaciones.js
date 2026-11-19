
document.addEventListener("DOMContentLoaded", function () {

    /*
     * =========================================================
     * CONFIGURACIÓN
     * =========================================================
     */

    // API del sistema CESFAM.
    const API_URL = new URL(
        "../api/notificaciones.php",
        window.location.href
    ).href;

    // Agenda del sistema CESFAM.
    const AGENDA_URL = new URL(
        "agenda.php",
        window.location.href
    ).href;

    // Actualización automática cada 10 segundos.
    const INTERVALO_ACTUALIZACION = 10000;


    /*
     * =========================================================
     * ELEMENTOS
     * =========================================================
     */

    const btnNotificaciones =
        document.getElementById("btnNotificaciones");

    const panelNotificaciones =
        document.getElementById("notificacionesPanel");

    const notificationBadge =
        document.getElementById("notificationBadge");

    const notificacionesLista =
        document.getElementById("notificacionesLista");

    const notificacionesCantidadTexto =
        document.getElementById(
            "notificacionesCantidadTexto"
        );

    const btnMarcarTodasLeidas =
        document.getElementById(
            "btnMarcarTodasLeidas"
        );


    /*
     * =========================================================
     * VALIDACIÓN
     * =========================================================
     */

    if (
        !btnNotificaciones ||
        !panelNotificaciones ||
        !notificationBadge ||
        !notificacionesLista
    ) {

        console.warn(
            "No se encontraron los elementos del panel de notificaciones."
        );

        return;
    }


    /*
     * =========================================================
     * CONTROL DE SOLICITUDES
     * =========================================================
     */

    let cargaEnCurso = false;


    /*
     * =========================================================
     * INTERPRETAR BOOLEANOS DE POSTGRESQL
     * =========================================================
     *
     * PostgreSQL puede devolver:
     *
     * true / false
     * "t" / "f"
     * "true" / "false"
     * 1 / 0
     *
     * Esta función interpreta correctamente
     * todos esos formatos.
     *
     * =========================================================
     */

    function esVerdadero(valor) {

        if (typeof valor === "boolean") {
            return valor;
        }

        if (typeof valor === "number") {
            return valor === 1;
        }

        const valorNormalizado =
            String(valor ?? "")
                .trim()
                .toLowerCase();

        return [
            "true",
            "t",
            "1",
            "si",
            "sí",
            "yes"
        ].includes(valorNormalizado);
    }


    /*
     * =========================================================
     * ESCAPAR HTML
     * =========================================================
     *
     * Evita insertar directamente contenido HTML
     * proveniente de la base de datos.
     *
     * =========================================================
     */

    function escaparHTML(texto) {

        const div =
            document.createElement("div");

        div.textContent =
            texto ?? "";

        return div.innerHTML;
    }


    /*
     * =========================================================
     * FORMATEAR FECHA
     * =========================================================
     */

    function formatearFecha(fecha) {

        if (!fecha) {
            return "";
        }

        try {

            let fechaNormalizada =
                String(fecha).trim();

            fechaNormalizada =
                fechaNormalizada.replace(
                    " ",
                    "T"
                );

            // PostgreSQL puede entregar microsegundos.
            // JavaScript trabaja con milisegundos.

            fechaNormalizada =
                fechaNormalizada.replace(
                    /\.(\d{3})\d+$/,
                    ".$1"
                );

            const fechaObjeto =
                new Date(fechaNormalizada);

            if (Number.isNaN(fechaObjeto.getTime())) {
                return fecha;
            }

            return new Intl.DateTimeFormat(
                "es-CL",
                {
                    day: "2-digit",
                    month: "2-digit",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: false
                }
            ).format(fechaObjeto);

        } catch (error) {

            console.error(
                "Error al formatear fecha:",
                error
            );

            return fecha;
        }
    }


    /*
     * =========================================================
     * ACTUALIZAR CONTADOR
     * =========================================================
     */

    function actualizarContador(cantidad) {

        cantidad =
            Number(cantidad) || 0;

        if (cantidad > 0) {

            notificationBadge.textContent =
                cantidad > 99
                    ? "99+"
                    : cantidad;

            notificationBadge.style.display =
                "flex";

        } else {

            notificationBadge.textContent =
                "0";

            notificationBadge.style.display =
                "none";
        }


        /*
         * Actualizar texto del encabezado.
         */

        if (notificacionesCantidadTexto) {

            if (cantidad === 0) {

                notificacionesCantidadTexto.textContent =
                    "Sin notificaciones nuevas";

            } else if (cantidad === 1) {

                notificacionesCantidadTexto.textContent =
                    "1 notificación nueva";

            } else {

                notificacionesCantidadTexto.textContent =
                    cantidad +
                    " notificaciones nuevas";
            }
        }
    }


    /*
     * =========================================================
     * MOSTRAR ESTADO VACÍO
     * =========================================================
     */

    function mostrarListaVacia() {

        notificacionesLista.innerHTML = `
            <div class="notificaciones-vacio">
                No hay notificaciones.
            </div>
        `;
    }


    /*
     * =========================================================
     * RENDERIZAR NOTIFICACIONES
     * =========================================================
     */

    function renderizarNotificaciones(
        notificaciones
    ) {

        if (
            !Array.isArray(notificaciones) ||
            notificaciones.length === 0
        ) {

            mostrarListaVacia();

            return;
        }


        notificacionesLista.innerHTML = "";


        notificaciones.forEach(function (notificacion) {

            const item =
                document.createElement("div");

            item.className =
                "notificacion-item";


            /*
             * Datos principales.
             */

            const idNotificacion =
                Number(
                    notificacion.id_notificacion
                );

            const leida =
                esVerdadero(notificacion.leida);


            /*
             * Validar identificador.
             */

            if (
                !Number.isInteger(idNotificacion) ||
                idNotificacion <= 0
            ) {
                return;
            }


            /*
             * Estado de lectura.
             */

            if (!leida) {

                item.classList.add(
                    "notificacion-no-leida"
                );
            }


            /*
             * Contenido.
             */

            const titulo =
                escaparHTML(
                    notificacion.titulo ||
                    "Nueva notificación"
                );

            const mensaje =
                escaparHTML(
                    notificacion.mensaje ||
                    ""
                );

            const fecha =
                escaparHTML(
                    formatearFecha(
                        notificacion.fecha_creacion_formateada ||
                        notificacion.fecha_creacion ||
                        ""
                    )
                );


            /*
             * Construir elemento.
             */

            item.innerHTML = `

                <div class="notificacion-item-icon">
                    🔔
                </div>

                <div class="notificacion-item-contenido">

                    <strong>
                        ${titulo}
                    </strong>

                    <p>
                        ${mensaje}
                    </p>

                    <span class="notificacion-item-fecha">
                        ${fecha}
                    </span>

                </div>

                ${
                    !leida
                        ? `
                            <button
                                type="button"
                                class="notificacion-marcar-leida"
                                data-id="${idNotificacion}"
                                title="Marcar como leída"
                                aria-label="Marcar como leída"
                            >
                                ✓
                            </button>
                        `
                        : ""
                }

            `;


            /*
             * Accesibilidad.
             */

            item.setAttribute(
                "role",
                "button"
            );

            item.setAttribute(
                "tabindex",
                "0"
            );

            item.setAttribute(
                "aria-label",
                titulo.replace(/<[^>]*>/g, "")
            );


            /*
             * =================================================
             * CLIC EN LA NOTIFICACIÓN
             * =================================================
             *
             * 1. Comprueba si está leída.
             * 2. Si está pendiente, la marca como leída.
             * 3. Cierra el panel.
             * 4. Redirige a la agenda del CESFAM.
             *
             * =================================================
             */

            async function abrirNotificacion(evento) {

                if (
                    evento &&
                    evento.target.closest(
                        ".notificacion-marcar-leida"
                    )
                ) {
                    return;
                }


                // Evitar múltiples clics.

                if (
                    item.dataset.procesando === "true"
                ) {
                    return;
                }

                item.dataset.procesando = "true";

                item.style.pointerEvents = "none";


                /*
                 * Marcar como leída si corresponde.
                 */

                if (!leida) {

                    await marcarComoLeida(
                        idNotificacion
                    );
                }


                /*
                 * Cerrar panel.
                 */

                cerrarPanel();


                /*
                 * Ir a la agenda.
                 */

                window.location.href =
                    AGENDA_URL;
            }


            item.addEventListener(
                "click",
                abrirNotificacion
            );


            /*
             * Permitir navegación mediante teclado.
             */

            item.addEventListener(
                "keydown",
                function (evento) {

                    if (
                        evento.key === "Enter" ||
                        evento.key === " "
                    ) {

                        evento.preventDefault();

                        abrirNotificacion(evento);
                    }
                }
            );


            /*
             * =================================================
             * BOTÓN MARCAR COMO LEÍDA
             * =================================================
             */

            const btnMarcarLeida =
                item.querySelector(
                    ".notificacion-marcar-leida"
                );


            if (btnMarcarLeida) {

                btnMarcarLeida.addEventListener(
                    "click",
                    async function (evento) {

                        evento.stopPropagation();

                        const id =
                            Number(
                                this.dataset.id
                            );

                        if (!id) {
                            return;
                        }

                        await marcarComoLeida(id);
                    }
                );
            }


            /*
             * Agregar elemento a la lista.
             */

            notificacionesLista.appendChild(
                item
            );

        });

    }


    /*
     * =========================================================
     * OBTENER NOTIFICACIONES
     * =========================================================
     */

    async function cargarNotificaciones() {

        if (cargaEnCurso) {
            return;
        }

        cargaEnCurso = true;

        try {

            const respuesta =
                await fetch(
                    API_URL +
                    "?accion=listar&limite=20",
                    {
                        method: "GET",
                        credentials: "same-origin",
                        cache: "no-store"
                    }
                );


            if (!respuesta.ok) {

                throw new Error(
                    "Error HTTP " +
                    respuesta.status
                );
            }


            const datos =
                await respuesta.json();


            if (!datos.ok) {

                throw new Error(
                    datos.mensaje ||
                    "No se pudieron obtener las notificaciones."
                );
            }


            /*
             * Actualizar contador.
             */

            actualizarContador(
                datos.cantidad || 0
            );


            /*
             * Renderizar lista.
             */

            renderizarNotificaciones(
                datos.notificaciones || []
            );


        } catch (error) {

            console.error(
                "Error al cargar notificaciones:",
                error
            );

        } finally {

            cargaEnCurso = false;
        }
    }


    /*
     * =========================================================
     * MARCAR UNA NOTIFICACIÓN COMO LEÍDA
     * =========================================================
     */

    async function marcarComoLeida(
        idNotificacion
    ) {

        try {

            const formulario =
                new URLSearchParams();

            formulario.append(
                "accion",
                "marcar_leida"
            );

            formulario.append(
                "id_notificacion",
                idNotificacion
            );


            const respuesta =
                await fetch(
                    API_URL,
                    {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type":
                                "application/x-www-form-urlencoded"
                        },
                        body:
                            formulario.toString()
                    }
                );


            if (!respuesta.ok) {

                throw new Error(
                    "Error HTTP " +
                    respuesta.status
                );
            }


            const datos =
                await respuesta.json();


            if (!datos.ok) {

                throw new Error(
                    datos.mensaje ||
                    "No se pudo marcar la notificación."
                );
            }


            /*
             * Actualizar contador y lista.
             */

            await cargarNotificaciones();

            return true;


        } catch (error) {

            console.error(
                "Error al marcar notificación como leída:",
                error
            );

            return false;
        }
    }


    /*
     * =========================================================
     * MARCAR TODAS COMO LEÍDAS
     * =========================================================
     */

    async function marcarTodasComoLeidas() {

        try {

            const formulario =
                new URLSearchParams();

            formulario.append(
                "accion",
                "marcar_todas_leidas"
            );


            const respuesta =
                await fetch(
                    API_URL,
                    {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "Content-Type":
                                "application/x-www-form-urlencoded"
                        },
                        body:
                            formulario.toString()
                    }
                );


            if (!respuesta.ok) {

                throw new Error(
                    "Error HTTP " +
                    respuesta.status
                );
            }


            const datos =
                await respuesta.json();


            if (!datos.ok) {

                throw new Error(
                    datos.mensaje ||
                    "No se pudieron marcar las notificaciones."
                );
            }


            /*
             * Actualizar notificaciones.
             */

            await cargarNotificaciones();


        } catch (error) {

            console.error(
                "Error al marcar todas las notificaciones:",
                error
            );
        }
    }


    /*
     * =========================================================
     * ABRIR PANEL
     * =========================================================
     */

    function abrirPanel() {

        panelNotificaciones.classList.add(
            "notificaciones-panel-abierto"
        );

        panelNotificaciones.setAttribute(
            "aria-hidden",
            "false"
        );

        btnNotificaciones.setAttribute(
            "aria-expanded",
            "true"
        );


        /*
         * Actualizar inmediatamente.
         */

        cargarNotificaciones();
    }


    /*
     * =========================================================
     * CERRAR PANEL
     * =========================================================
     */

    function cerrarPanel() {

        panelNotificaciones.classList.remove(
            "notificaciones-panel-abierto"
        );

        panelNotificaciones.setAttribute(
            "aria-hidden",
            "true"
        );

        btnNotificaciones.setAttribute(
            "aria-expanded",
            "false"
        );
    }


    /*
     * =========================================================
     * BOTÓN DE NOTIFICACIONES
     * =========================================================
     */

    btnNotificaciones.addEventListener(
        "click",
        function (evento) {

            evento.stopPropagation();

            const panelAbierto =
                panelNotificaciones.classList.contains(
                    "notificaciones-panel-abierto"
                );


            if (panelAbierto) {

                cerrarPanel();

            } else {

                abrirPanel();
            }
        }
    );


    /*
     * =========================================================
     * CERRAR AL HACER CLIC FUERA
     * =========================================================
     */

    document.addEventListener(
        "click",
        function (evento) {

            if (
                !panelNotificaciones.contains(
                    evento.target
                ) &&
                !btnNotificaciones.contains(
                    evento.target
                )
            ) {

                cerrarPanel();
            }
        }
    );


    /*
     * =========================================================
     * CERRAR CON ESCAPE
     * =========================================================
     */

    document.addEventListener(
        "keydown",
        function (evento) {

            if (evento.key === "Escape") {

                cerrarPanel();
            }
        }
    );


    /*
     * =========================================================
     * MARCAR TODAS COMO LEÍDAS
     * =========================================================
     */

    if (btnMarcarTodasLeidas) {

        btnMarcarTodasLeidas.addEventListener(
            "click",
            function (evento) {

                evento.stopPropagation();

                marcarTodasComoLeidas();
            }
        );
    }


    /*
     * =========================================================
     * CARGA INICIAL
     * =========================================================
     */

    cargarNotificaciones();


    /*
     * =========================================================
     * ACTUALIZACIÓN AUTOMÁTICA
     * =========================================================
     *
     * Consulta nuevas notificaciones cada 10 segundos.
     *
     * =========================================================
     */

    setInterval(
        cargarNotificaciones,
        INTERVALO_ACTUALIZACION
    );

});
