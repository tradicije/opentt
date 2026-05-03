<?php

namespace OpenTT\Tournaments;

final class Plugin
{
    const VERSION = '0.1.0';
    const OPTION_SCHEMA_VERSION = 'opentt_tournaments_schema_version';
    const SCHEMA_VERSION = '1';
    const OPTION_REWRITE_FLUSHED = 'opentt_tournaments_rewrite_flushed';
    const CPT = 'turnir';
    const TEMPLATE_SLUG = 'opentt-tournament';
    const CAP = 'edit_others_posts';

    private static $booted = false;
    private static $pluginFile = '';
    private static $pluginDir = '';
    private static $pluginUrl = '';

    public static function boot($pluginFile)
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        self::$pluginFile = (string) $pluginFile;
        self::$pluginDir = trailingslashit(dirname(self::$pluginFile));
        self::$pluginUrl = trailingslashit(plugin_dir_url(self::$pluginFile));

        self::registerAutoloader();

        if (function_exists('register_activation_hook')) {
            register_activation_hook(self::$pluginFile, [__CLASS__, 'activate']);
        }

        add_action('init', [WordPress\ContentTypes::class, 'register'], 9);
        add_action('init', [__CLASS__, 'maybeMigrateSchema'], 20);
        add_action('init', [__CLASS__, 'maybeFlushRewriteRulesOnce'], 100);
        add_action('init', [WordPress\Shortcodes::class, 'register'], 99);
        add_action('admin_menu', [WordPress\Admin::class, 'registerMenu'], 60);
        add_action('admin_post_opentt_tournaments_save_tournament', [WordPress\Admin::class, 'handleSaveTournament']);
        add_action('admin_post_opentt_tournaments_delete_tournament', [WordPress\Admin::class, 'handleDeleteTournament']);
        add_action('admin_post_opentt_tournaments_save_category', [WordPress\Admin::class, 'handleSaveCategory']);
        add_action('admin_post_opentt_tournaments_delete_category', [WordPress\Admin::class, 'handleDeleteCategory']);
        add_action('admin_post_opentt_tournaments_save_entry', [WordPress\Admin::class, 'handleSaveEntry']);
        add_action('admin_post_opentt_tournaments_delete_entry', [WordPress\Admin::class, 'handleDeleteEntry']);
        add_action('admin_post_opentt_tournaments_generate_bracket', [WordPress\Admin::class, 'handleGenerateBracket']);
        add_action('admin_post_opentt_tournaments_save_match', [WordPress\Admin::class, 'handleSaveMatch']);
        add_action('wp_enqueue_scripts', [WordPress\Assets::class, 'enqueueFrontend']);
        add_filter('template_include', [WordPress\Templates::class, 'templateInclude'], 80);
    }

    public static function activate()
    {
        WordPress\ContentTypes::register();
        self::maybeMigrateSchema(true);
        flush_rewrite_rules(false);
        update_option(self::OPTION_REWRITE_FLUSHED, '1', false);
    }

    public static function maybeMigrateSchema($force = false)
    {
        Infrastructure\Schema::migrate(self::SCHEMA_VERSION, self::OPTION_SCHEMA_VERSION, (bool) $force);
    }

    public static function maybeFlushRewriteRulesOnce()
    {
        if ((string) get_option(self::OPTION_REWRITE_FLUSHED, '') === '1') {
            return;
        }
        flush_rewrite_rules(false);
        update_option(self::OPTION_REWRITE_FLUSHED, '1', false);
    }

    public static function pluginFile()
    {
        return self::$pluginFile;
    }

    public static function pluginDir()
    {
        return self::$pluginDir;
    }

    public static function pluginUrl()
    {
        return self::$pluginUrl;
    }

    public static function isOpenTTEmbedded()
    {
        return class_exists('\OpenTT_Unified_Core');
    }

    private static function registerAutoloader()
    {
        spl_autoload_register(static function ($class) {
            $prefix = 'OpenTT\\Tournaments\\';
            if (strpos((string) $class, $prefix) !== 0) {
                return;
            }
            $relative = substr((string) $class, strlen($prefix));
            $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
}

