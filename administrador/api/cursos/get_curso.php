<?php
// LEER — trae los cursos del profesor logueado
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';
iniciarSesion();
header('Content-Type: application/json');

if (!estaAutenticado()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$idProfesor = usuarioId();

$stmt = $conexion->prepare("
    SELECT 
        c.id, c.titulo, c.descripcion, c.categoria,
        c.emoji, c.imagen, c.estado, c.duracion_total,
        COUNT(DISTINCT m.id) as total_modulos,
        COUNT(DISTINCT l.id) as total_lecciones
    FROM cursos c
    LEFT JOIN modulos   m ON m.curso_id   = c.id
    LEFT JOIN lecciones l ON l.modulo_id  = m.id
    WHERE c.profesor_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $idProfesor);
$stmt->execute();
$resultado = $stmt->get_result();

$cursos = $resultado->fetch_all(MYSQLI_ASSOC);
echo json_encode(['success' => true, 'data' => $cursos]);