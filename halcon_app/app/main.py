from __future__ import annotations

from datetime import date, datetime
from pathlib import Path
from typing import Optional

from fastapi import Depends, FastAPI, File, Form, HTTPException, Request, UploadFile
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from sqlmodel import Session, and_, col, select
from starlette.middleware.sessions import SessionMiddleware
from starlette.responses import Response

from .auth import get_current_user, require_roles
from .db import engine, get_session, init_db
from .models import Order, OrderStatus, Role, User
from .security import hash_password, verify_password
from .seed import ensure_default_admin
from .settings import get_settings

settings = get_settings()
app = FastAPI(title="Halcón")

app.add_middleware(SessionMiddleware, secret_key=settings.secret_key)

static_dir = Path(__file__).resolve().parent / "static"
static_dir.mkdir(parents=True, exist_ok=True)
app.mount("/static", StaticFiles(directory=str(static_dir)), name="static")

uploads_dir = settings.uploads_dir
app.mount("/uploads", StaticFiles(directory=str(uploads_dir)), name="uploads")

templates = Jinja2Templates(directory=str(Path(__file__).resolve().parent / "templates"))


@app.exception_handler(HTTPException)
def http_exception_handler(request: Request, exc: HTTPException) -> Response:
    # Para pantallas web, devolvemos HTML amigable en vez de JSON.
    accept = (request.headers.get("accept") or "").lower()
    wants_html = "text/html" in accept or "*/*" in accept

    # Si el usuario no está autenticado y está en dashboard, enviarlo al login.
    if exc.status_code == 401 and request.url.path.startswith("/dashboard"):
        return _redirect("/login")

    if wants_html:
        title = "Acceso denegado" if exc.status_code == 403 else "Error"
        if exc.status_code == 401:
            title = "Sesión requerida"
        if exc.status_code == 404:
            title = "No encontrado"

        message = "No tienes permiso para realizar esta acción." if exc.status_code == 403 else str(exc.detail)
        if exc.status_code == 401:
            message = "Tu sesión no es válida o expiró. Inicia sesión de nuevo."

        return templates.TemplateResponse(
            "error.html",
            {
                "request": request,
                "title": title,
                "status_code": exc.status_code,
                "message": message,
            },
            status_code=exc.status_code,
        )

    # Fallback: JSON para clientes no-HTML
    return JSONResponse(status_code=exc.status_code, content={"detail": exc.detail})


@app.on_event("startup")
def _startup() -> None:
    init_db()
    with Session(engine) as session:
        ensure_default_admin(session)


def _redirect(url: str) -> RedirectResponse:
    return RedirectResponse(url=url, status_code=303)


# -------------------------
# Public (Cliente)
# -------------------------


@app.get("/", response_class=HTMLResponse)
def public_home(request: Request):
    return templates.TemplateResponse(
        "public_tracking.html",
        {"request": request, "result": None, "error": None},
    )


@app.post("/track", response_class=HTMLResponse)
def public_track(
    request: Request,
    customer_number: str = Form(...),
    invoice_number: str = Form(...),
    session: Session = Depends(get_session),
):
    order = session.exec(
        select(Order).where(
            and_(
                Order.deleted == False,  # noqa: E712
                Order.customer_number == customer_number.strip(),
                Order.invoice_number == invoice_number.strip(),
            )
        )
    ).first()

    if not order:
        return templates.TemplateResponse(
            "public_tracking.html",
            {"request": request, "result": None, "error": "No se encontró el pedido."},
        )

    return templates.TemplateResponse(
        "public_tracking.html",
        {"request": request, "result": order, "error": None},
    )


# -------------------------
# Auth (Interno)
# -------------------------


@app.get("/login", response_class=HTMLResponse)
def login_form(request: Request):
    return templates.TemplateResponse(
        "login.html",
        {"request": request, "error": None},
    )


@app.post("/login")
def login(
    request: Request,
    username: str = Form(...),
    password: str = Form(...),
    session: Session = Depends(get_session),
):
    user = session.exec(select(User).where(User.username == username.strip())).first()
    if not user or not user.is_active or not verify_password(password, user.password_hash):
        return templates.TemplateResponse(
            "login.html",
            {"request": request, "error": "Credenciales inválidas."},
            status_code=401,
        )

    request.session["user_id"] = user.id
    return _redirect("/dashboard/orders")


