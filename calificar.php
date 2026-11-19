<?php
/**
 * KORTZEN - Calificación de Experiencia de Cliente (1 a 5 estrellas)
 * Permite a los clientes calificar su corte y servicio tras recibir el enlace por WhatsApp o Notificación Push.
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/webpush_helper.php';

$pdo = getConnection();

$citaId = intval($_GET['c'] ?? $_GET['id'] ?? $_POST['cita_id'] ?? 0);
$successSubmitted = false;
$errorMessage = '';
$cita = null;
$resenaExistente = null;

if ($citaId > 0) {
    try {
        $stmtC = $pdo->prepare("
            SELECT c.*, 
                   cl.id as cliente_id_bd, cl.nombre as cliente_nombre, cl.telefono as cliente_telefono,
                   s.nombre as servicio_nombre, s.precio as servicio_precio,
                   u.id as barbero_id, u.nombre as barbero_nombre, u.foto_url as barbero_foto,
                   su.id as sucursal_id, su.nombre as sucursal_nombre
            FROM citas c
            LEFT JOIN clientes cl ON c.cliente_id = cl.id
            LEFT JOIN servicios s ON c.servicio_id = s.id
            LEFT JOIN usuarios u ON c.barbero_id = u.id
            LEFT JOIN sucursales su ON c.sucursal_id = su.id
            WHERE c.id = ?
        ");
        $stmtC->execute([$citaId]);
        $cita = $stmtC->fetch(PDO::FETCH_ASSOC);

        if ($cita) {
            // Verificar si ya existe una reseña para esta cita
            $stmtRes = $pdo->prepare("SELECT * FROM resenas WHERE cita_id = ? ORDER BY id DESC LIMIT 1");
            $stmtRes->execute([$citaId]);
            $resenaExistente = $stmtRes->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $errorMessage = 'Ocurrió un error al cargar la información de la cita.';
    }
}

// Procesar formulario POST de calificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cita) {
    if ($resenaExistente) {
        $errorMessage = 'Esta cita ya fue calificada previamente.';
    } else {
        $calificacion = intval($_POST['calificacion'] ?? 5);
        if ($calificacion < 1) $calificacion = 1;
        if ($calificacion > 5) $calificacion = 5;

        $comentario = trim($_POST['comentario'] ?? '');
        $clienteNombre = !empty($cita['cliente_nombre']) ? $cita['cliente_nombre'] : trim($_POST['cliente_nombre'] ?? 'Cliente');
        if (empty($clienteNombre)) $clienteNombre = 'Cliente';

        $barberoId = intval($cita['barbero_id'] ?? 0);
        $clienteId = intval($cita['cliente_id_bd'] ?? ($cita['cliente_id'] ?? 0));
        $sucursalId = intval($cita['sucursal_id'] ?? 1);

        try {
            $stmtIns = $pdo->prepare("
                INSERT INTO resenas (cita_id, barbero_id, cliente_id, sucursal_id, cliente_nombre, comentario, calificacion, fecha, visible, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_DATE, 1, NOW())
            ");
            $stmtIns->execute([
                $citaId,
                $barberoId ?: null,
                $clienteId ?: null,
                $sucursalId ?: 1,
                $clienteNombre,
                $comentario,
                $calificacion
            ]);
            $newResenaId = $pdo->lastInsertId();

            // Notificar al barbero y a los administradores
            if (function_exists('notificarNuevaResena')) {
                notificarNuevaResena($pdo, $newResenaId, $barberoId, $clienteNombre, $calificacion, $comentario, $sucursalId);
            }

            $successSubmitted = true;

            // Recargar reseña guardada para mostrar en pantalla
            $stmtRes = $pdo->prepare("SELECT * FROM resenas WHERE id = ?");
            $stmtRes->execute([$newResenaId]);
            $resenaExistente = $stmtRes->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $eSave) {
            $errorMessage = 'No se pudo registrar la calificación: ' . $eSave->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Califica tu Experiencia • KORTZEN BARBERÍA</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/favicon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0D0D0D;
            --card-dark: #171717;
            --border-dark: #262626;
            --gold-accent: #F59E0B;
            --gold-light: #FDE68A;
            --text-light: #F9FAFB;
            --text-muted: #9CA3AF;
            --green-accent: #10B981;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
        }

        .rating-container {
            width: 100%;
            max-width: 480px;
            background: var(--card-dark);
            border: 1px solid var(--border-dark);
            border-radius: 20px;
            padding: 28px 22px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .rating-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #F59E0B, #10B981, #F59E0B);
        }

        .brand-logo {
            font-size: 1.35rem;
            font-weight: 900;
            letter-spacing: 2px;
            color: #FFFFFF;
            margin-bottom: 4px;
        }

        .brand-tagline {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--gold-accent);
            font-weight: 700;
            margin-bottom: 24px;
        }

        /* Tarjeta Barbero y Servicio */
        .barber-badge-box {
            background: #1F1F1F;
            border: 1px solid #2B2B2B;
            border-radius: 14px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-align: left;
            margin-bottom: 24px;
        }

        .barber-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #333333;
            border: 2px solid var(--gold-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 900;
            color: #FFFFFF;
            overflow: hidden;
            flex-shrink: 0;
        }

        .barber-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .barber-info h3 {
            font-size: 0.98rem;
            font-weight: 800;
            color: #FFFFFF;
        }

        .barber-info p {
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Estrellas Interactivas */
        .stars-wrapper {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 18px 0 10px 0;
        }

        .star-item {
            font-size: 2.6rem;
            color: #374151;
            cursor: pointer;
            transition: transform 0.15s ease, color 0.15s ease;
            user-select: none;
        }

        .star-item.active,
        .star-item.hovered {
            color: var(--gold-accent);
            text-shadow: 0 0 12px rgba(245, 158, 11, 0.4);
        }

        .star-item:active {
            transform: scale(1.25);
        }

        .rating-desc {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--gold-accent);
            min-height: 24px;
            margin-bottom: 20px;
        }

        /* Chips de elogio rápido */
        .chips-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-bottom: 18px;
        }

        .chip-btn {
            background: #222222;
            border: 1px solid #333333;
            color: #D1D5DB;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
        }

        .chip-btn:hover, .chip-btn.selected {
            background: #2D2513;
            border-color: var(--gold-accent);
            color: var(--gold-light);
        }

        /* Área de Comentarios */
        .comment-box {
            width: 100%;
            background: #1A1A1A;
            border: 1.5px solid var(--border-dark);
            border-radius: 12px;
            padding: 12px 14px;
            color: #FFFFFF;
            font-family: inherit;
            font-size: 0.85rem;
            line-height: 1.4;
            resize: none;
            margin-bottom: 22px;
            outline: none;
            transition: border-color 0.2s;
        }

        .comment-box:focus {
            border-color: var(--gold-accent);
        }

        /* Botón de Envío */
        .btn-submit-review {
            width: 100%;
            background: linear-gradient(135deg, #F59E0B, #D97706);
            color: #111111;
            font-weight: 900;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            padding: 14px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.35);
            transition: transform 0.15s, opacity 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit-review:active {
            transform: scale(0.98);
        }

        /* Pantalla de Éxito */
        .success-box {
            padding: 20px 10px;
            text-align: center;
        }

        .success-icon {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: rgba(16, 185, 129, 0.15);
            color: var(--green-accent);
            border: 2px solid var(--green-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 16px auto;
        }

        .stars-awarded {
            font-size: 1.8rem;
            color: var(--gold-accent);
            margin: 10px 0 16px 0;
            letter-spacing: 4px;
        }

        .btn-return {
            display: inline-block;
            margin-top: 20px;
            background: #222;
            color: #FFF;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            border: 1px solid #333;
        }

        .btn-return:hover {
            background: #333;
        }
    </style>
</head>
<body>

<div class="rating-container">
    <div class="brand-logo">KORTZEN</div>
    <div class="brand-tagline">Experiencia y Estilo</div>

    <?php if (!$cita): ?>
        <!-- Cita no encontrada o enlace vencido -->
        <div style="padding: 30px 10px;">
            <i class="fas fa-exclamation-circle" style="font-size: 2.5rem; color: #EF4444; margin-bottom: 14px;"></i>
            <h2 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 8px;">Cita no encontrada</h2>
            <p style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.5;">
                El enlace de calificación parece haber expirado o es incorrecto. Si tienes alguna duda, escríbenos directamente.
            </p>
            <a href="index.html" class="btn-return">Ir a la página principal</a>
        </div>

    <?php elseif ($resenaExistente): ?>
        <!-- Cita ya calificada -->
        <div class="success-box">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 6px;">¡Cita Calificada!</h2>
            <p style="font-size: 0.84rem; color: var(--text-muted); line-height: 1.5;">
                Muchas gracias, <strong><?php echo htmlspecialchars($cita['cliente_nombre'] ?? 'Cliente'); ?></strong>. Ya has registrado tu opinión para este corte con <strong><?php echo htmlspecialchars($cita['barbero_nombre'] ?? 'tu barbero'); ?></strong>.
            </p>

            <div class="stars-awarded">
                <?php 
                $calif = intval($resenaExistente['calificacion'] ?? 5);
                for ($s = 1; $s <= 5; $s++) {
                    echo ($s <= $calif) ? '★' : '<span style="color:#374151">★</span>';
                }
                ?>
            </div>

            <?php if (!empty($resenaExistente['comentario'])): ?>
                <div style="background: #1C1C1C; border: 1px solid #2C2C2C; border-radius: 10px; padding: 12px; font-size: 0.82rem; font-style: italic; color: #E5E7EB; margin-bottom: 18px;">
                    "<?php echo htmlspecialchars($resenaExistente['comentario']); ?>"
                </div>
            <?php endif; ?>

            <a href="/cliente-dashboard.php" class="btn-return">Ver mis Citas y Puntos</a>
        </div>

    <?php else: ?>
        <!-- Formulario Interactivo de Calificación -->
        <div class="barber-badge-box">
            <div class="barber-avatar">
                <?php if (!empty($cita['barbero_foto'])): ?>
                    <img src="<?php echo htmlspecialchars($cita['barbero_foto']); ?>" alt="Barbero">
                <?php else: ?>
                    <?php echo strtoupper(substr($cita['barbero_nombre'] ?? 'B', 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="barber-info">
                <h3><?php echo htmlspecialchars($cita['barbero_nombre'] ?? 'Tu Barbero'); ?></h3>
                <p>
                    <i class="fas fa-cut" style="color: var(--gold-accent);"></i> 
                    <?php echo htmlspecialchars($cita['servicio_nombre'] ?? 'Servicio'); ?>
                    • <?php echo htmlspecialchars($cita['sucursal_nombre'] ?? 'Kortzen'); ?>
                </p>
                <p style="font-size: 0.72rem; color: #6B7280; margin-top: 2px;">
                    <?php echo date('d/m/Y', strtotime($cita['fecha_hora'])); ?>
                </p>
            </div>
        </div>

        <h2 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 4px;">¿Cómo calificarías tu corte?</h2>
        <p style="font-size: 0.8rem; color: var(--text-muted);">Toca las estrellas para elegir tu calificación:</p>

        <form method="POST" action="calificar.php?c=<?php echo $citaId; ?>" onsubmit="return validarEnvio();">
            <input type="hidden" name="cita_id" value="<?php echo $citaId; ?>">
            <input type="hidden" name="calificacion" id="inputCalificacion" value="5">

            <!-- Estrellas -->
            <div class="stars-wrapper" id="starsContainer">
                <i class="fas fa-star star-item active" data-val="1"></i>
                <i class="fas fa-star star-item active" data-val="2"></i>
                <i class="fas fa-star star-item active" data-val="3"></i>
                <i class="fas fa-star star-item active" data-val="4"></i>
                <i class="fas fa-star star-item active" data-val="5"></i>
            </div>

            <div class="rating-desc" id="ratingDesc">🤩 ¡Excelente! - Corte impecable y recomendado</div>

            <!-- Chips de elogio rápido -->
            <div class="chips-container">
                <span class="chip-btn" onclick="agregarChip(this, '✂️ Corte impecable')">✂️ Corte impecable</span>
                <span class="chip-btn" onclick="agregarChip(this, '⏰ Muy puntual')">⏰ Muy puntual</span>
                <span class="chip-btn" onclick="agregarChip(this, '💈 Gran atención')">💈 Gran atención</span>
                <span class="chip-btn" onclick="agregarChip(this, '🧼 Higiene y orden')">🧼 Higiene y orden</span>
                <span class="chip-btn" onclick="agregarChip(this, '🎵 Buen ambiente')">🎵 Buen ambiente</span>
            </div>

            <!-- Comentario opcional -->
            <textarea name="comentario" id="comentarioArea" class="comment-box" rows="3" placeholder="Cuéntanos más sobre tu experiencia o sugerencias (opcional)..."></textarea>

            <button type="submit" class="btn-submit-review">
                <span>Enviar mi Calificación</span>
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
    <?php endif; ?>
</div>

<script>
    const descs = {
        1: '😞 Malo - Necesitamos mejorar',
        2: '😐 Regular - Hay detalles por corregir',
        3: '🙂 Bueno - Buen corte y atención',
        4: '😊 ¡Muy bueno! - Gran experiencia',
        5: '🤩 ¡Excelente! - Corte impecable y recomendado'
    };

    let selectedRating = 5;
    const inputCal = document.getElementById('inputCalificacion');
    const descElem = document.getElementById('ratingDesc');
    const starItems = document.querySelectorAll('.star-item');

    starItems.forEach(star => {
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.getAttribute('data-val'));
            if (inputCal) inputCal.value = selectedRating;
            pintarEstrellas(selectedRating);
        });

        star.addEventListener('mouseenter', function() {
            const val = parseInt(this.getAttribute('data-val'));
            pintarEstrellas(val);
        });
    });

    const container = document.getElementById('starsContainer');
    if (container) {
        container.addEventListener('mouseleave', function() {
            pintarEstrellas(selectedRating);
        });
    }

    function pintarEstrellas(val) {
        starItems.forEach(st => {
            const sVal = parseInt(st.getAttribute('data-val'));
            if (sVal <= val) {
                st.classList.add('active');
            } else {
                st.classList.remove('active');
            }
        });
        if (descElem) {
            descElem.innerText = descs[val] || '';
        }
    }

    function agregarChip(btn, texto) {
        const textarea = document.getElementById('comentarioArea');
        if (!textarea) return;

        btn.classList.toggle('selected');
        let actual = textarea.value.trim();

        if (btn.classList.contains('selected')) {
            if (actual.length > 0) {
                textarea.value = actual + ' • ' + texto;
            } else {
                textarea.value = texto;
            }
        } else {
            // Quitar texto si se deselecciona
            textarea.value = actual.replace(' • ' + texto, '').replace(texto + ' • ', '').replace(texto, '').trim();
        }
    }

    function validarEnvio() {
        if (selectedRating < 1 || selectedRating > 5) {
            alert('Por favor selecciona una calificación de 1 a 5 estrellas.');
            return false;
        }
        return true;
    }
</script>

</body>
</html>
