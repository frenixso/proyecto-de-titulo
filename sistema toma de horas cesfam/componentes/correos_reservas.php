<?php
/**
 * Funciones compartidas de correo para las reservas del CESFAM.
 *
 * Los mensajes se envían después del COMMIT para que un error
 * de correo no revierta cambios ya guardados en PostgreSQL.
 */

if (!function_exists('fechaMostrarCorreoCESFAM')) {
    function fechaMostrarCorreoCESFAM($fecha): string
    {
        $fecha = trim((string) $fecha);
        $fechaCorta = substr($fecha, 0, 10);

        $objeto = DateTime::createFromFormat(
            '!Y-m-d',
            $fechaCorta
        );

        return (
            $objeto &&
            $objeto->format('Y-m-d') === $fechaCorta
        )
            ? $objeto->format('d/m/Y')
            : $fecha;
    }
}

if (!function_exists('horaMostrarCorreoCESFAM')) {
    function horaMostrarCorreoCESFAM($hora): string
    {
        $hora = trim((string) $hora);

        return $hora !== ''
            ? substr($hora, 0, 5)
            : '';
    }
}

if (!function_exists('datoServicioCorreoCESFAM')) {
    function datoServicioCorreoCESFAM(
        array $servicio,
        string $clave,
        string $alternativa = ''
    ): string {
        $valor = $servicio[$clave] ??
            (
                $alternativa !== ''
                    ? ($servicio[$alternativa] ?? '')
                    : ''
            );

        return trim((string) $valor);
    }
}

/**
 * Envía un correo al ciudadano.
 */
