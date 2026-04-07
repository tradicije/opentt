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

final class DefaultPagesProvisioner
{
    public static function ensureCompetitionsPage()
    {
        if (!post_type_exists('page')) {
            return;
        }

        $existingSlug = get_posts([
            'post_type' => 'page',
            'name' => 'lige',
            'numberposts' => 1,
            'post_status' => ['publish', 'private', 'draft', 'pending', 'future', 'trash'],
            'fields' => 'ids',
            'suppress_filters' => true,
        ]);
        if (!empty($existingSlug)) {
            return;
        }

        global $wpdb;
        $postsTable = $wpdb->posts;
        $like = '%[opentt_competitions%';
        $foundShortcode = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$postsTable}
             WHERE post_type='page'
               AND post_status IN ('publish','private','draft','pending','future','trash')
               AND post_content LIKE %s
             LIMIT 1",
            $like
        ));
        if (!empty($foundShortcode)) {
            return;
        }

        wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'Lige',
            'post_name' => 'lige',
            'post_content' => '[opentt_competitions]',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ]);
    }

    public static function ensureUserPortalPages()
    {
        UserPortalManager::ensureDefaultPages();
    }
}
