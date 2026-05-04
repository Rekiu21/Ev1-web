# Halcón - Sistema de Órdenes de Compra

## Versión PHP — Hostinger (Ev3)

El servidor original en Render no garantizaba disponibilidad continua y los tiempos de arranque en frío lo hacían poco viable para pruebas reales. Para la evidencia 3 se migró la aplicación a PHP 7 + MySQL, desplegada en un servidor Hostinger que ya teníamos contratado para uso personal, aprovechando la infraestructura disponible sin incurrir en costos adicionales.

La versión PHP replica todas las funcionalidades de la versión FastAPI original: autenticación por roles, gestión de órdenes, control de estados con reglas de negocio, carga de evidencia fotográfica y rastreo público. El código está en la carpeta `halcon-php/`.

- URL en producción: https://rekiu.com/halcon-php

---

Aplicación web desarrollada con FastAPI para gestión de órdenes de compra.

## Requisitos de Servidor

- Python 3.9+
- MySQL 5.7+ (opcional - soporta SQLite por defecto)

## Instalación en Servidor

### 1. Clonar el repositorio

```bash
git clone https://github.com/Rekiu21/Ev1-web.git
cd Ev1-web
```

### 2. Configurar variables de entorno

```bash
# Copiar el archivo de ejemplo
cp .env.example .env

# Editar .env con tus configuraciones
nano .env
```

**Variables importantes:**
- `DATABASE_URL`: URL de conexión a BD (ej: `mysql+pymysql://root:password@localhost:3306/halcon`)
- `SECRET_KEY`: Generar una clave segura para sesiones

### 3. Instalar dependencias

```bash
pip install -r requirements.txt
```

### 4. Inicializar base de datos

```bash
python -c "from halcon_app.app.db import init_db; init_db()"
```

### 5. Ejecutar la aplicación

```bash
cd halcon_app
uvicorn app.main:app --host 0.0.0.0 --port 8000
```

## Configuración de Producción (cPanel/Shared Hosting)

### Con Gunicorn (recomendado)

```bash
pip install gunicorn
gunicorn -w 4 -b 0.0.0.0:8000 --chdir halcon_app app.main:app
```

## Estructura del Proyecto

```
├── .env.example              # Plantilla de variables de entorno
├── .htaccess                 # Configuración del servidor web
├── requirements.txt          # Dependencias Python
├── halcon_app/
│   ├── app/
│   │   ├── main.py          # App principal FastAPI
│   │   ├── settings.py      # Configuración
│   │   ├── db.py            # Conexión a BD
│   │   ├── models.py        # Modelos SQLModel
│   │   ├── auth.py          # Autenticación
│   │   ├── security.py      # Seguridad (hash, etc)
│   │   ├── seed.py          # Datos iniciales
│   │   ├── static/          # CSS, JS, etc
│   │   └── templates/       # Templates HTML
│   └── requirements.txt      # Dependencias locales
```

## Rutas Principales

- `/` - Página de inicio
- `/login` - Login de usuario
- `/dashboard` - Panel de control (requiere autenticación)
- `/orders` - Lista de órdenes
- `/tracking/{order_id}` - Rastreo público de órdenes

## Notas de Seguridad

- ⚠️ **Cambiar SECRET_KEY en producción**
- ⚠️ **Usar DATABASE_URL con credenciales seguras**
- ⚠️ **El .htaccess bloquea acceso a .env y .git**
- ⚠️ **Usar HTTPS en producción**

## Soporte

Para reportar problemas, contacta al equipo de desarrollo.
