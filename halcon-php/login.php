<?php
require 'config.php';
require 'functions.php';

// Si ya está logueado, redirigir al dashboard
if (get_logged_in_user()) {
    header('Location: ' . APP_URL . '/dashboard/orders.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (login_user($username, $password)) {
        header('Location: ' . APP_URL . '/dashboard/orders.php');
        exit;
    } else {
        $error = 'Credenciales inválidas.';
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halcón - Login</title>
    <link rel="stylesheet" href="static/styles.css">
</head>
<body>
    <div class="auth-layout">
        <div class="auth-card surface">
            <aside class="auth-aside">
                <div class="stack">
                    <div class="brand">
                        <span class="brand-mark">H</span>
                        <div class="brand-meta">
                            <span class="brand-title">Halcón</span>
                            <span class="brand-subtitle">Panel de operación comercial</span>
                        </div>
                    </div>

                    <div class="stack">
                        <span class="eyebrow">Acceso interno</span>
                        <h1 class="hero-title">Opera con claridad y foco.</h1>
                        <p class="muted">Gestión de pedidos y estatus en un flujo limpio para el equipo.</p>
                    </div>
                </div>

                <div class="metric-grid">
                    <div class="metric-card">
                        <span class="metric-value">5</span>
                        <span class="metric-label">Roles activos</span>
                    </div>
                    <div class="metric-card">
                        <span class="metric-value">24/7</span>
                        <span class="metric-label">Seguimiento continuo</span>
                    </div>
                </div>
            </aside>

            <section class="auth-panel">
                <div class="panel-header">
                    <span class="eyebrow">Iniciar sesión</span>
                    <h2 class="panel-title">Bienvenido de nuevo</h2>
                    <p class="panel-subtitle">Ingresa para continuar.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= h($error) ?></div>
                <?php endif; ?>

                <p class="demo-box"><strong>Demo:</strong> admin / admin123</p>

                <form method="POST" class="form-grid">
                    <div class="field">
                        <label class="field-label" for="username">Usuario</label>
                        <input class="input" type="text" id="username" name="username" required autofocus>
                    </div>

                    <div class="field">
                        <label class="field-label" for="password">Contraseña</label>
                        <input class="input" type="password" id="password" name="password" required>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                    <button type="submit" class="btn btn-primary btn-block">Entrar al panel</button>
                </form>

                <div class="footer-note">
                    <a class="inline-link" href="<?= h(APP_URL) ?>/">Volver al rastreo público</a>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
