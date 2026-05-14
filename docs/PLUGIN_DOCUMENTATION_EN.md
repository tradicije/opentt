# OpenTT Documentation (English)

This document is a detailed user and operations guide for the OpenTT WordPress plugin.
It is intended for site administrators, content editors, league operators, clubs, and technical maintainers.

## 1. What OpenTT Is

OpenTT is a WordPress plugin for managing, displaying, and archiving table tennis competitions.
It combines:
- match and result administration,
- club and player records,
- standings, statistics, and rankings,
- data import/export,
- legacy migration support.

Key architectural decision: match-level data (matches, games, sets) is stored in dedicated DB tables for performance and reliability.

## 2. Core Capabilities

- Unified OpenTT admin area.
- Club (`klub`) and player (`igrac`) support through WordPress content model.
- Dedicated DB model for high-volume match data.
- Frontend shortcode system.
- First-run onboarding flow.
- Visual settings and CSS override mechanisms.
- JSON-based import/export.
- Legacy route/content compatibility.

## 3. Requirements

- WordPress environment with database access.
- Permissions to create/update tables and plugin options.
- Recommended: regular backups of database and `uploads` before major import/migration tasks.

## 4. Installation

1. Place plugin files in the WordPress plugins directory.
2. Activate the plugin in WP Admin > Plugins.
3. On activation, OpenTT:
- registers rewrite/routing rules,
- checks and migrates schema when needed,
- prepares onboarding state,
- checks/creates default pages.

## 5. First Run (Onboarding)

On fresh installs, OpenTT can guide administrators through initial setup.
Typical steps:
- base configuration,
- initial page provisioning,
- data availability checks,
- hand-off to primary workflows (competitions, clubs, players, matches).

## 6. Data Architecture

### 6.1 Entities

- Clubs: WordPress post type `klub`.
- Players: WordPress post type `igrac`.
- Match data: dedicated DB tables.

### 6.2 Tables (prefix depends on WP setup)

- `*_opentt_matches`
- `*_opentt_games`
- `*_opentt_sets`
- `*_opentt_games_pending_submissions` (pending entries)

### 6.3 Why This Model

- Faster queries and processing at larger scale.
- More stable standings/statistics calculations.
- Easier historical data migration and structured import/export.

## 7. Admin Modules and Workflows

OpenTT admin generally includes:
- Dashboard
- Matches
- Clubs
- Players
- Competitions
- Import/Export
- Customize
- Settings

Additionally:
- live search and filtering,
- helper workflows (onboarding, migration, validation),
- diagnostics and maintenance actions.

## 8. Competition, League, and Season

OpenTT uses league/season logic via standardized slugs and competition rules.
Supported behavior includes:
- legacy format mapping,
- scoring system selection,
- promotion/relegation rules,
- standings calculation per season and competition.

## 9. Match Management

Matches are maintained in the DB model and linked to clubs/players.
Common fields include:
- date/time,
- home/away clubs,
- match score,
- game/set details,
- played status,
- round,
- league and season.

## 10. Clubs and Players

### Clubs

OpenTT supports displaying/storing:
- name and logo,
- city/municipality,
- contact details,
- jersey/ball/hall metadata,
- links to related content.

### Players

OpenTT supports displaying/storing:
- profile details,
- club association,
- statistics/performance,
- transfer history (depending on available data).

## 11. Shortcode System

OpenTT uses the `opentt_*` shortcode prefix.

### 11.1 Registered Shortcodes

- `[opentt_auth]`
- `[opentt_auth_menu]`
- `[opentt_profile]`
- `[opentt_search]`
- `[opentt_matches]`
- `[opentt_matches_grid]`
- `[opentt_matches_list]`
- `[opentt_match_id]`
- `[opentt_featured_match]`
- `[opentt_featured_player]`
- `[opentt_standings_short]`
- `[opentt_standings_table]`
- `[opentt_match_games]`
- `[opentt_h2h]`
- `[opentt_mvp]`
- `[opentt_match_report]`
- `[opentt_match_video]`
- `[opentt_match_teams_short]`
- `[opentt_home_club]`
- `[opentt_away_club]`
- `[opentt_club]`
- `[opentt_match_teams]`
- `[opentt_top_players]`
- `[opentt_players]`
- `[opentt_club_news]`
- `[opentt_club_featured]`
- `[opentt_player_news]`
- `[opentt_related_posts]`
- `[opentt_club_info]`
- `[opentt_club_card]`
- `[opentt_competition_info]`
- `[opentt_club_form]`
- `[opentt_player_stats]`
- `[opentt_team_stats]`
- `[opentt_player_transfers]`
- `[opentt_player_info]`
- `[opentt_competitions]`
- `[opentt_clubs]`

### 11.2 Commonly Used Shortcodes

- Matches/grid: `[opentt_matches_grid]`
- Standings table: `[opentt_standings_table]`
- Match games: `[opentt_match_games]`
- Club info: `[opentt_club_info]`
- Team stats: `[opentt_team_stats]`
- Club players: `[opentt_players]`
- Top players: `[opentt_top_players]`

### 11.3 Examples

