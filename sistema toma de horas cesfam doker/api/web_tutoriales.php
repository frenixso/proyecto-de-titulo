<?php
/*
 * =========================================================
 * API DE TUTORIALES
 * CESFAM EDUARDO RAGGIO CATEMU
 *
 * Archivo: api/web_tutoriales.php
 *
 * GET:
 *   ?accion=listar
 *   ?accion=obtener&id=1
 *   ?accion=categorias
 *
 * POST:
 *   accion=guardar
 *   accion=cambiar_estado
 *   accion=enviar_papelera
 *   accion=restaurar
 *   accion=eliminar_definitivamente
 *   accion=guardar_categoria
 *   accion=cambiar_estado_categoria
 *
 * Seguridad:
 *   - Requiere autenticación y permiso de administración web.
 *   - Protección CSRF.
 *   - Consultas preparadas mediante PDO.
 *   - Validación de archivos de video mediante MIME real.
 *   - Acepta el código iframe de YouTube sin almacenar HTML.
 *   - Valida los enlaces de YouTube en el servidor.
 *   - La eliminación definitiva solo se permite desde la papelera.
 * =========================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../componentes/auth_administracion_web.php';

/*
 * La conexión principal del proyecto utiliza $conexion.
 * Solo se carga la configuración si la autenticación no la incluyó.
 */
