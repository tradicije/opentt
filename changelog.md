# Changelog

All notable changes to the LibreTT plugin are documented in this file.

## Unreleased

### Highlights

- Added standalone-ready bundled `LibreTT Tournaments` addon foundation with tournament CPT, contextual tournament shortcodes, single tournament template support, admin tournament/category/entry management, and first-pass single-elimination bracket generation.
- Added a full WordPress user portal for login, registration, profile editing, role-aware frontend tools, and `opentt_auth_menu`.
- Added frontend league/team administration flows, including league-season scoped access, inline match editing, game/set entry, and manager tools for club/player data.
- Added public pending game submissions with Turnstile protection, email collection, admin review, and approve/deny notifications.
- Added richer match presentation shortcodes and controls, including combined matches view, compact/spacious grid modes, match list navigation, featured match, match ID card, match teams short card, standings short card, featured player, club card, and club cover photo.
- Added searchable site discovery with categorized live search, typo/accent tolerance, trending clicks, history, popular entities, and latest result discovery.
- Added standings export/share workflow that generates polished 1080x1080 social images with league branding, table content, watermark support, and share/download actions.
- Added optional ELO ratings by league and season, with historical backfill, player profile display, and an admin toggle.

### Frontend Shortcodes & UX

- Added or expanded shortcodes: `opentt_matches`, `opentt_matches_list`, `opentt_match_id`, `opentt_standings_short`, `opentt_featured_player`, `opentt_club_card`, `opentt_club_featured`, and `opentt_match_teams_short`.
- Added `season`/`sezona`, `played`, `view`, `kolo`, `highlight`, and `author` support across match shortcodes where relevant.
- Improved `opentt_matches_grid` with grouped rounds, compact/spacious density switching, calendar filtering, clickable calendar previews, winner/loser emphasis, highlight support, and scoped updater attribution.
- Added new `opentt_matches_grid_alt` shortcode with full attribute compatibility to `opentt_matches_grid` and an alternate match-card layout (`score -> home ~ away -> sets`) without club crests.
- Improved `opentt_matches_list` with contextual league-season navigation, no-refresh round switching, mobile-friendly layout, highlight support including `highlight="auto"`, report/video indicators, and updater attribution.
- Improved match cards for played and upcoming matches so upcoming fixtures show kickoff time instead of placeholder `0:0` scores.
- Added clickable club navigation to mini standings rows and match team cards.
- Enhanced `opentt_standings_short` with a no-refresh toggle action: `Prikaži celu tabelu` expands full standings inline, and `Sakrij celu tabelu` restores the compact three-row view.
- Refined `opentt_standings_short` toggle behavior to avoid duplicate table rendering: the existing table now expands/collapses in place by revealing/hiding additional rows.
- Added a global single-club season switcher inside `opentt_club_card` (dropdown rendered below primary club info) that updates the page `?sezona=` parameter.
- Wired single-club season query fallback (`?sezona=`) into `opentt_matches_grid`/`opentt_matches_list` query building, `opentt_team_stats` season selection, and standings short/full resolution paths.
- Fixed single-club season-switch navigation conflicts by moving the dropdown URL key to `?opentt_sezona=` (with backward fallback support for legacy `?sezona=` reads).
- Fixed single-club season propagation so `opentt_players` now derives players from games played by that club in the selected season, `opentt_matches_list` explicitly respects `?opentt_sezona=`, and `opentt_club_form` filters recent form rows by the same selected season.
- Fixed `opentt_matches_list` `highlight="auto"` scoping when only season is present: for single-club pages it now resolves and pins the club's actual league for that selected season instead of mixing all leagues from the same season.
- Added refactor-stability groundwork docs: phased migration plan (`docs/refactor/PHASED_REFACTOR_PLAN.md`) and execution smoke checklist (`docs/refactor/SMOKE_CHECKLIST.md`), plus a local architecture audit script (`tools/refactor_audit.sh`) for repeatable monolith-size and module-split tracking.
- Extracted standings calculation helpers from the shortcode mega-trait into a dedicated service (`includes/class-opentt-unified-shortcode-standings-service.php`) and kept the trait as a thin delegator to reduce monolith complexity without changing shortcode output behavior.
- Extracted player history stint builder from the shortcode trait into `includes/class-opentt-unified-player-history-service.php`, keeping trait method signatures intact while reducing trait logic size.
- Extracted competition presentation helpers (`competition_display_name`, `season_display_name`, `competition_archive_url`, `kolo_name_from_slug`, `kolo_heading_label`) from the shortcode trait into `includes/class-opentt-unified-competition-presentation-service.php`; trait now delegates to the new service for lower coupling and easier staged migration.
- Extracted match presentation/time helpers (`match_venue_label`, `display_match_time`, `is_match_live`, `parse_match_timestamp`) from the shortcode trait into `includes/class-opentt-unified-match-presentation-service.php`, preserving existing behavior while shrinking trait responsibilities.
- Expanded `includes/class-opentt-unified-match-presentation-service.php` with URL/date presentation helpers (`match_permalink`, `display_match_date`, `display_match_date_long`) and switched shortcode trait implementations to thin delegation.
- Extracted entity rendering helpers (`render_team_html`, `render_lp2_player`, `render_klub_card_html`) into `includes/class-opentt-unified-entity-presentation-service.php` and delegated trait methods to reduce shortcode-trait size and coupling.
- Extracted match context/template helpers (`current_match_context`, `get_template_match_context`, `get_match_block_template`) into `includes/class-opentt-unified-match-context-service.php`; shortcode trait now delegates these responsibilities to the service layer.
- Extracted media/logo/fallback helpers (`club_fallback_image_url`, `player_fallback_image_url`, `club_logo_url`, `resolve_club_id_from_value`, `club_logo_html`) into `includes/class-opentt-unified-media-service.php` and replaced trait implementations with delegators.
- Extracted grid rendering logic (`render_matches_grid_html`, `render_clubs_grid_html`) into `includes/class-opentt-unified-grid-render-service.php`, keeping shortcode trait as an orchestration/delegation layer.
- Extracted match shortcode query-argument building (`build_match_query_args`, `normalize_played_shortcode_attr`) into `includes/class-opentt-unified-shortcode-match-query-service.php` with trait-level delegation and dependency callbacks, preserving archive-context and legacy league-season parsing behavior while reducing trait monolith size.
- Extracted shortcode UI helpers into `includes/class-opentt-unified-shortcode-ui-service.php` (`shortcode_title_html`, `info_link_icon_html`, `render_top_player_card_list`) and switched trait methods to delegation; this further shrinks the shortcode trait while keeping existing shortcode rendering behavior and icon-loading logic.
- Extracted user-portal auth/profile flows into `src/WordPress/UserPortalAuthService.php` (`renderAuthShortcode`, `renderAuthMenuShortcode`, `renderProfileShortcode`, `handleFrontRegister`, `handleFrontProfileUpdate`) and converted `UserPortalManager` methods to delegators, reducing `UserPortalManager` from ~2021 to ~1808 LOC without functional changes.
- Extracted league match submit handlers into `src/WordPress/UserPortalLeagueMatchService.php` (`handleFrontSaveLeagueMatch`, `handleFrontSaveLeagueGames`, `handleFrontAddLeagueMatch`) and switched `UserPortalManager` to callback-based delegation; `UserPortalManager` reduced further from ~1808 to ~1664 LOC with no behavior change.

