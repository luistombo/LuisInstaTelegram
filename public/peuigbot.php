<?php
// LuisInstaTelegram 1.0.0
// Bot de Telegram para descargar contenido público de Instagram usando RapidAPI/instagram-looter2.

// Configuración del archivo de log.
// En producción conviene dejar DEBUG_MODE en false para no guardar respuestas completas ni URLs temporales.
define('LOG_FILE', __DIR__ . '/../storage/bot_log.txt');
define('DEBUG_MODE', false);

// Ruta para el archivo de cookies de cURL (aún usados por cURL, pero la API de terceros gestiona la sesión de Instagram)
define('COOKIE_JAR', __DIR__ . '/../storage/instagram_cookies.txt');

// URL base de la API de terceros (ACTUALIZADO CON INFO DE RAPIDAPI)
define('THIRD_PARTY_API_BASE_URL', 'https://instagram-looter2.p.rapidapi.com');

// HOST de la API de terceros (ACTUALIZADO CON INFO DE RAPIDAPI)
define('X_RAPIDAPI_HOST', 'instagram-looter2.p.rapidapi.com');


// Función para registrar mensajes en el archivo de log
function writeLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $dir = dirname(LOG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents(LOG_FILE, "[{$timestamp}] {$message}
", FILE_APPEND | LOCK_EX);
}

// Evita que tokens y API keys terminen escritos en logs
function maskSecret($value) {
    $value = (string)$value;
    if (strlen($value) <= 8) {
        return '***';
    }
    return substr($value, 0, 4) . '...' . substr($value, -4);
}

function sanitizeConfigForLog($config) {
    $safe = $config;
    foreach (['token', 'third_party_api_key', 'webhook_secret'] as $key) {
        if (isset($safe[$key]) && $safe[$key] !== '') {
            $safe[$key] = maskSecret($safe[$key]);
        }
    }
    return $safe;
}

function sanitizeHeadersForLog($headers) {
    $safe = [];
    foreach ($headers as $header) {
        if (stripos($header, 'X-RapidAPI-Key:') === 0 || stripos($header, 'Authorization:') === 0) {
            [$name] = explode(':', $header, 2);
            $safe[] = $name . ': ***';
        } else {
            $safe[] = $header;
        }
    }
    return $safe;
}

// Función para leer configuración
function loadConfig() {
    writeLog("Cargando configuración desde config.txt...");
    $configPath = __DIR__ . '/../config.txt';

    if (!file_exists($configPath)) {
        writeLog('ERROR: Archivo config.txt no encontrado.');
        error_log('Archivo config.txt no encontrado');
        return false;
    }

    $config = [];
    $lines = file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Ignorar líneas vacías y comentadas
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
            continue;
        }

        // Soporta tanto "clave: valor" como "clave=valor"
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
        } elseif (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
        } else {
            continue;
        }

        $config[trim($key)] = trim($value);
    }

    writeLog("Configuración cargada: " . json_encode(sanitizeConfigForLog($config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $config;
}

// Función para enviar mensaje de Telegram con cURL
function sendTelegramMessage($token, $chatId, $message, $parseMode = null) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message
    ];

    if ($parseMode) {
        $data['parse_mode'] = $parseMode;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    writeLog("Enviando mensaje a Telegram (chatId: {$chatId}): " . $message);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        writeLog("ERROR al enviar mensaje a Telegram. Detalles: " . curl_error($ch));
    } else {
        writeLog("Respuesta de Telegram (mensaje): " . $response);
    }
    curl_close($ch);
    return $response;
}

// Función para enviar foto de Telegram
function sendTelegramPhoto($token, $chatId, $photoUrl, $caption = '') {
    $url = "https://api.telegram.org/bot{$token}/sendPhoto";
    $data = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'caption' => $caption
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    writeLog("Enviando foto a Telegram (chatId: {$chatId}, URL: {$photoUrl})");
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        writeLog("ERROR al enviar foto a Telegram. Detalles: " . curl_error($ch));
    } else {
        writeLog("Respuesta de Telegram (foto): " . $response);
    }
    curl_close($ch);
    return $response;
}

// Función para enviar video de Telegram
function sendTelegramVideo($token, $chatId, $videoUrl, $caption = '') {
    $url = "https://api.telegram.org/bot{$token}/sendVideo";
    $data = [
        'chat_id' => $chatId,
        'video' => $videoUrl,
        'caption' => $caption
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    writeLog("Enviando video a Telegram (chatId: {$chatId}, URL: {$videoUrl})");
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        writeLog("ERROR al enviar video a Telegram. Detalles: " . curl_error($ch));
    } else {
        writeLog("Respuesta de Telegram (video): " . $response);
    }
    curl_close($ch);
    return $response;
}

