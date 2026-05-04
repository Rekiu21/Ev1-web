<?php
require '../config.php';
require '../functions.php';

$user = require_login();
$order_id = isset($_GET['id']) ? $_GET['id'] : null;
$error = '';
$success = '';

if (!$order_id || !is_numeric($order_id)) {
    header('Location: orders.php');
    exit;
}

$order = get_order($order_id);
if (!$order) {
    header('Location: orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_value = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!verify_csrf_token($csrf_value)) {
        $error = 'Token CSRF inválido.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'update_status') {
            $new_status = isset($_POST['status']) ? $_POST['status'] : '';

            if (!in_array($new_status, array_keys(ORDER_STATUSES))) {
                $error = 'Estado no válido.';
            } elseif (!can_transition($user['role'], $order['status'], $new_status)) {
                $error = 'No tienes permiso para realizar esa transición de estado, o el estado actual no lo permite.';
            } else {
                $result = update_order($order_id, array('status' => $new_status));
                if (isset($result['success'])) {
                    $success = 'Estado actualizado correctamente.';
                    $order = get_order($order_id);
                } else {
                    $error = isset($result['error']) ? $result['error'] : 'Error al actualizar';
                }
            }
        } elseif ($action === 'update_notes' && in_array($user['role'], array('Admin', 'Sales'))) {
            $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
            $result = update_order($order_id, array('notes' => $notes));

            if (isset($result['success'])) {
                $success = 'Notas actualizadas.';
                $order = get_order($order_id);
            } else {
                $error = isset($result['error']) ? $result['error'] : 'Error al actualizar';
            }
        } elseif ($action === 'upload_photo' && in_array($user['role'], array('Admin', 'Route'))) {
            $photo_type = isset($_POST['photo_type']) ? $_POST['photo_type'] : '';

            if (in_array($photo_type, array('loaded', 'delivered'))) {
                $result = upload_order_photo($order_id, $photo_type);
                if (isset($result['success'])) {
                    $success = 'Foto subida correctamente.';
                    $order = get_order($order_id);
                } else {
                    $error = isset($result['error']) ? $result['error'] : 'Error al subir la foto';
                }
            }
        } elseif ($action === 'delete' && $user['role'] === 'Admin') {
            delete_order($order_id);
            header('Location: orders.php?deleted=1');
            exit;
        }
    }
}

$csrf_token   = generate_csrf_token();
$fiscal_value = trim($order['fiscal_data']) !== '' ? h($order['fiscal_data']) : 'Sin información registrada.';
$notes_value  = trim($order['notes'])       !== '' ? h($order['notes'])       : 'Sin notas internas.';

// Calcular qué transiciones de estado puede hacer este usuario desde el estado actual
$allowed_targets = array();
foreach (array_keys(ORDER_STATUSES) as $candidate) {
    if (can_transition($user['role'], $order['status'], $candidate)) {
        $allowed_targets[] = $candidate;
    }
}
$can_upload_photos = in_array($user['role'], array('Admin', 'Route'))
    && $order['status'] === 'In route';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halcón - Detalle de Orden</title>
    <link rel="stylesheet" href="../static/styles.css">
