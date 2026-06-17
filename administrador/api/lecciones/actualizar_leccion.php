<?php
// ACTUALIZAR — edita título, tipo, url, descripción de una lección
session_start();
include "../../config/db.php";
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$body        = json_decode(file_get_contents('php://input'), true);
$id          = (int)($body['id']          ?? 0);
$titulo      = trim($body['titulo']       ?? '');
$tipo        = trim($body['tipo']         ?? 'video');
$url         = trim($body['url']          ?? '');
$descripcion = trim($body['descripcion']  ?? '');
$idProfesor  = $_SESSION["id"];

if (!$id || !$titulo) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// ✅ Verifica que la lección pertenece a un curso del profesor
$stmt = $conexion->prepare("
    UPDATE lecciones l
    INNER JOIN modulos  m ON l.modulo_id  = m.id
    INNER JOIN cursos   c ON m.curso_id   = c.id
    SET l.titulo = ?, l.tipo = ?, l.url = ?, l.descripcion = ?
    WHERE l.id = ? AND c.profesor_id = ?
");
$stmt->bind_param("ssssii", $titulo, $tipo, $url, $descripcion, $id, $idProfesor);
$stmt->execute();

echo json_encode(['success' => $stmt->affected_rows >= 0]);