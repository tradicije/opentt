# LibreTT Tournaments

Bundled tournament engine addon for LibreTT.

This folder is intentionally structured as a standalone-ready WordPress plugin. While it is currently loaded by LibreTT, it has its own bootstrap, schema version, assets, templates, admin screens, and shortcode registration.

## Shortcodes

- `[opentt_tournaments]` - tournament list.
- `[opentt_tournament]` - contextual tournament renderer on single tournament pages, or explicit renderer with `id`/`slug`.
- `[opentt_tournament_categories]` - category pills for the current tournament.
- `[opentt_tournament_signup]` - registration placeholder for a tournament.
- `[opentt_tournament_podium]` - podium/final placement placeholder.

## Template

Create a Site Editor template named `opentt-tournament` to control single tournament pages. Add `[opentt_tournament]` inside it for contextual rendering.

