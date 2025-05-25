<?php
/*
Plugin Name: AI Redraft Tool
Description: A simple, powerful WordPress plugin for AI-powered content rewriting and diffing.
Version: 1.0
Author: Adam Bevington
*/

// Add settings page to WordPress admin
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

// Settings page content
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

// Register plugin settings
function ai_redraft_settings_init() {
    register_setting('ai_redraft_settings', 'ai_redraft_message');
    register_setting('ai_redraft_settings', 'ai_redraft_api_key');

    add_settings_section(
        'ai_redraft_section',
        'AI Redraft Settings',
        null,
        'ai-redraft-settings'
    );

    add_settings_field(
        'ai_redraft_message_field',
        'Custom Message',
        function() {
            $message = get_option('ai_redraft_message', '');
            echo '<input type="text" name="ai_redraft_message" value="' . esc_attr($message) . '" size="50">';
        },
        'ai-redraft-settings',
        'ai_redraft_section'
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

// Add AI Redraft Tool (post redraft form) to admin menu
function ai_redraft_tool_menu() {
    add_menu_page(
        'AI Redraft Tool',
        'AI Redraft',
        'manage_options',
        'ai-redraft-tool',
        'ai_redraft_tool_page',
        'dashicons-edit',
        20
    );
}
add_action('admin_menu', 'ai_redraft_tool_menu');

// Page content for the AI Redraft Tool (post redraft form)
function ai_redraft_tool_page() {
    ?>
    <div class="wrap">
        <h1>AI Redraft Tool</h1>
        <form id="ai-redraft-form">
            <label for="post_id">Post ID:</label>
            <input type="number" id="post_id" name="post_id" required>

            <label for="prompt">Redraft Prompt (optional):</label>
            <input type="text" id="prompt" name="prompt" placeholder="e.g., make it more friendly">

            <label for="style">Choose Rewrite Style:</label>
            <select id="style" name="style">
                <option value="gds">GDS Style (UK Government)</option>
                <option value="friendly">Friendly Tone</option>
                <option value="formal">Formal/Professional</option>
                <option value="seo">SEO Optimised</option>
                <option value="translate">Translate to Spanish</option>
                <option value="shorten">Shorten Content</option>
                <option value="summarise">Summarise Content</option>
            </select>

            <button type="submit" class="button button-primary">Send to ChatGPT</button>
        </form>

        <div id="ai-redraft-result" style="margin-top: 20px;"></div>
        <button id="ai-save-post" class="button button-secondary" style="display: none; margin-top: 10px;">Save to Post</button>
    </div>
    <?php
}

// Enqueue scripts for the AI Redraft Tool page only
function ai_redraft_scripts($hook) {
    if ($hook !== 'toplevel_page_ai-redraft-tool') return;

    wp_enqueue_script('diff-lib', 'https://cdnjs.cloudflare.com/ajax/libs/diff/5.1.0/diff.min.js', array(), '5.1.0', true);
    wp_enqueue_script('ai-redraft-tool-js', plugin_dir_url(__FILE__) . 'ai-redraft-tool.js', array('diff-lib'), '1.0', true);

    wp_localize_script('ai-redraft-tool-js', 'aiRedraft', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ai_redraft_nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'ai_redraft_scripts');

// Handle AI Redraft AJAX request
function ai_redraft_request() {
    check_ajax_referer('ai_redraft_nonce');

    $post_id = intval($_POST['post_id']);
    $prompt = sanitize_text_field($_POST['prompt']);
    $style = sanitize_text_field($_POST['style']);

    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Invalid post ID');
    }

    $content = $post->post_content;

    $style_prompt = '';
    switch ($style) {
        case 'gds':
            $style_prompt = "Rewrite the content in plain English, following the UK Government Digital Service (GDS) Style Guide: short sentences, active voice, no jargon, no unnecessary words. Ensure accessibility and clarity.";
            break;
        case 'friendly':
            $style_prompt = "Rewrite the content in a friendly, conversational tone.";
            break;
        case 'formal':
            $style_prompt = "Rewrite the content in a formal, professional tone.";
            break;
        case 'seo':
            $style_prompt = "Optimise the content for SEO with natural keyword usage.";
            break;
        case 'translate':
            $style_prompt = "Translate the content into Spanish.";
            break;
        case 'shorten':
            $style_prompt = "Shorten the content while preserving key points.";
            break;
        case 'summarise':
            $style_prompt = "Summarise the content concisely.";
            break;
        default:
            $style_prompt = "Rewrite the content clearly.";
    }

    $full_prompt = $style_prompt . "\n\nAdditional instructions: " . $prompt . "\n\nContent:\n" . $content;

    $api_key = get_option('ai_redraft_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error('API key not set. Please add it in the plugin settings.');
    }

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ),
        'body' => json_encode(array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'system', 'content' => 'You are a helpful assistant that rewrites content.'),
                array('role' => 'user', 'content' => $full_prompt),
            ),
            'max_tokens' => 500,
        )),
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error('Request failed: ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['choices'][0]['message']['content'])) {
        $ai_output = trim($body['choices'][0]['message']['content']);
        wp_send_json_success(array(
            'ai' => $ai_output,
            'original' => $content
        ));
    } else {
        wp_send_json_error('No response from AI.');
    }
}
add_action('wp_ajax_ai_redraft_request', 'ai_redraft_request');

// Save post AJAX
function ai_redraft_save_post() {
    check_ajax_referer('ai_redraft_nonce');

    $post_id = intval($_POST['post_id']);
    $redraft = wp_kses_post($_POST['redraft']);

    $post = array(
        'ID' => $post_id,
        'post_content' => $redraft,
    );

    $result = wp_update_post($post, true);

    if (is_wp_error($result)) {
        wp_send_json_error('Failed to update post: ' . $result->get_error_message());
    } else {
        wp_send_json_success('Post updated!');
    }
}
add_action('wp_ajax_ai_save_post', 'ai_redraft_save_post');
