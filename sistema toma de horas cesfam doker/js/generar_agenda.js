
document.addEventListener('DOMContentLoaded', () => {

    /* =====================================================
       ELEMENTOS DEL PROFESIONAL
    ===================================================== */

    const selectProfesional =
        document.getElementById('profesionalAgenda');

    const buscarProfesional =
        document.getElementById('buscarProfesionalAgenda');

    const informacionProfesional =
        document.getElementById('informacionProfesionalAgenda');

    const campoProfesional =
        document.getElementById('idProfesionalAgenda');

    const nombreProfesional =
        document.getElementById('nombreProfesionalAgenda');

    const campoInstrumento =
        document.getElementById('instrumentoAgenda');


    /* =====================================================
       ELEMENTOS DEL MODAL
    ===================================================== */

    const modal =
        document.getElementById('modalGenerarAgenda');

    const formulario =
        document.getElementById('formGenerarAgenda');

    const botonCerrar =
        document.getElementById('cerrarModalGenerarAgenda');

    const botonCancelar =
        document.getElementById('btnCancelarGenerarAgenda');


    /* =====================================================
       ELEMENTOS DE CONFIGURACIÓN
    ===================================================== */

    const selectDepartamento =
        document.getElementById('tipoAtencionAgenda');

    const selectServicio =
        document.getElementById('servicioAgenda');

    const campoFecha =
        document.getElementById('diaAgenda');

    const campoDuracion =
        document.getElementById('duracionAgenda');

    const campoSector =
        document.getElementById('sectorAgenda');

    const campoHoraReferencia =
        document.getElementById('horaReferenciaAgenda');

    const checkboxDiferenciado =
        document.getElementById('inicioDiferenciado');

    const camposDiferenciados =
        document.getElementById('horarioDiferenciadoCampos');

    const campoHoraInicio =
        document.getElementById('horaInicioAgenda');

    const campoHoraFin =
        document.getElementById('horaFinAgenda');

    const checkboxHabilitado =
        document.getElementById('segmentoHabilitado');

    const checkboxMultiprofesional =
        document.getElementById('origenMultiprofesional');


    /* =====================================================
       ELEMENTOS DE VISTA PREVIA Y BOTONES
    ===================================================== */

    const resultado =
        document.getElementById('resultadoVistaPreviaAgenda');

    const botonPrevisualizar =
        document.getElementById('btnPrevisualizarAgenda');

    const botonGuardar =
        document.getElementById('btnGuardarAgenda');


    /* =====================================================
       DIRECCIONES DE API
    ===================================================== */

    const API_CONFIGURACION =
        '../api/obtener_configuracion_horarios_agenda.php';

    const API_CREAR_AGENDA =
        '../api/crear_agenda.php';


    /* =====================================================
       ESTADO INTERNO
    ===================================================== */

    let horaSeleccionada = '';

    let cuposPrevisualizados = [];

    let datosPrevisualizacion = null;

    let serviciosOriginales = [];

    let generandoVistaPrevia = false;

    let guardandoAgenda = false;


    /* =====================================================
       VALIDAR ELEMENTOS PRINCIPALES
    ===================================================== */

    const elementosObligatorios = [
        selectProfesional,
        buscarProfesional,
        informacionProfesional,
        campoProfesional,
        nombreProfesional,
        campoInstrumento,
        modal,
        formulario,
        selectDepartamento,
        selectServicio,
        campoFecha,
        campoDuracion,
        campoHoraReferencia,
        checkboxDiferenciado,
        camposDiferenciados,
        campoHoraInicio,
        campoHoraFin,
        checkboxHabilitado,
        checkboxMultiprofesional,
        resultado,
        botonPrevisualizar,
        botonGuardar
    ];

    if (
        elementosObligatorios.some(
            elemento => !elemento
        )
    ) {

        console.error(
            'Generar agenda: faltan elementos del formulario de agenda.php.'
        );

        return;
    }


    /* =====================================================
       ESCAPAR HTML
    ===================================================== */

    function escaparHTML(valor) {

        const elemento =
            document.createElement('div');

        elemento.textContent =
            String(valor ?? '');

        return elemento.innerHTML;
    }


    /* =====================================================
       MOSTRAR MENSAJE
    ===================================================== */

    function mostrarMensaje(
        mensaje,
        tipo = 'error'
    ) {

        resultado.innerHTML = `
            <div class="mensaje-agenda-${tipo}">
                ${escaparHTML(mensaje)}
            </div>
        `;
    }


    /* =====================================================
       INVALIDAR VISTA PREVIA

       Cualquier modificación de la configuración obliga
       a generar una nueva vista previa antes de guardar.
    ===================================================== */

    function invalidarVistaPrevia() {

        cuposPrevisualizados = [];
        datosPrevisualizacion = null;

        botonGuardar.disabled = true;

        if (!generandoVistaPrevia && !guardandoAgenda) {
            resultado.innerHTML = '';
        }
    }


    /* =====================================================
       CONVERTIR HORA A MINUTOS
    ===================================================== */

    function horaAMinutos(hora) {

        if (
            typeof hora !== 'string' ||
            !/^\d{2}:\d{2}$/.test(hora)
        ) {
            return null;
        }

        const [horas, minutos] =
            hora.split(':').map(Number);

        if (
            horas > 23 ||
            minutos > 59
        ) {
            return null;
        }

        return horas * 60 + minutos;
    }


    /* =====================================================
       CONVERTIR MINUTOS A HORA
    ===================================================== */

    function minutosAHora(minutos) {

        const horas =
            Math.floor(minutos / 60);

        const resto =
            minutos % 60;

        return (
            String(horas).padStart(2, '0') +
            ':' +
            String(resto).padStart(2, '0')
        );
    }


    /* =====================================================
       OBTENER FECHA LOCAL
    ===================================================== */

    function obtenerFechaLocal() {

        const fecha = new Date();

        const año =
            fecha.getFullYear();

        const mes =
            String(fecha.getMonth() + 1)
                .padStart(2, '0');

        const dia =
            String(fecha.getDate())
                .padStart(2, '0');

        return `${año}-${mes}-${dia}`;
    }


    /* =====================================================
       VALIDAR FECHA
    ===================================================== */

    function validarFecha(fecha) {

        if (
            typeof fecha !== 'string' ||
            !/^\d{4}-\d{2}-\d{2}$/.test(fecha)
        ) {
            return false;
        }

        const partes =
            fecha.split('-').map(Number);

        const objeto =
            new Date(
                partes[0],
                partes[1] - 1,
                partes[2]
            );

        return (
            objeto.getFullYear() === partes[0] &&
            objeto.getMonth() === partes[1] - 1 &&
            objeto.getDate() === partes[2]
        );
    }


    /* =====================================================
       LEER RESPUESTA JSON DEL SERVIDOR
    ===================================================== */

    async function leerRespuestaJSON(respuesta) {

        const texto =
            await respuesta.text();

        let datos;

        try {

            datos = JSON.parse(texto);

        } catch (error) {

            console.error(
                'Respuesta recibida del servidor:',
                texto
            );

            throw new Error(
                'El servidor no devolvió una respuesta JSON válida. Revisa los errores de PHP.'
            );
        }

        return datos;
    }


    /* =====================================================
       INICIALIZAR PROFESIONALES

       agenda.php ya carga los profesionales activos
       y deja seleccionado el profesional recibido por GET.

       Aquí también inicializamos sus datos en el modal.
    ===================================================== */

    function inicializarProfesionales() {

        const opciones =
            Array.from(selectProfesional.options);

        if (opciones.length <= 1) {

            informacionProfesional.textContent =
                'No existen profesionales activos registrados.';

            return;
        }

        selectProfesional.addEventListener(
            'change',
            seleccionarProfesional
        );

        /*
         * IMPORTANTE:
         * Inicializar también el profesional que viene
         * seleccionado desde agenda.php.
         */

        if (selectProfesional.value) {

            seleccionarProfesional();

        } else {

            informacionProfesional.textContent =
                'No hay ningún profesional seleccionado.';

        }
    }


    /* =====================================================
       BUSCAR PROFESIONAL
    ===================================================== */

    buscarProfesional.addEventListener(
        'input',
        () => {

            const busqueda =
                buscarProfesional.value
                    .toLowerCase()
                    .trim();

            Array.from(
                selectProfesional.options
            ).forEach(
                (opcion, indice) => {

                    if (indice === 0) {
                        return;
                    }

                    opcion.hidden =
                        !opcion.textContent
                            .toLowerCase()
                            .includes(busqueda);

                }
            );

        }
    );


    /* =====================================================
       SELECCIONAR PROFESIONAL
    ===================================================== */

    function seleccionarProfesional() {

        const opcion =
            selectProfesional.selectedOptions[0];

        if (!opcion || !opcion.value) {

            campoProfesional.value = '';
            nombreProfesional.value = '';
            campoInstrumento.value = '';

            informacionProfesional.textContent =
                'No hay ningún profesional seleccionado.';

            return;
        }

        campoProfesional.value =
            opcion.value;

        nombreProfesional.value =
            opcion.textContent.trim();

        campoInstrumento.value =
            opcion.dataset.estamento || '';

        informacionProfesional.textContent =
            `Profesional seleccionado: ${opcion.textContent.trim()}`;

    }


    /* =====================================================
       GUARDAR OPCIONES ORIGINALES DE SERVICIOS

       Los servicios vienen desde agenda.php.
       Cada opción contiene data-departamento.
    ===================================================== */

    function prepararServicios() {

        serviciosOriginales =
            Array.from(selectServicio.options)

                .filter(
                    opcion => opcion.value !== ''
                )

                .map(
                    opcion => ({

                        value: opcion.value,

                        texto: opcion.textContent.trim(),

                        departamento:
                            opcion.dataset.departamento || ''

                    })
                );

    }


    /* =====================================================
       CARGAR SERVICIOS DEL DEPARTAMENTO
    ===================================================== */

    function cargarServiciosPorDepartamento(
        idDepartamento,
        servicioSeleccionado = ''
    ) {

        selectServicio.innerHTML = '';

        const opcionInicial =
            document.createElement('option');

        opcionInicial.value = '';

        opcionInicial.textContent =
            'Selecciona un servicio';

        selectServicio.appendChild(
            opcionInicial
        );

        const servicios =
            serviciosOriginales.filter(
                servicio =>
                    String(servicio.departamento) ===
                    String(idDepartamento)
            );

        if (
            !idDepartamento ||
            servicios.length === 0
        ) {

            selectServicio.disabled = true;

            opcionInicial.textContent =
                idDepartamento
                    ? 'Este departamento no tiene servicios disponibles'
                    : 'Selecciona primero un tipo de atención';

            return;
        }

        servicios.forEach(
            servicio => {

                const opcion =
                    document.createElement('option');

                opcion.value =
                    servicio.value;

                opcion.textContent =
                    servicio.texto;

                selectServicio.appendChild(
                    opcion
                );

            }
        );

        selectServicio.disabled = false;

        if (servicioSeleccionado) {

            const existe =
                servicios.some(
                    servicio =>
                        String(servicio.value) ===
                        String(servicioSeleccionado)
                );

            if (existe) {

                selectServicio.value =
                    String(servicioSeleccionado);

            }
        }

    }


    /* =====================================================
       CAMBIAR TIPO DE ATENCIÓN
    ===================================================== */

    selectDepartamento.addEventListener(
        'change',
        () => {

            cargarServiciosPorDepartamento(
                selectDepartamento.value
            );

            invalidarVistaPrevia();

        }
    );


    /* =====================================================
       CAMBIAR SERVICIO
    ===================================================== */

    selectServicio.addEventListener(
        'change',
        invalidarVistaPrevia
    );


    /* =====================================================
       ABRIR MODAL
    ===================================================== */

    function abrirModal(fecha, hora) {

        if (!selectProfesional.value) {

            alert(
                'Primero debes seleccionar un profesional y pulsar Ver agenda.'
            );

            selectProfesional.focus();

            return;
        }

        if (!validarFecha(fecha)) {

            alert(
                'La fecha seleccionada no es válida.'
            );

            return;
        }

        /*
         * Guardar el departamento actual antes de
         * reiniciar el formulario.
         */

        const departamentoInicial =
            selectDepartamento.value;

        const opcion =
            selectProfesional.selectedOptions[0];

        formulario.reset();

        /*
         * Restaurar explícitamente el departamento.
         */

        selectDepartamento.value =
            departamentoInicial;

        invalidarVistaPrevia();

        campoProfesional.value =
            opcion.value;

        nombreProfesional.value =
            opcion.textContent.trim();

        campoInstrumento.value =
            opcion.dataset.estamento || '';

        campoFecha.value =
            fecha;

        campoFecha.min =
            obtenerFechaLocal();

        horaSeleccionada = hora;

        campoHoraReferencia.value =
            hora;

        /*
         * El horario de referencia se utiliza como
         * inicio sugerido cuando se activa inicio diferenciado.
         */

        campoHoraInicio.value =
            hora;

        campoHoraFin.value = '';

        camposDiferenciados.hidden = true;

        /*
         * Cargar servicios del departamento actual.
         */

        cargarServiciosPorDepartamento(
            departamentoInicial
        );

        modal.classList.add('activo');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

    }


    /* =====================================================
       CERRAR MODAL
    ===================================================== */

    function cerrarModal() {

        modal.classList.remove('activo');

        modal.setAttribute(
            'aria-hidden',
            'true'
        );

        formulario.reset();

        cuposPrevisualizados = [];
        datosPrevisualizacion = null;

        camposDiferenciados.hidden = true;

        selectServicio.disabled = true;

        resultado.innerHTML = '';

        botonGuardar.disabled = true;

        botonPrevisualizar.disabled = false;

        botonGuardar.textContent =
            'Guardar agenda';

        horaSeleccionada = '';

        /*
         * Restaurar los datos del profesional seleccionado.
         */

        seleccionarProfesional();

    }


    if (botonCerrar) {

        botonCerrar.addEventListener(
            'click',
            cerrarModal
        );

    }

    if (botonCancelar) {

        botonCancelar.addEventListener(
            'click',
            cerrarModal
        );

    }

    modal.addEventListener(
        'click',
        evento => {

            if (evento.target === modal) {
                cerrarModal();
            }

        }
    );

    document.addEventListener(
        'keydown',
        evento => {

            if (
                evento.key === 'Escape' &&
                modal.classList.contains('activo')
            ) {
                cerrarModal();
            }

        }
    );


    /* =====================================================
       ACTIVAR INICIO DIFERENCIADO
    ===================================================== */

    checkboxDiferenciado.addEventListener(
        'change',
        () => {

            camposDiferenciados.hidden =
                !checkboxDiferenciado.checked;

            if (checkboxDiferenciado.checked) {

                campoHoraInicio.value =
                    horaSeleccionada;

                campoHoraFin.value = '';

            }

            invalidarVistaPrevia();

        }
    );


    /* =====================================================
       OBTENER CONFIGURACIÓN HABITUAL
    ===================================================== */

    async function obtenerConfiguracionHabitual() {

        const servicio =
            selectServicio.value;

        const fecha =
            campoFecha.value;

        if (!servicio || !fecha) {

            throw new Error(
                'Selecciona un servicio y una fecha.'
            );
        }

        const parametros =
            new URLSearchParams();

        parametros.set(
            'servicio',
            servicio
        );

        parametros.set(
            'fecha',
            fecha
        );

        const respuesta =
            await fetch(
                `${API_CONFIGURACION}?${parametros.toString()}`,
                {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

        const datos =
            await leerRespuestaJSON(respuesta);

        if (!respuesta.ok || !datos.ok) {

            throw new Error(
                datos.mensaje ||
                datos.error ||
                'No se encontró la configuración habitual.'
            );
        }

        if (
            !Array.isArray(datos.configuraciones) ||
            datos.configuraciones.length === 0
        ) {

            throw new Error(
                'No existe una configuración habitual para este servicio y día.'
            );
        }

        if (datos.configuraciones.length > 1) {

            throw new Error(
                'Este servicio tiene varias configuraciones para el mismo día. Debemos definir cómo separarlas en segmentos.'
            );
        }

        return datos.configuraciones[0];

    }


    /* =====================================================
       GENERAR CUPOS

       - Respeta la duración.
       - Permite último cupo reducido.
       - Respeta la pausa de almuerzo.
    ===================================================== */

    function generarCupos(
        horaInicio,
        horaFin,
        duracion,
        almuerzoInicio = null,
        almuerzoFin = null
    ) {

        const inicio =
            horaAMinutos(horaInicio);

        const fin =
            horaAMinutos(horaFin);

        if (
            inicio === null ||
            fin === null ||
            fin <= inicio
        ) {

            throw new Error(
                'El rango horario seleccionado no es válido.'
            );
        }

        if (
            !Number.isInteger(duracion) ||
            duracion <= 0
        ) {

            throw new Error(
                'La duración del cupo no es válida.'
            );
        }

        let inicioAlmuerzo = null;
        let finAlmuerzo = null;

        if (almuerzoInicio && almuerzoFin) {

            inicioAlmuerzo =
                horaAMinutos(almuerzoInicio);

            finAlmuerzo =
                horaAMinutos(almuerzoFin);

            if (
                inicioAlmuerzo === null ||
                finAlmuerzo === null ||
                finAlmuerzo <= inicioAlmuerzo
            ) {

                throw new Error(
                    'El horario de almuerzo configurado no es válido.'
                );
            }

            if (
                inicioAlmuerzo < inicio ||
                finAlmuerzo > fin
            ) {

                throw new Error(
                    'El horario de almuerzo debe estar dentro del segmento de atención.'
                );
            }

        }

        const cupos = [];

        let actual = inicio;

        while (actual < fin) {

            /*
             * SALTAR EL ALMUERZO
             */

            if (
                inicioAlmuerzo !== null &&
                finAlmuerzo !== null &&
                actual >= inicioAlmuerzo &&
                actual < finAlmuerzo
            ) {

                actual = finAlmuerzo;

                continue;
            }

            let siguiente =
                Math.min(
                    actual + duracion,
                    fin
                );

            /*
             * EVITAR CRUZAR EL ALMUERZO
             */

            if (
                inicioAlmuerzo !== null &&
                actual < inicioAlmuerzo &&
                siguiente > inicioAlmuerzo
            ) {

                siguiente =
                    inicioAlmuerzo;

            }

            if (siguiente <= actual) {
                break;
            }

            cupos.push({

                numero:
                    cupos.length + 1,

                hora_inicio:
                    minutosAHora(actual),

                hora_fin:
                    minutosAHora(siguiente),

                duracion_minutos:
                    siguiente - actual

            });

            actual = siguiente;

            if (cupos.length > 500) {

                throw new Error(
                    'La cantidad de cupos supera el límite permitido.'
                );

            }

        }

        return cupos;

    }


    /* =====================================================
       GENERAR VISTA PREVIA
    ===================================================== */

    async function generarVistaPrevia() {

        if (generandoVistaPrevia || guardandoAgenda) {
            return;
        }

        invalidarVistaPrevia();

        if (!campoProfesional.value) {

            mostrarMensaje(
                'Selecciona un profesional.'
            );

            return;
        }

        if (!selectDepartamento.value) {

            mostrarMensaje(
                'Selecciona un tipo de atención.'
            );

            return;
        }

        if (!selectServicio.value) {

            mostrarMensaje(
                'Selecciona un servicio.'
            );

            return;
        }

        if (!validarFecha(campoFecha.value)) {

            mostrarMensaje(
                'Selecciona una fecha válida.'
            );

            return;
        }

        if (campoFecha.value < obtenerFechaLocal()) {

            mostrarMensaje(
                'No se pueden generar agendas para fechas anteriores a hoy.'
            );

            return;
        }

        let inicio = '';
        let fin = '';
        let duracion = 0;

        let almuerzoInicio = null;
        let almuerzoFin = null;

        let tipoConfiguracion = 'habitual';

        try {

            generandoVistaPrevia = true;

            botonPrevisualizar.disabled = true;

            if (checkboxDiferenciado.checked) {

                /*
                 * HORARIO DIFERENCIADO
                 */

                inicio =
                    campoHoraInicio.value;

                fin =
                    campoHoraFin.value;

                duracion =
                    Number(campoDuracion.value);

                tipoConfiguracion =
                    'diferenciado';

                if (!inicio || !fin) {

                    throw new Error(
                        'Debes indicar la hora de inicio y finalización.'
                    );
                }

            } else {

                /*
                 * HORARIO HABITUAL
                 */

                mostrarMensaje(
                    'Consultando configuración habitual...',
                    'info'
                );

                const configuracion =
                    await obtenerConfiguracionHabitual();

                inicio =
                    String(configuracion.hora_inicio)
                        .substring(0, 5);

                fin =
                    String(configuracion.hora_fin)
                        .substring(0, 5);

                duracion =
                    Number(configuracion.intervalo_minutos);

                const tieneAlmuerzo =
                    configuracion.tiene_almuerzo === true ||
                    configuracion.tiene_almuerzo === 'true' ||
                    configuracion.tiene_almuerzo === 't' ||
                    configuracion.tiene_almuerzo === 1;

                if (tieneAlmuerzo) {

                    almuerzoInicio =
                        configuracion.almuerzo_inicio
                            ? String(configuracion.almuerzo_inicio)
                                .substring(0, 5)
                            : null;

                    almuerzoFin =
                        configuracion.almuerzo_fin
                            ? String(configuracion.almuerzo_fin)
                                .substring(0, 5)
                            : null;

                    if (!almuerzoInicio || !almuerzoFin) {

                        throw new Error(
                            'La configuración de almuerzo está incompleta.'
                        );

                    }

                }

                /*
                 * Sincronizar la duración visual del formulario.
                 */

                campoDuracion.value =
                    String(duracion);

                if (campoDuracion.value !== String(duracion)) {

                    throw new Error(
                        'La duración configurada no está disponible en la lista de duraciones.'
                    );

                }

            }

            /*
             * GENERAR CUPOS
             */

            const nuevosCupos =
                generarCupos(
                    inicio,
                    fin,
                    duracion,
                    almuerzoInicio,
                    almuerzoFin
                );

            if (nuevosCupos.length === 0) {

                throw new Error(
                    'No se pudieron generar cupos dentro del horario seleccionado.'
                );

            }

            /*
             * GUARDAR LOS DATOS DE LA VISTA PREVIA
             */

            cuposPrevisualizados =
                nuevosCupos;

            datosPrevisualizacion = {

                id_profesional:
                    campoProfesional.value,

                id_departamento:
                    selectDepartamento.value,

                id_servicio:
                    selectServicio.value,

                fecha:
                    campoFecha.value,

                sector:
                    campoSector.value.trim() || 'No informado',

                hora_inicio:
                    inicio,

                hora_fin:
                    fin,

                duracion_minutos:
                    duracion,

                segmento_habilitado:
                    checkboxHabilitado.checked,

                origen_multiprofesional:
                    checkboxMultiprofesional.checked,

                inicio_diferenciado:
                    checkboxDiferenciado.checked,

                tipo_configuracion:
                    tipoConfiguracion,

                hora_referencia:
                    campoHoraReferencia.value

            };

            mostrarTablaPrevia();

            botonGuardar.disabled = false;

        } catch (error) {

            console.error(
                'Error al generar vista previa:',
                error
            );

            cuposPrevisualizados = [];
            datosPrevisualizacion = null;

            botonGuardar.disabled = true;

            mostrarMensaje(
                error.message ||
                'No fue posible generar la vista previa.'
            );

        } finally {

            generandoVistaPrevia = false;

            botonPrevisualizar.disabled = false;

        }

    }


    /* =====================================================
       MOSTRAR TABLA DE CUPOS
    ===================================================== */

    function mostrarTablaPrevia() {

        const filas =
            cuposPrevisualizados.map(
                cupo => `

                    <tr>

                        <td>
                            ${cupo.numero}
                        </td>

                        <td>
                            ${escaparHTML(cupo.hora_inicio)}
                        </td>

                        <td>
                            ${escaparHTML(cupo.hora_fin)}
                        </td>

                        <td>
                            ${cupo.duracion_minutos} min
                        </td>

                    </tr>

                `
            ).join('');

        const total =
            cuposPrevisualizados.length;

        const duracionTotal =
            cuposPrevisualizados.reduce(
                (suma, cupo) =>
                    suma + cupo.duracion_minutos,
                0
            );

        const nombreServicio =
            selectServicio.selectedOptions[0]?.textContent || '';

        resultado.innerHTML = `

            <h3>Vista previa de cupos</h3>

            <p>
                <strong>Profesional:</strong>
                ${escaparHTML(nombreProfesional.value)}
            </p>

            <p>
                <strong>Servicio:</strong>
                ${escaparHTML(nombreServicio)}
            </p>

            <p>
                <strong>Fecha:</strong>
                ${escaparHTML(campoFecha.value)}
            </p>

            <p>
                <strong>Segmento:</strong>
                ${escaparHTML(datosPrevisualizacion.hora_inicio)}
                a
                ${escaparHTML(datosPrevisualizacion.hora_fin)}
            </p>

            <div class="tabla-vista-previa-agenda">

                <table>

                    <thead>

                        <tr>
                            <th>N.º</th>
                            <th>Inicio</th>
                            <th>Término</th>
                            <th>Duración</th>
                        </tr>

                    </thead>

                    <tbody>
                        ${filas}
                    </tbody>

                </table>

            </div>

            <p>
                <strong>Total de cupos:</strong>
                ${total}
            </p>

            <p>
                <strong>Tiempo total:</strong>
                ${duracionTotal} minutos
            </p>

            <p>
                <strong>Estado del segmento:</strong>
                ${datosPrevisualizacion.segmento_habilitado
                    ? 'Habilitado'
                    : 'Deshabilitado'}
            </p>

            <p>
                <strong>Origen multiprofesional:</strong>
                ${datosPrevisualizacion.origen_multiprofesional
                    ? 'Sí'
                    : 'No'}
            </p>

            <p>
                <strong>Configuración:</strong>
                ${datosPrevisualizacion.tipo_configuracion === 'habitual'
                    ? 'Horario habitual'
                    : 'Horario diferenciado'}
            </p>

        `;

    }


    /* =====================================================
       BOTÓN PREVISUALIZAR
    ===================================================== */

    botonPrevisualizar.addEventListener(
        'click',
        generarVistaPrevia
    );


    /* =====================================================
       DETECTAR CAMBIOS EN LOS CAMPOS
    ===================================================== */

    [

        campoFecha,
        campoDuracion,
        campoHoraInicio,
        campoHoraFin,
        campoSector

    ].forEach(
        elemento => {

            if (!elemento) {
                return;
            }

            elemento.addEventListener(
                'change',
                invalidarVistaPrevia
            );

            elemento.addEventListener(
                'input',
                invalidarVistaPrevia
            );

        }
    );


    checkboxHabilitado.addEventListener(
        'change',
        invalidarVistaPrevia
    );

    checkboxMultiprofesional.addEventListener(
        'change',
        invalidarVistaPrevia
    );


    /* =====================================================
       GUARDAR AGENDA
    ===================================================== */

    async function guardarAgenda() {

        if (guardandoAgenda) {
            return;
        }

        if (
            !datosPrevisualizacion ||
            cuposPrevisualizados.length === 0
        ) {

            alert(
                'Primero debes generar una vista previa válida.'
            );

            return;
        }

        if (
            datosPrevisualizacion.id_profesional !==
                campoProfesional.value ||
            datosPrevisualizacion.id_departamento !==
                selectDepartamento.value ||
            datosPrevisualizacion.id_servicio !==
                selectServicio.value ||
            datosPrevisualizacion.fecha !==
                campoFecha.value
        ) {

            invalidarVistaPrevia();

            alert(
                'La configuración cambió. Genera una nueva vista previa.'
            );

            return;
        }

        guardandoAgenda = true;

        botonGuardar.disabled = true;
        botonPrevisualizar.disabled = true;

        botonGuardar.textContent =
            'Guardando agenda...';

        try {

            /*
             * PREPARAR FORMULARIO
             */

            const datos =
                new FormData(formulario);

            /*
             * ENVIAR ESTADOS BOOLEANOS EXPLÍCITAMENTE
             */

            datos.set(
                'segmento_habilitado',
                checkboxHabilitado.checked ? '1' : '0'
            );

            datos.set(
                'origen_multiprofesional',
                checkboxMultiprofesional.checked ? '1' : '0'
            );

            datos.set(
                'inicio_diferenciado',
                checkboxDiferenciado.checked ? '1' : '0'
            );

            /*
             * ENVIAR HORARIO CALCULADO
             */

            datos.set(
                'hora_inicio',
                datosPrevisualizacion.hora_inicio
            );

            datos.set(
                'hora_fin',
                datosPrevisualizacion.hora_fin
            );

            datos.set(
                'duracion_minutos',
                String(datosPrevisualizacion.duracion_minutos)
            );

            /*
             * ENVIAR TODOS LOS CUPOS
             */

            datos.set(
                'cupos',
                JSON.stringify(cuposPrevisualizados)
            );

            /*
             * ENVIAR HORA DE REFERENCIA
             */

            datos.set(
                'hora_referencia',
                campoHoraReferencia.value
            );

            /*
             * SOLICITAR GUARDADO
             */

            const respuesta =
                await fetch(
                    API_CREAR_AGENDA,
                    {
                        method: 'POST',
                        body: datos,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

            const resultadoServidor =
                await leerRespuestaJSON(respuesta);

            if (
                !respuesta.ok ||
                !resultadoServidor.ok
            ) {

                throw new Error(
                    resultadoServidor.mensaje ||
                    resultadoServidor.error ||
                    'No fue posible guardar la agenda.'
                );

            }

            /*
             * GUARDADO EXITOSO
             */

            alert(
                resultadoServidor.mensaje ||
                `Agenda guardada correctamente. Cupos generados: ${resultadoServidor.total_cupos ?? cuposPrevisualizados.length}.`
            );

            /*
             * CONSERVAR AL PROFESIONAL SELECCIONADO
             * AL ACTUALIZAR LA PÁGINA.
             */

            const url =
                new URL(window.location.href);

            url.searchParams.set(
                'profesional',
                campoProfesional.value
            );

            window.location.href =
                url.toString();

        } catch (error) {

            console.error(
                'Error al guardar agenda:',
                error
            );

            alert(
                error.message ||
                'Ocurrió un error al guardar la agenda.'
            );

            guardandoAgenda = false;

            botonGuardar.disabled =
                !datosPrevisualizacion;

            botonPrevisualizar.disabled = false;

            botonGuardar.textContent =
                'Guardar agenda';

        }

    }


    botonGuardar.addEventListener(
        'click',
        guardarAgenda
    );


    /* =====================================================
       CLIC EN CELDA VACÍA DEL CALENDARIO
    ===================================================== */

    document.addEventListener(
        'click',
        evento => {

            const boton =
                evento.target.closest('.week-cell-generar');

            if (!boton) {
                return;
            }

            const fecha =
                boton.dataset.fecha;

            const hora =
                boton.dataset.hora;

            if (!fecha || !hora) {

                console.error(
                    'La celda no tiene fecha u hora asignada.'
                );

                return;
            }

            abrirModal(fecha, hora);

        }
    );


    /* =====================================================
       EVITAR ENVÍO NATIVO DEL FORMULARIO
    ===================================================== */

    formulario.addEventListener(
        'submit',
        evento => {
            evento.preventDefault();
        }
    );


    /* =====================================================
       INICIALIZAR
    ===================================================== */

    prepararServicios();

    inicializarProfesionales();

    /*
     * Preparar los servicios del departamento
     * que viene seleccionado desde agenda.php.
     */

    if (selectDepartamento.value) {

        cargarServiciosPorDepartamento(
            selectDepartamento.value
        );

    }

});
