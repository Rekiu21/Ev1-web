<?php
/**
 * Funciones de utilidad y seguridad
 */

// ============ SEGURIDAD ============

/**
 * Hash de contraseña
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

/**
 * Verificar contraseña
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Escapar HTML
 */
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Verificar CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        if (function_exists('random_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            $_SESSION['csrf_token'] = sha1(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============ AUTENTICACIÓN ============

/**
 * Obtener usuario actual
 */
function get_logged_in_user() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? AND is_active = TRUE');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Requerir login
 */
function require_login() {
    $user = get_logged_in_user();
    if (!$user) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    return $user;
}

/**
 * Requerir rol específico
 */
function require_role($required_roles) {
    $user = get_logged_in_user();
    if (!$user) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    if (!in_array($user['role'], $required_roles)) {
        http_response_code(403);
        die('Acceso denegado. Rol insuficiente.');
    }
    
    return $user;
}

/**
 * Login de usuario
 */
function login_user($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = TRUE');
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch();
    
    if (!$user || !verify_password($password, $user['password_hash'])) {
        return false;
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    return true;
}

/**
 * Logout
 */
function logout_user() {
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// ============ ÓRDENES ============

/**
 * Obtener orden por ID
 */
function get_order($order_id) {
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND deleted = FALSE');
    $stmt->execute([$order_id]);
    return $stmt->fetch();
}

/**
 * Listar órdenes con filtros
 */
function list_orders($filters = []) {
    global $pdo;
    
    $query = 'SELECT * FROM orders WHERE deleted = FALSE';
    $params = [];
    
    if (!empty($filters['invoice'])) {
        $query .= ' AND invoice_number LIKE ?';
        $params[] = '%' . $filters['invoice'] . '%';
    }
    
    if (!empty($filters['customer'])) {
        $query .= ' AND customer_number LIKE ?';
        $params[] = '%' . $filters['customer'] . '%';
    }
    
    if (!empty($filters['status'])) {
        $query .= ' AND status = ?';
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['date'])) {
        $query .= ' AND DATE(created_at) = ?';
        $params[] = $filters['date'];
    }
    
    $query .= ' ORDER BY created_at DESC';
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Crear nueva orden
 */
function create_order($data) {
    global $pdo;
    
    // Validar que no exista factura con ese número
    $stmt = $pdo->prepare('SELECT id FROM orders WHERE invoice_number = ?');
    $stmt->execute([trim($data['invoice_number'])]);
    if ($stmt->fetch()) {
        return ['error' => 'No. de factura ya existe.'];
    }
    
    $stmt = $pdo->prepare('
        INSERT INTO orders (invoice_number, customer_name, customer_number, fiscal_data, delivery_address, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    
    try {
        $stmt->execute([
            trim($data['invoice_number']),
            trim($data['customer_name']),
            trim($data['customer_number']),
            isset($data['fiscal_data']) ? trim($data['fiscal_data']) : '',
            trim($data['delivery_address']),
            isset($data['notes']) ? trim($data['notes']) : '',
            'Ordered'
        ]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
    } catch (Exception $e) {
        return ['error' => 'Error al crear la orden: ' . $e->getMessage()];
    }
}

/**
 * Actualizar orden
 */
function update_order($order_id, $data) {
    global $pdo;
    
    $updates = [];
    $params = [];
    
    if (isset($data['status'])) {
        $updates[] = 'status = ?';
        $params[] = $data['status'];
    }
    if (isset($data['notes'])) {
        $updates[] = 'notes = ?';
        $params[] = trim($data['notes']);
    }
    if (isset($data['delivery_address'])) {
        $updates[] = 'delivery_address = ?';
        $params[] = trim($data['delivery_address']);
    }
    
    if (empty($updates)) {
        return ['error' => 'No hay datos para actualizar'];
    }
    
    $params[] = $order_id;
    $query = 'UPDATE orders SET ' . implode(', ', $updates) . ' WHERE id = ? AND deleted = FALSE';
    
    $stmt = $pdo->prepare($query);
    try {
        $stmt->execute($params);
        return ['success' => true];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Eliminar orden (soft delete)
 */
function delete_order($order_id) {
    global $pdo;
    
    $stmt = $pdo->prepare('UPDATE orders SET deleted = TRUE WHERE id = ? AND deleted = FALSE');
    $stmt->execute([$order_id]);
    return ['success' => true];
}

/**
 * Subir foto de orden.
 *
 * Reglas del sistema original:
 *   - Solo se pueden subir fotos cuando la orden está en estado "In route".
 *   - Al subir foto de entrega (delivered), el pedido pasa automáticamente a "Delivered".
 */
function upload_order_photo($order_id, $photo_type) {
    global $pdo;

    $order = get_order($order_id);
    if (!$order) {
        return array('error' => 'Orden no encontrada');
    }

    // Validación de estado: solo se pueden subir fotos cuando la orden está en camino
    if ($order['status'] !== 'In route') {
        return array('error' => 'Solo se pueden subir fotos cuando la orden está en estado "En camino" (In route).');
    }

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        return array('error' => 'No se subió archivo o hubo error');
    }

    $file = $_FILES['photo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, array('jpg', 'jpeg', 'png'))) {
        return array('error' => 'Solo se permiten imágenes (jpg, jpeg, png)');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return array('error' => 'La imagen es muy grande (máx. 5 MB)');
    }

    $filename = 'order_' . $order_id . '_' . $photo_type . '_' . time() . '.' . $ext;
    $filepath = UPLOAD_DIR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return array('error' => 'Error al guardar la imagen en el servidor');
    }

    // Guardar ruta en BD
    $column = ($photo_type === 'loaded') ? 'photo_loaded_path' : 'photo_delivered_path';
    $stmt = $pdo->prepare("UPDATE orders SET {$column} = ? WHERE id = ?");
    $stmt->execute(array($filename, $order_id));

    // Al subir foto de entrega → transición automática a Delivered
    if ($photo_type === 'delivered') {
        $stmt2 = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $stmt2->execute(array('Delivered', $order_id));
    }

    return array('success' => true, 'filename' => $filename);
}

// ============ FORMATEO ============

/**
 * Formatear fecha
 */
function format_date($date_str, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date_str));
}

/**
 * Traducir estado de orden
 */
function translate_status($status) {
    $statuses = ORDER_STATUSES;
    return array_key_exists($status, $statuses) ? $statuses[$status] : $status;
}

/**
 * Traducir rol
 */
function translate_role($role) {
    $roles = ROLES;
    return array_key_exists($role, $roles) ? $roles[$role] : $role;
}

/**
 * Validar si un rol puede realizar una transición de estado.
 * Replica la lógica de _can_transition() del sistema original FastAPI.
 *
 * Reglas:
 *   Ordered   → In process : Warehouse, Admin
 *   In process → In route   : Warehouse, Admin
 *   In route  → Delivered   : Route, Admin
 *   Cualquier otra combinación está prohibida.
 */
function can_transition($user_role, $current_status, $target_status) {
    if ($target_status === 'In process') {
        return in_array($user_role, array('Warehouse', 'Admin'))
            && $current_status === 'Ordered';
    }
    if ($target_status === 'In route') {
        return in_array($user_role, array('Warehouse', 'Admin'))
            && $current_status === 'In process';
    }
    if ($target_status === 'Delivered') {
        return in_array($user_role, array('Route', 'Admin'))
            && $current_status === 'In route';
    }
    return false;
}
