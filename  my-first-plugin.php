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
