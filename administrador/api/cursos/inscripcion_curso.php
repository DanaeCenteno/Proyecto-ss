<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');

if (!estaAutenticado()) {
    echo json_encode(["success" => false, "message" => "No autenticado"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$cursoId = (int)($data['curso_id'] ?? 0);
$usuarioId = usuarioId();

// Verificar que no este ya inscrito
$stmt = $conexion->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND curso_id = ?");
$stmt->bind_param("ii", $usuarioId, $cursoId);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(["success" => true, "message" => "Ya inscrito"]);
    exit;
}

// Inscribir
$stmt = $conexion->prepare("INSERT INTO inscripciones (usuario_id, curso_id) VALUES (?, ?)");
$stmt->bind_param("ii", $usuarioId, $cursoId);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "message" => $stmt->error]);
}