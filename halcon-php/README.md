# Halcón - Sistema de Órdenes de Compra (PHP)

Versión PHP de la aplicación para Hostinger shared hosting.

## 📋 Requisitos

- PHP 7.4+
- MySQL 5.7+
- Hostinger (Shared Hosting con PHP y MySQL)

## 🚀 Instalación en Hostinger

### 1. Subir archivos

Sube toda la carpeta `halcon-php` a tu servidor Hostinger:
- Vía FTP: `/public_html/halcon/`
- Vía File Manager: mismo destino

### 2. Crear base de datos

En el panel de Hostinger:
1. Ve a **Bases de datos MySQL**
2. Crea una nueva BD (ej: `halcon_db`)
3. Crea un usuario MySQL (ej: `halcon_user`)
4. Otorga privilegios al usuario sobre la BD

### 3. Ejecutar el SQL

1. Abre **phpMyAdmin** desde tu panel Hostinger
2. Selecciona tu base de datos
3. Ve a **SQL** y pega el contenido de `db.sql`
4. Ejecuta

Este script crea:
- Tabla `users` (con usuario admin)
- Tabla `orders`
- Datos de ejemplo

**Usuarios por defecto:**
- admin / admin123
- ventas / admin123
- compras / admin123
- almacen / admin123

### 4. Configurar `config.php`

Edita el archivo `config.php` con tus datos:

```php
define('DB_HOST', 'localhost');      // Generalmente localhost
define('DB_USER', 'tu_usuario_db');  // El usuario creado
define('DB_PASS', 'tu_contraseña');  // La contraseña
define('DB_NAME', 'halcon_db');      // El nombre de la BD
```

### 5. Verificar permisos

Asegúrate de que la carpeta `uploads/` tiene permisos de escritura (755).

## 🔗 Acceder a la aplicación

- **Rastreo público:** `https://rekiu.com/halcon/`
- **Login:** `https://rekiu.com/halcon/login.php`
- **Dashboard:** `https://rekiu.com/halcon/dashboard/orders.php`

## 📁 Estructura

```
halcon/
├── index.php              ← Rastreo público
├── login.php              ← Login de usuarios
├── logout.php             ← Cerrar sesión
├── config.php             ← Configuración (editar!)
├── functions.php          ← Funciones principales
├── db.sql                 ← Script SQL (ejecutar en phpMyAdmin)
├── .htaccess              ← Configuración del servidor
├── dashboard/
│   ├── orders.php         ← Listado de órdenes
│   ├── order_new.php      ← Crear nueva orden
│   └── order_detail.php   ← Detalle de orden
├── static/
│   └── styles.css         ← Estilos
└── uploads/               ← Fotos de órdenes
```

## 🔐 Seguridad

- ✅ `.htaccess` protege archivos sensibles (config.php, db.sql, etc)
- ✅ Contraseñas hasheadas con bcrypt
- ✅ CSRF tokens en todos los formularios
- ✅ Validación de roles y permisos
- ✅ SQL injection protected (prepared statements)
- ✅ XSS protected (htmlspecialchars)

## 👥 Roles y Permisos

| Rol | Ver Órdenes | Crear Órdenes | Actualizar Estado | Subir Fotos | Eliminar |
|-----|:-----------:|:-------------:|:----------------:|:-----------:|:--------:|
| Admin | ✅ | ✅ | ✅ | ✅ | ✅ |
| Sales | ✅ | ✅ | ❌ | ❌ | ❌ |
| Purchasing | ✅ | ❌ | ❌ | ❌ | ❌ |
| Warehouse | ✅ | ❌ | ✅ | ✅ | ❌ |
| Route | ✅ | ❌ | ✅ | ✅ | ❌ |

## 🆘 Troubleshooting

### "Error de conexión a BD"
- Verifica que los datos en `config.php` son correctos
- Verifica que la BD existe en phpMyAdmin
- Verifica que el usuario tiene permisos sobre la BD

### "Permiso denegado" al subir fotos
- Asegúrate que la carpeta `uploads/` existe
- Cambia permisos a 755 o 777

### "Las sesiones no funcionan"
- Verifica que las cookies están habilitadas en el navegador
- Verifica que PHP puede crear archivos temporales

### "La app redirige a /login.php infinitamente"
- Verifica que `APP_URL` en config.php es correcto
- Limpia cookies del navegador

## 📧 Soporte

Para problemas contacta al equipo de desarrollo.

## 📝 Notas

- Los datos de ejemplo se crean automáticamente
- Las fotos se guardan en `uploads/`
- Las sesiones expiran después de 1 hora de inactividad
- Usa HTTPS siempre (Hostinger lo proporciona gratis)
