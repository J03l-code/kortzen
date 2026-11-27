document.addEventListener('DOMContentLoaded', () => {
    const panels = document.querySelectorAll('[data-compras-barberos-panel]');
    if (!panels.length) return;

    const money = n => '$' + (Number(n) || 0).toFixed(2);
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    const cargarPanel = async (panel) => {
        const tbody = panel.querySelector('[data-cb-tbody]');
        const resumenBox = panel.querySelector('[data-cb-resumen]');
        const desde = panel.querySelector('[data-cb-desde]')?.value || '';
        const hasta = panel.querySelector('[data-cb-hasta]')?.value || '';
        const barberoId = panel.querySelector('[data-cb-barbero]')?.value || '';
        const estadoRol = panel.querySelector('[data-cb-estado]')?.value || '';
        const csrf = panel.dataset.csrf || '';

        const params = new URLSearchParams();
        if (desde) params.set('desde', desde);
        if (hasta) params.set('hasta', hasta);
        if (barberoId) params.set('barbero_id', barberoId);
        if (estadoRol) params.set('estado_rol', estadoRol);

        try {
            const resp = await fetch('api/compras_barberos.php?' + params.toString());
            const data = await resp.json();
            if (!data.success) throw new Error(data.message || 'Error al cargar');
            const rep = data.reporte;

            resumenBox.innerHTML = `
                <div style="background:#FFFBEB;border:1.5px solid #FDE68A;border-radius:10px;padding:10px 12px;">
                    <div style="font-size:0.7rem;font-weight:800;color:#92400E;text-transform:uppercase;">Pendiente de Descontar (Rol)</div>
                    <div style="font-size:1.25rem;font-weight:900;color:#B45309;">${money(rep.total_pendiente)}</div>
                </div>
                <div style="background:#ECFDF5;border:1.5px solid #A7F3D0;border-radius:10px;padding:10px 12px;">
                    <div style="font-size:0.7rem;font-weight:800;color:#065F46;text-transform:uppercase;">Ya Descontado en Rol</div>
                    <div style="font-size:1.25rem;font-weight:900;color:#047857;">${money(rep.total_descontado)}</div>
                </div>
                ${rep.resumen_barberos.map(rb => `
                    <div style="background:#F8FAFC;border:1.5px solid #E2E8F0;border-radius:10px;padding:10px 12px;">
                        <div style="font-size:0.75rem;font-weight:800;color:#1E293B;">${esc(rb.barbero)}</div>
                        <div style="font-size:0.75rem;color:#B45309;font-weight:700;margin-top:2px;">Por descontar: ${money(rb.pendiente)}</div>
                        <div style="font-size:0.72rem;color:#64748B;">Total período: ${money(rb.total)} (${rb.compras})</div>
                    </div>
                `).join('')}
            `;

            if (!rep.filas.length) {
                tbody.innerHTML = '<tr><td colspan="9" style="padding:20px;text-align:center;color:#64748B;">No hay compras de barberos en este período.</td></tr>';
                return;
            }

            tbody.innerHTML = rep.filas.map(f => {
                const esPendiente = f.estado_rol === 'pendiente_descuento';
                return `
                    <tr style="border-top:1px solid #F1F5F9;">
                        <td style="padding:9px 12px;white-space:nowrap;">${esc((f.fecha || '').slice(0, 16))}</td>
                        <td style="padding:9px 12px;font-weight:700;">${esc(f.barbero)}</td>
                        <td style="padding:9px 12px;">${esc(f.producto)}${f.cita_id ? ` <small style="color:#64748B;">(Cita #${Number(f.cita_id)})</small>` : ''}</td>
                        <td style="padding:9px 12px;font-weight:700;">${Number(f.cantidad)}</td>
                        <td style="padding:9px 12px;">${money(f.precio_unitario)}</td>
                        <td style="padding:9px 12px;font-weight:800;color:#111827;">${money(f.total)}</td>
                        <td style="padding:9px 12px;">${esc(f.sucursal)}</td>
                        <td style="padding:9px 12px;">
                            <span style="display:inline-block;padding:3px 8px;border-radius:6px;font-size:0.72rem;font-weight:800;${esPendiente ? 'background:#FEF3C7;color:#92400E;' : 'background:#DCFCE7;color:#166534;'}">
                                ${esPendiente ? 'Pendiente Rol' : 'Descontado'}
                            </span>
                        </td>
                        <td style="padding:9px 12px;">
                            ${esPendiente ? `<button type="button" data-cb-marcar="${Number(f.id)}" style="background:#111827;color:#FFF;border:none;border-radius:6px;padding:5px 9px;font-size:0.72rem;font-weight:700;cursor:pointer;">Marcar descontado</button>` : '<span style="color:#64748B;font-size:0.75rem;">—</span>'}
                        </td>
                    </tr>
                `;
            }).join('');

            tbody.querySelectorAll('[data-cb-marcar]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    btn.disabled = true;
                    const fd = new FormData();
                    fd.set('action', 'marcar_descontado');
                    fd.set('compra_id', btn.dataset.cbMarcar);
                    fd.set('csrf_token', csrf);
                    try {
                        const r = await fetch('api/compras_barberos.php', { method: 'POST', body: fd });
                        const res = await r.json();
                        if (!res.success) throw new Error(res.message || 'No se pudo actualizar');
                        cargarPanel(panel);
                    } catch (e) {
                        alert(e.message);
                        btn.disabled = false;
                    }
                });
            });
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="9" style="padding:20px;text-align:center;color:#DC2626;">${esc(err.message)}</td></tr>`;
        }
    };

    panels.forEach(panel => {
        panel.querySelector('[data-cb-buscar]')?.addEventListener('click', () => cargarPanel(panel));
        panel.querySelectorAll('[data-cb-desde],[data-cb-hasta],[data-cb-barbero],[data-cb-estado]').forEach(el => {
            el.addEventListener('change', () => cargarPanel(panel));
        });
        cargarPanel(panel);
    });

    window.recargarComprasBarberos = () => panels.forEach(cargarPanel);

    // Conectar botones de apertura del modal Venta a Barbero / Personal
    document.querySelectorAll('[data-abrir-venta-barbero]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('modalCompraBarberoGlobal');
            if (modal) {
                modal.style.display = 'flex';
            }
        });
    });
});
