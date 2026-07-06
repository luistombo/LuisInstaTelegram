<?php
declare(strict_types=1);

const APP_VERSION = '1.0.0';
const ROOT_DIR = __DIR__ . '/..';

function load_config(): array
{
    $path = ROOT_DIR . '/config.txt';
    $config = [];

    if (!is_readable($path)) {
        return $config;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $config;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $config[trim($key)] = trim($value);
    }

    return $config;
}

function cfg(array $config, string $key, string $default = ''): string
{
    return isset($config[$key]) ? trim((string) $config[$key]) : $default;
}

function log_line(array $config, string $message): void
{
    $logFile = ROOT_DIR . '/storage/bot.log';
    $configured = cfg($config, 'APP_LOG_FILE');
    if ($configured !== '') {
        $logFile = str_starts_with($configured, '/') ? $configured : ROOT_DIR . '/' . ltrim($configured, '/');
    }

    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_telegram_message(array $config, int|string $chatId, string $text): void
{
    $token = cfg($config, 'TELEGRAM_BOT_TOKEN');
    if ($token === '') {
        log_line($config, 'Missing TELEGRAM_BOT_TOKEN');
        return;
    }

    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payload = json_encode([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_exec($ch);
    curl_close($ch);
}

function extract_instagram_shortcode(string $text): ?string
{
    $pattern = '~https?://(?:www\.)?instagram\.com/(?:reel|reels|p|tv)/([A-Za-z0-9_-]+)~i';
    if (preg_match($pattern, $text, $matches) !== 1) {
        return null;
    }
    return $matches[1];
}

function allowed_chat(array $config, int|string $chatId): bool
{
    $raw = cfg($config, 'TELEGRAM_ALLOWED_CHAT_IDS');
    if ($raw === '') {
        return true;
    }

    $allowed = array_map('trim', explode(',', $raw));
    return in_array((string) $chatId, $allowed, true);
}

function valid_secret(array $config): bool
{
    $expected = cfg($config, 'TELEGRAM_WEBHOOK_SECRET');
    if ($expected === '') {
        return true;
    }

    $received = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    return hash_equals($expected, (string) $received);
}

function help_text(): string
{
    return "<b>LuisInstaTelegram</b>\n"
        . "Mandame un link público de Instagram y extraigo el shortcode para procesarlo.\n\n"
        . "Comandos:\n"
        . "/start - iniciar\n"
        . "/help - ayuda\n"
        . "/id - ver el chat ID actual\n"
        . "/version - ver versión";
}

function process_message(array $config, array $message): void
{
    $chatId = $message['chat']['id'] ?? null;
    $text = trim((string) ($message['text'] ?? ''));

    if ($chatId === null || $text === '') {
        return;
    }

    if (!allowed_chat($config, $chatId)) {
        log_line($config, 'Rejected unauthorized chat: ' . (string) $chatId);
        return;
    }

    if ($text === '/start' || str_starts_with($text, '/start@') || $text === '/help' || str_starts_with($text, '/help@')) {
        send_telegram_message($config, $chatId, help_text());
        return;
    }

    if ($text === '/version' || str_starts_with($text, '/version@')) {
        send_telegram_message($config, $chatId, 'LuisInstaTelegram ' . APP_VERSION);
        return;
    }

    if ($text === '/id' || str_starts_with($text, '/id@')) {
        send_telegram_message($config, $chatId, 'Chat ID: <code>' . h((string) $chatId) . '</code>');
        return;
    }

    $shortcode = extract_instagram_shortcode($text);
    if ($shortcode === null) {
        send_telegram_message($config, $chatId, 'No detecté un link válido de Instagram. Mandame una URL de /reel/, /p/ o /tv/.');
        return;
    }

    send_telegram_message($config, $chatId, "🔄 Link recibido.\nShortcode: <code>" . h($shortcode) . "</code>");

    $apiUrl = cfg($config, 'THIRD_PARTY_API_URL');
    $apiKey = cfg($config, 'THIRD_PARTY_API_KEY');
    if ($apiUrl === '' || $apiKey === '') {
        send_telegram_message($config, $chatId, "Todavía no hay API externa configurada.\nShortcode detectado: <code>" . h($shortcode) . "</code>");
        return;
    }

    send_telegram_message($config, $chatId, "API externa configurada. Falta adaptar el parser específico del proveedor para <code>" . h($shortcode) . "</code>.");
}

$config = load_config();

if (!valid_secret($config)) {
    http_response_code(401);
    echo 'unauthorized';
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$update = json_decode($raw, true);

if (!is_array($update)) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'version' => APP_VERSION]);
    exit;
}

log_line($config, 'Webhook update received');
$message = $update['message'] ?? $update['edited_message'] ?? null;
if (is_array($message)) {
    process_message($config, $message);
}

header('Content-Type: application/json');
echo json_encode(['ok' => true]);
