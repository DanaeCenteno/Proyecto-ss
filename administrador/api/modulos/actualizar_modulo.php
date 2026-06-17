<?php
// ACTUALIZAR — renombra un módulo
session_start();
include "../../config/db.php";
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$id     = (int)($body['id']     ?? 0);
$titulo = trim($body['titulo']  ?? '');

if (!$id || !$titulo) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// ✅ Verifica que el módulo pertenece a un curso del profesor
$stmt = $conexion->prepare("
    UPDATE modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    SET m.titulo = ?
    WHERE m.id = ? AND c.profesor_id = ?
");
$idProfesor = $_SESSION["id"];
$stmt->bind_param("sii", $titulo, $id, $idProfesor);
$stmt->execute();

echo json_encode(['success' => $stmt->affected_rows >= 0]);