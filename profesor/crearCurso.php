<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();

// Solo profesores pueden crear cursos
requiereRol(ROL_PROFESOR);

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$rolUsuario    = usuarioRol();
$iniciales     = inicialesAvatar($nombreUsuario);
$error         = "";

// ── Datos del usuario para el sidebar ────────────────────
$stmtU = $conexion->prepare("SELECT nombre, avatar FROM usuarios WHERE id = ?");
$stmtU->bind_param("i", $uid);
$stmtU->execute();
$usuario = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

$avatarRaw     = $usuario['avatar'] ?? '';
$avatarUsuario = '';
if (!empty($avatarRaw)) {
    if (str_starts_with($avatarRaw, 'http')) {
        $avatarUsuario = $avatarRaw;
    } else {
        $avatarUsuario = '../' . ltrim($avatarRaw, '/');
    }
}

// ── Verifica que el curso existe y pertenece al profesor ──
$cursoId = (int)($_GET["id"] ?? 0);

if (!$cursoId) {
    header("Location: dashboard.php");
    exit;
}


$stmtCurso = $conexion->prepare("SELECT id, titulo FROM cursos WHERE id = ? AND profesor_id = ?");
$stmtCurso->bind_param("ii", $cursoId, $uid);
$stmtCurso->execute();
$curso = $stmtCurso->get_result()->fetch_assoc();

if (!$curso) {
    header("Location: dashboard.php");
    exit;
}

// ── Carga módulos y lecciones existentes desde la BD ─────
$modulosExistentes = [];

$qMods = $conexion->prepare("SELECT id, titulo FROM modulos WHERE curso_id = ? ORDER BY orden ASC");
$qMods->bind_param("i", $cursoId);
$qMods->execute();
$resultMods = $qMods->get_result();

