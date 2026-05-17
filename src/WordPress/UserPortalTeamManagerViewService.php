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

final class UserPortalTeamManagerViewService
{
    public static function renderTeamManagerTools($userId, $deps = [])
    {
        $getUserManagedClubId = $deps['getUserManagedClubId'];
        $canManageAsAdmin = $deps['canManageAsAdmin'];

        $clubId = intval($getUserManagedClubId($userId));
        if ($clubId <= 0 && !$canManageAsAdmin($userId)) {
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
        $out .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-auth-form" enctype="multipart/form-data">';
        $out .= wp_nonce_field('opentt_front_team_save_club_' . $clubId, '_wpnonce', true, false);
        $out .= '<input type="hidden" name="action" value="opentt_front_team_save_club">';
        $out .= '<input type="hidden" name="club_id" value="' . esc_attr((string) $clubId) . '">';
        $coverId = intval(get_post_meta($clubId, 'opentt_club_featured_image_id', true));
        $coverUrl = $coverId > 0 ? (string) wp_get_attachment_image_url($coverId, 'full') : '';
        $focusXRaw = floatval(get_post_meta($clubId, 'opentt_club_featured_focus_x', true));
        $focusYRaw = floatval(get_post_meta($clubId, 'opentt_club_featured_focus_y', true));
        $focusX = ($focusXRaw >= 0 && $focusXRaw <= 100) ? $focusXRaw : 50.0;
        $focusY = ($focusYRaw >= 0 && $focusYRaw <= 100) ? $focusYRaw : 50.0;
        $coverPreview = $coverId > 0 ? wp_get_attachment_image($coverId, 'large') : '';
        $out .= '<label>Cover slika kluba <small>(preporuka: 2400x750 px, isti 16:5 banner prikaz na desktopu i mobilnom)</small></label>';
        $out .= '<div class="opentt-editor-featured-preview opentt-club-cover-current" id="opentt_team_club_cover_preview_' . esc_attr((string) $clubId) . '">' . ($coverPreview !== '' ? $coverPreview : '<div style="width:100%;max-width:360px;height:140px;background:#0a1f4f;border:1px dashed rgba(142,197,255,0.35);display:flex;align-items:center;justify-content:center;border-radius:10px;color:#d8e9ff;">Nema cover slike</div>') . '</div>';
        $out .= '<input type="hidden" name="opentt_club_featured_image_id" value="' . esc_attr((string) $coverId) . '">';
        $out .= '<input type="hidden" name="opentt_club_featured_focus_x" id="opentt_team_club_focus_x_' . esc_attr((string) $clubId) . '" value="' . esc_attr((string) $focusX) . '">';
        $out .= '<input type="hidden" name="opentt_club_featured_focus_y" id="opentt_team_club_focus_y_' . esc_attr((string) $clubId) . '" value="' . esc_attr((string) $focusY) . '">';
        $out .= '<input type="file" name="opentt_club_featured_image_file" accept="image/*">';
        $out .= '<label class="opentt-auth-inline"><input type="checkbox" name="opentt_club_featured_image_remove" value="1"> Ukloni postojeću cover sliku</label>';
        $out .= '<div class="opentt-cover-focus-wrap" id="opentt_team_cover_focus_wrap_' . esc_attr((string) $clubId) . '" data-image-url="' . esc_attr($coverUrl) . '"><div class="opentt-cover-focus-grid"><div><strong>Banner preview (16:5)</strong><div class="opentt-cover-focus-preview is-desktop"><img id="opentt_team_cover_preview_desktop_' . esc_attr((string) $clubId) . '" alt=""></div></div><div><strong>Isti prikaz (16:5)</strong><div class="opentt-cover-focus-preview is-mobile"><img id="opentt_team_cover_preview_mobile_' . esc_attr((string) $clubId) . '" alt=""></div></div></div><div class="opentt-cover-focus-controls"><label>Horizontalno<input type="range" id="opentt_team_cover_focus_range_x_' . esc_attr((string) $clubId) . '" min="0" max="100" step="1" value="' . esc_attr((string) round($focusX)) . '"></label><label>Vertikalno<input type="range" id="opentt_team_cover_focus_range_y_' . esc_attr((string) $clubId) . '" min="0" max="100" step="1" value="' . esc_attr((string) round($focusY)) . '"></label></div></div>';
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
        $out .= "<script>(function(){var cid='" . esc_js((string) $clubId) . "';var wrap=document.getElementById('opentt_team_cover_focus_wrap_'+cid);if(!wrap){return;}var desktop=document.getElementById('opentt_team_cover_preview_desktop_'+cid);var mobile=document.getElementById('opentt_team_cover_preview_mobile_'+cid);var rx=document.getElementById('opentt_team_cover_focus_range_x_'+cid);var ry=document.getElementById('opentt_team_cover_focus_range_y_'+cid);var hx=document.getElementById('opentt_team_club_focus_x_'+cid);var hy=document.getElementById('opentt_team_club_focus_y_'+cid);var fileInput=wrap.parentNode?wrap.parentNode.querySelector('input[name=\"opentt_club_featured_image_file\"]'):null;function applyPos(){if(!desktop||!mobile||!rx||!ry){return;}var x=parseInt(rx.value||'50',10);var y=parseInt(ry.value||'50',10);var pos=x+'% '+y+'%';desktop.style.objectPosition=pos;mobile.style.objectPosition=pos;if(hx){hx.value=String(x);}if(hy){hy.value=String(y);}}function setSrc(url){if(!desktop||!mobile){return;}desktop.src=url||'';mobile.src=url||'';applyPos();}if(rx){rx.addEventListener('input',applyPos);rx.addEventListener('change',applyPos);}if(ry){ry.addEventListener('input',applyPos);ry.addEventListener('change',applyPos);}if(fileInput){fileInput.addEventListener('change',function(){var f=fileInput.files&&fileInput.files[0]?fileInput.files[0]:null;if(!f){return;}var u=URL.createObjectURL(f);setSrc(u);});}setSrc(String(wrap.getAttribute('data-image-url')||''));})();</script>";

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
}
