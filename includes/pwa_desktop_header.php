<?php
/**
 * KORTZEN - Desktop Web Navbar & Collapsible Menu Header
 * Autocontenido con estilos para garantizar rendimiento visual en computadoras
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    /* Estilos Autocontenidos para Vista de Escritorio */
    @media (min-width: 769px) {
        .pwa-bottom-nav-bar, 
        .pwa-header {
            display: none !important;
        }

        .pwa-container, .booking-container {
            max-width: 1050px !important;
            margin: 0 auto !important;
            padding-top: 1.5rem !important;
            padding-bottom: 4rem !important;
        }

        .pwa-desktop-navbar {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            padding: 1.1rem 1.5rem;
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            margin-bottom: 1.75rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .pwa-desktop-logo {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #111111;
            text-decoration: none;
        }

        .pwa-desktop-menu-links {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .pwa-desktop-nav-link {
            color: #444444;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 0.55rem 0.95rem;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .pwa-desktop-nav-link:hover,
        .pwa-desktop-nav-link--active {
            background: #111111;
            color: #FFFFFF !important;
        }

        .pwa-desktop-toggle-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #F8F9FA;
            border: 1px solid #EAEAEA;
            padding: 0.55rem 1.1rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.82rem;
            color: #111111;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pwa-desktop-toggle-btn:hover {
            background: #111111;
            color: #FFFFFF;
        }

        /* Menú Colapsable */
        .pwa-collapsible-menu {
            background: #FFFFFF;
            border: 1px solid #EAEAEA;
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        }

        .pwa-collapsible-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.85rem;
        }

        .pwa-collapsible-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.85rem 1rem;
            background: #F9F9F9;
            border: 1px solid #EAEAEA;
            border-radius: 12px;
            color: #111111;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.82rem;
            transition: all 0.2s ease;
        }

        .pwa-collapsible-item:hover {
            background: #111111;
            color: #FFFFFF !important;
        }

        /* Grillas multicolumna en Escritorio */
        .pwa-quick-actions {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 1rem !important;
        }

        .pwa-services-list,
        .pwa-barber-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 1.25rem !important;
        }
    }

    @media (max-width: 768px) {
        .pwa-desktop-navbar,
        .pwa-collapsible-menu {
            display: none !important;
        }
    }
</style>

<!-- Desktop Web Navigation Header -->
<header class="pwa-desktop-navbar">
    <a href="cliente-dashboard.php" class="pwa-desktop-logo">KORTZEN</a>

    <div class="pwa-desktop-menu-links">
        <a href="cliente-dashboard.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'cliente-dashboard.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Inicio</a>
        <a href="pwa-servicios.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'pwa-servicios.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Servicios</a>
        <a href="pwa-barberos.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'pwa-barberos.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Barberos</a>
        <a href="reservar.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'reservar.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Reservar</a>
        <a href="mis-citas.php" class="pwa-desktop-nav-link <?php echo ($currentPage === 'mis-citas.php') ? 'pwa-desktop-nav-link--active' : ''; ?>">Mis Citas</a>
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
    <div style="font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #777; margin-bottom: 0.85rem;">
        ACCESOS RÁPIDOS Y NAVEGACIÓN KORTZEN
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
        if (!menu) return;
        if (menu.style.display === 'none' || menu.style.display === '') {
            menu.style.display = 'block';
        } else {
            menu.style.display = 'none';
        }
    }
</script>
