
// +----------------------------------------------------------------------
// | AGENDA CESFAM
// +----------------------------------------------------------------------
// | Archivo único: navegación, reservas, generación y edición de agendas.
// +----------------------------------------------------------------------

/* =========================================================
   PARÁMETROS DE LA URL
========================================================= */

const parametrosURL = new URLSearchParams(window.location.search);

let departamentoSeleccionado = parametrosURL.get('departamento') || '';
let servicioSeleccionado = parametrosURL.get('servicio') || '';
let profesionalSeleccionado = parametrosURL.get('profesional') || '';
let fechaSeleccionada = parametrosURL.get('fecha') || '';

/* =========================================================
   OBTENER FECHA LOCAL
========================================================= */

function obtenerFechaLocal() {
    const fecha = new Date();

    const año = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    const dia = String(fecha.getDate()).padStart(2, '0');

    return `${año}-${mes}-${dia}`;
}

/* =========================================================
   VALIDAR FECHA
========================================================= */

function fechaValida(fecha) {
    if (
        typeof fecha !== 'string' ||
        !/^\d{4}-\d{2}-\d{2}$/.test(fecha)
    ) {
        return false;
    }

    const partes = fecha.split('-').map(Number);

    const fechaObjeto = new Date(
        partes[0],
        partes[1] - 1,
        partes[2]
    );

    return (
        fechaObjeto.getFullYear() === partes[0] &&
        fechaObjeto.getMonth() === partes[1] - 1 &&
        fechaObjeto.getDate() === partes[2]
    );
}

if (!fechaValida(fechaSeleccionada)) {
    fechaSeleccionada = obtenerFechaLocal();
}

/* =========================================================
   PROFESIONAL: SINCRONIZAR SELECTOR Y URL
========================================================= */

const selectorProfesionalAgenda =
    document.getElementById('profesionalAgenda');

const formularioFiltroProfesional =
    document.getElementById('formFiltroProfesionalAgenda');

function sincronizarProfesionalSeleccionado() {
    if (selectorProfesionalAgenda) {
        profesionalSeleccionado =
            selectorProfesionalAgenda.value || '';
    }

    return profesionalSeleccionado;
}

if (selectorProfesionalAgenda) {
    selectorProfesionalAgenda.addEventListener('change', function () {
        profesionalSeleccionado = this.value || '';
    });
}

if (formularioFiltroProfesional) {
    formularioFiltroProfesional.addEventListener('submit', function () {
        sincronizarProfesionalSeleccionado();
    });
}

/* =========================================================
   DESPLAZAR FECHA EN EL CALENDARIO
========================================================= */

function desplazarFecha(fecha, cantidadDias) {
    if (!fechaValida(fecha)) return '';

    const partes = fecha.split('-').map(Number);

    const fechaObjeto = new Date(
        partes[0],
        partes[1] - 1,
        partes[2]
    );

    fechaObjeto.setDate(
        fechaObjeto.getDate() + cantidadDias
    );

    const año = fechaObjeto.getFullYear();
    const mes = String(fechaObjeto.getMonth() + 1).padStart(2, '0');
    const dia = String(fechaObjeto.getDate()).padStart(2, '0');

    return `${año}-${mes}-${dia}`;
}

/* =========================================================
   CONSTRUIR PARÁMETROS DE NAVEGACIÓN
========================================================= */

function construirParametrosAgenda(fecha, incluirResumen = false) {
    const parametros = new URLSearchParams();

    if (departamentoSeleccionado) {
        parametros.set('departamento', departamentoSeleccionado);
    }

    if (servicioSeleccionado) {
        parametros.set('servicio', servicioSeleccionado);
    }

    const profesionalActual =
        sincronizarProfesionalSeleccionado();

    if (profesionalActual) {
        parametros.set('profesional', profesionalActual);
    }

    parametros.set('fecha', fecha);

    if (incluirResumen) {
        parametros.set('abrir_resumen', '1');
    }

    return parametros;
}

/* =========================================================
   NAVEGAR A UNA FECHA
========================================================= */

function navegarAgenda(fecha, abrirPopup = false) {
    if (!fechaValida(fecha)) return;

    const parametros =
        construirParametrosAgenda(fecha, abrirPopup);

    window.location.href =
        'agenda.php?' + parametros.toString();
}

/* =========================================================
   NAVEGACIÓN SEMANAL
========================================================= */

function cambiarSemana(cantidad) {
    const nuevaFecha = desplazarFecha(
        fechaSeleccionada,
        cantidad * 7
    );

    if (nuevaFecha) {
        navegarAgenda(nuevaFecha);
    }
}

/* =========================================================
   CAMBIAR DEPARTAMENTO
========================================================= */

const selectorDepartamento =
    document.getElementById('departamentoAgenda');

if (selectorDepartamento) {
    selectorDepartamento.addEventListener('change', function () {
        departamentoSeleccionado = this.value || '';
        servicioSeleccionado = '';

        navegarAgenda(fechaSeleccionada);
    });
}

/* =========================================================
   CAMBIAR DÍA DESDE SELECTOR DE FECHA
========================================================= */

const selectoresFechaAgenda =
    document.querySelectorAll('#fechaSeleccionadaAgenda');

selectoresFechaAgenda.forEach(function (selectorFechaAgenda) {
    selectorFechaAgenda.addEventListener('change', function () {
        const nuevaFecha = this.value;

        if (!fechaValida(nuevaFecha)) return;

        fechaSeleccionada = nuevaFecha;

        navegarAgenda(nuevaFecha);
    });
});

/* =========================================================
   BOTONES DE NAVEGACIÓN
========================================================= */

const botonSemanaAnterior =
    document.getElementById('mesAnterior');

if (botonSemanaAnterior) {
    botonSemanaAnterior.addEventListener('click', function () {
        cambiarSemana(-1);
    });
}

const botonSemanaSiguiente =
    document.getElementById('mesSiguiente');

if (botonSemanaSiguiente) {
    botonSemanaSiguiente.addEventListener('click', function () {
        cambiarSemana(1);
    });
}

const botonHoy = document.getElementById('btnHoy');

if (botonHoy) {
    botonHoy.addEventListener('click', function () {
        navegarAgenda(obtenerFechaLocal());
    });
}

/* =========================================================
   SELECCIONAR DÍA DE LA SEMANA
========================================================= */

const diasCalendario =
    document.querySelectorAll('.week-day-header[data-fecha]');

diasCalendario.forEach(function (dia) {
    dia.addEventListener('click', function (evento) {
        evento.preventDefault();

        const fecha = this.getAttribute('data-fecha');

        if (fechaValida(fecha)) {
            navegarAgenda(fecha);
        }
    });
});

/* =========================================================
   ELEMENTOS DEL POPUP RESUMEN
========================================================= */

const agendaModalOverlay =
    document.getElementById('agendaModalOverlay');

const agendaModalClose =
    document.getElementById('agendaModalClose');

const agendaModalCancel =
    document.getElementById('agendaModalCancel');

const agendaModalVerAgenda =
    document.getElementById('agendaModalVerAgenda');

const agendaModalTitle =
    document.getElementById('agendaModalTitle');

const agendaModalFecha =
    document.getElementById('agendaModalFecha');

const agendaModalTotal =
    document.getElementById('agendaModalTotal');

const agendaModalServicio =
    document.getElementById('agendaModalServicio');

const agendaModalReservas =
    document.getElementById('agendaModalReservas');

const agendaModalLoading =
    document.getElementById('agendaModalLoading');

const agendaModalContent =
    document.getElementById('agendaModalContent');

const agendaModalError =
    document.getElementById('agendaModalError');

let fechaModalSeleccionada = '';

/* =========================================================
   ESCAPAR HTML
========================================================= */

function escaparHTML(texto) {
    const div = document.createElement('div');

    div.textContent =
        texto === null || texto === undefined
            ? ''
            : String(texto);

    return div.innerHTML;
}

/* =========================================================
   FORMATEAR FECHA
========================================================= */

function formatearFecha(fecha) {
    if (!fechaValida(fecha)) return fecha;

    const partes = fecha.split('-');

    const año = parseInt(partes[0], 10);
    const mes = parseInt(partes[1], 10);
    const dia = parseInt(partes[2], 10);

    const fechaObjeto = new Date(
        año,
        mes - 1,
        dia
    );

    const nombresMeses = [
        '',
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre'
    ];

    const nombresDias = [
        'domingo',
        'lunes',
        'martes',
        'miércoles',
        'jueves',
        'viernes',
        'sábado'
    ];

    const nombreDia =
        nombresDias[fechaObjeto.getDay()];

    return (
        nombreDia.charAt(0).toUpperCase() +
        nombreDia.slice(1) +
        ' ' +
        dia +
        ' de ' +
        nombresMeses[mes] +
        ' de ' +
        año
    );
}

/* =========================================================
   ABRIR RESUMEN DEL DÍA
========================================================= */

function abrirResumenDia(fecha) {
    if (!agendaModalOverlay) return;

    fechaModalSeleccionada = fecha;

    agendaModalOverlay.classList.add('active');

    agendaModalOverlay.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.classList.add('agenda-modal-open');

    if (agendaModalFecha) {
        agendaModalFecha.textContent =
            formatearFecha(fecha);
    }

    if (agendaModalTitle) {
        agendaModalTitle.textContent =
            'Resumen del día';
    }

    if (agendaModalTotal) {
        agendaModalTotal.textContent = '0';
    }

    if (agendaModalServicio) {
        agendaModalServicio.textContent = 'Cargando...';
    }

    if (agendaModalReservas) {
        agendaModalReservas.innerHTML = '';
    }

    if (agendaModalError) {
        agendaModalError.textContent = '';
        agendaModalError.classList.remove('active');
    }

    if (agendaModalContent) {
        agendaModalContent.classList.remove('active');
    }

    if (agendaModalLoading) {
        agendaModalLoading.classList.add('active');
    }

    cargarResumenDia(fecha);
}

/* =========================================================
   CARGAR RESUMEN DEL DÍA
========================================================= */

