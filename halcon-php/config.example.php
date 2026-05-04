<?php
/**
 * CONFIGURACIÓN PARA HOSTINGER
 * 
 * Renombra este archivo a "config.php" y edita los valores
 * con los datos de tu Hostinger
 */

// ============ DATOS DE LA BASE DE DATOS ============

// En Hostinger generalmente es 'localhost'
define('DB_HOST', 'localhost');

// Nombre de usuario de MySQL que creaste en Hostinger
define('DB_USER', 'tu_usuario_aqui');

// Contraseña del usuario MySQL
define('DB_PASS', 'tu_contraseña_aqui');

// Nombre de la base de datos que creaste
define('DB_NAME', 'tu_base_datos_aqui');


// ============ CONFIGURACIÓN DE LA APLICACIÓN ============

// URL de la aplicación
define('APP_URL', 'https://rekiu.com/halcon');

// Directorio de uploads (no cambiar normalmente)
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// Nombre de la sesión
define('SESSION_NAME', 'halcon_session');

// Tiempo de timeout de sesión (en segundos)
define('SESSION_TIMEOUT', 3600); // 1 hora


// ============ NO EDITAR ABAJO ============

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false
        ]
    );
} catch (PDOException $e) {
    die("❌ Error de conexión a la base de datos:<br>" . $e->getMessage());
}

session_name(SESSION_NAME);
session_start();

if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
    session_destroy();
}
$_SESSION['last_activity'] = time();

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

define('ROLES', [
    'Admin' => 'Administrador',
    'Sales' => 'Ventas',
    'Purchasing' => 'Compras',
    'Warehouse' => 'Almacén',
    'Route' => 'Ruta'
]);

define('ORDER_STATUSES', [
    'Ordered' => 'Ordenado',
    'In process' => 'En proceso',
    'In route' => 'En ruta',
    'Delivered' => 'Entregado'
]);
