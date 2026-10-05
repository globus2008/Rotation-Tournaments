# Rotation Tournaments – WordPress plugin (notes for Claude and contributors)

- Slug: `doubles-rotation-tournament`. Text domain: the same.
- Published on WordPress.org.
- Repo: https://github.com/globus2008/Rotation-Tournaments
- Runs doubles tournaments where partners rotate every match. Server side of the Android app **Doroto** (`c:\scr\doroto`).
- Code comments and notes are in **English**; the owner chats in Czech.
- `c:\scr\doubles-rotation-tournament` is a full local WP install; the plugin lives in this folder.
- PHP and MySQL are not on PATH on the dev machine.
- The code requires **PHP 8.0+**: it uses `mixed` and union types.

## Files
| File | Purpose |
|---|---|
| `doubles-rotation-tournament.php` | Bootstrap, assets, activation hooks, version check, admin notices |
| `includes/doroto-endpoints.php` | All REST routes (namespaces `doroto/v1`, `player`) and token auth |
| `includes/doroto-services.php` | Services (2.0): every tournament change shared by web forms, REST and blocks |
| `includes/doroto-tournament-management.php` | Drawing algorithm `doroto_offer_games`, table creation, scoring, toggles, demo data |
| `includes/doroto-shortcodes.php` | About 30 shortcodes and their admin-post form handlers |
| `includes/doroto-players-management.php` | Invitation link `doroto_register_player`, winner logic, AJAX helpers |
| `includes/doroto-repeated-functions.php` | `doroto_is_admin`, `doroto_getTournamentId`, flash messages, redirects |
| `includes/doroto-backend-pages.php` | Admin menu (9 tabs), settings defaults `doroto_settings` |
| `includes/doroto-frontend-pages.php` | Pages created on activation |
| `includes/doroto-view-model.php`, `doroto-view-list.php` | View models of the blocks; GET `view/<id>`, `view/<id>/player/<pid>`, `view-list` |
| `includes/doroto-block-actions.php` | POST `block-action` (runs a service, returns message + fresh view), GET `block-candidates/<id>` |
| `includes/doroto-blocks.php` | Block registration, store config, settings schema, migration (convert main page, `[doroto_tournament]`, `[doroto_tournament_list]`) |
| `src/blocks/*` -> `build/blocks/*` | Blocks `doroto/tournament` (+ variations standings/matches/presentation), `doroto/tournament-list`, `doroto/invite`; `npm run build` |
| `languages/` | cs_CZ only |

## Services layer (2.0, branch `v2-blocks`)
- Plan: blocks + Interactivity API front end. Stage A (foundations) first; see the owner's approved plan.
- `includes/doroto-services.php`: `doroto_service_*()` never read `$_POST`, redirect or print. They return
  `doroto_service_ok($action, $extra)` or `doroto_service_error($error_code, $status)` (a `WP_Error`); the codes are
  the REST `action` / `error_code` the app knows. They check permissions, take the tournament lock and bump `last_update`.
- Callers:
  - web handlers: verify the nonce, call the service, save `doroto_service_message()` as the flash message, then redirect;
  - REST callbacks: resolve the user, call the service, return `doroto_service_rest_response()`.
  Where an old answer had another shape (already entered result: `forbidden` + HTTP 200; remove-admin;
  tournament-save; tournament-register) the REST callback maps it, so old app versions see no change.
- REST also accepts the website login: without an `Authorization` header `doroto_get_current_user_id_from_token()`
  returns the cookie user, which core sets only with a valid `X-WP-Nonce` (the blocks send it).
- Tests of the web forms: scratch scripts log in with cookies and post the forms; the app routes are covered by
  `c:\scr\doroto\tools\api-tests` (all pass after stage A2).

## Blocks (2.0)
- Dynamic blocks + Interactivity API. render.php prints the view model with directives (readable without JS);
  the view model sits in the block context (`context.data.view`). No JS in PHP; JS texts come from
  `wp_interactivity_config('doroto')` (`doroto_block_config()`).
