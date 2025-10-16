<?php
/**
 * Handle AJAX rating submission.
 *
 * @package Private Post Feedback
 */

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 404 Not Found');
    exit();
}
// Load WordPress
require_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'wp-load.php';
// Load the plugin
require_once plugin_dir_path(__FILE__) . 'private-post-feedback.php';
// Handle AJAX rating submission
$plugin = new PrivatePostFeedback();
if ($plugin->handle_rating_submission()) {
    wp_remote_request(get_permalink($_POST['post_id']), [
        'method' => 'PURGE',
    ]);
    wp_send_json_success($plugin->get_current_rating((int)$_POST['post_id']));
} else {
    wp_send_json_error();
}