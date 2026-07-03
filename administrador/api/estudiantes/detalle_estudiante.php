<?php

header('Content-Type: application/json; charset=utf-8');

// Cargar dependencias
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . "/../../../includes/auth.php"; 

// Inicializar la sesión usando tu función global
iniciarSesion();

// VALIDACIÓN: Usamos la función nativa de auth.php
$profesor_id = usuarioId(); 

// Si no hay un ID de usuario válido o el rol no corresponde al de un profesor
if (!$profesor_id || $_SESSION['rol'] !== 'profesor') {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'No autorizado. Sesión inválida o rol incorrecto.'
    ]);
    exit;
}

// Obtener parámetros de la URL
$alumno_id = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;
$curso_id  = isset($_GET['curso_id'])  ? (int)$_GET['curso_id']  : 0;

// Validar parámetros
if ($alumno_id <= 0 || $curso_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

// ────────────────────────────────────────────────────────
// 1. Obtener datos del alumno
// ────────────────────────────────────────────────────────

$sqlAlumno = "
    SELECT u.id, u.nombre, u.correo, u.avatar
    FROM usuarios u
    WHERE u.id = ?
    LIMIT 1
";

$stmtAlumno = $conexion->prepare($sqlAlumno);
if (!$stmtAlumno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en preparación de consulta: ' . $conexion->error]);
    exit;
}

$stmtAlumno->bind_param("i", $alumno_id);
$stmtAlumno->execute();
$resultAlumno = $stmtAlumno->get_result();

if ($resultAlumno->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Estudiante no encontrado']);
    $stmtAlumno->close();
    exit;
}

$alumno = $resultAlumno->fetch_assoc();
$stmtAlumno->close();

// ────────────────────────────────────────────────────────
// 2. Obtener datos de inscripción (progreso, estado, etc)
// ────────────────────────────────────────────────────────

$sqlInscripcion = "
    SELECT 
        i.fecha_inscripcion,
        i.completado,
        i.progreso,
        COUNT(DISTINCT l.id) AS total_lecciones
    FROM inscripciones i
    LEFT JOIN cursos c ON c.id = i.curso_id
    LEFT JOIN modulos m ON m.curso_id = c.id
    LEFT JOIN lecciones l ON l.modulo_id = m.id
    WHERE i.usuario_id = ? AND i.curso_id = ?
    GROUP BY i.id
    LIMIT 1
";

$stmtInscripcion = $conexion->prepare($sqlInscripcion);
if (!$stmtInscripcion) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en preparación de consulta']);
    exit;
}

$stmtInscripcion->bind_param("ii", $alumno_id, $curso_id);
$stmtInscripcion->execute();
$resultInscripcion = $stmtInscripcion->get_result();

$inscripcion = $resultInscripcion->num_rows > 0 
    ? $resultInscripcion->fetch_assoc()
    : ['completado' => 0, 'progreso' => 0, 'total_lecciones' => 0, 'fecha_inscripcion' => null];

$stmtInscripcion->close();

// ────────────────────────────────────────────────────────
// 3. Obtener datos del curso y verificar permisos
// ────────────────────────────────────────────────────────

$sqlCurso = "
    SELECT id, titulo, descripcion, profesor_id
    FROM cursos
    WHERE id = ?
    LIMIT 1
";

$stmtCurso = $conexion->prepare($sqlCurso);
if (!$stmtCurso) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en preparación de consulta']);
    exit;
}

$stmtCurso->bind_param("i", $curso_id);
$stmtCurso->execute();
$resultCurso = $stmtCurso->get_result();

if ($resultCurso->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Curso no encontrado']);
    $stmtCurso->close();
    exit;
}

$curso = $resultCurso->fetch_assoc();
$stmtCurso->close();

// Verificar que el profesor es dueño del curso (usando $profesor_id corregido)
if ((int)$curso['profesor_id'] !== (int)$profesor_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para ver este curso']);
    exit;
}

// ────────────────────────────────────────────────────────
// 4. Obtener módulos y lecciones
// ────────────────────────────────────────────────────────

$sqlModulos = "
    SELECT 
        m.id,
        m.titulo,
        m.curso_id
    FROM modulos m
    WHERE m.curso_id = ?
    ORDER BY m.orden ASC
";

$stmtModulos = $conexion->prepare($sqlModulos);
if (!$stmtModulos) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en preparación de consulta']);
    exit;
}

$stmtModulos->bind_param("i", $curso_id);
$stmtModulos->execute();
$resultModulos = $stmtModulos->get_result();
$modulos = [];

while ($modulo = $resultModulos->fetch_assoc()) {
    // Obtener lecciones de este módulo
    $sqlLecciones = "
        SELECT id, titulo, tipo
        FROM lecciones
        WHERE modulo_id = ?
        ORDER BY orden ASC
    ";
    
    $stmtLecciones = $conexion->prepare($sqlLecciones);
    if ($stmtLecciones) {
        $modulo_id = $modulo['id'];
        $stmtLecciones->bind_param("i", $modulo_id);
        $stmtLecciones->execute();
        $resultLecciones = $stmtLecciones->get_result();
        
        $modulo['lecciones'] = [];
        while ($leccion = $resultLecciones->fetch_assoc()) {
            $modulo['lecciones'][] = $leccion;
        }
        $stmtLecciones->close();
    }
    
    $modulos[] = $modulo;
}
$stmtModulos->close();

// ────────────────────────────────────────────────────────
// 5. Construir respuesta completa
// ────────────────────────────────────────────────────────

$respuesta = [
    'success'   => true,
    'alumno'    => array_merge($alumno, $inscripcion),
    'curso'     => $curso,
    'modulos'   => $modulos,
];

echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;