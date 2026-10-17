import { reservasApi } from './api.js';
import { mostrarAviso, mensajeError } from './ui.js';

const contenedor = document.getElementById('acciones');

const TEXTOS = {
    confirmada: (id) => `¿Confirmar la reserva #${id}?`,
    cancelada: (id) => `¿Cancelar la reserva #${id}? Esta acción no se puede deshacer`,
};

contenedor?.addEventListener('click', async (ev) => {
    const boton = ev.target.closest('button[data-accion]');
    if (!boton) return;

    const { accion, id } = boton.dataset;
    if (!window.confirm(TEXTOS[accion]?.(id) ?? '¿Continuar?')) return;

    const botones = contenedor.querySelectorAll('button');
    botones.forEach((b) => { b.disabled = true; });
    try {
        await reservasApi.cambiarEstado(Number(id), accion);
        // El historial lo pinta Twig: recargar mantiene una única fuente de verdad para esa vista
        location.reload();
    } catch (e) {
        mostrarAviso('error', mensajeError(e));
        botones.forEach((b) => { b.disabled = false; });
    }
});