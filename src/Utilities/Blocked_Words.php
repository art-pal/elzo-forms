<?php
/**
 * Blocked words utilities.
 *
 * @package ElzoForms\Utilities
 */

namespace ElzoForms\Utilities;

defined('ABSPATH') || exit;

class Blocked_Words {
    /**
     * Check whether submitted value matches any blocked word rule.
     */
    public static function contains_blocked_word(string $submitted_value, array $blocked_words): bool {
        foreach($blocked_words as $blocked_word){
            if(!is_string($blocked_word) && !is_numeric($blocked_word)) continue;

            $blocked_word = (string) $blocked_word;

            // Detect wildcard stars at start/end.
            $leading_star = mb_substr($blocked_word, 0, 1) === '*';
            $trailing_star = mb_substr($blocked_word, -1) === '*';

            // Determine match mode based on wildcard presence.
            if($leading_star && $trailing_star){
                $mode = 'partial'; // *word*
            } elseif($leading_star){
                $mode = 'partial_start'; // *word => ends with
            } elseif($trailing_star){
                $mode = 'partial_end'; // word* => starts with
            } else {
                $mode = 'exact';
            }

            // Strip surrounding stars and whitespace for actual match term.
            $word = trim($blocked_word, " *");
            if($word === '') continue;

            switch($mode){
                case 'partial_start': // "*word" => ends with word
                    if((bool) preg_match('/\\b\\w*' . preg_quote($word, '/') . '\\b/iu', $submitted_value)) return true;
                    break;

                case 'partial_end': // "word*" => starts with word
                    if((bool) preg_match('/\\b' . preg_quote($word, '/') . '\\w*\\b/iu', $submitted_value)) return true;
                    break;

                case 'partial': // "*word*" => contains
                    if(mb_stripos($submitted_value, $word) !== false) return true;
                    break;

                case 'exact':
                default:
                    if((bool) preg_match('/\\b' . preg_quote($word, '/') . '\\b/iu', $submitted_value)) return true;
                    break;
            }
        }

        return false;
    }
}