</head>
<body>
    <div class="page-shell">
        <div class="page-width dashboard-grid">
            <div class="topbar surface">
                <div class="brand">
                    <span class="brand-mark">H</span>
                    <div class="brand-meta">
                        <span class="brand-title">Halcón</span>
                        <span class="brand-subtitle">Seguimiento interno de pedidos</span>
                    </div>
                </div>
                <div class="topbar-actions">
                    <span class="user-chip"><?= h($user['username']) ?> · <?= translate_role($user['role']) ?></span>
                    <button class="btn-evidence" onclick="openEvidenceModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span class="ev-btn-label">Project Description</span>
                    </button>
                    <a class="btn btn-secondary" href="orders.php">Volver al listado</a>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>

            <div class="page-intro">
                <div>
                    <span class="eyebrow">Detalle de orden</span>
                    <h1 class="page-title"><?= h($order['invoice_number']) ?></h1>
                    <p class="page-note">Consulta la información principal del pedido y ejecuta las acciones disponibles según el rol actual.</p>
                </div>
                <span class="status <?= strtolower(str_replace(' ', '-', $order['status'])) ?>"><?= translate_status($order['status']) ?></span>
            </div>

            <div class="detail-layout">
                <div class="detail-card surface surface-strong">
                    <div class="section-head">
                        <div>
                            <span class="eyebrow">Información</span>
                            <h2 class="panel-title">Ficha del pedido</h2>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-block">
                            <span class="info-label">Número de factura</span>
                            <div class="info-value"><?= h($order['invoice_number']) ?></div>
                        </div>
                        <div class="info-block">
                            <span class="info-label">Cliente</span>
                            <div class="info-value"><?= h($order['customer_name']) ?></div>
                        </div>
                        <div class="info-block">
                            <span class="info-label">Número de cliente</span>
                            <div class="info-value"><?= h($order['customer_number']) ?></div>
                        </div>
                        <div class="info-block">
                            <span class="info-label">Fecha de orden</span>
                            <div class="info-value"><?= format_date($order['created_at']) ?></div>
                        </div>
                        <div class="info-block full">
                            <span class="info-label">Dirección de entrega</span>
                            <div class="info-value"><?= h($order['delivery_address']) ?></div>
                        </div>
                        <div class="info-block full">
                            <span class="info-label">Información fiscal</span>
                            <div class="info-value"><?= $fiscal_value ?></div>
                        </div>
                        <div class="info-block full">
                            <span class="info-label">Notas</span>
                            <div class="info-value"><?= $notes_value ?></div>
                        </div>
                    </div>

                    <!-- Photo gallery -->
                    <div class="photos-section">
                        <span class="eyebrow">Evidencia fotográfica</span>
                        <div class="photos-row">
                            <div class="photo-card">
                                <div class="photo-card-head">
                                    <span class="photo-dot"></span>
                                    <span class="photo-label">Foto de carga</span>
                                </div>
                                <?php if (!empty($order['photo_loaded_path'])): ?>
                                    <div class="photo-img-wrap">
                                        <a href="<?= APP_URL . '/uploads/' . h($order['photo_loaded_path']) ?>" target="_blank" rel="noopener noreferrer">
                                            <img class="photo-img" src="<?= APP_URL . '/uploads/' . h($order['photo_loaded_path']) ?>" alt="Foto de carga">
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-empty">
                                        <div class="photo-empty-icon">&#128230;</div>
                                        <p>Sin foto de carga</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="photo-card">
                                <div class="photo-card-head">
                                    <span class="photo-dot delivered"></span>
                                    <span class="photo-label">Foto de entrega</span>
                                </div>
                                <?php if (!empty($order['photo_delivered_path'])): ?>
                                    <div class="photo-img-wrap">
                                        <a href="<?= APP_URL . '/uploads/' . h($order['photo_delivered_path']) ?>" target="_blank" rel="noopener noreferrer">
                                            <img class="photo-img" src="<?= APP_URL . '/uploads/' . h($order['photo_delivered_path']) ?>" alt="Foto de entrega">
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-empty">
                                        <div class="photo-empty-icon">&#127968;</div>
                                        <p>Sin foto de entrega</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stack">
                    <?php if (!empty($allowed_targets)): ?>
                        <div class="side-card surface surface-strong">
                            <div class="panel-header">
                                <h2 class="panel-title">Cambiar estado</h2>
                                <p class="panel-subtitle">Actualiza la fase operativa actual del pedido.</p>
                            </div>
                            <form method="POST" class="form-grid">
                                <div class="field">
                                    <label class="field-label">Nuevo estado</label>
                                    <select class="select" name="status">
                                        <?php foreach ($allowed_targets as $target): ?>
                                            <option value="<?= h($target) ?>"><?= h(translate_status($target)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                <button type="submit" class="btn btn-primary btn-block">Guardar estado</button>
                            </form>
                        </div>
                    <?php elseif (in_array($user['role'], array('Admin', 'Warehouse', 'Route'))): ?>
                        <div class="side-card surface surface-strong">
                            <div class="panel-header">
                                <h2 class="panel-title">Estado del pedido</h2>
                            </div>
                            <p class="muted" style="font-size:0.9rem;">
                                <?php if ($order['status'] === 'Delivered'): ?>
                                    Este pedido ya fue entregado. No hay más transiciones disponibles.
                                <?php else: ?>
                                    Tu rol no permite cambiar el estado en esta fase del pedido.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($user['role'], array('Admin', 'Sales'))): ?>
                        <div class="side-card surface surface-strong">
                            <div class="panel-header">
                                <h2 class="panel-title">Actualizar notas</h2>
                                <p class="panel-subtitle">Registra comentarios internos para el equipo.</p>
                            </div>
                            <form method="POST" class="form-grid">
                                <div class="field">
                                    <label class="field-label">Notas</label>
                                    <textarea class="textarea" name="notes" rows="5"><?= h($order['notes']) ?></textarea>
                                </div>
                                <input type="hidden" name="action" value="update_notes">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                <button type="submit" class="btn btn-primary btn-block">Guardar notas</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array($user['role'], array('Admin', 'Route'))): ?>
                        <div class="side-card surface surface-strong">
                            <div class="panel-header">
                                <h2 class="panel-title">Evidencia fotográfica</h2>
                                <p class="panel-subtitle">Adjunta fotos para documentar carga y entrega.</p>
                            </div>
                            <?php if ($can_upload_photos): ?>
                                <div class="stack">
                                    <form method="POST" enctype="multipart/form-data" class="upload-box">
                                        <h3>Foto de carga</h3>
                                        <?php if (!empty($order['photo_loaded_path'])): ?>
                                            <div class="upload-preview">
                                                <img class="upload-preview-img" src="<?= APP_URL . '/uploads/' . h($order['photo_loaded_path']) ?>" alt="Foto de carga actual">
                                                <span class="upload-preview-badge">Foto actual</span>
                                                <a class="upload-preview-link" href="<?= APP_URL . '/uploads/' . h($order['photo_loaded_path']) ?>" target="_blank" rel="noopener noreferrer">Ver original</a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="field">
                                            <input class="file-input" type="file" name="photo" accept="image/*" required>
                                        </div>
                                        <input type="hidden" name="action" value="upload_photo">
                                        <input type="hidden" name="photo_type" value="loaded">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                        <button type="submit" class="btn btn-secondary btn-block"><?= !empty($order['photo_loaded_path']) ? 'Reemplazar imagen' : 'Subir imagen' ?></button>
                                    </form>

                                    <form method="POST" enctype="multipart/form-data" class="upload-box">
                                        <h3>Foto de entrega</h3>
                                        <p class="muted" style="font-size:0.82rem;margin-bottom:10px;">Al subir esta foto el pedido pasará automáticamente a <strong>Entregado</strong>.</p>
                                        <?php if (!empty($order['photo_delivered_path'])): ?>
                                            <div class="upload-preview">
                                                <img class="upload-preview-img" src="<?= APP_URL . '/uploads/' . h($order['photo_delivered_path']) ?>" alt="Foto de entrega actual">
                                                <span class="upload-preview-badge">Foto actual</span>
                                                <a class="upload-preview-link" href="<?= APP_URL . '/uploads/' . h($order['photo_delivered_path']) ?>" target="_blank" rel="noopener noreferrer">Ver original</a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="field">
                                            <input class="file-input" type="file" name="photo" accept="image/*" required>
                                        </div>
                                        <input type="hidden" name="action" value="upload_photo">
                                        <input type="hidden" name="photo_type" value="delivered">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                        <button type="submit" class="btn btn-secondary btn-block"><?= !empty($order['photo_delivered_path']) ? 'Reemplazar imagen' : 'Subir imagen' ?></button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <p class="muted" style="font-size:0.9rem;">
                                    Las fotos solo se pueden subir cuando la orden está en estado <strong>En camino</strong>.
                                    <?php if ($order['status'] === 'Delivered'): ?>
                                        Este pedido ya fue entregado.
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($user['role'] === 'Admin'): ?>
                        <div class="side-card surface surface-strong">
                            <div class="panel-header">
                                <h2 class="panel-title">Zona administrativa</h2>
                                <p class="panel-subtitle">Esta acción realiza un borrado lógico del pedido.</p>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('¿Estás seguro de que quieres eliminar esta orden?');">Eliminar orden</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<!-- Evidence Modal -->
<div id="ev-modal" class="ev-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="ev-modal-lbl">
    <div class="ev-modal-box">
        <div class="ev-modal-bar">
            <div class="ev-modal-title" id="ev-modal-lbl">
                <span class="ev-modal-title-dot"></span>
                Project Description
            </div>
            <button class="ev-modal-close" onclick="closeEvidenceModal()" aria-label="Cerrar">&#x2715;</button>
        </div>
        <iframe class="ev-modal-iframe" src="<?= APP_URL ?>/Evidence%203_Web-Application-Design.pdf" title="Project Description"></iframe>
    </div>
</div>
<script>
function openEvidenceModal(){var o=document.getElementById('ev-modal');o.classList.add('open');document.body.style.overflow='hidden';}
function closeEvidenceModal(){var o=document.getElementById('ev-modal');o.classList.remove('open');document.body.style.overflow='';}
document.getElementById('ev-modal').addEventListener('click',function(e){if(e.target===this)closeEvidenceModal();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeEvidenceModal();});
</script>
</body>
