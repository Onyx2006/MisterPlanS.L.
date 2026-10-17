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

const fechaES = (iso) => iso.split('-').reverse().join('/');
const importeES = (valor) => `${Number(valor).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`;

function celda(texto, clase) {
    const td = document.createElement('td');
    td.textContent = texto;
    if (clase) td.className = clase;
    return td;
}

function pintarFilas(reservas) {
    if (reservas.length === 0) {
        const fila = document.createElement('tr');
        const td = celda('No hay reservas con esos filtros.');
        td.colSpan = 8;
        fila.append(td);
        tbody.replaceChildren(fila);
        return;
    }

    tbody.replaceChildren(...reservas.map((r) => {
        const fila = document.createElement('tr');

        const estado = document.createElement('span');
        estado.className = `estado estado-${r.estado}`;
        estado.textContent = r.estado;
        const tdEstado = document.createElement('td');
        tdEstado.append(estado);

        const enlace = document.createElement('a');
        enlace.href = `/reservas/${encodeURIComponent(r.id)}`;
        enlace.textContent = 'Ver';
        const tdAcciones = document.createElement('td');
        tdAcciones.append(enlace);
        if (r.estado !== 'cancelada') {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'peligro';
            boton.dataset.cancelar = String(r.id);
            boton.textContent = 'Cancelar';
            tdAcciones.append(' ', boton);
        }

        fila.append(
            celda(String(r.id)), celda(r.nombre_huesped), celda(r.nombre_alojamiento),
            celda(fechaES(r.fecha_entrada)), celda(fechaES(r.fecha_salida)),
            tdEstado, celda(importeES(r.importe), 'num'), tdAcciones,
        );
        return fila;
    }));
}

function pintarResumen(meta) {
    resumen.textContent = `${meta.total} reserva${meta.total === 1 ? '' : 's'} · página ${meta.page} de ${meta.total_pages}`;
}

function pintarPaginacion(meta) {
    const enlaces = [];
    const crear = (pagina, texto, rel) => {
        const a = document.createElement('a');
        const params = leerFiltros(pagina);
        a.href = params.size ? `/?${params}` : '/';
        a.dataset.pagina = String(pagina);
        a.rel = rel;
        a.textContent = texto;
        return a;
    };
    if (meta.page > 1) enlaces.push(crear(meta.page - 1, '← Anterior', 'prev'));
    if (meta.page < meta.total_pages) enlaces.push(crear(meta.page + 1, 'Siguiente →', 'next'));
    paginacion.replaceChildren(...enlaces);
}

formFiltros.addEventListener('submit', (ev) => {
    ev.preventDefault();
    cargar(1);
});

formFiltros.querySelector('#limpiar').addEventListener('click', (ev) => {
    ev.preventDefault();
    formFiltros.reset();
    // reset() restaura los valores iniciales de la página; si venían de la URL los vacío a mano
    formFiltros.querySelectorAll('input, select').forEach((c) => { c.value = ''; });
    cargar(1);
});

paginacion.addEventListener('click', (ev) => {
    const enlace = ev.target.closest('a');
    if (!enlace) return;
    ev.preventDefault();
    const pagina = enlace.dataset.pagina ?? new URL(enlace.href).searchParams.get('page') ?? '1';
    cargar(Number(pagina));
});