<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();

// Solo profesores pueden entrar aquí
requiereRol(ROL_PROFESOR);

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$rolUsuario    = usuarioRol();
$iniciales     = inicialesAvatar($nombreUsuario);


// ── Datos del usuario para el sidebar ────────────────────
$stmtU = $conexion->prepare("SELECT nombre, avatar FROM usuarios WHERE id = ?");
$stmtU->bind_param("i", $uid);
$stmtU->execute();
$usuario = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

$avatarRaw     = $usuario['avatar'] ?? '';
$avatarUsuario = '';
if (!empty($avatarRaw)) {
    if (str_starts_with($avatarRaw, 'http')) {
        $avatarUsuario = $avatarRaw;
    } else {
        $avatarUsuario = '../' . ltrim($avatarRaw, '/');
    }
}


// Estadísticas
$qTotal      = $conexion->query("SELECT COUNT(*) as total FROM cursos WHERE profesor_id = $uid");
$totalCursos = $qTotal->fetch_assoc()['total'];

$qPublicados    = $conexion->query("SELECT COUNT(*) as total FROM cursos WHERE profesor_id = $uid AND estado = 'publicado'");
$totalPublicados = $qPublicados->fetch_assoc()['total'];
$totalBorradores = $totalCursos - $totalPublicados;

