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
 * Postprocessing implementation for qtype_essay
 *
 * @package		local_quizattemptexport
 * @copyright	2020 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\html\methods;

use local_quizattemptexport\processing\html\domdocument_util;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class essay extends base {

    /**
     * Depending on the configuration of the essay question instance, the user may have an HTML editor
     * or only a simple textarea to enter his answer into. When the instance is configured to use the
     * textarea his answer will also be displayed within a textarea when reviewing the answer, which causes
     * problems when exporting to PDF. So we check the question HTML for a textarea-Element and replace
     * it if necessary...
     *
     * It was also requested that the user that assessed the question attempt (entered a mark) is displayed.
     * We add his name into the question steps table in the row where the user assigned the mark.
     *
     * @param string $questionhtml
     * @param \quiz_attempt $attempt
     * @param int $slot
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function process(string $questionhtml, \quiz_attempt $attempt, int $slot): string {
        global $DB;

        // Get DOM and XPath.
        $dom = domdocument_util::initialize_domdocument($questionhtml);
        $xpath = new \DOMXPath($dom);

        // Get the textarea if any.
        $textareas = $xpath->query('//textarea');
        foreach ($textareas as $ta) {
            /** @var \DOMElement $ta */

            // Create a div to replace the textarea with.
            $newnode = $dom->createElement('div');
            $newnode->setAttribute('class', 'qtype_essay_editor qtype_essay_response readonly'); // Using classes from the HTML-editor display mode.

            // Create a dom fragment, append text with line breaks and append fragment to div.
            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML(nl2br($ta->textContent));
            $newnode->appendChild($fragment);

            // Replace the textarea with the new div.
            $taparent = $ta->parentNode;
            $taparent->insertBefore($newnode, $ta);
            $taparent->removeChild($ta);
        }

        // Get the question attempt steps and collect the steps
        // where grading happened. Save the step and the graders
        // userid.
        $gradingsteps = [];
        $qa = $attempt->get_question_attempt($slot);
        foreach ($qa->get_step_iterator() as $key => $step) {

            if ($step->get_state() instanceof \question_state_mangrright) {
                $gradingsteps[$key] = $step->get_user_id();

            } else if ($step->get_state() instanceof \question_state_mangrpartial) {
                $gradingsteps[$key] = $step->get_user_id();

            } else if ($step->get_state() instanceof \question_state_mangrwrong) {
                $gradingsteps[$key] = $step->get_user_id();
            }
        }

        // Get the question steps table rows and the specific cells we want to edit in there and append
        // the fullname of the user that has done the grading in the relevant steps.
        $cells = $xpath->query('//div[@class="responsehistoryheader"]/table/tbody/tr/td[@class="cell c2"]');
        foreach ($cells as $key => $cell) {
            /** @var \DOMElement $cell */

            if (!empty($gradingsteps[$key])) {

                $user = $DB->get_record('user', ['id' => $gradingsteps[$key]]);
                $username = get_string('userdeleted', 'moodle');
                if (empty($user->deleted)) {
                    $username = fullname($user);
                }

                $cell->textContent = $cell->textContent . ' (' . $username . ')';
            }

        }

        // Save modified HTML and return.
        return domdocument_util::save_html($dom);
    }

}