// Función para extraer el shortcode de la URL de Instagram
function extractInstagramShortcode($url) {
    writeLog("Intentando extraer shortcode de la URL: {$url}");
    $url = strtok($url, '?');

    $patterns = [
        '/instagram\.com\/p\/([A-Za-z0-9_-]+)/',
        '/instagram\.com\/reel\/([A-Za-z0-9_-]+)/',
        '/instagram\.com\/reels\/([A-Za-z0-9_-]+)/',
        '/instagr\.am\/p\/([A-Za-z0-9_-]+)/',
        '/instagram\.com\/tv\/([A-Za-z0-9_-]+)/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            writeLog("Shortcode extraído: {$matches[1]}");
            return $matches[1];
        }
    }
    writeLog("No se pudo extraer el shortcode de la URL.");
    return false;
}

/**
 * Función para enviar una solicitud HTTP utilizando cURL.
 * Esta función ahora es genérica para cualquier solicitud HTTP, incluyendo la API de terceros.
 * @param string $url La URL a solicitar.
 * @param array $extraHeaders Los encabezados HTTP adicionales.
 * @param int $timeout El tiempo de espera en segundos.
 * @return array|false Un array con 'body' y 'status_code' o false en caso de error.
 */
function sendHttpRequest($url, $extraHeaders = [], $timeout = 60) { // Aumentar timeout para APIs externas
    writeLog("Realizando petición HTTP a: {$url}");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_ENCODING, ""); // Aceptar todas las codificaciones (gzip, deflate)

    curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIE_JAR);
    curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIE_JAR);

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
        'Accept: */*',
        'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
        'Connection: keep-alive',
    ];

    // Fusionar con encabezados adicionales (ej. para RapidAPI)
    foreach ($extraHeaders as $extraHeader) {
        list($name, $value) = explode(':', $extraHeader, 2);
        $name = trim($name);
        $found = false;
        foreach ($headers as $key => $header) {
            if (stripos($header, $name . ':') === 0) {
                $headers[$key] = $extraHeader;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $headers[] = $extraHeader;
        }
    }

    writeLog("Headers de la petición: " . json_encode(sanitizeHeadersForLog($headers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        writeLog("ERROR: Falló la petición HTTP (cURL). Detalles: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $response_headers = substr($response, 0, $header_size);
    $response_body = substr($response, $header_size);
    $http_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    writeLog("Respuesta HTTP: Código {$http_status_code}, Cuerpo (primeros 500 chars): " . substr($response_body, 0, 500) . "...");
    if (DEBUG_MODE) {
        writeLog("Cabeceras completas de la respuesta HTTP: " . $response_headers);
        writeLog("Cuerpo completo de la respuesta HTTP: " . $response_body);
    }

    curl_close($ch);

    return ['body' => $response_body, 'status_code' => $http_status_code];
}

/**
 * Función para obtener datos de Instagram usando una API de terceros.
 * @param string $shortcode El shortcode del post de Instagram.
 * @param array $config La configuración del bot, incluyendo la clave de API.
 * @return array|false Un array con 'type' y 'items' de medios o false si falla.
 */
function getInstagramDataFromThirdPartyAPI($shortcode, $config) {
    if (!isset($config['third_party_api_key']) || empty($config['third_party_api_key'])) {
        writeLog("ERROR: third_party_api_key no encontrada en config.txt. No se puede usar la API de terceros.");
        return false;
    }

    $instagram_url = "https://www.instagram.com/p/{$shortcode}/";

    // Construye la URL de la API de terceros con la URL completa del post
    // La API "instagram-looter2" parece usar el endpoint /post y toma la URL de Instagram como parámetro 'url'.
    $api_url = THIRD_PARTY_API_BASE_URL . '/post?url=' . urlencode($instagram_url);

    $extraHeaders = [
        'X-RapidAPI-Key: ' . $config['third_party_api_key'],
        'X-RapidAPI-Host: ' . X_RAPIDAPI_HOST,
        'Content-Type: application/json',
    ];

    writeLog("Realizando petición a la API de terceros: " . $api_url);
    $response = sendHttpRequest($api_url, $extraHeaders, 60);

    if (!$response || $response['status_code'] !== 200) {
        writeLog("ERROR: La petición a la API de terceros falló o devolvió un código HTTP no exitoso. Código: " . ($response['status_code'] ?? 'N/A'));
        if (isset($response['body']) && strpos($response['body'], '<!DOCTYPE html>') !== false) {
             writeLog("La respuesta de la API de terceros es HTML, no JSON. Posible error de configuración o API.");
        }
        return false;
    }

    $data = json_decode($response['body'], true);

    if (!$data) {
        writeLog("ERROR: La respuesta de la API de terceros no es un JSON válido. Cuerpo: " . substr($response['body'], 0, 500) . "...");
        return false;
    }

    $mediaItems = [];

    // Lógica de parseo adaptada a la estructura de la respuesta de "instagram-looter2"
    // que se parece mucho a la estructura GraphQL de Instagram.
    if (isset($data['__typename']) && ($data['__typename'] === 'GraphImage' || $data['__typename'] === 'GraphVideo')) {
        // Es un post de una sola imagen o un solo video
        $item_type = ($data['__typename'] === 'GraphVideo') ? 'video' : 'image';
        $item_url = ($item_type === 'video' && isset($data['video_url'])) ? $data['video_url'] : (isset($data['display_url']) ? $data['display_url'] : '');

        if (!empty($item_url) && filter_var($item_url, FILTER_VALIDATE_URL)) {
            $mediaItems[] = [
                'type' => $item_type,
                'url' => $item_url
            ];
            writeLog("Elemento único encontrado: Tipo '{$item_type}', URL '{$item_url}'");
        } else {
            writeLog("ADVERTENCIA: URL de elemento único vacía o inválida desde la API. Tipo '{$item_type}'.");
        }
    } elseif (isset($data['__typename']) && $data['__typename'] === 'GraphSidecar' && isset($data['edge_sidecar_to_children']['edges'])) {
        // Es un carrusel (GraphSidecar) con múltiples imágenes/videos
        writeLog("Tipo de post: Carrusel (GraphSidecar). Procesando elementos del carrusel.");
        foreach ($data['edge_sidecar_to_children']['edges'] as $edge) {
            $node = $edge['node'];
            $item_type = (isset($node['__typename']) && $node['__typename'] === 'XDTGraphVideo') ? 'video' : 'image';
            $item_url = ($item_type === 'video' && isset($node['video_url'])) ? $node['video_url'] : (isset($node['display_url']) ? $node['display_url'] : '');

            if (!empty($item_url) && filter_var($item_url, FILTER_VALIDATE_URL)) {
                $mediaItems[] = [
                    'type' => $item_type,
                    'url' => $item_url
                ];
                writeLog("Elemento de carrusel añadido: Tipo '{$item_type}', URL '{$item_url}'");
            } else {
                writeLog("ADVERTENCIA: URL de elemento de carrusel vacía o inválida desde la API. Tipo '{$item_type}'.");
            }
        }
    } else {
        writeLog("La estructura de la respuesta de la API no es la esperada (ni single, ni sidecar): " . json_encode($data));
    }


    if (!empty($mediaItems)) {
        writeLog("Medios encontrados a través de la API de terceros. Cantidad: " . count($mediaItems));
        return ['type' => count($mediaItems) > 1 ? 'carousel' : 'single', 'items' => $mediaItems];
    }

    writeLog("La API de terceros no devolvió medios válidos o la estructura no coincide.");
    return false;
}

/**
 * Función auxiliar para parsear HTML si la API de terceros devuelve HTML (fallback)
 * Esta función solo se mantendrá como un último recurso, pero la idea es que la API JSON sea directa.
 * @param string $html El HTML a parsear.
 * @return array|false Un array con 'type' y 'items' de medios o false si falla.
 */
function parseInstagramDataFromHtmlApiFallback($html) {
    $mediaUrls = [];
    writeLog("Parseando HTML de fallback recibido de la API de terceros...");

    // Buscar en window._sharedData
    if (preg_match('/<script[^>]*?>\s*window\._sharedData\s*=\s*({.+?});\s*<\/script>/s', $html, $matches)) {
        $data = json_decode($matches[1], true);
        if ($data && isset($data['entry_data']['PostPage'][0]['graphql']['shortcode_media'])) {
            writeLog("window._sharedData encontrado y decodificado del HTML de fallback.");
            // Reutilizamos la función de parseo de la estructura GraphQL
            return parseInstagramDataStructureFromGraph($data['entry_data']['PostPage'][0]['graphql']['shortcode_media']);
        }
    }

    // Si _sharedData no funciona, intentar con meta tags (Open Graph y JSON-LD)
    if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $matches)) {
        $imageUrl = html_entity_decode($matches[1]);
        if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $mediaUrls[] = ['type' => 'image', 'url' => $imageUrl];
        }
    }
    if (preg_match('/<meta property="og:video" content="([^"]+)"/', $html, $matches)) {
        $videoUrl = html_entity_decode($matches[1]);
        if (filter_var($videoUrl, FILTER_VALIDATE_URL)) {
            $mediaUrls[] = ['type' => 'video', 'url' => $videoUrl];
        }
    }
    if (preg_match_all('/<script type="application\/ld\+json">([^<]+)<\/script>/', $html, $matches)) {
        foreach ($matches[1] as $jsonString) {
            $data = json_decode($jsonString, true);
            if ($data) {
                if (isset($data['video']['contentUrl'])) {
                    $videoUrl = $data['video']['contentUrl'];
                    if (filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                        $mediaUrls[] = ['type' => 'video', 'url' => $videoUrl];
                    }
                }
                if (isset($data['image']) && is_string($data['image'])) {
                    $imageUrl = $data['image'];
                    if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                        $mediaUrls[] = ['type' => 'image', 'url' => $imageUrl];
                    }
                }
            }
        }
    }

    if (!empty($mediaUrls)) {
        writeLog("Medios encontrados mediante parsing HTML de fallback (meta tags/JSON-LD).");
        return ['type' => count($mediaUrls) > 1 ? 'carousel' : 'single', 'items' => $mediaUrls];
    }

    writeLog("No se encontraron datos válidos en el HTML de fallback de la API.");
    return false;
}


