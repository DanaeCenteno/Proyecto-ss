<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');
function resp(bool $ok, string $msg, array $extra=[]): void { ob_end_clean(); echo json_encode(array_merge(['ok'=>$ok,'msg'=>$msg],$extra)); exit; }
if (usuarioRol() !== 'admin') resp(false, 'Sin permisos.');

$q        = trim($_GET['q']        ?? '');
$cursoId  = (int)($_GET['curso_id'] ?? 0);

$where = ["u.rol = 'estudiante'"]; $params = []; $types = "";

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(u.nombre LIKE ? OR u.correo LIKE ?)";
    $params[] = $like; $params[] = $like; $types .= "ss";
}
if ($cursoId > 0) {
    $where[] = "i.curso_id = ?"; $params[] = $cursoId; $types .= "i";
}

// El progreso se calcula comparando lecciones completadas vs total del curso.
// Si existe tabla progreso_lecciones la usamos; si no, usamos i.progreso (columna de inscripciones).
$sql = "SELECT u.nombre AS estudiante, c.titulo AS curso,
               i.fecha_inscripcion AS fecha,
               COALESCE(
                   -- Intento con tabla progreso_lecciones
                   (SELECT ROUND(COUNT(pl.leccion_id) / NULLIF(tot.total_lecs, 0) * 100)
                    FROM progreso_lecciones pl
                    JOIN lecciones l2 ON l2.id = pl.leccion_id
                    JOIN modulos m2 ON m2.id = l2.modulo_id
                    WHERE pl.usuario_id = u.id AND m2.curso_id = c.id),
                   -- Fallback: columna progreso en inscripciones
                   i.progreso,
                   0
               ) AS pct,
               tot.total_lecs
        FROM inscripciones i
        JOIN usuarios u  ON u.id  = i.usuario_id
        JOIN cursos   c  ON c.id  = i.curso_id
        LEFT JOIN (
            SELECT m.curso_id, COUNT(l.id) AS total_lecs
            FROM modulos m JOIN lecciones l ON l.modulo_id = m.id
            GROUP BY m.curso_id
        ) tot ON tot.curso_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY u.nombre, c.titulo";

if (!empty($params)) {
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $progreso = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $res = $conexion->query($sql);
    $progreso = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

resp(true, '', ['progreso' => $progreso]);