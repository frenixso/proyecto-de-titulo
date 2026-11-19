<?php
/*
 * =========================================================
 * TUTORIALES PÚBLICOS
 * CESFAM EDUARDO RAGGIO CATEMU
 * Archivo: publicos/tutoriales.php
 *
 * Funciones:
 *   - Mostrar solamente tutoriales publicados.
 *   - Buscar por título y descripción.
 *   - Filtrar por categoría activa.
 *   - Mostrar 9 tutoriales por página.
 *   - Mostrar miniaturas de YouTube.
 *   - Generar vistas previas de videos locales.
 *   - Reproducir videos locales y de YouTube.
 *   - Mostrar el detalle de un tutorial.
 *
 * Seguridad:
 *   - Consultas preparadas mediante PDO.
 *   - Escapado de contenido HTML.
 *   - Validación de los identificadores de YouTube.
 *   - Validación de las rutas de videos locales.
 *   - No utiliza la API administrativa.
 *   - JavaScript separado en su propio archivo.
 * =========================================================
 */

$base_url = '/sistema toma de horas cesfam';

$tutorialesPublicados = [];
$categoriasPublicas = [];
$tutorialDetalle = null;

$errorListadoTutoriales = '';
$errorDetalleTutorial = '';

$totalTutoriales = 0;
$tutorialesPorPagina = 9;
$paginaActual = 1;
$totalPaginas = 1;
$offsetTutoriales = 0;

$busquedaTutoriales = trim(
    is_string($_GET['busqueda'] ?? null)
        ? $_GET['busqueda']
        : ''
);

$categoriaFiltro = null;

/*
 * =========================================================
 * VALIDAR PÁGINA
 * =========================================================
 */

