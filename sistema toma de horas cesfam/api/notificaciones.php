<?php

/*
 * =========================================================
 * API NOTIFICACIONES
 * =========================================================
 *
 * Permite:
 *
 * - Obtener cantidad de notificaciones no leídas.
 * - Obtener listado de notificaciones.
 * - Marcar una notificación como leída.
 * - Marcar todas las notificaciones como leídas.
 *
 * Solamente funciona para funcionarios autenticados.
 *
 * =========================================================
 */

header(
    'Content-Type: application/json; charset=utf-8'
);


/*
 * =========================================================
 * AUTENTICACIÓN
 * =========================================================
 */

require_once '../componentes/auth_funcionario.php';


/*
 * =========================================================
 * RESPUESTAS
 * =========================================================
 */

function responderNotificacion(
    $datos,
    $codigo = 200
) {

    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


function responderErrorNotificacion(
    $mensaje,
    $codigo = 400
) {

    responderNotificacion(
        [
            'ok' => false,
            'mensaje' => $mensaje
        ],
        $codigo
    );
}


/*
 * =========================================================
 * FUNCIONARIO ACTUAL
 * =========================================================
 */

$idFuncionario =
    (int) ($_SESSION['id_funcionario'] ?? 0);


if ($idFuncionario <= 0) {

    responderErrorNotificacion(
        'Sesión de funcionario no válida.',
        401
    );
}


/*
 * =========================================================
 * MÉTODO
 * =========================================================
 */

$metodo =
    strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );


/*
 * =========================================================
 * GET
 * =========================================================
 */

if ($metodo === 'GET') {

    $accion =
        trim(
            $_GET['accion'] ?? 'listar'
        );


    /*
     * =====================================================
     * CONTADOR
     * =====================================================
     */

    if ($accion === 'contador') {

        try {

            $sql = "
                SELECT
                    COUNT(*) AS cantidad
                FROM notificaciones
                WHERE id_funcionario = :id_funcionario
                  AND leida = FALSE
            ";


            $stmt =
                $conexion->prepare($sql);


            $stmt->execute(
                [
                    ':id_funcionario' =>
                        $idFuncionario
                ]
            );


            $resultado =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            responderNotificacion(
                [
                    'ok' => true,
                    'cantidad' =>
                        (int) (
                            $resultado['cantidad'] ?? 0
                        )
                ]
            );


        } catch (PDOException $e) {

            error_log(
                'Error contador notificaciones: ' .
                $e->getMessage()
            );


            responderErrorNotificacion(
                'No fue posible obtener las notificaciones.',
                500
            );
        }
    }


    /*
     * =====================================================
     * LISTAR
     * =====================================================
     */

    if ($accion === 'listar') {

        $limite =
            (int) (
                $_GET['limite'] ?? 10
            );


        if ($limite < 1) {
            $limite = 10;
        }


        if ($limite > 50) {
            $limite = 50;
        }


        try {

            $sql = "
                SELECT
                    n.id_notificacion,
                    n.id_reserva,
                    n.titulo,
                    n.mensaje,
                    n.leida,
                    n.fecha_creacion,

                    r.id_horario,
                    r.id_servicio,

                    s.nombre AS nombre_servicio,

                    h.fecha,
                    h.hora

                FROM notificaciones n

                INNER JOIN reservas r
                    ON r.id_reserva = n.id_reserva

                INNER JOIN servicios s
                    ON s.id_servicio = r.id_servicio

                INNER JOIN horarios h
                    ON h.id_horario = r.id_horario

                WHERE n.id_funcionario = :id_funcionario

                ORDER BY
                    n.fecha_creacion DESC,
                    n.id_notificacion DESC

                LIMIT :limite
            ";


            $stmt =
                $conexion->prepare($sql);


            $stmt->bindValue(
                ':id_funcionario',
                $idFuncionario,
                PDO::PARAM_INT
            );


            $stmt->bindValue(
                ':limite',
                $limite,
                PDO::PARAM_INT
            );


            $stmt->execute();


            $notificaciones =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            $sqlCantidad = "
                SELECT
                    COUNT(*) AS cantidad
                FROM notificaciones
                WHERE id_funcionario = :id_funcionario
                  AND leida = FALSE
            ";


            $stmtCantidad =
                $conexion->prepare(
                    $sqlCantidad
                );


            $stmtCantidad->execute(
                [
                    ':id_funcionario' =>
                        $idFuncionario
                ]
            );


            $cantidad =
                $stmtCantidad->fetch(
                    PDO::FETCH_ASSOC
                );


            responderNotificacion(
                [
                    'ok' => true,

                    'cantidad' =>
                        (int) (
                            $cantidad['cantidad'] ?? 0
                        ),

                    'notificaciones' =>
                        $notificaciones
                ]
            );


        } catch (PDOException $e) {

            error_log(
                'Error listar notificaciones: ' .
                $e->getMessage()
            );


            responderErrorNotificacion(
                'No fue posible obtener las notificaciones.',
                500
            );
        }
    }


    responderErrorNotificacion(
        'Acción no válida.',
        400
    );
}


/*
 * =========================================================
 * POST
 * =========================================================
 */

if ($metodo === 'POST') {

    $accion =
        trim(
            $_POST['accion'] ?? ''
        );


    /*
     * =====================================================
     * MARCAR UNA COMO LEÍDA
     * =====================================================
     */

    if ($accion === 'marcar_leida') {

        $idNotificacion =
            (int) (
                $_POST['id_notificacion'] ?? 0
            );


        if ($idNotificacion <= 0) {

            responderErrorNotificacion(
                'Notificación no válida.',
                400
            );
        }


        try {

            $sql = "
                UPDATE notificaciones

                SET leida = TRUE

                WHERE id_notificacion =
                    :id_notificacion

                  AND id_funcionario =
                    :id_funcionario
            ";


            $stmt =
                $conexion->prepare($sql);


            $stmt->execute(
                [
                    ':id_notificacion' =>
                        $idNotificacion,

                    ':id_funcionario' =>
                        $idFuncionario
                ]
            );


            responderNotificacion(
                [
                    'ok' => true,
                    'mensaje' =>
                        'Notificación marcada como leída.'
                ]
            );


        } catch (PDOException $e) {

            error_log(
                'Error marcar notificación: ' .
                $e->getMessage()
            );


            responderErrorNotificacion(
                'No fue posible actualizar la notificación.',
                500
            );
        }
    }


    /*
     * =====================================================
     * MARCAR TODAS COMO LEÍDAS
     * =====================================================
     */

    if ($accion === 'marcar_todas_leidas') {

        try {

            $sql = "
                UPDATE notificaciones

                SET leida = TRUE

                WHERE id_funcionario =
                    :id_funcionario

                  AND leida = FALSE
            ";


            $stmt =
                $conexion->prepare($sql);


            $stmt->execute(
                [
                    ':id_funcionario' =>
                        $idFuncionario
                ]
            );


            responderNotificacion(
                [
                    'ok' => true,

                    'mensaje' =>
                        'Todas las notificaciones fueron marcadas como leídas.'
                ]
            );


        } catch (PDOException $e) {

            error_log(
                'Error marcar todas las notificaciones: ' .
                $e->getMessage()
            );


            responderErrorNotificacion(
                'No fue posible actualizar las notificaciones.',
                500
            );
        }
    }


    responderErrorNotificacion(
        'Acción no válida.',
        400
    );
}


/*
 * =========================================================
 * MÉTODO NO PERMITIDO
 * =========================================================
 */

responderErrorNotificacion(
    'Método no permitido.',
    405
);