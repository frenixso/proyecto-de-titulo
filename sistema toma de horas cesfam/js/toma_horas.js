document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('createReservationForm');

    if (!form) {
        return;
    }


    /* =========================================================
       CAMPOS
    ========================================================= */

    const nombre = document.getElementById('createNombre');
    const rut = document.getElementById('createRut');
    const telefono = document.getElementById('createTelefono');
    const correo = document.getElementById('createCorreo');

    const departamento =
        document.getElementById('createDepartamento');

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
       ERRORES
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
       RESUMEN
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
       NOTIFICACIÓN
    ========================================================= */

    const notification =
        document.getElementById('notification');

    const notificationMessage =
        document.getElementById('notificationMessage');


    /* =========================================================
       POPUP
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
       FECHA MÍNIMA
    ========================================================= */

    const hoy = new Date();

    const año = hoy.getFullYear();

    const mes =
        String(hoy.getMonth() + 1).padStart(2, '0');

    const dia =
        String(hoy.getDate()).padStart(2, '0');

    /*
     * Se utiliza concatenación normal para evitar
     * template literals.
     */
    fecha.min =
        año + '-' + mes + '-' + dia;


    /* =========================================================
       TIPO DE IDENTIFICACIÓN
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

        const tipo =
            obtenerTipoIdentificacion();

        ocultarError(errores.rut);

        rut.classList.remove('field-invalid');

        rut.value = '';


        if (tipo === 'rut') {

            identificacionLabel.innerHTML =
                'RUT <span class="required-mark">*</span>';

            rut.placeholder =
                'Ej: 12.345.678-5';

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
       FORMATO RUT CHILENO
    ========================================================= */

    function formatearRut(valor) {

        let limpio =
            valor
                .toUpperCase()
                .replace(/[^0-9K]/g, '');


        if (limpio.length > 9) {

            limpio =
                limpio.substring(0, 9);
        }


        if (limpio.length <= 1) {

            return limpio;
        }


        const cuerpo =
            limpio.substring(
                0,
                limpio.length - 1
            );

        const dv =
            limpio.substring(
                limpio.length - 1
            );


        let cuerpoFormateado = '';

        let contador = 0;


        for (
            let i = cuerpo.length - 1;
            i >= 0;
            i--
        ) {

            cuerpoFormateado =
                cuerpo.charAt(i) +
                cuerpoFormateado;

            contador++;


            if (
                contador === 3 &&
                i !== 0
            ) {

                cuerpoFormateado =
                    '.' +
                    cuerpoFormateado;

                contador = 0;
            }
        }


        return cuerpoFormateado + '-' + dv;
    }


    function rutEsValido(rutIngresado) {

        const limpio =
            rutIngresado
                .toUpperCase()
                .replace(/\./g, '')
                .replace(/-/g, '')
                .replace(/\s/g, '');


        if (
            !/^[0-9]{1,8}[0-9K]$/.test(limpio)
        ) {

            return false;
        }


        const cuerpo =
            limpio.substring(
                0,
                limpio.length - 1
            );

        const dvIngresado =
            limpio.substring(
                limpio.length - 1
            );


        let suma = 0;

        let multiplicador = 2;


        for (
            let i = cuerpo.length - 1;
            i >= 0;
            i--
        ) {

            suma +=
                parseInt(
                    cuerpo.charAt(i),
                    10
                ) *
                multiplicador;

            multiplicador++;


            if (multiplicador > 7) {

                multiplicador = 2;
            }
        }


        const resto =
            suma % 11;

        const resultado =
            11 - resto;


        let dvCalculado;


        if (resultado === 11) {

            dvCalculado = '0';

        } else if (resultado === 10) {

            dvCalculado = 'K';

        } else {

            dvCalculado =
                String(resultado);
        }


        return dvCalculado === dvIngresado;
    }


    rut.addEventListener(
        'input',
        function () {

            const tipo =
                obtenerTipoIdentificacion();


            if (tipo === 'rut') {

                rut.value =
                    formatearRut(rut.value);

            } else {

                rut.value =
                    rut.value
                        .toUpperCase()
                        .replace(
                            /[^A-Z0-9\-]/g,
                            ''
                        );
            }


            actualizarResumen();


            if (
                rut.value.trim() !== ''
            ) {

                ocultarError(errores.rut);
            }


            actualizarEstadoFormulario();
        }
    );


    /* =========================================================
       VALIDACIÓN PERSONAL
    ========================================================= */

    function validarDatosPersonales(
        mostrarMensajes
    ) {

        if (
            typeof mostrarMensajes === 'undefined'
        ) {

            mostrarMensajes = true;
        }


        let valido = true;

        const tipo =
            obtenerTipoIdentificacion();


        /* NOMBRE */

        if (
            nombre.value.trim() === ''
        ) {

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

        const valorIdentificacion =
            rut.value.trim();


        if (
            valorIdentificacion === ''
        ) {

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

        if (
            telefono.value.trim() === ''
        ) {

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


        /* CORREO */

        const correoValor =
            correo.value.trim();


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
       ESTADO DEL FORMULARIO
    ========================================================= */

    function actualizarEstadoFormulario() {

        const datosCompletos =
            validarDatosPersonales(false);


        /*
         * Departamento se habilita solamente cuando
         * los datos personales están completos.
         */
        departamento.disabled =
            !datosCompletos;


        /*
         * Servicio depende del departamento.
         */
        servicio.disabled =
            !datosCompletos ||
            departamento.value === '';


        /*
         * Fecha depende del servicio.
         */
        fecha.disabled =
            !datosCompletos ||
            departamento.value === '' ||
            servicio.value === '';


        /*
         * Horario depende del servicio y la fecha.
         */
        if (
            !datosCompletos ||
            departamento.value === '' ||
            servicio.value === '' ||
            fecha.value === ''
        ) {

            horario.disabled = true;

            horario.innerHTML =
                '<option value="">Seleccione servicio y fecha</option>';

        } else {

            horario.disabled = false;
        }
    }


    /* =========================================================
       ERRORES
    ========================================================= */

    function mostrarError(
        elemento,
        mensaje
    ) {

        if (!elemento) {

            return;
        }


        elemento.textContent =
            mensaje;

        elemento.classList.add(
            'active'
        );


        const input =
            elemento.previousElementSibling;


        if (
            input &&
            (
                input.tagName === 'INPUT' ||
                input.tagName === 'SELECT' ||
                input.tagName === 'TEXTAREA'
            )
        ) {

            input.classList.add(
                'field-invalid'
            );
        }
    }


    function ocultarError(elemento) {

        if (!elemento) {

            return;
        }


        elemento.classList.remove(
            'active'
        );


        const input =
            elemento.previousElementSibling;


        if (
            input &&
            (
                input.tagName === 'INPUT' ||
                input.tagName === 'SELECT' ||
                input.tagName === 'TEXTAREA'
            )
        ) {

            input.classList.remove(
                'field-invalid'
            );
        }
    }


    /* =========================================================
       ESCUCHAR CAMPOS
    ========================================================= */

    [
        nombre,
        telefono,
        correo
    ].forEach(function (campo) {

        campo.addEventListener(
            'input',
            function () {

                actualizarEstadoFormulario();

                actualizarResumen();
            }
        );

    });


    /* =========================================================
       BLOQUEO DE DEPARTAMENTO / SERVICIO / FECHA / HORA
    ========================================================= */

    departamento.addEventListener(
        'mousedown',
        function (event) {

            if (
                departamento.disabled &&
                !validarDatosPersonales(true)
            ) {

                event.preventDefault();

            } else if (
                departamento.disabled
            ) {

                event.preventDefault();

            }

        }
    );


    servicio.addEventListener(
        'mousedown',
        function (event) {

            if (
                servicio.disabled &&
                !validarDatosPersonales(true)
            ) {

                event.preventDefault();

            } else if (
                servicio.disabled &&
                departamento.value === ''
            ) {

                event.preventDefault();

                mostrarError(
                    errores.departamento,
                    'Seleccione un departamento.'
                );

            }

        }
    );


    fecha.addEventListener(
        'mousedown',
        function (event) {

            if (
                fecha.disabled &&
                !validarDatosPersonales(true)
            ) {

                event.preventDefault();

            } else if (
                fecha.disabled &&
                departamento.value === ''
            ) {

                event.preventDefault();

                mostrarError(
                    errores.departamento,
                    'Seleccione un departamento.'
                );

            } else if (
                fecha.disabled &&
                servicio.value === ''
            ) {

                event.preventDefault();

                mostrarError(
                    errores.servicio,
                    'Seleccione un servicio.'
                );

            }

        }
    );


    horario.addEventListener(
        'mousedown',
        function (event) {

            if (
                horario.disabled &&
                !validarDatosPersonales(true)
            ) {

                event.preventDefault();

            } else if (
                horario.disabled &&
                servicio.value === ''
            ) {

                event.preventDefault();

                mostrarError(
                    errores.servicio,
                    'Seleccione un servicio.'
                );

            } else if (
                horario.disabled &&
                fecha.value === ''
            ) {

                event.preventDefault();

                mostrarError(
                    errores.fecha,
                    'Seleccione una fecha.'
                );

            }

        }
    );


    /* =========================================================
       CARGAR DEPARTAMENTOS
    ========================================================= */

    async function cargarDepartamentos() {

        try {

            const respuesta =
                await fetch(
                    '../api/obtener_departamentos.php'
                );


            const texto =
                await respuesta.text();


        


            let datos;


            try {

                datos =
                    JSON.parse(texto);

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


            const opcionInicial =
                document.createElement(
                    'option'
                );


            opcionInicial.value = '';

            opcionInicial.textContent =
                'Seleccione un departamento';


            departamento.appendChild(
                opcionInicial
            );


            datos.departamentos.forEach(
                function (item) {

                    const opcion =
                        document.createElement(
                            'option'
                        );


                    opcion.value =
                        item.id_departamento;


                    opcion.textContent =
                        item.nombre;


                    /*
                     * Un departamento solamente se puede
                     * seleccionar si está activo y tiene
                     * al menos un servicio activo.
                     */
                    if (
                        !item.seleccionable
                    ) {

                        opcion.disabled = true;
                    }


                    /*
                     * Guardamos los servicios dentro
                     * de la opción para utilizarlos
                     * posteriormente.
                     */
                    opcion.dataset.servicios =
                        JSON.stringify(
                            item.servicios || []
                        );


                    departamento.appendChild(
                        opcion
                    );
                }
            );


            /*
             * El estado final depende de los datos personales.
             */
            actualizarEstadoFormulario();

        }


        catch (error) {

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
       DEPARTAMENTO
    ========================================================= */

    departamento.addEventListener(
        'change',
        function () {

            ocultarError(
                errores.departamento
            );


            /*
             * Cada vez que cambia el departamento,
             * se limpia completamente el servicio,
             * fecha y horario.
             */
            servicio.innerHTML =
                '<option value="">Seleccione un servicio</option>';


            servicio.value = '';

            fecha.value = '';


            horario.innerHTML =
                '<option value="">Seleccione servicio y fecha</option>';

            horario.disabled = true;


            /*
             * Buscar la opción actualmente seleccionada.
             */
            const opcionSeleccionada =
                departamento.options[
                    departamento.selectedIndex
                ];


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

                servicios =
                    JSON.parse(
                        opcionSeleccionada.dataset.servicios || '[]'
                    );

            } catch (errorJSON) {

                console.error(
                    'No se pudieron interpretar los servicios:',
                    errorJSON
                );

                servicios = [];
            }


            /*
             * Agregar servicios del departamento.
             */
            servicios.forEach(
                function (item) {

                    const opcion =
                        document.createElement(
                            'option'
                        );


                    /*
                     * El value utiliza el ID del servicio.
                     * Esto permite que tanto obtener_horarios.php
                     * como crear_reserva.php trabajen directamente
                     * con id_servicio.
                     */
                    opcion.value =
                        item.id_servicio;


                    opcion.textContent =
                        item.nombre;


                    /*
                     * Guardamos también el ID del servicio
                     * como atributo de datos.
                     */
                    opcion.dataset.idServicio =
                        item.id_servicio;


                    if (
                        item.descripcion !== null &&
                        item.descripcion !== ''
                    ) {

                        opcion.title =
                            item.descripcion;
                    }


                    servicio.appendChild(
                        opcion
                    );
                }
            );


            servicio.disabled =
                servicios.length === 0;


            fecha.disabled = true;


            actualizarResumen();

            actualizarEstadoFormulario();
        }
    );


    /* =========================================================
       SERVICIO
    ========================================================= */

    servicio.addEventListener(
        'change',
        function () {

            ocultarError(
                errores.servicio
            );


            fecha.value = '';


            horario.innerHTML =
                '<option value="">Seleccione servicio y fecha</option>';


            horario.disabled = true;


            /*
             * La fecha solamente se habilita cuando
             * existe un servicio seleccionado.
             */
            fecha.disabled =
                servicio.value === '';


            /*
             * =====================================================
             * AVISO VACUNAS VETERINARIA
             * =====================================================
             *
             * Los servicios séxtuple y óctuple siguen siendo
             * servicios independientes en la base de datos.
             *
             * Aquí solamente detectamos cuál de los dos
             * seleccionó el usuario para mostrar el aviso
             * correspondiente.
             */

            if (
                servicio.value !== ''
            ) {

                const opcionServicio =
                    servicio.options[
                        servicio.selectedIndex
                    ];


                const nombreServicio =
                    opcionServicio
                        ? opcionServicio.textContent
                            .toLowerCase()
                            .trim()
                        : '';


                const esVacunaSextuple =
                    nombreServicio.indexOf(
                        'séxtuple'
                    ) !== -1 ||
                    nombreServicio.indexOf(
                        'sextuple'
                    ) !== -1;


                const esVacunaOctuple =
                    nombreServicio.indexOf(
                        'óctuple'
                    ) !== -1 ||
                    nombreServicio.indexOf(
                        'octuple'
                    ) !== -1;


                /*
                 * Primero se comprueba séxtuple.
                 */
                if (
                    esVacunaSextuple
                ) {

                    mostrarAvisoVacuna(
                        'séxtuple'
                    );


                /*
                 * Luego se comprueba óctuple.
                 */
                } else if (
                    esVacunaOctuple
                ) {

                    mostrarAvisoVacuna(
                        'óctuple'
                    );
                }
            }


            actualizarResumen();

            actualizarEstadoFormulario();
        }
    );


    /* =========================================================
       FECHA
    ========================================================= */

    fecha.addEventListener(
        'change',
        async function () {

            ocultarError(
                errores.fecha
            );


            horario.innerHTML =
                '<option value="">Cargando horarios...</option>';


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
        }
    );


    /* =========================================================
       CARGAR HORARIOS
    ========================================================= */

    async function cargarHorarios() {

        try {

            const url =
                '../api/obtener_horarios.php' +
                '?servicio=' +
                encodeURIComponent(
                    servicio.value
                ) +
                '&fecha=' +
                encodeURIComponent(
                    fecha.value
                );


            const respuesta =
                await fetch(url);


            const texto =
                await respuesta.text();


          


            let datos;


            try {

                datos =
                    JSON.parse(texto);

            } catch (errorJSON) {

                console.error(
                    'obtener_horarios.php no devolvió JSON válido:',
                    texto
                );


                horario.innerHTML =
                    '<option value="">Error al cargar horarios</option>';

                horario.disabled = true;

                return;
            }


            horario.innerHTML = '';


            if (
                !datos.ok ||
                !Array.isArray(datos.horarios)
            ) {

                horario.innerHTML =
                    '<option value="">No hay horarios disponibles</option>';

                horario.disabled = true;

                return;
            }


            const opcionInicial =
                document.createElement(
                    'option'
                );


            opcionInicial.value = '';

            opcionInicial.textContent =
                'Seleccione un horario';


            horario.appendChild(
                opcionInicial
            );


            datos.horarios.forEach(
                function (item) {

                    const opcion =
                        document.createElement(
                            'option'
                        );


                    opcion.value =
                        item.hora;


                    opcion.textContent =
                        item.disponible
                            ? item.hora
                            : item.hora +
                              ' — No disponible';


                    if (!item.disponible) {

                        opcion.disabled = true;
                    }


                    horario.appendChild(
                        opcion
                    );
                }
            );


            horario.disabled =
                datos.horarios.length === 0;
        }


        catch (error) {

            console.error(
                'Error cargando horarios:',
                error
            );


            horario.innerHTML =
                '<option value="">No se pudieron cargar los horarios</option>';


            horario.disabled = true;
        }
    }


    /* =========================================================
       HORARIO
    ========================================================= */

    horario.addEventListener(
        'change',
        function () {

            ocultarError(
                errores.horario
            );


            actualizarResumen();
        }
    );


    /* =========================================================
       RESUMEN
    ========================================================= */

    function actualizarResumen() {

        previewNombre.textContent =
            nombre.value.trim() || '-';


        const tipo =
            obtenerTipoIdentificacion();


        const identificacion =
            rut.value.trim();


        if (
            identificacion === ''
        ) {

            previewIdentificacion.textContent =
                '-';

        } else {

            let textoTipo =
                'RUT';


            if (
                tipo === 'rut_extranjero'
            ) {

                textoTipo =
                    'RUT extranjero';

            } else if (
                tipo === 'pasaporte'
            ) {

                textoTipo =
                    'Pasaporte';
            }


            previewIdentificacion.textContent =
                textoTipo +
                ': ' +
                identificacion;
        }


        /*
         * Departamento
         */
        if (
            departamento.value !== ''
        ) {

            const opcionDepartamento =
                departamento.options[
                    departamento.selectedIndex
                ];


            previewDepartamento.textContent =
                opcionDepartamento
                    ? opcionDepartamento.textContent
                    : '-';

        } else {

            previewDepartamento.textContent =
                '-';
        }


        /*
         * Servicio
         */
        if (
            servicio.value !== ''
        ) {

            const opcionServicio =
                servicio.options[
                    servicio.selectedIndex
                ];


            previewServicio.textContent =
                opcionServicio
                    ? opcionServicio.textContent
                    : '-';

        } else {

            previewServicio.textContent =
                '-';
        }


        if (
            fecha.value !== ''
        ) {

            const partes =
                fecha.value.split('-');


            previewFecha.textContent =
                partes[2] +
                '/' +
                partes[1] +
                '/' +
                partes[0];

        } else {

            previewFecha.textContent =
                '-';
        }


        previewHora.textContent =
            horario.value || '-';
    }


    /* =========================================================
       NOTIFICACIONES
    ========================================================= */

    function mostrarNotificacion(
        mensaje,
        tipo
    ) {

        if (
            typeof tipo === 'undefined'
        ) {

            tipo = 'info';
        }


        notificationMessage.textContent =
            mensaje;


        notification.classList.remove(
            'notification-success',
            'notification-error'
        );


        if (
            tipo === 'success'
        ) {

            notification.classList.add(
                'notification-success'
            );

        } else if (
            tipo === 'error'
        ) {

            notification.classList.add(
                'notification-error'
            );
        }


        notification.hidden = false;
    }


    /* =========================================================
       POPUP
    ========================================================= */

    function mostrarPopup(
        exito,
        titulo,
        mensaje,
        tipo
    ) {

        if (
            typeof tipo === 'undefined'
        ) {

            tipo =
                exito
                    ? 'success'
                    : 'error';
        }


        reservationModal.classList.remove(
            'modal-success',
            'modal-error',
            'modal-info'
        );


        if (
            tipo === 'success'
        ) {

            reservationModal.classList.add(
                'modal-success'
            );


            reservationModalIcon.textContent =
                '✓';


        } else if (
            tipo === 'info'
        ) {

            reservationModal.classList.add(
                'modal-info'
            );


            reservationModalIcon.textContent =
                'i';


        } else {

            reservationModal.classList.add(
                'modal-error'
            );


            reservationModalIcon.textContent =
                '!';
        }


        reservationModalTitle.textContent =
            titulo;


        reservationModalMessage.textContent =
            mensaje;


        reservationModal.hidden = false;


        document.body.classList.add(
            'modal-open'
        );
    }


    /* =========================================================
       AVISO VACUNAS VETERINARIA
    ========================================================= */

    function mostrarAvisoVacuna(
        tipoVacuna
    ) {

        let mensaje =
            'Debe comprar previamente la vacuna correspondiente y llevarla el día de la atención.';


        if (
            tipoVacuna === 'séxtuple'
        ) {

            mensaje =
                'Debe comprar previamente la vacuna séxtuple y llevarla el día de la atención.';


        } else if (
            tipoVacuna === 'óctuple'
        ) {

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


    function cerrarPopup() {

        reservationModal.hidden = true;

        document.body.classList.remove(
            'modal-open'
        );
    }


    reservationModalButton.addEventListener(
        'click',
        cerrarPopup
    );


    reservationModalOverlay.addEventListener(
        'click',
        cerrarPopup
    );


    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape' &&
                !reservationModal.hidden
            ) {

                cerrarPopup();
            }

        }
    );


    /* =========================================================
       ENVÍO DEL FORMULARIO
       MODO DEPURACIÓN RAW
    ========================================================= */

    form.addEventListener(
        'submit',
        async function (event) {

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

            if (
                departamento.value === ''
            ) {

                formularioValido = false;


                mostrarError(
                    errores.departamento,
                    'Campo obligatorio.'
                );

            } else {

                ocultarError(
                    errores.departamento
                );
            }


            /* SERVICIO */

            if (
                servicio.value === ''
            ) {

                formularioValido = false;


                mostrarError(
                    errores.servicio,
                    'Campo obligatorio.'
                );

            } else {

                ocultarError(
                    errores.servicio
                );
            }


            /* FECHA */

            if (
                fecha.value === ''
            ) {

                formularioValido = false;


                mostrarError(
                    errores.fecha,
                    'Campo obligatorio.'
                );

            } else {

                ocultarError(
                    errores.fecha
                );
            }


            /* HORARIO */

            if (
                horario.value === ''
            ) {

                formularioValido = false;


                mostrarError(
                    errores.horario,
                    'Campo obligatorio.'
                );

            } else {

                ocultarError(
                    errores.horario
                );
            }


            if (!formularioValido) {

                mostrarNotificacion(
                    'Complete los datos de la solicitud.',
                    'error'
                );

                return;
            }


            const datos =
                new FormData(form);


            try {

                const respuesta =
                    await fetch(
                        '../api/crear_reserva.php',
                        {
                            method: 'POST',
                            body: datos
                        }
                    );


                /*
                 * Obtenemos primero la respuesta
                 * como texto RAW.
                 */

                const textoRespuesta =
                    await respuesta.text();


                

                /*
                 * Intentamos convertir la respuesta
                 * a JSON.
                 */

                let resultado;


                try {

                    resultado =
                        JSON.parse(
                            textoRespuesta
                        );

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


                

                /* =================================================
                   RESPUESTA CORRECTA
                ================================================= */

                if (
                    resultado.ok
                ) {

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
            }

        }
    );


    /* =========================================================
       ESTADO INICIAL
    ========================================================= */

    actualizarTipoIdentificacion();

    actualizarEstadoFormulario();

    actualizarResumen();

    cargarDepartamentos();

});