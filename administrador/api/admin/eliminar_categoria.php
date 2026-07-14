<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');

function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$nombre = trim($_POST['nombre'] ?? '');
if ($nombre === '') resp(false, 'Nombre inválido.');

$stmt = $conexion->prepare("UPDATE cursos SET categoria = NULL WHERE categoria = ?");
$stmt->bind_param("s", $nombre);
$stmt->execute();
$afectados = $stmt->affected_rows;
$stmt->close();

resp(true, 'Categoría eliminada. ' . ($afectados > 0 ? "$afectados curso(s) quedaron sin categoría." : ''));