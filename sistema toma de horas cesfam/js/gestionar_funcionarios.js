
/*
|--------------------------------------------------------------------------
| GESTIÓN DE CUENTAS DEL SISTEMA
|--------------------------------------------------------------------------
| Archivo: js/gestionar_funcionarios.js
|
| Funciones:
| - Abrir y cerrar modal.
| - Crear cuentas del sistema.
| - Editar cuentas del sistema.
| - Activar y desactivar cuentas.
| - Eliminar cuentas.
| - Validar formulario.
| - Mostrar mensajes.
|
| TIPOS DE ACCESO:
| - superadmin.
| - administrador.
| - administrador_web.
|
| EQUIVALENCIAS EN LA BASE DE DATOS:
| - superadmin: rol = superadmin.
| - administrador: rol = administrador.
| - administrador_web: rol = funcionario
|   y puede_administrar_web = TRUE.
|
| Las cuentas no se asocian a departamentos.
|--------------------------------------------------------------------------
*/

/* =========================================================
   API
   ========================================================= */

const API_URL = "../api/gestionar_funcionarios.php";

/* =========================================================
   TIPOS DE ACCESO PERMITIDOS
   ========================================================= */

const TIPOS_ACCESO_PERMITIDOS = [
    "superadmin",
    "administrador",
    "administrador_web"
];

/* =========================================================
   ELEMENTOS DEL DOM
   ========================================================= */

let modalFuncionario;
let btnNuevoFuncionario;
let btnCerrarModalFuncionario;
let btnCancelarFuncionario;
let formFuncionario;
let modalFuncionarioTitulo;

let funcionarioId;
let funcionarioNombre;
let funcionarioApellido;
let funcionarioCorreo;
let funcionarioUsuario;
let funcionarioRol;
let funcionarioPassword;
let funcionarioActivo;

let notificacionFuncionario;

/* =========================================================
   ESTADO DEL FORMULARIO
   ========================================================= */

let modoFormulario = "crear";
let operacionEnCurso = false;

/* =========================================================
   INICIALIZAR
   ========================================================= */

document.addEventListener("DOMContentLoaded", function () {

    inicializarElementos();

    inicializarEventos();

});

/* =========================================================
   OBTENER ELEMENTOS
   ========================================================= */

function inicializarElementos() {

    modalFuncionario = document.getElementById(
        "modalFuncionario"
    );

    btnNuevoFuncionario = document.getElementById(
        "btnNuevoFuncionario"
    );

    btnCerrarModalFuncionario = document.getElementById(
        "btnCerrarModalFuncionario"
    );

    btnCancelarFuncionario = document.getElementById(
        "btnCancelarFuncionario"
    );

    formFuncionario = document.getElementById(
        "formFuncionario"
    );

    modalFuncionarioTitulo = document.getElementById(
        "modalFuncionarioTitulo"
    );

    funcionarioId = document.getElementById(
        "funcionarioId"
    );

    funcionarioNombre = document.getElementById(
        "funcionarioNombre"
    );

    funcionarioApellido = document.getElementById(
        "funcionarioApellido"
    );

    funcionarioCorreo = document.getElementById(
        "funcionarioCorreo"
    );

    if (!funcionarioCorreo && formFuncionario) {

        funcionarioCorreo = formFuncionario.querySelector(
            '[name="correo"]'
        );
    }

    funcionarioUsuario = document.getElementById(
        "funcionarioUsuario"
    );

    funcionarioRol = document.getElementById(
        "funcionarioRol"
    );

    funcionarioPassword = document.getElementById(
        "funcionarioPassword"
    );

    funcionarioActivo = document.getElementById(
        "funcionarioActivo"
    );

    notificacionFuncionario = document.getElementById(
        "notificacionFuncionario"
    );

    configurarOpcionesTipoAcceso();
}

/* =========================================================
   CONFIGURAR OPCIONES DE ACCESO
   =========================================================
   El tipo funcionario se mantiene únicamente para reconocer
   cuentas antiguas que todavía no tienen un tipo de acceso
   válido.

   No se permite crear nuevas cuentas con ese tipo.
   ========================================================= */

