<?php
/**
 * Upgrade script for Private Post Feedback
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

function private_post_feedback_upgrade($installed_version) {
    global $wpdb;

    // Add new option for version if it doesn't exist
    if (get_option(PrivatePostFeedback::OPTION_PLUGIN_VERSION) === false) {
        add_option(PrivatePostFeedback::OPTION_PLUGIN_VERSION, PrivatePostFeedback::VERSION);
    }

    if (version_compare($installed_version, PrivatePostFeedback::VERSION, '<')) {
        // Make meta for ratings visible in admin menu when editing a post.
        $meta_keys = [PrivatePostFeedback::META_KEY_COUNT, PrivatePostFeedback::META_KEY_SUM];
        foreach ($meta_keys as $meta_key) {
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . $wpdb->postmeta . ' SET `meta_key` = %s WHERE meta_key = %s',
                $meta_key,
                '_' . $meta_key
            ));
        }
    }

    // Update the stored plugin version
    update_option(PrivatePostFeedback::OPTION_PLUGIN_VERSION, PrivatePostFeedback::VERSION);
}