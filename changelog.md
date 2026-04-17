# Changelog

All notable changes to the OpenTT plugin are documented in this file.

## Unreleased

### Next

#### Assets & UI

- Test: Provera changelog zapisa za push validaciju.
- Increased logged-in avatar size in `opentt_auth_menu` from `24x24` to `40x40` while keeping the same `40x40` trigger area.
- Updated `opentt_standings_short` mini-table so club cells are now clickable links that route directly to the selected club profile page.
- Added new shortcode `opentt_club_card` (dynamic/context-aware club card): renders club logo, bold club name, and city in a vertical stack; supports `id`, `klub` (slug/name), and falls back to single-club context automatically.
- Added standings social export actions under each `opentt_standings_table`: new `Podeli` and `Preuzmi` buttons (with `share-icon` / `download-file-icon`) now generate a context-aware 1080x1080 social image (league+season header, rendered standings table, branded footer `Tabela preuzeta sa stkb.rs`) with OpenTT blue gradient background.
- Enhanced standings social image generator visuals (richer gradient layers, polished header/footer containers, row-striping/highlight contrast) and added centered table watermark rendering from `club-logo.png`.
- Fixed standings social image club-name encoding so entities like `&#8211;` are decoded to proper characters (for example `Spin - N`) in exported PNG rows.
- Updated standings social export template to match reference style: yellow promotion cutoff line (based on promotion slots), top-left club brand logo, top-right competition logo (from competition rule featured image), and rotated/blurred low-opacity `club-logo-alt.png` background treatment behind the table.
- Migrated standings social image rendering from manual canvas drawing to `html2canvas` template capture (1080x1080), enabling easier CSS-like layout control and cleaner premium broadcast styling updates.
- Refined standings export typography/layout: title now uses proper league title-casing and season slash format (`2025/26`), subtitle (`Tabela kola`) is visually smaller than league title, club column width increased to reduce cut-off names, table text scaled down for readability, and export logos are rendered without forced square background boxes.
- Further refined standings export composition: header now follows left-aligned competition-brand layout (competition logo + league/season/round text), footer now uses split layout (left `club-logo`, right attribution text), table height now auto-fits row count (no large empty bottom area), row striping removed (separator lines only), and bottom table padding now matches top spacing.
- Fine-tuned standings export spacing and hierarchy: league + season are back on the same header line, footer `club-logo` is slightly larger, and table-body bottom padding was increased to prevent the last row from feeling cramped.
- Updated standings share-action button icons to white tint so `share`/`download` icons visually match button text on dark backgrounds.
- Added subtle background watermark support for `opentt_standings_table`: each generated league table now renders `assets/img/club-logo.png` behind table rows with low opacity for branding without hurting readability.
- Fixed standings watermark rendering path so the background logo is always injected from plugin assets and visible with readable transparency.
- Improved standings watermark robustness and visibility: added multi-filename asset fallback lookup in `assets/img` and moved watermark rendering to table background layer for consistent display across themes/cache order.
- Switched standings watermark rendering to a dedicated image layer (`<img>` behind table content) for theme-agnostic display reliability and clearer low-opacity visibility.
- Hardened standings watermark delivery by embedding the chosen logo as inline `data:` image with inline fallback styles, eliminating asset URL/cache path issues on rendered tables.
- Adjusted standings watermark layer order to render above table rows/content (very low opacity) so watermark remains visible even when row backgrounds are strong.
- Replaced inline `data:` watermark delivery with direct plugin asset URL (+ filemtime cache-buster) and added wrapper background fallback to avoid CSP-blocked image rendering.
- Made standings watermark rendering deterministic: watermark markup/styles now always render, with fixed primary asset (`assets/img/club-logo.png`) and fallback asset (`assets/img/admin-ui-logo.png`).
- Fixed standings watermark plugin-root resolution: corrected shortcode path depth so watermark URL now includes `/plugins/opentt/...` and correctly resolves primary `assets/img/club-logo.png`.
- Refined standings watermark intensity control: removed duplicate wrapper background watermark layer and kept a single overlay image layer, enabling lower effective opacity values (for example `0.05`) to render as expected.
- Updated standings watermark sizing/placement: watermark now fills the table-body visual area with fixed `24px` insets and centered composition for stronger but still subtle branding.
- Increased standings watermark opacity from `0.05` to `0.1` for clearer visibility while preserving table readability.
- Refined standings export scene composition: removed decorative background circle overlays, moved watermark into the table card (centered, larger, clipped to table bounds), and vertically centered the table block in the 1080x1080 canvas when fewer rows are present.
- Fixed standings export table layering regression that introduced an unintended gap between table header and first row; watermark now preserves original image ratio (no stretch) and uses lower opacity for subtler background branding.
- Added new settings for frontend standings watermark control: toggle on/off plus custom image selection via WordPress media uploader (including upload/media-library workflow) with live preview in admin settings.
- Fixed standings social image export numeric rendering so zero values (for example `0` losses) are no longer dropped from table cells.
- Added WordPress user-portal integration in OpenTT: new frontend shortcodes `opentt_auth` (`/prijava`) and `opentt_profile` (`/profil`) with plugin-styled login/registration/profile UX, role-based tools (editor posts, league-admin match updates, team-manager club/player edits), plus a new admin `Korisnici` tab for assigning roles, linking users to players, and configuring per-user league/team ownership.
- Improved user-portal UX/security: login/register now supports Turnstile on both flows with pane switching (`Prijava` <-> `Registracija`), `opentt_profile` is split into clearer role-based cards (profile settings, editor tools, separate `Moje vesti` grid, league-admin tabs), and league-admin forms now include full match-context edits (season/round/clubs/live/played) plus reliable frontend action hook for adding new matches.
- Added `opentt_auth_menu` shortcode (guest login link or logged-in avatar dropdown with role-aware shortcuts), refined profile heading scale, and upgraded frontend league-admin panel by grouping matches per season within each league and replacing public pending-games redirection with direct admin match-game editing action (`Unos partija i setova`) from the same result-edit card.
- Refined `opentt_auth_menu` behavior: avatar click now always opens `/profil` (default profile tab), dropdown now opens on hover/focus, and menu items are strictly role-aware (including `Menadžer tima` only when role/capability is available).
- Fixed `opentt_auth_menu` hover dropdown stability by adding a small invisible hover-bridge zone between avatar trigger and dropdown panel, preventing premature close while moving the pointer onto menu items.
- Added new shortcode `opentt_club_featured` for club cover-photo display (separate from WordPress featured image), plus new club-cover image controls in both admin club edit and frontend team-manager tools, with shared meta persistence (`opentt_club_featured_image_id`) and JSON import/export support.
- Refined `opentt_club_featured` rendering and team-manager UX: removed forced title/link overlay from cover output, switched default cover behavior to full-width natural image ratio, added centered non-stretched crop mode when `height` is explicitly set, and changed team-manager cover update flow to direct local file upload (without WordPress media-library picker).
- Fixed `opentt_club_featured` `height` attribute parsing so fixed-height crop mode activates only when `height` is explicitly provided; without `height`, shortcode now reliably keeps natural image ratio.
- Removed forced minimum height clamp on `opentt_club_featured`: shortcode now respects small explicit values (for example `height="128"`), while still keeping centered crop behavior without stretch.
- Fixed frontend asset loading for `opentt_club_featured` by registering/enqueuing `club-featured.css` in the frontend module list, so `height`-based crop styles apply correctly on live pages.
- Refined `opentt_club_featured` visual behavior and club-cover editing workflow: removed forced border/radius on rendered cover output, introduced responsive default ratios (`3:2` desktop, `16:9` mobile) when `height` is not set, and added club-cover focus controls (`horizontal/vertical`) with dual desktop/mobile crop previews in both admin club edit and frontend team-manager tools.
- Tuned `opentt_club_featured` desktop default from cover-like block to true banner behavior (full width with capped banner height), aligned focus-preview desktop ratio to banner format (`16:5`) in admin/team-manager editors, improved team-manager cover preview rendering width, and added explicit recommended upload dimensions near cover-upload inputs.
- Unified club-cover crop ratio between devices by switching mobile preview/render to the same `16:5` banner ratio as desktop, so team-manager/admin focus positioning now matches frontend output more reliably.
- Fixed desktop club-cover focus mismatch by using strict `16:5` ratio rendering on frontend (instead of variable desktop height), ensuring saved focus position now matches editor previews.
- Updated `opentt_club_featured` empty-state behavior: when club cover is not set, shortcode now renders nothing (entire block hidden) instead of showing a fallback “no cover” message.
- Reworked frontend user-portal navigation to single-page tabbed profile flow (`Profil`, `Urednički portal`, `Administracija lige`, `Menadžer tima`): auth-menu shortcuts now open `/profil` with the corresponding tab preselected, account header stays fixed on top, and league-admin cards now include inline game/set entry forms directly in the same panel (no redirect to wp-admin or public submission page).
- Upgraded league-admin access granularity to `liga + sezona`: `Korisnici` access panel now assigns league-season pairs (instead of league-only), frontend admin tools honor season-scoped permissions, and users list retrieval now uses full WP user objects so administrator accounts are reliably visible in the plugin `Korisnici` tab.
- Rebuilt frontend league-admin match browser to season-first navigation (`sezona` tabs -> `liga` tabs), with contextual match lists per selected pair only (no cross-season mixing), plus built-in `pretraga`, `status` filter, and `sort` controls and a responsive 3-column desktop / 1-column mobile card grid.
- Updated `opentt_auth_menu` guest state to icon-only trigger: replaced text link with `assets/icons/login-icon.svg` (white), using a 24x24 icon inside a 40x40 clickable hitbox.
- Refined `opentt_auth_menu` avatar/icon sizing and defaults: both guest and logged-in triggers now use a compact 24x24 visual inside ~40x40 hitbox, guest icon has no background/border, logged-in avatar keeps only a subtle avatar border, and default user avatar now falls back to `assets/img/fallback-player.png` until a custom profile image is uploaded.
- Updated avatar fallback priority in user portal to preserve existing user images: profile-uploaded avatar first, then WordPress/Gravatar avatar, and `fallback-player.png` only as the final fallback.
- Updated `opentt_auth` card header treatment: removed textual heading and added centered top logo (`assets/img/club-logo.png`) while keeping existing form elements/flow unchanged.
- Refined `opentt_auth` logo sizing to preserve original image ratio and use near full card width with horizontal insets (`40px` left/right padding), avoiding forced square rendering.
- Adjusted `opentt_auth` vertical spacing for cleaner header rhythm: auth-card top padding is now `24px` and logo-wrapper bottom spacing is now `24px`.
- Improved mobile auth UX: Turnstile widget in `opentt_auth` now stretches to full available form width (while respecting container padding) instead of rendering narrower than other inputs.
- Hardened `opentt_auth` Turnstile rendering by switching widgets to `data-size=\"flexible\"` and styling wrapper border/radius (`overflow:hidden`) so the captcha fills container width and rounded border remains visually intact.
- Adjusted Turnstile visual styling in `opentt_auth`: removed wrapper-level border/radius and moved border-radius/border directly to captcha iframe for cleaner alignment with form fields.
- Standardized `opentt_auth` container width to `max 400px` across desktop and mobile (responsive shrink on narrower screens).
- Refined `opentt_auth` sizing to `max 360px` with `box-sizing: border-box` to prevent overflow on narrow mobile viewports.
- Updated `opentt_auth` header logo (`club-logo.png`) to be clickable and route users back to site homepage.
- Added new `opentt_featured_player` shortcode: renders a clickable featured-player card (photo, name, club, wins/losses/efficiency) with support for explicit player (`igrac`), explicit club (`klub`), and automatic single-club contextual selection of the best-ranked player from current ranking data.
- Improved `opentt_featured_player` rendering resilience: shortcode now supports `season` alias, `liga+sezona` standalone usage (without club/single-club context), keeps explicit-player cards visible even when ranking stats are missing, and falls back to first club player when club ranking scope has no qualified entries.
- Decoupled `opentt_featured_player` from rank-list participation cutoff (`50%`): featured-player selection/statistics now use unfiltered league-season player data, so cards render and show seasonal performance even when a player does not meet rank-list qualification rules.
- Updated `opentt_featured_player` season resolution: when `sezona/season` is omitted, shortcode now auto-locks to the latest available season for the selected league scope (never aggregates multiple seasons).
- Fixed strict player targeting in `opentt_featured_player`: when `igrac="slug"` is provided, shortcode now resolves player by robust slug/title matching and no longer falls back to generic top-player output if the requested player cannot be resolved.
- Improved `opentt_featured_player` stat labels with Serbian pluralization rules so win/loss captions are grammatically correct for all values (for example `0 poraza`, `1 poraz`, `2 poraza`).
- Enhanced `opentt_featured_player` club line: club crest is now rendered next to club name in the same row for clearer visual association.
- Updated `opentt_featured_player` club branding layout: club name remains as plain text below player name, while club crest now appears as an overlay badge in the lower-right corner of the player photo.
- Enhanced pending-games `Napredni unos` behavior: per-set points are now mandatory in advanced mode, and set inputs are dynamically scoped by entered final set score per game (for example `3:0` shows 3 required sets, `3:2` shows 5).
- Added entry-mode tabs on the standalone pending-games page: default `Brzi unos` (only final game set totals) and optional `Napredni unos` (enables per-set point inputs), with client-side mode switching and mobile-friendly tab styling.
- Updated frontend pending games form validation: submit now requires all expected game rows to be filled (players + total set score for each game), while per-set point fields remain optional.
- Added standalone frontend page for match-game submissions (`?opentt_pending_games_form=1&match_id=...`): `Unesi partije` now opens a dedicated full-page entry screen (desktop/mobile optimized) instead of reusing the regular match-content layout.
- Added frontend pending-submission flow for `opentt_match_games` when no games are entered: users now see `Unesi partije` CTA that opens a dedicated submission view in a new tab, with match-format-aware game rows, player selectors by home/away clubs, required email field, and clear Serbian submission status messages.
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

