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

final class OpenTT_Unified_Match_Presentation_Service
{
    public static function match_venue_label($row)
    {
        if (is_object($row)) {
            $direct_keys = ['location', 'lokacija', 'lokacija_utakmice'];
            foreach ($direct_keys as $key) {
                if (!isset($row->{$key})) {
                    continue;
                }
                $value = trim((string) $row->{$key});
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $legacy_id = isset($row->legacy_post_id) ? intval($row->legacy_post_id) : 0;
        if ($legacy_id > 0) {
            $keys = [
                'mesto_odigravanja',
                'mesto_utakmice',
                'lokacija_utakmice',
                'lokacija',
                'hala',
                'sala',
                'teren',
                'mesto',
            ];
            foreach ($keys as $key) {
                $value = trim((string) get_post_meta($legacy_id, $key, true));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    public static function display_match_time($match_date)
    {
        $match_date = (string) $match_date;
        if ($match_date === '' || $match_date === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = self::parse_match_timestamp($match_date);
        if ($ts === false) {
            return '';
        }
        return date_i18n('H:i', $ts);
    }

    public static function is_match_live($row)
    {
        if (!is_object($row)) {
            return false;
        }
        return intval($row->live ?? 0) === 1;
    }

    public static function parse_match_timestamp($match_date, $end_of_day_if_midnight = false)
    {
        $match_date = trim((string) $match_date);
        if ($match_date === '' || $match_date === '0000-00-00 00:00:00') {
            return false;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $match_date)) {
            $match_date .= ' 00:00:00';
        }

        $tz = wp_timezone();
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]+(\d{1,2}):(\d{2})(?::(\d{2}))?)?/', $match_date, $m)) {
            $year = intval($m[1]);
            $month = intval($m[2]);
            $day = intval($m[3]);
            $hour = isset($m[4]) ? intval($m[4]) : 0;
            $minute = isset($m[5]) ? intval($m[5]) : 0;
            $second = isset($m[6]) ? intval($m[6]) : 0;
            if (checkdate($month, $day, $year) && $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 && $second >= 0 && $second <= 59) {
                $dt = (new \DateTimeImmutable('now', $tz))
                    ->setDate($year, $month, $day)
                    ->setTime($hour, $minute, $second);
                if ($end_of_day_if_midnight && $hour === 0 && $minute === 0 && $second === 0) {
                    $dt = $dt->setTime(23, 59, 59);
                }
                return $dt->getTimestamp();
            }
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d G:i:s', 'Y-m-d H:i', 'Y-m-d G:i'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $match_date, $tz);
            if (!($dt instanceof \DateTimeImmutable)) {
                continue;
            }
            if ($end_of_day_if_midnight && preg_match('/\s00:00(?::00)?$/', $match_date)) {
                $dt = $dt->setTime(23, 59, 59);
            }
            return $dt->getTimestamp();
        }

        return false;
    }

    public static function match_permalink($row, array $deps = [])
    {
        $legacy_checker = isset($deps['is_legacy_match_cpt_enabled']) && is_callable($deps['is_legacy_match_cpt_enabled'])
            ? $deps['is_legacy_match_cpt_enabled']
            : null;

        $legacy_id = isset($row->legacy_post_id) ? intval($row->legacy_post_id) : 0;
        if (
            $legacy_checker
            && $legacy_checker()
            && $legacy_id > 0
            && get_post_type($legacy_id) === 'utakmica'
        ) {
            return get_permalink($legacy_id);
        }

        $liga = isset($row->liga_slug) ? sanitize_title((string) $row->liga_slug) : '';
        $sezona = isset($row->sezona_slug) ? sanitize_title((string) $row->sezona_slug) : '';
        $kolo = isset($row->kolo_slug) ? sanitize_title((string) $row->kolo_slug) : '';
        $slug = isset($row->slug) ? sanitize_title((string) $row->slug) : '';

        if ($liga === '' || $kolo === '' || $slug === '') {
            return home_url('/');
        }

        $path = '/' . $liga . '/';
        if ($sezona !== '') {
            $path .= $sezona . '/';
        }
        $path .= $kolo . '/' . $slug . '/';

        return home_url($path);
    }

    public static function display_match_date($match_date)
    {
        return OpenTT_Unified_Readonly_Helpers::display_match_date($match_date);
    }

    public static function display_match_date_long($match_date)
    {
        return OpenTT_Unified_Readonly_Helpers::display_match_date_long($match_date);
    }
}
