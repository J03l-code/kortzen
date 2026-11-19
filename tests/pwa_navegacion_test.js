const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('pwa-admin.php','utf8');
function funcion(nombre) {
    const start = source.indexOf('\nfunction ' + nombre + '(');
    assert.ok(start >= 0, 'Debe existir ' + nombre);
    const end = source.indexOf('\nfunction ',start+1);
    return source.slice(start,end<0?source.length:end).replace(/<\?php[\s\S]*?\?>/g,'0');
}
const clases = () => {
    const values = new Set();
    return {add: value=>values.add(value),remove:value=>values.delete(value),contains:value=>values.has(value)};
};
const ids = [...source.matchAll(/<section id="(view\w+)" class="pwa-view-panel/g)].map(m=>m[1]);
const paneles = ids.map(id=>({id,classList:clases()}));
const botones = ['Overview','Citas','Horarios','Usuarios','Menú'].map(text=>({classList:clases(),querySelector:()=>({innerText:text})}));
const mask = {classList:clases()};
const drawer = {classList:clases()};
const nodes = new Map(paneles.map(p=>[p.id,p]));
nodes.set('pwaDrawerMask',mask);nodes.set('pwaDrawerContent',drawer);
let cargadas=0,renderizadas=0,cobro=null,cerradas=0;
const almacen = new Map();
const context = vm.createContext({
    document:{querySelectorAll:s=>s==='.pwa-view-panel'?paneles:botones,getElementById:id=>nodes.get(id)||null},
    localStorage:{setItem:(k,v)=>almacen.set(k,v)},sessionStorage:{setItem(){}},
    window:{location:{href:'https://example.test/pwa-admin.php'},history:{replaceState(){}}},URL,
    cargarDatosHorariosPwa:()=>cargadas++,renderizarCitasTab:()=>renderizadas++,cargarHorariosConfigPwa(){},
    rawCitasList:[{id:42,cliente_nombre:'Cliente de prueba'}],citaDetalleActualPwa:{id:43,cliente_nombre:'Cliente de agenda'},
    cerrarModalCitaPwa:()=>cerradas++,abrirModalTerminarCitaPwa:cita=>cobro=cita,alert:()=>{}
});
for (const nombre of ['cambiarVistaPwa','abrirDrawer','cerrarDrawer','completarCitaPwa']) vm.runInContext(funcion(nombre),context);
vm.runInContext("citaDetalleActualPwa = {id:43,cliente_nombre:'Cliente de agenda'};",context);
assert.ok(paneles.length>=10,'La PWA debe conservar todas sus vistas.');
for (const panel of paneles) {
    const nombre = panel.id==='viewHorariosConfig'?'horarios_config':panel.id.slice(4,5).toLowerCase()+panel.id.slice(5);
    context.cambiarVistaPwa(nombre);
    assert.equal(panel.classList.contains('active'),true,'Debe abrirse '+panel.id);
    assert.equal(paneles.filter(p=>p.classList.contains('active')).length,1,'Sólo una vista debe permanecer activa.');
    assert.equal(almacen.get('kortzen_pwa_admin_active_tab'),nombre);
}
context.abrirDrawer();assert.ok(mask.classList.contains('open')&&drawer.classList.contains('open'));
context.cerrarDrawer();assert.ok(!mask.classList.contains('open')&&!drawer.classList.contains('open'));
assert.ok(cargadas>0&&renderizadas>0,'Las pestañas deben ejecutar sus cargadores.');
context.completarCitaPwa(42);assert.equal(cobro.id,42);
context.completarCitaPwa(43);assert.equal(cobro.id,43);
assert.equal(cerradas,2,'El acceso rápido debe abrir el cobro con método de pago y cerrar el detalle.');
console.log(`${paneles.length} vistas de menú, drawer y acceso al cobro: correctos.`);
