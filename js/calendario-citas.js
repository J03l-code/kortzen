// Distribuir bloques superpuestos sin tapar reservas de la misma columna.
function distribuirCitasCalendario(eventos) {
    const minuto = e => Number(e.fecha_hora.slice(11,13)) * 60 + Number(e.fecha_hora.slice(14,16));
    const ordenados = [...eventos].sort((a,b) => minuto(a)-minuto(b) || Number(a.id)-Number(b.id));
    const posiciones = new Map();
    let grupo = [];
    let finGrupo = -1;
    const cerrarGrupo = () => {
        const finales = [];
        const asignaciones = grupo.map(e => {
            const inicio = minuto(e);
            let carril = finales.findIndex(fin => fin <= inicio);
            if (carril < 0) carril = finales.length;
            finales[carril] = inicio + Number(e.duracion);
            return {id: e.id, carril};
        });
        asignaciones.forEach(e => posiciones.set(e.id,{carril:e.carril,columnas:finales.length}));
    };
    ordenados.forEach(e => {
        const inicio = minuto(e);
        if (grupo.length && inicio >= finGrupo) { cerrarGrupo(); grupo = []; finGrupo = -1; }
        grupo.push(e);
        finGrupo = Math.max(finGrupo,inicio + Number(e.duracion));
    });
    cerrarGrupo();
    return posiciones;
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-gestion-calendario]');
    if (!root) return;
    const lista = document.getElementById('citasVistaLista');
    const cuadricula = root.querySelector('[data-cuadricula-calendario]');
    const fecha = root.querySelector('[data-fecha-calendario]');
    const fechaLista = fecha.value;
    const estado = root.querySelector('[data-estado-calendario]');
    let version = 0;
    const nodo = (tipo, texto, clase) => { const el = document.createElement(tipo); if (texto !== undefined) el.textContent = texto; if (clase) el.className = clase; return el; };
    const cambiarVista = vista => {
        if (vista === 'lista' && fecha.value !== fechaLista) {
            const url = new URL(window.location.href); url.searchParams.set('fecha',fecha.value); url.searchParams.set('vista','lista');
            window.location.assign(url); return;
        }
        cuadricula.hidden = vista !== 'calendario';
        if (lista) lista.hidden = vista !== 'lista';
        root.querySelectorAll('[data-vista-citas]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.vistaCitas === vista)));
        const url = new URL(window.location.href); url.searchParams.set('vista',vista); history.replaceState(null,'',url);
    };
    root.querySelectorAll('[data-vista-citas]').forEach(b => b.addEventListener('click', () => cambiarVista(b.dataset.vistaCitas)));
    const cargar = async () => {
        const actual = ++version;
        estado.textContent = 'Cargando citas…';
        try {
            const params = new URLSearchParams({fecha: fecha.value, sucursal_id: root.dataset.sucursal});
            const r = await fetch('api/calendario_citas.php?' + params);
            const datos = await r.json();
            if (actual !== version) return;
            if (!datos.success) throw new Error(datos.message);
            const pendientesRoot = root.querySelector('[data-pendientes-citas]');
            const pendientesLista = root.querySelector('[data-lista-pendientes]');
            pendientesLista.replaceChildren();
            pendientesRoot.hidden = !datos.pendientes.length;
            datos.pendientes.forEach(cita => {
                const fila = nodo('div', undefined, 'kortzen-reasignar');
                fila.id = 'cita-pendiente-' + Number(cita.id);
                fila.append(nodo('span', `#${cita.id} · ${cita.fecha_hora.slice(0,16)} · ${cita.cliente} · ${cita.servicio} · ${cita.sucursal}. Anterior: ${cita.barbero_original || 'Sin registro'}. ${cita.motivo_reasignacion || ''}`));
                const select = nodo('select');
                select.setAttribute('aria-label', 'Barbero para cita ' + cita.id);
                select.append(new Option('Seleccionar barbero…',''));
                datos.barberos.forEach(b => select.append(new Option(b.nombre,b.id)));
                const boton = nodo('button','Reasignar','btn-primary'); boton.type = 'button';
                boton.addEventListener('click', async () => {
                    if (!select.value) { estado.textContent = 'Selecciona un barbero.'; select.focus(); return; }
                    boton.disabled = true;
                    try {
                        const fd = new FormData(); fd.set('cita_id',cita.id); fd.set('barbero_id',select.value); fd.set('csrf_token',root.dataset.csrf);
                        const respuesta = await fetch('api/reasignar_cita.php',{method:'POST',body:fd});
                        const resultado = await respuesta.json();
                        if (!resultado.success) throw new Error(resultado.message);
                        window.location.reload();
                    } catch (e) { estado.textContent = e.message; boton.disabled = false; }
                });
                fila.append(select,boton); pendientesLista.append(fila);
            });
            cuadricula.replaceChildren();
            const columnas = new Map(datos.barberos.map(b => [String(b.id),b.nombre]));
            datos.eventos.forEach(e => { if (!Number(e.reasignacion_pendiente) && !columnas.has(String(e.barbero_id))) columnas.set(String(e.barbero_id),e.barbero_original || 'Barbero anterior'); });
            if (datos.eventos.some(e => Number(e.reasignacion_pendiente))) columnas.set('sin-asignar','Sin barbero asignado');
            const minutos = texto => { const [h,m] = texto.split(':').map(Number); return h * 60 + m; };
            const inicios = datos.eventos.map(e => minutos(e.fecha_hora.slice(11,16)));
            const finales = datos.eventos.map(e => minutos(e.fecha_hora.slice(11,16)) + Number(e.duracion));
            const inicio = Math.min(8 * 60,...inicios);
            const fin = Math.max(21 * 60,...finales);
            const alto = (fin - inicio) * 1.3;
            const reloj = nodo('div',undefined,'kortzen-columna-reloj');
            reloj.append(nodo('div','Hora','kortzen-cal-cabecera'));
            const horas = nodo('div'); horas.style.height = alto + 'px'; horas.style.position = 'relative';
            for (let m = Math.ceil(inicio/60)*60; m < fin; m += 60) {
                const marca = nodo('span',String(Math.floor(m/60)).padStart(2,'0') + ':00'); marca.style.cssText = `position:absolute;top:${(m-inicio)*1.3}px;font-size:12px`; horas.append(marca);
            }
            reloj.append(horas); cuadricula.append(reloj);
            columnas.forEach((nombre,id) => {
                const columna = nodo('div',undefined,'kortzen-columna-barbero');
                columna.append(nodo('div',nombre,'kortzen-cal-cabecera'));
                const cuerpo = nodo('div',undefined,'kortzen-cal-dia'); cuerpo.style.height = alto + 'px';
                const eventos = datos.eventos.filter(e => (Number(e.reasignacion_pendiente) ? 'sin-asignar' : String(e.barbero_id)) === id);
                const posiciones = distribuirCitasCalendario(eventos);
                eventos.forEach(e => {
                    const tarjeta = nodo(datos.puede_administrar ? 'a' : 'div',undefined,'kortzen-cal-cita');
                    if (datos.puede_administrar) tarjeta.href = 'citas_editar.php?id=' + Number(e.id);
                    if (Number(e.reasignacion_pendiente)) {
                        tarjeta.href = '#cita-pendiente-' + Number(e.id);
                        tarjeta.addEventListener('click', event => {
                            event.preventDefault();
                            const pendiente = document.getElementById('cita-pendiente-' + Number(e.id));
                            if (pendiente) { pendiente.scrollIntoView({behavior:'smooth',block:'center'}); pendiente.querySelector('select').focus(); }
                        });
                    }
                    const minuto = minutos(e.fecha_hora.slice(11,16));
                    const posicion = posiciones.get(e.id);
                    tarjeta.style.top = ((minuto-inicio)*1.3) + 'px';
                    tarjeta.style.height = Math.max(4,Number(e.duracion)*1.3-2) + 'px';
                    tarjeta.style.width = (100/posicion.columnas) + '%';
                    tarjeta.style.left = (posicion.carril*100/posicion.columnas) + '%';
                    tarjeta.dataset.estado = Number(e.reasignacion_pendiente) ? 'sin_asignar' : e.estado;
                    tarjeta.append(nodo('strong',e.fecha_hora.slice(11,16) + ' · ' + e.cliente),nodo('span',e.servicio),nodo('small',Number(e.reasignacion_pendiente) ? 'Sin barbero asignado' : e.estado.replaceAll('_',' ')));
                    tarjeta.title = `${e.cliente} · ${e.servicio} · ${nombre} · ${e.sucursal}`;
                    cuerpo.append(tarjeta);
                });
                columna.append(cuerpo); cuadricula.append(columna);
            });
            estado.textContent = `${datos.eventos.length} citas en el día · ${datos.pendientes.length} pendientes de reasignación`;
        } catch (e) { if (actual === version) estado.textContent = e.message || 'No se pudo cargar el calendario.'; }
    };
    fecha.addEventListener('change', cargar);
    cambiarVista(new URLSearchParams(location.search).get('vista') === 'calendario' ? 'calendario' : 'lista');
    cargar();
});
