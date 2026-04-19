 /* main.js
 * JavaScript principal de Vestigia CheckIn
 * Maneja: sidebar, reloj, tabs, modales, toasts, chips de filtro
 */

'use strict';

// ── Reloj en tiempo real ──────────────────────────────────────────────────────
function iniciarReloj() {
    const relojes = document.querySelectorAll('.header-reloj, .reloj-grande');
    if (!relojes.length) return;

    function actualizar() {
        const ahora = new Date();
        const h = String(ahora.getHours()).padStart(2, '0');
        const m = String(ahora.getMinutes()).padStart(2, '0');
        const s = String(ahora.getSeconds()).padStart(2, '0');
        const texto = `${h}:${m}:${s}`;
        relojes.forEach(el => { el.textContent = texto; });
    }
    actualizar();
    setInterval(actualizar, 1000);
}

// ── Sidebar responsive ────────────────────────────────────────────────────────
function iniciarSidebar() {
    const toggle  = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (!toggle || !sidebar) return;

    function abrirSidebar() {
        sidebar.classList.add('abierto');
        if (overlay) overlay.classList.add('activo');
        document.body.style.overflow = 'hidden';
    }
    function cerrarSidebar() {
        sidebar.classList.remove('abierto');
        if (overlay) overlay.classList.remove('activo');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', () => {
        sidebar.classList.contains('abierto') ? cerrarSidebar() : abrirSidebar();
    });
    if (overlay) overlay.addEventListener('click', cerrarSidebar);

    // Cerrar al hacer clic en un enlace (móvil)
    sidebar.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 900) cerrarSidebar();
        });
    });
}

// ── Tabs ──────────────────────────────────────────────────────────────────────
function iniciarTabs() {
    document.querySelectorAll('.tabs').forEach(tabsEl => {
        const botones = tabsEl.querySelectorAll('.tab-btn');
        const wrapper = tabsEl.closest('.tabs-wrapper');
        const paneles = wrapper
            ? wrapper.querySelectorAll('.tab-panel')
            : document.querySelectorAll('.tab-panel');

        botones.forEach(btn => {
            btn.addEventListener('click', () => {
                const objetivo = btn.dataset.tab;
                botones.forEach(b => b.classList.remove('activo'));
                paneles.forEach(p => p.classList.remove('activo'));
                btn.classList.add('activo');
                const panel = document.getElementById('tab-' + objetivo);
                if (panel) panel.classList.add('activo');
            });
        });
    });
}

// ── Modales ───────────────────────────────────────────────────────────────────
function iniciarModales() {
    document.querySelectorAll('[data-modal-abrir]').forEach(btn => {
        btn.addEventListener('click', () => {
            abrirModal(btn.dataset.modalAbrir);
        });
    });

    document.querySelectorAll('.modal-cerrar, [data-modal-cerrar]').forEach(btn => {
        btn.addEventListener('click', () => {
            const overlay = btn.closest('.modal-overlay');
            if (overlay) cerrarModal(overlay.id);
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) cerrarModal(overlay.id);
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay.abierto').forEach(overlay => {
                cerrarModal(overlay.id);
            });
        }
    });
}

function abrirModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) overlay.classList.add('abierto');
}

function cerrarModal(id) {
    const overlay = document.getElementById(id);
    if (overlay) overlay.classList.remove('abierto');
}

// ── Toast notifications ───────────────────────────────────────────────────────
function mostrarToast(mensaje, tipo = 'info', duracion = 3500) {
    let contenedor = document.getElementById('toast-container');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'toast-container';
        document.body.appendChild(contenedor);
    }

    const iconos = { exito: '✓', error: '✕', aviso: '⚠', info: 'ℹ' };

    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    toast.innerHTML = `<span>${iconos[tipo] || 'ℹ'}</span> ${escapeHtml(mensaje)}`;
    contenedor.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('saliendo');
        setTimeout(() => toast.remove(), 350);
    }, duracion);
}

// ── Chips de filtro ───────────────────────────────────────────────────────────
function iniciarChips() {
    document.querySelectorAll('.filtros-chips').forEach(grupo => {
        grupo.querySelectorAll('.chip').forEach(chip => {
            chip.addEventListener('click', () => {
                grupo.querySelectorAll('.chip').forEach(c => c.classList.remove('activo'));
                chip.classList.add('activo');
                grupo.dispatchEvent(new CustomEvent('chipChange', {
                    detail: { valor: chip.dataset.valor },
                    bubbles: true
                }));
            });
        });
    });
}

// ── Confirmación de acciones peligrosas ───────────────────────────────────────
function iniciarConfirmaciones() {
    document.querySelectorAll('[data-confirmar]').forEach(btn => {
        btn.addEventListener('click', e => {
            const mensaje = btn.dataset.confirmar || '¿Estás seguro de realizar esta acción?';
            if (!confirm(mensaje)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
}

// ── Autosubmit de selects de filtro ──────────────────────────────────────────
function iniciarAutosubmit() {
    document.querySelectorAll('[data-autosubmit]').forEach(el => {
        el.addEventListener('change', () => {
            const form = el.closest('form');
            if (form) form.submit();
        });
    });
}

// ── Utilidad: escapar HTML ────────────────────────────────────────────────────
function escapeHtml(str) {
    const mapa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(str).replace(/[&<>"']/g, m => mapa[m]);
}

// ── Petición AJAX (fetch) simplificada ───────────────────────────────────────
async function apiPost(url, datos) {
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        return await resp.json();
    } catch (err) {
        console.error('Error en apiPost:', err);
        return { error: 'Error de comunicación con el servidor.' };
    }
}

async function apiGet(url) {
    try {
        const resp = await fetch(url);
        return await resp.json();
    } catch (err) {
        console.error('Error en apiGet:', err);
        return { error: 'Error de comunicación con el servidor.' };
    }
}

// ── Inicialización principal ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    iniciarReloj();
    iniciarSidebar();
    iniciarTabs();
    iniciarModales();
    iniciarChips();
    iniciarConfirmaciones();
    iniciarAutosubmit();

    // Marcar enlace activo en sidebar según la URL actual
    const paginaActual = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = (link.getAttribute('href') || '').split('/').pop();
        if (href && href === paginaActual) {
            link.classList.add('activo');
        }
    });
});

// Exportar funciones para uso global
window.Vestigia = {
    toast:      mostrarToast,
    abrirModal,
    cerrarModal,
    apiPost,
    apiGet,
    escapeHtml
};