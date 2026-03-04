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

final class TeamStatsShortcode
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
            'klub' => '',
            'filter' => 'false',
        ], $atts);

        $club_id = 0;
        if (!empty($atts['klub'])) {
            $lookup = sanitize_title((string) $atts['klub']);
            $post = get_page_by_path($lookup, OBJECT, 'klub');
            if (!$post) {
                $post = get_page_by_title((string) $atts['klub'], OBJECT, 'klub');
            }
            if ($post && !is_wp_error($post)) {
                $club_id = intval($post->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if ($club_id <= 0) {
            return '';
        }

        $enable_filter = in_array(strtolower(trim((string) $atts['filter'])), ['1', 'true', 'yes', 'da', 'on'], true);
        $season_key = 'opentt_team_season_' . $club_id;
        $season_options = [];
        if ($enable_filter) {
            $season_options = $call('db_get_club_season_options', $club_id);
            $season_options = is_array($season_options) ? $season_options : [];
        }
        $selected_season = isset($_GET[$season_key]) ? sanitize_title((string) wp_unslash($_GET[$season_key])) : '';
        if ($selected_season !== '' && !in_array($selected_season, $season_options, true)) {
            $selected_season = '';
        }

        $stats = $call('db_get_club_team_stats', $club_id, $selected_season);
        $stats = is_array($stats) ? $stats : [];
        $season_label = $selected_season !== '' ? (string) $call('season_display_name', $selected_season) : 'Ukupno';

        $current_comp = $call('db_get_latest_competition_for_club', $club_id);
        $current_season = '';
        if (is_array($current_comp) && !empty($current_comp['sezona_slug'])) {
            $current_season = sanitize_title((string) $current_comp['sezona_slug']);
        }
        if ($current_season === '' && !empty($season_options)) {
            $current_season = (string) $season_options[0];
        }
        $best_player = $current_season !== '' ? $call('db_get_club_season_best_player_by_success', $club_id, $current_season) : null;

        $table_liga_slug = '';
        $table_sezona_slug = '';
        $latest_comp = $call('db_get_latest_competition_for_club', $club_id);
        $latest_season = '';
        if (is_array($latest_comp)) {
            $latest_season = sanitize_title((string) ($latest_comp['sezona_slug'] ?? ''));
        }
        if ($latest_season === '' && !empty($season_options)) {
            $latest_season = (string) $season_options[0];
        }

        $table_sezona_slug = $selected_season !== '' ? $selected_season : $latest_season;
        if ($table_sezona_slug !== '') {
            $table_liga_slug = (string) $call('db_get_latest_liga_for_club_and_season', $club_id, $table_sezona_slug);
        } elseif (is_array($latest_comp)) {
            $table_liga_slug = sanitize_title((string) ($latest_comp['liga_slug'] ?? ''));
            $table_sezona_slug = sanitize_title((string) ($latest_comp['sezona_slug'] ?? ''));
        }

        $standings = [];
        $standings_slice = [];
        $club_rank = 0;
        $table_label = '';
        $table_uid = 'opentt-team-table-' . wp_unique_id();
        $table_short_uid = 'opentt-team-table-short-' . wp_unique_id();
        if ($table_liga_slug !== '') {
            $standings = $call('db_build_standings_for_competition', $table_liga_slug, $table_sezona_slug, null);
            $standings = is_array($standings) ? $standings : [];
            if (!empty($standings)) {
                $club_rank = intval($call('find_club_rank_in_standings', $standings, $club_id));
                if ($club_rank > 0) {
                    $standings_slice = $call('build_standings_window_around_club', $standings, $club_rank, 2);
                    $standings_slice = is_array($standings_slice) ? $standings_slice : [];
                }
                $table_label = (string) $call('competition_display_name', $table_liga_slug, $table_sezona_slug);
            }
        }
        $table_rule = ($table_liga_slug !== '' && $table_sezona_slug !== '') ? $call('get_competition_rule_data', $table_liga_slug, $table_sezona_slug) : null;
        $table_promo_direct = is_array($table_rule) ? max(0, intval($table_rule['promocija_broj'] ?? 0)) : 0;
        $table_promo_playoff = is_array($table_rule) ? max(0, intval($table_rule['promocija_baraz_broj'] ?? 0)) : 0;
        $table_releg_direct = is_array($table_rule) ? max(0, intval($table_rule['ispadanje_broj'] ?? 0)) : 0;
        $table_releg_playoff = is_array($table_rule) ? max(0, intval($table_rule['ispadanje_razigravanje_broj'] ?? 0)) : 0;
        $table_total_teams = !empty($standings) ? count($standings) : 0;

        $uid = 'opentt-team-stats-' . wp_unique_id();
        $home_pct = (string) $call('format_percentage_value', floatval($stats['home_win_pct'] ?? 0));
        $away_pct = (string) $call('format_percentage_value', floatval($stats['away_win_pct'] ?? 0));
        $doubles_pct = (string) $call('format_percentage_value', floatval($stats['doubles_win_pct'] ?? 0));

        ob_start();
        echo '<section id="' . esc_attr($uid) . '" class="opentt-stat-ekipe">';
        echo (string) $call('shortcode_title_html', 'Statistika ekipe');

        echo '<h3 class="opentt-stat-ekipe-title">Najkorisniji igrač</h3>';
        if (is_array($best_player) && !empty($best_player['player_id'])) {
            $mvp_id = intval($best_player['player_id']);
            $mvp_name = $mvp_id > 0 ? (string) get_the_title($mvp_id) : '';
            $mvp_link = $mvp_id > 0 ? (string) get_permalink($mvp_id) : '';
            $mvp_photo = $mvp_id > 0 ? get_the_post_thumbnail($mvp_id, 'thumbnail', ['class' => 'opentt-stat-ekipe-mvp-photo']) : '';
            if ($mvp_photo === '') {
                $mvp_photo = '<img src="' . esc_url((string) $call('player_fallback_image_url')) . '" alt="Igrač" class="opentt-stat-ekipe-mvp-photo" />';
            }
            $mvp_wins = intval($best_player['wins'] ?? 0);
            $mvp_losses = intval($best_player['losses'] ?? 0);
            $mvp_success = (string) $call('format_percentage_value', floatval($best_player['success_pct'] ?? 0));
            $mvp_season_label = (string) $call('season_display_name', (string) ($best_player['season_slug'] ?? $current_season));

            echo '<div class="opentt-stat-ekipe-mvp">';
            echo '<a class="opentt-stat-ekipe-mvp-link" href="' . esc_url($mvp_link) . '">';
            echo '<span class="opentt-stat-ekipe-mvp-photo-wrap">' . $mvp_photo . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="opentt-stat-ekipe-mvp-main">';
            echo '<span class="opentt-stat-ekipe-mvp-name">' . esc_html($mvp_name) . '</span>';
            echo '<span class="opentt-stat-ekipe-mvp-meta">Sezona: ' . esc_html($mvp_season_label) . ' • Učinak: ' . intval($mvp_wins) . '-' . intval($mvp_losses) . ' • Uspešnost: ' . esc_html($mvp_success) . '%</span>';
            echo '</span>';
            echo '</a>';
            echo '</div>';
        } else {
            echo '<div class="opentt-stat-ekipe-empty">Nema dovoljno podataka za obračun uspešnosti igrača u trenutnoj sezoni.</div>';
        }

        echo '<h3 class="opentt-stat-ekipe-title opentt-stat-ekipe-title-secondary">Statistika ekipe</h3>';
        if ($enable_filter && !empty($season_options)) {
            echo '<div class="opentt-stat-ekipe-filter">';
            echo '<label>Sezona ';
            echo '<select class="opentt-stat-ekipe-season">';
            echo '<option value="">Ukupno</option>';
            foreach ($season_options as $opt) {
                echo '<option value="' . esc_attr((string) $opt) . '" ' . selected($selected_season, (string) $opt, false) . '>' . esc_html((string) $call('season_display_name', (string) $opt)) . '</option>';
            }
            echo '</select>';
            echo '</label>';
            echo '</div>';
        }

        echo '<div class="opentt-stat-ekipe-meta">Period: <strong>' . esc_html($season_label) . '</strong></div>';
        echo '<div class="opentt-stat-ekipe-cards">';
        echo '<div class="opentt-stat-ekipe-card"><span class="k">Odigrane</span><strong class="v">' . intval($stats['played'] ?? 0) . '</strong></div>';
        echo '<div class="opentt-stat-ekipe-card"><span class="k">Pobede</span><strong class="v">' . intval($stats['wins'] ?? 0) . '</strong></div>';
        echo '<div class="opentt-stat-ekipe-card"><span class="k">Porazi</span><strong class="v">' . intval($stats['losses'] ?? 0) . '</strong></div>';
        echo '<div class="opentt-stat-ekipe-card"><span class="k">Najduži niz pobeda</span><strong class="v">' . intval($stats['longest_win_streak'] ?? 0) . '</strong></div>';
        echo '<div class="opentt-stat-ekipe-card"><span class="k">Kući pobede</span><strong class="v">' . esc_html($home_pct) . '%</strong></div>';
        echo '<div class="opentt-stat-ekipe-card"><span class="k">U gostima pobede</span><strong class="v">' . esc_html($away_pct) . '%</strong></div>';
        echo '<div class="opentt-stat-ekipe-card"><span class="k">Dubl učinak</span><strong class="v">' . esc_html($doubles_pct) . '%</strong></div>';
        echo '</div>';

        echo '<div class="opentt-stat-ekipe-table-wrap">';
        echo '<div class="opentt-stat-ekipe-table-head">';
        echo '<h4 class="opentt-stat-ekipe-table-title">Skraćena tabela</h4>';
        if ($table_sezona_slug !== '') {
            echo '<div class="opentt-stat-ekipe-table-season">Sezona: ' . esc_html((string) $call('season_display_name', $table_sezona_slug)) . '</div>';
        }
        if (!empty($standings) && $club_rank > 0) {
            echo '<button type="button" class="opentt-stat-ekipe-toggle" data-target="' . esc_attr($table_uid) . '" data-open-text="Vidi celu tabelu" data-close-text="Sakrij celu tabelu">Vidi celu tabelu</button>';
        }
        echo '</div>';
        if (!empty($standings_slice) && $club_rank > 0) {
            if ($table_label !== '') {
                echo '<div class="opentt-stat-ekipe-table-meta">' . esc_html($table_label) . '</div>';
            }
            echo '<div id="' . esc_attr($table_short_uid) . '" class="opentt-stat-ekipe-short-wrap">';
            echo '<table class="opentt-stat-ekipe-table">';
            echo '<thead><tr><th>#</th><th>Klub</th><th>P</th><th>W</th><th>L</th><th>Pts</th><th>+/-</th></tr></thead><tbody>';
            foreach ($standings_slice as $row) {
                $is_highlight = intval($row['club_id']) === $club_id;
                $row_rank = intval($row['rank']);
                $row_classes = [];
                if ($table_promo_direct > 0 && $row_rank <= $table_promo_direct) {
                    $row_classes[] = 'zone-promote-direct';
                } elseif ($table_promo_playoff > 0 && $row_rank <= ($table_promo_direct + $table_promo_playoff)) {
                    $row_classes[] = 'zone-promote-playoff';
                }
                if ($table_releg_direct > 0 && $table_total_teams > 0 && $row_rank > ($table_total_teams - $table_releg_direct)) {
                    $row_classes[] = 'zone-relegate-direct';
                } elseif ($table_releg_playoff > 0 && $table_total_teams > 0 && $row_rank > ($table_total_teams - $table_releg_direct - $table_releg_playoff) && $row_rank <= ($table_total_teams - $table_releg_direct)) {
                    $row_classes[] = 'zone-relegate-playoff';
                }
                if ($is_highlight) {
                    $row_classes[] = 'highlight';
                }
                $cls = !empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '';
                $club_name = (string) get_the_title(intval($row['club_id']));
                $club_link = get_permalink(intval($row['club_id']));
                echo '<tr' . $cls . '>';
                echo '<td>' . intval($row['rank']) . '</td>';
                echo '<td class="club">';
                echo '<a href="' . esc_url((string) $club_link) . '">';
                echo $call('club_logo_html', intval($row['club_id']), 'thumbnail', ['style' => 'width:24px;height:24px;object-fit:contain;border-radius:3px;']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<span>' . esc_html($club_name) . '</span>';
                echo '</a>';
                echo '</td>';
                echo '<td>' . intval($row['odigrane']) . '</td>';
                echo '<td>' . intval($row['pobede']) . '</td>';
                echo '<td>' . intval($row['porazi']) . '</td>';
                echo '<td>' . intval($row['bodovi']) . '</td>';
                $kol = intval($row['meckol']);
                echo '<td>' . ($kol > 0 ? '+' : '') . $kol . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';

            echo '<div id="' . esc_attr($table_uid) . '" class="opentt-stat-ekipe-full-wrap" hidden>';
            echo '<table class="opentt-stat-ekipe-table opentt-stat-ekipe-table-full">';
            echo '<thead><tr><th>#</th><th>Klub</th><th>P</th><th>W</th><th>L</th><th>Pts</th><th>+/-</th></tr></thead><tbody>';
            foreach ($standings as $row) {
                $is_highlight = intval($row['club_id']) === $club_id;
                $row_rank = intval($row['rank']);
                $row_classes = [];
                if ($table_promo_direct > 0 && $row_rank <= $table_promo_direct) {
                    $row_classes[] = 'zone-promote-direct';
                } elseif ($table_promo_playoff > 0 && $row_rank <= ($table_promo_direct + $table_promo_playoff)) {
                    $row_classes[] = 'zone-promote-playoff';
                }
                if ($table_releg_direct > 0 && $table_total_teams > 0 && $row_rank > ($table_total_teams - $table_releg_direct)) {
                    $row_classes[] = 'zone-relegate-direct';
                } elseif ($table_releg_playoff > 0 && $table_total_teams > 0 && $row_rank > ($table_total_teams - $table_releg_direct - $table_releg_playoff) && $row_rank <= ($table_total_teams - $table_releg_direct)) {
                    $row_classes[] = 'zone-relegate-playoff';
                }
                if ($is_highlight) {
                    $row_classes[] = 'highlight';
                }
                $cls = !empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '';
                $club_name = (string) get_the_title(intval($row['club_id']));
                $club_link = get_permalink(intval($row['club_id']));
                echo '<tr' . $cls . '>';
                echo '<td>' . intval($row['rank']) . '</td>';
                echo '<td class="club">';
                echo '<a href="' . esc_url((string) $club_link) . '">';
                echo $call('club_logo_html', intval($row['club_id']), 'thumbnail', ['style' => 'width:24px;height:24px;object-fit:contain;border-radius:3px;']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo '<span>' . esc_html($club_name) . '</span>';
                echo '</a>';
                echo '</td>';
                echo '<td>' . intval($row['odigrane']) . '</td>';
                echo '<td>' . intval($row['pobede']) . '</td>';
                echo '<td>' . intval($row['porazi']) . '</td>';
                echo '<td>' . intval($row['bodovi']) . '</td>';
                $kol = intval($row['meckol']);
                echo '<td>' . ($kol > 0 ? '+' : '') . $kol . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<div class="opentt-stat-ekipe-empty">Nema dovoljno podataka za prikaz skraćene tabele.</div>';
        }
        echo '</div>';

        echo '</section>';

        if ($enable_filter && !empty($season_options)) {
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var sel = root.querySelector('.opentt-stat-ekipe-season');
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

                var toggle = root.querySelector('.opentt-stat-ekipe-toggle');
                if (toggle) {
                    toggle.addEventListener('click', function(){
                        var targetId = toggle.getAttribute('data-target');
                        if (!targetId) { return; }
                        var full = document.getElementById(targetId);
                        var shortTable = document.getElementById('<?php echo esc_js($table_short_uid); ?>');
                        if (!full) { return; }
                        var willOpen = full.hasAttribute('hidden');
                        if (willOpen) {
                            full.removeAttribute('hidden');
                            if (shortTable) { shortTable.setAttribute('hidden', 'hidden'); }
                            toggle.textContent = toggle.getAttribute('data-close-text') || 'Sakrij celu tabelu';
                        } else {
                            full.setAttribute('hidden', 'hidden');
                            if (shortTable) { shortTable.removeAttribute('hidden'); }
                            toggle.textContent = toggle.getAttribute('data-open-text') || 'Vidi celu tabelu';
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
                var toggle = root.querySelector('.opentt-stat-ekipe-toggle');
                if (!toggle) { return; }
                toggle.addEventListener('click', function(){
                    var targetId = toggle.getAttribute('data-target');
                    if (!targetId) { return; }
                    var full = document.getElementById(targetId);
                    var shortTable = document.getElementById('<?php echo esc_js($table_short_uid); ?>');
                    if (!full) { return; }
                    var willOpen = full.hasAttribute('hidden');
                    if (willOpen) {
                        full.removeAttribute('hidden');
                        if (shortTable) { shortTable.setAttribute('hidden', 'hidden'); }
                        toggle.textContent = toggle.getAttribute('data-close-text') || 'Sakrij celu tabelu';
                    } else {
                        full.setAttribute('hidden', 'hidden');
                        if (shortTable) { shortTable.removeAttribute('hidden'); }
                        toggle.textContent = toggle.getAttribute('data-open-text') || 'Vidi celu tabelu';
                    }
                });
            })();
            </script>
            <?php
        }

        return ob_get_clean();
    }
}
