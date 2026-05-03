<?php

namespace OpenTT\Tournaments\WordPress;

use OpenTT\Tournaments\Plugin;

final class ContentTypes
{
    public static function register()
    {
        if (post_type_exists(Plugin::CPT)) {
            return;
        }

        register_post_type(Plugin::CPT, [
            'labels' => [
                'name' => 'Turniri',
                'singular_name' => 'Turnir',
                'add_new_item' => 'Dodaj turnir',
                'edit_item' => 'Uredi turnir',
                'new_item' => 'Novi turnir',
                'view_item' => 'Pogledaj turnir',
                'search_items' => 'Pretraži turnire',
            ],
            'public' => true,
            'show_ui' => false,
            'show_in_rest' => true,
            'show_in_menu' => false,
            'has_archive' => true,
            'rewrite' => ['slug' => 'turniri', 'with_front' => false],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'menu_icon' => 'dashicons-tickets-alt',
        ]);
    }
}

