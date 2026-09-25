# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Development version `0.4.0` (staging only). Part 1 of the home sections rework.

### Added

- **Home sections** — the plugin now draws its own home-page area with five sections
  (each on/off, one or two columns; two columns are 1:1 and stack on narrow screens). It is
  injected into the home layout as an overlay (`resources/extensions/home.json`,
  `main_content`, before the template's own home content) — no template files change.
  - Widget types in this release: **recent posts**, **popular posts** (This Week / This Month /
    1 Year tabs, switched in the browser without another request) and **news ticker** (rolls one
    line at a time, pauses on hover or focus, stays still with `prefers-reduced-motion`).
  - Default layout = the current home: section 1 two columns (recent · popular), section 2 one
    column (ticker on the `notice` board), sections 3–5 off. Default titles and icons are the
    same as the template widgets (Recent Posts · clock, Popular Posts · fire, Notice · bullhorn).
  - Settings key `home_layout` (plugin settings file). When it is absent the defaults above are
    used; nothing is written on update.
- **Admin screen** (`/admin/plugins/g7-home-widgets/settings`, same menu entry) rebuilt with tabs:
  **Widget Layout** (each section on/off and 1 or 2 columns; the common excluded boards editor
  from 0.3.0 at the bottom) and **Section 1–5** (per column: widget type, number of items, and
  boards — all boards or picked boards for recent/popular, a single board for the ticker; default
  period tab for popular posts). Saving goes through the core plugin settings endpoint; the plugin
  adds validation rules (a ticker with more than one board is rejected), converts the form into
  `home_layout` before saving, and clears the widget data cache and the cached bot home page after
  saving. New read endpoint `GET /api/plugins/g7-home-widgets/admin/home-form` (`core.plugins.read`).
- **Gallery** widget — recent posts of the chosen boards as thumbnail cards (1–20 items). The
  thumbnail is sirsoft-board's own list thumbnail (`PostResource`: image attachment first, then the
  first image in the body); posts without one get a placeholder box; secret posts never show a
  thumbnail. No image-delivery variants.
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

- Which boards a widget shows = the boards the viewer can read (decided by sirsoft-board) ∩ the
  widget's selection − the common excluded list (`excluded_board_ids`). Read permission,
  secret and deleted posts are still decided by sirsoft-board; popular posts come from the
  sirsoft-board popular cache (a narrow board selection can return fewer items than the limit).
  A missing board name on a popular post is filled from the readable-board list already loaded.
- The news ticker uses exactly one board and is **not** affected by the common excluded list
  (only read permission applies), so a notice board that is hidden from the other widgets still
  feeds the ticker.
- Widget data cache keys carry a generation number. Post create/update/delete/blind/restore,
  board updates and saving this plugin's settings bump it, so a bot page re-rendered right after
  a new post no longer shows the old list for the bot cache lifetime. Saving this plugin's
  settings also clears the cached bot home page.

### Unchanged

- The three widget APIs used by the wc-community template (`recent-posts`, `popular-posts`,
  `notice-posts`) and their responses. The template widgets and the new sections are both shown
  until the template is cleaned up.

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
