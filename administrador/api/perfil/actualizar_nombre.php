<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
ob_start();
header('Content-Type: application/json; charset=utf-8');

function responder(bool $ok, string $msg, int $code = 200): void {
    ob_end_clean(); http_response_code($code);
    echo json_encode(['ok'=>$ok,'msg'=>$msg],JSON_UNESCAPED_UNICODE); exit;
}

if (!estaAutenticado())                    responder(false,'No autenticado.',401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false,'Método no permitido.',405);

$usuarioId = usuarioId();
$nombre    = trim($_POST['nombre'] ?? '');

if (strlen($nombre) < 3) responder(false,'El nombre debe tener al menos 3 caracteres.',422);

$stmt = $conexion->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?");
$stmt->bind_param("si",$nombre,$usuarioId);
$stmt->execute() ? responder(true,'Nombre actualizado correctamente.') : responder(false,'Error al actualizar.',500);