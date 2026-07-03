<?php
ob_start();
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();

header('Content-Type: application/json; charset=utf-8');

function resp(bool $ok, string $msg, array $extra = []): void {
    ob_end_clean();
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

// ── Convierte valores tipo "40M", "1G" del php.ini a bytes ──
function iniABytes(string $val): int {
    $val = trim($val);
    if ($val === '') return 0;
    $unidad = strtolower($val[strlen($val) - 1]);
    $num    = (int)$val;
    switch ($unidad) {
        case 'g': $num *= 1024 * 1024 * 1024; break;
        case 'm': $num *= 1024 * 1024; break;
        case 'k': $num *= 1024; break;
    }
    return $num;
}

// ── Si el POST completo supera post_max_size, PHP vacía $_POST y $_FILES ──
// ── antes de que el script corra, así que lo detectamos por CONTENT_LENGTH ──
$postMaxBytes = iniABytes(ini_get('post_max_size'));
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($postMaxBytes > 0 && $contentLength > $postMaxBytes && empty($_POST) && empty($_FILES)) {
    $limiteMb = round($postMaxBytes / 1024 / 1024, 1);
    $pesoMb   = round($contentLength / 1024 / 1024, 1);
    resp(false, "El archivo pesa demasiados megas ({$pesoMb} MB). El límite actual es de {$limiteMb} MB. Reduce el tamaño del video o comprímelo e inténtalo de nuevo.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');

$usuarioId = usuarioId();
if ($usuarioId === 0) resp(false, 'No autorizado. Vuelve a iniciar sesión.');
if (usuarioRol() !== 'profesor' && usuarioRol() !== 'admin') resp(false, 'No tienes permisos para subir archivos.');

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $uploadMaxBytes = iniABytes(ini_get('upload_max_filesize'));
    $limiteMb = round($uploadMaxBytes / 1024 / 1024, 1);
    $errores = [
        UPLOAD_ERR_INI_SIZE   => "El archivo pesa demasiados megas. El límite permitido es de {$limiteMb} MB. Reduce el tamaño del video e inténtalo de nuevo.",
        UPLOAD_ERR_FORM_SIZE  => "El archivo pesa demasiados megas. Reduce su tamaño e inténtalo de nuevo.",
        UPLOAD_ERR_PARTIAL    => 'La subida se interrumpió a la mitad. Revisa tu conexión e inténtalo de nuevo.',
        UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: no hay carpeta temporal disponible.',
        UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se pudo escribir el archivo.',
        UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP del servidor bloqueó la subida.',
    ];
    resp(false, $errores[$_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Error al subir el archivo. Inténtalo de nuevo.');
}

$cursoId = (int)($_POST['curso_id'] ?? 0);
$archivo = $_FILES['archivo'];

// Validar extensión
$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$extPermitidas = ['mp4', 'mkv', 'avi', 'mov', 'webm', 'mp3'];

if (!in_array($ext, $extPermitidas)) {
    resp(false, 'Formato no permitido: "' . $ext . '". Usa uno de estos: MP4, MKV, AVI, MOV, WEBM o MP3.');
}

// Validar MIME con finfo (permisivo: algunos SO reportan tipos genéricos para mkv/avi)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($archivo['tmp_name']);

$mimesPermitidos = [
    'video/mp4',
    'video/x-matroska',   // mkv
    'video/x-msvideo',    // avi
    'video/quicktime',    // mov
    'video/webm',
    'audio/mpeg',         // mp3
    // Variantes que algunos sistemas reportan:
    'application/octet-stream', // mkv / avi en algunos OS
    'video/avi',
    'video/mov',
    'audio/mp3',
    'audio/x-mpeg',
];

// Solo bloquear si el MIME es claramente peligroso (ejecutable, script, etc.)
$mimesBloqueados = [
    'application/x-executable', 'application/x-sharedlib',
    'application/x-php', 'text/x-php', 'text/x-script.php',
    'application/x-sh', 'text/x-shellscript',
];

if (in_array($mimeReal, $mimesBloqueados)) {
    resp(false, 'Ese tipo de archivo no está permitido por seguridad.');
}

// Crear carpeta destino
$dirBase = __DIR__ . '/../../../profesor/uploads/cursos/curso_' . $cursoId . '/videos';
if (!is_dir($dirBase)) {
    mkdir($dirBase, 0755, true);
}

// Nombre único y seguro
$nombreSeguro = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
$nombreFinal  = $nombreSeguro . '_' . uniqid() . '.' . $ext;
$rutaFinal    = $dirBase . '/' . $nombreFinal;

if (!move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
    resp(false, 'No se pudo guardar el video en el servidor. Verifica los permisos de la carpeta uploads/ o inténtalo de nuevo.');
}

// URL pública relativa (igual que en subir_documento.php)
$urlPublica = 'uploads/cursos/curso_' . $cursoId . '/videos/' . $nombreFinal;

resp(true, 'Archivo subido correctamente.', [
    'url'    => $urlPublica,
    'nombre' => $archivo['name'],
    'ext'    => $ext,
]);