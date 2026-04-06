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

final class FrontendAssetsEnqueuer
{
    public static function enqueue($pluginDir, $pluginFile, $version, $visualCss, $customCss, $customCssMap)
    {
        wp_register_script(
            'opentt-html2canvas',
            'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',
            [],
            '1.4.1',
            true
        );

        $jsRel = 'assets/js/frontend.js';
        $jsPath = (string) $pluginDir . $jsRel;
        if (is_readable($jsPath)) {
            wp_enqueue_script(
                'opentt-unified-frontend',
                plugins_url($jsRel, $pluginFile),
                ['opentt-html2canvas'],
                filemtime($jsPath),
                true
            );
            wp_localize_script('opentt-unified-frontend', 'openttFrontend', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'searchNonce' => wp_create_nonce('opentt_frontend_search'),
            ]);
        }

        $modules = [
            'ekipe',
            'utakmice',
            'tabela',
            'takmicenje-info',
            'takmicenja-prikaz',
            'forma-kluba',
            'statistika-igraca',
            'statistika-ekipe',
            'transferi',
            'partije',
            'mvp',
            'h2h',
            'snimak',
            'izvestaj',
            'rang-lista',
            'prikaz-igraca',
            'vesti-kluba',
            'related-posts',
            'info-kluba',
            'info-igraca',
            'prikaz-klubova',
            'featured-match',
            'search',
        ];

        foreach ($modules as $mod) {
            $rel = 'assets/css/modules/' . $mod . '.css';
            $path = (string) $pluginDir . $rel;
            if (!is_readable($path)) {
                continue;
            }
            wp_enqueue_style(
                'opentt-unified-' . $mod,
                plugins_url($rel, $pluginFile),
                [],
                filemtime($path)
            );
        }

        if (!is_array($customCssMap)) {
            $customCssMap = [];
        }

        $chunks = [];
        $visualCss = trim((string) $visualCss);
        if ($visualCss !== '') {
            $chunks[] = $visualCss;
        }

        $globalCss = trim((string) $customCss);
        if ($globalCss !== '') {
            $chunks[] = $globalCss;
        }

        foreach ($customCssMap as $cssPart) {
            if (!is_string($cssPart)) {
                continue;
            }
            $cssPart = trim($cssPart);
            if ($cssPart !== '') {
                $chunks[] = $cssPart;
            }
        }

        if (!empty($chunks)) {
            wp_register_style('opentt-unified-custom-overrides', false, [], $version);
            wp_enqueue_style('opentt-unified-custom-overrides');
            wp_add_inline_style('opentt-unified-custom-overrides', implode("\n\n", $chunks));
        }
    }
}
