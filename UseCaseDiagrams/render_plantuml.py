from __future__ import annotations

import argparse
import os
import pathlib
import urllib.request
import zlib

PLANTUML_ALPHABET = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-_"


def _encode_6bit(value: int) -> str:
    if 0 <= value < 64:
        return PLANTUML_ALPHABET[value]
    raise ValueError("value out of range")


def _append_3bytes(b1: int, b2: int, b3: int) -> str:
    c1 = b1 >> 2
    c2 = ((b1 & 0x3) << 4) | (b2 >> 4)
    c3 = ((b2 & 0xF) << 2) | (b3 >> 6)
    c4 = b3 & 0x3F
    return (
        _encode_6bit(c1)
        + _encode_6bit(c2)
        + _encode_6bit(c3)
        + _encode_6bit(c4)
    )


def plantuml_deflate_and_encode(text: str) -> str:
    data = text.encode("utf-8")
    # PlantUML server expects raw DEFLATE (no zlib header)
    compressed = zlib.compress(data, level=9)[2:-4]

    encoded: list[str] = []
    i = 0
    while i < len(compressed):
        b1 = compressed[i]
        b2 = compressed[i + 1] if i + 1 < len(compressed) else 0
        b3 = compressed[i + 2] if i + 2 < len(compressed) else 0
        encoded.append(_append_3bytes(b1, b2, b3))
        i += 3

    return "".join(encoded)


def download(url: str, out_path: pathlib.Path) -> None:
    out_path.parent.mkdir(parents=True, exist_ok=True)
    req = urllib.request.Request(url, headers={"User-Agent": "render_plantuml.py"})
    with urllib.request.urlopen(req) as resp:
        content = resp.read()
    out_path.write_bytes(content)


def main() -> int:
    parser = argparse.ArgumentParser(description="Render PlantUML .puml files via PlantUML server")
    parser.add_argument(
        "--server",
        default=os.environ.get("PLANTUML_SERVER", "https://www.plantuml.com/plantuml"),
        help="Base URL of PlantUML server (default: https://www.plantuml.com/plantuml)",
    )
    parser.add_argument(
        "--in-dir",
        default=str(pathlib.Path(__file__).resolve().parent),
        help="Directory containing .puml files",
    )
    parser.add_argument(
        "--out-dir",
        default=str(pathlib.Path(__file__).resolve().parent / "out"),
        help="Output directory",
    )
    args = parser.parse_args()

    server = args.server.rstrip("/")
    in_dir = pathlib.Path(args.in_dir).resolve()
    out_dir = pathlib.Path(args.out_dir).resolve()

    puml_files = sorted(in_dir.glob("*.puml"))
    if not puml_files:
        print(f"No .puml files found in {in_dir}")
        return 1

    for puml in puml_files:
        text = puml.read_text(encoding="utf-8")
        encoded = plantuml_deflate_and_encode(text)

        svg_url = f"{server}/svg/{encoded}"
        png_url = f"{server}/png/{encoded}"

        svg_out = out_dir / (puml.stem + ".svg")
        png_out = out_dir / (puml.stem + ".png")

        print(f"Rendering {puml.name} -> {svg_out.name}, {png_out.name}")
        download(svg_url, svg_out)
        download(png_url, png_out)

    print(f"Done. Output in: {out_dir}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
