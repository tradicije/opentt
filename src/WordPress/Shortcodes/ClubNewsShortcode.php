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

final class ClubNewsShortcode
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
            'klub' => '',
            'limit' => 6,
            'columns' => 3,
        ], $atts);

        $tag_slug = '';
        if (empty($atts['klub']) && is_singular('klub')) {
            $tag_slug = sanitize_title((string) get_the_title(get_the_ID()));
        }
        if (!empty($atts['klub'])) {
            $tag_slug = sanitize_title((string) $atts['klub']);
        }

        if ($tag_slug === '') {
            return '<p>Nema pronađenih vesti za ovaj klub.</p>';
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

        if (!$q->have_posts()) {
            return (string) $call('shortcode_title_html', 'Vesti kluba') . '<p>Trenutno nema vesti za ovaj klub.</p>';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Vesti kluba');
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
