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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');

$usuarioId = usuarioId();
if ($usuarioId === 0) resp(false, 'No autorizado.');
if (usuarioRol() !== 'profesor' && usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $errores = [1=>'Archivo demasiado grande (php.ini)',2=>'Archivo demasiado grande (formulario)',
                3=>'Subida incompleta',4=>'No se seleccionó archivo',6=>'Sin carpeta temporal',
                7=>'Error al escribir en disco'];
    resp(false, $errores[$_FILES['archivo']['error'] ?? 4] ?? 'Error de subida.');
}

$cursoId  = (int)($_POST['curso_id'] ?? 0);
$archivo  = $_FILES['archivo'];
$tamano   = $archivo['size'];
$MAX_BYTES = 20 * 1024 * 1024; // 20 MB

if ($tamano > $MAX_BYTES) resp(false, 'El archivo supera los 20 MB permitidos.');

// Validar extensión y MIME
$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$mimes = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

if (!isset($mimes[$ext])) resp(false, 'Formato no permitido. Usa PDF, Word, PowerPoint o Excel.');

// Verificar MIME real con finfo
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($archivo['tmp_name']);
$mimesPermitidos = array_values($mimes);
if (!in_array($mimeReal, $mimesPermitidos)) resp(false, 'El tipo de archivo no coincide con la extensión.');

// Crear carpeta destino
$dirBase = __DIR__ . '/../../../profesor/uploads/cursos/curso_' . $cursoId;
if (!is_dir($dirBase)) {
    mkdir($dirBase, 0755, true);
}

// Nombre único
$nombreSeguro = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
$nombreFinal  = $nombreSeguro . '_' . uniqid() . '.' . $ext;
$rutaFinal    = $dirBase . '/' . $nombreFinal;

if (!move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
    resp(false, 'No se pudo guardar el archivo. Verifica permisos de la carpeta uploads/.');
}


$urlPublica = 'uploads/cursos/curso_' . $cursoId . '/' . $nombreFinal;

resp(true, 'Archivo subido correctamente.', ['url' => $urlPublica, 'nombre' => $archivo['name'], 'ext' => $ext]);