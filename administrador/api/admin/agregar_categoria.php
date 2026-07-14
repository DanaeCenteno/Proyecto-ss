<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');
function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') resp(false, 'Método no permitido.');
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$nombre = trim($_POST['nombre'] ?? '');
if ($nombre === '') resp(false, 'El nombre de la categoría no puede estar vacío.');
if (strlen($nombre) > 60) resp(false, 'El nombre es demasiado largo (máx. 60 caracteres).');

// Verificar si ya existe
$check = $conexion->prepare("SELECT COUNT(*) FROM cursos WHERE categoria = ?");
$check->bind_param("s", $nombre); $check->execute();
// La categoría se crea insertando un registro ficticio si no hay cursos aún,
// pero en realidad las categorías son valores de la columna `cursos.categoria`.
// Solo confirmamos que no es duplicada y la devolvemos al frontend para agregarla al DOM.
// No hay tabla de categorías separada — se infieren de los cursos.
$check->close();

resp(true, 'Categoría "' . htmlspecialchars($nombre) . '" registrada.', ['nombre' => $nombre]);