<?php
/**
 * Conditional logic utilities.
 *
 * Conditional visibility decides what a form shows, never what a request is
 * allowed to do. It is not an authorization boundary, and no feature may treat
 * it as one.
 *
 * Two of its inputs come from the browser and cannot be verified server side.
 * The page context is posted by the client, because submissions are handled by
 * admin-ajax.php where there is no queried object; cookies are client state by
 * definition. A visitor can therefore choose the outcome of any page, URL or
 * cookie condition. Field conditions are the exception: they are recomputed
 * from the sanitized submission itself.
 *
 * Consequently a condition must never be the only thing standing between a
 * request and a capability check, a rate limit, a secret, or a side effect that
 * matters. Gate those on the server state that actually proves them.
 *
 * @package ElzoForms\Utilities
 */

namespace ElzoForms\Utilities;

defined('ABSPATH') || exit;

class Conditional_Logic {
    /**
     * Get all supported field-value comparison operators.
     */
    public static function get_supported_operators(): array {
        return array_merge(
            ['==', '!=', '>', '<', 'like', 'not_like', 'starts_with', 'ends_with', 'pattern'],
            self::get_count_operators()
        );
    }

    /**
     * Get operators that compare how many values a field submitted.
     *
     * Every other operator asks a question about the values themselves, which
     * leaves the size of the list unreachable: a field that submits five files
     * or five checked options has no single value meaning "five". These ask
     * about the list instead, so they are useful to any field that can submit
     * more than one value.
     *
     * @return array
     */
    public static function get_count_operators(): array {
        return ['count_eq', 'count_gt', 'count_lt'];
    }

    /**
     * Get default string operators used by comparable text fields.
     */
    public static function get_string_operators(): array {
        return ['==', '!=', 'like', 'not_like', 'starts_with', 'ends_with', 'pattern'];
    }

    /**
     * Get translated operator labels for field-value conditions in admin UI.
     */
    public static function get_operator_labels(): array {
        return [
            '==' => __('Value is', 'elzo-forms'),
            '!=' => __('Value is not', 'elzo-forms'),
            '>' => __('Value is greater than', 'elzo-forms'),
            '<' => __('Value is less than', 'elzo-forms'),
            'like' => __('Value contains', 'elzo-forms'),
            'not_like' => __('Value does not contain', 'elzo-forms'),
            'starts_with' => __('Value starts with', 'elzo-forms'),
            'ends_with' => __('Value ends with', 'elzo-forms'),
            'pattern' => __('Value matches pattern', 'elzo-forms'),
            'count_eq' => __('Number of values is', 'elzo-forms'),
            'count_gt' => __('Number of values is greater than', 'elzo-forms'),
            'count_lt' => __('Number of values is less than', 'elzo-forms'),
        ];
    }

    // -------------------------------------------------------------------------
    // Field visibility condition types (available for field show/hide logic)
    // -------------------------------------------------------------------------

    /**
     * Returns condition types available for the given usage context.
     *
     * Every context currently exposes the same list: field visibility supports
     * Page (and URL in PRO) as long as the caller passes the page context to
     * is_visible(). $usage_context is kept for contexts that need a narrower
     * list — add a case below and filter the returned array before returning.
     */
    public static function get_condition_types(string $usage_context = ''): array {
        $types = [
            'field'  => __('Field value', 'elzo-forms'),
            'auth'   => __('Authentication', 'elzo-forms'),
            'page'   => __('Page', 'elzo-forms'),
        ];

        /**
         * Filters the condition types registered for a usage context.
         *
         * Presentation-only entries must not be added here. A type in this
         * registry is treated as genuinely supported by the runtime owner.
         *
         * @filter elzo_forms_condition_types
         * @param array<string, string> $types Condition type => label.
         * @param string $usage_context Usage context, such as "field".
         */
        $types = apply_filters('elzo_forms_condition_types', $types, $usage_context);

        return is_array($types) ? $types : [];
    }

