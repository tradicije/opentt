<?php

namespace OpenTT\Unified;

final class Plugin
{
    public static function boot($pluginFile)
    {
        self::loadLegacyCore();

        register_activation_hook($pluginFile, function () use ($pluginFile) {
            \OpenTT_Unified_Core::activate($pluginFile);
        });

        \OpenTT_Unified_Core::init($pluginFile);
    }

    private static function loadLegacyCore()
    {
        if (\class_exists('\OpenTT_Unified_Core')) {
            return;
        }

        require_once dirname(__DIR__) . '/includes/class-opentt-unified-core.php';
    }
}
