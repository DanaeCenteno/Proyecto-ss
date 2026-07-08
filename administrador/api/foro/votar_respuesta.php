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

// 1. Verificaciones de Seguridad e Integridad usando tu auth.php
if ($conexion->connect_errno) responder(false, 'Error de BD.', 500);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método no permitido.', 405);


if (!estaAutenticado()) {
    responder(false, 'Debes iniciar sesión para votar.', 401);
}


$usuarioId   = usuarioId();

// Leer datos enviados por POST (Soporta variables normales de formulario)
$respuestaId = (int) ($_POST['respuesta_id'] ?? 0);
$tipo        = in_array($_POST['tipo'] ?? '', ['up','down']) ? $_POST['tipo'] : 'up';

if ($respuestaId <= 0) responder(false, 'Respuesta inválida.', 422);

// 2. Verificar si ya votó — si sí, eliminar (toggle)
$stmtChk = $conexion->prepare("SELECT id, tipo FROM foro_votos WHERE respuesta_id = ? AND usuario_id = ?");
$stmtChk->bind_param("ii", $respuestaId, $usuarioId);
$stmtChk->execute();
$votoExistente = $stmtChk->get_result()->fetch_assoc();
$stmtChk->close();

if ($votoExistente) {
    if ($votoExistente['tipo'] === $tipo) {
        // Mismo voto → quitar (toggle off)
        $stmtDel = $conexion->prepare("DELETE FROM foro_votos WHERE id = ?");
        $stmtDel->bind_param("i", $votoExistente['id']);
        $stmtDel->execute();
        $stmtDel->close();
        $delta = $tipo === 'up' ? -1 : 1;
        $msg   = 'Voto eliminado.';
    } else {
        // Voto opuesto → actualizar
        $stmtUpd = $conexion->prepare("UPDATE foro_votos SET tipo = ? WHERE id = ?");
        $stmtUpd->bind_param("si", $tipo, $votoExistente['id']);
        $stmtUpd->execute();
        $stmtUpd->close();
        $delta = $tipo === 'up' ? 2 : -2;
        $msg   = $tipo === 'up' ? '¡Voto positivo registrado!' : 'Voto negativo registrado.';
    }
} else {
    // Nuevo voto
    $stmtIns = $conexion->prepare("INSERT INTO foro_votos (respuesta_id, usuario_id, tipo) VALUES (?, ?, ?)");
    $stmtIns->bind_param("iis", $respuestaId, $usuarioId, $tipo);
    $stmtIns->execute();
    $stmtIns->close();
    $delta = $tipo === 'up' ? 1 : -1;
    $msg   = $tipo === 'up' ? '¡Voto positivo registrado!' : 'Voto negativo registrado.';
}

// 3. Actualizar contador en la tabla foro_respuestas
$conexion->query("UPDATE foro_respuestas SET votos = votos + ($delta) WHERE id = $respuestaId");

// 4. Devolver nuevo total actualizado
$stmtV = $conexion->prepare("SELECT votos FROM foro_respuestas WHERE id = ?");
$stmtV->bind_param("i", $respuestaId);
$stmtV->execute();
$row = $stmtV->get_result()->fetch_assoc();
$stmtV->close();
$conexion->close();

responder(true, $msg, 200, ['votos' => (int)($row['votos'] ?? 0)]);