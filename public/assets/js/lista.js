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

// Cancelar desde el listado
tbody.addEventListener('click', async (ev) => {
    const boton = ev.target.closest('button[data-cancelar]');
    if (!boton) return;

    const id = Number(boton.dataset.cancelar);
    if (!window.confirm(`¿Cancelar la reserva #${id}? Esta acción no se puede deshacer`)) return;

    boton.disabled = true;
    try {
        await reservasApi.cambiarEstado(id, 'cancelada');
        mostrarAviso('ok', `Reserva #${id} cancelada.`);
        await cargar(paginaActual);
    } catch (e) {
        mostrarAviso('error', mensajeError(e));
        boton.disabled = false;
    }
});

// Crear reserva
// Validación de frontend (me la enseñaron en el ciclo superior) pero la que cuenta es la del backend ya que esta se la pueden saltar
function validarCliente(d) {
    const e = {};
    if (d.nombre_huesped.length < 2) e.nombre_huesped = 'Indica el nombre (mínimo 2 caracteres).';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email_huesped)) e.email_huesped = 'Email no válido.';
    if (d.nombre_alojamiento.length < 2) e.nombre_alojamiento = 'Indica el alojamiento.';
    if (!d.fecha_entrada) e.fecha_entrada = 'Indica la fecha de entrada.';
    if (!d.fecha_salida) e.fecha_salida = 'Indica la fecha de salida.';
    else if (d.fecha_entrada && d.fecha_salida <= d.fecha_entrada) e.fecha_salida = 'La salida debe ser posterior a la entrada.';
    if (!/^\d{1,8}([.,]\d{1,2})?$/.test(d.importe) || Number(d.importe.replace(',', '.')) <= 0) {
        e.importe = 'Importe no válido (ej. 120,50).';
    }
    return e;
}

formCrear.addEventListener('submit', async (ev) => {
    ev.preventDefault(); // Buenas prácticas que me enseñaron en el ciclo superior
    limpiarErrores(formCrear);

    const datos = Object.fromEntries(new FormData(formCrear));
    for (const clave of Object.keys(datos)) datos[clave] = String(datos[clave]).trim();
    datos.importe = datos.importe.replace(',', '.');

    const errores = validarCliente({ ...datos, importe: datos.importe });
    if (Object.keys(errores).length) {
        pintarErrores(formCrear, errores);
        formCrear.querySelector('[aria-invalid="true"]')?.focus();
        return;
    }

    const boton = formCrear.querySelector('button[type="submit"]');
    boton.disabled = true;
    setCargando(formCrear, true);
    try {
        const { data } = await reservasApi.crear(datos);
        formCrear.reset();
        mostrarAviso('ok', `Reserva #${data.id} creada correctamente.`);
        await cargar(1);
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            // El servidor es la autoridad: sus errores por campo se muestran junto a cada input.
            const sueltos = pintarErrores(formCrear, e.errores);
            mostrarAviso('error', ['Revisa el formulario.', ...sueltos].join(' '));
            formCrear.querySelector('[aria-invalid="true"]')?.focus();
        } else {
            mostrarAviso('error', mensajeError(e));
        }
    } finally {
        boton.disabled = false;
        setCargando(formCrear, false);
    }
});