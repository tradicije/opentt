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

final class OpenTT_Unified_Match_Context_Service
{
    public static function current_match_context($virtual_match_row, callable $db_get_match_by_legacy_id)
    {
        if ($virtual_match_row) {
            return [
                'db_row' => $virtual_match_row,
                'legacy_id' => intval($virtual_match_row->legacy_post_id),
            ];
        }

        if (is_singular('utakmica')) {
            $legacy_id = intval(get_the_ID());
            $row = $db_get_match_by_legacy_id($legacy_id);
            if ($row) {
                return ['db_row' => $row, 'legacy_id' => $legacy_id];
            }
        }

        return null;
    }

    public static function get_template_match_context($ctx, array $deps = [])
    {
        $display_match_date = isset($deps['display_match_date']) && is_callable($deps['display_match_date'])
            ? $deps['display_match_date']
            : null;
        $kolo_name_from_slug = isset($deps['kolo_name_from_slug']) && is_callable($deps['kolo_name_from_slug'])
            ? $deps['kolo_name_from_slug']
            : null;
        $match_permalink = isset($deps['match_permalink']) && is_callable($deps['match_permalink'])
            ? $deps['match_permalink']
            : null;

        if (!$ctx || empty($ctx['db_row']) || !$display_match_date || !$kolo_name_from_slug || !$match_permalink) {
            return null;
        }
        $row = $ctx['db_row'];
        return [
            'db_id' => intval($row->id),
            'legacy_id' => intval($ctx['legacy_id']),
            'slug' => (string) $row->slug,
            'liga_slug' => (string) $row->liga_slug,
            'kolo_slug' => (string) $row->kolo_slug,
            'date' => (string) $display_match_date($row->match_date),
            'kolo_name' => (string) $kolo_name_from_slug((string) $row->kolo_slug),
            'home_club_id' => intval($row->home_club_post_id),
            'away_club_id' => intval($row->away_club_post_id),
            'home_score' => intval($row->home_score),
            'away_score' => intval($row->away_score),
            'match_url' => (string) $match_permalink($row),
        ];
    }

    public static function get_match_block_template($slug)
    {
        if (!function_exists('get_block_template')) {
            return null;
        }

        $theme = get_stylesheet();
        $slug = sanitize_title((string) $slug);

        $tpl = get_block_template($theme . '//' . $slug, 'wp_template');
        if ($tpl) {
            return $tpl;
        }

        $parent = get_template();
        if ($parent && $parent !== $theme) {
            $tpl = get_block_template($parent . '//' . $slug, 'wp_template');
            if ($tpl) {
                return $tpl;
            }
        }

        $posts = get_posts([
            'post_type' => 'wp_template',
            'name' => $slug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft'],
        ]);
        if (!empty($posts[0])) {
            return (object) [
                'content' => $posts[0]->post_content,
            ];
        }

        $posts = get_posts([
            'post_type' => 'wp_template',
            'posts_per_page' => 20,
            'post_status' => ['publish', 'draft'],
            's' => '//' . $slug,
        ]);
        if (!empty($posts)) {
            foreach ($posts as $p) {
                if (strpos((string) $p->post_name, '//' . $slug) !== false) {
                    return (object) [
                        'content' => $p->post_content,
                    ];
                }
            }
        }

        return null;
    }
}

