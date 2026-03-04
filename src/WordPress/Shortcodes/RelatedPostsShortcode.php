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

final class RelatedPostsShortcode
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

        if (!is_singular('post')) {
            return '';
        }

        $post_id = intval(get_the_ID());
        if ($post_id <= 0) {
            return '';
        }

        $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
        $args = [
            'post__not_in' => [$post_id],
            'posts_per_page' => 4,
            'ignore_sticky_posts' => 1,
        ];

        if (!empty($tags)) {
            $args['tag__in'] = $tags;
        } else {
            $categories = wp_get_post_categories($post_id, ['fields' => 'ids']);
            if (!empty($categories)) {
                $args['category__in'] = $categories;
            }
        }

        $related = new \WP_Query($args);
        if (!$related->have_posts()) {
            return '';
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Povezane objave');
        echo '<div class="bbs-related-posts">';
        while ($related->have_posts()) {
            $related->the_post();
            $category_list = get_the_category_list(', ');
            echo '<div class="related-post-item">';
            echo '<div class="related-post-thumb"><a href="' . esc_url(get_permalink()) . '">';
            if (has_post_thumbnail()) {
                echo get_the_post_thumbnail(get_the_ID(), 'medium'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '</a></div>';
            echo '<div class="related-post-content">';
            echo '<h3><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
            echo '<p class="excerpt">' . esc_html(get_the_excerpt()) . '</p>';
            echo '<div class="meta"><span class="category">' . ($category_list ? $category_list : '') . '</span><span class="date">' . esc_html(get_the_date()) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div></div>';
        }
        echo '</div>';
        wp_reset_postdata();

        return ob_get_clean();
    }
}
