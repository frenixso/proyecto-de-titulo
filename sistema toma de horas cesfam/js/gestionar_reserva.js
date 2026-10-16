document.addEventListener("DOMContentLoaded", function () {

    const API_URL = "../api/gestionar_reserva.php";
    const API_HORARIOS_URL = "../api/obtener_horarios.php";
    const API_CREAR_URL = "../api/crear_reserva.php";
    const API_DEPARTAMENTOS_URL = "../api/obtener_departamentos.php";


    /*
     * =========================================================
     * PARÁMETROS
     * =========================================================
     */

    function obtenerParametro(nombre) {

        const parametros =
            new URLSearchParams(
                window.location.search
            );

        return parametros.get(nombre);
    }

    const idReserva =
        obtenerParametro("id_reserva");

    const abrirCrearReserva =
        obtenerParametro("crear_reserva");

    const servicioURL =
        obtenerParametro("servicio");

    const fechaURL =
        obtenerParametro("fecha");

    const horaURL =
        obtenerParametro("hora");


    /*
     * =========================================================
     * FECHA Y HORA LOCAL
     * =========================================================
     */

    function obtenerFechaLocal() {

        const fecha = new Date();

        const año =
            fecha.getFullYear();

        const mes =
            String(
                fecha.getMonth() + 1
            ).padStart(2, "0");

        const dia =
            String(
                fecha.getDate()
            ).padStart(2, "0");

        return `${año}-${mes}-${dia}`;
    }


    function obtenerHoraLocal() {

        const fecha = new Date();

        const horas =
            String(
                fecha.getHours()
            ).padStart(2, "0");

        const minutos =
            String(
                fecha.getMinutes()
            ).padStart(2, "0");

        return `${horas}:${minutos}`;
    }


    /*
     * =========================================================
     * NOTIFICACIONES
     * =========================================================
     */

    function showNotification(
        mensaje,
        tipo = "success"
    ) {

        const notification =
            document.getElementById(
                "createReservationNotification"
            );

        const notificationMessage =
            document.getElementById(
                "createReservationNotificationMessage"
            );

        if (
            !notification ||
            !notificationMessage
        ) {

            alert(mensaje);

            return;
        }

        notificationMessage.textContent =
            mensaje;

        notification.classList.remove(
            "success",
            "error",
            "warning",
            "info"
        );

        notification.classList.add(
            tipo
        );

        notification.hidden =
            false;

        notification.style.display =
            "block";

        setTimeout(
            function () {

                notification.hidden =
                    true;

                notification.style.display =
                    "none";

            },
            5000
        );
    }


    /*
     * =========================================================
     * API GENERAL
     * =========================================================
     */

    async function enviarAPI(
        accion,
        datos = {}
    ) {

        const formData =
            new FormData();

        formData.append(
            "accion",
            accion
        );

        Object.keys(datos).forEach(
            function (clave) {

                formData.append(
                    clave,
                    datos[clave]
                );
            }
        );

        const respuesta =
            await fetch(
                API_URL,
                {
                    method: "POST",
                    body: formData
                }
            );

        const texto =
            await respuesta.text();

        let resultado;

        try {

            resultado =
                JSON.parse(texto);

        } catch (error) {

            console.error(
                "Respuesta no válida de la API:",
                texto
            );

            throw new Error(
                "El servidor devolvió una respuesta no válida."
            );
        }

        if (
            !respuesta.ok ||
            resultado.ok === false
        ) {

            throw new Error(
                resultado.mensaje ||
                resultado.message ||
                "No se pudo completar la operación."
            );
        }

        return resultado;
    }


    /*
     * =========================================================
     * ELEMENTOS DEL FORMULARIO CREAR RESERVA
     * =========================================================
     */

    const btnCreateReservation =
        document.getElementById(
            "btnCreateReservation"
        );

    const createReservationSection =
        document.getElementById(
            "createReservationSection"
        );

    const createReservationForm =
        document.getElementById(
            "createReservationForm"
        );

    const createNombre =
        document.getElementById(
            "createNombre"
        );

    const createRut =
        document.getElementById(
            "createRut"
        );

    const createTelefono =
        document.getElementById(
            "createTelefono"
        );

    const createCorreo =
        document.getElementById(
            "createCorreo"
        );

    const createDepartamento =
        document.getElementById(
            "createDepartamento"
        );

    const createServicio =
        document.getElementById(
            "createServicio"
        );

    const createFecha =
        document.getElementById(
            "createFecha"
        );

    const createHorario =
        document.getElementById(
            "createHorario"
        );

    const createHorarioEstado =
        document.getElementById(
            "createHorarioEstado"
        );

    const createObservacion =
        document.getElementById(
            "createObservacion"
        );

    const errorNombre =
        document.getElementById(
            "errorNombre"
        );

    const errorRut =
        document.getElementById(
            "errorRut"
        );

    const errorTelefono =
        document.getElementById(
            "errorTelefono"
        );

    const errorCorreo =
        document.getElementById(
            "errorCorreo"
        );

    const previewNombre =
        document.getElementById(
            "previewNombre"
        );

    const previewIdentificacion =
        document.getElementById(
            "previewIdentificacion"
        );

    const previewDepartamento =
        document.getElementById(
            "previewDepartamento"
        );

    const previewServicio =
        document.getElementById(
            "previewServicio"
        );

    const previewFecha =
        document.getElementById(
            "previewFecha"
        );

    const previewHora =
        document.getElementById(
            "previewHora"
        );

    const btnCloseCreateModal =
        document.getElementById(
            "btnCloseCreateModal"
        );

    const btnCancelCreate =
        document.getElementById(
            "btnCancelCreate"
        );


    /*
     * =========================================================
     * VARIABLES DE DEPARTAMENTOS
     * =========================================================
     */

    let departamentosFormulario = [];


    /*
     * =========================================================
     * POPUP VER RESERVA
     *
     * CORRECCIÓN:
     *
     * El popup ya existe en gestionar_reserva.php.
     * Primero lo buscamos y lo reutilizamos.
     *
     * No debemos crear un segundo elemento con el mismo ID,
     * porque document.getElementById() podría devolver los
     * campos del popup equivocado.
     * =========================================================
     */

    let reservationViewModal =
        document.getElementById(
            "reservationViewModal"
        );


    function crearPopupVerReserva() {

        /*
         * Si PHP ya creó el popup, usarlo.
         */
        reservationViewModal =
            document.getElementById(
                "reservationViewModal"
            );

        if (reservationViewModal) {

            return reservationViewModal;
        }

        /*
         * Crear un popup de respaldo solamente si el HTML
         * no contiene uno.
         */
        reservationViewModal =
            document.createElement("div");

        reservationViewModal.id =
            "reservationViewModal";

        reservationViewModal.className =
            "reservation-view-modal";

        reservationViewModal.hidden =
            true;

        reservationViewModal.setAttribute(
            "aria-hidden",
            "true"
        );

        reservationViewModal.innerHTML = `
            <div
                class="reservation-view-dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="reservationViewTitleJS"
            >

                <div class="reservation-view-header">

                    <h2 id="reservationViewTitleJS">
                        Detalle de reserva
                    </h2>

                    <button
                        type="button"
                        id="btnCloseReservationViewJS"
                        class="reservation-view-close"
                        aria-label="Cerrar"
                    >
                        &times;
                    </button>

                </div>

                <div class="reservation-view-body">

                    <div class="reservation-view-item">
                        <span>ID reserva:</span>
                        <strong id="viewReservaId">-</strong>
                    </div>

                    <div class="reservation-view-item">
                        <span>Estado:</span>
                        <strong id="viewReservaEstado">-</strong>
                    </div>

                    <div class="reservation-view-item">
                        <span>Nombre:</span>
                        <strong id="viewReservaNombre">-</strong>
                    </div>

                    <div class="reservation-view-item">
                        <span>Identificación:</span>
                        <strong id="viewReservaRut">-</strong>
                    </div>

                    <div class="reservation-view-item">
                        <span>Servicio:</span>
                        <strong id="viewReservaServicio">-</strong>
                    </div>

                    <div class="reservation-view-item">
                        <span>Fecha:</span>
                        <strong id="viewReservaFecha">-</strong>
                    </div>

                    <div class="reservation-view-item">
                        <span>Hora:</span>
                        <strong id="viewReservaHora">-</strong>
                    </div>

                    <div class="reservation-view-item">
                        <span>Teléfono:</span>
                        <strong id="viewReservaTelefono">-</strong>
                    </div>

                    <div class="reservation-view-item">
                        <span>Correo:</span>
                        <strong id="viewReservaCorreo">-</strong>
                    </div>

                </div>

                <div class="reservation-view-footer">

                    <button
                        type="button"
                        id="btnCloseReservationViewFooterJS"
                        class="secondary-button"
                    >
                        Cerrar
                    </button>

                </div>

            </div>
        `;

        document.body.appendChild(
            reservationViewModal
        );

        return reservationViewModal;
    }


    function formatearEstadoReserva(
        estado
    ) {

        const estados = {
            confirmada: "Confirmada",
            cancelada: "Cancelada",
            reagendada: "Reagendada",
            pendiente: "Pendiente"
        };

        const estadoNormalizado =
            String(estado || "")
                .trim()
                .toLowerCase();

        return (
            estados[estadoNormalizado] ||
            estado ||
            "No registrado"
        );
    }


    function formatearTipoIdentificacion(
        tipo
    ) {

        const tipos = {
            rut: "RUT",
            rut_extranjero: "RUT extranjero",
            pasaporte: "Pasaporte"
        };

        const tipoNormalizado =
            String(tipo || "")
                .trim()
                .toLowerCase();

        return (
            tipos[tipoNormalizado] ||
            tipo ||
            "Identificación"
        );
    }


    function formatearFechaReserva(
        fecha
    ) {

        if (!fecha) {
            return "No registrada";
        }

        const partes =
            String(fecha).substring(0, 10).split("-");

        if (
            partes.length !== 3 ||
            !/^\d{4}$/.test(partes[0])
        ) {

            return fecha;
        }

        return (
            `${partes[2]}/${partes[1]}/${partes[0]}`
        );
    }


    /*
     * =========================================================
     * ABRIR POPUP VER RESERVA
     *
     * CORRECCIÓN:
     *
     * 1. Reutiliza el popup que ya existe en PHP.
     * 2. Lee los datos del botón Ver.
     * 3. Usa viewReservaRut, que es el ID real del HTML.
     * 4. Busca cada campo dentro del popup correcto.
     * 5. Evita que los campos queden vacíos por IDs duplicados.
     * =========================================================
     */

    function abrirPopupVerReserva(
        boton
    ) {

        if (!boton) {
            return;
        }

        const modal =
            crearPopupVerReserva();

        if (!modal) {

            console.error(
                "No se encontró ni se pudo crear el popup de reserva."
            );

            return;
        }

        const dataset =
            boton.dataset;


        /*
         * Obtener todos los datos del botón.
         */
        const id =
            dataset.idReserva ||
            dataset.id ||
            "";

        const estado =
            dataset.estado ||
            "";

        const nombre =
            dataset.nombre ||
            "";

        const rut =
            dataset.rut ||
            dataset.identificacion ||
            "";

        const tipoIdentificacion =
            dataset.tipoIdentificacion ||
            "rut";

        const telefono =
            dataset.telefono ||
            "";

        const correo =
            dataset.correo ||
            "";

        const servicio =
            dataset.servicio ||
            dataset.servicioNombre ||
            "";

        const fecha =
            dataset.fecha ||
            "";

        const hora =
            dataset.hora ||
            "";


        /*
         * Formatear valores para mostrar.
         */
        const valores = {

            viewReservaId:
                id !== ""
                    ? `#${id}`
                    : "No registrado",

            viewReservaEstado:
                formatearEstadoReserva(estado),

            viewReservaNombre:
                nombre || "No registrado",

            /*
             * IMPORTANTE:
             * El modal existente en PHP utiliza viewReservaRut.
             * No usar viewReservaIdentificacion aquí.
             */
            viewReservaRut:
                rut
                    ? (
                        formatearTipoIdentificacion(
                            tipoIdentificacion
                        ) + " - " + rut
                    )
                    : "No registrado",

            viewReservaServicio:
                servicio || "No registrado",

            viewReservaFecha:
                formatearFechaReserva(fecha),

            viewReservaHora:
                hora || "No registrada",

            viewReservaTelefono:
                telefono || "No registrado",

            viewReservaCorreo:
                correo || "No registrado"
        };


        /*
         * Rellenar los campos buscando los elementos DENTRO
         * del modal correspondiente.
         *
         * No se buscan globalmente porque podría haber campos
         * con IDs duplicados en otro popup.
         */
        Object.entries(valores).forEach(
            function ([idElemento, valor]) {

                const elemento =
                    modal.querySelector(
                        `#${idElemento}`
                    );

                if (elemento) {

                    elemento.textContent =
                        valor;

                } else {

                    console.warn(
                        "No se encontró el campo del modal:",
                        idElemento
                    );
                }
            }
        );


        /*
         * Mostrar el modal.
         */
        modal.hidden =
            false;

        modal.removeAttribute(
            "hidden"
        );

        modal.classList.add(
            "active"
        );

        modal.setAttribute(
            "aria-hidden",
            "false"
        );

        document.body.classList.add(
            "modal-open"
        );


        console.log(
            "Datos cargados en el popup Ver reserva:",
            valores
        );
    }


    /*
     * =========================================================
     * CERRAR POPUP VER RESERVA
     * =========================================================
     */

    function cerrarPopupVerReserva() {

        if (!reservationViewModal) {

            reservationViewModal =
                document.getElementById(
                    "reservationViewModal"
                );
        }

        if (!reservationViewModal) {
            return;
        }

        reservationViewModal.classList.remove(
            "active"
        );

        reservationViewModal.hidden =
            true;

        reservationViewModal.setAttribute(
            "aria-hidden",
            "true"
        );

        document.body.classList.remove(
            "modal-open"
        );
    }


    /*
     * =========================================================
     * POPUP MODIFICAR / REAGENDAR
     * =========================================================
     */

    let reservationModifyModal = null;
    let reservaModificarActual = null;


    function crearPopupModificarReserva() {


        /*
         * =====================================================
         * SI YA FUE REGISTRADO EN JAVASCRIPT, NO CREAR OTRO
         * =====================================================
         */

        if (reservationModifyModal) {

            console.log(
                "Popup de modificación ya existe."
            );

            return;
        }


        /*
         * =====================================================
         * BUSCAR POPUP EXISTENTE CREADO POR PHP
         * =====================================================
         */

        const popupExistente =
            document.getElementById(
                "reservationModifyModal"
            );


        if (popupExistente) {

            reservationModifyModal =
                popupExistente;

            reservationModifyModal.hidden =
                true;

            reservationModifyModal.style.display =
                "none";

            return;
        }


        /*
         * =====================================================
         * SI PHP NO TIENE EL POPUP, CREARLO DESDE JAVASCRIPT
         * =====================================================
         */

        reservationModifyModal =
            document.createElement("div");

        reservationModifyModal.id =
            "reservationModifyModal";

        reservationModifyModal.className =
            "reservation-modal";

        reservationModifyModal.hidden =
            true;

        reservationModifyModal.innerHTML = `
            <div class="reservation-modal-overlay"></div>

            <div class="reservation-modal-content">

                <div class="reservation-modal-header">

                    <h2>Modificar reserva</h2>

                    <button
                        type="button"
                        class="reservation-modal-close"
                        data-close-modify-reservation
                    >
                        &times;
                    </button>

                </div>

                <div class="reservation-modal-body">

                    <input
                        type="hidden"
                        id="modifyReservaId"
                    >

                    <div class="reservation-detail">
                        <strong>Nombre:</strong>
                        <span id="modifyReservaNombre">-</span>
                    </div>

                    <div class="reservation-detail">
                        <strong>Identificación:</strong>
                        <span id="modifyReservaRut">-</span>
                    </div>

                    <div class="reservation-detail">
                        <strong>Teléfono:</strong>
                        <span id="modifyReservaTelefono">-</span>
                    </div>

                    <div class="reservation-detail">
                        <strong>Correo:</strong>
                        <span id="modifyReservaCorreo">-</span>
                    </div>

                    <div class="reservation-detail">
                        <strong>ID de reserva:</strong>
                        <span id="modifyReservaIdMostrar">-</span>
                    </div>

                    <div class="reservation-detail">
                        <strong>Servicio:</strong>
                        <span id="modifyReservaServicio">-</span>
                    </div>

                    <div class="reservation-detail">
                        <strong>Fecha actual:</strong>
                        <span id="modifyReservaFechaActual">-</span>
                    </div>

                    <div class="reservation-detail">
                        <strong>Hora actual:</strong>
                        <span id="modifyReservaHoraActual">-</span>
                    </div>

                    <div class="form-group">

                        <label for="modifyReservaFecha">
                            Nueva fecha
                        </label>

                        <input
                            type="date"
                            id="modifyReservaFecha"
                            min="${obtenerFechaLocal()}"
                        >

                    </div>

                    <div class="form-group">

                        <label for="modifyReservaHora">
                            Nueva hora
                        </label>

                        <select
                            id="modifyReservaHora"
                            disabled
                        >

                            <option value="">
                                Seleccione una fecha
                            </option>

                        </select>

                        <small
                            id="modifyReservaEstadoHorario"
                        ></small>

                    </div>

                    <div class="reservation-modal-actions">

                        <button
                            type="button"
                            id="btnCancelReservationModify"
                            class="btn-secondary"
                        >
                            Cancelar
                        </button>

                        <button
                            type="button"
                            id="btnSaveReservationModify"
                            class="btn-primary"
                        >
                            Guardar cambios
                        </button>

                    </div>

                </div>

            </div>
        `;

        document.body.appendChild(
            reservationModifyModal
        );

        console.log(
            "Popup de modificación creado:",
            reservationModifyModal
        );
    }


    function cerrarPopupModificarReserva() {

        if (!reservationModifyModal) {
            return;
        }

        reservationModifyModal.hidden =
            true;

        reservationModifyModal.style.display =
            "none";

        reservationModifyModal.style.visibility =
            "hidden";

        reservationModifyModal.style.opacity =
            "0";

        document.body.classList.remove(
            "modal-open"
        );

        reservaModificarActual =
            null;
    }


    /*
     * =========================================================
     * CARGAR HORARIOS PARA MODIFICAR
     * =========================================================
     */

    async function cargarHorariosModificar() {

        if (!reservaModificarActual) {
            return;
        }

        const fechaElement =
            document.getElementById(
                "modifyReservaFecha"
            );

        const selectHora =
            document.getElementById(
                "modifyReservaHora"
            );

        const estadoHorario =
            document.getElementById(
                "modifyReservaEstadoHorario"
            );

        if (
            !fechaElement ||
            !selectHora ||
            !estadoHorario
        ) {

            console.error(
                "No se encontraron los elementos del formulario de modificación."
            );

            return;
        }

        const fecha =
            fechaElement.value;

        const servicio =
            reservaModificarActual.servicio;


        if (!fecha) {

            selectHora.innerHTML = `
                <option value="">
                    Seleccione una fecha
                </option>
            `;

            selectHora.disabled =
                true;

            estadoHorario.textContent =
                "";

            estadoHorario.className =
                "";

            return;
        }


        if (!servicio) {

            console.error(
                "No existe ID de servicio para cargar horarios.",
                reservaModificarActual
            );

            selectHora.innerHTML = `
                <option value="">
                    No se pudo identificar el servicio
                </option>
            `;

            selectHora.disabled =
                true;

            estadoHorario.textContent =
                "No se pudo identificar el servicio de la reserva.";

            estadoHorario.className =
                "error";

            return;
        }


        selectHora.innerHTML = `
            <option value="">
                Cargando horarios...
            </option>
        `;

        selectHora.disabled =
            true;

        estadoHorario.textContent =
            "Cargando horarios...";

        estadoHorario.className =
            "";


        try {

            const url =
                `${API_HORARIOS_URL}?servicio=${encodeURIComponent(servicio)}&fecha=${encodeURIComponent(fecha)}`;


            console.log(
                "Consultando horarios:",
                url
            );


            const respuesta =
                await fetch(
                    url,
                    {
                        method: "GET",
                        cache: "no-store"
                    }
                );


            const datos =
                await respuesta.json();


            console.log(
                "Respuesta horarios:",
                datos
            );


            if (
                !respuesta.ok ||
                datos.ok === false
            ) {

                throw new Error(
                    datos.mensaje ||
                    datos.message ||
                    "No se pudieron cargar los horarios."
                );
            }


            selectHora.innerHTML = `
                <option value="">
                    Seleccione una hora
                </option>
            `;


            const horarios =
                Array.isArray(
                    datos.horarios
                )
                    ? datos.horarios
                    : [];


            let disponibles = 0;


            const fechaHoy =
                obtenerFechaLocal();

            const horaHoy =
                obtenerHoraLocal();


            horarios.forEach(
                function (horario) {

                    /*
                     * El id_horario puede ser NULL cuando el
                     * horario todavía no existe físicamente
                     * en la tabla horarios.
                     *
                     * No descartarlo por ese motivo.
                     */

                    const idHorario =
                        horario.id_horario ||
                        horario.idHorario ||
                        horario.id ||
                        "";


                    let hora =
                        horario.hora ||
                        horario.hora_inicio ||
                        horario.hora_horario ||
                        "";


                    if (!hora) {
                        return;
                    }


                    hora =
                        String(hora).substring(0, 5);


                    let disponible =
                        horario.disponible === true ||
                        horario.disponible === "true" ||
                        horario.estado === "disponible";


                    if (
                        fecha === fechaHoy &&
                        hora <= horaHoy
                    ) {

                        disponible =
                            false;
                    }


                    const option =
                        document.createElement(
                            "option"
                        );


                    option.value =
                        hora;

                    option.dataset.hora =
                        hora;

                    option.dataset.idHorario =
                        idHorario;


                    if (disponible) {

                        option.textContent =
                            hora;

                        disponibles++;

                    } else {

                        option.textContent =
                            `${hora} - No disponible`;

                        option.disabled =
                            true;
                    }


                    selectHora.appendChild(
                        option
                    );
                }
            );


            selectHora.disabled =
                disponibles === 0;


            if (disponibles === 0) {

                estadoHorario.textContent =
                    datos.mensaje ||
                    "No hay horarios disponibles para esta fecha.";

                estadoHorario.className =
                    "error";

            } else {

                estadoHorario.textContent =
                    `${disponibles} horario(s) disponible(s).`;

                estadoHorario.className =
                    "success";
            }

        } catch (error) {

            console.error(
                "Error cargando horarios para modificar:",
                error
            );


            selectHora.innerHTML = `
                <option value="">
                    Error al cargar horarios
                </option>
            `;

            selectHora.disabled =
                true;


            estadoHorario.textContent =
                error.message ||
                "No se pudieron cargar los horarios.";

            estadoHorario.className =
                "error";
        }
    }


    /*
     * =========================================================
     * ABRIR POPUP MODIFICAR
     * =========================================================
     */

    function abrirPopupModificarReserva(
        boton
    ) {

        console.log(
            "=========================================="
        );

        console.log(
            "ABRIENDO POPUP MODIFICAR"
        );

        console.log(
            "Botón recibido:",
            boton
        );


        if (!boton) {

            console.error(
                "No se recibió el botón de modificar."
            );

            return;
        }


        crearPopupModificarReserva();


        function obtenerDato(
            atributo
        ) {

            const valor =
                boton.getAttribute(
                    atributo
                );

            if (valor === null) {
                return "";
            }

            return valor.trim();
        }


        const idReservaBoton =
            obtenerDato("data-id-reserva") ||
            obtenerDato("data-id");


        const nombreBoton =
            obtenerDato("data-nombre");


        const rutBoton =
            obtenerDato("data-rut");


        const tipoIdentificacionBoton =
            obtenerDato("data-tipo-identificacion");


        const telefonoBoton =
            obtenerDato("data-telefono");


        const correoBoton =
            obtenerDato("data-correo");


        const idServicioBoton =
            obtenerDato("data-id-servicio") ||
            obtenerDato("data-servicio-id");


        const servicioNombreBoton =
            obtenerDato("data-servicio-nombre") ||
            obtenerDato("data-servicio");


        const fechaBoton =
            obtenerDato("data-fecha");


        const horaBoton =
            obtenerDato("data-hora");


        const estadoBoton =
            obtenerDato("data-estado");


        const esPasadaBoton =
            obtenerDato("data-es-pasada");


        reservaModificarActual = {

            idReserva:
                idReservaBoton,

            nombre:
                nombreBoton,

            rut:
                rutBoton,

            tipoIdentificacion:
                tipoIdentificacionBoton,

            telefono:
                telefonoBoton,

            correo:
                correoBoton,

            servicio:
                idServicioBoton,

            servicioNombre:
                servicioNombreBoton,

            fecha:
                fechaBoton,

            hora:
                horaBoton,

            estado:
                estadoBoton,

            esPasada:
                esPasadaBoton === "1" ||
                esPasadaBoton.toLowerCase() === "true"
        };


        console.log(
            "Datos obtenidos del botón:",
            reservaModificarActual
        );


        /*
         * Cargar información.
         */

        const elementoId =
            document.getElementById(
                "modifyReservaId"
            );

        const elementoIdMostrar =
            document.getElementById(
                "modifyReservaIdMostrar"
            );

        const elementoNombre =
            document.getElementById(
                "modifyReservaNombre"
            );

        const elementoRut =
            document.getElementById(
                "modifyReservaRut"
            );

        const elementoIdentificacionAnterior =
            document.getElementById(
                "modifyReservaIdentificacion"
            );

        const elementoTelefono =
            document.getElementById(
                "modifyReservaTelefono"
            );

        const elementoCorreo =
            document.getElementById(
                "modifyReservaCorreo"
            );

        const elementoServicio =
            document.getElementById(
                "modifyReservaServicio"
            );

        const elementoFechaActual =
            document.getElementById(
                "modifyReservaFechaActual"
            );

        const elementoHoraActual =
            document.getElementById(
                "modifyReservaHoraActual"
            );


        if (elementoId) {

            elementoId.value =
                reservaModificarActual.idReserva || "";
        }


        if (elementoIdMostrar) {

            elementoIdMostrar.textContent =
                reservaModificarActual.idReserva || "-";
        }


        if (elementoNombre) {

            elementoNombre.textContent =
                reservaModificarActual.nombre || "-";
        }


        const identificacion =
            reservaModificarActual.rut || "-";


        if (elementoRut) {

            elementoRut.textContent =
                identificacion;
        }


        if (elementoIdentificacionAnterior) {

            elementoIdentificacionAnterior.textContent =
                identificacion;
        }


        if (elementoTelefono) {

            elementoTelefono.textContent =
                reservaModificarActual.telefono || "-";
        }


        if (elementoCorreo) {

            elementoCorreo.textContent =
                reservaModificarActual.correo || "-";
        }


        if (elementoServicio) {

            elementoServicio.textContent =
                reservaModificarActual.servicioNombre || "-";
        }


        if (elementoFechaActual) {

            elementoFechaActual.textContent =
                reservaModificarActual.fecha || "-";
        }


        if (elementoHoraActual) {

            elementoHoraActual.textContent =
                reservaModificarActual.hora || "-";
        }


        const fecha =
            document.getElementById(
                "modifyReservaFecha"
            );


        const hora =
            document.getElementById(
                "modifyReservaHora"
            );


        if (fecha) {

            fecha.min =
                obtenerFechaLocal();

            fecha.value =
                "";
        }


        if (hora) {

            hora.innerHTML = `
                <option value="">
                    Seleccione una fecha
                </option>
            `;

            hora.value =
                "";

            hora.disabled =
                true;
        }


        const estadoHorario =
            document.getElementById(
                "modifyReservaEstadoHorario"
            );


        if (estadoHorario) {

            estadoHorario.textContent =
                "Seleccione una nueva fecha para consultar los horarios.";

            estadoHorario.className =
                "";
        }


        if (!reservationModifyModal) {

            console.error(
                "reservationModifyModal no fue creado."
            );

            return;
        }


        reservationModifyModal.hidden =
            false;

        reservationModifyModal.removeAttribute(
            "hidden"
        );


        reservationModifyModal.style.setProperty(
            "display",
            "flex",
            "important"
        );

        reservationModifyModal.style.setProperty(
            "visibility",
            "visible",
            "important"
        );

        reservationModifyModal.style.setProperty(
            "opacity",
            "1",
            "important"
        );

        reservationModifyModal.style.setProperty(
            "z-index",
            "99999",
            "important"
        );


        document.body.classList.add(
            "modal-open"
        );


        if (!reservaModificarActual.idReserva) {

            console.error(
                "El botón no contiene ID de reserva."
            );

            showNotification(
                "La reserva se abrió, pero no contiene su ID.",
                "error"
            );
        }


        if (!reservaModificarActual.servicio) {

            console.error(
                "El botón no contiene ID de servicio."
            );

            if (estadoHorario) {

                estadoHorario.textContent =
                    "No se pudo identificar el servicio de esta reserva.";

                estadoHorario.className =
                    "error";
            }
        }


        console.log(
            "POPUP MOSTRADO CORRECTAMENTE.",
            reservaModificarActual
        );

        console.log(
            "=========================================="
        );
    }


    /*
     * =========================================================
     * IDENTIFICACIÓN
     * =========================================================
     */

    function formatearRutChileno(
        valor
    ) {

        valor =
            String(valor || "")
                .replace(/[^0-9kK]/g, "")
                .toUpperCase();

        if (!valor) {
            return "";
        }

        if (valor.length === 1) {
            return valor;
        }

        const cuerpo =
            valor.slice(0, -1);

        const dv =
            valor.slice(-1);

        let cuerpoFormateado = "";
        let contador = 0;

        for (
            let i = cuerpo.length - 1;
            i >= 0;
            i--
        ) {

            cuerpoFormateado =
                cuerpo[i] +
                cuerpoFormateado;

            contador++;

            if (
                contador === 3 &&
                i !== 0
            ) {

                cuerpoFormateado =
                    "." +
                    cuerpoFormateado;

                contador = 0;
            }
        }

        return `${cuerpoFormateado}-${dv}`;
    }


    function validarRutChileno(
        rut
    ) {

        const valor =
            String(rut || "")
                .replace(/\./g, "")
                .replace(/-/g, "")
                .toUpperCase();

        if (
            !/^\d{7,8}[0-9K]$/.test(valor)
        ) {
            return false;
        }

        const cuerpo =
            valor.slice(0, -1);

        const dvIngresado =
            valor.slice(-1);

        let suma = 0;
        let multiplicador = 2;

        for (
            let i = cuerpo.length - 1;
            i >= 0;
            i--
        ) {

            suma +=
                Number(cuerpo[i]) *
                multiplicador;

            multiplicador++;

            if (multiplicador > 7) {
                multiplicador = 2;
            }
        }

        const resto =
            suma % 11;

        let dvCalculado;

        if (resto === 0) {

            dvCalculado = "0";

        } else if (resto === 1) {

            dvCalculado = "K";

        } else {

            dvCalculado =
                String(11 - resto);
        }

        return dvCalculado === dvIngresado;
    }


    function validarRutExtranjero(
        valor
    ) {

        return /^[A-Za-z0-9.\-]{4,20}$/.test(
            String(valor || "").trim()
        );
    }


    function validarPasaporte(
        valor
    ) {

        return /^[A-Za-z0-9.\-]{4,20}$/.test(
            String(valor || "").trim()
        );
    }


    function obtenerTipoIdentificacion() {

        const seleccionado =
            document.querySelector(
                'input[name="tipo_identificacion"]:checked'
            );

        return seleccionado
            ? seleccionado.value
            : "rut";
    }


    function validarIdentificacion() {

        if (!createRut) {
            return false;
        }

        const tipo =
            obtenerTipoIdentificacion();

        const valor =
            createRut.value.trim();

        if (tipo === "rut") {

            return validarRutChileno(
                valor
            );
        }

        if (tipo === "rut_extranjero") {

            return validarRutExtranjero(
                valor
            );
        }

        if (tipo === "pasaporte") {

            return validarPasaporte(
                valor
            );
        }

        return false;
    }


    /*
     * =========================================================
     * VALIDACIÓN DE DATOS PERSONALES
     * =========================================================
     */

    function validarNombre() {

        if (!createNombre) {
            return false;
        }

        const valor =
            createNombre.value.trim();

        return (
            valor.length >= 2 &&
            valor.length <= 100 &&
            /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s.'-]+$/.test(
                valor
            )
        );
    }


    function validarTelefono() {

        if (!createTelefono) {
            return false;
        }

        const valor =
            createTelefono.value.trim();

        return (
            valor.length >= 7 &&
            valor.length <= 20 &&
            /^[0-9+\s().\-]+$/.test(
                valor
            )
        );
    }


    function validarCorreo() {

        if (!createCorreo) {
            return true;
        }

        const valor =
            createCorreo.value.trim();

        if (!valor) {
            return true;
        }

        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
            valor
        );
    }


    function datosPersonalesValidos() {

        return (
            validarNombre() &&
            validarIdentificacion() &&
            validarTelefono() &&
            validarCorreo()
        );
    }


    /*
     * =========================================================
     * MENSAJES DE ERROR
     * =========================================================
     */

    function mostrarError(
        elemento,
        mensaje
    ) {

        if (!elemento) {
            return;
        }

        elemento.textContent =
            mensaje;

        elemento.hidden =
            false;

        elemento.style.display =
            "block";
    }


    function ocultarError(
        elemento
    ) {

        if (!elemento) {
            return;
        }

        elemento.textContent =
            "";

        elemento.hidden =
            true;

        elemento.style.display =
            "none";
    }


    function validarCamposVisualmente() {

        const nombreValido =
            validarNombre();

        const identificacionValida =
            validarIdentificacion();

        const telefonoValido =
            validarTelefono();

        const correoValido =
            validarCorreo();


        if (!nombreValido) {

            mostrarError(
                errorNombre,
                "Ingrese un nombre válido."
            );

        } else {

            ocultarError(errorNombre);
        }


        if (!identificacionValida) {

            mostrarError(
                errorRut,
                "Ingrese una identificación válida."
            );

        } else {

            ocultarError(errorRut);
        }


        if (!telefonoValido) {

            mostrarError(
                errorTelefono,
                "Ingrese un teléfono válido."
            );

        } else {

            ocultarError(errorTelefono);
        }


        if (!correoValido) {

            mostrarError(
                errorCorreo,
                "Ingrese un correo electrónico válido."
            );

        } else {

            ocultarError(errorCorreo);
        }


        return (
            nombreValido &&
            identificacionValida &&
            telefonoValido &&
            correoValido
        );
    }


    /*
     * =========================================================
     * DEPARTAMENTO
     * =========================================================
     */

    function obtenerNombreDepartamentoSeleccionado() {

        if (!createDepartamento) {
            return "";
        }

        const option =
            createDepartamento.options[
                createDepartamento.selectedIndex
            ];

        if (!option) {
            return "";
        }

        return (
            option.dataset.nombre ||
            option.textContent ||
            ""
        ).trim();
    }


    /*
     * =========================================================
     * SERVICIO
     * =========================================================
     */

    function obtenerIdServicioSeleccionado() {

        if (!createServicio) {
            return "";
        }

        const valor =
            createServicio.value;

        if (!valor) {
            return "";
        }

        const option =
            createServicio.options[
                createServicio.selectedIndex
            ];

        if (!option) {
            return valor;
        }

        return (
            option.dataset.idServicio ||
            option.dataset.id ||
            option.value ||
            valor
        );
    }


    function obtenerNombreServicioSeleccionado() {

        if (!createServicio) {
            return "";
        }

        const option =
            createServicio.options[
                createServicio.selectedIndex
            ];

        if (!option) {
            return "";
        }

        return (
            option.dataset.nombre ||
            option.textContent ||
            ""
        ).trim();
    }


    function obtenerDepartamentoDeOpcionServicio(
        opcion
    ) {

        if (!opcion) {
            return "";
        }

        return (
            opcion.dataset.departamentoId ||
            opcion.dataset.idDepartamento ||
            opcion.dataset.departamento ||
            ""
        );
    }


    /*
     * =========================================================
     * CARGAR DEPARTAMENTOS DESDE API
     * =========================================================
     */

    async function cargarDepartamentosCrear() {

        if (!createDepartamento) {
            return;
        }

        createDepartamento.disabled =
            true;

        createDepartamento.innerHTML = `
            <option value="">
                Cargando departamentos...
            </option>
        `;

        try {

            const respuesta =
                await fetch(
                    API_DEPARTAMENTOS_URL,
                    {
                        method: "GET",
                        cache: "no-store"
                    }
                );

            const datos =
                await respuesta.json();

            if (
                !respuesta.ok ||
                datos.ok === false
            ) {

                throw new Error(
                    datos.mensaje ||
                    "No se pudieron cargar los departamentos."
                );
            }

            departamentosFormulario =
                Array.isArray(datos.departamentos)
                    ? datos.departamentos
                    : [];


            createDepartamento.innerHTML = `
                <option value="">
                    Seleccione un departamento
                </option>
            `;


            departamentosFormulario.forEach(
                function (departamento) {

                    const option =
                        document.createElement("option");

                    option.value =
                        departamento.id_departamento;

                    option.textContent =
                        departamento.nombre_departamento ||
                        departamento.nombre ||
                        "Departamento";

                    option.dataset.nombre =
                        departamento.nombre_departamento ||
                        departamento.nombre ||
                        "";

                    option.dataset.idDepartamento =
                        departamento.id_departamento;


                    const servicios =
                        Array.isArray(departamento.servicios)
                            ? departamento.servicios
                            : [];


                    const departamentoActivo =
                        departamento.departamento_activo !== false &&
                        departamento.departamento_activo !== "false";


                    option.disabled =
                        !departamentoActivo ||
                        servicios.length === 0;


                    createDepartamento.appendChild(
                        option
                    );
                }
            );


            createDepartamento.disabled =
                false;

        } catch (error) {

            console.error(
                "Error cargando departamentos:",
                error
            );

            createDepartamento.innerHTML = `
                <option value="">
                    Error al cargar departamentos
                </option>
            `;

            createDepartamento.disabled =
                true;

            showNotification(
                error.message,
                "error"
            );
        }
    }


    /*
     * =========================================================
     * CARGAR SERVICIOS SEGÚN DEPARTAMENTO
     * =========================================================
     */

    function cargarServiciosPorDepartamento(
        idDepartamento,
        idServicioSeleccionado = ""
    ) {

        if (!createServicio) {
            return;
        }

        createServicio.innerHTML = `
            <option value="">
                Seleccione un servicio
            </option>
        `;

        createServicio.disabled =
            true;


        if (!idDepartamento) {

            createServicio.innerHTML = `
                <option value="">
                    Seleccione primero un departamento
                </option>
            `;

            return;
        }


        const departamento =
            departamentosFormulario.find(
                function (item) {

                    return String(
                        item.id_departamento
                    ) === String(
                        idDepartamento
                    );
                }
            );


        if (!departamento) {

            createServicio.innerHTML = `
                <option value="">
                    No se encontró el departamento
                </option>
            `;

            return;
        }


        const servicios =
            Array.isArray(departamento.servicios)
                ? departamento.servicios
                : [];


        if (servicios.length === 0) {

            createServicio.innerHTML = `
                <option value="">
                    No hay servicios disponibles
                </option>
            `;

            return;
        }


        servicios.forEach(
            function (servicio) {

                const option =
                    document.createElement("option");

                option.value =
                    servicio.id_servicio;

                option.textContent =
                    servicio.nombre_servicio ||
                    servicio.nombre ||
                    "Servicio";

                option.dataset.idServicio =
                    servicio.id_servicio;

                option.dataset.nombre =
                    servicio.nombre_servicio ||
                    servicio.nombre ||
                    "";

                option.dataset.departamentoId =
                    idDepartamento;

                createServicio.appendChild(
                    option
                );
            }
        );


        createServicio.disabled =
            false;


        if (idServicioSeleccionado) {

            const existe =
                Array.from(
                    createServicio.options
                ).some(
                    function (option) {

                        return String(
                            option.value
                        ) === String(
                            idServicioSeleccionado
                        );
                    }
                );


            if (existe) {

                createServicio.value =
                    String(idServicioSeleccionado);
            }
        }
    }


    /*
     * =========================================================
     * ESTADO DEL FORMULARIO
     * =========================================================
     */

    function actualizarEstadoFormulario() {

        const personalesValidos =
            datosPersonalesValidos();


        if (createDepartamento) {

            createDepartamento.disabled =
                !personalesValidos;
        }


        if (!personalesValidos) {

            if (createDepartamento) {

                createDepartamento.value = "";
            }


            if (createServicio) {

                createServicio.innerHTML = `
                    <option value="">
                        Complete sus datos primero
                    </option>
                `;

                createServicio.value = "";
                createServicio.disabled = true;
            }


            if (createFecha) {

                createFecha.value = "";
                createFecha.disabled = true;
            }


            if (createHorario) {

                createHorario.innerHTML = `
                    <option value="">
                        Seleccione primero una fecha
                    </option>
                `;

                createHorario.value = "";
                createHorario.disabled = true;
            }


            actualizarPreview();

            return;
        }


        const departamentoSeleccionado =
            createDepartamento &&
            createDepartamento.value;


        if (!departamentoSeleccionado) {

            if (createServicio) {

                createServicio.innerHTML = `
                    <option value="">
                        Seleccione primero un departamento
                    </option>
                `;

                createServicio.value = "";
                createServicio.disabled = true;
            }


            if (createFecha) {

                createFecha.value = "";
                createFecha.disabled = true;
            }


            if (createHorario) {

                createHorario.value = "";
                createHorario.disabled = true;
            }


            actualizarPreview();

            return;
        }


        if (createServicio) {

            createServicio.disabled = false;
        }


        const servicioSeleccionado =
            obtenerIdServicioSeleccionado();


        if (!servicioSeleccionado) {

            if (createFecha) {

                createFecha.value = "";
                createFecha.disabled = true;
            }


            if (createHorario) {

                createHorario.value = "";
                createHorario.disabled = true;
            }


            actualizarPreview();

            return;
        }


        if (createFecha) {

            createFecha.disabled = false;
        }


        const fechaSeleccionada =
            createFecha
                ? createFecha.value
                : "";


        if (!fechaSeleccionada) {

            if (createHorario) {

                createHorario.value = "";
                createHorario.disabled = true;

                createHorario.innerHTML = `
                    <option value="">
                        Seleccione primero una fecha
                    </option>
                `;
            }


            actualizarPreview();

            return;
        }


        if (createHorario) {

            createHorario.disabled = false;
        }


        actualizarPreview();
    }


    /*
     * =========================================================
     * PREVISUALIZACIÓN
     * =========================================================
     */

    function actualizarPreview() {

        if (previewNombre) {

            previewNombre.textContent =
                createNombre
                    ? createNombre.value.trim() || "-"
                    : "-";
        }


        if (previewIdentificacion) {

            const tipo =
                obtenerTipoIdentificacion();

            const valor =
                createRut
                    ? createRut.value.trim()
                    : "";


            if (valor) {

                previewIdentificacion.textContent =
                    `${formatearTipoIdentificacion(tipo)}: ${valor}`;

            } else {

                previewIdentificacion.textContent = "-";
            }
        }


        if (previewDepartamento) {

            previewDepartamento.textContent =
                obtenerNombreDepartamentoSeleccionado() || "-";
        }


        if (previewServicio) {

            previewServicio.textContent =
                obtenerNombreServicioSeleccionado() || "-";
        }


        if (previewFecha) {

            previewFecha.textContent =
                createFecha &&
                createFecha.value
                    ? formatearFechaReserva(
                        createFecha.value
                    )
                    : "-";
        }


        if (previewHora) {

            previewHora.textContent =
                createHorario &&
                createHorario.value
                    ? createHorario.value
                    : "-";
        }
    }


    /*
     * =========================================================
     * CARGAR HORARIOS PARA CREAR RESERVA
     * =========================================================
     */

    async function cargarHorariosCrear(
        horaSeleccionada = ""
    ) {

        if (
            !createServicio ||
            !createFecha ||
            !createHorario
        ) {
            return;
        }


        const servicio =
            obtenerIdServicioSeleccionado();

        const fecha =
            createFecha.value;


        if (!servicio || !fecha) {

            createHorario.innerHTML = `
                <option value="">
                    Seleccione primero una fecha
                </option>
            `;

            createHorario.disabled = true;

            return;
        }


        createHorario.disabled = true;

        createHorario.innerHTML = `
            <option value="">
                Cargando horarios...
            </option>
        `;


        if (createHorarioEstado) {

            createHorarioEstado.textContent =
                "Cargando horarios...";

            createHorarioEstado.className = "";
        }


        try {

            const url =
                `${API_HORARIOS_URL}?servicio=${encodeURIComponent(servicio)}&fecha=${encodeURIComponent(fecha)}`;


            const respuesta =
                await fetch(
                    url,
                    {
                        method: "GET",
                        cache: "no-store"
                    }
                );


            const datos =
                await respuesta.json();


            if (
                !respuesta.ok ||
                datos.ok === false
            ) {

                throw new Error(
                    datos.mensaje ||
                    "No se pudieron cargar los horarios."
                );
            }


            const horarios =
                Array.isArray(datos.horarios)
                    ? datos.horarios
                    : [];


            createHorario.innerHTML = `
                <option value="">
                    Seleccione una hora
                </option>
            `;


            let disponibles = 0;


            const fechaHoy =
                obtenerFechaLocal();

            const horaHoy =
                obtenerHoraLocal();


            horarios.forEach(
                function (horario) {

                    const hora =
                        horario.hora ||
                        horario.hora_inicio ||
                        horario.hora_horario;


                    if (!hora) {
                        return;
                    }


                    let disponible =
                        horario.disponible === true ||
                        horario.disponible === "true" ||
                        horario.estado === "disponible";


                    if (
                        fecha === fechaHoy &&
                        String(hora).substring(0, 5) <= horaHoy
                    ) {

                        disponible = false;
                    }


                    const option =
                        document.createElement("option");


                    option.value =
                        hora;


                    if (disponible) {

                        option.textContent = hora;

                        disponibles++;

                    } else {

                        option.textContent =
                            `${hora} - No disponible`;

                        option.disabled = true;
                    }


                    createHorario.appendChild(option);
                }
            );


            if (horaSeleccionada) {

                const optionEncontrada =
                    Array.from(
                        createHorario.options
                    ).find(
                        function (option) {

                            return (
                                option.value === horaSeleccionada &&
                                !option.disabled
                            );
                        }
                    );


                if (optionEncontrada) {

                    createHorario.value =
                        horaSeleccionada;
                }
            }


            createHorario.disabled =
                disponibles === 0;


            if (createHorarioEstado) {

                if (disponibles === 0) {

                    createHorarioEstado.textContent =
                        "No hay horarios disponibles para esta fecha.";

                    createHorarioEstado.className = "error";

                } else {

                    createHorarioEstado.textContent =
                        `${disponibles} horario(s) disponible(s).`;

                    createHorarioEstado.className = "success";
                }
            }

        } catch (error) {

            console.error(
                "Error cargando horarios:",
                error
            );


            createHorario.innerHTML = `
                <option value="">
                    Error al cargar horarios
                </option>
            `;


            createHorario.disabled = true;


            if (createHorarioEstado) {

                createHorarioEstado.textContent =
                    error.message;

                createHorarioEstado.className = "error";
            }


            showNotification(
                error.message,
                "error"
            );
        }


        actualizarPreview();
    }


    /*
     * =========================================================
     * ABRIR FORMULARIO CREAR
     * =========================================================
     */

    async function abrirFormularioCrear() {

        if (!createReservationSection) {
            return;
        }


        createReservationSection.hidden = false;

        createReservationSection.style.display = "flex";

        createReservationSection.classList.add("active");

        document.body.classList.add("modal-open");


        if (createNombre) {
            createNombre.value = "";
        }


        if (createRut) {
            createRut.value = "";
        }


        if (createTelefono) {
            createTelefono.value = "";
        }


        if (createCorreo) {
            createCorreo.value = "";
        }


        if (createFecha) {
            createFecha.value = "";
        }


        if (createHorario) {

            createHorario.innerHTML = `
                <option value="">
                    Seleccione primero una fecha
                </option>
            `;

            createHorario.value = "";
        }


        if (createObservacion) {
            createObservacion.value = "";
        }


        document
            .querySelectorAll(
                'input[name="tipo_identificacion"]'
            )
            .forEach(
                function (radio) {

                    radio.checked =
                        radio.value === "rut";
                }
            );


        await cargarDepartamentosCrear();


        if (servicioURL) {

            let departamentoEncontrado = null;


            for (
                const departamento of departamentosFormulario
            ) {

                const servicios =
                    Array.isArray(departamento.servicios)
                        ? departamento.servicios
                        : [];


                const servicioEncontrado =
                    servicios.find(
                        function (servicio) {

                            return String(
                                servicio.id_servicio
                            ) === String(
                                servicioURL
                            );
                        }
                    );


                if (servicioEncontrado) {

                    departamentoEncontrado =
                        departamento;

                    break;
                }
            }


            if (departamentoEncontrado) {

                createDepartamento.value =
                    departamentoEncontrado.id_departamento;


                cargarServiciosPorDepartamento(
                    departamentoEncontrado.id_departamento,
                    servicioURL
                );

            } else {

                console.warn(
                    "No se encontró el servicio:",
                    servicioURL
                );
            }
        }


        if (fechaURL && createFecha) {

            createFecha.value =
                fechaURL;
        }


        actualizarEstadoFormulario();


        if (servicioURL && fechaURL) {

            await cargarHorariosCrear(
                horaURL || ""
            );
        }


        actualizarPreview();
    }


    /*
     * =========================================================
     * CERRAR FORMULARIO CREAR
     * =========================================================
     */

    function cerrarFormularioCrear() {

        if (!createReservationSection) {
            return;
        }

        createReservationSection.classList.remove("active");

        createReservationSection.hidden = true;

        createReservationSection.style.display = "none";

        document.body.classList.remove("modal-open");
    }


    /*
     * =========================================================
     * BOTÓN ABRIR CREAR RESERVA
     * =========================================================
     */

    if (btnCreateReservation) {

        btnCreateReservation.addEventListener(
            "click",
            function (event) {

                event.preventDefault();
                event.stopPropagation();

                abrirFormularioCrear();
            }
        );
    }


    document.addEventListener(
        "click",
        function (event) {

            const boton =
                event.target.closest(
                    "#btnCreateReservation"
                );


            if (!boton) {
                return;
            }


            event.preventDefault();
            event.stopPropagation();

            abrirFormularioCrear();

        },
        true
    );


    /*
     * =========================================================
     * CERRAR FORMULARIO
     * =========================================================
     */

    if (btnCloseCreateModal) {

        btnCloseCreateModal.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                cerrarFormularioCrear();
            }
        );
    }


    if (btnCancelCreate) {

        btnCancelCreate.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                cerrarFormularioCrear();
            }
        );
    }


    /*
     * Cerrar haciendo clic fuera.
     */

    if (createReservationSection) {

        createReservationSection.addEventListener(
            "click",
            function (event) {

                if (
                    event.target === createReservationSection
                ) {

                    cerrarFormularioCrear();
                }
            }
        );
    }


    /*
     * =========================================================
     * DEPARTAMENTO CAMBIA
     * =========================================================
     */

    if (createDepartamento) {

        createDepartamento.addEventListener(
            "change",
            function () {

                const idDepartamento =
                    createDepartamento.value;


                cargarServiciosPorDepartamento(
                    idDepartamento
                );


                if (createFecha) {

                    createFecha.value = "";
                    createFecha.disabled = true;
                }


                if (createHorario) {

                    createHorario.value = "";

                    createHorario.innerHTML = `
                        <option value="">
                            Seleccione primero una fecha
                        </option>
                    `;

                    createHorario.disabled = true;
                }


                actualizarEstadoFormulario();
                actualizarPreview();
            }
        );
    }


    /*
     * =========================================================
     * SERVICIO CAMBIA
     * =========================================================
     */

    if (createServicio) {

        createServicio.addEventListener(
            "change",
            function () {

                if (createFecha) {

                    createFecha.value = "";

                    createFecha.disabled =
                        !obtenerIdServicioSeleccionado();
                }


                if (createHorario) {

                    createHorario.value = "";

                    createHorario.innerHTML = `
                        <option value="">
                            Seleccione primero una fecha
                        </option>
                    `;

                    createHorario.disabled = true;
                }


                actualizarEstadoFormulario();
                actualizarPreview();
            }
        );
    }


    /*
     * =========================================================
     * FECHA CAMBIA
     * =========================================================
     */

    if (createFecha) {

        createFecha.addEventListener(
            "change",
            async function () {

                const fecha =
                    createFecha.value;


                if (!fecha) {

                    if (createHorario) {

                        createHorario.innerHTML = `
                            <option value="">
                                Seleccione primero una fecha
                            </option>
                        `;

                        createHorario.value = "";
                        createHorario.disabled = true;
                    }


                    actualizarPreview();

                    return;
                }


                await cargarHorariosCrear();

                actualizarEstadoFormulario();
                actualizarPreview();
            }
        );
    }


    /*
     * =========================================================
     * HORA CAMBIA
     * =========================================================
     */

    if (createHorario) {

        createHorario.addEventListener(
            "change",
            function () {

                actualizarPreview();
            }
        );
    }


    /*
     * =========================================================
     * DATOS PERSONALES
     * =========================================================
     */

    if (createNombre) {

        createNombre.addEventListener(
            "input",
            function () {

                validarNombre();

                actualizarEstadoFormulario();
                actualizarPreview();
            }
        );
    }


    if (createRut) {

        createRut.addEventListener(
            "input",
            function () {

                const tipo =
                    obtenerTipoIdentificacion();


                if (tipo === "rut") {

                    createRut.value =
                        formatearRutChileno(
                            createRut.value
                        );
                }


                validarIdentificacion();

                actualizarEstadoFormulario();
                actualizarPreview();
            }
        );
    }


    if (createTelefono) {

        createTelefono.addEventListener(
            "input",
            function () {

                validarTelefono();

                actualizarEstadoFormulario();
                actualizarPreview();
            }
        );
    }


    if (createCorreo) {

        createCorreo.addEventListener(
            "input",
            function () {

                validarCorreo();

                actualizarEstadoFormulario();
                actualizarPreview();
            }
        );
    }


    /*
     * =========================================================
     * CAMBIO TIPO IDENTIFICACIÓN
     * =========================================================
     */

    document
        .querySelectorAll(
            'input[name="tipo_identificacion"]'
        )
        .forEach(
            function (radio) {

                radio.addEventListener(
                    "change",
                    function () {

                        if (
                            createRut &&
                            this.value === "rut"
                        ) {

                            createRut.value =
                                formatearRutChileno(
                                    createRut.value
                                );
                        }


                        validarIdentificacion();

                        actualizarEstadoFormulario();
                        actualizarPreview();
                    }
                );
            }
        );


    /*
     * =========================================================
     * FORMULARIO CREAR RESERVA
     * =========================================================
     */

    if (createReservationForm) {

        createReservationForm.noValidate = true;


        const botonSubmit =
            createReservationForm.querySelector(
                'button[type="submit"]'
            );


        if (botonSubmit) {

            botonSubmit.type = "submit";
        }


        const botonesFormulario =
            Array.from(
                createReservationForm.querySelectorAll("button")
            );


        botonesFormulario.forEach(
            function (boton) {

                const texto =
                    boton.textContent
                        .trim()
                        .toLowerCase();


                const pareceCrear =
                    texto.includes("crear reserva") ||
                    texto === "crear" ||
                    boton.id === "btnCrearReserva";


                if (
                    pareceCrear &&
                    !boton.hasAttribute("type")
                ) {

                    boton.type = "submit";
                }
            }
        );


        /*
         * =====================================================
         * SUBMIT CREAR RESERVA
         * =====================================================
         */

        createReservationForm.addEventListener(
            "submit",
            async function (event) {

                event.preventDefault();
                event.stopPropagation();


                console.log(
                    "Formulario crear reserva enviado."
                );


                const personalesValidos =
                    validarCamposVisualmente();


                if (!personalesValidos) {

                    showNotification(
                        "Complete correctamente sus datos personales.",
                        "error"
                    );

                    return;
                }


                const departamento =
                    createDepartamento
                        ? createDepartamento.value
                        : "";


                if (!departamento) {

                    showNotification(
                        "Seleccione un departamento.",
                        "error"
                    );

                    if (createDepartamento) {
                        createDepartamento.focus();
                    }

                    return;
                }


                const servicio =
                    obtenerIdServicioSeleccionado();


                if (!servicio) {

                    showNotification(
                        "Seleccione un servicio.",
                        "error"
                    );

                    if (createServicio) {
                        createServicio.focus();
                    }

                    return;
                }


                const fecha =
                    createFecha
                        ? createFecha.value
                        : "";


                if (!fecha) {

                    showNotification(
                        "Seleccione una fecha.",
                        "error"
                    );

                    if (createFecha) {
                        createFecha.focus();
                    }

                    return;
                }


                const hora =
                    createHorario
                        ? createHorario.value
                        : "";


                if (!hora) {

                    showNotification(
                        "Seleccione una hora disponible.",
                        "error"
                    );

                    if (createHorario) {
                        createHorario.focus();
                    }

                    return;
                }


                const opcionHora =
                    createHorario
                        ? createHorario.options[
                            createHorario.selectedIndex
                        ]
                        : null;


                if (
                    opcionHora &&
                    opcionHora.disabled
                ) {

                    showNotification(
                        "La hora seleccionada no está disponible.",
                        "error"
                    );

                    return;
                }


                const formData =
                    new FormData();


                formData.append(
                    "nombre",
                    createNombre
                        ? createNombre.value.trim()
                        : ""
                );


                formData.append(
                    "rut",
                    createRut
                        ? createRut.value.trim()
                        : ""
                );


                formData.append(
                    "tipo_identificacion",
                    obtenerTipoIdentificacion()
                );


                formData.append(
                    "telefono",
                    createTelefono
                        ? createTelefono.value.trim()
                        : ""
                );


                formData.append(
                    "correo",
                    createCorreo
                        ? createCorreo.value.trim()
                        : ""
                );


                formData.append(
                    "departamento",
                    departamento
                );


                formData.append(
                    "servicio",
                    servicio
                );


                formData.append(
                    "fecha",
                    fecha
                );


                formData.append(
                    "hora",
                    hora
                );


                formData.append(
                    "observacion",
                    createObservacion
                        ? createObservacion.value.trim()
                        : ""
                );


                formData.append(
                    "modo",
                    "funcionario"
                );


                let botonCrear =
                    createReservationForm.querySelector(
                        'button[type="submit"]'
                    );


                if (!botonCrear) {

                    botonCrear =
                        Array.from(
                            createReservationForm.querySelectorAll(
                                "button"
                            )
                        ).find(
                            function (boton) {

                                const texto =
                                    boton.textContent
                                        .trim()
                                        .toLowerCase();


                                return (
                                    texto.includes("crear reserva") ||
                                    texto === "crear" ||
                                    boton.id === "btnCrearReserva"
                                );
                            }
                        );
                }


                const textoOriginal =
                    botonCrear
                        ? botonCrear.textContent
                        : "";


                if (botonCrear) {

                    botonCrear.disabled = true;

                    botonCrear.textContent =
                        "Creando reserva...";
                }


                try {

                    const respuesta =
                        await fetch(
                            API_CREAR_URL,
                            {
                                method: "POST",
                                body: formData
                            }
                        );


                    const texto =
                        await respuesta.text();


                    let resultado;


                    try {

                        resultado =
                            JSON.parse(texto);

                    } catch (error) {

                        console.error(
                            "Respuesta de crear_reserva.php:",
                            texto
                        );

                        throw new Error(
                            "El servidor devolvió una respuesta no válida."
                        );
                    }


                    if (
                        !respuesta.ok ||
                        resultado.ok === false
                    ) {

                        throw new Error(
                            resultado.mensaje ||
                            resultado.message ||
                            "No se pudo crear la reserva."
                        );
                    }


                    showNotification(
                        resultado.mensaje ||
                        "Reserva creada correctamente.",
                        "success"
                    );


                    cerrarFormularioCrear();


                    setTimeout(
                        function () {

                            window.location.reload();

                        },
                        1000
                    );

                } catch (error) {

                    console.error(
                        "Error creando reserva:",
                        error
                    );

                    showNotification(
                        error.message ||
                        "No se pudo crear la reserva.",
                        "error"
                    );

                } finally {

                    if (botonCrear) {

                        botonCrear.disabled = false;

                        botonCrear.textContent =
                            textoOriginal ||
                            "Crear reserva";
                    }
                }
            }
        );
    }


    /*
     * =========================================================
     * BOTÓN VER RESERVA
     *
     * Se utiliza delegación de eventos para funcionar con
     * todas las filas de la tabla.
     * =========================================================
     */

    document.addEventListener(
        "click",
        function (event) {

            const boton =
                event.target instanceof Element
                    ? event.target.closest(
                        ".action-button.view"
                    )
                    : null;


            if (!boton) {
                return;
            }


            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();


            abrirPopupVerReserva(
                boton
            );

        },
        true
    );


    /*
     * =========================================================
     * BOTÓN MODIFICAR / REAGENDAR
     * =========================================================
     */

    document.addEventListener(
        "click",
        function (event) {

            let boton = null;


            if (
                event.target instanceof Element
            ) {

                boton =
                    event.target.closest(
                        ".action-button.reschedule"
                    );


                if (!boton) {

                    boton =
                        event.target.closest(
                            "[data-action='modificar']"
                        );
                }
            }


            if (
                !boton &&
                event.target &&
                event.target.parentElement
            ) {

                boton =
                    event.target.parentElement.closest(
                        ".action-button.reschedule"
                    );


                if (!boton) {

                    boton =
                        event.target.parentElement.closest(
                            "[data-action='modificar']"
                        );
                }
            }


            if (!boton) {
                return;
            }


            console.log(
                "=========================================="
            );

            console.log(
                "CLICK DETECTADO EN BOTÓN MODIFICAR"
            );

            console.log(
                "Botón:",
                boton
            );


            console.log(
                "Atributos del botón:",
                {
                    idReserva:
                        boton.getAttribute("data-id-reserva"),

                    idServicio:
                        boton.getAttribute("data-id-servicio"),

                    nombre:
                        boton.getAttribute("data-nombre"),

                    rut:
                        boton.getAttribute("data-rut"),

                    telefono:
                        boton.getAttribute("data-telefono"),

                    correo:
                        boton.getAttribute("data-correo"),

                    servicio:
                        boton.getAttribute("data-servicio-nombre"),

                    fecha:
                        boton.getAttribute("data-fecha"),

                    hora:
                        boton.getAttribute("data-hora")
                }
            );


            event.preventDefault();
            event.stopPropagation();


            abrirPopupModificarReserva(
                boton
            );

        },
        true
    );


    /*
     * =========================================================
     * CERRAR POPUP VER
     *
     * Admite tanto el modal generado por PHP como el popup
     * de respaldo, por si alguna vez no existe el HTML.
     * =========================================================
     */

    document.addEventListener(
        "click",
        function (event) {

            const objetivo =
                event.target instanceof Element
                    ? event.target
                    : null;


            if (!objetivo) {
                return;
            }


            const botonCerrar =
                objetivo.closest(
                    [
                        "[data-close-view-reservation]",
                        "#btnCloseReservationView",
                        "#btnCloseReservationViewFooter",
                        "#btnCloseReservationViewJS",
                        "#btnCloseReservationViewFooterJS"
                    ].join(",")
                );


            if (botonCerrar) {

                event.preventDefault();
                event.stopPropagation();

                cerrarPopupVerReserva();

                return;
            }


            /*
             * Cerrar haciendo clic en la zona exterior.
             */
            if (
                reservationViewModal &&
                objetivo === reservationViewModal
            ) {

                cerrarPopupVerReserva();

                return;
            }


            /*
             * El modal original de PHP puede tener un contenedor
             * de contenido interno. Cerrar si se hace clic en
             * la zona exterior de ese modal.
             */
            if (
                reservationViewModal &&
                objetivo.classList.contains(
                    "reservation-view-modal"
                )
            ) {

                cerrarPopupVerReserva();
            }
        }
    );


    /*
     * =========================================================
     * CERRAR POPUP MODIFICAR
     * =========================================================
     */

    document.addEventListener(
        "click",
        function (event) {

            const objetivo =
                event.target instanceof Element
                    ? event.target
                    : null;


            if (!objetivo) {
                return;
            }


            if (
                objetivo.closest(
                    "[data-close-modify-reservation]"
                )
            ) {

                event.preventDefault();

                cerrarPopupModificarReserva();

                return;
            }


            if (
                objetivo.closest(
                    "#btnCancelReservationModify"
                )
            ) {

                event.preventDefault();

                cerrarPopupModificarReserva();

                return;
            }


            if (
                reservationModifyModal &&
                objetivo === reservationModifyModal
            ) {

                cerrarPopupModificarReserva();
            }
        }
    );


    /*
     * =========================================================
     * CAMBIO DE FECHA PARA MODIFICAR
     * =========================================================
     */

    document.addEventListener(
        "change",
        function (event) {

            if (
                event.target &&
                event.target.id === "modifyReservaFecha"
            ) {

                console.log(
                    "Nueva fecha seleccionada:",
                    event.target.value
                );

                cargarHorariosModificar();
            }
        }
    );


    /*
     * =========================================================
     * GUARDAR MODIFICACIÓN / REAGENDAMIENTO
     * =========================================================
     */

    document.addEventListener(
        "click",
        async function (event) {

            const objetivo =
                event.target instanceof Element
                    ? event.target
                    : null;


            const boton =
                objetivo
                    ? objetivo.closest(
                        "#btnSaveReservationModify"
                    )
                    : null;


            if (!boton) {
                return;
            }


            event.preventDefault();
            event.stopPropagation();


            if (!reservaModificarActual) {

                showNotification(
                    "No se encontró la reserva.",
                    "error"
                );

                return;
            }


            const fechaElement =
                document.getElementById(
                    "modifyReservaFecha"
                );


            const horaElement =
                document.getElementById(
                    "modifyReservaHora"
                );


            if (
                !fechaElement ||
                !horaElement
            ) {

                showNotification(
                    "No se pudo cargar el formulario de modificación.",
                    "error"
                );

                return;
            }


            const nuevaFecha =
                fechaElement.value;


            /*
             * La API trabaja con hora, no con id_horario.
             */

            const nuevaHora =
                horaElement.value;


            if (!nuevaFecha) {

                showNotification(
                    "Seleccione una nueva fecha.",
                    "error"
                );

                return;
            }


            if (!nuevaHora) {

                showNotification(
                    "Seleccione una nueva hora.",
                    "error"
                );

                return;
            }


            const opcionHora =
                horaElement.selectedOptions[0];


            if (
                opcionHora &&
                opcionHora.disabled
            ) {

                showNotification(
                    "La hora seleccionada no está disponible.",
                    "error"
                );

                return;
            }


            if (
                !/^\d{2}:\d{2}$/.test(
                    String(nuevaHora)
                )
            ) {

                showNotification(
                    "La hora seleccionada no es válida.",
                    "error"
                );

                return;
            }


            boton.disabled = true;


            const textoOriginal =
                boton.textContent;


            boton.textContent =
                "Guardando...";


            try {

                /*
                 * api/gestionar_reserva.php espera:
                 *
                 * id_reserva
                 * fecha_nueva
                 * hora_nueva
                 *
                 * No espera id_nuevo_horario.
                 */

                const resultado =
                    await enviarAPI(
                        "reagendar",
                        {
                            id_reserva:
                                reservaModificarActual.idReserva,

                            fecha_nueva:
                                nuevaFecha,

                            hora_nueva:
                                nuevaHora
                        }
                    );


                showNotification(
                    resultado.mensaje ||
                    "Reserva modificada correctamente.",
                    "success"
                );


                cerrarPopupModificarReserva();


                setTimeout(
                    function () {

                        window.location.reload();

                    },
                    1000
                );

            } catch (error) {

                console.error(
                    "Error modificando reserva:",
                    error
                );

                showNotification(
                    error.message ||
                    "No se pudo modificar la reserva.",
                    "error"
                );

            } finally {

                boton.disabled = false;

                boton.textContent =
                    textoOriginal;
            }
        }
    );


    /*
     * =========================================================
     * ESC PARA CERRAR MODALES
     * =========================================================
     */

    document.addEventListener(
        "keydown",
        function (event) {

            if (event.key !== "Escape") {
                return;
            }


            if (
                reservationViewModal &&
                !reservationViewModal.hidden
            ) {

                cerrarPopupVerReserva();

                return;
            }


            if (
                reservationModifyModal &&
                !reservationModifyModal.hidden
            ) {

                cerrarPopupModificarReserva();

                return;
            }


            if (
                createReservationSection &&
                !createReservationSection.hidden
            ) {

                cerrarFormularioCrear();
            }
        }
    );


    /*
     * =========================================================
     * ABRIR CREAR DESDE AGENDA
     * =========================================================
     */

    function abrirCrearDesdeAgenda() {

        if (
            abrirCrearReserva === "1" ||
            abrirCrearReserva === "true"
        ) {

            abrirFormularioCrear();
        }
    }


    /*
     * =========================================================
     * INICIALIZACIÓN
     * =========================================================
     */

    /*
     * Importante: esta función reutiliza el popup del PHP
     * y ya no genera un segundo elemento con el mismo ID.
     */
    crearPopupVerReserva();

    crearPopupModificarReserva();


    if (createReservationSection) {

        createReservationSection.hidden = true;

        createReservationSection.style.display = "none";
    }


    if (createReservationForm) {

        createReservationForm.noValidate = true;
    }


    actualizarPreview();

    abrirCrearDesdeAgenda();

});