<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '/pp');
}

iniciarSesion();

// ── Validar ID de pregunta ────────────────────────────────
$preguntaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($preguntaId <= 0) {
    header("Location: foroEs.php");
    exit;
}

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$rolUsuario    = usuarioRol();
$iniciales     = inicialesAvatar($nombreUsuario);
$avatarUsuario = null;

// Cargar avatar 
$stmtU = $conexion->prepare("SELECT avatar FROM usuarios WHERE id = ? LIMIT 1");
$stmtU->bind_param("i", $uid);
$stmtU->execute();
$resU = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

if ($resU && !empty($resU['avatar'])) {
    $avatarRaw = $resU['avatar'];
    if (str_starts_with($avatarRaw, 'http')) {
        $avatarUsuario = $avatarRaw;
    } else {
        $rutaLimpia = ltrim($avatarRaw, '/');
        if (file_exists(__DIR__ . '/../' . $rutaLimpia)) {
            $avatarUsuario = '../' . $rutaLimpia;
        }
    }
}

// ── Incrementar vistas ────────────────────────────────────
$conexion->query("UPDATE foro_preguntas SET vistas = vistas + 1 WHERE id = $preguntaId");

// ── Cargar la pregunta ────────────────────────────────────
$stmtP = $conexion->prepare("
    SELECT fp.*, u.nombre AS autor_nombre, u.avatar AS autor_avatar
    FROM   foro_preguntas fp
    JOIN   usuarios u ON u.id = fp.usuario_id
    WHERE  fp.id = ?
");
$stmtP->bind_param("i", $preguntaId);
$stmtP->execute();
$pregunta = $stmtP->get_result()->fetch_assoc();
$stmtP->close();

if (!$pregunta) {
    header("Location: foroEs.php");
    exit;
}

// ── Cargar respuestas ─────────────────────────────────────
$stmtR = $conexion->prepare("
    SELECT fr.*, u.nombre AS autor_nombre, u.avatar AS autor_avatar
    FROM   foro_respuestas fr
    JOIN   usuarios u ON u.id = fr.usuario_id
    WHERE  fr.pregunta_id = ?
    ORDER  BY fr.es_solucion DESC, fr.created_at ASC
");
$stmtR->bind_param("i", $preguntaId);
$stmtR->execute();
$respuestas      = $stmtR->get_result()->fetch_all(MYSQLI_ASSOC);
$totalRespuestas = count($respuestas);
$stmtR->close();

// ── Helpers ───────────────────────────────────────────────
function tiempoRelativo(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 60)      return 'hace un momento';
    if ($diff < 3600)    return 'hace ' . floor($diff/60)    . ' min';
    if ($diff < 86400)   return 'hace ' . floor($diff/3600)  . 'h';
    if ($diff < 2592000) return 'hace ' . floor($diff/86400) . ' días';
    return date('d M Y', strtotime($fecha));
}

function langColor(string $lang): string {
    $map = [
        'python'=>'#3572A5','javascript'=>'#f1e05a','typescript'=>'#2b7489',
        'java'=>'#b07219','csharp'=>'#178600','cpp'=>'#f34b7d','go'=>'#00ADD8',
        'rust'=>'#dea584','php'=>'#4F5D95','ruby'=>'#701516','swift'=>'#ffac45',
        'kotlin'=>'#A97BFF','sql'=>'#e38c00','bash'=>'#89e051',
    ];
    return $map[strtolower($lang)] ?? '#8ECAE6';
}

function inicialesDeNombre(string $nombre): string {
    $p = explode(" ", $nombre);
    return strtoupper(substr($p[0],0,1) . (isset($p[1]) ? substr($p[1],0,1) : ''));
}

$langs = $pregunta['lenguajes']
    ? array_filter(array_map('trim', explode(',', $pregunta['lenguajes'])))
    : [];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pregunta['titulo']) ?> | EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/sAsk.css">
    <link rel="stylesheet" href="../css/sRespuesta.css">
</head>