if (!isset($conexion) || !($conexion instanceof PDO)) {
    require_once __DIR__ . '/../config/conexion.php';
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 * =========================================================
 * RESPUESTAS JSON
 * =========================================================
 */

function responderTutoriales(
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
                'ok' => $exito,
                'success' => $exito,
                'exito' => $exito,
                'mensaje' => $mensaje,
                'data' => $datos,
                'datos' => $datos
            ]
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function errorTutoriales(
    string $mensaje,
    int $codigoHttp = 400
): void {
    responderTutoriales(false, $mensaje, [], $codigoHttp);
}

/*
 * =========================================================
 * UTILIDADES
 * =========================================================
 */

function limpiarTextoTutorial($valor): string
{
    if (!is_string($valor) && !is_numeric($valor)) {
        return '';
    }

    return trim((string) $valor);
}

function obtenerIdTutorialPositivo($valor): ?int
{
    $id = filter_var(
        $valor,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    return $id === false ? null : $id;
}

function esBooleanoTutorial($valor): bool
{
    return in_array(
        $valor,
        [true, 1, '1', 'true', 't', 'on', 'yes'],
        true
    );
}

function obtenerFuncionarioTutorial(): int
{
    return (int) ($_SESSION['id_funcionario'] ?? 0);
}

/*
 * =========================================================
 * PROTECCIÓN CSRF
 * =========================================================
 */

function validarTokenTutoriales(): bool
{
    if (
        !isset($_SESSION['csrf_web_tutoriales'])
        || !is_string($_SESSION['csrf_web_tutoriales'])
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
            $_SESSION['csrf_web_tutoriales'],
            $tokenRecibido
        );
}

if (empty($_SESSION['csrf_web_tutoriales'])) {
    $_SESSION['csrf_web_tutoriales'] = bin2hex(random_bytes(32));
}

$tokenCSRF = $_SESSION['csrf_web_tutoriales'];

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$accion = strtolower(
    limpiarTextoTutorial(
        $_GET['accion']
        ?? $_POST['accion']
        ?? 'listar'
    )
);

$idFuncionario = obtenerFuncionarioTutorial();

/*
 * =========================================================
 * AUDITORÍA
 * =========================================================
 */

function registrarCambioTutorial(
    PDO $conexion,
    int $idFuncionario,
    string $accion,
    string $tabla,
    int $idAfectado,
    string $descripcion
): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if (
        !is_string($ip)
        || filter_var($ip, FILTER_VALIDATE_IP) === false
    ) {
        $ip = null;
    }

    $sql = "
        INSERT INTO public.registro_cambios (
            id_funcionario,
            accion,
            tabla_afectada,
            id_registro_afectado,
            descripcion,
            direccion_ip
        ) VALUES (
            :funcionario,
            :accion,
            :tabla,
            :id_afectado,
            :descripcion,
            :ip
        )
    ";

    $stmt = $conexion->prepare($sql);

    $stmt->execute([
        ':funcionario' => $idFuncionario,
        ':accion' => $accion,
        ':tabla' => $tabla,
        ':id_afectado' => $idAfectado,
        ':descripcion' => $descripcion,
        ':ip' => $ip
    ]);
}

/*
 * =========================================================
 * OBTENER ID DE YOUTUBE
 *
 * Acepta:
 *   - Código iframe de YouTube.
 *   - URL normal de un video de YouTube.
 *   - URL corta de youtu.be.
 *   - URL /embed/, /shorts/ y /live/.
 *
 * Seguridad:
 *   - Solo acepta dominios autorizados.
 *   - Exige HTTPS.
 *   - Rechaza credenciales y puertos personalizados.
 *   - Si recibe un iframe, acepta únicamente /embed/ID.
 *   - Nunca guarda el HTML proporcionado por el usuario.
 * =========================================================
 */

function obtenerIdYouTubeTutorial(string $entrada): ?string
{
    $entrada = trim($entrada);

    /*
     * Evitar entradas excesivamente grandes.
     */
    if ($entrada === '' || strlen($entrada) > 5000) {
        return null;
    }

    $esIframe = stripos($entrada, '<iframe') !== false;
    $url = $entrada;

    /*
     * Si el usuario pegó el código de incorporación,
     * extraer únicamente el atributo src del iframe.
     */
    if ($esIframe) {
        $cantidadIframes = preg_match_all(
            '~<iframe\b([^>]*)>~i',
            $entrada,
            $coincidenciasIframes
        );

        /*
         * Se admite exactamente un iframe.
         */
        if (
            $cantidadIframes !== 1
            || !isset($coincidenciasIframes[1][0])
        ) {
            return null;
        }

        $atributos = $coincidenciasIframes[1][0];
        $coincidenciasSrc = [];

        /*
         * Admite src con comillas dobles,
         * comillas simples o sin comillas.
         */
        if (
            !preg_match(
                '~(?:^|\s)src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i',
                $atributos,
                $coincidenciasSrc
            )
        ) {
            return null;
        }

        $src = '';

        if (
            isset($coincidenciasSrc[1])
            && $coincidenciasSrc[1] !== ''
        ) {
            $src = $coincidenciasSrc[1];
        } elseif (
            isset($coincidenciasSrc[2])
            && $coincidenciasSrc[2] !== ''
        ) {
            $src = $coincidenciasSrc[2];
        } elseif (
            isset($coincidenciasSrc[3])
            && $coincidenciasSrc[3] !== ''
        ) {
            $src = $coincidenciasSrc[3];
        }

        /*
         * Convertir entidades HTML que puedan aparecer
         * en el atributo src.
         */
        $url = html_entity_decode(
            trim($src),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        if ($url === '') {
            return null;
        }
    }

    /*
     * Analizar la URL extraída o el enlace recibido.
     */
    $partes = parse_url($url);

    if (!is_array($partes)) {
        return null;
    }

    $esquema = strtolower(
        (string) ($partes['scheme'] ?? '')
    );

    /*
     * Solo HTTPS.
     * También se rechazan credenciales y puertos explícitos.
     */
    if (
        $esquema !== 'https'
        || isset($partes['user'])
        || isset($partes['pass'])
        || isset($partes['port'])
    ) {
        return null;
    }

    $host = strtolower(
        rtrim((string) ($partes['host'] ?? ''), '.')
    );

    $hostsPermitidos = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtu.be',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com'
    ];

    if (!in_array($host, $hostsPermitidos, true)) {
        return null;
    }

    $ruta = (string) ($partes['path'] ?? '');

    /*
     * Para el código iframe se exige una dirección del
     * reproductor de YouTube con un ID válido.
     *
     * Ejemplo:
     * https://www.youtube.com/embed/AbCdEf12345
     */
    if ($esIframe) {
        if (
            !preg_match(
                '~^/embed/([A-Za-z0-9_-]{11})/?$~',
                $ruta,
                $coincidencias
            )
        ) {
            return null;
        }

        return $coincidencias[1];
    }

    /*
     * Compatibilidad con URL normales.
     */
    $idVideo = null;

    if ($host === 'youtu.be') {
        $idVideo = trim($ruta, '/');

        if (strpos($idVideo, '/') !== false) {
            return null;
        }
    } elseif ($ruta === '/watch') {
        parse_str(
            (string) ($partes['query'] ?? ''),
            $consulta
        );

        $idVideo = $consulta['v'] ?? null;
    } elseif (
        preg_match(
            '~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~',
            $ruta,
            $coincidencias
        )
    ) {
        $idVideo = $coincidencias[1];
    }

    if (
        !is_string($idVideo)
        || !preg_match('/^[A-Za-z0-9_-]{11}$/', $idVideo)
    ) {
        return null;
    }

    return $idVideo;
}

/*
 * =========================================================
 * VALIDAR ARCHIVO DE VIDEO
 *
 * Formatos admitidos:
 *   MP4  -> video/mp4
 *   WebM -> video/webm
 *   OGG  -> video/ogg
 *
 * Tamaño máximo de la aplicación: 100 MB.
 * =========================================================
 */

function validarArchivoVideoTutorial(): ?array
{
    if (!isset($_FILES['archivo_video'])) {
        return null;
    }

    $archivo = $_FILES['archivo_video'];

    if (!is_array($archivo) || !isset($archivo['error'])) {
        throw new InvalidArgumentException(
            'La información del archivo recibido no es válida.'
        );
    }

    $error = (int) $archivo['error'];

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new InvalidArgumentException(
            'El video supera el tamaño permitido por PHP. Revisa la configuración de subida de XAMPP.'
        );
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(
            'No se pudo recibir el video. Código de error de subida: ' . $error
        );
    }

    $temporal = (string) ($archivo['tmp_name'] ?? '');
    $tamano = (int) ($archivo['size'] ?? 0);

    $limiteBytes = 100 * 1024 * 1024;

    if ($tamano <= 0) {
        throw new InvalidArgumentException(
            'El archivo está vacío o no tiene un tamaño válido.'
        );
    }

    if ($tamano > $limiteBytes) {
        throw new InvalidArgumentException(
            'El video supera el límite de 100 MB.'
        );
    }

    if (
        $temporal === ''
        || !is_uploaded_file($temporal)
        || !is_file($temporal)
    ) {
        throw new InvalidArgumentException(
            'El archivo recibido no es una subida válida.'
        );
    }

    if (!class_exists('finfo')) {
        throw new RuntimeException(
            'La extensión Fileinfo de PHP no está disponible.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) $finfo->file($temporal));

    $tiposPermitidos = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv'
    ];

    if (!isset($tiposPermitidos[$mime])) {
        throw new InvalidArgumentException(
            'Formato de video no permitido. Utiliza MP4, WebM u OGG compatible.'
        );
    }

    $nombreOriginal = (string) ($archivo['name'] ?? 'video');
    $nombreOriginal = str_replace('\\', '/', $nombreOriginal);
    $nombreOriginal = basename($nombreOriginal);

    $nombreOriginal = preg_replace(
        '/[\x00-\x1F\x7F]/',
        '',
        $nombreOriginal
    ) ?? 'video';

    $nombreOriginal = substr($nombreOriginal, 0, 255);

    return [
        'tmp_name' => $temporal,
        'nombre_original' => $nombreOriginal,
        'tipo_mime' => $mime,
        'extension' => $tiposPermitidos[$mime],
        'tamano_bytes' => $tamano
    ];
}

/*
 * =========================================================
 * RUTAS SEGURAS DE LOS ARCHIVOS
 * =========================================================
 */

function obtenerRutaFisicaVideoTutorial(?string $rutaRelativa): ?string
{
    if (
        !is_string($rutaRelativa)
        || $rutaRelativa === ''
        || strpos($rutaRelativa, 'uploads/tutoriales/') !== 0
        || strpos($rutaRelativa, '..') !== false
        || strpos($rutaRelativa, '\\') !== false
        || strpos($rutaRelativa, "\0") !== false
        || strpos($rutaRelativa, '/') === 0
        || preg_match('/^[a-z][a-z0-9+.-]*:/i', $rutaRelativa)
    ) {
        return null;
    }

    $raizProyecto = realpath(__DIR__ . '/..');

    if ($raizProyecto === false) {
        return null;
    }

    $directorioUploads = realpath(
        $raizProyecto . DIRECTORY_SEPARATOR . 'uploads'
    );

    $directorioTutoriales = realpath(
        $raizProyecto
        . DIRECTORY_SEPARATOR
        . 'uploads'
        . DIRECTORY_SEPARATOR
        . 'tutoriales'
    );

    if (
        $directorioUploads === false
        || $directorioTutoriales === false
        || strpos(
            $directorioTutoriales,
            $directorioUploads . DIRECTORY_SEPARATOR
        ) !== 0
    ) {
        return null;
    }

    $rutaFisica = realpath(
        $raizProyecto
        . DIRECTORY_SEPARATOR
        . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa)
    );

    if (
        $rutaFisica === false
        || !is_file($rutaFisica)
        || strpos(
            $rutaFisica,
            $directorioTutoriales . DIRECTORY_SEPARATOR
        ) !== 0
    ) {
        return null;
    }

    return $rutaFisica;
}