if (!function_exists('enviarCorreoReservaCESFAM')) {
    function enviarCorreoReservaCESFAM(
        array $reserva,
        array $servicio,
        string $asunto,
        string $titulo,
        string $introduccion,
        array $detalles,
        string $cierre
    ): void {
        $correo = trim(
            (string) ($reserva['correo'] ?? '')
        );

        // El correo del ciudadano es opcional.
        if ($correo === '') {
            return;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            error_log(
                'Correo CESFAM inválido para reserva #' .
                (string) ($reserva['id_reserva'] ?? 'N/A')
            );

            return;
        }

        try {
            require_once __DIR__ . '/../config/correo.php';

            $mail = crearCorreo();

            $nombre = trim(
                (string) ($reserva['nombre'] ?? '')
            );

            $nombreServicio = datoServicioCorreoCESFAM(
                $servicio,
                'nombre',
                'nombre_servicio'
            );

            $nombreDepartamento = datoServicioCorreoCESFAM(
                $servicio,
                'nombre_departamento'
            );

            $mail->addAddress($correo, $nombre);
            $mail->Subject = $asunto;

            $escape = static function ($valor): string {
                return htmlspecialchars(
                    (string) $valor,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
            };

            $filasHtml = '';
            $textoPlano = $titulo . "\n\n" .
                $introduccion . "\n\n";

            foreach ($detalles as $detalle) {
                $etiqueta = (string) ($detalle[0] ?? '');
                $valor = (string) ($detalle[1] ?? '');

                $filasHtml .=
                    '<tr>' .
                    '<td style="padding:9px 12px;border-bottom:1px solid #e6eaf0;font-weight:bold;width:42%;vertical-align:top;">' .
                    $escape($etiqueta) .
                    '</td>' .
                    '<td style="padding:9px 12px;border-bottom:1px solid #e6eaf0;vertical-align:top;">' .
                    $escape($valor) .
                    '</td>' .
                    '</tr>';

                $textoPlano .=
                    $etiqueta . ': ' . $valor . "\n";
            }

            $mail->Body = '
                <div style="font-family:Arial,Helvetica,sans-serif;max-width:650px;margin:0 auto;color:#273444;line-height:1.6;">
                    <div style="background:#087e8b;color:#ffffff;padding:22px 25px;border-radius:8px 8px 0 0;">
                        <h2 style="margin:0;font-size:22px;">' .
                            $escape($titulo) .
                        '</h2>
                        <p style="margin:5px 0 0;">CESFAM Eduardo Raggio Catemu</p>
                    </div>

                    <div style="padding:22px 25px;border:1px solid #e6eaf0;border-top:0;border-radius:0 0 8px 8px;">
                        <p>Estimado/a <strong>' .
                            $escape($nombre) .
                        '</strong>:</p>

                        <p>' .
                            $escape($introduccion) .
                        '</p>

                        <table role="presentation" style="width:100%;border-collapse:collapse;margin:20px 0;">
                            <tbody>' .
                                $filasHtml .
                            '</tbody>
                        </table>

                        <p>' .
                            $escape($cierre) .
                        '</p>

                        <p style="font-size:12px;color:#667085;margin-top:28px;">
                            Este mensaje fue generado automáticamente
                            por el sistema de toma de horas del CESFAM.
                            Por favor, no responda a este correo.
                        </p>
                    </div>
                </div>
            ';

            $mail->AltBody =
                $titulo . "\n\n" .
                'CESFAM Eduardo Raggio Catemu' . "\n\n" .
                'Estimado/a ' . $nombre . ":\n\n" .
                $introduccion . "\n\n" .
                $textoPlano . "\n" .
                $cierre . "\n\n" .
                'Este mensaje fue generado automáticamente por el sistema de toma de horas del CESFAM.';

            $mail->send();

        } catch (Throwable $e) {
            error_log(
                'Error enviando correo CESFAM para reserva #' .
                (string) ($reserva['id_reserva'] ?? 'N/A') .
                ' (' . $asunto . '): ' .
                $e->getMessage()
            );
        }
    }
}

/**
 * Correo de confirmación de la reserva.
 */
if (!function_exists('enviarCorreoConfirmacionReserva')) {
    function enviarCorreoConfirmacionReserva(
        array $reserva,
        array $servicio,
        string $fecha,
        string $hora
    ): void {
        $nombreServicio = datoServicioCorreoCESFAM(
            $servicio,
            'nombre',
            'nombre_servicio'
        );

        enviarCorreoReservaCESFAM(
            $reserva,
            $servicio,
            'Confirmación de hora - CESFAM Eduardo Raggio Catemu',
            'Su hora ha sido confirmada',
            'Su reserva ha sido registrada correctamente. Estos son los datos de su atención:',
            [
                [
                    'Número de reserva',
                    (string) ($reserva['id_reserva'] ?? '')
                ],
                [
                    'Departamento',
                    datoServicioCorreoCESFAM(
                        $servicio,
                        'nombre_departamento'
                    )
                ],
                [
                    'Servicio',
                    $nombreServicio
                ],
                [
                    'Fecha de atención',
                    fechaMostrarCorreoCESFAM($fecha)
                ],
                [
                    'Hora de atención',
                    horaMostrarCorreoCESFAM($hora)
                ],
            ],
            'Por favor, conserve este correo como comprobante de su hora de atención.'
        );
    }
}

/**
 * Correo de modificación de fecha u hora.
 */
if (!function_exists('enviarCorreoModificacionReserva')) {
    function enviarCorreoModificacionReserva(
        array $reserva,
        array $servicio,
        string $fechaAnterior,
        string $horaAnterior,
        string $fechaNueva,
        string $horaNueva
    ): void {
        $nombreServicio = datoServicioCorreoCESFAM(
            $servicio,
            'nombre',
            'nombre_servicio'
        );

        enviarCorreoReservaCESFAM(
            $reserva,
            $servicio,
            'Modificación de su hora - CESFAM Eduardo Raggio Catemu',
            'Su hora de atención fue modificada',
            'Le informamos que los datos de su reserva han sido modificados. Revise la nueva fecha y hora:',
            [
                [
                    'Número de reserva',
                    (string) ($reserva['id_reserva'] ?? '')
                ],
                [
                    'Departamento',
                    datoServicioCorreoCESFAM(
                        $servicio,
                        'nombre_departamento'
                    )
                ],
                [
                    'Servicio',
                    $nombreServicio
                ],
                [
                    'Fecha anterior',
                    fechaMostrarCorreoCESFAM($fechaAnterior)
                ],
                [
                    'Hora anterior',
                    horaMostrarCorreoCESFAM($horaAnterior)
                ],
                [
                    'Nueva fecha',
                    fechaMostrarCorreoCESFAM($fechaNueva)
                ],
                [
                    'Nueva hora',
                    horaMostrarCorreoCESFAM($horaNueva)
                ],
            ],
            'Lamentamos cualquier inconveniente que este cambio pueda ocasionarle.'
        );
    }
}

/**
 * Correo de cancelación de la reserva.
 */
if (!function_exists('enviarCorreoCancelacionReserva')) {
    function enviarCorreoCancelacionReserva(
        array $reserva,
        array $servicio,
        string $fechaCancelada,
        string $horaCancelada
    ): void {
        $nombreServicio = datoServicioCorreoCESFAM(
            $servicio,
            'nombre',
            'nombre_servicio'
        );

        enviarCorreoReservaCESFAM(
            $reserva,
            $servicio,
            'Cancelación de su hora - CESFAM Eduardo Raggio Catemu',
            'Su hora de atención fue cancelada',
            'Le informamos que la reserva indicada a continuación ha sido cancelada:',
            [
                [
                    'Número de reserva',
                    (string) ($reserva['id_reserva'] ?? '')
                ],
                [
                    'Departamento',
                    datoServicioCorreoCESFAM(
                        $servicio,
                        'nombre_departamento'
                    )
                ],
                [
                    'Servicio',
                    $nombreServicio
                ],
                [
                    'Fecha cancelada',
                    fechaMostrarCorreoCESFAM($fechaCancelada)
                ],
                [
                    'Hora cancelada',
                    horaMostrarCorreoCESFAM($horaCancelada)
                ],
            ],
            'Si necesita una nueva hora de atención, deberá solicitar otra reserva a través del sistema del CESFAM.'
        );
    }
}

/**
 * Envía un correo de modificación o cancelación a cada
 * funcionario activo del departamento correspondiente.
 *
 * Busca la columna de correo en la tabla funcionarios.
 * Retorna la cantidad de envíos aceptados por SMTP.
 */
if (!function_exists('enviarCorreoFuncionariosEventoReservaCESFAM')) {
    function enviarCorreoFuncionariosEventoReservaCESFAM(
        PDO $conexion,
        array $reserva,
        array $servicio,
        string $evento,
        array $detalles
    ): int {
        $idDepartamento = (int) (
            $servicio['id_departamento'] ?? 0
        );

        if ($idDepartamento <= 0) {
            error_log(
                'No se pudieron avisar funcionarios CESFAM: falta id_departamento.'
            );

            return 0;
        }

        $nombresColumnasCorreo = [
            'correo_institucional',
            'email_institucional',
            'correo',
            'correo_electronico',
            'email',
            'email_address'
        ];

        try {
            $columnas = implode(
                ', ',
                array_map(
                    static fn($valor) => $conexion->quote($valor),
                    $nombresColumnasCorreo
                )
            );

            $sqlColumna = "
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = current_schema()
                  AND table_name = 'funcionarios'
                  AND column_name IN ($columnas)
                ORDER BY CASE column_name
                    WHEN 'correo_institucional' THEN 1
                    WHEN 'email_institucional' THEN 2
                    WHEN 'correo' THEN 3
                    WHEN 'correo_electronico' THEN 4
                    WHEN 'email' THEN 5
                    WHEN 'email_address' THEN 6
                    ELSE 99
                END
                LIMIT 1
            ";

            $columnaCorreo = $conexion
                ->query($sqlColumna)
                ->fetchColumn();

            if (
                !is_string($columnaCorreo) ||
                !in_array(
                    $columnaCorreo,
                    $nombresColumnasCorreo,
                    true
                )
            ) {
                error_log(
                    'No se enviaron correos a funcionarios: ' .
                    'no existe una columna de correo reconocida en la tabla funcionarios.'
                );

                return 0;
            }

            // El nombre de columna procede de la lista permitida.
            $columnaSQL = '"' . $columnaCorreo . '"';

            $sqlFuncionarios = "
                SELECT
                    id_funcionario,
                    nombre,
                    apellido,
                    $columnaSQL AS correo
                FROM funcionarios
                WHERE id_departamento = :id_departamento
                  AND activo = TRUE
                  AND NULLIF(
                      TRIM(CAST($columnaSQL AS TEXT)),
                      ''
                  ) IS NOT NULL
                ORDER BY id_funcionario ASC
            ";

            $stmt = $conexion->prepare($sqlFuncionarios);

            $stmt->execute([
                ':id_departamento' => $idDepartamento
            ]);

            $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$funcionarios) {
                error_log(
                    'No hay funcionarios con correo configurado para el departamento ' .
                    $idDepartamento . '.'
                );

                return 0;
            }

            require_once __DIR__ . '/../config/correo.php';

            $enviados = 0;

            $nombreServicio = datoServicioCorreoCESFAM(
                $servicio,
                'nombre',
                'nombre_servicio'
            );

            $nombreDepartamento = datoServicioCorreoCESFAM(
                $servicio,
                'nombre_departamento'
            );

            $idReserva = (string) (
                $reserva['id_reserva'] ?? ''
            );

            $nombrePaciente = trim(
                (string) ($reserva['nombre'] ?? 'Paciente')
            );

            $evento = strtolower(trim($evento));

            if ($evento === 'cancelacion') {
                $asunto = 'Reserva cancelada - CESFAM';
                $titulo = 'Reserva cancelada';

                $intro = 'Se ha cancelado una reserva de su departamento.';
            } else {
                $asunto = 'Cambio de horario de reserva - CESFAM';
                $titulo = 'Reserva modificada';

                $intro = 'Se ha modificado el horario de una reserva de su departamento.';
            }

            $escape = static function ($valor): string {
                return htmlspecialchars(
                    (string) $valor,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
            };

            $filas = '';
            $texto = $titulo . "\n\n" . $intro . "\n\n";

            $detallesComunes = [
                ['Número de reserva', $idReserva],
                ['Departamento', $nombreDepartamento],
                ['Servicio', $nombreServicio],
                ['Paciente', $nombrePaciente],
            ];

            foreach (
                array_merge($detallesComunes, $detalles)
                as $par
            ) {
                $etiqueta = (string) ($par[0] ?? '');
                $valor = (string) ($par[1] ?? '');

                $filas .=
                    '<tr>' .
                    '<td style="padding:9px 12px;border-bottom:1px solid #e6eaf0;font-weight:bold;vertical-align:top;">' .
                    $escape($etiqueta) .
                    '</td>' .
                    '<td style="padding:9px 12px;border-bottom:1px solid #e6eaf0;vertical-align:top;">' .
                    $escape($valor) .
                    '</td>' .
                    '</tr>';

                $texto .= $etiqueta . ': ' . $valor . "\n";
            }

            foreach ($funcionarios as $funcionario) {
                $destino = trim(
                    (string) ($funcionario['correo'] ?? '')
                );

                if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                    error_log(
                        'Correo de funcionario inválido para funcionario #' .
                        (string) ($funcionario['id_funcionario'] ?? 'N/A')
                    );

                    continue;
                }

                try {
                    $mail = crearCorreo();

                    $nombreFuncionario = trim(
                        (string) ($funcionario['nombre'] ?? '') .
                        ' ' .
                        (string) ($funcionario['apellido'] ?? '')
                    );

                    $mail->addAddress(
                        $destino,
                        $nombreFuncionario
                    );

                    $mail->Subject = $asunto;

                    $mail->Body =
                        '<div style="font-family:Arial,Helvetica,sans-serif;max-width:650px;margin:0 auto;color:#273444;line-height:1.6">' .
                            '<div style="background:#087e8b;color:#fff;padding:22px 25px;border-radius:8px 8px 0 0">' .
                                '<h2 style="margin:0">' .
                                    $escape($titulo) .
                                '</h2>' .
                                '<p style="margin:5px 0 0">CESFAM Eduardo Raggio Catemu</p>' .
                            '</div>' .
                            '<div style="padding:22px 25px;border:1px solid #e6eaf0;border-top:0;border-radius:0 0 8px 8px">' .
                                '<p>Hola ' .
                                    $escape($nombreFuncionario) .
                                ':</p>' .
                                '<p>' .
                                    $escape($intro) .
                                '</p>' .
                                '<table role="presentation" style="width:100%;border-collapse:collapse;margin:20px 0">' .
                                    '<tbody>' .
                                        $filas .
                                    '</tbody>' .
                                '</table>' .
                                '<p style="font-size:12px;color:#667085;margin-top:28px">' .
                                    'Aviso automático del sistema de toma de horas del CESFAM.' .
                                '</p>' .
                            '</div>' .
                        '</div>';

                    $mail->AltBody =
                        $texto .
                        "\nAviso automático del sistema de toma de horas del CESFAM.";

                    $mail->send();

                    $enviados++;

                } catch (Throwable $e) {
                    error_log(
                        'Error notificando funcionario #' .
                        (string) ($funcionario['id_funcionario'] ?? 'N/A') .
                        ' por evento ' .
                        $evento .
                        ' para reserva #' .
                        $idReserva .
                        ': ' .
                        $e->getMessage()
                    );
                }
            }

            return $enviados;

        } catch (Throwable $e) {
            error_log(
                'Error general notificando funcionarios por evento de reserva: ' .
                $e->getMessage()
            );

            return 0;
        }
    }
}

/*
 * Alias compatibles con api/crear_reserva.php.
 */
if (!function_exists('enviarCorreoConfirmacionCesfam')) {
    function enviarCorreoConfirmacionCesfam(
        array $reserva,
        array $servicio,
        string $fecha,
        string $hora
    ): void {
        enviarCorreoConfirmacionReserva(
            $reserva,
            $servicio,
            $fecha,
            $hora
        );
    }
}

if (!function_exists('enviarCorreoReagendamientoCesfam')) {
    function enviarCorreoReagendamientoCesfam(
        array $reserva,
        array $servicio,
        string $fechaAnterior,
        string $horaAnterior,
        string $fechaNueva,
        string $horaNueva
    ): void {
        enviarCorreoModificacionReserva(
            $reserva,
            $servicio,
            $fechaAnterior,
            $horaAnterior,
            $fechaNueva,
            $horaNueva
        );
    }
}

if (!function_exists('enviarCorreoCancelacionCesfam')) {
    function enviarCorreoCancelacionCesfam(
        array $reserva,
        array $servicio,
        string $fecha,
        string $hora
    ): void {
        enviarCorreoCancelacionReserva(
            $reserva,
            $servicio,
            $fecha,
            $hora
        );
    }
}