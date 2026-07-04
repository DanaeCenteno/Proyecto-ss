<?php
/**
 * administrador/api/foro/editar_pregunta.php
 *
 * Edita el título y estado de una pregunta
 *
 * POST Parameters:
 *   - pregunta_id: ID de la pregunta
 *   - titulo: Nuevo título (max 150 caracteres)
 *   - estado: abierta | cerrada | resuelta (opcional)
 *
 * Response: JSON
 *   { ok: bool, msg: string }
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

// ── Validaciones iniciales ────────────────────────────────
if ($conexion->connect_errno) {
    responder(false, 'Error de conexión a la BD: ' . $conexion->connect_error, 500);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido.', 405);
}
if (!estaAutenticado()) {
    responder(false, 'Debes iniciar sesión.', 401);
}

$usuarioId  = usuarioId();
$preguntaId = (int) ($_POST['pregunta_id'] ?? 0);
$titulo     = trim($_POST['titulo'] ?? '');
$estado     = trim($_POST['estado'] ?? '');

if ($preguntaId <= 0) {
    responder(false, 'ID de pregunta inválido', 422);
}
if (empty($titulo)) {
    responder(false, 'El título es obligatorio', 422);
}
if (mb_strlen($titulo) > 150) {
    responder(false, 'El título no puede superar 150 caracteres', 422);
}
if (!empty($estado) && !in_array($estado, ['abierta', 'cerrada', 'resuelta'])) {
    responder(false, 'Estado inválido', 422);
}

// ── Verificar que el usuario es propietario ───────────────
$stmtChk = $conexion->prepare("SELECT usuario_id FROM foro_preguntas WHERE id = ?");
$stmtChk->bind_param("i", $preguntaId);
$stmtChk->execute();
$pregunta = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if (!$pregunta) {
    responder(false, 'La pregunta no existe', 404);
}
if ($pregunta['usuario_id'] !== $usuarioId) {
    responder(false, 'No tienes permiso para editar esta pregunta', 403);
}

// ── Actualizar pregunta ────────────────────────────────────
if (!empty($estado)) {
    $stmt = $conexion->prepare("UPDATE foro_preguntas SET titulo = ?, estado = ? WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("ssii", $titulo, $estado, $preguntaId, $usuarioId);
} else {
    $stmt = $conexion->prepare("UPDATE foro_preguntas SET titulo = ? WHERE id = ? AND usuario_id = ?");
    $stmt->bind_param("sii", $titulo, $preguntaId, $usuarioId);
}

if ($stmt->execute()) {
    $stmt->close();
    $conexion->close();
    responder(true, 'Pregunta actualizada correctamente', 200, [
        'pregunta_id'  => $preguntaId,
        'nuevo_titulo' => $titulo
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conexion->close();
    responder(false, 'Error al actualizar: ' . $error, 500);
}