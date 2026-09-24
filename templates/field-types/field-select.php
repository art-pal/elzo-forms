<?php
/**
 * The template for displaying select dropdown field
 *
 * This template can be overridden by copying it to yourtheme/elzo-forms/field-types/field-select.php.
 *
 * HOWEVER, on occasion Elzo Forms will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         Plugin documentation
 * @package     ElzoForms\Templates
 * @version     1.2.0
 *
 * @var array $d Field data prepared for rendering
 */

// Exit if accessed directly
defined('ABSPATH') || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- This file is loaded via Field::render() → Template_Loader::load_template() (function scope). Variables here are not in global scope.

/**
 * Available variables (passed directly):
 * @var bool   $has_custom_dropdown  Whether to use custom dropdown
 * @var string $name                 Field name
 * @var bool   $multiple             Whether multiple selection is allowed
 * @var string $class                CSS classes
 * @var string $id                   Field ID
 * @var bool   $required             Whether the field is required
 * @var array  $logic_rules          Conditional logic rules
 * @var string $placeholder          Placeholder text
 * @var mixed  $value                Current field value(s)
 * @var array  $options              Array of select options
 * @var bool   $search               Whether search is enabled
 * @var string $value_label          Label for current value
 */

?>
<?php if($has_custom_dropdown){ ?>
    <div class="elzo-forms-select-wrapper elzo-forms-custom-select-wrapper">
        <select name="<?php echo esc_attr($name); ?>" class="<?php echo esc_attr($class); ?>" id="<?php echo esc_attr($id); ?>" <?php if (!empty($described_by)) { ?>aria-describedby="<?php echo esc_attr($described_by); ?>"<?php } ?> <?php echo $multiple ? 'multiple="multiple"' : ''; ?> <?php echo $required && !$logic_rules ? 'required' : ''; ?> style="display:none">
            <?php if($placeholder){ ?>
                <option value="" <?php echo !$value && !$multiple ? 'selected=""' : ''; ?> <?php echo $required ? 'disabled=""' : ''; ?> data-placeholder="true" ><?php echo esc_html($placeholder); ?></option>
            <?php } ?>
            <?php if($options){ ?>
                <?php foreach($options as $option): ?>
                    <option value="<?php echo esc_attr($option['value']); ?>" <?php echo !empty($option['active']) ? 'selected=""' : ''; ?>><?php echo esc_html($option['label'] ? $option['label'] : $option['value']); ?></option>
                <?php endforeach; ?>
            <?php } ?>
        </select>
        <div class="elzo-forms-custom-select-facade-wrapper elzo-forms-custom-dropdown-wrapper">
            <div class="elzo-forms-custom-dropdown">
                <?php if($search){ ?>
                    <input type="text" class="elzo-forms-custom-dropdown-search-input" placeholder="<?php echo esc_attr__('Search', 'elzo-forms'); ?>...">
                <?php } else { ?>
                    <button type="button" class="elzo-forms-custom-dropdown-toggler"></button>
                <?php } ?>
                <div class="elzo-forms-custom-dropdown-items">
                    <?php if($options){ ?>
                        <?php foreach($options as $option): ?>
                            <button type="button" class="elzo-forms-custom-dropdown-item <?php echo !empty($option['active']) ? 'active' : ''; ?>" data-value="<?php echo esc_attr($option['value']); ?>"><?php echo wp_kses_post($option['label'] ? $option['label'] : $option['value']); ?></button>
                        <?php endforeach; ?>
                    <?php } ?>
                    <!-- Nothing found item -->
                    <button type="button" class="elzo-forms-custom-dropdown-item nothing-found" data-value="" disabled="" style="display:none"><?php esc_html_e('Nothing found', 'elzo-forms'); ?></button>
                </div>
            </div>
            <?php if($multiple){ ?>
                <div class="elzo-forms-custom-select-facade elzo-forms-field-multiple-select-facade elzo-forms-field-control elzo-forms-field-select" <?php echo $value ? '' : 'style="display:none"'; ?> >
                    <?php if($value && is_array($value)){
                        foreach($value as $value_item):
                            $value_label_index = is_scalar($value_item)
                                ? array_search((string) $value_item, array_map('strval', array_column($options, 'value')), true)
                                : false;
                            $value_label = $value_label_index !== false ? ($options[$value_label_index]['label'] ?: $options[$value_label_index]['value']) : $value_item;
                        ?><button type="button" class="elzo-forms-custom-select-facade-item" data-value="<?php echo esc_attr($value_item); ?>">
                            <?php echo wp_kses_post($value_label); ?> &times;
                        </button><?php endforeach;
                    } ?>
                </div>
            <?php } ?>
            <input type="text" readonly class="elzo-forms-custom-select-facade-input elzo-forms-field-control elzo-forms-field-select" value="<?php echo esc_attr($value_label); ?>" placeholder="<?php echo esc_attr($placeholder); ?>" <?php echo $multiple && $value ? 'style="display:none"' : ''; ?> tabindex="-1" >
        </div>
    </div>
<?php } else { ?>
    <div class="elzo-forms-select-wrapper">
        <select name="<?php echo esc_attr($name); ?>" class="<?php echo esc_attr($class); ?>" id="<?php echo esc_attr($id); ?>" <?php if (!empty($described_by)) { ?>aria-describedby="<?php echo esc_attr($described_by); ?>"<?php } ?> <?php echo $required && !$logic_rules ? 'required' : ''; ?>>
            <?php if($placeholder){ ?>
                <option value="" <?php echo !$value ? 'selected=""' : ''; ?> <?php echo $required ? 'disabled=""' : ''; ?> data-placeholder="true" ><?php echo esc_html($placeholder); ?></option>
            <?php } ?>
            <?php if($options){ ?>
                <?php foreach($options as $option): ?>
                    <option value="<?php echo esc_attr($option['value']); ?>" <?php echo !empty($option['active']) ? 'selected=""' : ''; ?>><?php echo wp_kses_post($option['label'] ? $option['label'] : $option['value']); ?></option>
                <?php endforeach; ?>
            <?php } ?>
        </select>
    </div>
<?php } ?>
