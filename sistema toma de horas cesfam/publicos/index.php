<?php
/*
 * Portal de Salud — CESFAM Eduardo Raggio Catemu
 * Archivo: publicos/index.php
 *
 * Actualización:
 * - Integración de noticias publicadas desde PostgreSQL.
 * - Máximo 2 noticias destacadas.
 * - Se agrega 1 noticia reciente no destacada.
 * - Se cargan imágenes desde la biblioteca multimedia.
 * - Se mantiene el contenido original del portal.
 */

$base_url = '/sistema toma de horas cesfam';

/*
 * =========================================================
 * CARGAR NOTICIAS PARA LA PÁGINA PÚBLICA
 * =========================================================
 *
 * Se consultan directamente las noticias publicadas.
 *
 * Distribución:
 * 1. Las 2 noticias destacadas más recientes.
 * 2. La noticia no destacada más reciente.
 *
 * Total máximo: 3 noticias.
 *
 * Las noticias en borrador, inactivas o en papelera
 * no se muestran en el portal público.
 */

$noticiasPublicadas = [];

try {
    require_once __DIR__ . '/../config/conexion.php';

    /*
     * =========================================================
     * OBTENER LAS 2 NOTICIAS DESTACADAS MÁS RECIENTES
     * =========================================================
     */

    $sqlDestacadas = "
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
        WHERE
            n.estado = 'publicada'
            AND n.destacada IS TRUE
        ORDER BY
            n.fecha_publicacion DESC NULLS LAST,
            n.id_noticia DESC
        LIMIT 2
    ";

    $stmtDestacadas = $conexion->query($sqlDestacadas);

    $noticiasDestacadas = $stmtDestacadas->fetchAll(
        PDO::FETCH_ASSOC
    );

    /*
     * =========================================================
     * OBTENER LA NOTICIA RECIENTE NO DESTACADA
     * =========================================================
     *
     * Se excluyen las destacadas para evitar que una misma
     * noticia aparezca dos veces en la página.
     */

    $sqlReciente = "
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
        WHERE
            n.estado = 'publicada'
            AND n.destacada IS NOT TRUE
        ORDER BY
            n.fecha_publicacion DESC NULLS LAST,
            n.id_noticia DESC
        LIMIT 1
    ";

    $stmtReciente = $conexion->query($sqlReciente);

    $noticiasRecientes = $stmtReciente->fetchAll(
        PDO::FETCH_ASSOC
    );

    /*
     * =========================================================
     * COMBINAR LAS NOTICIAS PARA LA PÁGINA PÚBLICA
     * =========================================================
     *
     * Primero se presentan las destacadas y luego la reciente.
     */

    $noticiasPublicadas = array_merge(
        $noticiasDestacadas,
        $noticiasRecientes
    );

} catch (Throwable $e) {
    error_log(
        'Error al cargar noticias públicas del CESFAM: '
        . $e->getMessage()
    );

    $noticiasPublicadas = [];
}

/*
 * =========================================================
 * UTILIDADES DE PRESENTACIÓN DE NOTICIAS
 * =========================================================
 */

/**
 * Escapa texto para mostrarlo de forma segura en HTML.
 */
function escaparTextoNoticiaPublica($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Devuelve una ruta de imagen permitida desde la biblioteca.
 */
function obtenerRutaImagenNoticiaPublica($ruta): string
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

/**
 * Prepara el texto de una tarjeta y limita su longitud.
 */
function prepararTextoNoticiaPublica($resumen, $contenido): string
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
            $texto = mb_substr($texto, 0, 200, 'UTF-8') . '…';
        }
    } elseif (strlen($texto) > 200) {
        $texto = substr($texto, 0, 197) . '...';
    }

    return $texto;
}

/**
 * Formatea la fecha de publicación para los visitantes.
 */
