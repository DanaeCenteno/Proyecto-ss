<?php

// 1. Importamos de manera segura tu archivo central de autenticación
require_once __DIR__ . "/../../../includes/auth.php"; 

// 2. Iniciamos la sesión usando la función global de tu sistema
iniciarSesion(); 

header('Content-Type: application/json');

// 3. Obtenemos el ID del profesor con tu función nativa (apunta a $_SESSION['user_id'])
$idProfesor = usuarioId(); 

// Validar si el usuario está realmente autenticado en el sistema
if ($idProfesor === 0) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// 4. Procesamos los datos enviados por el método POST (Fetch)
$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID inválido']);
    exit;
}

// 5. Conexión a la base de datos
include "../../config/db.php";

// ✅ Solo puede eliminar el curso si le pertenece al profesor logueado
$stmt = $conexion->prepare("DELETE FROM cursos WHERE id = ? AND profesor_id = ?");
$stmt->bind_param("ii", $id, $idProfesor);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Curso no encontrado o no tienes permisos']);
}