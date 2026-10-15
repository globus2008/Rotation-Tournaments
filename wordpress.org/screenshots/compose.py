"""Composes the WordPress.org screenshots from raw/<locale>/ into ../assets/.

No text is drawn into the images (captions live in the readme and are translated there),
so a locale only needs new raw captures. English goes to screenshot-N.png, another
locale to screenshot-N-<locale>.png (the file names WordPress.org looks for).

Usage: python compose.py [LOCALE]
"""
import base64
import json
import os
import struct
import sys

from playwright.sync_api import sync_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
LOCALE = sys.argv[1] if len(sys.argv) > 1 else 'en_US'
RAW = os.path.join(HERE, 'raw', LOCALE)
OUT = os.path.join(HERE, '..', 'assets')
W, H = 1600, 1000
META = json.load(open(os.path.join(RAW, 'meta.json'))) if os.path.exists(os.path.join(RAW, 'meta.json')) else {}

CSS = """
* { box-sizing: border-box; }
body { margin: 0; width: %dpx; height: %dpx; overflow: hidden; font-family: system-ui, sans-serif;
       background: radial-gradient(circle at 15%% 10%%, #e3f3d3 0, transparent 45%%),
                   linear-gradient(135deg, #eef4fc 0%%, #dce8f8 100%%); position: relative; }
.browser { position: absolute; background: #fff; border-radius: 14px; overflow: hidden;
           box-shadow: 0 24px 60px rgba(11, 42, 102, .22), 0 2px 6px rgba(11, 42, 102, .12); }
.browser .bar { height: 38px; background: #f1f3f6; border-bottom: 1px solid #e2e6ec; display: flex;
                align-items: center; gap: 8px; padding: 0 16px; }
.browser .bar i { width: 12px; height: 12px; border-radius: 50%%; background: #d4d9e1; display: block; }
.browser .bar b { flex: 1; height: 22px; margin-left: 18px; border-radius: 11px; background: #fff;
                  border: 1px solid #e2e6ec; }
.browser .view { position: absolute; top: 38px; left: 0; right: 0; bottom: 0; overflow: hidden; background: #fff; }
.browser .view img { width: 100%%; display: block; }
.phone { position: absolute; background: #10151f; border-radius: 46px; padding: 12px;
         box-shadow: 0 24px 60px rgba(11, 42, 102, .30); }
.phone img { width: 100%%; height: 100%%; display: block; border-radius: 36px; object-fit: cover; object-position: top; }
""" % (W, H)


def uri(name):
    data = base64.b64encode(open(os.path.join(RAW, name + '.png'), 'rb').read()).decode()
    return 'data:image/png;base64,' + data


def size(name):
    with open(os.path.join(RAW, name + '.png'), 'rb') as f:
        return struct.unpack('>II', f.read(24)[16:24])


def browser(name, x, y, w, h, offset=0):
    """Browser window with the raw shot; `offset` scrolls the shot up by that share of its width."""
    return (f'<div class="browser" style="left:{x}px;top:{y}px;width:{w}px;height:{h}px">'
            f'<div class="bar"><i></i><i></i><i></i><b></b></div>'
            f'<div class="view"><img src="{uri(name)}" style="margin-top:-{offset * w:.0f}px"></div></div>')


def phone(name, x, y, w):
    h = int(w * 2400 / 1080) + 24
    return f'<div class="phone" style="left:{x}px;top:{y}px;width:{w + 24}px;height:{h}px"><img src="{uri(name)}"></div>'


def centered(name, w, h, from_tabs=False):
    """Browser window as tall as the shot needs (at most h); from_tabs starts the view at the tab bar."""
    offset = META.get(name, {}).get('tabs', 0) if from_tabs else 0
    iw, ih = size(name)
    h = min(h, int((ih / iw - offset) * w) + 38)
    return browser(name, (W - w) // 2, (H - h) // 2, w, h, offset)


SLIDES = [
    # 1. The same tournament on the web and in the app.
    browser('web-matches', 70, 90, 1040, 820) + phone('app-ongoing', 1150, 70, 370),
    # 2-5. Tournament block: matches, results, standings, statistics.
    centered('web-matches', 1180, 900),
    centered('web-results', 1180, 900, True),
    centered('web-players', 1180, 900, True),
    centered('web-stats', 1180, 900, True),
    # 6. Presentation on a big screen.
    centered('web-presentation', 1440, 850),
    # 7. Share dialog with the QR code.
    centered('web-share', 1180, 900),
    # 8. Block editor.
    centered('web-editor', 1440, 900),
    # 9. Tournament settings for organizers.
    centered('web-settings', 1180, 900, True),
    # 10. Android app: ongoing, standings, played.
    phone('app-ongoing', 175, 70, 370) + phone('app-players', 615, 70, 370) + phone('app-played', 1055, 70, 370),
]


def main():
    os.makedirs(OUT, exist_ok=True)
    suffix = '' if LOCALE == 'en_US' else '-' + LOCALE
    with sync_playwright() as p:
        b = p.chromium.launch()
        pg = b.new_page(viewport={'width': W, 'height': H})
        for i, body in enumerate(SLIDES, 1):
            pg.set_content(f'<html><head><style>{CSS}</style></head><body>{body}</body></html>')
            pg.wait_for_timeout(400)
            path = os.path.join(OUT, f'screenshot-{i}{suffix}.jpg')
            pg.screenshot(path=path, type='jpeg', quality=88)
            print(path)
        b.close()


if __name__ == '__main__':
    main()
