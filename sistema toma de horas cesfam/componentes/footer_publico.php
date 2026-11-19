<?php
/*
 * ============================================
 * FOOTER PÚBLICO — CESFAM Eduardo Raggio Catemu
 * Archivo: componentes/footer_publico.php
 * ============================================
 */

$base_url = $base_url ?? '/sistema toma de horas cesfam';
?>

<!-- FOOTER PÚBLICO -->
<footer class="pub-footer" role="contentinfo">
    <div class="pub-footer-inner">
        <div class="pub-footer-top">
            <div class="pub-footer-brand">
                <div class="pub-logo">
                    <div class="pub-logo-icon" aria-hidden="true">🏥</div>
                    <div class="pub-logo-text">
                        <span class="pub-logo-name">CESFAM Eduardo Raggio Catemu</span>
                        <span class="pub-logo-sub">Red de Salud Pública · FONASA / MINSAL</span>
                    </div>
                </div>
                <p class="pub-footer-tagline">
                    Plataforma de gestión y atención del CESFAM Eduardo Raggio Catemu. Comprometidos con la salud y bienestar de nuestra comunidad.
                </p>
            </div>
            <div>
                <h3 class="pub-footer-col-title">Navegación</h3>
                <ul class="pub-footer-link-list">
                    <li><a class="pub-footer-link" href="#inicio">Inicio</a></li>
                    <li><a class="pub-footer-link" href="#servicios">Servicios</a></li>
                    <li><a class="pub-footer-link" href="#informacion">Información</a></li>
                    <li><a class="pub-footer-link" href="#contacto">Contacto</a></li>
                    <li><button class="pub-footer-link" id="btnFooterLogin" style="background:none;border:none;cursor:pointer;padding:0;font-size:inherit;font-family:inherit;">Iniciar sesión</button></li>
                </ul>
            </div>
            <div>
                <h3 class="pub-footer-col-title">Contacto rápido</h3>
                <ul class="pub-footer-link-list">
                    <li><a class="pub-footer-link" href="tel:+56223456789">📞 +56 2 2345 6789</a></li>
                    <li><a class="pub-footer-link" href="mailto:contacto@cesfamsanmartin.cl">✉️ contacto@cesfameduardoraggio.cl</a></li>
                    <li><span class="pub-footer-link" style="cursor:default">📍 Av. Principal 1234, Santiago</span></li>
                    <li><a class="pub-footer-link" href="tel:6003607777">🏥 600 360 7777 (Salud Responde)</a></li>
                </ul>
            </div>
        </div>
        <div class="pub-footer-bottom">
            <p class="pub-footer-copyright">
                &copy; <?php echo date('Y'); ?> CESFAM Eduardo Raggio Catemu. Todos los derechos reservados.
            </p>
            
        </div>
    </div>
</footer>