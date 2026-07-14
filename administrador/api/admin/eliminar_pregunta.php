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

$conexion->begin_transaction();
try {
    $dr = $conexion->prepare("DELETE FROM foro_respuestas WHERE pregunta_id = ?");
    $dr->bind_param("i", $id); $dr->execute(); $dr->close();

    $dp = $conexion->prepare("DELETE FROM foro_preguntas WHERE id = ?");
    $dp->bind_param("i", $id); $dp->execute(); $dp->close();

    $conexion->commit();
    resp(true, 'Pregunta y sus respuestas eliminadas correctamente.');
} catch (Exception $e) {
    $conexion->rollback();
    resp(false, 'Error al eliminar: ' . $e->getMessage());
}