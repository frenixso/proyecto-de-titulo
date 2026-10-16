document.addEventListener("DOMContentLoaded", function () {

    // =====================================================
    // ELEMENTOS DEL CHATBOT
    // =====================================================

    const chatPanel = document.getElementById("aiChatPanel");
    const chatForm = document.getElementById("aiChatForm");
    const chatInput = document.getElementById("aiChatInput");
    const chatMessages = document.getElementById("aiMessages");

    const btnOpen = document.getElementById("btnAiFab");
    const btnClose = document.getElementById("btnAiClose");


    // =====================================================
    // COMPROBAR QUE EXISTE EL CHATBOT
    // =====================================================

    if (
        !chatPanel ||
        !chatForm ||
        !chatInput ||
        !chatMessages ||
        !btnOpen ||
        !btnClose
    ) {
        return;
    }


    // =====================================================
    // ABRIR CHATBOT
    // =====================================================

    btnOpen.addEventListener("click", function () {

        chatPanel.classList.add("active");

        chatInput.focus();

    });


    // =====================================================
    // CERRAR CHATBOT
    // =====================================================

    btnClose.addEventListener("click", function () {

        chatPanel.classList.remove("active");

    });


    // =====================================================
    // AGREGAR MENSAJE AL CHAT
    // =====================================================

    function agregarMensaje(texto, tipo) {

        const mensaje = document.createElement("div");

        mensaje.classList.add("pub-ai-msg", tipo);

        mensaje.textContent = texto;

        // Permitir saltos de línea en las respuestas
        mensaje.style.whiteSpace = "pre-wrap";

        chatMessages.appendChild(mensaje);

        chatMessages.scrollTop = chatMessages.scrollHeight;

    }


    // =====================================================
    // ENVIAR MENSAJE
    // =====================================================

    chatForm.addEventListener("submit", async function (event) {

        event.preventDefault();


        // =================================================
        // OBTENER MENSAJE
        // =================================================

        const mensaje = chatInput.value.trim();

        if (!mensaje) {
            return;
        }


        // =================================================
        // MOSTRAR MENSAJE DEL USUARIO
        // =================================================

        agregarMensaje(
            mensaje,
            "user"
        );


        // =================================================
        // LIMPIAR CAMPO
        // =================================================

        chatInput.value = "";


        // =================================================
        // DESACTIVAR CAMPO TEMPORALMENTE
        // =================================================

        chatInput.disabled = true;


        // =================================================
        // MOSTRAR MENSAJE TEMPORAL
        // =================================================

        agregarMensaje(
            "Escribiendo...",
            "bot"
        );


        try {

            // =================================================
            // ENVIAR MENSAJE A PHP
            // =================================================

            const respuesta = await fetch("../api/chatbot.php", {

                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify({
                    mensaje: mensaje
                })

            });


            // =================================================
            // CONVERTIR RESPUESTA A JSON
            // =================================================

            const datos = await respuesta.json();


            // =================================================
            // ELIMINAR "ESCRIBIENDO..."
            // =================================================

            const mensajesBot =
                chatMessages.querySelectorAll(".pub-ai-msg.bot");


            if (mensajesBot.length > 0) {

                const ultimo =
                    mensajesBot[mensajesBot.length - 1];


                if (ultimo.textContent === "Escribiendo...") {

                    ultimo.remove();

                }

            }


            // =================================================
            // COMPROBAR RESPUESTA
            // =================================================

            if (datos.success) {

                agregarMensaje(
                    datos.respuesta,
                    "bot"
                );

            } else {

                agregarMensaje(
                    "Lo siento, ocurrió un problema al comunicarse con el asistente.",
                    "bot"
                );

                console.error(
                    "Error chatbot:",
                    datos
                );

            }

        } catch (error) {

            // =================================================
            // ERROR DE CONEXIÓN
            // =================================================

            console.error(
                "Error de conexión:",
                error
            );


            // =================================================
            // ELIMINAR "ESCRIBIENDO..."
            // =================================================

            const mensajesBot =
                chatMessages.querySelectorAll(".pub-ai-msg.bot");


            if (mensajesBot.length > 0) {

                const ultimo =
                    mensajesBot[mensajesBot.length - 1];


                if (ultimo.textContent === "Escribiendo...") {

                    ultimo.remove();

                }

            }


            // =================================================
            // MOSTRAR ERROR
            // =================================================

            agregarMensaje(
                "No fue posible comunicarse con el asistente.",
                "bot"
            );

        }


        // =================================================
        // REACTIVAR CAMPO
        // =================================================

        chatInput.disabled = false;

        chatInput.focus();

    });

});