/**
 * KORTZEN - PWA Core Manager
 * Handles Service Worker registration, custom install prompts for Android/iOS, and notifications
 */

// Register Service Worker
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

// Global PWA State
let deferredPrompt;

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', () => {
  // Inject CSS for PWA banners
  const style = document.createElement('style');
  style.textContent = `
    .pwa-banner {
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      width: 90%;
      max-width: 450px;
      background: #111111;
      border: 1px solid #333333;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      z-index: 99999;
      display: flex;
      flex-direction: column;
      gap: 12px;
      color: #ffffff;
      font-family: sans-serif;
      animation: slideUp 0.4s ease-out;
    }
    .pwa-banner__header {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .pwa-banner__icon {
      width: 48px;
      height: 48px;
      border-radius: 8px;
      background: #222;
      object-fit: cover;
    }
    .pwa-banner__info {
      flex: 1;
    }
    .pwa-banner__title {
      font-size: 15px;
      font-weight: 600;
      margin: 0 0 2px 0;
    }
    .pwa-banner__desc {
      font-size: 12px;
      color: #aaaaaa;
      margin: 0;
    }
    .pwa-banner__actions {
      display: flex;
      justify-content: flex-end;
      gap: 8px;
      margin-top: 4px;
    }
    .pwa-banner__btn {
      padding: 8px 16px;
      font-size: 12px;
      font-weight: 600;
      border-radius: 6px;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      transition: all 0.2s ease;
    }
    .pwa-banner__btn--install {
      background: #ffffff;
      color: #111111;
      border: 1px solid #ffffff;
    }
    .pwa-banner__btn--install:hover {
      background: #dddddd;
    }
    .pwa-banner__btn--dismiss {
      background: transparent;
      color: #aaaaaa;
      border: 1px solid transparent;
    }
    .pwa-banner__btn--dismiss:hover {
      color: #ffffff;
    }
    @keyframes slideUp {
      from { transform: translate(-50%, 100px); opacity: 0; }
      to { transform: translate(-50%, 0); opacity: 1; }
    }
  `;
  document.head.appendChild(style);

  // Check device type
  const userAgent = window.navigator.userAgent.toLowerCase();
  const isIos = /iphone|ipad|ipod/.test(userAgent);
  const isInStandaloneMode = ('standalone' in window.navigator) && (window.navigator.standalone);

  // Show iOS-specific install prompt if inside Safari but not added to Home Screen
  if (isIos && !isInStandaloneMode) {
    // Only show if they haven't dismissed it in this session
    if (!sessionStorage.getItem('pwa-ios-dismissed')) {
      showIosInstallPrompt();
    }
  }

  // Handle Chrome/Android install prompt
  window.addEventListener('beforeinstallprompt', (e) => {
    // Prevent default browser banner
    e.preventDefault();
    deferredPrompt = e;
    
    // Only show if not already in standalone mode and not dismissed
    if (!isInStandaloneMode && !sessionStorage.getItem('pwa-android-dismissed')) {
      showAndroidInstallPrompt();
    }
  });

  // Automatically request notifications if user is on dashboard and hasn't granted yet
  if (window.location.pathname.includes('cliente-dashboard.php')) {
    setTimeout(() => {
      checkAndPromptNotifications();
    }, 2000);
  }
});

// Show Android install banner
function showAndroidInstallPrompt() {
  const banner = document.createElement('div');
  banner.className = 'pwa-banner';
  banner.innerHTML = `
    <div class="pwa-banner__header">
      <img src="/assets/icons/favicon.png" class="pwa-banner__icon" alt="KORTZEN">
      <div class="pwa-banner__info">
        <h4 class="pwa-banner__title">KORTZEN Barbería</h4>
        <p class="pwa-banner__desc">Instala nuestra aplicación para reservar y ver tu historial al instante.</p>
      </div>
    </div>
    <div class="pwa-banner__actions">
      <button class="pwa-banner__btn pwa-banner__btn--dismiss" id="pwa-btn-dismiss">Más tarde</button>
      <button class="pwa-banner__btn pwa-banner__btn--install" id="pwa-btn-install">Instalar App</button>
    </div>
  `;

  document.body.appendChild(banner);

  document.getElementById('pwa-btn-install').addEventListener('click', async () => {
    banner.remove();
    if (deferredPrompt) {
      deferredPrompt.prompt();
      const { outcome } = await deferredPrompt.userChoice;
      console.log(\`User response to install prompt: \${outcome}\`);
      deferredPrompt = null;
    }
  });

  document.getElementById('pwa-btn-dismiss').addEventListener('click', () => {
    banner.remove();
    sessionStorage.setItem('pwa-android-dismissed', 'true');
  });
}

// Show iOS manual install guide
function showIosInstallPrompt() {
  const banner = document.createElement('div');
  banner.className = 'pwa-banner';
  banner.innerHTML = `
    <div class="pwa-banner__header">
      <img src="/assets/icons/favicon.png" class="pwa-banner__icon" alt="KORTZEN">
      <div class="pwa-banner__info">
        <h4 class="pwa-banner__title">Instalar en tu iPhone</h4>
        <p class="pwa-banner__desc">Pulsa el botón de compartir de Safari <strong style="color:#fff;">(Compartir)</strong> y luego selecciona <strong style="color:#fff;">"Añadir a pantalla de inicio"</strong>.</p>
      </div>
    </div>
    <div class="pwa-banner__actions">
      <button class="pwa-banner__btn pwa-banner__btn--dismiss" id="pwa-ios-dismiss" style="width:100%;">Entendido</button>
    </div>
  `;

  document.body.appendChild(banner);

  document.getElementById('pwa-ios-dismiss').addEventListener('click', () => {
    banner.remove();
    sessionStorage.setItem('pwa-ios-dismissed', 'true');
  });
}

// Check and request notifications permission
function checkAndPromptNotifications() {
  if (!('Notification' in window)) return;

  if (Notification.permission === 'default') {
    const banner = document.createElement('div');
    banner.className = 'pwa-banner';
    banner.style.bottom = '80px'; // Sit slightly above the installation banner if both are present
    banner.innerHTML = `
      <div class="pwa-banner__header">
        <img src="/assets/icons/favicon.png" class="pwa-banner__icon" alt="Notificaciones">
        <div class="pwa-banner__info">
          <h4 class="pwa-banner__title">Activar Recordatorios</h4>
          <p class="pwa-banner__desc">Activa las notificaciones para no perder tus citas y recibir actualizaciones en tiempo real.</p>
        </div>
      </div>
      <div class="pwa-banner__actions">
        <button class="pwa-banner__btn pwa-banner__btn--dismiss" id="notif-btn-dismiss">Omitir</button>
        <button class="pwa-banner__btn pwa-banner__btn--install" id="notif-btn-allow">Permitir</button>
      </div>
    `;

    document.body.appendChild(banner);

    document.getElementById('notif-btn-allow').addEventListener('click', () => {
      banner.remove();
      Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
          try {
            new Notification("KORTZEN Barbería", {
              body: "¡Excelente! Te notificaremos sobre tus citas confirmadas y reagendaciones.",
              icon: "/assets/icons/favicon.png"
            });
          } catch (e) {
            console.log("Desktop notifications not fully supported, but permission granted.");
          }
        }
      });
    });

    document.getElementById('notif-btn-dismiss').addEventListener('click', () => {
      banner.remove();
    });
  }
}
