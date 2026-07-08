<?php


require_once __DIR__ . "/../../../includes/auth.php"; 


iniciarSesion(); 

header('Content-Type: application/json');

$idProfesor = usuarioId(); 


if ($idProfesor === 0) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}


include "../../config/db.php";


$stmt = $conexion->prepare("DELETE FROM cursos WHERE id = ? AND profesor_id = ?");
$stmt->bind_param("ii", $id, $idProfesor);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Curso no encontrado o no tienes permisos']);
}