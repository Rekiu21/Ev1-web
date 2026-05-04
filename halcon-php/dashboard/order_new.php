<?php
require '../config.php';
require '../functions.php';

$user = require_role(['Sales', 'Admin']);
$error = '';

$invoice_number_value = isset($_POST['invoice_number']) ? $_POST['invoice_number'] : '';
$customer_name_value = isset($_POST['customer_name']) ? $_POST['customer_name'] : '';
$customer_number_value = isset($_POST['customer_number']) ? $_POST['customer_number'] : '';
$fiscal_data_value = isset($_POST['fiscal_data']) ? $_POST['fiscal_data'] : '';
$delivery_address_value = isset($_POST['delivery_address']) ? $_POST['delivery_address'] : '';
$notes_value = isset($_POST['notes']) ? $_POST['notes'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'invoice_number' => $invoice_number_value,
        'customer_name' => $customer_name_value,
        'customer_number' => $customer_number_value,
        'fiscal_data' => $fiscal_data_value,
        'delivery_address' => $delivery_address_value,
        'notes' => $notes_value
    ];
    
    if (empty($data['invoice_number']) || empty($data['customer_name']) || empty($data['customer_number']) || empty($data['delivery_address'])) {
        $error = 'Por favor completa todos los campos requeridos.';
    } else {
        $result = create_order($data);
        if (isset($result['success'])) {
            header('Location: order_detail.php?id=' . $result['id']);
            exit;
        } else {
            $error = isset($result['error']) ? $result['error'] : 'Error desconocido';
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halcón - Nueva Orden</title>
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
                        <span class="brand-subtitle">Alta de pedidos</span>
                    </div>
                </div>
                <div class="topbar-actions">
                    <span class="user-chip"><?= h($user['username']) ?> · <?= translate_role($user['role']) ?></span>
                    <button class="btn-evidence" onclick="openEvidenceModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span class="ev-btn-label">Project Description</span>
                    </button>
                    <a class="btn btn-secondary" href="orders.php">Volver</a>
                </div>
            </div>

            <div class="section-card surface surface-strong">
                <div class="page-intro">
                    <div>
                        <span class="eyebrow">Nueva orden</span>
                        <h1 class="page-title">Registrar pedido</h1>
                        <p class="page-note">Captura la información esencial del cliente y deja la orden lista para que el resto del equipo continúe el flujo.</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="POST" class="form-grid">
                    <div class="form-grid two">
                        <div class="field">
                            <label class="field-label" for="invoice_number">Número de factura</label>
                            <input class="input" type="text" id="invoice_number" name="invoice_number" required value="<?= h($invoice_number_value) ?>">
                            <span class="field-hint">Ejemplo: INV-001</span>
                        </div>

                        <div class="field">
                            <label class="field-label" for="customer_number">Número de cliente</label>
                            <input class="input" type="text" id="customer_number" name="customer_number" required value="<?= h($customer_number_value) ?>">
                            <span class="field-hint">Ejemplo: CUST001</span>
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="customer_name">Nombre del cliente</label>
                        <input class="input" type="text" id="customer_name" name="customer_name" required value="<?= h($customer_name_value) ?>">
                    </div>

                    <div class="field">
                        <label class="field-label" for="delivery_address">Dirección de entrega</label>
                        <textarea class="textarea" id="delivery_address" name="delivery_address" required><?= h($delivery_address_value) ?></textarea>
                    </div>

                    <div class="field">
                        <label class="field-label" for="fiscal_data">Información fiscal</label>
                        <textarea class="textarea" id="fiscal_data" name="fiscal_data"><?= h($fiscal_data_value) ?></textarea>
                    </div>

                    <div class="field">
                        <label class="field-label" for="notes">Notas internas</label>
                        <textarea class="textarea" id="notes" name="notes"><?= h($notes_value) ?></textarea>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

                    <div class="actions-row">
                        <a href="orders.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Crear orden</button>
                    </div>
                </form>
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
