<?php

defined('ABSPATH') || exit;
?>
<div class="elzo-forms-form-meta-box-content elzo-forms-wrapper">
    <ul>
        <li><strong><?php esc_html_e('User ID', 'elzo-forms'); ?></strong>: <?php echo !empty($user['user_id']) ? esc_html($user['user_id']) : '-'; ?></li>
        <?php if(!empty($user['user_id']) && !empty($user['user_object'])): ?>
            <li><strong><?php esc_html_e('User', 'elzo-forms'); ?></strong>: <a href="<?php echo esc_url(get_edit_user_link($user['user_id'])); ?>"><?php echo esc_html($user['user_object']->display_name); ?></a></li>
        <?php endif; ?>
        <li><strong><?php esc_html_e('User Agent', 'elzo-forms'); ?></strong>: <?php echo esc_html($user['user_agent'] ?? '-'); ?></li>
        <li><strong><?php esc_html_e('IP Address', 'elzo-forms'); ?></strong>: <?php echo esc_html($user['ip'] ?? '-'); ?></li>
    </ul>
</div>