function eliminarArchivoVideoTutorial(?string $rutaRelativa): bool
{
    if ($rutaRelativa === null || $rutaRelativa === '') {
        return true;
    }

    $rutaFisica = obtenerRutaFisicaVideoTutorial($rutaRelativa);

    /*
     * No se elimina nada si la ruta no pertenece
     * a la carpeta prevista o el archivo no existe.
     */
    if ($rutaFisica === null) {
        return false;
    }

    return @unlink($rutaFisica);
}

/*
 * =========================================================
 * GUARDAR ARCHIVO EN EL SERVIDOR
 * =========================================================
 */

function guardarArchivoVideoTutorial(array $archivo): string
{
    $raizProyecto = realpath(__DIR__ . '/..');

    if ($raizProyecto === false) {
        throw new RuntimeException(
            'No se pudo determinar la ubicación del proyecto.'
        );
    }

    $rutaDirectorio = $raizProyecto
        . DIRECTORY_SEPARATOR
        . 'uploads'
        . DIRECTORY_SEPARATOR
        . 'tutoriales';

    $directorioUploads = realpath(
        $raizProyecto . DIRECTORY_SEPARATOR . 'uploads'
    );

    $directorioTutoriales = realpath($rutaDirectorio);

    if (
        $directorioUploads === false
        || $directorioTutoriales === false
        || strpos(
            $directorioTutoriales,
            $directorioUploads . DIRECTORY_SEPARATOR
        ) !== 0
        || !is_dir($directorioTutoriales)
        || !is_writable($directorioTutoriales)
    ) {
        throw new RuntimeException(
            'La carpeta uploads/tutoriales no existe o no permite guardar archivos.'
        );
    }

    $nombreAleatorio = bin2hex(random_bytes(24))
        . '.'
        . $archivo['extension'];

    $destino = $directorioTutoriales
        . DIRECTORY_SEPARATOR
        . $nombreAleatorio;

    if (!move_uploaded_file($archivo['tmp_name'], $destino)) {
        throw new RuntimeException(
            'No fue posible guardar el video en el servidor.'
        );
    }

    @chmod($destino, 0640);

    return 'uploads/tutoriales/' . $nombreAleatorio;
}

/*
 * =========================================================
 * NORMALIZAR CATEGORÍAS RECIBIDAS
 * El formulario debe enviar categorias[].
 * =========================================================
 */

function obtenerCategoriasRecibidas(): array
{
    $recibidas = $_POST['categorias'] ?? [];

    if (!is_array($recibidas)) {
        throw new InvalidArgumentException(
            'La selección de categorías no es válida.'
        );
    }

    if (count($recibidas) > 100) {
        throw new InvalidArgumentException(
            'No se pueden asignar más de 100 categorías a un tutorial.'
        );
    }

    $ids = [];

    foreach ($recibidas as $valor) {
        $id = obtenerIdTutorialPositivo($valor);

        if ($id === null) {
            throw new InvalidArgumentException(
                'Una de las categorías seleccionadas no es válida.'
            );
        }

        $ids[] = $id;
    }

    return array_values(array_unique($ids));
}

/*
 * =========================================================
 * MÉTODO HTTP Y AUTENTICACIÓN
 * =========================================================
 */

if (!isset($conexion) || !($conexion instanceof PDO)) {
    errorTutoriales(
        'No se pudo establecer la conexión con la base de datos.',
        500
    );
}

if ($idFuncionario <= 0) {
    errorTutoriales(
        'No se pudo identificar al funcionario de la sesión.',
        401
    );
}

/*
 * =========================================================
 * GET: LISTAR TUTORIALES
 * =========================================================
 */

if ($metodo === 'GET' && $accion === 'listar') {
    try {
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));

        $porPagina = (int) ($_GET['por_pagina'] ?? 10);

        if (!in_array($porPagina, [10, 20, 50], true)) {
            $porPagina = 10;
        }

        $busqueda = limpiarTextoTutorial(
            $_GET['busqueda'] ?? ''
        );

        $estado = strtolower(
            limpiarTextoTutorial($_GET['estado'] ?? 'todas')
        );

        $tipo = strtolower(
            limpiarTextoTutorial($_GET['tipo'] ?? 'todos')
        );

        $categoria = $_GET['categoria'] ?? '';

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

        if (!in_array($tipo, ['todos', 'archivo', 'youtube'], true)) {
            $tipo = 'todos';
        }

        $idCategoria = null;

        if ($categoria !== '' && $categoria !== 'todas') {
            $idCategoria = obtenerIdTutorialPositivo($categoria);

            if ($idCategoria === null) {
                errorTutoriales(
                    'El filtro de categoría no es válido.'
                );
            }
        }

        $condiciones = [];
        $parametros = [];

        if ($estado !== 'todas') {
            $condiciones[] = 't.estado = :estado';
            $parametros[':estado'] = $estado;
        }

        if ($tipo !== 'todos') {
            $condiciones[] = 't.tipo_video = :tipo_video';
            $parametros[':tipo_video'] = $tipo;
        }

        if ($busqueda !== '') {
            $termino = '%' . $busqueda . '%';

            $condiciones[] = "(
                t.titulo ILIKE :busqueda_titulo
                OR COALESCE(t.descripcion, '') ILIKE :busqueda_descripcion
            )";

            $parametros[':busqueda_titulo'] = $termino;
            $parametros[':busqueda_descripcion'] = $termino;
        }

        if ($idCategoria !== null) {
            $condiciones[] = "
                EXISTS (
                    SELECT 1
                    FROM public.web_tutoriales_categoria_relacion r
                    WHERE r.id_tutorial = t.id_tutorial
                      AND r.id_categoria = :id_categoria
                )
            ";

            $parametros[':id_categoria'] = $idCategoria;
        }

        $where = $condiciones
            ? 'WHERE ' . implode(' AND ', $condiciones)
            : '';

        $stmtTotal = $conexion->prepare("
            SELECT COUNT(*)
            FROM public.web_tutoriales t
            $where
        ");

        $stmtTotal->execute($parametros);

        $total = (int) $stmtTotal->fetchColumn();

        $totalPaginas = max(
            1,
            (int) ceil($total / $porPagina)
        );

        $pagina = min($pagina, $totalPaginas);

        $offset = ($pagina - 1) * $porPagina;

        $sql = "
            SELECT
                t.id_tutorial,
                t.titulo,
                t.descripcion,
                t.tipo_video,
                t.ruta_video,
                t.youtube_id,
                t.nombre_archivo_original,
                t.tipo_mime,
                t.tamano_bytes,
                t.estado,
                t.fecha_publicacion,
                t.fecha_creacion,
                t.fecha_actualizacion,
                t.fecha_papelera,
                t.estado_antes_papelera,
                categorias.categorias_texto
            FROM public.web_tutoriales t

            LEFT JOIN LATERAL (
                SELECT string_agg(
                    c.nombre,
                    ', ' ORDER BY c.nombre
                ) AS categorias_texto
                FROM public.web_tutoriales_categoria_relacion r
                INNER JOIN public.web_tutoriales_categorias c
                    ON c.id_categoria = r.id_categoria
                WHERE r.id_tutorial = t.id_tutorial
            ) categorias ON TRUE

            $where

            ORDER BY
                COALESCE(
                    t.fecha_publicacion,
                    t.fecha_creacion
                ) DESC,
                t.id_tutorial DESC

            LIMIT :limite OFFSET :desplazamiento
        ";

        $stmt = $conexion->prepare($sql);

        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor);
        }

        $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':desplazamiento', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $tutoriales = $stmt->fetchAll(PDO::FETCH_ASSOC);

        responderTutoriales(
            true,
            'Tutoriales consultados correctamente.',
            [
                'tutoriales' => $tutoriales,
                'paginacion' => [
                    'pagina' => $pagina,
                    'por_pagina' => $porPagina,
                    'total' => $total,
                    'total_paginas' => $totalPaginas
                ],
                'csrf_token' => $tokenCSRF
            ]
        );
    } catch (Throwable $e) {
        error_log(
            'Error al listar tutoriales: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible consultar los tutoriales.',
            500
        );
    }
}

