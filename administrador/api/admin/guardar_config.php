<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');
function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$campos = ['nombre_plataforma', 'descripcion', 'logo_url', 'color_primario'];

$conexion->query("CREATE TABLE IF NOT EXISTS plataforma_config (
    clave VARCHAR(100) PRIMARY KEY,
    valor TEXT
)");

$stmt = $conexion->prepare("INSERT INTO plataforma_config (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");

foreach ($campos as $campo) {
    $valor = trim($_POST[$campo] ?? '');
    $stmt->bind_param("ss", $campo, $valor);
    $stmt->execute();
}
$stmt->close();

resp(true, 'Configuración guardada correctamente.');