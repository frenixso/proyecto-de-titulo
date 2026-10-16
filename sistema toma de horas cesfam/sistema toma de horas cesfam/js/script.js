document.addEventListener("DOMContentLoaded", function () {

    // =========================================================
    // 1. ACCESIBILIDAD: MODO OSCURO Y TAMAÑO DE TEXTO
    // =========================================================

    const btnDarkMode = document.getElementById("btnDarkMode");
    const btnTextDecrease = document.getElementById("btnTextDecrease");
    const btnTextReset = document.getElementById("btnTextReset");
    const btnTextIncrease = document.getElementById("btnTextIncrease");

    const fontClasses = ["font-small", "font-large", "font-xlarge"];

    // Cargar preferencia guardada de Modo Oscuro
    const savedTheme = localStorage.getItem("cesfam_theme");
    if (savedTheme === "dark") {
        document.body.classList.add("dark-mode");
        if (btnDarkMode) {
            btnDarkMode.textContent = "☀️ Modo Claro";
        }
    }

    // Cargar preferencia guardada de Tamaño de Fuente
    const savedFontSize = localStorage.getItem("cesfam_font_size") || "normal";
    aplicarTamanoFuente(savedFontSize);

    // Conmutador de Modo Oscuro
    if (btnDarkMode) {
        btnDarkMode.addEventListener("click", function () {
            document.body.classList.toggle("dark-mode");
            const isDark = document.body.classList.contains("dark-mode");

            localStorage.setItem("cesfam_theme", isDark ? "dark" : "light");
            btnDarkMode.textContent = isDark ? "☀️ Modo Claro" : "🌙 Modo Oscuro";
        });
    }

    // Aplicar clase CSS correspondiente al tamaño de fuente
    function aplicarTamanoFuente(level) {
        document.body.classList.remove(...fontClasses);
        if (level !== "normal") {
            document.body.classList.add(`font-${level}`);
        }
        localStorage.setItem("cesfam_font_size", level);
    }

    // Botón A- (Disminuir tamaño)
    if (btnTextDecrease) {
        btnTextDecrease.addEventListener("click", function () {
            const currentLevel = localStorage.getItem("cesfam_font_size") || "normal";
            if (currentLevel === "xlarge") aplicarTamanoFuente("large");
            else if (currentLevel === "large") aplicarTamanoFuente("normal");
            else if (currentLevel === "normal") aplicarTamanoFuente("small");
        });
    }

    // Botón A (Restablecer)
    if (btnTextReset) {
        btnTextReset.addEventListener("click", function () {
            aplicarTamanoFuente("normal");
        });
    }

    // Botón A+ (Aumentar tamaño)
    if (btnTextIncrease) {
        btnTextIncrease.addEventListener("click", function () {
            const currentLevel = localStorage.getItem("cesfam_font_size") || "normal";
            if (currentLevel === "small") aplicarTamanoFuente("normal");
            else if (currentLevel === "normal") aplicarTamanoFuente("large");
            else if (currentLevel === "large") aplicarTamanoFuente("xlarge");
        });
    }


    // =========================================================
    // 2. MENÚ MÓVIL (HAMBURGUESA)
    // =========================================================

    const btnHamburger = document.getElementById("btnHamburger");
    const pubNav = document.querySelector(".pub-nav");

    if (btnHamburger && pubNav) {
        btnHamburger.addEventListener("click", function () {
            pubNav.classList.toggle("active");
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

    // Verificar si los elementos del calendario existen en el DOM
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
        return; // Finaliza aquí si la página actual no tiene el calendario (ej. index.php)
    }

    const today = new Date();
    let currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
    let selectedDay = null;

    function obtenerNombreMes(fecha) {
        const nombre = fecha.toLocaleDateString("es-CL", { month: "long", year: "numeric" });
        return nombre.charAt(0).toUpperCase() + nombre.slice(1);
    }

    function formatearFechaSQL(fecha) {
        const year = fecha.getFullYear();
        const month = String(fecha.getMonth() + 1).padStart(2, "0");
        const day = String(fecha.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    }

    function renderCalendar() {
        calendarDays.innerHTML = "";
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        calendarMonth.textContent = obtenerNombreMes(currentDate);

        const firstDay = new Date(year, month, 1);
        let firstWeekDay = firstDay.getDay();
        firstWeekDay = (firstWeekDay === 0) ? 6 : firstWeekDay - 1;

        const lastDay = new Date(year, month + 1, 0);
        const numberOfDays = lastDay.getDate();

        // Rellenar espacios vacíos previos al inicio del mes
        for (let i = 0; i < firstWeekDay; i++) {
            const empty = document.createElement("div");
            empty.className = "calendar-day empty";
            calendarDays.appendChild(empty);
        }

        // Crear botones interactivos para cada día
        for (let day = 1; day <= numberOfDays; day++) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "calendar-day";
            button.textContent = day;

            const date = new Date(year, month, day);
            const isSunday = date.getDay() === 0;
            const currentDay = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            const dateOnly = new Date(year, month, day);
            const isPast = dateOnly < currentDay;

            if (isSunday || isPast) {
                button.classList.add("disabled");
                button.disabled = true;
            }

            if (day === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                button.classList.add("today");
            }

            if (!isSunday && !isPast) {
                button.addEventListener("click", function () {
                    seleccionarFecha(year, month, day, button);
                });
            }

            calendarDays.appendChild(button);
        }
    }

    function seleccionarFecha(year, month, day, button) {
        selectedDay = new Date(year, month, day);
        const previousSelected = document.querySelector(".calendar-day.selected");
        if (previousSelected) {
            previousSelected.classList.remove("selected");
        }

        button.classList.add("selected");
        const formattedDate = selectedDay.toLocaleDateString("es-CL", {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric"
        });

        selectedDate.textContent = "Fecha seleccionada: " + formattedDate;
        scheduleSection.style.display = "block";
        cargarHorarios();
    }

    async function cargarHorarios() {
        timeSlots.innerHTML = "<p>Consultando horarios disponibles...</p>";
        const servicio = serviceSelect.value;

        if (!servicio) {
            timeSlots.innerHTML = "<p class='warning-message'>Por favor, seleccione un servicio o especialidad arriba.</p>";
            return;
        }

        if (!selectedDay) return;

        const fecha = formatearFechaSQL(selectedDay);

        try {
            const response = await fetch(`api/obtener_horarios.php?servicio=${encodeURIComponent(servicio)}&fecha=${encodeURIComponent(fecha)}`);
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || "No se pudieron obtener los horarios.");
            }

            mostrarHorarios(data.horarios);
        } catch (error) {
            console.error("Error al cargar horarios:", error);
            timeSlots.innerHTML = "<p class='error-message'>No se pudieron cargar los horarios en este momento.</p>";
        }
    }

    function mostrarHorarios(horarios) {
        timeSlots.innerHTML = "";
        if (!horarios || horarios.length === 0) {
            timeSlots.innerHTML = "<p>No hay horarios disponibles para esta fecha.</p>";
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

    // Botones de navegación de meses
    if (prevMonth) {
        prevMonth.addEventListener("click", function () {
            const currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const previousMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
            if (previousMonth < currentMonth) return;

            currentDate = previousMonth;
            renderCalendar();
            scheduleSection.style.display = "none";
            selectedDate.textContent = "Selecciona una fecha para ver los horarios.";
        });
    }

    if (nextMonth) {
        nextMonth.addEventListener("click", function () {
            currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
            renderCalendar();
            scheduleSection.style.display = "none";
            selectedDate.textContent = "Selecciona una fecha para ver los horarios.";
        });
    }

    // Actualizar horarios si cambia el select de servicio
    if (serviceSelect) {
        serviceSelect.addEventListener("change", function () {
            if (selectedDay) {
                cargarHorarios();
            }
        });
    }

    // Renderizado inicial del calendario
    renderCalendar();
});