function cargarResumenDia(fecha) {
    const parametros = new URLSearchParams();

    if (departamentoSeleccionado) {
        parametros.set(
            'departamento',
            departamentoSeleccionado
        );
    }

    if (servicioSeleccionado) {
        parametros.set(
            'servicio',
            servicioSeleccionado
        );
    }

    const profesionalActual =
        sincronizarProfesionalSeleccionado();

    if (profesionalActual) {
        parametros.set(
            'profesional',
            profesionalActual
        );
    }

    parametros.set('fecha', fecha);

    const url =
        '../api/resumen_agenda.php?' +
        parametros.toString();

    fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(respuesta => respuesta.text())
    .then(texto => {
        let datos;

        try {
            datos = JSON.parse(texto);
        } catch (error) {
            throw new Error(
                'La respuesta del servidor no contiene JSON válido.'
            );
        }

        if (!datos.ok) {
            throw new Error(
                datos.error ||
                datos.mensaje ||
                'No fue posible obtener el resumen.'
            );
        }

        mostrarResumenDia(datos);
    })
    .catch(error => {
        console.error('Error resumen agenda:', error);

        if (agendaModalLoading) {
            agendaModalLoading.classList.remove('active');
        }

        if (agendaModalContent) {
            agendaModalContent.classList.remove('active');
        }

        if (agendaModalError) {
            agendaModalError.textContent =
                error.message ||
                'No fue posible consultar las reservas del día.';

            agendaModalError.classList.add('active');
        }
    });
}

/* =========================================================
   MOSTRAR RESUMEN DEL DÍA
========================================================= */

function mostrarResumenDia(datos) {
    if (agendaModalLoading) {
        agendaModalLoading.classList.remove('active');
    }

    if (agendaModalError) {
        agendaModalError.classList.remove('active');
    }

    if (agendaModalContent) {
        agendaModalContent.classList.add('active');
    }

    if (agendaModalTitle) {
        agendaModalTitle.textContent =
            'Resumen del día';
    }

    if (agendaModalFecha) {
        agendaModalFecha.textContent =
            formatearFecha(datos.fecha);
    }

    if (agendaModalTotal) {
        agendaModalTotal.textContent =
            datos.total_reservas ?? 0;
    }

    if (agendaModalServicio) {
        agendaModalServicio.textContent =
            datos.servicio || '';
    }

    if (!agendaModalReservas) return;

    agendaModalReservas.innerHTML = '';

    if (
        !datos.reservas ||
        datos.reservas.length === 0
    ) {
        const vacio = document.createElement('div');

        vacio.className = 'agenda-modal-empty';

        vacio.innerHTML =
            '<span class="agenda-modal-empty-icon">📭</span>' +
            '<strong>No hay reservas agendadas</strong>' +
            '<span>No existen reservas confirmadas para este día.</span>';

        agendaModalReservas.appendChild(vacio);

        return;
    }

    datos.reservas.forEach(reserva => {
        const tarjeta = document.createElement('div');

        tarjeta.className = 'agenda-modal-reserva';

        const descripcion =
            reserva.observacion &&
            String(reserva.observacion).trim() !== ''
                ? reserva.observacion
                : 'Sin descripción';

        tarjeta.innerHTML =
            '<div class="agenda-modal-reserva-top">' +
                '<div class="agenda-modal-reserva-hora">' +
                    escaparHTML(reserva.hora) +
                '</div>' +
                '<span class="agenda-modal-reserva-estado">Confirmada</span>' +
            '</div>' +
            '<div class="agenda-modal-reserva-persona">' +
                '<div class="agenda-modal-avatar">' +
                    escaparHTML(obtenerIniciales(reserva.nombre)) +
                '</div>' +
                '<div class="agenda-modal-persona-datos">' +
                    '<strong>' +
                        escaparHTML(reserva.nombre) +
                    '</strong>' +
                    '<span>' +
                        escaparHTML(reserva.servicio) +
                    '</span>' +
                '</div>' +
            '</div>' +
            '<div class="agenda-modal-reserva-descripcion">' +
                '<span class="agenda-modal-descripcion-icon">📝</span>' +
                '<div>' +
                    '<strong>Descripción</strong>' +
                    '<p>' +
                        escaparHTML(descripcion) +
                    '</p>' +
                '</div>' +
            '</div>';

        agendaModalReservas.appendChild(tarjeta);
    });
}

/* =========================================================
   OBTENER INICIALES
========================================================= */

function obtenerIniciales(nombre) {
    if (!nombre) return 'RS';

    const partes = String(nombre)
        .trim()
        .split(/\s+/);

    let iniciales = '';

    if (partes[0]) {
        iniciales += partes[0]
            .substring(0, 1)
            .toUpperCase();
    }

    if (partes[1]) {
        iniciales += partes[1]
            .substring(0, 1)
            .toUpperCase();
    }

    return iniciales || 'RS';
}

/* =========================================================
   CERRAR RESUMEN DEL DÍA
========================================================= */

function cerrarResumenDia() {
    if (!agendaModalOverlay) return;

    agendaModalOverlay.classList.remove('active');

    agendaModalOverlay.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.classList.remove('agenda-modal-open');

    fechaModalSeleccionada = '';
}

document.addEventListener('click', function (evento) {
    const botonCerrar =
        evento.target.closest('#agendaModalClose');

    if (!botonCerrar) return;

    evento.preventDefault();
    evento.stopPropagation();

    cerrarResumenDia();
}, true);

if (agendaModalCancel) {
    agendaModalCancel.addEventListener('click', function (evento) {
        evento.preventDefault();
        evento.stopPropagation();

        cerrarResumenDia();
    });
}

if (agendaModalOverlay) {
    agendaModalOverlay.addEventListener('click', function (evento) {
        if (evento.target === agendaModalOverlay) {
            cerrarResumenDia();
        }
    });
}

document.addEventListener('keydown', function (evento) {
    if (
        evento.key === 'Escape' &&
        agendaModalOverlay &&
        agendaModalOverlay.classList.contains('active')
    ) {
        cerrarResumenDia();
    }
});

if (agendaModalVerAgenda) {
    agendaModalVerAgenda.addEventListener('click', function (evento) {
        evento.preventDefault();
        evento.stopPropagation();

        cerrarResumenDia();

        window.location.href = 'gestionar_reserva.php';
    });
}

const abrirResumenAutomaticamente =
    parametrosURL.get('abrir_resumen');

if (
    abrirResumenAutomaticamente === '1' &&
    fechaSeleccionada
) {
    abrirResumenDia(fechaSeleccionada);
}

/* =========================================================
   API DE RESERVAS Y ELEMENTOS DEL FORMULARIO
========================================================= */

const API_DEPARTAMENTOS_URL =
    '../api/obtener_departamentos.php';

const API_HORARIOS_URL =
    '../api/obtener_horarios.php';

const API_CREAR_URL =
    '../api/crear_reserva.php';

const createReservationSection =
    document.getElementById('createReservationSection');

const createReservationForm =
    document.getElementById('createReservationForm');

const createNombre =
    document.getElementById('createNombre');

const createRut =
    document.getElementById('createRut');

const createTelefono =
    document.getElementById('createTelefono');

const createCorreo =
    document.getElementById('createCorreo');

const createDepartamento =
    document.getElementById('createDepartamento');

const createServicio =
    document.getElementById('createServicio');

const createFecha =
    document.getElementById('createFecha');

const createHorario =
    document.getElementById('createHorario');

const createHorarioEstado =
    document.getElementById('createHorarioEstado');

const createObservacion =
    document.getElementById('createObservacion');

const btnCancelCreate =
    document.getElementById('btnCancelCreate');

const btnCloseCreateModal =
    document.getElementById('btnCloseCreateModal');

const errorNombre =
    document.getElementById('errorNombre');

const errorRut =
    document.getElementById('errorRut');

const errorTelefono =
    document.getElementById('errorTelefono');

const errorCorreo =
    document.getElementById('errorCorreo');

const errorDepartamento =
    document.getElementById('errorDepartamento');

const errorServicio =
    document.getElementById('errorServicio');

const errorFecha =
    document.getElementById('errorFecha');

const errorHorario =
    document.getElementById('errorHorario');

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

const notification =
    document.getElementById('createReservationNotification');

const notificationMessage =
    document.getElementById('createReservationNotificationMessage');

let contextoReserva = {
    departamento: '',
    servicio: '',
    fecha: '',
    hora: '',
    servicioFijo: false,
    fechaFija: false
};

let datosPersonalesValidosAnteriormente = false;

let departamentosFormulario = [];

/* =========================================================
   MOSTRAR NOTIFICACIÓN
========================================================= */

function mostrarNotificacion(mensaje, tipo = 'error') {
    if (!notification || !notificationMessage) {
        alert(mensaje);
        return;
    }

    notificationMessage.textContent = mensaje;

    notification.classList.remove(
        'success',
        'error',
        'warning',
        'show'
    );

    notification.classList.add(tipo, 'show');

    setTimeout(() => {
        notification.classList.remove('show');
    }, 5000);
}

/* =========================================================
   VALIDAR RUT CHILENO
========================================================= */

function validarRutChileno(rut) {
    rut = rut
        .replace(/\./g, '')
        .replace(/-/g, '')
        .trim()
        .toUpperCase();

    if (!/^\d{7,8}[0-9K]$/.test(rut)) {
        return false;
    }

    const cuerpo = rut.slice(0, -1);
    const dv = rut.slice(-1);

    let suma = 0;
    let multiplicador = 2;

    for (let i = cuerpo.length - 1; i >= 0; i--) {
        suma += parseInt(cuerpo[i], 10) * multiplicador;

        multiplicador =
            multiplicador === 7 ? 2 : multiplicador + 1;
    }

    const resultado = 11 - (suma % 11);

    const dvCalculado =
        resultado === 11
            ? '0'
            : (resultado === 10 ? 'K' : String(resultado));

    return dv === dvCalculado;
}

/* =========================================================
   FORMATEAR RUT CHILENO
========================================================= */

function formatearRut(rut) {
    let valor = String(rut || '')
        .replace(/[^0-9kK]/g, '')
        .toUpperCase();

    if (valor.length === 0) return '';

    let cuerpo = valor.slice(0, -1);
    const dv = valor.slice(-1);

    cuerpo = cuerpo
        .replace(/\D/g, '')
        .slice(0, 8);

    if (!cuerpo) return dv;

    let cuerpoFormateado = '';

    while (cuerpo.length > 3) {
        cuerpoFormateado =
            '.' + cuerpo.slice(-3) + cuerpoFormateado;

        cuerpo = cuerpo.slice(0, -3);
    }

    return cuerpo + cuerpoFormateado + '-' + dv;
}

/* =========================================================
   VALIDAR IDENTIFICACIÓN EXTRANJERA
========================================================= */

function validarIdentificacionExtranjera(valor) {
    return /^[A-Za-z0-9.\-]{4,20}$/.test(valor.trim());
}

/* =========================================================
   OBTENER TIPO DE IDENTIFICACIÓN
========================================================= */

function obtenerTipoIdentificacion() {
    const seleccionado = document.querySelector(
        'input[name="tipo_identificacion"]:checked'
    );

    return seleccionado
        ? seleccionado.value
        : 'rut';
}

/* =========================================================
   VALIDAR IDENTIFICACIÓN
========================================================= */

