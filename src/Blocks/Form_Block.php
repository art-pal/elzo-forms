<?php
/**
 * Native block editor integration for embedding a form.
 *
 * @package ElzoForms\Blocks
 * @since 1.1.0
 */

namespace ElzoForms\Blocks;

use ElzoForms\Assets\Frontend_Assets;
use ElzoForms\Form\Form;

// Exit if accessed directly.
defined('ABSPATH') || exit;

final class Form_Block {
    public const NAME = 'elzo-forms/form';
    public const EDITOR_SCRIPT_HANDLE = 'elzo-forms-block-editor';
    public const EDITOR_STYLE_HANDLE = 'elzo-forms-block-editor-style';

    /**
     * Register block-related WordPress hooks.
     */
    public static function init(): void {
        add_action('init', [self::class, 'register']);
        add_action('enqueue_block_editor_assets', [self::class, 'provide_editor_data']);
    }

    /**
     * Register the dynamic block and its editor-only assets.
     */
    public static function register(): void {
        Frontend_Assets::register();

        $plugin_url = plugin_dir_url(ELZO_FORMS_FILE);

        wp_register_script(
            self::EDITOR_SCRIPT_HANDLE,
            $plugin_url . 'blocks/elzo-form/editor.js',
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-server-side-render'],
            ELZO_FORMS_VERSION,
            true
        );
        wp_register_style(
            self::EDITOR_STYLE_HANDLE,
            $plugin_url . 'blocks/elzo-form/editor.css',
            [],
            ELZO_FORMS_VERSION,
            'all'
        );

        register_block_type(
            ELZO_FORMS_PATH . 'blocks/elzo-form',
            [
                'render_callback' => [self::class, 'render'],
            ]
        );
    }

    /**
     * Supply only the minimal form catalogue needed by the editor selector.
     *
     * The private form post type intentionally remains unavailable through the
     * standard REST posts controller because its post_content contains the full
     * form definition and settings.
     */
    public static function provide_editor_data(): void {
        if (!current_user_can('edit_posts')) {
            return;
        }

        wp_localize_script(
            self::EDITOR_SCRIPT_HANDLE,
            'ElzoFormsBlockEditor',
            [
                'block' => [
                    'name' => self::NAME,
                    'title' => __('Elzo Form', 'elzo-forms'),
                    'description' => __('Display a form created with Elzo Forms.', 'elzo-forms'),
                    'category' => 'widgets',
                    'icon' => 'feedback',
                    'keywords' => [
                        __('form', 'elzo-forms'),
                        __('contact', 'elzo-forms'),
                        __('Elzo', 'elzo-forms'),
                    ],
                ],
                'forms' => self::get_form_options(),
                'newFormUrl' => current_user_can('edit_posts')
                    ? admin_url('post-new.php?post_type=elzo_form')
                    : '',
                'strings' => [
                    'selectForm' => __('Select a form', 'elzo-forms'),
                    'selectFormHelp' => __('Choose the form to display on this page.', 'elzo-forms'),
                    'searchPlaceholder' => __('Search...', 'elzo-forms'),
                    'changeForm' => __('Form', 'elzo-forms'),
                    'noFormSelected' => __('No form selected.', 'elzo-forms'),
                    'noForms' => __('No forms are available. Create a form first.', 'elzo-forms'),
                    'createForm' => __('Create a form', 'elzo-forms'),
                    'clearSelection' => __('Clear selection', 'elzo-forms'),
                    'unavailableForm' => __('The selected form is unavailable. It may have been deleted or you may no longer have permission to view it.', 'elzo-forms'),
                    'previewLoading' => __('Loading form preview…', 'elzo-forms'),
                    'previewEmpty' => __('This form cannot be previewed. Check that it is published or that you have permission to view it.', 'elzo-forms'),
                    'previewError' => __('The form preview could not be loaded.', 'elzo-forms'),
                    'previewLabel' => __('Form preview', 'elzo-forms'),
                    'untitledForm' => __('Untitled form', 'elzo-forms'),
                    // Context keeps these block labels apart from the same words
                    // used, and translated separately, elsewhere in the admin.
                    'layout' => _x('Layout', 'Elzo Form block settings panel', 'elzo-forms'),
                    'maxWidth' => __('Maximum width', 'elzo-forms'),
                    'maxWidthHelp' => __('For example 600px, 40rem or 80%. A number without a unit is read as pixels. Leave empty for the full width.', 'elzo-forms'),
                    'maxWidthInvalid' => __('Enter a number with an optional unit, such as 600px, 40rem or 80%. Any other value is ignored.', 'elzo-forms'),
                    'formAlign' => __('Form alignment', 'elzo-forms'),
                    'formAlignHelp' => __('Positions the form within the available width. Takes effect when a maximum width is set.', 'elzo-forms'),
                    'textAlign' => __('Text alignment', 'elzo-forms'),
                    'alignDefault' => _x('Default', 'Elzo Form block alignment', 'elzo-forms'),
                    'alignLeft' => _x('Left', 'Elzo Form block alignment', 'elzo-forms'),
                    'alignCenter' => _x('Center', 'Elzo Form block alignment', 'elzo-forms'),
                    'alignRight' => _x('Right', 'Elzo Form block alignment', 'elzo-forms'),
                ],
            ]
        );
    }

