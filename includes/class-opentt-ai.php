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

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('opentt_get_club_position')) {
    function opentt_get_club_position($club_name)
    {
        return OpenTT_AI::tool_get_club_position($club_name);
    }
}

if (!function_exists('opentt_get_last_match')) {
    function opentt_get_last_match($club_name)
    {
        return OpenTT_AI::tool_get_last_match($club_name);
    }
}

if (!function_exists('opentt_get_next_match')) {
    function opentt_get_next_match($club_name)
    {
        return OpenTT_AI::tool_get_next_match($club_name);
    }
}

final class OpenTT_AI
{
    const OPTION_API_KEY = 'opentt_ai_groq_api_key';
    const OPTION_MODEL = 'opentt_ai_groq_model';
    const SETTINGS_GROUP = 'opentt_ai_settings_group';
    const NONCE_ACTION = 'opentt_ai_chat';
    const AJAX_ACTION = 'opentt_ai_chat';
    const MODELS_TRANSIENT_KEY = 'opentt_ai_groq_models_cache';
    const DEFAULT_MODEL = 'llama-3.1-8b-instant';
    const SYSTEM_PROMPT = 'You are an assistant for a Serbian table tennis platform. ALWAYS use tools when user asks about matches, rankings, clubs, players, squads, or schedules. Never guess. Use prior conversation context to resolve references like "oni", "njihov", or "taj klub".';

    public static function register()
    {
        add_action('init', [__CLASS__, 'register_shortcode']);
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax_chat']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION, [__CLASS__, 'ajax_chat']);
    }

    public static function register_shortcode()
    {
        add_shortcode('opentt_ai', [__CLASS__, 'render_shortcode']);
    }

    public static function register_admin_menu()
    {
        $capability = defined('OpenTT_Unified_Core::CAP') ? OpenTT_Unified_Core::CAP : 'manage_options';
        add_submenu_page(
            'stkb-unified',
            'AI Asistent',
            'AI Asistent',
            $capability,
            'stkb-unified-ai-chat',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings()
    {
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_API_KEY,
            [
                'type' => 'string',
                'sanitize_callback' => [__CLASS__, 'sanitize_api_key'],
                'default' => '',
            ]
        );
        register_setting(
            self::SETTINGS_GROUP,
            self::OPTION_MODEL,
            [
                'type' => 'string',
                'sanitize_callback' => [__CLASS__, 'sanitize_model'],
                'default' => self::DEFAULT_MODEL,
            ]
        );
    }

    public static function sanitize_api_key($value)
    {
        $value = trim((string) $value);
        $value = preg_replace('/[\r\n\t]+/', '', $value);
        $value = sanitize_text_field((string) $value);
        if ($value === '') {
            add_settings_error(self::OPTION_API_KEY, 'opentt_ai_key_empty', 'API ključ je obrisan.');
            return '';
        }

        if (strpos($value, 'gsk_') !== 0) {
            add_settings_error(self::OPTION_API_KEY, 'opentt_ai_key_format', 'Groq API ključ nije u ispravnom formatu (mora početi sa gsk_).');
            return (string) get_option(self::OPTION_API_KEY, '');
        }

        $check = self::validate_api_key_connection($value);
        if (!empty($check['ok'])) {
            self::clear_models_cache();
            add_settings_error(self::OPTION_API_KEY, 'opentt_ai_key_ok', 'Groq API ključ je uspešno validiran.', 'updated');
            return $value;
        }

        $message = trim((string) ($check['message'] ?? 'Neuspešna validacija API ključa.'));
        add_settings_error(self::OPTION_API_KEY, 'opentt_ai_key_fail', 'Groq API ključ nije validan ili API nije dostupan: ' . $message);
        return (string) get_option(self::OPTION_API_KEY, '');
    }

    public static function sanitize_model($value)
    {
        $value = trim((string) $value);
        $value = sanitize_text_field($value);
        if ($value === '') {
            return self::DEFAULT_MODEL;
        }
        return $value;
    }

    public static function render_settings_page()
    {
        $capability = defined('OpenTT_Unified_Core::CAP') ? OpenTT_Unified_Core::CAP : 'manage_options';
        if (!current_user_can($capability)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'opentt'));
        }