function configurarOpcionesTipoAcceso() {

    if (!funcionarioRol) {
        return;
    }

    const opcionAntigua = funcionarioRol.querySelector(
        'option[value="funcionario"]'
    );

    if (opcionAntigua) {

        opcionAntigua.textContent =
            "Cuenta antigua sin perfil asignado";

        opcionAntigua.disabled = true;
    }
}

/* =========================================================
   EVENTOS
   ========================================================= */

function inicializarEventos() {

    if (btnNuevoFuncionario) {

        btnNuevoFuncionario.addEventListener(
            "click",
            abrirModalCrear
        );
    }

    if (btnCerrarModalFuncionario) {

        btnCerrarModalFuncionario.addEventListener(
            "click",
            cerrarModal
        );
    }

    if (btnCancelarFuncionario) {

        btnCancelarFuncionario.addEventListener(
            "click",
            cerrarModal
        );
    }

    if (modalFuncionario) {

        modalFuncionario.addEventListener(
            "click",
            function (evento) {

                if (evento.target === modalFuncionario) {

                    cerrarModal();
                }

            }
        );
    }

    document.addEventListener(
        "keydown",
        function (evento) {

            if (
                evento.key === "Escape" &&
                modalFuncionario &&
                !modalFuncionario.hidden
            ) {

                cerrarModal();
            }

        }
    );

    if (formFuncionario) {

        formFuncionario.addEventListener(
            "submit",
            guardarFuncionario
        );
    }

    document.querySelectorAll("[data-action]").forEach(
        function (boton) {

            boton.addEventListener(
                "click",
                manejarAccion
            );

        }
    );
}

/* =========================================================
   MANEJAR ACCIONES
   ========================================================= */

function manejarAccion(evento) {

    const boton = evento.currentTarget;

    const accion = boton.dataset.action;

    const id = boton.dataset.id;

    if (!id) {
        return;
    }

    if (accion === "editar") {

        abrirModalEditar(id);

        return;
    }

    if (accion === "estado") {

        cambiarEstadoFuncionario(
            id,
            boton.dataset.estado
        );

        return;
    }

    if (accion === "eliminar") {

        eliminarFuncionario(id);
    }
}

/* =========================================================
   ABRIR MODAL CREAR
   ========================================================= */

function abrirModalCrear() {

    if (!modalFuncionario || !formFuncionario) {
        return;
    }

    modoFormulario = "crear";

    limpiarFormulario();

    if (modalFuncionarioTitulo) {

        modalFuncionarioTitulo.textContent =
            "Nueva cuenta de acceso";
    }

    if (funcionarioPassword) {

        funcionarioPassword.required = true;
    }

    if (funcionarioActivo) {

        funcionarioActivo.checked = true;
    }

    /*
     * Se establece administrador de reservas como opción
     * inicial para evitar crear accidentalmente otro superadmin.
     */

    if (funcionarioRol) {

        funcionarioRol.value = "administrador";
    }

    limpiarErrores();

    ocultarNotificacion();

    modalFuncionario.hidden = false;

    setTimeout(function () {

        if (funcionarioNombre) {

            funcionarioNombre.focus();
        }

    }, 50);
}

/* =========================================================
   ABRIR MODAL EDITAR
   ========================================================= */

async function abrirModalEditar(id) {

    if (!modalFuncionario || !formFuncionario) {
        return;
    }

    if (operacionEnCurso) {
        return;
    }

    modoFormulario = "editar";

    limpiarFormulario();

    if (modalFuncionarioTitulo) {

        modalFuncionarioTitulo.textContent =
            "Editar cuenta de acceso";
    }

    if (funcionarioPassword) {

        funcionarioPassword.required = false;
    }

    modalFuncionario.hidden = false;

    mostrarNotificacion(
        "Cargando información de la cuenta...",
        "info"
    );

    try {

        const respuesta = await fetch(
            API_URL +
            "?accion=obtener&id=" +
            encodeURIComponent(id),
            {
                method: "GET",
                credentials: "same-origin"
            }
        );

        const datos = await obtenerRespuestaJSON(
            respuesta
        );

        if (!datos.funcionario) {

            throw new Error(
                "No se encontró la información de la cuenta."
            );
        }

        cargarDatosFuncionario(datos.funcionario);

        ocultarNotificacion();

        setTimeout(function () {

            if (funcionarioNombre) {

                funcionarioNombre.focus();
            }

        }, 50);

    } catch (error) {

        mostrarNotificacion(
            error.message,
            "error"
        );
    }
}

