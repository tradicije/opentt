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

namespace OpenTT\Unified\WordPress;

final class UserPortalLeagueGamesService
{
    private static function matchFormatByCompetition($ligaSlug, $sezonaSlug)
    {
        $ligaSlug = sanitize_title((string) $ligaSlug);
        $sezonaSlug = sanitize_title((string) $sezonaSlug);
        if ($ligaSlug === '' || $sezonaSlug === '') {
            return 'format_a';
        }

        $rules = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'opentt_competition_league_slug', 'value' => $ligaSlug, 'compare' => '='],
                ['key' => 'opentt_competition_season_slug', 'value' => $sezonaSlug, 'compare' => '='],
            ],
        ]) ?: [];
        $ruleId = !empty($rules) ? intval($rules[0]) : 0;
        if ($ruleId <= 0) {
            return 'format_a';
        }

        $format = sanitize_key((string) get_post_meta($ruleId, 'format_partija', true));
        return $format === 'format_b' ? 'format_b' : 'format_a';
    }

    private static function lineupTemplateByFormat($format)
    {
        if ($format === 'format_b') {
            return [
                1 => ['home' => 'a', 'away' => 'y', 'doubles' => false],
                2 => ['home' => 'b', 'away' => 'x', 'doubles' => false],
                3 => ['home' => 'c', 'away' => 'z', 'doubles' => false],
                4 => ['home' => 'a', 'away' => 'x', 'doubles' => false],
                5 => ['home' => 'c', 'away' => 'y', 'doubles' => false],
                6 => ['home' => 'b', 'away' => 'z', 'doubles' => false],
                7 => ['home' => '', 'away' => '', 'doubles' => true],
            ];
        }

        return [
            1 => ['home' => 'a', 'away' => 'y', 'doubles' => false],
            2 => ['home' => 'b', 'away' => 'x', 'doubles' => false],
            3 => ['home' => 'c', 'away' => 'z', 'doubles' => false],
            4 => ['home' => '', 'away' => '', 'doubles' => true],
            5 => ['home' => 'a', 'away' => 'x', 'doubles' => false],
            6 => ['home' => 'c', 'away' => 'y', 'doubles' => false],
            7 => ['home' => 'b', 'away' => 'z', 'doubles' => false],
        ];
    }

    private static function lineupIdByKey(array $lineup, $key)
    {
        $key = sanitize_key((string) $key);
        return isset($lineup[$key]) ? intval($lineup[$key]) : 0;
    }

    private static function resolveGeneratedSinglesPlayers($orderNo, $format, array $lineup)
    {
        $template = self::lineupTemplateByFormat($format);
        $row = isset($template[intval($orderNo)]) && is_array($template[intval($orderNo)]) ? $template[intval($orderNo)] : null;
        if (!$row || !empty($row['doubles'])) {
            return [0, 0];
        }

        $homeKey = (string) ($row['home'] ?? '');
        $awayKey = (string) ($row['away'] ?? '');
        $home = self::lineupIdByKey($lineup, 'home_' . $homeKey);
        $away = self::lineupIdByKey($lineup, 'away_' . $awayKey);
        if ($home <= 0) {
            $home = self::lineupIdByKey($lineup, 'home_reserve');
        }
        if ($away <= 0) {
            $away = self::lineupIdByKey($lineup, 'away_reserve');
        }
        return [$home, $away];
    }

    public static function renderPlayerSelect($name, array $options, $selectedId)
    {
        $out = '<select name="' . esc_attr((string) $name) . '"><option value="">- izaberi -</option>';
        foreach ($options as $option) {
            if (!is_array($option)) {
                continue;
            }
            $pid = intval($option['id'] ?? 0);
            $title = (string) ($option['title'] ?? '');
            if ($pid <= 0 || $title === '') {
                continue;
            }
            $out .= '<option value="' . esc_attr((string) $pid) . '"' . selected(intval($selectedId), $pid, false) . '>' . esc_html($title) . '</option>';
        }
        $out .= '</select>';
        return $out;
    }

    public static function playersByClub($clubId)
    {
        $clubId = intval($clubId);
        if ($clubId <= 0) {
            return [];
        }
        $players = get_posts([
            'post_type' => 'igrac',
            'numberposts' => 200,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'meta_query' => [
                'relation' => 'OR',
                ['key' => 'povezani_klub', 'value' => $clubId, 'compare' => '=', 'type' => 'NUMERIC'],
                ['key' => 'klub_igraca', 'value' => $clubId, 'compare' => '=', 'type' => 'NUMERIC'],
            ],
            'orderby' => 'title',
            'order' => 'ASC',
        ]) ?: [];

        $out = [];
        foreach ($players as $player) {
            if (!($player instanceof \WP_Post)) {
                continue;
            }
            $out[] = [
                'id' => intval($player->ID),
                'title' => (string) $player->post_title,
            ];
        }
        return $out;
    }

    public static function expectedDoublesOrderByCompetition($ligaSlug, $sezonaSlug)
    {
        return self::matchFormatByCompetition($ligaSlug, $sezonaSlug) === 'format_b' ? 7 : 4;
    }

    public static function renderLeagueMatchGamesForm($matchId, $homeClubId, $awayClubId, $maxGames, $deps = [])
    {
        $tableExists = $deps['tableExists'];

        $matchId = intval($matchId);
        if ($matchId <= 0) {
            return '';
        }

        global $wpdb;
        $gamesTable = \OpenTT_Unified_Core::db_table('games');
        $setsTable = \OpenTT_Unified_Core::db_table('sets');
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!$tableExists($gamesTable) || !$tableExists($setsTable) || !$tableExists($matchesTable)) {
            return '';
        }

        $match = $wpdb->get_row($wpdb->prepare("SELECT liga_slug,sezona_slug FROM {$matchesTable} WHERE id=%d LIMIT 1", $matchId));
        $matchFormat = self::matchFormatByCompetition(
            is_object($match) ? (string) ($match->liga_slug ?? '') : '',
            is_object($match) ? (string) ($match->sezona_slug ?? '') : ''
        );
        $expectedDoublesOrder = $matchFormat === 'format_b' ? 7 : 4;

        $existingGames = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$gamesTable} WHERE match_id=%d ORDER BY order_no ASC, id ASC", $matchId)) ?: [];
        $gamesByOrder = [];
        $setsByGame = [];
        foreach ($existingGames as $game) {
            if (!is_object($game)) {
                continue;
            }
            $orderNo = intval($game->order_no ?? 0);
            if ($orderNo <= 0) {
                continue;
            }
            $gamesByOrder[$orderNo] = $game;
            $gid = intval($game->id ?? 0);
            if ($gid <= 0) {
                continue;
            }
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$setsTable} WHERE game_id=%d ORDER BY set_no ASC, id ASC", $gid)) ?: [];
            $tmp = [];
            foreach ($rows as $sr) {
                if (!is_object($sr)) {
                    continue;
                }
                $tmp[intval($sr->set_no ?? 0)] = $sr;
            }
            $setsByGame[$gid] = $tmp;
        }

        $homePlayers = self::playersByClub($homeClubId);
        $awayPlayers = self::playersByClub($awayClubId);
        $maxGames = max(1, intval($maxGames));

        $out = '<section class="opentt-profile-subsection"><h4>Unos partija i setova</h4>';
        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form opentt-league-games-form">';
        $out .= wp_nonce_field('opentt_front_save_league_games_' . $matchId, '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_save_league_games">';
        $out .= '<input type="hidden" name="match_id" value="' . esc_attr((string) $matchId) . '">';
        $out .= '<div class="opentt-league-lineup-box">';
        $out .= '<div class="opentt-league-game-title">Sastavi za automatsko kreiranje partija</div>';
        $out .= '<p class="description">Izaberi domaće (A/B/C) i gostujuće (X/Y/Z) igrače. Sistem popunjava singl partije po formatu lige, dubl ostaje prazan za ručni unos.</p>';
        $out .= '<div class="opentt-inline-select-grid">';
        $out .= '<label>Domaći A' . self::renderPlayerSelect('lineup[home_a]', $homePlayers, 0) . '</label>';
        $out .= '<label>Domaći B' . self::renderPlayerSelect('lineup[home_b]', $homePlayers, 0) . '</label>';
        $out .= '<label>Domaći C' . self::renderPlayerSelect('lineup[home_c]', $homePlayers, 0) . '</label>';
        $out .= '<label>Domaći rezerva' . self::renderPlayerSelect('lineup[home_reserve]', $homePlayers, 0) . '</label>';
        $out .= '<label>Gost Y' . self::renderPlayerSelect('lineup[away_y]', $awayPlayers, 0) . '</label>';
        $out .= '<label>Gost X' . self::renderPlayerSelect('lineup[away_x]', $awayPlayers, 0) . '</label>';
        $out .= '<label>Gost Z' . self::renderPlayerSelect('lineup[away_z]', $awayPlayers, 0) . '</label>';
        $out .= '<label>Gost rezerva' . self::renderPlayerSelect('lineup[away_reserve]', $awayPlayers, 0) . '</label>';
        $out .= '</div>';
        $out .= '<div class="opentt-editor-media-row"><button type="button" class="button button-secondary opentt-apply-lineup-btn">Primeni sastave na partije</button></div>';
        $out .= '</div>';

        for ($orderNo = 1; $orderNo <= $maxGames; $orderNo++) {
            $game = isset($gamesByOrder[$orderNo]) ? $gamesByOrder[$orderNo] : null;
            $gameId = $game ? intval($game->id ?? 0) : 0;
            $isDoubles = $game ? intval($game->is_doubles ?? 0) === 1 : ($orderNo === $expectedDoublesOrder);
            $generatedPlayers = (!$game && !$isDoubles)
                ? self::resolveGeneratedSinglesPlayers($orderNo, $matchFormat, [])
                : [0, 0];
            $homeSets = $game ? intval($game->home_sets ?? 0) : 0;
            $awaySets = $game ? intval($game->away_sets ?? 0) : 0;
            $existingSets = ($gameId > 0 && isset($setsByGame[$gameId])) ? $setsByGame[$gameId] : [];

            $out .= '<div class="opentt-league-game-card" data-game-order="' . intval($orderNo) . '">';
            $out .= '<div class="opentt-league-game-title">Partija #' . intval($orderNo) . ($isDoubles ? ' (Dubl)' : '') . '</div>';
            $out .= '<input type="hidden" name="games[' . intval($orderNo) . '][order_no]" value="' . esc_attr((string) $orderNo) . '">';
            $out .= '<input type="hidden" name="games[' . intval($orderNo) . '][game_id]" value="' . esc_attr((string) $gameId) . '">';
            $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="games[' . intval($orderNo) . '][is_doubles]" value="1"' . checked($isDoubles, true, false) . '> Dubl</label>';
            $out .= '<div class="opentt-inline-select-grid">';
            $out .= '<label>Domaći igrač' . self::renderPlayerSelect('games[' . intval($orderNo) . '][home_player_post_id]', $homePlayers, $game ? intval($game->home_player_post_id ?? 0) : intval($generatedPlayers[0])) . '</label>';
            $out .= '<label>Gost igrač' . self::renderPlayerSelect('games[' . intval($orderNo) . '][away_player_post_id]', $awayPlayers, $game ? intval($game->away_player_post_id ?? 0) : intval($generatedPlayers[1])) . '</label>';
            $out .= '<label>Domaći setovi<input type="number" min="0" max="7" name="games[' . intval($orderNo) . '][home_sets]" value="' . esc_attr((string) $homeSets) . '"></label>';
            $out .= '<label>Gost setovi<input type="number" min="0" max="7" name="games[' . intval($orderNo) . '][away_sets]" value="' . esc_attr((string) $awaySets) . '"></label>';
            $out .= '<label>Domaći igrač 2' . self::renderPlayerSelect('games[' . intval($orderNo) . '][home_player2_post_id]', $homePlayers, $game ? intval($game->home_player2_post_id ?? 0) : 0) . '</label>';
            $out .= '<label>Gost igrač 2' . self::renderPlayerSelect('games[' . intval($orderNo) . '][away_player2_post_id]', $awayPlayers, $game ? intval($game->away_player2_post_id ?? 0) : 0) . '</label>';
            $out .= '</div>';
            $out .= '<div class="opentt-inline-select-grid">';
            for ($setNo = 1; $setNo <= 5; $setNo++) {
                $set = isset($existingSets[$setNo]) ? $existingSets[$setNo] : null;
                $hp = $set ? intval($set->home_points ?? 0) : '';
                $ap = $set ? intval($set->away_points ?? 0) : '';
                $out .= '<label>Set ' . intval($setNo) . ' (D:G)<span class="opentt-league-game-set-pair"><input type="number" min="0" max="30" name="games[' . intval($orderNo) . '][sets][' . intval($setNo) . '][home_points]" value="' . esc_attr((string) $hp) . '" placeholder="11"><span>:</span><input type="number" min="0" max="30" name="games[' . intval($orderNo) . '][sets][' . intval($setNo) . '][away_points]" value="' . esc_attr((string) $ap) . '" placeholder="9"></span></label>';
            }
            $out .= '</div>';
            $out .= '</div>';
        }

        $template = self::lineupTemplateByFormat($matchFormat);
        $out .= '<script>(function(){var form=document.currentScript&&document.currentScript.closest?document.currentScript.closest("form"):null;if(!form){return;}var btn=form.querySelector(".opentt-apply-lineup-btn");if(!btn){return;}var tpl=' . wp_json_encode($template) . ';function val(name){var el=form.querySelector("[name=\\"lineup["+name+"]\\"]");return el?String(el.value||""):"";}function pick(side,key){var direct=val(side+"_"+key);if(direct){return direct;}return val(side+"_reserve");}btn.addEventListener("click",function(){Object.keys(tpl).forEach(function(k){var row=tpl[k]||{};var card=form.querySelector(".opentt-league-game-card[data-game-order=\\""+k+"\\"]");if(!card){return;}var isD=!!row.doubles;var chk=card.querySelector("input[name=\\"games["+k+"][is_doubles]\\"]");if(chk){chk.checked=isD;}var h=card.querySelector("select[name=\\"games["+k+"][home_player_post_id]\\"]");var a=card.querySelector("select[name=\\"games["+k+"][away_player_post_id]\\"]");var h2=card.querySelector("select[name=\\"games["+k+"][home_player2_post_id]\\"]");var a2=card.querySelector("select[name=\\"games["+k+"][away_player2_post_id]\\"]");if(isD){if(h){h.value="";}if(a){a.value="";}if(h2){h2.value="";}if(a2){a2.value="";}return;}if(h){h.value=pick("home",String(row.home||""));}if(a){a.value=pick("away",String(row.away||""));}if(h2){h2.value="";}if(a2){a2.value="";}});});})();</script>';
        $out .= '<button type="submit" class="opentt-auth-btn">Sačuvaj partije</button>';
        $out .= '</form></section>';
        return $out;
    }

    public static function applyFrontGamesBatchForMatch($match, array $postedGames, &$error = '', $deps = [], array $lineup = [])
    {
        $tableExists = $deps['tableExists'];

        global $wpdb;
        $error = '';
        if (!is_object($match) || empty($match->id)) {
            $error = 'Utakmica nije pronađena.';
            return false;
        }

        $matchId = intval($match->id);
        $gamesTable = \OpenTT_Unified_Core::db_table('games');
        $setsTable = \OpenTT_Unified_Core::db_table('sets');
        if (!$tableExists($gamesTable) || !$tableExists($setsTable)) {
            $error = 'Tabela partija/setova nije dostupna.';
            return false;
        }

        $maxGames = max(0, min(7, intval($match->home_score ?? 0) + intval($match->away_score ?? 0)));
        if ($maxGames <= 0) {
            $maxGames = 7;
        }
        $matchFormat = self::matchFormatByCompetition((string) ($match->liga_slug ?? ''), (string) ($match->sezona_slug ?? ''));
        $expectedDoublesOrder = $matchFormat === 'format_b' ? 7 : 4;

        $existingRows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$gamesTable} WHERE match_id=%d", $matchId)) ?: [];
        $existingByOrder = [];
        foreach ($existingRows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $existingByOrder[intval($row->order_no ?? 0)] = $row;
        }

        for ($orderNo = 1; $orderNo <= $maxGames; $orderNo++) {
            $raw = isset($postedGames[$orderNo]) && is_array($postedGames[$orderNo]) ? $postedGames[$orderNo] : [];
            $hp = intval($raw['home_player_post_id'] ?? 0);
            $ap = intval($raw['away_player_post_id'] ?? 0);
            $hp2 = intval($raw['home_player2_post_id'] ?? 0);
            $ap2 = intval($raw['away_player2_post_id'] ?? 0);
            $hs = max(0, intval($raw['home_sets'] ?? 0));
            $as = max(0, intval($raw['away_sets'] ?? 0));
            $isDoubles = isset($raw['is_doubles']) ? 1 : (($orderNo === $expectedDoublesOrder) ? 1 : 0);
            if ($isDoubles !== 1 && ($hp <= 0 || $ap <= 0)) {
                $generated = self::resolveGeneratedSinglesPlayers($orderNo, $matchFormat, $lineup);
                if ($hp <= 0) {
                    $hp = intval($generated[0] ?? 0);
                }
                if ($ap <= 0) {
                    $ap = intval($generated[1] ?? 0);
                }
            }

            $setsRaw = isset($raw['sets']) && is_array($raw['sets']) ? $raw['sets'] : [];
            $setRows = [];
            $winsHome = 0;
            $winsAway = 0;
            for ($setNo = 1; $setNo <= 5; $setNo++) {
                $setIn = isset($setsRaw[$setNo]) && is_array($setsRaw[$setNo]) ? $setsRaw[$setNo] : [];
                $spHome = max(0, intval($setIn['home_points'] ?? 0));
                $spAway = max(0, intval($setIn['away_points'] ?? 0));
                if ($spHome <= 0 && $spAway <= 0) {
                    continue;
                }
                $setRows[] = ['set_no' => $setNo, 'home_points' => $spHome, 'away_points' => $spAway];
                if ($spHome > $spAway) {
                    $winsHome++;
                } elseif ($spAway > $spHome) {
                    $winsAway++;
                }
            }

            $hasAny = ($hp > 0 || $ap > 0 || $hp2 > 0 || $ap2 > 0 || $hs > 0 || $as > 0 || !empty($setRows));
            $existing = isset($existingByOrder[$orderNo]) ? $existingByOrder[$orderNo] : null;
            if (!$hasAny) {
                if ($existing) {
                    $wpdb->delete($setsTable, ['game_id' => intval($existing->id ?? 0)]);
                    $wpdb->delete($gamesTable, ['id' => intval($existing->id ?? 0)]);
                }
                continue;
            }
            if ($hp <= 0 || $ap <= 0) {
                $error = 'Partija #' . intval($orderNo) . ': izaberi oba glavna igrača.';
                return false;
            }
            if ($isDoubles === 1 && ($hp2 <= 0 || $ap2 <= 0 || $hp === $hp2 || $ap === $ap2)) {
                $error = 'Partija #' . intval($orderNo) . ': dubl nije validan (proveri igrače 2).';
                return false;
            }
            if ($isDoubles !== 1) {
                $hp2 = 0;
                $ap2 = 0;
            }
            if (($hs + $as) === 0 && !empty($setRows)) {
                $hs = $winsHome;
                $as = $winsAway;
            }

            $data = [
                'match_id' => $matchId,
                'order_no' => $orderNo,
                'slug' => 'partija-' . $orderNo,
                'is_doubles' => $isDoubles,
                'home_player_post_id' => $hp,
                'away_player_post_id' => $ap,
                'home_player2_post_id' => $isDoubles ? ($hp2 ?: null) : null,
                'away_player2_post_id' => $isDoubles ? ($ap2 ?: null) : null,
                'home_sets' => $hs,
                'away_sets' => $as,
                'updated_at' => current_time('mysql'),
            ];

            $gameId = 0;
            if ($existing) {
                $gameId = intval($existing->id ?? 0);
                $ok = $wpdb->update($gamesTable, $data, ['id' => $gameId]);
                if ($ok === false) {
                    $error = 'Greška pri čuvanju partije #' . intval($orderNo) . '.';
                    return false;
                }
            } else {
                $data['created_at'] = current_time('mysql');
                $ok = $wpdb->insert($gamesTable, $data);
                if ($ok === false) {
                    $error = 'Greška pri dodavanju partije #' . intval($orderNo) . '.';
                    return false;
                }
                $gameId = intval($wpdb->insert_id);
            }

            $wpdb->delete($setsTable, ['game_id' => $gameId]);
            foreach ($setRows as $sr) {
                $wpdb->insert($setsTable, [
                    'game_id' => $gameId,
                    'set_no' => intval($sr['set_no']),
                    'home_points' => intval($sr['home_points']),
                    'away_points' => intval($sr['away_points']),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
            }
        }

        $extraIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$gamesTable} WHERE match_id=%d AND order_no>%d", $matchId, $maxGames)) ?: [];
        foreach ($extraIds as $gid) {
            $wpdb->delete($setsTable, ['game_id' => intval($gid)]);
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$gamesTable} WHERE match_id=%d AND order_no>%d", $matchId, $maxGames));
        return true;
    }
}