        if (isset($_GET['refresh_models']) && isset($_GET['_wpnonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $nonce_ok = wp_verify_nonce((string) wp_unslash($_GET['_wpnonce']), 'opentt_ai_refresh_models'); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ($nonce_ok) {
                self::clear_models_cache();
                $probe_key = trim((string) get_option(self::OPTION_API_KEY, ''));
                if ($probe_key !== '') {
                    $probe = self::fetch_available_models($probe_key, true);
                    if (!empty($probe['ok'])) {
                        add_settings_error(self::OPTION_MODEL, 'opentt_ai_models_refresh_ok', 'Lista modela je uspešno osvežena.', 'updated');
                    } else {
                        add_settings_error(self::OPTION_MODEL, 'opentt_ai_models_refresh_fail', 'Neuspešno osvežavanje modela: ' . (string) ($probe['message'] ?? 'Nepoznata greška.'));
                    }
                }
            }
        }

        echo '<div class="wrap">';
        echo '<h1>OpenTT AI Podešavanja</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::SETTINGS_GROUP);
        settings_errors(self::OPTION_API_KEY);
        settings_errors(self::OPTION_MODEL);

        $api_key = (string) get_option(self::OPTION_API_KEY, '');
        $selected_model = self::get_selected_model();
        $models_data = self::fetch_available_models($api_key, false);
        $models = !empty($models_data['models']) && is_array($models_data['models']) ? $models_data['models'] : [];
        $refresh_url = wp_nonce_url(
            admin_url('admin.php?page=stkb-unified-ai-chat&refresh_models=1'),
            'opentt_ai_refresh_models'
        );
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="opentt-ai-groq-api-key">Groq API Key</label></th>';
        echo '<td>';
        echo '<input id="opentt-ai-groq-api-key" name="' . esc_attr(self::OPTION_API_KEY) . '" type="text" class="regular-text" value="' . esc_attr($api_key) . '" autocomplete="off" />';
        echo '<p class="description">Ključ se čuva kroz WordPress Options API i nikada se ne izlaže na frontendu.</p>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="opentt-ai-groq-model">Groq Model</label></th>';
        echo '<td>';
        echo '<select id="opentt-ai-groq-model" name="' . esc_attr(self::OPTION_MODEL) . '" class="regular-text">';
        if (!empty($models)) {
            foreach ($models as $model_id) {
                $model_id = (string) $model_id;
                if ($model_id === '') {
                    continue;
                }
                echo '<option value="' . esc_attr($model_id) . '" ' . selected($selected_model, $model_id, false) . '>' . esc_html($model_id) . '</option>';
            }
            if (!in_array($selected_model, $models, true)) {
                echo '<option value="' . esc_attr($selected_model) . '" selected="selected">' . esc_html($selected_model . ' (trenutni)') . '</option>';
            }
        } else {
            echo '<option value="' . esc_attr($selected_model) . '">' . esc_html($selected_model) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Podrazumevano: <code>' . esc_html(self::DEFAULT_MODEL) . '</code>. Možeš osvežiti listu modela direktno sa Groq API-ja.</p>';
        echo '<p><a class="button" href="' . esc_url($refresh_url) . '">Osveži dostupne modele</a></p>';
        if (empty($models) && $api_key !== '') {
            echo '<p class="description">Napomena: lista modela trenutno nije dostupna. Sačuvani model će se i dalje koristiti.</p>';
        }
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';

        submit_button('Sačuvaj podešavanja');
        echo '</form>';
        echo '</div>';
    }

    public static function render_shortcode($atts = [])
    {
        $atts = shortcode_atts([
            'placeholder' => 'Pitaj o utakmicama, igračima ili ligama...',
            'button' => 'Pošalji',
        ], (array) $atts, 'opentt_ai');

        $uid = 'opentt-ai-' . wp_unique_id();
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $icon_url = self::resolve_icon_url();
        $placeholder = trim((string) $atts['placeholder']);
        if ($placeholder === '') {
            $placeholder = 'Pitaj o utakmicama, igračima ili ligama...';
        }
        $button = trim((string) $atts['button']);
        if ($button === '') {
            $button = 'Pošalji';
        }

        ob_start();
        ?>
        <div id="<?php echo esc_attr($uid); ?>" class="opentt-ai" data-opentt-ai="1" data-ajax-url="<?php echo esc_url($ajax_url); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-ai-icon="<?php echo esc_url($icon_url); ?>">
            <button type="button" class="opentt-ai-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($uid . '-panel'); ?>" aria-label="Otvori AI asistenta">
                <img class="opentt-ai-toggle-icon" src="<?php echo esc_url($icon_url); ?>" alt="" aria-hidden="true">
            </button>
            <div class="opentt-ai-backdrop" hidden></div>
            <div id="<?php echo esc_attr($uid . '-panel'); ?>" class="opentt-ai-panel" hidden>
                <button type="button" class="opentt-ai-close" aria-label="Zatvori AI asistenta">&times;</button>
                <div class="opentt-ai-head">
                    <span class="opentt-ai-brand">STKB.AI</span>
                    <label class="opentt-ai-label" for="<?php echo esc_attr($uid . '-input'); ?>">AI Asistent</label>
                </div>
                <div class="opentt-ai-messages" aria-live="polite"></div>
                <div class="opentt-ai-form">
                    <input id="<?php echo esc_attr($uid . '-input'); ?>" type="text" class="opentt-ai-input" placeholder="<?php echo esc_attr($placeholder); ?>" />
                    <button type="button" class="opentt-ai-send"><?php echo esc_html($button); ?></button>
                </div>
            </div>
        </div>
        <?php self::render_inline_assets_once(); ?>
        <?php
        return (string) ob_get_clean();
    }

    public static function ajax_chat()
    {
        $nonce_ok = check_ajax_referer(self::NONCE_ACTION, 'nonce', false);
        if ($nonce_ok === false) {
            wp_send_json_error(['message' => 'Nevažeći sigurnosni token.'], 403);
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field((string) wp_unslash($_POST['message'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $message = trim($message);
        if ($message === '') {
            wp_send_json_error(['message' => 'Poruka je obavezna.'], 400);
        }
        $history = [];
        if (isset($_POST['history'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $raw_history = (string) wp_unslash($_POST['history']); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $decoded_history = json_decode($raw_history, true);
            if (is_array($decoded_history)) {
                foreach ($decoded_history as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $role = sanitize_key((string) ($item['role'] ?? ''));
                    if ($role !== 'user' && $role !== 'assistant') {
                        continue;
                    }
                    $content = sanitize_textarea_field((string) ($item['content'] ?? ''));
                    $content = trim($content);
                    if ($content === '') {
                        continue;
                    }
                    if (strlen($content) > 1200) {
                        $content = substr($content, 0, 1200);
                    }
                    $history[] = [
                        'role' => $role,
                        'content' => $content,
                    ];
                    if (count($history) >= 20) {
                        break;
                    }
                }
            }
        }

        $api_key = trim((string) get_option(self::OPTION_API_KEY, ''));
        if ($api_key === '') {
            wp_send_json_error(['message' => 'Groq API ključ nije podešen u administraciji.'], 500);
        }
        $model = self::get_selected_model();
        $assistant_result = self::generate_context_aware_reply($api_key, $model, $message, $history);
        if (!is_array($assistant_result) || empty($assistant_result['ok'])) {
            $error_message = is_array($assistant_result) ? trim((string) ($assistant_result['message'] ?? '')) : '';
            if ($error_message === '') {
                $error_message = 'AI odgovor je prazan.';
            }
            wp_send_json_error(['message' => $error_message], 502);
        }

        wp_send_json_success([
            'reply' => trim((string) ($assistant_result['reply'] ?? '')),
        ]);
    }

    private static function generate_context_aware_reply($api_key, $model, $user_message, array $history = [])
    {
        $messages = [
            [
                'role' => 'system',
                'content' => self::SYSTEM_PROMPT,
            ],
        ];
        foreach ($history as $h) {
            if (!is_array($h)) {
                continue;
            }
            $role = sanitize_key((string) ($h['role'] ?? ''));
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }
            $content = trim((string) ($h['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $messages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }
        $messages[] = [
            'role' => 'user',
            'content' => (string) $user_message,
        ];
        $tools = self::build_tools_schema();

        $tool_summaries = [];
        for ($round = 0; $round < 3; $round++) {
            $response = self::call_groq_chat_completion($api_key, $model, $messages, $tools, 'auto', 20);
            if (!is_array($response)) {
                return ['ok' => false, 'message' => 'AI servis trenutno nije dostupan.'];
            }
            if (!empty($response['error']) && is_array($response['error'])) {
                $message = trim((string) ($response['error']['message'] ?? 'Greška AI servisa.'));
                return ['ok' => false, 'message' => $message];
            }

            $assistant_message = isset($response['choices'][0]['message']) && is_array($response['choices'][0]['message'])
                ? $response['choices'][0]['message']
                : [];
            $content = trim((string) ($assistant_message['content'] ?? ''));
            $tool_calls = isset($assistant_message['tool_calls']) && is_array($assistant_message['tool_calls'])
                ? $assistant_message['tool_calls']
                : [];

            if (empty($tool_calls)) {
                if ($content !== '') {
                    return ['ok' => true, 'reply' => $content];
                }
                if (!empty($tool_summaries)) {
                    return ['ok' => true, 'reply' => implode(' ', array_values(array_unique($tool_summaries)))];
                }
                return ['ok' => false, 'message' => 'AI odgovor je prazan.'];
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => isset($assistant_message['content']) ? (string) $assistant_message['content'] : '',
                'tool_calls' => $tool_calls,
            ];

            foreach ($tool_calls as $tool_call) {
                if (!is_array($tool_call)) {
                    continue;
                }
                $call_id = trim((string) ($tool_call['id'] ?? ''));
                $fn = isset($tool_call['function']) && is_array($tool_call['function']) ? $tool_call['function'] : [];
                $name = sanitize_key((string) ($fn['name'] ?? ''));
                $args_raw = (string) ($fn['arguments'] ?? '');
                $args = json_decode($args_raw, true);
                if (!is_array($args)) {
                    $args = [];
                }
                $result = self::execute_tool_call($name, $args);
                if (is_array($result)) {
                    $summary = trim((string) ($result['summary'] ?? ''));
                    if ($summary !== '') {
                        $tool_summaries[] = $summary;
                    }
                }

                $tool_message = [
                    'role' => 'tool',
                    'content' => wp_json_encode($result),
                ];
                if ($call_id !== '') {
                    $tool_message['tool_call_id'] = $call_id;
                }
                $messages[] = $tool_message;
            }
        }

        if (!empty($tool_summaries)) {
            return ['ok' => true, 'reply' => implode(' ', array_values(array_unique($tool_summaries)))];
        }
        return ['ok' => false, 'message' => 'AI nije uspeo da generiše odgovor posle obrade alata.'];
    }

    private static function build_tools_schema()
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_club_position',
                    'description' => 'Get current league table position of a club',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'club_name' => ['type' => 'string'],
                        ],
                        'required' => ['club_name'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_last_match',
                    'description' => 'Get last match result for a club',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'club_name' => ['type' => 'string'],
                        ],
                        'required' => ['club_name'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_next_match',
                    'description' => 'Get next scheduled match',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'club_name' => ['type' => 'string'],
                        ],
                        'required' => ['club_name'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_club_squad',
                    'description' => 'Get current player squad for a club',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'club_name' => ['type' => 'string'],
                        ],
                        'required' => ['club_name'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_player_info',
                    'description' => 'Get player profile details and current club',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'player_name' => ['type' => 'string'],
                        ],
                        'required' => ['player_name'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_entities',
                    'description' => 'Search players, clubs, competitions, and matches by keyword',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string'],
                        ],
                        'required' => ['query'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];
    }

    private static function execute_tool_call($name, array $args)
    {
        $club_name = isset($args['club_name']) ? sanitize_text_field((string) $args['club_name']) : '';
        $player_name = isset($args['player_name']) ? sanitize_text_field((string) $args['player_name']) : '';
        $query = isset($args['query']) ? sanitize_text_field((string) $args['query']) : '';
        if ($club_name === '') {
            if ($name === 'get_player_info' && $player_name === '') {
                return ['ok' => false, 'error' => 'Nedostaje player_name argument.'];
            }
            if ($name === 'search_entities' && $query === '') {
                return ['ok' => false, 'error' => 'Nedostaje query argument.'];
            }
        }

        if ($name === 'get_club_position') {
            return opentt_get_club_position($club_name);
        }
        if ($name === 'get_last_match') {
            return opentt_get_last_match($club_name);
        }
        if ($name === 'get_next_match') {
            return opentt_get_next_match($club_name);
        }
        if ($name === 'get_club_squad') {
            return self::tool_get_club_squad($club_name);
        }
        if ($name === 'get_player_info') {
            return self::tool_get_player_info($player_name);
        }
        if ($name === 'search_entities') {
            return self::tool_search_entities($query);
        }

        return ['ok' => false, 'error' => 'Nepoznat tool: ' . $name];
    }

    private static function resolve_icon_url()
    {
        $base_path = dirname(__DIR__) . '/assets/icons/';
        $base_file = dirname(__DIR__) . '/opentt-unified-core.php';
        $candidates = [
            'ai-icon.svg',
            'ai.svg',
            'chat-icon.svg',
            'assistant.svg',
            'search-icon.svg',
        ];
        foreach ($candidates as $file) {
            if (is_readable($base_path . $file)) {
                return plugins_url('assets/icons/' . $file, $base_file);
            }
        }
        return '';
    }

    private static function validate_api_key_connection($api_key)
    {
        $api_key = trim((string) $api_key);
        if ($api_key === '') {
            return ['ok' => false, 'message' => 'API ključ je prazan.'];
        }

        $response = wp_remote_get(
            'https://api.groq.com/openai/v1/models',
            [
                'timeout' => 12,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => (string) $response->get_error_message()];
        }

        $status = intval(wp_remote_retrieve_response_code($response));
        $body = (string) wp_remote_retrieve_body($response);
        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'message' => 'OK'];
        }

        return ['ok' => false, 'message' => self::extract_api_error_message($body, $status)];
    }

    private static function get_selected_model()
    {
        $model = trim((string) get_option(self::OPTION_MODEL, self::DEFAULT_MODEL));
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }
        return sanitize_text_field($model);
    }

    private static function clear_models_cache()
    {
        delete_transient(self::MODELS_TRANSIENT_KEY);
    }

    private static function fetch_available_models($api_key, $force = false)
    {
        $api_key = trim((string) $api_key);
        if ($api_key === '') {
            return ['ok' => false, 'models' => [], 'message' => 'API ključ nije unet.'];
        }

        if (!$force) {
            $cached = get_transient(self::MODELS_TRANSIENT_KEY);
            if (is_array($cached) && !empty($cached['models']) && is_array($cached['models'])) {
                return ['ok' => true, 'models' => array_values($cached['models']), 'message' => 'cache'];
            }
        }

        $response = wp_remote_get(
            'https://api.groq.com/openai/v1/models',
            [
                'timeout' => 14,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
            ]
        );

        if (is_wp_error($response)) {
            return ['ok' => false, 'models' => [], 'message' => (string) $response->get_error_message()];
        }
        $status = intval(wp_remote_retrieve_response_code($response));
        $body = (string) wp_remote_retrieve_body($response);
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'models' => [], 'message' => self::extract_api_error_message($body, $status)];
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['ok' => false, 'models' => [], 'message' => 'Nevalidan odgovor za listu modela.'];
        }
        $data = isset($json['data']) && is_array($json['data']) ? $json['data'] : [];
        $models = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $models[] = $id;
        }
        $models = array_values(array_unique($models));
        sort($models, SORT_NATURAL | SORT_FLAG_CASE);
        if (empty($models)) {
            return ['ok' => false, 'models' => [], 'message' => 'Nema dostupnih modela za ovaj API ključ.'];
        }
        set_transient(self::MODELS_TRANSIENT_KEY, ['models' => $models], 10 * MINUTE_IN_SECONDS);
        return ['ok' => true, 'models' => $models, 'message' => 'ok'];
    }

    private static function call_groq_chat_completion($api_key, $model, array $messages, array $tools = [], $tool_choice = 'auto', $timeout = 20)
    {
        $model = trim((string) $model);
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }
        $body = [
            'model' => $model,
            'messages' => $messages,
        ];
        if (!empty($tools)) {
            $body['tools'] = $tools;
            $body['tool_choice'] = $tool_choice;
        }
        $response = wp_remote_post(
            'https://api.groq.com/openai/v1/chat/completions',
            [
                'timeout' => max(5, intval($timeout)),
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode($body),
            ]
        );

        if (is_wp_error($response)) {
            return [
                'error' => [
                    'message' => 'Neuspešna konekcija ka AI servisu: ' . (string) $response->get_error_message(),
                ],
            ];
        }

        $status = intval(wp_remote_retrieve_response_code($response));
        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '') {
            return ['error' => ['message' => 'AI servis je vratio prazan odgovor.']];
        }
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return ['error' => ['message' => 'Nevalidan JSON odgovor AI servisa.']];
        }
        if ($status < 200 || $status >= 300) {
            $message = self::extract_api_error_message($body, $status);
            return ['error' => ['message' => $message]];
        }
        return $json;
    }

    public static function tool_get_club_position($club_name)
    {
        global $wpdb;
        $club = self::find_club_by_name($club_name);
        if (empty($club)) {
            return ['ok' => false, 'error' => 'Klub nije pronađen.'];
        }
        $club_id = intval($club['id']);
        $competition = OpenTT_Unified_Shortcode_Stats_Query_Service::db_get_latest_competition_for_club($club_id);
        if (!is_array($competition) || empty($competition['liga_slug'])) {
            return ['ok' => false, 'error' => 'Nema dostupnih podataka o ligi za klub.'];
        }

        $liga_slug = sanitize_title((string) ($competition['liga_slug'] ?? ''));
        $sezona_slug = sanitize_title((string) ($competition['sezona_slug'] ?? ''));
        $table = OpenTT_Unified_Core::db_table('matches');
        if (!self::table_exists($table)) {
            return ['ok' => false, 'error' => 'Tabela utakmica nije dostupna.'];
        }

        $where = ['liga_slug=%s', 'home_club_post_id > 0', 'away_club_post_id > 0', '(played=1 OR home_score<>0 OR away_score<>0)'];
        $params = [$liga_slug];
        if ($sezona_slug !== '') {
            $where[] = 'sezona_slug=%s';
            $params[] = $sezona_slug;
        }
        $sql = "SELECT home_club_post_id, away_club_post_id, home_score, away_score
                FROM {$table}
                WHERE " . implode(' AND ', $where);
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params));
        if (!is_array($rows) || empty($rows)) {
            return ['ok' => false, 'error' => 'Nema odigranih mečeva za tabelu.'];
        }

        $stat = [];
        foreach ($rows as $r) {
            $home = intval($r->home_club_post_id ?? 0);
            $away = intval($r->away_club_post_id ?? 0);
            if ($home <= 0 || $away <= 0) {
                continue;
            }
            if (!isset($stat[$home])) {
                $stat[$home] = ['club_id' => $home, 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'for' => 0, 'against' => 0, 'points' => 0];
            }
            if (!isset($stat[$away])) {
                $stat[$away] = ['club_id' => $away, 'played' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'for' => 0, 'against' => 0, 'points' => 0];
            }

            $hs = intval($r->home_score ?? 0);
            $as = intval($r->away_score ?? 0);
            $stat[$home]['played']++;
            $stat[$away]['played']++;
            $stat[$home]['for'] += $hs;
            $stat[$home]['against'] += $as;
            $stat[$away]['for'] += $as;
            $stat[$away]['against'] += $hs;
            if ($hs > $as) {
                $stat[$home]['wins']++;
                $stat[$home]['points'] += 2;
                $stat[$away]['losses']++;
            } elseif ($hs < $as) {
                $stat[$away]['wins']++;
                $stat[$away]['points'] += 2;
                $stat[$home]['losses']++;
            } else {
                $stat[$home]['draws']++;
                $stat[$away]['draws']++;
                $stat[$home]['points']++;
                $stat[$away]['points']++;
            }
        }

        $standings = array_values($stat);
        usort($standings, static function ($a, $b) {
            $cmp = intval($b['points']) <=> intval($a['points']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = intval($b['wins']) <=> intval($a['wins']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $a_diff = intval($a['for']) - intval($a['against']);
            $b_diff = intval($b['for']) - intval($b['against']);
            $cmp = $b_diff <=> $a_diff;
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = intval($b['for']) <=> intval($a['for']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strnatcasecmp((string) get_the_title(intval($a['club_id'])), (string) get_the_title(intval($b['club_id'])));
        });

        $position = 0;
        $row = null;
        foreach ($standings as $i => $item) {
            if (intval($item['club_id']) === $club_id) {
                $position = $i + 1;
                $row = $item;
                break;
            }
        }
        if ($position <= 0 || !is_array($row)) {
            return ['ok' => false, 'error' => 'Klub nije pronađen u aktuelnoj tabeli.'];
        }

        return [
            'ok' => true,
            'club_name' => (string) $club['title'],
            'liga_slug' => $liga_slug,
            'sezona_slug' => $sezona_slug,
            'position' => $position,
            'played' => intval($row['played']),
            'wins' => intval($row['wins']),
            'draws' => intval($row['draws']),
            'losses' => intval($row['losses']),
            'points' => intval($row['points']),
            'summary' => sprintf('%s se trenutno nalazi na %d. mestu.', (string) $club['title'], $position),
        ];
    }

    public static function tool_get_last_match($club_name)
    {
        global $wpdb;
        $club = self::find_club_by_name($club_name);
        if (empty($club)) {
            return ['ok' => false, 'error' => 'Klub nije pronađen.'];
        }
        $club_id = intval($club['id']);
        $table = OpenTT_Unified_Core::db_table('matches');
        if (!self::table_exists($table)) {
            return ['ok' => false, 'error' => 'Tabela utakmica nije dostupna.'];
        }

        $sql = $wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE (home_club_post_id=%d OR away_club_post_id=%d)
               AND (played=1 OR home_score<>0 OR away_score<>0)
             ORDER BY match_date DESC, id DESC
             LIMIT 1",
            $club_id,
            $club_id
        );
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return ['ok' => false, 'error' => 'Nema odigranih mečeva za ovaj klub.'];
        }

        $home_id = intval($row->home_club_post_id ?? 0);
        $away_id = intval($row->away_club_post_id ?? 0);
        $home_name = (string) get_the_title($home_id);
        $away_name = (string) get_the_title($away_id);
        $date = OpenTT_Unified_Readonly_Helpers::display_match_date((string) ($row->match_date ?? ''));
        $result = intval($row->home_score ?? 0) . ':' . intval($row->away_score ?? 0);

        return [
            'ok' => true,
            'club_name' => (string) $club['title'],
            'match' => [
                'home' => $home_name,
                'away' => $away_name,
                'result' => $result,
                'date' => $date,
                'liga_slug' => sanitize_title((string) ($row->liga_slug ?? '')),
                'sezona_slug' => sanitize_title((string) ($row->sezona_slug ?? '')),
            ],
            'summary' => sprintf('Poslednja utakmica: %s %s %s (%s).', $home_name, $result, $away_name, $date),
        ];
    }

    public static function tool_get_next_match($club_name)
    {
        global $wpdb;
        $club = self::find_club_by_name($club_name);
        if (empty($club)) {
            return ['ok' => false, 'error' => 'Klub nije pronađen.'];
        }
        $club_id = intval($club['id']);
        $table = OpenTT_Unified_Core::db_table('matches');
        if (!self::table_exists($table)) {
            return ['ok' => false, 'error' => 'Tabela utakmica nije dostupna.'];
        }

        $now = (string) current_time('mysql');
        $sql = $wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE (home_club_post_id=%d OR away_club_post_id=%d)
               AND (played=0 OR (home_score=0 AND away_score=0))
               AND match_date >= %s
             ORDER BY match_date ASC, id ASC
             LIMIT 1",
            $club_id,
            $club_id,
            $now
        );
        $row = $wpdb->get_row($sql);
        if (!$row) {
            return ['ok' => false, 'error' => 'Nema zakazanih narednih mečeva za ovaj klub.'];
        }

        $home_id = intval($row->home_club_post_id ?? 0);
        $away_id = intval($row->away_club_post_id ?? 0);
        $home_name = (string) get_the_title($home_id);
        $away_name = (string) get_the_title($away_id);
        $date = OpenTT_Unified_Readonly_Helpers::display_match_date((string) ($row->match_date ?? ''));
        $time = self::format_match_time((string) ($row->match_date ?? ''));
        $location = trim((string) ($row->location ?? ''));

        return [
            'ok' => true,
            'club_name' => (string) $club['title'],
            'match' => [
                'home' => $home_name,
                'away' => $away_name,
                'date' => $date,
                'time' => $time,
                'location' => $location,
                'liga_slug' => sanitize_title((string) ($row->liga_slug ?? '')),
                'sezona_slug' => sanitize_title((string) ($row->sezona_slug ?? '')),
            ],
            'summary' => sprintf('Sledeća utakmica: %s - %s, %s %s%s.', $home_name, $away_name, $date, $time !== '' ? $time : '', $location !== '' ? (' @ ' . $location) : ''),
        ];
    }

    public static function tool_get_club_squad($club_name)
    {
        $club = self::find_club_by_name($club_name);
        if (empty($club)) {
            return ['ok' => false, 'error' => 'Klub nije pronađen.'];
        }
        $club_id = intval($club['id']);
        if ($club_id <= 0) {
            return ['ok' => false, 'error' => 'Neispravan klub.'];
        }

        $players = get_posts([
            'post_type' => 'igrac',
            'post_status' => 'publish',
            'numberposts' => 300,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ]);
        $names = [];
        if (is_array($players)) {
            foreach ($players as $player_id) {
                $player_id = intval($player_id);
                if ($player_id <= 0) {
                    continue;
                }
                $pid_club = intval(OpenTT_Unified_Admin_Readonly_Helpers::get_player_club_id($player_id));
                if ($pid_club !== $club_id) {
                    continue;
                }
                $title = trim((string) get_the_title($player_id));
                if ($title !== '') {
                    $names[] = $title;
                }
            }
        }
        $names = array_values(array_unique($names));
        if (empty($names)) {
            return ['ok' => false, 'error' => 'Nema dostupnog sastava za ovaj klub.'];
        }

        return [
            'ok' => true,
            'club_name' => (string) $club['title'],
            'players' => $names,
            'count' => count($names),
            'summary' => sprintf('Sastav kluba %s: %s.', (string) $club['title'], implode(', ', array_slice($names, 0, 20))),
        ];
    }

    public static function tool_get_player_info($player_name)
    {
        $player = self::find_player_by_name($player_name);
        if (empty($player)) {
            return ['ok' => false, 'error' => 'Igrač nije pronađen.'];
        }

        $player_id = intval($player['id']);
        $club_id = intval(OpenTT_Unified_Admin_Readonly_Helpers::get_player_club_id($player_id));
        $club_name = $club_id > 0 ? (string) get_the_title($club_id) : '';
        $country = trim((string) get_post_meta($player_id, 'opentt_player_country', true));
        $birth_year = trim((string) get_post_meta($player_id, 'opentt_player_year', true));

        return [
            'ok' => true,
            'player_name' => (string) $player['title'],
            'club_name' => $club_name,
            'country' => $country,
            'birth_year' => $birth_year,
            'profile_url' => (string) get_permalink($player_id),
            'summary' => sprintf(
                '%s trenutno igra za %s.',
                (string) $player['title'],
                $club_name !== '' ? $club_name : 'nepoznat klub'
            ),
        ];
    }

    public static function tool_search_entities($query)
    {
        global $wpdb;
        $query = trim((string) $query);
        $query = sanitize_text_field($query);
        if ($query === '') {
            return ['ok' => false, 'error' => 'Prazan upit.'];
        }

        $out = [
            'players' => [],
            'clubs' => [],
            'competitions' => [],
            'matches' => [],
        ];

        $posts_table = $wpdb->posts;
        $like = '%' . $wpdb->esc_like($query) . '%';
        $player_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$posts_table}
             WHERE post_type='igrac' AND post_status='publish' AND post_title LIKE %s
             ORDER BY post_title ASC LIMIT 8",
            $like
        ));
        if (is_array($player_rows)) {
            foreach ($player_rows as $r) {
                $out['players'][] = (string) ($r->post_title ?? '');
            }
        }
        $club_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$posts_table}
             WHERE post_type='klub' AND post_status='publish' AND post_title LIKE %s
             ORDER BY post_title ASC LIMIT 8",
            $like
        ));
        if (is_array($club_rows)) {
            foreach ($club_rows as $r) {
                $out['clubs'][] = (string) ($r->post_title ?? '');
            }
        }

        $matches_table = OpenTT_Unified_Core::db_table('matches');
        if (self::table_exists($matches_table)) {
            $comp_rows = $wpdb->get_results(
                "SELECT DISTINCT liga_slug, sezona_slug FROM {$matches_table}
                 WHERE liga_slug<>'' ORDER BY id DESC LIMIT 200"
            );
            if (is_array($comp_rows)) {
                foreach ($comp_rows as $r) {
                    $comp = trim(OpenTT_Unified_Readonly_Helpers::slug_to_title((string) ($r->liga_slug ?? '')) . ' - ' . OpenTT_Unified_Readonly_Helpers::slug_to_title((string) ($r->sezona_slug ?? '')));
                    if ($comp !== '' && stripos(self::fold_text_for_match($comp), self::fold_text_for_match($query)) !== false) {
                        $out['competitions'][] = $comp;
                    }
                    if (count($out['competitions']) >= 8) {
                        break;
                    }
                }
            }

            $match_rows = $wpdb->get_results(
                "SELECT id, home_club_post_id, away_club_post_id, home_score, away_score, match_date
                 FROM {$matches_table}
                 ORDER BY match_date DESC, id DESC LIMIT 300"
            );
            if (is_array($match_rows)) {
                $needle = self::fold_text_for_match($query);
                foreach ($match_rows as $r) {
                    $home = trim((string) get_the_title(intval($r->home_club_post_id ?? 0)));
                    $away = trim((string) get_the_title(intval($r->away_club_post_id ?? 0)));
                    $blob = self::fold_text_for_match($home . ' ' . $away);
                    if ($home === '' && $away === '') {
                        continue;
                    }
                    if ($needle !== '' && strpos($blob, $needle) === false) {
                        continue;
                    }
                    $out['matches'][] = sprintf(
                        '%s %d:%d %s (%s)',
                        $home,
                        intval($r->home_score ?? 0),
                        intval($r->away_score ?? 0),
                        $away,
                        OpenTT_Unified_Readonly_Helpers::display_match_date((string) ($r->match_date ?? ''))
                    );
                    if (count($out['matches']) >= 8) {
                        break;
                    }
                }
            }
        }

        return [
            'ok' => true,
            'query' => $query,
            'results' => $out,
            'summary' => 'Pronađeni su rezultati kroz igrače, klubove, takmičenja i utakmice.',
        ];
    }

    private static function find_club_by_name($club_name)
    {
        global $wpdb;
        $club_name = trim((string) $club_name);
        $club_name = sanitize_text_field($club_name);
        if ($club_name === '') {
            return [];
        }

        $exact = get_page_by_title($club_name, OBJECT, 'klub');
        if ($exact && intval($exact->ID) > 0) {
            return [
                'id' => intval($exact->ID),
                'title' => (string) $exact->post_title,
            ];
        }

        $posts_table = $wpdb->posts;
        $like = '%' . $wpdb->esc_like($club_name) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title
                 FROM {$posts_table}
                 WHERE post_type='klub'
                   AND post_status='publish'
                   AND post_title LIKE %s
                 ORDER BY post_title ASC
                 LIMIT 25",
                $like
            )
        );
        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        $needle = self::fold_text_for_match($club_name);
        $best = null;
        $best_score = -PHP_INT_MAX;
        foreach ($rows as $row) {
            $title = trim((string) ($row->post_title ?? ''));
            if ($title === '') {
                continue;
            }
            $title_fold = self::fold_text_for_match($title);
            $score = 0;
            if ($title_fold === $needle) {
                $score += 300;
            } elseif (strpos($title_fold, $needle) !== false) {
                $score += 120;
            }
            $dist = function_exists('levenshtein') ? levenshtein($needle, $title_fold) : 99;
            $score -= min(100, intval($dist * 5));
            if ($score > $best_score) {
                $best_score = $score;
                $best = [
                    'id' => intval($row->ID ?? 0),
                    'title' => $title,
                ];
            }
        }
        return is_array($best) ? $best : [];
    }

    private static function find_player_by_name($player_name)
    {
        global $wpdb;
        $player_name = trim((string) $player_name);
        $player_name = sanitize_text_field($player_name);
        if ($player_name === '') {
            return [];
        }

        $exact = get_page_by_title($player_name, OBJECT, 'igrac');
        if ($exact && intval($exact->ID) > 0) {
            return [
                'id' => intval($exact->ID),
                'title' => (string) $exact->post_title,
            ];
        }

        $posts_table = $wpdb->posts;
        $like = '%' . $wpdb->esc_like($player_name) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title
                 FROM {$posts_table}
                 WHERE post_type='igrac'
                   AND post_status='publish'
                   AND post_title LIKE %s
                 ORDER BY post_title ASC
                 LIMIT 25",
                $like
            )
        );
        if (!is_array($rows) || empty($rows)) {
            return [];
        }
        $needle = self::fold_text_for_match($player_name);
        $best = null;
        $best_score = -PHP_INT_MAX;
        foreach ($rows as $row) {
            $title = trim((string) ($row->post_title ?? ''));
            if ($title === '') {
                continue;
            }
            $title_fold = self::fold_text_for_match($title);
            $score = 0;
            if ($title_fold === $needle) {
                $score += 300;
            } elseif (strpos($title_fold, $needle) !== false) {
                $score += 120;
            }
            $dist = function_exists('levenshtein') ? levenshtein($needle, $title_fold) : 99;
            $score -= min(100, intval($dist * 5));
            if ($score > $best_score) {
                $best_score = $score;
                $best = [
                    'id' => intval($row->ID ?? 0),
                    'title' => $title,
                ];
            }
        }
        return is_array($best) ? $best : [];
    }

    private static function fold_text_for_match($value)
    {
        $value = (string) $value;
        $map = [
            'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj',
            'Č' => 'c', 'Ć' => 'c', 'Š' => 's', 'Ž' => 'z', 'Đ' => 'dj',
        ];
        $out = strtr($value, $map);
        $out = function_exists('mb_strtolower') ? mb_strtolower($out, 'UTF-8') : strtolower($out);
        $out = preg_replace('/\s+/u', ' ', $out);
        return trim((string) $out);
    }

    private static function format_match_time($match_date)
    {
        $match_date = trim((string) $match_date);
        if ($match_date === '' || !preg_match('/\s(\d{1,2}):(\d{2})(?::\d{2})?$/', $match_date, $m)) {
            return '';
        }
        $hour = str_pad((string) intval($m[1]), 2, '0', STR_PAD_LEFT);
        $minute = str_pad((string) intval($m[2]), 2, '0', STR_PAD_LEFT);
        return $hour . ':' . $minute;
    }

    private static function table_exists($table_name)
    {
        global $wpdb;
        $table_name = (string) $table_name;
        if ($table_name === '') {
            return false;
        }
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        return $found === $table_name;
    }

    private static function extract_api_error_message($raw_body, $status_code = 0)
    {
        $raw_body = trim((string) $raw_body);
        $status_code = intval($status_code);
        if ($raw_body !== '') {
            $decoded = json_decode($raw_body, true);
            if (is_array($decoded)) {
                $msg = '';
                if (isset($decoded['error']) && is_array($decoded['error'])) {
                    $msg = (string) ($decoded['error']['message'] ?? '');
                } elseif (isset($decoded['message'])) {
                    $msg = (string) $decoded['message'];
                }
                $msg = trim($msg);
                if ($msg !== '') {
                    return $msg . ($status_code > 0 ? ' (HTTP ' . $status_code . ')' : '');
                }
            }
        }
        if ($status_code > 0) {
            return 'AI servis je vratio grešku (HTTP ' . $status_code . ').';
        }
        return 'Nepoznata greška AI servisa.';
    }

    private static function render_inline_assets_once()
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;
        ?>
        <style>
            .opentt-ai { position: relative; display: inline-flex; align-items: center; max-width: 100%; }
            .opentt-ai-toggle { width: 40px; height: 40px; border-radius: 10px; border: 1px solid rgba(255,255,255,.18); background: rgba(3,23,69,.92); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: transform .18s ease, border-color .18s ease, background .18s ease; touch-action: manipulation; }
            .opentt-ai-toggle:hover { transform: translateY(-1px); border-color: rgba(255,255,255,.34); background: rgba(8,30,82,.95); }
            .opentt-ai-toggle-icon { width: 18px; height: 18px; display: block; filter: brightness(0) invert(1); }
            .opentt-ai-backdrop { position: fixed; inset: 0; background: radial-gradient(circle at 30% 20%, rgba(61,124,255,.22), rgba(2,10,28,.72) 55%, rgba(1,5,16,.84) 100%); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 9998; }
            .opentt-ai-panel { position: fixed; inset: 0; width: 100vw; height: 100dvh; background: linear-gradient(140deg, rgba(12,39,102,.52) 0%, rgba(5,20,58,.86) 32%, rgba(3,13,40,.97) 100%); border: 0; border-radius: 0; box-shadow: 0 22px 48px rgba(0,0,0,.48), 0 0 0 1px rgba(45,119,245,.18) inset; padding: 72px 24px 24px; z-index: 9999; box-sizing: border-box; overflow-y: auto; overscroll-behavior: contain; }
            .opentt-ai-close { display: inline-block; border: 0; background: transparent; color: #fff; font-size: 32px; line-height: 1; cursor: pointer; position: absolute; top: 12px; right: 16px; padding: 4px 10px; z-index: 2; }
            .opentt-ai-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
            .opentt-ai-brand { display: inline-flex; align-items: center; font-size: 13px; letter-spacing: .12em; font-weight: 800; text-transform: uppercase; color: #9ec8ff; padding: 4px 10px; border-radius: 999px; border: 1px solid rgba(130, 176, 255, .45); background: linear-gradient(90deg, rgba(64,118,255,.18), rgba(37,196,255,.16)); box-shadow: 0 0 20px rgba(54,128,255,.16); }
            .opentt-ai-label { display: block; font-size: 28px; font-weight: 700; line-height: 1.2; color: rgba(255,255,255,.92); letter-spacing: .06em; text-transform: uppercase; }
            .opentt-ai-messages { min-height: 220px; max-height: 52dvh; overflow-y: auto; border: 1px solid rgba(255,255,255,.08); background: #04102b; border-radius: 10px; padding: 12px; margin-bottom: 10px; display: flex; flex-direction: column; gap: 10px; }
            .opentt-ai-row { display: flex; align-items: flex-end; gap: 8px; width: 100%; }
            .opentt-ai-row.is-user { justify-content: flex-end; }
            .opentt-ai-row.is-assistant,
            .opentt-ai-row.is-error { justify-content: flex-start; }
            .opentt-ai-avatar { width: 22px; height: 22px; border-radius: 50%; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.08); display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; overflow: hidden; }
            .opentt-ai-avatar img { width: 14px; height: 14px; display: block; filter: brightness(0) invert(1); }
            .opentt-ai-msg { margin: 0; padding: 9px 11px; border-radius: 12px; line-height: 1.45; font-size: 14px; max-width: min(78%, 760px); word-break: break-word; }
            .opentt-ai-row.is-user .opentt-ai-msg { background: rgba(61,124,255,.2); border: 1px solid rgba(61,124,255,.35); border-bottom-right-radius: 4px; }
            .opentt-ai-row.is-assistant .opentt-ai-msg { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.14); border-bottom-left-radius: 4px; }
            .opentt-ai-row.is-error .opentt-ai-msg { background: rgba(255,75,75,.16); border: 1px solid rgba(255,75,75,.4); border-bottom-left-radius: 4px; }
            .opentt-ai-msg.is-thinking { opacity: .86; font-style: italic; }
            .opentt-ai-form { display: grid; grid-template-columns: 1fr auto; gap: 8px; }
            .opentt-ai-input { min-height: 42px; border-radius: 8px; border: 1px solid rgba(255,255,255,.18); background: #0a1d4a; color: #fff; padding: 0 12px; font-size: 16px; }
            .opentt-ai-send { min-height: 42px; border-radius: 8px; border: 1px solid rgba(61,124,255,.55); background: #2c63d6; color: #fff; padding: 0 14px; cursor: pointer; font-weight: 600; }
            .opentt-ai-send[disabled] { opacity: .7; cursor: not-allowed; }
            body.opentt-ai-open, html.opentt-ai-open { overflow: hidden; height: 100%; overscroll-behavior: none; }
            @media (max-width: 767px) {
                .opentt-ai-panel { padding: 64px 14px 14px; }
                .opentt-ai-form { grid-template-columns: 1fr; }
                .opentt-ai-label { font-size: 24px; }
                .opentt-ai-messages { max-height: 58dvh; padding: 10px; }
                .opentt-ai-msg { max-width: 88%; font-size: 13px; }
                .opentt-ai-input { font-size: 16px; }
            }
        </style>
        <script>
            (function () {
                if (typeof window.openttAiFallbackToggle !== 'function') {
                    window.openttAiFallbackToggle = function (rootId, forceClose) {
                        var id = String(rootId || '');
                        if (!id) {
                            return false;
                        }
                        var root = document.getElementById(id);
                        if (!root) {
                            return false;
                        }
                        var panel = root.querySelector('.opentt-ai-panel');
                        var backdrop = root.querySelector('.opentt-ai-backdrop');
                        var toggle = root.querySelector('.opentt-ai-toggle');
                        if (!panel) {
                            return false;
                        }
                        var shouldClose = !!forceClose;
                        if (!shouldClose) {
                            shouldClose = !panel.hidden ? true : false;
                        }
                        if (shouldClose) {
                            panel.hidden = true;
                            if (backdrop) {
                                backdrop.hidden = true;
                            }
                            if (toggle) {
                                toggle.setAttribute('aria-expanded', 'false');
                            }
                            if (document.body && document.body.classList) {
                                document.body.classList.remove('opentt-ai-open');
                            }
                            if (document.documentElement && document.documentElement.classList) {
                                document.documentElement.classList.remove('opentt-ai-open');
                            }
                        } else {
                            panel.hidden = false;
                            if (backdrop) {
                                backdrop.hidden = false;
                            }
                            if (toggle) {
                                toggle.setAttribute('aria-expanded', 'true');
                            }
                            if (document.body && document.body.classList) {
                                document.body.classList.add('opentt-ai-open');
                            }
                            if (document.documentElement && document.documentElement.classList) {
                                document.documentElement.classList.add('opentt-ai-open');
                            }
                            var input = root.querySelector('.opentt-ai-input');
                            if (input && typeof input.focus === 'function') {
                                setTimeout(function () { input.focus(); }, 0);
                            }
                        }
                        return false;
                    };
                }

                function escText(value) {
                    return String(value == null ? '' : value);
                }

                function appendMessage(root, panelNode, role, text, opts) {
                    var list = panelNode && panelNode.querySelector ? panelNode.querySelector('.opentt-ai-messages') : null;
                    if (!list) {
                        return null;
                    }
                    var options = opts && typeof opts === 'object' ? opts : {};
                    var row = document.createElement('div');
                    var safeRole = String(role || 'assistant');
                    if (safeRole !== 'user' && safeRole !== 'assistant' && safeRole !== 'error') {
                        safeRole = 'assistant';
                    }
                    row.className = 'opentt-ai-row is-' + safeRole;
                    if (safeRole !== 'user') {
                        var avatar = document.createElement('span');
                        avatar.className = 'opentt-ai-avatar';
                        var iconUrl = String(root.getAttribute('data-ai-icon') || '');
                        if (iconUrl) {
                            var img = document.createElement('img');
                            img.src = iconUrl;
                            img.alt = '';
                            img.setAttribute('aria-hidden', 'true');
                            avatar.appendChild(img);
                        } else {
                            avatar.textContent = 'AI';
                        }
                        row.appendChild(avatar);
                    }
                    var bubble = document.createElement('div');
                    bubble.className = 'opentt-ai-msg';
                    if (options.thinking) {
                        bubble.className += ' is-thinking';
                    }
                    bubble.textContent = escText(text);
                    row.appendChild(bubble);
                    list.appendChild(row);
                    list.scrollTop = list.scrollHeight;
                    return row;
                }

                function init(root) {
                    if (!root || root.dataset.openttAiReady === '1') {
                        return;
                    }
                    root.dataset.openttAiReady = '1';

                    var toggle = root.querySelector('.opentt-ai-toggle');
                    var panel = root.querySelector('.opentt-ai-panel');
                    var backdrop = root.querySelector('.opentt-ai-backdrop');
                    var close = root.querySelector('.opentt-ai-close');
                    var input = root.querySelector('.opentt-ai-input');
                    var send = root.querySelector('.opentt-ai-send');
                    if (!toggle || !panel || !input || !send) {
                        return;
                    }

                    var ajaxUrl = String(root.getAttribute('data-ajax-url') || '');
                    var nonce = String(root.getAttribute('data-nonce') || '');
                    var bodyEl = document.body;
                    var htmlEl = document.documentElement;
                    var conversation = [];
                    var thinkingNode = null;
                    var lastToggleAt = 0;

                    function openPanel() {
                        panel.hidden = false;
                        if (backdrop) {
                            backdrop.hidden = false;
                        }
                        toggle.setAttribute('aria-expanded', 'true');
                        if (bodyEl && bodyEl.classList) {
                            bodyEl.classList.add('opentt-ai-open');
                        }
                        if (htmlEl && htmlEl.classList) {
                            htmlEl.classList.add('opentt-ai-open');
                        }
                        setTimeout(function () {
                            input.focus();
                        }, 0);
                        var list = panel.querySelector('.opentt-ai-messages');
                        if (list && !list.dataset.openttAiGreetingShown) {
                            list.dataset.openttAiGreetingShown = '1';
                            appendMessage(root, panel, 'assistant', 'Ćao! Ja sam STKB.AI asistent. Pitaj me o ligama, klubovima, igračima i rezultatima.');
                        }
                    }

                    function closePanel() {
                        panel.hidden = true;
                        if (backdrop) {
                            backdrop.hidden = true;
                        }
                        toggle.setAttribute('aria-expanded', 'false');
                        if (bodyEl && bodyEl.classList) {
                            bodyEl.classList.remove('opentt-ai-open');
                        }
                        if (htmlEl && htmlEl.classList) {
                            htmlEl.classList.remove('opentt-ai-open');
                        }
                    }

                    function setLoading(state) {
                        send.disabled = !!state;
                        send.textContent = state ? 'Šaljem...' : 'Pošalji';
                    }

                    function showThinking() {
                        if (thinkingNode) {
                            return;
                        }
                        thinkingNode = appendMessage(root, panel, 'assistant', 'STKB.AI razmišlja...', { thinking: true });
                    }

                    function hideThinking() {
                        if (thinkingNode && thinkingNode.parentNode) {
                            thinkingNode.parentNode.removeChild(thinkingNode);
                        }
                        thinkingNode = null;
                    }

                    function submit() {
                        var question = String(input.value || '').trim();
                        if (!question) {
                            appendMessage(root, panel, 'error', 'Unesi poruku.');
                            return;
                        }
                        if (!ajaxUrl || !nonce) {
                            appendMessage(root, panel, 'error', 'AI chat nije pravilno podešen.');
                            return;
                        }

                        appendMessage(root, panel, 'user', question);
                        input.value = '';
                        setLoading(true);
                        showThinking();

                        var body = new URLSearchParams();
                        body.set('action', 'opentt_ai_chat');
                        body.set('nonce', nonce);
                        body.set('message', question);
                        body.set('history', JSON.stringify(conversation.slice(-20)));

                        fetch(ajaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            body: body.toString()
                        })
                        .then(function (res) { return res.json(); })
                        .then(function (payload) {
                            setLoading(false);
                            hideThinking();
                            if (!payload || payload.success !== true) {
                                var errorText = payload && payload.data && payload.data.message ? payload.data.message : 'AI trenutno nije dostupan.';
                                appendMessage(root, panel, 'error', errorText);
                                conversation.push({ role: 'user', content: String(question) });
                                return;
                            }
                            if (!payload.data || !payload.data.reply) {
                                appendMessage(root, panel, 'error', 'AI nije poslao odgovor.');
                                return;
                            }
                            appendMessage(root, panel, 'assistant', payload.data.reply);
                            conversation.push({ role: 'user', content: String(question) });
                            conversation.push({ role: 'assistant', content: String(payload.data.reply) });
                            if (conversation.length > 40) {
                                conversation = conversation.slice(-40);
                            }
                        })
                        .catch(function () {
                            setLoading(false);
                            hideThinking();
                            appendMessage(root, panel, 'error', 'Došlo je do greške pri slanju.');
                        });
                    }

                    function handleToggle(e) {
                        var now = Date.now();
                        if (now - lastToggleAt < 320) {
                            return;
                        }
                        lastToggleAt = now;
                        if (e && typeof e.preventDefault === 'function') {
                            e.preventDefault();
                        }
                        if (e && typeof e.stopPropagation === 'function') {
                            e.stopPropagation();
                        }
                        if (panel.hidden) {
                            openPanel();
                        } else {
                            closePanel();
                        }
                    }

                    toggle.addEventListener('click', handleToggle);
                    if (close) {
                        close.addEventListener('click', closePanel);
                    }
                    if (backdrop) {
                        backdrop.addEventListener('click', closePanel);
                    }
                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && !panel.hidden) {
                            closePanel();
                        }
                    });

                    send.addEventListener('click', submit);
                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            submit();
                        }
                    });
                }

                var roots = document.querySelectorAll('[data-opentt-ai="1"]');
                for (var i = 0; i < roots.length; i++) {
                    init(roots[i]);
                }
            })();
        </script>
        <?php
    }
}
