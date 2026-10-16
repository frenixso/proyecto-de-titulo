<?php

header(
    'Content-Type: application/json; charset=utf-8'
);

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';

date_default_timezone_set('America/Santiago');


// =========================================================
// RESPUESTAS
// =========================================================

function responder(
    bool $ok,
    string $mensaje,
    array $datos = [],
    int $codigo = 200
) {

    http_response_code($codigo);

    echo json_encode(
        array_merge(
            [
                'ok' => $ok,
                'mensaje' => $mensaje
            ],
            $datos
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// =========================================================
// VALIDAR FECHA
// =========================================================

function validarFecha(
    string $fecha
): bool {

    $objeto =
        DateTime::createFromFormat(
            'Y-m-d',
            $fecha
        );

    return
        $objeto !== false &&
        $objeto->format('Y-m-d') === $fecha;
}


// =========================================================
// VALIDAR HORA
// =========================================================

function validarHora(
    string $hora
): bool {

    return preg_match(
        '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
        $hora
    ) === 1;
}


// =========================================================
// BOOLEAN POSTGRESQL
// =========================================================

function esVerdadero($valor): bool
{
    return
        $valor === true ||
        $valor === 't' ||
        $valor === '1' ||
        $valor === 1;
}


// =========================================================
// VALIDAR RUT CHILENO
// =========================================================

function rutEsValido(
    string $rut
): bool {

    /*
     * ---------------------------------------------------------
     * LIMPIAR RUT
     * ---------------------------------------------------------
     *
     * Se permiten:
     * - números
     * - K
     * - k
     * - puntos
     * - guion
     * - espacios
     *
     * La K se normaliza a mayúscula.
     *
     * ---------------------------------------------------------
     */

    $rutLimpio =
        strtoupper(
            preg_replace(
                '/[^0-9Kk]/',
                '',
                $rut
            )
        );


    /*
     * ---------------------------------------------------------
     * FORMATO
     * ---------------------------------------------------------
     *
     * Cuerpo:
     * 1 a 8 números
     *
     * Dígito verificador:
     * 0-9 o K
     *
     * Ejemplos:
     *
     * 123456785
     * 12345678K
     *
     * ---------------------------------------------------------
     */

    if (
        !preg_match(
            '/^[0-9]{1,8}[0-9K]$/',
            $rutLimpio
        )
    ) {

        return false;
    }


    /*
     * ---------------------------------------------------------
     * SEPARAR CUERPO Y DÍGITO VERIFICADOR
     * ---------------------------------------------------------
     */

    $cuerpo =
        substr(
            $rutLimpio,
            0,
            -1
        );

    $dvIngresado =
        substr(
            $rutLimpio,
            -1
        );


    /*
     * ---------------------------------------------------------
     * CÁLCULO DEL DÍGITO VERIFICADOR
     * ---------------------------------------------------------
     */

    $suma = 0;

    $multiplicador = 2;


    for (
        $i = strlen($cuerpo) - 1;
        $i >= 0;
        $i--
    ) {

        $suma +=
            intval(
                $cuerpo[$i]
            )
            *
            $multiplicador;


        $multiplicador++;


        if (
            $multiplicador > 7
        ) {

            $multiplicador = 2;
        }
    }


    $resto =
        $suma % 11;


    $resultado =
        11 - $resto;


    /*
     * ---------------------------------------------------------
     * OBTENER DÍGITO VERIFICADOR CALCULADO
     * ---------------------------------------------------------
     */

    if (
        $resultado === 11
    ) {

        $dvCalculado = '0';

    } elseif (
        $resultado === 10
    ) {

        $dvCalculado = 'K';

    } else {

        $dvCalculado =
            (string) $resultado;
    }


    /*
     * ---------------------------------------------------------
     * COMPARAR
     * ---------------------------------------------------------
     */

    return
        $dvIngresado ===
        $dvCalculado;
}


// =========================================================
// OBTENER INFORMACIÓN DEL SERVICIO
// =========================================================

function obtenerServicio(
    PDO $conexion,
    int $idServicio
): ?array {

    $stmt =
        $conexion->prepare(
            "
            SELECT
                s.id_servicio,
                s.nombre,
                s.id_departamento,
                s.activo AS servicio_activo,

                d.nombre AS nombre_departamento,
                d.activo AS departamento_activo,
                d.horarios_unificados

            FROM servicios s

            INNER JOIN departamentos d
                ON d.id_departamento =
                    s.id_departamento

            WHERE s.id_servicio =
                :id_servicio

            LIMIT 1
            "
        );

    $stmt->execute([
        ':id_servicio' =>
            $idServicio
    ]);

    $resultado =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $resultado ?: null;
}


// =========================================================
// OBTENER SERVICIOS DEL DEPARTAMENTO
// =========================================================

function obtenerServiciosDepartamento(
    PDO $conexion,
    int $idDepartamento
): array {

    $stmt =
        $conexion->prepare(
            "
            SELECT
                id_servicio

            FROM servicios

            WHERE id_departamento =
                :id_departamento

              AND activo = TRUE

            ORDER BY
                id_servicio
            "
        );

    $stmt->execute([
        ':id_departamento' =>
            $idDepartamento
    ]);

    $ids = [];

    while (
        $fila =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $ids[] =
            (int)
            $fila['id_servicio'];
    }

    return $ids;
}


// =========================================================
// SERVICIOS QUE COMPARTEN DISPONIBILIDAD
// =========================================================

function obtenerServiciosDisponibilidad(
    PDO $conexion,
    array $servicio
): array {

    $idServicio =
        (int)
        $servicio['id_servicio'];

    $idDepartamento =
        (int)
        $servicio['id_departamento'];

    $unificado =
        esVerdadero(
            $servicio['horarios_unificados']
        );

    if ($unificado) {

        $ids =
            obtenerServiciosDepartamento(
                $conexion,
                $idDepartamento
            );

        if (
            !in_array(
                $idServicio,
                $ids,
                true
            )
        ) {

            $ids[] =
                $idServicio;
        }

        return $ids;
    }


    /*
     * ---------------------------------------------------------
     * CONSULTA A GRUPOS DE UNIFICACIÓN DINÁMICOS
     * ---------------------------------------------------------
     */

    $stmtGrupo =
        $conexion->prepare(
            "
            SELECT id_grupo
            FROM unificacion_horarios_servicios
            WHERE id_servicio = :id_servicio
            LIMIT 1
            "
        );

    $stmtGrupo->execute([
        ':id_servicio' => $idServicio
    ]);

    $grupo =
        $stmtGrupo->fetch(
            PDO::FETCH_ASSOC
        );

    if (!$grupo) {

        return [
            $idServicio
        ];
    }

    $idGrupo =
        (int)
        $grupo['id_grupo'];

    $stmtServiciosGrupo =
        $conexion->prepare(
            "
            SELECT u.id_servicio
            FROM unificacion_horarios_servicios u
            INNER JOIN servicios s
                ON s.id_servicio = u.id_servicio
            WHERE u.id_grupo = :id_grupo
              AND s.activo = TRUE
            ORDER BY u.id_servicio
            "
        );

    $stmtServiciosGrupo->execute([
        ':id_grupo' => $idGrupo
    ]);

    $ids = [
        $idServicio
    ];

    while (
        $fila =
            $stmtServiciosGrupo->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $id =
            (int)
            $fila['id_servicio'];

        if (
            !in_array(
                $id,
                $ids,
                true
            )
        ) {

            $ids[] =
                $id;
        }
    }

    return $ids;
}


// =========================================================
// OBTENER SERVICIO DE REFERENCIA PARA HORARIOS
// =========================================================

function obtenerServicioReferencia(
    PDO $conexion,
    array $servicio
): int {

    $idServicio =
        (int)
        $servicio['id_servicio'];

    if (
        !esVerdadero(
            $servicio['horarios_unificados']
        )
    ) {

        return $idServicio;
    }


    $idDepartamento =
        (int)
        $servicio['id_departamento'];


    $stmt =
        $conexion->prepare(
            "
            SELECT
                s.id_servicio

            FROM servicios s

            INNER JOIN configuracion_horarios ch
                ON ch.id_servicio =
                    s.id_servicio

            WHERE s.id_departamento =
                :id_departamento

              AND s.activo = TRUE

            ORDER BY
                s.id_servicio

            LIMIT 1
            "
        );

    $stmt->execute([
        ':id_departamento' =>
            $idDepartamento
    ]);


    $idReferencia =
        $stmt->fetchColumn();


    return
        $idReferencia !== false
            ? (int) $idReferencia
            : $idServicio;
}


// =========================================================
// OBTENER CONFIGURACIÓN DE HORARIO
// =========================================================

function obtenerConfiguracionHorario(
    PDO $conexion,
    int $idServicio,
    int $diaSemana
): ?array {

    $stmt =
        $conexion->prepare(
            "
            SELECT
                hora_inicio,
                hora_fin,
                intervalo_minutos,
                activo,
                tiene_almuerzo,
                almuerzo_inicio,
                almuerzo_fin

            FROM configuracion_horarios

            WHERE id_servicio =
                :id_servicio

              AND dia_semana =
                :dia_semana

            LIMIT 1
            "
        );

    $stmt->execute([
        ':id_servicio' =>
            $idServicio,

        ':dia_semana' =>
            $diaSemana
    ]);

    $config =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $config ?: null;
}


// =========================================================
// OBTENER EXCEPCIÓN
// =========================================================

function obtenerExcepcion(
    PDO $conexion,
    int $idServicio,
    string $fecha
): ?array {

    $stmt =
        $conexion->prepare(
            "
            SELECT
                es_bloqueo_total,
                hora_inicio,
                hora_fin,
                motivo

            FROM excepciones_horarios

            WHERE id_servicio =
                :id_servicio

              AND fecha =
                :fecha

            LIMIT 1
            "
        );

    $stmt->execute([
        ':id_servicio' =>
            $idServicio,

        ':fecha' =>
            $fecha
    ]);

    $excepcion =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    return
        $excepcion ?: null;
}


// =========================================================
// BLOQUEO TRANSACCIONAL PARA DISPONIBILIDAD UNIFICADA
// =========================================================

function bloquearDisponibilidad(
    PDO $conexion,
    array $servicio,
    string $fecha,
    string $hora
): void {

    $idServicio =
        (int)
        $servicio['id_servicio'];

    $idDepartamento =
        (int)
        $servicio['id_departamento'];

    if (
        esVerdadero(
            $servicio['horarios_unificados']
        )
    ) {

        $clave =
            'departamento-unificado|' .
            $idDepartamento .
            '|' .
            $fecha .
            '|' .
            $hora;

    } else {

        $stmtGrupo =
            $conexion->prepare(
                "
                SELECT id_grupo
                FROM unificacion_horarios_servicios
                WHERE id_servicio = :id_servicio
                LIMIT 1
                "
            );

        $stmtGrupo->execute([
            ':id_servicio' =>
                $idServicio
        ]);

        $grupo =
            $stmtGrupo->fetch(
                PDO::FETCH_ASSOC
            );

        if ($grupo) {

            $clave =
                'grupo-unificado|' .
                $grupo['id_grupo'] .
                '|' .
                $fecha .
                '|' .
                $hora;

        } else {

            $clave =
                'servicio-individual|' .
                $idServicio .
                '|' .
                $fecha .
                '|' .
                $hora;
        }
    }

    $stmt =
        $conexion->prepare(
            "
            SELECT pg_advisory_xact_lock(
                hashtext(:clave)
            )
            "
        );

    $stmt->execute([
        ':clave' =>
            $clave
    ]);
}


// =========================================================
// OBTENER HORAS OCUPADAS
// =========================================================

function obtenerHorasOcupadas(
    PDO $conexion,
    array $idsServicios,
    string $fecha
): array {

    if (
        empty($idsServicios)
    ) {

        return [];
    }


    $placeholders = [];
    $parametros = [];


    foreach (
        $idsServicios
        as $indice =>
        $idServicio
    ) {

        $param =
            ':servicio_' .
            $indice;

        $placeholders[] =
            $param;

        $parametros[$param] =
            (int)
            $idServicio;
    }


    $sql = "

        SELECT DISTINCT

            LEFT(
                h.hora::text,
                5
            ) AS hora

        FROM horarios h

        LEFT JOIN reservas r
            ON r.id_horario =
                h.id_horario

        WHERE h.id_servicio IN (
            " .
            implode(
                ', ',
                $placeholders
            ) .
            "
        )

          AND h.fecha =
              :fecha

          AND (

                h.estado =
                    'bloqueado'

                OR

                LOWER(
                    COALESCE(
                        r.estado,
                        ''
                    )
                ) IN (
                    'confirmada',
                    'reagendada'
                )

          )

    ";


    $stmt =
        $conexion->prepare($sql);


    foreach (
        $parametros
        as $parametro =>
        $valor
    ) {

        $stmt->bindValue(
            $parametro,
            $valor,
            PDO::PARAM_INT
        );
    }


    $stmt->bindValue(
        ':fecha',
        $fecha,
        PDO::PARAM_STR
    );


    $stmt->execute();


    $ocupadas = [];


    while (
        $fila =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )
    ) {

        $ocupadas[] =
            substr(
                $fila['hora'],
                0,
                5
            );
    }


    return $ocupadas;
}


// =========================================================
// ACCIÓN
// =========================================================

$accion =
    $_POST['accion'] ?? '';


$accionesPermitidas = [
    'horarios',
    'obtener_reserva',
    'crear',
    'confirmar',
    'reagendar',
    'cancelar'
];


if (
    !in_array(
        $accion,
        $accionesPermitidas,
        true
    )
) {

    responder(
        false,
        'Acción no válida.',
        [],
        400
    );
}


// =========================================================
// OBTENER RESERVA
// =========================================================

if (
    $accion === 'obtener_reserva'
) {

    $idReserva =
        isset($_POST['id_reserva'])
            ? (int) $_POST['id_reserva']
            : 0;


    if (
        $idReserva <= 0
    ) {

        responder(
            false,
            'Reserva no válida.',
            [],
            400
        );
    }


    try {

        $stmt =
            $conexion->prepare(
                "
                SELECT
                    r.id_reserva,
                    r.id_horario,
                    r.id_servicio,
                    r.nombre,
                    r.rut,
                    r.tipo_identificacion,
                    r.telefono,
                    r.correo,
                    r.estado,
                    r.fecha_reserva,
                    r.fecha_actualizacion,

                    s.nombre AS servicio_nombre,

                    h.fecha AS fecha_horario,
                    h.hora AS hora_horario,
                    h.estado AS estado_horario

                FROM reservas r

                INNER JOIN horarios h
                    ON h.id_horario =
                        r.id_horario

                INNER JOIN servicios s
                    ON s.id_servicio =
                        r.id_servicio

                WHERE r.id_reserva =
                    :id_reserva

                LIMIT 1
                "
            );


        $stmt->execute([
            ':id_reserva' =>
                $idReserva
        ]);


        $reserva =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$reserva) {

            responder(
                false,
                'La reserva no existe.',
                [],
                404
            );
        }


        $reserva['id_reserva'] =
            (int)
            $reserva['id_reserva'];

        $reserva['id_horario'] =
            (int)
            $reserva['id_horario'];

        $reserva['id_servicio'] =
            (int)
            $reserva['id_servicio'];

        $reserva['fecha_horario'] =
            $reserva['fecha_horario']
                ? (string)
                    $reserva['fecha_horario']
                : '';

        $reserva['hora_horario'] =
            $reserva['hora_horario']
                ? substr(
                    (string)
                    $reserva['hora_horario'],
                    0,
                    5
                )
                : '';

        $reserva['correo'] =
            $reserva['correo'] !== null
                ? (string)
                    $reserva['correo']
                : '';


        responder(
            true,
            'Reserva obtenida correctamente.',
            [
                'reserva' =>
                    $reserva
            ]
        );


    } catch (PDOException $e) {

        error_log(
            'Error obtener reserva: ' .
            $e->getMessage()
        );

        responder(
            false,
            'No fue posible obtener los datos de la reserva.',
            [],
            500
        );
    }
}


// =========================================================
// HORARIOS
// =========================================================

if (
    $accion === 'horarios'
) {

    $idServicio =
        isset($_POST['id_servicio'])
            ? (int) $_POST['id_servicio']
            : 0;

    $fecha =
        trim(
            $_POST['fecha'] ?? ''
        );


    if (
        $idServicio <= 0
    ) {

        responder(
            false,
            'Debe seleccionar un servicio.',
            [],
            400
        );
    }


    if (
        !validarFecha($fecha)
    ) {

        responder(
            false,
            'La fecha no es válida.',
            [],
            400
        );
    }


    try {

        $servicio =
            obtenerServicio(
                $conexion,
                $idServicio
            );


        if (
            !$servicio ||
            !esVerdadero(
                $servicio['servicio_activo']
            )
        ) {

            responder(
                false,
                'El servicio no existe o está desactivado.',
                [],
                404
            );
        }


        if (
            !esVerdadero(
                $servicio['departamento_activo']
            )
        ) {

            responder(
                false,
                'El departamento está desactivado.',
                [],
                400
            );
        }


        $fechaObjeto =
            new DateTime(
                $fecha
            );


        $diaSemana =
            (int)
            $fechaObjeto->format('N');


        if (
            $diaSemana >= 6
        ) {

            responder(
                true,
                'No hay atención durante el fin de semana.',
                [
                    'horarios' => []
                ]
            );
        }


        /*
         * -----------------------------------------------------
         * SERVICIO DE REFERENCIA
         * -----------------------------------------------------
         */

        $idServicioReferencia =
            obtenerServicioReferencia(
                $conexion,
                $servicio
            );


        $config =
            obtenerConfiguracionHorario(
                $conexion,
                $idServicioReferencia,
                $diaSemana
            );


        if (
            !$config ||
            !esVerdadero(
                $config['activo']
            )
        ) {

            responder(
                true,
                'No hay atención configurada para este día.',
                [
                    'horarios' => []
                ]
            );
        }


        $excepcion =
            obtenerExcepcion(
                $conexion,
                $idServicioReferencia,
                $fecha
            );


        if (
            $excepcion &&
            esVerdadero(
                $excepcion['es_bloqueo_total']
            )
        ) {

            responder(
                true,
                $excepcion['motivo']
                    ?: 'El día está bloqueado.',
                [
                    'horarios' => []
                ]
            );
        }


        $horaInicio =
            (
                $excepcion &&
                !empty(
                    $excepcion['hora_inicio']
                )
            )
                ? substr(
                    $excepcion['hora_inicio'],
                    0,
                    5
                )
                : substr(
                    $config['hora_inicio'],
                    0,
                    5
                );


        $horaFin =
            (
                $excepcion &&
                !empty(
                    $excepcion['hora_fin']
                )
            )
                ? substr(
                    $excepcion['hora_fin'],
                    0,
                    5
                )
                : substr(
                    $config['hora_fin'],
                    0,
                    5
                );


        $intervalo =
            (int)
            $config['intervalo_minutos'];


        if (
            !in_array(
                $intervalo,
                [15, 20, 30, 45, 60],
                true
            )
        ) {

            $intervalo =
                30;
        }


        /*
         * -----------------------------------------------------
         * SERVICIOS QUE COMPARTEN DISPONIBILIDAD
         * -----------------------------------------------------
         */

        $idsDisponibilidad =
            obtenerServiciosDisponibilidad(
                $conexion,
                $servicio
            );


        $horasOcupadas =
            obtenerHorasOcupadas(
                $conexion,
                $idsDisponibilidad,
                $fecha
            );


        /*
         * -----------------------------------------------------
         * HORARIOS EXISTENTES DEL SERVICIO SOLICITADO
         * -----------------------------------------------------
         */

        $stmtExistentes =
            $conexion->prepare(
                "
                SELECT
                    id_horario,
                    LEFT(hora::text,5) AS hora,
                    estado

                FROM horarios

                WHERE id_servicio =
                    :id_servicio

                  AND fecha =
                    :fecha
                "
            );


        $stmtExistentes->execute([
            ':id_servicio' =>
                $idServicio,

            ':fecha' =>
                $fecha
        ]);


        $existentes = [];


        while (
            $fila =
                $stmtExistentes->fetch(
                    PDO::FETCH_ASSOC
                )
        ) {

            $existentes[
                $fila['hora']
            ] = $fila;
        }


        /*
         * -----------------------------------------------------
         * HORA MÍNIMA PARA HOY
         * -----------------------------------------------------
         */

        $ahora =
            new DateTime(
                'now',
                new DateTimeZone(
                    'America/Santiago'
                )
            );


        $fechaHoy =
            $ahora->format(
                'Y-m-d'
            );


        $horaMinima =
            null;


        if (
            $fecha === $fechaHoy
        ) {

            $horaMinima =
                clone $ahora;

            $horaMinima->modify(
                '+1 hour'
            );

            $minutos =
                (int)
                $horaMinima->format('i');


            if (
                $minutos > 0 &&
                $minutos <= 30
            ) {

                $horaMinima->setTime(
                    (int)
                    $horaMinima->format('H'),
                    30,
                    0
                );

            } elseif (
                $minutos > 30
            ) {

                $horaMinima->modify(
                    '+1 hour'
                );

                $horaMinima->setTime(
                    (int)
                    $horaMinima->format('H'),
                    0,
                    0
                );
            }
        }


        /*
         * -----------------------------------------------------
         * GENERAR HORARIOS
         * -----------------------------------------------------
         */

        $cursor =
            new DateTime(
                $fecha .
                ' ' .
                $horaInicio
            );


        $fin =
            new DateTime(
                $fecha .
                ' ' .
                $horaFin
            );


        $horarios = [];


        while (
            $cursor < $fin
        ) {

            if (
                $horaMinima !== null &&
                $cursor < $horaMinima
            ) {

                $cursor->modify(
                    "+{$intervalo} minutes"
                );

                continue;
            }


            $hora =
                $cursor->format(
                    'H:i'
                );


            $esAlmuerzo =
                false;


            if (
                esVerdadero(
                    $config['tiene_almuerzo']
                ) &&
                !empty(
                    $config['almuerzo_inicio']
                ) &&
                !empty(
                    $config['almuerzo_fin']
                )
            ) {

                $inicioAlmuerzo =
                    new DateTime(
                        $fecha .
                        ' ' .
                        substr(
                            $config['almuerzo_inicio'],
                            0,
                            5
                        )
                    );


                $finAlmuerzo =
                    new DateTime(
                        $fecha .
                        ' ' .
                        substr(
                            $config['almuerzo_fin'],
                            0,
                            5
                        )
                    );


                if (
                    $cursor >= $inicioAlmuerzo &&
                    $cursor < $finAlmuerzo
                ) {

                    $esAlmuerzo =
                        true;
                }
            }


            if (
                !$esAlmuerzo
            ) {

                $registro =
                    $existentes[$hora]
                    ?? null;


                $ocupado =
                    in_array(
                        $hora,
                        $horasOcupadas,
                        true
                    );


                if (
                    $registro &&
                    strtolower(
                        trim(
                            (string)
                            $registro['estado']
                        )
                    ) === 'bloqueado'
                ) {

                    $ocupado =
                        true;
                }


                $horarios[] = [

                    'id_horario' =>
                        $registro
                            ? (int)
                                $registro[
                                    'id_horario'
                                ]
                            : null,

                    'hora' =>
                        $hora,

                    'disponible' =>
                        !$ocupado
                ];
            }


            $cursor->modify(
                "+{$intervalo} minutes"
            );
        }


        responder(
            true,
            'Horarios consultados.',
            [
                'horarios' =>
                    $horarios,

                'horarios_unificados' =>
                    esVerdadero(
                        $servicio[
                            'horarios_unificados'
                        ]
                    )
            ]
        );


    } catch (PDOException $e) {

        error_log(
            'Error horarios: ' .
            $e->getMessage()
        );

        responder(
            false,
            'No fue posible consultar los horarios.',
            [],
            500
        );
    }
}


// =========================================================
// CREAR RESERVA
// =========================================================

if (
    $accion === 'crear'
) {

    $nombre =
        trim(
            $_POST['nombre'] ?? ''
        );

    $rut =
        trim(
            $_POST['rut'] ?? ''
        );

    $telefono =
        trim(
            $_POST['telefono'] ?? ''
        );

    $correo =
        trim(
            $_POST['correo'] ?? ''
        );

    $idServicio =
        isset($_POST['id_servicio'])
            ? (int) $_POST['id_servicio']
            : 0;

    $fecha =
        trim(
            $_POST['fecha'] ?? ''
        );

    $hora =
        trim(
            $_POST['hora'] ?? ''
        );


    if (
        $nombre === '' ||
        $rut === '' ||
        $telefono === '' ||
        $idServicio <= 0 ||
        $fecha === '' ||
        $hora === ''
    ) {

        responder(
            false,
            'Complete todos los campos obligatorios.',
            [],
            400
        );
    }


    /*
     * -----------------------------------------------------
     * VALIDAR RUT
     * -----------------------------------------------------
     */

    if (
        !rutEsValido(
            $rut
        )
    ) {

        responder(
            false,
            'El RUT ingresado no es válido.',
            [],
            400
        );
    }


    if (
        !validarFecha($fecha) ||
        !validarHora($hora)
    ) {

        responder(
            false,
            'La fecha o el horario no son válidos.',
            [],
            400
        );
    }


    if (
        $correo !== '' &&
        !filter_var(
            $correo,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        responder(
            false,
            'El correo electrónico no es válido.',
            [],
            400
        );
    }


    try {

        $conexion->beginTransaction();


        $servicio =
            obtenerServicio(
                $conexion,
                $idServicio
            );


        if (
            !$servicio ||
            !esVerdadero(
                $servicio['servicio_activo']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El servicio seleccionado no existe o está desactivado.',
                [],
                404
            );
        }


        if (
            !esVerdadero(
                $servicio['departamento_activo']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El departamento está desactivado.',
                [],
                400
            );
        }


        $fechaObjeto =
            new DateTime(
                $fecha
            );


        $diaSemana =
            (int)
            $fechaObjeto->format('N');


        if (
            $diaSemana >= 6
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No se pueden crear reservas durante el fin de semana.',
                [],
                400
            );
        }


        /*
         * -----------------------------------------------------
         * BLOQUEAR CONCURRENCIA
         * -----------------------------------------------------
         */

        bloquearDisponibilidad(
            $conexion,
            $servicio,
            $fecha,
            $hora
        );


        /*
         * -----------------------------------------------------
         * VALIDAR CONFIGURACIÓN
         * -----------------------------------------------------
         */

        $idReferencia =
            obtenerServicioReferencia(
                $conexion,
                $servicio
            );


        $config =
            obtenerConfiguracionHorario(
                $conexion,
                $idReferencia,
                $diaSemana
            );


        if (
            !$config ||
            !esVerdadero(
                $config['activo']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No existe atención configurada para ese día.',
                [],
                409
            );
        }


        $excepcion =
            obtenerExcepcion(
                $conexion,
                $idReferencia,
                $fecha
            );


        if (
            $excepcion &&
            esVerdadero(
                $excepcion['es_bloqueo_total']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                $excepcion['motivo']
                    ?: 'El día está bloqueado.',
                [],
                409
            );
        }


        $horaInicio =
            (
                $excepcion &&
                !empty(
                    $excepcion['hora_inicio']
                )
            )
                ? substr(
                    $excepcion['hora_inicio'],
                    0,
                    5
                )
                : substr(
                    $config['hora_inicio'],
                    0,
                    5
                );


        $horaFin =
            (
                $excepcion &&
                !empty(
                    $excepcion['hora_fin']
                )
            )
                ? substr(
                    $excepcion['hora_fin'],
                    0,
                    5
                )
                : substr(
                    $config['hora_fin'],
                    0,
                    5
                );


        $intervalo =
            (int)
            $config[
                'intervalo_minutos'
            ];


        if (
            !in_array(
                $intervalo,
                [15, 20, 30, 45, 60],
                true
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La configuración de horarios no es válida.',
                [],
                500
            );
        }


        /*
         * -----------------------------------------------------
         * VALIDAR HORA
         * -----------------------------------------------------
         */

        [$inicioH, $inicioM] =
            array_map(
                'intval',
                explode(
                    ':',
                    $horaInicio
                )
            );


        [$finH, $finM] =
            array_map(
                'intval',
                explode(
                    ':',
                    $horaFin
                )
            );


        [$horaH, $horaM] =
            array_map(
                'intval',
                explode(
                    ':',
                    $hora
                )
            );


        $inicioMinutos =
            ($inicioH * 60) +
            $inicioM;

        $finMinutos =
            ($finH * 60) +
            $finM;

        $horaMinutos =
            ($horaH * 60) +
            $horaM;


        if (
            $horaMinutos < $inicioMinutos ||
            $horaMinutos >= $finMinutos
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El horario está fuera del horario de atención.',
                [],
                400
            );
        }


        if (
            (
                $horaMinutos -
                $inicioMinutos
            ) % $intervalo !== 0
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El horario no corresponde al intervalo configurado.',
                [],
                400
            );
        }


        /*
         * -----------------------------------------------------
         * ALMUERZO
         * -----------------------------------------------------
         */

        if (
            esVerdadero(
                $config['tiene_almuerzo']
            ) &&
            !empty(
                $config['almuerzo_inicio']
            ) &&
            !empty(
                $config['almuerzo_fin']
            )
        ) {

            [$aH, $aM] =
                array_map(
                    'intval',
                    explode(
                        ':',
                        substr(
                            $config['almuerzo_inicio'],
                            0,
                            5
                        )
                    )
                );


            [$bH, $bM] =
                array_map(
                    'intval',
                    explode(
                        ':',
                        substr(
                            $config['almuerzo_fin'],
                            0,
                            5
                        )
                    )
                );


            $almuerzoInicio =
                ($aH * 60) +
                $aM;

            $almuerzoFin =
                ($bH * 60) +
                $bM;


            if (
                $horaMinutos >= $almuerzoInicio &&
                $horaMinutos < $almuerzoFin
            ) {

                $conexion->rollBack();

                responder(
                    false,
                    'La hora seleccionada corresponde al horario de almuerzo.',
                    [],
                    400
                );
            }
        }


        /*
         * -----------------------------------------------------
         * NO PERMITIR HORAS PASADAS
         * -----------------------------------------------------
         */

        $ahora =
            new DateTime(
                'now',
                new DateTimeZone(
                    'America/Santiago'
                )
            );


        if (
            $fecha <
            $ahora->format('Y-m-d')
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No se pueden crear reservas para fechas pasadas.',
                [],
                400
            );
        }


        if (
            $fecha ===
            $ahora->format('Y-m-d')
            &&
            $hora <=
            $ahora->format('H:i')
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La hora seleccionada ya pasó.',
                [],
                400
            );
        }


        /*
         * -----------------------------------------------------
         * COMPROBAR OCUPACIÓN COMPARTIDA
         * -----------------------------------------------------
         */

        $idsDisponibilidad =
            obtenerServiciosDisponibilidad(
                $conexion,
                $servicio
            );


        $horasOcupadas =
            obtenerHorasOcupadas(
                $conexion,
                $idsDisponibilidad,
                $fecha
            );


        if (
            in_array(
                $hora,
                $horasOcupadas,
                true
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La hora seleccionada ya no está disponible.',
                [],
                409
            );
        }


        /*
         * -----------------------------------------------------
         * OBTENER / CREAR HORARIO
         * -----------------------------------------------------
         */

        $stmtHorario =
            $conexion->prepare(
                "
                SELECT
                    id_horario,
                    estado

                FROM horarios

                WHERE id_servicio =
                    :id_servicio

                  AND fecha =
                    :fecha

                  AND hora =
                    :hora

                FOR UPDATE
                "
            );


        $stmtHorario->execute([
            ':id_servicio' =>
                $idServicio,

            ':fecha' =>
                $fecha,

            ':hora' =>
                $hora
        ]);


        $horario =
            $stmtHorario->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            $horario
        ) {

            if (
                strtolower(
                    trim(
                        (string)
                        $horario['estado']
                    )
                ) ===
                'bloqueado'
            ) {

                $conexion->rollBack();

                responder(
                    false,
                    'El horario seleccionado está bloqueado.',
                    [],
                    409
                );
            }


            $idHorario =
                (int)
                $horario[
                    'id_horario'
                ];

        } else {

            $stmtCrearHorario =
                $conexion->prepare(
                    "
                    INSERT INTO horarios
                    (
                        id_servicio,
                        fecha,
                        hora,
                        estado,
                        observacion
                    )

                    VALUES
                    (
                        :id_servicio,
                        :fecha,
                        :hora,
                        'disponible',
                        NULL
                    )

                    RETURNING id_horario
                    "
                );


            $stmtCrearHorario->execute([
                ':id_servicio' =>
                    $idServicio,

                ':fecha' =>
                    $fecha,

                ':hora' =>
                    $hora
            ]);


            $idHorario =
                (int)
                $stmtCrearHorario
                    ->fetchColumn();
        }


        /*
         * -----------------------------------------------------
         * COMPROBAR RESERVA DIRECTA
         * -----------------------------------------------------
         */

        $stmtReserva =
            $conexion->prepare(
                "
                SELECT
                    id_reserva

                FROM reservas

                WHERE id_horario =
                    :id_horario

                  AND estado IN (
                      'confirmada',
                      'reagendada'
                  )

                FOR UPDATE
                "
            );


        $stmtReserva->execute([
            ':id_horario' =>
                $idHorario
        ]);


        if (
            $stmtReserva->fetch()
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La hora seleccionada ya no está disponible.',
                [],
                409
            );
        }


        /*
         * -----------------------------------------------------
         * INSERTAR RESERVA
         * -----------------------------------------------------
         */

        $stmtInsert =
            $conexion->prepare(
                "
                INSERT INTO reservas
                (
                    id_horario,
                    id_servicio,
                    nombre,
                    rut,
                    correo,
                    telefono,
                    estado,
                    fecha_reserva,
                    fecha_actualizacion,
                    tipo_identificacion
                )

                VALUES
                (
                    :id_horario,
                    :id_servicio,
                    :nombre,
                    :rut,
                    :correo,
                    :telefono,
                    'confirmada',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP,
                    'RUT'
                )

                RETURNING id_reserva
                "
            );


        $stmtInsert->execute([
            ':id_horario' =>
                $idHorario,

            ':id_servicio' =>
                $idServicio,

            ':nombre' =>
                $nombre,

            ':rut' =>
                $rut,

            ':correo' =>
                $correo !== ''
                    ? $correo
                    : null,

            ':telefono' =>
                $telefono
        ]);


        $nuevoId =
            (int)
            $stmtInsert->fetchColumn();


        $stmtBloquear =
            $conexion->prepare(
                "
                UPDATE horarios

                SET
                    estado = 'bloqueado'

                WHERE id_horario =
                    :id_horario
                "
            );


        $stmtBloquear->execute([
            ':id_horario' =>
                $idHorario
        ]);


        $conexion->commit();


        responder(
            true,
            'Reserva creada correctamente.',
            [
                'id_reserva' =>
                    $nuevoId
            ]
        );


    } catch (PDOException $e) {

        if (
            $conexion->inTransaction()
        ) {

            $conexion->rollBack();
        }


        error_log(
            'Error crear reserva: ' .
            $e->getMessage()
        );


        responder(
            false,
            'No fue posible crear la reserva.',
            [],
            500
        );
    }
}


