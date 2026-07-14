<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();
if (usuarioRol() !== 'admin') {
    header('Location: ../panelAdmin.php'); exit;
}

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$iniciales     = inicialesAvatar($nombreUsuario);

$stmtU = $conexion->prepare("SELECT nombre, avatar FROM usuarios WHERE id = ?");
$stmtU->bind_param("i", $uid); $stmtU->execute();
$usuario = $stmtU->get_result()->fetch_assoc(); $stmtU->close();
$avatarRaw = $usuario['avatar'] ?? '';
$avatarUsuario = '';
if (!empty($avatarRaw)) {
    if (str_starts_with($avatarRaw, 'http')) { $avatarUsuario = $avatarRaw; }
    else { $r = ltrim($avatarRaw, '/'); if (file_exists(__DIR__.'/../../'.$r)) $avatarUsuario = '../../'.$r; }
}

// ── Métricas para dashboard ──────────────────────────────
$metrics = [];
$metrics['total_usuarios']    = $conexion->query("SELECT COUNT(*) FROM usuarios")->fetch_row()[0];
$metrics['total_profesores']  = $conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol='profesor'")->fetch_row()[0];
$metrics['total_estudiantes'] = $conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol='estudiante'")->fetch_row()[0];
$metrics['total_cursos']      = $conexion->query("SELECT COUNT(*) FROM cursos")->fetch_row()[0];
$metrics['cursos_publicados'] = $conexion->query("SELECT COUNT(*) FROM cursos WHERE estado='publicado'")->fetch_row()[0];
$metrics['total_inscrip']     = $conexion->query("SELECT COUNT(*) FROM inscripciones")->fetch_row()[0];
$metrics['total_preguntas']   = $conexion->query("SELECT COUNT(*) FROM foro_preguntas")->fetch_row()[0];
$metrics['total_respuestas']  = $conexion->query("SELECT COUNT(*) FROM foro_respuestas")->fetch_row()[0];
$metrics['preguntas_abiertas']= $conexion->query("SELECT COUNT(*) FROM foro_preguntas WHERE estado='abierta'")->fetch_row()[0];

