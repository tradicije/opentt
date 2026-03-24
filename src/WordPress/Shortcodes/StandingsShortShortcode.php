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

        ob_start();
        echo '<section class="opentt-standings-short-card">';
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
        foreach ($slice as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row_club_id = intval($row['club_id'] ?? 0);
            $is_highlight = ($row_club_id === $club_id);
            echo '<tr' . ($is_highlight ? ' class="is-highlight"' : '') . '>';
            echo '<td>' . intval($row['rank'] ?? 0) . '</td>';
            echo '<td class="club-cell">';
            echo '<span class="club-wrap">';
            echo '<span class="club-crest">' . (string) $call('club_logo_html', $row_club_id, 'thumbnail', ['class' => 'opentt-standings-short-crest']) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="club-name">' . esc_html((string) get_the_title($row_club_id)) . '</span>';
            echo '</span>';
            echo '</td>';
            echo '<td>' . intval($row['odigrane'] ?? 0) . '</td>';
            echo '<td>' . intval($row['pobede'] ?? 0) . '</td>';
            echo '<td>' . intval($row['bodovi'] ?? 0) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div></section>';
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
