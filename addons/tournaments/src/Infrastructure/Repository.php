<?php

namespace OpenTT\Tournaments\Infrastructure;

final class Repository
{
    public static function categories($tournamentId)
    {
        global $wpdb;
        $tournamentId = intval($tournamentId);
        if ($tournamentId <= 0) {
            return [];
        }
        $table = Schema::table('categories');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE tournament_id=%d ORDER BY sort_order ASC, id ASC", $tournamentId)) ?: [];
    }

    public static function category($categoryId)
    {
        global $wpdb;
        $categoryId = intval($categoryId);
        if ($categoryId <= 0) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Schema::table('categories') . " WHERE id=%d LIMIT 1", $categoryId));
    }

    public static function entries($categoryId)
    {
        global $wpdb;
        $categoryId = intval($categoryId);
        if ($categoryId <= 0) {
            return [];
        }
        $table = Schema::table('entries');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE category_id=%d ORDER BY COALESCE(seed_no, 9999) ASC, id ASC", $categoryId)) ?: [];
    }

    public static function entryMap($categoryId)
    {
        $map = [];
        foreach (self::entries($categoryId) as $entry) {
            $map[intval($entry->id)] = $entry;
        }
        return $map;
    }

    public static function matches($categoryId)
    {
        global $wpdb;
        $categoryId = intval($categoryId);
        if ($categoryId <= 0) {
            return [];
        }
        $table = Schema::table('matches');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE category_id=%d ORDER BY round_no ASC, match_no ASC", $categoryId)) ?: [];
    }

    public static function matchesByRound($categoryId)
    {
        $out = [];
        foreach (self::matches($categoryId) as $match) {
            $round = max(1, intval($match->round_no ?? 1));
            if (!isset($out[$round])) {
                $out[$round] = [];
            }
            $out[$round][] = $match;
        }
        ksort($out);
        return $out;
    }

    public static function hasBracket($categoryId)
    {
        global $wpdb;
        $categoryId = intval($categoryId);
        if ($categoryId <= 0) {
            return false;
        }
        $table = Schema::table('matches');
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE category_id=%d AND phase='bracket'", $categoryId));
        return $count > 0;
    }
}

