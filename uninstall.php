<?php
/**
 * Perform plugin installation routines.
 *
 * @package Private Post Feedback
 */

global $wpdb;

// Make sure the uninstall file can't be accessed directly.
if (! defined( 'WP_UNINSTALL_PLUGIN' )) {
	die;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'private-post-feedback.php';

// Remove options introduced by the plugin.
delete_option(PrivatePostFeedback::OPTION_POST_TYPES_FEEDBACK);
delete_option(PrivatePostFeedback::OPTION_POST_TYPES_RATING);
delete_option(PrivatePostFeedback::OPTION_MAX_RATING);
delete_option(PrivatePostFeedback::OPTION_NOTIFY_ADMIN);
delete_option(PrivatePostFeedback::OPTION_NOTIFY_AUTHOR);

// Remove post meta added by the plugin.
$meta_keys = [PrivatePostFeedback::META_KEY_COUNT, PrivatePostFeedback::META_KEY_SUM];
$placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders})",
    ...$meta_keys
));

// Remove table where the comments are stored.
$table_name = (new PrivatePostFeedback())->get_table_name();
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Remove this directory.
function private_post_feedback_rrmdir($dir) {
   if (is_dir($dir)) {
       $objects = scandir($dir);
       foreach ($objects as $object) {
           if ($object != "." && $object != "..") {
              if (is_dir($dir. DIRECTORY_SEPARATOR . $object) && !is_link($dir. DIRECTORY_SEPARATOR .$object)) {
                  private_post_feedback_rrmdir($dir. DIRECTORY_SEPARATOR . $object);
              } else {
                  wp_delete_file($dir. DIRECTORY_SEPARATOR . $object);
              }
           }
       }
       rmdir($dir);
   }
}

private_post_feedback_rrmdir(__DIR__);