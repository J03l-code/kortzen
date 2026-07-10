/**
 * KORTZEN - PWA Registration and Management
 */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('Service Worker registrado con éxito:', registration.scope);
      })
      .catch(error => {
        console.log('Fallo al registrar el Service Worker:', error);
      });
  });
}
