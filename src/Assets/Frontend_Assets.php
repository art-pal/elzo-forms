<?php
/**
 * Frontend asset registration and enqueueing.
 *
 * @package ElzoForms\Assets
 * @since 1.1.0
 */

namespace ElzoForms\Assets;

// Exit if accessed directly.
defined('ABSPATH') || exit;

final class Frontend_Assets {
    public const STYLE_HANDLE = 'elzo-forms-style';
    public const SCRIPT_HANDLE = 'elzo-forms-script';

    /** @var bool Whether the shared assets have been registered. */
    private static $registered = false;

    /**
     * Register handles shared by shortcode and block rendering.
     *
     * Registration runs in admin requests too so the block editor can load the
     * real compiled form stylesheet inside its content iframe.
     */
    public static function register(): void {
        if (self::$registered) {
            return;
        }

        $plugin_url = plugin_dir_url(ELZO_FORMS_FILE);
        $style_version = get_option('elzo_forms_style_version');

        if ($style_version) {
            $upload_dir = wp_upload_dir();
            $style_url = $upload_dir['baseurl'] . '/elzo-forms/assets/elzo-forms-' . $style_version . '.min.css';
            $style_asset_version = $style_version;
        } elseif (file_exists(ELZO_FORMS_PATH . 'assets/css/elzo-forms.min.css')) {
            $style_url = $plugin_url . 'assets/css/elzo-forms.min.css';
            $style_asset_version = ELZO_FORMS_VERSION;
        } else {
            $style_url = $plugin_url . 'assets/css/elzo-forms.css';
            $style_asset_version = ELZO_FORMS_VERSION;
        }

        wp_register_style(self::STYLE_HANDLE, $style_url, [], $style_asset_version, 'all');
        wp_register_script(
            self::SCRIPT_HANDLE,
            $plugin_url . 'assets/js/elzo-forms.js',
            ['wp-hooks'],
            ELZO_FORMS_VERSION,
            true
        );
        self::$registered = true;
    }

    /**
     * Enqueue the complete interactive frontend runtime.
     */
    public static function enqueue(): void {
        self::register();

        wp_enqueue_style(self::STYLE_HANDLE);
        wp_enqueue_script(self::SCRIPT_HANDLE);
        wp_localize_script(self::SCRIPT_HANDLE, 'ElzoFormsAjax', self::get_script_data());
    }

    /**
     * Build request-specific data consumed by the frontend runtime.
     */
    private static function get_script_data(): array {
        // Non-singular views have no queried post, which is a valid context.
        $current_page_id = (int) get_queried_object_id();
        $is_logged_in = is_user_logged_in();

        return [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'uploadNonce' => wp_create_nonce('elzo_forms_upload_file'),
            'texts' => \ElzoForms\Utilities\Helpers::get_frontend_texts(),
            'logicOperators' => \ElzoForms\Utilities\Conditional_Logic::get_supported_operators(),
            'logicContext' => [
                'isLoggedIn' => $is_logged_in,
                'currentPageId' => $current_page_id,
                'currentPostType' => $current_page_id ? (string) get_post_type($current_page_id) : '',
            ],
        ];
    }
}
