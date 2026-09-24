<?php
/**
 * Import / Export screen.
 *
 * @var array $view Prepared by \ElzoForms\ImportExport\Admin_Controller::render_page().
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This template is included inside Admin_Controller::render_page(), so its variables are function-scoped, not globals.

?>
<div class="wrap elzo-forms-import-export">
    <h1 class="wp-heading-inline"><?php esc_html_e('Import / Export', 'elzo-forms'); ?></h1>
    <hr class="wp-header-end">

    <?php if (!empty($view['notice'])) : ?>
        <div class="notice notice-<?php echo esc_attr($view['notice']['type']); ?> is-dismissible">
            <p><?php echo esc_html($view['notice']['message']); ?></p>
            <?php if (!empty($view['notice']['details'])) : ?>
                <ul class="elzo-forms-ie-list">
                    <?php foreach ($view['notice']['details'] as $detail) : ?>
                        <li><?php echo esc_html($detail); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <section class="elzo-forms-tabs elzo-forms-ie-tabs">
        <nav class="elzo-forms-tabs-header" aria-label="<?php esc_attr_e('Import / Export sections', 'elzo-forms'); ?>">
            <?php foreach ($view['tabs'] as $tab_key => $tab_link) : ?>
                <a href="<?php echo esc_url($tab_link['url']); ?>" class="elzo-forms-tab-button <?php echo $view['tab'] === $tab_key ? 'active' : ''; ?>"<?php if ($view['tab'] === $tab_key) : ?> aria-current="page"<?php endif; ?>>
                    <?php echo esc_html($tab_link['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="elzo-forms-tabs-body">
            <?php // The shared admin tab script shows and hides the direct children of the tabs body, so the layout lives one level below. ?>
            <div class="elzo-forms-tab" data-tab="<?php echo esc_attr($view['tab']); ?>">
                <?php
                if ($view['tab'] === 'submissions') {
                    include __DIR__ . '/admin-import-export-submissions.php';
                } else {
                    include __DIR__ . '/admin-import-export-forms.php';
                }
                ?>
            </div>
        </div>
    </section>
</div>
