
<?php

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';


/* =========================================================
   DATOS DEL FUNCIONARIO
   ========================================================= */

$nombreFuncionario =
    $_SESSION['nombre_funcionario']
    ?? '';

$apellidoFuncionario =
    $_SESSION['apellido_funcionario']
    ?? '';

$usuarioFuncionario =
    $_SESSION['usuario_funcionario']
    ?? '';

$rolFuncionario =
    $_SESSION['rol_funcionario']
    ?? '';


/* =========================================================
   CONFIGURACIÓN DE COMPONENTES
   ========================================================= */

$base_url =
    '/municipalidad';

$nombre_funcionario =
    trim(
        $nombreFuncionario .
        ' ' .
        $apellidoFuncionario
    );

$rol_funcionario =
    $rolFuncionario;

$pagina_actual =
    'funcionarios';


/* =========================================================
   VALIDAR ACCESO
   ========================================================= */

if (
    $rolFuncionario !==
        'superadmin' &&
    $rolFuncionario !==
        'administrador'
) {

    header(
        'Location: panel.php'
    );

    exit;
}


/* =========================================================
   VARIABLES
   ========================================================= */

$funcionarios = [];

$departamentos = [];

$errorPagina = '';

$buscar =
    trim(
        $_GET['buscar']
        ?? ''
    );

$filtroRol =
    trim(
        $_GET['rol']
        ?? ''
    );

$filtroEstado =
    trim(
        $_GET['estado']
        ?? ''
    );

$filtroDepartamento =
    trim(
        $_GET['departamento']
        ?? ''
    );


/* =========================================================
   OBTENER DATOS DEL FUNCIONARIO ACTUAL
   ========================================================= */

$idDepartamentoFuncionario = null;


try {

    $sqlFuncionarioActual = "
        SELECT
            id_funcionario,
            rol,
            id_departamento
        FROM funcionarios
        WHERE usuario = :usuario
        LIMIT 1
    ";


    $stmtFuncionarioActual =
        $conexion->prepare(
            $sqlFuncionarioActual
        );


    $stmtFuncionarioActual->execute(
        [
            ':usuario' =>
                $usuarioFuncionario
        ]
    );


    $funcionarioActual =
        $stmtFuncionarioActual->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        !$funcionarioActual
    ) {

        header(
            'Location: panel.php'
        );

        exit;
    }


    $idDepartamentoFuncionario =
        $funcionarioActual[
            'id_departamento'
        ] !== null
            ? (int)
                $funcionarioActual[
                    'id_departamento'
                ]
            : null;


} catch (
    PDOException $e
) {

    error_log(
        'Error obtener funcionario actual: ' .
        $e->getMessage()
    );


    $errorPagina =
        'No fue posible validar los datos del funcionario actual.';
}


/* =========================================================
   OBTENER DEPARTAMENTOS
   ========================================================= */

try {

    if (
        $rolFuncionario ===
        'superadmin'
    ) {

        $sqlDepartamentos = "
            SELECT
                id_departamento,
                nombre,
                activo
            FROM departamentos
            WHERE activo = TRUE
            ORDER BY nombre ASC
        ";

        $stmtDepartamentos =
            $conexion->prepare(
                $sqlDepartamentos
            );

        $stmtDepartamentos->execute();

    } else {

        $sqlDepartamentos = "
            SELECT
                id_departamento,
                nombre,
                activo
            FROM departamentos
            WHERE id_departamento =
                  :id_departamento
              AND activo = TRUE
            ORDER BY nombre ASC
        ";

        $stmtDepartamentos =
            $conexion->prepare(
                $sqlDepartamentos
            );

        $stmtDepartamentos->execute(
            [
                ':id_departamento' =>
                    $idDepartamentoFuncionario
            ]
        );
    }


    $departamentos =
        $stmtDepartamentos->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    PDOException $e
) {

    error_log(
        'Error obtener departamentos: ' .
        $e->getMessage()
    );


    $errorPagina =
        'No fue posible cargar los departamentos.';
}


/* =========================================================
   OBTENER FUNCIONARIOS
   ========================================================= */

