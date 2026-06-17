<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();

if (!estaAutenticado()) { header("Location: ../login.php"); exit; }

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$iniciales     = inicialesAvatar($nombreUsuario);
$avatarUsuario = null;

$stmtAv = $conexion->prepare("SELECT avatar FROM usuarios WHERE id = ?");
$stmtAv->bind_param("i", $uid);
$stmtAv->execute();
$resAv = $stmtAv->get_result()->fetch_assoc();
$stmtAv->close();
if ($resAv && !empty($resAv['avatar'])) {
    $rutaAbs = __DIR__ . '/../' . ltrim($resAv['avatar'], '/');
    if (file_exists($rutaAbs)) $avatarUsuario = $resAv['avatar'];
}

// ── Curso ─────────────────────────────────────────────────
$cursoId = (int)($_GET['id'] ?? 0);
if (!$cursoId) { header("Location: index.php"); exit; }

$stmtC = $conexion->prepare("
    SELECT c.*, u.nombre AS profesor_nombre
    FROM cursos c JOIN usuarios u ON u.id = c.profesor_id
    WHERE c.id = ? AND c.estado = 'publicado'
");
$stmtC->bind_param("i", $cursoId);
$stmtC->execute();
$curso = $stmtC->get_result()->fetch_assoc();
$stmtC->close();
if (!$curso) { header("Location: index.php"); exit; }

// ── Verificar inscripción ─────────────────────────────────
$stmtIns = $conexion->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND curso_id = ?");
$stmtIns->bind_param("ii", $uid, $cursoId);
$stmtIns->execute();
if (!$stmtIns->get_result()->fetch_assoc()) {
    header("Location: verCurso.php?id=$cursoId"); exit;
}
$stmtIns->close();

// ── Módulos + lecciones ───────────────────────────────────
$stmtM = $conexion->prepare("SELECT id, titulo, orden FROM modulos WHERE curso_id = ? ORDER BY orden ASC");
$stmtM->bind_param("i", $cursoId);
$stmtM->execute();
$modulos = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtM->close();

$todasLecciones = [];
$totalLecciones = 0;

foreach ($modulos as &$mod) {
    $stmtL = $conexion->prepare("
        SELECT id, titulo, tipo, url, descripcion, codigo_base, lenguaje, duracion, orden
        FROM lecciones WHERE modulo_id = ? ORDER BY orden ASC
    ");
    $stmtL->bind_param("i", $mod['id']);
    $stmtL->execute();
    $mod['lecciones'] = $stmtL->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtL->close();
    foreach ($mod['lecciones'] as $lec) {
        $todasLecciones[] = ['modulo' => $mod['titulo'], 'lec' => $lec];
    }
    $totalLecciones += count($mod['lecciones']);
}
unset($mod);

// ── Lecciones vistas desde BD ─────────────────────────────
$leccionesVistas = [];
$stmtV = $conexion->prepare("
    SELECT leccion_id FROM progreso_lecciones
    WHERE usuario_id = ? AND curso_id = ?
");
$stmtV->bind_param("ii", $uid, $cursoId);
$stmtV->execute();
$rowsV = $stmtV->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtV->close();
foreach ($rowsV as $rv) $leccionesVistas[] = (int)$rv['leccion_id'];

$totalVistas    = count($leccionesVistas);
$porcentaje     = $totalLecciones > 0 ? round($totalVistas / $totalLecciones * 100) : 0;
$todasCompletas = ($totalVistas >= $totalLecciones && $totalLecciones > 0);

// ── Lección activa ────────────────────────────────────────
$leccionId     = (int)($_GET['leccion'] ?? 0);
$leccionActiva = null;
$leccionIndex  = 0;

foreach ($todasLecciones as $i => $item) {
    if ($leccionId && $item['lec']['id'] === $leccionId) {
        $leccionActiva = $item['lec'];
        $leccionIndex  = $i;
        break;
    }
}
if (!$leccionActiva && !empty($todasLecciones)) {
    $leccionActiva = $todasLecciones[0]['lec'];
    $leccionIndex  = 0;
}

$leccionAnterior  = $todasLecciones[$leccionIndex - 1]['lec'] ?? null;
$leccionSiguiente = $todasLecciones[$leccionIndex + 1]['lec'] ?? null;

// ── Helpers ───────────────────────────────────────────────
function embedUrl($url) {
    if (!$url) return '';
    if (str_contains($url, 'youtube.com/watch?v='))
        return str_replace('watch?v=', 'embed/', $url);
    if (str_contains($url, 'youtu.be/'))
        return 'https://www.youtube.com/embed/' . substr(strrchr($url, '/'), 1);
    return $url;
}
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
function tipoLabel($tipo) {
    return match($tipo) {
        'video'     => 'Video',
        'practica'  => 'Práctica',
        'texto'     => 'Lectura',
        'documento' => 'Documento',
        default     => 'Lección',
    };
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($leccionActiva['titulo'] ?? $curso['titulo']) ?> | EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/stomarCurso.css">
    <style>
    /* ── Checkmark lección vista ── */
    .lec-check {
        margin-left: auto;
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        border: 1.5px solid rgba(255, 255, 255, .2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        color: transparent;
        transition: all .2s;
    }

    .lec-item.vista .lec-check {
        background: #1a8a5a;
        border-color: #1a8a5a;
        color: #fff;
    }

    .lec-item.vista .lec-title-sb {
        opacity: .65;
    }

    /* ── Botón completar curso ── */
    #btnCompletarCurso {
        opacity: .4;
        cursor: not-allowed;
        pointer-events: none;
    }

    #btnCompletarCurso.habilitado {
        opacity: 1;
        cursor: pointer;
        pointer-events: auto;
        background: var(--amarillo) !important;
        color: var(--marino) !important;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            box-shadow: 0 0 0 0 rgba(255, 183, 3, .4);
        }

        50% {
            box-shadow: 0 0 0 8px rgba(255, 183, 3, 0);
        }
    }

    /* ── Progreso badge ── */
    .prog-badge {
        font-size: 12px;
        font-weight: 600;
        background: rgba(33, 158, 188, .15);
        color: #8ECAE6;
        border-radius: 20px;
        padding: 3px 10px;
    }
    </style>
</head>

<body>

    <!-- ══ TOPBAR ════════════════════════════════════════════════ -->
    <div class="topbar">
        <button class="btn-toggle" id="btnToggle" title="Ocultar/mostrar índice">
            <i class="bi bi-layout-sidebar-reverse"></i>
        </button>
        <a href="index.php" class="topbar-logo">
            <img src="../img/logoEduTecnia.png" alt="EduTecnia">
        </a>
        <div class="topbar-curso">
            <span><?= htmlspecialchars($curso['titulo']) ?></span>
        </div>
        <div class="topbar-progress">
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill" id="topbar-pbar" style="width:<?= $porcentaje ?>%"></div>
            </div>
            <span id="topbar-pct" class="prog-badge"><?= $porcentaje ?>%</span>
        </div>
        <a href="perfil.php" class="topbar-user" title="<?= htmlspecialchars($nombreUsuario) ?>">
            <?php if ($avatarUsuario): ?>
            <img src="<?= htmlspecialchars($avatarUsuario) ?>" class="user-avatar-img" alt="">
            <?php else: ?>
            <div class="user-avatar"><?= $iniciales ?></div>
            <?php endif; ?>
        </a>
        <a href="verCurso.php?id=<?= $cursoId ?>" class="btn-salir">
            <i class="bi bi-arrow-left"></i> Salir
        </a>
    </div>

    <!-- ══ LAYOUT ═══════════════════════════════════════════════ -->
    <div class="player-layout">

        <!-- ── SIDEBAR ── -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h6>Contenido del curso</h6>
                <div class="sidebar-progress-text">
                    <span id="sb-vistas"><?= $totalVistas ?></span> / <?= $totalLecciones ?> lecciones completadas
                </div>
                <div
                    style="margin-top:8px;background:rgba(255,255,255,.06);border-radius:10px;height:4px;overflow:hidden;">
                    <div id="sb-pbar"
                        style="height:100%;width:<?= $porcentaje ?>%;
                    background:linear-gradient(90deg,var(--azul),var(--cielo));border-radius:10px;transition:width .4s;">
                    </div>
                </div>
            </div>

            <div class="sidebar-body">
                <?php foreach ($modulos as $mi => $mod): ?>
                <div class="mod-group">
                    <div class="mod-group-header <?= ($mi === 0 || array_filter($mod['lecciones'], fn($l) => $l['id'] === ($leccionActiva['id'] ?? 0))) ? 'open' : '' ?>"
                        onclick="toggleMod(this)">
                        <div class="mod-num-sb"><?= $mi + 1 ?></div>
                        <div class="mod-title-sb"><?= htmlspecialchars($mod['titulo']) ?></div>
                        <i class="bi bi-chevron-down mod-chevron-sb"></i>
                    </div>
                    <div
                        class="lec-list <?= ($mi === 0 || array_filter($mod['lecciones'], fn($l) => $l['id'] === ($leccionActiva['id'] ?? 0))) ? 'open' : '' ?>">
                        <?php foreach ($mod['lecciones'] as $lec):
                        $esVista = in_array((int)$lec['id'], $leccionesVistas);
                    ?>
                        <a href="tomaCurso.php?id=<?= $cursoId ?>&leccion=<?= $lec['id'] ?>"
                            class="lec-item <?= ($leccionActiva && $lec['id'] === $leccionActiva['id']) ? 'activa' : '' ?> <?= $esVista ? 'vista' : '' ?>"
                            data-leccion-id="<?= $lec['id'] ?>">
                            <i class="bi <?= tipoIcon($lec['tipo']) ?> lec-icon-sb"
                                style="color:<?= tipoColor($lec['tipo']) ?>;"></i>
                            <span class="lec-title-sb"><?= htmlspecialchars($lec['titulo']) ?></span>
                            <?php if ($lec['duracion'] > 0): ?>
                            <span class="lec-dur-sb"><?= $lec['duracion'] ?>m</span>
                            <?php endif; ?>
                            <span class="lec-check"><i class="bi bi-check"></i></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <!-- ── PANEL PRINCIPAL ── -->
        <div class="main-panel">
            <div class="content-area">

                <?php if (!$leccionActiva): ?>
                <div class="lec-empty">
                    <i class="bi bi-collection-play"></i>
                    <p>Este curso aún no tiene lecciones.</p>
                </div>
                <?php else: ?>

                <!-- Header lección -->
                <div class="lec-header">
                    <div class="lec-breadcrumb">
                        <?= htmlspecialchars($curso['titulo']) ?>
                        <i class="bi bi-chevron-right"></i>
                        <?= htmlspecialchars($todasLecciones[$leccionIndex]['modulo'] ?? '') ?>
                    </div>
                    <div class="lec-tipo-badge" style="background:<?= tipoColor($leccionActiva['tipo']) ?>20;
                            color:<?= tipoColor($leccionActiva['tipo']) ?>;
                            border:1px solid <?= tipoColor($leccionActiva['tipo']) ?>40;">
                        <i class="bi <?= tipoIcon($leccionActiva['tipo']) ?>"></i>
                        <?= tipoLabel($leccionActiva['tipo']) ?>
                    </div>
                    <h1 class="lec-titulo"><?= htmlspecialchars($leccionActiva['titulo']) ?></h1>
                </div>

                <!-- Contenido según tipo -->
                <?php if ($leccionActiva['tipo'] === 'video'): ?>
                <div class="video-wrap">
                    <?php if (!empty($leccionActiva['url'])): ?>
                    <iframe src="<?= htmlspecialchars(embedUrl($leccionActiva['url'])) ?>"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
                    <?php else: ?>
                    <div class="video-placeholder">
                        <i class="bi bi-play-circle"></i>
                        <p>El video de esta lección aún no está disponible.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <?php elseif ($leccionActiva['tipo'] === 'practica'): ?>
                <?php $lang = strtolower(trim($leccionActiva['lenguaje'] ?? 'python')); ?>
                <iframe frameborder="0" width="100%" height="500px"
                    src="https://onecompiler.com/embed/<?= htmlspecialchars($lang) ?>?theme=dark"></iframe>

                <?php elseif ($leccionActiva['tipo'] === 'documento'): ?>
                <?php
                    $urlDoc = $leccionActiva['url'] ?? '';
                    $ext    = strtolower(pathinfo(parse_url($urlDoc, PHP_URL_PATH), PATHINFO_EXTENSION));

                    // Carpeta raíz de la app (sube desde /pp/estudiante o /pp/profesor hasta /pp)
                    // Ajusta '/pp' si tu proyecto se llama distinto.
                    $appRoot = '/pp';

                    // Ruta ABSOLUTA desde la raíz del sitio: los archivos SIEMPRE están en profesor/
                    if ($urlDoc && !preg_match('#^https?://#i', $urlDoc)) {
                        $rutaRel = ltrim($urlDoc, '/');                 // uploads/cursos/curso_23/archivo.pdf
                        $urlDoc  = $appRoot . '/profesor/' . $rutaRel;  // /pp/profesor/uploads/cursos/...
                    }

                    // URL absoluta completa (para el visor de Office)
                    $urlAbs = $urlDoc;
                    if (!preg_match('#^https?://#i', $urlDoc)) {
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $urlAbs = $scheme . '://' . $_SERVER['HTTP_HOST'] . $urlDoc;
                    }

                    $esPdf    = $ext === 'pdf';
                    $esOffice = in_array($ext, ['ppt', 'pptx', 'doc', 'docx', 'xls', 'xlsx']);
                ?>

                <!-- Visor del documento -->
                <?php if (!empty($urlDoc)): ?>
                <?php if ($esPdf): ?>
                <div class="doc-viewer">
                    <iframe src="<?= htmlspecialchars($urlDoc) ?>#toolbar=1"
                        title="Vista previa del documento"></iframe>
                </div>

                <?php elseif ($esOffice): ?>
                <div class="doc-viewer">
                    <iframe src="https://view.officeapps.live.com/op/embed.aspx?src=<?= urlencode($urlAbs) ?>"
                        title="Vista previa de la presentación" allowfullscreen></iframe>
                </div>
                <p class="doc-note">
                    <i class="bi bi-info-circle"></i>
                    La vista previa de Office requiere que el archivo sea accesible públicamente.
                    Si no se ve (por ejemplo en <strong>localhost</strong>), usa el botón Descargar.
                </p>

                <?php else: ?>
                <div class="doc-viewer doc-viewer--empty">
                    <i class="bi bi-file-earmark-text"></i>
                    <p>Este formato no se puede previsualizar. Descárgalo para verlo.</p>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Tarjeta con info + descargar -->
                <div class="doc-card">
                    <div class="doc-icon-wrap"><i
                            class="bi <?= $esPdf ? 'bi-file-earmark-pdf-fill' : ($esOffice ? 'bi-file-earmark-slides-fill' : 'bi-file-earmark-fill') ?>"></i>
                    </div>
                    <div class="doc-info">
                        <h6><?= htmlspecialchars($leccionActiva['titulo']) ?></h6>
                        <p><?= $ext ? strtoupper($ext) . ' · ' : '' ?>Documento adjunto a esta lección</p>
                    </div>
                    <?php if (!empty($urlDoc)): ?>
                    <a href="<?= htmlspecialchars($urlDoc) ?>" class="btn-download" target="_blank" download>
                        <i class="bi bi-download"></i> Descargar
                    </a>
                    <?php else: ?>
                    <button class="btn-download" disabled style="opacity:.4;">
                        <i class="bi bi-download"></i> Descargar
                    </button>
                    <?php endif; ?>
                </div>

                <?php elseif ($leccionActiva['tipo'] === 'texto'): ?>
                <div class="text-content">
                    <?= !empty($leccionActiva['descripcion']) ? $leccionActiva['descripcion'] : '<p>Contenido próximamente.</p>' ?>
                </div>
                <?php endif; ?>

                <!-- Descripción / instrucciones -->
                <?php if (!empty($leccionActiva['descripcion']) && $leccionActiva['tipo'] !== 'texto'): ?>
                <div class="desc-block">
                    <div class="desc-block-title"><i class="bi bi-journal-text me-1"></i> Descripción e instrucciones
                    </div>
                    <div class="desc-block-body"><?= $leccionActiva['descripcion'] ?></div>
                </div>
                <?php endif; ?>

                <?php endif; // fin leccionActiva ?>

            </div><!-- /content-area -->

            <!-- ══ NAVEGACIÓN INFERIOR ════════════════════════════ -->
            <div class="nav-bottom">
                <?php if ($leccionAnterior): ?>
                <a href="tomaCurso.php?id=<?= $cursoId ?>&leccion=<?= $leccionAnterior['id'] ?>" class="btn-nav">
                    <i class="bi bi-chevron-left"></i> Anterior
                </a>
                <?php else: ?>
                <span class="btn-nav" style="opacity:.3;cursor:default;">
                    <i class="bi bi-chevron-left"></i> Anterior
                </span>
                <?php endif; ?>

                <div class="nav-center">
                    <strong><?= htmlspecialchars($leccionActiva['titulo'] ?? 'Sin lección') ?></strong><br>
                    <?= $leccionIndex + 1 ?> de <?= $totalLecciones ?> lecciones
                </div>

                <?php if ($leccionSiguiente): ?>
                <a href="tomaCurso.php?id=<?= $cursoId ?>&leccion=<?= $leccionSiguiente['id'] ?>"
                    class="btn-nav primary">
                    Siguiente <i class="bi bi-chevron-right"></i>
                </a>
                <?php else: ?>
                <!-- Última lección: mostrar botón completar (habilitado solo si todas están vistas) -->
                <button type="button" id="btnCompletarCurso"
                    class="btn-nav primary <?= $todasCompletas ? 'habilitado' : '' ?>">
                    <i class="bi bi-trophy-fill"></i>
                    <?= $todasCompletas ? 'Completar curso' : 'Completa todas las lecciones' ?>
                </button>
                <?php endif; ?>
            </div>

        </div><!-- /main-panel -->
    </div><!-- /player-layout -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const BASE_URL = '<?= BASE_URL ?>';
    const CURSO_ID = <?= (int)$cursoId ?>;
    const LECCION_ID = <?= (int)($leccionActiva['id'] ?? 0) ?>;
    const TOTAL_LEC = <?= $totalLecciones ?>;

    // IDs de lecciones ya vistas (desde BD)
    let vistasSet = new Set(<?= json_encode($leccionesVistas) ?>);

    // ── Toggle sidebar ────────────────────────────────────────
    document.getElementById('btnToggle').addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });

    // ── Acordeón módulos ──────────────────────────────────────
    function toggleMod(header) {
        const lista = header.nextElementSibling;
        lista.classList.toggle('open');
        header.classList.toggle('open');
    }

    // ── Ejecutar código ───────────────────────────────────────
    function ejecutarCodigo() {
        const code = document.getElementById('codeBody')?.innerText ?? '';
        const output = document.getElementById('codeOutput');
        output.style.color = 'rgba(142,202,230,.8)';
        try {
            const lang = <?= json_encode(strtolower($leccionActiva['lenguaje'] ?? '')) ?>;
            if (lang === 'javascript' || lang === 'js') {
                let log = [];
                const fn = new Function('console', code);
                fn({
                    log: (...a) => log.push(a.join(' '))
                });
                output.textContent = log.length ? log.join('\n') : '(sin salida)';
            } else {
                output.style.color = 'rgba(255,183,3,.7)';
                output.textContent = '⚡ Ejecución disponible próximamente para ' + (lang || 'este lenguaje') + '.';
            }
        } catch (e) {
            output.style.color = '#f87171';
            output.textContent = '❌ Error: ' + e.message;
        }
    }

    // ── Actualizar UI de progreso ─────────────────────────────
    function actualizarProgreso(vistas, total) {
        const pct = total > 0 ? Math.round(vistas / total * 100) : 0;

        // Barras
        document.getElementById('topbar-pbar').style.width = pct + '%';
        document.getElementById('sb-pbar').style.width = pct + '%';
        document.getElementById('topbar-pct').textContent = pct + '%';
        document.getElementById('sb-vistas').textContent = vistas;

        // Marcar lección actual como vista en sidebar
        const itemActual = document.querySelector(`.lec-item[data-leccion-id="${LECCION_ID}"]`);
        if (itemActual) itemActual.classList.add('vista');

        // Habilitar botón completar si todas están vistas y es la última lección
        const btnComp = document.getElementById('btnCompletarCurso');
        if (btnComp && vistas >= total && total > 0) {
            btnComp.classList.add('habilitado');
            btnComp.innerHTML = '<i class="bi bi-trophy-fill"></i> Completar curso';
        }
    }

    // ── Marcar lección en BD al cargar ───────────────────────
    (async function marcarLeccion() {
        if (!LECCION_ID) return;

        // localStorage como respaldo visual inmediato
        try {
            localStorage.setItem('progreso_curso_' + CURSO_ID, JSON.stringify({
                leccionId: LECCION_ID,
                leccionIndex: <?= $leccionIndex ?>,
                total: TOTAL_LEC
            }));
        } catch (_) {}

        try {
            const fd = new FormData();
            fd.append('curso_id', CURSO_ID);
            fd.append('leccion_id', LECCION_ID);

            const res = await fetch(`${BASE_URL}/administrador/api/estudiantes/marcar_leccion.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const data = await res.json();

            if (data.ok) {
                vistasSet.add(LECCION_ID);
                actualizarProgreso(data.vistas, data.total);
            } else {
                console.warn('marcar_leccion:', data.msg);
            }
        } catch (e) {
            console.warn('No se pudo sincronizar progreso:', e);
        }
    })();

    // ── Completar curso ───────────────────────────────────────
    (function() {
        const btn = document.getElementById('btnCompletarCurso');
        if (!btn) return;

        btn.addEventListener('click', async function() {
            if (!btn.classList.contains('habilitado')) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';

            try {
                const fd = new FormData();
                fd.append('curso_id', CURSO_ID);

                const res = await fetch(
                    `${BASE_URL}/administrador/api/estudiantes/completar_curso.php`, {
                        method: 'POST',
                        body: fd,
                        credentials: 'include'
                    });
                const data = await res.json();

                if (data.ok) {
                    btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> ¡Completado!';
                    btn.style.setProperty('background', '#1a8a5a', 'important');
                    btn.style.setProperty('color', '#fff', 'important');

                    // Limpiar progreso del localStorage
                    try {
                        localStorage.removeItem('progreso_curso_' + CURSO_ID);
                    } catch (_) {}

                    setTimeout(() => {
                        location.href = 'index.php';
                    }, 1500);
                } else {
                    btn.disabled = false;
                    btn.classList.add('habilitado');
                    btn.innerHTML = '<i class="bi bi-trophy-fill"></i> Completar curso';
                    alert('Error: ' + data.msg);
                }
            } catch (e) {
                btn.disabled = false;
                btn.classList.add('habilitado');
                btn.innerHTML = '<i class="bi bi-trophy-fill"></i> Completar curso';
                alert('Error de conexión. Revisa la consola (F12).');
            }
        });
    })();

    // ── Keyboard shortcuts ────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.target.getAttribute('contenteditable') === 'true') return;
        <?php if ($leccionAnterior): ?>
        if (e.key === 'ArrowLeft')
            location.href = 'tomaCurso.php?id=<?= $cursoId ?>&leccion=<?= $leccionAnterior['id'] ?>';
        <?php endif; ?>
        <?php if ($leccionSiguiente): ?>
        if (e.key === 'ArrowRight')
            location.href = 'tomaCurso.php?id=<?= $cursoId ?>&leccion=<?= $leccionSiguiente['id'] ?>';
        <?php endif; ?>
    });
    </script>
</body>

</html>