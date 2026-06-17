
// ── Mapa de lenguajes → slug de OneCompiler ───────────────
const LANG_ONECOMPILER = {
    python: 'python', javascript: 'javascript',
    typescript: 'typescript', java: 'java',
    csharp: 'csharp', cpp: 'cpp',
    go: 'go', rust: 'rust',
    php: 'php', ruby: 'ruby',
    swift: 'swift', kotlin: 'kotlin',
    sql: 'sql', bash: 'bash',
    r: 'r', dart: 'dart',
    html: 'html',

};

// Código que llega del compilador vía postMessage
let codigoDesdeCompilador = '';
window.addEventListener('message', (e) => {
    if (e.data && (e.data.type === 'onecompiler' || e.data.language !== undefined)) {
        if (e.data.code !== undefined) codigoDesdeCompilador = e.data.code;
        if (e.data.sourceCode !== undefined) codigoDesdeCompilador = e.data.sourceCode;
    }
});

// ── Estado ────────────────────────────────────────────────

// ── Estado ────────────────────────────────────────────────
let modulos = [];
let moduloActivo = null;
let leccionActiva = null;
let quill = null;
let contadorModulos = 1;
let contadorLecciones = 1;

// ── Inicialización ────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initQuill();
    initTiposContenido();

    // ✅ Si hay módulos en la BD los carga, si no crea uno vacío
    if (typeof MODULOS_BD !== 'undefined' && MODULOS_BD.length > 0) {
        cargarDesdeDB(MODULOS_BD);
    } else {
        nuevoModulo();
    }
});

// ── Quill Editor ──────────────────────────────────────────
function initQuill() {
    quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Instrucciones para el alumno...',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['link', 'code-block'],
                ['clean']
            ]
        }
    });
}

// ── Carga datos desde la BD ───────────────────────────────
function cargarDesdeDB(modulosBD) {
    // Reconstruye el estado interno con los datos de la BD
    modulosBD.forEach(mod => {
        const modulo = {
            id: contadorModulos++,
            idBD: mod.id,           // ← ID real de la BD
            titulo: mod.titulo,
            abierto: true,
            lecciones: []
        };

        mod.lecciones.forEach(lec => {
            modulo.lecciones.push({
                id: contadorLecciones++,
                idBD: lec.id,    // ← ID real de la BD
                moduloId: modulo.id,
                titulo: lec.titulo,
                tipo: lec.tipo || 'video',
                url: lec.url || '',
                descripcion: lec.descripcion || '',
                codigo_base: lec.codigo_base || '',
                lenguaje: lec.lenguaje || '',
            });
        });

        modulos.push(modulo);
    });

    renderModulos();

    // Activa la primera lección automáticamente
    if (modulos.length > 0 && modulos[0].lecciones.length > 0) {
        activarLeccion(modulos[0].lecciones[0].id, modulos[0].id);
    }
}

// ── Tipos de contenido ────────────────────────────────────
function initTiposContenido() {
    const radios = document.querySelectorAll('input[name="contentType"]');
    radios.forEach(radio => {
        radio.addEventListener('change', () => mostrarCampos(radio.value));
    });
    mostrarCampos('video');
}
/* ── Language dropdown ── */
function toggleLangDropdown() {
    const panel = document.getElementById('lang-panel');
    const trigger = document.getElementById('lang-trigger');
    const chevron = document.getElementById('lang-chevron');
    const isOpen = panel.classList.contains('open');
    panel.classList.toggle('open');
    trigger.classList.toggle('open');
    chevron.classList.toggle('rotated');
    if (!isOpen) document.getElementById('lang-search').focus();
}

function filterLangs(q) {
    const term = q.toLowerCase();
    document.querySelectorAll('.lang-option').forEach(opt => {
        const label = opt.textContent.toLowerCase();
        opt.classList.toggle('hidden', term !== '' && !label.includes(term));
    });
}

function updateLangSelection() {
    const checked = [...document.querySelectorAll('#lang-options input[type=checkbox]:checked')];
    const hasOtro = checked.some(c => c.value === 'otro');

    // Campo "Otro"
    document.getElementById('nuevo-lenguaje-group').style.display = hasOtro ? 'block' : 'none';
    if (!hasOtro) document.getElementById('ask-custom-lang').value = '';

    // Chips
    const chips = document.getElementById('lang-chips');
    chips.innerHTML = '';
    checked.forEach(c => {
        const label = c.closest('.lang-option').textContent.trim();
        const chip = document.createElement('span');
        chip.className = 'lang-chip';
        chip.dataset.value = c.value;
        chip.innerHTML = `${label} <button type="button" class="lang-chip-remove" onclick="removeLang('${c.value}')" title="Quitar">&#10005;</button>`;
        chips.appendChild(chip);
    });

    // Texto del trigger
    const trigger = document.getElementById('lang-trigger');
    const triggerText = document.getElementById('lang-trigger-text');
    if (checked.length === 0) {
        triggerText.textContent = '— Selecciona uno o más lenguajes —';
        trigger.classList.remove('has-selection');
    } else {
        triggerText.textContent = checked.length === 1 ? '1 lenguaje seleccionado' : `${checked.length} lenguajes seleccionados`;
        trigger.classList.add('has-selection');
    }

    // ── Actualizar el compilador con el primer lenguaje seleccionado ──
    const iframe = document.getElementById('compilador-iframe');
    if (iframe && checked.length > 0) {
        const primerLang = checked[0].value === 'otro'
            ? (document.getElementById('ask-custom-lang')?.value.trim().toLowerCase() || 'python')
            : checked[0].value;
        const slug = LANG_ONECOMPILER[primerLang] || primerLang;
        const codigoActual = codigoDesdeCompilador
            ? encodeURIComponent(codigoDesdeCompilador)
            : '';
        iframe.src = `https://onecompiler.com/embed/${slug}?codeChangeEvent=true&hideNew=true${codigoActual ? '&code=' + codigoActual : ''}`;
    }
}

