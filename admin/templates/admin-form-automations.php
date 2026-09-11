<?php
// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin settings/meta-box callback chains, so variables here are function-scoped, not globals.

$automation_upsell_context = 'form';
$automation_upsell_title_url = \ElzoForms\Utilities\Admin::get_pro_url('form_automations_title');
$automation_upsell_learn_more_url = \ElzoForms\Utilities\Admin::get_pro_url('form_automations_button');
require __DIR__ . '/admin-automations-upsell.php';
