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

        $uid = 'opentt-global-stats-' . wp_unique_id();
        $count_lige = self::countLeagues();
        $count_klubovi = self::countPublishedPosts('klub');
        $count_igraci = self::countPublishedPosts('igrac');
        $count_utakmice = self::countMatches();

        ob_start();
        echo '<section id="' . esc_attr($uid) . '" class="opentt-global-stats">';
        echo (string) $call('shortcode_title_html', 'Globalna statistika');
        echo '<div class="opentt-global-stats-grid">';
        echo self::renderStatCard($count_lige, 'Lige');
        echo self::renderStatCard($count_klubovi, 'Klubovi');
        echo self::renderStatCard($count_igraci, 'Igrači');
        echo self::renderStatCard($count_utakmice, 'Utakmice');
        echo '</div>';
        echo '</section>';

        return (string) ob_get_clean();
    }

    private static function renderStatCard($value, $label)
    {
        $value = max(0, intval($value));
        $label = (string) $label;

        return '<article class="opentt-global-stats-card">'
            . '<strong class="opentt-global-stats-value">' . esc_html(number_format_i18n($value)) . '</strong>'
            . '<span class="opentt-global-stats-label">' . esc_html($label) . '</span>'
            . '</article>';
    }

    private static function countPublishedPosts($postType)
    {
        $counts = wp_count_posts((string) $postType);
        if (!is_object($counts)) {
            return 0;
        }
        return intval($counts->publish ?? 0);
    }

    private static function countMatches()
    {
        global $wpdb;

        if (!class_exists('OpenTT_Unified_Core') || !isset($wpdb)) {
            return 0;
        }

        $table = \OpenTT_Unified_Core::db_table('matches');
        if (!is_string($table) || $table === '') {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        return max(0, intval($count));
    }

    private static function countLeagues()
    {
        global $wpdb;

        $count_from_posts = self::countPublishedPosts('liga');
        $count_from_rules = 0;
        $count_from_matches = 0;

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

        if (class_exists('OpenTT_Unified_Core') && isset($wpdb)) {
            $table = \OpenTT_Unified_Core::db_table('matches');
            if (is_string($table) && $table !== '') {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $count_from_matches = intval($wpdb->get_var("SELECT COUNT(DISTINCT liga_slug) FROM {$table} WHERE liga_slug <> ''"));
            }
        }

        return max($count_from_posts, $count_from_rules, $count_from_matches);
    }
}
