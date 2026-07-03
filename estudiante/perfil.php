<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();
requiereLogin(); // cualquier usuario logueado

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$iniciales     = inicialesAvatar($nombreUsuario);

// ── Datos del usuario ─────────────────────────────────────
$stmtU = $conexion->prepare("SELECT nombre, correo, avatar FROM usuarios WHERE id = ?");
$stmtU->bind_param("i", $uid);
$stmtU->execute();
$usuario = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

// ── CORRECCIÓN DE LA RUTA DEL AVATAR ────────────────────
$avatarRaw     = $usuario['avatar'] ?? '';
$avatarUsuario = null;

if (!empty($avatarRaw)) {
    if (str_starts_with($avatarRaw, 'http')) {
        $avatarUsuario = $avatarRaw;
    } else {
        // Limpiamos las barras iniciales
        $rutaLimpia = ltrim($avatarRaw, '/');
        
        // Verificamos de forma segura subiendo un nivel con __DIR__
        if (file_exists(__DIR__ . '/../' . $rutaLimpia)) {
            // Le indicamos al navegador que suba un nivel para encontrar la carpeta uploads/
            $avatarUsuario = '../' . $rutaLimpia;
        }
    }
}

// ── Cursos inscritos ──────────────────────────────────────
$stmtCursos = $conexion->prepare("
    SELECT c.id, c.titulo, c.descripcion, c.imagen, c.categoria,
           u.nombre AS profesor,
           COUNT(DISTINCT l.id) AS total_lecciones,
           i.fecha_inscripcion AS inscrito_en,
           i.progreso   AS progreso,
           i.completado AS completado,
           (SELECT COUNT(*) FROM progreso_lecciones pl
             WHERE pl.usuario_id = i.usuario_id
               AND pl.curso_id  = c.id) AS lecciones_vistas
    FROM inscripciones i
    JOIN cursos c   ON c.id = i.curso_id
    JOIN usuarios u ON u.id = c.profesor_id
    LEFT JOIN modulos m   ON m.curso_id = c.id
    LEFT JOIN lecciones l ON l.modulo_id = m.id
    WHERE i.usuario_id = ?
    GROUP BY c.id, u.nombre, i.fecha_inscripcion, i.progreso, i.completado
    ORDER BY i.fecha_inscripcion DESC
");
$stmtCursos->bind_param("i", $uid);
$stmtCursos->execute();
$misCursos = $stmtCursos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtCursos->close();

// ── Preguntas del foro ────────────────────────────────────
$stmtQ = $conexion->prepare("
    SELECT id, titulo, lenguajes, estado, created_at
    FROM foro_preguntas
    WHERE usuario_id = ?
    ORDER BY created_at DESC
");
$stmtQ->bind_param("i", $uid);
$stmtQ->execute();
$misPreguntas = $stmtQ->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtQ->close();

// ── Respuestas del foro ───────────────────────────────────
$stmtR = $conexion->prepare("
    SELECT fr.id, fr.contenido, fr.created_at, fr.votos,
           fp.titulo AS pregunta_titulo, fp.id AS pregunta_id
    FROM foro_respuestas fr
    JOIN foro_preguntas fp ON fp.id = fr.pregunta_id
    WHERE fr.usuario_id = ?
    ORDER BY fr.created_at DESC
");
$stmtR->bind_param("i", $uid);
$stmtR->execute();
$misRespuestas = $stmtR->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtR->close();

function tiempoRelativo(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 60)      return 'hace un momento';
    if ($diff < 3600)    return 'hace ' . floor($diff/60) . ' min';
    if ($diff < 86400)   return 'hace ' . floor($diff/3600) . 'h';
    if ($diff < 2592000) return 'hace ' . floor($diff/86400) . ' días';
    return date('d M Y', strtotime($fecha));
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/sEsperfil.css">
</head>

<body>

    <!-- ══ SIDEBAR ══════════════════════════════════════════════ -->
    <aside class="sidebar">
        <div class="sb-logo">
            <a href="index.php" class="d-flex align-items-center text-decoration-none">
                <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="50">
            </a>
        </div>

        <nav class="sb-nav">
            <div class="sb-section">Principal</div>
            <a href="index.php" class="sb-link"><i class="bi bi-house-door-fill"></i> Inicio</a>
            <a href="index.php#cursos" class="sb-link"><i class="bi bi-collection-play-fill"></i> Ver Cursos</a>
            <a href="foroEs.php" class="sb-link"><i class="bi bi-chat-dots-fill"></i> Foro</a>

            <div class="sb-section">Mi cuenta</div>
            <a href="perfil.php" class="sb-link active"><i class="bi bi-person-circle"></i> Mi perfil</a>
            <a href="perfil.php" onclick="switchTab('cursos');return false;" class="sb-link">
                <i class="bi bi-journal-bookmark-fill"></i> Mis cursos
                <span
                    style="margin-left:auto;background:var(--azul);color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;">
                    <?= count($misCursos) ?>
                </span>
            </a>

            <a href="perfil.php" onclick="switchTab('preguntas');return false;" class="sb-link">
                <i class="bi bi-journal-bookmark-fill"></i> Mis preguntas
                <span
                    style="margin-left:auto;background:var(--azul);color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;">
                    <?= count($misPreguntas) ?>
                </span>
            </a>

            <a href="perfil.php" onclick="switchTab('respuestas');return false;" class="sb-link">
                <i class="bi bi-journal-bookmark-fill"></i> Mis respuestas
                <span
                    style="margin-left:auto;background:var(--azul);color:#fff;border-radius:10px;padding:1px 7px;font-size:10px;">
                    <?= count($misRespuestas) ?>
                </span>
            </a>

            <div class="sb-section">Sistema</div>
            <a href="<?= BASE_URL ?>/logout.php" class="sb-link"><i class="bi bi-box-arrow-left"></i> Cerrar sesión</a>
        </nav>

        <div class="sb-footer">
            <div class="sf-avatar">
                <?php if ($avatarUsuario): ?>
                <img src="<?= htmlspecialchars($avatarUsuario) ?>" alt="">
                <?php else: ?>
                <?= $iniciales ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="sf-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                <div class="sf-role">Estudiante</div>
            </div>
        </div>
    </aside>

    <!-- ══ MAIN ══════════════════════════════════════════════════ -->
    <div class="main-wrap">

        <!-- HERO -->
        <div class="perfil-hero">
            <div class="hero-avatar-wrap">
                <?php if ($avatarUsuario): ?>
                <div class="hero-avatar"><img src="<?= htmlspecialchars($avatarUsuario) ?>" alt=""></div>
                <?php else: ?>
                <div class="hero-avatar"><?= $iniciales ?></div>
                <?php endif; ?>
                <div class="avatar-cam-btn" onclick="document.getElementById('avatar-file-input').click()"
                    title="Cambiar foto">
                    <i class="fa-solid fa-camera"></i>
                </div>
                <input type="file" id="avatar-file-input" accept="image/*" onchange="subirAvatar(this)">
            </div>
            <div class="hero-info">
                <h2><?= htmlspecialchars($usuario['nombre']) ?></h2>
                <div class="hero-badge"><i class="bi bi-mortarboard-fill"></i> Estudiante</div>
                <div class="hero-stats">
                    <div class="hs-item">
                        <div class="hs-num"><?= count($misCursos) ?></div>
                        <div class="hs-lbl">Cursos</div>
                    </div>
                    <div class="hs-item">
                        <div class="hs-num"><?= count($misPreguntas) ?></div>
                        <div class="hs-lbl">Preguntas</div>
                    </div>
                    <div class="hs-item">
                        <div class="hs-num"><?= count($misRespuestas) ?></div>
                        <div class="hs-lbl">Respuestas</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="perfil-tabs-wrap">
            <div class="perfil-tabs">
                <div class="ptab active" onclick="switchTab('info')" id="tab-info">
                    <i class="bi bi-person-fill"></i> Información
                </div>
                <div class="ptab" onclick="switchTab('cursos')" id="tab-cursos">
                    <i class="bi bi-journal-bookmark-fill"></i> Mis cursos
                    <span class="tc"><?= count($misCursos) ?></span>
                </div>
                <div class="ptab" onclick="switchTab('preguntas')" id="tab-preguntas">
                    <i class="bi bi-question-circle-fill"></i> Preguntas
                    <span class="tc"><?= count($misPreguntas) ?></span>
                </div>
                <div class="ptab" onclick="switchTab('respuestas')" id="tab-respuestas">
                    <i class="bi bi-reply-fill"></i> Respuestas
                    <span class="tc"><?= count($misRespuestas) ?></span>
                </div>
            </div>
        </div>

        <!-- BODY -->
        <div class="perfil-body">

            <!-- ══ TAB: INFORMACIÓN ══ -->
            <div class="tab-pane active" id="pane-info">

                <div class="p-card">
                    <div class="p-card-title"><i class="bi bi-person-vcard-fill"></i> Datos de cuenta</div>
                    <div class="mb-3">
                        <label class="form-label">Nombre de usuario</label>
                        <input type="text" class="form-control" id="inp-nombre"
                            value="<?= htmlspecialchars($usuario['nombre']) ?>" maxlength="80">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control"
                            value="<?= htmlspecialchars($usuario['correo'] ?? '') ?>" disabled>
                        <small style="font-size:11px;color:var(--muted);">El correo no puede modificarse.</small>
                    </div>
                    <button class="btn-save" onclick="guardarNombre()">
                        <i class="bi bi-floppy-fill me-1"></i> Guardar nombre
                    </button>
                </div>

                <div class="p-card">
                    <div class="p-card-title"><i class="bi bi-lock-fill"></i> Cambiar contraseña</div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña actual</label>
                        <div class="password-wrap">
                            <input type="password" class="form-control" id="inp-pass-actual" placeholder="••••••••">
                            <i class="bi bi-eye eye-icon" onclick="togglePass('inp-pass-actual',this)"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña</label>
                        <div class="password-wrap">
                            <input type="password" class="form-control" id="inp-pass-nueva"
                                placeholder="Mínimo 8 caracteres">
                            <i class="bi bi-eye eye-icon" onclick="togglePass('inp-pass-nueva',this)"></i>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirmar nueva contraseña</label>
                        <div class="password-wrap">
                            <input type="password" class="form-control" id="inp-pass-confirm"
                                placeholder="Repite la nueva contraseña">
                            <i class="bi bi-eye eye-icon" onclick="togglePass('inp-pass-confirm',this)"></i>
                        </div>
                        <small id="err-pass" style="color:#e74c3c;font-size:11px;display:none;">Las contraseñas no
                            coinciden.</small>
                    </div>
                    <button class="btn-save" onclick="cambiarPassword()">
                        <i class="bi bi-key-fill me-1"></i> Actualizar contraseña
                    </button>
                </div>
            </div>

            <!-- ══ TAB: MIS CURSOS ══ -->
            <div class="tab-pane" id="pane-cursos">
                <?php if (empty($misCursos)): ?>
                <div class="empty-st">
                    <i class="bi bi-collection-play"></i>
                    <p>Aún no estás inscrito en ningún curso.</p>
                    <a href="index.php#cursos" class="btn-save mt-3" style="display:inline-block;text-decoration:none;">
                        <i class="bi bi-search me-1"></i> Explorar cursos
                    </a>
                </div>
                <?php else: ?>
                <div class="cursos-grid">
                    <?php foreach ($misCursos as $c):
                    $totalLec = (int) $c['total_lecciones'];
                    $vistas   = (int) $c['lecciones_vistas'];
                    // Usa el conteo real de lecciones; si no hay lecciones, cae al campo progreso
                    $pct = $totalLec > 0
                        ? (int) round($vistas / $totalLec * 100)
                        : (int) $c['progreso'];

                    // Imagen: tomamos solo el nombre del archivo y armamos la ruta correcta
                    $imgSrc = '';
                    if (!empty($c['imagen'])) {
                        $fileName = basename($c['imagen']);
                        $rutaAbs  = __DIR__ . '/../profesor/uploads/cursos/' . $fileName;
                        if (file_exists($rutaAbs)) {
                            $imgSrc = '../profesor/uploads/cursos/' . rawurlencode($fileName);
                        }
                    }
                    ?>
                    <div class="curso-card-p">
                        <div class="cc-thumb">
                            <?php if ($imgSrc): ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>"
                                alt="Portada de <?= htmlspecialchars($c['titulo']) ?>">
                            <?php endif; ?>
                            <span class="cc-cat"><?= htmlspecialchars($c['categoria'] ?? 'General') ?></span>
                        </div>
                        <div class="cc-body">
                            <div class="cc-titulo"><?= htmlspecialchars($c['titulo']) ?></div>
                            <div class="cc-prof"><i
                                    class="bi bi-person-circle me-1"></i><?= htmlspecialchars($c['profesor']) ?></div>
                            <div class="cc-progress" id="prog-<?= $c['id'] ?>">
                                <div class="cp-bar-wrap">
                                    <div class="cp-bar-fill" style="width:<?= $pct ?>%"></div>
                                </div>
                                <div class="cp-label">
                                    <?= $vistas ?> / <?= $totalLec ?> lecciones completadas (<?= $pct ?>%)
                                </div>
                            </div>
                            <div class="cc-actions">
                                <a href="tomaCurso.php?id=<?= $c['id'] ?>" class="btn-cc btn-cc-primary">
                                    <i class="bi bi-play-circle-fill"></i> Continuar
                                </a>
                                <a href="verCurso.php?id=<?= $c['id'] ?>" class="btn-cc btn-cc-outline">
                                    <i class="bi bi-info-circle"></i> Detalles
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
                <?php endif; ?>
            </div>

            <!-- ══ TAB: PREGUNTAS ══ -->
            <div class="tab-pane" id="pane-preguntas">
                <?php if (empty($misPreguntas)): ?>
                <div class="empty-st">
                    <i class="bi bi-question-circle"></i>
                    <p>Aún no has publicado ninguna pregunta.</p>
                    <a href="askEs.php" class="btn-save mt-3" style="display:inline-block;text-decoration:none;">
                        <i class="bi bi-plus me-1"></i> Hacer una pregunta
                    </a>
                </div>
                <?php else: ?>
                <?php foreach ($misPreguntas as $q): ?>
                <div class="foro-item" id="qi-<?= $q['id'] ?>">
                    <div class="fi-titulo"><?= htmlspecialchars($q['titulo']) ?></div>
                    <div class="fi-meta">
                        <span class="estado-badge estado-<?= $q['estado'] ?>">
                            <?php $ico = ['abierta'=>'bi-lock-open','cerrada'=>'bi-lock','resuelta'=>'bi-check-circle-fill']; ?>
                            <i class="bi <?= $ico[$q['estado']] ?? 'bi-circle' ?>"></i>
                            <?= ucfirst($q['estado']) ?>
                        </span>
                        <span><i class="bi bi-clock me-1"></i><?= tiempoRelativo($q['created_at']) ?></span>
                        <?php if ($q['lenguajes']): ?>
                        <?php foreach (array_slice(explode(',', $q['lenguajes']), 0, 3) as $l): ?>
                        <span class="lang-chip-sm"><?= htmlspecialchars(trim($l)) ?></span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="fi-actions">
                        <button class="btn-fi btn-fi-blue"
                            onclick="abrirEditarPregunta(<?= $q['id'] ?>, <?= htmlspecialchars(json_encode($q['titulo'])) ?>, <?= htmlspecialchars(json_encode('')) ?>, <?= htmlspecialchars(json_encode($q['lenguajes'] ?? '')) ?>)">
                            <i class="bi bi-pencil-fill"></i> Editar
                        </button>
                        <button class="btn-fi btn-fi-red" onclick="eliminarPregunta(<?= $q['id'] ?>)">
                            <i class="bi bi-trash-fill"></i> Eliminar
                        </button>
                        <a href="respuestaEs.php?id=<?= $q['id'] ?>" class="btn-fi btn-fi-blue"
                            style="text-decoration:none;">
                            <i class="bi bi-eye-fill"></i> Ver
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ══ TAB: RESPUESTAS ══ -->
            <div class="tab-pane" id="pane-respuestas">
                <?php if (empty($misRespuestas)): ?>
                <div class="empty-st">
                    <i class="bi bi-reply"></i>
                    <p>Aún no has respondido ninguna pregunta.</p>
                    <a href="foroEs.php" class="btn-save mt-3" style="display:inline-block;text-decoration:none;">
                        <i class="bi bi-chat-dots me-1"></i> Ir al foro
                    </a>
                </div>
                <?php else: ?>
                <?php foreach ($misRespuestas as $r): ?>
                <div class="foro-item" id="ri-<?= $r['id'] ?>">
                    <div class="ri-ref">
                        <i class="bi bi-link-45deg"></i>
                        En: <a
                            href="respuestaEs.php?id=<?= $r['pregunta_id'] ?>"><?= htmlspecialchars($r['pregunta_titulo']) ?></a>
                    </div>
                    <div class="ri-body ql-editor" style="padding:0;"><?= $r['contenido'] ?></div>
                    <div class="fi-meta" style="margin-top:10px;">
                        <span><i class="bi bi-clock me-1"></i><?= tiempoRelativo($r['created_at']) ?></span>
                        <span class="votos-chip"><i class="bi bi-chevron-up" style="color:var(--azul);"></i>
                            <?= $r['votos'] ?> votos</span>
                        <div style="margin-left:auto;display:flex;gap:7px;">
                            <button class="btn-fi btn-fi-blue"
                                onclick="abrirEditarRespuesta(<?= $r['id'] ?>, <?= htmlspecialchars(json_encode($r['contenido'])) ?>)">
                                <i class="bi bi-pencil-fill"></i> Editar
                            </button>
                            <button class="btn-fi btn-fi-red" onclick="eliminarRespuesta(<?= $r['id'] ?>)">
                                <i class="bi bi-trash-fill"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- /perfil-body -->
    </div><!-- /main-wrap -->

    <!-- ── MODAL: EDITAR PREGUNTA ── -->
    <div class="modal-overlay" id="modal-pregunta">
        <div class="modal-box">
            <div class="modal-hdr">
                <h5><i class="bi bi-pencil-fill me-2" style="color:var(--azul)"></i>Editar pregunta</h5>
                <button class="btn-close-m" onclick="cerrarModal('modal-pregunta')">&#10005;</button>
            </div>
            <input type="hidden" id="edit-pregunta-id">
            <div class="mb-3">
                <label class="form-label">Título</label>
                <input type="text" class="form-control" id="edit-titulo" maxlength="150">
            </div>
            <div class="mb-3">
                <label class="form-label">Lenguajes</label>
                <div class="lang-opts" id="edit-lang-grid">
                    <?php foreach (['html','css','javascript','php','python','sql','java','c++'] as $l): ?>
                    <button type="button" class="lang-opt-btn" data-lang="<?= $l ?>"
                        onclick="toggleLangEdit('<?= $l ?>')"><?= strtoupper($l) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="lang-chips-edit" id="edit-lang-chips"></div>
            </div>
            <div class="mb-4">
                <label class="form-label">Contenido</label>
                <div id="edit-quill-pregunta" style="height:180px;"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button class="btn-fi btn-fi-blue" onclick="cerrarModal('modal-pregunta')">Cancelar</button>
                <button class="btn-save" onclick="guardarPregunta()"><i class="bi bi-floppy-fill me-1"></i>
                    Guardar</button>
            </div>
        </div>
    </div>

    <!-- ── MODAL: EDITAR RESPUESTA ── -->
    <div class="modal-overlay" id="modal-respuesta">
        <div class="modal-box">
            <div class="modal-hdr">
                <h5><i class="bi bi-pencil-fill me-2" style="color:var(--azul)"></i>Editar respuesta</h5>
                <button class="btn-close-m" onclick="cerrarModal('modal-respuesta')">&#10005;</button>
            </div>
            <input type="hidden" id="edit-respuesta-id">
            <div class="mb-4">
                <label class="form-label">Contenido</label>
                <div id="edit-quill-respuesta" style="height:180px;"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button class="btn-fi btn-fi-blue" onclick="cerrarModal('modal-respuesta')">Cancelar</button>
                <button class="btn-save" onclick="guardarRespuesta()"><i class="bi bi-floppy-fill me-1"></i>
                    Guardar</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
    const BASE_URL = '<?= BASE_URL ?>';

    // ── TABS ──────────────────────────────────────────────────
    function switchTab(tab) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.ptab').forEach(t => t.classList.remove('active'));
        document.getElementById('pane-' + tab).classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
    }



    // ── TOGGLE PASSWORD ───────────────────────────────────────
    function togglePass(id, icon) {
        const inp = document.getElementById(id);
        inp.type = inp.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('bi-eye');
        icon.classList.toggle('bi-eye-slash');
    }

    // ── GUARDAR NOMBRE ────────────────────────────────────────
    function guardarNombre() {
        const nombre = document.getElementById('inp-nombre').value.trim();
        if (nombre.length < 3) {
            mostrarToast('El nombre debe tener al menos 3 caracteres.', 'error');
            return;
        }
        const fd = new FormData();
        fd.append('nombre', nombre);
        fetch(`${BASE_URL}/administrador/api/perfil/actualizar_nombre.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json()).then(d => mostrarToast(d.msg, d.ok ? 'success' : 'error'))
            .catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── CAMBIAR CONTRASEÑA ────────────────────────────────────
    function cambiarPassword() {
        const actual = document.getElementById('inp-pass-actual').value;
        const nueva = document.getElementById('inp-pass-nueva').value;
        const confirm = document.getElementById('inp-pass-confirm').value;
        const errEl = document.getElementById('err-pass');
        if (nueva !== confirm) {
            errEl.style.display = 'block';
            return;
        }
        errEl.style.display = 'none';
        if (nueva.length < 8) {
            mostrarToast('Mínimo 8 caracteres.', 'error');
            return;
        }
        const fd = new FormData();
        fd.append('actual', actual);
        fd.append('nueva', nueva);
        fetch(`${BASE_URL}/administrador/api/perfil/cambiar_password.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json()).then(d => {
                mostrarToast(d.msg, d.ok ? 'success' : 'error');
                if (d.ok) {
                    document.getElementById('inp-pass-actual').value = '';
                    document.getElementById('inp-pass-nueva').value = '';
                    document.getElementById('inp-pass-confirm').value = '';
                }
            }).catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── SUBIR AVATAR ──────────────────────────────────────────
    function subirAvatar(input) {
        if (!input.files[0]) return;
        const fd = new FormData();
        fd.append('avatar', input.files[0]);
        mostrarToast('Subiendo foto...', 'success');
        fetch(`${BASE_URL}/administrador/api/perfil/subir_avatar.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json()).then(d => {
                if (d.ok) {
                    mostrarToast('Foto actualizada.', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    mostrarToast(d.msg, 'error');
                }
            }).catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── MODALES ───────────────────────────────────────────────
    function cerrarModal(id) {
        document.getElementById(id).classList.remove('show');
    }
    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => {
            if (e.target === m) m.classList.remove('show');
        });
    });

    // ── QUILL ─────────────────────────────────────────────────
    let quillP = null,
        quillR = null;
    const qCfg = {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                ['code-block'],
                [{
                    list: 'ordered'
                }, {
                    list: 'bullet'
                }],
                ['link'],
                ['clean']
            ]
        }
    };

    // ── EDITAR PREGUNTA ───────────────────────────────────────
    let editLangs = [];

    function toggleLangEdit(lang) {
        const idx = editLangs.indexOf(lang);
        const btn = document.querySelector(`.lang-opt-btn[data-lang="${lang}"]`);
        if (idx > -1) {
            editLangs.splice(idx, 1);
            btn?.classList.remove('sel');
        } else {
            editLangs.push(lang);
            btn?.classList.add('sel');
        }
        renderLCE();
    }

    function renderLCE() {
        document.getElementById('edit-lang-chips').innerHTML =
            editLangs.map(l =>
                `<span class="lce">${l} <span class="rm" onclick="toggleLangEdit('${l}')">✕</span></span>`).join('');
    }

    function abrirEditarPregunta(id, titulo, contenido, lenguajes) {
        document.getElementById('edit-pregunta-id').value = id;
        document.getElementById('edit-titulo').value = titulo;
        editLangs = lenguajes ? lenguajes.split(',').map(l => l.trim()).filter(Boolean) : [];
        document.querySelectorAll('.lang-opt-btn').forEach(b => b.classList.toggle('sel', editLangs.includes(b.dataset
            .lang)));
        renderLCE();
        if (!quillP) quillP = new Quill('#edit-quill-pregunta', qCfg);
        quillP.root.innerHTML = contenido;
        document.getElementById('modal-pregunta').classList.add('show');
    }

    function guardarPregunta() {
        const id = document.getElementById('edit-pregunta-id').value;
        const titulo = document.getElementById('edit-titulo').value.trim();
        if (titulo.length < 10) {
            mostrarToast('El título debe tener al menos 10 caracteres.', 'error');
            return;
        }
        const fd = new FormData();
        fd.append('pregunta_id', id);
        fd.append('titulo', titulo);
        fd.append('contenido', quillP.root.innerHTML);
        fd.append('lenguajes', editLangs.join(','));
        fetch(`${BASE_URL}/administrador/api/foro/editar_pregunta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json()).then(d => {
                if (d.ok) {
                    cerrarModal('modal-pregunta');
                    mostrarToast('Pregunta actualizada', 'success');
                    const el = document.querySelector(`#qi-${id} .fi-titulo`);
                    if (el) el.textContent = titulo;
                } else mostrarToast(d.msg, 'error');
            }).catch(() => mostrarToast('Error de conexión', 'error'));
    }

    function eliminarPregunta(id) {
        if (!confirm('¿Eliminar esta pregunta y todas sus respuestas?')) return;
        const fd = new FormData();
        fd.append('pregunta_id', id);
        fetch(`${BASE_URL}/administrador/api/foro/eliminar_pregunta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json()).then(d => {
                if (d.ok) {
                    document.getElementById(`qi-${id}`)?.remove();
                    mostrarToast('Pregunta eliminada', 'success');
                } else mostrarToast(d.msg, 'error');
            }).catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── EDITAR / ELIMINAR RESPUESTA ───────────────────────────
    function abrirEditarRespuesta(id, contenido) {
        document.getElementById('edit-respuesta-id').value = id;
        if (!quillR) quillR = new Quill('#edit-quill-respuesta', qCfg);
        quillR.root.innerHTML = contenido;
        document.getElementById('modal-respuesta').classList.add('show');
    }

    function guardarRespuesta() {
        const id = document.getElementById('edit-respuesta-id').value;
        if (quillR.getText().trim().length < 5) {
            mostrarToast('Respuesta demasiado corta.', 'error');
            return;
        }
        const fd = new FormData();
        fd.append('respuesta_id', id);
        fd.append('contenido', quillR.root.innerHTML);
        fetch(`${BASE_URL}/administrador/api/foro/editar_respuesta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json()).then(d => {
                if (d.ok) {
                    cerrarModal('modal-respuesta');
                    mostrarToast('Respuesta actualizada', 'success');
                    const el = document.querySelector(`#ri-${id} .ri-body`);
                    if (el) el.innerHTML = quillR.root.innerHTML;
                } else mostrarToast(d.msg, 'error');
            }).catch(() => mostrarToast('Error de conexión', 'error'));
    }

    function eliminarRespuesta(id) {
        if (!confirm('¿Eliminar esta respuesta?')) return;
        const fd = new FormData();
        fd.append('respuesta_id', id);
        fetch(`${BASE_URL}/administrador/api/foro/eliminar_respuesta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json()).then(d => {
                if (d.ok) {
                    document.getElementById(`ri-${id}`)?.remove();
                    mostrarToast('Respuesta eliminada', 'success');
                } else mostrarToast(d.msg, 'error');
            }).catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── TOAST ─────────────────────────────────────────────────
    function mostrarToast(msg, tipo = 'success') {
        const viejo = document.getElementById('p-toast');
        if (viejo) viejo.remove();
        const c = {
            success: {
                bg: '#219EBC',
                ic: 'bi-check-circle-fill'
            },
            error: {
                bg: '#FB8500',
                ic: 'bi-exclamation-circle-fill'
            }
        };
        const {
            bg,
            ic
        } = c[tipo] ?? c.success;
        const t = document.createElement('div');
        t.id = 'p-toast';
        t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;background:${bg};color:#fff;
        padding:13px 18px;border-radius:10px;font-size:13px;font-weight:500;
        display:flex;align-items:center;gap:9px;box-shadow:0 8px 24px rgba(2,48,71,.25);
        animation:tIn .3s ease;font-family:'Poppins',sans-serif;`;
        t.innerHTML = `<i class="bi ${ic}"></i> ${msg}`;
        document.body.appendChild(t);
        if (!document.getElementById('toast-kf')) {
            const s = document.createElement('style');
            s.id = 'toast-kf';
            s.textContent = `@keyframes tIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
                         @keyframes tOut{from{opacity:1}to{opacity:0;transform:translateY(8px)}}`;
            document.head.appendChild(s);
        }
        setTimeout(() => {
            t.style.animation = 'tOut .3s ease forwards';
            setTimeout(() => t.remove(), 300);
        }, 3500);
    }
    </script>
</body>

</html>