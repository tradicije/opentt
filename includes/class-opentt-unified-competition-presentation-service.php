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

final class OpenTT_Unified_Competition_Presentation_Service
{
    public static function competition_display_name($liga_slug, $sezona_slug, array $deps = [])
    {
        $slug_to_title = isset($deps['slug_to_title']) && is_callable($deps['slug_to_title'])
            ? $deps['slug_to_title']
            : null;
        if (!$slug_to_title) {
            return '';
        }

        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);

        if ($liga_slug === '' && $sezona_slug === '') {
            return '';
        }

        $liga_name = $liga_slug !== '' ? (string) $slug_to_title($liga_slug) : '';
        if ($liga_name === '' && $liga_slug !== '') {
            $liga_name = (string) $liga_slug;
        }

        if ($sezona_slug === '') {
            return $liga_name;
        }

        return trim($liga_name . ', Sezona ' . self::season_display_name($sezona_slug, $deps));
    }

    public static function season_display_name($sezona_slug, array $deps = [])
    {
        $slug_to_title = isset($deps['slug_to_title']) && is_callable($deps['slug_to_title'])
            ? $deps['slug_to_title']
            : null;

        $sezona_slug = sanitize_title((string) $sezona_slug);
        if ($sezona_slug === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2,4})$/', $sezona_slug, $m)) {
            $second = (string) $m[2];
            if (strlen($second) === 4) {
                $second = substr($second, 2);
            }
            return $m[1] . '/' . $second;
        }

        if ($slug_to_title) {
            return (string) $slug_to_title($sezona_slug);
        }
        return $sezona_slug;
    }

    public static function competition_archive_url($liga_slug, $sezona_slug)
    {
        $liga_slug = sanitize_title((string) $liga_slug);
        $sezona_slug = sanitize_title((string) $sezona_slug);

        if ($liga_slug === '') {
            return '';
        }

        $term_candidates = [];
        if ($sezona_slug !== '') {
            $term_candidates[] = $liga_slug . '-' . $sezona_slug;
        }
        $term_candidates[] = $liga_slug;

        foreach ($term_candidates as $term_slug) {
            $term = get_term_by('slug', $term_slug, 'liga_sezona');
            if ($term && !is_wp_error($term)) {
                $term_link = get_term_link($term);
                if (!is_wp_error($term_link)) {
                    return (string) $term_link;
                }
            }
        }

        if ((string) get_option('permalink_structure', '') === '') {
            $base = home_url('/');
            $args = ['liga' => $liga_slug];
            if ($sezona_slug !== '') {
                $args['sezona'] = $sezona_slug;
            }
            return add_query_arg($args, $base);
        }

        if ($sezona_slug !== '') {
            return home_url('/liga/' . rawurlencode($liga_slug) . '/' . rawurlencode($sezona_slug) . '/');
        }

        return home_url('/liga/' . rawurlencode($liga_slug) . '/');
    }

    public static function kolo_name_from_slug($slug, array $deps = [])
    {
        $extract_round_no = isset($deps['extract_round_no']) && is_callable($deps['extract_round_no'])
            ? $deps['extract_round_no']
            : null;
        if (!$extract_round_no) {
            return '';
        }

        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return '';
        }

        $term_name = '';
        $term = get_term_by('slug', $slug, 'kolo');
        if ($term && !is_wp_error($term) && !empty($term->name)) {
            $term_name = trim((string) $term->name);
        }

        $candidate = $term_name !== '' ? $term_name : $slug;
        $candidate_slug = sanitize_title($candidate);
        $round_no = intval($extract_round_no($candidate_slug !== '' ? $candidate_slug : $candidate));
        if ($round_no > 0) {
            if ($candidate_slug === (string) $round_no || strpos($candidate_slug, 'kolo') !== false) {
                return $round_no . '. kolo';
            }
        }

        if ($term_name !== '') {
            return $term_name;
        }

        return OpenTT_Unified_Readonly_Helpers::slug_to_title($slug);
    }

    public static function kolo_heading_label($kolo_slug, $kolo_no = null, array $deps = [])
    {
        $extract_round_no = isset($deps['extract_round_no']) && is_callable($deps['extract_round_no'])
            ? $deps['extract_round_no']
            : null;
        if (!$extract_round_no) {
            return '';
        }

        $kolo_slug = sanitize_title((string) $kolo_slug);
        $computed_no = ($kolo_no === null) ? intval($extract_round_no($kolo_slug)) : intval($kolo_no);
        if ($computed_no > 0) {
            return $computed_no . '. kolo';
        }

        $kolo_name = self::kolo_name_from_slug($kolo_slug, $deps);
        if ($kolo_name !== '') {
            return $kolo_name;
        }

        if (isset($deps['slug_to_title']) && is_callable($deps['slug_to_title'])) {
            return (string) $deps['slug_to_title']($kolo_slug);
        }

        return OpenTT_Unified_Readonly_Helpers::slug_to_title($kolo_slug);
    }
}

