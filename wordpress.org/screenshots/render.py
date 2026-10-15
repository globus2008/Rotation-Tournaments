"""Renders an SVG to PNG at the given sizes with Chromium: python render.py file.svg out_prefix W[xH] ..."""
import base64
import sys

from playwright.sync_api import sync_playwright


def render(svg_path, out, w, h):
    data = base64.b64encode(open(svg_path, 'rb').read()).decode()
    with sync_playwright() as p:
        b = p.chromium.launch()
        pg = b.new_page(viewport={'width': w, 'height': h})
        pg.set_content(f'<body style="margin:0"><img src="data:image/svg+xml;base64,{data}" width="{w}" height="{h}" style="display:block"></body>')
        pg.wait_for_timeout(500)
        pg.screenshot(path=out, omit_background=True)
        b.close()


if __name__ == '__main__':
    svg, prefix = sys.argv[1], sys.argv[2]
    for size in sys.argv[3:]:
        w, h = (int(x) for x in (size.split('x') if 'x' in size else (size, size)))
        render(svg, f'{prefix}-{w}x{h}.png', w, h)
