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
 * Controller for postprocessing of file attachments
 * uploaded within an attempt.
 *
 * @package		local_quizattemptexport
 * @copyright	2021 Ralf Wiederhold
 * @author		Ralf Wiederhold <ralf.wiederhold@eledia.de>
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_quizattemptexport\processing\attachments;

defined('MOODLE_INTERNAL') || die();

class processor {

    public static function execute(\quiz_attempt $attempt) {

        foreach ($attempt->get_slots() as $slot) {

            $questionattempt = $attempt->get_question_attempt($slot);
            $question = $questionattempt->get_question();

            $qualifiedclassname = '\local_quizattemptexport\processing\attachments\methods\\' . $question->qtype->name();
            if (class_exists($qualifiedclassname)) {

                /** @var \local_quizattemptexport\processing\attachments\methods\base $qualifiedclassname */
                $qualifiedclassname::process($attempt, $slot);
            }
        }
    }
}
