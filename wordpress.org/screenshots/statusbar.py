"""Copies the clean status bar (rows 0-130) of the cs_CZ app shots onto another locale's app shots.

The emulator's System UI demo mode sometimes draws the status bar icons white or without the battery;
the bar is the same in every language, so it is taken from a good set.
Usage: python statusbar.py LOCALE
"""
import os
import sys

from PIL import Image

RAW = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'raw')
LOCALE = sys.argv[1]
for name in ('ongoing', 'players', 'played'):
    src = Image.open(os.path.join(RAW, 'cs_CZ', f'app-{name}.png'))
    path = os.path.join(RAW, LOCALE, f'app-{name}.png')
    dst = Image.open(path)
    dst.paste(src.crop((0, 0, 1080, 130)), (0, 0))
    dst.save(path)
print('status bar copied to', LOCALE)
