<?php

namespace OpenTT\Tournaments\WordPress;

use OpenTT\Tournaments\Domain\BracketGenerator;
use OpenTT\Tournaments\Infrastructure\Repository;
use OpenTT\Tournaments\Infrastructure\Schema;
use OpenTT\Tournaments\Plugin;

final class Admin
{
    public static function registerMenu()
    {
        if (self::hasOpenTTMenu()) {
            add_submenu_page('stkb-unified', 'Turniri', 'Turniri', Plugin::CAP, 'opentt-tournaments', [__CLASS__, 'renderTournamentsPage']);
        } else {
            add_menu_page('OpenTT Tournaments', 'OpenTT Tournaments', Plugin::CAP, 'opentt-tournaments', [__CLASS__, 'renderTournamentsPage'], 'dashicons-tickets-alt', 27);
        }
        add_submenu_page(null, 'Uredi turnir', 'Uredi turnir', Plugin::CAP, 'opentt-tournament-edit', [__CLASS__, 'renderTournamentEditPage']);
    }

    public static function renderTournamentsPage()
    {
        self::requireCap();
        $posts = get_posts([
            'post_type' => Plugin::CPT,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
        ]) ?: [];

        echo '<div class="wrap opentt-admin opentt-tournaments-admin">';
        echo '<h1>Turniri</h1>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=opentt-tournament-edit')) . '">+ Dodaj turnir</a></p>';
        if (empty($posts)) {
            echo '<p>Nema unetih turnira.</p></div>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Turnir</th><th>Status</th><th>Datum</th><th>Lokacija</th><th>Shortcode</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($posts as $post) {
            $edit = admin_url('admin.php?page=opentt-tournament-edit&id=' . intval($post->ID));
            $delete = wp_nonce_url(admin_url('admin-post.php?action=opentt_tournaments_delete_tournament&id=' . intval($post->ID)), 'opentt_tournaments_delete_tournament_' . intval($post->ID));
            echo '<tr>';
            echo '<td>' . intval($post->ID) . '</td>';
            echo '<td><strong>' . esc_html((string) get_the_title($post)) . '</strong><br><code>' . esc_html((string) $post->post_name) . '</code></td>';
            echo '<td>' . esc_html(self::statusLabel((string) get_post_meta($post->ID, '_opentt_tournament_status', true))) . '</td>';
            echo '<td>' . esc_html((string) get_post_meta($post->ID, '_opentt_tournament_date', true)) . '</td>';
            echo '<td>' . esc_html((string) get_post_meta($post->ID, '_opentt_tournament_location', true)) . '</td>';
            echo '<td><code>[opentt_tournament id="' . intval($post->ID) . '"]</code></td>';
            echo '<td><a class="button button-small" href="' . esc_url($edit) . '">Uredi</a> ';
            echo '<a class="button button-small" href="' . esc_url((string) get_permalink($post->ID)) . '" target="_blank" rel="noopener">Frontend</a> ';
            echo '<a class="button button-small button-link-delete" href="' . esc_url($delete) . '" onclick="return confirm(\'Obrisati turnir?\')">Obriši</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function renderTournamentEditPage()
    {
        self::requireCap();
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post = $id > 0 ? get_post($id) : null;
        if ($post && $post->post_type !== Plugin::CPT) {
            $post = null;
            $id = 0;
        }

        echo '<div class="wrap opentt-admin opentt-tournaments-admin">';
        echo '<h1>' . ($id > 0 ? 'Uredi turnir' : 'Dodaj turnir') . '</h1>';
        self::renderTournamentForm($post);
        if ($id > 0) {
            self::renderCategories($id);
        }
        echo '</div>';
    }

    public static function handleSaveTournament()
    {
        self::requireCap();
        check_admin_referer('opentt_tournaments_save_tournament');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = sanitize_text_field((string) wp_unslash($_POST['title'] ?? ''));
        if ($title === '') {
            $title = 'Novi turnir';
        }
        $content = wp_kses_post((string) wp_unslash($_POST['description'] ?? ''));
        $status = sanitize_key((string) wp_unslash($_POST['status'] ?? 'draft'));
        $postStatus = ($status === 'draft') ? 'draft' : 'publish';
        $data = [
            'post_type' => Plugin::CPT,
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $postStatus,
        ];
        if ($id > 0) {
            $data['ID'] = $id;
            $saved = wp_update_post($data, true);
        } else {
            $saved = wp_insert_post($data, true);
        }
        if (is_wp_error($saved) || intval($saved) <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=opentt-tournaments'));
            exit;
        }
        $savedId = intval($saved);
        update_post_meta($savedId, '_opentt_tournament_date', sanitize_text_field((string) wp_unslash($_POST['date'] ?? '')));
        update_post_meta($savedId, '_opentt_tournament_location', sanitize_text_field((string) wp_unslash($_POST['location'] ?? '')));
        update_post_meta($savedId, '_opentt_tournament_status', $status);
        update_post_meta($savedId, '_opentt_tournament_online_registration', !empty($_POST['online_registration']) ? '1' : '0');
        wp_safe_redirect(admin_url('admin.php?page=opentt-tournament-edit&id=' . $savedId . '&saved=1'));
        exit;
    }

    public static function handleDeleteTournament()
    {
        self::requireCap();
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        check_admin_referer('opentt_tournaments_delete_tournament_' . $id);
        if ($id > 0 && get_post_type($id) === Plugin::CPT) {
            wp_delete_post($id, true);
            self::deleteTournamentRows($id);
        }
        wp_safe_redirect(admin_url('admin.php?page=opentt-tournaments&deleted=1'));
        exit;
    }

    public static function handleSaveCategory()
    {
        self::requireCap();
        check_admin_referer('opentt_tournaments_save_category');
        global $wpdb;
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $tournamentId = isset($_POST['tournament_id']) ? intval($_POST['tournament_id']) : 0;
        if ($tournamentId <= 0 || get_post_type($tournamentId) !== Plugin::CPT) {
            wp_safe_redirect(admin_url('admin.php?page=opentt-tournaments'));
            exit;
        }
        $name = sanitize_text_field((string) wp_unslash($_POST['name'] ?? ''));
        if ($name === '') {
            $name = 'Kategorija';
        }
        $now = current_time('mysql');
        $data = [
            'tournament_id' => $tournamentId,
            'slug' => sanitize_title((string) wp_unslash($_POST['slug'] ?? '')) ?: sanitize_title($name),
            'name' => $name,
            'type' => self::cleanChoice((string) wp_unslash($_POST['type'] ?? 'single'), ['single', 'doubles'], 'single'),
            'format' => self::cleanChoice((string) wp_unslash($_POST['format'] ?? 'bracket'), ['groups', 'bracket', 'groups_bracket'], 'bracket'),
            'status' => self::cleanChoice((string) wp_unslash($_POST['status'] ?? 'draft'), ['draft', 'registration_open', 'draw', 'in_progress', 'finished'], 'draft'),
            'bracket_size' => self::cleanBracketSize(intval($_POST['bracket_size'] ?? 16)),
            'third_place' => !empty($_POST['third_place']) ? 1 : 0,
            'online_registration' => !empty($_POST['online_registration']) ? 1 : 0,
            'min_age' => ($_POST['min_age'] ?? '') !== '' ? max(0, intval($_POST['min_age'])) : null,
            'max_age' => ($_POST['max_age'] ?? '') !== '' ? max(0, intval($_POST['max_age'])) : null,
            'fee_amount' => max(0, floatval($_POST['fee_amount'] ?? 0)),
            'fee_currency' => sanitize_text_field((string) wp_unslash($_POST['fee_currency'] ?? 'RSD')),
            'doubles_fee_mode' => self::cleanChoice((string) wp_unslash($_POST['doubles_fee_mode'] ?? 'per_person'), ['per_person', 'per_pair'], 'per_person'),
            'sort_order' => intval($_POST['sort_order'] ?? 0),
            'updated_at' => $now,
        ];
        $table = Schema::table('categories');
        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($table, $data);
        }
        wp_safe_redirect(admin_url('admin.php?page=opentt-tournament-edit&id=' . $tournamentId . '#opentt-tournament-categories'));
        exit;
    }

    public static function handleDeleteCategory()
    {
        self::requireCap();
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $tournamentId = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
        check_admin_referer('opentt_tournaments_delete_category_' . $id);
        if ($id > 0) {
            $entryIds = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('entries') . ' WHERE category_id=%d', $id)) ?: [];
            foreach ($entryIds as $entryId) {
                $wpdb->delete(Schema::table('entry_members'), ['entry_id' => intval($entryId)]);
            }
            $groupIds = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('groups') . ' WHERE category_id=%d', $id)) ?: [];
            foreach ($groupIds as $groupId) {
                $wpdb->delete(Schema::table('group_entries'), ['group_id' => intval($groupId)]);
            }
            $wpdb->delete(Schema::table('categories'), ['id' => $id]);
            $wpdb->delete(Schema::table('entries'), ['category_id' => $id]);
            $wpdb->delete(Schema::table('groups'), ['category_id' => $id]);
            $wpdb->delete(Schema::table('matches'), ['category_id' => $id]);
            $wpdb->delete(Schema::table('bracket_slots'), ['category_id' => $id]);
        }
        wp_safe_redirect(admin_url('admin.php?page=opentt-tournament-edit&id=' . $tournamentId . '#opentt-tournament-categories'));
        exit;
    }

    public static function handleSaveEntry()
    {
        self::requireCap();
        check_admin_referer('opentt_tournaments_save_entry');
        global $wpdb;
        $categoryId = intval($_POST['category_id'] ?? 0);
        $category = Repository::category($categoryId);
        if (!$category) {
            wp_safe_redirect(admin_url('admin.php?page=opentt-tournaments'));
            exit;
        }
        $display = sanitize_text_field((string) wp_unslash($_POST['display_name'] ?? ''));
        if ($display === '') {
            $display = 'Učesnik';
        }
        $now = current_time('mysql');
        $wpdb->insert(Schema::table('entries'), [
            'tournament_id' => intval($category->tournament_id),
            'category_id' => $categoryId,
            'entry_type' => sanitize_key((string) ($category->type ?? 'single')),
            'display_name' => $display,
            'player_post_id' => intval($_POST['player_post_id'] ?? 0) ?: null,
            'seed_no' => ($_POST['seed_no'] ?? '') !== '' ? max(1, intval($_POST['seed_no'])) : null,
            'source' => 'manual',
            'status' => 'active',
            'club_name' => sanitize_text_field((string) wp_unslash($_POST['club_name'] ?? '')),
            'contact_phone' => sanitize_text_field((string) wp_unslash($_POST['contact_phone'] ?? '')),
            'contact_email' => sanitize_email((string) wp_unslash($_POST['contact_email'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        wp_safe_redirect(admin_url('admin.php?page=opentt-tournament-edit&id=' . intval($category->tournament_id) . '#opentt-category-' . $categoryId));
        exit;
    }

    public static function handleDeleteEntry()
    {
        self::requireCap();
        global $wpdb;
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        $tournamentId = isset($_GET['tournament_id']) ? intval($_GET['tournament_id']) : 0;
        check_admin_referer('opentt_tournaments_delete_entry_' . $id);
        if ($id > 0) {
            $wpdb->delete(Schema::table('entries'), ['id' => $id]);
            $wpdb->delete(Schema::table('entry_members'), ['entry_id' => $id]);
        }
        wp_safe_redirect(admin_url('admin.php?page=opentt-tournament-edit&id=' . $tournamentId . '#opentt-category-' . $categoryId));
        exit;
    }

    public static function handleGenerateBracket()
    {
        self::requireCap();
        $categoryId = intval($_POST['category_id'] ?? 0);
        check_admin_referer('opentt_tournaments_generate_bracket_' . $categoryId);
        $category = Repository::category($categoryId);
        if ($category) {
            BracketGenerator::generate($category, Repository::entries($categoryId));
            wp_safe_redirect(admin_url('admin.php?page=opentt-tournament-edit&id=' . intval($category->tournament_id) . '#opentt-category-' . $categoryId));
            exit;
        }
        wp_safe_redirect(admin_url('admin.php?page=opentt-tournaments'));
        exit;
    }

    public static function handleSaveMatch()
    {
        self::requireCap();
        $matchId = intval($_POST['match_id'] ?? 0);
        check_admin_referer('opentt_tournaments_save_match_' . $matchId);
        BracketGenerator::updateWinnerAndAdvance($matchId, intval($_POST['home_score'] ?? 0), intval($_POST['away_score'] ?? 0));
        $tournamentId = intval($_POST['tournament_id'] ?? 0);
        $categoryId = intval($_POST['category_id'] ?? 0);
        wp_safe_redirect(admin_url('admin.php?page=opentt-tournament-edit&id=' . $tournamentId . '#opentt-category-' . $categoryId));
        exit;
    }

    private static function renderTournamentForm($post)
    {
        $id = $post ? intval($post->ID) : 0;
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="opentt-panel" style="padding:12px;margin-bottom:16px;">';
        wp_nonce_field('opentt_tournaments_save_tournament');
        echo '<input type="hidden" name="action" value="opentt_tournaments_save_tournament">';
        echo '<input type="hidden" name="id" value="' . esc_attr((string) $id) . '">';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Naziv</th><td><input type="text" name="title" class="regular-text" value="' . esc_attr($post ? (string) $post->post_title : '') . '" required></td></tr>';
        echo '<tr><th>Opis</th><td><textarea name="description" class="large-text" rows="4">' . esc_textarea($post ? (string) $post->post_content : '') . '</textarea></td></tr>';
        echo '<tr><th>Datum</th><td><input type="date" name="date" value="' . esc_attr($id ? (string) get_post_meta($id, '_opentt_tournament_date', true) : '') . '"></td></tr>';
        echo '<tr><th>Lokacija</th><td><input type="text" name="location" class="regular-text" value="' . esc_attr($id ? (string) get_post_meta($id, '_opentt_tournament_location', true) : '') . '"></td></tr>';
        echo '<tr><th>Status</th><td>' . self::select('status', ['draft' => 'Draft', 'registration_open' => 'Prijave otvorene', 'draw' => 'Žreb', 'in_progress' => 'U toku', 'finished' => 'Završen'], $id ? (string) get_post_meta($id, '_opentt_tournament_status', true) : 'draft') . '</td></tr>';
        echo '<tr><th>Online prijave</th><td><label><input type="checkbox" name="online_registration" value="1"' . checked($id ? (string) get_post_meta($id, '_opentt_tournament_online_registration', true) : '', '1', false) . '> Uključi online prijave za ovaj turnir</label></td></tr>';
        echo '</tbody></table>';
        submit_button($id > 0 ? 'Sačuvaj turnir' : 'Kreiraj turnir', 'primary', 'submit', false);
        echo '</form>';
    }

    private static function renderCategories($tournamentId)
    {
        $categories = Repository::categories($tournamentId);
        echo '<section id="opentt-tournament-categories" class="opentt-panel" style="padding:12px;margin-bottom:16px;">';
        echo '<h2>Kategorije</h2>';
        echo '<p class="description">Phase 1 podržava unos kategorija, učesnika i generisanje single-elimination kostura. Grupe i online prijave koriste istu shemu, ali dolaze u sledećoj fazi.</p>';
        self::renderCategoryForm($tournamentId, null);
        foreach ($categories as $category) {
            self::renderCategoryAdmin($category);
        }
        echo '</section>';
    }

    private static function renderCategoryAdmin($category)
    {
        $categoryId = intval($category->id);
        $tournamentId = intval($category->tournament_id);
        echo '<article id="opentt-category-' . esc_attr((string) $categoryId) . '" class="opentt-tournament-admin-category" style="border:1px solid #dcdcde;padding:12px;margin-top:14px;background:#fff;">';
        echo '<h3>' . esc_html((string) $category->name) . '</h3>';
        self::renderCategoryForm($tournamentId, $category);
        echo '<hr>';
        self::renderEntriesAdmin($category);
        echo '<hr>';
        self::renderBracketAdmin($category);
        echo '</article>';
    }

    private static function renderCategoryForm($tournamentId, $category)
    {
        $isEdit = $category && !empty($category->id);
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:10px 0;">';
        wp_nonce_field('opentt_tournaments_save_category');
        echo '<input type="hidden" name="action" value="opentt_tournaments_save_category">';
        echo '<input type="hidden" name="tournament_id" value="' . esc_attr((string) intval($tournamentId)) . '">';
        echo '<input type="hidden" name="id" value="' . esc_attr($isEdit ? (string) intval($category->id) : '0') . '">';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end;">';
        echo '<label>Naziv<input type="text" name="name" value="' . esc_attr($isEdit ? (string) $category->name : '') . '" required></label>';
        echo '<label>Slug<input type="text" name="slug" value="' . esc_attr($isEdit ? (string) $category->slug : '') . '" placeholder="apsolutna"></label>';
        echo '<label>Tip' . self::select('type', ['single' => 'Singl', 'doubles' => 'Dubl'], $isEdit ? (string) $category->type : 'single') . '</label>';
        echo '<label>Format' . self::select('format', ['bracket' => 'Kostur', 'groups' => 'Grupe', 'groups_bracket' => 'Grupe + kostur'], $isEdit ? (string) $category->format : 'bracket') . '</label>';
        echo '<label>Status' . self::select('status', ['draft' => 'Draft', 'registration_open' => 'Prijave otvorene', 'draw' => 'Žreb', 'in_progress' => 'U toku', 'finished' => 'Završen'], $isEdit ? (string) $category->status : 'draft') . '</label>';
        echo '<label>Kostur' . self::select('bracket_size', ['4' => '4', '8' => '8', '16' => '16', '32' => '32', '64' => '64', '128' => '128'], $isEdit ? (string) intval($category->bracket_size) : '16') . '</label>';
        echo '<label>Kotizacija<input type="number" step="0.01" name="fee_amount" value="' . esc_attr($isEdit ? (string) $category->fee_amount : '0') . '"></label>';
        echo '<label>Valuta<input type="text" name="fee_currency" value="' . esc_attr($isEdit ? (string) $category->fee_currency : 'RSD') . '"></label>';
        echo '<label>Min godine<input type="number" name="min_age" value="' . esc_attr($isEdit && $category->min_age !== null ? (string) intval($category->min_age) : '') . '"></label>';
        echo '<label>Max godine<input type="number" name="max_age" value="' . esc_attr($isEdit && $category->max_age !== null ? (string) intval($category->max_age) : '') . '"></label>';
        echo '<label>Redosled<input type="number" name="sort_order" value="' . esc_attr($isEdit ? (string) intval($category->sort_order) : '0') . '"></label>';
        echo '<label><input type="checkbox" name="third_place" value="1"' . checked($isEdit ? intval($category->third_place) : 0, 1, false) . '> Treće mesto</label>';
        echo '<label><input type="checkbox" name="online_registration" value="1"' . checked($isEdit ? intval($category->online_registration) : 0, 1, false) . '> Online prijave</label>';
        echo '<button type="submit" class="button ' . ($isEdit ? '' : 'button-primary') . '">' . esc_html($isEdit ? 'Sačuvaj kategoriju' : '+ Dodaj kategoriju') . '</button>';
        if ($isEdit) {
            $del = wp_nonce_url(admin_url('admin-post.php?action=opentt_tournaments_delete_category&id=' . intval($category->id) . '&tournament_id=' . intval($tournamentId)), 'opentt_tournaments_delete_category_' . intval($category->id));
            echo '<a class="button button-link-delete" href="' . esc_url($del) . '" onclick="return confirm(\'Obrisati kategoriju?\')">Obriši</a>';
        }
        echo '</div></form>';
    }

    private static function renderEntriesAdmin($category)
    {
        $categoryId = intval($category->id);
        $tournamentId = intval($category->tournament_id);
        $entries = Repository::entries($categoryId);
        echo '<h4>Učesnici</h4>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:10px;">';
        wp_nonce_field('opentt_tournaments_save_entry');
        echo '<input type="hidden" name="action" value="opentt_tournaments_save_entry">';
        echo '<input type="hidden" name="category_id" value="' . esc_attr((string) $categoryId) . '">';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;align-items:end;">';
        echo '<label>Ime / par<input type="text" name="display_name" required></label>';
        echo '<label>Klub<input type="text" name="club_name"></label>';
        echo '<label>Seed<input type="number" name="seed_no" min="1"></label>';
        echo '<label>OpenTT player ID<input type="number" name="player_post_id" min="0"></label>';
        echo '<label>Telefon<input type="text" name="contact_phone"></label>';
        echo '<label>Email<input type="email" name="contact_email"></label>';
        echo '<button type="submit" class="button">+ Dodaj učesnika</button>';
        echo '</div></form>';
        if (empty($entries)) {
            echo '<p>Nema učesnika.</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>Seed</th><th>Učesnik</th><th>Klub</th><th>Tip</th><th>Akcije</th></tr></thead><tbody>';
        foreach ($entries as $entry) {
            $del = wp_nonce_url(admin_url('admin-post.php?action=opentt_tournaments_delete_entry&id=' . intval($entry->id) . '&category_id=' . $categoryId . '&tournament_id=' . $tournamentId), 'opentt_tournaments_delete_entry_' . intval($entry->id));
            echo '<tr><td>' . esc_html($entry->seed_no ? (string) intval($entry->seed_no) : '') . '</td><td>' . esc_html((string) $entry->display_name) . '</td><td>' . esc_html((string) $entry->club_name) . '</td><td>' . esc_html((string) $entry->entry_type) . '</td><td><a class="button button-small button-link-delete" href="' . esc_url($del) . '">Obriši</a></td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function renderBracketAdmin($category)
    {
        $categoryId = intval($category->id);
        $tournamentId = intval($category->tournament_id);
        echo '<h4>Kostur</h4>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:10px;">';
        wp_nonce_field('opentt_tournaments_generate_bracket_' . $categoryId);
        echo '<input type="hidden" name="action" value="opentt_tournaments_generate_bracket">';
        echo '<input type="hidden" name="category_id" value="' . esc_attr((string) $categoryId) . '">';
        echo '<button type="submit" class="button button-primary" onclick="return confirm(\'Generisanje kostura briše postojeće turnirske mečeve ove kategorije. Nastaviti?\')">Generiši kostur</button>';
        echo '</form>';
        $entries = Repository::entryMap($categoryId);
        $rounds = Repository::matchesByRound($categoryId);
        if (empty($rounds)) {
            echo '<p>Kostur još nije generisan.</p>';
            return;
        }
        foreach ($rounds as $round => $matches) {
            $label = !empty($matches[0]->round_label) ? (string) $matches[0]->round_label : 'Runda ' . intval($round);
            echo '<h5>' . esc_html($label) . '</h5>';
            echo '<table class="widefat striped"><thead><tr><th>Meč</th><th>Domaći</th><th>Gost</th><th>Rezultat</th><th>Akcija</th></tr></thead><tbody>';
            foreach ($matches as $match) {
                $home = isset($entries[intval($match->home_entry_id)]) ? (string) $entries[intval($match->home_entry_id)]->display_name : 'TBD';
                $away = isset($entries[intval($match->away_entry_id)]) ? (string) $entries[intval($match->away_entry_id)]->display_name : 'TBD';
                echo '<tr><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('opentt_tournaments_save_match_' . intval($match->id));
                echo '<input type="hidden" name="action" value="opentt_tournaments_save_match">';
                echo '<input type="hidden" name="match_id" value="' . esc_attr((string) intval($match->id)) . '">';
                echo '<input type="hidden" name="tournament_id" value="' . esc_attr((string) $tournamentId) . '">';
                echo '<input type="hidden" name="category_id" value="' . esc_attr((string) $categoryId) . '">';
                echo '<td>' . esc_html((string) intval($match->match_no)) . '</td>';
                echo '<td>' . esc_html($home) . '</td>';
                echo '<td>' . esc_html($away) . '</td>';
                echo '<td><input type="number" name="home_score" value="' . esc_attr((string) intval($match->home_score)) . '" style="width:64px;"> : <input type="number" name="away_score" value="' . esc_attr((string) intval($match->away_score)) . '" style="width:64px;"></td>';
                echo '<td><button class="button button-small" type="submit">Sačuvaj</button></td>';
                echo '</form></tr>';
            }
            echo '</tbody></table>';
        }
    }

    private static function select($name, array $options, $selected)
    {
        $html = '<select name="' . esc_attr((string) $name) . '">';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . esc_attr((string) $value) . '"' . selected((string) $selected, (string) $value, false) . '>' . esc_html((string) $label) . '</option>';
        }
        return $html . '</select>';
    }

    private static function deleteTournamentRows($tournamentId)
    {
        global $wpdb;
        $tournamentId = intval($tournamentId);
        if ($tournamentId <= 0) {
            return;
        }

        $entryIds = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('entries') . ' WHERE tournament_id=%d', $tournamentId)) ?: [];
        foreach ($entryIds as $entryId) {
            $wpdb->delete(Schema::table('entry_members'), ['entry_id' => intval($entryId)]);
        }

        $groupIds = $wpdb->get_col($wpdb->prepare('SELECT id FROM ' . Schema::table('groups') . ' WHERE tournament_id=%d', $tournamentId)) ?: [];
        foreach ($groupIds as $groupId) {
            $wpdb->delete(Schema::table('group_entries'), ['group_id' => intval($groupId)]);
        }

        foreach (['categories', 'entries', 'groups', 'matches', 'bracket_slots', 'registrations'] as $entity) {
            $wpdb->delete(Schema::table($entity), ['tournament_id' => $tournamentId]);
        }
    }

    private static function cleanChoice($value, array $allowed, $default)
    {
        $value = sanitize_key((string) $value);
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function cleanBracketSize($size)
    {
        return in_array($size, [4, 8, 16, 32, 64, 128], true) ? $size : 16;
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

    private static function hasOpenTTMenu()
    {
        global $admin_page_hooks;
        return is_array($admin_page_hooks) && isset($admin_page_hooks['stkb-unified']);
    }

    private static function requireCap()
    {
        if (!current_user_can(Plugin::CAP)) {
            wp_die(esc_html__('Nemate dozvolu za ovu akciju.', 'opentt-tournaments'));
        }
    }
}
