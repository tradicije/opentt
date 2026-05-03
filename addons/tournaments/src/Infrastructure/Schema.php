<?php

namespace OpenTT\Tournaments\Infrastructure;

final class Schema
{
    public static function table($entity)
    {
        global $wpdb;
        $entity = sanitize_key((string) $entity);
        $map = [
            'categories' => 'opentt_tournament_categories',
            'entries' => 'opentt_tournament_entries',
            'entry_members' => 'opentt_tournament_entry_members',
            'groups' => 'opentt_tournament_groups',
            'group_entries' => 'opentt_tournament_group_entries',
            'matches' => 'opentt_tournament_matches',
            'bracket_slots' => 'opentt_tournament_bracket_slots',
            'registrations' => 'opentt_tournament_registrations',
        ];
        $suffix = isset($map[$entity]) ? $map[$entity] : 'opentt_tournament_' . $entity;
        return $wpdb->prefix . $suffix;
    }

    public static function migrate($schemaVersion, $optionKey, $force = false)
    {
        $schemaVersion = (string) $schemaVersion;
        $optionKey = (string) $optionKey;
        if (!$force && (string) get_option($optionKey, '') === $schemaVersion) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $categories = self::table('categories');
        dbDelta("CREATE TABLE {$categories} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            slug varchar(190) NOT NULL,
            name varchar(190) NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'single',
            format varchar(30) NOT NULL DEFAULT 'bracket',
            status varchar(30) NOT NULL DEFAULT 'draft',
            bracket_size smallint(6) NOT NULL DEFAULT 16,
            third_place tinyint(1) NOT NULL DEFAULT 0,
            bracket_locked tinyint(1) NOT NULL DEFAULT 0,
            online_registration tinyint(1) NOT NULL DEFAULT 0,
            min_age smallint(6) DEFAULT NULL,
            max_age smallint(6) DEFAULT NULL,
            fee_amount decimal(12,2) NOT NULL DEFAULT 0,
            fee_currency varchar(12) NOT NULL DEFAULT 'RSD',
            doubles_fee_mode varchar(20) NOT NULL DEFAULT 'per_person',
            settings_json longtext NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tournament_slug (tournament_id, slug),
            KEY tournament_sort (tournament_id, sort_order)
        ) {$charset};");

        $entries = self::table('entries');
        dbDelta("CREATE TABLE {$entries} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            entry_type varchar(20) NOT NULL DEFAULT 'single',
            display_name varchar(255) NOT NULL,
            player_post_id bigint(20) unsigned DEFAULT NULL,
            seed_no smallint(6) DEFAULT NULL,
            source varchar(30) NOT NULL DEFAULT 'manual',
            status varchar(30) NOT NULL DEFAULT 'active',
            club_name varchar(190) NOT NULL DEFAULT '',
            birth_date date DEFAULT NULL,
            contact_phone varchar(80) NOT NULL DEFAULT '',
            contact_email varchar(190) NOT NULL DEFAULT '',
            meta_json longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY category_seed (category_id, seed_no),
            KEY tournament_category (tournament_id, category_id),
            KEY player_post_id (player_post_id)
        ) {$charset};");

        $members = self::table('entry_members');
        dbDelta("CREATE TABLE {$members} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            member_no tinyint(3) unsigned NOT NULL DEFAULT 1,
            player_post_id bigint(20) unsigned DEFAULT NULL,
            display_name varchar(255) NOT NULL,
            club_name varchar(190) NOT NULL DEFAULT '',
            birth_date date DEFAULT NULL,
            contact_phone varchar(80) NOT NULL DEFAULT '',
            contact_email varchar(190) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY entry_member (entry_id, member_no),
            KEY player_post_id (player_post_id)
        ) {$charset};");

        $groups = self::table('groups');
        dbDelta("CREATE TABLE {$groups} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            slug varchar(190) NOT NULL,
            name varchar(190) NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            settings_json longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY category_slug (category_id, slug),
            KEY category_sort (category_id, sort_order)
        ) {$charset};");

        $groupEntries = self::table('group_entries');
        dbDelta("CREATE TABLE {$groupEntries} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            entry_id bigint(20) unsigned NOT NULL,
            position_no smallint(6) DEFAULT NULL,
            override_rank smallint(6) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY group_entry (group_id, entry_id),
            KEY entry_id (entry_id)
        ) {$charset};");

        $matches = self::table('matches');
        dbDelta("CREATE TABLE {$matches} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            group_id bigint(20) unsigned DEFAULT NULL,
            phase varchar(30) NOT NULL DEFAULT 'bracket',
            round_no smallint(6) NOT NULL DEFAULT 1,
            round_label varchar(80) NOT NULL DEFAULT '',
            match_no int(11) NOT NULL DEFAULT 0,
            bracket_position int(11) NOT NULL DEFAULT 0,
            home_entry_id bigint(20) unsigned DEFAULT NULL,
            away_entry_id bigint(20) unsigned DEFAULT NULL,
            home_score smallint(6) NOT NULL DEFAULT 0,
            away_score smallint(6) NOT NULL DEFAULT 0,
            winner_entry_id bigint(20) unsigned DEFAULT NULL,
            status varchar(30) NOT NULL DEFAULT 'scheduled',
            scheduled_at datetime DEFAULT NULL,
            meta_json longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY category_phase_match (category_id, phase, round_no, match_no),
            KEY tournament_category (tournament_id, category_id),
            KEY winner_entry_id (winner_entry_id)
        ) {$charset};");

        $slots = self::table('bracket_slots');
        dbDelta("CREATE TABLE {$slots} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            category_id bigint(20) unsigned NOT NULL,
            round_no smallint(6) NOT NULL DEFAULT 1,
            match_no int(11) NOT NULL DEFAULT 0,
            side varchar(10) NOT NULL DEFAULT 'home',
            source_type varchar(40) NOT NULL DEFAULT 'manual',
            source_key varchar(80) NOT NULL DEFAULT '',
            entry_id bigint(20) unsigned DEFAULT NULL,
            seed_no smallint(6) DEFAULT NULL,
            locked tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY category_slot (category_id, round_no, match_no, side),
            KEY entry_id (entry_id)
        ) {$charset};");

        $registrations = self::table('registrations');
        dbDelta("CREATE TABLE {$registrations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tournament_id bigint(20) unsigned NOT NULL,
            category_ids text NULL,
            status varchar(30) NOT NULL DEFAULT 'pending',
            full_name varchar(255) NOT NULL,
            club_name varchar(190) NOT NULL DEFAULT '',
            birth_date date DEFAULT NULL,
            phone varchar(80) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            total_fee decimal(12,2) NOT NULL DEFAULT 0,
            ip_hash varchar(100) NOT NULL DEFAULT '',
            cookie_hash varchar(100) NOT NULL DEFAULT '',
            duplicate_flag tinyint(1) NOT NULL DEFAULT 0,
            payload_json longtext NULL,
            reviewed_by bigint(20) unsigned DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY tournament_status (tournament_id, status),
            KEY email (email),
            KEY phone (phone)
        ) {$charset};");

        update_option($optionKey, $schemaVersion, false);
    }
}

