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

final class PlayerInfoShortcode
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
        ], $atts);

        $player_id = 0;
        if (!empty($atts['igrac'])) {
            $lookup = sanitize_title((string) $atts['igrac']);
            $post = get_page_by_path($lookup, OBJECT, 'igrac');
            if (!$post) {
                $post = get_page_by_title((string) $atts['igrac'], OBJECT, 'igrac');
            }
            if ($post && !is_wp_error($post)) {
                $player_id = intval($post->ID);
            }
        } elseif (is_singular('igrac')) {
            $player_id = intval(get_the_ID());
        }

        if ($player_id <= 0) {
            return '';
        }

        $player_name = (string) get_the_title($player_id);
        $player_photo = get_the_post_thumbnail($player_id, 'medium', ['class' => 'opentt-info-igraca-slika']);
        if (!$player_photo) {
            $player_photo = '<img src="' . esc_url((string) $call('player_fallback_image_url')) . '" alt="' . esc_attr($player_name) . '" class="opentt-info-igraca-slika" />';
        }
        $player_link = (string) get_permalink($player_id);

        $club_id = intval($call('get_player_club_id', $player_id));
        $club_name = $club_id > 0 ? (string) get_the_title($club_id) : '';
        $club_link = $club_id > 0 ? (string) get_permalink($club_id) : '';
        $club_logo = $club_id > 0 ? (string) $call('club_logo_html', $club_id, 'thumbnail', ['class' => 'opentt-info-igraca-klub-grb']) : '';

        $rows = [];

        $dob = trim((string) get_post_meta($player_id, 'datum_rodjenja', true));
        if ($dob !== '') {
            $ts = strtotime($dob);
            if ($ts !== false) {
                $dob = date_i18n('d.m.Y.', $ts);
            }
            $rows[] = [
                'label' => 'Datum rođenja',
                'value' => esc_html($dob),
            ];
        }

        $pob = trim((string) get_post_meta($player_id, 'mesto_rodjenja', true));
        if ($pob !== '') {
            $rows[] = [
                'label' => 'Mesto rođenja',
                'value' => esc_html($pob),
            ];
        }

        $country_code = strtoupper(sanitize_key((string) get_post_meta($player_id, 'drzavljanstvo', true)));
        if ($country_code !== '') {
            $country_name = (string) $call('country_label_by_code', $country_code);
            if ($country_name !== '') {
                $flag = (string) $call('country_flag_emoji', $country_code);
                $country_value = '<span class="opentt-info-igraca-nacionalnost">';
                if ($flag !== '') {
                    $country_value .= '<span class="flag" aria-hidden="true">' . esc_html($flag) . '</span> ';
                }
                $country_value .= '<span class="name">' . esc_html($country_name) . '</span>';
                $country_value .= '</span>';
                $rows[] = [
                    'label' => 'Državljanstvo',
                    'value' => $country_value,
                ];
            }
        }

        $bio = trim((string) get_post_field('post_content', $player_id));
        if ($bio !== '') {
            $rows[] = [
                'label' => 'Biografija',
                'value' => wp_kses_post(wpautop($bio)),
            ];
        }

        ob_start();
        ?>
        <?php echo (string) $call('shortcode_title_html', 'Info igrača'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <section class="opentt-info-igraca">
            <div class="opentt-info-igraca-head">
                <div class="opentt-info-igraca-brand">
                    <a href="<?php echo esc_url($player_link); ?>" class="opentt-info-igraca-foto-link">
                        <span class="opentt-info-igraca-slika-wrap">
                            <?php echo $player_photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </span>
                    </a>
                    <span class="opentt-info-igraca-head-text">
                        <a href="<?php echo esc_url($player_link); ?>" class="opentt-info-igraca-ime"><?php echo esc_html($player_name); ?></a>
                        <?php if ($club_name !== ''): ?>
                            <span class="opentt-info-igraca-klub">
                                <?php if ($club_link !== ''): ?>
                                    <a class="opentt-info-igraca-klub-link" href="<?php echo esc_url($club_link); ?>">
                                        <?php if ($club_logo): ?>
                                            <span class="opentt-info-igraca-klub-logo"><?php echo $club_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                        <?php endif; ?>
                                        <span class="opentt-info-igraca-klub-tekst"><?php echo esc_html($club_name); ?></span>
                                    </a>
                                <?php else: ?>
                                    <?php if ($club_logo): ?>
                                        <span class="opentt-info-igraca-klub-logo"><?php echo $club_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                                    <?php endif; ?>
                                    <span class="opentt-info-igraca-klub-tekst"><?php echo esc_html($club_name); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($rows)): ?>
                <dl class="opentt-info-igraca-lista">
                    <?php foreach ($rows as $row): ?>
                        <div class="opentt-info-igraca-row">
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
