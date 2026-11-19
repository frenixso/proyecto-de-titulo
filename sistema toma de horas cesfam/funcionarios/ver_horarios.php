<?php

require_once '../componentes/auth_funcionario.php';
require_once '../config/conexion.php';


/* =========================================================
   DATOS DEL FUNCIONARIO AUTENTICADO
   ========================================================= */

$nombreFuncionario =
    $_SESSION['nombre_funcionario'] ??
    'Funcionario';

$apellidoFuncionario =
    $_SESSION['apellido_funcionario'] ??
    '';

$rolFuncionario =
    $_SESSION['rol_funcionario'] ??
    'funcionario';

$usuarioFuncionario =
    $_SESSION['usuario_funcionario'] ??
    '';


$nombreCompleto =
    trim(
        $nombreFuncionario .
        ' ' .
        $apellidoFuncionario
    );


if ($nombreCompleto === '') {

    $nombreCompleto =
        'Funcionario';

}


/* =========================================================
   CONFIGURACIÓN DE COMPONENTES
   ========================================================= */

$base_url =
    '/municipalidad';

$nombre_funcionario =
    $nombreCompleto;

$pagina_actual =
    'ver_horarios';


/* =========================================================
   VARIABLES
   ========================================================= */

$mensaje = '';

$tipoMensaje = '';

$filtroDepartamento =
    isset($_GET['departamento'])
        ? (int) $_GET['departamento']
        : 0;

$filtroServicio =
    isset($_GET['servicio'])
        ? (int) $_GET['servicio']
        : 0;


$diasSemana = [

    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo'

];


$idDepartamentoFuncionario =
    0;

$nombreDepartamentoFuncionario =
    '';

$departamentos = [];

$servicios = [];

$configuracionesActuales = [];

$diasActivos = [];

$cantidadDiasActivos = 0;


/* =========================================================
   CARGAR DEPARTAMENTO DEL FUNCIONARIO
   ========================================================= */

try {

    if (
        $rolFuncionario !==
        'superadmin'
    ) {

        if (
            $usuarioFuncionario === ''
        ) {

            throw new Exception(
                'No se pudo identificar al funcionario autenticado.'
            );

        }


        $sqlDepartamentoFuncionario = "

            SELECT
                f.id_departamento,
                d.nombre AS nombre_departamento

            FROM funcionarios f

            INNER JOIN departamentos d
                ON d.id_departamento =
                    f.id_departamento

            WHERE f.usuario =
                :usuario

              AND d.activo =
                TRUE

            LIMIT 1

        ";


        $stmtDepartamentoFuncionario =
            $conexion->prepare(
                $sqlDepartamentoFuncionario
            );


        $stmtDepartamentoFuncionario->execute(
            [

                ':usuario' =>
                    $usuarioFuncionario

            ]
        );


        $departamentoFuncionario =
            $stmtDepartamentoFuncionario->fetch(
                PDO::FETCH_ASSOC
            );


        if (
            !$departamentoFuncionario
        ) {

            throw new Exception(
                'El funcionario no tiene un departamento activo asociado.'
            );

        }


        $idDepartamentoFuncionario =
            (int) $departamentoFuncionario[
                'id_departamento'
            ];


        $nombreDepartamentoFuncionario =
            $departamentoFuncionario[
                'nombre_departamento'
            ];


        $filtroDepartamento =
            $idDepartamentoFuncionario;

    }

} catch (Exception $e) {

    $mensaje =
        'Error: ' .
        $e->getMessage();

    $tipoMensaje =
        'error';

}


/* =========================================================
   CARGAR DEPARTAMENTOS
   ========================================================= */

if (
    $rolFuncionario ===
    'superadmin'
) {

    try {

        $sqlDepartamentos = "

            SELECT
                id_departamento,
                nombre

            FROM departamentos

            WHERE activo =
                TRUE

            ORDER BY
                nombre ASC

        ";


        $stmtDepartamentos =
            $conexion->query(
                $sqlDepartamentos
            );


        $departamentos =
            $stmtDepartamentos->fetchAll(
                PDO::FETCH_ASSOC
            );


        if (
            $filtroDepartamento > 0
        ) {

            $departamentoValido =
                false;


            foreach (
                $departamentos as $departamento
            ) {

                if (
                    (int) $departamento[
                        'id_departamento'
                    ] ===
                    $filtroDepartamento
                ) {

                    $departamentoValido =
                        true;

                    break;

                }

            }


            if (
                !$departamentoValido
            ) {

                $filtroDepartamento =
                    0;

                $filtroServicio =
                    0;

                $mensaje =
                    'El departamento seleccionado no es válido.';

                $tipoMensaje =
                    'error';

            }

        }

    } catch (PDOException $e) {

        $departamentos = [];

    }

}


