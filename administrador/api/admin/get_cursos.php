<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');
function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$q      = trim($_GET['q']      ?? '');
$estado = trim($_GET['estado'] ?? '');
$cat    = trim($_GET['cat']    ?? '');

$where = ["1=1"]; $params = []; $types = "";

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(c.titulo LIKE ? OR u.nombre LIKE ?)";
    $params[] = $like; $params[] = $like; $types .= "ss";
}
if ($estado !== '') { $where[] = "c.estado = ?"; $params[] = $estado; $types .= "s"; }
if ($cat    !== '') { $where[] = "c.categoria = ?"; $params[] = $cat;  $types .= "s"; }

$sql = "SELECT c.id, c.titulo, c.estado, c.categoria, c.created_at,
               u.nombre AS profesor,
               COUNT(i.usuario_id) AS inscritos
        FROM cursos c
        JOIN usuarios u ON u.id = c.profesor_id
        LEFT JOIN inscripciones i ON i.curso_id = c.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY c.id, u.nombre
        ORDER BY c.created_at DESC";

if (!empty($params)) {
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $cursos = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
}

resp(true, '', ['cursos' => $cursos]);