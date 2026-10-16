<?php

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) &&
                $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax'
]);

session_start();

/*
 * Vaciar variables de sesión.
 */
$_SESSION = [];

/*
 * Eliminar cookie de sesión.
 */
if (ini_get('session.use_cookies')) {

    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $parametros['path'],
        $parametros['domain'],
        $parametros['secure'],
        $parametros['httponly']
    );
}

/*
 * Destruir sesión.
 */
session_destroy();

/*
 * Volver al login.
 */
header(
    'Location: ../funcionarios/login.php'
);

exit;