/* =========================================================
   CARGAR SERVICIOS PERMITIDOS
   ========================================================= */

try {

    if (
        $rolFuncionario ===
        'superadmin'
    ) {

        if (
            $filtroDepartamento > 0
        ) {

            $sqlServicios = "

                SELECT
                    s.id_servicio,
                    s.nombre,
                    s.descripcion,
                    s.id_departamento,
                    d.nombre AS nombre_departamento

                FROM servicios s

                INNER JOIN departamentos d
                    ON d.id_departamento =
                        s.id_departamento

                WHERE s.activo =
                    TRUE

                  AND d.activo =
                    TRUE

                  AND s.id_departamento =
                    :id_departamento

                ORDER BY
                    s.nombre ASC

            ";


            $stmtServicios =
                $conexion->prepare(
                    $sqlServicios
                );


            $stmtServicios->execute(
                [

                    ':id_departamento' =>
                        $filtroDepartamento

                ]
            );

        } else {

            $sqlServicios = "

                SELECT
                    s.id_servicio,
                    s.nombre,
                    s.descripcion,
                    s.id_departamento,
                    d.nombre AS nombre_departamento

                FROM servicios s

                INNER JOIN departamentos d
                    ON d.id_departamento =
                        s.id_departamento

                WHERE s.activo =
                    TRUE

                  AND d.activo =
                    TRUE

                ORDER BY
                    d.nombre ASC,
                    s.nombre ASC

            ";


            $stmtServicios =
                $conexion->query(
                    $sqlServicios
                );

        }

    } else {

        $sqlServicios = "

            SELECT
                s.id_servicio,
                s.nombre,
                s.descripcion,
                s.id_departamento,
                d.nombre AS nombre_departamento

            FROM servicios s

            INNER JOIN departamentos d
                ON d.id_departamento =
                    s.id_departamento

            WHERE s.activo =
                TRUE

              AND d.activo =
                TRUE

              AND s.id_departamento =
                :id_departamento

            ORDER BY
                s.nombre ASC

        ";


        $stmtServicios =
            $conexion->prepare(
                $sqlServicios
            );


        $stmtServicios->execute(
            [

                ':id_departamento' =>
                    $idDepartamentoFuncionario

            ]
        );

    }


    $servicios =
        $stmtServicios->fetchAll(
            PDO::FETCH_ASSOC
        );


    if (
        $filtroServicio <= 0 &&
        count($servicios) > 0
    ) {

        $filtroServicio =
            (int) $servicios[0][
                'id_servicio'
            ];

    }


    if (
        $filtroServicio > 0
    ) {

        $servicioPermitido =
            false;


        foreach (
            $servicios as $servicio
        ) {

            if (
                (int) $servicio[
                    'id_servicio'
                ] ===
                $filtroServicio
            ) {

                $servicioPermitido =
                    true;

                break;

            }

        }


        if (
            !$servicioPermitido
        ) {

            $filtroServicio =
                0;

            $mensaje =
                'El servicio seleccionado no pertenece a un departamento permitido.';

            $tipoMensaje =
                'error';

        }

    }

} catch (PDOException $e) {

    $servicios = [];

}


/* =========================================================
   CARGAR CONFIGURACIÓN DEL SERVICIO
   ========================================================= */

if (
    $filtroServicio > 0
) {

    try {

        $sqlConfiguracion = "

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

            WHERE id_servicio =
                :id_servicio

            ORDER BY
                dia_semana ASC

        ";


        $stmtConfiguracion =
            $conexion->prepare(
                $sqlConfiguracion
            );


        $stmtConfiguracion->execute(
            [

                ':id_servicio' =>
                    $filtroServicio

            ]
        );


        $registrosConfiguracion =
            $stmtConfiguracion->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $registrosConfiguracion
            as $configuracion
        ) {

            $configuracionesActuales[
                (int) $configuracion['dia_semana']
            ] =
                $configuracion;

        }

    } catch (PDOException $e) {

        $configuracionesActuales = [];

        $mensaje =
            'No se pudo cargar la configuración del horario.';

        $tipoMensaje =
            'error';

    }

}


/* =========================================================
   FUNCIONES PARA GENERAR BLOQUES
   ========================================================= */

