# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-09-17

### Added

- **Widget board settings** — an admin screen at `/admin/plugins/g7-home-widgets/settings`
  (sidebar: "홈 위젯 게시판 설정" / "Home Widget Boards") to choose which boards the
  **recent posts** and **popular posts** widgets leave out. The notice ticker is not affected
  (it already names its board).
  - Every existing board is shown as a card in one of two areas — **Included** and
    **Not included**. Move a card by dragging it between the areas or with its X / + button;
    a name filter narrows both areas. Inactive boards are shown with an "Inactive" badge.
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
