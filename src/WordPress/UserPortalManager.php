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
        add_role(self::ROLE_MEMBER, 'LibreTT Član', [
            'read' => true,
            'upload_files' => true,
        ]);

        add_role(self::ROLE_LEAGUE_ADMIN, 'LibreTT Administrator lige', [
            'read' => true,
            'upload_files' => true,
        ]);

        add_role(self::ROLE_TEAM_MANAGER, 'LibreTT Menadžer tima', [
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
        UserPortalUsersAdminService::renderUsersAdminPage($capability, [
            'tableExists' => static function ($tableName) {
                return self::tableExists($tableName);
            },
            'getPrimaryRole' => static function ($user) {
                return self::getPrimaryRole($user);
            },
            'getUserLinkedPlayerId' => static function ($userId) {
                return self::getUserLinkedPlayerId($userId);
            },
            'getUserManagedLeagueSeasons' => static function ($userId) {
                return self::getUserManagedLeagueSeasons($userId);
            },
            'getUserManagedClubId' => static function ($userId) {
                return self::getUserManagedClubId($userId);
            },
            'assignableRoleOptions' => static function () {
                return self::assignableRoleOptions();
            },
            'slugToTitle' => static function ($slug) {
                return self::slugToTitle($slug);
            },
        ]);
    }

    public static function handleSaveUserAccess($capability)
    {
        UserPortalUsersAdminService::handleSaveUserAccess($capability, [
            'adminNoticeUrl' => static function ($url, $type, $message) {
                return self::adminNoticeUrl($url, $type, $message);
            },
            'assignableRoleOptions' => static function () {
                return self::assignableRoleOptions();
            },
            'metaLeagues' => self::META_LEAGUES,
            'metaManagerClub' => self::META_MANAGER_CLUB,
            'metaLinkedPlayer' => self::META_LINKED_PLAYER,
            'roleMember' => self::ROLE_MEMBER,
        ]);
    }

    public static function renderAuthShortcode()
    {
        return UserPortalAuthService::renderAuthShortcode([
            'renderFrontendNotice' => static function () {
                return self::renderFrontendNotice();
            },
        ]);
    }

    public static function renderAuthMenuShortcode()
    {
        return UserPortalAuthService::renderAuthMenuShortcode([
            'profileAvatarUrl' => static function ($userId, $size = 96) {
                return UserPortalUtilityService::profileAvatarUrl($userId, $size, self::META_PROFILE_AVATAR_ID);
            },
        ]);
    }

    public static function renderProfileShortcode()
    {
        return UserPortalAuthService::renderProfileShortcode([
            'renderFrontendNotice' => static function () {
                return self::renderFrontendNotice();
            },
            'getPrimaryRole' => static function ($user) {
                return self::getPrimaryRole($user);
            },
            'roleLabelBySlug' => static function ($role) {
                return self::roleLabelBySlug($role);
            },
            'getUserLinkedPlayerId' => static function ($userId) {
                return self::getUserLinkedPlayerId($userId);
            },
            'profileAvatarUrl' => static function ($userId, $size = 96) {
                return UserPortalUtilityService::profileAvatarUrl($userId, $size, self::META_PROFILE_AVATAR_ID);
            },
            'renderEditorTools' => static function ($userId) {
                return UserPortalEditorViewService::renderEditorTools($userId);
            },
            'renderEditorPosts' => static function ($userId) {
                return UserPortalEditorViewService::renderEditorPosts($userId);
            },
            'renderLeagueAdminTools' => static function ($userId) {
                return self::renderLeagueAdminTools($userId);
            },
            'renderTeamManagerTools' => static function ($userId) {
                return UserPortalTeamManagerViewService::renderTeamManagerTools($userId, [
                    'getUserManagedClubId' => static function ($uid) {
                        return self::getUserManagedClubId($uid);
                    },
                    'canManageAsAdmin' => static function ($uid) {
                        return user_can($uid, 'administrator') || user_can($uid, \OpenTT_Unified_Core::CAP);
                    },
                ]);
            },
        ]);
    }

    private static function renderLeagueAdminTools($userId)
    {
        return UserPortalLeagueAdminViewService::renderLeagueAdminTools($userId, [
            'getUserManagedLeagues' => static function ($uid) {
                return self::getUserManagedLeagues($uid);
            },
            'getUserManagedLeagueSeasons' => static function ($uid) {
                return self::getUserManagedLeagueSeasons($uid);
            },
            'tableExists' => static function ($tableName) {
                return self::tableExists($tableName);
            },
            'slugToTitle' => static function ($slug) {
                return self::slugToTitle($slug);
            },
            'koloNameFromSlug' => static function ($slug) {
                return self::koloNameFromSlug($slug);
            },
            'collectLeagueClubIds' => static function ($leagueSlug) {
                return UserPortalUtilityService::collectLeagueClubIds($leagueSlug, static function ($tableName) {
                    return self::tableExists($tableName);
                });
            },
            'renderLeagueMatchGamesForm' => static function ($matchId, $homeClubId, $awayClubId, $maxGames) {
                return self::renderLeagueMatchGamesForm($matchId, $homeClubId, $awayClubId, $maxGames);
            },
        ]);
    }
    private static function renderPlayerSelect($name, array $options, $selectedId)
    {
        return UserPortalLeagueGamesService::renderPlayerSelect($name, $options, $selectedId);
    }

    private static function playersByClub($clubId)
    {
        return UserPortalLeagueGamesService::playersByClub($clubId);
    }

    private static function expectedDoublesOrderByCompetition($ligaSlug, $sezonaSlug)
    {
        return UserPortalLeagueGamesService::expectedDoublesOrderByCompetition($ligaSlug, $sezonaSlug);
    }

    private static function renderLeagueMatchGamesForm($matchId, $homeClubId, $awayClubId, $maxGames)
    {
        return UserPortalLeagueGamesService::renderLeagueMatchGamesForm($matchId, $homeClubId, $awayClubId, $maxGames, [
            'tableExists' => static function ($tableName) {
                return self::tableExists($tableName);
            },
        ]);
    }

    private static function applyFrontGamesBatchForMatch($match, array $postedGames, &$error = '')
    {
        return UserPortalLeagueGamesService::applyFrontGamesBatchForMatch($match, $postedGames, $error, [
            'tableExists' => static function ($tableName) {
                return self::tableExists($tableName);
            },
        ]);
    }

    public static function handleFrontLogin()
    {
        check_admin_referer('opentt_front_login');

        if (\OpenTT_Unified_Core::is_turnstile_enabled()) {
            $token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field((string) wp_unslash($_POST['cf-turnstile-response'])) : '';
            if (!UserPortalUtilityService::verifyTurnstileToken($token)) {
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
        UserPortalAuthService::handleFrontRegister([
            'frontendNoticeUrl' => static function ($baseUrl, $status, $message) {
                return self::frontendNoticeUrl($baseUrl, $status, $message);
            },
            'verifyTurnstileToken' => static function ($token) {
                return UserPortalUtilityService::verifyTurnstileToken($token);
            },
        ]);
    }

    public static function handleFrontProfileUpdate()
    {
        UserPortalAuthService::handleFrontProfileUpdate([
            'frontendNoticeUrl' => static function ($baseUrl, $status, $message) {
                return self::frontendNoticeUrl($baseUrl, $status, $message);
            },
            'metaProfileAvatarId' => self::META_PROFILE_AVATAR_ID,
        ]);
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
        UserPortalLeagueMatchService::handleFrontSaveLeagueMatch([
            'frontendNoticeUrl' => static function ($baseUrl, $status, $message) {
                return self::frontendNoticeUrl($baseUrl, $status, $message);
            },
            'tableExists' => static function ($tableName) {
                return self::tableExists($tableName);
            },
            'canManageLeague' => static function ($userId, $leagueSlug, $seasonSlug = '') {
                return self::canManageLeague($userId, $leagueSlug, $seasonSlug);
            },
            'normalizeMatchDate' => static function ($raw) {
                return UserPortalUtilityService::normalizeMatchDate($raw);
            },
        ]);
    }

    public static function handleFrontSaveLeagueGames()
    {
        UserPortalLeagueMatchService::handleFrontSaveLeagueGames([
            'frontendNoticeUrl' => static function ($baseUrl, $status, $message) {
                return self::frontendNoticeUrl($baseUrl, $status, $message);
            },
            'tableExists' => static function ($tableName) {
                return self::tableExists($tableName);
            },
            'canManageLeague' => static function ($userId, $leagueSlug, $seasonSlug = '') {
                return self::canManageLeague($userId, $leagueSlug, $seasonSlug);
            },
            'applyFrontGamesBatchForMatch' => static function ($match, array $postedGames, &$error = '') {
                return self::applyFrontGamesBatchForMatch($match, $postedGames, $error);
            },
        ]);
    }

    public static function handleFrontAddLeagueMatch()
    {
        UserPortalLeagueMatchService::handleFrontAddLeagueMatch([
            'frontendNoticeUrl' => static function ($baseUrl, $status, $message) {
                return self::frontendNoticeUrl($baseUrl, $status, $message);
            },
            'tableExists' => static function ($tableName) {
                return self::tableExists($tableName);
            },
            'canManageLeague' => static function ($userId, $leagueSlug, $seasonSlug = '') {
                return self::canManageLeague($userId, $leagueSlug, $seasonSlug);
            },
            'normalizeMatchDate' => static function ($raw) {
                return UserPortalUtilityService::normalizeMatchDate($raw);
            },
        ]);
    }

    public static function handleFrontTeamSaveClub()
    {
        UserPortalTeamService::handleFrontTeamSaveClub([
            'frontendNoticeUrl' => static function ($baseUrl, $status, $message) {
                return self::frontendNoticeUrl($baseUrl, $status, $message);
            },
            'canManageClub' => static function ($userId, $clubId) {
                return self::canManageClub($userId, $clubId);
            },
        ]);
    }

    public static function handleFrontTeamSavePlayer()
    {
        UserPortalTeamService::handleFrontTeamSavePlayer([
            'frontendNoticeUrl' => static function ($baseUrl, $status, $message) {
                return self::frontendNoticeUrl($baseUrl, $status, $message);
            },
            'canManageClub' => static function ($userId, $clubId) {
                return self::canManageClub($userId, $clubId);
            },
        ]);
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
