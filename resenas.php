<?php
require_once 'config.php';
requireLogin();

$currentUser = getCurrentUser();
$pageTitle = 'Gestión de Reseñas';
include 'includes/header.php';

// Fetch all reviews
$resenas = query("SELECT * FROM resenas ORDER BY created_at DESC");
?>

<div class="page-header">
    <h1 class="page-title">Reseñas de Clientes</h1>
    <a href="resenas_crear.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> NUEVA RESEÑA
    </a>
</div>

<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($_GET['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error">
        <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Comentario</th>
                <th>Calif.</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($resenas)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: var(--text-muted);">
                        No hay reseñas registradas.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($resenas as $r): ?>
                    <tr>
                        <td style="font-weight: 500; color: var(--text-primary);">
                            <?php echo htmlspecialchars($r['cliente_nombre']); ?>
                        </td>
                        <td style="max-width: 300px;">
                            <div
                                style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-secondary);">
                                "
                                <?php echo htmlspecialchars($r['comentario']); ?>"
                            </div>
                        </td>
                        <td>
                            <div style="color: var(--color-gold);">
                                <?php for ($i = 0; $i < $r['calificacion']; $i++)
                                    echo '★'; ?>
                            </div>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.9em;">
                            <?php echo $r['fecha']; ?>
                        </td>
                        <td>
                            <?php if ($r['visible']): ?>
                                <span class="badge badge-active">Visible</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Oculto</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="resenas_editar.php?id=<?php echo $r['id']; ?>" class="btn-action">EDITAR</a>
                                <form method="POST" action="api/reviews_action.php" style="display:inline;"
                                    onsubmit="return confirm('¿Estás seguro de eliminar esta reseña?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn-action btn-delete">ELIMINAR</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>