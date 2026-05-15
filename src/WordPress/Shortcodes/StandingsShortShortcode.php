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

final class StandingsShortShortcode
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
            'liga' => '',
            'sezona' => '',
            'klub' => '',
        ], $atts);

        $liga_slug = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_slug = sanitize_title((string) ($atts['sezona'] ?? ''));
        $club_input = trim((string) ($atts['klub'] ?? ''));
        $requested_sezona = isset($_GET['sezona']) ? sanitize_title((string) wp_unslash($_GET['sezona'])) : '';

        if (($liga_slug === '' || $sezona_slug === '') && is_array($call('current_archive_context'))) {
            $archive_ctx = (array) $call('current_archive_context');
            if (($archive_ctx['type'] ?? '') === 'liga_sezona') {
                if ($liga_slug === '') {
                    $liga_slug = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
                }
                if ($sezona_slug === '') {
                    $sezona_slug = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
                }
            }
        }

        if (is_singular('klub') && ($liga_slug === '' || $sezona_slug === '')) {
            $club_for_scope = self::resolve_club_id(['id' => '', 'klub' => '']);
            if ($club_for_scope <= 0) {
                $club_for_scope = intval(get_the_ID());
            }
            if ($club_for_scope > 0) {
                if ($sezona_slug === '' && $requested_sezona !== '') {
                    $sezona_slug = $requested_sezona;
                }
                if ($sezona_slug !== '' && $liga_slug === '') {
                    $liga_slug = sanitize_title((string) $call('db_get_latest_liga_for_club_and_season', $club_for_scope, $sezona_slug));
                }
                if ($liga_slug === '') {
                    $liga_slug = sanitize_title((string) $call('db_get_latest_liga_for_club', $club_for_scope));
                }
            }
        }

        if ($sezona_slug === '' && $requested_sezona !== '') {
            $sezona_slug = $requested_sezona;
        }

        if ($liga_slug === '' || $sezona_slug === '') {
            return '<p>Dodaj atribute <code>liga</code> i <code>sezona</code>.</p>';
        }

        $parsed = $call('parse_legacy_liga_sezona', $liga_slug, $sezona_slug);
        if (is_array($parsed)) {
            $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $liga_slug));
            $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $sezona_slug));
        }

        $club_id = self::resolve_club_id($club_input);
        if ($club_id <= 0 && is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }
        if ($club_id <= 0) {
            return '<p>Dodaj atribut <code>klub</code> (slug, naziv ili ID).</p>';
        }

        $standings = $call('db_build_standings_for_competition', $liga_slug, $sezona_slug, null);
        $standings = is_array($standings) ? array_values($standings) : [];
        if (empty($standings)) {
            return '<p>Nema podataka za tabelu.</p>';
        }

        $club_rank = intval($call('find_club_rank_in_standings', $standings, $club_id));
        if ($club_rank <= 0) {
            return '<p>Izabrani klub nije pronađen u tabeli za ovu ligu i sezonu.</p>';
        }

        $slice = self::slice_three_rows($standings, $club_rank);
        if (empty($slice)) {
            return '<p>Nema podataka za tabelu.</p>';
        }

        $liga_title = (string) $call('slug_to_title', $liga_slug);
        if ($liga_title === '') {
            $liga_title = $liga_slug;
        }
        $uid = 'opentt-standings-short-' . wp_unique_id();
        $visible_ranks = [];
        foreach ($slice as $slice_row) {
            if (!is_array($slice_row)) {
                continue;
            }
            $visible_ranks[intval($slice_row['rank'] ?? 0)] = true;
        }

        ob_start();
        echo '<section class="opentt-standings-short-card" id="' . esc_attr($uid) . '">';
        echo '<header class="opentt-standings-short-title">' . esc_html($liga_title) . '</header>';
        echo '<div class="opentt-standings-short-body">';
        echo '<table class="opentt-standings-short-table">';
        echo '<colgroup>';
        echo '<col class="col-rank">';
        echo '<col class="col-club">';
        echo '<col class="col-played">';
        echo '<col class="col-won">';
        echo '<col class="col-points">';
        echo '</colgroup>';
        echo '<thead><tr><th>#</th><th>Klub</th><th>P</th><th>W</th><th>Pts</th></tr></thead>';
        echo '<tbody>';
        foreach ($standings as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rank = intval($row['rank'] ?? 0);
            $row_club_id = intval($row['club_id'] ?? 0);
            $is_highlight = ($row_club_id === $club_id);
            $club_link = $row_club_id > 0 ? get_permalink($row_club_id) : '';
            $row_classes = [];
            if ($is_highlight) {
                $row_classes[] = 'is-highlight';
            }
            if (!isset($visible_ranks[$rank])) {
                $row_classes[] = 'opentt-standings-short-extra';
            }
            echo '<tr' . (!empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '') . '>';
            echo '<td>' . $rank . '</td>';
            echo '<td class="club-cell">';
            if ($club_link !== '') {
                echo '<a class="club-wrap club-link" href="' . esc_url($club_link) . '">';
            } else {
                echo '<span class="club-wrap">';
            }
            echo '<span class="club-crest">' . (string) $call('club_logo_html', $row_club_id, 'thumbnail', ['class' => 'opentt-standings-short-crest']) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="club-name">' . esc_html((string) get_the_title($row_club_id)) . '</span>';
            if ($club_link !== '') {
                echo '</a>';
            } else {
                echo '</span>';
            }
            echo '</td>';
            echo '<td>' . intval($row['odigrane'] ?? 0) . '</td>';
            echo '<td>' . intval($row['pobede'] ?? 0) . '</td>';
            echo '<td>' . intval($row['bodovi'] ?? 0) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<div class="opentt-standings-short-actions">';
        echo '<button type="button" class="opentt-standings-short-toggle" data-state="collapsed" aria-expanded="false">Prikaži celu tabelu</button>';
        echo '</div>';
        echo '</div></section>';
        ?>
        <style>
        #<?php echo esc_attr($uid); ?> .opentt-standings-short-extra { display: none; }
        #<?php echo esc_attr($uid); ?>.is-expanded .opentt-standings-short-extra { display: table-row; }
        </style>
        <script>
        (function() {
            var root = document.getElementById('<?php echo esc_js($uid); ?>');
            if (!root) { return; }
            var btn = root.querySelector('.opentt-standings-short-toggle');
            if (!btn) { return; }

            btn.addEventListener('click', function() {
                var isCollapsed = btn.getAttribute('data-state') !== 'expanded';
                if (isCollapsed) {
                    root.classList.add('is-expanded');
                    btn.setAttribute('data-state', 'expanded');
                    btn.setAttribute('aria-expanded', 'true');
                    btn.textContent = 'Sakrij celu tabelu';
                } else {
                    root.classList.remove('is-expanded');
                    btn.setAttribute('data-state', 'collapsed');
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = 'Prikaži celu tabelu';
                }
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    private static function resolve_club_id($club_input)
    {
        $club_input = trim((string) $club_input);
        if ($club_input === '') {
            return 0;
        }
        if (ctype_digit($club_input)) {
            return intval($club_input);
        }

        $club = get_page_by_path(sanitize_title($club_input), OBJECT, 'klub');
        if (!$club) {
            $club = get_page_by_title($club_input, OBJECT, 'klub');
        }

        return ($club && !is_wp_error($club)) ? intval($club->ID) : 0;
    }

    private static function slice_three_rows(array $standings, $club_rank)
    {
        $count = count($standings);
        if ($count <= 3) {
            return $standings;
        }

        $index = max(0, intval($club_rank) - 1);
        $start = $index - 1;
        $end = $index + 1;

        if ($start < 0) {
            $end += abs($start);
            $start = 0;
        }
        if ($end > $count - 1) {
            $start -= ($end - ($count - 1));
            $end = $count - 1;
        }

        $start = max(0, $start);
        $length = min(3, $count - $start);

        return array_slice($standings, $start, $length);
    }
}
