#!/usr/bin/env python3
"""Compose a mockup and a screenshot for side-by-side review (spec 0003, Part I).

    python3 storage/harness/compare.py <mockup.jpg> <shot.png> <out.png> [--overlay] [--crop x1,y1,x2,y2]

Writes a PNG with the mockup on the left, the screenshot on the right (both
scaled to the same width), optionally a 50 % blend and a difference heat map
underneath, and prints a few numeric hints: per-region mean absolute
difference on a 8x6 grid, so you can see which area of the page drifts most.
"""
import sys
from PIL import Image, ImageChops, ImageDraw, ImageFont, ImageOps

args = [a for a in sys.argv[1:] if not a.startswith('--')]
flags = [a for a in sys.argv[1:] if a.startswith('--')]
if len(args) < 3:
    print(__doc__)
    sys.exit(1)

mock_path, shot_path, out_path = args[:3]
crop = None
for f in flags:
    if f.startswith('--crop='):
        crop = tuple(int(v) for v in f.split('=')[1].split(','))
overlay = '--overlay' in flags

mock = Image.open(mock_path).convert('RGB')
shot = Image.open(shot_path).convert('RGB')
if crop:
    mock = mock.crop(crop)
    shot = shot.crop(crop)

W = 1280
def fit(img):
    if img.width != W:
        img = img.resize((W, round(img.height * W / img.width)), Image.LANCZOS)
    return img

mock, shot = fit(mock), fit(shot)
H = max(mock.height, shot.height)
pad = 24
rows = 2 if overlay else 1
canvas = Image.new('RGB', (W * 2 + pad * 3, H * rows + pad * (rows + 1) + 30), 'white')
draw = ImageDraw.Draw(canvas)
try:
    font = ImageFont.truetype('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', 18)
except Exception:
    font = ImageFont.load_default()
draw.text((pad, 4), f'MOCKUP {mock_path}', fill='black', font=font)
draw.text((W + pad * 2, 4), f'SCREENSHOT {shot_path}', fill='black', font=font)
canvas.paste(mock, (pad, 30 + pad))
canvas.paste(shot, (W + pad * 2, 30 + pad))

# numeric hints on a grid
a = mock.resize((W, H)) if mock.size != (W, H) else mock
b = shot.resize((W, H)) if shot.size != (W, H) else shot
diff = ImageChops.difference(a, b).convert('L')
cols, rws = 8, 6
cw, ch = W // cols, H // rws
print('mean abs diff per region (0-255), rows top→bottom, cols left→right:')
for r in range(rws):
    line = []
    for c in range(cols):
        region = diff.crop((c * cw, r * ch, (c + 1) * cw, (r + 1) * ch))
        hist = region.histogram()
        total = sum(hist)
        mean = sum(i * n for i, n in enumerate(hist)) / total if total else 0
        line.append(f'{mean:5.1f}')
    print('  ' + ' '.join(line))

if overlay:
    blend = Image.blend(a, b, 0.5)
    heat = ImageOps.autocontrast(diff)
    heat = ImageOps.colorize(heat, black='white', white='red')
    canvas.paste(blend, (pad, 30 + pad * 2 + H))
    canvas.paste(heat, (W + pad * 2, 30 + pad * 2 + H))
    draw.text((pad, 30 + pad * 2 + H - 22), '50% BLEND', fill='black', font=font)
    draw.text((W + pad * 2, 30 + pad * 2 + H - 22), 'DIFFERENCE HEAT MAP', fill='black', font=font)

canvas.save(out_path)
print('wrote', out_path, canvas.size)
