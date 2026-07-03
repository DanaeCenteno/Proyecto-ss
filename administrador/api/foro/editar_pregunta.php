<?php

ob_start();

require_once __DIR__ . '/../../config/db.php';   // usa la misma conexión que el resto de la app
require_once __DIR__ . '/../../../includes/auth.php';

iniciarSesion();

header('Content-Type: application/json; charset=utf-8');

function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

if ($conexion->connect_errno) {
    responder(false, 'Error de conexión a la BD: ' . $conexion->connect_error, 500);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido.', 405);
}
if (!estaAutenticado()) {
    responder(false, 'Debes iniciar sesión para responder una pregunta.', 401);
}

$usuarioId  = usuarioId();
$preguntaId = (int) ($_POST['pregunta_id'] ?? 0);
$contenido  = trim($_POST['contenido'] ?? '');


ob_start();
header('Content-Type: application/json; charset=utf-8');

// Conexión
$server = "localhost";
$user = "root";
$password = "";
$db = "eduforge";

$conexion = new mysqli($server, $user, $password, $db);
$conexion->set_charset("utf8mb4");

// ═══════════════════════════════════════════════════════════════════════════
// FUNCIÓN AUXILIAR
// ═══════════════════════════════════════════════════════════════════════════

function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════════════════
// VALIDACIONES INICIALES
// ═══════════════════════════════════════════════════════════════════════════

// Verificar conexión
if ($conexion->connect_errno) {
    responder(false, 'Error de conexión: ' . $conexion->connect_error, 500);
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido', 405);
}

// Usuario logueado
if (empty( $_SESSION['user_id'])) {
    responder(false, 'Debes iniciar sesión', 401);
}

// Parámetros
$usuarioId = (int) $_SESSION['user_id'];
$preguntaId = (int)($_POST['pregunta_id'] ?? 0);
$titulo = trim($_POST['titulo'] ?? '');
$estado = trim($_POST['estado'] ?? '');

// Validaciones
if ($preguntaId <= 0) {
    responder(false, 'ID de pregunta inválido', 422);
}

if (empty($titulo)) {
    responder(false, 'El título es obligatorio', 422);
}

if (mb_strlen($titulo) > 150) {
    responder(false, 'El título no puede superar 150 caracteres', 422);
}

// ✅ Estado es opcional, pero si se proporciona, debe ser válido
if (!empty($estado) && !in_array($estado, ['abierta', 'cerrada', 'resuelta'])) {
    responder(false, 'Estado inválido', 422);
}

// ═══════════════════════════════════════════════════════════════════════════
// VERIFICAR QUE EL USUARIO ES PROPIETARIO
// ═══════════════════════════════════════════════════════════════════════════

$stmtChk = $conexion->prepare("
    SELECT usuario_id 
    FROM foro_preguntas 
    WHERE id = ?
");
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

// ═══════════════════════════════════════════════════════════════════════════
// ACTUALIZAR PREGUNTA
// ═══════════════════════════════════════════════════════════════════════════

// Preparar consulta según si se actualiza estado o no
if (!empty($estado)) {
    $stmt = $conexion->prepare("
        UPDATE foro_preguntas 
        SET titulo = ?, estado = ? 
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->bind_param("ssii", $titulo, $estado, $preguntaId, $usuarioId);
} else {
    $stmt = $conexion->prepare("
        UPDATE foro_preguntas 
        SET titulo = ? 
        WHERE id = ? AND usuario_id = ?
    ");
    $stmt->bind_param("sii", $titulo, $preguntaId, $usuarioId);
}

if ($stmt->execute()) {
    $stmt->close();
    $conexion->close();
    
    // ✅ ÉXITO
    responder(true, 'Pregunta actualizada correctamente', 200, [
        'pregunta_id' => $preguntaId,
        'nuevo_titulo' => $titulo
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conexion->close();
    responder(false, 'Error al actualizar: ' . $error, 500);
}
?>