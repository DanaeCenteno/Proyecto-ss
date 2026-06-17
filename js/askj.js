const scriptPregunta = document.getElementById('script-pregunta');
const BASE_URL = scriptPregunta ? scriptPregunta.dataset.baseUrl : '/pp';

/* ── Init Quill ── */
const quill = new Quill('#ask-editor', {
  theme: 'snow',
  placeholder: 'Describe tu pregunta con detalle...',
  modules: {
    toolbar: [
      ['bold', 'italic', 'underline', 'strike'],
      ['blockquote', 'code-block'],
      [{ list: 'ordered' }, { list: 'bullet' }],
      [{ header: [1, 2, 3, false] }],
      ['link', 'image'],
      ['clean']
    ]
  }
});

/* ── Char counter ── */
function updateCharCount(input, countId, max) {
  const len = input.value.length;
  const el = document.getElementById(countId);
  el.textContent = len + ' / ' + max;
  el.className = 'char-count ms-auto' + (len >= max ? ' limit' : len >= max * 0.85 ? ' warn' : '');
}

/* ── Language dropdown ── */
function toggleLangDropdown() {
  const panel = document.getElementById('lang-panel');
  const trigger = document.getElementById('lang-trigger');
  const chevron = document.getElementById('lang-chevron');
  panel.classList.toggle('open');
  trigger.classList.toggle('active');
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', (e) => {
  const dropdown = document.getElementById('lang-dropdown-wrap');
  if (dropdown && !dropdown.contains(e.target)) {
    document.getElementById('lang-panel').classList.remove('open');
    document.getElementById('lang-trigger').classList.remove('active');
  }
});

let selectedLanguages = [];

function toggleLanguage(langValue) {
  const idx = selectedLanguages.indexOf(langValue);
  const badgeWrap = document.getElementById('lang-chips');

  if (idx > -1) {
    selectedLanguages.splice(idx, 1);
    const badge = document.querySelector(`.badge-lang[data-value="${langValue}"]`);
    if (badge) badge.remove();
  } else {
    selectedLanguages.push(langValue);
    const label = langValue.startsWith('otro:') ? langValue.substring(5) : langValue;
    const b = document.createElement('span');
    b.className = 'badge-lang text-capitalize';
    b.setAttribute('data-value', langValue);
    b.innerHTML = `${label} <i class="fa-solid fa-xmark ms-1" style="cursor:pointer" onclick="toggleLanguage('${langValue}'); event.stopPropagation();"></i>`;
    badgeWrap.appendChild(b);
  }

  // Actualizar checkboxes visuales
  document.querySelectorAll('.lang-opt input[type="checkbox"]').forEach(cb => {
    if (cb.value === 'otro') return;
    cb.checked = selectedLanguages.includes(cb.value);
  });

  // Si no hay seleccionados, mostrar placeholder
  const triggerText = document.getElementById('lang-trigger-text');
  if (triggerText) {
    triggerText.textContent = selectedLanguages.length
      ? selectedLanguages.map(l => l.startsWith('otro:') ? l.substring(5) : l).join(', ')
      : '— Selecciona uno o más lenguajes —';
  }
}

function checkOtro(cb) {
  const input = document.getElementById('lang-otro-input');
  if (cb.checked) {
    input.style.display = 'block';
    input.focus();
  } else {
    input.style.display = 'none';
    // Remover cualquier 'otro:...' previo
    selectedLanguages = selectedLanguages.filter(l => !l.startsWith('otro:'));
    document.querySelectorAll('.badge-lang').forEach(b => {
      if (b.getAttribute('data-value').startsWith('otro:')) b.remove();
    });
    const triggerText = document.getElementById('lang-trigger-text');
    if (triggerText) {
      triggerText.textContent = selectedLanguages.length
        ? selectedLanguages.map(l => l.startsWith('otro:') ? l.substring(5) : l).join(', ')
        : '— Selecciona uno o más lenguajes —';
    }
  }
}

function addOtro() {
  const input = document.getElementById('lang-otro-input');
  const val = input.value.trim().toLowerCase();
  if (!val) return;

  // Filtrar el anterior "otro:"
  selectedLanguages = selectedLanguages.filter(l => !l.startsWith('otro:'));
  document.querySelectorAll('.badge-lang').forEach(b => {
    if (b.getAttribute('data-value').startsWith('otro:')) b.remove();
  });

  toggleLanguage('otro:' + val);
  input.value = '';
  input.style.display = 'none';
  document.getElementById('cb-otro').checked = false;
}

/* ── Preview ─────────────────────────────────────────────── */
function abrirPreview() {
  if (!validarFormulario()) return;

  const title = document.getElementById('ask-title').value.trim();
  const courseSelect = document.getElementById('ask-course');
  const courseText = courseSelect ? courseSelect.options[courseSelect.selectedIndex].text : '';
  const htmlContent = quill.root.innerHTML;

  let headerHtml = `<h2 class="fw-bold mb-2">${title}</h2>`;
  if (courseSelect && courseSelect.value !== "") {
    headerHtml += `<span class="badge bg-light text-dark border mb-3"><i class="bi bi-bookmark me-1"></i>${courseText}</span>`;
  }

  let badgesHtml = '<div class="d-flex flex-wrap gap-1 mb-4">';
  selectedLanguages.forEach(l => {
    const label = l.startsWith('otro:') ? l.substring(5) : l;
    badgesHtml += `<span class="badge bg-secondary text-capitalize">${label}</span>`;
  });
  badgesHtml += '</div>';

  document.getElementById('preview-content-wrap').innerHTML = headerHtml + badgesHtml + `<div class="ql-editor p-0">${htmlContent}</div>`;
document.getElementById('preview-backdrop').classList.add('open');
}

