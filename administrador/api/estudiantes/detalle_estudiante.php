<?php
// administrador/api/estudiantes/detalle_estudiante.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

iniciarSesion();

if (!estaAutenticado() || usuarioRol() !== ROL_PROFESOR) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
    exit;
}

$uid      = usuarioId();
$alumnoId = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : 0;
$cursoId  = isset($_GET['curso_id'])  ? (int)$_GET['curso_id']  : 0;

if ($alumnoId <= 0 || $cursoId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parámetros inválidos.']);
    exit;
}

// Verificar que el curso pertenece al profesor
$stmtCheck = $conexion->prepare("SELECT id, titulo FROM cursos WHERE id = ? AND profesor_id = ?");
$stmtCheck->bind_param("ii", $cursoId, $uid);
$stmtCheck->execute();
$curso = $stmtCheck->get_result()->fetch_assoc();
$stmtCheck->close();

if (!$curso) {
    echo json_encode(['success' => false, 'error' => 'Curso no encontrado.']);
    exit;
}

// Datos del alumno con progreso desde inscripciones
// y también desde tabla `progreso` si existe un registro más actualizado
$stmtAlumno = $conexion->prepare("
    SELECT
        u.id, u.nombre, u.correo, u.avatar,
        i.completado,
        GREATEST(i.progreso, COALESCE(p.progreso, 0)) AS progreso,
        COUNT(DISTINCT l.id) AS total_lecciones
    FROM usuarios u
    INNER JOIN inscripciones i ON i.usuario_id = u.id AND i.curso_id = ?
    LEFT  JOIN progreso p       ON p.usuario_id = u.id AND p.curso_id = ?
    LEFT  JOIN modulos   m      ON m.curso_id  = ?
    LEFT  JOIN lecciones l      ON l.modulo_id = m.id
    WHERE u.id = ?
    GROUP BY u.id, i.completado, i.progreso, p.progreso
");
$stmtAlumno->bind_param("iiii", $cursoId, $cursoId, $cursoId, $alumnoId);
$stmtAlumno->execute();
$alumno = $stmtAlumno->get_result()->fetch_assoc();
$stmtAlumno->close();

if (!$alumno) {
    echo json_encode(['success' => false, 'error' => 'Estudiante no encontrado.']);
    exit;
}

// Módulos con sus lecciones
$stmtModulos = $conexion->prepare("
    SELECT m.id AS modulo_id, m.titulo AS modulo_titulo, m.orden AS modulo_orden,
           l.id AS leccion_id, l.titulo AS leccion_titulo, l.tipo AS leccion_tipo, l.orden AS leccion_orden
    FROM modulos m
    LEFT JOIN lecciones l ON l.modulo_id = m.id
    WHERE m.curso_id = ?
    ORDER BY m.orden ASC, l.orden ASC
");
$stmtModulos->bind_param("i", $cursoId);
$stmtModulos->execute();
$rows = $stmtModulos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtModulos->close();

// Agrupar por módulo
$modulosMap = [];
foreach ($rows as $row) {
    $mid = $row['modulo_id'];
    if (!isset($modulosMap[$mid])) {
        $modulosMap[$mid] = [
            'titulo'    => $row['modulo_titulo'],
            'lecciones' => [],
        ];
    }
    if ($row['leccion_id']) {
        $modulosMap[$mid]['lecciones'][] = [
            'titulo' => $row['leccion_titulo'],
            'tipo'   => $row['leccion_tipo'],
        ];
    }
}

// Limpiar avatar: si es URL absoluta usar directo; si es ruta relativa verificar existencia
$avatarAlumno = null;
if (!empty($alumno['avatar'])) {
    $av = $alumno['avatar'];
    if (str_starts_with($av, 'http')) {
        $avatarAlumno = $av;
    } elseif (file_exists(__DIR__ . '/../../../' . ltrim($av, '/'))) {
        $avatarAlumno = '/' . ltrim($av, '/');
    }
}

echo json_encode([
    'success' => true,
    'alumno'  => [
        'id'             => (int)$alumno['id'],
        'nombre'         => $alumno['nombre'],
        'correo'         => $alumno['correo'],
        'avatar'         => $avatarAlumno,
        'completado'     => (bool)$alumno['completado'],
        'progreso'       => (int)$alumno['progreso'],
        'total_lecciones'=> (int)$alumno['total_lecciones'],
    ],
    'curso'   => [
        'id'     => (int)$curso['id'],
        'titulo' => $curso['titulo'],
    ],
    'modulos' => array_values($modulosMap),
]);