/*
 * =========================================================
 * GET: OBTENER UN TUTORIAL
 * =========================================================
 */

if ($metodo === 'GET' && $accion === 'obtener') {
    $idTutorial = obtenerIdTutorialPositivo(
        $_GET['id'] ?? null
    );

    if ($idTutorial === null) {
        errorTutoriales(
            'El identificador del tutorial no es válido.'
        );
    }

    try {
        $stmt = $conexion->prepare("
            SELECT *
            FROM public.web_tutoriales
            WHERE id_tutorial = :id_tutorial
            LIMIT 1
        ");

        $stmt->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tutorial) {
            errorTutoriales(
                'El tutorial solicitado no existe.',
                404
            );
        }

        $stmtCategorias = $conexion->prepare("
            SELECT
                c.id_categoria,
                c.nombre,
                c.activo
            FROM public.web_tutoriales_categoria_relacion r
            INNER JOIN public.web_tutoriales_categorias c
                ON c.id_categoria = r.id_categoria
            WHERE r.id_tutorial = :id_tutorial
            ORDER BY c.nombre
        ");

        $stmtCategorias->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $tutorial['categorias'] = $stmtCategorias->fetchAll(
            PDO::FETCH_ASSOC
        );

        responderTutoriales(
            true,
            'Tutorial consultado correctamente.',
            [
                'tutorial' => $tutorial,
                'csrf_token' => $tokenCSRF
            ]
        );
    } catch (Throwable $e) {
        error_log(
            'Error al obtener tutorial: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible consultar el tutorial.',
            500
        );
    }
}

/*
 * =========================================================
 * GET: LISTAR CATEGORÍAS
 * =========================================================
 */

if ($metodo === 'GET' && $accion === 'categorias') {
    try {
        $incluirInactivas = esBooleanoTutorial(
            $_GET['incluir_inactivas'] ?? true
        );

        $where = $incluirInactivas
            ? ''
            : 'WHERE c.activo = TRUE';

        $sql = "
            SELECT
                c.id_categoria,
                c.nombre,
                c.descripcion,
                c.activo,
                c.fecha_creacion,
                c.creado_por,
                COUNT(r.id_tutorial) AS cantidad_tutoriales
            FROM public.web_tutoriales_categorias c

            LEFT JOIN public.web_tutoriales_categoria_relacion r
                ON r.id_categoria = c.id_categoria

            $where

            GROUP BY c.id_categoria

            ORDER BY
                c.activo DESC,
                c.nombre ASC
        ";

        $stmt = $conexion->query($sql);

        responderTutoriales(
            true,
            'Categorías consultadas correctamente.',
            [
                'categorias' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'csrf_token' => $tokenCSRF
            ]
        );
    } catch (Throwable $e) {
        error_log(
            'Error al listar categorías de tutoriales: '
            . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible consultar las categorías.',
            500
        );
    }
}

/*
 * =========================================================
 * EL RESTO DE LAS ACCIONES REQUIERE POST
 * =========================================================
 */

if ($metodo !== 'POST') {
    header('Allow: GET, POST');

    errorTutoriales(
        'El método HTTP solicitado no está permitido.',
        405
    );
}

if (!validarTokenTutoriales()) {
    errorTutoriales(
        'La sesión de seguridad venció o no es válida. Actualiza la página e inténtalo nuevamente.',
        403
    );
}

/*
 * =========================================================
 * POST: CREAR O EDITAR TUTORIAL
 * =========================================================
 */

if ($accion === 'guardar') {
    $idTutorial = null;
    $rutaNuevaArchivo = null;
    $rutaAntiguaEliminar = null;

    try {
        if (
            isset($_POST['id_tutorial'])
            && $_POST['id_tutorial'] !== ''
        ) {
            $idTutorial = obtenerIdTutorialPositivo(
                $_POST['id_tutorial']
            );

            if ($idTutorial === null) {
                throw new InvalidArgumentException(
                    'El identificador del tutorial no es válido.'
                );
            }
        }

        $titulo = limpiarTextoTutorial(
            $_POST['titulo'] ?? ''
        );

        $descripcion = limpiarTextoTutorial(
            $_POST['descripcion'] ?? ''
        );

        $tipoVideo = strtolower(
            limpiarTextoTutorial(
                $_POST['tipo_video'] ?? ''
            )
        );

        if ($titulo === '') {
            throw new InvalidArgumentException(
                'Debes ingresar el título del tutorial.'
            );
        }

        if (mb_strlen($titulo, 'UTF-8') > 180) {
            throw new InvalidArgumentException(
                'El título no puede superar los 180 caracteres.'
            );
        }

        if (!in_array($tipoVideo, ['archivo', 'youtube'], true)) {
            throw new InvalidArgumentException(
                'El tipo de video seleccionado no es válido.'
            );
        }

        $youtubeId = null;

        if ($tipoVideo === 'youtube') {
            /*
             * El campo puede contener una URL normal
             * o el código iframe completo de YouTube.
             */
            $entradaYouTube = limpiarTextoTutorial(
                $_POST['youtube_url'] ?? ''
            );

            $youtubeId = obtenerIdYouTubeTutorial($entradaYouTube);

            if ($youtubeId === null) {
                throw new InvalidArgumentException(
                    'El enlace o código iframe de YouTube no es válido. Copia el código desde YouTube, en Compartir > Incorporar, o utiliza una URL de video HTTPS de YouTube.'
                );
            }
        }

        $archivoValidado = validarArchivoVideoTutorial();

        if ($tipoVideo === 'youtube' && $archivoValidado !== null) {
            throw new InvalidArgumentException(
                'Seleccionaste YouTube. No adjuntes un archivo de video en esta opción.'
            );
        }

        $idsCategorias = obtenerCategoriasRecibidas();

        $conexion->beginTransaction();

        /*
         * Buscar y bloquear el tutorial existente.
         */
        $actual = null;

        if ($idTutorial !== null) {
            $stmtActual = $conexion->prepare("
                SELECT *
                FROM public.web_tutoriales
                WHERE id_tutorial = :id_tutorial
                FOR UPDATE
            ");

            $stmtActual->execute([
                ':id_tutorial' => $idTutorial
            ]);

            $actual = $stmtActual->fetch(PDO::FETCH_ASSOC);

            if (!$actual) {
                throw new InvalidArgumentException(
                    'El tutorial no existe.'
                );
            }

            if ($actual['estado'] === 'papelera') {
                throw new InvalidArgumentException(
                    'Primero debes restaurar el tutorial desde la papelera.'
                );
            }
        }

        /*
         * Comprobar que todas las categorías existan.
         */
        if ($idsCategorias) {
            $marcadores = [];
            $parametrosCategorias = [];

            foreach ($idsCategorias as $indice => $idCategoria) {
                $marcador = ':categoria_' . $indice;
                $marcadores[] = $marcador;
                $parametrosCategorias[$marcador] = $idCategoria;
            }

            $sqlCategorias = "
                SELECT COUNT(*)
                FROM public.web_tutoriales_categorias
                WHERE id_categoria IN (
                    " . implode(', ', $marcadores) . "
                )
            ";

            $stmtCategorias = $conexion->prepare($sqlCategorias);
            $stmtCategorias->execute($parametrosCategorias);

            if ((int) $stmtCategorias->fetchColumn() !== count($idsCategorias)) {
                throw new InvalidArgumentException(
                    'Una o más categorías seleccionadas ya no existen.'
                );
            }
        }

        /*
         * Preparar los datos del video.
         */
        $rutaVideo = null;
        $nombreArchivoOriginal = null;
        $tipoMime = null;
        $tamanoBytes = null;

        if ($tipoVideo === 'archivo') {
            if ($archivoValidado !== null) {
                /*
                 * Se guarda con un nombre aleatorio.
                 * Nunca se utiliza el nombre original como ruta.
                 */
                $rutaNuevaArchivo = guardarArchivoVideoTutorial(
                    $archivoValidado
                );

                $rutaVideo = $rutaNuevaArchivo;
                $nombreArchivoOriginal =
                    $archivoValidado['nombre_original'];
                $tipoMime = $archivoValidado['tipo_mime'];
                $tamanoBytes = $archivoValidado['tamano_bytes'];

                if (
                    $actual
                    && $actual['tipo_video'] === 'archivo'
                    && $actual['ruta_video'] !== $rutaVideo
                ) {
                    $rutaAntiguaEliminar = $actual['ruta_video'];
                }
            } elseif (
                $actual
                && $actual['tipo_video'] === 'archivo'
                && !empty($actual['ruta_video'])
            ) {
                /*
                 * Al editar, se conserva el archivo existente
                 * cuando el funcionario no selecciona otro.
                 */
                if (
                    obtenerRutaFisicaVideoTutorial(
                        $actual['ruta_video']
                    ) === null
                ) {
                    throw new InvalidArgumentException(
                        'El archivo de video anterior no se encuentra en el servidor. Selecciona nuevamente el video.'
                    );
                }

                $rutaVideo = $actual['ruta_video'];
                $nombreArchivoOriginal =
                    $actual['nombre_archivo_original'];
                $tipoMime = $actual['tipo_mime'];
                $tamanoBytes = $actual['tamano_bytes'];
            } else {
                throw new InvalidArgumentException(
                    'Debes seleccionar un archivo de video para crear este tutorial.'
                );
            }
        } elseif (
            $actual
            && $actual['tipo_video'] === 'archivo'
            && !empty($actual['ruta_video'])
        ) {
            /*
             * Si se cambia de archivo local a YouTube,
             * el archivo anterior se retirará después del COMMIT.
             */
            $rutaAntiguaEliminar = $actual['ruta_video'];
        }

        /*
         * Crear o actualizar el tutorial.
         */
        if ($idTutorial === null) {
            $sql = "
                INSERT INTO public.web_tutoriales (
                    titulo,
                    descripcion,
                    tipo_video,
                    ruta_video,
                    youtube_id,
                    nombre_archivo_original,
                    tipo_mime,
                    tamano_bytes,
                    estado,
                    creado_por,
                    actualizado_por
                ) VALUES (
                    :titulo,
                    :descripcion,
                    :tipo_video,
                    :ruta_video,
                    :youtube_id,
                    :nombre_archivo,
                    :tipo_mime,
                    :tamano_bytes,
                    'borrador',
                    :creado_por,
                    :actualizado_por
                )
                RETURNING id_tutorial
            ";

            $stmt = $conexion->prepare($sql);

            $stmt->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
                ':tipo_video' => $tipoVideo,
                ':ruta_video' => $rutaVideo,
                ':youtube_id' => $youtubeId,
                ':nombre_archivo' => $nombreArchivoOriginal,
                ':tipo_mime' => $tipoMime,
                ':tamano_bytes' => $tamanoBytes,
                ':creado_por' => $idFuncionario,
                ':actualizado_por' => $idFuncionario
            ]);

            $idTutorial = (int) $stmt->fetchColumn();

            $accionAuditoria = 'crear';
            $mensaje = 'Tutorial creado como borrador.';
        } else {
            $sql = "
                UPDATE public.web_tutoriales
                SET
                    titulo = :titulo,
                    descripcion = :descripcion,
                    tipo_video = :tipo_video,
                    ruta_video = :ruta_video,
                    youtube_id = :youtube_id,
                    nombre_archivo_original = :nombre_archivo,
                    tipo_mime = :tipo_mime,
                    tamano_bytes = :tamano_bytes,
                    fecha_actualizacion = CURRENT_TIMESTAMP,
                    actualizado_por = :actualizado_por
                WHERE id_tutorial = :id_tutorial
            ";

            $stmt = $conexion->prepare($sql);

            $stmt->execute([
                ':titulo' => $titulo,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
                ':tipo_video' => $tipoVideo,
                ':ruta_video' => $rutaVideo,
                ':youtube_id' => $youtubeId,
                ':nombre_archivo' => $nombreArchivoOriginal,
                ':tipo_mime' => $tipoMime,
                ':tamano_bytes' => $tamanoBytes,
                ':actualizado_por' => $idFuncionario,
                ':id_tutorial' => $idTutorial
            ]);

            $accionAuditoria = 'editar';
            $mensaje = 'Tutorial actualizado correctamente.';
        }

        /*
         * Reemplazar las asociaciones de categorías.
         */
        $stmtEliminarCategorias = $conexion->prepare("
            DELETE FROM public.web_tutoriales_categoria_relacion
            WHERE id_tutorial = :id_tutorial
        ");

        $stmtEliminarCategorias->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $stmtInsertarCategoria = $conexion->prepare("
            INSERT INTO public.web_tutoriales_categoria_relacion (
                id_tutorial,
                id_categoria
            ) VALUES (
                :id_tutorial,
                :id_categoria
            )
        ");

        foreach ($idsCategorias as $idCategoria) {
            $stmtInsertarCategoria->execute([
                ':id_tutorial' => $idTutorial,
                ':id_categoria' => $idCategoria
            ]);
        }

        registrarCambioTutorial(
            $conexion,
            $idFuncionario,
            $accionAuditoria,
            'web_tutoriales',
            $idTutorial,
            $mensaje . ' Título: ' . $titulo
        );

        $conexion->commit();

        /*
         * Solo después de guardar correctamente el registro
         * se intenta retirar el archivo anterior.
         */
        if ($rutaAntiguaEliminar !== null) {
            if (!eliminarArchivoVideoTutorial($rutaAntiguaEliminar)) {
                error_log(
                    'No se pudo retirar el archivo anterior del tutorial '
                    . $idTutorial
                );
            }
        }

        responderTutoriales(
            true,
            $mensaje,
            [
                'id_tutorial' => $idTutorial,
                'estado' => $actual['estado'] ?? 'borrador'
            ]
        );
    } catch (InvalidArgumentException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        if ($rutaNuevaArchivo !== null) {
            eliminarArchivoVideoTutorial($rutaNuevaArchivo);
        }

        errorTutoriales($e->getMessage(), 422);
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        if ($rutaNuevaArchivo !== null) {
            eliminarArchivoVideoTutorial($rutaNuevaArchivo);
        }

        error_log(
            'Error al guardar tutorial: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible guardar el tutorial. Revisa el registro de errores de PHP.',
            500
        );
    }
}

/*
 * =========================================================
 * POST: GUARDAR CATEGORÍA
 * Crea una categoría o actualiza una existente.
 * =========================================================
 */

if ($accion === 'guardar_categoria') {
    try {
        $idCategoria = null;

        if (
            isset($_POST['id_categoria'])
            && $_POST['id_categoria'] !== ''
        ) {
            $idCategoria = obtenerIdTutorialPositivo(
                $_POST['id_categoria']
            );

            if ($idCategoria === null) {
                throw new InvalidArgumentException(
                    'El identificador de la categoría no es válido.'
                );
            }
        }

        $nombre = limpiarTextoTutorial(
            $_POST['nombre'] ?? ''
        );

        $descripcion = limpiarTextoTutorial(
            $_POST['descripcion'] ?? ''
        );

        if ($nombre === '') {
            throw new InvalidArgumentException(
                'Debes ingresar el nombre de la categoría.'
            );
        }

        if (mb_strlen($nombre, 'UTF-8') > 100) {
            throw new InvalidArgumentException(
                'El nombre no puede superar los 100 caracteres.'
            );
        }

        $conexion->beginTransaction();

        if ($idCategoria === null) {
            $stmt = $conexion->prepare("
                INSERT INTO public.web_tutoriales_categorias (
                    nombre,
                    descripcion,
                    creado_por
                ) VALUES (
                    :nombre,
                    :descripcion,
                    :creado_por
                )
                RETURNING id_categoria
            ");

            $stmt->execute([
                ':nombre' => $nombre,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
                ':creado_por' => $idFuncionario
            ]);

            $idCategoria = (int) $stmt->fetchColumn();

            $accionAuditoria = 'crear_categoria';
            $mensaje = 'Categoría creada correctamente.';
        } else {
            $stmt = $conexion->prepare("
                UPDATE public.web_tutoriales_categorias
                SET
                    nombre = :nombre,
                    descripcion = :descripcion
                WHERE id_categoria = :id_categoria
                RETURNING id_categoria
            ");

            $stmt->execute([
                ':nombre' => $nombre,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
                ':id_categoria' => $idCategoria
            ]);

            if (!$stmt->fetchColumn()) {
                throw new InvalidArgumentException(
                    'La categoría que intentas editar no existe.'
                );
            }

            $accionAuditoria = 'editar_categoria';
            $mensaje = 'Categoría actualizada correctamente.';
        }

        registrarCambioTutorial(
            $conexion,
            $idFuncionario,
            $accionAuditoria,
            'web_tutoriales_categorias',
            $idCategoria,
            $mensaje . ' Nombre: ' . $nombre
        );

        $conexion->commit();

        responderTutoriales(
            true,
            $mensaje,
            ['id_categoria' => $idCategoria]
        );
    } catch (InvalidArgumentException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        errorTutoriales($e->getMessage(), 422);
    } catch (PDOException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        $codigoSQL = (string) (
            $e->errorInfo[0] ?? $e->getCode()
        );

        if ($codigoSQL === '23505') {
            errorTutoriales(
                'Ya existe una categoría con ese nombre. Utiliza uno diferente.',
                409
            );
        }

        error_log(
            'Error de PostgreSQL al guardar categoría. '
            . 'SQLSTATE: ' . $codigoSQL
            . ' — ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible guardar la categoría. Revisa los datos e inténtalo nuevamente.',
            500
        );
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al guardar categoría: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible guardar la categoría.',
            500
        );
    }
}

/*
 * =========================================================
 * POST: ACTIVAR O DESACTIVAR CATEGORÍA
 * =========================================================
 */

if ($accion === 'cambiar_estado_categoria') {
    $idCategoria = obtenerIdTutorialPositivo(
        $_POST['id_categoria'] ?? null
    );

    if ($idCategoria === null) {
        errorTutoriales(
            'El identificador de la categoría no es válido.'
        );
    }

    $valorActivo = (string) ($_POST['activo'] ?? '');

    if (!in_array($valorActivo, ['0', '1'], true)) {
        errorTutoriales(
            'El estado solicitado para la categoría no es válido.'
        );
    }

    $activoSQL = $valorActivo === '1';

    try {
        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            UPDATE public.web_tutoriales_categorias
            SET activo = :activo
            WHERE id_categoria = :id_categoria
            RETURNING nombre
        ");

        $stmt->execute([
            ':activo' => $activoSQL ? 'true' : 'false',
            ':id_categoria' => $idCategoria
        ]);

        $nombreCategoria = $stmt->fetchColumn();

        if ($nombreCategoria === false) {
            throw new InvalidArgumentException(
                'La categoría solicitada no existe.'
            );
        }

        $mensaje = $activoSQL
            ? 'Categoría activada correctamente.'
            : 'Categoría desactivada correctamente.';

        registrarCambioTutorial(
            $conexion,
            $idFuncionario,
            $activoSQL ? 'activar_categoria' : 'desactivar_categoria',
            'web_tutoriales_categorias',
            $idCategoria,
            $mensaje . ' Nombre: ' . $nombreCategoria
        );

        $conexion->commit();

        responderTutoriales(
            true,
            $mensaje,
            [
                'id_categoria' => $idCategoria,
                'activo' => $activoSQL
            ]
        );
    } catch (InvalidArgumentException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        errorTutoriales($e->getMessage(), 404);
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al cambiar estado de categoría: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible cambiar el estado de la categoría.',
            500
        );
    }
}

/*
 * =========================================================
 * POST: CAMBIAR ESTADO DEL TUTORIAL
 * =========================================================
 */

if ($accion === 'cambiar_estado') {
    $idTutorial = obtenerIdTutorialPositivo(
        $_POST['id_tutorial'] ?? null
    );

    $nuevoEstado = strtolower(
        limpiarTextoTutorial($_POST['estado'] ?? '')
    );

    if ($idTutorial === null) {
        errorTutoriales(
            'El identificador del tutorial no es válido.'
        );
    }

    if (
        !in_array(
            $nuevoEstado,
            ['publicada', 'inactiva', 'borrador'],
            true
        )
    ) {
        errorTutoriales(
            'El estado solicitado no es válido.'
        );
    }

    try {
        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            SELECT estado, tipo_video, ruta_video
            FROM public.web_tutoriales
            WHERE id_tutorial = :id_tutorial
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tutorial) {
            throw new InvalidArgumentException(
                'El tutorial solicitado no existe.'
            );
        }

        if ($tutorial['estado'] === 'papelera') {
            throw new InvalidArgumentException(
                'Primero debes restaurar el tutorial desde la papelera.'
            );
        }

        if (
            $nuevoEstado === 'publicada'
            && $tutorial['tipo_video'] === 'archivo'
            && obtenerRutaFisicaVideoTutorial(
                $tutorial['ruta_video']
            ) === null
        ) {
            throw new InvalidArgumentException(
                'No se puede publicar el tutorial porque su archivo no está disponible en el servidor.'
            );
        }

        $stmtActualizar = $conexion->prepare("
            UPDATE public.web_tutoriales
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
            WHERE id_tutorial = :id_tutorial
        ");

        $stmtActualizar->execute([
            ':estado' => $nuevoEstado,
            ':estado_fecha' => $nuevoEstado,
            ':actualizado_por' => $idFuncionario,
            ':id_tutorial' => $idTutorial
        ]);

        $mensajes = [
            'publicada' => 'Tutorial publicado correctamente.',
            'inactiva' => 'Tutorial desactivado correctamente.',
            'borrador' => 'El tutorial volvió a estado borrador.'
        ];

        registrarCambioTutorial(
            $conexion,
            $idFuncionario,
            'cambiar_estado',
            'web_tutoriales',
            $idTutorial,
            $mensajes[$nuevoEstado]
        );

        $conexion->commit();

        responderTutoriales(
            true,
            $mensajes[$nuevoEstado],
            [
                'id_tutorial' => $idTutorial,
                'estado' => $nuevoEstado
            ]
        );
    } catch (InvalidArgumentException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        errorTutoriales($e->getMessage(), 409);
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al cambiar estado de tutorial: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible cambiar el estado del tutorial.',
            500
        );
    }
}

/*
 * =========================================================
 * POST: ENVIAR TUTORIAL A PAPELERA
 * =========================================================
 */

if ($accion === 'enviar_papelera') {
    $idTutorial = obtenerIdTutorialPositivo(
        $_POST['id_tutorial'] ?? null
    );

    if ($idTutorial === null) {
        errorTutoriales(
            'El identificador del tutorial no es válido.'
        );
    }

    try {
        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            SELECT estado
            FROM public.web_tutoriales
            WHERE id_tutorial = :id_tutorial
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $estadoActual = $stmt->fetchColumn();

        if ($estadoActual === false) {
            throw new InvalidArgumentException(
                'El tutorial solicitado no existe.'
            );
        }

        if ($estadoActual === 'papelera') {
            throw new InvalidArgumentException(
                'El tutorial ya se encuentra en la papelera.'
            );
        }

        $stmt = $conexion->prepare("
            UPDATE public.web_tutoriales
            SET
                estado_antes_papelera = estado,
                estado = 'papelera',
                fecha_papelera = CURRENT_TIMESTAMP,
                papelera_por = :papelera_por,
                fecha_actualizacion = CURRENT_TIMESTAMP,
                actualizado_por = :actualizado_por
            WHERE id_tutorial = :id_tutorial
        ");

        $stmt->execute([
            ':papelera_por' => $idFuncionario,
            ':actualizado_por' => $idFuncionario,
            ':id_tutorial' => $idTutorial
        ]);

        registrarCambioTutorial(
            $conexion,
            $idFuncionario,
            'enviar_papelera',
            'web_tutoriales',
            $idTutorial,
            'Tutorial enviado a la papelera.'
        );

        $conexion->commit();

        responderTutoriales(
            true,
            'Tutorial enviado a la papelera. El archivo se conserva para permitir su recuperación.',
            [
                'id_tutorial' => $idTutorial,
                'estado' => 'papelera'
            ]
        );
    } catch (InvalidArgumentException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        errorTutoriales($e->getMessage(), 409);
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al enviar tutorial a papelera: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible enviar el tutorial a la papelera.',
            500
        );
    }
}

/*
 * =========================================================
 * POST: RESTAURAR TUTORIAL
 * =========================================================
 */

if ($accion === 'restaurar') {
    $idTutorial = obtenerIdTutorialPositivo(
        $_POST['id_tutorial'] ?? null
    );

    if ($idTutorial === null) {
        errorTutoriales(
            'El identificador del tutorial no es válido.'
        );
    }

    try {
        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            SELECT estado, estado_antes_papelera
            FROM public.web_tutoriales
            WHERE id_tutorial = :id_tutorial
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tutorial) {
            throw new InvalidArgumentException(
                'El tutorial solicitado no existe.'
            );
        }

        if ($tutorial['estado'] !== 'papelera') {
            throw new InvalidArgumentException(
                'El tutorial no se encuentra en la papelera.'
            );
        }

        $estadoAnterior = $tutorial['estado_antes_papelera'];

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
            SELECT tipo_video, ruta_video
            FROM public.web_tutoriales
            WHERE id_tutorial = :id_tutorial
        ");

        $stmt->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $datosVideo = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
         * Si falta el archivo, se recupera como borrador,
         * evitando que quede publicado un video inexistente.
         */
        if (
            $estadoAnterior === 'publicada'
            && $datosVideo
            && $datosVideo['tipo_video'] === 'archivo'
            && obtenerRutaFisicaVideoTutorial(
                $datosVideo['ruta_video']
            ) === null
        ) {
            $estadoAnterior = 'borrador';
        }

        $stmt = $conexion->prepare("
            UPDATE public.web_tutoriales
            SET
                estado = :estado,
                estado_antes_papelera = NULL,
                fecha_papelera = NULL,
                papelera_por = NULL,
                fecha_actualizacion = CURRENT_TIMESTAMP,
                actualizado_por = :actualizado_por
            WHERE id_tutorial = :id_tutorial
        ");

        $stmt->execute([
            ':estado' => $estadoAnterior,
            ':actualizado_por' => $idFuncionario,
            ':id_tutorial' => $idTutorial
        ]);

        registrarCambioTutorial(
            $conexion,
            $idFuncionario,
            'restaurar',
            'web_tutoriales',
            $idTutorial,
            'Tutorial restaurado desde la papelera con estado: '
                . $estadoAnterior
        );

        $conexion->commit();

        responderTutoriales(
            true,
            'Tutorial recuperado correctamente.',
            [
                'id_tutorial' => $idTutorial,
                'estado' => $estadoAnterior
            ]
        );
    } catch (InvalidArgumentException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        errorTutoriales($e->getMessage(), 409);
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al restaurar tutorial: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible recuperar el tutorial.',
            500
        );
    }
}

