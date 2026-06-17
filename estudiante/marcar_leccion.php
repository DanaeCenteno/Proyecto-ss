<?php
ob_start();

require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();
header('Content-Type: application/json; charset=utf-8');

// DEBUG
echo json_encode([
    'method'      => $_SERVER['REQUEST_METHOD'],
    'autenticado' => estaAutenticado(),
    'rol'         => usuarioRol(),
    'ROL_ESTUDIANTE'  => ROL_ESTUDIANTE,
    'user_id'     => usuarioId(),
    'curso_id'    => $_POST['curso_id']  ?? 'no viene',
    'leccion_id'  => $_POST['leccion_id'] ?? 'no viene',
]);
exit;
$estudianteId = usuarioId();
$cursoId      = (int) ($_POST['curso_id']   ?? 0);
$leccionId    = (int) ($_POST['leccion_id'] ?? 0);

if ($cursoId <= 0 || $leccionId <= 0) responder(false, 'Parámetros inválidos.', 422);

// ── 1. Verificar inscripción ──────────────────────────────
$stmtIns = $conexion->prepare(
    "SELECT id FROM inscripciones WHERE usuario_id = ? AND curso_id = ?"
);
$stmtIns->bind_param("ii", $estudianteId, $cursoId);
$stmtIns->execute();
$stmtIns->store_result();
if ($stmtIns->num_rows === 0) {
    $stmtIns->close();
    responder(false, 'No estás inscrito en este curso.', 403);
}
$stmtIns->close();

// ── 2. Registrar lección vista (INSERT IGNORE evita duplicados) ──
$stmtLec = $conexion->prepare(
    "INSERT IGNORE INTO progreso_lecciones (usuario_id, curso_id, leccion_id)
     VALUES (?, ?, ?)"
);
$stmtLec->bind_param("iii", $estudianteId, $cursoId, $leccionId);
$stmtLec->execute();
$stmtLec->close();

// ── 3. Contar lecciones vistas vs total del curso ─────────
$stmtStats = $conexion->prepare("
    SELECT
        (SELECT COUNT(*) FROM lecciones l
         JOIN modulos m ON m.id = l.modulo_id
         WHERE m.curso_id = ?)                  AS total,
        (SELECT COUNT(*) FROM progreso_lecciones
         WHERE usuario_id = ? AND curso_id = ?) AS vistas
");
$stmtStats->bind_param("iii", $cursoId, $estudianteId, $cursoId);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc();
$stmtStats->close();

$total      = (int) $stats['total'];
$vistas     = (int) $stats['vistas'];
$porcentaje = $total > 0 ? (int) round($vistas / $total * 100) : 0;
$completado = ($porcentaje >= 100) ? 1 : 0;

// ── 4. Actualizar inscripciones.progreso y .completado ────
$stmtUpd = $conexion->prepare(
    "UPDATE inscripciones SET progreso = ?, completado = ?
     WHERE usuario_id = ? AND curso_id = ?"
);
$stmtUpd->bind_param("iiii", $porcentaje, $completado, $estudianteId, $cursoId);
$stmtUpd->execute();
$stmtUpd->close();

$conexion->close();

responder(true, 'Progreso guardado.', 200, [
    'progreso'   => $porcentaje,
    'vistas'     => $vistas,
    'total'      => $total,
    'completado' => (bool) $completado,
]);