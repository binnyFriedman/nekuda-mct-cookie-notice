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

class Nekuda_MCT_Cookie_Notice {

    const COOKIE_NAME = 'nekuda_mct_cookie_consent';
    const OPTION_PREFIX = 'nekuda_mct_cookie_';

    public function __construct() {
        add_action('wp_footer', [$this, 'render_banner']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
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
        ];
    }

    /**
     * Get option value with default fallback
     */
    private function get_option($key) {
        $defaults = $this->get_defaults();
        return get_option(self::OPTION_PREFIX . $key, $defaults[$key] ?? '');
    }

    /**
     * Enqueue CSS and JS
     */
    public function enqueue_assets() {
        if ($this->has_consent()) {
            return;
        }

        $plugin_url = plugin_dir_url(__FILE__);
        $version = '1.0.0';

        wp_enqueue_style(
            'nekuda-mct-cookie-notice',
            $plugin_url . 'assets/css/cookie-notice.css',
            [],
            $version
        );

        wp_enqueue_script(
            'nekuda-mct-cookie-notice',
            $plugin_url . 'assets/js/cookie-notice.js',
            [],
            $version,
            true
        );

        wp_localize_script('nekuda-mct-cookie-notice', 'nekudaMctCookie', [
            'cookieName' => self::COOKIE_NAME,
            'cookieExpiry' => 365,
        ]);
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
        <div id="nekuda-mct-cookie-banner" class="nekuda-mct-cookie-banner" role="dialog" aria-label="<?php esc_attr_e('Cookie Notice', 'nekuda-mct-cookie-notice'); ?>">
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
     * Register settings
     */
    public function register_settings() {
        $fields = ['message', 'link_text', 'link_url', 'button_text'];

        foreach ($fields as $field) {
            register_setting('nekuda_mct_cookie_settings', self::OPTION_PREFIX . $field, [
                'sanitize_callback' => $field === 'link_url' ? 'esc_url_raw' : 'sanitize_text_field',
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
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php settings_fields('nekuda_mct_cookie_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo self::OPTION_PREFIX; ?>message"><?php _e('Message', 'nekuda-mct-cookie-notice'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="<?php echo self::OPTION_PREFIX; ?>message"
                                   name="<?php echo self::OPTION_PREFIX; ?>message"
                                   value="<?php echo esc_attr($this->get_option('message')); ?>"
                                   class="large-text"
                                   placeholder="<?php echo esc_attr($defaults['message']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo self::OPTION_PREFIX; ?>link_text"><?php _e('Link Text', 'nekuda-mct-cookie-notice'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="<?php echo self::OPTION_PREFIX; ?>link_text"
                                   name="<?php echo self::OPTION_PREFIX; ?>link_text"
                                   value="<?php echo esc_attr($this->get_option('link_text')); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr($defaults['link_text']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo self::OPTION_PREFIX; ?>link_url"><?php _e('Link URL', 'nekuda-mct-cookie-notice'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="<?php echo self::OPTION_PREFIX; ?>link_url"
                                   name="<?php echo self::OPTION_PREFIX; ?>link_url"
                                   value="<?php echo esc_attr($this->get_option('link_url')); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr($defaults['link_url']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo self::OPTION_PREFIX; ?>button_text"><?php _e('Button Text', 'nekuda-mct-cookie-notice'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   id="<?php echo self::OPTION_PREFIX; ?>button_text"
                                   name="<?php echo self::OPTION_PREFIX; ?>button_text"
                                   value="<?php echo esc_attr($this->get_option('button_text')); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr($defaults['button_text']); ?>">
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>
            <h2><?php _e('CSS Variables for Theme Customization', 'nekuda-mct-cookie-notice'); ?></h2>
            <p><?php _e('Add these CSS variables to your theme to customize the banner appearance:', 'nekuda-mct-cookie-notice'); ?></p>
            <pre style="background: #f1f1f1; padding: 15px; overflow-x: auto;">
:root {
    --nekuda-cookie-bg: #1a1a1a;
    --nekuda-cookie-text: rgba(255, 255, 255, 0.95);
    --nekuda-cookie-link: rgba(255, 255, 255, 0.95);
    --nekuda-cookie-btn-bg: transparent;
    --nekuda-cookie-btn-text: rgba(255, 255, 255, 0.95);
    --nekuda-cookie-btn-border: rgba(255, 255, 255, 0.95);
}
            </pre>
        </div>
        <?php
    }
}

new Nekuda_MCT_Cookie_Notice();
