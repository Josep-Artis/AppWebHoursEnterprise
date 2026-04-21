/**
 * fichaje.js
 * Lógica JavaScript para la página de fichaje
 * Vestigia CheckIn
 */

'use strict';

// ── Estado del fichaje ────────────────────────────────────────────────────────
let fichajeActivo = false;
let horaEntrada   = null;
let timerInterval = null;

// ── Inicialización ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const btnEntrada = document.getElementById('btn-entrada');
    const btnSalida  = document.getElementById('btn-salida');

    // Verificar estado actual del fichaje
    verificarEstadoFichaje();

    // Botón de entrada
    if (btnEntrada) {
        btnEntrada.addEventListener('click', () => registrarEntrada());
    }

    // Botón de salida
    if (btnSalida) {
        btnSalida.addEventListener('click', () => registrarSalida());
    }
});

// ── Verificar estado del fichaje (al cargar la página) ────────────────────────
async function verificarEstadoFichaje() {
    const respuesta = await Vestigia.apiGet('../api/fichar.php?accion=estado');

    if (respuesta.error) {
        console.error('Error al verificar fichaje:', respuesta.error);
        return;
    }

    fichajeActivo = respuesta.activo;
    horaEntrada   = respuesta.hora_entrada || null;

    actualizarUI(respuesta);

    // Si hay fichaje activo, iniciar contador de tiempo
    if (fichajeActivo && horaEntrada) {
        iniciarContador(horaEntrada);
    }
}

// ── Registrar entrada ─────────────────────────────────────────────────────────
async function registrarEntrada() {
    const proyectoId  = document.getElementById('proyecto-id')?.value;
    const teletrabajo = document.getElementById('teletrabajo')?.checked ? 1 : 0;

    if (!proyectoId) {
        Vestigia.toast('Por favor, selecciona un proyecto antes de fichar.', 'aviso');
        return;
    }

    const btnEntrada = document.getElementById('btn-entrada');
    btnEntrada.disabled = true;
    btnEntrada.innerHTML = '<span class="spinner"></span> Fichando...';

    const respuesta = await Vestigia.apiPost('../api/fichar.php', {
        accion:      'entrada',
        proyecto_id: parseInt(proyectoId),
        teletrabajo
    });

    btnEntrada.disabled = false;

    if (respuesta.error) {
        btnEntrada.innerHTML = '🕐 Registrar entrada';
        Vestigia.toast(respuesta.error, 'error');
        return;
    }

    // Actualizar UI
    fichajeActivo = true;
    horaEntrada   = respuesta.hora_entrada;

    btnEntrada.innerHTML = '🕐 Registrar entrada';
    actualizarEstadoVisual(true, respuesta.hora_entrada, respuesta.tarde, respuesta.minutos_retraso);
    iniciarContador(respuesta.hora_entrada);
    Vestigia.toast('Entrada registrada correctamente.', 'exito');

    // Añadir al historial de hoy
    agregarEntradaHistorial(respuesta);

    // Recargar para activar botón salida
    setTimeout(() => location.reload(), 1500);
}

// ── Registrar salida ──────────────────────────────────────────────────────────
async function registrarSalida() {
    if (!confirm('¿Confirmas que quieres registrar tu salida?')) return;

    const btnSalida = document.getElementById('btn-salida');
    btnSalida.disabled = true;
    btnSalida.innerHTML = '<span class="spinner"></span> Registrando...';

    const respuesta = await Vestigia.apiPost('../api/fichar.php', {
        accion: 'salida'
    });

    btnSalida.disabled = false;

    if (respuesta.error) {
        btnSalida.innerHTML = '🏁 Registrar salida';
        Vestigia.toast(respuesta.error, 'error');
        return;
    }

    fichajeActivo = false;
    detenerContador();

    btnSalida.innerHTML = '🏁 Registrar salida';
    actualizarEstadoVisual(false);
    Vestigia.toast('Salida registrada. ¡Hasta mañana!', 'exito');

    // Recargar historial
    setTimeout(() => location.reload(), 1500);
}

// ── Actualizar UI completa según estado ──────────────────────────────────────
function actualizarUI(estado) {
    const btnEntrada = document.getElementById('btn-entrada');
    const btnSalida  = document.getElementById('btn-salida');

    if (estado.activo) {
        // Hay fichaje activo
        if (btnEntrada) btnEntrada.disabled = true;
        if (btnSalida)  btnSalida.disabled  = false;
        actualizarEstadoVisual(true, estado.hora_entrada, estado.tarde, estado.minutos_retraso);
    } else {
        // Sin fichaje activo
        if (btnEntrada) btnEntrada.disabled = false;
        if (btnSalida)  btnSalida.disabled  = true;
        actualizarEstadoVisual(false);
    }
}

// ── Actualizar indicador visual de estado ─────────────────────────────────────
function actualizarEstadoVisual(activo, horaEnt = null, tarde = false, minutosRetraso = 0) {
    const estadoEl = document.getElementById('estado-fichaje');
    const infoEl   = document.getElementById('info-fichaje');

    if (!estadoEl) return;

    if (activo) {
        estadoEl.className = 'estado-fichaje entrado';
        estadoEl.textContent = '● En oficina';

        if (infoEl && horaEnt) {
            let html = `<strong>Entrada:</strong> ${horaEnt.substring(0, 5)}`;
            if (tarde && minutosRetraso > 0) {
                html += ` <span class="badge badge-amarillo">⚠ ${minutosRetraso} min de retraso</span>`;
            }
            infoEl.innerHTML = html;
        }
    } else {
        estadoEl.className = 'estado-fichaje sin-fichar';
        estadoEl.textContent = '○ Sin fichar';
        if (infoEl) infoEl.innerHTML = 'No has fichado hoy todavía.';
    }
}

// ── Contador de tiempo trabajado ──────────────────────────────────────────────
function iniciarContador(horaEntStr) {
    detenerContador();

    const contadorEl = document.getElementById('contador-tiempo');
    if (!contadorEl) return;

    const hoy      = new Date();
    const [h, m, s] = horaEntStr.split(':').map(Number);
    const entrada  = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate(), h, m, s || 0);

    timerInterval = setInterval(() => {
        const ahora      = new Date();
        const diffSeg    = Math.max(0, Math.floor((ahora - entrada) / 1000));
        const horas      = Math.floor(diffSeg / 3600);
        const minutos    = Math.floor((diffSeg % 3600) / 60);
        const segundos   = diffSeg % 60;

        contadorEl.textContent =
            `${String(horas).padStart(2,'0')}:${String(minutos).padStart(2,'0')}:${String(segundos).padStart(2,'0')}`;
    }, 1000);
}

function detenerContador() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

// ── Añadir entrada al historial de hoy ───────────────────────────────────────
function agregarEntradaHistorial(datos) {
    const lista = document.getElementById('historial-hoy');
    if (!lista) return;

    const item = document.createElement('div');
    item.className = 'timeline-item';
    item.innerHTML = `
        <div class="timeline-hora">${datos.hora_entrada.substring(0, 5)}</div>
        <div class="timeline-desc">
            Entrada registrada
            ${datos.teletrabajo ? '<span class="badge badge-azul">Teletrabajo</span>' : ''}
            ${datos.tarde ? `<span class="badge badge-amarillo">${datos.minutos_retraso} min tarde</span>` : ''}
        </div>
    `;

    if (lista.firstChild) {
        lista.insertBefore(item, lista.firstChild);
    } else {
        lista.appendChild(item);
    }
}
