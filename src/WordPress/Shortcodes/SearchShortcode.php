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

namespace OpenTT\Unified\WordPress\Shortcodes;

final class SearchShortcode
{
    public static function render($atts, array $deps)
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $atts = shortcode_atts([
            'placeholder' => 'Pretraži igrače, klubove, lige...',
            'min_chars' => '1',
            'limit' => '6',
            'liga' => '',
            'sezona' => '',
            'season' => '',
        ], $atts);

        $min_chars = max(1, min(4, intval($atts['min_chars'])));
        $limit = max(3, min(12, intval($atts['limit'])));
        $placeholder = trim((string) $atts['placeholder']);
        if ($placeholder === '') {
            $placeholder = 'Pretraži igrače, klubove, lige...';
        }

        $context = self::resolveContext($atts, $call);
        $uid = 'opentt-search-' . wp_unique_id();
        $icon_url = plugins_url('assets/icons/search-icon.svg', dirname(__DIR__, 3) . '/opentt-unified-core.php');

        $payload_json = wp_json_encode([
            'context' => $context,
            'minChars' => $min_chars,
            'limit' => $limit,
            'i18n' => [
                'inputLabel' => 'Pretraga',
                'loading' => 'Pretraga...',
                'empty' => 'Nema rezultata.',
                'prompt' => sprintf('Unesi najmanje %d karakter(a).', $min_chars),
                'categories' => [
                    'players' => 'Igrači',
                    'clubs' => 'Klubovi',
                    'leagues' => 'Lige i sezone',
                    'matches' => 'Utakmice',
                ],
                'historyLabel' => 'Istorija pretrage',
                'clearHistory' => 'Očisti istoriju pretrage',
            ],
        ]);

        if (!is_string($payload_json)) {
            $payload_json = '{}';
        }

        ob_start();
        echo '<div id="' . esc_attr($uid) . '" class="opentt-search" data-opentt-search="1">';
        echo '<button type="button" class="opentt-search-toggle" aria-expanded="false" aria-controls="' . esc_attr($uid . '-panel') . '" aria-label="Open search">';
        echo '<img class="opentt-search-toggle-icon" src="' . esc_url($icon_url) . '" alt="" aria-hidden="true">';
        echo '</button>';
        echo '<div class="opentt-search-backdrop" hidden></div>';
        echo '<div id="' . esc_attr($uid . '-panel') . '" class="opentt-search-panel" hidden>';
        echo '<button type="button" class="opentt-search-close" aria-label="Zatvori pretragu">&times;</button>';
        echo '<label class="opentt-search-label" for="' . esc_attr($uid . '-input') . '">' . esc_html('Pretraga') . '</label>';
        echo '<input id="' . esc_attr($uid . '-input') . '" class="opentt-search-input" type="search" autocomplete="off" placeholder="' . esc_attr($placeholder) . '" />';
        echo '<div class="opentt-search-results" data-opentt-search-results><p class="opentt-search-empty">' . esc_html(sprintf('Unesi najmanje %d karakter(a).', $min_chars)) . '</p></div>';
        echo '</div>';
        echo '<script type="application/json" class="opentt-search-data">' . $payload_json . '</script>';
        echo '</div>';

        return ob_get_clean();
    }

    private static function resolveContext(array $atts, callable $call)
    {
        $context = [
            'type' => 'generic',
            'match_id' => 0,
            'liga_slug' => '',
            'sezona_slug' => '',
            'home_club_id' => 0,
            'away_club_id' => 0,
        ];

        $match_ctx = $call('current_match_context');
        if (is_array($match_ctx) && !empty($match_ctx['db_row']) && is_object($match_ctx['db_row'])) {
            $row = $match_ctx['db_row'];
            $context['type'] = 'match';
            $context['match_id'] = intval($row->id ?? 0);
            $context['liga_slug'] = sanitize_title((string) ($row->liga_slug ?? ''));
            $context['sezona_slug'] = sanitize_title((string) ($row->sezona_slug ?? ''));
            $context['home_club_id'] = intval($row->home_club_post_id ?? 0);
            $context['away_club_id'] = intval($row->away_club_post_id ?? 0);
        }

        $archive_ctx = $call('current_archive_context');
        if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
            if ($context['type'] === 'generic') {
                $context['type'] = 'competition';
            }
            if ($context['liga_slug'] === '') {
                $context['liga_slug'] = sanitize_title((string) ($archive_ctx['liga_slug'] ?? ''));
            }
            if ($context['sezona_slug'] === '') {
                $context['sezona_slug'] = sanitize_title((string) ($archive_ctx['sezona_slug'] ?? ''));
            }
        }

        $liga_from_atts = sanitize_title((string) ($atts['liga'] ?? ''));
        $sezona_from_atts = sanitize_title((string) ($atts['season'] ?? ''));
        if ($sezona_from_atts === '') {
            $sezona_from_atts = sanitize_title((string) ($atts['sezona'] ?? ''));
        }
        if ($liga_from_atts !== '' || $sezona_from_atts !== '') {
            $parsed = $call('parse_legacy_liga_sezona', $liga_from_atts, $sezona_from_atts);
            if (is_array($parsed)) {
                $liga_from_atts = sanitize_title((string) ($parsed['league_slug'] ?? $liga_from_atts));
                $sezona_from_atts = sanitize_title((string) ($parsed['season_slug'] ?? $sezona_from_atts));
            }
            if ($liga_from_atts !== '') {
                $context['liga_slug'] = $liga_from_atts;
            }
            if ($sezona_from_atts !== '') {
                $context['sezona_slug'] = $sezona_from_atts;
            }
            if ($context['type'] === 'generic') {
                $context['type'] = 'competition';
            }
        }

        return $context;
    }
}
