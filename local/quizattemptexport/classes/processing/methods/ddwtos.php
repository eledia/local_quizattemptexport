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
 * Postprocessing implementation for qtype_ddwtos
 *
 * @package		local_quizattemptexport
 * @copyright	2021 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\methods;

use local_quizattemptexport\processing\domdocument_util;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class ddwtos extends base {

    public static function process(string $questionhtml, \quiz_attempt $attempt, int $slot): string {
        global $DB;

        // Initialize required data.
        $qa = $attempt->get_question_attempt($slot);
        $question = $qa->get_question();
        $answers_raw = $DB->get_records('question_answers', ['question' => $question->id]);

        // Unpack answer options into their related groups.
        $answers = [];
        foreach ($answers_raw as $answerrec) {

            $adddata = unserialize($answerrec->feedback);
            $group = $adddata->draggroup;
            if (!isset($answers[$group])) {
                $answers[$group] = [];
            }

            $answers[$group][] = $answerrec->answer;
        }

        // Get question attempts order of answers and the users positioning of the answer options.
        $choiceorders = [];
        $userdrops = [];
        foreach ($qa->get_step_iterator() as $step) {

            if ($step->get_state() instanceof \question_state_todo) {

                foreach ($step->get_all_data() as $key => $value) {

                    // Makes sure the key is a choice group.
                    if (0 === strpos($key, '_choiceorder')) {

                        $group = substr($key, -1);
                        $choiceorders[$group] = explode(',', $value);
                    }
                }
            }

            // Get the users drops from a complete answer.
            if ($step->get_state() instanceof \question_state_complete) {
                $userdrops = $step->get_all_data();
            }

            // Get the users drops from an incomplete answer.
            if ($step->get_state() instanceof \question_state_invalid) {
                $userdrops = $step->get_all_data();
            }
        }


        // Map the question attempts choice order onto the questions answer options.
        $attemptanswers = [];
        foreach ($choiceorders as $group => $grouporder) {

            if (!isset($attemptanswers[$group])) {
                $attemptanswers[$group] = [];
            }

            foreach ($grouporder as $key => $val) {
                $attemptanswers[$group][$key + 1] = $answers[$group][$val - 1];
            }
        }


        // Load html snippet into dom and look for dropzones.
        $dom = domdocument_util::initialize_domdocument($questionhtml);
        $xpath = new \DOMXPath($dom);

        $dropzones = $xpath->query('//span[contains(concat(" ", normalize-space(@class), " "), " drop ")]');
        foreach ($dropzones as $dropzone) {

            /** @var \DOMElement $dropzone */
            $classattr = $dropzone->getAttribute('class');
            $placeval = '';
            $groupval = '';
            foreach (explode(' ', $classattr) as $attrval) {
                if (0 === strpos($attrval, 'place')) {
                    $placeval = $attrval;
                } else if (0 === strpos($attrval, 'group')) {
                    $groupval = $attrval;
                }
            }
            foreach ($userdrops as $key => $val) {

                $dropposition = substr($key, 1);
                if ($placeval == 'place' . $dropposition) {

                    $dropval = get_string('ddwtos_emptydrop_placeholderstr', 'local_quizattemptexport');
                    if (!empty($val)) {
                        $dropval = $attemptanswers[substr($groupval, -1)][$val];
                    }
                    $dropzone->textContent = '[' . $dropval . ']';
                }
            }
        }

        return domdocument_util::save_html($dom);
    }

}
