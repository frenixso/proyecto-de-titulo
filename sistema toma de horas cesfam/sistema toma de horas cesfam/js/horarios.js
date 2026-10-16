document.addEventListener('DOMContentLoaded', () => {

    /* =========================================================
       1. SELECCIONAR / DESELECCIONAR TODOS LOS SERVICIOS
       ========================================================= */
    const checkAllServices = document.getElementById('js-check-all-services');
    const serviceCheckboxes = document.querySelectorAll('.js-service-checkbox');

    if (checkAllServices && serviceCheckboxes.length > 0) {
        // Marcar o desmarcar todos desde el checkbox general
        checkAllServices.addEventListener('change', (e) => {
            const isChecked = e.target.checked;
            serviceCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        });

        // Actualizar el estado del checkbox general según las elecciones individuales
        serviceCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const total = serviceCheckboxes.length;
                const checkedCount = document.querySelectorAll('.js-service-checkbox:checked').length;

                checkAllServices.checked = (total === checkedCount);
                checkAllServices.indeterminate = (checkedCount > 0 && checkedCount < total);
            });
        });
    }

    /* =========================================================
       2. VALIDACIÓN CLIENTE: UNIFICACIÓN SELECTIVA MÚLTIPLE
       ========================================================= */
    const formUnificacionSelectiva = document.querySelector('.selective-unify-card form');
    if (formUnificacionSelectiva) {
        formUnificacionSelectiva.addEventListener('submit', (e) => {
            const checkedCount = document.querySelectorAll('.js-service-checkbox:checked').length;
            if (checkedCount < 2) {
                e.preventDefault();
                alert('Debes seleccionar al menos 2 servicios para poder unificar sus horarios.');
            }
        });
    }

    /* =========================================================
       3. CONMUTADOR DE DÍA (ACTIVAR / DESACTIVAR DÍA)
       ========================================================= */
    const dayToggles = document.querySelectorAll('.js-toggle-day');
    dayToggles.forEach(toggle => {
        toggle.addEventListener('change', (e) => {
            const formId = e.target.getAttribute('data-target');
            const form = document.getElementById(formId);
            if (!form) return;

            const inputActivo = form.querySelector('.js-input-activo');
            const dayCard = form.closest('.day-card');
            const statusBadge = dayCard ? dayCard.querySelector('.day-status-badge') : null;
            const inputs = form.querySelectorAll('input:not(.js-input-activo):not([type="hidden"]), select, button');

            if (e.target.checked) {
                if (inputActivo) inputActivo.value = '1';
                if (dayCard) {
                    dayCard.classList.remove('inactive');
                    dayCard.classList.add('active');
                }
                if (statusBadge) statusBadge.textContent = 'Atención Activa';
                inputs.forEach(input => input.removeAttribute('disabled'));
            } else {
                if (inputActivo) inputActivo.value = '0';
                if (dayCard) {
                    dayCard.classList.remove('active');
                    dayCard.classList.add('inactive');
                }
                if (statusBadge) statusBadge.textContent = 'Sin Atención';
                inputs.forEach(input => input.setAttribute('disabled', 'true'));
            }
        });
    });

    /* =========================================================
       4. CONMUTADOR DE PAUSA DE ALMUERZO
       ========================================================= */
    const lunchToggles = document.querySelectorAll('.js-toggle-lunch');
    lunchToggles.forEach(toggle => {
        toggle.addEventListener('change', (e) => {
            const lunchBox = e.target.closest('.lunch-box');
            if (!lunchBox) return;

            const lunchInputsContainer = lunchBox.querySelector('.lunch-inputs');
            const inputs = lunchInputsContainer ? lunchInputsContainer.querySelectorAll('input') : [];

            if (e.target.checked) {
                if (lunchInputsContainer) lunchInputsContainer.classList.remove('hidden-lunch');
                inputs.forEach(input => input.removeAttribute('disabled'));
            } else {
                if (lunchInputsContainer) lunchInputsContainer.classList.add('hidden-lunch');
                inputs.forEach(input => input.setAttribute('disabled', 'true'));
            }
        });
    });

    /* =========================================================
       5. TIPO DE EXCEPCIÓN (BLOQUEO VS HORARIO ESPECIAL)
       ========================================================= */
    const tipoExcepcionSelect = document.querySelector('.js-tipo-excepcion');
    if (tipoExcepcionSelect) {
        const horasEspecialesContainer = document.querySelector('.js-horas-especiales');
        const inputsEspeciales = horasEspecialesContainer ? horasEspecialesContainer.querySelectorAll('input') : [];

        const toggleHorasEspeciales = () => {
            if (tipoExcepcionSelect.value === 'horario_especial') {
                if (horasEspecialesContainer) horasEspecialesContainer.classList.remove('hidden-lunch');
                inputsEspeciales.forEach(input => input.removeAttribute('disabled'));
            } else {
                if (horasEspecialesContainer) horasEspecialesContainer.classList.add('hidden-lunch');
                inputsEspeciales.forEach(input => input.setAttribute('disabled', 'true'));
            }
        };

        tipoExcepcionSelect.addEventListener('change', toggleHorasEspeciales);
        toggleHorasEspeciales();
    }

});