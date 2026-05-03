<?php

namespace OpenTT\Tournaments\WordPress;

use OpenTT\Tournaments\Infrastructure\Repository;
use OpenTT\Tournaments\Plugin;

final class Shortcodes
{
    public static function register()
    {
        add_shortcode('opentt_tournaments', [__CLASS__, 'renderTournaments']);
        add_shortcode('opentt_tournament', [__CLASS__, 'renderTournament']);
        add_shortcode('opentt_tournament_categories', [__CLASS__, 'renderCategories']);
        add_shortcode('opentt_tournament_signup', [__CLASS__, 'renderSignup']);
        add_shortcode('opentt_tournament_podium', [__CLASS__, 'renderPodium']);
    }

    public static function renderTournaments($atts = [])
    {
        $atts = shortcode_atts([
            'limit' => '12',
            'status' => '',
        ], (array) $atts, 'opentt_tournaments');

        $metaQuery = [];
        $status = sanitize_key((string) ($atts['status'] ?? ''));
        if ($status !== '') {
            $metaQuery[] = [
                'key' => '_opentt_tournament_status',
                'value' => $status,
            ];
        }

        $posts = get_posts([
            'post_type' => Plugin::CPT,
            'post_status' => ['publish'],
            'numberposts' => max(1, min(100, intval($atts['limit']))),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => $metaQuery,
        ]) ?: [];

        if (empty($posts)) {
            return '<div class="opentt-tournaments-empty">Nema turnira za prikaz.</div>';
        }

        ob_start();
        echo '<div class="opentt-tournaments-list">';
        foreach ($posts as $post) {
            $date = trim((string) get_post_meta($post->ID, '_opentt_tournament_date', true));
            $location = trim((string) get_post_meta($post->ID, '_opentt_tournament_location', true));
            $statusLabel = self::statusLabel((string) get_post_meta($post->ID, '_opentt_tournament_status', true));
            $thumb = get_the_post_thumbnail($post->ID, 'medium', ['class' => 'opentt-tournament-card-image']);
            echo '<article class="opentt-tournament-card">';
            echo '<a class="opentt-tournament-card-link" href="' . esc_url((string) get_permalink($post->ID)) . '">';
            if ($thumb) {
                echo '<span class="opentt-tournament-card-media">' . $thumb . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            echo '<span class="opentt-tournament-card-body">';
            echo '<span class="opentt-tournament-card-status">' . esc_html($statusLabel) . '</span>';
            echo '<span class="opentt-tournament-card-title">' . esc_html((string) get_the_title($post)) . '</span>';
            if ($date !== '' || $location !== '') {
                echo '<span class="opentt-tournament-card-meta">' . esc_html(trim($date . ($date !== '' && $location !== '' ? ' · ' : '') . $location)) . '</span>';
            }
            echo '</span>';
            echo '</a>';
            echo '</article>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    public static function renderTournament($atts = [])
    {
        $atts = shortcode_atts([
            'id' => '',
            'slug' => '',
            'category' => '',
        ], (array) $atts, 'opentt_tournament');

        $tournamentId = self::resolveTournamentId($atts);
        if ($tournamentId <= 0) {
            return '<div class="opentt-tournament-empty">Turnir nije pronađen.</div>';
        }

        $post = get_post($tournamentId);
        if (!$post || $post->post_type !== Plugin::CPT) {
            return '<div class="opentt-tournament-empty">Turnir nije pronađen.</div>';
        }

        $categories = Repository::categories($tournamentId);
        $categoryFilter = sanitize_title((string) ($atts['category'] ?? ''));
        if ($categoryFilter !== '') {
            $categories = array_values(array_filter($categories, static function ($category) use ($categoryFilter) {
                return sanitize_title((string) ($category->slug ?? '')) === $categoryFilter;
            }));
        }

        ob_start();
        echo '<section class="opentt-tournament" data-opentt-tournament="' . esc_attr((string) $tournamentId) . '">';
        self::renderHeader($post);
        if (empty($categories)) {
            echo '<div class="opentt-tournament-panel"><p>Još nema kategorija za ovaj turnir.</p></div>';
        } else {
            echo '<div class="opentt-tournament-tabs" role="tablist">';
            foreach ($categories as $i => $category) {
                echo '<button type="button" class="opentt-tournament-tab' . ($i === 0 ? ' is-active' : '') . '" data-opentt-tournament-tab="' . esc_attr((string) $category->id) . '">' . esc_html((string) $category->name) . '</button>';
            }
            echo '</div>';
            foreach ($categories as $i => $category) {
                echo '<div class="opentt-tournament-category' . ($i === 0 ? ' is-active' : '') . '" data-opentt-tournament-category="' . esc_attr((string) $category->id) . '">';
                self::renderCategory($category);
                echo '</div>';
            }
        }
        echo '</section>';
        return ob_get_clean();
    }

    public static function renderCategories($atts = [])
    {
        $tournamentId = self::resolveTournamentId((array) $atts);
        if ($tournamentId <= 0) {
            return '';
        }
        $categories = Repository::categories($tournamentId);
        if (empty($categories)) {
            return '';
        }
        $out = '<div class="opentt-tournament-category-list">';
        foreach ($categories as $category) {
            $out .= '<span class="opentt-tournament-category-pill">' . esc_html((string) $category->name) . '</span>';
        }
        $out .= '</div>';
        return $out;
    }

    public static function renderSignup($atts = [])
    {
        $tournamentId = self::resolveTournamentId((array) $atts);
        if ($tournamentId <= 0) {
            return '';
        }
        $enabled = (string) get_post_meta($tournamentId, '_opentt_tournament_online_registration', true) === '1';
        if (!$enabled) {
            return '<div class="opentt-tournament-panel"><p>Online prijave za ovaj turnir trenutno nisu uključene.</p></div>';
        }
        return '<div class="opentt-tournament-panel opentt-tournament-signup-placeholder"><p>Online prijave su uključene. Forma za prijavu dolazi u sledećoj fazi turnirskog engine-a.</p></div>';
    }

    public static function renderPodium($atts = [])
    {
        $tournamentId = self::resolveTournamentId((array) $atts);
        if ($tournamentId <= 0) {
            return '';
        }
        return '<div class="opentt-tournament-panel"><p>Podium i finalni plasman biće prikazani kada se unesu rezultati završnice.</p></div>';
    }

    private static function renderHeader($post)
    {
        $date = trim((string) get_post_meta($post->ID, '_opentt_tournament_date', true));
        $location = trim((string) get_post_meta($post->ID, '_opentt_tournament_location', true));
        $status = self::statusLabel((string) get_post_meta($post->ID, '_opentt_tournament_status', true));
        echo '<header class="opentt-tournament-header">';
        echo '<div>';
        echo '<span class="opentt-tournament-status">' . esc_html($status) . '</span>';
        echo '<h1 class="opentt-tournament-title">' . esc_html((string) get_the_title($post)) . '</h1>';
        if ($date !== '' || $location !== '') {
            echo '<p class="opentt-tournament-meta">' . esc_html(trim($date . ($date !== '' && $location !== '' ? ' · ' : '') . $location)) . '</p>';
        }
        echo '</div>';
        echo '</header>';
    }

    private static function renderCategory($category)
    {
        $entries = Repository::entryMap(intval($category->id));
        $rounds = Repository::matchesByRound(intval($category->id));
        echo '<div class="opentt-tournament-category-head">';
        echo '<div><h2>' . esc_html((string) $category->name) . '</h2>';
        echo '<p>' . esc_html(self::formatLabel((string) $category->format) . ' · ' . self::typeLabel((string) $category->type) . ' · Kostur ' . intval($category->bracket_size)) . '</p></div>';
        echo '</div>';

        if (empty($rounds)) {
            echo '<div class="opentt-tournament-panel"><p>Kostur još nije generisan za ovu kategoriju.</p></div>';
            return;
        }

        echo '<div class="opentt-tournament-round-nav" data-opentt-round-nav>';
        echo '<button type="button" class="opentt-tournament-round-prev" data-opentt-round-prev aria-label="Prethodna runda">‹</button>';
        echo '<span class="opentt-tournament-round-current" data-opentt-round-current></span>';
        echo '<button type="button" class="opentt-tournament-round-next" data-opentt-round-next aria-label="Sledeća runda">›</button>';
        echo '</div>';
        echo '<div class="opentt-tournament-bracket" data-opentt-bracket>';
        $roundIndex = 0;
        foreach ($rounds as $round => $matches) {
            $label = !empty($matches[0]->round_label) ? (string) $matches[0]->round_label : ('Runda ' . intval($round));
            echo '<section class="opentt-tournament-round' . ($roundIndex === 0 ? ' is-active' : '') . '" data-opentt-round="' . esc_attr((string) $roundIndex) . '" data-opentt-round-label="' . esc_attr($label) . '">';
            echo '<h3>' . esc_html($label) . '</h3>';
            foreach ($matches as $match) {
                self::renderMatch($match, $entries);
            }
            echo '</section>';
            $roundIndex++;
        }
        echo '</div>';
    }

    private static function renderMatch($match, array $entries)
    {
        $homeId = intval($match->home_entry_id ?? 0);
        $awayId = intval($match->away_entry_id ?? 0);
        $winnerId = intval($match->winner_entry_id ?? 0);
        $home = $homeId > 0 && isset($entries[$homeId]) ? (string) $entries[$homeId]->display_name : 'TBD';
        $away = $awayId > 0 && isset($entries[$awayId]) ? (string) $entries[$awayId]->display_name : 'TBD';
        echo '<article class="opentt-tournament-match">';
        echo '<div class="opentt-tournament-match-row' . ($winnerId === $homeId && $homeId > 0 ? ' is-winner' : '') . '"><span>' . esc_html($home) . '</span><strong>' . esc_html((string) intval($match->home_score ?? 0)) . '</strong></div>';
        echo '<div class="opentt-tournament-match-row' . ($winnerId === $awayId && $awayId > 0 ? ' is-winner' : '') . '"><span>' . esc_html($away) . '</span><strong>' . esc_html((string) intval($match->away_score ?? 0)) . '</strong></div>';
        echo '</article>';
    }

    private static function resolveTournamentId(array $atts)
    {
        $id = isset($atts['id']) ? trim((string) $atts['id']) : '';
        if ($id !== '' && ctype_digit($id)) {
            return intval($id);
        }
        $slug = isset($atts['slug']) ? sanitize_title((string) $atts['slug']) : '';
        if ($slug !== '') {
            $post = get_page_by_path($slug, OBJECT, Plugin::CPT);
            if ($post && !is_wp_error($post)) {
                return intval($post->ID);
            }
        }
        if (is_singular(Plugin::CPT)) {
            return intval(get_the_ID());
        }
        return 0;
    }

    private static function statusLabel($status)
    {
        $map = [
            'draft' => 'Draft',
            'registration_open' => 'Prijave otvorene',
            'draw' => 'Žreb',
            'in_progress' => 'U toku',
            'finished' => 'Završen',
        ];
        $status = sanitize_key((string) $status);
        return $map[$status] ?? 'Draft';
    }

    private static function formatLabel($format)
    {
        $map = [
            'groups' => 'Grupe',
            'bracket' => 'Kostur',
            'groups_bracket' => 'Grupe + kostur',
        ];
        $format = sanitize_key((string) $format);
        return $map[$format] ?? 'Kostur';
    }

    private static function typeLabel($type)
    {
        return sanitize_key((string) $type) === 'doubles' ? 'Dubl' : 'Singl';
    }
}

