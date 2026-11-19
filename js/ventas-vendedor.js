// El vendedor es obligatorio cuando el cobro incluye productos.
function actualizarVendedorCobro(form) {
    const vendedor = form.querySelector('[data-vendedor-cobro]');
    if (!vendedor) return;
    vendedor.required = Array.from(form.querySelectorAll('[name="materiales[]"]'))
        .some(producto => producto.value !== '');
}

document.addEventListener('change', event => {
    const form = event.target.closest('form');
    if (form) actualizarVendedorCobro(form);
});

document.addEventListener('reset', event => {
    if (event.target.matches('form')) {
        requestAnimationFrame(() => actualizarVendedorCobro(event.target));
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(actualizarVendedorCobro);
});
