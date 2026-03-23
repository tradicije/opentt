# Changelog

All notable changes to the OpenTT plugin are documented in this file.

## Unreleased

### Next

#### Assets & UI

- Reverted visible plugin branding back to `OpenTT` (plugin header metadata, admin UI labels, onboarding copy, import messaging, and README text), while keeping existing technical identifiers unchanged.
- Added an explicit project disclaimer in README files clarifying that OpenTT is not affiliated with `opentt.pl`.
- Added new `opentt_matches` combined shortcode (grid/list switcher): default grid view, dropdown toggle with grid/list icons, list mode without grid filters, and `filter=true` mode using incremental `Prikaži još` loading (no infinite-scroll takeover).
- Added `season` shortcode attribute support (season slug, for example `2025-26`) across `opentt_matches`, `opentt_matches_grid`, and `opentt_matches_list`, with `sezona` kept as a backward-compatible alias.
- Fixed non-filtered `opentt_matches_grid` limit consistency by filling the requested card count from valid match rows (skipping incomplete rows without both clubs), so `limit=6` renders six visible cards when data is available.
- Updated match shortcodes to support `played="true|false"` as the primary public filter attribute (with robust parsing), while keeping `odigrana` as a backward-compatible alias.
- Added new `opentt_matches_list` shortcode with contextual league/season round navigation (chevrons, no page refresh), per-round match rows, row-to-match linking, and optional report/video indicators when related content exists.
- Fixed `opentt_matches_list` score-line alignment so the center separator (`:`) and scores stay visually centered regardless of team-name length.
- Fixed `opentt_matches_list` score rendering and styling parity: `0` scores now render correctly, and winner/loser emphasis now matches `opentt_matches_grid` (winner bold, loser reduced opacity for both name and score).
- Updated `opentt_matches_list` unplayed rows to hide placeholder `0:0` scores and show kickoff time in the center block instead, with tighter team spacing around the middle label.
- Refined `opentt_matches_list` unplayed center label styling to keep the same horizontal spacing as played-score rows and match kickoff-time font size with score typography.
- Fixed `opentt_matches_list` club-name rendering for encoded dash characters so names containing `-`/en dash no longer appear as raw HTML entities (for example `&#8211;`).
- Hardened `opentt_matches_list` club-name entity decoding to handle doubly-encoded legacy titles (for example `&amp;#8211;`) so dash characters render correctly in list rows.
- Updated `opentt_matches_grid` calendar preview so the `+X more` indicator is clickable and applies direct date filtering for that day.
- Improved `opentt_matches_grid` calendar preview positioning to reduce hover gap between day cell and preview panel, preventing accidental preview switches to adjacent days.
- Added new `opentt_featured_match` shortcode with standout card layout (league/season/round meta, team crests/names, countdown center, location footer) and home/away gradient based on club jersey colors.
- Extended `opentt_featured_match` with `mode` selector: `manual` (admin featured flag) and `auto` (context-aware league/season selection of nearest upcoming match with derby tie-break based on standings rank).
- Updated club admin form (`boja_dresa`) to use WordPress color picker for reliable color input used by featured-match gradients.
- Fixed `opentt_featured_match` auto mode SQL filtering order so league+season contextual lookup returns upcoming matches correctly.
- Ensured featured-match CSS module is enqueued on frontend by adding `featured-match` to the module asset loader list.
- Updated `opentt_featured_match` auto mode to support legacy matches without kickoff time by filtering by date and treating `00:00:00` entries as end-of-day for upcoming selection.
- Refined `opentt_featured_match` auto mode selection: it now ignores matches considered played (`played=1` or score not `0:0`), prioritizes upcoming matches with explicit kickoff time, and falls back to date-only matches when needed.
- Aligned `opentt_featured_match` auto context detection with `opentt_matches_grid` by reusing the same match query context builder (`build_match_query_args`) for league/season resolution.
- Enqueued WordPress color picker assets across OpenTT admin pages so club `boja_dresa` consistently renders as a visual color picker (not plain HEX input).
- Updated featured-match location resolution to use match-level location first and fall back to home-club location when match location is empty.
- Fixed `opentt_featured_match` card gradient edge consistency by switching to horizontal gradient direction so left/right accent lines no longer appear color-inverted.
- Refined `opentt_featured_match` visual styling for stronger desktop presentation (enhanced spacing, crest sizing, center countdown panel, hover polish) and improved mobile layout to keep teams/countdown in a compact three-column row instead of stacked blocks.
- Fixed persistent featured-card side accent inversion by enforcing explicit inset edge accents (`home` on left, `away` on right) and simplifying overlay glow.
- Corrected inset shadow direction on `opentt_featured_match` so side accents truly render as home-left and away-right.
- Reworked featured-card side accents to explicit pseudo-element bars (`::before` home-left, `::after` away-right), removing inset-shadow accents for deterministic left/right color rendering.
- Removed duplicated featured-match style blocks from `main.css` and `legacy-ui.css` so `featured-match.css` is the single source of truth, preventing cross-file overrides.
- Updated featured-match card to use uninterrupted edge-to-edge gradient (without solid side bars) while retaining subtle top highlight overlay.
- Added center helper label in `opentt_featured_match` above countdown (`Početak utakmice za:`; `Rezultat` for played matches) for clearer context.
- Redesigned `opentt_matches_grid` cards to a two-row team layout with a right-side date/time status panel (removed third meta row), including conditional behavior: played matches show `Datum + Kraj`, upcoming `0:0` matches hide scores and show `Datum + Vreme`.
- Applied the same two-row card pattern to `opentt_h2h` (team rows + right-side date/status panel), including conditional score hiding for upcoming `0:0` matches and `Kraj` status for played matches.
- Added round-grouped rendering to `opentt_matches_grid`: matches are now grouped with per-round subheadings (`kolo`) that stay in sync with active filters/sorting/infinite loading.
- Hardened round heading labels in `opentt_matches_grid` with numeric fallback (`N. kolo`) and higher-contrast badge styling to ensure subheadings remain visible across all layouts.
- Updated `opentt_ekipe` center block for unplayed matches to use a live countdown (`Početak utakmice za:`) instead of static kickoff time, reusing featured-match countdown behavior with safe fallback.
- Added a new LIVE mode flow for matches after kickoff time expires: red blinking `LIVE` badges across key match shortcodes (`opentt_matches_grid`, `opentt_h2h`, `opentt_ekipe`, `opentt_featured_match`) and a new admin `Uživo` page listing active live matches with quick links for score updates and game entry.
- Updated `opentt_ekipe` LIVE state center display to show score + badge in one row (`home_score LIVE away_score`) while a match is in live mode.
- Fixed frontend match time drift by switching shortcode LIVE/countdown timestamp parsing from raw `strtotime` to WordPress-timezone-aware parsing (`wp_timezone`) across `opentt_ekipe`, `opentt_h2h`, `opentt_matches_grid`, and `opentt_featured_match`.
- Reverted temporary timezone fallback override and restored shortcode timing to rely strictly on WordPress `General Settings > Timezone`.
- Fixed LIVE-mode mismatch between frontend and admin `Uživo` page for legacy non-padded hour values (for example `6:50:00`): match-date save now normalizes to `Y-m-d H:i:s`, shortcode parsers accept both padded/non-padded hour formats, and admin LIVE query uses parsed datetime comparison instead of raw string ordering.
- Hardened LIVE parser behavior across shortcodes by removing permissive `strtotime` fallback from match timestamp detection, preventing false early LIVE states caused by ambiguous date-string interpretation.
- Fixed nondeterministic match-context selection by adding explicit ordering (`updated_at`/`created_at`, then `id DESC`) in `db_get_match_by_legacy_id` and `db_get_match_by_keys`, so frontend shortcodes consistently use the latest match row when historical duplicates exist.
- Switched LIVE workflow to fully manual control: added `live` match flag (schema/import-export), manual LIVE toggle in matches list and match edit form, `Uživo` tab now lists only manually flagged matches, and each LIVE row now has `Završi utakmicu` action to exit LIVE mode explicitly.
- Refined LIVE card visuals across frontend shortcodes: LIVE cards now use a synchronized red-tint pulse on the whole card, while the `LIVE` badge switches to white text-only pulse (no badge background) for cleaner contrast.
- Increased LIVE pulse visibility on full cards (brightness/saturation + border/shadow pulse), keeping synchronized timing with text-only white `LIVE` badge animation.
- Improved mobile `opentt_prikaz_ekipa` LIVE layout: center block now stacks into 3 rows (`home score`, `LIVE`, `away score`) instead of a single horizontal row.
- Added a small animated dot indicator before `LIVE` text inside the badge (synchronized pulse) for clearer live-match affordance across frontend card variants.
- Updated `opentt_featured_match` auto mode to prioritize manually flagged LIVE matches (`live=1`) in active context; when multiple LIVE matches exist, selection now prefers derby quality (better combined standings ranks) with kickoff proximity tie-break.
- Simplified LIVE center content in `opentt_featured_match` by removing redundant `Uživo` helper label above the LIVE badge.
- Restored score visibility in `opentt_featured_match` LIVE state by rendering center row as `home_score LIVE away_score` (with synchronized dot+text LIVE indicator).
- Polished `opentt_featured_match` LIVE center sizing: larger desktop score/indicator presence, plus mobile-specific balance tweak (slightly smaller LIVE indicator, slightly larger scores, and increased score-group spacing/padding).
- Updated `opentt_featured_match` LIVE center layout: `LIVE` indicator now sits above the score group, while scores are rendered on a separate line as `home : away` (with stronger desktop emphasis).
- Refactored featured LIVE center markup to separate indicator and score containers (`LIVE` as sibling above `opentt-featured-center`), ensuring vertical stack consistency.

