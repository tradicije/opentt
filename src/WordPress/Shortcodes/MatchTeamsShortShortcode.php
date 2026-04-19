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

final class MatchTeamsShortShortcode
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
            'title' => '',
        ], (array) $atts, 'opentt_match_teams_short');

        $ctx = $call('current_match_context');
        if (!$ctx || empty($ctx['db_row']) || !is_object($ctx['db_row'])) {
            return '';
        }

        $row = $ctx['db_row'];
        $home_id = intval($row->home_club_post_id ?? 0);
        $away_id = intval($row->away_club_post_id ?? 0);
        if ($home_id <= 0 || $away_id <= 0) {
            return '';
        }

        $home_name = (string) get_the_title($home_id);
        $away_name = (string) get_the_title($away_id);
        $home_logo = (string) $call('club_logo_html', $home_id, 'thumbnail', ['class' => 'opentt-match-teams-short-logo-img']);
        $away_logo = (string) $call('club_logo_html', $away_id, 'thumbnail', ['class' => 'opentt-match-teams-short-logo-img']);
        $score = intval($row->home_score ?? 0) . ':' . intval($row->away_score ?? 0);

        $home_color = self::clubJerseyColor($home_id, '#0b4db8');
        $away_color = self::clubJerseyColor($away_id, '#0084ff');

        $title = trim((string) ($atts['title'] ?? ''));

        ob_start();
        if ($title !== '') {
            echo (string) $call('shortcode_title_html', $title); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '<section class="opentt-match-teams-short" style="--opentt-mts-home:' . esc_attr($home_color) . ';--opentt-mts-away:' . esc_attr($away_color) . ';">';
        echo '<div class="opentt-match-teams-short-team home">';
        echo '<span class="opentt-match-teams-short-logo">' . $home_logo . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<span class="opentt-match-teams-short-name">' . esc_html($home_name) . '</span>';
        echo '</div>';
        echo '<div class="opentt-match-teams-short-team away">';
        echo '<span class="opentt-match-teams-short-logo">' . $away_logo . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<span class="opentt-match-teams-short-name">' . esc_html($away_name) . '</span>';
        echo '</div>';
        echo '<div class="opentt-match-teams-short-score-wrap"><span class="opentt-match-teams-short-score">' . esc_html($score) . '</span></div>';
        echo '</section>';
        return ob_get_clean();
    }

    private static function clubJerseyColor($club_id, $fallback)
    {
        $club_id = intval($club_id);
        if ($club_id <= 0) {
            return $fallback;
        }
        $raw = (string) get_post_meta($club_id, 'boja_dresa', true);
        $color = sanitize_hex_color($raw);
        return $color ? $color : $fallback;
    }
}

