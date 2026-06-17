<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();

// 🟢 CORREGIDO: Permitir el acceso a cualquier usuario logueado (incluyendo estudiantes)
requiereLogin();

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$rolUsuario    = usuarioRol();
$iniciales     = inicialesAvatar($nombreUsuario);
$avatarUsuario = null; 

// 🟢 AÑADIDO: Buscar el avatar real del estudiante en la base de datos
if ($uid > 0) {
    $stmtU = $conexion->prepare("SELECT avatar FROM usuarios WHERE id = ? LIMIT 1");
    $stmtU->bind_param("i", $uid);
    $stmtU->execute();
    $resU  = $stmtU->get_result()->fetch_assoc();
    if ($resU && !empty($resU['avatar']) && file_exists($resU['avatar'])) {
        $avatarUsuario = $resU['avatar'];
    }
    $stmtU->close();
}

// ── Filtros desde GET ─────────────────────────────────────
$busqueda     = trim($_GET['q']      ?? '');
$filtroEstado = trim($_GET['estado'] ?? '');
$filtroLang   = trim($_GET['lang']   ?? '');

// ── Query con filtros dinámicos ───────────────────────────
$where  = ["1=1"];
$params = [];
$types  = "";

if ($busqueda !== '') {
    $where[]  = "(fp.titulo LIKE ? OR fp.lenguajes LIKE ?)";
    $like     = "%$busqueda%";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}
if ($filtroEstado !== '') {
    $where[]  = "fp.estado = ?";
    $params[] = $filtroEstado;
    $types   .= "s";
}
if ($filtroLang !== '') {
    $where[]  = "FIND_IN_SET(?, fp.lenguajes)";
    $params[] = $filtroLang;
    $types   .= "s";
}

$whereSQL = implode(' AND ', $where);

$sql = "
    SELECT
        fp.id, fp.titulo, fp.lenguajes, fp.estado, fp.vistas, fp.created_at,
        u.nombre AS autor_nombre,
        u.avatar AS autor_avatar,
        COUNT(fr.id) AS total_respuestas
    FROM foro_preguntas fp
    JOIN   usuarios u  ON u.id = fp.usuario_id
    LEFT JOIN foro_respuestas fr ON fr.pregunta_id = fp.id
    WHERE $whereSQL
    GROUP BY fp.id, u.nombre, u.avatar
    ORDER BY fp.created_at DESC
";

$preguntas = [];
if (!empty($params)) {
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $preguntas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $res = $conexion->query($sql);
    if ($res) $preguntas = $res->fetch_all(MYSQLI_ASSOC);
}

$totalPreguntas = count($preguntas);

// ── Helpers ───────────────────────────────────────────────
function langColor(string $lang): string {
    $map = [
        'python' => '#3572A5', 'javascript' => '#f1e05a',
        'typescript' => '#2b7489', 'java' => '#b07219',
        'csharp' => '#178600', 'cpp' => '#f34b7d',
        'go' => '#00ADD8', 'rust' => '#dea584',
        'php' => '#4F5D95', 'ruby' => '#701516',
        'swift' => '#ffac45', 'kotlin' => '#A97BFF',
        'sql' => '#e38c00', 'bash' => '#89e051',
        'html' => '#ccd5ae', 'css' => '#e5989b'
    ];
    return $map[strtolower($lang)] ?? '#8ECAE6';
}

function tiempoRelativo(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 60)      return 'hace un momento';
    if ($diff < 3600)    return 'hace ' . floor($diff/60)    . ' min';
    if ($diff < 86400)   return 'hace ' . floor($diff/3600)  . 'h';
    if ($diff < 2592000) return 'hace ' . floor($diff/86400) . ' días';
    return date('d M Y', strtotime($fecha));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foro | EduTecnia</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/sforo.css">
</head>
<body>

