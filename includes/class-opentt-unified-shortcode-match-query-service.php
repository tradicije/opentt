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

final class OpenTT_Unified_Shortcode_Match_Query_Service
{
    public static function build_match_query_args($atts, $deps = [])
    {
        $get_archive_context = isset($deps['current_archive_context']) && is_callable($deps['current_archive_context'])
            ? $deps['current_archive_context']
            : static function () {
                return [];
            };
        $parse_legacy_liga_sezona = isset($deps['parse_legacy_liga_sezona']) && is_callable($deps['parse_legacy_liga_sezona'])
            ? $deps['parse_legacy_liga_sezona']
            : static function ($liga, $sezona) {
                return [
                    'league_slug' => sanitize_title((string) $liga),
                    'season_slug' => sanitize_title((string) $sezona),
                ];
            };

        $limit = isset($atts['limit']) ? intval($atts['limit']) : 5;
        $liga = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_from_atts = '';
        if (!empty($atts['season'])) {
            $sezona_from_atts = sanitize_title((string) $atts['season']);
        } elseif (!empty($atts['sezona'])) {
            $sezona_from_atts = sanitize_title((string) $atts['sezona']);
        }
        $sezona_from_context = '';
        $kolo = '';
        $club_id = 0;
        $player_id = 0;
        $archive_ctx = $get_archive_context();

        if ($liga === '') {
            if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
                $liga = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
            } elseif (is_tax('liga_sezona')) {
                $term = get_queried_object();
                if ($term && !is_wp_error($term) && !empty($term->slug)) {
                    $liga = sanitize_title((string) $term->slug);
                }
            } else {
                $liga_qv = get_query_var('liga_sezona');
                if ($liga_qv) {
                    $liga = sanitize_title((string) $liga_qv);
                }
            }
        }

