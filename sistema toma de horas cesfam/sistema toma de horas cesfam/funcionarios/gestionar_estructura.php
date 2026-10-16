
<?php

/*
 * =========================================================
 * GESTIONAR ESTRUCTURA - CESFAM
 * DEPARTAMENTOS Y SERVICIOS
 * =========================================================
 */

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';


/* =========================================================
   VALIDAR ROL
   SOLO SUPERADMIN
   ========================================================= */

$rolFuncionario = strtolower(
    trim(
        $_SESSION['rol_funcionario'] ?? ''
    )
);


if ($rolFuncionario !== 'superadmin') {

    header(
        'Location: panel.php'
    );

    exit;
}


/* =========================================================
   PÁGINA ACTUAL
   ========================================================= */

$pagina_actual = 'estructura';


/* =========================================================
   OBTENER DEPARTAMENTOS
   ========================================================= */

$sqlDepartamentos = "
    SELECT
        d.id_departamento,
        d.nombre,
        d.activo,
        COUNT(s.id_servicio) AS cantidad_servicios
    FROM departamentos d
    LEFT JOIN servicios s
        ON s.id_departamento = d.id_departamento
    GROUP BY
        d.id_departamento,
        d.nombre,
        d.activo
    ORDER BY
        d.nombre ASC
";


$stmtDepartamentos =
    $conexion->prepare(
        $sqlDepartamentos
    );


$stmtDepartamentos->execute();


$departamentos =
    $stmtDepartamentos->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   OBTENER SERVICIOS
   ========================================================= */

$sqlServicios = "
    SELECT
        s.id_servicio,
        s.id_departamento,
        s.nombre,
        s.descripcion,
        s.activo,
        d.nombre AS departamento_nombre
    FROM servicios s
    INNER JOIN departamentos d
        ON d.id_departamento = s.id_departamento
    ORDER BY
        d.nombre ASC,
        s.nombre ASC
";


$stmtServicios =
    $conexion->prepare(
        $sqlServicios
    );


$stmtServicios->execute();


$servicios =
    $stmtServicios->fetchAll(
        PDO::FETCH_ASSOC
    );

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
        Departamentos y servicios | CESFAM
    </title>


    <!-- =====================================================
         CSS GENERAL
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/general.css"
    >


    <!-- =====================================================
         CSS NAVBAR
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/navbar_funcionario.css"
    >


    <!-- =====================================================
         CSS SIDEBAR
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/sidebar_funcionario.css"
    >


    <!-- =====================================================
         CSS ESTRUCTURA
         ===================================================== -->

    <link
        rel="stylesheet"
        href="../css/gestionar_estructura.css"
    >

</head>


<body>


<?php

/*
 * =========================================================
 * NAVBAR
 * =========================================================
 */

require_once '../componentes/navbar_funcionario.php';

?>


<div class="layout-funcionario">


<?php

/*
 * =========================================================
 * SIDEBAR
 * =========================================================
 */

require_once '../componentes/sidebar_funcionario.php';

?>


