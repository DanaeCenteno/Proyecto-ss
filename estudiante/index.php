<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();

// ── Datos del usuario logueado ────────────────────────────
$usuarioLogueado = estaAutenticado();
$uid             = $usuarioLogueado ? usuarioId()     : 0;
$nombreUsuario   = $usuarioLogueado ? usuarioNombre() : '';
$rolSesion       = $usuarioLogueado ? usuarioRol()    : '';
$iniciales       = $usuarioLogueado ? inicialesAvatar($nombreUsuario) : '';
$avatarUsuario   = null;

if ($usuarioLogueado) {
    $stmtAv = $conexion->prepare("SELECT avatar FROM usuarios WHERE id = ?");
    $stmtAv->bind_param("i", $uid);
    $stmtAv->execute();
    $resAv = $stmtAv->get_result()->fetch_assoc();
    $stmtAv->close();
    if ($resAv && !empty($resAv['avatar'])) {
        $rutaAbs = __DIR__ . '/../' . ltrim($resAv['avatar'], '/');
        if (file_exists($rutaAbs)) $avatarUsuario = $resAv['avatar'];
    }
}

// ── Cursos publicados ─────────────────────────────────────
$cursos = [];
$resultado = $conexion->query("
    SELECT
        c.id, c.titulo, c.descripcion, c.categoria, c.imagen, c.duracion_total,
        COUNT(DISTINCT m.id) AS total_modulos,
        COUNT(DISTINCT l.id) AS total_lecciones,
        u.nombre             AS profesor
    FROM cursos c
    LEFT JOIN modulos   m ON m.curso_id  = c.id
    LEFT JOIN lecciones l ON l.modulo_id = m.id
    JOIN  usuarios u ON u.id = c.profesor_id
    WHERE c.estado = 'publicado'
    GROUP BY c.id, u.nombre
    ORDER BY c.created_at DESC
");
if ($resultado) $cursos = $resultado->fetch_all(MYSQLI_ASSOC);

$totalCursos = count($cursos);

// ── ¿El estudiante ya está inscrito en cada curso? ─────────
$inscritos = [];
if ($usuarioLogueado) {
    $stmtI = $conexion->prepare("SELECT curso_id FROM inscripciones WHERE usuario_id = ?");
    $stmtI->bind_param("i", $uid);
    $stmtI->execute();
    $resI = $stmtI->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtI->close();
    foreach ($resI as $row) $inscritos[] = $row['curso_id'];
}


?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal | EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/sindex.css">
</head>

<body>

    <div class="rounded-5 rounded-top-0" id="principal">

        <!-- NAVBAR -->
        <header class="d-flex flex-wrap justify-content-between align-items-center py-3 px-4" id="prin">
            <a href="index.php" class="d-flex align-items-center text-decoration-none">
                <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="50">
            </a>
            <ul class="nav nav-pills align-items-center gap-1 mb-0">
                <li class="nav-item">
                    <input type="search" id="courseSearch" placeholder="Buscar cursos..."
                        oninput="filtrarCursos(this.value)">
                </li>
                <li class="nav-item"><a href="index.php" class="nav-link active">Inicio</a></li>
                <li class="nav-item"><a href="#cursos" class="nav-link">Cursos</a></li>
                <li class="nav-item"><a href="foroEs.php" class="nav-link">Foro</a></li>

                <?php if ($usuarioLogueado): ?>
                <li class="nav-item">
                    <a href="perfil.php" class="nav-link px-2">
                        <?php if ($avatarUsuario): ?>
                        <img src="<?= htmlspecialchars($avatarUsuario) ?>" alt="<?= htmlspecialchars($nombreUsuario) ?>"
                            class="user-avatar-img">
                        <?php else: ?>
                        <div class="user-avatar"><?= $iniciales ?></div>
                        <?php endif; ?>
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a href="../login.php" class="nav-link fw-semibold"
                        style="background:#219EBC;color:#fff;border-radius:10px;padding:8px 18px;">
                        Ingresar
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </header>


        <!-- HERO -->
        <div class="container col-xxl-8 px-4 py-5">
            <div class="row flex-lg-row-reverse align-items-center g-5 py-5">
                <div class="col-10 col-sm-8 col-lg-6">
                    <img src="../img/principal2.svg" class="d-block mx-lg-auto img-fluid" alt="EduTecnia" width="700"
                        height="500" loading="lazy">
                </div>
                <div class="col-lg-6">
                    <span style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.8);
                      border-radius:20px;padding:6px 16px;font-size:12px;font-weight:600;
                      display:inline-block;margin-bottom:18px;border:1px solid rgba(255,255,255,.15);">
                        <?= $totalCursos ?> curso<?= $totalCursos !== 1 ? 's' : '' ?>
                        disponible<?= $totalCursos !== 1 ? 's' : '' ?>
                    </span>
                    <h1 class="display-5 fw-bold lh-1 mb-3">
                        Aprende a programar<br>con proyectos reales.
                    </h1>
                    <p class="lead">
                        Cursos prácticos, editor de código en el navegador y una comunidad
                        que te impulsa desde cero hasta donde quieras llegar.
                    </p>
                    <div class="d-flex gap-3 flex-wrap mt-4">
                        <?php if ($usuarioLogueado): ?>
                        <a href="perfil.php" class="btn btn-light btn-lg px-4 fw-bold">
                            <i class="bi bi-grid-1x2-fill me-1"></i> Mis cursos
                        </a>
                        <?php else: ?>
                        <a href="../login.php" class="btn btn-light btn-lg px-4 fw-bold">
                            <i class="bi bi-rocket-takeoff-fill me-1"></i> Comenzar ahora
                        </a>
                        <?php endif; ?>
                        <a href="#cursos" class="btn btn-lg px-4 fw-semibold"
                            style="border:1.5px solid rgba(255,255,255,.35);color:#fff;background:transparent;">
                            Ver cursos <i class="bi bi-arrow-down ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CURSOS -->
    <main class="container py-5" id="cursos">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-0">Cursos disponibles</h2>
                <p class="text-muted mb-0" style="font-size:14px;">Aprende con contenido actualizado y profesores
                    expertos</p>
            </div>
            <span id="courseCount"><?= $totalCursos ?> curso<?= $totalCursos !== 1 ? 's' : '' ?></span>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="courseGrid">

            <?php if (empty($cursos)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="bi bi-collection-play text-secondary" style="font-size:3rem;"></i>
                    <h5 class="fw-semibold text-secondary mt-3">Aún no hay cursos publicados</h5>
                    <p style="font-size:14px;">Pronto encontrarás contenido increíble aquí.</p>
                </div>
            </div>

            <?php else: ?>
                        <?php foreach ($cursos as $i => $c):
                $catKey   = preg_replace('/\s+/', '', $c['categoria'] ?? '');
                $catLabel = $c['categoria'] ?? 'General';
                $yaInscrito = in_array($c['id'], $inscritos);

                // Imagen: tomamos solo el nombre del archivo y armamos la ruta correcta
                $imgSrc = '';
                if (!empty($c['imagen'])) {
                    $fileName = basename($c['imagen']); // funciona aunque la BD guarde ruta o solo el nombre
                    $rutaAbs  = __DIR__ . '/../profesor/uploads/cursos/' . $fileName;
                    if (file_exists($rutaAbs)) {
                        // Ruta WEB relativa desde /estudiante/ hacia /profesor/uploads/cursos/
                        $imgSrc = '../profesor/uploads/cursos/' . rawurlencode($fileName);
                    }
                }
            ?>
            <div class="col curso-col" style="animation-delay:<?= $i * 0.06 ?>s"
                data-titulo="<?= htmlspecialchars(strtolower($c['titulo'])) ?>"
                data-cat="<?= htmlspecialchars(strtolower($c['categoria'] ?? '')) ?>">

                <div class="curso-card shadow-sm">

                    <?php if ($imgSrc): ?>
                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="curso-img"
                        alt="<?= htmlspecialchars($c['titulo']) ?>">
                    <?php else: ?>
                    <div class="curso-img-placeholder"></div>
                    <?php endif; ?>

                    <div class="curso-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="cat-badge cat-default">
                                <?= htmlspecialchars($catLabel) ?>
                            </span>
                            <small class="text-muted d-flex align-items-center gap-1">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($c['profesor']) ?>
                            </small>
                        </div>

                        <div class="curso-title"><?= htmlspecialchars($c['titulo']) ?></div>
                        <div class="curso-desc"><?= htmlspecialchars($c['descripcion'] ?? '') ?></div>

                        <div class="curso-meta">
                            <div class="curso-stats">
                                <span class="curso-stat">
                                    <i class="bi bi-collection"></i>
                                    <?= $c['total_modulos'] ?> módulo<?= $c['total_modulos'] != 1 ? 's' : '' ?>
                                </span>
                                <span class="curso-stat">
                                    <i class="bi bi-play-circle"></i>
                                    <?= $c['total_lecciones'] ?> lección<?= $c['total_lecciones'] != 1 ? 'es' : '' ?>
                                </span>
                            </div>
                            <?php if ($c['duracion_total'] > 0): ?>
                            <span class="curso-stat">
                                <i class="bi bi-clock"></i> <?= $c['duracion_total'] ?> min
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$usuarioLogueado): ?>
                    <a href="../login.php" class="btn-ver">
                        Iniciar sesión para inscribirse <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                    <?php elseif ($yaInscrito): ?>
                    <a href="verCurso.php?id=<?= $c['id'] ?>" class="btn-ver">
                        <i class="bi bi-play-circle-fill me-1"></i> Continuar curso
                    </a>
                    <?php else: ?>
                    <button class="btn-ver" onclick="inscribirse(<?= $c['id'] ?>, this)">
                        <i class="bi bi-plus-circle me-1"></i> Inscribirme
                    </button>
                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

    <!-- FOOTER -->
    <footer>
        <div id="div-footer">
            <span>© 2026 Universidad Autónoma Metropolitana, Cuajimalpa</span>
            <div style="display:flex;gap:24px;">
                <a href="index.php" style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Inicio</a>
                <a href="index.php#cursos"
                    style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Cursos</a>
                <a href="foroEs.php" style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Foro</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const BASE_URL = '<?= BASE_URL ?>';

    // ── Inscribirse a un curso ────────────────────────────────
    function inscribirse(cursoId, btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Inscribiendo...';

        const fd = new FormData();
        fd.append('curso_id', cursoId);

        fetch(`${BASE_URL}/administrador/api/estudiantes/inscribir.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> ¡Inscrito!';
                    btn.style.background = '#1a8a5a';
                    setTimeout(() => {
                        window.location.href = `verCurso.php?id=${cursoId}`;
                    }, 800);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Inscribirme';
                    alert(d.msg || 'Error al inscribirse.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Inscribirme';
                alert('Error de conexión.');
            });
    }

    // ── Filtro de búsqueda en tiempo real ─────────────────────
    function filtrarCursos(q) {
        q = q.toLowerCase().trim();
        let visible = 0;
        document.querySelectorAll('#courseGrid .curso-col').forEach(col => {
            const titulo = col.dataset.titulo || '';
            const cat = col.dataset.cat || '';
            const show = !q || titulo.includes(q) || cat.includes(q);
            col.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        const total = <?= $totalCursos ?>;
        document.getElementById('courseCount').textContent =
            (q ? visible : total) + ' curso' + ((q ? visible : total) !== 1 ? 's' : '');
    }

    document.getElementById('courseSearch')?.addEventListener('input', function() {
        filtrarCursos(this.value);
        if (this.value.trim()) {
            document.getElementById('cursos').scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
    </script>
</body>

</html>