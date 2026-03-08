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

namespace OpenTT\Unified\Infrastructure;

final class EloRatingManager
{
    public const META_KEY = 'opentt_elo_rating';
    public const META_KEY_SCOPED = 'opentt_elo_ratings';
    public const DEFAULT_RATING = 1500;
    public const K_FACTOR = 32.0;

    public static function getPlayerRating($playerId, $ligaSlug = '', $sezonaSlug = '')
    {
        $playerId = (int) $playerId;
        if ($playerId <= 0) {
            return self::DEFAULT_RATING;
        }

        $ligaSlug = sanitize_title((string) $ligaSlug);
        $sezonaSlug = sanitize_title((string) $sezonaSlug);
        $ratings = self::getPlayerRatingsMap($playerId);
        $scopeKey = self::scopeKey($ligaSlug, $sezonaSlug);

        if ($scopeKey !== '' && isset($ratings[$scopeKey])) {
            return (int) round((float) $ratings[$scopeKey]);
        }

        if ($scopeKey === '') {
            $resolved = self::resolveRequestScope();
            $resolvedKey = self::scopeKey((string) ($resolved['liga_slug'] ?? ''), (string) ($resolved['sezona_slug'] ?? ''));
            if ($resolvedKey !== '' && isset($ratings[$resolvedKey])) {
                return (int) round((float) $ratings[$resolvedKey]);
            }
        }

        $legacy = get_post_meta($playerId, self::META_KEY, true);
        if ($legacy !== '' && $legacy !== null) {
            return (int) round((float) $legacy);
        }

        return self::DEFAULT_RATING;
    }

    public static function getPlayerRatingsMap($playerId)
    {
        $playerId = (int) $playerId;
        if ($playerId <= 0) {
            return [];
        }

        $raw = get_post_meta($playerId, self::META_KEY_SCOPED, true);
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $key => $value) {
            $scope = sanitize_key((string) $key);
            if ($scope === '') {
                continue;
            }
            $out[$scope] = (int) round((float) $value);
        }

        return $out;
    }

    public static function updateAfterMatch($playerAId, $playerBId, $winnerId, $kFactor = self::K_FACTOR, $ligaSlug = '', $sezonaSlug = '')
    {
        $playerAId = (int) $playerAId;
        $playerBId = (int) $playerBId;
        $winnerId = (int) $winnerId;
        $kFactor = (float) $kFactor;
        $ligaSlug = sanitize_title((string) $ligaSlug);
        $sezonaSlug = sanitize_title((string) $sezonaSlug);
        $scopeKey = self::scopeKey($ligaSlug, $sezonaSlug);

        if ($playerAId <= 0 || $playerBId <= 0 || $playerAId === $playerBId) {
            return null;
        }
        if ($winnerId !== $playerAId && $winnerId !== $playerBId) {
            return null;
        }
        if ($kFactor <= 0) {
            $kFactor = self::K_FACTOR;
        }
        if ($scopeKey === '') {
            return null;
        }

        $ratingA = self::getPlayerRating($playerAId, $ligaSlug, $sezonaSlug);
        $ratingB = self::getPlayerRating($playerBId, $ligaSlug, $sezonaSlug);

        // Expected scores based on current ratings.
        $expectedA = 1.0 / (1.0 + pow(10.0, (($ratingB - $ratingA) / 400.0)));
        $expectedB = 1.0 - $expectedA;

        // Actual scores from winner perspective.
        $scoreA = ($winnerId === $playerAId) ? 1.0 : 0.0;
        $scoreB = 1.0 - $scoreA;

        // New ratings by standard ELO formula.
        $newRatingA = (int) round($ratingA + ($kFactor * ($scoreA - $expectedA)));
        $newRatingB = (int) round($ratingB + ($kFactor * ($scoreB - $expectedB)));

        $ratingsA = self::getPlayerRatingsMap($playerAId);
        $ratingsB = self::getPlayerRatingsMap($playerBId);
        $ratingsA[$scopeKey] = $newRatingA;
        $ratingsB[$scopeKey] = $newRatingB;
        update_post_meta($playerAId, self::META_KEY_SCOPED, $ratingsA);
        update_post_meta($playerBId, self::META_KEY_SCOPED, $ratingsB);

        return [
            'player_a_id' => $playerAId,
            'player_b_id' => $playerBId,
            'winner_id' => $winnerId,
            'scope_key' => $scopeKey,
            'liga_slug' => $ligaSlug,
            'sezona_slug' => $sezonaSlug,
            'old_a' => $ratingA,
            'old_b' => $ratingB,
            'new_a' => $newRatingA,
            'new_b' => $newRatingB,
            'expected_a' => $expectedA,
            'expected_b' => $expectedB,
            'k' => $kFactor,
        ];
    }

    private static function scopeKey($ligaSlug, $sezonaSlug)
    {
        $ligaSlug = sanitize_title((string) $ligaSlug);
        $sezonaSlug = sanitize_title((string) $sezonaSlug);
        if ($ligaSlug === '' || $sezonaSlug === '') {
            return '';
        }
        return sanitize_key($ligaSlug . '|' . $sezonaSlug);
    }

    private static function resolveRequestScope()
    {
        $liga = sanitize_title((string) (get_query_var('liga') ?: ''));
        $sezona = sanitize_title((string) (get_query_var('sezona') ?: ''));
        return [
            'liga_slug' => $liga,
            'sezona_slug' => $sezona,
        ];
    }
}
