<?php
/**
 * Plugin Name: Nekuda MCT Cookie Notice
 * Plugin URI: https://nekuda.dev
 * Description: A simple, elegant cookie consent banner with customizable text via options.
 * Version: 1.0.0
 * Author: Nekuda
 * Author URI: https://nekuda.dev
 * Text Domain: nekuda-mct-cookie-notice
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define version constant from plugin header (single source of truth)
if (!function_exists('get_plugin_data')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
define('NEKUDA_MCT_COOKIE_VERSION', get_plugin_data(__FILE__)['Version']);

class Nekuda_MCT_Cookie_Notice {

    const VERSION = NEKUDA_MCT_COOKIE_VERSION;
    const COOKIE_NAME = 'nekuda_mct_cookie_consent';
    const OPTION_PREFIX = 'nekuda_mct_cookie_';

    public function __construct() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_footer', [$this, 'render_banner']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Load plugin textdomain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'nekuda-mct-cookie-notice',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Get default options
     */
    private function get_defaults() {
        return [
            'message' => 'לידיעתך, אנחנו משתמשים בעוגיות באתר זה.',
            'link_text' => 'לפרטים נוספים',
            'link_url' => '/privacy-policy',
            'button_text' => 'סגור',
            // Colors
            'color_bg' => '#1a1a1a',
            'color_text' => '#f2f2f2',
            'color_link' => '#f2f2f2',
            'color_btn_bg' => 'transparent',
            'color_btn_text' => '#f2f2f2',
            'color_btn_border' => '#f2f2f2',
        ];
    }

    /**
     * Get option value with default fallback
     */
    private function get_option($key) {
        $defaults = $this->get_defaults();
        $value = get_option(self::OPTION_PREFIX . $key, '');
        return $value !== '' ? $value : ($defaults[$key] ?? '');
    }

    /**
     * Enqueue admin assets (color picker)
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_nekuda-mct-cookie-notice') {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker', "
            jQuery(document).ready(function($) {
                $('.nekuda-color-picker').wpColorPicker();
            });
        ");
    }

    /**
     * Enqueue CSS and JS
     */
    public function enqueue_assets() {
        if ($this->has_consent()) {
            return;
        }

        $plugin_url = plugin_dir_url(__FILE__);

        wp_enqueue_style(
            'nekuda-mct-cookie-notice',
            $plugin_url . 'assets/css/cookie-notice.css',
            [],
            self::VERSION
        );

        // Add inline styles for custom colors
        $custom_css = $this->get_custom_css();
        wp_add_inline_style('nekuda-mct-cookie-notice', $custom_css);

        wp_enqueue_script(
            'nekuda-mct-cookie-notice',
            $plugin_url . 'assets/js/cookie-notice.js',
            [],
            self::VERSION,
            true
        );

        wp_localize_script('nekuda-mct-cookie-notice', 'nekudaMctCookie', [
            'cookieName' => self::COOKIE_NAME,
            'cookieExpiry' => 365,
        ]);
    }

    /**
     * Generate custom CSS from options
     */
    private function get_custom_css() {
        $bg = esc_attr($this->get_option('color_bg'));
        $text = esc_attr($this->get_option('color_text'));
        $link = esc_attr($this->get_option('color_link'));
        $btn_bg = esc_attr($this->get_option('color_btn_bg'));
        $btn_text = esc_attr($this->get_option('color_btn_text'));
        $btn_border = esc_attr($this->get_option('color_btn_border'));

        return ":root {
            --nekuda-cookie-bg: {$bg};
            --nekuda-cookie-text: {$text};
            --nekuda-cookie-link: {$link};
            --nekuda-cookie-btn-bg: {$btn_bg};
            --nekuda-cookie-btn-text: {$btn_text};
            --nekuda-cookie-btn-border: {$btn_border};
        }";
    }

    /**
     * Check if user has already consented
     */
    private function has_consent() {
        return isset($_COOKIE[self::COOKIE_NAME]) && $_COOKIE[self::COOKIE_NAME] === 'accepted';
    }

    /**
     * Render the cookie banner
     */
    public function render_banner() {
        if ($this->has_consent()) {
            return;
        }

        $message = esc_html($this->get_option('message'));
        $link_text = esc_html($this->get_option('link_text'));
        $link_url = esc_url($this->get_option('link_url'));
        $button_text = esc_html($this->get_option('button_text'));
        ?>
<div id="nekuda-mct-cookie-banner" class="nekuda-mct-cookie-banner" role="dialog"
    aria-label="<?php esc_attr_e('Cookie Notice', 'nekuda-mct-cookie-notice'); ?>">
    <div class="nekuda-mct-cookie-banner__content">
        <p class="nekuda-mct-cookie-banner__message">
            <?php echo $message; ?>
            <?php if ($link_url && $link_text): ?>
            <a href="<?php echo $link_url; ?>" class="nekuda-mct-cookie-banner__link"><?php echo $link_text; ?></a>
            <?php endif; ?>
        </p>
        <button type="button" class="nekuda-mct-cookie-banner__close" id="nekuda-mct-cookie-close">
            <?php echo $button_text; ?>
        </button>
    </div>
</div>
<?php
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Cookie Notice Settings', 'nekuda-mct-cookie-notice'),
            __('Cookie Notice', 'nekuda-mct-cookie-notice'),
            'manage_options',
            'nekuda-mct-cookie-notice',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Sanitize color value
     * Allows hex colors, rgb/rgba, and 'transparent'
     */
    public function sanitize_color($value) {
        $value = trim($value);

        // Allow empty
        if ($value === '') {
            return '';
        }

        // Allow 'transparent'
        if (strtolower($value) === 'transparent') {
            return 'transparent';
        }

        // Allow hex colors
        if (preg_match('/^#([A-Fa-f0-9]{3}){1,2}$/', $value)) {
            return $value;
        }

        // Allow rgb/rgba
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+))?\s*\)$/', $value)) {
            return $value;
        }

        // Invalid color - return empty
        return '';
    }

    /**
     * Register settings
     */
    public function register_settings() {
        $text_fields = ['message', 'link_text', 'link_url', 'button_text'];
        $color_fields = ['color_bg', 'color_text', 'color_link', 'color_btn_bg', 'color_btn_text', 'color_btn_border'];

        foreach ($text_fields as $field) {
            register_setting('nekuda_mct_cookie_settings', self::OPTION_PREFIX . $field, [
                'sanitize_callback' => $field === 'link_url' ? 'esc_url_raw' : 'sanitize_text_field',
            ]);
        }

        foreach ($color_fields as $field) {
            register_setting('nekuda_mct_cookie_settings', self::OPTION_PREFIX . $field, [
                'sanitize_callback' => [$this, 'sanitize_color'],
            ]);
        }
    }

    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $defaults = $this->get_defaults();
        ?>