function validarIdentificacion() {
    const tipo = obtenerTipoIdentificacion();

    const valor = createRut
        ? createRut.value.trim()
        : '';

    if (!valor) return false;

    if (tipo === 'rut') {
        return validarRutChileno(valor);
    }

    if (
        tipo === 'rut_extranjero' ||
        tipo === 'pasaporte'
    ) {
        return validarIdentificacionExtranjera(valor);
    }

    return false;
}

/* =========================================================
   VALIDAR NOMBRE
========================================================= */

function validarNombre() {
    if (!createNombre) return false;

    const nombre = createNombre.value.trim();

    if (nombre.length < 2 || nombre.length > 100) {
        return false;
    }

    return /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s.'-]+$/.test(nombre);
}

/* =========================================================
   VALIDAR TELÉFONO
========================================================= */

function validarTelefono() {
    if (!createTelefono) return false;

    const telefono = createTelefono.value.trim();

    if (telefono.length < 7 || telefono.length > 20) {
        return false;
    }

    return /^[0-9+\s().\-]+$/.test(telefono);
}

/* =========================================================
   VALIDAR CORREO
========================================================= */

function validarCorreo() {
    if (!createCorreo) return true;

    const correo = createCorreo.value.trim();

    if (!correo) return true;

    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo);
}

/* =========================================================
   VALIDAR DATOS PERSONALES
========================================================= */

function datosPersonalesValidos() {
    return (
        validarNombre() &&
        validarIdentificacion() &&
        validarTelefono() &&
        validarCorreo()
    );
}

/* =========================================================
   OBTENER NOMBRE DEPARTAMENTO
========================================================= */

function obtenerNombreDepartamentoSeleccionado() {
    if (!createDepartamento) return '';

    const opcion =
        createDepartamento.options[
            createDepartamento.selectedIndex
        ];

    return opcion
        ? opcion.textContent.trim()
        : '';
}

/* =========================================================
   OBTENER NOMBRE SERVICIO
========================================================= */

function obtenerNombreServicioSeleccionado() {
    if (!createServicio) return '';

    const opcion =
        createServicio.options[
            createServicio.selectedIndex
        ];

    return opcion
        ? opcion.textContent.trim()
        : '';
}

/* =========================================================
   BUSCAR DEPARTAMENTO POR SERVICIO
========================================================= */

function obtenerDepartamentoPorServicio(idServicio) {
    if (!idServicio) return null;

    const id = String(idServicio);

    for (const departamento of departamentosFormulario) {
        const servicioEncontrado =
            (departamento.servicios || []).find(
                servicio =>
                    String(servicio.id_servicio) === id
            );

        if (servicioEncontrado) {
            return {
                departamento: departamento,
                servicio: servicioEncontrado
            };
        }
    }

    return null;
}

/* =========================================================
   CARGAR DEPARTAMENTOS
========================================================= */

async function cargarDepartamentosCrear() {
    if (!createDepartamento) return;

    createDepartamento.disabled = true;

    createDepartamento.innerHTML =
        '<option value="">Cargando departamentos...</option>';

    try {
        const respuesta = await fetch(
            API_DEPARTAMENTOS_URL,
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        const datos = await respuesta.json();

        if (!datos.ok) {
            throw new Error(
                datos.mensaje ||
                'No fue posible cargar los departamentos.'
            );
        }

        departamentosFormulario =
            Array.isArray(datos.departamentos)
                ? datos.departamentos
                : [];

        createDepartamento.innerHTML =
            '<option value="">Selecciona un departamento</option>';

        departamentosFormulario.forEach(departamento => {
            const option =
                document.createElement('option');

            option.value = departamento.id_departamento;

            option.textContent = departamento.nombre;

            option.disabled =
                departamento.seleccionable !== true;

            option.dataset.seleccionable =
                departamento.seleccionable ? '1' : '0';

            option.dataset.servicios =
                JSON.stringify(departamento.servicios || []);

            createDepartamento.appendChild(option);
        });

        createDepartamento.disabled = false;

        if (contextoReserva.departamento) {
            const existeDepartamento =
                Array.from(createDepartamento.options).some(
                    option =>
                        option.value === String(
                            contextoReserva.departamento
                        ) &&
                        !option.disabled
                );

            if (existeDepartamento) {
                createDepartamento.value =
                    String(contextoReserva.departamento);

                cargarServiciosPorDepartamento(
                    contextoReserva.departamento,
                    contextoReserva.servicio
                );
            }
        }

    } catch (error) {
        console.error(
            'Error cargando departamentos:',
            error
        );

        createDepartamento.innerHTML =
            '<option value="">No se pudieron cargar los departamentos</option>';

        createDepartamento.disabled = true;

        if (errorDepartamento) {
            errorDepartamento.textContent =
                error.message ||
                'No fue posible cargar los departamentos.';
        }

        mostrarNotificacion(
            error.message ||
            'No fue posible cargar los departamentos.',
            'error'
        );
    }
}

/* =========================================================
   CARGAR SERVICIOS POR DEPARTAMENTO
========================================================= */

function cargarServiciosPorDepartamento(
    idDepartamento,
    idServicioSeleccionado = ''
) {
    if (!createServicio) return;

    const departamento =
        departamentosFormulario.find(
            item =>
                String(item.id_departamento) ===
                String(idDepartamento)
        );

    createServicio.innerHTML =
        '<option value="">Selecciona un servicio</option>';

    if (
        !departamento ||
        !departamento.servicios ||
        departamento.servicios.length === 0
    ) {
        createServicio.disabled = true;

        createServicio.innerHTML =
            '<option value="">Este departamento no tiene servicios disponibles</option>';

        if (errorServicio) {
            errorServicio.textContent = '';
        }

        actualizarPreview();

        return;
    }

    departamento.servicios.forEach(servicio => {
        const option =
            document.createElement('option');

        option.value = servicio.id_servicio;

        option.textContent = servicio.nombre;

        option.dataset.idServicio =
            servicio.id_servicio;

        option.dataset.descripcion =
            servicio.descripcion || '';

        createServicio.appendChild(option);
    });

    createServicio.disabled = false;

    if (idServicioSeleccionado) {
        const existeServicio =
            Array.from(createServicio.options).some(
                option =>
                    option.value ===
                    String(idServicioSeleccionado)
            );

        if (existeServicio) {
            createServicio.value =
                String(idServicioSeleccionado);
        }
    }

    actualizarPreview();
}

/* =========================================================
   ACTUALIZAR PREVISUALIZACIÓN
========================================================= */

function actualizarPreview() {
    if (previewNombre) {
        previewNombre.textContent =
            createNombre?.value.trim() || 'Pendiente';
    }

    if (previewIdentificacion) {
        const tipo = obtenerTipoIdentificacion();

        const valor =
            createRut?.value.trim() || '';

        if (valor) {
            let textoTipo = 'RUT';

            if (tipo === 'rut_extranjero') {
                textoTipo = 'RUT extranjero';
            }

            if (tipo === 'pasaporte') {
                textoTipo = 'Pasaporte';
            }

            previewIdentificacion.textContent =
                textoTipo + ': ' + valor;

        } else {
            previewIdentificacion.textContent =
                'Pendiente';
        }
    }

    if (previewDepartamento) {
        previewDepartamento.textContent =
            obtenerNombreDepartamentoSeleccionado() ||
            'Pendiente';
    }

    if (previewServicio) {
        previewServicio.textContent =
            obtenerNombreServicioSeleccionado() ||
            'Pendiente';
    }

    if (previewFecha) {
        previewFecha.textContent =
            createFecha?.value || 'Pendiente';
    }

    if (previewHora) {
        previewHora.textContent =
            createHorario?.value || 'Pendiente';
    }
}

/* =========================================================
   NORMALIZAR BOOLEANOS
========================================================= */

function agendaValorVerdadero(valor) {
    return (
        valor === true ||
        valor === 1 ||
        valor === '1' ||
        valor === 'true' ||
        valor === 't'
    );
}

/* =========================================================
   CARGAR HORARIOS PARA CREAR RESERVA
========================================================= */

async function cargarHorariosCrear(horaSeleccionada = '') {
    if (
        !createServicio ||
        !createFecha ||
        !createHorario
    ) {
        return;
    }

    const idServicio = createServicio.value;
    const fecha = createFecha.value;

    if (!idServicio || !fecha) {
        createHorario.disabled = true;

        createHorario.innerHTML =
            '<option value="">Selecciona servicio y fecha</option>';

        if (createHorarioEstado) {
            createHorarioEstado.textContent =
                'Selecciona un servicio y una fecha.';
        }

        actualizarPreview();

        return;
    }

    createHorario.disabled = true;

    createHorario.innerHTML =
        '<option value="">Cargando horarios...</option>';

    if (createHorarioEstado) {
        createHorarioEstado.textContent =
            'Consultando cupos de las agendas...';
    }

    try {
        const url =
            `${API_HORARIOS_URL}?servicio=${encodeURIComponent(idServicio)}&fecha=${encodeURIComponent(fecha)}`;

        const respuesta = await fetch(
            url,
            {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.ok) {
            throw new Error(
                datos.mensaje ||
                datos.error ||
                'No fue posible obtener los horarios.'
            );
        }

        createHorario.innerHTML =
            '<option value="">Selecciona un horario</option>';

        let horasDisponibles = 0;

        (datos.horarios || []).forEach(horario => {
            const option =
                document.createElement('option');

            /*
             * La hora se conserva para mostrarla.
             * El ID identifica el cupo real en la base de datos.
             */

            option.value = horario.hora;

            option.dataset.idHorario =
                String(horario.id_horario || '');

            option.textContent = horario.hora;

            const disponible =
                agendaValorVerdadero(horario.disponible);

            if (
                !horario.id_horario ||
                !disponible
            ) {
                option.disabled = true;

                option.textContent =
                    `${horario.hora} - No disponible`;

            } else {
                horasDisponibles++;
            }

            createHorario.appendChild(option);
        });

        if (horaSeleccionada) {
            const opcion =
                Array.from(createHorario.options).find(
                    option =>
                        option.value === horaSeleccionada &&
                        !option.disabled
                );

            if (opcion) {
                createHorario.value = horaSeleccionada;

            } else {
                mostrarNotificacion(
                    'El horario seleccionado ya no está disponible.',
                    'warning'
                );
            }
        }

        createHorario.disabled =
            horasDisponibles === 0;

        if (createHorarioEstado) {
            if (horasDisponibles > 0) {
                createHorarioEstado.textContent =
                    `${horasDisponibles} horario(s) disponible(s).`;

            } else if ((datos.horarios || []).length === 0) {
                createHorarioEstado.textContent =
                    'No existe una agenda publicada con cupos para este día. No hay horas disponibles para reservar.';

            } else {
                createHorarioEstado.textContent =
                    'No quedan cupos disponibles para esta fecha.';
            }
        }

        actualizarPreview();

    } catch (error) {
        console.error(
            'Error cargando horarios:',
            error
        );

        createHorario.innerHTML =
            '<option value="">No se pudieron cargar los horarios</option>';

        createHorario.disabled = true;

        if (createHorarioEstado) {
            createHorarioEstado.textContent =
                'No fue posible consultar los cupos de las agendas.';
        }

        mostrarNotificacion(
            error.message ||
            'No fue posible cargar los horarios.',
            'error'
        );
    }
}

/* =========================================================
   ACTUALIZAR ESTADO DEL FORMULARIO
========================================================= */

function actualizarEstadoFormulario() {
    const datosValidos =
        datosPersonalesValidos();

    if (!datosValidos) {
        datosPersonalesValidosAnteriormente = false;

        if (createDepartamento) {
            createDepartamento.disabled = true;
        }

        if (createServicio) {
            createServicio.disabled = true;
        }

        if (createFecha) {
            createFecha.disabled = true;
        }

        if (createHorario) {
            createHorario.disabled = true;

            createHorario.innerHTML =
                '<option value="">Completa los datos personales</option>';
        }

        if (createHorarioEstado) {
            createHorarioEstado.textContent =
                'Completa correctamente nombre, identificación y teléfono.';
        }

        actualizarPreview();

        return;
    }

    datosPersonalesValidosAnteriormente = true;

    if (createDepartamento) {
        createDepartamento.disabled = false;
    }

    if (createServicio) {
        createServicio.disabled =
            !createDepartamento?.value;
    }

    if (createFecha) {
        createFecha.disabled =
            !createServicio?.value;
    }

    if (
        createServicio?.value &&
        createFecha?.value
    ) {
        cargarHorariosCrear(contextoReserva.hora);

    } else {
        if (createHorario) {
            createHorario.disabled = true;

            createHorario.innerHTML =
                '<option value="">Selecciona servicio y fecha</option>';
        }

        if (createHorarioEstado) {
            createHorarioEstado.textContent =
                'Selecciona un servicio y una fecha.';
        }
    }

    actualizarPreview();
}

/* =========================================================
   ABRIR FORMULARIO CREAR RESERVA
========================================================= */

async function abrirCrearReserva(
    servicio = '',
    servicioNombre = '',
    fecha = '',
    hora = ''
) {
    if (!createReservationSection) return;

    const resultadoServicio =
        obtenerDepartamentoPorServicio(servicio);

    const idDepartamento =
        resultadoServicio?.departamento?.id_departamento || '';

    contextoReserva = {
        departamento: idDepartamento,
        servicio: servicio || '',
        fecha: fecha || '',
        hora: hora || '',
        servicioFijo: false,
        fechaFija: false
    };

    datosPersonalesValidosAnteriormente = false;

    if (createNombre) createNombre.value = '';
    if (createRut) createRut.value = '';
    if (createTelefono) createTelefono.value = '';
    if (createCorreo) createCorreo.value = '';
    if (createObservacion) createObservacion.value = '';

    const radioRut = document.querySelector(
        'input[name="tipo_identificacion"][value="rut"]'
    );

    if (radioRut) {
        radioRut.checked = true;
    }

    if (createDepartamento) {
        await cargarDepartamentosCrear();

        if (idDepartamento) {
            createDepartamento.value =
                String(idDepartamento);

            cargarServiciosPorDepartamento(
                idDepartamento,
                servicio
            );
        }

    } else if (createServicio) {
        createServicio.value = servicio || '';
    }

    if (createDepartamento) {
        createDepartamento.disabled = false;
    }

    if (createServicio) {
        createServicio.value = servicio || '';

        createServicio.disabled =
            !createDepartamento?.value;
    }

    if (createFecha) {
        createFecha.value = fecha || '';

        createFecha.disabled =
            !createServicio?.value;
    }

    if (createHorario) {
        createHorario.innerHTML =
            '<option value="">Completa los datos personales</option>';

        createHorario.value = '';
        createHorario.disabled = true;
    }

    if (createHorarioEstado) {
        createHorarioEstado.textContent =
            'Completa correctamente nombre, identificación y teléfono.';
    }

    createReservationSection.hidden = false;

    createReservationSection.style.display = 'flex';

    createReservationSection.classList.add('active');

    document.body.classList.add('modal-open');

    actualizarPreview();

    actualizarEstadoFormulario();
}

/* =========================================================
   CERRAR FORMULARIO CREAR RESERVA
========================================================= */

function cerrarCrearReserva() {
    if (!createReservationSection) return;

    createReservationSection.hidden = true;

    createReservationSection.style.display = 'none';

    createReservationSection.classList.remove('active');

    document.body.classList.remove('modal-open');

    contextoReserva = {
        departamento: '',
        servicio: '',
        fecha: '',
        hora: '',
        servicioFijo: false,
        fechaFija: false
    };

    datosPersonalesValidosAnteriormente = false;
}

/* =========================================================
   BOTÓN CANCELAR DEL FORMULARIO
========================================================= */

if (btnCancelCreate) {
    btnCancelCreate.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        cerrarCrearReserva();
    });
}

