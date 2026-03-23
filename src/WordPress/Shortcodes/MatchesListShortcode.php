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

final class MatchesListShortcode
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
            'limit' => -1,
            'klub' => '',
            'played' => '',
            'odigrana' => '',
            'liga' => '',
            'sezona' => '',
            'season' => '',
            'kolo' => '',
            'highlight' => '',
        ], $atts);

        $query_args = (array) $call('build_match_query_args', $atts);
        $query_args['limit'] = -1;

        if (!empty($atts['kolo'])) {
            $query_args['kolo_slug'] = sanitize_title((string) $atts['kolo']);
        }

        $rows = $call('db_get_matches', $query_args);
        $rows = is_array($rows) ? $rows : [];
        if (empty($rows)) {
            $raw_liga = sanitize_title((string) ($atts['liga'] ?? ''));
            $raw_season = sanitize_title((string) (!empty($atts['season']) ? $atts['season'] : ($atts['sezona'] ?? '')));
            if ($raw_liga !== '' && $raw_season !== '') {
                $legacy_args = $query_args;
                $legacy_args['liga_slug'] = sanitize_title($raw_liga . '-' . $raw_season);

                $rows = $call('db_get_matches', $legacy_args);
                $rows = is_array($rows) ? $rows : [];

                if (empty($rows)) {
                    // Some older rows used combined liga slug while sezona was empty.
                    $legacy_args['sezona_slug'] = '';
                    $rows = $call('db_get_matches', $legacy_args);
                    $rows = is_array($rows) ? $rows : [];
                }
            }
        }
        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Utakmice') . '<p>Nema utakmica za prikaz.</p>';
        }

        $highlight_ids = self::resolve_highlight_ids((string) ($atts['highlight'] ?? ''));
        $prepared = self::build_round_data($rows, $call, $highlight_ids);
        if (empty($prepared['rounds']) || empty($prepared['matches_by_round'])) {
            return (string) $call('shortcode_title_html', 'Utakmice') . '<p>Nema utakmica za prikaz.</p>';
        }

        $default_round = self::resolve_default_round_slug($prepared['rounds'], $query_args, $atts);
        if ($default_round === '') {
            $default_round = self::resolve_first_upcoming_round_slug($prepared['rounds'], $prepared['matches_by_round']);
        }
        if ($default_round === '') {
            $default_round = (string) ($prepared['rounds'][count($prepared['rounds']) - 1]['slug'] ?? '');
        }
        $default_round_name = '';
        $default_round_index = 0;
        foreach ($prepared['rounds'] as $idx => $round_meta) {
            if ((string) ($round_meta['slug'] ?? '') === $default_round) {
                $default_round_name = (string) ($round_meta['name'] ?? '');
                $default_round_index = intval($idx);
                break;
            }
        }
        if ($default_round_name === '' && !empty($prepared['rounds'])) {
            $default_round_name = (string) ($prepared['rounds'][0]['name'] ?? '');
            $default_round_index = 0;
        }

        $initial_list = [];
        if ($default_round !== '' && !empty($prepared['matches_by_round'][$default_round]) && is_array($prepared['matches_by_round'][$default_round])) {
            $initial_list = $prepared['matches_by_round'][$default_round];
        } else {
            foreach ($prepared['rounds'] as $round_meta) {
                $slug = (string) ($round_meta['slug'] ?? '');
                $candidate = $prepared['matches_by_round'][$slug] ?? [];
                if (is_array($candidate) && !empty($candidate)) {
                    $initial_list = $candidate;
                    break;
                }
            }
        }

        $round_lists = [];
        $round_html_by_index = [];
        foreach ($prepared['rounds'] as $round_meta) {
            $slug = (string) ($round_meta['slug'] ?? '');
            $list = $prepared['matches_by_round'][$slug] ?? [];
            $list = is_array($list) ? array_values($list) : [];
            $round_lists[] = $list;
            $round_html_by_index[] = self::render_initial_rows_html($list);
        }

        $payload = [
            'rounds' => $prepared['rounds'],
            'matchesByRound' => $prepared['matches_by_round'],
            'roundLists' => $round_lists,
            'roundHtmlByIndex' => $round_html_by_index,
            'defaultRound' => $default_round,
            'defaultRoundIndex' => $default_round_index,
            'i18n' => [
                'prev' => '&lsaquo;',
                'next' => '&rsaquo;',
                'noMatches' => 'Nema utakmica u ovom kolu.',
                'reportLabel' => 'Izveštaj',
                'videoLabel' => 'Snimak',
            ],
        ];

        $uid = 'opentt-matches-list-' . wp_unique_id();
        $prev_slug = '';
        $next_slug = '';
        if (!empty($prepared['rounds'])) {
            if ($default_round_index > 0 && isset($prepared['rounds'][$default_round_index - 1]['slug'])) {
                $prev_slug = (string) $prepared['rounds'][$default_round_index - 1]['slug'];
            }
            if (isset($prepared['rounds'][$default_round_index + 1]['slug'])) {
                $next_slug = (string) $prepared['rounds'][$default_round_index + 1]['slug'];
            }
        }
        $prev_url = $prev_slug !== '' ? add_query_arg('opentt_matches_list_round', $prev_slug) : '';
        $next_url = $next_slug !== '' ? add_query_arg('opentt_matches_list_round', $next_slug) : '';

        ob_start();
        echo (string) $call('shortcode_title_html', 'Utakmice');
        echo '<div id="' . esc_attr($uid) . '" class="opentt-matches-list" data-opentt-matches-list="1">';
        echo '<div class="opentt-matches-list-nav" role="group" aria-label="Kolo navigacija">';
        echo '<button type="button" class="opentt-matches-list-nav-btn is-prev' . ($prev_url === '' ? ' is-disabled' : '') . '" data-direction="-1" aria-label="Prethodno kolo" ' . ($prev_url === '' ? 'disabled aria-disabled="true"' : '') . '>&lsaquo;</button>';
        echo '<div class="opentt-matches-list-round" aria-live="polite">' . esc_html($default_round_name) . '</div>';
        echo '<button type="button" class="opentt-matches-list-nav-btn is-next' . ($next_url === '' ? ' is-disabled' : '') . '" data-direction="1" aria-label="Sledeće kolo" ' . ($next_url === '' ? 'disabled aria-disabled="true"' : '') . '>&rsaquo;</button>';
        echo '</div>';
        if ($prev_url !== '' || $next_url !== '') {
            echo '<noscript><div class="opentt-matches-list-nav op-nojs" role="group" aria-label="Kolo navigacija fallback">';
            if ($prev_url !== '') {
                echo '<a class="opentt-matches-list-nav-btn is-prev" href="' . esc_url($prev_url) . '" aria-label="Prethodno kolo">&lsaquo;</a>';
            } else {
                echo '<span class="opentt-matches-list-nav-btn is-prev is-disabled" aria-hidden="true">&lsaquo;</span>';
            }
            echo '<div class="opentt-matches-list-round" aria-hidden="true">' . esc_html($default_round_name) . '</div>';
            if ($next_url !== '') {
                echo '<a class="opentt-matches-list-nav-btn is-next" href="' . esc_url($next_url) . '" aria-label="Sledeće kolo">&rsaquo;</a>';
            } else {
                echo '<span class="opentt-matches-list-nav-btn is-next is-disabled" aria-hidden="true">&rsaquo;</span>';
            }
            echo '</div></noscript>';
        }
        $payload_json = wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (!is_string($payload_json) || $payload_json === '') {
            $payload_json = '{}';
        }
        echo '<script type="application/json" class="opentt-matches-list-data">' . $payload_json . '</script>';
        echo '<div class="opentt-matches-list-body">' . self::render_initial_rows_html($initial_list) . '</div>';
        echo '</div>';
        echo self::render_last_update_footer($rows);
        return ob_get_clean();
    }

    private static function build_round_data(array $rows, callable $call, array $highlight_ids = [])
    {
        $matches_by_round = [];
        $highlight_map = [];
        foreach ($highlight_ids as $highlight_id) {
            $highlight_map[intval($highlight_id)] = true;
        }

        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }

            $kolo_slug = sanitize_title((string) ($row->kolo_slug ?? ''));
            if ($kolo_slug === '') {
                continue;
            }

            $home_id = intval($row->home_club_post_id ?? 0);
            $away_id = intval($row->away_club_post_id ?? 0);
            $match_link = (string) $call('match_permalink', $row);
            $home_name = $home_id > 0 ? self::decode_title_entities((string) get_the_title($home_id)) : '';
            $away_name = $away_id > 0 ? self::decode_title_entities((string) get_the_title($away_id)) : '';
            $home_score = intval($row->home_score ?? 0);
            $away_score = intval($row->away_score ?? 0);
            $is_played = intval($row->played ?? 0) === 1 || $home_score > 0 || $away_score > 0;
            $show_time = !$is_played && $home_score === 0 && $away_score === 0;
            $home_class = '';
            $away_class = '';
            if ($is_played) {
                if ($home_score > $away_score) {
                    $home_class = 'is-winner';
                    $away_class = 'is-loser';
                } elseif ($away_score > $home_score) {
                    $home_class = 'is-loser';
                    $away_class = 'is-winner';
                }
            }

            $is_highlighted = ($home_id > 0 && isset($highlight_map[$home_id])) || ($away_id > 0 && isset($highlight_map[$away_id]));

            $matches_by_round[$kolo_slug][] = [
                'id' => intval($row->id ?? 0),
                'matchDateRaw' => (string) ($row->match_date ?? ''),
                'date' => (string) $call('display_match_date', $row->match_date ?? ''),
                'homeName' => $home_name,
                'awayName' => $away_name,
                'homeLogo' => $home_id > 0 ? (string) $call('club_logo_html', $home_id, 'thumbnail', ['class' => 'opentt-list-team-crest']) : '',
                'awayLogo' => $away_id > 0 ? (string) $call('club_logo_html', $away_id, 'thumbnail', ['class' => 'opentt-list-team-crest']) : '',
                'homeScore' => $home_score,
                'awayScore' => $away_score,
                'isPlayed' => $is_played,
                'homeClass' => $home_class,
                'awayClass' => $away_class,
                'showTime' => $show_time,
                'timeLabel' => (string) $call('display_match_time', $row->match_date ?? ''),
                'link' => $match_link,
                'reportUrl' => trim((string) ($row->report_url ?? '')),
                'videoUrl' => trim((string) ($row->video_url ?? '')),
                'rowClass' => $is_highlighted ? 'is-highlight' : '',
            ];
        }

        if (empty($matches_by_round)) {
            return ['rounds' => [], 'matches_by_round' => []];
        }

        foreach ($matches_by_round as &$round_rows) {
            usort($round_rows, static function ($a, $b) {
                $at = strtotime((string) ($a['matchDateRaw'] ?? '')) ?: 0;
                $bt = strtotime((string) ($b['matchDateRaw'] ?? '')) ?: 0;
                if ($at !== $bt) {
                    return $at <=> $bt;
                }
                return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
            });
        }
        unset($round_rows);

        $rounds = [];
        foreach (array_keys($matches_by_round) as $slug) {
            $num = intval($call('extract_round_no', $slug));
            $name = (string) $call('kolo_name_from_slug', $slug);
            if ($name === '' && $num > 0) {
                $name = $num . '. kolo';
            }
            if ($name === '') {
                $name = $slug;
            }
            $rounds[] = [
                'slug' => $slug,
                'name' => $name,
                'num' => $num,
            ];
        }

        usort($rounds, static function ($a, $b) {
            $an = intval($a['num'] ?? 0);
            $bn = intval($b['num'] ?? 0);
            if ($an > 0 && $bn > 0 && $an !== $bn) {
                return $an <=> $bn;
            }
            if ($an > 0 && $bn <= 0) {
                return -1;
            }
            if ($bn > 0 && $an <= 0) {
                return 1;
            }
            return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return [
            'rounds' => $rounds,
            'matches_by_round' => $matches_by_round,
        ];
    }

    private static function resolve_default_round_slug(array $rounds, array $query_args, array $atts)
    {
        $target = isset($_GET['opentt_matches_list_round'])
            ? sanitize_title((string) wp_unslash($_GET['opentt_matches_list_round'])) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            : '';
        if ($target === '') {
            $target = sanitize_title((string) ($query_args['kolo_slug'] ?? ''));
        }
        if ($target === '') {
            $target = sanitize_title((string) ($atts['kolo'] ?? ''));
        }
        if ($target === '') {
            return '';
        }

        foreach ($rounds as $round) {
            if ((string) ($round['slug'] ?? '') === $target) {
                return $target;
            }
        }

        return '';
    }

    private static function resolve_first_upcoming_round_slug(array $rounds, array $matches_by_round)
    {
        foreach ($rounds as $round) {
            $slug = (string) ($round['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $list = $matches_by_round[$slug] ?? [];
            if (!is_array($list) || empty($list)) {
                continue;
            }
            foreach ($list as $match) {
                if (!is_array($match)) {
                    continue;
                }
                $is_played = !empty($match['isPlayed']);
                if (!$is_played) {
                    return $slug;
                }
            }
        }

        return '';
    }

    private static function decode_title_entities($value)
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }

        // Some legacy titles can be saved as doubly-encoded entities (e.g. &amp;#8211;).
        // Decode a few passes to normalize to visible UTF-8 characters.
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }

        return $value;
    }

    private static function render_last_update_footer(array $rows)
    {
        $timestamp = self::resolve_last_update_timestamp($rows);
        if ($timestamp <= 0) {
            return '';
        }

        $user_id = intval(get_option(\OpenTT_Unified_Core::OPTION_MATCHES_LAST_EDITOR_ID, 0));
        $stored_name = trim((string) get_option(\OpenTT_Unified_Core::OPTION_MATCHES_LAST_EDITOR_NAME, ''));
        $stored_avatar_url = esc_url((string) get_option(\OpenTT_Unified_Core::OPTION_MATCHES_LAST_EDITOR_AVATAR_URL, ''));
        $name = $user_id > 0 ? trim((string) get_the_author_meta('display_name', $user_id)) : '';
        if ($name === '' && $stored_name !== '') {
            $name = $stored_name;
        }
        if ($name === '') {
            $name = 'Administrator';
        }
        $author_url = '';
        if ($user_id > 0 && class_exists('\\OpenTT_Unified_Core')) {
            $author_url = (string) \OpenTT_Unified_Core::resolve_updater_profile_url($user_id);
        }

        if ($user_id > 0) {
            $avatar_html = get_avatar($user_id, 44, '', $name, ['class' => 'opentt-data-updated-avatar-img']);
        } elseif ($stored_avatar_url !== '') {
            $avatar_html = '<img class="opentt-data-updated-avatar-img" src="' . esc_url($stored_avatar_url) . '" alt="' . esc_attr($name) . '" />';
        } else {
            $avatar_html = '<span class="opentt-data-updated-avatar-fallback"></span>';
        }

        $badge_url = self::discover_icon_url([
            'assets/icons/admin-badge.svg',
            'assets/icons/admin-badge-icon.svg',
            'assets/icons/admin-icon.svg',
            'assets/icons/badge-admin.svg',
        ]);
        $datetime_label = wp_date('d.m.Y H:i', $timestamp, wp_timezone());

        $html = '<div class="opentt-data-updated opentt-data-updated--center">';
        $html .= '<span class="opentt-data-updated-row opentt-data-updated-row--label"><span class="opentt-data-updated-label">Podatke uneo:</span></span>';
        $html .= '<span class="opentt-data-updated-row opentt-data-updated-row--user">';
        if ($author_url !== '') {
            $html .= '<a class="opentt-data-updated-user" href="' . esc_url($author_url) . '">';
        } else {
            $html .= '<span class="opentt-data-updated-user">';
        }
        $html .= '<span class="opentt-data-updated-avatar-wrap">';
        $html .= '<span class="opentt-data-updated-avatar">' . $avatar_html . '</span>';
        if ($badge_url !== '') {
            $html .= '<span class="opentt-data-updated-admin-badge" aria-hidden="true" style="--opentt-admin-badge-icon:url(\'' . esc_url($badge_url) . '\')"></span>';
        }
        $html .= '</span>';
        $html .= '<span class="opentt-data-updated-meta"><strong>' . esc_html($name) . '</strong><span>' . esc_html($datetime_label) . '</span></span>';
        if ($author_url !== '') {
            $html .= '</a>';
        } else {
            $html .= '</span>';
        }
        $html .= '</span>';
        $html .= '</div>';

        return $html;
    }

    private static function resolve_last_update_timestamp(array $rows)
    {
        $latest = 0;
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $raw = trim((string) ($row->updated_at ?? ''));
            if ($raw === '') {
                continue;
            }
            $ts = strtotime($raw);
            if ($ts !== false) {
                $latest = max($latest, intval($ts));
            }
        }

        $opt = trim((string) get_option(\OpenTT_Unified_Core::OPTION_MATCHES_LAST_UPDATED_AT, ''));
        if ($opt !== '') {
            $opt_ts = strtotime($opt);
            if ($opt_ts !== false) {
                $latest = max($latest, intval($opt_ts));
            }
        }

        return $latest;
    }

    private static function discover_icon_url(array $relative_candidates)
    {
        $plugin_root = dirname(__DIR__, 3);
        $plugin_root_norm = wp_normalize_path($plugin_root);
        $plugins_root_norm = wp_normalize_path((string) WP_PLUGIN_DIR);
        if ($plugin_root_norm === '' || $plugins_root_norm === '' || strpos($plugin_root_norm, $plugins_root_norm) !== 0) {
            return '';
        }
        $relative_root = ltrim(substr($plugin_root_norm, strlen($plugins_root_norm)), '/');
        if ($relative_root === '') {
            return '';
        }
        $plugin_base_url = trailingslashit((string) WP_PLUGIN_URL) . str_replace('\\', '/', $relative_root);

        foreach ($relative_candidates as $relative_path) {
            $relative_path = ltrim((string) $relative_path, '/');
            if ($relative_path === '') {
                continue;
            }
            $absolute_path = $plugin_root . '/' . $relative_path;
            if (is_readable($absolute_path)) {
                return trailingslashit($plugin_base_url) . str_replace('\\', '/', $relative_path);
            }
        }

        return '';
    }

    private static function resolve_highlight_ids($raw)
    {
        $items = array_filter(array_map('trim', explode(',', (string) $raw)));
        if (empty($items)) {
            return [];
        }

        $ids = [];
        foreach ($items as $item) {
            if (is_numeric($item)) {
                $ids[] = intval($item);
                continue;
            }

            $post = get_page_by_path(sanitize_title($item), OBJECT, 'klub');
            if (!$post) {
                $post = get_page_by_title($item, OBJECT, 'klub');
            }
            if ($post && !is_wp_error($post)) {
                $ids[] = intval($post->ID);
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private static function render_initial_rows_html(array $list)
    {
        if (empty($list)) {
            return '<p>Nema utakmica u ovom kolu.</p>';
        }

        ob_start();
        echo '<div class="opentt-matches-list-items">';
        foreach ($list as $match) {
            if (!is_array($match)) {
                continue;
            }
            $row_class = trim((string) ($match['rowClass'] ?? ''));
            $row_class_attr = $row_class !== '' ? ' ' . $row_class : '';
            $match_link = (string) ($match['link'] ?? '#');
            $date = (string) ($match['date'] ?? '');
            $home_name = (string) ($match['homeName'] ?? '');
            $away_name = (string) ($match['awayName'] ?? '');
            $home_logo = (string) ($match['homeLogo'] ?? '');
            $away_logo = (string) ($match['awayLogo'] ?? '');
            $home_class = trim((string) ($match['homeClass'] ?? ''));
            $away_class = trim((string) ($match['awayClass'] ?? ''));
            $show_time = !empty($match['showTime']);
            $time_label = (string) ($match['timeLabel'] ?? '--:--');
            $home_score = (string) ($match['homeScore'] ?? '0');
            $away_score = (string) ($match['awayScore'] ?? '0');
            $report_url = trim((string) ($match['reportUrl'] ?? ''));
            $video_url = trim((string) ($match['videoUrl'] ?? ''));

            echo '<div class="opentt-matches-list-row' . esc_attr($row_class_attr) . '" data-link="' . esc_url($match_link) . '" tabindex="0" role="link">';
            echo '<div class="opentt-matches-list-col opentt-matches-list-col--date">' . esc_html($date) . '</div>';
            echo '<div class="opentt-matches-list-col opentt-matches-list-col--match">';
            echo '<span class="match-side match-side--home">';
            echo '<span class="team-name team-name--home ' . esc_attr($home_class) . '">' . esc_html($home_name) . '</span>';
            echo '<span class="team-crest">' . $home_logo . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</span>';
            echo '<span class="match-score ' . ($show_time ? 'is-time' : '') . '">';
            if ($show_time) {
                echo '<span class="match-time">' . esc_html($time_label !== '' ? $time_label : '--:--') . '</span>';
            } else {
                echo '<span class="team-score ' . esc_attr($home_class) . '">' . esc_html($home_score) . '</span>';
                echo '<span class="team-sep">:</span>';
                echo '<span class="team-score ' . esc_attr($away_class) . '">' . esc_html($away_score) . '</span>';
            }
            echo '</span>';
            echo '<span class="match-side match-side--away">';
            echo '<span class="team-crest">' . $away_logo . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="team-name team-name--away ' . esc_attr($away_class) . '">' . esc_html($away_name) . '</span>';
            echo '</span>';
            echo '</div>';
            echo '<div class="opentt-matches-list-col opentt-matches-list-col--media">';
            if ($report_url !== '') {
                echo '<a class="opentt-matches-list-icon opentt-matches-list-icon--report" href="' . esc_url($report_url) . '" aria-label="Izveštaj" title="Izveštaj">R</a>';
            }
            if ($video_url !== '') {
                echo '<a class="opentt-matches-list-icon opentt-matches-list-icon--video" href="' . esc_url($video_url) . '" aria-label="Snimak" title="Snimak">V</a>';
            }
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';

        return (string) ob_get_clean();
    }
}
