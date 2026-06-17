<?php
// LEER — trae los cursos del profesor logueado
session_start();
include "../../config/db.php";
header('Content-Type: application/json');

if (!isset($_SESSION["id"])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$idProfesor = $_SESSION["id"];

$resultado = $conexion->query("
    SELECT 
        c.id, c.titulo, c.descripcion, c.categoria,
        c.emoji, c.imagen, c.estado, c.duracion_total,
        COUNT(DISTINCT m.id) as total_modulos,
        COUNT(DISTINCT l.id) as total_lecciones
    FROM cursos c
    LEFT JOIN modulos   m ON m.curso_id   = c.id
    LEFT JOIN lecciones l ON l.modulo_id  = m.id
    WHERE c.profesor_id = $idProfesor
    GROUP BY c.id
    ORDER BY c.created_at DESC
");

$cursos = $resultado->fetch_all(MYSQLI_ASSOC);
echo json_encode(['success' => true, 'data' => $cursos]);