// Actividad reciente (últimos 7 días)
$metrics['nuevos_usuarios_semana'] = $conexion->query(
    "SELECT COUNT(*) FROM usuarios WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetch_row()[0];
$metrics['nuevas_preguntas_semana'] = $conexion->query(
    "SELECT COUNT(*) FROM foro_preguntas WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
)->fetch_row()[0];

// Cursos más inscritos
$topCursos = $conexion->query("
    SELECT c.id, c.titulo, c.estado, COUNT(i.usuario_id) AS inscritos, u.nombre AS profesor
    FROM cursos c
    LEFT JOIN inscripciones i ON i.curso_id = c.id
    JOIN usuarios u ON u.id = c.profesor_id
    GROUP BY c.id ORDER BY inscritos DESC LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Categorías existentes
$categorias = $conexion->query(
    "SELECT DISTINCT categoria FROM cursos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria"
)->fetch_all(MYSQLI_ASSOC);

// Config global (tabla simple clave-valor; se crea si no existe)
$conexion->query("CREATE TABLE IF NOT EXISTS plataforma_config (
    clave VARCHAR(100) PRIMARY KEY,
    valor TEXT
)");
$configRes = $conexion->query("SELECT clave, valor FROM plataforma_config");
$config = [];
while ($row = $configRes->fetch_assoc()) $config[$row['clave']] = $row['valor'];
$config = array_merge([
    'nombre_plataforma' => 'EduTecnia',
    'descripcion'       => 'Plataforma de aprendizaje en línea',
    'logo_url'          => '../img/logoEduTecnia.png',
    'color_primario'    => '#1E4978',
], $config);


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador | EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styleAdministrador.css">
    <link rel="stylesheet" href="../css/sadminPanel.css">
</head>
<body>
<div class="layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <img src="../img/logoEduTecnia.png" alt="EduTecnia">
        </div>
        <nav class="sidebar-nav">
            <span class="nav-section-label">Panel Admin</span>
            <a class="nav-link-item active" data-tab="dashboard" onclick="cambiarTab('dashboard')">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <span class="nav-section-label">Gestión</span>
            <a class="nav-link-item" data-tab="usuarios" onclick="cambiarTab('usuarios')">
                <i class="bi bi-people-fill"></i> Usuarios
            </a>
            <a class="nav-link-item" data-tab="cursos" onclick="cambiarTab('cursos')">
                <i class="bi bi-collection-play-fill"></i> Cursos
            </a>
            <a class="nav-link-item" data-tab="foro" onclick="cambiarTab('foro')">
                <i class="bi bi-chat-square-dots-fill"></i> Foro
            </a>
            <span class="nav-section-label">Reportes</span>
            <a class="nav-link-item" data-tab="reportes" onclick="cambiarTab('reportes')">
                <i class="bi bi-bar-chart-fill"></i> Reportes
            </a>
            <span class="nav-section-label">Sistema</span>
            <a class="nav-link-item" data-tab="config" onclick="cambiarTab('config')">
                <i class="bi bi-gear-fill"></i> Configuración
            </a>
            <a class="nav-link-item" href="../../logout.php">
                <i class="bi bi-box-arrow-left"></i> Cerrar sesión
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sf-avatar">
                <?php if ($avatarUsuario): ?>
                <img src="<?= htmlspecialchars($avatarUsuario) ?>" alt="">
                <?php else: ?><?= $iniciales ?><?php endif; ?>
            </div>
            <div class="sf-info">
                <div class="sf-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                <div class="sf-role">Administrador</div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main">
        <div class="topbar">
            <span class="topbar-title" id="topbar-titulo">Dashboard</span>
            <div class="topbar-actions" id="topbar-actions"></div>
        </div>

        <div class="content">

            <!-- ══════════════ DASHBOARD ══════════════ -->
            <section id="tab-dashboard" class="admin-tab active">
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon" style="background:rgba(30,73,120,.1);color:#1E4978">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="metric-body">
                            <div class="metric-num"><?= $metrics['total_usuarios'] ?></div>
                            <div class="metric-lbl">Usuarios totales</div>
                            <div class="metric-sub">+<?= $metrics['nuevos_usuarios_semana'] ?> esta semana</div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background:rgba(74,144,185,.1);color:#4A90B9">
                            <i class="bi bi-collection-play-fill"></i>
                        </div>
                        <div class="metric-body">
                            <div class="metric-num"><?= $metrics['total_cursos'] ?></div>
                            <div class="metric-lbl">Cursos</div>
                            <div class="metric-sub"><?= $metrics['cursos_publicados'] ?> publicados</div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background:rgba(100,185,190,.1);color:#64B9BE">
                            <i class="bi bi-person-check-fill"></i>
                        </div>
                        <div class="metric-body">
                            <div class="metric-num"><?= $metrics['total_inscrip'] ?></div>
                            <div class="metric-lbl">Inscripciones</div>
                            <div class="metric-sub"><?= $metrics['total_estudiantes'] ?> estudiantes</div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background:rgba(220,140,30,.1);color:#DC8C1E">
                            <i class="bi bi-chat-square-dots-fill"></i>
                        </div>
                        <div class="metric-body">
                            <div class="metric-num"><?= $metrics['total_preguntas'] ?></div>
                            <div class="metric-lbl">Preguntas en foro</div>
                            <div class="metric-sub"><?= $metrics['preguntas_abiertas'] ?> abiertas · +<?= $metrics['nuevas_preguntas_semana'] ?> esta semana</div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-lg-7">
                        <div class="admin-card">
                            <div class="admin-card-title"><i class="bi bi-trophy-fill"></i> Top cursos por inscripciones</div>
                            <table class="admin-table">
                                <thead><tr><th>Curso</th><th>Profesor</th><th>Estado</th><th>Inscritos</th></tr></thead>
                                <tbody>
                                <?php foreach ($topCursos as $tc): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($tc['titulo']) ?></td>
                                    <td><?= htmlspecialchars($tc['profesor']) ?></td>
                                    <td><span class="estado-pill estado-<?= $tc['estado'] ?>"><?= $tc['estado'] ?></span></td>
                                    <td><strong><?= $tc['inscritos'] ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="admin-card">
                            <div class="admin-card-title"><i class="bi bi-pie-chart-fill"></i> Distribución de roles</div>
                            <div class="rol-stat">
                                <span class="rol-label"><i class="bi bi-person-video3 me-1"></i> Profesores</span>
                                <div class="rol-bar-wrap">
                                    <div class="rol-bar" style="width:<?= $metrics['total_usuarios']>0 ? round($metrics['total_profesores']/$metrics['total_usuarios']*100) : 0 ?>%;background:#1E4978"></div>
                                </div>
                                <span class="rol-count"><?= $metrics['total_profesores'] ?></span>
                            </div>
                            <div class="rol-stat">
                                <span class="rol-label"><i class="bi bi-person-fill me-1"></i> Estudiantes</span>
                                <div class="rol-bar-wrap">
                                    <div class="rol-bar" style="width:<?= $metrics['total_usuarios']>0 ? round($metrics['total_estudiantes']/$metrics['total_usuarios']*100) : 0 ?>%;background:#4A90B9"></div>
                                </div>
                                <span class="rol-count"><?= $metrics['total_estudiantes'] ?></span>
                            </div>
                            <div class="rol-stat">
                                <span class="rol-label"><i class="bi bi-shield-fill me-1"></i> Admins</span>
                                <div class="rol-bar-wrap">
                                    <div class="rol-bar" style="width:<?= $metrics['total_usuarios']>0 ? round(($metrics['total_usuarios']-$metrics['total_profesores']-$metrics['total_estudiantes'])/$metrics['total_usuarios']*100) : 0 ?>%;background:#DC8C1E"></div>
                                </div>
                                <span class="rol-count"><?= $metrics['total_usuarios']-$metrics['total_profesores']-$metrics['total_estudiantes'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ══════════════ USUARIOS ══════════════ -->
            <section id="tab-usuarios" class="admin-tab">
                <div class="filtros-row">
                    <input type="text" id="u-buscar" class="admin-input" placeholder="Buscar por nombre o correo..." oninput="cargarUsuarios()">
                    <select id="u-rol" class="admin-select" onchange="cargarUsuarios()">
                        <option value="">Todos los roles</option>
                        <option value="profesor">Profesor</option>
                        <option value="estudiante">Estudiante</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button class="btn-admin-primary" onclick="abrirModalUsuario()">
                        <i class="bi bi-plus-lg me-1"></i> Nuevo usuario
                    </button>
                </div>
                <div class="admin-card mt-3">
                    <div id="tabla-usuarios-wrap">
                        <div class="cargando"><i class="bi bi-arrow-repeat spin me-2"></i>Cargando...</div>
                    </div>
                </div>
            </section>

            <!-- ══════════════ CURSOS ══════════════ -->
            <section id="tab-cursos" class="admin-tab">
                <div class="filtros-row">
                    <input type="text" id="c-buscar" class="admin-input" placeholder="Buscar curso..." oninput="cargarCursos()">
                    <select id="c-estado" class="admin-select" onchange="cargarCursos()">
                        <option value="">Todos los estados</option>
                        <option value="publicado">Publicado</option>
                        <option value="borrador">Borrador</option>
                    </select>
                    <select id="c-cat" class="admin-select" onchange="cargarCursos()">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['categoria']) ?>"><?= htmlspecialchars($cat['categoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-card mt-3">
                    <div id="tabla-cursos-wrap">
                        <div class="cargando"><i class="bi bi-arrow-repeat spin me-2"></i>Cargando...</div>
                    </div>
                </div>
            </section>

            <!-- ══════════════ FORO ══════════════ -->
            <section id="tab-foro" class="admin-tab">
                <div class="filtros-row">
                    <input type="text" id="f-buscar" class="admin-input" placeholder="Buscar pregunta..." oninput="cargarForo()">
                    <select id="f-estado" class="admin-select" onchange="cargarForo()">
                        <option value="">Todos los estados</option>
                        <option value="abierta">Abierta</option>
                        <option value="resuelta">Resuelta</option>
                        <option value="cerrada">Cerrada</option>
                        <option value="destacada">Destacada</option>
                    </select>
                </div>
                <div class="admin-card mt-3">
                    <div id="tabla-foro-wrap">
                        <div class="cargando"><i class="bi bi-arrow-repeat spin me-2"></i>Cargando...</div>
                    </div>
                </div>
            </section>

            <!-- ══════════════ REPORTES ══════════════ -->
            <section id="tab-reportes" class="admin-tab">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="admin-card">
                            <div class="admin-card-title"><i class="bi bi-download me-1"></i> Exportar datos</div>
                            <div class="export-grid">
                                <button class="export-btn" onclick="exportar('inscripciones')">
                                    <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                                    <span>Inscripciones</span>
                                    <small>CSV</small>
                                </button>
                                <button class="export-btn" onclick="exportar('progreso')">
                                    <i class="bi bi-file-earmark-bar-graph-fill"></i>
                                    <span>Progreso estudiantes</span>
                                    <small>CSV</small>
                                </button>
                                <button class="export-btn" onclick="exportar('usuarios')">
                                    <i class="bi bi-file-earmark-person-fill"></i>
                                    <span>Usuarios</span>
                                    <small>CSV</small>
                                </button>
                                <button class="export-btn" onclick="exportar('foro')">
                                    <i class="bi bi-file-earmark-text-fill"></i>
                                    <span>Actividad foro</span>
                                    <small>CSV</small>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="admin-card">
                            <div class="admin-card-title"><i class="bi bi-person-lines-fill me-1"></i> Progreso de estudiantes</div>
                            <div class="filtros-row mb-3">
                                <input type="text" id="r-buscar" class="admin-input" placeholder="Buscar estudiante..." oninput="cargarProgreso()">
                                <select id="r-curso" class="admin-select" onchange="cargarProgreso()">
                                    <option value="">Todos los cursos</option>
                                </select>
                            </div>
                            <div id="tabla-progreso-wrap">
                                <div class="cargando"><i class="bi bi-arrow-repeat spin me-2"></i>Cargando...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ══════════════ CONFIGURACIÓN ══════════════ -->
            <section id="tab-config" class="admin-tab">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="admin-card">
                            <div class="admin-card-title"><i class="bi bi-globe2 me-1"></i> Parámetros globales</div>
                            <form id="form-config" onsubmit="guardarConfig(event)">
                                <div class="mb-3">
                                    <label class="form-label admin-label">Nombre de la plataforma</label>
                                    <input type="text" name="nombre_plataforma" class="admin-input w-100"
                                        value="<?= htmlspecialchars($config['nombre_plataforma']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label admin-label">Descripción</label>
                                    <textarea name="descripcion" class="admin-input w-100" rows="2"><?= htmlspecialchars($config['descripcion']) ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label admin-label">URL del logo</label>
                                    <input type="text" name="logo_url" class="admin-input w-100"
                                        value="<?= htmlspecialchars($config['logo_url']) ?>">
                                </div>
                                <div class="mb-4">
                                    <label class="form-label admin-label">Color primario</label>
                                    <div class="d-flex align-items-center gap-3">
                                        <input type="color" name="color_primario" value="<?= htmlspecialchars($config['color_primario']) ?>"
                                            class="color-picker">
                                        <span class="text-muted" style="font-size:13px;">Color principal de la interfaz</span>
                                    </div>
                                </div>
                                <button type="submit" class="btn-admin-primary">
                                    <i class="bi bi-floppy-fill me-1"></i> Guardar configuración
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="admin-card">
                            <div class="admin-card-title"><i class="bi bi-tags-fill me-1"></i> Categorías de cursos</div>
                            <div id="lista-categorias">
                                <?php foreach ($categorias as $cat): ?>
                                <div class="cat-item" data-cat="<?= htmlspecialchars($cat['categoria']) ?>">
                                    <span><?= htmlspecialchars($cat['categoria']) ?></span>
                                    <button class="btn-icono text-danger" onclick="eliminarCategoria('<?= htmlspecialchars($cat['categoria']) ?>')">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <input type="text" id="nueva-cat" class="admin-input flex-1" placeholder="Nueva categoría...">
                                <button class="btn-admin-primary" onclick="agregarCategoria()">
                                    <i class="bi bi-plus-lg"></i> Agregar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </div><!-- /content -->
    </div><!-- /main -->
</div><!-- /layout -->

<!-- ══════════ MODAL USUARIO ══════════ -->
<div class="modal-overlay" id="modal-usuario">
    <div class="modal-box">
        <div class="modal-hdr">
            <span id="modal-usuario-titulo">Nuevo usuario</span>
            <button class="btn-close-modal" onclick="cerrarModalUsuario()"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="form-usuario" onsubmit="guardarUsuario(event)">
            <input type="hidden" id="u-id" name="id" value="">
            <div class="row g-3">
                <div class="col-12">
                    <label class="admin-label">Nombre completo</label>
                    <input type="text" name="nombre" id="u-nombre" class="admin-input w-100" required>
                </div>
                <div class="col-12">
                    <label class="admin-label">Correo electrónico</label>
                    <input type="email" name="correo" id="u-correo" class="admin-input w-100" required>
                </div>
                <div class="col-md-6">
                    <label class="admin-label">Rol</label>
                    <select name="rol" id="u-rol-select" class="admin-select w-100">
                        <option value="estudiante">Estudiante</option>
                        <option value="profesor">Profesor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="col-md-6" id="u-password-wrap">
                    <label class="admin-label">Contraseña <span id="u-pass-hint" style="font-size:11px;color:var(--muted)">(dejar vacío = sin cambio)</span></label>
                    <input type="password" name="password" id="u-password" class="admin-input w-100" placeholder="••••••••">
                </div>
            </div>
            <div class="modal-footer-btns">
                <button type="button" class="btn-admin-sec" onclick="cerrarModalUsuario()">Cancelar</button>
                <button type="submit" class="btn-admin-primary"><i class="bi bi-floppy-fill me-1"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════ MODAL FORO ══════════ -->
<div class="modal-overlay" id="modal-foro">
    <div class="modal-box" style="max-width:640px">
        <div class="modal-hdr">
            <span id="modal-foro-titulo">Editar pregunta</span>
            <button class="btn-close-modal" onclick="cerrarModalForo()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="modal-foro-body"></div>
        <div class="modal-footer-btns">
            <button type="button" class="btn-admin-sec" onclick="cerrarModalForo()">Cancelar</button>
            <button class="btn-admin-primary" onclick="guardarForo()"><i class="bi bi-floppy-fill me-1"></i> Guardar</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="admin-toast"></div>

<script>
const BASE = '<?= BASE_URL ?>';
let tabActual = 'dashboard';

// ── Tabs ─────────────────────────────────────────────────
const TABS_TITULO = {
    dashboard:'Dashboard', usuarios:'Gestión de Usuarios',
    cursos:'Gestión de Cursos', foro:'Moderación del Foro',
    reportes:'Reportes y Estadísticas', config:'Configuración'
};
function cambiarTab(tab) {
    document.querySelectorAll('.admin-tab').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-link-item').forEach(a => a.classList.remove('active'));
    document.getElementById('tab-'+tab).classList.add('active');
    document.querySelector(`.nav-link-item[data-tab="${tab}"]`)?.classList.add('active');
    document.getElementById('topbar-titulo').textContent = TABS_TITULO[tab];
    tabActual = tab;
    if (tab === 'usuarios')  cargarUsuarios();
    if (tab === 'cursos')    cargarCursos();
    if (tab === 'foro')      cargarForo();
    if (tab === 'reportes')  { cargarProgreso(); cargarCursosFiltro(); }
}

// ── Fetch helper ─────────────────────────────────────────
async function api(endpoint, data = null) {
    const opts = { credentials:'include' };
    if (data) { opts.method = 'POST'; opts.body = data instanceof FormData ? data : new URLSearchParams(data); opts.headers = data instanceof FormData ? {} : {'Content-Type':'application/x-www-form-urlencoded'}; }
    const res = await fetch(`${BASE}/administrador/api/admin/${endpoint}`, opts);
    return res.json();
}

// ── Toast ─────────────────────────────────────────────────
function toast(msg, tipo='success') {
    const t = document.getElementById('admin-toast');
    t.className = `admin-toast show toast-${tipo}`;
    t.innerHTML = `<i class="bi bi-${tipo==='success'?'check-circle-fill':'exclamation-circle-fill'} me-2"></i>${msg}`;
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.classList.remove('show'), 3500);
}

// ══════════════ USUARIOS ══════════════
function cargarUsuarios() {
    const q = document.getElementById('u-buscar').value;
    const rol = document.getElementById('u-rol').value;
    api(`get_usuarios.php?q=${encodeURIComponent(q)}&rol=${encodeURIComponent(rol)}`).then(data => {
        const wrap = document.getElementById('tabla-usuarios-wrap');
        if (!data.ok || !data.usuarios.length) { wrap.innerHTML = '<div class="empty-admin">No hay usuarios que coincidan.</div>'; return; }
        wrap.innerHTML = `<table class="admin-table"><thead><tr>
            <th>Usuario</th><th>Correo</th><th>Rol</th><th>Registro</th><th>Acciones</th>
        </tr></thead><tbody>${data.usuarios.map(u => `<tr>
            <td class="fw-semibold">${esc(u.nombre)}</td>
            <td class="text-muted">${esc(u.correo)}</td>
            <td><span class="rol-badge rol-${u.rol}">${u.rol}</span></td>
            <td class="text-muted" style="font-size:12px;">${u.created_at?.substring(0,10)||'-'}</td>
            <td>
                <button class="btn-icono" title="Editar" onclick="abrirModalUsuario(${JSON.stringify(u)})"><i class="bi bi-pencil-fill"></i></button>
                <button class="btn-icono" title="Reset contraseña" onclick="resetPassword(${u.id})"><i class="bi bi-key-fill"></i></button>
                <button class="btn-icono text-danger" title="Eliminar" onclick="eliminarUsuario(${u.id},'${esc(u.nombre)}')"><i class="bi bi-trash3-fill"></i></button>
            </td>
        </tr>`).join('')}</tbody></table>`;
    });
}

function abrirModalUsuario(u = null) {
    document.getElementById('u-id').value         = u?.id || '';
    document.getElementById('u-nombre').value     = u?.nombre || '';
    document.getElementById('u-correo').value     = u?.correo || '';
    document.getElementById('u-rol-select').value = u?.rol || 'estudiante';
    document.getElementById('u-password').value   = '';
    document.getElementById('modal-usuario-titulo').textContent = u ? 'Editar usuario' : 'Nuevo usuario';
    document.getElementById('u-pass-hint').style.display = u ? '' : 'none';
    document.getElementById('modal-usuario').classList.add('show');
}
function cerrarModalUsuario() { document.getElementById('modal-usuario').classList.remove('show'); }

async function guardarUsuario(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = await api('guardar_usuario.php', fd);
    if (data.ok) { toast(data.msg); cerrarModalUsuario(); cargarUsuarios(); }
    else toast(data.msg, 'error');
}

async function eliminarUsuario(id, nombre) {
    if (!confirm(`¿Eliminar al usuario "${nombre}"? Esta acción no se puede deshacer.`)) return;
    const data = await api('eliminar_usuario.php', {id});
    toast(data.msg, data.ok ? 'success' : 'error');
    if (data.ok) cargarUsuarios();
}

async function resetPassword(id) {
    const nueva = prompt('Nueva contraseña (mínimo 8 caracteres):');
    if (!nueva || nueva.length < 8) { toast('Contraseña demasiado corta.', 'error'); return; }
    const data = await api('reset_password.php', {id, password: nueva});
    toast(data.msg, data.ok ? 'success' : 'error');
}

// ══════════════ CURSOS ══════════════
function cargarCursos() {
    const q = document.getElementById('c-buscar').value;
    const estado = document.getElementById('c-estado').value;
    const cat = document.getElementById('c-cat').value;
    api(`get_cursos.php?q=${encodeURIComponent(q)}&estado=${encodeURIComponent(estado)}&cat=${encodeURIComponent(cat)}`).then(data => {
        const wrap = document.getElementById('tabla-cursos-wrap');
        if (!data.ok || !data.cursos.length) { wrap.innerHTML = '<div class="empty-admin">No hay cursos que coincidan.</div>'; return; }
        wrap.innerHTML = `<table class="admin-table"><thead><tr>
            <th>Curso</th><th>Profesor</th><th>Categoría</th><th>Inscritos</th><th>Estado</th><th>Acciones</th>
        </tr></thead><tbody>${data.cursos.map(c => `<tr>
            <td class="fw-semibold">${esc(c.titulo)}</td>
            <td>${esc(c.profesor)}</td>
            <td><span class="tag-cat">${esc(c.categoria||'Sin categoría')}</span></td>
            <td><strong>${c.inscritos}</strong></td>
            <td><span class="estado-pill estado-${c.estado}">${c.estado}</span></td>
            <td>
                <button class="btn-icono" title="${c.estado==='publicado'?'Despublicar':'Publicar'}"
                    onclick="toggleEstadoCurso(${c.id},'${c.estado}')">
                    <i class="bi bi-${c.estado==='publicado'?'eye-slash-fill':'eye-fill'}"></i>
                </button>
                <button class="btn-icono text-danger" title="Eliminar" onclick="eliminarCurso(${c.id},'${esc(c.titulo)}')">
                    <i class="bi bi-trash3-fill"></i>
                </button>
            </td>
        </tr>`).join('')}</tbody></table>`;
    });
}

async function toggleEstadoCurso(id, estadoActual) {
    const nuevoEstado = estadoActual === 'publicado' ? 'borrador' : 'publicado';
    const data = await api('cambiar_estado_curso.php', {id, estado: nuevoEstado});
    toast(data.msg, data.ok ? 'success' : 'error');
    if (data.ok) cargarCursos();
}

async function eliminarCurso(id, titulo) {
    if (!confirm(`¿Eliminar el curso "${titulo}"? Se eliminarán también sus módulos y lecciones.`)) return;
    const data = await api('eliminar_curso.php', {id});
    toast(data.msg, data.ok ? 'success' : 'error');
    if (data.ok) cargarCursos();
}

// ══════════════ FORO ══════════════
let foroItemActual = null;
function cargarForo() {
    const q = document.getElementById('f-buscar').value;
    const estado = document.getElementById('f-estado').value;
    api(`get_foro.php?q=${encodeURIComponent(q)}&estado=${encodeURIComponent(estado)}`).then(data => {
        const wrap = document.getElementById('tabla-foro-wrap');
        if (!data.ok || !data.preguntas.length) { wrap.innerHTML = '<div class="empty-admin">No hay preguntas que coincidan.</div>'; return; }
        wrap.innerHTML = `<table class="admin-table"><thead><tr>
            <th>Pregunta</th><th>Autor</th><th>Estado</th><th>Respuestas</th><th>Fecha</th><th>Acciones</th>
        </tr></thead><tbody>${data.preguntas.map(p => `<tr>
            <td class="fw-semibold" style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(p.titulo)}</td>
            <td>${esc(p.autor)}</td>
            <td><span class="estado-pill estado-${p.estado}">${p.estado}</span></td>
            <td>${p.respuestas}</td>
            <td class="text-muted" style="font-size:12px;">${p.created_at?.substring(0,10)||'-'}</td>
            <td>
                <button class="btn-icono" title="Editar/Cambiar estado" onclick="abrirModalForo(${JSON.stringify(p)})"><i class="bi bi-pencil-fill"></i></button>
                <button class="btn-icono" title="${p.destacada?'Quitar destaque':'Destacar'}" onclick="toggleDestacada(${p.id},${p.destacada})">
                    <i class="bi bi-star${p.destacada?'-fill text-warning':''}"></i>
                </button>
                <button class="btn-icono text-danger" title="Eliminar" onclick="eliminarPregunta(${p.id})"><i class="bi bi-trash3-fill"></i></button>
            </td>
        </tr>`).join('')}</tbody></table>`;
    });
}

