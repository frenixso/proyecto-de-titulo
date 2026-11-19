/* =========================================================
   GESTIÓN DE PROFESIONALES
   JavaScript complementario de gestionar_funcionarios.php

   Este archivo es independiente de gestionar_funcionarios.js.
   Gestiona profesionales que NO tienen usuario ni contraseña.
   ========================================================= */

const API_PROFESIONALES_URL = "../api/gestionar_profesionales.php";

let modalProfesional;
let formProfesional;
let btnNuevoProfesional;
let btnCerrarModalProfesional;
let btnCancelarProfesional;
let btnGuardarProfesional;
let modalProfesionalTitulo;
let profesionalId;
let profesionalNombre;
let profesionalApellido;
let profesionalRut;
let profesionalEstamentoSelect;
let profesionalEstamentoOtro;
let profesionalNodo;
let profesionalActivo;
let notificacionProfesional;

/* =========================================================
   INICIALIZACIÓN
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {
    inicializarPestanasPersonal();
    obtenerElementosProfesional();
    inicializarEventosProfesional();
    actualizarCampoOtroEstamento();
});

function obtenerElementosProfesional() {
    modalProfesional = document.getElementById("modalProfesional");
    formProfesional = document.getElementById("formProfesional");
    btnNuevoProfesional = document.getElementById("btnNuevoProfesional");
    btnCerrarModalProfesional = document.getElementById("btnCerrarModalProfesional");
    btnCancelarProfesional = document.getElementById("btnCancelarProfesional");
    btnGuardarProfesional = document.getElementById("btnGuardarProfesional");
    modalProfesionalTitulo = document.getElementById("modalProfesionalTitulo");
    profesionalId = document.getElementById("profesionalId");
    profesionalNombre = document.getElementById("profesionalNombre");
    profesionalApellido = document.getElementById("profesionalApellido");
    profesionalRut = document.getElementById("profesionalRut");
    profesionalEstamentoSelect = document.getElementById("profesionalEstamentoSelect");
    profesionalEstamentoOtro = document.getElementById("profesionalEstamentoOtro");
    profesionalNodo = document.getElementById("profesionalNodo");
    profesionalActivo = document.getElementById("profesionalActivo");
    notificacionProfesional = document.getElementById("notificacionProfesional");
}

/* =========================================================
   PESTAÑAS
   ========================================================= */

function inicializarPestanasPersonal() {
    const contenedor = document.getElementById("gestionPersonal");
    const botones = document.querySelectorAll(".management-tab[data-vista]");

    if (!contenedor || botones.length === 0) {
        return;
    }

    botones.forEach(function (boton) {
        boton.addEventListener("click", function () {
            cambiarVistaPersonal(boton.dataset.vista, true);
        });
    });

    cambiarVistaPersonal(contenedor.dataset.vistaInicial || "funcionarios", false);
}

function cambiarVistaPersonal(vista, actualizarUrl) {
    const vistaFuncionarios = document.getElementById("seccionFuncionarios");
    const vistaProfesionales = document.getElementById("seccionProfesionales");
    const btnNuevoFuncionario = document.getElementById("btnNuevoFuncionario");
    const btnNuevoProfesional = document.getElementById("btnNuevoProfesional");
    const botones = document.querySelectorAll(".management-tab[data-vista]");

    const mostrarProfesionales = vista === "profesionales";

    if (vistaFuncionarios) {
        vistaFuncionarios.hidden = mostrarProfesionales;
    }

    if (vistaProfesionales) {
        vistaProfesionales.hidden = !mostrarProfesionales;
    }

    if (btnNuevoFuncionario) {
        btnNuevoFuncionario.hidden = mostrarProfesionales;
    }

    if (btnNuevoProfesional) {
        btnNuevoProfesional.hidden = !mostrarProfesionales;
    }

    botones.forEach(function (boton) {
        const activo = boton.dataset.vista === vista;
        boton.classList.toggle("active", activo);
        boton.setAttribute("aria-selected", activo ? "true" : "false");
    });

    if (actualizarUrl && window.history && window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.set("vista", mostrarProfesionales ? "profesionales" : "funcionarios");
        window.history.replaceState({}, "", url.toString());
    }
}

