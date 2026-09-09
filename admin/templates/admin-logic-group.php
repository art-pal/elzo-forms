<?php

defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included via admin-form-field-logic.php within admin meta-box callback scope; variables here are function-scoped, not globals.

    //
?>
<div class="elzo-forms-field-logic-group">
    <div class="elzo-forms-field-logic-group-rules">
        <?php if(!empty($group)){
            foreach ($group as $rule_index => $rule) {
                include plugin_dir_path(__DIR__) . 'templates/admin-logic-item.php';
            }
        } else {
            include plugin_dir_path(__DIR__) . 'templates/admin-logic-item.php';
        } ?>
    </div>
    <div class="elzo-forms-logic-label"><?php echo esc_html__('or', 'elzo-forms'); ?></div>
</div>