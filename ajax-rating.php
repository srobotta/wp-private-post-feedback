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
// Check whether the "f" parameter is set in the url as GET variable.
if (!isset($_GET['f'])) {
    wp_send_json_error();
    wp_die();
}
// Load WordPress
require_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'wp-load.php';
// Load the plugin
require_once plugin_dir_path(__FILE__) . 'private-post-feedback.php';
$plugin = new PrivatePostFeedback();
// Depending on the "f" parameter, handle rating or feedback submission.
if ($_GET['f'] === 'rate') {
    if ($plugin->handle_rating_submission()) {
        wp_remote_request(get_permalink($_POST['post_id']), [
            'method' => 'PURGE',
        ]);
        $res = $plugin->get_current_rating((int)$_POST['post_id']);
        $res['message'] = __('Thank you for your rating!', PrivatePostFeedback::SLUG);
        wp_send_json_success($res);
        wp_die();
    }
}
if ($_GET['f'] === 'feedback') {
    if ($plugin->handle_feedback_submission()) {
        wp_send_json_success([
            'message' => __('Thank you for your feedback!', PrivatePostFeedback::SLUG),
        ]);
        wp_die();
    }
}
wp_send_json_error();
wp_die();