<div class="wrap nekuda-mct-cookie-notice-wrap" style="direction: ltr; text-align: start;">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form action="options.php" method="post">
        <?php settings_fields('nekuda_mct_cookie_settings'); ?>

        <h2><?php _e('Text Settings', 'nekuda-mct-cookie-notice'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>message"><?php _e('Message', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>message"
                        name="<?php echo self::OPTION_PREFIX; ?>message"
                        value="<?php echo esc_attr($this->get_option('message')); ?>" class="large-text"
                        placeholder="<?php echo esc_attr($defaults['message']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>link_text"><?php _e('Link Text', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>link_text"
                        name="<?php echo self::OPTION_PREFIX; ?>link_text"
                        value="<?php echo esc_attr($this->get_option('link_text')); ?>" class="regular-text"
                        placeholder="<?php echo esc_attr($defaults['link_text']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>link_url"><?php _e('Link URL', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>link_url"
                        name="<?php echo self::OPTION_PREFIX; ?>link_url"
                        value="<?php echo esc_attr($this->get_option('link_url')); ?>" class="regular-text"
                        placeholder="<?php echo esc_attr($defaults['link_url']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>button_text"><?php _e('Button Text', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>button_text"
                        name="<?php echo self::OPTION_PREFIX; ?>button_text"
                        value="<?php echo esc_attr($this->get_option('button_text')); ?>" class="regular-text"
                        placeholder="<?php echo esc_attr($defaults['button_text']); ?>">
                </td>
            </tr>
        </table>

        <h2><?php _e('Color Settings', 'nekuda-mct-cookie-notice'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>color_bg"><?php _e('Background Color', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>color_bg"
                        name="<?php echo self::OPTION_PREFIX; ?>color_bg"
                        value="<?php echo esc_attr($this->get_option('color_bg')); ?>"
                        class="nekuda-color-picker"
                        data-default-color="<?php echo esc_attr($defaults['color_bg']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>color_text"><?php _e('Text Color', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>color_text"
                        name="<?php echo self::OPTION_PREFIX; ?>color_text"
                        value="<?php echo esc_attr($this->get_option('color_text')); ?>"
                        class="nekuda-color-picker"
                        data-default-color="<?php echo esc_attr($defaults['color_text']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>color_link"><?php _e('Link Color', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>color_link"
                        name="<?php echo self::OPTION_PREFIX; ?>color_link"
                        value="<?php echo esc_attr($this->get_option('color_link')); ?>"
                        class="nekuda-color-picker"
                        data-default-color="<?php echo esc_attr($defaults['color_link']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>color_btn_bg"><?php _e('Button Background', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>color_btn_bg"
                        name="<?php echo self::OPTION_PREFIX; ?>color_btn_bg"
                        value="<?php echo esc_attr($this->get_option('color_btn_bg')); ?>"
                        class="regular-text"
                        placeholder="<?php echo esc_attr($defaults['color_btn_bg']); ?>">
                    <p class="description"><?php _e('Use "transparent" or a color value', 'nekuda-mct-cookie-notice'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>color_btn_text"><?php _e('Button Text Color', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>color_btn_text"
                        name="<?php echo self::OPTION_PREFIX; ?>color_btn_text"
                        value="<?php echo esc_attr($this->get_option('color_btn_text')); ?>"
                        class="nekuda-color-picker"
                        data-default-color="<?php echo esc_attr($defaults['color_btn_text']); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="<?php echo self::OPTION_PREFIX; ?>color_btn_border"><?php _e('Button Border Color', 'nekuda-mct-cookie-notice'); ?></label>
                </th>
                <td>
                    <input type="text" id="<?php echo self::OPTION_PREFIX; ?>color_btn_border"
                        name="<?php echo self::OPTION_PREFIX; ?>color_btn_border"
                        value="<?php echo esc_attr($this->get_option('color_btn_border')); ?>"
                        class="nekuda-color-picker"
                        data-default-color="<?php echo esc_attr($defaults['color_btn_border']); ?>">
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
<?php
    }
}

new Nekuda_MCT_Cookie_Notice();
