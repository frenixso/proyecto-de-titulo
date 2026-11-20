document.addEventListener("DOMContentLoaded", function () {

    "use strict";

    // =========================================================
    // 1. ACCESIBILIDAD: MODO OSCURO Y TAMAÑO DE TEXTO
    // =========================================================

    const html = document.documentElement;
    const body = document.body;

    const btnDarkMode = document.getElementById("btnDarkMode");
    const btnTextDecrease = document.getElementById("btnTextDecrease");
    const btnTextReset = document.getElementById("btnTextReset");
    const btnTextIncrease = document.getElementById("btnTextIncrease");

    const fontClasses = [
        "font-small",
        "font-large",
        "font-xlarge"
    ];

    const fontLevels = [
        "small",
        "normal",
        "large",
        "xlarge"
    ];

    const fontSizes = {
        small: 14,
        normal: 16,
        large: 18,
        xlarge: 20
    };

    const FONT_SIZE_DEFAULT = "normal";

    /*
     * =========================================================
     * PREFERENCIAS DEL NAVEGADOR
     * =========================================================
     *
     * Se utilizan las claves que ya tenía el proyecto:
     *
     * cesfam_theme
     * cesfam_font_size
     */

    function leerPreferencia(clave, valorPredeterminado) {

        try {

            const valor = localStorage.getItem(clave);

            return valor !== null
                ? valor
                : valorPredeterminado;

        } catch (error) {

            console.warn(
                "No se pudo leer una preferencia del navegador.",
                error
            );

            return valorPredeterminado;
        }
    }


    function guardarPreferencia(clave, valor) {

        try {

            localStorage.setItem(
                clave,
                String(valor)
            );

        } catch (error) {

            console.warn(
                "No se pudo guardar una preferencia del navegador.",
                error
            );
        }
    }


    // =========================================================
    // MODO OSCURO
    // =========================================================

    function aplicarModoOscuro(activar, guardar) {

        body.classList.toggle(
            "dark-mode",
            activar
        );

        if (btnDarkMode) {

            btnDarkMode.textContent = activar
                ? "☀️ Modo Claro"
                : "🌙 Modo Oscuro";

            btnDarkMode.setAttribute(
                "aria-label",
                activar
                    ? "Desactivar modo oscuro"
                    : "Activar modo oscuro"
            );

            btnDarkMode.setAttribute(
                "aria-pressed",
                activar ? "true" : "false"
            );

        }

        if (guardar) {

            guardarPreferencia(
                "cesfam_theme",
                activar ? "dark" : "light"
            );

        }
    }


    // Recuperar el tema guardado al cargar cualquier página.

    const savedTheme = leerPreferencia(
        "cesfam_theme",
        "light"
    );

    aplicarModoOscuro(
        savedTheme === "dark",
        false
    );


    // Botón para alternar entre modo oscuro y claro.

    if (btnDarkMode) {

        btnDarkMode.addEventListener("click", function () {

            const activar = !body.classList.contains("dark-mode");

            aplicarModoOscuro(
                activar,
                true
            );

        });

    }


    // =========================================================
    // TAMAÑO DE TEXTO
    // =========================================================

    let currentFontLevel = FONT_SIZE_DEFAULT;


    function aplicarTamanoFuente(level, guardar = true) {

        if (!fontLevels.includes(level)) {

            level = FONT_SIZE_DEFAULT;

        }

        currentFontLevel = level;

        /*
         * Eliminar las clases de tamaño anteriores
         * para evitar que se acumulen.
         */

        body.classList.remove(...fontClasses);

        /*
         * Aplicar la clase correspondiente al nivel seleccionado.
         */

        if (level !== "normal") {

            body.classList.add(`font-${level}`);

        }

        /*
         * Ajustar el tamaño base del documento.
         *
         * general.css utiliza --font-size-root en el elemento html.
         * Esto permite escalar elementos que usan rem y em.
         */

        html.style.setProperty(
            "--font-size-root",
            `${fontSizes[level]}px`
        );

        /*
         * Guardar la preferencia para conservarla
         * al navegar entre páginas.
         */

        if (guardar) {

            guardarPreferencia(
                "cesfam_font_size",
                level
            );

        }

        /*
         * Indicar cuándo se alcanza el límite de cada botón.
         */

        if (btnTextDecrease) {

            btnTextDecrease.disabled =
                level === "small";

        }

        if (btnTextIncrease) {

            btnTextIncrease.disabled =
                level === "xlarge";

        }

        if (btnTextReset) {

            btnTextReset.disabled =
                level === "normal";

        }
    }


    // Recuperar el tamaño de texto guardado.

    const savedFontSize = leerPreferencia(
        "cesfam_font_size",
        FONT_SIZE_DEFAULT
    );

    aplicarTamanoFuente(
        savedFontSize,
        false
    );


    // Botón A-: disminuir tamaño de texto.

    if (btnTextDecrease) {

        btnTextDecrease.addEventListener("click", function () {

            const currentIndex = fontLevels.indexOf(
                currentFontLevel
            );

            if (currentIndex > 0) {

                aplicarTamanoFuente(
                    fontLevels[currentIndex - 1],
                    true
                );

            }

        });

    }


    // Botón A: restablecer tamaño normal.

    if (btnTextReset) {

        btnTextReset.addEventListener("click", function () {

            aplicarTamanoFuente(
                FONT_SIZE_DEFAULT,
                true
            );

        });

    }


    // Botón A+: aumentar tamaño de texto.

    if (btnTextIncrease) {

        btnTextIncrease.addEventListener("click", function () {

            const currentIndex = fontLevels.indexOf(
                currentFontLevel
            );

            if (currentIndex < fontLevels.length - 1) {

                aplicarTamanoFuente(
                    fontLevels[currentIndex + 1],
                    true
                );

            }

        });

    }


    // =========================================================
    // 2. MENÚ MÓVIL (HAMBURGUESA)
    // =========================================================

    const btnHamburger = document.getElementById("btnHamburger");

    const pubNav = document.getElementById("menuPublico")
        || document.querySelector(".pub-nav");


    if (btnHamburger && pubNav) {

        btnHamburger.addEventListener("click", function () {

            const estaAbierto = pubNav.classList.toggle("active");

            btnHamburger.setAttribute(
                "aria-expanded",
                estaAbierto ? "true" : "false"
            );

            btnHamburger.setAttribute(
                "aria-label",
                estaAbierto
                    ? "Cerrar menú"
                    : "Abrir menú"
            );

            btnHamburger.textContent = estaAbierto
                ? "✕"
                : "☰";

        });


        /*
         * Cerrar el menú cuando el visitante selecciona un enlace.
         */

        pubNav.querySelectorAll("a").forEach(function (enlace) {

            enlace.addEventListener("click", function () {

                pubNav.classList.remove("active");

                btnHamburger.setAttribute(
                    "aria-expanded",
                    "false"
                );

                btnHamburger.setAttribute(
                    "aria-label",
                    "Abrir menú"
                );

                btnHamburger.textContent = "☰";

            });

        });


        /*
         * Cerrar el menú con Escape.
         */

        document.addEventListener("keydown", function (event) {

            if (
                event.key === "Escape"
                && pubNav.classList.contains("active")
            ) {

                pubNav.classList.remove("active");

                btnHamburger.setAttribute(
                    "aria-expanded",
                    "false"
                );

                btnHamburger.setAttribute(
                    "aria-label",
                    "Abrir menú"
                );

                btnHamburger.textContent = "☰";

                btnHamburger.focus();

            }

        });

    }


    // =========================================================
    // 3. CALENDARIO INTERACTIVO Y TOMA DE HORAS
    // =========================================================

    const calendarDays = document.getElementById("calendar-days");
    const calendarMonth = document.getElementById("calendar-month");
    const prevMonth = document.getElementById("prev-month");
    const nextMonth = document.getElementById("next-month");

    const selectedDate = document.getElementById("selected-date");
    const scheduleSection = document.getElementById("schedule-section");
    const timeSlots = document.getElementById("time-slots");
    const serviceSelect = document.getElementById("servicio");


    /*
     * Si la página no contiene el calendario, no ejecutar
     * la lógica del calendario.
     *
     * La accesibilidad y el menú móvil ya están configurados.
     */

    if (
        !calendarDays ||
        !calendarMonth ||
        !prevMonth ||
        !nextMonth ||
        !selectedDate ||
        !scheduleSection ||
        !timeSlots ||
        !serviceSelect
    ) {

        return;

    }


    // =========================================================
    // CONFIGURACIÓN DEL CALENDARIO
    // =========================================================

    const today = new Date();

    let currentDate = new Date(
        today.getFullYear(),
        today.getMonth(),
        1
    );

    let selectedDay = null;


    // =========================================================
    // FORMATEAR NOMBRE DEL MES
    // =========================================================

    function obtenerNombreMes(fecha) {

        const nombre = fecha.toLocaleDateString(
            "es-CL",
            {
                month: "long",
                year: "numeric"
            }
        );

        return nombre.charAt(0).toUpperCase()
            + nombre.slice(1);
    }


    // =========================================================
    // FORMATEAR FECHA PARA POSTGRESQL
    // =========================================================

    function formatearFechaSQL(fecha) {

        const year = fecha.getFullYear();

        const month = String(
            fecha.getMonth() + 1
        ).padStart(2, "0");

        const day = String(
            fecha.getDate()
        ).padStart(2, "0");

        return `${year}-${month}-${day}`;
    }


    // =========================================================
    // RENDERIZAR CALENDARIO
    // =========================================================

    function renderCalendar() {

        calendarDays.innerHTML = "";

        const year = currentDate.getFullYear();

        const month = currentDate.getMonth();


        calendarMonth.textContent = obtenerNombreMes(
            currentDate
        );


        // Primer día del mes.

        const firstDay = new Date(
            year,
            month,
            1
        );

        let firstWeekDay = firstDay.getDay();

        firstWeekDay = firstWeekDay === 0
            ? 6
            : firstWeekDay - 1;


        // Último día del mes.

        const lastDay = new Date(
            year,
            month + 1,
            0
        );

        const numberOfDays = lastDay.getDate();


        // Rellenar los espacios previos al primer día.

        for (
            let i = 0;
            i < firstWeekDay;
            i++
        ) {

            const empty = document.createElement("div");

            empty.className = "calendar-day empty";

            empty.setAttribute(
                "aria-hidden",
                "true"
            );

            calendarDays.appendChild(empty);

        }


        // Generar los botones de cada día.

        for (
            let day = 1;
            day <= numberOfDays;
            day++
        ) {

            const button = document.createElement("button");

            button.type = "button";

            button.className = "calendar-day";

            button.textContent = day;


            const date = new Date(
                year,
                month,
                day
            );

            const isSunday = date.getDay() === 0;


            const currentDay = new Date(
                today.getFullYear(),
                today.getMonth(),
                today.getDate()
            );

            const dateOnly = new Date(
                year,
                month,
                day
            );

            const isPast = dateOnly < currentDay;


            // Deshabilitar domingos y días anteriores.

            if (isSunday || isPast) {

                button.classList.add("disabled");

                button.disabled = true;

            }


            // Marcar el día actual.

            if (
                day === today.getDate()
                && month === today.getMonth()
                && year === today.getFullYear()
            ) {

                button.classList.add("today");

            }


            // Seleccionar una fecha disponible.

            if (!isSunday && !isPast) {

                button.addEventListener(
                    "click",
                    function () {

                        seleccionarFecha(
                            year,
                            month,
                            day,
                            button
                        );

                    }
                );

            }


            calendarDays.appendChild(button);

        }

    }


    // =========================================================
    // SELECCIONAR FECHA
    // =========================================================

    function seleccionarFecha(
        year,
        month,
        day,
        button
    ) {

        selectedDay = new Date(
            year,
            month,
            day
        );


        const previousSelected = document.querySelector(
            ".calendar-day.selected"
        );

        if (previousSelected) {

            previousSelected.classList.remove(
                "selected"
            );

        }


        button.classList.add("selected");


        const formattedDate = selectedDay.toLocaleDateString(
            "es-CL",
            {
                weekday: "long",
                day: "numeric",
                month: "long",
                year: "numeric"
            }
        );


        selectedDate.textContent =
            "Fecha seleccionada: " + formattedDate;


        scheduleSection.style.display = "block";


        cargarHorarios();

    }


    // =========================================================
    // CARGAR HORARIOS DISPONIBLES
    // =========================================================

    async function cargarHorarios() {

        timeSlots.innerHTML =
            "<p>Consultando horarios disponibles...</p>";


        const servicio = serviceSelect.value;


        if (!servicio) {

            timeSlots.innerHTML =
                "<p class='warning-message'>Por favor, seleccione un servicio o especialidad arriba.</p>";

            return;

        }


        if (!selectedDay) {

            return;

        }


        const fecha = formatearFechaSQL(
            selectedDay
        );


        try {

            /*
             * La carpeta api está en la raíz del proyecto:
             *
             * /sistema toma de horas cesfam/api/
             *
             * toma_horas.php se encuentra dentro de publicos/.
             */

            const response = await fetch(
                `../api/obtener_horarios.php?servicio=${encodeURIComponent(servicio)}&fecha=${encodeURIComponent(fecha)}`,
                {
                    method: "GET",
                    headers: {
                        "Accept": "application/json"
                    }
                }
            );


            if (!response.ok) {

                throw new Error(
                    `Error HTTP: ${response.status}`
                );

            }


            const data = await response.json();


            if (!data.success) {

                throw new Error(
                    data.message
                    || "No se pudieron obtener los horarios."
                );

            }


            mostrarHorarios(
                data.horarios
            );

        } catch (error) {

            console.error(
                "Error al cargar horarios:",
                error
            );


            timeSlots.innerHTML =
                "<p class='error-message'>No se pudieron cargar los horarios en este momento.</p>";

        }

    }


    // =========================================================
    // MOSTRAR HORARIOS
    // =========================================================

    function mostrarHorarios(horarios) {

        timeSlots.innerHTML = "";


        if (
            !horarios
            || horarios.length === 0
        ) {

            timeSlots.innerHTML =
                "<p>No hay horarios disponibles para esta fecha.</p>";

            return;

        }


        horarios.forEach(function (horario) {

            const label = document.createElement("label");

            label.classList.add("time-slot");


            const input = document.createElement("input");

            input.type = "radio";

            input.name = "hora";

            input.value = horario.hora;

            input.required = true;


            const span = document.createElement("span");

            span.textContent = horario.hora;


            const small = document.createElement("small");


            if (horario.disponible === true) {

                label.classList.add("available");

                small.textContent = "Disponible";

            } else {

                label.classList.add("unavailable");

                input.disabled = true;

                small.textContent = "No disponible";

            }


            label.appendChild(input);

            label.appendChild(span);

            label.appendChild(small);


            timeSlots.appendChild(label);

        });

    }


    // =========================================================
    // BOTÓN MES ANTERIOR
    // =========================================================

    if (prevMonth) {

        prevMonth.addEventListener(
            "click",
            function () {

                const currentMonth = new Date(
                    today.getFullYear(),
                    today.getMonth(),
                    1
                );


                const previousMonth = new Date(
                    currentDate.getFullYear(),
                    currentDate.getMonth() - 1,
                    1
                );


                // No permitir retroceder a meses anteriores.

                if (previousMonth < currentMonth) {

                    return;

                }


                currentDate = previousMonth;


                // Reiniciar la selección al cambiar el mes.

                selectedDay = null;


                renderCalendar();


                scheduleSection.style.display = "none";


                selectedDate.textContent =
                    "Selecciona una fecha para ver los horarios.";

            }
        );

    }


    // =========================================================
    // BOTÓN MES SIGUIENTE
    // =========================================================

    if (nextMonth) {

        nextMonth.addEventListener(
            "click",
            function () {

                currentDate = new Date(
                    currentDate.getFullYear(),
                    currentDate.getMonth() + 1,
                    1
                );


                // Reiniciar la selección al cambiar el mes.

                selectedDay = null;


                renderCalendar();


                scheduleSection.style.display = "none";


                selectedDate.textContent =
                    "Selecciona una fecha para ver los horarios.";

            }
        );

    }


    // =========================================================
    // ACTUALIZAR HORARIOS AL CAMBIAR DE SERVICIO
    // =========================================================

    if (serviceSelect) {

        serviceSelect.addEventListener(
            "change",
            function () {

                if (selectedDay) {

                    cargarHorarios();

                }

            }
        );

    }


    // =========================================================
    // RENDERIZADO INICIAL DEL CALENDARIO
    // =========================================================

    renderCalendar();

});