### Admin & Data Entry

- Added admin `Batch unos utakmica` workflow for fast round-based match entry: select competition, round, default date/time, auto-suggest match count from league team count, and expand per-match advanced options without page refresh.
- Reworked admin match editing into `Osnovno` and `Partije` tabs while preserving existing game/set entry logic.
- Added inline `Quick edit` for match rows with score, date, time, and location editing directly under the selected match.
- Added match `ID` column in admin match lists for easier shortcode/reference use.
- Added club cover photo management in both admin club edit and frontend team-manager tools, including focus positioning and upload guidance.
- Added player-to-WordPress-user mapping so update attribution can link to the correct player/author profile.
- Added Mailgun settings and test-email support for automated review notifications.

### User Portal & Permissions

- Added LibreTT roles and role-aware frontend areas for members, editors, league administrators, and team managers.
- Added `Korisnici` admin screen for linking WordPress users to players and assigning league-season or team management access.
- Added frontend editor tools for creating posts with featured image support and a user news overview.
- Added frontend league-admin tools with season-first and league-specific navigation, filters, search, match cards, and inline game/set controls.
- Added profile avatar handling with uploaded avatar, WordPress/Gravatar fallback, and plugin fallback image.

### Search & Discovery

- Added `opentt_search` overlay search with desktop/mobile layouts, categorized results, entity images, and context-aware discovery.
- Added smart query parser v1 for intent `klub + poslednjih N`, including quick suggestion chips and focused result blocks (club summary + recent match list).
- Expanded smart parser coverage with additional sports intents (next/last match, H2H with limits, round queries, home/away filters, date ranges, standings prompts, player stats, top players, live/today/upcoming, and location-based match queries).
- Added search history with clear-history support and recent-trending logic based on clicked results rather than raw typed queries.
- Added anti-abuse caps for trending clicks and improved Serbian pluralization for click labels.
- Added accent-insensitive and typo-tolerant matching with "Da li ste mislili..." suggestions.
- Improved discovery fallbacks so trending, latest results, popular players, and popular clubs remain available outside strict page context.