<body>

    <!-- NAVBAR -->
    <header class="d-flex flex-wrap justify-content-between align-items-center py-3 px-4" id="prin">
        <a href="index.php?uid=<?= $uid ?>" class="d-flex align-items-center text-decoration-none">
            <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="50">
        </a>
        <ul class="nav align-items-center gap-1 mb-0">
            <li class="nav-item">
                <input type="search" id="courseSearch" placeholder="Buscar cursos...">
            </li>
            <li><a href="index.php?uid=<?= $uid ?>" class="nav-link">Inicio</a></li>
            <li><a href="index.php?uid=<?= $uid ?>#cursos" class="nav-link">Cursos</a></li>
            <li><a href="foroEs.php?uid=<?= $uid ?>" class="nav-link active">Foro</a></li>
            <li>
                <a href="perfil.php?uid=<?= $uid ?>" class="nav-link px-2">
                    <?php if (!empty($avatarUsuario)): ?>
                    <img src="<?= $avatarUsuario ?>" alt="Avatar" class="user-avatar-img">
                    <?php else: ?>
                    <div class="user-avatar-initials"><?= $iniciales ?></div>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
    </header>

    <div class="container">
        <div class="resp-wrapper">

            <div class="btn-ask text-end container mt-3">
                <a href="foroEs.php?uid=<?= $uid ?>" class="btn btn-primary fw-semibold px-4 py-2 mb-2"
                    style="border-radius:10px; background-color:#219EBC; border:none;">
                    <i class="bi bi-arrow-left-short"></i>Regresar
                </a>
            </div>

            <div class="row g-4">

                <!-- COLUMNA PRINCIPAL -->
                <div class="col-lg-8">

                    <!-- Pregunta original -->
                    <div class="ask-card pregunta-original mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <?php
                            $avPRaw = $pregunta['autor_avatar'] ?? '';
                            $avP = '';
                            if (!empty($avPRaw)) {
                                if (str_starts_with($avPRaw, 'http')) {
                                    $avP = $avPRaw;
                                } else {
                                    $rutaAv = ltrim($avPRaw, '/');
                                    if (file_exists(__DIR__ . '/../' . $rutaAv)) {
                                        $avP = '../' . $rutaAv;
                                    }
                                }
                            }
                            $inP = inicialesDeNombre($pregunta['autor_nombre']);
                            ?>
                                <?php if ($avP): ?>
                                <img src="<?= htmlspecialchars($avP) ?>" class="resp-avatar-img" alt="">
                                <?php else: ?>
                                <div class="resp-avatar-initials"><?= $inP ?></div>
                                <?php endif; ?>
                                <div>
                                    <span class="fw-semibold"><?= htmlspecialchars($pregunta['autor_nombre']) ?></span>
                                    <span class="text-muted ms-2" style="font-size:13px;">
                                        · <?= tiempoRelativo($pregunta['created_at']) ?>
                                    </span>
                                </div>
                            </div>
                            <span class="status-<?= $pregunta['estado'] ?>" id="estado-badge-pregunta">
                                <?= ucfirst($pregunta['estado']) ?>
                            </span>
                        </div>

                        <h2 class="fw-bold mb-3" style="font-size:1.4rem;">
                            <?= htmlspecialchars($pregunta['titulo']) ?>
                        </h2>

                        <?php if (!empty($langs)): ?>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ($langs as $lang): ?>
                            <span class="badge px-2 py-1 text-white"
                                style="background:<?= langColor($lang) ?>;font-size:11px;">
                                # <?= htmlspecialchars($lang) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="resp-contenido ql-editor " style="padding:0;">
                            <?php
        $contenido = $pregunta['contenido'];
        // Remover imágenes base64 rotas
        $contenido = preg_replace('/<img[^>]*src="data:image[^"]*"[^>]*>/i', '', $contenido);
        echo $contenido;
    ?>
                        </div>

                        <!-- Opciones de la pregunta (solo el autor puede editarla/cambiar estado) -->
                        <?php if ($uid === (int)$pregunta['usuario_id']): ?>
                        <div class="d-flex gap-2 mt-3 flex-wrap">
                            <select class="form-select form-select-sm" style="max-width:160px;"
                                onchange="cambiarEstado(<?= $preguntaId ?>, this.value)">
                                <option value="abierta" <?= $pregunta['estado']==='abierta'  ? 'selected':'' ?>>Abierta
                                </option>
                                <option value="resuelta" <?= $pregunta['estado']==='resuelta' ? 'selected':'' ?>>
                                    Resuelta</option>
                                <option value="cerrada" <?= $pregunta['estado']==='cerrada'  ? 'selected':'' ?>>Cerrada
                                </option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Respuestas -->
                    <?php if (empty($respuestas)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-regular fa-comments fa-2x mb-2"></i>
                        <p>Aún no hay respuestas. ¡Sé el primero en responder!</p>
                    </div>
                    <?php else: ?>
                    <h6 class="fw-semibold mb-3"><?= $totalRespuestas ?> respuesta<?= $totalRespuestas!==1?'s':'' ?>
                    </h6>
                    <?php foreach ($respuestas as $r):
                    $avRRaw = $r['autor_avatar'] ?? '';
                    $avR = '';
                    if (!empty($avRRaw)) {
                        if (str_starts_with($avRRaw, 'http')) {
                            $avR = $avRRaw;
                        } else {
                            $rutaAv = ltrim($avRRaw, '/');
                            if (file_exists(__DIR__ . '/../' . $rutaAv)) {
                                $avR = '../' . $rutaAv;
                            }
                        }
                    }
                    $inR = inicialesDeNombre($r['autor_nombre']);
                ?>
                    <div class="ask-card mb-3 <?= $r['es_solucion'] ? 'border border-success' : '' ?>">
                        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($avR): ?>
                                <img src="<?= htmlspecialchars($avR) ?>" class="resp-avatar-img" alt="">
                                <?php else: ?>
                                <div class="resp-avatar-initials"><?= $inR ?></div>
                                <?php endif; ?>
                                <div>
                                    <span class="fw-semibold"><?= htmlspecialchars($r['autor_nombre']) ?></span>
                                    <span class="text-muted ms-2" style="font-size:13px;">
                                        · <?= tiempoRelativo($r['created_at']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Votos -->
                            <div class="votos-wrap ms-auto d-flex align-items-center gap-1">
                                <button class="btn-voto" onclick="votar(<?= $r['id'] ?>, 'up')" title="Útil">
                                    <i class="bi bi-chevron-compact-up"></i>
                                </button>
                                <span class="votos-count" id="votos-<?= $r['id'] ?>"><?= $r['votos'] ?></span>
                                <button class="btn-voto" onclick="votar(<?= $r['id'] ?>, 'down')" title="No útil">
                                    <i class="bi bi-chevron-compact-down"></i>
                                </button>
                            </div>


                        </div>

                        <div class="resp-contenido ql-editor" style="padding:0;">
                            <?= $r['contenido'] ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Formulario de respuesta -->
                    <?php if ($pregunta['estado'] !== 'cerrada'): ?>
                    <div class="resp-form-title mt-4">
                        <i class="fa-solid fa-reply me-2"></i>Tu respuesta
                    </div>
                    <div class="ask-card" id="form-respuesta">
                        <div class="mb-4">
                            <label class="form-label">Escribe tu respuesta <span class="field-required">*</span></label>
                            <div id="resp-editor"></div>
                            <span class="invalid-msg" id="err-resp-contenido">Escribe tu respuesta antes de
                                publicar.</span>
                            <p class="form-hint mt-2">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                Sé específico. Si incluyes código, usa el bloque de código del editor.
                            </p>
                        </div>
                        <hr class="ask-divider">
                        <div class="ask-actions">
                            <button class="btn-preview-ask" type="button" onclick="abrirPreviewResp()">
                                <i class="fa-regular fa-eye"></i> Vista previa
                            </button>
                            <div class="actions-right">
                                <button class="btn-reset-ask" type="button" onclick="resetRespuesta()">
                                    <i class="fa-solid fa-rotate-left me-1"></i>Limpiar
                                </button>
                                <button class="btn btn-primary px-4 fw-semibold" type="button"
                                    onclick="publicarRespuesta()">
                                    <i class="fa-solid fa-paper-plane me-2"></i>Publicar respuesta
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="resp-login-prompt" style="color:var(--muted);">
                        <i class="fa-solid fa-lock me-2"></i>
                        Esta pregunta está cerrada y ya no acepta respuestas.
                    </div>
                    <?php endif; ?>

                </div>

                <!-- SIDEBAR -->
                <div class="col-lg-4">
                    <div class="tips-card mb-3">
                        <h6><i class="fa-solid fa-circle-info"></i> Sobre esta pregunta</h6>
                        <ul>
                            <li>Publicada <?= tiempoRelativo($pregunta['created_at']) ?></li>
                            <li><?= $pregunta['vistas'] ?> vista<?= $pregunta['vistas']!=1?'s':'' ?></li>
                            <li><?= $totalRespuestas ?> respuesta<?= $totalRespuestas!==1?'s':'' ?></li>
                            <li>Estado: <strong><?= ucfirst($pregunta['estado']) ?></strong></li>
                        </ul>
                    </div>
                    <div class="tips-card mb-3">
                        <h6><i class="fa-solid fa-lightbulb"></i> Consejos para responder bien</h6>
                        <ul>
                            <li>Lee la pregunta completa antes de responder.</li>
                            <li>Sé claro, directo y respetuoso.</li>
                            <li>Incluye ejemplos de código si aplica.</li>
                            <li>Explica el <em>por qué</em>, no solo el cómo.</li>
                            <li>Verifica que tu solución funcione.</li>
                        </ul>
                    </div>
                    <div class="conduct-card">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Recuerda respetar el <a href="#">código de conducta</a>.
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Preview Modal Respuesta -->
    <div class="preview-backdrop" id="preview-backdrop-resp" onclick="backdropClickResp(event)">
        <div class="preview-modal" id="preview-modal-resp">
            <div class="preview-header">
                <div class="d-flex align-items-center gap-2">
                    <h5>Vista previa de respuesta</h5>
                    <span class="badge-preview">Solo tú puedes ver esto</span>
                </div>
                <button class="btn-close-preview" onclick="cerrarPreviewResp()" title="Cerrar">&#10005;</button>
            </div>
            <div class="preview-body">
                <div id="preview-resp-wrap"></div>
            </div>
            <div class="preview-footer">
                <button class="btn-cancel-ask" onclick="cerrarPreviewResp()">Cerrar</button>
                <button class="btn btn-primary px-4 fw-semibold" onclick="cerrarPreviewResp(); publicarRespuesta()">
                    <i class="fa-solid fa-paper-plane me-2"></i>Publicar
                </button>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <div id="div-footer">
            <span>© 2026 Universidad Autónoma Metropolitana, Cuajimalpa</span>
            <div style="display:flex;gap:24px;">
                <a href="index.php?uid=<?= $uid ?>"
                    style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Inicio</a>
                <a href="index.php?uid=<?= $uid ?>#cursos"
                    style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Cursos</a>
                <a href="foroEs.php?uid=<?= $uid ?>"
                    style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Foro</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
    const PREGUNTA_ID = <?= $preguntaId ?>;
    const USUARIO_LOGUEADO = <?= $uid ? 'true' : 'false' ?>;
    const userId = <?= (int)$uid ?>;
    const BASE_URL = '<?= BASE_URL ?>';
    </script>
    <!-- sRespuesta.js reutilizado tal cual — las rutas fetch ya usan BASE_URL o ruta absoluta /pp/ -->
    <script src="<?= BASE_URL ?>/js/sRespuesta.js"></script>

    <script>
    // ── Cambiar estado de pregunta (solo el autor) ────────────
    async function cambiarEstado(preguntaId, nuevoEstado) {
        const fd = new FormData();
        fd.append('pregunta_id', preguntaId);
        fd.append('estado', nuevoEstado);
        try {
            const res = await fetch(`${BASE_URL}/administrador/api/foro/cambiar_estado_pregunta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const data = await res.json();
            if (data.ok) {
                mostrarToast('Estado actualizado.', 'success');
                // Actualizar badge visual sin recargar
                const badge = document.getElementById('estado-badge-pregunta');
                if (badge) {
                    badge.className = `status-${nuevoEstado}`;
                    badge.textContent = nuevoEstado.charAt(0).toUpperCase() + nuevoEstado.slice(1);
                }
                // Si se cierra, recargar para ocultar el formulario de respuesta
                if (nuevoEstado === 'cerrada') setTimeout(() => location.reload(), 1000);
            } else {
                mostrarToast(data.msg || 'Error.', 'error');
            }
        } catch (e) {
            mostrarToast('Error de conexión.', 'error');
        }
    }

    // ── Eliminar respuesta propia ─────────────────────────────
    async function eliminarRespuesta(respuestaId) {
        if (!confirm('¿Eliminar esta respuesta?')) return;
        const fd = new FormData();
        fd.append('respuesta_id', respuestaId);
        try {
            const res = await fetch(`${BASE_URL}/administrador/api/foro/eliminar_respuesta.php`, {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            const data = await res.json();
            if (data.ok) {
                mostrarToast('Respuesta eliminada.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else mostrarToast(data.msg || 'Error.', 'error');
        } catch (e) {
            mostrarToast('Error de conexión.', 'error');
        }
    }
    </script>

</body>

</html>