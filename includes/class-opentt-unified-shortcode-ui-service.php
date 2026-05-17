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

final class OpenTT_Unified_Shortcode_UI_Service
{
    public static function shortcode_title_html($title)
    {
        $title = trim((string) $title);
        if ($title === '') {
            return '';
        }
        if (class_exists('OpenTT_Unified_Core') && method_exists('OpenTT_Unified_Core', 'should_show_shortcode_titles') && !OpenTT_Unified_Core::should_show_shortcode_titles()) {
            return '';
        }
        return '<h3 class="opentt-shortcode-title">' . esc_html($title) . '</h3>';
    }

    public static function info_link_icon_html($icon_file_name, $fallback, $modifier = 'before', $plugin_dir = '')
    {
        $icon_file_name = sanitize_file_name((string) $icon_file_name);
        if ($icon_file_name !== '' && substr($icon_file_name, -4) !== '.svg') {
            $icon_file_name .= '.svg';
        }
        $modifier = sanitize_html_class((string) $modifier);
        $classes = 'opentt-info-link-icon opentt-info-link-icon--' . ($modifier !== '' ? $modifier : 'before');
        $fallback = (string) $fallback;
        $plugin_dir = (string) $plugin_dir;

        $rel_path = 'assets/icons/' . $icon_file_name;
        $full_path = $plugin_dir . $rel_path;
        if ($plugin_dir !== '' && is_readable($full_path)) {
            $svg = file_get_contents($full_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            if (is_string($svg) && trim($svg) !== '') {
                $svg = preg_replace('/<\?xml.*?\?>/i', '', $svg);
                $svg = preg_replace('/<!DOCTYPE.*?>/i', '', $svg);
                if (is_string($svg) && trim($svg) !== '') {
                    return '<span class="' . esc_attr($classes) . '" aria-hidden="true"><span class="opentt-info-link-icon-svg">' . $svg . '</span></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
            }
        }

        return '<span class="' . esc_attr($classes) . '" aria-hidden="true">' . esc_html($fallback) . '</span>';
    }

    public static function render_top_player_card_list($igrac_id, $rank, $info, $highlight = false, $deps = [])
    {
        $club_logo_html = isset($deps['club_logo_html']) && is_callable($deps['club_logo_html'])
            ? $deps['club_logo_html']
            : static function () {
                return '';
            };
        $player_fallback_image_url = isset($deps['player_fallback_image_url']) && is_callable($deps['player_fallback_image_url'])
            ? $deps['player_fallback_image_url']
            : static function () {
                return '';
            };

        $igrac_id = intval($igrac_id);
        if ($igrac_id <= 0) {
            return '';
        }

        $full_name = (string) get_the_title($igrac_id);
        $parts = explode(' ', $full_name, 2);
        $ime = isset($parts[0]) ? $parts[0] : '';
        $prezime = isset($parts[1]) ? $parts[1] : '';

        $slika = get_the_post_thumbnail($igrac_id, 'thumbnail', ['class' => 'igrac-slika']);
        if (empty($slika)) {
            $slika = '<img src="' . esc_url($player_fallback_image_url()) . '" alt="Igrač" class="igrac-slika" />';
        }

        $klub_id = intval($info['klub'] ?? 0);
        $grb = $klub_id ? $club_logo_html($klub_id, 'thumbnail', ['class' => 'igrac-klub-grb']) : '';
        $naziv_kluba = $klub_id ? (string) get_the_title($klub_id) : '';
        $wins = intval($info['pobede'] ?? 0);
        $losses = intval($info['porazi'] ?? 0);
        $total = $wins + $losses;
        $score = $wins . '-' . $losses;
        $percent = $total > 0 ? (string) round(($wins / $total) * 100) . '%' : '-';
        $highlight_class = $highlight ? ' highlight' : '';
        $igrac_link = get_permalink($igrac_id);
        ob_start();
        ?>
        <div class="igrac-card-list<?php echo esc_attr($highlight_class); ?>">
            <div class="igrac-rank"><?php echo intval($rank); ?></div>
            <a class="igrac-link" href="<?php echo esc_url($igrac_link); ?>">
                <div class="igrac-slika-wrap"><?php echo $slika; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            </a>
            <div class="igrac-imeprezime">
                <a class="igrac-link" href="<?php echo esc_url($igrac_link); ?>">
                    <div class="ime"><?php echo esc_html($ime); ?></div>
                    <div class="prezime"><?php echo esc_html($prezime); ?></div>
                </a>
                <div class="igrac-klub">
                    <?php if ($grb): ?>
                        <span class="igrac-klub-grb"><?php echo $grb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <?php endif; ?>
                    <span class="igrac-klub-naziv"><?php echo esc_html($naziv_kluba); ?></span>
                </div>
            </div>
            <div class="igrac-skor"><?php echo esc_html($score); ?></div>
            <div class="igrac-procenat"><?php echo esc_html($percent); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
