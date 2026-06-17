<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();

// ── Sesión ────────────────────────────────────────────────
$uid             = usuarioId();
$nombreUsuario   = usuarioNombre();
$rolSesion       = usuarioRol();
$usuarioLogueado = estaAutenticado();
$iniciales       = '';
$avatarUsuario   = null;

if ($usuarioLogueado) {
    $palabras  = explode(" ", $nombreUsuario);
    $iniciales = strtoupper(substr($palabras[0], 0, 1) . (isset($palabras[1]) ? substr($palabras[1], 0, 1) : ''));
    $stmtAv    = $conexion->prepare("SELECT avatar FROM usuarios WHERE id = ?");
    $stmtAv->bind_param("i", $uid); 
    $stmtAv->execute();
    $resAv = $stmtAv->get_result()->fetch_assoc();
    if ($resAv && !empty($resAv['avatar']) && file_exists($resAv['avatar'])) {
        $avatarUsuario = $resAv['avatar'];
    }
    $stmtAv->close();
}

// ── Curso ─────────────────────────────────────────────────
$cursoId = (int)($_GET["id"] ?? 0);
if (!$cursoId) { header("Location: index.php"); exit; }

$stmtC = $conexion->prepare("
    SELECT c.*, u.nombre AS profesor_nombre, u.avatar AS profesor_avatar
    FROM cursos c
    JOIN usuarios u ON u.id = c.profesor_id
    WHERE c.id = ? AND c.estado = 'publicado'
");
$stmtC->bind_param("i", $cursoId);
$stmtC->execute();
$curso = $stmtC->get_result()->fetch_assoc();
$stmtC->close();

if (!$curso) { header("Location: index.php"); exit; }

// ── Módulos y lecciones ───────────────────────────────────
$stmtM = $conexion->prepare("SELECT id, titulo, orden FROM modulos WHERE curso_id = ? ORDER BY orden ASC");
$stmtM->bind_param("i", $cursoId);
$stmtM->execute();
$modulos = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtM->close();

$totalLecciones = 0;
$todasLasLecciones = []; // Almacén para calcular el progreso inicial

foreach ($modulos as &$mod) {
    $stmtL = $conexion->prepare("
        SELECT id, titulo, tipo, duracion, orden FROM lecciones
        WHERE modulo_id = ? ORDER BY orden ASC
    ");
    $stmtL->bind_param("i", $mod['id']);
    $stmtL->execute();
    $mod['lecciones'] = $stmtL->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtL->close();
    
    $totalLecciones += count($mod['lecciones']);
    // Guardamos las lecciones de forma lineal para detectar la primera fácilmente
    foreach ($mod['lecciones'] as $lec) {
        $todasLasLecciones[] = $lec;
    }
}
unset($mod);

// 🟢 CONFIGURACIÓN DEL PROGRESO (Para que JS no rompa)
$progreso = [
    'leccionId' => 0,
    'completadas' => 0
];

// Si el curso tiene lecciones creadas, asignamos la primera por defecto
if (count($todasLasLecciones) > 0) {
    $progreso['leccionId'] = $todasLasLecciones[0]['id'];
}

// ── Reseñas ───────────────────────────────────────────────
$stmtStats = $conexion->prepare("
    SELECT
        COUNT(*)                  AS total,
        ROUND(AVG(estrellas), 1) AS promedio,
        SUM(estrellas = 5)       AS cinco,
        SUM(estrellas = 4)       AS cuatro,
        SUM(estrellas = 3)       AS tres,
        SUM(estrellas = 2)       AS dos,
        SUM(estrellas = 1)       AS uno
    FROM curso_resenas
    WHERE curso_id = ?
");
$stmtStats->bind_param("i", $cursoId);
$stmtStats->execute();
$statsR = $stmtStats->get_result()->fetch_assoc();
$stmtStats->close();

$stmtListR = $conexion->prepare("
    SELECT cr.estrellas, cr.comentario, cr.created_at,
           u.nombre AS autor, u.avatar AS autor_avatar
    FROM   curso_resenas cr
    JOIN   usuarios u ON u.id = cr.usuario_id
    WHERE  cr.curso_id = ?
    ORDER  BY cr.created_at DESC
    LIMIT  6
");
$stmtListR->bind_param("i", $cursoId);
$stmtListR->execute();
$listaResenas = $stmtListR->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtListR->close();

$totalResenas  = (int)   ($statsR['total']    ?? 0);
$promedioR     = (float) ($statsR['promedio'] ?? 0);
$barrasR       = [
    5 => (int)($statsR['cinco']    ?? 0),
    4 => (int)($statsR['cuatro']   ?? 0),
    3 => (int)($statsR['tres']     ?? 0),
    2 => (int)($statsR['dos']      ?? 0),
    1 => (int)($statsR['uno']      ?? 0)
];

// ¿El usuario ya reseñó?
$yaReseno = false;
if ($usuarioLogueado) {
    $stmtYa = $conexion->prepare("SELECT id FROM curso_resenas WHERE curso_id = ? AND usuario_id = ?");
    $stmtYa->bind_param("ii", $cursoId, $uid); // 🟢 CORREGIDO: Cambiado $idUsuario por $uid
    $stmtYa->execute();
    $yaReseno = (bool) $stmtYa->get_result()->fetch_assoc();
    $stmtYa->close();
}

// Iniciales del profesor
$profPalabras  = explode(" ", $curso['profesor_nombre']);
$profIniciales = strtoupper(substr($profPalabras[0], 0, 1) . (isset($profPalabras[1]) ? substr($profPalabras[1], 0, 1) : ''));

// Iconos y colores por tipo de lección
function tipoIcon($tipo) {
    return match($tipo) {
        'video'     => 'bi-play-circle-fill',
        'practica'  => 'bi-code-slash',
        'texto'     => 'bi-file-text-fill',
        'documento' => 'bi-file-earmark-fill',
        default     => 'bi-circle',
    };
}

function tipoColor($tipo) {
    return match($tipo) {
        'video'     => '#219EBC',
        'practica'  => '#FB8500',
        'texto'     => '#FFB703',
        'documento' => '#8ECAE6',
        default     => '#aaa',
    };
}

// ── Progreso real del usuario (desde la BD) ───────────────
$leccionesVistas = 0;
$inscrito        = false;
if ($usuarioLogueado) {
    // ¿Está inscrito?
    $stmtIns = $conexion->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND curso_id = ?");
    $stmtIns->bind_param("ii", $uid, $cursoId);
    $stmtIns->execute();
    $inscrito = (bool) $stmtIns->get_result()->fetch_assoc();
    $stmtIns->close();

    // Lecciones completadas
    $stmtPL = $conexion->prepare("
        SELECT COUNT(*) AS vistas
        FROM progreso_lecciones
        WHERE usuario_id = ? AND curso_id = ?
    ");
    $stmtPL->bind_param("ii", $uid, $cursoId);
    $stmtPL->execute();
    $leccionesVistas = (int) ($stmtPL->get_result()->fetch_assoc()['vistas'] ?? 0);
    $stmtPL->close();
}

$pctProgreso = $totalLecciones > 0 ? (int) round($leccionesVistas / $totalLecciones * 100) : 0;
$tieneProgreso = $leccionesVistas > 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($curso['titulo']) ?> | EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/sverCurso.css">

</head>

<body>

    <!-- ══ NAVBAR ══════════════════════════════════════════════ -->
    <header class="d-flex flex-wrap justify-content-between align-items-center py-3 px-4" id="prin">
        <a href="index.php" class="d-flex align-items-center text-decoration-none">
            <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="50">
        </a>
        <ul class="nav align-items-center gap-1 mb-0">
            <li class="nav-item">
                <input type="search" id="courseSearch" placeholder="Buscar cursos...">
            </li>
            <li><a href="index.php?uid=<?= $uid ?>" class="nav-link active">Inicio</a></li>
            <li><a href="index.php#cursos" class="nav-link">Cursos</a></li>
            <li><a href="foroEs.php?uid=<?= $uid ?>" class="nav-link">Foro</a></li>

            <?php if ($usuarioLogueado): ?>
            <li>
                <a href="perfil.php" class="nav-link px-2">
                    <?php if ($avatarUsuario): ?>
                    <img src="<?= htmlspecialchars($avatarUsuario) ?>" class="user-avatar-img" alt="">
                    <?php else: ?>
                    <div class="user-avatar"><?= $iniciales ?></div>
                    <?php endif; ?>
                </a>
            </li>
            <?php else: ?>
            <li>
                <a href="../login.php" class="nav-link fw-semibold"
                    style="background:var(--amarillo);color:var(--marino);border-radius:10px;padding:8px 18px;">
                    Ingresar
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </header>

    <!-- ══ HERO ════════════════════════════════════════════════ -->
    <section class="hero-curso">
        <div class="hero-inner">
            <div class="row g-5">
                <div class="col-lg-8">



                    <!-- Categoría -->
                    <div class="curso-cat-badge">
                        <?= htmlspecialchars($curso['categoria'] ?? 'General') ?>
                    </div>

                    <h1 class="hero-titulo"><?= htmlspecialchars($curso['titulo']) ?></h1>
                    <p class="hero-desc"><?= htmlspecialchars($curso['descripcion'] ?? '') ?></p>

                    <!-- Meta chips -->
                    <div class="meta-chips">
                        <div class="meta-chip">
                            <i class="bi bi-collection"></i>
                            <strong><?= count($modulos) ?></strong>&nbsp;módulos
                        </div>
                        <div class="meta-chip">
                            <i class="bi bi-play-circle"></i>
                            <strong><?= $totalLecciones ?></strong>&nbsp;lecciones
                        </div>
                        <?php if ($curso['duracion_total'] > 0): ?>
                        <div class="meta-chip">
                            <i class="bi bi-clock"></i>
                            <strong><?= $curso['duracion_total'] ?></strong>&nbsp;min
                        </div>
                        <?php endif; ?>
                        <?php if ($totalResenas > 0): ?>
                        <div class="meta-chip" style="cursor:pointer;"
                            onclick="document.querySelector('[data-target=tab-resenas]').click();window.scrollTo({top:document.querySelector('.tabs-wrap').offsetTop-10,behavior:'smooth'});">
                            <i class="bi bi-star-fill" style="color:var(--amarillo);"></i>
                            <strong><?= number_format($promedioR, 1) ?></strong>
                            <span style="font-size:11px;opacity:.8;">(<?= $totalResenas ?>)</span>
                        </div>
                        <?php endif; ?>

                    </div>

                    <!-- Profesor -->
                    <div class="prof-chip">
                        <?php if (!empty($curso['profesor_avatar']) && file_exists($curso['profesor_avatar'])): ?>
                        <img src="<?= htmlspecialchars($curso['profesor_avatar']) ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;
                                    border:2px solid rgba(255,255,255,.2);">
                        <?php else: ?>
                        <div class="prof-avatar-hero"><?= $profIniciales ?></div>
                        <?php endif; ?>
                        <div class="prof-chip-info">
                            <strong><?= htmlspecialchars($curso['profesor_nombre']) ?></strong>
                            Instructor del curso
                        </div>
                    </div>

                </div>

                <!-- Tarjeta de acción (desktop) -->
                <div class="col-lg-4 d-none d-lg-block">
                    <div class="action-card" style="margin-top:-20px;">
                        <?php if (!empty($curso['imagen']) && file_exists($curso['imagen'])): ?>
                        <img src="<?= htmlspecialchars($curso['imagen']) ?>" class="action-card-img"
                            alt="<?= htmlspecialchars($curso['titulo']) ?>">
                        <?php else: ?>
                        <div class="action-card-placeholder"><?= htmlspecialchars($curso['emoji'] ?? ' ') ?></div>
                        <?php endif; ?>
                        <div class="action-card-body">
                            <?php if ($usuarioLogueado): ?>
                            <!-- Barra de progreso desde la BD -->
                            <?php if ($tieneProgreso): ?>
                            <div id="progreso-desktop" style="margin-bottom:14px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;
                                            font-size:12px;color:var(--muted);margin-bottom:6px;">
                                    <span><i class="bi bi-bar-chart-fill"
                                            style="color:var(--azul);margin-right:4px;"></i>Tu progreso</span>
                                    <span><?= $leccionesVistas ?> / <?= $totalLecciones ?> (<?= $pctProgreso ?>%)</span>
                                </div>
                                <div style="background:rgba(0,0,0,.08);border-radius:10px;height:8px;overflow:hidden;">
                                    <div style="height:100%;width:<?= $pctProgreso ?>%;background:linear-gradient(90deg,var(--azul),var(--cielo));
                                                border-radius:10px;transition:width .5s ease;"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <a id="btn-iniciar-desktop" href="tomaCurso.php?id=<?= $cursoId ?>" class="btn-iniciar">
                                <i class="bi bi-play-circle-fill"></i>
                                <?= $tieneProgreso ? 'Continuar' : 'Iniciar curso' ?>
                            </a>
                            <?php else: ?>
                            <a href="login.php" class="btn-iniciar">
                                <i class="bi bi-lock-fill"></i> Ingresar para comenzar
                            </a>
                            <?php endif; ?>
                            <ul class="action-include">
                                <?php if ($curso['duracion_total'] > 0): ?>
                                <li><i class="bi bi-clock-fill"></i> <?= $curso['duracion_total'] ?> minutos de
                                    contenido</li>
                                <?php endif; ?>
                                <li><i class="bi bi-collection-fill"></i> <?= count($modulos) ?> módulos</li>
                                <li><i class="bi bi-play-btn-fill"></i> <?= $totalLecciones ?> lecciones</li>
                                <li><i class="bi bi-code-slash"></i> Ejercicios prácticos</li>
                                <li><i class="bi bi-infinity"></i> Acceso de por vida</li>

                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══ TABS NAV ═════════════════════════════════════════════ -->
    <div class="tabs-wrap">
        <div class="tabs-inner">
            <button class="tab-btn active" data-target="tab-desc">
                <i class="bi bi-journal-text me-1"></i> Descripción
            </button>
            <button class="tab-btn" data-target="tab-contenido">
                <i class="bi bi-collection-play me-1"></i> Contenido
            </button>
            <button class="tab-btn" data-target="tab-profesor">
                <i class="bi bi-person-badge me-1"></i> Instructor
            </button>
            <button class="tab-btn" data-target="tab-resenas">
                <i class="bi bi-star me-1"></i> Reseñas
            </button>
        </div>
    </div>

    <!-- Botón iniciar mobile -->
    <div class="d-lg-none" style="background:var(--marino);padding:16px 1.5rem;">
        <?php if ($usuarioLogueado): ?>
        <!-- Barra de progreso mobile -->
        <div id="progreso-mobile" style="display:none;margin-bottom:12px;">
            <div style="display:flex;justify-content:space-between;align-items:center;
                        font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;">
                <span><i class="bi bi-bar-chart-fill" style="color:var(--cielo);margin-right:4px;"></i>Tu
                    progreso</span>
                <span id="progreso-texto-mobile">0 / <?= $totalLecciones ?></span>
            </div>
            <div style="background:rgba(255,255,255,.12);border-radius:10px;height:8px;overflow:hidden;">
                <div id="progreso-barra-mobile" style="height:100%;width:0%;background:linear-gradient(90deg,var(--azul),var(--cielo));
                            border-radius:10px;transition:width .5s ease;"></div>
            </div>
        </div>
        <a id="btn-iniciar-mobile" href="tomaCurso.php?id=<?= $cursoId ?>" class="btn-iniciar">
            <i class="bi bi-play-circle-fill"></i> Iniciar curso
        </a>
        <?php else: ?>
        <a href="login.php" class="btn-iniciar">
            <i class="bi bi-lock-fill"></i> Ingresar para comenzar
        </a>
        <?php endif; ?>
    </div>

    <!-- ══ CUERPO PRINCIPAL ═════════════════════════════════════ -->
    <div class="page-body">
        <div class="row g-5">
            <div class="col-lg-8">

                <!-- ── TAB: Descripción ── -->
                <div id="tab-desc" class="tab-panel active">

                    <!-- <div class="desc-section-title">
                    <i class="bi bi-check2-all" style="color:var(--azul);"></i>
                    Lo que aprenderás
                </div> -->
                    <!-- <div class="what-learn-grid">
                    <div class="what-item"><i class="bi bi-check-circle-fill"></i> Fundamentos sólidos desde cero</div>
                    <div class="what-item"><i class="bi bi-check-circle-fill"></i> Proyectos reales aplicables</div>
                    <div class="what-item"><i class="bi bi-check-circle-fill"></i> Buenas prácticas del industria</div>
                    <div class="what-item"><i class="bi bi-check-circle-fill"></i> Ejercicios prácticos interactivos</div>
                    <div class="what-item"><i class="bi bi-check-circle-fill"></i> Código base descargable</div>
                    <div class="what-item"><i class="bi bi-check-circle-fill"></i> Certificado al finalizar</div>
                </div> -->

                    <div class="desc-section-title">
                        <i class="bi bi-book-half" style="color:var(--azul);"></i>
                        Acerca del curso
                    </div>
                    <div class="desc-text">
                        <?= nl2br(htmlspecialchars($curso['descripcion'] ?? 'Próximamente más información sobre este curso.')) ?>
                    </div>

                </div>

                <!-- ── TAB: Contenido ── -->
                <div id="tab-contenido" class="tab-panel">
                    <div class="desc-section-title">
                        <i class="bi bi-list-ol" style="color:var(--azul);"></i>
                        Estructura del curso
                    </div>
                    <p style="font-size:14px;color:var(--muted);margin-bottom:20px;">
                        <?= count($modulos) ?> módulos · <?= $totalLecciones ?> lecciones
                    </p>

                    <?php if (empty($modulos)): ?>
                    <div style="text-align:center;padding:50px;color:var(--muted);">
                        <i class="bi bi-collection" style="font-size:40px;display:block;margin-bottom:12px;"></i>
                        El contenido del curso aún está siendo preparado.
                    </div>
                    <?php else: ?>
                    <?php foreach ($modulos as $mi => $mod): ?>
                    <div class="modulo-block">
                        <div class="modulo-header" onclick="toggleModulo(this)">
                            <div class="mod-left">
                                <div class="mod-num"><?= $mi + 1 ?></div>
                                <div>
                                    <div class="mod-title"><?= htmlspecialchars($mod['titulo']) ?></div>
                                    <div class="mod-meta">
                                        <?= count($mod['lecciones']) ?>
                                        lección<?= count($mod['lecciones']) != 1 ? 'es' : '' ?>
                                    </div>
                                </div>
                            </div>
                            <i class="bi bi-chevron-down mod-chevron"></i>
                        </div>
                        <div class="lecciones-list" <?= $mi === 0 ? 'style="display:block;"' : '' ?>>
                            <?php foreach ($mod['lecciones'] as $li => $lec): ?>
                            <div class="leccion-row">
                                <i class="bi <?= tipoIcon($lec['tipo']) ?> lec-icon"
                                    style="color:<?= tipoColor($lec['tipo']) ?>;"></i>
                                <span class="lec-title">
                                    <?= ($li + 1) ?>. <?= htmlspecialchars($lec['titulo']) ?>
                                </span>
                                <?php if ($lec['duracion'] > 0): ?>
                                <span class="lec-dur">
                                    <i class="bi bi-clock"></i> <?= $lec['duracion'] ?> min
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($mod['lecciones'])): ?>
                            <div style="padding:16px 32px;font-size:13px;color:var(--muted);">
                                Sin lecciones aún.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- ── TAB: Instructor ── -->
                <div id="tab-profesor" class="tab-panel">
                    <div class="prof-card">
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <?php if (!empty($curso['profesor_avatar']) && file_exists($curso['profesor_avatar'])): ?>
                            <img src="<?= htmlspecialchars($curso['profesor_avatar']) ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;
                                        border:3px solid var(--amarillo);" alt="">
                            <?php else: ?>
                            <div class="prof-big-avatar"><?= $profIniciales ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="prof-big-name"><?= htmlspecialchars($curso['profesor_nombre']) ?></div>
                                <span class="prof-big-role">Instructor ·
                                    <?= htmlspecialchars($curso['categoria'] ?? 'Tecnología') ?></span>
                            </div>
                        </div>
                        <p class="prof-bio">
                            <?= htmlspecialchars($curso['profesor_nombre']) ?> es instructor en EduTecnia con
                            experiencia
                            en el área de <?= htmlspecialchars($curso['categoria'] ?? 'tecnología') ?>.
                            Ha diseñado este curso para que cualquier persona pueda aprender de manera práctica
                            y efectiva, desde los fundamentos hasta proyectos reales.
                        </p>
                    </div>
                </div>

                <!-- ── TAB: Reseñas ── -->
                <div id="tab-resenas" class="tab-panel">

                    <!-- Encabezado + botón dejar reseña -->
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                        <div class="desc-section-title" style="margin-bottom:0;">
                            <i class="bi bi-star-fill" style="color:var(--amarillo);"></i>
                            Reseñas del curso
                        </div>
                        <?php if ($usuarioLogueado): ?>
                        <a href="resena.php?curso_id=<?= $cursoId ?>" style="background:var(--amarillo);color:var(--marino);border-radius:8px;font-size:13px;
                              font-weight:700;padding:8px 18px;text-decoration:none;display:inline-flex;
                              align-items:center;gap:6px;transition:background .15s,color .15s;"
                            onmouseover="this.style.background='var(--naranja)';this.style.color='#fff'"
                            onmouseout="this.style.background='var(--amarillo)';this.style.color='var(--marino)'">
                            <i class="bi bi-<?= $yaReseno ? 'pencil' : 'plus-circle' ?>"></i>
                            <?= $yaReseno ? 'Editar mi reseña' : 'Dejar una reseña' ?>
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($totalResenas > 0): ?>

                    <!-- Resumen: número grande + barras -->
                    <div style="background:#f8fbfc;border:1px solid var(--border);border-radius:14px;
                            padding:1.25rem 1.5rem;margin-bottom:1.5rem;">
                        <div class="row align-items-center g-3">

                            <!-- Promedio -->
                            <div class="col-auto text-center" style="min-width:110px;">
                                <div style="font-size:3.2rem;font-weight:800;color:var(--marino);line-height:1;">
                                    <?= number_format($promedioR, 1) ?>
                                </div>
                                <div style="display:flex;gap:3px;justify-content:center;margin:.3rem 0;">
                                    <?php for ($i = 1; $i <= 5; $i++):
                                    $full = $i <= floor($promedioR);
                                    $half = !$full && ($promedioR - floor($promedioR)) >= 0.5 && $i == ceil($promedioR);
                                ?>
                                    <i class="bi bi-star<?= $full ? '-fill' : ($half ? '-half' : '') ?>"
                                        style="color:<?= ($full || $half) ? 'var(--amarillo)' : '#d1d5db' ?>;font-size:14px;"></i>
                                    <?php endfor; ?>
                                </div>
                                <div style="font-size:12px;color:var(--muted);">
                                    <?= $totalResenas ?> reseña<?= $totalResenas !== 1 ? 's' : '' ?>
                                </div>
                            </div>

                            <!-- Barras de distribución -->
                            <div class="col">
                                <?php foreach ($barrasR as $n => $cant):
                                $pct = $totalResenas > 0 ? round(($cant / $totalResenas) * 100) : 0;
                            ?>
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                    <span
                                        style="font-size:11px;color:var(--muted);width:12px;text-align:right;"><?= $n ?></span>
                                    <i class="bi bi-star-fill"
                                        style="color:var(--amarillo);font-size:11px;flex-shrink:0;"></i>
                                    <div
                                        style="flex:1;background:#e8eef2;border-radius:10px;height:7px;overflow:hidden;">
                                        <div style="width:<?= $pct ?>%;background:var(--amarillo);height:100%;
                                                border-radius:10px;transition:width .5s;"></div>
                                    </div>
                                    <span style="font-size:11px;color:var(--muted);width:24px;"><?= $cant ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>

                        </div>
                    </div>

                    <!-- Lista de reseñas -->
                    <?php foreach ($listaResenas as $r):
                    $pA  = explode(" ", $r['autor']);
                    $inA = strtoupper(substr($pA[0],0,1) . (isset($pA[1]) ? substr($pA[1],0,1) : ''));
                ?>
                    <div style="background:#fff;border:1px solid var(--border);border-radius:12px;
                            padding:1rem 1.25rem;margin-bottom:10px;
                            box-shadow:0 1px 4px rgba(2,48,71,.04);">

                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                            <!-- Avatar -->
                            <?php if (!empty($r['autor_avatar']) && file_exists($r['autor_avatar'])): ?>
                            <img src="<?= htmlspecialchars($r['autor_avatar']) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;
                                    border:2px solid var(--cielo);flex-shrink:0;" alt="">
                            <?php else: ?>
                            <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--azul),var(--marino-2));
                                    color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;
                                    justify-content:center;flex-shrink:0;border:2px solid var(--cielo);"><?= $inA ?>
                            </div>
                            <?php endif; ?>

                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:700;color:var(--marino);">
                                    <?= htmlspecialchars($r['autor']) ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);">
                                    <?= date('d M Y', strtotime($r['created_at'])) ?>
                                </div>
                            </div>

                            <!-- Estrellas -->
                            <div style="display:flex;gap:2px;flex-shrink:0;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $r['estrellas'] ? '-fill' : '' ?>"
                                    style="font-size:12px;color:<?= $i <= $r['estrellas'] ? 'var(--amarillo)' : '#d1d5db' ?>;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <?php if (!empty($r['comentario'])): ?>
                        <p style="font-size:13px;color:var(--text);margin:0;line-height:1.65;">
                            <?= htmlspecialchars($r['comentario']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($totalResenas > 6): ?>
                    <div style="text-align:center;padding-top:.5rem;">
                        <small style="font-size:12px;color:var(--muted);">
                            Mostrando 6 de <?= $totalResenas ?> reseñas
                        </small>
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <!-- Sin reseñas -->
                    <div class="review-empty">
                        <i class="bi bi-chat-square-text" style="color:var(--cielo);"></i>
                        <p style="font-size:15px;font-weight:600;color:var(--marino);">
                            Aún no hay reseñas para este curso
                        </p>
                        <p style="font-size:14px;color:var(--muted);">
                            ¡Sé el primero en dejar tu opinión después de completarlo!
                        </p>
                        <?php if ($usuarioLogueado): ?>
                        <a href="resena.php?id=<?= $cursoId ?>" style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;
                              background:var(--amarillo);color:var(--marino);border-radius:8px;
                              font-size:13px;font-weight:700;padding:8px 18px;text-decoration:none;">
                            <i class="bi bi-plus-circle"></i> Dejar una reseña
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                </div>

            </div>

            <!-- Tarjeta lateral desktop (sticky) -->
            <div class="col-lg-4 d-none d-lg-block">
                <!-- Ya aparece en el hero, aquí espacio para cursos relacionados -->
                <div
                    style="background:#fff;border:1px solid var(--border);border-radius:var(--r);padding:20px;margin-top:40px;">
                    <div class="desc-section-title" style="font-size:15px;">
                        <i class="bi bi-grid" style="color:var(--azul);"></i>
                        Más cursos
                    </div>
                    <p style="font-size:13px;color:var(--muted);">
                        Explora otros cursos disponibles en la plataforma.
                    </p>
                    <a href="index.php#cursos" style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;
                          color:var(--azul);text-decoration:none;">
                        Ver catálogo <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ FOOTER ═══════════════════════════════════════════════ -->
    <footer>
        <div id="div-footer">
            <span>
                © 2026 Universidad Autónoma Metropolitana, Cuajimalpa
            </span>
            <div style="display:flex;gap:24px;">
                <a href="index.php" style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Inicio</a>
                <a href="index.php#cursos"
                    style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Cursos</a>
                <a href="foro.php" style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Foro</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ── Tabs ─────────────────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.target).classList.add('active');
        });
    });

    // ── Acordeón módulos ─────────────────────────────────────
    function toggleModulo(header) {
        const lista = header.nextElementSibling;
        const isOpen = lista.style.display === 'block';
        lista.style.display = isOpen ? 'none' : 'block';
        header.classList.toggle('open', !isOpen);
    }


    </script>
</body>

</html>