import { reservasApi, ApiError } from './api.js';
import { mostrarAviso, setCargando, limpiarErrores, pintarErrores, mensajeError } from './ui.js';

const formFiltros = document.getElementById('filtros');
const formCrear = document.getElementById('form-reserva');
const listado = document.getElementById('listado');
const tbody = document.querySelector('#tabla-reservas tbody');
const resumen = document.getElementById('resumen');
const paginacion = document.getElementById('paginacion');

const CAMPOS_FILTRO = ['estado', 'desde', 'hasta', 'huesped'];
let paginaActual = Number(new URLSearchParams(location.search).get('page')) || 1;
let controlador = null;

// Filtros y listado
function leerFiltros(pagina = 1) {
    const params = new URLSearchParams();
    const datos = new FormData(formFiltros);
    for (const campo of CAMPOS_FILTRO) {
        const valor = String(datos.get(campo) ?? '').trim();
        if (valor) params.set(campo, valor);
    }
    if (pagina > 1) params.set('page', String(pagina));
    return params;
}

async function cargar(pagina = 1) {
    controlador?.abort();
    const mio = (controlador = new AbortController());
    const params = leerFiltros(pagina);

    setCargando(listado, true);
    try {
        const { data, meta } = await reservasApi.listar(params, mio.signal);
        paginaActual = meta.page;
        pintarFilas(data);
        pintarResumen(meta);
        pintarPaginacion(meta);
        history.replaceState(null, '', params.size ? `/?${params}` : '/');
    } catch (e) {
        if (e.name === 'AbortError') return;
        mostrarAviso('error', mensajeError(e));
    } finally {
        if (controlador === mio) setCargando(listado, false);
    }
}