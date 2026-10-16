<?php

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';


/* =========================================================
   DATOS DEL FUNCIONARIO Y SESIÓN
   ========================================================= */

$nombreFuncionario =
    $_SESSION['nombre_funcionario'] ?? '';

$apellidoFuncionario =
    $_SESSION['apellido_funcionario'] ?? '';

$usuarioFuncionario =
    $_SESSION['usuario_funcionario'] ?? '';

$rolFuncionario =
    $_SESSION['rol_funcionario'] ?? '';

$pagina_actual = 'horarios';


if (in_array(strtolower(trim($rolFuncionario)), ['admin', 'administrador'], true)) {
    $rolFuncionario = 'administrador';
}


$idDepartamentoFuncionario =
    isset($_SESSION['id_departamento'])
        ? (int) $_SESSION['id_departamento']
        : 0;


if ($rolFuncionario === 'administrador' && $idDepartamentoFuncionario <= 0) {

    try {

        if (!empty($usuarioFuncionario)) {

            $stmtDepAutorec =
                $conexion->prepare("
                    SELECT id_departamento
                    FROM funcionarios
                    WHERE usuario = :usuario
                    LIMIT 1
                ");

            $stmtDepAutorec->execute([
                ':usuario' => $usuarioFuncionario
            ]);

            $depIdBD = $stmtDepAutorec->fetchColumn();

            if ($depIdBD !== false && (int) $depIdBD > 0) {

                $idDepartamentoFuncionario = (int) $depIdBD;
                $_SESSION['id_departamento'] = $idDepartamentoFuncionario;
            }
        }

    } catch (PDOException $e) {
        // No interrumpir la página por una falla en la recuperación silenciosa.
        error_log('Error recuperando departamento del funcionario: ' . $e->getMessage());
    }
}


/* =========================================================
   MENSAJES
   ========================================================= */

$mensaje = '';
$tipoMensaje = '';


/* =========================================================
   FUNCIONES AUXILIARES
   ========================================================= */

function esVerdadero($valor): bool
{
    return (
        $valor === true ||
        $valor === 't' ||
        $valor === 'true' ||
        $valor === '1' ||
        $valor === 1
    );
}


/*
 * Convierte un booleano PHP en texto reconocido por PostgreSQL.
 * PDO puede convertir false en una cadena vacía al ejecutar consultas.
 */
function booleanoPostgres(bool $valor): string
{
    return $valor ? 'true' : 'false';
}


/*
 * Devuelve los servicios que forman el grupo unificado
 * de un departamento.
 */
function obtenerServiciosUnificados(
    PDO $conexion,
    int $idDepartamento
): array {

    if ($idDepartamento <= 0) {
        return [];
    }

    $sql = "
        SELECT uhs.id_servicio
        FROM unificacion_horarios_servicios uhs
        INNER JOIN servicios s
            ON s.id_servicio = uhs.id_servicio
        WHERE uhs.id_departamento = :id_departamento
          AND s.id_departamento = :id_departamento_servicio
          AND s.activo = TRUE
        ORDER BY uhs.id_servicio ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':id_departamento' => $idDepartamento,
        ':id_departamento_servicio' => $idDepartamento
    ]);

    return array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}


/*
 * Obtiene el grupo al que pertenece un servicio.
 */
function obtenerGrupoServicio(
    PDO $conexion,
    int $idServicio
): array {

    if ($idServicio <= 0) {
        return [];
    }

    $sql = "
        SELECT uhs.id_servicio
        FROM unificacion_horarios_servicios uhs
        INNER JOIN servicios s
            ON s.id_servicio = uhs.id_servicio
        WHERE uhs.id_departamento = (
            SELECT id_departamento
            FROM servicios
            WHERE id_servicio = :id_servicio_departamento
            LIMIT 1
        )
        AND s.activo = TRUE
        ORDER BY uhs.id_servicio ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':id_servicio_departamento' => $idServicio
    ]);

    return array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}


/*
 * =========================================================
 * GUARDAR CONFIGURACIÓN DIARIA DE UN SERVICIO
 * =========================================================
 *
 * Los booleanos se convierten explícitamente a 'true'/'false'
 * para evitar el error PostgreSQL SQLSTATE[22P02].
 *
 * Cuando no existe almuerzo:
 * - tiene_almuerzo = FALSE
 * - almuerzo_inicio = NULL
 * - almuerzo_fin = NULL
 * =========================================================
 */

function guardarConfiguracionServicio(
    PDO $conexion,
    int $idServicio,
    int $diaSemana,
    string $horaInicio,
    string $horaFin,
    int $intervalo,
    bool $activo,
    bool $tieneAlmuerzo,
    ?string $almuerzoInicio,
    ?string $almuerzoFin
): void {

    $activoPostgres = booleanoPostgres($activo);

    $almuerzoPostgres = booleanoPostgres($tieneAlmuerzo);

    if (!$tieneAlmuerzo) {
        $almuerzoInicio = null;
        $almuerzoFin = null;
    }


    $sqlExiste = "
        SELECT id_configuracion
        FROM configuracion_horarios
        WHERE id_servicio = :id_servicio
          AND dia_semana = :dia_semana
        LIMIT 1
    ";

    $stmtExiste = $conexion->prepare($sqlExiste);

    $stmtExiste->execute([
        ':id_servicio' => $idServicio,
        ':dia_semana' => $diaSemana
    ]);

    $idConfiguracion = $stmtExiste->fetchColumn();


    if ($idConfiguracion) {

        $sqlUpdate = "
            UPDATE configuracion_horarios
            SET
                hora_inicio = :hora_inicio,
                hora_fin = :hora_fin,
                intervalo_minutos = :intervalo_minutos,
                activo = :activo,
                tiene_almuerzo = :tiene_almuerzo,
                almuerzo_inicio = :almuerzo_inicio,
                almuerzo_fin = :almuerzo_fin,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE id_configuracion = :id_configuracion
        ";

        $stmtUpdate = $conexion->prepare($sqlUpdate);

        $stmtUpdate->execute([
            ':hora_inicio' => $horaInicio,
            ':hora_fin' => $horaFin,
            ':intervalo_minutos' => $intervalo,
            ':activo' => $activoPostgres,
            ':tiene_almuerzo' => $almuerzoPostgres,
            ':almuerzo_inicio' => $almuerzoInicio,
            ':almuerzo_fin' => $almuerzoFin,
            ':id_configuracion' => $idConfiguracion
        ]);

    } else {

        $sqlInsert = "
            INSERT INTO configuracion_horarios (
                id_servicio,
                dia_semana,
                hora_inicio,
                hora_fin,
                intervalo_minutos,
                activo,
                tiene_almuerzo,
                almuerzo_inicio,
                almuerzo_fin,
                fecha_creacion,
                fecha_actualizacion
            )
            VALUES (
                :id_servicio,
                :dia_semana,
                :hora_inicio,
                :hora_fin,
                :intervalo_minutos,
                :activo,
                :tiene_almuerzo,
                :almuerzo_inicio,
                :almuerzo_fin,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ";

        $stmtInsert = $conexion->prepare($sqlInsert);

        $stmtInsert->execute([
            ':id_servicio' => $idServicio,
            ':dia_semana' => $diaSemana,
            ':hora_inicio' => $horaInicio,
            ':hora_fin' => $horaFin,
            ':intervalo_minutos' => $intervalo,
            ':activo' => $activoPostgres,
            ':tiene_almuerzo' => $almuerzoPostgres,
            ':almuerzo_inicio' => $almuerzoInicio,
            ':almuerzo_fin' => $almuerzoFin
        ]);
    }
}


/*
 * Obtiene todos los servicios activos de un departamento.
 */
function obtenerServiciosDepartamento(
    PDO $conexion,
    int $idDepartamento
): array {

    if ($idDepartamento <= 0) {
        return [];
    }

    $sql = "
        SELECT id_servicio
        FROM servicios
        WHERE id_departamento = :id_departamento
          AND activo = TRUE
        ORDER BY id_servicio ASC
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':id_departamento' => $idDepartamento
    ]);

    return array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}


/*
 * Obtiene los servicios sobre los que debe aplicarse
 * una modificación.
 */
function obtenerServiciosObjetivo(
    PDO $conexion,
    int $idServicio,
    int $idDepartamento,
    bool $horariosUnificados
): array {

    if ($horariosUnificados && $idDepartamento > 0) {

        return obtenerServiciosDepartamento(
            $conexion,
            $idDepartamento
        );
    }

    if ($idServicio > 0) {

        $grupo = obtenerGrupoServicio(
            $conexion,
            $idServicio
        );

        if (count($grupo) >= 2) {
            return $grupo;
        }

        return [$idServicio];
    }

    return [];
}


