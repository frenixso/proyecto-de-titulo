<?php
require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $consulta = "
        SELECT
            id_profesional,
            nombre,
            apellido,
            rut,
            estamento,
            nodo
        FROM profesionales
        WHERE activo = TRUE
        ORDER BY apellido ASC, nombre ASC
    ";

    $stmt = $conexion->prepare($consulta);
    $stmt->execute();

    echo json_encode([
        'ok' => true,
        'profesionales' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'mensaje' => 'No se pudieron obtener los profesionales.'
    ], JSON_UNESCAPED_UNICODE);
}
