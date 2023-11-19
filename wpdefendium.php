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
          <form method="post" action="">
              <?php wp_nonce_field('wpdefendium_check_spam'); ?>
              <input type="hidden" name="action" value="check_spam">
              <input type="submit" class="button-secondary" value="Check Pending Comments for Spam">
          </form>
    </div>
    <?php
}

add_filter('preprocess_comment', 'wpdefendium_check_comment_for_spam', 10, 1);

function wpdefendium_is_comment_spam($commentdata) {
    $api_key = get_option('wpdefendium_api_key');
    if (empty($api_key)) {
        return false;
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
        return false;
    }

    $response_body = wp_remote_retrieve_body($response);
    $result = json_decode($response_body);

    return ($result && isset($result->result) && $result->result);
}

function wpdefendium_check_comment_for_spam($commentdata) {
    $is_spam = wpdefendium_is_comment_spam($commentdata);
    if ($is_spam) {
        add_action('wp_insert_comment', 'wpdefendium_mark_comment_as_spam', 10, 2);
    }
    return $commentdata;
}

function wpdefendium_mark_comment_as_spam($comment_ID, $comment) {
    wp_spam_comment($comment_ID);
}
// ADD SETTINGS LINK TO PLUGIN PAGE
function wpdefendium_add_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=wpdefendium">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'wpdefendium_add_settings_link');

// FUNCTION FOR THE CHECK PENDING COMMENTS FOR SPAM BUTTON
add_action('admin_init', 'wpdefendium_process_check_spam');

function wpdefendium_process_check_spam() {
    if (isset($_POST['action']) && $_POST['action'] == 'check_spam' && check_admin_referer('wpdefendium_check_spam')) {
        $pending_comments = get_comments(array('status' => 'hold'));
        foreach ($pending_comments as $comment) {
            $commentdata = array(
                'comment_ID' => $comment->comment_ID,
                'comment_content' => $comment->comment_content
            );
            // Adapted spam check call
            $is_spam = wpdefendium_is_comment_spam($commentdata);
            if ($is_spam) {
                wp_spam_comment($comment->comment_ID);
            }
        }
        // Redirect to avoid resubmission
        wp_redirect(add_query_arg(array('page' => 'wpdefendium', 'spam_checked' => '1'), admin_url('options-general.php')));
        exit;
    }
}
