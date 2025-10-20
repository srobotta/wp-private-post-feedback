<?php
/**
 * Plugin Name: Private Post Feedback
 * Plugin URI:  https://github.com/srobotta/wp-private-post-feedback
 * Description: Adds a feedback form below specific post types allowing visitors to rate and send private comments to the post author and site admin.
 * Version: 1.0
 * Author: Stephan Robotta <stephan.robotta@bfh.ch>
 * Text Domain: private-post-feedback
 * License: GPLv3
 * Keywords: feedback, private, post, rating
 * Domain Path: /languages
 */

if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly

class PrivatePostFeedback {

    /**
     * Plugin version
     * @var string
     */
    public const VERSION = '1.0';

    /**
     * Cache of allowed post types for feedback and rating
     * @var array<string, string[]>
     */
    private $allowed_post_types = [];

    /**
     * Cache of maximum rating value
     * @var int
     */
    private $rating_max;

    /**
     * Cache of current rating by post ID
     * @var array<int, array<int, int>>
     */
    private $current_rating_by_post = [];

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
     * Option name for storing the plugin version.
     * @var string
     */
    public const OPTION_PLUGIN_VERSION = 'private_post_feedback_version';

    /**
     * Option name for storing enabled post types.
     * @var string
     */
    public const OPTION_POST_TYPES_FEEDBACK = 'private_post_feedback_post_types';

    /**
     * Option name for storing enabled post types for ratings.
     * @var string
     */
    public const OPTION_POST_TYPES_RATING = 'private_post_rating_post_types';

    /**
     * Option name for storing the maximum rating value.
     * @var string
     */
    public const OPTION_MAX_RATING = 'private_post_rating_max';

    /**
     * Meta key for storing the count of ratings.
     * @var string
     */
    public const META_KEY_COUNT = 'private_post_feedback_rating_count';

    /**
     * Meta key for storing the sum of ratings.
     * @var string
     */
    public const META_KEY_SUM = 'private_post_feedback_rating_sum';

