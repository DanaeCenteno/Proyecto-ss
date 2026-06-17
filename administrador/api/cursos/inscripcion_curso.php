
<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION["id"])) {
    echo json_encode(["success" => false, "message" => "No autenticado"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$cursoId = (int)($data['curso_id'] ?? 0);
$usuarioId = $_SESSION["id"];

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