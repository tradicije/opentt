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

final class UserPortalLeagueMatchService
{
    public static function handleFrontSaveLeagueMatch($deps = [])
    {
        $frontendNoticeUrl = $deps['frontendNoticeUrl'];
        $tableExists = $deps['tableExists'];
        $canManageLeague = $deps['canManageLeague'];
        $normalizeMatchDate = $deps['normalizeMatchDate'];

        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $matchId = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
        if ($matchId <= 0) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nedostaje ID utakmice.'));
            exit;
        }
        check_admin_referer('opentt_front_save_league_match_' . $matchId);

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!$tableExists($matchesTable)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Tabela utakmica nije dostupna.'));
            exit;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matchesTable} WHERE id=%d LIMIT 1", $matchId));
        if (!$row || !is_object($row)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Utakmica nije pronađena.'));
            exit;
        }

        $userId = get_current_user_id();
        if (!$canManageLeague($userId, (string) ($row->liga_slug ?? ''), (string) ($row->sezona_slug ?? ''))) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za ovu ligu.'));
            exit;
        }

        $homeScore = max(0, intval($_POST['home_score'] ?? 0));
        $awayScore = max(0, intval($_POST['away_score'] ?? 0));
        $homeClubId = isset($_POST['home_club_post_id']) ? intval($_POST['home_club_post_id']) : intval($row->home_club_post_id ?? 0);
        $awayClubId = isset($_POST['away_club_post_id']) ? intval($_POST['away_club_post_id']) : intval($row->away_club_post_id ?? 0);
        if ($homeClubId <= 0 || $awayClubId <= 0 || $homeClubId === $awayClubId) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Proveri izbor domaćina i gosta.'));
            exit;
        }

        $sezonaSlug = isset($_POST['sezona_slug']) ? sanitize_title((string) wp_unslash($_POST['sezona_slug'])) : sanitize_title((string) ($row->sezona_slug ?? ''));
        $koloSlug = isset($_POST['kolo_slug']) ? sanitize_title((string) wp_unslash($_POST['kolo_slug'])) : sanitize_title((string) ($row->kolo_slug ?? ''));
        if ($sezonaSlug === '' || $koloSlug === '') {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Sezona i kolo su obavezni.'));
            exit;
        }

        $location = isset($_POST['location']) ? sanitize_text_field((string) wp_unslash($_POST['location'])) : '';
        $matchDate = isset($_POST['match_date']) ? $normalizeMatchDate((string) wp_unslash($_POST['match_date'])) : (string) ($row->match_date ?? current_time('mysql'));
        $played = !empty($_POST['played']) ? 1 : 0;
        $live = !empty($_POST['live']) ? 1 : 0;

        $slug = sanitize_title(trim((string) get_the_title($homeClubId) . '-' . (string) get_the_title($awayClubId)));
        if ($slug === '') {
            $slug = 'utakmica-' . $matchId;
        }

        $ok = $wpdb->update($matchesTable, [
            'sezona_slug' => $sezonaSlug,
            'kolo_slug' => $koloSlug,
            'home_club_post_id' => $homeClubId,
            'away_club_post_id' => $awayClubId,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'played' => $played,
            'live' => $live,
            'match_date' => $matchDate,
            'location' => $location,
            'slug' => $slug,
            'updated_at' => current_time('mysql'),
        ], ['id' => $matchId]);

        if ($ok === false) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Čuvanje utakmice nije uspelo.'));
            exit;
        }

        wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'success', 'Utakmica je sačuvana.'));
        exit;
    }

    public static function handleFrontSaveLeagueGames($deps = [])
    {
        $frontendNoticeUrl = $deps['frontendNoticeUrl'];
        $tableExists = $deps['tableExists'];
        $canManageLeague = $deps['canManageLeague'];
        $applyFrontGamesBatchForMatch = $deps['applyFrontGamesBatchForMatch'];

        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $matchId = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
        if ($matchId <= 0) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nedostaje ID utakmice.'));
            exit;
        }
        check_admin_referer('opentt_front_save_league_games_' . $matchId);

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!$tableExists($matchesTable)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Tabela utakmica nije dostupna.'));
            exit;
        }

        $match = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matchesTable} WHERE id=%d LIMIT 1", $matchId));
        if (!$match || !is_object($match)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Utakmica nije pronađena.'));
            exit;
        }

        $userId = get_current_user_id();
        if (!$canManageLeague($userId, (string) ($match->liga_slug ?? ''), (string) ($match->sezona_slug ?? ''))) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za ovu ligu.'));
            exit;
        }

        $postedGames = isset($_POST['games']) && is_array($_POST['games']) ? $_POST['games'] : [];
        $error = '';
        if (!$applyFrontGamesBatchForMatch($match, $postedGames, $error)) {
            $msg = $error !== '' ? $error : 'Čuvanje partija nije uspelo.';
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', $msg));
            exit;
        }

        wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'success', 'Partije i setovi su sačuvani.'));
        exit;
    }

    public static function handleFrontAddLeagueMatch($deps = [])
    {
        $frontendNoticeUrl = $deps['frontendNoticeUrl'];
        $tableExists = $deps['tableExists'];
        $canManageLeague = $deps['canManageLeague'];
        $normalizeMatchDate = $deps['normalizeMatchDate'];

        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $ligaSlug = isset($_POST['liga_slug']) ? sanitize_title((string) wp_unslash($_POST['liga_slug'])) : '';
        check_admin_referer('opentt_front_add_league_match_' . $ligaSlug);

        $userId = get_current_user_id();
        $sezonaSlug = isset($_POST['sezona_slug']) ? sanitize_title((string) wp_unslash($_POST['sezona_slug'])) : '';
        if ($ligaSlug === '' || $sezonaSlug === '') {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Liga i sezona su obavezne.'));
            exit;
        }
        if (!$canManageLeague($userId, $ligaSlug, $sezonaSlug)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za ovu ligu.'));
            exit;
        }

        $koloSlug = isset($_POST['kolo_slug']) ? sanitize_title((string) wp_unslash($_POST['kolo_slug'])) : '';
        $homeClubId = isset($_POST['home_club_post_id']) ? intval($_POST['home_club_post_id']) : 0;
        $awayClubId = isset($_POST['away_club_post_id']) ? intval($_POST['away_club_post_id']) : 0;
        if ($koloSlug === '' || $homeClubId <= 0 || $awayClubId <= 0 || $homeClubId === $awayClubId) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Proveri kolo i izbor domaćina/gosta.'));
            exit;
        }

        $location = isset($_POST['location']) ? sanitize_text_field((string) wp_unslash($_POST['location'])) : '';
        $matchDate = isset($_POST['match_date']) ? $normalizeMatchDate((string) wp_unslash($_POST['match_date'])) : current_time('mysql');
        $slug = sanitize_title(trim((string) get_the_title($homeClubId) . '-' . (string) get_the_title($awayClubId)));
        if ($slug === '') {
            $slug = sanitize_title('utakmica-' . $koloSlug . '-' . $homeClubId . '-' . $awayClubId);
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!$tableExists($matchesTable)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Tabela utakmica nije dostupna.'));
            exit;
        }

        $ok = $wpdb->insert($matchesTable, [
            'liga_slug' => $ligaSlug,
            'sezona_slug' => $sezonaSlug,
            'kolo_slug' => $koloSlug,
            'slug' => $slug,
            'home_club_post_id' => $homeClubId,
            'away_club_post_id' => $awayClubId,
            'home_score' => 0,
            'away_score' => 0,
            'played' => 0,
            'match_date' => $matchDate,
            'location' => $location,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        if ($ok === false) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Dodavanje utakmice nije uspelo.'));
            exit;
        }

        wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'success', 'Nova utakmica je dodata.'));
        exit;
    }
}
