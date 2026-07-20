<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');

function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

// Asegurar que la columna destacada existe antes de consultarla
// Agregar columna si no existe (compatible con MySQL 5.7+)
$cols = $conexion->query("SHOW COLUMNS FROM foro_preguntas LIKE 'destacada'");
if ($cols->num_rows === 0) {
    $conexion->query("ALTER TABLE foro_preguntas ADD COLUMN destacada TINYINT(1) NOT NULL DEFAULT 0");
}

$q      = trim($_GET['q']      ?? '');
$estado = trim($_GET['estado'] ?? '');

$where = ["1=1"]; $params = []; $types = "";

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(fp.titulo LIKE ? OR u.nombre LIKE ?)";
    $params[] = $like; $params[] = $like; $types .= "ss";
}

if ($estado === 'destacada') {
    $where[] = "fp.destacada = 1";
} elseif ($estado !== '') {
    $where[] = "fp.estado = ?"; $params[] = $estado; $types .= "s";
}

$sql = "SELECT fp.id, fp.titulo, fp.estado,
               COALESCE(fp.destacada, 0) AS destacada,
               fp.created_at,
               u.nombre AS autor,
               COUNT(fr.id) AS respuestas
        FROM foro_preguntas fp
        JOIN usuarios u ON u.id = fp.usuario_id
        LEFT JOIN foro_respuestas fr ON fr.pregunta_id = fp.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY fp.id, fp.titulo, fp.estado, fp.destacada, fp.created_at, u.nombre
        ORDER BY fp.destacada DESC, fp.created_at DESC";

try {
    if (!empty($params)) {
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $preguntas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $res = $conexion->query($sql);
        $preguntas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
    resp(true, '', ['preguntas' => $preguntas]);
} catch (Exception $e) {
    resp(false, 'Error en la consulta: ' . $e->getMessage());
}