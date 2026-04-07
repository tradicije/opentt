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

final class UserPortalManager
{
    const ROLE_MEMBER = 'opentt_member';
    const ROLE_LEAGUE_ADMIN = 'opentt_league_admin';
    const ROLE_TEAM_MANAGER = 'opentt_team_manager';

    const META_LEAGUES = 'opentt_admin_leagues';
    const META_MANAGER_CLUB = 'opentt_manager_club_id';
    const META_LINKED_PLAYER = 'opentt_linked_player_id';
    const META_PROFILE_AVATAR_ID = 'opentt_profile_avatar_id';

    public static function registerRoles()
    {
        add_role(self::ROLE_MEMBER, 'OpenTT Član', [
            'read' => true,
            'upload_files' => true,
        ]);

        add_role(self::ROLE_LEAGUE_ADMIN, 'OpenTT Administrator lige', [
            'read' => true,
            'upload_files' => true,
        ]);

        add_role(self::ROLE_TEAM_MANAGER, 'OpenTT Menadžer tima', [
            'read' => true,
            'upload_files' => true,
        ]);
    }

    public static function ensureDefaultPages()
    {
        self::ensurePage('prijava', 'Prijava', '[opentt_auth]');
        self::ensurePage('profil', 'Profil', '[opentt_profile]');
    }