// Función para parsear la estructura de datos GraphQL de Instagram (internamente)
function parseInstagramDataStructureFromGraph($media) {
    $result = [
        'type' => 'single',
        'items' => []
    ];
    writeLog("Parseando estructura de datos de Instagram (GraphQL-like).");

    if (isset($media['edge_sidecar_to_children'])) {
        $result['type'] = 'carousel';
        writeLog("Tipo de post: Carrusel. Procesando elementos del carrusel.");
        foreach ($media['edge_sidecar_to_children']['edges'] as $edge) {
            $node = $edge['node'];
            $item = [
                'type' => $node['is_video'] ? 'video' : 'image',
                'url' => $node['is_video'] ? (isset($node['video_url']) ? $node['video_url'] : '') : (isset($node['display_url']) ? $node['display_url'] : '')
            ];
            if (!empty($item['url']) && filter_var($item['url'], FILTER_VALIDATE_URL)) {
                $result['items'][] = $item;
                writeLog("Elemento de carrusel añadido: Tipo '{$item['type']}', URL '{$item['url']}'");
            } else {
                writeLog("ADVERTENCIA: URL de elemento de carrusel vacía o inválida. Tipo '{$item['type']}'");
            }
        }
    } else {
        writeLog("Tipo de post: Único elemento.");
        $item = [
            'type' => isset($media['is_video']) && $media['is_video'] ? 'video' : 'image',
            'url' => isset($media['is_video']) && $media['is_video'] ? (isset($media['video_url']) ? $media['video_url'] : '') : (isset($media['display_url']) ? $media['display_url'] : '')
        ];
        if (!empty($item['url']) && filter_var($item['url'], FILTER_VALIDATE_URL)) {
            $result['items'][] = $item;
            writeLog("Elemento único añadido: Tipo '{$item['type']}', URL '{$item['url']}'");
        } else {
            writeLog("ADVERTENCIA: URL de elemento único vacía o inválida. Tipo '{$item['type']}'");
        }
    }
    writeLog("Parseo de datos de Instagram completado. Items encontrados: " . count($result['items']));
    return $result;
}


