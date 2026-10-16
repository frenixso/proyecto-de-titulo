/*
|--------------------------------------------------------------------------
| GESTIÓN DE FUNCIONARIOS
|--------------------------------------------------------------------------
| JavaScript de funcionarios/gestionar_funcionarios.php
|
| Funciones:
| - Abrir y cerrar modal.
| - Crear funcionario.
| - Editar funcionario.
| - Activar funcionario.
| - Desactivar funcionario.
| - Eliminar funcionario.
| - Validar formulario.
| - Mostrar mensajes.
| - Gestionar departamento del funcionario.
| - Gestionar permiso de administración de la página pública.
|--------------------------------------------------------------------------
*/


/* =========================================================
   API
   ========================================================= */

const API_URL =
    "../api/gestionar_funcionarios.php";


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

let funcionarioDepartamento;

let funcionarioDepartamentoHidden;

let funcionarioPassword;

let funcionarioActivo;

let funcionarioPuedeAdministrarWeb;

let notificacionFuncionario;


/* =========================================================
   ESTADO DEL FORMULARIO
   ========================================================= */

let modoFormulario =
    "crear";


/* =========================================================
   INICIALIZAR
   ========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function () {

        inicializarElementos();

        inicializarEventos();

        actualizarEstadoDepartamento();

    }
);


/* =========================================================
   OBTENER ELEMENTOS
   ========================================================= */

function inicializarElementos() {

    modalFuncionario =
        document.getElementById(
            "modalFuncionario"
        );

    btnNuevoFuncionario =
        document.getElementById(
            "btnNuevoFuncionario"
        );

    btnCerrarModalFuncionario =
        document.getElementById(
            "btnCerrarModalFuncionario"
        );

    btnCancelarFuncionario =
        document.getElementById(
            "btnCancelarFuncionario"
        );

    formFuncionario =
        document.getElementById(
            "formFuncionario"
        );

    modalFuncionarioTitulo =
        document.getElementById(
            "modalFuncionarioTitulo"
        );

    funcionarioId =
        document.getElementById(
            "funcionarioId"
        );

    funcionarioNombre =
        document.getElementById(
            "funcionarioNombre"
        );

    funcionarioApellido =
        document.getElementById(
            "funcionarioApellido"
        );


    /*
     * Obtener el correo directamente desde el formulario.
     *
     * Se utiliza primero el ID esperado y luego
     * como respaldo el atributo name="correo".
     *
     * Esto evita que una diferencia en el DOM
     * impida abrir el formulario completo.
     */

    funcionarioCorreo =
        document.getElementById(
            "funcionarioCorreo"
        );


    if (
        !funcionarioCorreo &&
        formFuncionario
    ) {

        funcionarioCorreo =
            formFuncionario.querySelector(
                '[name="correo"]'
            );
    }


    funcionarioUsuario =
        document.getElementById(
            "funcionarioUsuario"
        );

    funcionarioRol =
        document.getElementById(
            "funcionarioRol"
        );


    /*
     * Departamento al que pertenece el funcionario.
     *
     * El elemento corresponde al selector:
     *
     * id="funcionarioDepartamento"
     */

    funcionarioDepartamento =
        document.getElementById(
            "funcionarioDepartamento"
        );


    /*
     * Para un administrador de departamento,
     * este campo oculto contiene el departamento
     * al que pertenece el administrador actual.
     */

    funcionarioDepartamentoHidden =
        document.getElementById(
            "funcionarioDepartamentoHidden"
        );


    funcionarioPassword =
        document.getElementById(
            "funcionarioPassword"
        );

    funcionarioActivo =
        document.getElementById(
            "funcionarioActivo"
        );


    /*
     * Permiso para administrar la página pública.
     *
     * Este elemento solamente existe cuando
     * el usuario conectado es superadmin.
     */

    funcionarioPuedeAdministrarWeb =
        document.getElementById(
            "funcionarioPuedeAdministrarWeb"
        );


    notificacionFuncionario =
        document.getElementById(
            "notificacionFuncionario"
        );
}


/* =========================================================
   EVENTOS
   ========================================================= */

