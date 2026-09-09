<?php
/**
 * Template Loader class.
 *
 * Handles template loading with theme override support (WooCommerce-style).
 * Templates can be overridden by copying them to the theme directory.
 */

namespace ElzoForms\Utilities;

// Exit if accessed directly
defined('ABSPATH') || exit;

class Template_Loader {

    /** @var string Default template directory name in theme */
    const DEFAULT_THEME_DIR = 'elzo-forms';

    /** @var string Plugin templates directory path */
    protected static $plugin_template_path = '';

    /** @var string|null Theme templates directory name set programmatically, before filtering */
    protected static $theme_template_dir = null;

    /**
     * Initialize template loader.
     */
    public static function init(): void {
        // Set plugin template path
        self::$plugin_template_path = ELZO_FORMS_PATH . 'templates/';
    }

    /**
     * Get template file path.
     *
     * Searches for template in theme directory first, then falls back to plugin.
     * Admin templates must always pass $allow_override = false to prevent theme overrides.
     *
     * @param string $template_name Template file name (relative path)
     * @param array $args Optional arguments to pass to template
     * @param string $template_path Optional custom template path in theme
     * @param bool $allow_override Whether to allow theme/filter overrides. Must be false for admin templates.
     * @return string Template file path
     */
    public static function locate_template(string $template_name, array $args = [], string $template_path = '', bool $allow_override = true): string {
        if (!$allow_override) {
            // Admin templates: always resolve from plugin directory, no theme or filter override.
            return self::$plugin_template_path . $template_name;
        }

        // Use custom template path or default
        if (!$template_path) {
            $template_path = self::get_theme_template_dir();
        }

        // Check theme directory (child theme first, then parent)
        $theme_template = '';

        if ($template_path) {
            // Check child theme
            $theme_template = get_stylesheet_directory() . '/' . $template_path . '/' . $template_name;

            // Check parent theme if child theme template doesn't exist
            if (!file_exists($theme_template) && is_child_theme()) {
                $theme_template = get_template_directory() . '/' . $template_path . '/' . $template_name;
            }
        }

        // Allow filtering the template location
        $template = apply_filters('elzo_forms/templates/locate_template',
            (file_exists($theme_template) ? $theme_template : ''),
            $template_name,
            $template_path,
            $args
        );

        // Fallback to plugin template if theme template doesn't exist
        if (!$template || !file_exists($template)) {
            $template = self::$plugin_template_path . $template_name;
        }

        return $template;
    }

    /**
     * Get template and return content.
     *
     * @param string $template_name Template file name
     * @param array $args Arguments to pass to template
     * @param string $template_path Optional custom template path in theme
     * @param bool $allow_override Whether to allow theme/filter overrides. Must be false for admin templates.
     * @return string Template output
     */
    public static function get_template(string $template_name, array $args = [], string $template_path = '', bool $allow_override = true): string {
        ob_start();
        self::load_template($template_name, $args, $template_path, $allow_override);
        return ob_get_clean();
    }

    /**
     * Load and include template file.
     *
     * @param string $template_name Template file name
     * @param array $args Arguments to pass to template
     * @param string $template_path Optional custom template path in theme
     * @param bool $allow_override Whether to allow theme/filter overrides. Must be false for admin templates.
     */
    public static function load_template(string $template_name, array $args = [], string $template_path = '', bool $allow_override = true): void {
        // Locate template
        $template = self::locate_template($template_name, $args, $template_path, $allow_override);

        if ($allow_override) {
            // Allow filtering arguments (front-end templates only)
            $args = apply_filters('elzo_forms/templates/template_args', $args, $template_name, $template);

            // Allow action before template is loaded (front-end templates only)
            do_action('elzo_forms/templates/before_template', $template_name, $template_path, $template, $args);
        }

        // Extract args to make them available in template
        if (!empty($args) && is_array($args)) {
            extract($args); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Intentional template variable API; changing extraction mode could break existing template overrides.
        }

        // Include template
        if (file_exists($template)) {
            include $template;
        }

        if ($allow_override) {
            // Allow action after template is loaded (front-end templates only)
            do_action('elzo_forms/templates/after_template', $template_name, $template_path, $template, $args);
        }
    }

    /**
     * Get plugin template path.
     *
     * @return string
     */
    public static function get_plugin_template_path(): string {
        return self::$plugin_template_path;
    }

    /**
     * Get theme template directory name.
     *
     * Resolved on every call rather than during init(): the plugin boots before
     * themes are loaded, so a filter registered in a theme's functions.php would
     * be missed by an eagerly cached value.
     *
     * @return string
     */
    public static function get_theme_template_dir(): string {
        $dir = self::$theme_template_dir ?? self::DEFAULT_THEME_DIR;

        return (string) apply_filters('elzo_forms/templates/theme_dir', $dir);
    }

    /**
     * Set theme template directory name.
     *
     * The value is used as the default passed to 'elzo_forms/templates/theme_dir',
     * so a registered filter still takes precedence over it.
     *
     * @param string $dir Directory name
     */
    public static function set_theme_template_dir(string $dir): void {
        self::$theme_template_dir = $dir;
    }

    /**
     * Get template override info for admin display.
     *
     * @param string $template_name Template file name
     * @return array Template info (path, is_override, theme_file, plugin_file)
     */
    public static function get_template_info(string $template_name): array {
        $theme_template = '';
        $is_override = false;
        $theme_template_dir = self::get_theme_template_dir();

        // Check child theme
        $child_template = get_stylesheet_directory() . '/' . $theme_template_dir . '/' . $template_name;
        if (file_exists($child_template)) {
            $theme_template = $child_template;
            $is_override = true;
        }

        // Check parent theme
        if (!$is_override && is_child_theme()) {
            $parent_template = get_template_directory() . '/' . $theme_template_dir . '/' . $template_name;
            if (file_exists($parent_template)) {
                $theme_template = $parent_template;
                $is_override = true;
            }
        }

        $plugin_template = self::$plugin_template_path . $template_name;

        return [
            'template_name' => $template_name,
            'is_override' => $is_override,
            'active_file' => $is_override ? $theme_template : $plugin_template,
            'theme_file' => $theme_template ?: null,
            'plugin_file' => $plugin_template,
        ];
    }

    /**
     * Get all templates from plugin directory.
     *
     * @param string $path Subdirectory path (optional)
     * @return array Array of template file paths (relative to templates directory)
     */
    public static function get_plugin_templates(string $path = ''): array {
        $template_path = self::$plugin_template_path . $path;
        $templates = [];

        if (!is_dir($template_path)) {
            return $templates;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($template_path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative_path = str_replace(self::$plugin_template_path, '', $file->getPathname());
                $templates[] = $relative_path;
            }
        }

        return $templates;
    }
}
