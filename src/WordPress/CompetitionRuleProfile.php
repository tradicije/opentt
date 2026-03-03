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

final class CompetitionRuleProfile
{
    public static function getRuleData($leagueSlug, $seasonSlug)
    {
        $leagueSlug = sanitize_title((string) $leagueSlug);
        $seasonSlug = sanitize_title((string) $seasonSlug);
        if ($leagueSlug === '' || $seasonSlug === '') {
            return null;
        }

        $post = CompetitionRuleStore::findBySlugs($leagueSlug, $seasonSlug);
        if (!$post) {
            return null;
        }

        return [
            'id' => (int) $post->ID,
            'liga_slug' => $leagueSlug,
            'sezona_slug' => $seasonSlug,
            'rang' => max(1, min(5, (int) get_post_meta($post->ID, 'opentt_competition_rank', true) ?: 3)),
            'promocija_broj' => (int) get_post_meta($post->ID, 'opentt_competition_promotion_slots', true),
            'promocija_baraz_broj' => (int) get_post_meta($post->ID, 'opentt_competition_promotion_playoff_slots', true),
            'ispadanje_broj' => (int) get_post_meta($post->ID, 'opentt_competition_relegation_slots', true),
            'ispadanje_razigravanje_broj' => (int) get_post_meta($post->ID, 'opentt_competition_relegation_playoff_slots', true),
            'bodovanje_tip' => (string) get_post_meta($post->ID, 'opentt_competition_scoring_type', true),
            'format_partija' => (string) get_post_meta($post->ID, 'opentt_competition_match_format', true),
            'savez' => CompetitionRuleCatalog::normalizeFederation((string) get_post_meta($post->ID, 'opentt_competition_federation', true)),
        ];
    }

    public static function resolveMatchFormat($leagueSlug, $seasonSlug, $defaultFormat = 'format_a')
    {
        $rule = self::getRuleData($leagueSlug, $seasonSlug);
        $format = is_array($rule) ? (string) ($rule['format_partija'] ?? '') : '';
        if (in_array($format, ['format_a', 'format_b'], true)) {
            return $format;
        }
        return (string) $defaultFormat;
    }
}