    /**
     * Shorthand for field visibility context. Delegates to get_condition_types().
     */
    public static function get_field_condition_types(): array {
        return self::get_condition_types('field');
    }

    /**
     * Whether a saved rule of this type may be kept when a form is saved.
     *
     * Storing a rule and supporting it are separate questions. A type can be
     * picked and evaluated only while it is registered (get_condition_types()),
     * but a rule whose owner is inactive right now, such as PRO deactivated or
     * an addon switched off, still belongs to the form and must survive a
     * plain Save. Such a type only has to be a well-formed machine name; it
     * gains no runtime support from being stored, and is_visible() keeps
     * failing it closed until its owner registers it again.
     */
    public static function is_storable_condition_type(string $type, string $usage_context = ''): bool {
        if ($type === '') return false;

        if (isset(self::get_condition_types($usage_context)[$type])) return true;

        return preg_match('/^[a-z0-9_-]{1,64}$/', $type) === 1;
    }

    /**
     * Operator lists per condition type for the given usage context.
     *
     * @see get_condition_types() for $usage_context values.
     */
    public static function get_condition_operators_map(string $usage_context = ''): array {
        $operators = [
            'field'  => self::get_supported_operators(),
            'page' => ['page_id_equals', 'page_id_not_equals', 'page_id_in', 'page_id_not_in', 'post_type_equals', 'post_type_not_equals', 'post_type_in', 'post_type_not_in'],
            'auth'   => ['is_logged_in', 'is_guest'],
        ];

        /**
         * Filters operator lists for condition types in a usage context.
         *
         * @filter elzo_forms_condition_operators_map
         * @param array<string, array<int, string>> $operators Operators by type.
         * @param string $usage_context Usage context, such as "field".
         */
        $operators = apply_filters('elzo_forms_condition_operators_map', $operators, $usage_context);

        return is_array($operators) ? $operators : [];
    }

    /**
     * Shorthand for field visibility context. Delegates to get_condition_operators_map().
     */
    public static function get_field_condition_operators_map(): array {
        return self::get_condition_operators_map('field');
    }

    /**
     * Human-readable operator labels for the given usage context.
     *
     * @see get_condition_types() for $usage_context values.
     */
    public static function get_condition_operator_labels(string $usage_context = ''): array {
        // PRO operators with the same wording reuse these FREE labels.
        $contains_label = __('contains', 'elzo-forms');
        $not_contains_label = __('does not contain', 'elzo-forms');
        $labels = [
            '=='              => __('is', 'elzo-forms'),
            '!='              => __('is not', 'elzo-forms'),
            '>'               => __('greater than', 'elzo-forms'),
            '<'               => __('less than', 'elzo-forms'),
            'like'            => $contains_label,
            'not_like'        => $not_contains_label,
            'starts_with'     => __('starts with', 'elzo-forms'),
            'ends_with'       => __('ends with', 'elzo-forms'),
            'pattern'         => __('matches pattern', 'elzo-forms'),
            'count_eq'        => __('number of values is', 'elzo-forms'),
            'count_gt'        => __('number of values is greater than', 'elzo-forms'),
            'count_lt'        => __('number of values is less than', 'elzo-forms'),
            'is_logged_in'    => __('is logged in', 'elzo-forms'),
            'is_guest'        => __('is guest', 'elzo-forms'),
            'page_id_equals'       => __('page ID equals', 'elzo-forms'),
            'page_id_not_equals'   => __('page ID not equals', 'elzo-forms'),
            'page_id_in'           => __('page ID in list', 'elzo-forms'),
            'page_id_not_in'       => __('page ID not in list', 'elzo-forms'),
            'post_type_equals'     => __('post type is', 'elzo-forms'),
            'post_type_not_equals' => __('post type is not', 'elzo-forms'),
            'post_type_in'         => __('post type in list', 'elzo-forms'),
            'post_type_not_in'     => __('post type not in list', 'elzo-forms'),
        ];

        /**
         * Filters condition operator labels for a usage context.
         *
         * @filter elzo_forms_condition_operator_labels
         * @param array<string, string> $labels Operator labels.
         * @param string $usage_context Usage context, such as "field".
         */
        $labels = apply_filters('elzo_forms_condition_operator_labels', $labels, $usage_context);

        return is_array($labels) ? $labels : [];
    }

