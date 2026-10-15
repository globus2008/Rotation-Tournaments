"""Small adb helper for driving the app: python adbui.py dump | tap X Y | tapt TEXT | type TEXT | shot FILE | key CODE"""
import os
import re
import subprocess
import sys
import time

ADB = os.path.expanduser(r'~\AppData\Local\Android\Sdk\platform-tools\adb.exe')


def adb(*args, binary=False):
    r = subprocess.run([ADB, *args], capture_output=True)
    return r.stdout if binary else r.stdout.decode('utf-8', 'replace')


def nodes():
    adb('shell', 'uiautomator', 'dump', '/sdcard/ui.xml')
    xml = adb('shell', 'cat', '/sdcard/ui.xml')
    out = []
    for m in re.finditer(r'<node [^>]*>', xml):
        n = m.group(0)
        label = (re.search(r' text="([^"]*)"', n).group(1) or re.search(r'content-desc="([^"]*)"', n).group(1))
        b = [int(x) for x in re.findall(r'\d+', re.search(r'bounds="([^"]*)"', n).group(1))]
        if label:
            out.append((label.replace('&#10;', ' | '), (b[0] + b[2]) // 2, (b[1] + b[3]) // 2, 'EditText' in n))
    return out


def main():
    cmd = sys.argv[1]
    if cmd == 'dump':
        for label, x, y, edit in nodes():
            print(f'{x:>5} {y:>5} {"[edit] " if edit else ""}{label[:90]}')
    elif cmd == 'tap':
        adb('shell', 'input', 'tap', sys.argv[2], sys.argv[3])
    elif cmd == 'tapt':
        for label, x, y, _ in nodes():
            if sys.argv[2].lower() in label.lower():
                adb('shell', 'input', 'tap', str(x), str(y))
                print('tapped', label[:60], x, y)
                break
        else:
            print('not found')
    elif cmd == 'type':
        adb('shell', 'input', 'text', sys.argv[2].replace(' ', '%s'))
    elif cmd == 'key':
        adb('shell', 'input', 'keyevent', sys.argv[2])
    elif cmd == 'shot':
        open(sys.argv[2], 'wb').write(adb('exec-out', 'screencap', '-p', binary=True))
    time.sleep(float(os.environ.get('WAIT', '1.5')))


if __name__ == '__main__':
    main()
