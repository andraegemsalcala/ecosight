#!/usr/bin/env python3
"""
EcoSight logo asset generator (Pillow port).

Renders the full set of logo PNG assets from public/images/logo-original.png
(the 2000x2000 source lockup). Requires Pillow:

    pip install pillow

It mirrors scripts/make-logo-assets.php (the PHP/GD build used on this machine,
where Python/Pillow are not installed). Both produce the same outputs.

What it does, per spec:
  - Color-to-alpha against the white background: alpha = distance from white;
    the original RGB is kept (no un-blending) so pale fills (e.g. the deer's
    light-green tint) stay translucent. Pixels within a few levels of white
    become fully transparent.
  - Trims each asset to its exact content bounding box, then adds ~4% padding.
  - Splits the lockup into icon and wordmark at the empty horizontal band
    detected from the data.
  - Produces: logo-stacked, logo-mark, logo-wordmark, logo-horizontal, light
    variants (stacked/mark/horizontal) with dark ring + charcoal wordmark
    recolored to white and leaf green kept, logo.png alias of stacked, and
    favicon.png (32), favicon-192.png (192), apple-touch-icon.png (180 on an
    opaque white background), favicon.ico (16/32/48).
  - No artwork is cropped: every asset is trimmed to its own bbox + padding.
"""

from __future__ import annotations

import io
import os
import struct

from PIL import Image

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(BASE, "public", "images", "logo-original.png")
OUT_DIR = os.path.join(BASE, "public", "images")
PUBLIC = os.path.join(BASE, "public")

BG_TOL = 8          # near-white threshold (levels)
ALPHA_CUT = 0.35    # meaningful-content alpha threshold for trim/band detection
PAD = 0.04          # 4% padding on each side
DARK_CUT = 110      # max channel value to classify a pixel as dark ink (recolor target)


def key_alpha(rgb: tuple) -> tuple:
    """Return (r, g, b, alpha01) with alpha derived from distance to white."""
    r, g, b = rgb
    d = max(255 - r, 255 - g, 255 - b)
    if d <= BG_TOL:
        return (0, 0, 0, 0.0)
    return (r, g, b, d / 255)


def build_keyed(im: Image.Image, recolor: bool) -> Image.Image:
    im = im.convert("RGBA")
    px = im.load()
    w, h = im.size
    for y in range(h):
        for x in range(w):
            (r, g, b, a) = key_alpha(px[x, y])
            if a <= 0.0:
                px[x, y] = (0, 0, 0, 0)
            else:
                if recolor and max(r, g, b) < DARK_CUT:
                    r = g = b = 255  # dark ring/deer/wordmark ink -> white
                px[x, y] = (r, g, b, round(a * 255))
    return im


def content_bbox(im: Image.Image, region=None) -> tuple:
    """Content bounding box inside (y0,y1,x0,x1) region.

    Returns (minX, minY, maxX, maxY) or None if empty."""
    y0, y1, x0, x1 = region if region else (0, im.height, 0, im.width)
    alpha = im.getchannel("A")
    minX = minY = 10**9
    maxX = maxY = -1
    for y in range(y0, y1):
        for x in range(x0, x1):
            if alpha.getpixel((x, y)) / 255 > ALPHA_CUT:
                minX, maxX = min(minX, x), max(maxX, x)
                minY, maxY = min(minY, y), max(maxY, y)
    return None if maxX < 0 else (minX, minY, maxX, maxY)


def crop_region(label: str, im: Image.Image, region: tuple, width: int) -> Image.Image:
    bbox = content_bbox(im, region)
    if bbox is None:
        raise RuntimeError(f"empty region {label}")
    minX, minY, maxX, maxY = bbox
    pw = round((maxX - minX + 1) * PAD)
    ph = round((maxY - minY + 1) * PAD)
    bx = max(0, minX - pw)
    by = max(0, minY - ph)
    bw = (maxX - minX + 1) + 2 * pw
    bh = (maxY - minY + 1) + 2 * ph
    bw = min(bw, im.width - bx)
    bh = min(bh, im.height - by)
    crop = im.crop((bx, by, bx + bw, by + bh))
    if crop.width > width:
        ratio = width / crop.width
        crop = crop.resize((width, round(crop.height * ratio)), Image.LANCZOS)
    print(f"  crop[{label}]= {crop.width}x{crop.height}")
    return crop


def save_png(im: Image.Image, path: str, max_bytes: int = 100_000) -> None:
    while True:
        buf = io.BytesIO()
        im.save(buf, "PNG", optimize=True)
        if buf.tell() <= max_bytes or im.width < 120:
            break
        im = im.resize((round(im.width * 0.9), round(im.height * 0.9)), Image.LANCZOS)
    print(f"  wrote {path} ({buf.tell()} bytes)")
    with open(path, "wb") as f:
        f.write(buf.getvalue())


def estimate_x_height(im: Image.Image, y0: int, y1: int) -> float:
    alpha = im.getchannel("A")
    rows = []
    for y in range(y0, y1):
        rows.append(sum(1 for x in range(im.width) if alpha.getpixel((x, y)) / 255 > ALPHA_CUT))
    if max(rows) == 0:
        return (y1 - y0) * 0.5
    cap_top = next((y for y, c in zip(range(y0, y1), rows) if c >= 0.6 * max(rows)), None)
    baseline = max((y for y, c in zip(range(y0, y1), rows) if c >= 0.3 * max(rows)), default=None)
    if cap_top is None or baseline is None:
        return (y1 - y0) * 0.5
    return max(1.0, 0.70 * (baseline - cap_top))