        if ($sezona_from_atts === '') {
            if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
                $sezona_from_context = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            } elseif (is_tax('liga_sezona')) {
                $term = get_queried_object();
                if ($term && !is_wp_error($term) && !empty($term->slug)) {
                    $parsed_tax = $parse_legacy_liga_sezona((string) $term->slug, '');
                    $sezona_from_context = sanitize_title((string) ($parsed_tax['season_slug'] ?? ''));
                }
            } else {
                $sezona_qv = get_query_var('sezona');
                if ($sezona_qv) {
                    $sezona_from_context = sanitize_title((string) $sezona_qv);
                } elseif (isset($_GET['opentt_sezona'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $sezona_from_context = sanitize_title((string) wp_unslash($_GET['opentt_sezona'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                } elseif (isset($_GET['sezona'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $sezona_from_context = sanitize_title((string) wp_unslash($_GET['sezona'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                }
            }
        }

        if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'kolo') {
            $kolo = sanitize_title((string) ($archive_ctx['kolo_slug'] ?? ''));
        } elseif (is_tax('kolo')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $kolo = sanitize_title((string) $term->slug);
            }
        } else {
            $kolo_qv = get_query_var('kolo');
            if ($kolo_qv) {
                $kolo = sanitize_title((string) $kolo_qv);
            }
        }
        if (!empty($atts['kolo'])) {
            $kolo = sanitize_title((string) $atts['kolo']);
        }

        $raw_played = '';
        if (array_key_exists('played', (array) $atts)) {
            $raw_played = (string) ($atts['played'] ?? '');
        } elseif (array_key_exists('odigrana', (array) $atts)) {
            $raw_played = (string) ($atts['odigrana'] ?? '');
        }
        $played_filter = self::normalize_played_shortcode_attr($raw_played);

        if (!empty($atts['klub'])) {
            $club_slug_or_name = (string) $atts['klub'];
            $club = get_page_by_path(sanitize_title($club_slug_or_name), OBJECT, 'klub');
            if (!$club) {
                $club = get_page_by_title($club_slug_or_name, OBJECT, 'klub');
            }
            if ($club && !is_wp_error($club)) {
                $club_id = intval($club->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if (empty($atts['klub']) && is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        $resolved_sezona = $sezona_from_atts !== '' ? $sezona_from_atts : $sezona_from_context;
        $parsed = $parse_legacy_liga_sezona($liga, $resolved_sezona);
        $liga = sanitize_title((string) ($parsed['league_slug'] ?? $liga));
        $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $resolved_sezona));
        if ($sezona_from_atts !== '') {
            $sezona_slug = $sezona_from_atts;
        }

        return [
            'limit' => $limit,
            'liga_slug' => $liga,
            'sezona_slug' => $sezona_slug,
            'kolo_slug' => $kolo,
            'played' => $played_filter,
            'club_id' => $club_id,
            'player_id' => $player_id,
        ];
    }

    public static function normalize_played_shortcode_attr($value)
    {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return '';
        }

        $truthy = ['true', '1', 'yes', 'da', 'on'];
        $falsy = ['false', '0', 'no', 'ne', 'off'];

        foreach ($truthy as $token) {
            if ($value === $token || strpos($value, $token) === 0) {
                return '1';
            }
        }
        foreach ($falsy as $token) {
            if ($value === $token || strpos($value, $token) === 0) {
                return '0';
            }
        }

        return '';
    }

    public static function db_get_matches($args)
    {
        global $wpdb;
        $matches = OpenTT_Unified_Core::db_table('matches');
        $games = OpenTT_Unified_Core::db_table('games');

        $limit = isset($args['limit']) ? intval($args['limit']) : 5;
        $disable_legacy_fallback = !empty($args['_opentt_no_fallback']);
        $liga_slug = isset($args['liga_slug']) ? (string) $args['liga_slug'] : '';
        $sezona_slug = isset($args['sezona_slug']) ? (string) $args['sezona_slug'] : '';
        $kolo_slug = isset($args['kolo_slug']) ? (string) $args['kolo_slug'] : '';
        $played = isset($args['played']) ? (string) $args['played'] : '';
        $featured = isset($args['featured']) ? (string) $args['featured'] : '';
        $club_id = isset($args['club_id']) ? intval($args['club_id']) : 0;
        $player_id = isset($args['player_id']) ? intval($args['player_id']) : 0;

        $where = ['1=1'];
        $params = [];
        $join = '';
        $select = 'SELECT m.*';

        if ($player_id > 0) {
            $select = 'SELECT DISTINCT m.*';
            $join = " INNER JOIN {$games} g ON g.match_id = m.id ";
            $where[] = '(g.home_player_post_id=%d OR g.away_player_post_id=%d OR g.home_player2_post_id=%d OR g.away_player2_post_id=%d)';
            $params[] = $player_id;
            $params[] = $player_id;
            $params[] = $player_id;
            $params[] = $player_id;
        }

        if ($liga_slug !== '' && $sezona_slug !== '') {
            // Backward compatibility: support both normalized (liga_slug + sezona_slug)
            // and legacy combined storage where liga_slug already contains season.
            $legacy_combined = sanitize_title($liga_slug . '-' . $sezona_slug);
            $where[] = '((m.liga_slug=%s AND (m.sezona_slug=%s OR m.sezona_slug=%s OR m.sezona_slug IS NULL)) OR (m.liga_slug=%s AND (m.sezona_slug=%s OR m.sezona_slug=%s OR m.sezona_slug IS NULL)))';
            $params[] = $liga_slug;
            $params[] = $sezona_slug;
            $params[] = '';
            $params[] = $legacy_combined;
            $params[] = $sezona_slug;
            $params[] = '';
        } elseif ($liga_slug !== '') {
            $where[] = 'm.liga_slug=%s';
            $params[] = $liga_slug;
        } elseif ($sezona_slug !== '') {
            $where[] = 'm.sezona_slug=%s';
            $params[] = $sezona_slug;
        }
        if ($kolo_slug !== '') {
            $where[] = 'm.kolo_slug=%s';
            $params[] = $kolo_slug;
        }
        if ($played === '0' || $played === '1') {
            $where[] = 'm.played=%d';
            $params[] = intval($played);
        }
        if (($featured === '0' || $featured === '1') && self::has_featured_column($matches)) {
            $where[] = 'm.featured=%d';
            $params[] = intval($featured);
        }
        if ($club_id > 0) {
            $where[] = '(m.home_club_post_id=%d OR m.away_club_post_id=%d)';
            $params[] = $club_id;
            $params[] = $club_id;
        }

        $sql = $select . " FROM {$matches} m {$join} WHERE " . implode(' AND ', $where) . ' ORDER BY m.match_date DESC, m.id DESC';
        if ($limit !== -1) {
            $sql .= ' LIMIT %d';
            $params[] = max(1, $limit);
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql) ?: [];
        if (!empty($rows)) {
            return $rows;
        }

        // Fallback path: if explicit liga+sezona filter returns no rows, retry without
        // liga/sezona SQL constraints and normalize row slugs in PHP for legacy schemas.
        if (!$disable_legacy_fallback && $liga_slug !== '' && $sezona_slug !== '') {
            $fallback_args = $args;
            $fallback_args['liga_slug'] = '';
            $fallback_args['sezona_slug'] = '';
            $fallback_args['_opentt_no_fallback'] = 1;

            $candidate_rows = self::db_get_matches($fallback_args);
            if (!empty($candidate_rows)) {
                $filtered = array_values(array_filter($candidate_rows, static function ($row) use ($liga_slug, $sezona_slug) {
                    return self::row_matches_league_season($row, $liga_slug, $sezona_slug);
                }));
                if ($limit !== -1) {
                    return array_slice($filtered, 0, max(1, $limit));
                }
                return $filtered;
            }
        }

        return [];
    }

    public static function db_get_match_by_legacy_id($legacy_id)
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        $legacy_id = intval($legacy_id);
        if ($legacy_id <= 0 || !self::table_exists($table)) {
            return null;
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE legacy_post_id=%d
             ORDER BY
               COALESCE(
                   STR_TO_DATE(updated_at, '%%Y-%%m-%%d %%H:%%i:%%s'),
                   STR_TO_DATE(created_at, '%%Y-%%m-%%d %%H:%%i:%%s')
               ) DESC,
               id DESC
             LIMIT 1",
            $legacy_id
        );
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    public static function db_get_match_by_id($id)
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        $id = intval($id);
        if ($id <= 0 || !self::table_exists($table)) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id=%d LIMIT 1",
            $id
        );
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    public static function db_get_match_by_keys($liga_slug, $sezona_slug, $kolo_slug, $slug)
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        if (!self::table_exists($table)) {
            return null;
        }

        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        $kolo_slug = sanitize_title((string) $kolo_slug);
        $slug = sanitize_title((string) $slug);
        if ($liga_slug === '' || $kolo_slug === '' || $slug === '') {
            return null;
        }

        if ($sezona_slug !== '') {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE liga_slug=%s AND sezona_slug=%s AND kolo_slug=%s AND slug=%s
                 ORDER BY
                   COALESCE(
                       STR_TO_DATE(updated_at, '%%Y-%%m-%%d %%H:%%i:%%s'),
                       STR_TO_DATE(created_at, '%%Y-%%m-%%d %%H:%%i:%%s')
                   ) DESC,
                   id DESC
                 LIMIT 1",
                $liga_slug,
                $sezona_slug,
                $kolo_slug,
                $slug
            );
            $row = $wpdb->get_row($sql);
            if ($row) {
                return $row;
            }
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE liga_slug=%s AND kolo_slug=%s AND slug=%s
             ORDER BY
               COALESCE(
                   STR_TO_DATE(updated_at, '%%Y-%%m-%%d %%H:%%i:%%s'),
                   STR_TO_DATE(created_at, '%%Y-%%m-%%d %%H:%%i:%%s')
               ) DESC,
               id DESC
             LIMIT 1",
            $liga_slug,
            $kolo_slug,
            $slug
        );
        $row = $wpdb->get_row($sql);
        return $row ?: null;
    }

    public static function db_get_h2h_matches($current_match_db_id, $home_club_id, $away_club_id)
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        if (!self::table_exists($table)) {
            return [];
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE id <> %d AND (
                 (home_club_post_id=%d AND away_club_post_id=%d)
                 OR
                 (home_club_post_id=%d AND away_club_post_id=%d)
             )
             ORDER BY match_date DESC, id DESC",
            intval($current_match_db_id),
            intval($home_club_id),
            intval($away_club_id),
            intval($away_club_id),
            intval($home_club_id)
        );
        return $wpdb->get_results($sql) ?: [];
    }

    public static function db_get_games_for_match_id($match_id)
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('games');
        $match_id = intval($match_id);
        if ($match_id <= 0 || !self::table_exists($table)) {
            return [];
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE match_id=%d ORDER BY order_no ASC, id ASC",
            $match_id
        );
        return $wpdb->get_results($sql) ?: [];
    }

    public static function db_get_sets_for_game_id($game_id)
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('sets');
        $game_id = intval($game_id);
        if ($game_id <= 0 || !self::table_exists($table)) {
            return [];
        }
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE game_id=%d ORDER BY set_no ASC",
            $game_id
        );
        return $wpdb->get_results($sql) ?: [];
    }