<main class="main-content">


    <!-- =====================================================
         CONTENEDOR PRINCIPAL

         IMPORTANTE:
         Esta clase es la que reserva los 250px
         correspondientes a la sidebar.
         ===================================================== -->

    <div class="estructura-container">


        <!-- =================================================
             ENCABEZADO
             ================================================= -->

        <section class="page-header">

            <div>

                <span class="page-kicker">
                    Administración del sistema
                </span>


                <h1>
                    Departamentos y servicios
                </h1>


                <p>
                    Administra los departamentos y servicios
                    disponibles en el sistema de toma de horas
                    del CESFAM.
                </p>

            </div>


            <div class="page-header-actions">

                <button
                    type="button"
                    class="primary-button"
                    id="btnAgregarDepartamento"
                >

                    +

                    <span>
                        Nuevo departamento
                    </span>

                </button>

            </div>

        </section>


        <!-- =================================================
             DEPARTAMENTOS
             ================================================= -->

        <section class="estructura-section">


            <div class="section-header">

                <div>

                    <span class="section-kicker">
                        Organización
                    </span>

                    <h2>
                        Departamentos
                    </h2>

                    <p>
                        Administra las áreas que forman parte
                        del CESFAM.
                    </p>

                </div>


                <div>

                    <button
                        type="button"
                        class="secondary-button"
                        id="btnAgregarServicio"
                    >
                        + Agregar servicio
                    </button>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>
                                Departamento
                            </th>

                            <th>
                                Servicios
                            </th>

                            <th>
                                Estado
                            </th>

                            <th>
                                Acciones
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (empty($departamentos)): ?>

                        <tr>

                            <td
                                colspan="4"
                                class="empty-state"
                            >

                                No hay departamentos
                                registrados.

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach (
                            $departamentos
                            as $departamento
                        ): ?>


                            <tr>

                                <td>

                                    <div class="table-primary-text">

                                        <?php echo htmlspecialchars(
                                            $departamento['nombre'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </div>

                                </td>


                                <td>

                                    <span class="count-badge">

                                        <?php echo (int)
                                            $departamento[
                                                'cantidad_servicios'
                                            ]; ?>

                                    </span>

                                </td>


                                <td>

                                    <?php if (
                                        $departamento['activo']
                                    ): ?>

                                        <span class="status-badge active">
                                            Activo
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge inactive">
                                            Inactivo
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <div class="table-actions">


                                        <!-- =====================
                                             EDITAR
                                             ===================== -->

                                        <button
                                            type="button"
                                            class="table-action-button btn-editar-departamento"

                                            data-id="<?php echo (int)
                                                $departamento[
                                                    'id_departamento'
                                                ]; ?>"

                                            data-nombre="<?php echo htmlspecialchars(
                                                $departamento['nombre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >
                                            Editar
                                        </button>


                                        <!-- =====================
                                             ACTIVAR / DESACTIVAR
                                             ===================== -->

                                        <button
                                            type="button"
                                            class="table-action-button btn-estado-departamento"

                                            data-id="<?php echo (int)
                                                $departamento[
                                                    'id_departamento'
                                                ]; ?>"

                                            data-activo="<?php echo
                                                $departamento['activo']
                                                    ? '1'
                                                    : '0';
                                            ?>"
                                        >

                                            <?php echo
                                                $departamento['activo']
                                                    ? 'Desactivar'
                                                    : 'Activar';
                                            ?>

                                        </button>


                                        <!-- =====================
                                             ELIMINAR
                                             ===================== -->

                                        <button
                                            type="button"
                                            class="table-action-button delete btn-eliminar-departamento"

                                            data-id="<?php echo (int)
                                                $departamento[
                                                    'id_departamento'
                                                ]; ?>"

                                            data-nombre="<?php echo htmlspecialchars(
                                                $departamento['nombre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >
                                            Eliminar
                                        </button>


                                    </div>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- =================================================
             SERVICIOS
             ================================================= -->

        <section class="estructura-section">


            <div class="section-header">

                <div>

                    <span class="section-kicker">
                        Atención
                    </span>

                    <h2>
                        Servicios
                    </h2>

                    <p>
                        Administra los servicios disponibles
                        para la toma de horas.
                    </p>

                </div>


                <!-- FILTRO -->

                <div class="section-filter">

                    <label
                        for="filtroDepartamento"
                    >
                        Filtrar por departamento
                    </label>


                    <select
                        id="filtroDepartamento"
                        class="form-select"
                    >

                        <option value="">
                            Todos los departamentos
                        </option>


                        <?php foreach (
                            $departamentos
                            as $departamento
                        ): ?>

                            <option
                                value="<?php echo (int)
                                    $departamento[
                                        'id_departamento'
                                    ]; ?>"
                            >

                                <?php echo htmlspecialchars(
                                    $departamento['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>
                                Servicio
                            </th>

                            <th>
                                Departamento
                            </th>

                            <th>
                                Descripción
                            </th>

                            <th>
                                Estado
                            </th>

                            <th>
                                Acciones
                            </th>

                        </tr>

                    </thead>


                    <tbody id="tablaServicios">


                    <?php if (empty($servicios)): ?>

                        <tr>

                            <td
                                colspan="5"
                                class="empty-state"
                            >

                                No hay servicios
                                registrados.

                            </td>

                        </tr>


                    <?php else: ?>


                        <?php foreach (
                            $servicios
                            as $servicio
                        ): ?>


                            <tr

                                data-id-servicio="<?php echo (int)
                                    $servicio[
                                        'id_servicio'
                                    ]; ?>"

                                data-id-departamento="<?php echo (int)
                                    $servicio[
                                        'id_departamento'
                                    ]; ?>"

                            >


                                <td>

                                    <div class="table-primary-text">

                                        <?php echo htmlspecialchars(
                                            $servicio['nombre'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>

                                    </div>

                                </td>


                                <td>

                                    <?php echo htmlspecialchars(
                                        $servicio[
                                            'departamento_nombre'
                                        ],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </td>


                                <td>

                                    <div class="table-secondary-text">

                                        <?php

                                        $descripcion =
                                            trim(
                                                (string)
                                                (
                                                    $servicio[
                                                        'descripcion'
                                                    ] ?? ''
                                                )
                                            );


                                        if (
                                            $descripcion === ''
                                        ) {

                                            echo 'Sin descripción';

                                        } else {

                                            echo htmlspecialchars(
                                                $descripcion,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            );

                                        }

                                        ?>

                                    </div>

                                </td>


                                <td>

                                    <?php if (
                                        $servicio['activo']
                                    ): ?>

                                        <span class="status-badge active">
                                            Activo
                                        </span>

                                    <?php else: ?>

                                        <span class="status-badge inactive">
                                            Inactivo
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <div class="table-actions">


                                        <!-- =====================
                                             EDITAR
                                             ===================== -->

                                        <button
                                            type="button"
                                            class="table-action-button btn-editar-servicio"

                                            data-id="<?php echo (int)
                                                $servicio[
                                                    'id_servicio'
                                                ]; ?>"

                                            data-id-departamento="<?php echo (int)
                                                $servicio[
                                                    'id_departamento'
                                                ]; ?>"

                                            data-nombre="<?php echo htmlspecialchars(
                                                $servicio['nombre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"

                                            data-descripcion="<?php echo htmlspecialchars(
                                                $servicio[
                                                    'descripcion'
                                                ] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >
                                            Editar
                                        </button>


                                        <!-- =====================
                                             ACTIVAR / DESACTIVAR
                                             ===================== -->

                                        <button
                                            type="button"
                                            class="table-action-button btn-estado-servicio"

                                            data-id="<?php echo (int)
                                                $servicio[
                                                    'id_servicio'
                                                ]; ?>"

                                            data-activo="<?php echo
                                                $servicio['activo']
                                                    ? '1'
                                                    : '0';
                                            ?>"
                                        >

                                            <?php echo
                                                $servicio['activo']
                                                    ? 'Desactivar'
                                                    : 'Activar';
                                            ?>

                                        </button>


                                        <!-- =====================
                                             ELIMINAR
                                             ===================== -->

                                        <button
                                            type="button"
                                            class="table-action-button delete btn-eliminar-servicio"

                                            data-id="<?php echo (int)
                                                $servicio[
                                                    'id_servicio'
                                                ]; ?>"

                                            data-nombre="<?php echo htmlspecialchars(
                                                $servicio['nombre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>"
                                        >
                                            Eliminar
                                        </button>


                                    </div>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>

        </section>


    </div>

</main>

</div>


<!-- =========================================================
     MODAL DEPARTAMENTO
     ========================================================= -->

<div
    class="estructura-modal"
    id="modalDepartamento"
    hidden
>


    <div
        class="estructura-modal-overlay"
    ></div>


    <div
        class="estructura-modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalDepartamento"
    >


        <div class="estructura-modal-header">

            <div>

                <span class="modal-kicker">
                    Organización
                </span>


                <h2 id="tituloModalDepartamento">
                    Agregar departamento
                </h2>

            </div>


            <button
                type="button"
                class="modal-close"
                id="btnCerrarModalDepartamento"
                aria-label="Cerrar"
            >
                ×
            </button>

        </div>


        <form
            id="formDepartamento"
            novalidate
        >


            <div class="estructura-modal-body">


                <input
                    type="hidden"
                    id="departamentoId"
                    name="id_departamento"
                    value=""
                >


                <div class="form-group">

                    <label
                        for="departamentoNombre"
                    >
                        Nombre del departamento
                    </label>


                    <input
                        type="text"
                        id="departamentoNombre"
                        name="nombre"
                        maxlength="150"
                        autocomplete="off"
                        required
                    >

                </div>


                <div
                    id="mensajeDepartamento"
                    class="form-mensaje"
                    hidden
                ></div>


                <div class="modal-actions">

                    <button
                        type="button"
                        class="btn-secondary"
                        id="btnCancelarDepartamento"
                    >
                        Cancelar
                    </button>


                    <button
                        type="submit"
                        class="btn-primary"
                        id="btnGuardarDepartamento"
                    >
                        Guardar departamento
                    </button>

                </div>


            </div>


        </form>

    </div>

</div>


<!-- =========================================================
     MODAL SERVICIO
     ========================================================= -->

<div
    class="estructura-modal"
    id="modalServicio"
    hidden
>


    <div
        class="estructura-modal-overlay"
    ></div>


    <div
        class="estructura-modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloModalServicio"
    >


        <div class="estructura-modal-header">

            <div>

                <span class="modal-kicker">
                    Atención
                </span>


                <h2 id="tituloModalServicio">
                    Agregar servicio
                </h2>

            </div>


            <button
                type="button"
                class="modal-close"
                id="btnCerrarModalServicio"
                aria-label="Cerrar"
            >
                ×
            </button>

        </div>


        <form
            id="formServicio"
            novalidate
        >


            <div class="estructura-modal-body">


                <input
                    type="hidden"
                    id="servicioId"
                    name="id_servicio"
                    value=""
                >


                <div class="form-group">

                    <label
                        for="servicioDepartamento"
                    >
                        Departamento
                    </label>


                    <select
                        id="servicioDepartamento"
                        name="id_departamento"
                        required
                    >

                        <option value="">
                            Seleccione un departamento
                        </option>


                        <?php foreach (
                            $departamentos
                            as $departamento
                        ): ?>


                            <?php if (
                                $departamento['activo']
                            ): ?>

                                <option
                                    value="<?php echo (int)
                                        $departamento[
                                            'id_departamento'
                                        ]; ?>"
                                >

                                    <?php echo htmlspecialchars(
                                        $departamento['nombre'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>

                                </option>

                            <?php endif; ?>


                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label
                        for="servicioNombre"
                    >
                        Nombre del servicio
                    </label>


                    <input
                        type="text"
                        id="servicioNombre"
                        name="nombre"
                        maxlength="150"
                        autocomplete="off"
                        required
                    >

                </div>


                <div class="form-group">

                    <label
                        for="servicioDescripcion"
                    >
                        Descripción
                    </label>


                    <textarea
                        id="servicioDescripcion"
                        name="descripcion"
                        maxlength="500"
                        rows="4"
                    ></textarea>

                </div>


                <div
                    id="mensajeServicio"
                    class="form-mensaje"
                    hidden
                ></div>


                <div class="modal-actions">

                    <button
                        type="button"
                        class="btn-secondary"
                        id="btnCancelarServicio"
                    >
                        Cancelar
                    </button>


                    <button
                        type="submit"
                        class="btn-primary"
                        id="btnGuardarServicio"
                    >
                        Guardar servicio
                    </button>

                </div>


            </div>


        </form>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script
    src="../js/gestionar_estructura.js"
></script>


</body>

</html>