/* =========================================================
   PARÁMETROS GET
   ========================================================= */

$filtroDepartamento =
    isset($_GET['departamento'])
        ? (int) $_GET['departamento']
        : 0;

$filtroServicio =
    isset($_GET['servicio'])
        ? (int) $_GET['servicio']
        : 0;


/* =========================================================
   DETERMINAR DEPARTAMENTO PARA ADMINISTRADOR
   ========================================================= */

if ($rolFuncionario === 'administrador') {

    if ($idDepartamentoFuncionario > 0) {
        $filtroDepartamento = $idDepartamentoFuncionario;
    }

} elseif (
    $rolFuncionario !== 'superadmin' &&
    $idDepartamentoFuncionario > 0
) {

    $filtroDepartamento = $idDepartamentoFuncionario;
}


/* =========================================================
   DEPARTAMENTOS
   ========================================================= */

$departamentos = [];

try {

    if ($rolFuncionario === 'superadmin') {

        $sqlDepartamentos = "
            SELECT
                id_departamento,
                nombre,
                activo,
                horarios_unificados
            FROM departamentos
            WHERE activo = TRUE
            ORDER BY nombre ASC
        ";

        $stmtDepartamentos = $conexion->prepare($sqlDepartamentos);
        $stmtDepartamentos->execute();

    } else {

        $sqlDepartamentos = "
            SELECT
                id_departamento,
                nombre,
                activo,
                horarios_unificados
            FROM departamentos
            WHERE activo = TRUE
              AND id_departamento = :id_departamento
            ORDER BY nombre ASC
        ";

        $stmtDepartamentos = $conexion->prepare($sqlDepartamentos);

        $stmtDepartamentos->execute([
            ':id_departamento' => $filtroDepartamento
        ]);
    }

    $departamentos = $stmtDepartamentos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Error consultando departamentos: ' . $e->getMessage());
    $departamentos = [];
}


/* =========================================================
   VALIDAR DEPARTAMENTO SELECCIONADO
   ========================================================= */

$nombreDepartamentoSeleccionado = '';

foreach ($departamentos as $departamento) {

    if ((int) $departamento['id_departamento'] === $filtroDepartamento) {
        $nombreDepartamentoSeleccionado = $departamento['nombre'];
        break;
    }
}


/* =========================================================
   SERVICIOS
   ========================================================= */

$servicios = [];

