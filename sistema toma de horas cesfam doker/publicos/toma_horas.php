<?php

$base_url = getenv('APP_BASE_URL') !== false ? rtrim(getenv('APP_BASE_URL'), '/') : '/sistema toma de horas cesfam';

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Toma de horas | CESFAM Eduardo Raggio Catemu
    </title>


    <!-- ================================================
         ESTILOS
    ================================================= -->

    <link
        rel="stylesheet"
        href="<?php echo $base_url; ?>/css/compartidos/general.css"
    >

    <link
        rel="stylesheet"
        href="<?php echo $base_url; ?>/css/compartidos/navbar_publica.css"
    >

    <link
        rel="stylesheet"
        href="<?php echo $base_url; ?>/css/publicos/toma_horas.css"
    >

    
    <link
        rel="stylesheet"
        href="<?php echo $base_url; ?>/css/compartidos/footer_publico.css"
    >


    <!-- ================================================
         JAVASCRIPT

         script.js se carga desde navbar_publica.php.
         No volver a cargarlo aquí.
    ================================================= -->

    <script
        src="<?php echo $base_url; ?>/js/toma_horas.js"
        defer
    ></script>

</head>

<body>

<?php require_once __DIR__ . '/../componentes/navbar_publica.php'; ?>


<main class="reservation-page">


    <!-- ================================================
         ENCABEZADO
    ================================================= -->

    <section class="reservation-header">

        <div class="reservation-header-content">

            <span class="reservation-tag">
                SERVICIOS MUNICIPALES
            </span>

            <h1>
                Toma de horas
            </h1>

            <p>
                Solicita una hora de atención seleccionando tus
                datos, departamento, servicio, fecha y horario disponible.
            </p>

        </div>

    </section>


    <!-- ================================================
         CONTENIDO PRINCIPAL
    ================================================= -->

    <section class="reservation-container">


        <!-- ============================================
             FORMULARIO DE RESERVA NORMAL
        ============================================= -->

        <form
            id="createReservationForm"
            class="create-reservation-card"
            novalidate
        >

            <div class="card-header">

                <span class="card-kicker">
                    NUEVA SOLICITUD
                </span>

                <h2>
                    Crear reserva
                </h2>

                <p>
                    Complete los datos necesarios para solicitar una hora.
                </p>

            </div>


            <!-- ========================================
                 01. DATOS DEL SOLICITANTE
            ========================================= -->

            <div class="form-section">

                <div class="form-section-title">

                    <span class="section-number">
                        01
                    </span>

                    <div>

                        <h3>
                            Datos del solicitante
                        </h3>

                        <p>
                            Información de la persona que realizará la solicitud.
                        </p>

                    </div>

                </div>


                <div class="form-grid">


                    <!-- NOMBRE -->

                    <div class="form-group form-group-full">

                        <label for="createNombre">

                            Nombre completo

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="text"
                            id="createNombre"
                            name="nombre"
                            placeholder="Ingrese su nombre completo"
                            autocomplete="name"
                        >

                        <span
                            class="field-error"
                            id="errorNombre"
                        >
                            Campo obligatorio.
                        </span>

                    </div>


                    <!-- IDENTIFICACIÓN -->

                    <div class="form-group form-group-full">

                        <label
                            for="createRut"
                            id="identificacionLabel"
                        >

                            RUT

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="text"
                            id="createRut"
                            name="rut"
                            placeholder="Ej: 12.345.678-5"
                            autocomplete="off"
                            maxlength="12"
                        >


                        <!-- TIPO DE IDENTIFICACIÓN -->

                        <div class="identification-options">

                            <label class="identification-option">

                                <input
                                    type="radio"
                                    name="tipo_identificacion"
                                    value="rut"
                                    checked
                                >

                                <span class="radio-custom"></span>

                                <span>
                                    RUT
                                </span>

                            </label>


                            <label class="identification-option">

                                <input
                                    type="radio"
                                    name="tipo_identificacion"
                                    value="rut_extranjero"
                                >

                                <span class="radio-custom"></span>

                                <span>
                                    RUT extranjero
                                </span>

                            </label>


                            <label class="identification-option">

                                <input
                                    type="radio"
                                    name="tipo_identificacion"
                                    value="pasaporte"
                                >

                                <span class="radio-custom"></span>

                                <span>
                                    Pasaporte
                                </span>

                            </label>

                        </div>


                        <span
                            class="field-error"
                            id="errorRut"
                        >
                            Campo obligatorio.
                        </span>

                    </div>


                    <!-- TELÉFONO -->

                    <div class="form-group">

                        <label for="createTelefono">

                            Teléfono

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="tel"
                            id="createTelefono"
                            name="telefono"
                            placeholder="+56 9 1234 5678"
                            autocomplete="tel"
                        >

                        <span
                            class="field-error"
                            id="errorTelefono"
                        >
                            Campo obligatorio.
                        </span>

                    </div>


                    <!-- CORREO -->

                    <div class="form-group">

                        <label for="createCorreo">

                            Correo electrónico

                            <span class="optional-mark">
                                Opcional
                            </span>

                        </label>

                        <input
                            type="email"
                            id="createCorreo"
                            name="correo"
                            placeholder="ejemplo@correo.cl"
                            autocomplete="email"
                        >

                        <span
                            class="field-error"
                            id="errorCorreo"
                        >
                            Ingrese un correo válido.
                        </span>

                    </div>

                </div>

            </div>


            <!-- ========================================
                 02. DATOS DE LA SOLICITUD
            ========================================= -->

            <div class="form-section">

                <div class="form-section-title">

                    <span class="section-number">
                        02
                    </span>

                    <div>

                        <h3>
                            Datos de la solicitud
                        </h3>

                        <p>
                            Seleccione el departamento, servicio,
                            fecha y horario de atención.
                        </p>

                    </div>

                </div>


                <div class="form-grid">


                    <!-- DEPARTAMENTO -->

                    <div class="form-group form-group-full">

                        <label for="createDepartamento">

                            Departamento

                            <span class="required-mark">*</span>

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

                        <span
                            class="field-error"
                            id="errorDepartamento"
                        >
                            Campo obligatorio.
                        </span>

                    </div>


                    <!-- SERVICIO -->

                    <div class="form-group form-group-full">

                        <label for="createServicio">

                            Servicio

                            <span class="required-mark">*</span>

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

                        <span
                            class="field-error"
                            id="errorServicio"
                        >
                            Campo obligatorio.
                        </span>

                    </div>


                    <!-- FECHA -->

                    <div class="form-group">

                        <label for="createFecha">

                            Fecha

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="date"
                            id="createFecha"
                            name="fecha"
                            disabled
                        >

                        <span
                            class="field-error"
                            id="errorFecha"
                        >
                            Campo obligatorio.
                        </span>

                    </div>


                    <!-- HORARIO -->

                    <div class="form-group">

                        <label for="createHorario">

                            Horario

                            <span class="required-mark">*</span>

                        </label>

                        <select
                            id="createHorario"
                            name="hora"
                            disabled
                        >

                            <option value="">
                                Seleccione servicio y fecha
                            </option>

                        </select>

                        <span
                            class="field-error"
                            id="errorHorario"
                        >
                            Campo obligatorio.
                        </span>

                    </div>


                    <!-- OBSERVACIÓN -->

                    <div class="form-group form-group-full">

                        <label for="createObservacion">

                            Observación

                            <span class="optional-mark">
                                Opcional
                            </span>

                        </label>

                        <textarea
                            id="createObservacion"
                            name="observacion"
                            rows="4"
                            placeholder="Ingrese una observación si es necesario"
                        ></textarea>

                    </div>

                </div>

            </div>


            <!-- ========================================
                 NOTIFICACIÓN DE RESERVA
            ========================================= -->

            <div
                class="notification"
                id="notification"
                hidden
            >

                <span class="notification-icon">
                    !
                </span>

                <div>

                    <strong>
                        Información
                    </strong>

                    <p id="notificationMessage"></p>

                </div>

            </div>


            <!-- ========================================
                 BOTONES DE RESERVA
            ========================================= -->

            <div class="form-actions">

                <a
                    href="<?php echo $base_url; ?>/publicos/index.php"
                    class="secondary-button"
                >
                    Volver al inicio
                </a>

                <button
                    type="submit"
                    class="primary-button"
                >
                    Solicitar hora
                </button>

            </div>

        </form>


        <!-- ============================================
             RESUMEN DE LA RESERVA
        ============================================= -->

        <aside class="reservation-summary">

            <div class="summary-card">

                <div class="summary-header">

                    <span class="card-kicker">
                        RESUMEN
                    </span>

                    <h2>
                        Tu solicitud
                    </h2>

                    <p>
                        Revisa la información antes de confirmar la reserva.
                    </p>

                </div>


                <div class="summary-section">

                    <div class="form-section-title">

                        <span class="section-number">
                            03
                        </span>

                        <div>

                            <h3>
                                Detalles de la reserva
                            </h3>

                            <p>
                                Información seleccionada para tu solicitud.
                            </p>

                        </div>

                    </div>


                    <div class="summary-list">

                        <div class="summary-item">

                            <span>
                                Solicitante
                            </span>

                            <strong id="previewNombre">
                                -
                            </strong>

                        </div>


                        <div class="summary-item">

                            <span>
                                Identificación
                            </span>

                            <strong id="previewIdentificacion">
                                -
                            </strong>

                        </div>


                        <div class="summary-item">

                            <span>
                                Departamento
                            </span>

                            <strong id="previewDepartamento">
                                -
                            </strong>

                        </div>


                        <div class="summary-item">

                            <span>
                                Servicio
                            </span>

                            <strong id="previewServicio">
                                -
                            </strong>

                        </div>


                        <div class="summary-item">

                            <span>
                                Fecha
                            </span>

                            <strong id="previewFecha">
                                -
                            </strong>

                        </div>


                        <div class="summary-item">

                            <span>
                                Hora
                            </span>

                            <strong id="previewHora">
                                -
                            </strong>

                        </div>

                    </div>

                </div>


                <div class="summary-info">

                    <span class="summary-info-icon">
                        ✓
                    </span>

                    <p>
                        Las horas disponibles se actualizan
                        automáticamente según la fecha seleccionada.
                    </p>

                </div>

            </div>

        </aside>

    </section>

