from PIL import Image, ImageDraw

src = "public/decor/tests-mockup.jpg"
im = Image.open(src).convert("RGB")

# Header-piece candidate crops (mockup coords)
script = (1006, 6, 1006 + 132, 6 + 74)          # "Better Training ..." handwriting
photo = (1104, 0, 1104 + 176, 154)               # reception bell photo strip
note = (1143, 56, 1143 + 97, 56 + 84)            # "Small Tests Big Progress" sticky note
tagline = (375, 12, 375 + 293, 12 + 42)          # "Assess Today. Improve Tomorrow."

ov = im.copy()
d = ImageDraw.Draw(ov)
for b, c in [(script, (255, 0, 0)), (photo, (0, 160, 0)), (note, (255, 140, 0)), (tagline, (160, 0, 255))]:
    d.rectangle(b, outline=c, width=2)
ov.crop((360, 0, 1280, 170)).save("/tmp/tests-header-overlay.png")

# Generate the topbar centre tagline PNG (kept on its near-white mockup background)
im.crop(tagline).save("public/decor/tests-topbar-tagline.png")
print("done", im.crop(tagline).size)