- Server-side directive processing cannot evaluate derived `state.*` getters: anything that must be right in the
  server HTML (hidden flags, scores, trend) is a plain value of the view model (`flags`, `score`, `trend_text`, ...).
- Every change: `actions.run()` -> `block-action` -> service -> `{message, view}`. The 30 s poll compares
  `check-update` (answers a plain number) with `view.last_update`; typed scores live in `ui.drafts`.
- `courts_note` explains why fewer matches than courts are ongoing: during play another match is drawn only while
  at least 6 (doubles) / 3 (singles) players stay free (`doroto_matches_to_select_count()`).
- Reducing the courts during play never cancels ongoing matches and shows no note: the organizer decides whether
  to finish or skip them (owner decision 2026-10-05). The draw itself never exceeds the number of courts.
- Tests (local): Playwright scripts log in, drive the blocks and check the console (no errors expected).
- Translations of the plugin are made on translate.wordpress.org, do not edit `languages/`.

## Data model
- One table, `{prefix}doroto_tournaments`. Lists are serialized PHP arrays in text columns: `players`, `playing`, `statistics`, `matches_list`, `admin_users`, `special_group`, `payment_done`, `final_four`, `final_result`.
- `last_update` is a BIGINT in ms. The app uses it for change detection, so **every write must bump it.**
- Settings: the option `doroto_settings`. Defaults are in `doroto-backend-pages.php`.

## REST contract with the app
- Base URL: `<site>/index.php?rest_route=/doroto/v1/...`
- Auth header: `Authorization: Bearer <token>`. Since 1.6.0 every device has its own session
  (`doroto_store_session()`): multi-row metas `doroto_access_token` / `doroto_refresh_token` for lookup and
  `doroto_sessions` (sha256(refresh) => access, access_exp, refresh_exp, created). Max 10 devices per user.
  Single tokens issued by 1.5.x are migrated on first use.
- Success: `{success:true, action:"...", last_update?}`. Error: `{error_code:"..."}` plus an HTTP status.
- **Keep the existing endpoints backward compatible.** Old app versions stay in use. Add new endpoints or optional params instead of changing the old ones.

