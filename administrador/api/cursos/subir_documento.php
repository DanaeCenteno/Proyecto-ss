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


$postMaxBytes = iniABytes(ini_get('post_max_size'));
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($postMaxBytes > 0 && $contentLength > $postMaxBytes && empty($_POST) && empty($_FILES)) {
    $limiteMb = round($postMaxBytes / 1024 / 1024, 1);
    $pesoMb   = round($contentLength / 1024 / 1024, 1);
    resp(false, "El archivo pesa demasiados megas ({$pesoMb} MB). El límite actual es de {$limiteMb} MB. Reduce el tamaño del archivo e inténtalo de nuevo.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');

$usuarioId = usuarioId();
if ($usuarioId === 0) resp(false, 'No autorizado. Vuelve a iniciar sesión.');
if (usuarioRol() !== 'profesor' && usuarioRol() !== 'admin') resp(false, 'No tienes permisos para subir archivos.');

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $uploadMaxBytes = iniABytes(ini_get('upload_max_filesize'));
    $limiteMb = round($uploadMaxBytes / 1024 / 1024, 1);
    $errores = [
        UPLOAD_ERR_INI_SIZE   => "El archivo pesa demasiados megas. El límite permitido es de {$limiteMb} MB. Reduce el tamaño del archivo e inténtalo de nuevo.",
        UPLOAD_ERR_FORM_SIZE  => "El archivo pesa demasiados megas. Reduce su tamaño e inténtalo de nuevo.",
        UPLOAD_ERR_PARTIAL    => 'La subida se interrumpió a la mitad. Revisa tu conexión e inténtalo de nuevo.',
        UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: no hay carpeta temporal disponible. Contacta al administrador.',
        UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se pudo escribir el archivo en disco. Contacta al administrador.',
        UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP del servidor bloqueó la subida. Contacta al administrador.',
    ];
    resp(false, $errores[$_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Error al subir el archivo. Inténtalo de nuevo.');
}

$cursoId  = (int)($_POST['curso_id'] ?? 0);
$archivo  = $_FILES['archivo'];

$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));


$mimesOfimatica = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'ppt'  => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'xls'  => ['application/vnd.ms-excel'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
];


$mimesCodigo = [
    'js'   => ['text/javascript', 'application/javascript', 'text/plain'],
    'jsx'  => ['text/javascript', 'application/javascript', 'text/plain'],
    'ts'   => ['text/plain', 'video/mp2t', 'application/x-typescript'],
    'tsx'  => ['text/plain'],
    'py'   => ['text/x-python', 'text/x-script.python', 'text/plain'],
    'java' => ['text/x-java', 'text/x-java-source', 'text/plain'],
    'c'    => ['text/x-c', 'text/plain'],
    'cpp'  => ['text/x-c++', 'text/x-c', 'text/plain'],
    'cs'   => ['text/plain'],
    'php'  => ['text/x-php', 'application/x-httpd-php', 'text/plain'],
    'html' => ['text/html', 'text/plain'],
    'css'  => ['text/css', 'text/plain'],
    'json' => ['application/json', 'text/plain'],
    'sql'  => ['text/x-sql', 'text/plain', 'application/sql'],
    'sh'   => ['text/x-shellscript', 'text/plain', 'application/x-sh'],
    'txt'  => ['text/plain'],
    'zip'  => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
];

$mimes = $mimesOfimatica + $mimesCodigo;

if (!isset($mimes[$ext])) {
    resp(false, 'Formato no permitido: "' . $ext . '". Usa PDF, Word, PowerPoint, Excel, o un archivo de código/texto compatible.');
}


$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($archivo['tmp_name']);


$mimeAceptado = in_array($mimeReal, $mimes[$ext], true)
    || (isset($mimesCodigo[$ext]) && str_starts_with($mimeReal, 'text/'));

if (!$mimeAceptado) {
    resp(false, 'El contenido del archivo no coincide con su extensión (' . $ext . '). Verifica que el archivo no esté dañado.');
}


$dirBase = __DIR__ . '/../../../profesor/uploads/cursos/curso_' . $cursoId;
if (!is_dir($dirBase)) {
    mkdir($dirBase, 0755, true);
}

// Nombre único
$nombreSeguro = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
$nombreFinal  = $nombreSeguro . '_' . uniqid() . '.' . $ext;
$rutaFinal    = $dirBase . '/' . $nombreFinal;

if (!move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
    resp(false, 'No se pudo guardar el archivo en el servidor. Verifica los permisos de la carpeta uploads/ o inténtalo de nuevo.');
}


$urlPublica = 'uploads/cursos/curso_' . $cursoId . '/' . $nombreFinal;

resp(true, 'Archivo subido correctamente.', ['url' => $urlPublica, 'nombre' => $archivo['name'], 'ext' => $ext]);