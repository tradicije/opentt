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

final class UserPortalAuthService
{
    public static function renderAuthShortcode($deps = [])
    {
        $renderFrontendNotice = $deps['renderFrontendNotice'];

        $notice = $renderFrontendNotice();
        $turnstileEnabled = (bool) \OpenTT_Unified_Core::is_turnstile_enabled();
        $turnstileSiteKey = trim((string) \OpenTT_Unified_Core::turnstile_site_key());
        $turnstileOk = $turnstileEnabled && $turnstileSiteKey !== '';
        $authLogo = (string) plugins_url('assets/img/club-logo.png', dirname(__DIR__, 2) . '/opentt-unified-core.php');
        $homeUrl = home_url('/');

        if (is_user_logged_in()) {
            $profileUrl = home_url('/profil/');
            return $notice . '<section class="opentt-auth-card"><div class="opentt-auth-logo-wrap"><a href="' . esc_url($homeUrl) . '" aria-label="Početna"><img src="' . esc_url($authLogo) . '" alt="LibreTT" class="opentt-auth-logo"></a></div><p>Već si prijavljen.</p><p><a class="opentt-auth-btn" href="' . esc_url($profileUrl) . '">Idi na profil</a> <a class="opentt-auth-btn is-ghost" href="' . esc_url(wp_logout_url(home_url('/prijava/'))) . '">Odjavi se</a></p></section>';
        }

        $registerEnabled = get_option('users_can_register') === '1';
        $out = '<section class="opentt-auth-card opentt-auth-switcher" data-opentt-auth="1">';
        $out .= '<div class="opentt-auth-logo-wrap"><a href="' . esc_url($homeUrl) . '" aria-label="Početna"><img src="' . esc_url($authLogo) . '" alt="LibreTT" class="opentt-auth-logo"></a></div>';
        $out .= '<div class="opentt-auth-pane is-active" data-pane="login">';
        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
        $out .= wp_nonce_field('opentt_front_login', '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_login">';
        $out .= '<label>Korisničko ime ili email<input type="text" name="log" required></label>';
        $out .= '<label>Lozinka<input type="password" name="pwd" required></label>';
        $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="remember" value="1"> Zapamti me</label>';
        if ($turnstileOk) {
            $out .= '<div class="opentt-turnstile-wrap"><div class="cf-turnstile" data-sitekey="' . esc_attr($turnstileSiteKey) . '" data-theme="dark" data-size="flexible"></div></div>';
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
            $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form">';
            $out .= wp_nonce_field('opentt_front_register', '_wpnonce', true, false);
            $out .= '<input type="hidden" name="action" value="opentt_front_register">';
            $out .= '<label>Korisničko ime<input type="text" name="user_login" required></label>';
            $out .= '<label>Email<input type="email" name="user_email" required></label>';
            $out .= '<label>Lozinka<input type="password" name="user_pass" required></label>';
            if ($turnstileOk) {
                $out .= '<div class="opentt-turnstile-wrap"><div class="cf-turnstile" data-sitekey="' . esc_attr($turnstileSiteKey) . '" data-theme="dark" data-size="flexible"></div></div>';
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

    public static function renderAuthMenuShortcode($deps = [])
    {
        $profileAvatarUrl = $deps['profileAvatarUrl'];

        if (!is_user_logged_in()) {
            $iconUrl = (string) plugins_url('assets/icons/login-icon.svg', dirname(__DIR__, 2) . '/opentt-unified-core.php');
            return '<div class="opentt-auth-menu"><a class="opentt-auth-menu-login" href="' . esc_url(home_url('/prijava/')) . '" aria-label="Prijava" style="--opentt-login-icon:url(' . esc_url($iconUrl) . ');"><span class="opentt-auth-menu-login-icon" aria-hidden="true"></span></a></div>';
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        if (!$user) {
            $iconUrl = (string) plugins_url('assets/icons/login-icon.svg', dirname(__DIR__, 2) . '/opentt-unified-core.php');
            return '<div class="opentt-auth-menu"><a class="opentt-auth-menu-login" href="' . esc_url(home_url('/prijava/')) . '" aria-label="Prijava" style="--opentt-login-icon:url(' . esc_url($iconUrl) . ');"><span class="opentt-auth-menu-login-icon" aria-hidden="true"></span></a></div>';
        }

        $avatar = $profileAvatarUrl($userId, 64);
        $profileUrl = home_url('/profil/');
        $canLeague = user_can($userId, UserPortalManager::ROLE_LEAGUE_ADMIN) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP);
        $canEditor = user_can($userId, 'editor') || user_can($userId, 'administrator');
        $canTeam = user_can($userId, UserPortalManager::ROLE_TEAM_MANAGER) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP);

        $out = '<div class="opentt-auth-menu">';
        $out .= '<a class="opentt-auth-menu-toggle" href="' . esc_url($profileUrl) . '" aria-label="Profil">';
        $out .= '<img src="' . esc_url($avatar) . '" alt="' . esc_attr((string) $user->display_name) . '">';
        $out .= '</a>';
        $out .= '<div class="opentt-auth-menu-dropdown">';
        $out .= '<a class="opentt-auth-menu-link" href="' . esc_url($profileUrl) . '">Izmeni profil</a>';
        if ($canLeague) {
            $out .= '<a class="opentt-auth-menu-link" href="' . esc_url(add_query_arg('opentt_profile_tab', 'league', $profileUrl)) . '">Administracija lige</a>';
        }
        if ($canEditor) {
            $out .= '<a class="opentt-auth-menu-link" href="' . esc_url(add_query_arg('opentt_profile_tab', 'editor', $profileUrl)) . '">Urednički portal</a>';
        }
        if ($canTeam) {
            $out .= '<a class="opentt-auth-menu-link" href="' . esc_url(add_query_arg('opentt_profile_tab', 'team', $profileUrl)) . '">Menadžer tima</a>';
        }
        $out .= '<a class="opentt-auth-menu-link is-logout" href="' . esc_url(wp_logout_url(home_url('/prijava/'))) . '">Odjavi se</a>';
        $out .= '</div>';
        $out .= '</div>';

        return $out;
    }

    public static function renderProfileShortcode($deps = [])
    {
        $renderFrontendNotice = $deps['renderFrontendNotice'];
        $getPrimaryRole = $deps['getPrimaryRole'];
        $roleLabelBySlug = $deps['roleLabelBySlug'];
        $getUserLinkedPlayerId = $deps['getUserLinkedPlayerId'];
        $profileAvatarUrl = $deps['profileAvatarUrl'];
        $renderEditorTools = $deps['renderEditorTools'];
        $renderEditorPosts = $deps['renderEditorPosts'];
        $renderLeagueAdminTools = $deps['renderLeagueAdminTools'];
        $renderTeamManagerTools = $deps['renderTeamManagerTools'];

        $notice = $renderFrontendNotice();
        if (!is_user_logged_in()) {
            return $notice . '<section class="opentt-profile-card"><p>Moraš biti prijavljen da bi pristupio profilu. <a href="' . esc_url(home_url('/prijava/')) . '">Prijava</a></p></section>';
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        if (!$user) {
            return $notice . '<section class="opentt-profile-card"><p>Korisnik nije pronađen.</p></section>';
        }

        wp_enqueue_media();

        $primaryRole = $getPrimaryRole($user);
        $roleLabel = $roleLabelBySlug($primaryRole);
        $linkedPlayerId = $getUserLinkedPlayerId($userId);
        $profileAvatar = $profileAvatarUrl($userId, 128);

        $requestedTab = isset($_GET['opentt_profile_tab']) ? sanitize_key((string) wp_unslash($_GET['opentt_profile_tab'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $allowedTabs = ['profile' => true];
        if (user_can($userId, 'editor') || user_can($userId, 'administrator')) {
            $allowedTabs['editor'] = true;
        }
        if (user_can($userId, UserPortalManager::ROLE_LEAGUE_ADMIN) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
            $allowedTabs['league'] = true;
        }
        if (user_can($userId, UserPortalManager::ROLE_TEAM_MANAGER) || user_can($userId, 'administrator') || user_can($userId, \OpenTT_Unified_Core::CAP)) {
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
            $out .= $renderEditorTools($userId);
            $out .= $renderEditorPosts($userId);
            $out .= '</div>';
        }

        if (isset($allowedTabs['league'])) {
            $out .= '<div class="opentt-profile-tab-pane' . ($activeTab === 'league' ? ' is-active' : '') . '" data-tab-pane="league">';
            $out .= $renderLeagueAdminTools($userId);
            $out .= '</div>';
        }

        if (isset($allowedTabs['team'])) {
            $out .= '<div class="opentt-profile-tab-pane' . ($activeTab === 'team' ? ' is-active' : '') . '" data-tab-pane="team">';
            $out .= $renderTeamManagerTools($userId);
            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= '</div>';

        return $notice . $out;
    }

    public static function handleFrontRegister($deps = [])
    {
        $frontendNoticeUrl = $deps['frontendNoticeUrl'];
        $verifyTurnstileToken = $deps['verifyTurnstileToken'];

        check_admin_referer('opentt_front_register');

        if (get_option('users_can_register') !== '1') {
            wp_safe_redirect($frontendNoticeUrl(home_url('/prijava/'), 'error', 'Registracija je trenutno isključena.'));
            exit;
        }

        if (\OpenTT_Unified_Core::is_turnstile_enabled()) {
            $token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field((string) wp_unslash($_POST['cf-turnstile-response'])) : '';
            if (!$verifyTurnstileToken($token)) {
                wp_safe_redirect($frontendNoticeUrl(home_url('/prijava/'), 'error', 'Captcha verifikacija nije uspela.'));
                exit;
            }
        }

        $login = isset($_POST['user_login']) ? sanitize_user((string) wp_unslash($_POST['user_login']), true) : '';
        $email = isset($_POST['user_email']) ? sanitize_email((string) wp_unslash($_POST['user_email'])) : '';
        $pass = isset($_POST['user_pass']) ? (string) wp_unslash($_POST['user_pass']) : '';

        if ($login === '' || !is_email($email) || strlen($pass) < 6) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/prijava/'), 'error', 'Proveri podatke registracije (lozinka min 6 karaktera).'));
            exit;
        }
        if (username_exists($login) || email_exists($email)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/prijava/'), 'error', 'Korisničko ime ili email već postoje.'));
            exit;
        }

        $userId = wp_create_user($login, $pass, $email);
        if (is_wp_error($userId) || intval($userId) <= 0) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/prijava/'), 'error', 'Registracija nije uspela.'));
            exit;
        }

        $user = get_userdata((int) $userId);
        if ($user instanceof \WP_User) {
            $user->set_role(UserPortalManager::ROLE_MEMBER);
        }

        wp_set_current_user((int) $userId);
        wp_set_auth_cookie((int) $userId, true, is_ssl());
        wp_safe_redirect(home_url('/profil/'));
        exit;
    }

    public static function handleFrontProfileUpdate($deps = [])
    {
        $frontendNoticeUrl = $deps['frontendNoticeUrl'];
        $metaProfileAvatarId = (string) ($deps['metaProfileAvatarId'] ?? '');

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
            if (!is_wp_error($attachmentId) && intval($attachmentId) > 0 && $metaProfileAvatarId !== '') {
                update_user_meta($userId, $metaProfileAvatarId, intval($attachmentId));
            }
        }

        wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'success', 'Profil je sačuvan.'));
        exit;
    }
}