### Standings, Sharing & Branding

- Added standings table watermark settings with enable/disable toggle and custom media-library image selection.
- Added social image export for standings using `html2canvas`, with league/season/round header, competition logo, club branding, promotion cutoff line, table watermark, and footer attribution.
- Fixed social export rendering for encoded club names and zero-value table cells.
- Added explicit README project-identity disclaimer for branding clarity.

### Match Reports, Video & Content Links

- Reworked match report selection to use searchable WordPress posts instead of raw URL entry.
- Updated match report shortcode to render linked post preview content instead of a plain button.
- Updated match video shortcode to embed YouTube/video URLs where available and show clear empty states when missing.

### Fixes & Polish

- Fixed contextual league-season filtering for match grids/lists so archives and explicit shortcode attributes do not mix seasons.
- Fixed old `odigrana` filtering by introducing `played="true|false"` as the public attribute while keeping backward compatibility.
- Fixed `opentt_matches_grid` `limit` handling so valid rows fill the requested visible count.
- Fixed raw HTML entity rendering in club names across match lists and search/match cards.
- Fixed live match display to use manual live state instead of unreliable automatic time detection.
- Improved mobile layouts for filters, competition info, auth, match lists, match cards, and profile/admin portal screens.
- Fixed `opentt_matches_grid_alt` rendering stability by moving card transformation to server-side PHP output shaping instead of frontend JS-only replacement.
- Fixed `opentt_matches_grid_alt` text encoding normalization for club names (mojibake like `LeÅ¡ak`) and updated top-right date output to `dd. Month` format (e.g. `02. Januar`).
- Hardened `opentt_matches_grid_alt` mojibake recovery for double-broken UTF-8 club names (e.g. `LeÅÅ¡ak`) and added date fallback from `data-match-date` when `data-match-date-display` is missing, keeping `dd. Month` output.
- Extended `opentt_matches_grid_alt` charset recovery mapping to cover all Serbian Latin diacritics (`š`, `ž`, `ć`, `č`, `đ` and uppercase variants) across single and double mojibake forms.

### Engineering

- Continued gradual extraction from the legacy monolith into PSR-4 classes under `src/`.
- Added dedicated shortcode classes and kept the legacy shortcode trait as a compatibility/delegation layer.
- Added scoped match update tracking by `liga + sezona` for accurate frontend attribution.
- Added legacy JSON export conversion tooling for old `stkb_*` data packages.
- Removed the experimental AI chatbot feature completely after evaluation.

### Documentation & Project Policy

- Added comprehensive bilingual operations documentation: `docs/PLUGIN_DOCUMENTATION_EN.md` and `docs/PLUGIN_DOCUMENTATION_SR.md`, covering installation, onboarding, data model, shortcode catalog, import/export, migrations, templates, localization, security, and troubleshooting.
- Expanded license sections in `readme.md`, `readme-sr.md`, and both detailed documentation files with explicit scope clarifications and references to trademark/brand policy.
- Added trademark and brand usage policies in both languages: `trademark.md` and `trademark-sr.md`.
- Added long-form project vision documents in both languages: `vision.md` and `vision-sr.md`.
- Expanded vision scope with `stoni.rs` public commons direction and explicit infrastructure sustainability framing, including:
  - distinction between centralized `stoni.rs` infrastructure costs and decentralized LibreTT self-host usage,
  - sustainability models (donations, grants, sponsors, nonprofits, mirrors),
  - rationale for continued self-host deployments even with national-scale central coverage (amateur leagues, local tournaments, school competitions, regional federations, private analytics, experimentation, backup/mirror resilience).
- Rebranded admin-facing UI copy from `OpenTT` to `LibreTT` across dashboard/onboarding texts, admin notices, import validation messages, role labels, and admin translation dictionaries, while keeping technical identifiers (`opentt_*`, class names, shortcode tags) unchanged for compatibility.
- Fixed remaining admin EN localization gaps for short Serbian labels/actions (`Uredi`, `Obriši`, `Uživo`, `Pending partije`, quick-delete confirmations) by extending safe-key translation handling and completing SR→EN bridge mappings for live/pending tabs and related messages.

## Releases

### 1.1.0 - 2026-03-04

#### Highlights

