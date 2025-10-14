<?php
/**
 * Plugin Name: Private Post Feedback
 * Description: Adds a feedback form below specific post types allowing visitors to send private comments to the post author and site admin.
 * Version: 1.0
 * Author: Stephan Robotta <stephan.robotta@bfh.ch>
 * Text Domain: private-post-feedback
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Private_Post_Feedback {

    private $allowed_post_types;

    /**
     * Slug used for text domain and option names
     * @var string
     */
    public const SLUG = 'private-post-feedback';

    /**
     * Name of the database table (without WP prefix)
     * @var string
     */
    public const TABLE_NAME = 'private_post_feedback';

    /**
     * Option name for storing enabled post types.
     * @var string
     */
    public const OPTION_POST_TYPES = 'private_post_feedback_post_types';

    /**
     * Init function: Set up hooks and initialization.
     */
    public function init() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_filter('the_content', [$this, 'append_feedback_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp', [$this, 'handle_form_submission']);
        add_shortcode('private_feedback_form', [$this, 'feedback_form_html']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'maybe_create_table']);
        register_activation_hook(__FILE__, [$this, 'create_table']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'settings_link']);
    }

    /**
     * Get the list of post types where the feedback form should appear.
     * @return string[] List of post type names
     */
    public function get_enabled_post_types(): array {
        if ($this->allowed_post_types === null) {
            $types = get_option(self::OPTION_POST_TYPES, '');
            $this->allowed_post_types = array_filter(array_map('trim', explode(',', $types)));
        }
        return $this->allowed_post_types;
    }

    /**
     * Get the name of the database table (with WP prefix)
     * @return string Full table name
     */
    public function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    // Add settings page
    public function add_settings_page() {
        add_options_page(
            __('Private Post Feedback Settings', self::SLUG),
            __('Private Post Feedback', self::SLUG),
            'manage_options',
            'private_post_feedback_settings',
            [$this, 'render_settings_page']
        );
    }

    // Add settings link on Plugins page
    public function settings_link($links) {
        $url = admin_url('options-general.php?page=private_post_feedback_settings');
        $settings_link = '<a href="' . esc_url($url) . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // Render settings page
    public function render_settings_page() {
        $existing_types = get_post_types(['public' => true]);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('private_post_feedback_settings_save')) {
            $new_types = \array_key_exists(self::OPTION_POST_TYPES, $_POST)
                ? \array_keys($_POST[self::OPTION_POST_TYPES]) : [];
            // Sanitize and remove any invalid post types
            $new_types = array_intersect($new_types, array_keys($existing_types));
            update_option(self::OPTION_POST_TYPES, implode(',', $new_types));
            echo '<div class="updated"><p>' . __('Settings saved') . '</p></div>';
            $this->allowed_post_types = $new_types; // Update cached value
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Private Post Feedback Settings', 'private-post-feedback-settings'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('private_post_feedback_settings_save'); ?>
                <p><?php esc_html_e('Check all post types where the Private Post Feedback form should appear:', self::SLUG); ?></p>
                <p>
                    <?php foreach ($existing_types as $key => $label) {
                        $id = 'ppf_pt_' . $key;
                        $checked = checked(in_array($key, $this->get_enabled_post_types()), true, false);
                        printf(
                            '<div><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1"%4$s /><label for="%1$s">%5$s</label></div>',
                            $id, self::OPTION_POST_TYPES, esc_attr($key), $checked, esc_html($label)
                        );
                    } ?>
                </p>
                <p><input type="submit" class="button-primary" value="<?php echo esc_attr(__('Save Changes')); ?>" /></p>
            </form>
        </div>
        <?php
    }

    /** ---------- Initialization ---------- */
    public function load_textdomain() {
        load_plugin_textdomain(self::SLUG, false, dirname(plugin_basename(__FILE__)) . DIRECTORY_SEPARATOR . 'languages');
    }

    public function enqueue_assets() {
        wp_enqueue_style('private-feedback-css', plugins_url(self::SLUG . '/private-post-feedback.css'));
        wp_enqueue_script('private-feedback-js', plugins_url(self::SLUG . '/private-post-feedback.js'), ['jquery']);
    }

    /** ---------- Display Form ---------- */
    public function append_feedback_form($content) {
        if (empty($this->get_enabled_post_types())) return $content;
        if (is_singular($this->get_enabled_post_types()) && in_the_loop() && is_main_query()) {
            $content .= $this->feedback_form_html();
        }
        return $content;
    }

    public function feedback_form_html() {
        if (isset($_POST['private_feedback_nonce']) && wp_verify_nonce($_POST['private_feedback_nonce'], 'send_private_feedback')) {
            return '<p>' . esc_html__('Thank you for your feedback!', self::SLUG) . '</p>';
        }

        ob_start(); ?>
        <a href="#private_feedback_form" class="private-feedback-toggle">
            <?php _e('Leave Feedback', self::SLUG); ?>
        </a>
        <form class="private-feedback-form" method="post">
            <label for="private_feedback_message">
                <?php _e('Your private feedback for the author:', self::SLUG); ?>
            </label><br>
            <textarea id="private_feedback_message" name="private_feedback_message" required></textarea><br>
            <?php wp_nonce_field('send_private_feedback', 'private_feedback_nonce'); ?>
            <button type="submit"><?php _e('Send Feedback', self::SLUG); ?></button>
        </form>
        <?php
        return ob_get_clean();
    }

    /** ---------- Handle Submission ---------- */
    public function handle_form_submission() {
        if (!isset($_POST['private_feedback_nonce'])) return;
        if (!wp_verify_nonce($_POST['private_feedback_nonce'], 'send_private_feedback')) return;
        if (empty($_POST['private_feedback_message'])) return;

        global $post, $wpdb;

        if (!isset($post->ID)) return;
        if (!\in_array($post->post_type, $this->get_enabled_post_types())) return;

        $message = sanitize_textarea_field($_POST['private_feedback_message']);
        $author_email = get_the_author_meta('user_email', $post->post_author);
        $admin_email  = get_option('admin_email');

        // Store feedback in DB
        $wpdb->insert($this->get_table_name(), [
            'post_id'     => $post->ID,
            'user_ip'     => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'message'     => $message,
            'created_at'  => current_time('mysql'),
        ]);

        // Send email to author and admin
        $subject = sprintf(__('New private feedback on: %s', self::SLUG), get_the_title($post->ID));
        $body = sprintf(
            __("You have received new private feedback on your post \"%s\":\n\n%s\n\nSent from: %s", self::SLUG),
            get_the_title($post->ID),
            $message,
            get_permalink($post->ID)
        );
        wp_mail([$author_email, $admin_email], $subject, $body);
    }

    /** ---------- Database Table ---------- */
    public function create_table() {
        global $wpdb;
        
        $sql = sprintf("
            CREATE TABLE %s (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                user_ip varchar(100) DEFAULT '' NOT NULL,
                message text NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id)
            ) %s;",
            $this->get_table_name(),
            $wpdb->get_charset_collate()
        );

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function maybe_create_table() {
        global $wpdb;
        if ($wpdb->get_var(sprintf("SHOW TABLES LIKE '%s'", $this->get_table_name())) != $this->get_table_name()) {
            $this->create_table();
        }
    }

    /** ---------- Admin Page ---------- */
    public function register_admin_menu() {
        add_menu_page(
            __('Private Feedback', self::SLUG),
            __('Feedback', self::SLUG),
            'manage_options',
            self::SLUG,
            [$this, 'render_admin_page'],
            'dashicons-testimonial',
            26
        );
    }

    public function render_admin_page() {
        global $wpdb;
        $results = $wpdb->get_results(sprintf("SELECT * FROM %s ORDER BY created_at DESC", $this->get_table_name()));
        ?>
        <div class="wrap">
            <h1><?php _e('Private Feedback Entries', self::SLUG); ?></h1>
            <?php if (!empty($results)) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date'); ?></th>
                            <th><?php _e('Post'); ?></th>
                            <th><?php _e('Feedback', self::SLUG); ?></th>
                            <th><?php _e('User IP', self::SLUG); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row) : 
                            $post_title = get_the_title($row->post_id);
                            $post_link  = get_permalink($row->post_id);
                        ?>
                            <tr>
                                <td><?php echo esc_html($row->created_at); ?></td>
                                <td><a href="<?php echo esc_url($post_link); ?>" target="_blank"><?php echo esc_html($post_title); ?></a></td>
                                <td><?php echo esc_html($row->message); ?></td>
                                <td><?php echo esc_html($row->user_ip); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No feedback entries found.', self::SLUG); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}

(new Private_Post_Feedback())->init();