function convertirMinutos(
    string $hora
): int {

    $partes =
        explode(
            ':',
            substr(
                $hora,
                0,
                5
            )
        );


    $horaParte =
        isset($partes[0])
            ? (int) $partes[0]
            : 0;


    $minutoParte =
        isset($partes[1])
            ? (int) $partes[1]
            : 0;


    return (
        $horaParte * 60
    ) + $minutoParte;

}


function formatearMinutos(
    int $minutos
): string {

    $horas =
        floor(
            $minutos / 60
        );


    $minutosRestantes =
        $minutos % 60;


    return sprintf(
        '%02d:%02d',
        $horas,
        $minutosRestantes
    );

}


function generarBloquesHorario(
    array $configuracion
): array {

    $bloques = [];


    $horaInicio =
        substr(
            $configuracion['hora_inicio'],
            0,
            5
        );


    $horaFin =
        substr(
            $configuracion['hora_fin'],
            0,
            5
        );


    $intervalo =
        (int) $configuracion[
            'intervalo_minutos'
        ];


    $tieneAlmuerzo =
        $configuracion[
            'tiene_almuerzo'
        ] === true ||
        $configuracion[
            'tiene_almuerzo'
        ] === 't' ||
        $configuracion[
            'tiene_almuerzo'
        ] === '1' ||
        $configuracion[
            'tiene_almuerzo'
        ] === 1;


    $almuerzoInicio =
        $tieneAlmuerzo &&
        !empty(
            $configuracion[
                'almuerzo_inicio'
            ]
        )
            ? substr(
                $configuracion[
                    'almuerzo_inicio'
                ],
                0,
                5
            )
            : null;


    $almuerzoFin =
        $tieneAlmuerzo &&
        !empty(
            $configuracion[
                'almuerzo_fin'
            ]
        )
            ? substr(
                $configuracion[
                    'almuerzo_fin'
                ],
                0,
                5
            )
            : null;


    $inicioMinutos =
        convertirMinutos(
            $horaInicio
        );


    $finMinutos =
        convertirMinutos(
            $horaFin
        );


    $almuerzoInicioMinutos =
        $almuerzoInicio !== null
            ? convertirMinutos(
                $almuerzoInicio
            )
            : null;


    $almuerzoFinMinutos =
        $almuerzoFin !== null
            ? convertirMinutos(
                $almuerzoFin
            )
            : null;


    if (
        $intervalo <= 0
    ) {

        return $bloques;

    }


    $actual =
        $inicioMinutos;


    while (
        $actual < $finMinutos
    ) {

        if (
            $almuerzoInicioMinutos !== null &&
            $almuerzoFinMinutos !== null &&
            $actual >= $almuerzoInicioMinutos &&
            $actual < $almuerzoFinMinutos
        ) {

            $bloques[] = [

                'tipo' =>
                    'almuerzo',

                'inicio' =>
                    formatearMinutos(
                        $almuerzoInicioMinutos
                    ),

                'fin' =>
                    formatearMinutos(
                        $almuerzoFinMinutos
                    )

            ];


            $actual =
                $almuerzoFinMinutos;


            continue;

        }


        $siguiente =
            min(
                $actual + $intervalo,
                $finMinutos
            );


        if (
            $almuerzoInicioMinutos !== null &&
            $almuerzoFinMinutos !== null &&
            $actual < $almuerzoInicioMinutos &&
            $siguiente > $almuerzoInicioMinutos
        ) {

            $siguiente =
                $almuerzoInicioMinutos;

        }


        if (
            $siguiente > $actual
        ) {

            $bloques[] = [

                'tipo' =>
                    'atencion',

                'inicio' =>
                    formatearMinutos(
                        $actual
                    ),

                'fin' =>
                    formatearMinutos(
                        $siguiente
                    )

            ];

        }


        $actual =
            $siguiente;

    }


    $bloquesFinales = [];

    $almuerzoAgregado = false;


    foreach (
        $bloques as $bloque
    ) {

        if (
            $bloque['tipo'] ===
            'almuerzo'
        ) {

            if (
                $almuerzoAgregado
            ) {

                continue;

            }

            $almuerzoAgregado =
                true;

        }


        $bloquesFinales[] =
            $bloque;

    }


    return $bloquesFinales;

}


/* =========================================================
   DETERMINAR DÍAS ACTIVOS
   ========================================================= */