function formatearFechaNoticiaPublica($fecha): string
{
    if (empty($fecha)) {
        return 'Fecha no disponible';
    }

    try {
        $fechaConvertida = new DateTime((string) $fecha);

        return $fechaConvertida->format('d/m/Y');
    } catch (Throwable $e) {
        return 'Fecha no disponible';
    }
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="CESFAM Eduardo Raggio Catemu — Centro de Salud Familiar. Conoce nuestros servicios, teléfonos de atención, ubicación y canales de atención."
    >

    <title>CESFAM Eduardo Raggio Catemu — Portal de Salud</title>

    <!-- ESTILOS PÚBLICOS -->
    <link rel="stylesheet" href="../css/compartidos/general.css">
    <link rel="stylesheet" href="../css/compartidos/navbar_publica.css">
    <link rel="stylesheet" href="../css/publicos/index.css">
    <link rel="stylesheet" href="../css/compartidos/footer_publico.css">

</head>

<body>

    <!-- NAVBAR Y ACCESIBILIDAD -->
    <?php require_once __DIR__ . '/../componentes/navbar_publica.php'; ?>


    <main id="main-content">


        <!-- =====================================================
             HERO SECTION
             ===================================================== -->

        <section class="pub-hero" id="inicio" aria-labelledby="heroTitle">

            <div class="pub-hero-inner">

                <div class="pub-hero-content">

                    <div class="pub-hero-badge">
                        🏥 Centro de Salud Familiar — Atención Primaria
                    </div>

                    <h1 id="heroTitle">
                        Bienvenido al CESFAM Eduardo Raggio Catemu
                    </h1>

                    <p class="pub-hero-subtitle">
                        Gestiona tus horas de atención y encuentra información
                        sobre nuestros servicios de salud de manera simple y accesible.
                    </p>

                    <div class="pub-hero-actions">

                        <!-- TOMAR HORA -->
                        <a
                            class="pub-btn-hero-primary"
                            href="toma_horas.php"
                            aria-label="Tomar una hora de atención"
                        >
                            Tomar Hora
                        </a>

                        <a
                            class="pub-btn-hero-secondary"
                            href="#servicios"
                        >
                            📋 Conocer nuestros servicios
                        </a>

                    </div>

                </div>


                <!-- INFORMACIÓN GENERAL -->
                <aside
                    class="pub-hero-card"
                    aria-label="Información general del CESFAM"
                >

                    <p class="pub-hero-card-title">
                        📊 CESFAM Eduardo Raggio Catemu
                    </p>


                    <div class="pub-hero-stat">

                        <span
                            class="pub-hero-stat-icon"
                            aria-hidden="true"
                        >
                            👨‍👩‍👧‍👦
                        </span>

                        <div class="pub-hero-stat-text">

                            <span class="pub-hero-stat-value">
                                Atención Familiar
                            </span>

                            <span class="pub-hero-stat-label">
                                Salud integral para toda la familia
                            </span>

                        </div>

                    </div>


                    <div class="pub-hero-stat">

                        <span
                            class="pub-hero-stat-icon"
                            aria-hidden="true"
                        >
                            🩺
                        </span>

                        <div class="pub-hero-stat-text">

                            <span class="pub-hero-stat-value">
                                Atención Primaria
                            </span>

                            <span class="pub-hero-stat-label">
                                Prevención, promoción y cuidado de la salud
                            </span>

                        </div>

                    </div>


                    <div class="pub-hero-stat">

                        <span
                            class="pub-hero-stat-icon"
                            aria-hidden="true"
                        >
                            📞
                        </span>

                        <div class="pub-hero-stat-text">

                            <span class="pub-hero-stat-value">
                                Atención telefónica
                            </span>

                            <span class="pub-hero-stat-label">
                                Diversos canales según el tipo de atención
                            </span>

                        </div>

                    </div>


                    <div class="pub-hero-stat">

                        <span
                            class="pub-hero-stat-icon"
                            aria-hidden="true"
                        >
                            📍
                        </span>

                        <div class="pub-hero-stat-text">

                            <span class="pub-hero-stat-value">
                                El Arrayán
                            </span>

                            <span class="pub-hero-stat-label">
                                Camino Troncal, El Arrayán N°300, Catemu
                            </span>

                        </div>

                    </div>

                </aside>

            </div>

        </section>


        <!-- =====================================================
             AVISO DE EMERGENCIAS
             ===================================================== -->

        <div
            class="pub-emergency-banner"
            role="alert"
        >

            <div class="pub-emergency-inner">

                <span
                    class="pub-emergency-icon"
                    aria-hidden="true"
                >
                    🚨
                </span>

                <p class="pub-emergency-text">

                    <strong>Emergencia médica vital:</strong>
                    Llama al <strong>131 (SAMU)</strong>
                    o acude al <strong>SAPU / SAR</strong> más cercano.
                    Este portal no atiende emergencias.

                </p>

            </div>

        </div>


        <!-- =====================================================
             NOTICIAS DEL CESFAM
             Máximo 2 destacadas y 1 reciente no destacada.
             ===================================================== -->

        <section
            class="pub-section pub-news-section"
            id="noticias"
            aria-labelledby="noticiasTitle"
        >

            <div class="pub-section-inner">

                <div class="pub-section-header">

                    <span class="pub-section-tag">
                        Actualidad del CESFAM
                    </span>

                    <h2
                        class="pub-section-title"
                        id="noticiasTitle"
                    >
                        Noticias y novedades
                    </h2>

                    <p class="pub-section-desc">
                        Infórmate sobre las novedades, actividades y avisos
                        publicados por el CESFAM Eduardo Raggio Catemu.
                    </p>

                </div>


                <?php if (!empty($noticiasPublicadas)): ?>

                    <div class="pub-news-grid">

                        <?php foreach ($noticiasPublicadas as $noticia): ?>

                            <?php
                            $tituloNoticia = trim(
                                (string) ($noticia['titulo'] ?? '')
                            );

                            $tituloNoticia = $tituloNoticia !== ''
                                ? $tituloNoticia
                                : 'Noticia del CESFAM';

                            $textoNoticia = prepararTextoNoticiaPublica(
                                $noticia['resumen'] ?? '',
                                $noticia['contenido'] ?? ''
                            );

                            $rutaImagenNoticia = obtenerRutaImagenNoticiaPublica(
                                $noticia['ruta_relativa'] ?? ''
                            );

                            $textoAlternativo = trim(
                                (string) (
                                    $noticia['texto_alternativo_imagen'] ?? ''
                                )
                            );

                            if ($textoAlternativo === '') {
                                $textoAlternativo = $tituloNoticia;
                            }

                            $fechaNoticia = formatearFechaNoticiaPublica(
                                $noticia['fecha_publicacion'] ?? null
                            );

                            $esNoticiaDestacada = in_array(
                                $noticia['destacada'] ?? false,
                                [true, 1, '1', 't', 'true'],
                                true
                            );
                            ?>

                            <article class="pub-news-card">

                                <?php if ($rutaImagenNoticia !== ''): ?>

                                    <div class="pub-news-image-wrapper">

                                        <img
                                            class="pub-news-image"
                                            src="<?= escaparTextoNoticiaPublica($rutaImagenNoticia) ?>"
                                            alt="<?= escaparTextoNoticiaPublica($textoAlternativo) ?>"
                                            loading="lazy"
                                        >

                                        <?php if ($esNoticiaDestacada): ?>

                                            <span class="pub-news-featured">
                                                ★ Destacada
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                <?php else: ?>

                                    <div
                                        class="pub-news-image-placeholder"
                                        aria-hidden="true"
                                    >
                                        📰
                                    </div>

                                <?php endif; ?>


                                <div class="pub-news-content">

                                    <div class="pub-news-meta">

                                        <span class="pub-news-date">
                                            📅
                                            <?= escaparTextoNoticiaPublica($fechaNoticia) ?>
                                        </span>

                                        <?php if ($esNoticiaDestacada && $rutaImagenNoticia === ''): ?>

                                            <span class="pub-news-featured">
                                                ★ Destacada
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <h3 class="pub-news-title">
                                        <?= escaparTextoNoticiaPublica($tituloNoticia) ?>
                                    </h3>


                                    <?php if ($textoNoticia !== ''): ?>

                                        <p class="pub-news-description">
                                            <?= escaparTextoNoticiaPublica($textoNoticia) ?>
                                        </p>

                                    <?php else: ?>

                                        <p class="pub-news-description">
                                            Revisa esta novedad publicada por el CESFAM.
                                        </p>

                                    <?php endif; ?>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php else: ?>

                    <div class="pub-news-empty">

                        <span
                            class="pub-news-empty-icon"
                            aria-hidden="true"
                        >
                            📰
                        </span>

                        <h3>
                            Próximamente encontrarás nuestras noticias aquí
                        </h3>

                        <p>
                            En este momento no hay noticias publicadas.
                            Vuelve a visitarnos para conocer las novedades del CESFAM.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

        </section>


        <!-- =====================================================
             ACCIONES RÁPIDAS
             ===================================================== -->

        <section
            class="pub-section"
            id="acciones"
            aria-labelledby="accionesTitle"
        >

            <div class="pub-section-inner">

                <div class="pub-section-header">

                    <span class="pub-section-tag">
                        Portal de Pacientes
                    </span>

                    <h2
                        class="pub-section-title"
                        id="accionesTitle"
                    >
                        ¿Qué necesitas hacer?
                    </h2>

                    <p class="pub-section-desc">
                        Accede a los servicios e información del CESFAM
                        de manera rápida y sencilla.
                    </p>

                </div>


                <div class="pub-actions-grid">

                    <!-- RESERVAR UNA HORA -->

                    <a
                        class="pub-action-card"
                        href="toma_horas.php"
                        aria-label="Reservar una hora"
                    >

                        <div
                            class="pub-action-icon blue"
                            aria-hidden="true"
                        >
                            📅
                        </div>

                        <span class="pub-action-name">
                            Reservar una hora
                        </span>

                        <span class="pub-action-desc">
                            Gestiona una hora de atención disponible
                            en el sistema.
                        </span>

                    </a>


                    <!-- INFORMACIÓN -->

                    <a
                        class="pub-action-card"
                        href="#informacion"
                        aria-label="Ver información general del CESFAM"
                    >

                        <div
                            class="pub-action-icon teal"
                            aria-hidden="true"
                        >
                            ℹ️
                        </div>

                        <span class="pub-action-name">
                            Información del CESFAM
                        </span>

                        <span class="pub-action-desc">
                            Conoce nuestros servicios, programas de salud
                            y funcionamiento.
                        </span>

                    </a>


                    <!-- CONTACTO -->

                    <a
                        class="pub-action-card"
                        href="#contacto"
                        aria-label="Ver contacto y ubicación"
                    >

                        <div
                            class="pub-action-icon orange"
                            aria-hidden="true"
                        >
                            📞
                        </div>

                        <span class="pub-action-name">
                            Contacto y ubicación
                        </span>

                        <span class="pub-action-desc">
                            Consulta teléfonos, dirección y canales
                            digitales de atención.
                        </span>

                    </a>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SOBRE NUESTRO CESFAM
             ===================================================== -->

        <section
            class="pub-section alt-bg"
            id="informacion"
            aria-labelledby="sobreTitle"
        >

            <div class="pub-section-inner">

                <div class="pub-about-grid">


                    <div class="pub-about-content">

                        <div class="pub-section-header">

                            <span class="pub-section-tag">
                                Quiénes somos
                            </span>

                            <h2
                                class="pub-section-title"
                                id="sobreTitle"
                            >
                                Sobre nuestro CESFAM
                            </h2>

                        </div>


                        <p class="pub-about-description">

                            El Centro de Salud Familiar Dr. Eduardo Raggio Lanata
                            es el principal establecimiento público de atención
                            primaria municipal de la comuna de Catemu.

                            Su labor está enfocada en la prevención, promoción
                            y cuidado de la salud familiar y comunitaria.

                        </p>


                        <ul class="pub-pillar-list">

                            <li class="pub-pillar-item">

                                <span
                                    class="pub-pillar-icon"
                                    aria-hidden="true"
                                >
                                    🏥
                                </span>

                                <span>
                                    <strong>Atención Primaria de Salud</strong>
                                    con enfoque familiar y preventivo.
                                </span>

                            </li>


                            <li class="pub-pillar-item">

                                <span
                                    class="pub-pillar-icon"
                                    aria-hidden="true"
                                >
                                    👩‍⚕️
                                </span>

                                <span>
                                    <strong>Equipo de Salud</strong>
                                    orientado a la prevención, atención
                                    y cuidado de la comunidad.
                                </span>

                            </li>


                            <li class="pub-pillar-item">

                                <span
                                    class="pub-pillar-icon"
                                    aria-hidden="true"
                                >
                                    📋
                                </span>

                                <span>
                                    <strong>Servicios y Programas de Salud</strong>
                                    dirigidos a diferentes etapas del ciclo vital.
                                </span>

                            </li>


                            <li class="pub-pillar-item">

                                <span
                                    class="pub-pillar-icon"
                                    aria-hidden="true"
                                >
                                    🤝
                                </span>

                                <span>
                                    <strong>Orientación y Acompañamiento</strong>
                                    cercano a los usuarios.
                                </span>

                            </li>


                            <li class="pub-pillar-item">

                                <span
                                    class="pub-pillar-icon"
                                    aria-hidden="true"
                                >
                                    🌱
                                </span>

                                <span>
                                    <strong>Compromiso Comunitario</strong>
                                    con la salud de la comuna de Catemu.
                                </span>

                            </li>

                        </ul>

                    </div>


                    <div class="pub-about-visual">

                        <div class="pub-about-stat-card">

                            <div class="pub-about-stat-value">
                                🩺
                            </div>

                            <div class="pub-about-stat-label">
                                Atención Primaria
                            </div>

                        </div>


                        <div class="pub-about-stat-card">

                            <div class="pub-about-stat-value">
                                👨‍👩‍👧‍👦
                            </div>

                            <div class="pub-about-stat-label">
                                Salud Familiar
                            </div>

                        </div>


                        <div class="pub-about-stat-card">

                            <div class="pub-about-stat-value">
                                📅
                            </div>

                            <div class="pub-about-stat-label">
                                Toma de Horas
                            </div>

                        </div>


                        <div class="pub-about-stat-card">

                            <div class="pub-about-stat-value">
                                🤖
                            </div>

                            <div class="pub-about-stat-label">
                                Asistente IA de orientación
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             SERVICIOS
             ===================================================== -->

        <section
            class="pub-section"
            id="servicios"
            aria-labelledby="serviciosTitle"
        >

            <div class="pub-section-inner">

                <div class="pub-section-header">

                    <span class="pub-section-tag">
                        Atención médica
                    </span>

                    <h2
                        class="pub-section-title"
                        id="serviciosTitle"
                    >
                        Nuestros servicios
                    </h2>

                    <p class="pub-section-desc">
                        Conoce algunos de los principales servicios y áreas
                        de atención del CESFAM Eduardo Raggio Lanata.
                    </p>

                </div>


                <div class="pub-services-grid">


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            🩺
                        </div>

                        <h3 class="pub-service-name">
                            Medicina General
                        </h3>

                        <p class="pub-service-desc">
                            Atención médica general, evaluación,
                            orientación y seguimiento clínico.
                        </p>

                    </article>


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            💉
                        </div>

                        <h3 class="pub-service-name">
                            Enfermería
                        </h3>

                        <p class="pub-service-desc">
                            Curaciones, vacunatorio, controles de salud
                            y procedimientos clínicos ambulatorios.
                        </p>

                    </article>


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            🦷
                        </div>

                        <h3 class="pub-service-name">
                            Odontología
                        </h3>

                        <p class="pub-service-desc">
                            Atención odontológica preventiva y resolutiva
                            según los programas correspondientes.
                        </p>

                    </article>


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            🌸
                        </div>

                        <h3 class="pub-service-name">
                            Matronería
                        </h3>

                        <p class="pub-service-desc">
                            Atención relacionada con salud sexual y
                            reproductiva, control prenatal y planificación familiar.
                        </p>

                    </article>


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            🧠
                        </div>

                        <h3 class="pub-service-name">
                            Salud Mental
                        </h3>

                        <p class="pub-service-desc">
                            Atención y orientación en salud mental,
                            apoyo psicológico y familiar.
                        </p>

                    </article>


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            🥗
                        </div>

                        <h3 class="pub-service-name">
                            Nutrición
                        </h3>

                        <p class="pub-service-desc">
                            Evaluación nutricional y orientación para
                            una alimentación saludable.
                        </p>

                    </article>


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            🏃
                        </div>

                        <h3 class="pub-service-name">
                            Kinesiología
                        </h3>

                        <p class="pub-service-desc">
                            Atención y rehabilitación física según
                            las necesidades de los usuarios.
                        </p>

                    </article>


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            💊
                        </div>

                        <h3 class="pub-service-name">
                            Farmacia
                        </h3>

                        <p class="pub-service-desc">
                            Entrega de medicamentos prescritos según
                            corresponda a los usuarios.
                        </p>

                    </article>


                    <article class="pub-service-card">

                        <div
                            class="pub-service-icon"
                            aria-hidden="true"
                        >
                            🧪
                        </div>

                        <h3 class="pub-service-name">
                            Toma de Muestras
                        </h3>

                        <p class="pub-service-desc">
                            Exámenes de sangre, orina y otros análisis
                            de laboratorio bajo agenda.
                        </p>

                    </article>

                </div>

            </div>

        </section>


        <!-- =====================================================
             HORARIOS Y UBICACIÓN
             ===================================================== -->

        <section
            class="pub-section alt-bg"
            id="horarios"
            aria-labelledby="horariosTitle"
        >

            <div class="pub-section-inner">

                <div class="pub-section-header">

                    <span class="pub-section-tag">
                        Información general
                    </span>

                    <h2
                        class="pub-section-title"
                        id="horariosTitle"
                    >
                        Ubicación y atención
                    </h2>

                </div>


                <div class="pub-schedule-grid">


                    <div class="pub-schedule-card">

                        <div
                            class="pub-schedule-icon"
                            aria-hidden="true"
                        >
                            📍
                        </div>

                        <div class="pub-schedule-day">
                            Dirección
                        </div>

                        <div class="pub-schedule-time">
                            Camino Troncal,
                            El Arrayán N°300,
                            Catemu
                        </div>

                    </div>


                    <div class="pub-schedule-card">

                        <div
                            class="pub-schedule-icon"
                            aria-hidden="true"
                        >
                            🏛️
                        </div>

                        <div class="pub-schedule-day">
                            Administración
                        </div>

                        <div class="pub-schedule-time">
                            Ilustre Municipalidad
                            de Catemu
                        </div>

                    </div>


                    <div class="pub-schedule-card">

                        <div
                            class="pub-schedule-icon"
                            aria-hidden="true"
                        >
                            🌐
                        </div>

                        <div class="pub-schedule-day">
                            Gestión en línea
                        </div>

                        <div class="pub-schedule-time">
                            Telesalud Minsal
                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             TELÉFONOS DE ATENCIÓN
             ===================================================== -->

        <section
            class="pub-section"
            id="telefonos"
            aria-labelledby="telefonosTitle"
        >

            <div class="pub-section-inner">

                <div class="pub-section-header">

                    <span class="pub-section-tag">
                        Atención al usuario
                    </span>

                    <h2
                        class="pub-section-title"
                        id="telefonosTitle"
                    >
                        📞 Teléfonos de Atención y Citas
                    </h2>

                    <p class="pub-section-desc">
                        Utiliza el número correspondiente según el tipo
                        de atención que necesites.
                    </p>

                </div>


                <div class="pub-contact-grid">


                    <!-- HORAS MÉDICAS -->
                    <a
                        class="pub-contact-card"
                        href="tel:800432929"
                    >

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            📞
                        </div>

                        <span class="pub-contact-label">
                            Horas Médicas del Día
                        </span>

                        <span class="pub-contact-value">
                            800 432 929
                        </span>

                        <span class="pub-contact-label">
                            Disponible desde las 06:45 AM
                        </span>

                    </a>


                    <!-- CENTRAL -->
                    <a
                        class="pub-contact-card"
                        href="tel:+56342492536"
                    >

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            ☎️
                        </div>

                        <span class="pub-contact-label">
                            Central Telefónica General
                        </span>

                        <span class="pub-contact-value">
                            +56 34 249 2536
                        </span>

                        <span class="pub-contact-label">
                            También: +56 34 224 92500
                        </span>

                    </a>


                    <!-- DENTAL -->
                    <a
                        class="pub-contact-card"
                        href="tel:+56342492530"
                    >

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            🦷
                        </div>

                        <span class="pub-contact-label">
                            Citas Dentales
                        </span>

                        <span class="pub-contact-value">
                            +56 34 249 2530
                        </span>

                        <span class="pub-contact-label">
                            07:30 a 08:00 AM
                        </span>

                    </a>


                    <!-- RESPIRATORIAS -->
                    <a
                        class="pub-contact-card"
                        href="tel:+56342492545"
                    >

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            🫁
                        </div>

                        <span class="pub-contact-label">
                            Consultas Respiratorias
                        </span>

                        <span class="pub-contact-value">
                            +56 34 249 2545
                        </span>

                        <span class="pub-contact-label">
                            07:30 a 09:00 AM
                        </span>

                    </a>

                </div>

            </div>

        </section>


        <!-- =====================================================
             CANALES DIGITALES
             ===================================================== -->

        <section
            class="pub-section alt-bg"
            id="canales"
            aria-labelledby="canalesTitle"
        >

            <div class="pub-section-inner">

                <div class="pub-section-header">

                    <span class="pub-section-tag">
                        Atención digital
                    </span>

                    <h2
                        class="pub-section-title"
                        id="canalesTitle"
                    >
                        🌐 Canales Digitales y Gestión en Línea
                    </h2>

                    <p class="pub-section-desc">
                        Accede a los canales digitales relacionados con
                        la atención de salud y la información municipal.
                    </p>

                </div>


                <div class="pub-contact-grid">


                    <!-- TELESALUD -->
                    <a
                        class="pub-contact-card"
                        href="https://telesalud.gob.cl/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            🩺
                        </div>

                        <span class="pub-contact-label">
                            Solicitud de Horas
                        </span>

                        <span class="pub-contact-value">
                            Telesalud
                        </span>

                        <span class="pub-contact-label">
                            telesalud.gob.cl
                        </span>

                    </a>


                    <!-- MUNICIPALIDAD -->
                    <a
                        class="pub-contact-card"
                        href="https://municatemu.cl/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            🏛️
                        </div>

                        <span class="pub-contact-label">
                            Información Municipal
                        </span>

                        <span class="pub-contact-value">
                            Municipalidad de Catemu
                        </span>

                        <span class="pub-contact-label">
                            municatemu.cl
                        </span>

                    </a>

                </div>

            </div>

        </section>


        <!-- =====================================================
             CONTACTO Y UBICACIÓN
             ===================================================== -->

        <section
            class="pub-section"
            id="contacto"
            aria-labelledby="contactoTitle"
        >

            <div class="pub-section-inner">

                <div class="pub-section-header">

                    <span class="pub-section-tag">
                        Atención al usuario
                    </span>

                    <h2
                        class="pub-section-title"
                        id="contactoTitle"
                    >
                        Contáctanos
                    </h2>

                </div>


                <div class="pub-contact-grid">


                    <!-- CENTRAL -->
                    <a
                        class="pub-contact-card"
                        href="tel:+56342492536"
                    >

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            📞
                        </div>

                        <span class="pub-contact-label">
                            Central Telefónica
                        </span>

                        <span class="pub-contact-value">
                            +56 34 249 2536
                        </span>

                    </a>


                    <!-- DENTAL -->
                    <a
                        class="pub-contact-card"
                        href="tel:+56342492530"
                    >

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            🦷
                        </div>

                        <span class="pub-contact-label">
                            Citas Dentales
                        </span>

                        <span class="pub-contact-value">
                            +56 34 249 2530
                        </span>

                    </a>


                    <!-- DIRECCIÓN -->
                    <div class="pub-contact-card">

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            📍
                        </div>

                        <span class="pub-contact-label">
                            Dirección
                        </span>

                        <span class="pub-contact-value">
                            Camino Troncal,
                            El Arrayán N°300,
                            Catemu
                        </span>

                    </div>


                    <!-- ADMINISTRACIÓN -->
                    <div class="pub-contact-card">

                        <div
                            class="pub-contact-icon"
                            aria-hidden="true"
                        >
                            🏛️
                        </div>

                        <span class="pub-contact-label">
                            Administración
                        </span>

                        <span class="pub-contact-value">
                            Ilustre Municipalidad
                            de Catemu
                        </span>

                    </div>

                </div>

            </div>

        </section>

    </main>


    <!-- =====================================================
         FOOTER PÚBLICO
         ===================================================== -->

    <?php require_once __DIR__ . '/../componentes/footer_publico.php'; ?>


    <!-- =====================================================
         MODAL DE INICIO DE SESIÓN
         ===================================================== -->

    <div
        class="pub-modal-overlay"
        id="loginModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalTitle"
    >

        <div class="pub-modal-box">

            <div class="pub-modal-header">

                <div>

                    <h2
                        class="pub-modal-title"
                        id="modalTitle"
                    >
                        🔑 Iniciar sesión
                    </h2>

                    <p
                        class="pub-modal-subtitle"
                        id="modalSubtitle"
                    >
                        Ingresa con tu RUT y contraseña para acceder a tu portal.
                    </p>

                </div>

                <button
                    class="pub-modal-close"
                    id="btnModalClose"
                    aria-label="Cerrar"
                >
                    ✕
                </button>

            </div>


            <div class="pub-modal-body">

                <div
                    class="pub-modal-context hidden"
                    id="modalContext"
                    role="status"
                    aria-live="polite"
                >

                    <span aria-hidden="true">
                        ℹ️
                    </span>

                    <span id="modalContextText"></span>

                </div>


                <form
                    id="loginForm"
                    novalidate
                >

                    <div class="pub-form-group">

                        <label
                            class="pub-form-label"
                            for="inputRut"
                        >
                            RUT del paciente
                        </label>

                        <input
                            class="pub-form-input"
                            type="text"
                            id="inputRut"
                            name="rut"
                            placeholder="Ej: 12.345.678-9"
                            maxlength="12"
                            autocomplete="username"
                        />

                        <span
                            class="pub-form-error"
                            id="rutError"
                        >
                            Por favor ingresa un RUT válido.
                        </span>

                    </div>


                    <div class="pub-form-group">

                        <label
                            class="pub-form-label"
                            for="inputPassword"
                        >
                            Contraseña
                        </label>

                        <input
                            class="pub-form-input"
                            type="password"
                            id="inputPassword"
                            name="password"
                            placeholder="Ingresa tu contraseña"
                            autocomplete="current-password"
                        />

                        <span
                            class="pub-form-error"
                            id="passError"
                        >
                            Por favor ingresa tu contraseña.
                        </span>

                    </div>


                    <div class="pub-modal-login-actions">

                        <button
                            type="submit"
                            class="pub-btn-submit-login"
                        >
                            🔑 Ingresar a mi portal
                        </button>

                        <button
                            type="button"
                            class="pub-btn-claveunica"
                            id="btnClaveUnica"
                        >
                            🇨🇱 Ingresar con ClaveÚnica
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ASISTENTE IA FLOTANTE
         ===================================================== -->

    <div
        class="pub-ai-chat-panel"
        id="aiChatPanel"
        role="dialog"
        aria-modal="false"
        aria-label="Asistente virtual del CESFAM"
    >

        <div class="pub-ai-chat-header">

            <div class="pub-ai-chat-header-info">

                <div
                    class="pub-ai-avatar"
                    aria-hidden="true"
                >
                    🤖
                </div>

                <div>

                    <div class="pub-ai-chat-name">
                        Eduardito
                    </div>

                    <span class="pub-ai-online-badge">
                        En línea
                    </span>

                </div>

            </div>


            <button
                class="pub-ai-chat-close"
                id="btnAiClose"
                aria-label="Cerrar asistente"
            >
                ✕
            </button>

        </div>


        <div
            class="pub-ai-chat-messages"
            id="aiMessages"
            role="log"
            aria-live="polite"
        >

            <div class="pub-ai-msg bot">

                ¡Hola! 👋 Soy <strong>Eduardito</strong>,
                asistente virtual del
                <strong>CESFAM Eduardo Raggio</strong>.
                ¿En qué puedo ayudarte?

            </div>

        </div>


        <form
            class="pub-ai-chat-form"
            id="aiChatForm"
            novalidate
        >

            <input
                class="pub-ai-chat-input"
                type="text"
                id="aiChatInput"
                placeholder="Escribe tu pregunta aquí..."
                autocomplete="off"
            />

            <button
                type="submit"
                class="pub-ai-chat-send"
                aria-label="Enviar mensaje"
            >
                ➤
            </button>

        </form>

    </div>


    <!-- BOTÓN FLOTANTE DEL ASISTENTE -->

    <div class="pub-ai-fab">

        <button
            class="pub-ai-fab-btn"
            id="btnAiFab"
            aria-label="Abrir asistente virtual"
        >

            <span
                class="pub-ai-fab-emoji"
                aria-hidden="true"
            >
                🤖
            </span>

            <div class="pub-ai-fab-texts">

                <span class="pub-ai-fab-main">
                    Asistente CESFAM
                </span>

                <span class="pub-ai-fab-sub">
                    ¿Necesitas ayuda?
                </span>

            </div>

        </button>

    </div>


    <!-- =====================================================
         SCRIPTS
         ===================================================== -->

    <!--
        script.js ya se carga desde navbar_publica.php.
        No volver a cargarlo aquí para evitar registrar
        dos veces el evento del menú hamburguesa.
    -->

    <script src="../js/chatbot.js"></script>

</body>

</html>