function removeLang(value) {
    const cb = document.querySelector(`#lang-options input[value="${value}"]`);
    if (cb) { cb.checked = false; updateLangSelection(); }
}

function clearLangs() {
    document.querySelectorAll('#lang-options input[type=checkbox]').forEach(c => c.checked = false);
    updateLangSelection();
}

// Cerrar dropdown al hacer click fuera
document.addEventListener('click', e => {
    const wrap = document.getElementById('lang-dropdown-wrap');
    if (wrap && !wrap.contains(e.target)) {
        document.getElementById('lang-panel').classList.remove('open');
        document.getElementById('lang-trigger').classList.remove('open');
        document.getElementById('lang-chevron').classList.remove('rotated');
    }
});

/* ── Helpers ── */
function getSelectedLangs() {
    const checked = [...document.querySelectorAll('#lang-options input[type=checkbox]:checked')];
    return checked.map(c => {
        if (c.value === 'otro') {
            const custom = document.getElementById('ask-custom-lang').value.trim();
            return custom || 'Otro';
        }
        return c.closest('.lang-option').textContent.trim();
    });
}

function clearErrors() {
    document.querySelectorAll('.invalid-msg').forEach(el => el.classList.remove('show'));
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.getElementById('lang-trigger')?.classList.remove('is-invalid');
}

function showError(fieldId, msgId) {
    document.getElementById(fieldId)?.classList.add('is-invalid');
    document.getElementById(msgId)?.classList.add('show');
}

function validateForm() {
    clearErrors();
    let valid = true;
    const titulo = document.getElementById('ask-titulo').value.trim();
    const checked = [...document.querySelectorAll('#lang-options input[type=checkbox]:checked')];
    const hasOtro = checked.some(c => c.value === 'otro');
    const customLang = document.getElementById('ask-custom-lang').value.trim();
    const contenido = quill.getText().trim();

    if (!titulo) { showError('ask-titulo', 'err-titulo'); valid = false; }

    if (checked.length === 0) {
        document.getElementById('lang-trigger').style.borderColor = '#dc3545';
        document.getElementById('err-lang').classList.add('show');
        valid = false;
    } else {
        document.getElementById('lang-trigger').style.borderColor = '';
    }

    if (hasOtro && !customLang) { showError('ask-custom-lang', 'err-custom-lang'); valid = false; }

    if (!contenido || contenido.length < 10) {
        document.getElementById('err-contenido').classList.add('show');
        document.querySelector('.ql-container').style.borderColor = '#dc3545';
        valid = false;
    } else {
        document.querySelector('.ql-container').style.borderColor = '';
    }
    return valid;
}


