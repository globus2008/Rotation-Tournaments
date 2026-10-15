"""Demo data for the WordPress.org screenshots (local Docker site only).

Creates fictional players (logins demo_*), two tournaments and two pages with the
tournament block fixed to them:
  - "Friday Mixed Doubles": doubles tennis, 12 players, 2 courts, mixed teams
    (the women are the special group), 15 matches played, 3 ongoing.
  - "Summer Padel Cup": doubles padel, 8 players, closed, winners announced.
The organizer of both is the demo player Emma Walker. Ids are saved to state.json;
run again with --fresh to build new tournaments.

Usage: python seed.py [--fresh]
       python seed.py --names LOCALE   (only renames the two tournaments, e.g. es_CL; en_US restores)
"""
import json
import os
import random
import subprocess
import sys
import time
import urllib.request

BASE = 'http://localhost:8000/index.php?rest_route='
CONTAINER = 'doubles-rotation-tournament-wordpress-1'
DB_CONTAINER = 'doubles-rotation-tournament-database-1'
ADMIN = ('claude@claude.cz', 'claude')
STATE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'state.json')

WOMEN = ['Emma Walker', 'Sofia Rossi', 'Lena Novak', 'Mia Jensen', 'Chloe Martin', 'Ana Silva']
# Tournament names per locale (the names are data, the plugin does not translate them).
NAMES = {
    'en_US': ('Friday Mixed Doubles', 'Summer Padel Cup'),
    'es_CL': ('Dobles mixtos del viernes', 'Copa de pádel de verano'),
    'cs_CZ': ('Páteční smíšená čtyřhra', 'Letní padelový pohár'),
}
MEN = ['Liam Carter', 'Lucas Meyer', 'Noah Berg', 'Mateo Garcia', 'Oliver Hughes', 'Jonas Weber']


def wp(*args):
    cmd = ['docker', 'exec', CONTAINER, 'php', '/var/www/html/wp-cli.phar', '--allow-root',
           '--path=/var/www/html', *args]
    return subprocess.run(cmd, capture_output=True, text=True, encoding='utf-8').stdout.strip()


def sql(query):
    cmd = ['docker', 'exec', DB_CONTAINER, 'sh', '-c', f'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" wordpress -e "{query}"']
    return subprocess.run(cmd, capture_output=True, text=True).stdout


def call(method, route, body=None, token=None):
    req = urllib.request.Request(BASE + route + '&ts=' + str(time.time()),
                                 data=json.dumps(body).encode() if body is not None else None, method=method)
    req.add_header('Content-Type', 'application/json')
    if token:
        req.add_header('Authorization', 'Bearer ' + token)
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        return e.code, json.loads(e.read().decode() or '{}')


def login():
    st, b = call('POST', '/player/login', {'email': ADMIN[0], 'password': ADMIN[1]})
    assert st == 200, (st, b)
    return b['access_token']


def ensure_user(name):
    first, last = name.split(' ', 1)
    login_name = 'demo_' + first.lower()
    uid = wp('user', 'get', login_name, '--field=ID')
    if not uid.isdigit():
        uid = wp('user', 'create', login_name, f'{login_name}@example.com', '--role=subscriber',
                 f'--display_name={name}', f'--first_name={first}', f'--last_name={last}', '--porcelain')
    return int(uid)


def detail(tid, token):
    st, t = call('GET', f'/doroto/v1/tournament-detail/{tid}', None, token)
    assert st == 200, (st, t)
    return t


def is_open(m):
    return int(m['match_number']) > 0 and int(m['hide']) == 0 and int(m['result_1']) == 0 and int(m['result_2']) == 0


def is_finished(m):
    return int(m['match_number']) > 0 and int(m['hide']) == 0 and (int(m['result_1']) > 0 or int(m['result_2']) > 0)


def play(tid, token, target, strength, loser_max):
    """Plays the oldest open match until `target` matches are finished; stronger teams win more often."""
    while True:
        t = detail(tid, token)
        done = [m for m in t['matches_list'] if is_finished(m)]
        if len(done) >= target:
            return t
        open_m = sorted((m for m in t['matches_list'] if is_open(m)), key=lambda m: int(m['match_number']))
        if not open_m:
            if t.get('round_end_notice_html') and t['round_end_notice_html'] != 'display warning':
                call('POST', '/doroto/v1/round-end-action', {'tournament_id': tid, 'action': 'next'}, token)
                continue
            return t
        m = open_m[0]
        p = [int(m[f'player_{i}']) for i in range(1, 5)]
        s1, s2 = strength[p[0]] + strength[p[1]], strength[p[2]] + strength[p[3]]
        win = random.random() < s1 / (s1 + s2)
        lose = random.randint(1, loser_max)
        r = (6, lose) if win else (lose, 6)
        call('POST', '/doroto/v1/match-result', {'tournament_id': tid, 'match_number': int(m['match_number']),
                                                'result_1': r[0], 'result_2': r[1], 'hide': 0}, token)


