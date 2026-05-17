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

final class UserPortalTeamService
{
    public static function handleFrontTeamSaveClub($deps = [])
    {
        $frontendNoticeUrl = $deps['frontendNoticeUrl'];
        $canManageClub = $deps['canManageClub'];

        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $clubId = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
        if ($clubId <= 0) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nedostaje klub.'));
            exit;
        }
        check_admin_referer('opentt_front_team_save_club_' . $clubId);

        $userId = get_current_user_id();
        if (!$canManageClub($userId, $clubId)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za taj klub.'));
            exit;
        }

        $post = get_post($clubId);
        if (!($post instanceof \WP_Post) || $post->post_type !== 'klub') {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Klub nije pronađen.'));
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

        $removeCover = isset($_POST['opentt_club_featured_image_remove']) && intval($_POST['opentt_club_featured_image_remove']) === 1;
        $coverId = isset($_POST['opentt_club_featured_image_id']) ? intval($_POST['opentt_club_featured_image_id']) : 0;
        if ($removeCover) {
            delete_post_meta($clubId, 'opentt_club_featured_image_id');
        } else {
            if (
                isset($_FILES['opentt_club_featured_image_file']) &&
                is_array($_FILES['opentt_club_featured_image_file']) &&
                !empty($_FILES['opentt_club_featured_image_file']['name']) &&
                intval($_FILES['opentt_club_featured_image_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';

                $upload = wp_handle_upload($_FILES['opentt_club_featured_image_file'], ['test_form' => false]);
                if (is_array($upload) && empty($upload['error']) && !empty($upload['file'])) {
                    $attachment = [
                        'post_mime_type' => isset($upload['type']) ? (string) $upload['type'] : 'image/jpeg',
                        'post_title' => sanitize_text_field(pathinfo((string) ($upload['file'] ?? ''), PATHINFO_FILENAME)),
                        'post_content' => '',
                        'post_status' => 'inherit',
                    ];
                    $attach_id = wp_insert_attachment($attachment, (string) $upload['file'], $clubId);
                    if (!is_wp_error($attach_id) && intval($attach_id) > 0) {
                        $attach_id = intval($attach_id);
                        $meta = wp_generate_attachment_metadata($attach_id, (string) $upload['file']);
                        if (is_array($meta)) {
                            wp_update_attachment_metadata($attach_id, $meta);
                        }
                        update_post_meta($clubId, 'opentt_club_featured_image_id', $attach_id);
                    }
                }
            } elseif ($coverId > 0) {
                update_post_meta($clubId, 'opentt_club_featured_image_id', $coverId);
            } else {
                delete_post_meta($clubId, 'opentt_club_featured_image_id');
            }
        }

        $focusX = isset($_POST['opentt_club_featured_focus_x']) ? floatval($_POST['opentt_club_featured_focus_x']) : 50.0;
        $focusY = isset($_POST['opentt_club_featured_focus_y']) ? floatval($_POST['opentt_club_featured_focus_y']) : 50.0;
        $focusX = max(0.0, min(100.0, $focusX));
        $focusY = max(0.0, min(100.0, $focusY));
        update_post_meta($clubId, 'opentt_club_featured_focus_x', $focusX);
        update_post_meta($clubId, 'opentt_club_featured_focus_y', $focusY);

        wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'success', 'Klub je sačuvan.'));
        exit;
    }

    public static function handleFrontTeamSavePlayer($deps = [])
    {
        $frontendNoticeUrl = $deps['frontendNoticeUrl'];
        $canManageClub = $deps['canManageClub'];

        if (!is_user_logged_in()) {
            wp_safe_redirect(home_url('/prijava/'));
            exit;
        }

        $clubId = isset($_POST['club_id']) ? intval($_POST['club_id']) : 0;
        if ($clubId <= 0) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nedostaje klub.'));
            exit;
        }
        check_admin_referer('opentt_front_team_save_player_' . $clubId);

        $userId = get_current_user_id();
        if (!$canManageClub($userId, $clubId)) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Nemaš dozvolu za taj klub.'));
            exit;
        }

        $playerName = isset($_POST['player_name']) ? sanitize_text_field((string) wp_unslash($_POST['player_name'])) : '';
        if ($playerName === '') {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Ime igrača je obavezno.'));
            exit;
        }

        $playerId = wp_insert_post([
            'post_type' => 'igrac',
            'post_status' => 'publish',
            'post_title' => $playerName,
            'post_content' => '',
        ]);
        if (is_wp_error($playerId) || intval($playerId) <= 0) {
            wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'error', 'Dodavanje igrača nije uspelo.'));
            exit;
        }

        update_post_meta($playerId, 'povezani_klub', $clubId);
        update_post_meta($playerId, 'klub_igraca', $clubId);
        update_post_meta($playerId, 'datum_rodjenja', sanitize_text_field((string) wp_unslash($_POST['datum_rodjenja'] ?? '')));
        update_post_meta($playerId, 'drzavljanstvo', 'RS');

        wp_safe_redirect($frontendNoticeUrl(home_url('/profil/'), 'success', 'Igrač je dodat.'));
        exit;
    }
}
