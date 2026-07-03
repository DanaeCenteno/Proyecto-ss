<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
ob_start();
header('Content-Type: application/json; charset=utf-8');

function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean(); http_response_code($code);
    echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg], $extra), JSON_UNESCAPED_UNICODE); exit;
}

if (!estaAutenticado())                    responder(false,'No autenticado.',401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false,'Método no permitido.',405);

$usuarioId   = usuarioId();
$bio = trim($_POST['bio'] ?? '');

// Límite razonable para una bio de perfil
if (mb_strlen($bio) > 500) {
    responder(false, 'La descripción no puede superar los 500 caracteres.', 422);
}

$stmt = $conexion->prepare("UPDATE usuarios SET bio = ? WHERE id = ?");
$stmt->bind_param("si", $bio, $usuarioId);
$stmt->execute()
    ? responder(true, 'Descripción actualizada correctamente.', 200, ['bio' => $bio])
    : responder(false, 'Error al actualizar.', 500);