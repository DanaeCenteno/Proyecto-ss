<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();
requiereRol(ROL_ESTUDIANTE); // Solo estudiantes

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$rolUsuario    = usuarioRol();
$iniciales     = inicialesAvatar($nombreUsuario);
$avatarUsuario = null;

// Cargar avatar corregido
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
        // Subimos un nivel desde la carpeta estudiante/ para buscar en la raíz física
        if (file_exists(__DIR__ . '/../' . $rutaLimpia)) {
            $avatarUsuario = '../' . $rutaLimpia;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Pregunta | EduTecnia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/sAsk.css">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
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
        <div class="ask-wrapper">

            <div class="btn-ask text-end container mt-3">
                <a href="foroEs.php?uid=<?= $uid ?>" class="btn btn-primary fw-semibold px-4 py-2 mb-2"
                    style="border-radius:10px; background-color:#219EBC; border:none;">
                    <i class="bi bi-arrow-left-short"></i>Regresar
                </a>
            </div>

            <div class="ask-header">
                <h1><i class="fa-solid fa-circle-question text-primary me-2"></i>Crear nueva pregunta</h1>
                <p>Comparte tu duda con la comunidad EduTecnia. Cuanto más detalle incluyas, mejores respuestas
                    obtendrás.</p>
            </div>

            <div class="row g-4">

                <!-- Main Form -->
                <div class="col-lg-8">
                    <div class="ask-card">

                        <!-- Título -->
                        <div class="mb-4">
                            <label class="form-label" for="ask-title">
                                Título de la pregunta <span class="field-required">*</span>
                            </label>
                            <input type="text" class="form-control" id="ask-title"
                                placeholder="Ej: ¿Cómo manejar excepciones en Python con try/except?" maxlength="150"
                                autocomplete="off">
                            <div class="invalid-feedback">El título es obligatorio y debe contener al menos 10
                                caracteres.</div>
                        </div>

                        <!-- Lenguajes -->
                        <div class="mb-4">
                            <label class="form-label">Lenguaje(s) de programación</label>
                            <div class="lang-dropdown-wrap" id="lang-dropdown-wrap">
                                <button type="button" class="lang-trigger" id="lang-trigger"
                                    onclick="toggleLangDropdown()">
                                    <span id="lang-trigger-text">— Selecciona uno o más lenguajes —</span>
                                    <i class="fa-solid fa-chevron-down lang-chevron" id="lang-chevron"></i>
                                </button>
                                <div id="lang-panel" class="lang-panel">
                                    <div class="d-flex flex-wrap gap-2">
                                        <label class="lang-option"><input type="checkbox" value="html"
                                                onchange="toggleLanguage(this.value)"> HTML</label>
                                        <label class="lang-option"><input type="checkbox" value="css"
                                                onchange="toggleLanguage(this.value)"> CSS</label>
                                        <label class="lang-option"><input type="checkbox" value="javascript"
                                                onchange="toggleLanguage(this.value)"> JavaScript</label>
                                        <label class="lang-option"><input type="checkbox" value="php"
                                                onchange="toggleLanguage(this.value)"> PHP</label>
                                        <label class="lang-option"><input type="checkbox" value="python"
                                                onchange="toggleLanguage(this.value)"> Python</label>
                                        <label class="lang-option"><input type="checkbox" value="sql"
                                                onchange="toggleLanguage(this.value)"> SQL</label>
                                        <label class="lang-option"><input type="checkbox" value="java"
                                                onchange="toggleLanguage(this.value)"> Java</label>
                                        <label class="lang-option"><input type="checkbox" value="csharp"
                                                onchange="toggleLanguage(this.value)"> C#</label>
                                        <label class="lang-option"><input type="checkbox" value="cpp"
                                                onchange="toggleLanguage(this.value)"> C++</label>
                                        <label class="lang-option"><input type="checkbox" value="typescript"
                                                onchange="toggleLanguage(this.value)"> TypeScript</label>
                                        <label class="lang-option"><input type="checkbox" value="go"
                                                onchange="toggleLanguage(this.value)"> Go</label>
                                        <label class="lang-option"><input type="checkbox" value="rust"
                                                onchange="toggleLanguage(this.value)"> Rust</label>
                                        <label class="lang-option"><input type="checkbox" value="kotlin"
                                                onchange="toggleLanguage(this.value)"> Kotlin</label>
                                        <label class="lang-option"><input type="checkbox" value="swift"
                                                onchange="toggleLanguage(this.value)"> Swift</label>

                                        <label class="lang-option"><input type="checkbox" value="otro" id="cb-otro"
                                                onchange="checkOtro(this)"> Otro</label>
                                    </div>
                                    <div class="d-flex gap-2 mt-2">
                                        <input type="text" id="lang-otro-input" class="form-control form-control-sm"
                                            placeholder="¿Cuál?" style="display:none; max-width:150px;">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="addOtro()"
                                            style="display:none;" id="btn-add-otro">Agregar</button>
                                    </div>
                                </div>
                            </div>
                            <div class="lang-chips" id="lang-chips"></div>
                            <span class="invalid-msg" id="err-lang">Selecciona al menos un lenguaje.</span>
                        </div>

                        <hr class="ask-divider">

                        <!-- Contenido Quill -->
                        <div class="mb-4">
                            <label class="form-label">
                                Descripción del problema <span class="field-required">*</span>
                            </label>
                            <div id="ask-editor"></div>
                            <span class="invalid-msg" id="err-contenido">Describe tu pregunta antes de publicar.</span>
                            <p class="form-hint mt-2">
                                <i class="fa-solid fa-circle-info me-1"></i>
                                Incluye el código relevante, el error que obtienes y lo que ya intentaste.
                            </p>
                        </div>

                        <hr class="ask-divider">

                        <!-- Acciones -->
                        <div class="ask-actions">
                            <button class="btn-preview-ask" type="button" onclick="abrirPreview()">
                                <i class="fa-regular fa-eye"></i> Vista previa
                            </button>
                            <div class="actions-right">
                                <button class="btn-reset-ask" type="button" onclick="resetForm()">
                                    <i class="fa-solid fa-rotate-left me-1"></i>Reiniciar
                                </button>
                                <button class="btn-cancel-ask" type="button" onclick="cancelar()">Cancelar</button>
                                <button class="btn btn-primary px-4 fw-semibold" type="button" onclick="publicar()">
                                    <i class="fa-solid fa-paper-plane me-2"></i>Publicar pregunta
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="tips-card">
                        <h6><i class="fa-solid fa-lightbulb"></i> Consejos para una buena pregunta</h6>
                        <ul>
                            <li>Escribe un título claro y específico.</li>
                            <li>Describe el contexto del problema.</li>
                            <li>Incluye el código que no funciona.</li>
                            <li>Menciona el error exacto que ves.</li>
                            <li>Explica qué soluciones ya intentaste.</li>
                            <li>Selecciona el lenguaje correcto.</li>
                        </ul>
                    </div>
                    <div class="conduct-card">
                        <i class="fa-solid fa-shield-halved me-1"></i>
                        Recuerda respetar el <a href="#">código de conducta</a> de la comunidad.
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="preview-backdrop" id="preview-backdrop" onclick="backdropClick(event)">
        <div class="preview-modal" id="preview-modal-box">
            <div class="preview-header">
                <div class="d-flex align-items-center gap-2">
                    <h5>Vista previa</h5>
                    <span class="badge-preview">Solo tú puedes ver esto</span>
                </div>
                <button class="btn-close-preview" onclick="cerrarPreview()" title="Cerrar">&#10005;</button>
            </div>
            <div class="preview-body">
                <div id="preview-content-wrap"></div>
            </div>
            <div class="preview-footer">
                <button class="btn-cancel-ask" onclick="cerrarPreview()">Cerrar</button>
                <button class="btn btn-primary px-4 fw-semibold" onclick="cerrarPreview(); publicar()">
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

    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- askj.js reutilizado, solo sobreescribimos cancelar() y publicar() -->
    <script id="script-pregunta" src="<?= BASE_URL ?>/js/askj.js" data-base-url="<?= BASE_URL ?>"></script>
    <script>
    // ── Sobreescribir funciones que difieren del flujo profesor ──

    // Cancelar → foro del estudiante
    function cancelar() {
        window.location.href = '<?= BASE_URL ?>/estudiante/foroEs.php?uid=<?= $uid ?>';
    }

    function backdropClick(e) {
        if (e.target === document.getElementById('preview-backdrop')) cerrarPreview();
    }

    function resetForm() {
        const tituloInput = document.getElementById('ask-title');
        if (tituloInput) {
            tituloInput.value = '';
            tituloInput.classList.remove('is-invalid', 'is-valid');
        }
        if (typeof quill !== 'undefined') quill.setContents([]);
        if (typeof selectedLanguages !== 'undefined') selectedLanguages.length = 0;
        const chips = document.getElementById('lang-chips');
        if (chips) chips.innerHTML = '';
        const triggerText = document.getElementById('lang-trigger-text');
        if (triggerText) triggerText.textContent = '— Selecciona uno o más lenguajes —';
        document.querySelectorAll('.lang-option input[type="checkbox"]').forEach(cb => cb.checked = false);
        const otroInput = document.getElementById('lang-otro-input');
        if (otroInput) {
            otroInput.value = '';
            otroInput.style.display = 'none';
        }
    }

    // Publicar → mismo endpoint, redirige al foro del estudiante
    function publicar() {
        if (!validarFormulario()) {
            mostrarToast("Por favor, llena los campos requeridos correctamente.", "error");
            return;
        }

        const tituloInput = document.getElementById('ask-title');
        const cursoSelect = document.getElementById('ask-course');
        const contenidoHTML = quill.root.innerHTML;

        const fd = new FormData();
        fd.append('titulo', tituloInput.value.trim());
        fd.append('curso_id', cursoSelect ? cursoSelect.value : 0);
        fd.append('contenido', contenidoHTML.trim());
        fd.append('lenguajes', selectedLanguages.join(','));

        fetch('<?= BASE_URL ?>/administrador/api/foro/guardar_pregunta.php', {
                method: 'POST',
                body: fd,
                credentials: 'include'
            })
            .then(res => res.text())
            .then(texto => {
                let data;
                try {
                    data = JSON.parse(texto);
                } catch (_) {
                    console.error('Respuesta inesperada:', texto);
                    mostrarToast('Error interno del servidor.', 'error');
                    return;
                }
                if (data.ok) {
                    mostrarToast('¡Pregunta publicada con éxito!', 'success');
                    setTimeout(() => {
                        // ← redirige al foro del estudiante
                        window.location.href = '<?= BASE_URL ?>/estudiante/foroEs.php?uid=<?= $uid ?>';
                    }, 1500);
                } else {
                    mostrarToast(data.msg || 'Error al procesar la solicitud.', 'error');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                mostrarToast('Error de comunicación con el servidor.', 'error');
            });
    }
    </script>

</body>

</html>