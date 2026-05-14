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

namespace OpenTT\Unified\WordPress\Shortcodes;

final class GlobalStatsShortcode
{
    public static function render($atts, array $deps)
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $atts = shortcode_atts([
            'sezona' => '',
            'season' => '',
        ], $atts);

        $season_slug = sanitize_title((string) ($atts['sezona'] !== '' ? $atts['sezona'] : $atts['season']));
        $uid = 'opentt-global-stats-' . wp_unique_id();
        $count_lige = self::countLeagues($season_slug);
        $count_klubovi = self::countClubs($season_slug);
        $count_igraci = self::countPlayers($season_slug);
        $count_utakmice = self::countMatches($season_slug);

        ob_start();
        echo '<section id="' . esc_attr($uid) . '" class="opentt-global-stats">';
        echo (string) $call('shortcode_title_html', 'Globalna statistika');
        echo '<div class="opentt-global-stats-grid">';
        echo self::renderStatCard($count_lige, ['one' => 'liga', 'few' => 'lige', 'many' => 'liga']);
        echo self::renderStatCard($count_klubovi, ['one' => 'klub', 'few' => 'kluba', 'many' => 'klubova']);
        echo self::renderStatCard($count_igraci, ['one' => 'igrač', 'few' => 'igrača', 'many' => 'igrača']);
        echo self::renderStatCard($count_utakmice, ['one' => 'utakmica', 'few' => 'utakmice', 'many' => 'utakmica']);
        echo '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    private static function renderStatCard($value, array $forms)
    {
        $value = max(0, intval($value));
        $label = self::serbianCountLabel($value, $forms);

        return '<article class="opentt-global-stats-card">'
            . '<strong class="opentt-global-stats-value">' . esc_html(number_format_i18n($value)) . '</strong>'
            . '<span class="opentt-global-stats-label">' . esc_html($label) . '</span>'
            . '</article>';
    }

    private static function serbianCountLabel($count, array $forms)
    {
        $count = max(0, intval($count));
        $one = isset($forms['one']) ? (string) $forms['one'] : '';
        $few = isset($forms['few']) ? (string) $forms['few'] : $one;
        $many = isset($forms['many']) ? (string) $forms['many'] : $few;

        if ($count === 1) {
            return $one;
        }
        if ($count >= 2 && $count <= 4) {
            return $few;
        }
        return $many;
    }

    private static function countPublishedPosts($postType)
    {
        $counts = wp_count_posts((string) $postType);
        if (!is_object($counts)) {
            return 0;
        }
        return intval($counts->publish ?? 0);
    }

    private static function countMatches($seasonSlug = '')
    {
        global $wpdb;

        if (!class_exists('OpenTT_Unified_Core') || !isset($wpdb)) {
            return 0;
        }

        $table = \OpenTT_Unified_Core::db_table('matches');
        if (!is_string($table) || $table === '') {
            return 0;
        }

        $seasonSlug = sanitize_title((string) $seasonSlug);
        if ($seasonSlug !== '') {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE sezona_slug=%s", $seasonSlug));
            return max(0, intval($count));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        return max(0, intval($count));
    }

    private static function countLeagues($seasonSlug = '')
    {
        global $wpdb;

        $seasonSlug = sanitize_title((string) $seasonSlug);
        $count_from_posts = self::countPublishedPosts('liga');
        $count_from_rules = 0;
        $count_from_matches = 0;

        if ($seasonSlug === '') {
            $rule_ids = get_posts([
                'post_type' => 'pravilo_takmicenja',
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => -1,
                'no_found_rows' => true,
            ]);
            if (is_array($rule_ids) && !empty($rule_ids)) {
                $slugs = [];
                foreach ($rule_ids as $rule_id) {
                    $slug = sanitize_title((string) get_post_meta(intval($rule_id), 'opentt_competition_league_slug', true));
                    if ($slug !== '') {
                        $slugs[$slug] = true;
                    }
                }
                $count_from_rules = count($slugs);
            }
        }

        if (class_exists('OpenTT_Unified_Core') && isset($wpdb)) {
            $table = \OpenTT_Unified_Core::db_table('matches');
            if (is_string($table) && $table !== '') {
                if ($seasonSlug !== '') {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $count_from_matches = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(DISTINCT liga_slug) FROM {$table} WHERE liga_slug <> '' AND sezona_slug=%s", $seasonSlug)));
                } else {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $count_from_matches = intval($wpdb->get_var("SELECT COUNT(DISTINCT liga_slug) FROM {$table} WHERE liga_slug <> ''"));
                }
            }
        }

        if ($seasonSlug !== '') {
            return max($count_from_matches, $count_from_rules);
        }

        return max($count_from_posts, $count_from_rules, $count_from_matches);
    }

    private static function countClubs($seasonSlug = '')
    {
        global $wpdb;

        $seasonSlug = sanitize_title((string) $seasonSlug);
        if ($seasonSlug === '') {
            return self::countPublishedPosts('klub');
        }
        if (!class_exists('OpenTT_Unified_Core') || !isset($wpdb)) {
            return 0;
        }
        $table = \OpenTT_Unified_Core::db_table('matches');
        if (!is_string($table) || $table === '') {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT club_id) FROM (
                SELECT home_club_post_id AS club_id FROM {$table} WHERE sezona_slug=%s AND home_club_post_id > 0
                UNION
                SELECT away_club_post_id AS club_id FROM {$table} WHERE sezona_slug=%s AND away_club_post_id > 0
            ) AS season_clubs",
            $seasonSlug,
            $seasonSlug
        ));

        return max(0, intval($count));
    }

    private static function countPlayers($seasonSlug = '')
    {
        global $wpdb;

        $seasonSlug = sanitize_title((string) $seasonSlug);
        if ($seasonSlug === '') {
            return self::countPublishedPosts('igrac');
        }
        if (!class_exists('OpenTT_Unified_Core') || !isset($wpdb)) {
            return 0;
        }
        $matches = \OpenTT_Unified_Core::db_table('matches');
        $games = \OpenTT_Unified_Core::db_table('games');
        if (!is_string($matches) || $matches === '' || !is_string($games) || $games === '') {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT player_id) FROM (
                SELECT g.home_player_post_id AS player_id
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE m.sezona_slug=%s AND g.home_player_post_id > 0
                UNION
                SELECT g.home_player2_post_id AS player_id
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE m.sezona_slug=%s AND g.home_player2_post_id > 0
                UNION
                SELECT g.away_player_post_id AS player_id
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE m.sezona_slug=%s AND g.away_player_post_id > 0
                UNION
                SELECT g.away_player2_post_id AS player_id
                FROM {$games} g
                INNER JOIN {$matches} m ON m.id = g.match_id
                WHERE m.sezona_slug=%s AND g.away_player2_post_id > 0
            ) AS season_players",
            $seasonSlug,
            $seasonSlug,
            $seasonSlug,
            $seasonSlug
        ));

        return max(0, intval($count));
    }
}