$paginaRecibida = filter_var(
    $_GET['pagina'] ?? '1',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($paginaRecibida !== false && $paginaRecibida !== null) {
    $paginaActual = (int) $paginaRecibida;
}

/*
 * =========================================================
 * VALIDAR CATEGORÍA
 * =========================================================
 */

$categoriaRecibida = $_GET['categoria'] ?? '';

if ($categoriaRecibida !== '' && !is_array($categoriaRecibida)) {
    $categoriaValidada = filter_var(
        $categoriaRecibida,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($categoriaValidada !== false && $categoriaValidada !== null) {
        $categoriaFiltro = (int) $categoriaValidada;
    }
}

/*
 * =========================================================
 * DETERMINAR LISTADO O DETALLE
 * =========================================================
 */

$modoDetalle = array_key_exists('id', $_GET);
$idTutorial = null;

if ($modoDetalle) {
    $idTutorial = filter_var(
        $_GET['id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idTutorial === false || $idTutorial === null) {
        $errorDetalleTutorial = 'El identificador del tutorial no es válido.';
    } else {
        $idTutorial = (int) $idTutorial;
    }
}

/*
 * =========================================================
 * FUNCIONES DE PRESENTACIÓN
 * =========================================================
 */

function escaparTutorialPublico($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function fechaTutorialPublico($fecha): string
{
    if (empty($fecha)) {
        return 'Fecha no disponible';
    }

    try {
        return (new DateTime((string) $fecha))->format('d/m/Y');
    } catch (Throwable $e) {
        return 'Fecha no disponible';
    }
}

function resumenTutorialPublico($descripcion): string
{
    $texto = trim((string) ($descripcion ?? ''));

    $texto = strip_tags($texto);
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    $texto = trim($texto);

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($texto, 'UTF-8') > 200) {
            $texto = mb_substr($texto, 0, 197, 'UTF-8') . '...';
        }
    } elseif (strlen($texto) > 200) {
        $texto = substr($texto, 0, 197) . '...';
    }

    return $texto;
}

/*
 * =========================================================
 * VALIDAR ID DE YOUTUBE
 *
 * La base de datos guarda únicamente el identificador.
 * No se inserta el iframe proporcionado por el usuario.
 * =========================================================
 */

function obtenerIdYouTubePublico($id): ?string
{
    if (
        !is_string($id)
        || !preg_match('/^[A-Za-z0-9_-]{11}$/', $id)
    ) {
        return null;
    }

    return $id;
}

/*
 * =========================================================
 * VALIDAR RUTA DE VIDEO LOCAL
 *
 * Los archivos se guardan en:
 * uploads/tutoriales/
 *
 * Solo se admiten los nombres generados por la aplicación.
 * =========================================================
 */

function obtenerUrlVideoTutorialPublico($ruta): string
{
    if (
        !is_string($ruta)
        || !preg_match(
            '~^uploads/tutoriales/([A-Za-z0-9_-]+\.(?:mp4|webm|ogv))$~i',
            $ruta
        )
    ) {
        return '';
    }

    $raizProyecto = realpath(__DIR__ . '/..');

    if ($raizProyecto === false) {
        return '';
    }

    $directorioTutoriales = realpath(
        $raizProyecto
        . DIRECTORY_SEPARATOR
        . 'uploads'
        . DIRECTORY_SEPARATOR
        . 'tutoriales'
    );

    $rutaFisica = realpath(
        $raizProyecto
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $ruta)
    );

    if (
        $directorioTutoriales === false
        || $rutaFisica === false
        || !is_file($rutaFisica)
        || strpos(
            $rutaFisica,
            $directorioTutoriales . DIRECTORY_SEPARATOR
        ) !== 0
    ) {
        return '';
    }

    return '../' . $ruta;
}

/*
 * =========================================================
 * GENERAR URL PARA LA PAGINACIÓN
 * Conserva los filtros actuales.
 * =========================================================
 */

function urlListadoTutorialPublico(
    int $pagina,
    string $busqueda,
    ?int $categoria
): string {
    $parametros = [
        'pagina' => $pagina
    ];

    if ($busqueda !== '') {
        $parametros['busqueda'] = $busqueda;
    }

    if ($categoria !== null) {
        $parametros['categoria'] = $categoria;
    }

    return 'tutoriales.php?' . http_build_query($parametros);
}

/*
 * =========================================================
 * CONSULTAS
 * =========================================================
 */

try {
    require_once __DIR__ . '/../config/conexion.php';

    if (!isset($conexion) || !($conexion instanceof PDO)) {
        throw new RuntimeException(
            'No se encontró una conexión PDO válida.'
        );
    }

    /*
     * Las categorías inactivas no se muestran públicamente.
     */
    $stmtCategorias = $conexion->query("
        SELECT
            c.id_categoria,
            c.nombre
        FROM public.web_tutoriales_categorias c
        WHERE c.activo = TRUE
        ORDER BY c.nombre ASC
    ");

    $categoriasPublicas = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

    /*
     * =====================================================
     * DETALLE DE UN TUTORIAL
     * =====================================================
     */

    if ($modoDetalle && $errorDetalleTutorial === '') {
        $sqlDetalle = "
            SELECT
                t.id_tutorial,
                t.titulo,
                t.descripcion,
                t.tipo_video,
                t.ruta_video,
                t.youtube_id,
                t.nombre_archivo_original,
                t.tipo_mime,
                t.tamano_bytes,
                t.fecha_publicacion,
                categorias.categorias_texto
            FROM public.web_tutoriales t

            LEFT JOIN LATERAL (
                SELECT string_agg(
                    c.nombre,
                    ', ' ORDER BY c.nombre
                ) AS categorias_texto
                FROM public.web_tutoriales_categoria_relacion r
                INNER JOIN public.web_tutoriales_categorias c
                    ON c.id_categoria = r.id_categoria
                WHERE r.id_tutorial = t.id_tutorial
                  AND c.activo = TRUE
            ) categorias ON TRUE

            WHERE t.id_tutorial = :id_tutorial
              AND LOWER(BTRIM(CAST(t.estado AS TEXT))) = 'publicada'

            LIMIT 1
        ";

        $stmtDetalle = $conexion->prepare($sqlDetalle);

        $stmtDetalle->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $tutorialDetalle = $stmtDetalle->fetch(PDO::FETCH_ASSOC);

        if (!$tutorialDetalle) {
            $errorDetalleTutorial =
                'El tutorial no existe o ya no se encuentra publicado.';
        }
    }

    /*
     * =====================================================
     * LISTADO DE TUTORIALES
     * =====================================================
     */

    if (!$modoDetalle) {
        $condiciones = [
            "LOWER(BTRIM(CAST(t.estado AS TEXT))) = 'publicada'"
        ];

        $parametros = [];

        /*
         * Búsqueda por título o descripción.
         */
        if ($busquedaTutoriales !== '') {
            $condiciones[] = "(
                t.titulo ILIKE :busqueda_titulo
                OR COALESCE(t.descripcion, '') ILIKE :busqueda_descripcion
            )";

            $termino = '%' . $busquedaTutoriales . '%';

            $parametros[':busqueda_titulo'] = $termino;
            $parametros[':busqueda_descripcion'] = $termino;
        }

        /*
         * Filtro por categoría activa.
         */
        if ($categoriaFiltro !== null) {
            $condiciones[] = "
                EXISTS (
                    SELECT 1
                    FROM public.web_tutoriales_categoria_relacion r
                    INNER JOIN public.web_tutoriales_categorias c
                        ON c.id_categoria = r.id_categoria
                    WHERE r.id_tutorial = t.id_tutorial
                      AND r.id_categoria = :id_categoria
                      AND c.activo = TRUE
                )
            ";

            $parametros[':id_categoria'] = $categoriaFiltro;
        }

        $where = 'WHERE ' . implode(' AND ', $condiciones);

        /*
         * Total de resultados.
         */
        $sqlConteo = "
            SELECT COUNT(*)
            FROM public.web_tutoriales t
            $where
        ";

        $stmtConteo = $conexion->prepare($sqlConteo);
        $stmtConteo->execute($parametros);

        $totalTutoriales = (int) $stmtConteo->fetchColumn();

        $totalPaginas = max(
            1,
            (int) ceil($totalTutoriales / $tutorialesPorPagina)
        );

        $paginaActual = min($paginaActual, $totalPaginas);

        $offsetTutoriales = ($paginaActual - 1) * $tutorialesPorPagina;

        /*
         * Obtener los tutoriales publicados.
         *
         * Se incluye tipo_mime para validar los videos locales.
         */
        $sqlListado = "
            SELECT
                t.id_tutorial,
                t.titulo,
                t.descripcion,
                t.tipo_video,
                t.ruta_video,
                t.youtube_id,
                t.nombre_archivo_original,
                t.tipo_mime,
                t.fecha_publicacion,
                categorias.categorias_texto
            FROM public.web_tutoriales t

            LEFT JOIN LATERAL (
                SELECT string_agg(
                    c.nombre,
                    ', ' ORDER BY c.nombre
                ) AS categorias_texto
                FROM public.web_tutoriales_categoria_relacion r
                INNER JOIN public.web_tutoriales_categorias c
                    ON c.id_categoria = r.id_categoria
                WHERE r.id_tutorial = t.id_tutorial
                  AND c.activo = TRUE
            ) categorias ON TRUE

            $where

            ORDER BY
                t.fecha_publicacion DESC NULLS LAST,
                t.id_tutorial DESC

            LIMIT :limite
            OFFSET :desplazamiento
        ";

        $stmtListado = $conexion->prepare($sqlListado);

        foreach ($parametros as $nombre => $valor) {
            $stmtListado->bindValue($nombre, $valor);
        }

        $stmtListado->bindValue(
            ':limite',
            $tutorialesPorPagina,
            PDO::PARAM_INT
        );

        $stmtListado->bindValue(
            ':desplazamiento',
            $offsetTutoriales,
            PDO::PARAM_INT
        );

        $stmtListado->execute();

        $tutorialesPublicados = $stmtListado->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log(
        'Error en tutoriales públicos del CESFAM: '
        . $e->getMessage()
    );

    if ($modoDetalle) {
        if ($errorDetalleTutorial === '') {
            $errorDetalleTutorial =
                'No fue posible cargar el tutorial. Inténtalo nuevamente más tarde.';
        }

        $tutorialDetalle = null;
    } else {
        $errorListadoTutoriales =
            'No fue posible cargar los tutoriales. Inténtalo nuevamente más tarde.';

        $tutorialesPublicados = [];
        $totalTutoriales = 0;
        $totalPaginas = 1;
        $paginaActual = 1;
        $offsetTutoriales = 0;
    }
}

/*
 * =========================================================
 * PREPARAR DETALLE
 * =========================================================
 */

$tituloTutorialDetalle = '';
$descripcionTutorialDetalle = '';
$fechaTutorialDetalle = '';
$categoriasTutorialDetalle = '';
$urlVideoLocalDetalle = '';
$tipoMimeDetalle = '';
$idYouTubeDetalle = null;

if ($tutorialDetalle) {
    $tituloTutorialDetalle = trim(
        (string) ($tutorialDetalle['titulo'] ?? '')
    );

    if ($tituloTutorialDetalle === '') {
        $tituloTutorialDetalle = 'Tutorial del CESFAM';
    }

    $descripcionTutorialDetalle = trim(
        (string) ($tutorialDetalle['descripcion'] ?? '')
    );

    $fechaTutorialDetalle = fechaTutorialPublico(
        $tutorialDetalle['fecha_publicacion'] ?? null
    );

    $categoriasTutorialDetalle = trim(
        (string) ($tutorialDetalle['categorias_texto'] ?? '')
    );

    if (($tutorialDetalle['tipo_video'] ?? '') === 'youtube') {
        $idYouTubeDetalle = obtenerIdYouTubePublico(
            $tutorialDetalle['youtube_id'] ?? null
        );
    } elseif (($tutorialDetalle['tipo_video'] ?? '') === 'archivo') {
        $urlVideoLocalDetalle = obtenerUrlVideoTutorialPublico(
            $tutorialDetalle['ruta_video'] ?? null
        );

        $tiposMimePermitidos = [
            'video/mp4',
            'video/webm',
            'video/ogg'
        ];

        $mimeRecibido = strtolower(
            (string) ($tutorialDetalle['tipo_mime'] ?? '')
        );

        if (in_array($mimeRecibido, $tiposMimePermitidos, true)) {
            $tipoMimeDetalle = $mimeRecibido;
        }
    }
}

/*
 * =========================================================
 * METADATOS
 * =========================================================
 */

if ($modoDetalle && $tutorialDetalle) {
    $tituloPagina =
        $tituloTutorialDetalle
        . ' | Tutoriales del CESFAM Eduardo Raggio Catemu';

    $descripcionPagina = $descripcionTutorialDetalle !== ''
        ? resumenTutorialPublico($descripcionTutorialDetalle)
        : $tituloTutorialDetalle;
} elseif ($modoDetalle) {
    $tituloPagina =
        'Tutorial no disponible | CESFAM Eduardo Raggio Catemu';

    $descripcionPagina =
        'Tutoriales públicos del CESFAM Eduardo Raggio Catemu.';
} else {
    $tituloPagina =
        'Tutoriales | CESFAM Eduardo Raggio Catemu';

    $descripcionPagina =
        'Consulta los videos y tutoriales informativos del CESFAM Eduardo Raggio Catemu.';
}

/*
 * =========================================================
 * DATOS DE PAGINACIÓN
 * =========================================================
 */

$desdeTutorial = $totalTutoriales > 0
    ? $offsetTutoriales + 1
    : 0;

$hastaTutorial = min(
    $offsetTutoriales + $tutorialesPorPagina,
    $totalTutoriales
);

$paginaInicio = max(1, $paginaActual - 2);
$paginaFin = min($totalPaginas, $paginaActual + 2);

/*
 * URL para volver al listado conservando los filtros.
 */
$parametrosVolverListado = [];

if ($paginaActual > 1) {
    $parametrosVolverListado['pagina'] = $paginaActual;
}

if ($busquedaTutoriales !== '') {
    $parametrosVolverListado['busqueda'] = $busquedaTutoriales;
}

if ($categoriaFiltro !== null) {
    $parametrosVolverListado['categoria'] = $categoriaFiltro;
}

$urlVolverListado = 'tutoriales.php';

if (!empty($parametrosVolverListado)) {
    $urlVolverListado .= '?' . http_build_query($parametrosVolverListado);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="<?= escaparTutorialPublico($descripcionPagina) ?>"
    >

    <title><?= escaparTutorialPublico($tituloPagina) ?></title>

    <link rel="stylesheet" href="../css/compartidos/general.css">
    <link rel="stylesheet" href="../css/compartidos/navbar_publica.css">
    <link rel="stylesheet" href="../css/publicos/tutoriales.css?v=20261027-1">
    <link rel="stylesheet" href="../css/compartidos/footer_publico.css">
</head>
<body>

<?php require_once __DIR__ . '/../componentes/navbar_publica.php'; ?>

<main id="main-content">

<?php if (!$modoDetalle): ?>

    <!-- =================================================
         LISTADO PÚBLICO DE TUTORIALES
         ================================================= -->

    <section
        class="pub-section pub-tutoriales-section"
        id="tutoriales"
        aria-labelledby="tituloListadoTutoriales"
    >
        <div class="pub-section-inner">

            <nav class="pub-tutoriales-migas" aria-label="Ruta de navegación">
                <a href="index.php">Inicio</a>
                <span aria-hidden="true">›</span>
                <span aria-current="page">Tutoriales</span>
            </nav>

            <header class="pub-section-header">
                <span class="pub-section-tag">
                    Información para la comunidad
                </span>

                <h1
                    class="pub-section-title"
                    id="tituloListadoTutoriales"
                >
                    Tutoriales y videos
                </h1>

                <p class="pub-section-desc">
                    Encuentra videos y material de orientación
                    publicados por el CESFAM Eduardo Raggio Catemu.
                </p>
            </header>

            <!-- BÚSQUEDA Y FILTRO POR CATEGORÍA -->

            <form
                class="pub-tutoriales-filtros"
                method="get"
                action="tutoriales.php"
                role="search"
            >
                <div class="pub-tutoriales-filtro-campo pub-tutoriales-filtro-busqueda">
                    <label for="busquedaTutoriales">
                        Buscar tutorial
                    </label>

                    <input
                        type="search"
                        id="busquedaTutoriales"
                        name="busqueda"
                        value="<?= escaparTutorialPublico($busquedaTutoriales) ?>"
                        placeholder="Escribe el nombre o descripción..."
                        maxlength="180"
                    >
                </div>

                <div class="pub-tutoriales-filtro-campo">
                    <label for="categoriaTutoriales">
                        Categoría
                    </label>

                    <select
                        id="categoriaTutoriales"
                        name="categoria"
                    >
                        <option value="">Todas las categorías</option>

                        <?php foreach ($categoriasPublicas as $categoriaPublica): ?>
                            <?php
                            $idCategoriaPublica = (int) (
                                $categoriaPublica['id_categoria'] ?? 0
                            );
                            ?>
                            <option
                                value="<?= $idCategoriaPublica ?>"
                                <?= $categoriaFiltro === $idCategoriaPublica ? 'selected' : '' ?>
                            >
                                <?= escaparTutorialPublico($categoriaPublica['nombre'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pub-tutoriales-filtro-acciones">
                    <button
                        type="submit"
                        class="pub-tutoriales-btn-buscar"
                    >
                        <span aria-hidden="true">⌕</span>
                        Buscar
                    </button>

                    <?php if ($busquedaTutoriales !== '' || $categoriaFiltro !== null): ?>
                        <a
                            class="pub-tutoriales-btn-limpiar"
                            href="tutoriales.php"
                        >
                            Limpiar filtros
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($errorListadoTutoriales !== ''): ?>

                <div class="pub-tutoriales-vacio" role="alert">
                    <span
                        class="pub-tutoriales-vacio-icono"
                        aria-hidden="true"
                    >
                        ⚠️
                    </span>

                    <h2>No fue posible cargar los tutoriales</h2>

                    <p>
                        <?= escaparTutorialPublico($errorListadoTutoriales) ?>
                    </p>

                    <a
                        class="pub-tutoriales-btn-volver"
                        href="index.php"
                    >
                        ← Volver al inicio
                    </a>
                </div>

            <?php elseif (!empty($tutorialesPublicados)): ?>

                <div class="pub-tutoriales-listado-resumen">
                    <p class="pub-tutoriales-cantidad">
                        Mostrando
                        <?= (int) $desdeTutorial ?>–<?= (int) $hastaTutorial ?>
                        de <?= (int) $totalTutoriales ?> tutoriales
                    </p>

                    <p class="pub-tutoriales-pagina">
                        Página <?= (int) $paginaActual ?>
                        de <?= (int) $totalPaginas ?>
                    </p>
                </div>

                <div class="pub-tutoriales-grid">

                    <?php foreach ($tutorialesPublicados as $item): ?>
                        <?php
                        $idTarjeta = (int) ($item['id_tutorial'] ?? 0);

                        $tituloTarjeta = trim(
                            (string) ($item['titulo'] ?? '')
                        );

                        if ($tituloTarjeta === '') {
                            $tituloTarjeta = 'Tutorial del CESFAM';
                        }

                        $descripcionTarjeta = resumenTutorialPublico(
                            $item['descripcion'] ?? ''
                        );

                        $fechaTarjeta = fechaTutorialPublico(
                            $item['fecha_publicacion'] ?? null
                        );

                        $categoriasTarjeta = trim(
                            (string) ($item['categorias_texto'] ?? '')
                        );

                        $tipoTarjeta = (string) (
                            $item['tipo_video'] ?? ''
                        );

                        $idYouTubeTarjeta = null;

                        if ($tipoTarjeta === 'youtube') {
                            $idYouTubeTarjeta = obtenerIdYouTubePublico(
                                $item['youtube_id'] ?? null
                            );
                        }

                        $urlLocalTarjeta = '';

                        if ($tipoTarjeta === 'archivo') {
                            $urlLocalTarjeta = obtenerUrlVideoTutorialPublico(
                                $item['ruta_video'] ?? null
                            );
                        }

                        $mimeLocalTarjeta = strtolower(
                            (string) ($item['tipo_mime'] ?? '')
                        );

                        $mimesPermitidosTarjeta = [
                            'video/mp4',
                            'video/webm',
                            'video/ogg'
                        ];

                        $mostrarVideoLocal = (
                            $tipoTarjeta === 'archivo'
                            && $urlLocalTarjeta !== ''
                            && in_array(
                                $mimeLocalTarjeta,
                                $mimesPermitidosTarjeta,
                                true
                            )
                        );

                        $urlTarjeta = 'tutoriales.php?id='
                            . rawurlencode((string) $idTarjeta);
                        ?>

                        <a
                            class="pub-tutorial-card"
                            href="<?= escaparTutorialPublico($urlTarjeta) ?>"
                            aria-label="Ver tutorial: <?= escaparTutorialPublico($tituloTarjeta) ?>"
                        >

                            <!-- =====================================
                                 MINIATURA O VISTA PREVIA DEL VIDEO
                                 ===================================== -->

                            <div class="pub-tutorial-imagen-wrapper">

                                <?php if ($idYouTubeTarjeta !== null): ?>

                                    <!-- MINIATURA DE YOUTUBE -->

                                    <img
                                        class="pub-tutorial-imagen"
                                        src="https://img.youtube.com/vi/<?= escaparTutorialPublico($idYouTubeTarjeta) ?>/hqdefault.jpg"
                                        alt=""
                                        loading="lazy"
                                    >

                                <?php elseif ($mostrarVideoLocal): ?>

                                    <!-- VISTA PREVIA DEL VIDEO LOCAL -->

                                    <video
                                        class="pub-tutorial-imagen pub-tutorial-imagen-video"
                                        data-tutorial-preview
                                        preload="metadata"
                                        muted
                                        playsinline
                                        aria-hidden="true"
                                        tabindex="-1"
                                    >
                                        <source
                                            src="<?= escaparTutorialPublico($urlLocalTarjeta) ?>"
                                            type="<?= escaparTutorialPublico($mimeLocalTarjeta) ?>"
                                        >
                                    </video>

                                    <!-- IMAGEN DE RESPALDO -->

                                    <div
                                        class="pub-tutorial-imagen-placeholder pub-tutorial-imagen-fallback"
                                        data-tutorial-preview-fallback
                                        aria-hidden="true"
                                    >
                                        <span class="pub-tutorial-placeholder-icon">
                                            🎬
                                        </span>
                                    </div>

                                <?php else: ?>

                                    <!-- VISTA PREVIA NO DISPONIBLE -->

                                    <div
                                        class="pub-tutorial-imagen-placeholder"
                                        aria-hidden="true"
                                    >
                                        <span class="pub-tutorial-placeholder-icon">
                                            🎬
                                        </span>
                                    </div>

                                <?php endif; ?>

                                <!-- INDICADOR VISUAL DE REPRODUCCIÓN -->

                                <span
                                    class="pub-tutorial-play"
                                    aria-hidden="true"
                                >
                                    ▶
                                </span>

                                <!-- TIPO DE VIDEO -->

                                <span class="pub-tutorial-tipo">
                                    <?= $tipoTarjeta === 'youtube' ? 'YouTube' : 'Video tutorial' ?>
                                </span>

                            </div>

                            <!-- CONTENIDO DE LA TARJETA -->

                            <div class="pub-tutorial-contenido">

                                <div class="pub-tutorial-meta">
                                    <span class="pub-tutorial-fecha">
                                        📅
                                        <?= escaparTutorialPublico($fechaTarjeta) ?>
                                    </span>
                                </div>

                                <?php if ($categoriasTarjeta !== ''): ?>
                                    <p class="pub-tutorial-categoria">
                                        <?= escaparTutorialPublico($categoriasTarjeta) ?>
                                    </p>
                                <?php endif; ?>

                                <h2 class="pub-tutorial-titulo">
                                    <?= escaparTutorialPublico($tituloTarjeta) ?>
                                </h2>

                                <p class="pub-tutorial-descripcion">
                                    <?php if ($descripcionTarjeta !== ''): ?>
                                        <?= escaparTutorialPublico($descripcionTarjeta) ?>
                                    <?php else: ?>
                                        Revisa este video informativo del CESFAM.
                                    <?php endif; ?>
                                </p>

                                <span class="pub-tutorial-ver">
                                    Ver tutorial
                                    <span aria-hidden="true">→</span>
                                </span>

                            </div>

                        </a>

                    <?php endforeach; ?>

                </div>

                <!-- =============================================
                     PAGINACIÓN
                     ============================================= -->

                <nav
                    class="pub-tutoriales-paginacion"
                    aria-label="Paginación de tutoriales"
                >
                    <div class="pub-tutoriales-paginacion-controles">

                        <?php if ($paginaActual > 1): ?>

                            <a
                                class="pub-tutoriales-paginacion-btn pub-paginacion-anterior"
                                href="<?= escaparTutorialPublico(urlListadoTutorialPublico(
                                    $paginaActual - 1,
                                    $busquedaTutoriales,
                                    $categoriaFiltro
                                )) ?>"
                                rel="prev"
                            >
                                <span aria-hidden="true">←</span>
                                Página anterior
                            </a>

                        <?php else: ?>

                            <span
                                class="pub-tutoriales-paginacion-btn deshabilitado pub-paginacion-anterior"
                                aria-disabled="true"
                            >
                                <span aria-hidden="true">←</span>
                                Página anterior
                            </span>

                        <?php endif; ?>

                        <div class="pub-tutoriales-paginacion-numeros">

                            <?php if ($paginaInicio > 1): ?>

                                <a
                                    class="pub-tutoriales-paginacion-numero"
                                    href="<?= escaparTutorialPublico(urlListadoTutorialPublico(
                                        1,
                                        $busquedaTutoriales,
                                        $categoriaFiltro
                                    )) ?>"
                                    aria-label="Ir a la página 1"
                                >
                                    1
                                </a>

                                <?php if ($paginaInicio > 2): ?>
                                    <span
                                        class="pub-tutoriales-ellipsis"
                                        aria-hidden="true"
                                    >
                                        …
                                    </span>
                                <?php endif; ?>

                            <?php endif; ?>

                            <?php for (
                                $numeroPagina = $paginaInicio;
                                $numeroPagina <= $paginaFin;
                                $numeroPagina++
                            ): ?>

                                <?php if ($numeroPagina === $paginaActual): ?>

                                    <span
                                        class="pub-tutoriales-paginacion-numero activo"
                                        aria-current="page"
                                        aria-label="Página actual: <?= (int) $numeroPagina ?>"
                                    >
                                        <?= (int) $numeroPagina ?>
                                    </span>

                                <?php else: ?>

                                    <a
                                        class="pub-tutoriales-paginacion-numero"
                                        href="<?= escaparTutorialPublico(urlListadoTutorialPublico(
                                            $numeroPagina,
                                            $busquedaTutoriales,
                                            $categoriaFiltro
                                        )) ?>"
                                        aria-label="Ir a la página <?= (int) $numeroPagina ?>"
                                    >
                                        <?= (int) $numeroPagina ?>
                                    </a>

                                <?php endif; ?>

                            <?php endfor; ?>

                            <?php if ($paginaFin < $totalPaginas): ?>

                                <?php if ($paginaFin < $totalPaginas - 1): ?>
                                    <span
                                        class="pub-tutoriales-ellipsis"
                                        aria-hidden="true"
                                    >
                                        …
                                    </span>
                                <?php endif; ?>

                                <a
                                    class="pub-tutoriales-paginacion-numero"
                                    href="<?= escaparTutorialPublico(urlListadoTutorialPublico(
                                        $totalPaginas,
                                        $busquedaTutoriales,
                                        $categoriaFiltro
                                    )) ?>"
                                    aria-label="Ir a la última página"
                                >
                                    <?= (int) $totalPaginas ?>
                                </a>

                            <?php endif; ?>

                        </div>

                        <?php if ($paginaActual < $totalPaginas): ?>

                            <a
                                class="pub-tutoriales-paginacion-btn pub-paginacion-siguiente"
                                href="<?= escaparTutorialPublico(urlListadoTutorialPublico(
                                    $paginaActual + 1,
                                    $busquedaTutoriales,
                                    $categoriaFiltro
                                )) ?>"
                                rel="next"
                            >
                                Página siguiente
                                <span aria-hidden="true">→</span>
                            </a>

                        <?php else: ?>

                            <span
                                class="pub-tutoriales-paginacion-btn deshabilitado pub-paginacion-siguiente"
                                aria-disabled="true"
                            >
                                Página siguiente
                                <span aria-hidden="true">→</span>
                            </span>

                        <?php endif; ?>

                    </div>

                    <p class="pub-tutoriales-paginacion-indicador">
                        Página <?= (int) $paginaActual ?>
                        de <?= (int) $totalPaginas ?>
                    </p>
                </nav>

            <?php else: ?>

                <div class="pub-tutoriales-vacio">

                    <span
                        class="pub-tutoriales-vacio-icono"
                        aria-hidden="true"
                    >
                        🎬
                    </span>

                    <?php if ($busquedaTutoriales !== '' || $categoriaFiltro !== null): ?>

                        <h2>No encontramos tutoriales</h2>

                        <p>
                            No hay tutoriales publicados que coincidan con
                            los filtros seleccionados. Prueba con otra
                            búsqueda o selecciona otra categoría.
                        </p>

                        <a
                            class="pub-tutoriales-btn-volver"
                            href="tutoriales.php"
                        >
                            Mostrar todos los tutoriales
                        </a>

                    <?php else: ?>

                        <h2>Todavía no hay tutoriales publicados</h2>

                        <p>
                            Cuando el CESFAM publique videos y material
                            informativo, podrás encontrarlos aquí.
                        </p>

                        <a
                            class="pub-tutoriales-btn-volver"
                            href="index.php"
                        >
                            ← Volver al portal del CESFAM
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

            <div class="pub-tutoriales-listado-pie">
                <a
                    class="pub-tutoriales-btn-volver"
                    href="index.php"
                >
                    ← Volver al portal del CESFAM
                </a>
            </div>

        </div>
    </section>

