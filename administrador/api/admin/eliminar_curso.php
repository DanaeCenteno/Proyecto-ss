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
    // Eliminar lecciones de todos los módulos del curso
    $mods = $conexion->prepare("SELECT id FROM modulos WHERE curso_id = ?");
    $mods->bind_param("i", $id); $mods->execute();
    $moduloIds = array_column($mods->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $mods->close();

    foreach ($moduloIds as $mid) {
        $dl = $conexion->prepare("DELETE FROM lecciones WHERE modulo_id = ?");
        $dl->bind_param("i", $mid); $dl->execute(); $dl->close();
    }

    $conexion->prepare("DELETE FROM modulos WHERE curso_id = ?")->execute() ||
        $conexion->prepare("DELETE FROM modulos WHERE curso_id = ?")->bind_param("i", $id);

    $dm = $conexion->prepare("DELETE FROM modulos WHERE curso_id = ?");
    $dm->bind_param("i", $id); $dm->execute(); $dm->close();

    $di = $conexion->prepare("DELETE FROM inscripciones WHERE curso_id = ?");
    $di->bind_param("i", $id); $di->execute(); $di->close();

    $dc = $conexion->prepare("DELETE FROM cursos WHERE id = ?");
    $dc->bind_param("i", $id); $dc->execute(); $dc->close();

    $conexion->commit();
    resp(true, 'Curso eliminado correctamente.');
} catch (Exception $e) {
    $conexion->rollback();
    resp(false, 'Error al eliminar: ' . $e->getMessage());
}