function abrirModalForo(p) {
    foroItemActual = p;
    document.getElementById('modal-foro-titulo').textContent = 'Editar pregunta';
    document.getElementById('modal-foro-body').innerHTML = `
        <div class="mb-3">
            <label class="admin-label">Título</label>
            <input type="text" id="foro-titulo" class="admin-input w-100" value="${esc(p.titulo)}">
        </div>
        <div class="mb-3">
            <label class="admin-label">Estado</label>
            <select id="foro-estado" class="admin-select w-100">
                <option value="abierta" ${p.estado==='abierta'?'selected':''}>Abierta</option>
                <option value="resuelta" ${p.estado==='resuelta'?'selected':''}>Resuelta</option>
                <option value="cerrada" ${p.estado==='cerrada'?'selected':''}>Cerrada</option>
            </select>
        </div>`;
    document.getElementById('modal-foro').classList.add('show');
}
function cerrarModalForo() { document.getElementById('modal-foro').classList.remove('show'); foroItemActual = null; }

async function guardarForo() {
    if (!foroItemActual) return;
    const titulo = document.getElementById('foro-titulo').value.trim();
    const estado = document.getElementById('foro-estado').value;
    const data = await api('editar_pregunta.php', {id: foroItemActual.id, titulo, estado});
    toast(data.msg, data.ok ? 'success' : 'error');
    if (data.ok) { cerrarModalForo(); cargarForo(); }
}

