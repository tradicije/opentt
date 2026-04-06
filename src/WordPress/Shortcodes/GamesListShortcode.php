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

final class GamesListShortcode
{
    private static function renderPlayerSelect($name, array $options, $selected = 0)
    {
        $selected = intval($selected);
        $html = '<select name="' . esc_attr((string) $name) . '">';
        $html .= '<option value="">— izaberi —</option>';
        foreach ($options as $player_id => $player_name) {
            $player_id = intval($player_id);
            if ($player_id <= 0) {
                continue;
            }
            $html .= '<option value="' . esc_attr((string) $player_id) . '"' . selected($selected, $player_id, false) . '>' . esc_html((string) $player_name) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private static function renderFrontendSubmissionForm($matchRow, array $deps)
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $homeClubId = intval($matchRow->home_club_post_id ?? 0);
        $awayClubId = intval($matchRow->away_club_post_id ?? 0);
        $homePlayers = $call('players_for_club_options', $homeClubId);
        $awayPlayers = $call('players_for_club_options', $awayClubId);
        $homePlayers = is_array($homePlayers) ? $homePlayers : [];
        $awayPlayers = is_array($awayPlayers) ? $awayPlayers : [];

        $maxGames = max(0, min(7, intval($matchRow->home_score ?? 0) + intval($matchRow->away_score ?? 0)));
        if ($maxGames <= 0) {
            $maxGames = 7;
        }

        $format = 'format_a';
        $rule = $call('get_competition_rule_data', (string) ($matchRow->liga_slug ?? ''), (string) ($matchRow->sezona_slug ?? ''));
        if (is_array($rule) && !empty($rule['format_partija'])) {
            $candidate = (string) $rule['format_partija'];
            if (in_array($candidate, ['format_a', 'format_b'], true)) {
                $format = $candidate;
            }
        }
        $expectedDoublesOrder = ($format === 'format_b') ? 7 : 4;

        $currentUrl = '';
        if (!empty($_SERVER['REQUEST_URI'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $currentUrl = home_url((string) wp_unslash($_SERVER['REQUEST_URI'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if ($currentUrl === '') {
            $currentUrl = home_url('/');
        }
        $redirectTo = remove_query_arg(['opentt_games_submit', 'opentt_games_pending'], $currentUrl);

        ob_start();
        echo '<div class="opentt-games-submit-wrap">';
        echo '<h3>Predlog unosa partija</h3>';
        echo '<p class="opentt-games-submit-lead">Unesi partije za ovu utakmicu i pošalji predlog administratoru na pregled.</p>';
        if (empty($homePlayers) || empty($awayPlayers)) {
            echo '<p class="opentt-games-submit-warning">Nema dovoljno igrača povezanih sa klubovima ove utakmice za kvalitetan unos partija.</p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-games-submit-form">';
        wp_nonce_field('opentt_unified_submit_games_pending');
        echo '<input type="hidden" name="action" value="opentt_unified_submit_games_pending">';
        echo '<input type="hidden" name="match_id" value="' . intval($matchRow->id ?? 0) . '">';
        echo '<input type="hidden" name="redirect_to" value="' . esc_attr((string) $redirectTo) . '">';

        echo '<label class="opentt-games-submit-email">';
        echo '<span>Email adresa <strong>*</strong></span>';
        echo '<input type="email" name="submitter_email" required placeholder="ime@domen.rs">';
        echo '<small>Na ovu adresu dobićeš obaveštenje da li je tvoj unos partija odobren ili odbijen od strane administratora.</small>';
        echo '</label>';

        for ($orderNo = 1; $orderNo <= $maxGames; $orderNo++) {
            $isDoubles = ($orderNo === $expectedDoublesOrder);
            echo '<div class="opentt-games-submit-game">';
            echo '<h4>Partija #' . intval($orderNo) . ($isDoubles ? ' (Dubl)' : '') . '</h4>';
            echo '<div class="opentt-games-submit-grid">';
            echo '<label><span>Domaći igrač</span>' . self::renderPlayerSelect('games[' . intval($orderNo) . '][home_player_post_id]', $homePlayers, 0) . '</label>';
            echo '<label><span>Gost igrač</span>' . self::renderPlayerSelect('games[' . intval($orderNo) . '][away_player_post_id]', $awayPlayers, 0) . '</label>';
            echo '<label><span>Domaći setovi</span><input type="number" min="0" max="7" name="games[' . intval($orderNo) . '][home_sets]" value="0"></label>';
            echo '<label><span>Gost setovi</span><input type="number" min="0" max="7" name="games[' . intval($orderNo) . '][away_sets]" value="0"></label>';
            if ($isDoubles) {
                echo '<label><span>Domaći igrač 2</span>' . self::renderPlayerSelect('games[' . intval($orderNo) . '][home_player2_post_id]', $homePlayers, 0) . '</label>';
                echo '<label><span>Gost igrač 2</span>' . self::renderPlayerSelect('games[' . intval($orderNo) . '][away_player2_post_id]', $awayPlayers, 0) . '</label>';
            }
            echo '</div>';

            echo '<div class="opentt-games-submit-sets">';
            for ($setNo = 1; $setNo <= 5; $setNo++) {
                echo '<label><span>Set ' . intval($setNo) . ' (D:G)</span>';
                echo '<span class="opentt-games-submit-set-pair">';
                echo '<input type="number" min="0" max="30" name="games[' . intval($orderNo) . '][sets][' . intval($setNo) . '][home_points]" value="0" placeholder="11">';
                echo '<span>:</span>';
                echo '<input type="number" min="0" max="30" name="games[' . intval($orderNo) . '][sets][' . intval($setNo) . '][away_points]" value="0" placeholder="9">';
                echo '</span>';
                echo '</label>';
            }
            echo '</div>';
            echo '</div>';
        }

        echo '<button type="submit" class="opentt-games-submit-btn">Pošalji na pregled</button>';
        echo '</form>';
        echo '</div>';

        return ob_get_clean();
    }

    public static function render($atts = [], array $deps = [])
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $ctx = $call('current_match_context');
        if (!is_array($ctx) || empty($ctx['db_row'])) {
            return '';
        }

        $match_row = $ctx['db_row'];
        $legacy_match_id = intval($ctx['legacy_id'] ?? 0);
        $is_submit_mode = isset($_GET['opentt_games_submit']) && (string) $_GET['opentt_games_submit'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $pending_state = isset($_GET['opentt_games_pending']) ? sanitize_key((string) $_GET['opentt_games_pending']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $games = $call('db_get_games_for_match_id', intval($match_row->id));
        $games = is_array($games) ? $games : [];
        $open_form_url = '';
        if (!empty($_SERVER['REQUEST_URI'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $open_form_url = add_query_arg(
                ['opentt_games_submit' => '1'],
                remove_query_arg(['opentt_games_pending'], home_url((string) wp_unslash($_SERVER['REQUEST_URI']))) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            );
        }
        if ($open_form_url === '') {
            $open_form_url = add_query_arg('opentt_games_submit', '1', home_url('/'));
        }

        $pending_notice_html = '';
        if ($pending_state === 'submitted') {
            $pending_notice_html = '<p class="opentt-games-submit-success">Uspešno! Tvoj unos partija je poslat administratoru na pregled i trenutno je na čekanju. Na email adresu koju si ostavio/la dobićeš potvrdu da li je unos odobren ili odbijen.</p>';
        } elseif ($pending_state === 'empty') {
            $pending_notice_html = '<p class="opentt-games-submit-error">Unos nije poslat jer nijedna partija nije popunjena.</p>';
        } elseif ($pending_state === 'error') {
            $pending_notice_html = '<p class="opentt-games-submit-error">Došlo je do greške pri slanju unosa. Pokušaj ponovo.</p>';
        }

        if (empty($games)) {
            $out = (string) $call('shortcode_title_html', 'Tok utakmice');
            $out .= $pending_notice_html;
            $out .= '<p>Nema partija za prikaz.</p>';
            $out .= '<p><a class="opentt-games-submit-open" href="' . esc_url($open_form_url) . '" target="_blank" rel="noopener">Unesi partije</a></p>';
            if ($is_submit_mode) {
                $out .= self::renderFrontendSubmissionForm($match_row, $deps);
            }
            return $out;
        }

        $format = 'format_a';
        $rule = $call('get_competition_rule_data', (string) $match_row->liga_slug, (string) $match_row->sezona_slug);
        if (is_array($rule) && !empty($rule['format_partija']) && in_array((string) $rule['format_partija'], ['format_a', 'format_b'], true)) {
            $format = (string) $rule['format_partija'];
        } else {
            $sistem = strtolower(trim((string) get_post_meta($legacy_match_id, 'sistem', true)));
            if ($sistem === 'stari') {
                $format = 'format_b';
            }
        }

        $mapa_novi = [
            1 => '1. partija (A vs Y)',
            2 => '2. partija (B vs X)',
            3 => '3. partija (C vs Z)',
            4 => '4. partija (Dubl)',
            5 => '5. partija (A vs X)',
            6 => '6. partija (C vs Y)',
            7 => '7. partija (B vs Z)',
        ];
        $mapa_stari = [
            1 => '1. partija (A vs Y)',
            2 => '2. partija (B vs X)',
            3 => '3. partija (C vs Z)',
            4 => '4. partija (A vs X)',
            5 => '5. partija (C vs Y)',
            6 => '6. partija (B vs Z)',
            7 => '7. partija (Dubl)',
        ];
        $mapa_partija = $format === 'format_b' ? $mapa_stari : $mapa_novi;

        ob_start();
        echo (string) $call('shortcode_title_html', 'Tok utakmice');
        echo $pending_notice_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="lp2-lista">';

        foreach ($games as $g) {
            $redni_broj = intval($g->order_no);
            if ($redni_broj <= 0) {
                $redni_broj = 0;
            }

            if (isset($mapa_partija[$redni_broj])) {
                echo '<div class="lp2-naziv-partije">' . esc_html($mapa_partija[$redni_broj]) . '</div>';
            } else {
                echo '<div class="lp2-naziv-partije">Partija ' . intval($redni_broj) . '</div>';
            }

            $home_players = [];
            $away_players = [];
            foreach ([intval($g->home_player_post_id), intval($g->home_player2_post_id)] as $pid) {
                if ($pid > 0) {
                    $home_players[] = $pid;
                }
            }
            foreach ([intval($g->away_player_post_id), intval($g->away_player2_post_id)] as $pid) {
                if ($pid > 0) {
                    $away_players[] = $pid;
                }
            }

            $sets_dom = intval($g->home_sets);
            $sets_gos = intval($g->away_sets);
            $pob_dom = ($sets_dom > $sets_gos);
            $pob_gos = ($sets_gos > $sets_dom);
            $set_rows = $call('db_get_sets_for_game_id', intval($g->id));
            $set_rows = is_array($set_rows) ? $set_rows : [];

            echo '<div class="lp2-partija">';

            echo '<div class="lp2-item ' . esc_attr($pob_dom ? 'lp2-win' : 'lp2-lose') . '">';
            echo '<div class="lp2-team">';
            foreach ($home_players as $pid) {
                echo (string) $call('render_lp2_player', $pid);
            }
            echo '</div>';
            echo '<div class="lp2-sets">';
            foreach ($set_rows as $set_row) {
                $pdom = intval($set_row->home_points);
                $pgos = intval($set_row->away_points);
                if ($pdom === 0 && $pgos === 0) {
                    continue;
                }
                $class = ($pdom > $pgos) ? 'lp2-win' : 'lp2-lose';
                echo '<div class="lp2-set ' . esc_attr($class) . '">' . intval($pdom) . '</div>';
            }
            echo '<div class="lp2-ukupno">' . intval($sets_dom) . '</div>';
            echo '</div>';
            echo '</div>';

            echo '<div class="lp2-item ' . esc_attr($pob_gos ? 'lp2-win' : 'lp2-lose') . '">';
            echo '<div class="lp2-team">';
            foreach ($away_players as $pid) {
                echo (string) $call('render_lp2_player', $pid);
            }
            echo '</div>';
            echo '<div class="lp2-sets">';
            foreach ($set_rows as $set_row) {
                $pdom = intval($set_row->home_points);
                $pgos = intval($set_row->away_points);
                if ($pdom === 0 && $pgos === 0) {
                    continue;
                }
                $class = ($pgos > $pdom) ? 'lp2-win' : 'lp2-lose';
                echo '<div class="lp2-set ' . esc_attr($class) . '">' . intval($pgos) . '</div>';
            }
            echo '<div class="lp2-ukupno">' . intval($sets_gos) . '</div>';
            echo '</div>';
            echo '</div>';

            echo '</div>';
        }

        echo '</div>';
        return ob_get_clean();
    }
}
