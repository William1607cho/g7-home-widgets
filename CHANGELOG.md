# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.1] - 2026-09-26

### Changed

- **Board name colour in list widgets** (recent posts, popular posts, webzine style) is now the
  template's primary colour instead of blue — the same colour the template's own home widgets used
  for board names (`--color-primary-600`, dark mode `--color-primary-400`). Templates without these
  tokens get the same terracotta values as a fallback (`#cf5730` / `#e1937a`). Category names and
  everything else look the same as before.

## [0.4.0] - 2026-09-26

Home sections and the new settings screen. Needs g7-webzine-addon 1.3.0+ only for the optional
webzine-style widget. After updating, run `plugin:update` once more with the same package so the
new hook listeners take effect.

### Added

- **Home sections** — the plugin now draws its own home-page area with five sections
  (each on/off, one or two columns; two columns are 1:1 and stack on narrow screens). It is
  injected into the home layout as an overlay (`resources/extensions/home.json`,
  `main_content`, before the template's own home content) — no template files change.
  - Widget types: **recent posts**, **popular posts** (This Week / This Month / 1 Year tabs,
    switched in the browser without another request), **news ticker** (rolls one line at a time,
    pauses on hover or focus, stays still with `prefers-reduced-motion`), **gallery**,
    **webzine style** and **HTML** (see below).
  - Default layout = the current home: section 1 two columns (recent · popular), section 2 one
    column (ticker on the `notice` board), sections 3–5 off. Default titles and icons are the
    same as the template widgets (Recent Posts · clock, Popular Posts · fire, Notice · bullhorn).
  - Settings key `home_layout` (plugin settings file). When it is absent the defaults above are
    used; nothing is written on update.
- **Admin screen "Home Page Settings"** (`/admin/plugins/g7-home-widgets/settings`; the admin
  menu entry is renamed from "홈 위젯 게시판 설정" / "Home Widget Boards" to "홈 화면 설정" /
  "Home Page Settings" — display name
  only, same menu id, permission and path) with tabs **Widget Layout** and **Section 1–5**.
  - Widget Layout: five dashed boxes on the left show sections 1–5 as currently set (sections
    that are off are dimmed, two-column sections are split left/right) and update before saving;
    the rows on the right set each section on/off and 1 or 2 columns, and hovering, clicking or
    focusing a row highlights its box. Stacks on narrow screens.
  - Section tabs use the full width. A two-column section has sub-tabs "Left column · <widget>" /
    "Right column · <widget>" (the name follows the widget type as it changes); a section that is
    off only shows a note to turn it on in Widget Layout.
  - Each column: widget type → title and icon (an icon grid picker: search, 8 columns, name on
    hover, check on the selected icon, first cell = the widget type's default icon; the 141-icon
    allow-list) → number of items (1–20) → board choice (recent, popular, gallery: cards with drag
    handle, board name and slug, search, "Included" / "Not included" areas, drag or X / + buttons,
    "Select all" / "Deselect all" that act on the search results when a search term is set; saved as
    the "not included" list so new boards are included automatically; ticker: one board) or the
    HTML box.
  - Clicking outside an open select box or icon grid only closes it (the click does not reach the
    element underneath). Form keys left over from another admin screen are dropped on arrival.
  - The Columns select on the Widget Layout tab has a fixed width that fits its longest option on
    one line (the opened list uses the same width); on narrow screens it follows the row width.
  - Saving goes through the core plugin settings endpoint; the plugin adds validation rules
    (items 1–20, a ticker with more than one board is rejected), converts the form into
    `home_layout` before saving, never touches `excluded_board_ids`, and clears the widget data
    cache and the cached bot home page after saving. New read endpoints
    `GET /api/plugins/g7-home-widgets/admin/home-form` and `admin/home-meta` (`core.plugins.read`).
- Recent and popular posts show "board name · category"; posts on wiki boards (the boards listed in
  g7-light-wiki's `wiki_boards` setting, read through the core settings helper) show the board name
  only.
- **Gallery** widget — recent posts of the chosen boards as thumbnail cards (1–20 items). The
  thumbnail is sirsoft-board's own list thumbnail (`PostResource`: image attachment first, then the
  first image in the body); posts without one get a placeholder box; secret posts never show a
  thumbnail. No image-delivery variants.
- **Webzine style** widget (needs g7-webzine-addon 1.3.0+) — recent posts of the chosen boards as a
  list: thumbnail, title, summary, board name and category (no category on wiki boards) and date,
  1–20 items. Posts come from the same core path as the gallery (readable boards, `PostService`,
  `PostResource`); only the summary and thumbnail come from the add-on's public contract
  `WebzineCards::cards()` — no add-on internals are used. Secret posts always show the title only,
  a lock in the thumbnail slot and no summary. A post without a thumbnail shows the add-on's
  fallback image when the add-on has one set, otherwise the gallery's empty frame.
  - Add-on presence = the core reports the plugin active **and** the contract class exists with
    `VERSION >= 1`; checked once per request, so turning the add-on on or off shows on the next
    request. Turning g7-webzine-addon on or off also clears the widget data cache and the cached
    bot home page (the core clears the SEO cache on activation only).
  - Without the add-on (not installed, turned off, or a version without the contract) the type is
    a disabled choice labelled "(add-on required)". A column whose type is webzine also shows a
    small preview image (`resources/assets/webzine-preview.webp`, bundled, no external request —
    replace the file to change it), a note and a link to the add-on repository.
  - Columns already saved as webzine are drawn as recent posts (browser and bot) with their own
    title, icon, item count and board choice (empty title / icon → the recent-posts defaults), and
    switch back when the add-on returns; the saved settings are not changed. Saving a webzine
    column while the add-on is missing is allowed.
- **HTML** widget — admin-written HTML, sanitized with the core `HtmlSanitizer` when saving and
  again when shown (browser `HtmlContent`, bot pages). Scripts, event attributes and
  `javascript:` links are removed.
- **Title and icon per column** (all widget types). Icons are limited to the wc-community icon
  subset (141 Font Awesome solid icons, `resources/home/icons.json`); others are rejected when
  saving and ignored when reading.
- `GET /api/plugins/g7-home-widgets/home` (public, `optional.sanctum`) — all enabled sections
  and their lists in one response.
- Search-engine (bot) pages get the same data through the `core.seo.filter_context` filter, so
  the sections render server-side too (the ticker as a static list).
- Plugin CSS (`dist/css/plugin.css`) and JS (`dist/js/plugin.iife.js`, ticker handler
  `g7-home-widgets.ticker`) through the core extension bundles.

### Changed

- Each widget has its own board choice; there is no common excluded list for the new sections.
  Which boards a widget shows = the boards the viewer can read (decided by sirsoft-board) minus
  the widget's "not included" list (recent, popular, gallery) or its one board (ticker). Read
  permission, secret and deleted posts are still decided by sirsoft-board; popular posts come from
  the sirsoft-board popular cache (a narrow board selection can return fewer items than the limit).
  A missing board name on a popular post is filled from the readable-board list already loaded.
- Migration without rewriting the settings file: a list widget with no saved choice uses the old
  `excluded_board_ids` as its "not included" list, so the home page looks the same until the new
  screen is saved. `excluded_board_ids` stays (the three old widget APIs still use it).
- Widget data cache keys carry a generation number. Post create/update/delete/blind/restore,
  board updates and saving this plugin's settings bump it, so a bot page re-rendered right after
  a new post no longer shows the old list for the bot cache lifetime. Saving this plugin's
  settings also clears the cached bot home page.

### Removed

- The 0.3.0 settings screen (Included / Not included cards for one common list) and what only it
  used: the drag script route `GET /api/plugins/g7-home-widgets/board-filter-drag.js` and its
  translation keys. The admin API `GET` / `PUT admin/board-filter` stays (it is the only way to
  edit `excluded_board_ids`, which the three old widget APIs still read).

### Unchanged

- The three widget APIs used by the wc-community template (`recent-posts`, `popular-posts`,
  `notice-posts`) and their responses. The template widgets and the new sections are both shown
  until the template is cleaned up.

### Rolling back to 0.3.0

- `plugin:update` with the 0.3.0 package removes the new sections, but the home overlay row stays
  in the database; each home visit then makes one extra request to the removed
  `/api/plugins/g7-home-widgets/home` (404, no toast, nothing drawn). The `home_layout` key stays
  in the settings file and is used again after updating back to 0.4.0. See README.

## [0.3.0] - 2026-09-17

### Added

- **Widget board settings** — an admin screen at `/admin/plugins/g7-home-widgets/settings`
  (sidebar: "홈 위젯 게시판 설정" / "Home Widget Boards") to choose which boards the
  **recent posts** and **popular posts** widgets leave out. The notice ticker is not affected
  (it already names its board).
  - Every existing board is shown as a card in one of two areas — **Included** and
    **Not included** (cards wrap in rows). Move a card by dragging it between the areas or
    with its X / + button (always visible, so it also works on touch screens); a name filter
    narrows both areas. Inactive boards are shown with an "Inactive" badge.
  - Dragging is handled by a small script the settings layout loads
    (`GET /api/plugins/g7-home-widgets/board-filter-drag.js`, public, cached). Layout JSON
    action handlers cancel `dragstart`, so the script starts the drag natively and, when a
    card is dropped on the other area, presses that card's X / + button — the same move logic
    as a click. It makes no network requests and does not touch app state or storage.
  - Only the IDs in **Not included** are saved (`excluded_board_ids` plugin setting).
    New boards are therefore included automatically; IDs of deleted boards are ignored and
    dropped on the next save.
  - The setting only controls visibility. It does **not** change read permissions — per-board
    `sirsoft-board.{slug}.posts.read` filtering still applies to every request.
- `GET /api/plugins/g7-home-widgets/admin/board-filter` (`core.plugins.read`) and
  `PUT` on the same path (`core.plugins.update`), both returning
  `{ boards: [{id, name, slug, is_active}], excluded_board_ids: [...] }`.
  - `PUT` body: `{ "excluded_board_ids": [int, ...] }` — an array (may be empty) of up to 500
    integers ≥ 1. Duplicates and IDs of boards that do not exist are removed before saving;
    other input is rejected with 422.
- `github_url` in `plugin.json`, so the admin update check can find this repository.

### Changed

- Recent and popular posts now drop excluded boards **in the query** (before the pool is
  cut), so the requested `limit` is still filled. Request and response formats are unchanged.
- The pool cache keys include a short fingerprint of the excluded-board list, so a saved
  change takes effect immediately; saving also clears every pool cached under the previous
  list (recent: all pool sizes; popular: all four periods × all pool sizes).

## [0.2.0] - 2026-09-15

### Added

- `GET /api/plugins/g7-home-widgets/notice-posts?board={slug}&limit=5` — latest posts of a
  single board for a one-line home "notice ticker". Same filters as recent posts (active
  board, published, not deleted, not a reply) plus **secret posts excluded**, ordered by
  `created_at` desc. `board` is required; `limit` defaults to 5, clamped to 10.
  - Fields: `id`, `board_slug`, `title`, `created_at`, `created_at_formatted` only — no
    author, e-mail, board name or counters.
  - A missing / inactive board, a slug that is not a valid board slug, or no
    `sirsoft-board.{slug}.posts.read` permission returns an **empty array**, not an error.
  - Permission is checked before the cache (a request without permission neither reads nor
    fills it); the cache key includes `board` and `limit` (90 seconds).

## [0.1.0] - 2026-09-14

Initial public release.

### Added

- `GET /api/plugins/g7-home-widgets/recent-posts` — site-wide recent posts across all
  active `sirsoft-board` boards, merged in `created_at` order via per-board `UNION ALL`
  subqueries (reuses the core `idx_board_posts_board_status_created` index). Includes
  `category`, which the core recent-posts API does not return. `limit` defaults to 10,
  clamped to 20.
- `GET /api/plugins/g7-home-widgets/popular-posts` — site-wide popular posts with the same
  filters and ordering as the core `boards/popular` endpoint (active boards, published,
  not deleted, not a reply, not secret, period `today`/`week`/`month`/`year`; views desc,
  then comments desc), plus `category`. Author fields are not queried or returned.
  `period` defaults to `week` (`all`/unknown → `year`); `limit` defaults to 10, clamped
  to 50.
- Permission-safe caching for both endpoints: a user-independent pool is cached for 90
  seconds (keyed by period for popular posts) and `sirsoft-board.{slug}.posts.read` is
  checked per request for the current user. The pool is oversized so results stay full
  after filtering.