async function toggleDestacada(id, actual) {
    const data = await api('destacar_pregunta.php', {id, destacada: actual ? 0 : 1});
    toast(data.msg, data.ok ? 'success' : 'error');
    if (data.ok) cargarForo();
}

async function eliminarPregunta(id) {
    if (!confirm('¿Eliminar esta pregunta y todas sus respuestas?')) return;
    const data = await api('eliminar_pregunta.php', {id});
    toast(data.msg, data.ok ? 'success' : 'error');
    if (data.ok) cargarForo();
}

// ══════════════ REPORTES ══════════════
async function cargarCursosFiltro() {
    const data = await api('get_cursos.php?q=&estado=&cat=');
    const sel = document.getElementById('r-curso');
    if (data.ok) data.cursos.forEach(c => {
        const o = document.createElement('option'); o.value = c.id; o.textContent = c.titulo; sel.appendChild(o);
    });
}

function cargarProgreso() {
    const q = document.getElementById('r-buscar').value;
    const cursoId = document.getElementById('r-curso').value;
    api(`get_progreso.php?q=${encodeURIComponent(q)}&curso_id=${cursoId}`).then(data => {
        const wrap = document.getElementById('tabla-progreso-wrap');
        if (!data.ok || !data.progreso.length) { wrap.innerHTML = '<div class="empty-admin">No hay datos de progreso.</div>'; return; }
        wrap.innerHTML = `<table class="admin-table"><thead><tr>
            <th>Estudiante</th><th>Curso</th><th>Progreso</th><th>Inscrito</th>
        </tr></thead><tbody>${data.progreso.map(p => `<tr>
            <td class="fw-semibold">${esc(p.estudiante)}</td>
            <td>${esc(p.curso)}</td>
            <td>
                <div class="progress-wrap">
                    <div class="progress-bar-inner" style="width:${p.pct}%"></div>
                </div>
                <span style="font-size:11px;color:var(--muted)">${p.pct}%</span>
            </td>
            <td class="text-muted" style="font-size:12px;">${p.fecha?.substring(0,10)||'-'}</td>
        </tr>`).join('')}</tbody></table>`;
    });
}

