<?php
// ── Buffer: captura cualquier output accidental ───────────
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// ── Conexión propia sin die() ─────────────────────────────
$server   = "localhost";
$user     = "root";
$password = "";
$db       = "eduforge";

$conexion = new mysqli($server, $user, $password, $db);
$conexion->set_charset("utf8mb4");

// Header JSON siempre — antes de cualquier echo o respuesta
header('Content-Type: application/json; charset=utf-8');

// Helper: limpia buffer y responde JSON
function responder(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok, 'msg' => $msg], $extra));
    exit;
}

// ── 1. Verificar conexión a la base de datos ─────────────────
if ($conexion->connect_errno) {
    responder(false, 'Error de conexión a la BD: ' . $conexion->connect_error, 500);
}

// ── 2. Validar que la petición sea estrictamente POST ────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido.', 405);
}

// ── 3. Verificar sesión activa obligatoriamente ──────────────
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) { 
    responder(false, 'Debes iniciar sesión para responder una pregunta.', 401);
}

// ── 4. Capturar y sanitizar variables de forma segura ────────
$usuarioId   = (int) $_SESSION['user_id']; 
$preguntaId  = (int) ($_POST['pregunta_id'] ?? 0);
$contenido   = trim($_POST['contenido'] ?? '');

// ── 5. Validaciones básicas de campos ────────────────────────
if ($preguntaId <= 0) {
    responder(false, 'Pregunta inválida.', 422);
}
if ($contenido === '' || strip_tags($contenido) === '') {
    responder(false, 'La respuesta no puede estar vacía.', 422);
}

// ... (A partir de aquí continúa tu código para verificar si la pregunta existe e insertar)
// Verificar que la pregunta exista y no esté cerrada
$stmtChk = $conexion->prepare("SELECT estado FROM foro_preguntas WHERE id = ?");
$stmtChk->bind_param("i", $preguntaId);
$stmtChk->execute();
$pregunta = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if (!$pregunta)                    responder(false, 'La pregunta no existe.', 404);
if ($pregunta['estado'] === 'cerrada') responder(false, 'Esta pregunta está cerrada.', 403);

// Insertar respuesta
$stmt = $conexion->prepare("INSERT INTO foro_respuestas (pregunta_id, usuario_id, contenido) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $preguntaId, $usuarioId, $contenido);

if ($stmt->execute()) {
    $nuevaId = $stmt->insert_id;
    $stmt->close();
    $conexion->close();
    responder(true, '¡Respuesta publicada!', 200, ['id' => $nuevaId]);
} else {
    $e = $stmt->error; $stmt->close(); $conexion->close();
    responder(false, 'Error al guardar: ' . $e, 500);
}