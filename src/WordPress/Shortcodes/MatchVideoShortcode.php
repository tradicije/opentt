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

final class MatchVideoShortcode
{
    public static function render($atts, array $deps)
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $ctx = $call('current_match_context');
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }
        $row = is_object($ctx['db_row']) ? $ctx['db_row'] : null;
        if (!$row) {
            return '';
        }
        $video_url = trim((string) ($row->video_url ?? ''));
        if ($video_url === '' && !empty($ctx['legacy_id'])) {
            $legacy_match_id = intval($ctx['legacy_id']);
            $video_url = (string) get_post_meta($legacy_match_id, 'snimak_utakmice', true);
        }
        if ($video_url === '') {
            return (string) $call('shortcode_title_html', 'Snimak utakmice')
                . '<p>Nema snimka za ovu utakmicu.</p>';
        }

        $embed = wp_oembed_get($video_url);
        if (!$embed) {
            $embed = self::youtube_embed_fallback($video_url);
        }
        if (!$embed) {
            return (string) $call('shortcode_title_html', 'Snimak utakmice')
                . '<p>Nema snimka za ovu utakmicu.</p>';
        }

        return (string) $call('shortcode_title_html', 'Snimak utakmice')
            . '<div class="snimak-utakmice-section"><div class="video-wrapper">' . $embed . '</div></div>';
    }

    private static function youtube_embed_fallback($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        $video_id = self::extract_youtube_video_id($url);
        if ($video_id === '') {
            return '';
        }

        $embed_url = 'https://www.youtube.com/embed/' . rawurlencode($video_id) . '?rel=0';
        return '<iframe width="560" height="315" src="' . esc_url($embed_url) . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>';
    }

    private static function extract_youtube_video_id($url)
    {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        $query = (string) wp_parse_url($url, PHP_URL_QUERY);

        if (strpos($host, 'youtu.be') !== false && $path !== '') {
            return sanitize_text_field((string) explode('/', $path)[0]);
        }

        if (strpos($host, 'youtube.com') === false && strpos($host, 'youtube-nocookie.com') === false) {
            return '';
        }

        parse_str($query, $params);
        if (!empty($params['v'])) {
            return sanitize_text_field((string) $params['v']);
        }

        if (strpos($path, 'shorts/') === 0) {
            return sanitize_text_field((string) explode('/', substr($path, 7))[0]);
        }
        if (strpos($path, 'embed/') === 0) {
            return sanitize_text_field((string) explode('/', substr($path, 6))[0]);
        }
        if (strpos($path, 'watch/') === 0) {
            return sanitize_text_field((string) explode('/', substr($path, 6))[0]);
        }

        return '';
    }
}