- Added Mailgun integration settings in admin (`enable`, `API key`, `domain`, `from email`, `from name`) and wired pending approve/deny notifications to send through Mailgun API when enabled (with safe `wp_mail` fallback).
- Added Mailgun test-send action in admin settings (`Email za test` + `Pošalji test mejl`) with direct success/error feedback from Mailgun API response.
- Extended pending submissions with optional submitter full name (`Ime i prezime`) capture and admin visibility in pending list/details.
.
- Added Cloudflare Turnstile protection for frontend game submissions: new settings section (`enable + site key + secret key`) and server-side token verification on submit before pending record creation.
- Added full `Pending partije` moderation workflow: new pending submissions table, admin menu tab with pending list + per-match review screen, editable game/set payload before decision, `Odobri unos` / `Odbij unos` actions, and automatic submitter email notifications (approved/denied) in Serbian.
- Added global admin pending alert notice so administrators are notified when new frontend game submissions await review.
- Fixed admin match wizard submit flow: required-field validation is now enforced per step (`Dalje`) and on final submit, automatically returning users to the first invalid step instead of silently failing when hidden required fields are missing.
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
- Added `highlight="auto"` support to `opentt_matches_list`: on single-club pages, the shortcode now auto-detects current club context and highlights its fixtures without manually entering club slug/name.
- Refined `opentt_matches_list` `highlight="auto"` behavior: single-club context no longer hard-filters list to only that club; shortcode now keeps full contextual match scope and only applies visual highlight to the detected club rows.
- Tightened `opentt_matches_list` `highlight="auto"` competition scope: on single-club context it now locks display to the club’s latest league+season pair (newest season only), avoiding mixed rows from older seasons.
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
- Fixed matches-list updater-card container centering by switching centered variant to block-level flex with content-width sizing (`fit-content`), so the whole author card is centered (not only inner text).
- Added responsive behavior for matches-list updater card: desktop keeps centered card layout, while mobile now matches grid behavior (left-aligned container and left-aligned inner content).
- Switched match-data attribution from global to scoped tracking (`liga + sezona`): admin edits now persist updater/timestamp per competition scope, and `opentt_matches_grid` / `opentt_matches_list` footers now resolve author/update metadata from the currently displayed league-season context instead of site-wide last edit.
- Added `view` attribute support to `opentt_matches_grid` (`view="spacious|compact"`): when provided, grid density is forced to the selected mode and manual density switcher is hidden; works with `filter=true` and without filters (including infinite/static grid branches).
- Added `highlight` support to `opentt_matches_grid` (ID/slug/name, comma-separated): matching club fixtures are now visually emphasized in both `spacious` and `compact` views, aligned with `opentt_matches_list` highlight behavior.
- Fixed `opentt_matches_grid` density-switch initialization regression: when `view` is not provided, manual `Prostrano/Kompaktno` switcher works again; forced `view` now matches the same compact styling path as manual compact mode.
- Added `kolo` attribute support to `opentt_matches_grid` (`kolo="slug"`), enabling direct round-scoped rendering alongside `liga`/`season`/`highlight` filters.
- Aligned forced compact rendering with manual compact switch path in `opentt_matches_grid` (`filter=true`): `view="compact"` now uses the same density-init flow as UI switch mode, and compact highlight styling is now visible/consistent for highlighted club fixtures.
- Refined locked-view toolbar behavior in `opentt_matches_grid`: when `view` is forced, density controls are now visually hidden but still keep layout space (instead of `display:none`), so compact/filter toolbar spacing remains identical to manual switch mode.
- Added `author="true|false"` attribute to both `opentt_matches_grid` and `opentt_matches_list` for controlling visibility of the updater footer block (`Podatke uneo`).
- Added new shortcode `opentt_match_id` with a dedicated 1:1 match card: supports direct match selection via `id="<match_id>"`, or `id="latest" + klub="..."` lookup with optional `played="true|false"` to choose either latest played result or the next upcoming fixture for that club.
- Fixed `opentt_match_id` `id="latest" played="false"` selection logic to resolve the next fixture by schedule order (`kolo` then match date), instead of runtime-nearest timestamp heuristics.
- Redesigned `opentt_match_id` card layout to a strict top-to-bottom 1:1 composition with square edges (`border-radius: 0`): club/opponent crest header, compact league-season-round meta, uppercase score/team rows (played mode) or uppercase opponent focus (upcoming mode), datetime+location block, and bottom CTA (`Detalji meča` / `Meč centar` with arrow).
- Refined `opentt_match_id` alignment and hierarchy: switched to left-aligned content, moved datetime/location to the lower section above CTA, fixed CTA clipping/spacing, increased team-name prominence, and simplified meta line to show only league name.
- Fixed `opentt_match_id` CTA sizing to full card width with border-box sizing (`100%`), preventing button clipping/overflow against inner card padding.
- Hardened `opentt_match_id` CTA visibility: stabilized bottom layout and clamped datetime/location rows (single-line ellipsis) so long metadata no longer pushes or clips the CTA button.
- Updated `opentt_match_id` interaction model so only the CTA button is clickable (card body is no longer a full-surface link), and added winner/loser visual emphasis in played mode by muting loser name+score to gray.
- Polished `opentt_match_id` visual scale and CTA alignment: increased crest/meta/team/CTA typography for better emphasis, and kept CTA full-width while centering its inner label/icon content (no stretched edge alignment).
- Optimized `opentt_match_id` for mobile screens (<=700px and <=420px): tightened spacing hierarchy, improved logo/text/button scaling, and added safer multi-line handling for long team/opponent names to prevent cramped or broken card layout on phones.
- Updated `opentt_match_id` mobile card ratio to `4:3` (instead of `1:1`) for better vertical balance and content fit on phone screens.
- Added new shortcode `opentt_standings_short` with attributes `liga`, `sezona`, and `klub`: renders a compact 3-row standings window centered on the selected club (one above/one below, with edge fallback to two rows on available side), highlights the selected club row, and uses fixed card ratios (`1:1` desktop, `4:3` mobile) with columns `#`, `Klub`, `P`, `W`, `Pts`.
- Refined `opentt_standings_short` presentation: added club crests inside mini-table rows, increased crest/team-name prominence, reduced visual weight of numeric columns (`#`, `P`, `W`, `Pts`), aligned the card look with `opentt_match_id`, and centered the table block within the card while keeping league heading at the top.
- Rebalanced `opentt_standings_short` column density: enforced compact numeric columns and expanded `Klub` column to the dominant width (via fixed `colgroup` ratios), improving team-name readability while keeping `#/P/W/Pts` just wide enough for values.
- Fine-tuned `opentt_standings_short` row polish: slightly increased numeric column typography and enforced middle vertical alignment across all cells, including crest+club-name cluster alignment.
- Increased `opentt_standings_short` row breathing room (larger vertical cell padding) and removed table-header background fill for a cleaner heading row.
- Updated `opentt_standings_short` header/body readability: aligned `Klub` heading to the left, increased vertical row spacing once more, and bumped numeric column text (`#`, `P`, `W`, `Pts`) for clearer scanability.
- Refined `opentt_standings_short` title and row alignment: centered and enlarged league heading with safe multi-line wrapping for long league names, and normalized `club-wrap` vertical centering to keep row separators visually even.
- Reworked admin match quick editing UX: renamed action to `Quick edit`, moved editor inline below the selected match row (blue-highlighted box), opened/closed without page refresh, and expanded fields to include score, date, kickoff time, and location (refresh occurs only on save).
- Added explicit `ID` column to the admin `Utakmice` list table so each match row clearly shows its database match ID.
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
- Added configurable floating search mode: `opentt_search` now supports `floating="true"` positioning, and admin `Podešavanja` includes a new enable/disable toggle for a global frontend floating search icon (rendered via footer when enabled).
- Refined floating search icon appearance by removing toggle wrapper visuals (background/border/button box) so only the icon remains visible.
- Adjusted search-icon display modes: floating search keeps the boxed toggle wrapper, while shortcode-rendered inline search uses icon-only toggle styling.
- Improved inline shortcode search UX by adding an invisible enlarged hit-area around the icon (no visible box), making click/tap targeting easier while preserving icon-only look.
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
