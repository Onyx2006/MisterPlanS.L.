// Siempre todo texto que viene de fuera se escribe con textContent, nunca uso innerHTML por posibles inyecciones
import { ApiError } from './api.js';

let temporizador;

export function mostrarAviso(tipo, texto) {
    const aviso = document.getElementById('aviso');
    aviso.textContent = texto;
    aviso.className = `aviso aviso-${tipo}`;
    aviso.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
    aviso.hidden = false;
    clearTimeout(temporizador);
    if (tipo !== 'error') {
        temporizador = setTimeout(() => { aviso.hidden = true; }, 5000);
    }
}

export function setCargando(elemento, cargando) {
    elemento.setAttribute('aria-busy', String(cargando));
}

export function limpiarErrores(formulario) {
    formulario.querySelectorAll('[data-error-for]').forEach((s) => { s.textContent = ''; });
    formulario.querySelectorAll('[aria-invalid]').forEach((i) => i.removeAttribute('aria-invalid'));
}

// Pinta errores junto a cada campo y devuelve los mensajes que no corresponden a ningún campo
export function pintarErrores(formulario, errores) {
    const sueltos = [];
    for (const [campo, mensaje] of Object.entries(errores)) {
        const hueco = formulario.querySelector(`[data-error-for="${CSS.escape(campo)}"]`);
        if (!hueco) {
            sueltos.push(mensaje);
            continue;
        }
        hueco.textContent = mensaje;
        formulario.querySelector(`[name="${CSS.escape(campo)}"]`)?.setAttribute('aria-invalid', 'true');
    }
    return sueltos;
}

//  Mensaje legible para cualquier error: red caída, 400, 404, 409, 422, 500...
export function mensajeError(error) {
    if (error instanceof ApiError) {
        const detalles = Object.values(error.errores);
        return detalles.length ? `${error.message} ${detalles.join(' ')}` : error.message;
    }
    return 'Ha ocurrido un error inesperado.';
}