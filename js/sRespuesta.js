

/* ── Init Quill editor de respuesta ─────────────────────── */
const quillResp = new Quill('#resp-editor', {
    theme: 'snow',
    placeholder: 'Escribe tu respuesta aquí...',
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

/* ── Validación ─────────────────────────────────────────── */
function validarRespuesta() {
    const contenido = quillResp.getText().trim();
    const errEl = document.getElementById('err-resp-contenido');
    const qlCont = document.querySelector('#form-respuesta .ql-container');

    if (!contenido || contenido.length < 10) {
        errEl.classList.add('show');
        if (qlCont) qlCont.style.borderColor = 'var(--naranja)';
        return false;
    }
    errEl.classList.remove('show');
    if (qlCont) qlCont.style.borderColor = '';
    return true;
}

/* ── Preview ─────────────────────────────────────────────── */
function abrirPreviewResp() {
    const contenidoHTML = quillResp.root.innerHTML;
    const isEmpty = quillResp.getText().trim().length === 0;

    const wrap = document.getElementById('preview-resp-wrap');
    if (isEmpty) {
        wrap.innerHTML = '<div class="preview-empty"><i class="fa-regular fa-file-lines"></i>Aún no hay contenido para previsualizar.</div>';
    } else {
        wrap.innerHTML = `<div class="preview-content">${contenidoHTML}</div>`;
    }
    document.getElementById('preview-backdrop-resp').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function cerrarPreviewResp() {
    document.getElementById('preview-backdrop-resp').classList.remove('open');
    document.body.style.overflow = '';
}

function backdropClickResp(e) {
    if (e.target === document.getElementById('preview-backdrop-resp')) cerrarPreviewResp();
}

/* ── Reset ───────────────────────────────────────────────── */
function resetRespuesta() {
    quillResp.setContents([]);
    document.getElementById('err-resp-contenido').classList.remove('show');
    const qlCont = document.querySelector('#form-respuesta .ql-container');
    if (qlCont) qlCont.style.borderColor = '';
}

/* ── Publicar respuesta ──────────────────────────────────── */
function publicarRespuesta() {
    if (!validarRespuesta()) return;

    const urlParams = new URLSearchParams(window.location.search);
    const preguntaId = urlParams.get('id');

    if (!preguntaId) {
        mostrarToast('Error: No se pudo identificar la pregunta.', 'error');
        return;
    }

    const contenidoHTML = quillResp.root.innerHTML;

    // Creamos un FormData para que PHP lo reciba limpiamente en $_POST
    const datosFormulario = new FormData();
    datosFormulario.append('pregunta_id', preguntaId);
    datosFormulario.append('contenido', contenidoHTML);

    // Petición al servidor (Promesas corregidas y unificadas)
    fetch('/pp/administrador/api/foro/guardar_respuesta.php', {
        method: 'POST',
        body: datosFormulario,
        credentials: 'include' 
    })
    .then(res => {
        if (res.status === 401) {
            throw new Error("Sesión inválida o expirada. Por favor, vuelve a iniciar sesión.");
        }
        if (!res.ok) {
            throw new Error("No se encontró el archivo o el servidor dio error (Error " + res.status + ").");
        }
        return res.json();
    })
    .then(data => {
        if (data.ok) {
            mostrarToast('¡Respuesta publicada con éxito!', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            mostrarToast(data.msg || 'Error desconocido', 'error');
        }
    })
    .catch(err => {
        console.error("Error Fetch:", err);
        mostrarToast(err.message, 'error');
    });
}

/* ── Votar respuesta ─────────────────────────────────────── */
async function votar(respuestaId, tipo) {
    if (!userId) {
        mostrarToast('Debes iniciar sesión para votar.', 'error');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('respuesta_id', respuestaId);
        formData.append('tipo', tipo);

        // Agregamos el prefijo /pp/ aquí también por consistencia de rutas en XAMPP
        const res = await fetch('/pp/administrador/api/foro/votar_respuesta.php', {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const data = await res.json();

        if (data.ok) {
            // Actualizar contador en el DOM
            const contEl = document.getElementById(`votos-${respuestaId}`);
            if (contEl) contEl.textContent = data.votos;
            mostrarToast(data.msg, 'success');
        } else {
            mostrarToast(data.msg || 'No se pudo registrar el voto.', 'error');
        }
    } catch (err) {
        mostrarToast('Error de conexión.', 'error');
    }
}

/* ── Toast ───────────────────────────────────────────────── */
function mostrarToast(mensaje, tipo = 'success') {
    document.getElementById('ask-toast')?.remove();

    const colores = {
        success: { bg: '#219EBC', icon: 'fa-circle-check' },
        error: { bg: '#FB8500', icon: 'fa-circle-exclamation' }
    };
    const { bg, icon } = colores[tipo] ?? colores.success;

    const toast = document.createElement('div');
    toast.id = 'ask-toast';
    toast.style.cssText = `
        position:fixed; bottom:28px; right:28px; z-index:9999;
        background:${bg}; color:#fff;
        padding:14px 20px; border-radius:10px;
        font-size:14px; font-weight:500;
        display:flex; align-items:center; gap:10px;
        box-shadow:0 8px 24px rgba(2,48,71,.25);
        animation:toastIn .3s ease;
    `;
    toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${mensaje}`;
    document.body.appendChild(toast);

    if (!document.getElementById('toast-style')) {
        const s = document.createElement('style');
        s.id = 'toast-style';
        s.textContent = `
            @keyframes toastIn  { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
            @keyframes toastOut { from{opacity:1} to{opacity:0;transform:translateY(8px)} }
        `;
        document.head.appendChild(s);
    }

    setTimeout(() => {
        toast.style.animation = 'toastOut .3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

/* ── Esc cierra modal ────────────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarPreviewResp();
});

/* ── Scroll automático al formulario si viene #responder ── */
if (window.location.hash === '#responder') {
    document.getElementById('form-respuesta')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}