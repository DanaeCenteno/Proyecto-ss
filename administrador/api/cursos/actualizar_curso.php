<?php
// ACTUALIZAR — edita título, descripción, estado, etc.
session_start();
include "../../config/db.php";
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$body        = json_decode(file_get_contents('php://input'), true);
$id          = (int)($body['id']             ?? 0);
$titulo      = trim($body['titulo']          ?? '');
$descripcion = trim($body['descripcion']     ?? '');
$categoria   = trim($body['categoria']       ?? '');
$estado      = trim($body['estado']          ?? 'borrador');
$duracion    = (int)($body['duracion_total'] ?? 0);
$idProfesor  = $_SESSION["id"];

if (!$id || !$titulo) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// ✅ Solo puede editar sus propios cursos
$stmt = $conexion->prepare("
    UPDATE cursos 
    SET titulo = ?, descripcion = ?, categoria = ?, estado = ?, duracion_total = ?
    WHERE id = ? AND profesor_id = ?
");
$stmt->bind_param("sssssii", $titulo, $descripcion, $categoria, $estado, $duracion, $id, $idProfesor);
$stmt->execute();

if ($stmt->affected_rows >= 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $stmt->error]);
}