/* =========================================================
   EVENTOS DEL MÓDULO PROFESIONALES
   ========================================================= */

function inicializarEventosProfesional() {
    if (btnNuevoProfesional) {
        btnNuevoProfesional.addEventListener("click", abrirModalCrearProfesional);
    }

    if (btnCerrarModalProfesional) {
        btnCerrarModalProfesional.addEventListener("click", cerrarModalProfesional);
    }

    if (btnCancelarProfesional) {
        btnCancelarProfesional.addEventListener("click", cerrarModalProfesional);
    }

    if (modalProfesional) {
        modalProfesional.addEventListener("click", function (evento) {
            if (evento.target === modalProfesional) {
                cerrarModalProfesional();
            }
        });
    }

    document.addEventListener("keydown", function (evento) {
        if (evento.key === "Escape" && modalProfesional && !modalProfesional.hidden) {
            cerrarModalProfesional();
        }
    });

    if (formProfesional) {
        formProfesional.addEventListener("submit", guardarProfesional);
    }

    if (profesionalEstamentoSelect) {
        profesionalEstamentoSelect.addEventListener("change", actualizarCampoOtroEstamento);
    }

    document.querySelectorAll("[data-profesional-action]").forEach(function (boton) {
        boton.addEventListener("click", manejarAccionProfesional);
    });
}

function manejarAccionProfesional(evento) {
    const boton = evento.currentTarget;
    const accion = boton.dataset.profesionalAction;
    const id = boton.dataset.id;

    if (!id) {
        return;
    }

    if (accion === "editar") {
        abrirModalEditarProfesional(id);
        return;
    }

    if (accion === "estado") {
        cambiarEstadoProfesional(id, boton.dataset.estado);
    }
}

/* =========================================================
   ESTAMENTO: SELECCIÓN ÚNICA O AGREGAR OTRO
   ========================================================= */

function actualizarCampoOtroEstamento() {
    if (!profesionalEstamentoSelect || !profesionalEstamentoOtro) {
        return;
    }

    const usarOtro = profesionalEstamentoSelect.value === "__otro__";
    profesionalEstamentoOtro.hidden = !usarOtro;
    profesionalEstamentoOtro.required = usarOtro;

    if (!usarOtro) {
        profesionalEstamentoOtro.value = "";
    }
}

function obtenerEstamentoSeleccionado() {
    if (!profesionalEstamentoSelect) {
        return "";
    }

    if (profesionalEstamentoSelect.value === "__otro__") {
        return profesionalEstamentoOtro ? profesionalEstamentoOtro.value.trim() : "";
    }

    return profesionalEstamentoSelect.value.trim();
}

function cargarEstamentoProfesional(estamento) {
    if (!profesionalEstamentoSelect || !profesionalEstamentoOtro) {
        return;
    }

    const opcionExiste = Array.from(profesionalEstamentoSelect.options).some(function (opcion) {
        return opcion.value === estamento;
    });

    if (opcionExiste) {
        profesionalEstamentoSelect.value = estamento;
        profesionalEstamentoOtro.value = "";
    } else {
        profesionalEstamentoSelect.value = "__otro__";
        profesionalEstamentoOtro.value = estamento || "";
    }

    actualizarCampoOtroEstamento();
}

/* =========================================================
   ABRIR MODAL: CREAR
   ========================================================= */

function abrirModalCrearProfesional() {
    if (!formProfesional || !modalProfesional) {
        return;
    }

    formProfesional.reset();

    if (profesionalId) {
        profesionalId.value = "";
    }

    if (modalProfesionalTitulo) {
        modalProfesionalTitulo.textContent = "Nuevo profesional";
    }

    if (profesionalActivo) {
        profesionalActivo.checked = true;
    }

    if (profesionalEstamentoSelect) {
        profesionalEstamentoSelect.value = "";
    }

    if (profesionalEstamentoOtro) {
        profesionalEstamentoOtro.value = "";
    }

    actualizarCampoOtroEstamento();
    limpiarErroresProfesional();
    ocultarNotificacionProfesional();

    modalProfesional.hidden = false;

    window.setTimeout(function () {
        if (profesionalNombre) {
            profesionalNombre.focus();
        }
    }, 50);
}

