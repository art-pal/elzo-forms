<?php
/**
 * Custom Post Types registration.
 *
 * Registers Elzo Forms custom post types (forms, submissions).
 */

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file is included from ElzoForms::register_post_types() method, so variables here are function-scoped, not globals.

// Post type: Form

$form_labels = array(
    'name' => __('Forms', 'elzo-forms'),
    'singular_name' => __('Form', 'elzo-forms'),
    'add_new' => __('Add New', 'elzo-forms'),
    'add_new_item' => __('Add New Form', 'elzo-forms'),
    'edit_item' => __('Edit Form', 'elzo-forms'),
    'new_item' => __('New Form', 'elzo-forms'),
    'view_item' => __('View Form', 'elzo-forms'),
    'search_items' => __('Search Forms', 'elzo-forms'),
    'not_found' => __('No Forms found', 'elzo-forms'),
    'not_found_in_trash' => __('No Forms found in trash', 'elzo-forms'),
    'all_items' => __('All Forms', 'elzo-forms'),
    'insert_into_item' => __('Insert into Form', 'elzo-forms'),
    'uploaded_to_this_item' => __('Uploaded to this Form', 'elzo-forms'),
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

$submission_labels = array(
    'name' => __('Submissions', 'elzo-forms'),
    'singular_name' => __('Submission', 'elzo-forms'),
    'add_new' => __('Add New', 'elzo-forms'),
    'add_new_item' => __('Add New Submission', 'elzo-forms'),
    'edit_item' => __('Edit Submission', 'elzo-forms'),
    'new_item' => __('New Submission', 'elzo-forms'),
    'view_item' => __('View Submission', 'elzo-forms'),
    'search_items' => __('Search Submissions', 'elzo-forms'),
    'not_found' => __('No Submissions found', 'elzo-forms'),
    'not_found_in_trash' => __('No Submissions found in trash', 'elzo-forms'),
    'all_items' => __('Submissions', 'elzo-forms'),
    'insert_into_item' => __('Insert into Submission', 'elzo-forms'),
    'uploaded_to_this_item' => __('Uploaded to this Submission', 'elzo-forms'),
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
    'supports' => array(
        'title',
        'revisions',
    )
);

register_post_type('elzo_submission', $submission_args);
