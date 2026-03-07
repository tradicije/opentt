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

final class MatchReportShortcode
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

        $ctx = $call('current_match_context');
        if (!$ctx || empty($ctx['db_row'])) {
            return '';
        }
        $row = is_object($ctx['db_row']) ? $ctx['db_row'] : null;
        if (!$row) {
            return '';
        }
        $report_url = trim((string) ($row->report_url ?? ''));

        if ($report_url === '' && !empty($ctx['legacy_id'])) {
            $legacy_match_id = intval($ctx['legacy_id']);
            $q = new \WP_Query([
                'post_type' => 'post',
                'posts_per_page' => 1,
                'ignore_sticky_posts' => true,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => [[
                    'key' => 'povezana_utakmica',
                    'value' => $legacy_match_id,
                    'compare' => '=',
                ]],
            ]);
            if ($q->have_posts()) {
                $q->the_post();
                $report_url = (string) get_permalink();
                wp_reset_postdata();
            }
        }
        if ($report_url === '') {
            return '';
        }

        $linked_post_id = url_to_postid($report_url);
        $title = 'Izveštaj utakmice';
        $excerpt = '';
        $thumb = '';
        if ($linked_post_id > 0) {
            $title = (string) get_the_title($linked_post_id);
            $excerpt = (string) get_the_excerpt($linked_post_id);
            $thumb = (string) get_the_post_thumbnail($linked_post_id, 'medium');
        }
        if ($title === '') {
            $title = 'Izveštaj utakmice';
        }
        if ($excerpt === '') {
            $host = wp_parse_url($report_url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $excerpt = $host;
            }
        }

        ob_start();
        echo (string) $call('shortcode_title_html', 'Izveštaj utakmice');
        ?>
        <a href="<?php echo esc_url($report_url); ?>" class="izvestaj-utakmice-blok" target="_blank" rel="noopener noreferrer">
            <div class="izvestaj-leva-kolona">
                <?php echo $thumb ?: ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <div class="izvestaj-desna-kolona">
                <h3><?php echo esc_html($title); ?></h3>
                <?php if ($excerpt !== '') : ?><p><?php echo esc_html($excerpt); ?></p><?php endif; ?>
            </div>
        </a>
        <?php
        return ob_get_clean();
    }
}