/* =========================================================
   ABRIR MODAL: EDITAR
   ========================================================= */

async function abrirModalEditarProfesional(id) {
    if (!modalProfesional) {
        return;
    }

    limpiarFormularioProfesional();

    if (modalProfesionalTitulo) {
        modalProfesionalTitulo.textContent = "Editar profesional";
    }

    modalProfesional.hidden = false;
    mostrarNotificacionProfesional("Cargando información del profesional...", "info");

    try {
        const respuesta = await fetch(
            API_PROFESIONALES_URL + "?accion=obtener&id=" + encodeURIComponent(id),
            {
                method: "GET",
                credentials: "same-origin",
                headers: { "Accept": "application/json" }
            }
        );

        const datos = await obtenerRespuestaJSONProfesional(respuesta);
        cargarDatosProfesional(datos.profesional);
        ocultarNotificacionProfesional();

        window.setTimeout(function () {
            if (profesionalNombre) {
                profesionalNombre.focus();
            }
        }, 50);
    } catch (error) {
        mostrarNotificacionProfesional(error.message, "error");
    }
}

function cargarDatosProfesional(profesional) {
    if (!profesional) {
        throw new Error("El servidor no devolvió los datos del profesional.");
    }

    if (profesionalId) profesionalId.value = profesional.id_profesional || "";
    if (profesionalNombre) profesionalNombre.value = profesional.nombre || "";
    if (profesionalApellido) profesionalApellido.value = profesional.apellido || "";
    if (profesionalRut) profesionalRut.value = profesional.rut || "";
    if (profesionalNodo) profesionalNodo.value = profesional.nodo || "";
    if (profesionalActivo) profesionalActivo.checked = convertirBooleanoProfesional(profesional.activo);

    cargarEstamentoProfesional(profesional.estamento || "");
}

/* =========================================================
   GUARDAR PROFESIONAL
   ========================================================= */

async function guardarProfesional(evento) {
    evento.preventDefault();

    limpiarErroresProfesional();
    ocultarNotificacionProfesional();

    if (!validarFormularioProfesional()) {
        return;
    }

    const esEdicion = profesionalId && profesionalId.value.trim() !== "";
    const accion = esEdicion ? "editar" : "crear";

    if (btnGuardarProfesional) {
        btnGuardarProfesional.disabled = true;
        btnGuardarProfesional.textContent = "Guardando...";
    }

    try {
        const datos = new FormData(formProfesional);
        datos.set("accion", accion);
        datos.set("estamento", obtenerEstamentoSeleccionado());
        datos.set("activo", profesionalActivo && profesionalActivo.checked ? "1" : "0");

        const respuesta = await fetch(API_PROFESIONALES_URL, {
            method: "POST",
            body: datos,
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        });

        const resultado = await obtenerRespuestaJSONProfesional(respuesta);
        mostrarNotificacionProfesional(resultado.mensaje || "Profesional guardado correctamente.", "success");

        window.setTimeout(function () {
            window.location.href = "gestionar_funcionarios.php?vista=profesionales";
        }, 650);
    } catch (error) {
        mostrarNotificacionProfesional(error.message, "error");
    } finally {
        if (btnGuardarProfesional) {
            btnGuardarProfesional.disabled = false;
            btnGuardarProfesional.textContent = "Guardar profesional";
        }
    }
}

/* =========================================================
   VALIDACIÓN DEL FORMULARIO
   ========================================================= */

