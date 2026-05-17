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

final class UserPortalLeagueAdminViewService
{
    public static function renderLeagueAdminTools($userId, $deps = [])
    {
        $getUserManagedLeagues = $deps['getUserManagedLeagues'];
        $getUserManagedLeagueSeasons = $deps['getUserManagedLeagueSeasons'];
        $tableExists = $deps['tableExists'];
        $slugToTitle = $deps['slugToTitle'];
        $koloNameFromSlug = $deps['koloNameFromSlug'];
        $collectLeagueClubIds = $deps['collectLeagueClubIds'];
        $renderLeagueMatchGamesForm = $deps['renderLeagueMatchGamesForm'];

        $isSuper = user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP);
        $managedLeagues = $getUserManagedLeagues($userId);
        $managedPairs = $getUserManagedLeagueSeasons($userId);

        if (!$isSuper && empty($managedLeagues) && empty($managedPairs)) {
            return '<section class="opentt-profile-section"><h3>Alati administratora lige</h3><p>Nema dodeljenih liga.</p></section>';
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!$tableExists($matchesTable)) {
            return '<section class="opentt-profile-section"><h3>Alati administratora lige</h3><p>Tabela utakmica nije dostupna.</p></section>';
        }

        $distinctRows = $wpdb->get_results("SELECT DISTINCT liga_slug, sezona_slug FROM {$matchesTable} WHERE liga_slug <> '' AND sezona_slug <> '' ORDER BY sezona_slug DESC, liga_slug ASC") ?: [];
        $allowedPairMap = array_fill_keys($managedPairs, true);
        $allowedLeagueMap = array_fill_keys($managedLeagues, true);

        $seasonLeagueMap = [];
        foreach ($distinctRows as $dr) {
            if (!is_object($dr)) {
                continue;
            }
            $leagueSlug = sanitize_title((string) ($dr->liga_slug ?? ''));
            $seasonSlug = sanitize_title((string) ($dr->sezona_slug ?? ''));
            if ($leagueSlug === '' || $seasonSlug === '') {
                continue;
            }
            $pair = $leagueSlug . '|' . $seasonSlug;
            if (!$isSuper && !isset($allowedPairMap[$pair]) && !isset($allowedLeagueMap[$leagueSlug])) {
                continue;
            }
            if (!isset($seasonLeagueMap[$seasonSlug])) {
                $seasonLeagueMap[$seasonSlug] = [];
            }
            $seasonLeagueMap[$seasonSlug][$leagueSlug] = true;
        }

        if (empty($seasonLeagueMap)) {
            return '<section class="opentt-profile-section"><h3>Alati administratora lige</h3><p>Nema liga/sezona za upravljanje.</p></section>';
        }

        krsort($seasonLeagueMap, SORT_NATURAL);
        foreach ($seasonLeagueMap as $seasonSlug => $leagues) {
            ksort($leagues, SORT_NATURAL);
            $seasonLeagueMap[$seasonSlug] = array_keys($leagues);
        }

        $out = '<section class="opentt-profile-section" id="opentt-profile-league-admin"><h3>Alati administratora lige</h3>';
        $out .= '<div class="opentt-league-tabs" data-opentt-league-admin="1">';

        $out .= '<div class="opentt-league-tab-head opentt-season-tab-head">';
        $seasonIdx = 0;
        foreach ($seasonLeagueMap as $seasonSlug => $leagues) {
            unset($leagues);
            $active = $seasonIdx === 0 ? ' is-active' : '';
            $out .= '<button type="button" class="opentt-league-tab-btn opentt-season-tab-btn' . esc_attr($active) . '" data-season-tab="' . esc_attr($seasonSlug) . '">' . esc_html(str_replace('-', '/', $seasonSlug)) . '</button>';
            $seasonIdx++;
        }
        $out .= '</div>';

