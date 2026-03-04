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

final class H2hShortcode
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

        $cur = $ctx['db_row'];
        $home_id = intval($cur->home_club_post_id);
        $away_id = intval($cur->away_club_post_id);
        if ($home_id <= 0 || $away_id <= 0) {
            return '';
        }

        $rows = $call('db_get_h2h_matches', intval($cur->id), $home_id, $away_id);
        $rows = is_array($rows) ? $rows : [];
        if (empty($rows)) {
            return '';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Međusobni dueli');
        foreach ($rows as $row) {
            $rd = intval($row->home_score);
            $rg = intval($row->away_score);
            if ($rd === 0 && $rg === 0) {
                continue;
            }

            $domacin_id = intval($row->home_club_post_id);
            $gost_id = intval($row->away_club_post_id);
            if ($domacin_id <= 0 || $gost_id <= 0) {
                continue;
            }

            $domacin_title = get_the_title($domacin_id);
            $gost_title = get_the_title($gost_id);
            $grb_d = (string) $call('club_logo_url', $domacin_id, 'thumbnail');
            $grb_g = (string) $call('club_logo_url', $gost_id, 'thumbnail');

            $pobednik = null;
            if ($rd === 4) {
                $pobednik = 'domacin';
            } elseif ($rg === 4) {
                $pobednik = 'gost';
            }

            $kolo = (string) $call('kolo_name_from_slug', (string) $row->kolo_slug);
            $datum = (string) $call('display_match_date_long', $row->match_date);
            $link = (string) $call('match_permalink', $row);
            ?>
            <a href="<?php echo esc_url($link); ?>" class="h2h-box">
                <div class="h2h-club">
                    <?php if ($grb_d): ?><img src="<?php echo esc_url($grb_d); ?>" alt="<?php echo esc_attr($domacin_title); ?>"><?php endif; ?>
                    <span class="h2h-ime <?php echo esc_attr($pobednik === 'domacin' ? 'pobednik' : 'gubitnik'); ?>"><?php echo esc_html($domacin_title); ?></span>
                    <span class="h2h-rez <?php echo esc_attr($pobednik === 'domacin' ? 'pobednik' : 'gubitnik'); ?>"><?php echo intval($rd); ?></span>
                </div>
                <div class="h2h-club">
                    <?php if ($grb_g): ?><img src="<?php echo esc_url($grb_g); ?>" alt="<?php echo esc_attr($gost_title); ?>"><?php endif; ?>
                    <span class="h2h-ime <?php echo esc_attr($pobednik === 'gost' ? 'pobednik' : 'gubitnik'); ?>"><?php echo esc_html($gost_title); ?></span>
                    <span class="h2h-rez <?php echo esc_attr($pobednik === 'gost' ? 'pobednik' : 'gubitnik'); ?>"><?php echo intval($rg); ?></span>
                </div>
                <div class="h2h-meta">
                    <span><?php echo esc_html($kolo); ?></span>
                    <span><?php echo esc_html($datum); ?></span>
                </div>
            </a>
            <?php
        }

        return ob_get_clean();
    }
}
