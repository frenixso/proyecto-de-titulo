/*
|--------------------------------------------------------------------------
| AGENDA MUNICIPAL
|--------------------------------------------------------------------------
| JavaScript de funcionarios/agenda.php
*/


/*
|--------------------------------------------------------------------------
| OBTENER PARÁMETROS ACTUALES DE LA URL
|--------------------------------------------------------------------------
*/

const parametrosURL =
new URLSearchParams(
window.location.search
);


/*
|--------------------------------------------------------------------------
| DEPARTAMENTO SELECCIONADO
|--------------------------------------------------------------------------
*/

let departamentoSeleccionado =
parametrosURL.get('departamento') || '';


/*
|--------------------------------------------------------------------------
| SERVICIO SELECCIONADO
|--------------------------------------------------------------------------
*/

let servicioSeleccionado =
parametrosURL.get('servicio') || '';


/*
|--------------------------------------------------------------------------
| FECHA SELECCIONADA
|--------------------------------------------------------------------------
*/

let fechaSeleccionada =
parametrosURL.get('fecha') || '';


/*
|--------------------------------------------------------------------------
| OBTENER FECHA LOCAL
|--------------------------------------------------------------------------
*/

function obtenerFechaLocal() {


const fecha =
    new Date();

const año =
    fecha.getFullYear();

const mes =
    String(
        fecha.getMonth() + 1
    ).padStart(
        2,
        '0'
    );

const dia =
    String(
        fecha.getDate()
    ).padStart(
        2,
        '0'
    );

return (
    año +
    '-' +
    mes +
    '-' +
    dia
);


}


/*
|--------------------------------------------------------------------------
| SI NO EXISTE FECHA EN LA URL
|--------------------------------------------------------------------------
*/

if (
!fechaSeleccionada ||
!/^\d{4}-\d{2}-\d{2}$/.test(
fechaSeleccionada
)
) {


fechaSeleccionada =
    obtenerFechaLocal();


}


/*
|--------------------------------------------------------------------------
| VALIDAR FECHA
|--------------------------------------------------------------------------
*/

function fechaValida(
fecha
) {


return /^\d{4}-\d{2}-\d{2}$/.test(
    fecha
);


}


/*
|--------------------------------------------------------------------------
| OBTENER MES DESDE UNA FECHA
|--------------------------------------------------------------------------
*/

function obtenerMesDesdeFecha(
fecha
) {


if (
    !fechaValida(fecha)
) {

    return '';

}

return fecha.substring(
    0,
    7
);


}


/*
|--------------------------------------------------------------------------
| NAVEGAR A UNA FECHA
|--------------------------------------------------------------------------
*/

function navegarAgenda(
fecha,
abrirPopup = false
) {


if (
    !fechaValida(fecha)
) {

    return;

}


const parametros =
    new URLSearchParams();


/*
 * Mantener departamento seleccionado.
 */

if (
    departamentoSeleccionado
) {

    parametros.set(
        'departamento',
        departamentoSeleccionado
    );

}


/*
 * Mantener servicio seleccionado.
 */

if (
    servicioSeleccionado
) {

    parametros.set(
        'servicio',
        servicioSeleccionado
    );

}


parametros.set(
    'fecha',
    fecha
);


if (
    abrirPopup
) {

    parametros.set(
        'abrir_resumen',
        '1'
    );

}


window.location.href =
    'agenda.php?' +
    parametros.toString();


}


/*
|--------------------------------------------------------------------------
| CAMBIAR DEPARTAMENTO
|--------------------------------------------------------------------------
|
| Este selector solamente existe para superadmin.
|
| Al cambiar de departamento se elimina el servicio seleccionado,
| ya que el servicio anterior puede pertenecer a otro departamento.
|
|--------------------------------------------------------------------------

*/

const selectorDepartamento =
document.getElementById(
'departamentoAgenda'
);

if (
selectorDepartamento
) {


selectorDepartamento.addEventListener(
    'change',
    function () {

        departamentoSeleccionado =
            this.value || '';


        /*
         * El servicio anterior deja de ser válido
         * cuando cambia el departamento.
         */

        servicioSeleccionado =
            '';


        const parametros =
            new URLSearchParams();


        if (
            departamentoSeleccionado
        ) {

            parametros.set(
                'departamento',
                departamentoSeleccionado
            );

        }


        parametros.set(
            'fecha',
            fechaSeleccionada
        );


        window.location.href =
            'agenda.php?' +
            parametros.toString();

    }
);


}


/*
|--------------------------------------------------------------------------
| CAMBIAR SERVICIO
|--------------------------------------------------------------------------
*/

const selectorServicio =
document.getElementById(
'servicioAgenda'
);

if (
selectorServicio
) {


selectorServicio.addEventListener(
    'change',
    function () {

        servicioSeleccionado =
            this.value;


        const parametros =
            new URLSearchParams();


        /*
         * Mantener departamento cuando exista.
         */

        if (
            departamentoSeleccionado
        ) {

            parametros.set(
                'departamento',
                departamentoSeleccionado
            );

        }


        /*
         * Si se selecciona "Todos los servicios",
         * solamente se mantiene el departamento.
         */

        if (
            servicioSeleccionado
        ) {

            parametros.set(
                'servicio',
                servicioSeleccionado
            );

        }


        parametros.set(
            'fecha',
            fechaSeleccionada
        );


        window.location.href =
            'agenda.php?' +
            parametros.toString();

    }
);


}


/*
|--------------------------------------------------------------------------
| CAMBIAR DÍA DESDE EL SELECTOR DE FECHA
|--------------------------------------------------------------------------
*/

const selectoresFechaAgenda =
document.querySelectorAll(
'#fechaSeleccionadaAgenda'
);

selectoresFechaAgenda.forEach(
function (selectorFechaAgenda) {


    selectorFechaAgenda.addEventListener(
        'change',
        function () {

            const nuevaFecha =
                this.value;


            if (
                !fechaValida(
                    nuevaFecha
                )
            ) {

                return;

            }


            servicioSeleccionado =
                selectorServicio?.value ||
                servicioSeleccionado ||
                '';


            fechaSeleccionada =
                nuevaFecha;


            navegarAgenda(
                nuevaFecha
            );

        }
    );

}


);


/*
|--------------------------------------------------------------------------
| CAMBIAR DE MES
|--------------------------------------------------------------------------
*/

function cambiarMes(
cantidad
) {


const partes =
    fechaSeleccionada.split(
        '-'
    );


let año =
    parseInt(
        partes[0],
        10
    );


let mes =
    parseInt(
        partes[1],
        10
    );


mes += cantidad;


if (
    mes > 12
) {

    mes = 1;

    año++;

}


if (
    mes < 1
) {

    mes = 12;

    año--;

}


const mesFormateado =
    String(
        mes
    ).padStart(
        2,
        '0'
    );


const nuevaFecha =
    año +
    '-' +
    mesFormateado +
    '-01';


navegarAgenda(
    nuevaFecha
);


}


/*
|--------------------------------------------------------------------------
| BOTONES DE CALENDARIO Y NAVEGACIÓN
|--------------------------------------------------------------------------
*/

const botonMesAnterior =
document.getElementById(
'mesAnterior'
);

if (
botonMesAnterior
) {


botonMesAnterior.addEventListener(
    'click',
    function () {

        cambiarMes(-1);

    }
);


}


const botonMesSiguiente =
document.getElementById(
'mesSiguiente'
);

if (
botonMesSiguiente
) {


botonMesSiguiente.addEventListener(
    'click',
    function () {

        cambiarMes(1);

    }
);


}


const botonHoy =
document.getElementById(
'btnHoy'
);