</main>


<!-- ================================================
     MODAL DE SOLICITUD DE HORAS / LISTA DE ESPERA
================================================= -->

<section
    class="solicitud-espera-container solicitud-espera-modal"
    id="solicitudEsperaContainer"
    aria-hidden="true"
    hidden
>

    <!-- FONDO OSCURO -->

    <div
        class="solicitud-espera-overlay"
        id="solicitudEsperaOverlay"
    ></div>


    <!-- VENTANA EMERGENTE -->

    <div
        class="solicitud-espera-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="solicitudEsperaTitulo"
        aria-describedby="solicitudEsperaDescripcion"
    >

        <div class="solicitud-espera-card">


            <!-- BOTÓN PARA CERRAR -->

            <button
                type="button"
                class="solicitud-espera-cerrar"
                id="btnCerrarSolicitudHoras"
                aria-label="Cerrar solicitud de horas"
            >
                &times;
            </button>


            <!-- ENCABEZADO -->

            <div class="solicitud-espera-header">

                <span class="card-kicker">
                    LISTA DE ESPERA
                </span>

                <h2 id="solicitudEsperaTitulo">
                    Solicitud de horas
                </h2>

                <p id="solicitudEsperaDescripcion">
                    Actualmente no quedan cupos disponibles
                    para el servicio y la fecha seleccionados.
                </p>

            </div>


            <!-- AVISO INFORMATIVO -->

            <div class="solicitud-espera-aviso">

                <span class="solicitud-espera-icono">
                    ℹ
                </span>

                <p>

                    Si se cancela una hora y se libera un cupo,
                    tendremos en cuenta tu solicitud para contactarte
                    y ofrecerte una hora disponible.

                    <strong>
                        Enviar esta solicitud no garantiza
                        una reserva ni la asignación de un cupo.
                    </strong>

                </p>

            </div>


            <!-- FORMULARIO -->

            <form
                id="formSolicitudHoras"
                novalidate
            >

                <!-- DATOS INTERNOS DE LA SOLICITUD -->

                <input
                    type="hidden"
                    name="id_departamento"
                    id="solicitudDepartamento"
                >

                <input
                    type="hidden"
                    name="id_servicio"
                    id="solicitudServicio"
                >

                <input
                    type="hidden"
                    name="fecha_solicitada"
                    id="solicitudFecha"
                >


                <!-- DATOS PERSONALES -->

                <div class="solicitud-espera-datos">


                    <!-- RUT -->

                    <div class="form-group">

                        <label for="solicitudRut">

                            RUT

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="text"
                            id="solicitudRut"
                            name="rut"
                            placeholder="12.345.678-5"
                            maxlength="12"
                            autocomplete="off"
                            required
                        >

                    </div>


                    <!-- TELÉFONO -->

                    <div class="form-group">

                        <label for="solicitudTelefono">

                            Número de teléfono

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="tel"
                            id="solicitudTelefono"
                            name="telefono"
                            placeholder="+56 9 1234 5678"
                            autocomplete="tel"
                            required
                        >

                    </div>


                    <!-- NOMBRE -->

                    <div class="form-group">

                        <label for="solicitudNombre">

                            Nombre

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="text"
                            id="solicitudNombre"
                            name="nombre"
                            placeholder="Ingrese su nombre"
                            maxlength="100"
                            autocomplete="given-name"
                            required
                        >

                    </div>


                    <!-- APELLIDO -->

                    <div class="form-group">

                        <label for="solicitudApellido">

                            Apellido

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="text"
                            id="solicitudApellido"
                            name="apellido"
                            placeholder="Ingrese su apellido"
                            maxlength="100"
                            autocomplete="family-name"
                            required
                        >

                    </div>


                    <!-- CORREO -->

                    <div class="form-group solicitud-campo-completo">

                        <label for="solicitudCorreo">

                            Correo electrónico

                            <span class="required-mark">*</span>

                        </label>

                        <input
                            type="email"
                            id="solicitudCorreo"
                            name="correo"
                            placeholder="ejemplo@correo.cl"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>


                <!-- RESULTADO DEL ENVÍO -->

                <div
                    class="solicitud-espera-resultado"
                    id="solicitudHorasResultado"
                    role="status"
                    aria-live="polite"
                    hidden
                ></div>


                <!-- BOTÓN DE ENVÍO -->

                <div class="solicitud-espera-acciones">

                    <button
                        type="submit"
                        class="primary-button"
                        id="btnEnviarSolicitudHoras"
                    >
                        Enviar solicitud
                    </button>

                </div>

            </form>

        </div>

    </div>

</section>


<!-- ================================================
     POPUP DE CONFIRMACIÓN DE RESERVA
================================================= -->

<div
    class="reservation-modal"
    id="reservationModal"
    hidden
>

    <div
        class="reservation-modal-overlay"
        id="reservationModalOverlay"
    ></div>


    <div
        class="reservation-modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="reservationModalTitle"
    >

        <div
            class="reservation-modal-icon"
            id="reservationModalIcon"
        >
            ✓
        </div>

        <h2 id="reservationModalTitle">
            Reserva validada
        </h2>

        <p id="reservationModalMessage">
            Su reserva fue guardada correctamente.
        </p>

        <button
            type="button"
            id="reservationModalButton"
            class="modal-button"
        >
            Aceptar
        </button>

    </div>

</div>


<!-- ================================================
     FOOTER PÚBLICO
================================================= -->

<?php

require_once __DIR__ . '/../componentes/footer_publico.php';

?>

</body>
</html>