```text
[opentt_matches_grid liga="first-league" sezona="2025-26" limit="12" filter="true"]
```

```text
[opentt_standings_table liga="first-league" sezona="2025-26" highlight="stk-bubusinac"]
```

```text
[opentt_club_info]
[opentt_team_stats filter="true"]
[opentt_players]
```

Note: exact attribute sets vary by shortcode. Use OpenTT admin insert/builder helpers when available.

## 12. Single-Page Context Behavior

Many OpenTT shortcodes are context-aware:
- on `single-klub`, they infer the current club,
- on `single-igrac`, they infer the current player,
- on competition routes, they infer league/season context.

This reduces the need for manually passing attributes.

## 13. Frontend Search

OpenTT includes frontend search (including AJAX flows).
Typical features:
- query input,
- grouped results,
- contextual suggestions,
- trend/recommendation behavior.

## 14. Import/Export

### 14.1 Export

Available sections may include:
- competitions,
- clubs,
- players,
- matches,
- games,
- sets.

### 14.2 Import

Recommended flow:
1. Upload JSON package.
2. Validate package structure/content.
3. Review warnings (duplicates, references, missing entities).
4. Confirm and execute.

### 14.3 Best Practices

- Always create backups before import.
- Test imports on staging first.
- Validate key frontend pages after import.

## 15. Migrations and Legacy Compatibility

OpenTT provides migration and compatibility mechanisms for legacy structures.
This may include:
- league/season mapping,
- internal ID/key remapping,
- SQL migration scripts,
- fallback behavior for legacy routes/content.

Recommendation: run migrations with a plan, backup, and test pass.

## 16. Themes, Templates, Overrides

OpenTT supports:
- plugin fallback templates,
- theme override priority,
- block and classic themes.

If the active theme has no specific templates, plugin fallbacks are used.

## 17. Styling and Customization

Available options:
- global visual settings,
- global custom CSS,
- shortcode-level custom CSS,
- modular CSS files by feature area.

Recommendation:
- keep custom styles organized by section,
- use a child theme for larger override changes.

## 18. Localization

- Admin UI language files: `languages/admin-ui-<lang>.txt`.
- Line format: `english_reference = translation`.
- New languages are auto-detected if file naming is correct.

## 19. Roles and Permissions

OpenTT follows WordPress capability patterns.
Administrative actions require appropriate permissions (typically editor/admin level).

## 20. Performance

Recommendations:
- keep DB optimized,
- clean unnecessary revisions/transients,
- use caching in production,
- run heavy import/migration operations off-peak.

For large history (many seasons):
- dedicated match DB model is already a strong baseline,
- consider seasonal aggregate layers where needed.

## 21. Security

OpenTT follows common WordPress security patterns (nonce checks, input sanitization, capability checks) in admin and AJAX flows.
Best practices:
- keep WP core/plugins updated,
- limit admin access,
- enforce SSL and backup policy.

## 22. Recommended Operating Workflow (Club/Federation)

1. Configure competitions (league, season, rules).
2. Add/import clubs and players.
3. Enter schedule and results.
4. Validate standings/statistics.
5. Publish frontend pages using shortcodes.
6. Export data periodically for archival.

## 23. Common Issues and Troubleshooting

- No shortcode output:
  - verify context (club/league/season),
  - verify match played status,
  - verify slug consistency.
- Empty standings:
  - verify matches exist for selected league/season,
  - verify competition rules.
- Unexpected template behavior:
  - verify theme overrides vs plugin fallback flow.

## 24. Versioning and Maintenance

Before major updates:
- backup,
- staging validation,
- smoke test key shortcode pages,
- verify import/export path.

## 25. Bundled Addon: Tournaments

OpenTT includes a bundled tournaments addon in `addons/tournaments`.
The addon has:
- its own schema,
- its own shortcodes,
- admin and template flow.

Current status note:
- The bundled tournaments addon is currently in-progress and not feature-complete.
- Interfaces, data model details, and release/legal framing for standalone usage may change before final stabilization.
- For production-critical usage, treat this addon as evolving and validate workflows on staging before rollout.

Primary addon shortcodes:
- `[opentt_tournaments]`
- `[opentt_tournament]`
- `[opentt_tournament_categories]`
- `[opentt_tournament_signup]`
- `[opentt_tournament_podium]`

## 26. License

OpenTT is licensed under AGPL-3.0-or-later.
If you provide it as a SaaS/service, AGPL sharing obligations apply.

Additional licensing clarifications:
- This repository's core plugin code is distributed under `AGPL-3.0-or-later` (see `LICENSE`).
- Bundled addon code under `addons/tournaments` is part of the same repository but functionally still in-progress; policy and packaging details for a final standalone addon release are not yet finalized.
- Code license and brand usage are separate topics: project name, logo, and identity usage are governed by trademark/brand rules. See `trademark.md`.

## 27. Contributions and Support

Contributions are welcome through:
- bug reports,
- improvement proposals,
- pull requests,
- license-compliant forks.

---

If you plan to build a standardized club-season archive UX, a better long-term approach is to evolve existing context-aware shortcodes with a shared season state, rather than introducing one monolithic shortcode.
