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

final class MatchesListShortcode
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
            'limit' => 5,
            'klub' => '',
            'odigrana' => '',
            'liga' => '',
            'sezona' => '',
        ], $atts);

        $rows = $call('db_get_matches', $call('build_match_query_args', $atts));
        $rows = is_array($rows) ? $rows : [];
        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Utakmice lista') . '<p>Nema utakmica za prikaz.</p>';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Utakmice lista');
        echo '<ul class="opentt-list">';
        foreach ($rows as $row) {
            $home_id = intval($row->home_club_post_id);
            $away_id = intval($row->away_club_post_id);
            $rd = intval($row->home_score);
            $rg = intval($row->away_score);
            $home_win = ($rd === 4);
            $away_win = ($rg === 4);
            $date = (string) $call('display_match_date', $row->match_date);
            $link = (string) $call('match_permalink', $row);

            echo '<li><a href="' . esc_url($link) . '">';
            echo '<span class="' . esc_attr($home_win ? 'pobednik' : 'gubitnik') . '">' . esc_html(get_the_title($home_id)) . ' ' . intval($rd) . '</span>';
            echo ' : ';
            echo '<span class="' . esc_attr($away_win ? 'pobednik' : 'gubitnik') . '">' . intval($rg) . ' ' . esc_html(get_the_title($away_id)) . '</span>';
            if ($date !== '') {
                echo ' – ' . esc_html($date);
            }
            echo '</a></li>';
        }
        echo '</ul>';
        return ob_get_clean();
    }
}
