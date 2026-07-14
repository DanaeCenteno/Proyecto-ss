<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');
function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$q   = trim($_GET['q']   ?? '');
$rol = trim($_GET['rol'] ?? '');

$where = ["1=1"];
$params = []; $types = "";

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(nombre LIKE ? OR correo LIKE ?)";
    $params[] = $like; $params[] = $like; $types .= "ss";
}
if ($rol !== '') {
    $where[] = "rol = ?";
    $params[] = $rol; $types .= "s";
}

$sql = "SELECT id, nombre, correo, rol, created_at FROM usuarios WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $usuarios = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
}

resp(true, '', ['usuarios' => $usuarios]);