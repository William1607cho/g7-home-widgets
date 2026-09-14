# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
