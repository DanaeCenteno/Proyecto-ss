<?php
/**
 * administrador/api/foro/editar_respuesta.php
 *
 * POST Parameters:
 *   - respuesta_id: ID de la respuesta
 *   - contenido: nuevo contenido
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

if ($conexion->connect_errno)              responder(false, 'Error de BD.', 500);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método no permitido.', 405);
if (!estaAutenticado())                    responder(false, 'Debes iniciar sesión.', 401);

$usuarioId   = usuarioId();
$respuestaId = (int) ($_POST['respuesta_id'] ?? 0);
$contenido   = trim($_POST['contenido'] ?? '');

if ($respuestaId <= 0)             responder(false, 'ID inválido.', 422);
if (strip_tags($contenido) === '') responder(false, 'El contenido no puede estar vacío.', 422);

// ── Verificar propiedad ───────────────────────────────────
$stmtChk = $conexion->prepare("SELECT usuario_id FROM foro_respuestas WHERE id = ?");
$stmtChk->bind_param("i", $respuestaId);
$stmtChk->execute();
$row = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if (!$row)                             responder(false, 'Respuesta no encontrada.', 404);
if ($row['usuario_id'] !== $usuarioId) responder(false, 'Sin permiso.', 403);

// ── Actualizar ─────────────────────────────────────────────
$stmt = $conexion->prepare("UPDATE foro_respuestas SET contenido = ? WHERE id = ? AND usuario_id = ?");
$stmt->bind_param("sii", $contenido, $respuestaId, $usuarioId);

if ($stmt->execute()) {
    $stmt->close();
    $conexion->close();
    responder(true, 'Respuesta actualizada.', 200, ['respuesta_id' => $respuestaId]);
} else {
    $e = $stmt->error;
    $stmt->close();
    $conexion->close();
    responder(false, 'Error: ' . $e, 500);
}