def compose_horizontal(mark: Image.Image, word: Image.Image, x_height: float) -> Image.Image:
    gap = max(1, round(x_height * 0.5))
    h = max(mark.height, word.height)
    w = mark.width + gap + word.width
    out = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    out.alpha_composite(mark, (0, (h - mark.height) // 2))
    out.alpha_composite(word, (mark.width + gap, (h - word.height) // 2))
    return out


def resize_to(im: Image.Image, size: int) -> Image.Image:
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    s = size / max(im.size)
    nw, nh = round(im.width * s), round(im.height * s)
    out.alpha_composite(im.resize((nw, nh), Image.LANCZOS), ((size - nw) // 2, (size - nh) // 2))
    return out


def opaque_white(im: Image.Image) -> Image.Image:
    out = Image.new("RGBA", im.size, (255, 255, 255, 255))
    out.alpha_composite(im)
    return out


def write_ico(sizes, out_path: str, mark: Image.Image) -> None:
    blobs = []
    entries = []
    offset = 6 + 16 * len(sizes)
    header = struct.pack("<HHH", 0, 1, len(sizes))
    body = b""
    for s in sizes:
        buf = io.BytesIO()
        resize_to(mark, s).save(buf, "PNG", optimize=True)
        blob = buf.getvalue()
        header += struct.pack(
            "<BBBBHHII",
            s if s < 256 else 0, s if s < 256 else 0, 0, 0, 1, 32, len(blob), offset,
        )
        offset += len(blob)
        body += blob
    with open(out_path, "wb") as f:
        f.write(header + body)
    print(f"  wrote {out_path} ({offset} bytes)")


def main() -> None:
    src = Image.open(SRC)
    print(f"Source: {src.width}x{src.height}")

    keyed = build_keyed(src, recolor=False)
    light = build_keyed(src, recolor=True)

    # Detect empty vertical bands to split icon vs wordmark.
    rows = [
        sum(1 for x in range(src.width)
            if key_alpha(src.getpixel((x, y)))[3] > ALPHA_CUT)
        for y in range(src.height)
    ]
    bands, inside = [], False
    for y, c in enumerate(rows):
        if c == 0 and not inside:
            bands.append([y, y]); inside = True
        elif c == 0:
            bands[-1][1] = y
        else:
            inside = False
    topY = min(y for y, c in enumerate(rows) if c > 0)
    bottomY = max(y for y, c in enumerate(rows) if c > 0)
    inner = [b for b in bands if b[0] > topY and b[1] < bottomY]
    if not inner:
        raise RuntimeError("could not find icon/wordmark band")
    band = inner[0]
    icon_bottom = band[0] - 1
    word_top = band[1] + 1
    print(f"Split: icon y {topY}..{icon_bottom}, wordmark y {word_top}..{bottomY}")

    icon_range = [topY, icon_bottom + 1, 0, src.width]
    word_range = [word_top, bottomY + 1, 0, src.width]
    icon_bbox = content_bbox(keyed, icon_range)
    word_bbox = content_bbox(keyed, word_range)
    icon_x0, word_x0 = icon_bbox[0] - 50, word_bbox[0] - 50
    icon_x1, word_x1 = icon_bbox[2] + 50, word_bbox[2] + 50

    x_height = estimate_x_height(keyed, word_top, bottomY)
    print(f"wordmark x-height estimate: {x_height:.1f} px")

    stacked = crop_region("stacked", keyed, [topY, bottomY + 1, 0, src.width], 1000)
    stacked_light = crop_region("stacked-light", light, [topY, bottomY + 1, 0, src.width], 1000)
    mark = crop_region("mark", keyed, [topY, icon_bottom + 1, icon_x0, icon_x1], 512)
    mark_light = crop_region("mark-light", light, [topY, icon_bottom + 1, icon_x0, icon_x1], 512)
    word = crop_region("wordmark", keyed, [word_top, bottomY + 1, word_x0, word_x1], 900)
    word_light = crop_region("wordlight", light, [word_top, bottomY + 1, word_x0, word_x1], 900)

    scale = 512 / max(mark.size)
    horizontal = compose_horizontal(mark, word, x_height * scale)
    if horizontal.width > 1100:
        horizontal = horizontal.resize((1100, round(horizontal.height * 1100 / horizontal.width)), Image.LANCZOS)
    horizontal_light = compose_horizontal(mark_light, word_light, x_height * scale)
    if horizontal_light.width > 1100:
        horizontal_light = horizontal_light.resize(
            (1100, round(horizontal_light.height * 1100 / horizontal_light.width)), Image.LANCZOS)

    jobs = [
        (stacked, "logo-stacked.png", 120_000),
        (stacked_light, "logo-stacked-light.png", 100_000),
        (mark, "logo-mark.png", 100_000),
        (mark_light, "logo-mark-light.png", 100_000),
        (word, "logo-wordmark.png", 100_000),
        (horizontal, "logo-horizontal.png", 100_000),
        (horizontal_light, "logo-horizontal-light.png", 100_000),
    ]
    for im, name, cap in jobs:
        save_png(im, os.path.join(OUT_DIR, name), cap)
    os.replace(os.path.join(OUT_DIR, "logo-stacked.png"), os.path.join(OUT_DIR, "logo.png"))

    save_png(resize_to(mark, 32), os.path.join(PUBLIC, "favicon.png"), 50_000)
    save_png(resize_to(mark, 192), os.path.join(PUBLIC, "favicon-192.png"), 100_000)
    save_png(opaque_white(resize_to(mark, 180)), os.path.join(PUBLIC, "apple-touch-icon.png"), 100_000)
    write_ico([16, 32, 48], os.path.join(PUBLIC, "favicon.ico"), mark)

    print("Done.")


if __name__ == "__main__":
    main()