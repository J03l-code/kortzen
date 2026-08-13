<?php
/**
 * KORTZEN - Desktop Web Navbar & Collapsible Menu Header
 * Solo visible en pantallas de computadora para clientes
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Desktop Web Navigation Header -->
<header class="pwa-desktop-navbar">
    <a href="cliente-dashboard.php" class="pwa-desktop-logo">KORTZEN</a>

    <div class="pwa-desktop-menu-links">
        <a href="cliente-dashboard.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'cliente-dashboard.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Inicio</a>
        <a href="pwa-servicios.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'pwa-servicios.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Servicios</a>
        <a href="pwa-barberos.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'pwa-barberos.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Barberos</a>
        <a href="reservar.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'reservar.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Reservar</a>
        <a href="mis-citas.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'mis-citas.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Citas</a>
        <a href="mi-perfil.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'mi-perfil.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Perfil</a>
    </div>

    <button type="button" class="pwa-desktop-toggle-btn" onclick="toggleDesktopMenu()">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
        <span>Menú</span>
    </button>
</header>

<!-- Collapsible Menu Drawer on Desktop -->
<div id="pwaCollapsibleMenu" class="pwa-collapsible-menu" style="display: none;">
    <div style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #777; margin-bottom: 0.85rem;">
        Accesos Rápidos y Navegación KORTZEN
    </div>
    <div class="pwa-collapsible-grid">
        <a href="cliente-dashboard.php" class="pwa-collapsible-item">
            <span>🏠</span> <span>Inicio</span>
        </a>
        <a href="pwa-servicios.php" class="pwa-collapsible-item">
            <span>✂️</span> <span>Catálogo de Servicios</span>
        </a>
        <a href="pwa-barberos.php" class="pwa-collapsible-item">
            <span>💈</span> <span>Nuestros Barberos</span>
        </a>
        <a href="reservar.php" class="pwa-collapsible-item">
            <span>📅</span> <span>Agendar Cita</span>
        </a>
        <a href="mis-citas.php" class="pwa-collapsible-item">
            <span>🕒</span> <span>Mis Citas e Historial</span>
        </a>
        <a href="mi-perfil.php" class="pwa-collapsible-item">
            <span>👤</span> <span>Mi Perfil & Puntos</span>
        </a>
        <a href="https://wa.me/593988422770?text=Hola%20KORTZEN,%20tengo%20una%20consulta" target="_blank" class="pwa-collapsible-item">
            <span>💬</span> <span>Soporte WhatsApp</span>
        </a>
        <a href="logout.php" class="pwa-collapsible-item" style="color: #dc3545;">
            <span>🚪</span> <span>Cerrar Sesión</span>
        </a>
    </div>
</div>

<script>
    function toggleDesktopMenu() {
        const menu = document.getElementById('pwaCollapsibleMenu');
        if (menu.style.display === 'none' || !menu.style.display) {
            menu.style.display = 'block';
        } else {
            menu.style.display = 'none';
        }
    }
</script>