function validarFormularioProfesional() {
    let valido = true;

    const nombre = profesionalNombre ? profesionalNombre.value.trim() : "";
    const apellido = profesionalApellido ? profesionalApellido.value.trim() : "";
    const rut = profesionalRut ? profesionalRut.value.trim() : "";
    const estamento = obtenerEstamentoSeleccionado();
    const nodo = profesionalNodo ? profesionalNodo.value : "";

    if (nombre.length < 2 || nombre.length > 100) {
        mostrarErrorProfesional("errorProfesionalNombre", "El nombre debe tener entre 2 y 100 caracteres.");
        valido = false;
    }

    if (apellido.length < 2 || apellido.length > 100) {
        mostrarErrorProfesional("errorProfesionalApellido", "El apellido debe tener entre 2 y 100 caracteres.");
        valido = false;
    }

    if (!validarFormatoRutProfesional(rut)) {
        mostrarErrorProfesional("errorProfesionalRut", "Ingresa un RUT válido con guion y dígito verificador.");
        valido = false;
    }

    if (estamento.length < 2 || estamento.length > 100) {
        mostrarErrorProfesional("errorProfesionalEstamento", "Selecciona o escribe un estamento de hasta 100 caracteres.");
        valido = false;
    }

    if (nodo !== "CESFAM" && nodo !== "CECOSF") {
        mostrarErrorProfesional("errorProfesionalNodo", "Selecciona CESFAM o CECOSF.");
        valido = false;
    }

    return valido;
}

function validarFormatoRutProfesional(rut) {
    return /^\d{1,2}\.?\d{3}\.?\d{3}-[\dkK]$/.test(rut);
}

/* =========================================================
   CAMBIAR ESTADO
   ========================================================= */

async function cambiarEstadoProfesional(id, estadoActual) {
    const activar = estadoActual !== "activo";
    const pregunta = activar
        ? "¿Deseas activar este profesional?"
        : "¿Deseas desactivar este profesional?";

    if (!window.confirm(pregunta)) {
        return;
    }

    const datos = new FormData();
    datos.set("accion", activar ? "activar" : "desactivar");
    datos.set("id_profesional", id);

    try {
        const respuesta = await fetch(API_PROFESIONALES_URL, {
            method: "POST",
            body: datos,
            credentials: "same-origin",
            headers: { "Accept": "application/json" }
        });

        const resultado = await obtenerRespuestaJSONProfesional(respuesta);
        window.alert(resultado.mensaje || "Estado del profesional actualizado.");
        window.location.href = "gestionar_funcionarios.php?vista=profesionales";
    } catch (error) {
        window.alert(error.message);
    }
}

/* =========================================================
   LIMPIEZA Y CIERRE
   ========================================================= */

function limpiarFormularioProfesional() {
    if (formProfesional) {
        formProfesional.reset();
    }

    if (profesionalId) profesionalId.value = "";
    if (profesionalEstamentoOtro) profesionalEstamentoOtro.value = "";
    if (profesionalActivo) profesionalActivo.checked = true;

    actualizarCampoOtroEstamento();
    limpiarErroresProfesional();
    ocultarNotificacionProfesional();
}

function cerrarModalProfesional() {
    if (modalProfesional) {
        modalProfesional.hidden = true;
    }

    limpiarErroresProfesional();
    ocultarNotificacionProfesional();
}

function limpiarErroresProfesional() {
    document.querySelectorAll("#formProfesional .form-error").forEach(function (elemento) {
        elemento.textContent = "";
    });
}

function mostrarErrorProfesional(id, mensaje) {
    const elemento = document.getElementById(id);
    if (elemento) {
        elemento.textContent = mensaje;
    }
}

/* =========================================================
   NOTIFICACIONES Y RESPUESTAS
   ========================================================= */

function mostrarNotificacionProfesional(mensaje, tipo) {
    if (!notificacionProfesional) {
        return;
    }

    notificacionProfesional.textContent = mensaje;
    notificacionProfesional.className = "form-notification " + (tipo || "info");
    notificacionProfesional.hidden = false;
}

function ocultarNotificacionProfesional() {
    if (!notificacionProfesional) {
        return;
    }

    notificacionProfesional.hidden = true;
    notificacionProfesional.textContent = "";
}

async function obtenerRespuestaJSONProfesional(respuesta) {
    let datos;

    try {
        datos = await respuesta.json();
    } catch (error) {
        throw new Error("El servidor devolvió una respuesta inválida.");
    }

    if (!respuesta.ok || datos.ok === false) {
        throw new Error(datos.error || datos.mensaje || "No fue posible completar la operación.");
    }

    return datos;
}

function convertirBooleanoProfesional(valor) {
    return valor === true || valor === 1 || valor === "1" || valor === "true" || valor === "t";
}