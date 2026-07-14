<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');
function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$id        = (int)($_POST['id']         ?? 0);
$destacada = (int)($_POST['destacada']  ?? 0);

if ($id === 0) resp(false, 'ID inválido.');
$destacada = $destacada ? 1 : 0;

// Asegurar que la columna existe (migración automática segura)
$conexion->query("ALTER TABLE foro_preguntas ADD COLUMN IF NOT EXISTS destacada TINYINT(1) NOT NULL DEFAULT 0");

$stmt = $conexion->prepare("UPDATE foro_preguntas SET destacada = ? WHERE id = ?");
$stmt->bind_param("ii", $destacada, $id);
if ($stmt->execute())
    resp(true, $destacada ? 'Pregunta destacada.' : 'Destaque eliminado.');
resp(false, 'Error al actualizar.');