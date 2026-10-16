<?php
$base_url = '/sistema toma de horas cesfam';
?>

<!DOCTYPE html>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>Toma de horas | Municipalidad</title>

<link rel="stylesheet" href="<?php echo $base_url; ?>/css/general.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>/css/navbar_publica.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>/css/toma_horas.css">
<link rel="stylesheet" href="<?php echo $base_url; ?>/css/footer_publico.css">

<!-- CARGA DE SCRIPTS PÚBLICOS DE ACCESIBILIDAD Y FORMULARIO -->
<script src="<?php echo $base_url; ?>/js/script.js" defer></script>
<script src="<?php echo $base_url; ?>/js/toma_horas.js" defer></script>


</head>

<body>

<?php require_once __DIR__ . '/../componentes/navbar_publica.php'; ?>

<main class="reservation-page">


<!-- ENCABEZADO -->
<section class="reservation-header">

    <div class="reservation-header-content">

        <span class="reservation-tag">
            SERVICIOS MUNICIPALES
        </span>

        <h1>Toma de horas</h1>

        <p>
            Solicita una hora de atención municipal seleccionando tus
            datos, departamento, servicio, fecha y horario disponible.
        </p>

    </div>

</section>


<!-- CONTENIDO -->
<section class="reservation-container">


    <!-- CREAR RESERVA -->
    <form
        id="createReservationForm"
        class="create-reservation-card"
        novalidate
    >

        <div class="card-header">

            <span class="card-kicker">
                NUEVA SOLICITUD
            </span>

            <h2>Crear reserva</h2>

            <p>
                Complete los datos necesarios para solicitar una hora.
            </p>

        </div>


        <!-- DATOS DEL SOLICITANTE -->
        <div class="form-section">

            <div class="form-section-title">

                <span class="section-number">
                    01
                </span>

                <div>
                    <h3>Datos del solicitante</h3>

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

                    <label for="createRut" id="identificacionLabel">
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


                <!-- TELEFONO -->
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


        <!-- DATOS DE LA SOLICITUD -->
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
                        Seleccione el departamento, servicio, fecha y horario de atención.
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


        <!-- NOTIFICACIÓN -->
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


        <!-- BOTONES -->
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


    <!-- RESUMEN -->
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
                    Las horas disponibles se actualizan automáticamente
                    según la fecha seleccionada.
                </p>

            </div>

        </div>

    </aside>

</section>


</main>

<!-- POPUP -->

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

<?php require_once __DIR__ . '/../componentes/footer_publico.php'; ?>

</body>
</html>
