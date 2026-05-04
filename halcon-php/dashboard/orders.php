<?php
require '../config.php';
require '../functions.php';

$user = require_login();

// Filtros
$filters = [];
$q_invoice = isset($_GET['q_invoice']) ? $_GET['q_invoice'] : '';
$q_customer = isset($_GET['q_customer']) ? $_GET['q_customer'] : '';
$q_status = isset($_GET['q_status']) ? $_GET['q_status'] : '';
$q_date = isset($_GET['q_date']) ? $_GET['q_date'] : '';

if (!empty($q_invoice)) $filters['invoice'] = $q_invoice;
if (!empty($q_customer)) $filters['customer'] = $q_customer;
if (!empty($q_status)) $filters['status'] = $q_status;
if (!empty($q_date)) $filters['date'] = $q_date;

$orders = list_orders($filters);
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halcón - Órdenes</title>
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
                        <span class="brand-subtitle">Órdenes y flujo operativo</span>
                    </div>
                </div>
                <div class="topbar-actions">
                    <span class="user-chip"><?= h($user['username']) ?> · <?= translate_role($user['role']) ?></span>
                    <button class="btn-evidence" onclick="openEvidenceModal()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span class="ev-btn-label">Project Description</span>
                    </button>
                    <a class="btn btn-secondary" href="../logout.php">Cerrar sesión</a>
                </div>
            </div>

            <div class="page-intro">
                <div>
                    <span class="eyebrow">Panel interno</span>
                    <h1 class="page-title">Órdenes</h1>
                    <p class="page-note">Una vista limpia para filtrar, revisar y abrir pedidos sin saturar la pantalla con elementos innecesarios.</p>
                </div>
                <?php if (in_array($user['role'], ['Sales', 'Admin'])): ?>
                    <a href="order_new.php" class="btn btn-primary">Nueva orden</a>
                <?php endif; ?>
            </div>

            <div class="filters-bar surface">
                <form method="GET" class="filters-form">
                    <div class="field">
                        <label class="field-label">Factura</label>
                        <input class="input" type="text" name="q_invoice" value="<?= h($q_invoice) ?>" placeholder="INV-001">
                    </div>
                    <div class="field">
                        <label class="field-label">Cliente</label>
                        <input class="input" type="text" name="q_customer" value="<?= h($q_customer) ?>" placeholder="CUST001">
                    </div>
                    <div class="field">
                        <label class="field-label">Estado</label>
                        <select class="select" name="q_status">
                            <option value="">Todos</option>
                            <?php foreach (ORDER_STATUSES as $key => $val): ?>
                                <option value="<?= h($key) ?>" <?= $q_status === $key ? 'selected' : '' ?>><?= h($val) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Fecha</label>
                        <input class="input" type="date" name="q_date" value="<?= h($q_date) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                </form>
            </div>

            <div class="table-card surface surface-strong">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Listado</span>
                        <h2 class="panel-title">Pedidos registrados</h2>
                    </div>
                    <span class="subtle-chip"><?= count($orders) ?> resultados</span>
                </div>

                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <h3>Sin resultados</h3>
                        <p>No hay órdenes que coincidan con los filtros aplicados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Factura</th>
                                    <th>Cliente</th>
                                    <th>Número cliente</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><span class="invoice"><?= h($order['invoice_number']) ?></span></td>
                                        <td><?= h($order['customer_name']) ?></td>
                                        <td><?= h($order['customer_number']) ?></td>
                                        <td><span class="status <?= strtolower(str_replace(' ', '-', $order['status'])) ?>"><?= translate_status($order['status']) ?></span></td>
                                        <td><?= format_date($order['created_at'], 'd/m/Y') ?></td>
                                        <td><a href="order_detail.php?id=<?= $order['id'] ?>" class="inline-link">Abrir</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
