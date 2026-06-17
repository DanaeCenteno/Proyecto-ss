<?php
require_once __DIR__ . '/../administrador/config/db.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesion();
requiereLogin();

$uid           = usuarioId();
$nombreUsuario = usuarioNombre();
$rolUsuario    = usuarioRol();
$iniciales     = inicialesAvatar($nombreUsuario);
$avatarUsuario = null; // si no usas avatares por ahora

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $titulo    = trim($_POST['titulo']    ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $cursoId   = (int)($_POST['curso_id'] ?? 0);

    if (empty($titulo) || empty($contenido)) {
        $error = 'El título y el contenido son obligatorios.';
    } else {
        $stmt = $conexion->prepare("
            INSERT INTO foro_preguntas (usuario_id, curso_id, titulo, contenido)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param('iiss', $uid, $cursoId, $titulo, $contenido);

        if ($stmt->execute()) {
            $nuevaId = $conexion->insert_id;
            header("Location: foro.php?uid=$uid");
            exit;
        } else {
            $error = 'Error al publicar la pregunta.';
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Foro | EduTecnia</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../css/sAsk.css">
  <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
</head>

<body>
<!-- ══ NAVBAR ══════════════════════════════════════════════ -->
<header class="d-flex flex-wrap justify-content-between align-items-center py-3 px-4" id="prin">
    <a href="dashboard.php" class="d-flex align-items-center text-decoration-none">
        <img src="../img/logoEduTecnia.png" alt="EduTecnia" height="50">
    </a>
    <ul class="nav align-items-center gap-1 mb-0">
        <li class="nav-item">
            <input type="search" id="courseSearch" placeholder="Buscar cursos...">
        </li>
        <li><a href="dashboard.php?uid=<?= $uid ?>" class="nav-link active">Inicio</a></li>
        <li><a href="dashboard.php?uid=<?= $uid ?>" class="nav-link">Cursos</a></li>
        <li><a href="foro.php?uid=<?= $uid ?>" class="nav-link">Foro</a></li>
        <li><a href="#" class="nav-link">Blog</a></li>
        <?php if ($nombreUsuario): ?>
            <li>
                <a href="perfil.php" class="nav-link px-2">
                    <?php if ($avatarUsuario): ?>
                        <img src="<?= htmlspecialchars($avatarUsuario) ?>" class="user-avatar-img" alt="">
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




  <div class="container">
    <div class="ask-wrapper">

    <div class="btn-ask text-end container mt-3">
                <a href="foro.php" class="btn btn-primary fw-semibold px-4 py-2 mb-2"
                    style="border-radius:10px; background-color:#219EBC; border:none;">
                    <i class="bi bi-arrow-left-short"></i>Regresar
                </a>
            </div>


      <!-- Page Header -->
      <div class="ask-header">
        <h1><i class="fa-solid fa-circle-question text-primary me-2"></i>Crear nueva pregunta</h1>
        <p>Comparte tu duda con la comunidad EduTecnia. Cuanto más detalle incluyas, mejores respuestas obtendrás.</p>
      </div>

      <div class="row g-4">

        <!-- ── Main Form ── -->
        <div class="col-lg-8">
          <div class="ask-card">

            <!-- Título -->
            <div class="mb-4">
              <label class="form-label" for="ask-titulo">
                Título de la pregunta <span class="field-required">*</span>
              </label>

              <input type="text" class="form-control" id="ask-title"
                placeholder="Ej: ¿Cómo manejar excepciones en Python con try/except?" maxlength="150" autocomplete="off"
                >
              <div class="invalid-feedback">
  El título es obligatorio y debe contener al menos 10 caracteres.
</div>
            </div>

            <!-- Lenguaje (multi-select dropdown con checkboxes) -->
            <div class="mb-4">
              <label class="form-label">
                Lenguaje(s) de programación <span class="field-required">*</span>
              </label>

              <div class="lang-dropdown-wrap" id="lang-dropdown-wrap">
                <!-- Trigger -->
                <button type="button" class="lang-trigger" id="lang-trigger" onclick="toggleLangDropdown()">
                  <span id="lang-trigger-text">— Selecciona uno o más lenguajes —</span>
                  <i class="fa-solid fa-chevron-down lang-chevron" id="lang-chevron"></i>
                </button>

                <!-- Panel -->
              <div id="lang-panel" class="lang-panel">
  <div class="d-flex flex-wrap gap-2">
    <label class="lang-opt"><input type="checkbox" value="html" onchange="toggleLanguage(this.value)"> HTML</label>
    <label class="lang-opt"><input type="checkbox" value="css" onchange="toggleLanguage(this.value)"> CSS</label>
    <label class="lang-opt"><input type="checkbox" value="javascript" onchange="toggleLanguage(this.value)"> JavaScript</label>
    <label class="lang-opt"><input type="checkbox" value="php" onchange="toggleLanguage(this.value)"> PHP</label>
    <label class="lang-opt"><input type="checkbox" value="python" onchange="toggleLanguage(this.value)"> Python</label>
    <label class="lang-opt"><input type="checkbox" value="sql" onchange="toggleLanguage(this.value)"> SQL</label>
    <label class="lang-opt"><input type="checkbox" value="java" onchange="toggleLanguage(this.value)"> Java</label>
    <label class="lang-opt"><input type="checkbox" value="c++" onchange="toggleLanguage(this.value)"> C++</label>
    
    <label class="lang-opt">
      <input type="checkbox" id="cb-otro" value="otro" onchange="checkOtro(this)"> Otro
    </label>
  </div>
  
  <div class="d-flex gap-2 mt-2">
    <input type="text" id="lang-otro-input" class="form-control form-control-sm" placeholder="¿Cuál?" style="display:none; max-width:150px;">
    <button type="button" class="btn btn-sm btn-primary" onclick="addOtro()" style="display:none;" id="btn-add-otro">Agregar</button>
  </div>
</div>
              </div>

              <!-- Chips de seleccionados -->
              <div class="lang-chips" id="lang-chips"></div>
              <span class="invalid-msg" id="err-lang">Selecciona al menos un lenguaje.</span>

              <!-- Campo para "Otro" -->
              <div id="nuevo-lenguaje-group" class="mt-2">
                <input type="text" class="form-control" id="ask-custom-lang" placeholder="Ej: Haskell, Elixir, Dart..."
                  maxlength="50">
                <span class="invalid-msg" id="err-custom-lang">Escribe el nombre del lenguaje.</span>
              </div>
            </div>

            <hr class="ask-divider">

            <!-- Contenido con Quill -->
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

            <!-- Actions -->
            <div class="ask-actions">
              <button class="btn-preview-ask" type="button" onclick="abrirPreview()">
                <i class="fa-regular fa-eye"></i> Vista previa
              </button>
              <div class="actions-right">
                <button class="btn-reset-ask" type="button" onclick="resetForm()">
                  <i class="fa-solid fa-rotate-left me-1"></i>Reiniciar
                </button>
                <button class="btn-cancel-ask" type="button" onclick="cancelar()">
                  Cancelar
                </button>
                <button class="btn btn-primary px-4 fw-semibold" type="button" onclick="publicar()">
                  <i class="fa-solid fa-paper-plane me-2"></i>Publicar pregunta
                </button>
              </div>
            </div>

          </div>
        </div>

        <!-- ── Tips Sidebar ── -->
        <div class="col-lg-4">
          <div class="tips-card">
            <h6><i class="fa-solid fa-lightbulb"></i>Consejos para una buena pregunta</h6>
            <ul>
              <li>Escribe un título claro y específico.</li>
              <li>Describe el contexto del problema.</li>
              <li>Incluye el código que no funciona.</li>
              <li>Menciona el error exacto que ves.</li>
              <li>Explica qué soluciones ya intentaste.</li>
              <li>Selecciona el lenguaje correcto para que expertos lo encuentren.</li>
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

  <!-- ── Preview Modal ── -->
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
      <span>
        © 2026 Universidad Autónoma Metropolitana, Cuajimalpa
      </span>
      <div style="display:flex;gap:24px;">
        <a href="dashboard.php" style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Inicio</a>
        <a href="dashboard.php#cursos" style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Cursos</a>
        <a href="#" style="font-size:13px;color:rgba(255,255,255,.4);text-decoration:none;">Foro</a>
      </div>
    </div>
  </footer>

  <!-- Quill JS -->
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script id="script-pregunta" src="<?= BASE_URL ?>/js/askj.js" data-base-url="<?= BASE_URL ?>"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* ── Funciones de navegación y reset ── */
function cancelar() {
  window.location.href = '<?= BASE_URL ?>/profesor/foro.php';
}

function backdropClick(e) {
  if (e.target === document.getElementById('preview-backdrop')) {
    cerrarPreview();
  }
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
  document.querySelectorAll('.lang-opt input[type="checkbox"]').forEach(cb => cb.checked = false);
  const otroInput = document.getElementById('lang-otro-input');
  if (otroInput) { otroInput.value = ''; otroInput.style.display = 'none'; }
}
</script>

</body>

</html>