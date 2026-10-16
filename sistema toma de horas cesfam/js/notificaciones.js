document.addEventListener("DOMContentLoaded", function () {

    /*
     * =========================================================
     * CONFIGURACIÓN
     * =========================================================
     */

    const API_URL = "../api/notificaciones.php";

    const AGENDA_URL =
        "/municipalidad/funcionarios/agenda.php";

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
        return;
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

        const div = document.createElement("div");

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

        return fecha;
    }


    /*
     * =========================================================
     * ACTUALIZAR CONTADOR
     * =========================================================
     */

    function actualizarContador(cantidad) {

        cantidad = Number(cantidad) || 0;


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
         * Texto dentro del encabezado
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

            notificacionesLista.innerHTML = `
                <div class="notificaciones-vacio">
                    No hay notificaciones.
                </div>
            `;

            return;
        }


        notificacionesLista.innerHTML = "";


        notificaciones.forEach(function (notificacion) {

            const item =
                document.createElement("div");

            item.className =
                "notificacion-item";


            /*
             * Notificación no leída
             */

            if (!notificacion.leida) {

                item.classList.add(
                    "notificacion-no-leida"
                );
            }


            /*
             * Datos
             */

            const idNotificacion =
                Number(
                    notificacion.id_notificacion
                );

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
                formatearFecha(
                    notificacion
                        .fecha_creacion_formateada ||
                    notificacion.fecha_creacion ||
                    ""
                );


            /*
             * =================================================
             * CONTENIDO DE LA NOTIFICACIÓN
             * =================================================
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
                    !notificacion.leida
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
             * =================================================
             * CLIC EN LA NOTIFICACIÓN
             * =================================================
             *
             * Al hacer clic en la notificación:
             *
             * 1. Si está sin leer, se marca como leída.
             * 2. Se cierra el panel.
             * 3. Se redirige a agenda.php.
             *
             * =================================================
             */

            item.addEventListener(
                "click",
                async function (evento) {

                    /*
                     * Si se hizo clic específicamente
                     * en el botón de marcar como leída,
                     * NO redirigir a la agenda.
                     */

                    if (
                        evento.target.closest(
                            ".notificacion-marcar-leida"
                        )
                    ) {
                        return;
                    }


                    /*
                     * Evitar múltiples clics
                     */

                    item.style.pointerEvents =
                        "none";


                    /*
                     * Marcar como leída si corresponde
                     */

                    if (!notificacion.leida) {

                        await marcarComoLeida(
                            idNotificacion
                        );
                    }


                    /*
                     * Cerrar panel
                     */

                    cerrarPanel();


                    /*
                     * Ir a la agenda
                     */

                    window.location.href =
                        AGENDA_URL;
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
             * Agregar elemento a la lista
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
             * Actualizar contador
             */

            actualizarContador(
                datos.cantidad || 0
            );


            /*
             * Renderizar
             */

            renderizarNotificaciones(
                datos.notificaciones || []
            );


        } catch (error) {

            console.error(
                "Error al cargar notificaciones:",
                error
            );

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
             * Actualizar notificaciones después
             * de marcarla como leída.
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
             * Actualizar lista y contador
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
         * Actualizar inmediatamente
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