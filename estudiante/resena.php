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
    SELECT c.id, c.titulo, c.descripcion, c.imagen, c.categoria,
           u.nombre AS profesor_nombre, u.avatar AS profesor_avatar
    FROM cursos c
    JOIN usuarios u ON u.id = c.profesor_id
    WHERE c.id = ? AND c.estado = 'publicado'
");
$stmtC->bind_param("i", $cursoId);
$stmtC->execute();
$curso = $stmtC->get_result()->fetch_assoc();
$stmtC->close();
if (!$curso) { header("Location: index.php"); exit; }

// ── Verificar que el curso está completado ────────────────
$stmtIns = $conexion->prepare("
    SELECT completado FROM inscripciones
    WHERE usuario_id = ? AND curso_id = ?
");
$stmtIns->bind_param("ii", $uid, $cursoId);
$stmtIns->execute();
$inscripcion = $stmtIns->get_result()->fetch_assoc();
$stmtIns->close();

if (!$inscripcion) { header("Location: index.php"); exit; }
$cursoCompletado = (bool)$inscripcion['completado'];

// ── Reseña existente del usuario ──────────────────────────
$stmtR = $conexion->prepare("
    SELECT id, estrellas, comentario, created_at
    FROM curso_resenas
    WHERE curso_id = ? AND usuario_id = ?
");
$stmtR->bind_param("ii", $cursoId, $uid);
$stmtR->execute();
$resenaExistente = $stmtR->get_result()->fetch_assoc();
$stmtR->close();

// ── Estadísticas de reseñas del curso ────────────────────
$stmtStats = $conexion->prepare("
    SELECT
        COUNT(*)                    AS total,
        ROUND(AVG(estrellas), 1)    AS promedio,
        SUM(estrellas = 5)          AS e5,
        SUM(estrellas = 4)          AS e4,
        SUM(estrellas = 3)          AS e3,
        SUM(estrellas = 2)          AS e2,
        SUM(estrellas = 1)          AS e1
    FROM curso_resenas
    WHERE curso_id = ?
");
$stmtStats->bind_param("i", $cursoId);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc();
$stmtStats->close();

// ── Otras reseñas (las últimas 5, excluyendo la del usuario) ─
$stmtOtras = $conexion->prepare("
    SELECT r.estrellas, r.comentario, r.created_at, u.nombre, u.avatar
    FROM curso_resenas r
    JOIN usuarios u ON u.id = r.usuario_id
    WHERE r.curso_id = ? AND r.usuario_id != ?
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmtOtras->bind_param("ii", $cursoId, $uid);
$stmtOtras->execute();
$otrasResenas = $stmtOtras->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtOtras->close();

function tiempoRelativo(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 60)      return 'hace un momento';
    if ($diff < 3600)    return 'hace ' . floor($diff/60) . ' min';
    if ($diff < 86400)   return 'hace ' . floor($diff/3600) . 'h';
    if ($diff < 2592000) return 'hace ' . floor($diff/86400) . ' días';
    return date('d M Y', strtotime($fecha));
}

// Imagen del curso
$imgSrc = '';
if (!empty($curso['imagen'])) {
    $rutaAbs = __DIR__ . '/../' . ltrim($curso['imagen'], '/');
    if (file_exists($rutaAbs)) $imgSrc = $curso['imagen'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseña · <?= htmlspecialchars($curso['titulo']) ?> | EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="../css/sResena.css">
</head>
<body>

<!-- NAVBAR -->
<header id="prin">
    <a href="index.php" class="d-flex align-items-center text-decoration-none gap-2">
        <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="44">
    </a>
    <nav class="d-flex align-items-center gap-1">
        <a href="index.php" class="nav-link">Inicio</a>
        <a href="index.php#cursos" class="nav-link">Cursos</a>
        <a href="foroEs.php" class="nav-link">Foro</a>
        <a href="perfil.php" class="nav-link px-2">
            <?php if ($avatarUsuario): ?>
                <img src="<?= htmlspecialchars($avatarUsuario) ?>" class="user-avatar-img" alt="">
            <?php else: ?>
                <div class="user-avatar"><?= $iniciales ?></div>
            <?php endif; ?>
        </a>
    </nav>
</header>

<!-- HERO -->
<div class="curso-hero">
    <div class="container">
        <a href="verCurso.php?id=<?= $cursoId ?>"
           style="color:rgba(255,255,255,.5);font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:5px;margin-bottom:20px;">
            <i class="bi bi-chevron-left"></i> Volver al curso
        </a>
        <div class="d-flex align-items-center gap-20" style="gap:20px;">
            <?php if ($imgSrc): ?>
                <img src="<?= htmlspecialchars($imgSrc) ?>" class="hero-thumb" alt="">
            <?php else: ?>
                <div class="hero-thumb-placeholder"><i class="bi bi-collection-play-fill" style="color:rgba(255,255,255,.5);"></i></div>
            <?php endif; ?>
            <div>
                <div class="hero-cat"><?= htmlspecialchars($curso['categoria'] ?? 'General') ?></div>
                <div class="hero-titulo"><?= htmlspecialchars($curso['titulo']) ?></div>
                <div class="hero-prof">
                    <i class="bi bi-person-circle me-1"></i>
                    Impartido por <span><?= htmlspecialchars($curso['profesor_nombre']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CONTENIDO -->
<div class="resena-wrap">

    <!-- ══ FORMULARIO ══ -->
    <div class="form-card">
        <div class="form-card-title">
            <i class="bi bi-star-fill"></i>
            <?= $resenaExistente ? 'Editar mi reseña' : 'Escribe tu reseña' ?>
        </div>

        <?php if ($resenaExistente): ?>
        <div class="ya-resenado">
            <i class="bi bi-check-circle-fill"></i>
            Ya dejaste una reseña el <?= date('d M Y', strtotime($resenaExistente['created_at'])) ?>
            — puedes editarla aquí.
        </div>
        <?php endif; ?>

        <?php if (!$cursoCompletado): ?>
        <div class="aviso-incompleto">
            <i class="bi bi-exclamation-circle-fill"></i>
            <p>
                <strong>Aún no has completado este curso.</strong><br>
                Puedes dejar una reseña, pero te recomendamos terminar todas las lecciones para dar una opinión más completa.
            </p>
        </div>
        <?php endif; ?>

        <!-- Estrellas -->
        <div class="stars-wrap">
            <div class="stars-label">¿Cómo calificarías este curso?</div>
            <div class="stars-selector" id="starsSelector">
                <?php for ($s = 5; $s >= 1; $s--): ?>
                <input type="radio" name="estrellas" id="star<?= $s ?>" value="<?= $s ?>"
                       <?= ($resenaExistente && (int)$resenaExistente['estrellas'] === $s) ? 'checked' : '' ?>>
                <label for="star<?= $s ?>" title="<?= $s ?> estrella<?= $s > 1 ? 's' : '' ?>">★</label>
                <?php endfor; ?>
            </div>
            <div class="rating-desc" id="ratingDesc">
                <?php
                $descs = [1=>'Muy malo',2=>'Regular',3=>'Bueno',4=>'Muy bueno',5=>'¡Excelente!'];
                echo $resenaExistente ? ($descs[(int)$resenaExistente['estrellas']] ?? '') : '';
                ?>
            </div>
        </div>

        <!-- Comentario -->
        <label class="form-label-custom" for="comentario">
            Tu opinión <span style="color:var(--muted);font-weight:400;">(opcional · máx. 500 caracteres)</span>
        </label>
        <textarea class="form-textarea" id="comentario" maxlength="500"
                  placeholder="Cuéntanos qué aprendiste, qué te gustó o qué mejorarías..."><?= htmlspecialchars($resenaExistente['comentario'] ?? '') ?></textarea>
        <div class="char-count" id="charCount">0 / 500</div>

        <button class="btn-submit" id="btnEnviar" onclick="enviarResena()">
            <i class="bi bi-send-fill"></i>
            <?= $resenaExistente ? 'Actualizar reseña' : 'Publicar reseña' ?>
        </button>
    </div>

    <!-- ══ ESTADÍSTICAS ══ -->
    <?php if ($stats['total'] > 0): ?>
    <div class="stats-card">
        <div class="stats-card-title">
            <i class="bi bi-bar-chart-fill"></i> Calificaciones del curso
        </div>
        <div class="stats-main">
            <div style="text-align:center;flex-shrink:0;">
                <div class="stats-big-num"><?= $stats['promedio'] ?? '—' ?></div>
                <div class="stats-stars-big">
                    <?php
                    $prom = (float)($stats['promedio'] ?? 0);
                    for ($s = 1; $s <= 5; $s++):
                        if ($prom >= $s) echo '★';
                        elseif ($prom >= $s - 0.5) echo '½';
                        else echo '☆';
                    endfor;
                    ?>
                </div>
                <div class="stats-total"><?= $stats['total'] ?> reseña<?= $stats['total'] != 1 ? 's' : '' ?></div>
            </div>
            <div class="bars-wrap">
                <?php
                $barras = [5=>'e5',4=>'e4',3=>'e3',2=>'e2',1=>'e1'];
                foreach ($barras as $num => $key):
                    $cnt = (int)($stats[$key] ?? 0);
                    $pct = $stats['total'] > 0 ? round($cnt / $stats['total'] * 100) : 0;
                ?>
                <div class="bar-row">
                    <div class="bar-lbl"><?= $num ?><i class="bi bi-star-fill ms-1"></i></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="bar-cnt"><?= $cnt ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ OTRAS RESEÑAS ══ -->
    <?php if (!empty($otrasResenas)): ?>
    <div class="stats-card">
        <div class="stats-card-title">
            <i class="bi bi-chat-quote-fill"></i> Lo que dicen otros estudiantes
        </div>
        <?php foreach ($otrasResenas as $or):
            $palabras = explode(' ', $or['nombre']);
            $iniOr = strtoupper(substr($palabras[0],0,1) . (isset($palabras[1]) ? substr($palabras[1],0,1) : ''));
        ?>
        <div class="resena-item">
            <div class="ri-head">
                <div class="ri-avatar">
                    <?php if (!empty($or['avatar'])): ?>
                        <img src="<?= htmlspecialchars($or['avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= $iniOr ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="ri-nombre"><?= htmlspecialchars($or['nombre']) ?></div>
                    <div class="ri-fecha"><?= tiempoRelativo($or['created_at']) ?></div>
                </div>
                <div class="ms-auto ri-stars">
                    <?= str_repeat('★', (int)$or['estrellas']) . str_repeat('☆', 5 - (int)$or['estrellas']) ?>
                </div>
            </div>
            <?php if (!empty($or['comentario'])): ?>
                <div class="ri-comentario"><?= htmlspecialchars($or['comentario']) ?></div>
            <?php else: ?>
                <div class="ri-sin-comentario">Sin comentario adicional.</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div><!-- /resena-wrap -->

<!-- OVERLAY ÉXITO -->
<div class="exito-overlay" id="exitoOverlay">
    <div class="exito-box">
        
        <h3>¡Gracias por tu reseña!</h3>
        <p>Tu opinión ayuda a otros estudiantes a elegir el mejor curso para ellos.</p>
        <a href="index.php" class="btn-exito">
            <i class="bi bi-house-fill"></i> Ir al inicio
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
const CURSO_ID = <?= (int)$cursoId ?>;

const descs = { 1:'Muy malo', 2:'Regular', 3:'Bueno', 4:'Muy bueno', 5:'¡Excelente!' };

// ── Contador caracteres ───────────────────────────────────
const textarea  = document.getElementById('comentario');
const charCount = document.getElementById('charCount');

function updateChar() {
    const len = textarea.value.length;
    charCount.textContent = len + ' / 500';
    charCount.className = 'char-count' + (len >= 500 ? ' limit' : len >= 425 ? ' warn' : '');
}
textarea.addEventListener('input', updateChar);
updateChar();

// ── Descripción de estrellas ──────────────────────────────
document.querySelectorAll('.stars-selector input').forEach(inp => {
    inp.addEventListener('change', () => {
        document.getElementById('ratingDesc').textContent = descs[inp.value] || '';
    });
});

// ── Enviar reseña ─────────────────────────────────────────
async function enviarResena() {
    const estrellasEl = document.querySelector('.stars-selector input:checked');
    if (!estrellasEl) {
        mostrarToast('Selecciona una calificación con estrellas.', 'error');
        document.getElementById('starsSelector').style.outline = '2px solid var(--naranja)';
        return;
    }
    document.getElementById('starsSelector').style.outline = '';

    const btn = document.getElementById('btnEnviar');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Guardando...';

    const fd = new FormData();
    fd.append('curso_id',   CURSO_ID);
    fd.append('estrellas',  estrellasEl.value);
    fd.append('comentario', textarea.value.trim());

    try {
        const res  = await fetch(`${BASE_URL}/administrador/api/resenas/guardar_resena.php`, {
            method: 'POST', body: fd, credentials: 'include'
        });
        const data = await res.json();

        if (data.ok) {
            document.getElementById('exitoOverlay').classList.add('show');
        } else {
            mostrarToast(data.msg || 'Error al guardar.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill"></i> Publicar reseña';
        }
    } catch(e) {
        mostrarToast('Error de conexión.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Publicar reseña';
    }
}

// ── Toast ─────────────────────────────────────────────────
function mostrarToast(msg, tipo = 'success') {
    const viejo = document.getElementById('r-toast'); if(viejo) viejo.remove();
    const c = { success:{bg:'#219EBC',ic:'bi-check-circle-fill'}, error:{bg:'#FB8500',ic:'bi-exclamation-circle-fill'} };
    const {bg,ic} = c[tipo] ?? c.success;
    const t = document.createElement('div');
    t.id = 'r-toast';
    t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;background:${bg};color:#fff;
        padding:13px 18px;border-radius:10px;font-size:13px;font-weight:500;
        display:flex;align-items:center;gap:9px;box-shadow:0 8px 24px rgba(2,48,71,.25);
        animation:tIn .3s ease;font-family:'Poppins',sans-serif;`;
    t.innerHTML = `<i class="bi ${ic}"></i> ${msg}`;
    document.body.appendChild(t);
    if (!document.getElementById('tkf')) {
        const s = document.createElement('style'); s.id='tkf';
        s.textContent=`@keyframes tIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}`;
        document.head.appendChild(s);
    }
    setTimeout(()=>t.remove(), 3500);
}
</script>
</body>
</html>