        $seasonIdx = 0;
        foreach ($seasonLeagueMap as $seasonSlug => $leagues) {
            $seasonActive = $seasonIdx === 0 ? ' is-active' : '';
            $out .= '<div class="opentt-league-tab-pane opentt-season-pane' . esc_attr($seasonActive) . '" data-season-pane="' . esc_attr($seasonSlug) . '">';

            $out .= '<div class="opentt-league-tab-head opentt-league-subtab-head">';
            foreach ($leagues as $leagueIdx => $leagueSlug) {
                $leagueActive = $leagueIdx === 0 ? ' is-active' : '';
                $out .= '<button type="button" class="opentt-league-tab-btn opentt-league-subtab-btn' . esc_attr($leagueActive) . '" data-league-tab="' . esc_attr($seasonSlug . '|' . $leagueSlug) . '">' . esc_html($slugToTitle($leagueSlug)) . '</button>';
            }
            $out .= '</div>';

            foreach ($leagues as $leagueIdx => $leagueSlug) {
                $leaguePaneActive = $leagueIdx === 0 ? ' is-active' : '';
                $paneId = $seasonSlug . '|' . $leagueSlug;
                $out .= '<div class="opentt-league-tab-pane opentt-league-subpane' . esc_attr($leaguePaneActive) . '" data-league-pane="' . esc_attr($paneId) . '">';

                $clubsInLeague = $collectLeagueClubIds($leagueSlug);

                $out .= '<section class="opentt-profile-subsection"><h4>Dodaj novu utakmicu</h4>';
                $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
                $out .= wp_nonce_field('opentt_front_add_league_match_' . $leagueSlug, '_wpnonce', true, false);
                $out .= '<input type="hidden" name="action" value="opentt_front_add_league_match">';
                $out .= '<input type="hidden" name="liga_slug" value="' . esc_attr($leagueSlug) . '">';
                $out .= '<label>Sezona (slug)<input type="text" name="sezona_slug" value="' . esc_attr($seasonSlug) . '" required></label>';
                $out .= '<label>Kolo (slug, npr. 11-kolo)<input type="text" name="kolo_slug" required></label>';
                $out .= '<div class="opentt-inline-select-grid">';
                $out .= '<label>Domaćin<select name="home_club_post_id" required><option value="">- izaberi -</option>';
                foreach ($clubsInLeague as $cid) {
                    $out .= '<option value="' . esc_attr((string) $cid) . '">' . esc_html((string) get_the_title($cid)) . '</option>';
                }
                $out .= '</select></label>';
                $out .= '<label>Gost<select name="away_club_post_id" required><option value="">- izaberi -</option>';
                foreach ($clubsInLeague as $cid) {
                    $out .= '<option value="' . esc_attr((string) $cid) . '">' . esc_html((string) get_the_title($cid)) . '</option>';
                }
                $out .= '</select></label>';
                $out .= '</div>';
                $out .= '<label>Datum i vreme (YYYY-MM-DD HH:MM:SS)<input type="text" name="match_date" placeholder="2026-04-07 19:00:00"></label>';
                $out .= '<label>Lokacija<input type="text" name="location"></label>';
                $out .= '<button type="submit" class="opentt-auth-btn">Dodaj utakmicu</button>';
                $out .= '</form></section>';

                $matches = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$matchesTable} WHERE liga_slug=%s AND sezona_slug=%s ORDER BY kolo_slug DESC, match_date DESC, id DESC LIMIT 300",
                    $leagueSlug,
                    $seasonSlug
                )) ?: [];

                $out .= '<section class="opentt-profile-subsection"><h4>Utakmice lige</h4>';
                $out .= '<div class="opentt-league-admin-controls">';
                $out .= '<label>Pretraga<input type="search" class="opentt-league-admin-search" placeholder="Pretraži ekipe, kolo..."></label>';
                $out .= '<label>Status<select class="opentt-league-admin-filter-played"><option value="all">Sve</option><option value="played">Odigrane</option><option value="upcoming">Neodigrane</option></select></label>';
                $out .= '<label>Sort<select class="opentt-league-admin-sort"><option value="date_desc">Datum (novije)</option><option value="date_asc">Datum (starije)</option><option value="kolo_desc">Kolo (veće)</option><option value="kolo_asc">Kolo (manje)</option><option value="home_asc">Domaćin A-Z</option></select></label>';
                $out .= '</div>';

                if (empty($matches)) {
                    $out .= '<p>Nema utakmica za ovu ligu/sezonu.</p>';
                } else {
                    $out .= '<div class="opentt-league-admin-grid" data-opentt-admin-pane="1">';
                    foreach ($matches as $row) {
                        if (!is_object($row)) {
                            continue;
                        }
                        $matchId = intval($row->id ?? 0);
                        if ($matchId <= 0) {
                            continue;
                        }
                        $homeId = intval($row->home_club_post_id ?? 0);
                        $awayId = intval($row->away_club_post_id ?? 0);
                        $homeName = trim((string) get_the_title($homeId));
                        $awayName = trim((string) get_the_title($awayId));
                        $homeLogo = (string) get_the_post_thumbnail_url($homeId, 'thumbnail');
                        $awayLogo = (string) get_the_post_thumbnail_url($awayId, 'thumbnail');
                        $played = intval($row->played ?? 0) === 1;
                        $maxGames = max(0, min(7, intval($row->home_score ?? 0) + intval($row->away_score ?? 0)));
                        if ($maxGames <= 0) {
                            $maxGames = 7;
                        }
                        $searchBlob = strtolower(trim($homeName . ' ' . $awayName . ' ' . (string) ($row->kolo_slug ?? '')));
                        $koloNo = 0;
                        if (preg_match('/(\d+)/', (string) ($row->kolo_slug ?? ''), $m)) {
                            $koloNo = intval($m[1]);
                        }

                        $out .= '<details class="opentt-league-match-card" data-search="' . esc_attr($searchBlob) . '" data-played="' . ($played ? '1' : '0') . '" data-kolo="' . esc_attr((string) $koloNo) . '" data-date="' . esc_attr((string) ($row->match_date ?? '')) . '" data-home="' . esc_attr(strtolower($homeName)) . '">';
                        $out .= '<summary>';
                        $out .= '<span class="opentt-lm-top">' . esc_html($koloNameFromSlug((string) ($row->kolo_slug ?? ''))) . '</span>';
                        $out .= '<span class="opentt-lm-main">';
                        $out .= '<span class="opentt-lm-team">';
                        if ($homeLogo !== '') {
                            $out .= '<img src="' . esc_url($homeLogo) . '" alt="" loading="lazy">';
                        }
                        $out .= '<span>' . esc_html($homeName) . '</span>';
                        $out .= '</span>';
                        $out .= '<strong class="opentt-lm-score">' . intval($row->home_score ?? 0) . ':' . intval($row->away_score ?? 0) . '</strong>';
                        $out .= '<span class="opentt-lm-team">';
                        if ($awayLogo !== '') {
                            $out .= '<img src="' . esc_url($awayLogo) . '" alt="" loading="lazy">';
                        }
                        $out .= '<span>' . esc_html($awayName) . '</span>';
                        $out .= '</span>';
                        $out .= '</span>';
                        $out .= '<span class="opentt-lm-date">' . esc_html((string) ($row->match_date ?? '')) . '</span>';
                        $out .= '</summary>';

                        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
                        $out .= wp_nonce_field('opentt_front_save_league_match_' . $matchId, '_wpnonce', true, false);
                        $out .= '<input type="hidden" name="action" value="opentt_front_save_league_match">';
                        $out .= '<input type="hidden" name="match_id" value="' . esc_attr((string) $matchId) . '">';
                        $out .= '<div class="opentt-inline-select-grid">';
                        $out .= '<label>Sezona (slug)<input type="text" name="sezona_slug" value="' . esc_attr((string) ($row->sezona_slug ?? '')) . '" required></label>';
                        $out .= '<label>Kolo (slug)<input type="text" name="kolo_slug" value="' . esc_attr((string) ($row->kolo_slug ?? '')) . '" required></label>';
                        $out .= '</div>';
                        $out .= '<div class="opentt-inline-select-grid">';
                        $out .= '<label>Domaćin<select name="home_club_post_id" required>';
                        foreach ($clubsInLeague as $cid) {
                            $out .= '<option value="' . esc_attr((string) $cid) . '"' . selected($homeId, intval($cid), false) . '>' . esc_html((string) get_the_title($cid)) . '</option>';
                        }
                        $out .= '</select></label>';
                        $out .= '<label>Gost<select name="away_club_post_id" required>';
                        foreach ($clubsInLeague as $cid) {
                            $out .= '<option value="' . esc_attr((string) $cid) . '"' . selected($awayId, intval($cid), false) . '>' . esc_html((string) get_the_title($cid)) . '</option>';
                        }
                        $out .= '</select></label>';
                        $out .= '</div>';
                        $out .= '<div class="opentt-inline-select-grid">';
                        $out .= '<label>Domaći rezultat<input type="number" min="0" max="9" name="home_score" value="' . esc_attr((string) intval($row->home_score ?? 0)) . '"></label>';
                        $out .= '<label>Gostujući rezultat<input type="number" min="0" max="9" name="away_score" value="' . esc_attr((string) intval($row->away_score ?? 0)) . '"></label>';
                        $out .= '</div>';
                        $out .= '<label>Datum i vreme<input type="text" name="match_date" value="' . esc_attr((string) ($row->match_date ?? '')) . '"></label>';
                        $out .= '<label>Lokacija<input type="text" name="location" value="' . esc_attr((string) ($row->location ?? '')) . '"></label>';
                        $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="played" value="1"' . checked($played, true, false) . '> Odigrana</label>';
                        $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="live" value="1"' . checked(intval($row->live ?? 0), 1, false) . '> Uživo</label>';
                        $out .= '<div class="opentt-editor-media-row"><button type="submit" class="opentt-auth-btn">Sačuvaj izmene</button></div>';
                        $out .= '</form>';
                        $out .= $renderLeagueMatchGamesForm($matchId, $homeId, $awayId, $maxGames);
                        $out .= '</details>';
                    }
                    $out .= '</div>';
                }

                $out .= '</section>';
                $out .= '</div>';
            }

            $out .= '</div>';
            $seasonIdx++;
        }

        $out .= '</div>';
        $out .= "<script>(function(){var roots=document.querySelectorAll('[data-opentt-league-admin=\"1\"]');roots.forEach(function(root){if(root.dataset.bound==='1'){return;}root.dataset.bound='1';var seasonBtns=root.querySelectorAll('.opentt-season-tab-btn');var seasonPanes=root.querySelectorAll('.opentt-season-pane');seasonBtns.forEach(function(btn){btn.addEventListener('click',function(){var key=String(btn.getAttribute('data-season-tab')||'');seasonBtns.forEach(function(b){b.classList.toggle('is-active',b===btn);});seasonPanes.forEach(function(p){p.classList.toggle('is-active',String(p.getAttribute('data-season-pane')||'')===key);});});});root.querySelectorAll('.opentt-season-pane').forEach(function(seasonPane){var leagueBtns=seasonPane.querySelectorAll('.opentt-league-subtab-btn');var leaguePanes=seasonPane.querySelectorAll('.opentt-league-subpane');leagueBtns.forEach(function(btn){btn.addEventListener('click',function(){var key=String(btn.getAttribute('data-league-tab')||'');leagueBtns.forEach(function(b){b.classList.toggle('is-active',b===btn);});leaguePanes.forEach(function(p){p.classList.toggle('is-active',String(p.getAttribute('data-league-pane')||'')===key);});});});});root.querySelectorAll('.opentt-league-subpane').forEach(function(pane){var search=pane.querySelector('.opentt-league-admin-search');var played=pane.querySelector('.opentt-league-admin-filter-played');var sort=pane.querySelector('.opentt-league-admin-sort');var grid=pane.querySelector('[data-opentt-admin-pane=\"1\"]');if(!grid){return;}function parseDate(v){var s=String(v||'').trim();if(!s){return 0;}var d=Date.parse(s.replace(' ','T'));return isNaN(d)?0:d;}function apply(){var cards=Array.prototype.slice.call(grid.querySelectorAll('.opentt-league-match-card'));var q=search?String(search.value||'').toLowerCase().trim():'';var pv=played?String(played.value||'all'):'all';cards.forEach(function(card){var text=String(card.getAttribute('data-search')||'');var isPlayed=String(card.getAttribute('data-played')||'0')==='1';var okQ=!q||text.indexOf(q)!==-1;var okP=(pv==='all')||(pv==='played'&&isPlayed)||(pv==='upcoming'&&!isPlayed);card.style.display=(okQ&&okP)?'':'none';});var visible=cards.filter(function(card){return card.style.display!=='none';});var mode=sort?String(sort.value||'date_desc'):'date_desc';visible.sort(function(a,b){if(mode==='date_asc'){return parseDate(a.getAttribute('data-date'))-parseDate(b.getAttribute('data-date'));}if(mode==='kolo_desc'){return (parseInt(b.getAttribute('data-kolo')||'0',10)-parseInt(a.getAttribute('data-kolo')||'0',10));}if(mode==='kolo_asc'){return (parseInt(a.getAttribute('data-kolo')||'0',10)-parseInt(b.getAttribute('data-kolo')||'0',10));}if(mode==='home_asc'){return String(a.getAttribute('data-home')||'').localeCompare(String(b.getAttribute('data-home')||''));}return parseDate(b.getAttribute('data-date'))-parseDate(a.getAttribute('data-date'));});visible.forEach(function(card){grid.appendChild(card);});}if(search){search.addEventListener('input',apply);}if(played){played.addEventListener('change',apply);}if(sort){sort.addEventListener('change',apply);}apply();});});})();</script>";
        $out .= '</section>';

        return $out;
    }
}