if (
botonHoy
) {


botonHoy.addEventListener(
    'click',
    function () {

        navegarAgenda(
            obtenerFechaLocal()
        );

    }
);


}


/*
|--------------------------------------------------------------------------
| ELEMENTOS DEL POPUP RESUMEN DÍA
|--------------------------------------------------------------------------
*/

const agendaModalOverlay =
document.getElementById(
'agendaModalOverlay'
);

const agendaModalClose =
document.getElementById(
'agendaModalClose'
);

const agendaModalCancel =
document.getElementById(
'agendaModalCancel'
);

const agendaModalVerAgenda =
document.getElementById(
'agendaModalVerAgenda'
);

const agendaModalTitle =
document.getElementById(
'agendaModalTitle'
);

const agendaModalFecha =
document.getElementById(
'agendaModalFecha'
);

const agendaModalTotal =
document.getElementById(
'agendaModalTotal'
);

const agendaModalServicio =
document.getElementById(
'agendaModalServicio'
);

const agendaModalReservas =
document.getElementById(
'agendaModalReservas'
);

const agendaModalLoading =
document.getElementById(
'agendaModalLoading'
);

const agendaModalContent =
document.getElementById(
'agendaModalContent'
);

const agendaModalError =
document.getElementById(
'agendaModalError'
);

let fechaModalSeleccionada =
'';


function escaparHTML(
texto
) {


const div =
    document.createElement(
        'div'
    );

div.textContent =
    texto === null ||
    texto === undefined
        ? ''
        : String(texto);

return div.innerHTML;


}