#### Admin & Data

- Added `featured` match flag to match schema and import/export payloads.
- Added featured controls in admin matches workflow: quick list toggle action (`Feature/Unfeature`), featured indicator column, and featured checkbox in match edit details.
- Bumped schema version to force migration and added runtime fallback for auto-adding missing `featured` column when older installs hit admin featured actions.
- Added dedicated match `location` field in admin match form and persistence layer, and switched featured/match venue rendering to prefer this match-level location over club address fallbacks.
- Added admin helper note on match `Lokacija` field clarifying it should be overridden only when match is not played at the home venue.
- Updated match completion semantics to best-of-4 (`played=1` only when either side reaches 4), so live-mode matches remain editable until final result is reached.
- Added match-level `report_url` and `video_url` fields in match add/edit admin form, plus schema/import-export support, and migrated `opentt_match_report`, `opentt_match_video`, and `opentt_matches_list` media indicators to contextual DB-driven links (with legacy fallback for older content).
- Added empty-state messages for contextual match media shortcodes: `opentt_match_report` now shows `Nema izveštaja za ovu utakmicu.` and `opentt_match_video` shows `Nema snimka za ovu utakmicu.` when links are missing.
- Refined contextual media rendering: `opentt_match_report` now resolves and displays linked news card content (featured image + post title), while `opentt_match_video` now embeds match video directly on frontend with added YouTube fallback embed parsing.
- Replaced match-admin report URL input with a searchable dropdown picker of local site blog posts; save flow now stores the selected post permalink as match report link.
- Added ELO rating foundation for players (`1500` default, `K=32`) with automatic update on newly added single matches that have a winner, and added a small ELO badge overlay on player images in key player list/ranking card views.
- Updated ELO model to be competition-scoped (`liga + sezona`) so each league-season pair has its own rating track per player, with contextual ELO rendering in `opentt_player_info` (current scope + per-scope list).
- Fixed ELO badge positioning to render outside player images in the top-right corner (overlay style) instead of inside clipped avatar areas.
- Added one-time historical ELO backfill on plugin init: existing single games are replayed chronologically to populate scoped (`liga+sezona`) player ratings from already entered data.
- Adjusted ELO visibility to player profile context only (`opentt_player_info`), removing ELO badges from ranking/list cards.
- Improved player-profile ELO fallback resolution: when page context has no explicit league/season, profile now uses the player’s latest available competition scope instead of defaulting to `1500`.
- Added admin Settings toggle for ELO system (enable/disable): when disabled, ELO updates/backfill are skipped and player-profile ELO display is hidden.
- Added quick score edit workflow in admin `Utakmice` list (`Quick rezultat`) so home/away score can be updated directly from the list page without opening full match edit.
- Changed default ELO setting to disabled (`OFF`) for fresh installs unless explicitly enabled in Settings.
- Added new global personalization color `Boja zaglavlja tabela` and wired all frontend table-header surfaces (`thead` tint previously hardcoded as `rgba(8, 30, 82, 0.32)`) to this setting.
- Improved `opentt_matches_grid` round grouping when club filtering is active: rounds now respect the configured `columns` layout by rendering each round as its own grid cell with matches stacked underneath, instead of forcing a single full-width round list.
- Added `opentt_matches_list` to the admin shortcode catalog with documented `liga`, `sezona`/`season`, `played`, `kolo`, and `highlight` attributes.
- Improved `opentt_matches_list` mobile layout by giving rows more breathing room, better stacked alignment, larger central score/time typography, and more readable wrapped team names on small screens.
- Added `highlight` support to `opentt_matches_list`: passing a club ID, slug, or name now visually emphasizes rows where that club appears.
- Fixed match query compatibility for explicit `liga + season` shortcode filters by adding fallback support for legacy rows where `liga_slug` was historically stored as a combined value (for example `kvalitetna-liga-2025-26`).
- Added additional `opentt_matches_list` runtime fallback for homepage/non-context usage: when explicit `liga + season` yields no rows, shortcode now retries legacy combined league-season slug patterns and normalized season formats (`YYYY-YY` vs `YYYY-YYYY`) before returning empty output.
- Hardened `opentt_matches_list` frontend round-list resolution: when round navigation exists but direct `round slug -> list` mapping is inconsistent, shortcode now resolves normalized keys and falls back to the first non-empty round list instead of showing an empty body.
- Hardened `opentt_matches_list` frontend payload handling to support both array and object-shaped `matchesByRound` buckets, preventing empty render states when round data exists but JSON shape differs.
- Added server-rendered first-round fallback for `opentt_matches_list` so matches are visible immediately (and remain visible even if client-side round JS fails or payload mapping breaks).
- Added non-JS interaction fallback for `opentt_matches_list`: round arrows now have server-side navigation URLs, and row click navigation is preserved via inline `data-link` handlers even when main round JS fails.
- Updated `opentt_matches_list` round arrows back to JS-first `button` navigation (no page reload between rounds), while keeping server-side round links only inside `noscript` fallback.
- Fixed `opentt_matches_list` round-arrow enable/disable state so JS now also toggles `is-disabled` class (not just `disabled` attribute), preventing arrows from staying non-clickable after round changes.
- Stabilized `opentt_matches_list` round switching by shipping server-aligned `roundLists`/`defaultRoundIndex` payload to JS, so arrow navigation now switches by deterministic round index instead of fragile slug-key mapping.
- Added resilient arrow-click fallback for `opentt_matches_list`: inline handlers now attempt JS round stepping first and gracefully fall back to server round URLs when JS state is unavailable.
- Fixed `opentt_matches_list` arrow inline-handler ID encoding by switching to a safe quoted JS string literal for root lookup, restoring previous-round click behavior.
- Updated `opentt_matches_list` arrow controls to be JS-only in normal mode (no `window.location` fallback on click), so round changes no longer trigger page refresh; server links remain available only in `noscript` fallback.
- Refined `opentt_matches_list` per-round data resolution to use only exact/normalized keys for the selected round (removed global non-empty fallback), preventing arrow navigation from repeatedly showing the same round content.
- Reworked `opentt_matches_list` round switching renderer to index-based prebuilt HTML payload (`roundHtmlByIndex`), so arrow navigation swaps round content directly without fragile client-side row reconstruction.
- Removed server-side `disabled` lock on JS round buttons and added initial-index auto-correction to the latest round with actual content, preventing dead previous-arrow state when default index and payload buckets diverge.
- Switched primary round arrows to `href` anchors with JS click interception: rounds now switch client-side without reload when JS is healthy, with guaranteed server URL fallback only if JS step cannot execute.
- Added inline no-refresh guard on round arrows (`onclick ... return false`) and delegated stepping through shared global handler, preventing full-page navigation on arrow click.
- Finalized `opentt_matches_list` round navigation as JS-only `button` controls in standard mode (with `noscript` link fallback), eliminating context-dependent page refreshes on homepage/theme wrappers.
- Moved `opentt_matches_list` round-switch logic from inline shortcode script to global frontend asset (`assets/js/frontend.js`) with JSON payload bootstrap, so homepage/static-page renders keep arrow navigation functional without reload.
- Added stronger list-layout CSS guards for `opentt_matches_list` inside combined/homepage wrappers to prevent theme-level generic selector overrides from breaking row/nav alignment.
- Updated `opentt_matches_list` frontend bootstrap to preserve server-rendered initial round markup and only render on arrow interaction, preventing delayed homepage style regressions caused by immediate JS re-render on load.
- Simplified `opentt_matches_list` row HTML by removing inline keyboard/click handlers from server-rendered rows (delegated JS only), improving markup stability after round-switch re-render.
- Switched `opentt_matches_list` client payload transport from `textarea` to `application/json` script block to prevent escaped-markup artifacts during round-switch rendering on homepage contexts.
- Refined `opentt_matches_list` mobile card layout: rows now render compactly with date/media on top and teams+score in a single aligned line (instead of stacked home/score/away three-line flow).
- Center-aligned mobile date labels in `opentt_matches_list` rows for cleaner visual balance with score and team blocks.
- Updated `opentt_matches_grid` calendar trigger to show text label (`Kalendar`) next to the icon, and restyled `Očisti datum` action for stronger contrast/readability inside the calendar popover.
- Added new `Prostrano / Kompaktno` density switch in `opentt_matches_grid` filter toolbar (positioned before calendar): default remains spacious card view, while compact mode renders a tighter round-grouped list with `kolo` + date heading, slimmer rows, and reduced spacing for faster scanning.
- Polished compact density styling in `opentt_matches_grid`: round group transitions are now clearer via darker heading blocks (`kolo` left / date right), stronger visual separation between rounds, and explicit winner/loser emphasis restored (winner bold white, loser muted gray) for both team names and scores.
- Refined compact round grouping in `opentt_matches_grid`: each round now has a subtle bordered container around the full group (heading + matches) and tighter intra-group match spacing for denser scan-friendly layout.
- Fixed compact grouping consistency and separators in `opentt_matches_grid`: compact mode now always groups by round container (not only club-filter mode), and match separators are rendered with balanced spacing/offset to avoid uneven line appearance.
- Finalized compact visual hierarchy in `opentt_matches_grid`: removed redundant inner heading frame (kept single outer round-group frame) and slightly increased separator breathing room for less cramped match rows.
- Adjusted compact group separators in `opentt_matches_grid` so the last match in each round group no longer renders a bottom separator line.
- Updated `opentt_matches_grid` compact rows for unplayed matches: when score is not available (`0:0` / not played), the score slot now shows kickoff time instead of remaining empty.
- Refined unplayed-time rendering in `opentt_matches_grid`: kickoff time in score slot is now compact-only (spacious view unchanged) and shown once per match row (not duplicated for both teams).
- Aligned compact unplayed kickoff time vertically to the middle of match row (right side), replacing previous top-right placement.
- Updated `opentt_matches_grid` filter default sort behavior: when shortcode uses `played="false"`, initial sort now defaults to `kolo_asc` (oldest rounds first); played/all modes keep existing default (`kolo_desc`).
- Improved mobile filter layout for `opentt_matches_grid` and `opentt_competitions`: controls are now better structured and spaced on phones (cleaner two-column/row behavior, full-width selects where needed, and tidier reset/calendar alignment).
- Improved `opentt_competition_info` mobile presentation: switched from stacked block flow to compact two-column layout (logo + text), with tighter spacing and tuned typography for smaller screens.
- Fixed Serbian grammar in `opentt_search` Trending click metadata by adding proper pluralization (`1 klik`, `2/3/4 klika`, `5+ klikova`) instead of always rendering `klikova`.
- Fixed `opentt_search` discovery consistency across page contexts (for example match-detail pages): when context-scoped discovery queries return empty, plugin now falls back to global data so `Najnoviji rezultati`, `Popularni igrači`, and `Popularni klubovi` still render instead of only `Trending`.
- Added match-data update footer for `opentt_matches_grid` (both `Prostrano` and `Kompaktno`) and `opentt_matches_list`: bottom section now shows `Podatke uneo` with updater name (WordPress profile), avatar, admin badge overlay icon, and latest update date/time.
- Hardened match update attribution tracking across match/game/set admin actions: updater `user_id`, display name, avatar URL, and timestamp are now persisted on each edit, improving frontend footer accuracy and avoiding generic fallback identity display.
- Refined match-data attribution UX: footer now renders in two rows (`Podatke uneo` + author block), `opentt_matches_list` footer is centered, and updater avatar/name is clickable (linked player profile when mapped, otherwise virtual `stkb-author-page` fallback). Added player-admin `WP profil` mapping field so player entities can be linked to WordPress users for accurate author profile routing.
- Polished matches-list updater footer sizing/responsiveness: centered block now uses content width (not full-row stretch, same visual footprint as grid) and no longer overflows outside viewport on mobile.
- Adjusted matches-list updater footer alignment so the author row is consistently centered again inside the two-row card.
- Reworked admin match quick editing UX: renamed action to `Quick edit`, moved editor inline below the selected match row (blue-highlighted box), opened/closed without page refresh, and expanded fields to include score, date, kickoff time, and location (refresh occurs only on save).
- Updated `opentt_matches_list` default-round selection: when no explicit round is requested, shortcode now opens the first upcoming round (first round containing at least one unplayed match) instead of always opening the latest round.
- Redesigned `opentt_featured_match` card visuals for stronger hero emphasis: richer layered gradient, premium glass/panel styling, stronger typography hierarchy, featured ribbon marker, and polished hover/live-state presentation across desktop and mobile.
- Adjusted `opentt_featured_match` center timing behavior: countdown (`Početak za`) now shows only within the final 24 hours before kickoff; for matches further out it shows kickoff time with date underneath (without countdown intro text).
- Replaced `opentt_featured_match` visuals with a full “epic hero” redesign (no featured ribbon): bold high-contrast lighting layers, stronger team/score prominence, dramatic center panel, and more expressive hover/live aura for a clearly high-priority match presentation.
- Applied the same 24h timing rule to `opentt_match_teams`: countdown (`Početak utakmice za`) is now shown only in the final 24h before kickoff; for earlier matches it shows only kickoff time (date remains in footer).
- Normalized `opentt_match_teams` scheduled-time format to always render as `HH:MM` (for example `12:00`) instead of compact `12h` format.
- Added new global live-search shortcode `opentt_search` with search-icon trigger (`assets/icons/search-icon.svg`), instant AJAX suggestions while typing, grouped result categories (players, clubs, leagues/seasons, matches), and context-aware prioritization (match/competition context boosts relevant entities first).
- Refined `opentt_search` UX: fixed icon asset URL resolution, improved result-row text clipping/overflow behavior, and added mobile fullscreen search mode (with dedicated close button) to prevent off-screen results panel on phones.
- Further polished `opentt_search` readability: prevented mobile browser auto-zoom on input focus and adjusted desktop result row spacing/line-height/padding to eliminate clipped-looking text in category lists.
- Upgraded `opentt_search` desktop interaction to centered modal search (open on icon click) with blurred page backdrop and close button, while keeping mobile fullscreen behavior; added per-result thumbnails (players/clubs/leagues) displayed to the left of result text.
- Improved `opentt_search` behavior and relevance: disabled background page scrolling while overlay is open, added Serbian diacritic-tolerant matching (for example `bubusinac` now matches `bubušinac`), and refined modal/list visual styling for cleaner readability.
- Hardened `opentt_search` overlay UX: desktop now uses strict body/html scroll lock while modal is open (no background page scroll bleed), and mobile fullscreen mode now has a darker backdrop treatment for stronger search box contrast.
- Finalized `opentt_search` fullscreen overlay behavior across desktop/mobile: added stronger background-scroll guards (wheel/touch lock), switched results to single continuous full-list rendering (no inner category clipping scroll), and aligned both breakpoints to full-viewport search mode.
- Fixed `opentt_search` global click side effect that could reset page scroll to top outside the overlay; search panel close/unlock now runs only when overlay is actually open.
- Polished `opentt_search` header/input layout: localized label to `Pretraga`, increased heading size, improved top spacing and panel padding, and fixed desktop close-button clipping.
- Updated `opentt_search` heading treatment to stronger visual emphasis: larger all-caps `PRETRAGA` title with increased letter spacing (desktop + mobile scale).
- Added empty-input discovery mode in `opentt_search`: now shows popular players and clubs (ranked by current match/game performance) plus cookie-based search history (`Istorija pretrage`) for returning visitors without user profiles.
- Extended `opentt_search` discovery UX: search history now appears contextually on input focus (YouTube-style) with `Očisti istoriju pretrage` action, and `Trending` now ranks recently clicked search results (player/club entities) from the last 5 days instead of raw typed terms.
- Added anti-spam guard for `opentt_search` trending analytics: identical entity clicks (same player/club) now have a per-day counting cap, preventing automated repeated queries/clicks from unfairly pushing one result to the top.
- Improved desktop `opentt_search` discovery layout: when both popular groups are present, `Popularni klubovi` and `Popularni igrači` now render side-by-side in two equal columns (50/50), while mobile keeps a single-column flow.
- Fixed desktop popular-groups grid placement so `Popularni klubovi` (left) and `Popularni igrači` (right) consistently align in the same row, regardless of render order.
- Tuned `opentt_search` trending anti-abuse thresholds for low-traffic usage by adopting hybrid daily caps: per-client (cookie token) cap is `3` clicks per entity/day, with a global cap of `20` clicks per entity/day.
- Enhanced `opentt_search` trending presentation: added `trending-icon.svg` next to section title, limited list to Top 5, and added rank badges before item thumbnails (`trending-one/two/three` medal icons for #1-#3, numeric badges for #4-#5) with high-contrast medal styling for dark theme readability.
- Refined trending iconography styling: medal colors are now applied directly to `one/two/three` rank icons (without colored outer frames), and section `trending-icon.svg` now uses a vivid fire-like gradient blend (red/yellow/pink/orange) for stronger visual emphasis on dark overlays.
- Finalized trending rank alignment polish: removed frames from numeric `#4/#5` badges, shifted their color to softer gray for better hierarchy, and vertically centered all rank markers (`#1-#5`) within result rows.
- Updated `opentt_search` discovery composition on desktop to a 2x2 layout: first row `Trending` (left) + `Najnoviji rezultati` (right), second row `Popularni klubovi` (left) + `Popularni igrači` (right); mobile remains single-column.
- Added `Najnoviji rezultati` discovery block (Top 5 by match date) with compact match rows showing home/away logos, score, league, and date.
- Improved league thumbnails in search results by resolving logo from competition rule (`pravilo_takmicenja`) for `liga+sezona` before fallback sources.
- Fixed `Najnoviji rezultati` discovery feed to include only actually played matches (excluding `0:0` unplayed fixtures), and unified match-row presentation so both discovery latest-results and regular searched `Utakmice` use the same compact logo/score/league/date layout sizing.
- Refined search match-row details: corrected away-team content order to `name + logo`, normalized encoded dash entities in match club names (for example `&#8211;` -> `-`), and added query highlight emphasis for matched club names inside match rows.
- Fixed frontend search rendering regression where discovery blocks could disappear due missing highlight helper functions in `frontend.js` (restored stable highlight pipeline).
- Styled `Trending` discovery block with the same fire gradient palette as the trending icon and added subtle pulse animation to increase visual prominence.
- Center-aligned league/date metadata under match rows in both discovery and regular search results, and added fuzzy typo-tolerant matching for near-miss queries (for example small misspellings like `bubudinac` can still surface `bubušinac`).
- Added inline typo helper under search input: when query looks misspelled, search now shows `Da li ste mislili "..."?` with clickable suggestion that reruns results instantly.

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
- Switched admin branding logo source (topbar + onboarding) from root `opentt-logo.png` to `assets/img/admin-ui-logo.png` without changing frontend/readme logo usage.
- Enhanced `opentt_matches_grid` (`filter=true`) with a right-aligned popup calendar date filter (`opentt_match_date`) that highlights match days by status: played (green tint) and upcoming (blue tint).
- Updated admin Settings shortcode catalog/help for `opentt_matches_grid` to document calendar behavior and the optional `opentt_match_date` attribute.
- Aligned the matches-grid calendar toggle visual style with existing filter controls and switched its icon to `assets/icons/calendar.svg` rendered in white.
- Simplified the matches-grid calendar trigger to icon-only and switched calendar month/day labels to English.

#### Tooling

- Added standalone legacy export converter CLI app: `tools/convert-stkb-export.php` for transforming older `stkb_*` JSON packages (format/meta/section/table/key names) into OpenTT-compatible import JSON.

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

- First public OpenTT release baseline.

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
- Continued legacy-module unification into OpenTT core architecture.

## Notes

- Internal class/function names are standardized to the `OpenTT_*` scheme.
- Changelog heading format: `X.Y.Z(-tag) - YYYY-MM-DD`.
- This changelog is maintained in English for public release workflow.
