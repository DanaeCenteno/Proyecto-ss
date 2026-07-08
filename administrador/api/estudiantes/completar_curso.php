<?php


header('Content-Type: application/json; charset=utf-8');
ob_start();

// 2. Cargas del sistema
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

iniciarSesion();

function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    if (ob_get_length()) {
        ob_end_clean(); 
    }
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}



if ($conexion->connect_errno)              responder(false, 'Error de BD.',         500);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método no permitido.', 405);
if (!estaAutenticado())                    responder(false, 'Debes iniciar sesión.', 401);
if (usuarioRol() !== ROL_ESTUDIANTE)       responder(false, 'Solo estudiantes.',    403);

$estudianteId = usuarioId();
$cursoId      = (int) ($_POST['curso_id'] ?? 0);

if ($cursoId <= 0) responder(false, 'Parámetros inválidos.', 422);

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
        responder(false, 'No estás inscrito en este curso.', 403);
    }
    $stmtIns->close();

    // ── 2. Marcar TODAS las lecciones del curso como vistas ───
    //    Inserta en progreso_lecciones cada lección que aún no esté registrada.
    $stmtAll = $conexion->prepare("
        INSERT IGNORE INTO progreso_lecciones (usuario_id, curso_id, leccion_id)
        SELECT ?, ?, l.id
        FROM lecciones l
        JOIN modulos m ON m.id = l.modulo_id
        WHERE m.curso_id = ?
    ");
    $stmtAll->bind_param("iii", $estudianteId, $cursoId, $cursoId);
    $stmtAll->execute();
    $stmtAll->close();

    // ── 3. Poner progreso = 100 y completado = 1 ──────────────
    $stmtUpd = $conexion->prepare(
        "UPDATE inscripciones SET progreso = 100, completado = 1
         WHERE usuario_id = ? AND curso_id = ?"
    );
    $stmtUpd->bind_param("ii", $estudianteId, $cursoId);
    $stmtUpd->execute();
    $stmtUpd->close();

    $conexion->close();

    responder(true, 'Curso completado.', 200, [
        'progreso'   => 100,
        'completado' => true,
    ]);

} catch (\mysqli_sql_exception $e) {
    responder(false, 'ERROR MySQL: ' . $e->getMessage(), 500);
}