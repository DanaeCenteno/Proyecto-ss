<?php
ob_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

iniciarSesion();
header('Content-Type: application/json; charset=utf-8');

function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

if ($conexion->connect_errno)              responder(false, 'Error de BD.',          500);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método no permitido.',  405);
if (!estaAutenticado())                    responder(false, 'Debes iniciar sesión.',  401);
if (usuarioRol() !== ROL_ESTUDIANTE)       responder(false, 'Solo estudiantes. (rol actual: ' . usuarioRol() . ')', 403);

$estudianteId = usuarioId();
$cursoId      = (int) ($_POST['curso_id']   ?? 0);
$leccionId    = (int) ($_POST['leccion_id'] ?? 0);

if ($cursoId <= 0 || $leccionId <= 0) responder(false, 'Parámetros inválidos.', 422);

try {
    // ── 1. Verificar inscripción ──────────────────────────────
    $stmtIns = $conexion->prepare(
        "SELECT id FROM inscripciones WHERE usuario_id = ? AND curso_id = ?"
    );
    $stmtIns->bind_param("ii", $estudianteId, $cursoId);
    $stmtIns->execute();
    $stmtIns->store_result();
    if ($stmtIns->num_rows === 0) {
        $stmtIns->close();
        responder(false, 'No estás inscrito en este curso.', 403, [
            'debug' => compact('estudianteId', 'cursoId', 'leccionId')
        ]);
    }
    $stmtIns->close();

    // ── 2. Registrar lección vista ────────────────────────────
    $stmtLec = $conexion->prepare(
        "INSERT IGNORE INTO progreso_lecciones (usuario_id, curso_id, leccion_id)
         VALUES (?, ?, ?)"
    );
    $stmtLec->bind_param("iii", $estudianteId, $cursoId, $leccionId);
    $stmtLec->execute();
    $filasInsertadas = $stmtLec->affected_rows; // 1 = insertó, 0 = ya existía
    $stmtLec->close();

    // ── 3. Contar lecciones vistas vs total del curso ─────────
    $stmtStats = $conexion->prepare("
        SELECT
            (SELECT COUNT(*) FROM lecciones l
             JOIN modulos m ON m.id = l.modulo_id
             WHERE m.curso_id = ?)                AS total,
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
    $filasActualizadas = $stmtUpd->affected_rows;
    $stmtUpd->close();

    $conexion->close();

    responder(true, 'Progreso guardado.', 200, [
        'progreso'          => $porcentaje,
        'vistas'            => $vistas,
        'total'             => $total,
        'completado'        => (bool) $completado,
        'filasInsertadas'   => $filasInsertadas,
        'filasActualizadas' => $filasActualizadas,
    ]);

} catch (\mysqli_sql_exception $e) {
    responder(false, 'ERROR MySQL: ' . $e->getMessage(), 500, [
        'codigo' => $e->getCode(),
        'debug'  => compact('estudianteId', 'cursoId', 'leccionId'),
    ]);
}