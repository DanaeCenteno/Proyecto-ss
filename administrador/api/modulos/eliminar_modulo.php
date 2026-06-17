<?php
// ELIMINAR — borra módulo y sus lecciones en cascada
session_start();
include "../../config/db.php";
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true);
$id         = (int)($body['id'] ?? 0);
$idProfesor = $_SESSION["id"];

$stmt = $conexion->prepare("
    DELETE m FROM modulos m
    INNER JOIN cursos c ON m.curso_id = c.id
    WHERE m.id = ? AND c.profesor_id = ?
");
$stmt->bind_param("ii", $id, $idProfesor);
$stmt->execute();

echo json_encode(['success' => $stmt->affected_rows > 0]);