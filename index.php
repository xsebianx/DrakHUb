<?php
// ================================================
// CONFIGURACIÓN DE SEGURIDAD
// ================================================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');

// Bloquear acceso directo a archivos sensibles
$requestedFile = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (in_array($requestedFile, ['hwids.json', '.env', 'config.ini'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso prohibido');
}

// ================================================
// VALIDACIÓN DE HWID
// ================================================
$hwid = $_GET['hwid'] ?? '';
if (!preg_match('/^[a-f0-9]{8}-?([a-f0-9]{4}-?){3}[a-f0-9]{12}$/i', $hwid)) {
    header('HTTP/1.1 400 Bad Request');
    exit('HWID inválido');
}

// ================================================
// CARGA DE HWIDs AUTORIZADOS
// ================================================
$hwidsFile = '/data/hwids.json'; // Ruta en volumen persistente

if (!file_exists($hwidsFile)) {
    error_log("Archivo hwids.json no encontrado en: $hwidsFile");
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error de configuración');
}

$hwidsData = file_get_contents($hwidsFile);
if ($hwidsData === false) {
    error_log("Error leyendo hwids.json");
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error del servidor');
}

$hwids = json_decode($hwidsData, true);
if ($hwids === null) {
    error_log("JSON inválido en hwids.json: " . json_last_error_msg());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error de datos');
}

// ================================================
// VERIFICACIÓN DE AUTORIZACIÓN
// ================================================
if (!isset($hwids[$hwid]) || $hwids[$hwid]['estado'] !== 'autorizado') {
    header('HTTP/1.1 403 Forbidden');
    exit('no_autorizado');
}

$hwinfo = $hwids[$hwid];

// Verificación para autorizaciones temporales
if ($hwinfo['tipo'] === 'temporal' && isset($hwinfo['expiracion'])) {
    try {
        $fechaActual = new DateTime();
        $fechaExpiracion = new DateTime($hwinfo['expiracion']);
        
        if ($fechaActual > $fechaExpiracion) {
            // Actualizar estado si ha expirado
            $hwids[$hwid]['estado'] = 'expirado';
            file_put_contents($hwidsFile, json_encode($hwids, JSON_PRETTY_PRINT));
            
            header('HTTP/1.1 403 Forbidden');
            exit('expirado');
        }
    } catch (Exception $e) {
        error_log("Error en fecha de expiración: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        exit('Error interno');
    }
}

// ================================================
// OBTENCIÓN DEL SCRIPT DESDE GITHUB
// ================================================
$token = getenv('GITHUB_TOKEN');
$owner = getenv('GITHUB_OWNER');
$repo = getenv('GITHUB_REPO');
$filePath = getenv('GITHUB_PATH') ?: 'main.lua'; // Ruta predeterminada

if (empty($token) || empty($owner) || empty($repo)) {
    error_log("Faltan credenciales de GitHub");
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error de configuración');
}

$url = "https://api.github.com/repos/$owner/$repo/contents/$filePath";

// Configurar contexto con timeout y seguridad SSL
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'User-Agent: Roblox-Script-Loader',
            'Authorization: token ' . $token,
            'Accept: application/vnd.github.v3.raw'
        ],
        'timeout' => 10 // 10 segundos timeout
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false
    ]
]);

// Intentar obtener el contenido
$scriptContent = @file_get_contents($url, false, $context);

if ($scriptContent === false) {
    $error = error_get_last();
    error_log("Error GitHub API: " . ($error['message'] ?? 'Error desconocido'));
    
    // Intentar con una copia de respaldo local
    $backupFile = '/data/backup.lua';
    if (file_exists($backupFile)) {
        $scriptContent = file_get_contents($backupFile);
        error_log("Usando copia de respaldo local");
    } else {
        header('HTTP/1.1 502 Bad Gateway');
        exit('Error al obtener recurso');
    }
}

// ================================================
// ENTREGA DEL CONTENIDO
// ================================================
header('Content-Type: text/plain');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');

echo $scriptContent;

// ================================================
// REGISTRO DE ACCESO (OPCIONAL)
// ================================================
$logData = sprintf(
    "[%s] HWID: %s | IP: %s | User-Agent: %s\n",
    date('Y-m-d H:i:s'),
    $hwid,
    $_SERVER['REMOTE_ADDR'] ?? 'desconocida',
    $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido'
);

file_put_contents('/data/access.log', $logData, FILE_APPEND);
?>