- Finalized frontend shortcode UX polish for players list expansion and calendar-assisted match discovery in `opentt_matches_grid`.

#### Assets & UI

- Updated `opentt_players` to render the first 5 player cards by default and added a bottom toggle button (`Prikaži sve` / `Sakrij`) for expanding the full list.
- Fixed shortcode dropdown/toggle form controls to inherit the active user/theme font instead of browser default (applied in both `assets/css/main.css` and `assets/css/modules/legacy-ui.css`).
- Enhanced `opentt_matches_grid` calendar hover behavior with per-day match preview rows (`HOME | SCORE | AWAY`) and compact club naming (`BUB` for single-word names, `TSK` initials for multi-word names).
- Added direct navigation from `opentt_matches_grid` calendar hover preview: each match row is now a clickable link to that match page.
- Fixed round label rendering across frontend shortcodes so `kolo` slugs like `11-kolo` are displayed as `11. kolo`.

### 1.1.0-beta.3 - 2026-03-04

#### Highlights

- Release focused on completing shortcode architecture extraction and finalizing naming/UI consistency across admin, filters, and import/export UX.
- Unified shortcode trait is now a delegating layer, with all shortcode implementations moved into dedicated PSR-4 classes.

#### Engineering

- Completed remaining `stkb` to `opentt` identifier normalization across shortcode filter/query keys and internal admin/UI identifiers.
- Continued core service extraction by moving admin settings/onboarding actions, import/export actions, schema migration orchestration, and import payload inspection into dedicated PSR-4 service classes (`src/WordPress/*`, `src/Infrastructure/*`), with `OpenTT_Unified_Core` kept as a delegating layer.
- Removed redundant core wrappers (notice URL and ID/date parsing) and switched related call sites to direct helper/service usage.
- Completed shortcode architecture migration: all shortcode implementations are now extracted from `includes/modules/trait-opentt-unified-shortcodes.php` into dedicated `src/WordPress/Shortcodes/*` classes.
- Shortcodes now extracted include match views (`matches_grid`, `matches_list`, `match_games`, `match_report`, `match_video`, `show_match_teams`), club/player content (`clubs`, `show_players`, `club_news`, `player_news`, `related_posts`, `club_info`, `player_info`, `club_form`, `player_transfers`), rankings/stats (`standings_table`, `top_players_list`, `mvp`, `player_stats`, `team_stats`), and competition views (`competition_info`, `competitions_grid`, `h2h`).

#### Assets & UI

- Renamed frontend CSS override style handle namespace from `stkb-unified-*` to `opentt-unified-*`.
- Updated admin JS initialization dataset/data flags from `stkb*` to `opentt*` keys.
- Switched admin branding logo source (topbar + onboarding) from root `librett-logo.png` to `assets/img/admin-ui-logo.png` without changing frontend/readme logo usage.
- Enhanced `opentt_matches_grid` (`filter=true`) with a right-aligned popup calendar date filter (`opentt_match_date`) that highlights match days by status: played (green tint) and upcoming (blue tint).
- Updated admin Settings shortcode catalog/help for `opentt_matches_grid` to document calendar behavior and the optional `opentt_match_date` attribute.
- Aligned the matches-grid calendar toggle visual style with existing filter controls and switched its icon to `assets/icons/calendar.svg` rendered in white.
- Simplified the matches-grid calendar trigger to icon-only and switched calendar month/day labels to English.

#### Tooling

- Added standalone legacy export converter CLI app: `tools/convert-stkb-export.php` for transforming older `stkb_*` JSON packages (format/meta/section/table/key names) into LibreTT-compatible import JSON.

#### Import/Export

- Improved import upload error messaging for PHP upload limits (`UPLOAD_ERR_INI_SIZE` / code `1` and form size / code `2`) by showing file size and current `upload_max_filesize` / `post_max_size` values.

#### Localization

- Updated admin UI translation dictionaries in `languages/` to align key strings with the new `opentt-*`/`opentt_*` markup and action/query identifiers.

#### Fixes

- Fixed `opentt_matches_grid` contextual league-season filtering so league archives now respect the active `sezona` context instead of aggregating matches from all seasons of the same league.
- Updated `opentt_match_teams` center display logic to show scheduled match time (for example `19h`) instead of `0:0` for not-yet-played matches, using backend `played`, match date, and score fallback checks.

### 1.1.0 - 2026-03-03

#### Highlights

- Major Phase 2 refactor release focused on splitting monolithic core responsibilities into PSR-4 services, while preserving existing runtime behavior and public API contracts.
- Core architecture is now significantly more modular across bootstrap, onboarding, settings, admin workflows, migration flows, diagnostics, competition rule handling, and frontend assets pipeline.
- Public integration surfaces remain stable (`opentt_*` shortcodes, admin actions, option/meta namespaces, DB compatibility behavior).