function inicializarEventos() {

    if (
        btnNuevoFuncionario
    ) {

        btnNuevoFuncionario.addEventListener(
            "click",
            abrirModalCrear
        );
    }


    if (
        btnCerrarModalFuncionario
    ) {

        btnCerrarModalFuncionario.addEventListener(
            "click",
            cerrarModal
        );
    }


    if (
        btnCancelarFuncionario
    ) {

        btnCancelarFuncionario.addEventListener(
            "click",
            cerrarModal
        );
    }


    if (
        modalFuncionario
    ) {

        modalFuncionario.addEventListener(
            "click",
            function (evento) {

                if (
                    evento.target ===
                    modalFuncionario
                ) {

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


    if (
        formFuncionario
    ) {

        formFuncionario.addEventListener(
            "submit",
            guardarFuncionario
        );
    }


    /*
     * Actualizar el departamento cuando
     * cambia el rol del funcionario.
     */

    if (
        funcionarioRol
    ) {

        funcionarioRol.addEventListener(
            "change",
            actualizarEstadoDepartamento
        );
    }


    document
        .querySelectorAll(
            "[data-action]"
        )
        .forEach(
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

    const boton =
        evento.currentTarget;

    const accion =
        boton.dataset.action;

    const id =
        boton.dataset.id;


    if (
        !id
    ) {

        return;
    }


    if (
        accion === "editar"
    ) {

        abrirModalEditar(id);

        return;
    }


    if (
        accion === "estado"
    ) {

        cambiarEstadoFuncionario(
            id,
            boton.dataset.estado
        );

        return;
    }


    if (
        accion === "eliminar"
    ) {

        eliminarFuncionario(id);
    }
}


/* =========================================================
   ACTUALIZAR ESTADO DEL DEPARTAMENTO
   ========================================================= */

function actualizarEstadoDepartamento() {

    if (
        !funcionarioDepartamento ||
        !funcionarioRol
    ) {

        return;
    }


    const rol =
        funcionarioRol.value;


    /*
     * El superadmin no pertenece a un departamento.
     */

    if (
        rol === "superadmin"
    ) {

        funcionarioDepartamento.value =
            "";

        funcionarioDepartamento.required =
            false;

        funcionarioDepartamento.disabled =
            true;


        return;
    }


    /*
     * Si quien está conectado es administrador,
     * solamente puede utilizar su propio departamento.
     *
     * El valor se obtiene desde el campo oculto
     * generado por PHP.
     */

    if (
        funcionarioDepartamentoHidden
    ) {

        const departamentoAdministrador =
            funcionarioDepartamentoHidden.value;


        if (
            departamentoAdministrador !== ""
        ) {

            funcionarioDepartamento.value =
                departamentoAdministrador;
        }


        funcionarioDepartamento.disabled =
            true;

        funcionarioDepartamento.required =
            true;


        return;
    }


    /*
     * Superadmin creando un funcionario o administrador.
     *
     * Funcionarios y administradores deben
     * tener un departamento.
     */

    funcionarioDepartamento.disabled =
        false;

    funcionarioDepartamento.required =
        true;
}


/* =========================================================
   ABRIR MODAL CREAR
   ========================================================= */

function abrirModalCrear() {

    modoFormulario =
        "crear";


    limpiarFormulario();


    if (
        modalFuncionarioTitulo
    ) {

        modalFuncionarioTitulo.textContent =
            "Nuevo funcionario";
    }


    if (
        funcionarioPassword
    ) {

        funcionarioPassword.required =
            true;
    }


    if (
        funcionarioActivo
    ) {

        funcionarioActivo.checked =
            true;
    }


    /*
     * Al crear un funcionario, el permiso web
     * comienza desactivado.
     *
     * El superadmin podrá activarlo manualmente.
     */

    if (
        funcionarioPuedeAdministrarWeb
    ) {

        funcionarioPuedeAdministrarWeb.checked =
            false;
    }


    /*
     * Si el usuario actual es administrador,
     * el departamento debe quedar automáticamente
     * en su departamento y bloqueado.
     *
     * Si es superadmin, se deja sin selección.
     */

    if (
        funcionarioDepartamento
    ) {

        if (
            funcionarioDepartamentoHidden &&
            funcionarioDepartamentoHidden.value !== ""
        ) {

            funcionarioDepartamento.value =
                funcionarioDepartamentoHidden.value;

        } else {

            funcionarioDepartamento.value =
                "";
        }
    }


    actualizarEstadoDepartamento();


    limpiarErrores();

    ocultarNotificacion();


    if (
        modalFuncionario
    ) {

        modalFuncionario.hidden =
            false;
    }


    setTimeout(
        function () {

            if (
                funcionarioNombre
            ) {

                funcionarioNombre.focus();
            }

        },
        50
    );
}


/* =========================================================
   ABRIR MODAL EDITAR
   ========================================================= */

async function abrirModalEditar(
    id
) {

    modoFormulario =
        "editar";


    limpiarFormulario();


    if (
        modalFuncionarioTitulo
    ) {

        modalFuncionarioTitulo.textContent =
            "Editar funcionario";
    }


    if (
        funcionarioPassword
    ) {

        funcionarioPassword.required =
            false;
    }


    if (
        modalFuncionario
    ) {

        modalFuncionario.hidden =
            false;
    }


    mostrarNotificacion(
        "Cargando información del funcionario...",
        "info"
    );


    try {

        const respuesta =
            await fetch(
                API_URL +
                "?accion=obtener&id=" +
                encodeURIComponent(id),
                {
                    method: "GET",
                    credentials: "same-origin"
                }
            );


        const datos =
            await obtenerRespuestaJSON(
                respuesta
            );


        cargarDatosFuncionario(
            datos.funcionario
        );


        ocultarNotificacion();


        setTimeout(
            function () {

                if (
                    funcionarioNombre
                ) {

                    funcionarioNombre.focus();
                }

            },
            50
        );

    } catch (
        error
    ) {

        mostrarNotificacion(
            error.message,
            "error"
        );
    }
}


/* =========================================================
   CARGAR DATOS
   ========================================================= */

function cargarDatosFuncionario(
    funcionario
) {

    if (
        funcionarioId
    ) {

        funcionarioId.value =
            funcionario.id_funcionario
            ?? "";
    }


    if (
        funcionarioNombre
    ) {

        funcionarioNombre.value =
            funcionario.nombre
            ?? "";
    }


    if (
        funcionarioApellido
    ) {

        funcionarioApellido.value =
            funcionario.apellido
            ?? "";
    }


    if (
        funcionarioCorreo
    ) {

        funcionarioCorreo.value =
            funcionario.correo
            ?? "";
    }


    if (
        funcionarioUsuario
    ) {

        funcionarioUsuario.value =
            funcionario.usuario
            ?? "";
    }


    if (
        funcionarioRol
    ) {

        funcionarioRol.value =
            funcionario.rol
            ?? "funcionario";
    }


    /*
     * Cargar el departamento asociado
     * al funcionario.
     *
     * Para un administrador de departamento
     * se ignora cualquier otro departamento
     * devuelto por el servidor y se fuerza
     * el departamento propio.
     */

    if (
        funcionarioDepartamento
    ) {

        if (
            funcionarioDepartamentoHidden &&
            funcionarioDepartamentoHidden.value !== ""
        ) {

            funcionarioDepartamento.value =
                funcionarioDepartamentoHidden.value;

        } else {

            funcionarioDepartamento.value =
                funcionario.id_departamento
                ?? "";
        }
    }


    /*
     * Actualizar el estado del selector
     * según el rol cargado.
     */

    actualizarEstadoDepartamento();


    if (
        funcionarioPassword
    ) {

        funcionarioPassword.value =
            "";
    }


    if (
        funcionarioActivo
    ) {

        funcionarioActivo.checked =
            convertirBooleano(
                funcionario.activo
            );
    }


    /*
     * Cargar el permiso de administración web
     * devuelto por api/gestionar_funcionarios.php.
     *
     * Solo existe esta casilla para superadmin.
     */

    if (
        funcionarioPuedeAdministrarWeb
    ) {

        funcionarioPuedeAdministrarWeb.checked =
            convertirBooleano(
                funcionario.puede_administrar_web
            );
    }
}


/* =========================================================
   GUARDAR FUNCIONARIO
   ========================================================= */

async function guardarFuncionario(
    evento
) {

    evento.preventDefault();


    limpiarErrores();

    ocultarNotificacion();


    if (
        !validarFormulario()
    ) {

        return;
    }


    const botonGuardar =
        formFuncionario
            ? formFuncionario.querySelector(
                'button[type="submit"]'
            )
            : null;


    if (
        botonGuardar
    ) {

        botonGuardar.disabled =
            true;

        botonGuardar.textContent =
            "Guardando...";
    }


    try {

        const datos =
            new FormData(
                formFuncionario
            );


        datos.set(
            "accion",
            modoFormulario
        );


        datos.set(
            "activo",
            funcionarioActivo &&
            funcionarioActivo.checked
                ? "1"
                : "0"
        );


        /*
         * Enviar siempre el estado del permiso web
         * cuando la casilla está disponible.
         *
         * Las casillas HTML desmarcadas normalmente
         * no se incluyen en FormData.
         *
         * Por eso enviamos explícitamente "1" o "0".
         * Esto permite tanto conceder como retirar
         * el permiso de administración web.
         *
         * Cuando el usuario conectado no es superadmin,
         * la casilla no existe y no enviamos el campo.
         * El backend conserva así la restricción
         * de que solo superadmin pueda modificarlo.
         */

        if (
            funcionarioPuedeAdministrarWeb
        ) {

            datos.set(
                "puede_administrar_web",
                funcionarioPuedeAdministrarWeb.checked
                    ? "1"
                    : "0"
            );
        }


        /*
         * Asegurar que el departamento
         * viaje explícitamente al servidor.
         *
         * Si el usuario actual es administrador,
         * se fuerza su propio departamento.
         *
         * Si el rol es superadmin,
         * se envía vacío porque no corresponde
         * asociarlo a un departamento.
         */

        if (
            funcionarioDepartamento
        ) {

            if (
                funcionarioRol &&
                funcionarioRol.value ===
                "superadmin"
            ) {

                datos.set(
                    "id_departamento",
                    ""
                );

            } else if (
                funcionarioDepartamentoHidden &&
                funcionarioDepartamentoHidden.value !== ""
            ) {

                datos.set(
                    "id_departamento",
                    funcionarioDepartamentoHidden.value
                );

            } else {

                datos.set(
                    "id_departamento",
                    funcionarioDepartamento.value
                );
            }
        }


        const respuesta =
            await fetch(
                API_URL,
                {
                    method: "POST",
                    body: datos,
                    credentials: "same-origin"
                }
            );


        const resultado =
            await obtenerRespuestaJSON(
                respuesta
            );


        mostrarNotificacion(
            resultado.mensaje ||
            "Funcionario guardado correctamente.",
            "success"
        );


        setTimeout(
            function () {

                window.location.reload();

            },
            900
        );

    } catch (
        error
    ) {

        mostrarNotificacion(
            error.message,
            "error"
        );

    } finally {

        if (
            botonGuardar
        ) {

            botonGuardar.disabled =
                false;

            botonGuardar.textContent =
                "Guardar funcionario";
        }
    }
}


/* =========================================================
   CAMBIAR ESTADO
   ========================================================= */

async function cambiarEstadoFuncionario(
    id,
    estadoActual
) {

    const activar =
        estadoActual !== "activo";


    const mensaje =
        activar
            ? "¿Deseas activar este funcionario?"
            : "¿Deseas desactivar este funcionario?";


    if (
        !window.confirm(
            mensaje
        )
    ) {

        return;
    }


    try {

        const datos =
            new FormData();


        datos.set(
            "accion",
            activar
                ? "activar"
                : "desactivar"
        );


        datos.set(
            "id_funcionario",
            id
        );


        const respuesta =
            await fetch(
                API_URL,
                {
                    method: "POST",
                    body: datos,
                    credentials: "same-origin"
                }
            );


        const resultado =
            await obtenerRespuestaJSON(
                respuesta
            );


        mostrarNotificacionGlobal(
            resultado.mensaje ||
            "Estado actualizado correctamente."
        );


        window.location.reload();

    } catch (
        error
    ) {

        mostrarNotificacionGlobal(
            error.message
        );
    }
}


/* =========================================================
   ELIMINAR FUNCIONARIO
   ========================================================= */

async function eliminarFuncionario(
    id
) {

    if (
        !window.confirm(
            "¿Estás seguro de que deseas eliminar este funcionario? Esta acción no se puede deshacer."
        )
    ) {

        return;
    }


    try {

        const datos =
            new FormData();


        datos.set(
            "accion",
            "eliminar"
        );


        datos.set(
            "id_funcionario",
            id
        );


        const respuesta =
            await fetch(
                API_URL,
                {
                    method: "POST",
                    body: datos,
                    credentials: "same-origin"
                }
            );


        const resultado =
            await obtenerRespuestaJSON(
                respuesta
            );


        mostrarNotificacionGlobal(
            resultado.mensaje ||
            "Funcionario eliminado correctamente."
        );


        window.location.reload();

    } catch (
        error
    ) {

        mostrarNotificacionGlobal(
            error.message
        );
    }
}


/* =========================================================
   VALIDAR FORMULARIO
   ========================================================= */

function validarFormulario() {

    let valido =
        true;


    const nombre =
        funcionarioNombre
            ? funcionarioNombre.value.trim()
            : "";


    const apellido =
        funcionarioApellido
            ? funcionarioApellido.value.trim()
            : "";


    const correo =
        funcionarioCorreo
            ? funcionarioCorreo.value.trim()
            : "";


    const usuario =
        funcionarioUsuario
            ? funcionarioUsuario.value.trim()
            : "";


    const departamento =
        funcionarioDepartamento
            ? funcionarioDepartamento.value
            : "";


    const password =
        funcionarioPassword
            ? funcionarioPassword.value
            : "";


    if (
        nombre === ""
    ) {

        mostrarError(
            "errorFuncionarioNombre",
            "Ingresa el nombre."
        );

        valido =
            false;
    }


    if (
        apellido === ""
    ) {

        mostrarError(
            "errorFuncionarioApellido",
            "Ingresa el apellido."
        );

        valido =
            false;
    }


    if (
        correo === ""
    ) {

        mostrarError(
            "errorFuncionarioCorreo",
            "Ingresa el correo electrónico."
        );

        valido =
            false;

    } else if (
        !validarCorreo(
            correo
        )
    ) {

        mostrarError(
            "errorFuncionarioCorreo",
            "Ingresa un correo electrónico válido."
        );

        valido =
            false;
    }


    if (
        usuario === ""
    ) {

        mostrarError(
            "errorFuncionarioUsuario",
            "Ingresa el usuario."
        );

        valido =
            false;
    }


    /*
     * Un funcionario y un administrador
     * deben pertenecer a un departamento.
     *
     * El superadmin podrá trabajar sin departamento.
     */

    if (
        funcionarioRol &&
        funcionarioRol.value !== "superadmin" &&
        departamento === ""
    ) {

        mostrarError(
            "errorFuncionarioDepartamento",
            "Selecciona un departamento."
        );

        valido =
            false;
    }


    if (
        modoFormulario === "crear" &&
        password.length < 6
    ) {

        mostrarError(
            "errorFuncionarioPassword",
            "La contraseña debe tener al menos 6 caracteres."
        );

        valido =
            false;

    } else if (
        modoFormulario === "editar" &&
        password !== "" &&
        password.length < 6
    ) {

        mostrarError(
            "errorFuncionarioPassword",
            "La contraseña debe tener al menos 6 caracteres."
        );

        valido =
            false;
    }


    return valido;
}


/* =========================================================
   VALIDAR CORREO
   ========================================================= */

function validarCorreo(
    correo
) {

    const expresion =
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/;


    return expresion.test(
        correo
    );
}


/* =========================================================
   MOSTRAR ERROR
   ========================================================= */

function mostrarError(
    id,
    mensaje
) {

    const elemento =
        document.getElementById(
            id
        );


    if (
        elemento
    ) {

        elemento.textContent =
            mensaje;
    }
}


/* =========================================================
   LIMPIAR ERRORES
   ========================================================= */

function limpiarErrores() {

    document
        .querySelectorAll(
            ".form-error"
        )
        .forEach(
            function (elemento) {

                elemento.textContent =
                    "";

            }
        );
}


/* =========================================================
   LIMPIAR FORMULARIO
   ========================================================= */

function limpiarFormulario() {

    if (
        formFuncionario
    ) {

        formFuncionario.reset();
    }


    if (
        funcionarioId
    ) {

        funcionarioId.value =
            "";
    }


    if (
        funcionarioNombre
    ) {

        funcionarioNombre.value =
            "";
    }


    if (
        funcionarioApellido
    ) {

        funcionarioApellido.value =
            "";
    }


    if (
        funcionarioCorreo
    ) {

        funcionarioCorreo.value =
            "";
    }


    if (
        funcionarioUsuario
    ) {

        funcionarioUsuario.value =
            "";
    }


    if (
        funcionarioRol
    ) {

        funcionarioRol.value =
            "funcionario";
    }


    if (
        funcionarioDepartamento
    ) {

        /*
         * Si existe un departamento oculto,
         * corresponde al departamento del administrador
         * actual y debe conservarse.
         */

        if (
            funcionarioDepartamentoHidden &&
            funcionarioDepartamentoHidden.value !== ""
        ) {

            funcionarioDepartamento.value =
                funcionarioDepartamentoHidden.value;

        } else {

            funcionarioDepartamento.value =
                "";
        }
    }


    if (
        funcionarioPassword
    ) {

        funcionarioPassword.value =
            "";
    }


    if (
        funcionarioActivo
    ) {

        funcionarioActivo.checked =
            true;
    }


    /*
     * Dejar desactivado el permiso web al limpiar
     * el formulario para crear un funcionario nuevo.
     */

    if (
        funcionarioPuedeAdministrarWeb
    ) {

        funcionarioPuedeAdministrarWeb.checked =
            false;
    }


    /*
     * El rol inicial es funcionario,
     * por lo tanto el departamento debe
     * quedar habilitado para superadmin
     * o bloqueado para administrador.
     */

    actualizarEstadoDepartamento();
}


/* =========================================================
   CERRAR MODAL
   ========================================================= */

function cerrarModal() {

    if (
        modalFuncionario
    ) {

        modalFuncionario.hidden =
            true;
    }


    limpiarErrores();

    ocultarNotificacion();
}


/* =========================================================
   MOSTRAR NOTIFICACIÓN
   ========================================================= */

function mostrarNotificacion(
    mensaje,
    tipo = "info"
) {

    if (
        !notificacionFuncionario
    ) {

        return;
    }


    notificacionFuncionario.textContent =
        mensaje;


    notificacionFuncionario.className =
        "form-notification " +
        tipo;


    notificacionFuncionario.hidden =
        false;
}


/* =========================================================
   OCULTAR NOTIFICACIÓN
   ========================================================= */

function ocultarNotificacion() {

    if (
        !notificacionFuncionario
    ) {

        return;
    }


    notificacionFuncionario.hidden =
        true;


    notificacionFuncionario.textContent =
        "";
}


/* =========================================================
   OBTENER RESPUESTA JSON
   ========================================================= */

async function obtenerRespuestaJSON(
    respuesta
) {

    let datos;


    try {

        datos =
            await respuesta.json();

    } catch (
        error
    ) {

        throw new Error(
            "El servidor devolvió una respuesta inválida."
        );
    }


    if (
        !respuesta.ok ||
        datos.ok === false
    ) {

        throw new Error(
            datos.error ||
            datos.mensaje ||
            "No fue posible completar la operación."
        );
    }


    return datos;
}


/* =========================================================
   CONVERTIR BOOLEANO
   ========================================================= */

function convertirBooleano(
    valor
) {

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

function mostrarNotificacionGlobal(
    mensaje
) {

    alert(
        mensaje
    );
}