<?php

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
iniciarSesion();
header('Content-Type: application/json');

if (!estaAutenticado()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$id         = (int)($body['id'] ?? 0);
$idProfesor = usuarioId();

$stmt = $conexion->prepare("
    DELETE m FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE m.id = ? AND c.profesor_id = ?
");
$stmt->bind_param("ii", $id, $idProfesor);
$stmt->execute();

echo json_encode(['success' => $stmt->affected_rows > 0]);