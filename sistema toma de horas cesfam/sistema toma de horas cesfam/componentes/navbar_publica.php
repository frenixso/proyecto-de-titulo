<?php
/*
 * NAVBAR PÚBLICA
 * Componente reutilizable con barra de accesibilidad mejorada
 */

$base_url = $base_url ?? '/sistema toma de horas cesfam';
?>

<!-- BARRA DE ACCESIBILIDAD SUPERIOR -->
<div class="pub-accessibility-bar">
    <span class="pub-a11y-label">Accesibilidad:</span>
    <button type="button" class="pub-a11y-btn" id="btnDarkMode" aria-label="Cambiar modo oscuro / claro">🌙 Modo Oscuro</button>
    <button type="button" class="pub-a11y-btn" id="btnTextDecrease" aria-label="Disminuir tamaño de texto">A-</button>
    <button type="button" class="pub-a11y-btn" id="btnTextReset" aria-label="Restablecer tamaño de texto">A</button>
    <button type="button" class="pub-a11y-btn" id="btnTextIncrease" aria-label="Aumentar tamaño de texto">A+</button>
</div>

<!-- HEADER E IDENTIDAD INSTITUCIONAL -->
<header class="pub-header">
    <div class="pub-header-inner">

        <!-- LOGO -->
        <a href="<?php echo $base_url; ?>/publicos/index.php" class="pub-logo">
            <div class="pub-logo-icon">🏥</div>
            <div class="pub-logo-text">
                <span class="brand-title">CESFAM Eduardo Raggio Catemu</span>
                <span class="pub-logo-sub">Portal de Salud</span>
            </div>
        </a>

        <!-- NAVEGACIÓN PRINCIPAL -->
        <nav class="pub-nav">
            <a href="<?php echo $base_url; ?>/publicos/index.php#inicio" class="pub-nav-link active">Inicio</a>
            <a href="<?php echo $base_url; ?>/publicos/index.php#servicios" class="pub-nav-link">Servicios</a>
            <a href="<?php echo $base_url; ?>/publicos/index.php#informacion" class="pub-nav-link">Información</a>
            <a href="<?php echo $base_url; ?>/publicos/index.php#contacto" class="pub-nav-link">Contacto</a>
            <a href="<?php echo $base_url; ?>/publicos/toma_horas.php" class="pub-btn-login">Tomar Hora</a>
        </nav>

        <!-- BOTÓN MÓVIL HAMBURGUESA -->
        <button type="button" class="pub-hamburger" id="btnHamburger" aria-label="Abrir menú">☰</button>

    </div>
</header>