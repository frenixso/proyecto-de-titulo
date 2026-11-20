<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/*
 * =========================================================
 * CARGAR PHPMailer
 * =========================================================
 */

require_once __DIR__ . '/../librerias/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../librerias/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../librerias/PHPMailer-master/src/SMTP.php';


/*
 * =========================================================
 * FUNCIÓN PARA CREAR CONFIGURACIÓN DEL CORREO
 * =========================================================
 */

function crearCorreo(): PHPMailer
{
    $mail = new PHPMailer(true);


    /*
     * =====================================================
     * CONFIGURACIÓN SMTP BREVO
     * =====================================================
     */

    $mail->isSMTP();

 $mail->Host = getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com';

$mail->SMTPAuth = true;
$mail->Port = 587;

$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

// Credenciales SMTP desde variables de entorno
$mail->Username = trim((string) (getenv('SMTP_USERNAME') ?: ''));
$mail->Password = trim((string) (getenv('SMTP_PASSWORD') ?: ''));

if ($mail->Username === '' || $mail->Password === '') {
    throw new RuntimeException(
        'Faltan las credenciales SMTP en las variables de entorno.'
    );
}

// Remitente
$mail->setFrom(
    getenv('SMTP_FROM_EMAIL') ?: 'notificacionhorasmunicatemu.cl@gmail.com',
    getenv('SMTP_FROM_NAME') ?: 'Municipalidad'
);


    /*
     * =====================================================
     * CONFIGURACIÓN GENERAL
     * =====================================================
     */

    $mail->CharSet = 'UTF-8';

    $mail->isHTML(true);


    return $mail;
}