/* =========================================================
   BOTÓN X DEL FORMULARIO
========================================================= */

document.addEventListener('click', function (event) {
    const botonCerrar =
        event.target.closest('#btnCloseCreateModal');

    if (!botonCerrar) return;

    event.preventDefault();
    event.stopPropagation();

    cerrarCrearReserva();
}, true);

/* =========================================================
   CAMBIAR DEPARTAMENTO DEL FORMULARIO
========================================================= */

if (createDepartamento) {
    createDepartamento.addEventListener('change', function () {
        if (!datosPersonalesValidos()) {
            actualizarEstadoFormulario();
            return;
        }

        const idDepartamento = this.value;

        contextoReserva.departamento = idDepartamento;
        contextoReserva.servicio = '';
        contextoReserva.fecha = '';
        contextoReserva.hora = '';

        cargarServiciosPorDepartamento(idDepartamento);

        if (createFecha) {
            createFecha.value = '';
            createFecha.disabled = true;
        }

        if (createHorario) {
            createHorario.value = '';
            createHorario.disabled = true;

            createHorario.innerHTML =
                '<option value="">Selecciona un servicio</option>';
        }

        if (createHorarioEstado) {
            createHorarioEstado.textContent =
                'Selecciona un servicio.';
        }

        if (errorDepartamento) {
            errorDepartamento.textContent = '';
        }

        if (errorServicio) {
            errorServicio.textContent = '';
        }

        actualizarPreview();
    });
}

/* =========================================================
   CAMBIAR SERVICIO DEL FORMULARIO
========================================================= */

if (createServicio) {
    createServicio.addEventListener('change', function () {
        if (!datosPersonalesValidos()) {
            actualizarEstadoFormulario();
            return;
        }

        contextoReserva.servicio = this.value;
        contextoReserva.fecha = '';
        contextoReserva.hora = '';

        if (createFecha) {
            createFecha.value = '';
            createFecha.disabled = !this.value;
        }

        if (createHorario) {
            createHorario.value = '';
            createHorario.disabled = true;

            createHorario.innerHTML =
                '<option value="">Selecciona una fecha</option>';
        }

        if (createHorarioEstado) {
            createHorarioEstado.textContent =
                'Selecciona una fecha.';
        }

        if (errorServicio) {
            errorServicio.textContent = '';
        }

        actualizarPreview();
    });
}

/* =========================================================
   CAMBIAR FECHA DEL FORMULARIO
========================================================= */

if (createFecha) {
    createFecha.addEventListener('change', function () {
        if (!datosPersonalesValidos()) {
            actualizarEstadoFormulario();
            return;
        }

        const fecha = createFecha.value;

        if (!fecha) {
            if (createHorario) {
                createHorario.value = '';
                createHorario.disabled = true;

                createHorario.innerHTML =
                    '<option value="">Selecciona una fecha</option>';
            }

            contextoReserva.hora = '';
            contextoReserva.fecha = '';

            actualizarPreview();

            return;
        }

        if (errorFecha) {
            errorFecha.textContent = '';
        }

        contextoReserva.fecha = fecha;
        contextoReserva.hora = '';

        cargarHorariosCrear();

        actualizarPreview();
    });
}

/* =========================================================
   CAMBIAR HORARIO DEL FORMULARIO
========================================================= */

if (createHorario) {
    createHorario.addEventListener('change', function () {
        if (errorHorario) {
            errorHorario.textContent = '';
        }

        contextoReserva.hora = this.value || '';

        actualizarPreview();
    });
}

/* =========================================================
   VALIDACIÓN DE DATOS PERSONALES
========================================================= */

[
    createNombre,
    createRut,
    createTelefono,
    createCorreo
].forEach(elemento => {
    if (!elemento) return;

    elemento.addEventListener('input', () => {
        if (
            elemento === createRut &&
            obtenerTipoIdentificacion() === 'rut'
        ) {
            const valorAnterior = createRut.value;

            createRut.value =
                formatearRut(createRut.value);

            if (createRut.value !== valorAnterior) {
                createRut.selectionStart =
                    createRut.value.length;

                createRut.selectionEnd =
                    createRut.value.length;
            }
        }

        actualizarEstadoFormulario();
        actualizarPreview();
    });

    elemento.addEventListener('blur', () => {
        if (
            elemento === createRut &&
            obtenerTipoIdentificacion() === 'rut'
        ) {
            createRut.value =
                formatearRut(createRut.value);
        }

        actualizarEstadoFormulario();
        actualizarPreview();
    });
});

/* =========================================================
   CAMBIO DE TIPO DE IDENTIFICACIÓN
========================================================= */

document.querySelectorAll(
    'input[name="tipo_identificacion"]'
).forEach(radio => {
    radio.addEventListener('change', function () {
        if (
            obtenerTipoIdentificacion() === 'rut' &&
            createRut
        ) {
            createRut.value =
                formatearRut(createRut.value);
        }

        if (errorRut) {
            errorRut.textContent = '';
        }

        actualizarEstadoFormulario();
        actualizarPreview();
    });
});

/* =========================================================
   BOTONES DE CREACIÓN DE RESERVA
========================================================= */

const botonesAgregarHora = document.querySelectorAll(
    '.btn-agregar-hora, #btnAgregarHora, #btnAgregarHoraAgenda, #btnAgregarHoraDia, #btnReservaAsistida'
);

botonesAgregarHora.forEach(boton => {
    boton.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const servicio =
            boton.dataset.servicio ||
            servicioSeleccionado ||
            '';

        const servicioNombre =
            boton.dataset.servicioNombre || '';

        const fecha =
            boton.dataset.fecha ||
            fechaSeleccionada ||
            '';

        abrirCrearReserva(
            servicio,
            servicioNombre,
            fecha,
            ''
        );
    });
});

