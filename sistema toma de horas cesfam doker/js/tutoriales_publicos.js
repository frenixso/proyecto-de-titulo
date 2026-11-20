/*
 * =========================================================
 * VISTAS PREVIAS DE VIDEOS LOCALES
 * CESFAM EDUARDO RAGGIO CATEMU
 *
 * Archivo: js/tutoriales_publicos.js
 *
 * Funciones:
 * - Generar una vista previa desde un fotograma del video.
 * - Ocultar la imagen de respaldo cuando esté disponible.
 * - Mantener el respaldo si el video no se puede cargar.
 * - No reproducir los videos automáticamente.
 * =========================================================
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        const videos = document.querySelectorAll(
            'video[data-tutorial-preview]'
        );

        videos.forEach(function (video) {

            const contenedor = video.closest(
                '.pub-tutorial-imagen-wrapper'
            );

            if (!contenedor) {
                return;
            }

            const respaldo = contenedor.querySelector(
                '[data-tutorial-preview-fallback]'
            );

            let fotogramaPreparado = false;
            let instanteObjetivo = null;

            /*
             * Mantener visible la imagen de respaldo.
             */
            function mostrarRespaldo() {
                if (respaldo && !fotogramaPreparado) {
                    respaldo.style.display = 'flex';
                }
            }

            /*
             * Comprobar que el navegador tenga un fotograma
             * disponible antes de ocultar el respaldo.
             */
            function mostrarFotograma() {
                if (
                    fotogramaPreparado ||
                    video.seeking ||
                    video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA ||
                    video.videoWidth <= 0 ||
                    video.videoHeight <= 0
                ) {
                    return;
                }

                fotogramaPreparado = true;

                /*
                 * Dejar el video detenido en el fotograma elegido.
                 */
                video.pause();

                if (respaldo) {
                    respaldo.style.display = 'none';
                }
            }

            /*
             * Seleccionar un fotograma cercano al comienzo.
             * En videos cortos se utiliza un punto intermedio.
             */
            function prepararFotograma() {

                if (fotogramaPreparado) {
                    return;
                }

                const duracion = video.duration;

                if (
                    !Number.isFinite(duracion) ||
                    duracion <= 0
                ) {
                    mostrarRespaldo();
                    return;
                }

                /*
                 * Evitar que el navegador intente buscar
                 * repetidamente el mismo fotograma.
                 */
                if (video.dataset.fotogramaSolicitado === '1') {
                    return;
                }

                instanteObjetivo = duracion > 2
                    ? 1
                    : duracion / 2;

                video.dataset.fotogramaSolicitado = '1';

                try {
                    video.currentTime = instanteObjetivo;

                    /*
                     * Algunos videos pueden tener ya disponible
                     * el fotograma seleccionado.
                     */
                    if (
                        !video.seeking &&
                        video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA
                    ) {
                        mostrarFotograma();
                    }

                } catch (error) {
                    mostrarRespaldo();
                }
            }

            /*
             * El navegador ya conoce la duración del video.
             */
            video.addEventListener(
                'loadedmetadata',
                prepararFotograma
            );

            /*
             * Se termina de buscar el fotograma solicitado.
             */
            video.addEventListener(
                'seeked',
                mostrarFotograma
            );

            /*
             * Algunos navegadores notifican la disponibilidad
             * del fotograma mediante loadeddata.
             */
            video.addEventListener(
                'loadeddata',
                function () {
                    if (
                        instanteObjetivo !== null &&
                        !video.seeking &&
                        video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA &&
                        Math.abs(video.currentTime - instanteObjetivo) < 0.25
                    ) {
                        mostrarFotograma();
                    }
                }
            );

            /*
             * Si el archivo no se puede cargar, se conserva
             * el icono de respaldo.
             */
            video.addEventListener(
                'error',
                mostrarRespaldo
            );

            /*
             * Evitar que un problema de búsqueda deje una
             * miniatura vacía.
             */
            video.addEventListener(
                'stalled',
                function () {
                    if (!fotogramaPreparado && video.error) {
                        mostrarRespaldo();
                    }
                }
            );

            /*
             * Por si los metadatos ya están disponibles
             * cuando se inicializa el archivo JavaScript.
             */
            if (video.readyState >= HTMLMediaElement.HAVE_METADATA) {
                prepararFotograma();
            }
        });

    });

})();