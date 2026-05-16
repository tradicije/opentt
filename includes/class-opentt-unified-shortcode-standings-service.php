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

final class OpenTT_Unified_Shortcode_Standings_Service
{
    public static function db_build_standings_for_competition($liga_slug, $sezona_slug = '', $max_kolo = null, array $deps = [])
    {
        $db_get_matches = isset($deps['db_get_matches']) && is_callable($deps['db_get_matches'])
            ? $deps['db_get_matches']
            : null;
        $get_competition_rule_data = isset($deps['get_competition_rule_data']) && is_callable($deps['get_competition_rule_data'])
            ? $deps['get_competition_rule_data']
            : null;
        $extract_round_no = isset($deps['extract_round_no']) && is_callable($deps['extract_round_no'])
            ? $deps['extract_round_no']
            : null;

        if (!$db_get_matches || !$get_competition_rule_data || !$extract_round_no) {
            return [];
        }

        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($liga_slug === '') {
            return [];
        }

        $rows = $db_get_matches([
            'limit' => -1,
            'liga_slug' => $liga_slug,
            'sezona_slug' => $sezona_slug,
            'kolo_slug' => '',
            'played' => '',
            'club_id' => 0,
            'player_id' => 0,
        ]);
        if (empty($rows)) {
            return [];
        }

        $sistem = 'novi';
        $rule = $get_competition_rule_data($liga_slug, $sezona_slug);
        if (is_array($rule) && !empty($rule['bodovanje_tip'])) {
            $sistem = ((string) $rule['bodovanje_tip'] === '3-0_4-3_2-1') ? 'novi' : 'stari';
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
            $round = intval($extract_round_no((string) $r->kolo_slug));
            if ($max_kolo !== null && $round > intval($max_kolo)) {
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

        uasort($stat, static function ($a, $b) {
            if ($a['bodovi'] === $b['bodovi']) {
                if ($a['meckol'] === $b['meckol']) {
                    return 0;
                }
                return ($a['meckol'] > $b['meckol']) ? -1 : 1;
            }
            return ($a['bodovi'] > $b['bodovi']) ? -1 : 1;
        });

        $out = [];
        $rank = 0;
        foreach ($stat as $club_id => $row) {
            $rank++;
            $out[] = [
                'rank' => $rank,
                'club_id' => intval($club_id),
                'odigrane' => intval($row['odigrane']),
                'pobede' => intval($row['pobede']),
                'porazi' => intval($row['porazi']),
                'bodovi' => intval($row['bodovi']),
                'meckol' => intval($row['meckol']),
            ];
        }

        return $out;
    }

    public static function find_club_rank_in_standings($standings, $club_id)
    {
        $club_id = intval($club_id);
        if ($club_id <= 0 || empty($standings) || !is_array($standings)) {
            return 0;
        }
        foreach ($standings as $row) {
            if (intval($row['club_id'] ?? 0) === $club_id) {
                return intval($row['rank'] ?? 0);
            }
        }
        return 0;
    }

    public static function build_standings_window_around_club($standings, $club_rank, $radius = 2)
    {
        if (empty($standings) || !is_array($standings) || $club_rank <= 0) {
            return [];
        }
        $radius = max(0, intval($radius));
        $from = max(1, intval($club_rank) - $radius);
        $to = intval($club_rank) + $radius;
        $slice = [];
        foreach ($standings as $row) {
            $rank = intval($row['rank'] ?? 0);
            if ($rank >= $from && $rank <= $to) {
                $slice[] = $row;
            }
        }
        return $slice;
    }

    public static function format_percentage_value($value)
    {
        $value = max(0.0, floatval($value));
        if (abs($value - round($value)) < 0.05) {
            return (string) intval(round($value));
        }
        return (string) round($value, 1);
    }
}

