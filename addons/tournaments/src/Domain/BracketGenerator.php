<?php

namespace OpenTT\Tournaments\Domain;

use OpenTT\Tournaments\Infrastructure\Schema;

final class BracketGenerator
{
    public static function generate($category, array $entries)
    {
        global $wpdb;
        if (!$category || empty($category->id)) {
            return false;
        }

        $categoryId = intval($category->id);
        $tournamentId = intval($category->tournament_id ?? 0);
        $size = self::normalizeSize(intval($category->bracket_size ?? 16));
        $entries = self::orderedEntries($entries);
        $slots = array_fill(0, $size, null);
        foreach ($entries as $i => $entry) {
            if ($i >= $size) {
                break;
            }
            $slots[$i] = intval($entry->id ?? 0);
        }

        $matchesTable = Schema::table('matches');
        $slotsTable = Schema::table('bracket_slots');
        $wpdb->delete($matchesTable, ['category_id' => $categoryId]);
        $wpdb->delete($slotsTable, ['category_id' => $categoryId]);

        $now = current_time('mysql');
        $rounds = (int) log($size, 2);
        $firstRoundMatches = (int) ($size / 2);

        for ($matchNo = 1; $matchNo <= $firstRoundMatches; $matchNo++) {
            $homeEntry = $slots[($matchNo - 1) * 2] ?: null;
            $awayEntry = $slots[(($matchNo - 1) * 2) + 1] ?: null;
            $winner = null;
            $status = 'scheduled';
            if ($homeEntry && !$awayEntry) {
                $winner = $homeEntry;
                $status = 'bye';
            } elseif (!$homeEntry && $awayEntry) {
                $winner = $awayEntry;
                $status = 'bye';
            }
            $wpdb->insert($matchesTable, [
                'tournament_id' => $tournamentId,
                'category_id' => $categoryId,
                'phase' => 'bracket',
                'round_no' => 1,
                'round_label' => self::roundLabel(1, $rounds),
                'match_no' => $matchNo,
                'bracket_position' => $matchNo,
                'home_entry_id' => $homeEntry,
                'away_entry_id' => $awayEntry,
                'winner_entry_id' => $winner,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $wpdb->insert($slotsTable, [
                'tournament_id' => $tournamentId,
                'category_id' => $categoryId,
                'round_no' => 1,
                'match_no' => $matchNo,
                'side' => 'home',
                'source_type' => 'manual',
                'entry_id' => $homeEntry,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $wpdb->insert($slotsTable, [
                'tournament_id' => $tournamentId,
                'category_id' => $categoryId,
                'round_no' => 1,
                'match_no' => $matchNo,
                'side' => 'away',
                'source_type' => 'manual',
                'entry_id' => $awayEntry,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        for ($round = 2; $round <= $rounds; $round++) {
            $matchCount = (int) ($size / pow(2, $round));
            for ($matchNo = 1; $matchNo <= $matchCount; $matchNo++) {
                $wpdb->insert($matchesTable, [
                    'tournament_id' => $tournamentId,
                    'category_id' => $categoryId,
                    'phase' => 'bracket',
                    'round_no' => $round,
                    'round_label' => self::roundLabel($round, $rounds),
                    'match_no' => $matchNo,
                    'bracket_position' => $matchNo,
                    'status' => 'scheduled',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        self::advanceByes($categoryId);
        return true;
    }

    public static function updateWinnerAndAdvance($matchId, $homeScore, $awayScore)
    {
        global $wpdb;
        $matchId = intval($matchId);
        if ($matchId <= 0) {
            return false;
        }
        $table = Schema::table('matches');
        $match = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d LIMIT 1", $matchId));
        if (!$match) {
            return false;
        }

        $homeScore = max(0, intval($homeScore));
        $awayScore = max(0, intval($awayScore));
        $winner = null;
        if ($homeScore !== $awayScore) {
            $winner = ($homeScore > $awayScore) ? intval($match->home_entry_id) : intval($match->away_entry_id);
            if ($winner <= 0) {
                $winner = null;
            }
        }
        $wpdb->update($table, [
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'winner_entry_id' => $winner,
            'status' => $winner ? 'played' : 'scheduled',
            'updated_at' => current_time('mysql'),
        ], ['id' => $matchId]);

        if ($winner) {
            self::advanceWinner((object) array_merge((array) $match, ['winner_entry_id' => $winner]));
        }
        return true;
    }

    private static function advanceByes($categoryId)
    {
        global $wpdb;
        $table = Schema::table('matches');
        $matches = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE category_id=%d AND round_no=1 AND winner_entry_id IS NOT NULL", intval($categoryId))) ?: [];
        foreach ($matches as $match) {
            self::advanceWinner($match);
        }
    }

    private static function advanceWinner($match)
    {
        global $wpdb;
        $winner = intval($match->winner_entry_id ?? 0);
        $round = intval($match->round_no ?? 0);
        $matchNo = intval($match->match_no ?? 0);
        $categoryId = intval($match->category_id ?? 0);
        if ($winner <= 0 || $round <= 0 || $matchNo <= 0 || $categoryId <= 0) {
            return;
        }
        $nextRound = $round + 1;
        $nextMatchNo = (int) ceil($matchNo / 2);
        $side = ($matchNo % 2 === 1) ? 'home_entry_id' : 'away_entry_id';
        $table = Schema::table('matches');
        $next = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE category_id=%d AND round_no=%d AND match_no=%d LIMIT 1", $categoryId, $nextRound, $nextMatchNo));
        if (!$next) {
            return;
        }
        $wpdb->update($table, [
            $side => $winner,
            'updated_at' => current_time('mysql'),
        ], ['id' => intval($next->id)]);
    }

    private static function orderedEntries(array $entries)
    {
        usort($entries, static function ($a, $b) {
            $aSeed = intval($a->seed_no ?? 0);
            $bSeed = intval($b->seed_no ?? 0);
            if ($aSeed > 0 && $bSeed > 0 && $aSeed !== $bSeed) {
                return $aSeed <=> $bSeed;
            }
            if ($aSeed > 0 && $bSeed <= 0) {
                return -1;
            }
            if ($aSeed <= 0 && $bSeed > 0) {
                return 1;
            }
            return intval($a->id ?? 0) <=> intval($b->id ?? 0);
        });
        return $entries;
    }

    private static function normalizeSize($size)
    {
        $allowed = [4, 8, 16, 32, 64, 128];
        return in_array($size, $allowed, true) ? $size : 16;
    }

    private static function roundLabel($round, $totalRounds)
    {
        $round = intval($round);
        $totalRounds = intval($totalRounds);
        if ($round >= $totalRounds) {
            return 'Finale';
        }
        if ($round === $totalRounds - 1) {
            return 'Polufinale';
        }
        if ($round === $totalRounds - 2) {
            return 'Cetvrtfinale';
        }
        $remaining = (int) pow(2, $totalRounds - $round + 1);
        return '1/' . $remaining;
    }
}