    /**
     * Shorthand for field visibility context. Delegates to get_condition_operator_labels().
     */
    public static function get_field_condition_operator_labels(): array {
        return self::get_condition_operator_labels('field');
    }

    // -------------------------------------------------------------------------
    // Flatten helpers
    // -------------------------------------------------------------------------

    /**
     * Flatten submitted step payload to [field_id => value].
     */
    public static function flatten_submission_values_by_field_id(array $submission_steps): array {
        $values_by_field_id = [];

        foreach($submission_steps as $submission_step_fields){
            if(!is_array($submission_step_fields)) continue;

            foreach($submission_step_fields as $submission_field_id => $submission_field_value){
                $values_by_field_id[(string) $submission_field_id] = $submission_field_value;
            }
        }

        return $values_by_field_id;
    }

    /**
     * Build the page context for Page/URL conditions from the current request.
     *
     * Submissions are handled by admin-ajax.php, which has no queried object, so
     * the browser posts the page the form was rendered on. Only the ID is taken
     * from the request: the post type is derived from it and the URL is accepted
     * only when it belongs to this site. Returns an empty array when the request
     * carries no page context, which keeps page conditions closed.
     */
    public static function get_request_page_context(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Read-only visibility context; the submission handler verifies the nonce before this is used.
        if(!isset($_POST['elzo_form_page_id']) || !is_scalar($_POST['elzo_form_page_id'])) return [];

        $page_id = absint(wp_unslash((string) $_POST['elzo_form_page_id']));

        $context = [
            'current_page_id'   => $page_id,
            'current_post_type' => $page_id ? (string) get_post_type($page_id) : '',
        ];

        $posted_url = isset($_POST['elzo_form_page_url']) && is_scalar($_POST['elzo_form_page_url'])
            ? esc_url_raw(wp_unslash((string) $_POST['elzo_form_page_url']))
            : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if($posted_url !== '' && wp_parse_url($posted_url, PHP_URL_HOST) === wp_parse_url(home_url(), PHP_URL_HOST)){
            $context['current_url'] = $posted_url;
        }

        return $context;
    }

    // -------------------------------------------------------------------------
    // Unified condition evaluation
    // -------------------------------------------------------------------------

    /**
     * Evaluate OR-of-ANDs rule groups.
     *
     * Each rule item uses the unified schema: {type, operator, settings}.
     *
     * Context array keys:
     *   fields_by_id      => array [field_id => value] (submitted field values)
     *   fields            => array [field_id => value] (used by field-visibility contexts)
     *   cookies           => array [name => value]     (optional; falls back to $_COOKIE)
     *   current_page_id   => int    (page conditions; 0 on non-singular views)
     *   current_post_type => string (page conditions; '' when there is no queried post)
     *   current_url       => string (url conditions)
     *
     * Page and URL conditions never match while their context keys are absent:
     * the browser evaluates the same rules and hides the field when it cannot
     * resolve the page, so a caller without that context must not disagree.
     *
     * Auth and user conditions are evaluated directly via WordPress functions.
     *
     * The result describes visibility only. See the class docblock: page, URL
     * and cookie conditions rest on client-supplied context, so no caller may
     * use this as an authorization decision.
     */
    public static function is_visible(array $logic_rules, array $context): bool {
        if(!$logic_rules) return true;

        foreach($logic_rules as $group){
            if(!is_array($group) || !$group) continue;

            $all_conditions_met = true;

            foreach($group as $item){
                if(!is_array($item) || !self::evaluate_condition_item($item, $context)){
                    $all_conditions_met = false;
                    break;
                }
            }

            if($all_conditions_met) return true;
        }

        return false;
    }