function cerrarPreview() {
  document.getElementById('preview-backdrop').classList.remove('open');
}

/* ── VALIDACIÓN GLOBAL DEL FORMULARIO ── */
function validarFormulario() {
  const tituloInput = document.getElementById('ask-title');
  const contenidoTexto = typeof quill !== 'undefined' ? quill.getText().trim() : '';
  let esValido = true;

  // 1. Validar Título
  if (!tituloInput.value.trim() || tituloInput.value.trim().length < 10) {
    tituloInput.classList.add('is-invalid');
    esValido = false;
  } else {
    tituloInput.classList.remove('is-invalid');
    tituloInput.classList.add('is-valid');
  }

  // 2. Validar Contenido de Quill
  const qlContainer = document.querySelector('.ql-container');
  if (contenidoTexto.length < 10) {
    if (qlContainer) qlContainer.style.borderColor = '#FB8500';
    esValido = false;
  } else {
    if (qlContainer) qlContainer.style.borderColor = '';
  }

  return esValido;
}

/* ── PUBLICAR PREGUNTA (ENVÍO API) ── */
function publicar() {
  if (!validarFormulario()) {
    mostrarToast("Por favor, llena los campos requeridos correctamente.", "error");
    return;
  }

  const tituloInput = document.getElementById('ask-title');
  const cursoSelect = document.getElementById('ask-course');
  const contenidoHTML = quill.root.innerHTML;

  const datosFormulario = new FormData();
  datosFormulario.append('titulo', tituloInput.value.trim());
  datosFormulario.append('curso_id', cursoSelect ? cursoSelect.value : 0);
  datosFormulario.append('contenido', contenidoHTML.trim());
  datosFormulario.append('lenguajes', selectedLanguages.join(','));

  console.log("Enviando publicación a la API...");

  fetch(`${BASE_URL}/administrador/api/foro/guardar_pregunta.php`, {
    method: 'POST',
    body: datosFormulario,
    credentials: 'include'
  })
    .then(res => {
      if (res.status === 401) {
        mostrarToast("Tu sesión ha expirado. Por favor inicia sesión.", "error");
        throw new Error("No autorizado");
      }
      // Leer como texto primero para evitar crash si el servidor devuelve HTML de error
      return res.text();
    })
    .then(texto => {
      let data;
      try {
        data = JSON.parse(texto);
      } catch (_) {
        // El servidor devolvió HTML (error de PHP) en vez de JSON
        console.error("Respuesta inesperada del servidor:", texto);
        mostrarToast("Error interno del servidor. Revisa los logs de PHP.", "error");
        return;
      }
      if (data.ok) {
        mostrarToast("¡Pregunta publicada con éxito!", "success");
        setTimeout(() => {
          window.location.href = `${BASE_URL}/profesor/foro.php`;
        }, 1500);
      } else {
        mostrarToast(data.msg || "Error al procesar la solicitud.", "error");
      }
    })
    .catch(err => {
      console.error("Detalle del error:", err);
      mostrarToast("Error de comunicación con el servidor.", "error");
    });
}

/* ── Cancelar ── */
function cancelar() {
  const base = (typeof BASE_URL !== 'undefined' && BASE_URL) ? BASE_URL : '';
  window.location.href = base + '/profesor/foro.php';
}

/* ── Reiniciar formulario ── */
function resetForm() {
  document.getElementById('ask-title').value = '';
  document.getElementById('ask-title').classList.remove('is-invalid', 'is-valid');
  quill.setContents([]);
  selectedLanguages = [];
  document.getElementById('lang-chips').innerHTML = '';
  document.getElementById('lang-trigger-text').textContent = '— Selecciona uno o más lenguajes —';
  document.querySelectorAll('.lang-opt input[type="checkbox"]').forEach(cb => cb.checked = false);
}

/* ── Toast notifications ── */
function mostrarToast(mensaje, tipo = 'success') {
  const viejo = document.getElementById('ask-toast');
  if (viejo) viejo.remove();

  const colores = {
    success: { bg: '#219EBC', icon: 'fa-circle-check' },
    error: { bg: '#FB8500', icon: 'fa-circle-exclamation' }
  };
  const { bg, icon } = colores[tipo] ?? colores.success;

  const toast = document.createElement('div');
  toast.id = 'ask-toast';
  toast.style.cssText = `
      position: fixed; bottom: 28px; right: 28px; z-index: 9999;
      background: ${bg}; color: #fff;
      padding: 14px 20px; border-radius: 10px;
      font-size: 14px; font-weight: 500;
      display: flex; align-items: center; gap: 10px;
      box-shadow: 0 8px 24px rgba(2,48,71,.25);
      animation: toastIn .3s ease;
    `;
  toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${mensaje}`;
  document.body.appendChild(toast);

  if (!document.getElementById('toast-style')) {
    const style = document.createElement('style');
    style.id = 'toast-style';
    style.textContent = `
        @keyframes toastIn  { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
        @keyframes toastOut { from { opacity:1; } to { opacity:0; transform:translateY(8px); } }
    `;
    document.head.appendChild(style);
  }

  setTimeout(() => {
    toast.style.animation = 'toastOut .3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}