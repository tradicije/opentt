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

final class UserPortalEditorViewService
{
    public static function renderEditorTools($userId)
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

    public static function renderEditorPosts($userId)
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
}