def build_tournament(token, ids, ttype, name, settings, special, organizer):
    st, created = call('POST', '/doroto/v1/add-tournament', {'tournament_type': ttype}, token)
    assert st == 200, (st, created)
    tid = int(created['tournament_id'])
    call('POST', '/doroto/v1/tournament-save', {'tournament_id': tid, 'name': name, **settings}, token)
    for p in ids:
        call('POST', '/doroto/v1/add-player', {'tournament_id': tid, 'player_to_add': p}, token)
    for p in special:
        call('POST', '/doroto/v1/add-to-special-group', {'tournament_id': tid, 'player_id': p}, token)
    # Emma organizes. The creator cannot be removed over REST, so the list is rewritten in the
    # local database; the test admin keeps its rights as a site administrator.
    sql(f"update wp_doroto_tournaments set admin_users='a:1:{{i:0;i:{organizer};}}' where id={tid}")
    call('POST', '/doroto/v1/tournament-toggle', {'tournament_id': tid, 'action_type': 'registration'}, token)
    return tid


def ensure_page(slug, title, tid, presentation=False):
    # Colours of the banner (blue accent, green special group); wide so the cards sit side by side.
    attrs = {'tournamentId': tid, 'align': 'wide', 'accentColor': '#1b4f9c', 'specialColor': '#cdeeb0'}
    if presentation:
        attrs['presentation'] = True
    content = f'<!-- wp:doroto/tournament {json.dumps(attrs)} /-->'
    pid = wp('post', 'list', '--post_type=page', f'--name={slug}', '--field=ID')
    if pid.isdigit():
        wp('post', 'update', pid, f'--post_content={content}', f'--post_title={title}')
    else:
        pid = wp('post', 'create', '--post_type=page', '--post_status=publish', f'--post_name={slug}',
                 f'--post_title={title}', f'--post_content={content}', '--porcelain')
    return int(pid)


def rename(locale):
    state = json.load(open(STATE))
    token = login()
    for key, name in zip(('mixed', 'padel'), NAMES[locale]):
        call('POST', '/doroto/v1/tournament-save', {'tournament_id': state[key], 'name': name}, token)
        wp('post', 'update', str(state['pages'][key]), f'--post_title={name}')
        print(state[key], name)


def main():
    if '--names' in sys.argv:
        return rename(sys.argv[sys.argv.index('--names') + 1])
    random.seed(7)
    state = {}
    if os.path.exists(STATE) and '--fresh' not in sys.argv:
        state = json.load(open(STATE))
    token = login()

    women = [ensure_user(n) for n in WOMEN]
    men = [ensure_user(n) for n in MEN]
    everyone = women + men
    state['players'] = dict(zip(WOMEN + MEN, everyone))
    strength = {p: random.uniform(0.6, 1.4) for p in everyone}
    emma = women[0]

    if 'mixed' not in state:
        tid = build_tournament(token, everyone, 21, 'Friday Mixed Doubles',
                               {'courts_available': 2, 'whole_names': 1, 'two_special_group': 1,
                                'two_out_group': 1, 'min_not_playing': 1, 'average_result': 9,
                                'latitude': 51.5007, 'longitude': -0.1246, 'visibility': 1}, women, emma)
        play(tid, token, 15, strength, 4)
        state['mixed'] = tid

    if 'padel' not in state:
        squad = [women[1], women[3], women[4], women[5], men[0], men[2], men[3], men[5]]
        tid = build_tournament(token, squad, 25, 'Summer Padel Cup',
                               {'courts_available': 2, 'whole_names': 1, 'min_not_playing': 1,
                                'two_special_group': 0, 'two_out_group': 0,
                                'average_result': 9, 'visibility': 1}, [], emma)
        play(tid, token, 14, strength, 5)
        call('POST', '/doroto/v1/tournament-toggle', {'tournament_id': tid, 'action_type': 'tournament'}, token)
        state['padel'] = tid

    # 12 players fill 2 courts; with 3 courts the block explains why the third match waits.
    call('POST', '/doroto/v1/tournament-save', {'tournament_id': state['mixed'], 'courts_available': 2}, token)
    state['pages'] = {
        'mixed': ensure_page('demo-friday-mixed-doubles', 'Friday Mixed Doubles', state['mixed']),
        'padel': ensure_page('demo-summer-padel-cup', 'Summer Padel Cup', state['padel']),
    }
    json.dump(state, open(STATE, 'w'), indent=1)
    print(json.dumps(state, indent=1))


if __name__ == '__main__':
    main()
