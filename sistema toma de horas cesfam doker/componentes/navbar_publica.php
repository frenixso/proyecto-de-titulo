<?php
/*
 * =========================================================
 * NAVBAR PÚBLICA
 * CESFAM EDUARDO RAGGIO CATEMU
 *
 * Componentes:
 * - Barra de accesibilidad.
 * - Logo institucional.
 * - Navegación pública.
 * - Enlace a noticias y tutoriales.
 * - Acceso externo a OIRS.
 * - Acceso externo a TeleSalud.
 * - Botón Tomar Hora.
 * - Menú móvil.
 * - Carga del JavaScript público.
 * =========================================================
 */

$base_url = $base_url ?? (getenv('APP_BASE_URL') !== false ? rtrim(getenv('APP_BASE_URL'), '/') : '/sistema toma de horas cesfam');

$base_url = rtrim($base_url, '/');

$base_url_html = htmlspecialchars(
    $base_url,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$base_url_script = htmlspecialchars(
    str_replace(' ', '%20', $base_url),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

/*
 * =========================================================
 * IDENTIFICAR LA PÁGINA ACTUAL
 * =========================================================
 */

$paginaActualNavbar = basename(
    $_SERVER['SCRIPT_NAME'] ?? 'index.php'
);

$esInicio = $paginaActualNavbar === 'index.php';
$esNoticias = $paginaActualNavbar === 'noticia.php';
$esTutoriales = $paginaActualNavbar === 'tutoriales.php';
$esTomaHoras = $paginaActualNavbar === 'toma_horas.php';
?>

<!-- =========================================================
     BARRA DE ACCESIBILIDAD SUPERIOR
     ========================================================= -->

<div class="pub-accessibility-bar">

    <span class="pub-a11y-label">
        Accesibilidad:
    </span>

    <button
        type="button"
        class="pub-a11y-btn"
        id="btnDarkMode"
        aria-label="Activar modo oscuro"
        aria-pressed="false"
    >
        🌙 Modo Oscuro
    </button>

    <button
        type="button"
        class="pub-a11y-btn"
        id="btnTextDecrease"
        aria-label="Disminuir tamaño de texto"
    >
        A-
    </button>

    <button
        type="button"
        class="pub-a11y-btn"
        id="btnTextReset"
        aria-label="Restablecer tamaño de texto"
    >
        A
    </button>

    <button
        type="button"
        class="pub-a11y-btn"
        id="btnTextIncrease"
        aria-label="Aumentar tamaño de texto"
    >
        A+
    </button>

</div>


<!-- =========================================================
     HEADER E IDENTIDAD INSTITUCIONAL
     ========================================================= -->

<header class="pub-header">

    <div class="pub-header-inner">

        <!-- LOGO -->

        <a
            href="<?= $base_url_html ?>/publicos/index.php"
            class="pub-logo"
            aria-label="Ir al inicio del CESFAM Eduardo Raggio Catemu"
        >

            <div class="pub-logo-icon">
                🏥
            </div>

            <div class="pub-logo-text">

                <span class="brand-title">
                    CESFAM Eduardo Raggio Catemu
                </span>

                <span class="pub-logo-sub">
                    Portal de Salud
                </span>

            </div>

        </a>


        <!-- =================================================
             NAVEGACIÓN PRINCIPAL
             ================================================= -->

        <nav
            class="pub-nav"
            id="menuPublico"
            aria-label="Navegación principal"
        >

            <!-- INICIO -->

            <a
                href="<?= $base_url_html ?>/publicos/index.php#inicio"
                class="pub-nav-link<?= $esInicio ? ' active' : '' ?>"
                <?= $esInicio ? 'aria-current="page"' : '' ?>
            >
                Inicio
            </a>


            <!-- NOTICIAS -->

            <a
                href="<?= $base_url_html ?>/publicos/noticia.php"
                class="pub-nav-link<?= $esNoticias ? ' active' : '' ?>"
                <?= $esNoticias ? 'aria-current="page"' : '' ?>
            >
                Noticias
            </a>


            <!-- TUTORIALES -->

            <a
                href="<?= $base_url_html ?>/publicos/tutoriales.php"
                class="pub-nav-link<?= $esTutoriales ? ' active' : '' ?>"
                <?= $esTutoriales ? 'aria-current="page"' : '' ?>
            >
                Tutoriales
            </a>


            <!-- SERVICIOS -->

            <a
                href="<?= $base_url_html ?>/publicos/index.php#servicios"
                class="pub-nav-link"
            >
                Servicios
            </a>


            <!-- INFORMACIÓN -->

            <a
                href="<?= $base_url_html ?>/publicos/index.php#informacion"
                class="pub-nav-link"
            >
                Información
            </a>


            <!-- =================================================
                 OIRS: RECLAMOS, FELICITACIONES, SUGERENCIAS
                 Y CONSULTAS
                 ================================================= -->

            <a
                href="https://oirs.minsal.cl/"
                class="pub-nav-link"
                target="_blank"
                rel="noopener noreferrer"
                title="Ingresar a OIRS para reclamos, felicitaciones, sugerencias y consultas"
                aria-label="OIRS: reclamos, felicitaciones, sugerencias y consultas. Se abre en una pestaña nueva."
            >
                OIRS
            </a>


            <!-- =================================================
                 TELESALUD: SOLICITUD DE OTRAS ATENCIONES
                 ================================================= -->

            <a
                href="https://telesalud.gob.cl/"
                class="pub-nav-link"
                target="_blank"
                rel="noopener noreferrer"
                title="Solicitar otras atenciones de salud en línea mediante TeleSalud"
                aria-label="TeleSalud: solicitar otras atenciones de salud en línea. Se abre en una pestaña nueva."
            >
                TeleSalud
            </a>


            <!-- CONTACTO -->

            <a
                href="<?= $base_url_html ?>/publicos/index.php#contacto"
                class="pub-nav-link"
            >
                Contacto
            </a>


            <!-- TOMAR HORA -->

            <a
                href="<?= $base_url_html ?>/publicos/toma_horas.php"
                class="pub-btn-login<?= $esTomaHoras ? ' active' : '' ?>"
                <?= $esTomaHoras ? 'aria-current="page"' : '' ?>
            >
                Tomar Hora
            </a>

        </nav>


        <!-- =================================================
             BOTÓN MÓVIL HAMBURGUESA
             ================================================= -->

        <button
            type="button"
            class="pub-hamburger"
            id="btnHamburger"
            aria-label="Abrir menú"
            aria-expanded="false"
            aria-controls="menuPublico"
        >
            ☰
        </button>

    </div>

</header>


<!-- =========================================================
     JAVASCRIPT PÚBLICO
     Se carga desde la navbar compartida.
     ========================================================= -->

<script
    src="<?= $base_url_script ?>/js/script.js?v=20261015"
    defer
></script>