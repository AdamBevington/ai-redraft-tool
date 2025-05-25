<?php
/*
Plugin Name: AI Redraft Tool
Description: A simple, powerful WordPress plugin for AI-powered content rewriting and diffing.
Version: 1.1
Author: Adam Bevington
*/

// ========== SETTINGS PAGE (for API Key) ==========

function ai_redraft_menu() {
    add_options_page(
        'AI Redraft Tool Settings',
        'AI Redraft Tool',
        'manage_options',
        'ai-redraft-settings',
        'ai_redraft_settings_page'
    );
}
add_action('admin_menu', 'ai_redraft_menu');

function ai_redraft_settings_page() {
    ?>
    <div class="wrap">
        <h1>AI Redraft Tool Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ai_redraft_settings');
            do_settings_sections('ai-redraft-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

function ai_redraft_settings_init() {
    register_setting('ai_redraft_settings', 'ai_redraft_api_key');

    add_settings_section(
        'ai_redraft_section',
        'AI Redraft Settings',
        null,
        'ai-redraft-settings'
    );

    add_settings_field(
        'ai_redraft_api_key_field',
        'OpenAI API Key',
        function() {
            $key = get_option('ai_redraft_api_key', '');
            echo '<input type="text" name="ai_redraft_api_key" value="' . esc_attr($key) . '" size="50">';
        },
        'ai-redraft-settings',
        'ai_redraft_section'
    );
}
add_action('admin_init', 'ai_redraft_settings_init');

// ========== META BOX ON POST/PAGE EDITOR ==========

function ai_redraft_add_meta_box() {
    add_meta_box(
        'ai_redraft_box_post',
        'AI Redraft Tool',
        'ai_redraft_meta_box_html',
        'post',
        'normal', // or 'advanced'
        'high'
    );
    add_meta_box(
        'ai_redraft_box_page',
        'AI Redraft Tool',
        'ai_redraft_meta_box_html',
        'page',
        'normal', // or 'advanced'
        'high'
    );
}
add_action('add_meta_boxes', 'ai_redraft_add_meta_box');

function ai_redraft_meta_box_html($post) {
    ?>
    <label for="ai_redraft_prompt">Prompt:</label>
    <input type="text" id="ai_redraft_prompt" name="ai_redraft_prompt" style="width:100%;" value="Rewrite this content" />

    <label for="ai_redraft_style" style="margin-top:8px; display:block;">Style:</label>
    <select id="ai_redraft_style" name="ai_redraft_style" style="width:100%;">
        <option value="GDS">GDS Style</option>
        <option value="SEO">SEO Optimised</option>
        <option value="Plain English">Plain English</option>
    </select>

    <button type="button" class="button button-primary" id="ai_redraft_button" style="margin-top:8px;width:100%;">Redraft Content</button>

    <div id="ai_redraft_result" style="margin-top:10px;"></div>

    <button type="button" class="button" id="ai_replace_content" style="margin-top:8px;width:100%;display:none;">Replace Post Content</button>
    <?php
}

// ========== ENQUEUE JS FOR POST/PAGE EDITOR ==========

function ai_redraft_enqueue_scripts($hook) {
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script('ai-redraft-js', plugin_dir_url(__FILE__) . 'ai-redraft-tool.js', array('jquery'), '1.1', true);
        wp_localize_script('ai-redraft-js', 'aiRedraft', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ai_redraft_nonce')
        ));
    }
}
add_action('admin_enqueue_scripts', 'ai_redraft_enqueue_scripts');

// ========== AJAX HANDLER FOR AI REDRAFT ==========

add_action('wp_ajax_ai_redraft_request', 'ai_redraft_handle_ajax');

function ai_redraft_handle_ajax() {
    check_ajax_referer('ai_redraft_nonce', 'nonce');

    $content = sanitize_text_field($_POST['content']);
    $prompt = sanitize_text_field($_POST['prompt']);
    $style = sanitize_text_field($_POST['style']);

    $api_key = get_option('ai_redraft_api_key');
    if (empty($api_key)) {
        wp_send_json_error(array('error' => 'API key not set. Add it in plugin settings.'));
    }

    // Compose OpenAI request
    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
    );
    $messages = array(
        array('role' => 'system', 'content' => "You are an expert content redrafter. Style: $style."),
        array('role' => 'user', 'content' => $prompt . "\n\n" . $content)
    );

    $data = array(
        'model'    => 'gpt-3.5-turbo',
        'messages' => $messages,
        'max_tokens' => 1000,
    );

    $response = wp_remote_post($endpoint, array(
        'headers' => $headers,
        'body'    => json_encode($data),
        'timeout' => 60,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('error' => 'API request failed.'));
    } else {
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $ai_content = $body['choices'][0]['message']['content'] ?? '';
        wp_send_json_success(array('result' => $ai_content));
    }
    wp_die();
}

