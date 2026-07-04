<?php
/**
 * administrador/api/foro/eliminar_respuesta.php
 *
 * POST Parameters:
 *   - respuesta_id: ID de la respuesta a eliminar
 *
 * Response: JSON { ok: bool, msg: string }
 */

ob_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

iniciarSesion();

header('Content-Type: application/json; charset=utf-8');

function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Validaciones ──────────────────────────────────────────
if ($conexion->connect_errno)              responder(false, 'Error de conexión.', 500);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método no permitido.', 405);
if (!estaAutenticado())                    responder(false, 'Debes iniciar sesión.', 401);

$usuarioId   = usuarioId();
$respuestaId = (int) ($_POST['respuesta_id'] ?? 0);

if ($respuestaId <= 0) responder(false, 'ID de respuesta inválido.', 422);

// ── Verificar propiedad ───────────────────────────────────
$stmtChk = $conexion->prepare("SELECT usuario_id FROM foro_respuestas WHERE id = ?");
$stmtChk->bind_param("i", $respuestaId);
$stmtChk->execute();
$row = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if (!$row)                             responder(false, 'La respuesta no existe.', 404);
if ($row['usuario_id'] !== $usuarioId) responder(false, 'No tienes permiso para eliminar esta respuesta.', 403);

// ── Eliminar votos asociados (si existe la tabla) ─────────
$tablaVotos = $conexion->query("SHOW TABLES LIKE 'foro_votos'")->num_rows > 0;
if ($tablaVotos) {
    $stmtV = $conexion->prepare("DELETE FROM foro_votos WHERE respuesta_id = ?");
    $stmtV->bind_param("i", $respuestaId);
    $stmtV->execute();
    $stmtV->close();
}

// ── Eliminar respuesta ────────────────────────────────────
$stmt = $conexion->prepare("DELETE FROM foro_respuestas WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("ii", $respuestaId, $usuarioId);

if ($stmt->execute()) {
    $stmt->close();
    $conexion->close();
    responder(true, 'Respuesta eliminada correctamente.', 200, ['respuesta_id' => $respuestaId]);
} else {
    $e = $stmt->error;
    $stmt->close();
    $conexion->close();
    responder(false, 'Error al eliminar: ' . $e, 500);
}