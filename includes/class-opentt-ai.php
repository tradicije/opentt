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

final class OpenTT_AI
{
    const OPTION_API_KEY = 'opentt_ai_groq_api_key';
    const SETTINGS_GROUP = 'opentt_ai_settings_group';
    const NONCE_ACTION = 'opentt_ai_chat';
    const AJAX_ACTION = 'opentt_ai_chat';

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
            'AI Chat',
            'AI Chat',
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
    }

    public static function sanitize_api_key($value)
    {
        $value = trim((string) $value);
        $value = preg_replace('/[\r\n\t]+/', '', $value);
        return sanitize_text_field((string) $value);
    }

    public static function render_settings_page()
    {
        $capability = defined('OpenTT_Unified_Core::CAP') ? OpenTT_Unified_Core::CAP : 'manage_options';
        if (!current_user_can($capability)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'opentt'));
        }

        echo '<div class="wrap">';
        echo '<h1>OpenTT AI Chat Settings</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::SETTINGS_GROUP);

        $api_key = (string) get_option(self::OPTION_API_KEY, '');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="opentt-ai-groq-api-key">Groq API Key</label></th>';
        echo '<td>';
        echo '<input id="opentt-ai-groq-api-key" name="' . esc_attr(self::OPTION_API_KEY) . '" type="text" class="regular-text" value="' . esc_attr($api_key) . '" autocomplete="off" />';
        echo '<p class="description">API key is stored in WordPress options and never exposed on frontend.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</tbody></table>';

        submit_button('Save Settings');
        echo '</form>';
        echo '</div>';
    }

    public static function render_shortcode($atts = [])
    {
        $atts = shortcode_atts([
            'placeholder' => 'Ask about matches, players or leagues...',
            'button' => 'Send',
        ], (array) $atts, 'opentt_ai');

        $uid = 'opentt-ai-' . wp_unique_id();
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce(self::NONCE_ACTION);
        $placeholder = trim((string) $atts['placeholder']);
        if ($placeholder === '') {
            $placeholder = 'Ask about matches, players or leagues...';
        }
        $button = trim((string) $atts['button']);
        if ($button === '') {
            $button = 'Send';
        }

        ob_start();
        ?>
        <div id="<?php echo esc_attr($uid); ?>" class="opentt-ai" data-opentt-ai="1" data-ajax-url="<?php echo esc_url($ajax_url); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <div class="opentt-ai-messages" aria-live="polite"></div>
            <div class="opentt-ai-form">
                <input type="text" class="opentt-ai-input" placeholder="<?php echo esc_attr($placeholder); ?>" />
                <button type="button" class="opentt-ai-send"><?php echo esc_html($button); ?></button>
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
            wp_send_json_error(['message' => 'Invalid nonce.'], 403);
        }

        $message = isset($_POST['message']) ? sanitize_textarea_field((string) wp_unslash($_POST['message'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $message = trim($message);
        if ($message === '') {
            wp_send_json_error(['message' => 'Message is required.'], 400);
        }

        $api_key = trim((string) get_option(self::OPTION_API_KEY, ''));
        if ($api_key === '') {
            wp_send_json_error(['message' => 'Groq API key is not configured.'], 500);
        }

        $response = wp_remote_post(
            'https://api.groq.com/openai/v1/chat/completions',
            [
                'timeout' => 20,
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => 'llama3-8b-8192',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful assistant for a table tennis website. Answer briefly and clearly.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $message,
                        ],
                    ],
                ]),
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'AI request failed.'], 502);
        }

        $status = intval(wp_remote_retrieve_response_code($response));
        $body = (string) wp_remote_retrieve_body($response);
        if ($status < 200 || $status >= 300 || $body === '') {
            wp_send_json_error(['message' => 'AI service returned an error.'], 502);
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            wp_send_json_error(['message' => 'Invalid AI response format.'], 502);
        }

        $assistant = '';
        if (isset($json['choices'][0]['message']['content'])) {
            $assistant = trim((string) $json['choices'][0]['message']['content']);
        }
        if ($assistant === '') {
            wp_send_json_error(['message' => 'Empty AI response.'], 502);
        }

        wp_send_json_success([
            'reply' => $assistant,
        ]);
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
            .opentt-ai { max-width: 760px; margin: 0 auto; border: 1px solid rgba(255,255,255,.12); border-radius: 12px; background: #071538; color: #e9f1ff; padding: 12px; }
            .opentt-ai-messages { min-height: 220px; max-height: 420px; overflow-y: auto; border: 1px solid rgba(255,255,255,.08); background: #04102b; border-radius: 10px; padding: 10px; margin-bottom: 10px; }
            .opentt-ai-msg { margin: 0 0 10px 0; padding: 8px 10px; border-radius: 8px; line-height: 1.45; font-size: 14px; }
            .opentt-ai-msg-user { background: rgba(61,124,255,.18); border: 1px solid rgba(61,124,255,.35); }
            .opentt-ai-msg-bot { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); }
            .opentt-ai-msg-error { background: rgba(255,75,75,.16); border: 1px solid rgba(255,75,75,.4); }
            .opentt-ai-form { display: grid; grid-template-columns: 1fr auto; gap: 8px; }
            .opentt-ai-input { min-height: 42px; border-radius: 8px; border: 1px solid rgba(255,255,255,.18); background: #0a1d4a; color: #fff; padding: 0 12px; }
            .opentt-ai-send { min-height: 42px; border-radius: 8px; border: 1px solid rgba(61,124,255,.55); background: #2c63d6; color: #fff; padding: 0 14px; cursor: pointer; font-weight: 600; }
            .opentt-ai-send[disabled] { opacity: .7; cursor: not-allowed; }
            @media (max-width: 767px) {
                .opentt-ai-form { grid-template-columns: 1fr; }
            }
        </style>
        <script>
            (function () {
                function escText(value) {
                    return String(value == null ? '' : value);
                }

                function appendMessage(root, type, text) {
                    var list = root.querySelector('.opentt-ai-messages');
                    if (!list) {
                        return;
                    }
                    var item = document.createElement('div');
                    item.className = 'opentt-ai-msg ' + (type || 'opentt-ai-msg-bot');
                    item.textContent = escText(text);
                    list.appendChild(item);
                    list.scrollTop = list.scrollHeight;
                }

                function init(root) {
                    if (!root || root.dataset.openttAiReady === '1') {
                        return;
                    }
                    root.dataset.openttAiReady = '1';

                    var input = root.querySelector('.opentt-ai-input');
                    var send = root.querySelector('.opentt-ai-send');
                    if (!input || !send) {
                        return;
                    }

                    var ajaxUrl = String(root.getAttribute('data-ajax-url') || '');
                    var nonce = String(root.getAttribute('data-nonce') || '');

                    function setLoading(state) {
                        send.disabled = !!state;
                        send.textContent = state ? 'Sending...' : 'Send';
                    }

                    function submit() {
                        var msg = String(input.value || '').trim();
                        if (!msg) {
                            return;
                        }
                        if (!ajaxUrl || !nonce) {
                            appendMessage(root, 'opentt-ai-msg opentt-ai-msg-error', 'Chat is not configured.');
                            return;
                        }

                        appendMessage(root, 'opentt-ai-msg-user', msg);
                        input.value = '';
                        setLoading(true);

                        var body = new URLSearchParams();
                        body.set('action', 'opentt_ai_chat');
                        body.set('nonce', nonce);
                        body.set('message', msg);

                        fetch(ajaxUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                            body: body.toString()
                        })
                        .then(function (res) { return res.json(); })
                        .then(function (payload) {
                            setLoading(false);
                            if (!payload || payload.success !== true || !payload.data || !payload.data.reply) {
                                appendMessage(root, 'opentt-ai-msg-error', 'AI trenutno nije dostupan.');
                                return;
                            }
                            appendMessage(root, 'opentt-ai-msg-bot', payload.data.reply);
                        })
                        .catch(function () {
                            setLoading(false);
                            appendMessage(root, 'opentt-ai-msg-error', 'Došlo je do greške pri slanju.');
                        });
                    }

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