function mostrarCampos(tipo) {
    const contenedor = document.getElementById('dynamicFields');
    const campos = {
        video: `
            <div class="mb-3">
                <label class="form-label">URL de YouTube </label>
                <input type="url" id="fieldUrlInput" class="form-control"
                    placeholder="https://youtube.com/embed/...">
            </div>
            <div class="mb-3">
                <label class="form-label">Selecciona el Archivo de Video</label>
                <input class="form-control" type="file" id="formFileMultiple"
                    multiple accept=".mp3">
                <div class="form-text">Formatos: mp3, etc</div>
            </div>
            `,
        practica: `
              <div class="mb-4">
              <label class="form-label">
                Lenguaje(s) de programación 
              </label>

              <div class="lang-dropdown-wrap" id="lang-dropdown-wrap">
                <!-- Trigger -->
                <button type="button" class="lang-trigger" id="lang-trigger" onclick="toggleLangDropdown()">
                  <span id="lang-trigger-text">— Selecciona uno o más lenguajes —</span>
                  <i class="fa-solid fa-chevron-down lang-chevron" id="lang-chevron"></i>
                </button>

                <!-- Panel -->
                <div class="lang-panel" id="lang-panel">
                  <div class="lang-panel-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="lang-search" placeholder="Buscar lenguaje..."
                      oninput="filterLangs(this.value)" autocomplete="off">
                  </div>
                  <div class="lang-options" id="lang-options">
                    <label class="lang-option" data-lang="python">
                      <input type="checkbox" value="python" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Python
                    </label>
                    <label class="lang-option" data-lang="javascript">
                      <input type="checkbox" value="javascript" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>JavaScript
                    </label>
                    <label class="lang-option" data-lang="typescript">
                      <input type="checkbox" value="typescript" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>TypeScript
                    </label>
                    <label class="lang-option" data-lang="java">
                      <input type="checkbox" value="java" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Java
                    </label>
                    <label class="lang-option" data-lang="csharp">
                      <input type="checkbox" value="csharp" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>C#
                    </label>
                    <label class="lang-option" data-lang="cpp">
                      <input type="checkbox" value="cpp" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>C++
                    </label>
                    <label class="lang-option" data-lang="go">
                      <input type="checkbox" value="go" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Go
                    </label>
                    <label class="lang-option" data-lang="rust">
                      <input type="checkbox" value="rust" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Rust
                    </label>
                    <label class="lang-option" data-lang="php">
                      <input type="checkbox" value="php" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>PHP
                    </label>
                    <label class="lang-option" data-lang="ruby">
                      <input type="checkbox" value="ruby" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Ruby
                    </label>
                    <label class="lang-option" data-lang="swift">
                      <input type="checkbox" value="swift" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Swift
                    </label>
                    <label class="lang-option" data-lang="kotlin">
                      <input type="checkbox" value="kotlin" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Kotlin
                    </label>
                    <label class="lang-option" data-lang="sql">
                      <input type="checkbox" value="sql" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>SQL
                    </label>
                    <label class="lang-option" data-lang="bash">
                      <input type="checkbox" value="bash" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Bash / Shell
                    </label>
                    <label class="lang-option" data-lang="r">
                      <input type="checkbox" value="r" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>R
                    </label>
                    <label class="lang-option" data-lang="dart">
                      <input type="checkbox" value="dart" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Dart
                    </label>
                    <label class="lang-option lang-option-otro" data-lang="otro">
                      <input type="checkbox" value="otro" onchange="updateLangSelection()">
                      <span class="lang-check-box"></span>Otro (especificar)
                    </label>
                  </div>
                  <div class="lang-panel-footer">
                    <button type="button" class="lang-clear-btn" onclick="clearLangs()">Limpiar selección</button>
                    <button type="button" class="lang-done-btn" onclick="toggleLangDropdown()">Listo ✓</button>
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
            <div class="mb-3" id="compilador-wrap">
                <label class="form-label d-flex align-items-center justify-content-between">
                    <span>Compilador — código base de la práctica</span>
                    <small class="text-muted" style="font-weight:400;font-size:11px;">
                        <i class="bi bi-info-circle me-1"></i>El código que escribas aquí se guardará
                    </small>
                </label>
                <iframe
                    id="compilador-iframe"
                    frameBorder="0"
                    height="460px"
                    src="https://onecompiler.com/embed/python?codeChangeEvent=true&hideNew=true"
                    width="100%"
                    style="border-radius:10px;border:1px solid #dee2e6;"
                ></iframe>
            </div>
            `,
        texto: `
           `,
        documento: `
            <div class="mb-3">
                <label class="form-label">Documento adjunto</label>

                <!-- Zona de carga -->
                <div id="doc-dropzone"
                     onclick="document.getElementById('doc-file-input').click()"
                     ondragover="event.preventDefault();this.classList.add('drag-over')"
                     ondragleave="this.classList.remove('drag-over')"
                     ondrop="handleDocDrop(event)"
                     style="border:2px dashed #dee2e6;border-radius:10px;padding:28px 20px;
                            text-align:center;cursor:pointer;transition:background .2s,border-color .2s;
                            background:#fafafa;">
                    <i class="bi bi-file-earmark-arrow-up" style="font-size:1.8rem;color:#adb5bd;display:block;margin-bottom:6px;"></i>
                    <div style="font-size:13px;font-weight:600;color:#6c757d;">Haz clic o arrastra el archivo aquí</div>
                    <div style="font-size:11px;color:#adb5bd;margin-top:4px;">PDF, Word, PowerPoint, Excel — máx. 20 MB</div>
                </div>
                <input type="file" id="doc-file-input" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx"
                       style="display:none;" onchange="subirDocumento(this.files[0])">

                <!-- Progreso de subida -->
                <div id="doc-upload-progress" style="display:none;margin-top:10px;">
                    <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#6c757d;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span id="doc-upload-msg">Subiendo archivo...</span>
                    </div>
                    <div class="progress mt-2" style="height:5px;">
                        <div id="doc-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                             style="width:0%;"></div>
                    </div>
                </div>

                <!-- Archivo cargado -->
                <div id="doc-loaded-wrap" style="display:none;margin-top:10px;">
                    <div style="display:flex;align-items:center;gap:10px;background:#f0fdf4;
                                border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;">
                        <i class="bi bi-file-earmark-check-fill text-success fs-5"></i>
                        <div style="flex:1;min-width:0;">
                            <div id="doc-loaded-name" style="font-size:13px;font-weight:600;color:#15803d;
                                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
                            <div id="doc-loaded-size" style="font-size:11px;color:#6c757d;"></div>
                        </div>
                        <button type="button" onclick="quitarDocumento()" class="btn btn-sm btn-outline-danger" title="Quitar">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>

                <!-- URL guardada (oculta) -->
                <input type="hidden" id="doc-url-guardada" value="">
            </div>`
    };
    contenedor.innerHTML = campos[tipo] || '';
}

// ── MÓDULOS ───────────────────────────────────────────────
function nuevoModulo() {
    if (leccionActiva !== null) guardarEstadoLeccionActiva();

    const modulo = {
        id: contadorModulos++,
        titulo: `Módulo ${modulos.length + 1}`,
        abierto: true,
        lecciones: []
    };
    modulos.push(modulo);
    renderModulos();
    nuevaLeccion(modulo.id);
}

