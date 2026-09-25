from PIL import Image, ImageDraw

im = Image.open("public/decor/tests-mockup.jpg").convert("RGB")
d = ImageDraw.Draw(im)
boxes = []
# test list thumbs x=207 w=46 h=47 pitch 66.6
for i in range(7):
    y = 281 + round(i * 66.6)
    boxes.append((207, y, 207 + 46, y + 47))
# reception greeting Q1 mid
boxes.append((768, 548, 768 + 143, 548 + 86))
# question preview image right
boxes.append((953, 232, 953 + 95, 232 + 94))
# suggested images 2x2
for r in range(2):
    for c in range(2):
        x = 1163 + c * 53
        y = 420 + r * 39
        boxes.append((x, y, x + 48, y + 36))
for b in boxes:
    d.rectangle(b, outline=(255, 0, 0), width=2)
im.save("/tmp/tests-overlay.png")
im.crop((935, 0, 1280, 170)).save("/tmp/tests-header.png")
print(len(boxes), "boxes")
