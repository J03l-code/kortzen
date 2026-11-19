document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-historial-ventas]').forEach(root => {
        const form = root.querySelector('[data-filtros-ventas]');
        const estado = root.querySelector('[data-estado-ventas]');
        const cuerpo = root.querySelector('[data-filas-ventas]');
        const vendedores = root.querySelector('[data-vendedores-historial]');
        let pagina = 1;
        let paginas = 1;
        let peticion = 0;
        const cargarVendedores = async () => {
            if (!vendedores) return;
            try {
                const respuesta = await fetch('api/reportes_ventas.php?accion=vendedores', {headers: {'Accept': 'application/json'}});
                const datos = await respuesta.json();
                if (!datos.success || !Array.isArray(datos.vendedores)) throw new Error('Vendedores no disponibles.');
                vendedores.replaceChildren(new Option(datos.solo_propias ? 'Mis ventas' : 'Todos los vendedores', ''));
                datos.vendedores.forEach(vendedor => {
                    vendedores.append(new Option(vendedor.nombre + (Number(vendedor.activo) ? '' : ' (inactivo)'), String(vendedor.id)));
                });
                vendedores.disabled = false;
            } catch (error) {
                vendedores.replaceChildren(new Option('Vendedores no disponibles; recarga para reintentar', ''));
                vendedores.disabled = true;
            }
        };
        const cargar = async () => {
            const actual = ++peticion;
            const params = new URLSearchParams(new FormData(form));
            params.set('pagina', pagina);
            root.querySelectorAll('[data-exportar-ventas]').forEach(enlace => {
                const exportParams = new URLSearchParams(params);
                exportParams.set('formato', enlace.dataset.exportarVentas);
                enlace.href = 'api/reportes_ventas.php?' + exportParams;
            });
            estado.textContent = 'Cargando historial…';
            try {
                const respuesta = await fetch('api/reportes_ventas.php?' + params, {headers: {'Accept': 'application/json'}});
                const datos = await respuesta.json();
                if (actual !== peticion) return;
                if (!datos.success) throw new Error(datos.message || 'No se pudo cargar el historial.');
                const r = datos.reporte;
                pagina = r.pagina;
                paginas = r.paginas;
                cuerpo.replaceChildren();
                r.filas.forEach(fila => {
                    const tr = document.createElement('tr');
                    [fila.fecha, fila.producto, fila.cantidad, '$' + Number(fila.total).toFixed(2), fila.vendedor, fila.sucursal, fila.metodo_pago_label].forEach(valor => {
                        const td = document.createElement('td');
                        td.textContent = valor;
                        tr.append(td);
                    });
                    cuerpo.append(tr);
                });
                estado.textContent = `${r.registros} ventas · Total del filtro: $${Number(r.total).toFixed(2)}`;
                root.querySelector('[data-paginacion-ventas]').textContent = `Página ${pagina} de ${paginas}`;
                root.querySelector('[data-pagina-ventas="-1"]').disabled = pagina <= 1;
                root.querySelector('[data-pagina-ventas="1"]').disabled = pagina >= paginas;
            } catch (error) {
                if (actual === peticion) { cuerpo.replaceChildren(); estado.textContent = error.message; }
            }
        };
        form.addEventListener('submit', event => { event.preventDefault(); if (form.reportValidity()) { pagina = 1; cargar(); } });
        root.querySelectorAll('[data-pagina-ventas]').forEach(boton => boton.addEventListener('click', () => {
            pagina = Math.max(1, Math.min(paginas, pagina + Number(boton.dataset.paginaVentas)));
            cargar();
        }));
        cargarVendedores();
        cargar();
    });
    document.querySelectorAll('[data-inventario-tabs]').forEach(root => {
        root.querySelectorAll('[data-inventario-tab]').forEach(boton => boton.addEventListener('click', () => {
            root.querySelectorAll('[data-inventario-panel]').forEach(panel => { panel.hidden = panel.dataset.inventarioPanel !== boton.dataset.inventarioTab; });
            root.querySelectorAll('[data-inventario-tab]').forEach(tab => tab.setAttribute('aria-selected', String(tab === boton)));
        }));
    });
});
