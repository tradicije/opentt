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
            return '';
        }

        $embed = wp_oembed_get($video_url);
        if (!$embed) {
            return '';
        }

        return (string) $call('shortcode_title_html', 'Snimak utakmice')
            . '<div class="snimak-utakmice-section"><div class="video-wrapper">' . $embed . '</div></div>';
    }
}
