<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';

iniciarSesion();
header('Content-Type: application/json; charset=utf-8');

function resp(bool $ok, string $msg, int $code = 200, array $extra = []): void {
    ob_end_clean(); http_response_code($code);
    echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($conexion->connect_errno)              resp(false, 'Error de BD.', 500);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.', 405);
if (!estaAutenticado())                    resp(false, 'Debes iniciar sesión.', 401);

$usuarioId  = usuarioId();
$cursoId    = (int)($_POST['curso_id']  ?? 0);
$estrellas  = (int)($_POST['estrellas'] ?? 0);
$comentario = trim($_POST['comentario'] ?? '');

if ($cursoId   <= 0)                       resp(false, 'Curso inválido.', 422);
if ($estrellas < 1 || $estrellas > 5)      resp(false, 'La calificación debe ser entre 1 y 5.', 422);
if (mb_strlen($comentario) > 500)          resp(false, 'El comentario es demasiado largo.', 422);

// ── Verificar inscripción ─────────────────────────────────
$stmtI = $conexion->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND curso_id = ?");
$stmtI->bind_param("ii", $usuarioId, $cursoId);
$stmtI->execute();
if (!$stmtI->get_result()->fetch_assoc()) resp(false, 'No estás inscrito en este curso.', 403);
$stmtI->close();

// ── Upsert reseña ────────────────────────────────────────
$comentarioNull = $comentario !== '' ? $comentario : null;

$stmt = $conexion->prepare("
    INSERT INTO curso_resenas (curso_id, usuario_id, estrellas, comentario)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        estrellas   = VALUES(estrellas),
        comentario  = VALUES(comentario),
        updated_at  = NOW()
");
if (!$stmt) resp(false, 'Error al preparar: ' . $conexion->error, 500);
$stmt->bind_param("iiis", $cursoId, $usuarioId, $estrellas, $comentarioNull);

if ($stmt->execute()) {
    $stmt->close(); $conexion->close();
    resp(true, '¡Reseña guardada!', 200);
} else {
    $e = $stmt->error; $stmt->close(); $conexion->close();
    resp(false, 'Error al guardar: ' . $e, 500);
}