/* =========================================================
   BOTONES RESERVAR DEL CALENDARIO Y LISTA DIARIA
========================================================= */

const botonesReservar =
    document.querySelectorAll('.time-action.reserve');

botonesReservar.forEach(boton => {
    boton.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const servicio =
            boton.dataset.servicio ||
            servicioSeleccionado ||
            '';

        const servicioNombre =
            boton.dataset.servicioNombre || '';

        const fecha =
            boton.dataset.fecha ||
            fechaSeleccionada ||
            '';

        const hora =
            boton.dataset.hora || '';

        if (!servicio || !fecha || !hora) {
            mostrarNotificacion(
                'No fue posible obtener los datos del horario.',
                'error'
            );

            return;
        }

        abrirCrearReserva(
            servicio,
            servicioNombre,
            fecha,
            hora
        );
    });
});

/* =========================================================
   BOTONES VER RESERVA
========================================================= */

const botonesVerReserva =
    document.querySelectorAll('.time-action.view');

botonesVerReserva.forEach(boton => {
    boton.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const idReserva =
            boton.dataset.idReserva || '';

        const destino = idReserva
            ? `gestionar_reserva.php?id_reserva=${encodeURIComponent(idReserva)}`
            : 'gestionar_reserva.php';

        window.location.href = destino;
    });
});

/* =========================================================
   EDICIÓN INDIVIDUAL DE CUPOS
========================================================= */

const modalEditarHorario =
    document.getElementById('modalEditarHorario');

const formularioEditarHorario =
    document.getElementById('formEditarHorario');

const editarIdHorario =
    document.getElementById('editarIdHorario');

const editarServicioHorario =
    document.getElementById('editarServicioHorario');

const editarFechaHorario =
    document.getElementById('editarFechaHorario');

const editarHoraHorario =
    document.getElementById('editarHoraHorario');

const editarHoraFinHorario =
    document.getElementById('editarHoraFinHorario');

const editarEstadoHorario =
    document.getElementById('editarEstadoHorario');

const editarObservacionHorario =
    document.getElementById('editarObservacionHorario');

const editarHabilitadoHorario =
    document.getElementById('editarHabilitadoHorario');

const editarDisponibleWebHorario =
    document.getElementById('editarDisponibleWebHorario');

const botonCerrarEditarHorario =
    document.getElementById('cerrarModalEditarHorario');

const botonCancelarEditarHorario =
    document.getElementById('btnCancelarEdicionHorario');

const botonGuardarEditarHorario =
    document.getElementById('btnGuardarEdicionHorario');

const API_EDITAR_HORARIO_URL =
    '../api/editar_horario.php';

/* =========================================================
   VERIFICAR QUE EXISTA EL FORMULARIO DE EDICIÓN
========================================================= */

function formularioEdicionDisponible() {
    return (
        modalEditarHorario &&
        formularioEditarHorario &&
        editarIdHorario &&
        editarServicioHorario &&
        editarFechaHorario &&
        editarHoraHorario &&
        editarHoraFinHorario &&
        editarEstadoHorario &&
        editarObservacionHorario &&
        editarHabilitadoHorario &&
        editarDisponibleWebHorario
    );
}

/* =========================================================
   ABRIR MODAL DE EDICIÓN
========================================================= */

function abrirEditarHorario(boton) {
    if (!formularioEdicionDisponible()) {
        console.error(
            'No se encontró el modal de edición en agenda.php.'
        );

        mostrarNotificacion(
            'No se encontró el formulario para editar el cupo.',
            'error'
        );

        return;
    }

    const idHorario =
        boton.dataset.idHorario || '';

    const servicio =
        boton.dataset.servicioNombre || '';

    const fecha =
        boton.dataset.fecha || '';

    const hora =
        boton.dataset.hora || '';

    const horaFin =
        boton.dataset.horaFin || '';

    const estado =
        boton.dataset.estado || 'disponible';

    const observacion =
        boton.dataset.observacion || '';

    const habilitado =
        boton.dataset.habilitado === '1';

    const disponibleWeb =
        boton.dataset.disponibleWeb === '1';

    if (
        !idHorario ||
        !fechaValida(fecha) ||
        !/^\d{2}:\d{2}$/.test(hora)
    ) {
        mostrarNotificacion(
            'Los datos del cupo seleccionado están incompletos.',
            'error'
        );

        return;
    }

    editarIdHorario.value = idHorario;

    editarServicioHorario.value = servicio;

    editarFechaHorario.value = fecha;

    editarHoraHorario.value = hora;

    editarHoraFinHorario.value = horaFin;

    editarEstadoHorario.value =
        estado === 'bloqueado'
            ? 'bloqueado'
            : 'disponible';

    editarObservacionHorario.value =
        observacion;

    editarHabilitadoHorario.checked =
        habilitado;

    editarDisponibleWebHorario.checked =
        disponibleWeb;

    if (botonGuardarEditarHorario) {
        botonGuardarEditarHorario.disabled = false;

        botonGuardarEditarHorario.textContent =
            'Guardar cambios';
    }

    modalEditarHorario.classList.add('activo');

    modalEditarHorario.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.classList.add('modal-open');

    editarEstadoHorario.focus();
}

/* =========================================================
   CERRAR MODAL DE EDICIÓN
========================================================= */

function cerrarEditarHorario() {
    if (!modalEditarHorario) return;

    modalEditarHorario.classList.remove('activo');

    modalEditarHorario.setAttribute(
        'aria-hidden',
        'true'
    );

    if (formularioEditarHorario) {
        formularioEditarHorario.reset();
    }

    if (botonGuardarEditarHorario) {
        botonGuardarEditarHorario.disabled = false;

        botonGuardarEditarHorario.textContent =
            'Guardar cambios';
    }

    const formularioReservaAbierto =
        createReservationSection &&
        createReservationSection.classList.contains('active');

    const generadorAbierto =
        document.getElementById('modalGenerarAgenda')
            ?.classList.contains('activo');

    if (
        !formularioReservaAbierto &&
        !generadorAbierto
    ) {
        document.body.classList.remove('modal-open');
    }
}

/* =========================================================
   DETECTAR CLIC EN LOS CUPOS EDITABLES
========================================================= */

document.addEventListener('click', function (evento) {
    const boton = evento.target.closest('.edit-horario');

    if (!boton) return;

    evento.preventDefault();
    evento.stopPropagation();

    abrirEditarHorario(boton);
});

/* =========================================================
   BOTÓN CERRAR DEL MODAL DE EDICIÓN
========================================================= */

if (botonCerrarEditarHorario) {
    botonCerrarEditarHorario.addEventListener(
        'click',
        function (evento) {
            evento.preventDefault();
            evento.stopPropagation();

            cerrarEditarHorario();
        }
    );
}

/* =========================================================
   BOTÓN CANCELAR DEL MODAL DE EDICIÓN
========================================================= */

if (botonCancelarEditarHorario) {
    botonCancelarEditarHorario.addEventListener(
        'click',
        function (evento) {
            evento.preventDefault();
            evento.stopPropagation();

            cerrarEditarHorario();
        }
    );
}

/* =========================================================
   CERRAR MODAL AL HACER CLIC EN EL FONDO
========================================================= */

if (modalEditarHorario) {
    modalEditarHorario.addEventListener(
        'click',
        function (evento) {
            if (evento.target === modalEditarHorario) {
                cerrarEditarHorario();
            }
        }
    );
}

/* =========================================================
   CERRAR MODAL DE EDICIÓN CON ESCAPE
========================================================= */

document.addEventListener('keydown', function (evento) {
    if (
        evento.key === 'Escape' &&
        modalEditarHorario &&
        modalEditarHorario.classList.contains('activo')
    ) {
        cerrarEditarHorario();
    }
});

/* =========================================================
   VALIDAR DATOS DEL MODAL DE EDICIÓN
========================================================= */

function validarDatosEdicionHorario() {
    if (!formularioEdicionDisponible()) {
        return {
            valido: false,
            mensaje: 'No se encontró el formulario de edición.'
        };
    }

    const idHorario =
        editarIdHorario.value.trim();

    const fecha =
        editarFechaHorario.value;

    const hora =
        editarHoraHorario.value;

    const horaFin =
        editarHoraFinHorario.value;

    const estado =
        editarEstadoHorario.value;

    if (!/^\d+$/.test(idHorario)) {
        return {
            valido: false,
            mensaje: 'El identificador del cupo no es válido.'
        };
    }

    if (!fechaValida(fecha)) {
        return {
            valido: false,
            mensaje: 'La fecha del cupo no es válida.'
        };
    }

    if (!/^\d{2}:\d{2}$/.test(hora)) {
        return {
            valido: false,
            mensaje: 'Ingresa una hora de inicio válida.'
        };
    }

    if (horaFin !== '') {
        if (!/^\d{2}:\d{2}$/.test(horaFin)) {
            return {
                valido: false,
                mensaje: 'La hora de finalización no es válida.'
            };
        }

        const partesInicio =
            hora.split(':').map(Number);

        const partesFin =
            horaFin.split(':').map(Number);

        const minutosInicio =
            partesInicio[0] * 60 + partesInicio[1];

        const minutosFin =
            partesFin[0] * 60 + partesFin[1];

        if (minutosFin <= minutosInicio) {
            return {
                valido: false,
                mensaje: 'La hora de finalización debe ser posterior a la hora de inicio.'
            };
        }
    }

    if (
        estado !== 'disponible' &&
        estado !== 'bloqueado'
    ) {
        return {
            valido: false,
            mensaje: 'Selecciona un estado válido para el cupo.'
        };
    }

    if (
        editarObservacionHorario.value.length > 500
    ) {
        return {
            valido: false,
            mensaje: 'La observación no puede superar los 500 caracteres.'
        };
    }

    return {
        valido: true,
        mensaje: ''
    };
}

/* =========================================================
   GUARDAR CAMBIOS DEL CUPO
========================================================= */

