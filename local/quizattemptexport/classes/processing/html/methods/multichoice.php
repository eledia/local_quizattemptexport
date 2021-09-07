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
 * Postprocessing implementation for qtype_multichoice
 *
 * @package		local_quizattemptexport
 * @copyright	2021 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\html\methods;

use local_quizattemptexport\processing\html\domdocument_util;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot . '/mod/quiz/attemptlib.php';
require_once $CFG->dirroot . '/mod/quiz/accessmanager.php';

class multichoice extends base {

    /**
     * Replace font awesome correctness icons with locally embedded icons.
     *
     * @param string $questionhtml
     * @param \quiz_attempt $attempt
     * @param int $slot
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function process(string $questionhtml, \quiz_attempt $attempt, int $slot): string {
        global $CFG, $DB;

        // Get DOM and XPath.
        $dom = domdocument_util::initialize_domdocument($questionhtml);
        $xpath = new \DOMXPath($dom);

        // Get correctness icons if any.
        $icons = $xpath->query('//div[@class="answer"]//i');
        foreach ($icons as $icon) {

            /** @var \DOMElement $icon */

            // Check attributes of element containing the icon for indication of correctness. Decide
            // which image to replace the icon with depending on type of correctness.
            $parentclassvals = $icon->parentNode->getAttribute('class');
            $replacementimgname = '';
            foreach (explode(' ', $parentclassvals) as $val) {
                if ($val == 'correct') {
                    $replacementimgname = 'grade_correct.png';
                } else if ($val == 'incorrect') {
                    $replacementimgname = 'grade_incorrect.png';
                } else if ($val == 'partiallycorrect') {
                    $replacementimgname = 'grade_partiallycorrect.png';
                }
            }

            // Replace icon node with image node containing a data url if a correctness type could be established.
            if (!empty($replacementimgname)) {

                $imgcontent = file_get_contents($CFG->dirroot . '/local/quizattemptexport/pix/' . $replacementimgname);
                $dataurl = 'data:image/png;base64,' . base64_encode($imgcontent);
                $replacementnode = $dom->createElement('img');
                $replacementnode->setAttribute('src', $dataurl);
                $replacementnode->setAttribute('class', 'correctnessicon');
                $replacementnode->setAttribute('width', '13');
                $replacementnode->setAttribute('height', '13');
                $icon->parentNode->replaceChild($replacementnode, $icon);
            }
        }

        // Save modified HTML and return.
        return domdocument_util::save_html($dom);
    }

}
