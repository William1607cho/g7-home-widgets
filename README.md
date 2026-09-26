# g7-home-widgets

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)

A [Gnuboard7](https://sir.kr/) plugin that provides JSON data sources for home-page
widgets built on `sirsoft-board` — a **site-wide recent posts** feed, a
**site-wide popular posts** feed (both with the post's **category** included), and a
**notice ticker** feed for a single board.

`sirsoft-board` itself is not modified. The plugin queries the core `Post` / `Board`
models from its own API routes and applies per-board read permissions at response
time, so it can add fields (such as `category`) that the core home APIs do not return.

## Features

- **Recent posts** — `GET /api/plugins/g7-home-widgets/recent-posts?limit=10`
  - Posts from all active boards merged in `created_at` order (per-board `UNION ALL`
    subqueries, reusing the core `idx_board_posts_board_status_created` index).
  - Fields: `id`, `board_slug`, `board_name`, `title`, `category`, `author_name`,
    `created_at`, `created_at_formatted`, `view_count`, `comment_count`,
    `is_secret`, `is_new`.
  - `limit`: default 10, clamped to 20.
- **Popular posts** — `GET /api/plugins/g7-home-widgets/popular-posts?period=week&limit=10`
  - Same filters and ordering as the core `sirsoft-board` popular-posts endpoint
    (`boards/popular`): active boards only, published, not deleted, not a reply,
    not secret, within the period; ordered by views desc, then comments desc.
  - `period`: `today` | `week` | `month` | `year` (default `week`; `all` and
    unknown values resolve to `year`, same as core).
  - `limit`: default 10, clamped to 50.
  - Fields: `id`, `board_slug`, `board_name`, `title`, `category`, `view_count`,
    `comment_count`, `created_at`, `created_at_formatted`.
  - **No author fields** — author name, e-mail and avatar are never queried.
- **Notice ticker** (0.2.0+) — `GET /api/plugins/g7-home-widgets/notice-posts?board=notice&limit=5`
  - Latest posts of one board: active board, published, not deleted, not a reply,
    **not secret**; ordered by `created_at` desc.
  - `board` (required): board slug. `limit`: default 5, clamped to 10.
  - Fields: `id`, `board_slug`, `title`, `created_at`, `created_at_formatted` — **no author fields**.
  - Missing board, invalid slug or no read permission → `data: []` (never an error).
- **Widget board settings** (0.3.0+) — **Admin → 홈 화면 설정 / Home Page Settings** (named
  "홈 위젯 게시판 설정 / Home Widget Boards" before 0.4.0)
  (`/admin/plugins/g7-home-widgets/settings`)
  - Choose which boards the recent and popular posts widgets leave out. The notice ticker is
    not affected.
  - All boards are shown as cards in **Included** / **Not included**. Drag a card between
    the areas, or use its X / + button (always visible, so it works on touch screens too).
    A name filter narrows both areas; inactive boards carry an "Inactive" badge.
  - Dragging uses a small script loaded by the settings layout
    (`/api/plugins/g7-home-widgets/board-filter-drag.js`). Layout JSON action handlers cancel
    `dragstart`, so the script starts the drag and, on drop, presses the card's X / + button.
    It sends no requests and does not touch app state.
  - Only the IDs in **Not included** are saved, so new boards are included automatically and
    IDs of deleted boards are dropped on the next save.
  - **This only controls visibility and does not change read permissions.** Per-board read
    permission is still checked for every request.
  - Excluded boards are removed in the query, so `limit` is still filled.
  - Admin API: `GET` / `PUT /api/plugins/g7-home-widgets/admin/board-filter`
    (`core.plugins.read` / `core.plugins.update`), body `{ "excluded_board_ids": [1, 2] }`.
- **Permission-safe caching** (all endpoints)
  - A user-independent "safe pool" is cached for 90 seconds (the popular pool's
    cache key includes the period; the notice pool's key includes `board` and `limit`).
  - `sirsoft-board.{slug}.posts.read` is checked per request for the current
    user (guest or member), so a privileged user's result never leaks to others.
  - The pool is larger than `limit` (at least 50), so the list stays full after
    permission filtering.
  - The recent/popular pool keys also include a fingerprint of the excluded-board list;
    saving the settings clears the pools cached under the previous list.

## Requirements

- Gnuboard7 `>= 7.0.10`
- `sirsoft-board` module `>= 1.1.2`

## Installation

```bash
# place this repository at plugins/g7-home-widgets, then:
php artisan plugin:install g7-home-widgets
php artisan plugin:activate g7-home-widgets
```

Or install and activate it from **Admin → Plugins**.

If the settings screen does not appear after updating from an earlier version, refresh the
plugin layouts: `php artisan plugin:refresh-layout g7-home-widgets`.

If the routes do not appear and your site caches routes, clear and rebuild the cache:

```bash
php artisan plugin:cache-clear
php artisan route:clear && php artisan route:cache
```

## Using it in a template

The plugin only provides data — wire it into your user template's layout JSON as a
data source. Send the user's token when present (`auth_mode: "optional"`) so members
see boards that guests cannot:

```json
{
  "id": "home_popular_posts",
  "type": "api",
  "endpoint": "/api/plugins/g7-home-widgets/popular-posts",
  "method": "GET",
  "params": { "period": "{{_local.homePopularPeriod ?? 'week'}}", "limit": 10 },
  "auto_fetch": true,
  "auth_mode": "optional",
  "fallback": { "data": [] }
}
```

For the notice ticker, point a data source at `notice-posts` with your notice board's slug —
the `g7-wc-community` template renders it with its `NoticeTicker` component:

```json
{
  "id": "home_notice_posts",
  "type": "api",
  "endpoint": "/api/plugins/g7-home-widgets/notice-posts",
  "method": "GET",
  "params": { "board": "notice", "limit": 5 },
  "auto_fetch": true,
  "auth_mode": "optional",
  "fallback": { "data": [] }
}
```

To switch the popular-posts period without leaving the page, set the local state and refetch:
`sequence[ setState(local, homePopularPeriod) → refetchDataSource(home_popular_posts) ]`.

## Uninstalling

The plugin has no database tables or hook listeners; its only setting
(`excluded_board_ids`) is stored in the plugin settings file. Deactivate and uninstall it
from **Admin → Plugins** (or `php artisan plugin:uninstall g7-home-widgets`), and
remove any data sources that point at its endpoints from your templates.

## License

MIT — see [LICENSE](./LICENSE).
