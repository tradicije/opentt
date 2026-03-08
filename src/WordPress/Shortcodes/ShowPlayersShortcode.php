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

final class ShowPlayersShortcode
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
        ], $atts);

        $klub_id = 0;
        if (!empty($atts['klub'])) {
            $klub_post = get_page_by_path(sanitize_title((string) $atts['klub']), OBJECT, 'klub');
            if (!$klub_post) {
                $klub_post = get_page_by_title((string) $atts['klub'], OBJECT, 'klub');
            }
            if ($klub_post && !is_wp_error($klub_post)) {
                $klub_id = intval($klub_post->ID);
            }
        } elseif (is_singular('klub')) {
            $klub_id = intval(get_the_ID());
        }

        if ($klub_id <= 0) {
            return '<p>Nije pronađen klub.</p>';
        }

        $q = new \WP_Query([
            'post_type' => 'igrac',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => 'povezani_klub',
                    'value' => $klub_id,
                    'compare' => '=',
                ],
                [
                    'key' => 'klub_igraca',
                    'value' => $klub_id,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        if (!$q->have_posts()) {
            return (string) $call('shortcode_title_html', 'Igrači') . '<p>Nema registrovanih igrača za ovaj klub.</p>';
        }

        $scope = self::resolve_elo_scope($call);
        $liga_slug = (string) ($scope['liga_slug'] ?? '');
        $sezona_slug = (string) ($scope['sezona_slug'] ?? '');

        ob_start();
        $uid = 'opentt-players-' . wp_unique_id();
        $visible_limit = 5;
        $idx = 0;
        echo (string) $call('shortcode_title_html', 'Igrači');
        echo '<div id="' . esc_attr($uid) . '" class="stoni-igraci-list-wrap">';
        echo '<div class="stoni-igraci-list">';
        while ($q->have_posts()) {
            $q->the_post();
            $idx++;
            $id = intval(get_the_ID());
            $slika = get_the_post_thumbnail($id, 'medium', ['class' => 'stoni-igrac-slika']);
            if (!$slika) {
                $slika = '<img src="' . esc_url((string) $call('player_fallback_image_url')) . '" alt="Igrač" class="stoni-igrac-slika" />';
            }
            $ime = (string) get_the_title($id);
            $link = get_permalink($id);
            $elo = \OpenTT\Unified\Infrastructure\EloRatingManager::getPlayerRating($id, $liga_slug, $sezona_slug);

            $ime_ime = $ime;
            $ime_prezime = '';
            if (strpos($ime, ' ') !== false) {
                $parts = explode(' ', $ime, 2);
                $ime_ime = (string) ($parts[0] ?? '');
                $ime_prezime = (string) ($parts[1] ?? '');
            }

            $hidden_attr = ($idx > $visible_limit) ? ' hidden data-opentt-player-extra="1"' : '';
            echo '<div class="stoni-igrac-card"' . $hidden_attr . '>';
            echo '<a href="' . esc_url($link) . '" class="stoni-igrac-row">';
            echo '<div class="stoni-igrac-left">';
            echo '<div class="stoni-igrac-slika-wrap">';
            echo $slika; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<span class="opentt-elo-badge">ELO ' . esc_html((string) $elo) . '</span>';
            echo '</div>';
            echo '<div class="stoni-igrac-ime">';
            echo '<span class="stoni-igrac-ime-ime">' . esc_html($ime_ime) . '</span>';
            echo '<span class="stoni-igrac-ime-prezime">' . esc_html($ime_prezime) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="stoni-igrac-right"><span class="stoni-igrac-detalji">Detalji igrača</span></div>';
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
        if ($idx > $visible_limit) {
            echo '<button type="button" class="opentt-stat-ekipe-toggle stoni-igraci-toggle" data-open-text="Prikaži sve" data-close-text="Sakrij">Prikaži sve</button>';
        }
        echo '</div>';
        wp_reset_postdata();

        if ($idx > $visible_limit) {
            ?>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js($uid); ?>');
                if (!root) { return; }
                var toggle = root.querySelector('.stoni-igraci-toggle');
                if (!toggle) { return; }
                var items = root.querySelectorAll('[data-opentt-player-extra="1"]');
                if (!items.length) { return; }
                toggle.addEventListener('click', function(){
                    var opening = toggle.getAttribute('data-opened') !== '1';
                    items.forEach(function(item){
                        if (opening) {
                            item.removeAttribute('hidden');
                        } else {
                            item.setAttribute('hidden', 'hidden');
                        }
                    });
                    toggle.setAttribute('data-opened', opening ? '1' : '0');
                    toggle.textContent = opening
                        ? (toggle.getAttribute('data-close-text') || 'Sakrij')
                        : (toggle.getAttribute('data-open-text') || 'Prikaži sve');
                });
            })();
            </script>
            <?php
        }

        return ob_get_clean();
    }

    private static function resolve_elo_scope(callable $call)
    {
        $match_ctx = $call('current_match_context');
        if (is_array($match_ctx) && !empty($match_ctx['db_row']) && is_object($match_ctx['db_row'])) {
            return [
                'liga_slug' => sanitize_title((string) ($match_ctx['db_row']->liga_slug ?? '')),
                'sezona_slug' => sanitize_title((string) ($match_ctx['db_row']->sezona_slug ?? '')),
            ];
        }

        $archive_ctx = $call('current_archive_context');
        if (is_array($archive_ctx) && ($archive_ctx['type'] ?? '') === 'liga_sezona') {
            return [
                'liga_slug' => sanitize_title((string) ($archive_ctx['liga_slug'] ?? '')),
                'sezona_slug' => sanitize_title((string) ($archive_ctx['sezona_slug'] ?? '')),
            ];
        }

        return [
            'liga_slug' => sanitize_title((string) (get_query_var('liga') ?: '')),
            'sezona_slug' => sanitize_title((string) (get_query_var('sezona') ?: '')),
        ];
    }
}