## Known issues (analysis from 2026-10-01)
### Security (critical)
- `doroto_add_current_user_to_admin` (also `nopriv`, no checks): anyone can become admin of any tournament.
- `doroto_save_final_doubles` runs on `init` with no nonce, login or admin check.
- `wp_ajax_nopriv_doroto_create_tournament_record`: any visitor can rebuild the demo data and create users.
- `doroto_toggle_registration`: no permission check.
- admin-post handlers in `doroto-shortcodes.php` check the nonce plus `is_user_logged_in()` only, never `doroto_is_admin()`. `tournament_parameters` can delete a tournament.
- REST payment endpoints: token only, no admin check.
- `debug` blocks in REST responses. `tournament-save` echoes `getallheaders()`, including the token.
- `player/register` and `google-login` ignore `users_can_register`. `player/login` bypasses `authenticate` filters.
  - Fixed in 1.6.0. With registration disabled, `player/register` with a token only works for an organizer (`doroto_user_is_organizer()`: web role or in some tournament's `admin_users`).

### Data integrity
- `doroto_save_match_result`: `if ($last_update == 0) round(...)` computes the value but never assigns it. Web results store `last_update = 0` and the app stops refreshing.
- Other paths that don't bump `last_update`:
  - remove-admin
  - `doroto_tournament_progress`
  - final doubles
- `dbDelta` only runs when the table is missing (`SHOW TABLES` guard). Columns added in later versions never reach upgraded sites.
- No locking: concurrent results and draws overwrite whole serialized blobs (lost updates, players stuck in `playing`).
  - Fixed in 1.6.0: `doroto_with_tournament_lock()`; REST writers are wrapped with `doroto_rest_locked()`, web forms lock in `doroto_guard_form_submission()` / `doroto_require_admin_action()`. Sessions use `doroto_with_user_lock()`. Tests: `c:\scr\doroto\tools\api-tests`.
- The `tournament-detail` GET calls `doroto_offer_games`, so a GET draws matches and writes to the DB.
- `doroto_create_statistics_table` compares IDs with strict `===` (int vs string).
- `getallheaders()`-only token lookup: the Authorization header is often stripped on FPM/CGI hosts.

### Players and invitations
- Fixed in 1.6.0 (tested 2026-10-02 on localhost): the invitation link `admin-ajax.php?action=doroto_register_player`
  only joins. Logged-out users go to the login page and back to the link, then to the tournament page.
- Fixed in 1.6.1: a deleted account (`delete_user`) leaves its open tournaments. It is removed if it has not played,
  otherwise suspended.
- The front-end settings form (`doroto_tournament_parameters`) is shown to plain players too. Saving is refused
  (`doroto_require_admin_action`), but showing it is confusing.
- `users-all` with the default `only_admin_players = 1` lists only players from tournaments where the organizer **played**.
  - App-created users don't get `doroto_creator`, so they never show up.
  - A WP administrator sees everyone, which is why the owner never noticed.
- Accounts created by an organizer get a random password that is never sent.

### Other
- Fixed in 2.0: assets of the shortcodes and the no-cache headers only on pages that use the plugin
  (`doroto_page_uses_plugin()`, plus `do_shortcode_tag` for shortcodes outside the content); admin assets only on the
  plugin page; unused tipTip removed.
- `ip-api.com` is called over HTTP on table render.
- `delete_database` defaults to 1.
- Activation creates 13 demo users.

## Draw (1.6.2)
- `doroto_offer_games_locked()`: steps 1-18 pick the first player and the candidate teammates as before
  (step 8 now prefers fewer games among equal rest). Step 18.1 scores every teammate candidate together with
  every opposing pair (`doroto_choose_opposing_team()`), key: teammate repeats, players without rest
  (round end), games, previous meetings (sum of squares), rest, random. Sides: `doroto_team_sides()`.
- `skip-matches` (organizer): hides several open matches, then draws once (one by one, each draw had only
  the players of one match free).

## Changing results on the web
- Every tournament organizer (`doroto_is_admin() > 0`: `admin_users` or a web role) may change results;
  only the founder (`admin_users[0]`) manages the organizers. When results may change:
  `doroto_match_results_editable()`.
- The change form `[doroto_change_game]` sits in the "Played Matches ..." panel above the table. Since 1.6.2
  organizers get a ✎ button next to every match ID (`doroto_display_games`), which fills the form
  (`doroto-frontend-scripts.js`). The form is printed inside a `<table>`, so browsers move the `<form>` out:
  reach its fields through `form.elements`, not `querySelector`.
- Front-end script and style use `doroto_VERSION` as their version (cache busting).

## Directory / reach (deferred by the owner)
- `website-info` feeds the directory plugin `doroto-websites`.
- The site never registers itself.
- `website_visible` defaults to 0.
- The app notice option `doroto_show_app_notice` is never set to `'true'`.
- See `c:\scr\doroto-websites\CLAUDE.md`.

### Links into the Android app (1.6.1)
- Android App Links work only for the verified domain doroto.ltcchrast.cz (`/.well-known/assetlinks.json` there).
  A club website can't be verified for the app, so its links always opened in the browser.
- The app shares `https://doroto.ltcchrast.cz/?tournament_id=5&doroto_site=<club>`.
  With the app installed, Android opens it in the app.
  Without it, `doroto_forward_foreign_tournament_links()` on the central site forwards the browser to the club.
  It forwards only to sites in the `doroto_websites` table; any other site gets a page with a link (no open redirect).
- The tournament page shows "Open in the app" (`doroto_app_link_box()`), an Android `intent://` link with a Google Play fallback.
- The central site must run 1.6.1, otherwise shared links show the central site's own tournament with that number.
