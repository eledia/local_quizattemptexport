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

        // Get question attempts order of answers and the users positioning of the answer options.
        $choiceorders = [];
        $userdrops = [];
        foreach ($qa->get_step_iterator() as $step) {

            // Get the attempts randomized choice ordering.
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


        // Map the question attempts choice order onto the choice options defined in the question.
        $attemptanswers = [];
        foreach ($choiceorders as $group => $grouporder) {

            if (!isset($attemptanswers[$group])) {
                $attemptanswers[$group] = [];
            }

            foreach ($grouporder as $key => $val) {
                $attemptanswers[$group][$key + 1] = $question->choices[$group][$val]->text;
            }
        }


        // Load html snippet into dom and look for dropzones.
        $dom = domdocument_util::initialize_domdocument($questionhtml);
        $xpath = new \DOMXPath($dom);

        $dropzones = $xpath->query('//div[@class="qtext"]//span[contains(concat(" ", normalize-space(@class), " "), " drop ")]');
        foreach ($dropzones as $dropzone) {

            // Get the definition of the drop place and the group the drop is defined for
            // from the drop zones class attribute values.
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

            // Iterate all of the users drops, try to match them onto the dropzone
            // currently being processed and replace the dropzones text value with
            // the text of the respective user drop. If the user did not drop anything
            // on the dropzone a default value indicating an empty drop zone is used
            // instead.
            $dropval = get_string('ddwtos_emptydrop_placeholderstr', 'local_quizattemptexport');
            foreach ($userdrops as $key => $val) {

                $dropposition = substr($key, 1);
                if ($placeval == 'place' . $dropposition) {
                    if (!empty($val)) {
                        $dropval = $attemptanswers[substr($groupval, -1)][$val];
                    }
                }
            }
            $dropzone->textContent = '[' . $dropval . ']';
        }

        return domdocument_util::save_html($dom);
    }

}
