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
    public const DEFAULT_RATING = 1500;
    public const K_FACTOR = 32.0;

    public static function getPlayerRating($playerId)
    {
        $playerId = (int) $playerId;
        if ($playerId <= 0) {
            return self::DEFAULT_RATING;
        }

        $value = get_post_meta($playerId, self::META_KEY, true);
        if ($value === '' || $value === null) {
            return self::DEFAULT_RATING;
        }

        return (int) round((float) $value);
    }

    public static function updateAfterMatch($playerAId, $playerBId, $winnerId, $kFactor = self::K_FACTOR)
    {
        $playerAId = (int) $playerAId;
        $playerBId = (int) $playerBId;
        $winnerId = (int) $winnerId;
        $kFactor = (float) $kFactor;

        if ($playerAId <= 0 || $playerBId <= 0 || $playerAId === $playerBId) {
            return null;
        }
        if ($winnerId !== $playerAId && $winnerId !== $playerBId) {
            return null;
        }
        if ($kFactor <= 0) {
            $kFactor = self::K_FACTOR;
        }

        $ratingA = self::getPlayerRating($playerAId);
        $ratingB = self::getPlayerRating($playerBId);

        // Expected scores based on current ratings.
        $expectedA = 1.0 / (1.0 + pow(10.0, (($ratingB - $ratingA) / 400.0)));
        $expectedB = 1.0 - $expectedA;

        // Actual scores from winner perspective.
        $scoreA = ($winnerId === $playerAId) ? 1.0 : 0.0;
        $scoreB = 1.0 - $scoreA;

        // New ratings by standard ELO formula.
        $newRatingA = (int) round($ratingA + ($kFactor * ($scoreA - $expectedA)));
        $newRatingB = (int) round($ratingB + ($kFactor * ($scoreB - $expectedB)));

        update_post_meta($playerAId, self::META_KEY, $newRatingA);
        update_post_meta($playerBId, self::META_KEY, $newRatingB);

        return [
            'player_a_id' => $playerAId,
            'player_b_id' => $playerBId,
            'winner_id' => $winnerId,
            'old_a' => $ratingA,
            'old_b' => $ratingB,
            'new_a' => $newRatingA,
            'new_b' => $newRatingB,
            'expected_a' => $expectedA,
            'expected_b' => $expectedB,
            'k' => $kFactor,
        ];
    }
}

