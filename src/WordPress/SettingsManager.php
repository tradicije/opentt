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

final class SettingsManager
{
    public static function resetVisualSettings($optionVisualSettingsKey)
    {
        update_option(
            (string) $optionVisualSettingsKey,
            \OpenTT\Unified\Infrastructure\VisualSettings::defaultSettings(),
            false
        );
    }

    public static function resetCustomCss($optionCssKey, $optionCssMapKey)
    {
        update_option((string) $optionCssKey, '', false);
        update_option((string) $optionCssMapKey, [], false);
    }

    public static function saveAdminUiLanguage($optionLanguageKey, array $availableLanguages, $rawLanguage, $default = 'sr')
    {
        $lang = sanitize_key((string) $rawLanguage);
        if (!isset($availableLanguages[$lang])) {
            $lang = (string) $default;
        }
        update_option((string) $optionLanguageKey, $lang, false);
    }

    public static function saveVisualSettings($optionVisualSettingsKey, $rawVisualSettings)
    {
        update_option(
            (string) $optionVisualSettingsKey,
            \OpenTT\Unified\Infrastructure\VisualSettings::sanitize($rawVisualSettings),
            false
        );
    }

    public static function saveCustomCssOverrides($optionCssKey, $optionCssMapKey, $rawCss, $rawCssMap)
    {
        $css = str_replace("\r\n", "\n", (string) $rawCss);
        update_option((string) $optionCssKey, $css, false);
        update_option((string) $optionCssMapKey, self::sanitizeCssMap($rawCssMap), false);
    }

    private static function sanitizeCssMap($rawCssMap)
    {
        if (!is_array($rawCssMap)) {
            return [];
        }

        $cssMap = [];
        foreach ($rawCssMap as $tag => $cssPart) {
            $tag = sanitize_key((string) $tag);
            if ($tag === '') {
                continue;
            }
            $cssPart = is_string($cssPart) ? (string) wp_unslash($cssPart) : '';
            $cssPart = str_replace("\r\n", "\n", $cssPart);
            $cssMap[$tag] = $cssPart;
        }
        return $cssMap;
    }
}
