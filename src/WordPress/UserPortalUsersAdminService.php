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

final class UserPortalUsersAdminService
{
    public static function renderUsersAdminPage($capability, $deps = [])
    {
        $tableExists = $deps['tableExists'];
        $getPrimaryRole = $deps['getPrimaryRole'];
        $getUserLinkedPlayerId = $deps['getUserLinkedPlayerId'];
        $getUserManagedLeagueSeasons = $deps['getUserManagedLeagueSeasons'];
        $getUserManagedClubId = $deps['getUserManagedClubId'];
        $assignableRoleOptions = $deps['assignableRoleOptions'];
        $slugToTitle = $deps['slugToTitle'];

        if (!current_user_can((string) $capability)) {
            wp_die('Nedovoljna prava.');
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        $leagueOptions = [];
        if ($tableExists($matchesTable)) {
            $leagueRows = $wpdb->get_results("SELECT DISTINCT liga_slug, sezona_slug FROM {$matchesTable} WHERE liga_slug <> '' ORDER BY liga_slug ASC, sezona_slug DESC") ?: [];
            foreach ($leagueRows as $lr) {
                if (!is_object($lr)) {
                    continue;
                }
                $liga = sanitize_title((string) ($lr->liga_slug ?? ''));
                $sezona = sanitize_title((string) ($lr->sezona_slug ?? ''));
                if ($liga === '' || $sezona === '') {
                    continue;
                }
                $leagueOptions[] = $liga . '|' . $sezona;
            }
        }

        $clubs = get_posts([
            'post_type' => 'klub',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        $players = get_posts([
            'post_type' => 'igrac',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]) ?: [];

        $users = get_users([
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => 999,
            'fields' => 'all',
        ]);

        echo '<div class="wrap opentt-admin">';
        echo '<h1>Korisnici i role</h1>';
        echo '<p class="description">Poveži WordPress korisnike sa igračima, dodeli role i ovlašćenja za lige/klubove.</p>';

        echo '<div class="opentt-panel">';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Korisnik</th><th>Email</th><th>Role</th><th>Povezan igrač</th><th>Lige/Sezone (admin)</th><th>Tim (manager)</th><th>Akcija</th>';
        echo '</tr></thead><tbody>';

        foreach ($users as $user) {
            if (!($user instanceof \WP_User)) {
                continue;
            }
            $uid = intval($user->ID);
            $displayName = trim((string) $user->display_name);
            if ($displayName === '') {
                $displayName = trim((string) $user->user_login);
            }
            $role = $getPrimaryRole($user);
            $linkedPlayerId = $getUserLinkedPlayerId($uid);
            $managedLeagues = $getUserManagedLeagueSeasons($uid);
            $managedClubId = $getUserManagedClubId($uid);
            $formId = 'opentt-user-access-' . $uid;

            echo '<tr><td><strong>' . esc_html($displayName) . '</strong><br><code>@' . esc_html((string) $user->user_login) . '</code></td>';
            echo '<td>' . esc_html((string) $user->user_email) . '</td>';
            echo '<td><select name="opentt_role" form="' . esc_attr($formId) . '" style="min-width:180px;">';
            foreach ($assignableRoleOptions() as $slug => $label) {
                echo '<option value="' . esc_attr((string) $slug) . '"' . selected($role, (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select></td>';

            echo '<td><select name="linked_player_id" form="' . esc_attr($formId) . '" style="min-width:200px;"><option value="0">- Nije povezano -</option>';
            foreach ($players as $pid) {
                $pid = intval($pid);
                if ($pid <= 0) {
                    continue;
                }
                echo '<option value="' . esc_attr((string) $pid) . '"' . selected($linkedPlayerId, $pid, false) . '>' . esc_html((string) get_the_title($pid)) . '</option>';
            }
            echo '</select></td>';

            echo '<td><select name="admin_leagues[]" form="' . esc_attr($formId) . '" multiple size="4" style="min-width:200px;">';
            foreach ($leagueOptions as $leagueSeason) {
                $leagueSeason = (string) $leagueSeason;
                if (strpos($leagueSeason, '|') === false) {
                    continue;
                }
                $parts = explode('|', $leagueSeason, 2);
                $leagueSlug = sanitize_title((string) ($parts[0] ?? ''));
                $seasonSlug = sanitize_title((string) ($parts[1] ?? ''));
                if ($leagueSlug === '' || $seasonSlug === '') {
                    continue;
                }
                $label = $slugToTitle($leagueSlug) . ' / ' . str_replace('-', '/', $seasonSlug);
                echo '<option value="' . esc_attr($leagueSeason) . '"' . (in_array($leagueSeason, $managedLeagues, true) ? ' selected' : '') . '>' . esc_html($label) . '</option>';
            }
            echo '</select></td>';

            echo '<td><select name="manager_club_id" form="' . esc_attr($formId) . '" style="min-width:200px;"><option value="0">- Nije dodeljen tim -</option>';
            foreach ($clubs as $cid) {
                $cid = intval($cid);
                if ($cid <= 0) {
                    continue;
                }
                echo '<option value="' . esc_attr((string) $cid) . '"' . selected($managedClubId, $cid, false) . '>' . esc_html((string) get_the_title($cid)) . '</option>';
            }
            echo '</select></td>';

            echo '<td><form id="' . esc_attr($formId) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('opentt_unified_save_user_access');
            echo '<input type="hidden" name="action" value="opentt_unified_save_user_access">';
            echo '<input type="hidden" name="user_id" value="' . esc_attr((string) $uid) . '">';
            echo '<button type="submit" class="button button-primary">Sačuvaj</button>';
            echo '</form></td></tr>';
        }

        echo '</tbody></table></div></div>';
    }

    public static function handleSaveUserAccess($capability, $deps = [])
    {
        $adminNoticeUrl = $deps['adminNoticeUrl'];
        $assignableRoleOptions = $deps['assignableRoleOptions'];
        $metaLeagues = (string) $deps['metaLeagues'];
        $metaManagerClub = (string) $deps['metaManagerClub'];
        $metaLinkedPlayer = (string) $deps['metaLinkedPlayer'];
        $roleMember = (string) $deps['roleMember'];

        if (!current_user_can((string) $capability)) {
            wp_die('Nedovoljna prava.');
        }
        check_admin_referer('opentt_unified_save_user_access');

        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $user = $userId > 0 ? get_userdata($userId) : null;
        if (!$user) {
            wp_safe_redirect($adminNoticeUrl(admin_url('admin.php?page=stkb-unified-users'), 'error', 'Korisnik nije pronađen.'));
            exit;
        }

        $selectedRole = isset($_POST['opentt_role']) ? sanitize_key((string) wp_unslash($_POST['opentt_role'])) : $roleMember;
        $allowedRoles = array_keys($assignableRoleOptions());
        if (!in_array($selectedRole, $allowedRoles, true)) {
            $selectedRole = $roleMember;
        }

        if ($selectedRole !== 'administrator' || !user_can($userId, 'administrator')) {
            $user->set_role($selectedRole);
        }

        $leagues = isset($_POST['admin_leagues']) && is_array($_POST['admin_leagues']) ? (array) wp_unslash($_POST['admin_leagues']) : [];
        $cleanLeagues = [];
        foreach ($leagues as $item) {
            $item = (string) $item;
            if (strpos($item, '|') !== false) {
                $parts = explode('|', $item, 2);
                $leagueSlug = sanitize_title((string) ($parts[0] ?? ''));
                $seasonSlug = sanitize_title((string) ($parts[1] ?? ''));
                if ($leagueSlug !== '' && $seasonSlug !== '') {
                    $cleanLeagues[] = $leagueSlug . '|' . $seasonSlug;
                }
            } else {
                $slug = sanitize_title($item);
                if ($slug !== '') {
                    $cleanLeagues[] = $slug;
                }
            }
        }
        update_user_meta($userId, $metaLeagues, array_values(array_unique($cleanLeagues)));

        $managerClubId = isset($_POST['manager_club_id']) ? intval($_POST['manager_club_id']) : 0;
        if ($managerClubId > 0) {
            $post = get_post($managerClubId);
            if (!($post instanceof \WP_Post) || $post->post_type !== 'klub') {
                $managerClubId = 0;
            }
        }
        update_user_meta($userId, $metaManagerClub, $managerClubId);

        $linkedPlayerId = isset($_POST['linked_player_id']) ? intval($_POST['linked_player_id']) : 0;
        if ($linkedPlayerId > 0) {
            $post = get_post($linkedPlayerId);
            if (!($post instanceof \WP_Post) || $post->post_type !== 'igrac') {
                $linkedPlayerId = 0;
            }
        }
        update_user_meta($userId, $metaLinkedPlayer, $linkedPlayerId);

        if ($linkedPlayerId > 0) {
            $already = get_posts([
                'post_type' => 'igrac',
                'numberposts' => -1,
                'fields' => 'ids',
                'post__not_in' => [$linkedPlayerId],
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'meta_query' => [
                    'relation' => 'OR',
                    ['key' => 'opentt_wp_user_id', 'value' => $userId, 'compare' => '=', 'type' => 'NUMERIC'],
                    ['key' => 'wp_user_id', 'value' => $userId, 'compare' => '=', 'type' => 'NUMERIC'],
                ],
            ]) ?: [];
            foreach ($already as $playerId) {
                delete_post_meta(intval($playerId), 'opentt_wp_user_id');
                delete_post_meta(intval($playerId), 'wp_user_id');
            }
            update_post_meta($linkedPlayerId, 'opentt_wp_user_id', $userId);
            update_post_meta($linkedPlayerId, 'wp_user_id', $userId);
        }

        wp_safe_redirect($adminNoticeUrl(admin_url('admin.php?page=stkb-unified-users'), 'success', 'Korisnička ovlašćenja su sačuvana.'));
        exit;
    }
}
