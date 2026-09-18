from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

images_dir = Path('public/images')
images_dir.mkdir(parents=True, exist_ok=True)

logo_path = images_dir / 'logo-mark.png'
logo_size = (120, 120)
logo = Image.new('RGBA', logo_size, (0, 0, 0, 0))
draw = ImageDraw.Draw(logo)
for radius, color in [(60, (239, 68, 68)), (48, (185, 28, 28))]:
    draw.ellipse([logo_size[0]//2-radius, logo_size[1]//2-radius, logo_size[0]//2+radius, logo_size[1]//2+radius], fill=color)
try:
    font = ImageFont.truetype('arial.ttf', 64)
except Exception:
    font = ImageFont.load_default()
text = 'G'
try:
    bbox = draw.textbbox((0, 0), text, font=font)
    w, h = bbox[2] - bbox[0], bbox[3] - bbox[1]
except AttributeError:
    try:
        bbox = font.getbbox(text)
        w, h = bbox[2] - bbox[0], bbox[3] - bbox[1]
    except AttributeError:
        w, h = 48, 48
draw.text(((logo_size[0]-w)/2, (logo_size[1]-h)/2-4), text, font=font, fill='white')
logo.save(logo_path)

preview_path = images_dir / 'dashboard-preview.png'
size = (916, 588)
img = Image.new('RGB', size, (248, 250, 252))
draw = ImageDraw.Draw(img)
for y in range(size[1]):
    ratio = y / size[1]
    r = int(255 * (1 - ratio) + 238 * ratio)
    g = int(255 * (1 - ratio) + 247 * ratio)
    b = int(255 * (1 - ratio) + 255 * ratio)
    draw.line([(0, y), (size[0], y)], fill=(r, g, b))

panel_color = (255, 255, 255)
for x, y, w, h in [(24, 24, 868, 148), (24, 198, 868, 126), (24, 356, 868, 198)]:
    draw.rectangle([x, y, x + w, y + h], fill=panel_color, outline=(227, 231, 235), width=1)

try:
    title_font = ImageFont.truetype('arial.ttf', 42)
    label_font = ImageFont.truetype('arial.ttf', 20)
except Exception:
    title_font = ImageFont.load_default()
    label_font = ImageFont.load_default()

# Title text
draw.text((48, 34), 'GymFlow SaaS', fill=(15, 23, 42), font=title_font)

# small cards
for i, label in enumerate(['نشاط اليوم', 'المشتركون', 'الإيرادات']):
    bx = 54 + i * 286
    by = 90
    draw.rounded_rectangle([bx, by, bx + 220, by + 72], radius=18, fill=(247, 250, 252), outline=(226, 232, 240))
    draw.text((bx + 16, by + 16), label, fill=(15, 23, 42), font=label_font)
    draw.text((bx + 16, by + 40), '✅', fill=(239, 68, 68), font=label_font)

# chart bars
for i in range(8):
    x = 54 + i * 102
    height = 60 + (i % 4) * 40
    draw.rectangle([x, 422 - height, x + 60, 422], fill=(239, 68, 68))

# info labels
for i, label in enumerate(['زيارة العملاء', 'المبيعات', 'المخزون']):
    draw.text((54 + i * 286, 552), label, fill=(100, 116, 139), font=label_font)

img.save(preview_path)
print('Generated:', logo_path, preview_path)