try {

    if ($filtroDepartamento > 0) {

        $sqlServicios = "
            SELECT
                s.id_servicio,
                s.nombre,
                s.descripcion,
                s.activo,
                s.id_departamento,
                d.nombre AS nombre_departamento
            FROM servicios s
            INNER JOIN departamentos d
                ON d.id_departamento = s.id_departamento
            WHERE s.id_departamento = :id_departamento
              AND s.activo = TRUE
            ORDER BY s.nombre ASC
        ";

        $stmtServicios = $conexion->prepare($sqlServicios);

        $stmtServicios->execute([
            ':id_departamento' => $filtroDepartamento
        ]);

    } elseif ($rolFuncionario === 'superadmin') {

        $sqlServicios = "
            SELECT
                s.id_servicio,
                s.nombre,
                s.descripcion,
                s.activo,
                s.id_departamento,
                d.nombre AS nombre_departamento
            FROM servicios s
            INNER JOIN departamentos d
                ON d.id_departamento = s.id_departamento
            WHERE s.activo = TRUE
            ORDER BY d.nombre ASC, s.nombre ASC
        ";

        $stmtServicios = $conexion->prepare($sqlServicios);
        $stmtServicios->execute();

    } else {

        $servicios = [];
        $stmtServicios = null;
    }

    if ($stmtServicios) {
        $servicios = $stmtServicios->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {

    error_log('Error consultando servicios: ' . $e->getMessage());
    $servicios = [];
}


/* =========================================================
   VALIDAR SERVICIO SELECCIONADO
   ========================================================= */

if ($filtroServicio > 0 && $filtroDepartamento > 0) {

    $servicioPertenece = false;

    foreach ($servicios as $servicio) {

        if ((int) $servicio['id_servicio'] === $filtroServicio) {
            $servicioPertenece = true;
            break;
        }
    }

    if (!$servicioPertenece) {
        $filtroServicio = 0;
    }
}


/* =========================================================
   ESTADO DE UNIFICACIÓN
   ========================================================= */

$horariosUnificados = false;
$serviciosUnificados = [];

if ($filtroDepartamento > 0) {

    try {

        $sqlEstadoDepartamento = "
            SELECT horarios_unificados
            FROM departamentos
            WHERE id_departamento = :id_departamento
            LIMIT 1
        ";

        $stmtEstadoDepartamento = $conexion->prepare($sqlEstadoDepartamento);

        $stmtEstadoDepartamento->execute([
            ':id_departamento' => $filtroDepartamento
        ]);

        $valorUnificacion = $stmtEstadoDepartamento->fetchColumn();

        $horariosUnificados = esVerdadero($valorUnificacion);

        $serviciosUnificados = obtenerServiciosUnificados(
            $conexion,
            $filtroDepartamento
        );

        if ($horariosUnificados && count($serviciosUnificados) === 0) {
            $serviciosUnificados = obtenerServiciosDepartamento(
                $conexion,
                $filtroDepartamento
            );
        }

    } catch (PDOException $e) {

        error_log('Error consultando unificación: ' . $e->getMessage());
        $horariosUnificados = false;
        $serviciosUnificados = [];
    }
}


/* =========================================================
   MODO TODOS LOS SERVICIOS
   ========================================================= */

$modoTodosServicios = (
    $filtroDepartamento > 0 &&
    $filtroServicio <= 0
);


/* =========================================================
   PROCESAMIENTO POST
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    try {

        /* =====================================================
           GUARDAR UNIFICACIÓN
           ===================================================== */

        if ($accion === 'guardar_unificacion') {

            $idDepartamentoPost = (int) (
                $_POST['id_departamento'] ?? 0
            );

            if ($idDepartamentoPost <= 0) {
                throw new Exception('Debe seleccionar un departamento.');
            }

            if (
                $rolFuncionario !== 'superadmin' &&
                $idDepartamentoFuncionario > 0 &&
                $idDepartamentoPost !== $idDepartamentoFuncionario
            ) {
                throw new Exception('No tienes permisos para modificar este departamento.');
            }

            $activarUnificacion = isset($_POST['horarios_unificados']);

            $serviciosSeleccionados = $_POST['servicios_seleccionados'] ?? [];

            if (!is_array($serviciosSeleccionados)) {
                $serviciosSeleccionados = [];
            }

            $serviciosSeleccionados = array_values(
                array_unique(
                    array_map('intval', $serviciosSeleccionados)
                )
            );

            $serviciosValidos = obtenerServiciosDepartamento(
                $conexion,
                $idDepartamentoPost
            );

            $serviciosSeleccionados = array_values(
                array_intersect(
                    $serviciosSeleccionados,
                    $serviciosValidos
                )
            );

            if (!$activarUnificacion) {

                $conexion->beginTransaction();

                $sqlDesactivar = "
                    UPDATE departamentos
                    SET horarios_unificados = FALSE
                    WHERE id_departamento = :id_departamento
                ";

                $stmtDesactivar = $conexion->prepare($sqlDesactivar);

                $stmtDesactivar->execute([
                    ':id_departamento' => $idDepartamentoPost
                ]);

                $sqlEliminarGrupo = "
                    DELETE FROM unificacion_horarios_servicios
                    WHERE id_departamento = :id_departamento
                ";

                $stmtEliminarGrupo = $conexion->prepare($sqlEliminarGrupo);

                $stmtEliminarGrupo->execute([
                    ':id_departamento' => $idDepartamentoPost
                ]);

                $conexion->commit();

                $mensaje = 'La unificación fue desactivada. Cada servicio vuelve a utilizar su propia configuración.';
                $tipoMensaje = 'success';

            } else {

                if (count($serviciosSeleccionados) < 2) {
                    throw new Exception(
                        'Selecciona al menos 2 servicios para unificar sus horarios.'
                    );
                }

                $esUnificacionCompleta = (
                    count($serviciosSeleccionados) === count($serviciosValidos)
                );

                $conexion->beginTransaction();

                $sqlEstado = "
                    UPDATE departamentos
                    SET horarios_unificados = :horarios_unificados
                    WHERE id_departamento = :id_departamento
                ";

                $stmtEstado = $conexion->prepare($sqlEstado);

                $stmtEstado->execute([
                    ':horarios_unificados' => booleanoPostgres($esUnificacionCompleta),
                    ':id_departamento' => $idDepartamentoPost
                ]);

                $idServicioReferencia = $serviciosSeleccionados[0];

                $sqlConfigReferencia = "
                    SELECT
                        dia_semana,
                        hora_inicio,
                        hora_fin,
                        intervalo_minutos,
                        activo,
                        tiene_almuerzo,
                        almuerzo_inicio,
                        almuerzo_fin
                    FROM configuracion_horarios
                    WHERE id_servicio = :id_servicio
                    ORDER BY dia_semana ASC
                ";

                $stmtConfigReferencia = $conexion->prepare($sqlConfigReferencia);

                $stmtConfigReferencia->execute([
                    ':id_servicio' => $idServicioReferencia
                ]);

                $configReferencia = $stmtConfigReferencia->fetchAll(PDO::FETCH_ASSOC);

                $sqlEliminarGrupo = "
                    DELETE FROM unificacion_horarios_servicios
                    WHERE id_departamento = :id_departamento
                ";

                $stmtEliminarGrupo = $conexion->prepare($sqlEliminarGrupo);

                $stmtEliminarGrupo->execute([
                    ':id_departamento' => $idDepartamentoPost
                ]);

                $sqlInsertGrupo = "
                    INSERT INTO unificacion_horarios_servicios (
                        id_departamento,
                        id_servicio
                    )
                    VALUES (
                        :id_departamento,
                        :id_servicio
                    )
                ";

                $stmtInsertGrupo = $conexion->prepare($sqlInsertGrupo);

                foreach ($serviciosSeleccionados as $idServicio) {

                    $stmtInsertGrupo->execute([
                        ':id_departamento' => $idDepartamentoPost,
                        ':id_servicio' => $idServicio
                    ]);
                }

                if (count($configReferencia) > 0) {

                    foreach ($serviciosSeleccionados as $idServicio) {

                        if ($idServicio === $idServicioReferencia) {
                            continue;
                        }

                        foreach ($configReferencia as $conf) {

                            guardarConfiguracionServicio(
                                $conexion,
                                $idServicio,
                                (int) $conf['dia_semana'],
                                substr($conf['hora_inicio'], 0, 5),
                                substr($conf['hora_fin'], 0, 5),
                                (int) $conf['intervalo_minutos'],
                                esVerdadero($conf['activo']),
                                esVerdadero($conf['tiene_almuerzo']),
                                !empty($conf['almuerzo_inicio'])
                                    ? substr($conf['almuerzo_inicio'], 0, 5)
                                    : null,
                                !empty($conf['almuerzo_fin'])
                                    ? substr($conf['almuerzo_fin'], 0, 5)
                                    : null
                            );
                        }
                    }
                }

                if (!$esUnificacionCompleta) {

                    $sqlFlagSelectivo = "
                        UPDATE departamentos
                        SET horarios_unificados = FALSE
                        WHERE id_departamento = :id_departamento
                    ";

                    $stmtFlagSelectivo = $conexion->prepare($sqlFlagSelectivo);

                    $stmtFlagSelectivo->execute([
                        ':id_departamento' => $idDepartamentoPost
                    ]);
                }

                $conexion->commit();

                if ($esUnificacionCompleta) {
                    $mensaje = 'Se unificaron los horarios de todos los servicios del departamento.';
                } else {
                    $cantidad = count($serviciosSeleccionados);
                    $mensaje = "Se unificaron {$cantidad} servicios. Los demás servicios permanecen independientes.";
                }

                $tipoMensaje = 'success';
            }

            $urlRedireccion = 'horarios.php?' . http_build_query([
                'departamento' => $idDepartamentoPost,
                'servicio' => 0
            ]);

            header(
                'Location: ' . $urlRedireccion .
                '&mensaje=' . urlencode($mensaje) .
                '&tipo=success'
            );

            exit;
        }


        /* =====================================================
           GUARDAR CONFIGURACIÓN DE DÍA
           ===================================================== */

        if ($accion === 'guardar_configuracion_dia') {

            $idServicioPost = (int) (
                $_POST['id_servicio'] ?? 0
            );

            $diaSemana = (int) (
                $_POST['dia_semana'] ?? 0
            );

            $horaInicio = trim(
                $_POST['hora_inicio'] ?? ''
            );

            $horaFin = trim(
                $_POST['hora_fin'] ?? ''
            );

            $intervalo = (int) (
                $_POST['intervalo_minutos'] ?? 30
            );

            $activo = (($_POST['activo'] ?? '0') === '1');

            $tieneAlmuerzo = isset($_POST['tiene_almuerzo']);

            $almuerzoInicio = trim(
                $_POST['almuerzo_inicio'] ?? ''
            );

            $almuerzoFin = trim(
                $_POST['almuerzo_fin'] ?? ''
            );


            if ($diaSemana < 1 || $diaSemana > 7) {
                throw new Exception('El día seleccionado no es válido.');
            }

            if ($horaInicio === '' || $horaFin === '') {
                throw new Exception('Debes indicar la hora de apertura y cierre.');
            }

            if ($horaInicio >= $horaFin) {
                throw new Exception(
                    'La hora de apertura debe ser menor que la hora de cierre.'
                );
            }

            $intervalosPermitidos = [15, 20, 30, 45, 60];

            if (!in_array($intervalo, $intervalosPermitidos, true)) {
                throw new Exception('El intervalo seleccionado no es válido.');
            }

            if ($tieneAlmuerzo) {

                if ($almuerzoInicio === '' || $almuerzoFin === '') {
                    throw new Exception(
                        'Debes indicar el inicio y término del almuerzo.'
                    );
                }

                if ($almuerzoInicio >= $almuerzoFin) {
                    throw new Exception(
                        'El inicio del almuerzo debe ser menor que el término.'
                    );
                }

                if ($almuerzoInicio < $horaInicio || $almuerzoFin > $horaFin) {
                    throw new Exception(
                        'La pausa de almuerzo debe estar dentro del horario de atención.'
                    );
                }

            } else {

                $almuerzoInicio = null;
                $almuerzoFin = null;
            }


            if ($idServicioPost > 0) {

                $sqlDeptServicio = "
                    SELECT id_departamento
                    FROM servicios
                    WHERE id_servicio = :id_servicio
                      AND activo = TRUE
                    LIMIT 1
                ";

                $stmtDeptServicio = $conexion->prepare($sqlDeptServicio);

                $stmtDeptServicio->execute([
                    ':id_servicio' => $idServicioPost
                ]);

                $idDepartamentoServicio = (int) $stmtDeptServicio->fetchColumn();

            } else {

                $idDepartamentoServicio = $filtroDepartamento;
            }


            if ($idDepartamentoServicio <= 0) {
                throw new Exception(
                    'No se pudo determinar el departamento del servicio.'
                );
            }


            $sqlEstadoDept = "
                SELECT horarios_unificados
                FROM departamentos
                WHERE id_departamento = :id_departamento
                LIMIT 1
            ";

            $stmtEstadoDept = $conexion->prepare($sqlEstadoDept);

            $stmtEstadoDept->execute([
                ':id_departamento' => $idDepartamentoServicio
            ]);

            $horariosUnificadosPost = esVerdadero(
                $stmtEstadoDept->fetchColumn()
            );


            $todosServicios = isset($_POST['todos_servicios']);

            if ($todosServicios && $idDepartamentoServicio > 0) {

                $serviciosObjetivo = obtenerServiciosDepartamento(
                    $conexion,
                    $idDepartamentoServicio
                );

            } elseif ($idServicioPost > 0) {

                $serviciosObjetivo = obtenerServiciosObjetivo(
                    $conexion,
                    $idServicioPost,
                    $idDepartamentoServicio,
                    $horariosUnificadosPost
                );

            } else {

                throw new Exception('Debes seleccionar un servicio.');
            }


            if (count($serviciosObjetivo) === 0) {
                throw new Exception(
                    'No se encontraron servicios para actualizar.'
                );
            }


            $conexion->beginTransaction();

            foreach ($serviciosObjetivo as $idServicioObjetivo) {

                guardarConfiguracionServicio(
                    $conexion,
                    $idServicioObjetivo,
                    $diaSemana,
                    $horaInicio,
                    $horaFin,
                    $intervalo,
                    $activo,
                    $tieneAlmuerzo,
                    $almuerzoInicio,
                    $almuerzoFin
                );
            }

            $conexion->commit();


            $mensaje = count($serviciosObjetivo) > 1
                ? 'La configuración fue aplicada a los servicios unificados.'
                : 'La configuración del día fue guardada correctamente.';

            $tipoMensaje = 'success';
        }


        /* =====================================================
           GUARDAR EXCEPCIÓN
           ===================================================== */

        if ($accion === 'guardar_excepcion') {

            $idServicioPost = (int) (
                $_POST['id_servicio'] ?? 0
            );

            $fechaExcepcion = trim(
                $_POST['fecha_excepcion'] ?? ''
            );

            $tipoExcepcion = $_POST['tipo_excepcion'] ?? 'bloqueo';

            $horaInicioEsp = trim(
                $_POST['hora_inicio_esp'] ?? ''
            );

            $horaFinEsp = trim(
                $_POST['hora_fin_esp'] ?? ''
            );

            $motivo = trim(
                $_POST['motivo_excepcion'] ?? ''
            );


            if ($fechaExcepcion === '') {
                throw new Exception('Debes seleccionar una fecha.');
            }

            $fechaValidacion = DateTime::createFromFormat('Y-m-d', $fechaExcepcion);

            if (
                !$fechaValidacion ||
                $fechaValidacion->format('Y-m-d') !== $fechaExcepcion
            ) {
                throw new Exception('La fecha de excepción no es válida.');
            }

            if ($motivo === '') {
                throw new Exception('Debes indicar un motivo u observación.');
            }

            if (!in_array($tipoExcepcion, ['bloqueo', 'horario_especial'], true)) {
                throw new Exception('El tipo de excepción no es válido.');
            }

            if ($tipoExcepcion === 'horario_especial') {

                if ($horaInicioEsp === '' || $horaFinEsp === '') {
                    throw new Exception('Debes indicar el horario especial.');
                }

                if ($horaInicioEsp >= $horaFinEsp) {
                    throw new Exception(
                        'La hora de inicio debe ser menor que la hora de término.'
                    );
                }

            } else {

                $horaInicioEsp = null;
                $horaFinEsp = null;
            }


            if ($idServicioPost > 0) {

                $sqlDeptServicio = "
                    SELECT id_departamento
                    FROM servicios
                    WHERE id_servicio = :id_servicio
                      AND activo = TRUE
                    LIMIT 1
                ";

                $stmtDeptServicio = $conexion->prepare($sqlDeptServicio);

                $stmtDeptServicio->execute([
                    ':id_servicio' => $idServicioPost
                ]);

                $idDepartamentoServicio = (int) $stmtDeptServicio->fetchColumn();

            } else {

                $idDepartamentoServicio = $filtroDepartamento;
            }


            if ($idDepartamentoServicio <= 0) {
                throw new Exception('No se pudo determinar el departamento.');
            }


            $sqlEstadoDept = "
                SELECT horarios_unificados
                FROM departamentos
                WHERE id_departamento = :id_departamento
                LIMIT 1
            ";

            $stmtEstadoDept = $conexion->prepare($sqlEstadoDept);

            $stmtEstadoDept->execute([
                ':id_departamento' => $idDepartamentoServicio
            ]);

            $horariosUnificadosPost = esVerdadero(
                $stmtEstadoDept->fetchColumn()
            );


            $serviciosObjetivo = obtenerServiciosObjetivo(
                $conexion,
                $idServicioPost,
                $idDepartamentoServicio,
                $horariosUnificadosPost
            );


            if (count($serviciosObjetivo) === 0) {
                throw new Exception(
                    'No se encontraron servicios para aplicar la excepción.'
                );
            }


            $conexion->beginTransaction();

            foreach ($serviciosObjetivo as $idServicioObjetivo) {

                $sqlExiste = "
                    SELECT id_excepcion
                    FROM excepciones_horarios
                    WHERE id_servicio = :id_servicio
                      AND fecha = :fecha
                    LIMIT 1
                ";

                $stmtExiste = $conexion->prepare($sqlExiste);

                $stmtExiste->execute([
                    ':id_servicio' => $idServicioObjetivo,
                    ':fecha' => $fechaExcepcion
                ]);

                $idExcepcion = $stmtExiste->fetchColumn();

                $esBloqueo = ($tipoExcepcion === 'bloqueo');


                if ($idExcepcion) {

                    $sqlUpdate = "
                        UPDATE excepciones_horarios
                        SET
                            es_bloqueo_total = :es_bloqueo_total,
                            hora_inicio = :hora_inicio,
                            hora_fin = :hora_fin,
                            motivo = :motivo
                        WHERE id_excepcion = :id_excepcion
                    ";

                    $stmtUpdate = $conexion->prepare($sqlUpdate);

                    $stmtUpdate->execute([
                        ':es_bloqueo_total' => booleanoPostgres($esBloqueo),
                        ':hora_inicio' => $esBloqueo ? null : $horaInicioEsp,
                        ':hora_fin' => $esBloqueo ? null : $horaFinEsp,
                        ':motivo' => $motivo,
                        ':id_excepcion' => $idExcepcion
                    ]);

                } else {

                    $sqlInsert = "
                        INSERT INTO excepciones_horarios (
                            id_servicio,
                            fecha,
                            es_bloqueo_total,
                            hora_inicio,
                            hora_fin,
                            motivo
                        )
                        VALUES (
                            :id_servicio,
                            :fecha,
                            :es_bloqueo_total,
                            :hora_inicio,
                            :hora_fin,
                            :motivo
                        )
                    ";

                    $stmtInsert = $conexion->prepare($sqlInsert);

                    $stmtInsert->execute([
                        ':id_servicio' => $idServicioObjetivo,
                        ':fecha' => $fechaExcepcion,
                        ':es_bloqueo_total' => booleanoPostgres($esBloqueo),
                        ':hora_inicio' => $esBloqueo ? null : $horaInicioEsp,
                        ':hora_fin' => $esBloqueo ? null : $horaFinEsp,
                        ':motivo' => $motivo
                    ]);
                }
            }


            $conexion->commit();

            $mensaje = count($serviciosObjetivo) > 1
                ? 'La excepción fue aplicada a todos los servicios unificados.'
                : 'La excepción fue guardada correctamente.';

            $tipoMensaje = 'success';
        }


        /* =====================================================
           ELIMINAR EXCEPCIÓN
           ===================================================== */

        if ($accion === 'eliminar_excepcion') {

            $idExcepcion = (int) (
                $_POST['id_excepcion'] ?? 0
            );

            if ($idExcepcion <= 0) {
                throw new Exception('La excepción seleccionada no es válida.');
            }

            $sqlExcepcion = "
                SELECT id_servicio, fecha
                FROM excepciones_horarios
                WHERE id_excepcion = :id_excepcion
                LIMIT 1
            ";

            $stmtExcepcion = $conexion->prepare($sqlExcepcion);

            $stmtExcepcion->execute([
                ':id_excepcion' => $idExcepcion
            ]);

            $excepcion = $stmtExcepcion->fetch(PDO::FETCH_ASSOC);

            if (!$excepcion) {
                throw new Exception('La excepción no existe.');
            }

            $idServicioExcepcion = (int) $excepcion['id_servicio'];
            $fechaReal = $excepcion['fecha'];

            $sqlDeptServicio = "
                SELECT id_departamento
                FROM servicios
                WHERE id_servicio = :id_servicio
                LIMIT 1
            ";

            $stmtDeptServicio = $conexion->prepare($sqlDeptServicio);

            $stmtDeptServicio->execute([
                ':id_servicio' => $idServicioExcepcion
            ]);

            $idDepartamentoServicio = (int) $stmtDeptServicio->fetchColumn();

            $sqlEstadoDept = "
                SELECT horarios_unificados
                FROM departamentos
                WHERE id_departamento = :id_departamento
                LIMIT 1
            ";

            $stmtEstadoDept = $conexion->prepare($sqlEstadoDept);

            $stmtEstadoDept->execute([
                ':id_departamento' => $idDepartamentoServicio
            ]);

            $horariosUnificadosPost = esVerdadero(
                $stmtEstadoDept->fetchColumn()
            );

            $grupo = obtenerServiciosObjetivo(
                $conexion,
                $idServicioExcepcion,
                $idDepartamentoServicio,
                $horariosUnificadosPost
            );

            $conexion->beginTransaction();

            if (count($grupo) > 1) {

                $sqlEliminarGrupo = "
                    DELETE FROM excepciones_horarios
                    WHERE id_servicio IN (
                        SELECT id_servicio
                        FROM unificacion_horarios_servicios
                        WHERE id_departamento = :id_departamento
                    )
                    AND fecha = :fecha
                ";

                $stmtEliminarGrupo = $conexion->prepare($sqlEliminarGrupo);

                $stmtEliminarGrupo->execute([
                    ':id_departamento' => $idDepartamentoServicio,
                    ':fecha' => $fechaReal
                ]);

            } else {

                $sqlEliminar = "
                    DELETE FROM excepciones_horarios
                    WHERE id_excepcion = :id_excepcion
                ";

                $stmtEliminar = $conexion->prepare($sqlEliminar);

                $stmtEliminar->execute([
                    ':id_excepcion' => $idExcepcion
                ]);
            }

            $conexion->commit();

            $mensaje = count($grupo) > 1
                ? 'La excepción fue eliminada para todos los servicios unificados.'
                : 'La excepción fue eliminada correctamente.';

            $tipoMensaje = 'success';
        }

    } catch (Throwable $e) {

        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log('Error en la configuración de horarios: ' . $e->getMessage());

        $mensaje = $e->getMessage();
        $tipoMensaje = 'error';
    }
}


