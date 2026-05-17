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

if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Grid_Render_Service
{
    public static function render_matches_grid_html($rows, $columns, $with_kolo_attr, array $deps = [])
    {
        $extract_round_no = isset($deps['extract_round_no']) && is_callable($deps['extract_round_no']) ? $deps['extract_round_no'] : null;
        $kolo_heading_label = isset($deps['kolo_heading_label']) && is_callable($deps['kolo_heading_label']) ? $deps['kolo_heading_label'] : null;
        $display_match_date = isset($deps['display_match_date']) && is_callable($deps['display_match_date']) ? $deps['display_match_date'] : null;
        $display_match_time = isset($deps['display_match_time']) && is_callable($deps['display_match_time']) ? $deps['display_match_time'] : null;
        $match_permalink = isset($deps['match_permalink']) && is_callable($deps['match_permalink']) ? $deps['match_permalink'] : null;
        $parse_match_timestamp = isset($deps['parse_match_timestamp']) && is_callable($deps['parse_match_timestamp']) ? $deps['parse_match_timestamp'] : null;
        $is_match_live = isset($deps['is_match_live']) && is_callable($deps['is_match_live']) ? $deps['is_match_live'] : null;
        $render_team_html = isset($deps['render_team_html']) && is_callable($deps['render_team_html']) ? $deps['render_team_html'] : null;

        if (!$extract_round_no || !$kolo_heading_label || !$display_match_date || !$display_match_time || !$match_permalink || !$parse_match_timestamp || !$is_match_live || !$render_team_html) {
            return '<p>Nema utakmica za prikaz.</p>';
        }

        if (empty($rows)) {
            return '<p>Nema utakmica za prikaz.</p>';
        }

        ob_start();
        echo '<div class="opentt-grid-wrapper"><div class="opentt-grid cols-' . intval($columns) . '">';
        $last_kolo_slug = null;
        foreach ($rows as $row) {
            $home_id = intval($row->home_club_post_id);
            $away_id = intval($row->away_club_post_id);
            $rd = intval($row->home_score);
            $rg = intval($row->away_score);
            $is_played = intval($row->played) === 1 || $rd > 0 || $rg > 0;
            $live = (bool) $is_match_live($row);
            $is_upcoming_no_score = !$is_played && $rd === 0 && $rg === 0;
            $home_win = ($rd === 4);
            $away_win = ($rg === 4);
            $kolo_slug = sanitize_title((string) $row->kolo_slug);
            $kolo_no = intval($extract_round_no($kolo_slug));
            $kolo_name = (string) $kolo_heading_label($kolo_slug, $kolo_no);
            $date = (string) $display_match_date($row->match_date);
            $time = (string) $display_match_time($row->match_date);
            $time_label = $time !== '' ? $time : '--:--';
            $link = (string) $match_permalink($row);

            if ($kolo_slug !== '' && $kolo_slug !== $last_kolo_slug) {
                echo '<div class="opentt-grid-round-heading" data-kolo-slug="' . esc_attr($kolo_slug) . '"><span>' . esc_html($kolo_name) . '</span></div>';
                $last_kolo_slug = $kolo_slug;
            }

            $attr = '';
            if ($with_kolo_attr) {
                $match_ts = $parse_match_timestamp((string) $row->match_date);
                if ($match_ts === false) {
                    $match_ts = 0;
                }
                $match_date_iso = substr((string) $row->match_date, 0, 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $match_date_iso)) {
                    $match_date_iso = '';
                }
                $attr = ' data-kolo-slug="' . esc_attr($kolo_slug) . '"';
                $attr .= ' data-kolo-name="' . esc_attr($kolo_name) . '"';
                $attr .= ' data-kolo-no="' . esc_attr((string) $kolo_no) . '"';
                $attr .= ' data-match-ts="' . esc_attr((string) intval($match_ts)) . '"';
                $attr .= ' data-match-date="' . esc_attr($match_date_iso) . '"';
                $attr .= ' data-match-date-display="' . esc_attr($date) . '"';
                $attr .= ' data-played="' . esc_attr((string) intval($row->played)) . '"';
                $attr .= ' data-home-club-id="' . esc_attr((string) $home_id) . '"';
                $attr .= ' data-away-club-id="' . esc_attr((string) $away_id) . '"';
            }

            $is_highlight = !empty($row->opentt_is_highlight);
            $item_class = 'opentt-item'
                . ($live ? ' opentt-item-live' : '')
                . ($is_highlight ? ' is-highlight' : '');
            echo '<div class="' . esc_attr($item_class) . '"' . $attr . '>';
            echo '<a href="' . esc_url($link) . '">';
            echo '<div class="opentt-item-main">';
            echo '<div class="opentt-item-teams">';
            $fallback_score_label = $is_upcoming_no_score ? $time_label : '';
            echo $render_team_html($home_id, $rd, $home_win, !$is_upcoming_no_score, $fallback_score_label); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $render_team_html($away_id, $rg, $away_win, !$is_upcoming_no_score, ''); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            echo '<div class="opentt-item-side" aria-label="Vreme utakmice">';
            if ($live) {
                echo '<span class="opentt-item-side-top">' . esc_html($date) . '</span>';
                echo '<span class="opentt-item-side-bottom"><span class="opentt-live-badge">LIVE</span></span>';
            } elseif ($is_played) {
                echo '<span class="opentt-item-side-top">' . esc_html($date) . '</span>';
                echo '<span class="opentt-item-side-bottom">Kraj</span>';
            } else {
                echo '<span class="opentt-item-side-top">' . esc_html($date) . '</span>';
                echo '<span class="opentt-item-side-bottom">' . esc_html($time_label) . '</span>';
            }
            echo '</div>';
            echo '</div>';
            echo '</a></div>';
        }
        echo '</div></div>';
        return ob_get_clean();
    }

    public static function render_clubs_grid_html($rows, $columns, $with_attrs)
    {
        if (empty($rows)) {
            return '<p>Nema klubova za prikaz.</p>';
        }

        ob_start();
        echo '<div class="opentt-klubovi">';
        echo '<div class="opentt-klubovi-grid cols-' . intval($columns) . '">';
        foreach ($rows as $row) {
            $club_id = intval($row['id'] ?? 0);
            $url = (string) ($row['url'] ?? '');
            $display_name = (string) ($row['display_name'] ?? '');
            $league_label = (string) ($row['league_label'] ?? 'Bez takmičenja');
            $grad_label = trim((string) ($row['grad_label'] ?? ''));
            $logo_html = (string) ($row['logo_html'] ?? '');
            $sort_name = (string) ($row['sort_name'] ?? '');
            $league_slug = sanitize_title((string) ($row['league_slug'] ?? ''));
            $opstina_slug = sanitize_title((string) ($row['opstina_slug'] ?? ''));

            $attrs = ' data-club-id="' . esc_attr((string) $club_id) . '"';
            if ($with_attrs) {
                $attrs .= ' data-league-slug="' . esc_attr($league_slug) . '"';
                $attrs .= ' data-opstina-slug="' . esc_attr($opstina_slug) . '"';
                $attrs .= ' data-sort-name="' . esc_attr($sort_name) . '"';
            }

            echo '<article class="opentt-klubovi-item"' . $attrs . '>';
            echo '<a class="opentt-klubovi-link" href="' . esc_url($url) . '">';
            echo '<span class="opentt-klubovi-logo-wrap">' . $logo_html . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="opentt-klubovi-content">';
            echo '<strong class="opentt-klubovi-name">' . esc_html($display_name) . '</strong>';
            if ($grad_label !== '') {
                echo '<span class="opentt-klubovi-city">' . esc_html($grad_label) . '</span>';
            }
            echo '<span class="opentt-klubovi-league">' . esc_html($league_label) . '</span>';
            echo '</span>';
            echo '</a>';
            echo '</article>';
        }
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }
}

