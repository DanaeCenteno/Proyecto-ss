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

// Validaciones
if ($preguntaId <= 0) {
    responder(false, 'ID de pregunta inválido', 422);
}

// ═══════════════════════════════════════════════════════════════════════════
// VERIFICAR QUE EL USUARIO ES PROPIETARIO
// ═══════════════════════════════════════════════════════════════════════════

$stmtChk = $conexion->prepare("
    SELECT usuario_id, titulo 
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
    responder(false, 'No tienes permiso para eliminar esta pregunta', 403);
}

// ═══════════════════════════════════════════════════════════════════════════
// TRANSACCIÓN: ELIMINAR RESPUESTAS Y PREGUNTA
// ═══════════════════════════════════════════════════════════════════════════

try {
    // Iniciar transacción
    $conexion->begin_transaction();

    // 1️⃣ Eliminar respuestas asociadas
    $stmtDel1 = $conexion->prepare("
        DELETE FROM foro_respuestas 
        WHERE pregunta_id = ?
    ");
    $stmtDel1->bind_param("i", $preguntaId);
    
    if (!$stmtDel1->execute()) {
        throw new Exception('Error al eliminar respuestas: ' . $stmtDel1->error);
    }
    $stmtDel1->close();

    // 2️⃣ Eliminar votos de respuestas (si existe tabla)
    $tablaVotos = $conexion->query("SHOW TABLES LIKE 'votos_respuestas'")->num_rows > 0;
    
    if ($tablaVotos) {
        $stmt = $conexion->prepare("
            DELETE v FROM votos_respuestas v
            INNER JOIN foro_respuestas fr ON v.respuesta_id = fr.id
            WHERE fr.pregunta_id = ?
        ");
        $stmt->bind_param("i", $preguntaId);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al eliminar votos: ' . $stmt->error);
        }
        $stmt->close();
    }

    // 3️⃣ Eliminar la pregunta
    $stmtDel2 = $conexion->prepare("
        DELETE FROM foro_preguntas 
        WHERE id = ? AND usuario_id = ?
    ");
    $stmtDel2->bind_param("ii", $preguntaId, $usuarioId);
    
    if (!$stmtDel2->execute()) {
        throw new Exception('Error al eliminar pregunta: ' . $stmtDel2->error);
    }
    $stmtDel2->close();

    // ✅ Confirmar transacción
    $conexion->commit();
    $conexion->close();

    responder(true, '✅ Pregunta eliminada correctamente', 200, [
        'pregunta_id' => $preguntaId,
        'titulo_eliminado' => $pregunta['titulo']
    ]);

} catch (Exception $e) {
    // ❌ Deshacer cambios en caso de error
    $conexion->rollback();
    $conexion->close();
    
    responder(false, 'Error: ' . $e->getMessage(), 500);
}
?>