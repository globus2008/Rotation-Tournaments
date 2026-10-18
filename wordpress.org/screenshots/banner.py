"""Banner and icons for WordPress.org from icon.svg and the raw screenshots.

Writes ../assets/banner-1544x500.png, banner-772x250.png, icon-256x256.png, icon-128x128.png, icon.svg.
Usage: python banner.py [LOCALE]   (another locale writes banner-*-<locale>.png with its screenshots
                                    and the texts of TEXTS, English when the locale is missing there)
"""
import base64
import json
import os
import shutil
import sys

from playwright.sync_api import sync_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
LOCALE = sys.argv[1] if len(sys.argv) > 1 else 'en_US'
RAW = os.path.join(HERE, 'raw', LOCALE)
OUT = os.path.join(HERE, '..', 'assets')
TITLE = 'Rotation Tournaments'
# Tagline, its highlighted words and the sports per locale.
TEXTS = {
    'en_US': ('Doubles with a new partner every match', 'new partner',
              ['Tennis', 'Padel', 'Badminton', 'Table tennis', 'Squash', 'Beach volleyball']),
    'es_CL': ('Dobles con una nueva pareja en cada partido', 'nueva pareja',
              ['Tenis', 'Pádel', 'Bádminton', 'Tenis de mesa', 'Squash', 'Vóleibol playa']),
    'cs_CZ': ('Čtyřhra s novým spoluhráčem v každém zápase', 'novým spoluhráčem',
              ['Tenis', 'Padel', 'Badminton', 'Stolní tenis', 'Squash', 'Plážový volejbal']),
    'es_MX': ('Dobles con una nueva pareja en cada partido', 'nueva pareja',
              ['Tenis', 'Pádel', 'Bádminton', 'Tenis de mesa', 'Squash', 'Voleibol de playa']),
    'nl_NL': ('Elke wedstrijd een nieuwe partner', 'nieuwe partner',
              ['Tennis', 'Padel', 'Badminton', 'Tafeltennis', 'Squash', 'Beachvolleybal']),
    'ru_RU': ('Новый партнёр в каждом матче', 'Новый партнёр',
              ['Теннис', 'Падел', 'Бадминтон', 'Настольный теннис', 'Сквош', 'Пляжный волейбол']),
    'sv_SE': ('Dubbel med ny partner i varje match', 'ny partner',
              ['Tennis', 'Padel', 'Badminton', 'Bordtennis', 'Squash', 'Beachvolleyboll']),
}
TAGLINE, HIGHLIGHT, SPORTS = TEXTS.get(LOCALE, TEXTS['en_US'])
# The browser view starts just below the tab bar; a longer app notice (ru_RU wraps it) moves the tabs down.
TABS = json.load(open(os.path.join(RAW, 'meta.json'))).get('web-players', {}).get('tabs', 0.3118)
OFFSET = TABS + 0.0502


def b64(path, mime):
    return f'data:{mime};base64,' + base64.b64encode(open(path, 'rb').read()).decode()


def html():
    icon = b64(os.path.join(HERE, 'icon.svg'), 'image/svg+xml')
    web = b64(os.path.join(RAW, 'web-players.png'), 'image/png')
    app = b64(os.path.join(RAW, 'app-ongoing.png'), 'image/png')
    chips = ''.join(f'<span>{s}</span>' for s in SPORTS)
    return f"""<html><head><meta charset="utf-8"><style>
* {{ box-sizing: border-box; }}
body {{ margin: 0; width: 1544px; height: 500px; overflow: hidden; position: relative;
        font-family: 'Segoe UI', system-ui, sans-serif; color: #fff;
        background: radial-gradient(circle at 92% 0%, #2f7be0 0, transparent 50%),
                    radial-gradient(circle at 0% 100%, #1d5a2a 0, transparent 40%),
                    linear-gradient(120deg, #0b2a66 0%, #123f8f 60%, #1b4f9c 100%); }}
.ring {{ position: absolute; border-radius: 50%; border: 40px solid; opacity: .10; }}
.left {{ position: absolute; left: 80px; top: 92px; width: 700px; }}
.brand {{ display: flex; align-items: center; gap: 28px; }}
.brand img {{ width: 128px; height: 128px; filter: drop-shadow(0 10px 24px rgba(0,0,0,.35)); }}
h1 {{ margin: 0; font-size: 66px; line-height: 1.02; font-weight: 800; letter-spacing: -1px; }}
p {{ margin: 34px 0 26px; font-size: 32px; color: #cfe3ff; }}
p b {{ color: #8fd14f; font-weight: 700; }}
.chips {{ display: flex; flex-wrap: wrap; gap: 10px; }}
.chips span {{ font-size: 20px; padding: 6px 16px; border-radius: 20px; background: rgba(255,255,255,.12);
               border: 1px solid rgba(255,255,255,.22); }}
.browser {{ position: absolute; left: 820px; top: 70px; width: 620px; height: 470px; background: #fff;
            border-radius: 14px; overflow: hidden; box-shadow: 0 30px 70px rgba(0,0,0,.45); }}
.browser .bar {{ height: 30px; background: #f1f3f6; display: flex; gap: 7px; align-items: center; padding: 0 14px; }}
.browser .bar i {{ width: 10px; height: 10px; border-radius: 50%; background: #d4d9e1; }}
.browser img {{ width: 100%; display: block; margin-top: -{OFFSET * 620:.0f}px; }}
.phone {{ position: absolute; left: 1290px; top: 120px; width: 210px; height: 446px; background: #10151f;
          border-radius: 30px; padding: 8px; box-shadow: 0 30px 70px rgba(0,0,0,.5); }}
.phone img {{ width: 100%; height: 100%; object-fit: cover; object-position: top; border-radius: 23px; display: block; }}
</style></head><body>
<div class="ring" style="width:520px;height:520px;left:-160px;top:-260px;border-color:#8fd14f"></div>
<div class="ring" style="width:420px;height:420px;left:600px;top:300px;border-color:#5ec8ff"></div>
<div class="left">
  <div class="brand"><img src="{icon}"><h1>{TITLE.replace(' ', '<br>')}</h1></div>
  <p>{TAGLINE.replace(HIGHLIGHT, f'<b>{HIGHLIGHT}</b>')}</p>
  <div class="chips">{chips}</div>
</div>
<div class="browser"><div class="bar"><i></i><i></i><i></i></div><img src="{web}"></div>
<div class="phone"><img src="{app}"></div>
</body></html>"""


def main():
    suffix = '' if LOCALE == 'en_US' else '-' + LOCALE
    page = html()
    with sync_playwright() as p:
        b = p.chromium.launch()
        for w, h, scale in ((1544, 500, 1), (772, 250, 0.5)):
            pg = b.new_page(viewport={'width': 1544, 'height': 500}, device_scale_factor=scale)
            pg.set_content(page)
            pg.wait_for_timeout(500)
            pg.screenshot(path=os.path.join(OUT, f'banner-{w}x{h}{suffix}.png'))
            pg.close()
        b.close()
    if LOCALE == 'en_US':
        sys.path.insert(0, HERE)
        from render import render
        for s in (256, 128):
            render(os.path.join(HERE, 'icon.svg'), os.path.join(OUT, f'icon-{s}x{s}.png'), s, s)
        shutil.copy(os.path.join(HERE, 'icon.svg'), os.path.join(OUT, 'icon.svg'))
    print('banner and icons written to', os.path.abspath(OUT))


if __name__ == '__main__':
    main()
