<?php

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
iniciarSesion();
header('Content-Type: application/json');

if (!estaAutenticado()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$body        = json_decode(file_get_contents('php://input'), true);
$id          = (int)($body['id']          ?? 0);
$titulo      = trim($body['titulo']       ?? '');
$tipo        = trim($body['tipo']         ?? 'video');
$url         = trim($body['url']          ?? '');
$descripcion = trim($body['descripcion']  ?? '');
$idProfesor  = usuarioId();

if (!$id || !$titulo) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}


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