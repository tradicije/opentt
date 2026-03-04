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

final class MvpShortcode
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

        $ctx = $call('current_match_context');
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }
        $match_row = $ctx['db_row'];
        $games = $call('db_get_games_for_match_id', intval($match_row->id));
        $games = is_array($games) ? $games : [];
        if (empty($games)) {
            return (string) $call('shortcode_title_html', 'Najkorisniji igrač') . '<div class="mvp-box">Nema MVP za ovu utakmicu.</div>';
        }

        $stat = [];
        foreach ($games as $g) {
            if (intval($g->is_doubles) === 1 || intval($g->home_player2_post_id) > 0 || intval($g->away_player2_post_id) > 0) {
                continue;
            }

            $pid_home = intval($g->home_player_post_id);
            $pid_away = intval($g->away_player_post_id);
            if ($pid_home <= 0 || $pid_away <= 0) {
                continue;
            }

            $d_set = intval($g->home_sets);
            $g_set = intval($g->away_sets);
            if ($d_set === 0 && $g_set === 0) {
                continue;
            }

            $sets = $call('db_get_sets_for_game_id', intval($g->id));
            $sets = is_array($sets) ? $sets : [];
            $poeni_d = 0;
            $poeni_g = 0;
            foreach ($sets as $set) {
                $poeni_d += intval($set->home_points);
                $poeni_g += intval($set->away_points);
            }

            foreach ([$pid_home, $pid_away] as $pid) {
                if (!isset($stat[$pid])) {
                    $stat[$pid] = ['pobede' => 0, 'setovi' => 0, 'poeni' => 0];
                }
            }

            if ($d_set > $g_set) {
                $stat[$pid_home]['pobede'] += 1;
            } else {
                $stat[$pid_away]['pobede'] += 1;
            }
            $stat[$pid_home]['setovi'] += ($d_set - $g_set);
            $stat[$pid_away]['setovi'] += ($g_set - $d_set);
            $stat[$pid_home]['poeni'] += ($poeni_d - $poeni_g);
            $stat[$pid_away]['poeni'] += ($poeni_g - $poeni_d);
        }

        if (empty($stat)) {
            return (string) $call('shortcode_title_html', 'Najkorisniji igrač') . '<div class="mvp-box">Nema MVP za ovu utakmicu.</div>';
        }

        uasort($stat, function ($a, $b) {
            if ($a['pobede'] !== $b['pobede']) {
                return $b['pobede'] - $a['pobede'];
            }
            if ($a['setovi'] !== $b['setovi']) {
                return $b['setovi'] - $a['setovi'];
            }
            return $b['poeni'] - $a['poeni'];
        });

        $mvp_id = intval(array_key_first($stat));
        if ($mvp_id <= 0) {
            return (string) $call('shortcode_title_html', 'Najkorisniji igrač') . '<div class="mvp-box">Nema MVP za ovu utakmicu.</div>';
        }

        $ime = esc_html((string) get_the_title($mvp_id));
        $slika = get_the_post_thumbnail_url($mvp_id, 'medium');
        if (empty($slika)) {
            $slika = (string) $call('player_fallback_image_url');
        }
        $igrac_link = get_permalink($mvp_id);

        $klub_id = intval(get_post_meta($mvp_id, 'klub_igraca', true));
        if ($klub_id <= 0) {
            $klub_id = intval(get_post_meta($mvp_id, 'povezani_klub', true));
        }
        $klub_ime = $klub_id > 0 ? esc_html((string) get_the_title($klub_id)) : '';
        $klub_grb = $klub_id > 0 ? (string) $call('club_logo_url', $klub_id, 'thumbnail') : '';
        $klub_link = $klub_id > 0 ? get_permalink($klub_id) : '';

        ob_start(); ?>
        <?php echo (string) $call('shortcode_title_html', 'Najkorisniji igrač'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div class="mvp-box">
            <a href="<?php echo esc_url($igrac_link); ?>">
                <img src="<?php echo esc_url($slika); ?>" alt="<?php echo esc_attr($ime); ?>" class="mvp-slika">
            </a>
            <div class="mvp-info">
                <div class="mvp-ime">
                    <a href="<?php echo esc_url($igrac_link); ?>" style="color:white; text-decoration:none;">
                        <?php echo $ime; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </a>
                </div>
                <div class="mvp-klub">
                    <?php if ($klub_link): ?>
                    <a href="<?php echo esc_url($klub_link); ?>" style="display:inline-flex; align-items:center; gap:5px; color:#ccc; text-decoration:none;">
                        <?php if ($klub_grb): ?>
                            <img src="<?php echo esc_url($klub_grb); ?>" alt="<?php echo esc_attr($klub_ime); ?>" class="mvp-grb" style="width:20px; height:20px;">
                        <?php endif; ?>
                        <span><?php echo esc_html($klub_ime); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
