<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');
function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$id      = (int)($_POST['id']      ?? 0);
$nombre  = trim($_POST['nombre']   ?? '');
$correo  = trim($_POST['correo']   ?? '');
$rol     = trim($_POST['rol']      ?? 'estudiante');
$pass    = trim($_POST['password'] ?? '');

if ($nombre === '' || $correo === '') resp(false, 'Nombre y correo son obligatorios.');
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) resp(false, 'Correo inválido.');
if (!in_array($rol, ['estudiante', 'profesor', 'admin'], true)) resp(false, 'Rol inválido.');

if ($id === 0) {
    // ── Crear nuevo usuario ──
    if (strlen($pass) < 8) resp(false, 'La contraseña debe tener al menos 8 caracteres.');

    // Verificar correo duplicado
    $check = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ?");
    $check->bind_param("s", $correo); $check->execute();
    if ($check->get_result()->num_rows > 0) resp(false, 'Ya existe un usuario con ese correo.');
    $check->close();

    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, correo, password, rol, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssss", $nombre, $correo, $hash, $rol);
    if ($stmt->execute()) resp(true, 'Usuario creado correctamente.', ['id' => $conexion->insert_id]);
    resp(false, 'Error al crear el usuario.');
} else {
    // ── Editar usuario existente ──
    // Verificar correo duplicado (excluyendo al mismo usuario)
    $check = $conexion->prepare("SELECT id FROM usuarios WHERE correo = ? AND id != ?");
    $check->bind_param("si", $correo, $id); $check->execute();
    if ($check->get_result()->num_rows > 0) resp(false, 'Ese correo ya está en uso por otro usuario.');
    $check->close();

    if ($pass !== '' && strlen($pass) < 8) resp(false, 'La contraseña debe tener al menos 8 caracteres.');

    if ($pass !== '') {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("UPDATE usuarios SET nombre=?, correo=?, rol=?, password=? WHERE id=?");
        $stmt->bind_param("ssssi", $nombre, $correo, $rol, $hash, $id);
    } else {
        $stmt = $conexion->prepare("UPDATE usuarios SET nombre=?, correo=?, rol=? WHERE id=?");
        $stmt->bind_param("sssi", $nombre, $correo, $rol, $id);
    }
    if ($stmt->execute()) resp(true, 'Usuario actualizado correctamente.');
    resp(false, 'Error al actualizar el usuario.');
}