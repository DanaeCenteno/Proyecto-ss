<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();
requiereRol(ROL_PROFESOR);

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$iniciales     = inicialesAvatar($nombreUsuario);

$stmtU = $conexion->prepare("SELECT nombre, correo, avatar FROM usuarios WHERE id = ?");
$stmtU->bind_param("i", $uid);
$stmtU->execute();
$usuario = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

$avatarUsuario = null;
if (!empty($usuario['avatar'])) {
    $av = $usuario['avatar'];
    // Si es URL absoluta, usarla directo; si es ruta relativa, verificar que exista
    if (str_starts_with($av, 'http')) {
        $avatarUsuario = $av;
    } elseif (file_exists(__DIR__ . '/../' . ltrim($av, '/'))) {
        $avatarUsuario = $av;
    }
}

// ── Cursos del profesor (para el filtro) ──────────────────────────────────────
$qCursos = $conexion->prepare("SELECT id, titulo FROM cursos WHERE profesor_id = ? ORDER BY titulo ASC");
$qCursos->bind_param("i", $uid);
$qCursos->execute();
$cursosList = $qCursos->get_result()->fetch_all(MYSQLI_ASSOC);
$qCursos->close();

// ── Filtros desde GET ─────────────────────────────────────────────────────────
$filtroCurso  = isset($_GET['curso_id'])  ? (int)$_GET['curso_id']        : 0;
$filtroNombre = isset($_GET['nombre'])    ? trim($_GET['nombre'])          : '';
$filtroOrden  = isset($_GET['orden'])     ? $_GET['orden']                 : 'reciente';

// ── Construir consulta de estudiantes ─────────────────────────────────────────
// Trae todos los alumnos inscritos en los cursos del profesor, con su progreso.
$sql = "
    SELECT
        u.id                              AS alumno_id,
        u.nombre                          AS alumno_nombre,
        u.correo                          AS alumno_correo,
        u.avatar                          AS alumno_avatar,
        c.id                              AS curso_id,
        c.titulo                          AS curso_titulo,
        i.fecha_inscripcion,
        i.completado,
        i.progreso,
        COUNT(DISTINCT l.id)              AS total_lecciones
    FROM inscripciones i
    INNER JOIN usuarios u  ON u.id  = i.usuario_id
    INNER JOIN cursos   c  ON c.id  = i.curso_id
    LEFT  JOIN modulos  m  ON m.curso_id  = c.id
    LEFT  JOIN lecciones l ON l.modulo_id = m.id
    WHERE c.profesor_id = ?
";

$params  = [$uid];
$types   = "i";

if ($filtroCurso > 0) {
    $sql    .= " AND c.id = ?";
    $params[] = $filtroCurso;
    $types   .= "i";
}

if ($filtroNombre !== '') {
    $like     = '%' . $filtroNombre . '%';
    $sql     .= " AND u.nombre LIKE ?";
    $params[] = $like;
    $types   .= "s";
}

$sql .= " GROUP BY u.id, c.id, i.fecha_inscripcion, i.completado, i.progreso";

$orden = match($filtroOrden) {
    'nombre'   => " ORDER BY u.nombre ASC",
    'progreso' => " ORDER BY i.progreso DESC",
    default    => " ORDER BY i.fecha_inscripcion DESC",
};
$sql .= $orden;

$stmt = $conexion->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$estudiantes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Estadísticas rápidas ──────────────────────────────────────────────────────
$totalEstudiantes = count(array_unique(array_column($estudiantes, 'alumno_id')));
$totalInscripciones = count($estudiantes);

$promedioProgreso = 0;
if ($totalInscripciones > 0) {
    $suma = array_sum(array_column($estudiantes, 'progreso'));
    $promedioProgreso = round($suma / $totalInscripciones);
}