/* =========================================================
   CARGAR DATOS DE LA CUENTA
   ========================================================= */

function cargarDatosFuncionario(funcionario) {

    if (funcionarioId) {

        funcionarioId.value =
            funcionario.id_funcionario ?? "";
    }

    if (funcionarioNombre) {

        funcionarioNombre.value =
            funcionario.nombre ?? "";
    }

    if (funcionarioApellido) {

        funcionarioApellido.value =
            funcionario.apellido ?? "";
    }

    if (funcionarioCorreo) {

        funcionarioCorreo.value =
            funcionario.correo ?? "";
    }

    if (funcionarioUsuario) {

        funcionarioUsuario.value =
            funcionario.usuario ?? "";
    }

    /*
     * El backend entrega tipo_acceso:
     *
     * superadmin
     * administrador
     * administrador_web
     *
     * Para las cuentas antiguas se utiliza una conversión
     * de compatibilidad basada en rol y permiso web.
     */

    if (funcionarioRol) {

        let tipoAcceso = funcionario.tipo_acceso;

        if (!tipoAcceso) {

            if (
                funcionario.rol === "funcionario" &&
                convertirBooleano(
                    funcionario.puede_administrar_web
                )
            ) {

                tipoAcceso = "administrador_web";

            } else {

                tipoAcceso = funcionario.rol;
            }
        }

        funcionarioRol.value = tipoAcceso || "";

    }

    if (funcionarioPassword) {

        funcionarioPassword.value = "";
    }

    if (funcionarioActivo) {

        funcionarioActivo.checked =
            convertirBooleano(funcionario.activo);
    }
}

/* =========================================================
   GUARDAR CUENTA
   ========================================================= */

async function guardarFuncionario(evento) {

    evento.preventDefault();

    if (operacionEnCurso) {
        return;
    }

    limpiarErrores();

    ocultarNotificacion();

    if (!validarFormulario()) {
        return;
    }

    const botonGuardar = formFuncionario
        ? formFuncionario.querySelector(
            'button[type="submit"]'
        )
        : null;

    operacionEnCurso = true;

    if (botonGuardar) {

        botonGuardar.disabled = true;

        botonGuardar.textContent = "Guardando...";
    }

    try {

        const datos = new FormData(formFuncionario);

        /*
         * Acción que espera la API:
         * crear o editar.
         */

        datos.set(
            "accion",
            modoFormulario
        );

        /*
         * Estado de la cuenta.
         */

        datos.set(
            "activo",
            funcionarioActivo && funcionarioActivo.checked
                ? "1"
                : "0"
        );

        /*
         * El tipo de acceso se envía directamente.
         *
         * No se transforma administrador_web en funcionario.
         * El backend realiza la equivalencia interna.
         */

        datos.set(
            "rol",
            funcionarioRol.value
        );

        /*
         * Las cuentas no se relacionan con departamentos.
         */

        datos.delete("id_departamento");

        datos.delete("puede_administrar_web");

        /*
         * Limpiar datos de texto antes de enviar.
         */

        datos.set(
            "nombre",
            funcionarioNombre.value.trim()
        );

        datos.set(
            "apellido",
            funcionarioApellido.value.trim()
        );

        datos.set(
            "correo",
            funcionarioCorreo.value.trim()
        );

        datos.set(
            "usuario",
            funcionarioUsuario.value.trim()
        );

        const respuesta = await fetch(
            API_URL,
            {
                method: "POST",
                body: datos,
                credentials: "same-origin"
            }
        );

        const resultado = await obtenerRespuestaJSON(
            respuesta
        );

        mostrarNotificacion(
            resultado.mensaje ||
            "Cuenta guardada correctamente.",
            "success"
        );

        setTimeout(function () {

            window.location.reload();

        }, 900);

    } catch (error) {

        mostrarNotificacion(
            error.message,
            "error"
        );

    } finally {

        operacionEnCurso = false;

        if (botonGuardar) {

            botonGuardar.disabled = false;

            botonGuardar.textContent =
                "Guardar cuenta";
        }
    }
}

/* =========================================================
   CAMBIAR ESTADO
   ========================================================= */