// =========================================================
// CONFIRMAR
// =========================================================

if (
    $accion === 'confirmar'
) {

    $idReserva =
        isset($_POST['id_reserva'])
            ? (int) $_POST['id_reserva']
            : 0;


    if (
        $idReserva <= 0
    ) {

        responder(
            false,
            'Reserva no válida.',
            [],
            400
        );
    }


    try {

        $stmt =
            $conexion->prepare(
                "
                UPDATE reservas

                SET
                    estado =
                        'confirmada',

                    fecha_actualizacion =
                        CURRENT_TIMESTAMP

                WHERE id_reserva =
                    :id_reserva

                  AND estado <>
                    'cancelada'

                RETURNING id_reserva
                "
            );


        $stmt->execute([
            ':id_reserva' =>
                $idReserva
        ]);


        if (
            !$stmt->fetchColumn()
        ) {

            responder(
                false,
                'La reserva no existe o no puede ser confirmada.',
                [],
                404
            );
        }


        responder(
            true,
            'Reserva confirmada correctamente.'
        );


    } catch (PDOException $e) {

        error_log(
            'Error confirmar reserva: ' .
            $e->getMessage()
        );


        responder(
            false,
            'No fue posible confirmar la reserva.',
            [],
            500
        );
    }
}


// =========================================================
// REAGENDAR / MODIFICAR
// =========================================================

