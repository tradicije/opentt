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

namespace OpenTT\Unified\WordPress\Shortcodes;

final class ClubCardShortcode
{
    public static function render($atts = [], array $deps = [])
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $atts = shortcode_atts([
            'id' => '',
            'klub' => '',
            'title' => '',
        ], (array) $atts, 'opentt_club_card');

        $club_id = self::resolve_club_id($atts);
        if ($club_id <= 0 || get_post_type($club_id) !== 'klub') {
            return '';
        }

        $club_name = (string) get_the_title($club_id);
        if ($club_name === '') {
            return '';
        }

        $city = trim((string) get_post_meta($club_id, 'grad', true));
        $logo = (string) $call('club_logo_html', $club_id, 'medium', ['class' => 'opentt-club-card-logo-img']);
        $club_link = (string) get_permalink($club_id);
        $title = trim((string) ($atts['title'] ?? ''));

        ob_start();
        if ($title !== '') {
            echo (string) $call('shortcode_title_html', $title); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '<section class="opentt-club-card">';
        if ($club_link !== '') {
            echo '<a class="opentt-club-card-link" href="' . esc_url($club_link) . '">';
        } else {
            echo '<div class="opentt-club-card-link">';
        }
        echo '<span class="opentt-club-card-logo">' . $logo . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<span class="opentt-club-card-name">' . esc_html($club_name) . '</span>';
        if ($city !== '') {
            echo '<span class="opentt-club-card-city">' . esc_html($city) . '</span>';
        }
        if ($club_link !== '') {
            echo '</a>';
        } else {
            echo '</div>';
        }
        echo '</section>';
        return ob_get_clean();
    }

    private static function resolve_club_id(array $atts)
    {
        $id_raw = trim((string) ($atts['id'] ?? ''));
        if ($id_raw !== '' && ctype_digit($id_raw)) {
            return intval($id_raw);
        }

        $club_raw = trim((string) ($atts['klub'] ?? ''));
        if ($club_raw !== '') {
            if (ctype_digit($club_raw)) {
                return intval($club_raw);
            }

            $post = get_page_by_path(sanitize_title($club_raw), OBJECT, 'klub');
            if (!$post) {
                $post = get_page_by_title($club_raw, OBJECT, 'klub');
            }
            if ($post && !is_wp_error($post)) {
                return intval($post->ID);
            }
        }

        if (is_singular('klub')) {
            return intval(get_the_ID());
        }

        return 0;
    }
}

