#!/usr/bin/env python3
"""Generate WordPress.org plugin assets for SiteMap Redirects.

Outputs:
  .wordpress-org/banner-1544x500.png
  .wordpress-org/banner-772x250.png
  .wordpress-org/icon-256x256.png
  .wordpress-org/icon-128x128.png
  .wordpress-org/screenshot-1.png
  .wordpress-org/screenshot-2.png
  .wordpress-org/screenshot-3.png
  .wordpress-org/screenshot-4.png

Requires Pillow.
"""

import os
from PIL import Image, ImageDraw, ImageFont


def get_font(size):
    """Return a TTF font, falling back to bitmap/PIL default."""
    candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    ]
    for path in candidates:
        if os.path.isfile(path):
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


OUT_DIR = os.path.join(
    os.getcwd(),
    'wordpress-sandbox',
    'wp-content',
    'plugins',
    'site-map-redirects',
    '.wordpress-org'
)

colors = {
    'bg_dark': (30, 30, 45),
    'bg_light': (242, 244, 247),
    'accent': (34, 113, 177),       # WordPress brand-ish blue.
    'accent_light': (100, 160, 220),
    'white': (255, 255, 255),
    'text_dark': (40, 40, 40),
    'text_gray': (100, 100, 100),
    'node': (80, 140, 200),
    'node_leaf': (46, 162, 204),
    'red': (214, 54, 56),
    'amber': (220, 170, 0),
    'green': (70, 180, 80),
}


def save(img, name):
    os.makedirs(OUT_DIR, exist_ok=True)
    path = os.path.join(OUT_DIR, name)
    img.save(path, 'PNG')
    print(f'Saved {path}')


