<?php
// ELIMINAR — borra una lección
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
    DELETE l FROM lecciones l
    INNER JOIN modulos m ON l.modulo_id = m.id
    INNER JOIN cursos  c ON m.curso_id  = c.id
    WHERE l.id = ? AND c.profesor_id = ?
");
$stmt->bind_param("ii", $id, $idProfesor);
$stmt->execute();

echo json_encode(['success' => $stmt->affected_rows > 0]);