function eliminarModulo(moduloId, event) {
    event.stopPropagation();
    if (modulos.length === 1) {
        alert('Debe haber al menos un módulo.');
        return;
    }
    if (!confirm('¿Eliminar este módulo y todas sus lecciones?')) return;

    const eraActivo = modulos.find(m => m.id === moduloId)
        ?.lecciones.some(l => l.id === leccionActiva);

    modulos = modulos.filter(m => m.id !== moduloId);

    if (eraActivo) {
        leccionActiva = null;
        moduloActivo = null;
        limpiarFormulario();
    }

    renderModulos();
}

function toggleModulo(moduloId) {
    const modulo = modulos.find(m => m.id === moduloId);
    if (modulo) {
        modulo.abierto = !modulo.abierto;
        renderModulos();
    }
}

function editarTituloModulo(moduloId) {
    const modulo = modulos.find(m => m.id === moduloId);
    if (!modulo) return;
    const nuevoTitulo = prompt('Nombre del módulo:', modulo.titulo);
    if (nuevoTitulo && nuevoTitulo.trim()) {
        modulo.titulo = nuevoTitulo.trim();
        renderModulos();
    }
}

// ── LECCIONES ─────────────────────────────────────────────
function nuevaLeccion(moduloId) {
    if (leccionActiva !== null) guardarEstadoLeccionActiva();

    const modulo = modulos.find(m => m.id === moduloId);
    if (!modulo) return;

    const leccion = {
        id: contadorLecciones++,
        moduloId: moduloId,
        titulo: `Lección ${modulo.lecciones.length + 1}`,
        tipo: 'video',
        url: '',
        descripcion: '',
        codigo_base: '',
        lenguaje: '',
    };

    modulo.lecciones.push(leccion);
    moduloActivo = moduloId;
    renderModulos();
    activarLeccion(leccion.id, moduloId);
}

function activarLeccion(leccionId, moduloId) {
    if (leccionActiva !== null) guardarEstadoLeccionActiva();

    leccionActiva = leccionId;
    moduloActivo = moduloId;

    const modulo = modulos.find(m => m.id === moduloId);
    if (!modulo) return;
    const leccion = modulo.lecciones.find(l => l.id === leccionId);
    if (!leccion) return;

    // ── Carga datos en el formulario ──────────────────────
    document.getElementById('lessonTitle').value = leccion.titulo;

    const radio = document.querySelector(`input[value="${leccion.tipo}"]`);
    if (radio) {
        radio.checked = true;
        mostrarCampos(leccion.tipo);
    }

    // Carga campos dinámicos según el tipo
    setTimeout(() => {
        const urlInput = document.getElementById('fieldUrlInput');
        if (urlInput && leccion.url) urlInput.value = leccion.url;

        // ── Para documento: restaurar archivo cargado ──
        if (leccion.tipo === 'documento' && leccion.url) {
            const docUrl = document.getElementById('doc-url-guardada');
            if (docUrl) docUrl.value = leccion.url;
            // Mostrar nombre del archivo desde la URL
            const nombre = leccion.url.split('/').pop();
            mostrarDocCargado(leccion.url, nombre, '');
        }

        // ── Para práctica: restaura lenguaje en dropdown y código en compilador ──
        if (leccion.tipo === 'practica') {
            // Marcar el checkbox del lenguaje guardado
            if (leccion.lenguaje) {
                const cb = document.querySelector(`#lang-options input[value="${leccion.lenguaje}"]`);
                if (cb) {
                    cb.checked = true;
                    updateLangSelection(); // actualiza chips y trigger
                }
            }
            // Cargar el compilador con el lenguaje y código guardados
            const iframe = document.getElementById('compilador-iframe');
            if (iframe) {
                const slug = LANG_ONECOMPILER[leccion.lenguaje] || leccion.lenguaje || 'python';
                const code = leccion.codigo_base ? encodeURIComponent(leccion.codigo_base) : '';
                iframe.src = `https://onecompiler.com/embed/${slug}?codeChangeEvent=true&hideNew=true${code ? '&code=' + code : ''}`;
                // Resetear el buffer de código para esta lección
                codigoDesdeCompilador = leccion.codigo_base || '';
            }
        }
    }, 50);

    // Carga la descripción en Quill
    if (quill) {
        quill.root.innerHTML = leccion.descripcion || '';
    }

    renderModulos();
}

function eliminarLeccion(leccionId, moduloId, event) {
    event.stopPropagation();

    const modulo = modulos.find(m => m.id === moduloId);
    if (!modulo) return;

    if (modulo.lecciones.length === 1) {
        alert('El módulo debe tener al menos una lección.');
        return;
    }

    modulo.lecciones = modulo.lecciones.filter(l => l.id !== leccionId);

    if (leccionActiva === leccionId) {
        leccionActiva = null;
        activarLeccion(modulo.lecciones[0].id, moduloId);
    } else {
        renderModulos();
    }
}

