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
            add_settings_error(self::OPTION_API_KEY, 'opentt_ai_key_ok', 'Groq API ključ je uspešno validiran.', 'updated');
            return $value;
        }

        $message = trim((string) ($check['message'] ?? 'Neuspešna validacija API ključa.'));
        add_settings_error(self::OPTION_API_KEY, 'opentt_ai_key_fail', 'Groq API ključ nije validan ili API nije dostupan: ' . $message);
        return (string) get_option(self::OPTION_API_KEY, '');
    }

    public static function render_settings_page()
    {
        $capability = defined('OpenTT_Unified_Core::CAP') ? OpenTT_Unified_Core::CAP : 'manage_options';
        if (!current_user_can($capability)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'opentt'));
        }

        echo '<div class="wrap">';
        echo '<h1>OpenTT AI Podešavanja</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::SETTINGS_GROUP);
        settings_errors(self::OPTION_API_KEY);

        $api_key = (string) get_option(self::OPTION_API_KEY, '');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr>';
        echo '<th scope="row"><label for="opentt-ai-groq-api-key">Groq API Key</label></th>';
        echo '<td>';
        echo '<input id="opentt-ai-groq-api-key" name="' . esc_attr(self::OPTION_API_KEY) . '" type="text" class="regular-text" value="' . esc_attr($api_key) . '" autocomplete="off" />';
        echo '<p class="description">Ključ se čuva kroz WordPress Options API i nikada se ne izlaže na frontendu.</p>';
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
        <div id="<?php echo esc_attr($uid); ?>" class="opentt-ai" data-opentt-ai="1" data-ajax-url="<?php echo esc_url($ajax_url); ?>" data-nonce="<?php echo esc_attr($nonce); ?>">
            <button type="button" class="opentt-ai-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr($uid . '-panel'); ?>" aria-label="Otvori AI asistenta">
                <img class="opentt-ai-toggle-icon" src="<?php echo esc_url($icon_url); ?>" alt="" aria-hidden="true">
            </button>
            <div class="opentt-ai-backdrop" hidden></div>
            <div id="<?php echo esc_attr($uid . '-panel'); ?>" class="opentt-ai-panel" hidden>
                <button type="button" class="opentt-ai-close" aria-label="Zatvori AI asistenta">&times;</button>
                <label class="opentt-ai-label" for="<?php echo esc_attr($uid . '-input'); ?>">AI Asistent</label>
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

        $api_key = trim((string) get_option(self::OPTION_API_KEY, ''));
        if ($api_key === '') {
            wp_send_json_error(['message' => 'Groq API ključ nije podešen u administraciji.'], 500);
        }
        $json = self::call_groq_chat_completion($api_key, $message, 20);
        if (!is_array($json)) {
            wp_send_json_error(['message' => 'AI servis trenutno nije dostupan.'], 502);
        }
        if (!empty($json['error']) && is_array($json['error'])) {
            $err = trim((string) ($json['error']['message'] ?? 'Greška AI servisa.'));
            wp_send_json_error(['message' => $err], 502);
        }

        $assistant = '';
        if (isset($json['choices'][0]['message']['content'])) {
            $assistant = trim((string) $json['choices'][0]['message']['content']);
        }
        if ($assistant === '') {
            wp_send_json_error(['message' => 'AI odgovor je prazan.'], 502);
        }

        wp_send_json_success([
            'reply' => $assistant,
        ]);
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

    private static function call_groq_chat_completion($api_key, $message, $timeout = 20)
    {
        $response = wp_remote_post(
            'https://api.groq.com/openai/v1/chat/completions',
            [
                'timeout' => max(5, intval($timeout)),
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
                            'content' => (string) $message,
                        ],
                    ],
                ]),
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
            .opentt-ai-toggle { width: 40px; height: 40px; border-radius: 10px; border: 1px solid rgba(255,255,255,.18); background: rgba(3,23,69,.92); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: transform .18s ease, border-color .18s ease, background .18s ease; }
            .opentt-ai-toggle:hover { transform: translateY(-1px); border-color: rgba(255,255,255,.34); background: rgba(8,30,82,.95); }
            .opentt-ai-toggle-icon { width: 18px; height: 18px; display: block; filter: brightness(0) invert(1); }
            .opentt-ai-backdrop { position: fixed; inset: 0; background: radial-gradient(circle at 30% 20%, rgba(61,124,255,.22), rgba(2,10,28,.72) 55%, rgba(1,5,16,.84) 100%); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 78; }
            .opentt-ai-panel { position: fixed; inset: 0; width: 100vw; height: 100dvh; background: linear-gradient(140deg, rgba(12,39,102,.48) 0%, rgba(5,20,58,.85) 32%, rgba(3,13,40,.96) 100%); border: 0; border-radius: 0; box-shadow: 0 22px 48px rgba(0,0,0,.48), 0 0 0 1px rgba(45,119,245,.18) inset; padding: 72px 24px 24px; z-index: 79; box-sizing: border-box; overflow-y: auto; overscroll-behavior: contain; }
            .opentt-ai-close { display: inline-block; border: 0; background: transparent; color: #fff; font-size: 32px; line-height: 1; cursor: pointer; position: absolute; top: 12px; right: 16px; padding: 4px 10px; z-index: 2; }
            .opentt-ai-label { display: block; font-size: 28px; font-weight: 700; line-height: 1.2; color: rgba(255,255,255,.92); margin-bottom: 12px; letter-spacing: .06em; text-transform: uppercase; }
            .opentt-ai-messages { min-height: 220px; max-height: 52dvh; overflow-y: auto; border: 1px solid rgba(255,255,255,.08); background: #04102b; border-radius: 10px; padding: 10px; margin-bottom: 10px; }
            .opentt-ai-msg { margin: 0 0 10px 0; padding: 8px 10px; border-radius: 8px; line-height: 1.45; font-size: 14px; }
            .opentt-ai-msg-user { background: rgba(61,124,255,.18); border: 1px solid rgba(61,124,255,.35); }
            .opentt-ai-msg-bot { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); }
            .opentt-ai-msg-error { background: rgba(255,75,75,.16); border: 1px solid rgba(255,75,75,.4); }
            .opentt-ai-form { display: grid; grid-template-columns: 1fr auto; gap: 8px; }
            .opentt-ai-input { min-height: 42px; border-radius: 8px; border: 1px solid rgba(255,255,255,.18); background: #0a1d4a; color: #fff; padding: 0 12px; }
            .opentt-ai-send { min-height: 42px; border-radius: 8px; border: 1px solid rgba(61,124,255,.55); background: #2c63d6; color: #fff; padding: 0 14px; cursor: pointer; font-weight: 600; }
            .opentt-ai-send[disabled] { opacity: .7; cursor: not-allowed; }
            body.opentt-ai-open, html.opentt-ai-open { overflow: hidden; height: 100%; overscroll-behavior: none; }
            @media (max-width: 767px) {
                .opentt-ai-form { grid-template-columns: 1fr; }
                .opentt-ai-label { font-size: 24px; }
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

                    function submit() {
                        var msg = String(input.value || '').trim();
                        if (!msg) {
                            appendMessage(root, 'opentt-ai-msg-error', 'Unesi poruku.');
                            return;
                        }
                        if (!ajaxUrl || !nonce) {
                            appendMessage(root, 'opentt-ai-msg-error', 'AI chat nije pravilno podešen.');
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
                            if (!payload || payload.success !== true) {
                                var msg = payload && payload.data && payload.data.message ? payload.data.message : 'AI trenutno nije dostupan.';
                                appendMessage(root, 'opentt-ai-msg-error', msg);
                                return;
                            }
                            if (!payload.data || !payload.data.reply) {
                                appendMessage(root, 'opentt-ai-msg-error', 'AI nije poslao odgovor.');
                                return;
                            }
                            appendMessage(root, 'opentt-ai-msg-bot', payload.data.reply);
                        })
                        .catch(function () {
                            setLoading(false);
                            appendMessage(root, 'opentt-ai-msg-error', 'Došlo je do greške pri slanju.');
                        });
                    }

                    toggle.addEventListener('click', function () {
                        if (panel.hidden) {
                            openPanel();
                        } else {
                            closePanel();
                        }
                    });
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
