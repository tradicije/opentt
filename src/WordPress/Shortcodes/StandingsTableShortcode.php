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

final class StandingsTableShortcode
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
            'highlight' => '',
        ], $atts);

        $highlight_ids = [];
        $liga_slug = '';
        $sezona_slug = '';
        $max_kolo = null;
        $ctx = $call('current_match_context');
        $archive_ctx = $call('current_archive_context');

        if (!empty($atts['liga'])) {
            $raw_liga = sanitize_title((string) $atts['liga']);
            $raw_sezona = !empty($atts['sezona']) ? sanitize_title((string) $atts['sezona']) : '';

            $parsed = $call('parse_legacy_liga_sezona', $raw_liga, $raw_sezona);
            $parsed = is_array($parsed) ? $parsed : [];
            $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $raw_liga));
            $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? $raw_sezona));

            $term = get_term_by('slug', $raw_liga, 'liga_sezona');
            if ($term && !is_wp_error($term)) {
                $parsed_term = $call('parse_legacy_liga_sezona', (string) $term->slug, $sezona_slug);
                $parsed_term = is_array($parsed_term) ? $parsed_term : [];
                $liga_slug = sanitize_title((string) ($parsed_term['league_slug'] ?? $liga_slug));
                $sezona_slug = sanitize_title((string) ($parsed_term['season_slug'] ?? $sezona_slug));
            }

            if (!empty($atts['highlight'])) {
                $names = array_filter(array_map('trim', explode(',', (string) $atts['highlight'])));
                foreach ($names as $name) {
                    if (is_numeric($name)) {
                        $highlight_ids[] = intval($name);
                        continue;
                    }
                    $post = get_page_by_path($name, OBJECT, 'klub');
                    if (!$post) {
                        $post = get_page_by_title($name, OBJECT, 'klub');
                    }
                    if ($post && !is_wp_error($post)) {
                        $highlight_ids[] = intval($post->ID);
                    }
                }
            }
        } elseif (is_array($ctx) && !empty($ctx['db_row'])) {
            $db_row = $ctx['db_row'];
            if (!$db_row && is_singular('utakmica')) {
                $legacy_match_id = intval(get_the_ID());
                $db_row = $call('db_get_match_by_legacy_id', $legacy_match_id);
            }
            if ($db_row) {
                $liga_slug = (string) $db_row->liga_slug;
                $sezona_slug = (string) $db_row->sezona_slug;
                $max_kolo = intval($call('extract_round_no', (string) $db_row->kolo_slug));
                $highlight_ids[] = intval($db_row->home_club_post_id);
                $highlight_ids[] = intval($db_row->away_club_post_id);
            }
        } elseif (is_singular('klub')) {
            $klub_id = intval(get_the_ID());
            $highlight_ids[] = $klub_id;
            $liga_slug = (string) $call('db_get_latest_liga_for_club', $klub_id);
        } elseif (is_tax('liga_sezona') || (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term) && !empty($term->slug)) {
                $parsed_term = $call('parse_legacy_liga_sezona', (string) $term->slug, '');
                $parsed_term = is_array($parsed_term) ? $parsed_term : [];
                $liga_slug = sanitize_title((string) ($parsed_term['league_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($parsed_term['season_slug'] ?? ''));
            } elseif (is_array($archive_ctx)) {
                $liga_slug = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
                $sezona_slug = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            }
        } elseif (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'kolo') {
            $kolo_slug = sanitize_title((string) ($archive_ctx['kolo_slug'] ?? ''));
            if ($kolo_slug !== '') {
                $max_kolo = intval($call('extract_round_no', $kolo_slug));
                global $wpdb;
                $table = (string) $call('db_table', 'matches');
                $table_exists = (bool) $call('table_exists', $table);
                if ($table_exists) {
                    $row = $wpdb->get_row($wpdb->prepare("SELECT liga_slug, sezona_slug FROM {$table} WHERE kolo_slug=%s ORDER BY id DESC LIMIT 1", $kolo_slug));
                    if ($row) {
                        $liga_slug = sanitize_title((string) ($row->liga_slug ?? ''));
                        $sezona_slug = sanitize_title((string) ($row->sezona_slug ?? ''));
                    }
                }
            }
        } else {
            return '';
        }

        if ($liga_slug === '') {
            return (string) $call('shortcode_title_html', 'Tabela') . '<p>Nema definisanu ligu/sezonu za ovu stranicu.</p>';
        }

        if ($sezona_slug === '') {
            $parsed_comp = $call('parse_legacy_liga_sezona', $liga_slug, '');
            $parsed_comp = is_array($parsed_comp) ? $parsed_comp : [];
            $parsed_liga = sanitize_title((string) ($parsed_comp['league_slug'] ?? ''));
            $parsed_sezona = sanitize_title((string) ($parsed_comp['season_slug'] ?? ''));
            if ($parsed_liga !== '' && $parsed_liga !== $liga_slug) {
                $liga_slug = $parsed_liga;
            }
            if ($parsed_sezona !== '') {
                $sezona_slug = $parsed_sezona;
            }
        }

        $rows = $call('db_get_matches', [
            'limit' => -1,
            'liga_slug' => $liga_slug,
            'sezona_slug' => $sezona_slug,
            'kolo_slug' => '',
            'played' => '',
            'club_id' => 0,
            'player_id' => 0,
        ]);
        $rows = is_array($rows) ? $rows : [];

        if (empty($rows)) {
            return (string) $call('shortcode_title_html', 'Tabela') . '<p>Nema utakmica za ovu ligu/sezonu.</p>';
        }

        $sistem = 'novi';
        $rule = $call('get_competition_rule_data', $liga_slug, $sezona_slug);
        if (is_array($rule) && !empty($rule['bodovanje_tip'])) {
            $sistem = ((string) $rule['bodovanje_tip'] === '3-0_4-3_2-1') ? 'novi' : 'stari';
        } else {
            $term = get_term_by('slug', $liga_slug, 'liga_sezona');
            if ($term && !is_wp_error($term)) {
                $sm = get_term_meta(intval($term->term_id), 'sistem_bodovanja', true);
                if (!empty($sm)) {
                    $sistem = (string) $sm;
                }
            }
        }

        $stat = [];
        foreach ($rows as $r) {
            $home = intval($r->home_club_post_id);
            $away = intval($r->away_club_post_id);
            foreach ([$home, $away] as $club_id) {
                if ($club_id <= 0) {
                    continue;
                }
                if (!isset($stat[$club_id])) {
                    $stat[$club_id] = [
                        'odigrane' => 0,
                        'pobede' => 0,
                        'porazi' => 0,
                        'bodovi' => 0,
                        'meckol' => 0,
                    ];
                }
            }
        }

        foreach ($rows as $r) {
            if (intval($r->played) !== 1) {
                continue;
            }

            $round = intval($call('extract_round_no', (string) $r->kolo_slug));
            if ($max_kolo !== null && $round > $max_kolo) {
                continue;
            }

            $home = intval($r->home_club_post_id);
            $away = intval($r->away_club_post_id);
            if ($home <= 0 || $away <= 0) {
                continue;
            }

            $rd = intval($r->home_score);
            $rg = intval($r->away_score);

            $stat[$home]['odigrane']++;
            $stat[$away]['odigrane']++;
            $stat[$home]['meckol'] += ($rd - $rg);
            $stat[$away]['meckol'] += ($rg - $rd);

            $home_win = ($rd > $rg);
            $away_win = ($rg > $rd);

            if ($home_win) {
                $stat[$home]['pobede']++;
                $stat[$away]['porazi']++;
            } elseif ($away_win) {
                $stat[$away]['pobede']++;
                $stat[$home]['porazi']++;
            }

            if ($sistem === 'novi') {
                if ($home_win) {
                    if ($rd === 4 && in_array($rg, [0, 1, 2], true)) {
                        $stat[$home]['bodovi'] += 3;
                    } elseif ($rd === 4 && $rg === 3) {
                        $stat[$home]['bodovi'] += 2;
                        $stat[$away]['bodovi'] += 1;
                    }
                } elseif ($away_win) {
                    if ($rg === 4 && in_array($rd, [0, 1, 2], true)) {
                        $stat[$away]['bodovi'] += 3;
                    } elseif ($rg === 4 && $rd === 3) {
                        $stat[$away]['bodovi'] += 2;
                        $stat[$home]['bodovi'] += 1;
                    }
                }
            } else {
                if ($home_win) {
                    $stat[$home]['bodovi'] += 2;
                    $stat[$away]['bodovi'] += 1;
                } elseif ($away_win) {
                    $stat[$away]['bodovi'] += 2;
                    $stat[$home]['bodovi'] += 1;
                }
            }
        }

        uasort($stat, function ($a, $b) {
            if ($a['bodovi'] === $b['bodovi']) {
                if ($a['meckol'] === $b['meckol']) {
                    return 0;
                }
                return ($a['meckol'] > $b['meckol']) ? -1 : 1;
            }
            return ($a['bodovi'] > $b['bodovi']) ? -1 : 1;
        });

        $promo_direct = 0;
        $promo_playoff = 0;
        $releg_direct = 0;
        $releg_playoff = 0;
        if (is_array($rule)) {
            $promo_direct = max(0, intval($rule['promocija_broj'] ?? 0));
            $promo_playoff = max(0, intval($rule['promocija_baraz_broj'] ?? 0));
            $releg_direct = max(0, intval($rule['ispadanje_broj'] ?? 0));
            $releg_playoff = max(0, intval($rule['ispadanje_razigravanje_broj'] ?? 0));
        }

        ob_start();
        $watermark_class = 'opentt-standings-watermark';
        $plugin_root = dirname(__DIR__, 3);
        $watermark_url = plugins_url('assets/img/club-logo.png', $plugin_root . '/includes/class-opentt-unified-core.php');
        $watermark_class .= ' has-watermark';
        $watermark_style = ' style="' . esc_attr("--opentt-standings-watermark:url('" . esc_url_raw($watermark_url) . "');") . '"';

        echo (string) $call('shortcode_title_html', 'Tabela');
        echo '<div class="' . esc_attr($watermark_class) . '"' . $watermark_style . '>';
        echo '<table class="tabela-lige">';
        echo '<thead><tr>';
        echo '<th>#</th>';
        echo '<th class="tabela-klub-left">Klub</th>';
        echo '<th data-tooltip="Odigrane utakmice">P</th>';
        echo '<th data-tooltip="Pobede">W</th>';
        echo '<th data-tooltip="Porazi">L</th>';
        echo '<th data-tooltip="Bod/Poeni">Pts</th>';
        echo '<th data-tooltip="Meč količnik">+/-</th>';
        echo '</tr></thead><tbody>';

        $rank = 0;
        $team_count = count($stat);
        foreach ($stat as $club_id => $data) {
            $rank++;
            $row_classes = [];
            if ($promo_direct > 0 && $rank <= $promo_direct) {
                $row_classes[] = 'zone-promote-direct';
            } elseif ($promo_playoff > 0 && $rank <= ($promo_direct + $promo_playoff)) {
                $row_classes[] = 'zone-promote-playoff';
            }

            if ($releg_direct > 0 && $rank > ($team_count - $releg_direct)) {
                $row_classes[] = 'zone-relegate-direct';
            } elseif ($releg_playoff > 0 && $rank > ($team_count - $releg_direct - $releg_playoff) && $rank <= ($team_count - $releg_direct)) {
                $row_classes[] = 'zone-relegate-playoff';
            }

            if (in_array(intval($club_id), $highlight_ids, true)) {
                $row_classes[] = 'highlight';
            }
            $class_attr = !empty($row_classes) ? ' class="' . esc_attr(implode(' ', $row_classes)) . '"' : '';
            echo '<tr' . $class_attr . '>';
            echo '<td>' . intval($rank) . '</td>';
            echo '<td class="klub-cell">';
            echo '<a href="' . esc_url(get_permalink($club_id)) . '">';
            echo (string) $call('club_logo_html', $club_id, 'thumbnail', ['style' => 'width:32px;height:32px;object-fit:contain;border-radius:3px;']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span>' . esc_html(get_the_title($club_id)) . '</span>';
            echo '</a>';
            echo '</td>';
            echo '<td>' . intval($data['odigrane']) . '</td>';
            echo '<td>' . intval($data['pobede']) . '</td>';
            echo '<td>' . intval($data['porazi']) . '</td>';
            echo '<td>' . intval($data['bodovi']) . '</td>';
            $kol = intval($data['meckol']);
            echo '<td>' . ($kol > 0 ? '+' : '') . $kol . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        return ob_get_clean();
    }
}
