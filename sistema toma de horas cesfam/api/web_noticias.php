
<?php
/*
 * =========================================================
 * API DE NOTICIAS - CESFAM EDUARDO RAGGIO CATEMU
 * Archivo: api/web_noticias.php
 *
 * Acciones GET:
 *   ?accion=listar
 *   ?accion=obtener&id=1
 *
 * Acciones POST:
 *   accion=guardar
 *   accion=cambiar_estado
 *   accion=enviar_papelera
 *   accion=restaurar
 *   accion=eliminar_definitivamente
 *
 * Las noticias se eliminan definitivamente solo desde
 * la papelera y después de validar la solicitud.
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../componentes/auth_administracion_web.php';

/*
 * =========================================================
 * RESPUESTAS
 * =========================================================
 */

function responderNoticias(
    bool $exito,
    string $mensaje,
    array $datos = [],
    int $codigoHttp = 200
): void {
    http_response_code($codigoHttp);

    echo json_encode(
        array_merge(
            $datos,
            [
                'ok'      => $exito,
                'success' => $exito,
                'exito'   => $exito,
                'mensaje' => $mensaje,
                'data'    => $datos,
                'datos'   => $datos
            ]
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function errorNoticias(
    string $mensaje,
    int $codigoHttp = 400,
    array $datos = []
): void {
    responderNoticias(false, $mensaje, $datos, $codigoHttp);
}

/*
 * =========================================================
 * FUNCIONES AUXILIARES
 * =========================================================
 */

function limpiarTextoNoticia($valor): string
{
    if (!is_string($valor) && !is_numeric($valor)) {
        return '';
    }

    return trim((string) $valor);
}

function convertirBooleanoNoticia($valor): bool
{
    return in_array(
        $valor,
        [true, 1, '1', 'true', 't', 'on', 'yes'],
        true
    );
}

function obtenerIdPositivo($valor): ?int
{
    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $id === false ? null : $id;
}

function validarTokenNoticias(): bool
{
    if (
        !isset($_SESSION['csrf_web_noticias'])
        || !is_string($_SESSION['csrf_web_noticias'])
    ) {
        return false;
    }

    $tokenRecibido = (string) (
        $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ''
    );

    return $tokenRecibido !== ''
        && hash_equals(
            $_SESSION['csrf_web_noticias'],
            $tokenRecibido
        );
}

function obtenerFuncionarioNoticias(): int
{
    return (int) ($_SESSION['id_funcionario'] ?? 0);
}

/*
 * =========================================================
 * CONFIGURACIÓN DE SESIÓN Y ACCIÓN
 * =========================================================
 */

if (empty($_SESSION['csrf_web_noticias'])) {
    $_SESSION['csrf_web_noticias'] = bin2hex(random_bytes(32));
}

$tokenCSRF = $_SESSION['csrf_web_noticias'];

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$accion = strtolower(
    trim(
        (string) (
            $_GET['accion']
            ?? $_POST['accion']
            ?? 'listar'
        )
    )
);

$idFuncionario = obtenerFuncionarioNoticias();

/*
 * =========================================================
 * ACCIÓN GET: LISTAR NOTICIAS
 * =========================================================
 */

if ($metodo === 'GET' && $accion === 'listar') {
    try {
        $pagina = max(
            1,
            (int) ($_GET['pagina'] ?? 1)
        );

        $porPagina = (int) ($_GET['por_pagina'] ?? 10);

        if (!in_array($porPagina, [10, 20, 50], true)) {
            $porPagina = 10;
        }

        $busqueda = limpiarTextoNoticia(
            $_GET['busqueda'] ?? ''
        );

        $estado = strtolower(
            limpiarTextoNoticia($_GET['estado'] ?? 'todas')
        );

        $destacada = strtolower(
            limpiarTextoNoticia($_GET['destacada'] ?? 'todas')
        );

        $estadosPermitidos = [
            'todas',
            'borrador',
            'publicada',
            'inactiva',
            'papelera'
        ];

        if (!in_array($estado, $estadosPermitidos, true)) {
            $estado = 'todas';
        }

        if (!in_array($destacada, ['todas', '1', '0'], true)) {
            $destacada = 'todas';
        }

        $condiciones = [];
        $parametros = [];

        if ($estado !== 'todas') {
            $condiciones[] = 'n.estado = :estado';
            $parametros[':estado'] = $estado;
        }

        /*
         * Se envían literales booleanos válidos para PostgreSQL.
         */
        if ($destacada !== 'todas') {
            $condiciones[] = 'n.destacada = :destacada';

            $parametros[':destacada'] = (
                $destacada === '1'
            ) ? 'true' : 'false';
        }

        if ($busqueda !== '') {
            $condiciones[] = "(
                n.titulo ILIKE :busqueda_titulo
                OR COALESCE(n.resumen, '') ILIKE :busqueda_resumen
                OR n.contenido ILIKE :busqueda_contenido
            )";

            $termino = '%' . $busqueda . '%';

            $parametros[':busqueda_titulo'] = $termino;
            $parametros[':busqueda_resumen'] = $termino;
            $parametros[':busqueda_contenido'] = $termino;
        }

        $where = !empty($condiciones)
            ? 'WHERE ' . implode(' AND ', $condiciones)
            : '';

        $sqlTotal = "
            SELECT COUNT(*)
            FROM public.web_noticias n
            $where
        ";

        $stmtTotal = $conexion->prepare($sqlTotal);
        $stmtTotal->execute($parametros);

        $total = (int) $stmtTotal->fetchColumn();

        $totalPaginas = max(
            1,
            (int) ceil($total / $porPagina)
        );

        if ($pagina > $totalPaginas) {
            $pagina = $totalPaginas;
        }

        $desplazamiento = ($pagina - 1) * $porPagina;

        $sqlNoticias = "
            SELECT
                n.id_noticia,
                n.titulo,
                n.resumen,
                n.destacada,
                n.estado,
                n.fecha_publicacion,
                n.fecha_creacion,
                n.fecha_actualizacion,
                n.fecha_papelera,
                n.estado_antes_papelera,
                n.id_multimedia,
                n.texto_alternativo_imagen,
                m.ruta_relativa AS imagen_ruta,
                m.nombre_original AS imagen_nombre,
                creador.nombre AS creador_nombre,
                creador.apellido AS creador_apellido,
                editor.nombre AS editor_nombre,
                editor.apellido AS editor_apellido
            FROM public.web_noticias n
            LEFT JOIN public.web_multimedia m
                ON m.id_multimedia = n.id_multimedia
            LEFT JOIN public.funcionarios creador
                ON creador.id_funcionario = n.creado_por
            LEFT JOIN public.funcionarios editor
                ON editor.id_funcionario = n.actualizado_por
            $where
            ORDER BY
                COALESCE(
                    n.fecha_publicacion,
                    n.fecha_creacion
                ) DESC,
                n.id_noticia DESC
            LIMIT :limite OFFSET :desplazamiento
        ";

        $stmt = $conexion->prepare($sqlNoticias);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor);
        }

        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(
            ':desplazamiento',
            $desplazamiento,
            PDO::PARAM_INT
        );

        $stmt->execute();

        $noticias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        responderNoticias(
            true,
            'Noticias consultadas correctamente.',
            [
                'noticias' => $noticias,
                'paginacion' => [
                    'pagina' => $pagina,
                    'por_pagina' => $porPagina,
                    'total' => $total,
                    'total_paginas' => $totalPaginas
                ],
                'filtros' => [
                    'busqueda' => $busqueda,
                    'estado' => $estado,
                    'destacada' => $destacada
                ],
                'csrf_token' => $tokenCSRF
            ]
        );
    } catch (Throwable $e) {
        error_log(
            'Error al listar noticias: ' . $e->getMessage()
        );

        errorNoticias(
            'No fue posible consultar las noticias.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN GET: OBTENER UNA NOTICIA
 * =========================================================
 */

if ($metodo === 'GET' && $accion === 'obtener') {
    $idNoticia = obtenerIdPositivo($_GET['id'] ?? null);

    if ($idNoticia === null) {
        errorNoticias('El identificador de la noticia no es válido.');
    }

    try {
        $sql = "
            SELECT
                n.id_noticia,
                n.titulo,
                n.resumen,
                n.contenido,
                n.destacada,
                n.estado,
                n.fecha_publicacion,
                n.fecha_creacion,
                n.fecha_actualizacion,
                n.fecha_papelera,
                n.estado_antes_papelera,
                n.id_multimedia,
                n.texto_alternativo_imagen,
                m.nombre_original AS imagen_nombre,
                m.ruta_relativa AS imagen_ruta
            FROM public.web_noticias n
            LEFT JOIN public.web_multimedia m
                ON m.id_multimedia = n.id_multimedia
            WHERE n.id_noticia = :id_noticia
            LIMIT 1
        ";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':id_noticia' => $idNoticia
        ]);

        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$noticia) {
            errorNoticias('La noticia solicitada no existe.', 404);
        }

        responderNoticias(
            true,
            'Noticia consultada correctamente.',
            [
                'noticia' => $noticia,
                'csrf_token' => $tokenCSRF
            ]
        );
    } catch (Throwable $e) {
        error_log(
            'Error al obtener noticia: ' . $e->getMessage()
        );

        errorNoticias(
            'No fue posible consultar la noticia.',
            500
        );
    }
}

