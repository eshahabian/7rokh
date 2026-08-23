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
            # لوگوی هفت‌رخ پس‌زمینه مشکی دارد
            if r < 28 and g < 28 and b < 28:
                pixels[x, y] = (r, g, b, 0)
            elif r > 245 and g > 245 and b > 245:
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


def with_solid_bg(fg: Image.Image, color: tuple[int, int, int] = (0, 0, 0)) -> Image.Image:
    bg = Image.new("RGBA", fg.size, (*color, 255))
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
        with_solid_bg(full).save(d / "ic_launcher.png")

        mask = round_mask(launcher)
        round_opaque = Image.new("RGBA", (launcher, launcher), (0, 0, 0, 0))
        black_circle = Image.new("RGBA", (launcher, launcher), (0, 0, 0, 255))
        black_circle.putalpha(mask)
        round_opaque.alpha_composite(black_circle)
        round_opaque.alpha_composite(full)
        round_opaque.save(d / "ic_launcher_round.png")
        print(folder, "ok")

    store = fit_on_canvas(logo, 1024, 0.72)
    with_solid_bg(store).save(RES / "icon.png")
    print("wrote", RES / "icon.png")

    write_splashes(logo)


SPLASH_SIZES = [
    ("drawable", 480, 800),
    ("drawable-port-mdpi", 320, 480),
    ("drawable-port-hdpi", 480, 800),
    ("drawable-port-xhdpi", 720, 1280),
    ("drawable-port-xxhdpi", 1080, 1920),
    ("drawable-port-xxxhdpi", 1440, 2560),
    ("drawable-land-mdpi", 480, 320),
    ("drawable-land-hdpi", 800, 480),
    ("drawable-land-xhdpi", 1280, 720),
    ("drawable-land-xxhdpi", 1920, 1080),
    ("drawable-land-xxxhdpi", 2560, 1440),
]


def make_splash(logo: Image.Image, width: int, height: int, fill_ratio: float = 0.38) -> Image.Image:
    canvas = Image.new("RGBA", (width, height), (0, 0, 0, 255))
    max_side = int(min(width, height) * fill_ratio)
    lw, lh = logo.size
    scale = min(max_side / lw, max_side / lh)
    nw, nh = max(1, int(lw * scale)), max(1, int(lh * scale))
    resized = logo.resize((nw, nh), Image.Resampling.LANCZOS)
    x = (width - nw) // 2
    y = (height - nh) // 2
    canvas.alpha_composite(resized, (x, y))
    return canvas.convert("RGB")


def write_splashes(logo: Image.Image) -> None:
    for folder, width, height in SPLASH_SIZES:
        d = OUT / folder
        d.mkdir(parents=True, exist_ok=True)
        make_splash(logo, width, height).save(d / "splash.png", optimize=True)
        print(folder, "splash", width, "x", height)

    # Android 12+ center icon: logo in the inner ~2/3 so the system mask does not crop it
    icon = fit_on_canvas(logo, 960, 0.62)
    with_solid_bg(icon).save(OUT / "drawable" / "splash_icon.png", optimize=True)
    print("wrote splash_icon.png")

    # آیکون‌های PWA وب
    web_dir = ROOT.parent / "assets" / "img"
    web_dir.mkdir(parents=True, exist_ok=True)
    logo.save(web_dir / "logo.png")
    for size, name in [(32, "icon-32.png"), (192, "icon-192.png"), (512, "icon-512.png")]:
        web_icon = fit_on_canvas(logo, size, 0.78)
        with_solid_bg(web_icon).save(web_dir / name, optimize=True)
    print("wrote web icons in", web_dir)


if __name__ == "__main__":
    main()

