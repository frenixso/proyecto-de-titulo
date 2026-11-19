
<?php
/*
 * =========================================================
 * API: BIBLIOTECA MULTIMEDIA DE LA PÁGINA PÚBLICA
 * Archivo: api/web_multimedia.php
 *
 * Acciones:
 *   GET  ?accion=listar
 *   POST accion=subir
 *   POST accion=eliminar
 *
 * Seguridad:
 *   - Requiere permiso de administración web.
 *   - Protección CSRF.
 *   - Validación del tipo y tamaño de las imágenes.
 *   - Impide eliminar imágenes vinculadas a contenido.
 *   - Comprueba las rutas de archivo antes de eliminarlas.
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../componentes/auth_administracion_web.php';

/*
 * =========================================================
 * RESPUESTAS JSON
 * =========================================================
 */

function responderMultimedia(
    bool $exito,
    string $mensaje,
    array $datos = [],
    int $codigoHttp = 200
) {
    http_response_code($codigoHttp);

    echo json_encode(
        array_merge(
            [
                'ok'      => $exito,
                'success' => $exito,
                'exito'   => $exito,
                'mensaje' => $mensaje,
                'data'    => $datos,
                'datos'   => $datos
            ],
            $datos
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function responderErrorMultimedia(
    string $mensaje,
    int $codigoHttp = 400,
    array $datos = []
) {
    responderMultimedia(false, $mensaje, $datos, $codigoHttp);
}

/*
 * =========================================================
 * CONFIGURACIÓN
 * =========================================================
 */

$directorioMultimedia = dirname(__DIR__) . '/uploads/multimedia';
$rutaRelativaBase = 'uploads/multimedia/';

$limiteBytes = 5 * 1024 * 1024;

$tiposPermitidos = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif'
];

if (!isset($_SESSION['csrf_web_multimedia'])) {
    $_SESSION['csrf_web_multimedia'] = bin2hex(random_bytes(32));
}

$tokenCSRF = $_SESSION['csrf_web_multimedia'];

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

/*
 * =========================================================
 * VALIDAR TOKEN CSRF
 * =========================================================
 */

function validarTokenCSRF(string $tokenEsperado): bool
{
    $tokenRecibido = '';

    if (isset($_POST['csrf_token'])) {
        $tokenRecibido = (string) $_POST['csrf_token'];
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $tokenRecibido = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
    }

    return $tokenRecibido !== ''
        && hash_equals($tokenEsperado, $tokenRecibido);
}

/*
 * =========================================================
 * ACCIÓN: LISTAR IMÁGENES
 * =========================================================
 */

if ($metodo === 'GET' && $accion === 'listar') {
    try {
        $sql = "
            SELECT
                id_multimedia,
                nombre_original,
                nombre_archivo,
                ruta_relativa,
                tipo_mime,
                tamano_bytes,
                texto_alternativo,
                id_funcionario,
                fecha_subida
            FROM public.web_multimedia
            ORDER BY fecha_subida DESC, id_multimedia DESC
            LIMIT 500
        ";

        $stmt = $conexion->query($sql);
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        responderMultimedia(
            true,
            'Biblioteca multimedia cargada correctamente.',
            [
                'imagenes' => $imagenes,
                'csrf_token' => $tokenCSRF
            ]
        );
    } catch (Throwable $e) {
        error_log(
            'Error al listar la biblioteca multimedia: '
            . $e->getMessage()
        );

        responderErrorMultimedia(
            'No fue posible cargar la biblioteca multimedia.',
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

    responderErrorMultimedia(
        'Método HTTP no permitido.',
        405
    );
}

if (!validarTokenCSRF($tokenCSRF)) {
    responderErrorMultimedia(
        'La sesión de seguridad no es válida. Actualiza la página e inténtalo nuevamente.',
        403
    );
}

/*
 * =========================================================
 * ACCIÓN: SUBIR IMAGEN
 * =========================================================
 */

if ($accion === 'subir') {
    if (
        !isset($_FILES['archivo'])
        || !is_array($_FILES['archivo'])
    ) {
        responderErrorMultimedia(
            'No se recibió ninguna imagen.'
        );
    }

    $archivo = $_FILES['archivo'];

    if (
        !isset($archivo['error'])
        || is_array($archivo['error'])
    ) {
        responderErrorMultimedia(
            'Los datos del archivo recibido no son válidos.'
        );
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $mensajesError = [
            UPLOAD_ERR_INI_SIZE =>
                'La imagen supera el tamaño máximo permitido por PHP.',
            UPLOAD_ERR_FORM_SIZE =>
                'La imagen supera el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL =>
                'La imagen se recibió de forma incompleta.',
            UPLOAD_ERR_NO_FILE =>
                'Selecciona una imagen antes de continuar.',
            UPLOAD_ERR_NO_TMP_DIR =>
                'El servidor no tiene disponible la carpeta temporal.',
            UPLOAD_ERR_CANT_WRITE =>
                'El servidor no pudo guardar el archivo.',
            UPLOAD_ERR_EXTENSION =>
                'Una extensión de PHP impidió la carga.'
        ];

        responderErrorMultimedia(
            $mensajesError[$archivo['error']]
                ?? 'Ocurrió un error al recibir la imagen.'
        );
    }

    $tamano = (int) ($archivo['size'] ?? 0);
    $rutaTemporal = (string) ($archivo['tmp_name'] ?? '');

    if ($tamano <= 0) {
        responderErrorMultimedia(
            'La imagen está vacía o no tiene un tamaño válido.'
        );
    }

    if ($tamano > $limiteBytes) {
        responderErrorMultimedia(
            'La imagen supera el límite permitido de 5 MB.'
        );
    }

    if (
        $rutaTemporal === ''
        || !is_uploaded_file($rutaTemporal)
    ) {
        responderErrorMultimedia(
            'El archivo recibido no es una carga válida.'
        );
    }

    if (!is_dir($directorioMultimedia)) {
        responderErrorMultimedia(
            'No existe la carpeta de almacenamiento de imágenes.',
            500
        );
    }

    if (!is_writable($directorioMultimedia)) {
        responderErrorMultimedia(
            'El servidor no tiene permisos para guardar imágenes.',
            500
        );
    }

    /*
     * Comprobar el MIME real del archivo.
     */
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if ($finfo === false) {
        responderErrorMultimedia(
            'No fue posible comprobar el tipo de imagen.',
            500
        );
    }

    $tipoMime = finfo_file($finfo, $rutaTemporal);
    finfo_close($finfo);

    if (
        !is_string($tipoMime)
        || !isset($tiposPermitidos[$tipoMime])
    ) {
        responderErrorMultimedia(
            'Formato no permitido. Utiliza JPG, PNG, WEBP o GIF.'
        );
    }

    /*
     * Verificar que el archivo realmente sea una imagen.
     */
    $datosImagen = @getimagesize($rutaTemporal);

    if ($datosImagen === false) {
        responderErrorMultimedia(
            'El archivo recibido no es una imagen válida.'
        );
    }

    $ancho = (int) ($datosImagen[0] ?? 0);
    $alto = (int) ($datosImagen[1] ?? 0);

    if (
        $ancho <= 0
        || $alto <= 0
        || $ancho > 12000
        || $alto > 12000
        || ($ancho * $alto) > 40000000
    ) {
        responderErrorMultimedia(
            'Las dimensiones de la imagen superan los límites permitidos.'
        );
    }

    /*
     * Nombre aleatorio para evitar colisiones y nombres inseguros.
     */
    try {
        $nombreArchivo = bin2hex(random_bytes(24))
            . '.'
            . $tiposPermitidos[$tipoMime];
    } catch (Throwable $e) {
        responderErrorMultimedia(
            'No fue posible preparar el nombre de la imagen.',
            500
        );
    }

    $rutaRelativa = $rutaRelativaBase . $nombreArchivo;
    $rutaFinal = $directorioMultimedia
        . DIRECTORY_SEPARATOR
        . $nombreArchivo;

    $nombreOriginal = trim(
        (string) ($archivo['name'] ?? 'imagen')
    );

    $nombreOriginal = basename(
        str_replace('\\', '/', $nombreOriginal)
    );

    if ($nombreOriginal === '') {
        $nombreOriginal = 'imagen';
    }

    $nombreOriginal = mb_substr(
        $nombreOriginal,
        0,
        255,
        'UTF-8'
    );

    $textoAlternativo = trim(
        (string) ($_POST['texto_alternativo'] ?? '')
    );

    if ($textoAlternativo === '') {
        $textoAlternativo = null;
    } else {
        $textoAlternativo = mb_substr(
            $textoAlternativo,
            0,
            255,
            'UTF-8'
        );
    }

    if (!move_uploaded_file($rutaTemporal, $rutaFinal)) {
        responderErrorMultimedia(
            'No fue posible guardar la imagen en el servidor.',
            500
        );
    }

    try {
        $sql = "
            INSERT INTO public.web_multimedia (
                nombre_original,
                nombre_archivo,
                ruta_relativa,
                tipo_mime,
                tamano_bytes,
                texto_alternativo,
                id_funcionario
            ) VALUES (
                :nombre_original,
                :nombre_archivo,
                :ruta_relativa,
                :tipo_mime,
                :tamano_bytes,
                :texto_alternativo,
                :id_funcionario
            )
            RETURNING
                id_multimedia,
                nombre_original,
                nombre_archivo,
                ruta_relativa,
                tipo_mime,
                tamano_bytes,
                texto_alternativo,
                id_funcionario,
                fecha_subida
        ";

        $stmt = $conexion->prepare($sql);

        $stmt->execute([
            ':nombre_original' => $nombreOriginal,
            ':nombre_archivo' => $nombreArchivo,
            ':ruta_relativa' => $rutaRelativa,
            ':tipo_mime' => $tipoMime,
            ':tamano_bytes' => $tamano,
            ':texto_alternativo' => $textoAlternativo,
            ':id_funcionario' =>
                (int) ($_SESSION['id_funcionario'] ?? 0)
        ]);

        $imagenGuardada = $stmt->fetch(PDO::FETCH_ASSOC);

        responderMultimedia(
            true,
            'La imagen se subió correctamente.',
            [
                'imagen' => $imagenGuardada
            ]
        );
    } catch (Throwable $e) {
        /*
         * Si PostgreSQL falla, borrar el archivo recién subido
         * para no dejar archivos huérfanos.
         */
        if (is_file($rutaFinal)) {
            @unlink($rutaFinal);
        }

        error_log(
            'Error al guardar imagen en la base de datos: '
            . $e->getMessage()
        );

        responderErrorMultimedia(
            'La imagen no pudo registrarse en la base de datos.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN: ELIMINAR IMAGEN
 * =========================================================
 */

if ($accion === 'eliminar') {
    $idMultimedia = filter_var(
        $_POST['id_multimedia'] ?? null,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'min_range' => 1
            ]
        ]
    );

    if ($idMultimedia === false || $idMultimedia === null) {
        responderErrorMultimedia(
            'El identificador de la imagen no es válido.'
        );
    }

    $rutaOriginal = null;
    $rutaTemporalEliminacion = null;
    $transaccionIniciada = false;
    $eliminacionConfirmada = false;

    try {
        /*
         * Bloquear el registro mientras se comprueba y elimina.
         * Esto evita que se elimine si otra operación intenta
         * vincular la imagen al contenido al mismo tiempo.
         */
        $conexion->beginTransaction();
        $transaccionIniciada = true;

        $stmtImagen = $conexion->prepare("
            SELECT
                id_multimedia,
                nombre_archivo,
                ruta_relativa
            FROM public.web_multimedia
            WHERE id_multimedia = :id_multimedia
            FOR UPDATE
        ");

        $stmtImagen->execute([
            ':id_multimedia' => $idMultimedia
        ]);

        $imagen = $stmtImagen->fetch(PDO::FETCH_ASSOC);

        if (!$imagen) {
            $conexion->rollBack();
            $transaccionIniciada = false;

            responderErrorMultimedia(
                'La imagen seleccionada no existe en la biblioteca.',
                404
            );
        }

        /*
         * Comprobar que ningún contenido utilice esta imagen.
         */
        $stmtReferencias = $conexion->prepare("
            SELECT DISTINCT modulo
            FROM public.web_multimedia_referencias
            WHERE id_multimedia = :id_multimedia
            ORDER BY modulo
        ");

        $stmtReferencias->execute([
            ':id_multimedia' => $idMultimedia
        ]);

        $modulosReferenciados = $stmtReferencias->fetchAll(
            PDO::FETCH_COLUMN
        );

        if (!empty($modulosReferenciados)) {
            $conexion->rollBack();
            $transaccionIniciada = false;

            $nombresModulos = [
                'noticias' => 'noticias',
                'slider' => 'slider',
                'banners' => 'banners',
                'institucional' => 'información institucional',
                'tutoriales' => 'tutoriales'
            ];

            $modulosLegibles = [];

            foreach ($modulosReferenciados as $modulo) {
                $modulosLegibles[] =
                    $nombresModulos[$modulo] ?? $modulo;
            }

            responderErrorMultimedia(
                'No se puede eliminar esta imagen porque está utilizada en: '
                . implode(', ', $modulosLegibles)
                . '. Primero debes quitarla de ese contenido.',
                409,
                [
                    'modulos' => $modulosLegibles
                ]
            );
        }

        /*
         * Comprobar que la imagen solamente pueda borrarse
         * desde la carpeta de multimedia esperada.
         */
        $nombreArchivo = (string) $imagen['nombre_archivo'];
        $rutaRelativaGuardada = (string) $imagen['ruta_relativa'];

        if (
            !preg_match(
                '/^[a-f0-9]{48}\.(jpg|png|webp|gif)$/i',
                $nombreArchivo
            )
            || $rutaRelativaGuardada !==
                $rutaRelativaBase . $nombreArchivo
        ) {
            throw new RuntimeException(
                'La ruta almacenada para la imagen no es válida.'
            );
        }

        $directorioReal = realpath($directorioMultimedia);

        if (
            $directorioReal === false
            || !is_dir($directorioReal)
        ) {
            throw new RuntimeException(
                'No se pudo verificar la carpeta de multimedia.'
            );
        }

        $rutaOriginal = $directorioReal
            . DIRECTORY_SEPARATOR
            . $nombreArchivo;

        /*
         * Se rechazan enlaces simbólicos y rutas que salgan
         * de la carpeta de multimedia.
         */
        if (is_link($rutaOriginal)) {
            throw new RuntimeException(
                'La ruta de la imagen no es segura.'
            );
        }

        if (file_exists($rutaOriginal)) {
            $rutaArchivoReal = realpath($rutaOriginal);

            if (
                $rutaArchivoReal === false
                || dirname($rutaArchivoReal) !== $directorioReal
                || !is_file($rutaArchivoReal)
            ) {
                throw new RuntimeException(
                    'La ruta física de la imagen no es segura.'
                );
            }

            /*
             * Mover temporalmente el archivo. Si falla la
             * transacción, se intentará restaurar su nombre.
             */
            $rutaTemporalEliminacion = $directorioReal
                . DIRECTORY_SEPARATOR
                . '.eliminando-'
                . bin2hex(random_bytes(16))
                . '.tmp';

            if (!rename($rutaOriginal, $rutaTemporalEliminacion)) {
                throw new RuntimeException(
                    'No se pudo preparar la imagen para eliminarla.'
                );
            }
        }

        /*
         * La clave foránea también impide borrar una imagen
         * si existe una referencia registrada.
         */
        $stmtEliminar = $conexion->prepare("
            DELETE FROM public.web_multimedia
            WHERE id_multimedia = :id_multimedia
        ");

        $stmtEliminar->execute([
            ':id_multimedia' => $idMultimedia
        ]);

        if ($stmtEliminar->rowCount() !== 1) {
            throw new RuntimeException(
                'No se pudo eliminar el registro de la imagen.'
            );
        }

        $conexion->commit();
        $transaccionIniciada = false;
        $eliminacionConfirmada = true;

        /*
         * La fila de PostgreSQL ya se eliminó correctamente.
         * Borrar el archivo temporal que quedó en disco.
         */
        if (
            $rutaTemporalEliminacion !== null
            && is_file($rutaTemporalEliminacion)
            && !@unlink($rutaTemporalEliminacion)
        ) {
            error_log(
                'No se pudo borrar el archivo temporal: '
                . $rutaTemporalEliminacion
            );

            responderMultimedia(
                true,
                'La imagen se eliminó de la biblioteca, pero el servidor no pudo retirar un archivo temporal.',
                [
                    'id_multimedia' => (int) $idMultimedia,
                    'advertencia' => true
                ]
            );
        }

        responderMultimedia(
            true,
            'La imagen se eliminó correctamente.',
            [
                'id_multimedia' => (int) $idMultimedia
            ]
        );
    } catch (Throwable $e) {
        if (
            $transaccionIniciada
            && $conexion->inTransaction()
        ) {
            try {
                $conexion->rollBack();
            } catch (Throwable $errorRollback) {
                error_log(
                    'Error al revertir la eliminación multimedia: '
                    . $errorRollback->getMessage()
                );
            }
        }

        /*
         * Si el archivo se movió pero la eliminación no quedó
         * confirmada, devolverlo a su ubicación original.
         */
        if (
            !$eliminacionConfirmada
            && $rutaTemporalEliminacion !== null
            && is_file($rutaTemporalEliminacion)
            && $rutaOriginal !== null
            && !file_exists($rutaOriginal)
        ) {
            if (!@rename($rutaTemporalEliminacion, $rutaOriginal)) {
                error_log(
                    'ATENCIÓN: no se pudo restaurar la imagen: '
                    . $rutaTemporalEliminacion
                );
            }
        }

        error_log(
            'Error al eliminar imagen multimedia: '
            . $e->getMessage()
        );

        responderErrorMultimedia(
            'No fue posible eliminar la imagen. No se confirmó la eliminación; revisa el registro del servidor si el problema continúa.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN DESCONOCIDA
 * =========================================================
 */

responderErrorMultimedia(
    'La acción solicitada no existe.',
    400
);
