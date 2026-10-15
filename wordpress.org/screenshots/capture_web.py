"""Web screenshots for WordPress.org (local Docker site, run seed.py first).

Saves raw PNGs to raw/<locale>/web-*.png. For another language the site and the admin
user are switched to that locale (core + plugin language packs), then back to English.

Usage: python capture_web.py [LOCALE]      e.g. python capture_web.py de_DE
"""
import json
import os
import subprocess
import sys

from playwright.sync_api import sync_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
SITE = 'http://localhost:8000'
CONTAINER = 'doubles-rotation-tournament-wordpress-1'
ADMIN = ('claude@claude.cz', 'claude')
LOCALE = sys.argv[1] if len(sys.argv) > 1 else 'en_US'
OUT = os.path.join(HERE, 'raw', LOCALE)
STATE = json.load(open(os.path.join(HERE, 'state.json')))

# Only the block is shown: no admin bar, no site header/footer, no page title.
CLEAN_CSS = """
#wpadminbar, header.wp-block-template-part, footer.wp-block-template-part, .wp-block-post-title { display: none !important; }
html { margin-top: 0 !important; }
main { margin-top: 0 !important; padding-top: 24px !important; }
"""


def wp(*args):
    cmd = ['docker', 'exec', CONTAINER, 'php', '/var/www/html/wp-cli.phar', '--allow-root',
           '--path=/var/www/html', *args]
    return subprocess.run(cmd, capture_output=True, text=True, encoding='utf-8').stdout.strip()


def set_locale(locale):
    if locale != 'en_US':
        wp('language', 'core', 'install', locale)
        wp('language', 'plugin', 'install', 'doubles-rotation-tournament', locale)
    wp('site', 'switch-language', locale)
    wp('user', 'meta', 'update', 'claude', 'locale', '' if locale == 'en_US' else locale)


def open_page(pg, page_id):
    pg.goto(f'{SITE}/?page_id={page_id}')
    pg.add_style_tag(content=CLEAN_CSS)
    pg.wait_for_selector('.doroto-block .doroto-tab')
    pg.wait_for_timeout(800)


def tab(pg, index):
    pg.locator('.doroto-block .doroto-tab').nth(index).click()
    pg.wait_for_timeout(700)


META = {}


def shot_block(pg, name, clip_height=None):
    box = pg.locator('.doroto-block').first.bounding_box()
    height = min(clip_height or box['height'], box['height'])
    clip = {'x': box['x'] - 24, 'y': box['y'] - 24, 'width': box['width'] + 48, 'height': height + 48}
    pg.screenshot(path=os.path.join(OUT, name + '.png'), clip=clip, full_page=True)
    # Where the tab bar starts, as a share of the shot width (compose.py can start the view there).
    tabs = pg.locator('.doroto-block .doroto-tabs').first.bounding_box()
    META[name] = {'tabs': round((tabs['y'] - clip['y'] - 16) / clip['width'], 4)}


def main():
    os.makedirs(OUT, exist_ok=True)
    theme = wp('theme', 'list', '--status=active', '--field=name')
    wp('theme', 'activate', 'twentytwentyfive')
    set_locale(LOCALE)
    mixed, padel = STATE['pages']['mixed'], STATE['pages']['padel']
    try:
        with sync_playwright() as p:
            browser = p.chromium.launch()
            ctx = browser.new_context(viewport={'width': 1200, 'height': 900}, device_scale_factor=2)
            ctx.add_init_script("try{localStorage.setItem('tour_seen','true')}catch(e){}")
            pg = ctx.new_page()
            pg.goto(f'{SITE}/wp-login.php')
            pg.fill('#user_login', ADMIN[0])
            pg.fill('#user_pass', ADMIN[1])
            pg.click('#wp-submit')
            pg.wait_for_load_state()

            # Running tournament: matches with score inputs, results, standings.
            open_page(pg, mixed)
            shot_block(pg, 'web-matches')
            tab(pg, 1)
            shot_block(pg, 'web-results', 1500)
            tab(pg, 2)
            shot_block(pg, 'web-players', 1400)
            # Tall window: the sticky save bar of the settings stays below the clip.
            pg.set_viewport_size({'width': 1200, 'height': 1600})
            tab(pg, 4)
            shot_block(pg, 'web-settings', 1100)
            pg.set_viewport_size({'width': 1200, 'height': 900})

            # Share dialog with the QR code.
            open_page(pg, mixed)
            pg.click('.doroto-block [data-help="share"]')
            pg.wait_for_timeout(1500)
            pg.screenshot(path=os.path.join(OUT, 'web-share.png'))
            pg.keyboard.press('Escape')

            # Presentation mode (full screen view for a TV in the club house).
            pg.set_viewport_size({'width': 1920, 'height': 1080})
            open_page(pg, mixed)
            tab(pg, 2)
            pg.click('.doroto-block [data-help="present"]')
            pg.wait_for_timeout(3000)
            pg.screenshot(path=os.path.join(OUT, 'web-presentation.png'))
            # The full-screen window cannot be resized: go on in a new one.
            pg.close()
            pg = ctx.new_page()
            pg.set_viewport_size({'width': 1200, 'height': 900})

            # Finished tournament: winners and statistics.
            open_page(pg, padel)
            shot_block(pg, 'web-winners', 1000)
            tab(pg, 3)
            pg.select_option('.doroto-block [data-help="stats-player"] select', index=1)
            pg.wait_for_selector('.doroto-block [data-help="stats-table"]:not([hidden])')
            pg.wait_for_timeout(800)
            shot_block(pg, 'web-stats', 1000)

            # Block editor with the block selected.
            pg.set_viewport_size({'width': 1440, 'height': 900})
            pg.goto(f'{SITE}/wp-admin/post.php?post={mixed}&action=edit')
            pg.wait_for_timeout(5000)
            for sel in ('button[aria-label="Close"]', '.components-modal__header button'):
                if pg.locator(sel).count():
                    pg.locator(sel).first.click()
            # Full-screen editor: no admin bar with the test account's name.
            pg.evaluate("['core', 'core/edit-post'].forEach((s) => wp.data.dispatch('core/preferences').set(s, 'fullscreenMode', true))")
            pg.wait_for_timeout(1500)
            pg.reload()
            pg.wait_for_timeout(5000)
            pg.add_style_tag(content='#wpadminbar { display: none !important; } html.wp-toolbar { padding-top: 0 !important; }'
                             ' .interface-interface-skeleton { top: 0 !important; }')
            pg.wait_for_timeout(500)
            frame = pg.frame_locator('iframe[name="editor-canvas"]')
            frame.locator('.wp-block-doroto-tournament').first.click(position={'x': 30, 'y': 30})
            pg.wait_for_timeout(1500)
            pg.screenshot(path=os.path.join(OUT, 'web-editor.png'))

            # Admin page of the plugin.
            pg.goto(f'{SITE}/wp-admin/admin.php?page=doubles-rotation-tournament')
            pg.wait_for_selector('[data-mounted]', timeout=30000)
            pg.wait_for_timeout(2000)
            pg.screenshot(path=os.path.join(OUT, 'web-admin.png'))
            browser.close()
        json.dump(META, open(os.path.join(OUT, 'meta.json'), 'w'), indent=1)
    finally:
        if LOCALE != 'en_US':
            set_locale('en_US')
        wp('theme', 'activate', theme)
    print('saved to', OUT)


if __name__ == '__main__':
    main()
