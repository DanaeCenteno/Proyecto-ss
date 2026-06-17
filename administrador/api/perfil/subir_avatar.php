<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

iniciarSesion();
ob_start();
header('Content-Type: application/json; charset=utf-8');

function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean(); http_response_code($code);
    echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!estaAutenticado())                      responder(false, 'No autenticado.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')   responder(false, 'Método no permitido.', 405);
if (empty($_FILES['avatar']))                responder(false, 'No se recibió ningún archivo.', 422);

$usuarioId = usuarioId();
$file      = $_FILES['avatar'];

// ── Validar errores de subida ─────────────────────────────
if ($file['error'] !== UPLOAD_ERR_OK) {
    responder(false, 'Error al subir el archivo (código ' . $file['error'] . ').', 422);
}

// ── Validar tipo MIME real ────────────────────────────────
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeReal = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mimeReal, $tiposPermitidos)) {
    responder(false, 'Solo se permiten imágenes JPG, PNG, WebP o GIF.', 422);
}

// ── Validar tamaño (máx 2 MB) ────────────────────────────
if ($file['size'] > 2 * 1024 * 1024) {
    responder(false, 'La imagen no puede superar 2 MB.', 422);
}

// ── CORRECCIÓN: Definir y crear la carpeta de destino ─────

$uploadDir = realpath(__DIR__ . '/../../../') . '/profesor/uploads/perfiles/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── Generar nombre único ──────────────────────────────────
$ext      = match($mimeReal) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
    default      => 'jpg'
};
$filename = 'avatar_' . $usuarioId . '_' . time() . '.' . $ext;
$destPath = $uploadDir . $filename;

// ── CORRECCIÓN: URL pública ajustada a la nueva ruta ──────
$publicUrl = BASE_URL . '/profesor/uploads/perfiles/' . $filename;

// ── Eliminar avatar anterior si existe ───────────────────
$stmtOld = $conexion->prepare("SELECT avatar FROM usuarios WHERE id = ?");
$stmtOld->bind_param("i", $usuarioId);
$stmtOld->execute();
$old = $stmtOld->get_result()->fetch_assoc();
$stmtOld->close();

if ($old && $old['avatar']) {
    // Convertimos la URL guardada en base de datos a una ruta física real para borrar el archivo viejo
    $oldPath = str_replace(BASE_URL, realpath(__DIR__ . '/../../../'), $old['avatar']);
    if (file_exists($oldPath)) @unlink($oldPath);
}

// ── Mover archivo ────────────────────────────────────────
if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    responder(false, 'No se pudo guardar el archivo en el servidor.', 500);
}

// ── Guardar ruta en BD ────────────────────────────────────
$stmt = $conexion->prepare("UPDATE usuarios SET avatar = ? WHERE id = ?");
$stmt->bind_param("si", $publicUrl, $usuarioId);

if ($stmt->execute()) {
    $stmt->close();
    $conexion->close();
    responder(true, 'Foto de perfil actualizada.', 200, ['url' => $publicUrl]);
} else {
    $e = $stmt->error;
    $stmt->close();
    $conexion->close();
    // Si falla la BD, borrar el archivo subido
    @unlink($destPath);
    responder(false, 'Error al guardar en la base de datos: ' . $e, 500);
}