"""One-off script to generate PWA icons for the KAI Security app.
Run: python3 generate_icons.py
Produces files under frontend/public/icons/.
"""
from PIL import Image, ImageDraw, ImageFont
import os

OUT_DIR = os.path.join(os.path.dirname(__file__), "public", "icons")
os.makedirs(OUT_DIR, exist_ok=True)

NAVY = (31, 36, 84, 255)      # #1f2454
NAVY_2 = (41, 47, 107, 255)   # #292f6b
ORANGE = (232, 117, 0, 255)   # #e87500
WHITE = (255, 255, 255, 255)

FONT_BOLD = "C:/Windows/Fonts/arialbd.ttf"


def draw_shield(draw, cx, cy, size, color):
    w = size
    h = size * 1.15
    top = cy - h / 2
    left = cx - w / 2
    right = cx + w / 2
    bottom = cy + h / 2
    mid_y = top + h * 0.55

    points = [
        (left, top + h * 0.12),
        (cx, top),
        (right, top + h * 0.12),
        (right, mid_y),
        (cx, bottom),
        (left, mid_y),
    ]
    draw.polygon(points, fill=color)


def make_base(size, safe_ratio=1.0):
    """safe_ratio < 1 shrinks the artwork so it survives maskable cropping."""
    img = Image.new("RGBA", (size, size), NAVY)
    draw = ImageDraw.Draw(img)

    # Subtle diagonal gradient feel: soft translucent circles like the login page.
    deco = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    deco_draw = ImageDraw.Draw(deco)
    r1 = size * 0.55
    deco_draw.ellipse(
        [size * 0.55 - r1 / 2, -r1 / 2, size * 0.55 + r1 / 2, r1 / 2],
        fill=(255, 255, 255, 18),
    )
    r2 = size * 0.5
    deco_draw.ellipse(
        [-r2 / 2, size - r2 / 2, r2 / 2, size + r2 / 2],
        fill=(232, 117, 0, 30),
    )
    img.alpha_composite(deco)
    draw = ImageDraw.Draw(img)

    cx, cy = size / 2, size * 0.40 * safe_ratio + size * (1 - safe_ratio) / 2
    shield_size = size * 0.34 * safe_ratio
    draw_shield(draw, size / 2, size * (0.5 - 0.16 * safe_ratio), shield_size, ORANGE)

    # Checkmark inside shield
    check_w = int(size * 0.02 * safe_ratio) + 3
    sx, sy = size / 2, size * (0.5 - 0.16 * safe_ratio)
    s = shield_size
    draw.line(
        [
            (sx - s * 0.22, sy - s * 0.02),
            (sx - s * 0.05, sy + s * 0.18),
            (sx + s * 0.28, sy - s * 0.22),
        ],
        fill=WHITE,
        width=check_w,
        joint="curve",
    )

    # "KAI" wordmark
    font_size = int(size * 0.20 * safe_ratio)
    font = ImageFont.truetype(FONT_BOLD, font_size)
    text = "KAI"
    bbox = draw.textbbox((0, 0), text, font=font)
    tw = bbox[2] - bbox[0]
    th = bbox[3] - bbox[1]
    tx = size / 2 - tw / 2 - bbox[0]
    ty = size * (0.68 * safe_ratio + (1 - safe_ratio) * 0.5) - th / 2 - bbox[1]
    draw.text((tx, ty), text, font=font, fill=WHITE)

    # Orange underline accent
    bar_w = tw * 0.9
    bar_y = ty + th + size * 0.035
    draw.rounded_rectangle(
        [size / 2 - bar_w / 2, bar_y, size / 2 + bar_w / 2, bar_y + size * 0.018],
        radius=size * 0.01,
        fill=ORANGE,
    )

    return img


def save(img, name):
    path = os.path.join(OUT_DIR, name)
    img.save(path, "PNG")
    print("wrote", path)


# Standard "any" purpose icons - full bleed, OS applies its own mask shape.
icon_512 = make_base(512, safe_ratio=1.0)
save(icon_512, "icon-512.png")
save(icon_512.resize((192, 192), Image.LANCZOS), "icon-192.png")

# Maskable icon - keep artwork inside the safe zone (center ~80%).
maskable_512 = make_base(512, safe_ratio=0.78)
save(maskable_512, "maskable-icon-512.png")

# Apple touch icon - iOS rounds it itself, needs an opaque background.
apple_180 = make_base(180, safe_ratio=1.0)
save(apple_180, "apple-touch-icon.png")

print("done")
