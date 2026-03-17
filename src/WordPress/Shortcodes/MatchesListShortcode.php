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

        $payload = [
            'rounds' => $prepared['rounds'],
            'matchesByRound' => $prepared['matches_by_round'],
            'defaultRound' => $default_round,
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
        if ($prev_url !== '') {
            echo '<a class="opentt-matches-list-nav-btn is-prev" href="' . esc_url($prev_url) . '" aria-label="Prethodno kolo">&lsaquo;</a>';
        } else {
            echo '<span class="opentt-matches-list-nav-btn is-prev is-disabled" aria-hidden="true">&lsaquo;</span>';
        }
        echo '<div class="opentt-matches-list-round" aria-live="polite">' . esc_html($default_round_name) . '</div>';
        if ($next_url !== '') {
            echo '<a class="opentt-matches-list-nav-btn is-next" href="' . esc_url($next_url) . '" aria-label="Sledeće kolo">&rsaquo;</a>';
        } else {
            echo '<span class="opentt-matches-list-nav-btn is-next is-disabled" aria-hidden="true">&rsaquo;</span>';
        }
        echo '</div>';
        echo '<div class="opentt-matches-list-body">' . self::render_initial_rows_html($initial_list) . '</div>';
        echo '</div>';
        ?>
        <script>
        (function(){
          var root = document.getElementById(<?php echo wp_json_encode($uid); ?>);
          if (!root || root.dataset.openttListReady === '1') { return; }
          root.dataset.openttListReady = '1';

          var data = <?php echo wp_json_encode($payload); ?>;
          if (!data || !Array.isArray(data.rounds) || !data.rounds.length) { return; }

          var navPrev = root.querySelector('.opentt-matches-list-nav-btn.is-prev');
          var navNext = root.querySelector('.opentt-matches-list-nav-btn.is-next');
          var roundLabel = root.querySelector('.opentt-matches-list-round');
          var body = root.querySelector('.opentt-matches-list-body');
          var rounds = data.rounds;
          var matchesByRound = data.matchesByRound || {};
          var normalizedRoundKeys = {};

          function normalizeSlug(value) {
            return String(value || '').toLowerCase().trim();
          }

          function toList(value) {
            if (Array.isArray(value)) {
              return value;
            }
            if (!value || typeof value !== 'object') {
              return [];
            }
            var keys = Object.keys(value);
            if (!keys.length) {
              return [];
            }
            keys.sort(function(a, b){
              var ai = parseInt(a, 10);
              var bi = parseInt(b, 10);
              if (isNaN(ai) || isNaN(bi)) {
                return String(a).localeCompare(String(b));
              }
              return ai - bi;
            });
            return keys.map(function(key){ return value[key]; }).filter(Boolean);
          }

          Object.keys(matchesByRound).forEach(function(key){
            normalizedRoundKeys[normalizeSlug(key)] = key;
          });

          var roundIndex = 0;
          var i;
          for (i = 0; i < rounds.length; i++) {
            if ((rounds[i].slug || '') === (data.defaultRound || '')) {
              roundIndex = i;
              break;
            }
          }

          function esc(v) {
            var raw = (v === null || v === undefined) ? '' : v;
            return String(raw).replace(/[&<>"']/g, function(ch){
              if (ch === '&') { return '&amp;'; }
              if (ch === '<') { return '&lt;'; }
              if (ch === '>') { return '&gt;'; }
              if (ch === '"') { return '&quot;'; }
              return '&#39;';
            });
          }

          function icon(kind, url, label) {
            return '<a class="opentt-matches-list-icon opentt-matches-list-icon--' + kind + '" href="' + esc(url) + '" aria-label="' + esc(label) + '" title="' + esc(label) + '">' + esc(kind === 'report' ? 'R' : 'V') + '</a>';
          }

          function rowHtml(match) {
            var icons = '';
            if (match.reportUrl) {
              icons += icon('report', match.reportUrl, data.i18n.reportLabel || 'Izveštaj');
            }
            if (match.videoUrl) {
              icons += icon('video', match.videoUrl, data.i18n.videoLabel || 'Snimak');
            }

            return ''
              + '<div class="opentt-matches-list-row ' + esc(match.rowClass || '') + '" data-link="' + esc(match.link || '#') + '" tabindex="0" role="link" onclick="var u=this.getAttribute(\'data-link\');if(u){window.location.href=u;}" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();var u=this.getAttribute(\'data-link\');if(u){window.location.href=u;}}">'
              +   '<div class="opentt-matches-list-col opentt-matches-list-col--date">' + esc(match.date) + '</div>'
              +   '<div class="opentt-matches-list-col opentt-matches-list-col--match">'
              +     '<span class="match-side match-side--home">'
              +       '<span class="team-name team-name--home ' + esc(match.homeClass || '') + '">' + esc(match.homeName) + '</span>'
              +       '<span class="team-crest">' + (match.homeLogo || '') + '</span>'
              +     '</span>'
              +     '<span class="match-score ' + (match.showTime ? 'is-time' : '') + '">'
              +       (match.showTime
                        ? '<span class="match-time">' + esc(match.timeLabel || '--:--') + '</span>'
                        : '<span class="team-score ' + esc(match.homeClass || '') + '">' + esc(match.homeScore) + '</span>'
                          + '<span class="team-sep">:</span>'
                          + '<span class="team-score ' + esc(match.awayClass || '') + '">' + esc(match.awayScore) + '</span>')
              +     '</span>'
              +     '<span class="match-side match-side--away">'
              +       '<span class="team-crest">' + (match.awayLogo || '') + '</span>'
              +       '<span class="team-name team-name--away ' + esc(match.awayClass || '') + '">' + esc(match.awayName) + '</span>'
              +     '</span>'
              +   '</div>'
              +   '<div class="opentt-matches-list-col opentt-matches-list-col--media">' + icons + '</div>'
              + '</div>';
          }

          function resolveRoundList(roundSlug) {
            var direct = toList(matchesByRound[roundSlug]);
            if (direct.length) {
              return direct;
            }

            var normalizedKey = normalizedRoundKeys[normalizeSlug(roundSlug)] || '';
            if (normalizedKey) {
              var normalizedList = toList(matchesByRound[normalizedKey]);
              if (normalizedList.length) {
                return normalizedList;
              }
            }

            var roundPos;
            for (roundPos = 0; roundPos < rounds.length; roundPos++) {
              var slug = String((rounds[roundPos] && rounds[roundPos].slug) || '');
              var list = toList(matchesByRound[slug]);
              if (list.length) {
                return list;
              }
            }

            var allKeys = Object.keys(matchesByRound);
            for (roundPos = 0; roundPos < allKeys.length; roundPos++) {
              var anyList = toList(matchesByRound[allKeys[roundPos]]);
              if (anyList.length) {
                return anyList;
              }
            }

            return [];
          }

          function render() {
            var current = rounds[roundIndex] || null;
            if (!current) {
              body.innerHTML = '<p>' + esc(data.i18n.noMatches || 'Nema utakmica.') + '</p>';
              return;
            }

            roundLabel.textContent = current.name || current.slug || '';
            navPrev.disabled = roundIndex <= 0;
            navNext.disabled = roundIndex >= (rounds.length - 1);

            var list = resolveRoundList(current.slug || '');
            if (!list.length) {
              body.innerHTML = '<p>' + esc(data.i18n.noMatches || 'Nema utakmica.') + '</p>';
              return;
            }

            var html = '<div class="opentt-matches-list-items">';
            for (var idx = 0; idx < list.length; idx++) {
              html += rowHtml(list[idx]);
            }
            html += '</div>';
            body.innerHTML = html;
          }

          if (navPrev) {
            navPrev.addEventListener('click', function(e){
              e.preventDefault();
              if (roundIndex > 0) {
                roundIndex -= 1;
                render();
              }
            });
          }

          if (navNext) {
            navNext.addEventListener('click', function(e){
              e.preventDefault();
              if (roundIndex < rounds.length - 1) {
                roundIndex += 1;
                render();
              }
            });
          }

          root.addEventListener('click', function(e){
            var icon = e.target && e.target.closest ? e.target.closest('.opentt-matches-list-icon') : null;
            if (icon) {
              e.stopPropagation();
              return;
            }
            var row = e.target && e.target.closest ? e.target.closest('.opentt-matches-list-row') : null;
            if (!row) { return; }
            var link = row.getAttribute('data-link') || '';
            if (link) {
              window.location.href = link;
            }
          });

          root.addEventListener('keydown', function(e){
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var row = e.target && e.target.closest ? e.target.closest('.opentt-matches-list-row') : null;
            if (!row) { return; }
            e.preventDefault();
            var link = row.getAttribute('data-link') || '';
            if (link) {
              window.location.href = link;
            }
          });

          render();
        })();
        </script>
        <?php

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

            echo '<div class="opentt-matches-list-row' . esc_attr($row_class_attr) . '" data-link="' . esc_url($match_link) . '" tabindex="0" role="link" onclick="var u=this.getAttribute(\'data-link\');if(u){window.location.href=u;}" onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();var u=this.getAttribute(\'data-link\');if(u){window.location.href=u;}}">';
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
