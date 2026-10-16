<?php

$host = "localhost";
$port = "5432";
$dbname = "cesfam";
$user = "postgres";
$password = "frenixso1559";

try 
{

    $conexion = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );

    $conexion->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $conexion->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    http_response_code(500);

    die(
        "Error de conexión con PostgreSQL."
    );

}

