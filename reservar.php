<?php
require_once 'config.php';

// Si no hay cliente logueado, redirigir a login de cliente (o google auth)
// Nota: Asumimos que existe un index.html con botón de login o similar.
// Por ahora, si no está logueado, mostramos advertencia o forzamos login.
if (!isClienteLoggedIn()) {
    // Redirigir a la página de login de clientes
    header('Location: cliente-login.php');
    exit;
}

$cliente = getCurrentCliente();
$pageTitle = 'Reservar Cita';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Cita - KORTZEN</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">

    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KORTZEN">
    <link rel="apple-touch-icon" href="/assets/icons/favicon.png">
    <script src="/js/pwa.js" defer></script>
    <style>
        :root {
            --gold: #FFFFFF;
            --dark-bg: #050505;
            --card-bg: #111111;
            --text-primary: #F5F5F5;
            --text-secondary: #A3A3A3;
        }

        body {
            background-color: var(--dark-bg);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Container & Header */
        .booking-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
        }

        .booking-header {
            text-align: center;
            margin-bottom: 40px;
            margin-top: 20px;
        }

        .booking-title {
            font-size: 2rem;
            color: var(--gold);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        /* Wizard Steps Progress */
        .steps-progress {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .steps-progress::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #333;
            z-index: 1;
        }

        .step-dot {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #EEEEEE;
            color: #999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 2;
            border: 2px solid var(--dark-bg);
            transition: all 0.3s ease;
        }

        .step-dot.active {
            background: var(--gold);
            color: #000;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }

        .step-dot.completed {
            background: var(--gold);
            color: #000;
        }

        /* Wizard Sections */
        .wizard-step {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .wizard-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Cards Grid */
        .grid-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        /* Service Card */
        .option-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .option-card:hover {
            border-color: var(--gold);
            background: rgba(255, 255, 255, 0.05);
        }

        .option-card.selected {
            background: var(--gold);
            border-color: var(--gold);
        }

        .option-card.selected h3,
        .option-card.selected p,
        .option-card.selected .price {
            color: #000;
        }

        .option-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }

        .option-card p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .price {
            display: block;
            margin-top: 15px;
            font-weight: bold;
            color: var(--gold);
            font-size: 1.2rem;
        }

        /* Barber Card */
        .barber-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #333;
            margin: 0 auto 15px;
            background-size: cover;
            background-position: center;
            border: 2px solid var(--gold);
        }

        /* Date & Time */
        .datetime-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 700px) {
            .datetime-wrapper {
                grid-template-columns: 1fr;
            }
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
        }

        .time-slot {
            padding: 10px;
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .time-slot:hover:not(.disabled) {
            border-color: var(--gold);
        }

        .time-slot.selected {
            background: var(--gold);
            color: #000;
            border-color: var(--gold);
            font-weight: bold;
        }

        .time-slot.disabled {
            opacity: 0.3;
            cursor: not-allowed;
            background: #111;
        }

        /* Navigation Buttons */
        .wizard-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            border: none;
        }

        .btn-prev {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid #333;
        }

        .btn-prev:hover {
            border-color: #666;
            color: var(--text-primary);
        }

        .btn-next {
            background: var(--gold);
            color: #000;
        }

        .btn-next:disabled {
            background: #333;
            color: #666;
            cursor: not-allowed;
        }

        .hidden {
            display: none;
        }
    </style>
</head>

