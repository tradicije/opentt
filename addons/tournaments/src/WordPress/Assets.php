<?php

namespace OpenTT\Tournaments\WordPress;

use OpenTT\Tournaments\Plugin;

final class Assets
{
    public static function enqueueFrontend()
    {
        $css = Plugin::pluginDir() . 'assets/css/tournaments.css';
        if (is_readable($css)) {
            wp_enqueue_style(
                'opentt-tournaments',
                Plugin::pluginUrl() . 'assets/css/tournaments.css',
                [],
                filemtime($css)
            );
        }

        $js = Plugin::pluginDir() . 'assets/js/tournaments.js';
        if (is_readable($js)) {
            wp_enqueue_script(
                'opentt-tournaments',
                Plugin::pluginUrl() . 'assets/js/tournaments.js',
                [],
                filemtime($js),
                true
            );
        }
    }
}

