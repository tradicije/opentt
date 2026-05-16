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

if (!defined('ABSPATH')) {
    exit;
}

final class OpenTT_Unified_Media_Service
{
    public static function club_fallback_image_url($plugin_dir, $plugin_file)
    {
        $plugin_dir = is_string($plugin_dir) ? trim($plugin_dir) : '';
        if ($plugin_dir === '') {
            return '';
        }

        $relative_candidates = [
            'assets/img/fallback-club.png',
            'assets/image/fallback-club.png',
        ];

        foreach ($relative_candidates as $relative_path) {
            $absolute_path = trailingslashit($plugin_dir) . $relative_path;
            if (is_readable($absolute_path)) {
                return plugins_url($relative_path, $plugin_file);
            }
        }

        return '';
    }

    public static function player_fallback_image_url($plugin_dir, $plugin_file)
    {
        $plugin_dir = is_string($plugin_dir) ? trim($plugin_dir) : '';
        if ($plugin_dir === '') {
            return '';
        }

        $relative_candidates = [
            'assets/img/fallback-player.png',
            'assets/image/fallback-player.png',
            'assets/img/fallback-club.png',
            'assets/image/fallback-club.png',
        ];

        foreach ($relative_candidates as $relative_path) {
            $absolute_path = trailingslashit($plugin_dir) . $relative_path;
            if (is_readable($absolute_path)) {
                return plugins_url($relative_path, $plugin_file);
            }
        }

        return '';
    }

    public static function club_logo_url($club_id, $size = 'thumbnail', array $deps = [])
    {
        $club_fallback_image_url = isset($deps['club_fallback_image_url']) && is_callable($deps['club_fallback_image_url'])
            ? $deps['club_fallback_image_url']
            : null;

        $club_id = intval($club_id);
        if ($club_id <= 0) {
            return $club_fallback_image_url ? (string) $club_fallback_image_url() : '';
        }

        $url = get_the_post_thumbnail_url($club_id, $size);
        if (is_string($url) && trim($url) !== '') {
            return $url;
        }

        return $club_fallback_image_url ? (string) $club_fallback_image_url() : '';
    }

    public static function resolve_club_id_from_value($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            $id = intval($value);
            return ($id > 0 && get_post_type($id) === 'klub') ? $id : 0;
        }

        $club = get_page_by_path(sanitize_title($value), OBJECT, 'klub');
        if (!($club instanceof \WP_Post)) {
            $club = get_page_by_title($value, OBJECT, 'klub');
        }
        if ($club instanceof \WP_Post && $club->post_type === 'klub') {
            return intval($club->ID);
        }
        return 0;
    }

    public static function club_logo_html($club_id, $size = 'thumbnail', $attr = [], array $deps = [])
    {
        $club_fallback_image_url = isset($deps['club_fallback_image_url']) && is_callable($deps['club_fallback_image_url'])
            ? $deps['club_fallback_image_url']
            : null;

        $club_id = intval($club_id);
        $attr = is_array($attr) ? $attr : [];

        if ($club_id > 0) {
            $html = get_the_post_thumbnail($club_id, $size, $attr);
            if (is_string($html) && trim($html) !== '') {
                return $html;
            }
        }

        $fallback_url = $club_fallback_image_url ? (string) $club_fallback_image_url() : '';
        if ($fallback_url === '') {
            return '';
        }

        $class = isset($attr['class']) ? trim((string) $attr['class']) : '';
        if ($class === '') {
            $class = 'opentt-club-fallback-image';
        }
        $alt = isset($attr['alt']) ? (string) $attr['alt'] : (string) get_the_title($club_id);

        $img_attr = [
            'src' => $fallback_url,
            'alt' => $alt,
            'class' => $class,
        ];

        foreach (['style', 'loading', 'title', 'width', 'height', 'decoding'] as $key) {
            if (isset($attr[$key]) && $attr[$key] !== '') {
                $img_attr[$key] = (string) $attr[$key];
            }
        }

        $parts = [];
        foreach ($img_attr as $key => $value) {
            $parts[] = $key . '="' . esc_attr($value) . '"';
        }

        return '<img ' . implode(' ', $parts) . ' />';
    }
}