if (
    $accion === 'reagendar'
) {

    $idReserva =
        isset($_POST['id_reserva'])
            ? (int) $_POST['id_reserva']
            : 0;


    $fecha =
        trim(
            $_POST['fecha_nueva']
            ?? $_POST['fecha']
            ?? ''
        );


    $hora =
        trim(
            $_POST['hora_nueva']
            ?? $_POST['hora']
            ?? ''
        );


    if (
        $idReserva <= 0
    ) {

        responder(
            false,
            'Reserva no válida.',
            [],
            400
        );
    }


    if (
        !validarFecha($fecha)
    ) {

        responder(
            false,
            'La nueva fecha no es válida.',
            [],
            400
        );
    }


    if (
        !validarHora($hora)
    ) {

        responder(
            false,
            'La nueva hora no es válida.',
            [],
            400
        );
    }


    try {

        $conexion->beginTransaction();


        /*
         * -----------------------------------------------------
         * RESERVA ACTUAL
         * -----------------------------------------------------
         */

        $stmtReserva =
            $conexion->prepare(
                "
                SELECT

                    r.id_reserva,
                    r.id_servicio,
                    r.id_horario,
                    r.estado,

                    h.fecha AS fecha_actual,
                    h.hora AS hora_actual

                FROM reservas r

                INNER JOIN horarios h
                    ON h.id_horario =
                        r.id_horario

                WHERE r.id_reserva =
                    :id_reserva

                FOR UPDATE
                "
            );


        $stmtReserva->execute([
            ':id_reserva' =>
                $idReserva
        ]);


        $reservaActual =
            $stmtReserva->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$reservaActual
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva no existe.',
                [],
                404
            );
        }


        if (
            $reservaActual['estado'] ===
            'cancelada'
        ) {

            $conexion->rollBack();

            responder(
                false,
                'Una reserva cancelada no puede modificarse.',
                [],
                400
            );
        }


        /*
         * -----------------------------------------------------
         * OBTENER SERVICIO
         * -----------------------------------------------------
         */

        $servicio =
            obtenerServicio(
                $conexion,
                (int)
                $reservaActual[
                    'id_servicio'
                ]
            );


        if (
            !$servicio
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El servicio de la reserva no existe.',
                [],
                404
            );
        }


        /*
         * -----------------------------------------------------
         * BLOQUEO DE CONCURRENCIA
         * -----------------------------------------------------
         */

        bloquearDisponibilidad(
            $conexion,
            $servicio,
            $fecha,
            $hora
        );


        /*
         * -----------------------------------------------------
         * VALIDAR FECHA NUEVA
         * -----------------------------------------------------
         */

        $fechaObjeto =
            new DateTime(
                $fecha
            );


        $diaSemana =
            (int)
            $fechaObjeto->format('N');


        if (
            $diaSemana >= 6
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No se puede modificar una reserva para un fin de semana.',
                [],
                400
            );
        }


        $hoy =
            new DateTime(
                'today',
                new DateTimeZone(
                    'America/Santiago'
                )
            );


        $fechaComparar =
            clone $fechaObjeto;

        $fechaComparar->setTime(
            0,
            0,
            0
        );


        if (
            $fechaComparar < $hoy
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No se puede modificar una reserva hacia una fecha que ya pasó.',
                [],
                400
            );
        }


        /*
         * -----------------------------------------------------
         * VALIDAR HORA NUEVA
         * -----------------------------------------------------
         */

        $horaObjeto =
            DateTime::createFromFormat(
                'H:i',
                $hora
            );


        if (
            !$horaObjeto
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La nueva hora no es válida.',
                [],
                400
            );
        }


        $horaMinutos =
            (
                (int)
                $horaObjeto->format('H')
                * 60
            )
            +
            (int)
            $horaObjeto->format('i');


        $ahora =
            new DateTime(
                'now',
                new DateTimeZone(
                    'America/Santiago'
                )
            );


        if (
            $fecha ===
            $ahora->format('Y-m-d')
            &&
            $hora <=
            $ahora->format('H:i')
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La nueva hora ya pasó. Seleccione una hora posterior.',
                [],
                400
            );
        }


        /*
         * -----------------------------------------------------
         * CONFIGURACIÓN
         * -----------------------------------------------------
         */

        $idReferencia =
            obtenerServicioReferencia(
                $conexion,
                $servicio
            );


        $config =
            obtenerConfiguracionHorario(
                $conexion,
                $idReferencia,
                $diaSemana
            );


        if (
            !$config ||
            !esVerdadero(
                $config['activo']
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No existe una configuración de atención para la fecha seleccionada.',
                [],
                409
            );
        }


        /*
         * -----------------------------------------------------
         * EXCEPCIÓN
         * -----------------------------------------------------
         */

        $excepcion =
            obtenerExcepcion(
                $conexion,
                $idReferencia,
                $fecha
            );


        if (
            $excepcion &&
            esVerdadero(
                $excepcion[
                    'es_bloqueo_total'
                ]
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La fecha seleccionada está completamente bloqueada.',
                [],
                409
            );
        }


        $horaInicio =
            (
                $excepcion &&
                !empty(
                    $excepcion['hora_inicio']
                )
            )
                ? substr(
                    $excepcion['hora_inicio'],
                    0,
                    5
                )
                : substr(
                    $config['hora_inicio'],
                    0,
                    5
                );


        $horaFin =
            (
                $excepcion &&
                !empty(
                    $excepcion['hora_fin']
                )
            )
                ? substr(
                    $excepcion['hora_fin'],
                    0,
                    5
                )
                : substr(
                    $config['hora_fin'],
                    0,
                    5
                );


        $intervalo =
            (int)
            $config[
                'intervalo_minutos'
            ];


        if (
            !in_array(
                $intervalo,
                [15, 20, 30, 45, 60],
                true
            )
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La configuración de horarios no es válida.',
                [],
                500
            );
        }


        /*
         * -----------------------------------------------------
         * VALIDAR RANGO
         * -----------------------------------------------------
         */

        [$inicioH, $inicioM] =
            array_map(
                'intval',
                explode(
                    ':',
                    $horaInicio
                )
            );


        [$finH, $finM] =
            array_map(
                'intval',
                explode(
                    ':',
                    $horaFin
                )
            );


        $inicioMinutos =
            ($inicioH * 60) +
            $inicioM;


        $finMinutos =
            ($finH * 60) +
            $finM;


        if (
            $horaMinutos < $inicioMinutos ||
            $horaMinutos >= $finMinutos
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La nueva hora está fuera del horario de atención configurado.',
                [],
                400
            );
        }


        if (
            (
                $horaMinutos -
                $inicioMinutos
            ) % $intervalo !== 0
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La nueva hora no corresponde al intervalo configurado.',
                [],
                400
            );
        }


        /*
         * -----------------------------------------------------
         * ALMUERZO
         * -----------------------------------------------------
         */

        if (
            esVerdadero(
                $config['tiene_almuerzo']
            ) &&
            !empty(
                $config['almuerzo_inicio']
            ) &&
            !empty(
                $config['almuerzo_fin']
            )
        ) {

            [$aH, $aM] =
                array_map(
                    'intval',
                    explode(
                        ':',
                        substr(
                            $config['almuerzo_inicio'],
                            0,
                            5
                        )
                    )
                );


            [$bH, $bM] =
                array_map(
                    'intval',
                    explode(
                        ':',
                        substr(
                            $config['almuerzo_fin'],
                            0,
                            5
                        )
                    )
                );


            $almuerzoInicio =
                ($aH * 60) +
                $aM;


            $almuerzoFin =
                ($bH * 60) +
                $bM;


            if (
                $horaMinutos >=
                    $almuerzoInicio
                &&
                $horaMinutos <
                    $almuerzoFin
            ) {

                $conexion->rollBack();

                responder(
                    false,
                    'La nueva hora corresponde al horario de almuerzo.',
                    [],
                    400
                );
            }
        }


        /*
         * -----------------------------------------------------
         * MISMA FECHA / HORA
         * -----------------------------------------------------
         */

        $fechaActual =
            (string)
            $reservaActual[
                'fecha_actual'
            ];


        $horaActual =
            substr(
                (string)
                $reservaActual[
                    'hora_actual'
                ],
                0,
                5
            );


        if (
            $fechaActual === $fecha &&
            $horaActual === $hora
        ) {

            $conexion->rollBack();

            responder(
                false,
                'Debe seleccionar una fecha u hora diferente.',
                [],
                400
            );
        }


        /*
         * -----------------------------------------------------
         * COMPROBAR DISPONIBILIDAD COMPARTIDA
         * -----------------------------------------------------
         */

        $idsDisponibilidad =
            obtenerServiciosDisponibilidad(
                $conexion,
                $servicio
            );


        $placeholders = [];
        $parametros = [];


        foreach (
            $idsDisponibilidad
            as $indice =>
            $idServicio
        ) {

            $param =
                ':servicio_' .
                $indice;

            $placeholders[] =
                $param;

            $parametros[$param] =
                $idServicio;
        }


        $sqlConflicto = "

            SELECT
                r.id_reserva

            FROM reservas r

            INNER JOIN horarios h
                ON h.id_horario =
                    r.id_horario

            WHERE h.id_servicio IN (
                " .
                implode(
                    ',',
                    $placeholders
                ) .
                "
            )

              AND h.fecha =
                  :fecha

              AND h.hora =
                  :hora

              AND r.id_reserva <>
                  :id_reserva

              AND r.estado IN (
                  'confirmada',
                  'reagendada'
              )

            LIMIT 1

            FOR UPDATE
        ";


        $stmtConflicto =
            $conexion->prepare(
                $sqlConflicto
            );


        foreach (
            $parametros
            as $parametro =>
            $valor
        ) {

            $stmtConflicto->bindValue(
                $parametro,
                $valor,
                PDO::PARAM_INT
            );
        }


        $stmtConflicto->bindValue(
            ':fecha',
            $fecha,
            PDO::PARAM_STR
        );


        $stmtConflicto->bindValue(
            ':hora',
            $hora,
            PDO::PARAM_STR
        );


        $stmtConflicto->bindValue(
            ':id_reserva',
            $idReserva,
            PDO::PARAM_INT
        );


        $stmtConflicto->execute();


        if (
            $stmtConflicto->fetch()
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El nuevo horario ya está ocupado.',
                [],
                409
            );
        }


        /*
         * -----------------------------------------------------
         * BUSCAR HORARIO DEL MISMO SERVICIO
         * -----------------------------------------------------
         */

        $stmtHorario =
            $conexion->prepare(
                "
                SELECT
                    id_horario,
                    estado

                FROM horarios

                WHERE id_servicio =
                    :id_servicio

                  AND fecha =
                    :fecha

                  AND hora =
                    :hora

                FOR UPDATE
                "
            );


        $stmtHorario->execute([
            ':id_servicio' =>
                $reservaActual[
                    'id_servicio'
                ],

            ':fecha' =>
                $fecha,

            ':hora' =>
                $hora
        ]);


        $nuevoHorario =
            $stmtHorario->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            $nuevoHorario
        ) {

            if (
                strtolower(
                    trim(
                        (string)
                        $nuevoHorario['estado']
                    )
                ) !==
                'disponible'
            ) {

                $conexion->rollBack();

                responder(
                    false,
                    'El nuevo horario no está disponible.',
                    [],
                    409
                );
            }


            $idNuevoHorario =
                (int)
                $nuevoHorario[
                    'id_horario'
                ];

        } else {

            $stmtCrear =
                $conexion->prepare(
                    "
                    INSERT INTO horarios
                    (
                        id_servicio,
                        fecha,
                        hora,
                        estado,
                        observacion
                    )

                    VALUES
                    (
                        :id_servicio,
                        :fecha,
                        :hora,
                        'disponible',
                        NULL
                    )

                    RETURNING id_horario
                    "
                );


            $stmtCrear->execute([
                ':id_servicio' =>
                    $reservaActual[
                        'id_servicio'
                    ],

                ':fecha' =>
                    $fecha,

                ':hora' =>
                    $hora
            ]);


            $idNuevoHorario =
                (int)
                $stmtCrear->fetchColumn();
        }


        /*
         * -----------------------------------------------------
         * LIBERAR HORARIO ANTERIOR
         * -----------------------------------------------------
         */

        $stmtLiberar =
            $conexion->prepare(
                "
                UPDATE horarios

                SET
                    estado =
                        'disponible'

                WHERE id_horario =
                    :id_horario
                "
            );


        $stmtLiberar->execute([
            ':id_horario' =>
                $reservaActual[
                    'id_horario'
                ]
        ]);


        /*
         * -----------------------------------------------------
         * BLOQUEAR NUEVO HORARIO
         * -----------------------------------------------------
         */

        $stmtBloquear =
            $conexion->prepare(
                "
                UPDATE horarios

                SET
                    estado =
                        'bloqueado'

                WHERE id_horario =
                    :id_horario

                  AND estado =
                      'disponible'
                "
            );


        $stmtBloquear->execute([
            ':id_horario' =>
                $idNuevoHorario
        ]);


        if (
            $stmtBloquear->rowCount() === 0
        ) {

            $conexion->rollBack();

            responder(
                false,
                'El nuevo horario dejó de estar disponible.',
                [],
                409
            );
        }


        /*
         * -----------------------------------------------------
         * ACTUALIZAR RESERVA
         * -----------------------------------------------------
         */

        $stmtActualizar =
            $conexion->prepare(
                "
                UPDATE reservas

                SET
                    id_horario =
                        :id_horario,

                    estado =
                        'reagendada',

                    fecha_actualizacion =
                        CURRENT_TIMESTAMP

                WHERE id_reserva =
                    :id_reserva
                "
            );


        $stmtActualizar->execute([
            ':id_horario' =>
                $idNuevoHorario,

            ':id_reserva' =>
                $idReserva
        ]);


        if (
            $stmtActualizar->rowCount() === 0
        ) {

            $conexion->rollBack();

            responder(
                false,
                'No fue posible actualizar la reserva.',
                [],
                500
            );
        }


        $conexion->commit();


        responder(
            true,
            'Reserva modificada correctamente.',
            [
                'id_reserva' =>
                    $idReserva,

                'fecha' =>
                    $fecha,

                'hora' =>
                    $hora
            ]
        );


    } catch (PDOException $e) {

        if (
            $conexion->inTransaction()
        ) {

            $conexion->rollBack();
        }


        error_log(
            'Error modificar reserva: ' .
            $e->getMessage()
        );


        responder(
            false,
            'No fue posible modificar la reserva.',
            [],
            500
        );
    }
}


