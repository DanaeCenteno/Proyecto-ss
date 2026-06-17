<?php
/**
 * administrador/api/estudiante/inscribirse.php
 * Inscribe al estudiante en un curso
 */
if (session_status() === PHP_SESSION_NONE) session_start();
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

iniciarSesion();

function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean(); http_response_code($code);
    echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!estaAutenticado())                      responder(false, 'Debes iniciar sesión.', 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')   responder(false, 'Método no permitido.', 405);

$usuarioId = usuarioId();
$cursoId   = (int)($_POST['curso_id'] ?? 0);

if ($cursoId <= 0) responder(false, 'Curso inválido.', 422);

// ── Verificar que el curso existe y está publicado ─────────
$stmtC = $conexion->prepare("SELECT id, titulo FROM cursos WHERE id = ? AND estado = 'publicado'");
$stmtC->bind_param("i", $cursoId);
$stmtC->execute();
$curso = $stmtC->get_result()->fetch_assoc();
$stmtC->close();

if (!$curso) responder(false, 'El curso no existe o no está disponible.', 404);

// ── Verificar que no esté ya inscrito ─────────────────────
$stmtChk = $conexion->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND curso_id = ?");
$stmtChk->bind_param("ii", $usuarioId, $cursoId);
$stmtChk->execute();
$yaInscrito = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if ($yaInscrito) responder(false, 'Ya estás inscrito en este curso.', 409);

// ── Insertar inscripción ───────────────────────────────────
$stmt = $conexion->prepare("
    INSERT INTO inscripciones (usuario_id, curso_id, fecha_inscripcion)
    VALUES (?, ?, NOW())
");
$stmt->bind_param("ii", $usuarioId, $cursoId);

if ($stmt->execute()) {
    $stmt->close(); $conexion->close();
    responder(true, '¡Inscripción exitosa!', 200, [
        'curso_id'    => $cursoId,
        'curso_titulo'=> $curso['titulo']
    ]);
} else {
    $e = $stmt->error; $stmt->close(); $conexion->close();
    responder(false, 'Error al inscribirse: ' . $e, 500);
}