<header class="d-flex flex-wrap justify-content-between align-items-center py-3 px-4" id="prin">
    <a href="index.php" class="d-flex align-items-center text-decoration-none">
        <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="50">
    </a>
    <ul class="nav align-items-center gap-1 mb-0">
        <li class="nav-item">
            <input type="search" id="courseSearch" placeholder="Buscar cursos...">
        </li>
        <li><a href="index.php?uid=<?= $uid ?>" class="nav-link">Inicio</a></li>
        <li><a href="index.php#cursos" class="nav-link">Cursos</a></li>
        <li><a href="foroEs.php?uid=<?= $uid ?>" class="nav-link active">Foro</a></li>
        
        
        <?php if ($nombreUsuario): ?>
            <li>
                <a href="perfil.php?uid=<?= $uid ?>" class="nav-link px-2">
                    <?php if ($avatarUsuario): ?>
                        <img src="<?= htmlspecialchars($avatarUsuario) ?>" class="user-avatar-img" alt="Avatar" style="width:35px; height:35px; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <div class="user-avatar"><?= $iniciales ?></div>
                    <?php endif; ?>
                </a>
            </li>
        <?php else: ?>
            <li>
                <a href="login.php" class="nav-link fw-semibold"
                   style="background:var(--amarillo);color:var(--marino);border-radius:10px;padding:8px 18px;">
                    Ingresar
                </a>
            </li>
        <?php endif; ?>
    </ul>
</header>

<div class="filtros-bar">
    <div class="container">
        <form method="GET" action="foroEs.php">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" name="q" class="form-control" style="max-width:280px;"
                       placeholder="Buscar por título o lenguaje..."
                       value="<?= htmlspecialchars($busqueda) ?>">

                <select name="estado" class="form-select" style="max-width:160px;">
                    <option value="">Todos los estados</option>
                    <option value="abierta"  <?= $filtroEstado === 'abierta'  ? 'selected' : '' ?>>Abierta</option>
                    <option value="resuelta" <?= $filtroEstado === 'resuelta' ? 'selected' : '' ?>>Resuelta</option>
                    <option value="cerrada"  <?= $filtroEstado === 'cerrada'  ? 'selected' : '' ?>>Cerrada</option>
                </select>

                <select name="lang" class="form-select" style="max-width:160px;">
                    <option value="">Todos los lenguajes</option>
                    <?php
                    $langs = ['python','javascript','typescript','java','csharp','cpp','go','rust','php','ruby','swift','kotlin','sql','bash'];
                    foreach ($langs as $l):
                    ?>
                    <option value="<?= $l ?>" <?= $filtroLang === $l ? 'selected' : '' ?>><?= ucfirst($l) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-filtrar">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Buscar
                </button>

                <?php if ($busqueda || $filtroEstado || $filtroLang): ?>
                <a href="foroEs.php" class="btn-limpiar">
                    <i class="fa-solid fa-xmark me-1"></i>Limpiar
                </a>
                <?php endif; ?>

                <span class="total-badge ms-auto">
                    <?= $totalPreguntas ?> resultado<?= $totalPreguntas !== 1 ? 's' : '' ?>
                </span>
            </div>
        </form>
    </div>
</div>

<div class="btn-ask text-end container mt-3">
    <a href="askEs.php" class="btn btn-primary fw-semibold px-4 py-2" style="border-radius:10px; background-color:#219EBC; border:none;">
        <i class="fa-solid fa-plus me-2"></i>Publicar Pregunta
    </a>
</div>

