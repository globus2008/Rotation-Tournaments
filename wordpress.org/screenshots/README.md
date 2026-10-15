# WordPress.org listing assets

Scripts that build the banner, icons and screenshots in `../assets` (the SVN `assets/` folder).
Local Docker site only (http://localhost:8000). `wordpress.org/` is not part of the plugin zip (`files` in package.json).

| Script | What it does |
|---|---|
| `seed.py` | Fictional players `demo_*` (Emma Walker organizes), tournaments "Friday Mixed Doubles" (running) and "Summer Padel Cup" (closed), pages with the block. Ids in `state.json`; `--fresh` builds new ones. |
| `seed.py --names LOCALE` | Renames the two tournaments and pages (`NAMES`), e.g. `es_CL` before a Spanish run, `en_US` afterwards. |
| `capture_web.py [LOCALE]` | Web shots into `raw/<locale>/` (Twenty Twenty-Five during the run, banner colours on the block). Another locale installs the core and plugin language packs and switches back afterwards. |
| `compose.py [LOCALE]` | Frames the raw shots into `screenshot-N.jpg` (1600x1000); another locale writes `screenshot-N-<locale>.jpg`. |
| `banner.py [LOCALE]` | `banner-1544x500` / `772x250` (tagline and sports per locale in `TEXTS`) and, for English, the icons from `icon.svg`. |
| `render.py`, `adbui.py` | SVG to PNG; small adb helper for driving the app. |

Images carry no text of their own: captions are in the readme (`== Screenshots ==`) and get translated on translate.wordpress.org.

App shots (`raw/<locale>/app-ongoing|app-players|app-played.png`, 1080x2400) are taken by hand on the emulator with a
debug build (test ads): sign in as `demo_emma@example.com` / `demo1234`, select "Friday Mixed Doubles", type 6:4 in the
first match without saving. Clean status bar: `adb shell settings put global sysui_demo_allowed 1`, then
`am broadcast -a com.android.systemui.demo -e command clock -e hhmm 1000` (and battery/network/notifications).
For another language switch the app language first (menu -> Change Language).

A new locale: add it to `NAMES` (seed.py) and `TEXTS` (banner.py), then
`seed.py --names xx_XX`, `capture_web.py xx_XX`, app shots, `compose.py xx_XX`, `banner.py xx_XX`, `seed.py --names en_US`.
Done so far: en_US, es_CL (2026-10-15).

The Android app icon is built from the same `icon.svg`: `c:/scr/doroto/tools/app-icon/build.py`.

A localized set is worth it once the readme of that locale is translated on translate.wordpress.org
(the localized directory, e.g. de.wordpress.org, shows it only then).