$completados = count(array_filter($estudiantes, fn($e) => $e['completado'] == 1));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiantes — EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&family=Poppins:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/sdashboard.css">
    <style>
    /* ══ FILTROS ══════════════════════════════════════════ */
    .filters-bar {
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
        background: var(--bg2);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px 20px;
        margin-bottom: 28px;
    }

    .filter-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        min-width: 160px;
    }

    .filter-group label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--muted);
        white-space: nowrap;
    }

    .filter-input {
        flex: 1;
        height: 36px;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0 12px;
        font-size: 13px;
        font-family: 'DM Sans', sans-serif;
        color: var(--text);
        background: var(--bg);
        outline: none;
        transition: border-color .2s;
    }

    .filter-input:focus {
        border-color: var(--accent2);
    }

    .btn-filter {
        height: 36px;
        padding: 0 18px;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
        transition: background .2s;
        white-space: nowrap;
    }

    .btn-filter:hover {
        background: var(--accent2);
    }

    .btn-clear {
        height: 36px;
        padding: 0 14px;
        background: transparent;
        color: var(--muted);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
        cursor: pointer;
        font-family: 'DM Sans', sans-serif;
        transition: all .2s;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .btn-clear:hover {
        color: var(--red);
        border-color: var(--red);
    }

    /* ══ TABLA ════════════════════════════════════════════ */
    .students-table-wrap {
        background: var(--bg2);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
    }

    .students-table {
        width: 100%;
        border-collapse: collapse;
    }

    .students-table thead th {
        padding: 13px 18px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--muted);
        background: var(--bg);
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .students-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .15s;
    }

    .students-table tbody tr:last-child {
        border-bottom: none;
    }

    .students-table tbody tr:hover {
        background: rgba(30, 73, 120, .03);
    }

    .students-table td {
        padding: 14px 18px;
        vertical-align: middle;
        font-size: 13px;
        color: var(--text);
    }

    /* ══ AVATAR ══════════════════════════════════════════ */
    .alumno-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--accent2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
        overflow: hidden;
    }

    .alumno-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .alumno-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alumno-nombre {
        font-weight: 600;
        color: #1E4978;
        font-size: 13px;
    }

    .alumno-correo {
        font-size: 11px;
        color: var(--muted);
        margin-top: 1px;
    }

    /* ══ CURSO PILL ═════════════════════════════════════ */
    .curso-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: rgba(30, 73, 120, .08);
        color: #1E4978;
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 600;
        max-width: 200px;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    /* ══ BARRA DE PROGRESO ════════════════════════════ */
    .progress-wrap {
        min-width: 140px;
    }

    .progress-bar-track {
        height: 6px;
        background: rgba(30, 73, 120, .10);
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 4px;
    }

    .progress-bar-fill {
        height: 100%;
        border-radius: 10px;
        background: linear-gradient(90deg, #4A90B9, #64B9BE);
        transition: width .4s ease;
    }

    .progress-bar-fill.completo {
        background: linear-gradient(90deg, #2ecc71, #27ae60);
    }

    .progress-bar-fill.bajo {
        background: linear-gradient(90deg, #DC8C1E, #F0AA32);
    }

    .progress-label {
        font-size: 11px;
        color: var(--muted);
    }

    .progress-pct {
        font-weight: 700;
        color: #1E4978;
    }

    /* ══ STATUS BADGE ═══════════════════════════════════ */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border-radius: 20px;
        padding: 3px 10px;
        font-size: 11px;
        font-weight: 700;
    }

    .status-badge.completado {
        background: rgba(46, 204, 113, .12);
        color: #27ae60;
    }

    .status-badge.en-progreso {
        background: rgba(74, 144, 185, .12);
        color: #1E4978;
    }

    .status-badge.sin-iniciar {
        background: rgba(220, 140, 30, .12);
        color: #DC8C1E;
    }

    /* ══ FECHA ═══════════════════════════════════════════ */
    .fecha-inscripcion {
        font-size: 12px;
        color: var(--muted);
        white-space: nowrap;
    }

    /* ══ EMPTY STATE ════════════════════════════════════ */
    .empty-students {
        text-align: center;
        padding: 64px 24px;
    }

    .empty-students .empty-icon {
        font-size: 48px;
        color: #c8d8e8;
        margin-bottom: 16px;
    }

    .empty-students p {
        color: var(--muted);
        font-size: 14px;
        margin: 0;
    }

    .empty-students strong {
        display: block;
        font-size: 16px;
        color: #1E4978;
        margin-bottom: 6px;
    }

    /* ══ RESULTADO COUNT ════════════════════════════════ */
    .results-meta {
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 14px;
    }

    .results-meta strong {
        color: #1E4978;
    }

    /* ══ MODAL DETALLE ══════════════════════════════════ */
    .modal-detalle .modal-header {
        border-bottom: 1px solid var(--border);
        padding: 20px 24px 16px;
    }

    .modal-detalle .modal-body {
        padding: 24px;
    }

    .modal-detalle .modal-title {
        font-family: 'Syne', sans-serif;
        font-size: 17px;
        font-weight: 700;
        color: #1E4978;
    }

    .detalle-avatar {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--accent2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        overflow: hidden;
        flex-shrink: 0;
    }

    .detalle-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .detalle-stat {
        background: var(--bg);
        border-radius: 10px;
        padding: 12px 16px;
        text-align: center;
    }

    .detalle-stat-num {
        font-family: 'Syne', sans-serif;
        font-size: 24px;
        font-weight: 800;
        color: #1E4978;
    }

    .detalle-stat-label {
        font-size: 11px;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: .07em;
        margin-top: 2px;
    }

    .leccion-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
        font-size: 13px;
    }

    .leccion-row:last-child {
        border-bottom: none;
    }

    .leccion-check {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 11px;
    }

    .leccion-check.ok {
        background: rgba(46, 204, 113, .15);
        color: #27ae60;
    }

    .leccion-check.pend {
        background: rgba(220, 140, 30, .12);
        color: #DC8C1E;
    }

    .btn-detalle {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: transparent;
        color: var(--muted);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all .2s;
        font-size: 14px;
    }

    .btn-detalle:hover {
        background: rgba(30, 73, 120, .08);
        color: #1E4978;
        border-color: var(--accent2);
    }

    /* ══ RESPONSIVE ═════════════════════════════════════ */
    @media (max-width: 900px) {
        .filters-bar {
            flex-direction: column;
        }

        .filter-group {
            min-width: 100%;
        }

        .col-hide {
            display: none;
        }
    }
    </style>
</head>

<body>

    <div class="layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <a href="dashboard.php" class="d-flex align-items-center text-decoration-none">
                    <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="50">
                </a>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-label">Principal</div>
                <a href="dashboard.php?uid=<?= $uid ?>" class="nav-link-item">
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
                <a href="dashboard.php?uid=<?= $uid ?>#cursos" class="nav-link-item">
                    <i class="bi bi-collection-play"></i> Mis Cursos
                </a>
                <a href="foro.php?uid=<?= $uid ?>" class="nav-link-item">
                    <i class="bi bi-question-circle"></i> Foro
                </a>
                <a href="estudiantes.php?uid=<?= $uid ?>" class="nav-link-item active">
                    <i class="bi bi-person-square"></i> Estudiantes
                </a>

                <div class="nav-section-label">Gestión</div>
                <a href="ask.php?uid=<?= $uid ?>" class="nav-link-item">
                    <i class="bi bi-plus-circle"></i> Nueva Pregunta
                </a>

                <div class="nav-section-label">Sistema</div>
                <a href="perfilprof.php" class="nav-link-item">
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

        <!-- MAIN WRAP -->
        <div class="main-wrap">

            <div class="topbar">
                <span class="topbar-title">Estudiantes</span>
                <div class="topbar-actions">
                    <a href="curso.php?uid=<?= $uid ?>" class="btn-new">
                        <i class="bi bi-plus-lg"></i> Nuevo Curso
                    </a>
                </div>
            </div>

            <div class="content">

                <!-- STAT CARDS -->
                <div class="stat-cards" style="margin-bottom:28px;">
                    <div class="stat-card blue">
                        <div class="stat-header">
                            <span class="stat-label">Estudiantes únicos</span>
                            <div class="stat-icon blue"><i class="bi bi-people"></i></div>
                        </div>
                        <div class="stat-number"><?= $totalEstudiantes ?></div>
                        <div class="stat-sub">En todos tus cursos</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-header">
                            <span class="stat-label">Inscripciones</span>
                            <div class="stat-icon green"><i class="bi bi-journal-check"></i></div>
                        </div>
                        <div class="stat-number"><?= $totalInscripciones ?></div>
                        <div class="stat-sub">Total alumno–curso</div>
                    </div>
                    <div class="stat-card yellow">
                        <div class="stat-header">
                            <span class="stat-label">Progreso promedio</span>
                            <div class="stat-icon yellow"><i class="bi bi-graph-up"></i></div>
                        </div>
                        <div class="stat-number"><?= $promedioProgreso ?>%</div>
                        <div class="stat-sub">Entre todas las inscripciones</div>
                    </div>
                    <div class="stat-card cyan">
                        <div class="stat-header">
                            <span class="stat-label">Completados</span>
                            <div class="stat-icon cyan"><i class="bi bi-patch-check"></i></div>
                        </div>
                        <div class="stat-number"><?= $completados ?></div>
                        <div class="stat-sub">Cursos terminados al 100%</div>
                    </div>
                </div>

                <!-- FILTROS -->
                <form method="GET" action="estudiantes.php">
                    <input type="hidden" name="uid" value="<?= $uid ?>">
                    <div class="filters-bar">
                        <div class="filter-group">
                            <label><i class="bi bi-search"></i></label>
                            <input type="text" name="nombre" class="filter-input" placeholder="Buscar estudiante…"
                                value="<?= htmlspecialchars($filtroNombre) ?>">
                        </div>

                        <div class="filter-group">
                            <label><i class="bi bi-collection-play"></i></label>
                            <select name="curso_id" class="filter-input">
                                <option value="0">Todos los cursos</option>
                                <?php foreach ($cursosList as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filtroCurso == $c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['titulo']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group" style="min-width:auto; flex:none;">
                            <label><i class="bi bi-sort-down"></i></label>
                            <select name="orden" class="filter-input" style="min-width:140px;">
                                <option value="reciente" <?= $filtroOrden == 'reciente' ? 'selected' : '' ?>>Más
                                    reciente</option>
                                <option value="nombre" <?= $filtroOrden == 'nombre'   ? 'selected' : '' ?>>Nombre A–Z
                                </option>
                                <option value="progreso" <?= $filtroOrden == 'progreso' ? 'selected' : '' ?>>Mayor
                                    progreso</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-filter">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>

                        <?php if ($filtroNombre !== '' || $filtroCurso > 0 || $filtroOrden !== 'reciente'): ?>
                        <a href="estudiantes.php?uid=<?= $uid ?>" class="btn-clear">
                            <i class="bi bi-x-lg"></i> Limpiar
                        </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- TABLA -->
                <?php if (!empty($estudiantes)): ?>
                <p class="results-meta">
                    Mostrando <strong><?= count($estudiantes) ?></strong>
                    inscripci<?= count($estudiantes) == 1 ? 'ón' : 'ones' ?>
                    <?php if ($filtroNombre || $filtroCurso): ?>
                    con los filtros aplicados
                    <?php endif; ?>
                </p>
                <?php endif; ?>

                <div class="students-table-wrap">
                    <?php if (empty($estudiantes)): ?>
                    <div class="empty-students">
                        <div class="empty-icon"><i class="bi bi-person-x"></i></div>
                        <strong>
                            <?php if ($filtroNombre || $filtroCurso): ?>
                            Sin resultados para tu búsqueda
                            <?php else: ?>
                            Aún no hay estudiantes inscritos
                            <?php endif; ?>
                        </strong>
                        <p>
                            <?php if ($filtroNombre || $filtroCurso): ?>
                            Intenta con otros filtros.
                            <?php else: ?>
                            Cuando alguien se inscriba a uno de tus cursos aparecerá aquí.
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php else: ?>
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Curso</th>
                                <th>Progreso</th>
                                <th class="col-hide">Estado</th>
                                <th class="col-hide">Inscrito</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estudiantes as $e):
                        // Usar columnas directas de inscripciones
                        $pct = (int)$e['progreso'];

                        $fillClass = match(true) {
                            $pct >= 100 => 'completo',
                            $pct < 30   => 'bajo',
                            default     => '',
                        };

                        if ($e['completado'] == 1 || $pct >= 100) {
                            $statusClass  = 'completado';
                            $statusLabel  = 'Completado';
                            $statusIcon   = 'bi-patch-check-fill';
                        } elseif ($pct == 0) {
                            $statusClass  = 'sin-iniciar';
                            $statusLabel  = 'Sin iniciar';
                            $statusIcon   = 'bi-clock';
                        } else {
                            $statusClass  = 'en-progreso';
                            $statusLabel  = 'En progreso';
                            $statusIcon   = 'bi-arrow-repeat';
                        }

                        // Iniciales del alumno
                        $palabras   = explode(' ', trim($e['alumno_nombre']));
                        $inicAlumno = strtoupper(substr($palabras[0], 0, 1) . (isset($palabras[1]) ? substr($palabras[1], 0, 1) : ''));

                        $av = $e['alumno_avatar'] ?? '';
                        $tieneAvatar = !empty($av) && (
                            str_starts_with($av, 'http') ||
                            file_exists(__DIR__ . '/../' . ltrim($av, '/'))
                        );

                        $fecha = $e['fecha_inscripcion']
                            ? date('d M Y', strtotime($e['fecha_inscripcion']))
                            : '—';
                    ?>
                            <tr>
                                <!-- Estudiante -->
                                <td>
                                    <div class="alumno-info">
                                        <div class="alumno-avatar">
                                            <?php if ($tieneAvatar): ?>
                                            <img src="<?= htmlspecialchars($av) ?>" alt="">
                                            <?php else: ?>
                                            <?= $inicAlumno ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="alumno-nombre"><?= htmlspecialchars($e['alumno_nombre']) ?>
                                            </div>
                                            <div class="alumno-correo"><?= htmlspecialchars($e['alumno_correo']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Curso -->
                                <td>
                                    <span class="curso-pill">
                                        <i class="bi bi-collection-play" style="font-size:10px;"></i>
                                        <?= htmlspecialchars($e['curso_titulo']) ?>
                                    </span>
                                </td>

                                <!-- Progreso -->
                                <td>
                                    <div class="progress-wrap">
                                        <div class="progress-bar-track">
                                            <div class="progress-bar-fill <?= $fillClass ?>"
                                                style="width: <?= $pct ?>%"></div>
                                        </div>
                                        <div class="progress-label">
                                            <span class="progress-pct"><?= $pct ?>%</span>
                                            — <?= $e['total_lecciones'] ?> lecciones en total
                                        </div>
                                    </div>
                                </td>

                                <!-- Estado -->
                                <td class="col-hide">
                                    <span class="status-badge <?= $statusClass ?>">
                                        <i class="bi <?= $statusIcon ?>"></i>
                                        <?= $statusLabel ?>
                                    </span>
                                </td>

                                <!-- Fecha inscripción -->
                                <td class="col-hide">
                                    <span class="fecha-inscripcion"><?= $fecha ?></span>
                                </td>

                                <!-- Acciones -->
                                <td>
                                    <button class="btn-detalle" title="Ver detalle"
                                        onclick="abrirDetalle(<?= (int)$e['alumno_id'] ?>, <?= (int)$e['curso_id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>

            </div><!-- /content -->
        </div><!-- /main-wrap -->
    </div><!-- /layout -->


    <!-- ══ MODAL DETALLE ══════════════════════════════════════════════════════════ -->
    <div class="modal fade modal-detalle" id="modalDetalle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content"
                style="border-radius:16px; border: 1px solid var(--border); box-shadow: 0 20px 60px rgba(30,73,120,.15);">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle del estudiante</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalDetalleBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" style="color: #1E4978 !important;" role="status"></div>
                        <p class="text-muted mt-2" style="font-size:13px;">Cargando información…</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const modalEl = document.getElementById('modalDetalle');
    const modalBS = new bootstrap.Modal(modalEl);
    const modalBody = document.getElementById('modalDetalleBody');

    // ════════════════════════════════════════════════════════════
    // ✅ VERSIÓN MEJORADA CON DEBUGGING
    // ════════════════════════════════════════════════════════════

    function abrirDetalle(alumnoId, cursoId) {
        // Validar parámetros
        if (!alumnoId || !cursoId) {
            modalBody.innerHTML = `<p class="text-danger text-center py-3">❌ Error: Parámetros inválidos</p>`;
            modalBS.show();
            console.error('Parámetros inválidos:', {
                alumnoId,
                cursoId
            });
            return;
        }

        // Mostrar loading
        modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" style="color:#1E4978;" role="status"></div>
            <p class="text-muted mt-2" style="font-size:13px;">Cargando información…</p>
        </div>`;
        modalBS.show();

        // URL del endpoint
        const apiUrl =
            `../administrador/api/estudiantes/detalle_estudiante.php?alumno_id=${alumnoId}&curso_id=${cursoId}`;

        console.log('📡 Fetch a:', apiUrl);

        // Fetch con mejor manejo de errores
        fetch(apiUrl, {
                credentials: 'include'
            })
            .then(response => {
                console.log('📥 Respuesta recibida:', response.status, response.statusText);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                return response.text(); // Primero obtener texto
            })
            .then(texto => {
                console.log('📄 Texto recibido:', texto.substring(0, 200)); // Log primeros 200 caracteres

                try {
                    const data = JSON.parse(texto);
                    console.log('✅ JSON parseado:', data);

                    if (!data.success) {
                        throw new Error(data.error || 'Error en respuesta del servidor');
                    }

                    // Validar estructura de datos
                    if (!data.alumno || !data.curso || !Array.isArray(data.modulos)) {
                        throw new Error('Estructura de datos incompleta en respuesta');
                    }

                    renderDetalle(data);

                } catch (parseError) {
                    console.error('❌ Error parseando JSON:', parseError);
                    modalBody.innerHTML = `
                    <p class="text-danger text-center py-3">
                        ❌ Error procesando datos del servidor:<br>
                        <small>${parseError.message}</small>
                    </p>`;
                }
            })
            .catch(error => {
                console.error('❌ Error en fetch:', error);
                modalBody.innerHTML = `
                <div class="alert alert-danger text-center py-4" role="alert">
                    <i class="bi bi-exclamation-circle" style="font-size:24px;"></i>
                    <p style="margin-top:12px;margin-bottom:0;">
                        <strong>Error de conexión</strong><br>
                        <small style="font-size:12px;">${error.message}</small>
                    </p>
                </div>`;
            });
    }

    // ════════════════════════════════════════════════════════════
    // ✅ FUNCIÓN RENDERDETALLE MEJORADA
    // ════════════════════════════════════════════════════════════

    function renderDetalle(data) {
        console.log('🎨 Renderizando detalle...');

        try {
            const {
                alumno,
                curso,
                modulos
            } = data;

            // Validar datos críticos
            if (!alumno || !curso) {
                throw new Error('Datos de alumno o curso faltantes');
            }

            const pct = alumno.progreso ?? 0;
            const fillCls = pct >= 100 ? 'completo' : pct < 30 ? 'bajo' : '';
            const statusLabel = alumno.completado ? 'Completado' : pct === 0 ? 'Sin iniciar' : 'En progreso';

            const iniciales = (alumno.nombre ?? 'A.A').trim().split(' ')
                .slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');

            const avatarHtml = alumno.avatar ?
                `<img src="${escHtml(alumno.avatar)}" alt="" style="width:100%;height:100%;object-fit:cover;">` :
                `<span style="display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;">${iniciales}</span>`;

            const iconoTipo = {
                video: 'bi-play-circle',
                practica: 'bi-code-slash',
                texto: 'bi-file-text',
                documento: 'bi-file-earmark',
                mp4: 'bi-play-circle',
                mpeg: 'bi-play-circle',
                mov: 'bi-play-circle',
                avi: 'bi-play-circle'
            };

            // Generar HTML de módulos y lecciones
            const modulosHtml = (modulos && modulos.length > 0) ?
                modulos.map(mod => {
                    const leccionesHtml = (mod.lecciones && mod.lecciones.length > 0) ?
                        mod.lecciones.map(l => {
                            const icono = iconoTipo[l.tipo] ?? 'bi-circle';
                            return `
                            <div class="leccion-row" style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #f0f0f0;">
                                <div class="leccion-check" style="font-size:14px;color:#4A90B9;">
                                    <i class="bi ${icono}"></i>
                                </div>
                                <span style="flex:1; color: var(--text, #2c3e50); font-size:13px;">
                                    ${escHtml(l.titulo ?? '(Sin título)')}
                                </span>
                                <span style="font-size:10px;color:#4A90B9;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">
                                    ${escHtml(l.tipo ?? 'desconocido')}
                                </span>
                            </div>
                        `;
                        }).join('') :
                        '<p style="font-size:12px;color:#999;padding:6px 0;">Sin lecciones</p>';

                    return `
                    <div style="margin-bottom:18px;">
                        <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#4A90B9;margin-bottom:8px;">
                            <i class="bi bi-collection" style="margin-right:5px;"></i>${escHtml(mod.titulo ?? '(Sin título)')}
                        </div>
                        ${leccionesHtml}
                    </div>
                `;
                }).join('') :
                '<p style="font-size:13px;color:#999;">Este curso no tiene módulos aún.</p>';

            // Renderizar HTML
            modalBody.innerHTML = `
            <!-- Cabecera alumno -->
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
                <div class="detalle-avatar" style="width:60px;height:60px;border-radius:10px;background:#E8EEF5;display:flex;align-items:center;justify-content:center;font-size:20px;overflow:hidden;">
                    ${avatarHtml}
                </div>
                <div style="flex:1;">
                    <div style="font-family:'Syne',sans-serif;font-size:16px;font-weight:700;color:#1E4978;">
                        ${escHtml(alumno.nombre ?? '(Sin nombre)')}
                    </div>
                    <div style="font-size:12px;color:var(--muted,#5A5A5A);margin-top:2px;">
                        <i class="bi bi-envelope"></i> ${escHtml(alumno.correo ?? '(Sin correo)')}
                    </div>
                    <div style="font-size:11px;color:#4A90B9;margin-top:4px;font-weight:600;">
                        <i class="bi bi-collection-play"></i> ${escHtml(curso.titulo ?? '(Sin título)')}
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px;">
                <div class="detalle-stat" style="background:#F8FAFC;border:1px solid #E5E7EB;border-radius:10px;padding:12px;text-align:center;">
                    <div class="detalle-stat-num" style="font-size:20px;font-weight:700;color:#1E4978;">${pct}%</div>
                    <div class="detalle-stat-label" style="font-size:11px;color:#64748B;font-weight:600;margin-top:4px;">Progreso</div>
                </div>
                <div class="detalle-stat" style="background:#F8FAFC;border:1px solid #E5E7EB;border-radius:10px;padding:12px;text-align:center;">
                    <div class="detalle-stat-num" style="font-size:20px;font-weight:700;color:#1E4978;">${alumno.total_lecciones ?? 0}</div>
                    <div class="detalle-stat-label" style="font-size:11px;color:#64748B;font-weight:600;margin-top:4px;">Lecciones</div>
                </div>
                <div class="detalle-stat" style="background:#F8FAFC;border:1px solid #E5E7EB;border-radius:10px;padding:12px;text-align:center;">
                    <div class="detalle-stat-num" style="font-size:14px;font-weight:700;color:#1E4978;">${statusLabel}</div>
                    <div class="detalle-stat-label" style="font-size:11px;color:#64748B;font-weight:600;margin-top:4px;">Estado</div>
                </div>
            </div>

            <!-- Barra progreso grande -->
            <div style="margin-bottom:24px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--muted,#5A5A5A);margin-bottom:6px;">
                    <span>Progreso del curso</span>
                    <span style="font-weight:700;color:#1E4978;">${pct}%</span>
                </div>
                <div class="progress-bar-track" style="height:10px;background:#E5E7EB;border-radius:6px;overflow:hidden;">
                    <div class="progress-bar-fill ${fillCls}" style="width:${pct}%;background:#1E4978;height:100%;transition:width .3s;"></div>
                </div>
            </div>

        `;

            console.log('✅ Detalle renderizado correctamente');

        } catch (error) {
            console.error('❌ Error en renderDetalle:', error);
            modalBody.innerHTML = `
            <p class="text-danger text-center py-3">
                ❌ Error renderizando datos:<br>
                <small>${error.message}</small>
            </p>`;
        }
    }

    // ════════════════════════════════════════════════════════════
    // FUNCIÓN AUXILIAR: Escapar HTML
    // ════════════════════════════════════════════════════════════

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    console.log('✅ Script cargado correctamente. Funciones disponibles: abrirDetalle, renderDetalle, escHtml');
    </script>
</body>

</html>