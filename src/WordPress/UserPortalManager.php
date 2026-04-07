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
        foreach ($raw as $item) {
            $item = (string) $item;
            if (strpos($item, '|') !== false) {
                $parts = explode('|', $item, 2);
                $slug = sanitize_title((string) ($parts[0] ?? ''));
            } else {
                $slug = sanitize_title($item);
            }
            if ($slug !== '') {
                $out[] = $slug;
            }
        }
        return array_values(array_unique($out));
    }

    public static function getUserManagedLeagueSeasons($userId)
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
        foreach ($raw as $item) {
            $item = (string) $item;
            if (strpos($item, '|') === false) {
                continue;
            }
            $parts = explode('|', $item, 2);
            $league = sanitize_title((string) ($parts[0] ?? ''));
            $season = sanitize_title((string) ($parts[1] ?? ''));
            if ($league === '' || $season === '') {
                continue;
            }
            $out[] = $league . '|' . $season;
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

    public static function canManageLeague($userId, $leagueSlug, $seasonSlug = '')
    {
        $userId = intval($userId);
        $leagueSlug = sanitize_title((string) $leagueSlug);
        $seasonSlug = sanitize_title((string) $seasonSlug);
        if ($userId <= 0 || $leagueSlug === '') {
            return false;
        }
        if (user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            return true;
        }
        if (!user_can($userId, self::ROLE_LEAGUE_ADMIN)) {
            return false;
        }
        $allowedLeagues = self::getUserManagedLeagues($userId);
        if (in_array($leagueSlug, $allowedLeagues, true)) {
            return true;
        }
        if ($seasonSlug === '') {
            return false;
        }
        $allowedLeagueSeasons = self::getUserManagedLeagueSeasons($userId);
        return in_array($leagueSlug . '|' . $seasonSlug, $allowedLeagueSeasons, true);
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
            $role = self::getPrimaryRole($user);
            $linkedPlayerId = self::getUserLinkedPlayerId($uid);
            $managedLeagues = self::getUserManagedLeagueSeasons($uid);
            $managedClubId = self::getUserManagedClubId($uid);
            $formId = 'opentt-user-access-' . $uid;

            echo '<tr><td><strong>' . esc_html($displayName) . '</strong><br><code>@' . esc_html((string) $user->user_login) . '</code></td>';
            echo '<td>' . esc_html((string) $user->user_email) . '</td>';
            echo '<td><select name="opentt_role" form="' . esc_attr($formId) . '" style="min-width:180px;">';
            foreach (self::assignableRoleOptions() as $slug => $label) {
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
                $label = self::slugToTitle($leagueSlug) . ' / ' . str_replace('-', '/', $seasonSlug);
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
        $turnstileEnabled = (bool) \OpenTT_Unified_Core::is_turnstile_enabled();
        $turnstileSiteKey = trim((string) \OpenTT_Unified_Core::turnstile_site_key());
        $turnstileOk = $turnstileEnabled && $turnstileSiteKey !== '';

        if (is_user_logged_in()) {
            $profileUrl = home_url('/profil/');
            return $notice . '<section class="opentt-auth-card"><h3>Prijava</h3><p>Već si prijavljen.</p><p><a class="opentt-auth-btn" href="' . esc_url($profileUrl) . '">Idi na profil</a> <a class="opentt-auth-btn is-ghost" href="' . esc_url(wp_logout_url(home_url('/prijava/'))) . '">Odjavi se</a></p></section>';
        }

        $registerEnabled = get_option('users_can_register') === '1';
        $out = '<section class="opentt-auth-card opentt-auth-switcher" data-opentt-auth="1">';
        $out .= '<div class="opentt-auth-pane is-active" data-pane="login">';
        $out .= '<h3>Prijava</h3>';
        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
        $out .= wp_nonce_field('opentt_front_login', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_login">';
        $out .= '<label>Korisničko ime ili email<input type="text" name="log" required></label>';
        $out .= '<label>Lozinka<input type="password" name="pwd" required></label>';
        $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="remember" value="1"> Zapamti me</label>';
        if ($turnstileOk) {
            $out .= '<div class="opentt-turnstile-wrap"><div class="cf-turnstile" data-sitekey="' . esc_attr($turnstileSiteKey) . '" data-theme="dark"></div></div>';
        } elseif ($turnstileEnabled) {
            $out .= '<p class="opentt-auth-note">Turnstile je uključen, ali nije podešen Site Key.</p>';
        }
        $out .= '<button type="submit" class="opentt-auth-btn">Prijavi se</button>';
        $out .= '</form>';
        if ($registerEnabled) {
            $out .= '<p class="opentt-auth-switch-note">Nemaš profil? <button type="button" class="opentt-auth-link" data-open-pane="register">Registruj se</button></p>';
        }
        $out .= '</div>';

        if ($registerEnabled) {
            $out .= '<div class="opentt-auth-pane" data-pane="register">';
            $out .= '<h3>Registracija</h3>';
            $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
            $out .= wp_nonce_field('opentt_front_register', '_wpnonce', true, false);
            $out .= '<input type="hidden" name="action" value="opentt_front_register">';
            $out .= '<label>Korisničko ime<input type="text" name="user_login" required></label>';
            $out .= '<label>Email<input type="email" name="user_email" required></label>';
            $out .= '<label>Lozinka<input type="password" name="user_pass" required></label>';
            if ($turnstileOk) {
                $out .= '<div class="opentt-turnstile-wrap"><div class="cf-turnstile" data-sitekey="' . esc_attr($turnstileSiteKey) . '" data-theme="dark"></div></div>';
            }
            $out .= '<button type="submit" class="opentt-auth-btn">Registruj nalog</button>';
            $out .= '</form>';
            $out .= '<p class="opentt-auth-switch-note">Imaš nalog? <button type="button" class="opentt-auth-link" data-open-pane="login">Prijavi se</button></p>';
            $out .= '</div>';
        }

        $out .= "<script>(function(){var root=document.querySelector(\".opentt-auth-switcher[data-opentt-auth='1']\");if(!root||root.dataset.bound==='1'){return;}root.dataset.bound='1';function openPane(name){root.querySelectorAll('.opentt-auth-pane').forEach(function(p){p.classList.toggle('is-active',p.getAttribute('data-pane')===name);});}root.querySelectorAll('[data-open-pane]').forEach(function(btn){btn.addEventListener('click',function(){openPane(String(btn.getAttribute('data-open-pane')||'login'));});});})();</script>";
        if ($turnstileOk) {
            $out .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
        }
        $out .= '</section>';

        return $notice . $out;
    }

    public static function renderAuthMenuShortcode()
    {
        if (!is_user_logged_in()) {
            return '<div class="opentt-auth-menu"><a class="opentt-auth-menu-login" href="' . esc_url(home_url('/prijava/')) . '">Prijavi se</a></div>';
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        if (!$user) {
            return '<div class="opentt-auth-menu"><a class="opentt-auth-menu-login" href="' . esc_url(home_url('/prijava/')) . '">Prijavi se</a></div>';
        }

        $avatar = self::profileAvatarUrl($userId, 64);
        $profileUrl = home_url('/profil/');
        $menuId = 'opentt-auth-menu-' . $userId . '-' . wp_rand(100, 9999);

        $out = '<div class="opentt-auth-menu" id="' . esc_attr($menuId) . '">';
        $out .= '<button type="button" class="opentt-auth-menu-toggle" aria-expanded="false" aria-label="Korisnički meni">';
        $out .= '<img src="' . esc_url($avatar) . '" alt="' . esc_attr((string) $user->display_name) . '">';
        $out .= '</button>';
        $out .= '<div class="opentt-auth-menu-dropdown" hidden>';
        $out .= '<a class="opentt-auth-menu-link" href="' . esc_url(add_query_arg('opentt_profile_tab', 'profile', $profileUrl)) . '">Izmeni profil</a>';
        if (user_can($userId, self::ROLE_LEAGUE_ADMIN) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            $out .= '<a class="opentt-auth-menu-link" href="' . esc_url(add_query_arg('opentt_profile_tab', 'league', $profileUrl)) . '">Administracija lige</a>';
        }
        if (user_can($userId, 'editor') || user_can($userId, 'administrator')) {
            $out .= '<a class="opentt-auth-menu-link" href="' . esc_url(add_query_arg('opentt_profile_tab', 'editor', $profileUrl)) . '">Urednički portal</a>';
        }
        $out .= '<a class="opentt-auth-menu-link is-logout" href="' . esc_url(wp_logout_url(home_url('/prijava/'))) . '">Odjavi se</a>';
        $out .= '</div>';
        $out .= '</div>';
        $out .= "<script>(function(){var root=document.getElementById('" . esc_js($menuId) . "');if(!root||root.dataset.bound==='1'){return;}root.dataset.bound='1';var btn=root.querySelector('.opentt-auth-menu-toggle');var menu=root.querySelector('.opentt-auth-menu-dropdown');if(!btn||!menu){return;}function close(){btn.setAttribute('aria-expanded','false');menu.hidden=true;}btn.addEventListener('click',function(e){e.preventDefault();var open=btn.getAttribute('aria-expanded')==='true';if(open){close();return;}btn.setAttribute('aria-expanded','true');menu.hidden=false;});document.addEventListener('click',function(e){if(!root.contains(e.target)){close();}});})();</script>";

        return $out;
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
            return $notice . '<section class="opentt-profile-card"><p>Korisnik nije pronađen.</p></section>';
        }

        wp_enqueue_media();

        $primaryRole = self::getPrimaryRole($user);
        $roleLabel = self::roleLabelBySlug($primaryRole);
        $linkedPlayerId = self::getUserLinkedPlayerId($userId);
        $profileAvatar = self::profileAvatarUrl($userId, 128);

        $requestedTab = isset($_GET['opentt_profile_tab']) ? sanitize_key((string) wp_unslash($_GET['opentt_profile_tab'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $allowedTabs = ['profile' => true];
        if (user_can($userId, 'editor') || user_can($userId, 'administrator')) {
            $allowedTabs['editor'] = true;
        }
        if (user_can($userId, self::ROLE_LEAGUE_ADMIN) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            $allowedTabs['league'] = true;
        }
        if (user_can($userId, self::ROLE_TEAM_MANAGER) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            $allowedTabs['team'] = true;
        }
        $activeTab = isset($allowedTabs[$requestedTab]) ? $requestedTab : 'profile';

        $out = '<div class="opentt-profile-wrap">';
        $out .= '<section class="opentt-profile-card opentt-profile-card--account" id="opentt-profile-account">';
        $out .= '<header class="opentt-profile-head">';
        $out .= '<img src="' . esc_url($profileAvatar) . '" alt="Avatar" class="opentt-profile-avatar">';
        $out .= '<div class="opentt-profile-head-meta"><h2><strong>' . esc_html((string) $user->display_name) . '</strong></h2><p>' . esc_html($roleLabel) . '</p></div>';
        $out .= '</header>';
        $out .= '</section>';

        $out .= '<div class="opentt-profile-tabs" data-opentt-profile-tabs="1">';
        $out .= '<div class="opentt-profile-tab-head">';
        $out .= '<button type="button" class="opentt-profile-tab-btn' . ($activeTab === 'profile' ? ' is-active' : '') . '" data-tab="profile">Profil</button>';
        if (isset($allowedTabs['editor'])) {
            $out .= '<button type="button" class="opentt-profile-tab-btn' . ($activeTab === 'editor' ? ' is-active' : '') . '" data-tab="editor">Urednički portal</button>';
        }
        if (isset($allowedTabs['league'])) {
            $out .= '<button type="button" class="opentt-profile-tab-btn' . ($activeTab === 'league' ? ' is-active' : '') . '" data-tab="league">Administracija lige</button>';
        }
        if (isset($allowedTabs['team'])) {
            $out .= '<button type="button" class="opentt-profile-tab-btn' . ($activeTab === 'team' ? ' is-active' : '') . '" data-tab="team">Menadžer tima</button>';
        }
        $out .= '</div>';

        $out .= '<div class="opentt-profile-tab-pane' . ($activeTab === 'profile' ? ' is-active' : '') . '" data-tab-pane="profile">';
        $out .= '<section class="opentt-profile-section" id="opentt-profile-settings">';
        $out .= '<h3>Podešavanje profila</h3>';
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
        $out .= '</section>';
        $out .= '</div>';

        if (isset($allowedTabs['editor'])) {
            $out .= '<div class="opentt-profile-tab-pane' . ($activeTab === 'editor' ? ' is-active' : '') . '" data-tab-pane="editor">';
            $out .= self::renderEditorTools($userId);
            $out .= self::renderEditorPosts($userId);
            $out .= '</div>';
        }

        if (isset($allowedTabs['league'])) {
            $out .= '<div class="opentt-profile-tab-pane' . ($activeTab === 'league' ? ' is-active' : '') . '" data-tab-pane="league">';
            $out .= self::renderLeagueAdminTools($userId);
            $out .= '</div>';
        }

        if (isset($allowedTabs['team'])) {
            $out .= '<div class="opentt-profile-tab-pane' . ($activeTab === 'team' ? ' is-active' : '') . '" data-tab-pane="team">';
            $out .= self::renderTeamManagerTools($userId);
            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= "<script>(function(){var root=document.querySelector('[data-opentt-profile-tabs=\"1\"]');if(!root||root.dataset.bound==='1'){return;}root.dataset.bound='1';var btns=root.querySelectorAll('.opentt-profile-tab-btn');var panes=root.querySelectorAll('.opentt-profile-tab-pane');btns.forEach(function(btn){btn.addEventListener('click',function(){var tab=String(btn.getAttribute('data-tab')||'profile');btns.forEach(function(b){b.classList.toggle('is-active',b===btn);});panes.forEach(function(p){p.classList.toggle('is-active',String(p.getAttribute('data-tab-pane')||'')===tab);});});});})();</script>";

        $out .= '</div>';
        return $notice . $out;
    }

    private static function renderEditorTools($userId)
    {
        $out = '<section class="opentt-profile-section" id="opentt-profile-editor-tools"><h3>Alati urednika</h3>';
        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form" id="opentt-editor-post-form">';
        $out .= wp_nonce_field('opentt_front_save_editor_post', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_save_editor_post">';
        $out .= '<label>Naslov vesti<input type="text" name="post_title" required></label>';

        $editorId = 'opentt_front_post_content_' . intval($userId);
        ob_start();
        wp_editor('', $editorId, [
            'textarea_name' => 'post_content',
            'media_buttons' => true,
            'teeny' => false,
            'textarea_rows' => 10,
            'quicktags' => true,
        ]);
        $editorHtml = (string) ob_get_clean();
        $out .= '<div class="opentt-editor-wrap"><label>Tekst vesti</label>' . $editorHtml . '</div>';

        $out .= '<label>Naslovna slika (preporuka: 16:9, minimum 1200x675)<input type="hidden" id="opentt-editor-featured-image-id" name="featured_image_id" value="0"></label>';
        $out .= '<div class="opentt-editor-media-row"><button type="button" class="opentt-auth-btn is-ghost" id="opentt-editor-featured-image-btn">Izaberi naslovnu sliku</button><button type="button" class="opentt-auth-btn is-ghost" id="opentt-editor-featured-image-clear">Ukloni sliku</button></div>';
        $out .= '<div id="opentt-editor-featured-image-preview" class="opentt-editor-featured-preview"></div>';

        $out .= '<button type="submit" class="opentt-auth-btn">Objavi vest</button>';
        $out .= '</form>';

        $out .= "<script>(function($){if(!window.wp||!wp.media){return;}var frame;var btn=$('#opentt-editor-featured-image-btn');var clearBtn=$('#opentt-editor-featured-image-clear');var input=$('#opentt-editor-featured-image-id');var preview=$('#opentt-editor-featured-image-preview');if(!btn.length){return;}btn.on('click',function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:'Izaberi naslovnu sliku',button:{text:'Postavi sliku'},multiple:false,library:{type:'image'}});frame.on('select',function(){var att=frame.state().get('selection').first().toJSON();if(!att||!att.id){return;}input.val(String(att.id));preview.html('<img src=\"'+String(att.url||'')+'\" alt=\"\">');});frame.open();});clearBtn.on('click',function(e){e.preventDefault();input.val('0');preview.empty();});})(jQuery);</script>";

        $out .= '</section>';
        return $out;
    }

    private static function renderEditorPosts($userId)
    {
        $posts = get_posts([
            'post_type' => 'post',
            'author' => intval($userId),
            'numberposts' => 12,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $out = '<section class="opentt-profile-section" id="opentt-profile-editor-posts"><h3>Moje vesti</h3>';
        if (empty($posts)) {
            $out .= '<p>Nema objavljenih vesti.</p>';
        } else {
            $out .= '<div class="opentt-editor-posts-grid">';
            foreach ($posts as $post) {
                if (!($post instanceof \WP_Post)) {
                    continue;
                }
                $thumb = get_the_post_thumbnail_url($post->ID, 'medium');
                if ($thumb === '') {
                    $thumb = (string) plugins_url('assets/img/admin-ui-logo.png', dirname(__DIR__, 2) . '/opentt-unified-core.php');
                }
                $out .= '<article class="opentt-editor-post-card">';
                $out .= '<a href="' . esc_url((string) get_permalink($post->ID)) . '" target="_blank" rel="noopener">';
                $out .= '<img src="' . esc_url($thumb) . '" alt="" loading="lazy">';
                $out .= '<h5>' . esc_html((string) $post->post_title) . '</h5>';
                $out .= '</a>';
                $out .= '</article>';
            }
            $out .= '</div>';
        }
        $out .= '</section>';
        return $out;
    }

    private static function renderLeagueAdminTools($userId)
    {
        $isSuper = user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP);
        $managedLeagues = self::getUserManagedLeagues($userId);

        if (empty($managedLeagues) && !$isSuper) {
            return '<section class="opentt-profile-section"><h3>Alati administratora lige</h3><p>Nema dodeljenih liga.</p></section>';
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!self::tableExists($matchesTable)) {
            return '<section class="opentt-profile-section"><h3>Alati administratora lige</h3><p>Tabela utakmica nije dostupna.</p></section>';
        }

        $allowedLeagueSeasonMap = [];
        if (!$isSuper) {
            foreach (self::getUserManagedLeagueSeasons($userId) as $pair) {
                $parts = explode('|', (string) $pair, 2);
                $leagueSlug = sanitize_title((string) ($parts[0] ?? ''));
                $seasonSlug = sanitize_title((string) ($parts[1] ?? ''));
                if ($leagueSlug === '' || $seasonSlug === '') {
                    continue;
                }
                if (!isset($allowedLeagueSeasonMap[$leagueSlug])) {
                    $allowedLeagueSeasonMap[$leagueSlug] = [];
                }
                $allowedLeagueSeasonMap[$leagueSlug][$seasonSlug] = true;
            }
        }

        $allLeagues = $isSuper ? ($wpdb->get_col("SELECT DISTINCT liga_slug FROM {$matchesTable} WHERE liga_slug <> '' ORDER BY liga_slug ASC") ?: []) : $managedLeagues;
        $allLeagues = array_values(array_filter(array_map('sanitize_title', (array) $allLeagues)));
        if (!$isSuper && !empty($allowedLeagueSeasonMap)) {
            $allLeagues = array_values(array_unique(array_merge($allLeagues, array_keys($allowedLeagueSeasonMap))));
        }
        if (empty($allLeagues)) {
            return '<section class="opentt-profile-section"><h3>Alati administratora lige</h3><p>Nema liga za upravljanje.</p></section>';
        }

        $out = '<section class="opentt-profile-section" id="opentt-profile-league-admin"><h3>Alati administratora lige</h3>';

        $out .= '<div class="opentt-league-tabs" data-opentt-league-tabs="1">';
        $out .= '<div class="opentt-league-tab-head">';
        foreach ($allLeagues as $idx => $leagueSlug) {
            $active = $idx === 0 ? ' is-active' : '';
            $out .= '<button type="button" class="opentt-league-tab-btn' . esc_attr($active) . '" data-tab="' . esc_attr($leagueSlug) . '">' . esc_html(self::slugToTitle($leagueSlug)) . '</button>';
        }
        $out .= '</div>';

        foreach ($allLeagues as $idx => $leagueSlug) {
            $active = $idx === 0 ? ' is-active' : '';
            $out .= '<div class="opentt-league-tab-pane' . esc_attr($active) . '" data-tab-pane="' . esc_attr($leagueSlug) . '">';

            $clubsInLeague = self::collectLeagueClubIds($leagueSlug);

            $out .= '<section class="opentt-profile-subsection"><h4>Dodaj novu utakmicu</h4>';
            $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
            $out .= wp_nonce_field('opentt_front_add_league_match_' . $leagueSlug, '_wpnonce', true, false);
            $out .= '<input type="hidden" name="action" value="opentt_front_add_league_match">';
            $out .= '<input type="hidden" name="liga_slug" value="' . esc_attr($leagueSlug) . '">';
            $out .= '<label>Sezona (slug, npr. 2025-26)<input type="text" name="sezona_slug" required></label>';
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
            $out .= '</form>';
            $out .= '</section>';

            $matches = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$matchesTable} WHERE liga_slug=%s ORDER BY sezona_slug DESC, kolo_slug DESC, match_date DESC, id DESC LIMIT 200",
                $leagueSlug
            )) ?: [];

            $matchesBySeason = [];
            foreach ($matches as $row) {
                if (!is_object($row)) {
                    continue;
                }
                $seasonKey = sanitize_title((string) ($row->sezona_slug ?? ''));
                if ($seasonKey === '') {
                    $seasonKey = 'bez-sezone';
                }
                if (!isset($matchesBySeason[$seasonKey])) {
                    $matchesBySeason[$seasonKey] = [];
                }
                $matchesBySeason[$seasonKey][] = $row;
            }

            $out .= '<section class="opentt-profile-subsection"><h4>Utakmice lige</h4>';
            if (empty($matches)) {
                $out .= '<p>Nema utakmica za ovu ligu.</p>';
            } else {
                foreach ($matchesBySeason as $seasonSlug => $seasonMatches) {
                    if (!$isSuper && isset($allowedLeagueSeasonMap[$leagueSlug]) && !isset($allowedLeagueSeasonMap[$leagueSlug][$seasonSlug])) {
                        continue;
                    }
                    $seasonLabel = $seasonSlug === 'bez-sezone'
                        ? 'Bez sezone'
                        : str_replace('-', '/', (string) $seasonSlug);

                    $out .= '<div class="opentt-league-season-group">';
                    $out .= '<h5 class="opentt-league-season-title">Sezona ' . esc_html($seasonLabel) . '</h5>';
                    $out .= '<div class="opentt-league-matches-grid">';
                    foreach ($seasonMatches as $row) {
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
                        $maxGames = max(0, min(7, intval($row->home_score ?? 0) + intval($row->away_score ?? 0)));
                        if ($maxGames <= 0) {
                            $maxGames = 7;
                        }

                        $out .= '<details class="opentt-league-match-card">';
                        $out .= '<summary>';
                        $out .= '<span class="opentt-lm-top">' . esc_html(self::koloNameFromSlug((string) ($row->kolo_slug ?? ''))) . '</span>';
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
                        $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="played" value="1"' . checked(intval($row->played ?? 0), 1, false) . '> Odigrana</label>';
                        $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="live" value="1"' . checked(intval($row->live ?? 0), 1, false) . '> Uživo</label>';
                        $out .= '<div class="opentt-editor-media-row"><button type="submit" class="opentt-auth-btn">Sačuvaj izmene</button></div>';
                        $out .= '</form>';
                        $out .= self::renderLeagueMatchGamesForm($matchId, $homeId, $awayId, $maxGames);

                        $out .= '</details>';
                    }
                    $out .= '</div>';
                    $out .= '</div>';
                }
            }
            $out .= '</section>';

            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= "<script>(function(){document.querySelectorAll(\"[data-opentt-league-tabs='1']\").forEach(function(root){if(root.dataset.bound==='1'){return;}root.dataset.bound='1';var btns=root.querySelectorAll('.opentt-league-tab-btn');var panes=root.querySelectorAll('.opentt-league-tab-pane');btns.forEach(function(btn){btn.addEventListener('click',function(){var tab=String(btn.getAttribute('data-tab')||'');btns.forEach(function(b){b.classList.toggle('is-active',b===btn);});panes.forEach(function(p){p.classList.toggle('is-active',String(p.getAttribute('data-tab-pane')||'')===tab);});});});});})();</script>";
        $out .= '</section>';

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

        $out .= '<section class="opentt-profile-subsection"><h4>Podešavanje kluba</h4>';
        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
        $out .= wp_nonce_field('opentt_front_team_save_club_' . $clubId, '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_team_save_club">';
        $out .= '<input type="hidden" name="club_id" value="' . esc_attr((string) $clubId) . '">';
        $out .= '<label>Opis kluba<textarea name="post_content" rows="4">' . esc_textarea((string) $club->post_content) . '</textarea></label>';
        $out .= '<div class="opentt-inline-select-grid">';
        $out .= '<label>Grad<input type="text" name="grad" value="' . esc_attr((string) get_post_meta($clubId, 'grad', true)) . '"></label>';
        $out .= '<label>Kontakt<input type="text" name="kontakt" value="' . esc_attr((string) get_post_meta($clubId, 'kontakt', true)) . '"></label>';
        $out .= '<label>Email<input type="email" name="email" value="' . esc_attr((string) get_post_meta($clubId, 'email', true)) . '"></label>';
        $out .= '<label>Adresa sale<input type="text" name="adresa_sale" value="' . esc_attr((string) get_post_meta($clubId, 'adresa_sale', true)) . '"></label>';
        $out .= '<label>Termin igranja<input type="text" name="termin_igranja" value="' . esc_attr((string) get_post_meta($clubId, 'termin_igranja', true)) . '"></label>';
        $out .= '<label>Boja dresa<input type="text" name="boja_dresa" value="' . esc_attr((string) get_post_meta($clubId, 'boja_dresa', true)) . '" placeholder="#0b4db8"></label>';
        $out .= '</div>';
        $out .= '<button type="submit" class="opentt-auth-btn">Sačuvaj klub</button>';
        $out .= '</form></section>';

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

        $out .= '<section class="opentt-profile-subsection"><h4>Igrači kluba</h4>';
        if (empty($players)) {
            $out .= '<p>Nema unetih igrača.</p>';
        } else {
            $out .= '<ul class="opentt-team-player-list">';
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
        $out .= '</form></section></section>';

        return $out;
    }

    private static function renderPlayerSelect($name, array $options, $selectedId)
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

    private static function playersByClub($clubId)
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

    private static function expectedDoublesOrderByCompetition($ligaSlug, $sezonaSlug)
    {
        $ligaSlug = sanitize_title((string) $ligaSlug);
        $sezonaSlug = sanitize_title((string) $sezonaSlug);
        if ($ligaSlug === '' || $sezonaSlug === '') {
            return 4;
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
            return 4;
        }

        $format = sanitize_key((string) get_post_meta($ruleId, 'format_partija', true));
        return $format === 'format_b' ? 7 : 4;
    }

    private static function renderLeagueMatchGamesForm($matchId, $homeClubId, $awayClubId, $maxGames)
    {
        $matchId = intval($matchId);
        if ($matchId <= 0) {
            return '';
        }

        global $wpdb;
        $gamesTable = \OpenTT_Unified_Core::db_table('games');
        $setsTable = \OpenTT_Unified_Core::db_table('sets');
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!self::tableExists($gamesTable) || !self::tableExists($setsTable) || !self::tableExists($matchesTable)) {
            return '';
        }

        $match = $wpdb->get_row($wpdb->prepare("SELECT liga_slug,sezona_slug FROM {$matchesTable} WHERE id=%d LIMIT 1", $matchId));
        $expectedDoublesOrder = self::expectedDoublesOrderByCompetition(
            is_object($match) ? (string) ($match->liga_slug ?? '') : '',
            is_object($match) ? (string) ($match->sezona_slug ?? '') : ''
        );

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

        for ($orderNo = 1; $orderNo <= $maxGames; $orderNo++) {
            $game = isset($gamesByOrder[$orderNo]) ? $gamesByOrder[$orderNo] : null;
            $gameId = $game ? intval($game->id ?? 0) : 0;
            $isDoubles = $game ? intval($game->is_doubles ?? 0) === 1 : ($orderNo === $expectedDoublesOrder);
            $homeSets = $game ? intval($game->home_sets ?? 0) : 0;
            $awaySets = $game ? intval($game->away_sets ?? 0) : 0;
            $existingSets = ($gameId > 0 && isset($setsByGame[$gameId])) ? $setsByGame[$gameId] : [];

            $out .= '<div class="opentt-league-game-card">';
            $out .= '<div class="opentt-league-game-title">Partija #' . intval($orderNo) . ($isDoubles ? ' (Dubl)' : '') . '</div>';
            $out .= '<input type="hidden" name="games[' . intval($orderNo) . '][order_no]" value="' . esc_attr((string) $orderNo) . '">';
            $out .= '<input type="hidden" name="games[' . intval($orderNo) . '][game_id]" value="' . esc_attr((string) $gameId) . '">';
            $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="games[' . intval($orderNo) . '][is_doubles]" value="1"' . checked($isDoubles, true, false) . '> Dubl</label>';
            $out .= '<div class="opentt-inline-select-grid">';
            $out .= '<label>Domaći igrač' . self::renderPlayerSelect('games[' . intval($orderNo) . '][home_player_post_id]', $homePlayers, $game ? intval($game->home_player_post_id ?? 0) : 0) . '</label>';
            $out .= '<label>Gost igrač' . self::renderPlayerSelect('games[' . intval($orderNo) . '][away_player_post_id]', $awayPlayers, $game ? intval($game->away_player_post_id ?? 0) : 0) . '</label>';
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

        $out .= '<button type="submit" class="opentt-auth-btn">Sačuvaj partije</button>';
        $out .= '</form></section>';
        return $out;
    }

    private static function applyFrontGamesBatchForMatch($match, array $postedGames, &$error = '')
    {
        global $wpdb;
        $error = '';
        if (!is_object($match) || empty($match->id)) {
            $error = 'Utakmica nije pronađena.';
            return false;
        }

        $matchId = intval($match->id);
        $gamesTable = \OpenTT_Unified_Core::db_table('games');
        $setsTable = \OpenTT_Unified_Core::db_table('sets');
        if (!self::tableExists($gamesTable) || !self::tableExists($setsTable)) {
            $error = 'Tabela partija/setova nije dostupna.';
            return false;
        }

        $maxGames = max(0, min(7, intval($match->home_score ?? 0) + intval($match->away_score ?? 0)));
        if ($maxGames <= 0) {
            $maxGames = 7;
        }
        $expectedDoublesOrder = self::expectedDoublesOrderByCompetition((string) ($match->liga_slug ?? ''), (string) ($match->sezona_slug ?? ''));

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

    public static function handleFrontLogin()
    {
        check_admin_referer('opentt_front_login');

        if (\OpenTT_Unified_Core::is_turnstile_enabled()) {
            $token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field((string) wp_unslash($_POST['cf-turnstile-response'])) : '';
            if (!self::verifyTurnstileToken($token)) {
                wp_safe_redirect(self::frontendNoticeUrl(home_url('/prijava/'), 'error', 'Captcha verifikacija nije uspela.'));
                exit;
            }
        }

        $login = isset($_POST['log']) ? sanitize_text_field((string) wp_unslash($_POST['log'])) : '';
        $pwd = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
        $remember = !empty($_POST['remember']);

        $user = wp_signon([
            'user_login' => $login,
            'user_password' => $pwd,
            'remember' => $remember,
        ], is_ssl());

        if (is_wp_error($user)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/prijava/'), 'error', 'Neuspešna prijava. Proveri podatke.'));
            exit;
        }

        wp_safe_redirect(home_url('/profil/'));
        exit;
    }

    public static function handleFrontRegister()
    {
        check_admin_referer('opentt_front_register');

        if (get_option('users_can_register') !== '1') {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/prijava/'), 'error', 'Registracija je trenutno isključena.'));
            exit;
        }

        if (\OpenTT_Unified_Core::is_turnstile_enabled()) {
            $token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field((string) wp_unslash($_POST['cf-turnstile-response'])) : '';
            if (!self::verifyTurnstileToken($token)) {
                wp_safe_redirect(self::frontendNoticeUrl(home_url('/prijava/'), 'error', 'Captcha verifikacija nije uspela.'));
                exit;
            }
        }

        $login = isset($_POST['user_login']) ? sanitize_user((string) wp_unslash($_POST['user_login']), true) : '';
        $email = isset($_POST['user_email']) ? sanitize_email((string) wp_unslash($_POST['user_email'])) : '';
        $pass = isset($_POST['user_pass']) ? (string) wp_unslash($_POST['user_pass']) : '';

        if ($login === '' || !is_email($email) || strlen($pass) < 6) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/prijava/'), 'error', 'Proveri podatke registracije (lozinka min 6 karaktera).'));
            exit;
        }
        if (username_exists($login) || email_exists($email)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/prijava/'), 'error', 'Korisničko ime ili email već postoje.'));
            exit;
        }

        $userId = wp_create_user($login, $pass, $email);
        if (is_wp_error($userId) || intval($userId) <= 0) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/prijava/'), 'error', 'Registracija nije uspela.'));
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

        $featuredImageId = isset($_POST['featured_image_id']) ? intval($_POST['featured_image_id']) : 0;
        if ($featuredImageId > 0) {
            set_post_thumbnail($postId, $featuredImageId);
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
        if (!self::canManageLeague($userId, (string) ($row->liga_slug ?? ''), (string) ($row->sezona_slug ?? ''))) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za ovu ligu.'));
            exit;
        }

        $homeScore = max(0, intval($_POST['home_score'] ?? 0));
        $awayScore = max(0, intval($_POST['away_score'] ?? 0));
        $homeClubId = isset($_POST['home_club_post_id']) ? intval($_POST['home_club_post_id']) : intval($row->home_club_post_id ?? 0);
        $awayClubId = isset($_POST['away_club_post_id']) ? intval($_POST['away_club_post_id']) : intval($row->away_club_post_id ?? 0);
        if ($homeClubId <= 0 || $awayClubId <= 0 || $homeClubId === $awayClubId) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Proveri izbor domaćina i gosta.'));
            exit;
        }
        $seasonSlug = isset($_POST['sezona_slug']) ? sanitize_title((string) wp_unslash($_POST['sezona_slug'])) : sanitize_title((string) ($row->sezona_slug ?? ''));
        $roundSlug = isset($_POST['kolo_slug']) ? sanitize_title((string) wp_unslash($_POST['kolo_slug'])) : sanitize_title((string) ($row->kolo_slug ?? ''));
        if ($seasonSlug === '' || $roundSlug === '') {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Sezona i kolo su obavezni.'));
            exit;
        }
        $played = isset($_POST['played']) ? 1 : (($homeScore >= 4 || $awayScore >= 4) ? 1 : 0);
        $live = isset($_POST['live']) ? 1 : 0;
        if ($played === 1) {
            $live = 0;
        }
        $matchDate = self::normalizeMatchDate((string) wp_unslash($_POST['match_date'] ?? (string) ($row->match_date ?? '')));
        $location = sanitize_text_field((string) wp_unslash($_POST['location'] ?? (string) ($row->location ?? '')));

        $wpdb->update($matchesTable, [
            'sezona_slug' => $seasonSlug,
            'kolo_slug' => $roundSlug,
            'home_club_post_id' => $homeClubId,
            'away_club_post_id' => $awayClubId,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'played' => $played,
            'live' => $live,
            'match_date' => $matchDate,
            'location' => $location,
            'updated_at' => current_time('mysql'),
        ], ['id' => $matchId]);

        wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'success', 'Utakmica je sačuvana.'));
        exit;
    }

    public static function handleFrontSaveLeagueGames()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $matchId = isset($_POST['match_id']) ? intval($_POST['match_id']) : 0;
        if ($matchId <= 0) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nedostaje ID utakmice za partije.'));
            exit;
        }
        check_admin_referer('opentt_front_save_league_games_' . $matchId);

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!self::tableExists($matchesTable)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Tabela utakmica nije dostupna.'));
            exit;
        }
        $match = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matchesTable} WHERE id=%d LIMIT 1", $matchId));
        if (!$match || !is_object($match)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Utakmica nije pronađena.'));
            exit;
        }
        $userId = get_current_user_id();
        if (!self::canManageLeague($userId, (string) ($match->liga_slug ?? ''), (string) ($match->sezona_slug ?? ''))) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za unos partija u ovoj ligi.'));
            exit;
        }

        $postedGames = isset($_POST['games']) && is_array($_POST['games']) ? (array) $_POST['games'] : [];
        $error = '';
        if (!self::applyFrontGamesBatchForMatch($match, $postedGames, $error)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/?opentt_profile_tab=league'), 'error', $error !== '' ? $error : 'Greška pri čuvanju partija.'));
            exit;
        }

        wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/?opentt_profile_tab=league'), 'success', 'Partije su sačuvane.'));
        exit;
    }

    public static function handleFrontAddLeagueMatch()
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $leagueSlug = isset($_POST['liga_slug']) ? sanitize_title((string) wp_unslash($_POST['liga_slug'])) : '';
        check_admin_referer('opentt_front_add_league_match_' . $leagueSlug);

        if ($leagueSlug === '') {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Liga je obavezna.'));
            exit;
        }

        $userId = get_current_user_id();
        $seasonSlug = isset($_POST['sezona_slug']) ? sanitize_title((string) wp_unslash($_POST['sezona_slug'])) : '';
        $roundSlug = isset($_POST['kolo_slug']) ? sanitize_title((string) wp_unslash($_POST['kolo_slug'])) : '';
        $homeClubId = isset($_POST['home_club_post_id']) ? intval($_POST['home_club_post_id']) : 0;
        $awayClubId = isset($_POST['away_club_post_id']) ? intval($_POST['away_club_post_id']) : 0;
        $location = sanitize_text_field((string) wp_unslash($_POST['location'] ?? ''));
        $matchDate = self::normalizeMatchDate((string) wp_unslash($_POST['match_date'] ?? ''));

        if ($seasonSlug === '' || $roundSlug === '' || $homeClubId <= 0 || $awayClubId <= 0 || $homeClubId === $awayClubId) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Proveri obavezna polja za novu utakmicu.'));
            exit;
        }
        if (!self::canManageLeague($userId, $leagueSlug, $seasonSlug)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za ovu ligu/sezonu.'));
            exit;
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        if (!self::tableExists($matchesTable)) {
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Tabela utakmica nije dostupna.'));
            exit;
        }

        $baseSlug = sanitize_title((string) get_the_title($homeClubId) . '-' . (string) get_the_title($awayClubId));
        if ($baseSlug === '') {
            $baseSlug = 'utakmica';
        }
        $slug = $baseSlug;
        for ($i = 0; $i < 50; $i++) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$matchesTable} WHERE liga_slug=%s AND sezona_slug=%s AND kolo_slug=%s AND slug=%s LIMIT 1",
                $leagueSlug,
                $seasonSlug,
                $roundSlug,
                $slug
            ));
            if (!$exists) {
                break;
            }
            $slug = $baseSlug . '-' . ($i + 2);
        }

        $ok = $wpdb->insert($matchesTable, [
            'slug' => $slug,
            'liga_slug' => $leagueSlug,
            'sezona_slug' => $seasonSlug,
            'kolo_slug' => $roundSlug,
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
            wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'error', 'Dodavanje utakmice nije uspelo.'));
            exit;
        }

        wp_safe_redirect(self::frontendNoticeUrl(home_url('/profil/'), 'success', 'Nova utakmica je dodata.'));
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

    private static function verifyTurnstileToken($token)
    {
        $token = trim((string) $token);
        $secret = trim((string) \OpenTT_Unified_Core::turnstile_secret_key());
        if ($secret === '' || $token === '') {
            return false;
        }

        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'timeout' => 15,
            'body' => [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        if ($code < 200 || $code >= 300) {
            return false;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) && !empty($body['success']);
    }

    private static function normalizeMatchDate($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return current_time('mysql');
        }
        $tz = function_exists('wp_timezone') ? wp_timezone() : null;
        if ($tz instanceof \DateTimeZone) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw, $tz);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
            $dt = \DateTime::createFromFormat('Y-m-d H:i', $raw, $tz);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
            $dt = \DateTime::createFromFormat('Y-m-d', $raw, $tz);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d 00:00:00');
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return gmdate('Y-m-d H:i:s', intval($ts) + (int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS));
        }
        return current_time('mysql');
    }

    private static function collectLeagueClubIds($leagueSlug)
    {
        $leagueSlug = sanitize_title((string) $leagueSlug);
        if ($leagueSlug === '') {
            return [];
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        $ids = [];
        if (self::tableExists($matchesTable)) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT home_club_post_id, away_club_post_id FROM {$matchesTable} WHERE liga_slug=%s",
                $leagueSlug
            )) ?: [];
            foreach ($rows as $row) {
                if (!is_object($row)) {
                    continue;
                }
                $h = intval($row->home_club_post_id ?? 0);
                $a = intval($row->away_club_post_id ?? 0);
                if ($h > 0) {
                    $ids[$h] = true;
                }
                if ($a > 0) {
                    $ids[$a] = true;
                }
            }
        }

        if (empty($ids)) {
            $all = get_posts([
                'post_type' => 'klub',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'orderby' => 'title',
                'order' => 'ASC',
            ]) ?: [];
            foreach ($all as $cid) {
                $cid = intval($cid);
                if ($cid > 0) {
                    $ids[$cid] = true;
                }
            }
        }

        $out = array_map('intval', array_keys($ids));
        sort($out, SORT_NUMERIC);
        return $out;
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
        if (preg_match('/(\d+)/', $slug, $m)) {
            return $m[1] . '. kolo';
        }
        return self::slugToTitle($slug);
    }
}
