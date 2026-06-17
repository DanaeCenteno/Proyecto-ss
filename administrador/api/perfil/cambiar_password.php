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
$actual    = $_POST['actual'] ?? '';
$nueva     = $_POST['nueva']  ?? '';

if (strlen($nueva) < 8) responder(false,'La nueva contraseña debe tener al menos 8 caracteres.',422);

// Verificar contraseña actual
$stmt = $conexion->prepare("SELECT password FROM usuarios WHERE id = ?");
$stmt->bind_param("i",$usuarioId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($actual, $row['password'])) {
    responder(false,'La contraseña actual es incorrecta.',403);
}

$hash = password_hash($nueva, PASSWORD_DEFAULT);
$stmt2 = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
$stmt2->bind_param("si",$hash,$usuarioId);
$stmt2->execute() ? responder(true,'Contraseña actualizada correctamente.') : responder(false,'Error al actualizar.',500);