function formatearFecha(
fecha
) {


if (
    !fechaValida(fecha)
) {

    return fecha;

}


const partes =
    fecha.split('-');


const año =
    parseInt(
        partes[0],
        10
    );


const mes =
    parseInt(
        partes[1],
        10
    );


const dia =
    parseInt(
        partes[2],
        10
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


const fechaObjeto =
    new Date(
        año,
        mes - 1,
        dia
    );


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
    nombresDias[
        fechaObjeto.getDay()
    ];


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


function abrirResumenDia(
fecha
) {


if (
    !agendaModalOverlay
) {

    return;

}


fechaModalSeleccionada =
    fecha;


agendaModalOverlay.classList.add(
    'active'
);


agendaModalOverlay.setAttribute(
    'aria-hidden',
    'false'
);


document.body.classList.add(
    'agenda-modal-open'
);


if (
    agendaModalFecha
) {

    agendaModalFecha.textContent =
        formatearFecha(
            fecha
        );

}


if (
    agendaModalTitle
) {

    agendaModalTitle.textContent =
        'Resumen del día';

}


if (
    agendaModalTotal
) {

    agendaModalTotal.textContent =
        '0';

}


if (
    agendaModalServicio
) {

    agendaModalServicio.textContent =
        'Cargando...';

}


if (
    agendaModalReservas
) {

    agendaModalReservas.innerHTML =
        '';

}


if (
    agendaModalError
) {

    agendaModalError.textContent =
        '';

    agendaModalError.classList.remove(
        'active'
    );

}


if (
    agendaModalContent
) {

    agendaModalContent.classList.remove(
        'active'
    );

}


if (
    agendaModalLoading
) {

    agendaModalLoading.classList.add(
        'active'
    );

}


cargarResumenDia(
    fecha
);


}


/*
|--------------------------------------------------------------------------
| CARGAR RESUMEN DEL DÍA
|--------------------------------------------------------------------------
|
| Puede funcionar:
|
| - Sin departamento y sin servicio.
| - Con departamento y sin servicio.
| - Con departamento y servicio.
| - Con servicio seleccionado.
|
|--------------------------------------------------------------------------
*/

function cargarResumenDia(
fecha
) {


const parametros =
    new URLSearchParams();


/*
 * Departamento opcional.
 */

if (
    departamentoSeleccionado
) {

    parametros.set(
        'departamento',
        departamentoSeleccionado
    );

}


/*
 * Servicio opcional.
 */

if (
    servicioSeleccionado
) {

    parametros.set(
        'servicio',
        servicioSeleccionado
    );

}


parametros.set(
    'fecha',
    fecha
);


const url =
    '../api/resumen_agenda.php?' +
    parametros.toString();


fetch(
    url,
    {
        method:
            'GET',

        headers: {
            'Accept':
                'application/json'
        }
    }
)

.then(
    respuesta =>
        respuesta.text()
)

.then(
    texto => {

        let datos;


        try {

            datos =
                JSON.parse(
                    texto
                );

        } catch (
            error
        ) {

            throw new Error(
                'La respuesta del servidor no contiene JSON válido.'
            );

        }


        if (
            !datos.ok
        ) {

            throw new Error(
                datos.error ||
                datos.mensaje ||
                'No fue posible obtener el resumen.'
            );

        }


        mostrarResumenDia(
            datos
        );

    }
)

.catch(
    error => {

        console.error(
            'Error resumen agenda:',
            error
        );


        if (
            agendaModalLoading
        ) {

            agendaModalLoading.classList.remove(
                'active'
            );

        }


        if (
            agendaModalContent
        ) {

            agendaModalContent.classList.remove(
                'active'
            );

        }


        if (
            agendaModalError
        ) {

            agendaModalError.textContent =
                error.message ||
                'No fue posible consultar las reservas del día.';

            agendaModalError.classList.add(
                'active'
            );

        }

    }
);


}


function mostrarResumenDia(
datos
) {


if (
    agendaModalLoading
) {

    agendaModalLoading.classList.remove(
        'active'
    );

}


if (
    agendaModalError
) {

    agendaModalError.classList.remove(
        'active'
    );

}


if (
    agendaModalContent
) {

    agendaModalContent.classList.add(
        'active'
    );

}


if (
    agendaModalTitle
) {

    agendaModalTitle.textContent =
        'Resumen del día';

}


if (
    agendaModalFecha
) {

    agendaModalFecha.textContent =
        formatearFecha(
            datos.fecha
        );

}


if (
    agendaModalTotal
) {

    agendaModalTotal.textContent =
        datos.total_reservas ??
        0;

}


if (
    agendaModalServicio
) {

    agendaModalServicio.textContent =
        datos.servicio ||
        '';

}


if (
    !agendaModalReservas
) {

    return;

}


agendaModalReservas.innerHTML =
    '';


if (
    !datos.reservas ||
    datos.reservas.length === 0
) {

    const vacio =
        document.createElement(
            'div'
        );


    vacio.className =
        'agenda-modal-empty';


    vacio.innerHTML =
        '<span class="agenda-modal-empty-icon">📭</span>' +
        '<strong>No hay reservas agendadas</strong>' +
        '<span>No existen reservas confirmadas para este día.</span>';


    agendaModalReservas.appendChild(
        vacio
    );


    return;

}


datos.reservas.forEach(
    reserva => {

        const tarjeta =
            document.createElement(
                'div'
            );


        tarjeta.className =
            'agenda-modal-reserva';


        const descripcion =
            reserva.observacion &&
            String(
                reserva.observacion
            ).trim() !== ''

                ? reserva.observacion

                : 'Sin descripción';


        tarjeta.innerHTML =

            '<div class="agenda-modal-reserva-top">' +

                '<div class="agenda-modal-reserva-hora">' +
                    escaparHTML(
                        reserva.hora
                    ) +
                '</div>' +

                '<span class="agenda-modal-reserva-estado">' +
                    'Confirmada' +
                '</span>' +

            '</div>' +

            '<div class="agenda-modal-reserva-persona">' +

                '<div class="agenda-modal-avatar">' +
                    escaparHTML(
                        obtenerIniciales(
                            reserva.nombre
                        )
                    ) +
                '</div>' +

                '<div class="agenda-modal-persona-datos">' +

                    '<strong>' +
                        escaparHTML(
                            reserva.nombre
                        ) +
                    '</strong>' +

                    '<span>' +
                        escaparHTML(
                            reserva.servicio
                        ) +
                    '</span>' +

                '</div>' +

            '</div>' +

            '<div class="agenda-modal-reserva-descripcion">' +

                '<span class="agenda-modal-descripcion-icon">' +
                    '📝' +
                '</span>' +

                '<div>' +

                    '<strong>' +
                        'Descripción' +
                    '</strong>' +

                    '<p>' +
                        escaparHTML(
                            descripcion
                        ) +
                    '</p>' +

                '</div>' +

            '</div>';


        agendaModalReservas.appendChild(
            tarjeta
        );

    }
);


}


function obtenerIniciales(
nombre
) {


if (
    !nombre
) {

    return 'RS';

}


const partes =
    String(
        nombre
    )
    .trim()
    .split(
        /\s+/
    );


let iniciales =
    '';


if (
    partes[0]
) {

    iniciales +=
        partes[0]
        .substring(
            0,
            1
        )
        .toUpperCase();

}


if (
    partes[1]
) {

    iniciales +=
        partes[1]
        .substring(
            0,
            1
        )
        .toUpperCase();

}


return iniciales ||
    'RS';


}


function cerrarResumenDia() {


if (
    !agendaModalOverlay
) {

    return;

}


agendaModalOverlay.classList.remove(
    'active'
);


agendaModalOverlay.setAttribute(
    'aria-hidden',
    'true'
);


document.body.classList.remove(
    'agenda-modal-open'
);


fechaModalSeleccionada =
    '';


}


/*
|--------------------------------------------------------------------------
| CERRAR POPUP CON BOTÓN X
|--------------------------------------------------------------------------
|
| Se utiliza delegación de eventos para asegurar que el botón funcione
| incluso si el contenido del popup fue generado o reemplazado después.
|
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'click',
    function (evento) {

        const botonCerrar =
            evento.target.closest(
                '#agendaModalClose'
            );


        if (
            !botonCerrar
        ) {

            return;

        }


        evento.preventDefault();

        evento.stopPropagation();

        cerrarResumenDia();

    },
    true
);


/*
|--------------------------------------------------------------------------
| CERRAR POPUP CON BOTÓN CANCELAR
|--------------------------------------------------------------------------
*/

if (
agendaModalCancel
) {


agendaModalCancel.addEventListener(
    'click',
    function (evento) {

        evento.preventDefault();

        evento.stopPropagation();

        cerrarResumenDia();

    }
);


}


/*
|--------------------------------------------------------------------------
| CERRAR POPUP HACIENDO CLICK FUERA
|--------------------------------------------------------------------------
*/

if (
agendaModalOverlay
) {


agendaModalOverlay.addEventListener(
    'click',
    function (evento) {

        if (
            evento.target ===
            agendaModalOverlay
        ) {

            cerrarResumenDia();

        }

    }
);


}


/*
|--------------------------------------------------------------------------
| CERRAR POPUP CON ESCAPE
|--------------------------------------------------------------------------
*/

document.addEventListener(
'keydown',
function (evento) {


    if (
        evento.key ===
        'Escape' &&
        agendaModalOverlay &&
        agendaModalOverlay.classList.contains(
            'active'
        )
    ) {

        cerrarResumenDia();

    }


}


);


/*
|--------------------------------------------------------------------------
| VER AGENDA COMPLETA
|--------------------------------------------------------------------------
*/

if (
agendaModalVerAgenda
) {


agendaModalVerAgenda.addEventListener(
    'click',
    function (evento) {

        evento.preventDefault();

        evento.stopPropagation();


        cerrarResumenDia();


        window.location.href =
            'gestionar_reserva.php';

    }
);


}


/*
|--------------------------------------------------------------------------
| DÍAS DEL CALENDARIO
|--------------------------------------------------------------------------
*/

const diasCalendario =
document.querySelectorAll(
'.calendar-day[data-fecha]'
);

diasCalendario.forEach(
dia => {


    dia.addEventListener(
        'click',
        function (evento) {

            evento.preventDefault();

            evento.stopPropagation();


            const fecha =
                this.getAttribute(
                    'data-fecha'
                );


            if (
                fecha
            ) {

                navegarAgenda(
                    fecha,
                    true
                );

            }

        }
    );

}


);


/*
|--------------------------------------------------------------------------
| ABRIR RESUMEN AUTOMÁTICAMENTE
|--------------------------------------------------------------------------
*/

const abrirResumenAutomaticamente =
parametrosURL.get(
'abrir_resumen'
);

if (
abrirResumenAutomaticamente ===
'1' &&
fechaSeleccionada
) {


abrirResumenDia(
    fechaSeleccionada
);


}


/*
|--------------------------------------------------------------------------
| API DE DEPARTAMENTOS, HORARIOS Y CREACIÓN DE RESERVAS
|--------------------------------------------------------------------------
*/

const API_DEPARTAMENTOS_URL =
"../api/obtener_departamentos.php";

const API_HORARIOS_URL =
"../api/obtener_horarios.php";

const API_CREAR_URL =
"../api/crear_reserva.php";


/*
|--------------------------------------------------------------------------
| ELEMENTOS DEL FORMULARIO CREAR RESERVA
|--------------------------------------------------------------------------
*/

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

const btnCancelCreate =
document.getElementById(
"btnCancelCreate"
);


/*
|--------------------------------------------------------------------------
| BOTÓN X DEL FORMULARIO
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| El formulario usa id="btnCloseCreateModal".
|
| Antes agenda.js buscaba "btnCloseCreate", por lo que la X
| nunca encontraba el botón correcto.
|--------------------------------------------------------------------------

*/

const btnCloseCreateModal =
document.getElementById(
"btnCloseCreateModal"
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

const errorDepartamento =
document.getElementById(
"errorDepartamento"
);

const errorServicio =
document.getElementById(
"errorServicio"
);

const errorFecha =
document.getElementById(
"errorFecha"
);

const errorHorario =
document.getElementById(
"errorHorario"
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


/*
|--------------------------------------------------------------------------
| NOTIFICACIÓN DEL FORMULARIO
|--------------------------------------------------------------------------
|
| El formulario actual utiliza:
| createReservationNotification
| createReservationNotificationMessage
|--------------------------------------------------------------------------

*/

const notification =
document.getElementById(
"createReservationNotification"
);

const notificationMessage =
document.getElementById(
"createReservationNotificationMessage"
);


/*
|--------------------------------------------------------------------------
| CONTEXTO DE RESERVA
|--------------------------------------------------------------------------
*/

let contextoReserva = {


departamento:
    "",

servicio:
    "",

fecha:
    "",

hora:
    "",

servicioFijo:
    false,

fechaFija:
    false

};


let datosPersonalesValidosAnteriormente =
false;

let departamentosFormulario =
[];


/*
|--------------------------------------------------------------------------
| MOSTRAR NOTIFICACIÓN
|--------------------------------------------------------------------------
*/

function mostrarNotificacion(
mensaje,
tipo = "error"
) {


if (
    !notification ||
    !notificationMessage
) {

    return;

}


notificationMessage.textContent =
    mensaje;


notification.classList.remove(
    "success",
    "error",
    "warning",
    "show"
);


notification.classList.add(
    tipo,
    "show"
);


setTimeout(
    () => {

        notification.classList.remove(
            "show"
        );

    },
    5000
);


}


/*
|--------------------------------------------------------------------------
| VALIDAR RUT CHILENO
|--------------------------------------------------------------------------
*/

function validarRutChileno(
rut
) {


rut =
    rut
    .replace(
        /\./g,
        ""
    )
    .replace(
        /-/g,
        ""
    )
    .trim()
    .toUpperCase();


if (
    !/^\d{7,8}[0-9K]$/.test(
        rut
    )
) {

    return false;

}


const cuerpo =
    rut.slice(
        0,
        -1
    );


const dv =
    rut.slice(
        -1
    );


let suma =
    0;


let multiplicador =
    2;


for (
    let i =
        cuerpo.length - 1;

    i >= 0;

    i--
) {

    suma +=
        parseInt(
            cuerpo[i],
            10
        ) *
        multiplicador;


    multiplicador =
        multiplicador === 7
            ? 2
            : multiplicador + 1;

}


const resto =
    suma % 11;


const resultado =
    11 - resto;


let dvCalculado =
    resultado === 11
        ? "0"
        : (
            resultado === 10
                ? "K"
                : String(
                    resultado
                )
        );


return (
    dv ===
    dvCalculado
);


}


/*
|--------------------------------------------------------------------------
| FORMATEAR RUT CHILENO
|--------------------------------------------------------------------------
*/

function formatearRut(
rut
) {


let valor =
    String(
        rut || ""
    )
    .replace(
        /[^0-9kK]/g,
        ""
    )
    .toUpperCase();


if (
    valor.length === 0
) {

    return "";

}


/*
 * Separar cuerpo y dígito verificador.
 */

let cuerpo =
    valor.slice(
        0,
        -1
    );

const dv =
    valor.slice(
        -1
    );


/*
 * Limitar el cuerpo a 8 dígitos.
 */

cuerpo =
    cuerpo
    .replace(
        /\D/g,
        ""
    )
    .slice(
        0,
        8
    );


if (
    !cuerpo
) {

    return dv;

}


/*
 * Agregar puntos desde la derecha.
 */

let cuerpoFormateado =
    "";

while (
    cuerpo.length > 3
) {

    cuerpoFormateado =
        "." +
        cuerpo.slice(
            -3
        ) +
        cuerpoFormateado;

    cuerpo =
        cuerpo.slice(
            0,
            -3
        );

}


cuerpoFormateado =
    cuerpo +
    cuerpoFormateado;


/*
 * Agregar guion solamente
 * cuando existe dígito verificador.
 */

return (
    cuerpoFormateado +
    "-" +
    dv
);


}


/*
|--------------------------------------------------------------------------
| VALIDAR IDENTIFICACIÓN EXTRANJERA
|--------------------------------------------------------------------------
*/

function validarIdentificacionExtranjera(
valor
) {


return /^[A-Za-z0-9.\-]{4,20}$/.test(
    valor.trim()
);


}


/*
|--------------------------------------------------------------------------
| OBTENER TIPO DE IDENTIFICACIÓN
|--------------------------------------------------------------------------
*/

function obtenerTipoIdentificacion() {


const seleccionado =
    document.querySelector(
        'input[name="tipo_identificacion"]:checked'
    );


return seleccionado
    ? seleccionado.value
    : "rut";


}


/*
|--------------------------------------------------------------------------
| VALIDAR IDENTIFICACIÓN
|--------------------------------------------------------------------------
*/

function validarIdentificacion() {


const tipo =
    obtenerTipoIdentificacion();


const valor =
    createRut
        ? createRut.value.trim()
        : "";


if (
    !valor
) {

    return false;

}


if (
    tipo ===
    "rut"
) {

    return validarRutChileno(
        valor
    );

}


if (
    tipo === "rut_extranjero" ||
    tipo === "pasaporte"
) {

    return validarIdentificacionExtranjera(
        valor
    );

}


return false;


}


/*
|--------------------------------------------------------------------------
| VALIDAR NOMBRE
|--------------------------------------------------------------------------
*/

function validarNombre() {


if (
    !createNombre
) {

    return false;

}


const nombre =
    createNombre.value.trim();


if (
    nombre.length < 2 ||
    nombre.length > 100
) {

    return false;

}


return /^[A-Za-zÁÉÍÓÚáéíóúÑñÜü\s.'-]+$/.test(
    nombre
);


}


/*
|--------------------------------------------------------------------------
| VALIDAR TELÉFONO
|--------------------------------------------------------------------------
*/

function validarTelefono() {


if (
    !createTelefono
) {

    return false;

}


const telefono =
    createTelefono.value.trim();


if (
    telefono.length < 7 ||
    telefono.length > 20
) {

    return false;

}


return /^[0-9+\s().\-]+$/.test(
    telefono
);


}


/*
|--------------------------------------------------------------------------
| VALIDAR CORREO
|--------------------------------------------------------------------------
*/

function validarCorreo() {


if (
    !createCorreo
) {

    return true;

}


const correo =
    createCorreo.value.trim();


if (
    !correo
) {

    return true;

}


return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
    correo
);


}


/*
|--------------------------------------------------------------------------
| DATOS PERSONALES VÁLIDOS
|--------------------------------------------------------------------------
*/

function datosPersonalesValidos() {


return (

    validarNombre() &&
    validarIdentificacion() &&
    validarTelefono() &&
    validarCorreo()

);


}


/*
|--------------------------------------------------------------------------
| OBTENER NOMBRE DEPARTAMENTO SELECCIONADO
|--------------------------------------------------------------------------
*/

function obtenerNombreDepartamentoSeleccionado() {


if (
    !createDepartamento
) {

    return "";

}


const opcion =
    createDepartamento.options[
        createDepartamento.selectedIndex
    ];


return opcion
    ? opcion.textContent.trim()
    : "";


}


/*
|--------------------------------------------------------------------------
| OBTENER NOMBRE SERVICIO SELECCIONADO
|--------------------------------------------------------------------------
*/

function obtenerNombreServicioSeleccionado() {


if (
    !createServicio
) {

    return "";

}


const opcion =
    createServicio.options[
        createServicio.selectedIndex
    ];


return opcion
    ? opcion.textContent.trim()
    : "";


}


/*
|--------------------------------------------------------------------------
| BUSCAR DEPARTAMENTO POR SERVICIO
|--------------------------------------------------------------------------
*/

function obtenerDepartamentoPorServicio(
idServicio
) {


if (
    !idServicio
) {

    return null;

}


const id =
    String(
        idServicio
    );


for (
    const departamento
    of departamentosFormulario
) {

    const servicioEncontrado =
        (
            departamento.servicios ||
            []
        )
        .find(
            servicio =>
                String(
                    servicio.id_servicio
                ) ===
                id
        );


    if (
        servicioEncontrado
    ) {

        return {

            departamento:
                departamento,

            servicio:
                servicioEncontrado

        };

    }

}


return null;


}


/*
|--------------------------------------------------------------------------
| CARGAR DEPARTAMENTOS
|--------------------------------------------------------------------------
*/

async function cargarDepartamentosCrear() {


if (
    !createDepartamento
) {

    return;

}


createDepartamento.disabled =
    true;


createDepartamento.innerHTML =
    '<option value="">Cargando departamentos...</option>';


try {

    const respuesta =
        await fetch(
            API_DEPARTAMENTOS_URL,
            {
                method:
                    "GET",

                headers: {
                    "Accept":
                        "application/json"
                }
            }
        );


    const datos =
        await respuesta.json();


    if (
        !datos.ok
    ) {

        throw new Error(
            datos.mensaje ||
            "No fue posible cargar los departamentos."
        );

    }


    departamentosFormulario =
        Array.isArray(
            datos.departamentos
        )
            ? datos.departamentos
            : [];


    createDepartamento.innerHTML =
        '<option value="">Selecciona un departamento</option>';


    departamentosFormulario.forEach(
        departamento => {

            const option =
                document.createElement(
                    "option"
                );


            option.value =
                departamento.id_departamento;


            option.textContent =
                departamento.nombre;


            option.disabled =
                departamento.seleccionable !== true;


            option.dataset.seleccionable =
                departamento.seleccionable
                    ? "1"
                    : "0";


            option.dataset.servicios =
                JSON.stringify(
                    departamento.servicios ||
                    []
                );


            createDepartamento.appendChild(
                option
            );

        }
    );


    createDepartamento.disabled =
        false;


    if (
        contextoReserva.departamento
    ) {

        const existeDepartamento =
            Array.from(
                createDepartamento.options
            )
            .some(
                option =>
                    option.value ===
                    String(
                        contextoReserva.departamento
                    ) &&
                    !option.disabled
            );


        if (
            existeDepartamento
        ) {

            createDepartamento.value =
                String(
                    contextoReserva.departamento
                );

            cargarServiciosPorDepartamento(
                contextoReserva.departamento,
                contextoReserva.servicio
            );

        }

    }


} catch (
    error
) {

    console.error(
        "Error cargando departamentos:",
        error
    );


    createDepartamento.innerHTML =
        '<option value="">No se pudieron cargar los departamentos</option>';


    createDepartamento.disabled =
        true;


    if (
        errorDepartamento
    ) {

        errorDepartamento.textContent =
            error.message ||
            "No fue posible cargar los departamentos.";

    }


    mostrarNotificacion(
        error.message ||
        "No fue posible cargar los departamentos.",
        "error"
    );

}


}


/*
|--------------------------------------------------------------------------
| CARGAR SERVICIOS POR DEPARTAMENTO
|--------------------------------------------------------------------------
*/

function cargarServiciosPorDepartamento(
idDepartamento,
idServicioSeleccionado = ""
) {


if (
    !createServicio
) {

    return;

}


const departamento =
    departamentosFormulario.find(
        item =>
            String(
                item.id_departamento
            ) ===
            String(
                idDepartamento
            )
    );


createServicio.innerHTML =
    '<option value="">Selecciona un servicio</option>';


if (
    !departamento ||
    !departamento.servicios ||
    departamento.servicios.length === 0
) {


    createServicio.disabled =
        true;


    createServicio.innerHTML =
        '<option value="">Este departamento no tiene servicios disponibles</option>';


    if (
        errorServicio
    ) {

        errorServicio.textContent =
            "";

    }


    actualizarPreview();

    return;

}


departamento.servicios.forEach(
    servicio => {

        const option =
            document.createElement(
                "option"
            );


        option.value =
            servicio.id_servicio;


        option.textContent =
            servicio.nombre;


        option.dataset.idServicio =
            servicio.id_servicio;


        option.dataset.descripcion =
            servicio.descripcion ||
            "";


        createServicio.appendChild(
            option
        );

    }
);


createServicio.disabled =
    false;


if (
    idServicioSeleccionado
) {

    const existeServicio =
        Array.from(
            createServicio.options
        )
        .some(
            option =>
                option.value ===
                String(
                    idServicioSeleccionado
                )
        );


    if (
        existeServicio
    ) {

        createServicio.value =
            String(
                idServicioSeleccionado
            );

    }

}


actualizarPreview();


}


/*
|--------------------------------------------------------------------------
| ACTUALIZAR PREVIEW
|--------------------------------------------------------------------------
*/

function actualizarPreview() {


if (
    previewNombre
) {

    previewNombre.textContent =
        createNombre?.value.trim() ||
        "Pendiente";


}


if (
    previewIdentificacion
) {

    const tipo =
        obtenerTipoIdentificacion();


    const valor =
        createRut?.value.trim() ||
        "";


    if (
        valor
    ) {

        let textoTipo =
            "RUT";


        if (
            tipo ===
            "rut_extranjero"
        ) {

            textoTipo =
                "RUT extranjero";

        }


        if (
            tipo ===
            "pasaporte"
        ) {

            textoTipo =
                "Pasaporte";

        }


        previewIdentificacion.textContent =
            textoTipo +
            ": " +
            valor;

    } else {

        previewIdentificacion.textContent =
            "Pendiente";

    }

}


if (
    previewDepartamento
) {

    previewDepartamento.textContent =
        obtenerNombreDepartamentoSeleccionado() ||
        "Pendiente";


}


if (
    previewServicio
) {

    previewServicio.textContent =
        obtenerNombreServicioSeleccionado() ||
        "Pendiente";


}


if (
    previewFecha
) {

    previewFecha.textContent =
        createFecha?.value ||
        "Pendiente";


}


if (
    previewHora
) {

    previewHora.textContent =
        createHorario?.value ||
        "Pendiente";


}


}


/*
|--------------------------------------------------------------------------
| CARGAR HORARIOS PARA CREAR RESERVA
|--------------------------------------------------------------------------
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


const idServicio =
    createServicio.value;


const fecha =
    createFecha.value;


if (
    !idServicio ||
    !fecha
) {

    createHorario.disabled =
        true;


    createHorario.innerHTML =
        '<option value="">Selecciona servicio y fecha</option>';


    if (
        createHorarioEstado
    ) {

        createHorarioEstado.textContent =
            "Selecciona un servicio y una fecha.";

    }


    actualizarPreview();

    return;

}


createHorario.disabled =
    true;


createHorario.innerHTML =
    '<option value="">Cargando horarios...</option>';


if (
    createHorarioEstado
) {

    createHorarioEstado.textContent =
        "Cargando horarios disponibles...";

}


try {

    const url =
        `${API_HORARIOS_URL}?servicio=${encodeURIComponent(idServicio)}&fecha=${encodeURIComponent(fecha)}`;


    const respuesta =
        await fetch(
            url
        );


    const datos =
        await respuesta.json();


    if (
        !datos.ok
    ) {

        throw new Error(
            datos.mensaje ||
            "No fue posible obtener los horarios."
        );

    }


    createHorario.innerHTML =
        '<option value="">Selecciona un horario</option>';


    let horasDisponibles =
        0;


    datos.horarios.forEach(
        horario => {

            const option =
                document.createElement(
                    "option"
                );


            option.value =
                horario.hora;


            option.textContent =
                horario.hora;


            if (
                horario.estado ===
                    "bloqueado" ||
                horario.estado ===
                    "ocupado" ||
                horario.disponible ===
                    false
            ) {

                option.disabled =
                    true;


                option.textContent =
                    `${horario.hora} - No disponible`;

            } else {

                horasDisponibles++;

            }


            createHorario.appendChild(
                option
            );

        }
    );


    if (
        horaSeleccionada
    ) {

        const opcion =
            Array.from(
                createHorario.options
            )
            .find(
                option =>
                    option.value ===
                        horaSeleccionada &&
                    !option.disabled
            );


        if (
            opcion
        ) {

            createHorario.value =
                horaSeleccionada;

        } else {

            mostrarNotificacion(
                "El horario seleccionado ya no está disponible.",
                "warning"
            );

        }

    }


    createHorario.disabled =
        horasDisponibles ===
        0;


    if (
        createHorarioEstado
    ) {

        createHorarioEstado.textContent =
            horasDisponibles > 0

                ? `${horasDisponibles} horario(s) disponible(s).`

                : "No hay horarios disponibles para esta fecha.";

    }


    actualizarPreview();

} catch (
    error
) {

    console.error(
        "Error cargando horarios:",
        error
    );


    createHorario.innerHTML =
        '<option value="">No se pudieron cargar los horarios</option>';


    createHorario.disabled =
        true;


    if (
        createHorarioEstado
    ) {

        createHorarioEstado.textContent =
            "No fue posible cargar los horarios.";

    }


    mostrarNotificacion(
        error.message ||
        "No fue posible cargar los horarios.",
        "error"
    );

}


}


/*
|--------------------------------------------------------------------------
| ACTUALIZAR ESTADO FORMULARIO
|--------------------------------------------------------------------------
*/

function actualizarEstadoFormulario() {


const datosValidos =
    datosPersonalesValidos();


/*
 * DATOS PERSONALES INCOMPLETOS
 */

if (
    !datosValidos
) {

    datosPersonalesValidosAnteriormente =
        false;


    if (
        createDepartamento
    ) {

        createDepartamento.disabled =
            true;

    }


    if (
        createServicio
    ) {

        createServicio.disabled =
            true;

    }


    if (
        createFecha
    ) {

        createFecha.disabled =
            true;

    }


    if (
        createHorario
    ) {

        createHorario.disabled =
            true;


        createHorario.innerHTML =
            '<option value="">Completa los datos personales</option>';

    }


    if (
        createHorarioEstado
    ) {

        createHorarioEstado.textContent =
            "Completa correctamente nombre, identificación y teléfono.";

    }


    actualizarPreview();

    return;

}


/*
 * DATOS PERSONALES CORRECTOS
 */

datosPersonalesValidosAnteriormente =
    true;


/*
 * HABILITAR DEPARTAMENTO
 */

if (
    createDepartamento
) {

    createDepartamento.disabled =
        false;

}


/*
 * HABILITAR SERVICIO
 */

if (
    createServicio
) {

    createServicio.disabled =
        !createDepartamento?.value;

}


/*
 * HABILITAR FECHA
 */

if (
    createFecha
) {

    createFecha.disabled =
        !createServicio?.value;

}


/*
 * CARGAR HORARIOS
 */

if (
    createServicio?.value &&
    createFecha?.value
) {

    cargarHorariosCrear(
        contextoReserva.hora
    );

} else {

    if (
        createHorario
    ) {

        createHorario.disabled =
            true;


        createHorario.innerHTML =
            '<option value="">Selecciona servicio y fecha</option>';

    }


    if (
        createHorarioEstado
    ) {

        createHorarioEstado.textContent =
            "Selecciona un servicio y una fecha.";

    }

}


actualizarPreview();


}


/*
|--------------------------------------------------------------------------
| ABRIR CREAR RESERVA
|--------------------------------------------------------------------------
*/

async function abrirCrearReserva(
servicio = "",
servicioNombre = "",
fecha = "",
hora = ""
) {


if (
    !createReservationSection
) {

    return;

}


const resultadoServicio =
    obtenerDepartamentoPorServicio(
        servicio
    );


const idDepartamento =
    resultadoServicio?.departamento?.id_departamento ||
    "";


contextoReserva = {

    departamento:
        idDepartamento,

    servicio:
        servicio || "",

    fecha:
        fecha || "",

    hora:
        hora || "",

    servicioFijo:
        false,

    fechaFija:
        false

};


datosPersonalesValidosAnteriormente =
false;


if (
    createNombre
) {

    createNombre.value =
        "";

}


if (
    createRut
) {

    createRut.value =
        "";

}


if (
    createTelefono
) {

    createTelefono.value =
        "";

}


if (
    createCorreo
) {

    createCorreo.value =
        "";

}


if (
    createObservacion
) {

    createObservacion.value =
        "";

}


const radioRut =
    document.querySelector(
        'input[name="tipo_identificacion"][value="rut"]'
    );


if (
    radioRut
) {

    radioRut.checked =
        true;

}


/*
 * CARGAR DEPARTAMENTOS
 */

if (
    createDepartamento
) {

    await cargarDepartamentosCrear();


    if (
        idDepartamento
    ) {

        createDepartamento.value =
            String(
                idDepartamento
            );


        cargarServiciosPorDepartamento(
            idDepartamento,
            servicio
        );

    }

} else if (
    createServicio
) {

    createServicio.value =
        servicio || "";

}


/*
 * PRESELECCIONAR DEPARTAMENTO
 */

if (
    createDepartamento
) {

    createDepartamento.disabled =
        false;

}


/*
 * PRESELECCIONAR SERVICIO
 */

if (
    createServicio
) {

    createServicio.value =
        servicio || "";


    createServicio.disabled =
        !createDepartamento?.value;

}


/*
 * PRESELECCIONAR FECHA
 */

if (
    createFecha
) {

    createFecha.value =
        fecha || "";


    createFecha.disabled =
        !createServicio?.value;

}


/*
 * PREPARAR HORARIOS
 */

if (
    createHorario
) {

    createHorario.innerHTML =
        '<option value="">Completa los datos personales</option>';


    createHorario.value =
        "";


    createHorario.disabled =
        true;

}


if (
    createHorarioEstado
) {

    createHorarioEstado.textContent =
        "Completa correctamente nombre, identificación y teléfono.";

}


/*
 * MOSTRAR POPUP DE CREACIÓN
 */

createReservationSection.hidden =
    false;


createReservationSection.style.display =
    "flex";


createReservationSection.classList.add(
    "active"
);


document.body.classList.add(
    "modal-open"
);


actualizarPreview();

actualizarEstadoFormulario();


}


/*
|--------------------------------------------------------------------------
| CERRAR CREAR RESERVA
|--------------------------------------------------------------------------
*/

function cerrarCrearReserva() {


if (
    !createReservationSection
) {

    return;

}


/*
 * Ocultar completamente el formulario.
 */

createReservationSection.hidden =
    true;


createReservationSection.style.display =
    "none";


createReservationSection.classList.remove(
    "active"
);


/*
 * Desbloquear el scroll del documento.
 */

document.body.classList.remove(
    "modal-open"
);


/*
 * Limpiar el contexto de la reserva.
 */

contextoReserva = {

    departamento:
        "",

    servicio:
        "",

    fecha:
        "",

    hora:
        "",

    servicioFijo:
        false,

    fechaFija:
        false

};


datosPersonalesValidosAnteriormente =
false;


}


/*
|--------------------------------------------------------------------------
| BOTÓN CANCELAR DEL FORMULARIO
|--------------------------------------------------------------------------
*/

if (
btnCancelCreate
) {


btnCancelCreate.addEventListener(
    "click",
    function (event) {

        event.preventDefault();

        event.stopPropagation();

        cerrarCrearReserva();

    }
);


}


/*
|--------------------------------------------------------------------------
| BOTÓN X DEL FORMULARIO
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| El HTML de formulario_crear_reserva.php utiliza:
|
| id="btnCloseCreateModal"
|
| Por eso la X se busca mediante ese ID.
|
| Se usa delegación de eventos para que también funcione si el
| formulario es insertado dinámicamente.
|--------------------------------------------------------------------------

*/

document.addEventListener(
    "click",
    function (event) {

        const botonCerrar =
            event.target.closest(
                "#btnCloseCreateModal"
            );


        if (
            !botonCerrar
        ) {

            return;

        }


        event.preventDefault();

        event.stopPropagation();


        cerrarCrearReserva();

    },
    true
);


/*
|--------------------------------------------------------------------------
| CAMBIAR DEPARTAMENTO DEL FORMULARIO
|--------------------------------------------------------------------------
*/

if (
createDepartamento
) {


createDepartamento.addEventListener(
    "change",
    function () {

        if (
            !datosPersonalesValidos()
        ) {

            actualizarEstadoFormulario();

            return;

        }


        const idDepartamento =
            this.value;


        /*
         * Al cambiar de departamento,
         * el servicio, fecha y hora anteriores
         * dejan de ser válidos.
         */

        contextoReserva.departamento =
            idDepartamento;


        contextoReserva.servicio =
            "";


        contextoReserva.fecha =
            "";


        contextoReserva.hora =
            "";


        cargarServiciosPorDepartamento(
            idDepartamento
        );


        if (
            createFecha
        ) {

            createFecha.value =
                "";

            createFecha.disabled =
                true;

        }


        if (
            createHorario
        ) {

            createHorario.value =
                "";

            createHorario.disabled =
                true;

            createHorario.innerHTML =
                '<option value="">Selecciona un servicio</option>';

        }


        if (
            createHorarioEstado
        ) {

            createHorarioEstado.textContent =
                "Selecciona un servicio.";

        }


        if (
            errorDepartamento
        ) {

            errorDepartamento.textContent =
                "";

        }


        if (
            errorServicio
        ) {

            errorServicio.textContent =
                "";

        }


        actualizarPreview();

    }
);


}


/*
|--------------------------------------------------------------------------
| CAMBIAR SERVICIO DEL FORMULARIO
|--------------------------------------------------------------------------
*/

if (
createServicio
) {


createServicio.addEventListener(
    "change",
    function () {

        if (
            !datosPersonalesValidos()
        ) {

            actualizarEstadoFormulario();

            return;

        }


        /*
         * Al cambiar de servicio,
         * la fecha y hora anteriores
         * dejan de ser válidas.
         */

        contextoReserva.servicio =
            this.value;


        contextoReserva.fecha =
            "";


        contextoReserva.hora =
            "";


        if (
            createFecha
        ) {

            createFecha.value =
                "";

            createFecha.disabled =
                !this.value;

        }


        if (
            createHorario
        ) {

            createHorario.value =
                "";

            createHorario.disabled =
                true;

            createHorario.innerHTML =
                '<option value="">Selecciona una fecha</option>';

        }


        if (
            createHorarioEstado
        ) {

            createHorarioEstado.textContent =
                "Selecciona una fecha.";

        }


        if (
            errorServicio
        ) {

            errorServicio.textContent =
                "";

        }


        actualizarPreview();

    }
);


}


/*
|--------------------------------------------------------------------------
| CAMBIAR FECHA DEL FORMULARIO
|--------------------------------------------------------------------------
*/

if (
createFecha
) {


createFecha.addEventListener(
    "change",
    function () {

        if (
            !datosPersonalesValidos()
        ) {

            actualizarEstadoFormulario();

            return;

        }


        const fecha =
            createFecha.value;


        if (
            !fecha
        ) {

            if (
                createHorario
            ) {

                createHorario.value =
                    "";

                createHorario.disabled =
                    true;

                createHorario.innerHTML =
                    '<option value="">Selecciona una fecha</option>';

            }


            contextoReserva.hora =
                "";


            contextoReserva.fecha =
                "";


            actualizarPreview();

            return;

        }


        if (
            errorFecha
        ) {

            errorFecha.textContent =
                "";

        }


        /*
         * Al cambiar la fecha,
         * la hora anterior deja de ser válida.
         */

        contextoReserva.fecha =
            fecha;


        contextoReserva.hora =
            "";


        cargarHorariosCrear();


        actualizarPreview();

    }
);


}


/*
|--------------------------------------------------------------------------
| CAMBIAR HORARIO DEL FORMULARIO
|--------------------------------------------------------------------------
*/

if (
createHorario
) {


createHorario.addEventListener(
    "change",
    function () {

        if (
            errorHorario
        ) {

            errorHorario.textContent =
                "";

        }


        contextoReserva.hora =
            this.value ||
            "";


        actualizarPreview();

    }
);


}


/*
|--------------------------------------------------------------------------
| VALIDACIÓN DE DATOS PERSONALES
|--------------------------------------------------------------------------
*/

[
createNombre,
createRut,
createTelefono,
createCorreo
].forEach(
elemento => {


    if (
        !elemento
    ) {

        return;

    }


    elemento.addEventListener(
        "input",
        () => {

            /*
             * El RUT se formatea mientras se escribe.
             */

            if (
                elemento ===
                createRut &&
                obtenerTipoIdentificacion() ===
                "rut"
            ) {

                const valorAnterior =
                    createRut.value;


                createRut.value =
                    formatearRut(
                        createRut.value
                    );


                /*
                 * Mantener el cursor al final
                 * para una escritura natural.
                 */

                if (
                    createRut.value !==
                    valorAnterior
                ) {

                    createRut.selectionStart =
                        createRut.value.length;

                    createRut.selectionEnd =
                        createRut.value.length;

                }

            }


            /*
             * Actualizar inmediatamente el estado
             * para desbloquear los siguientes campos
             * apenas los datos sean válidos.
             */

            actualizarEstadoFormulario();

            actualizarPreview();

        }
    );


    elemento.addEventListener(
        "blur",
        () => {

            /*
             * Formatear RUT al salir del campo.
             */

            if (
                elemento ===
                createRut &&
                obtenerTipoIdentificacion() ===
                "rut"
            ) {

                createRut.value =
                    formatearRut(
                        createRut.value
                    );

            }


            actualizarEstadoFormulario();

            actualizarPreview();

        }
    );


}


);


/*
|--------------------------------------------------------------------------
| CAMBIO DE TIPO DE IDENTIFICACIÓN
|--------------------------------------------------------------------------
*/

document.querySelectorAll(
'input[name="tipo_identificacion"]'
).forEach(
radio => {


    radio.addEventListener(
        "change",
        function () {

            /*
             * Si vuelve a RUT,
             * formatear el valor existente.
             */

            if (
                obtenerTipoIdentificacion() ===
                "rut" &&
                createRut
            ) {

                createRut.value =
                    formatearRut(
                        createRut.value
                    );

            }


            if (
                errorRut
            ) {

                errorRut.textContent =
                    "";

            }


            actualizarEstadoFormulario();

            actualizarPreview();

        }
    );


}


);


/*
|--------------------------------------------------------------------------
| BOTONES DE CREACIÓN DE RESERVA
|--------------------------------------------------------------------------
*/

const botonesAgregarHora =
document.querySelectorAll(
".btn-agregar-hora, #btnAgregarHora, #btnAgregarHoraAgenda, #btnAgregarHoraDia, #btnReservaAsistida"
);

botonesAgregarHora.forEach(
boton => {


    boton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();

            event.stopPropagation();


            const servicio =
                boton.dataset.servicio ||
                boton.getAttribute(
                    "data-servicio"
                ) ||
                servicioSeleccionado ||
                "";


            const servicioNombre =
                boton.dataset.servicioNombre ||
                boton.getAttribute(
                    "data-servicio-nombre"
                ) ||
                "";


            const fecha =
                boton.dataset.fecha ||
                boton.getAttribute(
                    "data-fecha"
                ) ||
                fechaSeleccionada ||
                "";


            abrirCrearReserva(
                servicio,
                servicioNombre,
                fecha,
                ""
            );

        }
    );

}


);


/*
|--------------------------------------------------------------------------
| BOTONES RESERVAR
|--------------------------------------------------------------------------
*/

const botonesReservar =
document.querySelectorAll(
".time-action.reserve"
);

botonesReservar.forEach(
boton => {


    boton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();


            const servicio =
                boton.dataset.servicio ||
                boton.getAttribute(
                    "data-servicio"
                ) ||
                servicioSeleccionado ||
                "";


            const servicioNombre =
                boton.dataset.servicioNombre ||
                boton.getAttribute(
                    "data-servicio-nombre"
                ) ||
                "";


            const fecha =
                boton.dataset.fecha ||
                boton.getAttribute(
                    "data-fecha"
                ) ||
                fechaSeleccionada ||
                "";


            const hora =
                boton.dataset.hora ||
                boton.getAttribute(
                    "data-hora"
                ) ||
                "";


            if (
                !servicio ||
                !fecha ||
                !hora
            ) {

                mostrarNotificacion(
                    "No fue posible obtener los datos del horario.",
                    "error"
                );

                return;

            }


            abrirCrearReserva(
                servicio,
                servicioNombre,
                fecha,
                hora
            );

        }
    );

}


);


