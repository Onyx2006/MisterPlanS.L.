// Capa de acceso a la API. Los errores se normalizan en ApiError para que la UI no tenga que
// distinguir entre "sin red", "400/422 con detalle por campo" o "500"

export class ApiError extends Error {
    constructor(mensaje, { status = 0, errores = {} } = {}) {
        super(mensaje);
        this.name = 'ApiError';
        this.status = status;
        this.errores = errores; // { campo: 'mensaje' } cuando el servidor valida
    }
}

async function peticion(ruta, { metodo = 'GET', cuerpo, signal } = {}) {
    const cabeceras = { Accept: 'application/json' };
    if (cuerpo !== undefined) {
        cabeceras['Content-Type'] = 'application/json';
    }

    let respuesta;
    try {
        respuesta = await fetch(ruta, {
            method: metodo,
            headers: cabeceras,
            body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
            signal,
        });
    } catch (e) {
        if (e.name === 'AbortError') throw e; // cancelación intencionada: la gestiona quien la provoca
        throw new ApiError('No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.');
    }

    let contenido = null;
    try {
        contenido = await respuesta.json();
    } catch {
        // Cuerpo vacío o no JSON (p. ej. una página de error de proxy): se trata abajo según el status
    }

    if (!respuesta.ok) {
        const mensaje = contenido?.error?.message
            ?? (respuesta.status >= 500 ? 'Error interno del servidor. Inténtalo más tarde.' : `Error ${respuesta.status}.`);
        throw new ApiError(mensaje, { status: respuesta.status, errores: contenido?.error?.errors ?? {} });
    }
    if (contenido === null) {
        throw new ApiError('La respuesta del servidor no es válida.', { status: respuesta.status });
    }
    return contenido;
}

export const reservasApi = {
    listar: (params, signal) => peticion(`/api/reservas${params.size ? `?${params}` : ''}`, { signal }),
    crear: (datos) => peticion('/api/reservas', { metodo: 'POST', cuerpo: datos }),
    cambiarEstado: (id, estado) =>
        peticion(`/api/reservas/${encodeURIComponent(id)}/estado`, { metodo: 'PATCH', cuerpo: { estado } }),
};