    public static function db_get_latest_liga_for_club($club_id)
    {
        global $wpdb;
        $table = OpenTT_Unified_Core::db_table('matches');
        $club_id = intval($club_id);
        if ($club_id <= 0 || !self::table_exists($table)) {
            return '';
        }
        $sql = $wpdb->prepare(
            "SELECT liga_slug FROM {$table}
             WHERE home_club_post_id=%d OR away_club_post_id=%d
             ORDER BY match_date DESC, id DESC LIMIT 1",
            $club_id,
            $club_id
        );
        $slug = (string) $wpdb->get_var($sql);
        return sanitize_title($slug);
    }

    private static function table_exists($table_name)
    {
        global $wpdb;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $found === $table_name;
    }

    private static function has_featured_column($table_name)
    {
        global $wpdb;
        static $cache = [];
        $table_name = (string) $table_name;
        if (isset($cache[$table_name])) {
            return $cache[$table_name];
        }
        if (!self::table_exists($table_name)) {
            $cache[$table_name] = false;
            return false;
        }
        $column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", 'featured'));
        $cache[$table_name] = !empty($column);
        return $cache[$table_name];
    }

    private static function row_matches_league_season($row, $requested_liga, $requested_sezona)
    {
        if (!is_object($row)) {
            return false;
        }

        $requested_liga = sanitize_title((string) $requested_liga);
        $requested_sezona = sanitize_title((string) $requested_sezona);
        if ($requested_liga === '' || $requested_sezona === '') {
            return false;
        }

        $row_liga = sanitize_title((string) ($row->liga_slug ?? ''));
        $row_sezona = sanitize_title((string) ($row->sezona_slug ?? ''));
        $parsed = OpenTT_Unified_Readonly_Helpers::parse_legacy_liga_sezona($row_liga, $row_sezona);
        $parsed_liga = sanitize_title((string) ($parsed['league_slug'] ?? $row_liga));
        $parsed_sezona = sanitize_title((string) ($parsed['season_slug'] ?? $row_sezona));

        if ($parsed_liga !== $requested_liga) {
            return false;
        }

        return self::seasons_equivalent($parsed_sezona, $requested_sezona);
    }

    private static function seasons_equivalent($left, $right)
    {
        $left = sanitize_title((string) $left);
        $right = sanitize_title((string) $right);
        if ($left === $right) {
            return true;
        }
        if ($left === '' || $right === '') {
            return false;
        }

        $left_parts = self::season_parts($left);
        $right_parts = self::season_parts($right);
        if (!$left_parts || !$right_parts) {
            return false;
        }

        return intval($left_parts['from']) === intval($right_parts['from'])
            && intval($left_parts['to']) === intval($right_parts['to']);
    }

    private static function season_parts($value)
    {
        if (!preg_match('/^(\d{4})-(\d{2,4})$/', (string) $value, $m)) {
            return null;
        }

        $from = intval($m[1]);
        $to_raw = (string) $m[2];
        if (strlen($to_raw) === 2) {
            $century = substr((string) $from, 0, 2);
            $to = intval($century . $to_raw);
        } else {
            $to = intval($to_raw);
        }

        return ['from' => $from, 'to' => $to];
    }
}
