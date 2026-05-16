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

final class OpenTT_Unified_Entity_Presentation_Service
{
    public static function render_team_html($club_id, $score, $is_winner, $show_score = true, $fallback_score_label = '', array $deps = [])
    {
        $club_logo_html = isset($deps['club_logo_html']) && is_callable($deps['club_logo_html'])
            ? $deps['club_logo_html']
            : null;

        $class = $is_winner ? 'pobednik' : 'gubitnik';
        $name = $club_id ? get_the_title($club_id) : '';
        $crest = ($club_id && $club_logo_html) ? (string) $club_logo_html($club_id, 'thumbnail', []) : '';

        ob_start();
        echo '<div class="team ' . esc_attr($class) . '">';
        if ($crest !== '') {
            echo $crest; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '<span>' . esc_html($name) . '</span>';
        if ($show_score) {
            echo '<strong>' . esc_html((string) intval($score)) . '</strong>';
        } elseif ($fallback_score_label !== '') {
            echo '<strong class="team-time">' . esc_html((string) $fallback_score_label) . '</strong>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    public static function render_lp2_player($player_id, array $deps = [])
    {
        $player_fallback_image_url = isset($deps['player_fallback_image_url']) && is_callable($deps['player_fallback_image_url'])
            ? $deps['player_fallback_image_url']
            : null;

        $player_id = intval($player_id);
        if ($player_id <= 0) {
            return '';
        }

        $link = get_permalink($player_id);
        $fallback_url = $player_fallback_image_url ? (string) $player_fallback_image_url() : '';
        $thumb = has_post_thumbnail($player_id)
            ? get_the_post_thumbnail($player_id, 'thumbnail', ['class' => 'lp2-thumb'])
            : '<img src="' . esc_url($fallback_url) . '" alt="Igrač" class="lp2-thumb" />';

        $title = (string) get_the_title($player_id);
        $parts = explode(' ', $title, 2);
        $ime = isset($parts[0]) ? $parts[0] : '';
        $prezime = isset($parts[1]) ? $parts[1] : '';
        ob_start();
        echo '<div class="lp2-igrac-wrap">';
        echo '<a class="lp2-igrac" href="' . esc_url($link) . '">';
        echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<div class="lp2-name"><span>' . esc_html($ime) . '</span><span>' . esc_html($prezime) . '</span></div>';
        echo '</a>';
        echo '</div>';
        return ob_get_clean();
    }

    public static function render_klub_card_html($klub_id, array $deps = [])
    {
        $club_logo_html = isset($deps['club_logo_html']) && is_callable($deps['club_logo_html'])
            ? $deps['club_logo_html']
            : null;

        $klub_id = intval($klub_id);
        if ($klub_id <= 0) {
            return '';
        }
        $naziv = get_the_title($klub_id);
        $grb = $club_logo_html ? (string) $club_logo_html($klub_id, 'thumbnail', ['class' => 'opentt-grb']) : '';
        $link = get_permalink($klub_id);

        ob_start();
        ?>
        <div class="opentt-klub">
            <a href="<?php echo esc_url($link); ?>">
                <div class="opentt-grb-wrap"><?php echo $grb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <div class="opentt-naziv"><?php echo esc_html((string) $naziv); ?></div>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}