try {

    $sql = "
        SELECT
            f.id_funcionario,
            f.nombre,
            f.apellido,
            f.correo,
            f.usuario,
            f.rol,
            f.activo,
            f.id_departamento,
            d.nombre AS departamento
        FROM funcionarios f
        LEFT JOIN departamentos d
            ON d.id_departamento =
               f.id_departamento
        WHERE 1 = 1
    ";

    $parametros = [];


    /* =====================================================
       LIMITAR POR DEPARTAMENTO
       ===================================================== */

    if (
        $rolFuncionario ===
        'administrador'
    ) {

        $sql .= "
            AND f.id_departamento =
                :id_departamento_actual
        ";

        $parametros[
            ':id_departamento_actual'
        ] =
            $idDepartamentoFuncionario;
    }


    /* =====================================================
       FILTRO DE BÚSQUEDA
       ===================================================== */

    if (
        $buscar !== ''
    ) {

        $sql .= "
            AND (
                f.nombre ILIKE :buscar
                OR f.apellido ILIKE :buscar
                OR f.correo ILIKE :buscar
                OR f.usuario ILIKE :buscar
            )
        ";

        $parametros[':buscar'] =
            '%' .
            $buscar .
            '%';
    }


    /* =====================================================
       FILTRO POR ROL
       ===================================================== */

    if (
        $filtroRol !== ''
    ) {

        /*
         * Un administrador de departamento
         * no puede filtrar ni visualizar superadmins.
         */
        if (
            $rolFuncionario ===
                'administrador' &&
            $filtroRol ===
                'superadmin'
        ) {

            $filtroRol = '';
        }

        if (
            $filtroRol !== ''
        ) {

            $sql .= "
                AND f.rol = :rol
            ";

            $parametros[':rol'] =
                $filtroRol;
        }
    }


    /* =====================================================
       FILTRO POR DEPARTAMENTO
       ===================================================== */

    if (
        $filtroDepartamento !== ''
    ) {

        if (
            ctype_digit(
                $filtroDepartamento
            )
        ) {

            $idDepartamentoFiltro =
                (int)
                $filtroDepartamento;


            /*
             * Si es administrador solamente
             * puede consultar su departamento.
             */
            if (
                $rolFuncionario ===
                    'administrador' &&
                $idDepartamentoFiltro ===
                    $idDepartamentoFuncionario
            ) {

                $sql .= "
                    AND f.id_departamento =
                        :id_departamento_filtro
                ";

                $parametros[
                    ':id_departamento_filtro'
                ] =
                    $idDepartamentoFiltro;

            } elseif (
                $rolFuncionario ===
                'superadmin'
            ) {

                $sql .= "
                    AND f.id_departamento =
                        :id_departamento_filtro
                ";

                $parametros[
                    ':id_departamento_filtro'
                ] =
                    $idDepartamentoFiltro;
            }
        }
    }


    /* =====================================================
       FILTRO POR ESTADO
       ===================================================== */

    if (
        $filtroEstado ===
        'activo'
    ) {

        $sql .= "
            AND f.activo = TRUE
        ";

    } elseif (
        $filtroEstado ===
        'inactivo'
    ) {

        $sql .= "
            AND f.activo = FALSE
        ";
    }


    /* =====================================================
       ORDEN DE RESULTADOS
       ===================================================== */

    $sql .= "
        ORDER BY
            f.activo DESC,
            d.nombre ASC,
            f.apellido ASC,
            f.nombre ASC
    ";


    /* =====================================================
       EJECUTAR CONSULTA
       ===================================================== */

    $stmt =
        $conexion->prepare(
            $sql
        );

    $stmt->execute(
        $parametros
    );

    $funcionarios =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (
    PDOException $e
) {

    error_log(
        'Error gestionar funcionarios: ' .
        $e->getMessage()
    );

    $errorPagina =
        'No fue posible cargar los funcionarios.';
}


/* =========================================================
   CONTADORES
   ========================================================= */

$totalFuncionarios =
    count(
        $funcionarios
    );

$totalActivos = 0;

$totalInactivos = 0;


foreach (
    $funcionarios
    as $funcionario
) {

    if (
        $funcionario['activo']
    ) {

        $totalActivos++;

    } else {

        $totalInactivos++;
    }
}

?>


<!DOCTYPE html>

<html
    lang="es"
>


<head>

    <meta
        charset="UTF-8"
    >

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Funcionarios |
        Municipalidad
    </title>


    <link
        rel="stylesheet"
        href="../css/compartidos/general.css"
    >

    <link
        rel="stylesheet"
        href="../css/compartidos/navbar_funcionario.css"
    >

    <link
        rel="stylesheet"
        href="../css/compartidos/sidebar_funcionario.css"
    >

    <link
        rel="stylesheet"
        href="../css/compartidos/footer_funcionario.css"
    >

    <link
        rel="stylesheet"
        href="../css/funcionarios/funcionarios.css"
    >

</head>


<body>


<!-- =========================================================
     NAVBAR FUNCIONARIO
     ========================================================= -->

<?php

require_once
    '../componentes/navbar_funcionario.php';

?>


<!-- =========================================================
     ESTRUCTURA ADMINISTRATIVA
     ========================================================= -->

<div class="admin-layout">


    <!-- =====================================================
         SIDEBAR FUNCIONARIO
         ===================================================== -->

    <?php

    require_once
        '../componentes/sidebar_funcionario.php';

    ?>


    <!-- =====================================================
         CONTENIDO PRINCIPAL
         ===================================================== -->

    <main class="admin-content">


        <!-- =================================================
             ENCABEZADO
             ================================================= -->

        <section class="page-header">

            <div>

                <span class="page-kicker">
                    Administración del sistema
                </span>


                <h1>
                    Funcionarios
                </h1>


                <p>
                    Administra los funcionarios que tienen
                    acceso al sistema municipal.
                </p>

            </div>


            <div class="page-header-actions">

                <button
                    type="button"
                    class="primary-button"
                    id="btnNuevoFuncionario"
                >

                    +

                    <span>
                        Nuevo funcionario
                    </span>

                </button>

            </div>

        </section>


        <!-- =================================================
             RESUMEN
             ================================================= -->

        <section class="summary-cards">


            <article class="summary-card">

                <span class="summary-card-icon">
                    👥
                </span>


                <div>

                    <span class="summary-card-label">
                        Funcionarios
                    </span>


                    <strong>
                        <?= $totalFuncionarios ?>
                    </strong>

                </div>

            </article>


            <article class="summary-card">

                <span class="summary-card-icon">
                    ✓
                </span>


                <div>

                    <span class="summary-card-label">
                        Activos
                    </span>


                    <strong>
                        <?= $totalActivos ?>
                    </strong>

                </div>

            </article>


            <article class="summary-card">

                <span class="summary-card-icon">
                    —
                </span>


                <div>

                    <span class="summary-card-label">
                        Inactivos
                    </span>


                    <strong>
                        <?= $totalInactivos ?>
                    </strong>

                </div>

            </article>


        </section>


        <!-- =================================================
             MENSAJE DE ERROR
             ================================================= -->

        <?php if (
            $errorPagina !== ''
        ): ?>

            <div
                class="page-alert error"
            >

                <?= htmlspecialchars(
                    $errorPagina,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FILTROS
             ================================================= -->

        <section class="filters-card">

            <form
                method="GET"
                action="gestionar_funcionarios.php"
                class="filters-form"
            >


                <div class="filter-group">

                    <label
                        for="buscar"
                    >
                        Buscar
                    </label>


                    <input
                        type="search"
                        id="buscar"
                        name="buscar"
                        value="<?= htmlspecialchars(
                            $buscar,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        placeholder="Nombre, apellido, correo o usuario"
                    >

                </div>


                <div class="filter-group">

                    <label
                        for="rol"
                    >
                        Rol
                    </label>


                    <select
                        id="rol"
                        name="rol"
                    >

                        <option
                            value=""
                        >
                            Todos los roles
                        </option>


                        <?php if (
                            $rolFuncionario ===
                            'superadmin'
                        ): ?>

                            <option
                                value="superadmin"
                                <?= $filtroRol ===
                                    'superadmin'
                                        ? 'selected'
                                        : '' ?>
                            >
                                Superadmin
                            </option>

                        <?php endif; ?>


                        <option
                            value="administrador"
                            <?= $filtroRol ===
                                'administrador'
                                    ? 'selected'
                                    : '' ?>
                        >
                            Administrador
                        </option>


                        <option
                            value="funcionario"
                            <?= $filtroRol ===
                                'funcionario'
                                    ? 'selected'
                                    : '' ?>
                        >
                            Funcionario
                        </option>

                    </select>

                </div>


                <div class="filter-group">

                    <label
                        for="departamento"
                    >
                        Departamento
                    </label>


                    <select
                        id="departamento"
                        name="departamento"
                    >

                        <option
                            value=""
                        >
                            Todos
                        </option>


                        <?php foreach (
                            $departamentos
                            as $departamento
                        ): ?>

                            <option
                                value="<?= (int)
                                    $departamento[
                                        'id_departamento'
                                    ] ?>"
                                <?= $filtroDepartamento ===
                                    (string)
                                    $departamento[
                                        'id_departamento'
                                    ]
                                        ? 'selected'
                                        : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $departamento['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label
                        for="estado"
                    >
                        Estado
                    </label>


                    <select
                        id="estado"
                        name="estado"
                    >

                        <option
                            value=""
                        >
                            Todos
                        </option>


                        <option
                            value="activo"
                            <?= $filtroEstado ===
                                'activo'
                                    ? 'selected'
                                    : '' ?>
                        >
                            Activos
                        </option>


                        <option
                            value="inactivo"
                            <?= $filtroEstado ===
                                'inactivo'
                                    ? 'selected'
                                    : '' ?>
                        >
                            Inactivos
                        </option>

                    </select>

                </div>


                <div class="filter-actions">

                    <button
                        type="submit"
                        class="secondary-button"
                    >
                        Buscar
                    </button>


                    <a
                        href="gestionar_funcionarios.php"
                        class="secondary-button light"
                    >
                        Limpiar
                    </a>

                </div>


            </form>

        </section>


        <!-- =================================================
             LISTADO DE FUNCIONARIOS
             ================================================= -->

        <section class="content-card">


            <div class="content-card-header">

                <div>

                    <span class="content-card-kicker">
                        Usuarios internos
                    </span>


                    <h2>
                        Funcionarios registrados
                    </h2>

                </div>


                <span class="results-count">

                    <?= $totalFuncionarios ?>

                    resultado<?= $totalFuncionarios === 1
                        ? ''
                        : 's' ?>

                </span>

            </div>


            <?php if (
                count(
                    $funcionarios
                ) > 0
            ): ?>


                <div class="table-container">

                    <table class="data-table">


                        <thead>

                            <tr>

                                <th>
                                    Funcionario
                                </th>


                                <th>
                                    Usuario
                                </th>


                                <th>
                                    Departamento
                                </th>


                                <th>
                                    Rol
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


                            <?php foreach (
                                $funcionarios
                                as $funcionario
                            ): ?>


                                <tr>


                                    <td>

                                        <div
                                            class="person-cell"
                                        >

                                            <span
                                                class="person-avatar"
                                            >

                                                <?= htmlspecialchars(
                                                    strtoupper(
                                                        mb_substr(
                                                            $funcionario['nombre'],
                                                            0,
                                                            1,
                                                            'UTF-8'
                                                        ) .
                                                        mb_substr(
                                                            $funcionario['apellido'],
                                                            0,
                                                            1,
                                                            'UTF-8'
                                                        )
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </span>


                                            <div>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $funcionario['nombre'] .
                                                        ' ' .
                                                        $funcionario['apellido'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </strong>


                                                <small>

                                                    ID #

                                                    <?= (int)
                                                        $funcionario[
                                                            'id_funcionario'
                                                        ] ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $funcionario['usuario'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            !empty(
                                                $funcionario[
                                                    'departamento'
                                                ]
                                            )
                                        ): ?>

                                            <?= htmlspecialchars(
                                                $funcionario['departamento'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        <?php else: ?>

                                            <span>
                                                Sin departamento
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <span
                                            class="role-badge
                                            <?= $funcionario['rol'] ===
                                                'superadmin'
                                                    ? 'administrator'
                                                    : (
                                                        $funcionario['rol'] ===
                                                        'administrador'
                                                            ? 'administrator'
                                                            : 'employee'
                                                    )
                                            ?>"
                                        >

                                            <?= $funcionario['rol'] ===
                                                'superadmin'
                                                    ? 'Superadmin'
                                                    : (
                                                        $funcionario['rol'] ===
                                                        'administrador'
                                                            ? 'Administrador'
                                                            : 'Funcionario'
                                                    )
                                            ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php if (
                                            $funcionario['activo']
                                        ): ?>

                                            <span
                                                class="status-badge active"
                                            >
                                                Activo
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="status-badge inactive"
                                            >
                                                Inactivo
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div
                                            class="table-actions"
                                        >


                                            <button
                                                type="button"
                                                class="table-action-button edit"
                                                data-id="<?= (int)
                                                    $funcionario[
                                                        'id_funcionario'
                                                    ] ?>"
                                                data-action="editar"
                                            >
                                                Editar
                                            </button>


                                            <button
                                                type="button"
                                                class="table-action-button
                                                <?= $funcionario['activo']
                                                    ? 'disable'
                                                    : 'enable' ?>"
                                                data-id="<?= (int)
                                                    $funcionario[
                                                        'id_funcionario'
                                                    ] ?>"
                                                data-action="estado"
                                                data-estado="<?= $funcionario['activo']
                                                    ? 'activo'
                                                    : 'inactivo' ?>"
                                            >

                                                <?= $funcionario['activo']
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>

                                            </button>


                                            <button
                                                type="button"
                                                class="table-action-button delete"
                                                data-id="<?= (int)
                                                    $funcionario[
                                                        'id_funcionario'
                                                    ] ?>"
                                                data-action="eliminar"
                                            >
                                                Eliminar
                                            </button>


                                        </div>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="empty-state">

                    <span class="empty-state-icon">
                        👥
                    </span>


                    <h3>
                        No se encontraron funcionarios
                    </h3>


                    <p>
                        No existen funcionarios que coincidan
                        con los filtros seleccionados.
                    </p>

                </div>


            <?php endif; ?>


        </section>


    </main>

</div>


<!-- =========================================================
     FOOTER FUNCIONARIO
     ========================================================= -->

<?php

require_once
    '../componentes/footer_funcionario.php';

?>


<!-- =========================================================
     POPUP CREAR / EDITAR FUNCIONARIO
     ========================================================= -->

<div
    class="modal-overlay"
    id="modalFuncionario"
    hidden
>

    <div
        class="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalFuncionarioTitulo"
    >


        <div class="modal-header">

            <div>

                <span class="modal-kicker">
                    Administración
                </span>


                <h2
                    id="modalFuncionarioTitulo"
                >
                    Nuevo funcionario
                </h2>

            </div>


            <button
                type="button"
                class="modal-close"
                id="btnCerrarModalFuncionario"
                aria-label="Cerrar"
            >
                ×
            </button>

        </div>


        <form
            id="formFuncionario"
            autocomplete="off"
        >


            <input
                type="hidden"
                id="funcionarioId"
                name="id_funcionario"
                value=""
            >


            <div class="form-grid">


                <div class="form-group">

                    <label
                        for="funcionarioNombre"
                    >
                        Nombre
                    </label>


                    <input
                        type="text"
                        id="funcionarioNombre"
                        name="nombre"
                        maxlength="100"
                        required
                    >


                    <small
                        class="form-error"
                        id="errorFuncionarioNombre"
                    ></small>

                </div>


                <div class="form-group">

                    <label
                        for="funcionarioApellido"
                    >
                        Apellido
                    </label>


                    <input
                        type="text"
                        id="funcionarioApellido"
                        name="apellido"
                        maxlength="100"
                        required
                    >


                    <small
                        class="form-error"
                        id="errorFuncionarioApellido"
                    ></small>

                </div>


                <div class="form-group">

                    <label
                        for="funcionarioCorreo"
                    >
                        Correo electrónico
                    </label>


                    <input
                        type="email"
                        id="funcionarioCorreo"
                        name="correo"
                        maxlength="254"
                        required
                        autocomplete="email"
                    >


                    <small
                        class="form-error"
                        id="errorFuncionarioCorreo"
                    ></small>

                </div>


                <div class="form-group">

                    <label
                        for="funcionarioUsuario"
                    >
                        Usuario
                    </label>


                    <input
                        type="text"
                        id="funcionarioUsuario"
                        name="usuario"
                        maxlength="80"
                        required
                    >


                    <small
                        class="form-error"
                        id="errorFuncionarioUsuario"
                    ></small>

                </div>


                <div class="form-group">

                    <label
                        for="funcionarioRol"
                    >
                        Rol
                    </label>


                    <select
                        id="funcionarioRol"
                        name="rol"
                        required
                    >

                        <option
                            value="funcionario"
                        >
                            Funcionario
                        </option>


                        <?php if (
                            $rolFuncionario ===
                            'superadmin'
                        ): ?>

                            <option
                                value="administrador"
                            >
                                Administrador
                            </option>


                            <option
                                value="superadmin"
                            >
                                Superadmin
                            </option>

                        <?php endif; ?>


                    </select>

                </div>


                <div class="form-group">

                    <label
                        for="funcionarioDepartamento"
                    >
                        Departamento
                    </label>


                    <select
                        id="funcionarioDepartamento"
                        name="id_departamento"
                        <?= $rolFuncionario ===
                            'administrador'
                                ? 'disabled'
                                : '' ?>
                    >

                        <option
                            value=""
                        >
                            Seleccione un departamento
                        </option>


                        <?php foreach (
                            $departamentos
                            as $departamento
                        ): ?>

                            <option
                                value="<?= (int)
                                    $departamento[
                                        'id_departamento'
                                    ] ?>"
                            >

                                <?= htmlspecialchars(
                                    $departamento['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <?php if (
                        $rolFuncionario ===
                        'administrador'
                    ): ?>

                        <input
                            type="hidden"
                            id="funcionarioDepartamentoHidden"
                            name="id_departamento"
                            value="<?= $idDepartamentoFuncionario !== null
                                ? (int)
                                    $idDepartamentoFuncionario
                                : '' ?>"
                        >

                    <?php endif; ?>


                    <small
                        class="form-error"
                        id="errorFuncionarioDepartamento"
                    ></small>

                </div>


                <div
                    class="form-group full-width"
                >

                    <label
                        for="funcionarioPassword"
                    >
                        Contraseña
                    </label>


                    <input
                        type="password"
                        id="funcionarioPassword"
                        name="password"
                        minlength="6"
                        autocomplete="new-password"
                    >


                    <small
                        class="form-help"
                    >
                        En un funcionario nuevo es obligatoria.
                        Al editar, déjala vacía para conservar
                        la contraseña actual.
                    </small>


                    <small
                        class="form-error"
                        id="errorFuncionarioPassword"
                    ></small>

                </div>


                <div
                    class="form-group full-width"
                >

                    <label
                        class="checkbox-field"
                    >

                        <input
                            type="checkbox"
                            id="funcionarioActivo"
                            name="activo"
                            checked
                        >


                        <span>
                            Funcionario activo
                        </span>

                    </label>

                </div>


                <?php if (
                    $rolFuncionario === 'superadmin'
                ): ?>

                    <!-- =================================================
                         PERMISO PARA ADMINISTRAR LA PÁGINA PÚBLICA
                         Exclusivo del superadmin
                         ================================================= -->

                    <div class="form-group full-width">

                        <label class="checkbox-field">

                            <input
                                type="checkbox"
                                id="funcionarioPuedeAdministrarWeb"
                                name="puede_administrar_web"
                                value="1"
                            >

                            <span>
                                Puede administrar la página pública del CESFAM
                            </span>

                        </label>

                        <small class="form-help">
                            Permite gestionar noticias, slider, banners,
                            tutoriales e información institucional.
                            Este permiso es independiente del acceso
                            a la toma de horas.
                        </small>

                    </div>

                <?php endif; ?>


            </div>


            <div
                class="modal-actions"
            >

                <button
                    type="button"
                    class="secondary-button"
                    id="btnCancelarFuncionario"
                >
                    Cancelar
                </button>


                <button
                    type="submit"
                    class="primary-button"
                >
                    Guardar funcionario
                </button>

            </div>


            <div
                class="form-notification"
                id="notificacionFuncionario"
                hidden
            ></div>


        </form>

    </div>

</div>


<script
    src="../js/gestionar_funcionarios.js"
></script>


</body>

</html>