    /**
     * Evaluate a single condition item {type, operator, settings} against context.
     *
     * This is the shared evaluator used by both field visibility and (via delegation)
     * the PRO automation condition classes for auth / user / cookie / field types.
     */
    public static function evaluate_condition_item(array $item, array $context): bool {
        $type     = isset($item['type'])     ? sanitize_key((string) $item['type'])          : 'field';
        $operator = isset($item['operator']) ? self::normalize_operator((string) $item['operator']) : '==';
        $settings = isset($item['settings']) && is_array($item['settings']) ? $item['settings'] : [];

        switch($type){
            case 'field':
                return self::evaluate_field_item($operator, $settings, $context);
            case 'page':
                return self::evaluate_page_item($operator, $settings, $context);
            case 'auth':
                return self::evaluate_auth_item($operator);
            default:
                return false;
        }
    }

    /**
     * Evaluate a field-value condition item.
     */
    private static function evaluate_field_item(string $operator, array $settings, array $context): bool {
        $field_id = isset($settings['field_id']) ? sanitize_text_field((string) $settings['field_id']) : '';
        $expected = isset($settings['value'])    ? (string) $settings['value']                        : '';

        if($field_id === '') return false;

        $fields = isset($context['fields_by_id']) && is_array($context['fields_by_id'])
            ? $context['fields_by_id']
            : (isset($context['fields']) && is_array($context['fields']) ? $context['fields'] : []);
        $actual = $fields[$field_id] ?? '';

        return self::compare_values(self::normalize_values($actual), $operator, $expected);
    }

    /**
     * Evaluate an authentication condition item.
     */
    private static function evaluate_auth_item(string $operator): bool {
        $is_logged_in = is_user_logged_in();
        return $operator === 'is_guest' ? !$is_logged_in : $is_logged_in;
    }
    /**
     * Evaluate a page condition item.
     */
    private static function evaluate_page_item(string $operator, array $settings, array $context): bool {
        if(!array_key_exists('current_page_id', $context) && !array_key_exists('current_post_type', $context)) return false;

        $current_page_id   = isset($context['current_page_id'])   ? intval($context['current_page_id'])                  : 0;
        $current_post_type = isset($context['current_post_type']) ? sanitize_key((string) $context['current_post_type']) : '';

        $page_ids = array_map('intval', (array) ($settings['page_ids'] ?? []));
        if (empty($page_ids) && isset($settings['page_id'])) {
            $page_ids = [intval($settings['page_id'])];
        }
        // Fallback: comma-separated value
        if (empty($page_ids) && !empty($settings['value'])) {
            $page_ids = array_values(array_filter(array_map('intval', explode(',', (string) $settings['value']))));
        }

        $raw_post_types = $settings['post_types'] ?? ($settings['post_type'] ?? ($settings['value'] ?? []));
        if (!is_array($raw_post_types)) {
            $raw_post_types = preg_split('/[\s,]+/', (string) $raw_post_types, -1, PREG_SPLIT_NO_EMPTY);
        }
        $post_types = array_values(array_unique(array_filter(array_map(function ($post_type): string {
            return is_scalar($post_type) ? sanitize_key((string) $post_type) : '';
        }, (array) $raw_post_types))));
        $post_type = $post_types[0] ?? '';

        switch ($operator) {
            case 'page_id_equals':
                return !empty($page_ids[0]) && $current_page_id === (int) $page_ids[0];
            case 'page_id_not_equals':
                return !empty($page_ids[0]) && $current_page_id !== (int) $page_ids[0];
            case 'page_id_in':
                return in_array($current_page_id, $page_ids, true);
            case 'page_id_not_in':
                return !in_array($current_page_id, $page_ids, true);
            case 'post_type_equals':
                return $post_type !== '' && $current_post_type === $post_type;
            case 'post_type_not_equals':
                return $post_type !== '' && $current_post_type !== $post_type;
            case 'post_type_in':
                return $post_types !== [] && in_array($current_post_type, $post_types, true);
            case 'post_type_not_in':
                return $post_types !== [] && !in_array($current_post_type, $post_types, true);
            default:
                return false;
        }
    }

