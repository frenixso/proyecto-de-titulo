
document.addEventListener("DOMContentLoaded", function () {

    "use strict";

    /* =========================================================
       1. DIRECCIONES DE API
    ========================================================= */

    const API_URL = "../api/gestionar_reserva.php";
    const API_CREAR_URL = "../api/crear_reserva.php";
    const API_DEPARTAMENTOS_URL = "../api/obtener_departamentos.php";
    const API_SOLICITUDES_URL = "../api/gestionar_solicitud_horas.php";


    /* =========================================================
       2. PARÁMETROS DE URL
    ========================================================= */

    function obtenerParametro(nombre) {

        return new URLSearchParams(
            window.location.search
        ).get(nombre);
    }

    const abrirCrearReserva = obtenerParametro("crear_reserva");
    const servicioURL = obtenerParametro("servicio");
    const fechaURL = obtenerParametro("fecha");
    const horaURL = obtenerParametro("hora");


    /* =========================================================
       3. VARIABLES GENERALES
    ========================================================= */

    let departamentosFormulario = [];

    let consultaHorariosCrear = 0;
    let consultaHorariosModificar = 0;

    let reservationViewModal = null;
    let reservationModifyModal = null;

    let reservaModificarActual = null;

    let enlaceAgendaPendiente = null;
    let enlaceAgendaProcesado = false;

    let temporizadorNotificacion = null;


    /* =========================================================
       4. FECHA Y HORA LOCAL
    ========================================================= */

    function obtenerFechaLocal() {

        const fecha = new Date();

        const año = fecha.getFullYear();

        const mes = String(
            fecha.getMonth() + 1
        ).padStart(2, "0");

        const dia = String(
            fecha.getDate()
        ).padStart(2, "0");

        return `${año}-${mes}-${dia}`;
    }


    function obtenerHoraLocal() {

        const fecha = new Date();

        const horas = String(
            fecha.getHours()
        ).padStart(2, "0");

        const minutos = String(
            fecha.getMinutes()
        ).padStart(2, "0");

        return `${horas}:${minutos}`;
    }


    function fechaMostrar(fecha) {

        if (!fecha) {
            return "No registrada";
        }

        const partes = String(fecha)
            .substring(0, 10)
            .split("-");

        if (
            partes.length !== 3 ||
            !/^\d{4}$/.test(partes[0])
        ) {
            return String(fecha);
        }

        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }


    function normalizarHora(hora) {

        if (!hora) {
            return "";
        }

        return String(hora).substring(0, 5);
    }


    /* =========================================================
       5. NOTIFICACIONES
    ========================================================= */

    function showNotification(mensaje, tipo = "success") {

        let notification = document.getElementById(
            "createReservationNotification"
        );

        let notificationMessage = document.getElementById(
            "createReservationNotificationMessage"
        );

        let notificationIcon = null;

        if (!notification || !notificationMessage) {

            notification = document.getElementById(
                "notification"
            );

            notificationMessage = document.getElementById(
                "notificationText"
            );

            notificationIcon = document.getElementById(
                "notificationIcon"
            );
        }

        if (!notification || !notificationMessage) {
            alert(mensaje);
            return;
        }

        notificationMessage.textContent = mensaje;

        notification.classList.remove(
            "success",
            "error",
            "warning",
            "info"
        );

        notification.classList.add(tipo);

        if (notificationIcon) {

            const iconos = {
                success: "✓",
                error: "✕",
                warning: "⚠",
                info: "ℹ"
            };

            notificationIcon.textContent =
                iconos[tipo] || "ℹ";
        }

        notification.hidden = false;
        notification.style.display = "flex";

        if (temporizadorNotificacion) {
            clearTimeout(temporizadorNotificacion);
        }

        temporizadorNotificacion = setTimeout(function () {

            notification.hidden = true;
            notification.style.display = "none";

        }, 5000);
    }


    /* =========================================================
       6. FUNCIONES GENERALES DE API
    ========================================================= */

    async function leerRespuestaJSON(respuesta) {

        const texto = await respuesta.text();

        let resultado;

        try {

            resultado = JSON.parse(texto);

        } catch (error) {

            console.error(
                "Respuesta no válida de la API:",
                texto
            );

            throw new Error(
                "El servidor devolvió una respuesta no válida. Revisa los registros de PHP."
            );
        }

        return resultado;
    }


    async function enviarAPI(accion, datos = {}) {

        const formData = new FormData();

        formData.append("accion", accion);

        Object.entries(datos).forEach(function ([clave, valor]) {

            if (
                valor !== undefined &&
                valor !== null
            ) {

                formData.append(
                    clave,
                    String(valor)
                );
            }
        });

        const respuesta = await fetch(API_URL, {

            method: "POST",

            body: formData,

            cache: "no-store"
        });

        const resultado = await leerRespuestaJSON(
            respuesta
        );

        if (
            !respuesta.ok ||
            resultado.ok === false ||
            resultado.success === false
        ) {

            throw new Error(
                resultado.mensaje ||
                resultado.message ||
                resultado.error ||
                "No se pudo completar la operación."
            );
        }

        return resultado;
    }


    async function consultarHorariosAgenda(
        idServicio,
        fecha
    ) {

        if (!idServicio || !fecha) {

            throw new Error(
                "Debe seleccionar un servicio y una fecha."
            );
        }

        return await enviarAPI("horarios", {

            id_servicio: idServicio,

            fecha: fecha
        });
    }


    /* =========================================================
       7. NORMALIZAR DISPONIBILIDAD
    ========================================================= */

    function normalizarDisponibilidad(item) {

        if (!item) {
            return false;
        }

        const valor = item.disponible;

        if (valor !== undefined && valor !== null) {

            return (
                valor === true ||
                valor === 1 ||
                valor === "1" ||
                valor === "true" ||
                valor === "t"
            );
        }

        const estado = String(
            item.estado || ""
        ).trim().toLowerCase();

        const habilitado =
            item.habilitado === undefined ||
            item.habilitado === true ||
            item.habilitado === 1 ||
            item.habilitado === "1" ||
            item.habilitado === "t" ||
            item.habilitado === "true";

        return (
            estado === "disponible" &&
            habilitado
        );
    }


    /* =========================================================
       8. ELEMENTOS DEL FORMULARIO CREAR RESERVA
    ========================================================= */

    const btnCreateReservation = document.getElementById(
        "btnCreateReservation"
    );

    const createReservationSection = document.getElementById(
        "createReservationSection"
    );

    const createReservationForm = document.getElementById(
        "createReservationForm"
    );

    const createNombre = document.getElementById(
        "createNombre"
    );

    const createRut = document.getElementById(
        "createRut"
    );

    const createTelefono = document.getElementById(
        "createTelefono"
    );

    const createCorreo = document.getElementById(
        "createCorreo"
    );

    const createDepartamento = document.getElementById(
        "createDepartamento"
    );

    const createServicio = document.getElementById(
        "createServicio"
    );

    const createFecha = document.getElementById(
        "createFecha"
    );

    const createHorario = document.getElementById(
        "createHorario"
    );

    const createHorarioEstado = document.getElementById(
        "createHorarioEstado"
    );

    const createObservacion = document.getElementById(
        "createObservacion"
    );


    /* =========================================================
       9. ELEMENTOS DE VALIDACIÓN
    ========================================================= */

    const errorNombre = document.getElementById(
        "errorNombre"
    );

    const errorRut = document.getElementById(
        "errorRut"
    );

    const errorTelefono = document.getElementById(
        "errorTelefono"
    );

    const errorCorreo = document.getElementById(
        "errorCorreo"
    );


    /* =========================================================
       10. ELEMENTOS DE PREVISUALIZACIÓN
    ========================================================= */

    const previewNombre = document.getElementById(
        "previewNombre"
    );

    const previewIdentificacion = document.getElementById(
        "previewIdentificacion"
    );

    const previewDepartamento = document.getElementById(
        "previewDepartamento"
    );

    const previewServicio = document.getElementById(
        "previewServicio"
    );

    const previewFecha = document.getElementById(
        "previewFecha"
    );

    const previewHora = document.getElementById(
        "previewHora"
    );


    /* =========================================================
       11. BOTONES DEL FORMULARIO
    ========================================================= */

    const btnCloseCreateModal = document.getElementById(
        "btnCloseCreateModal"
    );

    const btnCancelCreate = document.getElementById(
        "btnCancelCreate"
    );


    /* =========================================================
       12. IDENTIFICACIÓN
    ========================================================= */

    function obtenerTipoIdentificacion() {

        const seleccionado = document.querySelector(
            'input[name="tipo_identificacion"]:checked'
        );

        return seleccionado
            ? seleccionado.value
            : "rut";
    }


    function formatearTipoIdentificacion(tipo) {

        const tipos = {

            rut: "RUT",

            rut_extranjero: "RUT extranjero",

            pasaporte: "Pasaporte"
        };

        const normalizado = String(tipo || "")
            .trim()
            .toLowerCase();

        return tipos[normalizado] ||
            tipo ||
            "Identificación";
    }


    function formatearRutChileno(valor) {

        valor = String(valor || "")
            .replace(/[^0-9kK]/g, "")
            .toUpperCase();

        if (!valor || valor.length === 1) {
            return valor;
        }

        const cuerpo = valor.slice(0, -1);

        const dv = valor.slice(-1);

        let cuerpoFormateado = "";

        let contador = 0;

        for (
            let i = cuerpo.length - 1;
            i >= 0;
            i--
        ) {

            cuerpoFormateado =
                cuerpo[i] + cuerpoFormateado;

            contador++;

            if (
                contador === 3 &&
                i !== 0
            ) {

                cuerpoFormateado =
                    "." + cuerpoFormateado;

                contador = 0;
            }
        }

        return `${cuerpoFormateado}-${dv}`;
    }


    function validarRutChileno(rut) {

        const valor = String(rut || "")
            .replace(/\./g, "")
            .replace(/-/g, "")
            .replace(/\s/g, "")
            .toUpperCase();

        if (!/^[0-9]{1,8}[0-9K]$/.test(valor)) {
            return false;
        }

        const cuerpo = valor.slice(0, -1);

        const dvIngresado = valor.slice(-1);

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

        const resultado = 11 - (suma % 11);

        let dvCalculado;

        if (resultado === 11) {

            dvCalculado = "0";

        } else if (resultado === 10) {

            dvCalculado = "K";

        } else {

            dvCalculado = String(resultado);
        }

        return dvCalculado === dvIngresado;
    }


    function validarRutExtranjero(valor) {

        return /^[A-Za-z0-9.\-]{4,20}$/.test(
            String(valor || "").trim()
        );
    }


    function validarPasaporte(valor) {

        return /^[A-Za-z0-9.\-]{4,20}$/.test(
            String(valor || "").trim()
        );
    }


    function validarIdentificacion() {

        if (!createRut) {
            return false;
        }

        const tipo = obtenerTipoIdentificacion();

        const valor = createRut.value.trim();

        if (tipo === "rut") {
            return validarRutChileno(valor);
        }

        if (tipo === "rut_extranjero") {
            return validarRutExtranjero(valor);
        }

        if (tipo === "pasaporte") {
            return validarPasaporte(valor);
        }

        return false;
    }


    /* =========================================================
       13. VALIDACIÓN DE DATOS PERSONALES
    ========================================================= */

    function validarNombre() {

        if (!createNombre) {
            return false;
        }

        const valor = createNombre.value.trim();

        return (
            valor.length >= 2 &&
            valor.length <= 100 &&
            /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s.'-]+$/.test(valor)
        );
    }


    function validarTelefono() {

        if (!createTelefono) {
            return false;
        }

        const valor = createTelefono.value.trim();

        return (
            valor.length >= 7 &&
            valor.length <= 20 &&
            /^[0-9+\s().\-]+$/.test(valor)
        );
    }


    function validarCorreo() {

        if (!createCorreo) {
            return true;
        }

        const valor = createCorreo.value.trim();

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


    function mostrarError(elemento, mensaje) {

        if (!elemento) {
            return;
        }

        elemento.textContent = mensaje;

        elemento.hidden = false;

        elemento.style.display = "block";
    }


    function ocultarError(elemento) {

        if (!elemento) {
            return;
        }

        elemento.textContent = "";

        elemento.hidden = true;

        elemento.style.display = "none";
    }


    function validarCamposVisualmente() {

        const nombreValido = validarNombre();

        const identificacionValida =
            validarIdentificacion();

        const telefonoValido = validarTelefono();

        const correoValido = validarCorreo();


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


    /* =========================================================
       14. DATOS SELECCIONADOS
    ========================================================= */

    function obtenerNombreDepartamentoSeleccionado() {

        if (!createDepartamento) {
            return "";
        }

        const option =
            createDepartamento.options[
                createDepartamento.selectedIndex
            ];

        return option
            ? (
                option.dataset.nombre ||
                option.textContent ||
                ""
            ).trim()
            : "";
    }


    function obtenerIdServicioSeleccionado() {

        if (
            !createServicio ||
            !createServicio.value
        ) {
            return "";
        }

        const option =
            createServicio.options[
                createServicio.selectedIndex
            ];

        if (!option) {
            return createServicio.value;
        }

        return (
            option.dataset.idServicio ||
            option.dataset.id ||
            option.value
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

        return option
            ? (
                option.dataset.nombre ||
                option.textContent ||
                ""
            ).trim()
            : "";
    }


    /* =========================================================
       15. PREVISUALIZACIÓN
    ========================================================= */

    function actualizarPreview() {

        if (previewNombre) {

            previewNombre.textContent =
                createNombre
                    ? createNombre.value.trim() || "-"
                    : "-";
        }


        if (previewIdentificacion) {

            const tipo = obtenerTipoIdentificacion();

            const valor = createRut
                ? createRut.value.trim()
                : "";

            previewIdentificacion.textContent = valor
                ? `${formatearTipoIdentificacion(tipo)}: ${valor}`
                : "-";
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
                createFecha && createFecha.value
                    ? fechaMostrar(createFecha.value)
                    : "-";
        }


        if (previewHora) {

            const option = createHorario
                ? createHorario.selectedOptions[0]
                : null;

            if (option && option.value) {

                const hora =
                    option.dataset.hora || "";

                const horaFin =
                    option.dataset.horaFin || "";

                previewHora.textContent = horaFin
                    ? `${hora} - ${horaFin}`
                    : hora;

            } else {

                previewHora.textContent = "-";
            }
        }
    }


    /* =========================================================
       16. ESTADO DEL FORMULARIO
    ========================================================= */

    function actualizarEstadoFormulario() {

        const personalesValidos =
            datosPersonalesValidos();

        const departamentoSeleccionado =
            createDepartamento &&
            createDepartamento.value;

        const servicioSeleccionado =
            obtenerIdServicioSeleccionado();

        if (createDepartamento) {

            createDepartamento.disabled =
                !personalesValidos;
        }


        if (createServicio) {

            createServicio.disabled = (
                !personalesValidos ||
                !departamentoSeleccionado ||
                createServicio.options.length <= 1
            );
        }


        if (createFecha) {

            createFecha.disabled = (
                !personalesValidos ||
                !servicioSeleccionado
            );
        }


        if (createHorario) {

            const fechaSeleccionada =
                createFecha && createFecha.value;

            const tieneHorarioSeleccionado =
                Array.from(
                    createHorario.options
                ).some(function (option) {

                    return (
                        option.value !== "" &&
                        !option.disabled
                    );
                });

            createHorario.disabled = (
                !personalesValidos ||
                !servicioSeleccionado ||
                !fechaSeleccionada ||
                !tieneHorarioSeleccionado
            );
        }

        actualizarPreview();

        procesarEnlaceAgendaPendiente();
    }


    /* =========================================================
       17. PROCESAR ENLACE DE AGENDA PENDIENTE
    ========================================================= */

    function procesarEnlaceAgendaPendiente() {

        if (
            !enlaceAgendaPendiente ||
            enlaceAgendaProcesado
        ) {
            return;
        }

        if (!datosPersonalesValidos()) {
            return;
        }

        const idServicio =
            obtenerIdServicioSeleccionado();

        const fecha =
            createFecha ? createFecha.value : "";

        if (
            !idServicio ||
            !fecha
        ) {
            return;
        }

        if (
            String(idServicio) !==
            String(enlaceAgendaPendiente.idServicio)
        ) {
            return;
        }

        if (
            String(fecha) !==
            String(enlaceAgendaPendiente.fecha)
        ) {
            return;
        }

        enlaceAgendaProcesado = true;

        cargarHorariosCrear(
            enlaceAgendaPendiente.hora
        );
    }


    /* =========================================================
       18. CARGAR DEPARTAMENTOS
    ========================================================= */

    async function cargarDepartamentosCrear() {

        if (!createDepartamento) {
            return;
        }

        createDepartamento.disabled = true;

        createDepartamento.innerHTML = `
            <option value="">
                Cargando departamentos...
            </option>
        `;

        try {

            const respuesta = await fetch(
                API_DEPARTAMENTOS_URL,
                {
                    method: "GET",
                    cache: "no-store"
                }
            );

            const datos = await leerRespuestaJSON(
                respuesta
            );

            if (
                !respuesta.ok ||
                datos.ok === false ||
                datos.success === false
            ) {

                throw new Error(
                    datos.mensaje ||
                    "No se pudieron cargar los departamentos."
                );
            }

            departamentosFormulario = Array.isArray(
                datos.departamentos
            )
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

                    const servicios = Array.isArray(
                        departamento.servicios
                    )
                        ? departamento.servicios
                        : [];

                    const activo = (
                        departamento.departamento_activo !== false &&
                        departamento.departamento_activo !== "false"
                    );

                    option.disabled = (
                        !activo ||
                        servicios.length === 0
                    );

                    createDepartamento.appendChild(
                        option
                    );
                }
            );

            createDepartamento.disabled = true;

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

            createDepartamento.disabled = true;

            showNotification(
                error.message,
                "error"
            );
        }
    }


    /* =========================================================
       19. SERVICIOS POR DEPARTAMENTO
    ========================================================= */

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

        createServicio.disabled = true;

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

                    return (
                        String(item.id_departamento) ===
                        String(idDepartamento)
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

        const servicios = Array.isArray(
            departamento.servicios
        )
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

        servicios.forEach(function (servicio) {

            const option =
                document.createElement("option");

            option.value = servicio.id_servicio;

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

            createServicio.appendChild(option);
        });

        if (idServicioSeleccionado) {

            const existe = Array.from(
                createServicio.options
            ).some(function (option) {

                return (
                    String(option.value) ===
                    String(idServicioSeleccionado)
                );
            });

            if (existe) {

                createServicio.value =
                    String(idServicioSeleccionado);
            }
        }

        createServicio.disabled = true;
    }


    /* =========================================================
       20. CARGAR HORARIOS REALES PARA CREAR RESERVA
    ========================================================= */

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

        const numeroConsulta =
            ++consultaHorariosCrear;

        const idServicio =
            obtenerIdServicioSeleccionado();

        const fecha =
            createFecha.value;

        if (!idServicio || !fecha) {

            createHorario.innerHTML = `
                <option value="">
                    Seleccione primero una fecha
                </option>
            `;

            createHorario.disabled = true;

            actualizarPreview();

            return;
        }

        createHorario.disabled = true;

        createHorario.innerHTML = `
            <option value="">
                Consultando agendas publicadas...
            </option>
        `;

        if (createHorarioEstado) {

            createHorarioEstado.textContent =
                "Consultando agendas publicadas...";

            createHorarioEstado.className = "";
        }

        try {

            const datos = await consultarHorariosAgenda(
                idServicio,
                fecha
            );

            if (
                numeroConsulta !==
                consultaHorariosCrear
            ) {
                return;
            }

            const horarios = Array.isArray(
                datos.horarios
            )
                ? datos.horarios
                : [];

            createHorario.innerHTML = `
                <option value="">
                    Seleccione un horario
                </option>
            `;

            let disponibles = 0;

            horarios.forEach(function (item) {

                const idHorario = Number(
                    item.id_horario
                );

                const hora = normalizarHora(
                    item.hora
                );

                if (!hora) {
                    return;
                }

                const tieneIdHorario = (
                    Number.isInteger(idHorario) &&
                    idHorario > 0
                );

                const estaDisponible =
                    normalizarDisponibilidad(item);

                const option =
                    document.createElement("option");

                option.value = tieneIdHorario
                    ? String(idHorario)
                    : "";

                option.dataset.idHorario =
                    tieneIdHorario
                        ? String(idHorario)
                        : "";

                option.dataset.hora = hora;

                option.dataset.horaFin =
                    normalizarHora(item.hora_fin);

                option.dataset.idServicioHorario =
                    String(
                        item.id_servicio_horario ||
                        item.id_servicio ||
                        idServicio
                    );

                const nombreServicioHorario =
                    item.servicio_horario ||
                    item.nombre_servicio_horario ||
                    item.servicio_nombre ||
                    "";

                let etiqueta = hora;

                if (item.hora_fin) {

                    etiqueta +=
                        ` - ${normalizarHora(item.hora_fin)}`;
                }

                if (
                    nombreServicioHorario &&
                    String(
                        item.id_servicio_horario ||
                        item.id_servicio
                    ) !== String(idServicio)
                ) {

                    etiqueta +=
                        ` (${nombreServicioHorario})`;
                }

                if (!tieneIdHorario) {

                    option.textContent =
                        `${etiqueta} — Horario sin identificador`;

                    option.disabled = true;

                } else if (estaDisponible) {

                    option.textContent = etiqueta;

                    disponibles++;

                } else {

                    option.textContent =
                        `${etiqueta} — No disponible`;

                    option.disabled = true;
                }

                createHorario.appendChild(option);
            });


            if (horaSeleccionada) {

                const optionEncontrada = Array.from(
                    createHorario.options
                ).find(function (option) {

                    return (
                        option.dataset.hora === horaSeleccionada &&
                        !option.disabled &&
                        option.value !== ""
                    );
                });

                if (optionEncontrada) {

                    createHorario.value =
                        optionEncontrada.value;
                }
            }


            createHorario.disabled =
                disponibles === 0;

            if (createHorarioEstado) {

                if (horarios.length === 0) {

                    createHorarioEstado.textContent =
                        "No existe una agenda publicada para esta fecha.";

                    createHorarioEstado.className =
                        "error";

                } else if (disponibles === 0) {

                    createHorarioEstado.textContent =
                        "No hay horarios disponibles para esta fecha.";

                    createHorarioEstado.className =
                        "error";

                } else {

                    createHorarioEstado.textContent =
                        `${disponibles} horario(s) disponible(s).`;

                    createHorarioEstado.className =
                        "success";
                }
            }

        } catch (error) {

            if (
                numeroConsulta !==
                consultaHorariosCrear
            ) {
                return;
            }

            console.error(
                "Error cargando horarios para crear:",
                error
            );

            createHorario.innerHTML = `
                <option value="">
                    Error al consultar agendas
                </option>
            `;

            createHorario.disabled = true;

            if (createHorarioEstado) {

                createHorarioEstado.textContent =
                    error.message;

                createHorarioEstado.className =
                    "error";
            }

            showNotification(
                error.message,
                "error"
            );

        } finally {

            if (
                numeroConsulta ===
                consultaHorariosCrear
            ) {
                actualizarPreview();
            }
        }
    }


    /* =========================================================
       21. ABRIR FORMULARIO CREAR RESERVA
    ========================================================= */

    async function abrirFormularioCrear() {

        if (!createReservationSection) {

            showNotification(
                "No se encontró el formulario de creación de reservas.",
                "error"
            );

            return;
        }

        consultaHorariosCrear++;

        enlaceAgendaPendiente = null;

        enlaceAgendaProcesado = false;

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
            createFecha.min = obtenerFechaLocal();
        }

        if (createObservacion) {
            createObservacion.value = "";
        }


        document.querySelectorAll(
            'input[name="tipo_identificacion"]'
        ).forEach(function (radio) {

            radio.checked = (
                radio.value === "rut"
            );
        });


        if (createDepartamento) {

            createDepartamento.innerHTML = `
                <option value="">
                    Cargando departamentos...
                </option>
            `;

            createDepartamento.value = "";
            createDepartamento.disabled = true;
        }

        if (createServicio) {

            createServicio.innerHTML = `
                <option value="">
                    Seleccione primero un departamento
                </option>
            `;

            createServicio.value = "";
            createServicio.disabled = true;
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

        if (createHorarioEstado) {
            createHorarioEstado.textContent = "";
            createHorarioEstado.className = "";
        }


        ocultarError(errorNombre);
        ocultarError(errorRut);
        ocultarError(errorTelefono);
        ocultarError(errorCorreo);

        actualizarPreview();


        await cargarDepartamentosCrear();


        if (servicioURL) {

            let departamentoEncontrado = null;

            for (
                const departamento of departamentosFormulario
            ) {

                const servicios = Array.isArray(
                    departamento.servicios
                )
                    ? departamento.servicios
                    : [];

                const encontrado = servicios.find(
                    function (servicio) {

                        return (
                            String(servicio.id_servicio) ===
                            String(servicioURL)
                        );
                    }
                );

                if (encontrado) {

                    departamentoEncontrado =
                        departamento;

                    break;
                }
            }


            if (departamentoEncontrado) {

                createDepartamento.value =
                    String(
                        departamentoEncontrado.id_departamento
                    );

                cargarServiciosPorDepartamento(
                    departamentoEncontrado.id_departamento,
                    servicioURL
                );

                enlaceAgendaPendiente = {
                    idServicio: String(servicioURL),
                    fecha: String(fechaURL || ""),
                    hora: normalizarHora(horaURL || "")
                };

            } else {

                showNotification(
                    "No se encontró el servicio indicado en el enlace de la agenda.",
                    "warning"
                );
            }
        }


        if (fechaURL && createFecha) {

            createFecha.value = fechaURL;
        }


        actualizarEstadoFormulario();

        actualizarPreview();
    }


    /* =========================================================
       22. CERRAR FORMULARIO CREAR RESERVA
    ========================================================= */

    function cerrarFormularioCrear() {

        if (!createReservationSection) {
            return;
        }

        consultaHorariosCrear++;

        enlaceAgendaPendiente = null;

        enlaceAgendaProcesado = false;

        createReservationSection.classList.remove(
            "active"
        );

        createReservationSection.hidden = true;

        createReservationSection.style.display = "none";

        document.body.classList.remove(
            "modal-open"
        );
    }


    /* =========================================================
       23. EVENTOS DEL FORMULARIO CREAR
    ========================================================= */

    if (btnCreateReservation) {

        btnCreateReservation.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                abrirFormularioCrear();
            }
        );
    }


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


    if (createReservationSection) {

        createReservationSection.addEventListener(
            "click",
            function (event) {

                if (
                    event.target ===
                    createReservationSection
                ) {
                    cerrarFormularioCrear();
                }
            }
        );
    }


    /* =========================================================
       24. CAMBIO DE DEPARTAMENTO
    ========================================================= */

    if (createDepartamento) {

        createDepartamento.addEventListener(
            "change",
            function () {

                consultaHorariosCrear++;

                enlaceAgendaPendiente = null;

                enlaceAgendaProcesado = false;

                cargarServiciosPorDepartamento(
                    createDepartamento.value
                );

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
                    createHorario.disabled = true;
                }

                if (createHorarioEstado) {
                    createHorarioEstado.textContent = "";
                    createHorarioEstado.className = "";
                }

                actualizarEstadoFormulario();

                actualizarPreview();
            }
        );
    }


    /* =========================================================
       25. CAMBIO DE SERVICIO
    ========================================================= */

    if (createServicio) {

        createServicio.addEventListener(
            "change",
            function () {

                consultaHorariosCrear++;

                enlaceAgendaPendiente = null;

                enlaceAgendaProcesado = false;

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
                    createHorario.disabled = true;
                }

                if (createHorarioEstado) {
                    createHorarioEstado.textContent = "";
                    createHorarioEstado.className = "";
                }

                actualizarEstadoFormulario();

                actualizarPreview();
            }
        );
    }


    /* =========================================================
       26. CAMBIO DE FECHA
    ========================================================= */

    if (createFecha) {

        createFecha.addEventListener(
            "change",
            async function () {

                enlaceAgendaPendiente = null;

                enlaceAgendaProcesado = false;

                if (!createFecha.value) {

                    consultaHorariosCrear++;

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

                if (!datosPersonalesValidos()) {

                    actualizarEstadoFormulario();

                    showNotification(
                        "Complete los datos personales antes de consultar los horarios.",
                        "warning"
                    );

                    return;
                }

                if (!obtenerIdServicioSeleccionado()) {

                    showNotification(
                        "Seleccione un servicio antes de consultar horarios.",
                        "warning"
                    );

                    return;
                }

                await cargarHorariosCrear();

                actualizarEstadoFormulario();

                actualizarPreview();
            }
        );
    }


    /* =========================================================
       27. CAMBIO DE HORARIO
    ========================================================= */

    if (createHorario) {

        createHorario.addEventListener(
            "change",
            actualizarPreview
        );
    }


    /* =========================================================
       28. EVENTOS DE DATOS PERSONALES
    ========================================================= */

    if (createNombre) {

        createNombre.addEventListener(
            "input",
            function () {

                ocultarError(errorNombre);

                actualizarEstadoFormulario();
            }
        );
    }


    if (createRut) {

        createRut.addEventListener(
            "input",
            function () {

                if (
                    obtenerTipoIdentificacion() === "rut"
                ) {

                    createRut.value =
                        formatearRutChileno(
                            createRut.value
                        );
                }

                ocultarError(errorRut);

                actualizarEstadoFormulario();
            }
        );
    }


    if (createTelefono) {

        createTelefono.addEventListener(
            "input",
            function () {

                ocultarError(errorTelefono);

                actualizarEstadoFormulario();
            }
        );
    }


    if (createCorreo) {

        createCorreo.addEventListener(
            "input",
            function () {

                ocultarError(errorCorreo);

                actualizarEstadoFormulario();
            }
        );
    }


    document.querySelectorAll(
        'input[name="tipo_identificacion"]'
    ).forEach(function (radio) {

        radio.addEventListener(
            "change",
            function () {

                if (
                    createRut &&
                    obtenerTipoIdentificacion() === "rut"
                ) {

                    createRut.value =
                        formatearRutChileno(
                            createRut.value
                        );
                }

                ocultarError(errorRut);

                actualizarEstadoFormulario();
            }
        );
    });


    /* =========================================================
       29. ENVÍO DE RESERVA DESDE EL PANEL INTERNO
    ========================================================= */

    if (createReservationForm) {

        createReservationForm.noValidate = true;

        createReservationForm.addEventListener(
            "submit",
            async function (event) {

                event.preventDefault();

                event.stopPropagation();


                const personalesValidos =
                    validarCamposVisualmente();

                if (!personalesValidos) {

                    showNotification(
                        "Complete correctamente los datos personales.",
                        "error"
                    );

                    return;
                }


                const idDepartamento =
                    createDepartamento
                        ? createDepartamento.value
                        : "";

                const idServicio =
                    obtenerIdServicioSeleccionado();

                const fecha =
                    createFecha
                        ? createFecha.value
                        : "";

                const opcionHorario =
                    createHorario
                        ? createHorario.selectedOptions[0]
                        : null;

                const idHorario =
                    opcionHorario
                        ? opcionHorario.dataset.idHorario
                        : "";

                const hora =
                    opcionHorario
                        ? opcionHorario.dataset.hora
                        : "";


                if (!idDepartamento) {

                    showNotification(
                        "Seleccione un departamento.",
                        "error"
                    );

                    createDepartamento?.focus();

                    return;
                }


                if (!idServicio) {

                    showNotification(
                        "Seleccione un servicio.",
                        "error"
                    );

                    createServicio?.focus();

                    return;
                }


                if (!fecha) {

                    showNotification(
                        "Seleccione una fecha.",
                        "error"
                    );

                    createFecha?.focus();

                    return;
                }


                if (
                    !idHorario ||
                    !Number.isInteger(Number(idHorario)) ||
                    Number(idHorario) <= 0 ||
                    !hora ||
                    !opcionHorario ||
                    opcionHorario.disabled
                ) {

                    showNotification(
                        "Seleccione un horario válido de una agenda publicada.",
                        "error"
                    );

                    createHorario?.focus();

                    return;
                }


                const formData = new FormData();

                formData.append(
                    "nombre",
                    createNombre.value.trim()
                );

                formData.append(
                    "rut",
                    createRut.value.trim()
                );

                formData.append(
                    "tipo_identificacion",
                    obtenerTipoIdentificacion()
                );

                formData.append(
                    "telefono",
                    createTelefono.value.trim()
                );

                formData.append(
                    "correo",
                    createCorreo
                        ? createCorreo.value.trim()
                        : ""
                );


                formData.append(
                    "id_servicio",
                    idServicio
                );

                formData.append(
                    "servicio",
                    idServicio
                );

                formData.append(
                    "id_departamento",
                    idDepartamento
                );

                formData.append(
                    "departamento",
                    idDepartamento
                );


                formData.append(
                    "id_horario",
                    idHorario
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


                const botonSubmit =
                    createReservationForm.querySelector(
                        'button[type="submit"]'
                    );

                const textoOriginal = botonSubmit
                    ? botonSubmit.textContent
                    : "";

                if (botonSubmit) {

                    botonSubmit.disabled = true;

                    botonSubmit.textContent =
                        "Creando reserva...";
                }


                try {

                    const respuesta = await fetch(
                        API_CREAR_URL,
                        {
                            method: "POST",
                            body: formData,
                            cache: "no-store"
                        }
                    );

                    const resultado =
                        await leerRespuestaJSON(respuesta);

                    if (
                        !respuesta.ok ||
                        resultado.ok === false ||
                        resultado.success === false
                    ) {

                        throw new Error(
                            resultado.mensaje ||
                            resultado.message ||
                            resultado.error ||
                            "No se pudo crear la reserva."
                        );
                    }


                    showNotification(
                        resultado.mensaje ||
                        "Solicitud de hora recibida correctamente. Pendiente de confirmación.",
                        "success"
                    );

                    cerrarFormularioCrear();

                    setTimeout(function () {

                        window.location.reload();

                    }, 1000);

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

                    if (botonSubmit) {

                        botonSubmit.disabled = false;

                        botonSubmit.textContent =
                            textoOriginal ||
                            "Crear reserva";
                    }
                }
            }
        );
    }


    /* =========================================================
       30. POPUP VER RESERVA
    ========================================================= */

    function crearPopupVerReserva() {

        reservationViewModal =
            document.getElementById(
                "reservationViewModal"
            );

        if (reservationViewModal) {
            return reservationViewModal;
        }

        reservationViewModal =
            document.createElement("div");

        reservationViewModal.id =
            "reservationViewModal";

        reservationViewModal.className =
            "reservation-view-modal";

        reservationViewModal.hidden = true;

        reservationViewModal.setAttribute(
            "aria-hidden",
            "true"
        );

        reservationViewModal.innerHTML = `

            <div
                class="reservation-view-dialog"
                role="dialog"
                aria-modal="true"
            >

                <div class="reservation-view-header">

                    <h2>
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


    function formatearEstadoReserva(estado) {

        const estados = {

            confirmada: "Confirmada",

            cancelada: "Cancelada",

            reagendada: "Reagendada",

            pendiente_confirmacion:
                "Pendiente de confirmación",

            pendiente: "Pendiente"
        };

        const normalizado = String(estado || "")
            .trim()
            .toLowerCase();

        return estados[normalizado] ||
            estado ||
            "No registrado";
    }


    function abrirPopupVerReserva(boton) {

        if (!boton) {
            return;
        }

        const modal = crearPopupVerReserva();

        const dataset = boton.dataset;

        const valores = {

            viewReservaId:
                dataset.idReserva ||
                dataset.id
                    ? `#${dataset.idReserva || dataset.id}`
                    : "No registrado",

            viewReservaEstado:
                formatearEstadoReserva(dataset.estado),

            viewReservaNombre:
                dataset.nombre || "No registrado",

            viewReservaRut:
                dataset.rut
                    ? `${formatearTipoIdentificacion(
                        dataset.tipoIdentificacion || "rut"
                    )} - ${dataset.rut}`
                    : "No registrado",

            viewReservaServicio:
                dataset.servicio ||
                dataset.servicioNombre ||
                "No registrado",

            viewReservaFecha:
                fechaMostrar(dataset.fecha),

            viewReservaHora:
                dataset.hora || "No registrada",

            viewReservaTelefono:
                dataset.telefono || "No registrado",

            viewReservaCorreo:
                dataset.correo || "No registrado"
        };

        Object.entries(valores).forEach(
            function ([id, valor]) {

                const elemento =
                    modal.querySelector(`#${id}`);

                if (elemento) {

                    elemento.textContent = valor;
                }
            }
        );

        modal.hidden = false;

        modal.classList.add("active");

        modal.setAttribute(
            "aria-hidden",
            "false"
        );

        document.body.classList.add(
            "modal-open"
        );
    }


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

        reservationViewModal.hidden = true;

        reservationViewModal.setAttribute(
            "aria-hidden",
            "true"
        );

        document.body.classList.remove(
            "modal-open"
        );
    }


    /* =========================================================
       31. POPUP MODIFICAR RESERVA
    ========================================================= */

    function crearPopupModificarReserva() {

        reservationModifyModal =
            document.getElementById(
                "reservationModifyModal"
            );

        if (reservationModifyModal) {

            reservationModifyModal.hidden = true;

            return reservationModifyModal;
        }

        reservationModifyModal =
            document.createElement("div");

        reservationModifyModal.id =
            "reservationModifyModal";

        reservationModifyModal.className =
            "reservation-modal";

        reservationModifyModal.hidden = true;

        reservationModifyModal.innerHTML = `

            <div class="reservation-modal-overlay"></div>

            <div class="reservation-modal-content">

                <div class="reservation-modal-header">

                    <h2>
                        Modificar reserva
                    </h2>

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
                            Nuevo horario
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

        return reservationModifyModal;
    }


    function cerrarPopupModificarReserva() {

        consultaHorariosModificar++;

        if (reservationModifyModal) {

            reservationModifyModal.classList.remove(
                "active"
            );

            reservationModifyModal.hidden = true;

            reservationModifyModal.style.display = "none";

            reservationModifyModal.style.visibility = "hidden";

            reservationModifyModal.style.opacity = "0";
        }

        document.body.classList.remove(
            "modal-open"
        );

        reservaModificarActual = null;
    }


    /* =========================================================
       32. RELLENAR DATOS DEL POPUP MODIFICAR
    ========================================================= */

    function rellenarDatosPopupModificar(datos) {

        const campos = {

            modifyReservaIdMostrar:
                datos.idReserva || "-",

            modifyReservaNombre:
                datos.nombre || "-",

            modifyReservaRut:
                datos.rut
                    ? `${formatearTipoIdentificacion(
                        datos.tipoIdentificacion || "rut"
                    )} - ${datos.rut}`
                    : "-",

            modifyReservaTelefono:
                datos.telefono || "-",

            modifyReservaCorreo:
                datos.correo || "-",

            modifyReservaServicio:
                datos.servicioNombre || "-",

            modifyReservaFechaActual:
                fechaMostrar(datos.fecha),

            modifyReservaHoraActual:
                datos.hora || "-"
        };

        Object.entries(campos).forEach(
            function ([id, valor]) {

                const elemento =
                    document.getElementById(id);

                if (elemento) {

                    elemento.textContent = valor;
                }
            }
        );

        const inputId =
            document.getElementById(
                "modifyReservaId"
            );

        if (inputId) {

            inputId.value =
                datos.idReserva || "";
        }
    }


    /* =========================================================
       33. CARGAR HORARIOS REALES PARA REAGENDAR
    ========================================================= */

    async function cargarHorariosModificar() {

        if (!reservaModificarActual) {
            return;
        }

        const numeroConsulta =
            ++consultaHorariosModificar;

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
            return;
        }

        const fecha = fechaElement.value;

        const idServicio =
            reservaModificarActual.idServicio;

        if (!fecha) {

            selectHora.innerHTML = `
                <option value="">
                    Seleccione una fecha
                </option>
            `;

            selectHora.disabled = true;

            estadoHorario.textContent = "";

            return;
        }

        if (!idServicio) {

            selectHora.innerHTML = `
                <option value="">
                    No se pudo identificar el servicio
                </option>
            `;

            selectHora.disabled = true;

            estadoHorario.textContent =
                "No se pudo identificar el servicio de la reserva.";

            estadoHorario.className = "error";

            return;
        }

        selectHora.innerHTML = `
            <option value="">
                Consultando agendas publicadas...
            </option>
        `;

        selectHora.disabled = true;

        estadoHorario.textContent =
            "Consultando agendas publicadas...";

        estadoHorario.className = "";

        try {

            const datos = await consultarHorariosAgenda(
                idServicio,
                fecha
            );

            if (
                numeroConsulta !==
                consultaHorariosModificar
            ) {
                return;
            }

            const horarios = Array.isArray(
                datos.horarios
            )
                ? datos.horarios
                : [];

            selectHora.innerHTML = `
                <option value="">
                    Seleccione un horario
                </option>
            `;

            let disponibles = 0;

            horarios.forEach(function (item) {

                const idHorario = Number(
                    item.id_horario
                );

                const hora = normalizarHora(
                    item.hora
                );

                if (!hora) {
                    return;
                }

                const tieneIdHorario = (
                    Number.isInteger(idHorario) &&
                    idHorario > 0
                );

                const disponible =
                    normalizarDisponibilidad(item);

                const option =
                    document.createElement("option");

                option.value = tieneIdHorario
                    ? String(idHorario)
                    : "";

                option.dataset.idHorario =
                    tieneIdHorario
                        ? String(idHorario)
                        : "";

                option.dataset.hora = hora;

                option.dataset.horaFin =
                    normalizarHora(item.hora_fin);

                const nombreServicioHorario =
                    item.servicio_horario ||
                    item.nombre_servicio_horario ||
                    item.servicio_nombre ||
                    "";

                let etiqueta = hora;

                if (item.hora_fin) {

                    etiqueta +=
                        ` - ${normalizarHora(item.hora_fin)}`;
                }

                if (
                    nombreServicioHorario &&
                    String(
                        item.id_servicio_horario ||
                        item.id_servicio
                    ) !== String(idServicio)
                ) {

                    etiqueta +=
                        ` (${nombreServicioHorario})`;
                }

                if (!tieneIdHorario) {

                    option.textContent =
                        `${etiqueta} — Horario sin identificador`;

                    option.disabled = true;

                } else if (disponible) {

                    option.textContent = etiqueta;

                    disponibles++;

                } else {

                    option.textContent =
                        `${etiqueta} — No disponible`;

                    option.disabled = true;
                }

                selectHora.appendChild(option);
            });


            selectHora.disabled =
                disponibles === 0;

            if (horarios.length === 0) {

                estadoHorario.textContent =
                    "No existe una agenda publicada para esta fecha.";

                estadoHorario.className = "error";

            } else if (disponibles === 0) {

                estadoHorario.textContent =
                    "No hay horarios disponibles para esta fecha.";

                estadoHorario.className = "error";

            } else {

                estadoHorario.textContent =
                    `${disponibles} horario(s) disponible(s).`;

                estadoHorario.className = "success";
            }

        } catch (error) {

            if (
                numeroConsulta !==
                consultaHorariosModificar
            ) {
                return;
            }

            console.error(
                "Error cargando horarios para modificar:",
                error
            );

            selectHora.innerHTML = `
                <option value="">
                    Error al cargar horarios
                </option>
            `;

            selectHora.disabled = true;

            estadoHorario.textContent =
                error.message;

            estadoHorario.className = "error";
        }
    }


    /* =========================================================
       34. ABRIR POPUP MODIFICAR
    ========================================================= */

    async function abrirPopupModificarReserva(boton) {

        if (!boton) {
            return;
        }

        crearPopupModificarReserva();

        const leerDato = function (atributo) {

            return String(
                boton.getAttribute(atributo) || ""
            ).trim();
        };

        const idReserva =
            leerDato("data-id-reserva") ||
            leerDato("data-id");

        if (!idReserva) {

            showNotification(
                "El botón no contiene el identificador de la reserva.",
                "error"
            );

            return;
        }

        reservaModificarActual = {

            idReserva: idReserva,

            idServicio:
                leerDato("data-id-servicio") ||
                leerDato("data-servicio-id"),

            nombre:
                leerDato("data-nombre"),

            rut:
                leerDato("data-rut"),

            tipoIdentificacion:
                leerDato("data-tipo-identificacion") || "rut",

            telefono:
                leerDato("data-telefono"),

            correo:
                leerDato("data-correo"),

            servicioNombre:
                leerDato("data-servicio-nombre") ||
                leerDato("data-servicio"),

            fecha:
                leerDato("data-fecha"),

            hora:
                leerDato("data-hora"),

            estado:
                leerDato("data-estado")
        };


        reservationModifyModal.hidden = false;

        reservationModifyModal.style.display = "flex";

        reservationModifyModal.style.visibility = "visible";

        reservationModifyModal.style.opacity = "1";

        reservationModifyModal.classList.add("active");

        document.body.classList.add(
            "modal-open"
        );

        rellenarDatosPopupModificar(
            reservaModificarActual
        );


        const estadoHorario =
            document.getElementById(
                "modifyReservaEstadoHorario"
            );

        const fechaElement =
            document.getElementById(
                "modifyReservaFecha"
            );

        const horaElement =
            document.getElementById(
                "modifyReservaHora"
            );

        if (fechaElement) {

            fechaElement.min =
                obtenerFechaLocal();

            fechaElement.value = "";
        }

        if (horaElement) {

            horaElement.innerHTML = `
                <option value="">
                    Seleccione una fecha
                </option>
            `;

            horaElement.value = "";

            horaElement.disabled = true;
        }

        if (estadoHorario) {

            estadoHorario.textContent =
                "Consultando los datos actuales de la reserva...";

            estadoHorario.className = "";
        }


        try {

            const datos = await enviarAPI(
                "obtener_reserva",
                {
                    id_reserva: idReserva
                }
            );

            const reserva =
                datos.reserva ||
                (
                    datos.data &&
                    datos.data.reserva
                ) ||
                datos.data;

            if (!reserva) {

                throw new Error(
                    "La API no devolvió los datos de la reserva."
                );
            }

            reservaModificarActual = {

                ...reservaModificarActual,

                idReserva:
                    String(reserva.id_reserva || idReserva),

                idServicio:
                    String(reserva.id_servicio || ""),

                nombre:
                    reserva.nombre || "",

                rut:
                    reserva.rut || "",

                tipoIdentificacion:
                    reserva.tipo_identificacion || "rut",

                telefono:
                    reserva.telefono || "",

                correo:
                    reserva.correo || "",

                servicioNombre:
                    reserva.servicio_nombre ||
                    reservaModificarActual.servicioNombre,

                fecha:
                    reserva.fecha_horario ||
                    reserva.fecha ||
                    reservaModificarActual.fecha,

                hora:
                    reserva.hora_horario ||
                    reserva.hora ||
                    reservaModificarActual.hora,

                estado:
                    reserva.estado || ""
            };

            rellenarDatosPopupModificar(
                reservaModificarActual
            );

            if (estadoHorario) {

                estadoHorario.textContent =
                    "Seleccione una nueva fecha para consultar las agendas.";

                estadoHorario.className = "";
            }

        } catch (error) {

            console.error(
                "No se pudieron actualizar los datos de la reserva:",
                error
            );

            if (
                !reservaModificarActual.idServicio
            ) {

                if (estadoHorario) {

                    estadoHorario.textContent =
                        "No se pudo identificar el servicio de la reserva.";

                    estadoHorario.className = "error";
                }

                showNotification(
                    error.message,
                    "error"
                );
            } else {

                showNotification(
                    "No se pudieron actualizar los datos. Se utilizará la información del listado.",
                    "warning"
                );
            }
        }
    }


    /* =========================================================
       35. CAMBIO DE FECHA PARA MODIFICAR
    ========================================================= */

    document.addEventListener(
        "change",
        function (event) {

            if (
                event.target &&
                event.target.id === "modifyReservaFecha"
            ) {

                cargarHorariosModificar();
            }
        }
    );


    /* =========================================================
       36. GUARDAR MODIFICACIÓN
    ========================================================= */

    document.addEventListener(
        "click",
        async function (event) {

            const objetivo =
                event.target instanceof Element
                    ? event.target
                    : null;

            const boton = objetivo
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

            const opcionSeleccionada =
                horaElement.selectedOptions[0];

            const idNuevoHorario =
                opcionSeleccionada
                    ? opcionSeleccionada.dataset.idHorario
                    : "";

            if (!nuevaFecha) {

                showNotification(
                    "Seleccione una nueva fecha.",
                    "error"
                );

                return;
            }

            if (
                !idNuevoHorario ||
                !Number.isInteger(Number(idNuevoHorario)) ||
                Number(idNuevoHorario) <= 0 ||
                !opcionSeleccionada ||
                opcionSeleccionada.disabled
            ) {

                showNotification(
                    "Seleccione un horario disponible de una agenda publicada.",
                    "error"
                );

                return;
            }

            const textoOriginal =
                boton.textContent;

            boton.disabled = true;

            boton.textContent = "Guardando...";

            try {

                const resultado = await enviarAPI(
                    "reagendar",
                    {
                        id_reserva:
                            reservaModificarActual.idReserva,

                        id_nuevo_horario:
                            idNuevoHorario
                    }
                );

                showNotification(
                    resultado.mensaje ||
                    "Reserva modificada correctamente.",
                    "success"
                );

                cerrarPopupModificarReserva();

                setTimeout(function () {

                    window.location.reload();

                }, 1000);

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

                boton.textContent = textoOriginal;
            }
        }
    );


    /* =========================================================
       37. CONFIRMAR RESERVA
    ========================================================= */

    document.addEventListener(
        "click",
        async function (event) {

            const objetivo =
                event.target instanceof Element
                    ? event.target
                    : null;

            const boton = objetivo
                ? objetivo.closest(
                    '[data-reserva-accion="confirmar"]'
                )
                : null;

            if (!boton) {
                return;
            }

            event.preventDefault();

            event.stopPropagation();

            const idReserva =
                boton.dataset.idReserva ||
                boton.getAttribute("data-id-reserva");

            if (
                !idReserva ||
                !Number.isInteger(Number(idReserva)) ||
                Number(idReserva) <= 0
            ) {

                showNotification(
                    "No se pudo identificar la reserva.",
                    "error"
                );

                return;
            }

            if (boton.disabled) {
                return;
            }

            const textoOriginal =
                boton.textContent.trim();

            boton.disabled = true;

            boton.textContent = "Confirmando...";

            try {

                const resultado = await enviarAPI(
                    "confirmar",
                    {
                        id_reserva: idReserva
                    }
                );

                showNotification(
                    resultado.mensaje ||
                    "Reserva confirmada correctamente.",
                    "success"
                );

                boton.textContent = "Confirmada";

                setTimeout(function () {

                    window.location.reload();

                }, 1000);

            } catch (error) {

                console.error(
                    "Error confirmando reserva:",
                    error
                );

                showNotification(
                    error.message ||
                    "No fue posible confirmar la reserva.",
                    "error"
                );

                boton.disabled = false;

                boton.textContent = textoOriginal;
            }
        }
    );


    /* =========================================================
       38. CANCELAR RESERVA
    ========================================================= */

    /*
     * El botón utiliza:
     *
     * data-reserva-accion="cancelar"
     * data-id-reserva="ID"
     *
     * Antes de cancelar se solicita confirmación.
     *
     * La API se encarga de:
     * - Verificar los permisos del funcionario.
     * - Cambiar el estado a cancelada.
     * - Liberar el horario cuando corresponda.
     * - Enviar los correos de cancelación.
     *
     * Al finalizar correctamente se recarga el listado.
     */

    document.addEventListener(
        "click",
        async function (event) {

            const objetivo =
                event.target instanceof Element
                    ? event.target
                    : null;

            const boton = objetivo
                ? objetivo.closest(
                    '[data-reserva-accion="cancelar"]'
                )
                : null;

            if (!boton) {
                return;
            }

            event.preventDefault();

            event.stopPropagation();

            const idReserva =
                boton.dataset.idReserva ||
                boton.getAttribute("data-id-reserva") ||
                boton.dataset.id;

            if (
                !idReserva ||
                !Number.isInteger(Number(idReserva)) ||
                Number(idReserva) <= 0
            ) {

                showNotification(
                    "No se pudo identificar la reserva que desea cancelar.",
                    "error"
                );

                return;
            }

            if (boton.disabled) {
                return;
            }

            const confirmarCancelacion = window.confirm(
                "¿Está seguro de que desea cancelar esta reserva? Esta acción cambiará su estado y liberará el horario cuando corresponda."
            );

            if (!confirmarCancelacion) {
                return;
            }

            const textoOriginal =
                boton.textContent.trim();

            boton.disabled = true;

            boton.textContent = "Cancelando...";

            try {

                const resultado = await enviarAPI(
                    "cancelar",
                    {
                        id_reserva: idReserva
                    }
                );

                showNotification(
                    resultado.mensaje ||
                    "La reserva fue cancelada correctamente.",
                    "success"
                );

                boton.textContent = "Cancelada";

                setTimeout(function () {

                    window.location.reload();

                }, 1000);

            } catch (error) {

                console.error(
                    "Error cancelando reserva:",
                    error
                );

                showNotification(
                    error.message ||
                    "No fue posible cancelar la reserva.",
                    "error"
                );

                boton.disabled = false;

                boton.textContent = textoOriginal;
            }
        }
    );


    /* =========================================================
       39. BOTÓN VER RESERVA
    ========================================================= */

    document.addEventListener(
        "click",
        function (event) {

            const objetivo =
                event.target instanceof Element
                    ? event.target
                    : null;

            const boton = objetivo
                ? objetivo.closest(
                    ".action-button.view, [data-reserva-view]"
                )
                : null;

            if (!boton) {
                return;
            }

            event.preventDefault();

            event.stopPropagation();

            abrirPopupVerReserva(boton);
        },
        true
    );


    /* =========================================================
       40. BOTÓN MODIFICAR RESERVA
    ========================================================= */

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

            const boton =
                objetivo.closest(
                    ".action-button.reschedule, [data-action='modificar']"
                );

            if (!boton) {
                return;
            }

            event.preventDefault();

            event.stopPropagation();

            abrirPopupModificarReserva(boton);
        },
        true
    );


    /* =========================================================
       41. CERRAR POPUP VER
    ========================================================= */

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

            const botonCerrar = objetivo.closest(
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

                cerrarPopupVerReserva();

                return;
            }

            if (
                reservationViewModal &&
                objetivo === reservationViewModal
            ) {

                cerrarPopupVerReserva();
            }
        }
    );


    /* =========================================================
       42. CERRAR POPUP MODIFICAR
    ========================================================= */

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
                ) ||

                objetivo.closest(
                    "#btnCancelReservationModify"
                ) ||

                objetivo.classList.contains(
                    "reservation-modal-overlay"
                )
            ) {

                event.preventDefault();

                cerrarPopupModificarReserva();
            }
        }
    );


    /* =========================================================
       43. ESC PARA CERRAR MODALES
    ========================================================= */

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
                requestAssignModal &&
                !requestAssignModal.hidden
            ) {

                cerrarModalAsignarSolicitud();

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


    /* =========================================================
       44. ABRIR CREAR DESDE AGENDA
    ========================================================= */

    function abrirCrearDesdeAgenda() {

        if (
            abrirCrearReserva === "1" ||
            abrirCrearReserva === "true"
        ) {

            abrirFormularioCrear();
        }
    }


    /* =========================================================
       45. INICIALIZACIÓN
    ========================================================= */

    crearPopupVerReserva();

    crearPopupModificarReserva();


    if (createReservationSection) {

        createReservationSection.hidden = true;

        createReservationSection.style.display = "none";
    }


    if (createReservationForm) {

        createReservationForm.noValidate = true;
    }


    if (createFecha) {

        createFecha.min = obtenerFechaLocal();
    }


    actualizarPreview();


    /*
     * Abrir formulario automáticamente cuando se llega
     * desde una agenda mediante:
     *
     * ?crear_reserva=1&servicio=ID&fecha=AAAA-MM-DD&hora=HH:MM
     */
    abrirCrearDesdeAgenda();


    /* =========================================================
       46. GESTIÓN DE SOLICITUDES DE HORAS
    ========================================================= */

    const requestAssignModal = document.getElementById("requestAssignModal");
    const requestAssignId = document.getElementById("requestAssignId");
    const requestAssignServiceId = document.getElementById("requestAssignServiceId");
    const requestAssignDepartmentId = document.getElementById("requestAssignDepartmentId");
    const requestAssignName = document.getElementById("requestAssignName");
    const requestAssignSurname = document.getElementById("requestAssignSurname");
    const requestAssignRut = document.getElementById("requestAssignRut");
    const requestAssignPhone = document.getElementById("requestAssignPhone");
    const requestAssignEmail = document.getElementById("requestAssignEmail");
    const requestAssignPatientName = document.getElementById("requestAssignPatientName");
    const requestAssignPatientRut = document.getElementById("requestAssignPatientRut");
    const requestAssignPatientPhone = document.getElementById("requestAssignPatientPhone");
    const requestAssignPatientEmail = document.getElementById("requestAssignPatientEmail");
    const requestAssignServiceName = document.getElementById("requestAssignServiceName");
    const requestAssignDate = document.getElementById("requestAssignDate");
    const requestAssignHour = document.getElementById("requestAssignHour");
    const requestAssignHourMessage = document.getElementById("requestAssignHourMessage");
    const btnConfirmRequestAssignment = document.getElementById("btnConfirmRequestAssignment");

    function abrirModalAsignarSolicitud(boton) {
        if (!requestAssignModal || !boton) {
            return;
        }

        const idSolicitud = boton.getAttribute("data-id-solicitud") || "";
        const idServicio = boton.getAttribute("data-id-servicio") || "";
        const idDepartamento = boton.getAttribute("data-id-departamento") || "";
        const nombre = boton.getAttribute("data-nombre") || "";
        const apellido = boton.getAttribute("data-apellido") || "";
        const rut = boton.getAttribute("data-rut") || "";
        const telefono = boton.getAttribute("data-telefono") || "";
        const correo = boton.getAttribute("data-correo") || "";
        const fecha = boton.getAttribute("data-fecha") || "";
        const servicioNombre = boton.getAttribute("data-servicio-nombre") || "";

        if (requestAssignId) requestAssignId.value = idSolicitud;
        if (requestAssignServiceId) requestAssignServiceId.value = idServicio;
        if (requestAssignDepartmentId) requestAssignDepartmentId.value = idDepartamento;
        if (requestAssignName) requestAssignName.value = nombre;
        if (requestAssignSurname) requestAssignSurname.value = apellido;
        if (requestAssignRut) requestAssignRut.value = rut;
        if (requestAssignPhone) requestAssignPhone.value = telefono;
        if (requestAssignEmail) requestAssignEmail.value = correo;

        const nombreCompleto = `${nombre} ${apellido}`.trim();
        if (requestAssignPatientName) requestAssignPatientName.textContent = nombreCompleto || "—";
        if (requestAssignPatientRut) requestAssignPatientRut.textContent = rut || "—";
        if (requestAssignPatientPhone) requestAssignPatientPhone.textContent = telefono || "—";
        if (requestAssignPatientEmail) requestAssignPatientEmail.textContent = correo || "—";
        if (requestAssignServiceName) requestAssignServiceName.textContent = servicioNombre || "—";

        const hoy = obtenerFechaLocal();
        if (requestAssignDate) {
            requestAssignDate.min = hoy;
            requestAssignDate.value = (fecha && fecha >= hoy) ? fecha : hoy;
        }

        if (btnConfirmRequestAssignment) {
            btnConfirmRequestAssignment.disabled = true;
            btnConfirmRequestAssignment.textContent = "Asignar hora";
        }

        requestAssignModal.hidden = false;
        requestAssignModal.style.display = "flex";

        cargarHorariosParaSolicitud();
    }

    function cerrarModalAsignarSolicitud() {
        if (requestAssignModal) {
            requestAssignModal.hidden = true;
            requestAssignModal.style.display = "none";
        }

        if (requestAssignHour) {
            requestAssignHour.innerHTML = '<option value="">Seleccione una fecha</option>';
            requestAssignHour.disabled = true;
        }

        if (requestAssignHourMessage) {
            requestAssignHourMessage.textContent = "";
        }

        if (btnConfirmRequestAssignment) {
            btnConfirmRequestAssignment.disabled = true;
            btnConfirmRequestAssignment.textContent = "Asignar hora";
        }
    }

   
    let consultaHorariosSolicitud = 0;

    async function cargarHorariosParaSolicitud() {

        if (
            !requestAssignServiceId ||
            !requestAssignDate ||
            !requestAssignHour
        ) {
            return;
        }

        const numeroConsulta = ++consultaHorariosSolicitud;

        const idServicio = requestAssignServiceId.value;
        const fecha = requestAssignDate.value;

        if (!idServicio || !fecha) {

            requestAssignHour.innerHTML =
                '<option value="">Seleccione una fecha</option>';

            requestAssignHour.disabled = true;

            if (btnConfirmRequestAssignment) {
                btnConfirmRequestAssignment.disabled = true;
            }

            return;
        }

        requestAssignHour.innerHTML =
            '<option value="">Cargando cupos disponibles...</option>';

        requestAssignHour.disabled = true;

        if (btnConfirmRequestAssignment) {
            btnConfirmRequestAssignment.disabled = true;
        }

        if (requestAssignHourMessage) {
            requestAssignHourMessage.textContent = "";
        }

        try {

            const formData = new FormData();

            formData.append("accion", "horarios");
            formData.append("id_servicio", idServicio);
            formData.append("fecha", fecha);

            const respuesta = await fetch(API_URL, {
                method: "POST",
                body: formData,
                cache: "no-store"
            });

            const resultado = await leerRespuestaJSON(respuesta);

            if (
                !respuesta.ok ||
                resultado.ok !== true
            ) {

                throw new Error(
                    resultado.mensaje ||
                    "No fue posible obtener los horarios."
                );
            }

            if (numeroConsulta !== consultaHorariosSolicitud) {
                return;
            }

            /*
             * La API devuelve horarios directamente
             * en resultado.horarios.
             *
             * No dentro de resultado.datos.horarios.
             */

            const horarios = Array.isArray(resultado.horarios)
                ? resultado.horarios
                : [];

            /*
             * Filtrar horarios realmente disponibles.
             */

            const disponibles = horarios.filter(function (h) {

                const idHorario = Number(h.id_horario);

                const hora = normalizarHora(h.hora);

                return (
                    normalizarDisponibilidad(h) &&
                    Number.isInteger(idHorario) &&
                    idHorario > 0 &&
                    hora !== ""
                );
            });

            requestAssignHour.innerHTML = "";

            if (disponibles.length === 0) {

                const optVacio = document.createElement("option");

                optVacio.value = "";

                optVacio.textContent =
                    "No hay cupos disponibles para esta fecha";

                requestAssignHour.appendChild(optVacio);

                requestAssignHour.disabled = true;

                if (btnConfirmRequestAssignment) {
                    btnConfirmRequestAssignment.disabled = true;
                }

                if (requestAssignHourMessage) {

                    requestAssignHourMessage.textContent =
                        "No hay cupos disponibles en esta fecha. Seleccione otra fecha.";

                    requestAssignHourMessage.style.color = "#dc2626";
                }

                return;
            }

            /*
             * Opción inicial.
             */

            const optPlaceholder = document.createElement("option");

            optPlaceholder.value = "";

            optPlaceholder.textContent =
                "Seleccione un horario disponible";

            requestAssignHour.appendChild(optPlaceholder);

            /*
             * Agregar cada cupo disponible.
             */

            disponibles.forEach(function (h) {

                const opt = document.createElement("option");

                const idHorario = Number(h.id_horario);

                const hora = normalizarHora(h.hora);

                opt.value = String(idHorario);

                opt.dataset.hora = hora;

                opt.textContent = h.hora_fin
                    ? `${hora} - ${normalizarHora(h.hora_fin)} hrs`
                    : `${hora} hrs`;

                requestAssignHour.appendChild(opt);
            });

            requestAssignHour.disabled = false;

            if (btnConfirmRequestAssignment) {
                btnConfirmRequestAssignment.disabled = true;
            }

            if (requestAssignHourMessage) {

                requestAssignHourMessage.textContent =
                    `${disponibles.length} cupo(s) disponible(s).`;

                requestAssignHourMessage.style.color = "#15803d";
            }

            console.log(
                "Cupos disponibles para asignación:",
                disponibles
            );

        } catch (error) {

            if (numeroConsulta !== consultaHorariosSolicitud) {
                return;
            }

            console.error(
                "Error al cargar horarios para solicitud:",
                error
            );

            requestAssignHour.innerHTML =
                '<option value="">Error al cargar cupos</option>';

            requestAssignHour.disabled = true;

            if (btnConfirmRequestAssignment) {
                btnConfirmRequestAssignment.disabled = true;
            }

            if (requestAssignHourMessage) {

                requestAssignHourMessage.textContent =
                    error.message;

                requestAssignHourMessage.style.color = "#dc2626";
            }

        }
    }



    async function confirmarAsignacionSolicitud() {
        if (!requestAssignId || !requestAssignServiceId || !requestAssignDate || !requestAssignHour) {
            return;
        }

        const idSolicitud = requestAssignId.value;
        const idServicio = requestAssignServiceId.value;
        const fecha = requestAssignDate.value;
        const idHorario = requestAssignHour.value;

        const optSeleccionada = requestAssignHour.selectedOptions[0];
        const hora = optSeleccionada ? optSeleccionada.getAttribute("data-hora") : "";

        const nombre = requestAssignName ? requestAssignName.value : "";
        const apellido = requestAssignSurname ? requestAssignSurname.value : "";
        const nombreCompleto = `${nombre} ${apellido}`.trim();
        const rut = requestAssignRut ? requestAssignRut.value : "";
        const telefono = requestAssignPhone ? requestAssignPhone.value : "";
        const correo = requestAssignEmail ? requestAssignEmail.value : "";

        if (!idSolicitud || !idHorario || !idServicio || !fecha || !hora) {
            showNotification("Por favor seleccione un cupo disponible para asignar la hora.", "error");
            return;
        }

        if (btnConfirmRequestAssignment) {
            btnConfirmRequestAssignment.disabled = true;
            btnConfirmRequestAssignment.textContent = "Asignando...";
        }

        try {
            // 1. Crear la reserva mediante api/crear_reserva.php
            const formDataReserva = new FormData();
            formDataReserva.append("modo", "funcionario");
            formDataReserva.append("id_servicio", idServicio);
            formDataReserva.append("id_horario", idHorario);
            formDataReserva.append("fecha", fecha);
            formDataReserva.append("hora", hora);
            formDataReserva.append("nombre", nombreCompleto);
            formDataReserva.append("rut", rut);
            formDataReserva.append("tipo_identificacion", "rut");
            formDataReserva.append("telefono", telefono);
            if (correo) {
                formDataReserva.append("correo", correo);
            }
            formDataReserva.append("observacion", `Asignada desde solicitud de hora #${idSolicitud}`);

            const respuestaReserva = await fetch(API_CREAR_URL, {
                method: "POST",
                body: formDataReserva,
                cache: "no-store"
            });

            const resultadoReserva = await leerRespuestaJSON(respuestaReserva);

            if (!resultadoReserva.ok) {
                throw new Error(resultadoReserva.mensaje || "No fue posible crear la reserva.");
            }

            // 2. Marcar la solicitud como atendida en api/gestionar_solicitud_horas.php
            const formDataSolicitud = new FormData();
            formDataSolicitud.append("accion", "marcar_atendida");
            formDataSolicitud.append("id_solicitud", idSolicitud);
            formDataSolicitud.append("id_horario", idHorario);

            const respuestaSolicitud = await fetch(API_SOLICITUDES_URL, {
                method: "POST",
                body: formDataSolicitud,
                cache: "no-store"
            });

            const resultadoSolicitud = await leerRespuestaJSON(respuestaSolicitud);

            if (!resultadoSolicitud.ok) {
                throw new Error(resultadoSolicitud.mensaje || resultadoSolicitud.error || "La reserva fue creada, pero no se pudo actualizar el estado de la solicitud.");
            }

            showNotification("Hora asignada correctamente. La solicitud quedó marcada como atendida.", "success");
            cerrarModalAsignarSolicitud();

            setTimeout(function () {
                window.location.reload();
            }, 800);

        } catch (error) {
            console.error("Error al asignar hora:", error);
            showNotification(error.message, "error");
            if (btnConfirmRequestAssignment) {
                btnConfirmRequestAssignment.disabled = false;
                btnConfirmRequestAssignment.textContent = "Asignar hora";
            }
        }
    }

    async function procesarRechazoSolicitud(idSolicitud) {
        if (!idSolicitud) {
            return;
        }

        if (!confirm("¿Está seguro de que desea rechazar esta solicitud de hora?")) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append("accion", "rechazar");
            formData.append("id_solicitud", idSolicitud);

            const respuesta = await fetch(API_SOLICITUDES_URL, {
                method: "POST",
                body: formData,
                cache: "no-store"
            });

            const resultado = await leerRespuestaJSON(respuesta);

            if (!resultado.ok) {
                throw new Error(resultado.mensaje || resultado.error || "No fue posible rechazar la solicitud.");
            }

            showNotification("La solicitud fue rechazada correctamente.", "success");

            setTimeout(function () {
                window.location.reload();
            }, 800);

        } catch (error) {
            console.error("Error al rechazar solicitud:", error);
            showNotification(error.message, "error");
        }
    }

    async function procesarReaperturaSolicitud(idSolicitud) {
        if (!idSolicitud) {
            return;
        }

        if (!confirm("¿Desea reabrir esta solicitud y dejarla como pendiente nuevamente?")) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append("accion", "reabrir");
            formData.append("id_solicitud", idSolicitud);

            const respuesta = await fetch(API_SOLICITUDES_URL, {
                method: "POST",
                body: formData,
                cache: "no-store"
            });

            const resultado = await leerRespuestaJSON(respuesta);

            if (!resultado.ok) {
                throw new Error(resultado.mensaje || resultado.error || "No fue posible reabrir la solicitud.");
            }

            showNotification("La solicitud volvió a quedar pendiente.", "success");

            setTimeout(function () {
                window.location.reload();
            }, 800);

        } catch (error) {
            console.error("Error al reabrir solicitud:", error);
            showNotification(error.message, "error");
        }
    }

    // Delegación de eventos para solicitudes de horas
    document.addEventListener("click", function (event) {
        const objetivo = event.target instanceof Element ? event.target : null;
        if (!objetivo) {
            return;
        }

        // 1. Botón Asignar Hora
        const btnAsignar = objetivo.closest("[data-solicitud-asignar]");
        if (btnAsignar) {
            event.preventDefault();
            abrirModalAsignarSolicitud(btnAsignar);
            return;
        }

        // 2. Botón Rechazar o Reabrir
        const btnAccion = objetivo.closest("[data-solicitud-accion]");
        if (btnAccion) {
            event.preventDefault();
            const accion = btnAccion.getAttribute("data-solicitud-accion");
            const idSolicitud = btnAccion.getAttribute("data-id-solicitud");

            if (accion === "rechazar") {
                procesarRechazoSolicitud(idSolicitud);
            } else if (accion === "reabrir") {
                procesarReaperturaSolicitud(idSolicitud);
            }
            return;
        }

        // 3. Cerrar popup de solicitud
        if (
            objetivo.closest("[data-close-request-modal]") ||
            objetivo.classList.contains("request-modal-overlay")
        ) {
            event.preventDefault();
            cerrarModalAsignarSolicitud();
            return;
        }
    });

    if (requestAssignDate) {
        requestAssignDate.addEventListener("change", function () {
            cargarHorariosParaSolicitud();
        });
    }

    if (requestAssignHour) {
        requestAssignHour.addEventListener("change", function () {
            if (btnConfirmRequestAssignment) {
                btnConfirmRequestAssignment.disabled = !requestAssignHour.value;
            }
        });
    }

    if (btnConfirmRequestAssignment) {
        btnConfirmRequestAssignment.addEventListener("click", function (event) {
            event.preventDefault();
            confirmarAsignacionSolicitud();
        });
    }

});

