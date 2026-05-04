<?php
require 'config.php';
require 'functions.php';

$result = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_number = isset($_POST['customer_number']) ? $_POST['customer_number'] : '';
    $invoice_number = isset($_POST['invoice_number']) ? $_POST['invoice_number'] : '';
    $csrf_token_post = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    if (!verify_csrf_token($csrf_token_post)) {
        $error = 'La sesión expiró. Vuelve a intentar la consulta.';
    } elseif (empty($customer_number) || empty($invoice_number)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        try {
            $stmt = $pdo->prepare('
                SELECT * FROM orders
                WHERE deleted = FALSE
                AND customer_number = ?
                AND invoice_number = ?
                LIMIT 1
            ');
            $stmt->execute([trim($customer_number), trim($invoice_number)]);
            $result = $stmt->fetch();

            if (!$result) {
                $error = 'No se encontró el pedido con esos datos.';
            }
        } catch (Exception $e) {
            error_log('Error en consulta publica de orden: ' . $e->getMessage());
            $error = 'No fue posible consultar en este momento. Intenta de nuevo en unos segundos.';
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
    <title>Halcón - Rastreo de Órdenes</title>
    <link rel="stylesheet" href="static/styles.css">
</head>
<body>
    <div class="page-shell">
        <div class="page-width">
            <div class="topbar surface">
                <div class="brand">
                    <span class="brand-mark">H</span>
                    <div class="brand-meta">
                        <span class="brand-title">Halcón</span>
                        <span class="brand-subtitle">Seguimiento de órdenes y operación comercial</span>
                    </div>
                </div>
                <div class="topbar-actions">
                    <span class="subtle-chip">rekiu.com</span>
                    <button class="btn-evidence" onclick="openEvidenceModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span class="ev-btn-label">Project Description</span>
                    </button>
                    <a class="btn btn-secondary" href="login.php">Acceso interno</a>
                </div>
            </div>

            <div class="hero surface">
                <div class="hero-copy">
                    <span class="eyebrow">Rastreo público</span>
                    <h1 class="hero-title">Consulta el estado de una orden en segundos.</h1>
                    <p class="hero-text">Una vista clara para clientes y operación. Busca por número de cliente y factura para ver el avance, la fecha de registro y los datos de entrega sin pasos extra.</p>
                    <div class="hero-points">
                        <div class="info-tile">
                            <strong>Estado en vivo</strong>
                            <span>Lectura rápida del progreso del pedido.</span>
                        </div>
                        <div class="info-tile">
                            <strong>Datos clave</strong>
                            <span>Factura, cliente, fecha y dirección en una sola ficha.</span>
                        </div>
                        <div class="info-tile">
                            <strong>Acceso simple</strong>
                            <span>Sin panel adicional para el cliente final.</span>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2 class="panel-title">Buscar orden</h2>
                        <p class="panel-subtitle">Introduce los dos datos de referencia para ubicar el pedido correcto.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= h($error) ?></div>
                    <?php endif; ?>

                    <form method="POST" class="form-grid">
                        <div class="form-grid two">
                            <div class="field">
                                <label class="field-label" for="customer_number">Número de cliente</label>
                                <input class="input" type="text" id="customer_number" name="customer_number" placeholder="CUST001" required>
                            </div>
                            <div class="field">
                                <label class="field-label" for="invoice_number">Número de factura</label>
                                <input class="input" type="text" id="invoice_number" name="invoice_number" placeholder="INV-001" required>
                            </div>
                        </div>

                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                        <button type="submit" class="btn btn-primary btn-block">Consultar orden</button>
                    </form>
                </div>
            </div>

            <?php if ($result): ?>
                <?php
                $status_steps = array(
                    'Ordered'    => 'Ordenado',
                    'In process' => 'En proceso',
                    'In route'   => 'En camino',
                    'Delivered'  => 'Entregado',
                );
                $status_order = array_keys($status_steps);
                $current_idx  = array_search($result['status'], $status_order);
                if ($current_idx === false) { $current_idx = 0; }
                ?>
                <div class="detail-card surface surface-strong">
                    <div class="section-head">
                        <div>
                            <span class="eyebrow">Resultado</span>
                            <h2 class="panel-title">Detalle de la orden</h2>
                        </div>
                        <span class="status <?= strtolower(str_replace(' ', '-', $result['status'])) ?>"><?= translate_status($result['status']) ?></span>
                    </div>

                    <!-- Status progress stepper -->
                    <div class="status-progress">
                        <?php foreach ($status_steps as $key => $label):
                            $idx  = array_search($key, $status_order);
                            if ($idx < $current_idx) {
                                $cls  = 'done';
                                $icon = '&#10003;';
                            } elseif ($idx === $current_idx) {
                                $cls  = 'active';
                                $icon = $idx + 1;
                            } else {
                                $cls  = '';
                                $icon = $idx + 1;
                            }
                        ?>
                            <div class="sp-step <?= $cls ?>">
                                <div class="sp-dot"><?= $icon ?></div>
                                <span class="sp-label"><?= h($label) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="info-grid">
                        <div class="info-block">
                            <span class="info-label">Factura</span>
                            <div class="info-value"><?= h($result['invoice_number']) ?></div>
                        </div>
                        <div class="info-block">
                            <span class="info-label">Cliente</span>
                            <div class="info-value"><?= h($result['customer_name']) ?></div>
                        </div>
                        <div class="info-block">
                            <span class="info-label">Número de cliente</span>
                            <div class="info-value"><?= h($result['customer_number']) ?></div>
                        </div>
                        <div class="info-block">
                            <span class="info-label">Fecha de orden</span>
                            <div class="info-value"><?= format_date($result['created_at']) ?></div>
                        </div>
                        <div class="info-block full">
                            <span class="info-label">Dirección de entrega</span>
                            <div class="info-value"><?= h($result['delivery_address']) ?></div>
                        </div>
                        <?php if (!empty($result['notes'])): ?>
                            <div class="info-block full">
                                <span class="info-label">Notas</span>
                                <div class="info-value"><?= h($result['notes']) ?></div>
                            </div>
                        <?php endif; ?>
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
                                <?php if (!empty($result['photo_loaded_path'])): ?>
                                    <div class="photo-img-wrap">
                                        <a href="<?= APP_URL . '/uploads/' . h($result['photo_loaded_path']) ?>" target="_blank" rel="noopener noreferrer">
                                            <img class="photo-img" src="<?= APP_URL . '/uploads/' . h($result['photo_loaded_path']) ?>" alt="Foto de carga de la orden">
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-empty">
                                        <div class="photo-empty-icon">&#128230;</div>
                                        <p>Aún no disponible</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="photo-card">
                                <div class="photo-card-head">
                                    <span class="photo-dot delivered"></span>
                                    <span class="photo-label">Foto de entrega</span>
                                </div>
                                <?php if (!empty($result['photo_delivered_path'])): ?>
                                    <div class="photo-img-wrap">
                                        <a href="<?= APP_URL . '/uploads/' . h($result['photo_delivered_path']) ?>" target="_blank" rel="noopener noreferrer">
                                            <img class="photo-img" src="<?= APP_URL . '/uploads/' . h($result['photo_delivered_path']) ?>" alt="Foto de entrega de la orden">
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="photo-empty">
                                        <div class="photo-empty-icon">&#127968;</div>
                                        <p>Aún no disponible</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="footer-note">
                <p><a class="inline-link" href="login.php">Ir al acceso interno</a></p>
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
