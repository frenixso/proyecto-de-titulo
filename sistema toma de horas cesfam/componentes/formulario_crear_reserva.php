<?php
/*
 * Formulario reutilizable para crear reservas desde:
 * - Agenda de funcionarios
 * - Gestionar reservas
 *
 * Está basado en el formulario original de:
 * publicos/toma_horas.php
 *
 * La reserva se envía posteriormente a:
 * api/crear_reserva.php
 *
 * En este formulario el JS debe enviar:
 * modo=funcionario
 */

$serviciosFormulario = $servicios ?? [];
?>

<section id="createReservationSection" class="create-reservation-overlay" hidden>

    <div class="create-reservation-modal" role="dialog" aria-modal="true" aria-labelledby="createReservationTitle">

        <!-- ENCABEZADO -->
        <div class="create-reservation-header">

            <div>
                <h2 id="createReservationTitle">
                    Crear reserva
                </h2>

                <p>
                    Complete los datos para registrar una nueva hora.
                </p>
            </div>

            <button
                type="button"
                id="btnCloseCreateModal"
                class="create-reservation-close"
                aria-label="Cerrar"
            >
                &times;
            </button>

        </div>


        <!-- NOTIFICACIÓN -->
        <div id="createReservationNotification" class="create-reservation-notification" hidden>
            <span id="createReservationNotificationMessage"></span>
        </div>


        <!-- FORMULARIO -->
        <form id="createReservationForm" class="create-reservation-form" novalidate>

            <!-- DATOS PERSONALES -->
            <div class="create-form-section">

                <div class="create-form-section-title">
                    <h3>Datos de la persona</h3>
                    <p>Ingrese los datos de quien solicitará la hora.</p>
                </div>


                <div class="create-form-grid">

                    <!-- NOMBRE -->
                    <div class="create-form-group full">

                        <label for="createNombre">
                            Nombre completo
                            <span>*</span>
                        </label>

                        <input
                            type="text"
                            id="createNombre"
                            name="nombre"
                            autocomplete="name"
                            maxlength="100"
                            placeholder="Ingrese nombre completo"
                        >

                        <small
                            id="errorNombre"
                            class="create-form-error"
                        ></small>

                    </div>


                    <!-- TIPO IDENTIFICACIÓN -->
                    <div class="create-form-group full">

                        <label>
                            Tipo de identificación
                            <span>*</span>
                        </label>

                        <div class="create-identification-options">

                            <label class="create-identification-option">
                                <input
                                    type="radio"
                                    name="tipo_identificacion"
                                    value="rut"
                                    checked
                                >
                                <span>RUT</span>
                            </label>

                            <label class="create-identification-option">
                                <input
                                    type="radio"
                                    name="tipo_identificacion"
                                    value="rut_extranjero"
                                >
                                <span>RUT extranjero</span>
                            </label>

                            <label class="create-identification-option">
                                <input
                                    type="radio"
                                    name="tipo_identificacion"
                                    value="pasaporte"
                                >
                                <span>Pasaporte</span>
                            </label>

                        </div>

                    </div>


                    <!-- RUT / IDENTIFICACIÓN -->
                    <div class="create-form-group">

                        <label for="createRut">
                            RUT / Identificación
                            <span>*</span>
                        </label>

                        <input
                            type="text"
                            id="createRut"
                            name="rut"
                            autocomplete="off"
                            maxlength="20"
                            placeholder="Ej: 12.345.678-5"
                        >

                        <small
                            id="errorRut"
                            class="create-form-error"
                        ></small>

                    </div>


                    <!-- TELÉFONO -->
                    <div class="create-form-group">

                        <label for="createTelefono">
                            Teléfono
                            <span>*</span>
                        </label>

                        <input
                            type="tel"
                            id="createTelefono"
                            name="telefono"
                            autocomplete="tel"
                            maxlength="20"
                            placeholder="Ej: +56 9 1234 5678"
                        >

                        <small
                            id="errorTelefono"
                            class="create-form-error"
                        ></small>

                    </div>


                    <!-- CORREO -->
                    <div class="create-form-group full">

                        <label for="createCorreo">
                            Correo electrónico
                        </label>

                        <input
                            type="email"
                            id="createCorreo"
                            name="correo"
                            autocomplete="email"
                            maxlength="150"
                            placeholder="Ej: nombre@correo.cl"
                        >

                        <small
                            id="errorCorreo"
                            class="create-form-error"
                        ></small>

                    </div>

                </div>

            </div>


            <!-- DATOS DE LA RESERVA -->
            <div class="create-form-section">

                <div class="create-form-section-title">
                    <h3>Datos de la hora</h3>
                    <p>Seleccione el departamento, servicio, fecha y horario.</p>
                </div>


                <div class="create-form-grid">

                    <!-- DEPARTAMENTO -->
                    <div class="create-form-group full">

                        <label for="createDepartamento">
                            Departamento
                            <span>*</span>
                        </label>

                        <select
                            id="createDepartamento"
                            name="departamento"
                            disabled
                        >

                            <option value="">
                                Complete sus datos primero
                            </option>

                        </select>

                        <small
                            id="errorDepartamento"
                            class="create-form-error"
                        ></small>

                    </div>


                    <!-- SERVICIO -->
                    <div class="create-form-group full">

                        <label for="createServicio">
                            Servicio
                            <span>*</span>
                        </label>

                        <select
                            id="createServicio"
                            name="servicio"
                            disabled
                        >

                            <option value="">
                                Seleccione primero un departamento
                            </option>

                        </select>

                        <small
                            id="errorServicio"
                            class="create-form-error"
                        ></small>

                    </div>


                    <!-- FECHA -->
                    <div class="create-form-group">

                        <label for="createFecha">
                            Fecha
                            <span>*</span>
                        </label>

                        <input
                            type="date"
                            id="createFecha"
                            name="fecha"
                            disabled
                        >

                        <small
                            id="errorFecha"
                            class="create-form-error"
                        ></small>

                    </div>


                    <!-- HORARIO -->
                    <div class="create-form-group">

                        <label for="createHorario">
                            Horario
                            <span>*</span>
                        </label>

                        <select
                            id="createHorario"
                            name="hora"
                            disabled
                        >

                            <option value="">
                                Seleccione primero una fecha
                            </option>

                        </select>

                        <small
                            id="createHorarioEstado"
                            class="create-horario-estado"
                        ></small>

                        <small
                            id="errorHorario"
                            class="create-form-error"
                        ></small>

                    </div>


                    <!-- OBSERVACIÓN -->
                    <div class="create-form-group full">

                        <label for="createObservacion">
                            Observación
                        </label>

                        <textarea
                            id="createObservacion"
                            name="observacion"
                            rows="3"
                            maxlength="500"
                            placeholder="Ingrese una observación si es necesario"
                        ></textarea>

                    </div>

                </div>

            </div>


            <!-- RESUMEN -->
            <div class="create-reservation-preview">

                <div class="create-preview-header">
                    <h3>Resumen de la reserva</h3>
                </div>

                <div class="create-preview-grid">

                    <div class="create-preview-item">

                        <span class="create-preview-label">
                            Persona
                        </span>

                        <strong id="previewNombre">
                            —
                        </strong>

                    </div>


                    <div class="create-preview-item">

                        <span class="create-preview-label">
                            Identificación
                        </span>

                        <strong id="previewIdentificacion">
                            —
                        </strong>

                    </div>


                    <div class="create-preview-item">

                        <span class="create-preview-label">
                            Departamento
                        </span>

                        <strong id="previewDepartamento">
                            —
                        </strong>

                    </div>


                    <div class="create-preview-item">

                        <span class="create-preview-label">
                            Servicio
                        </span>

                        <strong id="previewServicio">
                            —
                        </strong>

                    </div>


                    <div class="create-preview-item">

                        <span class="create-preview-label">
                            Fecha
                        </span>

                        <strong id="previewFecha">
                            —
                        </strong>

                    </div>


                    <div class="create-preview-item">

                        <span class="create-preview-label">
                            Hora
                        </span>

                        <strong id="previewHora">
                            —
                        </strong>

                    </div>

                </div>

            </div>


            <!-- MODO -->
            <input
                type="hidden"
                name="modo"
                value="funcionario"
            >


            <!-- BOTONES -->
            <div class="create-reservation-actions">

                <button
                    type="button"
                    id="btnCancelCreate"
                    class="create-cancel-button"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="create-submit-button"
                >
                    Crear reserva
                </button>

            </div>

        </form>

    </div>

</section>