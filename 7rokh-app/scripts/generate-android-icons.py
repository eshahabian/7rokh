#!/usr/bin/env python3
"""Generate Android launcher icons from the 7rokh logo."""
from __future__ import annotations

from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "resources" / "icon-source.png"
RES = ROOT / "resources"
OUT = ROOT / "android" / "app" / "src" / "main" / "res"

SIZES = [
    ("mipmap-mdpi", 48, 108),
    ("mipmap-hdpi", 72, 162),
    ("mipmap-xhdpi", 96, 216),
    ("mipmap-xxhdpi", 144, 324),
    ("mipmap-xxxhdpi", 192, 432),
]


def make_transparent(img: Image.Image) -> Image.Image:
    img = img.convert("RGBA")
    pixels = img.load()
    w, h = img.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = pixels[x, y]
            if r > 245 and g > 245 and b > 245:
                pixels[x, y] = (r, g, b, 0)
    bbox = img.getbbox()
    if bbox:
        img = img.crop(bbox)
    return img


def fit_on_canvas(logo: Image.Image, canvas: int, fill_ratio: float = 0.62) -> Image.Image:
    out = Image.new("RGBA", (canvas, canvas), (0, 0, 0, 0))
    max_side = int(canvas * fill_ratio)
    lw, lh = logo.size
    scale = min(max_side / lw, max_side / lh)
    nw, nh = max(1, int(lw * scale)), max(1, int(lh * scale))
    resized = logo.resize((nw, nh), Image.Resampling.LANCZOS)
    x = (canvas - nw) // 2
    y = (canvas - nh) // 2
    out.paste(resized, (x, y), resized)
    return out


def with_white_bg(fg: Image.Image) -> Image.Image:
    bg = Image.new("RGBA", fg.size, (255, 255, 255, 255))
    bg.alpha_composite(fg)
    return bg.convert("RGB")


def round_mask(size: int) -> Image.Image:
    m = Image.new("L", (size, size), 0)
    draw = ImageDraw.Draw(m)
    draw.ellipse((0, 0, size - 1, size - 1), fill=255)
    return m


def main() -> None:
    if not SRC.is_file():
        raise SystemExit(f"Missing source logo: {SRC}")

    RES.mkdir(parents=True, exist_ok=True)
    logo = make_transparent(Image.open(SRC))
    logo.save(RES / "icon-foreground.png")
    print("master", logo.size)

    for folder, launcher, foreground in SIZES:
        d = OUT / folder
        d.mkdir(parents=True, exist_ok=True)

        fg = fit_on_canvas(logo, foreground, 0.62)
        fg.save(d / "ic_launcher_foreground.png")

        full = fit_on_canvas(logo, launcher, 0.72)
        with_white_bg(full).save(d / "ic_launcher.png")

        mask = round_mask(launcher)
        round_opaque = Image.new("RGBA", (launcher, launcher), (0, 0, 0, 0))
        white_circle = Image.new("RGBA", (launcher, launcher), (255, 255, 255, 255))
        white_circle.putalpha(mask)
        round_opaque.alpha_composite(white_circle)
        round_opaque.alpha_composite(full)
        round_opaque.save(d / "ic_launcher_round.png")
        print(folder, "ok")

    store = fit_on_canvas(logo, 1024, 0.72)
    with_white_bg(store).save(RES / "icon.png")
    print("wrote", RES / "icon.png")


if __name__ == "__main__":
    main()