async function cambiarEstadoFuncionario(id, estadoActual) {

    if (operacionEnCurso) {
        return;
    }

    const activar = estadoActual !== "activo";

    const mensaje = activar
        ? "¿Deseas activar esta cuenta?"
        : "¿Deseas desactivar esta cuenta?";

    if (!window.confirm(mensaje)) {
        return;
    }

    operacionEnCurso = true;

    try {

        const datos = new FormData();

        datos.set(
            "accion",
            activar ? "activar" : "desactivar"
        );

        datos.set(
            "id_funcionario",
            id
        );

        const respuesta = await fetch(
            API_URL,
            {
                method: "POST",
                body: datos,
                credentials: "same-origin"
            }
        );

        const resultado = await obtenerRespuestaJSON(
            respuesta
        );

        mostrarNotificacionGlobal(
            resultado.mensaje ||
            "Estado actualizado correctamente."
        );

        window.location.reload();

    } catch (error) {

        mostrarNotificacionGlobal(
            error.message
        );

    } finally {

        operacionEnCurso = false;
    }
}

/* =========================================================
   ELIMINAR CUENTA
   ========================================================= */

async function eliminarFuncionario(id) {

    if (operacionEnCurso) {
        return;
    }

    if (
        !window.confirm(
            "¿Estás seguro de que deseas eliminar esta cuenta? Esta acción no se puede deshacer."
        )
    ) {

        return;
    }

    operacionEnCurso = true;

    try {

        const datos = new FormData();

        datos.set(
            "accion",
            "eliminar"
        );

        datos.set(
            "id_funcionario",
            id
        );

        const respuesta = await fetch(
            API_URL,
            {
                method: "POST",
                body: datos,
                credentials: "same-origin"
            }
        );

        const resultado = await obtenerRespuestaJSON(
            respuesta
        );

        mostrarNotificacionGlobal(
            resultado.mensaje ||
            "Cuenta eliminada correctamente."
        );

        window.location.reload();

    } catch (error) {

        mostrarNotificacionGlobal(
            error.message
        );

    } finally {

        operacionEnCurso = false;
    }
}

/* =========================================================
   VALIDAR FORMULARIO
   ========================================================= */

function validarFormulario() {

    let valido = true;

    const nombre = funcionarioNombre
        ? funcionarioNombre.value.trim()
        : "";

    const apellido = funcionarioApellido
        ? funcionarioApellido.value.trim()
        : "";

    const correo = funcionarioCorreo
        ? funcionarioCorreo.value.trim()
        : "";

    const usuario = funcionarioUsuario
        ? funcionarioUsuario.value.trim()
        : "";

    const password = funcionarioPassword
        ? funcionarioPassword.value
        : "";

    const tipoAcceso = funcionarioRol
        ? funcionarioRol.value
        : "";

    /* =============================================
       NOMBRE
       ============================================= */

    if (nombre === "") {

        mostrarError(
            "errorFuncionarioNombre",
            "Ingresa el nombre."
        );

        valido = false;
    }

    /* =============================================
       APELLIDO
       ============================================= */

    if (apellido === "") {

        mostrarError(
            "errorFuncionarioApellido",
            "Ingresa el apellido."
        );

        valido = false;
    }

    /* =============================================
       CORREO
       ============================================= */

    if (correo === "") {

        mostrarError(
            "errorFuncionarioCorreo",
            "Ingresa el correo electrónico."
        );

        valido = false;

    } else if (!validarCorreo(correo)) {

        mostrarError(
            "errorFuncionarioCorreo",
            "Ingresa un correo electrónico válido."
        );

        valido = false;
    }

    /* =============================================
       USUARIO
       ============================================= */

    if (usuario === "") {

        mostrarError(
            "errorFuncionarioUsuario",
            "Ingresa el usuario."
        );

        valido = false;

    } else if (
        usuario.length < 3 ||
        usuario.length > 50
    ) {

        mostrarError(
            "errorFuncionarioUsuario",
            "El usuario debe tener entre 3 y 50 caracteres."
        );

        valido = false;
    }

    /* =============================================
       TIPO DE ACCESO
       ============================================= */

    if (
        !TIPOS_ACCESO_PERMITIDOS.includes(tipoAcceso)
    ) {

        mostrarNotificacion(
            "Selecciona un tipo de acceso válido: Superadmin, Administrador de reservas o Administrador web.",
            "error"
        );

        valido = false;
    }

    /* =============================================
       CONTRASEÑA
       ============================================= */

    if (
        modoFormulario === "crear" &&
        password.length < 6
    ) {

        mostrarError(
            "errorFuncionarioPassword",
            "La contraseña debe tener al menos 6 caracteres."
        );

        valido = false;

    } else if (
        modoFormulario === "editar" &&
        password !== "" &&
        password.length < 6
    ) {

        mostrarError(
            "errorFuncionarioPassword",
            "La contraseña debe tener al menos 6 caracteres."
        );

        valido = false;
    }

    return valido;
}

