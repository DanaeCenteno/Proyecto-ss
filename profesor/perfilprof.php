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

$avatarUsuario = ($usuario['avatar'] && file_exists($usuario['avatar'])) ? $usuario['avatar'] : null;

$stmtQ = $conexion->prepare("SELECT id, titulo, contenido, lenguajes, estado, created_at FROM foro_preguntas WHERE usuario_id = ? ORDER BY created_at DESC");
$stmtQ->bind_param("i", $uid);
$stmtQ->execute();
$misPreguntas = $stmtQ->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtQ->close();

$stmtR = $conexion->prepare("SELECT fr.id, fr.contenido, fr.created_at, fr.votos, fp.titulo AS pregunta_titulo, fp.id AS pregunta_id FROM foro_respuestas fr JOIN foro_preguntas fp ON fp.id = fr.pregunta_id WHERE fr.usuario_id = ? ORDER BY fr.created_at DESC");
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
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styleperfilProf.css">


</head>

<body>

    <!-- ══════════════════════════════════════
     SIDEBAR
══════════════════════════════════════ -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../img/logoEduTecnia.png" alt="EduTecnia">
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Principal</div>
            <a href="dashboard.php" class="nav-link-item">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <a href="dashboard.php#cursos" class="nav-link-item">
                <i class="bi bi-collection-play"></i> Mis Cursos
            </a>
            <a href="foro.php" class="nav-link-item">
                <i class="bi bi-question-circle"></i> Foro
            </a>
            <a href="#" class="nav-link-item">
                <i class="bi bi-person-square"></i> Estudiantes
            </a>

            <div class="nav-section-label">Gestión</div>
            <a href="ask.php" class="nav-link-item">
                <i class="bi bi-plus-circle"></i> Nueva Pregunta
            </a>

            <div class="nav-section-label">Sistema</div>
            <a href="perfil.php" class="nav-link-item active">
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

    <!-- ══════════════════════════════════════
     MAIN