    /**
     * Init function: Set up hooks and initialization.
     */
    public function init() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_filter('the_content', [$this, 'append_feedback_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp', [$this, 'handle_form_submission']);
        add_shortcode('private_post_feedback_form', [$this, 'feedback_form_html']);
        add_shortcode('private_post_feedback_rating', [$this, 'rating_form_html']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'maybe_create_table']);
        register_activation_hook(__FILE__, [$this, 'activation_hook']);
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'settings_link']);
        add_action('wp_ajax_contact_form_submit', [$this, 'handle_rating_submission']);
        add_action('wp_ajax_nopriv_contact_form_submit', [$this, 'handle_rating_submission']);
    }

    /**
     * Get the list of post types where the feedback form should appear.
     *
     * @param string $type Option name to fetch post types from a settings option.
     * @return string[] List of post type names
     */
    public function get_enabled_post_types($type): array {
        if (!\array_key_exists($type, $this->allowed_post_types)) {
            $types = get_option($type, '');
            $this->allowed_post_types[$type] = array_filter(array_map('trim', explode(',', $types)));
        }
        return $this->allowed_post_types[$type];
    }

    /**
     * Get the maximum rating value (default is 5)
     * @return int Maximum rating value
     */
    public function get_rating_max(): int {
        if ($this->rating_max === null) {
            $max = get_option(self::OPTION_MAX_RATING, 5);
            $this->rating_max = is_numeric($max) && (int)$max > 0 ? (int)$max : 5;
        }
        return $this->rating_max;
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
            $this->save_selected_post_types(self::OPTION_POST_TYPES_FEEDBACK, $existing_types);
            $this->save_selected_post_types(self::OPTION_POST_TYPES_RATING, $existing_types);
            if (isset($_POST[self::OPTION_MAX_RATING])) {
                $max = intval($_POST[self::OPTION_MAX_RATING]);
                if ($max > 0) {
                    update_option(self::OPTION_MAX_RATING, $max);
                    $this->rating_max = $max; // Update cached value
                }
            }
            echo '<div class="updated"><p>' . __('Changes saved') . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Private Post Feedback Settings', 'private-post-feedback-settings'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('private_post_feedback_settings_save'); ?>
                <?php echo $this->get_checkboxes_html(
                    self::OPTION_POST_TYPES_FEEDBACK,
                    $existing_types,
                    __('Check all post types where the Private Post Feedback form should appear:', self::SLUG));
                ?>
                <?php echo $this->get_checkboxes_html(
                    self::OPTION_POST_TYPES_RATING,
                    $existing_types,
                    __('Check all post types where the star rating should appear:', self::SLUG));
                ?>
                <p><label for="private_feedback_max_rating"><?php esc_html_e('Number of stars for rating:', self::SLUG); ?></label>
                <input type="number" id="private_feedback_max_rating" name="<?php self::OPTION_MAX_RATING ?>" value="<?php echo esc_attr($this->get_rating_max()); ?>" min="1" /></p>
                <p><input type="submit" class="button-primary" value="<?php echo esc_attr(__('Save Changes')); ?>" /></p>
            </form>
        </div>
        <?php
    }

    /**
     * Get the label of a post type.
     * @param string $post_type The post type name.
     * @param string $context The context for the label (default is 'name').
     * @return string The label of the post type or the post type name if not found.
     */
    protected function get_post_type_label(string $post_type, string $context = 'name'): string {
        $obj = get_post_type_object($post_type);
        return $obj && isset($obj->labels->$context) ? $obj->labels->$context : $post_type;
    }

    /**
     * Get the checkboxes HTML for selecting post types.
     * @param string $type Option name to fetch post types from a settings option.
     * @param array $existing_types Array of existing post types (key => label).
     * @param string $header Header text to display above the checkboxes.
     * @return string HTML string for the checkboxes
     */
    protected function get_checkboxes_html(string $type, array $existing_types, string $header): string {
        $html = '<p>' . esc_html($header) . '</p><p>';
        foreach ($existing_types as $key) {
            $id = $type . '_' . $key;
            $checked = checked(in_array($key, $this->get_enabled_post_types($type)), true, false);
            $html .= sprintf(
                '<div><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1"%4$s /><label for="%1$s">%5$s</label></div>',
                $id, $type, esc_attr($key), $checked, esc_html($this->get_post_type_label($key))
            );
        }
        $html .= '</p>';
        return $html;
    }

    /**
     * Save selected post types to the database as a plugin setting.
     * @param string $type Option name to save post types to a settings option.
     * @param array $existing_types Array of existing post types (key => label).
     * @return void
     */
    protected function save_selected_post_types(string $type, array $existing_types) {
        $new_types = \array_key_exists($type, $_POST) ? \array_keys($_POST[$type]) : [];
        // Sanitize and remove any invalid post types
        $new_types = array_intersect($new_types, array_keys($existing_types));
        update_option($type, implode(',', $new_types));
        $this->allowed_post_types[$type] = $new_types; // Update cached value.
    }

    /** ---------- Initialization ---------- */
    public function load_textdomain() {
        load_plugin_textdomain(self::SLUG, false, dirname(plugin_basename(__FILE__)) . DIRECTORY_SEPARATOR . 'languages');
    }

    /**
     * Enqueue CSS and JS assets.
     */
    public function enqueue_assets() {
        wp_enqueue_style('private-feedback-css', plugins_url(self::SLUG . '/private-post-feedback.css'));
        wp_enqueue_script('private-feedback-js', plugins_url(self::SLUG . '/private-post-feedback.js'), ['jquery']);
        wp_localize_script('private-feedback-js', 'PrivateFeedbackRating', [
            'ajax_url' => plugins_url(self::SLUG .'/ajax-rating.php'),
            'existing_rating' => $this->get_current_rating(get_the_ID())['average'],
            'rating_saved' => __('Thank you for your rating!', self::SLUG),
        ]);
    }

    /**
     * Append the feedback and/or rating form to the post content if the post type is enabled.
     *
     * @param string $content The original post content.
     * @return string The modified post content with the feedback/rating form appended.
     */
    public function append_feedback_form($content) {
        if (in_the_loop() && is_main_query()) {
            if (!empty($this->get_enabled_post_types(self::OPTION_POST_TYPES_RATING)) &&
                is_singular($this->get_enabled_post_types(self::OPTION_POST_TYPES_RATING))
            ) {
                $content .= $this->rating_form_html();
            }
            if (!empty($this->get_enabled_post_types(self::OPTION_POST_TYPES_FEEDBACK)) &&
                is_singular($this->get_enabled_post_types(self::OPTION_POST_TYPES_FEEDBACK))
            ) {
                $content .= $this->feedback_form_html();
            }
        }
        return $content;
    }

    /**
     * Generate the HTML for the feedback form.
     * @return string HTML string for the feedback form
     */
    public function feedback_form_html(): string {
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

    /**
     * Generate the HTML for the rating form.
     * @return string HTML string for the rating form
     */
    public function rating_form_html(): string {
        ob_start(); ?>
            <span class="private-feedback-stars" id="private_feedback_stars">
                <form class="private-feedback-rating-form" method="post">
                    <input type="hidden" name="private_feedback_rate" id="private_feedback_rate" value="0" />
                    <input type="hidden" name="post_id" value="<?php echo get_the_ID(); ?>" />
                    <?php for ($i = 1; $i <= $this->get_rating_max(); $i++): ?>
                        <span class="private-feedback-star" data-value="<?php echo $i; ?>">★</span>
                    <?php endfor; ?>
                    <?php wp_nonce_field('send_private_feedback_rating', 'private_feedback_rating_nonce'); ?>
                </form>
            </span>
            <span class="private-feedback-rating-text">
                <?php
                $rating = $this->get_current_rating(get_the_ID());
                if ($rating['count'] > 0) {
                    printf(
                        /* translators: 1: average rating, 2: number of ratings */
                        __('Average rating: %.2f (%d ratings)', self::SLUG),
                        $rating['average'],
                        $rating['count']
                    );
                } else {
                    _e('No ratings yet', self::SLUG);
                }
                ?>
            </span>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle form submission when a visitor submits feedback.
     */
    public function handle_form_submission() {
        if (!isset($_POST['private_feedback_nonce'])) return;
        if (!wp_verify_nonce($_POST['private_feedback_nonce'], 'send_private_feedback')) return;
        if (empty($_POST['private_feedback_message'])) return;

        global $post, $wpdb;

        if (!isset($post->ID)) return;
        if (!\in_array($post->post_type, $this->get_enabled_post_types(self::OPTION_POST_TYPES_FEEDBACK))) return;

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

    /**
     * Handle AJAX rating submission.
     * @return bool True if the rating was successfully processed, false otherwise.
     */
    public function handle_rating_submission(): bool {
        // Similar to handle_form_submission, implement rating storage and notification here
        if (!isset($_POST['private_feedback_rating_nonce']) ||
            !wp_verify_nonce($_POST['private_feedback_rating_nonce'], 'send_private_feedback_rating') ||
            empty($_POST['post_id']) ||
            empty($_POST['private_feedback_rate']) ||
            !is_numeric($_POST['private_feedback_rate']) ||
            $_POST['private_feedback_rate'] < 1 ||
            $_POST['private_feedback_rate'] > $this->get_rating_max()
        ) {
            return false;
        }

        $post = get_post(intval($_POST['post_id']));

        if (empty($post) ||
            !\in_array($post->post_type, $this->get_enabled_post_types(self::OPTION_POST_TYPES_RATING))
        ) {
            return false;
        }
        $rating = intval($_POST['private_feedback_rate']);
        list($count, $sum) = $this->store_rating($post->ID, $rating);
        $this->current_rating_by_post[$post->ID] = ['count' => $count, 'average' => round($sum / $count, 2)];
        return true;
    }

    /**
     * Get the current rating for a post.
     * @param int $post_id The ID of the post.
     * @return array An array containing the rating count and average.
     */
    public function get_current_rating(int $post_id): array {
        if (!\array_key_exists($post_id, $this->current_rating_by_post)) {
            $count = (int)get_post_meta($post_id, self::META_KEY_COUNT, true);
            $sum = (int)get_post_meta($post_id, self::META_KEY_SUM, true);
            $average = $count > 0 ? round($sum / $count, 2) : 0;
            $this->current_rating_by_post[$post_id] = ['count' => $count, 'average' => $average];
        }
        return $this->current_rating_by_post[$post_id];
    }

    /**
     * Store a new rating for a post.
     * @param int $post_id The ID of the post.
     * @param int $rating The rating value to store.
     * @return array An array containing the updated rating count and sum.
     */
    protected function store_rating(int $post_id, int $rating) {

        $count = (int)get_post_meta($post_id, self::META_KEY_COUNT, true);
        $sum = (int)get_post_meta($post_id, self::META_KEY_SUM, true);

        $count++;
        $sum += $rating;

        update_post_meta($post_id, self::META_KEY_COUNT, $count);
        update_post_meta($post_id, self::META_KEY_SUM, $sum);

        return [$count, $sum];
    }

    /*
     * Activation hook for the plugin.
     * Creates the database table if it doesn't exist and sets the plugin version option.
     */
    public function activation_hook() {
        $this->maybe_create_table();
        add_option(self::OPTION_PLUGIN_VERSION, self::VERSION);
    }

    /**
     * Create the database table for storing feedback.
     */
    protected function create_table() {
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

    /**
     * Check if the database table exists and create it if not.
     */
    public function maybe_create_table() {
        global $wpdb;
        if ($wpdb->get_var(sprintf("SHOW TABLES LIKE '%s'", $this->get_table_name())) != $this->get_table_name()) {
            $this->create_table();
        }
        $installed = get_option(self::OPTION_PLUGIN_VERSION);
        if (version_compare($installed, self::VERSION, '<')) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'upgrade.php';
            private_post_feedback_upgrade($installed);
        }
    }

    /** ---------- Admin Page to list feedback ---------- */

    /**
     * Register the admin menu page for viewing feedback entries.
     */
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

    /**
     * Render the admin page displaying feedback entries.
     */
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

(new PrivatePostFeedback())->init();
