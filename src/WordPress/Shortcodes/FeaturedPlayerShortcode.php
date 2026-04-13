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

final class FeaturedPlayerShortcode
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

        $atts = shortcode_atts([
            'igrac' => '',
            'klub' => '',
            'liga' => '',
            'sezona' => '',
            'season' => '',
            'title' => '',
        ], (array) $atts, 'opentt_featured_player');

        $player_id = self::resolve_player_id((string) ($atts['igrac'] ?? ''));
        $stats = [];
        $club_id_from_rank = 0;

        if ($player_id > 0) {
            $scope = self::resolve_scope_for_player($atts, $player_id, $call);
            if ($scope['liga_slug'] !== '') {
                $rank_data = (array) $call('db_get_top_players_data_unfiltered', $scope['liga_slug'], $scope['sezona_slug'], null);
                if (isset($rank_data[$player_id]) && is_array($rank_data[$player_id])) {
                    $stats = $rank_data[$player_id];
                    $club_id_from_rank = intval($stats['klub'] ?? 0);
                }
            }
        } else {
            $club_id = self::resolve_club_id((string) ($atts['klub'] ?? ''));
            if ($club_id <= 0 && is_singular('klub')) {
                $club_id = intval(get_the_ID());
            }

            if ($club_id > 0) {
                $scope = self::resolve_scope_for_club($atts, $club_id, $call);
            } else {
                $scope = self::resolve_scope_from_atts_or_latest($atts, $call);
            }
            if ($scope['liga_slug'] === '') {
                return '';
            }

            $rank_data = (array) $call('db_get_top_players_data_unfiltered', $scope['liga_slug'], $scope['sezona_slug'], null);
            if ($club_id > 0) {
                foreach ($rank_data as $candidate_player_id => $candidate_stats) {
                    $candidate_player_id = intval($candidate_player_id);
                    if ($candidate_player_id <= 0 || get_post_type($candidate_player_id) !== 'igrac' || !is_array($candidate_stats)) {
                        continue;
                    }
                    if (intval($candidate_stats['klub'] ?? 0) !== $club_id) {
                        continue;
                    }
                    $player_id = $candidate_player_id;
                    $stats = $candidate_stats;
                    $club_id_from_rank = $club_id;
                    break;
                }
            } else {
                foreach ($rank_data as $candidate_player_id => $candidate_stats) {
                    $candidate_player_id = intval($candidate_player_id);
                    if ($candidate_player_id <= 0 || get_post_type($candidate_player_id) !== 'igrac' || !is_array($candidate_stats)) {
                        continue;
                    }
                    $player_id = $candidate_player_id;
                    $stats = $candidate_stats;
                    $club_id_from_rank = intval($candidate_stats['klub'] ?? 0);
                    break;
                }
            }

            if ($player_id <= 0 && $club_id > 0) {
                $fallback_player = self::find_first_player_for_club($club_id);
                if ($fallback_player > 0) {
                    $player_id = $fallback_player;
                    $club_id_from_rank = $club_id;
                }
            }
        }

        if ($player_id <= 0 || get_post_type($player_id) !== 'igrac') {
            return '';
        }

        $player_name = (string) get_the_title($player_id);
        $player_link = (string) get_permalink($player_id);
        if ($player_name === '' || $player_link === '') {
            return '';
        }

        $club_id = intval($call('get_player_club_id', $player_id));
        if ($club_id <= 0) {
            $club_id = $club_id_from_rank;
        }
        $club_name = $club_id > 0 ? (string) get_the_title($club_id) : '';

        $wins = intval($stats['pobede'] ?? 0);
        $losses = intval($stats['porazi'] ?? 0);
        $total = $wins + $losses;
        $percent = $total > 0 ? (string) round(($wins / $total) * 100) . '%' : '-';

        $photo = get_the_post_thumbnail($player_id, 'medium', ['class' => 'opentt-featured-player-photo-img']);
        if (!$photo) {
            $photo = '<img src="' . esc_url((string) $call('player_fallback_image_url')) . '" alt="' . esc_attr($player_name) . '" class="opentt-featured-player-photo-img" />';
        }

        $title = trim((string) ($atts['title'] ?? ''));
        ob_start();
        if ($title !== '') {
            echo (string) $call('shortcode_title_html', $title); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
        <a class="opentt-featured-player-card" href="<?php echo esc_url($player_link); ?>">
            <span class="opentt-featured-player-photo"><?php echo $photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
            <span class="opentt-featured-player-name"><?php echo esc_html($player_name); ?></span>
            <?php if ($club_name !== ''): ?>
                <span class="opentt-featured-player-club"><?php echo esc_html($club_name); ?></span>
            <?php endif; ?>
            <span class="opentt-featured-player-stats">
                <span class="opentt-featured-player-stat">
                    <strong><?php echo esc_html((string) $wins); ?></strong>
                    <small>Pobede</small>
                </span>
                <span class="opentt-featured-player-stat">
                    <strong><?php echo esc_html((string) $losses); ?></strong>
                    <small>Porazi</small>
                </span>
                <span class="opentt-featured-player-stat">
                    <strong><?php echo esc_html($percent); ?></strong>
                    <small>Učinak</small>
                </span>
            </span>
        </a>
        <?php
        return ob_get_clean();
    }

    private static function resolve_player_id($player_raw)
    {
        $player_raw = trim((string) $player_raw);
        if ($player_raw === '') {
            return 0;
        }
        if (ctype_digit($player_raw)) {
            return intval($player_raw);
        }

        $post = get_page_by_path(sanitize_title($player_raw), OBJECT, 'igrac');
        if (!$post) {
            $post = get_page_by_title($player_raw, OBJECT, 'igrac');
        }
        if (!$post) {
            $q = get_posts([
                'post_type' => 'igrac',
                'post_status' => 'publish',
                'name' => sanitize_title($player_raw),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'suppress_filters' => true,
            ]);
            if (!empty($q)) {
                return intval($q[0]);
            }
        }
        if ($post && !is_wp_error($post)) {
            return intval($post->ID);
        }
        return 0;
    }

    private static function resolve_club_id($club_raw)
    {
        $club_raw = trim((string) $club_raw);
        if ($club_raw === '') {
            return 0;
        }
        if (ctype_digit($club_raw)) {
            return intval($club_raw);
        }

        $post = get_page_by_path(sanitize_title($club_raw), OBJECT, 'klub');
        if (!$post) {
            $post = get_page_by_title($club_raw, OBJECT, 'klub');
        }
        if (!$post) {
            $q = get_posts([
                'post_type' => 'klub',
                'post_status' => 'publish',
                'name' => sanitize_title($club_raw),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'suppress_filters' => true,
            ]);
            if (!empty($q)) {
                return intval($q[0]);
            }
        }
        if ($post && !is_wp_error($post)) {
            return intval($post->ID);
        }
        return 0;
    }

    private static function resolve_scope_for_club(array $atts, $club_id, callable $call)
    {
        $liga = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona = sanitize_title((string) (($atts['sezona'] ?? '') !== '' ? $atts['sezona'] : ($atts['season'] ?? '')));
        if ($liga !== '') {
            if ($sezona === '') {
                $sezona = sanitize_title((string) $call('db_get_latest_season_for_liga', $liga));
            }
            return ['liga_slug' => $liga, 'sezona_slug' => $sezona];
        }

        $from_db = $call('db_get_latest_competition_for_club', intval($club_id));
        if (!is_array($from_db)) {
            return ['liga_slug' => '', 'sezona_slug' => ''];
        }

        return [
            'liga_slug' => sanitize_title((string) ($from_db['liga_slug'] ?? '')),
            'sezona_slug' => sanitize_title((string) ($from_db['sezona_slug'] ?? '')),
        ];
    }

    private static function resolve_scope_for_player(array $atts, $player_id, callable $call)
    {
        $liga = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona = sanitize_title((string) (($atts['sezona'] ?? '') !== '' ? $atts['sezona'] : ($atts['season'] ?? '')));
        if ($liga !== '') {
            if ($sezona === '') {
                $sezona = sanitize_title((string) $call('db_get_latest_season_for_liga', $liga));
            }
            return ['liga_slug' => $liga, 'sezona_slug' => $sezona];
        }

        $from_db = $call('db_get_latest_competition_for_player', intval($player_id));
        if (!is_array($from_db)) {
            return ['liga_slug' => '', 'sezona_slug' => ''];
        }

        return [
            'liga_slug' => sanitize_title((string) ($from_db['liga_slug'] ?? '')),
            'sezona_slug' => sanitize_title((string) ($from_db['sezona_slug'] ?? '')),
        ];
    }

    private static function resolve_scope_from_atts_or_latest(array $atts, callable $call)
    {
        $liga = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona = sanitize_title((string) (($atts['sezona'] ?? '') !== '' ? $atts['sezona'] : ($atts['season'] ?? '')));
        if ($liga !== '') {
            if ($sezona === '') {
                $sezona = sanitize_title((string) $call('db_get_latest_season_for_liga', $liga));
            }
            return ['liga_slug' => $liga, 'sezona_slug' => $sezona];
        }

        $from_db = $call('db_get_latest_competition_with_games');
        if (!is_array($from_db)) {
            return ['liga_slug' => '', 'sezona_slug' => ''];
        }

        return [
            'liga_slug' => sanitize_title((string) ($from_db['liga_slug'] ?? '')),
            'sezona_slug' => sanitize_title((string) ($from_db['sezona_slug'] ?? '')),
        ];
    }

    private static function find_first_player_for_club($club_id)
    {
        $club_id = intval($club_id);
        if ($club_id <= 0) {
            return 0;
        }

        $q = new \WP_Query([
            'post_type' => 'igrac',
            'posts_per_page' => 1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'povezani_klub',
                    'value' => $club_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'klub_igraca',
                    'value' => $club_id,
                    'compare' => '=',
                ],
            ],
        ]);

        if (!$q->have_posts()) {
            return 0;
        }

        $id = intval($q->posts[0] ?? 0);
        wp_reset_postdata();
        return $id;
    }
}
