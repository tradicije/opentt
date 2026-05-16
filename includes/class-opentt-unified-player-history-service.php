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

final class OpenTT_Unified_Player_History_Service
{
    public static function build_player_stints($history)
    {
        if (empty($history) || !is_array($history)) {
            return [];
        }

        $stints = [];
        foreach ($history as $row) {
            $season = sanitize_title((string) ($row['season_slug'] ?? ''));
            $club_id = intval($row['club_id'] ?? 0);
            if ($season === '' || $club_id <= 0) {
                continue;
            }

            if (empty($stints)) {
                $stints[] = [
                    'club_id' => $club_id,
                    'from_season' => $season,
                    'to_season' => $season,
                ];
                continue;
            }

            $idx = count($stints) - 1;
            if (intval($stints[$idx]['club_id']) === $club_id) {
                $stints[$idx]['to_season'] = $season;
            } else {
                $stints[] = [
                    'club_id' => $club_id,
                    'from_season' => $season,
                    'to_season' => $season,
                ];
            }
        }

        return $stints;
    }
}