/* =========================================================
   MENSAJE DESDE REDIRECCIÓN
   ========================================================= */

if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
    $tipoMensaje = $_GET['tipo'] ?? 'success';
}


/* =========================================================
   RECARGAR ESTADO DE UNIFICACIÓN
   ========================================================= */

$horariosUnificados = false;
$serviciosUnificados = [];

if ($filtroDepartamento > 0) {

    try {

        $sqlEstado = "
            SELECT horarios_unificados
            FROM departamentos
            WHERE id_departamento = :id_departamento
            LIMIT 1
        ";

        $stmtEstado = $conexion->prepare($sqlEstado);

        $stmtEstado->execute([
            ':id_departamento' => $filtroDepartamento
        ]);

        $horariosUnificados = esVerdadero(
            $stmtEstado->fetchColumn()
        );

        $serviciosUnificados = obtenerServiciosUnificados(
            $conexion,
            $filtroDepartamento
        );

        if ($horariosUnificados && count($serviciosUnificados) === 0) {
            $serviciosUnificados = obtenerServiciosDepartamento(
                $conexion,
                $filtroDepartamento
            );
        }

    } catch (PDOException $e) {

        error_log('Error recargando unificación: ' . $e->getMessage());
        $horariosUnificados = false;
        $serviciosUnificados = [];
    }
}


