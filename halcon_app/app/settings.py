from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path


@dataclass(frozen=True)
class Settings:
    base_dir: Path

    @property
    def data_dir(self) -> Path:
        p = self.base_dir / "data"
        p.mkdir(parents=True, exist_ok=True)
        return p

    @property
    def db_path(self) -> Path:
        return self.data_dir / "halcon.db"

    @property
    def uploads_dir(self) -> Path:
        p = self.base_dir / "uploads"
        p.mkdir(parents=True, exist_ok=True)
        return p

    @property
    def secret_key(self) -> str:
        # Para evidencia/escuela: clave fija local. En prod sería env var.
        return "dev-secret-change-me"


def get_settings() -> Settings:
    base = Path(__file__).resolve().parents[1]  # Evidencia1/halcon_app
    return Settings(base_dir=base)
