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

namespace OpenTT\Unified\WordPress\Shortcodes;

final class PlayerNewsShortcode
{
    public static function render($atts, array $deps)
    {
        $call = static function ($name, ...$args) use ($deps) {
            $name = (string) $name;
            if (!isset($deps[$name]) || !is_callable($deps[$name])) {
                return null;
            }
            return $deps[$name](...$args);
        };

        $atts = shortcode_atts([
            'igrac' => '',
            'limit' => 6,
            'columns' => 3,
        ], $atts);

        $player_name = '';
        $tag_slug = '';
        if (empty($atts['igrac']) && is_singular('igrac')) {
            $player_name = (string) get_the_title(get_the_ID());
            $tag_slug = sanitize_title($player_name);
        }
        if (!empty($atts['igrac'])) {
            $player_name = (string) $atts['igrac'];
            $tag_slug = sanitize_title((string) $atts['igrac']);
        }

        if ($tag_slug === '' && $player_name === '') {
            return '<p>Nema pronađenih vesti za ovog igrača.</p>';
        }

        $limit = intval($atts['limit']);
        if ($limit === 0) {
            $limit = -1;
        }
        $columns = intval($atts['columns']);
        if ($columns < 1 || $columns > 6) {
            $columns = 3;
        }

        $q = new \WP_Query([
            'post_type' => 'post',
            'posts_per_page' => $limit,
            'tag' => $tag_slug,
        ]);

        // Fallback: ako nema tag pogodaka, probaj po nazivu igrača u sadržaju.
        if (!$q->have_posts() && $player_name !== '') {
            $q = new \WP_Query([
                'post_type' => 'post',
                'posts_per_page' => $limit,
                's' => $player_name,
            ]);
        }

        if (!$q->have_posts()) {
            return (string) $call('shortcode_title_html', 'Vesti igrača') . '<p>Trenutno nema vesti za ovog igrača.</p>';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Vesti igrača');
        echo '<div class="stoni-vesti-grid stoni-vesti-cols-' . esc_attr((string) $columns) . '">';
        while ($q->have_posts()) {
            $q->the_post();
            $link = get_permalink();
            $title = get_the_title();
            $date = get_the_date();
            $thumbnail = get_the_post_thumbnail(get_the_ID(), 'medium_large', ['class' => 'vest-klub-slika']);

            echo '<a class="stoni-vesti-kartica" href="' . esc_url($link) . '">';
            echo $thumbnail ?: '<div class="vest-klub-slika prazna"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<div class="vest-klub-naslov">' . esc_html($title) . '</div>';
            echo '<div class="vest-klub-datum">' . esc_html((string) $date) . '</div>';
            echo '</a>';
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean();
    }
}
