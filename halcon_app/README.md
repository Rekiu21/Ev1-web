# Halcón - Web App (Evidencia 1)

App web para seguimiento de pedidos (cliente) y dashboard interno con roles (Ventas, Compras, Almacén, Ruta, Admin).

## Requisitos
- Python 3.10+

## Instalación
Desde la raíz del workspace (donde está tu `.venv`):

```powershell
& "C:/Users/ikeri/OneDrive/Documentos/tecmi/Diseño de aplicaciones web/.venv/Scripts/python.exe" -m pip install -r "Evidencia1/halcon_app/requirements.txt"
```

## Ejecutar
```powershell
& "C:/Users/ikeri/OneDrive/Documentos/tecmi/Diseño de aplicaciones web/.venv/Scripts/python.exe" -m uvicorn Evidencia1.halcon_app.app.main:app --reload
```

Luego abre:
- Cliente (consulta): http://127.0.0.1:8000/
- Login interno: http://127.0.0.1:8000/login
- Dashboard: http://127.0.0.1:8000/dashboard/orders

## Usuario inicial (seed)
Al primer arranque se crea un admin por defecto:
- usuario: `admin`
- contraseña: `admin123`

Puedes cambiarlo luego desde el dashboard (pantalla de usuarios).

## (Opcional) Usar MySQL con XAMPP
La app funciona “de verdad” con SQLite ya (persistente en archivo). Si tu profe pide MySQL/XAMPP:

1) En XAMPP, inicia **MySQL**.
2) En phpMyAdmin crea la BD `halcon`.
3) Ejecuta el servidor con `DATABASE_URL`:

```powershell
$env:DATABASE_URL = "mysql+pymysql://root:@127.0.0.1:3306/halcon"
& "C:/Users/ikeri/OneDrive/Documentos/tecmi/Diseño de aplicaciones web/.venv/Scripts/python.exe" -m uvicorn Evidencia1.halcon_app.app.main:app --reload
```

Las tablas se crean automáticamente al arrancar.

## Prueba rápida (flujo completo)
1) Entra a `http://127.0.0.1:8000/login` con `admin/admin123`.
2) (Opcional) Crea usuarios por rol en `Usuarios`.
3) Crea un pedido (rol `Sales`) en `Pedidos` → `Nuevo pedido`.
4) Cambia estatus:
	- `Warehouse`: `Ordered` → `In process` → `In route`
	- `Route`: sube **Foto de unidad cargada** (solo si está `In route`).
	- `Route`: sube **Evidencia entrega** (al subir, el pedido queda `Delivered`).
5) Cliente: entra a `http://127.0.0.1:8000/` y consulta con No. Cliente + No. Factura.