    /**
     * Compare one or multiple values using a logic operator.
     */
    public static function compare_values(array $values, string $operator, string $expected_value): bool {
        switch($operator){
            case '==':
                return in_array($expected_value, $values, true);

            case '!=':
                foreach($values as $value){
                    if($value === $expected_value) return false;
                }
                return true;

            case '>':
                foreach($values as $value){
                    if(floatval($value) <= floatval($expected_value)) return false;
                }
                return true;

            case '<':
                foreach($values as $value){
                    if(floatval($value) >= floatval($expected_value)) return false;
                }
                return true;

            case 'like':
                foreach($values as $value){
                    if(mb_strpos($value, $expected_value) !== false) return true;
                }
                return false;

            case 'not_like':
                foreach($values as $value){
                    if(mb_strpos($value, $expected_value) !== false) return false;
                }
                return true;

            case 'starts_with':
                foreach($values as $value){
                    if(self::starts_with($value, $expected_value)) return true;
                }
                return false;

            case 'ends_with':
                foreach($values as $value){
                    if(self::ends_with($value, $expected_value)) return true;
                }
                return false;

            case 'pattern':
                return self::matches_pattern_for_all_values($values, $expected_value);

            case 'count_eq':
                return self::count_submitted_values($values) === intval($expected_value);

            case 'count_gt':
                return self::count_submitted_values($values) > intval($expected_value);

            case 'count_lt':
                return self::count_submitted_values($values) < intval($expected_value);

            default:
                return false;
        }
    }

    /**
     * Count the values a field actually submitted.
     *
     * An empty field still normalizes to a single empty string so that the
     * value operators have something to compare, so counting the list as-is
     * would report one value for a field that submitted none. Empty strings
     * are dropped here instead: nothing was uploaded, checked or selected.
     */
    private static function count_submitted_values(array $values): int {
        $submitted = array_filter($values, static function($value){
            return (string) $value !== '';
        });

        return count($submitted);
    }

    /**
     * Normalize scalar/array input to a list of strings.
     */
    private static function normalize_values($source_value): array {
        if(is_array($source_value)){
            $values = array_map(static function($value){
                return (string) $value;
            }, $source_value);

            return empty($values) ? [''] : $values;
        }

        return [(string) $source_value];
    }

    /**
     * Normalize operator while preserving symbolic values like < and >.
     *
     * sanitize_text_field() is not suitable here because it strips angle
     * brackets, which are valid conditional operators. Instead, accept only
     * operators explicitly registered for the current build/context.
     */
    private static function normalize_operator(string $operator): string {
        $operator = trim($operator);
        if($operator === '') return '==';

        $allowed = [];
        foreach(self::get_condition_operators_map() as $operators){
            if(is_array($operators)){
                $allowed = array_merge($allowed, $operators);
            }
        }

        return in_array($operator, array_unique($allowed), true) ? $operator : '==';
    }

    /**
     * Match JavaScript RegExp(logicValue).test(value) semantics with a safe PHP fallback.
     */
    private static function matches_pattern_for_all_values(array $values, string $pattern_body): bool {
        $pattern = '/' . str_replace('/', '\\/', $pattern_body) . '/u';

        foreach($values as $value){
            $result = @preg_match($pattern, $value); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid user patterns intentionally fail closed without emitting runtime warnings.
            if($result !== 1) return false;
        }

        return true;
    }

    /**
     * PHP 7.4 compatible starts_with check.
     */
    private static function starts_with(string $value, string $expected): bool {
        return substr($value, 0, strlen($expected)) === $expected;
    }

    /**
     * PHP 7.4 compatible ends_with check.
     */
    private static function ends_with(string $value, string $expected): bool {
        if($expected === '') return true;
        return substr($value, -strlen($expected)) === $expected;
    }
}
