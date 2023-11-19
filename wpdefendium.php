<?php
/**
 * Plugin Name: WPDefendium Spam Checker
 * Description: Checks comments for spam using the Defendium API.
 * Version: 1.0
 * Author: Ryan Kopf
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

add_action('admin_menu', 'wpdefendium_menu');

function wpdefendium_menu() {
    add_options_page('WPDefendium Spam Checker Options', 'WPDefendium Spam Checker', 'manage_options', 'wpdefendium', 'wpdefendium_options');
}

function wpdefendium_options() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Save settings logic
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['wpdefendium_api_key'])) {
        check_admin_referer('wpdefendium_update_options');
        $api_key = sanitize_text_field($_POST['wpdefendium_api_key']);
        update_option('wpdefendium_api_key', $api_key);
        echo '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
    }

    // Retrieve existing option value from the database
    $api_key = get_option('wpdefendium_api_key');

    // Settings form
    ?>
    <div class="wrap">
        <h2>WPDefendium Spam Checker</h2>
        <form name="form1" method="post" action="">
            <?php wp_nonce_field('wpdefendium_update_options'); ?>
            <p><strong>API Key:</strong></p>
            <p><input type="text" name="wpdefendium_api_key" value="<?php echo esc_attr($api_key); ?>" size="20"></p>
            <hr />
            <p class="submit">
                <input type="submit" name="Submit" class="button-primary" value="Save Changes" />
            </p>
        </form>
    </div>
    <?php
}

add_filter('preprocess_comment', 'wpdefendium_check_comment_for_spam', 10, 1);

function wpdefendium_check_comment_for_spam($commentdata) {
    $api_key = get_option('wpdefendium_api_key');
    if (empty($api_key)) {
        return $commentdata;
    }

    $content = $commentdata['comment_content'];
    $body = array(
        'secret_key' => $api_key,
        'content' => $content
    );

    $response = wp_remote_post("https://api.defendium.com/check", array(
        'body' => $body
    ));

    if (is_wp_error($response)) {
        // Optionally, log error or notify admin
        return $commentdata;
    }

    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body);

    if ($result && isset($result->result) && $result->result) {
        // Mark the comment as spam
        add_action('wp_insert_comment', 'wpdefendium_mark_comment_as_spam', 10, 2);
    }

    return $commentdata;
}

function wpdefendium_mark_comment_as_spam($comment_ID, $comment) {
    wp_spam_comment($comment_ID);
}