// ── Guarda el estado del formulario en la lección activa ──
function guardarEstadoLeccionActiva() {
    if (leccionActiva === null || moduloActivo === null) return;

    const modulo = modulos.find(m => m.id === moduloActivo);
    if (!modulo) return;
    const leccion = modulo.lecciones.find(l => l.id === leccionActiva);
    if (!leccion) return;

    const titulo = document.getElementById('lessonTitle');
    if (titulo) leccion.titulo = titulo.value || leccion.titulo;

    const tipoSeleccionado = document.querySelector('input[name="contentType"]:checked');
    if (tipoSeleccionado) leccion.tipo = tipoSeleccionado.value;

    const urlInput = document.getElementById('fieldUrlInput');
    if (urlInput) leccion.url = urlInput.value;

    // ── Para documento: leer URL del archivo subido ──
    if (leccion.tipo === 'documento') {
        const docUrl = document.getElementById('doc-url-guardada');
        if (docUrl && docUrl.value) leccion.url = docUrl.value;
    }

    // ── Para práctica: lenguaje desde el dropdown y código desde postMessage ──
    if (leccion.tipo === 'practica') {
        const checked = [...document.querySelectorAll('#lang-options input[type=checkbox]:checked')];
        if (checked.length > 0) {
            const primerLang = checked[0].value === 'otro'
                ? (document.getElementById('ask-custom-lang')?.value.trim() || 'otro')
                : checked[0].value;
            leccion.lenguaje = primerLang;
        }
        // Captura el código que llegó por postMessage desde el compilador
        if (codigoDesdeCompilador !== '') {
            leccion.codigo_base = codigoDesdeCompilador;
        }
    }

    // Guarda el HTML del editor Quill
    if (quill) leccion.descripcion = quill.root.innerHTML;
}

function limpiarFormulario() {
    document.getElementById('lessonTitle').value = '';
    if (quill) quill.root.innerHTML = '';
    mostrarCampos('video');
    const radioVideo = document.querySelector('input[value="video"]');
    if (radioVideo) radioVideo.checked = true;
}

// ── RENDER ────────────────────────────────────────────────
function renderModulos() {
    const contenedor = document.getElementById('modulosContainer');
    contenedor.innerHTML = '';

    const iconoTipo = {
        video: 'bi-camera-video-fill',
        practica: 'bi-file-earmark-code',
        texto: 'bi-fonts',
        documento: 'bi-file-earmark-fill'
    };

    modulos.forEach((modulo, mIndex) => {
        const div = document.createElement('div');
        div.className = 'modulo-item';

        div.innerHTML = `
            <div class="modulo-header" onclick="toggleModulo(${modulo.id})">
                <div class="d-flex align-items-center gap-2 flex-1">
                    <i class="bi ${modulo.abierto ? 'bi-chevron-down' : 'bi-chevron-right'} modulo-chevron"></i>
                    <span class="modulo-titulo">Módulo ${mIndex + 1}: ${modulo.titulo}</span>
                </div>
                <div class="modulo-acciones">
                    <button class="btn-icono" title="Renombrar"
                        onclick="event.stopPropagation(); editarTituloModulo(${modulo.id})">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn-icono text-danger" title="Eliminar módulo"
                        onclick="eliminarModulo(${modulo.id}, event)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <ul class="lecciones-lista ${modulo.abierto ? '' : 'd-none'}" id="lecciones-${modulo.id}">
                ${modulo.lecciones.map((lec, lIndex) => `
                    <li class="leccion-item ${lec.id === leccionActiva ? 'leccion-activa' : ''}"
                        onclick="activarLeccion(${lec.id}, ${modulo.id})">
                        <div class="d-flex align-items-center gap-2 flex-1">
                            <i class="bi ${iconoTipo[lec.tipo] || 'bi-play-circle'} leccion-icono"></i>
                            <span class="leccion-titulo">${lIndex + 1}. ${lec.titulo}</span>
                        </div>
                        <button class="btn-icono text-danger"
                            onclick="eliminarLeccion(${lec.id}, ${modulo.id}, event)">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </li>
                `).join('')}
                <li class="leccion-nueva" onclick="nuevaLeccion(${modulo.id})">
                    <i class="bi bi-plus-circle"></i> Añadir lección
                </li>
            </ul>
        `;

        contenedor.appendChild(div);
    });
}

