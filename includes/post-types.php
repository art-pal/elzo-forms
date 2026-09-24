<?php
/**
 * Custom Post Types registration.
 *
 * Registers Elzo Forms custom post types (forms, submissions).
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file is included from ElzoForms::register_post_types() method, so variables here are function-scoped, not globals.

// Post type: Form
// Labels for viewing and attaching media are omitted: forms are not publicly
// viewable and have no media modal. 'new_item' is omitted too. 'add_new' stays:
// WordPress 6.5 and 6.6 still label the Add New submenu and button with it,
// and its default there is "Add New Post". From 6.7 the admin reads
// 'add_new_item' instead.

$form_labels = array(
    'name' => __('Forms', 'elzo-forms'),
    'singular_name' => __('Form', 'elzo-forms'),
    'add_new' => __('Add New Form', 'elzo-forms'),
    'add_new_item' => __('Add New Form', 'elzo-forms'),
    'edit_item' => __('Edit Form', 'elzo-forms'),
    'search_items' => __('Search Forms', 'elzo-forms'),
    'not_found' => __('No Forms found', 'elzo-forms'),
    'not_found_in_trash' => __('No Forms found in trash', 'elzo-forms'),
    'all_items' => __('All Forms', 'elzo-forms'),
    'filter_items_list' => __('Filter Forms list', 'elzo-forms'),
    'items_list_navigation' => __('Forms list navigation', 'elzo-forms'),
    'items_list' => __('Forms list', 'elzo-forms'),
    'item_published' => __('Form published', 'elzo-forms'),
    'item_reverted_to_draft' => __('Form reverted to draft', 'elzo-forms'),
    'item_updated' => __('Form updated', 'elzo-forms'),
);

$form_args = array(
    'label' => __('Elzo Form', 'elzo-forms'),
    'labels' => $form_labels,
    'description' => __('A custom post type for Elzo Forms', 'elzo-forms'),
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'menu_position' => 20,
    'menu_icon' => 'dashicons-feedback',
    'show_in_admin_bar' => true,
    'capability_type' => 'post',
    'supports' => array(
        'title',
        'author',
        'revisions',
    )
);

register_post_type('elzo_form', $form_args);

// Post type: Submission
// Labels for creating, viewing and attaching media are omitted: submissions
// cannot be created by hand, are not publicly viewable and have no media modal.

$submission_labels = array(
    'name' => __('Submissions', 'elzo-forms'),
    'singular_name' => __('Submission', 'elzo-forms'),
    'edit_item' => __('Edit Submission', 'elzo-forms'),
    'search_items' => __('Search Submissions', 'elzo-forms'),
    'not_found' => __('No Submissions found', 'elzo-forms'),
    'not_found_in_trash' => __('No Submissions found in trash', 'elzo-forms'),
    'all_items' => __('Submissions', 'elzo-forms'),
    'filter_items_list' => __('Filter Submissions list', 'elzo-forms'),
    'items_list_navigation' => __('Submissions list navigation', 'elzo-forms'),
    'items_list' => __('Submissions list', 'elzo-forms'),
    'item_published' => __('Submission published', 'elzo-forms'),
    'item_reverted_to_draft' => __('Submission reverted to draft', 'elzo-forms'),
    'item_updated' => __('Submission updated', 'elzo-forms'),
);

$submission_args = array(
    'label' => __('Elzo Submission', 'elzo-forms'),
    'labels' => $submission_labels,
    'description' => __('A custom post type for Elzo Submissions', 'elzo-forms'),
    'public' => false,
    'show_ui' => true,
    'show_in_menu' => 'edit.php?post_type=elzo_form',
    'menu_position' => 20,
    'menu_icon' => 'dashicons-feedback',
    'show_in_admin_bar' => true,
    'capability_type' => 'post',
    // Submissions are created by the form handler, never by hand in wp-admin.
    'capabilities' => array(
        'create_posts' => 'do_not_allow',
    ),
    // Passing 'capabilities' cancels the implicit meta cap mapping WordPress
    // applies to the 'post' capability type, so it has to be set explicitly.
    'map_meta_cap' => true,
    'supports' => array(
        'title',
        'revisions',
    )
);

register_post_type('elzo_submission', $submission_args);

// Post status: Spam
// Registered with the post types, in every request: queries match registered
// statuses only, so spam submissions would otherwise be invisible outside
// wp-admin, for instance to exports and imports run by WP-CLI or cron.

register_post_status('spam', array(
    'label'                     => _x('Spam', 'post status', 'elzo-forms'),
    'public'                    => false,
    'exclude_from_search'       => true,
    'show_in_admin_all_list'    => false,
    'show_in_admin_status_list' => true,
    /* translators: %s: Number of spam submissions. */
    'label_count'               => _n_noop('Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>', 'elzo-forms'),
));