<?php else: ?>

    <!-- =================================================
         REPRODUCTOR Y DETALLE DEL TUTORIAL
         ================================================= -->

    <section
        class="pub-section pub-tutorial-detalle-seccion"
        aria-labelledby="tituloDetalleTutorial"
    >
        <div class="pub-section-inner">

            <nav class="pub-tutoriales-migas" aria-label="Ruta de navegación">
                <a href="index.php">Inicio</a>
                <span aria-hidden="true">›</span>

                <a href="<?= escaparTutorialPublico($urlVolverListado) ?>">
                    Tutoriales
                </a>

                <?php if ($tutorialDetalle): ?>
                    <span aria-hidden="true">›</span>

                    <span aria-current="page">
                        <?= escaparTutorialPublico($tituloTutorialDetalle) ?>
                    </span>
                <?php endif; ?>
            </nav>

            <?php if ($tutorialDetalle): ?>

                <article class="pub-tutorial-detalle">

                    <header class="pub-tutorial-detalle-encabezado">

                        <span class="pub-section-tag">
                            Tutorial del CESFAM
                        </span>

                        <h1
                            class="pub-tutorial-detalle-titulo"
                            id="tituloDetalleTutorial"
                        >
                            <?= escaparTutorialPublico($tituloTutorialDetalle) ?>
                        </h1>

                        <div class="pub-tutorial-detalle-meta">
                            <span>
                                📅 Publicado el
                                <?= escaparTutorialPublico($fechaTutorialDetalle) ?>
                            </span>

                            <?php if ($categoriasTutorialDetalle !== ''): ?>
                                <span class="pub-tutorial-detalle-categoria">
                                    <?= escaparTutorialPublico($categoriasTutorialDetalle) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if ($descripcionTutorialDetalle !== ''): ?>
                            <p class="pub-tutorial-detalle-descripcion">
                                <?= nl2br(
                                    escaparTutorialPublico($descripcionTutorialDetalle),
                                    false
                                ) ?>
                            </p>
                        <?php endif; ?>

                    </header>

                    <div class="pub-tutorial-reproductor">

                        <?php if (
                            ($tutorialDetalle['tipo_video'] ?? '') === 'youtube'
                            && $idYouTubeDetalle !== null
                        ): ?>

                            <div class="pub-tutorial-reproductor-marco">
                                <iframe
                                    src="https://www.youtube-nocookie.com/embed/<?= escaparTutorialPublico($idYouTubeDetalle) ?>"
                                    title="<?= escaparTutorialPublico($tituloTutorialDetalle) ?>"
                                    loading="lazy"
                                    referrerpolicy="strict-origin-when-cross-origin"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                    allowfullscreen
                                ></iframe>
                            </div>

                        <?php elseif (
                            ($tutorialDetalle['tipo_video'] ?? '') === 'archivo'
                            && $urlVideoLocalDetalle !== ''
                            && $tipoMimeDetalle !== ''
                        ): ?>

                            <video
                                class="pub-tutorial-video-local"
                                controls
                                playsinline
                                preload="metadata"
                            >
                                <source
                                    src="<?= escaparTutorialPublico($urlVideoLocalDetalle) ?>"
                                    type="<?= escaparTutorialPublico($tipoMimeDetalle) ?>"
                                >

                                Tu navegador no permite reproducir este video.
                                Puedes probar con una versión actualizada
                                de tu navegador.
                            </video>

                        <?php else: ?>

                            <div
                                class="pub-tutorial-video-no-disponible"
                                role="alert"
                            >
                                <span aria-hidden="true">⚠️</span>

                                <h2>Video no disponible</h2>

                                <p>
                                    No fue posible encontrar o reproducir
                                    el archivo asociado a este tutorial.
                                    Comunícate con el CESFAM si el problema
                                    continúa.
                                </p>
                            </div>

                        <?php endif; ?>

                    </div>

                    <footer class="pub-tutorial-detalle-acciones">
                        <a
                            class="pub-tutoriales-btn-volver"
                            href="<?= escaparTutorialPublico($urlVolverListado) ?>"
                        >
                            ← Volver al listado de tutoriales
                        </a>
                    </footer>

                </article>

            <?php else: ?>

                <div class="pub-tutoriales-vacio">

                    <span
                        class="pub-tutoriales-vacio-icono"
                        aria-hidden="true"
                    >
                        🎬
                    </span>

                    <h1 id="tituloDetalleTutorial">
                        Tutorial no disponible
                    </h1>

                    <p>
                        <?= escaparTutorialPublico($errorDetalleTutorial) ?>
                    </p>

                    <p>
                        Es posible que el tutorial haya sido retirado
                        de la publicación.
                    </p>

                    <a
                        class="pub-tutoriales-btn-volver"
                        href="tutoriales.php"
                    >
                        ← Volver al listado de tutoriales
                    </a>

                </div>

            <?php endif; ?>

        </div>
    </section>

<?php endif; ?>

</main>

<?php require_once __DIR__ . '/../componentes/footer_publico.php'; ?>

<!-- =========================================================
     JAVASCRIPT PÚBLICO DE TUTORIALES

     El funcionamiento de las vistas previas está separado
     en js/tutoriales_publicos.js.
     ========================================================= -->

<script
    src="../js/tutoriales_publicos.js?v=20261027-1"
    defer
></script>

</body>
</html>