/*
 * =========================================================
 * LAS DEMÁS ACCIONES REQUIEREN POST
 * =========================================================
 */

if ($metodo !== 'POST') {
    header('Allow: GET, POST');

    errorNoticias('Método HTTP no permitido.', 405);
}

if (!validarTokenNoticias()) {
    errorNoticias(
        'La sesión de seguridad venció o no es válida. Actualiza la página e inténtalo nuevamente.',
        403
    );
}

if ($idFuncionario <= 0) {
    errorNoticias(
        'No se pudo identificar al funcionario de la sesión.',
        401
    );
}

/*
 * =========================================================
 * ACCIÓN POST: GUARDAR NOTICIA
 * Crea o actualiza una noticia sin publicarla automáticamente.
 * =========================================================
 */

if ($accion === 'guardar') {
    $idNoticia = null;

    if (
        isset($_POST['id_noticia'])
        && $_POST['id_noticia'] !== ''
    ) {
        $idNoticia = obtenerIdPositivo($_POST['id_noticia']);

        if ($idNoticia === null) {
            errorNoticias(
                'El identificador de la noticia no es válido.'
            );
        }
    }

    $titulo = limpiarTextoNoticia($_POST['titulo'] ?? '');
    $resumen = limpiarTextoNoticia($_POST['resumen'] ?? '');
    $contenido = limpiarTextoNoticia($_POST['contenido'] ?? '');
    $textoAlternativo = limpiarTextoNoticia(
        $_POST['texto_alternativo_imagen'] ?? ''
    );

    if ($titulo === '') {
        errorNoticias('Debes ingresar el título de la noticia.');
    }

    if (mb_strlen($titulo, 'UTF-8') > 180) {
        errorNoticias(
            'El título no puede superar los 180 caracteres.'
        );
    }

    if (mb_strlen($resumen, 'UTF-8') > 500) {
        errorNoticias(
            'El resumen no puede superar los 500 caracteres.'
        );
    }

    if ($contenido === '') {
        errorNoticias(
            'Debes ingresar el contenido de la noticia.'
        );
    }

    if (mb_strlen($textoAlternativo, 'UTF-8') > 255) {
        errorNoticias(
            'El texto alternativo de la imagen no puede superar los 255 caracteres.'
        );
    }

    $idMultimedia = null;
    $valorImagen = $_POST['id_multimedia'] ?? '';

    if ($valorImagen !== '' && $valorImagen !== null) {
        $idMultimedia = obtenerIdPositivo($valorImagen);

        if ($idMultimedia === null) {
            errorNoticias(
                'La imagen seleccionada no es válida.'
            );
        }
    }

    $destacada = convertirBooleanoNoticia(
        $_POST['destacada'] ?? false
    );

    /*
     * PDO podría convertir false en una cadena vacía.
     * Enviamos un literal booleano explícito a PostgreSQL.
     */
    $destacadaSQL = $destacada ? 'true' : 'false';

    if ($idMultimedia === null) {
        $textoAlternativo = '';
    }

    try {
        $conexion->beginTransaction();

        /*
         * Comprobar que la imagen exista.
         */
        if ($idMultimedia !== null) {
            $stmtImagen = $conexion->prepare("
                SELECT id_multimedia
                FROM public.web_multimedia
                WHERE id_multimedia = :id_multimedia
                FOR KEY SHARE
            ");

            $stmtImagen->execute([
                ':id_multimedia' => $idMultimedia
            ]);

            if (!$stmtImagen->fetchColumn()) {
                $conexion->rollBack();

                errorNoticias(
                    'La imagen seleccionada ya no existe en la biblioteca multimedia.',
                    404
                );
            }
        }

        if ($idNoticia === null) {
            $sql = "
                INSERT INTO public.web_noticias (
                    titulo,
                    resumen,
                    contenido,
                    id_multimedia,
                    texto_alternativo_imagen,
                    destacada,
                    estado,
                    creado_por,
                    actualizado_por
                ) VALUES (
                    :titulo,
                    :resumen,
                    :contenido,
                    :id_multimedia,
                    :texto_alternativo,
                    :destacada,
                    'borrador',
                    :creado_por,
                    :actualizado_por
                )
                RETURNING id_noticia
            ";

            $stmt = $conexion->prepare($sql);

            $stmt->execute([
                ':titulo' => $titulo,
                ':resumen' => $resumen !== '' ? $resumen : null,
                ':contenido' => $contenido,
                ':id_multimedia' => $idMultimedia,
                ':texto_alternativo' =>
                    $textoAlternativo !== ''
                        ? $textoAlternativo
                        : null,
                ':destacada' => $destacadaSQL,
                ':creado_por' => $idFuncionario,
                ':actualizado_por' => $idFuncionario
            ]);

            $idNoticia = (int) $stmt->fetchColumn();
            $mensaje = 'Noticia creada como borrador.';
        } else {
            $stmtExiste = $conexion->prepare("
                SELECT id_noticia
                FROM public.web_noticias
                WHERE id_noticia = :id_noticia
                  AND estado <> 'papelera'
                FOR UPDATE
            ");

            $stmtExiste->execute([
                ':id_noticia' => $idNoticia
            ]);

            if (!$stmtExiste->fetchColumn()) {
                $conexion->rollBack();

                errorNoticias(
                    'La noticia no existe o está en la papelera.',
                    404
                );
            }

            $sql = "
                UPDATE public.web_noticias
                SET
                    titulo = :titulo,
                    resumen = :resumen,
                    contenido = :contenido,
                    id_multimedia = :id_multimedia,
                    texto_alternativo_imagen = :texto_alternativo,
                    destacada = :destacada,
                    fecha_actualizacion = CURRENT_TIMESTAMP,
                    actualizado_por = :actualizado_por
                WHERE id_noticia = :id_noticia
            ";

            $stmt = $conexion->prepare($sql);

            $stmt->execute([
                ':titulo' => $titulo,
                ':resumen' => $resumen !== '' ? $resumen : null,
                ':contenido' => $contenido,
                ':id_multimedia' => $idMultimedia,
                ':texto_alternativo' =>
                    $textoAlternativo !== ''
                        ? $textoAlternativo
                        : null,
                ':destacada' => $destacadaSQL,
                ':actualizado_por' => $idFuncionario,
                ':id_noticia' => $idNoticia
            ]);

            $mensaje = 'Noticia actualizada correctamente.';
        }

        /*
         * Registrar o retirar la referencia de la imagen.
         */
        if ($idMultimedia !== null) {
            $sqlReferencia = "
                INSERT INTO public.web_multimedia_referencias (
                    id_multimedia,
                    modulo,
                    id_contenido,
                    campo
                ) VALUES (
                    :id_multimedia,
                    'noticias',
                    :id_contenido,
                    'imagen'
                )
                ON CONFLICT (modulo, id_contenido, campo)
                DO UPDATE SET
                    id_multimedia = EXCLUDED.id_multimedia,
                    fecha_referencia = CURRENT_TIMESTAMP
            ";

            $stmtReferencia = $conexion->prepare($sqlReferencia);

            $stmtReferencia->execute([
                ':id_multimedia' => $idMultimedia,
                ':id_contenido' => $idNoticia
            ]);
        } else {
            $stmtReferencia = $conexion->prepare("
                DELETE FROM public.web_multimedia_referencias
                WHERE modulo = 'noticias'
                  AND id_contenido = :id_contenido
                  AND campo = 'imagen'
            ");

            $stmtReferencia->execute([
                ':id_contenido' => $idNoticia
            ]);
        }

        $conexion->commit();

        responderNoticias(
            true,
            $mensaje,
            [
                'id_noticia' => $idNoticia
            ]
        );
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al guardar noticia: ' . $e->getMessage()
        );

        errorNoticias(
            'No fue posible guardar la noticia.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN POST: CAMBIAR ESTADO
 * =========================================================
 */

if ($accion === 'cambiar_estado') {
    $idNoticia = obtenerIdPositivo(
        $_POST['id_noticia'] ?? null
    );

    $nuevoEstado = strtolower(
        limpiarTextoNoticia($_POST['estado'] ?? '')
    );

    $estadosPermitidos = [
        'publicada',
        'inactiva',
        'borrador'
    ];

    if ($idNoticia === null) {
        errorNoticias(
            'El identificador de la noticia no es válido.'
        );
    }

    if (!in_array($nuevoEstado, $estadosPermitidos, true)) {
        errorNoticias(
            'El estado solicitado no es válido.'
        );
    }

    try {
        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            SELECT estado
            FROM public.web_noticias
            WHERE id_noticia = :id_noticia
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_noticia' => $idNoticia
        ]);

        $estadoActual = $stmt->fetchColumn();

        if ($estadoActual === false) {
            $conexion->rollBack();

            errorNoticias(
                'La noticia solicitada no existe.',
                404
            );
        }

        if ($estadoActual === 'papelera') {
            $conexion->rollBack();

            errorNoticias(
                'Primero debes recuperar la noticia de la papelera.',
                409
            );
        }

        $sql = "
            UPDATE public.web_noticias
            SET
                estado = :estado,
                fecha_publicacion = CASE
                    WHEN :estado_fecha = 'publicada'
                    THEN COALESCE(
                        fecha_publicacion,
                        CURRENT_TIMESTAMP
                    )
                    ELSE fecha_publicacion
                END,
                fecha_actualizacion = CURRENT_TIMESTAMP,
                actualizado_por = :actualizado_por
            WHERE id_noticia = :id_noticia
        ";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':estado' => $nuevoEstado,
            ':estado_fecha' => $nuevoEstado,
            ':actualizado_por' => $idFuncionario,
            ':id_noticia' => $idNoticia
        ]);

        $conexion->commit();

        $mensajes = [
            'publicada' => 'Noticia publicada correctamente.',
            'inactiva' => 'Noticia desactivada correctamente.',
            'borrador' => 'La noticia volvió a estado borrador.'
        ];

        responderNoticias(
            true,
            $mensajes[$nuevoEstado],
            [
                'id_noticia' => $idNoticia,
                'estado' => $nuevoEstado
            ]
        );
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al cambiar estado de noticia: '
            . $e->getMessage()
        );

        errorNoticias(
            'No fue posible cambiar el estado de la noticia.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN POST: ENVIAR A PAPELERA
 * =========================================================
 */

if ($accion === 'enviar_papelera') {
    $idNoticia = obtenerIdPositivo(
        $_POST['id_noticia'] ?? null
    );

    if ($idNoticia === null) {
        errorNoticias(
            'El identificador de la noticia no es válido.'
        );
    }

    try {
        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            SELECT estado
            FROM public.web_noticias
            WHERE id_noticia = :id_noticia
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_noticia' => $idNoticia
        ]);

        $estadoActual = $stmt->fetchColumn();

        if ($estadoActual === false) {
            $conexion->rollBack();

            errorNoticias(
                'La noticia solicitada no existe.',
                404
            );
        }

        if ($estadoActual === 'papelera') {
            $conexion->rollBack();

            errorNoticias(
                'La noticia ya se encuentra en la papelera.',
                409
            );
        }

        $stmt = $conexion->prepare("
            UPDATE public.web_noticias
            SET
                estado_antes_papelera = estado,
                estado = 'papelera',
                fecha_papelera = CURRENT_TIMESTAMP,
                papelera_por = :papelera_por,
                fecha_actualizacion = CURRENT_TIMESTAMP,
                actualizado_por = :actualizado_por
            WHERE id_noticia = :id_noticia
        ");

        $stmt->execute([
            ':papelera_por' => $idFuncionario,
            ':actualizado_por' => $idFuncionario,
            ':id_noticia' => $idNoticia
        ]);

        $conexion->commit();

        responderNoticias(
            true,
            'Noticia enviada a la papelera. Puedes recuperarla más adelante.',
            [
                'id_noticia' => $idNoticia,
                'estado' => 'papelera'
            ]
        );
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al enviar noticia a papelera: '
            . $e->getMessage()
        );

        errorNoticias(
            'No fue posible enviar la noticia a la papelera.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN POST: RESTAURAR DESDE PAPELERA
 * =========================================================
 */

if ($accion === 'restaurar') {
    $idNoticia = obtenerIdPositivo(
        $_POST['id_noticia'] ?? null
    );

    if ($idNoticia === null) {
        errorNoticias(
            'El identificador de la noticia no es válido.'
        );
    }

    try {
        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            SELECT estado, estado_antes_papelera
            FROM public.web_noticias
            WHERE id_noticia = :id_noticia
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_noticia' => $idNoticia
        ]);

        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$noticia) {
            $conexion->rollBack();

            errorNoticias(
                'La noticia solicitada no existe.',
                404
            );
        }

        if ($noticia['estado'] !== 'papelera') {
            $conexion->rollBack();

            errorNoticias(
                'La noticia no se encuentra en la papelera.',
                409
            );
        }

        $estadoAnterior = $noticia['estado_antes_papelera'];

        if (
            !in_array(
                $estadoAnterior,
                ['borrador', 'publicada', 'inactiva'],
                true
            )
        ) {
            $estadoAnterior = 'borrador';
        }

        $stmt = $conexion->prepare("
            UPDATE public.web_noticias
            SET
                estado = :estado,
                estado_antes_papelera = NULL,
                fecha_papelera = NULL,
                papelera_por = NULL,
                fecha_actualizacion = CURRENT_TIMESTAMP,
                actualizado_por = :actualizado_por
            WHERE id_noticia = :id_noticia
        ");

        $stmt->execute([
            ':estado' => $estadoAnterior,
            ':actualizado_por' => $idFuncionario,
            ':id_noticia' => $idNoticia
        ]);

        $conexion->commit();

        responderNoticias(
            true,
            'Noticia recuperada correctamente.',
            [
                'id_noticia' => $idNoticia,
                'estado' => $estadoAnterior
            ]
        );
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al restaurar noticia: '
            . $e->getMessage()
        );

        errorNoticias(
            'No fue posible recuperar la noticia.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN POST: ELIMINAR DEFINITIVAMENTE
 *
 * Medidas de protección:
 * - Requiere sesión autenticada y token CSRF válido.
 * - Exige un ID positivo.
 * - Solo elimina noticias que estén en la papelera.
 * - Borra primero sus referencias multimedia.
 * - Ejecuta todo dentro de una transacción.
 * =========================================================
 */

if ($accion === 'eliminar_definitivamente') {
    $idNoticia = obtenerIdPositivo(
        $_POST['id_noticia'] ?? null
    );

    if ($idNoticia === null) {
        errorNoticias(
            'El identificador de la noticia no es válido.'
        );
    }

    try {
        $conexion->beginTransaction();

        /*
         * Bloquear la noticia mientras se comprueba y elimina.
         */
        $stmt = $conexion->prepare("
            SELECT id_noticia, estado, titulo
            FROM public.web_noticias
            WHERE id_noticia = :id_noticia
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_noticia' => $idNoticia
        ]);

        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$noticia) {
            $conexion->rollBack();

            errorNoticias(
                'La noticia que intentas eliminar no existe.',
                404
            );
        }

        /*
         * Evitar borrar directamente una noticia publicada,
         * inactiva o un borrador que no pasó por la papelera.
         */
        if ($noticia['estado'] !== 'papelera') {
            $conexion->rollBack();

            errorNoticias(
                'Solo se pueden eliminar definitivamente las noticias que están en la papelera.',
                409
            );
        }

        /*
         * Primero eliminar la referencia registrada a la imagen.
         * La imagen de la biblioteca multimedia no se elimina.
         */
        $stmtReferencias = $conexion->prepare("
            DELETE FROM public.web_multimedia_referencias
            WHERE modulo = 'noticias'
              AND id_contenido = :id_contenido
        ");

        $stmtReferencias->execute([
            ':id_contenido' => $idNoticia
        ]);

        /*
         * Eliminar definitivamente la noticia.
         */
        $stmtEliminar = $conexion->prepare("
            DELETE FROM public.web_noticias
            WHERE id_noticia = :id_noticia
              AND estado = 'papelera'
        ");

        $stmtEliminar->execute([
            ':id_noticia' => $idNoticia
        ]);

        if ($stmtEliminar->rowCount() !== 1) {
            throw new RuntimeException(
                'No se pudo confirmar la eliminación de la noticia.'
            );
        }

        $conexion->commit();

        responderNoticias(
            true,
            'La noticia fue eliminada definitivamente.',
            [
                'id_noticia' => $idNoticia
            ]
        );
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al eliminar definitivamente noticia: '
            . $e->getMessage()
        );

        errorNoticias(
            'No fue posible eliminar definitivamente la noticia.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN DESCONOCIDA
 * =========================================================
 */

errorNoticias('La acción solicitada no existe.', 400);
