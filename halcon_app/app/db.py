from __future__ import annotations

import os

from sqlmodel import Session, SQLModel, create_engine

from .settings import get_settings

_settings = get_settings()


def _build_engine():
    database_url = os.getenv("DATABASE_URL")
    if database_url:
        # Ejemplo MySQL (XAMPP): mysql+pymysql://root:@127.0.0.1:3306/halcon
        return create_engine(database_url, pool_pre_ping=True)

    return create_engine(
        f"sqlite:///{_settings.db_path}",
        connect_args={"check_same_thread": False},
    )


engine = _build_engine()


def init_db() -> None:
    SQLModel.metadata.create_all(engine)


def get_session():
    with Session(engine) as session:
        yield session