$qLecciones   = $conexion->query("
    SELECT COUNT(l.id) as total
    FROM lecciones l
    INNER JOIN modulos m ON l.modulo_id = m.id
    INNER JOIN cursos c  ON m.curso_id  = c.id
    WHERE c.profesor_id = $uid
");
$totalLecciones = $qLecciones->fetch_assoc()['total'];

// Cursos recientes (Se agrega c.imagen a la consulta)
$qCursos = $conexion->query("
    SELECT c.id, c.titulo, c.descripcion, c.categoria, c.estado, c.duracion_total, c.imagen,
           COUNT(DISTINCT m.id) as total_modulos,
           COUNT(DISTINCT l.id) as total_lecciones
    FROM cursos c
    LEFT JOIN modulos m   ON m.curso_id   = c.id
    LEFT JOIN lecciones l ON l.modulo_id  = m.id
    WHERE c.profesor_id = $uid
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT 3
");
$cursosRecientes = $qCursos->fetch_all(MYSQLI_ASSOC);

// Todos los cursos (Se agrega c.imagen a la consulta)
$tCursos = $conexion->query("
    SELECT c.id, c.titulo, c.descripcion, c.categoria, c.estado, c.duracion_total, c.imagen,
           COUNT(DISTINCT m.id) as total_modulos,
           COUNT(DISTINCT l.id) as total_lecciones
    FROM cursos c
    LEFT JOIN modulos m   ON m.curso_id   = c.id
    LEFT JOIN lecciones l ON l.modulo_id  = m.id
    WHERE c.profesor_id = $uid
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$totalCursosListado = $tCursos->fetch_all(MYSQLI_ASSOC);

$colores = ['color-purple','color-blue','color-orange','color-teal','color-dark'];
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600&family=Poppins:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/sdashboard.css">
</head>

<body>




    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-logo" href="dashboard.php">
                <img src="../img/logoEduTecnia.png" alt="EduTecnia">
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-label">Principal</div>
                <a href="dashboard.php?uid=<?= $uid ?>" class="nav-link-item active">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
                <a href="dashboard.php?uid=<?= $uid ?>#cursos" class="nav-link-item">
                    <i class="bi bi-collection-play"></i> Mis Cursos
                </a>
                <a href="foro.php?uid=<?= $uid ?>" class="nav-link-item">
                    <i class="bi bi-question-circle"></i> Foro
                </a>
                <a href="estudiantes.php?uid=<?= $uid ?>" class="nav-link-item">
                    <i class="bi bi-person-square"></i> Estudiantes
                </a>

                <div class="nav-section-label">Sistema</div>
                <a href="perfilprof.php" class="nav-link-item ">
                    <i class="bi bi-person-fill-gear"></i> Mi Perfil
                </a>
                <a href="../logout.php" class="nav-link-item">
                    <i class="bi bi-box-arrow-left"></i> Cerrar sesión
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="sf-avatar">
                    <?php if ($avatarUsuario): ?>
                    <img src="<?= htmlspecialchars($avatarUsuario) ?>" alt="">
                    <?php else: ?>
                    <?= $iniciales ?>
                    <?php endif; ?>
                </div>
                <div class="sf-info">
                    <div class="sf-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                    <div class="sf-role">Profesor</div>
                </div>
            </div>
        </aside>


        <div class="main-wrap">

            <div class="topbar">
                <span class="topbar-title">Dashboard</span>
                <div class="topbar-actions">
                    <a href="curso.php?uid=<?= $uid ?>" class="btn-new">
                        <i class="bi bi-plus-lg"></i> Nuevo Curso
                    </a>
                </div>
            </div>

            <div class="content">

                <div class="stat-cards">
                    <div class="stat-card blue">
                        <div class="stat-header">
                            <span class="stat-label">Cursos Totales</span>
                            <div class="stat-icon blue"><i class="bi bi-collection-play"></i></div>
                        </div>
                        <div class="stat-number"><?= $totalCursos ?></div>
                        <div class="stat-sub">Creados por ti</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-header">
                            <span class="stat-label">Publicados</span>
                            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                        </div>
                        <div class="stat-number"><?= $totalPublicados ?></div>
                        <div class="stat-sub">Visibles al público</div>
                    </div>
                    <div class="stat-card yellow">
                        <div class="stat-header">
                            <span class="stat-label">Borradores</span>
                            <div class="stat-icon yellow"><i class="bi bi-pencil"></i></div>
                        </div>
                        <div class="stat-number"><?= $totalBorradores ?></div>
                        <div class="stat-sub">En progreso</div>
                    </div>
                    <div class="stat-card cyan">
                        <div class="stat-header">
                            <span class="stat-label">Total Lecciones</span>
                            <div class="stat-icon cyan"><i class="bi bi-play-btn"></i></div>
                        </div>
                        <div class="stat-number"><?= $totalLecciones ?></div>
                        <div class="stat-sub">En todos los cursos</div>
                    </div>
                </div>

                <div class="section-header">
                    <span class="section-title">Cursos Recientes</span>
                </div>

                <div class="course-grid">
                    <?php if (empty($cursosRecientes)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-collection-play" style="font-size:48px; color:#ccc;"></i>
                        <p class="text-muted mt-3">Aún no tienes cursos creados.</p>
                        <a href="curso.php?uid=<?= $uid ?>" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-lg"></i> Crear mi primer curso
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($cursosRecientes as $i => $curso): 
                        $color = $colores[$i % count($colores)];
                        $tieneImagen = !empty($curso['imagen']) && file_exists($curso['imagen']);
                    ?>
                    <div class="course-card">
                        <div class="course-thumb <?= $tieneImagen ? '' : $color ?>">
                            <?php if ($tieneImagen): ?>
                            <img src="<?= htmlspecialchars($curso['imagen']) ?>"
                                alt="<?= htmlspecialchars($curso['titulo']) ?>">
                            <?php endif; ?>
                            <span class="course-badge badge-<?= $curso['estado'] ?>">
                                <?= ucfirst($curso['estado']) ?>
                            </span>
                        </div>
                        <div class="course-body">
                            <div class="course-cat"><?= htmlspecialchars($curso['categoria'] ?? 'General') ?></div>
                            <div class="course-title"><?= htmlspecialchars($curso['titulo']) ?></div>
                            <div class="course-desc"><?= htmlspecialchars($curso['descripcion'] ?? '') ?></div>
                            <div class="course-meta">
                                <span><i class="bi bi-collection"></i> <?= $curso['total_modulos'] ?> módulos</span>
                                <span><i class="bi bi-play-btn"></i> <?= $curso['total_lecciones'] ?> lecciones</span>
                                <span><i class="bi bi-clock"></i> <?= $curso['duracion_total'] ?> min</span>
                            </div>
                            <div class="course-actions">
                                <a href="curso.php?uid=<?= $uid ?>&id=<?= $curso['id'] ?>" class="btn-action btn-edit">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                                <button class="btn-action btn-del" onclick="eliminarCurso(<?= (int)$curso['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <hr>

                <div class="section-header">
                    <span class="section-title">Todos los Cursos</span>
                    <a href="dashboard.php?uid=<?= $uid ?>" class="btn-ver-todos">Ver todos <i
                            class="bi bi-arrow-right"></i></a>
                </div>

                <div class="course-grid">
                    <?php if (empty($totalCursosListado)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-collection-play" style="font-size:48px; color:#ccc;"></i>
                        <p class="text-muted mt-3">Aún no tienes cursos creados.</p>
                        <a href="curso.php?uid=<?= $uid ?>" class="btn btn-primary mt-2">
                            <i class="bi bi-plus-lg"></i> Crear mi primer curso
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($totalCursosListado as $i => $cursoTotal): 
                        $color = $colores[$i % count($colores)];
                        $tieneImagen = !empty($cursoTotal['imagen']) && file_exists($cursoTotal['imagen']);
                    ?>
                    <div class="course-card">
                        <div class="course-thumb <?= $tieneImagen ? '' : $color ?>">
                            <?php if ($tieneImagen): ?>
                            <img src="<?= htmlspecialchars($cursoTotal['imagen']) ?>"
                                alt="<?= htmlspecialchars($cursoTotal['titulo']) ?>">
                            <?php endif; ?>
                            <span class="course-badge badge-<?= $cursoTotal['estado'] ?>">
                                <?= ucfirst($cursoTotal['estado']) ?>
                            </span>
                        </div>
                        <div class="course-body">
                            <div class="course-cat"><?= htmlspecialchars($cursoTotal['categoria'] ?? 'General') ?></div>
                            <div class="course-title"><?= htmlspecialchars($cursoTotal['titulo']) ?></div>
                            <div class="course-desc"><?= htmlspecialchars($cursoTotal['descripcion'] ?? '') ?></div>
                            <div class="course-meta">
                                <span><i class="bi bi-collection"></i> <?= $cursoTotal['total_modulos'] ?>
                                    módulos</span>
                                <span><i class="bi bi-play-btn"></i> <?= $cursoTotal['total_lecciones'] ?>
                                    lecciones</span>
                                <span><i class="bi bi-clock"></i> <?= $cursoTotal['duracion_total'] ?> min</span>
                            </div>
                            <div class="course-actions">
                                <a href="curso.php?uid=<?= $uid ?>&id=<?= $cursoTotal['id'] ?>"
                                    class="btn-action btn-edit">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>

                                <button class="btn-action btn-del"
                                    onclick="eliminarCurso(<?= (int)$cursoTotal['id'] ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function eliminarCurso(id) {
        if (!id || id <= 0) {
            alert('Error: ID de curso no válido.');
            return;
        }

        if (!confirm('¿Seguro que deseas eliminar este curso?')) return;

        console.log("Enviando ID a eliminar:", id);

        fetch('../administrador/api/cursos/eliminar_curso.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: parseInt(id, 10)
                })
            })
            .then(res => res.text())
            .then(texto => {
                console.log("Respuesta del servidor:", texto);
                const data = JSON.parse(texto);
                if (data.success) {
                    location.reload();
                } else {
                    alert("Error del servidor: " + data.error);
                }
            })
            .catch(error => console.error('Error:', error));
    }
    </script>
</body>

</html>