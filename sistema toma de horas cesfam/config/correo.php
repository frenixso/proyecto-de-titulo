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

    $mail->Host = 'smtp-relay.brevo.com';

    $mail->SMTPAuth = true;

    $mail->Port = 587;

    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_STARTTLS;


    /*
     * =====================================================
     * CREDENCIALES SMTP
     * =====================================================
     */

    $mail->Username =
        'bd1f54001@smtp-brevo.com';


    /*
     * Coloca aquí tu CLAVE SMTP de Brevo.
     */

    $mail->Password =
        '';


    /*
     * =====================================================
     * REMITENTE
     * =====================================================
     *
     * Debe ser el correo que verificaste en Brevo.
     *
     */

    $mail->setFrom(
        'notificacionhorasmunicatemu.cl@gmail.com',
        'Municipalidad'
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