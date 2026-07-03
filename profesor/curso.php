<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();

// Solo profesores pueden crear cursos
requiereRol(ROL_PROFESOR);







$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$iniciales     = inicialesAvatar($nombreUsuario);
$error      = "";
$stmtU = $conexion->prepare("SELECT nombre, correo, avatar FROM usuarios WHERE id = ?");
$stmtU->bind_param("i", $uid);
$stmtU->execute();
$usuario = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

// Resolver URL del avatar (puede ser ruta relativa o URL absoluta)
$avatarRaw     = $usuario['avatar'] ?? '';
$avatarUsuario = '';
if (!empty($avatarRaw)) {
    if (str_starts_with($avatarRaw, 'http')) {
        $avatarUsuario = $avatarRaw;                     // URL absoluta
    } else {
        $avatarUsuario = '../' . ltrim($avatarRaw, '/'); // ruta relativa desde /profesor/
    }
}


// ── Modo editar o crear ───────────────────────────────────
$cursoId    = (int)($_GET["id"] ?? 0);
$modoEditar = $cursoId > 0;
$curso      = null;

if ($modoEditar) {
    $stmt = $conexion->prepare("SELECT * FROM cursos WHERE id = ? AND profesor_id = ?");
    $stmt->bind_param("ii", $cursoId, $uid);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc();

    if (!$curso) {
        header("Location: dashboard.php");
        exit;
    }
}

