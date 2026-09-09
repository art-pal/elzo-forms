<?php
/**
 * Form compatibility inspector.
 *
 * @package ElzoForms\Form\Compatibility
 */

namespace ElzoForms\Form\Compatibility;

defined('ABSPATH') || exit;

final class FormCompatibilityInspector {
    public const WARNING_INVALID_MINIMUM_VERSION = 'invalid_min_version';
    public const WARNING_REQUIRES_NEWER_VERSION = 'requires_newer_version';

    public function inspect(
        array $form_payload,
        string $installed_version
    ): FormCompatibilityResult {
        $installed_version = MinimumVersion::normalize($installed_version);

        if (!array_key_exists(MinimumVersion::KEY, $form_payload)) {
            return new FormCompatibilityResult(true, '', $installed_version, []);
        }

        $raw_minimum = $form_payload[MinimumVersion::KEY];
        if (!MinimumVersion::is_valid($raw_minimum)) {
            return new FormCompatibilityResult(true, '', $installed_version, [
                [
                    'code' => self::WARNING_INVALID_MINIMUM_VERSION,
                    'message' => __('This form contains an invalid minimum version value. Compatibility could not be checked.', 'elzo-forms'),
                ],
            ]);
        }

        $minimum = MinimumVersion::normalize($raw_minimum);
        if (!MinimumVersion::requires_newer_version($minimum, $installed_version)) {
            return new FormCompatibilityResult(true, $minimum, $installed_version, []);
        }

        return new FormCompatibilityResult(false, $minimum, $installed_version, [
            [
                'code' => self::WARNING_REQUIRES_NEWER_VERSION,
                'minimum_version' => $minimum,
                'installed_version' => $installed_version,
                'message' => sprintf(
                    /* translators: 1: Recommended Elzo Forms version. 2: Installed Elzo Forms version. */
                    __('This form was created for Elzo Forms %1$s or newer. Some settings or automations may not work as expected with the installed version %2$s.', 'elzo-forms'),
                    $minimum,
                    $installed_version
                ),
            ],
        ]);
    }
}
