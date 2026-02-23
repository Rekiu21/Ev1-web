from __future__ import annotations

from sqlmodel import Session, select

from .models import Role, User
from .security import hash_password


def ensure_default_admin(session: Session) -> None:
    existing = session.exec(select(User).where(User.username == "admin")).first()
    if existing:
        return

    admin = User(
        username="admin",
        password_hash=hash_password("admin123"),
        role=Role.ADMIN,
        is_active=True,
    )
    session.add(admin)
    session.commit()