while ($mod = $resultMods->fetch_assoc()) {
    $modulo = [
        'id'        => (int)$mod['id'],
        'titulo'    => $mod['titulo'],
        'abierto'   => true,
        'lecciones' => []
    ];

    $qLecs = $conexion->prepare("
        SELECT id, titulo, tipo, url, descripcion, codigo_base, lenguaje
        FROM lecciones WHERE modulo_id = ? ORDER BY orden ASC
    ");
    $qLecs->bind_param("i", $mod['id']);
    $qLecs->execute();
    $resultLecs = $qLecs->get_result();

    while ($lec = $resultLecs->fetch_assoc()) {
        $modulo['lecciones'][] = [
            'id'          => (int)$lec['id'],
            'moduloId'    => (int)$mod['id'],
            'titulo'      => $lec['titulo'],
            'tipo'        => $lec['tipo'],
            'url'         => $lec['url']         ?? '',
            'descripcion' => $lec['descripcion'] ?? '',
            'codigo_base' => $lec['codigo_base'] ?? '',
            'lenguaje'    => $lec['lenguaje']    ?? '',
        ];
    }

    $modulosExistentes[] = $modulo;
}

// ── Recibe POST (JSON) del JS ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = file_get_contents('php://input');
    $datos = json_decode($input, true);

    if (!$datos || empty($datos['modulos'])) {
        echo json_encode(["status" => "error", "message" => "Datos incompletos"]);
        exit;
    }

    $conexion->begin_transaction();

    try {
        // CORREGIDO: Uso de sentencia preparada para evitar inyecciones lógicas
        $stmtDel = $conexion->prepare("DELETE FROM modulos WHERE curso_id = ?");
        $stmtDel->bind_param("i", $cursoId);
        $stmtDel->execute();
        $stmtDel->close();

        foreach ($datos['modulos'] as $iMod => $modulo) {
            $stmtMod = $conexion->prepare("INSERT INTO modulos (curso_id, titulo, orden) VALUES (?, ?, ?)");
            $ordenMod = $iMod + 1;
            $stmtMod->bind_param("isi", $cursoId, $modulo['titulo'], $ordenMod);
            $stmtMod->execute();
            $moduloId = $conexion->insert_id;
            $stmtMod->close();

            foreach ($modulo['lecciones'] as $iLec => $leccion) {
                $stmtLec = $conexion->prepare("
                    INSERT INTO lecciones (modulo_id, titulo, tipo, descripcion, url, codigo_base, lenguaje, orden)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ordenLec    = $iLec + 1;
                $codigoBase  = $leccion['codigo_base'] ?? '';
                $lenguaje    = $leccion['lenguaje']    ?? '';

                $stmtLec->bind_param("issssssi",
                    $moduloId,
                    $leccion['titulo'],
                    $leccion['tipo'],
                    $leccion['descripcion'],
                    $leccion['url'],
                    $codigoBase,
                    $lenguaje,
                    $ordenLec
                );
                $stmtLec->execute();
                $stmtLec->close();
            }
        }

        $conexion->commit();
        echo json_encode(["status" => "success", "message" => "Curso guardado correctamente"]);

    } catch (Exception $e) {
        $conexion->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Constructor — <?= htmlspecialchars($curso['titulo']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styleAdministrador.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <style>
        /* ── Vista previa: sidebar lecciones ── */
        .previo-modulo-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            user-select: none;
        }
        .previo-modulo-header:hover { background: #f0f2f5; }
        .previo-modulo-titulo { font-size: 12px; font-weight: 600; color: #3a3a5c; text-transform: uppercase; letter-spacing: .5px; flex: 1; }
        .previo-leccion-item { display: flex; align-items: center; gap: 10px; padding: 9px 16px 9px 24px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background .15s; }
        .previo-leccion-item:hover { background: #f0f4ff; }
        .previo-leccion-item.activa { background: #eef2ff; border-left: 3px solid #4f46e5; }
        .previo-leccion-titulo { font-size: 13px; color: #374151; flex: 1; line-height: 1.3; }
        .previo-leccion-tipo-badge { font-size: 10px; padding: 2px 7px; border-radius: 20px; font-weight: 500; }
        .badge-video { background: #dbeafe; color: #1d4ed8; }
        .badge-practica { background: #dcfce7; color: #15803d; }
        .badge-texto { background: #fef9c3; color: #854d0e; }
        .badge-documento { background: #fce7f3; color: #9d174d; }
        .previo-content-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 400px; color: #9ca3af; gap: 12px; }
        .previo-video-wrapper { position: relative; padding-bottom: 56.25%; height: 0; border-radius: 12px; overflow: hidden; background: #000; margin-bottom: 20px; }
        .previo-video-wrapper iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
        .previo-code-block { background: #1e1e2e; color: #e2e8f0; border-radius: 10px; padding: 16px 18px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; overflow-x: auto; margin-bottom: 16px; }
        .previo-code-header { display: flex; align-items: center; justify-content: space-between; background: #12121f; border-radius: 10px 10px 0 0; padding: 8px 14px; }
        .previo-code-wrapper { border-radius: 10px; overflow: hidden; margin-bottom: 20px; }
        .previo-code-wrapper .previo-code-block { border-radius: 0 0 10px 10px; }
        .previo-doc-card { display: flex; align-items: center; gap: 14px; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px 18px; background: #fff; margin-bottom: 16px; }
        .previo-progress-bar-wrap { background: #e5e7eb; border-radius: 20px; height: 6px; overflow: hidden; margin-top: 4px; }
        .previo-progress-bar-fill { height: 100%; background: linear-gradient(90deg, #4f46e5, #818cf8); border-radius: 20px; transition: width .4s; }
        #doc-dropzone.drag-over { border-color: #1E4978 !important; background: #eff6ff !important; }
        #doc-dropzone:hover { border-color: #adb5bd; background: #f5f5f5; }
    </style>
</head>
<body>
<div class="layout">
      <aside class="sidebar">
            <div class="sidebar-logo">
                <img src="../img/logoEduTecnia.png" alt="EduTecnia">
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-label">Principal</div>
                <a href="dashboard.php?uid=<?= $uid ?>" class="nav-link-item active" >
                    <i class="bi bi-grid-1x2"></i> Dashboard
                </a>
                <a href="dashboard.php?uid=<?= $uid ?>#cursos" class="nav-link-item">
                    <i class="bi bi-collection-play" ></i> Mis Cursos
                </a>
                <a href="foro.php?uid=<?= $uid ?>" class="nav-link-item">
                    <i class="bi bi-question-circle"></i> Foro
                </a>
                <a href="estudiantes.php?uid=<?= $uid ?>" class="nav-link-item">
                    <i class="bi bi-person-square"></i> Estudiantes
                </a>

                <div class="nav-section-label">Sistema</div>
                <a href="perfilprof.php" class="nav-link-item ">
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

    <!-- <aside class="sidebar">
        <a href="#" class="sidebar-brand">
            <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="50">
        </a>
        <span class="sidebar-section-label">Principal</span>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>
            <li><a href="dashboard.php"><i class="bi bi-collection-play"></i> Mis Cursos</a></li>
        </ul>
        <span class="sidebar-section-label">Gestión</span>
        <ul class="sidebar-nav">
            <li><a href="curso.php"><i class="bi bi-plus-circle"></i> Nuevo Curso</a></li>
        </ul>
        <span class="sidebar-section-label">Sistema</span>
        <ul class="sidebar-nav">
            <li><a href="perfil.php"><i class="bi bi-gear"></i> Configuración</a></li>
            <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
        </ul>
        <div class="sidebar-user">
            <div class="user-avatar"><?= $iniciales ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($nombreUsuario) ?></div>
                <div class="user-role"><?= htmlspecialchars($rolUsuario) ?></div>
            </div>
        </div>
    </aside> -->

    <div class="main">

        <div class="topbar">
            <div>
                <span class="topbar-title">Constructor de Lecciones</span>
                <div style="font-size:12px; color:#888; margin-top:2px;">
                    📘 <?= htmlspecialchars($curso['titulo']) ?>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="btn-new" onclick="guardarCurso()">
                    <i class="bi bi-floppy-fill"></i> Guardar
                </button>
                <a href="dashboard.php" class="btn-new">
                    <i class="bi bi-house-fill"></i> Regresar
                </a>
            </div>
        </div>

        <div class="content">
            <main class="px-md-4 py-4">
                <div class="row">

                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4 text-secondary">Configuración de la Lección</h5>

                                <div class="mb-3">
                                    <label class="form-label">Título de la lección</label>
                                    <input type="text" id="lessonTitle" class="form-control" placeholder="Ej: Introducción a Arrays" required>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Tipo de Contenido</label>
                                    <div class="row g-2">
                                        <div class="col-md-4">
                                            <input type="radio" class="btn-check" name="contentType" id="typeVideo" value="video" checked>
                                            <label class="btn btn-outline-primary w-100 p-3" for="typeVideo">
                                                <i class="bi bi-camera-video-fill"></i> Video
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="radio" class="btn-check" name="contentType" id="typeCode" value="practica">
                                            <label class="btn btn-outline-primary w-100 p-3" for="typeCode">
                                                <i class="bi bi-file-earmark-code"></i> Práctica
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="radio" class="btn-check" name="contentType" id="typeText" value="texto">
                                            <label class="btn btn-outline-primary w-100 p-3" for="typeText">
                                                <i class="bi bi-fonts"></i> Texto
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="radio" class="btn-check" name="contentType" id="typeDoc" value="documento">
                                            <label class="btn btn-outline-primary w-100 p-3" for="typeDoc">
                                                <i class="bi bi-file-earmark-fill"></i> Documentos
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div id="dynamicFields"></div>

                                <div class="mb-3">
                                    <label class="form-label">Descripción / Especificaciones</label>
                                    <div id="editor"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm sticky-top" style="top:20px;">
                            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold">Estructura del Curso</h6>
                                <button class="btn btn-sm btn-primary" onclick="nuevoModulo()">
                                    <i class="bi bi-plus-lg"></i> Módulo
                                </button>
                            </div>
                            <div class="card-body p-0" id="modulosContainer"></div>
                        </div>

                        <div class="mt-3 px-3 pb-3">
                            <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#modalVistaPrevia">
                                <i class="bi bi-eye-fill me-1"></i> Vista previa del estudiante
                            </button>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVistaPrevia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

            <div class="modal-header border-0 px-4 py-3" style="background: linear-gradient(135deg, #1e1e2e 0%, #2d2d44 100%);">
                <div class="d-flex align-items-center gap-3">
                    <div style="width:38px;height:38px;border-radius:10px;background:rgba(255,255,255,.12); display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-mortarboard-fill text-white fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 fw-semibold" id="previoTituloCurso">
                            Vista previa del curso
                        </h5>
                        <small class="text-white-50">Así verá el contenido el estudiante</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0" style="background:#f4f6fb; min-height:520px;">
                <div class="row g-0 h-100">

                    <div class="col-lg-4 border-end bg-white" style="min-height:520px;">
                        <div class="p-3 border-bottom" style="background: linear-gradient(135deg,#1e1e2e,#2d2d44);">
                            <p class="text-white-50 mb-1" style="font-size:11px;text-transform:uppercase; letter-spacing:.8px;">Contenido del curso</p>
                            <div id="previoContadorLecciones" class="text-white fw-semibold" style="font-size:13px;"></div>
                        </div>
                        <div id="previoSidebar" class="overflow-auto" style="max-height:460px;"></div>
                    </div>

                    <div class="col-lg-8 p-4" id="previoContenido">
                        </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
    const CURSO_ID  = <?= $cursoId ?>;
    const MODULOS_BD = <?= json_encode($modulosExistentes, JSON_UNESCAPED_UNICODE) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/screarCurso.js"></script>
</body>
</html>