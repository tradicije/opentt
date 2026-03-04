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

final class GamesListShortcode
{
    public static function render($atts = [], array $deps = [])
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $ctx = $call('current_match_context');
        if (!is_array($ctx) || empty($ctx['db_row'])) {
            return '';
        }

        $match_row = $ctx['db_row'];
        $legacy_match_id = intval($ctx['legacy_id'] ?? 0);

        $games = $call('db_get_games_for_match_id', intval($match_row->id));
        $games = is_array($games) ? $games : [];
        if (empty($games)) {
            return (string) $call('shortcode_title_html', 'Tok utakmice') . '<p>Nema unetih partija.</p>';
        }

        $format = 'format_a';
        $rule = $call('get_competition_rule_data', (string) $match_row->liga_slug, (string) $match_row->sezona_slug);
        if (is_array($rule) && !empty($rule['format_partija']) && in_array((string) $rule['format_partija'], ['format_a', 'format_b'], true)) {
            $format = (string) $rule['format_partija'];
        } else {
            $sistem = strtolower(trim((string) get_post_meta($legacy_match_id, 'sistem', true)));
            if ($sistem === 'stari') {
                $format = 'format_b';
            }
        }

        $mapa_novi = [
            1 => '1. partija (A vs Y)',
            2 => '2. partija (B vs X)',
            3 => '3. partija (C vs Z)',
            4 => '4. partija (Dubl)',
            5 => '5. partija (A vs X)',
            6 => '6. partija (C vs Y)',
            7 => '7. partija (B vs Z)',
        ];
        $mapa_stari = [
            1 => '1. partija (A vs Y)',
            2 => '2. partija (B vs X)',
            3 => '3. partija (C vs Z)',
            4 => '4. partija (A vs X)',
            5 => '5. partija (C vs Y)',
            6 => '6. partija (B vs Z)',
            7 => '7. partija (Dubl)',
        ];
        $mapa_partija = $format === 'format_b' ? $mapa_stari : $mapa_novi;

        ob_start();
        echo (string) $call('shortcode_title_html', 'Tok utakmice');
        echo '<div class="lp2-lista">';

        foreach ($games as $g) {
            $redni_broj = intval($g->order_no);
            if ($redni_broj <= 0) {
                $redni_broj = 0;
            }

            if (isset($mapa_partija[$redni_broj])) {
                echo '<div class="lp2-naziv-partije">' . esc_html($mapa_partija[$redni_broj]) . '</div>';
            } else {
                echo '<div class="lp2-naziv-partije">Partija ' . intval($redni_broj) . '</div>';
            }

            $home_players = [];
            $away_players = [];
            foreach ([intval($g->home_player_post_id), intval($g->home_player2_post_id)] as $pid) {
                if ($pid > 0) {
                    $home_players[] = $pid;
                }
            }
            foreach ([intval($g->away_player_post_id), intval($g->away_player2_post_id)] as $pid) {
                if ($pid > 0) {
                    $away_players[] = $pid;
                }
            }

            $sets_dom = intval($g->home_sets);
            $sets_gos = intval($g->away_sets);
            $pob_dom = ($sets_dom > $sets_gos);
            $pob_gos = ($sets_gos > $sets_dom);
            $set_rows = $call('db_get_sets_for_game_id', intval($g->id));
            $set_rows = is_array($set_rows) ? $set_rows : [];

            echo '<div class="lp2-partija">';

            echo '<div class="lp2-item ' . esc_attr($pob_dom ? 'lp2-win' : 'lp2-lose') . '">';
            echo '<div class="lp2-team">';
            foreach ($home_players as $pid) {
                echo (string) $call('render_lp2_player', $pid);
            }
            echo '</div>';
            echo '<div class="lp2-sets">';
            foreach ($set_rows as $set_row) {
                $pdom = intval($set_row->home_points);
                $pgos = intval($set_row->away_points);
                if ($pdom === 0 && $pgos === 0) {
                    continue;
                }
                $class = ($pdom > $pgos) ? 'lp2-win' : 'lp2-lose';
                echo '<div class="lp2-set ' . esc_attr($class) . '">' . intval($pdom) . '</div>';
            }
            echo '<div class="lp2-ukupno">' . intval($sets_dom) . '</div>';
            echo '</div>';
            echo '</div>';

            echo '<div class="lp2-item ' . esc_attr($pob_gos ? 'lp2-win' : 'lp2-lose') . '">';
            echo '<div class="lp2-team">';
            foreach ($away_players as $pid) {
                echo (string) $call('render_lp2_player', $pid);
            }
            echo '</div>';
            echo '<div class="lp2-sets">';
            foreach ($set_rows as $set_row) {
                $pdom = intval($set_row->home_points);
                $pgos = intval($set_row->away_points);
                if ($pdom === 0 && $pgos === 0) {
                    continue;
                }
                $class = ($pgos > $pdom) ? 'lp2-win' : 'lp2-lose';
                echo '<div class="lp2-set ' . esc_attr($class) . '">' . intval($pgos) . '</div>';
            }
            echo '<div class="lp2-ukupno">' . intval($sets_gos) . '</div>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
        }

        echo '</div>';
        return ob_get_clean();
    }
}