/*
 * =========================================================
 * POST: ELIMINAR DEFINITIVAMENTE TUTORIAL
 *
 * Solo elimina registros que estén en la papelera.
 * El archivo se intenta eliminar después del COMMIT.
 * =========================================================
 */

if ($accion === 'eliminar_definitivamente') {
    $idTutorial = obtenerIdTutorialPositivo(
        $_POST['id_tutorial'] ?? null
    );

    if ($idTutorial === null) {
        errorTutoriales(
            'El identificador del tutorial no es válido.'
        );
    }

    $rutaEliminar = null;

    try {
        $conexion->beginTransaction();

        $stmt = $conexion->prepare("
            SELECT
                id_tutorial,
                titulo,
                estado,
                ruta_video,
                tipo_video
            FROM public.web_tutoriales
            WHERE id_tutorial = :id_tutorial
            FOR UPDATE
        ");

        $stmt->execute([
            ':id_tutorial' => $idTutorial
        ]);

        $tutorial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tutorial) {
            throw new InvalidArgumentException(
                'El tutorial que intentas eliminar no existe.'
            );
        }

        if ($tutorial['estado'] !== 'papelera') {
            throw new InvalidArgumentException(
                'Solo se pueden eliminar definitivamente los tutoriales que estén en la papelera.'
            );
        }

        if ($tutorial['tipo_video'] === 'archivo') {
            $rutaEliminar = $tutorial['ruta_video'];
        }

        registrarCambioTutorial(
            $conexion,
            $idFuncionario,
            'eliminar_definitivamente',
            'web_tutoriales',
            $idTutorial,
            'Tutorial eliminado definitivamente. Título: '
                . $tutorial['titulo']
        );

        /*
         * Las categorías asociadas se eliminan por ON DELETE CASCADE.
         */
        $stmtEliminar = $conexion->prepare("
            DELETE FROM public.web_tutoriales
            WHERE id_tutorial = :id_tutorial
              AND estado = 'papelera'
        ");

        $stmtEliminar->execute([
            ':id_tutorial' => $idTutorial
        ]);

        if ($stmtEliminar->rowCount() !== 1) {
            throw new RuntimeException(
                'No se pudo confirmar la eliminación del tutorial.'
            );
        }

        $conexion->commit();

        if (
            $rutaEliminar !== null
            && !eliminarArchivoVideoTutorial($rutaEliminar)
        ) {
            /*
             * El registro ya fue eliminado.
             * Se deja constancia para una limpieza manual del archivo.
             */
            error_log(
                'No se pudo eliminar el archivo físico del tutorial '
                . $idTutorial
                . ': '
                . $rutaEliminar
            );
        }

        responderTutoriales(
            true,
            'El tutorial fue eliminado definitivamente.',
            ['id_tutorial' => $idTutorial]
        );
    } catch (InvalidArgumentException $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        errorTutoriales($e->getMessage(), 409);
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) {
            $conexion->rollBack();
        }

        error_log(
            'Error al eliminar tutorial: ' . $e->getMessage()
        );

        errorTutoriales(
            'No fue posible eliminar definitivamente el tutorial.',
            500
        );
    }
}

/*
 * =========================================================
 * ACCIÓN DESCONOCIDA
 * =========================================================
 */

errorTutoriales(
    'La acción solicitada no existe.',
    400
);