function exportar(tipo) {
    window.location.href = `${BASE}/administrador/api/admin/exportar.php?tipo=${tipo}`;
}

// ══════════════ CONFIGURACIÓN ══════════════
async function guardarConfig(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const data = await api('guardar_config.php', fd);
    toast(data.msg, data.ok ? 'success' : 'error');
}

async function agregarCategoria() {
    const input = document.getElementById('nueva-cat');
    const nombre = input.value.trim();
    if (!nombre) return;
    const data = await api('agregar_categoria.php', {nombre});
    if (data.ok) {
        const div = document.createElement('div');
        div.className = 'cat-item';
        div.dataset.cat = nombre;
        div.innerHTML = `<span>${esc(nombre)}</span>
            <button class="btn-icono text-danger" onclick="eliminarCategoria('${esc(nombre)}')"><i class="bi bi-trash3"></i></button>`;
        document.getElementById('lista-categorias').appendChild(div);
        input.value = '';
        toast(data.msg);
    } else toast(data.msg, 'error');
}

async function eliminarCategoria(nombre) {
    if (!confirm(`¿Eliminar la categoría "${nombre}"? Los cursos con esta categoría quedarán sin categoría asignada.`)) return;
    const data = await api('eliminar_categoria.php', {nombre});
    if (data.ok) {
        document.querySelector(`.cat-item[data-cat="${nombre}"]`)?.remove();
        toast(data.msg);
    } else toast(data.msg, 'error');
}

// ── Util ─────────────────────────────────────────────────
function esc(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
cargarUsuarios();
</script>
</body>
</html>