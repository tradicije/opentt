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

final class MatchIdShortcode
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
            'id' => '',
            'klub' => '',
            'club' => '',
            'played' => '',
            'odigrana' => '',
            'liga' => '',
            'sezona' => '',
            'season' => '',
        ], $atts);

        $id_mode = strtolower(trim((string) ($atts['id'] ?? '')));
        if ($id_mode === '') {
            return '<p>Nedostaje atribut <code>id</code> (broj ili <code>latest</code>).</p>';
        }

        $match = null;
        if (ctype_digit($id_mode) && intval($id_mode) > 0) {
            $match = $call('db_get_match_by_id', intval($id_mode));
        } elseif ($id_mode === 'latest') {
            $match = self::resolve_latest_match($atts, $call);
        } else {
            return '<p>Neispravan atribut <code>id</code>. Koristi broj ili <code>latest</code>.</p>';
        }

        if (!is_object($match)) {
            return '<p>Nema utakmice za prikaz.</p>';
        }

        $home_id = intval($match->home_club_post_id ?? 0);
        $away_id = intval($match->away_club_post_id ?? 0);
        $home_name = $home_id > 0 ? (string) get_the_title($home_id) : 'Domaćin';
        $away_name = $away_id > 0 ? (string) get_the_title($away_id) : 'Gost';

        $home_score = intval($match->home_score ?? 0);
        $away_score = intval($match->away_score ?? 0);
        $is_played = intval($match->played ?? 0) === 1 || $home_score > 0 || $away_score > 0;
        $is_live = intval($match->live ?? 0) === 1;

        $score_or_time = $is_played
            ? ($home_score . ' : ' . $away_score)
            : ((string) $call('display_match_time', (string) ($match->match_date ?? '')) ?: 'Najava');
        $date_label = (string) $call('display_match_date', (string) ($match->match_date ?? ''));
        $kolo_label = (string) $call('kolo_name_from_slug', (string) ($match->kolo_slug ?? ''));
        $liga_label = (string) $call('slug_to_title', (string) ($match->liga_slug ?? ''));
        $sezona_label = trim((string) ($match->sezona_slug ?? ''));

        $top_meta_parts = [];
        if ($liga_label !== '') {
            $top_meta_parts[] = $liga_label;
        }
        if ($sezona_label !== '') {
            $top_meta_parts[] = $sezona_label;
        }
        if ($kolo_label !== '') {
            $top_meta_parts[] = $kolo_label;
        }
        $top_meta = implode(' • ', $top_meta_parts);
        $match_url = (string) $call('match_permalink', $match);

        ob_start();
        echo '<article class="opentt-match-id-card' . ($is_live ? ' is-live' : '') . '">';
        echo '<a class="opentt-match-id-link" href="' . esc_url($match_url) . '">';
        if ($top_meta !== '') {
            echo '<div class="opentt-match-id-meta">' . esc_html($top_meta) . '</div>';
        }
        echo '<div class="opentt-match-id-main">';
        echo '<div class="opentt-match-id-team is-home">';
        echo '<span class="opentt-match-id-team-logo">' . (string) $call('club_logo_html', $home_id, 'thumbnail', ['class' => 'opentt-match-id-crest']) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<span class="opentt-match-id-team-name">' . esc_html($home_name) . '</span>';
        echo '</div>';
        echo '<div class="opentt-match-id-center">';
        if ($is_live) {
            echo '<span class="opentt-live-badge">LIVE</span>';
        }
        echo '<strong class="opentt-match-id-score">' . esc_html($score_or_time) . '</strong>';
        echo '</div>';
        echo '<div class="opentt-match-id-team is-away">';
        echo '<span class="opentt-match-id-team-logo">' . (string) $call('club_logo_html', $away_id, 'thumbnail', ['class' => 'opentt-match-id-crest']) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<span class="opentt-match-id-team-name">' . esc_html($away_name) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="opentt-match-id-footer">' . esc_html($date_label) . '</div>';
        echo '</a>';
        echo '</article>';
        return ob_get_clean();
    }

    private static function resolve_latest_match(array $atts, callable $call)
    {
        $club_input = trim((string) ($atts['klub'] !== '' ? $atts['klub'] : ($atts['club'] ?? '')));
        if ($club_input === '') {
            return null;
        }

        $args = $atts;
        $args['klub'] = $club_input;
        $query_args = (array) $call('build_match_query_args', $args);
        if (intval($query_args['club_id'] ?? 0) <= 0) {
            return null;
        }

        $played = self::normalize_played(
            (string) ($atts['played'] ?? ''),
            (string) ($atts['odigrana'] ?? '')
        );
        if ($played !== '') {
            $query_args['played'] = $played;
        }
        $query_args['limit'] = -1;

        $rows = $call('db_get_matches', $query_args);
        $rows = is_array($rows) ? $rows : [];
        if (empty($rows)) {
            return null;
        }

        if ($played === '0') {
            return self::pick_next_upcoming_match($rows, $call);
        }

        return is_object($rows[0]) ? $rows[0] : null;
    }

    private static function pick_next_upcoming_match(array $rows, callable $call)
    {
        $prepared = [];
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $kolo_no = self::extract_round_no((string) ($row->kolo_slug ?? ''));
            $ts = $call('parse_match_timestamp', (string) ($row->match_date ?? ''), true);
            $prepared[] = [
                'row' => $row,
                'kolo_no' => $kolo_no > 0 ? $kolo_no : PHP_INT_MAX,
                'match_ts' => $ts === false ? PHP_INT_MAX : intval($ts),
                'id' => intval($row->id ?? 0),
            ];
        }

        if (empty($prepared)) {
            return is_object($rows[0] ?? null) ? $rows[0] : null;
        }

        usort($prepared, static function ($a, $b) {
            if ($a['kolo_no'] !== $b['kolo_no']) {
                return $a['kolo_no'] <=> $b['kolo_no'];
            }
            if ($a['match_ts'] !== $b['match_ts']) {
                return $a['match_ts'] <=> $b['match_ts'];
            }
            return $a['id'] <=> $b['id'];
        });

        return is_object($prepared[0]['row'] ?? null) ? $prepared[0]['row'] : (is_object($rows[0] ?? null) ? $rows[0] : null);
    }

    private static function normalize_played($played, $odigrana)
    {
        $value = trim((string) $played);
        if ($value === '') {
            $value = trim((string) $odigrana);
        }
        $value = strtolower($value);
        if ($value === '') {
            return '';
        }
        if (in_array($value, ['1', 'true', 'yes', 'da', 'on'], true)) {
            return '1';
        }
        if (in_array($value, ['0', 'false', 'no', 'ne', 'off'], true)) {
            return '0';
        }
        return '';
    }

    private static function extract_round_no($kolo_slug)
    {
        $kolo_slug = strtolower(trim((string) $kolo_slug));
        if ($kolo_slug === '') {
            return 0;
        }
        if (preg_match('/(\d+)/', $kolo_slug, $m)) {
            return intval($m[1]);
        }
        return 0;
    }
}
