#!/usr/bin/env php
<?php
/**
 * Convert legacy STKB JSON export files to OpenTT-compatible JSON.
 *
 * Usage:
 *   php tools/convert-stkb-export.php --in old.json --out converted.json
 *   php tools/convert-stkb-export.php old.json converted.json
 */

declare(strict_types=1);

const EXIT_OK = 0;
const EXIT_USAGE = 1;
const EXIT_IO = 2;
const EXIT_JSON = 3;

function stderr(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function usage(): void
{
    $script = basename(__FILE__);
    stderr("Usage:");
    stderr("  php {$script} --in <legacy.json> --out <converted.json>");
    stderr("  php {$script} <legacy.json> <converted.json>");
}

function parseArgs(array $argv): array
{
    $args = $argv;
    array_shift($args);

    if (count($args) === 2 && strpos((string) $args[0], '--') !== 0) {
        return ['in' => (string) $args[0], 'out' => (string) $args[1]];
    }

    $in = '';
    $out = '';
    for ($i = 0; $i < count($args); $i++) {
        $arg = (string) $args[$i];
        if ($arg === '--in' && isset($args[$i + 1])) {
            $in = (string) $args[++$i];
            continue;
        }
        if ($arg === '--out' && isset($args[$i + 1])) {
            $out = (string) $args[++$i];
            continue;
        }
    }

    return ['in' => $in, 'out' => $out];
}

function normalizeSectionName(string $section): string
{
    $map = [
        'stkb_competitions' => 'competitions',
        'stkb_clubs' => 'clubs',
        'stkb_players' => 'players',
        'stkb_matches' => 'matches',
        'stkb_games' => 'games',
        'stkb_sets' => 'sets',
    ];

    $section = trim($section);
    if ($section === '') {
        return $section;
    }

    if (isset($map[$section])) {
        return $map[$section];
    }

    if (strpos($section, 'stkb_') === 0) {
        return substr_replace($section, 'opentt_', 0, 5);
    }

    return $section;
}

function normalizeKey(string $key): string
{
    $exact = [
        '_stkb_legacy_ref_id' => '_opentt_legacy_ref_id',
        '_stkb_import_source_attachment_id' => '_opentt_import_source_attachment_id',
        'stkb_pravila_liga_slug' => 'opentt_competition_league_slug',
        'stkb_pravila_sezona_slug' => 'opentt_competition_season_slug',
        'stkb_pravila_format_partija' => 'opentt_competition_match_format',
        'stkb_pravila_bodovanje_tip' => 'opentt_competition_scoring_type',
        'stkb_pravila_promocija_broj' => 'opentt_competition_promotion_slots',
        'stkb_pravila_promocija_baraz_broj' => 'opentt_competition_promotion_playoff_slots',
        'stkb_pravila_ispadanje_broj' => 'opentt_competition_relegation_slots',
        'stkb_pravila_ispadanje_razigravanje_broj' => 'opentt_competition_relegation_playoff_slots',
        'stkb_pravila_savez' => 'opentt_competition_federation',
        'stkb_pravila_rang' => 'opentt_competition_rank',
    ];

    if (isset($exact[$key])) {
        return $exact[$key];
    }

    if (strpos($key, 'stkb_unified_') === 0) {
        return substr_replace($key, 'opentt_unified_', 0, 13);
    }

    if (strpos($key, 'stkb_') === 0) {
        return substr_replace($key, 'opentt_', 0, 5);
    }

    return $key;
}

function normalizeStringValue(string $value): string
{
    $exact = [
        'stkb-data-transfer' => 'opentt-data-transfer',
    ];

    if (isset($exact[$value])) {
        return $exact[$value];
    }

    $out = $value;

    $out = str_replace('_stkb_legacy_ref_id', '_opentt_legacy_ref_id', $out);
    $out = str_replace('_stkb_import_source_attachment_id', '_opentt_import_source_attachment_id', $out);
    $out = str_replace('stkb_unified_', 'opentt_unified_', $out);
    $out = str_replace('wp_stkb_matches', 'wp_opentt_matches', $out);
    $out = str_replace('wp_stkb_games', 'wp_opentt_games', $out);
    $out = str_replace('wp_stkb_sets', 'wp_opentt_sets', $out);

    $out = str_replace('stkb_matches', 'opentt_matches', $out);
    $out = str_replace('stkb_games', 'opentt_games', $out);
    $out = str_replace('stkb_sets', 'opentt_sets', $out);

    $out = str_replace('[stkb_', '[opentt_', $out);
    $out = str_replace('[/stkb_', '[/opentt_', $out);

    return $out;
}

function convertNode($node)
{
    if (is_array($node)) {
        $isAssoc = array_keys($node) !== range(0, count($node) - 1);
        if ($isAssoc) {
            $out = [];
            foreach ($node as $key => $value) {
                $newKey = normalizeKey((string) $key);
                if ($newKey === 'format' && is_string($value)) {
                    $value = normalizeStringValue($value);
                }
                if ($newKey === 'sections' && is_array($value)) {
                    $sections = [];
                    foreach ($value as $section) {
                        $sections[] = is_string($section) ? normalizeSectionName($section) : $section;
                    }
                    $value = $sections;
                }
                if ($newKey === 'data' && is_array($value)) {
                    $dataOut = [];
                    foreach ($value as $sectionKey => $sectionRows) {
                        $normalizedSection = normalizeSectionName((string) $sectionKey);
                        $dataOut[$normalizedSection] = convertNode($sectionRows);
                    }
                    $value = $dataOut;
                } else {
                    $value = convertNode($value);
                }
                $out[$newKey] = $value;
            }
            return $out;
        }

        $out = [];
        foreach ($node as $value) {
            if (is_string($value)) {
                $out[] = normalizeSectionName(normalizeStringValue($value));
            } else {
                $out[] = convertNode($value);
            }
        }
        return $out;
    }

    if (is_string($node)) {
        return normalizeStringValue($node);
    }

    return $node;
}

function main(array $argv): int
{
    $parsed = parseArgs($argv);
    $in = trim((string) ($parsed['in'] ?? ''));
    $out = trim((string) ($parsed['out'] ?? ''));

    if ($in === '' || $out === '') {
        usage();
        return EXIT_USAGE;
    }

    if (!is_file($in) || !is_readable($in)) {
        stderr("Input file is missing or unreadable: {$in}");
        return EXIT_IO;
    }

    $raw = file_get_contents($in);
    if (!is_string($raw) || $raw === '') {
        stderr("Input file is empty or unreadable: {$in}");
        return EXIT_IO;
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        stderr('Input is not a valid JSON object.');
        return EXIT_JSON;
    }

    $converted = convertNode($json);

    if (!isset($converted['format']) || !is_string($converted['format']) || $converted['format'] === '') {
        $converted['format'] = 'opentt-data-transfer';
    }

    $encoded = json_encode($converted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || $encoded === '') {
        stderr('Failed to encode converted JSON.');
        return EXIT_JSON;
    }
    $encoded .= PHP_EOL;

    $dir = dirname($out);
    if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            stderr("Failed to create output directory: {$dir}");
            return EXIT_IO;
        }
    }

    $written = file_put_contents($out, $encoded);
    if ($written === false) {
        stderr("Failed to write output file: {$out}");
        return EXIT_IO;
    }

    fwrite(STDOUT, "Converted legacy export:\n");
    fwrite(STDOUT, "  Input : {$in}\n");
    fwrite(STDOUT, "  Output: {$out}\n");

    return EXIT_OK;
}

exit(main($argv));