/*
|--------------------------------------------------------------------------
| BOTONES VER RESERVA
|--------------------------------------------------------------------------
*/

const botonesVerReserva =
document.querySelectorAll(
".time-action.view"
);

botonesVerReserva.forEach(
boton => {


    boton.addEventListener(
        "click",
        function (event) {

            event.preventDefault();

            event.stopPropagation();

            window.location.href =
                "gestionar_reserva.php";

        }
    );

}


);


/*
|--------------------------------------------------------------------------
| ENVIAR FORMULARIO CREAR RESERVA
|--------------------------------------------------------------------------
*/

if (
createReservationForm
) {


createReservationForm.addEventListener(
    "submit",
    async function (event) {

        event.preventDefault();


        let valido =
            true;


        if (
            !validarNombre()
        ) {

            valido =
                false;


            if (
                errorNombre
            ) {

                errorNombre.textContent =
                    "Ingresa un nombre válido.";

            }

        } else if (
            errorNombre
        ) {

            errorNombre.textContent =
                "";

        }


        if (
            !validarIdentificacion()
        ) {

            valido =
                false;


            if (
                errorRut
            ) {

                const tipo =
                    obtenerTipoIdentificacion();


                errorRut.textContent =
                    tipo === "rut"

                        ? "Ingresa un RUT válido."

                        : "Ingresa una identificación válida.";

            }

        } else if (
            errorRut
        ) {

            errorRut.textContent =
                "";

        }


        if (
            !validarTelefono()
        ) {

            valido =
                false;


            if (
                errorTelefono
            ) {

                errorTelefono.textContent =
                    "Ingresa un teléfono válido.";

            }

        } else if (
            errorTelefono
        ) {

            errorTelefono.textContent =
                "";

        }


        if (
            !validarCorreo()
        ) {

            valido =
                false;


            if (
                errorCorreo
            ) {

                errorCorreo.textContent =
                    "Ingresa un correo válido.";

            }

        } else if (
            errorCorreo
        ) {

            errorCorreo.textContent =
                "";

        }


        /*
         * VALIDAR DEPARTAMENTO
         */

        const departamento =
            createDepartamento?.value ||
            "";


        if (
            !departamento
        ) {

            valido =
                false;


            if (
                errorDepartamento
            ) {

                errorDepartamento.textContent =
                    "Selecciona un departamento.";

            }

        } else if (
            errorDepartamento
        ) {

            errorDepartamento.textContent =
                "";

        }


        /*
         * VALIDAR SERVICIO
         */

        const idServicio =
            createServicio?.value ||
            "";


        const servicioNombreSeleccionado =
            obtenerNombreServicioSeleccionado();


        if (
            !idServicio ||
            !servicioNombreSeleccionado
        ) {

            valido =
                false;


            if (
                errorServicio
            ) {

                errorServicio.textContent =
                    "Selecciona un servicio.";

            }

        } else if (
            errorServicio
        ) {

            errorServicio.textContent =
                "";

        }


        const fecha =
            createFecha?.value ||
            "";


        if (
            !fecha
        ) {

            valido =
                false;


            if (
                errorFecha
            ) {

                errorFecha.textContent =
                    "Selecciona una fecha.";

            }

        } else if (
            errorFecha
        ) {

            errorFecha.textContent =
                "";

        }


        const hora =
            createHorario?.value ||
            "";


        if (
            !hora
        ) {

            valido =
                false;


            if (
                errorHorario
            ) {

                errorHorario.textContent =
                    "Selecciona un horario.";

            }

        } else if (
            errorHorario
        ) {

            errorHorario.textContent =
                "";

        }


        if (
            !valido
        ) {

            actualizarEstadoFormulario();


            mostrarNotificacion(
                "Completa correctamente todos los campos requeridos.",
                "error"
            );


            return;

        }


        const datos =
            new FormData(
                createReservationForm
            );


        datos.set(
            "nombre",
            createNombre.value.trim()
        );


        datos.set(
            "rut",
            createRut.value.trim()
        );


        datos.set(
            "tipo_identificacion",
            obtenerTipoIdentificacion()
        );


        datos.set(
            "telefono",
            createTelefono.value.trim()
        );


        datos.set(
            "correo",
            createCorreo?.value.trim() ||
            ""
        );


        /*
         * crear_reserva.php recibe
         * el ID del departamento.
         */

        datos.set(
            "departamento",
            departamento
        );


        /*
         * crear_reserva.php recibe
         * el ID del servicio.
         */

        datos.set(
            "servicio",
            idServicio
        );


        datos.set(
            "fecha",
            fecha
        );


        datos.set(
            "hora",
            hora
        );


        datos.set(
            "observacion",
            createObservacion?.value.trim() ||
            ""
        );


        datos.set(
            "modo",
            "funcionario"
        );


        const botonSubmit =
            createReservationForm.querySelector(
                'button[type="submit"]'
            );


        if (
            botonSubmit
        ) {

            botonSubmit.disabled =
                true;


            botonSubmit.dataset.textoOriginal =
                botonSubmit.textContent;


            botonSubmit.textContent =
                "Reservando...";

        }


        try {

            const respuesta =
                await fetch(
                    API_CREAR_URL,
                    {
                        method:
                            "POST",

                        body:
                            datos
                    }
                );


            const resultado =
                await respuesta.json();


            if (
                !respuesta.ok ||
                !resultado.ok
            ) {

                throw new Error(
                    resultado.mensaje ||
                    "No fue posible crear la reserva."
                );

            }


            mostrarNotificacion(
                "La hora fue reservada correctamente.",
                "success"
            );


            cerrarCrearReserva();


            setTimeout(
                () => {

                    const parametros =
                        new URLSearchParams();


                    /*
                     * Mantener departamento seleccionado.
                     */

                    if (
                        departamentoSeleccionado
                    ) {

                        parametros.set(
                            "departamento",
                            departamentoSeleccionado
                        );

                    }


                    /*
                     * Mantener servicio seleccionado.
                     */

                    if (
                        servicioSeleccionado
                    ) {

                        parametros.set(
                            "servicio",
                            servicioSeleccionado
                        );

                    }


                    if (
                        fechaSeleccionada
                    ) {

                        parametros.set(
                            "fecha",
                            fechaSeleccionada
                        );

                    }


                    window.location.href =
                        "agenda.php?" +
                        parametros.toString();

                },
                800
            );


        } catch (
            error
        ) {

            console.error(
                "Error creando reserva:",
                error
            );


            mostrarNotificacion(
                error.message ||
                "No fue posible crear la reserva.",
                "error"
            );


        } finally {

            if (
                botonSubmit
            ) {

                botonSubmit.disabled =
                    false;


                botonSubmit.textContent =
                    botonSubmit.dataset.textoOriginal ||
                    "Reservar";

            }

        }

    }
);


}


/*
|--------------------------------------------------------------------------
| ESTADO INICIAL DEL POPUP
|--------------------------------------------------------------------------
*/

if (
createReservationSection
) {


createReservationSection.hidden =
    true;


createReservationSection.style.display =
    "none";


createReservationSection.classList.remove(
    "active"
);


}


/*
|--------------------------------------------------------------------------
| FECHA MÍNIMA
|--------------------------------------------------------------------------
*/

if (
createFecha
) {


createFecha.min =
    obtenerFechaLocal();


}