async function guardarEdicionHorario(evento) {
    evento.preventDefault();

    if (!formularioEdicionDisponible()) {
        mostrarNotificacion(
            'No se encontró el formulario de edición.',
            'error'
        );

        return;
    }

    if (
        botonGuardarEditarHorario &&
        botonGuardarEditarHorario.disabled
    ) {
        return;
    }

    const validacion =
        validarDatosEdicionHorario();

    if (!validacion.valido) {
        mostrarNotificacion(
            validacion.mensaje,
            'error'
        );

        return;
    }

    const fechaActualizada =
        editarFechaHorario.value;

    const datos = new FormData(
        formularioEditarHorario
    );

    datos.set(
        'habilitado',
        editarHabilitadoHorario.checked ? '1' : '0'
    );

    datos.set(
        'disponible_web',
        editarDisponibleWebHorario.checked ? '1' : '0'
    );

    datos.set(
        'id_horario',
        editarIdHorario.value
    );

    datos.set(
        'hora',
        editarHoraHorario.value
    );

    datos.set(
        'hora_fin',
        editarHoraFinHorario.value || ''
    );

    datos.set(
        'estado',
        editarEstadoHorario.value
    );

    datos.set(
        'observacion',
        editarObservacionHorario.value.trim()
    );

    if (botonGuardarEditarHorario) {
        botonGuardarEditarHorario.disabled = true;

        botonGuardarEditarHorario.textContent =
            'Guardando cambios...';
    }

    try {
        const respuesta = await fetch(
            API_EDITAR_HORARIO_URL,
            {
                method: 'POST',
                body: datos,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            }
        );

        const texto = await respuesta.text();

        let resultado;

        try {
            resultado = JSON.parse(texto);

        } catch (error) {
            console.error(
                'Respuesta de editar_horario.php:',
                texto
            );

            throw new Error(
                'El servidor no devolvió una respuesta JSON válida. Revisa el registro de errores de PHP.'
            );
        }

        if (!respuesta.ok || !resultado.ok) {
            throw new Error(
                resultado.mensaje ||
                resultado.error ||
                'No fue posible actualizar el cupo.'
            );
        }

        cerrarEditarHorario();

        mostrarNotificacion(
            resultado.mensaje ||
            'El cupo fue actualizado correctamente.',
            'success'
        );

        setTimeout(function () {
            navegarAgenda(fechaActualizada);
        }, 700);

    } catch (error) {
        console.error(
            'Error al editar horario:',
            error
        );

        mostrarNotificacion(
            error.message ||
            'Ocurrió un error al actualizar el cupo.',
            'error'
        );

        if (botonGuardarEditarHorario) {
            botonGuardarEditarHorario.disabled = false;

            botonGuardarEditarHorario.textContent =
                'Guardar cambios';
        }
    }
}

/* =========================================================
   EVENTO DEL FORMULARIO DE EDICIÓN
========================================================= */

if (formularioEditarHorario) {
    formularioEditarHorario.addEventListener(
        'submit',
        guardarEdicionHorario
    );
}

/* =========================================================
   ENVIAR FORMULARIO CREAR RESERVA
========================================================= */

