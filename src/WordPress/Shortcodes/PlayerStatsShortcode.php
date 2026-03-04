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

final class PlayerStatsShortcode
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
            'igrac' => '',
            'filter' => 'false',
        ], $atts);

        $player_id = 0;
        if (!empty($atts['igrac'])) {
            $lookup = sanitize_title((string) $atts['igrac']);
            $post = get_page_by_path($lookup, OBJECT, 'igrac');
            if (!$post) {
                $post = get_page_by_title((string) $atts['igrac'], OBJECT, 'igrac');
            }
            if ($post && !is_wp_error($post)) {
                $player_id = intval($post->ID);
            }
        } elseif (is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        if ($player_id <= 0) {
            return '';
        }

        $enable_filter = in_array(strtolower(trim((string) $atts['filter'])), ['1', 'true', 'yes', 'da', 'on'], true);
        $season_key = 'opentt_player_season_' . $player_id;
        $selected_season = isset($_GET[$season_key]) ? sanitize_title((string) wp_unslash($_GET[$season_key])) : '';

        $season_options = [];
        if ($enable_filter) {
            $season_options = $call('db_get_player_season_options', $player_id);
            $season_options = is_array($season_options) ? $season_options : [];
            if ($selected_season !== '' && !in_array($selected_season, $season_options, true)) {
                $selected_season = '';
            }
        } else {
            $selected_season = '';
        }

        $stats = $call('db_get_player_stats', $player_id, $selected_season);
        $stats = is_array($stats) ? $stats : ['wins' => 0, 'losses' => 0];
        $mvp_count = intval($call('db_get_player_mvp_count', $player_id, $selected_season));
        $played = intval($stats['wins']) + intval($stats['losses']);
        $pct = $played > 0 ? round((intval($stats['wins']) / $played) * 100, 1) : 0;
        $season_label = $selected_season !== '' ? (string) $call('season_display_name', $selected_season) : 'Ukupno';

        $latest_comp = $call('db_get_latest_competition_for_player', $player_id);
        $ranking_season = $selected_season;
        if ($ranking_season === '') {
            $ranking_season = is_array($latest_comp) ? sanitize_title((string) ($latest_comp['sezona_slug'] ?? '')) : '';
            if ($ranking_season === '' && !empty($season_options)) {
                $ranking_season = (string) $season_options[0];
            }
        }
        $ranking_liga = '';
        if ($ranking_season !== '') {
            $ranking_liga = (string) $call('db_get_latest_liga_for_player_and_season', $player_id, $ranking_season);
        }
        if ($ranking_liga === '' && is_array($latest_comp)) {
            $ranking_liga = sanitize_title((string) ($latest_comp['liga_slug'] ?? ''));
        }

        $ranking_data = [];
        $ranking_rows = [];
        $ranking_slice = [];
        $player_rank = 0;
        if ($ranking_liga !== '') {
            $ranking_data = $call('db_get_top_players_data', $ranking_liga, $ranking_season, null);
            $ranking_data = is_array($ranking_data) ? $ranking_data : [];
            if (!empty($ranking_data)) {
                $rank = 0;
                foreach ($ranking_data as $pid => $info) {
                    $rank++;
                    $ranking_rows[] = [
                        'rank' => $rank,
                        'player_id' => intval($pid),
                        'info' => is_array($info) ? $info : [],
                    ];
                    if (intval($pid) === $player_id) {
                        $player_rank = $rank;
                    }
                }
                if ($player_rank > 0) {
                    $from = max(1, $player_rank - 2);
                    $to = $player_rank + 2;
                    foreach ($ranking_rows as $rr) {
                        $rr_rank = intval($rr['rank']);
                        if ($rr_rank >= $from && $rr_rank <= $to) {
                            $ranking_slice[] = $rr;
                        }
                    }
                }
            }
        }
        $ranking_uid = 'opentt-player-ranking-' . wp_unique_id();

        $uid = 'opentt-player-stats-' . wp_unique_id();

        ob_start();
        echo '<div id="' . esc_attr($uid) . '" class="opentt-stat-igraca">';
        echo (string) $call('shortcode_title_html', 'Statistika igrača');
        if ($enable_filter && !empty($season_options)) {
            echo '<div class="opentt-stat-igraca-filter">';
            echo '<label>Sezona ';
            echo '<select class="opentt-stat-igraca-season">';
            echo '<option value="">Ukupno</option>';
            foreach ($season_options as $opt) {
                echo '<option value="' . esc_attr((string) $opt) . '" ' . selected($selected_season, (string) $opt, false) . '>' . esc_html((string) $call('season_display_name', (string) $opt)) . '</option>';
            }
            echo '</select>';
            echo '</label>';
            echo '</div>';
        }

        echo '<div class="opentt-stat-igraca-meta">Period: <strong>' . esc_html($season_label) . '</strong></div>';
        echo '<div class="opentt-stat-igraca-cards">';
        echo '<div class="opentt-stat-card"><span class="k">Pobede</span><strong class="v">' . intval($stats['wins']) . '</strong></div>';
        echo '<div class="opentt-stat-card"><span class="k">Porazi</span><strong class="v">' . intval($stats['losses']) . '</strong></div>';
        echo '<div class="opentt-stat-card"><span class="k">Uspešnost</span><strong class="v">' . esc_html((string) $pct) . '%</strong></div>';
        echo '<div class="opentt-stat-card"><span class="k">Igrač utakmice</span><strong class="v">' . intval($mvp_count) . '</strong></div>';
        echo '</div>';

        echo '<div class="opentt-stat-igraca-rang-wrap">';
        echo '<div class="opentt-stat-igraca-rang-head">';
        echo '<h4 class="opentt-stat-igraca-rang-title">Skraćena rang lista</h4>';
        if ($ranking_season !== '') {
            echo '<div class="opentt-stat-igraca-rang-season">Sezona: ' . esc_html((string) $call('season_display_name', $ranking_season)) . '</div>';
        }
        if (!empty($ranking_rows) && $player_rank > 0) {
            echo '<button type="button" class="opentt-stat-igraca-toggle" data-target="' . esc_attr($ranking_uid) . '" data-open-text="Vidi celu rang listu" data-close-text="Sakrij celu rang listu">Vidi celu rang listu</button>';
        }
        echo '</div>';
        if (!empty($ranking_slice) && $player_rank > 0) {
            echo '<div class="opentt-stat-igraca-rang-short" id="' . esc_attr($ranking_uid . '-short') . '">';
            echo '<div class="top-igraci-list opentt-stat-igraca-rang-list">';
            foreach ($ranking_slice as $rr) {
                $pid = intval($rr['player_id']);
                $rank = intval($rr['rank']);
                $info = is_array($rr['info']) ? $rr['info'] : [];
                if ($pid > 0 && get_post_type($pid) === 'igrac') {
                    echo $call('render_top_player_card_list', $pid, $rank, $info, $pid === $player_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
            echo '</div>';
            echo '</div>';

            echo '<div id="' . esc_attr($ranking_uid) . '" class="opentt-stat-igraca-rang-full" hidden>';
            echo '<div class="top-igraci-list opentt-stat-igraca-rang-list">';
            foreach ($ranking_rows as $rr) {
                $pid = intval($rr['player_id']);
                $rank = intval($rr['rank']);
                $info = is_array($rr['info']) ? $rr['info'] : [];
                if ($pid > 0 && get_post_type($pid) === 'igrac') {
                    echo $call('render_top_player_card_list', $pid, $rank, $info, $pid === $player_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="opentt-stat-igraca-rang-empty">Nema dovoljno podataka za prikaz rang liste.</div>';
        }
        echo '</div>';
        echo '</div>';

        if ($enable_filter && !empty($season_options)) {
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var sel = root.querySelector('.opentt-stat-igraca-season');
                if (!sel) { return; }
                sel.addEventListener('change', function(){
                    var url = new URL(window.location.href);
                    if (sel.value) {
                        url.searchParams.set('<?php echo esc_js($season_key); ?>', sel.value);
                    } else {
                        url.searchParams.delete('<?php echo esc_js($season_key); ?>');
                    }
                    window.location.href = url.toString();
                });

                var toggle = root.querySelector('.opentt-stat-igraca-toggle');
                if (toggle) {
                    toggle.addEventListener('click', function(){
                        var targetId = toggle.getAttribute('data-target');
                        if (!targetId) { return; }
                        var full = document.getElementById(targetId);
                        var shortList = document.getElementById(targetId + '-short');
                        if (!full) { return; }
                        var willOpen = full.hasAttribute('hidden');
                        if (willOpen) {
                            full.removeAttribute('hidden');
                            if (shortList) { shortList.setAttribute('hidden', 'hidden'); }
                            toggle.textContent = toggle.getAttribute('data-close-text') || 'Sakrij celu rang listu';
                        } else {
                            full.setAttribute('hidden', 'hidden');
                            if (shortList) { shortList.removeAttribute('hidden'); }
                            toggle.textContent = toggle.getAttribute('data-open-text') || 'Vidi celu rang listu';
                        }
                    });
                }
            })();
            </script>
            <?php
        } else {
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var toggle = root.querySelector('.opentt-stat-igraca-toggle');
                if (!toggle) { return; }
                toggle.addEventListener('click', function(){
                    var targetId = toggle.getAttribute('data-target');
                    if (!targetId) { return; }
                    var full = document.getElementById(targetId);
                    var shortList = document.getElementById(targetId + '-short');
                    if (!full) { return; }
                    var willOpen = full.hasAttribute('hidden');
                    if (willOpen) {
                        full.removeAttribute('hidden');
                        if (shortList) { shortList.setAttribute('hidden', 'hidden'); }
                        toggle.textContent = toggle.getAttribute('data-close-text') || 'Sakrij celu rang listu';
                    } else {
                        full.setAttribute('hidden', 'hidden');
                        if (shortList) { shortList.removeAttribute('hidden'); }
                        toggle.textContent = toggle.getAttribute('data-open-text') || 'Vidi celu rang listu';
                    }
                });
            })();
            </script>
            <?php
        }

        return ob_get_clean();
    }
}