@app.get("/logout")
def logout(request: Request):
    request.session.clear()
    return _redirect("/login")


# -------------------------
# Dashboard - Pedidos
# -------------------------


@app.get("/dashboard/orders", response_class=HTMLResponse)
def orders_list(
    request: Request,
    q_invoice: Optional[str] = None,
    q_customer: Optional[str] = None,
    q_status: Optional[str] = None,
    q_date: Optional[str] = None,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    filters = [Order.deleted == False]  # noqa: E712

    if q_invoice:
        filters.append(col(Order.invoice_number).contains(q_invoice.strip()))
    if q_customer:
        filters.append(col(Order.customer_number).contains(q_customer.strip()))
    if q_status:
        try:
            status_enum = OrderStatus(q_status)
            filters.append(Order.status == status_enum)
        except ValueError:
            pass
    if q_date:
        try:
            d = date.fromisoformat(q_date)
            start = datetime(d.year, d.month, d.day)
            end = datetime(d.year, d.month, d.day, 23, 59, 59)
            filters.append(and_(Order.created_at >= start, Order.created_at <= end))
        except ValueError:
            pass

    stmt = select(Order).where(and_(*filters)).order_by(Order.created_at.desc())
    orders = session.exec(stmt).all()

    return templates.TemplateResponse(
        "orders_list.html",
        {
            "request": request,
            "user": user,
            "orders": orders,
            "OrderStatus": OrderStatus,
            "q_invoice": q_invoice or "",
            "q_customer": q_customer or "",
            "q_status": q_status or "",
            "q_date": q_date or "",
        },
    )


@app.get("/dashboard/orders/new", response_class=HTMLResponse)
def order_new_form(
    request: Request,
    user: User = Depends(require_roles(Role.SALES, Role.ADMIN)),
):
    return templates.TemplateResponse(
        "order_new.html",
        {"request": request, "user": user, "error": None},
    )


@app.post("/dashboard/orders/new")
def order_create(
    request: Request,
    invoice_number: str = Form(...),
    customer_name: str = Form(...),
    customer_number: str = Form(...),
    fiscal_data: str = Form(...),
    delivery_address: str = Form(...),
    notes: str = Form(""),
    user: User = Depends(require_roles(Role.SALES, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    existing = session.exec(select(Order).where(Order.invoice_number == invoice_number.strip())).first()
    if existing:
        return templates.TemplateResponse(
            "order_new.html",
            {"request": request, "user": user, "error": "No. de factura ya existe."},
            status_code=400,
        )

    order = Order(
        invoice_number=invoice_number.strip(),
        customer_name=customer_name.strip(),
        customer_number=customer_number.strip(),
        fiscal_data=fiscal_data.strip(),
        delivery_address=delivery_address.strip(),
        notes=notes.strip(),
        status=OrderStatus.ORDERED,
    )
    session.add(order)
    session.commit()
    session.refresh(order)
    return _redirect(f"/dashboard/orders/{order.id}")


@app.get("/dashboard/orders/{order_id}", response_class=HTMLResponse)
def order_detail(
    request: Request,
    order_id: int,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or order.deleted:
        raise HTTPException(status_code=404)

    allowed_targets: list[str] = []
    for candidate in (OrderStatus.IN_PROCESS, OrderStatus.IN_ROUTE, OrderStatus.DELIVERED):
        if _can_transition(user, order.status, candidate):
            allowed_targets.append(candidate.value)

    return templates.TemplateResponse(
        "order_detail.html",
        {
            "request": request,
            "user": user,
            "order": order,
            "allowed_targets": allowed_targets,
        },
    )


@app.post("/dashboard/orders/{order_id}/edit")
def order_edit(
    order_id: int,
    invoice_number: str = Form(...),
    customer_name: str = Form(...),
    customer_number: str = Form(...),
    fiscal_data: str = Form(...),
    delivery_address: str = Form(...),
    notes: str = Form(""),
    user: User = Depends(require_roles(Role.SALES, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or order.deleted:
        raise HTTPException(status_code=404)

    if order.invoice_number != invoice_number.strip():
        existing = session.exec(select(Order).where(Order.invoice_number == invoice_number.strip())).first()
        if existing:
            raise HTTPException(status_code=400, detail="No. de factura ya existe")

    order.invoice_number = invoice_number.strip()
    order.customer_name = customer_name.strip()
    order.customer_number = customer_number.strip()
    order.fiscal_data = fiscal_data.strip()
    order.delivery_address = delivery_address.strip()
    order.notes = notes.strip()

    session.add(order)
    session.commit()

    return _redirect(f"/dashboard/orders/{order.id}")


def _can_transition(user: User, current: OrderStatus, target: OrderStatus) -> bool:
    if target == OrderStatus.IN_PROCESS:
        return user.role in (Role.WAREHOUSE, Role.ADMIN) and current == OrderStatus.ORDERED
    if target == OrderStatus.IN_ROUTE:
        return user.role in (Role.WAREHOUSE, Role.ADMIN) and current in (OrderStatus.IN_PROCESS,)
    if target == OrderStatus.DELIVERED:
        return user.role in (Role.ROUTE, Role.ADMIN) and current == OrderStatus.IN_ROUTE
    if target == OrderStatus.ORDERED:
        return False
    return False


@app.post("/dashboard/orders/{order_id}/status")
def order_change_status(
    order_id: int,
    status_value: str = Form(...),
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or order.deleted:
        raise HTTPException(status_code=404)

    try:
        target = OrderStatus(status_value)
    except ValueError:
        raise HTTPException(status_code=400)

    if not _can_transition(user, order.status, target):
        raise HTTPException(status_code=403)

    order.status = target
    session.add(order)
    session.commit()

    return _redirect(f"/dashboard/orders/{order.id}")


@app.post("/dashboard/orders/{order_id}/delete")
def order_soft_delete(
    order_id: int,
    user: User = Depends(require_roles(Role.SALES, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or order.deleted:
        raise HTTPException(status_code=404)

    order.deleted = True
    session.add(order)
    session.commit()

    return _redirect("/dashboard/orders")


@app.get("/dashboard/deleted", response_class=HTMLResponse)
def deleted_orders_list(
    request: Request,
    user: User = Depends(require_roles(Role.SALES, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    orders = session.exec(select(Order).where(Order.deleted == True).order_by(Order.created_at.desc())).all()  # noqa: E712
    return templates.TemplateResponse(
        "orders_deleted.html",
        {"request": request, "user": user, "orders": orders},
    )


@app.post("/dashboard/deleted/{order_id}/restore")
def deleted_order_restore(
    order_id: int,
    user: User = Depends(require_roles(Role.SALES, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or not order.deleted:
        raise HTTPException(status_code=404)

    order.deleted = False
    session.add(order)
    session.commit()
    return _redirect(f"/dashboard/orders/{order.id}")


# -------------------------
# Fotos (solo Ruta)
# -------------------------


def _save_upload(order_id: int, kind: str, file: UploadFile) -> str:
    order_dir = uploads_dir / str(order_id)
    order_dir.mkdir(parents=True, exist_ok=True)

    ext = Path(file.filename or "").suffix.lower()
    if ext not in (".jpg", ".jpeg", ".png"):
        ext = ".jpg"

    out_path = order_dir / f"{kind}{ext}"
    content = file.file.read()
    out_path.write_bytes(content)

    # URL path
    return f"/uploads/{order_id}/{out_path.name}"


@app.post("/dashboard/orders/{order_id}/upload/loaded")
def upload_loaded_photo(
    order_id: int,
    photo: UploadFile = File(...),
    user: User = Depends(require_roles(Role.ROUTE, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or order.deleted:
        raise HTTPException(status_code=404)

    if order.status != OrderStatus.IN_ROUTE:
        raise HTTPException(status_code=400, detail="La foto de unidad cargada aplica en 'In route'.")

    url_path = _save_upload(order_id, "loaded", photo)
    order.photo_loaded_path = url_path
    session.add(order)
    session.commit()

    return _redirect(f"/dashboard/orders/{order.id}")


@app.post("/dashboard/orders/{order_id}/upload/delivered")
def upload_delivered_photo(
    order_id: int,
    photo: UploadFile = File(...),
    user: User = Depends(require_roles(Role.ROUTE, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or order.deleted:
        raise HTTPException(status_code=404)

    if order.status != OrderStatus.IN_ROUTE:
        raise HTTPException(status_code=400, detail="La evidencia de entrega aplica en 'In route'.")

    url_path = _save_upload(order_id, "delivered", photo)
    order.photo_delivered_path = url_path
    # Al subir evidencia de entrega, el pedido pasa a Delivered.
    order.status = OrderStatus.DELIVERED
    session.add(order)
    session.commit()

    return _redirect(f"/dashboard/orders/{order.id}")


# -------------------------
# Admin - Usuarios
# -------------------------


@app.get("/dashboard/users", response_class=HTMLResponse)
def users_list(
    request: Request,
    notice: str | None = None,
    user: User = Depends(require_roles(Role.ADMIN)),
    session: Session = Depends(get_session),
):
    users = session.exec(select(User).order_by(User.username)).all()
    return templates.TemplateResponse(
        "users_list.html",
        {
            "request": request,
            "user": user,
            "users": users,
            "Role": Role,
            "error": None,
            "notice": notice,
        },
    )


@app.post("/dashboard/users/{user_id}/role")
def user_change_role(
    user_id: int,
    role_value: str = Form(...),
    user: User = Depends(require_roles(Role.ADMIN)),
    session: Session = Depends(get_session),
):
    target = session.get(User, user_id)
    if not target:
        raise HTTPException(status_code=404)

    try:
        role = Role(role_value)
    except ValueError:
        raise HTTPException(status_code=400)

    target.role = role
    session.add(target)
    session.commit()
    return _redirect("/dashboard/users?notice=role_saved")


@app.post("/dashboard/users/new")
def user_create(
    request: Request,
    username: str = Form(...),
    password: str = Form(...),
    role_value: str = Form(...),
    user: User = Depends(require_roles(Role.ADMIN)),
    session: Session = Depends(get_session),
):
    try:
        role = Role(role_value)
    except ValueError:
        raise HTTPException(status_code=400)

    username_clean = username.strip()
    if not username_clean:
        users = session.exec(select(User).order_by(User.username)).all()
        return templates.TemplateResponse(
            "users_list.html",
            {
                "request": request,
                "user": user,
                "users": users,
                "Role": Role,
                "error": "El usuario no puede estar vacío.",
                "notice": None,
            },
            status_code=400,
        )

    existing = session.exec(select(User).where(User.username == username_clean)).first()
    if existing:
        users = session.exec(select(User).order_by(User.username)).all()
        return templates.TemplateResponse(
            "users_list.html",
            {
                "request": request,
                "user": user,
                "users": users,
                "Role": Role,
                "error": "Usuario ya existe.",
                "notice": None,
            },
            status_code=400,
        )

    new_user = User(
        username=username_clean,
        password_hash=hash_password(password),
        role=role,
        is_active=True,
    )
    session.add(new_user)
    session.commit()

    return _redirect("/dashboard/users?notice=user_created")


# -------------------------
# Pedidos eliminados - Editar (sin restaurar)
# -------------------------


@app.get("/dashboard/deleted/{order_id}", response_class=HTMLResponse)
def deleted_order_detail(
    request: Request,
    order_id: int,
    user: User = Depends(require_roles(Role.SALES, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or not order.deleted:
        raise HTTPException(status_code=404)

    return templates.TemplateResponse(
        "order_deleted_detail.html",
        {"request": request, "user": user, "order": order},
    )


@app.post("/dashboard/deleted/{order_id}/edit")
def deleted_order_edit(
    order_id: int,
    invoice_number: str = Form(...),
    customer_name: str = Form(...),
    customer_number: str = Form(...),
    fiscal_data: str = Form(...),
    delivery_address: str = Form(...),
    notes: str = Form(""),
    user: User = Depends(require_roles(Role.SALES, Role.ADMIN)),
    session: Session = Depends(get_session),
):
    order = session.get(Order, order_id)
    if not order or not order.deleted:
        raise HTTPException(status_code=404)

    if order.invoice_number != invoice_number.strip():
        existing = session.exec(select(Order).where(Order.invoice_number == invoice_number.strip())).first()
        if existing:
            raise HTTPException(status_code=400, detail="No. de factura ya existe")

    order.invoice_number = invoice_number.strip()
    order.customer_name = customer_name.strip()
    order.customer_number = customer_number.strip()
    order.fiscal_data = fiscal_data.strip()
    order.delivery_address = delivery_address.strip()
    order.notes = notes.strip()

    session.add(order)
    session.commit()

    return _redirect(f"/dashboard/deleted/{order.id}")
