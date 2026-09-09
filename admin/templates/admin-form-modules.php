<?php

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin-form-meta-box-content.php inside admin callback scope; variables here are function-scoped, not globals.

// Prepare form data for modules template
$form_data = isset($form_object) && method_exists($form_object, 'to_array') ? $form_object->to_array() : [];

// Include unified modules template
include plugin_dir_path(__FILE__) . 'admin-settings-modules.php';