<div class="foro-list mt-3">
    <div class="container">

        <?php if (empty($preguntas)): ?>
        <div class="empty-foro text-center py-5">
            <i class="fa-regular fa-comments fa-3x mb-3 text-muted"></i>
            <h5>No hay preguntas<?= ($busqueda || $filtroEstado || $filtroLang) ? ' que coincidan' : ' aún' ?></h5>
            <p class="text-muted"><?= ($busqueda || $filtroEstado || $filtroLang)
                ? 'Intenta con otros filtros o términos de búsqueda.'
                : 'Sé el primero en publicar una pregunta.' ?>
            </p>
            <a href="askEs.php" class="btn btn-outline-primary mt-2">
                <i class="fa-solid fa-plus"></i> Nueva pregunta
            </a>
        </div>

        <?php else: ?>
        <?php foreach ($preguntas as $i => $p):
            $palabrasAutor   = explode(" ", $p['autor_nombre']);
            $inicialesAutor  = strtoupper(
                substr($palabrasAutor[0], 0, 1) .
                (isset($palabrasAutor[1]) ? substr($palabrasAutor[1], 0, 1) : '')
            );

            $langs = $p['lenguajes']
                ? array_filter(array_map('trim', explode(',', $p['lenguajes'])))
                : [];
        ?>
        <div class="pregunta-card p-4 mb-3 shadow-sm bg-white" style="border-radius:12px; border-left: 5px solid #219EBC;">
            <div class="d-flex align-items-center gap-3">
                <?php if (!empty($p['autor_avatar']) && file_exists($p['autor_avatar'])): ?>
                    <img src="<?= htmlspecialchars($p['autor_avatar']) ?>"
                         alt="<?= htmlspecialchars($p['autor_nombre']) ?>"
                         class="autor-avatar-img" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <div class="autor-avatar-initials d-flex align-items-center justify-content-center bg-secondary text-white fw-bold rounded-circle" style="width:40px; height:40px;">
                        <?= $inicialesAutor ?>
                    </div>
                <?php endif; ?>

                <div class="flex-grow-1">
                    <span class="autor-nombre fw-semibold"><?= htmlspecialchars($p['autor_nombre']) ?></span>
                    <span class="autor-tiempo text-muted ms-2">· <?= tiempoRelativo($p['created_at']) ?></span>
                </div>

                <span class="badge text-capitalize px-3 py-2 status-<?= $p['estado'] ?>" style="border-radius:20px; font-size:12px;">
                    <?= $p['estado'] ?>
                </span>
            </div>

            <div class="mt-3">
                <a href="respuestaEs.php?id=<?= $p['id'] ?>" class="pregunta-titulo text-decoration-none fw-bold fs-5 text-dark d-block">
                    <?= htmlspecialchars($p['titulo']) ?>
                </a>
            </div>

            <?php if (!empty($langs)): ?>
            <div class="lang-tags mt-2 d-flex gap-2 flex-wrap">
                <?php foreach ($langs as $lang): ?>
                <span class="badge px-2 py-1 text-white" style="background:<?= langColor($lang) ?>; font-size:11px;">
                    # <?= htmlspecialchars($lang) ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <hr class="card-divider my-3">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pregunta-footer">
                <div class="pregunta-stats text-muted" style="font-size:13px;">
                    <span class="stat-item me-3">
                        <i class="fa-regular fa-comment me-1"></i>
                        <?= $p['total_respuestas'] ?> respuesta<?= $p['total_respuestas'] != 1 ? 's' : '' ?>
                    </span>
                    <span class="stat-item">
                        <i class="fa-regular fa-eye me-1"></i>
                        <?= $p['vistas'] ?> vista<?= $p['vistas'] != 1 ? 's' : '' ?>
                    </span>
                </div>

                <a href="respuestaEs.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary px-3 fw-medium" style="border-radius:8px;">
                    <i class="fa-solid fa-reply me-1"></i>
                    <?= $p['total_respuestas'] > 0 ? 'Ver respuestas' : 'Responder' ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<footer>
    <div id="div-footer">
        <span>© 2026 Universidad Autónoma Metropolitana, Cuajimalpa</span>
        <div style="display:flex;gap:24px;">
            <a href="index.php"        style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Inicio</a>
            <a href="index.php#cursos" style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Cursos</a>
            <a href="foro.php"         style="font-size:13px;color:rgba(255,255,255,.5);text-decoration:none;">Foro</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>