<body>

    <div class="booking-container">
        <div class="booking-header">
            <h1 class="booking-title">Tu Cita</h1>
            <p>Hola,
                <?php echo htmlspecialchars($cliente['nombre']); ?>. Vamos a agendar tu próximo corte.
            </p>
        </div>

        <!-- Progress -->
        <div class="steps-progress">
            <div class="step-dot active" data-step="1">1</div>
            <div class="step-dot" data-step="2">2</div>
            <div class="step-dot" data-step="3">3</div>
            <div class="step-dot" data-step="4">4</div>
            <div class="step-dot" data-step="5">5</div>
        </div>

        <!-- Step 1: Services -->
        <div class="wizard-step active" id="step1">
            <h2 style="margin-bottom: 20px;">Elige tu Servicio</h2>
            <div class="grid-options" id="servicesGrid">
                <!-- Cargado vía JS -->
                <p>Cargando servicios...</p>
            </div>
        </div>

        <!-- Step 2: Barbers -->
        <div class="wizard-step" id="step2">
            <h2 style="margin-bottom: 20px;">Elige tu Barbero</h2>
            <div class="grid-options" id="barbersGrid">
                <!-- Cargado vía JS -->
            </div>
        </div>

        <!-- Step 3: Date & Time -->
        <div class="wizard-step" id="step3">
            <h2 style="margin-bottom: 20px;">Elige Fecha y Hora</h2>
            <div class="datetime-wrapper">
                <div>
                    <label style="display:block; margin-bottom:10px; color:var(--text-secondary);">Selecciona el
                        día</label>
                    <input type="text" id="datePicker" placeholder="Seleccionar fecha"
                        style="width: 100%; padding: 15px; background: #1A1A1A; border: 1px solid #333; color: white; border-radius: 8px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:10px; color:var(--text-secondary);">Horarios
                        Disponibles</label>
                    <div id="slotsGrid" class="slots-grid">
                        <p style="color:#666; grid-column: 1/-1;">Selecciona una fecha primero</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Personal Details -->
        <div class="wizard-step" id="step4">
            <h2 style="margin-bottom: 20px;">Datos de Contacto</h2>
            <div
                style="background:var(--card-bg); padding:30px; border-radius:12px; border:1px solid #E0E0E0; max-width: 500px; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <p style="color:var(--text-secondary); margin-bottom:20px;">Necesitamos un número de contacto para
                    confirmar tu cita.</p>

                <div style="margin-bottom: 20px;">
                    <label
                        style="display:block; margin-bottom:8px; color:var(--text-secondary); font-size: 0.9rem;">Nombre</label>
                    <input type="text" id="clientName" readonly
                        style="width: 100%; padding: 12px; background: #F5F5F5; border: 1px solid #DDD; color: #555; border-radius: 6px; cursor: not-allowed;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label
                        style="display:block; margin-bottom:8px; color:var(--text-secondary); font-size: 0.9rem;">Email</label>
                    <input type="text" id="clientEmail" readonly
                        style="width: 100%; padding: 12px; background: #F5F5F5; border: 1px solid #DDD; color: #555; border-radius: 6px; cursor: not-allowed;">
                </div>

                <div style="margin-bottom: 10px;">
                    <label style="display:block; margin-bottom:8px; color:var(--gold); font-size: 0.9rem;">Teléfono /
                        WhatsApp *</label>
                    <input type="tel" id="clientPhone" placeholder="Ej: 0991234567"
                        style="width: 100%; padding: 12px; background: #FFF; border: 2px solid var(--gold); color: #333; border-radius: 6px; font-weight: bold; font-size: 1.1rem;">
                </div>
                <p style="font-size: 0.8rem; color: #999;">* Obligatorio para notificaciones de la cita.</p>
            </div>
        </div>

        <!-- Step 5: Confirm -->
        <div class="wizard-step" id="step5">
            <h2 style="margin-bottom: 20px;">Confirma tu Reserva</h2>
            <div
                style="background:var(--card-bg); padding:30px; border-radius:12px; border:1px solid #E0E0E0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                <div style="display:grid; gap:15px; margin-bottom:30px;">
                    <div>
                        <span style="color:var(--text-secondary); font-size:0.9rem;">SERVICIO</span>
                        <div id="confirmService" style="font-size:1.2rem; margin-top:5px;">-</div>
                    </div>
                    <div>
                        <span style="color:var(--text-secondary); font-size:0.9rem;">BARBERO</span>
                        <div id="confirmBarber" style="font-size:1.2rem; margin-top:5px;">-</div>
                    </div>
                    <div>
                        <span style="color:var(--text-secondary); font-size:0.9rem;">FECHA Y HORA</span>
                        <div id="confirmDateTime"
                            style="font-size:1.2rem; margin-top:5px; color:var(--gold); font-weight:bold;">-</div>
                    </div>
                    <div>
                        <span style="color:var(--text-secondary); font-size:0.9rem;">PRECIO ESTIMADO</span>
                        <div id="confirmPrice" style="font-size:1.2rem; margin-top:5px;">-</div>
                    </div>
                </div>

                <div class="policy-section"
                    style="margin-bottom: 25px; padding: 15px; background: #fff5e6; border-left: 4px solid var(--gold); border-radius: 4px;">
                    <h4 style="margin-top: 0; color: #b38600; font-size: 0.95rem;">POLÍTICA DE RESERVAS</h4>
                    <ul style="font-size: 0.85rem; color: #555; padding-left: 20px; margin-bottom: 10px;">
                        <li style="margin-bottom: 5px;">Si no puede llegar a su cita, informar con 1 hora de
                            anticipación, caso contrario tendrá un recargo adicional en su próxima cita.</li>
                        <li>Si llega 10 minutos tarde, pierde el servicio de toalla caliente y limpieza facial.</li>
                    </ul>
                    <div style="display: flex; align-items: flex-start; gap: 10px; margin-top: 10px;">
                        <input type="checkbox" id="policyCheckbox"
                            style="margin-top: 3px; transform: scale(1.2); cursor: pointer;">
                        <label for="policyCheckbox" style="font-size: 0.9rem; font-weight: 600; cursor: pointer;">
                            He leído y acepto los términos y condiciones.
                        </label>
                    </div>
                </div>

                <button id="btnConfirmBooking" class="btn btn-next"
                    style="width:100%; font-size:1.1rem; padding: 15px; opacity: 0.5; cursor: not-allowed;" disabled>
                    CONFIRMAR RESERVA
                </button>
            </div>
        </div>

        <script>
            // Policy Checkbox Logic (Inline for immediate effect, though will be moved to main script block if preferred)
            document.addEventListener('DOMContentLoaded', () => {
                const checkbox = document.getElementById('policyCheckbox');
                const btnConfirm = document.getElementById('btnConfirmBooking');

                if (checkbox && btnConfirm) {
                    checkbox.addEventListener('change', (e) => {
                        if (e.target.checked) {
                            btnConfirm.disabled = false;
                            btnConfirm.style.opacity = '1';
                            btnConfirm.style.cursor = 'pointer';
                        } else {
                            btnConfirm.disabled = true;
                            btnConfirm.style.opacity = '0.5';
                            btnConfirm.style.cursor = 'not-allowed';
                        }
                    });
                }
            });
        </script>

        <div class="wizard-nav">
            <button id="btnPrev" class="btn btn-prev" disabled>Atrás</button>
            <button id="btnNext" class="btn btn-next" disabled>Siguiente</button>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    <script>
        // State
        const bookingData = {
            serviceId: null,
            serviceName: null,
            servicePrice: null,
            barberId: null,
            barberName: null,
            date: null,
            time: null,
            phone: null
        };

        let currentStep = 1;
        let hasExistingPhone = false; // Track if client already has phone

        // Load Data
        document.addEventListener('DOMContentLoaded', async () => {
            // Get selected branch from localStorage
            const branchId = localStorage.getItem('kortzen_selected_branch') || 1;

            await loadServices(branchId);
            await loadBarbers(branchId);
            await loadClientProfile();
            initDatePicker();
            updateNavButtons();
        });

        // --- API Calls ---

        async function loadServices(branchId = 1) {
            const grid = document.getElementById('servicesGrid');

            try {
                const response = await fetch(`api/get_catalog.php?sucursal_id=${branchId}`);

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (data.error) {
                    grid.innerHTML = `<p style="color:#cc0000; grid-column:1/-1;">Error: ${data.error}</p>`;
                    return;
                }

                if (!data.servicios || data.servicios.length === 0) {
                    grid.innerHTML = '<p style="color:#888; grid-column:1/-1;">No hay servicios disponibles.</p>';
                    return;
                }

                // Group by category
                const servicesByCategory = {};
                data.servicios.forEach(s => {
                    const cat = s.categoria || 'General';
                    if (!servicesByCategory[cat]) {
                        servicesByCategory[cat] = [];
                    }
                    servicesByCategory[cat].push(s);
                });

                grid.innerHTML = '';

                // Iterate categories
                for (const [category, services] of Object.entries(servicesByCategory)) {
                    // Create Category Header
                    const catHeader = document.createElement('h3');
                    catHeader.style.cssText = 'grid-column: 1/-1; margin: 20px 0 10px 0; color: var(--color-gold); border-bottom: 1px solid #ddd; padding-bottom: 5px; text-transform: uppercase; font-size: 1.1rem;';
                    catHeader.textContent = category;
                    grid.appendChild(catHeader);

                    // Create Service Cards
                    services.forEach(s => {
                        const el = document.createElement('div');
                        el.className = 'option-card';
                        el.onclick = () => selectService(s.id, s.nombre, s.precio, el);

                        let imageHtml = '';
                        if (s.foto_url && s.foto_url.trim() !== '') {
                            // Ensure path is correct. If it starts with 'upload/', prepend nothing? Or assume relative?
                            // Let's assume the user puts a valid URL or path.
                            imageHtml = `<div class="service-image" style="width:100%; height:140px; background-image:url('${s.foto_url}'); background-size:cover; background-position:center; border-radius:8px 8px 0 0; margin-bottom:10px;"></div>`;
                        } else {
                            // Placeholder if no image? Or just no image area?
                            // User wants images. If missing, maybe a subtle gradient placeholder?
                            imageHtml = `<div class="service-image" style="width:100%; height:140px; background: linear-gradient(to bottom right, #333, #555); display:flex; align-items:center; justify-content:center; border-radius:8px 8px 0 0; margin-bottom:10px;"><span style="color:rgba(255,255,255,0.2); font-size:2rem;">✂️</span></div>`;
                        }

                        el.innerHTML = `
                        ${imageHtml}
                        <h3 style="margin:5px 0;">${s.nombre}</h3>
                        <p style="font-size:0.9rem; color:#666; margin-bottom:5px;">${s.duracion_minutos} min</p>
                        <span class="price" style="font-size:1.1rem;">$${s.precio}</span>
                    `;
                        grid.appendChild(el);
                    });
                }
            } catch (e) {
                console.error('Error cargando servicios:', e);
                grid.innerHTML = `<p style="color:#cc0000; grid-column:1/-1;">Error al cargar servicios. Revisa la consola (F12).</p>`;
                console.error(e);
            }
        }

        async function loadBarbers(branchId = 1) {
            try {
                const response = await fetch(`api/get_catalog.php?type=barbers&sucursal_id=${branchId}`);
                const data = await response.json();

                const grid = document.getElementById('barbersGrid');
                grid.innerHTML = '';

                data.barberos.forEach(b => {
                    const el = document.createElement('div');
                    el.className = 'option-card';
                    el.onclick = () => selectBarber(b.id, b.nombre, el);
                    let avatarHtml = '';
                    if (b.foto_perfil && b.foto_perfil.length > 5) {
                        avatarHtml = `
                        <div class="barber-avatar" style="width:60px; height:60px; border-radius:50%; background-image:url('${b.foto_perfil}'); background-size:cover; background-position:center; margin-bottom:10px; border:2px solid var(--color-gold);"></div>`;
                    } else {
                        avatarHtml = `
                        <div class="barber-avatar" style="width:60px; height:60px; border-radius:50%; background:#333; color:white; display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin-bottom:10px; border:2px solid var(--color-gold);">
                            ${b.nombre.charAt(0)}
                        </div>`;
                    }

                    el.innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center;">
                        ${avatarHtml}
                        <h3>${b.nombre}</h3>
                        <p style="font-size:0.9rem; color:#666;">${b.sucursal_nombre || 'Kortzen'}</p>
                    </div>
                `;
                    grid.appendChild(el);
                });
            } catch (e) {
                console.error(e);
            }
        }

        async function loadSlots(date) {
            if (!bookingData.barberId) return;

            const grid = document.getElementById('slotsGrid');
            grid.innerHTML = '<p style="color:#888">Cargando...</p>';

            try {
                const url = `api/get_disponibilidad.php?fecha=${date}&barbero_id=${bookingData.barberId}&servicio_id=${bookingData.serviceId}`;
                const response = await fetch(url);
                const slots = await response.json();

                grid.innerHTML = '';

                if (slots.length === 0) {
                    grid.innerHTML = '<p style="grid-column:1/-1; color:#ff5555; text-align:center;">No hay horarios disponibles para este día.</p>';
                    return;
                }

                slots.forEach(time => {
                    const el = document.createElement('div');
                    el.className = 'time-slot';
                    el.textContent = time;
                    el.onclick = () => selectTime(time, el);
                    grid.appendChild(el);
                });

            } catch (e) {
                grid.innerHTML = '<p style="color:red">Error al cargar horarios</p>';
            }
        }

        async function loadClientProfile() {
            try {
                const response = await fetch('api/get_client_profile.php');
                const res = await response.json();

                if (res.success && res.cliente) {
                    document.getElementById('clientName').value = res.cliente.nombre;
                    document.getElementById('clientEmail').value = res.cliente.email;
                    if (res.cliente.telefono && res.cliente.telefono.length > 6) {
                        document.getElementById('clientPhone').value = res.cliente.telefono;
                        bookingData.phone = res.cliente.telefono;
                        hasExistingPhone = true; // Will skip step 4
                    }
                }
            } catch (e) {
                console.error("Error loading profile", e);
            }
        }

        // --- Actions ---

        function selectService(id, name, price, el) {
            bookingData.serviceId = id;
            bookingData.serviceName = name;
            bookingData.servicePrice = price;
            bookingData.barberId = null; // Reset barber when service changes
            bookingData.barberName = null;

            document.querySelectorAll('#servicesGrid .option-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');

            // Auto-select "Barbería con Mateo" logic
            // Assuming the service name contains "Mateo"
            if (name.toLowerCase().includes('mateo')) {
                // Find Mateo in the loaded barbers
                // We need to access the loaded barbers list. 
                // A better way is to find the barber card with "Mateo" in the text
                const barbersGrid = document.getElementById('barbersGrid');
                const mateoCard = Array.from(barbersGrid.children).find(card =>
                    card.querySelector('h3').textContent.toLowerCase().includes('mateo')
                );

                if (mateoCard) {
                    // Trigger click on Mateo's card to select him
                    mateoCard.click();
                    // Auto-advance is handled in button click, but we want to skip step 2
                    // We can set a flag or just force next step
                    setTimeout(() => {
                        // Skip step 2 (Barbers) and go to Step 3 (Date)
                        currentStep = 3;
                        showStep(currentStep);
                    }, 500);
                }
            }

            updateNavButtons();

            // Auto-advance to next step (Step 2: Barbers)
            // Wait a small delay for visual feedback
            setTimeout(() => {
                const serviceName = name.toLowerCase();
                if (!serviceName.includes('mateo')) {
                    // Only auto-advance if NOT Mateo (Mateo logic handles its own skip)
                    currentStep = 2;
                    showStep(currentStep);
                }
            }, 300);
        }

        function selectBarber(id, name, el) {
            bookingData.barberId = id;
            bookingData.barberName = name;

            document.querySelectorAll('#barbersGrid .option-card').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            updateNavButtons();

            // Auto-advance to Step 3 (Date)
            setTimeout(() => {
                currentStep = 3;
                showStep(currentStep);
            }, 300);
        }

        function selectTime(time, el) {
            bookingData.time = time;

            document.querySelectorAll('.time-slot').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            updateNavButtons();

            // Auto-advance to Step 4 (Client Info) or 5 (Summary) depending on logic
            setTimeout(() => {
                // Logic mimics btnNext click
                // Skip step 4 if client already has phone
                if (hasExistingPhone) {
                    currentStep = 5;
                } else {
                    currentStep = 4;
                }
                showStep(currentStep);
            }, 300);
        }

        function initDatePicker() {
            flatpickr("#datePicker", {
                locale: "es",
                minDate: "today",
                maxDate: new Date().fp_incr(30), // 30 días adelante
                disable: [
                    function (date) {
                        return (date.getDay() === 0); // Deshabilitar domingos si cerrados
                    }
                ],
                onChange: function (selectedDates, dateStr, instance) {
                    bookingData.date = dateStr;
                    bookingData.time = null; // Reset time
                    loadSlots(dateStr);
                    updateNavButtons();
                }
            });
        }

        // Input changed listener
        document.getElementById('clientPhone').addEventListener('input', (e) => {
            bookingData.phone = e.target.value.trim();
            updateNavButtons();
        });

        // --- Navigation & Confirmation ---

        document.getElementById('btnPrev').addEventListener('click', () => {
            if (currentStep > 1) {
                // Skip step 4 going backwards if phone exists
                if (currentStep === 5 && hasExistingPhone) {
                    currentStep = 3;
                } else {
                    currentStep--;
                }
                showStep(currentStep);
            }
        });

        document.getElementById('btnNext').addEventListener('click', () => {
            if (currentStep < 5) {

                // Skip step 4 if client already has phone
                if (currentStep === 3 && hasExistingPhone) {
                    currentStep = 5;
                    showStep(currentStep);
                    return;
                }

                // Validation Step 4
                if (currentStep === 4) {
                    const phone = document.getElementById('clientPhone').value.trim();
                    if (!phone || phone.length < 7) {
                        alert('Por favor ingresa un número de teléfono válido.');
                        return;
                    }
                    bookingData.phone = phone;
                }

                currentStep++;
                showStep(currentStep);
            }
        });

        document.getElementById('btnConfirmBooking').addEventListener('click', async () => {
            const btn = document.getElementById('btnConfirmBooking');
            btn.disabled = true;
            btn.textContent = "Procesando...";

            try {
                const formData = new FormData();
                formData.append('servicio_id', bookingData.serviceId);
                formData.append('barbero_id', bookingData.barberId);
                formData.append('fecha', bookingData.date);
                formData.append('hora', bookingData.time);
                formData.append('telefono', bookingData.phone);

                const req = await fetch('api/crear_cita_cliente.php', {
                    method: 'POST',
                    body: formData
                });

                const res = await req.json();

                if (res.success) {
                    // Success UI
                    document.querySelector('.booking-container').innerHTML = `
                    <div style="text-align:center; padding-top:50px;">
                        <div style="font-size:4rem; color:var(--gold); margin-bottom:20px;">✓</div>
                        <h1 style="color:var(--text-primary); margin-bottom:10px;">¡Reserva Exitosa!</h1>
                        <p style="color:var(--text-muted); margin-bottom:30px;">Tu cita ha sido agendada correctamente.</p>
                        <a href="index.html" class="btn btn-next" style="text-decoration:none;">Volver al Inicio</a>
                        <br><br>
                        <a href="https://www.google.com/maps/place/KORTZEN/@-0.1352812,-78.4460419,17z/data=!3m1!4b1!4m6!3m5!1s0x91d58fc52de96153:0x35f5708deeee0cf7!8m2!3d-0.1352812!4d-78.443467!16s%2Fg%2F11yck29m8p?entry=ttu" target="_blank" style="color:var(--gold); text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
                            <span style="font-size:1.2rem;">⭐</span> Califícanos en Google
                        </a>
                    </div>
                `;
                } else {
                    alert('Error: ' + res.message);
                    btn.disabled = false;
                    btn.textContent = "CONFIRMAR RESERVA";
                }

            } catch (e) {
                alert('Error de conexión');
                btn.disabled = false;
                btn.textContent = "CONFIRMAR RESERVA";
            }
        });

        function showStep(step) {
            document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
            document.getElementById(`step${step}`).classList.add('active');

            // Update dots
            document.querySelectorAll('.step-dot').forEach(d => {
                const s = parseInt(d.dataset.step);
                d.classList.remove('active', 'completed');
                if (s === step) d.classList.add('active');
                if (s < step) d.classList.add('completed');
            });

            updateNavButtons();

            if (step === 5) {
                updateSummary();
            }
        }

        function updateNavButtons() {
            const prev = document.getElementById('btnPrev');
            const next = document.getElementById('btnNext');

            prev.disabled = currentStep === 1;

            // Logic next button
            let canNext = false;
            if (currentStep === 1 && bookingData.serviceId) canNext = true;
            if (currentStep === 2 && bookingData.barberId) canNext = true;
            if (currentStep === 3 && bookingData.date && bookingData.time) canNext = true;
            if (currentStep === 4 && bookingData.phone && bookingData.phone.length > 6) canNext = true;

            if (currentStep === 5) {
                next.classList.add('hidden'); // Hide next on last step
            } else {
                next.classList.remove('hidden');
                next.disabled = !canNext;
            }
        }

        function updateSummary() {
            document.getElementById('confirmService').textContent = bookingData.serviceName;
            document.getElementById('confirmBarber').textContent = bookingData.barberName;
            document.getElementById('confirmDateTime').textContent = `${bookingData.date} a las ${bookingData.time}`;
            document.getElementById('confirmPrice').textContent = `$${bookingData.servicePrice}`;

            // Add Phone to summary if desired, or just leave it
            const phoneDiv = document.createElement('div');
            phoneDiv.innerHTML = `<span style="color:var(--text-secondary); font-size:0.9rem;">TELEFONO</span><div style="font-size:1.2rem; margin-top:5px;">${bookingData.phone}</div>`;
            // Append if needed implementation
        }
    </script>

</body>

</html>