/* =========================================================
   VALIDAR CORREO
   ========================================================= */

function validarCorreo(correo) {

    const expresion =
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    return expresion.test(correo);
}

/* =========================================================
   MOSTRAR ERROR DE CAMPO
   ========================================================= */

function mostrarError(id, mensaje) {

    const elemento = document.getElementById(id);

    if (elemento) {

        elemento.textContent = mensaje;
    }
}

/* =========================================================
   LIMPIAR ERRORES
   ========================================================= */

function limpiarErrores() {

    if (!formFuncionario) {
        return;
    }

    formFuncionario.querySelectorAll(".form-error").forEach(
        function (elemento) {

            elemento.textContent = "";
        }
    );
}

/* =========================================================
   LIMPIAR FORMULARIO
   ========================================================= */

function limpiarFormulario() {

    if (formFuncionario) {

        formFuncionario.reset();
    }

    if (funcionarioId) {

        funcionarioId.value = "";
    }

    if (funcionarioNombre) {

        funcionarioNombre.value = "";
    }

    if (funcionarioApellido) {

        funcionarioApellido.value = "";
    }

    if (funcionarioCorreo) {

        funcionarioCorreo.value = "";
    }

    if (funcionarioUsuario) {

        funcionarioUsuario.value = "";
    }

    if (funcionarioRol) {

        funcionarioRol.value = "administrador";
    }

    if (funcionarioPassword) {

        funcionarioPassword.value = "";

        funcionarioPassword.required =
            modoFormulario === "crear";
    }

    if (funcionarioActivo) {

        funcionarioActivo.checked = true;
    }

    limpiarErrores();

    ocultarNotificacion();
}

/* =========================================================
   CERRAR MODAL
   ========================================================= */

function cerrarModal() {

    if (!modalFuncionario) {
        return;
    }

    modalFuncionario.hidden = true;

    limpiarErrores();

    ocultarNotificacion();
}

/* =========================================================
   MOSTRAR NOTIFICACIÓN
   ========================================================= */

function mostrarNotificacion(mensaje, tipo = "info") {

    if (!notificacionFuncionario) {
        return;
    }

    notificacionFuncionario.textContent = mensaje;

    notificacionFuncionario.className =
        "form-notification " + tipo;

    notificacionFuncionario.hidden = false;
}

/* =========================================================
   OCULTAR NOTIFICACIÓN
   ========================================================= */

function ocultarNotificacion() {

    if (!notificacionFuncionario) {
        return;
    }

    notificacionFuncionario.hidden = true;

    notificacionFuncionario.textContent = "";
}

/* =========================================================
   OBTENER RESPUESTA JSON
   ========================================================= */

async function obtenerRespuestaJSON(respuesta) {

    let datos;

    try {

        datos = await respuesta.json();

    } catch (error) {

        throw new Error(
            "El servidor devolvió una respuesta inválida."
        );
    }

    if (
        !respuesta.ok ||
        datos.ok === false ||
        datos.exito === false ||
        datos.error
    ) {

        throw new Error(
            datos.error ||
            datos.mensaje ||
            datos.message ||
            "No fue posible completar la operación."
        );
    }

    return datos;
}

/* =========================================================
   CONVERTIR BOOLEANO
   ========================================================= */

function convertirBooleano(valor) {

    return (
        valor === true ||
        valor === 1 ||
        valor === "1" ||
        valor === "true" ||
        valor === "t"
    );
}

/* =========================================================
   NOTIFICACIÓN GLOBAL
   ========================================================= */

function mostrarNotificacionGlobal(mensaje) {

    window.alert(mensaje);
}
