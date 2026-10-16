<?php
/*
 * =========================================================
 * NOTICIAS PÚBLICAS
 * CESFAM EDUARDO RAGGIO CATEMU
 * Archivo: publicos/noticia.php
 * =========================================================
 */

$base_url = '/sistema toma de horas cesfam';

$noticia = null;
$noticiasPublicadas = [];
$errorNoticia = '';
$errorListadoNoticias = '';

$paginacionNoticiasPorPagina = 9;
$totalNoticias = 0;
$paginacionNoticiasTotalPaginas = 1;
$paginacionNoticiasPaginaActual = 1;
$paginacionNoticiasOffset = 0;

/* =========================================================
   VALIDAR PÁGINA
   ========================================================= */
$paginacionNoticiasPaginaRecibida = filter_var(
    $_GET['pagina'] ?? '1',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($paginacionNoticiasPaginaRecibida !== false && $paginacionNoticiasPaginaRecibida !== null) {
    $paginacionNoticiasPaginaActual = (int) $paginacionNoticiasPaginaRecibida;
}

/* =========================================================
   DETERMINAR LISTADO O DETALLE
   ========================================================= */
$modoDetalle = array_key_exists('id', $_GET);
$idNoticia = null;

if ($modoDetalle) {
    $idNoticia = filter_var(
        $_GET['id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($idNoticia === false || $idNoticia === null) {
        $errorNoticia = 'El identificador de la noticia no es válido.';
    }
}

/* =========================================================
   CONSULTAS
   ========================================================= */
try {
    require_once __DIR__ . '/../config/conexion.php';

    if (!isset($conexion) || !($conexion instanceof PDO)) {
        throw new RuntimeException(
            'No se encontró una conexión PDO válida en config/conexion.php.'
        );
    }

    if ($modoDetalle && $errorNoticia === '') {
        $sql = "
            SELECT
                n.id_noticia,
                n.titulo,
                n.resumen,
                n.contenido,
                n.fecha_publicacion,
                n.destacada,
                n.texto_alternativo_imagen,
                m.ruta_relativa,
                m.nombre_original
            FROM public.web_noticias AS n
            LEFT JOIN public.web_multimedia AS m
                ON m.id_multimedia = n.id_multimedia
            WHERE n.id_noticia = :id_noticia
              AND LOWER(BTRIM(CAST(n.estado AS TEXT))) = 'publicada'
            LIMIT 1
        ";

        $stmt = $conexion->prepare($sql);
        $stmt->execute([':id_noticia' => $idNoticia]);
        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$noticia) {
            $errorNoticia = 'La noticia no existe o ya no se encuentra publicada.';
        }
    } elseif (!$modoDetalle) {
        /*
         * IMPORTANTE:
         * La consulta de conteo y la consulta del listado usan el mismo
         * criterio de publicación para que coincidan las páginas calculadas.
         */
        $sqlConteo = "
            SELECT COUNT(*)
            FROM public.web_noticias
            WHERE LOWER(BTRIM(CAST(estado AS TEXT))) = 'publicada'
        ";

        $stmtConteo = $conexion->query($sqlConteo);
        $totalNoticias = (int) $stmtConteo->fetchColumn();

        $paginacionNoticiasTotalPaginas = max(
            1,
            (int) ceil($totalNoticias / $paginacionNoticiasPorPagina)
        );

        // Si solicitan una página fuera del rango, se ajusta a la última.
        $paginacionNoticiasPaginaActual = min($paginacionNoticiasPaginaActual, $paginacionNoticiasTotalPaginas);
        $paginacionNoticiasOffset = ($paginacionNoticiasPaginaActual - 1) * $paginacionNoticiasPorPagina;

        $sql = "
            SELECT
                n.id_noticia,
                n.titulo,
                n.resumen,
                n.contenido,
                n.fecha_publicacion,
                n.destacada,
                n.texto_alternativo_imagen,
                m.ruta_relativa,
                m.nombre_original
            FROM public.web_noticias AS n
            LEFT JOIN public.web_multimedia AS m
                ON m.id_multimedia = n.id_multimedia
            WHERE LOWER(BTRIM(CAST(n.estado AS TEXT))) = 'publicada'
            ORDER BY
                n.destacada DESC NULLS LAST,
                n.fecha_publicacion DESC NULLS LAST,
                n.id_noticia DESC
            LIMIT :limite
            OFFSET :offset
        ";

        $stmt = $conexion->prepare($sql);
        $stmt->bindValue(':limite', $paginacionNoticiasPorPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $paginacionNoticiasOffset, PDO::PARAM_INT);
        $stmt->execute();
        $noticiasPublicadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    error_log('Error en noticias públicas del CESFAM: ' . $e->getMessage());

    if ($modoDetalle) {
        if ($errorNoticia === '') {
            $errorNoticia = 'No fue posible cargar la noticia. Inténtalo nuevamente más tarde.';
        }
    } else {
        $errorListadoNoticias = 'No fue posible cargar las noticias. Inténtalo nuevamente más tarde.';
        $noticiasPublicadas = [];
        $totalNoticias = 0;
        $paginacionNoticiasTotalPaginas = 1;
        $paginacionNoticiasPaginaActual = 1;
        $paginacionNoticiasOffset = 0;
    }
}

/* =========================================================
   FUNCIONES DE PRESENTACIÓN
   ========================================================= */
function escaparDetalleNoticia($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function obtenerImagenDetalleNoticia($ruta): string
{
    $ruta = (string) ($ruta ?? '');

    if (
        $ruta === ''
        || strpos($ruta, 'uploads/multimedia/') !== 0
        || strpos($ruta, '..') !== false
        || strpos($ruta, '\\') !== false
        || strpos($ruta, '/') === 0
        || preg_match('/^[a-z][a-z0-9+.-]*:/i', $ruta)
    ) {
        return '';
    }

    return '../' . $ruta;
}

function resumenTarjetaNoticia($resumen, $contenido): string
{
    $texto = trim((string) ($resumen ?? ''));

    if ($texto === '') {
        $texto = trim((string) ($contenido ?? ''));
    }

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

function fechaDetalleNoticia($fecha): string
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

function esNoticiaPublicaDestacada($valor): bool
{
    return in_array($valor, [true, 1, '1', 't', 'true'], true);
}

/* =========================================================
   PREPARAR DATOS DEL DETALLE
   ========================================================= */
$tituloNoticia = '';
$resumenNoticia = '';
$contenidoNoticia = '';
$fechaNoticia = '';
$rutaImagenNoticia = '';
$textoAlternativoNoticia = '';
$esNoticiaDestacada = false;

if ($noticia) {
    $tituloNoticia = trim((string) ($noticia['titulo'] ?? ''));
    if ($tituloNoticia === '') {
        $tituloNoticia = 'Noticia del CESFAM';
    }

    $resumenNoticia = trim((string) ($noticia['resumen'] ?? ''));
    $contenidoNoticia = trim((string) ($noticia['contenido'] ?? ''));
    $fechaNoticia = fechaDetalleNoticia($noticia['fecha_publicacion'] ?? null);
    $rutaImagenNoticia = obtenerImagenDetalleNoticia($noticia['ruta_relativa'] ?? '');
    $textoAlternativoNoticia = trim((string) ($noticia['texto_alternativo_imagen'] ?? ''));

    if ($textoAlternativoNoticia === '') {
        $textoAlternativoNoticia = $tituloNoticia;
    }

    $esNoticiaDestacada = esNoticiaPublicaDestacada($noticia['destacada'] ?? false);
}

/* =========================================================
   METADATOS
   ========================================================= */
if ($modoDetalle && $noticia) {
    $tituloPagina = $tituloNoticia . ' | CESFAM Eduardo Raggio Catemu';
    $descripcionPagina = $resumenNoticia !== '' ? $resumenNoticia : $tituloNoticia;
} elseif ($modoDetalle) {
    $tituloPagina = 'Noticia no disponible | CESFAM Eduardo Raggio Catemu';
    $descripcionPagina = 'Noticias del CESFAM Eduardo Raggio Catemu.';
} else {
    $tituloPagina = 'Noticias del CESFAM Eduardo Raggio Catemu';
    $descripcionPagina = 'Consulta las noticias, actividades y novedades publicadas por el CESFAM Eduardo Raggio Catemu.';
}

/* =========================================================
   DATOS DE PAGINACIÓN Y DIAGNÓSTICO
   ========================================================= */
$desdeNoticia = $totalNoticias > 0 ? $paginacionNoticiasOffset + 1 : 0;
$hastaNoticia = min($paginacionNoticiasOffset + $paginacionNoticiasPorPagina, $totalNoticias);
$paginacionNoticiasPaginaInicio = max(1, $paginacionNoticiasPaginaActual - 2);
$paginacionNoticiasPaginaFin = min($paginacionNoticiasTotalPaginas, $paginacionNoticiasPaginaActual + 2);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= escaparDetalleNoticia($descripcionPagina) ?>">
    <title><?= escaparDetalleNoticia($tituloPagina) ?></title>

    <link rel="stylesheet" href="../css/compartidos/general.css">
    <link rel="stylesheet" href="../css/compartidos/navbar_publica.css">
    <link rel="stylesheet" href="../css/publicos/noticia.css?v=20261015-5">
    <link rel="stylesheet" href="../css/compartidos/footer_publico.css">
</head>
<body>

<?php require_once __DIR__ . '/../componentes/navbar_publica.php'; ?>

<main id="main-content">
<?php if (!$modoDetalle): ?>
    <section class="pub-section pub-news-section" id="noticias" aria-labelledby="tituloListadoNoticias">
        <div class="pub-section-inner">
            <nav class="pub-noticia-migas" aria-label="Ruta de navegación">
                <a href="index.php">Inicio</a>
                <span aria-hidden="true">›</span>
                <span aria-current="page">Noticias</span>
            </nav>

            <header class="pub-section-header">
                <span class="pub-section-tag">Actualidad del CESFAM</span>
                <h1 class="pub-section-title" id="tituloListadoNoticias">Noticias y novedades</h1>
                <p class="pub-section-desc">
                    Encuentra las noticias, actividades y avisos publicados por el CESFAM Eduardo Raggio Catemu.
                </p>
            </header>

            <?php if ($errorListadoNoticias !== ''): ?>
                <div class="pub-news-empty" role="alert">
                    <span class="pub-news-empty-icon" aria-hidden="true">⚠️</span>
                    <h2>No fue posible cargar las noticias</h2>
                    <p><?= escaparDetalleNoticia($errorListadoNoticias) ?></p>
                    <a class="pub-noticia-btn-volver" href="index.php">← Volver al inicio</a>
                </div>

            <?php elseif (!empty($noticiasPublicadas)): ?>
                <div class="pub-noticia-listado-resumen">
                    <p class="pub-noticia-listado-cantidad">
                        Mostrando <?= (int) $desdeNoticia ?>–<?= (int) $hastaNoticia ?> de <?= (int) $totalNoticias ?> noticias publicadas
                    </p>
                    <p class="pub-noticia-listado-pagina">
                        Página <?= (int) $paginacionNoticiasPaginaActual ?> de <?= (int) $paginacionNoticiasTotalPaginas ?>
                    </p>
                </div>

                <div class="pub-news-grid">
                    <?php foreach ($noticiasPublicadas as $item): ?>
                        <?php
                        $idTarjeta = (int) ($item['id_noticia'] ?? 0);
                        $tituloTarjeta = trim((string) ($item['titulo'] ?? ''));
                        if ($tituloTarjeta === '') {
                            $tituloTarjeta = 'Noticia del CESFAM';
                        }
                        $resumenTarjeta = resumenTarjetaNoticia(
                            $item['resumen'] ?? '',
                            $item['contenido'] ?? ''
                        );
                        $rutaImagenTarjeta = obtenerImagenDetalleNoticia($item['ruta_relativa'] ?? '');
                        $altTarjeta = trim((string) ($item['texto_alternativo_imagen'] ?? ''));
                        if ($altTarjeta === '') {
                            $altTarjeta = $tituloTarjeta;
                        }
                        $fechaTarjeta = fechaDetalleNoticia($item['fecha_publicacion'] ?? null);
                        $destacadaTarjeta = esNoticiaPublicaDestacada($item['destacada'] ?? false);
                        $urlTarjeta = 'noticia.php?id=' . rawurlencode((string) $idTarjeta);
                        ?>
                        <a class="pub-news-card pub-news-card-link"
                           href="<?= escaparDetalleNoticia($urlTarjeta) ?>"
                           aria-label="Leer noticia: <?= escaparDetalleNoticia($tituloTarjeta) ?>">
                            <?php if ($rutaImagenTarjeta !== ''): ?>
                                <div class="pub-news-image-wrapper">
                                    <img class="pub-news-image"
                                         src="<?= escaparDetalleNoticia($rutaImagenTarjeta) ?>"
                                         alt="<?= escaparDetalleNoticia($altTarjeta) ?>"
                                         loading="lazy">
                                    <?php if ($destacadaTarjeta): ?>
                                        <span class="pub-news-featured">★ Destacada</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="pub-news-image-placeholder" aria-hidden="true">
                                    <span class="pub-news-placeholder-icon">📰</span>
                                    <?php if ($destacadaTarjeta): ?>
                                        <span class="pub-news-featured">★ Destacada</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="pub-news-content">
                                <div class="pub-news-meta">
                                    <span class="pub-news-date">📅 <?= escaparDetalleNoticia($fechaTarjeta) ?></span>
                                </div>
                                <h2 class="pub-news-title"><?= escaparDetalleNoticia($tituloTarjeta) ?></h2>
                                <p class="pub-news-description">
                                    <?php if ($resumenTarjeta !== ''): ?>
                                        <?= escaparDetalleNoticia($resumenTarjeta) ?>
                                    <?php else: ?>
                                        Conoce más sobre esta novedad del CESFAM.
                                    <?php endif; ?>
                                </p>
                                <span class="pub-news-read-more">Leer noticia completa <span aria-hidden="true">→</span></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- PAGINACIÓN: aparece siempre que haya noticias cargadas. -->
                <nav class="pub-noticia-paginacion" aria-label="Paginación de noticias">
                    <div class="pub-noticia-paginacion-controles">
                        <?php if ($paginacionNoticiasPaginaActual > 1): ?>
                            <a class="pub-noticia-paginacion-btn pub-paginacion-anterior"
                               href="noticia.php?pagina=<?= (int) ($paginacionNoticiasPaginaActual - 1) ?>" rel="prev">
                                <span aria-hidden="true">←</span> Página anterior
                            </a>
                        <?php else: ?>
                            <span class="pub-noticia-paginacion-btn deshabilitado pub-paginacion-anterior" aria-disabled="true">
                                <span aria-hidden="true">←</span> Página anterior
                            </span>
                        <?php endif; ?>

                        <div class="pub-noticia-paginacion-numeros">
                            <?php if ($paginacionNoticiasPaginaInicio > 1): ?>
                                <a class="pub-noticia-paginacion-numero" href="noticia.php?pagina=1" aria-label="Ir a la página 1">1</a>
                                <?php if ($paginacionNoticiasPaginaInicio > 2): ?>
                                    <span class="pub-noticia-paginacion-ellipsis" aria-hidden="true">…</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($numeroPagina = $paginacionNoticiasPaginaInicio; $numeroPagina <= $paginacionNoticiasPaginaFin; $numeroPagina++): ?>
                                <?php if ($numeroPagina === $paginacionNoticiasPaginaActual): ?>
                                    <span class="pub-noticia-paginacion-numero activo" aria-current="page"
                                          aria-label="Página actual: <?= (int) $numeroPagina ?>"><?= (int) $numeroPagina ?></span>
                                <?php else: ?>
                                    <a class="pub-noticia-paginacion-numero"
                                       href="noticia.php?pagina=<?= (int) $numeroPagina ?>"
                                       aria-label="Ir a la página <?= (int) $numeroPagina ?>"><?= (int) $numeroPagina ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($paginacionNoticiasPaginaFin < $paginacionNoticiasTotalPaginas): ?>
                                <?php if ($paginacionNoticiasPaginaFin < $paginacionNoticiasTotalPaginas - 1): ?>
                                    <span class="pub-noticia-paginacion-ellipsis" aria-hidden="true">…</span>
                                <?php endif; ?>
                                <a class="pub-noticia-paginacion-numero"
                                   href="noticia.php?pagina=<?= (int) $paginacionNoticiasTotalPaginas ?>"
                                   aria-label="Ir a la última página"><?= (int) $paginacionNoticiasTotalPaginas ?></a>
                            <?php endif; ?>
                        </div>

                        <?php if ($paginacionNoticiasPaginaActual < $paginacionNoticiasTotalPaginas): ?>
                            <a class="pub-noticia-paginacion-btn pub-paginacion-siguiente"
                               href="noticia.php?pagina=<?= (int) ($paginacionNoticiasPaginaActual + 1) ?>" rel="next">
                                Página siguiente <span aria-hidden="true">→</span>
                            </a>
                        <?php else: ?>
                            <span class="pub-noticia-paginacion-btn deshabilitado pub-paginacion-siguiente" aria-disabled="true">
                                Página siguiente <span aria-hidden="true">→</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="pub-noticia-paginacion-indicador">
                        Página <?= (int) $paginacionNoticiasPaginaActual ?> de <?= (int) $paginacionNoticiasTotalPaginas ?>
                    </p>
                </nav>

            <?php else: ?>
                <div class="pub-news-empty">
                    <span class="pub-news-empty-icon" aria-hidden="true">📰</span>
                    <h2>Todavía no hay noticias publicadas</h2>
                    <p>Cuando el CESFAM publique noticias y novedades, podrás encontrarlas aquí.</p>
                    <a class="pub-noticia-btn-volver" href="index.php">← Volver al inicio</a>
                </div>
            <?php endif; ?>

            <div class="pub-noticia-listado-pie pub-noticia-listado-acciones">
                <a class="pub-noticia-btn-volver" href="index.php">← Volver al portal del CESFAM</a>
                
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="pub-section pub-noticia-detalle-seccion" aria-labelledby="tituloDetalleNoticia">
        <div class="pub-section-inner">
            <nav class="pub-noticia-migas" aria-label="Ruta de navegación">
                <a href="index.php">Inicio</a>
                <span aria-hidden="true">›</span>
                <a href="noticia.php">Noticias</a>
                <?php if ($noticia): ?>
                    <span aria-hidden="true">›</span>
                    <span aria-current="page"><?= escaparDetalleNoticia($tituloNoticia) ?></span>
                <?php endif; ?>
            </nav>

            <?php if ($noticia): ?>
                <article class="pub-noticia-detalle">
                    <header class="pub-noticia-detalle-encabezado">
                        <?php if ($esNoticiaDestacada): ?>
                            <span class="pub-news-featured pub-noticia-etiqueta-detalle">★ Noticia destacada</span>
                        <?php endif; ?>
                        <h1 class="pub-noticia-detalle-titulo" id="tituloDetalleNoticia"><?= escaparDetalleNoticia($tituloNoticia) ?></h1>
                        <div class="pub-noticia-detalle-meta">
                            <span>📅 Publicado el <?= escaparDetalleNoticia($fechaNoticia) ?></span>
                        </div>
                        <?php if ($resumenNoticia !== ''): ?>
                            <p class="pub-noticia-detalle-resumen"><?= escaparDetalleNoticia($resumenNoticia) ?></p>
                        <?php endif; ?>
                    </header>

                    <?php if ($rutaImagenNoticia !== ''): ?>
                        <figure class="pub-noticia-detalle-figura">
                            <img class="pub-noticia-detalle-imagen"
                                 src="<?= escaparDetalleNoticia($rutaImagenNoticia) ?>"
                                 alt="<?= escaparDetalleNoticia($textoAlternativoNoticia) ?>"
                                 fetchpriority="high">
                            <?php if (!empty($noticia['nombre_original'])): ?>
                                <figcaption><?= escaparDetalleNoticia($noticia['nombre_original']) ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endif; ?>

                    <div class="pub-noticia-detalle-contenido">
                        <?php if ($contenidoNoticia !== ''): ?>
                            <?= nl2br(escaparDetalleNoticia($contenidoNoticia), false) ?>
                        <?php else: ?>
                            <p>No hay contenido adicional disponible para esta noticia.</p>
                        <?php endif; ?>
                    </div>

                    <footer class="pub-noticia-detalle-acciones">
                        <a class="pub-noticia-btn-volver" href="noticia.php">← Volver al listado de noticias</a>
                    </footer>
                </article>
            <?php else: ?>
                <div class="pub-noticia-no-disponible">
                    <span class="pub-noticia-no-disponible-icono" aria-hidden="true">📰</span>
                    <h1 id="tituloDetalleNoticia">Noticia no disponible</h1>
                    <p><?= escaparDetalleNoticia($errorNoticia) ?></p>
                    <p>Es posible que la noticia haya sido retirada de la publicación.</p>
                    <a class="pub-noticia-btn-volver" href="noticia.php">← Volver al listado de noticias</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
</main>

<?php require_once __DIR__ . '/../componentes/footer_publico.php'; ?>

</body>
</html>
