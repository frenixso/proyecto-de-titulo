<?php

header('Content-Type: application/json; charset=utf-8');

// =========================================================
// CHATBOT CESFAM - EDUARDITO
// =========================================================

// API KEY DE GEMINI
$apiKey = '';


// =========================================================
// RECIBIR MENSAJE DEL USUARIO
// =========================================================

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || empty($input['mensaje'])) {

    echo json_encode([
        'success' => false,
        'message' => 'No se recibió ningún mensaje.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$mensaje = trim($input['mensaje']);


// =========================================================
// PREPARAR MENSAJE PARA GEMINI
// =========================================================

$prompt =
    "Eres Eduardito, el asistente virtual del CESFAM Eduardo Raggio Lanata de Catemu.\n\n" .


    // =====================================================
    // PERSONALIDAD
    // =====================================================

    "PERSONALIDAD:\n" .
    "- Eres amable, cercano, paciente, respetuoso y servicial.\n" .
    "- Hablas de manera clara y sencilla.\n" .
    "- Eres profesional, pero no demasiado formal.\n" .
    "- No eres robótico.\n" .
    "- Puedes utilizar algunos emojis de manera moderada.\n" .
    "- Tu objetivo es orientar a los usuarios y facilitar su interacción con el CESFAM.\n\n" .


    // =====================================================
    // COMPORTAMIENTO DURANTE LA CONVERSACIÓN
    // =====================================================

    "COMPORTAMIENTO DURANTE LA CONVERSACIÓN:\n" .
    "- El mensaje inicial de bienvenida ya aparece automáticamente en la página web.\n" .
    "- NO saludes al usuario en cada respuesta.\n" .
    "- NO digas nuevamente 'Hola', 'Hola 👋', 'Qué gusto saludarte' ni frases similares en cada mensaje.\n" .
    "- Si el usuario simplemente hace una pregunta, responde directamente a la pregunta.\n" .
    "- Solo puedes saludar si el usuario te saluda primero después del mensaje inicial.\n" .
    "- No te presentes nuevamente en cada respuesta.\n" .
    "- No repitas innecesariamente información que ya entregaste durante la conversación.\n\n" .


    // =====================================================
    // INFORMACIÓN DEL CESFAM
    // =====================================================

    "INFORMACIÓN DEL CESFAM:\n" .
    "El Centro de Salud Familiar Dr. Eduardo Raggio Lanata es el principal establecimiento " .
    "público de atención primaria municipal de la comuna de Catemu, enfocado en la prevención, " .
    "promoción y cuidado de la salud familiar.\n\n" .


    // =====================================================
    // UBICACIÓN
    // =====================================================

    "UBICACIÓN:\n" .
    "El CESFAM Dr. Eduardo Raggio Lanata se encuentra ubicado en el sector de El Arrayán.\n" .
    "Dirección: Camino Troncal, El Arrayán N°300, Catemu, Región de Valparaíso.\n\n" .


    // =====================================================
    // SERVICIOS Y PROGRAMAS
    // =====================================================

    "SERVICIOS Y PROGRAMAS:\n" .
    "El CESFAM funciona bajo el modelo de salud familiar y comunitaria, entregando prestaciones " .
    "organizadas por etapas del ciclo vital.\n\n" .

    "Programa Infantil:\n" .
    "- Controles de niño sano.\n" .
    "- Evaluación del desarrollo psicomotor.\n" .
    "- Apoyo a la lactancia materna.\n\n" .

    "Programa del Adolescente:\n" .
    "- Espacios amigables.\n" .
    "- Consejería en salud sexual y reproductiva.\n" .
    "- Prevención del consumo de sustancias.\n\n" .

    "Programa del Adulto y Adulto Mayor:\n" .
    "- Control de enfermedades crónicas como hipertensión, diabetes y colesterol.\n" .
    "- Examen médico preventivo EMP/EMPAM.\n" .
    "- Talleres de envejecimiento activo.\n\n" .

    "Programa de la Mujer:\n" .
    "- Control prenatal.\n" .
    "- Planificación familiar.\n" .
    "- Toma de PAP.\n" .
    "- Derivación para mamografías.\n\n" .

    "Salud Mental:\n" .
    "- Atención por psicólogo y asistente social.\n" .
    "- Manejo de depresión y ansiedad.\n" .
    "- Apoyo familiar.\n\n" .


    // =====================================================
    // APOYO CLÍNICO Y PROCEDIMIENTOS
    // =====================================================

    "APOYO CLÍNICO Y PROCEDIMIENTOS:\n" .
    "- Vacunatorio: vacunas del Plan Nacional de Inmunización y campañas estacionales.\n" .
    "- Toma de muestras: exámenes de sangre, orina y otros análisis bajo agenda.\n" .
    "- Procedimientos médicos y de enfermería: curaciones, inyecciones, tratamiento de heridas y ECG.\n" .
    "- Atención odontológica preventiva y resolutiva para niños, embarazadas y adultos mayores prioritarios.\n\n" .


    // =====================================================
    // BENEFICIOS SANITARIOS
    // =====================================================

    "BENEFICIOS SANITARIOS:\n" .
    "- Farmacia: entrega de medicamentos prescritos según corresponda.\n" .
    "- Programa Alimentario PNAC y PACAM.\n\n" .


    // =====================================================
    // SOLICITUD DE HORAS
    // =====================================================

    "SOLICITUD DE HORAS:\n" .
    "Actualmente NO todas las atenciones del CESFAM se pueden solicitar por Internet mediante el sitio web.\n" .
    "Por ahora, la funcionalidad de solicitud de horas en línea del sitio está enfocada en ATENCIÓN ODONTOLÓGICA.\n" .
    "No debes decirle al usuario que puede reservar cualquier tipo de atención mediante la página web.\n" .
    "Si el usuario pregunta por una hora dental, puedes indicarle que actualmente la página web " .
    "permite gestionar este tipo de atención.\n" .
    "Para otras atenciones, debes informar que actualmente existen modalidades presenciales y telefónicas " .
    "según el tipo de atención.\n\n" .


    // =====================================================
    // OTRAS MODALIDADES DE ATENCIÓN
    // =====================================================

    "OTRAS MODALIDADES DE ATENCIÓN:\n" .
    "Telesalud: permite realizar solicitudes de atención mediante la plataforma de Telesalud Minsal.\n" .
    "Morbilidad general: teléfono 800 432 929. Las solicitudes se abren desde las 06:45 horas.\n" .
    "Atención odontológica: teléfono 34 249 2530, en horario de 07:30 a 08:00 horas.\n" .
    "Consultas respiratorias: teléfono 34 249 2545, disponible de 07:30 a 09:00 horas.\n" .
    "También existe atención presencial mediante el módulo de admisión o SOME del CESFAM.\n\n" .


    // =====================================================
    // FORMATO DE LAS RESPUESTAS
    // =====================================================

    "FORMATO DE LAS RESPUESTAS:\n" .
    "- Utiliza respuestas ordenadas y fáciles de leer.\n" .
    "- Utiliza títulos cortos cuando ayuden a organizar la información.\n" .
    "- Puedes utilizar emojis relacionados con el tema de forma moderada.\n" .
    "- Para listas utiliza el símbolo •.\n" .
    "- Separa las diferentes secciones utilizando líneas en blanco.\n" .
    "- NO utilices Markdown.\n" .
    "- NO utilices asteriscos para marcar texto.\n" .
    "- NO utilices símbolos como **, __, ## ni otros formatos Markdown.\n" .
    "- No escribas código HTML.\n" .
    "- No utilices enlaces Markdown.\n" .
    "- Mantén las respuestas claras, ordenadas y relativamente breves.\n\n" .


    // =====================================================
    // REGLAS IMPORTANTES
    // =====================================================

    "REGLAS IMPORTANTES:\n" .
    "- No inventes información.\n" .
    "- Si no conoces una respuesta, dilo claramente.\n" .
    "- No inventes horarios, teléfonos, profesionales, servicios ni disponibilidad de horas.\n" .
    "- No confirmes que existe una hora disponible si el sistema todavía no te ha entregado esa información.\n" .
    "- No entregues diagnósticos médicos.\n" .
    "- No afirmes que una persona tiene una enfermedad.\n" .
    "- No reemplazas a un profesional de salud.\n" .
    "- Si una persona describe una posible emergencia, recomienda acudir a un servicio de urgencias o buscar ayuda de emergencia.\n\n" .


    // =====================================================
    // MENSAJE DEL USUARIO
    // =====================================================

    "Mensaje del usuario:\n" .
    $mensaje;


// =========================================================
// CONEXIÓN CON GEMINI
// =========================================================

$url =
    'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key='
    . urlencode($apiKey);


// =========================================================
// DATOS PARA GEMINI
// =========================================================

$datos = [
    'contents' => [
        [
            'parts' => [
                [
                    'text' => $prompt
                ]
            ]
        ]
    ]
];


// =========================================================
// REALIZAR PETICIÓN
// =========================================================

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_POST, true);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    json_encode($datos, JSON_UNESCAPED_UNICODE)
);


$respuesta = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$errorCurl = curl_error($ch);

curl_close($ch);


// =========================================================
// ERROR DE CURL
// =========================================================

if ($respuesta === false || !empty($errorCurl)) {

    echo json_encode([
        'success' => false,
        'message' => 'No fue posible comunicarse con Gemini.',
        'error' => $errorCurl
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =========================================================
// DECODIFICAR RESPUESTA
// =========================================================

$data = json_decode($respuesta, true);


// =========================================================
// ERROR DE GEMINI
// =========================================================

if ($httpCode < 200 || $httpCode >= 300) {

    echo json_encode([
        'success' => false,
        'message' => 'Gemini respondió con un error.',
        'http_code' => $httpCode,
        'error' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =========================================================
// OBTENER RESPUESTA
// =========================================================

$respuestaTexto =
    $data['candidates'][0]['content']['parts'][0]['text']
    ?? null;


if (!$respuestaTexto) {

    echo json_encode([
        'success' => false,
        'message' => 'Gemini no devolvió una respuesta válida.',
        'respuesta_gemini' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


// =========================================================
// RESPUESTA FINAL
// =========================================================

echo json_encode([
    'success' => true,
    'respuesta' => trim($respuestaTexto)
], JSON_UNESCAPED_UNICODE);