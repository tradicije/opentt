<?php
/**
 * OpenTT - Table Tennis Management Plugin
 * Copyright (C) 2026 Aleksa Dimitrijević
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 */


if (!defined('ABSPATH')) {
    exit;
}

trait OpenTT_Unified_Shortcodes_Trait
{
    public static function shortcode_auth($atts = [])
    {
        unset($atts);
        return \OpenTT\Unified\WordPress\UserPortalManager::renderAuthShortcode();
    }

    public static function shortcode_auth_menu($atts = [])
    {
        unset($atts);
        return \OpenTT\Unified\WordPress\UserPortalManager::renderAuthMenuShortcode();
    }

    public static function shortcode_profile($atts = [])
    {
        unset($atts);
        return \OpenTT\Unified\WordPress\UserPortalManager::renderProfileShortcode();
    }

    private static function shortcode_title_html($title)
    {
        return OpenTT_Unified_Shortcode_UI_Service::shortcode_title_html($title);
    }

    public static function shortcode_matches_grid($atts)
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchesGridShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'build_match_query_args' => static function ($args) {
                return self::build_match_query_args($args);
            },
            'db_get_matches' => static function ($args) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_matches($args);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'render_matches_grid_html' => static function ($rows, $columns, $with_kolo_attr) {
                return self::render_matches_grid_html($rows, $columns, $with_kolo_attr);
            },
        ]);
    }

    public static function shortcode_matches_grid_alt($atts)
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchesGridAltShortcode::render($atts, [
            'render_default' => static function ($inner_atts) {
                return self::shortcode_matches_grid($inner_atts);
            },
        ]);
    }

    public static function shortcode_matches($atts)
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchesShortcode::render($atts, [
            'render_matches_grid' => static function ($inner_atts) {
                return self::shortcode_matches_grid($inner_atts);
            },
            'render_matches_list' => static function ($inner_atts) {
                return self::shortcode_matches_list($inner_atts);
            },
        ]);
    }

    public static function shortcode_search($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\SearchShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_get_latest_liga_for_club_and_season' => static function ($club_id, $season_slug = '') {
                return self::db_get_latest_liga_for_club_and_season($club_id, $season_slug);
            },
        ]);
    }

    public static function shortcode_matches_list($atts)
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchesListShortcode::render($atts, [
            'build_match_query_args' => static function ($args) {
                return self::build_match_query_args($args);
            },
            'db_get_matches' => static function ($args) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_matches($args);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'display_match_time' => static function ($match_date) {
                return self::display_match_time($match_date);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
        ]);
    }

    public static function shortcode_match_id($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchIdShortcode::render($atts, [
            'db_get_match_by_id' => static function ($id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_match_by_id($id);
            },
            'build_match_query_args' => static function ($args) {
                return self::build_match_query_args($args);
            },
            'db_get_matches' => static function ($args) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_matches($args);
            },
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'display_match_time' => static function ($match_date) {
                return self::display_match_time($match_date);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'parse_match_timestamp' => static function ($match_date, $end_of_day_if_midnight = false) {
                return self::parse_match_timestamp($match_date, $end_of_day_if_midnight);
            },
        ]);
    }

    public static function shortcode_featured_match($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\FeaturedMatchShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_build_standings_for_competition' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_build_standings_for_competition($liga_slug, $sezona_slug, $max_kolo);
            },
            'build_match_query_args' => static function ($args) {
                return self::build_match_query_args($args);
            },
        ]);
    }

    public static function shortcode_featured_player($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\FeaturedPlayerShortcode::render($atts, [
            'db_get_top_players_data' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
            },
            'db_get_top_players_data_unfiltered' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_get_top_players_data_unfiltered($liga_slug, $sezona_slug, $max_kolo);
            },
            'db_get_latest_competition_for_club' => static function ($club_id) {
                return self::db_get_latest_competition_for_club($club_id);
            },
            'db_get_latest_competition_for_player' => static function ($player_id) {
                return self::db_get_latest_competition_for_player($player_id);
            },
            'db_get_latest_season_for_liga' => static function ($liga_slug) {
                return self::db_get_latest_season_for_liga($liga_slug);
            },
            'db_get_latest_competition_with_games' => static function () {
                return self::db_get_latest_competition_with_games();
            },
            'get_player_club_id' => static function ($player_id) {
                return self::get_player_club_id($player_id);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_clubs_grid($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubsGridShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'db_get_latest_competition_for_club' => static function ($club_id) {
                return self::db_get_latest_competition_for_club($club_id);
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'render_clubs_grid_html' => static function ($rows, $columns, $with_attrs) {
                return self::render_clubs_grid_html($rows, $columns, $with_attrs);
            },
        ]);
    }

    public static function shortcode_show_players($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowPlayersShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
            'db_get_club_player_ids_for_season' => static function ($club_id, $season_slug = '') {
                return self::db_get_club_player_ids_for_season($club_id, $season_slug);
            },
        ]);
    }

    public static function shortcode_club_news($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubNewsShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_player_news($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\PlayerNewsShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_related_posts($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\RelatedPostsShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_standings_table($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\StandingsTableShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_get_match_by_legacy_id' => static function ($legacy_id) {
                return self::db_get_match_by_legacy_id($legacy_id);
            },
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'db_get_latest_liga_for_club' => static function ($club_id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_latest_liga_for_club($club_id);
            },
            'db_table' => static function ($table_alias) {
                return OpenTT_Unified_Core::db_table($table_alias);
            },
            'table_exists' => static function ($table_name) {
                return self::table_exists($table_name);
            },
            'db_get_matches' => static function ($args) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_matches($args);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    public static function shortcode_standings_short($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\StandingsShortShortcode::render($atts, [
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_build_standings_for_competition' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_build_standings_for_competition($liga_slug, $sezona_slug, $max_kolo);
            },
            'find_club_rank_in_standings' => static function ($standings, $club_id) {
                return self::find_club_rank_in_standings($standings, $club_id);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'db_get_latest_liga_for_club' => static function ($club_id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_latest_liga_for_club($club_id);
            },
            'db_get_latest_liga_for_club_and_season' => static function ($club_id, $season_slug = '') {
                return self::db_get_latest_liga_for_club_and_season($club_id, $season_slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    public static function shortcode_games_list($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\GamesListShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'db_get_match_by_id' => static function ($id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_match_by_id($id);
            },
            'db_get_games_for_match_id' => static function ($match_id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_games_for_match_id($match_id);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'db_get_sets_for_game_id' => static function ($game_id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_sets_for_game_id($game_id);
            },
            'render_lp2_player' => static function ($player_id) {
                return self::render_lp2_player($player_id);
            },
            'players_for_club_options' => static function ($club_id) {
                return OpenTT_Unified_Admin_Readonly_Helpers::players_for_club_options($club_id);
            },
            'turnstile_enabled' => static function () {
                return OpenTT_Unified_Core::is_turnstile_enabled();
            },
            'turnstile_site_key' => static function () {
                return OpenTT_Unified_Core::turnstile_site_key();
            },
            'games_submit_page_url' => static function ($match_id) {
                return OpenTT_Unified_Core::games_submit_page_url($match_id);
            },
            'render_match_teams_for_row' => static function ($row) {
                if (!is_object($row)) {
                    return '';
                }
                $ctx = [
                    'db_row' => $row,
                    'legacy_id' => intval($row->legacy_post_id ?? 0),
                ];
                return \OpenTT\Unified\WordPress\Shortcodes\ShowMatchTeamsShortcode::render([], [
                    'current_match_context' => static function () use ($ctx) {
                        return $ctx;
                    },
                    'competition_display_name' => static function ($liga_slug, $sezona_slug) {
                        return self::competition_display_name($liga_slug, $sezona_slug);
                    },
                    'competition_archive_url' => static function ($liga_slug, $sezona_slug) {
                        return self::competition_archive_url($liga_slug, $sezona_slug);
                    },
                    'kolo_name_from_slug' => static function ($slug) {
                        return self::kolo_name_from_slug($slug);
                    },
                    'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                        return self::club_logo_html($club_id, $size, $attr);
                    },
                    'display_match_date' => static function ($match_date) {
                        return self::display_match_date($match_date);
                    },
                    'match_venue_label' => static function ($row_in) {
                        return self::match_venue_label($row_in);
                    },
                    'shortcode_title_html' => static function ($title) {
                        return '';
                    },
                ]);
            },
        ]);
    }

    public static function shortcode_h2h($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\H2hShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'db_get_h2h_matches' => static function ($current_match_db_id, $home_club_id, $away_club_id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_h2h_matches($current_match_db_id, $home_club_id, $away_club_id);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'club_logo_url' => static function ($club_id, $size = 'thumbnail') {
                return self::club_logo_url($club_id, $size);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'display_match_date_long' => static function ($match_date) {
                return self::display_match_date_long($match_date);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
        ]);
    }

    public static function shortcode_mvp($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MvpShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'db_get_games_for_match_id' => static function ($match_id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_games_for_match_id($match_id);
            },
            'db_get_sets_for_game_id' => static function ($game_id) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_sets_for_game_id($game_id);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
            'club_logo_url' => static function ($club_id, $size = 'thumbnail') {
                return self::club_logo_url($club_id, $size);
            },
        ]);
    }

    public static function shortcode_match_report($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchReportShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_match_video($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchVideoShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_match_teams_short($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\MatchTeamsShortShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_show_home_club($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowHomeClubShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'render_klub_card_html' => static function ($club_id) {
                return self::render_klub_card_html($club_id);
            },
        ]);
    }

    public static function shortcode_show_away_club($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowAwayClubShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'render_klub_card_html' => static function ($club_id) {
                return self::render_klub_card_html($club_id);
            },
        ]);
    }

    public static function shortcode_show_club_by_name($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowClubByNameShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'render_klub_card_html' => static function ($club_id) {
                return self::render_klub_card_html($club_id);
            },
        ]);
    }

    public static function shortcode_club_form($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubFormShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'db_get_recent_club_matches' => static function ($club_id, $limit) {
                return self::db_get_recent_club_matches($club_id, $limit);
            },
            'db_get_recent_club_matches_for_season' => static function ($club_id, $limit, $season_slug = '') {
                return self::db_get_recent_club_matches_for_season($club_id, $limit, $season_slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
        ]);
    }

    public static function shortcode_player_stats($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\PlayerStatsShortcode::render($atts, [
            'db_get_player_season_options' => static function ($player_id) {
                return self::db_get_player_season_options($player_id);
            },
            'db_get_player_stats' => static function ($player_id, $season_slug = '') {
                return self::db_get_player_stats($player_id, $season_slug);
            },
            'db_get_player_mvp_count' => static function ($player_id, $season_slug = '') {
                return self::db_get_player_mvp_count($player_id, $season_slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'db_get_latest_competition_for_player' => static function ($player_id) {
                return self::db_get_latest_competition_for_player($player_id);
            },
            'db_get_latest_liga_for_player_and_season' => static function ($player_id, $season_slug = '') {
                return self::db_get_latest_liga_for_player_and_season($player_id, $season_slug);
            },
            'db_get_top_players_data' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
            },
            'render_top_player_card_list' => static function ($player_id, $rank, $info, $highlight = false) {
                return self::render_top_player_card_list($player_id, $rank, $info, $highlight);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_team_stats($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\TeamStatsShortcode::render($atts, [
            'db_get_club_season_options' => static function ($club_id) {
                return self::db_get_club_season_options($club_id);
            },
            'db_get_club_team_stats' => static function ($club_id, $season_slug = '') {
                return self::db_get_club_team_stats($club_id, $season_slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'db_get_latest_competition_for_club' => static function ($club_id) {
                return self::db_get_latest_competition_for_club($club_id);
            },
            'db_get_club_season_best_player_by_success' => static function ($club_id, $season_slug) {
                return self::db_get_club_season_best_player_by_success($club_id, $season_slug);
            },
            'db_get_latest_liga_for_club_and_season' => static function ($club_id, $season_slug = '') {
                return self::db_get_latest_liga_for_club_and_season($club_id, $season_slug);
            },
            'db_build_standings_for_competition' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_build_standings_for_competition($liga_slug, $sezona_slug, $max_kolo);
            },
            'find_club_rank_in_standings' => static function ($standings, $club_id) {
                return self::find_club_rank_in_standings($standings, $club_id);
            },
            'build_standings_window_around_club' => static function ($standings, $club_rank, $radius = 2) {
                return self::build_standings_window_around_club($standings, $club_rank, $radius);
            },
            'competition_display_name' => static function ($liga_slug, $sezona_slug) {
                return self::competition_display_name($liga_slug, $sezona_slug);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'format_percentage_value' => static function ($value) {
                return self::format_percentage_value($value);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    public static function shortcode_global_stats($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\GlobalStatsShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_player_transfers($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\PlayerTransfersShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'db_get_player_season_club_history' => static function ($player_id) {
                return self::db_get_player_season_club_history($player_id);
            },
            'build_player_stints' => static function ($history) {
                return self::build_player_stints($history);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    public static function shortcode_club_featured($atts = [])
    {
        $atts = shortcode_atts([
            'klub' => '',
            'id' => '',
            'height' => '',
            'link' => 'false',
        ], (array) $atts, 'opentt_club_featured');

        $club_id = 0;
        $id_raw = trim((string) ($atts['id'] ?? ''));
        if ($id_raw !== '' && is_numeric($id_raw)) {
            $club_id = intval($id_raw);
            if ($club_id > 0 && get_post_type($club_id) !== 'klub') {
                $club_id = 0;
            }
        }

        if ($club_id <= 0) {
            $club_id = self::resolve_club_id_from_value((string) ($atts['klub'] ?? ''));
        }

        if ($club_id <= 0 && is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if ($club_id <= 0) {
            $ctx = self::current_match_context();
            if (is_array($ctx) && !empty($ctx['db_row'])) {
                $club_id = intval($ctx['db_row']->home_club_post_id ?? 0);
            }
        }

        if ($club_id <= 0 || get_post_type($club_id) !== 'klub') {
            return '';
        }

        $image_id = intval(get_post_meta($club_id, 'opentt_club_featured_image_id', true));
        $image_url = $image_id > 0 ? wp_get_attachment_image_url($image_id, 'full') : '';
        if (!is_string($image_url)) {
            $image_url = '';
        }
        if ($image_url === '') {
            return '';
        }

        $focus_x_raw = floatval(get_post_meta($club_id, 'opentt_club_featured_focus_x', true));
        $focus_y_raw = floatval(get_post_meta($club_id, 'opentt_club_featured_focus_y', true));
        $focus_x = ($focus_x_raw >= 0 && $focus_x_raw <= 100) ? $focus_x_raw : 50.0;
        $focus_y = ($focus_y_raw >= 0 && $focus_y_raw <= 100) ? $focus_y_raw : 50.0;

        $title = (string) get_the_title($club_id);
        $raw_height = intval($atts['height'] ?? 0);
        $height = $raw_height > 0 ? max(1, min(2000, $raw_height)) : 0;
        $style_parts = [
            '--opentt-club-featured-focus-x:' . $focus_x . '%',
            '--opentt-club-featured-focus-y:' . $focus_y . '%',
        ];
        if ($height > 0) {
            $style_parts[] = '--opentt-club-featured-height:' . $height . 'px';
        }
        $style_attr = ' style="' . esc_attr(implode(';', $style_parts)) . '"';
        $wrap_class = 'opentt-club-featured-wrap' . ($height > 0 ? ' is-fixed-height' : '');
        $link_raw = strtolower(trim((string) ($atts['link'] ?? 'false')));
        $link_enabled = !in_array($link_raw, ['0', 'false', 'no', 'off'], true);
        $url = get_permalink($club_id);

        ob_start();
        echo '<div class="' . esc_attr($wrap_class) . '"' . $style_attr . '>';
        if ($link_enabled && is_string($url) && $url !== '') {
            echo '<a class="opentt-club-featured-media" href="' . esc_url($url) . '">';
        } else {
            echo '<div class="opentt-club-featured-media">';
        }
        echo '<img class="opentt-club-featured-image" src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '">';
        if ($link_enabled && is_string($url) && $url !== '') {
            echo '</a>';
        } else {
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    public static function shortcode_club_info($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubInfoShortcode::render($atts, [
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'db_get_latest_competition_for_club' => static function ($club_id) {
                return self::db_get_latest_competition_for_club($club_id);
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'competition_federation_data' => static function ($code) {
                return self::competition_federation_data($code);
            },
            'info_link_icon_html' => static function ($icon_file_name, $fallback, $modifier = 'before') {
                return self::info_link_icon_html($icon_file_name, $fallback, $modifier);
            },
            'normalize_phone_for_href' => static function ($raw_phone) {
                return self::normalize_phone_for_href($raw_phone);
            },
            'format_phone_for_display' => static function ($raw_phone) {
                return self::format_phone_for_display($raw_phone);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_club_card($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ClubCardShortcode::render($atts, [
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'db_get_club_season_options' => static function ($club_id) {
                return self::db_get_club_season_options($club_id);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    private static function info_link_icon_html($icon_file_name, $fallback, $modifier = 'before')
    {
        return OpenTT_Unified_Shortcode_UI_Service::info_link_icon_html($icon_file_name, $fallback, $modifier, (string) self::$plugin_dir);
    }

    public static function shortcode_player_info($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\PlayerInfoShortcode::render($atts, [
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
            'get_player_club_id' => static function ($player_id) {
                return self::get_player_club_id($player_id);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'country_label_by_code' => static function ($country_code) {
                return OpenTT_Unified_Core::country_label_by_code($country_code);
            },
            'country_flag_emoji' => static function ($country_code) {
                return OpenTT_Unified_Core::country_flag_emoji($country_code);
            },
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    private static function normalize_phone_for_href($raw_phone)
    {
        return OpenTT_Unified_Readonly_Helpers::normalize_phone_for_href($raw_phone);
    }

    private static function format_phone_for_display($raw_phone)
    {
        return OpenTT_Unified_Readonly_Helpers::format_phone_for_display($raw_phone);
    }

    public static function shortcode_show_match_teams($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\ShowMatchTeamsShortcode::render($atts, [
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'competition_display_name' => static function ($liga_slug, $sezona_slug) {
                return self::competition_display_name($liga_slug, $sezona_slug);
            },
            'competition_archive_url' => static function ($liga_slug, $sezona_slug) {
                return self::competition_archive_url($liga_slug, $sezona_slug);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'match_venue_label' => static function ($row) {
                return self::match_venue_label($row);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_competition_info($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\CompetitionInfoShortcode::render($atts, [
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_table' => static function ($table_alias) {
                return OpenTT_Unified_Core::db_table($table_alias);
            },
            'table_exists' => static function ($table_name) {
                return self::table_exists($table_name);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'get_competition_rule_data' => static function ($liga_slug, $sezona_slug = '') {
                return self::get_competition_rule_data($liga_slug, $sezona_slug);
            },
            'competition_federation_data' => static function ($code) {
                return self::competition_federation_data($code);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
        ]);
    }

    public static function shortcode_competitions_grid($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\CompetitionsGridShortcode::render($atts, [
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'season_sort_key' => static function ($season_slug) {
                return self::season_sort_key($season_slug);
            },
            'season_display_name' => static function ($sezona_slug) {
                return self::season_display_name($sezona_slug);
            },
            'competition_archive_url' => static function ($liga_slug, $sezona_slug) {
                return self::competition_archive_url($liga_slug, $sezona_slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
            'db_get_competition_club_ids' => static function ($liga_slug, $sezona_slug = '') {
                return self::db_get_competition_club_ids($liga_slug, $sezona_slug);
            },
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
        ]);
    }

    private static function competition_display_name($liga_slug, $sezona_slug)
    {
        return OpenTT_Unified_Competition_Presentation_Service::competition_display_name($liga_slug, $sezona_slug, [
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
        ]);
    }

    private static function season_display_name($sezona_slug)
    {
        return OpenTT_Unified_Competition_Presentation_Service::season_display_name($sezona_slug, [
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
        ]);
    }

    private static function competition_archive_url($liga_slug, $sezona_slug)
    {
        return OpenTT_Unified_Competition_Presentation_Service::competition_archive_url($liga_slug, $sezona_slug);
    }

    private static function match_venue_label($row)
    {
        return OpenTT_Unified_Match_Presentation_Service::match_venue_label($row);
    }

    public static function shortcode_top_players_list($atts = [])
    {
        return \OpenTT\Unified\WordPress\Shortcodes\TopPlayersListShortcode::render($atts, [
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga_slug, $sezona_slug = '') {
                return self::parse_legacy_liga_sezona($liga_slug, $sezona_slug);
            },
            'db_table' => static function ($table_alias) {
                return OpenTT_Unified_Core::db_table($table_alias);
            },
            'table_exists' => static function ($table_name) {
                return self::table_exists($table_name);
            },
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'current_match_context' => static function () {
                return self::current_match_context();
            },
            'db_get_latest_competition_for_player' => static function ($player_id) {
                return self::db_get_latest_competition_for_player($player_id);
            },
            'db_get_latest_competition_with_games' => static function () {
                return self::db_get_latest_competition_with_games();
            },
            'db_get_top_players_data' => static function ($liga_slug, $sezona_slug = '', $max_kolo = null) {
                return self::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
            },
            'shortcode_title_html' => static function ($title) {
                return self::shortcode_title_html($title);
            },
            'render_top_player_card_list' => static function ($player_id, $rank, $info, $highlight = false) {
                return self::render_top_player_card_list($player_id, $rank, $info, $highlight);
            },
        ]);
    }

    private static function db_get_top_players_data($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_top_players_data($liga_slug, $sezona_slug, $max_kolo);
    }

    private static function db_get_top_players_data_unfiltered($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_top_players_data_unfiltered($liga_slug, $sezona_slug, $max_kolo);
    }

    private static function db_get_played_matches_count_by_club($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_played_matches_count_by_club($liga_slug, $sezona_slug, $max_kolo);
    }

    private static function db_get_latest_competition_with_games()
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_with_games();
    }

    private static function db_get_latest_competition_for_player($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_for_player($player_id);
    }

    private static function db_get_latest_season_for_liga($liga_slug)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_season_for_liga($liga_slug);
    }

    private static function db_get_latest_competition_for_club($club_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_for_club($club_id);
    }

    private static function db_get_recent_club_matches($club_id, $limit = 5)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_recent_club_matches($club_id, $limit);
    }

    private static function db_get_recent_club_matches_for_season($club_id, $limit = 5, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_recent_club_matches_for_season($club_id, $limit, $season_slug);
    }

    private static function db_get_club_player_ids_for_season($club_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_player_ids_for_season($club_id, $season_slug);
    }

    private static function db_get_player_season_club_history($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_season_club_history($player_id);
    }

    private static function season_sort_key($season_slug)
    {
        return OpenTT_Unified_Readonly_Helpers::season_sort_key($season_slug);
    }

    private static function build_player_stints($history)
    {
        return OpenTT_Unified_Player_History_Service::build_player_stints($history);
    }

    private static function db_get_player_stats($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_stats($player_id, $season_slug);
    }

    private static function db_get_player_season_options($player_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_season_options($player_id);
    }

    private static function db_get_club_season_options($club_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_season_options($club_id);
    }

    private static function db_get_latest_liga_for_player_and_season($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_liga_for_player_and_season($player_id, $season_slug);
    }

    private static function db_get_latest_liga_for_club_and_season($club_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_liga_for_club_and_season($club_id, $season_slug);
    }

    private static function db_get_club_team_stats($club_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_team_stats($club_id, $season_slug);
    }

    private static function db_get_club_season_best_player_by_success($club_id, $season_slug)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_club_season_best_player_by_success($club_id, $season_slug);
    }

    private static function db_get_competition_club_ids($liga_slug, $sezona_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_competition_club_ids($liga_slug, $sezona_slug);
    }

    private static function db_build_standings_for_competition($liga_slug, $sezona_slug = '', $max_kolo = null)
    {
        return OpenTT_Unified_Shortcode_Standings_Service::db_build_standings_for_competition($liga_slug, $sezona_slug, $max_kolo, [
            'db_get_matches' => static function ($args) {
                return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_matches($args);
            },
            'get_competition_rule_data' => static function ($liga_slug_arg, $sezona_slug_arg = '') {
                return self::get_competition_rule_data($liga_slug_arg, $sezona_slug_arg);
            },
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
        ]);
    }

    private static function find_club_rank_in_standings($standings, $club_id)
    {
        return OpenTT_Unified_Shortcode_Standings_Service::find_club_rank_in_standings($standings, $club_id);
    }

    private static function build_standings_window_around_club($standings, $club_rank, $radius = 2)
    {
        return OpenTT_Unified_Shortcode_Standings_Service::build_standings_window_around_club($standings, $club_rank, $radius);
    }

    private static function format_percentage_value($value)
    {
        return OpenTT_Unified_Shortcode_Standings_Service::format_percentage_value($value);
    }

    private static function db_get_player_mvp_count($player_id, $season_slug = '')
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_player_mvp_count($player_id, $season_slug);
    }

    private static function db_get_match_mvp_player_id($match_id)
    {
        return OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_match_mvp_player_id($match_id);
    }

    private static function render_top_player_card_list($igrac_id, $rank, $info, $highlight = false)
    {
        return OpenTT_Unified_Shortcode_UI_Service::render_top_player_card_list($igrac_id, $rank, $info, $highlight, [
            'club_logo_html' => static function ($club_id, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id, $size, $attr);
            },
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
        ]);
    }

    private static function get_validation_report()
    {
        $report = get_option(self::OPTION_VALIDATION_REPORT, []);
        return is_array($report) ? $report : [];
    }

    private static function build_match_query_args($atts)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::build_match_query_args($atts, [
            'current_archive_context' => static function () {
                return self::current_archive_context();
            },
            'parse_legacy_liga_sezona' => static function ($liga, $sezona) {
                return self::parse_legacy_liga_sezona($liga, $sezona);
            },
        ]);
    }

    private static function db_get_match_by_legacy_id($legacy_id)
    {
        return OpenTT_Unified_Shortcode_Match_Query_Service::db_get_match_by_legacy_id($legacy_id);
    }

    private static function render_matches_grid_html($rows, $columns, $with_kolo_attr)
    {
        return OpenTT_Unified_Grid_Render_Service::render_matches_grid_html($rows, $columns, $with_kolo_attr, [
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'kolo_heading_label' => static function ($slug, $round_no = null) {
                return self::kolo_heading_label($slug, $round_no);
            },
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'display_match_time' => static function ($match_date) {
                return self::display_match_time($match_date);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
            'parse_match_timestamp' => static function ($match_date, $end_of_day_if_midnight = false) {
                return self::parse_match_timestamp($match_date, $end_of_day_if_midnight);
            },
            'is_match_live' => static function ($row) {
                return self::is_match_live($row);
            },
            'render_team_html' => static function ($club_id, $score, $is_winner, $show_score = true, $fallback_score_label = '') {
                return self::render_team_html($club_id, $score, $is_winner, $show_score, $fallback_score_label);
            },
        ]);
    }

    private static function render_clubs_grid_html($rows, $columns, $with_attrs)
    {
        return OpenTT_Unified_Grid_Render_Service::render_clubs_grid_html($rows, $columns, $with_attrs);
    }

    private static function club_fallback_image_url()
    {
        return OpenTT_Unified_Media_Service::club_fallback_image_url(self::$plugin_dir, self::$plugin_file);
    }

    private static function player_fallback_image_url()
    {
        return OpenTT_Unified_Media_Service::player_fallback_image_url(self::$plugin_dir, self::$plugin_file);
    }

    private static function club_logo_url($club_id, $size = 'thumbnail')
    {
        return OpenTT_Unified_Media_Service::club_logo_url($club_id, $size, [
            'club_fallback_image_url' => static function () {
                return self::club_fallback_image_url();
            },
        ]);
    }

    private static function resolve_club_id_from_value($value)
    {
        return OpenTT_Unified_Media_Service::resolve_club_id_from_value($value);
    }

    private static function club_logo_html($club_id, $size = 'thumbnail', $attr = [])
    {
        return OpenTT_Unified_Media_Service::club_logo_html($club_id, $size, $attr, [
            'club_fallback_image_url' => static function () {
                return self::club_fallback_image_url();
            },
        ]);
    }

    private static function render_team_html($club_id, $score, $is_winner, $show_score = true, $fallback_score_label = '')
    {
        return OpenTT_Unified_Entity_Presentation_Service::render_team_html($club_id, $score, $is_winner, $show_score, $fallback_score_label, [
            'club_logo_html' => static function ($club_id_arg, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id_arg, $size, $attr);
            },
        ]);
    }

    private static function display_match_time($match_date)
    {
        return OpenTT_Unified_Match_Presentation_Service::display_match_time($match_date);
    }

    private static function kolo_heading_label($kolo_slug, $kolo_no = null)
    {
        return OpenTT_Unified_Competition_Presentation_Service::kolo_heading_label($kolo_slug, $kolo_no, [
            'extract_round_no' => static function ($slug) {
                return self::extract_round_no($slug);
            },
            'slug_to_title' => static function ($slug) {
                return self::slug_to_title($slug);
            },
        ]);
    }

    private static function is_match_live($row)
    {
        return OpenTT_Unified_Match_Presentation_Service::is_match_live($row);
    }

    private static function parse_match_timestamp($match_date, $end_of_day_if_midnight = false)
    {
        return OpenTT_Unified_Match_Presentation_Service::parse_match_timestamp($match_date, $end_of_day_if_midnight);
    }

    private static function match_permalink($row)
    {
        return OpenTT_Unified_Match_Presentation_Service::match_permalink($row, [
            'is_legacy_match_cpt_enabled' => static function () {
                return self::is_legacy_match_cpt_enabled();
            },
        ]);
    }

    private static function display_match_date($match_date)
    {
        return OpenTT_Unified_Match_Presentation_Service::display_match_date($match_date);
    }

    private static function display_match_date_long($match_date)
    {
        return OpenTT_Unified_Match_Presentation_Service::display_match_date_long($match_date);
    }

    private static function kolo_name_from_slug($slug)
    {
        return OpenTT_Unified_Competition_Presentation_Service::kolo_name_from_slug($slug, [
            'extract_round_no' => static function ($kolo_slug) {
                return self::extract_round_no($kolo_slug);
            },
        ]);
    }

    private static function extract_round_no($kolo_slug)
    {
        return OpenTT_Unified_Readonly_Helpers::extract_round_no($kolo_slug);
    }

    private static function render_lp2_player($player_id)
    {
        return OpenTT_Unified_Entity_Presentation_Service::render_lp2_player($player_id, [
            'player_fallback_image_url' => static function () {
                return self::player_fallback_image_url();
            },
        ]);
    }

    private static function render_klub_card_html($klub_id)
    {
        return OpenTT_Unified_Entity_Presentation_Service::render_klub_card_html($klub_id, [
            'club_logo_html' => static function ($club_id_arg, $size = 'thumbnail', $attr = []) {
                return self::club_logo_html($club_id_arg, $size, $attr);
            },
        ]);
    }

    private static function current_match_context()
    {
        return OpenTT_Unified_Match_Context_Service::current_match_context(
            self::$virtual_match_row,
            static function ($legacy_id) {
                return self::db_get_match_by_legacy_id($legacy_id);
            }
        );
    }

    public static function get_template_match_context()
    {
        $ctx = self::current_match_context();
        return OpenTT_Unified_Match_Context_Service::get_template_match_context($ctx, [
            'display_match_date' => static function ($match_date) {
                return self::display_match_date($match_date);
            },
            'kolo_name_from_slug' => static function ($slug) {
                return self::kolo_name_from_slug($slug);
            },
            'match_permalink' => static function ($row) {
                return self::match_permalink($row);
            },
        ]);
    }

    private static function get_match_block_template()
    {
        return OpenTT_Unified_Match_Context_Service::get_match_block_template(self::MATCH_BLOCK_TEMPLATE_SLUG);
    }

}
