<?php

namespace OpenTT\Tournaments\WordPress;

use OpenTT\Tournaments\Plugin;

final class Templates
{
    public static function templateInclude($template)
    {
        if (!is_singular(Plugin::CPT)) {
            return $template;
        }

        if (function_exists('wp_is_block_theme') && wp_is_block_theme()) {
            $bridge = Plugin::pluginDir() . 'templates/block-tournament-template.php';
            if (is_readable($bridge)) {
                return $bridge;
            }
        }

        $theme = self::findThemeTemplate();
        if ($theme !== '') {
            return $theme;
        }

        $fallback = Plugin::pluginDir() . 'templates/single-turnir.php';
        return is_readable($fallback) ? $fallback : $template;
    }

    public static function renderBlockTemplateContent()
    {
        $tpl = self::getBlockTemplate(Plugin::TEMPLATE_SLUG);
        if ($tpl && !empty($tpl->content)) {
            return do_shortcode(self::renderBlocksWithShortcodeSupport((string) $tpl->content));
        }
        return self::fallbackContent();
    }

    public static function fallbackContent()
    {
        return '<main class="opentt-tournament-page" style="max-width:1180px;margin:0 auto;padding:20px 16px;">' . do_shortcode('[opentt_tournament]') . '</main>';
    }

    private static function findThemeTemplate()
    {
        $candidates = [
            trailingslashit(get_stylesheet_directory()) . Plugin::TEMPLATE_SLUG . '.php',
            trailingslashit(get_stylesheet_directory()) . 'stkb/' . Plugin::TEMPLATE_SLUG . '.php',
            trailingslashit(get_template_directory()) . Plugin::TEMPLATE_SLUG . '.php',
            trailingslashit(get_template_directory()) . 'stkb/' . Plugin::TEMPLATE_SLUG . '.php',
            trailingslashit(get_stylesheet_directory()) . 'single-turnir.php',
            trailingslashit(get_template_directory()) . 'single-turnir.php',
        ];
        foreach ($candidates as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }
        return '';
    }

    private static function getBlockTemplate($slug)
    {
        if (!function_exists('get_block_template')) {
            return null;
        }
        $slug = sanitize_key((string) $slug);
        if ($slug === '') {
            return null;
        }
        $theme = get_stylesheet();
        $tpl = get_block_template($theme . '//' . $slug, 'wp_template');
        if ($tpl) {
            return $tpl;
        }
        $parent = get_template();
        if ($parent && $parent !== $theme) {
            $tpl = get_block_template($parent . '//' . $slug, 'wp_template');
            if ($tpl) {
                return $tpl;
            }
        }
        $posts = get_posts([
            'post_type' => 'wp_template',
            'name' => $slug,
            'numberposts' => 1,
            'post_status' => ['publish', 'draft'],
        ]);
        if (!empty($posts[0])) {
            return (object) ['content' => $posts[0]->post_content];
        }

        $posts = get_posts([
            'post_type' => 'wp_template',
            'posts_per_page' => 20,
            'post_status' => ['publish', 'draft'],
            's' => '//' . $slug,
        ]);
        foreach ($posts as $post) {
            if (strpos((string) $post->post_name, '//' . $slug) !== false) {
                return (object) ['content' => $post->post_content];
            }
        }

        return null;
    }

    private static function renderBlocksWithShortcodeSupport($content)
    {
        if (!function_exists('parse_blocks')) {
            return do_shortcode((string) $content);
        }
        $blocks = parse_blocks((string) $content);
        if (!is_array($blocks) || empty($blocks)) {
            return do_shortcode((string) $content);
        }
        $out = '';
        foreach ($blocks as $block) {
            $name = isset($block['blockName']) ? (string) $block['blockName'] : '';
            if ($name === 'core/shortcode') {
                $raw = '';
                if (!empty($block['innerHTML'])) {
                    $raw = (string) $block['innerHTML'];
                } elseif (!empty($block['innerContent']) && is_array($block['innerContent'])) {
                    $raw = implode('', array_filter($block['innerContent'], 'is_string'));
                }
                $out .= do_shortcode(trim($raw));
                continue;
            }
            $out .= render_block($block);
        }
        return $out;
    }
}