if (createReservationForm) {
    createReservationForm.addEventListener(
        'submit',
        async function (event) {
            event.preventDefault();

            let valido = true;

            if (!validarNombre()) {
                valido = false;

                if (errorNombre) {
                    errorNombre.textContent =
                        'Ingresa un nombre válido.';
                }

            } else if (errorNombre) {
                errorNombre.textContent = '';
            }

            if (!validarIdentificacion()) {
                valido = false;

                if (errorRut) {
                    const tipo =
                        obtenerTipoIdentificacion();

                    errorRut.textContent =
                        tipo === 'rut'
                            ? 'Ingresa un RUT válido.'
                            : 'Ingresa una identificación válida.';
                }

            } else if (errorRut) {
                errorRut.textContent = '';
            }

            if (!validarTelefono()) {
                valido = false;

                if (errorTelefono) {
                    errorTelefono.textContent =
                        'Ingresa un teléfono válido.';
                }

            } else if (errorTelefono) {
                errorTelefono.textContent = '';
            }

            if (!validarCorreo()) {
                valido = false;

                if (errorCorreo) {
                    errorCorreo.textContent =
                        'Ingresa un correo válido.';
                }

            } else if (errorCorreo) {
                errorCorreo.textContent = '';
            }

            const departamento =
                createDepartamento?.value || '';

            if (!departamento) {
                valido = false;

                if (errorDepartamento) {
                    errorDepartamento.textContent =
                        'Selecciona un departamento.';
                }

            } else if (errorDepartamento) {
                errorDepartamento.textContent = '';
            }

            const idServicio =
                createServicio?.value || '';

            const servicioNombreSeleccionado =
                obtenerNombreServicioSeleccionado();

            if (
                !idServicio ||
                !servicioNombreSeleccionado
            ) {
                valido = false;

                if (errorServicio) {
                    errorServicio.textContent =
                        'Selecciona un servicio.';
                }

            } else if (errorServicio) {
                errorServicio.textContent = '';
            }

            const fecha =
                createFecha?.value || '';

            if (!fecha) {
                valido = false;

                if (errorFecha) {
                    errorFecha.textContent =
                        'Selecciona una fecha.';
                }

            } else if (errorFecha) {
                errorFecha.textContent = '';
            }

            const opcionHorario =
                createHorario?.selectedOptions?.[0] || null;

            const hora =
                createHorario?.value || '';

            const idHorario =
                opcionHorario?.dataset.idHorario || '';

            if (!hora || !/^\d+$/.test(idHorario)) {
                valido = false;

                if (errorHorario) {
                    errorHorario.textContent =
                        'Selecciona un cupo disponible de la agenda.';
                }

            } else if (errorHorario) {
                errorHorario.textContent = '';
            }

            if (!valido) {
                actualizarEstadoFormulario();

                mostrarNotificacion(
                    'Completa correctamente todos los campos requeridos.',
                    'error'
                );

                return;
            }

            const datos =
                new FormData(createReservationForm);

            datos.set(
                'nombre',
                createNombre.value.trim()
            );

            datos.set(
                'rut',
                createRut.value.trim()
            );

            datos.set(
                'tipo_identificacion',
                obtenerTipoIdentificacion()
            );

            datos.set(
                'telefono',
                createTelefono.value.trim()
            );

            datos.set(
                'correo',
                createCorreo?.value.trim() || ''
            );

            datos.set(
                'departamento',
                departamento
            );

            datos.set(
                'servicio',
                idServicio
            );

            datos.set(
                'fecha',
                fecha
            );

            datos.set(
                'hora',
                hora
            );

            datos.set(
                'id_horario',
                idHorario
            );

            datos.set(
                'observacion',
                createObservacion?.value.trim() || ''
            );

            datos.set(
                'modo',
                'funcionario'
            );

            const botonSubmit =
                createReservationForm.querySelector(
                    'button[type="submit"]'
                );

            if (botonSubmit) {
                botonSubmit.disabled = true;

                botonSubmit.dataset.textoOriginal =
                    botonSubmit.textContent;

                botonSubmit.textContent =
                    'Reservando...';
            }

            try {
                const respuesta = await fetch(
                    API_CREAR_URL,
                    {
                        method: 'POST',
                        body: datos,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const texto = await respuesta.text();

                let resultado;

                try {
                    resultado = JSON.parse(texto);

                } catch (error) {
                    console.error(
                        'Respuesta de crear_reserva.php:',
                        texto
                    );

                    throw new Error(
                        'El servidor no devolvió una respuesta JSON válida.'
                    );
                }

                if (
                    !respuesta.ok ||
                    !resultado.ok
                ) {
                    throw new Error(
                        resultado.mensaje ||
                        resultado.error ||
                        'No fue posible crear la reserva.'
                    );
                }

                mostrarNotificacion(
                    'La hora fue reservada correctamente.',
                    'success'
                );

                cerrarCrearReserva();

                setTimeout(() => {
                    navegarAgenda(fechaSeleccionada);
                }, 800);

            } catch (error) {
                console.error(
                    'Error creando reserva:',
                    error
                );

                mostrarNotificacion(
                    error.message ||
                    'No fue posible crear la reserva.',
                    'error'
                );

            } finally {
                if (botonSubmit) {
                    botonSubmit.disabled = false;

                    botonSubmit.textContent =
                        botonSubmit.dataset.textoOriginal ||
                        'Reservar';
                }
            }
        }
    );
}

/* =========================================================
   ESTADO INICIAL DEL FORMULARIO
========================================================= */

if (createReservationSection) {
    createReservationSection.hidden = true;

    createReservationSection.style.display = 'none';

    createReservationSection.classList.remove('active');
}

if (createFecha) {
    createFecha.min = obtenerFechaLocal();
}

/* =========================================================
   EXPONER FUNCIONES
========================================================= */

window.navegarAgenda = navegarAgenda;

window.cambiarSemana = cambiarSemana;

window.abrirResumenDia = abrirResumenDia;

window.abrirCrearReserva = abrirCrearReserva;

window.cerrarCrearReserva = cerrarCrearReserva;

window.abrirEditarHorario = abrirEditarHorario;

window.cerrarEditarHorario = cerrarEditarHorario;

/* ======================================================================
   GENERACIÓN DE AGENDAS
====================================================================== */

function inicializarGeneradorAgenda() {

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

    const selectDepartamentoAgenda =
        document.getElementById('tipoAtencionAgenda');

    const selectServicioAgenda =
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
       API
    ===================================================== */

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
        selectDepartamentoAgenda,
        selectServicioAgenda,
        campoFecha,
        campoDuracion,
        campoSector,
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
       ESCAPAR HTML / MOSTRAR MENSAJE
    ===================================================== */

    function escaparHTMLGenerador(valor) {
        const elemento =
            document.createElement('div');

        elemento.textContent =
            String(valor ?? '');

        return elemento.innerHTML;
    }

    function mostrarMensaje(mensaje, tipo = 'error') {
        resultado.innerHTML =
            `<div class="mensaje-agenda-${tipo}">${escaparHTMLGenerador(mensaje)}</div>`;
    }

    /* =====================================================
       INVALIDAR VISTA PREVIA
    ===================================================== */

    function invalidarVistaPrevia() {
        cuposPrevisualizados = [];

        datosPrevisualizacion = null;

        botonGuardar.disabled = true;

        if (
            !generandoVistaPrevia &&
            !guardandoAgenda
        ) {
            resultado.innerHTML = '';
        }
    }

    /* =====================================================
       CONVERSIÓN DE HORAS
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

    function minutosAHora(minutos) {
        const horas = Math.floor(minutos / 60);

        const resto = minutos % 60;

        return (
            String(horas).padStart(2, '0') +
            ':' +
            String(resto).padStart(2, '0')
        );
    }

    function validarFechaGenerador(fecha) {
        if (
            typeof fecha !== 'string' ||
            !/^\d{4}-\d{2}-\d{2}$/.test(fecha)
        ) {
            return false;
        }

        const partes = fecha.split('-').map(Number);

        const objeto = new Date(
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

    function leerRespuestaJSON(respuesta) {
        return respuesta.text().then(texto => {
            try {
                return JSON.parse(texto);

            } catch (error) {
                console.error(
                    'Respuesta recibida del servidor:',
                    texto
                );

                throw new Error(
                    'El servidor no devolvió una respuesta JSON válida. Revisa los errores de PHP.'
                );
            }
        });
    }

    /* =====================================================
       INICIALIZAR Y SELECCIONAR PROFESIONALES
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

        campoProfesional.value = opcion.value;

        nombreProfesional.value =
            opcion.textContent.trim();

        campoInstrumento.value =
            opcion.dataset.estamento || '';

        informacionProfesional.textContent =
            `Profesional seleccionado: ${opcion.textContent.trim()}`;
    }

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
            function () {
                seleccionarProfesional();

                sincronizarProfesionalSeleccionado();
            }
        );

        if (selectProfesional.value) {
            seleccionarProfesional();

        } else {
            informacionProfesional.textContent =
                'No hay ningún profesional seleccionado.';
        }
    }

    buscarProfesional.addEventListener('input', () => {
        const busqueda =
            buscarProfesional.value.toLowerCase().trim();

        Array.from(selectProfesional.options).forEach(
            (opcion, indice) => {
                if (indice === 0) return;

                opcion.hidden = !opcion.textContent
                    .toLowerCase()
                    .includes(busqueda);
            }
        );
    });

    /* =====================================================
       SERVICIOS DEL FORMULARIO DE GENERACIÓN
    ===================================================== */

    function prepararServicios() {
        serviciosOriginales =
            Array.from(selectServicioAgenda.options)
                .filter(opcion => opcion.value !== '')
                .map(opcion => ({
                    value: opcion.value,
                    texto: opcion.textContent.trim(),
                    departamento: opcion.dataset.departamento || ''
                }));
    }

    function cargarServiciosAgenda(
        idDepartamento,
        servicioSeleccionado = ''
    ) {
        selectServicioAgenda.innerHTML = '';

        const opcionInicial =
            document.createElement('option');

        opcionInicial.value = '';

        opcionInicial.textContent =
            'Selecciona un servicio';

        selectServicioAgenda.appendChild(
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
            selectServicioAgenda.disabled = true;

            opcionInicial.textContent =
                idDepartamento
                    ? 'Este departamento no tiene servicios disponibles'
                    : 'Selecciona primero un tipo de atención';

            return;
        }

        servicios.forEach(servicio => {
            const opcion =
                document.createElement('option');

            opcion.value = servicio.value;

            opcion.textContent = servicio.texto;

            selectServicioAgenda.appendChild(opcion);
        });

        selectServicioAgenda.disabled = false;

        if (servicioSeleccionado) {
            const existe = servicios.some(
                servicio =>
                    String(servicio.value) ===
                    String(servicioSeleccionado)
            );

            if (existe) {
                selectServicioAgenda.value =
                    String(servicioSeleccionado);
            }
        }
    }

    selectDepartamentoAgenda.addEventListener(
        'change',
        () => {
            cargarServiciosAgenda(
                selectDepartamentoAgenda.value
            );

            invalidarVistaPrevia();
        }
    );

    selectServicioAgenda.addEventListener(
        'change',
        invalidarVistaPrevia
    );

    /* =====================================================
       ABRIR MODAL DE GENERACIÓN
    ===================================================== */

    function abrirModal(fecha, hora) {
        if (!selectProfesional.value) {
            alert(
                'Primero debes seleccionar un profesional y pulsar Ver agenda.'
            );

            selectProfesional.focus();

            return;
        }

        if (!validarFechaGenerador(fecha)) {
            alert('La fecha seleccionada no es válida.');
            return;
        }

        const profesionalEnURL =
            new URLSearchParams(
                window.location.search
            ).get('profesional') || '';

        if (
            profesionalEnURL &&
            profesionalEnURL !== selectProfesional.value
        ) {
            alert(
                'Has cambiado el profesional. Pulsa Ver agenda antes de generar cupos.'
            );

            return;
        }

        const departamentoInicial =
            selectDepartamentoAgenda.value;

        const opcion =
            selectProfesional.selectedOptions[0];

        formulario.reset();

        selectDepartamentoAgenda.value =
            departamentoInicial;

        invalidarVistaPrevia();

        campoProfesional.value = opcion.value;

        nombreProfesional.value =
            opcion.textContent.trim();

        campoInstrumento.value =
            opcion.dataset.estamento || '';

        campoFecha.value = fecha;

        campoFecha.min = obtenerFechaLocal();

        horaSeleccionada = hora;

        campoHoraReferencia.value = hora;

        /*
         * No se completa automáticamente el horario.
         * El usuario debe activar Inicio diferenciado.
         */

        checkboxDiferenciado.checked = false;

        campoHoraInicio.value = '';
        campoHoraFin.value = '';

        campoHoraInicio.required = false;
        campoHoraFin.required = false;

        camposDiferenciados.hidden = true;

        cargarServiciosAgenda(
            departamentoInicial
        );

        modal.classList.add('activo');

        modal.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add('modal-open');
    }

    /* =====================================================
       CERRAR MODAL DE GENERACIÓN
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

        campoHoraInicio.required = false;
        campoHoraFin.required = false;

        selectServicioAgenda.disabled = true;

        resultado.innerHTML = '';

        botonGuardar.disabled = true;

        botonPrevisualizar.disabled = false;

        botonGuardar.textContent =
            'Guardar agenda';

        horaSeleccionada = '';

        seleccionarProfesional();

        if (
            !modalEditarHorario?.classList.contains('activo') &&
            !createReservationSection?.classList.contains('active')
        ) {
            document.body.classList.remove('modal-open');
        }
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

    modal.addEventListener('click', evento => {
        if (evento.target === modal) {
            cerrarModal();
        }
    });

    document.addEventListener('keydown', evento => {
        if (
            evento.key === 'Escape' &&
            modal.classList.contains('activo')
        ) {
            cerrarModal();
        }
    });

    /* =====================================================
       INICIO DIFERENCIADO
    ===================================================== */

    checkboxDiferenciado.addEventListener(
        'change',
        () => {
            const activado =
                checkboxDiferenciado.checked;

            camposDiferenciados.hidden =
                !activado;

            campoHoraInicio.required = activado;
            campoHoraFin.required = activado;

            if (activado) {
                campoHoraInicio.value =
                    horaSeleccionada || '';

                campoHoraFin.value = '';

            } else {
                campoHoraInicio.value = '';
                campoHoraFin.value = '';
            }

            invalidarVistaPrevia();
        }
    );

    /* =====================================================
       GENERAR CUPOS
    ===================================================== */

    function generarCupos(
        horaInicio,
        horaFin,
        duracion
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

        const cupos = [];

        let actual = inicio;

        while (actual < fin) {
            const siguiente =
                Math.min(actual + duracion, fin);

            if (siguiente <= actual) {
                break;
            }

            cupos.push({
                numero: cupos.length + 1,
                hora_inicio: minutosAHora(actual),
                hora_fin: minutosAHora(siguiente),
                duracion_minutos: siguiente - actual
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
        if (
            generandoVistaPrevia ||
            guardandoAgenda
        ) {
            return;
        }

        invalidarVistaPrevia();

        if (!campoProfesional.value) {
            return mostrarMensaje(
                'Selecciona un profesional.'
            );
        }

        if (!selectDepartamentoAgenda.value) {
            return mostrarMensaje(
                'Selecciona un tipo de atención.'
            );
        }

        if (!selectServicioAgenda.value) {
            return mostrarMensaje(
                'Selecciona un servicio.'
            );
        }

        if (!validarFechaGenerador(campoFecha.value)) {
            return mostrarMensaje(
                'Selecciona una fecha válida.'
            );
        }

        if (campoFecha.value < obtenerFechaLocal()) {
            return mostrarMensaje(
                'No se pueden generar agendas para fechas anteriores a hoy.'
            );
        }

        /*
         * REGLA PRINCIPAL:
         * Si no está marcado Inicio diferenciado,
         * no se genera ninguna agenda.
         */

        if (!checkboxDiferenciado.checked) {
            return mostrarMensaje(
                'No se puede generar la agenda porque no has activado Inicio diferenciado. Define la hora de inicio y la hora de finalización. Si no se configura una agenda para este día, no habrá horas disponibles para reservar.',
                'error'
            );
        }

        const inicio = campoHoraInicio.value;
        const fin = campoHoraFin.value;
        const duracion = Number(campoDuracion.value);

        try {
            generandoVistaPrevia = true;

            botonPrevisualizar.disabled = true;

            if (!inicio || !fin) {
                throw new Error(
                    'Debes indicar la hora de inicio y finalización.'
                );
            }

            const minutosInicio =
                horaAMinutos(inicio);

            const minutosFin =
                horaAMinutos(fin);

            if (
                minutosInicio === null ||
                minutosFin === null
            ) {
                throw new Error(
                    'Las horas seleccionadas no tienen un formato válido.'
                );
            }

            if (minutosFin <= minutosInicio) {
                throw new Error(
                    'La hora de finalización debe ser posterior a la hora de inicio.'
                );
            }

            /*
             * El calendario admite el rango 07:00 a 21:00.
             */

            if (
                minutosInicio < 7 * 60 ||
                minutosFin > 21 * 60
            ) {
                throw new Error(
                    'El horario debe estar comprendido entre las 07:00 y las 21:00.'
                );
            }

            if (
                !Number.isInteger(duracion) ||
                duracion <= 0
            ) {
                throw new Error(
                    'Selecciona una duración válida para los cupos.'
                );
            }

            /*
             * Generar exclusivamente con los datos
             * introducidos en el formulario de agenda.
             */

            const nuevosCupos = generarCupos(
                inicio,
                fin,
                duracion
            );

            if (nuevosCupos.length === 0) {
                throw new Error(
                    'No se pudieron generar cupos dentro del horario seleccionado.'
                );
            }

            cuposPrevisualizados = nuevosCupos;

            datosPrevisualizacion = {
                id_profesional: campoProfesional.value,
                id_departamento: selectDepartamentoAgenda.value,
                id_servicio: selectServicioAgenda.value,
                fecha: campoFecha.value,
                sector: campoSector.value.trim() || 'No informado',
                hora_inicio: inicio,
                hora_fin: fin,
                duracion_minutos: duracion,
                segmento_habilitado: checkboxHabilitado.checked,
                origen_multiprofesional: checkboxMultiprofesional.checked,
                inicio_diferenciado: true,
                tipo_configuracion: 'agenda_manual',
                hora_referencia: campoHoraReferencia.value
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

    const total = cuposPrevisualizados.length;

    const duracionTotal = cuposPrevisualizados.reduce(
        (suma, cupo) => suma + cupo.duracion_minutos,
        0
    );

    /*
     * DATOS DEL PROFESIONAL Y CONFIGURACIÓN
     */

    const nombreProfesionalActual =
        nombreProfesional.value.trim() || 'No especificado';

    const nombreDepartamento =
        selectDepartamentoAgenda.selectedOptions[0]
            ?.textContent.trim() || 'No especificado';

    const nombreServicio =
        selectServicioAgenda.selectedOptions[0]
            ?.textContent.trim() || 'No especificado';

    const instrumento =
        campoInstrumento.value.trim() || 'No informado';

    const sector =
        campoSector.value.trim() || 'No informado';

    const fechaOriginal =
        datosPrevisualizacion.fecha;

    const partesFecha = fechaOriginal.split('-');

    const fechaFormateada =
        partesFecha.length === 3
            ? `${partesFecha[2]}/${partesFecha[1]}/${partesFecha[0]}`
            : fechaOriginal;

    const horaInicio =
        datosPrevisualizacion.hora_inicio;

    const horaFin =
        datosPrevisualizacion.hora_fin;

    const duracionCupo =
        datosPrevisualizacion.duracion_minutos;

    /*
     * FORMATEAR DURACIÓN TOTAL
     */

    const horasTotales =
        Math.floor(duracionTotal / 60);

    const minutosTotales =
        duracionTotal % 60;

    let tiempoTotalTexto = '';

    if (horasTotales > 0) {
        tiempoTotalTexto += `${horasTotales} h`;
    }

    if (minutosTotales > 0) {
        tiempoTotalTexto +=
            `${tiempoTotalTexto ? ' ' : ''}${minutosTotales} min`;
    }

    if (!tiempoTotalTexto) {
        tiempoTotalTexto = '0 min';
    }

    /*
     * ESTADOS DEL SEGMENTO
     */

    const segmentoHabilitado =
        datosPrevisualizacion.segmento_habilitado;

    const textoSegmento =
        segmentoHabilitado
            ? 'Habilitado'
            : 'Deshabilitado';

    const claseSegmento =
        segmentoHabilitado
            ? 'habilitado'
            : 'deshabilitado';

    const multiprofesional =
        datosPrevisualizacion.origen_multiprofesional;

    const textoMultiprofesional =
        multiprofesional ? 'Sí' : 'No';

    /*
     * CONSTRUIR FILAS DE CUPOS
     */

    const filas = cuposPrevisualizados.map(cupo => `

        <tr>

            <td class="vista-previa-numero">
                <span>${cupo.numero}</span>
            </td>

            <td>
                <strong>
                    ${escaparHTMLGenerador(cupo.hora_inicio)}
                </strong>
            </td>

            <td>
                ${escaparHTMLGenerador(cupo.hora_fin)}
            </td>

            <td>
                <span class="vista-previa-duracion">
                    ${cupo.duracion_minutos} min
                </span>
            </td>

            <td>
                <span class="vista-previa-estado-cupo">
                    Disponible
                </span>
            </td>

        </tr>

    `).join('');

    /*
     * CONSTRUIR RESUMEN VISUAL
     */

    resultado.innerHTML = `

        <div class="vista-previa-agenda">

            <!-- ENCABEZADO -->

            <div class="vista-previa-encabezado">

                <div class="vista-previa-titulo">

                    <span class="vista-previa-etiqueta">
                        REVISIÓN DE CONFIGURACIÓN
                    </span>

                    <h3>
                        Vista previa de la agenda
                    </h3>

                    <p>
                        Revisa los datos y horarios antes de guardar.
                    </p>

                </div>

                <div class="vista-previa-fecha">

                    <span>Fecha de atención</span>

                    <strong>
                        ${escaparHTMLGenerador(fechaFormateada)}
                    </strong>

                    <small>
                        ${escaparHTMLGenerador(horaInicio)}
                        -
                        ${escaparHTMLGenerador(horaFin)}
                    </small>

                </div>

            </div>


            <!-- INFORMACIÓN GENERAL -->

            <div class="vista-previa-informacion">

                <div class="vista-previa-dato profesional">

                    <span class="vista-previa-dato-label">
                        Profesional
                    </span>

                    <strong>
                        ${escaparHTMLGenerador(nombreProfesionalActual)}
                    </strong>

                    <small>
                        Instrumento: ${escaparHTMLGenerador(instrumento)}
                    </small>

                </div>


                <div class="vista-previa-dato">

                    <span class="vista-previa-dato-label">
                        Tipo de atención
                    </span>

                    <strong>
                        ${escaparHTMLGenerador(nombreDepartamento)}
                    </strong>

                </div>


                <div class="vista-previa-dato">

                    <span class="vista-previa-dato-label">
                        Servicio
                    </span>

                    <strong>
                        ${escaparHTMLGenerador(nombreServicio)}
                    </strong>

                </div>


                <div class="vista-previa-dato">

                    <span class="vista-previa-dato-label">
                        Sector
                    </span>

                    <strong>
                        ${escaparHTMLGenerador(sector)}
                    </strong>

                </div>

            </div>


            <!-- INDICADORES -->

            <div class="vista-previa-indicadores">

                <div class="vista-previa-indicador">

                    <span class="indicador-label">
                        Total de cupos
                    </span>

                    <strong>
                        ${total}
                    </strong>

                    <small>
                        Horarios generados
                    </small>

                </div>


                <div class="vista-previa-indicador">

                    <span class="indicador-label">
                        Duración total
                    </span>

                    <strong>
                        ${escaparHTMLGenerador(tiempoTotalTexto)}
                    </strong>

                    <small>
                        Tiempo del segmento
                    </small>

                </div>


                <div class="vista-previa-indicador">

                    <span class="indicador-label">
                        Duración por cupo
                    </span>

                    <strong>
                        ${duracionCupo} min
                    </strong>

                    <small>
                        Duración configurada
                    </small>

                </div>

            </div>


            <!-- CONFIGURACIÓN -->

            <div class="vista-previa-configuracion">

                <div class="vista-previa-configuracion-titulo">

                    <h4>
                        Configuración del segmento
                    </h4>

                    <p>
                        Características aplicadas a los cupos.
                    </p>

                </div>

                <div class="vista-previa-etiquetas">

                    <span class="vista-previa-etiqueta-estado ${claseSegmento}">
                        <span></span>
                        Segmento ${textoSegmento}
                    </span>

                    <span class="vista-previa-etiqueta-estado neutro">
                        Inicio diferenciado
                    </span>

                    <span class="vista-previa-etiqueta-estado neutro">
                        Origen multiprofesional: ${textoMultiprofesional}
                    </span>

                </div>

            </div>


            <!-- TABLA DE HORARIOS -->

            <div class="vista-previa-listado">

                <div class="vista-previa-listado-header">

                    <div>

                        <h4>
                            Distribución de cupos
                        </h4>

                        <p>
                            Horarios que se generarán en la agenda.
                        </p>

                    </div>

                    <span class="vista-previa-total-badge">
                        ${total} cupos
                    </span>

                </div>


                <div class="vista-previa-tabla-contenedor">

                    <table class="vista-previa-tabla">

                        <thead>

                            <tr>

                                <th>N.º</th>

                                <th>Hora de inicio</th>

                                <th>Hora de término</th>

                                <th>Duración</th>

                                <th>Estado</th>

                            </tr>

                        </thead>

                        <tbody>
                            ${filas}
                        </tbody>

                    </table>

                </div>

            </div>


            <!-- PIE -->

            <div class="vista-previa-nota">

                <span class="vista-previa-nota-icono">
                    i
                </span>

                <p>
                    Esta es una vista previa. Los cupos se registrarán
                    en el sistema únicamente cuando pulses
                    <strong>Guardar agenda</strong>.
                </p>

            </div>

        </div>

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
    ].forEach(elemento => {
        if (!elemento) return;

        elemento.addEventListener(
            'change',
            invalidarVistaPrevia
        );

        elemento.addEventListener(
            'input',
            invalidarVistaPrevia
        );
    });

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
        if (guardandoAgenda) return;

        if (
            !checkboxDiferenciado.checked ||
            !datosPrevisualizacion ||
            cuposPrevisualizados.length === 0
        ) {
            alert(
                'Primero debes activar Inicio diferenciado y generar una vista previa válida.'
            );

            return;
        }

        if (
            datosPrevisualizacion.id_profesional !== campoProfesional.value ||
            datosPrevisualizacion.id_departamento !== selectDepartamentoAgenda.value ||
            datosPrevisualizacion.id_servicio !== selectServicioAgenda.value ||
            datosPrevisualizacion.fecha !== campoFecha.value ||
            datosPrevisualizacion.hora_inicio !== campoHoraInicio.value ||
            datosPrevisualizacion.hora_fin !== campoHoraFin.value ||
            datosPrevisualizacion.duracion_minutos !== Number(campoDuracion.value)
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
            const datos =
                new FormData(formulario);

            datos.set(
                'segmento_habilitado',
                checkboxHabilitado.checked ? '1' : '0'
            );

            datos.set(
                'origen_multiprofesional',
                checkboxMultiprofesional.checked ? '1' : '0'
            );

            /*
             * Siempre debe ser 1.
             * El servidor también validará esta condición.
             */

            datos.set(
                'inicio_diferenciado',
                '1'
            );

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

            datos.set(
                'cupos',
                JSON.stringify(cuposPrevisualizados)
            );

            datos.set(
                'hora_referencia',
                campoHoraReferencia.value
            );

            const respuesta = await fetch(
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

            alert(
                resultadoServidor.mensaje ||
                `Agenda guardada correctamente. Cupos generados: ${resultadoServidor.total_cupos ?? cuposPrevisualizados.length}.`
            );

            const url =
                new URL(window.location.href);

            url.searchParams.set(
                'profesional',
                campoProfesional.value
            );

            url.searchParams.set(
                'fecha',
                campoFecha.value
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

    document.addEventListener('click', evento => {
        const boton =
            evento.target.closest('.week-cell-generar');

        if (!boton) return;

        const fecha = boton.dataset.fecha;

        const hora = boton.dataset.hora;

        if (!fecha || !hora) {
            console.error(
                'La celda no tiene fecha u hora asignada.'
            );

            return;
        }

        abrirModal(fecha, hora);
    });

    /* =====================================================
       EVITAR ENVÍO NATIVO DEL FORMULARIO
    ===================================================== */

    formulario.addEventListener(
        'submit',
        evento => evento.preventDefault()
    );

    /* =====================================================
       INICIALIZAR GENERADOR
    ===================================================== */

    prepararServicios();

    inicializarProfesionales();

    if (selectDepartamentoAgenda.value) {
        cargarServiciosAgenda(
            selectDepartamentoAgenda.value
        );
    }
}

/* =========================================================
   INICIALIZAR GENERADOR CUANDO EL DOM ESTÉ DISPONIBLE
========================================================= */

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        inicializarGeneradorAgenda,
        { once: true }
    );

} else {
    inicializarGeneradorAgenda();
}