// ── Procesar formulario ───────────────────────────────────
if (!empty($_POST["btn-crear"])) {
    $titulo      = trim($_POST["titulo"]          ?? '');
    $categoria   = trim($_POST["categoria"]       ?? '');
    $descripcion = trim($_POST["descripcion"]     ?? '');
    $estado      = trim($_POST["estado"]          ?? 'borrador');
    $duracion    = (int)($_POST["duracion_total"] ?? 0);

    // ── Manejo de imagen ──────────────────────────────────
    $imagen = $curso['imagen'] ?? null; // conserva la anterior si no sube nueva

    if (!empty($_FILES["imagen"]["name"])) {
        $ext        = pathinfo($_FILES["imagen"]["name"], PATHINFO_EXTENSION);
        $permitidos = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array(strtolower($ext), $permitidos)) {
            $error = "Solo se permiten imágenes JPG, PNG o WEBP.";
        } else {
            $nombreArchivo = uniqid("curso_") . "." . $ext;
            $ruta          = "uploads/cursos/" . $nombreArchivo;
            if (!is_dir("uploads/cursos/")) mkdir("uploads/cursos/", 0755, true);
            move_uploaded_file($_FILES["imagen"]["tmp_name"], $ruta);
            $imagen = $ruta;
        }
    }

    // ── Validaciones ──────────────────────────────────────
    if (empty($titulo) || empty($categoria)) {
        $error = "El título y la categoría son obligatorios.";

    } elseif (empty($error)) {

        if ($modoEditar) {
            // UPDATE
            // Tipos: s=titulo, s=descripcion, s=categoria, s=imagen, s=estado, i=duracion, i=cursoId, i=idProfesor
            $stmt = $conexion->prepare("
                UPDATE cursos
                SET titulo=?, descripcion=?, categoria=?, imagen=?, estado=?, duracion_total=?
                WHERE id=? AND profesor_id=?
            ");
            $stmt->bind_param("sssssiii",
                $titulo, $descripcion, $categoria,
                $imagen, $estado, $duracion,
                $cursoId, $uid
            );

            if ($stmt->execute()) {
                header("Location: crearCurso.php?id=$cursoId");
                exit;
            } else {
                $error = "Error al actualizar: " . $stmt->error;
            }

        } else {
            // INSERT
            // Tipos: i=profesor_id, s=titulo, s=descripcion, s=categoria, s=imagen, s=estado, i=duracion
            $stmt = $conexion->prepare("
                INSERT INTO cursos (profesor_id, titulo, descripcion, categoria, imagen, estado, duracion_total)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssssi",
                $uid, $titulo, $descripcion,
                $categoria, $imagen, $estado, $duracion
            );

            if ($stmt->execute()) {
                $cursoId = $conexion->insert_id;
                header("Location: crearCurso.php?id=$cursoId");
                exit;
            } else {
                $error = "Error al crear: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Valores actuales para pre-llenar el formulario
$valTitulo      = htmlspecialchars($curso['titulo']      ?? '');
$valDescripcion = htmlspecialchars($curso['descripcion'] ?? '');
$valCategoria   = $curso['categoria']    ?? '';
$valEstado      = $curso['estado']       ?? 'borrador';
$valDuracion    = $curso['duracion_total'] ?? 0;
$valImagen      = $curso['imagen']       ?? '';

$categorias = [
    'Desarrollo web', 'Redes', 'Ciberseguridad',
    'Inteligencia Artificial', 'Bases de Datos',
    'Aplicaciones', 'Estructura', 'Tecnología', 'Programación',  'Otro'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $modoEditar ? 'Editar Curso' : 'Nuevo Curso' ?> — EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/styleAdministrador.css">
    <link rel="stylesheet" href="../css/sCurso.css">
</head>
<body>
<div class="layout">

    <!-- ── SIDEBAR ── -->
      <aside class="sidebar">
            <div class="sidebar-logo" href="dashboard.php">
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

    <!-- ── MAIN ── -->
    <div class="main">

        <!-- TOPBAR -->
        <div class="topbar">
            <span class="topbar-title">
                <?= $modoEditar ? 'Editar Curso' : 'Nuevo Curso' ?>
            </span>
            <div class="topbar-actions">
                <a href="dashboard.php" class="btn-new">
                    <i class="bi bi-arrow-left"></i> Regresar
                </a>
            </div>
        </div>

        <div class="content">
            <div style="max-width: 900px; margin: 0 auto; padding: 24px 0;">

                <!-- Error -->
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <div class="row g-4">

                        <!-- ── COLUMNA IZQUIERDA ── -->
                        <div class="col-lg-8">
                            <div class="section-card">
                                <div class="section-card-title">
                                    <i class="bi bi-info-circle"></i> Identificación del Curso
                                </div>

                                <!-- Título -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Título del curso <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="titulo" class="form-control"
                                        placeholder="Ej: Introducción a Python desde cero"
                                        value="<?= $valTitulo ?>"
                                        maxlength="200" required>
                                </div>

                                <!-- Categoría -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">
                                        Categoría <span class="text-danger">*</span>
                                    </label>
                                    <select name="categoria" class="form-select" required>
                                        <option value="" disabled <?= empty($valCategoria) ? 'selected' : '' ?>>
                                            Selecciona una categoría
                                        </option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?= $cat ?>"
                                                <?= $valCategoria === $cat ? 'selected' : '' ?>>
                                                <?= $cat ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Descripción -->
                                <div class="mb-0">
                                    <label class="form-label fw-semibold">Descripción</label>
                                    <textarea name="descripcion" class="form-control" rows="4"
                                        placeholder="¿De qué trata el curso? ¿Qué aprenderán los estudiantes?"
                                        maxlength="500"><?= $valDescripcion ?></textarea>
                                    <div class="form-text">Máximo 500 caracteres.</div>
                                </div>
                            </div>
                        </div>

                        <!-- ── COLUMNA DERECHA ── -->
                        <div class="col-lg-4">

                            <!-- Tarjeta imagen -->
                            <div class="section-card">
                                <div class="section-card-title">
                                    <i class="bi bi-image"></i> Imagen de portada
                                </div>
                                <div class="img-preview" id="imgPreview"
                                    onclick="document.getElementById('inputImagen').click()">

                                    <?php if (!empty($valImagen) && file_exists($valImagen)): ?>
                                        <!-- ✅ Imagen actual del curso -->
                                        <img id="imgActual"
                                            src="<?= htmlspecialchars($valImagen) ?>"
                                            alt="Portada del curso"
                                            style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>
                                        <!-- Placeholder cuando no hay imagen -->
                                        <div class="img-preview-placeholder" id="imgPlaceholder">
                                            <i class="bi bi-cloud-arrow-up"></i>
                                            <span>Clic para subir imagen</span>
                                            <small>JPG, PNG o WEBP</small>
                                        </div>
                                    <?php endif; ?>

                                </div>
                                <input type="file" name="imagen" id="inputImagen"
                                    accept=".jpg,.jpeg,.png,.webp" class="d-none">
                            </div>

                            <!-- Tarjeta configuración -->
                            <div class="section-card">
                                <div class="section-card-title">
                                    <i class="bi bi-gear"></i> Configuración
                                </div>

                                <!-- Estado -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold d-block mb-2">Estado</label>
                                    <div class="estado-toggle">
                                        <input type="radio" name="estado" id="est-borrador"
                                            value="borrador"
                                            <?= $valEstado === 'borrador' ? 'checked' : '' ?>>
                                        <label for="est-borrador" class="borrador-lbl">
                                            ✏️ Borrador
                                        </label>
                                        <input type="radio" name="estado" id="est-publicado"
                                            value="publicado"
                                            <?= $valEstado === 'publicado' ? 'checked' : '' ?>>
                                        <label for="est-publicado" class="publicado-lbl">
                                            ✅ Publicado
                                        </label>
                                    </div>
                                    <div class="form-text mt-2" id="estadoHelp">
                                        <?= $valEstado === 'publicado'
                                            ? 'Visible para todos los estudiantes.'
                                            : 'Solo visible para ti mientras editas.' ?>
                                    </div>
                                </div>

                                <!-- Duración -->
                                <div class="mb-0">
                                    <label class="form-label fw-semibold d-block mb-2">
                                        Duración estimada
                                    </label>
                                    <div class="duracion-input">
                                        <button type="button" class="duracion-btn"
                                            onclick="cambiarDuracion(-5)">
                                            <i class="bi bi-dash"></i>
                                        </button>
                      
                                        <input type="number" name="duracion_total" id="duracionInput"
                                            class="form-control text-center fw-bold"
                                            value="<?= $valDuracion ?>"
                                            min="0" max="9999"
                                            style="width:90px; font-size:18px;">
                                        <button type="button" class="duracion-btn"
                                            onclick="cambiarDuracion(5)">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                        <span class="text-muted" style="font-size:13px;">min</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div><!-- fin .row -->

                    <!-- Botones -->
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <a href="dashboard.php" class="btn-cancelar">Cancelar</a>
                        <button type="submit" name="btn-crear" value="ok" class="btn-continuar">
                            <?php if ($modoEditar): ?>
                                <i class="bi bi-floppy-fill"></i> Guardar cambios
                            <?php else: ?>
                                Continuar — Añadir lecciones
                                <i class="bi bi-arrow-right-circle-fill"></i>
                            <?php endif; ?>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Preview de imagen ─────────────────────────────────────
document.getElementById('inputImagen').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
        const preview     = document.getElementById('imgPreview');
        const placeholder = document.getElementById('imgPlaceholder');
        const imgActual   = document.getElementById('imgActual');

        // Oculta placeholder e imagen anterior
        if (placeholder) placeholder.style.display = 'none';
        if (imgActual)   imgActual.style.display   = 'none';

        // Quita preview anterior si ya había uno nuevo
        const prevNueva = preview.querySelector('img.nueva-img');
        if (prevNueva) prevNueva.remove();

        // Muestra la nueva imagen seleccionada
        const img     = document.createElement('img');
        img.src       = e.target.result;
        img.className = 'nueva-img';
        img.style     = 'width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;';
        preview.appendChild(img);
    };
    reader.readAsDataURL(file);
});

// ── Control de duración ───────────────────────────────────
function cambiarDuracion(delta) {
    const input = document.getElementById('duracionInput');
    input.value = Math.max(0, parseInt(input.value || 0) + delta);
}

// ── Texto de ayuda del estado ─────────────────────────────
document.querySelectorAll('input[name="estado"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const help = document.getElementById('estadoHelp');
        if (radio.value === 'publicado') {
            help.textContent   = 'Visible para todos los estudiantes.';
            help.style.color   = '#3a9a9f';
        } else {
            help.textContent   = 'Solo visible para ti mientras editas.';
            help.style.color   = '';
        }
    });
});
</script>
</body>
</html>