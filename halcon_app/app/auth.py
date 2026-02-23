from __future__ import annotations

from typing import Optional

from fastapi import Depends, HTTPException, Request, status
from sqlmodel import Session, select

from .db import get_session
from .models import Role, User


def get_current_user(
    request: Request,
    session: Session = Depends(get_session),
) -> User:
    user_id = request.session.get("user_id")
    if not user_id:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED)

    user = session.get(User, user_id)
    if not user or not user.is_active:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED)

    return user


def require_roles(*roles: Role):
    def _dep(user: User = Depends(get_current_user)) -> User:
        if user.role not in roles:
            raise HTTPException(status_code=status.HTTP_403_FORBIDDEN)
        return user

    return _dep


def authenticate_user(session: Session, username: str) -> Optional[User]:
    return session.exec(select(User).where(User.username == username)).first()