/* =========================================================
   DETERMINAR SI HAY UNIFICACIÓN SELECTIVA
   ========================================================= */

$unificacionSelectiva = (
    !$horariosUnificados &&
    count($serviciosUnificados) >= 2
);


/* =========================================================
   SERVICIOS SELECCIONADOS PARA LA INTERFAZ
   ========================================================= */

$serviciosSeleccionadosUI = $serviciosUnificados;

if ($horariosUnificados && count($serviciosSeleccionadosUI) === 0) {

    foreach ($servicios as $servicio) {
        $serviciosSeleccionadosUI[] = (int) $servicio['id_servicio'];
    }
}


/* =========================================================
   DETERMINAR SERVICIO DE REFERENCIA
   ========================================================= */

$servicioReferenciaConfiguracion = 0;

if ($horariosUnificados && count($serviciosSeleccionadosUI) > 0) {

    $servicioReferenciaConfiguracion = (int) $serviciosSeleccionadosUI[0];

} elseif ($filtroServicio > 0) {

    $servicioReferenciaConfiguracion = $filtroServicio;
}


/* =========================================================
   CONFIGURACIONES ACTUALES
   ========================================================= */

$configuracionesActuales = [];

if ($servicioReferenciaConfiguracion > 0) {

    try {

        $sqlConfig = "
            SELECT
                dia_semana,
                hora_inicio,
                hora_fin,
                intervalo_minutos,
                activo,
                tiene_almuerzo,
                almuerzo_inicio,
                almuerzo_fin
            FROM configuracion_horarios
            WHERE id_servicio = :id_servicio
            ORDER BY dia_semana ASC
        ";

        $stmtConfig = $conexion->prepare($sqlConfig);

        $stmtConfig->execute([
            ':id_servicio' => $servicioReferenciaConfiguracion
        ]);

        $registrosConfig = $stmtConfig->fetchAll(PDO::FETCH_ASSOC);

        foreach ($registrosConfig as $reg) {
            $configuracionesActuales[(int) $reg['dia_semana']] = $reg;
        }

    } catch (PDOException $e) {

        error_log('Error consultando configuraciones: ' . $e->getMessage());
        $configuracionesActuales = [];
    }
}


/* =========================================================
   EXCEPCIONES ACTUALES
   ========================================================= */

$excepcionesActuales = [];

if ($servicioReferenciaConfiguracion > 0) {

    try {

        $sqlExcepciones = "
            SELECT
                id_excepcion,
                fecha,
                es_bloqueo_total,
                hora_inicio,
                hora_fin,
                motivo
            FROM excepciones_horarios
            WHERE id_servicio = :id_servicio
              AND fecha >= CURRENT_DATE
            ORDER BY fecha ASC
        ";

        $stmtExcepciones = $conexion->prepare($sqlExcepciones);

        $stmtExcepciones->execute([
            ':id_servicio' => $servicioReferenciaConfiguracion
        ]);

        $excepcionesActuales = $stmtExcepciones->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        error_log('Error consultando excepciones: ' . $e->getMessage());
        $excepcionesActuales = [];
    }
}


/* =========================================================
   DATOS GENERALES
   ========================================================= */

$diasSemana = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo'
];

$modoTodosServicios = (
    $filtroDepartamento > 0 &&
    $filtroServicio <= 0
);


$urlFiltros = http_build_query(
    array_filter(
        [
            'departamento' => $filtroDepartamento > 0 ? $filtroDepartamento : null,
            'servicio' => $filtroServicio > 0 ? $filtroServicio : null
        ],
        function ($valor) {
            return $valor !== null;
        }
    )
);

?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Configuración de Horarios | CESFAM</title>

<link rel="stylesheet" href="../css/compartidos/general.css">
<link rel="stylesheet" href="../css/compartidos/navbar_funcionario.css">
<link rel="stylesheet" href="../css/compartidos/sidebar_funcionario.css">
<link rel="stylesheet" href="../css/compartidos/footer_funcionario.css">
<link rel="stylesheet" href="../css/funcionarios/horarios.css">

<script src="../js/horarios.js" defer></script>

</head>

<body>

<?php require_once '../componentes/navbar_funcionario.php'; ?>

<div class="admin-layout">

<?php require_once '../componentes/sidebar_funcionario.php'; ?>

