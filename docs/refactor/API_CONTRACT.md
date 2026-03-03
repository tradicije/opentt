# OpenTT API Contract (Refactor Freeze)

This document defines compatibility boundaries for the refactor initiative.
Changes to items below require an explicit migration plan and release notes.

## 1) Public Shortcode Tags

These tags are public content API and must remain stable:

- `opentt_matches_grid`
- `opentt_standings_table`
- `opentt_match_games`
- `opentt_h2h`
- `opentt_mvp`
- `opentt_match_report`
- `opentt_match_video`
- `opentt_home_club`
- `opentt_away_club`
- `opentt_club`
- `opentt_match_teams`
- `opentt_top_players`
- `opentt_players`
- `opentt_club_news`
- `opentt_player_news`
- `opentt_related_posts`
- `opentt_club_info`
- `opentt_competition_info`
- `opentt_club_form`
- `opentt_player_stats`
- `opentt_team_stats`
- `opentt_player_transfers`
- `opentt_player_info`
- `opentt_competitions`
- `opentt_clubs`

## 2) Admin Action Hooks (`admin_post_*`)

These hooks are integration points for admin forms and flows:

- `opentt_unified_save_match`
- `opentt_unified_delete_match`
- `opentt_unified_delete_matches_bulk`
- `opentt_unified_save_game`
- `opentt_unified_save_games_batch`
- `opentt_unified_delete_game`
- `opentt_unified_save_set`
- `opentt_unified_delete_set`
- `opentt_unified_save_club`
- `opentt_unified_delete_club`
- `opentt_unified_delete_clubs_bulk`
- `opentt_unified_save_player`
- `opentt_unified_delete_player`
- `opentt_unified_delete_players_bulk`
- `opentt_unified_save_league`
- `opentt_unified_delete_league`
- `opentt_unified_save_season`
- `opentt_unified_delete_season`
- `opentt_unified_save_competition_rule`
- `opentt_unified_delete_competition_rule`
- `opentt_unified_save_settings`
- `opentt_unified_delete_all_data`
- `opentt_unified_onboarding_action`
- `opentt_unified_export_data`
- `opentt_unified_import_validate`
- `opentt_unified_import_commit`
- `opentt_unified_reset_competition_matches`
- `opentt_unified_competition_diagnostics`
- `opentt_unified_repair_competition_played`
- `opentt_unified_migrate_batch`
- `opentt_unified_reset_migration`
- `opentt_unified_validate_import`
- `opentt_unified_repair_relations`
- `opentt_unified_cleanup_placeholders`

## 3) Admin Page Slugs

Current slugs are still `stkb-unified*` and should remain unchanged until a dedicated migration phase:

- `stkb-unified`
- `stkb-unified-matches`
- `stkb-unified-clubs`
- `stkb-unified-players`
- `stkb-unified-competitions`
- `stkb-unified-transfer`
- `stkb-unified-customize`
- `stkb-unified-settings`
- `stkb-unified-add-match`
- `stkb-unified-add-club`
- `stkb-unified-add-player`
- `stkb-unified-add-competition`
- `stkb-unified-onboarding`
- `stkb-unified-leagues`
- `stkb-unified-seasons`
- `stkb-unified-add-league`
- `stkb-unified-add-season`

## 4) CPT and Taxonomy Keys

Current keys are data contracts and should be preserved:

- Post types: `klub`, `igrac`, `liga`, `sezona`, `pravilo_takmicenja`
- Taxonomies: `liga_sezona`, `kolo`

## 5) Option Keys

Primary option namespace:

- `opentt_unified_*`

Known stable keys in code:

- `opentt_unified_schema_version`
- `opentt_unified_migration_state`
- `opentt_unified_validation_report`
- `opentt_unified_league_season_validation_report`
- `opentt_unified_legacy_id_map`
- `opentt_unified_player_citizenship_backfill_done`
- `opentt_unified_custom_shortcode_css`
- `opentt_unified_custom_shortcode_css_map`
- `opentt_unified_visual_settings`
- `opentt_unified_default_pages_setup_done`
- `opentt_unified_onboarding_state`
- `opentt_unified_rewrite_flushed`
- `opentt_unified_show_shortcode_titles`
- `opentt_unified_import_preview`
- `opentt_unified_competition_diagnostics`
- `opentt_unified_admin_ui_language`

## 6) Meta Keys

Competition rules (`pravilo_takmicenja`) stable meta keys:

- `opentt_competition_league_slug`
- `opentt_competition_season_slug`
- `opentt_competition_match_format`
- `opentt_competition_scoring_type`
- `opentt_competition_promotion_slots`
- `opentt_competition_promotion_playoff_slots`
- `opentt_competition_relegation_slots`
- `opentt_competition_relegation_playoff_slots`
- `opentt_competition_federation`
- `opentt_competition_rank`

Internal legacy mapping/support keys:

- `_opentt_legacy_ref_id`
- `_opentt_import_source_attachment_id`

Legacy domain meta still in use (future migration phase required):

- Club/player/match legacy keys (for example `opstina`, `drzavljanstvo`, `klub_domacina`, `igrac_domacin`, `setovi`, etc.).

## 7) DB Tables

Canonical tables:

- `opentt_matches`
- `opentt_games`
- `opentt_sets`

Legacy tables still supported via runtime resolver:

- `stkb_matches`
- `stkb_games`
- `stkb_sets`

## 8) Guardrails for Next Phases

- No silent rename of public tags, hooks, slugs, option/meta keys, or table names.
- Any contract change must include:
- migration script (or runtime compatibility bridge)
- changelog note
- rollback path
