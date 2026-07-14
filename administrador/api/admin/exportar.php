<?php
ob_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../../includes/auth.php';
iniciarSesion();
header('Content-Type: application/json; charset=utf-8');if (usuarioRol() !== 'admin') { http_response_code(403); exit('Sin permisos.'); }

$tipo = trim($_GET['tipo'] ?? '');
$tipos_validos = ['inscripciones', 'progreso', 'usuarios', 'foro'];
if (!in_array($tipo, $tipos_validos, true)) { http_response_code(400); exit('Tipo inválido.'); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="edutecnia_' . $tipo . '_' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache');

$out = fopen('php://output', 'w');
// BOM para Excel
fwrite($out, "\xEF\xBB\xBF");

switch ($tipo) {
    case 'inscripciones':
        fputcsv($out, ['Estudiante', 'Correo', 'Curso', 'Profesor', 'Fecha inscripción']);
        $res = $conexion->query("
            SELECT u.nombre, u.correo, c.titulo AS curso, p.nombre AS profesor, i.fecha_inscripcion
            FROM inscripciones i
            JOIN usuarios u ON u.id = i.usuario_id
            JOIN cursos c ON c.id = i.curso_id
            JOIN usuarios p ON p.id = c.profesor_id
            ORDER BY i.fecha_inscripcion DESC
        ");
        while ($row = $res->fetch_assoc())
            fputcsv($out, [$row['nombre'], $row['correo'], $row['curso'], $row['profesor'], $row['fecha_inscripcion']]);
        break;

    case 'progreso':
        fputcsv($out, ['Estudiante', 'Correo', 'Curso', 'Progreso (%)', 'Fecha inscripción']);
        $res = $conexion->query("
            SELECT u.nombre, u.correo, c.titulo AS curso,
                   COALESCE(i.progreso, 0) AS pct,
                   i.fecha_inscripcion
            FROM inscripciones i
            JOIN usuarios u ON u.id = i.usuario_id AND u.rol = 'estudiante'
            JOIN cursos c ON c.id = i.curso_id
            ORDER BY u.nombre, c.titulo
        ");
        while ($row = $res->fetch_assoc())
            fputcsv($out, [$row['nombre'], $row['correo'], $row['curso'], $row['pct'], $row['fecha_inscripcion']]);
        break;

    case 'usuarios':
        fputcsv($out, ['ID', 'Nombre', 'Correo', 'Rol', 'Fecha registro']);
        $res = $conexion->query("SELECT id, nombre, correo, rol, created_at FROM usuarios ORDER BY created_at DESC");
        while ($row = $res->fetch_assoc())
            fputcsv($out, [$row['id'], $row['nombre'], $row['correo'], $row['rol'], $row['created_at']]);
        break;

    case 'foro':
        fputcsv($out, ['ID', 'Título', 'Autor', 'Estado', 'Respuestas', 'Vistas', 'Fecha']);
        $res = $conexion->query("
            SELECT fp.id, fp.titulo, u.nombre AS autor, fp.estado,
                   COUNT(fr.id) AS respuestas, fp.vistas, fp.created_at
            FROM foro_preguntas fp
            JOIN usuarios u ON u.id = fp.usuario_id
            LEFT JOIN foro_respuestas fr ON fr.pregunta_id = fp.id
            GROUP BY fp.id, u.nombre
            ORDER BY fp.created_at DESC
        ");
        while ($row = $res->fetch_assoc())
            fputcsv($out, [$row['id'], $row['titulo'], $row['autor'], $row['estado'],
                           $row['respuestas'], $row['vistas'], $row['created_at']]);
        break;
}

fclose($out);