foreach (
    $diasSemana as $diaNum => $diaNombre
) {

    $configuracion =
        $configuracionesActuales[
            $diaNum
        ] ?? null;


    if (
        !$configuracion
    ) {

        continue;

    }


    $esActivo =
        $configuracion[
            'activo'
        ] === true ||
        $configuracion[
            'activo'
        ] === 't' ||
        $configuracion[
            'activo'
        ] === '1' ||
        $configuracion[
            'activo'
        ] === 1;


    if (
        !$esActivo
    ) {

        continue;

    }


    $diasActivos[$diaNum] = [

        'nombre' =>
            $diaNombre,

        'configuracion' =>
            $configuracion

    ];

}


$cantidadDiasActivos =
    count($diasActivos);


/* =========================================================
   DETERMINAR SERVICIO SELECCIONADO
   ========================================================= */

$servicioSeleccionado = null;


foreach (
    $servicios as $servicio
) {

    if (
        (int) $servicio[
            'id_servicio'
        ] ===
        $filtroServicio
    ) {

        $servicioSeleccionado =
            $servicio;

        break;

    }

}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Ver Horarios | Municipalidad
</title>

<link
    rel="stylesheet"
    href="../css/compartidos/general.css"
>

<link
    rel="stylesheet"
    href="../css/compartidos/navbar_funcionario.css"
>

<link
    rel="stylesheet"
    href="../css/compartidos/sidebar_funcionario.css"
>

<link
    rel="stylesheet"
    href="../css/compartidos/footer_funcionario.css"
>

<link
    rel="stylesheet"
    href="../css/funcionarios/ver_horarios.css"
>

</head>

<body>

<?php require_once '../componentes/navbar_funcionario.php'; ?>

<div class="admin-layout">

<?php require_once '../componentes/sidebar_funcionario.php'; ?>

<main class="admin-content">


<section class="schedule-view-header">

    <div>

        <span class="page-kicker">
            Consulta de Disponibilidad
        </span>

        <h1>
            Ver Horarios
        </h1>

        <p>
            Consulta la jornada de atención configurada para cada servicio.
            Esta sección es únicamente informativa.
        </p>

    </div>

</section>


