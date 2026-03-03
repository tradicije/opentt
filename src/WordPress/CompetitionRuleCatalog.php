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

final class CompetitionRuleCatalog
{
    public static function hasAnyRules()
    {
        $rows = get_posts([
            'post_type' => 'pravilo_takmicenja',
            'numberposts' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'fields' => 'ids',
        ]);
        return !empty($rows);
    }

    public static function federationOptions()
    {
        return [
            'STSS' => [
                'label' => 'STSS',
                'url' => 'https://stss.rs',
            ],
            'STSV' => [
                'label' => 'STSV',
                'url' => 'https://stsv.rs',
            ],
            'RSTSN' => [
                'label' => 'RSTSN',
                'url' => 'https://regionnis.rs',
            ],
            'SSKCR' => [
                'label' => 'SSKCR',
                'url' => 'https://centralniregion.com',
            ],
        ];
    }

    public static function normalizeFederation($code)
    {
        $code = strtoupper(trim((string) $code));
        $options = self::federationOptions();
        return isset($options[$code]) ? $code : '';
    }

    public static function federationData($code)
    {
        $code = self::normalizeFederation($code);
        if ($code === '') {
            return null;
        }
        $options = self::federationOptions();
        return isset($options[$code]) ? $options[$code] : null;
    }

    public static function ruleIdBySlugs($leagueSlug, $seasonSlug)
    {
        $post = CompetitionRuleStore::findBySlugs($leagueSlug, $seasonSlug);
        return $post ? (int) $post->ID : 0;
    }
}