// Función principal para procesar el webhook
function processWebhook() {
    writeLog("Iniciando procesamiento del webhook.");
    $config = loadConfig();
    if (!$config) {
        http_response_code(500);
        writeLog("ERROR: Fallo al cargar la configuración. Terminando.");
        exit('Error de configuración');
    }

    // Si config.txt define webhook_secret, valida el header enviado por Telegram al configurar setWebhook.
    if (!empty($config['webhook_secret'])) {
        $receivedSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if (!hash_equals($config['webhook_secret'], (string)$receivedSecret)) {
            http_response_code(401);
            writeLog("ERROR: webhook_secret inválido. Terminando.");
            exit('Unauthorized');
        }
    }

    $input = file_get_contents('php://input');
    $update = json_decode($input, true);

    writeLog("Webhook input: " . $input);

    if (!$update || !isset($update['message'])) {
        http_response_code(200);
        writeLog("No se recibió un mensaje válido en el webhook. Terminando.");
        exit('OK');
    }

    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? ''; // El texto original enviado por el usuario (contiene la URL de Instagram)

    writeLog("Mensaje recibido (chatId: {$chatId}, texto: {$text})");

    // Verificar si es un comando de inicio
    if ($text === '/start') {
        sendTelegramMessage(
            $config['token'],
            $chatId,
            "¡Hola! 👋\n\nEnvíame un enlace de Instagram y te descargaré las imágenes y videos del post.\n\nEjemplo:\nhttps://www.instagram.com/p/ABC123/"
        );
        http_response_code(200);
        writeLog("Comando /start procesado. Terminando.");
        exit('OK');
    }

    if ($text === '/id') {
        sendTelegramMessage(
            $config['token'],
            $chatId,
            "Chat ID: <code>{$chatId}</code>",
            'HTML'
        );
        http_response_code(200);
        writeLog("Comando /id procesado. Terminando.");
        exit('OK');
    }

    if ($text === '/version') {
        sendTelegramMessage(
            $config['token'],
            $chatId,
            "LuisInstaTelegram 1.0.0"
        );
        http_response_code(200);
        writeLog("Comando /version procesado. Terminando.");
        exit('OK');
    }

    // Verificar si el mensaje contiene una URL de Instagram
    if (strpos($text, 'instagram.com') === false && strpos($text, 'instagr.am') === false) {
        sendTelegramMessage(
            $config['token'],
            $chatId,
            "Por favor, envía un enlace válido de Instagram."
        );
        http_response_code(200);
        writeLog("Mensaje no es un enlace de Instagram. Terminando.");
        exit('OK');
    }

    $shortcode = extractInstagramShortcode($text);
    if (!$shortcode) {
        sendTelegramMessage(
            $config['token'],
            $chatId,
            "No pude extraer el código del post de Instagram. Verifica que el enlace sea correcto."
        );
        http_response_code(200);
        writeLog("No se pudo extraer el shortcode. Terminando.");
        exit('OK');
    }

    // Enviar mensaje de procesamiento
    sendTelegramMessage(
        $config['token'],
        $chatId,
        "🔄 Procesando tu enlace de Instagram... Esto puede tardar un momento."
    );

    // Obtener datos de Instagram usando la API de terceros
    $instagramData = getInstagramDataFromThirdPartyAPI($shortcode, $config);

    if (!$instagramData || empty($instagramData['items'])) {
        sendTelegramMessage(
            $config['token'],
            $chatId,
            "❌ No pude obtener el contenido de Instagram a través de la API de terceros.\n\n" .
            "Posibles causas:\n" .
            "• El post podría ser privado o no público.\n" .
            "• La API de terceros está experimentando problemas o bloqueos.\n" .
            "• La clave de API no es válida o se agotaron los límites de uso.\n" .
            "• Cambios en la estructura de Instagram o de la API de terceros.\n\n" .
            "Shortcode detectado: <code>{$shortcode}</code>\n\n" .
            "Intenta con otro enlace o prueba más tarde. Revisa el archivo de log para más detalles.",
            'HTML'
        );
        http_response_code(200);
        writeLog("Fallo al obtener datos de Instagram o no se encontraron items. Terminando.");
        exit('OK');
    }

    // Enviar todos los contenidos (fotos y videos)
    $total_items = count($instagramData['items']);
    $count = 0;
    foreach ($instagramData['items'] as $item) {
        $count++;
        // Construye el caption
        $caption = "";
        if ($total_items > 1) {
            $caption .= "📸 Elemento {$count} de {$total_items}\n";
        }
        $caption .= "{$text}"; // Agrega solo el link al post original

        if ($item['type'] === 'image') {
            writeLog("Preparando para enviar imagen {$count}: {$item['url']}");
            sendTelegramPhoto($config['token'], $chatId, $item['url'], $caption);
        } elseif ($item['type'] === 'video') {
            writeLog("Preparando para enviar video {$count}: {$item['url']}");
            sendTelegramVideo($config['token'], $chatId, $item['url'], $caption);
        } else {
            writeLog("ADVERTENCIA: Tipo de medio desconocido para el elemento {$count}.");
        }

        // Pequeña pausa para evitar límites de rate de Telegram
        sleep(1);
    }

    sendTelegramMessage(
        $config['token'],
        $chatId,
        "✅ ¡Listo! He descargado y enviado " . $count . " archivo(s) del post de Instagram."
    );

    http_response_code(200);
    writeLog("Procesamiento completado. Total de elementos enviados: {$count}. Terminando.");
    exit('OK');
}

// Ejecutar el procesamiento del webhook
processWebhook();