<?php if (
    $mensaje !== ''
): ?>

    <div
        class="alert-box <?= $tipoMensaje === 'success' ? 'success' : 'error' ?>"
    >

        <?= htmlspecialchars(
            $mensaje,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </div>

<?php endif; ?>


<form
    method="GET"
    action="ver_horarios.php"
    class="schedule-view-filters"
>


    <?php if (
        $rolFuncionario ===
        'superadmin'
    ): ?>

        <div class="filter-group">

            <label for="schedule-department">
                Departamento
            </label>


            <select
                id="schedule-department"
                name="departamento"
                onchange="this.form.submit()"
            >

                <option value="0">

                    Todos los departamentos

                </option>


                <?php foreach (
                    $departamentos
                    as $departamento
                ): ?>

                    <option
                        value="<?= (int) $departamento['id_departamento'] ?>"
                        <?= $filtroDepartamento ===
                            (int) $departamento['id_departamento']
                                ? 'selected'
                                : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $departamento['nombre'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    <?php endif; ?>


    <div class="filter-group">

        <label for="schedule-service">
            Servicio
        </label>


        <select
            id="schedule-service"
            name="servicio"
            onchange="this.form.submit()"
        >

            <?php if (
                count($servicios) === 0
            ): ?>

                <option value="0">

                    No hay servicios disponibles

                </option>

            <?php else: ?>

                <?php foreach (
                    $servicios as $servicio
                ): ?>

                    <option
                        value="<?= (int) $servicio['id_servicio'] ?>"
                        <?= $filtroServicio ===
                            (int) $servicio['id_servicio']
                                ? 'selected'
                                : ''
                        ?>
                    >

                        <?php if (
                            $rolFuncionario ===
                            'superadmin' &&
                            $filtroDepartamento <= 0
                        ): ?>

                            <?= htmlspecialchars(
                                $servicio['nombre_departamento'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            -

                        <?php endif; ?>


                        <?= htmlspecialchars(
                            $servicio['nombre'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </option>

                <?php endforeach; ?>

            <?php endif; ?>

        </select>

    </div>


</form>


<?php if (
    $servicioSeleccionado
): ?>


    <section class="selected-service-card">

        <div>

            <span class="service-label">
                Servicio seleccionado
            </span>


            <h2>

                <?= htmlspecialchars(
                    $servicioSeleccionado['nombre'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h2>


            <?php if (
                !empty(
                    $servicioSeleccionado[
                        'descripcion'
                    ]
                )
            ): ?>

                <p>

                    <?= htmlspecialchars(
                        $servicioSeleccionado[
                            'descripcion'
                        ],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>

            <?php endif; ?>

        </div>


        <?php if (
            $rolFuncionario !==
            'superadmin'
        ): ?>

            <span class="department-badge">

                <?= htmlspecialchars(
                    $nombreDepartamentoFuncionario,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        <?php else: ?>

            <span class="department-badge">

                <?= htmlspecialchars(
                    $servicioSeleccionado[
                        'nombre_departamento'
                    ],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </span>

        <?php endif; ?>


    </section>


    <section class="schedule-weekly-section">


        <div class="section-title-bar">

            <h2>
                Horario semanal
            </h2>

            <p>
                Los bloques se generan automáticamente
                según la configuración registrada para este servicio.
            </p>

        </div>


        <?php if (
            $cantidadDiasActivos > 0
        ): ?>


            <div class="weekly-schedule-wrapper">


                <div
                    class="weekly-schedule-grid"
                    style="grid-template-columns: repeat(<?= $cantidadDiasActivos ?>, minmax(0, 1fr));"
                >


                    <?php foreach (
                        $diasActivos
                        as $diaNum => $dia
                    ): ?>


                        <?php

                        $configuracion =
                            $dia['configuracion'];


                        $bloques =
                            generarBloquesHorario(
                                $configuracion
                            );

                        ?>


                        <article
                            class="weekly-day-card active"
                        >


                            <div class="weekly-day-header">

                                <div>

                                    <h3>

                                        <?= htmlspecialchars(
                                            $dia['nombre'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </h3>

                                </div>


                                <span
                                    class="weekly-day-status"
                                >
                                    Atención activa
                                </span>

                            </div>


                            <div class="weekly-day-content">


                                <?php if (
                                    count($bloques) === 0
                                ): ?>


                                    <div
                                        class="no-service-message"
                                    >

                                        <span>
                                            ⚠️
                                        </span>

                                        <p>
                                            No hay bloques de atención configurados.
                                        </p>

                                    </div>


                                <?php else: ?>


                                    <div
                                        class="time-blocks"
                                    >


                                        <?php foreach (
                                            $bloques
                                            as $bloque
                                        ): ?>


                                            <?php if (
                                                $bloque['tipo'] ===
                                                'almuerzo'
                                            ): ?>


                                                <div
                                                    class="time-block lunch-block"
                                                >

                                                    <span
                                                        class="time-block-icon"
                                                    >
                                                        🍽️
                                                    </span>


                                                    <div
                                                        class="time-block-info"
                                                    >

                                                        <strong>
                                                            Pausa de almuerzo
                                                        </strong>


                                                        <span>

                                                            <?= htmlspecialchars(
                                                                $bloque['inicio'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>

                                                            -

                                                            <?= htmlspecialchars(
                                                                $bloque['fin'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>

                                                        </span>

                                                    </div>

                                                </div>


                                            <?php else: ?>


                                                <div
                                                    class="time-block"
                                                >

                                                    <span
                                                        class="time-block-icon"
                                                    >
                                                        🕐
                                                    </span>


                                                    <div
                                                        class="time-block-info"
                                                    >

                                                        <strong>
                                                            Atención
                                                        </strong>


                                                        <span>

                                                            <?= htmlspecialchars(
                                                                $bloque['inicio'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>

                                                            -

                                                            <?= htmlspecialchars(
                                                                $bloque['fin'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>

                                                        </span>

                                                    </div>

                                                </div>

                                            <?php endif; ?>


                                        <?php endforeach; ?>


                                    </div>

                                <?php endif; ?>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>

            </div>


        <?php else: ?>


            <div
                class="empty-state"
            >

                <span>
                    📅
                </span>

                <h2>
                    No hay días de atención configurados
                </h2>

                <p>
                    Este servicio no tiene ningún día activo
                    en su configuración semanal.
                </p>

            </div>


        <?php endif; ?>


    </section>


<?php elseif (
    count($servicios) === 0
): ?>


    <div
        class="empty-state"
    >

        <span>
            📭
        </span>

        <h2>
            No hay servicios disponibles
        </h2>

        <p>
            No existen servicios activos disponibles
            para consultar en este momento.
        </p>

    </div>


<?php endif; ?>


</main>

</div>

<?php require_once '../componentes/footer_funcionario.php'; ?>

</body>

</html>