document.addEventListener(
    "DOMContentLoaded",
    function () {

        const form =
            document.getElementById(
                "login-form"
            );

        const usuarioInput =
            document.getElementById(
                "usuario"
            );

        const passwordInput =
            document.getElementById(
                "password"
            );

        const passwordToggle =
            document.getElementById(
                "password-toggle"
            );

        const message =
            document.getElementById(
                "login-message"
            );

        const loginButton =
            document.getElementById(
                "login-button"
            );


        /* =================================================
           MOSTRAR / OCULTAR CONTRASEÑA
           ================================================= */

        passwordToggle.addEventListener(
            "click",
            function () {

                if (
                    passwordInput.type ===
                    "password"
                ) {

                    passwordInput.type =
                        "text";

                    passwordToggle.textContent =
                        "🙈";

                    passwordToggle.setAttribute(
                        "aria-label",
                        "Ocultar contraseña"
                    );

                } else {

                    passwordInput.type =
                        "password";

                    passwordToggle.textContent =
                        "👁";

                    passwordToggle.setAttribute(
                        "aria-label",
                        "Mostrar contraseña"
                    );

                }

            }
        );


        /* =================================================
           MOSTRAR MENSAJE
           ================================================= */

        function mostrarMensaje(texto) {

            message.textContent =
                texto;

            message.style.display =
                "block";

        }


        /* =================================================
           OCULTAR MENSAJE
           ================================================= */

        function ocultarMensaje() {

            message.textContent =
                "";

            message.style.display =
                "none";

        }


        /* =================================================
           ENVIAR LOGIN
           ================================================= */

        form.addEventListener(
            "submit",
            async function (event) {

                event.preventDefault();

                ocultarMensaje();


                const usuario =
                    usuarioInput.value.trim();

                const password =
                    passwordInput.value;


                /* =========================================
                   VALIDACIÓN LOCAL
                   ========================================= */

                if (
                    usuario === "" ||
                    password === ""
                ) {

                    mostrarMensaje(
                        "Debes ingresar usuario y contraseña."
                    );

                    return;

                }


                loginButton.disabled =
                    true;

                loginButton.textContent =
                    "Ingresando...";


                try {

                    /* =====================================
                       CREAR DATOS JSON
                       ===================================== */

                    const datos = {

                        usuario:
                            usuario,

                        password:
                            password

                    };


                    console.log(
                        "Usuario enviado:",
                        usuario
                    );


                    console.log(
                        "Largo contraseña:",
                        password.length
                    );


                    /* =====================================
                       PETICIÓN AL SERVIDOR
                       ===================================== */

                    const respuesta =
                        await fetch(
                            "../api/login_funcionario.php",
                            {
                                method: "POST",

                                credentials:
                                    "same-origin",

                                cache:
                                    "no-store",

                                headers: {

                                    "Content-Type":
                                        "application/json; charset=UTF-8",

                                    "Accept":
                                        "application/json"

                                },

                                body:
                                    JSON.stringify(
                                        datos
                                    )

                            }
                        );


                    /* =====================================
                       LEER RESPUESTA
                       ===================================== */

                    const texto =
                        await respuesta.text();


                    console.log(
                        "HTTP:",
                        respuesta.status
                    );


                    console.log(
                        "Respuesta del servidor:",
                        texto
                    );


                    let resultado;


                    try {

                        resultado =
                            JSON.parse(
                                texto
                            );

                    } catch (error) {

                        console.error(
                            "Respuesta no válida del servidor:",
                            texto
                        );


                        mostrarMensaje(
                            "El servidor devolvió una respuesta no válida."
                        );


                        return;

                    }


                    /* =====================================
                       LOGIN CORRECTO
                       ===================================== */

                    if (
                        resultado.ok === true
                    ) {

                        if (
                            resultado.redirect
                        ) {

                            window.location.href =
                                resultado.redirect;

                            return;

                        }


                        mostrarMensaje(
                            "Inicio de sesión correcto, pero no se recibió la página de destino."
                        );


                        return;

                    }


                    /* =====================================
                       LOGIN INCORRECTO
                       ===================================== */

                    mostrarMensaje(
                        resultado.mensaje ||
                        "Usuario o contraseña incorrectos."
                    );


                } catch (error) {

                    console.error(
                        "Error de conexión:",
                        error
                    );


                    mostrarMensaje(
                        "No fue posible conectar con el servidor."
                    );


                } finally {

                    loginButton.disabled =
                        false;

                    loginButton.textContent =
                        "Iniciar sesión";

                }

            }
        );

    }
);