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

if ($conexion->connect_errno) responder(false, 'Error de conexión.', 500);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(false, 'Método no permitido.', 405);
if (!estaAutenticado()) responder(false, 'Debes iniciar sesión.', 401);

$usuarioId = usuarioId();
$titulo    = trim($_POST['titulo']    ?? '');
$cursoIdRaw = (int)($_POST['curso_id'] ?? 0);
$cursoId    = $cursoIdRaw > 0 ? $cursoIdRaw : null;
$contenido = trim($_POST['contenido'] ?? '');
$lenguajes = trim($_POST['lenguajes'] ?? '');

$errores = [];

if ($titulo === '' || strlen($titulo) < 10) {
    $errores[] = 'El título debe tener al menos 10 caracteres.';
}

$contenidoLimpio = trim(strip_tags(html_entity_decode($contenido)));
if ($contenidoLimpio === '') {
    $errores[] = 'La descripción es obligatoria.';
}

if (!empty($errores)) {
    responder(false, implode(' ', $errores), 422);
}

// Normalizar lenguajes
$lenguajesGuardar = '';
if ($lenguajes !== '') {
    $langsLimpios = [];
    foreach (explode(',', $lenguajes) as $lang) {
        $lang = trim($lang);
        if (str_starts_with($lang, 'otro:')) {
            $custom = trim(substr($lang, 5));
            if ($custom !== '') $langsLimpios[] = strtolower($custom);
        } elseif ($lang !== '' && $lang !== 'otro') {
            $langsLimpios[] = strtolower($lang);
        }
    }
    $lenguajesGuardar = implode(',', array_unique($langsLimpios));
}

$sql  = "INSERT INTO foro_preguntas (usuario_id, curso_id, titulo, contenido, lenguajes, estado)
         VALUES (?, ?, ?, ?, ?, 'abierta')";
$stmt = $conexion->prepare($sql);

if (!$stmt) responder(false, 'Error al preparar consulta: ' . $conexion->error, 500);


$stmt->bind_param('iisss', $usuarioId, $cursoId, $titulo, $contenido, $lenguajesGuardar);
if ($stmt->execute()) {
    $nuevaId = $stmt->insert_id;
    $stmt->close();
    $conexion->close();
    responder(true, '¡Pregunta publicada con éxito!', 200, ['pregunta_id' => $nuevaId]);
} else {
    $errorMsg = $stmt->error;
    $stmt->close();
    $conexion->close();
    responder(false, 'Error al guardar: ' . $errorMsg, 500);
}