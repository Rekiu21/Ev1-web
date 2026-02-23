from __future__ import annotations

from datetime import datetime
from enum import Enum
from typing import Optional

from sqlmodel import Field, SQLModel


class Role(str, Enum):
    ADMIN = "Admin"
    SALES = "Sales"
    PURCHASING = "Purchasing"
    WAREHOUSE = "Warehouse"
    ROUTE = "Route"


class OrderStatus(str, Enum):
    ORDERED = "Ordered"
    IN_PROCESS = "In process"
    IN_ROUTE = "In route"
    DELIVERED = "Delivered"


class User(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)
    username: str = Field(index=True, unique=True)
    password_hash: str
    role: Role = Field(index=True)
    is_active: bool = Field(default=True, index=True)


class Order(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)

    invoice_number: str = Field(index=True, unique=True)
    customer_name: str
    customer_number: str = Field(index=True)

    fiscal_data: str
    created_at: datetime = Field(default_factory=datetime.utcnow, index=True)
    delivery_address: str
    notes: str = Field(default="")

    status: OrderStatus = Field(default=OrderStatus.ORDERED, index=True)

    deleted: bool = Field(default=False, index=True)

    photo_loaded_path: Optional[str] = Field(default=None)
    photo_delivered_path: Optional[str] = Field(default=None)
