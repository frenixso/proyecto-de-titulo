<?php

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';

/* =========================================================
   DATOS DEL FUNCIONARIO ACTUAL
   ========================================================= */

$nombreFuncionario = $_SESSION['nombre_funcionario'] ?? '';
$apellidoFuncionario = $_SESSION['apellido_funcionario'] ?? '';
$usuarioFuncionario = $_SESSION['usuario_funcionario'] ?? '';
$rolFuncionario = $_SESSION['rol_funcionario'] ?? '';

$base_url = getenv('APP_BASE_URL') !== false ? rtrim(getenv('APP_BASE_URL'), '/') : '/sistema toma de horas cesfam';
$nombre_funcionario = trim($nombreFuncionario . ' ' . $apellidoFuncionario);
$rol_funcionario = $rolFuncionario;

/* =========================================================
   FUNCIONES AUXILIARES
   ========================================================= */

function escaparGestionPersonal($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function valorBooleanoActivo($valor): bool
{
    return (
        $valor === true ||
        $valor === 1 ||
        $valor === '1' ||
        $valor === 't' ||
        $valor === 'true'
    );
}

function etiquetaTipoAcceso($tipo): string
{
    switch ($tipo) {
        case 'superadmin':
            return 'Superadmin';

        case 'administrador':
            return 'Administrador de reservas';

        case 'administrador_web':
            return 'Administrador web';

        case 'funcionario':
            return 'Funcionario';

        default:
            return 'Sin perfil asignado';
    }
}

/* =========================================================
   VALIDAR ACCESO
   =========================================================
   SUPERADMIN:
   - Puede gestionar cuentas del sistema.
   - Puede gestionar profesionales.

   ADMINISTRADOR:
   - Puede gestionar profesionales.
   - No puede gestionar cuentas del sistema.

   ADMINISTRADOR WEB:
   - No puede acceder a esta sección.
   ========================================================= */

if (
    !in_array(
        $rolFuncionario,
        ['superadmin', 'administrador'],
        true
    )
) {
    header('Location: panel.php');
    exit;
}

/* =========================================================
   VALIDAR CUENTA ACTUAL EN LA BASE DE DATOS
   ========================================================= */

try {

    $sqlFuncionarioActual = "
        SELECT
            id_funcionario,
            rol,
            activo
        FROM funcionarios
        WHERE usuario = :usuario
        LIMIT 1
    ";

    $stmtFuncionarioActual =
        $conexion->prepare($sqlFuncionarioActual);

    $stmtFuncionarioActual->execute([
        ':usuario' => $usuarioFuncionario
    ]);

    $funcionarioActual =
        $stmtFuncionarioActual->fetch(PDO::FETCH_ASSOC);

    if (
        !$funcionarioActual ||
        !valorBooleanoActivo($funcionarioActual['activo']) ||
        $funcionarioActual['rol'] !== $rolFuncionario
    ) {
        header('Location: panel.php');
        exit;
    }

} catch (PDOException $e) {

    error_log(
        'Error validar funcionario actual: ' .
        $e->getMessage()
    );

    header('Location: panel.php');
    exit;
}

/* =========================================================
   VARIABLES Y VISTAS
   ========================================================= */

$funcionarios = [];
$profesionales = [];

$estamentosProfesionales = [
    'Odontología'
];

$errorPagina = '';
$errorProfesionales = '';

$vistaActual = $_GET['vista'] ?? 'funcionarios';

/*
 * El administrador de reservas solamente puede acceder
 * a la gestión de profesionales.
 */

if ($rolFuncionario === 'administrador') {

    $vistaActual = 'profesionales';

} elseif (
    !in_array(
        $vistaActual,
        ['funcionarios', 'profesionales'],
        true
    )
) {

    $vistaActual = 'funcionarios';
}

/*
 * Identificador usado por el menú lateral.
 */

$pagina_actual = (
    $vistaActual === 'profesionales'
        ? 'personal_atencion'
        : 'funcionarios'
);

/* =========================================================
   FILTROS
   ========================================================= */

$buscar = trim($_GET['buscar'] ?? '');
$filtroRol = trim($_GET['rol'] ?? '');
$filtroEstado = trim($_GET['estado'] ?? '');

$buscarProfesional = trim($_GET['buscar_profesional'] ?? '');
$filtroNodoProfesional = trim($_GET['nodo_profesional'] ?? '');
$filtroEstadoProfesional = trim($_GET['estado_profesional'] ?? '');

/* =========================================================
   OBTENER CUENTAS DEL SISTEMA
   =========================================================
   Exclusivo para superadmin.

   Las cuentas no están vinculadas a departamentos.

   El tipo de acceso se determina mediante:
   - rol = superadmin.
   - rol = administrador.
   - rol = funcionario y puede_administrar_web = TRUE.
   - rol = funcionario y puede_administrar_web = FALSE.
   ========================================================= */

if ($rolFuncionario === 'superadmin') {

    try {

        $expresionTipoAcceso = "
            CASE
                WHEN f.rol = 'superadmin'
                    THEN 'superadmin'

                WHEN f.rol = 'administrador'
                    THEN 'administrador'

                WHEN f.rol = 'funcionario'
                     AND COALESCE(f.puede_administrar_web, FALSE) = TRUE
                    THEN 'administrador_web'

                ELSE 'funcionario'
            END
        ";

        $sql = "
            SELECT
                f.id_funcionario,
                f.nombre,
                f.apellido,
                f.correo,
                f.usuario,
                f.rol,
                f.puede_administrar_web,
                f.activo,
                {$expresionTipoAcceso} AS tipo_acceso
            FROM funcionarios f
            WHERE 1 = 1
        ";

        $parametros = [];

        /* =============================================
           BÚSQUEDA
           ============================================= */

        if ($buscar !== '') {

            $sql .= "
                AND (
                    f.nombre ILIKE :buscar_nombre
                    OR f.apellido ILIKE :buscar_apellido
                    OR f.correo ILIKE :buscar_correo
                    OR f.usuario ILIKE :buscar_usuario
                )
            ";

            $terminoBuscar = '%' . $buscar . '%';

            $parametros[':buscar_nombre'] = $terminoBuscar;
            $parametros[':buscar_apellido'] = $terminoBuscar;
            $parametros[':buscar_correo'] = $terminoBuscar;
            $parametros[':buscar_usuario'] = $terminoBuscar;
        }

        /* =============================================
           FILTRO POR TIPO DE ACCESO
           ============================================= */

        $tiposAccesoPermitidos = [
            'superadmin',
            'administrador',
            'administrador_web',
            'funcionario'
        ];

        if (
            in_array(
                $filtroRol,
                $tiposAccesoPermitidos,
                true
            )
        ) {

            $sql .= "
                AND ({$expresionTipoAcceso}) = :tipo_acceso
            ";

            $parametros[':tipo_acceso'] = $filtroRol;
        }

        /* =============================================
           FILTRO POR ESTADO
           ============================================= */

        if ($filtroEstado === 'activo') {

            $sql .= "
                AND f.activo = TRUE
            ";

        } elseif ($filtroEstado === 'inactivo') {

            $sql .= "
                AND f.activo = FALSE
            ";
        }

        /* =============================================
           ORDEN
           ============================================= */

        $sql .= "
            ORDER BY
                f.activo DESC,
                f.apellido ASC,
                f.nombre ASC
        ";

        $stmtFuncionarios =
            $conexion->prepare($sql);

        $stmtFuncionarios->execute($parametros);

        $funcionarios =
            $stmtFuncionarios->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log(
            'Error gestionar funcionarios: ' .
            $e->getMessage()
        );

        $errorPagina =
            'No fue posible cargar las cuentas del sistema.';
    }
}

/* =========================================================
   OBTENER ESTAMENTOS YA UTILIZADOS
   ========================================================= */

try {

    $stmtEstamentos = $conexion->query("
        SELECT DISTINCT estamento
        FROM profesionales
        WHERE estamento IS NOT NULL
          AND BTRIM(estamento) <> ''
        ORDER BY estamento ASC
    ");

    foreach (
        $stmtEstamentos->fetchAll(PDO::FETCH_COLUMN)
        as $estamento
    ) {

        if (
            !in_array(
                $estamento,
                $estamentosProfesionales,
                true
            )
        ) {

            $estamentosProfesionales[] =
                $estamento;
        }
    }

} catch (PDOException $e) {

    error_log(
        'Error obtener estamentos de profesionales: ' .
        $e->getMessage()
    );

    // Se conserva Odontología como opción inicial.
}

/* =========================================================
   OBTENER PROFESIONALES SIN ACCESO AL SISTEMA
   ========================================================= */

try {

    $sqlProfesionales = "
        SELECT
            id_profesional,
            nombre,
            apellido,
            rut,
            estamento,
            nodo,
            activo
        FROM profesionales
        WHERE 1 = 1
    ";

    $parametrosProfesionales = [];

    /* =============================================
       BÚSQUEDA
       ============================================= */

    if ($buscarProfesional !== '') {

        $sqlProfesionales .= "
            AND (
                nombre ILIKE :buscar_nombre_profesional
                OR apellido ILIKE :buscar_apellido_profesional
                OR rut ILIKE :buscar_rut_profesional
                OR estamento ILIKE :buscar_estamento_profesional
            )
        ";

        $terminoBuscarProfesional =
            '%' . $buscarProfesional . '%';

        $parametrosProfesionales[':buscar_nombre_profesional'] =
            $terminoBuscarProfesional;

        $parametrosProfesionales[':buscar_apellido_profesional'] =
            $terminoBuscarProfesional;

        $parametrosProfesionales[':buscar_rut_profesional'] =
            $terminoBuscarProfesional;

        $parametrosProfesionales[':buscar_estamento_profesional'] =
            $terminoBuscarProfesional;
    }

    /* =============================================
       FILTRO POR NODO
       ============================================= */

    if (
        in_array(
            $filtroNodoProfesional,
            ['CESFAM', 'CECOSF'],
            true
        )
    ) {

        $sqlProfesionales .= "
            AND nodo = :nodo_profesional
        ";

        $parametrosProfesionales[':nodo_profesional'] =
            $filtroNodoProfesional;
    }

    /* =============================================
       FILTRO POR ESTADO
       ============================================= */

    if ($filtroEstadoProfesional === 'activo') {

        $sqlProfesionales .= "
            AND activo = TRUE
        ";

    } elseif ($filtroEstadoProfesional === 'inactivo') {

        $sqlProfesionales .= "
            AND activo = FALSE
        ";
    }

    /* =============================================
       ORDEN
       ============================================= */

    $sqlProfesionales .= "
        ORDER BY
            activo DESC,
            apellido ASC,
            nombre ASC
    ";

    $stmtProfesionales =
        $conexion->prepare($sqlProfesionales);

    $stmtProfesionales->execute(
        $parametrosProfesionales
    );

    $profesionales =
        $stmtProfesionales->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        'Error obtener profesionales: ' .
        $e->getMessage()
    );

    $errorProfesionales =
        'No fue posible cargar los profesionales. Comprueba que exista la tabla profesionales.';
}

/* =========================================================
   CONTADORES DE CUENTAS DEL SISTEMA
   ========================================================= */

$totalFuncionarios = count($funcionarios);

$totalActivos = 0;
$totalInactivos = 0;

foreach ($funcionarios as $funcionario) {

    if (
        valorBooleanoActivo($funcionario['activo'])
    ) {

        $totalActivos++;

    } else {

        $totalInactivos++;
    }
}

/* =========================================================
   CONTADORES DE PROFESIONALES
   ========================================================= */

$totalProfesionales = count($profesionales);

$totalProfesionalesActivos = 0;
$totalProfesionalesInactivos = 0;

foreach ($profesionales as $profesional) {

    if (
        valorBooleanoActivo($profesional['activo'])
    ) {

        $totalProfesionalesActivos++;

    } else {

        $totalProfesionalesInactivos++;
    }
}

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
        Gestión de personal | CESFAM Eduardo Raggio
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

    <link
        rel="stylesheet"
        href="../css/funcionarios/gestionar_profesionales.css"
    >

</head>

<body>


<!-- =========================================================
     NAVBAR
     ========================================================= -->

<?php

require_once '../componentes/navbar_funcionario.php';

?>


<div class="admin-layout">


    <!-- =====================================================
         SIDEBAR
         ===================================================== -->

    <?php

    require_once '../componentes/sidebar_funcionario.php';

    ?>


    <!-- =====================================================
         CONTENIDO PRINCIPAL
         ===================================================== -->

    <main
        class="admin-content"
        id="gestionPersonal"
        data-vista-inicial="<?= escaparGestionPersonal($vistaActual) ?>"
    >


        <!-- =================================================
             ENCABEZADO
             ================================================= -->

        <section class="page-header">

            <div>

                <span class="page-kicker">
                    Administración del CESFAM
                </span>

                <h1>
                    Gestión de personal
                </h1>

                <p>

                    <?php if ($rolFuncionario === 'superadmin'): ?>

                        Administra las cuentas que utilizan el sistema
                        y los profesionales que atenderán las horas
                        de los pacientes.

                    <?php else: ?>

                        Administra los profesionales que atenderán
                        las horas de los pacientes.

                    <?php endif; ?>

                </p>

            </div>


            <div class="page-header-actions">

                <?php if ($rolFuncionario === 'superadmin'): ?>

                    <button
                        type="button"
                        class="primary-button"
                        id="btnNuevoFuncionario"
                        <?= $vistaActual !== 'funcionarios' ? 'hidden' : '' ?>
                    >

                        +

                        <span>
                            Nueva cuenta
                        </span>

                    </button>

                <?php endif; ?>


                <button
                    type="button"
                    class="primary-button"
                    id="btnNuevoProfesional"
                    <?= $vistaActual !== 'profesionales' ? 'hidden' : '' ?>
                >

                    +

                    <span>
                        Nuevo profesional
                    </span>

                </button>

            </div>

        </section>


        <!-- =================================================
             PESTAÑAS DE GESTIÓN
             ================================================= -->

        <?php if ($rolFuncionario === 'superadmin'): ?>

            <nav
                class="management-tabs"
                aria-label="Secciones de gestión"
            >

                <button
                    type="button"
                    class="management-tab <?= $vistaActual === 'funcionarios' ? 'active' : '' ?>"
                    data-vista="funcionarios"
                    aria-selected="<?= $vistaActual === 'funcionarios' ? 'true' : 'false' ?>"
                >

                    <span class="management-tab-icon">
                        👤
                    </span>

                    Funcionarios del sistema

                </button>


                <button
                    type="button"
                    class="management-tab <?= $vistaActual === 'profesionales' ? 'active' : '' ?>"
                    data-vista="profesionales"
                    aria-selected="<?= $vistaActual === 'profesionales' ? 'true' : 'false' ?>"
                >

                    <span class="management-tab-icon">
                        🩺
                    </span>

                    Profesionales de atención

                </button>

            </nav>

        <?php endif; ?>


        <!-- =================================================
             VISTA: CUENTAS DEL SISTEMA
             =================================================
             Esta sección solamente se genera para superadmin.
             ================================================= -->

        <?php if ($rolFuncionario === 'superadmin'): ?>

            <section
                id="seccionFuncionarios"
                class="management-section"
                <?= $vistaActual !== 'funcionarios' ? 'hidden' : '' ?>
            >


                <!-- RESUMEN -->

                <section class="summary-cards">

                    <article class="summary-card">

                        <span class="summary-card-icon">
                            👥
                        </span>

                        <div>

                            <span class="summary-card-label">
                                Cuentas del sistema
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
                                Activas
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
                                Inactivas
                            </span>

                            <strong>
                                <?= $totalInactivos ?>
                            </strong>

                        </div>

                    </article>

                </section>


                <!-- ERROR -->

                <?php if ($errorPagina !== ''): ?>

                    <div class="page-alert error">

                        <?= escaparGestionPersonal($errorPagina) ?>

                    </div>

                <?php endif; ?>


                <!-- FILTROS -->

                <section class="filters-card">

                    <form
                        method="GET"
                        action="gestionar_funcionarios.php"
                        class="filters-form"
                    >

                        <input
                            type="hidden"
                            name="vista"
                            value="funcionarios"
                        >


                        <div class="filter-group">

                            <label for="buscar">
                                Buscar
                            </label>

                            <input
                                type="search"
                                id="buscar"
                                name="buscar"
                                value="<?= escaparGestionPersonal($buscar) ?>"
                                placeholder="Nombre, apellido, correo o usuario"
                            >

                        </div>


                        <div class="filter-group">

                            <label for="rol">
                                Tipo de acceso
                            </label>

                            <select
                                id="rol"
                                name="rol"
                            >

                                <option value="">
                                    Todos los accesos
                                </option>

                                <option
                                    value="superadmin"
                                    <?= $filtroRol === 'superadmin' ? 'selected' : '' ?>
                                >
                                    Superadmin
                                </option>

                                <option
                                    value="administrador"
                                    <?= $filtroRol === 'administrador' ? 'selected' : '' ?>
                                >
                                    Administrador de reservas
                                </option>

                                <option
                                    value="administrador_web"
                                    <?= $filtroRol === 'administrador_web' ? 'selected' : '' ?>
                                >
                                    Administrador web
                                </option>

                                <option
                                    value="funcionario"
                                    <?= $filtroRol === 'funcionario' ? 'selected' : '' ?>
                                >
                                    Funcionario
                                </option>

                            </select>

                        </div>


                        <div class="filter-group">

                            <label for="estado">
                                Estado
                            </label>

                            <select
                                id="estado"
                                name="estado"
                            >

                                <option value="">
                                    Todos
                                </option>

                                <option
                                    value="activo"
                                    <?= $filtroEstado === 'activo' ? 'selected' : '' ?>
                                >
                                    Activos
                                </option>

                                <option
                                    value="inactivo"
                                    <?= $filtroEstado === 'inactivo' ? 'selected' : '' ?>
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
                                href="gestionar_funcionarios.php?vista=funcionarios"
                                class="secondary-button light"
                            >
                                Limpiar
                            </a>

                        </div>

                    </form>

                </section>


                <!-- LISTADO DE CUENTAS -->

                <section class="content-card">

                    <div class="content-card-header">

                        <div>

                            <span class="content-card-kicker">
                                Usuarios internos
                            </span>

                            <h2>
                                Cuentas registradas
                            </h2>

                        </div>

                        <span class="results-count">

                            <?= $totalFuncionarios ?>

                            resultado<?= $totalFuncionarios === 1 ? '' : 's' ?>

                        </span>

                    </div>


                    <?php if (count($funcionarios) > 0): ?>

                        <div class="table-container">

                            <table class="data-table">

                                <thead>

                                    <tr>
                                        <th>Funcionario</th>
                                        <th>Usuario</th>
                                        <th>Tipo de acceso</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>

                                </thead>


                                <tbody>

                                    <?php foreach ($funcionarios as $funcionario): ?>

                                        <?php

                                        $funcionarioActivo =
                                            valorBooleanoActivo($funcionario['activo']);

                                        $tipoAcceso =
                                            $funcionario['tipo_acceso'] ?? 'funcionario';

                                        $claseRol = in_array(
                                            $tipoAcceso,
                                            ['superadmin', 'administrador'],
                                            true
                                        )
                                            ? 'administrator'
                                            : 'employee';

                                        ?>

                                        <tr>

                                            <td>

                                                <div class="person-cell">

                                                    <span class="person-avatar">

                                                        <?= escaparGestionPersonal(
                                                            mb_strtoupper(
                                                                mb_substr($funcionario['nombre'], 0, 1, 'UTF-8') .
                                                                mb_substr($funcionario['apellido'], 0, 1, 'UTF-8'),
                                                                'UTF-8'
                                                            )
                                                        ) ?>

                                                    </span>

                                                    <div>

                                                        <strong>

                                                            <?= escaparGestionPersonal(
                                                                $funcionario['nombre'] .
                                                                ' ' .
                                                                $funcionario['apellido']
                                                            ) ?>

                                                        </strong>

                                                        <small>

                                                            ID #

                                                            <?= (int) $funcionario['id_funcionario'] ?>

                                                        </small>

                                                    </div>

                                                </div>

                                            </td>


                                            <td>
                                                <?= escaparGestionPersonal($funcionario['usuario']) ?>
                                            </td>


                                            <td>

                                                <span class="role-badge <?= $claseRol ?>">

                                                    <?= escaparGestionPersonal(
                                                        etiquetaTipoAcceso($tipoAcceso)
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <span class="status-badge <?= $funcionarioActivo ? 'active' : 'inactive' ?>">

                                                    <?= $funcionarioActivo ? 'Activo' : 'Inactivo' ?>

                                                </span>

                                            </td>


                                            <td>

                                                <div class="table-actions">

                                                    <button
                                                        type="button"
                                                        class="table-action-button edit"
                                                        data-id="<?= (int) $funcionario['id_funcionario'] ?>"
                                                        data-action="editar"
                                                    >
                                                        Editar
                                                    </button>


                                                    <button
                                                        type="button"
                                                        class="table-action-button <?= $funcionarioActivo ? 'disable' : 'enable' ?>"
                                                        data-id="<?= (int) $funcionario['id_funcionario'] ?>"
                                                        data-action="estado"
                                                        data-estado="<?= $funcionarioActivo ? 'activo' : 'inactivo' ?>"
                                                    >

                                                        <?= $funcionarioActivo ? 'Desactivar' : 'Activar' ?>

                                                    </button>


                                                    <button
                                                        type="button"
                                                        class="table-action-button delete"
                                                        data-id="<?= (int) $funcionario['id_funcionario'] ?>"
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
                                No se encontraron cuentas
                            </h3>

                            <p>
                                No existen cuentas que coincidan
                                con los filtros seleccionados.
                            </p>

                        </div>

                    <?php endif; ?>

                </section>

            </section>

        <?php endif; ?>


        <!-- =================================================
             VISTA: PROFESIONALES SIN ACCESO AL SISTEMA
             ================================================= -->

        <section
            id="seccionProfesionales"
            class="management-section"
            <?= $vistaActual !== 'profesionales' ? 'hidden' : '' ?>
        >


            <div class="professional-info-note">

                <span class="professional-info-icon">
                    ℹ
                </span>

                <p>
                    Estos registros corresponden a los profesionales
                    que atenderán a los pacientes. No tienen usuario,
                    contraseña ni acceso al sistema.
                </p>

            </div>


            <!-- RESUMEN -->

            <section class="summary-cards">

                <article class="summary-card">

                    <span class="summary-card-icon">
                        🩺
                    </span>

                    <div>

                        <span class="summary-card-label">
                            Profesionales
                        </span>

                        <strong>
                            <?= $totalProfesionales ?>
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
                            <?= $totalProfesionalesActivos ?>
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
                            <?= $totalProfesionalesInactivos ?>
                        </strong>

                    </div>

                </article>

            </section>


            <!-- ERROR PROFESIONALES -->

            <?php if ($errorProfesionales !== ''): ?>

                <div class="page-alert error">

                    <?= escaparGestionPersonal($errorProfesionales) ?>

                </div>

            <?php endif; ?>


            <!-- FILTROS PROFESIONALES -->

            <section class="filters-card">

                <form
                    method="GET"
                    action="gestionar_funcionarios.php"
                    class="professional-filters-form"
                >

                    <input
                        type="hidden"
                        name="vista"
                        value="profesionales"
                    >


                    <div class="filter-group professional-search-group">

                        <label for="buscarProfesional">
                            Buscar profesional
                        </label>

                        <input
                            type="search"
                            id="buscarProfesional"
                            name="buscar_profesional"
                            value="<?= escaparGestionPersonal($buscarProfesional) ?>"
                            placeholder="Nombre, apellido, RUT o estamento"
                        >

                    </div>


                    <div class="filter-group">

                        <label for="nodoProfesionalFiltro">
                            Nodo
                        </label>

                        <select
                            id="nodoProfesionalFiltro"
                            name="nodo_profesional"
                        >

                            <option value="">
                                Todos los nodos
                            </option>

                            <option
                                value="CESFAM"
                                <?= $filtroNodoProfesional === 'CESFAM' ? 'selected' : '' ?>
                            >
                                CESFAM
                            </option>

                            <option
                                value="CECOSF"
                                <?= $filtroNodoProfesional === 'CECOSF' ? 'selected' : '' ?>
                            >
                                CECOSF
                            </option>

                        </select>

                    </div>


                    <div class="filter-group">

                        <label for="estadoProfesionalFiltro">
                            Estado
                        </label>

                        <select
                            id="estadoProfesionalFiltro"
                            name="estado_profesional"
                        >

                            <option value="">
                                Todos
                            </option>

                            <option
                                value="activo"
                                <?= $filtroEstadoProfesional === 'activo' ? 'selected' : '' ?>
                            >
                                Activos
                            </option>

                            <option
                                value="inactivo"
                                <?= $filtroEstadoProfesional === 'inactivo' ? 'selected' : '' ?>
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
                            href="gestionar_funcionarios.php?vista=profesionales"
                            class="secondary-button light"
                        >
                            Limpiar
                        </a>

                    </div>

                </form>

            </section>


            <!-- LISTADO DE PROFESIONALES -->

            <section class="content-card">

                <div class="content-card-header">

                    <div>

                        <span class="content-card-kicker">
                            Personal de atención
                        </span>

                        <h2>
                            Profesionales registrados
                        </h2>

                    </div>

                    <span class="results-count">

                        <?= $totalProfesionales ?>

                        resultado<?= $totalProfesionales === 1 ? '' : 's' ?>

                    </span>

                </div>


                <?php if (count($profesionales) > 0): ?>

                    <div class="table-container">

                        <table class="data-table professional-table">

                            <thead>

                                <tr>
                                    <th>Profesional</th>
                                    <th>RUT</th>
                                    <th>Estamento / Instrumento</th>
                                    <th>Nodo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($profesionales as $profesional): ?>

                                    <?php

                                    $profesionalActivo =
                                        valorBooleanoActivo($profesional['activo']);

                                    ?>

                                    <tr>

                                        <td>

                                            <div class="person-cell">

                                                <span class="person-avatar professional-avatar">

                                                    <?= escaparGestionPersonal(
                                                        mb_strtoupper(
                                                            mb_substr($profesional['nombre'], 0, 1, 'UTF-8') .
                                                            mb_substr($profesional['apellido'], 0, 1, 'UTF-8'),
                                                            'UTF-8'
                                                        )
                                                    ) ?>

                                                </span>

                                                <div>

                                                    <strong>

                                                        <?= escaparGestionPersonal(
                                                            $profesional['nombre'] .
                                                            ' ' .
                                                            $profesional['apellido']
                                                        ) ?>

                                                    </strong>

                                                    <small>

                                                        ID #

                                                        <?= (int) $profesional['id_profesional'] ?>

                                                    </small>

                                                </div>

                                            </div>

                                        </td>


                                        <td>
                                            <?= escaparGestionPersonal($profesional['rut']) ?>
                                        </td>


                                        <td>
                                            <?= escaparGestionPersonal($profesional['estamento']) ?>
                                        </td>


                                        <td>

                                            <span class="node-badge <?= $profesional['nodo'] === 'CECOSF' ? 'cecosf' : 'cesfam' ?>">

                                                <?= escaparGestionPersonal($profesional['nodo']) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span class="status-badge <?= $profesionalActivo ? 'active' : 'inactive' ?>">

                                                <?= $profesionalActivo ? 'Activo' : 'Inactivo' ?>

                                            </span>

                                        </td>


                                        <td>

                                            <div class="table-actions">

                                                <button
                                                    type="button"
                                                    class="table-action-button edit"
                                                    data-profesional-action="editar"
                                                    data-id="<?= (int) $profesional['id_profesional'] ?>"
                                                >
                                                    Editar
                                                </button>


                                                <button
                                                    type="button"
                                                    class="table-action-button <?= $profesionalActivo ? 'disable' : 'enable' ?>"
                                                    data-profesional-action="estado"
                                                    data-id="<?= (int) $profesional['id_profesional'] ?>"
                                                    data-estado="<?= $profesionalActivo ? 'activo' : 'inactivo' ?>"
                                                >

                                                    <?= $profesionalActivo ? 'Desactivar' : 'Activar' ?>

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
                            🩺
                        </span>

                        <h3>
                            No se encontraron profesionales
                        </h3>

                        <p>
                            Registra un profesional o modifica
                            los filtros de búsqueda.
                        </p>

                    </div>

                <?php endif; ?>

            </section>

        </section>

    </main>

</div>


<!-- =========================================================
     FOOTER
     ========================================================= -->

<?php

require_once '../componentes/footer_funcionario.php';

?>


<?php if ($rolFuncionario === 'superadmin'): ?>

    <!-- =====================================================
         MODAL: CREAR / EDITAR CUENTA DEL SISTEMA
         ===================================================== -->

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

                    <h2 id="modalFuncionarioTitulo">
                        Nueva cuenta
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

                        <label for="funcionarioNombre">
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

                        <label for="funcionarioApellido">
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

                        <label for="funcionarioCorreo">
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

                        <label for="funcionarioUsuario">
                            Usuario
                        </label>

                        <input
                            type="text"
                            id="funcionarioUsuario"
                            name="usuario"
                            maxlength="50"
                            required
                            autocomplete="username"
                        >

                        <small class="form-help">
                            Debe tener entre 3 y 50 caracteres.
                        </small>

                        <small
                            class="form-error"
                            id="errorFuncionarioUsuario"
                        ></small>

                    </div>


                    <div class="form-group full-width">

                        <label for="funcionarioRol">
                            Tipo de acceso
                        </label>

                        <select
                            id="funcionarioRol"
                            name="rol"
                            required
                        >

                            <option value="superadmin">
                                Superadmin
                            </option>

                            <option value="administrador">
                                Administrador de reservas
                            </option>

                            <option value="administrador_web">
                                Administrador web
                            </option>

                            <option value="funcionario">
                                Funcionario
                            </option>

                        </select>

                        <small class="form-help">
                            El administrador de reservas gestiona agendas
                            y profesionales. El administrador web gestiona
                            el contenido del sitio público. El superadmin
                            tiene acceso completo.
                        </small>

                    </div>


                    <div class="form-group full-width">

                        <label for="funcionarioPassword">
                            Contraseña
                        </label>

                        <input
                            type="password"
                            id="funcionarioPassword"
                            name="password"
                            minlength="6"
                            autocomplete="new-password"
                        >

                        <small class="form-help">
                            En una cuenta nueva es obligatoria.
                            Al editar, déjala vacía para conservar
                            la contraseña actual.
                        </small>

                        <small
                            class="form-error"
                            id="errorFuncionarioPassword"
                        ></small>

                    </div>


                    <div class="form-group full-width">

                        <label class="checkbox-field">

                            <input
                                type="checkbox"
                                id="funcionarioActivo"
                                name="activo"
                                checked
                            >

                            <span>
                                Cuenta activa
                            </span>

                        </label>

                    </div>

                </div>


                <div class="modal-actions">

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
                        Guardar cuenta
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

<?php endif; ?>


<!-- =========================================================
     MODAL: CREAR / EDITAR PROFESIONAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="modalProfesional"
    hidden
>

    <div
        class="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalProfesionalTitulo"
    >

        <div class="modal-header">

            <div>

                <span class="modal-kicker">
                    Personal de atención
                </span>

                <h2 id="modalProfesionalTitulo">
                    Nuevo profesional
                </h2>

            </div>

            <button
                type="button"
                class="modal-close"
                id="btnCerrarModalProfesional"
                aria-label="Cerrar"
            >
                ×
            </button>

        </div>


        <form
            id="formProfesional"
            autocomplete="off"
        >

            <input
                type="hidden"
                id="profesionalId"
                name="id_profesional"
                value=""
            >


            <div class="form-grid">


                <div class="form-group">

                    <label for="profesionalNombre">
                        Nombre
                    </label>

                    <input
                        type="text"
                        id="profesionalNombre"
                        name="nombre"
                        maxlength="100"
                        required
                    >

                    <small
                        class="form-error"
                        id="errorProfesionalNombre"
                    ></small>

                </div>


                <div class="form-group">

                    <label for="profesionalApellido">
                        Apellido
                    </label>

                    <input
                        type="text"
                        id="profesionalApellido"
                        name="apellido"
                        maxlength="100"
                        required
                    >

                    <small
                        class="form-error"
                        id="errorProfesionalApellido"
                    ></small>

                </div>


                <div class="form-group">

                    <label for="profesionalRut">
                        RUT
                    </label>

                    <input
                        type="text"
                        id="profesionalRut"
                        name="rut"
                        maxlength="12"
                        placeholder="12.345.678-9"
                        autocomplete="off"
                        required
                    >

                    <small class="form-help">
                        Ingresa el RUT con o sin puntos y con guion.
                    </small>

                    <small
                        class="form-error"
                        id="errorProfesionalRut"
                    ></small>

                </div>


                <div class="form-group">

                    <label for="profesionalEstamentoSelect">
                        Estamento / Instrumento
                    </label>

                    <select
                        id="profesionalEstamentoSelect"
                        required
                    >

                        <option value="">
                            Seleccione un estamento
                        </option>

                        <?php foreach ($estamentosProfesionales as $estamento): ?>

                            <option value="<?= escaparGestionPersonal($estamento) ?>">

                                <?= escaparGestionPersonal($estamento) ?>

                            </option>

                        <?php endforeach; ?>

                        <option value="__otro__">
                            Agregar otro estamento...
                        </option>

                    </select>


                    <input
                        type="text"
                        id="profesionalEstamentoOtro"
                        maxlength="100"
                        placeholder="Escribe el estamento o instrumento"
                        hidden
                    >


                    <small class="form-help">
                        Se guarda un solo estamento por profesional.
                        Puedes elegir uno existente o escribir otro.
                    </small>

                    <small
                        class="form-error"
                        id="errorProfesionalEstamento"
                    ></small>

                </div>


                <div class="form-group">

                    <label for="profesionalNodo">
                        Nodo
                    </label>

                    <select
                        id="profesionalNodo"
                        name="nodo"
                        required
                    >

                        <option value="">
                            Seleccione un nodo
                        </option>

                        <option value="CESFAM">
                            CESFAM
                        </option>

                        <option value="CECOSF">
                            CECOSF
                        </option>

                    </select>

                    <small
                        class="form-error"
                        id="errorProfesionalNodo"
                    ></small>

                </div>


                <div class="form-group full-width">

                    <label class="checkbox-field">

                        <input
                            type="checkbox"
                            id="profesionalActivo"
                            name="activo"
                            checked
                        >

                        <span>
                            Profesional activo
                        </span>

                    </label>

                </div>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="secondary-button"
                    id="btnCancelarProfesional"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="primary-button"
                    id="btnGuardarProfesional"
                >
                    Guardar profesional
                </button>

            </div>


            <div
                class="form-notification"
                id="notificacionProfesional"
                hidden
            ></div>

        </form>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<?php if ($rolFuncionario === 'superadmin'): ?>

    <script src="../js/gestionar_funcionarios.js"></script>

<?php endif; ?>

<script src="../js/gestionar_profesionales.js"></script>


</body>
</html>
