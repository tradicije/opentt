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

final class LeagueSeasonAdminManager
{
    public static function handleSaveLeague($capability)
    {
        self::requireCapability($capability);
        check_admin_referer('opentt_unified_save_league');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $title = sanitize_text_field((string) ($_POST['post_title'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($title === '') {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-add-league'), 'error', 'Naziv lige je obavezan.'));
            exit;
        }

        $postData = [
            'post_type' => 'liga',
            'post_title' => $title,
            'post_content' => wp_kses_post((string) ($_POST['post_content'] ?? '')), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            'post_status' => 'publish',
        ];

        if ($id > 0) {
            $postData['ID'] = $id;
            $leagueId = wp_update_post($postData, true);
        } else {
            $leagueId = wp_insert_post($postData, true);
        }

        if (!$leagueId || is_wp_error($leagueId)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-add-league'), 'error', 'Neuspešno čuvanje lige.'));
            exit;
        }

        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-leagues'), 'success', 'Liga je sačuvana.'));
        exit;
    }

    public static function handleDeleteLeague($capability)
    {
        self::requireCapability($capability);
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($id <= 0) {
            wp_die('Nedostaje ID.');
        }

        check_admin_referer('opentt_unified_delete_league_' . $id);
        wp_trash_post($id);
        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-leagues'), 'success', 'Liga je obrisana.'));
        exit;
    }

    public static function handleSaveSeason($capability)
    {
        self::requireCapability($capability);
        check_admin_referer('opentt_unified_save_season');

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $title = sanitize_text_field((string) ($_POST['post_title'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($title === '') {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-add-season'), 'error', 'Naziv sezone je obavezan.'));
            exit;
        }

        $postData = [
            'post_type' => 'sezona',
            'post_title' => $title,
            'post_status' => 'publish',
        ];

        if ($id > 0) {
            $postData['ID'] = $id;
            $seasonId = wp_update_post($postData, true);
        } else {
            $seasonId = wp_insert_post($postData, true);
        }

        if (!$seasonId || is_wp_error($seasonId)) {
            wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-add-season'), 'error', 'Neuspešno čuvanje sezone.'));
            exit;
        }

        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-seasons'), 'success', 'Sezona je sačuvana.'));
        exit;
    }

    public static function handleDeleteSeason($capability)
    {
        self::requireCapability($capability);
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($id <= 0) {
            wp_die('Nedostaje ID.');
        }

        check_admin_referer('opentt_unified_delete_season_' . $id);
        wp_trash_post($id);
        wp_safe_redirect(AdminNoticeManager::buildUrl(admin_url('admin.php?page=stkb-unified-seasons'), 'success', 'Sezona je obrisana.'));
        exit;
    }

    private static function requireCapability($capability)
    {
        if (!current_user_can((string) $capability)) {
            wp_die('Nemaš dozvolu za ovu akciju.');
        }
    }
}
