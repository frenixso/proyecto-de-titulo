document.addEventListener("DOMContentLoaded", function () {

    const API_URL = "../api/gestionar_estructura.php";


    // =========================================================
    // ELEMENTOS DEPARTAMENTO
    // =========================================================

    const btnAgregarDepartamento =
        document.getElementById("btnAgregarDepartamento");

    const modalDepartamento =
        document.getElementById("modalDepartamento");

    const btnCerrarModalDepartamento =
        document.getElementById("btnCerrarModalDepartamento");

    const btnCancelarDepartamento =
        document.getElementById("btnCancelarDepartamento");

    const formDepartamento =
        document.getElementById("formDepartamento");

    const departamentoId =
        document.getElementById("departamentoId");

    const departamentoNombre =
        document.getElementById("departamentoNombre");

    const mensajeDepartamento =
        document.getElementById("mensajeDepartamento");

    const tituloModalDepartamento =
        document.getElementById("tituloModalDepartamento");

    const btnGuardarDepartamento =
        document.getElementById("btnGuardarDepartamento");


    // =========================================================
    // ELEMENTOS SERVICIO
    // =========================================================

    const btnAgregarServicio =
        document.getElementById("btnAgregarServicio");

    const modalServicio =
        document.getElementById("modalServicio");

    const btnCerrarModalServicio =
        document.getElementById("btnCerrarModalServicio");

    const btnCancelarServicio =
        document.getElementById("btnCancelarServicio");

    const formServicio =
        document.getElementById("formServicio");

    const servicioId =
        document.getElementById("servicioId");

    const servicioDepartamento =
        document.getElementById("servicioDepartamento");

    const servicioNombre =
        document.getElementById("servicioNombre");

    const servicioDescripcion =
        document.getElementById("servicioDescripcion");

    const mensajeServicio =
        document.getElementById("mensajeServicio");

    const tituloModalServicio =
        document.getElementById("tituloModalServicio");

    const btnGuardarServicio =
        document.getElementById("btnGuardarServicio");


    // =========================================================
    // FILTRO
    // =========================================================

    const filtroDepartamento =
        document.getElementById("filtroDepartamento");

    const tablaServicios =
        document.getElementById("tablaServicios");


    // =========================================================
    // MENSAJES
    // =========================================================

    function mostrarMensajeDepartamento(
        mensaje,
        tipo = "error"
    ) {

        if (!mensajeDepartamento) {
            return;
        }

        mensajeDepartamento.textContent =
            mensaje;

        mensajeDepartamento.className =
            "form-mensaje " + tipo;

        mensajeDepartamento.hidden =
            false;
    }


    function ocultarMensajeDepartamento() {

        if (!mensajeDepartamento) {
            return;
        }

        mensajeDepartamento.textContent =
            "";

        mensajeDepartamento.className =
            "form-mensaje";

        mensajeDepartamento.hidden =
            true;
    }


    function mostrarMensajeServicio(
        mensaje,
        tipo = "error"
    ) {

        if (!mensajeServicio) {
            return;
        }

        mensajeServicio.textContent =
            mensaje;

        mensajeServicio.className =
            "form-mensaje " + tipo;

        mensajeServicio.hidden =
            false;
    }


    function ocultarMensajeServicio() {

        if (!mensajeServicio) {
            return;
        }

        mensajeServicio.textContent =
            "";

        mensajeServicio.className =
            "form-mensaje";

        mensajeServicio.hidden =
            true;
    }


    // =========================================================
    // ABRIR MODAL DEPARTAMENTO
    // =========================================================

    function abrirModalDepartamento() {

        if (!modalDepartamento) {
            return;
        }

        modalDepartamento.hidden =
            false;

        document.body.classList.add(
            "modal-abierto"
        );

        setTimeout(function () {

            if (departamentoNombre) {
                departamentoNombre.focus();
            }

        }, 100);
    }


    // =========================================================
    // CERRAR MODAL DEPARTAMENTO
    // =========================================================

    function cerrarModalDepartamento() {

        if (!modalDepartamento) {
            return;
        }

        modalDepartamento.hidden =
            true;

        document.body.classList.remove(
            "modal-abierto"
        );

        if (formDepartamento) {
            formDepartamento.reset();
        }

        if (departamentoId) {
            departamentoId.value = "";
        }

        if (tituloModalDepartamento) {
            tituloModalDepartamento.textContent =
                "Agregar departamento";
        }

        if (btnGuardarDepartamento) {
            btnGuardarDepartamento.textContent =
                "Guardar departamento";
        }

        ocultarMensajeDepartamento();
    }


    // =========================================================
    // ABRIR MODAL SERVICIO
    // =========================================================

    function abrirModalServicio() {

        if (!modalServicio) {
            return;
        }

        modalServicio.hidden =
            false;

        document.body.classList.add(
            "modal-abierto"
        );

        setTimeout(function () {

            if (servicioDepartamento) {
                servicioDepartamento.focus();
            }

        }, 100);
    }


    // =========================================================
    // CERRAR MODAL SERVICIO
    // =========================================================

    function cerrarModalServicio() {

        if (!modalServicio) {
            return;
        }

        modalServicio.hidden =
            true;

        document.body.classList.remove(
            "modal-abierto"
        );

        if (formServicio) {
            formServicio.reset();
        }

        if (servicioId) {
            servicioId.value = "";
        }

        if (tituloModalServicio) {
            tituloModalServicio.textContent =
                "Agregar servicio";
        }

        if (btnGuardarServicio) {
            btnGuardarServicio.textContent =
                "Guardar servicio";
        }

        ocultarMensajeServicio();
    }


    // =========================================================
    // NUEVO DEPARTAMENTO
    // =========================================================

    if (btnAgregarDepartamento) {

        btnAgregarDepartamento.addEventListener(
            "click",
            function () {

                if (formDepartamento) {
                    formDepartamento.reset();
                }

                if (departamentoId) {
                    departamentoId.value = "";
                }

                if (tituloModalDepartamento) {
                    tituloModalDepartamento.textContent =
                        "Agregar departamento";
                }

                if (btnGuardarDepartamento) {
                    btnGuardarDepartamento.textContent =
                        "Guardar departamento";
                }

                ocultarMensajeDepartamento();

                abrirModalDepartamento();
            }
        );
    }


    // =========================================================
    // NUEVO SERVICIO
    // =========================================================

    if (btnAgregarServicio) {

        btnAgregarServicio.addEventListener(
            "click",
            function () {

                if (formServicio) {
                    formServicio.reset();
                }

                if (servicioId) {
                    servicioId.value = "";
                }

                if (tituloModalServicio) {
                    tituloModalServicio.textContent =
                        "Agregar servicio";
                }

                if (btnGuardarServicio) {
                    btnGuardarServicio.textContent =
                        "Guardar servicio";
                }

                ocultarMensajeServicio();

                abrirModalServicio();
            }
        );
    }


    // =========================================================
    // CERRAR MODAL DEPARTAMENTO
    // =========================================================

    if (btnCerrarModalDepartamento) {

        btnCerrarModalDepartamento.addEventListener(
            "click",
            cerrarModalDepartamento
        );
    }


    if (btnCancelarDepartamento) {

        btnCancelarDepartamento.addEventListener(
            "click",
            cerrarModalDepartamento
        );
    }


    if (modalDepartamento) {

        const overlay =
            modalDepartamento.querySelector(
                ".estructura-modal-overlay"
            );

        if (overlay) {

            overlay.addEventListener(
                "click",
                cerrarModalDepartamento
            );
        }
    }


    // =========================================================
    // CERRAR MODAL SERVICIO
    // =========================================================

    if (btnCerrarModalServicio) {

        btnCerrarModalServicio.addEventListener(
            "click",
            cerrarModalServicio
        );
    }


    if (btnCancelarServicio) {

        btnCancelarServicio.addEventListener(
            "click",
            cerrarModalServicio
        );
    }


    if (modalServicio) {

        const overlay =
            modalServicio.querySelector(
                ".estructura-modal-overlay"
            );

        if (overlay) {

            overlay.addEventListener(
                "click",
                cerrarModalServicio
            );
        }
    }


    // =========================================================
    // ESC PARA CERRAR MODALES
    // =========================================================

    document.addEventListener(
        "keydown",
        function (event) {

            if (event.key !== "Escape") {
                return;
            }

            if (
                modalDepartamento &&
                !modalDepartamento.hidden
            ) {

                cerrarModalDepartamento();

                return;
            }

            if (
                modalServicio &&
                !modalServicio.hidden
            ) {

                cerrarModalServicio();
            }
        }
    );


    // =========================================================
    // EDITAR DEPARTAMENTO
    // =========================================================

    document
        .querySelectorAll(
            ".btn-editar-departamento"
        )
        .forEach(function (boton) {

            boton.addEventListener(
                "click",
                function () {

                    const id =
                        this.dataset.id || "";

                    const nombre =
                        this.dataset.nombre || "";


                    if (formDepartamento) {
                        formDepartamento.reset();
                    }

                    if (departamentoId) {
                        departamentoId.value =
                            id;
                    }

                    if (departamentoNombre) {
                        departamentoNombre.value =
                            nombre;
                    }

                    if (tituloModalDepartamento) {
                        tituloModalDepartamento.textContent =
                            "Editar departamento";
                    }

                    if (btnGuardarDepartamento) {
                        btnGuardarDepartamento.textContent =
                            "Guardar cambios";
                    }

                    ocultarMensajeDepartamento();

                    abrirModalDepartamento();


                    setTimeout(function () {

                        if (departamentoNombre) {

                            departamentoNombre.focus();

                            departamentoNombre.select();
                        }

                    }, 100);
                }
            );
        });


    // =========================================================
    // EDITAR SERVICIO
    // =========================================================

    document
        .querySelectorAll(
            ".btn-editar-servicio"
        )
        .forEach(function (boton) {

            boton.addEventListener(
                "click",
                function () {

                    const id =
                        this.dataset.id || "";

                    const idDepartamento =
                        this.dataset.idDepartamento || "";

                    const nombre =
                        this.dataset.nombre || "";

                    const descripcion =
                        this.dataset.descripcion || "";


                    if (formServicio) {
                        formServicio.reset();
                    }

                    if (servicioId) {
                        servicioId.value =
                            id;
                    }

                    if (servicioDepartamento) {
                        servicioDepartamento.value =
                            idDepartamento;
                    }

                    if (servicioNombre) {
                        servicioNombre.value =
                            nombre;
                    }

                    if (servicioDescripcion) {
                        servicioDescripcion.value =
                            descripcion;
                    }

                    if (tituloModalServicio) {
                        tituloModalServicio.textContent =
                            "Editar servicio";
                    }

                    if (btnGuardarServicio) {
                        btnGuardarServicio.textContent =
                            "Guardar cambios";
                    }

                    ocultarMensajeServicio();

                    abrirModalServicio();


                    setTimeout(function () {

                        if (servicioNombre) {

                            servicioNombre.focus();

                            servicioNombre.select();
                        }

                    }, 100);
                }
            );
        });


    // =========================================================
    // ENVIAR SOLICITUD AL API
    // =========================================================

    async function enviarSolicitud(datos) {

        const respuesta =
            await fetch(
                API_URL,
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/json"
                    },

                    body:
                        JSON.stringify(datos)
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
                "Respuesta no válida del servidor:",
                texto
            );

            throw new Error(
                "El servidor devolvió una respuesta no válida."
            );
        }


        if (
            !respuesta.ok ||
            !resultado.ok
        ) {

            throw new Error(
                resultado.mensaje ||
                "No fue posible realizar la operación."
            );
        }


        return resultado;
    }


    // =========================================================
    // CREAR / EDITAR DEPARTAMENTO
    // =========================================================

    if (formDepartamento) {

        formDepartamento.addEventListener(
            "submit",
            async function (event) {

                event.preventDefault();

                ocultarMensajeDepartamento();


                const nombre =
                    departamentoNombre
                        ? departamentoNombre.value.trim()
                        : "";


                const id =
                    departamentoId
                        ? departamentoId.value.trim()
                        : "";


                // -------------------------------------------------
                // VALIDACIONES
                // -------------------------------------------------

                if (!nombre) {

                    mostrarMensajeDepartamento(
                        "Debes ingresar el nombre del departamento."
                    );

                    if (departamentoNombre) {
                        departamentoNombre.focus();
                    }

                    return;
                }


                if (nombre.length < 2) {

                    mostrarMensajeDepartamento(
                        "El nombre debe tener al menos 2 caracteres."
                    );

                    if (departamentoNombre) {
                        departamentoNombre.focus();
                    }

                    return;
                }


                if (nombre.length > 150) {

                    mostrarMensajeDepartamento(
                        "El nombre no puede superar los 150 caracteres."
                    );

                    if (departamentoNombre) {
                        departamentoNombre.focus();
                    }

                    return;
                }


                // -------------------------------------------------
                // DATOS
                // -------------------------------------------------

                const datos = {

                    accion:
                        id
                            ? "editar"
                            : "crear",

                    tipo:
                        "departamento",

                    nombre:
                        nombre
                };


                if (id) {

                    datos.id_departamento =
                        parseInt(
                            id,
                            10
                        );
                }


                // -------------------------------------------------
                // BOTÓN
                // -------------------------------------------------

                const textoOriginal =
                    btnGuardarDepartamento
                        ? btnGuardarDepartamento.textContent
                        : "Guardar departamento";


                try {

                    if (btnGuardarDepartamento) {

                        btnGuardarDepartamento.disabled =
                            true;

                        btnGuardarDepartamento.textContent =
                            "Guardando...";
                    }


                    const resultado =
                        await enviarSolicitud(
                            datos
                        );


                    alert(
                        resultado.mensaje ||
                        "Departamento guardado correctamente."
                    );


                    cerrarModalDepartamento();

                    window.location.reload();


                } catch (error) {

                    console.error(
                        "Error guardando departamento:",
                        error
                    );


                    mostrarMensajeDepartamento(
                        error.message ||
                        "No fue posible guardar el departamento."
                    );


                } finally {

                    if (btnGuardarDepartamento) {

                        btnGuardarDepartamento.disabled =
                            false;

                        btnGuardarDepartamento.textContent =
                            textoOriginal;
                    }
                }
            }
        );
    }


    // =========================================================
    // CREAR / EDITAR SERVICIO
    // =========================================================

    if (formServicio) {

        formServicio.addEventListener(
            "submit",
            async function (event) {

                event.preventDefault();

                ocultarMensajeServicio();


                const id =
                    servicioId
                        ? servicioId.value.trim()
                        : "";


                const idDepartamento =
                    servicioDepartamento
                        ? servicioDepartamento.value
                        : "";


                const nombre =
                    servicioNombre
                        ? servicioNombre.value.trim()
                        : "";


                const descripcion =
                    servicioDescripcion
                        ? servicioDescripcion.value.trim()
                        : "";


                // -------------------------------------------------
                // VALIDAR DEPARTAMENTO
                // -------------------------------------------------

                if (!idDepartamento) {

                    mostrarMensajeServicio(
                        "Debes seleccionar un departamento."
                    );

                    if (servicioDepartamento) {
                        servicioDepartamento.focus();
                    }

                    return;
                }


                // -------------------------------------------------
                // VALIDAR NOMBRE
                // -------------------------------------------------

                if (!nombre) {

                    mostrarMensajeServicio(
                        "Debes ingresar el nombre del servicio."
                    );

                    if (servicioNombre) {
                        servicioNombre.focus();
                    }

                    return;
                }


                if (nombre.length < 2) {

                    mostrarMensajeServicio(
                        "El nombre debe tener al menos 2 caracteres."
                    );

                    if (servicioNombre) {
                        servicioNombre.focus();
                    }

                    return;
                }


                if (nombre.length > 150) {

                    mostrarMensajeServicio(
                        "El nombre no puede superar los 150 caracteres."
                    );

                    if (servicioNombre) {
                        servicioNombre.focus();
                    }

                    return;
                }


                if (descripcion.length > 500) {

                    mostrarMensajeServicio(
                        "La descripción no puede superar los 500 caracteres."
                    );

                    if (servicioDescripcion) {
                        servicioDescripcion.focus();
                    }

                    return;
                }


                // -------------------------------------------------
                // DATOS
                // -------------------------------------------------

                const datos = {

                    accion:
                        id
                            ? "editar"
                            : "crear",

                    tipo:
                        "servicio",

                    nombre:
                        nombre,

                    id_departamento:
                        parseInt(
                            idDepartamento,
                            10
                        ),

                    descripcion:
                        descripcion
                };


                if (id) {

                    datos.id_servicio =
                        parseInt(
                            id,
                            10
                        );
                }


                // -------------------------------------------------
                // BOTÓN
                // -------------------------------------------------

                const textoOriginal =
                    btnGuardarServicio
                        ? btnGuardarServicio.textContent
                        : "Guardar servicio";


                try {

                    if (btnGuardarServicio) {

                        btnGuardarServicio.disabled =
                            true;

                        btnGuardarServicio.textContent =
                            "Guardando...";
                    }


                    const resultado =
                        await enviarSolicitud(
                            datos
                        );


                    alert(
                        resultado.mensaje ||
                        "Servicio guardado correctamente."
                    );


                    cerrarModalServicio();

                    window.location.reload();


                } catch (error) {

                    console.error(
                        "Error guardando servicio:",
                        error
                    );


                    mostrarMensajeServicio(
                        error.message ||
                        "No fue posible guardar el servicio."
                    );


                } finally {

                    if (btnGuardarServicio) {

                        btnGuardarServicio.disabled =
                            false;

                        btnGuardarServicio.textContent =
                            textoOriginal;
                    }
                }
            }
        );
    }


    // =========================================================
    // ACTIVAR / DESACTIVAR DEPARTAMENTO
    // =========================================================

    document
        .querySelectorAll(
            ".btn-estado-departamento"
        )
        .forEach(function (boton) {

            boton.addEventListener(
                "click",
                async function () {

                    const id =
                        parseInt(
                            this.dataset.id,
                            10
                        );


                    const activo =
                        this.dataset.activo === "1";


                    if (!id) {

                        alert(
                            "No se pudo identificar el departamento."
                        );

                        return;
                    }


                    const accion =
                        activo
                            ? "desactivar"
                            : "activar";


                    const mensaje =
                        activo
                            ? "¿Deseas desactivar este departamento?"
                            : "¿Deseas activar este departamento?";


                    if (!confirm(mensaje)) {
                        return;
                    }


                    try {

                        this.disabled =
                            true;


                        const resultado =
                            await enviarSolicitud({

                                accion:
                                    accion,

                                tipo:
                                    "departamento",

                                id_departamento:
                                    id
                            });


                        alert(
                            resultado.mensaje ||
                            "Estado actualizado correctamente."
                        );


                        window.location.reload();


                    } catch (error) {

                        console.error(
                            "Error cambiando estado del departamento:",
                            error
                        );


                        alert(
                            error.message ||
                            "No fue posible cambiar el estado."
                        );


                        this.disabled =
                            false;
                    }
                }
            );
        });


    // =========================================================
    // ACTIVAR / DESACTIVAR SERVICIO
    // =========================================================

    document
        .querySelectorAll(
            ".btn-estado-servicio"
        )
        .forEach(function (boton) {

            boton.addEventListener(
                "click",
                async function () {

                    const id =
                        parseInt(
                            this.dataset.id,
                            10
                        );


                    const activo =
                        this.dataset.activo === "1";


                    if (!id) {

                        alert(
                            "No se pudo identificar el servicio."
                        );

                        return;
                    }


                    const accion =
                        activo
                            ? "desactivar"
                            : "activar";


                    const mensaje =
                        activo
                            ? "¿Deseas desactivar este servicio?"
                            : "¿Deseas activar este servicio?";


                    if (!confirm(mensaje)) {
                        return;
                    }


                    try {

                        this.disabled =
                            true;


                        const resultado =
                            await enviarSolicitud({

                                accion:
                                    accion,

                                tipo:
                                    "servicio",

                                id_servicio:
                                    id
                            });


                        alert(
                            resultado.mensaje ||
                            "Estado actualizado correctamente."
                        );


                        window.location.reload();


                    } catch (error) {

                        console.error(
                            "Error cambiando estado del servicio:",
                            error
                        );


                        alert(
                            error.message ||
                            "No fue posible cambiar el estado."
                        );


                        this.disabled =
                            false;
                    }
                }
            );
        });


    // =========================================================
    // ELIMINAR DEPARTAMENTO
    // =========================================================

    document
        .querySelectorAll(
            ".btn-eliminar-departamento"
        )
        .forEach(function (boton) {

            boton.addEventListener(
                "click",
                async function () {

                    const id =
                        parseInt(
                            this.dataset.id,
                            10
                        );


                    const nombre =
                        this.dataset.nombre ||
                        "este departamento";


                    if (!id) {

                        alert(
                            "No se pudo identificar el departamento."
                        );

                        return;
                    }


                    const confirmar =
                        confirm(
                            "¿Estás seguro de eliminar el departamento \"" +
                            nombre +
                            "\"?\n\n" +

                            "ATENCIÓN: esta acción eliminará permanentemente:\n" +
                            "• El departamento\n" +
                            "• Todos sus servicios\n" +
                            "• Todos sus horarios\n" +
                            "• Todas las reservas asociadas\n" +
                            "• Todos los funcionarios asociados\n" +
                            "• Las configuraciones de horarios asociadas\n\n" +

                            "Esta acción NO se puede deshacer.\n\n" +

                            "¿Deseas continuar?"
                        );


                    if (!confirmar) {
                        return;
                    }


                    const confirmarFinal =
                        confirm(
                            "CONFIRMACIÓN FINAL\n\n" +

                            "Se eliminarán permanentemente todos los datos asociados al departamento \"" +
                            nombre +
                            "\".\n\n" +

                            "Presiona Aceptar para eliminarlo."
                        );


                    if (!confirmarFinal) {
                        return;
                    }


                    try {

                        this.disabled =
                            true;

                        this.textContent =
                            "Eliminando...";


                        const resultado =
                            await enviarSolicitud({

                                accion:
                                    "eliminar",

                                tipo:
                                    "departamento",

                                id_departamento:
                                    id
                            });


                        alert(
                            resultado.mensaje ||
                            "El departamento y sus datos asociados fueron eliminados correctamente."
                        );


                        window.location.reload();


                    } catch (error) {

                        console.error(
                            "Error eliminando departamento:",
                            error
                        );


                        alert(
                            error.message ||
                            "No fue posible eliminar el departamento."
                        );


                        this.disabled =
                            false;

                        this.textContent =
                            "Eliminar";
                    }
                }
            );
        });


    // =========================================================
    // ELIMINAR SERVICIO
    // =========================================================

    document
        .querySelectorAll(
            ".btn-eliminar-servicio"
        )
        .forEach(function (boton) {

            boton.addEventListener(
                "click",
                async function () {

                    const id =
                        parseInt(
                            this.dataset.id,
                            10
                        );


                    const nombre =
                        this.dataset.nombre ||
                        "este servicio";


                    if (!id) {

                        alert(
                            "No se pudo identificar el servicio."
                        );

                        return;
                    }


                    const confirmar =
                        confirm(
                            "¿Estás seguro de eliminar el servicio \"" +
                            nombre +
                            "\"?\n\n" +

                            "Esta acción eliminará permanentemente:\n" +
                            "• El servicio\n" +
                            "• Sus horarios\n" +
                            "• Sus reservas asociadas\n" +
                            "• Sus configuraciones de horarios\n" +
                            "• Sus relaciones de unificación\n\n" +

                            "Esta acción NO se puede deshacer.\n\n" +

                            "¿Deseas continuar?"
                        );


                    if (!confirmar) {
                        return;
                    }


                    try {

                        this.disabled =
                            true;

                        this.textContent =
                            "Eliminando...";


                        const resultado =
                            await enviarSolicitud({

                                accion:
                                    "eliminar",

                                tipo:
                                    "servicio",

                                id_servicio:
                                    id
                            });


                        alert(
                            resultado.mensaje ||
                            "Servicio eliminado correctamente."
                        );


                        window.location.reload();


                    } catch (error) {

                        console.error(
                            "Error eliminando servicio:",
                            error
                        );


                        alert(
                            error.message ||
                            "No fue posible eliminar el servicio."
                        );


                        this.disabled =
                            false;

                        this.textContent =
                            "Eliminar";
                    }
                }
            );
        });


    // =========================================================
    // FILTRO POR DEPARTAMENTO
    // =========================================================

    if (filtroDepartamento) {

        filtroDepartamento.addEventListener(
            "change",
            function () {

                const idDepartamento =
                    this.value;


                if (!tablaServicios) {
                    return;
                }


                const filas =
                    tablaServicios.querySelectorAll(
                        "tr[data-id-servicio]"
                    );


                filas.forEach(
                    function (fila) {

                        const idFila =
                            fila.dataset.idDepartamento;


                        if (
                            !idDepartamento ||
                            idFila === idDepartamento
                        ) {

                            fila.style.display =
                                "";

                        } else {

                            fila.style.display =
                                "none";
                        }
                    }
                );
            }
        );
    }

});