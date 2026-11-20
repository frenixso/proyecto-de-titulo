<?php

/*
|--------------------------------------------------------------------------
| API - OBTENER DEPARTAMENTOS Y SERVICIOS
|--------------------------------------------------------------------------
| Retorna los departamentos activos registrados en la base de datos.
|
| Los servicios inactivos NO se entregan al frontend.
|
| Un departamento será considerado seleccionable cuando:
| - Esté activo.
| - Tenga al menos un servicio activo.
|
| Los departamentos inactivos NO se retornan al frontend.
|--------------------------------------------------------------------------
*/


header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| CONEXIÓN
|--------------------------------------------------------------------------
*/

require_once '../config/conexion.php';


/*
|--------------------------------------------------------------------------
| RESPUESTA DE ERROR
|--------------------------------------------------------------------------
*/

function responderError($mensaje, $codigo = 500)
{
    http_response_code($codigo);

    echo json_encode(
        [
            'ok' => false,
            'mensaje' => $mensaje
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| CONSULTA
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            d.id_departamento,
            d.nombre AS nombre_departamento,
            d.activo AS departamento_activo,

            s.id_servicio,
            s.nombre AS nombre_servicio,
            s.descripcion AS descripcion_servicio,
            s.activo AS servicio_activo

        FROM departamentos d

        LEFT JOIN servicios s
            ON s.id_departamento = d.id_departamento
            AND s.activo = TRUE

        WHERE d.activo = TRUE

        ORDER BY
            d.id_departamento ASC,
            s.id_servicio ASC
    ";


    $stmt = $conexion->prepare($sql);

    $stmt->execute();

    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | AGRUPAR DEPARTAMENTOS
    |--------------------------------------------------------------------------
    */

    $departamentos = [];


    foreach ($filas as $fila) {

        $idDepartamento = (int) $fila['id_departamento'];


        /*
        |--------------------------------------------------------------------------
        | CREAR DEPARTAMENTO
        |--------------------------------------------------------------------------
        */

        if (!isset($departamentos[$idDepartamento])) {

            $departamentos[$idDepartamento] = [
                'id_departamento' => $idDepartamento,
                'nombre' => $fila['nombre_departamento'],
                'activo' => (bool) $fila['departamento_activo'],
                'seleccionable' => false,
                'servicios' => []
            ];

        }


        /*
        |--------------------------------------------------------------------------
        | AGREGAR SERVICIO
        |--------------------------------------------------------------------------
        */

        if (
            $fila['id_servicio'] !== null
            && (bool) $fila['servicio_activo'] === true
        ) {

            $departamentos[$idDepartamento]['servicios'][] = [
                'id_servicio' => (int) $fila['id_servicio'],
                'nombre' => $fila['nombre_servicio'],
                'descripcion' => $fila['descripcion_servicio']
            ];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | DETERMINAR SI EL DEPARTAMENTO SE PUEDE SELECCIONAR
    |--------------------------------------------------------------------------
    */

    foreach ($departamentos as &$departamento) {

        $tieneServicios =
            count($departamento['servicios']) > 0;

        $departamento['seleccionable'] =
            $departamento['activo']
            && $tieneServicios;

    }

    unset($departamento);


    /*
    |--------------------------------------------------------------------------
    | RESPUESTA
    |--------------------------------------------------------------------------
    */

    echo json_encode(
        [
            'ok' => true,
            'departamentos' => array_values($departamentos)
        ],
        JSON_UNESCAPED_UNICODE
    );


} catch (PDOException $e) {

    responderError(
        'No fue posible obtener los departamentos y servicios.'
    );

} catch (Exception $e) {

    responderError(
        'Ocurrió un error al obtener los departamentos.'
    );

}