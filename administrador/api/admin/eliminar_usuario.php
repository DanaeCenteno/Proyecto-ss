<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');
function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$id = (int)($_POST['id'] ?? 0);
if ($id === 0) resp(false, 'ID inválido.');
if ($id === usuarioId()) resp(false, 'No puedes eliminar tu propia cuenta.');

$stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute() && $stmt->affected_rows > 0) resp(true, 'Usuario eliminado correctamente.');
resp(false, 'No se encontró el usuario o ya fue eliminado.');