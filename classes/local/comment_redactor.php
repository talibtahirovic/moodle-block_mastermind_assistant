<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Redaction of free-text evaluation answers before they leave Moodle.
 *
 * @package    block_mastermind_assistant
 * @copyright  2026 The Namers <info@mastermindassistant.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_mastermind_assistant\local;

/**
 * Removes contact details and the ways learners sign their name from one answer.
 *
 * Evaluation answers are sent to the dashboard as text, so identity a learner
 * typed into an answer would otherwise leave the site. This applies the same
 * rules as the dashboard's own redaction (src/lib/redact.ts), which still runs
 * as a second pass. Two deliberate differences:
 *
 * - Every removal leaves a placeholder, signatures included. The dashboard never
 *   quotes an answer that carries a placeholder, so a signed answer can never
 *   become an excerpt even though its signature is gone by the time it arrives.
 * - Names must start with a capital letter; only the salutations and titles
 *   around them are matched case-insensitively.
 *
 * Privacy first: an over-eager match loses a few words of an answer; a missed
 * one sends a name.
 */
class comment_redactor {
    /** @var string A capitalised name token: "Jane", "O'Brien-Walsh". */
    const NAME = "\\p{Lu}[\\p{L}'\u{2019}-]*";

    /** @var string A title before a name. */
    const HONORIFIC = '(?:Dr|Prof|Professor|Mr|Mrs|Ms|Miss|Mx|Sir|Dame|Rev|Fr|Hon)\\.?';

    /** @var string A closing salutation before a signature. */
    const SALUTATION = '(?:kind regards|best regards|warm regards|best wishes|many thanks|thank you|thanks|regards|'
        . 'cheers|sincerely|yours(?: sincerely| truly| faithfully)?|best)';

    /**
     * Redact one answer.
     *
     * @param string $text The answer as written (plain text).
     * @return string The answer without contact details or signatures; '' if it cannot be processed.
     */
    public static function redact(string $text): string {
        $name = self::NAME;
        $honorific = self::HONORIFIC;
        $salutation = self::SALUTATION;

        $rules = [
            // Links, emails and phone or id numbers (7+ digits; "3 hours", "12 cases" stay).
            '#\b(?:https?://|www\.)[^\s<>"\']+#iu' => '[link removed]',
            '#[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.\p{L}{2,}#u' => '[email removed]',
            '#\+?(?:\(?\d\)?[\s.-]?){6,}\d#u' => '[number removed]',
            // A sign-off after a dash at the very end: "-- Jane Okonkwo".
            '#(?:^|\s)(?:--+|\x{2014}|\x{2013}|~)\s*\p{L}[^\n]{0,80}$#u' => ' [name removed]',
            // A salutation and name at the very end: "Regards, Tomasz Wielgosz", "Thanks, Dr. Ayers".
            "#(?<=^|[\\n.!?,;]|\\s)(?i:{$salutation})\\s*[,!.:-]?\\s*(?:(?i:{$honorific})\\s+)?(?:{$name}\\.?\\s*){1,4}$#u"
                => '[name removed]',
            // A title and name anywhere: "Prof. Lindqvist", "Mr. Kwame Boateng".
            "#\\b{$honorific}\\s+(?:{$name}\\s?){1,3}#u" => '[name removed] ',
            // A self-introduction: "My name is Olamide Adeyemi".
            "#\\b(?i:my name is|i am|i'm|this is)\\s+(?:{$name}\\s+){1,2}{$name}#u" => '[name removed]',
            // A bare two- or three-word name after the last sentence or on its own last line.
            "#(?<=[.!?]|\\n)\\s*(?:{$name}\\s+){1,2}{$name}\\.?\\s*$#u" => ' [name removed]',
        ];

        $out = trim($text);
        foreach ($rules as $pattern => $replacement) {
            $out = preg_replace($pattern, $replacement, $out);
            if ($out === null) {
                // Invalid UTF-8 or a regex failure: send nothing rather than unredacted text.
                return '';
            }
            $out = trim($out);
        }
        // A removal can leave a dangling separator ("...please. Thanks,").
        $out = trim(preg_replace('#[\s,;:-]+$#u', '', $out) ?? '');
        return preg_replace('#[ \t]{2,}#u', ' ', $out) ?? '';
    }
}
