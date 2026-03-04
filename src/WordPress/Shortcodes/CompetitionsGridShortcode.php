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

final class CompetitionsGridShortcode
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
            'limit' => '0',
            'filter' => '',
        ], $atts);

        $limit = max(0, intval($atts['limit']));
        $filter_mode = strtolower(trim((string) $atts['filter']));
        $enable_filter = in_array($filter_mode, ['1', 'true', 'yes', 'da', 'on'], true);
        $rows = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Takmičenja') . '<p>Nema unetih takmičenja.</p>';
        }

        $groups = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
            5 => [],
        ];
        $season_options = [];
        $selected_season = '';
        if ($enable_filter) {
            $selected_season = isset($_GET['opentt_competition_season']) ? sanitize_title((string) wp_unslash($_GET['opentt_competition_season'])) : '';
            $season_pool = [];
            foreach ($rows as $r) {
                $s = sanitize_title((string) get_post_meta($r->ID, 'opentt_competition_season_slug', true));
                if ($s !== '') {
                    $season_pool[$s] = $s;
                }
            }
            $season_pool = array_values($season_pool);
            usort($season_pool, function ($a, $b) use ($call) {
                $ak = $call('season_sort_key', (string) $a);
                $bk = $call('season_sort_key', (string) $b);
                if ($ak === $bk) {
                    return strnatcasecmp((string) $b, (string) $a);
                }
                return $bk <=> $ak;
            });
            if ($selected_season !== '' && !in_array($selected_season, $season_pool, true)) {
                $selected_season = '';
            }
            if ($selected_season === '' && !empty($season_pool)) {
                $selected_season = (string) $season_pool[0];
            }
        }

        foreach ($rows as $r) {
            $liga_slug = sanitize_title((string) get_post_meta($r->ID, 'opentt_competition_league_slug', true));
            $sezona_slug = sanitize_title((string) get_post_meta($r->ID, 'opentt_competition_season_slug', true));
            if ($liga_slug === '') {
                continue;
            }
            if ($sezona_slug !== '') {
                $season_options[$sezona_slug] = (string) $call('season_display_name', $sezona_slug);
            }
            if ($enable_filter && $selected_season !== '' && $selected_season !== $sezona_slug) {
                continue;
            }
            $rank = (int) get_post_meta($r->ID, 'opentt_competition_rank', true);
            if ($rank < 1 || $rank > 5) {
                $rank = 3;
            }

            $archive_url = (string) $call('competition_archive_url', $liga_slug, $sezona_slug);
            $league_name = (string) $call('slug_to_title', $liga_slug);
            if ($league_name === '') {
                $league_name = $liga_slug;
            }

            $club_ids = $call('db_get_competition_club_ids', $liga_slug, $sezona_slug);
            $groups[$rank][] = [
                'rule_id' => (int) $r->ID,
                'league_name' => $league_name,
                'season_name' => (string) $call('season_display_name', $sezona_slug),
                'url' => $archive_url,
                'club_ids' => $club_ids,
            ];
        }

        $rank_titles = [
            1 => 'Prvi rang takmičenja',
            2 => 'Drugi rang takmičenja',
            3 => 'Treći rang takmičenja',
            4 => 'Četvrti rang takmičenja',
            5 => 'Peti rang takmičenja',
        ];
        uasort($season_options, function ($a, $b) {
            return strnatcasecmp((string) $a, (string) $b);
        });

        ob_start();
        echo (string) $call('shortcode_title_html', 'Takmičenja');
        echo '<div class="opentt-prikaz-takmicenja">';
        if ($enable_filter) {
            echo '<form method="get" class="opentt-grid-filters">';
            foreach ($_GET as $k => $v) {
                $k = (string) $k;
                if ($k === 'opentt_competition_season') {
                    continue;
                }
                if (is_array($v)) {
                    continue;
                }
                echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) wp_unslash($v)) . '">';
            }
            echo '<label>Sezona <select name="opentt_competition_season" onchange="this.form.submit()">';
            foreach ($season_options as $slug => $label) {
                echo '<option value="' . esc_attr((string) $slug) . '"' . selected($selected_season, (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></label>';
            if (isset($_GET['opentt_competition_season'])) {
                echo '<a class="button opentt-grid-filter-reset" href="' . esc_url(remove_query_arg(['opentt_competition_season'])) . '">Reset</a>';
            }
            echo '</form>';
        }
        $has_items = false;
        for ($rank = 1; $rank <= 5; $rank++) {
            $items = $groups[$rank];
            if (empty($items)) {
                continue;
            }
            $has_items = true;
            if ($limit > 0) {
                $items = array_slice($items, 0, $limit);
            }

            echo '<section class="opentt-prikaz-takmicenja-rank opentt-prikaz-takmicenja-rank-' . intval($rank) . '">';
            echo '<h3 class="opentt-prikaz-takmicenja-rank-title">' . esc_html($rank_titles[$rank]) . '</h3>';
            echo '<div class="opentt-prikaz-takmicenja-grid">';
            foreach ($items as $item) {
                $url = (string) ($item['url'] ?? '');
                $tag = $url !== '' ? 'a' : 'div';
                $open_attrs = $url !== ''
                    ? ' class="opentt-prikaz-takmicenja-card" href="' . esc_url($url) . '"'
                    : ' class="opentt-prikaz-takmicenja-card"';

                echo '<' . $tag . $open_attrs . '>';
                echo '<div class="opentt-prikaz-takmicenja-card-head">';
                echo '<div class="opentt-prikaz-takmicenja-logo">';
                if (!empty($item['rule_id']) && has_post_thumbnail((int) $item['rule_id'])) {
                    echo get_the_post_thumbnail((int) $item['rule_id'], 'thumbnail', ['class' => 'opentt-prikaz-takmicenja-logo-img']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                } else {
                    echo '<div class="opentt-prikaz-takmicenja-logo-fallback"></div>';
                }
                echo '</div>';
                echo '<div class="opentt-prikaz-takmicenja-meta">';
                echo '<div class="opentt-prikaz-takmicenja-title">' . esc_html((string) $item['league_name']) . '</div>';
                echo '<div class="opentt-prikaz-takmicenja-season">Sezona ' . esc_html((string) $item['season_name']) . '</div>';
                echo '</div>';
                echo '</div>';
                echo '<div class="opentt-prikaz-takmicenja-sep"></div>';
                echo '<div class="opentt-prikaz-takmicenja-clubs">';
                $club_ids = is_array($item['club_ids']) ? $item['club_ids'] : [];
                if (empty($club_ids)) {
                    echo '<span class="opentt-prikaz-takmicenja-no-clubs">Nema klubova</span>';
                } else {
                    foreach ($club_ids as $club_id) {
                        $club_id = (int) $club_id;
                        if ($club_id <= 0) {
                            continue;
                        }
                        $club_name = (string) get_the_title($club_id);
                        echo '<span class="opentt-prikaz-takmicenja-club">';
                        echo $call('club_logo_html', $club_id, 'thumbnail', ['class' => 'opentt-prikaz-takmicenja-club-logo', 'title' => $club_name]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo '</span>';
                    }
                }
                echo '</div>';
                echo '</' . $tag . '>';
            }
            echo '</div>';
            echo '</section>';
        }
        if (!$has_items) {
            echo '<p>Nema takmičenja za zadatu sezonu.</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }
}