// =========================================================
// CANCELAR
// =========================================================

if (
    $accion === 'cancelar'
) {

    $idReserva =
        isset($_POST['id_reserva'])
            ? (int) $_POST['id_reserva']
            : 0;


    if (
        $idReserva <= 0
    ) {

        responder(
            false,
            'Reserva no válida.',
            [],
            400
        );
    }


    try {

        $conexion->beginTransaction();


        $stmtReserva =
            $conexion->prepare(
                "
                SELECT
                    id_horario

                FROM reservas

                WHERE id_reserva =
                    :id_reserva

                FOR UPDATE
                "
            );


        $stmtReserva->execute([
            ':id_reserva' =>
                $idReserva
        ]);


        $reserva =
            $stmtReserva->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$reserva
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva no existe.',
                [],
                404
            );
        }


        $stmt =
            $conexion->prepare(
                "
                UPDATE reservas

                SET
                    estado =
                        'cancelada',

                    fecha_actualizacion =
                        CURRENT_TIMESTAMP

                WHERE id_reserva =
                    :id_reserva

                  AND estado <>
                    'cancelada'

                RETURNING id_reserva
                "
            );


        $stmt->execute([
            ':id_reserva' =>
                $idReserva
        ]);


        if (
            !$stmt->fetchColumn()
        ) {

            $conexion->rollBack();

            responder(
                false,
                'La reserva no existe o ya está cancelada.',
                [],
                404
            );
        }


        $stmtLiberar =
            $conexion->prepare(
                "
                UPDATE horarios

                SET
                    estado =
                        'disponible'

                WHERE id_horario =
                    :id_horario
                "
            );


        $stmtLiberar->execute([
            ':id_horario' =>
                $reserva[
                    'id_horario'
                ]
        ]);


        $conexion->commit();


        responder(
            true,
            'Reserva cancelada correctamente.'
        );


    } catch (PDOException $e) {

        if (
            $conexion->inTransaction()
        ) {

            $conexion->rollBack();
        }


        error_log(
            'Error cancelar reserva: ' .
            $e->getMessage()
        );


        responder(
            false,
            'No fue posible cancelar la reserva.',
            [],
            500
        );
    }
}