// ── Guardar curso (fetch al PHP) ──────────────────────────
async function guardarCurso() {
    // Guarda el estado de la lección activa antes de enviar
    guardarEstadoLeccionActiva();

    // Validaciones
    if (modulos.length === 0) {
        alert('Agrega al menos un módulo.');
        return;
    }
    for (const modulo of modulos) {
        if (!modulo.titulo.trim()) {
            alert('Todos los módulos deben tener título.');
            return;
        }
        if (modulo.lecciones.length === 0) {
            alert(`El módulo "${modulo.titulo}" no tiene lecciones.`);
            return;
        }
        for (const lec of modulo.lecciones) {
            if (!lec.titulo.trim()) {
                alert('Todas las lecciones deben tener título.');
                return;
            }
        }
    }

    // Prepara los datos para enviar
    const payload = {
        curso_id: CURSO_ID,
        modulos: modulos.map(m => ({
            titulo: m.titulo,
            lecciones: m.lecciones.map(l => ({
                titulo: l.titulo,
                tipo: l.tipo,
                url: l.url || '',
                descripcion: l.descripcion || '',  // ← HTML de Quill
                codigo_base: l.codigo_base || '',
                lenguaje: l.lenguaje || '',
            }))
        }))
    };

    try {
        const res = await fetch(`crearCurso.php?id=${CURSO_ID}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (data.status === 'success') {
            // Muestra confirmación y redirige al dashboard
            alert('✅ ' + data.message);
            window.location.href = 'dashboard.php';
        } else {
            alert('❌ Error: ' + data.message);
        }

    } catch (e) {
        alert('❌ Error de conexión: ' + e.message);
    }
}

// ── VISTA PREVIA ──────────────────────────────────────────

// El modal se abre via data-bs-toggle en el botón del PHP.
// abrirVistaPrevia() solo rellena el contenido; también se dispara
// desde el evento show.bs.modal como fallback.
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('modalVistaPrevia');
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', () => abrirVistaPrevia());
    }
});

const iconoTipoPreview = {
    video: { icon: 'bi-camera-video-fill', badge: 'badge-video', label: 'Video' },
    practica: { icon: 'bi-file-earmark-code', badge: 'badge-practica', label: 'Práctica' },
    texto: { icon: 'bi-fonts', badge: 'badge-texto', label: 'Texto' },
    documento: { icon: 'bi-file-earmark-fill', badge: 'badge-documento', label: 'Documento' },
};

let previoLeccionActiva = null;

function abrirVistaPrevia() {
    guardarEstadoLeccionActiva();

    const totalMods = modulos.length;
    const totalLecs = modulos.reduce((a, m) => a + m.lecciones.length, 0);
    document.getElementById('previoContadorLecciones').innerHTML =
        `<i class="bi bi-collection-play me-1"></i>${totalMods} módulo${totalMods !== 1 ? 's' : ''} &nbsp;·&nbsp; ${totalLecs} lección${totalLecs !== 1 ? 'es' : ''}`;

    const sidebar = document.getElementById('previoSidebar');
    sidebar.innerHTML = '';

    modulos.forEach((modulo, mIndex) => {
        const wrapMod = document.createElement('div');
        wrapMod.className = 'previo-modulo-wrap';

        const header = document.createElement('div');
        header.className = 'previo-modulo-header';
        header.innerHTML = `
            <i class="bi bi-collection text-secondary" style="font-size:13px;"></i>
            <span class="previo-modulo-titulo">Módulo ${mIndex + 1}: ${escapeHtml(modulo.titulo)}</span>
            <span class="text-muted" style="font-size:11px;">${modulo.lecciones.length} lec.</span>
            <i class="bi bi-chevron-down text-muted" style="font-size:11px;" id="chevron-mod-${modulo.id}"></i>
        `;

        const listaLecs = document.createElement('div');
        listaLecs.id = `previo-lecs-${modulo.id}`;

        header.addEventListener('click', () => {
            listaLecs.style.display = listaLecs.style.display === 'none' ? 'block' : 'none';
            const chev = document.getElementById(`chevron-mod-${modulo.id}`);
            chev.className = listaLecs.style.display === 'none'
                ? 'bi bi-chevron-right text-muted' : 'bi bi-chevron-down text-muted';
            chev.style.fontSize = '11px';
        });

        modulo.lecciones.forEach((lec, lIndex) => {
            const meta = iconoTipoPreview[lec.tipo] || iconoTipoPreview.video;
            const item = document.createElement('div');
            item.className = 'previo-leccion-item';
            item.id = `previo-lec-item-${lec.id}`;
            item.innerHTML = `
                <i class="bi ${meta.icon}" style="font-size:13px;color:#6366f1;flex-shrink:0;"></i>
                <span class="previo-leccion-titulo">${lIndex + 1}. ${escapeHtml(lec.titulo)}</span>
                <span class="previo-leccion-tipo-badge ${meta.badge}">${meta.label}</span>
            `;
            item.addEventListener('click', () => previoActivarLeccion(lec.id, modulo, lec, lIndex));
            listaLecs.appendChild(item);
        });

        wrapMod.appendChild(header);
        wrapMod.appendChild(listaLecs);
        sidebar.appendChild(wrapMod);
    });

    if (modulos.length > 0 && modulos[0].lecciones.length > 0) {
        previoActivarLeccion(modulos[0].lecciones[0].id, modulos[0], modulos[0].lecciones[0], 0);
    } else {
        document.getElementById('previoContenido').innerHTML = `
            <div class="previo-content-placeholder">
                <i class="bi bi-collection-play" style="font-size:48px;"></i>
                <p class="mb-0">No hay lecciones aún.</p>
            </div>`;
    }
}

function previoActivarLeccion(lecId, modulo, lec, lIndex) {
    if (!lec || !modulo) return;

    if (previoLeccionActiva) {
        const prev = document.getElementById(`previo-lec-item-${previoLeccionActiva}`);
        if (prev) prev.classList.remove('activa');
    }
    previoLeccionActiva = lecId;
    const curr = document.getElementById(`previo-lec-item-${lecId}`);
    if (curr) curr.classList.add('activa');

    const meta = iconoTipoPreview[lec.tipo] || iconoTipoPreview.video;
    const panel = document.getElementById('previoContenido');

    let html = `
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb" style="font-size:12px;">
                <li class="breadcrumb-item text-muted">${escapeHtml(modulo.titulo)}</li>
                <li class="breadcrumb-item active">${escapeHtml(lec.titulo)}</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="previo-leccion-tipo-badge ${meta.badge} px-2 py-1">
                <i class="bi ${meta.icon} me-1"></i>${meta.label}
            </span>
            <h5 class="mb-0 fw-bold text-dark">${escapeHtml(lec.titulo)}</h5>
        </div>`;

    // ── Video ─────────────────────────────────────────────
    if (lec.tipo === 'video') {
        if (lec.url) {
            const embedUrl = lec.url.includes('/embed/') ? lec.url
                : lec.url.replace('watch?v=', 'embed/').replace('youtu.be/', 'www.youtube.com/embed/');
            html += `<div class="previo-video-wrapper"><iframe src="${escapeHtml(embedUrl)}" allowfullscreen></iframe></div>`;
        } else {
            html += `
                <div class="previo-video-placeholder mb-3"><i class="bi bi-play-circle-fill"></i></div>
                <p class="text-muted small"><i class="bi bi-info-circle me-1"></i>No se ha asignado URL de video aún.</p>`;
        }
    }

    // ── Práctica ──────────────────────────────────────────
    if (lec.tipo === 'practica') {
        const slug = LANG_ONECOMPILER[lec.lenguaje] || lec.lenguaje || 'python';
        const code = lec.codigo_base ? encodeURIComponent(lec.codigo_base) : '';
        const embedUrl = `https://onecompiler.com/embed/${slug}?codeChangeEvent=true&hideNew=true${code ? '&code=' + code : ''}`;
        html += `
            <div class="mb-2 d-flex align-items-center gap-2">
                <span style="font-size:11px;background:#1e1e2e;color:#a0aec0;padding:3px 10px;border-radius:20px;font-family:monospace;">
                    <i class="bi bi-terminal-fill me-1"></i>${escapeHtml((lec.lenguaje || 'código').toUpperCase())}
                </span>
                <small class="text-muted" style="font-size:11px;">Compilador interactivo</small>
            </div>
            <iframe
                frameBorder="0"
                height="460px"
                src="${embedUrl}"
                width="100%"
                style="border-radius:10px;border:1px solid #dee2e6;margin-bottom:12px;"
            ></iframe>`;
    }

    // ── Documento ─────────────────────────────────────────
    if (lec.tipo === 'documento') {
        if (lec.url) {
            const ext = lec.url.split('.').pop().toLowerCase();
            const esPdf = ext === 'pdf';
            const nombre = lec.url.split('/').pop();
            const iconoMap = {
                pdf: 'bi-file-earmark-pdf-fill', doc: 'bi-file-earmark-word-fill',
                docx: 'bi-file-earmark-word-fill', ppt: 'bi-file-earmark-ppt-fill',
                pptx: 'bi-file-earmark-ppt-fill', xls: 'bi-file-earmark-excel-fill',
                xlsx: 'bi-file-earmark-excel-fill'
            };
            const colorMap = {
                pdf: '#dc2626', doc: '#1d4ed8', docx: '#1d4ed8',
                ppt: '#ea580c', pptx: '#ea580c', xls: '#15803d', xlsx: '#15803d'
            };
            const icono = iconoMap[ext] || 'bi-file-earmark-fill';
            const color = colorMap[ext] || '#6c757d';



            // DESPUÉS (correcto):
            html += `
<div style="display:flex;align-items:center;gap:12px;background:#fff;
            border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;
            margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
    <div style="width:46px;height:46px;border-radius:10px;background:${color}18;
                display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi ${icono}" style="font-size:1.4rem;color:${color};"></i>
    </div>
    <div style="flex:1;min-width:0;">
        <div style="font-size:14px;font-weight:600;color:#1e293b;
                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            ${escapeHtml(nombre)}
        </div>
        <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;
                    letter-spacing:.4px;">${ext}</div>
    </div>
    <a href="${lec.url}" download="${escapeHtml(nombre)}" target="_blank"
        style="display:inline-flex;align-items:center;gap:6px;background:#1E4978;
              color:#fff;border-radius:8px;font-size:12px;font-weight:600;
              padding:8px 14px;text-decoration:none;transition:background .15s;white-space:nowrap;"
        onmouseover="this.style.background='#163660'"
        onmouseout="this.style.background='#1E4978'">
        <i class="bi bi-download"></i> Descargar
    </a>
</div>`;

            // Visor inline si es PDF

            if (esPdf) {
                html += `
    <div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;
                margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,.05);">
        <div style="background:#f8fafc;border-bottom:1px solid #e5e7eb;
                    padding:8px 14px;font-size:11px;font-weight:600;
                    color:#64748b;display:flex;align-items:center;gap:6px;">
            <i class="bi bi-eye"></i> Vista previa del documento
        </div>
        <iframe src="${lec.url}"
            width="100%" height="520px"
            style="display:block;border:none;"
            title="Visor PDF"></iframe>
    </div>`;
            }
        } else {
            // Sin archivo aún

            html += `
<div style="text-align:center;padding:2.5rem 1rem;background:#fff;
            border:2px dashed #e5e7eb;border-radius:12px;margin-bottom:12px;color:#94a3b8;">
    <i class="bi bi-file-earmark-arrow-up" style="font-size:2.5rem;display:block;margin-bottom:.5rem;"></i>
    <p style="font-size:13px;margin:0;">No hay documento cargado para esta lección.</p>
    <p style="font-size:12px;margin-top:4px;">Sube un archivo en el editor para que aparezca aquí.</p>
</div>`;
        }
    }

    // ── Descripción / instrucciones ───────────────────────
    if (lec.descripcion && lec.descripcion.replace(/<[^>]+>/g, '').trim()) {
        html += `
            <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
                <div class="card-body p-3">
                    <p class="text-muted mb-2" style="font-size:11px;text-transform:uppercase;letter-spacing:.6px;font-weight:600;">
                        <i class="bi bi-journal-text me-1"></i>Descripción / Instrucciones
                    </p>
                    <div style="font-size:14px;line-height:1.7;color:#374151;">${lec.descripcion}</div>
                </div>
            </div>`;
    }

    // ── Navegación anterior / siguiente ───────────────────
    const todasLecs = modulos.flatMap(m => m.lecciones.map(l => ({ ...l, _modulo: m })));
    const idx = todasLecs.findIndex(l => l.id === lecId);
    const anterior = todasLecs[idx - 1] ?? null;
    const siguiente = todasLecs[idx + 1] ?? null;

    html += `<div class="d-flex justify-content-between mt-4 pt-3 border-top">`;
    if (anterior) {
        html += `<button class="btn btn-outline-secondary btn-sm" id="btn-prev-nav"><i class="bi bi-chevron-left me-1"></i>Anterior</button>`;
    } else {
        html += `<div></div>`;
    }
    if (siguiente) {
        html += `<button class="btn btn-primary btn-sm" id="btn-next-nav">Siguiente <i class="bi bi-chevron-right ms-1"></i></button>`;
    }
    html += `</div>`;

    panel.innerHTML = html;

    // Bind navegación tras render
    if (anterior) {
        document.getElementById('btn-prev-nav')?.addEventListener('click', () =>
            previoActivarLeccion(anterior.id, anterior._modulo, anterior, 0));
    }
    if (siguiente) {
        document.getElementById('btn-next-nav')?.addEventListener('click', () =>
            previoActivarLeccion(siguiente.id, siguiente._modulo, siguiente, 0));
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ══════════════════════════════════════════════════════════
// ── GESTIÓN DE DOCUMENTOS ─────────────────────────────────
// ══════════════════════════════════════════════════════════

function handleDocDrop(event) {
    event.preventDefault();
    document.getElementById('doc-dropzone')?.classList.remove('drag-over');
    const file = event.dataTransfer.files[0];
    if (file) subirDocumento(file);
}

async function subirDocumento(file) {
    if (!file) return;

    const MAX_MB = 20;
    if (file.size > MAX_MB * 1024 * 1024) {
        alert(`El archivo supera los ${MAX_MB} MB permitidos.`);
        return;
    }

    const aceptados = ['application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    if (!aceptados.includes(file.type)) {
        alert('Formato no permitido. Usa PDF, Word, PowerPoint o Excel.');
        return;
    }

    // Mostrar progreso
    document.getElementById('doc-dropzone').style.display = 'none';
    document.getElementById('doc-upload-progress').style.display = 'flex';
    document.getElementById('doc-loaded-wrap').style.display = 'none';
    document.getElementById('doc-upload-msg').textContent = `Subiendo ${file.name}...`;
    document.getElementById('doc-progress-bar').style.width = '30%';

    const formData = new FormData();
    formData.append('archivo', file);
    formData.append('curso_id', CURSO_ID);

    try {
        // Simular progreso
        let prog = 30;
        const timer = setInterval(() => {
            prog = Math.min(prog + 15, 85);
            const bar = document.getElementById('doc-progress-bar');
            if (bar) bar.style.width = prog + '%';
        }, 300);

        const res = await fetch('/pp/administrador/api/cursos/subir_documento.php', {
            method: 'POST', credentials: 'include', body: formData
        });
        clearInterval(timer);

        const data = await res.json();

        if (data.ok) {
            document.getElementById('doc-progress-bar').style.width = '100%';
            setTimeout(() => {
                document.getElementById('doc-upload-progress').style.display = 'none';
                const tamano = (file.size / 1024 < 1024)
                    ? (file.size / 1024).toFixed(1) + ' KB'
                    : (file.size / 1024 / 1024).toFixed(1) + ' MB';
                mostrarDocCargado(data.url, file.name, tamano);
                // Guardar URL en el input oculto
                const inp = document.getElementById('doc-url-guardada');
                if (inp) inp.value = data.url;
                // Guardar en la lección activa
                if (leccionActiva !== null) {
                    const lec = modulos.flatMap(m => m.lecciones).find(l => l.id === leccionActiva);
                    if (lec) lec.url = data.url;
                }
            }, 300);
        } else {
            document.getElementById('doc-upload-progress').style.display = 'none';
            document.getElementById('doc-dropzone').style.display = 'block';
            alert('Error al subir: ' + (data.msg || 'Error desconocido'));
        }
    } catch (err) {
        document.getElementById('doc-upload-progress').style.display = 'none';
        document.getElementById('doc-dropzone').style.display = 'block';
        console.error(err);
        alert('Error de conexión al subir el archivo.');
    }
}

function mostrarDocCargado(url, nombre, tamano) {
    document.getElementById('doc-dropzone').style.display = 'none';
    document.getElementById('doc-loaded-wrap').style.display = 'flex';
    document.getElementById('doc-loaded-name').textContent = nombre;
    document.getElementById('doc-loaded-size').textContent = tamano;
    document.getElementById('doc-url-guardada').value = url;
}

function quitarDocumento() {
    document.getElementById('doc-dropzone').style.display = 'block';
    document.getElementById('doc-loaded-wrap').style.display = 'none';
    document.getElementById('doc-url-guardada').value = '';
    const fi = document.getElementById('doc-file-input');
    if (fi) fi.value = '';
    // Limpiar URL de la lección activa
    if (leccionActiva !== null) {
        const lec = modulos.flatMap(m => m.lecciones).find(l => l.id === leccionActiva);
        if (lec) lec.url = '';
    }
}