<main class="admin-content">

    <section class="schedule-header">

        <div>

            <span class="page-kicker">
                Gestión de Disponibilidad
            </span>

            <h1>
                Configuración de Horarios y Excepciones
            </h1>

            <p>
                Ajusta la plantilla semanal predeterminada,
                incluyendo pausa de almuerzo, o define fechas
                específicas como días feriados o con horarios especiales.
            </p>

        </div>

    </section>


    <?php if ($mensaje !== ''): ?>

        <div class="alert-box <?= $tipoMensaje === 'success' ? 'success' : 'error' ?>">

            <?= htmlspecialchars(
                $mensaje,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         FILTROS
         ===================================================== -->

    <form method="GET" action="horarios.php" class="schedule-filters" id="formFiltroServicio">

        <?php if ($rolFuncionario === 'superadmin'): ?>

            <div class="filter-group">

                <label for="schedule-department">
                    Selecciona Departamento
                </label>

                <select
                    id="schedule-department"
                    name="departamento"
                    onchange="this.form.submit()"
                >

                    <option value="0">
                        Todos los departamentos
                    </option>

                    <?php foreach ($departamentos as $departamento): ?>

                        <option
                            value="<?= (int) $departamento['id_departamento'] ?>"
                            <?= $filtroDepartamento === (int) $departamento['id_departamento'] ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $departamento['nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <?php if (esVerdadero($departamento['horarios_unificados'])): ?>
                                — Unificado
                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

        <?php endif; ?>


        <div class="filter-group">

            <label for="schedule-service">
                Selecciona Servicio
            </label>

            <select
                id="schedule-service"
                name="servicio"
                onchange="this.form.submit()"
            >

                <?php if (count($servicios) === 0): ?>

                    <option value="0">
                        No hay servicios disponibles
                    </option>

                <?php else: ?>

                    <?php if ($filtroDepartamento > 0): ?>

                        <option value="0" <?= $modoTodosServicios ? 'selected' : '' ?>>
                            Todos los servicios
                        </option>

                    <?php endif; ?>

                    <?php foreach ($servicios as $s): ?>

                        <?php
                        $idServicioActual = (int) $s['id_servicio'];

                        $esServicioUnificado = in_array(
                            $idServicioActual,
                            $serviciosUnificados,
                            true
                        );
                        ?>

                        <option
                            value="<?= $idServicioActual ?>"
                            <?= (!$modoTodosServicios && $filtroServicio === $idServicioActual) ? 'selected' : '' ?>
                        >

                            <?php if ($rolFuncionario === 'superadmin' && $filtroDepartamento <= 0): ?>

                                <?= htmlspecialchars(
                                    $s['nombre_departamento'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?> -

                            <?php endif; ?>

                            <?= htmlspecialchars(
                                $s['nombre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <?php if ($esServicioUnificado): ?>
                                — Servicio unificado
                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                <?php endif; ?>

            </select>

        </div>

    </form>


    <?php if ($filtroDepartamento > 0): ?>

        <section class="special-days-list-card" style="margin-bottom:24px;">

            <h3>
                🔗 Configuración de horarios del departamento
            </h3>

            <p style="margin-bottom:18px;">

                <?php if ($horariosUnificados): ?>

                    Actualmente todos los servicios de
                    <strong><?= htmlspecialchars($nombreDepartamentoSeleccionado, ENT_QUOTES, 'UTF-8') ?></strong>
                    utilizan el mismo horario.

                <?php elseif ($unificacionSelectiva): ?>

                    Actualmente hay servicios seleccionados
                    que comparten el mismo horario dentro de
                    <strong><?= htmlspecialchars($nombreDepartamentoSeleccionado, ENT_QUOTES, 'UTF-8') ?></strong>.

                <?php else: ?>

                    Actualmente cada servicio de
                    <strong><?= htmlspecialchars($nombreDepartamentoSeleccionado, ENT_QUOTES, 'UTF-8') ?></strong>
                    puede tener su propio horario.

                <?php endif; ?>

            </p>

            <form method="POST" action="horarios.php?<?= $urlFiltros ?>" id="formUnificacionServicios">

                <input type="hidden" name="accion" value="guardar_unificacion">

                <input
                    type="hidden"
                    name="id_departamento"
                    value="<?= $filtroDepartamento ?>"
                >

                <label class="lunch-checkbox-label" style="display:flex;align-items:center;gap:10px;">

                    <input
                        type="checkbox"
                        name="horarios_unificados"
                        value="1"
                        id="js-toggle-unificacion"
                        <?= ($horariosUnificados || $unificacionSelectiva) ? 'checked' : '' ?>
                    >

                    <strong>
                        Unificar horarios de servicios seleccionados
                    </strong>

                </label>

                <div
                    id="js-panel-servicios-unificacion"
                    style="margin-top:18px;<?= ($horariosUnificados || $unificacionSelectiva) ? '' : 'display:none;' ?>"
                >

                    <p style="margin:0 0 15px;">

                        Selecciona <strong>2 o más servicios</strong>
                        que deban compartir el mismo horario semanal
                        y las mismas excepciones.

                    </p>

                    <div class="services-select-box">

                        <div class="check-all-wrapper">

                            <label>

                                <input
                                    type="checkbox"
                                    id="js-check-all-services"
                                    <?= (
                                        count($serviciosSeleccionadosUI) === count($servicios) &&
                                        count($servicios) > 0
                                    ) ? 'checked' : '' ?>
                                >

                                <strong>
                                    Seleccionar / Deseleccionar Todos los Servicios
                                </strong>

                            </label>

                        </div>

                        <div class="services-checkbox-grid">

                            <?php foreach ($servicios as $s): ?>

                                <?php
                                $idServicioCheckbox = (int) $s['id_servicio'];

                                $estaSeleccionado = in_array(
                                    $idServicioCheckbox,
                                    $serviciosSeleccionadosUI,
                                    true
                                );
                                ?>

                                <label class="service-checkbox-item">

                                    <input
                                        type="checkbox"
                                        name="servicios_seleccionados[]"
                                        class="js-service-checkbox"
                                        value="<?= $idServicioCheckbox ?>"
                                        <?= $estaSeleccionado ? 'checked' : '' ?>
                                    >

                                    <span>

                                        <strong>
                                            <?= htmlspecialchars(
                                                $s['nombre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                        <?php if ($rolFuncionario === 'superadmin' && $filtroDepartamento <= 0): ?>

                                            <small>
                                                (
                                                <?= htmlspecialchars(
                                                    $s['nombre_departamento'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                                )
                                            </small>

                                        <?php endif; ?>

                                        <?php if ($estaSeleccionado): ?>
                                            <small>Comparte horario</small>
                                        <?php endif; ?>

                                    </span>

                                </label>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <button
                        type="submit"
                        class="save-special-btn"
                        onclick="return validarUnificacionServicios();"
                    >
                        🔄 Guardar Configuración de Horarios
                    </button>

                </div>

                <?php if (!$horariosUnificados && !$unificacionSelectiva): ?>

                    <div class="alert-box" style="margin-top:18px;">

                        <strong>Horarios independientes</strong>

                        <br>

                        Cada servicio puede tener una configuración
                        semanal y excepciones diferentes.

                    </div>

                <?php endif; ?>

                <?php if ($horariosUnificados): ?>

                    <div class="alert-box success" style="margin-top:18px;">

                        <strong>Horario unificado del departamento</strong>

                        <br>

                        Todos los servicios comparten la misma
                        plantilla semanal y las mismas excepciones.

                    </div>

                <?php elseif ($unificacionSelectiva): ?>

                    <div class="alert-box success" style="margin-top:18px;">

                        <strong>Unificación selectiva activa</strong>

                        <br>

                        Los servicios seleccionados comparten la
                        misma plantilla semanal y las mismas excepciones.

                    </div>

                <?php endif; ?>

            </form>

        </section>

    <?php endif; ?>


    <?php if ($modoTodosServicios): ?>

        <div class="alert-box success">

            <strong>

                <?php if ($horariosUnificados): ?>
                    🔗 Horario unificado del departamento
                <?php elseif ($unificacionSelectiva): ?>
                    🔗 Configuración de servicios unificados
                <?php else: ?>
                    Configuración para todos los servicios
                <?php endif; ?>

            </strong>

            <br>

            <?php if ($horariosUnificados): ?>

                Los cambios que guardes en la plantilla semanal
                se aplicarán automáticamente a todos los servicios
                del departamento.

                <br>

                Las excepciones también se aplicarán a todos
                los servicios del departamento.

            <?php elseif ($unificacionSelectiva): ?>

                Para modificar el horario de un grupo unificado,
                selecciona uno de sus servicios en el selector superior.

                <br>

                Los cambios se aplicarán automáticamente a todos
                los servicios pertenecientes al grupo.

            <?php else: ?>

                Los cambios que guardes en la plantilla semanal
                se aplicarán a todos los servicios activos del
                departamento seleccionado.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         SECCIÓN 1: PLANTILLA SEMANAL
         ===================================================== -->

    <div class="section-title-bar">

        <h2>1. Plantilla Semanal Habitual</h2>

        <p>

            <?php if ($modoTodosServicios && $horariosUnificados): ?>

                Configura el horario semanal que se aplicará
                a todos los servicios del departamento.

            <?php elseif ($unificacionSelectiva && $filtroServicio > 0): ?>

                Configura el horario semanal del grupo de
                servicios unificados.

            <?php elseif ($modoTodosServicios): ?>

                Configura el horario semanal que se aplicará
                a todos los servicios del departamento.

            <?php else: ?>

                Configura la atención recurrente de Lunes a Domingo.
                Las horas marcadas en el almuerzo se omitirán
                automáticamente del público.

            <?php endif; ?>

        </p>

    </div>


    <section class="weekly-template-grid">

        <?php foreach ($diasSemana as $diaNum => $diaNombre): ?>

            <?php
            $conf = $configuracionesActuales[$diaNum] ?? null;

            $esActivo = $conf
                ? esVerdadero($conf['activo'])
                : ($diaNum <= 5);

            $horaInicio = $conf
                ? substr($conf['hora_inicio'], 0, 5)
                : '08:30';

            $horaFin = $conf
                ? substr($conf['hora_fin'], 0, 5)
                : ($diaNum === 5 ? '16:00' : '17:00');

            $intervalo = $conf
                ? (int) $conf['intervalo_minutos']
                : 30;

            $tieneAlmuerzo = $conf
                ? esVerdadero($conf['tiene_almuerzo'])
                : false;

            $almuerzoInicio = (
                $conf &&
                !empty($conf['almuerzo_inicio'])
            )
                ? substr($conf['almuerzo_inicio'], 0, 5)
                : '13:00';

            $almuerzoFin = (
                $conf &&
                !empty($conf['almuerzo_fin'])
            )
                ? substr($conf['almuerzo_fin'], 0, 5)
                : '14:00';
            ?>

            <div class="day-card <?= $esActivo ? 'active' : 'inactive' ?>">

                <div class="day-card-header">

                    <div class="day-title-group">

                        <h2><?= $diaNombre ?></h2>

                        <span class="day-status-badge">
                            <?= $esActivo ? 'Atención Activa' : 'Sin Atención' ?>
                        </span>

                    </div>

                    <label class="toggle-switch">

                        <input
                            type="checkbox"
                            class="js-toggle-day"
                            data-target="form-day-<?= $diaNum ?>"
                            <?= $esActivo ? 'checked' : '' ?>
                        >

                        <span class="slider"></span>

                    </label>

                </div>


                <form
                    method="POST"
                    action="horarios.php?<?= $urlFiltros ?>"
                    id="form-day-<?= $diaNum ?>"
                    class="day-card-form"
                >

                    <input type="hidden" name="accion" value="guardar_configuracion_dia">

                    <input
                        type="hidden"
                        name="id_servicio"
                        value="<?= $modoTodosServicios ? 0 : $filtroServicio ?>"
                    >

                    <?php if ($modoTodosServicios): ?>

                        <input type="hidden" name="todos_servicios" value="1">

                    <?php endif; ?>

                    <input type="hidden" name="dia_semana" value="<?= $diaNum ?>">

                    <input
                        type="hidden"
                        name="activo"
                        class="js-input-activo"
                        value="<?= $esActivo ? '1' : '0' ?>"
                    >


                    <div class="form-row-2">

                        <div class="filter-group">

                            <label>Hora Apertura</label>

                            <input
                                type="time"
                                name="hora_inicio"
                                value="<?= $horaInicio ?>"
                                <?= !$esActivo ? 'disabled' : '' ?>
                                required
                            >

                        </div>

                        <div class="filter-group">

                            <label>Hora Cierre</label>

                            <input
                                type="time"
                                name="hora_fin"
                                value="<?= $horaFin ?>"
                                <?= !$esActivo ? 'disabled' : '' ?>
                                required
                            >

                        </div>

                    </div>


                    <div class="lunch-box">

                        <label class="lunch-checkbox-label">

                            <input
                                type="checkbox"
                                name="tiene_almuerzo"
                                value="1"
                                class="js-toggle-lunch"
                                <?= $tieneAlmuerzo ? 'checked' : '' ?>
                                <?= !$esActivo ? 'disabled' : '' ?>
                            >

                            <strong>
                                Pausa de Almuerzo / Colación
                            </strong>

                        </label>


                        <div class="form-row-2 lunch-inputs <?= !$tieneAlmuerzo ? 'hidden-lunch' : '' ?>">

                            <div class="filter-group">

                                <label>Inicio Almuerzo</label>

                                <input
                                    type="time"
                                    name="almuerzo_inicio"
                                    value="<?= $almuerzoInicio ?>"
                                    <?= (!$esActivo || !$tieneAlmuerzo) ? 'disabled' : '' ?>
                                >

                            </div>

                            <div class="filter-group">

                                <label>Fin Almuerzo</label>

                                <input
                                    type="time"
                                    name="almuerzo_fin"
                                    value="<?= $almuerzoFin ?>"
                                    <?= (!$esActivo || !$tieneAlmuerzo) ? 'disabled' : '' ?>
                                >

                            </div>

                        </div>

                    </div>


                    <div class="filter-group form-group-mt">

                        <label>Duración Bloque Cita</label>

                        <select
                            name="intervalo_minutos"
                            <?= !$esActivo ? 'disabled' : '' ?>
                        >

                            <?php foreach ([15, 20, 30, 45, 60] as $valorIntervalo): ?>

                                <option
                                    value="<?= $valorIntervalo ?>"
                                    <?= $intervalo === $valorIntervalo ? 'selected' : '' ?>
                                >
                                    <?= $valorIntervalo === 60
                                        ? '60 minutos (1 hora)'
                                        : $valorIntervalo . ' minutos'
                                    ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <button type="submit" class="save-day-btn">

                        <?php if ($modoTodosServicios): ?>

                            Aplicar <?= $diaNombre ?> a todos

                        <?php elseif ($unificacionSelectiva): ?>

                            Aplicar <?= $diaNombre ?> al grupo

                        <?php else: ?>

                            Guardar <?= $diaNombre ?>

                        <?php endif; ?>

                    </button>

                </form>

            </div>

        <?php endforeach; ?>

    </section>


    <!-- =====================================================
         SECCIÓN 2: EXCEPCIONES
         ===================================================== -->

    <div class="section-title-bar section-mt">

        <h2>2. Días Especiales y Excepciones</h2>

        <p>

            <?php if ($horariosUnificados): ?>

                Las excepciones se aplicarán a todos los servicios
                del departamento.

            <?php elseif ($unificacionSelectiva && $filtroServicio > 0): ?>

                Las excepciones se aplicarán a todos los servicios
                pertenecientes al grupo unificado.

            <?php elseif ($modoTodosServicios): ?>

                Selecciona un servicio específico para administrar
                sus fechas especiales.

            <?php else: ?>

                Añade días festivos (cierre total) o jornadas
                especiales en fechas puntuales que reemplazan
                la plantilla semanal.

            <?php endif; ?>

        </p>

    </div>


    <?php if ($modoTodosServicios && !$horariosUnificados): ?>

        <div class="special-days-list-card">

            <div class="empty-state">

                <strong>Todos los servicios seleccionados</strong>

                <br><br>

                La configuración semanal se está aplicando
                de forma masiva al departamento seleccionado.

                <br>

                Para agregar o eliminar una excepción,
                selecciona un servicio específico en el
                selector superior.

            </div>

        </div>

    <?php else: ?>

        <div class="special-days-layout">


            <!-- FORMULARIO AGREGAR EXCEPCIÓN -->

            <form
                method="POST"
                action="horarios.php?<?= $urlFiltros ?>"
                class="special-day-card-form"
            >

                <input type="hidden" name="accion" value="guardar_excepcion">

                <input type="hidden" name="id_servicio" value="<?= $filtroServicio ?>">

                <h3>➕ Añadir Excepción por Fecha</h3>


                <?php if ($horariosUnificados): ?>

                    <div class="alert-box success" style="margin-bottom:18px;">

                        Esta excepción se aplicará automáticamente
                        a todos los servicios del departamento.

                    </div>

                <?php elseif ($unificacionSelectiva): ?>

                    <div class="alert-box success" style="margin-bottom:18px;">

                        Esta excepción se aplicará automáticamente
                        a todos los servicios pertenecientes al
                        grupo unificado.

                    </div>

                <?php endif; ?>


                <div class="filter-group">

                    <label for="fecha_excepcion">Selecciona Fecha (Calendario)</label>

                    <input
                        type="date"
                        id="fecha_excepcion"
                        name="fecha_excepcion"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <div class="filter-group form-group-mt">

                    <label for="tipo_excepcion">Tipo de Excepción</label>

                    <select id="tipo_excepcion" name="tipo_excepcion" class="js-tipo-excepcion">

                        <option value="bloqueo">
                            Día No Laborable (Cierre Total / Feriado)
                        </option>

                        <option value="horario_especial">
                            Horario Especial en esta Fecha
                        </option>

                    </select>

                </div>


                <div class="form-row-2 form-group-mt js-horas-especiales hidden-lunch">

                    <div class="filter-group">

                        <label>Hora Inicio</label>

                        <input
                            type="time"
                            name="hora_inicio_esp"
                            value="09:00"
                            disabled
                        >

                    </div>

                    <div class="filter-group">

                        <label>Hora Fin</label>

                        <input
                            type="time"
                            name="hora_fin_esp"
                            value="13:00"
                            disabled
                        >

                    </div>

                </div>


                <div class="filter-group form-group-mt">

                    <label for="motivo_excepcion">Motivo / Observación</label>

                    <input
                        type="text"
                        id="motivo_excepcion"
                        name="motivo_excepcion"
                        placeholder="Ej: Feriado Comunal, Capacitación..."
                        required
                    >

                </div>


                <button type="submit" class="save-special-btn">
                    Guardar Excepción
                </button>

            </form>


            <!-- LISTA DE EXCEPCIONES -->

            <div class="special-days-list-card">

                <h3>📅 Excepciones Programadas</h3>

                <?php if (count($excepcionesActuales) === 0): ?>

                    <div class="empty-state">

                        No hay excepciones ni días especiales agendados

                        <?php if ($horariosUnificados): ?>
                            para este departamento.
                        <?php elseif ($unificacionSelectiva): ?>
                            para el grupo de servicios.
                        <?php else: ?>
                            para este servicio.
                        <?php endif; ?>

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="exceptions-table">

                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Detalle</th>
                                    <th>Motivo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($excepcionesActuales as $ex): ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?= date('d/m/Y', strtotime($ex['fecha'])) ?>
                                            </strong>
                                        </td>

                                        <td>

                                            <?php if (esVerdadero($ex['es_bloqueo_total'])): ?>

                                                <span class="badge badge-blocked">
                                                    Cierre Total
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-special">
                                                    Horario Especial
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if (esVerdadero($ex['es_bloqueo_total'])): ?>

                                                <span>Sin atención</span>

                                            <?php else: ?>

                                                <span>
                                                    <?= substr($ex['hora_inicio'], 0, 5) ?>
                                                    a
                                                    <?= substr($ex['hora_fin'], 0, 5) ?>
                                                    hrs
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $ex['motivo'] ?? '-',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </td>

                                        <td>

                                            <form
                                                method="POST"
                                                action="horarios.php?<?= $urlFiltros ?>"
                                                onsubmit="return confirm('¿Eliminar esta excepción?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="accion"
                                                    value="eliminar_excepcion"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id_excepcion"
                                                    value="<?= (int) $ex['id_excepcion'] ?>"
                                                >

                                                <button type="submit" class="btn-delete">
                                                    🗑️
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

</main>

</div>

<?php require_once '../componentes/footer_funcionario.php'; ?>


<script>
function validarUnificacionServicios() {

    const checkbox = document.getElementById('js-toggle-unificacion');

    const servicios = document.querySelectorAll(
        '.js-service-checkbox:checked'
    );

    if (!checkbox || !checkbox.checked) {
        return confirm(
            '¿Confirmas desactivar la unificación de horarios? Los servicios conservarán sus configuraciones individuales.'
        );
    }

    if (servicios.length < 2) {
        alert(
            'Debes seleccionar al menos 2 servicios para unificar sus horarios.'
        );
        return false;
    }

    return confirm(
        '¿Confirmas guardar la unificación de los servicios seleccionados? El horario del primer servicio seleccionado se copiará a los demás.'
    );
}


/* =========================================================
   MOSTRAR / OCULTAR SERVICIOS
   ========================================================= */

document.addEventListener('DOMContentLoaded', function () {

    const toggle = document.getElementById('js-toggle-unificacion');
    const panel = document.getElementById('js-panel-servicios-unificacion');
    const checkAll = document.getElementById('js-check-all-services');
    const checks = document.querySelectorAll('.js-service-checkbox');

    if (toggle && panel) {
        toggle.addEventListener('change', function () {
            panel.style.display = this.checked ? '' : 'none';
        });
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            checks.forEach(function (checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }

    checks.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {

            if (!checkAll) {
                return;
            }

            const total = checks.length;

            const seleccionados = document.querySelectorAll(
                '.js-service-checkbox:checked'
            ).length;

            checkAll.checked = (
                total > 0 &&
                total === seleccionados
            );

            checkAll.indeterminate = (
                seleccionados > 0 &&
                seleccionados < total
            );
        });
    });


    /*
     * ---------------------------------------------------------
     * PAUSA DE ALMUERZO
     * ---------------------------------------------------------
     */

    document.querySelectorAll('.js-toggle-lunch').forEach(function (toggleLunch) {

        function actualizarAlmuerzo() {

            const lunchBox = toggleLunch.closest('.lunch-box');

            if (!lunchBox) {
                return;
            }

            const contenedor = lunchBox.querySelector('.lunch-inputs');

            if (!contenedor) {
                return;
            }

            const inputs = contenedor.querySelectorAll('input');

            const formulario = toggleLunch.closest('.day-card-form');

            const toggleDia = formulario
                ? document.querySelector(
                    '.js-toggle-day[data-target="' + formulario.id + '"]'
                )
                : null;

            const diaActivo = toggleDia
                ? toggleDia.checked
                : !toggleLunch.disabled;

            if (toggleLunch.checked && diaActivo) {

                contenedor.classList.remove('hidden-lunch');

                inputs.forEach(function (input) {
                    input.disabled = false;
                });

            } else {

                contenedor.classList.add('hidden-lunch');

                inputs.forEach(function (input) {
                    input.disabled = true;
                });
            }
        }

        toggleLunch.addEventListener('change', actualizarAlmuerzo);
        actualizarAlmuerzo();
    });


    /*
     * ---------------------------------------------------------
     * ACTIVAR / DESACTIVAR DÍAS
     * ---------------------------------------------------------
     */

    document.querySelectorAll('.js-toggle-day').forEach(function (toggleDia) {

        toggleDia.addEventListener('change', function () {

            const formId = toggleDia.getAttribute('data-target');

            const form = document.getElementById(formId);

            if (!form) {
                return;
            }

            const inputActivo = form.querySelector('.js-input-activo');

            const dayCard = form.closest('.day-card');

            const statusBadge = dayCard
                ? dayCard.querySelector('.day-status-badge')
                : null;

            const inputs = form.querySelectorAll(
                'input:not(.js-input-activo):not([type="hidden"]), select, button'
            );

            if (toggleDia.checked) {

                if (inputActivo) {
                    inputActivo.value = '1';
                }

                if (dayCard) {
                    dayCard.classList.remove('inactive');
                    dayCard.classList.add('active');
                }

                if (statusBadge) {
                    statusBadge.textContent = 'Atención Activa';
                }

                inputs.forEach(function (input) {
                    input.disabled = false;
                });

            } else {

                if (inputActivo) {
                    inputActivo.value = '0';
                }

                if (dayCard) {
                    dayCard.classList.remove('active');
                    dayCard.classList.add('inactive');
                }

                if (statusBadge) {
                    statusBadge.textContent = 'Sin Atención';
                }

                inputs.forEach(function (input) {
                    input.disabled = true;
                });
            }

            /*
             * Volver a evaluar el almuerzo para no habilitar
             * sus horas cuando la casilla está desmarcada.
             */
            const toggleLunch = form.querySelector('.js-toggle-lunch');

            if (toggleLunch) {
                toggleLunch.dispatchEvent(new Event('change'));
            }
        });
    });


    /*
     * ---------------------------------------------------------
     * TIPO DE EXCEPCIÓN
     * ---------------------------------------------------------
     */

    const tipoExcepcionSelect = document.querySelector('.js-tipo-excepcion');

    if (tipoExcepcionSelect) {

        const horasEspecialesContainer = document.querySelector(
            '.js-horas-especiales'
        );

        const inputsEspeciales = horasEspecialesContainer
            ? horasEspecialesContainer.querySelectorAll('input')
            : [];

        const toggleHorasEspeciales = function () {

            if (tipoExcepcionSelect.value === 'horario_especial') {

                if (horasEspecialesContainer) {
                    horasEspecialesContainer.classList.remove('hidden-lunch');
                }

                inputsEspeciales.forEach(function (input) {
                    input.disabled = false;
                });

            } else {

                if (horasEspecialesContainer) {
                    horasEspecialesContainer.classList.add('hidden-lunch');
                }

                inputsEspeciales.forEach(function (input) {
                    input.disabled = true;
                });
            }
        };

        tipoExcepcionSelect.addEventListener(
            'change',
            toggleHorasEspeciales
        );

        toggleHorasEspeciales();
    }

});
</script>

</body>
</html>