def banner(width, height):
    img = Image.new('RGB', (width, height), colors['bg_dark'])
    d = ImageDraw.Draw(img)

    # Safe text box so lines/nodes do not obscure the title.
    text_left = int(width * 0.065)
    text_top = int(height * 0.28)
    text_bottom = int(height * 0.72)
    text_right = int(width * 0.62)

    # Draw a very subtle tree/network pattern, avoiding the text region.
    node_count = 22
    nodes = []
    attempts = 0
    while len(nodes) < node_count and attempts < node_count * 20:
        nx = int(width * (0.05 + 0.95 * ((len(nodes) * 1.309) % 1)))
        ny = int(height * (0.10 + 0.80 * ((len(nodes) * 1.753) % 1)))
        # Keep nodes out of the safe text box.
        if not (text_left <= nx <= text_right and text_top <= ny <= text_bottom):
            nodes.append((nx, ny))
        attempts += 1

    line_color = (55, 85, 125)
    for i, (x1, y1) in enumerate(nodes):
        for j in range(i + 1, min(i + 4, len(nodes))):
            x2, y2 = nodes[j]
            d.line([(x1, y1), (x2, y2)], fill=line_color, width=max(1, height // 300))
    for x, y in nodes:
        r = max(2, height // 100)
        d.ellipse([(x - r, y - r), (x + r, y + r)], fill=line_color)

    # Darken the safe text box slightly so the text pops.
    d.rectangle([(text_left - 20, 0), (text_right, height)], fill=colors['bg_dark'])

    # Title and tagline.
    title_size = height // 7
    tagline_size = height // 19
    title_font = get_font(title_size)
    tagline_font = get_font(tagline_size)

    title = 'SiteMap Redirects'
    tagline = 'Map every URL. See every redirect. In plain English.'

    d.text((width * 0.08, height * 0.32), title, font=title_font, fill=colors['white'])
    d.text((width * 0.08, height * 0.54), tagline, font=tagline_font, fill=colors['accent_light'])
    return img


def icon(size):
    img = Image.new('RGB', (size, size), colors['accent'])
    d = ImageDraw.Draw(img)

    # Draw a simple tree/redirect icon: root -> two children with arrows.
    margin = size // 6
    root_x = size // 2
    root_y = margin
    left_x = margin
    left_y = size - margin
    right_x = size - margin
    right_y = size - margin

    line_width = max(2, size // 32)
    d.line([(root_x, root_y), (left_x, left_y)], fill=colors['white'], width=line_width)
    d.line([(root_x, root_y), (right_x, right_y)], fill=colors['white'], width=line_width)
    d.line([(root_x, root_y), (root_x, size // 2)], fill=colors['white'], width=line_width)
    d.line([(root_x, size // 2), (left_x, size // 2)], fill=colors['white'], width=line_width)
    d.line([(root_x, size // 2), (right_x, size // 2)], fill=colors['white'], width=line_width)

    r = max(size // 16, 4)
    for x, y in [(root_x, root_y), (root_x, size // 2), (left_x, left_y), (right_x, right_y)]:
        d.ellipse([(x - r, y - r), (x + r, y + r)], fill=colors['white'])

    # Tiny arrow near left leaf.
    ax, ay = left_x, left_y - size // 8
    d.polygon([(ax, ay), (ax + size // 12, ay + size // 24), (ax, ay + size // 12)], fill=colors['amber'])

    return img


def screenshot(title, subtitle, draw_fn=None, width=1200, height=900):
    """Create a fake WordPress admin screenshot."""
    img = Image.new('RGB', (width, height), colors['bg_light'])
    d = ImageDraw.Draw(img)

    # WP admin top bar.
    top_bar_h = 56
    d.rectangle([(0, 0), (width, top_bar_h)], fill=(30, 30, 45))
    d.text((width - 260, 14), 'Howdy, admin', font=get_font(18), fill=colors['white'])

    # Left admin menu.
    menu_w = 220
    d.rectangle([(0, top_bar_h), (menu_w, height)], fill=(40, 45, 60))
    menu_items = ['Dashboard', 'Posts', 'Media', 'Pages', 'Comments', 'Appearance', 'Plugins', 'Users', 'Tools', 'Settings', 'SiteMap Redirects']
    y = top_bar_h + 30
    smr_idx = menu_items.index('SiteMap Redirects')
    for idx, item in enumerate(menu_items):
        if idx == smr_idx:
            d.rectangle([(10, y - 4), (menu_w - 10, y + 24)], fill=(55, 65, 90))
        d.text((20, y), item, font=get_font(16), fill=colors['white'])
        y += 38

    # Content area title.
    d.text((menu_w + 40, top_bar_h + 40), title, font=get_font(34), fill=colors['text_dark'])
    d.text((menu_w + 40, top_bar_h + 90), subtitle, font=get_font(18), fill=colors['text_gray'])

    # Toolbar.
    toolbar_y = top_bar_h + 140
    d.rectangle([(menu_w + 30, toolbar_y), (width - 30, toolbar_y + 50)], fill=colors['white'], outline=(200, 200, 200))
    d.rectangle([(menu_w + 45, toolbar_y + 10), (menu_w + 160, toolbar_y + 40)], fill=colors['accent'])
    d.text((menu_w + 55, toolbar_y + 15), 'Re-index', font=get_font(16), fill=colors['white'])
    d.rectangle([(menu_w + 175, toolbar_y + 10), (menu_w + 275, toolbar_y + 40)], fill=(240, 240, 240))
    d.text((menu_w + 190, toolbar_y + 15), 'Debug', font=get_font(16), fill=colors['text_dark'])
    d.rectangle([(menu_w + 290, toolbar_y + 10), (menu_w + 400, toolbar_y + 40)], fill=(240, 240, 240))
    d.text((menu_w + 305, toolbar_y + 15), 'Export', font=get_font(16), fill=colors['text_dark'])

    # Main content card.
    card_top = toolbar_y + 70
    d.rectangle([(menu_w + 30, card_top), (width - 30, height - 30)], fill=colors['white'], outline=(200, 200, 200))

    if draw_fn:
        draw_fn(d, menu_w + 30, card_top, width - menu_w - 60, height - card_top - 30)

    return img


def draw_tree(d, x, y, w, h):
    """Draw a small D3-ish tree into the content card."""
    cx = x + w // 2
    cy = y + h // 4
    d.ellipse([(cx - 12, cy - 12), (cx + 12, cy + 12)], fill=colors['accent'])
    d.text((cx + 18, cy - 8), 'Home', font=get_font(16), fill=colors['text_dark'])

    # Children.
    children = [
        (x + w * 0.2, y + h * 0.55, 'About', False),
        (x + w * 0.5, y + h * 0.65, 'Blog', False),
        (x + w * 0.8, y + h * 0.55, 'Contact', True),
    ]
    for tx, ty, label, redirect in children:
        d.line([(cx, cy + 12), (tx, ty - 12)], fill=colors['node'], width=2)
        fill = colors['red'] if redirect else colors['node_leaf']
        d.ellipse([(tx - 10, ty - 10), (tx + 10, ty + 10)], fill=fill)
        d.text((tx + 15, ty - 8), label + ('  → /new' if redirect else ''), font=get_font(14), fill=colors['text_dark'])

    # Legend.
    lx, ly = x + w * 0.65, y + h * 0.82
    d.rectangle([(lx, ly), (lx + 160, ly + 80)], fill=(248, 249, 250), outline=(200, 200, 200))
    d.text((lx + 10, ly + 10), 'Legend', font=get_font(16), fill=colors['text_dark'])
    d.ellipse([(lx + 15, ly + 38), (lx + 25, ly + 48)], fill=colors['red'])
    d.text((lx + 32, ly + 36), '301 Permanent', font=get_font(12), fill=colors['text_dark'])
    d.ellipse([(lx + 15, ly + 56), (lx + 25, ly + 66)], fill=colors['amber'])
    d.text((lx + 32, ly + 54), '302 Temporary', font=get_font(12), fill=colors['text_dark'])


def draw_zoom(d, x, y, w, h):
    cx = x + w // 3
    cy = y + h // 3
    d.ellipse([(cx - 18, cy - 18), (cx + 18, cy + 18)], fill=colors['accent'])
    d.text((cx + 28, cy - 10), 'Homepage', font=get_font(18), fill=colors['text_dark'])

    target = [(cx + 220, cy + 60)]
    for tx, ty in target:
        d.line([(cx + 12, cy + 6), (tx - 14, ty - 6)], fill=colors['node'], width=3)
        d.ellipse([(tx - 14, ty - 14), (tx + 14, ty + 14)], fill=colors['red'])
        d.text((tx + 24, ty - 10), '/contact  →  /contact-new', font=get_font(18), fill=colors['text_dark'])

    # Detail panel mock.
    panel_x = x + w * 0.55
    d.rectangle([(panel_x, y + 40), (x + w - 20, y + h - 50)], fill=(248, 249, 250), outline=(200, 200, 200))
    d.text((panel_x + 20, y + 70), 'Node details', font=get_font(20), fill=colors['text_dark'])
    d.text((panel_x + 20, y + 110), 'URL: example.com/contact', font=get_font(15), fill=colors['text_gray'])
    d.text((panel_x + 20, y + 140), 'Type: Page', font=get_font(15), fill=colors['text_gray'])
    d.text((panel_x + 20, y + 180), 'Redirects', font=get_font(17), fill=colors['text_dark'])
    d.rectangle([(panel_x + 20, y + 210), (panel_x + 300, y + 240)], fill=colors['red'])
    d.text((panel_x + 30, y + 215), '301 → /contact-new', font=get_font(14), fill=colors['white'])
    d.text((panel_x + 20, y + 260), 'Why: Redirection plugin rule', font=get_font(14), fill=colors['text_gray'])


def draw_debug(d, x, y, w, h):
    """Draw a sortable table."""
    headers = ['Source URL', 'Target URL', 'Code', 'Priority', 'Source']
    col_w = w // 5
    row_h = 42
    start_y = y + 50
    # Header.
    d.rectangle([(x + 20, start_y), (x + w - 20, start_y + row_h)], fill=(230, 235, 240))
    for i, h_text in enumerate(headers):
        d.text((x + 30 + i * col_w, start_y + 10), h_text, font=get_font(16), fill=colors['text_dark'])
    # Rows.
    data = [
        ('/old-campaign', '/new-campaign', '301', '1', '.htaccess'),
        ('/contact', '/contact-new', '301', '2', 'Redirection'),
        ('/blog/2024/*', '/blog/archive', '302', '2', 'Redirection'),
        ('* trailing slash', '(canonical)', '301', '3', 'WP core'),
    ]
    for idx, row in enumerate(data):
        ry = start_y + (idx + 1) * row_h
        d.rectangle([(x + 20, ry), (x + w - 20, ry + row_h)], fill=colors['white'] if idx % 2 == 0 else (248, 248, 248), outline=(220, 220, 220))
        for i, cell in enumerate(row):
            color = colors['red'] if i == 2 and cell == '301' else (colors['text_dark'] if i != 2 else colors['amber'])
            d.text((x + 30 + i * col_w, ry + 8), cell, font=get_font(15), fill=color)


def draw_management(d, x, y, w, h):
    """Draw drag-and-drop toolbar mock."""
    d.text((x + 40, y + 40), 'Drag a node onto another node to create a redirect.', font=get_font(18), fill=colors['text_gray'])

    nodes = [
        (x + w * 0.2, y + h * 0.4, '/old-page', colors['red']),
        (x + w * 0.7, y + h * 0.4, '/new-page', colors['green']),
    ]
    for nx, ny, label, c in nodes:
        d.ellipse([(nx - 16, ny - 16), (nx + 16, ny + 16)], fill=c)
        d.text((nx + 24, ny - 10), label, font=get_font(18), fill=colors['text_dark'])

    d.line([(nodes[0][0] + 16, nodes[0][1]), (nodes[1][0] - 16, nodes[1][1])], fill=colors['accent'], width=3)
    # Arrowhead.
    ax = nodes[1][0] - 30
    ay = nodes[1][1]
    d.polygon([(ax, ay - 8), (ax + 16, ay), (ax, ay + 8)], fill=colors['accent'])

    # Form card.
    d.rectangle([(x + w * 0.2, y + h * 0.6), (x + w * 0.8, y + h * 0.88)], fill=(248, 249, 250), outline=(200, 200, 200))
    d.text((x + w * 0.25, y + h * 0.65), 'New redirect', font=get_font(20), fill=colors['text_dark'])
    d.text((x + w * 0.25, y + h * 0.72), 'Source: /old-page', font=get_font(16), fill=colors['text_gray'])
    d.text((x + w * 0.25, y + h * 0.78), 'Target: /new-page', font=get_font(16), fill=colors['text_gray'])
    d.text((x + w * 0.25, y + h * 0.84), 'Status: 301 Permanent', font=get_font(16), fill=colors['text_gray'])


def main():
    # Banners.
    save(banner(1544, 500), 'banner-1544x500.png')
    save(banner(772, 250), 'banner-772x250.png')

    # Icons.
    save(icon(256), 'icon-256x256.png')
    save(icon(128), 'icon-128x128.png')

    # Screenshots.
    save(screenshot('SiteMap Redirects', 'Interactive tree map of every URL on your site.', draw_tree), 'screenshot-1.png')
    save(screenshot('Tree Detail View', 'Click any node to inspect redirects and destinations.', draw_zoom), 'screenshot-2.png')
    save(screenshot('Debug Mode', 'Sortable priority table of all active redirects.', draw_debug), 'screenshot-3.png')
    save(screenshot('Redirect Management', 'Drag-and-drop creation with plain-English summary.', draw_management), 'screenshot-4.png')


if __name__ == '__main__':
    main()