══════════════════════════════════════ -->
    <div class="main-wrap">

        <!-- HERO -->
        <div class="perfil-hero">
            <div class="hero-inner">
                <div class="avatar-wrap">
                    <div class="avatar-circle" id="avatar-preview-wrap">
                        <?php if ($avatarUsuario): ?>
                        <img src="<?= htmlspecialchars($avatarUsuario) ?>" id="avatar-preview" alt="">
                        <?php else: ?>
                        <span id="avatar-initials"><?= $iniciales ?></span>
                        <img id="avatar-preview" src="" alt="" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <!-- Botón cámara abre el input file -->
                    <div class="avatar-edit-btn" onclick="document.getElementById('avatar-input').click()"
                        title="Cambiar foto">
                        <i class="fa-solid fa-camera"></i>
                    </div>
                    <!-- Input file oculto -->
                    <input type="file" id="avatar-input" accept="image/*" onchange="subirAvatar(this)">
                </div>
                <div class="hero-info">
                    <h2><?= htmlspecialchars($usuario['nombre']) ?></h2>
                    <div class="role-badge"><i class="fa-solid fa-chalkboard-user"></i> Profesor</div>
                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-num"><?= count($misPreguntas) ?></div>
                            <div class="stat-lbl">Preguntas</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-num"><?= count($misRespuestas) ?></div>
                            <div class="stat-lbl">Respuestas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="perfil-tabs-wrap">
            <div class="perfil-tabs">
                <div class="ptab active" onclick="switchTab('info')" id="tab-info">
                    <i class="bi bi-person-fill"></i>Información personal
                </div>
                <div class="ptab" onclick="switchTab('preguntas')" id="tab-preguntas">
                    <i class="bi bi-question-circle-fill"></i> Mis preguntas
                    <span class="tab-count"><?= count($misPreguntas) ?></span>
                </div>
                <div class="ptab" onclick="switchTab('respuestas')" id="tab-respuestas">
                    <i class="bi bi-person-raised-hand"></i> Mis respuestas
                    <span class="tab-count"><?= count($misRespuestas) ?></span>
                </div>
            </div>
        </div>

        <!-- CONTENIDO -->
        <div class="perfil-body">

            <!-- ══ TAB 1: INFO PERSONAL ══ -->
            <div class="tab-pane active" id="pane-info">

                <div class="p-card">
                    <div class="p-card-title"><i class="bi bi-person-vcard-fill"></i>Información Personal</div>
                    <div class="mb-3">
                        <label class="form-label">Nombre de usuario</label>
                        <input type="text" class="form-control" id="inp-nombre"
                            value="<?= htmlspecialchars($usuario['nombre']) ?>" maxlength="80">
                        <div class="invalid-feedback">El nombre debe tener al menos 3 caracteres.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>"
                            disabled style="background:var(--bg);cursor:not-allowed;">
                        <small style="font-size:11px;color:var(--muted);">El correo no puede modificarse.</small>
                    </div>
                    <button class="btn-guardar" onclick="guardarNombre()">
                        <i class="bi bi-floppy"></i>Guardar nombre
                    </button>
                </div>

                <div class="p-card">
                    <div class="p-card-title"><i class="fa-solid fa-lock"></i>Cambiar contraseña</div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña actual</label>
                        <div class="password-toggle">
                            <input type="password" class="form-control" id="inp-pass-actual" placeholder="••••••••">
                            <i class="fa-regular fa-eye toggle-eye" onclick="togglePass('inp-pass-actual',this)"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña</label>
                        <div class="password-toggle">
                            <input type="password" class="form-control" id="inp-pass-nueva"
                                placeholder="Mínimo 8 caracteres">
                            <i class="fa-regular fa-eye toggle-eye" onclick="togglePass('inp-pass-nueva',this)"></i>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Confirmar nueva contraseña</label>
                        <div class="password-toggle">
                            <input type="password" class="form-control" id="inp-pass-confirm"
                                placeholder="Repite la contraseña">
                            <i class="fa-regular fa-eye toggle-eye" onclick="togglePass('inp-pass-confirm',this)"></i>
                        </div>
                        <div class="invalid-feedback" id="err-pass" style="display:none;">Las contraseñas no coinciden.
                        </div>
                    </div>
                    <button class="btn-guardar" onclick="cambiarPassword()">
                        <i class="bi bi-key-fill"></i>Actualizar contraseña
                    </button>
                </div>
            </div>

            <!-- ══ TAB 2: MIS PREGUNTAS ══ -->
            <div class="tab-pane" id="pane-preguntas">
                <?php if (empty($misPreguntas)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-circle-question"></i>
                    <p>Aún no has publicado ninguna pregunta.</p>
                    <a href="ask.php" class="btn-guardar mt-3 d-inline-block text-decoration-none"
                        style="margin-top:16px;">
                        <i class="fa-solid fa-plus me-2"></i>Hacer mi primera pregunta
                    </a>
                </div>
                <?php else: ?>
                <?php foreach ($misPreguntas as $q): ?>
                <div class="pregunta-item" id="qi-<?= $q['id'] ?>">
                    <div class="pi-titulo"><?= htmlspecialchars($q['titulo']) ?></div>
                    <div class="pi-meta">
                        <?php $iconos = ['abierta'=>'fa-lock-open','cerrada'=>'fa-lock','resuelta'=>'fa-circle-check']; ?>
                        <span class="estado-badge estado-<?= $q['estado'] ?>">
                            <i class="fa-solid <?= $iconos[$q['estado']] ?? 'fa-circle' ?>"></i>
                            <?= ucfirst($q['estado']) ?>
                        </span>
                        <span><i class="fa-regular fa-clock me-1"></i><?= tiempoRelativo($q['created_at']) ?></span>
                        <?php if ($q['lenguajes']): ?>
                        <?php foreach (array_slice(explode(',', $q['lenguajes']), 0, 3) as $l): ?>
                        <span class="lang-badge"><?= htmlspecialchars(trim($l)) ?></span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="pi-actions">
                        <button class="btn-accion edit"
                            onclick="abrirEditarPregunta(<?= $q['id'] ?>, <?= htmlspecialchars(json_encode($q['titulo'])) ?>, <?= htmlspecialchars(json_encode($q['contenido'])) ?>, <?= htmlspecialchars(json_encode($q['lenguajes'] ?? '')) ?>)">
                            <i class="fa-solid fa-pen"></i> Editar
                        </button>
                        <button class="btn-accion del" onclick="eliminarPregunta(<?= $q['id'] ?>)">
                            <i class="fa-solid fa-trash"></i> Eliminar
                        </button>
                        <a href="respuesta.php?id=<?= $q['id'] ?>" class="btn-accion view">
                            <i class="fa-solid fa-eye"></i> Ver
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ══ TAB 3: MIS RESPUESTAS ══ -->
            <div class="tab-pane" id="pane-respuestas">
                <?php if (empty($misRespuestas)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-reply"></i>
                    <p>Aún no has respondido ninguna pregunta.</p>
                    <a href="foro.php" class="btn-guardar mt-3 d-inline-block text-decoration-none"
                        style="margin-top:16px;">
                        <i class="fa-solid fa-comments me-2"></i>Explorar el foro
                    </a>
                </div>
                <?php else: ?>
                <?php foreach ($misRespuestas as $r): ?>
                <div class="respuesta-item" id="ri-<?= $r['id'] ?>">
                    <div class="ri-pregunta-ref">
                        <i class="fa-solid fa-link"></i>
                        En: <a
                            href="respuesta.php?id=<?= $r['pregunta_id'] ?>"><?= htmlspecialchars($r['pregunta_titulo']) ?></a>
                    </div>
                    <div class="ri-contenido ql-editor" style="padding:0;">
                        <?= $r['contenido'] ?>
                    </div>
                    <div class="ri-meta">
                        <span><i class="fa-regular fa-clock me-1"></i><?= tiempoRelativo($r['created_at']) ?></span>
                        <span class="votos-mini"><i class="fa-solid fa-chevron-up"></i><?= $r['votos'] ?> votos</span>
                        <div style="margin-left:auto; display:flex; gap:7px;">
                            <button class="btn-accion edit"
                                onclick="abrirEditarRespuesta(<?= $r['id'] ?>, <?= htmlspecialchars(json_encode($r['contenido'])) ?>)">
                                <i class="fa-solid fa-pen"></i> Editar
                            </button>
                            <button class="btn-accion del" onclick="eliminarRespuesta(<?= $r['id'] ?>)">
                                <i class="fa-solid fa-trash"></i> Eliminar
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
            <div class="modal-header-custom">
                <h5><i class="fa-solid fa-pen me-2" style="color:var(--azul)"></i>Editar pregunta</h5>
                <button class="btn-close-modal" onclick="cerrarModal('modal-pregunta')">&#10005;</button>
            </div>
            <input type="hidden" id="edit-pregunta-id">
            <div class="mb-3">
                <label class="form-label">Título</label>
                <input type="text" class="form-control" id="edit-titulo" maxlength="150">
            </div>
            <div class="mb-3">
                <label class="form-label">Lenguajes</label>
                <div class="lang-options-grid" id="edit-lang-grid">
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
                <button class="btn-accion view" onclick="cerrarModal('modal-pregunta')">Cancelar</button>
                <button class="btn-guardar" onclick="guardarPregunta()">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar cambios
                </button>
            </div>
        </div>
    </div>

    <!-- ── MODAL: EDITAR RESPUESTA ── -->
    <div class="modal-overlay" id="modal-respuesta">
        <div class="modal-box">
            <div class="modal-header-custom">
                <h5><i class="fa-solid fa-pen me-2" style="color:var(--azul)"></i>Editar respuesta</h5>
                <button class="btn-close-modal" onclick="cerrarModal('modal-respuesta')">&#10005;</button>
            </div>
            <input type="hidden" id="edit-respuesta-id">
            <div class="mb-4">
                <label class="form-label">Contenido</label>
                <div id="edit-quill-respuesta" style="height:180px;"></div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;">
                <button class="btn-accion view" onclick="cerrarModal('modal-respuesta')">Cancelar</button>
                <button class="btn-guardar" onclick="guardarRespuesta()">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar cambios
                </button>
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
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }

    // ── SUBIR AVATAR ──────────────────────────────────────────
    function subirAvatar(input) {
        const file = input.files[0];
        if (!file) return;

        // Preview inmediato
        const reader = new FileReader();
        reader.onload = e => {
            const prev = document.getElementById('avatar-preview');
            const init = document.getElementById('avatar-initials');
            prev.src = e.target.result;
            prev.style.display = 'block';
            if (init) init.style.display = 'none';
            // Actualizar sidebar también
            const sfAv = document.querySelector('.sf-avatar');
            if (sfAv) sfAv.innerHTML =
                `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">`;
        };
        reader.readAsDataURL(file);

        // Subir al servidor
        const fd = new FormData();
        fd.append('avatar', file);

        fetch(`${BASE_URL}/administrador/api/perfil/subir_avatar.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(d => mostrarToast(d.msg, d.ok ? 'success' : 'error'))
            .catch(() => mostrarToast('Error al subir la foto', 'error'));
    }

    // ── GUARDAR NOMBRE ────────────────────────────────────────
    function guardarNombre() {
        const inp = document.getElementById('inp-nombre');
        const nombre = inp.value.trim();
        if (nombre.length < 3) {
            inp.classList.add('is-invalid');
            return;
        }
        inp.classList.remove('is-invalid');

        const fd = new FormData();
        fd.append('nombre', nombre);
        fetch(`${BASE_URL}/administrador/api/perfil/actualizar_nombre.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(d => mostrarToast(d.msg, d.ok ? 'success' : 'error'))
            .catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── CAMBIAR PASSWORD ──────────────────────────────────────
    function cambiarPassword() {
        const actual = document.getElementById('inp-pass-actual').value;
        const nueva = document.getElementById('inp-pass-nueva').value;
        const confirm = document.getElementById('inp-pass-confirm').value;
        const errEl = document.getElementById('err-pass');

        if (nueva !== confirm) {
            errEl.style.display = 'block';
            document.getElementById('inp-pass-confirm').classList.add('is-invalid');
            return;
        }
        errEl.style.display = 'none';
        document.getElementById('inp-pass-confirm').classList.remove('is-invalid');
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
            .then(r => r.json())
            .then(d => {
                mostrarToast(d.msg, d.ok ? 'success' : 'error');
                if (d.ok) {
                    ['inp-pass-actual', 'inp-pass-nueva', 'inp-pass-confirm']
                    .forEach(id => document.getElementById(id).value = '');
                }
            })
            .catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── QUILL ─────────────────────────────────────────────────
    let quillPregunta = null,
        quillRespuesta = null;
    const quillCfg = {
        theme: 'snow',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                ['blockquote', 'code-block'],
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

    // ── MODALES ───────────────────────────────────────────────
    function cerrarModal(id) {
        document.getElementById(id).classList.remove('show');
    }
    document.querySelectorAll('.modal-overlay').forEach(m =>
        m.addEventListener('click', e => {
            if (e.target === m) m.classList.remove('show');
        })
    );

    // ── EDITAR PREGUNTA ───────────────────────────────────────
    let editLangs = [];

    function toggleLangEdit(lang) {
        const idx = editLangs.indexOf(lang);
        const btn = document.querySelector(`.lang-opt-btn[data-lang="${lang}"]`);
        if (idx > -1) {
            editLangs.splice(idx, 1);
            btn?.classList.remove('selected');
        } else {
            editLangs.push(lang);
            btn?.classList.add('selected');
        }
        renderLangChips();
    }

    function renderLangChips() {
        document.getElementById('edit-lang-chips').innerHTML =
            editLangs.map(l =>
                `<span class="lang-chip-edit">${l} <span class="rm" onclick="toggleLangEdit('${l}')">✕</span></span>`)
            .join('');
    }

    function abrirEditarPregunta(id, titulo, contenido, lenguajes) {
        document.getElementById('edit-pregunta-id').value = id;
        document.getElementById('edit-titulo').value = titulo;
        editLangs = lenguajes ? lenguajes.split(',').map(l => l.trim()).filter(Boolean) : [];
        document.querySelectorAll('.lang-opt-btn').forEach(b =>
            b.classList.toggle('selected', editLangs.includes(b.dataset.lang))
        );
        renderLangChips();
        if (!quillPregunta) quillPregunta = new Quill('#edit-quill-pregunta', quillCfg);
        quillPregunta.root.innerHTML = contenido;
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
        fd.append('contenido', quillPregunta.root.innerHTML);
        fd.append('lenguajes', editLangs.join(','));
        fetch(`${BASE_URL}/administrador/api/foro/editar_pregunta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    cerrarModal('modal-pregunta');
                    mostrarToast('Pregunta actualizada', 'success');
                    const el = document.querySelector(`#qi-${id} .pi-titulo`);
                    if (el) el.textContent = titulo;
                } else mostrarToast(d.msg, 'error');
            })
            .catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── ELIMINAR PREGUNTA ─────────────────────────────────────
    function eliminarPregunta(id) {
        if (!confirm('¿Eliminar esta pregunta y todas sus respuestas? Esta acción no se puede deshacer.')) return;
        const fd = new FormData();
        fd.append('pregunta_id', id);
        fetch(`${BASE_URL}/administrador/api/foro/eliminar_pregunta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    document.getElementById(`qi-${id}`)?.remove();
                    mostrarToast('Pregunta eliminada', 'success');
                } else mostrarToast(d.msg, 'error');
            })
            .catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── EDITAR RESPUESTA ──────────────────────────────────────
    function abrirEditarRespuesta(id, contenido) {
        document.getElementById('edit-respuesta-id').value = id;
        if (!quillRespuesta) quillRespuesta = new Quill('#edit-quill-respuesta', quillCfg);
        quillRespuesta.root.innerHTML = contenido;
        document.getElementById('modal-respuesta').classList.add('show');
    }

    function guardarRespuesta() {
        const id = document.getElementById('edit-respuesta-id').value;
        if (quillRespuesta.getText().trim().length < 5) {
            mostrarToast('Respuesta demasiado corta.', 'error');
            return;
        }
        const fd = new FormData();
        fd.append('respuesta_id', id);
        fd.append('contenido', quillRespuesta.root.innerHTML);
        fetch(`${BASE_URL}/administrador/api/foro/editar_respuesta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    cerrarModal('modal-respuesta');
                    mostrarToast('Respuesta actualizada', 'success');
                    const el = document.querySelector(`#ri-${id} .ri-contenido`);
                    if (el) el.innerHTML = quillRespuesta.root.innerHTML;
                } else mostrarToast(d.msg, 'error');
            })
            .catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── ELIMINAR RESPUESTA ────────────────────────────────────
    function eliminarRespuesta(id) {
        if (!confirm('¿Eliminar esta respuesta? Esta acción no se puede deshacer.')) return;
        const fd = new FormData();
        fd.append('respuesta_id', id);
        fetch(`${BASE_URL}/administrador/api/foro/eliminar_respuesta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    document.getElementById(`ri-${id}`)?.remove();
                    mostrarToast('Respuesta eliminada', 'success');
                } else mostrarToast(d.msg, 'error');
            })
            .catch(() => mostrarToast('Error de conexión', 'error'));
    }

    // ── TOAST ─────────────────────────────────────────────────
    function mostrarToast(msg, tipo = 'success') {
        const viejo = document.getElementById('p-toast');
        if (viejo) viejo.remove();
        const c = {
            success: {
                bg: '#219EBC',
                icon: 'fa-circle-check'
            },
            error: {
                bg: '#FB8500',
                icon: 'fa-circle-exclamation'
            }
        };
        const {
            bg,
            icon
        } = c[tipo] ?? c.success;
        const t = document.createElement('div');
        t.id = 'p-toast';
        t.style.cssText = `position:fixed;bottom:28px;right:28px;z-index:9999;background:${bg};color:#fff;
        padding:13px 20px;border-radius:10px;font-size:13px;font-weight:500;
        display:flex;align-items:center;gap:10px;box-shadow:0 8px 24px rgba(2,48,71,.25);
        animation:toastIn .3s ease;font-family:'Poppins',sans-serif;`;
        t.innerHTML = `<i class="fa-solid ${icon}"></i> ${msg}`;
        document.body.appendChild(t);
        if (!document.getElementById('toast-anim')) {
            const s = document.createElement('style');
            s.id = 'toast-anim';
            s.textContent = `@keyframes toastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
                       @keyframes toastOut{from{opacity:1}to{opacity:0;transform:translateY(8px)}}`;
            document.head.appendChild(s);
        }
        setTimeout(() => {
            t.style.animation = 'toastOut .3s ease forwards';
            setTimeout(() => t.remove(), 300);
        }, 3500);
    }
    </script>
</body>

</html>