    /**
     * Return readable forms for the block selector without exposing form JSON.
     *
     * @return array<int, array{id:int,title:string,status:string,statusLabel:string}>
     */
    public static function get_form_options(): array {
        if (!current_user_can('edit_posts')) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'elzo_form',
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'suppress_filters' => false,
        ]);
        $options = [];

        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $form = new Form($post);
            if (!$form->can_interact()) {
                continue;
            }

            $title = trim($form->get_title());
            if ($title === '') {
                $title = sprintf(
                    /* translators: %d: Form post ID. */
                    __('Untitled form (#%d)', 'elzo-forms'),
                    $form->get_id()
                );
            }

            $status = sanitize_key($form->get_status());
            $options[] = [
                'id' => $form->get_id(),
                'title' => $title,
                'status' => $status,
                'statusLabel' => self::get_status_label($status),
            ];
        }

        /**
         * Filter the minimal form catalogue displayed in the block selector.
         *
         * Callers must not add private form configuration to these entries.
         *
         * @param array $options Form selector entries.
         */
        return (array) apply_filters('elzo_forms_block_form_options', $options);
    }

    /**
     * Render the selected form through the existing frontend engine.
     *
     * @param array    $attributes Parsed block attributes.
     * @param string   $content    Saved block content; empty for this dynamic block.
     * @param \WP_Block|null $block Block instance.
     */
    public static function render(array $attributes, string $content = '', $block = null): string {
        unset($content, $block);

        $form_id = isset($attributes['formId']) && is_scalar($attributes['formId'])
            ? (int) $attributes['formId']
            : 0;

        if ($form_id <= 0) {
            return '';
        }

        // The renderer validates the layout values, exactly as for the shortcode.
        $form_html = Form::render_by_id([
            'id' => $form_id,
            'max_width' => self::get_string_attribute($attributes, 'maxWidth'),
            'form_align' => self::get_string_attribute($attributes, 'formAlign'),
            'text_align' => self::get_string_attribute($attributes, 'textAlign'),
            'echo' => false,
        ]);

        if ($form_html === '') {
            return '';
        }

        $wrapper_attributes = get_block_wrapper_attributes([
            'class' => 'elzo-forms-block',
        ]);

        return '<div ' . $wrapper_attributes . '>' . $form_html . '</div>';
    }

    /**
     * Read a scalar block attribute as a string; anything else is empty.
     *
     * @param array  $attributes Parsed block attributes.
     * @param string $key        Attribute name.
     */
    private static function get_string_attribute(array $attributes, string $key): string {
        return isset($attributes[$key]) && is_scalar($attributes[$key]) ? (string) $attributes[$key] : '';
    }

    /**
     * Get a translated label for the post statuses accepted by the selector.
     */
    private static function get_status_label(string $status): string {
        $labels = [
            'publish' => __('Published', 'elzo-forms'),
            'draft' => __('Draft', 'elzo-forms'),
            'pending' => __('Pending Review', 'elzo-forms'),
            'private' => __('Private', 'elzo-forms'),
            'future' => __('Scheduled', 'elzo-forms'),
        ];

        return $labels[$status] ?? $status;
    }
}
