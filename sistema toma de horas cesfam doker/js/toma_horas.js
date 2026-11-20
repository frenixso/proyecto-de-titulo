
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('createReservationForm');

    if (!form) {
        return;
    }

    /* =========================================================
       1. CAMPOS PRINCIPALES
    ========================================================= */

    const nombre = document.getElementById('createNombre');
    const rut = document.getElementById('createRut');
    const telefono = document.getElementById('createTelefono');
    const correo = document.getElementById('createCorreo');

    const departamento = document.getElementById('createDepartamento');
    const servicio = document.getElementById('createServicio');
    const fecha = document.getElementById('createFecha');
    const horario = document.getElementById('createHorario');

    const identificacionLabel =
        document.getElementById('identificacionLabel');

    const identificacionRadios =
        document.querySelectorAll(
            'input[name="tipo_identificacion"]'
        );

    /* =========================================================
       2. ERRORES
    ========================================================= */

    const errores = {
        nombre: document.getElementById('errorNombre'),
        rut: document.getElementById('errorRut'),
        telefono: document.getElementById('errorTelefono'),
        correo: document.getElementById('errorCorreo'),
        departamento: document.getElementById('errorDepartamento'),
        servicio: document.getElementById('errorServicio'),
        fecha: document.getElementById('errorFecha'),
        horario: document.getElementById('errorHorario')
    };

    /* =========================================================
       3. RESUMEN
    ========================================================= */

    const previewNombre =
        document.getElementById('previewNombre');

    const previewIdentificacion =
        document.getElementById('previewIdentificacion');

    const previewDepartamento =
        document.getElementById('previewDepartamento');

    const previewServicio =
        document.getElementById('previewServicio');

    const previewFecha =
        document.getElementById('previewFecha');

    const previewHora =
        document.getElementById('previewHora');

    /* =========================================================
       4. NOTIFICACIÓN
    ========================================================= */

    const notification =
        document.getElementById('notification');

    const notificationMessage =
        document.getElementById('notificationMessage');

    /* =========================================================
       5. POPUP DE RESERVA
    ========================================================= */

    const reservationModal =
        document.getElementById('reservationModal');

    const reservationModalOverlay =
        document.getElementById('reservationModalOverlay');

    const reservationModalIcon =
        document.getElementById('reservationModalIcon');

    const reservationModalTitle =
        document.getElementById('reservationModalTitle');

    const reservationModalMessage =
        document.getElementById('reservationModalMessage');

    const reservationModalButton =
        document.getElementById('reservationModalButton');

    /* =========================================================
       6. SOLICITUD DE HORAS / LISTA DE ESPERA
    ========================================================= */

    const solicitudContainer =
        document.getElementById('solicitudEsperaContainer');

    const solicitudOverlay =
        document.getElementById('solicitudEsperaOverlay');

    const botonCerrarSolicitud =
        document.getElementById('btnCerrarSolicitudHoras');

    const formSolicitud =
        document.getElementById('formSolicitudHoras');

    const solicitudRut =
        document.getElementById('solicitudRut');

    const solicitudNombre =
        document.getElementById('solicitudNombre');

    const solicitudApellido =
        document.getElementById('solicitudApellido');

    const solicitudTelefono =
        document.getElementById('solicitudTelefono');

    const solicitudCorreo =
        document.getElementById('solicitudCorreo');

    const solicitudDepartamento =
        document.getElementById('solicitudDepartamento');

    const solicitudServicio =
        document.getElementById('solicitudServicio');

    const solicitudFecha =
        document.getElementById('solicitudFecha');

    const solicitudResultado =
        document.getElementById('solicitudHorasResultado');

    const botonSolicitud =
        document.getElementById('btnEnviarSolicitudHoras');

    let solicitudRegistrada = false;
    let cargandoHorarios = false;
    let numeroConsultaHorarios = 0;

    /* =========================================================
       7. FECHA MÍNIMA
    ========================================================= */

    const hoy = new Date();

    const año = hoy.getFullYear();

    const mes =
        String(hoy.getMonth() + 1).padStart(2, '0');

    const dia =
        String(hoy.getDate()).padStart(2, '0');

    fecha.min = año + '-' + mes + '-' + dia;

    /* =========================================================
       8. TIPO DE IDENTIFICACIÓN
    ========================================================= */

    function obtenerTipoIdentificacion() {

        const seleccionado =
            document.querySelector(
                'input[name="tipo_identificacion"]:checked'
            );

        return seleccionado
            ? seleccionado.value
            : 'rut';
    }

    function actualizarTipoIdentificacion() {

        const tipo = obtenerTipoIdentificacion();

        ocultarError(errores.rut);

        rut.classList.remove('field-invalid');

        rut.value = '';

        if (tipo === 'rut') {

            identificacionLabel.innerHTML =
                'RUT <span class="required-mark">*</span>';

            rut.placeholder = 'Ej: 12.345.678-5';
            rut.maxLength = 12;
            rut.inputMode = 'text';

        } else if (tipo === 'rut_extranjero') {

            identificacionLabel.innerHTML =
                'Identificación extranjera <span class="required-mark">*</span>';

            rut.placeholder =
                'Ingrese su identificación extranjera';

            rut.maxLength = 20;
            rut.inputMode = 'text';

        } else if (tipo === 'pasaporte') {

            identificacionLabel.innerHTML =
                'Número de pasaporte <span class="required-mark">*</span>';

            rut.placeholder =
                'Ingrese su número de pasaporte';

            rut.maxLength = 20;
            rut.inputMode = 'text';
        }

        actualizarEstadoFormulario();
        actualizarResumen();
    }

    identificacionRadios.forEach(function (radio) {

        radio.addEventListener(
            'change',
            actualizarTipoIdentificacion
        );

    });

    /* =========================================================
       9. FORMATO DE RUT CHILENO
    ========================================================= */

    function formatearRut(valor) {

        let limpio = valor
            .toUpperCase()
            .replace(/[^0-9K]/g, '');

        if (limpio.length > 9) {
            limpio = limpio.substring(0, 9);
        }

        if (limpio.length <= 1) {
            return limpio;
        }

        const cuerpo =
            limpio.substring(0, limpio.length - 1);

        const dv =
            limpio.substring(limpio.length - 1);

        let cuerpoFormateado = '';
        let contador = 0;

        for (let i = cuerpo.length - 1; i >= 0; i--) {

            cuerpoFormateado =
                cuerpo.charAt(i) + cuerpoFormateado;

            contador++;

            if (contador === 3 && i !== 0) {

                cuerpoFormateado =
                    '.' + cuerpoFormateado;

                contador = 0;
            }
        }

        return cuerpoFormateado + '-' + dv;
    }

    function rutEsValido(rutIngresado) {

        const limpio = rutIngresado
            .toUpperCase()
            .replace(/\./g, '')
            .replace(/-/g, '')
            .replace(/\s/g, '');

        if (!/^[0-9]{1,8}[0-9K]$/.test(limpio)) {
            return false;
        }

        const cuerpo =
            limpio.substring(0, limpio.length - 1);

        const dvIngresado =
            limpio.substring(limpio.length - 1);

        let suma = 0;
        let multiplicador = 2;

        for (let i = cuerpo.length - 1; i >= 0; i--) {

            suma +=
                parseInt(cuerpo.charAt(i), 10) *
                multiplicador;

            multiplicador++;

            if (multiplicador > 7) {
                multiplicador = 2;
            }
        }

        const resultado = 11 - (suma % 11);

        let dvCalculado;

        if (resultado === 11) {

            dvCalculado = '0';

        } else if (resultado === 10) {

            dvCalculado = 'K';

        } else {

            dvCalculado = String(resultado);
        }

        return dvCalculado === dvIngresado;
    }

    rut.addEventListener('input', function () {

        const tipo = obtenerTipoIdentificacion();

        if (tipo === 'rut') {

            rut.value = formatearRut(rut.value);

        } else {

            rut.value = rut.value
                .toUpperCase()
                .replace(/[^A-Z0-9\-]/g, '');
        }

        actualizarResumen();

        if (rut.value.trim() !== '') {
            ocultarError(errores.rut);
        }

        actualizarEstadoFormulario();
    });

    /* =========================================================
       10. VALIDACIÓN DE DATOS PERSONALES
    ========================================================= */

    function validarDatosPersonales(mostrarMensajes) {

        if (typeof mostrarMensajes === 'undefined') {
            mostrarMensajes = true;
        }

        let valido = true;

        const tipo = obtenerTipoIdentificacion();

        /* NOMBRE */

        if (nombre.value.trim() === '') {

            valido = false;

            if (mostrarMensajes) {

                mostrarError(
                    errores.nombre,
                    'Campo obligatorio.'
                );
            }

        } else {

            ocultarError(errores.nombre);
        }

        /* IDENTIFICACIÓN */

        const valorIdentificacion = rut.value.trim();

        if (valorIdentificacion === '') {

            valido = false;

            if (mostrarMensajes) {

                mostrarError(
                    errores.rut,
                    'Campo obligatorio.'
                );
            }

        } else if (
            tipo === 'rut' &&
            !rutEsValido(valorIdentificacion)
        ) {

            valido = false;

            if (mostrarMensajes) {

                mostrarError(
                    errores.rut,
                    'Ingrese un RUT válido.'
                );
            }

        } else {

            ocultarError(errores.rut);
        }

        /* TELÉFONO */

        if (telefono.value.trim() === '') {

            valido = false;

            if (mostrarMensajes) {

                mostrarError(
                    errores.telefono,
                    'Campo obligatorio.'
                );
            }

        } else {

            ocultarError(errores.telefono);
        }

        /* CORREO ORIGINAL: OPCIONAL */

        const correoValor = correo.value.trim();

        if (
            correoValor !== '' &&
            !correo.validity.valid
        ) {

            valido = false;

            if (mostrarMensajes) {

                mostrarError(
                    errores.correo,
                    'Ingrese un correo válido.'
                );
            }

        } else {

            ocultarError(errores.correo);
        }

        return valido;
    }

    /* =========================================================
       11. ESTADO DEL FORMULARIO PRINCIPAL
    ========================================================= */

    function actualizarEstadoFormulario() {

        const datosCompletos =
            validarDatosPersonales(false);

        departamento.disabled = !datosCompletos;

        servicio.disabled =
            !datosCompletos ||
            departamento.value === '';

        fecha.disabled =
            !datosCompletos ||
            departamento.value === '' ||
            servicio.value === '';

        if (
            !datosCompletos ||
            departamento.value === '' ||
            servicio.value === '' ||
            fecha.value === ''
        ) {

            horario.disabled = true;

            horario.innerHTML =
                '<option value="">Seleccione servicio y fecha</option>';
        }
    }

    /* =========================================================
       12. MOSTRAR Y OCULTAR ERRORES
    ========================================================= */

    function mostrarError(elemento, mensaje) {

        if (!elemento) {
            return;
        }

        elemento.textContent = mensaje;

        elemento.classList.add('active');

        const input = elemento.previousElementSibling;

        if (
            input &&
            (
                input.tagName === 'INPUT' ||
                input.tagName === 'SELECT' ||
                input.tagName === 'TEXTAREA'
            )
        ) {

            input.classList.add('field-invalid');
        }
    }

    function ocultarError(elemento) {

        if (!elemento) {
            return;
        }

        elemento.classList.remove('active');

        const input = elemento.previousElementSibling;

        if (
            input &&
            (
                input.tagName === 'INPUT' ||
                input.tagName === 'SELECT' ||
                input.tagName === 'TEXTAREA'
            )
        ) {

            input.classList.remove('field-invalid');
        }
    }

    /* =========================================================
       13. ESCUCHAR CAMPOS PERSONALES
    ========================================================= */

    [
        nombre,
        telefono,
        correo
    ].forEach(function (campo) {

        campo.addEventListener('input', function () {

            actualizarEstadoFormulario();
            actualizarResumen();

        });

    });

    /* =========================================================
       14. PROTEGER SELECTORES
    ========================================================= */

    departamento.addEventListener('mousedown', function (event) {

        if (departamento.disabled) {

            event.preventDefault();

            if (!validarDatosPersonales(true)) {

                mostrarNotificacion(
                    'Complete correctamente sus datos personales.',
                    'error'
                );
            }
        }

    });

    servicio.addEventListener('mousedown', function (event) {

        if (servicio.disabled) {

            event.preventDefault();

            if (!validarDatosPersonales(true)) {

                mostrarNotificacion(
                    'Complete correctamente sus datos personales.',
                    'error'
                );

            } else if (departamento.value === '') {

                mostrarError(
                    errores.departamento,
                    'Seleccione un departamento.'
                );
            }
        }

    });

    fecha.addEventListener('mousedown', function (event) {

        if (fecha.disabled) {

            event.preventDefault();

            if (!validarDatosPersonales(true)) {

                mostrarNotificacion(
                    'Complete correctamente sus datos personales.',
                    'error'
                );

            } else if (departamento.value === '') {

                mostrarError(
                    errores.departamento,
                    'Seleccione un departamento.'
                );

            } else if (servicio.value === '') {

                mostrarError(
                    errores.servicio,
                    'Seleccione un servicio.'
                );
            }
        }

    });

    horario.addEventListener('mousedown', function (event) {

        if (horario.disabled) {

            event.preventDefault();

            if (!validarDatosPersonales(true)) {

                mostrarNotificacion(
                    'Complete correctamente sus datos personales.',
                    'error'
                );

            } else if (servicio.value === '') {

                mostrarError(
                    errores.servicio,
                    'Seleccione un servicio.'
                );

            } else if (fecha.value === '') {

                mostrarError(
                    errores.fecha,
                    'Seleccione una fecha.'
                );
            }
        }

    });

    /* =========================================================
       15. CARGAR DEPARTAMENTOS
    ========================================================= */

    async function cargarDepartamentos() {

        try {

            const respuesta = await fetch(
                '../api/obtener_departamentos.php'
            );

            const texto = await respuesta.text();

            let datos;

            try {

                datos = JSON.parse(texto);

            } catch (errorJSON) {

                console.error(
                    'obtener_departamentos.php no devolvió JSON válido:',
                    texto
                );

                departamento.innerHTML =
                    '<option value="">Error al cargar departamentos</option>';

                departamento.disabled = true;

                return;
            }

            departamento.innerHTML = '';

            if (
                !datos.ok ||
                !Array.isArray(datos.departamentos)
            ) {

                departamento.innerHTML =
                    '<option value="">No se pudieron cargar los departamentos</option>';

                departamento.disabled = true;

                return;
            }

            const opcionInicial = document.createElement('option');

            opcionInicial.value = '';
            opcionInicial.textContent =
                'Seleccione un departamento';

            departamento.appendChild(opcionInicial);

            datos.departamentos.forEach(function (item) {

                const opcion = document.createElement('option');

                opcion.value = item.id_departamento;
                opcion.textContent = item.nombre;

                if (!item.seleccionable) {
                    opcion.disabled = true;
                }

                opcion.dataset.servicios =
                    JSON.stringify(item.servicios || []);

                departamento.appendChild(opcion);

            });

            actualizarEstadoFormulario();

        } catch (error) {

            console.error(
                'Error cargando departamentos:',
                error
            );

            departamento.innerHTML =
                '<option value="">No se pudieron cargar los departamentos</option>';

            departamento.disabled = true;
        }
    }

    /* =========================================================
       16. DEPARTAMENTO
    ========================================================= */

    departamento.addEventListener('change', function () {

        prepararNuevaBusqueda();

        numeroConsultaHorarios++;

        ocultarError(errores.departamento);

        servicio.innerHTML =
            '<option value="">Seleccione un servicio</option>';

        servicio.value = '';

        fecha.value = '';

        horario.innerHTML =
            '<option value="">Seleccione servicio y fecha</option>';

        horario.disabled = true;

        const opcionSeleccionada =
            departamento.options[departamento.selectedIndex];

        if (
            !opcionSeleccionada ||
            departamento.value === ''
        ) {

            servicio.disabled = true;
            fecha.disabled = true;

            actualizarResumen();
            actualizarEstadoFormulario();

            return;
        }

        let servicios = [];

        try {

            servicios = JSON.parse(
                opcionSeleccionada.dataset.servicios || '[]'
            );

        } catch (errorJSON) {

            console.error(
                'No se pudieron interpretar los servicios:',
                errorJSON
            );

            servicios = [];
        }

        servicios.forEach(function (item) {

            const opcion = document.createElement('option');

            opcion.value = item.id_servicio;
            opcion.textContent = item.nombre;

            opcion.dataset.idServicio = item.id_servicio;

            if (
                item.descripcion !== null &&
                item.descripcion !== ''
            ) {

                opcion.title = item.descripcion;
            }

            servicio.appendChild(opcion);

        });

        servicio.disabled = servicios.length === 0;

        fecha.disabled = true;

        actualizarResumen();
        actualizarEstadoFormulario();

    });

    /* =========================================================
       17. SERVICIO
    ========================================================= */

    servicio.addEventListener('change', function () {

        prepararNuevaBusqueda();

        numeroConsultaHorarios++;

        ocultarError(errores.servicio);

        fecha.value = '';

        horario.innerHTML =
            '<option value="">Seleccione servicio y fecha</option>';

        horario.disabled = true;

        fecha.disabled = servicio.value === '';

        /* AVISOS DE VACUNAS VETERINARIAS */

        if (servicio.value !== '') {

            const opcionServicio =
                servicio.options[servicio.selectedIndex];

            const nombreServicio = opcionServicio
                ? opcionServicio.textContent.toLowerCase().trim()
                : '';

            const esVacunaSextuple =
                nombreServicio.indexOf('séxtuple') !== -1 ||
                nombreServicio.indexOf('sextuple') !== -1;

            const esVacunaOctuple =
                nombreServicio.indexOf('óctuple') !== -1 ||
                nombreServicio.indexOf('octuple') !== -1;

            if (esVacunaSextuple) {

                mostrarAvisoVacuna('séxtuple');

            } else if (esVacunaOctuple) {

                mostrarAvisoVacuna('óctuple');
            }
        }

        actualizarResumen();
        actualizarEstadoFormulario();

    });

    /* =========================================================
       18. FECHA
    ========================================================= */

    fecha.addEventListener('change', async function () {

        prepararNuevaBusqueda();

        ocultarError(errores.fecha);

        horario.innerHTML =
            '<option value="">Consultando disponibilidad...</option>';

        horario.disabled = true;

        actualizarResumen();

        if (
            servicio.value === '' ||
            fecha.value === ''
        ) {

            horario.innerHTML =
                '<option value="">Seleccione servicio y fecha</option>';

            return;
        }

        await cargarHorarios();
    });

    /* =========================================================
       19. FUNCIONES DE SOLICITUD DE HORAS
    ========================================================= */

    function normalizarDisponibilidad(valor) {

        return (
            valor === true ||
            valor === 1 ||
            valor === '1' ||
            valor === 'true' ||
            valor === 't'
        );
    }

    function mostrarResultadoSolicitud(mensaje, tipo) {

        if (!solicitudResultado) {
            return;
        }

        solicitudResultado.textContent = mensaje;

        solicitudResultado.className =
            'solicitud-espera-resultado ' + tipo;

        solicitudResultado.hidden = false;
    }

    /*
     * Copia los datos existentes sin reemplazar
     * información que la persona ya haya escrito
     * en el formulario de espera.
     */

    function completarDatosSolicitud() {

        if (!formSolicitud) {
            return;
        }

        if (solicitudDepartamento) {
            solicitudDepartamento.value = departamento.value;
        }

        if (solicitudServicio) {
            solicitudServicio.value = servicio.value;
        }

        if (solicitudFecha) {
            solicitudFecha.value = fecha.value;
        }

        /*
         * Solo se copia el identificador cuando
         * el tipo seleccionado sea RUT chileno.
         */

        if (
            solicitudRut &&
            obtenerTipoIdentificacion() === 'rut' &&
            !solicitudRut.value.trim()
        ) {

            solicitudRut.value = rut.value.trim();
        }

        if (
            solicitudTelefono &&
            !solicitudTelefono.value.trim()
        ) {

            solicitudTelefono.value =
                telefono.value.trim();
        }

        if (
            solicitudCorreo &&
            !solicitudCorreo.value.trim()
        ) {

            solicitudCorreo.value =
                correo.value.trim();
        }
    }

    /* =========================================================
       20. CONTROL DEL DESPLAZAMIENTO DE LA PÁGINA
    ========================================================= */

    function actualizarBloqueoScroll() {

        const solicitudAbierta = (
            solicitudContainer &&
            !solicitudContainer.hidden
        );

        const reservaAbierta = (
            reservationModal &&
            !reservationModal.hidden
        );

        if (solicitudAbierta || reservaAbierta) {

            document.body.classList.add('modal-open');

        } else {

            document.body.classList.remove('modal-open');
        }
    }

    /* =========================================================
       21. MOSTRAR MODAL DE SOLICITUD DE HORAS
    ========================================================= */

    function mostrarSolicitudEspera() {

        if (
            !solicitudContainer ||
            solicitudRegistrada
        ) {
            return;
        }

        if (
            !departamento.value ||
            !servicio.value ||
            !fecha.value
        ) {
            return;
        }

        completarDatosSolicitud();

        /*
         * Mostrar el modal.
         */

        solicitudContainer.hidden = false;

        solicitudContainer.setAttribute(
            'aria-hidden',
            'false'
        );

        actualizarBloqueoScroll();

        /*
         * Llevar el foco al primer campo del formulario.
         */

        window.requestAnimationFrame(function () {

            if (solicitudRut) {
                solicitudRut.focus();
            }

        });
    }

    /* =========================================================
       22. CERRAR MODAL DE SOLICITUD DE HORAS
    ========================================================= */

    function cerrarSolicitudEspera(devolverFoco) {

        if (!solicitudContainer) {
            return;
        }

        if (typeof devolverFoco === 'undefined') {
            devolverFoco = true;
        }

        solicitudContainer.hidden = true;

        solicitudContainer.setAttribute(
            'aria-hidden',
            'true'
        );

        actualizarBloqueoScroll();

        /*
         * Al cerrar manualmente, devolver el foco
         * al selector de fecha.
         */

        if (devolverFoco && fecha) {
            fecha.focus();
        }
    }

    /* =========================================================
       23. OCULTAR MODAL DE SOLICITUD DE HORAS
    ========================================================= */

    function ocultarSolicitudEspera() {

        cerrarSolicitudEspera(false);
    }

    /* =========================================================
       24. EVENTOS PARA CERRAR EL MODAL DE SOLICITUD
    ========================================================= */

    if (botonCerrarSolicitud) {

        botonCerrarSolicitud.addEventListener(
            'click',
            function () {

                cerrarSolicitudEspera(true);

            }
        );
    }

    if (solicitudOverlay) {

        solicitudOverlay.addEventListener(
            'click',
            function () {

                cerrarSolicitudEspera(true);

            }
        );
    }

    /* =========================================================
       25. PREPARAR NUEVA BÚSQUEDA
    ========================================================= */

    function prepararNuevaBusqueda() {

        ocultarSolicitudEspera();

        /*
         * Si ya se había enviado una solicitud y la persona
         * cambia el servicio o la fecha, se prepara el formulario
         * para una nueva solicitud.
         */

        if (solicitudRegistrada && formSolicitud) {

            formSolicitud.reset();

            Array.from(formSolicitud.elements).forEach(
                function (elemento) {

                    elemento.disabled = false;

                }
            );

            solicitudRegistrada = false;

            if (botonSolicitud) {

                botonSolicitud.disabled = false;

                botonSolicitud.textContent =
                    'Enviar solicitud';
            }

            if (solicitudResultado) {

                solicitudResultado.hidden = true;

                solicitudResultado.textContent = '';
            }
        }
    }

    /* =========================================================
       26. CARGAR HORARIOS
    ========================================================= */

    async function cargarHorarios() {

        const numeroConsulta = ++numeroConsultaHorarios;

        if (cargandoHorarios) {
            // La nueva consulta tendrá prioridad sobre la anterior.
            // No se bloquea la consulta nueva.
        }

        cargandoHorarios = true;

        ocultarSolicitudEspera();

        horario.innerHTML =
            '<option value="">Consultando disponibilidad...</option>';

        horario.disabled = true;

        try {

            const servicioConsultado = servicio.value;
            const fechaConsultada = fecha.value;

            const url =
                '../api/obtener_horarios.php' +
                '?servicio=' +
                encodeURIComponent(servicioConsultado) +
                '&fecha=' +
                encodeURIComponent(fechaConsultada);

            const respuesta = await fetch(url);

            const texto = await respuesta.text();

            let datos;

            try {

                datos = JSON.parse(texto);

            } catch (errorJSON) {

                console.error(
                    'obtener_horarios.php no devolvió JSON válido:',
                    texto
                );

                if (numeroConsulta !== numeroConsultaHorarios) {
                    return;
                }

                horario.innerHTML =
                    '<option value="">Error al consultar disponibilidad</option>';

                horario.disabled = true;

                mostrarNotificacion(
                    'No se pudo consultar la disponibilidad. Intenta nuevamente.',
                    'error'
                );

                return;
            }

            /*
             * Ignorar respuestas de consultas anteriores.
             */

            if (numeroConsulta !== numeroConsultaHorarios) {
                return;
            }

            /*
             * Una respuesta incorrecta del servidor NO debe
             * abrir la lista de espera.
             */

            if (
                !respuesta.ok ||
                !datos.ok ||
                !Array.isArray(datos.horarios)
            ) {

                horario.innerHTML =
                    '<option value="">No se pudo consultar la disponibilidad</option>';

                horario.disabled = true;

                mostrarNotificacion(
                    datos.error ||
                    datos.mensaje ||
                    'No se pudo consultar la disponibilidad de horarios.',
                    'error'
                );

                return;
            }

            /* LIMPIAR SELECTOR */

            horario.innerHTML = '';

            const opcionInicial =
                document.createElement('option');

            opcionInicial.value = '';

            opcionInicial.textContent =
                'Seleccione un horario';

            horario.appendChild(opcionInicial);

            /*
             * Contar únicamente los horarios que realmente
             * están disponibles.
             */

            const cuposDisponibles =
                datos.horarios.filter(function (item) {

                    return normalizarDisponibilidad(
                        item.disponible
                    );

                });

            /* AGREGAR HORARIOS AL SELECTOR */

            datos.horarios.forEach(function (item) {

                const opcion =
                    document.createElement('option');

                /*
                 * El valor del option es id_horario.
                 * La hora real se conserva en data-hora.
                 */

                opcion.value = String(item.id_horario || '');

                opcion.dataset.idHorario =
                    String(item.id_horario || '');

                opcion.dataset.hora =
                    String(item.hora || '');

                opcion.dataset.horaFin =
                    String(item.hora_fin || '');

                opcion.dataset.idServicioHorario =
                    String(
                        item.id_servicio_horario ||
                        item.id_servicio ||
                        servicioConsultado
                    );

                const estaDisponible =
                    normalizarDisponibilidad(item.disponible);

                opcion.textContent =
                    estaDisponible
                        ? (
                            item.hora_fin
                                ? item.hora + ' - ' + item.hora_fin
                                : item.hora
                        )
                        : (
                            item.hora +
                            ' — No disponible'
                        );

                opcion.disabled = !estaDisponible;

                horario.appendChild(opcion);

            });

            /* =============================================
               CASO 1: NO EXISTEN AGENDAS O CUPOS
            ============================================= */

            if (datos.horarios.length === 0) {

                horario.disabled = true;

                opcionInicial.textContent =
                    'Día sin posibilidad de tomar hora';

                ocultarSolicitudEspera();

                mostrarNotificacion(
                    datos.mensaje ||
                    'No existe una agenda publicada con cupos para la fecha seleccionada. Seleccione otro día.',
                    'info'
                );

                return;
            }

            /* =============================================
               CASO 2: HAY CUPOS, PERO TODOS OCUPADOS
            ============================================= */

            if (cuposDisponibles.length === 0) {

                horario.disabled = true;

                opcionInicial.textContent =
                    'No hay cupos disponibles';

                if (datos.permite_solicitud === true) {

                    mostrarSolicitudEspera();

                } else {

                    ocultarSolicitudEspera();

                    mostrarNotificacion(
                        datos.mensaje ||
                        'No hay cupos disponibles para esta fecha.',
                        'info'
                    );
                }

                return;
            }

            /* =============================================
               CASO 3: EXISTEN CUPOS DISPONIBLES
            ============================================= */

            horario.disabled = false;

            ocultarSolicitudEspera();

            if (notification) {
                notification.hidden = true;
            }

        } catch (error) {

            console.error(
                'Error consultando horarios:',
                error
            );

            if (numeroConsulta !== numeroConsultaHorarios) {
                return;
            }

            horario.innerHTML =
                '<option value="">Error al cargar horarios</option>';

            horario.disabled = true;

            ocultarSolicitudEspera();

            mostrarNotificacion(
                'Ocurrió un error de comunicación al consultar los horarios.',
                'error'
            );

        } finally {

            if (numeroConsulta === numeroConsultaHorarios) {
                cargandoHorarios = false;
            }
        }
    }

    /* =========================================================
       27. ENVÍO DE SOLICITUD DE HORAS
    ========================================================= */

    if (formSolicitud) {

        formSolicitud.addEventListener(
            'submit',
            async function (event) {

                event.preventDefault();

                if (!formSolicitud.reportValidity()) {
                    return;
                }

                completarDatosSolicitud();

                /* DATOS DEL SERVICIO Y FECHA */

                if (
                    !solicitudDepartamento.value ||
                    !solicitudServicio.value ||
                    !solicitudFecha.value
                ) {

                    mostrarResultadoSolicitud(
                        'Falta seleccionar un departamento, servicio o fecha.',
                        'error'
                    );

                    return;
                }

                /* CAMPOS OBLIGATORIOS */

                if (
                    !solicitudRut.value.trim() ||
                    !solicitudNombre.value.trim() ||
                    !solicitudApellido.value.trim() ||
                    !solicitudTelefono.value.trim() ||
                    !solicitudCorreo.value.trim()
                ) {

                    mostrarResultadoSolicitud(
                        'Todos los campos son obligatorios.',
                        'error'
                    );

                    return;
                }

                botonSolicitud.disabled = true;

                botonSolicitud.textContent =
                    'Enviando solicitud...';

                solicitudResultado.hidden = true;

                try {

                    const respuesta = await fetch(
                        '../api/crear_solicitud_horas.php',
                        {
                            method: 'POST',
                            body: new FormData(formSolicitud)
                        }
                    );

                    const texto = await respuesta.text();

                    let resultado;

                    try {

                        resultado = JSON.parse(texto);

                    } catch (errorJSON) {

                        console.error(
                            'Respuesta inválida de crear_solicitud_horas.php:',
                            texto
                        );

                        throw new Error(
                            'El servidor devolvió una respuesta no válida.'
                        );
                    }

                    if (!respuesta.ok || !resultado.ok) {

                        const mensaje =
                            resultado.mensaje ||
                            resultado.error ||
                            'No se pudo registrar la solicitud.';

                        mostrarResultadoSolicitud(
                            mensaje,
                            'error'
                        );

                        /*
                         * Si la disponibilidad cambió mientras
                         * se completaba el formulario, volver
                         * a consultar los horarios.
                         */

                        if (
                            respuesta.status === 409 &&
                            mensaje.toLowerCase().includes('todavía existen cupos disponibles')
                        ) {

                            ocultarSolicitudEspera();

                            await cargarHorarios();
                        }

                        botonSolicitud.disabled = false;

                        botonSolicitud.textContent =
                            'Enviar solicitud';

                        return;
                    }

                    /* SOLICITUD REGISTRADA */

                    solicitudRegistrada = true;

                    mostrarResultadoSolicitud(
                        resultado.mensaje ||
                        'Tu solicitud fue registrada correctamente.',
                        'exito'
                    );

                    botonSolicitud.textContent =
                        'Solicitud registrada';

                    /*
                     * Bloquear todos los campos para evitar
                     * que se envíe varias veces la misma solicitud.
                     *
                     * El modal permanece abierto para que la
                     * persona pueda leer el mensaje de éxito.
                     */

                    Array.from(formSolicitud.elements).forEach(
                        function (elemento) {

                            elemento.disabled = true;

                        }
                    );

                } catch (error) {

                    console.error(
                        'Error enviando solicitud:',
                        error
                    );

                    mostrarResultadoSolicitud(
                        error.message ||
                        'Ocurrió un error al enviar tu solicitud.',
                        'error'
                    );

                    botonSolicitud.disabled = false;

                    botonSolicitud.textContent =
                        'Enviar solicitud';
                }

            }
        );
    }

    /* =========================================================
       28. HORARIO SELECCIONADO
    ========================================================= */

    horario.addEventListener('change', function () {

        ocultarError(errores.horario);

        actualizarResumen();

    });

    /* =========================================================
       29. ACTUALIZAR RESUMEN
    ========================================================= */

    function actualizarResumen() {

        previewNombre.textContent =
            nombre.value.trim() || '-';

        const tipo = obtenerTipoIdentificacion();

        const identificacion = rut.value.trim();

        if (identificacion === '') {

            previewIdentificacion.textContent = '-';

        } else {

            let textoTipo = 'RUT';

            if (tipo === 'rut_extranjero') {

                textoTipo = 'RUT extranjero';

            } else if (tipo === 'pasaporte') {

                textoTipo = 'Pasaporte';
            }

            previewIdentificacion.textContent =
                textoTipo + ': ' + identificacion;
        }

        /* DEPARTAMENTO */

        if (departamento.value !== '') {

            const opcionDepartamento =
                departamento.options[departamento.selectedIndex];

            previewDepartamento.textContent =
                opcionDepartamento
                    ? opcionDepartamento.textContent
                    : '-';

        } else {

            previewDepartamento.textContent = '-';
        }

        /* SERVICIO */

        if (servicio.value !== '') {

            const opcionServicio =
                servicio.options[servicio.selectedIndex];

            previewServicio.textContent =
                opcionServicio
                    ? opcionServicio.textContent
                    : '-';

        } else {

            previewServicio.textContent = '-';
        }

        /* FECHA */

        if (fecha.value !== '') {

            const partes = fecha.value.split('-');

            previewFecha.textContent =
                partes[2] + '/' + partes[1] + '/' + partes[0];

        } else {

            previewFecha.textContent = '-';
        }

        /* HORA */

        const opcionHorario =
            horario.options[horario.selectedIndex];

        previewHora.textContent =
            opcionHorario && horario.value !== ''
                ? (
                    opcionHorario.dataset.horaFin
                        ? opcionHorario.dataset.hora +
                            ' - ' +
                            opcionHorario.dataset.horaFin
                        : opcionHorario.dataset.hora
                )
                : '-';
    }

    /* =========================================================
       30. MOSTRAR NOTIFICACIONES
    ========================================================= */

    function mostrarNotificacion(mensaje, tipo) {

        if (typeof tipo === 'undefined') {
            tipo = 'info';
        }

        if (!notification || !notificationMessage) {
            return;
        }

        notificationMessage.textContent = mensaje;

        notification.classList.remove(
            'notification-success',
            'notification-error'
        );

        if (tipo === 'success') {

            notification.classList.add(
                'notification-success'
            );

        } else if (tipo === 'error') {

            notification.classList.add(
                'notification-error'
            );
        }

        notification.hidden = false;
    }

    /* =========================================================
       31. POPUP DE CONFIRMACIÓN
    ========================================================= */

    function mostrarPopup(exito, titulo, mensaje, tipo) {

        if (typeof tipo === 'undefined') {
            tipo = exito ? 'success' : 'error';
        }

        reservationModal.classList.remove(
            'modal-success',
            'modal-error',
            'modal-info'
        );

        if (tipo === 'success') {

            reservationModal.classList.add('modal-success');

            reservationModalIcon.textContent = '✓';

        } else if (tipo === 'info') {

            reservationModal.classList.add('modal-info');

            reservationModalIcon.textContent = 'i';

        } else {

            reservationModal.classList.add('modal-error');

            reservationModalIcon.textContent = '!';
        }

        reservationModalTitle.textContent = titulo;

        reservationModalMessage.textContent = mensaje;

        reservationModal.hidden = false;

        actualizarBloqueoScroll();
    }

    function cerrarPopup() {

        reservationModal.hidden = true;

        actualizarBloqueoScroll();
    }

    reservationModalButton.addEventListener(
        'click',
        cerrarPopup
    );

    reservationModalOverlay.addEventListener(
        'click',
        cerrarPopup
    );

    /* =========================================================
       32. CERRAR MODALES CON ESCAPE
    ========================================================= */

    document.addEventListener('keydown', function (event) {

        if (event.key !== 'Escape') {
            return;
        }

        /*
         * Se cierra primero el modal de solicitud
         * cuando está abierto.
         */

        if (
            solicitudContainer &&
            !solicitudContainer.hidden
        ) {

            cerrarSolicitudEspera(true);

            return;
        }

        /*
         * Si no está abierto el modal de solicitud,
         * se comprueba el popup de reserva.
         */

        if (
            reservationModal &&
            !reservationModal.hidden
        ) {

            cerrarPopup();
        }

    });

    /* =========================================================
       33. AVISO DE VACUNAS VETERINARIAS
    ========================================================= */

    function mostrarAvisoVacuna(tipoVacuna) {

        let mensaje =
            'Debe comprar previamente la vacuna correspondiente y llevarla el día de la atención.';

        if (tipoVacuna === 'séxtuple') {

            mensaje =
                'Debe comprar previamente la vacuna séxtuple y llevarla el día de la atención.';

        } else if (tipoVacuna === 'óctuple') {

            mensaje =
                'Debe comprar previamente la vacuna óctuple y llevarla el día de la atención.';
        }

        mostrarPopup(
            false,
            'Importante antes de su reserva',
            mensaje,
            'info'
        );
    }

    /* =========================================================
       34. ENVÍO DEL FORMULARIO DE RESERVA NORMAL
    ========================================================= */

    form.addEventListener('submit', async function (event) {

        event.preventDefault();

        const datosPersonalesValidos =
            validarDatosPersonales(true);

        if (!datosPersonalesValidos) {

            mostrarNotificacion(
                'Complete correctamente sus datos personales.',
                'error'
            );

            return;
        }

        let formularioValido = true;

        /* DEPARTAMENTO */

        if (departamento.value === '') {

            formularioValido = false;

            mostrarError(
                errores.departamento,
                'Campo obligatorio.'
            );

        } else {

            ocultarError(errores.departamento);
        }

        /* SERVICIO */

        if (servicio.value === '') {

            formularioValido = false;

            mostrarError(
                errores.servicio,
                'Campo obligatorio.'
            );

        } else {

            ocultarError(errores.servicio);
        }

        /* FECHA */

        if (fecha.value === '') {

            formularioValido = false;

            mostrarError(
                errores.fecha,
                'Campo obligatorio.'
            );

        } else {

            ocultarError(errores.fecha);
        }

        /* HORARIO */

        if (
            horario.value === '' ||
            !horario.selectedOptions[0] ||
            !horario.selectedOptions[0].dataset.idHorario ||
            !horario.selectedOptions[0].dataset.hora
        ) {

            formularioValido = false;

            mostrarError(
                errores.horario,
                'Debe seleccionar un horario disponible.'
            );

        } else {

            ocultarError(errores.horario);
        }

        if (!formularioValido) {

            mostrarNotificacion(
                'Complete los datos de la solicitud.',
                'error'
            );

            return;
        }

        /*
         * OBTENER EL CUPO EXACTO SELECCIONADO
         *
         * El valor del select es id_horario.
         * La hora real se encuentra en data-hora.
         */

        const opcionHorario =
            horario.selectedOptions[0];

        const idHorario =
            opcionHorario.dataset.idHorario;

        const horaSeleccionada =
            opcionHorario.dataset.hora;

        const datos = new FormData(form);

        /*
         * DATOS DEL PACIENTE
         */

        datos.set(
            'nombre',
            nombre.value.trim()
        );

        datos.set(
            'rut',
            rut.value.trim()
        );

        datos.set(
            'tipo_identificacion',
            obtenerTipoIdentificacion()
        );

        datos.set(
            'telefono',
            telefono.value.trim()
        );

        datos.set(
            'correo',
            correo.value.trim()
        );

        /*
         * DATOS DE LA RESERVA
         */

        datos.set(
            'departamento',
            departamento.value
        );

        datos.set(
            'servicio',
            servicio.value
        );

        datos.set(
            'fecha',
            fecha.value
        );

        /*
         * Como el select guarda id_horario,
         * se envía la hora real por separado.
         */

        datos.set(
            'id_horario',
            idHorario
        );

        datos.set(
            'hora',
            horaSeleccionada
        );

        datos.set(
            'observacion',
            document.getElementById('createObservacion')?.value.trim() || ''
        );

        datos.set(
            'modo',
            'publico'
        );

        /*
         * EVITAR DOBLE ENVÍO
         */

        const botonSubmit =
            form.querySelector('button[type="submit"]');

        if (botonSubmit) {

            botonSubmit.disabled = true;

            botonSubmit.dataset.textoOriginal =
                botonSubmit.textContent;

            botonSubmit.textContent =
                'Reservando...';
        }

        try {

            const respuesta = await fetch(
                '../api/crear_reserva.php',
                {
                    method: 'POST',
                    body: datos
                }
            );

            const textoRespuesta =
                await respuesta.text();

            let resultado;

            try {

                resultado = JSON.parse(textoRespuesta);

            } catch (errorJSON) {

                const mensajeDepuracion =
                    'HTTP ' +
                    respuesta.status +
                    ' - El servidor no devolvió JSON válido.\n\n' +
                    'Respuesta del servidor:\n' +
                    textoRespuesta;

                console.error(
                    'Respuesta no válida:',
                    mensajeDepuracion
                );

                mostrarNotificacion(
                    mensajeDepuracion,
                    'error'
                );

                mostrarPopup(
                    false,
                    'Error del servidor',
                    mensajeDepuracion
                );

                return;
            }

            /* =============================================
               RESERVA CORRECTA
            ============================================= */

            if (resultado.ok) {

                mostrarNotificacion(
                    'Su reserva fue guardada correctamente.',
                    'success'
                );

                mostrarPopup(
                    true,
                    'Reserva validada',
                    resultado.mensaje ||
                    'Su reserva fue guardada correctamente.'
                );

                /*
                 * Volver a consultar los cupos.
                 * El cupo reservado ya no debe aparecer
                 * como disponible.
                 */

                await cargarHorarios();

            } else {

                const mensaje =
                    resultado.mensaje ||
                    'No se pudo completar su reserva.';

                mostrarNotificacion(
                    mensaje,
                    'error'
                );

                mostrarPopup(
                    false,
                    'No se pudo completar su reserva',
                    mensaje
                );

                /*
                 * Si el cupo dejó de estar disponible,
                 * actualizar el selector.
                 */

                await cargarHorarios();
            }

        } catch (error) {

            console.error(
                'Error real al crear reserva:',
                error
            );

            const mensaje =
                'ERROR DE COMUNICACIÓN:\n\n' +
                error.message;

            mostrarNotificacion(
                mensaje,
                'error'
            );

            mostrarPopup(
                false,
                'Error de comunicación',
                mensaje
            );

        } finally {

            if (botonSubmit) {

                botonSubmit.disabled = false;

                botonSubmit.textContent =
                    botonSubmit.dataset.textoOriginal ||
                    'Solicitar hora';
            }
        }

    });

    /* =========================================================
       35. ESTADO INICIAL
    ========================================================= */

    actualizarTipoIdentificacion();

    actualizarEstadoFormulario();

    actualizarResumen();

    cargarDepartamentos();

});
