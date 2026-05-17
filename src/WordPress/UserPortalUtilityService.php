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

final class UserPortalUtilityService
{
    public static function verifyTurnstileToken($token)
    {
        $token = trim((string) $token);
        $secret = trim((string) \OpenTT_Unified_Core::turnstile_secret_key());
        if ($secret === '' || $token === '') {
            return false;
        }

        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'timeout' => 15,
            'body' => [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        if ($code < 200 || $code >= 300) {
            return false;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($body) && !empty($body['success']);
    }

    public static function normalizeMatchDate($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return current_time('mysql');
        }
        $tz = function_exists('wp_timezone') ? wp_timezone() : null;
        if ($tz instanceof \DateTimeZone) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw, $tz);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
            $dt = \DateTime::createFromFormat('Y-m-d H:i', $raw, $tz);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
            $dt = \DateTime::createFromFormat('Y-m-d', $raw, $tz);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d 00:00:00');
            }
        }
        $ts = strtotime($raw);
        if ($ts !== false) {
            return gmdate('Y-m-d H:i:s', intval($ts) + (int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS));
        }
        return current_time('mysql');
    }

    public static function collectLeagueClubIds($leagueSlug, callable $tableExists)
    {
        $leagueSlug = sanitize_title((string) $leagueSlug);
        if ($leagueSlug === '') {
            return [];
        }

        global $wpdb;
        $matchesTable = \OpenTT_Unified_Core::db_table('matches');
        $ids = [];
        if ($tableExists($matchesTable)) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT home_club_post_id, away_club_post_id FROM {$matchesTable} WHERE liga_slug=%s",
                $leagueSlug
            )) ?: [];
            foreach ($rows as $row) {
                if (!is_object($row)) {
                    continue;
                }
                $h = intval($row->home_club_post_id ?? 0);
                $a = intval($row->away_club_post_id ?? 0);
                if ($h > 0) {
                    $ids[$h] = true;
                }
                if ($a > 0) {
                    $ids[$a] = true;
                }
            }
        }

        if (empty($ids)) {
            $all = get_posts([
                'post_type' => 'klub',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'orderby' => 'title',
                'order' => 'ASC',
            ]) ?: [];
            foreach ($all as $cid) {
                $cid = intval($cid);
                if ($cid > 0) {
                    $ids[$cid] = true;
                }
            }
        }

        $out = array_map('intval', array_keys($ids));
        sort($out, SORT_NUMERIC);
        return $out;
    }

    public static function profileAvatarUrl($userId, $size, $avatarMetaKey)
    {
        $avatarId = intval(get_user_meta(intval($userId), (string) $avatarMetaKey, true));
        if ($avatarId > 0) {
            $url = (string) wp_get_attachment_image_url($avatarId, 'thumbnail');
            if ($url !== '') {
                return $url;
            }
        }
        $gravatarUrl = (string) get_avatar_url(intval($userId), ['size' => max(32, intval($size))]);
        if ($gravatarUrl !== '') {
            return $gravatarUrl;
        }
        $fallback = (string) plugins_url('assets/img/fallback-player.png', dirname(__DIR__, 2) . '/opentt-unified-core.php');
        if ($fallback !== '') {
            return $fallback;
        }
        return '';
    }
}