#### Engineering

- Added refactor compatibility contract documentation: `docs/refactor/API_CONTRACT.md`.
- Introduced Composer PSR-4 foundation and namespaced plugin bootstrap with safe non-Composer autoload fallback.
- Extracted infrastructure services for DB table resolution, admin UI translation, and visual settings CSS/settings domain.
- Extracted WordPress service layer for legacy content and shortcode registration, onboarding/rewrite lifecycle, settings and notices, league/season and competition admin flows, migration and maintenance actions, and competition rule storage/catalog/profile/query helpers.

#### Assets & UI

- Migrated frontend/admin selector namespace to `opentt-*` and aligned JS bindings.
- Standardized key admin JS microcopy to English.

#### Documentation

- Updated version references to `1.1.0` in README files.
- Standardized AGPL file headers across PHP sources.

### 1.1.0-beta.1 - 2026-03-03

#### Highlights

- Full naming standardization to `opentt` across public and internal surfaces.
- Shortcodes are now fully English and use only `opentt_*` tags.
- DB layer now uses canonical `opentt_*` tables with built-in legacy fallback.
- Core was further modularized to reduce monolith size and simplify maintenance.

#### Breaking Changes

- Removed runtime support for old shortcode tags.
- Renamed PHP source files from `stkb` prefix to `opentt` prefix.
- Renamed internal classes from `STKB_Unified_*` to `OpenTT_Unified_*`.
- Renamed internal hooks/nonces/actions/options/transients from `stkb_unified_*` to `opentt_unified_*`.
- Renamed and translated competition meta keys from `stkb_pravila_*` to `opentt_competition_*`.
- DB canonical table names are now `opentt_matches`, `opentt_games`, `opentt_sets`.

#### Migration & Compatibility

- Added shortcode content migration script: `migrations/2026-03-03-shortcode-tags-to-opentt.sql`.
- Added internal key migration script: `migrations/2026-03-03-internal-keys-to-opentt.sql`.
- Added physical DB table migration script: `migrations/2026-03-03-db-tables-stkb-to-opentt.sql`.
- Added runtime DB table resolver (`OpenTT_Unified_Core::db_table`) with legacy fallback.
- Added automatic legacy row sync from `stkb_*` tables to `opentt_*` tables during bootstrap/schema migration.

#### Documentation

- Updated `readme.md` and `readme-sr.md` shortcode examples and branding.
- Added centered logo and removed redundant top-level heading in both README files.

### 1.0.1 - 2026-03-02

#### Highlights

- Refactor release focused on reducing `OpenTT_Unified_Core` size without behavior changes.

#### Changed

- Extracted shared read-only helpers into `class-opentt-unified-readonly-helpers.php`.
- Extracted admin read-only helper layer into `class-opentt-unified-admin-readonly-helpers.php`.
- Preserved compatibility via delegating wrappers in existing core/trait methods.

### 1.0.0 - 2026-03-01

#### Highlights

- First public LibreTT release baseline.

#### Changed

- Standardized public package metadata and license fields (AGPL).
- Finalized public docs in English and cleaned internal-only tooling references.
- Added local fallback assets for player/club visuals.
- Added admin UI language switching and file-based translation pipeline.

### 0.9.2 - 2026-02-26

#### Added

- Club fallback image support using local asset.
- Import validation merge preview for potential duplicate players.
- Bulk delete with shift-range selection for Players, Clubs, and Matches.

#### Changed

- Improved match import reliability for DB writes.
- Added diagnostics/repair flow for `played` consistency.
- Improved filtering/sorting UX in Players admin.

### 0.9.1 - 2026-02-25

#### Added

- Competition diagnostics panel in Import/Export.
- Competition-level reset action for matches/games/sets.

#### Changed

- Better import conflict handling for player merge decisions.
- Hardening of import token/validation flow.
- Routing fallback stability improvements for archive/taxonomy contexts.

### 0.9.0 - 2026-02-24

#### Added

- New Settings tab modules: shortcode catalog, CSS override panels, first-time setup previews.

#### Changed

- Admin UX improvements for non-technical operators.
- Documentation and setup guidance updates.
- Continued legacy-module unification into LibreTT core architecture.

## Notes

- Internal class/function names are standardized to the `OpenTT_*` scheme.
- Changelog heading format: `X.Y.Z(-tag) - YYYY-MM-DD`.
- This changelog is maintained in English for public release workflow.
