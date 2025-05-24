<?php
/*
Plugin Name: My First Plugin
Description: A simple test plugin.
Version: 1.1
Author: Your Name
*/

// Add menu item in admin
function my_first_plugin_menu() {
    add_options_page(
        'My First Plugin Settings',
        'My First Plugin',
        'manage_options',
        'my-first-plugin',
        'my_first_plugin_settings_page'
    );
}
add_action('admin_menu', 'my_first_plugin_menu');

// Create settings page
function my_first_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>My First Plugin Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('my_first_plugin_settings');
            do_settings_sections('my_first_plugin');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register setting
function my_first_plugin_settings_init() {
    register_setting('my_first_plugin_settings', 'my_first_plugin_message');

    add_settings_section(
        'my_first_plugin_section',
        'Custom Message',
        null,
        'my_first_plugin'
    );

    add_settings_field(
        'my_first_plugin_message_field',
        'Message Text',
        'my_first_plugin_message_field_callback',
        'my_first_plugin',
        'my_first_plugin_section'
    );

    register_setting('my_first_plugin_settings', 'ai_redraft_api_key');

add_settings_field(
    'ai_redraft_api_key_field',
    'OpenAI API Key',
    function() {
        $key = get_option('ai_redraft_api_key', '');
        echo '<input type="text" name="ai_redraft_api_key" value="' . esc_attr($key) . '" size="50">';
    },
    'my_first_plugin',
    'my_first_plugin_section'
);

}
add_action('admin_init', 'my_first_plugin_settings_init');

function my_first_plugin_message_field_callback() {
    $message = get_option('my_first_plugin_message', '');
    echo '<input type="text" name="my_first_plugin_message" value="' . esc_attr($message) . '" size="50">';
}

// Update content filter to use custom message
function my_first_plugin_message($content) {
    if (is_single()) {
        $message = get_option('my_first_plugin_message', 'This is my first plugin!');
        $content .= '<p style="color: red;">' . esc_html($message) . '</p>';
    }
    return $content;
}
add_filter('the_content', 'my_first_plugin_message');

// Add shortcode
function my_first_plugin_shortcode() {
    $message = get_option('my_first_plugin_message', 'This is my first plugin!');
    return '<p style="color: blue;">' . esc_html($message) . '</p>';
}
add_shortcode('my_first_message', 'my_first_plugin_shortcode');

// Add AI Redraft Tool to admin menu
function my_ai_redraft_menu() {
    add_menu_page(
        'AI Redraft Tool',
        'AI Redraft',
        'manage_options',
        'ai-redraft',
        'my_ai_redraft_page',
        'dashicons-edit',
        20
    );
}
add_action('admin_menu', 'my_ai_redraft_menu');

// Create the page content
function my_ai_redraft_page() {
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

// Enqueue script for AI Redraft Tool
function my_ai_redraft_scripts($hook) {
    if ($hook != 'toplevel_page_ai-redraft') return;

    wp_enqueue_script('diff-lib', 'https://cdn.jsdelivr.net/npm/diff@5.1.0/dist/diff.min.js', array(), '5.1.0', true);
    wp_enqueue_script('my-ai-redraft-js', plugin_dir_url(__FILE__) . 'my-first-plugin.js', array('diff-lib'), '1.0', true);

    wp_localize_script('my-ai-redraft-js', 'aiRedraft', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ai_redraft_nonce'),
    ));
}
add_action('admin_enqueue_scripts', 'my_ai_redraft_scripts');

// Handle AI Redraft AJAX request
function my_ai_redraft_request() {
    check_ajax_referer('ai_redraft_nonce');

    $post_id = intval($_POST['post_id']);
    $prompt = sanitize_text_field($_POST['prompt']);
    $style = sanitize_text_field($_POST['style']);

    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Invalid post ID');
    }

    $content = $post->post_content;

    // Build style-specific prompt
    $style_prompt = '';

    switch ($style) {
        case 'gds':
            $style_prompt = "Rewrite the provided content in plain English, following the UK Government Digital Service (GDS) Style Guide: short sentences, active voice, no jargon, no unnecessary words. Ensure accessibility and clarity.";
            break;
        case 'friendly':
            $style_prompt = "Rewrite the content in a friendly, conversational tone.";
            break;
        case 'formal':
            $style_prompt = "Rewrite the content in a formal, professional tone suitable for official communication.";
            break;
        case 'seo':
            $style_prompt = "Rewrite the content optimised for SEO, using keywords naturally.";
            break;
        case 'translate':
            $style_prompt = "Translate the content into Spanish.";
            break;
        case 'shorten':
            $style_prompt = "Shorten the content while preserving key information.";
            break;
        case 'summarise':
            $style_prompt = "Summarise the content into a concise version.";
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
        wp_send_json_error('No response from AI. API returned: ' . json_encode($body));
    }
}
add_action('wp_ajax_ai_redraft_request', 'my_ai_redraft_request');

// Handle Save Post AJAX request
function my_ai_save_post() {
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
add_action('wp_ajax_ai_save_post', 'my_ai_save_post');