    private static function ensurePage($slug, $title, $shortcode)
    {
        if (!post_type_exists('page')) {
            return;
        }

        $existing = get_posts([
            'post_type' => 'page',
            'name' => sanitize_title((string) $slug),
            'numberposts' => 1,
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future', 'trash'],
            'fields' => 'ids',
            'suppress_filters' => true,
        ]);
        if (!empty($existing)) {
            return;
        }

        wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => (string) $title,
            'post_name' => sanitize_title((string) $slug),
            'post_content' => (string) $shortcode,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);
    }

    public static function roleLabelBySlug($role)
    {
        $role = sanitize_key((string) $role);
        $map = self::assignableRoleOptions();
        return isset($map[$role]) ? (string) $map[$role] : ucfirst(str_replace('_', ' ', $role));
    }

    public static function assignableRoleOptions()
    {
        return [
            self::ROLE_MEMBER => 'Član',
            'editor' => 'Urednik',
            self::ROLE_LEAGUE_ADMIN => 'Administrator lige',
            self::ROLE_TEAM_MANAGER => 'Menadžer tima',
            'administrator' => 'Administrator',
        ];
    }

    public static function getPrimaryRole($user)
    {
        if (!($user instanceof \WP_User)) {
            return '';
        }
        $roles = is_array($user->roles) ? $user->roles : [];
        if (empty($roles)) {
            return '';
        }

        foreach ([self::ROLE_LEAGUE_ADMIN, self::ROLE_TEAM_MANAGER, 'editor', self::ROLE_MEMBER, 'administrator'] as $preferred) {
            if (in_array($preferred, $roles, true)) {
                return $preferred;
            }
        }

        return (string) reset($roles);
    }

    public static function getUserManagedLeagues($userId)
    {
        $userId = intval($userId);
        if ($userId <= 0) {
            return [];
        }
        $raw = get_user_meta($userId, self::META_LEAGUES, true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $out = [];
        foreach ($raw as $slug) {
            $slug = sanitize_title((string) $slug);
            if ($slug !== '') {
                $out[] = $slug;
            }
        }
        return array_values(array_unique($out));
    }

    public static function getUserManagedClubId($userId)
    {
        return max(0, intval(get_user_meta(intval($userId), self::META_MANAGER_CLUB, true)));
    }

    public static function getUserLinkedPlayerId($userId)
    {
        $playerId = max(0, intval(get_user_meta(intval($userId), self::META_LINKED_PLAYER, true)));
        if ($playerId > 0) {
            return $playerId;
        }
        if (class_exists('OpenTT_Unified_Core') && method_exists('OpenTT_Unified_Core', 'get_player_id_by_wp_user_id')) {
            return intval(\OpenTT_Unified_Core::get_player_id_by_wp_user_id(intval($userId)));
        }
        return 0;
    }

    public static function canManageLeague($userId, $leagueSlug)
    {
        $userId = intval($userId);
        $leagueSlug = sanitize_title((string) $leagueSlug);
        if ($userId <= 0 || $leagueSlug === '') {
            return false;
        }
        if (user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            return true;
        }
        if (!user_can($userId, self::ROLE_LEAGUE_ADMIN)) {
            return false;
        }
        $allowed = self::getUserManagedLeagues($userId);
        return in_array($leagueSlug, $allowed, true);
    }

    public static function canManageClub($userId, $clubId)
    {
        $userId = intval($userId);
        $clubId = intval($clubId);
        if ($userId <= 0 || $clubId <= 0) {
            return false;
        }
        if (user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            return true;
        }
        if (!user_can($userId, self::ROLE_TEAM_MANAGER)) {
            return false;
        }
        return intval(get_user_meta($userId, self::META_MANAGER_CLUB, true)) === $clubId;
    }

    public static function renderUsersAdminPage($capability)
    {
        if (!current_user_can((string) $capability)) {
            wp_die('Nedovoljna prava.');
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        $leagueOptions = [];
        if (self::tableExists($matchesTable)) {
            $leagueOptions = $wpdb->get_col("SELECT DISTINCT liga_slug FROM {$matchesTable} WHERE liga_slug <> '' ORDER BY liga_slug ASC") ?: [];
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
            'fields' => ['ID', 'display_name', 'user_login', 'user_email', 'roles'],
        ]);

        echo '<div class="wrap opentt-admin">';
        echo '<h1>Korisnici i role</h1>';
        echo '<p class="description">Poveži WordPress korisnike sa igračima, dodeli role i ovlašćenja za lige/klubove.</p>';

        echo '<div class="opentt-panel">';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Korisnik</th><th>Email</th><th>Role</th><th>Povezan igrač</th><th>Lige (admin)</th><th>Tim (manager)</th><th>Akcija</th>';
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
            $role = self::getPrimaryRole($user);
            $linkedPlayerId = self::getUserLinkedPlayerId($uid);
            $managedLeagues = self::getUserManagedLeagues($uid);
            $managedClubId = self::getUserManagedClubId($uid);
            $formId = 'opentt-user-access-' . $uid;

            echo '<tr><td><strong>' . esc_html($displayName) . '</strong><br><code>@' . esc_html((string) $user->user_login) . '</code></td>';
            echo '<td>' . esc_html((string) $user->user_email) . '</td>';
            echo '<td>';
            echo '<select name="opentt_role" form="' . esc_attr($formId) . '" style="min-width:180px;">';
            foreach (self::assignableRoleOptions() as $slug => $label) {
                echo '<option value="' . esc_attr((string) $slug) . '"' . selected($role, (string) $slug, false) . '>' . esc_html((string) $label) . '</option>';
            }
            echo '</select>';
            echo '</td>';

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
            foreach ($leagueOptions as $leagueSlug) {
                $leagueSlug = sanitize_title((string) $leagueSlug);
                if ($leagueSlug === '') {
                    continue;
                }
                echo '<option value="' . esc_attr($leagueSlug) . '"' . (in_array($leagueSlug, $managedLeagues, true) ? ' selected' : '') . '>' . esc_html((string) self::slugToTitle($leagueSlug)) . '</option>';
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

            echo '<td>';
            echo '<form id="' . esc_attr($formId) . '" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('opentt_unified_save_user_access');
            echo '<input type="hidden" name="action" value="opentt_unified_save_user_access">';
            echo '<input type="hidden" name="user_id" value="' . esc_attr((string) $uid) . '">';
            echo '<button type="submit" class="button button-primary">Sačuvaj</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    public static function handleSaveUserAccess($capability)
    {
        if (!current_user_can((string) $capability)) {
            wp_die('Nedovoljna prava.');
        }
        check_admin_referer('opentt_unified_save_user_access');

        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $user = $userId > 0 ? get_userdata($userId) : null;
        if (!$user) {
            wp_safe_redirect(self::adminNoticeUrl(admin_url('admin.php?page=stkb-unified-users'), 'error', 'Korisnik nije pronađen.'));
            exit;
        }

        $selectedRole = isset($_POST['opentt_role']) ? sanitize_key((string) wp_unslash($_POST['opentt_role'])) : self::ROLE_MEMBER;
        $allowedRoles = array_keys(self::assignableRoleOptions());
        if (!in_array($selectedRole, $allowedRoles, true)) {
            $selectedRole = self::ROLE_MEMBER;
        }

        if ($selectedRole !== 'administrator' || !user_can($userId, 'administrator')) {
            $user->set_role($selectedRole);
        }

        $leagues = isset($_POST['admin_leagues']) && is_array($_POST['admin_leagues']) ? (array) wp_unslash($_POST['admin_leagues']) : [];
        $cleanLeagues = [];
        foreach ($leagues as $slug) {
            $slug = sanitize_title((string) $slug);
            if ($slug !== '') {
                $cleanLeagues[] = $slug;
            }
        }
        update_user_meta($userId, self::META_LEAGUES, array_values(array_unique($cleanLeagues)));

        $managerClubId = isset($_POST['manager_club_id']) ? intval($_POST['manager_club_id']) : 0;
        if ($managerClubId > 0) {
            $post = get_post($managerClubId);
            if (!($post instanceof \WP_Post) || $post->post_type !== 'klub') {
                $managerClubId = 0;
            }
        }
        update_user_meta($userId, self::META_MANAGER_CLUB, $managerClubId);

        $linkedPlayerId = isset($_POST['linked_player_id']) ? intval($_POST['linked_player_id']) : 0;
        if ($linkedPlayerId > 0) {
            $post = get_post($linkedPlayerId);
            if (!($post instanceof \WP_Post) || $post->post_type !== 'igrac') {
                $linkedPlayerId = 0;
            }
        }
        update_user_meta($userId, self::META_LINKED_PLAYER, $linkedPlayerId);

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

        wp_safe_redirect(self::adminNoticeUrl(admin_url('admin.php?page=stkb-unified-users'), 'success', 'Korisnička ovlašćenja su sačuvana.'));
        exit;
    }

    public static function renderAuthShortcode()
    {
        $notice = self::renderFrontendNotice();
        if (is_user_logged_in()) {
            $profileUrl = home_url('/profil/');
            return $notice . '<section class="opentt-auth-card"><h3>Prijava</h3><p>Već si prijavljen.</p><p><a class="opentt-auth-btn" href="' . esc_url($profileUrl) . '">Idi na profil</a> <a class="opentt-auth-btn is-ghost" href="' . esc_url(wp_logout_url(home_url('/prijava/'))) . '">Odjavi se</a></p></section>';
        }

        $loginAction = admin_url('admin-post.php');
        $registerAction = admin_url('admin-post.php');
        $registerEnabled = get_option('users_can_register') === '1';
        $out = '<section class="opentt-auth-card">';
        $out .= '<h3>Prijava</h3>';
        $out .= '<form method="post" action="' . esc_url($loginAction) . '" class="opentt-auth-form">';
        $out .= wp_nonce_field('opentt_front_login', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_login">';
        $out .= '<label>Korisničko ime ili email<input type="text" name="log" required></label>';
        $out .= '<label>Lozinka<input type="password" name="pwd" required></label>';
        $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="remember" value="1"> Zapamti me</label>';
        $out .= '<button type="submit" class="opentt-auth-btn">Prijavi se</button>';
        $out .= '</form>';

        if ($registerEnabled) {
            $out .= '<hr>'; 
            $out .= '<h3>Registracija</h3>';
            $out .= '<form method="post" action="' . esc_url($registerAction) . '" class="opentt-auth-form">';
            $out .= wp_nonce_field('opentt_front_register', '_wpnonce', true, false);
            $out .= '<input type="hidden" name="action" value="opentt_front_register">';
            $out .= '<label>Korisničko ime<input type="text" name="user_login" required></label>';
            $out .= '<label>Email<input type="email" name="user_email" required></label>';
            $out .= '<label>Lozinka<input type="password" name="user_pass" required></label>';
            $out .= '<button type="submit" class="opentt-auth-btn">Registruj nalog</button>';
            $out .= '</form>';
        } else {
            $out .= '<p class="opentt-auth-note">Registracija je trenutno isključena.</p>';
        }

        $out .= '</section>';
        return $notice . $out;
    }

    public static function renderProfileShortcode()
    {
        $notice = self::renderFrontendNotice();
        if (!is_user_logged_in()) {
            return $notice . '<section class="opentt-profile-card"><p>Moraš biti prijavljen da bi pristupio profilu. <a href="' . esc_url(home_url('/prijava/')) . '">Prijava</a></p></section>';
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        if (!$user) {
            return '<section class="opentt-profile-card"><p>Korisnik nije pronađen.</p></section>';
        }

        $primaryRole = self::getPrimaryRole($user);
        $roleLabel = self::roleLabelBySlug($primaryRole);
        $linkedPlayerId = self::getUserLinkedPlayerId($userId);
        $profileAvatar = self::profileAvatarUrl($userId, 128);

        $out = '<section class="opentt-profile-card">';
        $out .= '<header class="opentt-profile-head">';
        $out .= '<img src="' . esc_url($profileAvatar) . '" alt="Avatar" class="opentt-profile-avatar">';
        $out .= '<div><h2>' . esc_html((string) $user->display_name) . '</h2><p>Rola: <strong>' . esc_html($roleLabel) . '</strong></p></div>';
        $out .= '</header>';

        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data" class="opentt-auth-form">';
        $out .= wp_nonce_field('opentt_front_profile_update', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_profile_update">';
        $out .= '<label>Prikazno ime<input type="text" name="display_name" value="' . esc_attr((string) $user->display_name) . '" required></label>';
        $out .= '<label>Kratak opis<input type="text" name="description" value="' . esc_attr((string) get_user_meta($userId, 'description', true)) . '"></label>';
        $out .= '<label>Nova profilna slika<input type="file" name="profile_avatar" accept="image/*"></label>';
        $out .= '<button type="submit" class="opentt-auth-btn">Sačuvaj profil</button>';
        $out .= '</form>';

        if ($linkedPlayerId > 0) {
            $playerUrl = (string) get_permalink($linkedPlayerId);
            if ($playerUrl !== '') {
                $out .= '<p class="opentt-auth-note">Povezan profil igrača: <a href="' . esc_url($playerUrl) . '">' . esc_html((string) get_the_title($linkedPlayerId)) . '</a></p>';
            }
        }

        if (user_can($userId, 'editor') || user_can($userId, 'administrator')) {
            $out .= self::renderEditorTools($userId);
        }

        if (user_can($userId, self::ROLE_LEAGUE_ADMIN) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            $out .= self::renderLeagueAdminTools($userId);
        }

        if (user_can($userId, self::ROLE_TEAM_MANAGER) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            $out .= self::renderTeamManagerTools($userId);
        }

        $out .= '</section>';
        return $notice . $out;
    }

    private static function renderEditorTools($userId)
    {
        $out = '<section class="opentt-profile-section"><h3>Alati urednika</h3>';
        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
        $out .= wp_nonce_field('opentt_front_save_editor_post', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_save_editor_post">';
        $out .= '<label>Naslov vesti<input type="text" name="post_title" required></label>';
        $out .= '<label>Tekst vesti<textarea name="post_content" rows="6" required></textarea></label>';
        $out .= '<button type="submit" class="opentt-auth-btn">Objavi vest</button>';
        $out .= '</form>';

        $posts = get_posts([
            'post_type' => 'post',
            'author' => intval($userId),
            'numberposts' => 10,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        $out .= '<div class="opentt-profile-list"><h4>Moje vesti</h4>';
        if (empty($posts)) {
            $out .= '<p>Nema objavljenih vesti.</p>';
        } else {
            $out .= '<ul>';
            foreach ($posts as $post) {
                if (!($post instanceof \WP_Post)) {
                    continue;
                }
                $out .= '<li><a href="' . esc_url((string) get_permalink($post->ID)) . '">' . esc_html((string) $post->post_title) . '</a></li>';
            }
            $out .= '</ul>';
        }
        $out .= '</div></section>';
        return $out;
    }

    private static function renderLeagueAdminTools($userId)
    {
        $leagues = self::getUserManagedLeagues($userId);
        if (empty($leagues) && !user_can($userId, 'administrator') && !user_can($userId, \OpenTT_Unified_Core::CAP)) {
            return '<section class="opentt-profile-section"><h3>Alati administratora lige</h3><p>Nema dodeljenih liga.</p></section>';
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!self::tableExists($matchesTable)) {
            return '';
        }

        $rows = [];
        if (user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            $rows = $wpdb->get_results("SELECT * FROM {$matchesTable} ORDER BY match_date DESC, id DESC LIMIT 40") ?: [];
        } else {
            $in = implode(',', array_fill(0, count($leagues), '%s'));
            $sql = $wpdb->prepare("SELECT * FROM {$matchesTable} WHERE liga_slug IN ({$in}) ORDER BY match_date DESC, id DESC LIMIT 40", ...$leagues);
            $rows = $wpdb->get_results($sql) ?: [];
        }

        $out = '<section class="opentt-profile-section"><h3>Alati administratora lige</h3>';
        if (!empty($leagues)) {
            $labels = [];
            foreach ($leagues as $slug) {
                $labels[] = self::slugToTitle($slug);
            }
            $out .= '<p>Lige: <strong>' . esc_html(implode(', ', $labels)) . '</strong></p>';
        }

        if (empty($rows)) {
            $out .= '<p>Nema utakmica za prikaz.</p></section>';
            return $out;
        }

        $out .= '<div class="opentt-profile-list"><table class="widefat striped"><thead><tr><th>Utakmica</th><th>Rezultat</th><th>Datum</th><th>Lokacija</th><th>Akcija</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $id = intval($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }
            $homeId = intval($row->home_club_post_id ?? 0);
            $awayId = intval($row->away_club_post_id ?? 0);
            $matchLabel = trim((string) get_the_title($homeId)) . ' - ' . trim((string) get_the_title($awayId));
            $out .= '<tr><td>' . esc_html($matchLabel) . '<br><small>' . esc_html((string) self::slugToTitle((string) ($row->liga_slug ?? ''))) . ' • ' . esc_html((string) self::koloNameFromSlug((string) ($row->kolo_slug ?? ''))) . '</small></td>';
            $out .= '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-inline-form">';
            $out .= wp_nonce_field('opentt_front_save_league_match_' . $id, '_wpnonce', true, false);
            $out .= '<input type="hidden" name="action" value="opentt_front_save_league_match">';
            $out .= '<input type="hidden" name="match_id" value="' . esc_attr((string) $id) . '">';
            $out .= '<input type="number" min="0" max="9" name="home_score" value="' . esc_attr((string) intval($row->home_score ?? 0)) . '" style="width:56px;"> : ';
            $out .= '<input type="number" min="0" max="9" name="away_score" value="' . esc_attr((string) intval($row->away_score ?? 0)) . '" style="width:56px;"></td>';
            $out .= '<td><input type="text" name="match_date" value="' . esc_attr((string) ($row->match_date ?? '')) . '" style="width:190px;"></td>';
            $out .= '<td><input type="text" name="location" value="' . esc_attr((string) ($row->location ?? '')) . '" style="width:160px;"></td>';
            $out .= '<td><button type="submit" class="button">Sačuvaj</button></form></td></tr>';
        }
        $out .= '</tbody></table></div></section>';

        return $out;
    }

    private static function renderTeamManagerTools($userId)
    {
        $clubId = self::getUserManagedClubId($userId);
        if ($clubId <= 0 && !user_can($userId, 'administrator') && !user_can($userId, \OpenTT_Unified_Core::CAP)) {
            return '<section class="opentt-profile-section"><h3>Alati menadžera tima</h3><p>Nije dodeljen klub.</p></section>';
        }
        if ($clubId <= 0) {
            return '';
        }

        $club = get_post($clubId);
        if (!($club instanceof \WP_Post) || $club->post_type !== 'klub') {
            return '<section class="opentt-profile-section"><h3>Alati menadžera tima</h3><p>Klub nije pronađen.</p></section>';
        }

        $out = '<section class="opentt-profile-section"><h3>Alati menadžera tima</h3>';
        $out .= '<p>Administriraš klub: <strong>' . esc_html((string) $club->post_title) . '</strong></p>';

        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
        $out .= wp_nonce_field('opentt_front_team_save_club_' . $clubId, '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_team_save_club">';
        $out .= '<input type="hidden" name="club_id" value="' . esc_attr((string) $clubId) . '">';
        $out .= '<label>Opis kluba<textarea name="post_content" rows="4">' . esc_textarea((string) $club->post_content) . '</textarea></label>';
        $out .= '<label>Grad<input type="text" name="grad" value="' . esc_attr((string) get_post_meta($clubId, 'grad', true)) . '"></label>';
        $out .= '<label>Kontakt<input type="text" name="kontakt" value="' . esc_attr((string) get_post_meta($clubId, 'kontakt', true)) . '"></label>';
        $out .= '<label>Email<input type="email" name="email" value="' . esc_attr((string) get_post_meta($clubId, 'email', true)) . '"></label>';
        $out .= '<label>Adresa sale<input type="text" name="adresa_sale" value="' . esc_attr((string) get_post_meta($clubId, 'adresa_sale', true)) . '"></label>';
        $out .= '<label>Termin igranja<input type="text" name="termin_igranja" value="' . esc_attr((string) get_post_meta($clubId, 'termin_igranja', true)) . '"></label>';
        $out .= '<label>Boja dresa<input type="text" name="boja_dresa" value="' . esc_attr((string) get_post_meta($clubId, 'boja_dresa', true)) . '" placeholder="#0b4db8"></label>';
        $out .= '<button type="submit" class="opentt-auth-btn">Sačuvaj klub</button>';
        $out .= '</form>';

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
        ]);

        $out .= '<div class="opentt-profile-list"><h4>Igrači kluba</h4>';
        if (empty($players)) {
            $out .= '<p>Nema unetih igrača.</p>';
        } else {
            $out .= '<ul>';
            foreach ($players as $player) {
                if (!($player instanceof \WP_Post)) {
                    continue;
                }
                $out .= '<li>' . esc_html((string) $player->post_title) . '</li>';
            }
            $out .= '</ul>';
        }

        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
        $out .= wp_nonce_field('opentt_front_team_save_player_' . $clubId, '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_team_save_player">';
        $out .= '<input type="hidden" name="club_id" value="' . esc_attr((string) $clubId) . '">';
        $out .= '<label>Ime i prezime igrača<input type="text" name="player_name" required></label>';
        $out .= '<label>Datum rođenja<input type="date" name="datum_rodjenja"></label>';
        $out .= '<button type="submit" class="opentt-auth-btn">Dodaj igrača</button>';
        $out .= '</form>';

        $out .= '</div></section>';

        return $out;
    }

    public static function handleFrontLogin()
    {
        check_admin_referer('opentt_front_login');

        $login = isset($_POST['log']) ? sanitize_text_field((string) wp_unslash($_POST['log'])) : '';
        $pwd = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
        $remember = !empty($_POST['remember']);

        $creds = [
            'user_login' => $login,
            'user_password' => $pwd,
            'remember' => $remember,
        ];

        $user = wp_signon($creds, is_ssl());
        $redirect = home_url('/profil/');
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg(['opentt_notice' => 'error', 'opentt_msg' => 'Neuspešna prijava. Proveri podatke.'], home_url('/prijava/')));
            exit;
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public static function handleFrontRegister()
    {
        check_admin_referer('opentt_front_register');

        if (get_option('users_can_register') !== '1') {
            wp_safe_redirect(add_query_arg(['opentt_notice' => 'error', 'opentt_msg' => 'Registracija je trenutno isključena.'], home_url('/prijava/')));
            exit;
        }

        $login = isset($_POST['user_login']) ? sanitize_user((string) wp_unslash($_POST['user_login']), true) : '';
        $email = isset($_POST['user_email']) ? sanitize_email((string) wp_unslash($_POST['user_email'])) : '';
        $pass = isset($_POST['user_pass']) ? (string) wp_unslash($_POST['user_pass']) : '';

        if ($login === '' || !is_email($email) || strlen($pass) < 6) {
            wp_safe_redirect(add_query_arg(['opentt_notice' => 'error', 'opentt_msg' => 'Proveri podatke registracije (lozinka min 6 karaktera).'], home_url('/prijava/')));
            exit;
        }
        if (username_exists($login) || email_exists($email)) {
            wp_safe_redirect(add_query_arg(['opentt_notice' => 'error', 'opentt_msg' => 'Korisničko ime ili email već postoje.'], home_url('/prijava/')));
            exit;
        }

        $userId = wp_create_user($login, $pass, $email);
        if (is_wp_error($userId) || intval($userId) <= 0) {
            wp_safe_redirect(add_query_arg(['opentt_notice' => 'error', 'opentt_msg' => 'Registracija nije uspela.'], home_url('/prijava/')));
            exit;
        }

        $user = get_userdata((int) $userId);
        if ($user instanceof \WP_User) {
            $user->set_role(self::ROLE_MEMBER);
        }

        wp_set_current_user((int) $userId);
        wp_set_auth_cookie((int) $userId, true, is_ssl());

        wp_safe_redirect(home_url('/profil/'));
        exit;
    }

    public static function handleFrontProfileUpdate()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }
        check_admin_referer('opentt_front_profile_update');

        $userId = get_current_user_id();
        $displayName = isset($_POST['display_name']) ? sanitize_text_field((string) wp_unslash($_POST['display_name'])) : '';
        $description = isset($_POST['description']) ? sanitize_text_field((string) wp_unslash($_POST['description'])) : '';

        if ($displayName !== '') {
            wp_update_user([
                'ID' => $userId,
                'display_name' => $displayName,
            ]);
        }
        update_user_meta($userId, 'description', $description);

        if (!empty($_FILES['profile_avatar']['name']) && !empty($_FILES['profile_avatar']['tmp_name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachmentId = media_handle_upload('profile_avatar', 0);
            if (!is_wp_error($attachmentId) && intval($attachmentId) > 0) {
                update_user_meta($userId, self::META_PROFILE_AVATAR_ID, intval($attachmentId));
            }
        }

        wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'success', 'Profil je sačuvan.'));
        exit;
    }

    public static function handleFrontEditorPost()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }
        check_admin_referer('opentt_front_save_editor_post');

        $userId = get_current_user_id();
        if (!user_can($userId, 'edit_posts')) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za objavu vesti.'));
            exit;
        }

        $title = isset($_POST['post_title']) ? sanitize_text_field((string) wp_unslash($_POST['post_title'])) : '';
        $content = isset($_POST['post_content']) ? wp_kses_post((string) wp_unslash($_POST['post_content'])) : '';
        if ($title === '' || trim($content) === '') {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Naslov i tekst vesti su obavezni.'));
            exit;
        }

        $postId = wp_insert_post([
            'post_type' => 'post',
            'post_author' => $userId,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $content,
        ]);

        if (is_wp_error($postId) || intval($postId) <= 0) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Greška pri objavi vesti.'));
            exit;
        }

        wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'success', 'Vest je objavljena.'));
        exit;
    }

    public static function handleFrontSaveLeagueMatch()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $matchId = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
        if ($matchId <= 0) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nedostaje ID utakmice.'));
            exit;
        }
        check_admin_referer('opentt_front_save_league_match_' . $matchId);

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!self::tableExists($matchesTable)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Tabela utakmica nije dostupna.'));
            exit;
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matchesTable} WHERE id=%d LIMIT 1", $matchId));
        if (!$row || !is_object($row)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Utakmica nije pronađena.'));
            exit;
        }

        $userId = get_current_user_id();
        if (!self::canManageLeague($userId, (string) ($row->liga_slug ?? ''))) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za ovu ligu.'));
            exit;
        }

        $homeScore = max(0, intval($_POST['home_score'] ?? 0));
        $awayScore = max(0, intval($_POST['away_score'] ?? 0));
        $played = ($homeScore >= 4 || $awayScore >= 4) ? 1 : 0;
        $matchDate = sanitize_text_field((string) wp_unslash($_POST['match_date'] ?? (string) ($row->match_date ?? '')));
        $location = sanitize_text_field((string) wp_unslash($_POST['location'] ?? (string) ($row->location ?? '')));

        $wpdb->update($matchesTable, [
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'played' => $played,
            'match_date' => $matchDate,
            'location' => $location,
            'updated_at' => current_time('mysql'),
        ], ['id' => $matchId]);

        wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'success', 'Utakmica je sačuvana.'));
        exit;
    }

    public static function handleFrontTeamSaveClub()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $clubId = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
        if ($clubId <= 0) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nedostaje klub.'));
            exit;
        }
        check_admin_referer('opentt_front_team_save_club_' . $clubId);

        $userId = get_current_user_id();
        if (!self::canManageClub($userId, $clubId)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za taj klub.'));
            exit;
        }

        $post = get_post($clubId);
        if (!($post instanceof \WP_Post) || $post->post_type !== 'klub') {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Klub nije pronađen.'));
            exit;
        }

        $content = isset($_POST['post_content']) ? wp_kses_post((string) wp_unslash($_POST['post_content'])) : (string) $post->post_content;
        wp_update_post([
            'ID' => $clubId,
            'post_content' => $content,
        ]);

        update_post_meta($clubId, 'grad', sanitize_text_field((string) wp_unslash($_POST['grad'] ?? '')));
        update_post_meta($clubId, 'kontakt', sanitize_text_field((string) wp_unslash($_POST['kontakt'] ?? '')));
        update_post_meta($clubId, 'email', sanitize_email((string) wp_unslash($_POST['email'] ?? '')));
        update_post_meta($clubId, 'adresa_sale', sanitize_text_field((string) wp_unslash($_POST['adresa_sale'] ?? '')));
        update_post_meta($clubId, 'termin_igranja', sanitize_text_field((string) wp_unslash($_POST['termin_igranja'] ?? '')));
        $jerseyColor = sanitize_hex_color((string) wp_unslash($_POST['boja_dresa'] ?? ''));
        update_post_meta($clubId, 'boja_dresa', $jerseyColor ? $jerseyColor : '');

        wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'success', 'Klub je sačuvan.'));
        exit;
    }

    public static function handleFrontTeamSavePlayer()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $clubId = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
        if ($clubId <= 0) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nedostaje klub.'));
            exit;
        }
        check_admin_referer('opentt_front_team_save_player_' . $clubId);

        $userId = get_current_user_id();
        if (!self::canManageClub($userId, $clubId)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za taj klub.'));
            exit;
        }

        $playerName = isset($_POST['player_name']) ? sanitize_text_field((string) wp_unslash($_POST['player_name'])) : '';
        if ($playerName === '') {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Ime igrača je obavezno.'));
            exit;
        }

        $playerId = wp_insert_post([
            'post_type' => 'igrac',
            'post_status' => 'publish',
            'post_title' => $playerName,
            'post_content' => '',
        ]);
        if (is_wp_error($playerId) || intval($playerId) <= 0) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Dodavanje igrača nije uspelo.'));
            exit;
        }

        update_post_meta($playerId, 'povezani_klub', $clubId);
        update_post_meta($playerId, 'klub_igraca', $clubId);
        update_post_meta($playerId, 'datum_rodjenja', sanitize_text_field((string) wp_unslash($_POST['datum_rodjenja'] ?? '')));
        update_post_meta($playerId, 'drzavljanstvo', 'RS');

        wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'success', 'Igrač je dodat.'));
        exit;
    }

    private static function profileAvatarUrl($userId, $size)
    {
        $avatarId = intval(get_user_meta(intval($userId), self::META_PROFILE_AVATAR_ID, true));
        if ($avatarId > 0) {
            $url = (string) wp_get_attachment_image_url($avatarId, 'thumbnail');
            if ($url !== '') {
                return $url;
            }
        }
        return (string) get_avatar_url(intval($userId), ['size' => max(32, intval($size))]);
    }

    private static function adminNoticeUrl($url, $type, $message)
    {
        return add_query_arg([
            'opentt_notice' => sanitize_key((string) $type),
            'opentt_msg' => (string) $message,
        ], $url);
    }

    private static function frontendNoticeUrl($url, $type, $message)
    {
        return add_query_arg([
            'opentt_notice' => sanitize_key((string) $type),
            'opentt_msg' => (string) $message,
        ], $url);
    }

    private static function renderFrontendNotice()
    {
        $type = isset($_GET['opentt_notice']) ? sanitize_key((string) wp_unslash($_GET['opentt_notice'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $message = isset($_GET['opentt_msg']) ? sanitize_text_field((string) wp_unslash($_GET['opentt_msg'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($type === '' || $message === '') {
            return '';
        }
        $cls = $type === 'success' ? 'is-success' : 'is-error';
        return '<div class="opentt-frontend-notice ' . esc_attr($cls) . '">' . esc_html($message) . '</div>';
    }

    private static function tableExists($tableName)
    {
        global $wpdb;
        $tableName = (string) $tableName;
        if ($tableName === '') {
            return false;
        }
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
        return is_string($exists) && $exists === $tableName;
    }

    private static function slugToTitle($slug)
    {
        $slug = trim((string) $slug);
        if ($slug === '') {
            return '';
        }
        $text = str_replace('-', ' ', $slug);
        if (function_exists('mb_convert_case')) {
            return (string) mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
        }
        return (string) ucwords(strtolower($text));
    }

    private static function koloNameFromSlug($slug)
    {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return '';
        }
        if (preg_match('/(\\d+)/', $slug, $m)) {
            return $m[1] . '. kolo';
        }
        return self::slugToTitle($slug);
    }
}
