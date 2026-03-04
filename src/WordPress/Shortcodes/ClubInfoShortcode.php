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

final class ClubInfoShortcode
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

        $club_id = 0;
        if (!empty($atts['klub'])) {
            $lookup = sanitize_title((string) $atts['klub']);
            $post = get_page_by_path($lookup, OBJECT, 'klub');
            if (!$post) {
                $post = get_page_by_title((string) $atts['klub'], OBJECT, 'klub');
            }
            if ($post && !is_wp_error($post)) {
                $club_id = intval($post->ID);
            }
        } elseif (is_singular('klub')) {
            $club_id = intval(get_the_ID());
        }

        if ($club_id <= 0) {
            return '';
        }

        $club_name = (string) get_the_title($club_id);
        $club_logo = (string) $call('club_logo_html', $club_id, 'medium', ['class' => 'opentt-info-kluba-grb']);
        $club_link = (string) get_permalink($club_id);
        $club_display_name = 'STK ' . $club_name;

        $fields = [
            'grad' => 'Grad',
            'opstina' => 'Opština',
            'kontakt' => 'Kontakt',
            'email' => 'Email',
            'zastupnik_kluba' => 'Zastupnik kluba',
            'website_kluba' => 'Website kluba',
            'boja_dresa' => 'Boja dresa',
            'loptice' => 'Loptice',
            'adresa_kluba' => 'Adresa kluba',
            'adresa_sale' => 'Adresa sale',
            'termin_igranja' => 'Termin igranja',
        ];

        $rows = [];
        $club_meta_subtitle = '';

        $club_comp = $call('db_get_latest_competition_for_club', $club_id);
        if (is_array($club_comp)) {
            $liga_slug = sanitize_title((string) ($club_comp['liga_slug'] ?? ''));
            $sezona_slug = sanitize_title((string) ($club_comp['sezona_slug'] ?? ''));
            if ($liga_slug !== '' && $sezona_slug === '') {
                $parsed = (array) $call('parse_legacy_liga_sezona', $liga_slug, '');
                $liga_slug = sanitize_title((string) ($parsed['league_slug'] ?? $liga_slug));
                $sezona_slug = sanitize_title((string) ($parsed['season_slug'] ?? ''));
            }
            if ($liga_slug !== '') {
                $league_label = (string) $call('slug_to_title', $liga_slug);
                if ($league_label === '') {
                    $league_label = $liga_slug;
                }
                $league_label = ucwords(strtolower((string) $league_label));
                $subtitle_parts = [trim((string) $league_label)];

                if ($sezona_slug !== '') {
                    $rule = $call('get_competition_rule_data', $liga_slug, $sezona_slug);
                    if (is_array($rule) && !empty($rule['savez'])) {
                        $savez = $call('competition_federation_data', (string) $rule['savez']);
                        if (is_array($savez) && !empty($savez['label'])) {
                            $subtitle_parts[] = (string) $savez['label'];
                        }
                    }
                }

                $subtitle_parts = array_values(array_filter(array_map('trim', $subtitle_parts)));
                if (!empty($subtitle_parts)) {
                    $club_meta_subtitle = implode(', ', $subtitle_parts);
                }
            }
        }

        foreach ($fields as $key => $label) {
            $value = trim((string) get_post_meta($club_id, $key, true));
            if ($value === '') {
                continue;
            }
            $prefix_icon = '';
            $suffix_icon = '';
            if ($key === 'website_kluba') {
                $href = esc_url($value);
                if ($href !== '') {
                    $display = preg_replace('#^https?://#i', '', $value);
                    $value = '<a href="' . $href . '" target="_blank" rel="noopener">' . esc_html((string) $display) . '</a>';
                    $suffix_icon = (string) $call('info_link_icon_html', 'external-icon', '↗', 'after');
                } else {
                    $value = esc_html($value);
                }
            } elseif ($key === 'kontakt') {
                $phone_href = (string) $call('normalize_phone_for_href', $value);
                $phone_display = (string) $call('format_phone_for_display', $value);
                if ($phone_href !== '') {
                    $value = '<a href="tel:' . esc_attr($phone_href) . '">' . esc_html($phone_display) . '</a>';
                    $suffix_icon = (string) $call('info_link_icon_html', 'external-icon', '↗', 'after');
                } else {
                    $value = esc_html($phone_display);
                }
            } elseif ($key === 'email') {
                $email = sanitize_email($value);
                if ($email !== '') {
                    $value = '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
                    $suffix_icon = (string) $call('info_link_icon_html', 'external-icon', '↗', 'after');
                } else {
                    $value = esc_html($value);
                }
            } else {
                $value = esc_html($value);
            }
            if ($prefix_icon !== '' || $suffix_icon !== '') {
                $value = '<span class="opentt-info-link-wrap">' . $prefix_icon . $value . $suffix_icon . '</span>';
            }
            $rows[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        ob_start();
        ?>
        <?php echo (string) $call('shortcode_title_html', 'Info kluba'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <section class="opentt-info-kluba">
            <div class="opentt-info-kluba-head">
                <a href="<?php echo esc_url($club_link); ?>" class="opentt-info-kluba-brand">
                    <span class="opentt-info-kluba-grb-wrap">
                        <?php echo $club_logo ?: ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </span>
                    <span class="opentt-info-kluba-head-text">
                        <span class="opentt-info-kluba-ime"><?php echo esc_html($club_display_name); ?></span>
                        <?php if ($club_meta_subtitle !== ''): ?>
                            <span class="opentt-info-kluba-podnaslov"><?php echo esc_html($club_meta_subtitle); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </div>
            <?php if (!empty($rows)): ?>
                <dl class="opentt-info-kluba-lista">
                    <?php foreach ($rows as $row): ?>
                        <div class="opentt-info-kluba-row">
                            <dt><?php echo esc_html((string) $row['label']); ?></dt>
                            <dd><?php echo $row['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }
}
